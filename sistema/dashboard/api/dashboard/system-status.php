<?php
/**
 * ================================================================================
 * API DE STATUS DO SISTEMA
 * Verifica status de serviços, diretórios e configurações
 * ================================================================================
 */

require_once '../../../config/database.php';
require_once '../common/response.php';

// Configurar middleware de segurança
apiMiddleware();

try {
    // Verificar status de todos os componentes
    $systemStatus = [
        'database' => checkDatabaseStatus(),
        'directories' => checkDirectoriesStatus(),
        'permissions' => checkPermissionsStatus(),
        'services' => checkServicesStatus(),
        'configuration' => checkConfigurationStatus(),
        'disk_space' => checkDiskSpaceStatus(),
        'performance' => checkPerformanceStatus()
    ];

    // Calcular status geral
    $overallStatus = calculateOverallStatus($systemStatus);
    
    // Obter métricas do sistema
    $metrics = getSystemMetrics();
    
    // Verificar alertas e avisos
    $alerts = checkSystemAlerts($systemStatus);

    // Preparar resposta
    $responseData = [
        'status' => $overallStatus,
        'components' => $systemStatus,
        'metrics' => $metrics,
        'alerts' => $alerts,
        'last_check' => date('c'),
        'uptime' => getSystemUptime()
    ];

    $response = apiSuccess($responseData);
    $response->addMeta('check_interval', '30s');
    $response->addMeta('system_health', $overallStatus['level']);
    
    $response->send();

} catch (Exception $e) {
    error_log("System Status API Error: " . $e->getMessage());
    apiError('Erro ao verificar status do sistema', 500)->send();
}

/**
 * Verificar status do banco de dados
 */
function checkDatabaseStatus(): array 
{
    try {
        $db = getDatabase();
        
        // Testar conexão básica
        $isConnected = $db->testConnection();
        
        if (!$isConnected) {
            return [
                'status' => 'error',
                'message' => 'Não foi possível conectar ao banco de dados',
                'details' => ['connection' => false]
            ];
        }

        // Verificar se schema está pronto
        $isReady = $db->isDatabaseReady();
        
        // Obter estatísticas
        $stats = $db->getStatistics();
        
        // Obter informações do servidor
        $serverInfo = $db->getServerInfo();
        
        // Verificar performance básica
        $startTime = microtime(true);
        $db->query('SELECT 1');
        $queryTime = round((microtime(true) - $startTime) * 1000, 2);

        return [
            'status' => $isReady ? 'healthy' : 'warning',
            'message' => $isReady ? 'Banco de dados operacional' : 'Schema incompleto',
            'details' => [
                'connection' => true,
                'schema_ready' => $isReady,
                'server_version' => $serverInfo['version'] ?? 'unknown',
                'query_time_ms' => $queryTime,
                'statistics' => $stats
            ]
        ];

    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => 'Erro na conexão com banco de dados: ' . $e->getMessage(),
            'details' => ['connection' => false, 'error' => $e->getMessage()]
        ];
    }
}

/**
 * Verificar status dos diretórios
 */
function checkDirectoriesStatus(): array 
{
    $baseDir = dirname(dirname(dirname(__DIR__)));
    
    $requiredDirs = [
        'uploads' => $baseDir . '/data/uploads',
        'processed' => $baseDir . '/data/processed', 
        'exports' => $baseDir . '/data/exports',
        'logs' => $baseDir . '/data/logs',
        'cache' => $baseDir . '/data/cache'
    ];

    $status = [];
    $hasErrors = false;

    foreach ($requiredDirs as $name => $path) {
        $exists = is_dir($path);
        $writable = $exists && is_writable($path);
        
        if (!$exists || !$writable) {
            $hasErrors = true;
        }

        $status[$name] = [
            'path' => $path,
            'exists' => $exists,
            'writable' => $writable,
            'size' => $exists ? formatBytes(getDirSize($path)) : 'N/A'
        ];
    }

    return [
        'status' => $hasErrors ? 'error' : 'healthy',
        'message' => $hasErrors ? 'Alguns diretórios não estão acessíveis' : 'Todos os diretórios OK',
        'details' => $status
    ];
}

/**
 * Verificar permissões do sistema
 */
function checkPermissionsStatus(): array 
{
    $phpUser = get_current_user();
    $webUser = isset($_SERVER['USER']) ? $_SERVER['USER'] : 'unknown';
    
    $permissionChecks = [
        'php_version' => version_compare(PHP_VERSION, '8.1.0', '>='),
        'pdo_mysql' => extension_loaded('pdo_mysql'),
        'json' => extension_loaded('json'),
        'mbstring' => extension_loaded('mbstring'),
        'xml' => extension_loaded('xml'),
        'fileinfo' => extension_loaded('fileinfo')
    ];

    $hasErrors = in_array(false, $permissionChecks);

    return [
        'status' => $hasErrors ? 'error' : 'healthy',
        'message' => $hasErrors ? 'Algumas extensões PHP estão ausentes' : 'Permissões e extensões OK',
        'details' => [
            'php_user' => $phpUser,
            'web_user' => $webUser,
            'php_version' => PHP_VERSION,
            'extensions' => $permissionChecks
        ]
    ];
}

/**
 * Verificar status dos serviços
 */
function checkServicesStatus(): array 
{
    $services = [];
    
    // Verificar MySQL (ServBay)
    $mysqlStatus = checkServicePort('localhost', 3307);
    $services['mysql_servbay'] = [
        'name' => 'MySQL ServBay',
        'port' => 3307,
        'status' => $mysqlStatus ? 'running' : 'stopped',
        'accessible' => $mysqlStatus
    ];

    // Verificar se há outros serviços PHP rodando
    $phpStatus = function_exists('apache_get_version') || 
                 isset($_SERVER['SERVER_SOFTWARE']);
    $services['web_server'] = [
        'name' => 'Servidor Web',
        'status' => $phpStatus ? 'running' : 'unknown',
        'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'PHP Built-in'
    ];

    $hasErrors = !$mysqlStatus;

    return [
        'status' => $hasErrors ? 'error' : 'healthy',
        'message' => $hasErrors ? 'Alguns serviços não estão disponíveis' : 'Todos os serviços OK',
        'details' => $services
    ];
}

/**
 * Verificar configurações do sistema
 */
function checkConfigurationStatus(): array 
{
    global $config;
    
    $configChecks = [
        'database_config' => isset($config['host']) && !empty($config['host']),
        'timezone_set' => date_default_timezone_get() !== false,
        'memory_limit' => (int)ini_get('memory_limit') >= 128,
        'max_upload_size' => (int)ini_get('upload_max_filesize') >= 50,
        'error_reporting' => error_reporting() !== 0
    ];

    $hasWarnings = in_array(false, $configChecks);

    return [
        'status' => $hasWarnings ? 'warning' : 'healthy',
        'message' => $hasWarnings ? 'Algumas configurações podem afetar performance' : 'Configurações OK',
        'details' => [
            'environment' => $_ENV['ETL_ENVIRONMENT'] ?? 'development',
            'timezone' => date_default_timezone_get(),
            'memory_limit' => ini_get('memory_limit'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'max_execution_time' => ini_get('max_execution_time'),
            'checks' => $configChecks
        ]
    ];
}

/**
 * Verificar espaço em disco
 */
function checkDiskSpaceStatus(): array 
{
    $dataDir = dirname(dirname(dirname(__DIR__))) . '/data';
    
    $totalSpace = disk_total_space($dataDir);
    $freeSpace = disk_free_space($dataDir);
    $usedSpace = $totalSpace - $freeSpace;
    $usagePercent = round(($usedSpace / $totalSpace) * 100, 2);

    $status = 'healthy';
    $message = 'Espaço em disco suficiente';
    
    if ($usagePercent > 90) {
        $status = 'error';
        $message = 'Espaço em disco crítico (>90%)';
    } elseif ($usagePercent > 80) {
        $status = 'warning';
        $message = 'Espaço em disco baixo (>80%)';
    }

    return [
        'status' => $status,
        'message' => $message,
        'details' => [
            'total_space' => formatBytes($totalSpace),
            'free_space' => formatBytes($freeSpace),
            'used_space' => formatBytes($usedSpace),
            'usage_percent' => $usagePercent
        ]
    ];
}

/**
 * Verificar performance do sistema
 */
function checkPerformanceStatus(): array 
{
    $startTime = microtime(true);
    
    // Teste de CPU
    $iterations = 100000;
    for ($i = 0; $i < $iterations; $i++) {
        md5($i);
    }
    $cpuTime = microtime(true) - $startTime;
    
    // Teste de memória
    $memoryStart = memory_get_usage();
    $testArray = range(1, 10000);
    $memoryPeak = memory_get_peak_usage() - $memoryStart;
    unset($testArray);
    
    // Teste de disco
    $tempFile = tempnam(sys_get_temp_dir(), 'perf_test');
    $data = str_repeat('test', 1000);
    $diskStart = microtime(true);
    file_put_contents($tempFile, $data);
    $diskWrite = microtime(true) - $diskStart;
    unlink($tempFile);

    // Avaliar performance
    $cpuScore = $cpuTime < 0.1 ? 'excellent' : ($cpuTime < 0.5 ? 'good' : 'slow');
    $memoryScore = $memoryPeak < 1048576 ? 'excellent' : 'normal'; // < 1MB
    $diskScore = $diskWrite < 0.01 ? 'excellent' : ($diskWrite < 0.05 ? 'good' : 'slow');
    
    $overallPerf = ($cpuScore === 'excellent' && $memoryScore === 'excellent' && $diskScore === 'excellent') 
        ? 'excellent' : 'good';

    return [
        'status' => $overallPerf === 'slow' ? 'warning' : 'healthy',
        'message' => "Performance geral: {$overallPerf}",
        'details' => [
            'cpu_test_time' => round($cpuTime * 1000, 2) . 'ms',
            'cpu_score' => $cpuScore,
            'memory_peak' => formatBytes($memoryPeak),
            'memory_score' => $memoryScore,
            'disk_write_time' => round($diskWrite * 1000, 2) . 'ms',
            'disk_score' => $diskScore,
            'overall_score' => $overallPerf
        ]
    ];
}

/**
 * Calcular status geral do sistema
 */
function calculateOverallStatus(array $systemStatus): array 
{
    $errorCount = 0;
    $warningCount = 0;
    $healthyCount = 0;

    foreach ($systemStatus as $component) {
        switch ($component['status']) {
            case 'error':
                $errorCount++;
                break;
            case 'warning':
                $warningCount++;
                break;
            case 'healthy':
                $healthyCount++;
                break;
        }
    }

    if ($errorCount > 0) {
        return [
            'level' => 'error',
            'message' => "Sistema com {$errorCount} erro(s) crítico(s)",
            'color' => '#e74c3c'
        ];
    } elseif ($warningCount > 0) {
        return [
            'level' => 'warning', 
            'message' => "Sistema operacional com {$warningCount} aviso(s)",
            'color' => '#f39c12'
        ];
    } else {
        return [
            'level' => 'healthy',
            'message' => 'Sistema totalmente operacional',
            'color' => '#27ae60'
        ];
    }
}

/**
 * Obter métricas gerais do sistema
 */
function getSystemMetrics(): array 
{
    return [
        'memory_usage' => formatBytes(memory_get_usage(true)),
        'memory_peak' => formatBytes(memory_get_peak_usage(true)),
        'execution_time' => round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 3) . 's',
        'php_version' => PHP_VERSION,
        'server_time' => date('H:i:s'),
        'load_average' => function_exists('sys_getloadavg') ? sys_getloadavg()[0] : 'N/A'
    ];
}

/**
 * Verificar alertas do sistema
 */
function checkSystemAlerts(array $systemStatus): array 
{
    $alerts = [];

    foreach ($systemStatus as $component => $status) {
        if ($status['status'] === 'error') {
            $alerts[] = [
                'type' => 'error',
                'component' => $component,
                'message' => $status['message'],
                'timestamp' => date('c')
            ];
        } elseif ($status['status'] === 'warning') {
            $alerts[] = [
                'type' => 'warning',
                'component' => $component,
                'message' => $status['message'],
                'timestamp' => date('c')
            ];
        }
    }

    return $alerts;
}

/**
 * Verificar se uma porta está aberta
 */
function checkServicePort(string $host, int $port): bool 
{
    $connection = @fsockopen($host, $port, $errno, $errstr, 1);
    if ($connection) {
        fclose($connection);
        return true;
    }
    return false;
}

/**
 * Obter uptime estimado do sistema
 */
function getSystemUptime(): string 
{
    // Para desenvolvimento, simular uptime baseado no tempo de modificação do arquivo de config
    $configFile = dirname(dirname(__DIR__)) . '/config/database.php';
    if (file_exists($configFile)) {
        $uptime = time() - filemtime($configFile);
        return formatUptime($uptime);
    }
    return 'unknown';
}

/**
 * Formatar uptime em formato legível
 */
function formatUptime(int $seconds): string 
{
    $days = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    
    if ($days > 0) return "{$days}d {$hours}h {$minutes}m";
    if ($hours > 0) return "{$hours}h {$minutes}m";
    return "{$minutes}m";
}

/**
 * Formatar bytes em formato legível
 */
function formatBytes(int $bytes): string 
{
    $units = ['B', 'KB', 'MB', 'GB'];
    for ($i = 0; $bytes > 1024; $i++) {
        $bytes /= 1024;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

/**
 * Calcular tamanho de diretório recursivamente
 */
function getDirSize(string $dir): int 
{
    $size = 0;
    if (is_dir($dir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($files as $file) {
            $size += $file->getSize();
        }
    }
    return $size;
}