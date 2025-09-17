/**
 * ================================================================================
 * SISTEMA DE GRÁFICOS INTERATIVOS - PADRÃO EXPERTZY
 * Chart.js v4+ integrado com sistema ETL DI's
 * Cores: #FF002D (vermelho), #091A30 (azul escuro)
 * ================================================================================
 */

class ExpertzyChartsSystem {
    constructor() {
        this.charts = new Map();
        this.colors = {
            primary: '#FF002D',
            secondary: '#091A30',
            success: '#28a745',
            warning: '#ffc107',
            info: '#007bff',
            light: '#f8f9fa',
            dark: '#343a40',
            gray: '#6c757d'
        };
        
        this.gradients = {};
        this.isInitialized = false;
        
        this.init();
    }

    /**
     * Inicialização do sistema
     */
    init() {
        if (this.isInitialized) return;
        
        this.setupChartDefaults();
        this.createColorPalettes();
        // this.initWebSocket(); // DESABILITADO - Controle manual
        this.setupResizeObservers();
        // this.loadChartData(); // REMOVIDO - Carregamento manual apenas
        
        this.isInitialized = true;
        console.log('✅ Sistema de Gráficos Expertzy inicializado (modo manual)');
    }

    /**
     * Configurações padrão do Chart.js
     */
    setupChartDefaults() {
        Chart.defaults.font.family = 'var(--expertzy-font-family)';
        Chart.defaults.font.size = 12;
        Chart.defaults.color = this.colors.gray;
        Chart.defaults.scale.grid.color = 'rgba(0,0,0,0.05)';
        Chart.defaults.plugins.legend.display = true;
        Chart.defaults.plugins.legend.position = 'bottom';
        Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(9, 26, 48, 0.95)';
        Chart.defaults.plugins.tooltip.titleColor = '#ffffff';
        Chart.defaults.plugins.tooltip.bodyColor = '#ffffff';
        Chart.defaults.plugins.tooltip.cornerRadius = 8;
        Chart.defaults.animation.duration = 1000;
        Chart.defaults.animation.easing = 'easeInOutQuart';
        Chart.defaults.responsive = true;
        Chart.defaults.maintainAspectRatio = false;
    }

    /**
     * Criação de paletas de cores harmoniosas
     */
    createColorPalettes() {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');

        // Gradiente principal Expertzy
        this.gradients.primary = ctx.createLinearGradient(0, 0, 0, 400);
        this.gradients.primary.addColorStop(0, 'rgba(255, 0, 45, 0.8)');
        this.gradients.primary.addColorStop(1, 'rgba(255, 0, 45, 0.1)');

        // Gradiente secundário
        this.gradients.secondary = ctx.createLinearGradient(0, 0, 0, 400);
        this.gradients.secondary.addColorStop(0, 'rgba(9, 26, 48, 0.8)');
        this.gradients.secondary.addColorStop(1, 'rgba(9, 26, 48, 0.1)');

        // Paleta para múltiplos datasets
        this.dataColors = [
            this.colors.primary,
            this.colors.secondary,
            this.colors.success,
            this.colors.warning,
            this.colors.info,
            '#e83e8c', // Pink
            '#6f42c1', // Purple
            '#fd7e14', // Orange
            '#20c997', // Teal
            '#6610f2'  // Indigo
        ];

        // Paleta para gráfico de pizza/donut (mais cores)
        this.pieColors = [
            '#FF002D', '#091A30', '#28a745', '#ffc107', '#007bff',
            '#e83e8c', '#6f42c1', '#fd7e14', '#20c997', '#6610f2',
            '#17a2b8', '#dc3545', '#28a745', '#ffc107', '#6c757d',
            '#343a40'
        ];
    }

    /**
     * Sistema de polling DESABILITADO - Controle manual apenas
     */
    initWebSocket() {
        // POLLING DESABILITADO - Sistema em modo manual
        // setInterval(() => {
        //     this.refreshCharts();
        // }, 30000);
        
        console.log('📊 Sistema de polling DESABILITADO - Controle manual ativo');
    }
    
    /**
     * Atualiza gráficos - APENAS MANUAL
     */
    refreshCharts() {
        console.log('🔄 Atualização manual de gráficos solicitada');
        this.loadChartData(true); // Manual trigger = true
    }
    
    /**
     * Método público para carregamento manual
     */
    manualLoadCharts() {
        console.log('🎯 Carregamento manual iniciado pelo usuário');
        return this.loadChartData(true);
    }

    /**
     * Observer para redimensionamento responsivo
     */
    setupResizeObservers() {
        if (window.ResizeObserver) {
            const resizeObserver = new ResizeObserver((entries) => {
                entries.forEach((entry) => {
                    const chartContainer = entry.target;
                    const chartInstance = this.charts.get(chartContainer.id);
                    if (chartInstance) {
                        chartInstance.resize();
                    }
                });
            });

            // Observar todos os containers de gráficos
            document.querySelectorAll('[data-chart]').forEach((container) => {
                resizeObserver.observe(container);
            });
        }
    }

    /**
     * Carregamento de dados - APENAS MANUAL
     */
    async loadChartData(manualTrigger = false) {
        if (!manualTrigger) {
            console.log('🔄 Carregamento automático BLOQUEADO - Use controle manual');
            this.showEmptyStates(); // Mostra placeholders vazios
            return;
        }
        
        try {
            const response = await fetch('/sistema/dashboard/api/dashboard/charts.php?type=all');
            const data = await response.json();
            
            if (data.success) {
                this.renderAllCharts(data.charts);
            }
        } catch (error) {
            console.error('Erro ao carregar dados dos gráficos:', error);
            this.showEmptyStates();
        }
    }

    /**
     * Renderização de todos os gráficos
     */
    renderAllCharts(chartsData) {
        // 1. Evolução temporal de importações
        if (chartsData.temporal) {
            this.createTemporalChart(chartsData.temporal);
        }

        // 2. Impostos por tipo
        if (chartsData.taxes) {
            this.createTaxesChart(chartsData.taxes);
        }

        // 3. Distribuição de despesas portuárias
        if (chartsData.expenses) {
            this.createExpensesChart(chartsData.expenses);
        }

        // 4. Segmentação por moedas
        if (chartsData.currencies) {
            this.createCurrenciesChart(chartsData.currencies);
        }

        // 5. Performance por estado
        if (chartsData.states) {
            this.createStatesHeatmap(chartsData.states);
        }

        // 6. Correlação câmbio vs custo
        if (chartsData.correlation) {
            this.createCorrelationChart(chartsData.correlation);
        }
    }

    /**
     * 1. Gráfico de linha - Evolução temporal
     */
    createTemporalChart(data) {
        const ctx = document.getElementById('temporalChart');
        if (!ctx) return;

        this.showChartSkeleton(ctx.parentElement);

        const chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.months,
                datasets: [
                    {
                        label: 'DIs Processadas',
                        data: data.dis_count,
                        borderColor: this.colors.primary,
                        backgroundColor: this.gradients.primary,
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: this.colors.primary,
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 6,
                        pointHoverRadius: 8
                    },
                    {
                        label: 'Valor CIF (milhões)',
                        data: data.cif_values,
                        borderColor: this.colors.secondary,
                        backgroundColor: this.gradients.secondary,
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: this.colors.secondary,
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 6,
                        pointHoverRadius: 8,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    x: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Período (últimos 12 meses)',
                            font: { weight: 'bold' }
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Quantidade de DIs',
                            font: { weight: 'bold' }
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Valor CIF (R$ milhões)',
                            font: { weight: 'bold' }
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Evolução de Importações - Últimos 12 Meses',
                        font: { size: 16, weight: 'bold' },
                        color: this.colors.dark
                    },
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 20
                        }
                    },
                    tooltip: {
                        callbacks: {
                            afterLabel: function(context) {
                                if (context.datasetIndex === 1) {
                                    return `Crescimento: ${data.growth[context.dataIndex]}%`;
                                }
                                return '';
                            }
                        }
                    }
                },
                animation: {
                    onComplete: () => {
                        this.hideChartSkeleton(ctx.parentElement);
                    }
                },
                onClick: (event, elements) => {
                    if (elements.length > 0) {
                        const index = elements[0].index;
                        this.showTemporalDrillDown(data.months[index], data);
                    }
                }
            }
        });

        this.charts.set('temporalChart', chart);
        this.addChartControls(ctx.parentElement, 'temporal');
    }

    /**
     * 2. Gráfico de barras - Impostos por tipo
     */
    createTaxesChart(data) {
        const ctx = document.getElementById('taxesChart');
        if (!ctx) return;

        this.showChartSkeleton(ctx.parentElement);

        const chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['II', 'IPI', 'PIS', 'COFINS', 'ICMS'],
                datasets: [{
                    label: 'Impostos Arrecadados (R$ milhões)',
                    data: [
                        data.ii_total,
                        data.ipi_total,
                        data.pis_total,
                        data.cofins_total,
                        data.icms_total
                    ],
                    backgroundColor: [
                        this.colors.primary,
                        this.colors.secondary,
                        this.colors.success,
                        this.colors.warning,
                        this.colors.info
                    ],
                    borderColor: [
                        this.colors.primary,
                        this.colors.secondary,
                        this.colors.success,
                        this.colors.warning,
                        this.colors.info
                    ],
                    borderWidth: 2,
                    borderRadius: 8,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Tipos de Impostos',
                            font: { weight: 'bold' }
                        }
                    },
                    y: {
                        title: {
                            display: true,
                            text: 'Valor Arrecadado (R$ milhões)',
                            font: { weight: 'bold' }
                        },
                        beginAtZero: true
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Impostos Arrecadados por Tipo',
                        font: { size: 16, weight: 'bold' },
                        color: this.colors.dark
                    },
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            afterLabel: function(context) {
                                const percentage = data.percentages[context.dataIndex];
                                return `${percentage}% do total`;
                            }
                        }
                    }
                },
                animation: {
                    onComplete: () => {
                        this.hideChartSkeleton(ctx.parentElement);
                    }
                },
                onClick: (event, elements) => {
                    if (elements.length > 0) {
                        const index = elements[0].index;
                        const taxType = ['ii', 'ipi', 'pis', 'cofins', 'icms'][index];
                        this.showTaxDrillDown(taxType, data);
                    }
                }
            }
        });

        this.charts.set('taxesChart', chart);
        this.addChartControls(ctx.parentElement, 'taxes');
    }

    /**
     * 3. Gráfico de pizza - Despesas portuárias
     */
    createExpensesChart(data) {
        const ctx = document.getElementById('expensesChart');
        if (!ctx) return;

        this.showChartSkeleton(ctx.parentElement);

        const chart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: data.categories,
                datasets: [{
                    data: data.values,
                    backgroundColor: this.pieColors.slice(0, data.categories.length),
                    borderColor: '#ffffff',
                    borderWidth: 3,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Distribuição de Despesas Portuárias',
                        font: { size: 16, weight: 'bold' },
                        color: this.colors.dark
                    },
                    legend: {
                        position: 'right',
                        labels: {
                            usePointStyle: true,
                            padding: 15,
                            generateLabels: function(chart) {
                                const data = chart.data;
                                const labels = Chart.defaults.plugins.legend.labels.generateLabels(chart);
                                
                                labels.forEach((label, index) => {
                                    const value = data.datasets[0].data[index];
                                    const total = data.datasets[0].data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    label.text += ` (${percentage}%)`;
                                });
                                
                                return labels;
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const value = context.parsed;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return `${context.label}: R$ ${value.toLocaleString('pt-BR')} (${percentage}%)`;
                            }
                        }
                    }
                },
                animation: {
                    animateRotate: true,
                    animateScale: true,
                    onComplete: () => {
                        this.hideChartSkeleton(ctx.parentElement);
                    }
                },
                onClick: (event, elements) => {
                    if (elements.length > 0) {
                        const index = elements[0].index;
                        const category = data.categories[index];
                        this.showExpenseDrillDown(category, data);
                    }
                }
            }
        });

        this.charts.set('expensesChart', chart);
        this.addChartControls(ctx.parentElement, 'expenses');
    }

    /**
     * 4. Gráfico donut - Moedas
     */
    createCurrenciesChart(data) {
        const ctx = document.getElementById('currenciesChart');
        if (!ctx) return;

        this.showChartSkeleton(ctx.parentElement);

        const chart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.currencies,
                datasets: [{
                    data: data.values,
                    backgroundColor: this.dataColors.slice(0, data.currencies.length),
                    borderColor: '#ffffff',
                    borderWidth: 3,
                    hoverOffset: 15
                }]
            },
            options: {
                responsive: true,
                cutout: '60%',
                plugins: {
                    title: {
                        display: true,
                        text: 'Segmentação por Moedas',
                        font: { size: 16, weight: 'bold' },
                        color: this.colors.dark
                    },
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 20
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const value = context.parsed;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return `${context.label}: ${value.toLocaleString('pt-BR')} DIs (${percentage}%)`;
                            }
                        }
                    }
                },
                animation: {
                    animateRotate: true,
                    animateScale: true,
                    onComplete: () => {
                        this.hideChartSkeleton(ctx.parentElement);
                    }
                }
            }
        });

        // Adicionar informação central no donut
        this.addDonutCenterText(ctx, data.total_dis, 'Total de DIs');
        
        this.charts.set('currenciesChart', chart);
        this.addChartControls(ctx.parentElement, 'currencies');
    }

    /**
     * 5. Heatmap - Performance por estado
     */
    createStatesHeatmap(data) {
        // Para um heatmap real, usaríamos Chart.js com plugin matrix
        // Por simplicidade, criar um grid visual customizado
        const container = document.getElementById('statesHeatmap');
        if (!container) return;

        this.showChartSkeleton(container);

        const heatmapHTML = `
            <div class="heatmap-container">
                <div class="heatmap-title">Performance por Estado - Benefícios Fiscais</div>
                <div class="heatmap-grid">
                    ${data.states.map(state => `
                        <div class="heatmap-cell" 
                             data-state="${state.uf}" 
                             data-value="${state.performance}"
                             style="background-color: ${this.getHeatmapColor(state.performance)}">
                            <div class="state-code">${state.uf}</div>
                            <div class="state-value">${state.performance}%</div>
                            <div class="state-dis">${state.dis_count} DIs</div>
                        </div>
                    `).join('')}
                </div>
                <div class="heatmap-legend">
                    <div class="legend-title">Performance de Benefícios</div>
                    <div class="legend-scale">
                        <div class="legend-item">
                            <div class="legend-color" style="background: #ff4757"></div>
                            <span>0-25%</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: #ffa502"></div>
                            <span>25-50%</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: #ffdd59"></div>
                            <span>50-75%</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: #5f27cd"></div>
                            <span>75-100%</span>
                        </div>
                    </div>
                </div>
            </div>
        `;

        container.innerHTML = heatmapHTML;
        
        // Adicionar interatividade
        container.querySelectorAll('.heatmap-cell').forEach(cell => {
            cell.addEventListener('click', () => {
                const state = cell.dataset.state;
                this.showStateDrillDown(state, data);
            });
        });

        setTimeout(() => {
            this.hideChartSkeleton(container);
        }, 1000);

        this.addChartControls(container, 'states');
    }

    /**
     * 6. Scatter plot - Correlação câmbio vs custo
     */
    createCorrelationChart(data) {
        const ctx = document.getElementById('correlationChart');
        if (!ctx) return;

        this.showChartSkeleton(ctx.parentElement);

        const chart = new Chart(ctx, {
            type: 'scatter',
            data: {
                datasets: [{
                    label: 'DIs Importadas',
                    data: data.scatter_points,
                    backgroundColor: this.colors.primary,
                    borderColor: this.colors.primary,
                    pointRadius: 6,
                    pointHoverRadius: 8,
                    pointBorderWidth: 2,
                    pointBorderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    x: {
                        type: 'linear',
                        position: 'bottom',
                        title: {
                            display: true,
                            text: 'Taxa de Câmbio (R$/USD)',
                            font: { weight: 'bold' }
                        }
                    },
                    y: {
                        title: {
                            display: true,
                            text: 'Custo Landed (R$ milhões)',
                            font: { weight: 'bold' }
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Correlação: Taxa de Câmbio vs Custo Landed',
                        font: { size: 16, weight: 'bold' },
                        color: this.colors.dark
                    },
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const point = data.scatter_details[context.dataIndex];
                                return [
                                    `DI: ${point.di_number}`,
                                    `Câmbio: R$ ${context.parsed.x.toFixed(4)}`,
                                    `Custo: R$ ${context.parsed.y.toLocaleString('pt-BR')}`,
                                    `Data: ${point.date}`
                                ];
                            }
                        }
                    }
                },
                animation: {
                    onComplete: () => {
                        this.hideChartSkeleton(ctx.parentElement);
                    }
                }
            }
        });

        this.charts.set('correlationChart', chart);
        this.addChartControls(ctx.parentElement, 'correlation');
    }

    /**
     * Utility: Cor do heatmap baseada no valor
     */
    getHeatmapColor(value) {
        if (value >= 75) return '#5f27cd';
        if (value >= 50) return '#ffdd59';
        if (value >= 25) return '#ffa502';
        return '#ff4757';
    }

    /**
     * Utility: Texto central no donut
     */
    addDonutCenterText(ctx, value, label) {
        const chart = Chart.getChart(ctx);
        const plugin = {
            id: 'centerText',
            afterDraw: function(chart) {
                const ctx = chart.ctx;
                const centerX = (chart.chartArea.left + chart.chartArea.right) / 2;
                const centerY = (chart.chartArea.top + chart.chartArea.bottom) / 2;
                
                ctx.save();
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                
                ctx.font = 'bold 24px var(--expertzy-font-family)';
                ctx.fillStyle = '#FF002D';
                ctx.fillText(value.toLocaleString('pt-BR'), centerX, centerY - 10);
                
                ctx.font = '14px var(--expertzy-font-family)';
                ctx.fillStyle = '#6c757d';
                ctx.fillText(label, centerX, centerY + 15);
                
                ctx.restore();
            }
        };
        
        chart.options.plugins = chart.options.plugins || {};
        chart.options.plugins.centerText = plugin;
    }

    /**
     * Skeletons de carregamento
     */
    showChartSkeleton(container) {
        const skeleton = container.querySelector('.chart-skeleton');
        if (skeleton) {
            skeleton.style.display = 'block';
        }
    }

    hideChartSkeleton(container) {
        const skeleton = container.querySelector('.chart-skeleton');
        if (skeleton) {
            skeleton.style.display = 'none';
        }
    }

    /**
     * Estados vazios com indicação de controle manual
     */
    showEmptyStates() {
        document.querySelectorAll('[data-chart]').forEach(container => {
            const emptyState = container.querySelector('.chart-empty');
            if (emptyState) {
                emptyState.style.display = 'flex';
            }
            
            // Marcar container como aguardando carregamento manual
            container.setAttribute('data-state', 'awaiting-manual');
            
            // Esconder skeleton de loading
            const skeleton = container.querySelector('.chart-skeleton');
            if (skeleton) {
                skeleton.style.display = 'none';
            }
        });
    }

    /**
     * Controles dos gráficos (fullscreen, export, etc)
     */
    addChartControls(container, chartType) {
        const controlsHTML = `
            <div class="chart-controls">
                <button class="chart-control-btn" data-action="fullscreen" title="Tela cheia">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                        <path d="M8 3H5C3.89543 3 3 3.89543 3 5V8M21 8V5C21 3.89543 20.1046 3 19 3H16M16 21H19C20.1046 21 21 20.1046 21 19V16M8 21H5C3.89543 21 3 20.1046 3 19V16" stroke="currentColor" stroke-width="2"/>
                    </svg>
                </button>
                <button class="chart-control-btn" data-action="export-png" title="Exportar PNG">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                        <path d="M21 15V19C21 20.1046 20.1046 21 19 21H5C3.89543 21 3 20.1046 3 19V15M7 10L12 15M12 15L17 10M12 15V3" stroke="currentColor" stroke-width="2"/>
                    </svg>
                </button>
                <button class="chart-control-btn" data-action="refresh" title="Atualizar">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                        <path d="M4 4V9H4.58152M4.58152 9C5.24618 7.35652 6.43937 5.97687 8.01844 5.05653C9.59752 4.13618 11.4737 3.73552 13.3644 3.9062C15.2552 4.07687 17.0321 4.80945 18.4133 6.00005C19.7944 7.19065 20.6988 8.78249 20.9982 10.5" stroke="currentColor" stroke-width="2"/>
                        <path d="M20 20V15H19.4185M19.4185 15C18.7538 16.6435 17.5606 18.0231 15.9816 18.9435C14.4025 19.8638 12.5263 20.2645 10.6356 20.0938C8.74482 19.9231 6.96787 19.1905 5.58668 18C4.20549 16.8094 3.30116 15.2175 3.00183 13.5" stroke="currentColor" stroke-width="2"/>
                    </svg>
                </button>
            </div>
        `;

        container.insertAdjacentHTML('afterbegin', controlsHTML);

        // Event listeners para controles
        container.querySelector('.chart-controls').addEventListener('click', (e) => {
            const action = e.target.closest('.chart-control-btn')?.dataset.action;
            if (action) {
                this.handleChartAction(action, chartType, container);
            }
        });
    }

    /**
     * Handler para ações dos gráficos
     */
    handleChartAction(action, chartType, container) {
        switch (action) {
            case 'fullscreen':
                this.toggleFullscreen(container);
                break;
            case 'export-png':
                this.exportChart(chartType, 'png');
                break;
            case 'refresh':
                this.refreshChart(chartType);
                break;
        }
    }

    /**
     * Fullscreen toggle
     */
    toggleFullscreen(container) {
        if (container.requestFullscreen) {
            if (document.fullscreenElement) {
                document.exitFullscreen();
            } else {
                container.requestFullscreen();
            }
        }
    }

    /**
     * Export de gráfico
     */
    exportChart(chartType, format) {
        const chart = this.charts.get(`${chartType}Chart`);
        if (!chart) return;

        const url = chart.toBase64Image('image/png', 1.0);
        const link = document.createElement('a');
        link.download = `${chartType}-chart-${new Date().toISOString().split('T')[0]}.${format}`;
        link.href = url;
        link.click();
    }

    /**
     * Atualização de gráfico específico
     */
    async refreshChart(chartType, manualTrigger = false) {
        if (!manualTrigger) {
            console.log(`🚫 Atualização automática de ${chartType} BLOQUEADA - Use controle manual`);
            return;
        }
        
        try {
            console.log(`🔄 Atualizando gráfico ${chartType} manualmente...`);
            const response = await fetch(`/sistema/dashboard/api/dashboard/charts.php?type=${chartType}`);
            const data = await response.json();
            
            if (data.success) {
                const chart = this.charts.get(`${chartType}Chart`);
                if (chart) {
                    chart.data = data.chartData.data;
                    chart.update('active');
                }
            }
        } catch (error) {
            console.error(`Erro ao atualizar gráfico ${chartType}:`, error);
        }
    }

    /**
     * Drill-down handlers
     */
    showTemporalDrillDown(month, data) {
        // Implementar modal ou painel lateral com detalhes do mês
        console.log('Drill-down temporal:', month, data);
    }

    showTaxDrillDown(taxType, data) {
        // Implementar drill-down para tipo específico de imposto
        console.log('Drill-down impostos:', taxType, data);
    }

    showExpenseDrillDown(category, data) {
        // Implementar drill-down para categoria de despesa
        console.log('Drill-down despesas:', category, data);
    }

    showStateDrillDown(state, data) {
        // Implementar drill-down para estado específico
        console.log('Drill-down estado:', state, data);
    }


    /**
     * Destruição/limpeza
     */
    destroy() {
        this.charts.forEach(chart => {
            chart.destroy();
        });
        this.charts.clear();
        
        
        this.isInitialized = false;
    }
}

// IIFE to prevent global scope pollution and redeclaration errors
(function() {
    'use strict';
    
    // Prevent multiple initializations
    if (window.expertzyCharts) {
        return;
    }
    
    // Inicialização em modo manual quando DOM carregado
    document.addEventListener('DOMContentLoaded', function() {
        // Aguardar Chart.js carregar
        if (typeof Chart !== 'undefined') {
            window.expertzyCharts = new ExpertzyChartsSystem();
            
            // MODO MANUAL: Expor função global para carregamento manual
            window.loadChartsManually = function() {
                console.log('🎯 Carregamento manual de gráficos iniciado...');
                return window.expertzyCharts.manualLoadCharts();
            };
            
            // Informar modo manual ativo
            console.log('📊 Sistema de Gráficos: MODO MANUAL ATIVO');
            console.log('🔧 Use window.loadChartsManually() ou botão "Atualizar Todos"');
            
        } else {
            console.error('Chart.js não foi carregado. Verifique se a biblioteca está incluída.');
        }
    });
    
    // Export para uso externo
    window.ExpertzyChartsSystem = ExpertzyChartsSystem;
    
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = ExpertzyChartsSystem;
    }
})();