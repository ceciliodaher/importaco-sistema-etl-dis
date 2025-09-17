<?php
/**
 * ================================================================================
 * SISTEMA ETL DE DI's - CONFIGURAÇÃO DE CONEXÃO DO BANCO DE DADOS
 * Interface web para configuração e teste de conexões
 * Padrão Visual: Expertzy (#FF002D, #091A30)
 * ================================================================================
 */

// Configurações de erro para desenvolvimento
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Carregar dependências
require_once '../core/DatabaseConnectionManager.php';
require_once '../shared/utils/layout-helpers.php';

// Inicializar o gerenciador de conexões
try {
    $connectionManager = DatabaseConnectionManager::getInstance();
    $detectedEnvironments = $connectionManager->getDetectedEnvironments();
    $availableProfiles = $connectionManager->getAvailableProfiles();
    $currentProfile = $connectionManager->getCurrentProfileName();
    $systemStatus = $connectionManager->getStatus();
} catch (Exception $e) {
    $error = $e->getMessage();
    $detectedEnvironments = [];
    $availableProfiles = [];
    $currentProfile = null;
    $systemStatus = [];
}

// Configurar layout da página
$layoutConfig = [
    'page_title' => 'Configuração do Banco de Dados',
    'page_description' => 'Interface para configuração e teste de conexões com banco de dados',
    'layout_type' => 'setup',
    'additional_css' => [
        'assets/setup.css',
        '../shared/assets/css/system-navigation.css'
    ],
    'additional_js' => [
        'assets/setup.js'
    ],
    'meta_tags' => [
        'keywords' => 'Database, Configuração, MySQL, Conexão, Setup',
        'author' => 'Expertzy IT Solutions'
    ]
];

// Renderizar início do layout
$layout = new LayoutManager($layoutConfig);
$layout->renderLayoutStart();
?>

<!-- Container principal da configuração -->
<div class="setup-container animate-fade-in">
    <!-- Header da página -->
    <div class="setup-header card animate-slide-in-down">
        <div class="header-content">
            <div class="header-icon">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none">
                    <path d="M20 14V8C20 5.79086 18.2091 4 16 4H8C5.79086 4 4 5.79086 4 8V14C4 16.2091 5.79086 18 8 18H16C18.2091 18 20 16.2091 20 14Z" stroke="currentColor" stroke-width="2"/>
                    <path d="M8 9H16M8 13H16" stroke="currentColor" stroke-width="2"/>
                </svg>
            </div>
            <div class="header-info">
                <h1>Configuração do Banco de Dados</h1>
                <p>Configure e teste conexões com diferentes perfis de banco de dados MySQL</p>
            </div>
        </div>
        
        <!-- Status atual da conexão -->
        <div class="current-status">
            <div class="status-item">
                <span class="status-label">Perfil Atual:</span>
                <span class="status-value" id="currentProfileName"><?= $currentProfile ?? 'Nenhum' ?></span>
            </div>
            <div class="status-item">
                <span class="status-label">Status:</span>
                <div class="status-indicator-wrapper">
                    <div class="status-indicator" id="connectionStatus"></div>
                    <span class="status-text" id="connectionStatusText">Verificando...</span>
                </div>
            </div>
        </div>
    </div>

    <div class="setup-content">
        <!-- Detecção Automática -->
        <section class="detection-section card animate-scale-in">
            <div class="section-header">
                <h2>Ambientes Detectados</h2>
                <p>O sistema detectou automaticamente os seguintes ambientes disponíveis:</p>
                <button class="btn btn-secondary btn-sm" id="refreshDetection">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                        <path d="M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2"/>
                        <path d="M9 12L11 14L15 10" stroke="currentColor" stroke-width="2"/>
                    </svg>
                    Atualizar Detecção
                </button>
            </div>
            
            <div class="environments-grid" id="environmentsGrid">
                <?php if (empty($detectedEnvironments)): ?>
                <div class="no-environments">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none">
                        <path d="M12 8V12M12 16H12.01M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2"/>
                    </svg>
                    <p>Nenhum ambiente foi detectado automaticamente</p>
                    <span class="note">Isso é normal se você não tiver MySQL instalado localmente</span>
                </div>
                <?php else: ?>
                    <?php foreach ($detectedEnvironments as $env => $config): ?>
                    <div class="environment-card" data-environment="<?= $env ?>">
                        <div class="env-header">
                            <div class="env-icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                                    <path d="M9 12L11 14L15 10M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2"/>
                                </svg>
                            </div>
                            <div class="env-info">
                                <h3><?= htmlspecialchars($config['name']) ?></h3>
                                <span class="env-type"><?= ucfirst($config['type']) ?></span>
                            </div>
                            <div class="env-status detected">Detectado</div>
                        </div>
                        <div class="env-actions">
                            <button class="btn btn-primary btn-sm test-connection" data-profile="<?= $config['profile'] ?>">
                                Testar Conexão
                            </button>
                            <button class="btn btn-secondary btn-sm use-profile" data-profile="<?= $config['profile'] ?>">
                                Usar Este Perfil
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <!-- Perfis de Conexão -->
        <section class="profiles-section card animate-scale-in">
            <div class="section-header">
                <h2>Perfis de Conexão Disponíveis</h2>
                <p>Todos os perfis de conexão configurados no sistema:</p>
                <button class="btn btn-primary" id="testAllConnections">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                        <path d="M14 2H6C4.89543 2 4 2.89543 4 4V20C4 21.1046 4.89543 22 6 22H18C19.1046 22 20 21.1046 20 20V8L14 2Z" stroke="currentColor" stroke-width="2"/>
                        <path d="M14 2V8H20M16 13H8M16 17H8M10 9H8" stroke="currentColor" stroke-width="2"/>
                    </svg>
                    Testar Todos os Perfis
                </button>
            </div>
            
            <div class="profiles-grid" id="profilesGrid">
                <?php foreach ($availableProfiles as $profileName): ?>
                    <?php 
                    try {
                        $profile = $connectionManager->getProfile($profileName);
                        $isActive = $profileName === $currentProfile;
                    } catch (Exception $e) {
                        continue;
                    }
                    ?>
                <div class="profile-card <?= $isActive ? 'active' : '' ?>" data-profile="<?= $profileName ?>">
                    <div class="profile-header">
                        <div class="profile-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <path d="M20 14V8C20 5.79086 18.2091 4 16 4H8C5.79086 4 4 5.79086 4 8V14C4 16.2091 5.79086 18 8 18H16C18.2091 18 20 16.2091 20 14Z" stroke="currentColor" stroke-width="2"/>
                                <path d="M8 9H16M8 13H16" stroke="currentColor" stroke-width="2"/>
                            </svg>
                        </div>
                        <div class="profile-info">
                            <h3><?= htmlspecialchars($profile['name']) ?></h3>
                            <span class="profile-type"><?= ucfirst($profile['environment_type']) ?></span>
                        </div>
                        <?php if ($isActive): ?>
                        <div class="profile-badge active">ATIVO</div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="profile-details">
                        <div class="detail-row">
                            <span class="detail-label">Host:</span>
                            <span class="detail-value"><?= htmlspecialchars($profile['host']) ?>:<?= $profile['port'] ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Database:</span>
                            <span class="detail-value"><?= htmlspecialchars($profile['database']) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Usuário:</span>
                            <span class="detail-value"><?= htmlspecialchars($profile['username']) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Descrição:</span>
                            <span class="detail-value detail-description"><?= htmlspecialchars($profile['description']) ?></span>
                        </div>
                    </div>
                    
                    <div class="profile-test-result" id="testResult-<?= $profileName ?>" style="display: none;">
                        <!-- Resultado do teste será inserido aqui -->
                    </div>
                    
                    <div class="profile-actions">
                        <button class="btn btn-primary btn-sm test-connection" data-profile="<?= $profileName ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                <path d="M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2"/>
                                <path d="M9 12L11 14L15 10" stroke="currentColor" stroke-width="2"/>
                            </svg>
                            Testar
                        </button>
                        <?php if (!$isActive): ?>
                        <button class="btn btn-secondary btn-sm use-profile" data-profile="<?= $profileName ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                <path d="M9 12L11 14L15 10" stroke="currentColor" stroke-width="2"/>
                            </svg>
                            Usar Este Perfil
                        </button>
                        <?php endif; ?>
                        <button class="btn btn-outline btn-sm edit-profile" data-profile="<?= $profileName ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                <path d="M11 4H4C3.44772 4 3 4.44772 3 5V19C3 19.5523 3.44772 20 4 20H18C18.5523 20 19 19.5523 19 19V12" stroke="currentColor" stroke-width="2"/>
                                <path d="M18.5 2.5C19.3284 1.67157 20.6716 1.67157 21.5 2.5C22.3284 3.32843 22.3284 4.67157 21.5 5.5L12 15L8 16L9 12L18.5 2.5Z" stroke="currentColor" stroke-width="2"/>
                            </svg>
                            Editar
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Configuração Customizada -->
        <section class="custom-config-section card animate-scale-in">
            <div class="section-header">
                <h2>Configuração Customizada</h2>
                <p>Crie e teste uma conexão personalizada:</p>
            </div>
            
            <form id="customConfigForm" class="custom-config-form">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="customName">Nome da Configuração</label>
                        <input type="text" id="customName" name="name" placeholder="Ex: Minha Configuração Local" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="customHost">Host</label>
                        <input type="text" id="customHost" name="host" placeholder="localhost" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="customPort">Porta</label>
                        <input type="number" id="customPort" name="port" placeholder="3306" min="1" max="65535" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="customDatabase">Database</label>
                        <input type="text" id="customDatabase" name="database" placeholder="importaco_etl_dis" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="customUsername">Usuário</label>
                        <input type="text" id="customUsername" name="username" placeholder="root" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="customPassword">Senha</label>
                        <input type="password" id="customPassword" name="password" placeholder="Digite a senha">
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" id="testCustomConnection" class="btn btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                            <path d="M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2"/>
                            <path d="M9 12L11 14L15 10" stroke="currentColor" stroke-width="2"/>
                        </svg>
                        Testar Conexão
                    </button>
                    <button type="submit" class="btn btn-success" disabled id="saveCustomProfile">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                            <path d="M19 21H5C3.89543 21 3 20.1046 3 19V5C3 3.89543 3.89543 3 5 3H16L21 8V19C21 20.1046 20.1046 21 19 21Z" stroke="currentColor" stroke-width="2"/>
                            <path d="M17 21V13H7V21M7 3V8H15" stroke="currentColor" stroke-width="2"/>
                        </svg>
                        Salvar Perfil
                    </button>
                </div>
                
                <div class="custom-test-result" id="customTestResult" style="display: none;">
                    <!-- Resultado do teste customizado será inserido aqui -->
                </div>
            </form>
        </section>
    </div>
</div>

<!-- Modal de Loading -->
<div class="loading-overlay" id="loadingOverlay" style="display: none;">
    <div class="loading-content">
        <div class="loading-spinner"></div>
        <p class="loading-text">Testando conexão...</p>
    </div>
</div>

<!-- Modal de Confirmação -->
<div class="modal-overlay" id="confirmModal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Confirmar Ação</h3>
            <button class="modal-close" id="closeConfirmModal">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2"/>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <p id="confirmMessage">Tem certeza que deseja continuar?</p>
        </div>
        <div class="modal-actions">
            <button class="btn btn-secondary" id="cancelAction">Cancelar</button>
            <button class="btn btn-primary" id="confirmAction">Confirmar</button>
        </div>
    </div>
</div>

<!-- Feedback Messages -->
<div class="feedback-container" id="feedbackContainer">
    <!-- Messages serão inseridas dinamicamente aqui -->
</div>

<!-- JavaScript Customizado -->
<script>
// Configurações globais
window.setupConfig = {
    currentProfile: <?= json_encode($currentProfile) ?>,
    profiles: <?= json_encode($availableProfiles) ?>,
    detectedEnvironments: <?= json_encode($detectedEnvironments) ?>,
    systemStatus: <?= json_encode($systemStatus) ?>
};

// Inicializar após carregamento da página
document.addEventListener('DOMContentLoaded', function() {
    console.log('Setup de configuração do banco carregado');
    
    // Aguardar o layout estar pronto
    window.addEventListener('expertzyLayoutReady', function(e) {
        console.log('Layout Expertzy pronto:', e.detail);
        
        // Inicializar funcionalidades específicas do setup
        initSetupFeatures();
    });
});

// Funções específicas do setup
function initSetupFeatures() {
    console.log('Inicializando funcionalidades do setup...');
    
    // Verificar status da conexão atual
    checkCurrentConnectionStatus();
    
    // Configurar listeners de eventos
    setupEventListeners();
}

function checkCurrentConnectionStatus() {
    if (window.setupConfig.currentProfile) {
        testConnection(window.setupConfig.currentProfile, true);
    }
}

function setupEventListeners() {
    // Implementação será feita no setup.js
    if (typeof initConnectionSetup === 'function') {
        initConnectionSetup();
    }
}

// Expor funções globalmente
window.setupUtils = {
    testConnection: testConnection,
    checkCurrentConnectionStatus: checkCurrentConnectionStatus
};
</script>

<?php
// Renderizar fim do layout
$layout->renderLayoutEnd();
?>