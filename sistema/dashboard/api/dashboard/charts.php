<?php
/**
 * ================================================================================
 * API DE DADOS PARA GRÁFICOS - OTIMIZADA COM CACHE
 * Sistema ETL DI's - Endpoint para alimentação dos gráficos Chart.js (< 1s)
 * Performance: Cache inteligente + Views MySQL + Queries pré-otimizadas
 * ================================================================================
 */

require_once '../common/response.php';
require_once '../common/cache.php';
require_once '../common/validator.php';
require_once '../../../config/database.php';

// Middleware de inicialização
apiMiddleware();

try {
    // Verificar método
    if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
        apiError('Método não permitido.', 405)->send();
    }

    // Obter e validar parâmetros
    $validator = new ApiValidator();
    $params = array_merge($_GET, $_POST);
    
    // Validação básica
    if (!$validator->allowedValues($params, [
        'type' => ['evolution', 'taxes', 'expenses', 'currencies', 'ncms', 'states', 'importers', 'correlation', 'all'],
        'period' => ['1month', '3months', '6months', '12months', 'all']
    ])) {
        apiError('Parâmetros inválidos: ' . implode(', ', $validator->getErrors()), 400)->send();
    }

    $chartType = $params['type'] ?? 'evolution';
    $period = $params['period'] ?? '6months';
    $filters = $params['filters'] ?? [];
    
    // Handle special 'all' type for bulk chart data
    if ($chartType === 'all') {
        return handleAllChartsRequest($period, $filters);
    }

    // Inicializar cache do dashboard
    $cache = getDashboardCache();
    $response = apiSuccess();
    
    // Obter dados do gráfico com cache
    $chartData = $cache->getChart($chartType, compact('period', 'filters'), function() use ($chartType, $period, $filters) {
        return generateChartData($chartType, $period, $filters);
    });
    
    // Adicionar metadata
    $response->setCacheStats($chartData !== null, 'L1+L2');
    $response->addMeta('chart_type', $chartType);
    $response->addMeta('period', $period);
    $response->addMeta('data_points', count($chartData['datasets'][0]['data'] ?? []));
    
    // Enviar resposta
    $response->setData($chartData)->send();
    
} catch (Exception $e) {
    error_log("API Charts Error: " . $e->getMessage());
    apiError('Erro interno do servidor', 500)->send();
}

/**
 * Handle request for all charts data
 */
function handleAllChartsRequest(string $period, array $filters): void
{
    try {
        $cache = getDashboardCache();
        $response = apiSuccess();
        
        // Generate all chart types
        $allCharts = [
            'temporal' => $cache->getChart('evolution', compact('period', 'filters'), function() use ($period, $filters) {
                return generateChartData('evolution', $period, $filters);
            }),
            'taxes' => $cache->getChart('taxes', compact('period', 'filters'), function() use ($period, $filters) {
                return generateChartData('taxes', $period, $filters);
            }),
            'expenses' => $cache->getChart('expenses', compact('period', 'filters'), function() use ($period, $filters) {
                return generateChartData('expenses', $period, $filters);
            }),
            'currencies' => $cache->getChart('currencies', compact('period', 'filters'), function() use ($period, $filters) {
                return generateChartData('currencies', $period, $filters);
            }),
            'states' => $cache->getChart('states', compact('period', 'filters'), function() use ($period, $filters) {
                return generateChartData('states', $period, $filters);
            }),
            'correlation' => $cache->getChart('correlation', compact('period', 'filters'), function() use ($period, $filters) {
                return generateChartData('correlation', $period, $filters);
            })
        ];
        
        // Add metadata
        $response->setCacheStats(true, 'L1+L2');
        $response->addMeta('chart_types', array_keys($allCharts));
        $response->addMeta('period', $period);
        $response->addMeta('total_charts', count($allCharts));
        
        // Send response with all charts
        $response->setData([
            'charts' => $allCharts,
            'summary' => [
                'period' => $period,
                'generated_at' => date('Y-m-d H:i:s'),
                'cache_enabled' => true
            ]
        ])->send();
        
    } catch (Exception $e) {
        error_log("Error generating all charts: " . $e->getMessage());
        apiError('Erro ao gerar todos os gráficos', 500)->send();
    }
}

/**
 * Gerar dados otimizados para gráficos Chart.js
 * Performance target: < 800ms
 */
function generateChartData(string $chartType, string $period, array $filters): array 
{
    try {
        $db = getDatabase();
        $pdo = $db->getConnection();
        
        switch ($chartType) {
            case 'evolution':
                return generateEvolutionChart($pdo, $period);
            case 'taxes':
                return generateTaxesChart($pdo, $period);
            case 'expenses':
                return generateExpensesChart($pdo, $period);
            case 'currencies':
                return generateCurrenciesChart($pdo, $period);
            case 'states':
                return generateStatesChart($pdo, $period);
            case 'correlation':
                return generateCorrelationChart($pdo, $period);
            case 'ncms':
                return generateNCMsChart($pdo, $period);
            case 'importers':
                return generateImportersChart($pdo, $period);
            default:
                throw new Exception("Tipo de gráfico não suportado: {$chartType}");
        }
        
    } catch (Exception $e) {
        error_log("Error generating chart {$chartType}: " . $e->getMessage());
        return generateFallbackData($chartType);
    }
}

/**
 * Gráfico de evolução temporal (Line Chart)
 */
function generateEvolutionChart(PDO $pdo, string $period): array 
{
    $monthsBack = getMonthsFromPeriod($period);
    
    $stmt = $pdo->query("
        SELECT 
            ano_mes,
            total_dis,
            cif_total_milhoes,
            ii_total_milhoes,
            ipi_total_milhoes,
            usd_taxa_media
        FROM v_performance_fiscal 
        WHERE ano_mes >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL {$monthsBack} MONTH), '%Y-%m')
        ORDER BY ano_mes ASC
        LIMIT {$monthsBack}
    ");
    
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($data)) {
        return generateFallbackEvolution($monthsBack);
    }
    
    $labels = [];
    $dis_values = [];
    $cif_values = [];
    $tax_values = [];
    $rate_values = [];
    
    foreach ($data as $row) {
        $labels[] = formatMonthLabel($row['ano_mes']);
        $dis_values[] = (int)$row['total_dis'];
        $cif_values[] = (float)$row['cif_total_milhoes'];
        $tax_values[] = (float)($row['ii_total_milhoes'] + $row['ipi_total_milhoes']);
        $rate_values[] = (float)$row['usd_taxa_media'];
    }
    
    return [
        'type' => 'line',
        'data' => [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'DIs Processadas',
                    'data' => $dis_values,
                    'borderColor' => 'rgb(75, 192, 192)',
                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                    'yAxisID' => 'y'
                ],
                [
                    'label' => 'Valor CIF (Mi)',
                    'data' => $cif_values,
                    'borderColor' => 'rgb(54, 162, 235)',
                    'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                    'yAxisID' => 'y1'
                ],
                [
                    'label' => 'Taxa USD/BRL',
                    'data' => $rate_values,
                    'borderColor' => 'rgb(255, 99, 132)',
                    'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                    'yAxisID' => 'y2'
                ]
            ]
        ],
        'options' => [
            'responsive' => true,
            'scales' => [
                'y' => ['type' => 'linear', 'display' => true, 'position' => 'left'],
                'y1' => ['type' => 'linear', 'display' => true, 'position' => 'right', 'grid' => ['drawOnChartArea' => false]],
                'y2' => ['type' => 'linear', 'display' => false]
            ]
        ]
    ];
}

/**
 * Gráfico de impostos (Pie/Doughnut Chart)
 */
function generateTaxesChart(PDO $pdo, string $period): array 
{
    $monthsBack = getMonthsFromPeriod($period);
    
    $stmt = $pdo->query("
        SELECT 
            SUM(CASE WHEN imp.tipo_imposto = 'II' THEN imp.valor_devido_reais ELSE 0 END) as ii_total,
            SUM(CASE WHEN imp.tipo_imposto = 'IPI' THEN imp.valor_devido_reais ELSE 0 END) as ipi_total,
            SUM(CASE WHEN imp.tipo_imposto = 'PIS' THEN imp.valor_devido_reais ELSE 0 END) as pis_total,
            SUM(CASE WHEN imp.tipo_imposto = 'COFINS' THEN imp.valor_devido_reais ELSE 0 END) as cofins_total,
            SUM(COALESCE(icms.valor_total_icms, 0)) as icms_total
        FROM impostos_adicao imp
        LEFT JOIN icms_detalhado icms ON imp.adicao_id = icms.adicao_id
        JOIN adicoes a ON imp.adicao_id = a.id
        JOIN declaracoes_importacao di ON a.numero_di = di.numero_di
        WHERE di.data_registro >= DATE_SUB(CURDATE(), INTERVAL {$monthsBack} MONTH)
    ");
    
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$data || array_sum($data) == 0) {
        return generateFallbackTaxes();
    }
    
    $values = [
        $data['ii_total'] / 1000000,
        $data['ipi_total'] / 1000000,
        $data['pis_total'] / 1000000,
        $data['cofins_total'] / 1000000,
        $data['icms_total'] / 1000000
    ];
    
    return [
        'type' => 'doughnut',
        'data' => [
            'labels' => ['II', 'IPI', 'PIS', 'COFINS', 'ICMS'],
            'datasets' => [
                [
                    'data' => $values,
                    'backgroundColor' => [
                        'rgba(255, 99, 132, 0.8)',
                        'rgba(54, 162, 235, 0.8)', 
                        'rgba(255, 205, 86, 0.8)',
                        'rgba(75, 192, 192, 0.8)',
                        'rgba(153, 102, 255, 0.8)'
                    ],
                    'borderWidth' => 2
                ]
            ]
        ],
        'options' => [
            'responsive' => true,
            'plugins' => [
                'legend' => ['position' => 'top'],
                'tooltip' => [
                    'callbacks' => [
                        'label' => 'function(context) { return context.label + ": R$ " + context.parsed.toFixed(2) + "Mi (" + Math.round(context.parsed / context.dataset.data.reduce((a,b) => a + b, 0) * 100) + "%)" }'
                    ]
                ]
            ]
        ]
    ];
}

/**
 * Gráfico de despesas (Bar Chart)
 */
function generateExpensesChart(PDO $pdo, string $period): array 
{
    $monthsBack = getMonthsFromPeriod($period);
    
    $stmt = $pdo->query("
        SELECT 
            categoria,
            SUM(valor_final) as valor_total,
            COUNT(*) as quantidade
        FROM despesas_extras de
        JOIN declaracoes_importacao di ON de.numero_di = di.numero_di
        WHERE di.data_registro >= DATE_SUB(CURDATE(), INTERVAL {$monthsBack} MONTH)
        GROUP BY categoria
        ORDER BY valor_total DESC
        LIMIT 16
    ");
    
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($data)) {
        return generateFallbackExpenses();
    }
    
    $labels = [];
    $values = [];
    
    foreach ($data as $row) {
        $labels[] = translateExpenseCategory($row['categoria']);
        $values[] = round($row['valor_total'] / 1000, 2); // Em milhares
    }
    
    return [
        'type' => 'bar',
        'data' => [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Valor (R$ Mil)',
                    'data' => $values,
                    'backgroundColor' => 'rgba(54, 162, 235, 0.6)',
                    'borderColor' => 'rgba(54, 162, 235, 1)',
                    'borderWidth' => 1
                ]
            ]
        ],
        'options' => [
            'responsive' => true,
            'scales' => [
                'y' => ['beginAtZero' => true],
                'x' => ['ticks' => ['maxRotation' => 45]]
            ]
        ]
    ];
}

/**
 * Gráfico de moedas (Donut Chart)
 */
function generateCurrenciesChart(PDO $pdo, string $period): array 
{
    $monthsBack = getMonthsFromPeriod($period);
    
    $stmt = $pdo->query("
        SELECT 
            m.codigo_iso,
            m.simbolo,
            COUNT(DISTINCT di.numero_di) as dis_count,
            SUM(di.valor_total_cif_brl) / 1000000 as valor_milhoes
        FROM declaracoes_importacao di
        JOIN adicoes a ON di.numero_di = a.numero_di
        JOIN moedas_referencia m ON a.moeda_codigo = m.codigo_siscomex
        WHERE di.data_registro >= DATE_SUB(CURDATE(), INTERVAL {$monthsBack} MONTH)
        GROUP BY m.codigo_iso, m.simbolo
        ORDER BY dis_count DESC
        LIMIT 8
    ");
    
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($data)) {
        return generateFallbackCurrencies();
    }
    
    $labels = [];
    $values = [];
    $colors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#FF6384', '#C9CBCF'];
    
    foreach ($data as $index => $row) {
        $labels[] = $row['codigo_iso'];
        $values[] = (int)$row['dis_count'];
    }
    
    return [
        'type' => 'doughnut',
        'data' => [
            'labels' => $labels,
            'datasets' => [
                [
                    'data' => $values,
                    'backgroundColor' => array_slice($colors, 0, count($labels)),
                    'borderWidth' => 2
                ]
            ]
        ],
        'options' => [
            'responsive' => true,
            'plugins' => [
                'legend' => ['position' => 'right']
            ]
        ]
    ];
}

/**
 * Mapa de calor dos estados (Heatmap)
 */
function generateStatesChart(PDO $pdo, string $period): array 
{
    // Para heatmap, retornamos dados estruturados para o frontend processar
    $monthsBack = getMonthsFromPeriod($period);
    
    $stmt = $pdo->query("
        SELECT 
            di.importador_uf as uf,
            COUNT(DISTINCT di.numero_di) as dis_count,
            SUM(di.valor_total_cif_brl) / 1000000 as valor_milhoes,
            AVG(CASE WHEN at.percentual_reducao > 0 THEN at.percentual_reducao ELSE 0 END) as beneficio_medio
        FROM declaracoes_importacao di
        LEFT JOIN adicoes a ON di.numero_di = a.numero_di
        LEFT JOIN acordos_tarifarios at ON a.id = at.adicao_id
        WHERE di.data_registro >= DATE_SUB(CURDATE(), INTERVAL {$monthsBack} MONTH)
          AND di.importador_uf IS NOT NULL
        GROUP BY di.importador_uf
        HAVING dis_count >= 1
        ORDER BY dis_count DESC
    ");
    
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $statesData = [];
    foreach ($data as $row) {
        $statesData[] = [
            'uf' => $row['uf'],
            'dis_count' => (int)$row['dis_count'],
            'valor_milhoes' => round($row['valor_milhoes'], 2),
            'beneficio_medio' => round($row['beneficio_medio'], 2),
            'intensity' => min(100, ($row['dis_count'] / max(1, max(array_column($data, 'dis_count')))) * 100)
        ];
    }
    
    return [
        'type' => 'heatmap',
        'data' => $statesData,
        'metadata' => [
            'max_dis' => max(array_column($statesData, 'dis_count')),
            'total_states' => count($statesData)
        ]
    ];
}

/**
 * Scatter plot correlação câmbio vs custo (Scatter Chart)
 */
function generateCorrelationChart(PDO $pdo, string $period): array 
{
    $monthsBack = getMonthsFromPeriod($period);
    
    $stmt = $pdo->query("
        SELECT 
            di.numero_di,
            a.taxa_cambio_calculada,
            di.valor_total_cif_brl / 1000000 as custo_milhoes,
            di.data_registro
        FROM declaracoes_importacao di
        JOIN adicoes a ON di.numero_di = a.numero_di
        WHERE di.data_registro >= DATE_SUB(CURDATE(), INTERVAL {$monthsBack} MONTH)
          AND a.taxa_cambio_calculada > 0
          AND di.valor_total_cif_brl > 0
        ORDER BY di.data_registro DESC
        LIMIT 200
    ");
    
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $scatterData = [];
    foreach ($data as $row) {
        $scatterData[] = [
            'x' => (float)$row['taxa_cambio_calculada'],
            'y' => (float)$row['custo_milhoes'],
            'di' => $row['numero_di']
        ];
    }
    
    return [
        'type' => 'scatter',
        'data' => [
            'datasets' => [
                [
                    'label' => 'Câmbio vs Custo Landed',
                    'data' => $scatterData,
                    'backgroundColor' => 'rgba(75, 192, 192, 0.6)',
                    'borderColor' => 'rgba(75, 192, 192, 1)'
                ]
            ]
        ],
        'options' => [
            'responsive' => true,
            'scales' => [
                'x' => ['title' => ['display' => true, 'text' => 'Taxa de Câmbio']],
                'y' => ['title' => ['display' => true, 'text' => 'Custo Landed (Milhões)']]
            ]
        ]
    ];
}

/**
 * Helper functions
 */
function getMonthsFromPeriod(string $period): int 
{
    $periods = [
        '1month' => 1,
        '3months' => 3,
        '6months' => 6,
        '12months' => 12,
        'all' => 24
    ];
    
    return $periods[$period] ?? 6;
}

function formatMonthLabel(string $yearMonth): string 
{
    $months = [
        '01' => 'Jan', '02' => 'Fev', '03' => 'Mar', '04' => 'Abr',
        '05' => 'Mai', '06' => 'Jun', '07' => 'Jul', '08' => 'Ago',
        '09' => 'Set', '10' => 'Out', '11' => 'Nov', '12' => 'Dez'
    ];
    
    [$year, $month] = explode('-', $yearMonth);
    return $months[$month] . '/' . substr($year, 2);
}

function translateExpenseCategory(string $category): string 
{
    $translations = [
        'AFRMM' => 'AFRMM',
        'SISCOMEX' => 'Siscomex',
        'CAPATAZIA' => 'Capatazia',
        'ARMAZENAGEM' => 'Armazenagem',
        'THC' => 'THC',
        'ISPS' => 'ISPS',
        'SCANNER' => 'Scanner',
        'DESPACHANTE' => 'Despachante',
        'LIBERACAO_BL' => 'Liberação BL',
        'FRETE_INTERNO' => 'Frete Interno',
        'BANCARIO' => 'Taxas Bancárias'
    ];
    
    return $translations[$category] ?? ucfirst(strtolower($category));
}

/**
 * Fallback data generators
 */
function generateFallbackData(string $chartType): array 
{
    switch ($chartType) {
        case 'evolution':
            return generateFallbackEvolution(6);
        case 'taxes':
            return generateFallbackTaxes();
        case 'expenses':
            return generateFallbackExpenses();
        case 'currencies':
            return generateFallbackCurrencies();
        default:
            return ['type' => $chartType, 'data' => [], 'message' => 'Dados não disponíveis'];
    }
}

function generateFallbackEvolution(int $months): array 
{
    $labels = [];
    $values = [];
    
    for ($i = $months - 1; $i >= 0; $i--) {
        $labels[] = formatMonthLabel(date('Y-m', strtotime("-{$i} months")));
        $values[] = rand(50, 200);
    }
    
    return [
        'type' => 'line',
        'data' => [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'DIs (simulado)',
                    'data' => $values,
                    'borderColor' => 'rgba(75, 192, 192, 0.5)',
                    'backgroundColor' => 'rgba(75, 192, 192, 0.1)'
                ]
            ]
        ]
    ];
}

function generateFallbackTaxes(): array 
{
    return [
        'type' => 'doughnut',
        'data' => [
            'labels' => ['II', 'IPI', 'PIS', 'COFINS', 'ICMS'],
            'datasets' => [
                [
                    'data' => [42.5, 23.8, 12.4, 18.9, 67.2],
                    'backgroundColor' => [
                        'rgba(255, 99, 132, 0.8)',
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(255, 205, 86, 0.8)',
                        'rgba(75, 192, 192, 0.8)',
                        'rgba(153, 102, 255, 0.8)'
                    ]
                ]
            ]
        ]
    ];
}

function generateFallbackExpenses(): array 
{
    return [
        'type' => 'bar',
        'data' => [
            'labels' => ['AFRMM', 'THC', 'Armazenagem', 'Despachante', 'Siscomex', 'ISPS'],
            'datasets' => [
                [
                    'label' => 'Valor (R$ Mil)',
                    'data' => [125.8, 89.3, 67.2, 45.6, 38.9, 29.8],
                    'backgroundColor' => 'rgba(54, 162, 235, 0.6)'
                ]
            ]
        ]
    ];
}

function generateFallbackCurrencies(): array 
{
    return [
        'type' => 'doughnut',
        'data' => [
            'labels' => ['USD', 'EUR', 'GBP', 'CNY', 'JPY'],
            'datasets' => [
                [
                    'data' => [1245, 456, 234, 178, 89],
                    'backgroundColor' => ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF']
                ]
            ]
        ]
    ];
}

// Implementar outros geradores de NCMs e Importadores se necessário
function generateNCMsChart(PDO $pdo, string $period): array 
{
    // Implementação similar aos outros gráficos
    return generateFallbackData('ncms');
}

function generateImportersChart(PDO $pdo, string $period): array 
{
    // Implementação similar aos outros gráficos
    return generateFallbackData('importers');
}