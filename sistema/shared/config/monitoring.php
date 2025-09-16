<?php
/**
 * ================================================================================
 * SISTEMA ETL DE DI's - CONFIGURAÇÕES DE MONITORAMENTO
 * Configurações centrais para monitoramento de status e performance
 * Padrão Expertzy
 * Versão: 1.0.0
 * ================================================================================
 */

// Previne acesso direto
if (!defined('ETL_SYSTEM')) {
    define('ETL_SYSTEM', true);
}

/**
 * Configurações de Cache
 */
define('MONITORING_CACHE_DIR', __DIR__ . '/../../data/cache/');
define('MONITORING_CACHE_DURATION', 120); // 2 minutos em segundos
define('MONITORING_QUICK_CACHE_DURATION', 30); // 30 segundos para dados críticos

/**
 * Configurações de Logs
 */
define('MONITORING_LOG_DIR', __DIR__ . '/../../data/logs/');
define('MONITORING_LOG_FILE', MONITORING_LOG_DIR . 'system.log');
define('MONITORING_LOG_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('MONITORING_LOG_ROTATION_COUNT', 5);

/**
 * Configurações de Performance
 */
define('MONITORING_TIMEOUT_DATABASE', 5); // segundos
define('MONITORING_TIMEOUT_FILE_CHECK', 2); // segundos
define('MONITORING_MEMORY_LIMIT_WARNING', 80); // percentual
define('MONITORING_DISK_SPACE_WARNING', 90); // percentual

/**
 * Configurações de Status
 */
$monitoringConfig = [
    'refresh_intervals' => [
        'widget' => 30000,      // 30 segundos
        'dashboard' => 60000,   // 1 minuto
        'background' => 300000  // 5 minutos
    ],
    
    'modules' => [
        'dashboard' => [
            'name' => 'Dashboard ETL',
            'path' => 'dashboard/',
            'critical' => true,
            'health_check_url' => 'dashboard/api/health.php',
            'dependencies' => ['database', 'uploads', 'cache']
        ],
        'fiscal' => [
            'name' => 'Módulo Fiscal',
            'path' => 'modules/fiscal/',
            'critical' => true,
            'health_check_url' => 'modules/fiscal/api/health.php',
            'dependencies' => ['database', 'calculators']
        ],
        'commercial' => [
            'name' => 'Módulo Comercial',
            'path' => 'modules/commercial/',
            'critical' => false,
            'health_check_url' => 'modules/commercial/api/health.php',
            'dependencies' => ['database', 'fiscal']
        ],
        'accounting' => [
            'name' => 'Módulo Contábil',
            'path' => 'modules/accounting/',
            'critical' => false,
            'health_check_url' => 'modules/accounting/api/health.php',
            'dependencies' => ['database', 'fiscal']
        ],
        'billing' => [
            'name' => 'Módulo Faturamento',
            'path' => 'modules/billing/',
            'critical' => false,
            'health_check_url' => 'modules/billing/api/health.php',
            'dependencies' => ['database', 'fiscal', 'commercial']
        ]
    ],
    
    'components' => [
        'database' => [
            'name' => 'Banco de Dados',
            'check_method' => 'checkDatabase',
            'critical' => true,
            'timeout' => 5
        ],
        'uploads' => [
            'name' => 'Diretório de Uploads',
            'check_method' => 'checkUploadsDirectory',
            'critical' => true,
            'timeout' => 2
        ],
        'cache' => [
            'name' => 'Sistema de Cache',
            'check_method' => 'checkCacheSystem',
            'critical' => false,
            'timeout' => 2
        ],
        'calculators' => [
            'name' => 'Engines de Cálculo',
            'check_method' => 'checkCalculators',
            'critical' => true,
            'timeout' => 3
        ],
        'parsers' => [
            'name' => 'Parsers XML',
            'check_method' => 'checkParsers',
            'critical' => true,
            'timeout' => 2
        ]
    ],
    
    'performance_thresholds' => [
        'response_time_warning' => 1000,  // ms
        'response_time_critical' => 3000, // ms
        'memory_usage_warning' => 70,     // %
        'memory_usage_critical' => 85,    // %
        'disk_space_warning' => 80,       // %
        'disk_space_critical' => 90       // %
    ],
    
    'alert_settings' => [
        'enabled' => true,
        'email_notifications' => false,
        'webhook_url' => null,
        'cooldown_period' => 300, // 5 minutos entre alertas
        'retry_attempts' => 3
    ]
];

/**
 * Status possíveis para módulos e componentes
 */
define('STATUS_ONLINE', 'online');
define('STATUS_OFFLINE', 'offline');
define('STATUS_WARNING', 'warning');
define('STATUS_ERROR', 'error');
define('STATUS_MAINTENANCE', 'maintenance');
define('STATUS_DEVELOPING', 'developing');
define('STATUS_PLANNED', 'planned');

/**
 * Códigos de prioridade para logs
 */
define('LOG_LEVEL_DEBUG', 0);
define('LOG_LEVEL_INFO', 1);
define('LOG_LEVEL_WARNING', 2);
define('LOG_LEVEL_ERROR', 3);
define('LOG_LEVEL_CRITICAL', 4);

/**
 * Função para obter configuração
 */
function getMonitoringConfig($key = null) {
    global $monitoringConfig;
    
    if ($key === null) {
        return $monitoringConfig;
    }
    
    return isset($monitoringConfig[$key]) ? $monitoringConfig[$key] : null;
}

/**
 * Função para verificar se o monitoramento está habilitado
 */
function isMonitoringEnabled() {
    return defined('MONITORING_ENABLED') ? MONITORING_ENABLED : true;
}

/**
 * Função para obter diretório raiz do sistema
 */
function getSystemRoot() {
    return dirname(dirname(__DIR__));
}

/**
 * Função para verificar se está em modo de desenvolvimento
 */
function isDevelopmentMode() {
    return defined('DEVELOPMENT_MODE') ? DEVELOPMENT_MODE : false;
}

// Definir constantes de ambiente se não estiverem definidas
if (!defined('MONITORING_ENABLED')) {
    define('MONITORING_ENABLED', true);
}

if (!defined('DEVELOPMENT_MODE')) {
    define('DEVELOPMENT_MODE', false);
}

return $monitoringConfig;