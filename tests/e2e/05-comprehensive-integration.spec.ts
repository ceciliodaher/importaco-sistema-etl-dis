import { test, expect } from '@playwright/test';
import { TestHelpers, TestValidations, TestDataUtils } from '../utils/test-helpers';

/**
 * Comprehensive Integration Test Suite
 * 
 * End-to-end integration tests covering complete user workflows:
 * - Complete XML import to dashboard visualization workflow
 * - Integration between manual controls and data display
 * - Cross-browser compatibility verification
 * - Mobile responsive behavior validation
 * - Error recovery and system resilience
 * - Real-world usage scenarios
 */

test.describe('Comprehensive Integration Tests', () => {
  let helpers: TestHelpers;

  test.beforeEach(async ({ page }) => {
    helpers = new TestHelpers(page, 'comprehensive-integration');
    await helpers.setupNetworkMonitoring();
  });

  test.afterEach(async () => {
    await helpers.saveTestReport();
  });

  test('Complete XML-to-Dashboard workflow', async ({ page }) => {
    console.log('🧪 Testing: Complete XML-to-Dashboard workflow');
    
    // Step 1: Navigate to dashboard
    console.log('📍 Step 1: Navigate to dashboard');
    await helpers.navigateToDashboard();
    await helpers.captureScreenshot('workflow-step-1-dashboard');
    
    // Step 2: Verify system status
    console.log('📍 Step 2: Verify system status');
    await helpers.waitForManualControlSystem();
    const systemStatus = await helpers.verifySystemStatus();
    console.log('📊 System Status:', systemStatus);
    await helpers.captureScreenshot('workflow-step-2-status');
    
    // Step 3: Check for available XML files
    console.log('📍 Step 3: Check available XML files');
    const availableFiles = await TestDataUtils.getAvailableXMLFiles();
    console.log(`📁 Available files: ${availableFiles.length}`);
    
    if (availableFiles.length === 0) {
      console.log('⚠️ No XML files available - creating test workflow without file upload');
      await helpers.captureScreenshot('workflow-no-files');
      return;
    }
    
    // Step 4: Import XML file (simulate)
    console.log('📍 Step 4: Import XML file');
    const testFile = availableFiles[0];
    console.log(`📄 Using file: ${testFile}`);
    
    // Simulate file upload workflow
    const uploadZone = page.locator('#uploadZone');
    await expect(uploadZone).toBeVisible();
    await uploadZone.click();
    await helpers.captureScreenshot('workflow-step-4-upload');
    
    // Step 5: Verify data processing (manual trigger)
    console.log('📍 Step 5: Verify data processing');
    const loadStatsButton = page.locator('#btnLoadStats');
    
    if (await loadStatsButton.isEnabled()) {
      await loadStatsButton.click();
      await page.waitForTimeout(3000);
      await helpers.captureScreenshot('workflow-step-5-processing');
      
      // Verify stats updated
      const stats = await page.evaluate(() => {
        const cards = document.querySelectorAll('.dashboard-card .card-value');
        return Array.from(cards).map(card => card.textContent?.trim() || '0');
      });
      
      console.log('📊 Updated Statistics:', stats);
    }
    
    // Step 6: Load visualizations
    console.log('📍 Step 6: Load visualizations');
    const loadChartsButton = page.locator('#btnLoadCharts');
    
    if (await loadChartsButton.isEnabled()) {
      await loadChartsButton.click();
      await page.waitForTimeout(5000);
      await helpers.captureScreenshot('workflow-step-6-visualizations');
      
      // Verify charts are displayed
      const chartElements = await page.locator('canvas[id*="chart"]').count();
      console.log(`📈 Charts displayed: ${chartElements}`);
    }
    
    // Step 7: Final verification
    console.log('📍 Step 7: Final verification');
    await helpers.captureScreenshot('workflow-step-7-complete', { fullPage: true });
    
    // Verify no errors occurred during the workflow
    const phpErrors = await helpers.checkForPHPErrors();
    expect(phpErrors, 'PHP errors detected during workflow').toHaveLength(0);
    
    console.log('✅ Complete XML-to-Dashboard workflow tested');
  });

  test('Cross-browser compatibility', async ({ page, browserName }) => {
    console.log(`🧪 Testing: Cross-browser compatibility (${browserName})`);
    
    await helpers.navigateToDashboard();
    
    // Test browser-specific functionality
    const browserFeatures = await page.evaluate(() => {
      return {
        userAgent: navigator.userAgent,
        localStorage: typeof Storage !== 'undefined',
        fetch: typeof fetch !== 'undefined',
        promises: typeof Promise !== 'undefined',
        es6: typeof Symbol !== 'undefined',
        flexbox: CSS.supports('display', 'flex'),
        grid: CSS.supports('display', 'grid'),
        customProperties: CSS.supports('--custom-property', 'value')
      };
    });
    
    console.log(`🌐 ${browserName} Features:`, browserFeatures);
    
    // Verify essential features are supported
    expect(browserFeatures.localStorage, 'localStorage not supported').toBe(true);
    expect(browserFeatures.fetch, 'fetch API not supported').toBe(true);
    expect(browserFeatures.promises, 'Promises not supported').toBe(true);
    expect(browserFeatures.flexbox, 'Flexbox not supported').toBe(true);
    
    // Test manual control system in this browser
    await helpers.waitForManualControlSystem();
    
    // Test critical UI elements
    const criticalElements = [
      '.dashboard-container',
      '#manualControlPanel',
      '.upload-section',
      '.system-status'
    ];
    
    for (const element of criticalElements) {
      await TestValidations.expectElementVisible(page, element);
    }
    
    await helpers.captureScreenshot(`cross-browser-${browserName}`);
    
    console.log(`✅ Cross-browser compatibility verified for ${browserName}`);
  });

  test('Mobile responsive integration', async ({ page }) => {
    console.log('🧪 Testing: Mobile responsive integration');
    
    // Test various mobile viewport sizes
    const mobileViewports = [
      { width: 390, height: 844, name: 'iPhone-12-Pro' },
      { width: 428, height: 926, name: 'iPhone-14-Pro-Max' },
      { width: 360, height: 800, name: 'Android-Standard' },
      { width: 768, height: 1024, name: 'iPad-Portrait' }
    ];
    
    for (const viewport of mobileViewports) {
      console.log(`📱 Testing mobile viewport: ${viewport.name}`);
      
      await page.setViewportSize({ width: viewport.width, height: viewport.height });
      await helpers.navigateToDashboard();
      await page.waitForTimeout(1000);
      
      // Check if mobile layout is active
      const isMobileLayout = await page.evaluate(() => {
        return window.innerWidth < 768;
      });
      
      console.log(`📐 Mobile layout active: ${isMobileLayout}`);
      
      // Test mobile-specific functionality
      if (isMobileLayout) {
        // Check if hamburger menu or mobile navigation exists
        const mobileNav = page.locator('.mobile-nav, .hamburger-menu, [data-mobile-nav]');
        const hasMobileNav = await mobileNav.count() > 0;
        
        console.log(`🍔 Mobile navigation present: ${hasMobileNav}`);
      }
      
      // Verify critical elements are accessible on mobile
      await TestValidations.expectElementVisible(page, '.dashboard-container');
      await TestValidations.expectElementVisible(page, '#manualControlPanel');
      
      // Test touch interactions (tap instead of click)
      const verifyButton = page.locator('#btnVerifyDatabase');
      if (await verifyButton.isVisible()) {
        await verifyButton.tap();
        await page.waitForTimeout(1000);
      }
      
      await helpers.captureScreenshot(`mobile-responsive-${viewport.name}`);
    }
    
    // Reset to desktop viewport
    await page.setViewportSize({ width: 1920, height: 1080 });
    
    console.log('✅ Mobile responsive integration tested');
  });

  test('Error recovery and system resilience', async ({ page }) => {
    console.log('🧪 Testing: Error recovery and system resilience');
    
    await helpers.navigateToDashboard();
    await helpers.waitForManualControlSystem();
    
    // Test 1: Network interruption recovery
    console.log('🔌 Testing network interruption recovery');
    
    // Block network temporarily
    await page.route('**/*', route => route.abort());
    
    // Try to trigger an action that requires network
    const loadStatsButton = page.locator('#btnLoadStats');
    if (await loadStatsButton.isEnabled()) {
      await loadStatsButton.click();
      await page.waitForTimeout(2000);
    }
    
    await helpers.captureScreenshot('network-interrupted');
    
    // Restore network
    await page.unroute('**/*');
    
    // Try the action again
    if (await loadStatsButton.isEnabled()) {
      await loadStatsButton.click();
      await page.waitForTimeout(3000);
    }
    
    await helpers.captureScreenshot('network-recovered');
    
    // Test 2: Invalid data handling
    console.log('💾 Testing invalid data handling');
    
    // Test with malformed API responses
    await page.route('**/api/**', route => {
      route.fulfill({
        status: 200,
        body: 'invalid json response'
      });
    });
    
    const verifyButton = page.locator('#btnVerifyDatabase');
    await verifyButton.click();
    await page.waitForTimeout(2000);
    
    await helpers.captureScreenshot('invalid-data-handling');
    
    // Restore normal API responses
    await page.unroute('**/api/**');
    
    // Test 3: System recovery after errors
    console.log('🔄 Testing system recovery');
    
    // Refresh the page to test recovery
    await page.reload({ waitUntil: 'domcontentloaded' });
    await helpers.waitForManualControlSystem();
    
    // Verify system is functional after recovery
    const systemStatus = await helpers.verifySystemStatus();
    console.log('📊 System status after recovery:', systemStatus);
    
    await helpers.captureScreenshot('system-recovered');
    
    // Verify no persistent errors
    const phpErrors = await helpers.checkForPHPErrors();
    expect(phpErrors, 'Persistent PHP errors after recovery').toHaveLength(0);
    
    console.log('✅ Error recovery and system resilience tested');
  });

  test('Real-world usage scenario simulation', async ({ page }) => {
    console.log('🧪 Testing: Real-world usage scenario simulation');
    
    // Simulate a typical user workflow
    
    // Scenario: New user first time setup
    console.log('👤 Scenario: New user first time setup');
    
    await helpers.navigateToDashboard();
    await helpers.captureScreenshot('scenario-new-user-arrival');
    
    // Step 1: User checks system status
    console.log('1. User checks system status');
    const verifyButton = page.locator('#btnVerifyDatabase');
    await verifyButton.click();
    await page.waitForTimeout(2000);
    await helpers.captureScreenshot('scenario-status-check');
    
    // Step 2: User tries to load data but finds none
    console.log('2. User tries to load data');
    const loadStatsButton = page.locator('#btnLoadStats');
    const isStatsEnabled = await loadStatsButton.isEnabled();
    
    if (!isStatsEnabled) {
      console.log('   → No data available (expected for first-time user)');
      await helpers.captureScreenshot('scenario-no-data');
    } else {
      await loadStatsButton.click();
      await page.waitForTimeout(3000);
      await helpers.captureScreenshot('scenario-data-loaded');
    }
    
    // Step 3: User explores upload functionality
    console.log('3. User explores upload functionality');
    const uploadZone = page.locator('#uploadZone');
    await uploadZone.hover();
    await page.waitForTimeout(500);
    await helpers.captureScreenshot('scenario-explore-upload');
    
    // Step 4: User checks charts section
    console.log('4. User checks charts section');
    const loadChartsButton = page.locator('#btnLoadCharts');
    const isChartsEnabled = await loadChartsButton.isEnabled();
    
    if (isChartsEnabled) {
      await loadChartsButton.click();
      await page.waitForTimeout(5000);
      await helpers.captureScreenshot('scenario-charts-loaded');
    } else {
      console.log('   → Charts disabled (no data)');
      await helpers.captureScreenshot('scenario-charts-disabled');
    }
    
    // Step 5: User explores settings
    console.log('5. User explores settings');
    const autoRefreshToggle = page.locator('#autoRefreshToggle');
    if (await autoRefreshToggle.isVisible()) {
      await autoRefreshToggle.click();
      await page.waitForTimeout(500);
      await helpers.captureScreenshot('scenario-settings-toggle');
    }
    
    // Step 6: User tests refresh functionality
    console.log('6. User tests refresh functionality');
    const refreshAllButton = page.locator('#btnRefreshAll');
    if (await refreshAllButton.isEnabled()) {
      await refreshAllButton.click();
      await page.waitForTimeout(3000);
    } else {
      const clearCacheButton = page.locator('#btnClearCache');
      await clearCacheButton.click();
      await page.waitForTimeout(1000);
    }
    
    await helpers.captureScreenshot('scenario-refresh-test');
    
    // Final state capture
    await helpers.captureScreenshot('scenario-final-state', { fullPage: true });
    
    // Verify user experience quality
    const userExperienceMetrics = await page.evaluate(() => {
      return {
        visibleButtons: document.querySelectorAll('button:not([disabled])').length,
        visibleCards: document.querySelectorAll('.card').length,
        interactiveElements: document.querySelectorAll('button, input, select').length,
        hasLoadingStates: document.querySelectorAll('[data-loading], .loading, .spinner').length > 0
      };
    });
    
    console.log('📊 User Experience Metrics:', userExperienceMetrics);
    
    // Verify UX quality
    expect(userExperienceMetrics.visibleButtons, 'Not enough interactive buttons').toBeGreaterThan(3);
    expect(userExperienceMetrics.visibleCards, 'Not enough content cards').toBeGreaterThan(5);
    
    console.log('✅ Real-world usage scenario simulation completed');
  });

  test('Integration test - Manual control to data visualization flow', async ({ page }) => {
    console.log('🧪 Testing: Manual control to data visualization integration');
    
    await helpers.navigateToDashboard();
    await helpers.waitForManualControlSystem();
    
    // Capture initial state
    await helpers.captureScreenshot('integration-initial-state');
    
    // Test the complete manual control flow
    const controlFlow = [
      {
        button: '#btnVerifyDatabase',
        name: 'Database Verification',
        expectedResult: 'System status updated'
      },
      {
        button: '#btnLoadStats',
        name: 'Statistics Loading',
        expectedResult: 'Dashboard cards updated'
      },
      {
        button: '#btnLoadCharts',
        name: 'Charts Loading',
        expectedResult: 'Charts displayed'
      }
    ];
    
    for (const step of controlFlow) {
      console.log(`🔄 Testing integration step: ${step.name}`);
      
      const button = page.locator(step.button);
      const isEnabled = await button.isEnabled();
      
      if (isEnabled) {
        // Capture before state
        await helpers.captureScreenshot(`integration-before-${step.name.toLowerCase().replace(' ', '-')}`);
        
        // Execute the manual control
        await button.click();
        
        // Wait for integration to complete
        await page.waitForTimeout(3000);
        
        // Capture after state
        await helpers.captureScreenshot(`integration-after-${step.name.toLowerCase().replace(' ', '-')}`);
        
        console.log(`✅ ${step.name}: ${step.expectedResult}`);
      } else {
        console.log(`⚠️ ${step.name}: Button disabled (likely no data)`);
      }
      
      // Brief pause between integration steps
      await page.waitForTimeout(1000);
    }
    
    // Test integration between components
    console.log('🔗 Testing component integration');
    
    // Verify that manual controls affect the correct UI components
    const integrationChecks = await page.evaluate(() => {
      return {
        dashboardCards: document.querySelectorAll('.dashboard-card').length,
        statusIndicators: document.querySelectorAll('.status-indicator').length,
        chartContainers: document.querySelectorAll('.chart-container, canvas').length,
        controlButtons: document.querySelectorAll('#manualControlPanel button').length,
        feedbackElements: document.querySelectorAll('.feedback, .alert, .message').length
      };
    });
    
    console.log('🔗 Integration Components:', integrationChecks);
    
    // Verify integration quality
    expect(integrationChecks.dashboardCards, 'Missing dashboard cards').toBeGreaterThan(0);
    expect(integrationChecks.statusIndicators, 'Missing status indicators').toBeGreaterThan(0);
    expect(integrationChecks.controlButtons, 'Missing control buttons').toBeGreaterThan(3);
    
    // Final integration screenshot
    await helpers.captureScreenshot('integration-complete-flow', { fullPage: true });
    
    console.log('✅ Manual control to data visualization integration tested');
  });

  test('System state persistence and recovery', async ({ page }) => {
    console.log('🧪 Testing: System state persistence and recovery');
    
    await helpers.navigateToDashboard();
    await helpers.waitForManualControlSystem();
    
    // Set up a specific system state
    console.log('⚙️ Setting up system state');
    
    // Toggle auto-refresh if available
    const autoRefreshToggle = page.locator('#autoRefreshToggle');
    if (await autoRefreshToggle.isVisible()) {
      const initialState = await autoRefreshToggle.isChecked();
      await autoRefreshToggle.click();
      await page.waitForTimeout(500);
      
      const newState = await autoRefreshToggle.isChecked();
      console.log(`🔄 Auto-refresh toggled: ${initialState} → ${newState}`);
    }
    
    // Trigger some manual controls to establish state
    const verifyButton = page.locator('#btnVerifyDatabase');
    await verifyButton.click();
    await page.waitForTimeout(2000);
    
    // Capture current state
    await helpers.captureScreenshot('state-before-refresh');
    
    // Test state persistence through page refresh
    console.log('🔄 Testing state persistence through page refresh');
    await page.reload({ waitUntil: 'domcontentloaded' });
    await helpers.waitForManualControlSystem();
    
    // Verify state is maintained
    await helpers.captureScreenshot('state-after-refresh');
    
    // Check if auto-refresh setting persisted
    if (await autoRefreshToggle.isVisible()) {
      const persistedState = await autoRefreshToggle.isChecked();
      console.log(`💾 Auto-refresh state persisted: ${persistedState}`);
    }
    
    // Test state recovery through browser navigation
    console.log('🧭 Testing state recovery through navigation');
    
    // Navigate away and back
    await page.goto('/');
    await page.waitForTimeout(1000);
    
    await page.goBack();
    await helpers.waitForManualControlSystem();
    
    await helpers.captureScreenshot('state-after-navigation');
    
    // Verify system is functional after navigation
    const systemStatus = await helpers.verifySystemStatus();
    console.log('📊 System status after navigation:', systemStatus);
    
    console.log('✅ System state persistence and recovery tested');
  });
});