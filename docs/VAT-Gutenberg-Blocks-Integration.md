# VAT Validation for WooCommerce Gutenberg Blocks

## Overview

Complete integration of real-time VAT validation with WooCommerce Checkout Blocks (Gutenberg). Supports both classic shortcode checkout and modern block-based checkout with the same validation logic and user experience.

## Architecture

### Dual Support System

```
┌─────────────────────────────────────┐
│  Checkout Detection                 │
└──────────┬──────────────────────────┘
           │
    ┌──────┴──────┐
    │             │
Classic          Blocks
Shortcode        Gutenberg
    │             │
    ├─────────────┤
    │             │
vat-validation.js  (shared)
    │             │
    └──────┬──────┘
           │
vat-validation-blocks.js (blocks specific)
```

## Files Structure

### JavaScript Files

1. **`vat-validation.js`** (Core - Shared)
   - Vanilla JavaScript validation logic
   - AJAX calls to backend
   - Feedback display
   - Works for both classic and blocks

2. **`vat-validation-blocks.js`** (Blocks Specific)
   - MutationObserver for React DOM
   - Store API integration
   - Blocks validation hooks
   - Reuses core validator

### PHP Integration

#### Field Registration

```php
// In add_vat_field_to_checkout()
woocommerce_register_additional_checkout_field(
    array(
        'id'            => 'connect_ecommerce/billing_vat',
        'label'         => __('VAT Number', 'connect-ecommerce'),
        'location'      => 'address',
        'required'      => $required,
        'attributes'    => array(
            'autocomplete' => 'billing_vat',
            'title'        => __('VAT number', 'connect-ecommerce'),
        ),
    )
);
```

This function automatically registers the field for **both classic and blocks**.

#### Backend Validation

```php
// Classic checkout
add_action('woocommerce_after_checkout_validation', 
    array($this, 'validate_vat_number_checkout'), 10, 2);

// Blocks checkout
add_action('woocommerce_store_api_checkout_update_order_from_request',
    array($this, 'validate_vat_number_checkout_blocks'), 10, 2);
```

## How It Works

### 1. Field Detection (Blocks)

```javascript
setupBlocksListeners() {
    // MutationObserver watches for React rendering
    const observer = new MutationObserver(() => {
        const vatField = this.getVATField();
        
        if (vatField && !vatField.dataset.conecomVatListener) {
            vatField.dataset.conecomVatListener = 'true';
            this.attachValidationToField(vatField);
        }
    });
    
    // Observe checkout block container
    const checkoutBlock = document.querySelector('.wp-block-woocommerce-checkout');
    observer.observe(checkoutBlock, { childList: true, subtree: true });
}
```

**Why MutationObserver?**
- React (Blocks) renders fields dynamically
- Standard `DOMContentLoaded` is too early
- MutationObserver catches React updates
- Prevents duplicate listener attachment

### 2. Field Selectors

```javascript
getVATField() {
    const selectors = [
        '#billing_vat',                              // Classic
        'input[name="billing_vat"]',                 // Classic alt
        'input[name="connect_ecommerce/billing_vat"]', // Blocks
        'input[id*="billing_vat"]',                  // Blocks wildcard
        'input[id*="billing-vat"]',                  // Blocks hyphenated
    ];
    // ...
}
```

### 3. Validation Flow (Blocks)

```
User types in blocks checkout
    ↓
MutationObserver detects field
    ↓
attachValidationToField() adds listeners
    ↓
'input' event → handleVATInput()
    ↓
Debounce 800ms
    ↓
performValidation() → AJAX
    ↓
VIES validation (primary)
    ↓ If fails
VATSense validation (fallback)
    ↓
handleValidationResponse()
    ↓
showFeedback() displays result
    ↓
updateCheckoutTotals() triggers WC update
    ↓
Store API receives updated data
    ↓
Backend validates again (security)
    ↓
Order created with correct tax class
```

### 4. Backend Validation (Blocks)

```php
public function validate_vat_number_checkout_blocks($order, $request) {
    // Get billing address from Store API request
    $billing_address = $request->get_param('billing_address');
    
    // Extract VAT number
    $vat_number = $billing_address['connect_ecommerce/billing_vat'] ?? '';
    
    if (empty($vat_number)) {
        VAT::remove_vat_exemption(); // Restore standard VAT
        return;
    }
    
    // Validate
    $validation_result = VAT::validate_vat_number($vat_number, $country_code);
    
    // Apply or remove exemption
    if ($validation_result['valid']) {
        VAT::apply_vat_exemption($country_code, $vat_number, true);
    } else {
        VAT::remove_vat_exemption();
    }
    
    // Save to order meta
    VAT::save_vat_validation_result($order->get_id(), $validation_result);
}
```

## Script Loading

### Conditional Loading

```php
public function enqueue_vat_validation_scripts() {
    // Core validation script (always)
    wp_enqueue_script('conecom-vat-validation', ...);
    
    // Blocks-specific script (only if blocks active)
    if (has_block('woocommerce/checkout') || $this->is_checkout_block_active()) {
        wp_enqueue_script(
            'conecom-vat-validation-blocks',
            $plugin_url . 'includes/assets/vat-validation-blocks.js',
            array('conecom-vat-validation', 'wc-blocks-checkout'),
            $version,
            true
        );
    }
}
```

### Dependencies

**Classic Checkout:**
- No dependencies (pure Vanilla JS)

**Blocks Checkout:**
- `conecom-vat-validation` (core validator)
- `wc-blocks-checkout` (WooCommerce Blocks)
- `wp-element` (React - provided by WC)
- `wp-hooks` (WordPress hooks - provided by WP)

## Features

### Real-time Validation
- ✅ Works in both classic and blocks checkout
- ✅ Same debounce timing (800ms)
- ✅ Same visual feedback
- ✅ Same AJAX endpoint

### Tax Calculation
- ✅ Zero-rate applied for valid B2B intra-community
- ✅ Standard VAT for domestic transactions
- ✅ Automatic recalculation in blocks
- ✅ Correct tax class saved to order

### User Experience
- ✅ Identical feedback in both checkouts
- ✅ No page refresh needed
- ✅ Immediate visual response
- ✅ Accessible (ARIA attributes)

## Blocks-Specific Considerations

### React Rendering

Blocks use React, which means:
- Fields are rendered asynchronously
- DOM updates happen via React state
- Need MutationObserver to detect fields
- Standard event listeners work once field is found

### Store API

Blocks use WooCommerce Store API:
- Different request structure
- Field names can vary
- Backend validation hook is different
- Session handling is the same

### Validation Hook

```javascript
// Register validation with WooCommerce Blocks
window.wp.hooks.addFilter(
    'woocommerce_blocks_checkout_validation_errors',
    'conecom-vat-validation',
    async function(errors, checkoutData) {
        const result = await VATBlocksValidator.validateBeforeCheckout(checkoutData);
        
        if (!result.valid) {
            errors.push({
                message: result.message,
                hidden: false,
            });
        }
        
        return errors;
    }
);
```

This hook runs before order creation and can prevent checkout if VAT is invalid (when mandatory).

## Testing

### Test Classic Checkout

1. Go to classic checkout page
2. Enter VAT number
3. Verify real-time validation
4. Complete order
5. Check order meta for VAT info

### Test Blocks Checkout

1. Go to blocks checkout page
2. Enter VAT number in address form
3. Verify real-time validation appears
4. Complete order
5. Check order meta for VAT info and tax class

### Test Both Scenarios

#### Scenario A: Same Country (Domestic)

**Classic Checkout:**
```
Country: ES
VAT: ESB12345678
Expected: ✓ Valid, Standard VAT (21%)
```

**Blocks Checkout:**
```
Country: ES
VAT: ESB12345678
Expected: ✓ Valid, Standard VAT (21%)
```

#### Scenario B: Different Country (B2B)

**Classic Checkout:**
```
Country: FR
VAT: FR12345678901
Expected: ✓✓ Valid, Zero Rate (0%), Exemption applied
```

**Blocks Checkout:**
```
Country: FR
VAT: FR12345678901
Expected: ✓✓ Valid, Zero Rate (0%), Exemption applied
```

## Compatibility

### WooCommerce Versions

- **WooCommerce 6.0-7.x**: Classic checkout
- **WooCommerce 8.0+**: Blocks checkout available
- **WooCommerce 8.3+**: Blocks checkout default

### Block Types Supported

- ✅ `woocommerce/checkout` - Main checkout block
- ✅ `woocommerce/cart` - Cart block (if VAT shown there)
- ✅ Additional checkout fields API

## Troubleshooting

### Validation not working in blocks

**Check:**
1. Is `wc-blocks-checkout` script loaded?
2. Open browser console, check for errors
3. Verify `window.ConecomVATBlocksValidator` exists
4. Check if MutationObserver is supported

**Solution:**
```javascript
// In browser console
console.log('Blocks Validator:', window.ConecomVATBlocksValidator);
console.log('Core Validator:', window.ConecomVATValidator);
```

### Field not found in blocks

**Check:**
1. Is field registered via `woocommerce_register_additional_checkout_field()`?
2. Check field ID in inspector: should contain "billing_vat"
3. Try different selectors in `getVATField()`

**Debug:**
```javascript
// Find all inputs with 'vat' in blocks
document.querySelectorAll('[id*="vat"], [name*="vat"]');
```

### Validation happens twice

**Cause:** Both classic and blocks scripts loaded simultaneously.

**Solution:** Scripts are conditionally loaded:
```php
if (has_block('woocommerce/checkout') || $this->is_checkout_block_active()) {
    // Load blocks script
}
```

### Exemption not applying in blocks

**Check:**
1. Backend validation hook is firing: `woocommerce_store_api_checkout_update_order_from_request`
2. VAT::apply_vat_exemption() is called
3. Session is maintained during Store API requests

**Debug:**
```php
add_action('woocommerce_store_api_checkout_update_order_from_request', function($order, $request) {
    error_log('Blocks validation - Order ID: ' . $order->get_id());
    error_log('VAT Exempt: ' . (VAT::is_customer_vat_exempt() ? 'YES' : 'NO'));
}, 999, 2);
```

## Best Practices

### 1. Test Both Checkouts

Always test with:
- Classic shortcode checkout
- Blocks checkout
- Different browsers
- Mobile devices

### 2. Monitor Performance

Blocks can be slower due to React rendering:
```javascript
// Measure validation time
console.time('VAT Validation');
// ... validation code
console.timeEnd('VAT Validation');
```

### 3. Handle Edge Cases

- User switches between classic and blocks
- Theme compatibility issues
- Other plugins modifying checkout
- Custom blocks modifications

### 4. Fallback Support

If blocks-specific features fail:
- Core validator still works
- Backend validation catches issues
- Standard VAT is fail-safe default

## Migration Guide

### From Classic to Blocks

No changes needed:
- Same backend validation
- Same AJAX endpoint
- Same tax class system
- Automatically detected and handled

### From Other VAT Plugins

1. Deactivate old plugin
2. Activate this implementation
3. Configure VATSense (optional)
4. Test both checkouts
5. Verify tax classes created

## Performance Considerations

### Classic vs Blocks

| Aspect | Classic | Blocks |
|--------|---------|--------|
| Initial load | Fast | Slower (React) |
| Field detection | Immediate | Delayed (observer) |
| Validation speed | Same | Same |
| Tax recalc | Fast | Slightly slower |

### Optimization Tips

1. **Debounce**: 800ms prevents excessive API calls
2. **AbortController**: Cancels outdated requests
3. **Cache**: 24h for valid, 1h for invalid
4. **Conditional loading**: Only load blocks script when needed

## Known Limitations

### Blocks-Specific

1. **React rendering delay**: Field may take 100-500ms to appear
2. **Store API structure**: Different from classic `$_POST`
3. **Session handling**: Needs careful management
4. **Custom blocks**: May need additional selectors

### Workarounds

All limitations have workarounds implemented:
- MutationObserver handles React delays
- Both `$_POST` and Store API supported in backend
- Session explicitly managed in validation
- Multiple selectors for field detection

## Debug Mode

### Enable Detailed Logging

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('SCRIPT_DEBUG', true); // Uses non-minified scripts
```

### Check Blocks Script Loaded

```javascript
// Browser console on checkout
console.log('Blocks loaded:', typeof window.ConecomVATBlocksValidator !== 'undefined');
console.log('Core loaded:', typeof window.ConecomVATValidator !== 'undefined');
console.log('WC Blocks:', typeof window.wc?.blocksCheckout !== 'undefined');
```

### Monitor Field Detection

```javascript
// See when field is detected
const checkField = setInterval(() => {
    const field = document.querySelector('[id*="billing_vat"]');
    if (field) {
        console.log('VAT field found:', field);
        console.log('Listener attached:', field.dataset.conecomVatListener);
        clearInterval(checkField);
    }
}, 500);
```

## Conclusion

This implementation provides **complete WooCommerce Blocks support** while maintaining backward compatibility with classic checkout. The dual-script approach ensures optimal performance and user experience in both checkout types, with the same validation logic and tax exemption rules.

## Related Documentation

- `VAT-Realtime-Validation.md` - Core validation system
- `VAT-Zero-Rate-B2B.md` - Tax exemption rules
- `VATSense-Integration.md` - Dual API system

