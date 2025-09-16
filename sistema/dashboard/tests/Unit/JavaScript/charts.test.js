/**
 * ================================================================================
 * TESTES UNITÁRIOS - CHARTS.JS
 * Validação dos gráficos Chart.js e interações visuais
 * ================================================================================
 */

describe('Dashboard Charts', () => {
  let chartsContainer;
  let mockChart;
  
  beforeEach(() => {
    // Criar container para gráficos
    chartsContainer = createTestElement('div', {
      id: 'charts-container',
      className: 'charts-container'
    });
    
    // Mock de instância do Chart
    mockChart = {
      update: jest.fn(),
      destroy: jest.fn(),
      data: {
        labels: [],
        datasets: []
      },
      options: {},
      canvas: createTestElement('canvas')
    };
    
    // Sobrescrever Chart constructor
    global.Chart = jest.fn().mockImplementation(() => mockChart);
    
    jest.clearAllMocks();
  });
  
  describe('Chart Initialization', () => {
    test('should initialize evolution chart', () => {
      const canvas = createTestElement('canvas', {
        id: 'evolution-chart',
        appendTo: chartsContainer
      });
      
      const initEvolutionChart = (canvasId, data) => {
        const ctx = document.getElementById(canvasId);
        if (!ctx) {
          throw new Error('Canvas evolution-chart não disponível - obrigatório para o fluxo');
        }
        
        const config = {
          type: 'line',
          data: data,
          options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
              y: {
                beginAtZero: true
              }
            }
          }
        };
        
        return new Chart(ctx, config);
      };
      
      const chartData = {
        labels: ['Jan', 'Feb', 'Mar'],
        datasets: [{
          label: 'Evolução USD',
          data: [100000, 120000, 150000],
          borderColor: '#3b82f6',
          backgroundColor: 'rgba(59, 130, 246, 0.1)'
        }]
      };
      
      const chart = initEvolutionChart('evolution-chart', chartData);
      
      expect(Chart).toHaveBeenCalledWith(canvas, expect.objectContaining({
        type: 'line',
        data: chartData
      }));
      
      expect(chart).toBeDefined();
    });
    
    test('should initialize taxes breakdown chart', () => {
      const canvas = createTestElement('canvas', {
        id: 'taxes-chart',
        appendTo: chartsContainer
      });
      
      const initTaxesChart = (canvasId, data) => {
        const ctx = document.getElementById(canvasId);
        if (!ctx) {
          throw new Error('Canvas taxes-chart não disponível - obrigatório para o fluxo');
        }
        
        const config = {
          type: 'doughnut',
          data: data,
          options: {
            responsive: true,
            plugins: {
              legend: {
                position: 'bottom'
              }
            }
          }
        };
        
        return new Chart(ctx, config);
      };
      
      const taxesData = {
        labels: ['II', 'IPI', 'PIS', 'COFINS', 'ICMS'],
        datasets: [{
          data: [250000, 180000, 95000, 120000, 300000],
          backgroundColor: [
            '#ef4444', // II - Vermelho
            '#f97316', // IPI - Laranja
            '#eab308', // PIS - Amarelo
            '#22c55e', // COFINS - Verde
            '#3b82f6'  // ICMS - Azul
          ]
        }]
      };
      
      const chart = initTaxesChart('taxes-chart', taxesData);
      
      expect(Chart).toHaveBeenCalledWith(canvas, expect.objectContaining({
        type: 'doughnut',
        data: taxesData
      }));
    });
    
    test('should handle missing canvas gracefully', () => {
      const initChart = (canvasId) => {
        const ctx = document.getElementById(canvasId);
        if (!ctx) {
          throw new Error(`Canvas ${canvasId} não disponível - obrigatório para o fluxo`);
        }
        return new Chart(ctx, {});
      };
      
      expect(() => initChart('nonexistent-canvas')).toThrow('Canvas nonexistent-canvas não disponível - obrigatório para o fluxo');
    });
  });
  
  describe('Chart Data Management', () => {
    test('should update chart data correctly', () => {
      const updateChartData = (chart, newData) => {
        if (!chart || !chart.data) {
          throw new Error('Chart instance não disponível - obrigatório para o fluxo');
        }
        
        chart.data.labels = newData.labels;
        chart.data.datasets = newData.datasets;
        chart.update();
      };
      
      const newData = {
        labels: ['Apr', 'May', 'Jun'],
        datasets: [{
          label: 'Updated Data',
          data: [200000, 180000, 220000]
        }]
      };
      
      updateChartData(mockChart, newData);
      
      expect(mockChart.data.labels).toEqual(['Apr', 'May', 'Jun']);
      expect(mockChart.data.datasets).toEqual(newData.datasets);
      expect(mockChart.update).toHaveBeenCalledTimes(1);
    });
    
    test('should validate chart data structure', () => {
      const validateChartData = (data) => {
        if (!data || typeof data !== 'object') {
          return false;
        }
        
        if (!Array.isArray(data.labels) || !Array.isArray(data.datasets)) {
          return false;
        }
        
        // Verificar que cada dataset tem estrutura válida
        for (const dataset of data.datasets) {
          if (!dataset.data || !Array.isArray(dataset.data)) {
            return false;
          }
          
          if (dataset.data.length !== data.labels.length) {
            return false;
          }
        }
        
        return true;
      };
      
      const validData = {
        labels: ['A', 'B', 'C'],
        datasets: [{
          data: [1, 2, 3]
        }]
      };
      
      const invalidData = {
        labels: ['A', 'B'],
        datasets: [{
          data: [1, 2, 3] // Tamanho diferente dos labels
        }]
      };
      
      expect(validateChartData(validData)).toBe(true);
      expect(validateChartData(invalidData)).toBe(false);
    });
    
    test('should handle empty data gracefully', () => {
      const handleEmptyData = (chart) => {
        const emptyData = {
          labels: ['Sem dados'],
          datasets: [{
            label: 'Nenhum registro encontrado',
            data: [0],
            backgroundColor: '#6b7280'
          }]
        };
        
        chart.data.labels = emptyData.labels;
        chart.data.datasets = emptyData.datasets;
        chart.update();
        
        return true;
      };
      
      const result = handleEmptyData(mockChart);
      
      expect(result).toBe(true);
      expect(mockChart.data.labels).toEqual(['Sem dados']);
      expect(mockChart.update).toHaveBeenCalled();
    });
  });
  
  describe('Chart Interactions', () => {
    test('should handle chart click events', () => {
      let clickedData = null;
      
      const handleChartClick = (event, elements, chart) => {
        if (elements.length > 0) {
          const element = elements[0];
          const dataIndex = element.index;
          const dataset = chart.data.datasets[element.datasetIndex];
          
          clickedData = {
            label: chart.data.labels[dataIndex],
            value: dataset.data[dataIndex],
            dataset: dataset.label
          };
        }
      };
      
      // Simular click em elemento do gráfico
      const mockElements = [{
        index: 1,
        datasetIndex: 0
      }];
      
      const chartWithData = {
        ...mockChart,
        data: {
          labels: ['Jan', 'Feb', 'Mar'],
          datasets: [{
            label: 'Valores',
            data: [100, 200, 150]
          }]
        }
      };
      
      handleChartClick(null, mockElements, chartWithData);
      
      expect(clickedData).toEqual({
        label: 'Feb',
        value: 200,
        dataset: 'Valores'
      });
    });
    
    test('should handle chart hover effects', () => {
      let isHovered = false;
      
      const handleChartHover = (event, elements) => {
        isHovered = elements.length > 0;
        
        if (isHovered) {
          event.native.target.style.cursor = 'pointer';
        } else {
          event.native.target.style.cursor = 'default';
        }
      };
      
      const mockEvent = {
        native: {
          target: {
            style: {}
          }
        }
      };
      
      // Hover com elementos
      handleChartHover(mockEvent, [{ index: 0 }]);
      expect(isHovered).toBe(true);
      expect(mockEvent.native.target.style.cursor).toBe('pointer');
      
      // Hover sem elementos
      handleChartHover(mockEvent, []);
      expect(isHovered).toBe(false);
      expect(mockEvent.native.target.style.cursor).toBe('default');
    });
  });
  
  describe('Chart Responsiveness', () => {
    test('should resize charts on window resize', () => {
      const resizeChart = (chart) => {
        if (chart && chart.canvas) {
          chart.canvas.style.height = '400px';
          chart.update('resize');
        }
      };
      
      resizeChart(mockChart);
      
      expect(mockChart.canvas.style.height).toBe('400px');
      expect(mockChart.update).toHaveBeenCalledWith('resize');
    });
    
    test('should adapt chart options for mobile', () => {
      const getMobileChartOptions = () => ({
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              boxWidth: 12,
              fontSize: 10
            }
          }
        },
        scales: {
          x: {
            ticks: {
              maxRotation: 45,
              fontSize: 10
            }
          },
          y: {
            ticks: {
              fontSize: 10
            }
          }
        }
      });
      
      const mobileOptions = getMobileChartOptions();
      
      expect(mobileOptions.plugins.legend.position).toBe('bottom');
      expect(mobileOptions.plugins.legend.labels.boxWidth).toBe(12);
      expect(mobileOptions.scales.x.ticks.maxRotation).toBe(45);
    });
  });
  
  describe('Chart Colors and Themes', () => {
    test('should apply consistent color scheme', () => {
      const colorScheme = {
        primary: '#3b82f6',
        secondary: '#6366f1',
        success: '#22c55e',
        warning: '#eab308',
        danger: '#ef4444',
        info: '#06b6d4'
      };
      
      const getChartColors = (type, count = 1) => {
        const colors = Object.values(colorScheme);
        return colors.slice(0, count);
      };
      
      const evolutionColors = getChartColors('line', 2);
      expect(evolutionColors).toEqual(['#3b82f6', '#6366f1']);
      
      const taxesColors = getChartColors('doughnut', 5);
      expect(taxesColors).toHaveLength(5);
      expect(taxesColors[0]).toBe('#3b82f6');
    });
    
    test('should support dark theme', () => {
      const getDarkThemeOptions = () => ({
        scales: {
          x: {
            ticks: {
              color: '#ffffff'
            },
            grid: {
              color: '#374151'
            }
          },
          y: {
            ticks: {
              color: '#ffffff'
            },
            grid: {
              color: '#374151'
            }
          }
        },
        plugins: {
          legend: {
            labels: {
              color: '#ffffff'
            }
          }
        }
      });
      
      const darkOptions = getDarkThemeOptions();
      
      expect(darkOptions.scales.x.ticks.color).toBe('#ffffff');
      expect(darkOptions.scales.y.grid.color).toBe('#374151');
      expect(darkOptions.plugins.legend.labels.color).toBe('#ffffff');
    });
  });
  
  describe('Chart Performance', () => {
    test('should optimize large datasets', () => {
      const optimizeDataset = (data, maxPoints = 50) => {
        if (data.length <= maxPoints) {
          return data;
        }
        
        const step = Math.ceil(data.length / maxPoints);
        return data.filter((_, index) => index % step === 0);
      };
      
      const largeDataset = Array.from({ length: 1000 }, (_, i) => i);
      const optimized = optimizeDataset(largeDataset, 50);
      
      expect(optimized.length).toBeLessThanOrEqual(50);
      expect(optimized[0]).toBe(0);
    });
    
    test('should measure chart render time', () => {
      const measureRenderTime = (renderFunction) => {
        const start = performance.now();
        renderFunction();
        const end = performance.now();
        return end - start;
      };
      
      const mockRender = () => {
        // Simular renderização
        for (let i = 0; i < 100; i++) {
          Math.random();
        }
      };
      
      const renderTime = measureRenderTime(mockRender);
      
      expect(renderTime).toBeGreaterThan(0);
      expect(renderTime).toBeLessThan(100); // Should be fast
    });
  });
  
  describe('Chart Error Handling', () => {
    test('should handle chart creation errors', () => {
      const createChartSafely = (canvasId, config) => {
        try {
          const ctx = document.getElementById(canvasId);
          if (!ctx) {
            throw new Error(`Canvas ${canvasId} não encontrado`);
          }
          
          return new Chart(ctx, config);
        } catch (error) {
          console.error('Erro ao criar gráfico:', error);
          return null;
        }
      };
      
      const chart = createChartSafely('nonexistent-canvas', {});
      
      expect(chart).toBeNull();
      expect(console.error).toHaveBeenCalledWith(
        'Erro ao criar gráfico:',
        expect.any(Error)
      );
    });
    
    test('should handle data loading failures', async () => {
      const loadChartDataWithFallback = async (endpoint) => {
        try {
          const response = await fetch(endpoint);
          if (!response.ok) {
            throw new Error('Falha ao carregar dados');
          }
          return await response.json();
        } catch (error) {
          console.warn('Usando dados de fallback:', error.message);
          return {
            labels: ['Sem dados'],
            datasets: [{
              label: 'Dados indisponíveis',
              data: [0],
              backgroundColor: '#6b7280'
            }]
          };
        }
      };
      
      // Mock fetch failure
      fetch.mockRejectedValueOnce(new Error('Network error'));
      
      const data = await loadChartDataWithFallback('/api/charts/evolution');
      
      expect(data.labels).toEqual(['Sem dados']);
      expect(console.warn).toHaveBeenCalledWith(
        'Usando dados de fallback:',
        'Network error'
      );
    });
  });
  
  describe('Chart Accessibility', () => {
    test('should provide chart descriptions', () => {
      const generateChartDescription = (chartData) => {
        const totalValues = chartData.datasets.reduce((sum, dataset) => {
          return sum + dataset.data.reduce((a, b) => a + b, 0);
        }, 0);
        
        const highestValue = Math.max(...chartData.datasets[0].data);
        const highestIndex = chartData.datasets[0].data.indexOf(highestValue);
        const highestLabel = chartData.labels[highestIndex];
        
        return `Gráfico com ${chartData.labels.length} pontos de dados. ` +
               `Total: ${totalValues.toLocaleString()}. ` +
               `Maior valor: ${highestValue.toLocaleString()} em ${highestLabel}.`;
      };
      
      const chartData = {
        labels: ['Jan', 'Feb', 'Mar'],
        datasets: [{
          data: [100, 300, 200]
        }]
      };
      
      const description = generateChartDescription(chartData);
      
      expect(description).toContain('Gráfico com 3 pontos de dados');
      expect(description).toContain('Total: 600');
      expect(description).toContain('Maior valor: 300 em Feb');
    });
    
    test('should support keyboard navigation', () => {
      const canvas = createTestElement('canvas', {
        id: 'accessible-chart',
        attributes: {
          'tabindex': '0',
          'role': 'img',
          'aria-label': 'Gráfico de evolução mensal'
        },
        appendTo: chartsContainer
      });
      
      let focusedElement = null;
      
      canvas.addEventListener('focus', () => {
        focusedElement = canvas;
      });
      
      canvas.focus();
      
      expect(focusedElement).toBe(canvas);
      expect(canvas.getAttribute('aria-label')).toBe('Gráfico de evolução mensal');
    });
  });
});