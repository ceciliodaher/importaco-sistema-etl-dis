<?php
/**
 * ================================================================================
 * API HELPER - Utilitários para Testes de APIs REST
 * Client HTTP para testes, mocks de respostas e validações
 * ================================================================================
 */

/**
 * Cliente HTTP para testes de APIs
 */
class ApiTestClient
{
    private $baseUrl;
    private $defaultHeaders;
    private $lastResponse;
    private $lastResponseTime;
    
    public function __construct(string $baseUrl = 'http://localhost:8000/api')
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->defaultHeaders = [
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: Test Client/1.0'
        ];
    }
    
    /**
     * GET request
     */
    public function get(string $endpoint, array $params = [], array $headers = []): array
    {
        $url = $this->baseUrl . $endpoint;
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        return $this->makeRequest('GET', $url, null, $headers);
    }
    
    /**
     * POST request
     */
    public function post(string $endpoint, $data = null, array $headers = []): array
    {
        $url = $this->baseUrl . $endpoint;
        return $this->makeRequest('POST', $url, $data, $headers);
    }
    
    /**
     * PUT request
     */
    public function put(string $endpoint, $data = null, array $headers = []): array
    {
        $url = $this->baseUrl . $endpoint;
        return $this->makeRequest('PUT', $url, $data, $headers);
    }
    
    /**
     * DELETE request
     */
    public function delete(string $endpoint, array $headers = []): array
    {
        $url = $this->baseUrl . $endpoint;
        return $this->makeRequest('DELETE', $url, null, $headers);
    }
    
    /**
     * Upload de arquivo
     */
    public function uploadFile(string $endpoint, string $filePath, string $fieldName = 'file', array $additionalData = []): array
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("Arquivo não encontrado: {$filePath}");
        }
        
        $url = $this->baseUrl . $endpoint;
        
        $postData = $additionalData;
        $postData[$fieldName] = new CURLFile($filePath);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => array_merge($this->defaultHeaders, ['Content-Type: multipart/form-data'])
        ]);
        
        $startTime = microtime(true);
        $response = curl_exec($ch);
        $this->lastResponseTime = (microtime(true) - $startTime) * 1000;
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new RuntimeException("Erro cURL: {$error}");
        }
        
        $this->lastResponse = [
            'http_code' => $httpCode,
            'body' => $response,
            'response_time' => $this->lastResponseTime
        ];
        
        return $this->parseResponse($response, $httpCode);
    }
    
    /**
     * Fazer requisição HTTP
     */
    private function makeRequest(string $method, string $url, $data = null, array $headers = []): array
    {
        $ch = curl_init();
        
        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => array_merge($this->defaultHeaders, $headers),
            CURLOPT_CUSTOMREQUEST => $method
        ];
        
        if ($data !== null) {
            if (is_array($data) || is_object($data)) {
                $curlOptions[CURLOPT_POSTFIELDS] = json_encode($data);
            } else {
                $curlOptions[CURLOPT_POSTFIELDS] = $data;
            }
        }
        
        curl_setopt_array($ch, $curlOptions);
        
        $startTime = microtime(true);
        $response = curl_exec($ch);
        $this->lastResponseTime = (microtime(true) - $startTime) * 1000;
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new RuntimeException("Erro cURL: {$error}");
        }
        
        $this->lastResponse = [
            'http_code' => $httpCode,
            'body' => $response,
            'response_time' => $this->lastResponseTime
        ];
        
        return $this->parseResponse($response, $httpCode);
    }
    
    /**
     * Parse da resposta
     */
    private function parseResponse(string $response, int $httpCode): array
    {
        $decoded = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => 'Invalid JSON response',
                'raw_response' => $response,
                'http_code' => $httpCode,
                'response_time' => $this->lastResponseTime
            ];
        }
        
        return array_merge($decoded, [
            'http_code' => $httpCode,
            'response_time' => $this->lastResponseTime
        ]);
    }
    
    /**
     * Obter última resposta completa
     */
    public function getLastResponse(): ?array
    {
        return $this->lastResponse;
    }
    
    /**
     * Obter tempo da última resposta
     */
    public function getLastResponseTime(): float
    {
        return $this->lastResponseTime ?? 0;
    }
    
    /**
     * Verificar se última resposta foi bem-sucedida
     */
    public function wasSuccessful(): bool
    {
        return $this->lastResponse && 
               $this->lastResponse['http_code'] >= 200 && 
               $this->lastResponse['http_code'] < 300;
    }
}

/**
 * Helper para validações de API
 */
class ApiValidator
{
    /**
     * Validar estrutura de resposta da API
     */
    public static function validateApiResponse(array $response, array $requiredFields = []): void
    {
        $defaultFields = ['success', 'message', 'timestamp'];
        $fields = array_merge($defaultFields, $requiredFields);
        
        foreach ($fields as $field) {
            if (!array_key_exists($field, $response)) {
                throw new InvalidArgumentException("Campo obrigatório '{$field}' não encontrado na resposta");
            }
        }
        
        // Validar timestamp
        if (isset($response['timestamp'])) {
            $timestamp = strtotime($response['timestamp']);
            if ($timestamp === false) {
                throw new InvalidArgumentException("Timestamp inválido: {$response['timestamp']}");
            }
            
            // Verificar se timestamp é recente (máximo 1 minuto de diferença)
            $diff = abs(time() - $timestamp);
            if ($diff > 60) {
                throw new InvalidArgumentException("Timestamp muito antigo: diferença de {$diff} segundos");
            }
        }
    }
    
    /**
     * Validar paginação
     */
    public static function validatePagination(array $pagination): void
    {
        $required = ['current_page', 'total_pages', 'total_records', 'records_per_page'];
        
        foreach ($required as $field) {
            if (!array_key_exists($field, $pagination)) {
                throw new InvalidArgumentException("Campo de paginação '{$field}' não encontrado");
            }
            
            if (!is_numeric($pagination[$field]) || $pagination[$field] < 0) {
                throw new InvalidArgumentException("Campo de paginação '{$field}' deve ser um número positivo");
            }
        }
        
        // Validações lógicas
        if ($pagination['current_page'] > $pagination['total_pages'] && $pagination['total_pages'] > 0) {
            throw new InvalidArgumentException("Página atual maior que total de páginas");
        }
        
        if ($pagination['records_per_page'] > 1000) {
            throw new InvalidArgumentException("Muitos registros por página: {$pagination['records_per_page']}");
        }
    }
    
    /**
     * Validar dados de dashboard
     */
    public static function validateDashboardData(array $data): void
    {
        $required = ['total_dis', 'total_adicoes', 'valor_total_usd', 'valor_total_brl'];
        
        foreach ($required as $field) {
            if (!array_key_exists($field, $data)) {
                throw new InvalidArgumentException("Campo de dashboard '{$field}' não encontrado");
            }
            
            if (!is_numeric($data[$field]) || $data[$field] < 0) {
                throw new InvalidArgumentException("Campo de dashboard '{$field}' deve ser um número positivo");
            }
        }
        
        // Validar consistência
        if ($data['valor_total_brl'] > 0 && $data['valor_total_usd'] > 0) {
            $taxa = $data['valor_total_brl'] / $data['valor_total_usd'];
            if ($taxa < 3 || $taxa > 8) {
                throw new InvalidArgumentException("Taxa de câmbio calculada parece incorreta: {$taxa}");
            }
        }
    }
    
    /**
     * Validar dados de gráfico
     */
    public static function validateChartData(array $chartData): void
    {
        if (!isset($chartData['labels']) || !isset($chartData['datasets'])) {
            throw new InvalidArgumentException("Dados de gráfico devem ter 'labels' e 'datasets'");
        }
        
        if (!is_array($chartData['labels']) || !is_array($chartData['datasets'])) {
            throw new InvalidArgumentException("Labels e datasets devem ser arrays");
        }
        
        foreach ($chartData['datasets'] as $index => $dataset) {
            if (!isset($dataset['label']) || !isset($dataset['data'])) {
                throw new InvalidArgumentException("Dataset {$index} deve ter 'label' e 'data'");
            }
            
            if (count($dataset['data']) !== count($chartData['labels'])) {
                throw new InvalidArgumentException("Dataset {$index} tem quantidade de dados diferente dos labels");
            }
        }
    }
}

/**
 * Mock de respostas para testes
 */
class ApiMocker
{
    private static $mocks = [];
    
    /**
     * Registrar mock para endpoint
     */
    public static function mockEndpoint(string $method, string $endpoint, array $response, int $httpCode = 200): void
    {
        $key = strtoupper($method) . ':' . $endpoint;
        self::$mocks[$key] = [
            'response' => $response,
            'http_code' => $httpCode,
            'call_count' => 0
        ];
    }
    
    /**
     * Obter mock para endpoint
     */
    public static function getMock(string $method, string $endpoint): ?array
    {
        $key = strtoupper($method) . ':' . $endpoint;
        
        if (isset(self::$mocks[$key])) {
            self::$mocks[$key]['call_count']++;
            return self::$mocks[$key];
        }
        
        return null;
    }
    
    /**
     * Limpar todos os mocks
     */
    public static function clearMocks(): void
    {
        self::$mocks = [];
    }
    
    /**
     * Obter estatísticas de chamadas
     */
    public static function getCallStats(): array
    {
        $stats = [];
        
        foreach (self::$mocks as $key => $mock) {
            $stats[$key] = $mock['call_count'];
        }
        
        return $stats;
    }
    
    /**
     * Gerar resposta de sucesso padrão
     */
    public static function successResponse(array $data = [], string $message = 'Sucesso'): array
    {
        return [
            'success' => true,
            'data' => $data,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Gerar resposta de erro padrão
     */
    public static function errorResponse(string $message = 'Erro', int $code = 400, array $details = []): array
    {
        return [
            'success' => false,
            'error' => [
                'message' => $message,
                'code' => $code,
                'details' => $details
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}

/**
 * Helper para testes de performance de API
 */
class ApiPerformanceTester
{
    /**
     * Testar múltiplas requisições
     */
    public static function testMultipleRequests(ApiTestClient $client, string $endpoint, int $count = 10): array
    {
        $times = [];
        $errors = 0;
        $responses = [];
        
        for ($i = 0; $i < $count; $i++) {
            try {
                $response = $client->get($endpoint);
                $times[] = $client->getLastResponseTime();
                $responses[] = $response;
                
                if (!$client->wasSuccessful()) {
                    $errors++;
                }
            } catch (Exception $e) {
                $errors++;
                $times[] = 0;
                $responses[] = ['error' => $e->getMessage()];
            }
            
            // Pequena pausa entre requisições
            usleep(50000); // 50ms
        }
        
        return [
            'count' => $count,
            'errors' => $errors,
            'success_rate' => (($count - $errors) / $count) * 100,
            'times' => [
                'min' => min($times),
                'max' => max($times),
                'avg' => array_sum($times) / count($times),
                'median' => self::calculateMedian($times)
            ],
            'responses' => $responses
        ];
    }
    
    /**
     * Calcular mediana
     */
    private static function calculateMedian(array $values): float
    {
        sort($values);
        $count = count($values);
        
        if ($count % 2 === 0) {
            return ($values[$count / 2 - 1] + $values[$count / 2]) / 2;
        } else {
            return $values[floor($count / 2)];
        }
    }
}