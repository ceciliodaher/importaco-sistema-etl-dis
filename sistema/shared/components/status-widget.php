<?php
/**
 * ================================================================================
 * SISTEMA ETL DE DI's - WIDGET DE STATUS GLOBAL
 * Componente reutilizável para monitoramento de status em tempo real
 * Padrão Expertzy
 * Versão: 1.0.0
 * ================================================================================
 */

// Previne acesso direto
if (!defined('ETL_SYSTEM')) {
    define('ETL_SYSTEM', true);
}

// Incluir dependências
require_once __DIR__ . '/../utils/module-status.php';

class StatusWidget {
    
    private $moduleStatus;
    private $widgetId;
    private $options;
    
    public function __construct($widgetId = 'status-widget', $options = []) {
        $this->moduleStatus = getModuleStatus();
        $this->widgetId = $widgetId;
        $this->options = array_merge([
            'show_details' => true,
            'show_performance' => true,
            'show_modules' => true,
            'show_components' => true,
            'compact_mode' => false,
            'refresh_interval' => 30000, // 30 segundos
            'animate' => true,
            'theme' => 'default' // default, compact, minimal
        ], $options);
    }
    
    /**
     * Renderizar widget completo
     */
    public function render() {
        $status = $this->moduleStatus->getSystemStatus();
        
        echo $this->generateHTML($status);
        echo $this->generateCSS();
        echo $this->generateJavaScript();
    }
    
    /**
     * Renderizar apenas o HTML do widget
     */
    public function renderHTML() {
        $status = $this->moduleStatus->getSystemStatus();
        echo $this->generateHTML($status);
    }
    
    /**
     * Gerar HTML do widget
     */
    private function generateHTML($status) {
        $themeClass = 'status-widget-' . $this->options['theme'];
        $compactClass = $this->options['compact_mode'] ? 'compact' : '';
        
        ob_start();
        ?>
        
        <div id="<?php echo $this->widgetId; ?>" class="status-widget <?php echo $themeClass; ?> <?php echo $compactClass; ?>" data-refresh-interval="<?php echo $this->options['refresh_interval']; ?>">
            
            <!-- Header do Widget -->
            <div class="status-widget-header">
                <div class="status-widget-title">
                    <i class="status-icon" data-feather="activity"></i>
                    <span>Status do Sistema</span>
                    <div class="status-indicator status-<?php echo $status['overall_status']; ?>">
                        <span class="status-dot"></span>
                        <span class="status-text"><?php echo ucfirst($status['overall_status']); ?></span>
                    </div>
                </div>
                
                <div class="status-widget-actions">
                    <button class="status-refresh-btn" title="Atualizar Status">
                        <i data-feather="refresh-cw"></i>
                    </button>
                    
                    <?php if (!$this->options['compact_mode']): ?>
                    <button class="status-toggle-details" title="Alternar Detalhes">
                        <i data-feather="chevron-down"></i>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Performance Summary -->
            <?php if ($this->options['show_performance']): ?>
            <div class="status-performance-summary">
                <div class="performance-metric">
                    <span class="metric-label">Resposta</span>
                    <span class="metric-value"><?php echo $status['response_time']; ?>ms</span>
                </div>
                
                <div class="performance-metric">
                    <span class="metric-label">Memória</span>
                    <span class="metric-value"><?php echo $status['memory_usage']['percentage']; ?>%</span>
                    <div class="metric-bar">
                        <div class="metric-fill" style="width: <?php echo $status['memory_usage']['percentage']; ?>%"></div>
                    </div>
                </div>
                
                <div class="performance-metric">
                    <span class="metric-label">Disco</span>
                    <span class="metric-value"><?php echo $status['disk_space']['percentage']; ?>%</span>
                    <div class="metric-bar">
                        <div class="metric-fill" style="width: <?php echo $status['disk_space']['percentage']; ?>%"></div>
                    </div>
                </div>
                
                <div class="performance-metric">
                    <span class="metric-label">Uptime</span>
                    <span class="metric-value"><?php echo $status['uptime']['formatted']; ?></span>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Details Section -->
            <?php if ($this->options['show_details']): ?>
            <div class="status-widget-details">
                
                <!-- Modules Status -->
                <?php if ($this->options['show_modules']): ?>
                <div class="status-section modules-section">
                    <div class="section-header">
                        <h4>Módulos</h4>
                        <span class="section-count"><?php echo count($status['modules']); ?></span>
                    </div>
                    
                    <div class="status-grid">
                        <?php foreach ($status['modules'] as $moduleKey => $module): ?>
                        <div class="status-item module-item status-<?php echo $module['status']; ?>" data-module="<?php echo $moduleKey; ?>">
                            <div class="item-icon">
                                <i data-feather="<?php echo $this->getModuleIcon($moduleKey); ?>"></i>
                            </div>
                            <div class="item-info">
                                <span class="item-name"><?php echo $module['name']; ?></span>
                                <span class="item-status"><?php echo ucfirst($module['status']); ?></span>
                                <?php if ($module['progress'] > 0): ?>
                                <div class="item-progress">
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $module['progress']; ?>%"></div>
                                    </div>
                                    <span class="progress-text"><?php echo $module['progress']; ?>%</span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="item-status-dot status-<?php echo $module['status']; ?>"></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Components Status -->
                <?php if ($this->options['show_components']): ?>
                <div class="status-section components-section">
                    <div class="section-header">
                        <h4>Componentes</h4>
                        <span class="section-count"><?php echo count($status['components']); ?></span>
                    </div>
                    
                    <div class="status-grid">
                        <?php foreach ($status['components'] as $componentKey => $component): ?>
                        <div class="status-item component-item status-<?php echo $component['status']; ?>" data-component="<?php echo $componentKey; ?>">
                            <div class="item-icon">
                                <i data-feather="<?php echo $this->getComponentIcon($componentKey); ?>"></i>
                            </div>
                            <div class="item-info">
                                <span class="item-name"><?php echo $component['name']; ?></span>
                                <span class="item-status"><?php echo ucfirst($component['status']); ?></span>
                                <?php if (isset($component['response_time'])): ?>
                                <span class="item-meta"><?php echo $component['response_time']; ?>ms</span>
                                <?php endif; ?>
                            </div>
                            <div class="item-status-dot status-<?php echo $component['status']; ?>"></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
            </div>
            <?php endif; ?>
            
            <!-- Footer -->
            <div class="status-widget-footer">
                <span class="last-update">Última atualização: <span class="update-time" data-timestamp="<?php echo $status['timestamp']; ?>"><?php echo date('H:i:s', $status['timestamp']); ?></span></span>
                
                <?php if (!$this->options['compact_mode']): ?>
                <div class="footer-actions">
                    <a href="/sistema/shared/api/system-status.php" target="_blank" class="api-link" title="Ver API">
                        <i data-feather="code"></i>
                    </a>
                    <a href="/sistema/data/logs/" target="_blank" class="logs-link" title="Ver Logs">
                        <i data-feather="file-text"></i>
                    </a>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Loading Overlay -->
            <div class="status-loading-overlay" style="display: none;">
                <div class="loading-spinner"></div>
                <span>Atualizando status...</span>
            </div>
            
        </div>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * Gerar CSS do widget
     */
    private function generateCSS() {
        ob_start();
        ?>
        
        <style>
        /* Status Widget Styles */
        .status-widget {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
            overflow: hidden;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            position: relative;
            min-width: 300px;
        }
        
        .status-widget.compact {
            min-width: 250px;
        }
        
        /* Header */
        .status-widget-header {
            padding: 16px 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, #091A30 0%, #1a2b3a 100%);
            color: white;
        }
        
        .status-widget-title {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
        }
        
        .status-widget-title .status-icon {
            width: 20px;
            height: 20px;
            color: #64748b;
        }
        
        .status-indicator {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-left: auto;
            margin-right: 12px;
        }
        
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        .status-online .status-dot { background: #10b981; }
        .status-warning .status-dot { background: #f59e0b; }
        .status-error .status-dot { background: #ef4444; }
        .status-offline .status-dot { background: #6b7280; }
        .status-developing .status-dot { background: #3b82f6; }
        .status-planned .status-dot { background: #8b5cf6; }
        
        .status-text {
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-widget-actions {
            display: flex;
            gap: 8px;
        }
        
        .status-refresh-btn,
        .status-toggle-details {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            border-radius: 6px;
            padding: 6px;
            color: white;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .status-refresh-btn:hover,
        .status-toggle-details:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.05);
        }
        
        .status-refresh-btn.spinning {
            animation: spin 1s linear infinite;
        }
        
        /* Performance Summary */
        .status-performance-summary {
            padding: 16px 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
            gap: 16px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .status-widget.compact .status-performance-summary {
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            padding: 12px 16px;
        }
        
        .performance-metric {
            text-align: center;
        }
        
        .metric-label {
            display: block;
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        
        .metric-value {
            display: block;
            font-size: 14px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 4px;
        }
        
        .metric-bar {
            width: 100%;
            height: 3px;
            background: #e2e8f0;
            border-radius: 2px;
            overflow: hidden;
        }
        
        .metric-fill {
            height: 100%;
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
            transition: width 0.3s ease;
        }
        
        /* Details Section */
        .status-widget-details {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .status-widget.compact .status-widget-details {
            max-height: 300px;
        }
        
        .status-section {
            padding: 16px 20px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .status-section:last-child {
            border-bottom: none;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .section-header h4 {
            margin: 0;
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
        }
        
        .section-count {
            background: #e2e8f0;
            color: #64748b;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .status-grid {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .status-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 12px;
            border-radius: 8px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        
        .status-item:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
            transform: translateY(-1px);
        }
        
        .item-icon {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            background: white;
            border: 1px solid #e2e8f0;
        }
        
        .item-icon i {
            width: 16px;
            height: 16px;
            color: #64748b;
        }
        
        .item-info {
            flex: 1;
            min-width: 0;
        }
        
        .item-name {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 2px;
        }
        
        .item-status {
            display: block;
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 500;
            letter-spacing: 0.5px;
        }
        
        .item-meta {
            display: block;
            font-size: 10px;
            color: #94a3b8;
            margin-top: 2px;
        }
        
        .item-progress {
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .progress-bar {
            flex: 1;
            height: 4px;
            background: #e2e8f0;
            border-radius: 2px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #3b82f6 0%, #1d4ed8 100%);
            transition: width 0.3s ease;
        }
        
        .progress-text {
            font-size: 10px;
            font-weight: 600;
            color: #64748b;
            min-width: 25px;
        }
        
        .item-status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        
        /* Footer */
        .status-widget-footer {
            padding: 12px 20px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .last-update {
            font-size: 11px;
            color: #64748b;
        }
        
        .footer-actions {
            display: flex;
            gap: 8px;
        }
        
        .api-link,
        .logs-link {
            padding: 4px;
            border-radius: 4px;
            color: #64748b;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .api-link:hover,
        .logs-link:hover {
            color: #FF002D;
            background: rgba(255, 0, 45, 0.1);
        }
        
        .api-link i,
        .logs-link i {
            width: 12px;
            height: 12px;
        }
        
        /* Loading Overlay */
        .status-loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 12px;
            z-index: 10;
        }
        
        .loading-spinner {
            width: 24px;
            height: 24px;
            border: 3px solid #e2e8f0;
            border-radius: 50%;
            border-top-color: #FF002D;
            animation: spin 1s ease-in-out infinite;
        }
        
        .status-loading-overlay span {
            font-size: 12px;
            color: #64748b;
            font-weight: 500;
        }
        
        /* Animations */
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Responsive */
        @media (max-width: 480px) {
            .status-widget {
                border-radius: 0;
                box-shadow: none;
                border-left: none;
                border-right: none;
            }
            
            .status-performance-summary {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .status-widget-header,
            .status-section,
            .status-widget-footer {
                padding-left: 16px;
                padding-right: 16px;
            }
        }
        
        /* Theme Variations */
        .status-widget-minimal {
            box-shadow: none;
            border: 1px solid #e2e8f0;
        }
        
        .status-widget-minimal .status-widget-header {
            background: white;
            color: #1e293b;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .status-widget-minimal .status-widget-title .status-icon {
            color: #FF002D;
        }
        
        .status-widget-compact .status-performance-summary {
            display: none;
        }
        
        .status-widget-compact .status-widget-details {
            max-height: 200px;
        }
        </style>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * Gerar JavaScript do widget
     */
    private function generateJavaScript() {
        ob_start();
        ?>
        
        <script>
        (function() {
            const widget = document.getElementById('<?php echo $this->widgetId; ?>');
            if (!widget) return;
            
            const refreshInterval = parseInt(widget.dataset.refreshInterval) || 30000;
            let refreshTimer;
            let isRefreshing = false;
            
            // Initialize Feather icons
            if (typeof feather !== 'undefined') {
                feather.replace();
            }
            
            // Refresh button handler
            const refreshBtn = widget.querySelector('.status-refresh-btn');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', function() {
                    refreshStatus(true);
                });
            }
            
            // Toggle details handler
            const toggleBtn = widget.querySelector('.status-toggle-details');
            const detailsSection = widget.querySelector('.status-widget-details');
            
            if (toggleBtn && detailsSection) {
                toggleBtn.addEventListener('click', function() {
                    const isVisible = detailsSection.style.display !== 'none';
                    detailsSection.style.display = isVisible ? 'none' : 'block';
                    
                    const icon = toggleBtn.querySelector('i');
                    if (icon) {
                        icon.setAttribute('data-feather', isVisible ? 'chevron-down' : 'chevron-up');
                        if (typeof feather !== 'undefined') {
                            feather.replace();
                        }
                    }
                });
            }
            
            // Status item click handlers
            widget.querySelectorAll('.status-item').forEach(item => {
                item.addEventListener('click', function() {
                    const module = this.dataset.module;
                    const component = this.dataset.component;
                    
                    if (module) {
                        showModuleDetails(module);
                    } else if (component) {
                        showComponentDetails(component);
                    }
                });
            });
            
            // Refresh status function
            function refreshStatus(manual = false) {
                if (isRefreshing) return;
                
                isRefreshing = true;
                
                // Show loading
                const loadingOverlay = widget.querySelector('.status-loading-overlay');
                if (loadingOverlay && manual) {
                    loadingOverlay.style.display = 'flex';
                }
                
                // Add spinning class to refresh button
                if (refreshBtn) {
                    refreshBtn.classList.add('spinning');
                }
                
                // Make API call
                fetch('/sistema/shared/api/system-status.php?no_cache=' + (manual ? '1' : '0'))
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            updateWidget(data.data);
                            updateTimestamp(data.data.timestamp);
                            
                            // Show notification for manual refresh
                            if (manual) {
                                showNotification('Status atualizado com sucesso', 'success');
                            }
                        } else {
                            console.error('Erro ao atualizar status:', data.error);
                            showNotification('Erro ao atualizar status', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Erro na requisição:', error);
                        showNotification('Erro de conexão', 'error');
                    })
                    .finally(() => {
                        isRefreshing = false;
                        
                        // Hide loading
                        if (loadingOverlay) {
                            loadingOverlay.style.display = 'none';
                        }
                        
                        // Remove spinning class
                        if (refreshBtn) {
                            refreshBtn.classList.remove('spinning');
                        }
                    });
            }
            
            // Update widget content
            function updateWidget(data) {
                // Update overall status
                const statusIndicator = widget.querySelector('.status-indicator');
                if (statusIndicator) {
                    statusIndicator.className = 'status-indicator status-' + data.overall_status;
                    
                    const statusText = statusIndicator.querySelector('.status-text');
                    if (statusText) {
                        statusText.textContent = capitalizeFirst(data.overall_status);
                    }
                }
                
                // Update performance metrics
                updatePerformanceMetrics(data.performance);
                
                // Update modules
                if (data.modules) {
                    updateModules(data.modules);
                }
                
                // Update components
                if (data.components) {
                    updateComponents(data.components);
                }
            }
            
            // Update performance metrics
            function updatePerformanceMetrics(performance) {
                const metrics = {
                    'response_time': performance.response_time || 0,
                    'memory_usage': performance.memory_usage?.percentage || 0,
                    'disk_space': performance.disk_space?.percentage || 0
                };
                
                Object.keys(metrics).forEach(key => {
                    const metricElement = widget.querySelector(`[data-metric="${key}"]`);
                    if (metricElement) {
                        const value = metrics[key];
                        const valueElement = metricElement.querySelector('.metric-value');
                        const fillElement = metricElement.querySelector('.metric-fill');
                        
                        if (valueElement) {
                            if (key === 'response_time') {
                                valueElement.textContent = value + 'ms';
                            } else {
                                valueElement.textContent = value + '%';
                            }
                        }
                        
                        if (fillElement && key !== 'response_time') {
                            fillElement.style.width = value + '%';
                        }
                    }
                });
            }
            
            // Update modules status
            function updateModules(modules) {
                Object.keys(modules).forEach(moduleKey => {
                    const module = modules[moduleKey];
                    const moduleElement = widget.querySelector(`[data-module="${moduleKey}"]`);
                    
                    if (moduleElement) {
                        moduleElement.className = `status-item module-item status-${module.status}`;
                        
                        const statusElement = moduleElement.querySelector('.item-status');
                        if (statusElement) {
                            statusElement.textContent = capitalizeFirst(module.status);
                        }
                        
                        const statusDot = moduleElement.querySelector('.item-status-dot');
                        if (statusDot) {
                            statusDot.className = `item-status-dot status-${module.status}`;
                        }
                        
                        const progressFill = moduleElement.querySelector('.progress-fill');
                        const progressText = moduleElement.querySelector('.progress-text');
                        
                        if (progressFill && module.progress) {
                            progressFill.style.width = module.progress + '%';
                        }
                        
                        if (progressText && module.progress) {
                            progressText.textContent = module.progress + '%';
                        }
                    }
                });
            }
            
            // Update components status
            function updateComponents(components) {
                Object.keys(components).forEach(componentKey => {
                    const component = components[componentKey];
                    const componentElement = widget.querySelector(`[data-component="${componentKey}"]`);
                    
                    if (componentElement) {
                        componentElement.className = `status-item component-item status-${component.status}`;
                        
                        const statusElement = componentElement.querySelector('.item-status');
                        if (statusElement) {
                            statusElement.textContent = capitalizeFirst(component.status);
                        }
                        
                        const statusDot = componentElement.querySelector('.item-status-dot');
                        if (statusDot) {
                            statusDot.className = `item-status-dot status-${component.status}`;
                        }
                        
                        const metaElement = componentElement.querySelector('.item-meta');
                        if (metaElement && component.response_time) {
                            metaElement.textContent = component.response_time + 'ms';
                        }
                    }
                });
            }
            
            // Update timestamp
            function updateTimestamp(timestamp) {
                const timeElement = widget.querySelector('.update-time');
                if (timeElement) {
                    const date = new Date(timestamp * 1000);
                    timeElement.textContent = date.toLocaleTimeString();
                    timeElement.dataset.timestamp = timestamp;
                }
            }
            
            // Show module details
            function showModuleDetails(moduleKey) {
                console.log('Detalhes do módulo:', moduleKey);
                // Implementar modal ou tooltip com detalhes
            }
            
            // Show component details
            function showComponentDetails(componentKey) {
                console.log('Detalhes do componente:', componentKey);
                // Implementar modal ou tooltip com detalhes
            }
            
            // Show notification
            function showNotification(message, type = 'info') {
                // Criar sistema de notificações simples
                const notification = document.createElement('div');
                notification.className = `status-notification status-notification-${type}`;
                notification.textContent = message;
                
                notification.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
                    color: white;
                    padding: 12px 16px;
                    border-radius: 8px;
                    font-size: 14px;
                    z-index: 1000;
                    animation: slideIn 0.3s ease;
                `;
                
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    notification.remove();
                }, 3000);
            }
            
            // Helper function
            function capitalizeFirst(str) {
                return str.charAt(0).toUpperCase() + str.slice(1);
            }
            
            // Start auto-refresh
            function startAutoRefresh() {
                refreshTimer = setInterval(() => {
                    refreshStatus(false);
                }, refreshInterval);
            }
            
            // Stop auto-refresh
            function stopAutoRefresh() {
                if (refreshTimer) {
                    clearInterval(refreshTimer);
                    refreshTimer = null;
                }
            }
            
            // Visibility change handler
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    stopAutoRefresh();
                } else {
                    startAutoRefresh();
                    refreshStatus(false); // Refresh immediately when page becomes visible
                }
            });
            
            // Initialize auto-refresh
            startAutoRefresh();
            
            // Add CSS for notification animation
            if (!document.querySelector('#status-widget-notification-styles')) {
                const style = document.createElement('style');
                style.id = 'status-widget-notification-styles';
                style.textContent = `
                    @keyframes slideIn {
                        from {
                            transform: translateX(100%);
                            opacity: 0;
                        }
                        to {
                            transform: translateX(0);
                            opacity: 1;
                        }
                    }
                `;
                document.head.appendChild(style);
            }
            
        })();
        </script>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * Obter ícone para módulo
     */
    private function getModuleIcon($moduleKey) {
        $icons = [
            'dashboard' => 'dashboard',
            'fiscal' => 'calculator',
            'commercial' => 'trending-up',
            'accounting' => 'file-text',
            'billing' => 'receipt'
        ];
        
        return isset($icons[$moduleKey]) ? $icons[$moduleKey] : 'box';
    }
    
    /**
     * Obter ícone para componente
     */
    private function getComponentIcon($componentKey) {
        $icons = [
            'database' => 'database',
            'uploads' => 'upload',
            'cache' => 'layers',
            'calculators' => 'cpu',
            'parsers' => 'file-plus'
        ];
        
        return isset($icons[$componentKey]) ? $icons[$componentKey] : 'settings';
    }
}

// Função helper para uso rápido
function renderStatusWidget($widgetId = 'status-widget', $options = []) {
    $widget = new StatusWidget($widgetId, $options);
    $widget->render();
}