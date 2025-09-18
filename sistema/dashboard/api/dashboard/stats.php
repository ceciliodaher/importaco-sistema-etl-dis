<?php
/**
 * ================================================================================
 * API DE ESTATÍSTICAS DO DASHBOARD - OTIMIZADA COM CACHE
 * Sistema ETL DI's - Endpoint para dados em tempo real (< 500ms)
 * Performance: Cache L1 (APCu) + L2 (Redis) + Views MySQL otimizadas
 * ================================================================================
 */

require_once dirname(__DIR__) . '/common/response.php';
require_once dirname(__DIR__) . '/common/cache.php';
require_once dirname(__DIR__) . '/common/cache-headers.php';
require_once dirname(__DIR__, 3) . '/config/database.php';

// Headers de performance e cache
CacheHeaders::enableCompression();
CacheHeaders::setAPIHeaders(300); // 5 minutos de cache

// Middleware de inicialização
apiMiddleware();

try {
    // Verificar se é GET
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        apiError('Método não permitido. Use GET.', 405)->send();
    }

    // Verificar se há dados mínimos no banco antes de processar
    $dataValidation = validateMinimumData();
    
    if (!$dataValidation['has_data']) {
        // Retornar estrutura vazia mas válida quando não há dados
        $response = apiSuccess([
            'overview' => getEmptyOverview(),
            'performance' => getEmptyPerformance(),
            'trends' => [],
            'alerts' => getEmptyAlerts(),
            'system_health' => $dataValidation['system_health'],
            'data_status' => [
                'has_data' => false,
                'reason' => $dataValidation['reason'],
                'recommendations' => $dataValidation['recommendations']
            ]
        ]);
        
        $response->addMeta('data_available', false);
        $response->addMeta('total_records', 0);
        $response->send();
    }

    // Inicializar cache do dashboard
    $cache = getDashboardCache();
    $response = apiSuccess();
    
    // Obter estatísticas com cache inteligente
    $stats = $cache->getStats(function() {
        return generateOptimizedStats();
    });
    
    // Adicionar informações de cache à resposta
    $response->setCacheStats($stats !== null, 'L1+L2');
    $response->addMeta('data_available', true);
    $response->addMeta('total_records', $stats['overview']['total_dis_processadas'] ?? 0);
    
    // Enviar resposta
    $response->setData([
        'overview' => $stats['overview'],
        'performance' => $stats['performance'], 
        'trends' => $stats['trends'],
        'alerts' => $stats['alerts'],
        'system_health' => $stats['system_health'],
        'data_status' => [
            'has_data' => true,
            'last_updated' => date('Y-m-d H:i:s')
        ]
    ])->send();
    
} catch (Exception $e) {
    error_log("API Stats Error: " . $e->getMessage());
    
    // Em caso de erro, tentar retornar dados básicos ao invés de falhar
    $fallbackResponse = apiSuccess([
        'overview' => getEmptyOverview(),
        'performance' => getEmptyPerformance(),
        'trends' => [],
        'alerts' => getEmptyAlerts(),
        'system_health' => [
            'status' => 'error',
            'database' => 'error',
            'cache' => 'disabled',
            'error_message' => $e->getMessage()
        ],
        'data_status' => [
            'has_data' => false,
            'reason' => 'Erro no sistema: ' . $e->getMessage(),
            'recommendations' => [
                'Verifique a conexão com o banco de dados',
                'Consulte os logs do sistema',
                'Entre em contato com o suporte se o problema persistir'
            ]
        ]
    ]);
    
    $fallbackResponse->addMeta('error_occurred', true);
    $fallbackResponse->addMeta('data_available', false);
    $fallbackResponse->send();
}

/**
 * Gerar estatísticas otimizadas usando views MySQL
 * Performance target: < 300ms
 */
function generateOptimizedStats(): array 
{
    try {
        $db = getDatabase();
        $pdo = $db->getConnection();
        
        // Query única combinada para máxima performance
        $stmt = $pdo->query("
            SELECT 
                -- Overview KPIs (v_dashboard_executivo)
                (SELECT dis_ultimo_mes FROM v_dashboard_executivo) as dis_ultimo_mes,
                (SELECT cif_ultimo_mes_milhoes FROM v_dashboard_executivo) as cif_ultimo_mes,
                (SELECT dis_mes_anterior FROM v_dashboard_executivo) as dis_mes_anterior,
                (SELECT cif_mes_anterior_milhoes FROM v_dashboard_executivo) as cif_mes_anterior,
                (SELECT total_dis_processadas FROM v_dashboard_executivo) as total_dis,
                (SELECT total_importadores FROM v_dashboard_executivo) as total_importadores,
                (SELECT usd_taxa_media_30d FROM v_dashboard_executivo) as taxa_usd_30d,
                
                -- Status atual
                (SELECT dis_completas FROM v_dashboard_executivo) as dis_completas,
                (SELECT dis_pendentes FROM v_dashboard_executivo) as dis_pendentes,
                (SELECT dis_erro FROM v_dashboard_executivo) as dis_erro,
                
                -- Alertas AFRMM
                (SELECT afrmm_nao_validados FROM v_dashboard_executivo) as afrmm_alertas,
                (SELECT afrmm_divergencia_alta FROM v_dashboard_executivo) as afrmm_divergencias
        ");
        
        $data = $stmt->fetch();
        
        // Calcular variações percentuais
        $variacao_dis = calculatePercentChange($data['dis_ultimo_mes'], $data['dis_mes_anterior']);
        $variacao_cif = calculatePercentChange($data['cif_ultimo_mes'], $data['cif_mes_anterior']);
        
        // Obter trends dos últimos 6 meses para gráfico
        $trends = getTrendsData($pdo);
        
        // Verificar saúde do sistema
        $systemHealth = getSystemHealthMetrics($db);
        
        return [
            'overview' => [
                'dis_periodo_atual' => (int)$data['dis_ultimo_mes'],
                'dis_variacao_pct' => $variacao_dis,
                'cif_periodo_atual' => (float)$data['cif_ultimo_mes'],
                'cif_variacao_pct' => $variacao_cif,
                'total_dis_processadas' => (int)$data['total_dis'],
                'total_importadores' => (int)$data['total_importadores'],
                'taxa_usd_media' => (float)$data['taxa_usd_30d']
            ],
            'performance' => [
                'dis_completas' => (int)$data['dis_completas'],
                'dis_pendentes' => (int)$data['dis_pendentes'],
                'dis_erro' => (int)$data['dis_erro'],
                'taxa_sucesso' => round(($data['dis_completas'] / max(1, $data['total_dis'])) * 100, 2),
                'tempo_medio_processamento' => getAverageProcessingTime($pdo)
            ],
            'trends' => $trends,
            'alerts' => [
                'afrmm_nao_validados' => (int)$data['afrmm_alertas'],
                'afrmm_divergencia_alta' => (int)$data['afrmm_divergencias'],
                'dis_pendentes' => (int)$data['dis_pendentes'],
                'dis_erro' => (int)$data['dis_erro'],
                'total_alertas' => (int)$data['afrmm_alertas'] + (int)$data['afrmm_divergencias'] + (int)$data['dis_pendentes'] + (int)$data['dis_erro']
            ],
            'system_health' => $systemHealth
        ];
        
    } catch (Exception $e) {
        error_log("Error generating stats: " . $e->getMessage());
        
        // Fallback com dados básicos
        return [
            'overview' => [
                'dis_periodo_atual' => 0,
                'dis_variacao_pct' => 0,
                'cif_periodo_atual' => 0,
                'cif_variacao_pct' => 0,
                'total_dis_processadas' => 0,
                'total_importadores' => 0,
                'taxa_usd_media' => 5.50
            ],
            'performance' => [
                'dis_completas' => 0,
                'dis_pendentes' => 0,
                'dis_erro' => 0,
                'taxa_sucesso' => 0,
                'tempo_medio_processamento' => 0
            ],
            'trends' => [],
            'alerts' => [
                'total_alertas' => 0,
                'afrmm_nao_validados' => 0,
                'afrmm_divergencia_alta' => 0,
                'dis_pendentes' => 0,
                'dis_erro' => 0
            ],
            'system_health' => [
                'status' => 'warning',
                'database' => 'offline',
                'cache' => 'disabled',
                'disk_usage' => 0
            ]
        ];
    }
}

/**
 * Obter dados de tendência dos últimos 6 meses
 */
function getTrendsData(PDO $pdo): array 
{
    try {
        $stmt = $pdo->query("
            SELECT 
                ano_mes,
                total_dis,
                cif_total_milhoes,
                ii_total_milhoes,
                ipi_total_milhoes,
                usd_taxa_media
            FROM v_performance_fiscal 
            WHERE ano_mes >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 6 MONTH), '%Y-%m')
            ORDER BY ano_mes ASC
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Obter métricas de saúde do sistema
 */
function getSystemHealthMetrics(Database $db): array 
{
    try {
        $health = [
            'status' => 'healthy',
            'database' => $db->testConnection() ? 'online' : 'offline',
            'schema_ready' => $db->isDatabaseReady() ? 'ready' : 'pending',
            'cache' => 'unknown',
            'disk_usage' => 0,
            'memory_usage' => formatBytes(memory_get_usage(true))
        ];
        
        // Verificar cache
        $cache = getCache();
        $cacheStats = $cache->getStats();
        $health['cache'] = ($cacheStats['l1_enabled'] || $cacheStats['l2_enabled']) ? 'enabled' : 'disabled';
        $health['cache_hit_rate'] = $cacheStats['hit_rate'];
        
        // Verificar espaço em disco
        $uploadDir = '../../../data/uploads/';
        if (is_dir($uploadDir)) {
            $totalSpace = disk_total_space($uploadDir);
            $freeSpace = disk_free_space($uploadDir);
            $health['disk_usage'] = round((($totalSpace - $freeSpace) / $totalSpace) * 100, 2);
        }
        
        // Determinar status geral
        if ($health['database'] === 'offline' || $health['schema_ready'] === 'pending') {
            $health['status'] = 'critical';
        } elseif ($health['disk_usage'] > 90 || $cacheStats['hit_rate'] < 50) {
            $health['status'] = 'warning';
        }
        
        return $health;
        
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'database' => 'unknown',
            'cache' => 'unknown',
            'disk_usage' => 0,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Calcular variação percentual
 */
function calculatePercentChange(?float $current, ?float $previous): float 
{
    if ($previous === null || $previous == 0) {
        return $current > 0 ? 100 : 0;
    }
    
    return round((($current - $previous) / $previous) * 100, 2);
}

/**
 * Obter tempo médio de processamento
 */
function getAverageProcessingTime(PDO $pdo): float 
{
    try {
        $stmt = $pdo->query("
            SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, updated_at)) as avg_minutes
            FROM declaracoes_importacao 
            WHERE status_processamento = 'COMPLETO'
            AND updated_at > created_at
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        
        $result = $stmt->fetch();
        return round($result['avg_minutes'] ?? 0, 2);
        
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Formatar bytes para leitura
 */
function formatBytes(int $bytes): string 
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= (1 << (10 * $pow));
    
    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Verificar status do sistema
 */
function getSystemStatus() {
    $status = [
        'database' => 'offline',
        'schema' => 'pending',
        'upload_dir' => 'error',
        'processed_dir' => 'error',
        'disk_space' => 'unknown'
    ];
    
    try {
        // Verificar banco de dados
        $db = getDatabase();
        $status['database'] = $db->testConnection() ? 'online' : 'offline';
        $status['schema'] = $db->isDatabaseReady() ? 'ready' : 'pending';
        
        // Verificar diretórios
        $uploadDir = '../../../data/uploads/';
        $processedDir = '../../../data/processed/';
        
        $status['upload_dir'] = is_writable($uploadDir) ? 'writable' : 'readonly';
        $status['processed_dir'] = is_writable($processedDir) ? 'writable' : 'readonly';
        
        // Verificar espaço em disco
        $diskSpace = getDiskSpace();
        $status['disk_space'] = $diskSpace;
        
    } catch (Exception $e) {
        error_log("Erro ao verificar status do sistema: " . $e->getMessage());
    }
    
    return $status;
}

/**
 * Obter atividades recentes
 */
function getRecentActivity() {
    $activities = [];
    
    try {
        // Verificar arquivos recentes no diretório de upload
        $uploadDir = '../../../data/uploads/';
        if (is_dir($uploadDir)) {
            $files = glob($uploadDir . '*.xml');
            
            // Ordenar por data de modificação (mais recente primeiro)
            usort($files, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });
            
            // Pegar os 10 mais recentes
            $recentFiles = array_slice($files, 0, 10);
            
            foreach ($recentFiles as $file) {
                $activities[] = [
                    'type' => 'upload',
                    'message' => 'Arquivo XML enviado: ' . basename($file),
                    'timestamp' => date('Y-m-d H:i:s', filemtime($file)),
                    'details' => [
                        'filename' => basename($file),
                        'size' => formatFileSize(filesize($file))
                    ]
                ];
            }
        }
        
        // Adicionar atividades do banco se disponível
        try {
            $db = getDatabase();
            
            // Tentar obter logs de processamento (se tabela existir)
            $sql = "SELECT * FROM processamento_arquivos 
                    ORDER BY data_upload DESC 
                    LIMIT 5";
            
            $logs = $db->fetchAll($sql);
            
            foreach ($logs as $log) {
                $activities[] = [
                    'type' => 'processing',
                    'message' => 'Arquivo processado: ' . $log['nome_arquivo'],
                    'timestamp' => $log['data_upload'],
                    'details' => [
                        'status' => $log['status'],
                        'size' => formatFileSize($log['tamanho_arquivo'])
                    ]
                ];
            }
            
        } catch (Exception $e) {
            // Ignorar erros de banco por enquanto
        }
        
        // Ordenar por timestamp
        usort($activities, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        
    } catch (Exception $e) {
        error_log("Erro ao obter atividades recentes: " . $e->getMessage());
    }
    
    return array_slice($activities, 0, 10);
}

/**
 * Obter informações de espaço em disco
 */
function getDiskSpace() {
    try {
        $uploadDir = '../../../data/uploads/';
        
        $totalSpace = disk_total_space($uploadDir);
        $freeSpace = disk_free_space($uploadDir);
        $usedSpace = $totalSpace - $freeSpace;
        
        $percentUsed = ($usedSpace / $totalSpace) * 100;
        
        return [
            'total' => formatFileSize($totalSpace),
            'free' => formatFileSize($freeSpace),
            'used' => formatFileSize($usedSpace),
            'percent_used' => round($percentUsed, 2),
            'status' => $percentUsed > 90 ? 'critical' : ($percentUsed > 80 ? 'warning' : 'ok')
        ];
        
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => 'Não foi possível verificar espaço em disco'
        ];
    }
}

/**
 * Formatar tamanho de arquivo
 */
function formatFileSize($bytes) {
    if ($bytes === 0) return '0 Bytes';
    
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes) / log($k));
    
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

/**
 * Obter estatísticas de performance
 */
function getPerformanceStats() {
    return [
        'memory_usage' => formatFileSize(memory_get_usage(true)),
        'peak_memory' => formatFileSize(memory_get_peak_usage(true)),
        'execution_time' => microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"],
        'server_load' => function_exists('sys_getloadavg') ? sys_getloadavg()[0] : 'N/A'
    ];
}

/**
 * Validar se há dados mínimos no banco
 */
function validateMinimumData(): array 
{
    try {
        $db = getDatabase();
        
        // Verificar se o banco está acessível
        if (!$db->testConnection()) {
            return [
                'has_data' => false,
                'reason' => 'Banco de dados não acessível',
                'recommendations' => [
                    'Verifique a configuração do banco de dados',
                    'Certifique-se de que o MySQL está rodando',
                    'Confirme as credenciais de acesso'
                ],
                'system_health' => [
                    'status' => 'critical',
                    'database' => 'offline',
                    'cache' => 'disabled'
                ]
            ];
        }
        
        // Verificar se o schema está pronto
        if (!$db->isDatabaseReady()) {
            return [
                'has_data' => false,
                'reason' => 'Schema do banco não está completo',
                'recommendations' => [
                    'Execute o setup do banco de dados',
                    'Verifique se todas as tabelas foram criadas',
                    'Consulte a documentação de instalação'
                ],
                'system_health' => [
                    'status' => 'warning',
                    'database' => 'online',
                    'cache' => 'disabled'
                ]
            ];
        }
        
        $pdo = $db->getConnection();
        
        // Verificar se há pelo menos uma DI
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM declaracoes_importacao");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] == 0) {
            return [
                'has_data' => false,
                'reason' => 'Nenhuma Declaração de Importação encontrada',
                'recommendations' => [
                    'Faça upload de arquivos XML de DI',
                    'Use a funcionalidade de importação do sistema',
                    'Verifique se os arquivos XML estão no formato correto'
                ],
                'system_health' => [
                    'status' => 'healthy',
                    'database' => 'online',
                    'cache' => 'enabled'
                ]
            ];
        }
        
        return [
            'has_data' => true,
            'total_dis' => (int)$result['count']
        ];
        
    } catch (Exception $e) {
        return [
            'has_data' => false,
            'reason' => 'Erro ao validar dados: ' . $e->getMessage(),
            'recommendations' => [
                'Verifique os logs do sistema',
                'Confirme se o banco está funcionando',
                'Entre em contato com o suporte técnico'
            ],
            'system_health' => [
                'status' => 'error',
                'database' => 'unknown',
                'cache' => 'disabled',
                'error' => $e->getMessage()
            ]
        ];
    }
}

/**
 * Estrutura vazia para overview
 */
function getEmptyOverview(): array 
{
    return [
        'dis_periodo_atual' => 0,
        'dis_variacao_pct' => 0,
        'cif_periodo_atual' => 0.0,
        'cif_variacao_pct' => 0,
        'total_dis_processadas' => 0,
        'total_importadores' => 0,
        'taxa_usd_media' => 0.0
    ];
}

/**
 * Estrutura vazia para performance
 */
function getEmptyPerformance(): array 
{
    return [
        'dis_completas' => 0,
        'dis_pendentes' => 0,
        'dis_erro' => 0,
        'taxa_sucesso' => 0,
        'tempo_medio_processamento' => 0
    ];
}

/**
 * Estrutura vazia para alertas
 */
function getEmptyAlerts(): array 
{
    return [
        'total_alertas' => 0,
        'afrmm_nao_validados' => 0,
        'afrmm_divergencia_alta' => 0,
        'dis_pendentes' => 0,
        'dis_erro' => 0
    ];
}