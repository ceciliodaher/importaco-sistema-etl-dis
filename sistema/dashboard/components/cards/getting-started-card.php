<?php
/**
 * ================================================================================
 * CARD "COMO COMEÇAR" - DASHBOARD ETL DI's
 * Interface de orientação para primeiros passos
 * ================================================================================
 */
?>

<div class="getting-started-card card animate-fade-in">
    <div class="card-header">
        <h3>
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                <path d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" stroke="currentColor" stroke-width="2"/>
            </svg>
            Como Começar
        </h3>
        <span class="card-badge">Guia Rápido</span>
    </div>
    
    <div class="card-content">
        <p class="card-description">
            Siga estes passos para usar o sistema ETL de DI's pela primeira vez:
        </p>
        
        <div class="steps-list">
            <div class="step-item">
                <div class="step-number">1</div>
                <div class="step-content">
                    <h4>Verificar Sistema</h4>
                    <p>Clique em "Verificar Status" para confirmar que o banco de dados está funcionando.</p>
                    <div class="step-actions">
                        <button type="button" class="btn-quick-action" onclick="manualControlPanel.handleVerifyDatabase()">
                            Verificar Agora
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="step-item">
                <div class="step-number">2</div>
                <div class="step-content">
                    <h4>Importar Dados</h4>
                    <p>Faça upload de arquivos XML de Declaração de Importação para processar.</p>
                    <div class="step-actions">
                        <button type="button" class="btn-quick-action" onclick="manualControlPanel.handleImportXML()">
                            Importar XML
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="step-item">
                <div class="step-number">3</div>
                <div class="step-content">
                    <h4>Visualizar Dados</h4>
                    <p>Após importar, carregue os gráficos e estatísticas para análise.</p>
                    <div class="step-actions">
                        <button type="button" class="btn-quick-action" onclick="manualControlPanel.handleLoadCharts()" disabled>
                            Carregar Gráficos
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="quick-tips">
            <h4>Dicas Rápidas</h4>
            <ul>
                <li><strong>Atalhos de Teclado:</strong> Ctrl+Shift+I (Importar), Ctrl+Shift+R (Atualizar)</li>
                <li><strong>Auto-refresh:</strong> Ative nas configurações para atualizações automáticas</li>
                <li><strong>Suporte:</strong> Consulte a documentação para instruções detalhadas</li>
            </ul>
        </div>
    </div>
</div>

<style>
.getting-started-card {
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    border: 2px solid #e2e8f0;
    border-radius: 16px;
    overflow: hidden;
    margin-bottom: 2rem;
}

.getting-started-card .card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.5rem;
    background: rgba(255, 255, 255, 0.8);
    border-bottom: 1px solid #e2e8f0;
}

.getting-started-card .card-header h3 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #1e293b;
    font-weight: 600;
    margin: 0;
}

.getting-started-card .card-header svg {
    color: #3b82f6;
}

.card-badge {
    padding: 0.25rem 0.75rem;
    background: #3b82f6;
    color: white;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.getting-started-card .card-content {
    padding: 1.5rem;
}

.card-description {
    color: #64748b;
    margin-bottom: 1.5rem;
    font-size: 0.95rem;
    line-height: 1.5;
}

.steps-list {
    margin-bottom: 2rem;
}

.step-item {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.6);
    border-radius: 12px;
    border: 1px solid #e2e8f0;
}

.step-item:last-child {
    margin-bottom: 0;
}

.step-number {
    width: 32px;
    height: 32px;
    background: #3b82f6;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.9rem;
    flex-shrink: 0;
}

.step-content {
    flex: 1;
}

.step-content h4 {
    color: #1e293b;
    font-weight: 600;
    margin: 0 0 0.5rem 0;
    font-size: 1rem;
}

.step-content p {
    color: #64748b;
    margin: 0 0 1rem 0;
    font-size: 0.9rem;
    line-height: 1.4;
}

.step-actions {
    margin-top: 0.75rem;
}

.btn-quick-action {
    padding: 0.5rem 1rem;
    background: #3b82f6;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-quick-action:hover:not(:disabled) {
    background: #2563eb;
    transform: translateY(-1px);
}

.btn-quick-action:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    background: #94a3b8;
}

.quick-tips {
    padding: 1rem;
    background: rgba(59, 130, 246, 0.05);
    border-radius: 8px;
    border: 1px solid rgba(59, 130, 246, 0.1);
}

.quick-tips h4 {
    color: #1e293b;
    font-weight: 600;
    margin: 0 0 0.75rem 0;
    font-size: 0.95rem;
}

.quick-tips ul {
    margin: 0;
    padding-left: 1.2rem;
    list-style-type: disc;
}

.quick-tips li {
    color: #64748b;
    font-size: 0.85rem;
    line-height: 1.4;
    margin-bottom: 0.5rem;
}

.quick-tips li:last-child {
    margin-bottom: 0;
}

.quick-tips strong {
    color: #1e293b;
}

@media (max-width: 768px) {
    .getting-started-card .card-header {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }
    
    .step-item {
        flex-direction: column;
        text-align: center;
    }
    
    .step-number {
        align-self: center;
    }
}
</style>