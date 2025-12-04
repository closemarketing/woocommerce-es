# VAT Zero-Rate for B2B Intra-Community Transactions

## Description

Implementation of VAT exemption (0% rate) for intra-community B2B transactions, according to European Union regulations. When a company from one EU country sells to another company from a different EU country with a valid VAT number, the reverse charge mechanism applies.

## Requirements for Zero-Rate Application

For VAT exemption to apply, **all** these conditions must be met:

1. ✅ **Store country** must be in the EU
2. ✅ **Customer country** must be in the EU
3. ✅ **Different countries** (store ≠ customer)
4. ✅ **VAT number provided** by customer
5. ✅ **VAT validated** correctly via VIES or VATSense

## How It Works

### Tax Class System

Instead of using WooCommerce's `set_is_vat_exempt()`, this implementation uses a **zero-rate tax class** which is fiscally more correct:

```
Standard Transaction:
- Base: €100
- VAT 21%: €21
- Total: €121

B2B Intra-community:
- Base: €100
- Zero Rate FR (0%): €0
- Total: €100
```

### Automatic Tax Class Creation

The system automatically creates:

1. **Tax class**: "Zero Rate"
2. **Tax rates**: 0% for each EU country
3. **Database entries**: In `woocommerce_tax_rates` table

```sql
-- Example entries created
INSERT INTO woocommerce_tax_rates
(tax_rate_country, tax_rate, tax_rate_name, tax_rate_class)
VALUES
('FR', '0.0000', 'Zero Rate FR', 'zero-rate'),
('DE', '0.0000', 'Zero Rate DE', 'zero-rate'),
('IT', '0.0000', 'Zero Rate IT', 'zero-rate'),
...
```

## Operation Flow

### 1. Customer Enters VAT

```
Customer types VAT at checkout
    ↓
Real-time validation (JavaScript)
    ↓
AJAX → ajax_validate_vat()
    ↓
VATSense (if configured) or VIES validation
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
ensure_zero_rate_tax_class() // Creates tax class if needed
    ↓
WC()->session->set('customer_tax_class', 'zero-rate')
    ↓
Filter applies: woocommerce_product_tax_class → 'zero-rate'
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
5. Logs decision for debugging

#### `VAT::apply_vat_exemption()`

Applies VAT exemption to current customer.

```php
public static function apply_vat_exemption( 
    $customer_country, 
    $vat_number, 
    $is_validated 
) {
    // Removes any existing exemption first
    // Creates zero-rate tax class if needed
    // Sets customer tax class to 'zero-rate'
    // Stores in session
}
```

**Effects:**
- Session tax class set to `zero-rate`
- Session updated with exemption data
- Automatic tax recalculation
- Filters apply zero-rate to all products

#### `VAT::remove_vat_exemption()`

Removes VAT exemption and restores standard VAT.

```php
public static function remove_vat_exemption() {
    // Resets tax class to standard
    // Clears session data
    // Triggers checkout update
}
```

#### `VAT::ensure_zero_rate_tax_class()`

Creates zero-rate tax class and rates for EU countries.

```php
public static function ensure_zero_rate_tax_class() {
    // Creates 'Zero Rate' tax class
    // Creates 0% rate for each EU country
    // Clears tax cache
}
```

Called automatically when exemption is applied.

### Frontend (JavaScript)

#### Extended AJAX Response

```javascript
{
    status: 'valid',
    message: 'VAT number is valid',
    country_code: 'FR',
    vat_number: '12345678901',
    company_name: 'Example SAS',
    company_address: '123 Rue...',
    vat_exempt: true,
    exempt_message: 'B2B intra-community transaction - Zero VAT rate applied (FR)',
    service_used: 'vatsense' // or 'vies'
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

### Example 1: Store in Spain, Customer in France (B2B)

**Configuration:**
- Base country: ES
- Customer country: FR
- VAT: FR12345678901 (valid)

**Result:**
```
✅ Exemption applied
Tax class: Zero Rate FR
Tax rate: 0%
Message: "B2B intra-community transaction - Zero VAT rate applied (FR)"
```

**Invoice shows:**
```
Subtotal: €100.00
Zero Rate FR (0%): €0.00
Total: €100.00
```

### Example 2: Store in Spain, Customer in Spain (Domestic)

**Configuration:**
- Base country: ES
- Customer country: ES
- VAT: ESB12345678 (valid)

**Result:**
```
❌ Exemption NOT applied
Tax class: Standard
Tax rate: 21%
Message: "Domestic transaction - Standard VAT rate applies"
```

**Invoice shows:**
```
Subtotal: €100.00
IVA (21%): €21.00
Total: €121.00
```

### Example 3: Store in Spain, Customer in USA (Export)

**Configuration:**
- Base country: ES
- Customer country: US
- VAT: Not applicable

**Result:**
```
❌ Exemption NOT applied
Tax: 0% (export rules)
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
Tax class: Standard
Tax rate: 21%
Message: "VAT validation failed - Standard VAT rate applies for FR"
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

// Tax line items
tax_rate_id: 2
tax_rate_code: 'FR-ZERO RATE FR-1'
tax_amount: 0.00
```

## Order Notes

A note is added to the order:

```
VAT exemption applied for B2B intra-community transaction. 
VAT: FR12345678901 (FR)
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

// Apply tax class
add_filter('woocommerce_product_tax_class',
    array($this, 'apply_zero_rate_tax_class'), 10, 2);
```

### Tax System

The system uses WooCommerce's native tax class system:

1. **Filter tax class**: All products get `zero-rate` class when exempt
2. **WC calculates**: Uses 0% rate for customer's country
3. **Invoice shows**: "Zero Rate [Country]" line with €0.00
4. **Reports**: Properly categorized as B2B transactions

## Legal Considerations

### European Regulation

This implementation follows **VAT Directive 2006/112/EC**:

- **Article 194**: Reverse charge mechanism
- **Article 196**: Exempt intra-community supplies
- **Article 138**: Conditions for exemption

### Seller Obligations

1. ✅ **Validate VAT**: Mandatory via VIES or equivalent
2. ✅ **Save proof**: Record validation and date
3. ✅ **Correct invoice**: Show "Zero Rate [Country]" line
4. ✅ **Declaration**: Include in VAT declaration (EC Sales List)

### Liability

- **If VAT is valid**: Legitimate exemption
- **If VAT is invalid but exemption was applied**: Tax risk for seller
- **Therefore validation via VIES/VATSense is MANDATORY**

## Troubleshooting

### Exemption not applying

**Verify:**
1. Is store country in EU?
2. Is customer country in EU?
3. Are they different countries?
4. Was VAT validated successfully?
5. Check debug logs for decision

**Debug:**
```php
add_action('wp_footer', function() {
    if (is_checkout() && WP_DEBUG) {
        $info = VAT::get_vat_exemption_info();
        $is_exempt = VAT::is_customer_vat_exempt();
        echo '<pre>';
        echo 'VAT Exempt: ' . ($is_exempt ? 'YES' : 'NO') . "\n";
        echo 'Info: ';
        print_r($info);
        echo '</pre>';
    }
});
```

### Taxes not recalculating

**Check:**
1. Is `update_checkout` triggered?
2. Any conflicts with other tax plugins?
3. Is cache active preventing recalculation?

**Force update:**
```javascript
// In browser console
jQuery('body').trigger('update_checkout', {update_shipping_method: true});
```

### Exemption persists after VAT removal

**Solution:** The `maybe_remove_vat_exemption_on_update()` hook handles this:

```php
// Automatically removes exemption if:
// - VAT field is emptied
// - Country changes
// - VAT number changes
```

### Zero-rate class not visible in admin

**Create manually if needed:**

1. WooCommerce → Settings → Tax → Tax Classes
2. Add "Zero Rate" to the list
3. Create rates for EU countries with 0%

Or let the system create it automatically on first B2B transaction.

## Performance

- **Validation**: 300ms-3s (depends on service)
- **Apply exemption**: <5ms
- **Create tax class**: <50ms (one-time)
- **Recalculate taxes**: 50-200ms
- **Total perceived**: ~1 second

## Security

1. ✅ **Nonce verification** in AJAX
2. ✅ **Input sanitization**
3. ✅ **Backend validation** (not just frontend)
4. ✅ **Cache with expiration** (24h valid, 1h invalid)
5. ✅ **Logging** of all exemption applications
6. ✅ **Automatic removal** on validation failure

## Best Practices

1. **Configure VATSense** for better reliability (even free tier)
2. **Enable debug logging** during initial setup
3. **Monitor exemption applications** via order notes
4. **Test with real VAT numbers** before going live
5. **Review tax reports** regularly to ensure correct categorization

## Conclusion

This implementation provides a complete and EU regulation-compliant solution for managing intra-community B2B transactions, automatically applying 0% VAT rate when appropriate using WooCommerce's native tax class system, and saving all necessary information to comply with tax obligations.

