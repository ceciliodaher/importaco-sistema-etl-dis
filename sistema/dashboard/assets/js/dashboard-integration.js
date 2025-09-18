/**
 * ================================================================================
 * INTEGRAÇÃO DASHBOARD - SISTEMA MANUAL CONTROL
 * Conecta manual-control-system.js com charts.js e dashboard.js existentes
 * ================================================================================
 */

/**
 * Wrapper de Integração
 * Conecta o novo sistema com componentes existentes
 */
class DashboardIntegration {
    constructor() {
        this.manualControl = null;
        this.expertzyCharts = null;
        this.isInitialized = false;
        
        this.init();
    }
    
    async init() {
        // Aguardar sistemas estarem prontos
        await this.waitForSystems();
        
        // Configurar integrações
        this.setupIntegrations();
        
        // Configurar event listeners
        this.setupEventListeners();
        
        this.isInitialized = true;
        console.log('✅ DashboardIntegration inicializado');
    }
    
    async waitForSystems() {
        // Aguardar manual control system
        let attempts = 0;
        while (!window.manualControlSystem && attempts < 50) {
            await new Promise(resolve => setTimeout(resolve, 100));
            attempts++;
        }
        
        if (window.manualControlSystem) {
            this.manualControl = window.manualControlSystem;
            console.log('✅ Manual Control System conectado');
        } else {
            console.error('❌ Manual Control System não encontrado');
            return;
        }
        
        // Aguardar charts system (opcional)
        attempts = 0;
        while (!window.expertzyCharts && attempts < 30) {
            await new Promise(resolve => setTimeout(resolve, 100));
            attempts++;
        }
        
        if (window.expertzyCharts) {
            this.expertzyCharts = window.expertzyCharts;
            console.log('✅ Expertzy Charts System conectado');
        } else {
            console.warn('⚠️ Expertzy Charts System não encontrado (opcional)');
        }
    }
    
    setupIntegrations() {
        if (!this.manualControl) return;
        
        // Integrar com charts.js existente
        this.integrateWithCharts();
        
        // Integrar com dashboard.js existente
        this.integrateWithDashboard();
        
        // Configurar auto-refresh inteligente
        this.setupIntelligentAutoRefresh();
        
        // Configurar validações condicionais
        this.setupConditionalValidations();
    }
    
    integrateWithCharts() {
        if (!this.expertzyCharts) return;
        
        const state = this.manualControl.getState();
        const feedback = this.manualControl.getFeedback();
        
        // Sobrescrever método de carregamento dos gráficos
        const originalLoadChartData = this.expertzyCharts.loadChartData;
        
        this.expertzyCharts.loadChartData = async function(manualTrigger = false) {
            // Verificar se é permitido carregar
            if (!manualTrigger && !state.canLoadCharts()) {
                console.log('🚫 Carregamento automático bloqueado - dados insuficientes');
                this.showEmptyStates();
                return;
            }
            
            // Se manual, verificar permissões
            if (manualTrigger && !state.canLoadCharts()) {
                feedback.showToast(
                    'Não é possível carregar gráficos',
                    'warning',
                    { subtitle: 'Verifique se há dados suficientes no banco' }
                );
                return;
            }
            
            // Executar carregamento original
            try {
                await originalLoadChartData.call(this, manualTrigger);
                
                // Atualizar estado no manual control
                state.updateChartsState({
                    loaded: true,
                    types: state.charts.available_types,
                    last_load: new Date().toISOString()
                });
                
            } catch (error) {
                console.error('Erro no carregamento dos gráficos:', error);
                feedback.showToast('Erro ao carregar gráficos', 'error');
            }
        };
        
        // Sobrescrever método de refresh individual
        const originalRefreshChart = this.expertzyCharts.refreshChart;
        
        this.expertzyCharts.refreshChart = async function(chartType, manualTrigger = false) {
            if (!manualTrigger) {
                console.log(`🚫 Refresh automático de ${chartType} bloqueado`);
                return;
            }
            
            return originalRefreshChart.call(this, chartType, manualTrigger);
        };
        
        // Expor método manual global
        window.loadChartsManually = () => {
            if (this.expertzyCharts) {
                return this.expertzyCharts.loadChartData(true);
            } else {
                feedback.showToast('Sistema de gráficos não disponível', 'error');
            }
        };
        
        console.log('✅ Integração com Charts System configurada');
    }
    
    integrateWithDashboard() {
        // Integrar com funções existentes do dashboard
        const state = this.manualControl.getState();
        const feedback = this.manualControl.getFeedback();
        
        // Sobrescrever função de refresh global se existir
        if (typeof window.refreshAllCharts === 'function') {
            const originalRefreshAll = window.refreshAllCharts;
            
            window.refreshAllCharts = function() {
                if (!state.canLoadCharts()) {
                    feedback.showToast(
                        'Sistema não pronto para refresh completo',
                        'warning',
                        { subtitle: 'Verifique status do banco de dados' }
                    );
                    return;
                }
                
                // Usar sistema manual control
                return window.manualControlSystem.forceRefresh();
            };
        }
        
        // Integrar com loadStatsCards se existir
        if (typeof window.loadStatsCards === 'function') {
            const originalLoadStats = window.loadStatsCards;
            
            window.loadStatsCards = function() {
                if (!state.canLoadStats()) {
                    feedback.showToast(
                        'Dados insuficientes para estatísticas',
                        'warning'
                    );
                    return;
                }
                
                return originalLoadStats();
            };
        }
        
        console.log('✅ Integração com Dashboard configurada');
    }
    
    setupIntelligentAutoRefresh() {
        const autoRefresh = this.manualControl.getAutoRefresh();
        const state = this.manualControl.getState();
        
        // Configurar auto-refresh inteligente
        autoRefresh.setCallbacks({
            onRefresh: async () => {
                // Auto-refresh apenas se sistema estiver pronto
                if (state.canLoadCharts() || state.canLoadStats()) {
                    return this.manualControl.forceRefresh();
                } else {
                    console.log('🔄 Auto-refresh pulado - sistema não pronto');
                    return Promise.resolve();
                }
            },
            
            onStart: (interval) => {
                console.log(`🔄 Auto-refresh inteligente iniciado (${interval/1000}s)`);
            },
            
            onError: (error) => {
                console.error('Erro no auto-refresh:', error);
                
                // Pausar auto-refresh em caso de erro persistente
                if (error.message.includes('HTTP 5')) {
                    autoRefresh.stop();
                    this.manualControl.getFeedback().showToast(
                        'Auto-refresh pausado devido a erro no servidor',
                        'warning',
                        { persistent: true }
                    );
                }
            }
        });
        
        console.log('✅ Auto-refresh inteligente configurado');
    }
    
    setupConditionalValidations() {
        const state = this.manualControl.getState();
        
        // Escutar mudanças de estado para atualizar UI
        state.on('database-changed', (newState, oldState) => {
            this.updateUIBasedOnDatabaseChange(newState, oldState);
        });
        
        state.on('charts-loaded', (chartsState) => {
            this.updateChartsUI(chartsState);
        });
        
        state.on('operation-started', (operationId) => {
            this.updateOperationUI(operationId, 'started');
        });
        
        state.on('operation-completed', (operationId, result) => {
            this.updateOperationUI(operationId, 'completed', result);
        });
        
        console.log('✅ Validações condicionais configuradas');
    }
    
    setupEventListeners() {
        // Event listeners para integração
        document.addEventListener('chartsDataUpdated', (event) => {
            console.log('📊 Dados de gráficos atualizados via evento', event.detail);
        });
        
        // Escutar cliques nos botões de refresh individuais dos gráficos
        document.addEventListener('click', (event) => {
            if (event.target.closest('.chart-control-btn[data-action="refresh"]')) {
                const chartContainer = event.target.closest('[data-chart]');
                if (chartContainer) {
                    const chartType = chartContainer.dataset.chart;
                    this.handleIndividualChartRefresh(chartType);
                }
            }
        });
        
        // Escutar mudanças nos filtros de gráficos
        this.setupFiltersIntegration();
        
        console.log('✅ Event listeners configurados');
    }
    
    setupFiltersIntegration() {
        // Integrar filtros existentes com sistema manual
        const filterElements = {
            period: document.getElementById('periodFilter'),
            currency: document.getElementById('currencyFilter'),
            state: document.getElementById('stateFilter'),
            taxRegime: document.getElementById('taxRegimeFilter')
        };
        
        Object.entries(filterElements).forEach(([type, element]) => {
            if (element) {
                element.addEventListener('change', () => {
                    // Só aplicar filtros se gráficos estiverem carregados
                    if (this.manualControl.getState().charts.loaded) {
                        this.applyFiltersToCharts();
                    }
                });
            }
        });
    }
    
    // ================================================================
    // HANDLERS DE ATUALIZAÇÃO DE UI
    // ================================================================
    
    updateUIBasedOnDatabaseChange(newState, oldState) {
        // Atualizar indicadores visuais
        const indicators = document.querySelectorAll('[data-status-indicator]');
        indicators.forEach(indicator => {
            const type = indicator.dataset.statusIndicator;
            
            switch (type) {
                case 'database':
                    indicator.className = `status-indicator ${newState.connected ? 'success' : 'error'}`;
                    break;
                case 'data':
                    indicator.className = `status-indicator ${newState.sufficient ? 'success' : 'warning'}`;
                    break;
            }
        });
        
        // Atualizar contadores
        const disCounters = document.querySelectorAll('[data-counter="dis"]');
        disCounters.forEach(counter => {
            counter.textContent = newState.dis_count.toLocaleString('pt-BR');
        });
        
        // Mostrar mudanças significativas
        if (oldState.dis_count !== newState.dis_count) {
            const change = newState.dis_count - oldState.dis_count;
            if (change > 0) {
                this.manualControl.getFeedback().showToast(
                    `${change} nova(s) DI(s) detectada(s)`,
                    'info',
                    { subtitle: `Total: ${newState.dis_count}` }
                );
            }
        }
    }
    
    updateChartsUI(chartsState) {
        // Atualizar estado visual dos containers de gráficos
        const chartContainers = document.querySelectorAll('[data-chart]');
        chartContainers.forEach(container => {
            if (chartsState.loaded) {
                container.setAttribute('data-state', 'loaded');
                
                // Esconder empty states
                const emptyState = container.querySelector('.chart-empty');
                if (emptyState) {
                    emptyState.style.display = 'none';
                }
            }
        });
        
        // Habilitar controles de filtros
        const filterControls = document.querySelector('.charts-filters');
        if (filterControls && chartsState.loaded) {
            filterControls.classList.remove('disabled');
        }
    }
    
    updateOperationUI(operationId, status, result = null) {
        const button = document.getElementById(operationId.replace('-', ''));
        
        if (button) {
            switch (status) {
                case 'started':
                    button.disabled = true;
                    button.classList.add('loading');
                    break;
                    
                case 'completed':
                    button.disabled = false;
                    button.classList.remove('loading');
                    
                    if (result && !result.success) {
                        button.classList.add('error');
                        setTimeout(() => {
                            button.classList.remove('error');
                        }, 3000);
                    }
                    break;
            }
        }
    }
    
    async handleIndividualChartRefresh(chartType) {
        const state = this.manualControl.getState();
        
        if (!state.canLoadCharts()) {
            this.manualControl.getFeedback().showToast(
                `Não é possível atualizar gráfico ${chartType}`,
                'warning',
                { subtitle: 'Dados insuficientes' }
            );
            return;
        }
        
        try {
            if (this.expertzyCharts) {
                await this.expertzyCharts.refreshChart(chartType, true);
                this.manualControl.getFeedback().showToast(
                    `Gráfico ${chartType} atualizado`,
                    'success'
                );
            }
        } catch (error) {
            console.error(`Erro ao atualizar gráfico ${chartType}:`, error);
            this.manualControl.getFeedback().showToast(
                `Erro ao atualizar gráfico ${chartType}`,
                'error'
            );
        }
    }
    
    applyFiltersToCharts() {
        // Aplicar filtros selecionados aos gráficos
        if (this.expertzyCharts && typeof this.expertzyCharts.applyFilters === 'function') {
            const filters = this.getSelectedFilters();
            this.expertzyCharts.applyFilters(filters);
        }
    }
    
    getSelectedFilters() {
        const filters = {};
        
        // Period filter
        const periodActive = document.querySelector('#periodFilter .toggle-option.active');
        if (periodActive) {
            filters.period = periodActive.dataset.period;
        }
        
        // Currency filter
        const currencySelect = document.getElementById('currencyFilter');
        if (currencySelect) {
            filters.currency = currencySelect.value;
        }
        
        // State filter
        const stateSelect = document.getElementById('stateFilter');
        if (stateSelect) {
            filters.state = stateSelect.value;
        }
        
        // Tax regime filter
        const regimeActive = document.querySelector('#taxRegimeFilter .toggle-option.active');
        if (regimeActive) {
            filters.taxRegime = regimeActive.dataset.regime;
        }
        
        return filters;
    }
    
    // ================================================================
    // API PÚBLICA
    // ================================================================
    
    getManualControl() {
        return this.manualControl;
    }
    
    getChartsSystem() {
        return this.expertzyCharts;
    }
    
    isReady() {
        return this.isInitialized && this.manualControl !== null;
    }
    
    async forceSystemRefresh() {
        if (this.manualControl) {
            return this.manualControl.forceRefresh();
        }
    }
    
    getSystemStatus() {
        if (!this.manualControl) return null;
        
        const state = this.manualControl.getState();
        return {
            database: state.database,
            charts: state.charts,
            stats: state.stats,
            autoRefresh: state.autoRefresh,
            canLoadCharts: state.canLoadCharts(),
            canLoadStats: state.canLoadStats(),
            nextAction: state.getNextRecommendedAction()
        };
    }
}

// ================================================================
// INICIALIZAÇÃO
// ================================================================

let dashboardIntegration;

function initDashboardIntegration() {
    if (window.dashboardIntegration) {
        console.warn('DashboardIntegration já foi inicializado');
        return window.dashboardIntegration;
    }
    
    dashboardIntegration = new DashboardIntegration();
    
    // Expor globalmente
    window.dashboardIntegration = dashboardIntegration;
    
    // Compatibilidade - expor métodos principais
    window.getSystemStatus = () => dashboardIntegration.getSystemStatus();
    window.forceSystemRefresh = () => dashboardIntegration.forceSystemRefresh();
    
    console.log('✅ DashboardIntegration inicializado e exposto globalmente');
    
    return dashboardIntegration;
}

// Auto-inicialização após DOM pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        // Aguardar um pouco para outros sistemas carregarem
        setTimeout(initDashboardIntegration, 100);
    });
} else {
    setTimeout(initDashboardIntegration, 100);
}

// Export para uso como módulo
if (typeof module !== 'undefined' && module.exports) {
    module.exports = DashboardIntegration;
}