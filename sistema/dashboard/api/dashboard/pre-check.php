<?php
/**
 * ================================================================================
 * API DE VALIDAÇÃO PRÉ-CARREGAMENTO - CONTROLE MANUAL DASHBOARD
 * Sistema ETL DI's - Verificação antes de carregar gráficos
 * ================================================================================
 */

require_once dirname(__DIR__) . '/common/response.php';
require_once dirname(__DIR__, 3) . '/config/database.php';

// Middleware de inicialização
apiMiddleware();

try {
    // Verificar se é GET
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        apiError('Método não permitido. Use GET.', 405)->send();
    }

    // Realizar verificação pré-carregamento
    $preCheck = performPreLoadCheck();
    
    // Formatar resposta
    $response = apiSuccess([
        'can_load_charts' => $preCheck['can_load_charts'],
        'available_chart_types' => $preCheck['available_chart_types'],
        'data_requirements' => $preCheck['data_requirements'],
        'missing_requirements' => $preCheck['missing_requirements'],
        'recommendations' => $preCheck['recommendations'],
        'database_health' => $preCheck['database_health']
    ]);
    
    $response->addMeta('pre_check_timestamp', date('Y-m-d H:i:s'));
    $response->addMeta('minimum_data_threshold', $preCheck['thresholds']);
    $response->send();
    
} catch (Exception $e) {
    error_log("API Pre-Check Error: " . $e->getMessage());
    apiError('Erro na validação pré-carregamento: ' . $e->getMessage(), 500)->send();
}

/**
 * Realizar verificação completa pré-carregamento
 */
function performPreLoadCheck(): array 
{
    try {
        $db = getDatabase();
        $pdo = $db->getConnection();
        
        // Verificar se banco está acessível
        if (!$db->testConnection()) {
            return getEmptyPreCheck('Banco de dados não acessível');
        }
        
        // Verificar se schema está pronto
        if (!$db->isDatabaseReady()) {
            return getEmptyPreCheck('Schema do banco não está completo');
        }
        
        // Obter contagens de dados
        $dataCounts = getDataCounts($pdo);
        
        // Definir thresholds mínimos para cada tipo de gráfico
        $thresholds = [
            'overview_charts' => ['dis' => 1, 'adicoes' => 1],
            'trend_charts' => ['dis' => 3, 'months' => 2],
            'fiscal_charts' => ['impostos' => 5, 'adicoes' => 3],
            'performance_charts' => ['dis' => 2, 'processed' => 1],
            'distribution_charts' => ['ncm' => 3, 'adicoes' => 5]
        ];
        
        // Verificar quais tipos de gráficos são possíveis
        $availableCharts = checkAvailableCharts($dataCounts, $thresholds);
        
        // Verificar requirements
        $requirements = checkDataRequirements($dataCounts, $thresholds);
        
        // Verificar saúde das tabelas principais
        $databaseHealth = checkDatabaseHealth($pdo);
        
        // Gerar recomendações
        $recommendations = generateRecommendations($dataCounts, $requirements, $databaseHealth);
        
        return [
            'can_load_charts' => count($availableCharts) > 0,
            'available_chart_types' => $availableCharts,
            'data_requirements' => $requirements['met'],
            'missing_requirements' => $requirements['missing'],
            'recommendations' => $recommendations,
            'database_health' => $databaseHealth,
            'thresholds' => $thresholds,
            'current_data' => $dataCounts
        ];
        
    } catch (Exception $e) {
        throw new Exception("Erro na verificação pré-carregamento: " . $e->getMessage());
    }
}

/**
 * Obter contagens de dados principais
 */
function getDataCounts(PDO $pdo): array 
{
    try {
        $stmt = $pdo->query("
            SELECT 
                -- Contagens básicas
                (SELECT COUNT(*) FROM declaracoes_importacao) as total_dis,
                (SELECT COUNT(*) FROM adicoes) as total_adicoes,
                (SELECT COUNT(*) FROM impostos_adicao) as total_impostos,
                (SELECT COUNT(*) FROM ncm_referencia) as total_ncms,
                
                -- DIs processadas
                (SELECT COUNT(*) FROM declaracoes_importacao WHERE status_processamento = 'COMPLETO') as dis_processadas,
                
                -- Dados com valores válidos
                (SELECT COUNT(*) FROM declaracoes_importacao WHERE cif_total > 0) as dis_com_cif,
                (SELECT COUNT(*) FROM adicoes WHERE valor_vmcv > 0) as adicoes_com_valor,
                (SELECT COUNT(*) FROM impostos_adicao WHERE valor_ii > 0 OR valor_ipi > 0) as impostos_calculados,
                
                -- Período de dados
                (SELECT COUNT(DISTINCT DATE_FORMAT(data_registro, '%Y-%m')) FROM declaracoes_importacao WHERE data_registro IS NOT NULL) as meses_dados,
                (SELECT MIN(data_registro) FROM declaracoes_importacao WHERE data_registro IS NOT NULL) as primeira_di,
                (SELECT MAX(data_registro) FROM declaracoes_importacao WHERE data_registro IS NOT NULL) as ultima_di
        ");
        
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verificar dados por NCM (para charts de distribuição)
        $ncmStmt = $pdo->query("
            SELECT COUNT(DISTINCT ncm_codigo) as ncms_com_dados
            FROM adicoes 
            WHERE ncm_codigo IS NOT NULL AND ncm_codigo != ''
        ");
        $ncmData = $ncmStmt->fetch(PDO::FETCH_ASSOC);
        
        return array_merge($data, $ncmData);
        
    } catch (Exception $e) {
        return [
            'total_dis' => 0,
            'total_adicoes' => 0,
            'total_impostos' => 0,
            'total_ncms' => 0,
            'dis_processadas' => 0,
            'dis_com_cif' => 0,
            'adicoes_com_valor' => 0,
            'impostos_calculados' => 0,
            'meses_dados' => 0,
            'ncms_com_dados' => 0
        ];
    }
}

/**
 * Verificar quais tipos de gráficos estão disponíveis
 */
function checkAvailableCharts(array $dataCounts, array $thresholds): array 
{
    $available = [];
    
    // Overview Charts - básicos do dashboard
    if ($dataCounts['total_dis'] >= $thresholds['overview_charts']['dis'] && 
        $dataCounts['total_adicoes'] >= $thresholds['overview_charts']['adicoes']) {
        $available[] = 'overview_charts';
    }
    
    // Trend Charts - evolução temporal
    if ($dataCounts['total_dis'] >= $thresholds['trend_charts']['dis'] && 
        $dataCounts['meses_dados'] >= $thresholds['trend_charts']['months']) {
        $available[] = 'trend_charts';
    }
    
    // Fiscal Charts - análise tributária
    if ($dataCounts['total_impostos'] >= $thresholds['fiscal_charts']['impostos'] && 
        $dataCounts['adicoes_com_valor'] >= $thresholds['fiscal_charts']['adicoes']) {
        $available[] = 'fiscal_charts';
    }
    
    // Performance Charts - processamento
    if ($dataCounts['dis_processadas'] >= $thresholds['performance_charts']['processed'] && 
        $dataCounts['total_dis'] >= $thresholds['performance_charts']['dis']) {
        $available[] = 'performance_charts';
    }
    
    // Distribution Charts - distribuição por NCM
    if ($dataCounts['ncms_com_dados'] >= $thresholds['distribution_charts']['ncm'] && 
        $dataCounts['adicoes_com_valor'] >= $thresholds['distribution_charts']['adicoes']) {
        $available[] = 'distribution_charts';
    }
    
    return $available;
}

/**
 * Verificar requirements de dados
 */
function checkDataRequirements(array $dataCounts, array $thresholds): array 
{
    $met = [];
    $missing = [];
    
    // Verificar cada requirement
    $requirements = [
        'basic_data' => [
            'description' => 'Dados básicos para dashboard',
            'checks' => [
                'dis_count' => $dataCounts['total_dis'] >= 1,
                'adicoes_count' => $dataCounts['total_adicoes'] >= 1
            ]
        ],
        'fiscal_data' => [
            'description' => 'Dados fiscais para cálculos',
            'checks' => [
                'impostos_count' => $dataCounts['total_impostos'] >= 3,
                'impostos_calculados' => $dataCounts['impostos_calculados'] >= 1
            ]
        ],
        'temporal_data' => [
            'description' => 'Dados temporais para tendências',
            'checks' => [
                'multiple_months' => $dataCounts['meses_dados'] >= 2,
                'time_range' => !empty($dataCounts['primeira_di']) && !empty($dataCounts['ultima_di'])
            ]
        ],
        'quality_data' => [
            'description' => 'Qualidade dos dados',
            'checks' => [
                'dis_with_values' => $dataCounts['dis_com_cif'] >= 1,
                'adicoes_with_values' => $dataCounts['adicoes_com_valor'] >= 1,
                'processed_dis' => $dataCounts['dis_processadas'] >= 1
            ]
        ]
    ];
    
    foreach ($requirements as $key => $requirement) {
        $allPassed = true;
        $failedChecks = [];
        
        foreach ($requirement['checks'] as $checkName => $passed) {
            if (!$passed) {
                $allPassed = false;
                $failedChecks[] = $checkName;
            }
        }
        
        if ($allPassed) {
            $met[] = [
                'requirement' => $key,
                'description' => $requirement['description'],
                'status' => 'met'
            ];
        } else {
            $missing[] = [
                'requirement' => $key,
                'description' => $requirement['description'],
                'status' => 'missing',
                'failed_checks' => $failedChecks
            ];
        }
    }
    
    return ['met' => $met, 'missing' => $missing];
}

/**
 * Verificar saúde do banco de dados
 */
function checkDatabaseHealth(PDO $pdo): array 
{
    $health = [
        'overall_status' => 'healthy',
        'issues' => [],
        'warnings' => []
    ];
    
    try {
        // Verificar tabelas essenciais
        $essentialTables = [
            'declaracoes_importacao',
            'adicoes',
            'impostos_adicao',
            'moedas_referencia'
        ];
        
        foreach ($essentialTables as $table) {
            try {
                $stmt = $pdo->query("SELECT 1 FROM {$table} LIMIT 1");
            } catch (Exception $e) {
                $health['issues'][] = "Tabela {$table} não acessível: " . $e->getMessage();
                $health['overall_status'] = 'critical';
            }
        }
        
        // Verificar views do dashboard
        $views = ['v_dashboard_executivo', 'v_performance_fiscal'];
        
        foreach ($views as $view) {
            try {
                $stmt = $pdo->query("SELECT 1 FROM {$view} LIMIT 1");
            } catch (Exception $e) {
                $health['warnings'][] = "View {$view} não disponível - funcionalidade limitada";
                if ($health['overall_status'] === 'healthy') {
                    $health['overall_status'] = 'warning';
                }
            }
        }
        
        // Verificar integridade referencial básica
        $stmt = $pdo->query("
            SELECT COUNT(*) as orfas
            FROM adicoes a
            LEFT JOIN declaracoes_importacao di ON a.di_id = di.id
            WHERE di.id IS NULL
        ");
        $orfas = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($orfas['orfas'] > 0) {
            $health['warnings'][] = "Encontradas {$orfas['orfas']} adições órfãs (sem DI correspondente)";
            if ($health['overall_status'] === 'healthy') {
                $health['overall_status'] = 'warning';
            }
        }
        
    } catch (Exception $e) {
        $health['issues'][] = 'Erro na verificação de saúde: ' . $e->getMessage();
        $health['overall_status'] = 'critical';
    }
    
    return $health;
}

/**
 * Gerar recomendações baseadas no estado atual
 */
function generateRecommendations(array $dataCounts, array $requirements, array $health): array 
{
    $recommendations = [];
    
    // Recomendações baseadas em dados faltantes
    if ($dataCounts['total_dis'] === 0) {
        $recommendations[] = [
            'type' => 'critical',
            'title' => 'Nenhuma DI encontrada',
            'message' => 'Importe arquivos XML de DI para começar a usar o dashboard',
            'action' => 'upload_di'
        ];
    } elseif ($dataCounts['total_dis'] < 3) {
        $recommendations[] = [
            'type' => 'warning',
            'title' => 'Poucos dados para análise',
            'message' => 'Importe mais DIs para análises mais robustas',
            'action' => 'upload_more_dis'
        ];
    }
    
    if ($dataCounts['meses_dados'] < 2) {
        $recommendations[] = [
            'type' => 'info',
            'title' => 'Dados de um período único',
            'message' => 'Importe DIs de períodos diferentes para análise de tendências',
            'action' => 'import_historical_data'
        ];
    }
    
    if ($dataCounts['impostos_calculados'] === 0 && $dataCounts['total_impostos'] > 0) {
        $recommendations[] = [
            'type' => 'warning',
            'title' => 'Impostos não calculados',
            'message' => 'Execute o processamento fiscal para calcular impostos',
            'action' => 'process_taxes'
        ];
    }
    
    // Recomendações baseadas na saúde do banco
    if ($health['overall_status'] === 'critical') {
        $recommendations[] = [
            'type' => 'critical',
            'title' => 'Problemas críticos no banco',
            'message' => 'Corrija os problemas de banco antes de usar o dashboard',
            'action' => 'fix_database'
        ];
    }
    
    // Recomendações de otimização
    if ($dataCounts['total_dis'] > 0 && count($requirements['missing']) === 0) {
        $recommendations[] = [
            'type' => 'success',
            'title' => 'Sistema pronto',
            'message' => 'Todos os dados necessários estão disponíveis',
            'action' => 'load_dashboard'
        ];
    }
    
    return $recommendations;
}

/**
 * Retornar estrutura vazia para casos de erro
 */
function getEmptyPreCheck(string $reason): array 
{
    return [
        'can_load_charts' => false,
        'available_chart_types' => [],
        'data_requirements' => [],
        'missing_requirements' => [
            [
                'requirement' => 'database_access',
                'description' => 'Acesso ao banco de dados',
                'status' => 'missing',
                'reason' => $reason
            ]
        ],
        'recommendations' => [
            [
                'type' => 'critical',
                'title' => 'Banco não acessível',
                'message' => $reason,
                'action' => 'fix_database'
            ]
        ],
        'database_health' => [
            'overall_status' => 'critical',
            'issues' => [$reason],
            'warnings' => []
        ],
        'thresholds' => [],
        'current_data' => []
    ];
}