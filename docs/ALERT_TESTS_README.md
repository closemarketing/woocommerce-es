# Alert System Unit Tests

## Test Coverage Summary

**Total Tests:** 26 tests with 54 assertions
**Test Files:** 2
- `tests/Unit/AlertTest.php` - Core alert functionality (17 tests)
- `tests/Unit/AlertIntegrationTest.php` - Integration tests (9 tests)

## Running Tests

```bash
# Run all alert tests
composer test -- --filter=Alert

# Run only core alert tests
composer test -- --filter=AlertTest

# Run only integration tests
composer test -- --filter=AlertIntegrationTest
```

## Test Coverage

### AlertTest.php (Core Functionality)

#### 1. **Configuration Tests**
- ✅ `test_alert_disabled_by_default` - Verifies alerts are disabled by default
- ✅ `test_enable_alerts` - Tests enabling alerts via settings
- ✅ `test_alert_settings_sanitization` - Validates proper sanitization of settings

#### 2. **Email Alert Tests**
- ✅ `test_email_alert_with_valid_settings` - Tests email sending with valid configuration
- ✅ `test_email_alert_defaults_to_admin_email` - Verifies default admin email usage
- ✅ `test_alert_with_context` - Tests alerts with additional context data

#### 3. **Slack Alert Tests**
- ✅ `test_slack_alert_requires_webhook` - Ensures webhook URL is required
- ✅ `test_slack_alert_with_webhook` - Tests Slack webhook integration
- ✅ `test_slack_alert_special_characters` - Validates special character handling

#### 4. **Product Error Alerts**
- ✅ `test_product_errors_alert` - Tests product import error notifications
- ✅ `test_empty_product_errors_array` - Handles empty error arrays gracefully
- ✅ `test_multiple_product_errors_formatting` - Tests formatting of multiple errors

#### 5. **Order Error Alerts**
- ✅ `test_order_error_alert` - Tests order submission error notifications
- ✅ `test_order_error_alert_invalid_order` - Handles invalid order IDs

#### 6. **Logging and Testing**
- ✅ `test_alert_logs_to_woocommerce` - Verifies WooCommerce logger integration
- ✅ `test_test_alert` - Tests the test alert functionality
- ✅ `test_alert_is_logged_even_when_disabled` - Ensures logging happens even when alerts are disabled

### AlertIntegrationTest.php (Integration)

#### 1. **HELPER Integration**
- ✅ `test_helper_send_product_errors_triggers_alert` - Verifies HELPER class integration
- ✅ `test_helper_send_product_errors_empty_array` - Tests edge case handling
- ✅ `test_alerts_with_different_connectors` - Tests with multiple connector types

#### 2. **ORDER Integration**
- ✅ `test_order_create_invoice_integration` - Tests ORDER class integration
- ✅ `test_alerts_dont_interfere_when_disabled` - Ensures no interference when disabled

#### 3. **Performance and Edge Cases**
- ✅ `test_concurrent_alerts` - Tests multiple simultaneous alerts
- ✅ `test_alert_with_long_error_message` - Handles very long error messages
- ✅ `test_alert_with_special_characters` - Tests special character escaping
- ✅ `test_alert_performance_with_many_errors` - Performance test with 100 errors

## Test Assertions

The tests verify:

1. **Functionality**
   - Alerts are disabled by default
   - Email and Slack alerts work correctly
   - Settings are properly saved and sanitized
   - Test alert button works

2. **Integration**
   - HELPER::send_product_errors() triggers alerts
   - ORDER::create_invoice() error handling works
   - Multiple connectors are supported
   - No interference with existing functionality

3. **Error Handling**
   - Empty arrays are handled gracefully
   - Invalid order IDs don't break the system
   - Missing webhook URLs are caught
   - Special characters are properly escaped

4. **Performance**
   - System handles 100 errors efficiently (< 5 seconds)
   - Multiple concurrent alerts work correctly
   - Long error messages don't cause issues

## Mock Objects

Tests use WordPress PHPUnit mocking for:
- **Email sending** - `tests_retrieve_phpmailer_instance()`
- **HTTP requests** - `pre_http_request` filter for Slack webhooks
- **WooCommerce orders** - `wc_create_order()`

## Test Data

Tests use realistic data:
- Product errors with SKUs, names, and error messages
- Order data with customer information
- Various connector types (holded, clientify, test-connector)
- Special characters and edge cases

## Continuous Integration

These tests are designed to run in CI/CD pipelines:
- Fast execution (< 1 second for all tests)
- No external dependencies
- Isolated test environment
- Automatic cleanup after each test

## Code Coverage

The tests cover:
- ✅ All public methods in ALERT class
- ✅ Integration points in HELPER class
- ✅ Integration points in ORDER class
- ✅ Settings page AJAX handler
- ✅ Email and Slack notification paths
- ✅ Error handling and edge cases

## Future Test Additions

Potential areas for expanded coverage:
- Settings page UI testing (requires Selenium/browser testing)
- Real Slack webhook integration (requires network access)
- Performance tests with larger datasets
- Multisite WordPress installations

## Debugging Failed Tests

If tests fail:

1. **Check WordPress Test Environment**
   ```bash
   composer test-install
   ```

2. **Run Tests with Debug Output**
   ```bash
   composer test-debug -- --filter=AlertTest
   ```

3. **Check WooCommerce Installation**
   - Ensure WooCommerce is installed in test environment
   - Verify `bin/install-wp-tests.sh` ran successfully

4. **Review Test Logs**
   - Check for PHPUnit error messages
   - Look for WordPress database errors
   - Verify mock objects are working

## Contributing

When adding new alert features:

1. Add corresponding unit tests
2. Add integration tests if touching existing code
3. Run all tests before committing: `composer test`
4. Ensure tests are independent and isolated
5. Follow existing test naming conventions

---

**Last Updated:** October 24, 2025
**Test Framework:** PHPUnit 9.6.22
**WordPress Test Library:** wp-phpunit/wp-phpunit ^6.3

