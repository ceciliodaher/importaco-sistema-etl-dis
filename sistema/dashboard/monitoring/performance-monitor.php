<?php
/**
 * ================================================================================
 * PERFORMANCE MONITORING SYSTEM
 * Sistema de monitoramento contínuo de performance
 * ================================================================================
 */

class PerformanceMonitor {
    private $config;
    private $results = [];
    private $alerts = [];
    
    public function __construct($config = []) {
        $this->config = array_merge([
            'api_endpoints' => [
                'database-status.php' => ['target' => 200, 'critical' => 500],
                'stats.php' => ['target' => 500, 'critical' => 1000],
                'charts.php?type=all' => ['target' => 1000, 'critical' => 2000],
                'clear-cache.php' => ['target' => 300, 'critical' => 800]
            ],
            'memory_limit' => 50 * 1024 * 1024, // 50MB
            'disk_usage_warning' => 80, // 80%
            'disk_usage_critical' => 90, // 90%
            'check_interval' => 300, // 5 minutes
            'alert_email' => null,
            'log_file' => __DIR__ . '/performance.log'
        ], $config);
    }
    
    /**
     * Executar monitoramento completo
     */
    public function runFullMonitoring() {
        $startTime = microtime(true);
        
        echo "🔍 Iniciando monitoramento de performance...\n\n";
        
        // API Performance
        $this->monitorAPIPerformance();
        
        // Memory Usage
        $this->monitorMemoryUsage();
        
        // Disk Usage
        $this->monitorDiskUsage();
        
        // Database Performance
        $this->monitorDatabasePerformance();
        
        // System Load
        $this->monitorSystemLoad();
        
        $executionTime = (microtime(true) - $startTime) * 1000;
        
        // Generate Report
        $this->generateReport($executionTime);
        
        // Send Alerts
        $this->processAlerts();
        
        // Log Results
        $this->logResults();
        
        return $this->results;
    }
    
    /**
     * Monitorar performance das APIs
     */
    private function monitorAPIPerformance() {
        echo "📡 Monitorando performance das APIs...\n";
        
        $apiResults = [];
        
        foreach ($this->config['api_endpoints'] as $endpoint => $thresholds) {
            $times = [];
            $errors = 0;
            
            // Fazer 3 requisições para cada endpoint
            for ($i = 0; $i < 3; $i++) {
                $startTime = microtime(true);
                
                try {
                    $context = stream_context_create([
                        'http' => [
                            'method' => strpos($endpoint, 'clear-cache') !== false ? 'POST' : 'GET',
                            'timeout' => 10,
                            'header' => 'User-Agent: Performance-Monitor/1.0'
                        ]
                    ]);
                    
                    $url = 'http://localhost:8000/sistema/dashboard/api/dashboard/' . $endpoint;
                    $response = file_get_contents($url, false, $context);
                    
                    if ($response === false) {
                        $errors++;
                        continue;
                    }
                    
                } catch (Exception $e) {
                    $errors++;
                    continue;
                }
                
                $endTime = microtime(true);
                $times[] = ($endTime - $startTime) * 1000;
            }
            
            if (!empty($times)) {
                $avgTime = array_sum($times) / count($times);
                $maxTime = max($times);
                
                $status = 'OK';
                if ($avgTime > $thresholds['critical']) {
                    $status = 'CRITICAL';
                    $this->alerts[] = "API {$endpoint} muito lenta: {$avgTime}ms (limite: {$thresholds['critical']}ms)";
                } elseif ($avgTime > $thresholds['target']) {
                    $status = 'WARNING';
                    $this->alerts[] = "API {$endpoint} acima do target: {$avgTime}ms (target: {$thresholds['target']}ms)";
                }
                
                $apiResults[$endpoint] = [
                    'avg_time' => round($avgTime, 2),
                    'max_time' => round($maxTime, 2),
                    'errors' => $errors,
                    'status' => $status
                ];
                
                echo "   ✓ {$endpoint}: {$avgTime}ms ({$status})\n";
            }
        }
        
        $this->results['api_performance'] = $apiResults;
        echo "\n";
    }
    
    /**
     * Monitorar uso de memória
     */
    private function monitorMemoryUsage() {
        echo "💾 Monitorando uso de memória...\n";
        
        $memoryUsage = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);
        
        $status = 'OK';
        if ($memoryUsage > $this->config['memory_limit']) {
            $status = 'WARNING';
            $this->alerts[] = "Uso de memória alto: " . $this->formatBytes($memoryUsage);
        }
        
        $this->results['memory'] = [
            'current' => $memoryUsage,
            'peak' => $peakMemory,
            'formatted_current' => $this->formatBytes($memoryUsage),
            'formatted_peak' => $this->formatBytes($peakMemory),
            'status' => $status
        ];
        
        echo "   Atual: " . $this->formatBytes($memoryUsage) . "\n";
        echo "   Pico: " . $this->formatBytes($peakMemory) . "\n";
        echo "   Status: {$status}\n\n";
    }
    
    /**
     * Monitorar uso de disco
     */
    private function monitorDiskUsage() {
        echo "💿 Monitorando uso de disco...\n";
        
        $uploadDir = '../../../data/uploads/';
        
        if (is_dir($uploadDir)) {
            $totalSpace = disk_total_space($uploadDir);
            $freeSpace = disk_free_space($uploadDir);
            $usedSpace = $totalSpace - $freeSpace;
            $usagePercent = ($usedSpace / $totalSpace) * 100;
            
            $status = 'OK';
            if ($usagePercent > $this->config['disk_usage_critical']) {
                $status = 'CRITICAL';
                $this->alerts[] = "Uso de disco crítico: {$usagePercent}%";
            } elseif ($usagePercent > $this->config['disk_usage_warning']) {
                $status = 'WARNING';
                $this->alerts[] = "Uso de disco alto: {$usagePercent}%";
            }
            
            $this->results['disk'] = [
                'total' => $totalSpace,
                'free' => $freeSpace,
                'used' => $usedSpace,
                'usage_percent' => round($usagePercent, 2),
                'status' => $status
            ];
            
            echo "   Total: " . $this->formatBytes($totalSpace) . "\n";
            echo "   Usado: " . $this->formatBytes($usedSpace) . " ({$usagePercent}%)\n";
            echo "   Status: {$status}\n\n";
        }
    }
    
    /**
     * Monitorar performance do banco de dados
     */
    private function monitorDatabasePerformance() {
        echo "🗄️ Monitorando performance do banco...\n";
        
        try {
            require_once dirname(__DIR__, 2) . '/config/database.php';
            
            $db = getDatabase();
            $startTime = microtime(true);
            
            // Teste de conexão
            $connected = $db->testConnection();
            $connectionTime = (microtime(true) - $startTime) * 1000;
            
            if (!$connected) {
                $this->alerts[] = "Banco de dados não acessível";
                $this->results['database'] = ['status' => 'CRITICAL', 'connection_time' => 0];
                return;
            }
            
            // Teste de query simples
            $startTime = microtime(true);
            $pdo = $db->getConnection();
            $stmt = $pdo->query("SELECT COUNT(*) FROM declaracoes_importacao");
            $queryTime = (microtime(true) - $startTime) * 1000;
            
            $status = 'OK';
            if ($connectionTime > 100 || $queryTime > 500) {
                $status = 'WARNING';
                $this->alerts[] = "Performance do banco lenta: conexão {$connectionTime}ms, query {$queryTime}ms";
            }
            
            $this->results['database'] = [
                'status' => $status,
                'connection_time' => round($connectionTime, 2),
                'query_time' => round($queryTime, 2),
                'total_dis' => $stmt->fetchColumn()
            ];
            
            echo "   Conexão: {$connectionTime}ms\n";
            echo "   Query: {$queryTime}ms\n";
            echo "   Status: {$status}\n\n";
            
        } catch (Exception $e) {
            $this->alerts[] = "Erro no banco: " . $e->getMessage();
            $this->results['database'] = ['status' => 'ERROR', 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Monitorar carga do sistema
     */
    private function monitorSystemLoad() {
        echo "⚡ Monitorando carga do sistema...\n";
        
        $load = null;
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
        }
        
        $status = 'OK';
        if ($load && $load[0] > 2.0) {
            $status = 'WARNING';
            $this->alerts[] = "Carga do sistema alta: " . round($load[0], 2);
        }
        
        $this->results['system_load'] = [
            'load_1min' => $load ? round($load[0], 2) : null,
            'load_5min' => $load ? round($load[1], 2) : null,
            'load_15min' => $load ? round($load[2], 2) : null,
            'status' => $status
        ];
        
        if ($load) {
            echo "   1min: " . round($load[0], 2) . "\n";
            echo "   5min: " . round($load[1], 2) . "\n";
            echo "   15min: " . round($load[2], 2) . "\n";
        }
        echo "   Status: {$status}\n\n";
    }
    
    /**
     * Gerar relatório
     */
    private function generateReport($executionTime) {
        echo "📊 RELATÓRIO DE PERFORMANCE\n";
        echo str_repeat("=", 50) . "\n";
        
        $totalAlerts = count($this->alerts);
        $overallStatus = $totalAlerts === 0 ? 'HEALTHY' : ($totalAlerts > 3 ? 'CRITICAL' : 'WARNING');
        
        echo "Status Geral: {$overallStatus}\n";
        echo "Total de Alertas: {$totalAlerts}\n";
        echo "Tempo de Execução: " . round($executionTime, 2) . "ms\n\n";
        
        // APIs mais lentas
        $slowestAPI = null;
        $slowestTime = 0;
        foreach ($this->results['api_performance'] ?? [] as $endpoint => $metrics) {
            if ($metrics['avg_time'] > $slowestTime) {
                $slowestTime = $metrics['avg_time'];
                $slowestAPI = $endpoint;
            }
        }
        
        if ($slowestAPI) {
            echo "API mais lenta: {$slowestAPI} ({$slowestTime}ms)\n";
        }
        
        echo "Uso de memória: " . ($this->results['memory']['formatted_current'] ?? 'N/A') . "\n";
        echo "Uso de disco: " . ($this->results['disk']['usage_percent'] ?? 'N/A') . "%\n";
        echo "Status do banco: " . ($this->results['database']['status'] ?? 'N/A') . "\n\n";
        
        if (!empty($this->alerts)) {
            echo "⚠️ ALERTAS:\n";
            foreach ($this->alerts as $alert) {
                echo "   • {$alert}\n";
            }
        }
        
        echo "\n";
    }
    
    /**
     * Processar alertas
     */
    private function processAlerts() {
        if (empty($this->alerts)) {
            return;
        }
        
        // TODO: Implementar envio de email/webhook para alertas críticos
        if ($this->config['alert_email']) {
            // Enviar email de alerta
        }
    }
    
    /**
     * Registrar resultados em log
     */
    private function logResults() {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'results' => $this->results,
            'alerts' => $this->alerts
        ];
        
        $logLine = json_encode($logEntry) . "\n";
        file_put_contents($this->config['log_file'], $logLine, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Formatar bytes
     */
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Health check simples
     */
    public function healthCheck() {
        $health = [
            'status' => 'healthy',
            'checks' => []
        ];
        
        // Check APIs críticas
        $criticalAPIs = ['database-status.php', 'stats.php'];
        foreach ($criticalAPIs as $api) {
            $startTime = microtime(true);
            $context = stream_context_create([
                'http' => ['timeout' => 5]
            ]);
            
            $url = 'http://localhost:8000/sistema/dashboard/api/dashboard/' . $api;
            $response = @file_get_contents($url, false, $context);
            $responseTime = (microtime(true) - $startTime) * 1000;
            
            $health['checks'][$api] = [
                'status' => $response !== false ? 'ok' : 'failed',
                'response_time' => round($responseTime, 2)
            ];
            
            if ($response === false || $responseTime > 1000) {
                $health['status'] = 'unhealthy';
            }
        }
        
        return $health;
    }
}

// Executar se chamado diretamente
if (php_sapi_name() === 'cli') {
    $monitor = new PerformanceMonitor();
    $monitor->runFullMonitoring();
}