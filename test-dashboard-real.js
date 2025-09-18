const { chromium } = require('playwright');

(async () => {
  console.log('🚀 Iniciando teste do dashboard com dados reais...');
  
  const browser = await chromium.launch({ headless: false });
  const page = await browser.newPage();
  
  const errors = [];
  const httpErrors = [];
  
  // Capturar erros de console
  page.on('console', msg => {
    if (msg.type() === 'error') {
      errors.push(`CONSOLE ERROR: ${msg.text()}`);
      console.log('❌ Console Error:', msg.text());
    }
  });
  
  // Capturar erros HTTP 500/503
  page.on('response', response => {
    if (response.status() >= 500) {
      const error = `HTTP ${response.status()}: ${response.url()}`;
      httpErrors.push(error);
      console.log('❌ HTTP Error:', error);
    } else if (response.url().includes('/api/dashboard/charts.php')) {
      console.log('✅ Charts API Response:', response.status(), response.url());
    }
  });
  
  try {
    console.log('📍 Navegando para o dashboard...');
    await page.goto('https://importacao-sistema.local/sistema/dashboard/', { 
      waitUntil: 'networkidle',
      timeout: 30000 
    });
    
    console.log('⏳ Aguardando carregamento dos gráficos...');
    
    // Aguardar elementos dos gráficos aparecerem
    await page.waitForSelector('.chart-container', { timeout: 15000 });
    
    // Aguardar um pouco mais para todas as requisições terminarem
    await page.waitForTimeout(5000);
    
    console.log('📊 Verificando se gráficos carregaram...');
    
    // Verificar se há canvas (Chart.js) na página
    const chartElements = await page.$$('canvas');
    console.log(`📈 Gráficos encontrados: ${chartElements.length}`);
    
    // Capturar screenshot final
    await page.screenshot({ path: 'dashboard-test-result.png', fullPage: true });
    
    console.log('\n=== RESULTADO DO TESTE ===');
    console.log(`❌ Erros HTTP 500/503: ${httpErrors.length}`);
    console.log(`❌ Erros de Console: ${errors.length}`);
    console.log(`📊 Gráficos carregados: ${chartElements.length}`);
    
    if (httpErrors.length === 0 && errors.length === 0) {
      console.log('🎉 SUCESSO: Dashboard carregou sem erros!');
    } else {
      console.log('💥 FALHAS encontradas:');
      httpErrors.forEach(err => console.log('  - ' + err));
      errors.forEach(err => console.log('  - ' + err));
    }
    
  } catch (error) {
    console.log('💥 ERRO NO TESTE:', error.message);
  } finally {
    await browser.close();
  }
})();