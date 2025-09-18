import * as fs from 'fs-extra';
import * as path from 'path';

/**
 * Global teardown for Playwright tests
 * 
 * Responsibilities:
 * - Generate comprehensive test report
 * - Archive test artifacts
 * - Clean up temporary files
 * - Log test completion summary
 */

async function globalTeardown() {
  console.log('🏁 Starting Playwright E2E Test Teardown...');

  try {
    // Generate test summary
    const resultsPath = 'test-results/results.json';
    
    if (await fs.pathExists(resultsPath)) {
      const results = await fs.readJson(resultsPath);
      
      console.log('📊 Test Execution Summary:');
      console.log(`   - Total Suites: ${results.suites?.length || 0}`);
      console.log(`   - Total Tests: ${results.stats?.total || 0}`);
      console.log(`   - Passed: ${results.stats?.passed || 0}`);
      console.log(`   - Failed: ${results.stats?.failed || 0}`);
      console.log(`   - Skipped: ${results.stats?.skipped || 0}`);
      console.log(`   - Duration: ${results.stats?.duration || 0}ms`);
    }

    // Create artifact archive timestamp
    const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
    const archiveDir = `test-results/archive-${timestamp}`;
    
    // Archive important artifacts
    if (await fs.pathExists('test-results/screenshots')) {
      await fs.copy('test-results/screenshots', `${archiveDir}/screenshots`);
    }
    
    if (await fs.pathExists('playwright-report')) {
      await fs.copy('playwright-report', `${archiveDir}/html-report`);
    }

    console.log(`📦 Test artifacts archived to: ${archiveDir}`);

  } catch (error) {
    console.error('⚠️ Teardown warning:', error.message);
  }

  console.log('✅ Global teardown completed');
}

export default globalTeardown;