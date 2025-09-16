<?php
/**
 * ================================================================================
 * SISTEMA DE CACHE INTELIGENTE - L1 (APCu) + L2 (Redis)
 * Cache hierárquico com TTL dinâmico e invalidação por tags
 * Performance: L1 < 1ms, L2 < 5ms
 * ================================================================================
 */

/**
 * Classe de cache inteligente com dois níveis
 * L1: APCu (in-memory) para dados hot
 * L2: Redis para dados compartilhados
 */
class IntelligentCache 
{
    private static $instance = null;
    private $redis = null;
    private $apcu_enabled = false;
    private $redis_enabled = false;
    private $stats = [
        'hits' => 0,
        'misses' => 0,
        'l1_hits' => 0,
        'l2_hits' => 0,
        'writes' => 0
    ];

    private function __construct() 
    {
        $this->initializeCache();
    }

    public static function getInstance() 
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Inicializar sistemas de cache
     */
    private function initializeCache() 
    {
        // Verificar APCu
        $this->apcu_enabled = extension_loaded('apcu') && apcu_enabled();
        
        // Verificar e conectar Redis
        if (extension_loaded('redis')) {
            try {
                $this->redis = new Redis();
                $this->redis->connect('127.0.0.1', 6379);
                $this->redis->select(1); // DB específico para dashboard
                $this->redis_enabled = true;
            } catch (RedisException $e) {
                error_log("Redis não disponível: " . $e->getMessage());
                $this->redis_enabled = false;
            }
        }
    }

    /**
     * Obter valor do cache com TTL dinâmico
     * 
     * @param string $key Chave do cache
     * @param callable $callback Função para gerar dados se não existir
     * @param int $ttl TTL em segundos (padrão: 300)
     * @param array $tags Tags para invalidação
     * @return mixed
     */
    public function get(string $key, callable $callback = null, int $ttl = 300, array $tags = []) 
    {
        $start_time = microtime(true);
        
        // Tentar L1 (APCu) primeiro
        if ($this->apcu_enabled) {
            $value = apcu_fetch($key, $success);
            if ($success) {
                $this->stats['hits']++;
                $this->stats['l1_hits']++;
                return $this->unserializeValue($value);
            }
        }

        // Tentar L2 (Redis)
        if ($this->redis_enabled) {
            try {
                $value = $this->redis->get($key);
                if ($value !== false) {
                    $this->stats['hits']++;
                    $this->stats['l2_hits']++;
                    
                    // Armazenar também no L1 para próxima consulta
                    if ($this->apcu_enabled) {
                        apcu_store($key, $value, min($ttl, 300)); // L1 máximo 5 min
                    }
                    
                    return $this->unserializeValue($value);
                }
            } catch (RedisException $e) {
                error_log("Erro Redis get: " . $e->getMessage());
            }
        }

        // Se não encontrou e tem callback, gerar dados
        if ($callback !== null) {
            $this->stats['misses']++;
            $value = $callback();
            
            if ($value !== null) {
                $this->set($key, $value, $ttl, $tags);
            }
            
            return $value;
        }

        $this->stats['misses']++;
        return null;
    }

    /**
     * Armazenar valor no cache
     */
    public function set(string $key, $value, int $ttl = 300, array $tags = []) 
    {
        $serialized = $this->serializeValue($value);
        $this->stats['writes']++;

        // Armazenar no L1 (APCu)
        if ($this->apcu_enabled) {
            apcu_store($key, $serialized, min($ttl, 300)); // L1 máximo 5 min
        }

        // Armazenar no L2 (Redis)
        if ($this->redis_enabled) {
            try {
                $this->redis->setex($key, $ttl, $serialized);
                
                // Associar tags se fornecidas
                if (!empty($tags)) {
                    $this->associateTags($key, $tags, $ttl);
                }
                
            } catch (RedisException $e) {
                error_log("Erro Redis set: " . $e->getMessage());
            }
        }

        return true;
    }

    /**
     * Deletar chave específica
     */
    public function delete(string $key) 
    {
        // Deletar do L1
        if ($this->apcu_enabled) {
            apcu_delete($key);
        }

        // Deletar do L2
        if ($this->redis_enabled) {
            try {
                $this->redis->del($key);
            } catch (RedisException $e) {
                error_log("Erro Redis delete: " . $e->getMessage());
            }
        }

        return true;
    }

    /**
     * Invalidar cache por tags
     */
    public function invalidateByTag(string $tag) 
    {
        if (!$this->redis_enabled) {
            return false;
        }

        try {
            // Obter todas as chaves associadas à tag
            $tagKey = "tag:{$tag}";
            $keys = $this->redis->smembers($tagKey);
            
            if (!empty($keys)) {
                // Deletar todas as chaves
                $this->redis->del($keys);
                
                // Deletar também do APCu se possível
                if ($this->apcu_enabled) {
                    foreach ($keys as $key) {
                        apcu_delete($key);
                    }
                }
            }
            
            // Deletar o set de tags
            $this->redis->del($tagKey);
            
            return count($keys);
            
        } catch (RedisException $e) {
            error_log("Erro invalidateByTag: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Warming de cache para queries críticas
     */
    public function warmCache(array $queries) 
    {
        $warmed = 0;
        
        foreach ($queries as $query) {
            $key = $query['key'];
            $callback = $query['callback'];
            $ttl = $query['ttl'] ?? 300;
            $tags = $query['tags'] ?? [];
            
            // Só fazer warm se não existir
            if ($this->get($key) === null) {
                try {
                    $value = $callback();
                    if ($value !== null) {
                        $this->set($key, $value, $ttl, $tags);
                        $warmed++;
                    }
                } catch (Exception $e) {
                    error_log("Erro no warming de {$key}: " . $e->getMessage());
                }
            }
        }
        
        return $warmed;
    }

    /**
     * Obter estatísticas de cache
     */
    public function getStats() 
    {
        $total_requests = $this->stats['hits'] + $this->stats['misses'];
        $hit_rate = $total_requests > 0 ? ($this->stats['hits'] / $total_requests) * 100 : 0;
        
        $stats = array_merge($this->stats, [
            'hit_rate' => round($hit_rate, 2),
            'l1_enabled' => $this->apcu_enabled,
            'l2_enabled' => $this->redis_enabled
        ]);

        // Estatísticas do Redis se disponível
        if ($this->redis_enabled) {
            try {
                $redis_info = $this->redis->info('memory');
                $stats['redis_memory'] = $redis_info['used_memory_human'] ?? 'N/A';
            } catch (RedisException $e) {
                $stats['redis_memory'] = 'Error';
            }
        }

        return $stats;
    }

    /**
     * Limpar todo o cache
     */
    public function flush() 
    {
        $cleared = 0;

        // Limpar APCu
        if ($this->apcu_enabled) {
            if (apcu_clear_cache()) {
                $cleared++;
            }
        }

        // Limpar Redis
        if ($this->redis_enabled) {
            try {
                if ($this->redis->flushDB()) {
                    $cleared++;
                }
            } catch (RedisException $e) {
                error_log("Erro Redis flush: " . $e->getMessage());
            }
        }

        return $cleared;
    }

    /**
     * Associar chaves com tags
     */
    private function associateTags(string $key, array $tags, int $ttl) 
    {
        foreach ($tags as $tag) {
            $tagKey = "tag:{$tag}";
            $this->redis->sadd($tagKey, $key);
            $this->redis->expire($tagKey, $ttl + 60); // Tag expira 1 min depois
        }
    }

    /**
     * Serializar valor para cache
     */
    private function serializeValue($value) 
    {
        return serialize([
            'data' => $value,
            'timestamp' => time(),
            'type' => gettype($value)
        ]);
    }

    /**
     * Deserializar valor do cache
     */
    private function unserializeValue($serialized) 
    {
        $data = unserialize($serialized);
        return $data['data'] ?? null;
    }
}

/**
 * Classe para cache específico do Dashboard
 * TTLs otimizados para cada tipo de dado
 */
class DashboardCache 
{
    private $cache;
    
    // TTLs otimizados por tipo de dado
    const TTL_STATS = 60;           // Estatísticas: 1 min
    const TTL_CHARTS = 300;         // Gráficos: 5 min
    const TTL_SEARCH = 600;         // Pesquisa: 10 min
    const TTL_EXPORTS = 1800;       // Exports: 30 min
    const TTL_REFERENCE = 3600;     // Dados de referência: 1 hora

    public function __construct() 
    {
        $this->cache = IntelligentCache::getInstance();
    }

    /**
     * Cache para estatísticas do dashboard
     */
    public function getStats(callable $callback) 
    {
        return $this->cache->get(
            'dashboard:stats',
            $callback,
            self::TTL_STATS,
            ['dashboard', 'stats']
        );
    }

    /**
     * Cache para dados de gráficos
     */
    public function getChart(string $chartType, array $params, callable $callback) 
    {
        $key = 'dashboard:chart:' . $chartType . ':' . md5(json_encode($params));
        
        return $this->cache->get(
            $key,
            $callback,
            self::TTL_CHARTS,
            ['dashboard', 'charts', $chartType]
        );
    }

    /**
     * Cache para pesquisas
     */
    public function getSearch(string $query, array $filters, callable $callback) 
    {
        $key = 'dashboard:search:' . md5($query . json_encode($filters));
        
        return $this->cache->get(
            $key,
            $callback,
            self::TTL_SEARCH,
            ['dashboard', 'search']
        );
    }

    /**
     * Cache para dados de exportação
     */
    public function getExportData(string $format, array $params, callable $callback) 
    {
        $key = 'dashboard:export:' . $format . ':' . md5(json_encode($params));
        
        return $this->cache->get(
            $key,
            $callback,
            self::TTL_EXPORTS,
            ['dashboard', 'export', $format]
        );
    }

    /**
     * Invalidar cache quando dados são atualizados
     */
    public function invalidateOnDataUpdate() 
    {
        $this->cache->invalidateByTag('dashboard');
        $this->cache->invalidateByTag('stats');
        $this->cache->invalidateByTag('charts');
    }

    /**
     * Warming do cache do dashboard
     */
    public function warmDashboardCache(PDO $db) 
    {
        $queries = [
            [
                'key' => 'dashboard:stats',
                'callback' => function() use ($db) {
                    return $this->generateStatsData($db);
                },
                'ttl' => self::TTL_STATS,
                'tags' => ['dashboard', 'stats']
            ],
            [
                'key' => 'dashboard:chart:evolution',
                'callback' => function() use ($db) {
                    return $this->generateEvolutionChart($db);
                },
                'ttl' => self::TTL_CHARTS,
                'tags' => ['dashboard', 'charts']
            ]
        ];

        return $this->cache->warmCache($queries);
    }

    /**
     * Gerar dados de estatísticas
     */
    private function generateStatsData(PDO $db) 
    {
        $stmt = $db->query("
            SELECT 
                COUNT(DISTINCT di.id) as total_dis,
                COUNT(DISTINCT a.id) as total_adicoes,
                SUM(CASE WHEN di.situacao = 'DESEMBARCADA' THEN 1 ELSE 0 END) as dis_desembarcadas,
                ROUND(SUM(a.valor_total_item_usd), 2) as valor_total_usd,
                COUNT(DISTINCT di.importador_cnpj) as importadores_distintos
            FROM declaracoes_importacao di
            LEFT JOIN adicoes a ON di.id = a.declaracao_id
            WHERE di.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        ");
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Gerar dados do gráfico de evolução
     */
    private function generateEvolutionChart(PDO $db) 
    {
        $stmt = $db->query("
            SELECT 
                DATE_FORMAT(data_registro_di, '%Y-%m') as periodo,
                COUNT(*) as quantidade,
                ROUND(SUM(valor_total_usd), 2) as valor_total
            FROM declaracoes_importacao
            WHERE data_registro_di >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(data_registro_di, '%Y-%m')
            ORDER BY periodo ASC
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

/**
 * Função helper para obter instância do cache
 */
function getCache() {
    return IntelligentCache::getInstance();
}

/**
 * Função helper para obter cache do dashboard
 */
function getDashboardCache() {
    return new DashboardCache();
}