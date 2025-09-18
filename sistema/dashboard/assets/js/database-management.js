/**
 * ================================================================================
 * GERENCIAMENTO DE BANCO DE DADOS - FRONTEND
 * Sistema ETL DI's - Interface para limpeza e exportação do banco
 * Padrão Expertzy: Energia • Segurança • Transparência
 * ================================================================================
 */

class DatabaseManagement {
    constructor() {
        this.apiBase = '/dashboard/api/dashboard';
        this.isProcessing = false;
        this.currentOperation = null;
        
        this.init();
    }
    
    /**
     * Inicialização do sistema
     */
    init() {
        this.bindEvents();
        this.setupModals();
        console.log('✅ Sistema de Gerenciamento de Banco inicializado');
    }
    
    /**
     * Bind eventos dos botões
     */
    bindEvents() {
        // Botões principais
        document.addEventListener('click', (e) => {
            if (e.target.matches('[data-action="database-cleanup"]')) {
                e.preventDefault();
                this.showCleanupModal();
            }
            
            if (e.target.matches('[data-action="database-export"]')) {
                e.preventDefault();
                this.showExportModal();
            }
            
            // Botões do modal de limpeza
            if (e.target.matches('[data-cleanup-type]')) {
                e.preventDefault();
                const cleanupType = e.target.dataset.cleanupType;
                this.selectCleanupType(cleanupType);
            }
            
            if (e.target.matches('#execute-cleanup')) {
                e.preventDefault();
                this.executeCleanup();
            }
            
            // Botões do modal de export
            if (e.target.matches('[data-export-type]')) {
                e.preventDefault();
                const exportType = e.target.dataset.exportType;
                this.selectExportType(exportType);
            }
            
            if (e.target.matches('#execute-export')) {
                e.preventDefault();
                this.executeExport();
            }
            
            // Fechar modais
            if (e.target.matches('.modal-close, .modal-overlay')) {
                e.preventDefault();
                this.closeAllModals();
            }
        });
        
        // Escape para fechar modais
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeAllModals();
            }
        });
    }
    
    /**
     * Configurar modais
     */
    setupModals() {
        // Criar modais se não existirem
        if (!document.getElementById('cleanup-modal')) {
            this.createCleanupModal();
        }
        
        if (!document.getElementById('export-modal')) {
            this.createExportModal();
        }
    }
    
    /**
     * Mostrar modal de limpeza
     */
    showCleanupModal() {
        const modal = document.getElementById('cleanup-modal');
        if (modal) {
            modal.classList.add('active');
            this.resetCleanupForm();
        }
    }
    
    /**
     * Mostrar modal de exportação
     */
    showExportModal() {
        const modal = document.getElementById('export-modal');
        if (modal) {
            modal.classList.add('active');
            this.resetExportForm();
        }
    }
    
    /**
     * Fechar todos os modais
     */
    closeAllModals() {
        const modals = document.querySelectorAll('.modal.active');
        modals.forEach(modal => {
            modal.classList.remove('active');
        });
        
        this.resetForms();
    }
    
    /**
     * Selecionar tipo de limpeza
     */
    selectCleanupType(type) {
        // Reset previous selections
        document.querySelectorAll('[data-cleanup-type]').forEach(btn => {
            btn.classList.remove('selected');
        });
        
        // Mark current selection
        document.querySelector(`[data-cleanup-type="${type}"]`).classList.add('selected');
        
        // Show/hide options based on type
        this.showCleanupOptions(type);
    }
    
    /**
     * Mostrar opções de limpeza baseado no tipo
     */
    showCleanupOptions(type) {
        // Esconder todas as opções
        document.querySelectorAll('.cleanup-options').forEach(option => {
            option.style.display = 'none';
        });
        
        // Mostrar opção específica
        const optionElement = document.getElementById(`cleanup-options-${type}`);
        if (optionElement) {
            optionElement.style.display = 'block';
        }
        
        // Habilitar botão de execução
        const executeBtn = document.getElementById('execute-cleanup');
        if (executeBtn) {
            executeBtn.disabled = false;
            executeBtn.dataset.cleanupType = type;
        }
    }
    
    /**
     * Selecionar tipo de exportação
     */
    selectExportType(type) {
        // Reset previous selections
        document.querySelectorAll('[data-export-type]').forEach(btn => {
            btn.classList.remove('selected');
        });
        
        // Mark current selection
        document.querySelector(`[data-export-type="${type}"]`).classList.add('selected');
        
        // Show/hide options based on type
        this.showExportOptions(type);
    }
    
    /**
     * Mostrar opções de exportação baseado no tipo
     */
    showExportOptions(type) {
        // Esconder todas as opções
        document.querySelectorAll('.export-options').forEach(option => {
            option.style.display = 'none';
        });
        
        // Mostrar opção específica
        const optionElement = document.getElementById(`export-options-${type}`);
        if (optionElement) {
            optionElement.style.display = 'block';
        }
        
        // Habilitar botão de execução
        const executeBtn = document.getElementById('execute-export');
        if (executeBtn) {
            executeBtn.disabled = false;
            executeBtn.dataset.exportType = type;
        }
    }
    
    /**
     * Executar limpeza
     */
    async executeCleanup() {
        if (this.isProcessing) return;
        
        const executeBtn = document.getElementById('execute-cleanup');
        const cleanupType = executeBtn.dataset.cleanupType;
        
        if (!cleanupType) {
            this.showError('Selecione um tipo de limpeza');
            return;
        }
        
        // Preparar dados da requisição
        const requestData = this.prepareCleanupData(cleanupType);
        
        if (!requestData) {
            return; // Erro já foi mostrado em prepareCleanupData
        }
        
        try {
            this.setProcessing(true, 'Executando limpeza...');
            
            const response = await fetch(`${this.apiBase}/database-cleanup.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(requestData)
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showCleanupSuccess(result);
            } else {
                this.showError(result.message || 'Erro na limpeza');
            }
            
        } catch (error) {
            console.error('Erro na limpeza:', error);
            this.showError('Erro de comunicação com o servidor');
        } finally {
            this.setProcessing(false);
        }
    }
    
    /**
     * Executar exportação
     */
    async executeExport() {
        if (this.isProcessing) return;
        
        const executeBtn = document.getElementById('execute-export');
        const exportType = executeBtn.dataset.exportType;
        
        if (!exportType) {
            this.showError('Selecione um tipo de exportação');
            return;
        }
        
        // Preparar dados da requisição
        const requestData = this.prepareExportData(exportType);
        
        if (!requestData) {
            return; // Erro já foi mostrado em prepareExportData
        }
        
        try {
            this.setProcessing(true, 'Executando exportação...');
            
            const response = await fetch(`${this.apiBase}/database-export.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(requestData)
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showExportSuccess(result);
            } else {
                this.showError(result.message || 'Erro na exportação');
            }
            
        } catch (error) {
            console.error('Erro na exportação:', error);
            this.showError('Erro de comunicação com o servidor');
        } finally {
            this.setProcessing(false);
        }
    }
    
    /**
     * Preparar dados para limpeza
     */
    prepareCleanupData(cleanupType) {
        const data = {
            operation: `cleanup_${cleanupType}`
        };
        
        switch (cleanupType) {
            case 'test':
                const testConfirmation = document.getElementById('test-confirmation').value;
                if (testConfirmation !== 'CONFIRMAR') {
                    this.showError('Digite "CONFIRMAR" para prosseguir com a limpeza de dados de teste');
                    return null;
                }
                data.confirmation = 'CONFIRM_CLEANUP_TEST';
                break;
                
            case 'period':
                const days = document.getElementById('period-days').value;
                const periodConfirmation = document.getElementById('period-confirmation').value;
                
                if (!days || days < 7) {
                    this.showError('Especifique um período mínimo de 7 dias');
                    return null;
                }
                
                if (periodConfirmation !== 'CONFIRMAR') {
                    this.showError('Digite "CONFIRMAR" para prosseguir com a limpeza por período');
                    return null;
                }
                
                data.days = parseInt(days);
                data.confirmation = 'CONFIRM_CLEANUP_PERIOD';
                break;
                
            case 'di':
                const numeroDi = document.getElementById('di-number').value;
                const diConfirmation = document.getElementById('di-confirmation').value;
                
                if (!numeroDi || !numeroDi.match(/^[0-9]{10}$/)) {
                    this.showError('Digite um número de DI válido (10 dígitos)');
                    return null;
                }
                
                if (diConfirmation !== 'CONFIRMAR') {
                    this.showError('Digite "CONFIRMAR" para prosseguir com a limpeza da DI');
                    return null;
                }
                
                data.numero_di = numeroDi;
                data.confirmation = 'CONFIRM_CLEANUP_DI';
                break;
                
            case 'all':
                const allConfirmation = document.getElementById('all-confirmation').value;
                const doubleConfirmation = document.getElementById('all-double-confirmation').value;
                
                if (allConfirmation !== 'CONFIRMAR') {
                    this.showError('Digite "CONFIRMAR" no primeiro campo');
                    return null;
                }
                
                if (doubleConfirmation !== 'DELETAR TUDO') {
                    this.showError('Digite "DELETAR TUDO" no segundo campo para confirmar');
                    return null;
                }
                
                data.confirmation = 'CONFIRM_CLEANUP_ALL';
                data.double_confirmation = 'I_UNDERSTAND_THIS_DELETES_ALL_DATA';
                break;
                
            default:
                this.showError('Tipo de limpeza inválido');
                return null;
        }
        
        return data;
    }
    
    /**
     * Preparar dados para exportação
     */
    prepareExportData(exportType) {
        const data = {
            export_type: exportType,
            include_metadata: document.getElementById('include-metadata')?.checked ?? true,
            pretty_print: document.getElementById('pretty-print')?.checked ?? true,
            compression: document.getElementById('compression')?.value ?? 'none'
        };
        
        switch (exportType) {
            case 'all':
                // Nenhum parâmetro adicional necessário
                break;
                
            case 'period':
                const startDate = document.getElementById('export-start-date').value;
                const endDate = document.getElementById('export-end-date').value;
                
                if (!startDate || !endDate) {
                    this.showError('Especifique as datas de início e fim');
                    return null;
                }
                
                if (startDate > endDate) {
                    this.showError('Data de início deve ser anterior à data de fim');
                    return null;
                }
                
                data.start_date = startDate;
                data.end_date = endDate;
                break;
                
            case 'di':
                const numeroDi = document.getElementById('export-di-number').value;
                
                if (!numeroDi || !numeroDi.match(/^[0-9]{10}$/)) {
                    this.showError('Digite um número de DI válido (10 dígitos)');
                    return null;
                }
                
                data.numero_di = numeroDi;
                break;
                
            default:
                this.showError('Tipo de exportação inválido');
                return null;
        }
        
        return data;
    }
    
    /**
     * Mostrar sucesso da limpeza
     */
    showCleanupSuccess(result) {
        const totalDeleted = result.data.total_deleted || 0;
        const operation = result.data.operation;
        
        let message = `✅ Limpeza concluída com sucesso!\\n\\n`;
        message += `Operação: ${operation}\\n`;
        message += `Registros deletados: ${totalDeleted}\\n\\n`;
        
        if (result.data.records_deleted) {
            message += `Detalhes por tabela:\\n`;
            Object.entries(result.data.records_deleted).forEach(([table, count]) => {
                message += `- ${table}: ${count} registros\\n`;
            });
        }
        
        alert(message);
        this.closeAllModals();
        
        // Atualizar dashboard se existir
        if (window.expertzyCharts && typeof window.expertzyCharts.loadChartData === 'function') {
            window.expertzyCharts.loadChartData();
        }
    }
    
    /**
     * Mostrar sucesso da exportação
     */
    showExportSuccess(result) {
        const recordsCount = result.meta.records_count || 0;
        
        // Gerar nome do arquivo
        const timestamp = new Date().toISOString().slice(0, 19).replace(/[:-]/g, '');
        const filename = `database_export_${timestamp}.json`;
        
        // Criar e baixar arquivo
        const jsonString = JSON.stringify(result.data, null, 2);
        const blob = new Blob([jsonString], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        link.click();
        
        URL.revokeObjectURL(url);
        
        // Mostrar mensagem de sucesso
        alert(`✅ Exportação concluída!\\n\\nArquivo: ${filename}\\nRegistros exportados: ${recordsCount}\\n\\nO download foi iniciado automaticamente.`);
        
        this.closeAllModals();
    }
    
    /**
     * Mostrar erro
     */
    showError(message) {
        alert(`❌ Erro: ${message}`);
    }
    
    /**
     * Definir estado de processamento
     */
    setProcessing(processing, message = '') {
        this.isProcessing = processing;
        
        const processingElements = document.querySelectorAll('.processing-indicator');
        const executeButtons = document.querySelectorAll('#execute-cleanup, #execute-export');
        
        if (processing) {
            processingElements.forEach(el => {
                el.textContent = message;
                el.style.display = 'block';
            });
            
            executeButtons.forEach(btn => {
                btn.disabled = true;
                btn.textContent = 'Processando...';
            });
        } else {
            processingElements.forEach(el => {
                el.style.display = 'none';
            });
            
            executeButtons.forEach(btn => {
                btn.disabled = false;
                if (btn.id === 'execute-cleanup') {
                    btn.textContent = 'Executar Limpeza';
                } else {
                    btn.textContent = 'Executar Exportação';
                }
            });
        }
    }
    
    /**
     * Reset formulários
     */
    resetForms() {
        this.resetCleanupForm();
        this.resetExportForm();
    }
    
    /**
     * Reset formulário de limpeza
     */
    resetCleanupForm() {
        // Reset selections
        document.querySelectorAll('[data-cleanup-type]').forEach(btn => {
            btn.classList.remove('selected');
        });
        
        // Hide options
        document.querySelectorAll('.cleanup-options').forEach(option => {
            option.style.display = 'none';
        });
        
        // Reset form fields
        const inputs = document.querySelectorAll('#cleanup-modal input[type="text"], #cleanup-modal input[type="number"]');
        inputs.forEach(input => {
            input.value = '';
        });
        
        // Disable execute button
        const executeBtn = document.getElementById('execute-cleanup');
        if (executeBtn) {
            executeBtn.disabled = true;
            executeBtn.removeAttribute('data-cleanup-type');
        }
    }
    
    /**
     * Reset formulário de exportação
     */
    resetExportForm() {
        // Reset selections
        document.querySelectorAll('[data-export-type]').forEach(btn => {
            btn.classList.remove('selected');
        });
        
        // Hide options
        document.querySelectorAll('.export-options').forEach(option => {
            option.style.display = 'none';
        });
        
        // Reset form fields
        const inputs = document.querySelectorAll('#export-modal input');
        inputs.forEach(input => {
            if (input.type === 'checkbox') {
                input.checked = input.id === 'include-metadata' || input.id === 'pretty-print';
            } else {
                input.value = '';
            }
        });
        
        // Reset select
        const select = document.getElementById('compression');
        if (select) {
            select.value = 'none';
        }
        
        // Disable execute button
        const executeBtn = document.getElementById('execute-export');
        if (executeBtn) {
            executeBtn.disabled = true;
            executeBtn.removeAttribute('data-export-type');
        }
    }
    
    /**
     * Criar modal de limpeza (será chamado pelo modal component PHP)
     */
    createCleanupModal() {
        // Este método será implementado quando o modal PHP for carregado
        console.log('Modal de limpeza será criado pelo componente PHP');
    }
    
    /**
     * Criar modal de exportação (será chamado pelo modal component PHP)
     */
    createExportModal() {
        // Este método será implementado quando o modal PHP for carregado
        console.log('Modal de exportação será criado pelo componente PHP');
    }
}

// Inicializar quando DOM estiver pronto
document.addEventListener('DOMContentLoaded', () => {
    window.databaseManagement = new DatabaseManagement();
});

// Export para uso em outros scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = DatabaseManagement;
}