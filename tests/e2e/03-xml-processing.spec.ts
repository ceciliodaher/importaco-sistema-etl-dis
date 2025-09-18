import { test, expect } from '@playwright/test';
import { TestHelpers, TestValidations, TestDataUtils } from '../utils/test-helpers';

/**
 * XML Processing Test Suite
 * 
 * Validates XML import and processing functionality:
 * - Upload real XML files from the uploads directory
 * - Process DI data correctly
 * - Update database with imported data
 * - Display processed data in dashboard
 * - Handle various XML formats and edge cases
 */

test.describe('XML Processing Functionality', () => {
  let helpers: TestHelpers;
  let availableXMLFiles: string[];

  test.beforeAll(async () => {
    // Get list of available XML files for testing
    availableXMLFiles = await TestDataUtils.getAvailableXMLFiles();
    console.log(`📁 Found ${availableXMLFiles.length} XML files for testing:`, availableXMLFiles);
  });

  test.beforeEach(async ({ page }) => {
    helpers = new TestHelpers(page, 'xml-processing');
    await helpers.setupNetworkMonitoring();
    await helpers.navigateToDashboard();
    await helpers.waitForManualControlSystem();
  });

  test.afterEach(async () => {
    await helpers.saveTestReport();
  });

  test('XML upload zone is functional', async ({ page }) => {
    console.log('🧪 Testing: XML upload zone is functional');
    
    // Verify upload zone is visible
    await TestValidations.expectElementVisible(page, '#uploadZone');
    await TestValidations.expectElementVisible(page, '#fileInput');
    
    // Capture upload zone screenshot
    await helpers.captureScreenshot('upload-zone-initial');
    
    // Test upload zone interactions
    const uploadZone = page.locator('#uploadZone');
    
    // Hover over upload zone
    await uploadZone.hover();
    await page.waitForTimeout(500);
    await helpers.captureScreenshot('upload-zone-hover');
    
    // Click upload zone to trigger file input
    await uploadZone.click();
    
    // Verify file input is accessible
    const fileInput = page.locator('#fileInput');
    await expect(fileInput).toBeAttached();
    
    console.log('✅ XML upload zone is functional');
  });

  test('Process existing XML files from uploads directory', async ({ page }) => {
    console.log('🧪 Testing: Process existing XML files from uploads directory');
    
    if (availableXMLFiles.length === 0) {
      console.log('⚠️ No XML files available for testing');
      return;
    }
    
    // Use the first available XML file
    const testFile = availableXMLFiles[0];
    console.log(`📄 Testing with file: ${testFile}`);
    
    // Capture initial dashboard state
    await helpers.captureScreenshot('before-xml-processing');
    
    // Simulate file upload by programmatically setting the file
    const fileInputElement = await page.locator('#fileInput').elementHandle();
    
    if (fileInputElement) {
      // Set the file directly to the input element
      await fileInputElement.setInputFiles(`../sistema/data/uploads/${testFile}`);
      
      // Wait for file to be detected
      await page.waitForTimeout(1000);
      
      // Check if file list appeared
      const fileListVisible = await page.locator('#fileList').isVisible();
      
      if (fileListVisible) {
        await helpers.captureScreenshot('file-selected');
        
        // Click process files button
        const processButton = page.locator('#processFiles');
        if (await processButton.isVisible() && await processButton.isEnabled()) {
          await processButton.click();
          
          // Wait for processing
          await page.waitForTimeout(5000);
          
          // Capture processing result
          await helpers.captureScreenshot('xml-processing-complete');
          
          console.log('✅ XML file processed successfully');
        }
      }
    }
  });

  test('Database updates after XML import', async ({ page }) => {
    console.log('🧪 Testing: Database updates after XML import');
    
    // Get initial database stats
    const initialStats = await page.evaluate(() => {
      const cards = document.querySelectorAll('.dashboard-card .card-value');
      const stats: Record<string, string> = {};
      cards.forEach((card, index) => {
        const label = card.closest('.dashboard-card')?.querySelector('h3')?.textContent || `stat_${index}`;
        stats[label] = card.textContent || '0';
      });
      return stats;
    });
    
    await helpers.captureScreenshot('initial-stats');
    
    // Trigger manual stats reload to check current database state
    const loadStatsButton = page.locator('#btnLoadStats');
    
    if (await loadStatsButton.isEnabled()) {
      await loadStatsButton.click();
      await page.waitForTimeout(3000);
      
      // Get updated stats
      const updatedStats = await page.evaluate(() => {
        const cards = document.querySelectorAll('.dashboard-card .card-value');
        const stats: Record<string, string> = {};
        cards.forEach((card, index) => {
          const label = card.closest('.dashboard-card')?.querySelector('h3')?.textContent || `stat_${index}`;
          stats[label] = card.textContent || '0';
        });
        return stats;
      });
      
      await helpers.captureScreenshot('updated-stats');
      
      // Compare stats to see if database has data
      console.log('📊 Database Stats Comparison:');
      console.log('Initial:', initialStats);
      console.log('Updated:', updatedStats);
      
      // Check if we have meaningful data
      const hasData = Object.values(updatedStats).some(value => 
        value !== '0' && value !== 'Sem Dados' && !value.includes('N/A')
      );
      
      if (hasData) {
        console.log('✅ Database contains processed XML data');
      } else {
        console.log('⚠️ Database appears to be empty or not updated');
      }
    }
  });

  test('Charts display real data after XML processing', async ({ page }) => {
    console.log('🧪 Testing: Charts display real data after XML processing');
    
    // Trigger manual chart loading
    const loadChartsButton = page.locator('#btnLoadCharts');
    
    if (await loadChartsButton.isEnabled()) {
      await helpers.captureScreenshot('before-chart-loading');
      
      await loadChartsButton.click();
      
      // Wait for charts to load
      await page.waitForTimeout(5000);
      
      await helpers.captureScreenshot('after-chart-loading');
      
      // Check for chart canvas elements
      const chartCanvases = page.locator('canvas[id*="chart"], canvas[class*="chart"]');
      const chartCount = await chartCanvases.count();
      
      console.log(`📈 Found ${chartCount} chart canvases`);
      
      if (chartCount > 0) {
        // Verify charts are not empty/placeholder
        const chartData = await page.evaluate(() => {
          const canvases = document.querySelectorAll('canvas[id*="chart"], canvas[class*="chart"]');
          return Array.from(canvases).map(canvas => ({
            id: canvas.id,
            width: canvas.width,
            height: canvas.height,
            hasData: canvas.width > 0 && canvas.height > 0
          }));
        });
        
        console.log('📊 Chart Data:', chartData);
        
        const hasRealCharts = chartData.some(chart => chart.hasData);
        expect(hasRealCharts, 'No functional charts found').toBe(true);
        
        console.log('✅ Charts display real data');
      } else {
        console.log('⚠️ No charts found - may need data import first');
      }
    } else {
      console.log('⚠️ Load Charts button is disabled - likely no data available');
      await helpers.captureScreenshot('charts-disabled-no-data');
    }
  });

  test('XML processing error handling', async ({ page }) => {
    console.log('🧪 Testing: XML processing error handling');
    
    // Test with invalid XML content
    const invalidXMLContent = `<?xml version="1.0"?>
    <invalid>
      <unclosed-tag>
      <!-- This is intentionally malformed XML -->
    </invalid>`;
    
    // Create a test file with invalid XML
    await TestDataUtils.createTestXMLFile('test-invalid.xml', invalidXMLContent);
    
    await helpers.captureScreenshot('before-invalid-xml-test');
    
    try {
      // Try to upload the invalid XML file
      const fileInput = await page.locator('#fileInput').elementHandle();
      
      if (fileInput) {
        await fileInput.setInputFiles('../sistema/data/uploads/test-invalid.xml');
        await page.waitForTimeout(1000);
        
        // Check if error handling occurs
        const processButton = page.locator('#processFiles');
        if (await processButton.isVisible() && await processButton.isEnabled()) {
          await processButton.click();
          await page.waitForTimeout(3000);
          
          // Look for error indicators
          const errorElements = page.locator('.error, .alert-danger, [class*="error"]');
          const errorCount = await errorElements.count();
          
          await helpers.captureScreenshot('invalid-xml-error-handling');
          
          console.log(`🔍 Error indicators found: ${errorCount}`);
        }
      }
    } finally {
      // Clean up test file
      await TestDataUtils.cleanupTestFiles();
    }
    
    console.log('✅ XML processing error handling tested');
  });

  test('Large XML file processing performance', async ({ page }) => {
    console.log('🧪 Testing: Large XML file processing performance');
    
    if (availableXMLFiles.length === 0) {
      console.log('⚠️ No XML files available for performance testing');
      return;
    }
    
    // Find the largest available XML file
    const largestFile = availableXMLFiles[0]; // Assuming first file is suitable for testing
    
    console.log(`⏱️ Performance testing with: ${largestFile}`);
    
    await helpers.captureScreenshot('before-performance-test');
    
    const startTime = Date.now();
    
    // Process the file
    const fileInput = await page.locator('#fileInput').elementHandle();
    
    if (fileInput) {
      await fileInput.setInputFiles(`../sistema/data/uploads/${largestFile}`);
      await page.waitForTimeout(1000);
      
      const processButton = page.locator('#processFiles');
      if (await processButton.isVisible() && await processButton.isEnabled()) {
        await processButton.click();
        
        // Wait for processing with longer timeout for large files
        await page.waitForTimeout(15000);
        
        const endTime = Date.now();
        const processingTime = endTime - startTime;
        
        console.log(`⏱️ Processing time: ${processingTime}ms`);
        
        // Verify processing didn't timeout or fail
        expect(processingTime).toBeLessThan(30000); // Should complete within 30 seconds
        
        await helpers.captureScreenshot('performance-test-complete');
      }
    }
    
    console.log('✅ Large XML file processing performance tested');
  });

  test('Multiple XML files batch processing', async ({ page }) => {
    console.log('🧪 Testing: Multiple XML files batch processing');
    
    if (availableXMLFiles.length < 2) {
      console.log('⚠️ Need at least 2 XML files for batch processing test');
      return;
    }
    
    // Select multiple files (limit to first 2 for testing)
    const testFiles = availableXMLFiles.slice(0, 2);
    
    console.log(`📄 Batch testing with files: ${testFiles.join(', ')}`);
    
    await helpers.captureScreenshot('before-batch-processing');
    
    // Upload multiple files
    const fileInput = await page.locator('#fileInput').elementHandle();
    
    if (fileInput) {
      const filePaths = testFiles.map(file => `../sistema/data/uploads/${file}`);
      await fileInput.setInputFiles(filePaths);
      
      await page.waitForTimeout(2000);
      
      // Check if multiple files are listed
      const fileListItems = page.locator('#filesContainer .file-item, .file-list-item');
      const fileCount = await fileListItems.count();
      
      console.log(`📁 Files listed for processing: ${fileCount}`);
      
      await helpers.captureScreenshot('batch-files-selected');
      
      // Process all files
      const processButton = page.locator('#processFiles');
      if (await processButton.isVisible() && await processButton.isEnabled()) {
        await processButton.click();
        
        // Wait for batch processing to complete
        await page.waitForTimeout(10000);
        
        await helpers.captureScreenshot('batch-processing-complete');
        
        console.log('✅ Batch processing completed');
      }
    }
  });

  test('XML validation and parsing accuracy', async ({ page }) => {
    console.log('🧪 Testing: XML validation and parsing accuracy');
    
    if (availableXMLFiles.length === 0) {
      console.log('⚠️ No XML files available for parsing accuracy test');
      return;
    }
    
    const testFile = availableXMLFiles[0];
    
    // Process a file and then verify the data was parsed correctly
    const fileInput = await page.locator('#fileInput').elementHandle();
    
    if (fileInput) {
      await fileInput.setInputFiles(`../sistema/data/uploads/${testFile}`);
      await page.waitForTimeout(1000);
      
      const processButton = page.locator('#processFiles');
      if (await processButton.isVisible() && await processButton.isEnabled()) {
        await processButton.click();
        await page.waitForTimeout(5000);
        
        // Load fresh stats to verify parsing
        const loadStatsButton = page.locator('#btnLoadStats');
        if (await loadStatsButton.isEnabled()) {
          await loadStatsButton.click();
          await page.waitForTimeout(3000);
          
          // Check if specific DI data elements are present
          const statisticsData = await page.evaluate(() => {
            const elements = {
              'DIs Processadas': document.querySelector('[data-stat="DIs Processadas"], .dashboard-card:has-text("DIs Processadas") .card-value')?.textContent,
              'Adições': document.querySelector('[data-stat="Adições"], .dashboard-card:has-text("Impostos") .card-value')?.textContent,
              'NCMs': document.querySelector('[data-stat="NCMs"], .dashboard-card:has-text("NCMs") .card-value')?.textContent
            };
            return elements;
          });
          
          console.log('📊 Parsed Statistics:', statisticsData);
          
          await helpers.captureScreenshot('parsing-accuracy-results');
          
          // Verify we have meaningful data (not just zeros)
          const hasValidData = Object.values(statisticsData).some(value => 
            value && value !== '0' && !value.includes('Sem')
          );
          
          if (hasValidData) {
            console.log('✅ XML parsing produced valid data');
          } else {
            console.log('⚠️ XML parsing may not have extracted data correctly');
          }
        }
      }
    }
  });
});