<?php
/**
 * DEBUG ESPECÍFICO: handleAllChartsRequest
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "🔍 DEBUG: handleAllChartsRequest\n";
echo "===============================\n";

// Simular ambiente de requisição
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['type'] = 'all';
$_GET['period'] = '6months';

try {
    // Carregar dependências
    require_once 'sistema/dashboard/api/common/response.php';
    require_once 'sistema/dashboard/api/common/cache.php';
    require_once 'sistema/dashboard/api/common/validator.php';
    require_once 'sistema/config/database.php';

    echo "✅ Dependências carregadas\n";

    // Testar função individual primeiro
    echo "\n📊 Testando gráficos individuais:\n";
    
    $chartTypes = ['evolution', 'taxes', 'expenses', 'currencies', 'states', 'correlation'];
    $workingCharts = [];
    $failingCharts = [];
    
    $cache = getDashboardCache();
    $db = getDatabase();
    $connection = $db->getConnection();
    
    foreach ($chartTypes as $type) {
        echo "  - Testando {$type}... ";
        
        try {
            // Testar query específica de cada tipo
            switch ($type) {
                case 'evolution':
                    $stmt = $connection->query("
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
                    $stmt = $connection->query("
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
                    // Esta pode falhar se não há despesas_extras
                    $stmt = $connection->query("
                        SELECT categoria, SUM(valor_final) as valor_total
                        FROM despesas_extras de
                        JOIN declaracoes_importacao di ON de.numero_di = di.numero_di
                        WHERE di.data_registro >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                        GROUP BY categoria
                        LIMIT 16
                    ");
                    break;
                    
                case 'currencies':
                    $stmt = $connection->query("
                        SELECT 
                            a.moeda_codigo,
                            COUNT(DISTINCT di.numero_di) as dis_count
                        FROM declaracoes_importacao di
                        JOIN adicoes a ON di.numero_di = a.numero_di
                        WHERE di.data_registro >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                        GROUP BY a.moeda_codigo
                        LIMIT 8
                    ");
                    break;
                    
                case 'states':
                    // Esta pode falhar se não há coluna importador_uf
                    $stmt = $connection->query("
                        SELECT 
                            di.importador_cnpj as identificador,
                            COUNT(DISTINCT di.numero_di) as dis_count
                        FROM declaracoes_importacao di
                        WHERE di.data_registro >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                        GROUP BY di.importador_cnpj
                        LIMIT 10
                    ");
                    break;
                    
                case 'correlation':
                    $stmt = $connection->query("
                        SELECT 
                            di.numero_di,
                            di.valor_total_cif_brl
                        FROM declaracoes_importacao di
                        JOIN adicoes a ON di.numero_di = a.numero_di
                        WHERE di.data_registro >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                          AND di.valor_total_cif_brl > 0
                        LIMIT 50
                    ");
                    break;
            }
            
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($data)) {
                echo "✅ (" . count($data) . " registros)\n";
                $workingCharts[] = $type;
            } else {
                echo "⚠️  (sem dados)\n";
                // Ainda consideramos como working se não deu erro
                $workingCharts[] = $type;
            }
            
        } catch (Exception $e) {
            echo "❌ ERRO: " . $e->getMessage() . "\n";
            $failingCharts[] = $type;
        }
    }
    
    echo "\n📈 Resultado dos testes:\n";
    echo "✅ Gráficos funcionando: " . implode(', ', $workingCharts) . "\n";
    echo "❌ Gráficos com problema: " . implode(', ', $failingCharts) . "\n";
    
    // Agora simular handleAllChartsRequest
    echo "\n🔄 Simulando handleAllChartsRequest...\n";
    
    $allCharts = [];
    $errors = [];
    
    foreach ($workingCharts as $type) {
        echo "  - Processando {$type}... ";
        
        try {
            // Simular a lógica do cache
            $chartData = $cache->getChart($type, ['period' => '6months', 'filters' => []], function() use ($type) {
                // Mock data estruturado
                return [
                    'type' => $type,
                    'data' => [
                        'labels' => ['Mock'],
                        'datasets' => [['data' => [1]]]
                    ],
                    'generated_at' => date('Y-m-d H:i:s')
                ];
            });
            
            $allCharts[$type] = $chartData;
            echo "✅\n";
            
        } catch (Exception $e) {
            echo "❌ " . $e->getMessage() . "\n";
            $errors[] = "{$type}: " . $e->getMessage();
        }
    }
    
    // Tentar criar response final
    echo "\n🎯 Criando response final...\n";
    
    $finalResult = [
        'charts' => $allCharts,
        'summary' => [
            'period' => '6months',
            'generated_at' => date('Y-m-d H:i:s'),
            'cache_enabled' => true,
            'working_charts' => count($workingCharts),
            'total_charts' => count($allCharts)
        ]
    ];
    
    $jsonResult = json_encode($finalResult);
    
    echo "Charts processados: " . count($allCharts) . "\n";
    echo "JSON válido: " . ($jsonResult ? "✅" : "❌") . "\n";
    echo "Erros encontrados: " . count($errors) . "\n";
    
    if (!empty($errors)) {
        echo "\nErros detalhados:\n";
        foreach ($errors as $error) {
            echo "- {$error}\n";
        }
    }
    
    if ($jsonResult && count($allCharts) > 0) {
        echo "\n🎉 handleAllChartsRequest DEVERIA FUNCIONAR!\n";
    } else {
        echo "\n💥 PROBLEMA IDENTIFICADO na handleAllChartsRequest\n";
    }
    
} catch (Exception $e) {
    echo "💥 ERRO GERAL: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}