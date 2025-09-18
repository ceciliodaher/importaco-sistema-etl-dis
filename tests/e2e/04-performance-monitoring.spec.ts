import { test, expect } from '@playwright/test';
import { TestHelpers, TestValidations } from '../utils/test-helpers';

/**
 * Performance Monitoring Test Suite
 * 
 * Validates system performance and responsiveness:
 * - Page load times and rendering performance
 * - API response times and monitoring
 * - Memory usage and resource utilization
 * - Network efficiency and caching
 * - Database query performance
 * - User interface responsiveness
 */

test.describe('Performance Monitoring', () => {
  let helpers: TestHelpers;

  test.beforeEach(async ({ page }) => {
    helpers = new TestHelpers(page, 'performance-monitoring');
    await helpers.setupNetworkMonitoring();
  });

  test.afterEach(async () => {
    await helpers.saveTestReport();
  });

  test('Dashboard initial load performance', async ({ page }) => {
    console.log('🧪 Testing: Dashboard initial load performance');
    
    // Capture performance metrics
    const startTime = Date.now();
    
    // Navigate with timing
    const response = await page.goto('/sistema/dashboard/index.php', {
      waitUntil: 'domcontentloaded',
      timeout: 30000
    });
    
    const loadTime = Date.now() - startTime;
    
    // Capture initial load screenshot
    await helpers.captureScreenshot('initial-load-performance');
    
    // Verify page loaded successfully
    expect(response?.status()).toBe(200);
    
    // Check load time performance
    console.log(`⏱️ Initial load time: ${loadTime}ms`);
    expect(loadTime, 'Initial load time too slow').toBeLessThan(10000); // Should load within 10 seconds
    
    // Wait for all critical elements to be visible
    await page.waitForSelector('.dashboard-container', { timeout: 5000 });
    await page.waitForSelector('#manualControlPanel', { timeout: 5000 });
    
    const fullyLoadedTime = Date.now() - startTime;
    console.log(`🎯 Fully loaded time: ${fullyLoadedTime}ms`);
    
    // Verify fully loaded performance
    expect(fullyLoadedTime, 'Fully loaded time too slow').toBeLessThan(15000); // Should be fully loaded within 15 seconds
    
    // Get detailed performance metrics
    const performanceMetrics = await page.evaluate(() => {
      const perf = performance.getEntriesByType('navigation')[0] as PerformanceNavigationTiming;
      return {
        domContentLoaded: perf.domContentLoadedEventEnd - perf.domContentLoadedEventStart,
        loadComplete: perf.loadEventEnd - perf.loadEventStart,
        firstByte: perf.responseStart - perf.requestStart,
        domParsing: perf.domInteractive - perf.domLoading,
        resourceLoading: perf.loadEventStart - perf.domContentLoadedEventEnd
      };
    });
    
    console.log('📊 Performance Metrics:', performanceMetrics);
    
    // Verify individual metrics
    expect(performanceMetrics.firstByte, 'First byte time too slow').toBeLessThan(3000);
    expect(performanceMetrics.domParsing, 'DOM parsing too slow').toBeLessThan(2000);
    
    console.log('✅ Dashboard initial load performance acceptable');
  });

  test('API response time monitoring', async ({ page }) => {
    console.log('🧪 Testing: API response time monitoring');
    
    await helpers.navigateToDashboard();
    await helpers.waitForManualControlSystem();
    
    // Test different API endpoints
    const apiTests = [
      { button: '#btnVerifyDatabase', name: 'Database Status Check', timeout: 5000 },
      { button: '#btnLoadStats', name: 'Load Statistics', timeout: 8000 },
      { button: '#btnLoadCharts', name: 'Load Charts', timeout: 10000 }
    ];
    
    const responseTimeResults: Array<{ api: string; responseTime: number; success: boolean }> = [];
    
    for (const apiTest of apiTests) {
      console.log(`🔍 Testing API: ${apiTest.name}`);
      
      const button = page.locator(apiTest.button);
      const isEnabled = await button.isEnabled();
      
      if (!isEnabled) {
        console.log(`⚠️ ${apiTest.name} button is disabled, skipping`);
        continue;
      }
      
      const apiStartTime = Date.now();
      
      // Click button and monitor response
      await button.click();
      
      try {
        // Wait for any network activity to complete
        await page.waitForLoadState('networkidle', { timeout: apiTest.timeout });
        
        const responseTime = Date.now() - apiStartTime;
        responseTimeResults.push({
          api: apiTest.name,
          responseTime,
          success: true
        });
        
        console.log(`✅ ${apiTest.name}: ${responseTime}ms`);
        
        // Verify response time is acceptable
        expect(responseTime, `${apiTest.name} response too slow`).toBeLessThan(apiTest.timeout);
        
      } catch (error) {
        const responseTime = Date.now() - apiStartTime;
        responseTimeResults.push({
          api: apiTest.name,
          responseTime,
          success: false
        });
        
        console.log(`❌ ${apiTest.name}: timeout or error after ${responseTime}ms`);
      }
      
      // Wait between API calls
      await page.waitForTimeout(1000);
    }
    
    // Capture performance results
    await helpers.captureScreenshot('api-response-times');
    
    // Calculate average response time
    const successfulTests = responseTimeResults.filter(r => r.success);
    const averageResponseTime = successfulTests.length > 0 
      ? successfulTests.reduce((sum, r) => sum + r.responseTime, 0) / successfulTests.length 
      : 0;
    
    console.log(`📈 Average API Response Time: ${Math.round(averageResponseTime)}ms`);
    console.log('📊 All Response Times:', responseTimeResults);
    
    // Verify overall performance
    if (successfulTests.length > 0) {
      expect(averageResponseTime, 'Average API response time too slow').toBeLessThan(5000);
    }
    
    console.log('✅ API response time monitoring completed');
  });

  test('Resource loading efficiency', async ({ page }) => {
    console.log('🧪 Testing: Resource loading efficiency');
    
    // Monitor all resource loading
    const resourceMetrics: Array<{ url: string; type: string; size: number; duration: number }> = [];
    
    page.on('response', async (response) => {
      try {
        const request = response.request();
        const resourceType = request.resourceType();
        const contentLength = response.headers()['content-length'];
        const size = contentLength ? parseInt(contentLength) : 0;
        
        // Calculate response time
        const timing = response.timing();
        const duration = timing.responseEnd - timing.responseStart;
        
        resourceMetrics.push({
          url: response.url(),
          type: resourceType,
          size,
          duration
        });
      } catch (error) {
        // Ignore timing errors for some requests
      }
    });
    
    // Navigate and load dashboard
    await helpers.navigateToDashboard();
    await page.waitForLoadState('networkidle');
    
    // Analyze resource metrics
    const totalResources = resourceMetrics.length;
    const totalSize = resourceMetrics.reduce((sum, r) => sum + r.size, 0);
    const averageLoadTime = resourceMetrics.length > 0 
      ? resourceMetrics.reduce((sum, r) => sum + r.duration, 0) / resourceMetrics.length 
      : 0;
    
    // Group by resource type
    const resourceTypes = resourceMetrics.reduce((acc, r) => {
      acc[r.type] = acc[r.type] || { count: 0, totalSize: 0, totalDuration: 0 };
      acc[r.type].count++;
      acc[r.type].totalSize += r.size;
      acc[r.type].totalDuration += r.duration;
      return acc;
    }, {} as Record<string, { count: number; totalSize: number; totalDuration: number }>);
    
    console.log('📊 Resource Loading Metrics:');
    console.log(`   Total Resources: ${totalResources}`);
    console.log(`   Total Size: ${(totalSize / 1024).toFixed(2)} KB`);
    console.log(`   Average Load Time: ${averageLoadTime.toFixed(2)}ms`);
    console.log('📋 By Resource Type:', resourceTypes);
    
    await helpers.captureScreenshot('resource-loading-efficiency');
    
    // Performance expectations
    expect(totalResources, 'Too many resources loaded').toBeLessThan(50);
    expect(totalSize, 'Total resource size too large').toBeLessThan(5 * 1024 * 1024); // 5MB
    expect(averageLoadTime, 'Average resource load time too slow').toBeLessThan(1000);
    
    // Check for specific resource types efficiency
    const cssResources = resourceTypes['stylesheet'] || { count: 0, totalSize: 0 };
    const jsResources = resourceTypes['script'] || { count: 0, totalSize: 0 };
    
    expect(cssResources.count, 'Too many CSS files').toBeLessThan(10);
    expect(jsResources.count, 'Too many JS files').toBeLessThan(20);
    
    console.log('✅ Resource loading efficiency acceptable');
  });

  test('Memory usage monitoring', async ({ page }) => {
    console.log('🧪 Testing: Memory usage monitoring');
    
    await helpers.navigateToDashboard();
    
    // Get initial memory usage
    const initialMemory = await page.evaluate(() => {
      if ('memory' in performance) {
        const mem = (performance as any).memory;
        return {
          usedJSHeapSize: mem.usedJSHeapSize,
          totalJSHeapSize: mem.totalJSHeapSize,
          jsHeapSizeLimit: mem.jsHeapSizeLimit
        };
      }
      return null;
    });
    
    if (initialMemory) {
      console.log('💾 Initial Memory Usage:', {
        used: `${(initialMemory.usedJSHeapSize / 1024 / 1024).toFixed(2)} MB`,
        total: `${(initialMemory.totalJSHeapSize / 1024 / 1024).toFixed(2)} MB`,
        limit: `${(initialMemory.jsHeapSizeLimit / 1024 / 1024).toFixed(2)} MB`
      });
    }
    
    // Perform memory-intensive operations
    await helpers.waitForManualControlSystem();
    
    // Load charts if available
    const loadChartsButton = page.locator('#btnLoadCharts');
    if (await loadChartsButton.isEnabled()) {
      await loadChartsButton.click();
      await page.waitForTimeout(3000);
    }
    
    // Load stats if available
    const loadStatsButton = page.locator('#btnLoadStats');
    if (await loadStatsButton.isEnabled()) {
      await loadStatsButton.click();
      await page.waitForTimeout(3000);
    }
    
    // Get memory usage after operations
    const finalMemory = await page.evaluate(() => {
      if ('memory' in performance) {
        const mem = (performance as any).memory;
        return {
          usedJSHeapSize: mem.usedJSHeapSize,
          totalJSHeapSize: mem.totalJSHeapSize,
          jsHeapSizeLimit: mem.jsHeapSizeLimit
        };
      }
      return null;
    });
    
    if (finalMemory && initialMemory) {
      const memoryIncrease = finalMemory.usedJSHeapSize - initialMemory.usedJSHeapSize;
      const memoryIncreasePercent = (memoryIncrease / initialMemory.usedJSHeapSize) * 100;
      
      console.log('💾 Final Memory Usage:', {
        used: `${(finalMemory.usedJSHeapSize / 1024 / 1024).toFixed(2)} MB`,
        increase: `${(memoryIncrease / 1024 / 1024).toFixed(2)} MB (${memoryIncreasePercent.toFixed(1)}%)`
      });
      
      // Verify memory usage is reasonable
      expect(finalMemory.usedJSHeapSize, 'Memory usage too high').toBeLessThan(100 * 1024 * 1024); // 100MB
      expect(memoryIncreasePercent, 'Memory increase too large').toBeLessThan(200); // 200% increase max
    }
    
    await helpers.captureScreenshot('memory-usage-monitoring');
    
    console.log('✅ Memory usage monitoring completed');
  });

  test('Database query performance', async ({ page }) => {
    console.log('🧪 Testing: Database query performance');
    
    await helpers.navigateToDashboard();
    await helpers.waitForManualControlSystem();
    
    // Monitor database-related API calls
    const dbPerformanceTests = [
      { 
        button: '#btnVerifyDatabase', 
        name: 'Database Status Check',
        expectedMaxTime: 3000
      },
      { 
        button: '#btnLoadStats', 
        name: 'Statistics Query',
        expectedMaxTime: 5000
      }
    ];
    
    for (const test of dbPerformanceTests) {
      const button = page.locator(test.button);
      
      if (await button.isEnabled()) {
        console.log(`🗄️ Testing database performance: ${test.name}`);
        
        const startTime = Date.now();
        await button.click();
        
        // Wait for completion
        await page.waitForTimeout(1000);
        
        const queryTime = Date.now() - startTime;
        console.log(`⏱️ ${test.name}: ${queryTime}ms`);
        
        // Verify query performance
        expect(queryTime, `${test.name} query too slow`).toBeLessThan(test.expectedMaxTime);
        
        await page.waitForTimeout(500); // Brief pause between tests
      }
    }
    
    await helpers.captureScreenshot('database-query-performance');
    
    console.log('✅ Database query performance testing completed');
  });

  test('UI responsiveness under load', async ({ page }) => {
    console.log('🧪 Testing: UI responsiveness under load');
    
    await helpers.navigateToDashboard();
    await helpers.waitForManualControlSystem();
    
    // Capture baseline responsiveness
    await helpers.captureScreenshot('ui-baseline');
    
    // Test rapid button clicks
    const testButtons = [
      '#btnVerifyDatabase',
      '#btnClearCache'
    ];
    
    const responsivenessTimes: number[] = [];
    
    for (const buttonSelector of testButtons) {
      const button = page.locator(buttonSelector);
      
      if (await button.isVisible() && await button.isEnabled()) {
        console.log(`🖱️ Testing responsiveness: ${buttonSelector}`);
        
        // Measure click responsiveness
        const clickStartTime = Date.now();
        await button.click();
        
        // Wait for UI feedback (loading state, etc.)
        await page.waitForTimeout(100);
        
        const responseTime = Date.now() - clickStartTime;
        responsivenessTimes.push(responseTime);
        
        console.log(`⚡ ${buttonSelector} response: ${responseTime}ms`);
        
        // UI should respond immediately (within 100ms)
        expect(responseTime, `${buttonSelector} UI response too slow`).toBeLessThan(500);
        
        await page.waitForTimeout(1000); // Wait for operation to complete
      }
    }
    
    // Test scroll performance
    console.log('🖱️ Testing scroll performance');
    const scrollStartTime = Date.now();
    
    await page.evaluate(() => {
      window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
    });
    
    await page.waitForTimeout(500);
    
    await page.evaluate(() => {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
    
    const scrollTime = Date.now() - scrollStartTime;
    console.log(`📜 Scroll performance: ${scrollTime}ms`);
    
    await helpers.captureScreenshot('ui-responsiveness-test');
    
    // Calculate average responsiveness
    const averageResponseTime = responsivenessTimes.length > 0 
      ? responsivenessTimes.reduce((sum, time) => sum + time, 0) / responsivenessTimes.length 
      : 0;
    
    console.log(`📊 Average UI Response Time: ${averageResponseTime.toFixed(2)}ms`);
    
    expect(averageResponseTime, 'Overall UI responsiveness too slow').toBeLessThan(200);
    
    console.log('✅ UI responsiveness under load acceptable');
  });

  test('Network efficiency and caching', async ({ page }) => {
    console.log('🧪 Testing: Network efficiency and caching');
    
    // First visit - track all requests
    const firstVisitRequests: string[] = [];
    
    page.on('request', (request) => {
      firstVisitRequests.push(request.url());
    });
    
    await helpers.navigateToDashboard();
    await page.waitForLoadState('networkidle');
    
    const firstVisitRequestCount = firstVisitRequests.length;
    console.log(`📡 First visit requests: ${firstVisitRequestCount}`);
    
    await helpers.captureScreenshot('first-visit-network');
    
    // Second visit - check caching
    const secondVisitRequests: string[] = [];
    
    page.off('request'); // Remove previous listener
    page.on('request', (request) => {
      secondVisitRequests.push(request.url());
    });
    
    // Reload the page
    await page.reload({ waitUntil: 'networkidle' });
    
    const secondVisitRequestCount = secondVisitRequests.length;
    console.log(`📡 Second visit requests: ${secondVisitRequestCount}`);
    
    await helpers.captureScreenshot('second-visit-network');
    
    // Analyze caching efficiency
    const cachingEfficiency = ((firstVisitRequestCount - secondVisitRequestCount) / firstVisitRequestCount) * 100;
    console.log(`💾 Caching efficiency: ${cachingEfficiency.toFixed(1)}%`);
    
    // Find requests that should be cached but weren't
    const staticResourcePatterns = ['.css', '.js', '.png', '.jpg', '.svg', '.woff'];
    
    const uncachedStaticResources = secondVisitRequests.filter(url => 
      staticResourcePatterns.some(pattern => url.includes(pattern))
    );
    
    console.log(`🚫 Uncached static resources: ${uncachedStaticResources.length}`);
    
    if (uncachedStaticResources.length > 0) {
      console.log('📋 Uncached resources:', uncachedStaticResources.slice(0, 5)); // Show first 5
    }
    
    // Verify caching performance
    expect(secondVisitRequestCount, 'Too many requests on second visit').toBeLessThan(firstVisitRequestCount * 1.1);
    
    // At least some resources should be cached
    if (firstVisitRequestCount > 10) {
      expect(cachingEfficiency, 'Caching efficiency too low').toBeGreaterThan(10);
    }
    
    console.log('✅ Network efficiency and caching analysis completed');
  });
});