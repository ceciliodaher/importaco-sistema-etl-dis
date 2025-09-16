<?php
/**
 * ================================================================================
 * TESTES UNITÁRIOS - API STATS
 * Validação da API de estatísticas do dashboard (target: < 500ms)
 * ================================================================================
 */

require_once __DIR__ . '/../../bootstrap.php';

use PHPUnit\Framework\TestCase;

class StatsApiTest extends TestCase
{
    private ApiTestClient $client;
    private static $testData = [];
    
    public static function setUpBeforeClass(): void
    {
        // Preparar dados de teste
        TestBootstrap::cleanTestData();
        TestBootstrap::seedTestData();
        
        // Inserir dados adicionais para testes de stats
        $db = TestBootstrap::getDbConnection();
        
        self::$testData = [
            [
                'numero_di' => 'TEST001',
                'data_registro' => '2024-01-01',
                'importador_nome' => 'Empresa A',
                'importador_cnpj' => '11.111.111/0001-11',
                'valor_total_usd' => 10000,
                'valor_total_brl' => 50000,
                'status' => 'concluida'
            ],
            [
                'numero_di' => 'TEST002',
                'data_registro' => '2024-02-01',
                'importador_nome' => 'Empresa B',
                'importador_cnpj' => '22.222.222/0001-22',
                'valor_total_usd' => 20000,
                'valor_total_brl' => 100000,
                'status' => 'concluida'
            ]
        ];
        
        foreach (self::$testData as $di) {
            $stmt = $db->prepare("
                INSERT INTO declaracoes_importacao 
                (numero_di, data_registro, importador_nome, importador_cnpj, valor_total_usd, valor_total_brl, status)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $di['numero_di'], $di['data_registro'], $di['importador_nome'],
                $di['importador_cnpj'], $di['valor_total_usd'], $di['valor_total_brl'], $di['status']
            ]);
        }
    }
    
    protected function setUp(): void
    {
        $this->client = new ApiTestClient('http://localhost:8000/api/dashboard');
    }
    
    public function testStatsApiStructure(): void
    {
        $response = $this->client->get('/stats');
        
        // Verificar se response é bem-sucedida
        $this->assertTrue($this->client->wasSuccessful(), 'API Stats deve retornar sucesso');
        
        // Verificar estrutura básica da resposta
        TestHelper::assertApiResponseStructure($response, ['data']);
        
        // Verificar estrutura dos dados
        $this->assertArrayHasKey('data', $response);
        $data = $response['data'];
        
        $expectedFields = [
            'total_dis', 'total_adicoes', 'valor_total_usd', 'valor_total_brl',
            'total_impostos', 'ticket_medio_usd', 'periodo', 'ultima_atualizacao'
        ];
        
        foreach ($expectedFields as $field) {
            $this->assertArrayHasKey($field, $data, "Campo '{$field}' deve estar presente nos dados de stats");
        }
    }
    
    public function testStatsApiDataTypes(): void
    {
        $response = $this->client->get('/stats');
        $data = $response['data'];
        
        // Verificar tipos de dados
        $this->assertIsInt($data['total_dis'], 'total_dis deve ser inteiro');
        $this->assertIsInt($data['total_adicoes'], 'total_adicoes deve ser inteiro');
        $this->assertIsNumeric($data['valor_total_usd'], 'valor_total_usd deve ser numérico');
        $this->assertIsNumeric($data['valor_total_brl'], 'valor_total_brl deve ser numérico');
        $this->assertIsNumeric($data['total_impostos'], 'total_impostos deve ser numérico');
        
        // Verificar valores positivos
        $this->assertGreaterThanOrEqual(0, $data['total_dis']);
        $this->assertGreaterThanOrEqual(0, $data['valor_total_usd']);
        $this->assertGreaterThanOrEqual(0, $data['valor_total_brl']);
    }
    
    public function testStatsApiPerformance(): void
    {
        // Múltiplas requisições para testar performance
        $times = [];
        $errorCount = 0;
        
        for ($i = 0; $i < 5; $i++) {
            $startTime = microtime(true);
            $response = $this->client->get('/stats');
            $endTime = microtime(true);
            
            $executionTime = ($endTime - $startTime) * 1000;
            $times[] = $executionTime;
            
            if (!$this->client->wasSuccessful()) {
                $errorCount++;
            }
            
            // Pequena pausa entre requisições
            usleep(100000); // 100ms
        }
        
        $avgTime = array_sum($times) / count($times);
        $maxTime = max($times);
        
        // Verificar performance (target: < 500ms)
        $this->assertLessThan(500, $avgTime, "Tempo médio da API Stats ({$avgTime}ms) deve ser < 500ms");
        $this->assertLessThan(1000, $maxTime, "Tempo máximo da API Stats ({$maxTime}ms) deve ser < 1000ms");
        $this->assertEquals(0, $errorCount, 'Não deve haver erros nas requisições de stats');
    }
    
    public function testStatsApiCaching(): void
    {
        // Primeira requisição (provavelmente cache miss)
        $startTime1 = microtime(true);
        $response1 = $this->client->get('/stats');
        $time1 = (microtime(true) - $startTime1) * 1000;
        
        // Segunda requisição (deve ser cache hit)
        $startTime2 = microtime(true);
        $response2 = $this->client->get('/stats');
        $time2 = (microtime(true) - $startTime2) * 1000;
        
        // Verificar que ambas foram bem-sucedidas
        $this->assertTrue($this->client->wasSuccessful());
        
        // Verificar que os dados são consistentes
        $this->assertEquals($response1['data']['total_dis'], $response2['data']['total_dis']);
        $this->assertEquals($response1['data']['valor_total_usd'], $response2['data']['valor_total_usd']);
        
        // Segunda requisição deve ser mais rápida (cache hit)
        // Tolerância de 10ms para variações normais
        if ($time1 > 100) { // Só testar se primeira req foi significativa
            $this->assertLessThan($time1 + 10, $time2 + 50, 'Segunda requisição deve ser mais rápida (cache hit)');
        }
    }
    
    public function testStatsApiWithFilters(): void
    {
        // Testar filtros por período
        $filters = [
            'periodo' => '30d',
            'data_inicio' => '2024-01-01',
            'data_fim' => '2024-12-31'
        ];
        
        $response = $this->client->get('/stats', $filters);
        
        $this->assertTrue($this->client->wasSuccessful());
        $this->assertArrayHasKey('data', $response);
        
        // Verificar que filtros foram aplicados
        $data = $response['data'];
        $this->assertArrayHasKey('periodo', $data);
        $this->assertEquals('30d', $data['periodo']);
    }
    
    public function testStatsApiErrorHandling(): void
    {
        // Testar com parâmetros inválidos
        $invalidFilters = [
            'periodo' => 'invalid_period',
            'data_inicio' => 'invalid_date',
            'limit' => -1
        ];
        
        $response = $this->client->get('/stats', $invalidFilters);
        
        // API deve tratar erros graciosamente
        if (!$this->client->wasSuccessful()) {
            $this->assertArrayHasKey('error', $response);
            $this->assertArrayHasKey('message', $response['error']);
        } else {
            // Se não retornou erro, deve ter dados válidos
            $this->assertArrayHasKey('data', $response);
        }
    }
    
    public function testStatsApiDataConsistency(): void
    {
        $response = $this->client->get('/stats');
        $data = $response['data'];
        
        // Verificar consistência dos dados
        if ($data['total_dis'] > 0) {
            $this->assertGreaterThan(0, $data['valor_total_usd'], 'Se há DIs, deve haver valor em USD');
            $this->assertGreaterThan(0, $data['valor_total_brl'], 'Se há DIs, deve haver valor em BRL');
        }
        
        // Verificar relação USD/BRL (taxa de câmbio)
        if ($data['valor_total_usd'] > 0 && $data['valor_total_brl'] > 0) {
            $taxa = $data['valor_total_brl'] / $data['valor_total_usd'];
            $this->assertGreaterThan(3, $taxa, 'Taxa USD/BRL deve ser > 3');
            $this->assertLessThan(8, $taxa, 'Taxa USD/BRL deve ser < 8');
        }
        
        // Verificar ticket médio
        if ($data['total_dis'] > 0 && $data['valor_total_usd'] > 0) {
            $ticketCalculado = $data['valor_total_usd'] / $data['total_dis'];
            $ticketRetornado = $data['ticket_medio_usd'] ?? 0;
            
            $this->assertEquals(
                $ticketCalculado,
                $ticketRetornado,
                'Ticket médio deve ser consistente',
                0.01 // Tolerância para arredondamentos
            );
        }
    }
    
    public function testStatsApiSQLInjectionProtection(): void
    {
        // Tentar injeções SQL comuns
        $maliciousInputs = [
            "'; DROP TABLE declaracoes_importacao; --",
            "' OR '1'='1",
            "1' UNION SELECT * FROM declaracoes_importacao --",
            "<script>alert('xss')</script>",
            "../../etc/passwd"
        ];
        
        foreach ($maliciousInputs as $input) {
            $response = $this->client->get('/stats', ['periodo' => $input]);
            
            // API deve rejeitar ou sanitizar input malicioso
            if ($this->client->wasSuccessful()) {
                // Se aceitou, deve ter dados válidos (input foi sanitizado)
                $this->assertArrayHasKey('data', $response);
                $data = $response['data'];
                $this->assertIsInt($data['total_dis']);
            } else {
                // Se rejeitou, deve ter mensagem de erro apropriada
                $this->assertArrayHasKey('error', $response);
            }
        }
    }
    
    public function testStatsApiConcurrentRequests(): void
    {
        // Simular requisições concorrentes
        $results = ApiPerformanceTester::testMultipleRequests($this->client, '/stats', 10);
        
        // Verificar taxa de sucesso
        $this->assertGreaterThanOrEqual(90, $results['success_rate'], 'Taxa de sucesso deve ser >= 90%');
        
        // Verificar performance sob carga
        $this->assertLessThan(1000, $results['times']['avg'], 'Tempo médio sob carga deve ser < 1000ms');
        $this->assertLessThan(2000, $results['times']['max'], 'Tempo máximo sob carga deve ser < 2000ms');
    }
    
    public static function tearDownAfterClass(): void
    {
        // Limpar dados de teste
        TestBootstrap::cleanTestData();
    }
}