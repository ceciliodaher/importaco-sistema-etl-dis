/**
 * ================================================================================
 * EXTENSÕES AVANÇADAS PARA GRÁFICOS - PADRÃO EXPERTZY
 * Sistema ETL DI's - Funcionalidades avançadas de interatividade
 * ================================================================================
 */

// Extensões para a classe ExpertzyChartsSystem
if (typeof ExpertzyChartsSystem !== 'undefined') {
    
    /**
     * Extensão para filtros avançados
     */
    ExpertzyChartsSystem.prototype.updateAllChartsWithPeriod = function(period) {
        this.currentFilters = this.currentFilters || {};
        this.currentFilters.period = period;
        
        // Mostrar skeletons
        document.querySelectorAll('[data-chart]').forEach(container => {
            this.showChartSkeleton(container);
        });
        
        // Recarregar dados com novo período
        this.loadFilteredData();
    };
    
    ExpertzyChartsSystem.prototype.updateAllChartsWithCurrency = function(currency) {
        this.currentFilters = this.currentFilters || {};
        this.currentFilters.currency = currency;
        
        // Filtrar dados existentes ou recarregar
        if (currency === 'all') {
            this.loadChartData();
        } else {
            this.loadFilteredData();
        }
    };
    
    ExpertzyChartsSystem.prototype.updateAllChartsWithState = function(state) {
        this.currentFilters = this.currentFilters || {};
        this.currentFilters.state = state;
        this.loadFilteredData();
    };
    
    ExpertzyChartsSystem.prototype.updateAllChartsWithTaxRegime = function(regime) {
        this.currentFilters = this.currentFilters || {};
        this.currentFilters.taxRegime = regime;
        this.loadFilteredData();
    };
    
    /**
     * Carregamento de dados filtrados
     */
    ExpertzyChartsSystem.prototype.loadFilteredData = async function() {
        const filters = this.currentFilters || {};
        const params = new URLSearchParams(filters);
        
        try {
            const response = await fetch(`/api/dashboard/charts/all?${params.toString()}`);
            const data = await response.json();
            
            if (data.success) {
                this.renderAllCharts(data.charts);
            }
        } catch (error) {
            console.error('Erro ao carregar dados filtrados:', error);
            this.showEmptyStates();
        }
    };
    
    /**
     * Mini gráficos para cards estatísticos
     */
    ExpertzyChartsSystem.prototype.createMiniChart = function(canvasId, data, type = 'line') {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        
        // Limpar chart existente
        const existingChart = Chart.getChart(ctx);
        if (existingChart) {
            existingChart.destroy();
        }
        
        new Chart(ctx, {
            type: type,
            data: {
                labels: data.labels || [],
                datasets: [{
                    data: data.values || [],
                    borderColor: this.colors.primary,
                    backgroundColor: 'rgba(255, 0, 45, 0.1)',
                    borderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 0,
                    tension: 0.4,
                    fill: type === 'line'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { enabled: false }
                },
                scales: {
                    x: { display: false },
                    y: { display: false }
                },
                elements: {
                    line: { borderWidth: 2 },
                    point: { radius: 0 }
                },
                animation: {
                    duration: 1000,
                    easing: 'easeInOutQuart'
                }
            }
        });
    };
    
    /**
     * Sistema de drill-down avançado
     */
    ExpertzyChartsSystem.prototype.showTemporalDrillDown = function(month, data) {
        const modal = this.createDrillDownModal({
            title: `Detalhes de ${month}`,
            content: this.generateTemporalDrillDownContent(month, data)
        });
        
        this.showModal(modal);
    };
    
    ExpertzyChartsSystem.prototype.showTaxDrillDown = function(taxType, data) {
        const modal = this.createDrillDownModal({
            title: `Detalhes - ${taxType.toUpperCase()}`,
            content: this.generateTaxDrillDownContent(taxType, data)
        });
        
        this.showModal(modal);
    };
    
    ExpertzyChartsSystem.prototype.showExpenseDrillDown = function(category, data) {
        const modal = this.createDrillDownModal({
            title: `Despesas - ${category}`,
            content: this.generateExpenseDrillDownContent(category, data)
        });
        
        this.showModal(modal);
    };
    
    ExpertzyChartsSystem.prototype.showStateDrillDown = function(state, data) {
        const modal = this.createDrillDownModal({
            title: `Performance - ${state}`,
            content: this.generateStateDrillDownContent(state, data)
        });
        
        this.showModal(modal);
    };
    
    /**
     * Gerador de conteúdo para modais
     */
    ExpertzyChartsSystem.prototype.generateTemporalDrillDownContent = function(month, data) {
        return `
            <div class="drill-down-content">
                <div class="drill-down-stats">
                    <div class="drill-stat-item">
                        <span class="drill-stat-label">DIs Processadas</span>
                        <span class="drill-stat-value">${data.dis_count || 0}</span>
                    </div>
                    <div class="drill-stat-item">
                        <span class="drill-stat-label">Valor CIF</span>
                        <span class="drill-stat-value">R$ ${(data.cif_values || 0).toLocaleString('pt-BR')}M</span>
                    </div>
                    <div class="drill-stat-item">
                        <span class="drill-stat-label">Crescimento</span>
                        <span class="drill-stat-value ${data.growth > 0 ? 'positive' : 'negative'}">${data.growth || 0}%</span>
                    </div>
                </div>
                <div class="drill-down-chart">
                    <canvas id="drillDownTemporalChart" width="400" height="200"></canvas>
                </div>
                <div class="drill-down-actions">
                    <button class="btn btn-primary" onclick="exportDrillDownData('${month}', 'temporal')">Exportar Dados</button>
                    <button class="btn btn-secondary" onclick="viewDetailedReport('${month}', 'temporal')">Relatório Detalhado</button>
                </div>
            </div>
        `;
    };
    
    ExpertzyChartsSystem.prototype.generateTaxDrillDownContent = function(taxType, data) {
        const taxDetails = {
            ii: { name: 'Imposto de Importação', color: '#FF002D' },
            ipi: { name: 'IPI', color: '#091A30' },
            pis: { name: 'PIS', color: '#28a745' },
            cofins: { name: 'COFINS', color: '#ffc107' },
            icms: { name: 'ICMS', color: '#007bff' }
        };
        
        const tax = taxDetails[taxType] || { name: taxType.toUpperCase(), color: '#6c757d' };
        
        return `
            <div class="drill-down-content">
                <div class="drill-down-header">
                    <div class="tax-indicator" style="background: ${tax.color}"></div>
                    <h4>${tax.name}</h4>
                </div>
                <div class="drill-down-stats">
                    <div class="drill-stat-item">
                        <span class="drill-stat-label">Valor Total</span>
                        <span class="drill-stat-value">R$ ${data[`${taxType}_total`]?.toLocaleString('pt-BR') || 0}M</span>
                    </div>
                    <div class="drill-stat-item">
                        <span class="drill-stat-label">% do Total</span>
                        <span class="drill-stat-value">${data.percentages?.[0] || 0}%</span>
                    </div>
                    <div class="drill-stat-item">
                        <span class="drill-stat-label">Operações</span>
                        <span class="drill-stat-value">1,245</span>
                    </div>
                </div>
                <div class="drill-down-breakdown">
                    <h5>Breakdown por Estado</h5>
                    <div class="breakdown-list">
                        <div class="breakdown-item">
                            <span>SP</span>
                            <div class="breakdown-bar">
                                <div class="breakdown-fill" style="width: 45%; background: ${tax.color}"></div>
                            </div>
                            <span>45%</span>
                        </div>
                        <div class="breakdown-item">
                            <span>RJ</span>
                            <div class="breakdown-bar">
                                <div class="breakdown-fill" style="width: 23%; background: ${tax.color}"></div>
                            </div>
                            <span>23%</span>
                        </div>
                        <div class="breakdown-item">
                            <span>RS</span>
                            <div class="breakdown-bar">
                                <div class="breakdown-fill" style="width: 18%; background: ${tax.color}"></div>
                            </div>
                            <span>18%</span>
                        </div>
                    </div>
                </div>
            </div>
        `;
    };
    
    ExpertzyChartsSystem.prototype.generateExpenseDrillDownContent = function(category, data) {
        return `
            <div class="drill-down-content">
                <div class="drill-down-stats">
                    <div class="drill-stat-item">
                        <span class="drill-stat-label">Valor Total</span>
                        <span class="drill-stat-value">R$ ${data.values?.[0]?.toLocaleString('pt-BR') || 0}</span>
                    </div>
                    <div class="drill-stat-item">
                        <span class="drill-stat-label">Operações</span>
                        <span class="drill-stat-value">234</span>
                    </div>
                    <div class="drill-stat-item">
                        <span class="drill-stat-label">Valor Médio</span>
                        <span class="drill-stat-value">R$ 1,287.50</span>
                    </div>
                </div>
                <div class="drill-down-trend">
                    <h5>Evolução nos Últimos 6 Meses</h5>
                    <canvas id="drillDownExpenseChart" width="400" height="150"></canvas>
                </div>
                <div class="drill-down-comparison">
                    <h5>Comparação com Outras Categorias</h5>
                    <div class="comparison-grid">
                        <div class="comparison-item highlight">
                            <span class="category">${category}</span>
                            <span class="value">R$ 125.8K</span>
                            <span class="percentage">28.3%</span>
                        </div>
                        <div class="comparison-item">
                            <span class="category">THC</span>
                            <span class="value">R$ 89.3K</span>
                            <span class="percentage">20.1%</span>
                        </div>
                        <div class="comparison-item">
                            <span class="category">Armazenagem</span>
                            <span class="value">R$ 67.2K</span>
                            <span class="percentage">15.1%</span>
                        </div>
                    </div>
                </div>
            </div>
        `;
    };
    
    ExpertzyChartsSystem.prototype.generateStateDrillDownContent = function(state, stateData) {
        const stateDetails = stateData.states?.find(s => s.uf === state);
        
        return `
            <div class="drill-down-content">
                <div class="drill-down-stats">
                    <div class="drill-stat-item">
                        <span class="drill-stat-label">DIs Processadas</span>
                        <span class="drill-stat-value">${stateDetails?.dis_count || 0}</span>
                    </div>
                    <div class="drill-stat-item">
                        <span class="drill-stat-label">Performance</span>
                        <span class="drill-stat-value performance-${stateDetails?.performance > 75 ? 'high' : stateDetails?.performance > 50 ? 'medium' : 'low'}">${stateDetails?.performance || 0}%</span>
                    </div>
                    <div class="drill-stat-item">
                        <span class="drill-stat-label">Benefícios Aplicados</span>
                        <span class="drill-stat-value">${Math.round((stateDetails?.dis_count || 0) * (stateDetails?.performance || 0) / 100)}</span>
                    </div>
                </div>
                <div class="drill-down-benefits">
                    <h5>Tipos de Benefícios Fiscais</h5>
                    <div class="benefits-list">
                        <div class="benefit-item">
                            <div class="benefit-icon">🌱</div>
                            <div class="benefit-info">
                                <span class="benefit-name">Zona Franca</span>
                                <span class="benefit-desc">Redução de 88% no II</span>
                            </div>
                            <span class="benefit-count">45 DIs</span>
                        </div>
                        <div class="benefit-item">
                            <div class="benefit-icon">🏭</div>
                            <div class="benefit-info">
                                <span class="benefit-name">Ex-Tarifário</span>
                                <span class="benefit-desc">Redução II para BK/BIT</span>
                            </div>
                            <span class="benefit-count">23 DIs</span>
                        </div>
                        <div class="benefit-item">
                            <div class="benefit-icon">🚢</div>
                            <div class="benefit-info">
                                <span class="benefit-name">Acordos Comerciais</span>
                                <span class="benefit-desc">MERCOSUL, Chile, Peru</span>
                            </div>
                            <span class="benefit-count">78 DIs</span>
                        </div>
                    </div>
                </div>
            </div>
        `;
    };
    
    /**
     * Sistema de modal avançado
     */
    ExpertzyChartsSystem.prototype.createDrillDownModal = function(options) {
        return {
            title: options.title,
            content: options.content,
            size: options.size || 'large',
            closable: options.closable !== false
        };
    };
    
    ExpertzyChartsSystem.prototype.showModal = function(modal) {
        const modalHTML = `
            <div class="expertzy-modal-overlay" onclick="closeModal(event)">
                <div class="expertzy-modal ${modal.size}" onclick="event.stopPropagation()">
                    <div class="expertzy-modal-header">
                        <h3>${modal.title}</h3>
                        ${modal.closable ? '<button class="modal-close-btn" onclick="closeModal()">&times;</button>' : ''}
                    </div>
                    <div class="expertzy-modal-body">
                        ${modal.content}
                    </div>
                </div>
            </div>
        `;
        
        // Remover modal existente
        const existingModal = document.querySelector('.expertzy-modal-overlay');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Adicionar novo modal
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        // Animar entrada
        setTimeout(() => {
            const modalOverlay = document.querySelector('.expertzy-modal-overlay');
            if (modalOverlay) {
                modalOverlay.classList.add('show');
            }
        }, 10);
        
        // Renderizar gráficos do drill-down se existirem
        this.renderDrillDownCharts();
    };
    
    ExpertzyChartsSystem.prototype.renderDrillDownCharts = function() {
        // Aguardar modal estar visível
        setTimeout(() => {
            const drillCharts = document.querySelectorAll('[id^="drillDown"][id$="Chart"]');
            drillCharts.forEach(canvas => {
                if (canvas.id.includes('Temporal')) {
                    this.createDrillDownTemporalChart(canvas);
                } else if (canvas.id.includes('Expense')) {
                    this.createDrillDownExpenseChart(canvas);
                }
            });
        }, 100);
    };
    
    ExpertzyChartsSystem.prototype.createDrillDownTemporalChart = function(canvas) {
        const ctx = canvas.getContext('2d');
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun'],
                datasets: [{
                    label: 'DIs por Dia',
                    data: [12, 19, 8, 15, 22, 18],
                    borderColor: this.colors.primary,
                    backgroundColor: 'rgba(255, 0, 45, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,0.05)' }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    };
    
    ExpertzyChartsSystem.prototype.createDrillDownExpenseChart = function(canvas) {
        const ctx = canvas.getContext('2d');
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'],
                datasets: [{
                    label: 'Valor (R$ mil)',
                    data: [145, 132, 158, 142, 139, 151],
                    backgroundColor: this.colors.warning,
                    borderColor: this.colors.warning,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,0.05)' }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    };
}

/**
 * Funções globais de utilidade
 */
window.closeModal = function(event) {
    if (!event || event.target.classList.contains('expertzy-modal-overlay') || event.target.classList.contains('modal-close-btn')) {
        const modalOverlay = document.querySelector('.expertzy-modal-overlay');
        if (modalOverlay) {
            modalOverlay.classList.add('fade-out');
            setTimeout(() => {
                modalOverlay.remove();
            }, 300);
        }
    }
};

window.exportDrillDownData = function(identifier, type) {
    console.log(`Exportando dados de drill-down: ${type} - ${identifier}`);
    // Implementar export específico
};

window.viewDetailedReport = function(identifier, type) {
    console.log(`Visualizando relatório detalhado: ${type} - ${identifier}`);
    // Implementar navegação para relatório
};

/**
 * Estilos CSS dinâmicos para modais e drill-down
 */
const dynamicStyles = `
<style>
.expertzy-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(9, 26, 48, 0.8);
    backdrop-filter: blur(10px);
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.expertzy-modal-overlay.show {
    opacity: 1;
}

.expertzy-modal-overlay.fade-out {
    opacity: 0;
}

.expertzy-modal {
    background: white;
    border-radius: 20px;
    max-width: 90vw;
    max-height: 90vh;
    overflow: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    transform: scale(0.9);
    transition: transform 0.3s ease;
}

.expertzy-modal-overlay.show .expertzy-modal {
    transform: scale(1);
}

.expertzy-modal.large {
    width: 800px;
    min-height: 600px;
}

.expertzy-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.5rem 2rem;
    border-bottom: 1px solid #e9ecef;
}

.expertzy-modal-header h3 {
    color: #343a40;
    font-weight: 700;
    margin: 0;
}

.modal-close-btn {
    width: 32px;
    height: 32px;
    border: none;
    background: #f8f9fa;
    color: #6c757d;
    border-radius: 8px;
    cursor: pointer;
    font-size: 1.5rem;
    line-height: 1;
    transition: all 0.3s ease;
}

.modal-close-btn:hover {
    background: #FF002D;
    color: white;
}

.expertzy-modal-body {
    padding: 2rem;
}

.drill-down-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.drill-stat-item {
    text-align: center;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 12px;
    border: 1px solid #e9ecef;
}

.drill-stat-label {
    display: block;
    font-size: 0.85rem;
    color: #6c757d;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.5rem;
}

.drill-stat-value {
    display: block;
    font-size: 1.5rem;
    font-weight: 700;
    color: #FF002D;
}

.drill-stat-value.positive {
    color: #28a745;
}

.drill-stat-value.negative {
    color: #dc3545;
}

.drill-down-chart {
    margin: 2rem 0;
    height: 200px;
}

.drill-down-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid #e9ecef;
}

.breakdown-list {
    display: flex;
    flex-direction: column;
    gap: 0.8rem;
}

.breakdown-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.8rem;
    background: #f8f9fa;
    border-radius: 8px;
}

.breakdown-item span:first-child {
    min-width: 30px;
    font-weight: 600;
    color: #343a40;
}

.breakdown-bar {
    flex: 1;
    height: 8px;
    background: #e9ecef;
    border-radius: 4px;
    overflow: hidden;
}

.breakdown-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.8s ease;
}

.benefits-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.benefit-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 12px;
    border: 1px solid #e9ecef;
    transition: all 0.3s ease;
}

.benefit-item:hover {
    border-color: #FF002D;
    transform: translateX(5px);
}

.benefit-icon {
    font-size: 1.5rem;
    width: 40px;
    text-align: center;
}

.benefit-info {
    flex: 1;
}

.benefit-name {
    display: block;
    font-weight: 600;
    color: #343a40;
    margin-bottom: 0.2rem;
}

.benefit-desc {
    display: block;
    font-size: 0.85rem;
    color: #6c757d;
}

.benefit-count {
    font-weight: 600;
    color: #FF002D;
    background: rgba(255, 0, 45, 0.1);
    padding: 0.3rem 0.8rem;
    border-radius: 12px;
    font-size: 0.85rem;
}

.comparison-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.comparison-item {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 12px;
    border: 1px solid #e9ecef;
    text-align: center;
}

.comparison-item.highlight {
    border-color: #FF002D;
    background: rgba(255, 0, 45, 0.05);
}

.comparison-item .category {
    font-weight: 600;
    color: #343a40;
}

.comparison-item .value {
    font-size: 1.2rem;
    font-weight: 700;
    color: #FF002D;
}

.comparison-item .percentage {
    font-size: 0.9rem;
    color: #6c757d;
}

.performance-high {
    color: #28a745 !important;
}

.performance-medium {
    color: #ffc107 !important;
}

.performance-low {
    color: #dc3545 !important;
}

@media (max-width: 768px) {
    .expertzy-modal {
        width: 95vw;
        margin: 0 10px;
    }
    
    .expertzy-modal-body {
        padding: 1rem;
    }
    
    .drill-down-stats {
        grid-template-columns: 1fr;
    }
    
    .drill-down-actions {
        flex-direction: column;
    }
}
</style>
`;

// Injetar estilos
document.head.insertAdjacentHTML('beforeend', dynamicStyles);

console.log('✅ Extensões avançadas dos gráficos carregadas com sucesso');