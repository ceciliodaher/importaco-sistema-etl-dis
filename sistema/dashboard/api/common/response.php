<?php
/**
 * ================================================================================
 * RESPONSE HELPER - API Response Padronizada
 * Sistema ETL DI's - Formatação de respostas JSON
 * ================================================================================
 */

/**
 * Middleware de inicialização da API
 */
function apiMiddleware() {
    // Headers CORS
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Content-Type: application/json; charset=UTF-8');
    
    // Headers de segurança
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    
    // Tratamento de OPTIONS para CORS
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

/**
 * Classe para resposta padronizada da API
 */
class ApiResponse {
    private $success;
    private $data;
    private $message;
    private $code;
    private $meta;
    
    public function __construct($success = true, $code = 200) {
        $this->success = $success;
        $this->code = $code;
        $this->data = [];
        $this->meta = [];
    }
    
    public function setData($data) {
        $this->data = $data;
        return $this;
    }
    
    public function setMessage($message) {
        $this->message = $message;
        return $this;
    }
    
    public function addMeta($key, $value) {
        $this->meta[$key] = $value;
        return $this;
    }
    
    public function setCacheStats($cached, $type) {
        $this->meta['cached'] = $cached;
        $this->meta['cache_type'] = $type;
        return $this;
    }
    
    public function send() {
        http_response_code($this->code);
        
        $response = [
            'success' => $this->success,
            'timestamp' => date('Y-m-d H:i:s'),
            'data' => $this->data
        ];
        
        if ($this->message) {
            $response['message'] = $this->message;
        }
        
        if (!empty($this->meta)) {
            $response['meta'] = $this->meta;
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit();
    }
}

/**
 * Helper para resposta de sucesso
 */
function apiSuccess($data = [], $message = null) {
    $response = new ApiResponse(true, 200);
    if ($data) $response->setData($data);
    if ($message) $response->setMessage($message);
    return $response;
}

/**
 * Helper para resposta de erro
 */
function apiError($message, $code = 400) {
    $response = new ApiResponse(false, $code);
    $response->setMessage($message);
    return $response;
}