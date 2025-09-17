<?php
/**
 * ================================================================================
 * SISTEMA ETL DE DI's - AJAX ENDPOINT PARA SALVAR PERFIS
 * API para salvar e gerenciar perfis de conexão
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
        case 'save_custom_profile':
            handleSaveCustomProfile($connectionManager, $input);
            break;
            
        case 'switch_profile':
            handleSwitchProfile($connectionManager, $input);
            break;
            
        case 'delete_custom_profile':
            handleDeleteCustomProfile($connectionManager, $input);
            break;
            
        case 'export_profiles':
            handleExportProfiles($connectionManager);
            break;
            
        case 'import_profiles':
            handleImportProfiles($connectionManager, $input);
            break;
            
        default:
            apiError('Ação não reconhecida: ' . $action, 400)->send();
    }
    
} catch (Exception $e) {
    error_log('Erro ao salvar perfil: ' . $e->getMessage());
    apiError('Erro interno do servidor: ' . $e->getMessage(), 500)->send();
}

/**
 * Salvar perfil customizado
 */
function handleSaveCustomProfile($connectionManager, $input) {
    $requiredFields = ['name', 'host', 'port', 'database', 'username'];
    
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
    
    // Gerar nome do perfil baseado no nome fornecido
    $profileName = generateProfileKey($input['name']);
    
    // Verificar se já existe
    $existingProfiles = $connectionManager->getAvailableProfiles();
    if (in_array($profileName, $existingProfiles)) {
        apiError('Já existe um perfil com este nome. Use um nome diferente.', 409)->send();
    }
    
    // Criar configuração do perfil
    $profileConfig = [
        'name' => trim($input['name']),
        'host' => trim($input['host']),
        'port' => $port,
        'database' => trim($input['database']),
        'username' => trim($input['username']),
        'password' => $input['password'] ?? '',
        'charset' => 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ],
        'mysql_settings' => [
            "SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'",
            "SET SESSION time_zone = '+00:00'"
        ],
        'environment_type' => 'custom',
        'description' => $input['description'] ?? 'Configuração personalizada criada pelo usuário',
        'created_at' => date('Y-m-d H:i:s'),
        'is_custom' => true
    ];
    
    try {
        // Salvar configuração personalizada em arquivo
        $customProfilesFile = __DIR__ . '/../custom-profiles.php';
        
        $customProfiles = [];
        if (file_exists($customProfilesFile)) {
            $customProfiles = include $customProfilesFile;
            if (!is_array($customProfiles)) {
                $customProfiles = [];
            }
        }
        
        $customProfiles[$profileName] = $profileConfig;
        
        $fileContent = "<?php\n/**\n * Perfis de conexão personalizados\n * Gerado automaticamente - não editar manualmente\n */\n\nreturn " . var_export($customProfiles, true) . ";\n";
        
        if (!file_put_contents($customProfilesFile, $fileContent, LOCK_EX)) {
            throw new RuntimeException('Não foi possível salvar o perfil personalizado');
        }
        
        // Adicionar perfil ao gerenciador
        $connectionManager->addProfile($profileName, $profileConfig);
        
        $result = [
            'profile_name' => $profileName,
            'config' => $profileConfig,
            'saved_at' => date('Y-m-d H:i:s')
        ];
        
        $response = apiSuccess($result, 'Perfil personalizado salvo com sucesso');
        $response->addMeta('profile_key', $profileName);
        $response->addMeta('timestamp', date('Y-m-d H:i:s'));
        $response->send();
        
    } catch (Exception $e) {
        apiError('Erro ao salvar perfil: ' . $e->getMessage(), 500)->send();
    }
}

/**
 * Trocar perfil ativo
 */
function handleSwitchProfile($connectionManager, $input) {
    if (!isset($input['profile'])) {
        apiError('Parâmetro "profile" é obrigatório', 400)->send();
    }
    
    $profileName = $input['profile'];
    
    try {
        // Verificar se o perfil existe
        $availableProfiles = $connectionManager->getAvailableProfiles();
        if (!in_array($profileName, $availableProfiles)) {
            apiError('Perfil não encontrado: ' . $profileName, 404)->send();
        }
        
        // Testar conexão antes de trocar
        $testResult = $connectionManager->testConnection($profileName);
        if (!$testResult['success']) {
            apiError('Não é possível trocar para um perfil com falha na conexão: ' . $testResult['message'], 400)->send();
        }
        
        // Definir novo perfil
        $connectionManager->setProfile($profileName);
        
        // Salvar preferência em arquivo ou sessão
        $preferencesFile = __DIR__ . '/../user-preferences.php';
        $preferences = [];
        
        if (file_exists($preferencesFile)) {
            $preferences = include $preferencesFile;
            if (!is_array($preferences)) {
                $preferences = [];
            }
        }
        
        $preferences['active_profile'] = $profileName;
        $preferences['last_switched'] = date('Y-m-d H:i:s');
        
        $fileContent = "<?php\n/**\n * Preferências do usuário\n * Gerado automaticamente\n */\n\nreturn " . var_export($preferences, true) . ";\n";
        
        file_put_contents($preferencesFile, $fileContent, LOCK_EX);
        
        $result = [
            'previous_profile' => $input['previous_profile'] ?? null,
            'new_profile' => $profileName,
            'connection_test' => $testResult,
            'switched_at' => date('Y-m-d H:i:s')
        ];
        
        $response = apiSuccess($result, 'Perfil alterado com sucesso');
        $response->addMeta('active_profile', $profileName);
        $response->addMeta('timestamp', date('Y-m-d H:i:s'));
        $response->send();
        
    } catch (Exception $e) {
        apiError('Erro ao trocar perfil: ' . $e->getMessage(), 500)->send();
    }
}

/**
 * Deletar perfil customizado
 */
function handleDeleteCustomProfile($connectionManager, $input) {
    if (!isset($input['profile'])) {
        apiError('Parâmetro "profile" é obrigatório', 400)->send();
    }
    
    $profileName = $input['profile'];
    
    try {
        $customProfilesFile = __DIR__ . '/../custom-profiles.php';
        
        if (!file_exists($customProfilesFile)) {
            apiError('Perfil personalizado não encontrado', 404)->send();
        }
        
        $customProfiles = include $customProfilesFile;
        if (!is_array($customProfiles) || !isset($customProfiles[$profileName])) {
            apiError('Perfil personalizado não encontrado: ' . $profileName, 404)->send();
        }
        
        // Verificar se não é o perfil ativo
        $currentProfile = $connectionManager->getCurrentProfileName();
        if ($currentProfile === $profileName) {
            apiError('Não é possível deletar o perfil ativo. Troque para outro perfil primeiro.', 400)->send();
        }
        
        // Remover perfil
        unset($customProfiles[$profileName]);
        
        $fileContent = "<?php\n/**\n * Perfis de conexão personalizados\n * Gerado automaticamente - não editar manualmente\n */\n\nreturn " . var_export($customProfiles, true) . ";\n";
        
        if (!file_put_contents($customProfilesFile, $fileContent, LOCK_EX)) {
            throw new RuntimeException('Não foi possível deletar o perfil personalizado');
        }
        
        $result = [
            'deleted_profile' => $profileName,
            'remaining_profiles' => array_keys($customProfiles),
            'deleted_at' => date('Y-m-d H:i:s')
        ];
        
        $response = apiSuccess($result, 'Perfil personalizado deletado com sucesso');
        $response->addMeta('profile_key', $profileName);
        $response->addMeta('timestamp', date('Y-m-d H:i:s'));
        $response->send();
        
    } catch (Exception $e) {
        apiError('Erro ao deletar perfil: ' . $e->getMessage(), 500)->send();
    }
}

/**
 * Exportar perfis para backup
 */
function handleExportProfiles($connectionManager) {
    try {
        $customProfilesFile = __DIR__ . '/../custom-profiles.php';
        $preferencesFile = __DIR__ . '/../user-preferences.php';
        
        $export = [
            'version' => '1.0',
            'exported_at' => date('Y-m-d H:i:s'),
            'custom_profiles' => [],
            'user_preferences' => []
        ];
        
        // Exportar perfis customizados
        if (file_exists($customProfilesFile)) {
            $customProfiles = include $customProfilesFile;
            if (is_array($customProfiles)) {
                $export['custom_profiles'] = $customProfiles;
            }
        }
        
        // Exportar preferências (sem senhas)
        if (file_exists($preferencesFile)) {
            $preferences = include $preferencesFile;
            if (is_array($preferences)) {
                $export['user_preferences'] = $preferences;
            }
        }
        
        $response = apiSuccess($export, 'Perfis exportados com sucesso');
        $response->addMeta('total_profiles', count($export['custom_profiles']));
        $response->addMeta('timestamp', date('Y-m-d H:i:s'));
        $response->send();
        
    } catch (Exception $e) {
        apiError('Erro ao exportar perfis: ' . $e->getMessage(), 500)->send();
    }
}

/**
 * Importar perfis de backup
 */
function handleImportProfiles($connectionManager, $input) {
    if (!isset($input['data'])) {
        apiError('Dados de importação são obrigatórios', 400)->send();
    }
    
    $importData = $input['data'];
    
    // Validar estrutura dos dados
    if (!is_array($importData) || !isset($importData['custom_profiles'])) {
        apiError('Formato de dados inválido', 400)->send();
    }
    
    try {
        $customProfilesFile = __DIR__ . '/../custom-profiles.php';
        
        // Carregar perfis existentes
        $existingProfiles = [];
        if (file_exists($customProfilesFile)) {
            $existingProfiles = include $customProfilesFile;
            if (!is_array($existingProfiles)) {
                $existingProfiles = [];
            }
        }
        
        $importedCount = 0;
        $skippedCount = 0;
        $importedProfiles = [];
        
        foreach ($importData['custom_profiles'] as $profileName => $profileConfig) {
            if (isset($existingProfiles[$profileName])) {
                $skippedCount++;
                continue;
            }
            
            // Adicionar timestamp de importação
            $profileConfig['imported_at'] = date('Y-m-d H:i:s');
            
            $existingProfiles[$profileName] = $profileConfig;
            $importedProfiles[] = $profileName;
            $importedCount++;
        }
        
        // Salvar perfis atualizados
        if ($importedCount > 0) {
            $fileContent = "<?php\n/**\n * Perfis de conexão personalizados\n * Gerado automaticamente - não editar manualmente\n */\n\nreturn " . var_export($existingProfiles, true) . ";\n";
            
            if (!file_put_contents($customProfilesFile, $fileContent, LOCK_EX)) {
                throw new RuntimeException('Não foi possível salvar os perfis importados');
            }
        }
        
        $result = [
            'imported_count' => $importedCount,
            'skipped_count' => $skippedCount,
            'imported_profiles' => $importedProfiles,
            'imported_at' => date('Y-m-d H:i:s')
        ];
        
        $response = apiSuccess($result, 'Perfis importados com sucesso');
        $response->addMeta('total_imported', $importedCount);
        $response->addMeta('timestamp', date('Y-m-d H:i:s'));
        $response->send();
        
    } catch (Exception $e) {
        apiError('Erro ao importar perfis: ' . $e->getMessage(), 500)->send();
    }
}

/**
 * Gerar chave única para o perfil baseada no nome
 */
function generateProfileKey($name) {
    // Remover caracteres especiais e espaços
    $key = preg_replace('/[^a-zA-Z0-9\s]/', '', $name);
    $key = strtolower(trim($key));
    $key = preg_replace('/\s+/', '_', $key);
    
    // Adicionar prefixo para perfis customizados
    return 'custom_' . $key;
}
?>