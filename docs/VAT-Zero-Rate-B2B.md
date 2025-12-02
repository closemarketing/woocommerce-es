# VAT Zero-Rate for B2B Intra-Community Transactions

## Description

Implementation of VAT exemption (0% rate) for intra-community B2B transactions, according to European Union regulations. When a company from one EU country sells to another company from a different EU country with a valid VAT number, the reverse charge mechanism applies.

## Requirements for Zero-Rate Application

For VAT exemption to apply, **all** these conditions must be met:

1. ✅ **Store country** must be in the EU
2. ✅ **Customer country** must be in the EU
3. ✅ **Different countries** (store ≠ customer)
4. ✅ **VAT number provided** by customer
5. ✅ **VAT validated** correctly via VIES

## Operation Flow

### 1. Customer Enters VAT

```
Customer types VAT at checkout
    ↓
Real-time validation (JavaScript)
    ↓
AJAX → ajax_validate_vat()
    ↓
VIES Validation
```

### 2. Exemption Evaluation

```php
should_apply_vat_exemption($customer_country, $vat_number, $is_validated)
    ↓
Check: Store country in EU?
    ↓
Check: Customer country in EU?
    ↓
Check: Different countries?
    ↓
Check: Valid VAT?
    ↓
Return: true/false
```

### 3. Exemption Application

If all conditions are met:

```php
apply_vat_exemption($customer_country, $vat_number, true)
    ↓
WC()->customer->set_is_vat_exempt(true)
    ↓
WC()->session->set('vat_exempt_applied', true)
    ↓
Recalculate checkout (taxes = 0)
```

### 4. Customer Response

JavaScript updates UI:
- ✓✓ **Special green message**: "B2B intra-community transaction - Zero VAT rate applied (XX)"
- 🔄 **Recalculates totals**: `update_checkout` trigger
- 💾 **Saves in session** for persistence

### 5. Save to Order

At checkout completion:

```php
save_vat_validation_result($order_id)
    ↓
update_post_meta(_vat_exempt_applied, 'yes')
update_post_meta(_vat_exempt_country, $country)
update_post_meta(_vat_exempt_vat_number, $vat)
    ↓
$order->add_order_note("VAT exemption applied...")
```

## Main Methods

### Backend (PHP)

#### `VAT::should_apply_vat_exemption()`

Determines if exemption should apply.

```php
public static function should_apply_vat_exemption( 
    $customer_country, 
    $vat_number, 
    $is_validated = false 
) {
    // Returns: bool
}
```

**Logic:**
1. Gets store base country
2. Verifies both countries are in EU
3. Verifies countries are different
4. Verifies VAT is validated

#### `VAT::apply_vat_exemption()`

Applies VAT exemption to current customer.

```php
public static function apply_vat_exemption( 
    $customer_country, 
    $vat_number, 
    $is_validated 
) {
    // Sets customer as VAT exempt
    // Stores in session
}
```

**Effects:**
- `WC()->customer->set_is_vat_exempt(true)`
- Session updated with exemption data
- Automatic tax recalculation

#### `VAT::remove_vat_exemption()`

Removes VAT exemption.

```php
public static function remove_vat_exemption() {
    // Removes exemption
    // Clears session
}
```

#### `VAT::is_customer_vat_exempt()`

Checks if current customer has exemption.

```php
public static function is_customer_vat_exempt() {
    // Returns: bool
}
```

#### `VAT::get_vat_exemption_info()`

Gets current exemption information.

```php
public static function get_vat_exemption_info() {
    // Returns: array|null
    // [
    //     'country' => 'FR',
    //     'vat_number' => 'FR12345678901'
    // ]
}
```

### Frontend (JavaScript)

#### Extended AJAX Response

```javascript
{
    status: 'valid',
    message: 'VAT number is valid',
    country_code: 'FR',
    vat_number: '12345678901',
    company_name: 'Example SAS',
    vat_exempt: true,  // ← NEW
    exempt_message: 'B2B intra-community transaction - Zero VAT rate applied (FR)'  // ← NEW
}
```

#### Checkout Update

```javascript
updateCheckoutTotals() {
    // Trigger WooCommerce checkout update
    jQuery('body').trigger('update_checkout');
}
```

Called automatically when:
- VAT validates as correct and exemption applies
- VAT changes to invalid and exemption is removed

## Usage Examples

### Example 1: Store in Spain, Customer in France

**Configuration:**
- Base country: ES
- Customer country: FR
- VAT: FR12345678901 (valid)

**Result:**
```
✅ Exemption applied
Tax: 0%
Message: "B2B intra-community transaction - Zero VAT rate applied (FR)"
```

### Example 2: Store in Spain, Customer in Spain

**Configuration:**
- Base country: ES
- Customer country: ES
- VAT: ESB12345678 (valid)

**Result:**
```
❌ Exemption NOT applied
Tax: 21% (standard)
Reason: Same country (domestic transaction)
```

### Example 3: Store in Spain, Customer in USA

**Configuration:**
- Base country: ES
- Customer country: US
- VAT: Not applicable

**Result:**
```
❌ Exemption NOT applied
Tax: 0% (export)
Reason: USA not in EU
```

### Example 4: Store in Spain, Customer in France (Invalid VAT)

**Configuration:**
- Base country: ES
- Customer country: FR
- VAT: FR99999999999 (invalid)

**Result:**
```
❌ Exemption NOT applied
Tax: 21%
Reason: VAT not validated correctly
```

## Order Metadata

When exemption is applied, these metadata are saved:

```php
// Metadata in wp_postmeta
_vat_exempt_applied: 'yes'
_vat_exempt_country: 'FR'
_vat_exempt_vat_number: 'FR12345678901'
_vat_validation_result: [complete array]
_vat_number_validated: 'yes'
_vat_validation_date: '2025-01-15'
```

## Order Notes

A note is added to the order:

```
VAT exemption applied for B2B intra-community transaction. 
VAT: FR12345678901 (FR)
```

## Special CSS Styles

When there's exemption, visual feedback is special:

```css
.conecom-vat-feedback.valid.vat-exempt {
    font-weight: 500;
}

.conecom-vat-feedback.valid.vat-exempt::before {
    content: '✓✓';  /* Double check */
    color: #059669;
}
```

## WooCommerce Integration

### Process Hooks

```php
// Classic checkout
add_action('woocommerce_after_checkout_validation', 
    array($this, 'validate_vat_number_checkout'), 10, 2);

// Checkout Blocks
add_action('woocommerce_store_api_checkout_update_order_from_request',
    array($this, 'validate_vat_number_checkout_blocks'), 10, 2);

// Save to order
add_action('woocommerce_checkout_order_processed',
    array($this, 'save_vat_validation_result'), 10, 1);
```

### Tax System

WooCommerce uses `$customer->get_is_vat_exempt()` to:

1. **Calculate taxes**: If `true`, applies 0% rate
2. **Show totals**: Shows "VAT exempt" line
3. **Invoices**: Indicates exemption on documents

## Testing

### Manual Test

1. **Configure store in EU country** (e.g., Spain)
2. **At checkout:**
   - Country: Other EU country (e.g., France)
   - VAT: Valid French VAT (e.g., FR12345678901)
3. **Verify:**
   - ✓✓ Green exemption message
   - Tax = 0€
   - Total without VAT

### Programmatic Test

```php
// Test should_apply_vat_exemption
$result = VAT::should_apply_vat_exemption('FR', 'FR12345678901', true);
// Expected: true (if store is ES or other different EU country)

// Test apply_vat_exemption
VAT::apply_vat_exemption('FR', 'FR12345678901', true);
$is_exempt = VAT::is_customer_vat_exempt();
// Expected: true

// Test remove_vat_exemption
VAT::remove_vat_exemption();
$is_exempt = VAT::is_customer_vat_exempt();
// Expected: false
```

### AJAX Test

```javascript
// In browser console (checkout page)
fetch(ajaxurl, {
    method: 'POST',
    body: new FormData()
        .set('action', 'conecom_validate_vat')
        .set('security', nonce)
        .set('vat_number', 'FR12345678901')
        .set('country_code', 'FR')
})
.then(r => r.json())
.then(data => {
    console.log('VAT Exempt:', data.data.vat_exempt);
    // Expected: true
});
```

## Legal Considerations

### European Regulation

This implementation follows **VAT Directive 2006/112/EC**:

- **Article 194**: Reverse charge
- **Article 196**: Exempt intra-community supplies
- **Article 138**: Conditions for exemption

### Seller Obligations

1. ✅ **Validate VAT**: Mandatory via VIES
2. ✅ **Save proof**: Record validation and date
3. ✅ **Correct invoice**: Indicate "Reverse charge"
4. ✅ **Declaration**: Include in VAT declaration (model 303/349)

### Liability

- **If VAT is valid**: Legitimate exemption
- **If VAT is invalid but exemption was applied**: Tax risk for seller
- **Therefore VIES validation is MANDATORY**

## Troubleshooting

### Exemption not applying

**Verify:**
1. Is store country in `get_eu_countries()`?
2. Is customer country in `get_eu_countries()`?
3. Are they different countries?
4. Was VAT validated correctly?
5. Is `$validation_result['valid']` `true`?

**Debug:**
```php
add_action('wp_footer', function() {
    if (is_checkout()) {
        $info = VAT::get_vat_exemption_info();
        echo '<pre>VAT Exemption Info: ';
        var_dump($info);
        echo '</pre>';
    }
});
```

### Taxes not recalculating

**Verify:**
1. Is `jQuery('body').trigger('update_checkout')` called?
2. Are there conflicts with other tax plugins?
3. Is cache active preventing recalculation?

**Solution:**
```javascript
// Force recalculation
jQuery('body').trigger('update_checkout', {update_shipping_method: true});
```

### Exemption persists after changing country

**Cause:** Session not cleared.

**Solution:** Add listener for country change:

```php
add_action('woocommerce_checkout_update_order_review', function($post_data) {
    parse_str($post_data, $data);
    $country = $data['billing_country'] ?? '';
    
    // If country changes, reevaluate exemption
    $prev_country = WC()->session->get('vat_exempt_country');
    if ($country !== $prev_country) {
        VAT::remove_vat_exemption();
    }
});
```

## Performance

- **VIES Validation**: 500-1000ms
- **Apply exemption**: <5ms
- **Recalculate taxes**: 50-200ms
- **Total perceived**: ~1 second

## Security

1. ✅ **Nonce verification** in AJAX
2. ✅ **Input sanitization**
3. ✅ **Backend validation** (not just frontend)
4. ✅ **VIES results cache** (24h)
5. ✅ **Logging** of exemption applications

## Debugging

### Enable Debug Logs

In `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### View Logs

```bash
tail -f /path/to/wp-content/debug.log
```

### Search for Lines

```
[WooCommerce ES - VAT Debug] VAT Exemption Check
```

### Complete Log Example

#### Case Spain → Spain (Should NOT exempt)
```
[WooCommerce ES - VAT Debug] VAT Exemption Check - Base: ES, Customer: ES, VAT: ESB12345678, Validated: yes
[WooCommerce ES - VAT Debug] VAT Exemption NOT applied - Same country (domestic transaction)
```

#### Case Spain → France (Should exempt)
```
[WooCommerce ES - VAT Debug] VAT Exemption Check - Base: ES, Customer: FR, VAT: FR12345678901, Validated: yes
[WooCommerce ES - VAT Debug] VAT Exemption APPLIED - All conditions met
```

## Conclusion

This implementation provides a complete and EU regulation-compliant solution for managing intra-community B2B transactions, automatically applying 0% VAT rate when appropriate and saving all necessary information to comply with tax obligations.

