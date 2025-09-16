<?php
/**
 * ================================================================================
 * SISTEMA ETL DE DI's - FOOTER UNIFICADO
 * Componente reutilizável para rodapé com informações do sistema
 * Padrão Visual: Expertzy (#FF002D, #091A30)
 * ================================================================================
 */

// Carregar configurações de rotas se não estiver carregado
if (!class_exists('SystemRoutes')) {
    require_once __DIR__ . '/../config/routes.php';
}

/**
 * Renderiza o footer unificado do sistema
 * 
 * @param array $options Opções de configuração do footer
 * @return void
 */
function renderSystemFooter($options = []) {
    // Configurações padrão
    $defaults = [
        'show_modules' => true,
        'show_system_info' => true,
        'show_support_links' => true,
        'show_version' => true,
        'additional_links' => [],
        'compact' => false // Versão compacta para páginas específicas
    ];
    
    $config = array_merge($defaults, $options);
    
    // Obter dados do sistema
    $systemInfo = SystemRoutes::getSystemInfo();
    $navigation = SystemRoutes::getMainNavigation();
    $icons = SystemRoutes::getNavigationIcons();
    
    // Status dos módulos (simulado - pode ser integrado com verificação real)
    $moduleStatus = [
        'fiscal' => 'active',
        'commercial' => 'active', 
        'accounting' => 'active',
        'billing' => 'active'
    ];
?>
<footer class="system-footer <?= $config['compact'] ? 'footer-compact' : '' ?>" id="systemFooter">
    <div class="container-fluid">
        
        <?php if (!$config['compact']): ?>
        <!-- Seção principal do footer -->
        <div class="footer-main">
            <div class="row">
                
                <?php if ($config['show_modules']): ?>
                <!-- Links dos módulos -->
                <div class="col-lg-3 col-md-6">
                    <div class="footer-section">
                        <h4 class="footer-title">Módulos do Sistema</h4>
                        <ul class="footer-links">
                            <?php if (isset($navigation['modules']['dropdown'])): ?>
                                <?php foreach ($navigation['modules']['dropdown'] as $key => $module): ?>
                                <li class="footer-link-item">
                                    <a href="<?= htmlspecialchars($module['url']) ?>" class="footer-link">
                                        <span class="module-status <?= $moduleStatus[$key] ?? 'inactive' ?>"></span>
                                        <span class="link-text"><?= htmlspecialchars($module['label']) ?></span>
                                    </a>
                                    <?php if (isset($module['description'])): ?>
                                    <small class="link-description"><?= htmlspecialchars($module['description']) ?></small>
                                    <?php endif; ?>
                                </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($config['show_support_links']): ?>
                <!-- Links de suporte -->
                <div class="col-lg-3 col-md-6">
                    <div class="footer-section">
                        <h4 class="footer-title">Suporte & Documentação</h4>
                        <ul class="footer-links">
                            <li class="footer-link-item">
                                <a href="<?= htmlspecialchars($systemInfo['documentation_url']) ?>" class="footer-link">
                                    <span class="link-icon"><?= $icons['reports'] ?></span>
                                    <span class="link-text">Documentação API</span>
                                </a>
                            </li>
                            <li class="footer-link-item">
                                <a href="<?= htmlspecialchars($systemInfo['support_url']) ?>" class="footer-link">
                                    <span class="link-icon"><?= $icons['settings'] ?></span>
                                    <span class="link-text">Manual do Usuário</span>
                                </a>
                            </li>
                            <li class="footer-link-item">
                                <a href="/sistema/config/" class="footer-link">
                                    <span class="link-icon"><?= $icons['settings'] ?></span>
                                    <span class="link-text">Configurações</span>
                                </a>
                            </li>
                            <li class="footer-link-item">
                                <a href="#" class="footer-link" onclick="showSystemInfo()">
                                    <span class="link-icon"><?= $icons['dashboard'] ?></span>
                                    <span class="link-text">Informações do Sistema</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Links adicionais -->
                <?php if (!empty($config['additional_links'])): ?>
                <div class="col-lg-3 col-md-6">
                    <div class="footer-section">
                        <h4 class="footer-title">Links Úteis</h4>
                        <ul class="footer-links">
                            <?php foreach ($config['additional_links'] as $link): ?>
                            <li class="footer-link-item">
                                <a href="<?= htmlspecialchars($link['url']) ?>" class="footer-link" 
                                   <?= isset($link['target']) ? 'target="' . htmlspecialchars($link['target']) . '"' : '' ?>>
                                    <?php if (isset($link['icon'])): ?>
                                    <span class="link-icon"><?= $link['icon'] ?></span>
                                    <?php endif; ?>
                                    <span class="link-text"><?= htmlspecialchars($link['label']) ?></span>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($config['show_system_info']): ?>
                <!-- Informações do sistema -->
                <div class="col-lg-3 col-md-6">
                    <div class="footer-section">
                        <h4 class="footer-title">Sistema ETL DI's</h4>
                        <div class="system-summary">
                            <p class="system-description">
                                Sistema modular para importação, processamento e exportação de XMLs de Declarações de Importação brasileiras.
                            </p>
                            
                            <div class="system-stats">
                                <div class="stat-item">
                                    <span class="stat-label">Versão:</span>
                                    <span class="stat-value"><?= htmlspecialchars($systemInfo['version']) ?></span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-label">Ambiente:</span>
                                    <span class="stat-value">Desenvolvimento</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-label">Última atualização:</span>
                                    <span class="stat-value" id="lastUpdate"><?= date('d/m/Y H:i') ?></span>
                                </div>
                            </div>

                            <div class="module-status-summary">
                                <h5 class="status-title">Status dos Módulos</h5>
                                <div class="status-grid">
                                    <?php foreach ($moduleStatus as $module => $status): ?>
                                    <div class="status-item">
                                        <div class="status-indicator <?= $status ?>"></div>
                                        <span class="status-name"><?= ucfirst($module) ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Barra inferior do footer -->
        <div class="footer-bottom">
            <div class="footer-bottom-content">
                <div class="footer-left">
                    <div class="copyright">
                        <span>&copy; <?= htmlspecialchars($systemInfo['year']) ?> <?= htmlspecialchars($systemInfo['company']) ?>.</span>
                        <span>Todos os direitos reservados.</span>
                    </div>
                    <?php if ($config['show_version']): ?>
                    <div class="version-info">
                        <span class="version-label"><?= htmlspecialchars($systemInfo['name']) ?></span>
                        <span class="version-number">v<?= htmlspecialchars($systemInfo['version']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="footer-right">
                    <!-- Links rápidos -->
                    <div class="quick-links">
                        <a href="/index.html" class="quick-link" title="Página Inicial">
                            <?= $icons['home'] ?>
                        </a>
                        <a href="/sistema/dashboard/" class="quick-link" title="Dashboard">
                            <?= $icons['dashboard'] ?>
                        </a>
                        <a href="<?= htmlspecialchars($systemInfo['documentation_url']) ?>" class="quick-link" title="Documentação">
                            <?= $icons['reports'] ?>
                        </a>
                        <a href="/sistema/config/" class="quick-link" title="Configurações">
                            <?= $icons['settings'] ?>
                        </a>
                    </div>

                    <!-- Indicador de status -->
                    <div class="footer-status">
                        <div class="status-indicator online" title="Sistema Online">
                            <div class="status-dot"></div>
                        </div>
                        <span class="status-text d-none d-md-inline">Online</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- Modal de Informações do Sistema -->
<div class="modal fade" id="systemInfoModal" tabindex="-1" aria-labelledby="systemInfoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="systemInfoModalLabel">
                    <?= $icons['dashboard'] ?>
                    Informações do Sistema
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="system-info-grid">
                    <div class="info-section">
                        <h6>Sistema</h6>
                        <div class="info-items">
                            <div class="info-item">
                                <span class="info-label">Nome:</span>
                                <span class="info-value"><?= htmlspecialchars($systemInfo['name']) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Versão:</span>
                                <span class="info-value"><?= htmlspecialchars($systemInfo['version']) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Desenvolvedor:</span>
                                <span class="info-value"><?= htmlspecialchars($systemInfo['company']) ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="info-section">
                        <h6>Módulos</h6>
                        <div class="info-items">
                            <?php foreach ($moduleStatus as $module => $status): ?>
                            <div class="info-item">
                                <span class="info-label"><?= ucfirst($module) ?>:</span>
                                <span class="info-value">
                                    <span class="status-indicator <?= $status ?>"></span>
                                    <?= $status === 'active' ? 'Ativo' : 'Inativo' ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="info-section">
                        <h6>Ambiente</h6>
                        <div class="info-items">
                            <div class="info-item">
                                <span class="info-label">Modo:</span>
                                <span class="info-value">Desenvolvimento</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">PHP:</span>
                                <span class="info-value"><?= PHP_VERSION ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Servidor:</span>
                                <span class="info-value"><?= $_SERVER['SERVER_SOFTWARE'] ?? 'N/A' ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script>
// Função para mostrar informações do sistema
function showSystemInfo() {
    // Se Bootstrap modal estiver disponível
    if (typeof bootstrap !== 'undefined') {
        const modal = new bootstrap.Modal(document.getElementById('systemInfoModal'));
        modal.show();
    } else {
        // Fallback simples
        alert('Informações do Sistema:\n\nNome: <?= addslashes($systemInfo['name']) ?>\nVersão: <?= addslashes($systemInfo['version']) ?>\nDesenvolvedor: <?= addslashes($systemInfo['company']) ?>');
    }
}

// Atualizar timestamp do último update
function updateLastUpdateTime() {
    const lastUpdateElement = document.getElementById('lastUpdate');
    if (lastUpdateElement) {
        const now = new Date();
        lastUpdateElement.textContent = now.toLocaleString('pt-BR');
    }
}

// Atualizar a cada minuto
setInterval(updateLastUpdateTime, 60000);
</script>
<?php
}

/**
 * Função simplificada para incluir o footer
 * 
 * @param array $options Opções de configuração
 */
function includeSystemFooter($options = []) {
    renderSystemFooter($options);
}
?>