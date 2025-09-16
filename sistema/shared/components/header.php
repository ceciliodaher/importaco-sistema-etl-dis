<?php
/**
 * ================================================================================
 * SISTEMA ETL DE DI's - HEADER UNIFICADO
 * Componente reutilizável para navegação principal e breadcrumbs
 * Padrão Visual: Expertzy (#FF002D, #091A30)
 * ================================================================================
 */

// Carregar configurações de rotas se não estiver carregado
if (!class_exists('SystemRoutes')) {
    require_once __DIR__ . '/../config/routes.php';
}

/**
 * Renderiza o header unificado do sistema
 * 
 * @param array $options Opções de configuração do header
 * @return void
 */
function renderSystemHeader($options = []) {
    // Configurações padrão
    $defaults = [
        'show_breadcrumbs' => true,
        'show_status' => true,
        'show_user_menu' => false,
        'current_url' => $_SERVER['REQUEST_URI'] ?? '/sistema/dashboard/',
        'system_status' => 'online', // online, offline, maintenance
        'custom_title' => null,
        'logo_url' => '/index.html'
    ];
    
    $config = array_merge($defaults, $options);
    
    // Obter dados de navegação
    $navigation = SystemRoutes::getMainNavigation();
    $icons = SystemRoutes::getNavigationIcons();
    $breadcrumbs = SystemRoutes::generateBreadcrumbs($config['current_url']);
    $activePage = SystemRoutes::detectActivePage($config['current_url']);
    $statusConfig = SystemRoutes::getSystemStatusConfig();
    $systemInfo = SystemRoutes::getSystemInfo();
    
    // Determinar título da página
    $pageTitle = $config['custom_title'] ?? $systemInfo['name'];
    
    // Status do sistema
    $systemStatus = $statusConfig[$config['system_status']] ?? $statusConfig['offline'];
?>
<header class="system-header" id="systemHeader">
    <!-- Barra superior -->
    <div class="header-top">
        <div class="container-fluid">
            <div class="header-content">
                <!-- Logo e informações do sistema -->
                <div class="header-brand">
                    <a href="<?= htmlspecialchars($config['logo_url']) ?>" class="brand-link">
                        <img src="/images/logo-expertzy.png" alt="Expertzy" class="brand-logo">
                        <div class="brand-info">
                            <h1 class="brand-title"><?= htmlspecialchars($pageTitle) ?></h1>
                            <span class="brand-version"><?= htmlspecialchars($systemInfo['version']) ?></span>
                        </div>
                    </a>
                </div>

                <!-- Navegação principal -->
                <nav class="main-nav" role="navigation">
                    <ul class="nav-list">
                        <?php foreach ($navigation as $key => $item): ?>
                        <li class="nav-item <?= $activePage === $key ? 'active' : '' ?> <?= isset($item['dropdown']) ? 'has-dropdown' : '' ?>">
                            <?php if (isset($item['dropdown'])): ?>
                                <a href="#" class="nav-link dropdown-toggle" data-dropdown="<?= $key ?>">
                                    <span class="nav-icon"><?= $icons[$item['icon']] ?? '' ?></span>
                                    <span class="nav-label"><?= htmlspecialchars($item['label']) ?></span>
                                    <span class="dropdown-arrow"><?= $icons['chevron-down'] ?></span>
                                </a>
                                <div class="dropdown-menu" id="dropdown-<?= $key ?>">
                                    <?php foreach ($item['dropdown'] as $subKey => $subItem): ?>
                                    <a href="<?= htmlspecialchars($subItem['url']) ?>" class="dropdown-item">
                                        <div class="dropdown-item-content">
                                            <span class="dropdown-item-label"><?= htmlspecialchars($subItem['label']) ?></span>
                                            <?php if (isset($subItem['description'])): ?>
                                            <span class="dropdown-item-desc"><?= htmlspecialchars($subItem['description']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <a href="<?= htmlspecialchars($item['url']) ?>" class="nav-link">
                                    <span class="nav-icon"><?= $icons[$item['icon']] ?? '' ?></span>
                                    <span class="nav-label"><?= htmlspecialchars($item['label']) ?></span>
                                </a>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </nav>

                <!-- Ações do header -->
                <div class="header-actions">
                    <?php if ($config['show_status']): ?>
                    <div class="system-status-indicator">
                        <div class="status-icon <?= $systemStatus['class'] ?>" title="Sistema <?= $systemStatus['label'] ?>">
                            <div class="status-dot"></div>
                        </div>
                        <span class="status-text d-none d-md-inline"><?= $systemStatus['label'] ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ($config['show_user_menu']): ?>
                    <div class="user-menu">
                        <button class="user-menu-toggle" data-dropdown="user">
                            <div class="user-avatar">
                                <span>U</span>
                            </div>
                            <span class="user-name d-none d-lg-inline">Usuário</span>
                            <span class="dropdown-arrow"><?= $icons['chevron-down'] ?></span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-right" id="dropdown-user">
                            <a href="#" class="dropdown-item">Perfil</a>
                            <a href="#" class="dropdown-item">Configurações</a>
                            <div class="dropdown-divider"></div>
                            <a href="#" class="dropdown-item">Sair</a>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Toggle mobile menu -->
                    <button class="mobile-menu-toggle d-lg-none" id="mobileMenuToggle">
                        <span class="hamburger-line"></span>
                        <span class="hamburger-line"></span>
                        <span class="hamburger-line"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php if ($config['show_breadcrumbs'] && !empty($breadcrumbs)): ?>
    <!-- Breadcrumbs -->
    <div class="breadcrumb-section">
        <div class="container-fluid">
            <nav class="breadcrumb-nav" aria-label="Navegação estrutural">
                <ol class="breadcrumb">
                    <?php foreach ($breadcrumbs as $index => $crumb): ?>
                    <li class="breadcrumb-item <?= isset($crumb['active']) && $crumb['active'] ? 'active' : '' ?>">
                        <?php if (isset($crumb['active']) && $crumb['active']): ?>
                            <span><?= htmlspecialchars($crumb['label']) ?></span>
                        <?php elseif (!empty($crumb['url'])): ?>
                            <a href="<?= htmlspecialchars($crumb['url']) ?>"><?= htmlspecialchars($crumb['label']) ?></a>
                        <?php else: ?>
                            <span><?= htmlspecialchars($crumb['label']) ?></span>
                        <?php endif; ?>
                        <?php if ($index < count($breadcrumbs) - 1): ?>
                        <span class="breadcrumb-separator"><?= $icons['chevron-right'] ?></span>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ol>
            </nav>
        </div>
    </div>
    <?php endif; ?>

    <!-- Mobile Navigation Overlay -->
    <div class="mobile-nav-overlay d-lg-none" id="mobileNavOverlay">
        <div class="mobile-nav-content">
            <div class="mobile-nav-header">
                <div class="mobile-brand">
                    <img src="/images/logo-expertzy.png" alt="Expertzy" class="mobile-logo">
                    <span class="mobile-title"><?= htmlspecialchars($pageTitle) ?></span>
                </div>
                <button class="mobile-nav-close" id="mobileNavClose">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>
            
            <nav class="mobile-nav-menu">
                <?php foreach ($navigation as $key => $item): ?>
                <div class="mobile-nav-item <?= $activePage === $key ? 'active' : '' ?>">
                    <?php if (isset($item['dropdown'])): ?>
                        <button class="mobile-nav-toggle" data-mobile-dropdown="<?= $key ?>">
                            <span class="nav-icon"><?= $icons[$item['icon']] ?? '' ?></span>
                            <span class="nav-label"><?= htmlspecialchars($item['label']) ?></span>
                            <span class="dropdown-arrow"><?= $icons['chevron-down'] ?></span>
                        </button>
                        <div class="mobile-dropdown" id="mobile-dropdown-<?= $key ?>">
                            <?php foreach ($item['dropdown'] as $subKey => $subItem): ?>
                            <a href="<?= htmlspecialchars($subItem['url']) ?>" class="mobile-dropdown-item">
                                <span class="dropdown-item-label"><?= htmlspecialchars($subItem['label']) ?></span>
                                <?php if (isset($subItem['description'])): ?>
                                <span class="dropdown-item-desc"><?= htmlspecialchars($subItem['description']) ?></span>
                                <?php endif; ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <a href="<?= htmlspecialchars($item['url']) ?>" class="mobile-nav-link">
                            <span class="nav-icon"><?= $icons[$item['icon']] ?? '' ?></span>
                            <span class="nav-label"><?= htmlspecialchars($item['label']) ?></span>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </nav>

            <?php if ($config['show_status']): ?>
            <div class="mobile-status">
                <div class="status-indicator">
                    <div class="status-icon <?= $systemStatus['class'] ?>">
                        <div class="status-dot"></div>
                    </div>
                    <span class="status-text">Sistema <?= $systemStatus['label'] ?></span>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</header>

<script>
// Inicialização da navegação - será expandido no navigation.js
document.addEventListener('DOMContentLoaded', function() {
    if (typeof initSystemNavigation === 'function') {
        initSystemNavigation();
    }
});
</script>
<?php
}

/**
 * Função simplificada para incluir o header
 * 
 * @param array $options Opções de configuração
 */
function includeSystemHeader($options = []) {
    renderSystemHeader($options);
}
?>