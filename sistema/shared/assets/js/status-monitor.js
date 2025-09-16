/**
 * ================================================================================
 * SISTEMA ETL DE DI's - MONITORAMENTO EM TEMPO REAL
 * Sistema JavaScript para monitoramento e atualizações automáticas de status
 * Padrão Expertzy
 * Versão: 1.0.0
 * ================================================================================
 */

class SystemStatusMonitor {
    
    constructor(options = {}) {
        this.options = {
            apiEndpoint: '/sistema/shared/api/system-status.php',
            refreshInterval: 30000, // 30 segundos
            quickRefreshInterval: 5000, // 5 segundos para dados críticos
            retryAttempts: 3,
            retryDelay: 2000,
            enableNotifications: true,
            enableSounds: false,
            persistData: true,
            debug: false,
            ...options
        };
        
        this.isActive = true;
        this.refreshTimer = null;
        this.quickRefreshTimer = null;
        this.retryCount = 0;
        this.lastStatus = null;
        this.subscribers = new Map();
        this.cache = new Map();
        
        // Bind methods
        this.refresh = this.refresh.bind(this);
        this.quickRefresh = this.quickRefresh.bind(this);
        this.handleVisibilityChange = this.handleVisibilityChange.bind(this);
        
        this.init();
    }
    
    /**
     * Inicializar monitor
     */
    init() {
        this.log('Inicializando Sistema de Monitoramento');
        
        // Carregar dados persistidos
        if (this.options.persistData) {
            this.loadPersistedData();
        }
        
        // Configurar eventos
        this.setupEventListeners();
        
        // Primeira verificação
        this.refresh();
        
        // Iniciar timers
        this.startTimers();
        
        this.log('Sistema de Monitoramento iniciado com sucesso');
    }
    
    /**
     * Configurar event listeners
     */
    setupEventListeners() {
        // Visibility change
        document.addEventListener('visibilitychange', this.handleVisibilityChange);
        
        // Antes de sair da página
        window.addEventListener('beforeunload', () => {
            this.stop();
        });
        
        // Online/offline events
        window.addEventListener('online', () => {
            this.log('Conexão restaurada');
            this.retryCount = 0;
            this.refresh();
        });
        
        window.addEventListener('offline', () => {
            this.log('Conexão perdida');
            this.notifySubscribers('connection', { status: 'offline' });
        });
        
        // Focus/blur events
        window.addEventListener('focus', () => {
            if (this.isActive) {
                this.refresh();
            }
        });
    }
    
    /**
     * Iniciar timers
     */
    startTimers() {
        this.stopTimers();
        
        // Timer principal (30s)
        this.refreshTimer = setInterval(this.refresh, this.options.refreshInterval);
        
        // Timer rápido para dados críticos (5s)
        this.quickRefreshTimer = setInterval(this.quickRefresh, this.options.quickRefreshInterval);
    }
    
    /**
     * Parar timers
     */
    stopTimers() {
        if (this.refreshTimer) {
            clearInterval(this.refreshTimer);
            this.refreshTimer = null;
        }
        
        if (this.quickRefreshTimer) {
            clearInterval(this.quickRefreshTimer);
            this.quickRefreshTimer = null;
        }
    }
    
    /**
     * Parar monitoramento
     */
    stop() {
        this.isActive = false;
        this.stopTimers();
        
        if (this.options.persistData) {
            this.persistData();
        }
        
        this.log('Sistema de Monitoramento parado');
    }
    
    /**
     * Iniciar monitoramento
     */
    start() {
        this.isActive = true;
        this.startTimers();
        this.refresh();
        
        this.log('Sistema de Monitoramento reiniciado');
    }
    
    /**
     * Refresh completo do status
     */
    async refresh(force = false) {
        if (!this.isActive && !force) {
            return;
        }
        
        try {
            this.log('Atualizando status do sistema');
            
            const response = await this.makeRequest('', { no_cache: force ? '1' : '0' });
            
            if (response.success) {
                this.handleStatusUpdate(response.data);
                this.retryCount = 0;
                
                // Cache por 2 minutos
                this.setCache('system_status', response.data, 120000);
                
                return response.data;
            } else {
                throw new Error(response.error?.message || 'Erro desconhecido');
            }
            
        } catch (error) {
            this.handleError('refresh', error);
            return null;
        }
    }
    
    /**
     * Refresh rápido para dados críticos
     */
    async quickRefresh() {
        if (!this.isActive) {
            return;
        }
        
        try {
            // Verificar apenas performance e health
            const [performance, health] = await Promise.all([
                this.makeRequest('/performance'),
                this.makeRequest('/health')
            ]);
            
            if (performance.success && health.success) {
                this.notifySubscribers('performance', performance.data);
                this.notifySubscribers('health', health.data);
                
                // Cache por 30 segundos
                this.setCache('performance', performance.data, 30000);
                this.setCache('health', health.data, 30000);
            }
            
        } catch (error) {
            this.handleError('quickRefresh', error);
        }
    }
    
    /**
     * Obter status de módulo específico
     */
    async getModuleStatus(moduleKey) {
        try {
            const response = await this.makeRequest(`/modules/${moduleKey}`);
            
            if (response.success) {
                this.notifySubscribers('module', response.data);
                return response.data;
            } else {
                throw new Error(response.error?.message || 'Erro ao obter status do módulo');
            }
            
        } catch (error) {
            this.handleError('getModuleStatus', error);
            return null;
        }
    }
    
    /**
     * Fazer requisição à API
     */
    async makeRequest(endpoint = '', params = {}) {
        const url = new URL(this.options.apiEndpoint + endpoint, window.location.origin);
        
        // Adicionar parâmetros
        Object.keys(params).forEach(key => {
            url.searchParams.append(key, params[key]);
        });
        
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 10000); // 10s timeout
        
        try {
            const response = await fetch(url.toString(), {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                signal: controller.signal
            });
            
            clearTimeout(timeoutId);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            return data;
            
        } catch (error) {
            clearTimeout(timeoutId);
            
            if (error.name === 'AbortError') {
                throw new Error('Timeout na requisição');
            }
            
            throw error;
        }
    }
    
    /**
     * Lidar com atualização de status
     */
    handleStatusUpdate(data) {
        const previousStatus = this.lastStatus;
        this.lastStatus = data;
        
        // Notificar subscribers
        this.notifySubscribers('status', data);
        
        // Verificar mudanças significativas
        if (previousStatus) {
            this.checkStatusChanges(previousStatus, data);
        }
        
        // Atualizar elementos DOM automaticamente
        this.updateDOMElements(data);
        
        this.log('Status atualizado', data.overall_status);
    }
    
    /**
     * Verificar mudanças de status
     */
    checkStatusChanges(previous, current) {
        // Status geral
        if (previous.overall_status !== current.overall_status) {
            this.handleStatusChange('system', {
                from: previous.overall_status,
                to: current.overall_status,
                timestamp: current.timestamp
            });
        }
        
        // Módulos
        if (previous.modules && current.modules) {
            Object.keys(current.modules).forEach(moduleKey => {
                const prevModule = previous.modules[moduleKey];
                const currModule = current.modules[moduleKey];
                
                if (prevModule && prevModule.status !== currModule.status) {
                    this.handleStatusChange('module', {
                        module: moduleKey,
                        from: prevModule.status,
                        to: currModule.status,
                        timestamp: current.timestamp
                    });
                }
            });
        }
        
        // Componentes
        if (previous.components && current.components) {
            Object.keys(current.components).forEach(componentKey => {
                const prevComponent = previous.components[componentKey];
                const currComponent = current.components[componentKey];
                
                if (prevComponent && prevComponent.status !== currComponent.status) {
                    this.handleStatusChange('component', {
                        component: componentKey,
                        from: prevComponent.status,
                        to: currComponent.status,
                        timestamp: current.timestamp
                    });
                }
            });
        }
    }
    
    /**
     * Lidar com mudança de status
     */
    handleStatusChange(type, change) {
        this.log(`Mudança de status ${type}:`, change);
        
        // Notificar subscribers
        this.notifySubscribers('statusChange', { type, change });
        
        // Mostrar notificação se habilitada
        if (this.options.enableNotifications) {
            this.showNotification(type, change);
        }
        
        // Tocar som se habilitado
        if (this.options.enableSounds) {
            this.playNotificationSound(change.to);
        }
    }
    
    /**
     * Lidar com erros
     */
    handleError(operation, error) {
        this.log(`Erro em ${operation}:`, error.message);
        
        this.retryCount++;
        
        // Notificar subscribers
        this.notifySubscribers('error', {
            operation,
            error: error.message,
            retryCount: this.retryCount
        });
        
        // Tentar novamente se necessário
        if (this.retryCount < this.options.retryAttempts) {
            setTimeout(() => {
                if (operation === 'refresh') {
                    this.refresh(true);
                }
            }, this.options.retryDelay * this.retryCount);
        } else {
            this.log(`Máximo de tentativas atingido para ${operation}`);
        }
    }
    
    /**
     * Atualizar elementos DOM automaticamente
     */
    updateDOMElements(data) {
        // Atualizar indicadores de status
        const statusIndicators = document.querySelectorAll('[data-status-indicator]');
        statusIndicators.forEach(indicator => {
            const type = indicator.dataset.statusIndicator;
            let status = null;
            
            switch (type) {
                case 'system':
                    status = data.overall_status;
                    break;
                case 'database':
                    status = data.database?.status || 'unknown';
                    break;
                default:
                    if (data.modules && data.modules[type]) {
                        status = data.modules[type].status;
                    } else if (data.components && data.components[type]) {
                        status = data.components[type].status;
                    }
            }
            
            if (status) {
                this.updateStatusIndicator(indicator, status);
            }
        });
        
        // Atualizar métricas de performance
        this.updatePerformanceMetrics(data.performance || {});
        
        // Atualizar timestamps
        const timestampElements = document.querySelectorAll('[data-timestamp]');
        timestampElements.forEach(element => {
            element.textContent = new Date(data.timestamp * 1000).toLocaleTimeString();
        });
    }
    
    /**
     * Atualizar indicador de status
     */
    updateStatusIndicator(element, status) {
        // Remover classes de status anteriores
        element.classList.remove('status-online', 'status-offline', 'status-warning', 'status-error', 'status-developing', 'status-planned');
        
        // Adicionar nova classe de status
        element.classList.add(`status-${status}`);
        
        // Atualizar texto se houver
        const textElement = element.querySelector('.status-text');
        if (textElement) {
            textElement.textContent = this.capitalizeFirst(status);
        }
        
        // Animar mudança
        element.style.transform = 'scale(1.05)';
        setTimeout(() => {
            element.style.transform = 'scale(1)';
        }, 200);
    }
    
    /**
     * Atualizar métricas de performance
     */
    updatePerformanceMetrics(performance) {
        // Memória
        if (performance.memory_usage) {
            this.updateMetric('memory', performance.memory_usage.percentage, '%');
        }
        
        // Disco
        if (performance.disk_space) {
            this.updateMetric('disk', performance.disk_space.percentage, '%');
        }
        
        // Tempo de resposta
        if (performance.response_time !== undefined) {
            this.updateMetric('response', performance.response_time, 'ms');
        }
    }
    
    /**
     * Atualizar métrica específica
     */
    updateMetric(type, value, unit) {
        const metricElements = document.querySelectorAll(`[data-metric="${type}"]`);
        
        metricElements.forEach(element => {
            const valueElement = element.querySelector('.metric-value');
            const fillElement = element.querySelector('.metric-fill');
            
            if (valueElement) {
                valueElement.textContent = value + unit;
            }
            
            if (fillElement && unit === '%') {
                fillElement.style.width = value + '%';
                
                // Cores baseadas no valor
                if (value > 90) {
                    fillElement.style.background = '#ef4444'; // Vermelho
                } else if (value > 70) {
                    fillElement.style.background = '#f59e0b'; // Amarelo
                } else {
                    fillElement.style.background = '#10b981'; // Verde
                }
            }
        });
    }
    
    /**
     * Mostrar notificação
     */
    showNotification(type, change) {
        let message = '';
        let notificationType = 'info';
        
        switch (type) {
            case 'system':
                message = `Sistema ${change.from} → ${change.to}`;
                notificationType = change.to === 'online' ? 'success' : 'warning';
                break;
                
            case 'module':
                message = `Módulo ${change.module}: ${change.from} → ${change.to}`;
                notificationType = change.to === 'online' ? 'success' : 'warning';
                break;
                
            case 'component':
                message = `Componente ${change.component}: ${change.from} → ${change.to}`;
                notificationType = change.to === 'online' ? 'success' : 'error';
                break;
        }
        
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification('Sistema ETL DI\'s', {
                body: message,
                icon: '/sistema/shared/assets/images/logo-expertzy.png'
            });
        } else {
            this.showToastNotification(message, notificationType);
        }
    }
    
    /**
     * Mostrar notificação toast
     */
    showToastNotification(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `status-toast status-toast-${type}`;
        toast.innerHTML = `
            <div class="toast-content">
                <i data-feather="${this.getToastIcon(type)}"></i>
                <span>${message}</span>
            </div>
            <button class="toast-close" onclick="this.parentElement.remove()">×</button>
        `;
        
        // Estilos inline
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${this.getToastColor(type)};
            color: white;
            padding: 12px 16px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 10000;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            max-width: 300px;
            animation: slideInRight 0.3s ease;
        `;
        
        document.body.appendChild(toast);
        
        // Remover automaticamente
        setTimeout(() => {
            if (toast.parentNode) {
                toast.style.animation = 'slideOutRight 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }
        }, 5000);
        
        // Reinicializar ícones
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
    }
    
    /**
     * Tocar som de notificação
     */
    playNotificationSound(status) {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();
        
        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);
        
        // Frequências diferentes para diferentes status
        const frequencies = {
            'online': 800,
            'warning': 600,
            'error': 400,
            'offline': 300
        };
        
        oscillator.frequency.setValueAtTime(frequencies[status] || 500, audioContext.currentTime);
        oscillator.type = 'sine';
        
        gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);
        
        oscillator.start(audioContext.currentTime);
        oscillator.stop(audioContext.currentTime + 0.5);
    }
    
    /**
     * Lidar com mudança de visibilidade
     */
    handleVisibilityChange() {
        if (document.hidden) {
            this.log('Página oculta - pausando monitoramento');
            this.stopTimers();
        } else {
            this.log('Página visível - retomando monitoramento');
            this.startTimers();
            this.refresh(); // Refresh imediato ao voltar
        }
    }
    
    /**
     * Sistema de subscribers
     */
    subscribe(event, callback) {
        if (!this.subscribers.has(event)) {
            this.subscribers.set(event, new Set());
        }
        
        this.subscribers.get(event).add(callback);
        
        // Retornar função para unsubscribe
        return () => {
            this.subscribers.get(event)?.delete(callback);
        };
    }
    
    /**
     * Notificar subscribers
     */
    notifySubscribers(event, data) {
        const callbacks = this.subscribers.get(event);
        if (callbacks) {
            callbacks.forEach(callback => {
                try {
                    callback(data);
                } catch (error) {
                    this.log(`Erro em subscriber para ${event}:`, error);
                }
            });
        }
    }
    
    /**
     * Sistema de cache
     */
    setCache(key, data, ttl = 60000) {
        this.cache.set(key, {
            data,
            expires: Date.now() + ttl
        });
    }
    
    getCache(key) {
        const cached = this.cache.get(key);
        
        if (cached && cached.expires > Date.now()) {
            return cached.data;
        }
        
        this.cache.delete(key);
        return null;
    }
    
    /**
     * Persistir dados
     */
    persistData() {
        if (!this.lastStatus) return;
        
        try {
            localStorage.setItem('etl_system_status', JSON.stringify({
                status: this.lastStatus,
                timestamp: Date.now()
            }));
        } catch (error) {
            this.log('Erro ao persistir dados:', error);
        }
    }
    
    /**
     * Carregar dados persistidos
     */
    loadPersistedData() {
        try {
            const data = localStorage.getItem('etl_system_status');
            if (data) {
                const parsed = JSON.parse(data);
                
                // Verificar se dados não são muito antigos (1 hora)
                if (Date.now() - parsed.timestamp < 3600000) {
                    this.lastStatus = parsed.status;
                    this.log('Dados persistidos carregados');
                }
            }
        } catch (error) {
            this.log('Erro ao carregar dados persistidos:', error);
        }
    }
    
    /**
     * Utilitários
     */
    getToastIcon(type) {
        const icons = {
            success: 'check-circle',
            warning: 'alert-triangle',
            error: 'x-circle',
            info: 'info'
        };
        return icons[type] || 'info';
    }
    
    getToastColor(type) {
        const colors = {
            success: '#10b981',
            warning: '#f59e0b',
            error: '#ef4444',
            info: '#3b82f6'
        };
        return colors[type] || '#3b82f6';
    }
    
    capitalizeFirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }
    
    log(...args) {
        if (this.options.debug) {
            console.log('[StatusMonitor]', ...args);
        }
    }
    
    /**
     * API pública
     */
    getLastStatus() {
        return this.lastStatus;
    }
    
    isRunning() {
        return this.isActive && this.refreshTimer !== null;
    }
    
    forceRefresh() {
        return this.refresh(true);
    }
    
    setRefreshInterval(interval) {
        this.options.refreshInterval = interval;
        if (this.isActive) {
            this.startTimers();
        }
    }
}

// Adicionar estilos CSS para notificações
const statusMonitorStyles = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
    
    .status-toast {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    
    .toast-content {
        display: flex;
        align-items: center;
        gap: 8px;
        flex: 1;
    }
    
    .toast-close {
        background: none;
        border: none;
        color: white;
        font-size: 18px;
        cursor: pointer;
        padding: 0;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0.7;
    }
    
    .toast-close:hover {
        opacity: 1;
    }
`;

// Injetar estilos
if (!document.querySelector('#status-monitor-styles')) {
    const styleSheet = document.createElement('style');
    styleSheet.id = 'status-monitor-styles';
    styleSheet.textContent = statusMonitorStyles;
    document.head.appendChild(styleSheet);
}

// Exportar para uso global
window.SystemStatusMonitor = SystemStatusMonitor;

// Auto-inicializar se configurado
if (window.AUTO_INIT_STATUS_MONITOR !== false) {
    // Aguardar DOM carregar
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.statusMonitor = new SystemStatusMonitor();
        });
    } else {
        window.statusMonitor = new SystemStatusMonitor();
    }
}

// Solicitar permissão para notificações
if ('Notification' in window && Notification.permission === 'default') {
    Notification.requestPermission();
}