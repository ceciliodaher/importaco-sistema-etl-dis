<?php
/**
 * ================================================================================
 * API DE PROCESSAMENTO DE UPLOAD
 * Sistema ETL DI's - Endpoint para processamento de XMLs
 * ================================================================================
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Configurações de erro
error_reporting(E_ALL);
ini_set('display_errors', 0); // Não exibir erros no output JSON

// Tratar OPTIONS request para CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Método não permitido. Use POST.'
    ]);
    exit();
}

try {
    // Carregar configurações
    require_once '../../../config/database.php';
    
    // Verificar se arquivo foi enviado
    if (!isset($_FILES['xml_file']) || $_FILES['xml_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Nenhum arquivo XML válido foi enviado');
    }
    
    $uploadedFile = $_FILES['xml_file'];
    
    // Validações básicas
    $validations = validateUploadedFile($uploadedFile);
    if (!$validations['valid']) {
        throw new Exception($validations['error']);
    }
    
    // Processar arquivo XML
    $result = processXmlFile($uploadedFile);
    
    // Resposta de sucesso
    echo json_encode([
        'success' => true,
        'message' => 'Arquivo processado com sucesso',
        'data' => $result
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} catch (Error $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro interno do servidor: ' . $e->getMessage()
    ]);
}

/**
 * Validar arquivo enviado
 */
function validateUploadedFile($file) {
    // Verificar extensão
    $allowedExtensions = ['xml'];
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($fileExtension, $allowedExtensions)) {
        return [
            'valid' => false,
            'error' => 'Apenas arquivos XML são permitidos'
        ];
    }
    
    // Verificar tamanho (10MB máximo)
    $maxSize = 10 * 1024 * 1024; // 10MB
    if ($file['size'] > $maxSize) {
        return [
            'valid' => false,
            'error' => 'Arquivo muito grande. Máximo permitido: 10MB'
        ];
    }
    
    // Verificar se arquivo está vazio
    if ($file['size'] === 0) {
        return [
            'valid' => false,
            'error' => 'Arquivo está vazio'
        ];
    }
    
    // Verificar MIME type
    $allowedMimeTypes = [
        'text/xml',
        'application/xml',
        'application/x-xml'
    ];
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    // Validação flexível do MIME type (alguns sistemas podem detectar como text/plain)
    if (!in_array($mimeType, $allowedMimeTypes) && $mimeType !== 'text/plain') {
        // Verificar conteúdo XML lendo início do arquivo
        $handle = fopen($file['tmp_name'], 'r');
        $firstLine = fgets($handle, 100);
        fclose($handle);
        
        if (strpos(trim($firstLine), '<?xml') !== 0) {
            return [
                'valid' => false,
                'error' => 'Arquivo não é um XML válido'
            ];
        }
    }
    
    return ['valid' => true];
}

/**
 * Processar arquivo XML
 */
function processXmlFile($file) {
    // Gerar nome único para o arquivo
    $filename = uniqid('di_', true) . '_' . time() . '.xml';
    $uploadPath = '../../../data/uploads/' . $filename;
    
    // Mover arquivo para diretório de uploads
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        throw new Exception('Erro ao salvar arquivo no servidor');
    }
    
    // Tentar fazer parse básico do XML para validação
    try {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($uploadPath);
        
        if ($xml === false) {
            $errors = libxml_get_errors();
            $errorMessage = 'XML inválido: ';
            foreach ($errors as $error) {
                $errorMessage .= trim($error->message) . ' ';
            }
            throw new Exception($errorMessage);
        }
        
        // Verificar se é uma DI válida (verificação básica)
        $isDI = isset($xml->declaracaoImportacao) || 
                isset($xml->DI) || 
                isset($xml->DeclaracaoImportacao) ||
                strpos(strtolower($xml->getName()), 'declaracao') !== false ||
                strpos(strtolower($xml->getName()), 'importacao') !== false;
        
        if (!$isDI) {
            // Log para debug - não falhar se não conseguir identificar
            error_log("Aviso: XML pode não ser uma DI válida: " . $filename);
        }
        
    } catch (Exception $e) {
        // Remover arquivo inválido
        if (file_exists($uploadPath)) {
            unlink($uploadPath);
        }
        throw new Exception('Erro ao validar XML: ' . $e->getMessage());
    }
    
    // Simular processamento (por enquanto apenas salva o arquivo)
    // TODO: Implementar parser de DI real aqui
    
    // Registrar no banco (básico por enquanto)
    try {
        $db = getDatabase();
        
        // Inserir registro de processamento
        $sql = "INSERT INTO processamento_arquivos (nome_arquivo, tamanho_arquivo, status, data_upload) 
                VALUES (?, ?, 'uploaded', NOW())";
        
        // Verificar se tabela existe (pode não existir ainda)
        try {
            $db->query($sql, [$filename, $file['size']]);
        } catch (Exception $e) {
            // Se tabela não existe, apenas log
            error_log("Aviso: Não foi possível registrar no banco: " . $e->getMessage());
        }
        
    } catch (Exception $e) {
        error_log("Erro no banco de dados: " . $e->getMessage());
        // Não falhar por erro de banco neste momento
    }
    
    return [
        'filename' => $filename,
        'original_name' => $file['name'],
        'size' => $file['size'],
        'upload_path' => $uploadPath,
        'processed_at' => date('Y-m-d H:i:s'),
        'status' => 'uploaded'
    ];
}

/**
 * Função helper para logs
 */
function logError($message, $context = []) {
    $logMessage = date('Y-m-d H:i:s') . ' - ' . $message;
    if (!empty($context)) {
        $logMessage .= ' | Context: ' . json_encode($context);
    }
    error_log($logMessage);
}