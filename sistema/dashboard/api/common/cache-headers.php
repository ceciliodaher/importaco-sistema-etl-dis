<?php
/**
 * ================================================================================
 * CACHE HEADERS OPTIMIZATION
 * Headers HTTP otimizados para performance
 * ================================================================================
 */

class CacheHeaders {
    /**
     * Set cache headers for static assets (CSS, JS, images)
     */
    public static function setStaticAssetHeaders($maxAge = 2592000) { // 30 days
        header('Cache-Control: public, max-age=' . $maxAge);
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $maxAge) . ' GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($_SERVER['SCRIPT_FILENAME'])) . ' GMT');
        
        // ETag for better caching
        $etag = md5_file($_SERVER['SCRIPT_FILENAME']);
        header('ETag: "' . $etag . '"');
        
        // Check if client has cached version
        if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === $etag) {
            http_response_code(304);
            exit;
        }
        
        if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
            $lastModified = filemtime($_SERVER['SCRIPT_FILENAME']);
            $ifModifiedSince = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
            
            if ($lastModified <= $ifModifiedSince) {
                http_response_code(304);
                exit;
            }
        }
    }
    
    /**
     * Set cache headers for API responses
     */
    public static function setAPIHeaders($maxAge = 300) { // 5 minutes
        header('Cache-Control: public, max-age=' . $maxAge);
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $maxAge) . ' GMT');
        header('Content-Type: application/json; charset=utf-8');
        
        // CORS headers for dashboard
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        
        // Security headers
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
    }
    
    /**
     * Set cache headers for dynamic content
     */
    public static function setDynamicHeaders($maxAge = 60) { // 1 minute
        header('Cache-Control: public, max-age=' . $maxAge);
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $maxAge) . ' GMT');
        header('Content-Type: application/json; charset=utf-8');
        
        // Vary header for better caching
        header('Vary: Accept-Encoding, User-Agent');
    }
    
    /**
     * Set no-cache headers for sensitive data
     */
    public static function setNoCacheHeaders() {
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Content-Type: application/json; charset=utf-8');
    }
    
    /**
     * Enable compression if supported
     */
    public static function enableCompression() {
        if (!headers_sent() && 
            isset($_SERVER['HTTP_ACCEPT_ENCODING']) && 
            strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false &&
            extension_loaded('zlib')) {
            
            ob_start('ob_gzhandler');
        }
    }
    
    /**
     * Set performance headers for HTML pages
     */
    public static function setHTMLHeaders() {
        header('Content-Type: text/html; charset=utf-8');
        
        // Preload critical resources
        header('Link: </assets/dist/css/bundle.min.css>; rel=preload; as=style', false);
        header('Link: </assets/dist/js/bundle.min.js>; rel=preload; as=script', false);
        header('Link: <https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js>; rel=preload; as=script; crossorigin', false);
        
        // Resource hints
        header('Link: <https://cdn.jsdelivr.net>; rel=dns-prefetch', false);
        
        // Security headers
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Performance hints
        header('Timing-Allow-Origin: *');
    }
}

/**
 * APCu Cache Implementation for Data Caching
 */
class APCuCache {
    /**
     * Get cached data
     */
    public static function get($key) {
        if (!extension_loaded('apcu') || !apcu_enabled()) {
            return false;
        }
        
        return apcu_fetch($key);
    }
    
    /**
     * Set cached data
     */
    public static function set($key, $data, $ttl = 300) {
        if (!extension_loaded('apcu') || !apcu_enabled()) {
            return false;
        }
        
        return apcu_store($key, $data, $ttl);
    }
    
    /**
     * Delete cached data
     */
    public static function delete($key) {
        if (!extension_loaded('apcu') || !apcu_enabled()) {
            return false;
        }
        
        return apcu_delete($key);
    }
    
    /**
     * Clear all cache
     */
    public static function clear() {
        if (!extension_loaded('apcu') || !apcu_enabled()) {
            return false;
        }
        
        return apcu_clear_cache();
    }
    
    /**
     * Get cache info
     */
    public static function info() {
        if (!extension_loaded('apcu') || !apcu_enabled()) {
            return false;
        }
        
        return apcu_cache_info();
    }
    
    /**
     * Cache wrapper for database queries
     */
    public static function cacheQuery($key, $callback, $ttl = 300) {
        $cached = self::get($key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $result = $callback();
        self::set($key, $result, $ttl);
        
        return $result;
    }
}

/**
 * Content Compression Helper
 */
class ContentCompression {
    /**
     * Compress CSS content
     */
    public static function compressCSS($css) {
        // Remove comments
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        
        // Remove whitespace
        $css = preg_replace('/\s+/', ' ', $css);
        $css = preg_replace('/\s*([{}:;,>+~])\s*/', '$1', $css);
        $css = str_replace(';}', '}', $css);
        
        return trim($css);
    }
    
    /**
     * Compress JavaScript content
     */
    public static function compressJS($js) {
        // Simple compression (for production, use tools like UglifyJS)
        $js = preg_replace('/\/\/.*$/m', '', $js);
        $js = preg_replace('/\/\*[\s\S]*?\*\//', '', $js);
        $js = preg_replace('/\s+/', ' ', $js);
        $js = trim($js);
        
        return $js;
    }
    
    /**
     * Compress JSON response
     */
    public static function compressJSON($data) {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}