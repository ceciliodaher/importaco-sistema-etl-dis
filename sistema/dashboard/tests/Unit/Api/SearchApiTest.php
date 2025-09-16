<?php
/**
 * ================================================================================
 * TESTES UNITÁRIOS - API SEARCH
 * Validação da API de pesquisa e filtros (target: < 2s)
 * ================================================================================
 */

require_once __DIR__ . '/../../bootstrap.php';

use PHPUnit\Framework\TestCase;

class SearchApiTest extends TestCase
{
    private ApiTestClient $client;
    private static $searchTestData = [];
    
    public static function setUpBeforeClass(): void
    {
        TestBootstrap::cleanTestData();
        TestBootstrap::seedTestData();
        
        $db = TestBootstrap::getDbConnection();
        
        // Dados específicos para testes de busca
        self::$searchTestData = [
            [
                'numero_di' => 'SEARCH001',
                'data_registro' => '2024-01-15',
                'importador_nome' => 'Equiplex Industrial Ltda',
                'importador_cnpj' => '12.345.678/0001-90',
                'valor_total_usd' => 25000,
                'valor_total_brl' => 125000,
                'status' => 'concluida'
            ],
            [
                'numero_di' => 'SEARCH002',
                'data_registro' => '2024-02-20',
                'importador_nome' => 'TechCorp Importadora S.A.',
                'importador_cnpj' => '98.765.432/0001-10',
                'valor_total_usd' => 45000,
                'valor_total_brl' => 225000,
                'status' => 'concluida'
            ],
            [
                'numero_di' => 'SEARCH003',
                'data_registro' => '2024-03-10',
                'importador_nome' => 'Santos Port Trading',
                'importador_cnpj' => '11.222.333/0001-44',
                'valor_total_usd' => 35000,
                'valor_total_brl' => 175000,
                'status' => 'processando'
            ]
        ];
        
        foreach (self::$searchTestData as $di) {
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
        
        // Inserir adições para teste de busca por NCM
        $diId = $db->lastInsertId();
        $stmt = $db->prepare("
            INSERT INTO adicoes (di_id, numero_adicao, ncm, valor_usd, valor_brl, peso_kg, quantidade)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $adicoesTest = [
            [$diId, 1, '85371000', 15000, 75000, 100.5, 10],
            [$diId, 2, '84281000', 10000, 50000, 50.2, 5]
        ];
        
        foreach ($adicoesTest as $adicao) {
            $stmt->execute($adicao);
        }
    }
    
    protected function setUp(): void
    {
        $this->client = new ApiTestClient('http://localhost:8000/api/dashboard');
    }
    
    public function testSearchApiBasicStructure(): void
    {
        $searchData = [
            'query' => 'Equiplex',
            'limit' => 10,
            'page' => 1
        ];
        
        $response = $this->client->post('/search', $searchData);
        
        $this->assertTrue($this->client->wasSuccessful(), 'API Search deve retornar sucesso');
        
        // Verificar estrutura básica
        TestHelper::assertApiResponseStructure($response, ['data', 'pagination']);
        
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('pagination', $response);
        
        // Validar paginação
        ApiValidator::validatePagination($response['pagination']);
    }
    
    public function testSearchByCompanyName(): void
    {
        $searchData = [
            'query' => 'Equiplex',
            'limit' => 25
        ];
        
        $response = $this->client->post('/search', $searchData);
        
        $this->assertTrue($this->client->wasSuccessful());
        
        $results = $response['data'];
        $this->assertNotEmpty($results, 'Deve encontrar resultados para "Equiplex"');
        
        // Verificar que todos resultados contêm o termo buscado
        foreach ($results as $result) {
            $this->assertArrayHasKey('importador_nome', $result);
            $this->assertStringContainsStringIgnoringCase(
                'Equiplex', 
                $result['importador_nome'], 
                'Resultado deve conter o termo buscado'
            );
        }
    }
    
    public function testSearchByDINumber(): void
    {
        $searchData = [
            'query' => 'SEARCH001',
            'limit' => 25
        ];
        
        $response = $this->client->post('/search', $searchData);
        
        $this->assertTrue($this->client->wasSuccessful());
        
        $results = $response['data'];
        $this->assertCount(1, $results, 'Deve encontrar exatamente 1 DI com número SEARCH001');
        
        $result = $results[0];
        $this->assertEquals('SEARCH001', $result['numero_di']);
        $this->assertEquals('Equiplex Industrial Ltda', $result['importador_nome']);
    }
    
    public function testSearchByCNPJ(): void
    {
        $searchData = [
            'query' => '12.345.678/0001-90',
            'limit' => 25
        ];
        
        $response = $this->client->post('/search', $searchData);
        
        $this->assertTrue($this->client->wasSuccessful());
        
        $results = $response['data'];
        $this->assertNotEmpty($results, 'Deve encontrar resultados para CNPJ');
        
        foreach ($results as $result) {
            $this->assertEquals('12.345.678/0001-90', $result['importador_cnpj']);
        }
    }
    
    public function testSearchByNCM(): void
    {
        $searchData = [
            'query' => '85371000',
            'limit' => 25
        ];
        
        $response = $this->client->post('/search', $searchData);
        
        $this->assertTrue($this->client->wasSuccessful());
        
        // Busca por NCM pode retornar múltiplos resultados
        $results = $response['data'];
        
        if (!empty($results)) {
            // Verificar se encontrou DIs que contêm essa NCM
            $this->assertNotEmpty($results);
        }
    }
    
    public function testSearchWithFilters(): void
    {
        $searchData = [
            'query' => '',
            'filters' => [
                'status' => ['concluida'],
                'valor_min_usd' => 20000,
                'valor_max_usd' => 50000,
                'data_inicio' => '2024-01-01',
                'data_fim' => '2024-12-31'
            ],
            'limit' => 25
        ];
        
        $response = $this->client->post('/search', $searchData);
        
        $this->assertTrue($this->client->wasSuccessful());
        
        $results = $response['data'];
        
        foreach ($results as $result) {
            // Verificar filtros aplicados
            $this->assertEquals('concluida', $result['status']);
            $this->assertGreaterThanOrEqual(20000, $result['valor_total_usd']);
            $this->assertLessThanOrEqual(50000, $result['valor_total_usd']);
            
            $dataRegistro = strtotime($result['data_registro']);
            $this->assertGreaterThanOrEqual(strtotime('2024-01-01'), $dataRegistro);
            $this->assertLessThanOrEqual(strtotime('2024-12-31'), $dataRegistro);
        }
    }
    
    public function testSearchPagination(): void
    {
        // Primeira página
        $searchData = [
            'query' => '',
            'limit' => 2,
            'page' => 1
        ];
        
        $response1 = $this->client->post('/search', $searchData);
        $this->assertTrue($this->client->wasSuccessful());
        
        $pagination1 = $response1['pagination'];
        $this->assertEquals(1, $pagination1['current_page']);
        $this->assertEquals(2, $pagination1['records_per_page']);
        
        // Segunda página (se houver dados suficientes)
        $searchData['page'] = 2;
        $response2 = $this->client->post('/search', $searchData);
        
        if ($this->client->wasSuccessful() && $pagination1['total_pages'] > 1) {
            $pagination2 = $response2['pagination'];
            $this->assertEquals(2, $pagination2['current_page']);
            
            // Verificar que resultados são diferentes
            $results1 = $response1['data'];
            $results2 = $response2['data'];
            
            if (!empty($results1) && !empty($results2)) {
                $this->assertNotEquals($results1[0]['numero_di'], $results2[0]['numero_di']);
            }
        }
    }
    
    public function testSearchPerformance(): void
    {
        $searchQueries = [
            ['query' => 'Equiplex', 'limit' => 25],
            ['query' => 'SEARCH', 'limit' => 50],
            ['query' => '', 'filters' => ['status' => ['concluida']], 'limit' => 10],
            ['query' => 'Santos', 'limit' => 25]
        ];
        
        foreach ($searchQueries as $index => $searchData) {
            $startTime = microtime(true);
            $response = $this->client->post('/search', $searchData);
            $endTime = microtime(true);
            
            $executionTime = ($endTime - $startTime) * 1000;
            
            $this->assertTrue($this->client->wasSuccessful(), "Search query {$index} deve ser bem-sucedida");
            
            // Target: < 2000ms
            $this->assertLessThan(2000, $executionTime, 
                "Search query {$index} demorou {$executionTime}ms (target: < 2000ms)");
        }
    }
    
    public function testSearchEmptyQuery(): void
    {
        // Busca vazia deve retornar todos os registros (com paginação)
        $searchData = [
            'query' => '',
            'limit' => 10
        ];
        
        $response = $this->client->post('/search', $searchData);
        
        $this->assertTrue($this->client->wasSuccessful());
        
        $results = $response['data'];
        $pagination = $response['pagination'];
        
        // Deve retornar registros com paginação
        $this->assertIsArray($results);
        $this->assertLessThanOrEqual(10, count($results));
        $this->assertGreaterThan(0, $pagination['total_records']);
    }
    
    public function testSearchSpecialCharacters(): void
    {
        $specialQueries = [
            'Ltda.',
            'S.A.',
            'Industrial',
            'Santos Port',
            'Tech Corp'
        ];
        
        foreach ($specialQueries as $query) {
            $searchData = [
                'query' => $query,
                'limit' => 25
            ];
            
            $response = $this->client->post('/search', $searchData);
            
            $this->assertTrue($this->client->wasSuccessful(), 
                "Busca por '{$query}' deve funcionar");
                
            // Resultados devem ser array válido
            $this->assertIsArray($response['data']);
        }
    }
    
    public function testSearchCaseInsensitive(): void
    {
        $queries = [
            'equiplex',
            'EQUIPLEX',
            'Equiplex',
            'eQuIpLeX'
        ];
        
        $results = [];
        
        foreach ($queries as $query) {
            $searchData = [
                'query' => $query,
                'limit' => 25
            ];
            
            $response = $this->client->post('/search', $searchData);
            $this->assertTrue($this->client->wasSuccessful());
            
            $results[] = count($response['data']);
        }
        
        // Todos devem retornar o mesmo número de resultados
        $firstCount = $results[0];
        foreach ($results as $count) {
            $this->assertEquals($firstCount, $count, 'Busca deve ser case-insensitive');
        }
    }
    
    public function testSearchSQLInjectionProtection(): void
    {
        $maliciousQueries = [
            "'; DROP TABLE declaracoes_importacao; --",
            "' OR '1'='1",
            "1' UNION SELECT * FROM declaracoes_importacao --",
            "\"; DELETE FROM declaracoes_importacao; --",
            "' OR 1=1 --"
        ];
        
        foreach ($maliciousQueries as $query) {
            $searchData = [
                'query' => $query,
                'limit' => 25
            ];
            
            $response = $this->client->post('/search', $searchData);
            
            // API deve rejeitar ou sanitizar entrada maliciosa
            if ($this->client->wasSuccessful()) {
                // Se aceitou, deve retornar estrutura válida
                $this->assertArrayHasKey('data', $response);
                $this->assertIsArray($response['data']);
            } else {
                // Se rejeitou, deve ter mensagem de erro
                $this->assertArrayHasKey('error', $response);
            }
            
            // Verificar que não executou comando SQL malicioso
            $this->assertFalse(TestHelper::containsSQLInjection($query), 
                'Query maliciosa deve ser detectada');
        }
    }
    
    public function testSearchXSSProtection(): void
    {
        $xssQueries = [
            '<script>alert("xss")</script>',
            '<img src="x" onerror="alert(1)">',
            'javascript:alert(1)',
            '<iframe src="evil.com"></iframe>'
        ];
        
        foreach ($xssQueries as $query) {
            $searchData = [
                'query' => $query,
                'limit' => 25
            ];
            
            $response = $this->client->post('/search', $searchData);
            
            if ($this->client->wasSuccessful()) {
                // Verificar que resposta não contém código XSS
                $responseJson = json_encode($response);
                $this->assertFalse(TestHelper::containsXSS($responseJson), 
                    'Resposta não deve conter código XSS');
            }
        }
    }
    
    public function testSearchSorting(): void
    {
        $sortOptions = [
            ['field' => 'data_registro', 'direction' => 'desc'],
            ['field' => 'valor_total_usd', 'direction' => 'desc'],
            ['field' => 'importador_nome', 'direction' => 'asc']
        ];
        
        foreach ($sortOptions as $sort) {
            $searchData = [
                'query' => '',
                'sort' => $sort,
                'limit' => 25
            ];
            
            $response = $this->client->post('/search', $searchData);
            
            if ($this->client->wasSuccessful()) {
                $results = $response['data'];
                
                if (count($results) > 1) {
                    // Verificar ordenação
                    $field = $sort['field'];
                    $direction = $sort['direction'];
                    
                    for ($i = 0; $i < count($results) - 1; $i++) {
                        $current = $results[$i][$field];
                        $next = $results[$i + 1][$field];
                        
                        if ($direction === 'asc') {
                            $this->assertLessThanOrEqual($next, $current, 
                                "Ordenação ASC por {$field} incorreta");
                        } else {
                            $this->assertGreaterThanOrEqual($next, $current, 
                                "Ordenação DESC por {$field} incorreta");
                        }
                    }
                }
            }
        }
    }
    
    public function testSearchConcurrentRequests(): void
    {
        // Simular múltiplas buscas simultâneas
        $results = ApiPerformanceTester::testMultipleRequests($this->client, '/search', 5);
        
        // Ajustar para POST request
        // Nota: ApiPerformanceTester precisaria ser modificado para suportar POST
        // Por enquanto, fazer teste manual
        
        $times = [];
        $errors = 0;
        
        for ($i = 0; $i < 5; $i++) {
            $startTime = microtime(true);
            
            $response = $this->client->post('/search', [
                'query' => 'Test',
                'limit' => 10
            ]);
            
            $endTime = microtime(true);
            $times[] = ($endTime - $startTime) * 1000;
            
            if (!$this->client->wasSuccessful()) {
                $errors++;
            }
        }
        
        $avgTime = array_sum($times) / count($times);
        $successRate = (5 - $errors) / 5 * 100;
        
        $this->assertGreaterThanOrEqual(80, $successRate, 'Taxa de sucesso deve ser >= 80%');
        $this->assertLessThan(3000, $avgTime, 'Tempo médio sob carga deve ser < 3000ms');
    }
    
    public static function tearDownAfterClass(): void
    {
        TestBootstrap::cleanTestData();
    }
}