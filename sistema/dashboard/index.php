<?php
/**
 * ================================================================================
 * SISTEMA ETL DE DI's - DASHBOARD PRINCIPAL
 * Interface principal para importação e processamento de XMLs DI
 * Padrão Visual: Expertzy (#FF002D, #091A30)
 * ================================================================================
 */

// Configurações de erro para desenvolvimento
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Carregar configurações e sistema de navegação
require_once '../config/database.php';
require_once '../shared/utils/layout-helpers.php';

// Função para obter estatísticas do sistema
function getDashboardStats() {
    try {
        $db = getDatabase();
        return $db->getStatistics();
    } catch (Exception $e) {
        return [
            'DIs Processadas' => 0,
            'Adições' => 0,
            'Impostos Calculados' => 0,
            'Despesas Extras' => 0,
            'NCMs Catalogados' => 0,
            'Moedas Configuradas' => 0
        ];
    }
}

// Função para verificar status do sistema
function getSystemStatus() {
    try {
        $db = getDatabase();
        $isReady = $db->isDatabaseReady();
        $connection = $db->testConnection();
        
        return [
            'database' => $connection ? 'online' : 'offline',
            'schema' => $isReady ? 'ready' : 'pending',
            'upload_dir' => is_writable('../data/uploads/') ? 'writable' : 'readonly',
            'processed_dir' => is_writable('../data/processed/') ? 'writable' : 'readonly'
        ];
    } catch (Exception $e) {
        return [
            'database' => 'offline',
            'schema' => 'error',
            'upload_dir' => 'error',
            'processed_dir' => 'error'
        ];
    }
}

$stats = getDashboardStats();
$status = getSystemStatus();

// Configurar layout do dashboard
$layoutConfig = [
    'page_title' => 'Dashboard Principal',
    'page_description' => 'Interface principal para importação e processamento de XMLs DI',
    'layout_type' => 'dashboard',
    'additional_css' => [
        '../../assets/css/expertzy-theme.css',
        'assets/css/dashboard.css',
        'assets/css/charts.css',
        '../shared/assets/css/system-navigation.css'
    ],
    'additional_js' => [
        'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js',
        'assets/js/dashboard.js',
        'assets/js/charts.js',
        'assets/js/upload.js',
        'assets/js/charts-extensions.js'
    ],
    'meta_tags' => [
        'keywords' => 'ETL, DI, Importação, Tributação, Sistema Fiscal, Dashboard',
        'author' => 'Expertzy IT Solutions'
    ]
];

// Renderizar início do layout
$layout = new LayoutManager($layoutConfig);
$layout->renderLayoutStart();
?>
<!-- O header é renderizado automaticamente pelo LayoutManager -->

<!-- Container do dashboard -->
<div class="dashboard-container animate-fade-in">
        <!-- Sidebar -->
        <aside class="sidebar card animate-slide-in-right">
            <div class="modules-section">
                <h3>Módulos do Sistema</h3>
                
                <div class="module-card card fiscal animate-scale-in">
                    <div class="module-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <path d="M9 11H15M9 15H15M17 21H7C5.89543 21 5 20.1046 5 19V5C5 3.89543 5.89543 3 7 3H12.5858C12.851 3 13.1054 3.10536 13.2929 3.29289L19.7071 9.70711C19.8946 9.89464 20 10.149 20 10.4142V19C20 20.1046 19.1046 21 18 21H17ZM17 21V11H13V7H7V19H17Z" stroke="currentColor" stroke-width="2"/>
                        </svg>
                    </div>
                    <div class="module-info">
                        <h4>Fiscal</h4>
                        <p>Cálculos tributários e nomenclatura</p>
                    </div>
                    <div class="module-status active"></div>
                </div>

                <div class="module-card card commercial animate-scale-in">
                    <div class="module-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <path d="M12 6V4M12 6C10.8954 6 10 6.89543 10 8C10 9.10457 10.8954 10 12 10M12 6C13.1046 6 14 6.89543 14 8C14 9.10457 13.1046 10 12 10M12 10V20M8 21H16M16 4H8M12 14C8.68629 14 6 11.3137 6 8H4C4 12.4183 7.58172 16 12 16C16.4183 16 20 12.4183 20 8H18C18 11.3137 15.3137 14 12 14Z" stroke="currentColor" stroke-width="2"/>
                        </svg>
                    </div>
                    <div class="module-info">
                        <h4>Comercial</h4>
                        <p>Precificação B2B/B2C</p>
                    </div>
                    <div class="module-status active"></div>
                </div>

                <div class="module-card card accounting animate-scale-in">
                    <div class="module-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <path d="M9 7H15M9 11H15M9 15H13M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2"/>
                        </svg>
                    </div>
                    <div class="module-info">
                        <h4>Contábil</h4>
                        <p>Custeio e rateio de despesas</p>
                    </div>
                    <div class="module-status active"></div>
                </div>

                <div class="module-card card billing animate-scale-in">
                    <div class="module-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <path d="M9 12L11 14L15 10M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2"/>
                        </svg>
                    </div>
                    <div class="module-info">
                        <h4>Faturamento</h4>
                        <p>Emissão de documentos fiscais</p>
                    </div>
                    <div class="module-status active"></div>
                </div>
            </div>

            <div class="quick-stats">
                <h3>Estatísticas Rápidas</h3>
                <?php foreach ($stats as $label => $value): ?>
                <div class="stat-item animate-fade-in">
                    <span class="stat-label"><?= $label ?></span>
                    <span class="stat-value"><?= is_numeric($value) ? number_format($value) : $value ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Upload Zone -->
            <section class="upload-section card animate-fade-in">
                <div class="upload-zone" id="uploadZone">
                    <div class="upload-icon">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none">
                            <path d="M7 18C3.68629 18 1 15.3137 1 12C1 8.68629 3.68629 6 7 6C7.36312 6 7.71308 6.04264 8.04907 6.12166C9.15004 3.31397 11.8844 1.5 15 1.5C19.1421 1.5 22.5 4.85786 22.5 9C22.5 9.78632 22.3489 10.5371 22.0748 11.2273C22.5484 11.6946 22.8333 12.3097 22.8333 13C22.8333 14.2887 21.7887 15.3333 20.5 15.3333H18M12 8V15M9 12L12 9L15 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <div class="upload-content">
                        <h2>Importar XMLs de DI</h2>
                        <p>Arraste e solte seus arquivos XML aqui ou <span class="upload-link">clique para procurar</span></p>
                        <div class="upload-restrictions">
                            <span>Apenas arquivos .xml | Máximo 10MB por arquivo</span>
                        </div>
                    </div>
                    <input type="file" id="fileInput" multiple accept=".xml" hidden>
                </div>

                <div class="upload-progress" id="uploadProgress" style="display: none;">
                    <div class="progress-bar">
                        <div class="progress-fill" id="progressFill"></div>
                    </div>
                    <div class="progress-info">
                        <span id="progressText">Processando...</span>
                        <span id="progressPercent">0%</span>
                    </div>
                </div>

                <div class="file-list" id="fileList" style="display: none;">
                    <h3>Arquivos Selecionados</h3>
                    <div class="files-container" id="filesContainer"></div>
                    <div class="file-actions">
                        <button class="btn btn-primary" id="processFiles">Processar Arquivos</button>
                        <button class="btn btn-secondary" id="clearFiles">Limpar Lista</button>
                    </div>
                </div>
            </section>

            <!-- Dashboard Cards -->
            <section class="dashboard-cards card animate-fade-in">
                <div class="card-grid">
                    <div class="dashboard-card card animate-scale-in">
                        <div class="card-icon success">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <path d="M9 12L11 14L15 10M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2"/>
                            </svg>
                        </div>
                        <div class="card-content">
                            <h3>DIs Processadas</h3>
                            <div class="card-value"><?= number_format($stats['DIs Processadas']) ?></div>
                            <div class="card-trend positive">
                                <span>+12% este mês</span>
                            </div>
                        </div>
                    </div>

                    <div class="dashboard-card card animate-scale-in">
                        <div class="card-icon info">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <path d="M9 11H15M9 15H15M17 21H7C5.89543 21 5 20.1046 5 19V5C5 3.89543 5.89543 3 7 3H12.5858C12.851 3 13.1054 3.10536 13.2929 3.29289L19.7071 9.70711C19.8946 9.89464 20 10.149 20 10.4142V19C20 20.1046 19.1046 21 18 21H17Z" stroke="currentColor" stroke-width="2"/>
                            </svg>
                        </div>
                        <div class="card-content">
                            <h3>Impostos Calculados</h3>
                            <div class="card-value"><?= number_format($stats['Impostos Calculados']) ?></div>
                            <div class="card-trend neutral">
                                <span>Última atualização: hoje</span>
                            </div>
                        </div>
                    </div>

                    <div class="dashboard-card card animate-scale-in">
                        <div class="card-icon warning">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <path d="M12 6V4M12 6C10.8954 6 10 6.89543 10 8C10 9.10457 10.8954 10 12 10M12 6C13.1046 6 14 6.89543 14 8C14 9.10457 13.1046 10 12 10M12 10V20M8 21H16M16 4H8M12 14C8.68629 14 6 11.3137 6 8H4C4 12.4183 7.58172 16 12 16C16.4183 16 20 12.4183 20 8H18C18 11.3137 15.3137 14 12 14Z" stroke="currentColor" stroke-width="2"/>
                            </svg>
                        </div>
                        <div class="card-content">
                            <h3>NCMs Catalogados</h3>
                            <div class="card-value"><?= number_format($stats['NCMs Catalogados']) ?></div>
                            <div class="card-trend positive">
                                <span>+5 novos NCMs</span>
                            </div>
                        </div>
                    </div>

                    <div class="dashboard-card card animate-scale-in">
                        <div class="card-icon error">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <path d="M12 8V12M12 16H12.01M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2"/>
                            </svg>
                        </div>
                        <div class="card-content">
                            <h3>Despesas Extras</h3>
                            <div class="card-value"><?= number_format($stats['Despesas Extras']) ?></div>
                            <div class="card-trend negative">
                                <span>-2% este mês</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- System Status -->
            <section class="system-status card animate-fade-in">
                <div class="status-card card animate-scale-in">
                    <h3>Status do Sistema</h3>
                    <div class="status-grid">
                        <div class="status-item">
                            <div class="status-indicator <?= $status['database'] ?>"></div>
                            <span>Banco de Dados</span>
                            <div class="status-label"><?= ucfirst($status['database']) ?></div>
                        </div>
                        <div class="status-item">
                            <div class="status-indicator <?= $status['schema'] === 'ready' ? 'online' : 'offline' ?>"></div>
                            <span>Schema</span>
                            <div class="status-label"><?= $status['schema'] === 'ready' ? 'Pronto' : 'Pendente' ?></div>
                        </div>
                        <div class="status-item">
                            <div class="status-indicator <?= $status['upload_dir'] === 'writable' ? 'online' : 'offline' ?>"></div>
                            <span>Upload</span>
                            <div class="status-label"><?= $status['upload_dir'] === 'writable' ? 'OK' : 'Erro' ?></div>
                        </div>
                        <div class="status-item">
                            <div class="status-indicator <?= $status['processed_dir'] === 'writable' ? 'online' : 'offline' ?>"></div>
                            <span>Processados</span>
                            <div class="status-label"><?= $status['processed_dir'] === 'writable' ? 'OK' : 'Erro' ?></div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Dashboard de Gráficos Interativos -->
            <?php include 'components/charts/charts-dashboard.php'; ?>
            
        </main>
</div>

<!-- Feedback Messages -->
<div class="feedback-container" id="feedbackContainer">
    <!-- Messages serão inseridas dinamicamente aqui -->
</div>

<!-- JavaScript Customizado do Dashboard -->
<script>
// Inicializar dashboard
document.addEventListener('DOMContentLoaded', function() {
    console.log('Dashboard Expertzy ETL DI carregado com sucesso');
    
    // Aguardar o layout estar pronto
    window.addEventListener('expertzyLayoutReady', function(e) {
        console.log('Layout Expertzy pronto:', e.detail);
        
        // Inicializar funcionalidades específicas do dashboard
        initDashboardFeatures();
    });
    
    // Atualizar estatísticas a cada 30 segundos
    setInterval(() => {
        console.log('Atualizando estatísticas...');
        if (window.expertzyCharts) {
            window.expertzyCharts.loadChartData();
        }
        updateSystemStats();
    }, 30000);
});

// Funções específicas do dashboard
function initDashboardFeatures() {
    // Inicializar upload de arquivos
    if (typeof initFileUpload === 'function') {
        initFileUpload();
    }
    
    // Inicializar gráficos
    if (typeof initCharts === 'function') {
        initCharts();
    }
    
    // Configurar atualizações em tempo real
    setupRealTimeUpdates();
}

function updateSystemStats() {
    // Atualizar estatísticas via AJAX
    fetch('/sistema/dashboard/api/stats.php')
        .then(response => response.json())
        .then(data => {
            updateStatCards(data);
        })
        .catch(error => {
            console.warn('Erro ao atualizar estatísticas:', error);
        });
}

function updateStatCards(stats) {
    // Atualizar cards de estatísticas
    Object.keys(stats).forEach(key => {
        const element = document.querySelector(`[data-stat="${key}"]`);
        if (element) {
            element.textContent = stats[key];
        }
    });
}

function setupRealTimeUpdates() {
    // Configurar WebSocket ou polling para atualizações em tempo real
    // Implementação futura
}

// Expor funções globalmente para compatibilidade
window.dashboardUtils = {
    updateStats: updateSystemStats,
    updateStatCards: updateStatCards
};
</script>

<?php
// Renderizar fim do layout
$layout->renderLayoutEnd();
?>