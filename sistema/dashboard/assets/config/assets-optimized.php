<?php
/**
 * ================================================================================
 * CONFIGURAÇÃO DE ASSETS OTIMIZADA
 * Configuração para carregamento de assets minificados com cache
 * ================================================================================
 */

class OptimizedAssets {
    private static $manifest = null;
    private static $isProduction = false;
    
    public static function init($production = false) {
        self::$isProduction = $production;
        self::loadManifest();
    }
    
    private static function loadManifest() {
        $manifestPath = __DIR__ . '/../dist/manifest.json';
        
        if (file_exists($manifestPath)) {
            self::$manifest = json_decode(file_get_contents($manifestPath), true);
        }
    }
    
    /**
     * Get CSS assets (bundle or individual)
     */
    public static function getCSS($bundle = true) {
        if (self::$isProduction && $bundle && self::$manifest) {
            return [self::$manifest['css']['bundle']];
        }
        
        if (self::$manifest && !$bundle) {
            return self::$manifest['css']['individual'];
        }
        
        // Fallback para desenvolvimento
        return [
            '../../assets/css/expertzy-theme.css',
            'assets/css/dashboard.css',
            'assets/css/charts.css',
            'assets/css/manual-control.css',
            '../shared/assets/css/system-navigation.css'
        ];
    }
    
    /**
     * Get JavaScript assets (bundle or individual)
     */
    public static function getJS($bundle = true) {
        if (self::$isProduction && $bundle && self::$manifest) {
            return [
                'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js',
                self::$manifest['js']['bundle']
            ];
        }
        
        if (self::$manifest && !$bundle) {
            $scripts = ['https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js'];
            return array_merge($scripts, self::$manifest['js']['individual']);
        }
        
        // Fallback para desenvolvimento
        return [
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js',
            'assets/js/dashboard.js',
            'assets/js/charts.js',
            'assets/js/upload.js',
            'assets/js/charts-extensions.js',
            'assets/js/database-management.js',
            'assets/js/manual-control.js',
            'assets/js/manual-control-system.js',
            'assets/js/dashboard-integration.js'
        ];
    }
    
    /**
     * Generate preload links for critical assets
     */
    public static function getPreloadLinks() {
        $links = [];
        
        if (self::$isProduction && self::$manifest) {
            $links[] = '<link rel="preload" href="' . self::$manifest['css']['bundle'] . '" as="style">';
            $links[] = '<link rel="preload" href="' . self::$manifest['js']['bundle'] . '" as="script">';
        }
        
        $links[] = '<link rel="preload" href="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js" as="script" crossorigin>';
        $links[] = '<link rel="dns-prefetch" href="https://cdn.jsdelivr.net">';
        
        return $links;
    }
    
    /**
     * Get critical CSS inline (above the fold)
     */
    public static function getCriticalCSS() {
        $criticalCSS = "
        .dashboard-container{display:grid;grid-template-columns:320px 1fr;gap:2rem;max-width:1400px;margin:90px auto 2rem;padding:0 2rem;min-height:calc(100vh - 90px)}
        .sidebar{background:#fff;border-radius:20px;padding:2rem;height:fit-content;box-shadow:0 5px 20px rgba(9,26,48,0.08);position:sticky;top:110px;border:1px solid #e9ecef}
        .main-content{display:flex;flex-direction:column;gap:2rem}
        .upload-section{background:#fff;border-radius:20px;padding:2rem;box-shadow:0 5px 20px rgba(9,26,48,0.08)}
        .dashboard-cards{background:#fff;border-radius:20px;padding:2rem;box-shadow:0 5px 20px rgba(9,26,48,0.08)}
        .card-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:1.5rem}
        ";
        
        return '<style>' . $criticalCSS . '</style>';
    }
    
    /**
     * Get asset version for cache busting
     */
    public static function getVersion() {
        return self::$manifest['version'] ?? time();
    }
    
    /**
     * Get total bundle sizes
     */
    public static function getBundleSizes() {
        if (!self::$manifest) {
            return null;
        }
        
        return [
            'css' => self::$manifest['sizes']['bundle.min.css'] ?? 0,
            'js' => self::$manifest['sizes']['bundle.min.js'] ?? 0,
            'total' => (self::$manifest['sizes']['bundle.min.css'] ?? 0) + (self::$manifest['sizes']['bundle.min.js'] ?? 0)
        ];
    }
}