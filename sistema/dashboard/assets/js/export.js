/**
 * ================================================================================
 * SISTEMA DE EXPORTAÇÃO FRONTEND - ENTERPRISE GRADE
 * Dashboard ETL DI's - Controle de Exportações com Progress Tracking
 * Features: Progress real-time, WebSocket, Batch operations, Templates
 * ================================================================================
 */

class ExportManager {
    constructor() {
        this.currentExports = new Map();
        this.websocket = null;
        this.progressUpdateInterval = null;
        this.templates = new Map();
        this.exportHistory = [];
        
        // Configurações
        this.config = {
            maxConcurrentExports: 3,
            progressUpdateInterval: 2000, // 2 segundos
            maxFileSize: 50 * 1024 * 1024, // 50MB
            timeoutDuration: 300000, // 5 minutos
            retryAttempts: 3
        };
        
        this.init();
    }
    
    /**
     * Inicializar sistema de exportação
     */
    init() {
        this.setupEventListeners();
        this.loadTemplates();
        this.setupWebSocket();
        this.createExportUI();
        this.loadExportHistory();
        
        console.log('Sistema de Exportação inicializado com sucesso');
    }
    
    /**
     * Configurar event listeners
     */
    setupEventListeners() {
        // Botões de exportação
        document.addEventListener('click', (e) => {
            if (e.target.matches('[data-export-type]')) {
                e.preventDefault();
                this.handleExportClick(e.target);
            }
            
            if (e.target.matches('[data-cancel-export]')) {
                e.preventDefault();
                this.cancelExport(e.target.dataset.exportId);
            }
            
            if (e.target.matches('[data-download-export]')) {
                e.preventDefault();
                this.downloadExport(e.target.dataset.exportId);
            }
        });
        
        // Modal de configuração de export
        document.addEventListener('change', (e) => {
            if (e.target.matches('#exportFormat')) {
                this.updateFormatOptions(e.target.value);
            }
            
            if (e.target.matches('#exportTemplate')) {
                this.updateTemplatePreview(e.target.value);
            }
        });
        
        // Cleanup ao fechar página
        window.addEventListener('beforeunload', () => {
            this.cleanup();
        });
    }
    
    /**
     * Criar interface de exportação
     */
    createExportUI() {
        // Verificar se UI já existe
        if (document.getElementById('exportManager')) return;
        
        const exportUI = this.createExportModal();
        document.body.appendChild(exportUI);
        
        // Criar painel de progresso
        const progressPanel = this.createProgressPanel();
        document.body.appendChild(progressPanel);
        
        // Criar histórico de exports
        const historyPanel = this.createHistoryPanel();
        document.body.appendChild(historyPanel);
        
        // Estilos CSS
        this.injectStyles();
    }
    
    /**
     * Criar modal de configuração de export
     */
    createExportModal() {
        const modal = document.createElement('div');
        modal.id = 'exportManager';
        modal.className = 'export-modal';
        modal.innerHTML = `
            <div class="export-modal-content">
                <div class="export-modal-header">
                    <h3><i class="fas fa-download"></i> Configurar Exportação</h3>
                    <button class="export-modal-close">&times;</button>
                </div>
                
                <div class="export-modal-body">
                    <form id="exportConfigForm">
                        <!-- Tipo de Export -->
                        <div class="export-form-group">
                            <label for="exportType">Tipo de Relatório:</label>
                            <select id="exportType" required>
                                <option value="">Selecione...</option>
                                <option value="dashboard_complete">Dashboard Completo</option>
                                <option value="dis_detailed">DIs Detalhadas</option>
                                <option value="financial_analysis">Análise Financeira</option>
                                <option value="customs_report">Relatório Aduaneiro</option>
                            </select>
                        </div>
                        
                        <!-- Formato -->
                        <div class="export-form-group">
                            <label for="exportFormat">Formato:</label>
                            <select id="exportFormat" required>
                                <option value="">Selecione...</option>
                                <option value="json">JSON Estruturado</option>
                                <option value="pdf">PDF Executivo</option>
                                <option value="xlsx">Excel Avançado</option>
                            </select>
                        </div>
                        
                        <!-- Template -->
                        <div class="export-form-group" id="templateGroup" style="display: none;">
                            <label for="exportTemplate">Template:</label>
                            <select id="exportTemplate">
                                <option value="default">Padrão</option>
                            </select>
                        </div>
                        
                        <!-- Filtros -->
                        <div class="export-form-group">
                            <label>Filtros de Data:</label>
                            <div class="export-date-range">
                                <input type="date" id="dateStart" placeholder="Data início">
                                <input type="date" id="dateEnd" placeholder="Data fim">
                            </div>
                        </div>
                        
                        <div class="export-form-group">
                            <label for="filterUF">Estados (UF):</label>
                            <select id="filterUF" multiple>
                                <option value="SC">Santa Catarina</option>
                                <option value="SP">São Paulo</option>
                                <option value="RJ">Rio de Janeiro</option>
                                <option value="RS">Rio Grande do Sul</option>
                                <option value="PR">Paraná</option>
                                <option value="MG">Minas Gerais</option>
                                <!-- Adicionar outras UFs -->
                            </select>
                        </div>
                        
                        <!-- Opções Avançadas -->
                        <div class="export-form-group">
                            <label>Opções Avançadas:</label>
                            <div class="export-checkboxes">
                                <label>
                                    <input type="checkbox" id="includeCharts"> Incluir Gráficos
                                </label>
                                <label>
                                    <input type="checkbox" id="includeMetadata" checked> Incluir Metadados
                                </label>
                                <label>
                                    <input type="checkbox" id="compressFile"> Comprimir Arquivo
                                </label>
                                <label>
                                    <input type="checkbox" id="digitalSignature"> Assinatura Digital
                                </label>
                            </div>
                        </div>
                        
                        <!-- Preview do Template -->
                        <div id="templatePreview" class="export-template-preview" style="display: none;">
                            <h4>Preview do Template</h4>
                            <div id="templatePreviewContent"></div>
                        </div>
                        
                        <!-- Estimativas -->
                        <div class="export-estimates">
                            <div class="estimate-item">
                                <span class="estimate-label">Registros estimados:</span>
                                <span id="estimatedRecords">-</span>
                            </div>
                            <div class="estimate-item">
                                <span class="estimate-label">Tamanho estimado:</span>
                                <span id="estimatedSize">-</span>
                            </div>
                            <div class="estimate-item">
                                <span class="estimate-label">Tempo estimado:</span>
                                <span id="estimatedTime">-</span>
                            </div>
                        </div>
                    </form>
                </div>
                
                <div class="export-modal-footer">
                    <button type="button" class="btn btn-secondary" id="cancelExportConfig">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="startExport">
                        <i class="fas fa-play"></i> Iniciar Exportação
                    </button>
                </div>
            </div>
        `;
        
        // Event listeners do modal
        this.setupModalEvents(modal);
        
        return modal;
    }
    
    /**
     * Criar painel de progresso
     */
    createProgressPanel() {
        const panel = document.createElement('div');
        panel.id = 'exportProgressPanel';
        panel.className = 'export-progress-panel';
        panel.innerHTML = `
            <div class="progress-panel-header">
                <h4><i class="fas fa-tasks"></i> Exportações em Andamento</h4>
                <button class="progress-panel-toggle">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </div>
            <div class="progress-panel-body" id="exportProgressList">
                <div class="no-exports">
                    <i class="fas fa-info-circle"></i>
                    Nenhuma exportação em andamento
                </div>
            </div>
        `;
        
        return panel;
    }
    
    /**
     * Criar painel de histórico
     */
    createHistoryPanel() {
        const panel = document.createElement('div');
        panel.id = 'exportHistoryPanel';
        panel.className = 'export-history-panel';
        panel.innerHTML = `
            <div class="history-panel-header">
                <h4><i class="fas fa-history"></i> Histórico de Exportações</h4>
                <div class="history-actions">
                    <button class="btn btn-sm btn-secondary" id="clearHistory">Limpar</button>
                    <button class="history-panel-toggle">
                        <i class="fas fa-chevron-up"></i>
                    </button>
                </div>
            </div>
            <div class="history-panel-body" id="exportHistoryList">
                <div class="no-history">
                    <i class="fas fa-info-circle"></i>
                    Nenhuma exportação realizada
                </div>
            </div>
        `;
        
        return panel;
    }
    
    /**
     * Configurar WebSocket para atualizações em tempo real
     */
    setupWebSocket() {
        try {
            // URL do WebSocket (ajustar conforme necessário)
            const wsUrl = `wss://${window.location.host}/ws/export`;
            this.websocket = new WebSocket(wsUrl);
            
            this.websocket.onopen = () => {
                console.log('WebSocket conectado para atualizações de exportação');
            };
            
            this.websocket.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);
                    this.handleWebSocketMessage(data);
                } catch (e) {
                    console.error('Erro ao processar mensagem WebSocket:', e);
                }
            };
            
            this.websocket.onerror = (error) => {
                console.error('Erro WebSocket:', error);
                this.fallbackToPolling();
            };
            
            this.websocket.onclose = () => {
                console.log('WebSocket desconectado');
                this.fallbackToPolling();
            };
            
        } catch (error) {
            console.warn('WebSocket não disponível, usando polling:', error);
            this.fallbackToPolling();
        }
    }
    
    /**
     * Fallback para polling quando WebSocket não disponível
     */
    fallbackToPolling() {
        if (this.progressUpdateInterval) {
            clearInterval(this.progressUpdateInterval);
        }
        
        this.progressUpdateInterval = setInterval(() => {
            this.updateExportProgress();
        }, this.config.progressUpdateInterval);
    }
    
    /**
     * Processar mensagens WebSocket
     */
    handleWebSocketMessage(data) {
        if (data.type === 'export_progress') {
            this.updateExportProgressUI(data.export_id, data.progress, data.status, data.message);
        } else if (data.type === 'export_complete') {
            this.handleExportComplete(data.export_id, data.download_url, data.file_size);
        } else if (data.type === 'export_error') {
            this.handleExportError(data.export_id, data.error);
        }
    }
    
    /**
     * Manipular clique nos botões de export
     */
    handleExportClick(button) {
        const exportType = button.dataset.exportType;
        const format = button.dataset.format || 'xlsx';
        
        // Pre-configurar modal com dados do botão
        this.preConfigureModal(exportType, format);
        
        // Mostrar modal
        this.showExportModal();
    }
    
    /**
     * Pre-configurar modal com dados
     */
    preConfigureModal(type, format) {
        const typeSelect = document.getElementById('exportType');
        const formatSelect = document.getElementById('exportFormat');
        
        if (typeSelect) typeSelect.value = type;
        if (formatSelect) formatSelect.value = format;
        
        this.updateFormatOptions(format);
        this.updateEstimates();
    }
    
    /**
     * Mostrar modal de exportação
     */
    showExportModal() {
        const modal = document.getElementById('exportManager');
        if (modal) {
            modal.style.display = 'flex';
            modal.classList.add('show');
            
            // Focus no primeiro campo
            const firstInput = modal.querySelector('select, input');
            if (firstInput) firstInput.focus();
        }
    }
    
    /**
     * Ocultar modal de exportação
     */
    hideExportModal() {
        const modal = document.getElementById('exportManager');
        if (modal) {
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        }
    }
    
    /**
     * Atualizar opções baseadas no formato
     */
    updateFormatOptions(format) {
        const templateGroup = document.getElementById('templateGroup');
        const templateSelect = document.getElementById('exportTemplate');
        const chartsCheckbox = document.getElementById('includeCharts');
        
        if (!templateGroup || !templateSelect) return;
        
        // Mostrar/ocultar grupo de template
        templateGroup.style.display = format ? 'block' : 'none';
        
        // Limpar templates existentes
        templateSelect.innerHTML = '<option value="default">Padrão</option>';
        
        // Adicionar templates específicos do formato
        if (this.templates.has(format)) {
            const formatTemplates = this.templates.get(format);
            formatTemplates.forEach(template => {
                const option = document.createElement('option');
                option.value = template.id;
                option.textContent = template.name;
                templateSelect.appendChild(option);
            });
        }
        
        // Configurações específicas por formato
        switch (format) {
            case 'pdf':
                if (chartsCheckbox) chartsCheckbox.checked = true;
                break;
            case 'xlsx':
                if (chartsCheckbox) chartsCheckbox.checked = false; // Charts nativos Excel
                break;
            case 'json':
                if (chartsCheckbox) chartsCheckbox.checked = false;
                break;
        }
        
        this.updateEstimates();
    }
    
    /**
     * Atualizar preview do template
     */
    updateTemplatePreview(templateId) {
        const previewDiv = document.getElementById('templatePreview');
        const previewContent = document.getElementById('templatePreviewContent');
        
        if (!previewDiv || !previewContent) return;
        
        if (templateId && templateId !== 'default') {
            const format = document.getElementById('exportFormat').value;
            const template = this.getTemplate(format, templateId);
            
            if (template) {
                previewContent.innerHTML = this.generateTemplatePreview(template);
                previewDiv.style.display = 'block';
            }
        } else {
            previewDiv.style.display = 'none';
        }
    }
    
    /**
     * Gerar preview do template
     */
    generateTemplatePreview(template) {
        return `
            <div class="template-preview-item">
                <strong>Nome:</strong> ${template.name}
            </div>
            <div class="template-preview-item">
                <strong>Descrição:</strong> ${template.description}
            </div>
            <div class="template-preview-item">
                <strong>Seções:</strong> 
                ${Object.keys(template.sections || {}).filter(k => template.sections[k].enabled).join(', ')}
            </div>
        `;
    }
    
    /**
     * Atualizar estimativas
     */
    async updateEstimates() {
        const form = document.getElementById('exportConfigForm');
        if (!form) return;
        
        const formData = new FormData(form);
        const config = this.getExportConfig();
        
        try {
            const response = await fetch('/api/export/estimate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(config)
            });
            
            if (response.ok) {
                const estimates = await response.json();
                this.updateEstimatesUI(estimates);
            }
        } catch (error) {
            console.error('Erro ao obter estimativas:', error);
        }
    }
    
    /**
     * Atualizar UI das estimativas
     */
    updateEstimatesUI(estimates) {
        const recordsEl = document.getElementById('estimatedRecords');
        const sizeEl = document.getElementById('estimatedSize');
        const timeEl = document.getElementById('estimatedTime');
        
        if (recordsEl) recordsEl.textContent = estimates.records?.toLocaleString() || '-';
        if (sizeEl) sizeEl.textContent = estimates.size || '-';
        if (timeEl) timeEl.textContent = estimates.time || '-';
    }
    
    /**
     * Iniciar exportação
     */
    async startExport() {
        const config = this.getExportConfig();
        
        // Validar configuração
        if (!this.validateExportConfig(config)) {
            return;
        }
        
        // Verificar limite de exports simultâneos
        if (this.currentExports.size >= this.config.maxConcurrentExports) {
            this.showNotification('Limite de exportações simultâneas atingido', 'warning');
            return;
        }
        
        try {
            // Gerar ID único para o export
            const exportId = this.generateExportId();
            
            // Adicionar à lista de exports ativos
            this.currentExports.set(exportId, {
                id: exportId,
                config: config,
                startTime: Date.now(),
                status: 'initiating',
                progress: 0
            });
            
            // Atualizar UI
            this.addExportProgressUI(exportId, config);
            this.hideExportModal();
            
            // Fazer requisição para iniciar export
            const response = await fetch('/api/export/manager', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    export_id: exportId,
                    ...config
                })
            });
            
            if (!response.ok) {
                throw new Error(`Erro HTTP: ${response.status}`);
            }
            
            const result = await response.json();
            
            if (result.status === 'completed') {
                // Export síncrono completado
                this.handleExportComplete(exportId, result.download_url, result.file_size);
            } else if (result.status === 'queued') {
                // Export assíncrono em fila
                this.updateExportProgressUI(exportId, 0, 'queued', 'Exportação adicionada à fila');
            }
            
        } catch (error) {
            console.error('Erro ao iniciar exportação:', error);
            this.handleExportError(exportId, error.message);
        }
    }
    
    /**
     * Obter configuração do export do formulário
     */
    getExportConfig() {
        const form = document.getElementById('exportConfigForm');
        if (!form) return {};
        
        return {
            type: document.getElementById('exportType')?.value,
            format: document.getElementById('exportFormat')?.value,
            template: document.getElementById('exportTemplate')?.value || 'default',
            filters: {
                date_start: document.getElementById('dateStart')?.value,
                date_end: document.getElementById('dateEnd')?.value,
                uf: Array.from(document.getElementById('filterUF')?.selectedOptions || [])
                    .map(option => option.value)
            },
            options: {
                include_charts: document.getElementById('includeCharts')?.checked || false,
                include_metadata: document.getElementById('includeMetadata')?.checked || true,
                compress_file: document.getElementById('compressFile')?.checked || false,
                digital_signature: document.getElementById('digitalSignature')?.checked || false
            }
        };
    }
    
    /**
     * Validar configuração do export
     */
    validateExportConfig(config) {
        const errors = [];
        
        if (!config.type) errors.push('Tipo de relatório é obrigatório');
        if (!config.format) errors.push('Formato é obrigatório');
        
        if (config.filters.date_start && config.filters.date_end) {
            const startDate = new Date(config.filters.date_start);
            const endDate = new Date(config.filters.date_end);
            
            if (startDate > endDate) {
                errors.push('Data de início deve ser anterior à data de fim');
            }
            
            const daysDiff = (endDate - startDate) / (1000 * 60 * 60 * 24);
            if (daysDiff > 365) {
                errors.push('Período não pode ser superior a 1 ano');
            }
        }
        
        if (errors.length > 0) {
            this.showNotification('Erros de validação:\n' + errors.join('\n'), 'error');
            return false;
        }
        
        return true;
    }
    
    /**
     * Adicionar progress UI para export
     */
    addExportProgressUI(exportId, config) {
        const progressList = document.getElementById('exportProgressList');
        if (!progressList) return;
        
        // Remover mensagem de "nenhuma exportação"
        const noExports = progressList.querySelector('.no-exports');
        if (noExports) noExports.style.display = 'none';
        
        // Criar item de progresso
        const progressItem = document.createElement('div');
        progressItem.className = 'export-progress-item';
        progressItem.id = `export-${exportId}`;
        progressItem.innerHTML = `
            <div class="export-info">
                <div class="export-title">
                    ${this.getExportTitle(config)} (${config.format.toUpperCase()})
                </div>
                <div class="export-details">
                    Iniciado em ${new Date().toLocaleTimeString()}
                </div>
            </div>
            <div class="export-progress">
                <div class="progress-bar">
                    <div class="progress-fill" style="width: 0%"></div>
                </div>
                <div class="progress-text">0%</div>
            </div>
            <div class="export-status">Iniciando...</div>
            <div class="export-actions">
                <button class="btn btn-sm btn-danger" data-cancel-export="${exportId}">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        progressList.appendChild(progressItem);
        
        // Mostrar painel se estiver oculto
        this.showProgressPanel();
    }
    
    /**
     * Atualizar progress UI do export
     */
    updateExportProgressUI(exportId, progress, status, message) {
        const progressItem = document.getElementById(`export-${exportId}`);
        if (!progressItem) return;
        
        // Atualizar dados locais
        if (this.currentExports.has(exportId)) {
            const exportData = this.currentExports.get(exportId);
            exportData.progress = progress;
            exportData.status = status;
            exportData.lastUpdate = Date.now();
        }
        
        // Atualizar UI
        const progressFill = progressItem.querySelector('.progress-fill');
        const progressText = progressItem.querySelector('.progress-text');
        const statusEl = progressItem.querySelector('.export-status');
        
        if (progressFill) progressFill.style.width = `${progress}%`;
        if (progressText) progressText.textContent = `${progress}%`;
        if (statusEl) statusEl.textContent = message || this.getStatusText(status);
        
        // Aplicar classe CSS baseada no status
        progressItem.className = `export-progress-item status-${status}`;
    }
    
    /**
     * Manipular completion do export
     */
    handleExportComplete(exportId, downloadUrl, fileSize) {
        const exportData = this.currentExports.get(exportId);
        if (!exportData) return;
        
        // Atualizar status para completed
        this.updateExportProgressUI(exportId, 100, 'completed', 'Concluído com sucesso');
        
        // Adicionar botão de download
        const progressItem = document.getElementById(`export-${exportId}`);
        if (progressItem) {
            const actions = progressItem.querySelector('.export-actions');
            actions.innerHTML = `
                <button class="btn btn-sm btn-success" data-download-export="${exportId}" data-url="${downloadUrl}">
                    <i class="fas fa-download"></i> Download
                </button>
                <button class="btn btn-sm btn-secondary" data-cancel-export="${exportId}">
                    <i class="fas fa-times"></i>
                </button>
            `;
        }
        
        // Adicionar ao histórico
        this.addToHistory({
            ...exportData,
            completedAt: Date.now(),
            downloadUrl: downloadUrl,
            fileSize: fileSize,
            status: 'completed'
        });
        
        // Remover da lista ativa após 30 segundos
        setTimeout(() => {
            this.removeFromActiveExports(exportId);
        }, 30000);
        
        // Mostrar notificação
        this.showNotification('Exportação concluída com sucesso!', 'success');
    }
    
    /**
     * Manipular erro no export
     */
    handleExportError(exportId, error) {
        this.updateExportProgressUI(exportId, 0, 'error', `Erro: ${error}`);
        
        // Adicionar ao histórico
        const exportData = this.currentExports.get(exportId);
        if (exportData) {
            this.addToHistory({
                ...exportData,
                completedAt: Date.now(),
                error: error,
                status: 'error'
            });
        }
        
        // Remover da lista ativa
        setTimeout(() => {
            this.removeFromActiveExports(exportId);
        }, 10000);
        
        // Mostrar notificação
        this.showNotification(`Erro na exportação: ${error}`, 'error');
    }
    
    /**
     * Download do arquivo exportado
     */
    downloadExport(exportId) {
        const button = document.querySelector(`[data-download-export="${exportId}"]`);
        const downloadUrl = button?.dataset.url;
        
        if (!downloadUrl) {
            this.showNotification('URL de download não encontrada', 'error');
            return;
        }
        
        // Criar link temporário para download
        const link = document.createElement('a');
        link.href = downloadUrl;
        link.download = '';
        link.style.display = 'none';
        
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        // Registrar download no histórico
        this.updateHistoryDownload(exportId);
    }
    
    /**
     * Cancelar exportação
     */
    async cancelExport(exportId) {
        try {
            const response = await fetch(`/api/export/cancel/${exportId}`, {
                method: 'POST'
            });
            
            if (response.ok) {
                this.updateExportProgressUI(exportId, 0, 'cancelled', 'Cancelado pelo usuário');
                this.removeFromActiveExports(exportId);
                this.showNotification('Exportação cancelada', 'info');
            }
        } catch (error) {
            console.error('Erro ao cancelar exportação:', error);
            this.showNotification('Erro ao cancelar exportação', 'error');
        }
    }
    
    /**
     * Métodos auxiliares
     */
    generateExportId() {
        return 'export_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }
    
    getExportTitle(config) {
        const titles = {
            dashboard_complete: 'Dashboard Completo',
            dis_detailed: 'DIs Detalhadas',
            financial_analysis: 'Análise Financeira',
            customs_report: 'Relatório Aduaneiro'
        };
        return titles[config.type] || 'Exportação';
    }
    
    getStatusText(status) {
        const texts = {
            initiating: 'Iniciando...',
            queued: 'Na fila',
            processing: 'Processando...',
            completed: 'Concluído',
            error: 'Erro',
            cancelled: 'Cancelado'
        };
        return texts[status] || status;
    }
    
    showNotification(message, type = 'info') {
        // Implementar sistema de notificações toast
        console.log(`[${type.toUpperCase()}] ${message}`);
        
        // Criar toast notification
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <div class="toast-content">
                <i class="fas fa-${this.getNotificationIcon(type)}"></i>
                <span>${message}</span>
            </div>
            <button class="toast-close">&times;</button>
        `;
        
        document.body.appendChild(toast);
        
        // Auto-remover após 5 segundos
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 5000);
        
        // Botão de fechar
        toast.querySelector('.toast-close').addEventListener('click', () => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        });
    }
    
    getNotificationIcon(type) {
        const icons = {
            success: 'check-circle',
            error: 'exclamation-circle',
            warning: 'exclamation-triangle',
            info: 'info-circle'
        };
        return icons[type] || 'info-circle';
    }
    
    // Métodos placeholder para implementações específicas
    loadTemplates() {
        // Carregar templates disponíveis
        this.templates.set('pdf', [
            { id: 'executive_summary', name: 'Resumo Executivo' },
            { id: 'detailed_report', name: 'Relatório Detalhado' }
        ]);
        
        this.templates.set('xlsx', [
            { id: 'financial_analysis', name: 'Análise Financeira' },
            { id: 'operational_dashboard', name: 'Dashboard Operacional' }
        ]);
    }
    
    getTemplate(format, templateId) {
        const formatTemplates = this.templates.get(format);
        return formatTemplates?.find(t => t.id === templateId);
    }
    
    setupModalEvents(modal) {
        // Implementar eventos específicos do modal
    }
    
    showProgressPanel() {
        const panel = document.getElementById('exportProgressPanel');
        if (panel && !panel.classList.contains('visible')) {
            panel.classList.add('visible');
        }
    }
    
    removeFromActiveExports(exportId) {
        this.currentExports.delete(exportId);
        
        const progressItem = document.getElementById(`export-${exportId}`);
        if (progressItem) {
            progressItem.remove();
        }
        
        // Se não há mais exports ativos, ocultar painel
        if (this.currentExports.size === 0) {
            const progressList = document.getElementById('exportProgressList');
            const noExports = progressList?.querySelector('.no-exports');
            if (noExports) noExports.style.display = 'block';
        }
    }
    
    addToHistory(exportData) {
        this.exportHistory.unshift(exportData);
        
        // Manter apenas os últimos 50 exports
        if (this.exportHistory.length > 50) {
            this.exportHistory = this.exportHistory.slice(0, 50);
        }
        
        this.updateHistoryUI();
        this.saveHistoryToStorage();
    }
    
    updateHistoryUI() {
        // Implementar atualização da UI do histórico
    }
    
    updateHistoryDownload(exportId) {
        const historyItem = this.exportHistory.find(h => h.id === exportId);
        if (historyItem) {
            historyItem.downloadedAt = Date.now();
            this.updateHistoryUI();
            this.saveHistoryToStorage();
        }
    }
    
    loadExportHistory() {
        const stored = localStorage.getItem('exportHistory');
        if (stored) {
            try {
                this.exportHistory = JSON.parse(stored);
                this.updateHistoryUI();
            } catch (e) {
                console.error('Erro ao carregar histórico:', e);
            }
        }
    }
    
    saveHistoryToStorage() {
        try {
            localStorage.setItem('exportHistory', JSON.stringify(this.exportHistory));
        } catch (e) {
            console.error('Erro ao salvar histórico:', e);
        }
    }
    
    updateExportProgress() {
        // Polling fallback para atualizar progresso
        this.currentExports.forEach(async (exportData, exportId) => {
            try {
                const response = await fetch(`/api/export/progress/${exportId}`);
                if (response.ok) {
                    const progress = await response.json();
                    this.updateExportProgressUI(exportId, progress.progress, progress.status, progress.message);
                    
                    if (progress.status === 'completed') {
                        this.handleExportComplete(exportId, progress.download_url, progress.file_size);
                    } else if (progress.status === 'error') {
                        this.handleExportError(exportId, progress.error);
                    }
                }
            } catch (error) {
                console.error(`Erro ao atualizar progresso do export ${exportId}:`, error);
            }
        });
    }
    
    cleanup() {
        if (this.websocket) {
            this.websocket.close();
        }
        
        if (this.progressUpdateInterval) {
            clearInterval(this.progressUpdateInterval);
        }
    }
    
    injectStyles() {
        const styles = `
            <style>
            .export-modal {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 10000;
                justify-content: center;
                align-items: center;
            }
            
            .export-modal.show {
                opacity: 1;
            }
            
            .export-modal-content {
                background: white;
                border-radius: 8px;
                width: 90%;
                max-width: 600px;
                max-height: 90vh;
                overflow-y: auto;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            }
            
            .export-modal-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 20px;
                border-bottom: 1px solid #eee;
                background: #FF002D;
                color: white;
                border-radius: 8px 8px 0 0;
            }
            
            .export-modal-header h3 {
                margin: 0;
                font-size: 18px;
            }
            
            .export-modal-close {
                background: none;
                border: none;
                color: white;
                font-size: 24px;
                cursor: pointer;
                padding: 0;
                width: 30px;
                height: 30px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .export-modal-body {
                padding: 20px;
            }
            
            .export-form-group {
                margin-bottom: 20px;
            }
            
            .export-form-group label {
                display: block;
                margin-bottom: 5px;
                font-weight: 500;
                color: #333;
            }
            
            .export-form-group select,
            .export-form-group input {
                width: 100%;
                padding: 8px 12px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 14px;
            }
            
            .export-date-range {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }
            
            .export-checkboxes {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }
            
            .export-checkboxes label {
                display: flex;
                align-items: center;
                gap: 8px;
                font-weight: normal;
                margin-bottom: 0;
            }
            
            .export-estimates {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 4px;
                border: 1px solid #e9ecef;
            }
            
            .estimate-item {
                display: flex;
                justify-content: space-between;
                margin-bottom: 5px;
            }
            
            .estimate-label {
                font-weight: 500;
            }
            
            .export-modal-footer {
                padding: 20px;
                border-top: 1px solid #eee;
                display: flex;
                justify-content: flex-end;
                gap: 10px;
            }
            
            .export-progress-panel {
                position: fixed;
                bottom: 20px;
                right: 20px;
                width: 400px;
                background: white;
                border-radius: 8px;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                border: 1px solid #ddd;
                z-index: 9999;
                transform: translateY(100%);
                transition: transform 0.3s ease;
            }
            
            .export-progress-panel.visible {
                transform: translateY(0);
            }
            
            .progress-panel-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 15px;
                background: #FF002D;
                color: white;
                border-radius: 8px 8px 0 0;
            }
            
            .progress-panel-header h4 {
                margin: 0;
                font-size: 14px;
            }
            
            .export-progress-item {
                padding: 15px;
                border-bottom: 1px solid #eee;
                display: grid;
                grid-template-columns: 1fr auto auto;
                gap: 10px;
                align-items: center;
            }
            
            .export-info {
                min-width: 0;
            }
            
            .export-title {
                font-weight: 500;
                margin-bottom: 5px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            
            .export-details {
                font-size: 12px;
                color: #666;
            }
            
            .export-progress {
                width: 100px;
            }
            
            .progress-bar {
                height: 6px;
                background: #e9ecef;
                border-radius: 3px;
                overflow: hidden;
                margin-bottom: 5px;
            }
            
            .progress-fill {
                height: 100%;
                background: #FF002D;
                transition: width 0.3s ease;
            }
            
            .progress-text {
                font-size: 12px;
                text-align: center;
                color: #666;
            }
            
            .export-status {
                font-size: 12px;
                color: #666;
                text-align: center;
                min-width: 80px;
            }
            
            .export-actions {
                display: flex;
                gap: 5px;
            }
            
            .toast {
                position: fixed;
                top: 20px;
                right: 20px;
                background: white;
                border-radius: 4px;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                border-left: 4px solid;
                padding: 15px;
                max-width: 300px;
                z-index: 10001;
                animation: slideIn 0.3s ease;
            }
            
            .toast-success { border-left-color: #28a745; }
            .toast-error { border-left-color: #dc3545; }
            .toast-warning { border-left-color: #ffc107; }
            .toast-info { border-left-color: #17a2b8; }
            
            .toast-content {
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .toast-close {
                position: absolute;
                top: 5px;
                right: 10px;
                background: none;
                border: none;
                font-size: 18px;
                cursor: pointer;
                color: #999;
            }
            
            @keyframes slideIn {
                from { transform: translateX(100%); }
                to { transform: translateX(0); }
            }
            
            .btn {
                padding: 8px 16px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 5px;
                transition: background-color 0.2s;
            }
            
            .btn-primary { background: #FF002D; color: white; }
            .btn-secondary { background: #6c757d; color: white; }
            .btn-success { background: #28a745; color: white; }
            .btn-danger { background: #dc3545; color: white; }
            .btn-sm { padding: 4px 8px; font-size: 12px; }
            
            .btn:hover { opacity: 0.9; }
            </style>
        `;
        
        document.head.insertAdjacentHTML('beforeend', styles);
    }
}

// Inicializar quando DOM estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    window.exportManager = new ExportManager();
});

// API pública para integração
window.ExportAPI = {
    startExport: (type, format, options = {}) => {
        if (window.exportManager) {
            window.exportManager.preConfigureModal(type, format);
            window.exportManager.showExportModal();
        }
    },
    
    getActiveExports: () => {
        return window.exportManager ? Array.from(window.exportManager.currentExports.values()) : [];
    },
    
    getExportHistory: () => {
        return window.exportManager ? window.exportManager.exportHistory : [];
    }
};