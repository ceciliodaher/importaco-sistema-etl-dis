<?php
/**
 * ================================================================================
 * SISTEMA DE DOWNLOAD SEGURO PARA EXPORTAÇÕES
 * Sistema ETL DI's - Download com autenticação e logs
 * Features: Token validation, rate limiting, audit trail
 * ================================================================================
 */

require_once '../common/response.php';
require_once '../../../config/database.php';

/**
 * Classe para gerenciar downloads seguros
 */
class SecureDownloadHandler 
{
    private $db;
    private $downloadDir;
    private $maxDownloadsPerHour = 50;
    private $allowedExtensions = ['json', 'pdf', 'xlsx', 'csv'];
    
    public function __construct() 
    {
        $this->db = getDatabase()->getConnection();
        $this->downloadDir = __DIR__ . '/../../exports/';
    }
    
    /**
     * Processar requisição de download
     */
    public function handleDownload(): void 
    {
        try {
            // Verificar método
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                $this->sendError('Método não permitido', 405);
                return;
            }
            
            // Extrair parâmetros
            $fileName = $this->getFileName();
            $token = $this->getToken();
            
            if (!$fileName || !$token) {
                $this->sendError('Parâmetros obrigatórios ausentes', 400);
                return;
            }
            
            // Validar token
            $tokenData = $this->validateToken($token);
            if (!$tokenData) {
                $this->sendError('Token inválido ou expirado', 403);
                return;
            }
            
            // Verificar se arquivo corresponde ao token
            if ($tokenData['file'] !== $fileName) {
                $this->sendError('Arquivo não corresponde ao token', 403);
                return;
            }
            
            // Verificar rate limiting
            if (!$this->checkRateLimit()) {
                $this->sendError('Limite de downloads excedido', 429);
                return;
            }
            
            // Verificar se arquivo existe e é seguro
            $filePath = $this->validateAndGetFilePath($fileName);
            if (!$filePath) {
                $this->sendError('Arquivo não encontrado ou inválido', 404);
                return;
            }
            
            // Registrar download
            $this->logDownload($tokenData['export_id'], $fileName, $filePath);
            
            // Enviar arquivo
            $this->serveFile($filePath, $fileName);
            
        } catch (Exception $e) {
            error_log("Download Handler Error: " . $e->getMessage());
            $this->sendError('Erro interno do servidor', 500);
        }
    }
    
    /**
     * Extrair nome do arquivo da URL
     */
    private function getFileName(): ?string 
    {
        $pathInfo = pathinfo($_SERVER['REQUEST_URI']);
        $fileName = $pathInfo['basename'] ?? '';
        
        // Remover query string
        $fileName = explode('?', $fileName)[0];
        
        return !empty($fileName) ? $fileName : null;
    }
    
    /**
     * Extrair token da query string
     */
    private function getToken(): ?string 
    {
        return $_GET['token'] ?? null;
    }
    
    /**
     * Validar token de download
     */
    private function validateToken(string $token): ?array 
    {
        try {
            $tokenData = json_decode(base64_decode($token), true);
            
            if (!$tokenData || !isset($tokenData['expires'])) {
                return null;
            }
            
            // Verificar expiração
            if (time() > $tokenData['expires']) {
                return null;
            }
            
            // Verificar hash de segurança (se presente)
            if (isset($tokenData['hash'])) {
                $expectedHash = hash('sha256', $tokenData['file'] . $tokenData['export_id'] . 'secret_key');
                if ($tokenData['hash'] !== $expectedHash) {
                    return null;
                }
            }
            
            return $tokenData;
            
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Verificar rate limiting
     */
    private function checkRateLimit(): bool 
    {
        $clientIp = $this->getClientIP();
        
        $query = "
            SELECT COUNT(*) as download_count
            FROM download_logs 
            WHERE client_ip = ? 
            AND downloaded_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$clientIp]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return ($result['download_count'] ?? 0) < $this->maxDownloadsPerHour;
    }
    
    /**
     * Validar arquivo e retornar caminho seguro
     */
    private function validateAndGetFilePath(string $fileName): ?string 
    {
        // Verificar extensão
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions)) {
            return null;
        }
        
        // Verificar caracteres perigosos
        if (preg_match('/[^a-zA-Z0-9._-]/', $fileName)) {
            return null;
        }
        
        // Construir caminho
        $filePath = $this->downloadDir . $fileName;
        
        // Verificar se existe e está dentro do diretório permitido
        $realPath = realpath($filePath);
        $realDownloadDir = realpath($this->downloadDir);
        
        if (!$realPath || !$realDownloadDir || !str_starts_with($realPath, $realDownloadDir)) {
            return null;
        }
        
        // Verificar se é arquivo (não diretório)
        if (!is_file($realPath)) {
            return null;
        }
        
        return $realPath;
    }
    
    /**
     * Registrar download no log
     */
    private function logDownload(string $exportId, string $fileName, string $filePath): void 
    {
        try {
            $query = "
                INSERT INTO download_logs (
                    export_id, file_name, file_size, client_ip, 
                    user_agent, downloaded_at
                ) VALUES (?, ?, ?, ?, ?, NOW())
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                $exportId,
                $fileName,
                filesize($filePath),
                $this->getClientIP(),
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ]);
            
            // Atualizar contador de downloads no job
            $updateQuery = "
                UPDATE export_jobs 
                SET download_count = COALESCE(download_count, 0) + 1,
                    last_downloaded_at = NOW()
                WHERE export_id = ?
            ";
            
            $updateStmt = $this->db->prepare($updateQuery);
            $updateStmt->execute([$exportId]);
            
        } catch (Exception $e) {
            error_log("Erro ao registrar download: " . $e->getMessage());
        }
    }
    
    /**
     * Servir arquivo para download
     */
    private function serveFile(string $filePath, string $fileName): void 
    {
        // Limpar qualquer output anterior
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Determinar content type
        $contentType = $this->getContentType($fileName);
        
        // Headers de download
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: private, must-revalidate');
        header('Pragma: public');
        header('Expires: 0');
        
        // Headers de segurança
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        
        // Enviar arquivo em chunks para arquivos grandes
        $this->readFileChunked($filePath);
        
        exit;
    }
    
    /**
     * Ler arquivo em chunks para economizar memória
     */
    private function readFileChunked(string $filePath, int $chunkSize = 8192): void 
    {
        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            throw new Exception('Não foi possível abrir o arquivo');
        }
        
        while (!feof($handle)) {
            $chunk = fread($handle, $chunkSize);
            if ($chunk === false) {
                break;
            }
            echo $chunk;
            flush();
        }
        
        fclose($handle);
    }
    
    /**
     * Determinar content type baseado na extensão
     */
    private function getContentType(string $fileName): string 
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        $mimeTypes = [
            'json' => 'application/json',
            'pdf' => 'application/pdf',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'csv' => 'text/csv'
        ];
        
        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }
    
    /**
     * Obter IP do cliente
     */
    private function getClientIP(): string 
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Enviar erro formatado
     */
    private function sendError(string $message, int $code): void 
    {
        http_response_code($code);
        
        // Se for uma requisição AJAX, retornar JSON
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            
            header('Content-Type: application/json');
            echo json_encode([
                'error' => true,
                'message' => $message,
                'code' => $code
            ]);
        } else {
            // Página de erro simples
            echo "<!DOCTYPE html>
<html>
<head>
    <title>Erro {$code}</title>
    <meta charset='utf-8'>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
        .error { color: #dc3545; }
        .code { font-size: 48px; font-weight: bold; margin: 20px 0; }
        .message { font-size: 18px; margin: 20px 0; }
        .back { margin-top: 30px; }
        .back a { color: #FF002D; text-decoration: none; }
    </style>
</head>
<body>
    <div class='error'>
        <div class='code'>{$code}</div>
        <div class='message'>{$message}</div>
        <div class='back'>
            <a href='javascript:history.back()'>← Voltar</a>
        </div>
    </div>
</body>
</html>";
        }
    }
}

// Processar requisição de download
try {
    $handler = new SecureDownloadHandler();
    $handler->handleDownload();
} catch (Exception $e) {
    error_log("Download Error: " . $e->getMessage());
    http_response_code(500);
    echo "Erro interno do servidor";
}