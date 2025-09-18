<?php
/**
 * ================================================================================
 * PAINEL DE CONTROLE MANUAL - DASHBOARD ETL DI's
 * Interface intuitiva para controle total do workflow manual
 * Padrão Visual: Expertzy (#FF002D, #091A30)
 * ================================================================================
 */

// Função para obter status detalhado do sistema
function getControlPanelStatus() {
    try {
        require_once __DIR__ . '/../../config/database.php';
        $db = getDatabase();
        
        // Verificar status do banco de dados
        $dbConnected = $db->testConnection();
        $schemaReady = $dbConnected ? $db->isDatabaseReady() : false;
        
        // Contar DIs no banco
        $disCount = 0;
        $lastUpdate = null;
        
        if ($dbConnected && $schemaReady) {
            try {
                $stmt = $db->getPDO()->query("SELECT COUNT(*) as total, MAX(created_at) as last_update FROM declaracoes_importacao");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $disCount = (int)$result['total'];
                $lastUpdate = $result['last_update'];
            } catch (Exception $e) {
                // Tabela pode não existir ainda
            }
        }
        
        return [
            'database_connected' => $dbConnected,
            'schema_ready' => $schemaReady,
            'dis_count' => $disCount,
            'last_update' => $lastUpdate,
            'has_data' => $disCount > 0,
            'upload_ready' => is_writable(__DIR__ . '/../../data/uploads/'),
            'processed_ready' => is_writable(__DIR__ . '/../../data/processed/')
        ];
    } catch (Exception $e) {
        return [
            'database_connected' => false,
            'schema_ready' => false,
            'dis_count' => 0,
            'last_update' => null,
            'has_data' => false,
            'upload_ready' => false,
            'processed_ready' => false
        ];
    }
}

$controlStatus = getControlPanelStatus();

// Determinar próximo passo lógico
function getNextStep($status) {
    if (!$status['database_connected']) {
        return [
            'title' => 'Conectar Banco de Dados',
            'description' => 'Verificar conexão com MySQL',
            'icon' => 'database',
            'priority' => 'error'
        ];
    }
    
    if (!$status['schema_ready']) {
        return [
            'title' => 'Configurar Schema',
            'description' => 'Instalar estrutura do banco',
            'icon' => 'settings',
            'priority' => 'warning'
        ];
    }
    
    if (!$status['has_data']) {
        return [
            'title' => 'Importar XMLs de DI',
            'description' => 'Carregar dados para análise',
            'icon' => 'upload',
            'priority' => 'info'
        ];
    }
    
    return [
        'title' => 'Carregar Visualizações',
        'description' => 'Gerar gráficos e estatísticas',
        'icon' => 'chart',
        'priority' => 'success'
    ];
}

$nextStep = getNextStep($controlStatus);

// Configurações do auto-refresh
$autoRefreshEnabled = isset($_COOKIE['etl_auto_refresh']) ? $_COOKIE['etl_auto_refresh'] === 'true' : false;
$refreshInterval = isset($_COOKIE['etl_refresh_interval']) ? (int)$_COOKIE['etl_refresh_interval'] : 60;
?>

<!-- Painel de Controle Manual -->
<section class="manual-control-panel card animate-fade-in" id="manualControlPanel">
    <div class="control-header">
        <div class="control-title">
            <h2>
                <svg class="control-icon" width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <path d="M12 2C13.1 2 14 2.9 14 4C14 5.1 13.1 6 12 6C10.9 6 10 5.1 10 4C10 2.9 10.9 2 12 2ZM21 9V7L15 6.5V9L21 9ZM3 9V7L9 6.5V9L3 9ZM15 10.5V13.5L21 13V10.5L15 10.5ZM3 10.5V13L9 13.5V10.5L3 10.5ZM15 15V18L21 17.5V15L15 15ZM3 15V17.5L9 18V15L3 15Z" stroke="currentColor" stroke-width="1.5"/>
                </svg>
                Painel de Controle ETL
            </h2>
            <span class="control-subtitle">Controle total do workflow manual</span>
        </div>
        
        <div class="control-status">
            <div class="status-indicator-main <?= $controlStatus['has_data'] ? 'active' : 'inactive' ?>">
                <span class="status-text">
                    <?= $controlStatus['has_data'] ? 'Sistema Ativo' : 'Aguardando Dados' ?>
                </span>
                <div class="status-dot"></div>
            </div>
        </div>
    </div>

    <!-- Status do Sistema com Visual Rico -->
    <div class="system-overview">
        <div class="overview-stats">
            <div class="stat-card database <?= $controlStatus['database_connected'] ? 'success' : 'error' ?>">
                <div class="stat-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                        <path d="M4 6C4 4.89543 4.89543 4 6 4H18C19.1046 4 20 4.89543 20 6V8C20 9.10457 19.1046 10 18 10H6C4.89543 10 4 9.10457 4 8V6Z" stroke="currentColor" stroke-width="2"/>
                        <path d="M4 14C4 12.8954 4.89543 12 6 12H18C19.1046 12 20 12.8954 20 14V16C20 17.1046 19.1046 18 18 18H6C4.89543 18 4 17.1046 4 16V14Z" stroke="currentColor" stroke-width="2"/>
                    </svg>
                </div>
                <div class="stat-info">
                    <span class="stat-label">Banco de Dados</span>
                    <span class="stat-value"><?= $controlStatus['database_connected'] ? 'Online' : 'Offline' ?></span>
                </div>
            </div>

            <div class="stat-card data <?= $controlStatus['has_data'] ? 'success' : 'warning' ?>">
                <div class="stat-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                        <path d="M14 2H6C4.89543 2 4 2.89543 4 4V20C4 21.1046 4.89543 22 6 22H18C19.1046 22 20 21.1046 20 20V8L14 2Z" stroke="currentColor" stroke-width="2"/>
                        <path d="M14 2V8H20" stroke="currentColor" stroke-width="2"/>
                        <path d="M16 13H8" stroke="currentColor" stroke-width="2"/>
                        <path d="M16 17H8" stroke="currentColor" stroke-width="2"/>
                        <path d="M10 9H8" stroke="currentColor" stroke-width="2"/>
                    </svg>
                </div>
                <div class="stat-info">
                    <span class="stat-label">DIs Importadas</span>
                    <span class="stat-value"><?= number_format($controlStatus['dis_count']) ?></span>
                </div>
            </div>

            <div class="stat-card update <?= $controlStatus['last_update'] ? 'info' : 'neutral' ?>">
                <div class="stat-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                        <path d="M12 2V6M12 18V22M4.93 4.93L7.76 7.76M16.24 16.24L19.07 19.07M2 12H6M18 12H22M4.93 19.07L7.76 16.24M16.24 7.76L19.07 4.93" stroke="currentColor" stroke-width="2"/>
                    </svg>
                </div>
                <div class="stat-info">
                    <span class="stat-label">Última Atualização</span>
                    <span class="stat-value">
                        <?= $controlStatus['last_update'] ? date('d/m H:i', strtotime($controlStatus['last_update'])) : 'Nunca' ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Card Como Começar (se não há dados) -->
        <?php if (!$controlStatus['has_data']): ?>
            <?php include 'cards/getting-started-card.php'; ?>
        <?php endif; ?>

        <!-- Próximo Passo Recomendado -->
        <div class="next-step-card <?= $nextStep['priority'] ?>">
            <div class="next-step-content">
                <div class="step-icon">
                    <?php
                    $iconSvg = match($nextStep['icon']) {
                        'database' => '<path d="M4 6C4 4.89543 4.89543 4 6 4H18C19.1046 4 20 4.89543 20 6V8C20 9.10457 19.1046 10 18 10H6C4.89543 10 4 9.10457 4 8V6Z" stroke="currentColor" stroke-width="2"/>',
                        'settings' => '<path d="M12 15C13.6569 15 15 13.6569 15 12C15 10.3431 13.6569 9 12 9C10.3431 9 9 10.3431 9 12C9 13.6569 10.3431 15 12 15Z" stroke="currentColor" stroke-width="2"/>',
                        'upload' => '<path d="M21 15V19C21 20.1046 20.1046 21 19 21H5C3.89543 21 3 20.1046 3 19V15M17 8L12 3M12 3L7 8M12 3V15" stroke="currentColor" stroke-width="2"/>',
                        'chart' => '<path d="M22 12H18L15 21L9 3L6 12H2" stroke="currentColor" stroke-width="2"/>',
                        default => '<path d="M9 12L11 14L15 10M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2"/>'
                    };
                    ?>
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <?= $iconSvg ?>
                    </svg>
                </div>
                <div class="step-info">
                    <h3>Próximo Passo</h3>
                    <h4><?= htmlspecialchars($nextStep['title']) ?></h4>
                    <p><?= htmlspecialchars($nextStep['description']) ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Seções de Controle -->
    <div class="control-sections">
        <!-- Gestão de Dados -->
        <div class="control-section data-management">
            <div class="section-header">
                <h3>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                        <path d="M5 7H19L18 17H6L5 7Z" stroke="currentColor" stroke-width="2"/>
                        <path d="M5 7L4 3H2" stroke="currentColor" stroke-width="2"/>
                        <path d="M9 11V13" stroke="currentColor" stroke-width="2"/>
                        <path d="M15 11V13" stroke="currentColor" stroke-width="2"/>
                    </svg>
                    Gestão de Dados
                </h3>
                <span class="section-badge">
                    <?= $controlStatus['dis_count'] ?> DIs
                </span>
            </div>
            
            <div class="control-actions">
                <button type="button" class="control-btn primary" id="btnImportXML" 
                        <?= (!$controlStatus['database_connected'] || !$controlStatus['upload_ready']) ? 'disabled' : '' ?>>
                    <div class="btn-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M21 15V19C21 20.1046 20.1046 21 19 21H5C3.89543 21 3 20.1046 3 19V15M17 8L12 3M12 3L7 8M12 3V15" stroke="currentColor" stroke-width="2"/>
                        </svg>
                    </div>
                    <div class="btn-content">
                        <span class="btn-title">Importar XML DI</span>
                        <small class="btn-description">Carregar novos dados</small>
                    </div>
                </button>

                <button type="button" class="control-btn secondary" id="btnVerifyDatabase" 
                        <?= !$controlStatus['database_connected'] ? 'disabled' : '' ?>>
                    <div class="btn-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M9 12L11 14L15 10M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2"/>
                        </svg>
                    </div>
                    <div class="btn-content">
                        <span class="btn-title">Verificar Status</span>
                        <small class="btn-description">Validar sistema</small>
                    </div>
                </button>

                <button type="button" class="control-btn tertiary" id="btnClearCache">
                    <div class="btn-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M3 6H5H21M8 6V4C8 3.46957 8.21071 2.96086 8.58579 2.58579C8.96086 2.21071 9.46957 2 10 2H14C14.5304 2 15.0391 2.21071 15.4142 2.58579C15.7893 2.96086 16 3.46957 16 4V6M19 6V20C19 20.5304 18.7893 21.0391 18.4142 21.4142C18.0391 21.7893 17.5304 22 17 22H7C6.46957 22 5.96086 21.7893 5.58579 21.4142C5.21071 21.0391 5 20.5304 5 20V6H19Z" stroke="currentColor" stroke-width="2"/>
                        </svg>
                    </div>
                    <div class="btn-content">
                        <span class="btn-title">Limpar Cache</span>
                        <small class="btn-description">Reset temporário</small>
                    </div>
                </button>
            </div>
        </div>

        <!-- Visualizações -->
        <div class="control-section visualizations">
            <div class="section-header">
                <h3>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                        <path d="M22 12H18L15 21L9 3L6 12H2" stroke="currentColor" stroke-width="2"/>
                    </svg>
                    Visualizações
                </h3>
                <span class="section-badge">
                    <?= $controlStatus['has_data'] ? 'Disponível' : 'Bloqueado' ?>
                </span>
            </div>
            
            <div class="control-actions">
                <button type="button" class="control-btn primary" id="btnLoadCharts" 
                        <?= !$controlStatus['has_data'] ? 'disabled' : '' ?>>
                    <div class="btn-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M22 12H18L15 21L9 3L6 12H2" stroke="currentColor" stroke-width="2"/>
                        </svg>
                    </div>
                    <div class="btn-content">
                        <span class="btn-title">Carregar Gráficos</span>
                        <small class="btn-description">Gerar visualizações</small>
                    </div>
                </button>

                <button type="button" class="control-btn secondary" id="btnLoadStats" 
                        <?= !$controlStatus['has_data'] ? 'disabled' : '' ?>>
                    <div class="btn-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M9 11H15M9 15H15M17 21H7C5.89543 21 5 20.1046 5 19V5C5 3.89543 5.89543 3 7 3H12.5858C12.851 3 13.1054 3.10536 13.2929 3.29289L19.7071 9.70711C19.8946 9.89464 20 10.149 20 10.4142V19C20 20.1046 19.1046 21 18 21H17Z" stroke="currentColor" stroke-width="2"/>
                        </svg>
                    </div>
                    <div class="btn-content">
                        <span class="btn-title">Carregar Estatísticas</span>
                        <small class="btn-description">Atualizar métricas</small>
                    </div>
                </button>

                <button type="button" class="control-btn primary" id="btnRefreshAll" 
                        <?= !$controlStatus['has_data'] ? 'disabled' : '' ?>>
                    <div class="btn-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M1 4V10H7M23 20V14H17M20.49 9C19.9828 7.56678 19.1209 6.28593 17.9845 5.27394C16.8482 4.26195 15.4745 3.55663 13.9917 3.21958C12.5089 2.88254 10.9652 2.92346 9.50481 3.33851C8.04439 3.75356 6.71475 4.52769 5.64 5.58L1 10M23 14L18.36 18.42C17.2853 19.4723 15.9556 20.2464 14.4952 20.6615C13.0348 21.0765 11.4911 21.1175 10.0083 20.7804C8.52547 20.4434 7.1518 19.738 6.01547 18.7261C4.87913 17.7141 4.01717 16.4332 3.51 15" stroke="currentColor" stroke-width="2"/>
                        </svg>
                    </div>
                    <div class="btn-content">
                        <span class="btn-title">Atualizar Tudo</span>
                        <small class="btn-description">Sincronizar sistema</small>
                    </div>
                </button>
            </div>
        </div>

        <!-- Configurações -->
        <div class="control-section settings">
            <div class="section-header">
                <h3>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                        <path d="M12 15C13.6569 15 15 13.6569 15 12C15 10.3431 13.6569 9 12 9C10.3431 9 9 10.3431 9 12C9 13.6569 10.3431 15 12 15Z" stroke="currentColor" stroke-width="2"/>
                        <path d="M19.4 15C19.2669 15.3016 19.2272 15.6362 19.286 15.9606C19.3448 16.285 19.4995 16.5843 19.73 16.82L19.79 16.88C19.976 17.0657 20.1235 17.2863 20.2241 17.5291C20.3248 17.7719 20.3766 18.0322 20.3766 18.295C20.3766 18.5578 20.3248 18.8181 20.2241 19.0609C20.1235 19.3037 19.976 19.5243 19.79 19.71C19.6043 19.896 19.3837 20.0435 19.1409 20.1441C18.8981 20.2448 18.6378 20.2966 18.375 20.2966C18.1122 20.2966 17.8519 20.2448 17.6091 20.1441C17.3663 20.0435 17.1457 19.896 16.96 19.71L16.9 19.65C16.6643 19.4195 16.365 19.2648 16.0406 19.206C15.7162 19.1472 15.3816 19.1869 15.08 19.32C14.7842 19.4468 14.532 19.6572 14.3543 19.9255C14.1766 20.1938 14.0813 20.5082 14.08 20.83V21C14.08 21.5304 13.8693 22.0391 13.4942 22.4142C13.1191 22.7893 12.6104 23 12.08 23C11.5496 23 11.0409 22.7893 10.6658 22.4142C10.2907 22.0391 10.08 21.5304 10.08 21V20.91C10.0723 20.579 9.96512 20.2582 9.77251 19.9887C9.5799 19.7193 9.31074 19.5143 9 19.4C8.69838 19.2669 8.36381 19.2272 8.03941 19.286C7.71502 19.3448 7.41568 19.4995 7.18 19.73L7.12 19.79C6.93425 19.976 6.71368 20.1235 6.47088 20.2241C6.22808 20.3248 5.96783 20.3766 5.705 20.3766C5.44217 20.3766 5.18192 20.3248 4.93912 20.2241C4.69632 20.1235 4.47575 19.976 4.29 19.79C4.10405 19.6043 3.95653 19.3837 3.85588 19.1409C3.75523 18.8981 3.70343 18.6378 3.70343 18.375C3.70343 18.1122 3.75523 17.8519 3.85588 17.6091C3.95653 17.3663 4.10405 17.1457 4.29 16.96L4.35 16.9C4.58054 16.6643 4.73519 16.365 4.794 16.0406C4.85282 15.7162 4.81312 15.3816 4.68 15.08C4.55324 14.7842 4.34276 14.532 4.07447 14.3543C3.80618 14.1766 3.49179 14.0813 3.17 14.08H3C2.46957 14.08 1.96086 13.8693 1.58579 13.4942C1.21071 13.1191 1 12.6104 1 12.08C1 11.5496 1.21071 11.0409 1.58579 10.6658C1.96086 10.2907 2.46957 10.08 3 10.08H3.09C3.42099 10.0723 3.74178 9.96512 4.01127 9.77251C4.28075 9.5799 4.48571 9.31074 4.6 9C4.73312 8.69838 4.77282 8.36381 4.714 8.03941C4.65519 7.71502 4.50054 7.41568 4.27 7.18L4.21 7.12C4.02405 6.93425 3.87653 6.71368 3.77588 6.47088C3.67523 6.22808 3.62343 5.96783 3.62343 5.705C3.62343 5.44217 3.67523 5.18192 3.77588 4.93912C3.87653 4.69632 4.02405 4.47575 4.21 4.29C4.39575 4.10405 4.61632 3.95653 4.85912 3.85588C5.10192 3.75523 5.36217 3.70343 5.625 3.70343C5.88783 3.70343 6.14808 3.75523 6.39088 3.85588C6.63368 3.95653 6.85425 4.10405 7.04 4.29L7.1 4.35C7.33568 4.58054 7.63502 4.73519 7.95941 4.794C8.28381 4.85282 8.61838 4.81312 8.92 4.68H9C9.29577 4.55324 9.54802 4.34276 9.72569 4.07447C9.90337 3.80618 9.99872 3.49179 10 3.17V3C10 2.46957 10.2107 1.96086 10.5858 1.58579C10.9609 1.21071 11.4696 1 12 1C12.5304 1 13.0391 1.21071 13.4142 1.58579C13.7893 1.96086 14 2.46957 14 3V3.09C14.0013 3.41179 14.0966 3.72618 14.2743 3.99447C14.452 4.26276 14.7042 4.47324 15 4.6C15.3016 4.73312 15.6362 4.77282 15.9606 4.714C16.285 4.65519 16.5843 4.50054 16.82 4.27L16.88 4.21C17.0657 4.02405 17.2863 3.87653 17.5291 3.77588C17.7719 3.67523 18.0322 3.62343 18.295 3.62343C18.5578 3.62343 18.8181 3.67523 19.0609 3.77588C19.3037 3.87653 19.5243 4.02405 19.71 4.21C19.896 4.39575 20.0435 4.61632 20.1441 4.85912C20.2448 5.10192 20.2966 5.36217 20.2966 5.625C20.2966 5.88783 20.2448 6.14808 20.1441 6.39088C20.0435 6.63368 19.896 6.85425 19.71 7.04L19.65 7.1C19.4195 7.33568 19.2648 7.63502 19.206 7.95941C19.1472 8.28381 19.1869 8.61838 19.32 8.92V9C19.4468 9.29577 19.6572 9.54802 19.9255 9.72569C20.1938 9.90337 20.5082 9.99872 20.83 10H21C21.5304 10 22.0391 10.2107 22.4142 10.5858C22.7893 10.9609 23 11.4696 23 12C23 12.5304 22.7893 13.0391 22.4142 13.4142C22.0391 13.7893 21.5304 14 21 14H20.91C20.5882 14.0013 20.2738 14.0966 20.0055 14.2743C19.7372 14.452 19.5268 14.7042 19.4 15V15Z" stroke="currentColor" stroke-width="2"/>
                    </svg>
                    Configurações
                </h3>
                <span class="section-badge auto-refresh <?= $autoRefreshEnabled ? 'active' : 'inactive' ?>">
                    Auto-refresh: <?= $autoRefreshEnabled ? 'ON' : 'OFF' ?>
                </span>
            </div>
            
            <div class="control-actions">
                <div class="settings-row">
                    <label class="toggle-container">
                        <input type="checkbox" id="autoRefreshToggle" <?= $autoRefreshEnabled ? 'checked' : '' ?>>
                        <span class="toggle-slider"></span>
                        <span class="toggle-label">Auto-refresh</span>
                    </label>
                    
                    <div class="interval-control" <?= !$autoRefreshEnabled ? 'style="opacity: 0.5; pointer-events: none;"' : '' ?>>
                        <label for="refreshInterval">Intervalo (segundos):</label>
                        <input type="range" id="refreshInterval" min="10" max="300" value="<?= $refreshInterval ?>" step="10">
                        <span class="interval-value"><?= $refreshInterval ?>s</span>
                    </div>
                </div>

                <div class="settings-actions">
                    <button type="button" class="control-btn secondary" id="btnAdvancedSettings">
                        <div class="btn-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                <path d="M12 15C13.6569 15 15 13.6569 15 12C15 10.3431 13.6569 9 12 9C10.3431 9 9 10.3431 9 12C9 13.6569 10.3431 15 12 15Z" stroke="currentColor" stroke-width="2"/>
                            </svg>
                        </div>
                        <span class="btn-title">Configurações Avançadas</span>
                    </button>

                    <a href="/docs/" class="control-btn tertiary" target="_blank">
                        <div class="btn-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                <path d="M14 2H6C4.89543 2 4 2.89543 4 4V20C4 21.1046 4.89543 22 6 22H18C19.1046 22 20 21.1046 20 20V8L14 2Z" stroke="currentColor" stroke-width="2"/>
                                <path d="M14 2V8H20" stroke="currentColor" stroke-width="2"/>
                                <path d="M16 13H8" stroke="currentColor" stroke-width="2"/>
                                <path d="M16 17H8" stroke="currentColor" stroke-width="2"/>
                                <path d="M10 9H8" stroke="currentColor" stroke-width="2"/>
                            </svg>
                        </div>
                        <span class="btn-title">Documentação</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Progresso de Ações -->
    <div class="action-progress" id="actionProgress" style="display: none;">
        <div class="progress-content">
            <div class="progress-spinner">
                <div class="spinner"></div>
            </div>
            <div class="progress-info">
                <h4 id="progressTitle">Processando...</h4>
                <p id="progressDescription">Aguarde enquanto a operação é executada</p>
                <div class="progress-bar">
                    <div class="progress-fill" id="progressFill"></div>
                </div>
                <span class="progress-percent" id="progressPercent">0%</span>
            </div>
        </div>
    </div>
</section>

<!-- Indicadores de Feedback Visual -->
<div class="control-feedback" id="controlFeedback">
    <!-- Será preenchido dinamicamente pelo JavaScript -->
</div>