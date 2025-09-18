<?php
/**
 * ================================================================================
 * PERFORMANCE BENCHMARK SCRIPT
 * Testa tempo de resposta de todas as APIs do dashboard
 * ================================================================================
 */

// Performance benchmark - não requer dependências

class PerformanceBenchmark {
    private $baseUrl;
    private $results = [];
    
    public function __construct($baseUrl = '') {
        $this->baseUrl = $baseUrl ?: 'http://localhost:8000/sistema/dashboard/api/dashboard';
    }
    
    public function runAllBenchmarks() {
        echo "🚀 Iniciando benchmarks de performance das APIs...\n\n";
        
        $apis = [
            'database-status.php' => 'Database Status',
            'stats.php' => 'System Stats',
            'charts.php?type=all' => 'Charts Data',
            'system-status.php' => 'System Status',
            'pre-check.php' => 'Pre-check',
            'clear-cache.php' => 'Clear Cache'
        ];
        
        foreach ($apis as $endpoint => $name) {
            $this->benchmarkAPI($endpoint, $name);
        }
        
        $this->generateReport();
    }
    
    private function benchmarkAPI($endpoint, $name, $iterations = 5) {
        echo "📊 Testando: {$name} ({$endpoint})\n";
        
        $times = [];
        $errors = 0;
        
        for ($i = 0; $i < $iterations; $i++) {
            $startTime = microtime(true);
            
            try {
                $context = stream_context_create([
                    'http' => [
                        'method' => 'GET',
                        'timeout' => 10,
                        'header' => [
                            'User-Agent: Performance-Benchmark/1.0',
                            'Cache-Control: no-cache'
                        ]
                    ]
                ]);
                
                $url = $this->baseUrl . '/' . $endpoint;
                $response = file_get_contents($url, false, $context);
                
                if ($response === false) {
                    $errors++;
                    continue;
                }
                
                $data = json_decode($response, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $errors++;
                    continue;
                }
                
            } catch (Exception $e) {
                $errors++;
                continue;
            }
            
            $endTime = microtime(true);
            $times[] = ($endTime - $startTime) * 1000; // Convert to milliseconds
        }
        
        if (empty($times)) {
            echo "   ❌ Falha total - API não respondeu\n\n";
            $this->results[$endpoint] = [
                'name' => $name,
                'status' => 'FAILED',
                'avg_time' => 0,
                'min_time' => 0,
                'max_time' => 0,
                'errors' => $errors
            ];
            return;
        }
        
        $avgTime = array_sum($times) / count($times);
        $minTime = min($times);
        $maxTime = max($times);
        
        $status = $this->getPerformanceStatus($avgTime);
        
        echo "   ⏱️  Média: " . number_format($avgTime, 2) . "ms\n";
        echo "   🔽 Min: " . number_format($minTime, 2) . "ms\n";
        echo "   🔼 Max: " . number_format($maxTime, 2) . "ms\n";
        echo "   📈 Status: {$status}\n";
        
        if ($errors > 0) {
            echo "   ⚠️  Erros: {$errors}/{$iterations}\n";
        }
        
        echo "\n";
        
        $this->results[$endpoint] = [
            'name' => $name,
            'status' => $status,
            'avg_time' => $avgTime,
            'min_time' => $minTime,
            'max_time' => $maxTime,
            'errors' => $errors,
            'success_rate' => (($iterations - $errors) / $iterations) * 100
        ];
    }
    
    private function getPerformanceStatus($avgTime) {
        if ($avgTime < 200) return '🟢 EXCELENTE';
        if ($avgTime < 500) return '🟡 BOM';
        if ($avgTime < 1000) return '🟠 ACEITÁVEL';
        return '🔴 LENTO';
    }
    
    private function generateReport() {
        echo "📋 RELATÓRIO FINAL DE PERFORMANCE\n";
        echo str_repeat("=", 60) . "\n\n";
        
        $totalAvg = 0;
        $totalCount = 0;
        $excellent = 0;
        $good = 0;
        $acceptable = 0;
        $slow = 0;
        
        foreach ($this->results as $endpoint => $result) {
            if ($result['status'] !== 'FAILED') {
                $totalAvg += $result['avg_time'];
                $totalCount++;
                
                switch (substr($result['status'], 0, 2)) {
                    case '🟢': $excellent++; break;
                    case '🟡': $good++; break;
                    case '🟠': $acceptable++; break;
                    case '🔴': $slow++; break;
                }
            }
            
            echo sprintf(
                "%-30s | %s | %6.2fms | %5.1f%%\n",
                $result['name'],
                $result['status'],
                $result['avg_time'],
                $result['success_rate']
            );
        }
        
        echo "\n" . str_repeat("-", 60) . "\n";
        
        if ($totalCount > 0) {
            $overallAvg = $totalAvg / $totalCount;
            echo "⚡ Tempo médio geral: " . number_format($overallAvg, 2) . "ms\n";
            echo "📊 Distribuição de performance:\n";
            echo "   🟢 Excelente (<200ms): {$excellent} APIs\n";
            echo "   🟡 Bom (200-500ms): {$good} APIs\n";
            echo "   🟠 Aceitável (500-1000ms): {$acceptable} APIs\n";
            echo "   🔴 Lento (>1000ms): {$slow} APIs\n";
        }
        
        echo "\n";
        
        // Recomendações
        echo "💡 RECOMENDAÇÕES:\n";
        foreach ($this->results as $endpoint => $result) {
            if ($result['avg_time'] > 500) {
                echo "   • {$result['name']}: Otimizar - tempo médio {$result['avg_time']}ms\n";
            }
            if ($result['errors'] > 0) {
                echo "   • {$result['name']}: Investigar erros - {$result['errors']} falhas\n";
            }
        }
        
        // Salvar resultados em JSON
        $this->saveResults();
    }
    
    private function saveResults() {
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'results' => $this->results,
            'summary' => [
                'total_apis' => count($this->results),
                'avg_response_time' => array_sum(array_column($this->results, 'avg_time')) / count($this->results),
                'recommendations' => $this->generateRecommendations()
            ]
        ];
        
        file_put_contents(
            '../tests/performance_report_' . date('Y-m-d_H-i-s') . '.json',
            json_encode($report, JSON_PRETTY_PRINT)
        );
        
        echo "💾 Relatório salvo em tests/performance_report_" . date('Y-m-d_H-i-s') . ".json\n";
    }
    
    private function generateRecommendations() {
        $recommendations = [];
        
        foreach ($this->results as $endpoint => $result) {
            if ($result['avg_time'] > 1000) {
                $recommendations[] = "Otimização crítica necessária para {$result['name']}";
            } elseif ($result['avg_time'] > 500) {
                $recommendations[] = "Otimização recomendada para {$result['name']}";
            }
            
            if ($result['errors'] > 0) {
                $recommendations[] = "Investigar erros em {$result['name']}";
            }
        }
        
        return $recommendations;
    }
}

// Executar se chamado diretamente
if (php_sapi_name() === 'cli') {
    $benchmark = new PerformanceBenchmark();
    $benchmark->runAllBenchmarks();
}