import { test, expect } from '@playwright/test';
import { TestHelpers, TestValidations } from '../utils/test-helpers';

/**
 * Dashboard Load Validation Test Suite
 * 
 * Validates that the dashboard loads correctly after the syntax error fixes:
 * - No Fatal PHP errors (like those shown in the PDF)
 * - All critical components render properly
 * - Manual control panel displays correctly
 * - No automatic API calls on page load
 * - System status indicators work correctly
 */

test.describe('Dashboard Load Validation', () => {
  let helpers: TestHelpers;

  test.beforeEach(async ({ page }) => {
    helpers = new TestHelpers(page, 'dashboard-load');
    await helpers.setupNetworkMonitoring();
  });

  test.afterEach(async () => {
    await helpers.saveTestReport();
  });

  test('Dashboard loads without Fatal PHP errors', async ({ page }) => {
    console.log('🧪 Testing: Dashboard loads without Fatal PHP errors');
    
    // Capture before screenshot
    await helpers.captureScreenshot('before-navigation');
    
    // Navigate to dashboard
    await helpers.navigateToDashboard();
    
    // Capture after screenshot
    await helpers.captureScreenshot('after-navigation');
    
    // Check for PHP errors that were present in the PDF
    const phpErrors = await helpers.checkForPHPErrors();
    
    expect(phpErrors, `PHP errors detected: ${phpErrors.join(', ')}`).toHaveLength(0);
    
    // Verify page loaded successfully
    await TestValidations.expectElementVisible(page, '.dashboard-container');
    
    console.log('✅ Dashboard loaded without PHP errors');
  });

  test('Manual control panel displays correctly', async ({ page }) => {
    console.log('🧪 Testing: Manual control panel displays correctly');
    
    await helpers.navigateToDashboard();
    
    // Verify manual control panel is visible
    await TestValidations.expectElementVisible(page, '#manualControlPanel');
    
    // Check for required sections
    const requiredSections = [
      '.control-header',
      '.system-overview',
      '.control-sections',
      '.data-management',
      '.visualizations',
      '.settings'
    ];
    
    for (const section of requiredSections) {
      await TestValidations.expectElementVisible(page, section);
    }
    
    // Capture screenshot of manual control panel
    await helpers.captureScreenshot('manual-control-panel');
    
    // Verify control buttons are present
    const controlButtons = [
      '#btnImportXML',
      '#btnVerifyDatabase', 
      '#btnClearCache',
      '#btnLoadCharts',
      '#btnLoadStats',
      '#btnRefreshAll'
    ];
    
    for (const button of controlButtons) {
      await TestValidations.expectElementVisible(page, button);
    }
    
    console.log('✅ Manual control panel displays correctly');
  });

  test('No automatic API calls on page load', async ({ page }) => {
    console.log('🧪 Testing: No automatic API calls on page load');
    
    // Track initial network activity
    const initialNetworkCount = helpers.getAPICallCount();
    
    // Navigate to dashboard
    await helpers.navigateToDashboard();
    
    // Wait for any potential automatic calls and verify none occurred
    await helpers.verifyNoAutomaticAPICalls(8000); // Wait 8 seconds
    
    // Capture screenshot showing manual control state
    await helpers.captureScreenshot('no-automatic-calls');
    
    const finalNetworkCount = helpers.getAPICallCount();
    const apiCallsMade = finalNetworkCount - initialNetworkCount;
    
    expect(apiCallsMade, `Unexpected automatic API calls detected`).toBe(0);
    
    console.log('✅ No automatic API calls detected on page load');
  });

  test('Critical dashboard elements are present', async ({ page }) => {
    console.log('🧪 Testing: Critical dashboard elements are present');
    
    await helpers.navigateToDashboard();
    
    // Define critical elements that must be present
    const criticalElements = [
      // Main structure
      '.dashboard-container',
      '.sidebar',
      '.main-content',
      
      // Manual control panel (the fix target)
      '#manualControlPanel',
      
      // Upload functionality
      '.upload-section',
      '#uploadZone',
      
      // Dashboard cards
      '.dashboard-cards',
      
      // System status
      '.system-status',
      
      // Charts section
      '.charts-section'
    ];
    
    // Check each critical element
    for (const element of criticalElements) {
      try {
        await TestValidations.expectElementVisible(page, element, 5000);
        console.log(`✓ Found: ${element}`);
      } catch (error) {
        console.log(`✗ Missing: ${element}`);
        throw error;
      }
    }
    
    // Capture full page screenshot
    await helpers.captureScreenshot('critical-elements-check', { fullPage: true });
    
    console.log('✅ All critical dashboard elements are present');
  });

  test('System status indicators function correctly', async ({ page }) => {
    console.log('🧪 Testing: System status indicators function correctly');
    
    await helpers.navigateToDashboard();
    
    // Verify system status
    const systemStatus = await helpers.verifySystemStatus();
    
    // Check that status indicators have valid values
    expect(systemStatus.database).toMatch(/^(Online|Offline|Unknown)$/);
    expect(systemStatus.schema).toMatch(/^(Pronto|Pendente|Unknown)$/);
    expect(systemStatus.uploads).toMatch(/^(OK|Erro|Unknown)$/);
    expect(systemStatus.processed).toMatch(/^(OK|Erro|Unknown)$/);
    
    // Capture screenshot of status section
    await helpers.captureScreenshot('system-status-indicators');
    
    console.log('✅ System status indicators function correctly');
  });

  test('Manual control system initializes properly', async ({ page }) => {
    console.log('🧪 Testing: Manual control system initializes properly');
    
    await helpers.navigateToDashboard();
    
    // Wait for manual control system to be available
    await helpers.waitForManualControlSystem();
    
    // Verify manual control system state
    const manualControlState = await page.evaluate(() => {
      return {
        available: typeof window.manualControlSystem !== 'undefined',
        hasState: window.manualControlSystem?.getState !== undefined,
        hasHandlers: window.manualControlSystem?.handleLoadStats !== undefined
      };
    });
    
    expect(manualControlState.available).toBe(true);
    expect(manualControlState.hasState).toBe(true);
    expect(manualControlState.hasHandlers).toBe(true);
    
    // Capture screenshot showing initialized state
    await helpers.captureScreenshot('manual-control-initialized');
    
    console.log('✅ Manual control system initializes properly');
  });

  test('No JavaScript console errors on load', async ({ page }) => {
    console.log('🧪 Testing: No JavaScript console errors on load');
    
    const jsErrors: string[] = [];
    
    // Monitor console errors
    page.on('console', msg => {
      if (msg.type() === 'error') {
        jsErrors.push(msg.text());
      }
    });
    
    page.on('pageerror', error => {
      jsErrors.push(error.message);
    });
    
    // Navigate and wait for page to settle
    await helpers.navigateToDashboard();
    await page.waitForTimeout(3000);
    
    // Capture screenshot for error context if any
    if (jsErrors.length > 0) {
      await helpers.captureScreenshot('js-errors-context');
    } else {
      await helpers.captureScreenshot('no-js-errors');
    }
    
    // Verify no console errors
    expect(jsErrors, `JavaScript errors detected: ${jsErrors.join(', ')}`).toHaveLength(0);
    
    console.log('✅ No JavaScript console errors detected');
  });

  test('Dashboard responsive design works correctly', async ({ page }) => {
    console.log('🧪 Testing: Dashboard responsive design works correctly');
    
    // Test different viewport sizes
    const viewports = [
      { width: 1920, height: 1080, name: 'desktop-large' },
      { width: 1366, height: 768, name: 'desktop-medium' },
      { width: 768, height: 1024, name: 'tablet' },
      { width: 390, height: 844, name: 'mobile' }
    ];
    
    for (const viewport of viewports) {
      await page.setViewportSize({ width: viewport.width, height: viewport.height });
      await helpers.navigateToDashboard();
      
      // Wait for layout to settle
      await page.waitForTimeout(1000);
      
      // Capture screenshot for this viewport
      await helpers.captureScreenshot(`responsive-${viewport.name}`);
      
      // Verify critical elements are still visible
      await TestValidations.expectElementVisible(page, '.dashboard-container');
      await TestValidations.expectElementVisible(page, '#manualControlPanel');
      
      console.log(`✓ Responsive design works for ${viewport.name}`);
    }
    
    console.log('✅ Dashboard responsive design works correctly');
  });
});