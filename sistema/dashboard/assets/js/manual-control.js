/**
 * ================================================================================
 * MANUAL CONTROL PANEL JAVASCRIPT
 * Sistema de controle manual para dashboard ETL DI's
 * ================================================================================
 */

class ManualControlPanel {
    constructor() {
        this.isAutoRefreshEnabled = false;
        this.refreshInterval = 60;
        this.refreshTimer = null;
        this.activeOperations = new Set();
        
        this.init();
    }

    init() {
        console.log('Inicializando Manual Control Panel...');
        
        // Aguardar DOM estar pronto
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setupEventListeners());
        } else {
            this.setupEventListeners();
        }
        
        // Carregar configurações salvas
        this.loadSavedSettings();
    }

    setupEventListeners() {
        // Botões de gestão de dados
        this.bindButton('btnImportXML', () => this.handleImportXML());
        this.bindButton('btnVerifyDatabase', () => this.handleVerifyDatabase());
        this.bindButton('btnClearCache', () => this.handleClearCache());
        
        // Botões de visualizações
        this.bindButton('btnLoadCharts', () => this.handleLoadCharts());
        this.bindButton('btnLoadStats', () => this.handleLoadStats());
        this.bindButton('btnRefreshAll', () => this.handleRefreshAll());
        
        // Configurações
        this.bindButton('btnAdvancedSettings', () => this.handleAdvancedSettings());
        
        // Toggle auto-refresh
        const autoRefreshToggle = document.getElementById('autoRefreshToggle');
        if (autoRefreshToggle) {
            autoRefreshToggle.addEventListener('change', (e) => {
                this.toggleAutoRefresh(e.target.checked);
            });
        }
        
        // Slider de intervalo
        const refreshInterval = document.getElementById('refreshInterval');
        if (refreshInterval) {
            refreshInterval.addEventListener('input', (e) => {
                this.updateRefreshInterval(parseInt(e.target.value));
            });
        }
        
        // Atalhos de teclado
        this.setupKeyboardShortcuts();
        
        console.log('Event listeners configurados com sucesso');
    }

    bindButton(id, handler) {
        const button = document.getElementById(id);
        if (button) {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                if (!button.disabled && !this.activeOperations.has(id)) {
                    handler();
                }
            });
        }
    }

    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + Shift + combinations
            if ((e.ctrlKey || e.metaKey) && e.shiftKey) {
                switch (e.key.toLowerCase()) {
                    case 'i':
                        e.preventDefault();
                        this.handleImportXML();
                        break;
                    case 'r':
                        e.preventDefault();
                        this.handleRefreshAll();
                        break;
                    case 'v':
                        e.preventDefault();
                        this.handleVerifyDatabase();
                        break;
                    case 'c':
                        e.preventDefault();
                        this.handleLoadCharts();
                        break;
                }
            }
        });
    }

    // ================================================================
    // GESTÃO DE DADOS
    // ================================================================

    async handleImportXML() {
        console.log('Iniciando importação de XML...');
        
        // Simular clique no upload zone existente
        const uploadZone = document.getElementById('uploadZone');
        const fileInput = document.getElementById('fileInput');
        
        if (fileInput) {
            fileInput.click();
        } else if (uploadZone) {
            uploadZone.click();
        } else {
            this.showFeedback('Upload não disponível no momento', 'warning');
        }
    }

    async handleVerifyDatabase() {
        console.log('Verificando status do banco de dados...');
        
        await this.executeOperation('btnVerifyDatabase', 'Verificando Banco', async () => {
            try {
                const response = await fetch('/sistema/dashboard/api/database-status.php', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const data = await response.json();
                
                if (data.success) {
                    this.showFeedback('Banco de dados verificado com sucesso', 'success');
                    this.updateSystemStatus(data.status);
                } else {
                    this.showFeedback(data.message || 'Erro na verificação do banco', 'error');
                }
                
            } catch (error) {
                console.error('Erro na verificação:', error);
                this.showFeedback('Erro ao conectar com o servidor', 'error');
            }
        });
    }

    async handleClearCache() {
        console.log('Limpando cache do sistema...');
        
        await this.executeOperation('btnClearCache', 'Limpando Cache', async () => {
            try {
                // Limpar localStorage
                localStorage.removeItem('etl_dashboard_cache');
                localStorage.removeItem('etl_charts_cache');
                
                // Limpar sessionStorage
                sessionStorage.clear();
                
                // Tentar limpar cache do servidor
                const response = await fetch('/sistema/dashboard/api/cache-clear.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
                
                if (response.ok) {
                    this.showFeedback('Cache limpo com sucesso', 'success');
                } else {
                    this.showFeedback('Cache local limpo (servidor indisponível)', 'warning');
                }
                
            } catch (error) {
                console.error('Erro ao limpar cache:', error);
                this.showFeedback('Cache local limpo', 'info');
            }
        });
    }

    // ================================================================
    // VISUALIZAÇÕES
    // ================================================================

    async handleLoadCharts() {
        console.log('Carregando gráficos...');
        
        await this.executeOperation('btnLoadCharts', 'Carregando Gráficos', async () => {
            try {
                // Verificar se o módulo de gráficos existe
                if (typeof window.expertzyCharts !== 'undefined') {
                    await window.expertzyCharts.loadChartData();
                    this.showFeedback('Gráficos carregados com sucesso', 'success');
                } else {
                    // Tentar carregar os gráficos manualmente
                    const response = await fetch('/sistema/dashboard/api/dashboard/charts.php');
                    
                    if (response.ok) {
                        const data = await response.json();
                        this.showFeedback('Dados de gráficos carregados', 'success');
                        
                        // Disparar evento para atualizar gráficos
                        document.dispatchEvent(new CustomEvent('chartsDataUpdated', { 
                            detail: data 
                        }));
                    } else {
                        throw new Error('Falha ao carregar dados dos gráficos');
                    }
                }
                
            } catch (error) {
                console.error('Erro ao carregar gráficos:', error);
                this.showFeedback('Erro ao carregar gráficos', 'error');
            }
        });
    }

    async handleLoadStats() {
        console.log('Carregando estatísticas...');
        
        await this.executeOperation('btnLoadStats', 'Carregando Estatísticas', async () => {
            try {
                const response = await fetch('/sistema/dashboard/api/stats.php');
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const data = await response.json();
                
                if (data.success) {
                    this.updateStatCards(data.stats);
                    this.showFeedback('Estatísticas atualizadas', 'success');
                } else {
                    throw new Error(data.message || 'Erro ao carregar estatísticas');
                }
                
            } catch (error) {
                console.error('Erro ao carregar estatísticas:', error);
                this.showFeedback('Erro ao carregar estatísticas', 'error');
            }
        });
    }

    async handleRefreshAll() {
        console.log('Atualizando todo o sistema...');
        
        await this.executeOperation('btnRefreshAll', 'Atualizando Sistema', async () => {
            try {
                // Executar todas as operações em paralelo
                const operations = [
                    this.verifyDatabaseStatus(),
                    this.loadStats(),
                    this.loadCharts()
                ];
                
                const results = await Promise.allSettled(operations);
                
                let successCount = 0;
                let errorCount = 0;
                
                results.forEach((result, index) => {
                    if (result.status === 'fulfilled') {
                        successCount++;
                    } else {
                        errorCount++;
                        console.error(`Operação ${index} falhou:`, result.reason);
                    }
                });
                
                if (errorCount === 0) {
                    this.showFeedback('Sistema atualizado completamente', 'success');
                } else if (successCount > 0) {
                    this.showFeedback(`Sistema parcialmente atualizado (${successCount}/${results.length})`, 'warning');
                } else {
                    this.showFeedback('Falha na atualização do sistema', 'error');
                }
                
            } catch (error) {
                console.error('Erro na atualização geral:', error);
                this.showFeedback('Erro na atualização do sistema', 'error');
            }
        });
    }

    // ================================================================
    // CONFIGURAÇÕES
    // ================================================================

    handleAdvancedSettings() {
        console.log('Abrindo configurações avançadas...');
        
        // Verificar se existe modal de configurações
        const modal = document.querySelector('[data-modal="advanced-settings"]');
        if (modal) {
            // Abrir modal existente
            modal.style.display = 'block';
        } else {
            // Criar interface de configurações inline
            this.showAdvancedSettingsPanel();
        }
    }

    showAdvancedSettingsPanel() {
        const panel = document.createElement('div');
        panel.className = 'advanced-settings-panel';
        panel.innerHTML = `
            <div class="settings-overlay">
                <div class="settings-modal">
                    <div class="settings-header">
                        <h3>Configurações Avançadas</h3>
                        <button class="close-settings" aria-label="Fechar">&times;</button>
                    </div>
                    <div class="settings-content">
                        <div class="setting-group">
                            <label>
                                <input type="checkbox" id="enableDebugMode"> 
                                Modo Debug
                            </label>
                            <small>Exibe logs detalhados no console</small>
                        </div>
                        <div class="setting-group">
                            <label>
                                <input type="checkbox" id="enableNotifications"> 
                                Notificações
                            </label>
                            <small>Receber notificações do sistema</small>
                        </div>
                        <div class="setting-group">
                            <label>Cache Duration (minutes):</label>
                            <input type="number" id="cacheDuration" min="1" max="60" value="15">
                        </div>
                    </div>
                    <div class="settings-actions">
                        <button class="btn btn-primary save-settings">Salvar</button>
                        <button class="btn btn-secondary cancel-settings">Cancelar</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(panel);
        
        // Event listeners para o modal
        panel.querySelector('.close-settings').addEventListener('click', () => {
            panel.remove();
        });
        
        panel.querySelector('.cancel-settings').addEventListener('click', () => {
            panel.remove();
        });
        
        panel.querySelector('.save-settings').addEventListener('click', () => {
            this.saveAdvancedSettings(panel);
            panel.remove();
        });
        
        // Fechar ao clicar fora
        panel.querySelector('.settings-overlay').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) {
                panel.remove();
            }
        });
    }

    saveAdvancedSettings(panel) {
        const settings = {
            debugMode: panel.querySelector('#enableDebugMode').checked,
            notifications: panel.querySelector('#enableNotifications').checked,
            cacheDuration: parseInt(panel.querySelector('#cacheDuration').value)
        };
        
        localStorage.setItem('etl_advanced_settings', JSON.stringify(settings));
        this.showFeedback('Configurações salvas', 'success');
        
        // Aplicar configurações
        if (settings.debugMode) {
            console.log('Modo debug ativado');
        }
    }

    toggleAutoRefresh(enabled) {
        this.isAutoRefreshEnabled = enabled;
        
        if (enabled) {
            this.startAutoRefresh();
            this.showFeedback('Auto-refresh ativado', 'info');
        } else {
            this.stopAutoRefresh();
            this.showFeedback('Auto-refresh desativado', 'info');
        }
        
        // Atualizar badge
        this.updateAutoRefreshBadge();
        
        // Salvar configuração
        this.saveSettings();
    }

    updateRefreshInterval(seconds) {
        this.refreshInterval = seconds;
        
        // Atualizar display
        const intervalValue = document.querySelector('.interval-value');
        if (intervalValue) {
            intervalValue.textContent = `${seconds}s`;
        }
        
        // Reiniciar timer se estiver ativo
        if (this.isAutoRefreshEnabled) {
            this.stopAutoRefresh();
            this.startAutoRefresh();
        }
        
        // Salvar configuração
        this.saveSettings();
    }

    startAutoRefresh() {
        this.stopAutoRefresh(); // Limpar timer existente
        
        this.refreshTimer = setInterval(() => {
            console.log('Auto-refresh executando...');
            this.handleRefreshAll();
        }, this.refreshInterval * 1000);
    }

    stopAutoRefresh() {
        if (this.refreshTimer) {
            clearInterval(this.refreshTimer);
            this.refreshTimer = null;
        }
    }

    updateAutoRefreshBadge() {
        const badge = document.querySelector('.section-badge.auto-refresh');
        if (badge) {
            badge.textContent = `Auto-refresh: ${this.isAutoRefreshEnabled ? 'ON' : 'OFF'}`;
            badge.className = `section-badge auto-refresh ${this.isAutoRefreshEnabled ? 'active' : 'inactive'}`;
        }
    }

    // ================================================================
    // UTILITÁRIOS
    // ================================================================

    async executeOperation(buttonId, title, operation) {
        const button = document.getElementById(buttonId);
        const progressPanel = document.getElementById('actionProgress');
        
        try {
            // Marcar operação como ativa
            this.activeOperations.add(buttonId);
            
            // Desabilitar botão
            if (button) {
                button.disabled = true;
                button.classList.add('loading');
            }
            
            // Mostrar progresso
            this.showProgress(title, 'Executando operação...');
            
            // Executar operação
            await operation();
            
        } catch (error) {
            console.error(`Erro na operação ${buttonId}:`, error);
            throw error;
        } finally {
            // Limpar estado
            this.activeOperations.delete(buttonId);
            
            // Reabilitar botão
            if (button) {
                button.disabled = false;
                button.classList.remove('loading');
            }
            
            // Esconder progresso
            this.hideProgress();
        }
    }

    showProgress(title, description, percent = 0) {
        const progressPanel = document.getElementById('actionProgress');
        const progressTitle = document.getElementById('progressTitle');
        const progressDescription = document.getElementById('progressDescription');
        const progressFill = document.getElementById('progressFill');
        const progressPercent = document.getElementById('progressPercent');
        
        if (progressPanel) {
            if (progressTitle) progressTitle.textContent = title;
            if (progressDescription) progressDescription.textContent = description;
            if (progressFill) progressFill.style.width = `${percent}%`;
            if (progressPercent) progressPercent.textContent = `${percent}%`;
            
            progressPanel.style.display = 'block';
        }
    }

    hideProgress() {
        const progressPanel = document.getElementById('actionProgress');
        if (progressPanel) {
            setTimeout(() => {
                progressPanel.style.display = 'none';
            }, 500);
        }
    }

    showFeedback(message, type = 'info', duration = 5000) {
        const container = document.getElementById('controlFeedback');
        if (!container) return;
        
        const messageElement = document.createElement('div');
        messageElement.className = `feedback-message ${type}`;
        messageElement.innerHTML = `
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                ${this.getFeedbackIcon(type)}
            </svg>
            <span>${message}</span>
        `;
        
        container.appendChild(messageElement);
        
        // Remover automaticamente
        setTimeout(() => {
            if (messageElement.parentNode) {
                messageElement.style.animation = 'slideOutRight 0.3s ease';
                setTimeout(() => {
                    messageElement.remove();
                }, 300);
            }
        }, duration);
        
        // Permitir fechar clicando
        messageElement.addEventListener('click', () => {
            messageElement.remove();
        });
    }

    getFeedbackIcon(type) {
        const icons = {
            success: '<path d="M9 12L11 14L15 10M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2"/>',
            error: '<path d="M12 8V12M12 16H12.01M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2"/>',
            warning: '<path d="M12 9V13M12 17H12.01M10.29 3.86L1.82 18C1.64466 18.3024 1.55685 18.6453 1.56567 18.9928C1.57449 19.3403 1.67953 19.6781 1.87013 19.9725C2.06073 20.2669 2.33033 20.5068 2.6492 20.6687C2.96806 20.8305 3.32405 20.9089 3.68 20.896H20.32C20.676 20.9089 21.0319 20.8305 21.3508 20.6687C21.6697 20.5068 21.9393 20.2669 22.1299 19.9725C22.3205 19.6781 22.4255 19.3403 22.4343 18.9928C22.4432 18.6453 22.3553 18.3024 22.18 18L13.71 3.86C13.5317 3.56611 13.2807 3.32312 12.9812 3.15448C12.6817 2.98585 12.3437 2.89725 12 2.89725C11.6563 2.89725 11.3183 2.98585 11.0188 3.15448C10.7193 3.32312 10.4683 3.56611 10.29 3.86V3.86Z" stroke="currentColor" stroke-width="2"/>',
            info: '<path d="M13 16H12V12H11M12 8H12.01M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2"/>'
        };
        
        return icons[type] || icons.info;
    }

    // ================================================================
    // MÉTODOS AUXILIARES
    // ================================================================

    async verifyDatabaseStatus() {
        const response = await fetch('/sistema/dashboard/api/database-status.php');
        return response.json();
    }

    async loadStats() {
        const response = await fetch('/sistema/dashboard/api/stats.php');
        return response.json();
    }

    async loadCharts() {
        if (typeof window.expertzyCharts !== 'undefined') {
            return window.expertzyCharts.loadChartData();
        }
        return Promise.resolve();
    }

    updateStatCards(stats) {
        Object.keys(stats).forEach(key => {
            const element = document.querySelector(`[data-stat="${key}"]`);
            if (element) {
                element.textContent = typeof stats[key] === 'number' 
                    ? stats[key].toLocaleString() 
                    : stats[key];
            }
        });
    }

    updateSystemStatus(status) {
        // Atualizar indicadores de status
        Object.keys(status).forEach(key => {
            const indicator = document.querySelector(`[data-status="${key}"]`);
            if (indicator) {
                indicator.className = `status-indicator ${status[key] ? 'online' : 'offline'}`;
            }
        });
    }

    saveSettings() {
        const settings = {
            autoRefresh: this.isAutoRefreshEnabled,
            refreshInterval: this.refreshInterval
        };
        
        // Salvar em cookies para persistir entre sessões
        document.cookie = `etl_auto_refresh=${this.isAutoRefreshEnabled}; path=/; max-age=2592000`; // 30 dias
        document.cookie = `etl_refresh_interval=${this.refreshInterval}; path=/; max-age=2592000`;
        
        localStorage.setItem('etl_control_settings', JSON.stringify(settings));
    }

    loadSavedSettings() {
        try {
            const saved = localStorage.getItem('etl_control_settings');
            if (saved) {
                const settings = JSON.parse(saved);
                this.isAutoRefreshEnabled = settings.autoRefresh || false;
                this.refreshInterval = settings.refreshInterval || 60;
                
                // Aplicar configurações
                const autoRefreshToggle = document.getElementById('autoRefreshToggle');
                if (autoRefreshToggle) {
                    autoRefreshToggle.checked = this.isAutoRefreshEnabled;
                }
                
                const refreshIntervalSlider = document.getElementById('refreshInterval');
                if (refreshIntervalSlider) {
                    refreshIntervalSlider.value = this.refreshInterval;
                }
                
                this.updateAutoRefreshBadge();
                
                if (this.isAutoRefreshEnabled) {
                    this.startAutoRefresh();
                }
            }
        } catch (error) {
            console.warn('Erro ao carregar configurações salvas:', error);
        }
    }
}

// ================================================================
// INICIALIZAÇÃO GLOBAL
// ================================================================

// Inicializar quando o DOM estiver pronto
let manualControlPanel;

function initManualControlPanel() {
    manualControlPanel = new ManualControlPanel();
    
    // Expor globalmente para compatibilidade
    window.manualControlPanel = manualControlPanel;
    
    console.log('Manual Control Panel inicializado com sucesso');
}

// Auto-inicializar
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initManualControlPanel);
} else {
    initManualControlPanel();
}

// CSS adicional para estados de loading
const additionalStyles = document.createElement('style');
additionalStyles.textContent = `
    .control-btn.loading {
        opacity: 0.7;
        pointer-events: none;
        position: relative;
    }
    
    .control-btn.loading::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 16px;
        height: 16px;
        margin: -8px 0 0 -8px;
        border: 2px solid transparent;
        border-top: 2px solid currentColor;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    .advanced-settings-panel .settings-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .advanced-settings-panel .settings-modal {
        background: white;
        border-radius: 16px;
        padding: 2rem;
        max-width: 500px;
        width: 90%;
        max-height: 80vh;
        overflow-y: auto;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    }
    
    .advanced-settings-panel .settings-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1.5rem;
    }
    
    .advanced-settings-panel .close-settings {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        padding: 0.5rem;
        border-radius: 8px;
    }
    
    .advanced-settings-panel .setting-group {
        margin-bottom: 1.5rem;
    }
    
    .advanced-settings-panel .setting-group label {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 500;
        margin-bottom: 0.25rem;
    }
    
    .advanced-settings-panel .setting-group small {
        color: #6b7280;
        font-size: 0.875rem;
    }
    
    .advanced-settings-panel .settings-actions {
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
        margin-top: 2rem;
    }
    
    @keyframes slideOutRight {
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
`;

document.head.appendChild(additionalStyles);