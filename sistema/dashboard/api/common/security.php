<?php
/**
 * ================================================================================
 * SISTEMA DE SEGURANÇA AVANÇADO PARA APIs
 * Rate limiting + Validação + Logging + Prevenção de ataques
 * ================================================================================
 */

require_once 'cache.php';

/**
 * Classe avançada de rate limiting com múltiplas estratégias
 */
class AdvancedRateLimiter 
{
    private $cache;
    private $config;
    
    public function __construct() 
    {
        $this->cache = IntelligentCache::getInstance();
        $this->config = [
            'default' => ['requests' => 100, 'window' => 60],        // 100 req/min
            'search' => ['requests' => 30, 'window' => 60],          // 30 searches/min
            'export' => ['requests' => 10, 'window' => 300],         // 10 exports/5min
            'upload' => ['requests' => 20, 'window' => 3600],        // 20 uploads/hour
            'burst' => ['requests' => 10, 'window' => 10],           // 10 req/10sec (burst protection)
            'ip_daily' => ['requests' => 5000, 'window' => 86400],   // 5000 req/day por IP
            'suspicious' => ['requests' => 5, 'window' => 300]       // IPs suspeitos: 5 req/5min
        ];
    }
    
    /**
     * Verificar rate limit com múltiplas camadas
     */
    public function checkLimit(string $identifier, string $type = 'default'): array 
    {
        $ip = $this->getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        // Verificar se IP está na lista negra
        if ($this->isBlacklisted($ip)) {
            return $this->buildResponse(false, 'IP bloqueado', 0, 0);
        }
        
        // Verificar comportamento suspeito
        if ($this->isSuspiciousBehavior($ip, $userAgent)) {
            $type = 'suspicious';
        }
        
        $limits = [
            $this->checkSingleLimit("{$type}:{$identifier}", $type),
            $this->checkSingleLimit("burst:{$ip}", 'burst'),
            $this->checkSingleLimit("ip_daily:{$ip}", 'ip_daily')
        ];
        
        // Se qualquer limite foi excedido
        foreach ($limits as $limit) {
            if (!$limit['allowed']) {
                $this->logRateLimitViolation($ip, $type, $identifier);
                return $limit;
            }
        }
        
        // Todos os limites OK
        $config = $this->config[$type] ?? $this->config['default'];
        return $this->buildResponse(true, 'OK', $config['requests'], time() + $config['window']);
    }
    
    /**
     * Verificar limite individual
     */
    private function checkSingleLimit(string $key, string $type): array 
    {
        $config = $this->config[$type] ?? $this->config['default'];
        $window = $config['window'];
        $maxRequests = $config['requests'];
        
        $current = time();
        $windowStart = $current - $window;
        
        // Obter requests atuais
        $requests = $this->cache->get($key) ?? [];
        
        // Filtrar requests dentro da janela
        $requests = array_filter($requests, function($timestamp) use ($windowStart) {
            return $timestamp > $windowStart;
        });
        
        // Verificar limite
        $currentCount = count($requests);
        $allowed = $currentCount < $maxRequests;
        
        if ($allowed) {
            // Adicionar request atual
            $requests[] = $current;
            $this->cache->set($key, $requests, $window + 60);
        }
        
        return $this->buildResponse(
            $allowed, 
            $allowed ? 'OK' : 'Rate limit exceeded', 
            max(0, $maxRequests - $currentCount - 1),
            $current + $window
        );
    }
    
    /**
     * Verificar comportamento suspeito
     */
    private function isSuspiciousBehavior(string $ip, string $userAgent): bool 
    {
        $suspiciousPatterns = [
            'bot', 'crawler', 'spider', 'scraper', 'curl', 'wget',
            'python-requests', 'php', 'java', 'scanner', 'test'
        ];
        
        $userAgentLower = strtolower($userAgent);
        
        foreach ($suspiciousPatterns as $pattern) {
            if (strpos($userAgentLower, $pattern) !== false) {
                $this->markSuspiciousActivity($ip, "Suspicious user-agent: {$userAgent}");
                return true;
            }
        }
        
        // Verificar requests muito rápidas
        $recentRequests = $this->cache->get("recent_requests:{$ip}") ?? [];
        $now = time();
        $recentRequests = array_filter($recentRequests, function($timestamp) use ($now) {
            return ($now - $timestamp) < 60; // Últimos 60 segundos
        });
        
        if (count($recentRequests) > 30) { // Mais de 30 requests em 1 minuto
            $this->markSuspiciousActivity($ip, "Too many requests in short time");
            return true;
        }
        
        $recentRequests[] = $now;
        $this->cache->set("recent_requests:{$ip}", $recentRequests, 300);
        
        return false;
    }
    
    /**
     * Verificar se IP está na lista negra
     */
    private function isBlacklisted(string $ip): bool 
    {
        $blacklist = $this->cache->get('ip_blacklist') ?? [];
        return in_array($ip, $blacklist);
    }
    
    /**
     * Marcar atividade suspeita
     */
    private function markSuspiciousActivity(string $ip, string $reason): void 
    {
        $key = "suspicious_activity:{$ip}";
        $activities = $this->cache->get($key) ?? [];
        
        $activities[] = [
            'timestamp' => time(),
            'reason' => $reason,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
        ];
        
        // Manter apenas os últimos 50 registros
        $activities = array_slice($activities, -50);
        
        $this->cache->set($key, $activities, 3600);
        
        // Se muitas atividades suspeitas, adicionar à lista negra temporariamente
        if (count($activities) > 10) {
            $this->addToBlacklist($ip, 'Excessive suspicious activity');
        }
    }
    
    /**
     * Adicionar IP à lista negra
     */
    private function addToBlacklist(string $ip, string $reason): void 
    {
        $blacklist = $this->cache->get('ip_blacklist') ?? [];
        
        if (!in_array($ip, $blacklist)) {
            $blacklist[] = $ip;
            $this->cache->set('ip_blacklist', $blacklist, 86400); // 24 horas
            
            error_log("IP {$ip} added to blacklist: {$reason}");
        }
    }
    
    /**
     * Log de violação de rate limit
     */
    private function logRateLimitViolation(string $ip, string $type, string $identifier): void 
    {
        $logEntry = [
            'timestamp' => time(),
            'ip' => $ip,
            'type' => $type,
            'identifier' => $identifier,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown'
        ];
        
        error_log("Rate limit violation: " . json_encode($logEntry));
        
        // Armazenar para estatísticas
        $violations = $this->cache->get('rate_limit_violations') ?? [];
        $violations[] = $logEntry;
        $violations = array_slice($violations, -100); // Manter últimas 100
        $this->cache->set('rate_limit_violations', $violations, 86400);
    }
    
    /**
     * Obter IP real do cliente
     */
    private function getClientIP(): string 
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Proxy/Load balancer
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                
                // Validar IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    /**
     * Construir resposta padronizada
     */
    private function buildResponse(bool $allowed, string $message, int $remaining, int $resetTime): array 
    {
        return [
            'allowed' => $allowed,
            'message' => $message,
            'remaining' => $remaining,
            'reset_time' => $resetTime,
            'retry_after' => $allowed ? 0 : max(1, $resetTime - time())
        ];
    }
    
    /**
     * Obter estatísticas de rate limiting
     */
    public function getStats(): array 
    {
        $violations = $this->cache->get('rate_limit_violations') ?? [];
        $blacklist = $this->cache->get('ip_blacklist') ?? [];
        
        return [
            'total_violations' => count($violations),
            'blacklisted_ips' => count($blacklist),
            'violations_last_hour' => count(array_filter($violations, function($v) {
                return $v['timestamp'] > (time() - 3600);
            })),
            'most_violated_endpoints' => $this->getMostViolatedEndpoints($violations)
        ];
    }
    
    /**
     * Endpoints mais violados
     */
    private function getMostViolatedEndpoints(array $violations): array 
    {
        $endpoints = [];
        
        foreach ($violations as $violation) {
            $uri = $violation['request_uri'] ?? 'unknown';
            $endpoints[$uri] = ($endpoints[$uri] ?? 0) + 1;
        }
        
        arsort($endpoints);
        return array_slice($endpoints, 0, 5, true);
    }
}

/**
 * Validador de segurança avançado
 */
class SecurityValidator 
{
    /**
     * Validar requisição contra ataques comuns
     */
    public function validateRequest(): array 
    {
        $issues = [];
        
        // SQL Injection
        if ($this->detectSQLInjection()) {
            $issues[] = ['type' => 'sql_injection', 'severity' => 'high'];
        }
        
        // XSS
        if ($this->detectXSS()) {
            $issues[] = ['type' => 'xss', 'severity' => 'medium'];
        }
        
        // Path Traversal
        if ($this->detectPathTraversal()) {
            $issues[] = ['type' => 'path_traversal', 'severity' => 'high'];
        }
        
        // Command Injection
        if ($this->detectCommandInjection()) {
            $issues[] = ['type' => 'command_injection', 'severity' => 'high'];
        }
        
        // Suspicious Headers
        if ($this->detectSuspiciousHeaders()) {
            $issues[] = ['type' => 'suspicious_headers', 'severity' => 'low'];
        }
        
        return $issues;
    }
    
    /**
     * Detectar tentativas de SQL Injection
     */
    private function detectSQLInjection(): bool 
    {
        $sqlPatterns = [
            "/('|(\\|\/)|(\\*)|(\--)|(\;)|(\|)|(\\?)|(%27)|(\\')|(\\%2527)|(\%27)|(\%5C)|(\%22)|(\%5c))/i",
            "/(union|select|insert|update|delete|drop|create|alter|exec|execute|script|javascript|vbscript)/i",
            "/(\b(or|and)\b\s*['\"]?\s*\w+\s*[=<>])/i"
        ];
        
        $input = $this->getAllInput();
        
        foreach ($sqlPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Detectar tentativas de XSS
     */
    private function detectXSS(): bool 
    {
        $xssPatterns = [
            "/<script[^>]*>.*?<\/script>/i",
            "/javascript:/i",
            "/on\w+\s*=/i",
            "/<iframe[^>]*>/i",
            "/<object[^>]*>/i",
            "/<embed[^>]*>/i"
        ];
        
        $input = $this->getAllInput();
        
        foreach ($xssPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Detectar Path Traversal
     */
    private function detectPathTraversal(): bool 
    {
        $pathPatterns = [
            "/\.\.[\/\\\\]/",
            "/[\/\\\\]\.\.[\/\\\\]/",
            "/\.\.[\/\\\\]\.\./",
            "/(\\\.){3,}/"
        ];
        
        $input = $this->getAllInput();
        
        foreach ($pathPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Detectar Command Injection
     */
    private function detectCommandInjection(): bool 
    {
        $cmdPatterns = [
            "/[;&|`$(){}[\]\\\\]/",
            "/(cat|ls|pwd|whoami|id|uname|wget|curl|nc|netcat)/i"
        ];
        
        $input = $this->getAllInput();
        
        foreach ($cmdPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Detectar headers suspeitos
     */
    private function detectSuspiciousHeaders(): bool 
    {
        $suspiciousHeaders = [
            'X-Forwarded-For' => '/^(127\.|192\.168\.|10\.|172\.(1[6-9]|2\d|3[01])\.)/i',
            'User-Agent' => '/(sqlmap|nikto|nessus|nmap|masscan|zap)/i'
        ];
        
        foreach ($suspiciousHeaders as $header => $pattern) {
            $value = $_SERVER["HTTP_" . str_replace('-', '_', strtoupper($header))] ?? '';
            if (!empty($value) && preg_match($pattern, $value)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Obter todo input da requisição
     */
    private function getAllInput(): string 
    {
        $input = '';
        
        // GET parameters
        $input .= json_encode($_GET);
        
        // POST parameters
        $input .= json_encode($_POST);
        
        // JSON body
        $jsonInput = file_get_contents('php://input');
        if (!empty($jsonInput)) {
            $input .= $jsonInput;
        }
        
        // Headers relevantes
        $headers = ['User-Agent', 'Referer', 'X-Forwarded-For'];
        foreach ($headers as $header) {
            $value = $_SERVER["HTTP_" . str_replace('-', '_', strtoupper($header))] ?? '';
            $input .= $value;
        }
        
        return strtolower($input);
    }
}

/**
 * Logger de segurança
 */
class SecurityLogger 
{
    private $cache;
    
    public function __construct() 
    {
        $this->cache = IntelligentCache::getInstance();
    }
    
    /**
     * Log de evento de segurança
     */
    public function logSecurityEvent(string $type, array $details): void 
    {
        $event = [
            'timestamp' => time(),
            'type' => $type,
            'ip' => $this->getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'details' => $details
        ];
        
        // Log no arquivo
        error_log("Security Event [{$type}]: " . json_encode($event));
        
        // Armazenar no cache para estatísticas
        $events = $this->cache->get('security_events') ?? [];
        $events[] = $event;
        $events = array_slice($events, -500); // Manter últimos 500 eventos
        $this->cache->set('security_events', $events, 86400);
        
        // Alertas críticos
        if (in_array($type, ['sql_injection', 'command_injection'])) {
            $this->sendCriticalAlert($event);
        }
    }
    
    /**
     * Enviar alerta crítico
     */
    private function sendCriticalAlert(array $event): void 
    {
        // Em produção, aqui seria enviado email, SMS, webhook, etc.
        error_log("CRITICAL SECURITY ALERT: " . json_encode($event));
        
        // Adicionar à lista de eventos críticos
        $criticalEvents = $this->cache->get('critical_security_events') ?? [];
        $criticalEvents[] = $event;
        $criticalEvents = array_slice($criticalEvents, -50);
        $this->cache->set('critical_security_events', $criticalEvents, 86400);
    }
    
    /**
     * Obter IP real do cliente
     */
    private function getClientIP(): string 
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    /**
     * Obter estatísticas de segurança
     */
    public function getSecurityStats(): array 
    {
        $events = $this->cache->get('security_events') ?? [];
        $criticalEvents = $this->cache->get('critical_security_events') ?? [];
        
        $stats = [
            'total_events' => count($events),
            'critical_events' => count($criticalEvents),
            'events_last_hour' => 0,
            'top_threats' => [],
            'threat_ips' => []
        ];
        
        $hourAgo = time() - 3600;
        $threatTypes = [];
        $threatIPs = [];
        
        foreach ($events as $event) {
            if ($event['timestamp'] > $hourAgo) {
                $stats['events_last_hour']++;
            }
            
            $type = $event['type'];
            $threatTypes[$type] = ($threatTypes[$type] ?? 0) + 1;
            
            $ip = $event['ip'];
            $threatIPs[$ip] = ($threatIPs[$ip] ?? 0) + 1;
        }
        
        arsort($threatTypes);
        arsort($threatIPs);
        
        $stats['top_threats'] = array_slice($threatTypes, 0, 5, true);
        $stats['threat_ips'] = array_slice($threatIPs, 0, 10, true);
        
        return $stats;
    }
}

/**
 * Função helper para verificar segurança completa
 */
function checkAPISecurity(string $endpoint = 'default'): array 
{
    $rateLimiter = new AdvancedRateLimiter();
    $validator = new SecurityValidator();
    $logger = new SecurityLogger();
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    
    // Rate limiting
    $rateCheck = $rateLimiter->checkLimit("{$endpoint}:{$ip}", $endpoint);
    
    if (!$rateCheck['allowed']) {
        $logger->logSecurityEvent('rate_limit_exceeded', $rateCheck);
        
        http_response_code(429);
        header('X-RateLimit-Remaining: ' . $rateCheck['remaining']);
        header('X-RateLimit-Reset: ' . $rateCheck['reset_time']);
        header('Retry-After: ' . $rateCheck['retry_after']);
        
        return [
            'allowed' => false,
            'reason' => 'rate_limit',
            'message' => 'Rate limit exceeded',
            'retry_after' => $rateCheck['retry_after']
        ];
    }
    
    // Validação de segurança
    $securityIssues = $validator->validateRequest();
    
    if (!empty($securityIssues)) {
        foreach ($securityIssues as $issue) {
            $logger->logSecurityEvent($issue['type'], $issue);
        }
        
        // Bloquear apenas ataques de alta severidade
        $highSeverityIssues = array_filter($securityIssues, function($issue) {
            return $issue['severity'] === 'high';
        });
        
        if (!empty($highSeverityIssues)) {
            http_response_code(403);
            return [
                'allowed' => false,
                'reason' => 'security_violation',
                'message' => 'Request blocked by security filter'
            ];
        }
    }
    
    // Adicionar headers de segurança
    header('X-RateLimit-Remaining: ' . $rateCheck['remaining']);
    header('X-RateLimit-Reset: ' . $rateCheck['reset_time']);
    
    return [
        'allowed' => true,
        'rate_limit' => $rateCheck,
        'security_issues' => $securityIssues
    ];
}