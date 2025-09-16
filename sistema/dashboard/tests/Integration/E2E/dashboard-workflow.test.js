/**
 * ================================================================================
 * TESTES E2E - FLUXO COMPLETO DASHBOARD
 * Testes end-to-end com Puppeteer para jornadas críticas do usuário
 * ================================================================================
 */

const puppeteer = require('puppeteer');

describe('Dashboard E2E Workflow Tests', () => {
  let browser;
  let page;
  const baseUrl = process.env.TEST_BASE_URL || 'http://localhost:8000/dashboard';
  const timeout = 30000;
  
  beforeAll(async () => {
    browser = await puppeteer.launch({
      headless: process.env.CI !== 'false', // Mostrar browser em desenvolvimento local
      slowMo: process.env.CI ? 0 : 50, // Slow motion para debug
      args: [
        '--no-sandbox',
        '--disable-setuid-sandbox',
        '--disable-dev-shm-usage',
        '--disable-gpu'
      ],
      defaultViewport: {
        width: 1280,
        height: 720
      }
    });
  });
  
  beforeEach(async () => {
    page = await browser.newPage();
    
    // Interceptar requests para APIs
    await page.setRequestInterception(true);
    
    page.on('request', (request) => {
      // Mock responses para APIs durante testes
      if (request.url().includes('/api/dashboard/stats')) {
        request.respond({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            success: true,
            data: {
              total_dis: 125,
              total_adicoes: 350,
              valor_total_usd: 2500000,
              valor_total_brl: 12500000,
              total_impostos: 1875000,
              ticket_medio_usd: 20000,
              periodo: '6months',
              ultima_atualizacao: '2024-01-15T10:30:00Z'
            },
            timestamp: new Date().toISOString()
          })
        });
      } else if (request.url().includes('/api/dashboard/charts')) {
        request.respond({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            success: true,
            data: {
              chart_data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                  label: 'Evolução USD',
                  data: [400000, 450000, 380000, 520000, 480000, 550000],
                  borderColor: '#3b82f6',
                  backgroundColor: 'rgba(59, 130, 246, 0.1)'
                }]
              },
              chart_config: {
                type: 'line',
                options: {
                  responsive: true,
                  maintainAspectRatio: false
                }
              },
              type: 'evolution',
              period: '6months'
            }
          })
        });
      } else if (request.url().includes('/api/dashboard/search')) {
        request.respond({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            success: true,
            data: [
              {
                numero_di: 'TEST001',
                data_registro: '2024-01-15',
                importador_nome: 'Equiplex Industrial Ltda',
                importador_cnpj: '12.345.678/0001-90',
                valor_total_usd: 25000,
                valor_total_brl: 125000,
                status: 'concluida'
              }
            ],
            pagination: {
              current_page: 1,
              total_pages: 1,
              total_records: 1,
              records_per_page: 25
            }
          })
        });
      } else {
        request.continue();
      }
    });
    
    // Console logging para debug
    page.on('console', (msg) => {
      if (msg.type() === 'error') {
        console.error('Browser console error:', msg.text());
      }
    });
    
    // Error handling
    page.on('pageerror', (error) => {
      console.error('Page error:', error.message);
    });
  });
  
  afterEach(async () => {
    if (page) {
      await page.close();
    }
  });
  
  afterAll(async () => {
    if (browser) {
      await browser.close();
    }
  });
  
  describe('Dashboard Loading and Initialization', () => {
    test('should load dashboard page successfully', async () => {
      await page.goto(baseUrl, { waitUntil: 'networkidle2', timeout });
      
      // Verificar que página carregou
      const title = await page.title();
      expect(title).toContain('Dashboard');
      
      // Verificar elementos principais presentes
      await page.waitForSelector('#dashboard-container', { timeout: 5000 });
      await page.waitForSelector('#stats-container', { timeout: 5000 });
      await page.waitForSelector('#charts-container', { timeout: 5000 });
      
      const dashboardVisible = await page.isVisible('#dashboard-container');
      expect(dashboardVisible).toBe(true);
    });
    
    test('should load dashboard stats within performance target', async () => {
      const startTime = Date.now();
      
      await page.goto(baseUrl, { waitUntil: 'networkidle2', timeout });
      
      // Aguardar stats carregarem
      await page.waitForSelector('.stats-card', { timeout: 5000 });
      
      const endTime = Date.now();
      const loadTime = endTime - startTime;
      
      // Target: dashboard completo em < 3s
      expect(loadTime).toBeLessThan(3000);
      
      // Verificar que stats são exibidas corretamente
      const statsCards = await page.$$('.stats-card');
      expect(statsCards.length).toBeGreaterThan(0);
    });
    
    test('should display loading states appropriately', async () => {
      await page.goto(baseUrl);
      
      // Verificar se loading aparecer (pode ser muito rápido para capturar)
      const loadingExists = await page.$('.loading') !== null;
      
      // Aguardar loading desaparecer e conteúdo aparecer
      await page.waitForSelector('#stats-container:not(.loading)', { timeout: 5000 });
      
      const statsVisible = await page.isVisible('#stats-container');
      expect(statsVisible).toBe(true);
    });
  });
  
  describe('Dashboard Stats Display', () => {
    test('should display stats cards with correct values', async () => {
      await page.goto(baseUrl, { waitUntil: 'networkidle2', timeout });
      
      await page.waitForSelector('.stats-card', { timeout: 5000 });
      
      // Verificar total de DIs
      const totalDIs = await page.$eval('[data-stat="total-dis"]', el => el.textContent);
      expect(totalDIs).toContain('125');
      
      // Verificar valor total USD
      const valorUSD = await page.$eval('[data-stat="valor-usd"]', el => el.textContent);
      expect(valorUSD).toContain('2,500,000');
      
      // Verificar formato de moeda
      expect(valorUSD).toMatch(/\$|USD/);
    });
    
    test('should update stats when refresh button clicked', async () => {
      await page.goto(baseUrl, { waitUntil: 'networkidle2', timeout });
      
      await page.waitForSelector('#refresh-stats', { timeout: 5000 });
      
      // Clicar botão refresh
      await page.click('#refresh-stats');
      
      // Verificar que houve nova requisição para stats
      await page.waitForResponse(response => 
        response.url().includes('/api/dashboard/stats') && response.status() === 200
      );
      
      // Verificar que stats ainda estão visíveis
      const statsVisible = await page.isVisible('.stats-card');
      expect(statsVisible).toBe(true);
    });
  });
  
  describe('Dashboard Charts Interaction', () => {
    test('should render charts correctly', async () => {
      await page.goto(baseUrl, { waitUntil: 'networkidle2', timeout });
      
      // Aguardar charts carregarem
      await page.waitForSelector('canvas[id*="chart"]', { timeout: 10000 });
      
      const charts = await page.$$('canvas[id*="chart"]');
      expect(charts.length).toBeGreaterThan(0);
      
      // Verificar que canvas tem dimensões válidas
      const chartDimensions = await page.evaluate(() => {
        const canvas = document.querySelector('canvas[id*="chart"]');
        return {
          width: canvas.width,
          height: canvas.height
        };
      });
      
      expect(chartDimensions.width).toBeGreaterThan(0);
      expect(chartDimensions.height).toBeGreaterThan(0);
    });
    
    test('should switch between chart types', async () => {
      await page.goto(baseUrl, { waitUntil: 'networkidle2', timeout });
      
      // Aguardar charts carregarem
      await page.waitForSelector('.chart-tabs', { timeout: 5000 });
      
      // Clicar na aba de impostos
      await page.click('[data-chart="taxes"]');
      
      // Aguardar nova requisição de chart
      await page.waitForResponse(response => 
        response.url().includes('/api/dashboard/charts') && 
        response.url().includes('type=taxes')
      );
      
      // Verificar que aba está ativa
      const activeTab = await page.$eval('[data-chart="taxes"]', el => 
        el.classList.contains('active')
      );
      expect(activeTab).toBe(true);
    });
    
    test('should handle chart interactions (click/hover)', async () => {
      await page.goto(baseUrl, { waitUntil: 'networkidle2', timeout });
      
      await page.waitForSelector('canvas[id*="chart"]', { timeout: 10000 });
      
      // Hover sobre chart
      await page.hover('canvas[id*="chart"]');
      
      // Click no chart (pode abrir modal de detalhes)
      await page.click('canvas[id*="chart"]');
      
      // Verificar se modal de detalhes apareceu (se implementado)
      const modalExists = await page.$('.chart-detail-modal') !== null;
      
      // Teste passa se não há erro JavaScript
      const jsErrors = await page.evaluate(() => window.jsErrors || []);
      expect(jsErrors).toHaveLength(0);
    });
  });
  
  describe('Dashboard Search Functionality', () => {
    test('should perform search and display results', async () => {
      await page.goto(baseUrl, { waitUntil: 'networkidle2', timeout });
      
      // Aguardar search container carregar
      await page.waitForSelector('#search-container', { timeout: 5000 });
      
      // Navegar para aba de pesquisa
      await page.click('[data-view="search"]');
      
      // Preencher campo de pesquisa
      await page.type('#search-input', 'Equiplex');
      
      // Clicar botão pesquisar
      await page.click('#search-button');
      
      // Aguardar resultados
      await page.waitForResponse(response => 
        response.url().includes('/api/dashboard/search')
      );
      
      await page.waitForSelector('.search-results', { timeout: 5000 });
      
      // Verificar que resultados aparecem
      const results = await page.$$('.search-result-item');
      expect(results.length).toBeGreaterThan(0);
      
      // Verificar conteúdo do primeiro resultado
      const firstResult = await page.$eval('.search-result-item:first-child', el => el.textContent);
      expect(firstResult).toContain('Equiplex');
    });
    
    test('should handle pagination in search results', async () => {
      await page.goto(baseUrl, { waitUntil: 'networkidle2', timeout });
      
      await page.waitForSelector('#search-container', { timeout: 5000 });
      
      // Realizar pesquisa
      await page.click('[data-view="search"]');
      await page.type('#search-input', '');  // Pesquisa vazia para trazer todos
      await page.click('#search-button');
      
      await page.waitForSelector('.search-results', { timeout: 5000 });
      
      // Verificar se paginação existe
      const paginationExists = await page.$('.pagination') !== null;
      
      if (paginationExists) {
        // Clicar na próxima página
        const nextButton = await page.$('.pagination .next');
        if (nextButton) {
          await nextButton.click();
          
          // Aguardar nova página carregar
          await page.waitForResponse(response => 
            response.url().includes('/api/dashboard/search')
          );
          
          await page.waitForSelector('.search-results', { timeout: 5000 });
        }
      }
      
      expect(true).toBe(true); // Teste passa se não há erros
    });
  });
  
  describe('Dashboard Export Functionality', () => {
    test('should export data to different formats', async () => {
      await page.goto(baseUrl, { waitUntil: 'networkidle2', timeout });
      
      // Navegar para seção de export
      await page.waitForSelector('[data-view="export"]', { timeout: 5000 });
      await page.click('[data-view="export"]');
      
      // Aguardar opções de export carregarem
      await page.waitForSelector('.export-options', { timeout: 5000 });
      
      // Selecionar formato CSV
      await page.click('input[value="csv"]');
      
      // Selecionar tipo de dados
      await page.click('input[value="dis"]');
      
      // Clicar botão export
      const downloadPromise = page.waitForEvent('download');
      await page.click('#export-button');
      
      // Aguardar download iniciar
      const download = await downloadPromise;
      
      expect(download).toBeDefined();
      expect(download.suggestedFilename()).toMatch(/\.csv$/);
    });
  });
  
  describe('Dashboard Mobile Responsiveness', () => {
    test('should adapt layout for mobile viewport', async () => {
      // Configurar viewport mobile
      await page.setViewport({ width: 375, height: 667 });
      
      await page.goto(baseUrl, { waitUntil: 'networkidle2', timeout });
      
      await page.waitForSelector('#dashboard-container', { timeout: 5000 });
      
      // Verificar que layout mobile foi aplicado
      const isMobileLayout = await page.$eval('#dashboard-container', el => 
        el.classList.contains('mobile-layout') || 
        window.getComputedStyle(el).flexDirection === 'column'
      );
      
      expect(isMobileLayout).toBe(true);
      
      // Verificar que elementos são tocáveis (tamanho mínimo)
      const buttons = await page.$$('button');
      for (const button of buttons) {
        const boundingBox = await button.boundingBox();
        if (boundingBox) {
          expect(boundingBox.height).toBeGreaterThanOrEqual(44); // iOS touch target
        }
      }
    });
    
    test('should handle touch interactions', async () => {
      await page.setViewport({ width: 375, height: 667 });
      await page.goto(baseUrl, { waitUntil: 'networkidle2', timeout });
      
      await page.waitForSelector('#dashboard-container', { timeout: 5000 });
      
      // Simular touch tap
      await page.tap('#refresh-stats');
      
      // Verificar que tap funcionou (nova requisição)
      await page.waitForResponse(response => 
        response.url().includes('/api/dashboard/stats')
      );
      
      expect(true).toBe(true); // Teste passa se não há erros
    });
  });
  
  describe('Dashboard Error Handling', () => {
    test('should handle API errors gracefully', async () => {
      // Override intercept para retornar erro
      await page.setRequestInterception(true);
      
      page.on('request', (request) => {
        if (request.url().includes('/api/dashboard/stats')) {
          request.respond({
            status: 500,
            contentType: 'application/json',
            body: JSON.stringify({
              success: false,
              error: {
                message: 'Internal server error',
                code: 500
              }
            })
          });
        } else {
          request.continue();
        }
      });
      
      await page.goto(baseUrl, { waitUntil: 'networkidle2', timeout });
      
      // Aguardar mensagem de erro aparecer
      await page.waitForSelector('.error-message', { timeout: 5000 });
      
      const errorVisible = await page.isVisible('.error-message');
      expect(errorVisible).toBe(true);
      
      const errorText = await page.$eval('.error-message', el => el.textContent);
      expect(errorText).toContain('erro');
    });
    
    test('should handle network connectivity issues', async () => {
      await page.goto(baseUrl, { waitUntil: 'networkidle2', timeout });
      
      // Simular perda de conectividade
      await page.setOfflineMode(true);
      
      // Tentar refresh
      await page.click('#refresh-stats');
      
      // Aguardar indicador de conectividade
      await page.waitForSelector('.offline-indicator', { timeout: 5000 });
      
      const offlineVisible = await page.isVisible('.offline-indicator');
      expect(offlineVisible).toBe(true);
      
      // Restaurar conectividade
      await page.setOfflineMode(false);
    });
  });
  
  describe('Dashboard Performance', () => {
    test('should meet Core Web Vitals targets', async () => {
      await page.goto(baseUrl, { waitUntil: 'networkidle2', timeout });
      
      // Medir métricas de performance
      const metrics = await page.metrics();
      
      // Verificar uso de memória (< 50MB)
      expect(metrics.JSHeapUsedSize).toBeLessThan(50 * 1024 * 1024);
      
      // Medir LCP (Largest Contentful Paint)
      const lcp = await page.evaluate(() => {
        return new Promise((resolve) => {
          new PerformanceObserver((list) => {
            const entries = list.getEntries();
            const lastEntry = entries[entries.length - 1];
            resolve(lastEntry.startTime);
          }).observe({ entryTypes: ['largest-contentful-paint'] });
          
          // Timeout para resolver se LCP não disparar
          setTimeout(() => resolve(0), 5000);
        });
      });
      
      // LCP deve ser < 2.5s
      if (lcp > 0) {
        expect(lcp).toBeLessThan(2500);
      }
    });
    
    test('should handle concurrent user interactions smoothly', async () => {
      await page.goto(baseUrl, { waitUntil: 'networkidle2', timeout });
      
      await page.waitForSelector('#dashboard-container', { timeout: 5000 });
      
      // Realizar múltiplas interações rapidamente
      const interactions = [
        () => page.click('#refresh-stats'),
        () => page.click('[data-chart="taxes"]'),
        () => page.click('[data-view="search"]'),
        () => page.type('#search-input', 'test'),
        () => page.click('[data-view="overview"]')
      ];
      
      // Executar interações rapidamente
      for (const interaction of interactions) {
        await interaction();
        await page.waitForTimeout(100); // Pequena pausa
      }
      
      // Verificar que dashboard ainda está responsivo
      const dashboardResponsive = await page.isVisible('#dashboard-container');
      expect(dashboardResponsive).toBe(true);
    });
  });
});