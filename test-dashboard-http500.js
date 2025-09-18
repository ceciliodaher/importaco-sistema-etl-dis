const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

async function testDashboardHTTP500() {
    console.log('🚀 Iniciando teste completo do dashboard...');
    
    const browser = await chromium.launch({ 
        headless: false,
        slowMo: 1000 // Dar tempo para ver o que está acontecendo
    });
    
    const context = await browser.newContext();
    const page = await context.newPage();
    
    // Arrays para capturar logs e erros
    const httpErrors = [];
    const consoleErrors = [];
    const networkRequests = [];
    
    // Monitorar responses HTTP - FOCO em 500/503
    page.on('response', response => {
        const url = response.url();
        const status = response.status();
        
        networkRequests.push({ url, status, timestamp: new Date().toISOString() });
        
        if (status >= 500) {
            httpErrors.push({
                status,
                url,
                statusText: response.statusText(),
                timestamp: new Date().toISOString()
            });
            console.log('❌ HTTP ERROR DETECTADO:', status, url);
        }
        
        // Log específico para charts.php
        if (url.includes('charts.php')) {
            console.log(`📊 Charts API Response: ${status} - ${url}`);
        }
    });
    
    // Monitorar erros de console
    page.on('console', msg => {
        if (msg.type() === 'error') {
            consoleErrors.push({
                text: msg.text(),
                timestamp: new Date().toISOString()
            });
            console.log('🔴 Console Error:', msg.text());
        }
    });
    
    // Monitorar erros de rede
    page.on('requestfailed', request => {
        httpErrors.push({
            status: 'FAILED',
            url: request.url(),
            failure: request.failure()?.errorText || 'Unknown error',
            timestamp: new Date().toISOString()
        });
        console.log('🚫 Request Failed:', request.url(), request.failure()?.errorText);
    });
    
    try {
        console.log('📱 Acessando dashboard...');
        
        // Navegar para o dashboard
        await page.goto('https://importacao-sistema.local/sistema/dashboard/', {
            waitUntil: 'networkidle',
            timeout: 30000
        });
        
        console.log('⏱️ Aguardando carregamento completo...');
        
        // Aguardar carregamento dos elementos principais
        await page.waitForSelector('.dashboard-container', { timeout: 15000 });
        
        // Aguardar especificamente pelos gráficos
        console.log('📊 Aguardando carregamento dos gráficos...');
        
        // Esperar um tempo para garantir que todas as chamadas AJAX foram feitas
        await page.waitForTimeout(5000);
        
        // Tentar aguardar pelo canvas dos gráficos (Chart.js)
        try {
            await page.waitForSelector('canvas', { timeout: 10000 });
            console.log('✅ Canvas dos gráficos encontrado');
        } catch (e) {
            console.log('⚠️ Canvas não encontrado, mas continuando teste...');
        }
        
        // Capturar screenshot para análise visual
        await page.screenshot({ 
            path: '/Users/ceciliodaher/Documents/git/importaco-sistema/dashboard-test-screenshot.png',
            fullPage: true 
        });
        
        console.log('📷 Screenshot capturado: dashboard-test-screenshot.png');
        
        // Fazer chamada específica para charts.php
        console.log('🎯 Testando API charts.php diretamente...');
        
        const chartsResponse = await page.goto('https://importacao-sistema.local/sistema/dashboard/api/dashboard/charts.php?type=all', {
            timeout: 10000
        });
        
        console.log(`📊 Charts API Direct Response: ${chartsResponse.status()}`);
        
        if (chartsResponse.status() === 200) {
            const responseText = await chartsResponse.text();
            console.log('✅ Charts API retornou 200');
            console.log('📄 Response preview:', responseText.substring(0, 200) + '...');
            
            // Tentar parsear JSON
            try {
                const jsonData = JSON.parse(responseText);
                console.log('✅ JSON válido retornado');
                console.log('📊 Keys no response:', Object.keys(jsonData));
            } catch (e) {
                console.log('❌ Response não é JSON válido:', e.message);
            }
        } else {
            console.log('❌ Charts API falhou:', chartsResponse.status());
        }
        
        // Aguardar mais um pouco para capturar qualquer request atrasado
        await page.waitForTimeout(3000);
        
    } catch (error) {
        console.log('❌ Erro durante navegação:', error.message);
        
        // Capturar screenshot do erro
        await page.screenshot({ 
            path: '/Users/ceciliodaher/Documents/git/importaco-sistema/dashboard-error-screenshot.png',
            fullPage: true 
        });
    }
    
    await browser.close();
    
    // Gerar relatório final
    console.log('\n📋 RELATÓRIO FINAL:');
    console.log('==================');
    
    console.log(`🌐 Total de requests: ${networkRequests.length}`);
    console.log(`❌ HTTP Errors (500+): ${httpErrors.length}`);
    console.log(`🔴 Console Errors: ${consoleErrors.length}`);
    
    if (httpErrors.length > 0) {
        console.log('\n❌ ERROS HTTP DETECTADOS:');
        httpErrors.forEach(error => {
            console.log(`  ${error.status} - ${error.url} (${error.timestamp})`);
            if (error.failure) console.log(`    Failure: ${error.failure}`);
        });
    } else {
        console.log('\n✅ NENHUM ERRO HTTP 500+ DETECTADO!');
    }
    
    if (consoleErrors.length > 0) {
        console.log('\n🔴 ERROS DE CONSOLE:');
        consoleErrors.forEach(error => {
            console.log(`  ${error.text} (${error.timestamp})`);
        });
    }
    
    // Salvar relatório detalhado
    const report = {
        timestamp: new Date().toISOString(),
        summary: {
            totalRequests: networkRequests.length,
            httpErrors: httpErrors.length,
            consoleErrors: consoleErrors.length,
            testPassed: httpErrors.length === 0
        },
        httpErrors,
        consoleErrors,
        allRequests: networkRequests
    };
    
    fs.writeFileSync(
        '/Users/ceciliodaher/Documents/git/importaco-sistema/dashboard-test-report.json',
        JSON.stringify(report, null, 2)
    );
    
    console.log('\n📄 Relatório completo salvo em: dashboard-test-report.json');
    
    return report.summary.testPassed;
}

// Executar teste
testDashboardHTTP500()
    .then(passed => {
        if (passed) {
            console.log('\n🎉 TESTE PASSOU! Dashboard está funcionando sem HTTP 500.');
            process.exit(0);
        } else {
            console.log('\n💥 TESTE FALHOU! Ainda há erros HTTP 500.');
            process.exit(1);
        }
    })
    .catch(error => {
        console.error('\n💥 Erro fatal no teste:', error);
        process.exit(1);
    });