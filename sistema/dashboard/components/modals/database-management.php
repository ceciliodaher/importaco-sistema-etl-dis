<?php
/**
 * ================================================================================
 * MODAIS DE GERENCIAMENTO DO BANCO DE DADOS
 * Sistema ETL DI's - Interface para limpeza e exportação
 * Padrão Visual: Expertzy (#FF002D, #091A30)
 * ================================================================================
 */
?>

<!-- Modal de Limpeza do Banco de Dados -->
<div id="cleanup-modal" class="modal">
    <div class="modal-overlay"></div>
    <div class="modal-container">
        <div class="modal-header">
            <h2 class="modal-title">
                <i class="icon-database"></i>
                Limpeza do Banco de Dados
            </h2>
            <button class="modal-close" aria-label="Fechar">
                <i class="icon-close"></i>
            </button>
        </div>
        
        <div class="modal-body">
            <div class="warning-banner">
                <i class="icon-warning"></i>
                <strong>Atenção:</strong> Operações de limpeza são irreversíveis. Certifique-se de ter backup.
            </div>
            
            <!-- Seleção do Tipo de Limpeza -->
            <div class="section">
                <h3>Selecione o Tipo de Limpeza</h3>
                <div class="cleanup-types">
                    <button type="button" class="cleanup-type-btn" data-cleanup-type="test">
                        <i class="icon-test"></i>
                        <span>Dados de Teste</span>
                        <small>Remove apenas registros com prefixo 'TEST%'</small>
                    </button>
                    
                    <button type="button" class="cleanup-type-btn" data-cleanup-type="period">
                        <i class="icon-calendar"></i>
                        <span>Por Período</span>
                        <small>Remove registros mais antigos que X dias</small>
                    </button>
                    
                    <button type="button" class="cleanup-type-btn" data-cleanup-type="di">
                        <i class="icon-document"></i>
                        <span>DI Específica</span>
                        <small>Remove uma DI específica e seus dados</small>
                    </button>
                    
                    <button type="button" class="cleanup-type-btn danger" data-cleanup-type="all">
                        <i class="icon-delete-all"></i>
                        <span>Limpeza Total</span>
                        <small>⚠️ Remove TODOS os dados do sistema</small>
                    </button>
                </div>
            </div>
            
            <!-- Opções de Limpeza por Tipo -->
            
            <!-- Opções para Dados de Teste -->
            <div id="cleanup-options-test" class="cleanup-options" style="display: none;">
                <div class="section">
                    <h4>Limpeza de Dados de Teste</h4>
                    <p>Esta operação removerá todos os registros que possuem prefixo 'TEST%' no número da DI.</p>
                    
                    <div class="form-group">
                        <label for="test-confirmation">Digite "CONFIRMAR" para prosseguir:</label>
                        <input type="text" id="test-confirmation" placeholder="CONFIRMAR" class="confirmation-input">
                    </div>
                </div>
            </div>
            
            <!-- Opções para Limpeza por Período -->
            <div id="cleanup-options-period" class="cleanup-options" style="display: none;">
                <div class="section">
                    <h4>Limpeza por Período</h4>
                    <p>Remove registros com data de registro anterior ao período especificado.</p>
                    
                    <div class="form-group">
                        <label for="period-days">Remover dados mais antigos que (dias):</label>
                        <input type="number" id="period-days" min="7" placeholder="30" class="form-input">
                        <small>Mínimo: 7 dias por segurança</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="period-confirmation">Digite "CONFIRMAR" para prosseguir:</label>
                        <input type="text" id="period-confirmation" placeholder="CONFIRMAR" class="confirmation-input">
                    </div>
                </div>
            </div>
            
            <!-- Opções para DI Específica -->
            <div id="cleanup-options-di" class="cleanup-options" style="display: none;">
                <div class="section">
                    <h4>Limpeza de DI Específica</h4>
                    <p>Remove uma DI específica e todos os seus dados relacionados (adições, impostos, mercadorias, despesas).</p>
                    
                    <div class="form-group">
                        <label for="di-number">Número da DI (10 dígitos):</label>
                        <input type="text" id="di-number" placeholder="1234567890" pattern="[0-9]{10}" maxlength="10" class="form-input">
                    </div>
                    
                    <div class="form-group">
                        <label for="di-confirmation">Digite "CONFIRMAR" para prosseguir:</label>
                        <input type="text" id="di-confirmation" placeholder="CONFIRMAR" class="confirmation-input">
                    </div>
                </div>
            </div>
            
            <!-- Opções para Limpeza Total -->
            <div id="cleanup-options-all" class="cleanup-options" style="display: none;">
                <div class="section danger-section">
                    <h4>⚠️ LIMPEZA TOTAL - OPERAÇÃO PERIGOSA</h4>
                    <div class="danger-warning">
                        <p><strong>ATENÇÃO:</strong> Esta operação deletará TODOS os dados do sistema, incluindo:</p>
                        <ul>
                            <li>Todas as Declarações de Importação</li>
                            <li>Todas as Adições e Impostos</li>
                            <li>Todas as Mercadorias e Despesas</li>
                            <li>Histórico de Processamento XML</li>
                        </ul>
                        <p><strong>Esta operação é IRREVERSÍVEL!</strong></p>
                    </div>
                    
                    <div class="form-group">
                        <label for="all-confirmation">Digite "CONFIRMAR" no primeiro campo:</label>
                        <input type="text" id="all-confirmation" placeholder="CONFIRMAR" class="confirmation-input">
                    </div>
                    
                    <div class="form-group">
                        <label for="all-double-confirmation">Digite "DELETAR TUDO" no segundo campo:</label>
                        <input type="text" id="all-double-confirmation" placeholder="DELETAR TUDO" class="confirmation-input">
                    </div>
                </div>
            </div>
            
            <!-- Indicador de Processamento -->
            <div class="processing-indicator" style="display: none;">
                <i class="icon-loading spinning"></i>
                <span>Processando operação...</span>
            </div>
        </div>
        
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary modal-close">Cancelar</button>
            <button type="button" id="execute-cleanup" class="btn btn-danger" disabled>Executar Limpeza</button>
        </div>
    </div>
</div>

<!-- Modal de Exportação do Banco de Dados -->
<div id="export-modal" class="modal">
    <div class="modal-overlay"></div>
    <div class="modal-container">
        <div class="modal-header">
            <h2 class="modal-title">
                <i class="icon-download"></i>
                Exportação do Banco de Dados
            </h2>
            <button class="modal-close" aria-label="Fechar">
                <i class="icon-close"></i>
            </button>
        </div>
        
        <div class="modal-body">
            <div class="info-banner">
                <i class="icon-info"></i>
                <strong>Exportação JSON:</strong> Gera arquivo estruturado para validação dos dados importados.
            </div>
            
            <!-- Seleção do Tipo de Exportação -->
            <div class="section">
                <h3>Selecione o Tipo de Exportação</h3>
                <div class="export-types">
                    <button type="button" class="export-type-btn" data-export-type="all">
                        <i class="icon-database"></i>
                        <span>Exportação Completa</span>
                        <small>Todas as DIs processadas</small>
                    </button>
                    
                    <button type="button" class="export-type-btn" data-export-type="period">
                        <i class="icon-calendar"></i>
                        <span>Por Período</span>
                        <small>DIs dentro de um período específico</small>
                    </button>
                    
                    <button type="button" class="export-type-btn" data-export-type="di">
                        <i class="icon-document"></i>
                        <span>DI Específica</span>
                        <small>Uma DI específica com todos os detalhes</small>
                    </button>
                </div>
            </div>
            
            <!-- Opções de Exportação por Tipo -->
            
            <!-- Opções para Exportação Completa -->
            <div id="export-options-all" class="export-options" style="display: none;">
                <div class="section">
                    <h4>Exportação Completa</h4>
                    <p>Exporta todas as DIs processadas com estrutura hierárquica completa.</p>
                    <div class="info-note">
                        <strong>Atenção:</strong> Para muitos registros, considere usar exportação por período.
                    </div>
                </div>
            </div>
            
            <!-- Opções para Exportação por Período -->
            <div id="export-options-period" class="export-options" style="display: none;">
                <div class="section">
                    <h4>Exportação por Período</h4>
                    <p>Exporta DIs processadas dentro do período especificado.</p>
                    
                    <div class="date-range">
                        <div class="form-group">
                            <label for="export-start-date">Data de Início:</label>
                            <input type="date" id="export-start-date" class="form-input">
                        </div>
                        
                        <div class="form-group">
                            <label for="export-end-date">Data de Fim:</label>
                            <input type="date" id="export-end-date" class="form-input">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Opções para DI Específica -->
            <div id="export-options-di" class="export-options" style="display: none;">
                <div class="section">
                    <h4>Exportação de DI Específica</h4>
                    <p>Exporta uma DI específica com todos os dados relacionados em estrutura hierárquica.</p>
                    
                    <div class="form-group">
                        <label for="export-di-number">Número da DI (10 dígitos):</label>
                        <input type="text" id="export-di-number" placeholder="1234567890" pattern="[0-9]{10}" maxlength="10" class="form-input">
                    </div>
                </div>
            </div>
            
            <!-- Opções Gerais de Exportação -->
            <div class="section export-general-options">
                <h4>Opções de Formatação</h4>
                
                <div class="options-grid">
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="include-metadata" checked>
                            <span class="checkmark"></span>
                            Incluir Metadados
                        </label>
                        <small>Informações de exportação, checksums e auditoria</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="pretty-print" checked>
                            <span class="checkmark"></span>
                            Formatação Legível
                        </label>
                        <small>JSON formatado para leitura humana</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="compression">Compressão:</label>
                        <select id="compression" class="form-select">
                            <option value="none">Nenhuma</option>
                            <option value="gzip">GZIP</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Indicador de Processamento -->
            <div class="processing-indicator" style="display: none;">
                <i class="icon-loading spinning"></i>
                <span>Processando exportação...</span>
            </div>
        </div>
        
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary modal-close">Cancelar</button>
            <button type="button" id="execute-export" class="btn btn-primary" disabled>Executar Exportação</button>
        </div>
    </div>
</div>

<style>
/* Estilos específicos para os modais de gerenciamento */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 10000;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.modal.active {
    display: flex;
    opacity: 1;
    visibility: visible;
    align-items: center;
    justify-content: center;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(9, 26, 48, 0.8);
    backdrop-filter: blur(4px);
}

.modal-container {
    position: relative;
    background: white;
    border-radius: 12px;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
    max-width: 800px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    transform: scale(0.9);
    transition: transform 0.3s ease;
}

.modal.active .modal-container {
    transform: scale(1);
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 24px 32px;
    border-bottom: 1px solid #e5e7eb;
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    border-radius: 12px 12px 0 0;
}

.modal-title {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 1.5rem;
    font-weight: 600;
    color: #091A30;
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    color: #6b7280;
    cursor: pointer;
    font-size: 1.5rem;
    padding: 8px;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.modal-close:hover {
    background: rgba(255, 0, 45, 0.1);
    color: #FF002D;
}

.modal-body {
    padding: 32px;
}

.modal-footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 16px;
    padding: 24px 32px;
    border-top: 1px solid #e5e7eb;
    background: #f8fafc;
    border-radius: 0 0 12px 12px;
}

/* Banners de Aviso */
.warning-banner, .info-banner {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    border-radius: 8px;
    margin-bottom: 24px;
    font-size: 0.95rem;
}

.warning-banner {
    background: #fef3cd;
    border: 1px solid #fbbf24;
    color: #92400e;
}

.info-banner {
    background: #dbeafe;
    border: 1px solid #60a5fa;
    color: #1e40af;
}

/* Seções */
.section {
    margin-bottom: 32px;
}

.section h3 {
    color: #091A30;
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 16px;
}

.section h4 {
    color: #374151;
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 12px;
}

/* Tipos de Limpeza/Exportação */
.cleanup-types, .export-types {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 16px;
}

.cleanup-type-btn, .export-type-btn {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    padding: 20px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    background: white;
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: left;
}

.cleanup-type-btn:hover, .export-type-btn:hover {
    border-color: #FF002D;
    box-shadow: 0 4px 12px rgba(255, 0, 45, 0.15);
}

.cleanup-type-btn.selected, .export-type-btn.selected {
    border-color: #FF002D;
    background: #fff5f5;
    box-shadow: 0 4px 12px rgba(255, 0, 45, 0.15);
}

.cleanup-type-btn.danger {
    border-color: #dc2626;
}

.cleanup-type-btn.danger:hover,
.cleanup-type-btn.danger.selected {
    border-color: #dc2626;
    background: #fef2f2;
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.15);
}

.cleanup-type-btn i, .export-type-btn i {
    font-size: 1.5rem;
    margin-bottom: 8px;
    color: #FF002D;
}

.cleanup-type-btn.danger i {
    color: #dc2626;
}

.cleanup-type-btn span, .export-type-btn span {
    font-weight: 600;
    color: #091A30;
    margin-bottom: 4px;
}

.cleanup-type-btn small, .export-type-btn small {
    color: #6b7280;
    font-size: 0.85rem;
    line-height: 1.3;
}

/* Opções de Limpeza/Exportação */
.cleanup-options, .export-options {
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 24px;
    margin-top: 24px;
}

.danger-section {
    border-color: #dc2626;
    background: #fef2f2;
}

.danger-warning {
    background: white;
    border: 1px solid #dc2626;
    border-radius: 6px;
    padding: 16px;
    margin-bottom: 20px;
}

.danger-warning p {
    color: #dc2626;
    font-weight: 600;
    margin-bottom: 8px;
}

.danger-warning ul {
    color: #7f1d1d;
    margin: 12px 0 12px 20px;
}

.danger-warning li {
    margin-bottom: 4px;
}

/* Formulários */
.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-weight: 600;
    color: #374151;
    margin-bottom: 6px;
}

.form-input, .form-select {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 0.95rem;
    transition: border-color 0.2s ease;
}

.form-input:focus, .form-select:focus {
    outline: none;
    border-color: #FF002D;
    box-shadow: 0 0 0 3px rgba(255, 0, 45, 0.1);
}

.confirmation-input {
    font-family: monospace;
    font-weight: 600;
    text-transform: uppercase;
}

.form-group small {
    display: block;
    color: #6b7280;
    font-size: 0.85rem;
    margin-top: 4px;
}

/* Data Range */
.date-range {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

@media (max-width: 640px) {
    .date-range {
        grid-template-columns: 1fr;
    }
}

/* Checkbox */
.checkbox-label {
    display: flex !important;
    align-items: center;
    cursor: pointer;
    margin-bottom: 0 !important;
}

.checkbox-label input[type="checkbox"] {
    width: auto !important;
    margin-right: 8px;
    cursor: pointer;
}

/* Options Grid */
.options-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
}

/* Botões */
.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.95rem;
}

.btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-primary {
    background: #FF002D;
    color: white;
}

.btn-primary:hover:not(:disabled) {
    background: #e6002a;
    box-shadow: 0 4px 12px rgba(255, 0, 45, 0.3);
}

.btn-secondary {
    background: #6b7280;
    color: white;
}

.btn-secondary:hover {
    background: #5b6470;
}

.btn-danger {
    background: #dc2626;
    color: white;
}

.btn-danger:hover:not(:disabled) {
    background: #c53030;
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
}

/* Indicador de Processamento */
.processing-indicator {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding: 20px;
    background: #f0f9ff;
    border: 1px solid #0ea5e9;
    border-radius: 8px;
    color: #0c4a6e;
    font-weight: 600;
}

.spinning {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Info Note */
.info-note {
    background: #f0f9ff;
    border: 1px solid #0ea5e9;
    border-radius: 6px;
    padding: 12px;
    color: #0c4a6e;
    font-size: 0.9rem;
    margin-top: 12px;
}

/* Responsividade */
@media (max-width: 768px) {
    .modal-container {
        width: 95%;
        margin: 20px;
        max-height: calc(100vh - 40px);
    }
    
    .modal-header, .modal-body, .modal-footer {
        padding: 20px;
    }
    
    .cleanup-types, .export-types {
        grid-template-columns: 1fr;
    }
    
    .options-grid {
        grid-template-columns: 1fr;
    }
}
</style>