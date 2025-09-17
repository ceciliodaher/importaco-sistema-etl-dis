<?php
/**
 * ================================================================================
 * SISTEMA ETL DE DI's - AJAX ENDPOINT PARA TESTE DE CONEXÃO
 * API para testar conexões com banco de dados
 * ================================================================================
 */

// Configurações de erro para desenvolvimento
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Carregar dependências
require_once '../../dashboard/api/common/response.php';
require_once '../../core/DatabaseConnectionManager.php';

// Inicializar middleware de API
apiMiddleware();

// Verificar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError('Método não permitido. Use POST.', 405)->send();
}

// Verificar Content-Type
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') === false) {
    apiError('Content-Type deve ser application/json', 400)->send();
}

// Decodificar JSON do corpo da requisição
$input = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    apiError('JSON inválido: ' . json_last_error_msg(), 400)->send();
}

// Validar parâmetros obrigatórios
if (!isset($input['action'])) {
    apiError('Parâmetro "action" é obrigatório', 400)->send();
}

$action = $input['action'];

try {
    $connectionManager = DatabaseConnectionManager::getInstance();
    
    switch ($action) {
        case 'test_profile':
            handleTestProfile($connectionManager, $input);
            break;
            
        case 'test_custom':
            handleTestCustom($connectionManager, $input);
            break;
            
        case 'test_all':
            handleTestAll($connectionManager);
            break;
            
        case 'get_status':
            handleGetStatus($connectionManager);
            break;
            
        case 'refresh_detection':
            handleRefreshDetection($connectionManager);
            break;
            
        default:
            apiError('Ação não reconhecida: ' . $action, 400)->send();
    }
    
} catch (Exception $e) {
    error_log('Erro no teste de conexão: ' . $e->getMessage());
    apiError('Erro interno do servidor: ' . $e->getMessage(), 500)->send();
}

/**
 * Testar um perfil específico
 */
function handleTestProfile($connectionManager, $input) {
    if (!isset($input['profile'])) {
        apiError('Parâmetro "profile" é obrigatório para test_profile', 400)->send();
    }
    
    $profileName = $input['profile'];
    
    try {
        $result = $connectionManager->testConnection($profileName);
        
        $response = apiSuccess($result, 'Teste de conexão concluído');
        $response->addMeta('profile', $profileName);
        $response->addMeta('timestamp', date('Y-m-d H:i:s'));
        $response->send();
        
    } catch (InvalidArgumentException $e) {
        apiError('Perfil não encontrado: ' . $profileName, 404)->send();
    } catch (Exception $e) {
        apiError('Erro ao testar perfil: ' . $e->getMessage(), 500)->send();
    }
}

/**
 * Testar configuração customizada
 */
function handleTestCustom($connectionManager, $input) {
    $requiredFields = ['host', 'port', 'database', 'username'];
    
    foreach ($requiredFields as $field) {
        if (!isset($input[$field]) || trim($input[$field]) === '') {
            apiError("Campo '$field' é obrigatório", 400)->send();
        }
    }
    
    // Validar porta
    $port = (int)$input['port'];
    if ($port < 1 || $port > 65535) {
        apiError('Porta deve estar entre 1 e 65535', 400)->send();
    }
    
    // Preparar configuração customizada
    $customConfig = [
        'host' => trim($input['host']),
        'port' => $port,
        'database' => trim($input['database']),
        'username' => trim($input['username']),
        'password' => $input['password'] ?? '',
        'charset' => 'utf8mb4'
    ];
    
    try {
        // Testar conexão customizada
        $dsn = sprintf(
            'mysql:host=%s;port=%d;charset=%s',
            $customConfig['host'],
            $customConfig['port'],
            $customConfig['charset']
        );
        
        $startTime = microtime(true);
        $pdo = new PDO($dsn, $customConfig['username'], $customConfig['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5
        ]);
        
        // Testar query simples
        $stmt = $pdo->query('SELECT 1 as test, VERSION() as version');
        $testResult = $stmt->fetch();
        $endTime = microtime(true);
        
        // Testar se o database existe
        $dbExists = false;
        try {
            $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '" . $customConfig['database'] . "'");
            $dbExists = $stmt->fetch() !== false;
        } catch (Exception $e) {
            // Ignorar erros de permissão
        }
        
        $pdo = null; // Fechar conexão
        
        $result = [
            'success' => true,
            'message' => 'Conexão estabelecida com sucesso',
            'server_version' => $testResult['version'] ?? 'N/A',
            'response_time' => round(($endTime - $startTime) * 1000, 2) . 'ms',
            'database_exists' => $dbExists,
            'config' => [
                'host' => $customConfig['host'],
                'port' => $customConfig['port'],
                'database' => $customConfig['database'],
                'username' => $customConfig['username']
            ]
        ];
        
        $response = apiSuccess($result, 'Teste de conexão customizada concluído');
        $response->addMeta('type', 'custom');
        $response->addMeta('timestamp', date('Y-m-d H:i:s'));
        $response->send();
        
    } catch (PDOException $e) {
        $errorCode = $e->getCode();
        $errorMessage = $e->getMessage();
        
        // Mapear códigos de erro comuns
        $friendlyMessage = getFriendlyErrorMessage($errorCode, $errorMessage);
        
        $result = [
            'success' => false,
            'message' => $friendlyMessage,
            'error_code' => $errorCode,
            'raw_error' => $errorMessage,
            'config' => [
                'host' => $customConfig['host'],
                'port' => $customConfig['port'],
                'database' => $customConfig['database'],
                'username' => $customConfig['username']
            ]
        ];
        
        $response = apiSuccess($result, 'Teste de conexão customizada concluído');
        $response->addMeta('type', 'custom');
        $response->addMeta('timestamp', date('Y-m-d H:i:s'));
        $response->send();
    }
}

/**
 * Testar todos os perfis disponíveis
 */
function handleTestAll($connectionManager) {
    try {
        $results = $connectionManager->testAllConnections();
        
        $response = apiSuccess($results, 'Teste de todos os perfis concluído');
        $response->addMeta('total_profiles', count($results));
        $response->addMeta('timestamp', date('Y-m-d H:i:s'));
        $response->send();
        
    } catch (Exception $e) {
        apiError('Erro ao testar todos os perfis: ' . $e->getMessage(), 500)->send();
    }
}

/**
 * Obter status atual do sistema
 */
function handleGetStatus($connectionManager) {
    try {
        $status = $connectionManager->getStatus();
        $currentProfile = $connectionManager->getCurrentProfileName();
        
        // Testar conexão atual se existir
        $currentConnectionStatus = null;
        if ($currentProfile) {
            $currentConnectionStatus = $connectionManager->testConnection($currentProfile);
        }
        
        $result = [
            'system_status' => $status,
            'current_profile' => $currentProfile,
            'current_connection' => $currentConnectionStatus,
            'detected_environments' => $connectionManager->getDetectedEnvironments()
        ];
        
        $response = apiSuccess($result, 'Status do sistema obtido');
        $response->addMeta('timestamp', date('Y-m-d H:i:s'));
        $response->send();
        
    } catch (Exception $e) {
        apiError('Erro ao obter status: ' . $e->getMessage(), 500)->send();
    }
}

/**
 * Atualizar detecção de ambientes
 */
function handleRefreshDetection($connectionManager) {
    try {
        $connectionManager->refreshEnvironmentDetection();
        $detectedEnvironments = $connectionManager->getDetectedEnvironments();
        
        $response = apiSuccess($detectedEnvironments, 'Detecção de ambientes atualizada');
        $response->addMeta('total_detected', count($detectedEnvironments));
        $response->addMeta('timestamp', date('Y-m-d H:i:s'));
        $response->send();
        
    } catch (Exception $e) {
        apiError('Erro ao atualizar detecção: ' . $e->getMessage(), 500)->send();
    }
}

/**
 * Converter códigos de erro em mensagens amigáveis
 */
function getFriendlyErrorMessage($errorCode, $errorMessage) {
    // Códigos de erro MySQL comuns
    $errorMappings = [
        1045 => 'Credenciais inválidas. Verifique usuário e senha.',
        2002 => 'Não foi possível conectar ao servidor. Verifique host e porta.',
        1049 => 'Database não existe no servidor.',
        2003 => 'Servidor MySQL não está rodando ou não é acessível.',
        1044 => 'Usuário não tem permissão para acessar o database.',
        1129 => 'Host bloqueado devido a muitas falhas de conexão.',
        2006 => 'Conexão com o servidor perdida.',
        2013 => 'Conexão perdida durante a query.'
    ];
    
    if (isset($errorMappings[$errorCode])) {
        return $errorMappings[$errorCode];
    }
    
    // Verificar por padrões na mensagem
    if (strpos($errorMessage, 'Access denied') !== false) {
        return 'Acesso negado. Verifique usuário e senha.';
    }
    
    if (strpos($errorMessage, 'Connection refused') !== false) {
        return 'Conexão recusada. Servidor pode estar offline.';
    }
    
    if (strpos($errorMessage, 'timeout') !== false) {
        return 'Timeout na conexão. Servidor demorou para responder.';
    }
    
    if (strpos($errorMessage, 'Unknown database') !== false) {
        return 'Database não encontrado no servidor.';
    }
    
    // Retornar mensagem original se não houver mapeamento
    return 'Erro de conexão: ' . $errorMessage;
}
?>