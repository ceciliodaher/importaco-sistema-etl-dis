<?php
/**
 * Debug detalhado para identificar erro na geração de gráficos
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== DEBUG DETALHADO CHARTS ===\n";

// Simular ambiente da API
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['type'] = 'all';
$_GET['period'] = '6months';

// Includes corretos
require_once '/Users/ceciliodaher/Documents/git/importaco-sistema/sistema/dashboard/api/common/response.php';
require_once '/Users/ceciliodaher/Documents/git/importaco-sistema/sistema/dashboard/api/common/cache.php';
require_once '/Users/ceciliodaher/Documents/git/importaco-sistema/sistema/dashboard/api/common/validator.php';
require_once '/Users/ceciliodaher/Documents/git/importaco-sistema/sistema/config/database.php';

try {
    echo "1. ✅ Includes carregados com sucesso\n";
    
    // Testar conexão
    $db = getDatabase();
    $pdo = $db->getConnection();
    echo "2. ✅ Conexão com banco estabelecida\n";
    
    // Testar cada tipo de gráfico individualmente
    $chartTypes = ['evolution', 'taxes', 'expenses', 'currencies', 'states', 'correlation'];
    
    foreach ($chartTypes as $type) {
        echo "\n--- Testando gráfico: {$type} ---\n";
        
        try {
            // Simular a função generateChartData para cada tipo
            switch ($type) {
                case 'evolution':
                    $stmt = $pdo->query("
                        SELECT 
                            DATE_FORMAT(di.data_registro, '%Y-%m') as ano_mes,
                            COUNT(DISTINCT di.numero_di) as total_dis,
                            SUM(di.valor_total_cif_brl) / 1000000 as cif_total_milhoes
                        FROM declaracoes_importacao di
                        LEFT JOIN adicoes a ON di.numero_di = a.numero_di
                        LEFT JOIN impostos_adicao imp ON a.id = imp.adicao_id
                        WHERE di.data_registro >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                        GROUP BY DATE_FORMAT(di.data_registro, '%Y-%m')
                        ORDER BY ano_mes ASC
                        LIMIT 6
                    ");
                    break;
                    
                case 'taxes':
                    $stmt = $pdo->query("
                        SELECT 
                            SUM(CASE WHEN imp.tipo_imposto = 'II' THEN imp.valor_devido_reais ELSE 0 END) as ii_total,
                            SUM(CASE WHEN imp.tipo_imposto = 'IPI' THEN imp.valor_devido_reais ELSE 0 END) as ipi_total
                        FROM impostos_adicao imp
                        JOIN adicoes a ON imp.adicao_id = a.id
                        JOIN declaracoes_importacao di ON a.numero_di = di.numero_di
                        WHERE di.data_registro >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                    ");
                    break;
                    
                case 'expenses':
                    $stmt = $pdo->query("
                        SELECT 
                            categoria,
                            SUM(valor_final) as valor_total
                        FROM despesas_extras de
                        JOIN declaracoes_importacao di ON de.numero_di = di.numero_di
                        WHERE di.data_registro >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                        GROUP BY categoria
                        ORDER BY valor_total DESC
                        LIMIT 10
                    ");
                    break;
                    
                case 'currencies':
                    $stmt = $pdo->query("
                        SELECT 
                            a.moeda_codigo,
                            COUNT(DISTINCT di.numero_di) as dis_count
                        FROM declaracoes_importacao di
                        JOIN adicoes a ON di.numero_di = a.numero_di
                        WHERE di.data_registro >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                        GROUP BY a.moeda_codigo
                        ORDER BY dis_count DESC
                        LIMIT 5
                    ");
                    break;
                    
                case 'states':
                    $stmt = $pdo->query("
                        SELECT 
                            di.importador_uf as uf,
                            COUNT(DISTINCT di.numero_di) as dis_count
                        FROM declaracoes_importacao di
                        WHERE di.data_registro >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                          AND di.importador_uf IS NOT NULL
                        GROUP BY di.importador_uf
                        ORDER BY dis_count DESC
                    ");
                    break;
                    
                case 'correlation':
                    $stmt = $pdo->query("
                        SELECT 
                            di.numero_di,
                            a.taxa_cambio_calculada,
                            di.valor_total_cif_brl
                        FROM declaracoes_importacao di
                        JOIN adicoes a ON di.numero_di = a.numero_di
                        WHERE di.data_registro >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                          AND a.taxa_cambio_calculada > 0
                        LIMIT 10
                    ");
                    break;
            }
            
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($data)) {
                echo "⚠️  {$type}: Nenhum dado retornado\n";
            } else {
                echo "✅ {$type}: " . count($data) . " registros encontrados\n";
                // Mostrar primeiro registro como amostra
                if (isset($data[0])) {
                    echo "   Amostra: " . json_encode($data[0]) . "\n";
                }
            }
            
        } catch (Exception $e) {
            echo "❌ {$type}: ERRO - " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n=== TESTE COMPLETO FINALIZADO ===\n";
    
} catch (Exception $e) {
    echo "❌ ERRO FATAL: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}