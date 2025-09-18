<?php
/**
 * ================================================================================
 * API EVENTSOURCE/SSE - DADOS EM TEMPO REAL
 * Sistema ETL DI's - Server-Sent Events para atualizações automáticas
 * Performance: Long-polling + Cache invalidation + Push notifications
 * ================================================================================
 */

// Configurar headers para SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Cache-Control');

// Configurar para não bufferizar
ini_set('output_buffering', 'off');
ini_set('zlib.output_compression', false);
if (ob_get_level()) ob_end_clean();

require_once dirname(__DIR__) . '/common/cache.php';
require_once dirname(__DIR__, 3) . '/config/database.php';

// Desabilitar timeout para conexão longa
set_time_limit(0);
ignore_user_abort(false);

/**
 * Classe para gerenciar eventos em tempo real
 */
class RealtimeEventStream 
{
    private $db;
    private $cache;
    private $clientId;
    private $lastEventTime;
    private $eventTypes;
    
    public function __construct() 
    {
        $this->clientId = $_GET['client_id'] ?? uniqid('client_', true);
        $this->eventTypes = $_GET['events'] ?? 'stats,upload,processing';
        $this->lastEventTime = $_GET['last_event_time'] ?? time();
        
        try {
            $this->db = getDatabase();
            $this->cache = getCache();
        } catch (Exception $e) {
            $this->sendEvent('error', ['message' => 'Falha na inicialização do sistema']);
            exit();
        }
    }
    
    /**
     * Iniciar stream de eventos
     */
    public function start() 
    {
        // Enviar evento de conexão estabelecida
        $this->sendEvent('connected', [
            'client_id' => $this->clientId,
            'timestamp' => date('c'),
            'events_subscribed' => explode(',', $this->eventTypes)
        ]);
        
        $lastCheck = time();
        $eventTypesArray = explode(',', $this->eventTypes);
        
        while (true) {
            // Verificar se cliente ainda está conectado
            if (connection_aborted()) {
                break;
            }
            
            $currentTime = time();
            
            try {
                // Verificar eventos por tipo
                foreach ($eventTypesArray as $eventType) {
                    $this->checkAndSendEvents(trim($eventType), $lastCheck);
                }
                
                // Heartbeat a cada 30 segundos
                if ($currentTime - $lastCheck >= 30) {
                    $this->sendHeartbeat();
                    $lastCheck = $currentTime;
                }
                
                // Flush buffer
                flush();
                
                // Aguardar antes da próxima verificação
                sleep(5);
                
            } catch (Exception $e) {
                $this->sendEvent('error', [
                    'message' => 'Erro na verificação de eventos',
                    'error' => $e->getMessage()
                ]);
                
                sleep(10); // Aguardar mais tempo em caso de erro
            }
        }
    }
    
    /**
     * Verificar e enviar eventos por tipo
     */
    private function checkAndSendEvents(string $eventType, int $lastCheck) 
    {
        switch ($eventType) {
            case 'stats':
                $this->checkStatsChanges($lastCheck);
                break;
            case 'upload':
                $this->checkNewUploads($lastCheck);
                break;
            case 'processing':
                $this->checkProcessingStatus($lastCheck);
                break;
            case 'alerts':
                $this->checkSystemAlerts($lastCheck);
                break;
            case 'afrmm':
                $this->checkAFRMMValidation($lastCheck);
                break;
        }
    }
    
    /**
     * Verificar mudanças nas estatísticas
     */
    private function checkStatsChanges(int $lastCheck) 
    {
        try {
            // Verificar mudanças usando cache timestamp
            $cacheKey = 'dashboard:stats';
            $statsTimestamp = $this->cache->get("{$cacheKey}:timestamp");
            
            if ($statsTimestamp && $statsTimestamp > $lastCheck) {
                $newStats = $this->cache->get($cacheKey);
                
                if ($newStats) {
                    $this->sendEvent('stats_update', [
                        'overview' => $newStats['overview'],
                        'alerts' => $newStats['alerts'],
                        'timestamp' => $statsTimestamp
                    ]);
                }
            }
            
        } catch (Exception $e) {
            error_log("Error checking stats changes: " . $e->getMessage());
        }
    }
    
    /**
     * Verificar novos uploads
     */
    private function checkNewUploads(int $lastCheck) 
    {
        try {
            $uploadDir = '../../../data/uploads/';
            
            if (!is_dir($uploadDir)) {
                return;
            }
            
            $newFiles = [];
            $files = glob($uploadDir . '*.xml');
            
            foreach ($files as $file) {
                $fileTime = filemtime($file);
                if ($fileTime > $lastCheck) {
                    $newFiles[] = [
                        'filename' => basename($file),
                        'size' => filesize($file),
                        'upload_time' => $fileTime,
                        'formatted_time' => date('d/m/Y H:i:s', $fileTime),
                        'status' => 'uploaded'
                    ];
                }
            }
            
            if (!empty($newFiles)) {
                $this->sendEvent('new_uploads', [
                    'files' => $newFiles,
                    'count' => count($newFiles)
                ]);
            }
            
        } catch (Exception $e) {
            error_log("Error checking new uploads: " . $e->getMessage());
        }
    }
    
    /**
     * Verificar status de processamento
     */
    private function checkProcessingStatus(int $lastCheck) 
    {
        try {
            if (!$this->db->testConnection()) {
                return;
            }
            
            $pdo = $this->db->getConnection();
            
            // Verificar DIs processadas recentemente
            $stmt = $pdo->prepare("
                SELECT 
                    numero_di,
                    status_processamento,
                    data_processamento,
                    importador_nome,
                    valor_total_cif_brl
                FROM declaracoes_importacao 
                WHERE updated_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
                ORDER BY updated_at DESC
                LIMIT 10
            ");
            
            $stmt->execute();
            $recentlyProcessed = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($recentlyProcessed)) {
                $processed = [];
                $errors = [];
                
                foreach ($recentlyProcessed as $di) {
                    $item = [
                        'numero_di' => $di['numero_di'],
                        'importador' => $di['importador_nome'],
                        'valor_cif' => number_format($di['valor_total_cif_brl'], 2),
                        'timestamp' => strtotime($di['data_processamento'])
                    ];
                    
                    if ($di['status_processamento'] === 'COMPLETO') {
                        $processed[] = $item;
                    } elseif ($di['status_processamento'] === 'ERRO') {
                        $errors[] = $item;
                    }
                }
                
                if (!empty($processed)) {
                    $this->sendEvent('processing_complete', [
                        'dis' => $processed,
                        'count' => count($processed)
                    ]);
                }
                
                if (!empty($errors)) {
                    $this->sendEvent('processing_error', [
                        'dis' => $errors,
                        'count' => count($errors)
                    ]);
                }
            }
            
        } catch (Exception $e) {
            error_log("Error checking processing status: " . $e->getMessage());
        }
    }
    
    /**
     * Verificar alertas do sistema
     */
    private function checkSystemAlerts(int $lastCheck) 
    {
        try {
            $alerts = [];
            
            // Verificar espaço em disco
            $uploadDir = '../../../data/uploads/';
            if (is_dir($uploadDir)) {
                $totalSpace = disk_total_space($uploadDir);
                $freeSpace = disk_free_space($uploadDir);
                $usedPercent = (($totalSpace - $freeSpace) / $totalSpace) * 100;
                
                if ($usedPercent > 90) {
                    $alerts[] = [
                        'type' => 'disk_space_critical',
                        'level' => 'critical',
                        'message' => 'Espaço em disco crítico: ' . number_format($usedPercent, 1) . '% usado',
                        'timestamp' => time()
                    ];
                } elseif ($usedPercent > 80) {
                    $alerts[] = [
                        'type' => 'disk_space_warning',
                        'level' => 'warning',
                        'message' => 'Espaço em disco baixo: ' . number_format($usedPercent, 1) . '% usado',
                        'timestamp' => time()
                    ];
                }
            }
            
            // Verificar status do banco de dados
            if (!$this->db->testConnection()) {
                $alerts[] = [
                    'type' => 'database_connection',
                    'level' => 'critical',
                    'message' => 'Perda de conexão com banco de dados',
                    'timestamp' => time()
                ];
            }
            
            // Verificar cache
            $cacheStats = $this->cache->getStats();
            if ($cacheStats['hit_rate'] < 50) {
                $alerts[] = [
                    'type' => 'cache_performance',
                    'level' => 'warning',
                    'message' => 'Performance do cache baixa: ' . $cacheStats['hit_rate'] . '% hit rate',
                    'timestamp' => time()
                ];
            }
            
            if (!empty($alerts)) {
                $this->sendEvent('system_alerts', [
                    'alerts' => $alerts,
                    'count' => count($alerts)
                ]);
            }
            
        } catch (Exception $e) {
            error_log("Error checking system alerts: " . $e->getMessage());
        }
    }
    
    /**
     * Verificar validações de AFRMM
     */
    private function checkAFRMMValidation(int $lastCheck) 
    {
        try {
            if (!$this->db->testConnection()) {
                return;
            }
            
            $pdo = $this->db->getConnection();
            
            // Verificar AFRMM com divergências altas criados recentemente
            $stmt = $pdo->prepare("
                SELECT 
                    de.numero_di,
                    di.importador_nome,
                    de.valor_informado,
                    de.valor_calculado,
                    de.divergencia_percentual,
                    de.created_at
                FROM despesas_extras de
                JOIN declaracoes_importacao di ON de.numero_di = di.numero_di
                WHERE de.categoria = 'AFRMM'
                    AND de.created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
                    AND ABS(de.divergencia_percentual) > 20
                ORDER BY ABS(de.divergencia_percentual) DESC
                LIMIT 5
            ");
            
            $stmt->execute();
            $afrmm_issues = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($afrmm_issues)) {
                $issues = [];
                
                foreach ($afrmm_issues as $issue) {
                    $issues[] = [
                        'numero_di' => $issue['numero_di'],
                        'importador' => $issue['importador_nome'],
                        'valor_informado' => number_format($issue['valor_informado'], 2),
                        'valor_calculado' => number_format($issue['valor_calculado'], 2),
                        'divergencia' => number_format($issue['divergencia_percentual'], 2) . '%',
                        'severity' => abs($issue['divergencia_percentual']) > 50 ? 'high' : 'medium'
                    ];
                }
                
                $this->sendEvent('afrmm_validation', [
                    'issues' => $issues,
                    'count' => count($issues)
                ]);
            }
            
        } catch (Exception $e) {
            error_log("Error checking AFRMM validation: " . $e->getMessage());
        }
    }
    
    /**
     * Enviar heartbeat
     */
    private function sendHeartbeat() 
    {
        $this->sendEvent('heartbeat', [
            'timestamp' => time(),
            'server_time' => date('c'),
            'client_id' => $this->clientId
        ]);
    }
    
    /**
     * Enviar evento SSE
     */
    private function sendEvent(string $event, array $data) 
    {
        echo "event: {$event}\n";
        echo "data: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
        
        // Log do evento para debug (apenas em desenvolvimento)
        if (isset($_ENV['ETL_ENVIRONMENT']) && $_ENV['ETL_ENVIRONMENT'] === 'development') {
            error_log("SSE Event [{$event}]: " . json_encode($data));
        }
    }
}

// Verificar se é uma conexão SSE válida
if ($_SERVER['HTTP_ACCEPT'] === 'text/event-stream') {
    try {
        $eventStream = new RealtimeEventStream();
        $eventStream->start();
    } catch (Exception $e) {
        echo "event: error\n";
        echo "data: " . json_encode([
            'message' => 'Erro na inicialização do stream de eventos',
            'error' => $e->getMessage()
        ]) . "\n\n";
        flush();
    }
} else {
    // Resposta para requisições não-SSE
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Este endpoint requer conexão Server-Sent Events',
        'usage' => 'Conecte usando EventSource JavaScript ou HTTP Accept: text/event-stream'
    ]);
}
?>