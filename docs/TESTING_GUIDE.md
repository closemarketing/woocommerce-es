# Testing Guide - Background Import Process

## Quick Start

### Prerequisites
1. WordPress 6.3+ with WooCommerce installed
2. Connect Ecommerce plugin activated
3. API credentials configured in plugin settings
4. Action Scheduler working (comes with WooCommerce)

### Installation

1. **No additional setup required** - All files are in place:
   - Background process handler class
   - AJAX endpoints
   - JavaScript handler
   - UI updates
   - CSS styles

2. **Clear any caches**:
   ```bash
   # WordPress object cache (if using caching plugin)
   wp cache flush
   
   # Browser cache
   Clear browser cache and hard reload (Ctrl+Shift+R)
   ```

## Testing Scenarios

### Test 1: Basic Import Start
1. Navigate to: `WooCommerce > Connect Ecommerce > Synchronization > Products`
2. Click "Start Import"
3. **Expected**: 
   - Button changes to "Pause" and "Stop"
   - Progress information appears
   - Logs start appearing in real-time (every 2 seconds)
   - Products are being imported
   - Logs show color-coded messages

### Test 2: Pause and Resume
1. Start an import (Test 1)
2. Wait for a few products to be imported
3. Click "Pause"
4. **Expected**: 
   - Import pauses
   - Button changes to "Resume"
   - Current progress is preserved
5. Click "Resume"
6. **Expected**:
   - Import continues from where it paused
   - Progress continues incrementing

### Test 3: Background Processing
1. Start an import
2. Wait for a few products to import
3. **Close the browser tab completely**
4. Wait 1-2 minutes
5. Reopen the page
6. **Expected**:
   - Import continued in background
   - Progress is updated
   - Logs show products imported while tab was closed

### Test 4: Stop Import
1. Start an import
2. Wait for a few products
3. Click "Stop"
4. Confirm the dialog
5. **Expected**:
   - Import stops
   - Final progress is saved
   - Can start a new import

### Test 5: Real-time Log Updates
1. Start an import
2. Keep the page open
3. **Expected**:
   - Logs appear every few seconds
   - New entries auto-scroll to bottom
   - Color coding:
     - Blue border = Success
     - Red background = Error
     - Yellow background = Warning
     - Gray border = Info

### Test 6: Progress Display
1. Start an import
2. Monitor the progress section
3. **Expected**:
   - Shows current product number
   - Shows total synced count
   - Shows error count (if any)
   - Updates in real-time

### Test 7: AI Generation Options
1. Select "NEW Products" from AI dropdown
2. Start import
3. **Expected**:
   - Only new products get AI-generated content
4. Try with "ALL Products"
5. **Expected**:
   - All products get AI-generated content

### Test 8: Error Handling
1. **Temporarily break API connection** (change API key)
2. Start import
3. **Expected**:
   - Import starts but fails with API error
   - Error is logged with red background
   - Import stops gracefully
4. **Fix API connection**
5. Try again
6. **Expected**:
   - Import works normally

### Test 9: Multiple Users (if applicable)
1. User A starts import
2. User B opens the same page
3. **Expected**:
   - User B sees the same import progress
   - Both see same logs and status

### Test 10: Log Persistence on Page Reload
1. Start an import
2. Wait for several products to be imported
3. **Reload the page** (F5 or Ctrl+R)
4. **Expected**:
   - All previous logs are displayed immediately
   - Import continues in background
   - New logs continue to appear
   - Progress information is correct

## Verification Checklist

### Action Scheduler
1. Go to: `Tools > Action Scheduler`
2. Filter by group: `conecom_imports`
3. **Expected**:
   - See scheduled actions when import is running
   - Actions complete successfully
   - No failed actions (unless intentional error test)

### WordPress Options
Check that data is being stored:
```php
// In WordPress debug console or plugin
$state = get_option('conecom_import_state');
print_r($state);

$logs = get_option('conecom_import_logs');
print_r($logs);
```

### Browser Console
Open browser console (F12) and check:
- No JavaScript errors
- Network tab shows polling requests every 2 seconds
- AJAX responses are successful (200 status)

### WordPress Debug Log
Enable debug logging in `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Check `wp-content/debug.log` for any PHP errors or warnings.

## Performance Testing

### Small Catalog (< 100 products)
1. Start import
2. **Expected**:
   - Completes in reasonable time
   - No timeouts or errors
   - All products imported successfully

### Medium Catalog (100-500 products)
1. Start import
2. Close browser tab
3. Check back periodically
4. **Expected**:
   - Import continues in background
   - Progress saves correctly
   - Can pause/resume multiple times

### Large Catalog (500+ products)
1. Start import
2. Monitor Action Scheduler
3. Check server resources
4. **Expected**:
   - No memory errors
   - No timeout errors
   - Consistent processing speed
   - Automatic cleanup after completion

## Common Issues and Solutions

### Issue: Import Not Starting
**Symptoms**: Clicking "Start" does nothing

**Debug**:
1. Check browser console for JavaScript errors
2. Check Network tab for failed AJAX requests
3. Verify nonce is valid (not expired session)

**Solution**: Reload page and try again

### Issue: Import Stops Unexpectedly
**Symptoms**: Status changes to "stopped" or gets stuck

**Debug**:
1. Check Action Scheduler for failed jobs
2. Check WordPress debug log
3. Verify API connection

**Solutions**:
- Check API credentials
- Increase PHP memory limit
- Check server resources

### Issue: Logs Not Updating
**Symptoms**: Progress frozen, no new logs

**Debug**:
1. Check browser console for polling errors
2. Verify AJAX endpoint is responding
3. Check JavaScript is loaded

**Solution**: 
- Clear browser cache
- Reload page
- Check JavaScript console for errors

### Issue: Can't Pause/Resume
**Symptoms**: Buttons not working

**Debug**:
1. Check process_id is valid
2. Verify state in database
3. Check Action Scheduler queue

**Solution**:
- Stop and start new import
- Clear state: `delete_option('conecom_import_state')`

### Issue: Background Process Not Running
**Symptoms**: Import only works with page open

**Debug**:
1. Check WP-Cron is enabled
2. Verify Action Scheduler is working
3. Test with WP-CLI: `wp cron event list`

**Solution**:
- Set up system cron
- Check hosting configuration
- Verify Action Scheduler is not disabled

## Manual Testing Script

Copy and run this test script for comprehensive testing:

```javascript
// Run in browser console on import page

const testSuite = {
    async testStart() {
        console.log('Test 1: Starting import...');
        document.getElementById('conecom-start-background-import').click();
        await this.wait(5000);
        console.log('✓ Import started');
    },
    
    async testPause() {
        console.log('Test 2: Pausing import...');
        document.getElementById('conecom-pause-import').click();
        await this.wait(2000);
        console.log('✓ Import paused');
    },
    
    async testResume() {
        console.log('Test 3: Resuming import...');
        document.getElementById('conecom-resume-import').click();
        await this.wait(2000);
        console.log('✓ Import resumed');
    },
    
    async testStop() {
        console.log('Test 4: Stopping import...');
        document.getElementById('conecom-stop-import').click();
        // Note: Will show confirmation dialog
        console.log('✓ Stop initiated (confirm dialog)');
    },
    
    async wait(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    },
    
    async runAll() {
        await this.testStart();
        await this.testPause();
        await this.testResume();
        await this.testStop();
        console.log('All tests completed!');
    }
};

// Run all tests
// testSuite.runAll();

// Or run individual tests
// testSuite.testStart();
```

## Automated Testing (Recommended)

Add PHPUnit tests for:

### Unit Tests
```php
// tests/Unit/BackgroundProcessHandlerTest.php
class BackgroundProcessHandlerTest extends WP_UnitTestCase {
    public function test_start_creates_process() {
        $handler = new BACKGR();
        $process_id = $handler->start(['api_pagination' => 50]);
        
        $this->assertNotEmpty($process_id);
        $state = BACKGR::get_state($process_id);
        $this->assertEquals('running', $state['status']);
    }
    
    public function test_pause_changes_status() {
        $handler = new BACKGR();
        $process_id = $handler->start(['api_pagination' => 50]);
        
        $result = $handler->pause();
        $this->assertTrue($result);
        
        $state = BACKGR::get_state($process_id);
        $this->assertEquals('paused', $state['status']);
    }
    
    // Add more tests...
}
```

### Integration Tests
```php
// tests/Integration/ImportAjaxTest.php
class ImportAjaxTest extends WP_Ajax_UnitTestCase {
    public function test_start_background_import_endpoint() {
        $this->_setRole('administrator');
        
        $_POST['nonce'] = wp_create_nonce('conecom_manual_import_nonce');
        $_POST['product_ai'] = 'none';
        
        try {
            $this->_handleAjax('conecom_start_background_import');
        } catch (WPAjaxDieContinueException $e) {
            // Expected
        }
        
        $response = json_decode($this->_last_response, true);
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('process_id', $response['data']);
    }
    
    // Add more tests...
}
```

## Success Criteria

✅ All manual tests pass
✅ No JavaScript errors in console
✅ No PHP errors in debug log
✅ Action Scheduler jobs complete successfully
✅ Import continues when browser is closed
✅ Pause/Resume works correctly
✅ Logs update in real-time
✅ Progress tracking is accurate
✅ Large imports complete without errors

## Reporting Issues

When reporting issues, include:

1. **Environment**:
   - WordPress version
   - WooCommerce version
   - PHP version
   - Plugin version

2. **Steps to reproduce**

3. **Expected vs actual behavior**

4. **Screenshots** of:
   - Browser console
   - Network tab
   - Import screen
   - Action Scheduler page

5. **Logs**:
   - WordPress debug log
   - JavaScript console errors
   - Action Scheduler failed jobs

6. **Additional info**:
   - Number of products in catalog
   - API being used
   - Any custom modifications

## Next Steps After Testing

1. Run through all test scenarios
2. Document any issues found
3. Run automated tests (if available)
4. Test on staging environment first
5. Monitor performance on production
6. Collect user feedback
7. Plan any necessary improvements

## Contact

For questions or issues during testing:
- Check Action Scheduler: `Tools > Action Scheduler`
- Enable debug logging
- Review documentation in `/docs/`
- Check implementation summary for architecture details
