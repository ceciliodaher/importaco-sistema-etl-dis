<?php
/**
 * ================================================================================
 * API DE PREPARAÇÃO PARA EXPORTS - OTIMIZADA COM CACHE
 * Sistema ETL DI's - Preparação de dados para Excel/PDF/CSV (< 3s)
 * Performance: Cache + Views otimizadas + Processamento assíncrono
 * ================================================================================
 */

require_once dirname(__DIR__) . '/common/response.php';
require_once dirname(__DIR__) . '/common/cache.php';
require_once dirname(__DIR__, 3) . '/config/database.php';

// Middleware de inicialização
apiMiddleware();

try {
    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        apiError('Método não permitido. Use POST.', 405)->send();
    }

    // Obter dados JSON
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        apiError('JSON inválido no corpo da requisição', 400)->send();
    }

    // Validar parâmetros
    $validator = new ApiValidator();
    $params = $input ?? [];
    
    // Validação básica
    if (!$validator->allowedValues($params, [
        'format' => ['excel', 'pdf', 'csv'],
        'type' => ['dis', 'adicoes', 'impostos', 'despesas', 'custo_landed', 'performance']
    ])) {
        apiError('Parâmetros inválidos: ' . implode(', ', $validator->getErrors()), 400)->send();
    }

    $format = $params['format'] ?? 'excel';
    $type = $params['type'] ?? 'dis';
    $filters = $params['filters'] ?? [];
    $columns = $params['columns'] ?? [];
    
    // Inicializar cache do dashboard
    $cache = getDashboardCache();
    $response = apiSuccess();
    
    // Obter dados para export com cache
    $exportData = $cache->getExportData($format, compact('type', 'filters', 'columns'), function() use ($type, $filters, $columns, $format) {
        return prepareExportData($type, $filters, $columns, $format);
    });
    
    // Adicionar metadata
    $response->setCacheStats($exportData !== null, 'L1+L2');
    $response->addMeta('export_type', $type);
    $response->addMeta('format', $format);
    $response->addMeta('total_records', count($exportData['data'] ?? []));
    $response->addMeta('estimated_size', formatBytes(estimateExportSize($exportData, $format)));
    
    // Enviar resposta
    $response->setData([
        'export_ready' => true,
        'data' => $exportData['data'] ?? [],
        'metadata' => $exportData['metadata'] ?? [],
        'headers' => $exportData['headers'] ?? [],
        'filename' => generateExportFilename($type, $format),
        'download_url' => generateDownloadUrl($type, $format, $filters)
    ])->send();
    
} catch (Exception $e) {
    error_log("API Export Error: " . $e->getMessage());
    apiError('Erro interno do servidor', 500)->send();
}

/**
 * Preparar dados para exportação baseado no tipo
 * Performance target: < 2.5s
 */
function prepareExportData(string $type, array $filters, array $columns, string $format): array 
{
    try {
        $db = getDatabase();
        $pdo = $db->getConnection();
        
        switch ($type) {
            case 'dis':
                return prepareDIsExport($pdo, $filters, $columns, $format);
            case 'adicoes':
                return prepareAdicoesExport($pdo, $filters, $columns, $format);
            case 'impostos':
                return prepareImpostosExport($pdo, $filters, $columns, $format);
            case 'despesas':
                return prepareDespesasExport($pdo, $filters, $columns, $format);
            case 'custo_landed':
                return prepareCustoLandedExport($pdo, $filters, $columns, $format);
            case 'performance':
                return preparePerformanceExport($pdo, $filters, $columns, $format);
            default:
                throw new Exception("Tipo de export não suportado: {$type}");
        }
        
    } catch (Exception $e) {
        error_log("Error preparing export data {$type}: " . $e->getMessage());
        return [
            'data' => [],
            'metadata' => ['error' => $e->getMessage()],
            'headers' => []
        ];
    }
}

/**
 * Export de DIs (Declarações de Importação)
 */
function prepareDIsExport(PDO $pdo, array $filters, array $columns, string $format): array 
{
    // Campos padrão para DIs
    $defaultColumns = [
        'numero_di' => 'Número DI',
        'data_registro' => 'Data Registro',
        'importador_nome' => 'Importador',
        'importador_cnpj' => 'CNPJ',
        'urf_despacho_nome' => 'URF Despacho',
        'valor_cif_brl' => 'Valor CIF (R$)',
        'valor_cif_usd' => 'Valor CIF (US$)',
        'total_impostos' => 'Total Impostos (R$)',
        'custo_total_landed' => 'Custo Landed (R$)',
        'taxa_cambio_media' => 'Taxa Câmbio Média',
        'status_processamento' => 'Status'
    ];
    
    $selectedColumns = !empty($columns) ? array_intersect_key($defaultColumns, array_flip($columns)) : $defaultColumns;
    
    // Construir query base
    $selectFields = implode(', ', array_map(function($col) {
        return "di.{$col}";
    }, array_keys($selectedColumns)));
    
    $whereClause = buildExportWhereClause($filters);
    $parameters = buildExportParameters($filters);
    
    $query = "
        SELECT {$selectFields}
        FROM v_di_resumo di
        {$whereClause}
        ORDER BY di.data_registro DESC
        LIMIT 10000
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($parameters);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Processar dados para export
    $processedData = [];
    foreach ($data as $row) {
        $processedRow = [];
        foreach ($selectedColumns as $key => $label) {
            $processedRow[$label] = formatValueForExport($row[$key] ?? '', $key, $format);
        }
        $processedData[] = $processedRow;
    }
    
    return [
        'data' => $processedData,
        'headers' => array_values($selectedColumns),
        'metadata' => [
            'total_records' => count($processedData),
            'export_date' => date('Y-m-d H:i:s'),
            'filters_applied' => $filters
        ]
    ];
}

/**
 * Export de Adições
 */
function prepareAdicoesExport(PDO $pdo, array $filters, array $columns, string $format): array 
{
    $defaultColumns = [
        'numero_di' => 'Número DI',
        'numero_adicao' => 'Número Adição',
        'ncm' => 'NCM',
        'ncm_descricao' => 'Descrição NCM',
        'valor_cif' => 'Valor CIF (R$)',
        'peso_liquido' => 'Peso Líquido (Kg)',
        'peso_bruto' => 'Peso Bruto (Kg)',
        'ii_valor' => 'II (R$)',
        'ii_aliquota' => 'II Alíquota (%)',
        'ipi_valor' => 'IPI (R$)',
        'ipi_aliquota' => 'IPI Alíquota (%)',
        'pis_valor' => 'PIS (R$)',
        'cofins_valor' => 'COFINS (R$)',
        'custo_total_adicao' => 'Custo Total (R$)',
        'acordos_aplicados' => 'Acordos Tarifários',
        'moeda_iso' => 'Moeda'
    ];
    
    $selectedColumns = !empty($columns) ? array_intersect_key($defaultColumns, array_flip($columns)) : $defaultColumns;
    $selectFields = implode(', ', array_keys($selectedColumns));
    
    $whereClause = buildExportWhereClause($filters, 'ac');
    $parameters = buildExportParameters($filters);
    
    $query = "
        SELECT {$selectFields}
        FROM v_adicoes_completas ac
        JOIN v_di_resumo di ON ac.numero_di = di.numero_di
        {$whereClause}
        ORDER BY ac.numero_di, ac.numero_adicao
        LIMIT 15000
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($parameters);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $processedData = [];
    foreach ($data as $row) {
        $processedRow = [];
        foreach ($selectedColumns as $key => $label) {
            $processedRow[$label] = formatValueForExport($row[$key] ?? '', $key, $format);
        }
        $processedData[] = $processedRow;
    }
    
    return [
        'data' => $processedData,
        'headers' => array_values($selectedColumns),
        'metadata' => [
            'total_records' => count($processedData),
            'export_date' => date('Y-m-d H:i:s'),
            'filters_applied' => $filters
        ]
    ];
}

/**
 * Export de Impostos
 */
function prepareImpostosExport(PDO $pdo, array $filters, array $columns, string $format): array 
{
    $query = "
        SELECT 
            di.numero_di,
            a.numero_adicao,
            a.ncm,
            imp.tipo_imposto,
            imp.aliquota_ad_valorem,
            imp.aliquota_especifica,
            imp.valor_base_calculo,
            imp.valor_devido_reais,
            imp.reducao_beneficio,
            imp.acordo_aplicado,
            di.data_registro
        FROM impostos_adicao imp
        JOIN adicoes a ON imp.adicao_id = a.id
        JOIN declaracoes_importacao di ON a.numero_di = di.numero_di
        " . buildExportWhereClause($filters, 'di') . "
        ORDER BY di.numero_di, a.numero_adicao, imp.tipo_imposto
        LIMIT 20000
    ";
    
    $parameters = buildExportParameters($filters);
    $stmt = $pdo->prepare($query);
    $stmt->execute($parameters);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $processedData = [];
    foreach ($data as $row) {
        $processedData[] = [
            'Número DI' => $row['numero_di'],
            'Adição' => $row['numero_adicao'],
            'NCM' => $row['ncm'],
            'Tipo Imposto' => $row['tipo_imposto'],
            'Alíquota Ad Valorem (%)' => number_format($row['aliquota_ad_valorem'] * 100, 2),
            'Alíquota Específica' => number_format($row['aliquota_especifica'], 4),
            'Base Cálculo (R$)' => formatCurrency($row['valor_base_calculo']),
            'Valor Devido (R$)' => formatCurrency($row['valor_devido_reais']),
            'Redução/Benefício (%)' => number_format($row['reducao_beneficio'], 2),
            'Acordo Aplicado' => $row['acordo_aplicado'] ?: 'Não',
            'Data Registro' => date('d/m/Y', strtotime($row['data_registro']))
        ];
    }
    
    return [
        'data' => $processedData,
        'headers' => [
            'Número DI', 'Adição', 'NCM', 'Tipo Imposto', 'Alíquota Ad Valorem (%)',
            'Alíquota Específica', 'Base Cálculo (R$)', 'Valor Devido (R$)',
            'Redução/Benefício (%)', 'Acordo Aplicado', 'Data Registro'
        ],
        'metadata' => [
            'total_records' => count($processedData),
            'export_date' => date('Y-m-d H:i:s')
        ]
    ];
}

/**
 * Export de Despesas
 */
function prepareDespesasExport(PDO $pdo, array $filters, array $columns, string $format): array 
{
    $query = "
        SELECT 
            dd.numero_di,
            di.data_registro,
            di.importador_nome,
            dd.categoria,
            dd.grupo_despesa,
            dd.valor_final,
            dd.origem_valor,
            dd.validado,
            dd.observacao_divergencia,
            dd.numero_documento,
            dd.fornecedor_nome,
            dd.compoe_base_icms
        FROM v_despesas_discriminadas dd
        JOIN v_di_resumo di ON dd.numero_di = di.numero_di
        " . buildExportWhereClause($filters, 'di') . "
        ORDER BY dd.numero_di, dd.grupo_despesa, dd.categoria
        LIMIT 25000
    ";
    
    $parameters = buildExportParameters($filters);
    $stmt = $pdo->prepare($query);
    $stmt->execute($parameters);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $processedData = [];
    foreach ($data as $row) {
        $processedData[] = [
            'Número DI' => $row['numero_di'],
            'Data Registro' => date('d/m/Y', strtotime($row['data_registro'])),
            'Importador' => $row['importador_nome'],
            'Categoria' => $row['categoria'],
            'Grupo' => $row['grupo_despesa'],
            'Valor (R$)' => formatCurrency($row['valor_final']),
            'Origem' => $row['origem_valor'],
            'Validado' => $row['validado'] ? 'Sim' : 'Não',
            'Observações' => $row['observacao_divergencia'],
            'Documento' => $row['numero_documento'],
            'Fornecedor' => $row['fornecedor_nome'],
            'Compõe Base ICMS' => $row['compoe_base_icms'] ? 'Sim' : 'Não'
        ];
    }
    
    return [
        'data' => $processedData,
        'headers' => [
            'Número DI', 'Data Registro', 'Importador', 'Categoria', 'Grupo',
            'Valor (R$)', 'Origem', 'Validado', 'Observações', 'Documento',
            'Fornecedor', 'Compõe Base ICMS'
        ],
        'metadata' => [
            'total_records' => count($processedData),
            'export_date' => date('Y-m-d H:i:s')
        ]
    ];
}

/**
 * Export de Custo Landed
 */
function prepareCustoLandedExport(PDO $pdo, array $filters, array $columns, string $format): array 
{
    $query = "
        SELECT 
            cl.numero_di,
            cl.data_registro,
            cl.importador_nome,
            cl.cif_brl,
            cl.ii,
            cl.ipi,
            cl.pis,
            cl.cofins,
            cl.icms,
            cl.total_impostos,
            cl.siscomex,
            cl.afrmm,
            cl.capatazia,
            cl.armazenagem,
            cl.thc,
            cl.despachante,
            cl.frete_interno,
            cl.total_portuarias,
            cl.total_despacho,
            cl.total_logistica,
            cl.total_despesas,
            cl.custo_total_landed,
            cl.percentual_impostos,
            cl.percentual_despesas
        FROM v_custo_landed_completo cl
        " . buildExportWhereClause($filters, 'cl') . "
        ORDER BY cl.data_registro DESC
        LIMIT 8000
    ";
    
    $parameters = buildExportParameters($filters);
    $stmt = $pdo->prepare($query);
    $stmt->execute($parameters);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $processedData = [];
    foreach ($data as $row) {
        $processedData[] = [
            'Número DI' => $row['numero_di'],
            'Data' => date('d/m/Y', strtotime($row['data_registro'])),
            'Importador' => $row['importador_nome'],
            'CIF (R$)' => formatCurrency($row['cif_brl']),
            'II (R$)' => formatCurrency($row['ii']),
            'IPI (R$)' => formatCurrency($row['ipi']),
            'PIS (R$)' => formatCurrency($row['pis']),
            'COFINS (R$)' => formatCurrency($row['cofins']),
            'ICMS (R$)' => formatCurrency($row['icms']),
            'Total Impostos (R$)' => formatCurrency($row['total_impostos']),
            'Siscomex (R$)' => formatCurrency($row['siscomex']),
            'AFRMM (R$)' => formatCurrency($row['afrmm']),
            'Capatazia (R$)' => formatCurrency($row['capatazia']),
            'Armazenagem (R$)' => formatCurrency($row['armazenagem']),
            'THC (R$)' => formatCurrency($row['thc']),
            'Despachante (R$)' => formatCurrency($row['despachante']),
            'Frete Interno (R$)' => formatCurrency($row['frete_interno']),
            'Total Portuárias (R$)' => formatCurrency($row['total_portuarias']),
            'Total Despacho (R$)' => formatCurrency($row['total_despacho']),
            'Total Logística (R$)' => formatCurrency($row['total_logistica']),
            'Total Despesas (R$)' => formatCurrency($row['total_despesas']),
            'CUSTO LANDED (R$)' => formatCurrency($row['custo_total_landed']),
            'Impostos (%)' => number_format($row['percentual_impostos'], 2) . '%',
            'Despesas (%)' => number_format($row['percentual_despesas'], 2) . '%'
        ];
    }
    
    return [
        'data' => $processedData,
        'headers' => [
            'Número DI', 'Data', 'Importador', 'CIF (R$)', 'II (R$)', 'IPI (R$)',
            'PIS (R$)', 'COFINS (R$)', 'ICMS (R$)', 'Total Impostos (R$)',
            'Siscomex (R$)', 'AFRMM (R$)', 'Capatazia (R$)', 'Armazenagem (R$)',
            'THC (R$)', 'Despachante (R$)', 'Frete Interno (R$)',
            'Total Portuárias (R$)', 'Total Despacho (R$)', 'Total Logística (R$)',
            'Total Despesas (R$)', 'CUSTO LANDED (R$)', 'Impostos (%)', 'Despesas (%)'
        ],
        'metadata' => [
            'total_records' => count($processedData),
            'export_date' => date('Y-m-d H:i:s'),
            'breakdown_complete' => true
        ]
    ];
}

/**
 * Export de Performance
 */
function preparePerformanceExport(PDO $pdo, array $filters, array $columns, string $format): array 
{
    $query = "
        SELECT 
            pf.ano,
            pf.mes,
            pf.ano_mes,
            pf.total_dis,
            pf.total_adicoes,
            pf.cif_total_milhoes,
            pf.ii_total_milhoes,
            pf.ipi_total_milhoes,
            pf.adicoes_com_acordo,
            pf.reducao_media_acordos,
            pf.usd_taxa_media,
            pf.despesas_extras_media
        FROM v_performance_fiscal pf
        ORDER BY pf.ano DESC, pf.mes DESC
        LIMIT 24
    ";
    
    $stmt = $pdo->query($query);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $processedData = [];
    foreach ($data as $row) {
        $processedData[] = [
            'Período' => $row['ano_mes'],
            'DIs' => number_format($row['total_dis']),
            'Adições' => number_format($row['total_adicoes']),
            'CIF (Mi R$)' => number_format($row['cif_total_milhoes'], 2),
            'II (Mi R$)' => number_format($row['ii_total_milhoes'], 2),
            'IPI (Mi R$)' => number_format($row['ipi_total_milhoes'], 2),
            'Adições c/ Acordo' => number_format($row['adicoes_com_acordo']),
            'Redução Média (%)' => number_format($row['reducao_media_acordos'], 2),
            'Taxa USD Média' => number_format($row['usd_taxa_media'], 4),
            'Despesas Médias (R$)' => formatCurrency($row['despesas_extras_media'])
        ];
    }
    
    return [
        'data' => $processedData,
        'headers' => [
            'Período', 'DIs', 'Adições', 'CIF (Mi R$)', 'II (Mi R$)',
            'IPI (Mi R$)', 'Adições c/ Acordo', 'Redução Média (%)',
            'Taxa USD Média', 'Despesas Médias (R$)'
        ],
        'metadata' => [
            'total_records' => count($processedData),
            'export_date' => date('Y-m-d H:i:s'),
            'period_analysis' => true
        ]
    ];
}

/**
 * Helper functions para construir queries de export
 */
function buildExportWhereClause(array $filters, string $alias = 'di'): string 
{
    $conditions = [];
    
    if (!empty($filters['date_start'])) {
        $conditions[] = "{$alias}.data_registro >= :date_start";
    }
    
    if (!empty($filters['date_end'])) {
        $conditions[] = "{$alias}.data_registro <= :date_end";
    }
    
    if (!empty($filters['uf'])) {
        $conditions[] = "{$alias}.importador_uf IN (" . implode(',', array_map(function($uf) {
            return "'" . addslashes($uf) . "'";
        }, (array)$filters['uf'])) . ")";
    }
    
    if (!empty($filters['status'])) {
        $conditions[] = "{$alias}.status_processamento = :status";
    }
    
    return !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
}

function buildExportParameters(array $filters): array 
{
    $parameters = [];
    
    if (!empty($filters['date_start'])) {
        $parameters['date_start'] = $filters['date_start'];
    }
    
    if (!empty($filters['date_end'])) {
        $parameters['date_end'] = $filters['date_end'];
    }
    
    if (!empty($filters['status'])) {
        $parameters['status'] = $filters['status'];
    }
    
    return $parameters;
}

function formatValueForExport($value, string $field, string $format): string 
{
    if ($value === null || $value === '') return '';
    
    // Campos monetários
    $monetaryFields = ['valor_cif_brl', 'valor_cif_usd', 'total_impostos', 'custo_total_landed'];
    if (in_array($field, $monetaryFields)) {
        return $format === 'excel' ? $value : formatCurrency($value);
    }
    
    // Campos de data
    $dateFields = ['data_registro'];
    if (in_array($field, $dateFields)) {
        return date('d/m/Y', strtotime($value));
    }
    
    // Taxa de câmbio
    if ($field === 'taxa_cambio_media') {
        return number_format($value, 4);
    }
    
    return (string)$value;
}

function formatCurrency($value): string 
{
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

function generateExportFilename(string $type, string $format): string 
{
    $typeNames = [
        'dis' => 'Declaracoes_Importacao',
        'adicoes' => 'Adicoes',
        'impostos' => 'Impostos',
        'despesas' => 'Despesas',
        'custo_landed' => 'Custo_Landed',
        'performance' => 'Performance'
    ];
    
    $typeName = $typeNames[$type] ?? 'Export';
    $date = date('Y-m-d_H-i-s');
    
    return "{$typeName}_{$date}.{$format}";
}

function generateDownloadUrl(string $type, string $format, array $filters): string 
{
    $params = [
        'type' => $type,
        'format' => $format,
        'filters' => base64_encode(json_encode($filters)),
        'token' => generateDownloadToken($type, $format)
    ];
    
    return '/api/dashboard/download.php?' . http_build_query($params);
}

function generateDownloadToken(string $type, string $format): string 
{
    $data = [
        'type' => $type,
        'format' => $format,
        'timestamp' => time(),
        'expires' => time() + 3600 // 1 hora
    ];
    
    return base64_encode(json_encode($data));
}

function estimateExportSize(array $exportData, string $format): int 
{
    $recordCount = count($exportData['data'] ?? []);
    $columnCount = count($exportData['headers'] ?? []);
    
    // Estimativa baseada no formato
    switch ($format) {
        case 'excel':
            return $recordCount * $columnCount * 25; // ~25 bytes por célula
        case 'csv':
            return $recordCount * $columnCount * 15; // ~15 bytes por célula
        case 'pdf':
            return $recordCount * $columnCount * 35; // ~35 bytes por célula
        default:
            return $recordCount * $columnCount * 20;
    }
}

function formatBytes(int $bytes): string 
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= (1 << (10 * $pow));
    
    return round($bytes, 2) . ' ' . $units[$pow];
}