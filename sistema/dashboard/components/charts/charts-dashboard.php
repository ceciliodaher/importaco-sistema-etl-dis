<?php
/**
 * ================================================================================
 * COMPONENTE DASHBOARD DE GRÁFICOS - PADRÃO EXPERTZY
 * Sistema ETL DI's - Template principal dos gráficos interativos
 * ================================================================================
 */
?>

<!-- Filtros e Controles Globais -->
<section class="charts-filters">
    <div class="filter-group">
        <label class="filter-label">Período</label>
        <div class="filter-toggle" id="periodFilter">
            <button class="toggle-option active" data-period="3m">3 Meses</button>
            <button class="toggle-option" data-period="6m">6 Meses</button>
            <button class="toggle-option" data-period="12m">12 Meses</button>
        </div>
    </div>
    
    <div class="filter-group">
        <label class="filter-label">Moeda</label>
        <select class="filter-select" id="currencyFilter">
            <option value="all">Todas as Moedas</option>
            <option value="USD">USD - Dólar Americano</option>
            <option value="EUR">EUR - Euro</option>
            <option value="BRL">BRL - Real Brasileiro</option>
            <option value="GBP">GBP - Libra Esterlina</option>
        </select>
    </div>
    
    <div class="filter-group">
        <label class="filter-label">Estado</label>
        <select class="filter-select" id="stateFilter">
            <option value="all">Todos os Estados</option>
            <option value="SP">SP - São Paulo</option>
            <option value="RJ">RJ - Rio de Janeiro</option>
            <option value="RS">RS - Rio Grande do Sul</option>
            <option value="PR">PR - Paraná</option>
            <option value="SC">SC - Santa Catarina</option>
        </select>
    </div>
    
    <div class="filter-group">
        <label class="filter-label">Regime Tributário</label>
        <div class="filter-toggle" id="taxRegimeFilter">
            <button class="toggle-option active" data-regime="all">Todos</button>
            <button class="toggle-option" data-regime="real">Real</button>
            <button class="toggle-option" data-regime="presumido">Presumido</button>
            <button class="toggle-option" data-regime="simples">Simples</button>
        </div>
    </div>
    
    <div class="filter-group">
        <button class="btn btn-primary" id="refreshAllCharts">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                <path d="M4 4V9H4.58152M4.58152 9C5.24618 7.35652 6.43937 5.97687 8.01844 5.05653C9.59752 4.13618 11.4737 3.73552 13.3644 3.9062C15.2552 4.07687 17.0321 4.80945 18.4133 6.00005C19.7944 7.19065 20.6988 8.78249 20.9982 10.5" stroke="currentColor" stroke-width="2"/>
                <path d="M20 20V15H19.4185M19.4185 15C18.7538 16.6435 17.5606 18.0231 15.9816 18.9435C14.4025 19.8638 12.5263 20.2645 10.6356 20.0938C8.74482 19.9231 6.96787 19.1905 5.58668 18C4.20549 16.8094 3.30116 15.2175 3.00183 13.5" stroke="currentColor" stroke-width="2"/>
            </svg>
            Atualizar Todos
        </button>
    </div>
</section>

<!-- Dashboard Principal de Gráficos -->
<div class="charts-dashboard">
    <!-- 1. Gráfico Temporal - Evolução das Importações -->
    <div class="chart-container" data-chart="temporal">
        <div class="chart-skeleton">
            <div class="skeleton-animation">
                <div class="skeleton-bars">
                    <div class="skeleton-bar skeleton-animation"></div>
                    <div class="skeleton-bar skeleton-animation"></div>
                    <div class="skeleton-bar skeleton-animation"></div>
                    <div class="skeleton-bar skeleton-animation"></div>
                    <div class="skeleton-bar skeleton-animation"></div>
                </div>
                <div class="skeleton-text skeleton-animation"></div>
                <div class="skeleton-text skeleton-animation" style="width: 60%;"></div>
            </div>
        </div>
        
        <div class="chart-empty">
            <div class="chart-empty-icon">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none">
                    <path d="M3 3V21H21" stroke="currentColor" stroke-width="2"/>
                    <path d="M7 16L12 12L16 16L21 10" stroke="currentColor" stroke-width="2"/>
                </svg>
            </div>
            <div class="chart-empty-title">Sem Dados Temporais</div>
            <div class="chart-empty-subtitle">Não há dados suficientes para exibir a evolução temporal. Importe algumas DIs para ver o gráfico.</div>
        </div>
        
        <div class="chart-canvas-container">
            <canvas id="temporalChart"></canvas>
        </div>
    </div>

    <!-- 2. Gráfico de Barras - Impostos por Tipo -->
    <div class="chart-container" data-chart="taxes">
        <div class="chart-skeleton">
            <div class="skeleton-animation">
                <div class="skeleton-bars">
                    <div class="skeleton-bar skeleton-animation" style="height: 80%;"></div>
                    <div class="skeleton-bar skeleton-animation" style="height: 60%;"></div>
                    <div class="skeleton-bar skeleton-animation" style="height: 100%;"></div>
                    <div class="skeleton-bar skeleton-animation" style="height: 45%;"></div>
                    <div class="skeleton-bar skeleton-animation" style="height: 90%;"></div>
                </div>
                <div class="skeleton-text skeleton-animation"></div>
                <div class="skeleton-text skeleton-animation" style="width: 70%;"></div>
            </div>
        </div>
        
        <div class="chart-empty">
            <div class="chart-empty-icon">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2" stroke="currentColor" stroke-width="2"/>
                    <rect x="7" y="7" width="3" height="10" fill="currentColor"/>
                    <rect x="14" y="11" width="3" height="6" fill="currentColor"/>
                </svg>
            </div>
            <div class="chart-empty-title">Sem Dados de Impostos</div>
            <div class="chart-empty-subtitle">Os cálculos de impostos aparecerão aqui após o processamento das DIs.</div>
        </div>
        
        <div class="chart-canvas-container">
            <canvas id="taxesChart"></canvas>
        </div>
    </div>

    <!-- 3. Gráfico de Pizza - Despesas Portuárias -->
    <div class="chart-container" data-chart="expenses">
        <div class="chart-skeleton">
            <div class="skeleton-animation">
                <div class="skeleton-circle skeleton-animation"></div>
                <div class="skeleton-text skeleton-animation"></div>
                <div class="skeleton-text skeleton-animation" style="width: 80%;"></div>
            </div>
        </div>
        
        <div class="chart-empty">
            <div class="chart-empty-icon">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                    <path d="M12 2L12 12L19 12" stroke="currentColor" stroke-width="2"/>
                </svg>
            </div>
            <div class="chart-empty-title">Sem Despesas Registradas</div>
            <div class="chart-empty-subtitle">As despesas portuárias e extras serão exibidas aqui conforme os dados forem processados.</div>
        </div>
        
        <div class="chart-canvas-container">
            <canvas id="expensesChart"></canvas>
        </div>
    </div>

    <!-- 4. Gráfico Donut - Segmentação por Moedas -->
    <div class="chart-container" data-chart="currencies">
        <div class="chart-skeleton">
            <div class="skeleton-animation">
                <div class="skeleton-circle skeleton-animation"></div>
                <div class="skeleton-text skeleton-animation"></div>
                <div class="skeleton-text skeleton-animation" style="width: 65%;"></div>
            </div>
        </div>
        
        <div class="chart-empty">
            <div class="chart-empty-icon">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                    <circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="2"/>
                </svg>
            </div>
            <div class="chart-empty-title">Aguardando Dados de Moedas</div>
            <div class="chart-empty-subtitle">A distribuição por moedas será exibida após importar DIs com diferentes moedas.</div>
        </div>
        
        <div class="chart-canvas-container">
            <canvas id="currenciesChart"></canvas>
        </div>
    </div>

    <!-- 5. Heatmap - Performance por Estado -->
    <div class="chart-container chart-container-large" data-chart="states">
        <div class="chart-skeleton">
            <div class="skeleton-animation">
                <div style="display: grid; grid-template-columns: repeat(6, 1fr); gap: 8px;">
                    <?php for($i = 0; $i < 18; $i++): ?>
                    <div style="width: 50px; height: 40px; background: rgba(255, 0, 45, 0.1); border-radius: 4px;" class="skeleton-animation"></div>
                    <?php endfor; ?>
                </div>
                <div class="skeleton-text skeleton-animation" style="margin-top: 1rem;"></div>
            </div>
        </div>
        
        <div class="chart-empty">
            <div class="chart-empty-icon">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none">
                    <path d="M21 10C21 17 12 23 12 23C12 23 3 17 3 10C3 5.02944 7.02944 1 12 1C16.9706 1 21 5.02944 21 10Z" stroke="currentColor" stroke-width="2"/>
                    <circle cx="12" cy="10" r="3" stroke="currentColor" stroke-width="2"/>
                </svg>
            </div>
            <div class="chart-empty-title">Mapa de Estados Vazio</div>
            <div class="chart-empty-subtitle">A performance por estado será calculada após processar DIs de diferentes locais de desembaraço.</div>
        </div>
        
        <div class="chart-canvas-container large">
            <div id="statesHeatmap"></div>
        </div>
    </div>

    <!-- 6. Scatter Plot - Correlação Câmbio vs Custo -->
    <div class="chart-container" data-chart="correlation">
        <div class="chart-skeleton">
            <div class="skeleton-animation">
                <div style="display: flex; justify-content: space-around; align-items: end; height: 120px;">
                    <?php for($i = 0; $i < 25; $i++): ?>
                    <div style="width: 4px; height: <?= rand(20, 100) ?>%; background: rgba(255, 0, 45, 0.2); border-radius: 2px;" class="skeleton-animation"></div>
                    <?php endfor; ?>
                </div>
                <div class="skeleton-text skeleton-animation"></div>
                <div class="skeleton-text skeleton-animation" style="width: 75%;"></div>
            </div>
        </div>
        
        <div class="chart-empty">
            <div class="chart-empty-icon">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none">
                    <circle cx="6" cy="18" r="2" stroke="currentColor" stroke-width="2"/>
                    <circle cx="12" cy="12" r="2" stroke="currentColor" stroke-width="2"/>
                    <circle cx="18" cy="6" r="2" stroke="currentColor" stroke-width="2"/>
                    <path d="M6 18L12 12L18 6" stroke="currentColor" stroke-width="2"/>
                </svg>
            </div>
            <div class="chart-empty-title">Correlação Não Disponível</div>
            <div class="chart-empty-subtitle">A análise de correlação entre câmbio e custos será exibida com mais dados históricos.</div>
        </div>
        
        <div class="chart-canvas-container">
            <canvas id="correlationChart"></canvas>
        </div>
    </div>
</div>

<!-- Cards Estatísticos Avançados -->
<div class="stats-cards-grid">
    <!-- Card 1: Total DIs -->
    <div class="stat-card" data-stat="total-dis">
        <div class="stat-card-header">
            <div class="stat-card-icon success">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <path d="M9 12L11 14L15 10M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2"/>
                </svg>
            </div>
            <button class="stat-card-menu">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="1" stroke="currentColor" stroke-width="2"/>
                    <circle cx="12" cy="5" r="1" stroke="currentColor" stroke-width="2"/>
                    <circle cx="12" cy="19" r="1" stroke="currentColor" stroke-width="2"/>
                </svg>
            </button>
        </div>
        <div class="stat-card-title">DIs Processadas</div>
        <div class="stat-card-value" id="stat-dis-value">---</div>
        <div class="stat-card-change positive" id="stat-dis-change">
            <svg class="change-arrow" viewBox="0 0 24 24" fill="none">
                <path d="M12 19V5M5 12L12 5L19 12" stroke="currentColor" stroke-width="2"/>
            </svg>
            <span>+12.5% este mês</span>
        </div>
        <div class="stat-mini-chart">
            <canvas id="miniChartDis" width="100" height="40"></canvas>
        </div>
        <div class="stat-card-footer">
            <span class="stat-card-period">Últimos 12 meses</span>
            <a href="#" class="stat-card-more">Ver detalhes</a>
        </div>
    </div>

    <!-- Card 2: Volume CIF -->
    <div class="stat-card" data-stat="volume-cif">
        <div class="stat-card-header">
            <div class="stat-card-icon info">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <path d="M12 6V4M12 6C10.8954 6 10 6.89543 10 8C10 9.10457 10.8954 10 12 10M12 6C13.1046 6 14 6.89543 14 8C14 9.10457 13.1046 10 12 10M12 10V20M8 21H16M16 4H8" stroke="currentColor" stroke-width="2"/>
                </svg>
            </div>
            <button class="stat-card-menu">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="1" stroke="currentColor" stroke-width="2"/>
                    <circle cx="12" cy="5" r="1" stroke="currentColor" stroke-width="2"/>
                    <circle cx="12" cy="19" r="1" stroke="currentColor" stroke-width="2"/>
                </svg>
            </button>
        </div>
        <div class="stat-card-title">Volume CIF</div>
        <div class="stat-card-value" id="stat-cif-value">---</div>
        <div class="stat-card-change positive" id="stat-cif-change">
            <svg class="change-arrow" viewBox="0 0 24 24" fill="none">
                <path d="M12 19V5M5 12L12 5L19 12" stroke="currentColor" stroke-width="2"/>
            </svg>
            <span>+8.9% este mês</span>
        </div>
        <div class="stat-mini-chart">
            <canvas id="miniChartCif" width="100" height="40"></canvas>
        </div>
        <div class="stat-card-footer">
            <span class="stat-card-period">BRL + USD convertido</span>
            <a href="#" class="stat-card-more">Ver detalhes</a>
        </div>
    </div>

    <!-- Card 3: Total Impostos -->
    <div class="stat-card" data-stat="total-impostos">
        <div class="stat-card-header">
            <div class="stat-card-icon warning">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <path d="M9 11H15M9 15H15M17 21H7C5.89543 21 5 20.1046 5 19V5C5 3.89543 5.89543 3 7 3H12.5858C12.851 3 13.1054 3.10536 13.2929 3.29289L19.7071 9.70711C19.8946 9.89464 20 10.149 20 10.4142V19C20 20.1046 19.1046 21 18 21H17Z" stroke="currentColor" stroke-width="2"/>
                </svg>
            </div>
            <button class="stat-card-menu">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="1" stroke="currentColor" stroke-width="2"/>
                    <circle cx="12" cy="5" r="1" stroke="currentColor" stroke-width="2"/>
                    <circle cx="12" cy="19" r="1" stroke="currentColor" stroke-width="2"/>
                </svg>
            </button>
        </div>
        <div class="stat-card-title">Total Impostos</div>
        <div class="stat-card-value" id="stat-impostos-value">---</div>
        <div class="stat-card-change neutral" id="stat-impostos-change">
            <span>Última atualização: hoje</span>
        </div>
        <div class="stat-mini-chart">
            <canvas id="miniChartImpostos" width="100" height="40"></canvas>
        </div>
        <div class="stat-card-footer">
            <span class="stat-card-period">II + IPI + PIS + COFINS + ICMS</span>
            <a href="#" class="stat-card-more">Ver breakdown</a>
        </div>
    </div>

    <!-- Card 4: Despesas Discriminadas -->
    <div class="stat-card" data-stat="despesas">
        <div class="stat-card-header">
            <div class="stat-card-icon danger">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <path d="M12 8V12M12 16H12.01M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2"/>
                </svg>
            </div>
            <button class="stat-card-menu">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="1" stroke="currentColor" stroke-width="2"/>
                    <circle cx="12" cy="5" r="1" stroke="currentColor" stroke-width="2"/>
                    <circle cx="12" cy="19" r="1" stroke="currentColor" stroke-width="2"/>
                </svg>
            </button>
        </div>
        <div class="stat-card-title">Despesas Extras</div>
        <div class="stat-card-value" id="stat-despesas-value">---</div>
        <div class="stat-card-change positive" id="stat-despesas-change">
            <svg class="change-arrow" viewBox="0 0 24 24" fill="none" transform="rotate(180)">
                <path d="M12 19V5M5 12L12 5L19 12" stroke="currentColor" stroke-width="2"/>
            </svg>
            <span>-2.3% este mês</span>
        </div>
        <div class="stat-mini-chart">
            <canvas id="miniChartDespesas" width="100" height="40"></canvas>
        </div>
        <div class="stat-card-footer">
            <span class="stat-card-period">16 categorias diferentes</span>
            <a href="#" class="stat-card-more">Ver categorias</a>
        </div>
    </div>

    <!-- Card 5: NCMs Catalogados -->
    <div class="stat-card" data-stat="ncms">
        <div class="stat-card-header">
            <div class="stat-card-icon success">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <path d="M9 12L11 14L15 10M21 10C21 17 12 23 12 23C12 23 3 17 3 10C3 5.02944 7.02944 1 12 1C16.9706 1 21 5.02944 21 10Z" stroke="currentColor" stroke-width="2"/>
                </svg>
            </div>
            <button class="stat-card-menu">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="1" stroke="currentColor" stroke-width="2"/>
                    <circle cx="12" cy="5" r="1" stroke="currentColor" stroke-width="2"/>
                    <circle cx="12" cy="19" r="1" stroke="currentColor" stroke-width="2"/>
                </svg>
            </button>
        </div>
        <div class="stat-card-title">NCMs Catalogados</div>
        <div class="stat-card-value" id="stat-ncms-value">---</div>
        <div class="stat-card-change positive" id="stat-ncms-change">
            <span>+5 novos NCMs</span>
        </div>
        <div class="stat-mini-chart">
            <canvas id="miniChartNcms" width="100" height="40"></canvas>
        </div>
        <div class="stat-card-footer">
            <span class="stat-card-period">Base MERCOSUL atualizada</span>
            <a href="#" class="stat-card-more">Ver ranking</a>
        </div>
    </div>

    <!-- Card 6: AFRMM Performance -->
    <div class="stat-card" data-stat="afrmm">
        <div class="stat-card-header">
            <div class="stat-card-icon warning">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <path d="M13 2L3 14H12L11 22L21 10H12L13 2Z" stroke="currentColor" stroke-width="2"/>
                </svg>
            </div>
            <button class="stat-card-menu">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="1" stroke="currentColor" stroke-width="2"/>
                    <circle cx="12" cy="5" r="1" stroke="currentColor" stroke-width="2"/>
                    <circle cx="12" cy="19" r="1" stroke="currentColor" stroke-width="2"/>
                </svg>
            </button>
        </div>
        <div class="stat-card-title">AFRMM Performance</div>
        <div class="stat-card-value" id="stat-afrmm-value">---</div>
        <div class="stat-card-change positive" id="stat-afrmm-change">
            <svg class="change-arrow" viewBox="0 0 24 24" fill="none">
                <path d="M12 19V5M5 12L12 5L19 12" stroke="currentColor" stroke-width="2"/>
            </svg>
            <span>+8.7% performance</span>
        </div>
        <div class="stat-mini-chart">
            <canvas id="miniChartAfrmm" width="100" height="40"></canvas>
        </div>
        <div class="stat-card-footer">
            <span class="stat-card-period">Marinha Mercante</span>
            <a href="#" class="stat-card-more">Ver divergências</a>
        </div>
    </div>
</div>

<!-- Modal para Drill-down (será implementado via JavaScript) -->
<div id="chartDrilldownModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="drilldownTitle">Detalhes</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body" id="drilldownContent">
            <!-- Conteúdo será inserido dinamicamente -->
        </div>
    </div>
</div>

<script>
// Inicialização dos filtros e eventos
document.addEventListener('DOMContentLoaded', function() {
    // Event listeners para filtros
    document.getElementById('periodFilter').addEventListener('click', handlePeriodFilter);
    document.getElementById('currencyFilter').addEventListener('change', handleCurrencyFilter);
    document.getElementById('stateFilter').addEventListener('change', handleStateFilter);
    document.getElementById('taxRegimeFilter').addEventListener('click', handleTaxRegimeFilter);
    document.getElementById('refreshAllCharts').addEventListener('click', refreshAllCharts);
    
    // Event listeners para cards estatísticos
    document.querySelectorAll('.stat-card-more').forEach(link => {
        link.addEventListener('click', handleStatCardDetails);
    });
    
    // Carregar dados iniciais dos cards
    loadStatsCards();
});

function handlePeriodFilter(e) {
    if (e.target.classList.contains('toggle-option')) {
        document.querySelectorAll('#periodFilter .toggle-option').forEach(btn => btn.classList.remove('active'));
        e.target.classList.add('active');
        
        const period = e.target.dataset.period;
        if (window.expertzyCharts) {
            window.expertzyCharts.updateAllChartsWithPeriod(period);
        }
    }
}

function handleCurrencyFilter(e) {
    const currency = e.target.value;
    if (window.expertzyCharts) {
        window.expertzyCharts.updateAllChartsWithCurrency(currency);
    }
}

function handleStateFilter(e) {
    const state = e.target.value;
    if (window.expertzyCharts) {
        window.expertzyCharts.updateAllChartsWithState(state);
    }
}

function handleTaxRegimeFilter(e) {
    if (e.target.classList.contains('toggle-option')) {
        document.querySelectorAll('#taxRegimeFilter .toggle-option').forEach(btn => btn.classList.remove('active'));
        e.target.classList.add('active');
        
        const regime = e.target.dataset.regime;
        if (window.expertzyCharts) {
            window.expertzyCharts.updateAllChartsWithTaxRegime(regime);
        }
    }
}

function refreshAllCharts() {
    if (window.expertzyCharts) {
        window.expertzyCharts.loadChartData();
        loadStatsCards();
    }
}

function handleStatCardDetails(e) {
    e.preventDefault();
    const card = e.target.closest('.stat-card');
    const statType = card.dataset.stat;
    
    // Implementar modal ou navegação para detalhes
    console.log('Mostrar detalhes para:', statType);
}

async function loadStatsCards() {
    try {
        const response = await fetch('/api/dashboard/charts?endpoint=stats');
        const data = await response.json();
        
        if (data.success) {
            updateStatsCards(data.data);
        }
    } catch (error) {
        console.error('Erro ao carregar cards estatísticos:', error);
    }
}

function updateStatsCards(stats) {
    // Atualizar cada card com os dados recebidos
    Object.keys(stats).forEach(key => {
        const stat = stats[key];
        const elements = {
            value: document.getElementById(`stat-${key.replace('_', '-')}-value`),
            change: document.getElementById(`stat-${key.replace('_', '-')}-change`)
        };
        
        if (elements.value) {
            elements.value.textContent = stat.value;
        }
        
        if (elements.change && stat.change !== undefined) {
            const arrow = elements.change.querySelector('.change-arrow');
            const text = elements.change.querySelector('span');
            
            if (text) {
                text.textContent = stat.change > 0 ? `+${stat.change}%` : `${stat.change}%`;
            }
            
            // Atualizar classe de tendência
            elements.change.className = `stat-card-change ${stat.trend}`;
            
            // Rotacionar seta se necessário
            if (arrow && stat.trend === 'negative') {
                arrow.style.transform = 'rotate(180deg)';
            }
        }
    });
}
</script>