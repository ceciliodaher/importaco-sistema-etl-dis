#!/bin/bash

# ===============================================================================
# Comprehensive Playwright Test Execution Script
# ETL DI Dashboard System - Post-Fix Validation
# ===============================================================================

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

echo -e "${BLUE}===============================================================================${NC}"
echo -e "${BLUE}🚀 ETL DI Dashboard - Comprehensive E2E Test Suite${NC}"
echo -e "${BLUE}   Validating system after syntax error fixes${NC}"
echo -e "${BLUE}===============================================================================${NC}"

# Function to print section headers
print_section() {
    echo -e "\n${YELLOW}📋 $1${NC}"
    echo "----------------------------------------"
}

# Function to check if server is running
check_server() {
    print_section "Checking Server Status"
    
    local max_attempts=10
    local attempt=1
    
    while [ $attempt -le $max_attempts ]; do
        if curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/sistema/dashboard/index.php | grep -q "200"; then
            echo -e "${GREEN}✅ Server is running and accessible${NC}"
            return 0
        fi
        
        echo -e "${YELLOW}⏳ Attempt $attempt/$max_attempts - Server not ready, waiting...${NC}"
        sleep 2
        ((attempt++))
    done
    
    echo -e "${RED}❌ Server is not accessible after $max_attempts attempts${NC}"
    return 1
}

# Function to install dependencies
install_dependencies() {
    print_section "Installing Test Dependencies"
    
    cd "$SCRIPT_DIR"
    
    if [ ! -f "package.json" ]; then
        echo -e "${RED}❌ package.json not found in test directory${NC}"
        exit 1
    fi
    
    echo "📦 Installing npm packages..."
    npm install
    
    echo "🌐 Installing Playwright browsers..."
    npx playwright install
    
    echo -e "${GREEN}✅ Dependencies installed successfully${NC}"
}

# Function to run specific test suite
run_test_suite() {
    local suite_name="$1"
    local suite_file="$2"
    
    print_section "Running Test Suite: $suite_name"
    
    echo "🧪 Executing: $suite_file"
    
    if npx playwright test "$suite_file" --reporter=list; then
        echo -e "${GREEN}✅ $suite_name - PASSED${NC}"
        return 0
    else
        echo -e "${RED}❌ $suite_name - FAILED${NC}"
        return 1
    fi
}

# Function to generate test report
generate_report() {
    print_section "Generating Test Report"
    
    echo "📊 Generating HTML report..."
    npx playwright show-report --host=localhost --port=9323 &
    local report_pid=$!
    
    sleep 3
    
    echo -e "${GREEN}📋 Test report available at: http://localhost:9323${NC}"
    echo -e "${BLUE}💡 Report will remain open for viewing. Press Ctrl+C to close.${NC}"
    
    # Keep the report server running
    wait $report_pid
}

# Function to capture system info
capture_system_info() {
    print_section "Capturing System Information"
    
    local info_file="$SCRIPT_DIR/test-results/system-info.txt"
    mkdir -p "$(dirname "$info_file")"
    
    {
        echo "ETL DI Dashboard Test Execution - System Information"
        echo "=================================================="
        echo "Date: $(date)"
        echo "System: $(uname -a)"
        echo "Node.js: $(node --version 2>/dev/null || echo 'Not installed')"
        echo "npm: $(npm --version 2>/dev/null || echo 'Not installed')"
        echo "PHP: $(php --version 2>/dev/null | head -1 || echo 'Not available')"
        echo "Git: $(git --version 2>/dev/null || echo 'Not installed')"
        echo ""
        echo "Project Information"
        echo "==================="
        echo "Project Root: $PROJECT_ROOT"
        echo "Test Directory: $SCRIPT_DIR"
        echo "Current Branch: $(cd "$PROJECT_ROOT" && git branch --show-current 2>/dev/null || echo 'Unknown')"
        echo "Latest Commit: $(cd "$PROJECT_ROOT" && git log -1 --oneline 2>/dev/null || echo 'Unknown')"
        echo ""
        echo "Server Status"
        echo "============="
        echo "Dashboard URL: http://localhost:8000/sistema/dashboard/index.php"
        echo "Server Response: $(curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/sistema/dashboard/index.php || echo 'No response')"
    } > "$info_file"
    
    echo -e "${GREEN}✅ System information saved to: $info_file${NC}"
}

# Function to run pre-test validations
run_pre_test_validations() {
    print_section "Pre-Test Validations"
    
    echo "🔍 Checking dashboard accessibility..."
    if ! curl -s -f http://localhost:8000/sistema/dashboard/index.php >/dev/null; then
        echo -e "${RED}❌ Dashboard is not accessible${NC}"
        return 1
    fi
    
    echo "🔍 Checking for PHP syntax errors..."
    local php_check=$(curl -s http://localhost:8000/sistema/dashboard/index.php | grep -i "fatal\|parse error\|warning" || true)
    
    if [ -n "$php_check" ]; then
        echo -e "${RED}❌ PHP errors detected in dashboard:${NC}"
        echo "$php_check"
        return 1
    fi
    
    echo "🔍 Checking manual control panel..."
    local manual_control_check=$(curl -s http://localhost:8000/sistema/dashboard/index.php | grep -c "manualControlPanel" || echo "0")
    
    if [ "$manual_control_check" -eq "0" ]; then
        echo -e "${RED}❌ Manual control panel not found in dashboard${NC}"
        return 1
    fi
    
    echo -e "${GREEN}✅ Pre-test validations passed${NC}"
    return 0
}

# Main execution function
main() {
    cd "$SCRIPT_DIR"
    
    # Capture system information
    capture_system_info
    
    # Check server status
    if ! check_server; then
        echo -e "${RED}❌ Cannot proceed without running server${NC}"
        echo -e "${YELLOW}💡 Please ensure the server is running: php -S localhost:8000 -t sistema/${NC}"
        exit 1
    fi
    
    # Install dependencies
    install_dependencies
    
    # Run pre-test validations
    if ! run_pre_test_validations; then
        echo -e "${RED}❌ Pre-test validations failed${NC}"
        exit 1
    fi
    
    # Test execution tracking
    local total_tests=0
    local passed_tests=0
    local failed_tests=0
    
    # Define test suites to run
    declare -a test_suites=(
        "Dashboard Load Validation:e2e/01-dashboard-load.spec.ts"
        "Manual Control Functionality:e2e/02-manual-control-functionality.spec.ts"
        "XML Processing:e2e/03-xml-processing.spec.ts"
        "Performance Monitoring:e2e/04-performance-monitoring.spec.ts"
        "Comprehensive Integration:e2e/05-comprehensive-integration.spec.ts"
    )
    
    # Run each test suite
    for suite in "${test_suites[@]}"; do
        IFS=':' read -r suite_name suite_file <<< "$suite"
        
        ((total_tests++))
        
        if run_test_suite "$suite_name" "$suite_file"; then
            ((passed_tests++))
        else
            ((failed_tests++))
        fi
        
        echo ""  # Add spacing between suites
    done
    
    # Print final results
    print_section "Test Execution Summary"
    
    echo "📊 Test Results:"
    echo "   Total Suites: $total_tests"
    echo "   Passed: $passed_tests"
    echo "   Failed: $failed_tests"
    echo ""
    
    if [ $failed_tests -eq 0 ]; then
        echo -e "${GREEN}🎉 ALL TESTS PASSED! Dashboard system is working correctly after fixes.${NC}"
        echo -e "${GREEN}✅ No Fatal PHP errors detected${NC}"
        echo -e "${GREEN}✅ Manual control system functional${NC}"
        echo -e "${GREEN}✅ API integrations working${NC}"
        echo -e "${GREEN}✅ Performance within acceptable limits${NC}"
    else
        echo -e "${RED}❌ $failed_tests test suite(s) failed${NC}"
        echo -e "${YELLOW}💡 Check the detailed report for specific failures${NC}"
    fi
    
    echo ""
    echo -e "${BLUE}📋 Detailed HTML report will be generated...${NC}"
    
    # Generate and show report
    generate_report
    
    # Exit with appropriate code
    [ $failed_tests -eq 0 ] && exit 0 || exit 1
}

# Handle script interruption
trap 'echo -e "\n${YELLOW}⚠️ Test execution interrupted${NC}"; exit 1' INT

# Run main function
main "$@"