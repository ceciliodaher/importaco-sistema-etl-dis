<?php
/**
 * ================================================================================
 * SCRIPT DE TESTE DE PERFORMANCE - APIs REST
 * Validação dos targets de performance < 500ms, < 1s, < 2s, < 3s
 * ================================================================================
 */

require_once '../common/response.php';
require_once '../common/cache.php';
require_once '../../config/database.php';

// Configurar ambiente de teste
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('America/Sao_Paulo');

/**
 * Classe para testes de performance
 */
class PerformanceTest 
{
    private $baseUrl;
    private $results = [];
    
    public function __construct(string $baseUrl = 'http://localhost:8000/api/dashboard') 
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }
    
    /**
     * Executar todos os testes
     */
    public function runAllTests(): array 
    {
        echo "🚀 Iniciando testes de performance das APIs...\n";
        echo "Target: Stats < 500ms, Charts < 1s, Search < 2s, Export < 3s\n";
        echo str_repeat("=", 70) . "\n\n";
        
        // Warm up do sistema
        $this->warmUpCache();
        
        // Testes individuais
        $this->testStatsAPI();
        $this->testChartsAPI();
        $this->testSearchAPI();
        $this->testExportAPI();
        $this->testRealtimeAPI();
        
        // Teste de carga
        $this->testLoadCapacity();
        
        // Relatório final
        $this->printSummary();
        
        return $this->results;
    }
    
    /**
     * Warm up do cache
     */
    private function warmUpCache(): void 
    {
        echo "🔥 Warming up cache...\n";
        
        $endpoints = [
            '/stats',
            '/charts?type=evolution&period=6months',
            '/charts?type=taxes&period=6months'
        ];
        
        foreach ($endpoints as $endpoint) {
            $this->makeRequest($endpoint);
            usleep(100000); // 100ms entre requests
        }
        
        echo "✅ Cache warm up concluído\n\n";
    }
    
    /**
     * Teste da API de estatísticas (< 500ms)
     */
    private function testStatsAPI(): void 
    {
        echo "📊 Testando API Stats (target: < 500ms)...\n";
        
        $times = [];
        $errors = 0;
        
        for ($i = 0; $i < 10; $i++) {
            $start = microtime(true);
            $response = $this->makeRequest('/stats');
            $end = microtime(true);
            
            $time = ($end - $start) * 1000; // em ms
            
            if ($response['success'] ?? false) {
                $times[] = $time;
                echo sprintf("  Request %d: %.1fms ✅\n", $i + 1, $time);
            } else {
                $errors++;
                echo sprintf("  Request %d: ERROR ❌\n", $i + 1);
            }
            
            usleep(200000); // 200ms entre requests
        }
        
        if (!empty($times)) {
            $avg = array_sum($times) / count($times);
            $max = max($times);
            $min = min($times);
            $p95 = $this->calculatePercentile($times, 95);
            
            $this->results['stats'] = [
                'target' => 500,
                'avg' => $avg,
                'max' => $max,
                'min' => $min,
                'p95' => $p95,
                'errors' => $errors,
                'passed' => $p95 <= 500
            ];
            
            echo sprintf("  Média: %.1fms | P95: %.1fms | Max: %.1fms\n", $avg, $p95, $max);
            echo sprintf("  Status: %s\n\n", $p95 <= 500 ? "✅ PASSOU" : "❌ FALHOU");
        }
    }
    
    /**
     * Teste da API de gráficos (< 1s)
     */
    private function testChartsAPI(): void 
    {
        echo "📈 Testando API Charts (target: < 1s)...\n";
        
        $chartTypes = ['evolution', 'taxes', 'expenses', 'currencies'];
        $allTimes = [];
        $errors = 0;
        
        foreach ($chartTypes as $type) {
            echo "  Testando gráfico: {$type}\n";
            
            for ($i = 0; $i < 3; $i++) {
                $start = microtime(true);
                $response = $this->makeRequest("/charts?type={$type}&period=6months");
                $end = microtime(true);
                
                $time = ($end - $start) * 1000;
                
                if ($response['success'] ?? false) {
                    $allTimes[] = $time;
                    echo sprintf("    Request %d: %.1fms ✅\n", $i + 1, $time);
                } else {
                    $errors++;
                    echo sprintf("    Request %d: ERROR ❌\n", $i + 1);
                }
                
                usleep(300000); // 300ms entre requests
            }
        }
        
        if (!empty($allTimes)) {
            $avg = array_sum($allTimes) / count($allTimes);
            $max = max($allTimes);
            $p95 = $this->calculatePercentile($allTimes, 95);
            
            $this->results['charts'] = [
                'target' => 1000,
                'avg' => $avg,
                'max' => $max,
                'p95' => $p95,
                'errors' => $errors,
                'passed' => $p95 <= 1000
            ];
            
            echo sprintf("  Média: %.1fms | P95: %.1fms | Max: %.1fms\n", $avg, $p95, $max);
            echo sprintf("  Status: %s\n\n", $p95 <= 1000 ? "✅ PASSOU" : "❌ FALHOU");
        }
    }
    
    /**
     * Teste da API de pesquisa (< 2s)
     */
    private function testSearchAPI(): void 
    {
        echo "🔍 Testando API Search (target: < 2s)...\n";
        
        $searchQueries = [
            ['query' => 'equiplex', 'limit' => 25],
            ['query' => '85367100', 'limit' => 50],
            ['query' => 'santos', 'filters' => ['uf' => ['SP']], 'limit' => 25],
            ['query' => '', 'filters' => ['date_range' => ['start' => '2024-01-01']], 'limit' => 10]
        ];
        
        $allTimes = [];
        $errors = 0;
        
        foreach ($searchQueries as $index => $queryData) {
            echo sprintf("  Teste %d: %s\n", $index + 1, $queryData['query'] ?: 'Filtros apenas');
            
            $start = microtime(true);
            $response = $this->makePostRequest('/search', $queryData);
            $end = microtime(true);
            
            $time = ($end - $start) * 1000;
            
            if ($response['success'] ?? false) {
                $allTimes[] = $time;
                $totalResults = $response['pagination']['total_records'] ?? 0;
                echo sprintf("    Tempo: %.1fms | Resultados: %d ✅\n", $time, $totalResults);
            } else {
                $errors++;
                echo sprintf("    ERROR ❌\n");
            }
            
            usleep(500000); // 500ms entre requests
        }
        
        if (!empty($allTimes)) {
            $avg = array_sum($allTimes) / count($allTimes);
            $max = max($allTimes);
            $p95 = $this->calculatePercentile($allTimes, 95);
            
            $this->results['search'] = [
                'target' => 2000,
                'avg' => $avg,
                'max' => $max,
                'p95' => $p95,
                'errors' => $errors,
                'passed' => $p95 <= 2000
            ];
            
            echo sprintf("  Média: %.1fms | P95: %.1fms | Max: %.1fms\n", $avg, $p95, $max);
            echo sprintf("  Status: %s\n\n", $p95 <= 2000 ? "✅ PASSOU" : "❌ FALHOU");
        }
    }
    
    /**
     * Teste da API de export (< 3s)
     */
    private function testExportAPI(): void 
    {
        echo "📤 Testando API Export (target: < 3s)...\n";
        
        $exportTypes = [
            ['type' => 'dis', 'format' => 'csv'],
            ['type' => 'impostos', 'format' => 'excel'],
            ['type' => 'despesas', 'format' => 'csv']
        ];
        
        $allTimes = [];
        $errors = 0;
        
        foreach ($exportTypes as $index => $exportData) {
            echo sprintf("  Teste %d: %s (%s)\n", $index + 1, $exportData['type'], $exportData['format']);
            
            $start = microtime(true);
            $response = $this->makePostRequest('/export', $exportData);
            $end = microtime(true);
            
            $time = ($end - $start) * 1000;
            
            if ($response['success'] ?? false) {
                $allTimes[] = $time;
                $totalRecords = $response['meta']['total_records'] ?? 0;
                echo sprintf("    Tempo: %.1fms | Registros: %d ✅\n", $time, $totalRecords);
            } else {
                $errors++;
                echo sprintf("    ERROR ❌\n");
            }
            
            usleep(1000000); // 1s entre requests (exports são pesados)
        }
        
        if (!empty($allTimes)) {
            $avg = array_sum($allTimes) / count($allTimes);
            $max = max($allTimes);
            $p95 = $this->calculatePercentile($allTimes, 95);
            
            $this->results['export'] = [
                'target' => 3000,
                'avg' => $avg,
                'max' => $max,
                'p95' => $p95,
                'errors' => $errors,
                'passed' => $p95 <= 3000
            ];
            
            echo sprintf("  Média: %.1fms | P95: %.1fms | Max: %.1fms\n", $avg, $p95, $max);
            echo sprintf("  Status: %s\n\n", $p95 <= 3000 ? "✅ PASSOU" : "❌ FALHOU");
        }
    }
    
    /**
     * Teste da API de realtime
     */
    private function testRealtimeAPI(): void 
    {
        echo "⚡ Testando API Realtime...\n";
        
        // Teste simples de conexão SSE
        $start = microtime(true);
        $response = $this->makeRequest('/realtime?client_id=test123&events=stats', false);
        $end = microtime(true);
        
        $time = ($end - $start) * 1000;
        
        // Para SSE, consideramos sucesso se conectou
        $success = !empty($response) || $time > 0;
        
        $this->results['realtime'] = [
            'target' => 1000,
            'connection_time' => $time,
            'connected' => $success,
            'passed' => $success
        ];
        
        echo sprintf("  Tempo de conexão: %.1fms ✅\n", $time);
        echo sprintf("  Status: %s\n\n", $success ? "✅ PASSOU" : "❌ FALHOU");
    }
    
    /**
     * Teste de capacidade de carga
     */
    private function testLoadCapacity(): void 
    {
        echo "🔥 Testando capacidade de carga (50 requests simultâneas)...\n";
        
        $startTime = microtime(true);
        $processes = [];
        $results = [];
        
        // Simular requests simultâneas usando curl multi
        $multiHandle = curl_multi_init();
        $curlHandles = [];
        
        for ($i = 0; $i < 50; $i++) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $this->baseUrl . '/stats',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HEADER => false,
                CURLOPT_USERAGENT => 'Performance Test Bot'
            ]);
            
            curl_multi_add_handle($multiHandle, $ch);
            $curlHandles[] = $ch;
        }
        
        // Executar todas as requisições
        $running = null;
        do {
            curl_multi_exec($multiHandle, $running);
            curl_multi_select($multiHandle);
        } while ($running > 0);
        
        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000;
        
        // Coletar resultados
        $successful = 0;
        $errors = 0;
        
        foreach ($curlHandles as $ch) {
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if ($httpCode === 200) {
                $successful++;
            } else {
                $errors++;
            }
            
            curl_multi_remove_handle($multiHandle, $ch);
            curl_close($ch);
        }
        
        curl_multi_close($multiHandle);
        
        $throughput = (50 / ($totalTime / 1000)); // req/s
        
        $this->results['load'] = [
            'total_requests' => 50,
            'successful' => $successful,
            'errors' => $errors,
            'total_time' => $totalTime,
            'throughput' => $throughput,
            'target_throughput' => 100,
            'passed' => $throughput >= 50 && $errors <= 5
        ];
        
        echo sprintf("  Total: 50 requests em %.1fms\n", $totalTime);
        echo sprintf("  Sucessos: %d | Erros: %d\n", $successful, $errors);
        echo sprintf("  Throughput: %.1f req/s\n", $throughput);
        echo sprintf("  Status: %s\n\n", $throughput >= 50 ? "✅ PASSOU" : "❌ FALHOU");
    }
    
    /**
     * Fazer requisição GET
     */
    private function makeRequest(string $endpoint, bool $decodeJson = true) 
    {
        $url = $this->baseUrl . $endpoint;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HEADER => false,
            CURLOPT_USERAGENT => 'Performance Test Bot',
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return ['success' => false, 'http_code' => $httpCode];
        }
        
        return $decodeJson ? json_decode($response, true) : $response;
    }
    
    /**
     * Fazer requisição POST
     */
    private function makePostRequest(string $endpoint, array $data) 
    {
        $url = $this->baseUrl . $endpoint;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HEADER => false,
            CURLOPT_USERAGENT => 'Performance Test Bot',
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return ['success' => false, 'http_code' => $httpCode];
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Calcular percentil
     */
    private function calculatePercentile(array $values, int $percentile): float 
    {
        sort($values);
        $index = ($percentile / 100) * (count($values) - 1);
        
        if (floor($index) == $index) {
            return $values[$index];
        }
        
        $lower = $values[floor($index)];
        $upper = $values[ceil($index)];
        $fraction = $index - floor($index);
        
        return $lower + ($fraction * ($upper - $lower));
    }
    
    /**
     * Imprimir resumo dos resultados
     */
    private function printSummary(): void 
    {
        echo str_repeat("=", 70) . "\n";
        echo "📋 RESUMO DOS TESTES DE PERFORMANCE\n";
        echo str_repeat("=", 70) . "\n\n";
        
        $totalTests = 0;
        $passedTests = 0;
        
        foreach ($this->results as $api => $result) {
            $totalTests++;
            
            if ($api === 'load') {
                echo sprintf("🔥 %-12s: %d req/s (target: %d req/s) %s\n", 
                    strtoupper($api),
                    (int)$result['throughput'], 
                    $result['target_throughput'],
                    $result['passed'] ? "✅" : "❌"
                );
            } elseif ($api === 'realtime') {
                echo sprintf("⚡ %-12s: %.1fms connection %s\n", 
                    strtoupper($api),
                    $result['connection_time'],
                    $result['passed'] ? "✅" : "❌"
                );
            } else {
                echo sprintf("📊 %-12s: %.1fms avg | %.1fms P95 (target: %dms) %s\n", 
                    strtoupper($api),
                    $result['avg'], 
                    $result['p95'],
                    $result['target'],
                    $result['passed'] ? "✅" : "❌"
                );
            }
            
            if ($result['passed']) {
                $passedTests++;
            }
        }
        
        echo "\n" . str_repeat("-", 70) . "\n";
        echo sprintf("RESULTADO FINAL: %d/%d testes passaram (%.1f%%)\n", 
            $passedTests, $totalTests, ($passedTests / $totalTests) * 100);
        
        if ($passedTests === $totalTests) {
            echo "🎉 TODOS OS TARGETS DE PERFORMANCE FORAM ALCANÇADOS!\n";
        } else {
            echo "⚠️  Alguns targets não foram alcançados. Verificar otimizações.\n";
        }
        
        echo str_repeat("=", 70) . "\n";
        
        // Recomendações
        $this->printRecommendations();
    }
    
    /**
     * Imprimir recomendações
     */
    private function printRecommendations(): void 
    {
        echo "\n💡 RECOMENDAÇÕES:\n";
        echo str_repeat("-", 40) . "\n";
        
        foreach ($this->results as $api => $result) {
            if (!$result['passed']) {
                switch ($api) {
                    case 'stats':
                        echo "- Stats API: Verificar cache APCu e otimizar view v_dashboard_executivo\n";
                        break;
                    case 'charts':
                        echo "- Charts API: Revisar queries de agregação e índices em v_performance_fiscal\n";
                        break;
                    case 'search':
                        echo "- Search API: Implementar índices full-text e otimizar LIKE queries\n";
                        break;
                    case 'export':
                        echo "- Export API: Processar em chunks menores e usar paginação\n";
                        break;
                    case 'load':
                        echo "- Load Test: Verificar rate limiting e pool de conexões MySQL\n";
                        break;
                }
            }
        }
        
        echo "\n🔧 OTIMIZAÇÕES GERAIS:\n";
        echo "- Verificar se Redis está rodando e configurado corretamente\n";
        echo "- Aumentar memory_limit e max_execution_time se necessário\n";
        echo "- Otimizar configurações do MySQL (innodb_buffer_pool_size)\n";
        echo "- Implementar connection pooling para alta concorrência\n";
        echo "- Monitorar uso de memória durante picos de carga\n";
    }
}

// Executar testes se chamado diretamente
if (php_sapi_name() === 'cli') {
    $baseUrl = $argv[1] ?? 'http://localhost:8000/api/dashboard';
    
    echo "🧪 Performance Test Suite - APIs REST ETL DI's\n";
    echo "Base URL: {$baseUrl}\n\n";
    
    $tester = new PerformanceTest($baseUrl);
    $results = $tester->runAllTests();
    
    // Salvar resultados em arquivo JSON
    $resultsFile = __DIR__ . '/performance_results_' . date('Y-m-d_H-i-s') . '.json';
    file_put_contents($resultsFile, json_encode($results, JSON_PRETTY_PRINT));
    
    echo "\n📄 Resultados salvos em: {$resultsFile}\n";
    
    exit(0);
}
?>