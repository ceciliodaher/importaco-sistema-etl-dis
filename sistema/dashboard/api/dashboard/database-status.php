<?php
/**
 * ================================================================================
 * API DE STATUS DO BANCO - CONTROLE MANUAL DASHBOARD
 * Sistema ETL DI's - Verificação de dados e período disponível
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

    // Obter status completo do banco
    $status = getDatabaseStatus();
    
    // Determinar se há dados suficientes para gráficos
    $sufficientForCharts = $status['dis_count'] >= 3 && $status['adicoes_count'] >= 5;
    
    // Formatar resposta
    $response = apiSuccess([
        'dis_count' => $status['dis_count'],
        'adicoes_count' => $status['adicoes_count'],
        'impostos_count' => $status['impostos_count'],
        'sufficient_for_charts' => $sufficientForCharts,
        'period' => $status['period'],
        'database_ready' => $status['database_ready'],
        'tables_status' => $status['tables_status'],
        'data_quality' => $status['data_quality']
    ]);
    
    $response->addMeta('check_timestamp', date('Y-m-d H:i:s'));
    $response->addMeta('minimum_dis_for_charts', 3);
    $response->send();
    
} catch (Exception $e) {
    error_log("API Database Status Error: " . $e->getMessage());
    apiError('Erro ao verificar status do banco: ' . $e->getMessage(), 500)->send();
}

/**
 * Obter status completo do banco de dados
 */
function getDatabaseStatus(): array 
{
    try {
        $db = getDatabase();
        $pdo = $db->getConnection();
        
        // Verificar se o banco está pronto
        $databaseReady = $db->testConnection() && $db->isDatabaseReady();
        
        if (!$databaseReady) {
            return [
                'dis_count' => 0,
                'adicoes_count' => 0,
                'impostos_count' => 0,
                'period' => 'Sem dados',
                'database_ready' => false,
                'tables_status' => getTablesStatus($pdo),
                'data_quality' => []
            ];
        }
        
        // Query para contagens básicas
        $stmt = $pdo->query("
            SELECT 
                (SELECT COUNT(*) FROM declaracoes_importacao) as dis_count,
                (SELECT COUNT(*) FROM adicoes) as adicoes_count,
                (SELECT COUNT(*) FROM impostos_adicao) as impostos_count,
                (SELECT MIN(data_registro) FROM declaracoes_importacao WHERE data_registro IS NOT NULL) as primeira_di,
                (SELECT MAX(data_registro) FROM declaracoes_importacao WHERE data_registro IS NOT NULL) as ultima_di
        ");
        
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Formatar período
        $period = formatPeriod($data['primeira_di'], $data['ultima_di']);
        
        // Verificar qualidade dos dados
        $dataQuality = getDataQuality($pdo, $data);
        
        return [
            'dis_count' => (int)$data['dis_count'],
            'adicoes_count' => (int)$data['adicoes_count'],
            'impostos_count' => (int)$data['impostos_count'],
            'period' => $period,
            'database_ready' => true,
            'tables_status' => getTablesStatus($pdo),
            'data_quality' => $dataQuality
        ];
        
    } catch (Exception $e) {
        throw new Exception("Erro ao acessar banco: " . $e->getMessage());
    }
}

/**
 * Verificar status das tabelas principais
 */
function getTablesStatus(PDO $pdo): array 
{
    $tables = [
        'declaracoes_importacao',
        'adicoes', 
        'mercadorias',
        'impostos_adicao',
        'moedas_referencia',
        'ncm_referencia'
    ];
    
    $status = [];
    
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM {$table}");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $status[$table] = [
                'exists' => true,
                'count' => (int)$result['count'],
                'status' => $result['count'] > 0 ? 'populated' : 'empty'
            ];
            
        } catch (Exception $e) {
            $status[$table] = [
                'exists' => false,
                'count' => 0,
                'status' => 'missing',
                'error' => $e->getMessage()
            ];
        }
    }
    
    return $status;
}

/**
 * Analisar qualidade dos dados
 */
function getDataQuality(PDO $pdo, array $basicData): array 
{
    $quality = [];
    
    try {
        // Verificar DIs com dados completos
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as dis_completas
            FROM declaracoes_importacao 
            WHERE numero_di IS NOT NULL 
            AND ano_di IS NOT NULL 
            AND data_registro IS NOT NULL
            AND cif_total > 0
        ");
        $completas = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verificar adições órfãs (sem DI correspondente)
        $stmt = $pdo->query("
            SELECT COUNT(*) as adicoes_orfas
            FROM adicoes a
            LEFT JOIN declaracoes_importacao di ON a.di_id = di.id
            WHERE di.id IS NULL
        ");
        $orfas = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verificar impostos sem adições
        $stmt = $pdo->query("
            SELECT COUNT(*) as impostos_orfaos
            FROM impostos_adicao ia
            LEFT JOIN adicoes a ON ia.adicao_id = a.id
            WHERE a.id IS NULL
        ");
        $impostosOrfaos = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calcular percentuais de qualidade
        $totalDis = (int)$basicData['dis_count'];
        $disCompletas = (int)$completas['dis_completas'];
        
        $quality = [
            'dis_completas' => $disCompletas,
            'dis_incompletas' => $totalDis - $disCompletas,
            'completude_pct' => $totalDis > 0 ? round(($disCompletas / $totalDis) * 100, 2) : 0,
            'adicoes_orfas' => (int)$orfas['adicoes_orfas'],
            'impostos_orfaos' => (int)$impostosOrfaos['impostos_orfaos'],
            'data_integrity' => [
                'dis_with_adicoes' => getDIsWithAdicoes($pdo),
                'adicoes_with_impostos' => getAdicoesWithImpostos($pdo),
                'avg_adicoes_per_di' => getAverageAdicoesPorDI($pdo)
            ]
        ];
        
    } catch (Exception $e) {
        $quality['error'] = 'Erro na análise de qualidade: ' . $e->getMessage();
    }
    
    return $quality;
}

/**
 * Contar DIs que têm adições
 */
function getDIsWithAdicoes(PDO $pdo): int 
{
    try {
        $stmt = $pdo->query("
            SELECT COUNT(DISTINCT di.id) as count
            FROM declaracoes_importacao di
            INNER JOIN adicoes a ON di.id = a.di_id
        ");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['count'];
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Contar adições que têm impostos
 */
function getAdicoesWithImpostos(PDO $pdo): int 
{
    try {
        $stmt = $pdo->query("
            SELECT COUNT(DISTINCT a.id) as count
            FROM adicoes a
            INNER JOIN impostos_adicao ia ON a.id = ia.adicao_id
        ");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['count'];
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Calcular média de adições por DI
 */
function getAverageAdicoesPorDI(PDO $pdo): float 
{
    try {
        $stmt = $pdo->query("
            SELECT AVG(adicoes_count) as avg_count
            FROM (
                SELECT COUNT(a.id) as adicoes_count
                FROM declaracoes_importacao di
                LEFT JOIN adicoes a ON di.id = a.di_id
                GROUP BY di.id
            ) as counts
        ");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return round((float)$result['avg_count'], 2);
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Formatar período de dados
 */
function formatPeriod(?string $primeira, ?string $ultima): string 
{
    if (!$primeira || !$ultima) {
        return 'Sem dados';
    }
    
    try {
        $primeiraDate = new DateTime($primeira);
        $ultimaDate = new DateTime($ultima);
        
        $primeiraFormatted = $primeiraDate->format('Y-m');
        $ultimaFormatted = $ultimaDate->format('Y-m');
        
        if ($primeiraFormatted === $ultimaFormatted) {
            return $primeiraFormatted;
        }
        
        return $primeiraFormatted . ' a ' . $ultimaFormatted;
        
    } catch (Exception $e) {
        return 'Período inválido';
    }
}