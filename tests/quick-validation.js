const { chromium } = require('playwright');

async function validateDashboard() {
  console.log('🚀 Starting Quick Dashboard Validation...');
  
  const browser = await chromium.launch({ headless: false });
  const page = await browser.newPage();
  
  try {
    console.log('📍 Step 1: Navigate to dashboard');
    const response = await page.goto('http://localhost:8000/sistema/dashboard/index.php', {
      waitUntil: 'domcontentloaded',
      timeout: 30000
    });
    
    console.log(`   Status: ${response.status()}`);
    
    // Take baseline screenshot
    await page.screenshot({ 
      path: 'test-results/quick-validation-dashboard.png', 
      fullPage: true 
    });
    console.log('📸 Dashboard screenshot captured');
    
    console.log('📍 Step 2: Check for PHP errors');
    const content = await page.content();
    const phpErrors = content.match(/Fatal error:|Parse error:|Warning:|Notice:/gi);
    
    if (phpErrors) {
      console.log('❌ PHP Errors found:', phpErrors);
    } else {
      console.log('✅ No PHP errors detected');
    }
    
    console.log('📍 Step 3: Check critical elements');
    const elements = {
      'Dashboard Container': '.dashboard-container',
      'Manual Control Panel': '#manualControlPanel',
      'Upload Zone': '#uploadZone',
      'System Status': '.system-status'
    };
    
    for (const [name, selector] of Object.entries(elements)) {
      const element = await page.$(selector);
      if (element) {
        console.log(`✅ ${name}: Found`);
      } else {
        console.log(`❌ ${name}: Not found`);
      }
    }
    
    console.log('📍 Step 4: Test manual control buttons');
    const buttons = await page.$$('#manualControlPanel button');
    console.log(`🔘 Found ${buttons.length} control buttons`);
    
    // Test clicking a safe button
    const verifyButton = await page.$('#btnVerifyDatabase');
    if (verifyButton) {
      console.log('🖱️ Testing verify database button click');
      await verifyButton.click();
      await page.waitForTimeout(2000);
      
      await page.screenshot({ 
        path: 'test-results/quick-validation-after-verify.png', 
        fullPage: true 
      });
      console.log('📸 After button click screenshot captured');
    }
    
    console.log('📍 Step 5: Check for JavaScript errors');
    const jsErrors = [];
    page.on('pageerror', error => jsErrors.push(error.message));
    
    await page.waitForTimeout(3000);
    
    if (jsErrors.length > 0) {
      console.log('❌ JavaScript errors:', jsErrors);
    } else {
      console.log('✅ No JavaScript errors detected');
    }
    
    console.log('✅ Quick validation completed successfully!');
    
  } catch (error) {
    console.error('❌ Validation failed:', error.message);
  } finally {
    await browser.close();
  }
}

validateDashboard();