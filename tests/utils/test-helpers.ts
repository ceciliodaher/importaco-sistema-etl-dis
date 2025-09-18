import { Page, expect, BrowserContext } from '@playwright/test';
import * as fs from 'fs-extra';
import * as path from 'path';

/**
 * Test Helper Utilities for ETL DI Dashboard Testing
 * 
 * Provides reusable functions for:
 * - HTTP request monitoring and logging
 * - Screenshot capture with metadata
 * - Performance measurement
 * - UI state validation
 * - Test data management
 */

export interface NetworkLog {
  url: string;
  method: string;
  status: number;
  responseTime: number;
  timestamp: string;
  headers: Record<string, string>;
  isAPI: boolean;
}

export interface PerformanceMetrics {
  loadTime: number;
  domContentLoaded: number;
  networkRequests: number;
  jsErrors: string[];
  timestamp: string;
}

export interface TestContext {
  testName: string;
  networkLogs: NetworkLog[];
  performanceMetrics: PerformanceMetrics;
  screenshots: string[];
}

export class TestHelpers {
  private context: TestContext;
  private page: Page;

  constructor(page: Page, testName: string) {
    this.page = page;
    this.context = {
      testName: testName.replace(/[^a-zA-Z0-9]/g, '-'),
      networkLogs: [],
      performanceMetrics: {
        loadTime: 0,
        domContentLoaded: 0,
        networkRequests: 0,
        jsErrors: [],
        timestamp: new Date().toISOString()
      },
      screenshots: []
    };
  }

  /**
   * Initialize HTTP request monitoring
   */
  async setupNetworkMonitoring(): Promise<void> {
    this.page.on('request', request => {
      const startTime = Date.now();
      
      request.response().then(response => {
        if (response) {
          const endTime = Date.now();
          const log: NetworkLog = {
            url: request.url(),
            method: request.method(),
            status: response.status(),
            responseTime: endTime - startTime,
            timestamp: new Date().toISOString(),
            headers: request.headers(),
            isAPI: this.isAPICall(request.url())
          };
          
          this.context.networkLogs.push(log);
          
          // Log API calls for debugging
          if (log.isAPI) {
            console.log(`🌐 API Call: ${log.method} ${log.url} - ${log.status} (${log.responseTime}ms)`);
          }
        }
      }).catch(() => {
        // Handle failed requests
        const log: NetworkLog = {
          url: request.url(),
          method: request.method(),
          status: 0,
          responseTime: 0,
          timestamp: new Date().toISOString(),
          headers: request.headers(),
          isAPI: this.isAPICall(request.url())
        };
        
        this.context.networkLogs.push(log);
      });
    });

    // Monitor JavaScript errors
    this.page.on('pageerror', error => {
      this.context.performanceMetrics.jsErrors.push(error.message);
      console.log(`❌ JS Error: ${error.message}`);
    });

    // Monitor console errors
    this.page.on('console', msg => {
      if (msg.type() === 'error') {
        this.context.performanceMetrics.jsErrors.push(msg.text());
        console.log(`⚠️ Console Error: ${msg.text()}`);
      }
    });
  }

  /**
   * Check if URL is an API call
   */
  private isAPICall(url: string): boolean {
    const apiPatterns = [
      '/api/',
      '/charts.php',
      '/stats.php',
      '/upload.php',
      '/database-export.php',
      '/database-cleanup.php'
    ];
    
    return apiPatterns.some(pattern => url.includes(pattern));
  }

  /**
   * Navigate to dashboard and wait for it to load completely
   */
  async navigateToDashboard(): Promise<void> {
    const startTime = Date.now();
    
    const response = await this.page.goto('/sistema/dashboard/index.php', {
      waitUntil: 'domcontentloaded',
      timeout: 30000
    });

    const endTime = Date.now();
    this.context.performanceMetrics.loadTime = endTime - startTime;
    this.context.performanceMetrics.domContentLoaded = endTime - startTime;

    if (!response || response.status() !== 200) {
      throw new Error(`Dashboard navigation failed. Status: ${response?.status()}`);
    }

    // Wait for critical elements
    await this.page.waitForSelector('.dashboard-container', { timeout: 10000 });
    await this.page.waitForSelector('#manualControlPanel', { timeout: 10000 });
    
    console.log(`✅ Dashboard loaded in ${this.context.performanceMetrics.loadTime}ms`);
  }

  /**
   * Capture screenshot with metadata
   */
  async captureScreenshot(name: string, options: { fullPage?: boolean; clip?: any } = {}): Promise<string> {
    const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
    const filename = `${this.context.testName}-${name}-${timestamp}.png`;
    const filepath = `test-results/screenshots/${filename}`;
    
    await this.page.screenshot({
      path: filepath,
      fullPage: options.fullPage || true,
      clip: options.clip,
      animations: 'disabled'
    });
    
    this.context.screenshots.push(filepath);
    console.log(`📸 Screenshot captured: ${filename}`);
    
    return filepath;
  }

  /**
   * Wait for manual control system to be ready
   */
  async waitForManualControlSystem(timeout: number = 10000): Promise<void> {
    await this.page.waitForFunction(() => {
      return window.manualControlSystem !== undefined;
    }, { timeout });
    
    console.log('✅ Manual control system detected');
  }

  /**
   * Verify no automatic API calls on page load
   */
  async verifyNoAutomaticAPICalls(waitTime: number = 5000): Promise<void> {
    const initialAPICallCount = this.getAPICallCount();
    
    // Wait and check for any automatic API calls
    await this.page.waitForTimeout(waitTime);
    
    const finalAPICallCount = this.getAPICallCount();
    const automaticCalls = finalAPICallCount - initialAPICallCount;
    
    if (automaticCalls > 0) {
      const recentAPICalls = this.context.networkLogs
        .filter(log => log.isAPI)
        .slice(-automaticCalls)
        .map(log => `${log.method} ${log.url}`)
        .join(', ');
      
      throw new Error(`Detected ${automaticCalls} automatic API calls: ${recentAPICalls}`);
    }
    
    console.log('✅ No automatic API calls detected');
  }

  /**
   * Get count of API calls made so far
   */
  getAPICallCount(): number {
    return this.context.networkLogs.filter(log => log.isAPI).length;
  }

  /**
   * Click manual control button and verify API call
   */
  async clickManualControlButton(buttonId: string, expectedAPIPattern: string): Promise<void> {
    const initialAPICount = this.getAPICallCount();
    
    // Click the button
    await this.page.click(`#${buttonId}`);
    console.log(`🖱️ Clicked manual control button: ${buttonId}`);
    
    // Wait for API call to complete
    await this.page.waitForFunction((count) => {
      return window.manualControlSystem && 
             document.querySelectorAll('[data-network-log]').length > count;
    }, initialAPICount, { timeout: 10000 });
    
    // Verify the API call was made
    const newAPICall = this.context.networkLogs
      .filter(log => log.isAPI)
      .slice(-1)[0];
    
    if (!newAPICall || !newAPICall.url.includes(expectedAPIPattern)) {
      throw new Error(`Expected API call matching "${expectedAPIPattern}" not found`);
    }
    
    console.log(`✅ Manual control triggered API: ${newAPICall.url}`);
  }

  /**
   * Verify system status indicators
   */
  async verifySystemStatus(): Promise<{ database: string; schema: string; uploads: string; processed: string }> {
    const status = {
      database: await this.page.textContent('[data-indicator="database-status"] .status-label') || 'Unknown',
      schema: await this.page.textContent('[data-indicator="schema-status"] .status-label') || 'Unknown',
      uploads: await this.page.textContent('[data-indicator="upload-dir-status"] .status-label') || 'Unknown',
      processed: await this.page.textContent('[data-indicator="processed-dir-status"] .status-label') || 'Unknown'
    };
    
    console.log('📊 System Status:', status);
    return status;
  }

  /**
   * Check for Fatal PHP errors in page content
   */
  async checkForPHPErrors(): Promise<string[]> {
    const pageContent = await this.page.content();
    const errors: string[] = [];
    
    const errorPatterns = [
      /Fatal error:/gi,
      /Parse error:/gi,
      /Warning:/gi,
      /Notice:/gi,
      /Call to undefined function/gi,
      /include.*failed to open stream/gi,
      /require.*failed to open stream/gi
    ];
    
    for (const pattern of errorPatterns) {
      const matches = pageContent.match(pattern);
      if (matches) {
        errors.push(...matches);
      }
    }
    
    if (errors.length > 0) {
      console.log('❌ PHP Errors detected:', errors);
    } else {
      console.log('✅ No PHP errors detected');
    }
    
    return errors;
  }

  /**
   * Save comprehensive test report
   */
  async saveTestReport(): Promise<string> {
    const report = {
      testName: this.context.testName,
      timestamp: new Date().toISOString(),
      performanceMetrics: this.context.performanceMetrics,
      networkLogs: this.context.networkLogs,
      screenshots: this.context.screenshots,
      summary: {
        totalRequests: this.context.networkLogs.length,
        apiCalls: this.context.networkLogs.filter(log => log.isAPI).length,
        failedRequests: this.context.networkLogs.filter(log => log.status >= 400).length,
        averageResponseTime: this.calculateAverageResponseTime(),
        jsErrorCount: this.context.performanceMetrics.jsErrors.length
      }
    };
    
    const reportPath = `test-results/${this.context.testName}-report.json`;
    await fs.writeJson(reportPath, report, { spaces: 2 });
    
    console.log(`📋 Test report saved: ${reportPath}`);
    return reportPath;
  }

  /**
   * Calculate average response time for API calls
   */
  private calculateAverageResponseTime(): number {
    const apiCalls = this.context.networkLogs.filter(log => log.isAPI && log.responseTime > 0);
    if (apiCalls.length === 0) return 0;
    
    const totalTime = apiCalls.reduce((sum, log) => sum + log.responseTime, 0);
    return Math.round(totalTime / apiCalls.length);
  }

  /**
   * Get test context for debugging
   */
  getTestContext(): TestContext {
    return this.context;
  }
}

/**
 * Common test expectations and validations
 */
export class TestValidations {
  
  static async expectElementVisible(page: Page, selector: string, timeout: number = 5000): Promise<void> {
    await expect(page.locator(selector)).toBeVisible({ timeout });
  }

  static async expectElementHidden(page: Page, selector: string, timeout: number = 5000): Promise<void> {
    await expect(page.locator(selector)).toBeHidden({ timeout });
  }

  static async expectTextContent(page: Page, selector: string, expectedText: string | RegExp): Promise<void> {
    await expect(page.locator(selector)).toContainText(expectedText);
  }

  static async expectNoConsoleErrors(page: Page): Promise<void> {
    const errors: string[] = [];
    
    page.on('console', msg => {
      if (msg.type() === 'error') {
        errors.push(msg.text());
      }
    });
    
    // Wait a bit to collect any errors
    await page.waitForTimeout(1000);
    
    if (errors.length > 0) {
      throw new Error(`Console errors detected: ${errors.join(', ')}`);
    }
  }

  static async expectResponseStatus(page: Page, urlPattern: string, expectedStatus: number): Promise<void> {
    const response = await page.waitForResponse(response => 
      response.url().includes(urlPattern) && response.status() === expectedStatus
    );
    
    expect(response.status()).toBe(expectedStatus);
  }
}

/**
 * Test data utilities
 */
export class TestDataUtils {
  
  static async getAvailableXMLFiles(): Promise<string[]> {
    const uploadsDir = '../sistema/data/uploads';
    const files = await fs.readdir(uploadsDir);
    return files.filter(file => file.endsWith('.xml'));
  }

  static async createTestXMLFile(filename: string, content: string): Promise<string> {
    const filepath = `../sistema/data/uploads/${filename}`;
    await fs.writeFile(filepath, content);
    return filepath;
  }

  static async cleanupTestFiles(): Promise<void> {
    const testFiles = await fs.readdir('../sistema/data/uploads');
    const testFilePattern = /^test-.*\.xml$/;
    
    for (const file of testFiles) {
      if (testFilePattern.test(file)) {
        await fs.remove(`../sistema/data/uploads/${file}`);
      }
    }
  }
}