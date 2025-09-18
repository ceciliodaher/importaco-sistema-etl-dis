import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright Configuration for ETL DI Dashboard Testing
 * 
 * This configuration covers comprehensive testing scenarios including:
 * - Manual control system validation
 * - HTTP request monitoring
 * - Visual regression testing
 * - Real data processing validation
 * - Performance benchmarking
 */

export default defineConfig({
  testDir: './e2e',
  fullyParallel: false, // Sequential for better control flow testing
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 1,
  workers: process.env.CI ? 1 : 1, // Single worker for controlled testing
  
  // Global test configuration
  timeout: 60000, // 60 seconds per test
  expect: {
    timeout: 10000, // 10 seconds for assertions
    toHaveScreenshot: {
      mode: 'strict',
      threshold: 0.3,
      animations: 'disabled'
    }
  },

  // Output configuration
  reporter: [
    ['html', { outputFolder: 'playwright-report', open: 'never' }],
    ['json', { outputFile: 'test-results/results.json' }],
    ['junit', { outputFile: 'test-results/junit.xml' }],
    ['list']
  ],

  outputDir: 'test-results/',

  use: {
    // Base URL for testing
    baseURL: 'http://localhost:8000',
    
    // Browser context configuration
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    
    // Network and performance monitoring
    acceptDownloads: true,
    ignoreHTTPSErrors: true,
    
    // Viewport and device settings
    viewport: { width: 1920, height: 1080 },
    
    // Additional context options
    locale: 'pt-BR',
    timezoneId: 'America/Sao_Paulo',
    
    // Custom headers for testing
    extraHTTPHeaders: {
      'X-Test-Source': 'Playwright-E2E',
      'X-Test-Environment': 'Development'
    }
  },

  projects: [
    {
      name: 'chromium-desktop',
      use: { 
        ...devices['Desktop Chrome'],
        viewport: { width: 1920, height: 1080 }
      },
    },
    {
      name: 'firefox-desktop',
      use: { 
        ...devices['Desktop Firefox'],
        viewport: { width: 1920, height: 1080 }
      },
    },
    {
      name: 'webkit-desktop',
      use: { 
        ...devices['Desktop Safari'],
        viewport: { width: 1920, height: 1080 }
      },
    },
    {
      name: 'mobile-chrome',
      use: { 
        ...devices['Pixel 5'] 
      },
    }
  ],

  // Global setup and teardown
  globalSetup: require.resolve('./global-setup.ts'),
  globalTeardown: require.resolve('./global-teardown.ts'),

  // Web server configuration (if needed)
  webServer: {
    command: 'php -S localhost:8000 -t sistema/',
    url: 'http://localhost:8000',
    reuseExistingServer: !process.env.CI,
    timeout: 30000,
    cwd: '../'
  }
});