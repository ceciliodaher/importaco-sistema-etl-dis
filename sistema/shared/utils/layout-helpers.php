<?php
/**
 * ================================================================================
 * SISTEMA ETL DE DI's - UTILITÁRIOS DE LAYOUT
 * Funções helper para renderização unificada de layouts
 * Padrão Visual: Expertzy (#FF002D, #091A30)
 * ================================================================================
 */

// Carregar dependências
require_once __DIR__ . '/../components/header.php';
require_once __DIR__ . '/../components/footer.php';
require_once __DIR__ . '/../config/routes.php';

/**
 * Classe principal para gerenciar layouts do sistema
 */
class LayoutManager {
    
    private $defaultConfig = [
        'page_title' => 'Sistema ETL DI\'s',
        'page_description' => 'Sistema para processamento de Declarações de Importação',
        'show_header' => true,
        'show_footer' => true,
        'show_breadcrumbs' => true,
        'show_status' => true,
        'layout_type' => 'default', // default, dashboard, simple, fullscreen
        'body_class' => '',
        'additional_css' => [],
        'additional_js' => [],
        'meta_tags' => []
    ];
    
    private $currentConfig = [];
    
    /**
     * Construtor
     */
    public function __construct($config = []) {
        $this->currentConfig = array_merge($this->defaultConfig, $config);
    }
    
    /**
     * Renderiza o início do layout (DOCTYPE até abertura do main)
     */
    public function renderLayoutStart() {
        $config = $this->currentConfig;
        $systemInfo = SystemRoutes::getSystemInfo();
        
        // Detectar status do sistema
        $systemStatus = $this->detectSystemStatus();
        
        ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    
    <!-- Title e Meta Tags -->
    <title><?= htmlspecialchars($config['page_title']) ?> | <?= htmlspecialchars($systemInfo['name']) ?></title>
    <meta name="description" content="<?= htmlspecialchars($config['page_description']) ?>">
    <meta name="keywords" content="ETL, DI, Importação, Sistema Fiscal, Expertzy">
    <meta name="author" content="<?= htmlspecialchars($systemInfo['company']) ?>">
    <meta name="robots" content="noindex, nofollow">
    
    <!-- Open Graph -->
    <meta property="og:title" content="<?= htmlspecialchars($config['page_title']) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($config['page_description']) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= htmlspecialchars($systemInfo['name']) ?>">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/images/logo-expertzy.png">
    <link rel="apple-touch-icon" href="/images/logo-expertzy.png">
    
    <!-- CSS Base -->
    <link rel="stylesheet" href="/assets/css/expertzy-theme.css">
    <link rel="stylesheet" href="/sistema/shared/assets/css/system-navigation.css">
    
    <?php
    // CSS adicional baseado no tipo de layout
    $this->renderLayoutCSS();
    
    // CSS adicional customizado
    foreach ($config['additional_css'] as $css): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($css) ?>">
    <?php endforeach; ?>
    
    <?php
    // Meta tags adicionais
    foreach ($config['meta_tags'] as $name => $content): ?>
    <meta name="<?= htmlspecialchars($name) ?>" content="<?= htmlspecialchars($content) ?>">
    <?php endforeach; ?>
    
    <!-- Preload recursos críticos -->
    <link rel="preload" href="/sistema/shared/assets/js/navigation.js" as="script">
    <link rel="preload" href="/images/logo-expertzy.png" as="image">
    
    <!-- Schema.org JSON-LD -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebApplication",
        "name": "<?= addslashes($systemInfo['name']) ?>",
        "description": "<?= addslashes($config['page_description']) ?>",
        "version": "<?= addslashes($systemInfo['version']) ?>",
        "applicationCategory": "BusinessApplication",
        "operatingSystem": "Web Browser",
        "offers": {
            "@type": "Offer",
            "price": "0",
            "priceCurrency": "BRL"
        }
    }
    </script>
</head>
<body class="<?= htmlspecialchars($this->getBodyClass()) ?>" data-layout="<?= htmlspecialchars($config['layout_type']) ?>">
    
    <!-- Skip Navigation Link para Acessibilidade -->
    <a href="#main-content" class="skip-nav">Pular para o conteúdo principal</a>
    
    <?php
    // Renderizar header se habilitado
    if ($config['show_header']) {
        $headerConfig = [
            'show_breadcrumbs' => $config['show_breadcrumbs'],
            'show_status' => $config['show_status'],
            'current_url' => $_SERVER['REQUEST_URI'] ?? '/',
            'system_status' => $systemStatus,
            'custom_title' => $config['page_title']
        ];
        
        renderSystemHeader($headerConfig);
    }
    ?>
    
    <!-- Início do conteúdo principal -->
    <main id="main-content" class="main-content <?= $config['layout_type'] ?>-layout" role="main">
        
        <?php if ($config['layout_type'] === 'dashboard'): ?>
        <div class="dashboard-wrapper">
        <?php elseif ($config['layout_type'] === 'fullscreen'): ?>
        <div class="fullscreen-wrapper">
        <?php else: ?>
        <div class="container-fluid">
        <?php endif; ?>
        
        <?php
    }
    
    /**
     * Renderiza o fim do layout (fechamento do main até </html>)
     */
    public function renderLayoutEnd() {
        $config = $this->currentConfig;
        ?>
        
        </div> <!-- Fim wrapper -->
    </main>
    
    <?php
    // Renderizar footer se habilitado
    if ($config['show_footer']) {
        $footerConfig = [
            'compact' => $config['layout_type'] === 'simple'
        ];
        
        renderSystemFooter($footerConfig);
    }
    ?>
    
    <!-- JavaScript Base -->
    <script src="/sistema/shared/assets/js/navigation.js"></script>
    
    <?php
    // JavaScript adicional baseado no tipo de layout
    $this->renderLayoutJS();
    
    // JavaScript adicional customizado
    foreach ($config['additional_js'] as $js): ?>
    <script src="<?= htmlspecialchars($js) ?>"></script>
    <?php endforeach; ?>
    
    <!-- Inicialização do Sistema -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar navegação
        if (typeof initSystemNavigation === 'function') {
            initSystemNavigation();
        }
        
        // Configurações específicas do layout
        window.systemLayout = {
            type: '<?= addslashes($config['layout_type']) ?>',
            hasHeader: <?= $config['show_header'] ? 'true' : 'false' ?>,
            hasFooter: <?= $config['show_footer'] ? 'true' : 'false' ?>,
            hasBreadcrumbs: <?= $config['show_breadcrumbs'] ? 'true' : 'false' ?>
        };
        
        // Log de debug
        console.log('Layout Expertzy carregado:', window.systemLayout);
        
        // Evento customizado de layout pronto
        window.dispatchEvent(new CustomEvent('expertzyLayoutReady', {
            detail: window.systemLayout
        }));
    });
    </script>
    
    <!-- Analytics e Tracking -->
    <?php $this->renderAnalytics(); ?>
    
</body>
</html>
        <?php
    }
    
    /**
     * Renderiza CSS específico do layout
     */
    private function renderLayoutCSS() {
        $layoutType = $this->currentConfig['layout_type'];
        
        switch ($layoutType) {
            case 'dashboard':
                echo '<link rel="stylesheet" href="/sistema/dashboard/assets/css/dashboard.css">';
                echo '<link rel="stylesheet" href="/sistema/dashboard/assets/css/charts.css">';
                break;
                
            case 'simple':
                echo '<style>.system-header { box-shadow: none; } .footer-main { display: none; }</style>';
                break;
                
            case 'fullscreen':
                echo '<style>body { overflow: hidden; } .main-content { height: 100vh; }</style>';
                break;
        }
    }
    
    /**
     * Renderiza JavaScript específico do layout
     */
    private function renderLayoutJS() {
        $layoutType = $this->currentConfig['layout_type'];
        
        switch ($layoutType) {
            case 'dashboard':
                // Dashboard JS will be loaded via additional_js to prevent duplicates
                // echo '<script src="/sistema/dashboard/assets/js/dashboard.js"></script>';
                // echo '<script src="/sistema/dashboard/assets/js/charts.js"></script>';
                break;
        }
    }
    
    /**
     * Gera classe CSS para o body
     */
    private function getBodyClass() {
        $classes = [];
        
        // Classe base do layout
        $classes[] = $this->currentConfig['layout_type'] . '-layout';
        
        // Classes adicionais
        if (!empty($this->currentConfig['body_class'])) {
            $classes[] = $this->currentConfig['body_class'];
        }
        
        // Classe baseada na página atual
        $currentUrl = $_SERVER['REQUEST_URI'] ?? '/';
        if (strpos($currentUrl, '/dashboard/') !== false) {
            $classes[] = 'page-dashboard';
        } elseif (strpos($currentUrl, '/modules/') !== false) {
            $classes[] = 'page-modules';
        } elseif (strpos($currentUrl, '/config/') !== false) {
            $classes[] = 'page-config';
        }
        
        return implode(' ', $classes);
    }
    
    /**
     * Detecta status atual do sistema
     */
    private function detectSystemStatus() {
        try {
            // Verificar se existe classe de database
            if (file_exists(__DIR__ . '/../../config/database.php')) {
                require_once __DIR__ . '/../../config/database.php';
                
                if (function_exists('getDatabase')) {
                    $db = getDatabase();
                    return $db->testConnection() ? 'online' : 'offline';
                }
            }
            
            return 'offline';
        } catch (Exception $e) {
            return 'offline';
        }
    }
    
    /**
     * Renderiza scripts de analytics
     */
    private function renderAnalytics() {
        // Placeholder para scripts de analytics futuros
        // Google Analytics, etc.
    }
}

/**
 * Função helper principal para renderizar layout completo
 */
function renderSystemLayout($config = [], $contentCallback = null) {
    $layout = new LayoutManager($config);
    
    // Renderizar início do layout
    $layout->renderLayoutStart();
    
    // Executar callback de conteúdo se fornecido
    if (is_callable($contentCallback)) {
        $contentCallback();
    }
    
    // Renderizar fim do layout
    $layout->renderLayoutEnd();
}

/**
 * Função helper para layout de dashboard
 */
function renderDashboardLayout($config = [], $contentCallback = null) {
    $dashboardConfig = array_merge([
        'layout_type' => 'dashboard',
        'additional_css' => [
            '/sistema/dashboard/assets/css/dashboard.css',
            '/sistema/dashboard/assets/css/charts.css'
        ],
        'additional_js' => [
            '/sistema/dashboard/assets/js/dashboard.js',
            '/sistema/dashboard/assets/js/charts.js'
        ]
    ], $config);
    
    renderSystemLayout($dashboardConfig, $contentCallback);
}

/**
 * Função helper para layout simples
 */
function renderSimpleLayout($config = [], $contentCallback = null) {
    $simpleConfig = array_merge([
        'layout_type' => 'simple',
        'show_breadcrumbs' => false
    ], $config);
    
    renderSystemLayout($simpleConfig, $contentCallback);
}

/**
 * Função helper para layout fullscreen
 */
function renderFullscreenLayout($config = [], $contentCallback = null) {
    $fullscreenConfig = array_merge([
        'layout_type' => 'fullscreen',
        'show_header' => false,
        'show_footer' => false
    ], $config);
    
    renderSystemLayout($fullscreenConfig, $contentCallback);
}

/**
 * Função helper para incluir apenas header
 */
function includeHeader($config = []) {
    renderSystemHeader($config);
}

/**
 * Função helper para incluir apenas footer
 */
function includeFooter($config = []) {
    renderSystemFooter($config);
}

/**
 * Função para gerar meta tags dinâmicas
 */
function generateMetaTags($pageData) {
    $meta = [];
    
    if (isset($pageData['keywords'])) {
        $meta['keywords'] = $pageData['keywords'];
    }
    
    if (isset($pageData['author'])) {
        $meta['author'] = $pageData['author'];
    }
    
    if (isset($pageData['robots'])) {
        $meta['robots'] = $pageData['robots'];
    }
    
    return $meta;
}

/**
 * Função para gerar breadcrumbs dinâmicas
 */
function generateDynamicBreadcrumbs($currentPage, $customBreadcrumbs = []) {
    if (!empty($customBreadcrumbs)) {
        return $customBreadcrumbs;
    }
    
    $breadcrumbs = SystemRoutes::generateBreadcrumbs($currentPage);
    return $breadcrumbs;
}

/**
 * Função para verificar se uma feature está habilitada
 */
function isFeatureEnabled($feature) {
    $features = [
        'breadcrumbs' => true,
        'system_status' => true,
        'user_menu' => false,
        'dark_mode' => true,
        'analytics' => false
    ];
    
    return $features[$feature] ?? false;
}

/**
 * Função para obter configuração de ambiente
 */
function getEnvironmentConfig() {
    return [
        'environment' => 'development', // development, staging, production
        'debug' => true,
        'cache_enabled' => false,
        'asset_version' => '1.0.0'
    ];
}

/**
 * Função para renderizar alertas/notificações do sistema
 */
function renderSystemAlerts($alerts = []) {
    if (empty($alerts)) return;
    
    echo '<div class="system-alerts">';
    foreach ($alerts as $alert) {
        $type = $alert['type'] ?? 'info';
        $message = $alert['message'] ?? '';
        $dismissible = $alert['dismissible'] ?? true;
        
        echo '<div class="alert alert-' . htmlspecialchars($type) . ($dismissible ? ' alert-dismissible' : '') . '">';
        echo htmlspecialchars($message);
        if ($dismissible) {
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        }
        echo '</div>';
    }
    echo '</div>';
}

/**
 * Função para renderizar loading state
 */
function renderLoadingState($message = 'Carregando...') {
    echo '<div class="loading-state" id="loadingState">';
    echo '<div class="loading-spinner"></div>';
    echo '<div class="loading-message">' . htmlspecialchars($message) . '</div>';
    echo '</div>';
}
?>