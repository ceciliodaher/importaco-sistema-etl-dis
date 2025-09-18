<?php
/**
 * Debug script para identificar o erro HTTP 500 no charts.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== DEBUG CHARTS API ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // Simular a chamada da API
    $originalDir = getcwd();
    chdir('/Users/ceciliodaher/Documents/git/importaco-sistema/sistema/dashboard/api/dashboard');
    
    echo "1. Verificando arquivos de dependência...\n";
    
    $files = [
        'common/response.php' => dirname(__DIR__) . '/common/response.php',
        'common/cache.php' => dirname(__DIR__) . '/common/cache.php', 
        'common/validator.php' => dirname(__DIR__) . '/common/validator.php',
        'config/database.php' => dirname(__DIR__, 3) . '/config/database.php'
    ];
    
    foreach ($files as $name => $path) {
        if (file_exists($path)) {
            echo "✅ {$name} - OK\n";
        } else {
            echo "❌ {$name} - MISSING: {$path}\n";
        }
    }
    
    echo "\n2. Testando includes...\n";
    
    // Testar cada include individualmente
    foreach ($files as $name => $path) {
        if (file_exists($path)) {
            try {
                require_once $path;
                echo "✅ Include {$name} - OK\n";
            } catch (Exception $e) {
                echo "❌ Include {$name} - ERROR: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\n3. Testando conexão com banco...\n";
    
    try {
        $db = getDatabase();
        $pdo = $db->getConnection();
        echo "✅ Conexão com banco - OK\n";
        
        // Testar query simples
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM declaracoes_importacao");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "✅ Query teste - OK: {$result['total']} DIs\n";
        
    } catch (Exception $e) {
        echo "❌ Conexão com banco - ERROR: " . $e->getMessage() . "\n";
    }
    
    echo "\n4. Testando função generateChartData...\n";
    
    try {
        // Simular parâmetros da API
        $_GET['type'] = 'all';
        $_GET['period'] = '6months';
        
        // Testar middleware
        if (function_exists('apiMiddleware')) {
            echo "✅ Função apiMiddleware encontrada\n";
        } else {
            echo "❌ Função apiMiddleware não encontrada\n";
        }
        
        // Testar cache
        if (function_exists('getDashboardCache')) {
            echo "✅ Função getDashboardCache encontrada\n";
        } else {
            echo "❌ Função getDashboardCache não encontrada\n";
        }
        
        // Testar validador
        if (class_exists('ApiValidator')) {
            echo "✅ Classe ApiValidator encontrada\n";
        } else {
            echo "❌ Classe ApiValidator não encontrada\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Teste de funções - ERROR: " . $e->getMessage() . "\n";
    }
    
    echo "\n5. Testando geração de gráfico individual...\n";
    
    try {
        // Testar função específica que pode estar falhando
        $db = getDatabase();
        $pdo = $db->getConnection();
        
        // Testar query de evolução
        $stmt = $pdo->query("
            SELECT 
                DATE_FORMAT(di.data_registro, '%Y-%m') as ano_mes,
                COUNT(DISTINCT di.numero_di) as total_dis
            FROM declaracoes_importacao di
            WHERE di.data_registro >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(di.data_registro, '%Y-%m')
            ORDER BY ano_mes ASC
            LIMIT 6
        ");
        
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "✅ Query evolução - OK: " . count($data) . " registros\n";
        
        if (!empty($data)) {
            foreach ($data as $row) {
                echo "  - {$row['ano_mes']}: {$row['total_dis']} DIs\n";
            }
        }
        
    } catch (Exception $e) {
        echo "❌ Teste query evolução - ERROR: " . $e->getMessage() . "\n";
    }
    
    chdir($originalDir);
    
    echo "\n6. Testando handleAllChartsRequest diretamente...\n";
    
    try {
        // Capturar output da função
        ob_start();
        
        // Simular $_SERVER para evitar erros
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_HOST'] = 'importacao-sistema.local';
        $_SERVER['REQUEST_URI'] = '/test';
        
        // Incluir o arquivo charts.php para ter acesso às funções
        include '/Users/ceciliodaher/Documents/git/importaco-sistema/sistema/dashboard/api/dashboard/charts.php';
        
        $output = ob_get_contents();
        ob_end_clean();
        
        echo "✅ Include charts.php - OK\n";
        if (!empty($output)) {
            echo "Output capturado: " . substr($output, 0, 200) . "...\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Include charts.php - ERROR: " . $e->getMessage() . "\n";
        echo "Stack trace: " . $e->getTraceAsString() . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERRO FATAL: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== FIM DEBUG ===\n";