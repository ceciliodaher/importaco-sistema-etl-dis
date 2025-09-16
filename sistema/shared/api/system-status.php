<?php
/**
 * ================================================================================
 * SISTEMA ETL DE DI's - API DE STATUS DO SISTEMA
 * Endpoint RESTful para monitoramento em tempo real
 * Padrão Expertzy
 * Versão: 1.0.0
 * ================================================================================
 */

// Headers CORS e JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Previne acesso direto sem contexto
if (!defined('ETL_SYSTEM')) {
    define('ETL_SYSTEM', true);
}

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0); // Não mostrar erros no JSON

// Carregar dependências
require_once __DIR__ . '/../utils/module-status.php';

class SystemStatusAPI {
    
    private $moduleStatus;
    private $startTime;
    
    public function __construct() {
        $this->startTime = microtime(true);
        $this->moduleStatus = getModuleStatus();
    }
    
    /**
     * Processar requisição da API
     */
    public function handleRequest() {
        try {
            $method = $_SERVER['REQUEST_METHOD'];
            $path = $this->getPath();
            
            switch ($method) {
                case 'GET':
                    return $this->handleGet($path);
                    
                case 'POST':
                    return $this->handlePost($path);
                    
                default:
                    return $this->errorResponse('Método não permitido', 405);
            }
            
        } catch (Exception $e) {
            return $this->errorResponse(
                'Erro interno do servidor: ' . $e->getMessage(),
                500
            );
        }
    }
    
    /**
     * Processar requisições GET
     */
    private function handleGet($path) {
        $useCache = !isset($_GET['no_cache']);
        
        switch ($path) {
            case '/':
            case '/status':
                return $this->getSystemStatus($useCache);
                
            case '/modules':
                return $this->getModulesStatus($useCache);
                
            case '/components':
                return $this->getComponentsStatus($useCache);
                
            case '/performance':
                return $this->getPerformanceMetrics();
                
            case '/health':
                return $this->getHealthCheck();
                
            case '/logs':
                return $this->getRecentLogs();
                
            default:
                // Verificar se é status de módulo específico
                if (preg_match('/^\/modules\/([a-zA-Z_]+)$/', $path, $matches)) {
                    return $this->getModuleStatus($matches[1]);
                }
                
                return $this->errorResponse('Endpoint não encontrado', 404);
        }
    }
    
    /**
     * Processar requisições POST
     */
    private function handlePost($path) {
        $data = json_decode(file_get_contents('php://input'), true);
        
        switch ($path) {
            case '/refresh':
                return $this->refreshStatus($data);
                
            case '/alert':
                return $this->handleAlert($data);
                
            default:
                return $this->errorResponse('Endpoint não encontrado', 404);
        }
    }
    
    /**
     * Obter status completo do sistema
     */
    private function getSystemStatus($useCache = true) {
        $status = $this->moduleStatus->getSystemStatus($useCache);
        
        // Adicionar metadados da API
        $status['api'] = [
            'version' => '1.0.0',
            'response_time' => round((microtime(true) - $this->startTime) * 1000, 2),
            'timestamp' => time(),
            'cache_used' => $useCache
        ];
        
        return $this->successResponse($status);
    }
    
    /**
     * Obter status apenas dos módulos
     */
    private function getModulesStatus($useCache = true) {
        $modules = $this->moduleStatus->checkAllModules();
        
        return $this->successResponse([
            'modules' => $modules,
            'count' => count($modules),
            'timestamp' => time()
        ]);
    }
    
    /**
     * Obter status apenas dos componentes
     */
    private function getComponentsStatus($useCache = true) {
        $components = $this->moduleStatus->checkAllComponents();
        
        return $this->successResponse([
            'components' => $components,
            'count' => count($components),
            'timestamp' => time()
        ]);
    }
    
    /**
     * Obter métricas de performance
     */
    private function getPerformanceMetrics() {
        $metrics = $this->moduleStatus->getPerformanceMetrics();
        $metrics['api_response_time'] = round((microtime(true) - $this->startTime) * 1000, 2);
        
        return $this->successResponse($metrics);
    }
    
    /**
     * Health check simples
     */
    private function getHealthCheck() {
        $health = [
            'status' => 'ok',
            'timestamp' => time(),
            'version' => '1.0.0',
            'uptime' => $this->moduleStatus->getSystemUptime(),
            'response_time' => round((microtime(true) - $this->startTime) * 1000, 2)
        ];
        
        // Verificar componentes críticos
        $components = $this->moduleStatus->checkAllComponents();
        $criticalDown = false;
        
        foreach ($components as $component) {
            if ($component['status'] === STATUS_ERROR || $component['status'] === STATUS_OFFLINE) {
                $criticalDown = true;
                break;
            }
        }
        
        if ($criticalDown) {
            $health['status'] = 'degraded';
        }
        
        return $this->successResponse($health);
    }
    
    /**
     * Obter status de módulo específico
     */
    private function getModuleStatus($moduleKey) {
        $config = getMonitoringConfig();
        
        if (!isset($config['modules'][$moduleKey])) {
            return $this->errorResponse('Módulo não encontrado', 404);
        }
        
        $moduleConfig = $config['modules'][$moduleKey];
        $status = $this->moduleStatus->checkModule($moduleKey, $moduleConfig);
        
        return $this->successResponse([
            'module' => $moduleKey,
            'status' => $status,
            'timestamp' => time()
        ]);
    }
    
    /**
     * Obter logs recentes do sistema
     */
    private function getRecentLogs() {
        $logFile = MONITORING_LOG_FILE;
        $lines = [];
        
        if (file_exists($logFile)) {
            $handle = fopen($logFile, 'r');
            if ($handle) {
                // Ler últimas 50 linhas
                $buffer = 4096;
                $output = '';
                $chunk = '';
                
                fseek($handle, -1, SEEK_END);
                if (fread($handle, 1) != "\n") {
                    $output = "\n";
                }
                
                while (ftell($handle) > 0 && strlen($output) < $buffer * 50) {
                    $seek = min(ftell($handle), $buffer);
                    fseek($handle, -$seek, SEEK_CUR);
                    $output = ($chunk = fread($handle, $seek)) . $output;
                    fseek($handle, -mb_strlen($chunk, '8bit'), SEEK_CUR);
                }
                
                fclose($handle);
                
                $lines = array_slice(explode("\n", trim($output)), -50);
                $lines = array_filter($lines);
            }
        }
        
        return $this->successResponse([
            'logs' => $lines,
            'count' => count($lines),
            'timestamp' => time()
        ]);
    }
    
    /**
     * Forçar refresh do cache
     */
    private function refreshStatus($data) {
        // Limpar cache
        $cacheDir = MONITORING_CACHE_DIR;
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '*.json');
            foreach ($files as $file) {
                unlink($file);
            }
        }
        
        // Obter status atualizado
        $status = $this->moduleStatus->getSystemStatus(false);
        
        return $this->successResponse([
            'message' => 'Cache limpo e status atualizado',
            'status' => $status,
            'timestamp' => time()
        ]);
    }
    
    /**
     * Processar alerta
     */
    private function handleAlert($data) {
        if (!isset($data['type']) || !isset($data['message'])) {
            return $this->errorResponse('Dados de alerta inválidos', 400);
        }
        
        // Log do alerta
        $alertData = [
            'type' => $data['type'],
            'message' => $data['message'],
            'timestamp' => time(),
            'source' => isset($data['source']) ? $data['source'] : 'unknown'
        ];
        
        // Em um sistema real, aqui seria enviado email, webhook, etc.
        
        return $this->successResponse([
            'message' => 'Alerta processado',
            'alert' => $alertData
        ]);
    }
    
    /**
     * Obter path da requisição
     */
    private function getPath() {
        $requestUri = $_SERVER['REQUEST_URI'];
        $scriptName = $_SERVER['SCRIPT_NAME'];
        
        // Remover script name do URI
        $path = str_replace(dirname($scriptName), '', $requestUri);
        $path = str_replace(basename($scriptName), '', $path);
        
        // Remover query string
        if (($pos = strpos($path, '?')) !== false) {
            $path = substr($path, 0, $pos);
        }
        
        return rtrim($path, '/') ?: '/';
    }
    
    /**
     * Resposta de sucesso
     */
    private function successResponse($data, $code = 200) {
        http_response_code($code);
        
        $response = [
            'success' => true,
            'data' => $data,
            'timestamp' => time(),
            'response_time' => round((microtime(true) - $this->startTime) * 1000, 2)
        ];
        
        return json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * Resposta de erro
     */
    private function errorResponse($message, $code = 400) {
        http_response_code($code);
        
        $response = [
            'success' => false,
            'error' => [
                'message' => $message,
                'code' => $code
            ],
            'timestamp' => time(),
            'response_time' => round((microtime(true) - $this->startTime) * 1000, 2)
        ];
        
        return json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}

// Executar API
try {
    $api = new SystemStatusAPI();
    echo $api->handleRequest();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => [
            'message' => 'Erro crítico da API: ' . $e->getMessage(),
            'code' => 500
        ],
        'timestamp' => time()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}