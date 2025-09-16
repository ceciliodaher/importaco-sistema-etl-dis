/**
 * ================================================================================
 * DASHBOARD MANAGER - INTERFACE REATIVA PARA SISTEMA ETL DI's
 * Features: Auto-refresh, Filtros Dinâmicos, Keyboard Shortcuts, Drag Between Cards
 * Cores de Feedback: Vermelho (erro), Amarelo (processando), Verde (sucesso), Azul (info)
 * ================================================================================
 */

class DashboardManager {
    constructor() {
        // Core elements
        this.dashboardContainer = document.querySelector('.dashboard-container');
        this.statsCards = document.querySelectorAll('.stats-card');
        this.chartsContainer = document.querySelector('.charts-container');
        this.filtersContainer = document.querySelector('.filters-container');
        this.searchInput = document.querySelector('#globalSearch');
        this.refreshBtn = document.querySelector('#refreshDashboard');
        this.settingsBtn = document.querySelector('#dashboardSettings');
        
        // State management
        this.isRefreshing = false;
        this.autoRefreshInterval = null;
        this.autoRefreshEnabled = true;
        this.refreshIntervalTime = 30000; // 30 seconds
        this.lastDataUpdate = null;
        this.cachedData = new Map();
        this.activeFilters = new Map();
        this.searchTimeout = null;
        
        // User preferences (stored in localStorage)
        this.preferences = this.loadPreferences();
        
        // Keyboard shortcuts
        this.shortcuts = new Map([
            ['r', () => this.refreshDashboard()],
            ['f', () => this.focusSearch()],
            ['s', () => this.toggleSettings()],
            ['1', () => this.focusCard(0)],
            ['2', () => this.focusCard(1)],
            ['3', () => this.focusCard(2)],
            ['4', () => this.focusCard(3)],
            ['Escape', () => this.clearFilters()]
        ]);
        
        // Drag and drop state
        this.draggedCard = null;
        this.dropZones = [];
        
        this.init();
    }

    async init() {
        try {
            this.setupEventListeners();
            this.setupKeyboardShortcuts();
            this.setupDragAndDrop();
            this.initializeSearch();
            this.initializeFilters();
            this.applyUserPreferences();
            
            // Initial data load
            await this.refreshDashboard(false); // Silent initial load
            
            // Start auto-refresh
            this.startAutoRefresh();
            
            this.showNotification('Dashboard inicializado com sucesso', 'success', 2000);
        } catch (error) {
            this.showNotification('Erro ao inicializar dashboard: ' + error.message, 'error');
            console.error('Dashboard initialization error:', error);
        }
    }

    setupEventListeners() {
        // Refresh button
        if (this.refreshBtn) {
            this.refreshBtn.addEventListener('click', () => this.refreshDashboard());
        }
        
        // Settings button
        if (this.settingsBtn) {
            this.settingsBtn.addEventListener('click', () => this.toggleSettings());
        }
        
        // Search input
        if (this.searchInput) {
            this.searchInput.addEventListener('input', (e) => this.handleSearch(e.target.value));
            this.searchInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    this.executeSearch(e.target.value);
                }
            });
        }
        
        // Window focus/blur for auto-refresh management
        window.addEventListener('focus', () => {
            if (this.autoRefreshEnabled) {
                this.startAutoRefresh();
                this.refreshDashboard(true); // Immediate refresh on focus
            }
        });
        
        window.addEventListener('blur', () => {
            this.pauseAutoRefresh();
        });
        
        // Visibility API for better resource management
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible' && this.autoRefreshEnabled) {
                this.startAutoRefresh();
                this.refreshDashboard(true);
            } else {
                this.pauseAutoRefresh();
            }
        });
        
        // Card interactions
        this.statsCards.forEach((card, index) => {
            card.addEventListener('click', () => this.handleCardClick(card, index));
            card.addEventListener('dblclick', () => this.handleCardDoubleClick(card, index));
            
            // Context menu for cards
            card.addEventListener('contextmenu', (e) => {
                e.preventDefault();
                this.showCardContextMenu(e, card, index);
            });
        });
        
        // Close context menu on outside click
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.context-menu')) {
                this.hideContextMenu();
            }
        });
    }

    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Check if user is typing in an input
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
                // Allow Escape to exit inputs
                if (e.key === 'Escape') {
                    e.target.blur();
                    this.shortcuts.get('Escape')();
                }
                return;
            }
            
            // Handle Ctrl/Cmd combinations
            if (e.ctrlKey || e.metaKey) {
                switch (e.key) {
                    case 'r':
                        e.preventDefault();
                        this.refreshDashboard();
                        break;
                    case 'f':
                        e.preventDefault();
                        this.focusSearch();
                        break;
                }
                return;
            }
            
            // Handle single key shortcuts
            if (this.shortcuts.has(e.key)) {
                e.preventDefault();
                this.shortcuts.get(e.key)();
            }
        });
    }

    setupDragAndDrop() {
        this.statsCards.forEach((card, index) => {
            card.draggable = true;
            card.setAttribute('data-card-index', index);
            
            card.addEventListener('dragstart', (e) => {
                this.draggedCard = { element: card, index };
                card.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', index);
            });
            
            card.addEventListener('dragend', () => {
                card.classList.remove('dragging');
                this.clearDropZones();
                this.draggedCard = null;
            });
            
            card.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                card.classList.add('drop-target');
            });
            
            card.addEventListener('dragleave', () => {
                card.classList.remove('drop-target');
            });
            
            card.addEventListener('drop', (e) => {
                e.preventDefault();
                card.classList.remove('drop-target');
                
                if (this.draggedCard && this.draggedCard.element !== card) {
                    this.swapCards(this.draggedCard.index, index);
                }
            });
        });
    }

    initializeSearch() {
        // Setup autocomplete functionality
        if (this.searchInput) {
            this.searchInput.setAttribute('autocomplete', 'off');
            this.createSearchSuggestions();
        }
    }

    initializeFilters() {
        const filterButtons = document.querySelectorAll('.filter-btn');
        filterButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                this.toggleFilter(btn.dataset.filter, btn.dataset.value);
            });
        });
    }

    async refreshDashboard(showFeedback = true) {
        if (this.isRefreshing) return;
        
        this.isRefreshing = true;
        
        if (showFeedback) {
            this.showRefreshingState();
        }
        
        try {
            const startTime = Date.now();
            
            // Parallel data fetching
            const dataPromises = [
                this.fetchStatsData(),
                this.fetchChartsData(),
                this.fetchRecentActivity(),
                this.fetchSystemStatus()
            ];
            
            const [statsData, chartsData, activityData, statusData] = await Promise.all(dataPromises);
            
            // Update UI components
            await Promise.all([
                this.updateStatsCards(statsData),
                this.updateCharts(chartsData),
                this.updateRecentActivity(activityData),
                this.updateSystemStatus(statusData)
            ]);
            
            // Cache the data
            this.cachedData.set('stats', { data: statsData, timestamp: Date.now() });
            this.cachedData.set('charts', { data: chartsData, timestamp: Date.now() });
            this.cachedData.set('activity', { data: activityData, timestamp: Date.now() });
            this.cachedData.set('status', { data: statusData, timestamp: Date.now() });
            
            this.lastDataUpdate = Date.now();
            
            const loadTime = Date.now() - startTime;
            
            if (showFeedback) {
                this.showNotification(
                    `Dashboard atualizado em ${loadTime}ms`, 
                    'success', 
                    2000
                );
            }
            
            // Dispatch custom event for other components
            this.dispatchDataUpdateEvent(statsData);
            
        } catch (error) {
            this.showNotification('Erro ao atualizar dashboard: ' + error.message, 'error');
            console.error('Dashboard refresh error:', error);
        } finally {
            this.isRefreshing = false;
            this.hideRefreshingState();
        }
    }

    async fetchStatsData() {
        const response = await fetch('api/dashboard/stats.php', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Cache-Control': 'no-cache'
            }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        return await response.json();
    }

    async fetchChartsData() {
        const response = await fetch('api/dashboard/charts.php', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Cache-Control': 'no-cache'
            }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        return await response.json();
    }

    async fetchRecentActivity() {
        const response = await fetch('api/dashboard/activity.php', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        return await response.json();
    }

    async fetchSystemStatus() {
        const response = await fetch('api/dashboard/system-status.php', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        return await response.json();
    }

    async updateStatsCards(data) {
        if (!data.success || !data.stats) {
            throw new Error('Dados de estatísticas inválidos');
        }
        
        const stats = data.stats;
        const animations = [];
        
        this.statsCards.forEach((card, index) => {
            const cardType = card.dataset.cardType;
            if (stats[cardType]) {
                const animation = this.updateStatsCard(card, stats[cardType]);
                animations.push(animation);
            }
        });
        
        // Wait for all animations to complete
        await Promise.all(animations);
    }

    async updateStatsCard(cardElement, data) {
        return new Promise(resolve => {
            const valueElement = cardElement.querySelector('.card-value');
            const trendElement = cardElement.querySelector('.card-trend');
            const lastUpdatedElement = cardElement.querySelector('.last-updated');
            
            if (valueElement) {
                // Animate number change
                this.animateNumber(valueElement, data.current || 0, 1000).then(resolve);
            } else {
                resolve();
            }
            
            if (trendElement && data.trend) {
                trendElement.textContent = data.trend.text || '';
                trendElement.className = `card-trend ${data.trend.direction || 'neutral'}`;
            }
            
            if (lastUpdatedElement) {
                lastUpdatedElement.textContent = this.formatTimestamp(Date.now());
            }
            
            // Update card status based on data
            this.updateCardStatus(cardElement, data.status || 'normal');
        });
    }

    animateNumber(element, targetValue, duration = 1000) {
        return new Promise(resolve => {
            const startValue = parseFloat(element.textContent.replace(/[^\d.-]/g, '')) || 0;
            const difference = targetValue - startValue;
            const startTime = Date.now();
            
            const animate = () => {
                const elapsed = Date.now() - startTime;
                const progress = Math.min(elapsed / duration, 1);
                
                // Easing function (ease-out)
                const easeOut = 1 - Math.pow(1 - progress, 3);
                const currentValue = startValue + (difference * easeOut);
                
                element.textContent = this.formatNumber(currentValue);
                
                if (progress < 1) {
                    requestAnimationFrame(animate);
                } else {
                    element.textContent = this.formatNumber(targetValue);
                    resolve();
                }
            };
            
            requestAnimationFrame(animate);
        });
    }

    async updateCharts(data) {
        if (!data.success || !data.charts) return;
        
        const charts = data.charts;
        
        // Update each chart if chart library is available
        if (window.Chart) {
            Object.keys(charts).forEach(chartId => {
                this.updateChart(chartId, charts[chartId]);
            });
        }
    }

    updateChart(chartId, chartData) {
        const chartElement = document.getElementById(chartId);
        if (!chartElement) return;
        
        // Implementation depends on chart library (Chart.js, D3, etc.)
        // This is a placeholder for chart updates
        console.log(`Updating chart ${chartId}:`, chartData);
    }

    async updateRecentActivity(data) {
        if (!data.success || !data.activities) return;
        
        const activityContainer = document.querySelector('#recent-activity');
        if (!activityContainer) return;
        
        const activitiesHTML = data.activities.map(activity => `
            <div class="activity-item ${activity.type}">
                <div class="activity-icon">
                    ${this.getActivityIcon(activity.type)}
                </div>
                <div class="activity-content">
                    <div class="activity-title">${activity.title}</div>
                    <div class="activity-time">${this.formatTimestamp(activity.timestamp)}</div>
                </div>
                <div class="activity-status ${activity.status}">
                    ${activity.status.toUpperCase()}
                </div>
            </div>
        `).join('');
        
        activityContainer.innerHTML = activitiesHTML;
    }

    async updateSystemStatus(data) {
        if (!data.success || !data.status) return;
        
        const statusElement = document.querySelector('#system-status');
        if (!statusElement) return;
        
        statusElement.className = `system-status ${data.status.level}`;
        statusElement.textContent = data.status.message;
        
        // Update system health indicators
        this.updateHealthIndicators(data.status.health || {});
    }

    updateHealthIndicators(health) {
        const indicators = {
            'db-health': health.database || 'unknown',
            'api-health': health.api || 'unknown',
            'storage-health': health.storage || 'unknown'
        };
        
        Object.keys(indicators).forEach(id => {
            const indicator = document.getElementById(id);
            if (indicator) {
                indicator.className = `health-indicator ${indicators[id]}`;
            }
        });
    }

    handleSearch(query) {
        // Debounce search
        clearTimeout(this.searchTimeout);
        this.searchTimeout = setTimeout(() => {
            this.executeSearch(query);
        }, 300);
    }

    async executeSearch(query) {
        if (!query.trim()) {
            this.clearSearchResults();
            return;
        }
        
        try {
            const response = await fetch('api/dashboard/search.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ query: query.trim() })
            });
            
            if (!response.ok) {
                throw new Error('Erro na busca');
            }
            
            const results = await response.json();
            this.displaySearchResults(results);
            
        } catch (error) {
            this.showNotification('Erro na busca: ' + error.message, 'error');
        }
    }

    displaySearchResults(results) {
        // Implementation for displaying search results
        console.log('Search results:', results);
    }

    clearSearchResults() {
        const resultsContainer = document.querySelector('#search-results');
        if (resultsContainer) {
            resultsContainer.innerHTML = '';
        }
    }

    createSearchSuggestions() {
        // Create autocomplete suggestions dropdown
        const suggestionsList = document.createElement('div');
        suggestionsList.className = 'search-suggestions';
        suggestionsList.style.display = 'none';
        
        if (this.searchInput && this.searchInput.parentNode) {
            this.searchInput.parentNode.appendChild(suggestionsList);
        }
    }

    toggleFilter(filterType, filterValue) {
        if (this.activeFilters.has(filterType)) {
            this.activeFilters.delete(filterType);
        } else {
            this.activeFilters.set(filterType, filterValue);
        }
        
        this.applyFilters();
        this.updateFilterButtons();
    }

    applyFilters() {
        // Apply active filters to dashboard data
        if (this.activeFilters.size === 0) {
            this.showAllCards();
            return;
        }
        
        this.statsCards.forEach(card => {
            const shouldShow = this.cardMatchesFilters(card);
            card.style.display = shouldShow ? '' : 'none';
        });
    }

    cardMatchesFilters(card) {
        for (const [filterType, filterValue] of this.activeFilters) {
            if (!this.cardMatchesFilter(card, filterType, filterValue)) {
                return false;
            }
        }
        return true;
    }

    cardMatchesFilter(card, filterType, filterValue) {
        // Implementation depends on card structure and filter types
        return true; // Placeholder
    }

    updateFilterButtons() {
        const filterButtons = document.querySelectorAll('.filter-btn');
        filterButtons.forEach(btn => {
            const isActive = this.activeFilters.has(btn.dataset.filter);
            btn.classList.toggle('active', isActive);
        });
    }

    clearFilters() {
        this.activeFilters.clear();
        this.applyFilters();
        this.updateFilterButtons();
        this.showNotification('Filtros removidos', 'info', 1500);
    }

    showAllCards() {
        this.statsCards.forEach(card => {
            card.style.display = '';
        });
    }

    startAutoRefresh() {
        if (!this.autoRefreshEnabled) return;
        
        this.stopAutoRefresh();
        this.autoRefreshInterval = setInterval(() => {
            this.refreshDashboard(false); // Silent refresh
        }, this.refreshIntervalTime);
    }

    stopAutoRefresh() {
        if (this.autoRefreshInterval) {
            clearInterval(this.autoRefreshInterval);
            this.autoRefreshInterval = null;
        }
    }

    pauseAutoRefresh() {
        this.stopAutoRefresh();
    }

    toggleAutoRefresh() {
        this.autoRefreshEnabled = !this.autoRefreshEnabled;
        
        if (this.autoRefreshEnabled) {
            this.startAutoRefresh();
            this.showNotification('Auto-refresh ativado', 'success', 2000);
        } else {
            this.stopAutoRefresh();
            this.showNotification('Auto-refresh desativado', 'warning', 2000);
        }
        
        this.savePreferences();
    }

    handleCardClick(card, index) {
        // Handle card selection/activation
        this.setActiveCard(card, index);
    }

    handleCardDoubleClick(card, index) {
        // Handle card expansion/details view
        this.expandCard(card, index);
    }

    setActiveCard(card, index) {
        this.statsCards.forEach(c => c.classList.remove('active'));
        card.classList.add('active');
    }

    expandCard(card, index) {
        // Create expanded view modal or slide-out panel
        this.createCardModal(card, index);
    }

    createCardModal(card, index) {
        const modal = document.createElement('div');
        modal.className = 'card-modal-overlay';
        modal.innerHTML = `
            <div class="card-modal">
                <div class="card-modal-header">
                    <h3>${card.querySelector('.card-title')?.textContent || 'Detalhes'}</h3>
                    <button class="modal-close">&times;</button>
                </div>
                <div class="card-modal-content">
                    <p>Detalhes expandidos do card ${index + 1}</p>
                </div>
            </div>
        `;
        
        modal.querySelector('.modal-close').addEventListener('click', () => {
            document.body.removeChild(modal);
        });
        
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                document.body.removeChild(modal);
            }
        });
        
        document.body.appendChild(modal);
    }

    showCardContextMenu(event, card, index) {
        this.hideContextMenu();
        
        const contextMenu = document.createElement('div');
        contextMenu.className = 'context-menu';
        contextMenu.innerHTML = `
            <div class="context-menu-item" data-action="refresh">
                <span>🔄</span> Atualizar Card
            </div>
            <div class="context-menu-item" data-action="expand">
                <span>🔍</span> Ver Detalhes
            </div>
            <div class="context-menu-item" data-action="hide">
                <span>👁️</span> Ocultar Card
            </div>
            <div class="context-menu-separator"></div>
            <div class="context-menu-item" data-action="settings">
                <span>⚙️</span> Configurações
            </div>
        `;
        
        contextMenu.style.position = 'absolute';
        contextMenu.style.left = `${event.pageX}px`;
        contextMenu.style.top = `${event.pageY}px`;
        contextMenu.style.zIndex = '9999';
        
        contextMenu.addEventListener('click', (e) => {
            const action = e.target.closest('.context-menu-item')?.dataset.action;
            if (action) {
                this.handleContextMenuAction(action, card, index);
                this.hideContextMenu();
            }
        });
        
        document.body.appendChild(contextMenu);
    }

    hideContextMenu() {
        const existing = document.querySelector('.context-menu');
        if (existing) {
            document.body.removeChild(existing);
        }
    }

    handleContextMenuAction(action, card, index) {
        switch (action) {
            case 'refresh':
                this.refreshCard(card, index);
                break;
            case 'expand':
                this.expandCard(card, index);
                break;
            case 'hide':
                this.hideCard(card, index);
                break;
            case 'settings':
                this.showCardSettings(card, index);
                break;
        }
    }

    async refreshCard(card, index) {
        // Refresh specific card data
        card.classList.add('refreshing');
        
        try {
            const cardType = card.dataset.cardType;
            const response = await fetch(`api/dashboard/card-data.php?type=${cardType}`);
            const data = await response.json();
            
            if (data.success) {
                await this.updateStatsCard(card, data.data);
                this.showNotification('Card atualizado', 'success', 1500);
            } else {
                throw new Error(data.error || 'Erro ao atualizar card');
            }
        } catch (error) {
            this.showNotification('Erro ao atualizar card: ' + error.message, 'error');
        } finally {
            card.classList.remove('refreshing');
        }
    }

    hideCard(card, index) {
        card.style.display = 'none';
        this.showNotification('Card ocultado', 'info', 1500);
    }

    showCardSettings(card, index) {
        // Show settings panel for specific card
        console.log('Card settings for:', card, index);
    }

    swapCards(fromIndex, toIndex) {
        const cards = Array.from(this.statsCards);
        const fromCard = cards[fromIndex];
        const toCard = cards[toIndex];
        
        if (fromCard && toCard && fromCard !== toCard) {
            // Swap DOM elements
            const fromParent = fromCard.parentNode;
            const fromNext = fromCard.nextSibling;
            
            toCard.parentNode.insertBefore(fromCard, toCard);
            fromParent.insertBefore(toCard, fromNext);
            
            this.showNotification('Cards reordenados', 'success', 1500);
            this.saveCardOrder();
        }
    }

    saveCardOrder() {
        const order = Array.from(this.statsCards).map(card => card.dataset.cardType);
        this.preferences.cardOrder = order;
        this.savePreferences();
    }

    clearDropZones() {
        this.statsCards.forEach(card => {
            card.classList.remove('drop-target');
        });
    }

    focusSearch() {
        if (this.searchInput) {
            this.searchInput.focus();
            this.searchInput.select();
        }
    }

    focusCard(index) {
        if (this.statsCards[index]) {
            this.setActiveCard(this.statsCards[index], index);
            this.statsCards[index].scrollIntoView({ 
                behavior: 'smooth', 
                block: 'center' 
            });
        }
    }

    toggleSettings() {
        const settingsPanel = document.querySelector('#dashboard-settings-panel');
        if (settingsPanel) {
            const isVisible = settingsPanel.style.display !== 'none';
            settingsPanel.style.display = isVisible ? 'none' : 'block';
        } else {
            this.createSettingsPanel();
        }
    }

    createSettingsPanel() {
        const panel = document.createElement('div');
        panel.id = 'dashboard-settings-panel';
        panel.className = 'settings-panel';
        panel.innerHTML = `
            <div class="settings-header">
                <h3>Configurações do Dashboard</h3>
                <button class="settings-close">&times;</button>
            </div>
            <div class="settings-content">
                <div class="setting-group">
                    <label>
                        <input type="checkbox" id="auto-refresh-toggle" ${this.autoRefreshEnabled ? 'checked' : ''}>
                        Auto-refresh ativado
                    </label>
                </div>
                <div class="setting-group">
                    <label>
                        Intervalo de refresh (segundos):
                        <input type="range" id="refresh-interval" min="10" max="300" value="${this.refreshIntervalTime / 1000}">
                        <span id="refresh-interval-value">${this.refreshIntervalTime / 1000}</span>
                    </label>
                </div>
                <div class="setting-group">
                    <button id="reset-layout">Restaurar Layout Padrão</button>
                </div>
                <div class="setting-group">
                    <button id="export-data">Exportar Dados</button>
                </div>
            </div>
        `;
        
        // Event listeners for settings
        panel.querySelector('.settings-close').addEventListener('click', () => {
            document.body.removeChild(panel);
        });
        
        panel.querySelector('#auto-refresh-toggle').addEventListener('change', (e) => {
            this.autoRefreshEnabled = e.target.checked;
            if (this.autoRefreshEnabled) {
                this.startAutoRefresh();
            } else {
                this.stopAutoRefresh();
            }
            this.savePreferences();
        });
        
        const intervalSlider = panel.querySelector('#refresh-interval');
        const intervalValue = panel.querySelector('#refresh-interval-value');
        intervalSlider.addEventListener('input', (e) => {
            const seconds = parseInt(e.target.value);
            intervalValue.textContent = seconds;
            this.refreshIntervalTime = seconds * 1000;
            if (this.autoRefreshEnabled) {
                this.startAutoRefresh(); // Restart with new interval
            }
            this.savePreferences();
        });
        
        panel.querySelector('#reset-layout').addEventListener('click', () => {
            this.resetLayout();
        });
        
        panel.querySelector('#export-data').addEventListener('click', () => {
            this.exportDashboardData();
        });
        
        document.body.appendChild(panel);
    }

    resetLayout() {
        // Reset card positions and preferences
        this.preferences = this.getDefaultPreferences();
        this.savePreferences();
        this.applyUserPreferences();
        this.showNotification('Layout restaurado', 'success', 2000);
    }

    async exportDashboardData() {
        try {
            const data = {
                stats: this.cachedData.get('stats')?.data,
                charts: this.cachedData.get('charts')?.data,
                activity: this.cachedData.get('activity')?.data,
                exportTime: new Date().toISOString()
            };
            
            const blob = new Blob([JSON.stringify(data, null, 2)], {
                type: 'application/json'
            });
            
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `dashboard-data-${Date.now()}.json`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            
            this.showNotification('Dados exportados', 'success', 2000);
        } catch (error) {
            this.showNotification('Erro ao exportar: ' + error.message, 'error');
        }
    }

    loadPreferences() {
        const defaultPrefs = this.getDefaultPreferences();
        const saved = localStorage.getItem('dashboard-preferences');
        
        if (saved) {
            try {
                return { ...defaultPrefs, ...JSON.parse(saved) };
            } catch (error) {
                console.warn('Erro ao carregar preferências:', error);
            }
        }
        
        return defaultPrefs;
    }

    savePreferences() {
        localStorage.setItem('dashboard-preferences', JSON.stringify(this.preferences));
    }

    getDefaultPreferences() {
        return {
            autoRefreshEnabled: true,
            refreshIntervalTime: 30000,
            cardOrder: [],
            hiddenCards: [],
            theme: 'light'
        };
    }

    applyUserPreferences() {
        this.autoRefreshEnabled = this.preferences.autoRefreshEnabled;
        this.refreshIntervalTime = this.preferences.refreshIntervalTime;
        
        // Apply card order if saved
        if (this.preferences.cardOrder && this.preferences.cardOrder.length > 0) {
            this.applyCardOrder(this.preferences.cardOrder);
        }
        
        // Hide cards as needed
        if (this.preferences.hiddenCards && this.preferences.hiddenCards.length > 0) {
            this.applyHiddenCards(this.preferences.hiddenCards);
        }
    }

    applyCardOrder(order) {
        // Implementation for applying saved card order
        console.log('Applying card order:', order);
    }

    applyHiddenCards(hiddenCards) {
        hiddenCards.forEach(cardType => {
            const card = document.querySelector(`[data-card-type="${cardType}"]`);
            if (card) {
                card.style.display = 'none';
            }
        });
    }

    showRefreshingState() {
        if (this.refreshBtn) {
            this.refreshBtn.classList.add('refreshing');
            this.refreshBtn.disabled = true;
        }
        
        this.statsCards.forEach(card => {
            card.classList.add('updating');
        });
    }

    hideRefreshingState() {
        if (this.refreshBtn) {
            this.refreshBtn.classList.remove('refreshing');
            this.refreshBtn.disabled = false;
        }
        
        this.statsCards.forEach(card => {
            card.classList.remove('updating');
        });
    }

    updateCardStatus(card, status) {
        card.classList.remove('normal', 'warning', 'error', 'success');
        card.classList.add(status);
    }

    dispatchDataUpdateEvent(data) {
        const event = new CustomEvent('dashboardDataUpdate', {
            detail: { data, timestamp: Date.now() }
        });
        document.dispatchEvent(event);
    }

    getActivityIcon(type) {
        const icons = {
            upload: '📤',
            process: '⚙️',
            error: '❌',
            success: '✅',
            warning: '⚠️',
            info: 'ℹ️'
        };
        return icons[type] || '📋';
    }

    formatNumber(num) {
        if (num >= 1000000) {
            return (num / 1000000).toFixed(1) + 'M';
        } else if (num >= 1000) {
            return (num / 1000).toFixed(1) + 'K';
        }
        return num.toLocaleString();
    }

    formatTimestamp(timestamp) {
        const date = new Date(timestamp);
        const now = new Date();
        const diff = now.getTime() - date.getTime();
        
        if (diff < 60000) { // Less than 1 minute
            return 'agora mesmo';
        } else if (diff < 3600000) { // Less than 1 hour
            const minutes = Math.floor(diff / 60000);
            return `${minutes} min atrás`;
        } else if (diff < 86400000) { // Less than 1 day
            const hours = Math.floor(diff / 3600000);
            return `${hours}h atrás`;
        } else {
            return date.toLocaleDateString('pt-BR');
        }
    }

    showNotification(message, type = 'info', duration = 5000) {
        // Use existing notification system from upload.js if available
        if (window.uploadManager && window.uploadManager.showFeedback) {
            window.uploadManager.showFeedback(message, type, duration);
        } else {
            // Fallback notification system
            this.createNotification(message, type, duration);
        }
    }

    createNotification(message, type, duration) {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.textContent = message;
        
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 20px;
            background: var(--${type}-color, #333);
            color: white;
            border-radius: 4px;
            z-index: 10000;
            animation: slideInFromRight 0.3s ease;
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'slideOutToRight 0.3s ease';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, duration);
    }

    // Cleanup method
    destroy() {
        this.stopAutoRefresh();
        
        // Remove event listeners
        document.removeEventListener('keydown', this.keydownHandler);
        window.removeEventListener('focus', this.focusHandler);
        window.removeEventListener('blur', this.blurHandler);
        document.removeEventListener('visibilitychange', this.visibilityHandler);
        
        // Clear timeouts
        if (this.searchTimeout) {
            clearTimeout(this.searchTimeout);
        }
    }
}

// CSS animations
const dashboardStyles = document.createElement('style');
dashboardStyles.textContent = `
    @keyframes slideInFromRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutToRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
    
    .stats-card.dragging {
        opacity: 0.5;
        transform: rotate(5deg);
        z-index: 1000;
    }
    
    .stats-card.drop-target {
        border: 2px dashed #007bff;
        transform: scale(1.05);
    }
    
    .stats-card.updating {
        position: relative;
        pointer-events: none;
    }
    
    .stats-card.updating::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        animation: pulse 1s infinite;
    }
    
    @keyframes pulse {
        0% { opacity: 0.6; }
        50% { opacity: 1; }
        100% { opacity: 0.6; }
    }
    
    .context-menu {
        background: white;
        border: 1px solid #ddd;
        border-radius: 4px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        overflow: hidden;
    }
    
    .context-menu-item {
        padding: 8px 16px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .context-menu-item:hover {
        background: #f5f5f5;
    }
    
    .context-menu-separator {
        height: 1px;
        background: #eee;
        margin: 4px 0;
    }
    
    .settings-panel {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: white;
        border: 1px solid #ddd;
        border-radius: 8px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.2);
        z-index: 10000;
        min-width: 400px;
    }
    
    .settings-header {
        padding: 16px 20px;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: between;
        align-items: center;
    }
    
    .settings-close {
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        margin-left: auto;
    }
    
    .settings-content {
        padding: 20px;
    }
    
    .setting-group {
        margin-bottom: 20px;
    }
    
    .setting-group label {
        display: block;
        margin-bottom: 8px;
    }
    
    .card-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .card-modal {
        background: white;
        border-radius: 8px;
        max-width: 600px;
        max-height: 80vh;
        overflow: auto;
    }
    
    .card-modal-header {
        padding: 20px;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: between;
        align-items: center;
    }
    
    .card-modal-content {
        padding: 20px;
    }
`;

document.head.appendChild(dashboardStyles);

// Initialize dashboard manager when DOM is ready
let dashboardManager;

document.addEventListener('DOMContentLoaded', function() {
    dashboardManager = new DashboardManager();
    
    // Make available globally
    window.dashboardManager = dashboardManager;
});

// Export for module use
window.DashboardManager = DashboardManager;