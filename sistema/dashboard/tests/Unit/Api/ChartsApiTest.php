<?php
/**
 * ================================================================================
 * TESTES UNITÁRIOS - API CHARTS
 * Validação da API de gráficos Chart.js (target: < 1s)
 * ================================================================================
 */

require_once __DIR__ . '/../../bootstrap.php';

use PHPUnit\Framework\TestCase;

class ChartsApiTest extends TestCase
{
    private ApiTestClient $client;
    private static $chartTypes = ['evolution', 'taxes', 'expenses', 'currencies', 'top_importers'];
    
    public static function setUpBeforeClass(): void
    {
        // Preparar dados de teste com variedade para gráficos
        TestBootstrap::cleanTestData();
        TestBootstrap::seedTestData();
        
        $db = TestBootstrap::getDbConnection();
        
        // Inserir dados com datas variadas para gráficos de evolução
        $testData = [
            ['2024-01-15', 'CHART001', 15000, 75000],
            ['2024-02-15', 'CHART002', 25000, 125000],
            ['2024-03-15', 'CHART003', 35000, 175000],
            ['2024-04-15', 'CHART004', 20000, 100000],
            ['2024-05-15', 'CHART005', 30000, 150000]
        ];
        
        foreach ($testData as $index => $data) {
            $stmt = $db->prepare("
                INSERT INTO declaracoes_importacao 
                (numero_di, data_registro, importador_nome, importador_cnpj, valor_total_usd, valor_total_brl, status)
                VALUES (?, ?, ?, ?, ?, ?, 'concluida')
            ");
            
            $stmt->execute([
                $data[1],
                $data[0],
                "Importadora Chart Test " . ($index + 1),
                "12.345.67" . $index . "/0001-99",
                $data[2],
                $data[3]
            ]);
        }
    }
    
    protected function setUp(): void
    {
        $this->client = new ApiTestClient('http://localhost:8000/api/dashboard');
    }
    
    /**
     * @dataProvider chartTypeProvider
     */
    public function testChartsApiStructure(string $chartType): void
    {
        $response = $this->client->get('/charts', [
            'type' => $chartType,
            'period' => '6months'
        ]);
        
        $this->assertTrue($this->client->wasSuccessful(), "API Charts deve retornar sucesso para {$chartType}");
        
        // Verificar estrutura básica
        TestHelper::assertApiResponseStructure($response, ['data']);
        
        $data = $response['data'];
        
        // Verificar estrutura específica do gráfico
        $this->assertArrayHasKey('chart_data', $data);
        $this->assertArrayHasKey('chart_config', $data);
        $this->assertArrayHasKey('type', $data);
        
        $this->assertEquals($chartType, $data['type']);
        
        // Validar estrutura Chart.js
        ApiValidator::validateChartData($data['chart_data']);
    }
    
    public function chartTypeProvider(): array
    {
        return array_map(function($type) {
            return [$type];
        }, self::$chartTypes);
    }
    
    public function testChartsApiEvolutionData(): void
    {
        $response = $this->client->get('/charts', [
            'type' => 'evolution',
            'period' => '6months'
        ]);
        
        $this->assertTrue($this->client->wasSuccessful());
        
        $chartData = $response['data']['chart_data'];
        
        // Verificar labels (devem ser datas)
        $this->assertNotEmpty($chartData['labels']);
        foreach ($chartData['labels'] as $label) {
            $this->assertIsString($label);
        }
        
        // Verificar datasets (valor USD e BRL)
        $this->assertGreaterThanOrEqual(1, count($chartData['datasets']));
        
        foreach ($chartData['datasets'] as $dataset) {
            $this->assertArrayHasKey('label', $dataset);
            $this->assertArrayHasKey('data', $dataset);
            $this->assertArrayHasKey('backgroundColor', $dataset);
            
            // Verificar que dados são numéricos
            foreach ($dataset['data'] as $value) {
                $this->assertIsNumeric($value, 'Valores do gráfico devem ser numéricos');
            }
        }
    }
    
    public function testChartsApiTaxesBreakdown(): void
    {
        $response = $this->client->get('/charts', [
            'type' => 'taxes',
            'period' => '6months'
        ]);
        
        $this->assertTrue($this->client->wasSuccessful());
        
        $chartData = $response['data']['chart_data'];
        
        // Para gráfico de impostos, esperamos labels de tipos de impostos
        $expectedTaxes = ['II', 'IPI', 'PIS', 'COFINS', 'ICMS'];
        
        foreach ($chartData['labels'] as $label) {
            $this->assertContains($label, $expectedTaxes, "Label '{$label}' deve ser um tipo de imposto válido");
        }
        
        // Verificar que há apenas um dataset para breakdown
        $this->assertCount(1, $chartData['datasets']);
        
        $dataset = $chartData['datasets'][0];
        $this->assertArrayHasKey('backgroundColor', $dataset);
        $this->assertIsArray($dataset['backgroundColor']);
    }
    
    public function testChartsApiPerformance(): void
    {
        $performanceResults = [];
        
        foreach (self::$chartTypes as $chartType) {
            $times = [];
            
            for ($i = 0; $i < 3; $i++) {
                $startTime = microtime(true);
                $response = $this->client->get('/charts', [
                    'type' => $chartType,
                    'period' => '6months'
                ]);
                $endTime = microtime(true);
                
                $executionTime = ($endTime - $startTime) * 1000;
                $times[] = $executionTime;
                
                $this->assertTrue($this->client->wasSuccessful(), "Charts API falhou para {$chartType}");
            }
            
            $avgTime = array_sum($times) / count($times);
            $performanceResults[$chartType] = $avgTime;
            
            // Target individual: < 1000ms
            $this->assertLessThan(1000, $avgTime, "Gráfico {$chartType} demorou {$avgTime}ms (target: < 1000ms)");
        }
        
        // Verificar performance geral
        $overallAvg = array_sum($performanceResults) / count($performanceResults);
        $this->assertLessThan(800, $overallAvg, "Performance média de todos gráficos: {$overallAvg}ms (target: < 800ms)");
    }
    
    public function testChartsApiPeriodFilters(): void
    {
        $periods = ['30d', '6months', '1year', 'all'];
        
        foreach ($periods as $period) {
            $response = $this->client->get('/charts', [
                'type' => 'evolution',
                'period' => $period
            ]);
            
            $this->assertTrue($this->client->wasSuccessful(), "Period filter '{$period}' deve funcionar");
            
            $data = $response['data'];
            $this->assertArrayHasKey('period', $data);
            $this->assertEquals($period, $data['period']);
            
            // Verificar que há dados para o período
            $chartData = $data['chart_data'];
            if ($period !== 'all') {
                $this->assertNotEmpty($chartData['labels'], "Deve haver dados para período {$period}");
            }
        }
    }
    
    public function testChartsApiCustomDateRange(): void
    {
        $response = $this->client->get('/charts', [
            'type' => 'evolution',
            'date_start' => '2024-01-01',
            'date_end' => '2024-06-30'
        ]);
        
        $this->assertTrue($this->client->wasSuccessful());
        
        $data = $response['data'];
        $this->assertArrayHasKey('date_range', $data);
        $this->assertEquals('2024-01-01', $data['date_range']['start']);
        $this->assertEquals('2024-06-30', $data['date_range']['end']);
    }
    
    public function testChartsApiEmptyDataHandling(): void
    {
        // Criar período sem dados
        $response = $this->client->get('/charts', [
            'type' => 'evolution',
            'date_start' => '2030-01-01',
            'date_end' => '2030-12-31'
        ]);
        
        $this->assertTrue($this->client->wasSuccessful(), 'API deve lidar graciosamente com dados vazios');
        
        $chartData = $response['data']['chart_data'];
        
        // Deve retornar estrutura válida mesmo sem dados
        $this->assertArrayHasKey('labels', $chartData);
        $this->assertArrayHasKey('datasets', $chartData);
        
        // Labels podem estar vazios ou ter placeholders
        $this->assertIsArray($chartData['labels']);
        $this->assertIsArray($chartData['datasets']);
    }
    
    public function testChartsApiConfigGeneration(): void
    {
        $response = $this->client->get('/charts', [
            'type' => 'evolution',
            'period' => '6months'
        ]);
        
        $config = $response['data']['chart_config'];
        
        // Verificar configurações essenciais do Chart.js
        $this->assertArrayHasKey('type', $config);
        $this->assertArrayHasKey('options', $config);
        
        $options = $config['options'];
        $this->assertArrayHasKey('responsive', $options);
        $this->assertArrayHasKey('scales', $options);
        $this->assertArrayHasKey('plugins', $options);
        
        // Verificar configurações de responsividade
        $this->assertTrue($options['responsive']);
        
        // Verificar escalas
        if (isset($options['scales']['y'])) {
            $this->assertArrayHasKey('beginAtZero', $options['scales']['y']);
        }
    }
    
    public function testChartsApiColorConsistency(): void
    {
        $response = $this->client->get('/charts', [
            'type' => 'evolution',
            'period' => '6months'
        ]);
        
        $chartData = $response['data']['chart_data'];
        
        foreach ($chartData['datasets'] as $dataset) {
            if (isset($dataset['backgroundColor'])) {
                $colors = is_array($dataset['backgroundColor']) 
                    ? $dataset['backgroundColor'] 
                    : [$dataset['backgroundColor']];
                
                foreach ($colors as $color) {
                    // Verificar formato de cor válido (hex, rgb, rgba)
                    $this->assertMatchesRegularExpression(
                        '/^(#[0-9a-fA-F]{6}|rgb\(|rgba\(|[a-zA-Z]+)/',
                        $color,
                        "Cor '{$color}' deve estar em formato válido"
                    );
                }
            }
        }
    }
    
    public function testChartsApiDataAggregation(): void
    {
        // Testar diferentes níveis de agregação
        $aggregations = ['daily', 'weekly', 'monthly'];
        
        foreach ($aggregations as $aggregation) {
            $response = $this->client->get('/charts', [
                'type' => 'evolution',
                'period' => '6months',
                'aggregation' => $aggregation
            ]);
            
            if ($this->client->wasSuccessful()) {
                $data = $response['data'];
                $chartData = $data['chart_data'];
                
                // Verificar que agregação foi aplicada
                if (isset($data['aggregation'])) {
                    $this->assertEquals($aggregation, $data['aggregation']);
                }
                
                // Para agregação mensal, deve haver menos pontos que diária
                if ($aggregation === 'monthly') {
                    $this->assertLessThanOrEqual(12, count($chartData['labels']), 
                        'Agregação mensal deve ter no máximo 12 pontos para 6 meses');
                }
            }
        }
    }
    
    public function testChartsApiErrorHandling(): void
    {
        // Tipo de gráfico inválido
        $response = $this->client->get('/charts', [
            'type' => 'invalid_chart_type',
            'period' => '6months'
        ]);
        
        if (!$this->client->wasSuccessful()) {
            $this->assertArrayHasKey('error', $response);
            $this->assertArrayHasKey('message', $response['error']);
        }
        
        // Período inválido
        $response = $this->client->get('/charts', [
            'type' => 'evolution',
            'period' => 'invalid_period'
        ]);
        
        if (!$this->client->wasSuccessful()) {
            $this->assertArrayHasKey('error', $response);
        }
        
        // Datas inválidas
        $response = $this->client->get('/charts', [
            'type' => 'evolution',
            'date_start' => 'invalid-date',
            'date_end' => '2024-13-45'
        ]);
        
        if (!$this->client->wasSuccessful()) {
            $this->assertArrayHasKey('error', $response);
        }
    }
    
    public function testChartsApiCacheEfficiency(): void
    {
        $chartType = 'evolution';
        $params = ['type' => $chartType, 'period' => '6months'];
        
        // Primeira requisição
        $startTime1 = microtime(true);
        $response1 = $this->client->get('/charts', $params);
        $time1 = (microtime(true) - $startTime1) * 1000;
        
        // Segunda requisição (cache hit esperado)
        $startTime2 = microtime(true);
        $response2 = $this->client->get('/charts', $params);
        $time2 = (microtime(true) - $startTime2) * 1000;
        
        // Verificar consistência dos dados
        $this->assertEquals(
            $response1['data']['chart_data']['labels'],
            $response2['data']['chart_data']['labels'],
            'Dados do cache devem ser consistentes'
        );
        
        // Segunda requisição deve ser mais rápida (considerando cache)
        if ($time1 > 200) { // Só comparar se primeira req foi significativa
            $improvement = (($time1 - $time2) / $time1) * 100;
            $this->assertGreaterThan(0, $improvement, 'Cache deve melhorar performance');
        }
    }
    
    public static function tearDownAfterClass(): void
    {
        TestBootstrap::cleanTestData();
    }
}