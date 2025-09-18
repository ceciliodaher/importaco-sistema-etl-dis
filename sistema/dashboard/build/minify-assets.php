<?php
/**
 * ================================================================================
 * ASSET MINIFICATION SCRIPT
 * Minifica CSS e JS para otimização de performance
 * ================================================================================
 */

class AssetMinifier {
    private $assetsDir;
    private $outputDir;
    
    public function __construct() {
        $this->assetsDir = __DIR__ . '/../assets';
        $this->outputDir = __DIR__ . '/../assets/dist';
        
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }
        
        if (!is_dir($this->outputDir . '/css')) {
            mkdir($this->outputDir . '/css', 0755, true);
        }
        
        if (!is_dir($this->outputDir . '/js')) {
            mkdir($this->outputDir . '/js', 0755, true);
        }
    }
    
    public function minifyAll() {
        echo "🚀 Iniciando minificação de assets...\n\n";
        
        $this->minifyCSS();
        $this->minifyJS();
        $this->generateManifest();
        
        echo "✅ Minificação concluída!\n";
    }
    
    private function minifyCSS() {
        echo "📄 Minificando arquivos CSS...\n";
        
        $cssFiles = [
            'dashboard.css' => 'dashboard.min.css',
            'charts.css' => 'charts.min.css',
            'manual-control.css' => 'manual-control.min.css',
            'advanced-features.css' => 'advanced-features.min.css'
        ];
        
        $bundledCSS = '';
        $totalOriginal = 0;
        $totalMinified = 0;
        
        foreach ($cssFiles as $source => $target) {
            $sourcePath = $this->assetsDir . '/css/' . $source;
            $targetPath = $this->outputDir . '/css/' . $target;
            
            if (!file_exists($sourcePath)) {
                echo "   ⚠️  Arquivo não encontrado: {$source}\n";
                continue;
            }
            
            $originalContent = file_get_contents($sourcePath);
            $minifiedContent = $this->minifyCSS_content($originalContent);
            
            file_put_contents($targetPath, $minifiedContent);
            
            $originalSize = strlen($originalContent);
            $minifiedSize = strlen($minifiedContent);
            $savings = round((($originalSize - $minifiedSize) / $originalSize) * 100, 1);
            
            echo "   ✓ {$source} → {$target} ({$savings}% menor)\n";
            
            $bundledCSS .= "/* {$source} */\n" . $minifiedContent . "\n";
            $totalOriginal += $originalSize;
            $totalMinified += $minifiedSize;
        }
        
        // Criar bundle único
        file_put_contents($this->outputDir . '/css/bundle.min.css', $bundledCSS);
        
        $totalSavings = round((($totalOriginal - $totalMinified) / $totalOriginal) * 100, 1);
        echo "   📦 Bundle CSS criado: " . number_format($totalMinified) . " bytes ({$totalSavings}% economia)\n\n";
    }
    
    private function minifyJS() {
        echo "📄 Minificando arquivos JavaScript...\n";
        
        $jsFiles = [
            'dashboard.js' => 'dashboard.min.js',
            'charts.js' => 'charts.min.js',
            'upload.js' => 'upload.min.js',
            'manual-control.js' => 'manual-control.min.js',
            'manual-control-system.js' => 'manual-control-system.min.js',
            'database-management.js' => 'database-management.min.js',
            'dashboard-integration.js' => 'dashboard-integration.min.js',
            'charts-extensions.js' => 'charts-extensions.min.js'
        ];
        
        $bundledJS = '';
        $totalOriginal = 0;
        $totalMinified = 0;
        
        foreach ($jsFiles as $source => $target) {
            $sourcePath = $this->assetsDir . '/js/' . $source;
            $targetPath = $this->outputDir . '/js/' . $target;
            
            if (!file_exists($sourcePath)) {
                echo "   ⚠️  Arquivo não encontrado: {$source}\n";
                continue;
            }
            
            $originalContent = file_get_contents($sourcePath);
            $minifiedContent = $this->minifyJS_content($originalContent);
            
            file_put_contents($targetPath, $minifiedContent);
            
            $originalSize = strlen($originalContent);
            $minifiedSize = strlen($minifiedContent);
            $savings = round((($originalSize - $minifiedSize) / $originalSize) * 100, 1);
            
            echo "   ✓ {$source} → {$target} ({$savings}% menor)\n";
            
            $bundledJS .= "/* {$source} */\n" . $minifiedContent . "\n";
            $totalOriginal += $originalSize;
            $totalMinified += $minifiedSize;
        }
        
        // Criar bundle único
        file_put_contents($this->outputDir . '/js/bundle.min.js', $bundledJS);
        
        $totalSavings = round((($totalOriginal - $totalMinified) / $totalOriginal) * 100, 1);
        echo "   📦 Bundle JS criado: " . number_format($totalMinified) . " bytes ({$totalSavings}% economia)\n\n";
    }
    
    private function minifyCSS_content($css) {
        // Remove comentários
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        
        // Remove espaços em branco desnecessários
        $css = preg_replace('/\s+/', ' ', $css);
        
        // Remove espaços antes e depois de caracteres especiais
        $css = preg_replace('/\s*([{}:;,>+~])\s*/', '$1', $css);
        
        // Remove ponto e vírgula antes de }
        $css = str_replace(';}', '}', $css);
        
        // Remove espaços no início e fim
        $css = trim($css);
        
        return $css;
    }
    
    private function minifyJS_content($js) {
        // Remove comentários de linha
        $js = preg_replace('/\/\/.*$/m', '', $js);
        
        // Remove comentários de bloco (preservando strings)
        $js = preg_replace('/\/\*[\s\S]*?\*\//', '', $js);
        
        // Remove espaços em branco extras
        $js = preg_replace('/\s+/', ' ', $js);
        
        // Remove espaços antes e depois de operadores
        $js = preg_replace('/\s*([{}:;,()=+\-*\/])\s*/', '$1', $js);
        
        // Remove ponto e vírgula desnecessário antes de }
        $js = str_replace(';}', '}', $js);
        
        // Remove espaços no início e fim
        $js = trim($js);
        
        return $js;
    }
    
    private function generateManifest() {
        echo "📋 Gerando manifest de assets...\n";
        
        $manifest = [
            'version' => date('Y-m-d-H-i-s'),
            'css' => [
                'bundle' => 'assets/dist/css/bundle.min.css',
                'individual' => []
            ],
            'js' => [
                'bundle' => 'assets/dist/js/bundle.min.js',
                'individual' => []
            ],
            'sizes' => []
        ];
        
        // CSS individual
        $cssFiles = glob($this->outputDir . '/css/*.min.css');
        foreach ($cssFiles as $file) {
            $name = basename($file);
            if ($name !== 'bundle.min.css') {
                $manifest['css']['individual'][] = 'assets/dist/css/' . $name;
                $manifest['sizes'][$name] = filesize($file);
            }
        }
        
        // JS individual
        $jsFiles = glob($this->outputDir . '/js/*.min.js');
        foreach ($jsFiles as $file) {
            $name = basename($file);
            if ($name !== 'bundle.min.js') {
                $manifest['js']['individual'][] = 'assets/dist/js/' . $name;
                $manifest['sizes'][$name] = filesize($file);
            }
        }
        
        // Tamanhos dos bundles
        $manifest['sizes']['bundle.min.css'] = filesize($this->outputDir . '/css/bundle.min.css');
        $manifest['sizes']['bundle.min.js'] = filesize($this->outputDir . '/js/bundle.min.js');
        
        file_put_contents($this->outputDir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
        
        echo "   ✓ Manifest criado: assets/dist/manifest.json\n";
        echo "   📊 Bundle CSS: " . number_format($manifest['sizes']['bundle.min.css']) . " bytes\n";
        echo "   📊 Bundle JS: " . number_format($manifest['sizes']['bundle.min.js']) . " bytes\n\n";
    }
}

// Executar se chamado diretamente
if (php_sapi_name() === 'cli') {
    $minifier = new AssetMinifier();
    $minifier->minifyAll();
}