<?php
/**
 * ================================================================================
 * API DE STATUS DE UPLOAD
 * Monitora o status de uploads de XML em processamento
 * ================================================================================
 */

require_once '../../../config/database.php';
require_once '../common/response.php';

// Configurar middleware de segurança
apiMiddleware();

try {
    $db = getDatabase();
    
    // Buscar uploads recentes
    $sql = "SELECT 
                id,
                hash_arquivo as file_hash,
                nome_arquivo as filename,
                numero_di,
                incoterm,
                status_processamento as status,
                erro_detalhes as error,
                tamanho_arquivo as file_size,
                data_upload as upload_time,
                data_processamento as process_time
            FROM processamento_xmls
            WHERE data_upload >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY data_upload DESC
            LIMIT 20";
    
    $uploads = $db->fetchAll($sql);
    
    // Calcular progresso baseado no status
    $processedUploads = [];
    foreach ($uploads as $upload) {
        $progress = 0;
        switch ($upload['status']) {
            case 'PENDENTE':
                $progress = 0;
                break;
            case 'PROCESSANDO':
                $progress = 50;
                break;
            case 'COMPLETO':
                $progress = 100;
                break;
            case 'ERRO':
                $progress = 0;
                break;
        }
        
        $processedUploads[] = [
            'id' => 'upload-' . $upload['id'],
            'filename' => $upload['filename'],
            'progress' => $progress,
            'status' => $upload['status'],
            'numero_di' => $upload['numero_di'],
            'incoterm' => $upload['incoterm'],
            'error' => $upload['error'],
            'file_size' => formatBytes($upload['file_size'] ?? 0),
            'upload_time' => $upload['upload_time'],
            'process_time' => $upload['process_time']
        ];
    }
    
    // Calcular resumo
    $summary = [
        'total' => count($uploads),
        'completed' => count(array_filter($uploads, fn($u) => $u['status'] === 'COMPLETO')),
        'processing' => count(array_filter($uploads, fn($u) => $u['status'] === 'PROCESSANDO')),
        'pending' => count(array_filter($uploads, fn($u) => $u['status'] === 'PENDENTE')),
        'failed' => count(array_filter($uploads, fn($u) => $u['status'] === 'ERRO'))
    ];
    
    // Preparar resposta compatível com upload.js
    $responseData = [
        'uploads' => $processedUploads,
        'summary' => $summary,
        'server_time' => date('c')
    ];
    
    $response = apiSuccess($responseData);
    $response->addMeta('refresh_interval', '2s');
    $response->send();
    
} catch (Exception $e) {
    error_log("Upload Status API Error: " . $e->getMessage());
    
    // Retornar resposta vazia se tabela não existir ainda
    if (strpos($e->getMessage(), "doesn't exist") !== false) {
        $response = apiSuccess([
            'uploads' => [],
            'summary' => [
                'total' => 0,
                'completed' => 0,
                'processing' => 0,
                'pending' => 0,
                'failed' => 0
            ],
            'server_time' => date('c')
        ]);
        $response->send();
    } else {
        apiError('Erro ao verificar status de uploads', 500)->send();
    }
}

/**
 * Formatar bytes em formato legível
 */
function formatBytes(int $bytes): string 
{
    if ($bytes <= 0) return '0 B';
    
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    
    while ($bytes > 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    
    return round($bytes, 2) . ' ' . $units[$i];
}