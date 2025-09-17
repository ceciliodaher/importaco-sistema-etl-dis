<?php
/**
 * ================================================================================
 * API DE VERIFICAÇÃO DE DUPLICATAS DE XMLs
 * Verifica se um XML de DI já foi importado baseado no hash MD5
 * ================================================================================
 */

require_once '../../../config/database.php';
require_once '../common/response.php';

// Configurar middleware de segurança
apiMiddleware();

try {
    // Validar método HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new InvalidArgumentException('Método não permitido. Use POST.');
    }

    // Obter dados do request
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($input === null) {
        // Fallback para form data
        $input = $_POST;
    }

    // Validar parâmetros obrigatórios
    $validator = new ApiValidator();
    $required = ['file_hash'];
    
    if (!$validator->required($input, $required)) {
        throw new InvalidArgumentException(implode(', ', $validator->getErrors()));
    }

    $fileHash = trim($input['file_hash']);
    $fileName = $input['file_name'] ?? null;
    $fileSize = $input['file_size'] ?? null;

    // Validar formato do hash MD5
    if (!preg_match('/^[a-f0-9]{32}$/i', $fileHash)) {
        throw new InvalidArgumentException('Hash MD5 inválido. Deve conter 32 caracteres hexadecimais.');
    }

    // Conectar ao banco
    $db = getDatabase();
    
    if (!$db->isDatabaseReady()) {
        throw new RuntimeException('Banco de dados não está configurado corretamente');
    }

    // Verificar duplicatas na tabela principal
    $duplicateInfo = checkMainDuplicate($db, $fileHash);
    
    // Verificar histórico de uploads
    $uploadHistory = checkUploadHistory($db, $fileHash);
    
    // Verificar duplicatas por nome de arquivo se fornecido
    $nameMatches = [];
    if ($fileName) {
        $nameMatches = checkNameMatches($db, $fileName);
    }

    // Verificar padrões similares
    $similarFiles = findSimilarFiles($db, $fileHash, $fileSize);

    // Determinar se é duplicata
    $isDuplicate = $duplicateInfo['found'] || $uploadHistory['found'];
    
    // Preparar detalhes da verificação
    $details = [
        'hash_match' => $duplicateInfo,
        'upload_history' => $uploadHistory,
        'name_matches' => $nameMatches,
        'similar_files' => $similarFiles,
        'recommendations' => generateRecommendations($isDuplicate, $duplicateInfo, $uploadHistory, $nameMatches)
    ];

    // Log da verificação
    logDuplicateCheck($db, $fileHash, $fileName, $isDuplicate);

    // Preparar resposta
    $responseData = [
        'isDuplicate' => $isDuplicate,
        'confidence' => calculateConfidence($duplicateInfo, $uploadHistory, $nameMatches),
        'details' => $details,
        'summary' => generateSummary($isDuplicate, $duplicateInfo, $uploadHistory),
        'check_timestamp' => date('c')
    ];

    $response = apiSuccess($responseData);
    $response->addMeta('hash_provided', $fileHash);
    $response->addMeta('check_type', 'md5_hash');
    
    $response->send();

} catch (InvalidArgumentException $e) {
    apiError($e->getMessage(), 400)->send();
} catch (RuntimeException $e) {
    apiError($e->getMessage(), 503)->send();
} catch (Exception $e) {
    error_log("Duplicate Check API Error: " . $e->getMessage());
    apiError('Erro interno ao verificar duplicatas', 500)->send();
}

/**
 * Verificar duplicata na tabela principal de DI's
 */
function checkMainDuplicate(Database $db, string $fileHash): array 
{
    try {
        $sql = "
            SELECT 
                id,
                numero_di,
                hash_arquivo,
                nome_arquivo_original,
                data_registro,
                status_processamento,
                valor_total_reais,
                created_at
            FROM declaracoes_importacao 
            WHERE hash_arquivo = ?
            ORDER BY created_at DESC
            LIMIT 1
        ";

        $stmt = $db->query($sql, [$fileHash]);
        $result = $stmt->fetch();

        if ($result) {
            return [
                'found' => true,
                'di_id' => $result['id'],
                'di_numero' => $result['numero_di'],
                'original_filename' => $result['nome_arquivo_original'],
                'upload_date' => $result['created_at'],
                'processing_status' => $result['status_processamento'],
                'total_value' => $result['valor_total_reais'],
                'match_type' => 'exact_hash'
            ];
        }

        return ['found' => false];

    } catch (Exception $e) {
        error_log("Error checking main duplicate: " . $e->getMessage());
        return ['found' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Verificar histórico de uploads (tabela de controle)
 */
function checkUploadHistory(Database $db, string $fileHash): array 
{
    try {
        // Verificar se existe tabela de histórico de uploads
        $tableExists = $db->query("SHOW TABLES LIKE 'upload_history'")->fetch();
        
        if (!$tableExists) {
            // Criar tabela de histórico se não existir
            createUploadHistoryTable($db);
            return ['found' => false, 'table_created' => true];
        }

        $sql = "
            SELECT 
                id,
                file_hash,
                original_filename,
                file_size,
                upload_status,
                error_message,
                di_id,
                created_at
            FROM upload_history 
            WHERE file_hash = ?
            ORDER BY created_at DESC
            LIMIT 5
        ";

        $stmt = $db->query($sql, [$fileHash]);
        $results = $stmt->fetchAll();

        if (!empty($results)) {
            return [
                'found' => true,
                'count' => count($results),
                'uploads' => array_map(function($upload) {
                    return [
                        'id' => $upload['id'],
                        'filename' => $upload['original_filename'],
                        'file_size' => $upload['file_size'],
                        'status' => $upload['upload_status'],
                        'di_id' => $upload['di_id'],
                        'upload_date' => $upload['created_at'],
                        'error_message' => $upload['error_message']
                    ];
                }, $results)
            ];
        }

        return ['found' => false];

    } catch (Exception $e) {
        error_log("Error checking upload history: " . $e->getMessage());
        return ['found' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Verificar arquivos com nomes similares
 */
function checkNameMatches(Database $db, string $fileName): array 
{
    try {
        // Extrair número da DI do nome do arquivo se possível
        $diNumber = extractDINumberFromFilename($fileName);
        
        $matches = [];

        // Buscar por nome exato
        $sql = "
            SELECT numero_di, nome_arquivo_original, hash_arquivo, created_at
            FROM declaracoes_importacao 
            WHERE nome_arquivo_original = ?
            LIMIT 3
        ";
        $stmt = $db->query($sql, [$fileName]);
        $exactMatches = $stmt->fetchAll();

        if (!empty($exactMatches)) {
            $matches['exact_name'] = $exactMatches;
        }

        // Buscar por número da DI se encontrado
        if ($diNumber) {
            $sql = "
                SELECT numero_di, nome_arquivo_original, hash_arquivo, created_at
                FROM declaracoes_importacao 
                WHERE numero_di = ?
                LIMIT 3
            ";
            $stmt = $db->query($sql, [$diNumber]);
            $diMatches = $stmt->fetchAll();

            if (!empty($diMatches)) {
                $matches['di_number'] = $diMatches;
            }
        }

        // Buscar nomes similares (usando LIKE)
        $baseName = pathinfo($fileName, PATHINFO_FILENAME);
        $sql = "
            SELECT numero_di, nome_arquivo_original, hash_arquivo, created_at
            FROM declaracoes_importacao 
            WHERE nome_arquivo_original LIKE ?
            AND nome_arquivo_original != ?
            LIMIT 5
        ";
        $stmt = $db->query($sql, ["%{$baseName}%", $fileName]);
        $similarMatches = $stmt->fetchAll();

        if (!empty($similarMatches)) {
            $matches['similar_names'] = $similarMatches;
        }

        return $matches;

    } catch (Exception $e) {
        error_log("Error checking name matches: " . $e->getMessage());
        return ['error' => $e->getMessage()];
    }
}

/**
 * Encontrar arquivos similares por tamanho
 */
function findSimilarFiles(Database $db, string $fileHash, $fileSize = null): array 
{
    if (!$fileSize) {
        return [];
    }

    try {
        // Buscar arquivos com tamanho similar (±10%)
        $tolerance = $fileSize * 0.1;
        $minSize = $fileSize - $tolerance;
        $maxSize = $fileSize + $tolerance;

        $sql = "
            SELECT 
                numero_di,
                nome_arquivo_original,
                hash_arquivo,
                tamanho_arquivo,
                created_at,
                ABS(tamanho_arquivo - ?) as size_diff
            FROM declaracoes_importacao 
            WHERE tamanho_arquivo BETWEEN ? AND ?
            AND hash_arquivo != ?
            ORDER BY size_diff ASC
            LIMIT 5
        ";

        $stmt = $db->query($sql, [$fileSize, $minSize, $maxSize, $fileHash]);
        $results = $stmt->fetchAll();

        return array_map(function($file) {
            return [
                'di_numero' => $file['numero_di'],
                'filename' => $file['nome_arquivo_original'],
                'hash' => $file['hash_arquivo'],
                'file_size' => $file['tamanho_arquivo'],
                'size_difference' => $file['size_diff'],
                'upload_date' => $file['created_at']
            ];
        }, $results);

    } catch (Exception $e) {
        error_log("Error finding similar files: " . $e->getMessage());
        return ['error' => $e->getMessage()];
    }
}

/**
 * Gerar recomendações baseadas na verificação
 */
function generateRecommendations(bool $isDuplicate, array $duplicateInfo, array $uploadHistory, array $nameMatches): array 
{
    $recommendations = [];

    if ($isDuplicate) {
        if ($duplicateInfo['found']) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => 'Arquivo já foi processado anteriormente',
                'action' => 'Verifique se não há atualizações no XML antes de prosseguir',
                'details' => "DI {$duplicateInfo['di_numero']} já existe no sistema"
            ];
        }

        if ($uploadHistory['found']) {
            $recommendations[] = [
                'type' => 'error',
                'message' => 'Hash do arquivo encontrado no histórico de uploads',
                'action' => 'Não recomendado fazer upload do mesmo arquivo',
                'details' => "Arquivo foi enviado {$uploadHistory['count']} vez(es)"
            ];
        }
    } else {
        $recommendations[] = [
            'type' => 'success',
            'message' => 'Arquivo não encontrado no sistema',
            'action' => 'Seguro para processar o upload',
            'details' => 'Nenhuma duplicata detectada'
        ];

        if (!empty($nameMatches)) {
            $recommendations[] = [
                'type' => 'info',
                'message' => 'Encontrados arquivos com nomes similares',
                'action' => 'Verifique se não são versões do mesmo documento',
                'details' => 'Análise adicional recomendada'
            ];
        }
    }

    return $recommendations;
}

/**
 * Calcular nível de confiança da verificação
 */
function calculateConfidence(array $duplicateInfo, array $uploadHistory, array $nameMatches): array 
{
    $score = 0;
    $factors = [];

    // Hash exato = 100% de confiança
    if ($duplicateInfo['found']) {
        $score = 100;
        $factors[] = 'Hash MD5 exato encontrado';
    } elseif ($uploadHistory['found']) {
        $score = 95;
        $factors[] = 'Hash encontrado no histórico';
    } else {
        $score = 10; // Base mínima

        if (!empty($nameMatches['exact_name'])) {
            $score += 30;
            $factors[] = 'Nome do arquivo idêntico';
        }

        if (!empty($nameMatches['di_number'])) {
            $score += 40;
            $factors[] = 'Número da DI já existe';
        }

        if (!empty($nameMatches['similar_names'])) {
            $score += 20;
            $factors[] = 'Nomes similares encontrados';
        }
    }

    $level = 'low';
    if ($score >= 90) $level = 'very_high';
    elseif ($score >= 70) $level = 'high';
    elseif ($score >= 50) $level = 'medium';

    return [
        'score' => min($score, 100),
        'level' => $level,
        'factors' => $factors
    ];
}

/**
 * Gerar resumo da verificação
 */
function generateSummary(bool $isDuplicate, array $duplicateInfo, array $uploadHistory): string 
{
    if ($isDuplicate) {
        if ($duplicateInfo['found']) {
            return "Arquivo duplicado: DI {$duplicateInfo['di_numero']} já processada em {$duplicateInfo['upload_date']}";
        } elseif ($uploadHistory['found']) {
            return "Arquivo já foi enviado {$uploadHistory['count']} vez(es) anteriormente";
        }
    }

    return "Arquivo novo, seguro para processamento";
}

/**
 * Registrar verificação de duplicata
 */
function logDuplicateCheck(Database $db, string $fileHash, ?string $fileName, bool $isDuplicate): void 
{
    try {
        // Verificar se tabela de log existe
        $tableExists = $db->query("SHOW TABLES LIKE 'duplicate_checks'")->fetch();
        
        if (!$tableExists) {
            createDuplicateChecksTable($db);
        }

        $sql = "
            INSERT INTO duplicate_checks (
                file_hash, 
                file_name, 
                is_duplicate, 
                check_ip, 
                user_agent,
                created_at
            ) VALUES (?, ?, ?, ?, ?, NOW())
        ";

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

        $db->query($sql, [
            $fileHash,
            $fileName,
            $isDuplicate ? 1 : 0,
            $ip,
            $userAgent
        ]);

    } catch (Exception $e) {
        error_log("Error logging duplicate check: " . $e->getMessage());
    }
}

/**
 * Extrair número da DI do nome do arquivo
 */
function extractDINumberFromFilename(string $fileName): ?string 
{
    // Padrões comuns de DI brasileiras
    $patterns = [
        '/(\d{2}\.\d{7}-\d)/',    // Formato: XX.XXXXXXX-X
        '/(\d{10}-\d)/',         // Formato: XXXXXXXXXX-X
        '/(\d{11})/',            // Formato: XXXXXXXXXXX
        '/DI[_\s-]*(\d+)/',      // Formato: DI_123456789
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $fileName, $matches)) {
            return $matches[1];
        }
    }

    return null;
}

/**
 * Criar tabela de histórico de uploads
 */
function createUploadHistoryTable(Database $db): void 
{
    $sql = "
        CREATE TABLE upload_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            file_hash VARCHAR(32) NOT NULL,
            original_filename VARCHAR(255),
            file_size BIGINT,
            upload_status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
            error_message TEXT,
            di_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_file_hash (file_hash),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $db->query($sql);
}

/**
 * Criar tabela de logs de verificação de duplicatas
 */
function createDuplicateChecksTable(Database $db): void 
{
    $sql = "
        CREATE TABLE duplicate_checks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            file_hash VARCHAR(32) NOT NULL,
            file_name VARCHAR(255),
            is_duplicate BOOLEAN DEFAULT FALSE,
            check_ip VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_file_hash (file_hash),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $db->query($sql);
}