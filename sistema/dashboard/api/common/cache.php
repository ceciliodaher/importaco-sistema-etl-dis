<?php
/**
 * ================================================================================
 * CACHE HELPER - Sistema de Cache Simples
 * Sistema ETL DI's - Cache em arquivo
 * ================================================================================
 */

class SimpleCache {
    private $cacheDir;
    private $ttl;
    
    public function __construct($cacheDir = null, $ttl = 300) {
        $this->cacheDir = $cacheDir ?: __DIR__ . '/../../../../data/cache/';
        $this->ttl = $ttl;
        
        // Criar diretório se não existir
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0777, true);
        }
    }
    
    public function getStats($callback = null) {
        $key = 'dashboard_stats';
        
        // Tentar obter do cache
        $cached = $this->get($key);
        if ($cached !== null) {
            return $cached;
        }
        
        // Gerar novos dados se callback fornecido
        if ($callback && is_callable($callback)) {
            $data = $callback();
            $this->set($key, $data);
            return $data;
        }
        
        return null;
    }
    
    public function getChart($type, $params, $callback = null) {
        $key = 'chart_' . $type . '_' . md5(serialize($params));
        
        // Tentar obter do cache
        $cached = $this->get($key);
        if ($cached !== null) {
            return $cached;
        }
        
        // Gerar novos dados se callback fornecido
        if ($callback && is_callable($callback)) {
            $data = $callback();
            $this->set($key, $data);
            return $data;
        }
        
        return null;
    }
    
    public function get($key) {
        $file = $this->getCacheFile($key);
        
        if (!file_exists($file)) {
            return null;
        }
        
        $data = @file_get_contents($file);
        if (!$data) {
            return null;
        }
        
        $cached = @unserialize($data);
        if (!$cached || !isset($cached['expires']) || !isset($cached['data'])) {
            return null;
        }
        
        // Verificar expiração
        if (time() > $cached['expires']) {
            @unlink($file);
            return null;
        }
        
        return $cached['data'];
    }
    
    public function set($key, $data, $ttl = null) {
        $file = $this->getCacheFile($key);
        
        $cached = [
            'expires' => time() + ($ttl ?: $this->ttl),
            'data' => $data
        ];
        
        return @file_put_contents($file, serialize($cached));
    }
    
    public function delete($key) {
        $file = $this->getCacheFile($key);
        if (file_exists($file)) {
            return @unlink($file);
        }
        return true;
    }
    
    public function clear() {
        $files = glob($this->cacheDir . '*.cache');
        foreach ($files as $file) {
            @unlink($file);
        }
        return true;
    }
    
    public function getCacheStats() {
        return [
            'l1_enabled' => false,
            'l2_enabled' => false,
            'hit_rate' => 0
        ];
    }
    
    private function getCacheFile($key) {
        return $this->cacheDir . md5($key) . '.cache';
    }
}

/**
 * Obter instância do cache
 */
function getCache() {
    static $cache = null;
    if ($cache === null) {
        $cache = new SimpleCache();
    }
    return $cache;
}

/**
 * Obter cache específico do dashboard
 */
function getDashboardCache() {
    return new SimpleCache(null, 60); // Cache de 1 minuto para dashboard
}