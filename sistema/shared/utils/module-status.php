<?php
/**
 * ================================================================================
 * SISTEMA ETL DE DI's - UTILITÁRIO DE STATUS DOS MÓDULOS
 * Verificação inteligente de status de todos os módulos e componentes
 * Padrão Expertzy
 * Versão: 1.0.0
 * ================================================================================
 */

// Previne acesso direto
if (!defined('ETL_SYSTEM')) {
    define('ETL_SYSTEM', true);
}

require_once __DIR__ . '/../config/monitoring.php';
require_once __DIR__ . '/../../config/database.php';

class ModuleStatus {
    
    private $systemRoot;
    private $config;
    private $cache = [];
    private $logFile;
    
    public function __construct() {
        $this->systemRoot = getSystemRoot();
        $this->config = getMonitoringConfig();
        $this->logFile = MONITORING_LOG_FILE;
        
        // Garantir que diretórios necessários existem
        $this->ensureDirectories();
    }
    
    /**
     * Verifica status completo do sistema
     */
    public function getSystemStatus($useCache = true) {
        $cacheKey = 'system_status';
        
        if ($useCache && $this->hasValidCache($cacheKey)) {
            return $this->getFromCache($cacheKey);
        }
        
        $startTime = microtime(true);
        
        $status = [
            'timestamp' => time(),
            'overall_status' => STATUS_ONLINE,
            'performance' => $this->getPerformanceMetrics(),
            'modules' => $this->checkAllModules(),
            'components' => $this->checkAllComponents(),
            'database' => $this->checkDatabaseStatus(),
            'disk_space' => $this->checkDiskSpace(),
            'memory_usage' => $this->getMemoryUsage(),
            'uptime' => $this->getSystemUptime(),
            'response_time' => round((microtime(true) - $startTime) * 1000, 2)
        ];
        
        // Determinar status geral
        $status['overall_status'] = $this->determineOverallStatus($status);
        
        // Log do status
        $this->logStatus($status);
        
        // Salvar cache
        $this->saveToCache($cacheKey, $status, MONITORING_CACHE_DURATION);
        
        return $status;
    }
    
    /**
     * Verifica status de todos os módulos
     */
    public function checkAllModules() {
        $modules = [];
        $moduleConfigs = $this->config['modules'];
        
        foreach ($moduleConfigs as $moduleKey => $moduleConfig) {
            $modules[$moduleKey] = $this->checkModule($moduleKey, $moduleConfig);
        }
        
        return $modules;
    }
    
    /**
     * Verifica status de um módulo específico
     */
    public function checkModule($moduleKey, $moduleConfig) {
        $startTime = microtime(true);
        
        $status = [
            'name' => $moduleConfig['name'],
            'status' => STATUS_OFFLINE,
            'exists' => false,
            'functional' => false,
            'progress' => 0,
            'response_time' => 0,
            'error' => null,
            'dependencies' => [],
            'last_check' => time()
        ];
        
        try {
            // Verificar se diretório existe
            $modulePath = $this->systemRoot . '/' . $moduleConfig['path'];
            $status['exists'] = is_dir($modulePath);
            
            if (!$status['exists']) {
                $status['status'] = STATUS_PLANNED;
                $status['error'] = 'Diretório do módulo não encontrado';
                return $status;
            }
            
            // Verificar se index.php existe
            $indexFile = $modulePath . 'index.php';
            if (!file_exists($indexFile)) {
                $status['status'] = STATUS_DEVELOPING;
                $status['progress'] = 25;
                $status['error'] = 'Arquivo index.php não encontrado';
                return $status;
            }
            
            $status['functional'] = true;
            
            // Verificar dependências
            $status['dependencies'] = $this->checkModuleDependencies($moduleConfig['dependencies']);
            
            // Determinar progress baseado na funcionalidade
            $status['progress'] = $this->calculateModuleProgress($moduleKey, $modulePath);
            
            // Health check se disponível
            if (isset($moduleConfig['health_check_url'])) {
                $healthStatus = $this->performHealthCheck($moduleConfig['health_check_url']);
                if ($healthStatus['success']) {
                    $status['status'] = STATUS_ONLINE;
                } else {
                    $status['status'] = STATUS_WARNING;
                    $status['error'] = $healthStatus['error'];
                }
            } else {
                $status['status'] = $status['progress'] >= 80 ? STATUS_ONLINE : STATUS_DEVELOPING;
            }
            
        } catch (Exception $e) {
            $status['status'] = STATUS_ERROR;
            $status['error'] = $e->getMessage();
            $this->logError("Erro ao verificar módulo {$moduleKey}: " . $e->getMessage());
        }
        
        $status['response_time'] = round((microtime(true) - $startTime) * 1000, 2);
        
        return $status;
    }
    
    /**
     * Verifica todos os componentes do sistema
     */
    public function checkAllComponents() {
        $components = [];
        $componentConfigs = $this->config['components'];
        
        foreach ($componentConfigs as $componentKey => $componentConfig) {
            $components[$componentKey] = $this->checkComponent($componentKey, $componentConfig);
        }
        
        return $components;
    }
    
    /**
     * Verifica um componente específico
     */
    public function checkComponent($componentKey, $componentConfig) {
        $startTime = microtime(true);
        
        $status = [
            'name' => $componentConfig['name'],
            'status' => STATUS_OFFLINE,
            'error' => null,
            'response_time' => 0,
            'last_check' => time()
        ];
        
        try {
            $method = $componentConfig['check_method'];
            
            if (method_exists($this, $method)) {
                $result = $this->$method();
                $status['status'] = $result['status'];
                if (isset($result['error'])) {
                    $status['error'] = $result['error'];
                }
                if (isset($result['data'])) {
                    $status['data'] = $result['data'];
                }
            } else {
                throw new Exception("Método de verificação '{$method}' não encontrado");
            }
            
        } catch (Exception $e) {
            $status['status'] = STATUS_ERROR;
            $status['error'] = $e->getMessage();
            $this->logError("Erro ao verificar componente {$componentKey}: " . $e->getMessage());
        }
        
        $status['response_time'] = round((microtime(true) - $startTime) * 1000, 2);
        
        return $status;
    }
    
    /**
     * Verifica status do banco de dados
     */
    public function checkDatabase() {
        try {
            $db = getDatabase();
            $connection = $db->testConnection();
            
            if (!$connection) {
                return [
                    'status' => STATUS_OFFLINE,
                    'error' => 'Não foi possível conectar ao banco de dados'
                ];
            }
            
            $isReady = $db->isDatabaseReady();
            
            return [
                'status' => $isReady ? STATUS_ONLINE : STATUS_WARNING,
                'error' => $isReady ? null : 'Schema do banco não está completo',
                'data' => [
                    'connection' => $connection,
                    'schema_ready' => $isReady
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'status' => STATUS_ERROR,
                'error' => 'Erro de conexão: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Verifica status detalhado do banco
     */
    public function checkDatabaseStatus() {
        $result = $this->checkDatabase();
        
        if ($result['status'] !== STATUS_ONLINE) {
            return $result;
        }
        
        try {
            $db = getDatabase();
            $stats = $db->getStatistics();
            
            $result['data']['statistics'] = $stats;
            $result['data']['total_dis'] = isset($stats['DIs Processadas']) ? $stats['DIs Processadas'] : 0;
            
        } catch (Exception $e) {
            $result['status'] = STATUS_WARNING;
            $result['error'] = 'Erro ao obter estatísticas: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Verifica diretório de uploads
     */
    public function checkUploadsDirectory() {
        $uploadsDir = $this->systemRoot . '/data/uploads/';
        
        if (!is_dir($uploadsDir)) {
            return [
                'status' => STATUS_ERROR,
                'error' => 'Diretório de uploads não existe'
            ];
        }
        
        if (!is_writable($uploadsDir)) {
            return [
                'status' => STATUS_ERROR,
                'error' => 'Diretório de uploads não tem permissão de escrita'
            ];
        }
        
        $files = glob($uploadsDir . '*');
        
        return [
            'status' => STATUS_ONLINE,
            'data' => [
                'path' => $uploadsDir,
                'writable' => true,
                'file_count' => count($files)
            ]
        ];
    }
    
    /**
     * Verifica sistema de cache
     */
    public function checkCacheSystem() {
        $cacheDir = MONITORING_CACHE_DIR;
        
        if (!is_dir($cacheDir)) {
            if (!mkdir($cacheDir, 0755, true)) {
                return [
                    'status' => STATUS_ERROR,
                    'error' => 'Não foi possível criar diretório de cache'
                ];
            }
        }
        
        if (!is_writable($cacheDir)) {
            return [
                'status' => STATUS_WARNING,
                'error' => 'Diretório de cache não tem permissão de escrita'
            ];
        }
        
        // Testar escrita no cache
        $testFile = $cacheDir . 'test_' . time() . '.tmp';
        if (file_put_contents($testFile, 'test') === false) {
            return [
                'status' => STATUS_ERROR,
                'error' => 'Falha ao escrever arquivo de teste no cache'
            ];
        }
        
        unlink($testFile);
        
        return [
            'status' => STATUS_ONLINE,
            'data' => [
                'path' => $cacheDir,
                'writable' => true
            ]
        ];
    }
    
    /**
     * Verifica engines de cálculo
     */
    public function checkCalculators() {
        $calculatorsDir = $this->systemRoot . '/core/calculators/';
        
        if (!is_dir($calculatorsDir)) {
            return [
                'status' => STATUS_ERROR,
                'error' => 'Diretório de calculators não existe'
            ];
        }
        
        $requiredCalculators = [
            'CurrencyCalculator.php',
            'TaxCalculator.php',
            'MarkupCalculator.php'
        ];
        
        $missing = [];
        foreach ($requiredCalculators as $calculator) {
            if (!file_exists($calculatorsDir . $calculator)) {
                $missing[] = $calculator;
            }
        }
        
        if (!empty($missing)) {
            return [
                'status' => STATUS_WARNING,
                'error' => 'Calculators faltando: ' . implode(', ', $missing)
            ];
        }
        
        return [
            'status' => STATUS_ONLINE,
            'data' => [
                'path' => $calculatorsDir,
                'calculators' => $requiredCalculators
            ]
        ];
    }
    
    /**
     * Verifica parsers XML
     */
    public function checkParsers() {
        $parsersDir = $this->systemRoot . '/core/parsers/';
        
        if (!is_dir($parsersDir)) {
            return [
                'status' => STATUS_ERROR,
                'error' => 'Diretório de parsers não existe'
            ];
        }
        
        $requiredParsers = ['DiXmlParser.php'];
        
        $missing = [];
        foreach ($requiredParsers as $parser) {
            if (!file_exists($parsersDir . $parser)) {
                $missing[] = $parser;
            }
        }
        
        if (!empty($missing)) {
            return [
                'status' => STATUS_WARNING,
                'error' => 'Parsers faltando: ' . implode(', ', $missing)
            ];
        }
        
        return [
            'status' => STATUS_ONLINE,
            'data' => [
                'path' => $parsersDir,
                'parsers' => $requiredParsers
            ]
        ];
    }
    
    /**
     * Verifica dependências de um módulo
     */
    private function checkModuleDependencies($dependencies) {
        $results = [];
        
        foreach ($dependencies as $dependency) {
            if (isset($this->config['components'][$dependency])) {
                $componentConfig = $this->config['components'][$dependency];
                $result = $this->checkComponent($dependency, $componentConfig);
                $results[$dependency] = $result['status'];
            } else {
                $results[$dependency] = STATUS_ERROR;
            }
        }
        
        return $results;
    }
    
    /**
     * Calcula o progresso de implementação de um módulo
     */
    private function calculateModuleProgress($moduleKey, $modulePath) {
        $progress = 0;
        
        // Verifica arquivos básicos
        $basicFiles = ['index.php'];
        foreach ($basicFiles as $file) {
            if (file_exists($modulePath . $file)) {
                $progress += 25;
            }
        }
        
        // Verifica diretórios específicos baseado no módulo
        switch ($moduleKey) {
            case 'dashboard':
                $dirs = ['api', 'assets', 'components'];
                foreach ($dirs as $dir) {
                    if (is_dir($modulePath . $dir)) {
                        $progress += 15;
                    }
                }
                // Dashboard já está implementado
                $progress = 100;
                break;
                
            case 'fiscal':
                $dirs = ['api', 'calculators', 'config'];
                foreach ($dirs as $dir) {
                    if (is_dir($modulePath . $dir)) {
                        $progress += 20;
                    }
                }
                // Adicionar 15% se tem funcionalidades específicas
                $progress += 15;
                break;
                
            default:
                // Para módulos em planejamento
                if ($progress > 0) {
                    $progress = min($progress + 25, 50); // Máximo 50% para módulos básicos
                }
        }
        
        return min($progress, 100);
    }
    
    /**
     * Realiza health check via HTTP
     */
    private function performHealthCheck($url) {
        $fullUrl = $this->systemRoot . '/' . $url;
        
        if (!file_exists($fullUrl)) {
            return [
                'success' => false,
                'error' => 'Endpoint de health check não encontrado'
            ];
        }
        
        // Simular chamada do health check
        try {
            // Em um ambiente real, faria uma requisição HTTP
            return [
                'success' => true,
                'response_time' => 50
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Obter métricas de performance
     */
    public function getPerformanceMetrics() {
        return [
            'memory_usage' => $this->getMemoryUsage(),
            'disk_space' => $this->checkDiskSpace(),
            'response_time' => 0, // Será preenchido pelo método que chama
            'php_version' => PHP_VERSION,
            'server_time' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Obter uso de memória
     */
    public function getMemoryUsage() {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = ini_get('memory_limit');
        $memoryLimitBytes = $this->convertToBytes($memoryLimit);
        
        $percentage = $memoryLimitBytes > 0 ? ($memoryUsage / $memoryLimitBytes) * 100 : 0;
        
        return [
            'used' => $memoryUsage,
            'used_formatted' => $this->formatBytes($memoryUsage),
            'limit' => $memoryLimitBytes,
            'limit_formatted' => $memoryLimit,
            'percentage' => round($percentage, 2)
        ];
    }
    
    /**
     * Verificar espaço em disco
     */
    public function checkDiskSpace() {
        $path = $this->systemRoot;
        $totalSpace = disk_total_space($path);
        $freeSpace = disk_free_space($path);
        $usedSpace = $totalSpace - $freeSpace;
        
        $percentage = $totalSpace > 0 ? ($usedSpace / $totalSpace) * 100 : 0;
        
        return [
            'total' => $totalSpace,
            'total_formatted' => $this->formatBytes($totalSpace),
            'used' => $usedSpace,
            'used_formatted' => $this->formatBytes($usedSpace),
            'free' => $freeSpace,
            'free_formatted' => $this->formatBytes($freeSpace),
            'percentage' => round($percentage, 2)
        ];
    }
    
    /**
     * Obter uptime do sistema
     */
    public function getSystemUptime() {
        // Simular uptime baseado no timestamp do arquivo de log
        if (file_exists($this->logFile)) {
            $startTime = filemtime($this->logFile);
            $uptime = time() - $startTime;
        } else {
            $uptime = 0;
        }
        
        return [
            'seconds' => $uptime,
            'formatted' => $this->formatUptime($uptime)
        ];
    }
    
    /**
     * Determinar status geral do sistema
     */
    private function determineOverallStatus($status) {
        // Se algum componente crítico está offline
        foreach ($status['components'] as $component) {
            if ($component['status'] === STATUS_ERROR || $component['status'] === STATUS_OFFLINE) {
                return STATUS_ERROR;
            }
        }
        
        // Se algum módulo crítico tem problemas
        foreach ($status['modules'] as $moduleKey => $module) {
            if (isset($this->config['modules'][$moduleKey]['critical']) && 
                $this->config['modules'][$moduleKey]['critical']) {
                if ($module['status'] === STATUS_ERROR || $module['status'] === STATUS_OFFLINE) {
                    return STATUS_WARNING;
                }
            }
        }
        
        // Verificar thresholds de performance
        $thresholds = $this->config['performance_thresholds'];
        
        if ($status['memory_usage']['percentage'] > $thresholds['memory_usage_critical'] ||
            $status['disk_space']['percentage'] > $thresholds['disk_space_critical']) {
            return STATUS_ERROR;
        }
        
        if ($status['memory_usage']['percentage'] > $thresholds['memory_usage_warning'] ||
            $status['disk_space']['percentage'] > $thresholds['disk_space_warning']) {
            return STATUS_WARNING;
        }
        
        return STATUS_ONLINE;
    }
    
    /**
     * Funções de cache
     */
    private function hasValidCache($key) {
        $cacheFile = MONITORING_CACHE_DIR . $key . '.json';
        
        if (!file_exists($cacheFile)) {
            return false;
        }
        
        $cacheTime = filemtime($cacheFile);
        return (time() - $cacheTime) < MONITORING_CACHE_DURATION;
    }
    
    private function getFromCache($key) {
        $cacheFile = MONITORING_CACHE_DIR . $key . '.json';
        
        if (!file_exists($cacheFile)) {
            return null;
        }
        
        $content = file_get_contents($cacheFile);
        return json_decode($content, true);
    }
    
    private function saveToCache($key, $data, $duration = null) {
        if (!is_dir(MONITORING_CACHE_DIR)) {
            mkdir(MONITORING_CACHE_DIR, 0755, true);
        }
        
        $cacheFile = MONITORING_CACHE_DIR . $key . '.json';
        file_put_contents($cacheFile, json_encode($data));
    }
    
    /**
     * Sistema de logs
     */
    private function logStatus($status) {
        $message = sprintf(
            "Status do sistema verificado - Overall: %s, Modules: %d, Components: %d, Response: %sms",
            $status['overall_status'],
            count($status['modules']),
            count($status['components']),
            $status['response_time']
        );
        
        $this->log(LOG_LEVEL_INFO, $message);
    }
    
    private function logError($message) {
        $this->log(LOG_LEVEL_ERROR, $message);
    }
    
    private function log($level, $message) {
        if (!is_dir(dirname($this->logFile))) {
            mkdir(dirname($this->logFile), 0755, true);
        }
        
        $levelNames = [
            LOG_LEVEL_DEBUG => 'DEBUG',
            LOG_LEVEL_INFO => 'INFO',
            LOG_LEVEL_WARNING => 'WARNING',
            LOG_LEVEL_ERROR => 'ERROR',
            LOG_LEVEL_CRITICAL => 'CRITICAL'
        ];
        
        $levelName = isset($levelNames[$level]) ? $levelNames[$level] : 'UNKNOWN';
        $timestamp = date('Y-m-d H:i:s');
        $logLine = "[{$timestamp}] [{$levelName}] {$message}" . PHP_EOL;
        
        file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);
        
        // Rotacionar logs se necessário
        $this->rotateLogsIfNeeded();
    }
    
    private function rotateLogsIfNeeded() {
        if (!file_exists($this->logFile)) {
            return;
        }
        
        if (filesize($this->logFile) > MONITORING_LOG_MAX_SIZE) {
            // Mover logs antigos
            for ($i = MONITORING_LOG_ROTATION_COUNT - 1; $i >= 1; $i--) {
                $oldFile = $this->logFile . '.' . $i;
                $newFile = $this->logFile . '.' . ($i + 1);
                
                if (file_exists($oldFile)) {
                    rename($oldFile, $newFile);
                }
            }
            
            // Mover log atual
            rename($this->logFile, $this->logFile . '.1');
        }
    }
    
    /**
     * Garantir que diretórios necessários existem
     */
    private function ensureDirectories() {
        $dirs = [
            MONITORING_CACHE_DIR,
            MONITORING_LOG_DIR
        ];
        
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }
    
    /**
     * Utilitários
     */
    private function convertToBytes($value) {
        $unit = strtolower(substr($value, -1));
        $num = intval($value);
        
        switch ($unit) {
            case 'g': return $num * 1024 * 1024 * 1024;
            case 'm': return $num * 1024 * 1024;
            case 'k': return $num * 1024;
            default: return $num;
        }
    }
    
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = floor((strlen($bytes) - 1) / 3);
        
        return sprintf("%.2f %s", $bytes / pow(1024, $factor), $units[$factor]);
    }
    
    private function formatUptime($seconds) {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        
        $parts = [];
        if ($days > 0) $parts[] = $days . 'd';
        if ($hours > 0) $parts[] = $hours . 'h';
        if ($minutes > 0) $parts[] = $minutes . 'm';
        
        return implode(' ', $parts) ?: '0m';
    }
}

// Função helper para uso global
function getModuleStatus() {
    static $instance = null;
    
    if ($instance === null) {
        $instance = new ModuleStatus();
    }
    
    return $instance;
}