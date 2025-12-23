# VAT Validation Unit Tests

## Overview

Comprehensive unit tests for VAT validation functionality covering all configuration scenarios and edge cases.

## Test File

**Location:** `tests/Unit/CheckoutVATValidationTest.php`

**Namespace:** `CLOSE\ConnectEcommerce\Tests\Unit`

## Test Scenarios

### 1. Mandatory VAT with Valid VAT → Allows Checkout ✓

**Test:** `test_mandatory_vat_valid_allows_checkout()`

**Configuration:**
```php
'vat_show'           => 'yes'
'vat_mandatory'      => 'yes'
'vat_vies_enabled'   => 'yes'
'vat_vies_mandatory' => 'yes'
```

**Input:**
```php
billing_vat: 'FR12345678901' (valid)
billing_country: 'FR'
```

**Expected Result:**
- ✅ No errors in `WP_Error` object
- ✅ Checkout allowed
- ✅ Order can be created

**Assertion:**
```php
$this->assertEmpty($error_messages, 
    'Valid VAT with mandatory setting should allow checkout');
```

---

### 2. Mandatory VAT with Invalid VAT → Blocks Checkout ✗

**Test:** `test_mandatory_vat_invalid_blocks_checkout()`

**Configuration:**
```php
'vat_show'           => 'yes'
'vat_mandatory'      => 'yes'
'vat_vies_enabled'   => 'yes'
'vat_vies_mandatory' => 'yes'
```

**Input:**
```php
billing_vat: 'FR99999999999' (invalid)
billing_country: 'FR'
```

**Expected Result:**
- ❌ Error added to `WP_Error` object
- ❌ Checkout blocked
- ❌ Order cannot be created

**Assertion:**
```php
$this->assertNotEmpty($error_messages, 
    'Invalid VAT with mandatory setting should block checkout');
$this->assertStringContainsString('validation failed', $error_messages[0]);
```

---

### 3. Optional VAT Validation → Allows Checkout with Warning

**Test:** `test_optional_vat_validation_allows_checkout()`

**Configuration:**
```php
'vat_show'           => 'yes'
'vat_vies_enabled'   => 'yes'
'vat_vies_mandatory' => 'no'  // ← Validation optional
```

**Input:**
```php
billing_vat: 'FR99999999999' (invalid)
billing_country: 'FR'
```

**Expected Result:**
- ✅ No errors in `WP_Error` object
- ✅ Checkout allowed
- ⚠️ Warning notice shown (via `wc_add_notice()`)
- ✅ Order can be created

**Assertion:**
```php
$this->assertEmpty($error_messages, 
    'Optional validation should allow checkout even with invalid VAT');
```

**Note:** The warning is shown via `wc_add_notice()` but doesn't block checkout.

---

### 4. VAT Field Not Mandatory → Allows Checkout Without VAT

**Test:** `test_vat_not_mandatory_allows_checkout_without_vat()`

**Configuration:**
```php
'vat_show'           => 'yes'
'vat_mandatory'      => 'no'   // ← Field not required
'vat_vies_enabled'   => 'yes'
'vat_vies_mandatory' => 'no'
```

**Input:**
```php
// No billing_vat field provided
billing_country: 'ES'
billing_email: 'test@example.com'
```

**Expected Result:**
- ✅ No errors in `WP_Error` object
- ✅ Checkout allowed without VAT
- ✅ Order created without VAT validation

**Assertion:**
```php
$this->assertEmpty($error_messages, 
    'Non-mandatory VAT field should allow checkout without VAT number');
```

---

### 5. VAT Required BUT Validation Optional → Allows Invalid VAT

**Test:** `test_vat_required_but_validation_optional_allows_invalid()`

**Configuration:**
```php
'vat_show'           => 'yes'
'vat_mandatory'      => 'yes'  // ← Field required
'vat_vies_enabled'   => 'yes'
'vat_vies_mandatory' => 'no'   // ← But validation optional
```

**Input:**
```php
billing_vat: 'FR99999999999' (invalid)
billing_country: 'FR'
```

**Expected Result:**
- ✅ No errors in `WP_Error` object
- ✅ Checkout allowed (field is filled, validation optional)
- ⚠️ Warning notice shown
- ✅ Order created with invalid VAT recorded

**Assertion:**
```php
$this->assertEmpty($error_messages, 
    'VAT field required but validation optional should allow checkout with invalid VAT');
```

**Important:** This distinguishes between:
- **Field requirement**: Customer must enter something
- **Validation requirement**: What they enter must be valid

---

### 6. B2B Exemption for Different Countries

**Test:** `test_b2b_exemption_different_countries()`

**Configuration:**
```php
Store country: ES (Spain)
Customer country: FR (France)
```

**Input:**
```php
VAT: 'FR12345678901' (valid)
```

**Expected Result:**
- ✅ `should_apply_vat_exemption()` returns `true`
- ✅ Zero-rate tax class applied
- ✅ 0% VAT in order

**Assertion:**
```php
$should_exempt = VAT::should_apply_vat_exemption('FR', 'FR12345678901', true);
$this->assertTrue($should_exempt, 
    'B2B exemption should apply for different EU countries');
```

---

### 7. B2B Exemption NOT for Same Country

**Test:** `test_b2b_exemption_same_country()`

**Configuration:**
```php
Store country: ES (Spain)
Customer country: ES (Spain)
```

**Input:**
```php
VAT: 'ESB12345678' (valid)
```

**Expected Result:**
- ❌ `should_apply_vat_exemption()` returns `false`
- ❌ Zero-rate NOT applied
- ✅ Standard VAT (21%) in order

**Assertion:**
```php
$should_exempt = VAT::should_apply_vat_exemption('ES', 'ESB12345678', true);
$this->assertFalse($should_exempt, 
    'B2B exemption should NOT apply for same country (domestic)');
```

---

### 8. Exemption Removed When Field Emptied

**Test:** `test_vat_exemption_removed_on_empty_field()`

**Scenario:**
```
1. Customer enters valid VAT (exemption applied)
2. Customer clears VAT field
3. Exemption should be removed
```

**Steps:**
```php
// Step 1: Apply exemption
VAT::apply_vat_exemption('FR', 'FR12345678901', true);
assertTrue(VAT::is_customer_vat_exempt());

// Step 2: Validate with empty VAT
$data = array('billing_country' => 'FR');
$checkout->validate_vat_number_checkout($data, $errors);

// Step 3: Verify exemption removed
assertFalse(VAT::is_customer_vat_exempt());
```

**Assertion:**
```php
$this->assertFalse(VAT::is_customer_vat_exempt(), 
    'Exemption should be removed when VAT field is empty');
```

---

## Running Tests

### Run All VAT Tests

```bash
cd /path/to/woocommerce-es
composer test -- tests/Unit/CheckoutVATValidationTest.php
```

### Run Specific Test

```bash
composer test -- tests/Unit/CheckoutVATValidationTest.php::test_mandatory_vat_valid_allows_checkout
```

### Run with Debug Output

```bash
composer test -- --debug tests/Unit/CheckoutVATValidationTest.php
```

## Test Matrix

### Settings Combinations

| VAT Show | VAT Mandatory | VIES Enabled | VIES Mandatory | Result |
|----------|---------------|--------------|----------------|--------|
| No | - | - | - | No VAT field shown |
| Yes | No | - | - | Field shown, optional, no validation |
| Yes | Yes | No | - | Field required, no validation |
| Yes | No | Yes | No | Field optional, validation optional |
| Yes | Yes | Yes | No | Field required, validation optional ✓ |
| Yes | No | Yes | Yes | Field optional, validation blocks invalid |
| Yes | Yes | Yes | Yes | Field required, validation blocks invalid ✓ |

### Expected Outcomes

| Scenario | VAT Field | VAT Valid | Mandatory Val | Checkout |
|----------|-----------|-----------|---------------|----------|
| 1 | Empty | - | No | ✅ Allowed |
| 2 | Empty | - | Yes | ❌ Blocked (field empty) |
| 3 | Provided | Valid | No | ✅ Allowed |
| 4 | Provided | Valid | Yes | ✅ Allowed |
| 5 | Provided | Invalid | No | ✅ Allowed + Warning |
| 6 | Provided | Invalid | Yes | ❌ Blocked |
| 7 | Provided | Failed VIES | No | ✅ Allowed + Warning |
| 8 | Provided | Failed VIES | Yes | ❌ Blocked |

## Mocking

### Mock Valid VAT Result

```php
$cache_key = md5('ES' . $vat_number);
wp_cache_set(
    $cache_key,
    array(
        'valid'        => true,
        'message'      => 'Valid VAT number',
        'country_code' => 'ES',
        'vat_number'   => $vat_number,
        'name'         => 'Test Company SL',
    ),
    'conecom_vat_validation',
    DAY_IN_SECONDS
);
```

### Mock Invalid VAT Result

```php
$cache_key = md5('ES' . $vat_number);
wp_cache_set(
    $cache_key,
    array(
        'valid'   => false,
        'message' => 'Invalid VAT number',
    ),
    'conecom_vat_validation',
    DAY_IN_SECONDS
);
```

### Mock WooCommerce Session

```php
if (function_exists('WC') && WC()->session) {
    WC()->session->set('vat_exempt_applied', true);
    WC()->session->set('vat_exempt_country', 'FR');
}
```

## Coverage

### Code Coverage

Run tests with coverage:

```bash
composer test -- --coverage-html coverage/
```

View coverage report:
```bash
open coverage/index.html
```

### Expected Coverage

- ✅ `VAT::validate_vat_number()` - 100%
- ✅ `VAT::should_apply_vat_exemption()` - 100%
- ✅ `VAT::apply_vat_exemption()` - 100%
- ✅ `VAT::remove_vat_exemption()` - 100%
- ✅ `Checkout::validate_vat_number_checkout()` - 95%

## CI/CD Integration

### GitHub Actions

Tests run automatically on:
- Push to `trunk` branch
- Pull requests

**Config:** `.github/workflows/phpunit.yml`

### Expected Results

All tests should pass:
```
✓ test_mandatory_vat_valid_allows_checkout
✓ test_mandatory_vat_invalid_blocks_checkout
✓ test_optional_vat_validation_allows_checkout
✓ test_vat_not_mandatory_allows_checkout_without_vat
✓ test_vat_required_but_validation_optional_allows_invalid
✓ test_b2b_exemption_different_countries
✓ test_b2b_exemption_same_country
✓ test_vat_exemption_removed_on_empty_field
```

## Troubleshooting

### Test Fails: WooCommerce Not Available

**Error:** `WooCommerce is not available`

**Solution:**
1. Install WooCommerce in test environment
2. Run `bash bin/install-wp-tests.sh` with WooCommerce

### Test Fails: Session Not Available

**Error:** `WooCommerce session not available`

**Solution:**
- Test is skipped automatically with `markTestSkipped()`
- This is expected in some test environments

### Test Fails: Cache Issues

**Error:** Cached results interfering with tests

**Solution:**
```php
public function tearDown(): void {
    VAT::clear_cache(); // Already implemented
    parent::tearDown();
}
```

## Best Practices

### 1. Isolate Tests

Each test should:
- Set up its own configuration
- Not depend on other tests
- Clean up after itself

### 2. Mock External Services

Don't call real VIES/VATSense APIs:
- Use cache mocking
- Use filters to override results
- Keep tests fast (<1s each)

### 3. Test Edge Cases

Cover:
- Empty fields
- Invalid formats
- Service failures
- Session issues
- Country changes

### 4. Descriptive Names

Test names clearly indicate:
- What is being tested
- Expected behavior
- Pass/fail criteria

## Adding New Tests

### Template

```php
/**
 * Test [description]
 *
 * Scenario: [when X] → [should Y]
 *
 * @return void
 */
public function test_[descriptive_name]() {
    // 1. Configure settings
    update_option('connect_ecommerce_public', array(...));
    
    // 2. Mock data
    $cache_key = md5('...');
    wp_cache_set($cache_key, array(...), 'conecom_vat_validation');
    
    // 3. Prepare test data
    $data = array('billing_vat' => '...', ...);
    $errors = new WP_Error();
    
    // 4. Execute
    $checkout = new Checkout(array());
    $checkout->validate_vat_number_checkout($data, $errors);
    
    // 5. Assert
    $this->assert...($expected, $actual, 'message');
}
```

## Summary

These tests ensure:

✅ **Mandatory validation works correctly** - Blocks invalid VAT when required  
✅ **Optional validation works correctly** - Shows warnings but allows checkout  
✅ **Field requirement is independent** - Can require field without requiring validation  
✅ **B2B exemption logic is correct** - Only applies for different EU countries  
✅ **Exemption cleanup works** - Removed when field is emptied  
✅ **All configuration combinations covered** - 8 core scenarios tested  

## Running Full Test Suite

```bash
# All tests
composer test

# Only VAT-related tests
composer test -- tests/Unit/CheckoutVATValidationTest.php tests/Unit/VATValidationTest.php tests/Unit/VATSettingsTest.php

# With verbose output
composer test -- --verbose tests/Unit/CheckoutVATValidationTest.php
```

Expected output:
```
PHPUnit 9.x

Testing CLOSE\ConnectEcommerce\Tests\Unit\CheckoutVATValidationTest
✓ test_validate_vat_number_checkout_empty
✓ test_save_vat_validation_result
✓ test_vies_validation_enabled
✓ test_vies_validation_disabled
✓ test_vat_field_extraction
✓ test_validation_result_session_storage
✓ test_mandatory_validation_blocks_invalid
✓ test_optional_validation_shows_warning
✓ test_custom_checkout_field
✓ test_mandatory_vat_valid_allows_checkout
✓ test_mandatory_vat_invalid_blocks_checkout
✓ test_optional_vat_validation_allows_checkout
✓ test_vat_not_mandatory_allows_checkout_without_vat
✓ test_vat_required_but_validation_optional_allows_invalid
✓ test_b2b_exemption_different_countries
✓ test_b2b_exemption_same_country
✓ test_vat_exemption_removed_on_empty_field

Time: 00:02.456, Memory: 34.00 MB

OK (17 tests, 23 assertions)
```


