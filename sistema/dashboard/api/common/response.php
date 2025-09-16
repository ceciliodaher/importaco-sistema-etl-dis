<?php
/**
 * ================================================================================
 * SISTEMA DE RESPOSTA PADRONIZADA PARA APIs
 * Padronização de responses com metadata, paginação e performance
 * ================================================================================
 */

/**
 * Classe para padronização de respostas das APIs
 */
class ApiResponse 
{
    private $data = null;
    private $success = true;
    private $error = null;
    private $meta = [];
    private $pagination = null;
    private $startTime;
    
    public function __construct() 
    {
        $this->startTime = microtime(true);
        $this->meta = [
            'timestamp' => date('c'),
            'version' => '1.0.0'
        ];
    }

    /**
     * Definir dados de sucesso
     */
    public function setData($data) 
    {
        $this->data = $data;
        $this->success = true;
        return $this;
    }

    /**
     * Definir erro
     */
    public function setError(string $message, int $code = 500) 
    {
        $this->error = $message;
        $this->success = false;
        http_response_code($code);
        return $this;
    }

    /**
     * Adicionar metadata
     */
    public function addMeta(string $key, $value) 
    {
        $this->meta[$key] = $value;
        return $this;
    }

    /**
     * Definir paginação
     */
    public function setPagination(int $page, int $limit, int $total, int $totalPages = null) 
    {
        $this->pagination = [
            'current_page' => $page,
            'per_page' => $limit,
            'total_records' => $total,
            'total_pages' => $totalPages ?? (int)ceil($total / $limit),
            'has_next' => $page < ($totalPages ?? (int)ceil($total / $limit)),
            'has_prev' => $page > 1
        ];
        return $this;
    }

    /**
     * Adicionar estatísticas de cache
     */
    public function setCacheStats(bool $hit, string $source = null) 
    {
        $this->meta['cache_hit'] = $hit;
        if ($source) {
            $this->meta['cache_source'] = $source; // L1, L2, etc.
        }
        return $this;
    }

    /**
     * Enviar resposta JSON
     */
    public function send() 
    {
        // Calcular tempo de execução
        $this->meta['execution_time'] = round((microtime(true) - $this->startTime) * 1000, 2) . 'ms';
        
        // Adicionar informações de memória se em desenvolvimento
        if (isset($_ENV['ETL_ENVIRONMENT']) && $_ENV['ETL_ENVIRONMENT'] === 'development') {
            $this->meta['memory_usage'] = $this->formatBytes(memory_get_usage(true));
            $this->meta['peak_memory'] = $this->formatBytes(memory_get_peak_usage(true));
        }

        // Construir resposta
        $response = [
            'success' => $this->success,
            'meta' => $this->meta
        ];

        if ($this->success) {
            $response['data'] = $this->data;
            if ($this->pagination !== null) {
                $response['pagination'] = $this->pagination;
            }
        } else {
            $response['error'] = $this->error;
        }

        // Headers de performance
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
        header('Cache-Control: public, max-age=60'); // Cache por 1 minuto
        
        // Header personalizado com tempo de execução
        header('X-Response-Time: ' . $this->meta['execution_time']);

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        exit();
    }

    /**
     * Formatar bytes para leitura humana
     */
    private function formatBytes(int $bytes, int $precision = 2): string 
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

/**
 * Classe para validação de requests
 */
class ApiValidator 
{
    private $errors = [];

    /**
     * Validar parâmetros obrigatórios
     */
    public function required(array $params, array $required): bool 
    {
        foreach ($required as $field) {
            if (!isset($params[$field]) || empty($params[$field])) {
                $this->errors[] = "Campo obrigatório ausente: {$field}";
            }
        }
        return empty($this->errors);
    }

    /**
     * Validar tipos de dados
     */
    public function types(array $params, array $types): bool 
    {
        foreach ($types as $field => $type) {
            if (isset($params[$field])) {
                $value = $params[$field];
                $valid = false;

                switch ($type) {
                    case 'int':
                        $valid = is_numeric($value) && (int)$value == $value;
                        break;
                    case 'float':
                        $valid = is_numeric($value);
                        break;
                    case 'string':
                        $valid = is_string($value);
                        break;
                    case 'bool':
                        $valid = is_bool($value) || in_array($value, ['true', 'false', '1', '0']);
                        break;
                    case 'array':
                        $valid = is_array($value);
                        break;
                    case 'date':
                        $valid = strtotime($value) !== false;
                        break;
                }

                if (!$valid) {
                    $this->errors[] = "Campo {$field} deve ser do tipo {$type}";
                }
            }
        }
        return empty($this->errors);
    }

    /**
     * Validar valores permitidos
     */
    public function allowedValues(array $params, array $allowed): bool 
    {
        foreach ($allowed as $field => $values) {
            if (isset($params[$field]) && !in_array($params[$field], $values)) {
                $this->errors[] = "Campo {$field} deve ser um dos valores: " . implode(', ', $values);
            }
        }
        return empty($this->errors);
    }

    /**
     * Validar ranges numéricos
     */
    public function ranges(array $params, array $ranges): bool 
    {
        foreach ($ranges as $field => $range) {
            if (isset($params[$field])) {
                $value = (float)$params[$field];
                $min = $range['min'] ?? null;
                $max = $range['max'] ?? null;

                if ($min !== null && $value < $min) {
                    $this->errors[] = "Campo {$field} deve ser maior ou igual a {$min}";
                }
                if ($max !== null && $value > $max) {
                    $this->errors[] = "Campo {$field} deve ser menor ou igual a {$max}";
                }
            }
        }
        return empty($this->errors);
    }

    /**
     * Sanitizar SQL injection
     */
    public function sanitizeSql(string $input): string 
    {
        // Remove caracteres perigosos para SQL
        $dangerous = ['--', ';', '/*', '*/', 'xp_', 'sp_', 'UNION', 'EXEC', 'EXECUTE'];
        $input = str_ireplace($dangerous, '', $input);
        
        // Escape básico
        return addslashes(trim($input));
    }

    /**
     * Obter erros de validação
     */
    public function getErrors(): array 
    {
        return $this->errors;
    }

    /**
     * Verificar se há erros
     */
    public function hasErrors(): bool 
    {
        return !empty($this->errors);
    }

    /**
     * Limpar erros
     */
    public function clearErrors(): void 
    {
        $this->errors = [];
    }
}

/**
 * Classe para rate limiting
 */
class RateLimiter 
{
    private $cache;
    private $windowSize = 60; // 1 minuto
    private $maxRequests = 100; // 100 requests por minuto

    public function __construct() 
    {
        require_once 'cache.php';
        $this->cache = IntelligentCache::getInstance();
    }

    /**
     * Verificar se IP está dentro do limite
     */
    public function isAllowed(string $ip): bool 
    {
        $key = "rate_limit:{$ip}";
        $current = time();
        $windowStart = $current - $this->windowSize;

        // Obter requests atuais
        $requests = $this->cache->get($key);
        if ($requests === null) {
            $requests = [];
        }

        // Filtrar requests dentro da janela
        $requests = array_filter($requests, function($timestamp) use ($windowStart) {
            return $timestamp > $windowStart;
        });

        // Verificar limite
        if (count($requests) >= $this->maxRequests) {
            return false;
        }

        // Adicionar request atual
        $requests[] = $current;
        $this->cache->set($key, $requests, $this->windowSize + 10);

        return true;
    }

    /**
     * Obter informações do rate limit
     */
    public function getLimitInfo(string $ip): array 
    {
        $key = "rate_limit:{$ip}";
        $current = time();
        $windowStart = $current - $this->windowSize;

        $requests = $this->cache->get($key) ?? [];
        $requests = array_filter($requests, function($timestamp) use ($windowStart) {
            return $timestamp > $windowStart;
        });

        return [
            'limit' => $this->maxRequests,
            'remaining' => max(0, $this->maxRequests - count($requests)),
            'reset_time' => $current + $this->windowSize,
            'window_size' => $this->windowSize
        ];
    }
}

/**
 * Função helper para criar response de sucesso
 */
function apiSuccess($data = null): ApiResponse 
{
    $response = new ApiResponse();
    if ($data !== null) {
        $response->setData($data);
    }
    return $response;
}

/**
 * Função helper para criar response de erro
 */
function apiError(string $message, int $code = 500): ApiResponse 
{
    $response = new ApiResponse();
    return $response->setError($message, $code);
}

/**
 * Função helper para validar CORS
 */
function handleCORS(): void 
{
    // Headers CORS básicos
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

    // Responder OPTIONS
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

/**
 * Função helper para verificar rate limit
 */
function checkRateLimit(): void 
{
    $limiter = new RateLimiter();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    if (!$limiter->isAllowed($ip)) {
        $info = $limiter->getLimitInfo($ip);
        
        http_response_code(429);
        header('X-RateLimit-Limit: ' . $info['limit']);
        header('X-RateLimit-Remaining: ' . $info['remaining']);
        header('X-RateLimit-Reset: ' . $info['reset_time']);
        
        apiError('Rate limit excedido. Tente novamente em alguns segundos.', 429)->send();
    }

    // Adicionar headers informativos
    $info = $limiter->getLimitInfo($ip);
    header('X-RateLimit-Limit: ' . $info['limit']);
    header('X-RateLimit-Remaining: ' . $info['remaining']);
}

/**
 * Middleware para APIs com segurança avançada
 */
function apiMiddleware(): void 
{
    // Configurar headers de erro
    ini_set('display_errors', 0);
    error_reporting(E_ALL);

    // Configurar timezone
    date_default_timezone_set('America/Sao_Paulo');

    // Verificar CORS
    handleCORS();

    // Verificar segurança avançada (inclui rate limiting)
    checkAdvancedSecurity();

    // Headers de segurança adicionais
    addSecurityHeaders();

    // Handler de erro global
    set_error_handler(function($severity, $message, $file, $line) {
        if (error_reporting() & $severity) {
            throw new ErrorException($message, 0, $severity, $file, $line);
        }
    });

    // Handler de exceção global
    set_exception_handler(function($exception) {
        error_log("API Exception: " . $exception->getMessage());
        apiError('Erro interno do servidor', 500)->send();
    });
}

/**
 * Verificação de segurança avançada
 */
function checkAdvancedSecurity(): void 
{
    require_once 'security.php';
    
    // Determinar endpoint para rate limiting específico
    $endpoint = determineEndpoint($_SERVER['REQUEST_URI'] ?? '');
    
    $securityCheck = checkAPISecurity($endpoint);
    
    if (!$securityCheck['allowed']) {
        $reason = $securityCheck['reason'] ?? 'unknown';
        $message = $securityCheck['message'] ?? 'Access denied';
        
        if ($reason === 'rate_limit') {
            $response = apiError($message, 429);
            if (isset($securityCheck['retry_after'])) {
                header('Retry-After: ' . $securityCheck['retry_after']);
            }
        } else {
            $response = apiError($message, 403);
        }
        
        $response->send();
    }
}

/**
 * Adicionar headers de segurança
 */
function addSecurityHeaders(): void 
{
    // Prevenir ataques XSS
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    
    // Política de segurança de conteúdo básica para APIs
    header('Content-Security-Policy: default-src \'none\'; script-src \'none\'; object-src \'none\';');
    
    // Prevenir ataques de referrer
    header('Referrer-Policy: no-referrer');
    
    // Headers de cache seguros
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

/**
 * Determinar endpoint para rate limiting específico
 */
function determineEndpoint(string $uri): string 
{
    if (strpos($uri, '/search') !== false) return 'search';
    if (strpos($uri, '/export') !== false) return 'export';
    if (strpos($uri, '/upload') !== false) return 'upload';
    if (strpos($uri, '/realtime') !== false) return 'realtime';
    if (strpos($uri, '/charts') !== false) return 'charts';
    if (strpos($uri, '/stats') !== false) return 'stats';
    
    return 'default';
}