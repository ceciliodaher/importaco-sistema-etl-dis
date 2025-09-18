<?php
/**
 * ================================================================================
 * API DE PESQUISA AVANÇADA - OTIMIZADA COM CACHE
 * Sistema ETL DI's - Full-text search com faceted search (< 2s)
 * Performance: Índices MySQL + Cache inteligente + Paginação eficiente
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

    // Obter dados JSON do corpo da requisição
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        apiError('JSON inválido no corpo da requisição', 400)->send();
    }

    // Validar parâmetros
    $validator = new ApiValidator();
    $params = $input ?? [];
    
    // Parâmetros padrão
    $query = $params['query'] ?? '';
    $filters = $params['filters'] ?? [];
    $page = max(1, (int)($params['page'] ?? 1));
    $limit = max(10, min(100, (int)($params['limit'] ?? 25))); // Entre 10 e 100
    $sortBy = $params['sort_by'] ?? 'relevance';
    $sortOrder = $params['sort_order'] ?? 'desc';
    
    // Validar campos de ordenação permitidos
    $allowedSortFields = ['relevance', 'data_registro', 'valor_cif', 'numero_di', 'importador_nome'];
    if (!in_array($sortBy, $allowedSortFields)) {
        $sortBy = 'relevance';
    }
    
    if (!in_array($sortOrder, ['asc', 'desc'])) {
        $sortOrder = 'desc';
    }

    // Sanitizar query
    $query = $validator->sanitizeSql(trim($query));
    
    // Inicializar cache do dashboard
    $cache = getDashboardCache();
    $response = apiSuccess();
    
    // Chave de cache baseada nos parâmetros de pesquisa
    $searchKey = md5($query . json_encode($filters) . $page . $limit . $sortBy . $sortOrder);
    
    // Obter resultados com cache
    $searchResults = $cache->getSearch($query, compact('filters', 'page', 'limit', 'sortBy', 'sortOrder'), function() use ($query, $filters, $page, $limit, $sortBy, $sortOrder) {
        return performAdvancedSearch($query, $filters, $page, $limit, $sortBy, $sortOrder);
    });
    
    // Adicionar metadata
    $response->setCacheStats($searchResults !== null, 'L1+L2');
    $response->addMeta('query', $query);
    $response->addMeta('total_results', $searchResults['total'] ?? 0);
    
    // Configurar paginação
    $totalResults = $searchResults['total'] ?? 0;
    $totalPages = (int)ceil($totalResults / $limit);
    $response->setPagination($page, $limit, $totalResults, $totalPages);
    
    // Enviar resposta
    $response->setData([
        'results' => $searchResults['results'] ?? [],
        'facets' => $searchResults['facets'] ?? [],
        'suggestions' => $searchResults['suggestions'] ?? [],
        'highlights' => $searchResults['highlights'] ?? []
    ])->send();
    
} catch (Exception $e) {
    error_log("API Search Error: " . $e->getMessage());
    apiError('Erro interno do servidor', 500)->send();
}

/**
 * Executar pesquisa avançada otimizada
 * Performance target: < 1.5s
 */
function performAdvancedSearch(string $query, array $filters, int $page, int $limit, string $sortBy, string $sortOrder): array 
{
    try {
        $db = getDatabase();
        $pdo = $db->getConnection();
        
        // Construir consulta base usando views otimizadas
        $baseQuery = "
            FROM v_di_resumo di
            LEFT JOIN v_adicoes_completas ac ON di.numero_di = ac.numero_di
            LEFT JOIN v_despesas_discriminadas dd ON di.numero_di = dd.numero_di
        ";
        
        // Construir condições WHERE
        $whereConditions = [];
        $parameters = [];
        
        // Pesquisa de texto livre (full-text search)
        if (!empty($query)) {
            $whereConditions[] = buildFullTextSearch($query, $parameters);
        }
        
        // Aplicar filtros específicos
        if (!empty($filters)) {
            $whereConditions = array_merge($whereConditions, buildFilterConditions($filters, $parameters));
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        // Query para contar resultados totais
        $countQuery = "SELECT COUNT(DISTINCT di.numero_di) as total " . $baseQuery . " " . $whereClause;
        $countStmt = $pdo->prepare($countQuery);
        $countStmt->execute($parameters);
        $totalResults = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Query principal com resultados paginados
        $selectFields = "
            DISTINCT di.numero_di,
            di.data_registro,
            di.importador_nome,
            di.importador_cnpj,
            di.valor_cif_brl,
            di.valor_cif_usd,
            di.total_impostos,
            di.custo_total_landed,
            di.taxa_cambio_media,
            di.urf_despacho_nome,
            di.status_processamento,
            GROUP_CONCAT(DISTINCT ac.ncm SEPARATOR ', ') as ncms,
            GROUP_CONCAT(DISTINCT ac.ncm_descricao SEPARATOR '; ') as ncm_descricoes
        ";
        
        // Construir ORDER BY
        $orderBy = buildOrderByClause($sortBy, $sortOrder, $query);
        
        // Calcular OFFSET
        $offset = ($page - 1) * $limit;
        
        $mainQuery = "
            SELECT {$selectFields}
            {$baseQuery}
            {$whereClause}
            GROUP BY di.numero_di
            {$orderBy}
            LIMIT {$limit} OFFSET {$offset}
        ";
        
        $mainStmt = $pdo->prepare($mainQuery);
        $mainStmt->execute($parameters);
        $results = $mainStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Processar resultados para adicionar highlights
        $processedResults = processSearchResults($results, $query);
        
        // Gerar facets (agregações)
        $facets = generateSearchFacets($pdo, $whereClause, $parameters);
        
        // Gerar sugestões de pesquisa
        $suggestions = generateSearchSuggestions($query, $pdo);
        
        return [
            'results' => $processedResults,
            'total' => $totalResults,
            'facets' => $facets,
            'suggestions' => $suggestions,
            'highlights' => !empty($query) ? generateHighlights($query) : []
        ];
        
    } catch (Exception $e) {
        error_log("Error in performAdvancedSearch: " . $e->getMessage());
        
        // Fallback com dados básicos
        return [
            'results' => [],
            'total' => 0,
            'facets' => [],
            'suggestions' => [],
            'highlights' => [],
            'error' => 'Erro na pesquisa: ' . $e->getMessage()
        ];
    }
}

/**
 * Construir pesquisa full-text otimizada
 */
function buildFullTextSearch(string $query, array &$parameters): string 
{
    // Dividir query em termos
    $terms = array_filter(explode(' ', $query));
    $conditions = [];
    
    foreach ($terms as $index => $term) {
        $paramName = "search_term_{$index}";
        $parameters[$paramName] = "%{$term}%";
        
        $conditions[] = "(
            di.numero_di LIKE :{$paramName} OR
            di.importador_nome LIKE :{$paramName} OR
            di.importador_cnpj LIKE :{$paramName} OR
            ac.ncm LIKE :{$paramName} OR
            ac.ncm_descricao LIKE :{$paramName} OR
            di.urf_despacho_nome LIKE :{$paramName}
        )";
    }
    
    return !empty($conditions) ? '(' . implode(' AND ', $conditions) . ')' : '1=1';
}

/**
 * Construir condições de filtros específicos
 */
function buildFilterConditions(array $filters, array &$parameters): array 
{
    $conditions = [];
    
    // Filtro por período
    if (!empty($filters['date_range'])) {
        $dateRange = $filters['date_range'];
        
        if (!empty($dateRange['start'])) {
            $parameters['date_start'] = $dateRange['start'];
            $conditions[] = "di.data_registro >= :date_start";
        }
        
        if (!empty($dateRange['end'])) {
            $parameters['date_end'] = $dateRange['end'];
            $conditions[] = "di.data_registro <= :date_end";
        }
    }
    
    // Filtro por valor CIF
    if (!empty($filters['valor_range'])) {
        $valorRange = $filters['valor_range'];
        
        if (isset($valorRange['min']) && $valorRange['min'] > 0) {
            $parameters['valor_min'] = $valorRange['min'];
            $conditions[] = "di.valor_cif_brl >= :valor_min";
        }
        
        if (isset($valorRange['max']) && $valorRange['max'] > 0) {
            $parameters['valor_max'] = $valorRange['max'];
            $conditions[] = "di.valor_cif_brl <= :valor_max";
        }
    }
    
    // Filtro por UF
    if (!empty($filters['uf'])) {
        $ufs = array_filter((array)$filters['uf']);
        if (!empty($ufs)) {
            $ufParams = [];
            foreach ($ufs as $index => $uf) {
                $paramName = "uf_{$index}";
                $parameters[$paramName] = $uf;
                $ufParams[] = ":{$paramName}";
            }
            $conditions[] = "di.importador_uf IN (" . implode(', ', $ufParams) . ")";
        }
    }
    
    // Filtro por status
    if (!empty($filters['status'])) {
        $parameters['status'] = $filters['status'];
        $conditions[] = "di.status_processamento = :status";
    }
    
    // Filtro por NCM
    if (!empty($filters['ncm'])) {
        $parameters['ncm_filter'] = "%{$filters['ncm']}%";
        $conditions[] = "ac.ncm LIKE :ncm_filter";
    }
    
    return $conditions;
}

/**
 * Construir cláusula ORDER BY otimizada
 */
function buildOrderByClause(string $sortBy, string $sortOrder, string $query): string 
{
    $orderClauses = [];
    
    // Se há query de pesquisa, priorizar relevância
    if (!empty($query) && $sortBy === 'relevance') {
        // Score de relevância baseado em matches
        $orderClauses[] = "(
            CASE 
                WHEN di.numero_di LIKE '%{$query}%' THEN 100
                WHEN di.importador_nome LIKE '%{$query}%' THEN 80
                WHEN ac.ncm LIKE '%{$query}%' THEN 60
                WHEN ac.ncm_descricao LIKE '%{$query}%' THEN 40
                ELSE 1
            END
        ) DESC";
    }
    
    // Ordenação secundária
    switch ($sortBy) {
        case 'data_registro':
            $orderClauses[] = "di.data_registro {$sortOrder}";
            break;
        case 'valor_cif':
            $orderClauses[] = "di.valor_cif_brl {$sortOrder}";
            break;
        case 'numero_di':
            $orderClauses[] = "di.numero_di {$sortOrder}";
            break;
        case 'importador_nome':
            $orderClauses[] = "di.importador_nome {$sortOrder}";
            break;
        default:
            $orderClauses[] = "di.data_registro DESC";
    }
    
    return 'ORDER BY ' . implode(', ', $orderClauses);
}

/**
 * Processar resultados para adicionar informações extras
 */
function processSearchResults(array $results, string $query): array 
{
    $processed = [];
    
    foreach ($results as $result) {
        // Formatar valores monetários
        $result['valor_cif_formatted'] = 'R$ ' . number_format($result['valor_cif_brl'], 2, ',', '.');
        $result['custo_total_formatted'] = 'R$ ' . number_format($result['custo_total_landed'], 2, ',', '.');
        
        // Adicionar badge de status
        $result['status_badge'] = [
            'text' => $result['status_processamento'],
            'color' => getStatusColor($result['status_processamento'])
        ];
        
        // Processar NCMs (máximo 3 para display)
        $ncms = array_filter(explode(', ', $result['ncms'] ?? ''));
        $result['ncms_display'] = array_slice($ncms, 0, 3);
        $result['ncms_count'] = count($ncms);
        
        // Adicionar score de relevância se há query
        if (!empty($query)) {
            $result['relevance_score'] = calculateRelevanceScore($result, $query);
        }
        
        $processed[] = $result;
    }
    
    return $processed;
}

/**
 * Gerar facets para pesquisa avançada
 */
function generateSearchFacets(PDO $pdo, string $whereClause, array $parameters): array 
{
    $facets = [];
    
    try {
        // Facet: Status
        $statusQuery = "
            SELECT 
                di.status_processamento as value,
                COUNT(DISTINCT di.numero_di) as count
            FROM v_di_resumo di
            LEFT JOIN v_adicoes_completas ac ON di.numero_di = ac.numero_di
            {$whereClause}
            GROUP BY di.status_processamento
            ORDER BY count DESC
        ";
        
        $statusStmt = $pdo->prepare($statusQuery);
        $statusStmt->execute($parameters);
        $facets['status'] = $statusStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Facet: UF (top 10)
        $ufQuery = "
            SELECT 
                di.importador_uf as value,
                COUNT(DISTINCT di.numero_di) as count
            FROM v_di_resumo di
            LEFT JOIN v_adicoes_completas ac ON di.numero_di = ac.numero_di
            {$whereClause}
            AND di.importador_uf IS NOT NULL
            GROUP BY di.importador_uf
            ORDER BY count DESC
            LIMIT 10
        ";
        
        $ufStmt = $pdo->prepare($ufQuery);
        $ufStmt->execute($parameters);
        $facets['uf'] = $ufStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Facet: Faixas de valor
        $valorFaixas = [
            ['label' => 'Até R$ 100K', 'min' => 0, 'max' => 100000],
            ['label' => 'R$ 100K - R$ 500K', 'min' => 100000, 'max' => 500000],
            ['label' => 'R$ 500K - R$ 1M', 'min' => 500000, 'max' => 1000000],
            ['label' => 'R$ 1M - R$ 5M', 'min' => 1000000, 'max' => 5000000],
            ['label' => 'Acima de R$ 5M', 'min' => 5000000, 'max' => null]
        ];
        
        $facets['valor_ranges'] = [];
        foreach ($valorFaixas as $faixa) {
            $conditions = [$whereClause];
            $faixaParams = $parameters;
            
            $conditions[] = "di.valor_cif_brl >= {$faixa['min']}";
            if ($faixa['max'] !== null) {
                $conditions[] = "di.valor_cif_brl <= {$faixa['max']}";
            }
            
            $faixaWhere = implode(' AND ', array_filter($conditions));
            
            $faixaQuery = "
                SELECT COUNT(DISTINCT di.numero_di) as count
                FROM v_di_resumo di
                LEFT JOIN v_adicoes_completas ac ON di.numero_di = ac.numero_di
                {$faixaWhere}
            ";
            
            $faixaStmt = $pdo->prepare($faixaQuery);
            $faixaStmt->execute($faixaParams);
            $count = $faixaStmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($count > 0) {
                $facets['valor_ranges'][] = [
                    'label' => $faixa['label'],
                    'count' => (int)$count,
                    'min' => $faixa['min'],
                    'max' => $faixa['max']
                ];
            }
        }
        
    } catch (Exception $e) {
        error_log("Error generating facets: " . $e->getMessage());
    }
    
    return $facets;
}

/**
 * Gerar sugestões de pesquisa
 */
function generateSearchSuggestions(string $query, PDO $pdo): array 
{
    if (empty($query) || strlen($query) < 3) {
        return [];
    }
    
    $suggestions = [];
    
    try {
        // Sugestões baseadas em importadores
        $importerQuery = "
            SELECT DISTINCT importador_nome as suggestion, 'importador' as type
            FROM declaracoes_importacao 
            WHERE importador_nome LIKE :query
            ORDER BY importador_nome
            LIMIT 5
        ";
        
        $importerStmt = $pdo->prepare($importerQuery);
        $importerStmt->execute(['query' => "%{$query}%"]);
        $suggestions = array_merge($suggestions, $importerStmt->fetchAll(PDO::FETCH_ASSOC));
        
        // Sugestões baseadas em NCM
        $ncmQuery = "
            SELECT DISTINCT CONCAT(codigo_ncm, ' - ', descricao) as suggestion, 'ncm' as type
            FROM ncm_referencia 
            WHERE codigo_ncm LIKE :query OR descricao LIKE :query
            ORDER BY codigo_ncm
            LIMIT 5
        ";
        
        $ncmStmt = $pdo->prepare($ncmQuery);
        $ncmStmt->execute(['query' => "%{$query}%"]);
        $suggestions = array_merge($suggestions, $ncmStmt->fetchAll(PDO::FETCH_ASSOC));
        
    } catch (Exception $e) {
        error_log("Error generating suggestions: " . $e->getMessage());
    }
    
    return array_slice($suggestions, 0, 8); // Máximo 8 sugestões
}

/**
 * Helper functions
 */
function getStatusColor(string $status): string 
{
    $colors = [
        'COMPLETO' => 'success',
        'PENDENTE' => 'warning',
        'ERRO' => 'danger',
        'PROCESSANDO' => 'info'
    ];
    
    return $colors[$status] ?? 'secondary';
}

function calculateRelevanceScore(array $result, string $query): int 
{
    $score = 0;
    $queryLower = strtolower($query);
    
    // Pontuação por campo
    if (strpos(strtolower($result['numero_di']), $queryLower) !== false) $score += 100;
    if (strpos(strtolower($result['importador_nome']), $queryLower) !== false) $score += 80;
    if (strpos(strtolower($result['ncms'] ?? ''), $queryLower) !== false) $score += 60;
    if (strpos(strtolower($result['ncm_descricoes'] ?? ''), $queryLower) !== false) $score += 40;
    
    return $score;
}

function generateHighlights(string $query): array 
{
    $terms = array_filter(explode(' ', $query));
    
    return [
        'terms' => $terms,
        'fields' => ['numero_di', 'importador_nome', 'ncm_descricoes'],
        'pre_tag' => '<mark>',
        'post_tag' => '</mark>'
    ];
}