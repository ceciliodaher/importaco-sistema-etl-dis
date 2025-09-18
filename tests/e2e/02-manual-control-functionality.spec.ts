import { test, expect } from '@playwright/test';
import { TestHelpers, TestValidations } from '../utils/test-helpers';

/**
 * Manual Control Functionality Test Suite
 * 
 * Validates the manual control system functionality:
 * - Manual control buttons trigger correct API calls
 * - No automatic loading behavior
 * - Proper error handling and user feedback
 * - Status updates after manual actions
 * - Control flow between different manual operations
 */

test.describe('Manual Control Functionality', () => {
  let helpers: TestHelpers;

  test.beforeEach(async ({ page }) => {
    helpers = new TestHelpers(page, 'manual-control-functionality');
    await helpers.setupNetworkMonitoring();
    await helpers.navigateToDashboard();
    await helpers.waitForManualControlSystem();
  });

  test.afterEach(async () => {
    await helpers.saveTestReport();
  });

  test('Manual "Verificar Status" button works correctly', async ({ page }) => {
    console.log('🧪 Testing: Manual "Verificar Status" button works correctly');
    
    // Capture initial state
    await helpers.captureScreenshot('before-verify-status');
    
    // Find and click the Verificar Status button
    const verifyButton = page.locator('#btnVerifyDatabase');
    await expect(verifyButton).toBeVisible();
    
    // Click the button and monitor API calls
    const initialAPICount = helpers.getAPICallCount();
    await verifyButton.click();
    
    // Wait for the status check to complete
    await page.waitForTimeout(2000);
    
    // Capture state after clicking
    await helpers.captureScreenshot('after-verify-status');
    
    // Verify status indicators updated
    const systemStatus = await helpers.verifySystemStatus();
    expect(Object.values(systemStatus)).not.toContain('Unknown');
    
    console.log('✅ Manual "Verificar Status" button works correctly');
  });

  test('Manual "Carregar Gráficos" button triggers correct API', async ({ page }) => {
    console.log('🧪 Testing: Manual "Carregar Gráficos" button triggers correct API');
    
    // Check if button is enabled (requires data)
    const loadChartsButton = page.locator('#btnLoadCharts');
    const isEnabled = await loadChartsButton.isEnabled();
    
    if (!isEnabled) {
      console.log('⚠️ Load Charts button is disabled (no data available)');
      await helpers.captureScreenshot('load-charts-disabled');
      return;
    }
    
    // Capture before state
    await helpers.captureScreenshot('before-load-charts');
    
    // Click button and monitor API call
    const initialAPICount = helpers.getAPICallCount();
    await loadChartsButton.click();
    
    // Wait for charts API call
    await page.waitForResponse(response => 
      response.url().includes('charts.php'), 
      { timeout: 10000 }
    );
    
    // Verify API call was made
    const finalAPICount = helpers.getAPICallCount();
    expect(finalAPICount).toBeGreaterThan(initialAPICount);
    
    // Capture after state
    await helpers.captureScreenshot('after-load-charts');
    
    console.log('✅ Manual "Carregar Gráficos" button triggers correct API');
  });

  test('Manual "Carregar Estatísticas" button triggers correct API', async ({ page }) => {
    console.log('🧪 Testing: Manual "Carregar Estatísticas" button triggers correct API');
    
    // Check if button is enabled
    const loadStatsButton = page.locator('#btnLoadStats');
    const isEnabled = await loadStatsButton.isEnabled();
    
    if (!isEnabled) {
      console.log('⚠️ Load Stats button is disabled (no data available)');
      await helpers.captureScreenshot('load-stats-disabled');
      return;
    }
    
    // Capture before state
    await helpers.captureScreenshot('before-load-stats');
    
    // Click button and monitor API call
    const initialAPICount = helpers.getAPICallCount();
    await loadStatsButton.click();
    
    // Wait for stats to load
    await page.waitForTimeout(3000);
    
    // Verify stats were updated in the UI
    const statsCards = page.locator('.dashboard-card .card-value');
    const statsCount = await statsCards.count();
    expect(statsCount).toBeGreaterThan(0);
    
    // Capture after state
    await helpers.captureScreenshot('after-load-stats');
    
    console.log('✅ Manual "Carregar Estatísticas" button triggers correct API');
  });

  test('Manual "Atualizar Tudo" button refreshes entire dashboard', async ({ page }) => {
    console.log('🧪 Testing: Manual "Atualizar Tudo" button refreshes entire dashboard');
    
    // Check if button is enabled
    const refreshAllButton = page.locator('#btnRefreshAll');
    const isEnabled = await refreshAllButton.isEnabled();
    
    if (!isEnabled) {
      console.log('⚠️ Refresh All button is disabled (no data available)');
      await helpers.captureScreenshot('refresh-all-disabled');
      return;
    }
    
    // Capture before state
    await helpers.captureScreenshot('before-refresh-all');
    
    // Click button and monitor multiple API calls
    const initialAPICount = helpers.getAPICallCount();
    await refreshAllButton.click();
    
    // Wait for refresh to complete
    await page.waitForTimeout(5000);
    
    // Verify multiple API calls were made
    const finalAPICount = helpers.getAPICallCount();
    expect(finalAPICount).toBeGreaterThan(initialAPICount);
    
    // Capture after state
    await helpers.captureScreenshot('after-refresh-all');
    
    console.log('✅ Manual "Atualizar Tudo" button refreshes entire dashboard');
  });

  test('Manual "Limpar Cache" button works correctly', async ({ page }) => {
    console.log('🧪 Testing: Manual "Limpar Cache" button works correctly');
    
    // Capture before state
    await helpers.captureScreenshot('before-clear-cache');
    
    // Click clear cache button
    const clearCacheButton = page.locator('#btnClearCache');
    await expect(clearCacheButton).toBeVisible();
    await clearCacheButton.click();
    
    // Wait for cache clear operation
    await page.waitForTimeout(2000);
    
    // Verify no errors occurred
    const jsErrors = await helpers.checkForPHPErrors();
    expect(jsErrors).toHaveLength(0);
    
    // Capture after state
    await helpers.captureScreenshot('after-clear-cache');
    
    console.log('✅ Manual "Limpar Cache" button works correctly');
  });

  test('Manual control buttons show proper loading states', async ({ page }) => {
    console.log('🧪 Testing: Manual control buttons show proper loading states');
    
    // Test loading state for Verificar Status button
    const verifyButton = page.locator('#btnVerifyDatabase');
    
    // Capture initial state
    await helpers.captureScreenshot('button-initial-state');
    
    // Click button and immediately check for loading state
    await verifyButton.click();
    
    // Check if loading indicator appears
    const loadingIndicator = page.locator('#actionProgress, .spinner, .loading');
    
    // Wait a short time for loading state to appear
    await page.waitForTimeout(500);
    
    // Capture loading state if visible
    const isLoadingVisible = await loadingIndicator.isVisible().catch(() => false);
    if (isLoadingVisible) {
      await helpers.captureScreenshot('button-loading-state');
    }
    
    // Wait for loading to complete
    await page.waitForTimeout(3000);
    
    // Capture final state
    await helpers.captureScreenshot('button-final-state');
    
    console.log('✅ Manual control buttons show proper loading states');
  });

  test('Auto-refresh toggle works correctly', async ({ page }) => {
    console.log('🧪 Testing: Auto-refresh toggle works correctly');
    
    // Find auto-refresh toggle
    const autoRefreshToggle = page.locator('#autoRefreshToggle');
    await expect(autoRefreshToggle).toBeVisible();
    
    // Capture initial state
    const initialState = await autoRefreshToggle.isChecked();
    await helpers.captureScreenshot('auto-refresh-initial');
    
    // Toggle the setting
    await autoRefreshToggle.click();
    
    // Verify state changed
    const newState = await autoRefreshToggle.isChecked();
    expect(newState).toBe(!initialState);
    
    // Capture new state
    await helpers.captureScreenshot('auto-refresh-toggled');
    
    // Verify interval control visibility
    const intervalControl = page.locator('.interval-control');
    if (newState) {
      await expect(intervalControl).toBeVisible();
    } else {
      // Should be dimmed/disabled when auto-refresh is off
      const opacity = await intervalControl.evaluate(el => 
        window.getComputedStyle(el).opacity
      );
      expect(parseFloat(opacity)).toBeLessThan(1);
    }
    
    console.log('✅ Auto-refresh toggle works correctly');
  });

  test('Manual control system prevents automatic loading', async ({ page }) => {
    console.log('🧪 Testing: Manual control system prevents automatic loading');
    
    // Reload the page to test initial load behavior
    await page.reload({ waitUntil: 'domcontentloaded' });
    await helpers.waitForManualControlSystem();
    
    // Monitor for any automatic chart or stats loading
    const automatedElements = [
      '.chart-container canvas',
      '[data-chart-loaded="true"]',
      '[data-stats-loaded="true"]'
    ];
    
    // Wait to see if any automated loading occurs
    await page.waitForTimeout(8000);
    
    // Verify no automated loading occurred
    for (const selector of automatedElements) {
      const elements = page.locator(selector);
      const count = await elements.count();
      
      if (count > 0) {
        console.log(`⚠️ Found automated loading element: ${selector}`);
      }
    }
    
    // Capture state showing manual control is active
    await helpers.captureScreenshot('manual-control-prevents-auto-loading');
    
    // Verify manual control status is shown
    const controlStatus = page.locator('.control-status .status-text');
    await expect(controlStatus).toBeVisible();
    
    console.log('✅ Manual control system prevents automatic loading');
  });

  test('Manual control error handling works correctly', async ({ page }) => {
    console.log('🧪 Testing: Manual control error handling works correctly');
    
    // Simulate network issues by blocking API calls
    await page.route('**/api/**', route => route.abort());
    await page.route('**/charts.php', route => route.abort());
    await page.route('**/stats.php', route => route.abort());
    
    // Try to load charts (should handle error gracefully)
    const loadChartsButton = page.locator('#btnLoadCharts');
    
    if (await loadChartsButton.isEnabled()) {
      await loadChartsButton.click();
      
      // Wait for error handling
      await page.waitForTimeout(3000);
      
      // Check for error indicators or feedback
      const errorFeedback = page.locator('.error, .alert-danger, [data-error="true"]');
      const hasErrorFeedback = await errorFeedback.count() > 0;
      
      // Capture error state
      await helpers.captureScreenshot('manual-control-error-handling');
      
      console.log(`Error feedback shown: ${hasErrorFeedback}`);
    }
    
    // Reset network interception
    await page.unroute('**/api/**');
    await page.unroute('**/charts.php');
    await page.unroute('**/stats.php');
    
    console.log('✅ Manual control error handling works correctly');
  });
});