<?php
/**
 * ================================================================================
 * API DE LIMPEZA DE CACHE - CONTROLE MANUAL DASHBOARD
 * Sistema ETL DI's - Limpeza de cache do sistema
 * ================================================================================
 */

require_once dirname(__DIR__) . '/common/response.php';
require_once dirname(__DIR__) . '/common/cache.php';

// Middleware de inicialização
apiMiddleware();

try {
    // Verificar se é POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        apiError('Método não permitido. Use POST.', 405)->send();
    }

    // Obter parâmetros de entrada
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $cacheTypes = $input['cache_types'] ?? ['all']; // Tipos de cache a limpar
    $force = $input['force'] ?? false; // Forçar limpeza mesmo se houver erros
    
    // Executar limpeza de cache
    $clearResult = clearSystemCache($cacheTypes, $force);
    
    // Formatar resposta
    $response = apiSuccess([
        'cache_cleared' => $clearResult['success'],
        'operations_performed' => $clearResult['operations'],
        'cache_stats_before' => $clearResult['stats_before'],
        'cache_stats_after' => $clearResult['stats_after'],
        'warnings' => $clearResult['warnings'],
        'errors' => $clearResult['errors']
    ]);
    
    $response->addMeta('clear_timestamp', date('Y-m-d H:i:s'));
    $response->addMeta('cache_types_requested', $cacheTypes);
    $response->addMeta('force_mode', $force);
    
    if (!$clearResult['success']) {
        $response->setMessage('Limpeza de cache concluída com problemas');
    } else {
        $response->setMessage('Cache limpo com sucesso');
    }
    
    $response->send();
    
} catch (Exception $e) {
    error_log("API Clear Cache Error: " . $e->getMessage());
    apiError('Erro na limpeza de cache: ' . $e->getMessage(), 500)->send();
}

/**
 * Limpar cache do sistema
 */
function clearSystemCache(array $cacheTypes, bool $force = false): array 
{
    $operations = [];
    $warnings = [];
    $errors = [];
    $success = true;
    
    // Obter estatísticas antes da limpeza
    $statsBefore = getCacheStatsBefore();
    
    try {
        // Determinar quais tipos de cache limpar
        $typesToClear = determineCacheTypes($cacheTypes);
        
        foreach ($typesToClear as $type) {
            try {
                $result = clearCacheType($type, $force);
                $operations[] = [
                    'type' => $type,
                    'status' => $result['success'] ? 'success' : 'failed',
                    'details' => $result['details'],
                    'items_cleared' => $result['items_cleared']
                ];
                
                if (!$result['success']) {
                    $success = false;
                    $errors[] = "Falha ao limpar cache {$type}: " . $result['error'];
                }
                
                if (!empty($result['warnings'])) {
                    $warnings = array_merge($warnings, $result['warnings']);
                }
                
            } catch (Exception $e) {
                $success = false;
                $errors[] = "Erro ao limpar cache {$type}: " . $e->getMessage();
                
                if (!$force) {
                    break; // Parar se não for modo forçado
                }
            }
        }
        
    } catch (Exception $e) {
        $success = false;
        $errors[] = "Erro geral na limpeza: " . $e->getMessage();
    }
    
    // Obter estatísticas após a limpeza
    $statsAfter = getCacheStatsAfter();
    
    return [
        'success' => $success,
        'operations' => $operations,
        'stats_before' => $statsBefore,
        'stats_after' => $statsAfter,
        'warnings' => $warnings,
        'errors' => $errors
    ];
}

/**
 * Determinar tipos de cache a limpar
 */
function determineCacheTypes(array $requested): array 
{
    $availableTypes = [
        'apcu' => 'APCu (cache L1)',
        'redis' => 'Redis (cache L2)', 
        'file_cache' => 'Cache de arquivos',
        'database_cache' => 'Cache de queries',
        'session_cache' => 'Cache de sessões',
        'compiled_templates' => 'Templates compilados'
    ];
    
    if (in_array('all', $requested)) {
        return array_keys($availableTypes);
    }
    
    // Filtrar apenas tipos válidos
    return array_intersect($requested, array_keys($availableTypes));
}

/**
 * Limpar um tipo específico de cache
 */
function clearCacheType(string $type, bool $force): array 
{
    switch ($type) {
        case 'apcu':
            return clearAPCuCache($force);
        case 'redis':
            return clearRedisCache($force);
        case 'file_cache':
            return clearFileCache($force);
        case 'database_cache':
            return clearDatabaseCache($force);
        case 'session_cache':
            return clearSessionCache($force);
        case 'compiled_templates':
            return clearCompiledTemplates($force);
        default:
            return [
                'success' => false,
                'error' => 'Tipo de cache desconhecido: ' . $type,
                'details' => [],
                'items_cleared' => 0,
                'warnings' => []
            ];
    }
}

/**
 * Limpar cache APCu
 */
function clearAPCuCache(bool $force): array 
{
    if (!extension_loaded('apcu')) {
        return [
            'success' => true,
            'details' => ['APCu não está instalado'],
            'items_cleared' => 0,
            'warnings' => ['Extensão APCu não disponível']
        ];
    }
    
    try {
        $info = apcu_cache_info(true);
        $itemsBefore = $info['num_entries'] ?? 0;
        
        $cleared = apcu_clear_cache();
        
        return [
            'success' => $cleared,
            'details' => [
                'method' => 'apcu_clear_cache()',
                'items_before' => $itemsBefore
            ],
            'items_cleared' => $cleared ? $itemsBefore : 0,
            'warnings' => []
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'details' => [],
            'items_cleared' => 0,
            'warnings' => []
        ];
    }
}

/**
 * Limpar cache Redis
 */
function clearRedisCache(bool $force): array 
{
    try {
        // Tentar conectar ao Redis se disponível
        if (!class_exists('Redis')) {
            return [
                'success' => true,
                'details' => ['Redis não está instalado'],
                'items_cleared' => 0,
                'warnings' => ['Extensão Redis não disponível']
            ];
        }
        
        $redis = new Redis();
        $connected = $redis->connect('127.0.0.1', 6379);
        
        if (!$connected) {
            return [
                'success' => true,
                'details' => ['Redis não está rodando'],
                'items_cleared' => 0,
                'warnings' => ['Servidor Redis não acessível']
            ];
        }
        
        // Contar chaves antes
        $keysBefore = $redis->dbSize();
        
        // Limpar cache do dashboard (prefixo específico)
        $pattern = 'dashboard:*';
        $keys = $redis->keys($pattern);
        $cleared = 0;
        
        foreach ($keys as $key) {
            if ($redis->del($key)) {
                $cleared++;
            }
        }
        
        $redis->close();
        
        return [
            'success' => true,
            'details' => [
                'method' => 'del por pattern',
                'pattern' => $pattern,
                'keys_before' => $keysBefore
            ],
            'items_cleared' => $cleared,
            'warnings' => []
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'details' => [],
            'items_cleared' => 0,
            'warnings' => []
        ];
    }
}

/**
 * Limpar cache de arquivos
 */
function clearFileCache(bool $force): array 
{
    $cacheDir = dirname(__DIR__, 3) . '/data/cache/';
    
    if (!is_dir($cacheDir)) {
        return [
            'success' => true,
            'details' => ['Diretório de cache não existe'],
            'items_cleared' => 0,
            'warnings' => []
        ];
    }
    
    try {
        $files = glob($cacheDir . '*.cache');
        $cleared = 0;
        $errors = [];
        
        foreach ($files as $file) {
            if (is_file($file)) {
                if (unlink($file)) {
                    $cleared++;
                } else {
                    $errors[] = 'Não foi possível remover: ' . basename($file);
                }
            }
        }
        
        return [
            'success' => count($errors) === 0,
            'details' => [
                'directory' => $cacheDir,
                'files_found' => count($files)
            ],
            'items_cleared' => $cleared,
            'warnings' => $errors
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'details' => [],
            'items_cleared' => 0,
            'warnings' => []
        ];
    }
}

/**
 * Limpar cache de banco
 */
function clearDatabaseCache(bool $force): array 
{
    try {
        // Se houver cache específico de queries no banco
        // Por enquanto, apenas retornar sucesso já que não temos cache específico
        return [
            'success' => true,
            'details' => ['Cache de banco não implementado'],
            'items_cleared' => 0,
            'warnings' => []
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'details' => [],
            'items_cleared' => 0,
            'warnings' => []
        ];
    }
}

/**
 * Limpar cache de sessões
 */
function clearSessionCache(bool $force): array 
{
    try {
        $sessionDir = session_save_path() ?: sys_get_temp_dir();
        $sessionFiles = glob($sessionDir . '/sess_*');
        $cleared = 0;
        
        foreach ($sessionFiles as $file) {
            if (is_file($file) && filemtime($file) < (time() - 3600)) { // Apenas sessões antigas
                if (unlink($file)) {
                    $cleared++;
                }
            }
        }
        
        return [
            'success' => true,
            'details' => [
                'session_dir' => $sessionDir,
                'files_found' => count($sessionFiles)
            ],
            'items_cleared' => $cleared,
            'warnings' => []
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'details' => [],
            'items_cleared' => 0,
            'warnings' => []
        ];
    }
}

/**
 * Limpar templates compilados
 */
function clearCompiledTemplates(bool $force): array 
{
    $templatesDir = dirname(__DIR__, 3) . '/data/compiled/';
    
    if (!is_dir($templatesDir)) {
        return [
            'success' => true,
            'details' => ['Diretório de templates não existe'],
            'items_cleared' => 0,
            'warnings' => []
        ];
    }
    
    try {
        $files = glob($templatesDir . '*.php');
        $cleared = 0;
        
        foreach ($files as $file) {
            if (is_file($file) && unlink($file)) {
                $cleared++;
            }
        }
        
        return [
            'success' => true,
            'details' => [
                'directory' => $templatesDir,
                'files_found' => count($files)
            ],
            'items_cleared' => $cleared,
            'warnings' => []
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'details' => [],
            'items_cleared' => 0,
            'warnings' => []
        ];
    }
}

/**
 * Obter estatísticas de cache antes da limpeza
 */
function getCacheStatsBefore(): array 
{
    try {
        $cache = getCache();
        return $cache->getStats();
    } catch (Exception $e) {
        return [
            'error' => 'Não foi possível obter estatísticas: ' . $e->getMessage()
        ];
    }
}

/**
 * Obter estatísticas de cache após a limpeza
 */
function getCacheStatsAfter(): array 
{
    try {
        // Aguardar um momento para as estatísticas se atualizarem
        usleep(100000); // 100ms
        
        $cache = getCache();
        return $cache->getStats();
    } catch (Exception $e) {
        return [
            'error' => 'Não foi possível obter estatísticas: ' . $e->getMessage()
        ];
    }
}