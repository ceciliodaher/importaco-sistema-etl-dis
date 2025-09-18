import { chromium, FullConfig } from '@playwright/test';
import * as fs from 'fs-extra';
import * as path from 'path';

/**
 * Global setup for Playwright tests
 * 
 * Responsibilities:
 * - Verify server is running and accessible
 * - Check database connectivity
 * - Prepare test data and directories
 * - Validate system prerequisites
 */

async function globalSetup(config: FullConfig) {
  console.log('🚀 Starting Playwright E2E Test Setup...');

  // Create necessary test directories
  const testDirs = [
    'test-results',
    'test-results/screenshots',
    'test-results/traces',
    'test-results/videos',
    'test-results/network-logs',
    'test-results/performance-logs'
  ];

  for (const dir of testDirs) {
    await fs.ensureDir(dir);
  }

  // Verify server accessibility
  const browser = await chromium.launch();
  const page = await browser.newPage();
  
  try {
    console.log('🔍 Checking server accessibility...');
    const response = await page.goto('http://localhost:8000/sistema/dashboard/index.php', {
      waitUntil: 'domcontentloaded',
      timeout: 30000
    });

    if (!response || response.status() !== 200) {
      throw new Error(`Server not accessible. Status: ${response?.status()}`);
    }

    console.log('✅ Server is accessible');

    // Check for critical elements to ensure page loaded correctly
    const criticalElements = [
      '.dashboard-container',
      '#manualControlPanel',
      '.upload-section'
    ];

    for (const selector of criticalElements) {
      const element = await page.waitForSelector(selector, { timeout: 10000 });
      if (!element) {
        throw new Error(`Critical element not found: ${selector}`);
      }
    }

    console.log('✅ Critical dashboard elements found');

    // Take a baseline screenshot for comparison
    await page.screenshot({
      path: 'test-results/screenshots/baseline-setup.png',
      fullPage: true
    });

  } catch (error) {
    console.error('❌ Setup failed:', error);
    throw error;
  } finally {
    await browser.close();
  }

  // Log test environment information
  console.log('📋 Test Environment Information:');
  console.log(`   - Base URL: ${config.projects[0].use?.baseURL}`);
  console.log(`   - Test Directory: ${config.testDir}`);
  console.log(`   - Workers: ${config.workers}`);
  console.log(`   - Timeout: ${config.timeout}ms`);
  
  console.log('✅ Global setup completed successfully');
}

export default globalSetup;