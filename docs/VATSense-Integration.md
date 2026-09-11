# VATSense Integration - Fallback API Service

## Description

Integration of **VATSense** as fallback VAT validation service. VIES (official EU service) is used as the primary service, and VATSense is used when VIES fails or is unavailable, providing higher reliability and availability.

## What is VATSense?

**VATSense** is a commercial VAT validation service that offers:

- ✅ Higher **reliability** than VIES
- ✅ Higher **availability** (99.9% uptime)
- ✅ **Faster** than VIES
- ✅ Supports **EU + Norway + Switzerland**
- ✅ **Free tier** available (500 validations/month)
- 🔗 Website: https://vatsense.com

## Fallback System

### Priority Order

```
1st VIES (official EU, free)
    ↓ If fails or unavailable
2nd VATSense (commercial fallback, if configured)
    ↓ If both fail
Accept VAT with warning (standard VAT applies)
```

### Validation Flow

```php
validate_vat_number($vat, $country)
    ↓
validate_with_vies($vat, $country) [primary - official & free]
    ↓
Successful? → Return VIES result
    ↓ NO/Failed
Is VATSense configured?
    ↓ YES
validate_with_vatsense($vat, $country) [fallback]
    ↓
Return VATSense result (if successful) or VIES result
    ↓ NO (not configured)
Return VIES result (failed)
```

## Implementation

### 1. Main Method with Fallback

```php
public static function validate_vat_number($vat_number, $country_code = '') {
    // Try VIES first (official and free)
    $result = self::validate_with_vies($vat_number, $country_code);

    // If VIES failed, try VATSense as fallback
    if (!$result['valid'] && self::is_vatsense_configured()) {
        self::log_debug('VIES validation failed, trying VATSense fallback');
        $vatsense_result = self::validate_with_vatsense($vat_number, $country_code);

        if (isset($vatsense_result['valid'])) {
            $vatsense_result['service_used'] = 'vatsense';
            return $vatsense_result;
        }
    }

    $result['service_used'] = 'vies';
    return $result;
}
```

### 2. VATSense Validation

```php
private static function validate_with_vatsense($vat_number, $country_code = '') {
    $api_key = self::get_vatsense_api_key();
    
    // Build API request
    $api_url = 'https://api.vatsense.com/1.0/validate?vat_number=' . $full_vat;
    
    $response = wp_remote_get($api_url, array(
        'headers' => array(
            'Authorization' => 'Basic ' . base64_encode('user:' . $api_key)
        )
    ));
    
    // Process response
    // Returns: ['valid' => bool, 'message' => string, 'name' => string, ...]
}
```

### 3. Admin Configuration

**Location:** WooCommerce → Settings → Connect Ecommerce → Public Settings

**Added field:**
```html
VATSense API Key (Optional)
[________________________]

VATSense is a commercial VAT validation service with higher 
reliability than VIES. Used as fallback if VIES fails. 
Also supports Norway and Switzerland.
Sign up for VATSense (free tier available).
```

## Configuration

### 1. Get API Key

1. Go to: https://vatsense.com/signup?referral=CLOSEMARKETING
2. Create account (free)
3. Copy API key from dashboard

### 2. Configure in WordPress

1. WooCommerce → Settings → Connect Ecommerce
2. "Public Settings" section
3. Paste API key in "VATSense API Key" field
4. Save changes

### 3. Verify Configuration

```php
// In PHP console or via debug
$is_configured = VAT::is_vatsense_configured();
// true = configured, false = not configured

$api_key = VAT::get_vatsense_api_key();
// Returns: 'your-api-key' or ''
```

## Use Cases

### Case 1: VIES Available (VATSense Not Needed)

```
Customer enters: FR12345678901
    ↓
VIES validates → Successful ✓
    ↓
Result: Valid (service: vies)
    ↓
VATSense NOT used (VIES succeeded)
```

**Log:**
```
[WooCommerce ES - VAT Debug] Using VIES as primary validation service
[WooCommerce ES - VAT Debug] VIES validation successful
```

### Case 2: VIES Down, VATSense Configured (Fallback Activated)

```
Customer enters: FR12345678901
    ↓
VIES validates → Error (service unavailable) ✗
    ↓
VATSense validates as fallback → Successful ✓
    ↓
Result: Valid (service: vatsense)
```

**Log:**
```
[WooCommerce ES - VAT Debug] Using VIES as primary validation service
[WooCommerce ES - VAT Debug] VIES validation failed
[WooCommerce ES - VAT Debug] VIES validation failed or returned invalid, trying VATSense fallback
[WooCommerce ES - VAT Debug] VATSense validation successful
```

### Case 3: VIES Down, VATSense Not Configured

```
Customer enters: FR12345678901
    ↓
VIES validates → Error (service unavailable) ✗
    ↓
VATSense NOT configured ✗
    ↓
Result: Failed validation
    ↓
Standard VAT applies
```

**Log:**
```
[WooCommerce ES - VAT Debug] Using VIES as primary validation service
[WooCommerce ES - VAT Debug] VIES validation failed
[WooCommerce ES - VAT Debug] VIES validation failed but VATSense not configured
```

### Case 4: Both Services Down

```
Customer enters: FR12345678901
    ↓
VIES → Error ✗
    ↓
VATSense (fallback) → Error ✗
    ↓
Result: Both services failed
    ↓
Standard VAT applies (fail-safe default)
```

**Log:**
```
[WooCommerce ES - VAT Debug] Using VIES as primary validation service
[WooCommerce ES - VAT Debug] VIES validation failed
[WooCommerce ES - VAT Debug] VIES validation failed or returned invalid, trying VATSense fallback
[WooCommerce ES - VAT Debug] VATSense validation failed
[WooCommerce ES - VAT Debug] Both services failed
```

## VATSense API

### Endpoint

```
GET https://api.vatsense.com/1.0/validate?vat_number={VAT}
```

### Authentication

```
Authorization: Basic base64(user:API_KEY)
```

### Successful Response

```json
{
    "success": true,
    "data": {
        "valid": true,
        "company_name": "Example SAS",
        "company_address": "123 Rue Example, Paris",
        "country_code": "FR",
        "vat_number": "12345678901"
    }
}
```

### Error Response

```json
{
    "success": false,
    "data": {
        "code": "invalid_vat",
        "error": {
            "title": "Invalid VAT number"
        }
    }
}
```

## VATSense Advantages as Fallback

| Feature | VIES | VATSense |
|---|---|---|
| **Cost** | Free | Freemium (500 free/month) |
| **Uptime** | ~95% | 99.9% |
| **Speed** | Variable (1-3s) | Fast (~500ms) |
| **Countries** | EU | EU + NO + CH |
| **Rate Limit** | Strict | Flexible |
| **Overloads** | Frequent | Rare |

## Cache

Both services use cache for optimization:

### VIES Cache

```php
// In wp_cache
Group: 'conecom_vat_validation'
Key: md5($country . $vat_number)
Duration: DAY_IN_SECONDS (24 hours)
```

### VATSense Cache

```php
// In transients
Key: 'vatsense_' . md5($full_vat)
Duration: 
- DAY_IN_SECONDS if valid
- HOUR_IN_SECONDS if invalid
```

## Pricing and Plans

### Free Plan
- 500 validations/month
- Ideal for small stores
- No credit card required

### Starter Plan ($19/month)
- 5,000 validations/month
- Email support
- For medium stores

### Business Plan ($49/month)
- 25,000 validations/month
- Priority support
- For large stores

## Debugging

### Check Which Service Was Used

```php
$result = VAT::validate_vat_number('FR12345678901', 'FR');
echo $result['service_used']; // 'vies' or 'vatsense'
```

### Detailed Logging

With `WP_DEBUG = true`:

```
[WooCommerce ES - VAT Debug] Validating VAT: FR12345678901
[WooCommerce ES - VAT Debug] Trying VIES first...
[WooCommerce ES - VAT Debug] VIES validation failed: Service unavailable
[WooCommerce ES - VAT Debug] VIES validation failed, trying VATSense fallback
[WooCommerce ES - VAT Debug] VATSense API call successful
[WooCommerce ES - VAT Debug] VAT is valid (verified by VATSense)
```

### Manual Test

```bash
# Test VATSense API directly
curl -X GET "https://api.vatsense.com/1.0/validate?vat_number=FR12345678901" \
  -H "Authorization: Basic $(echo -n 'user:YOUR_API_KEY' | base64)"
```

## Common Errors

### 1. Invalid API Key

**Error:**
```
VATSense authentication failed. Check API key.
```

**Solution:**
- Verify API key in VATSense dashboard
- Copy/paste correctly (no spaces)
- Regenerate key if necessary

### 2. Request Limit Reached

**Error:**
```
VATSense: Too many requests
```

**Solution:**
- Upgrade VATSense plan
- Use more aggressive caching
- Limit real-time validations

### 3. VATSense Down

**Error:**
```
VATSense service temporarily unavailable
```

**Solution:**
- System automatically accepts VAT
- Logged in error logs
- Check status: https://status.vatsense.com

## Monitoring

### Add Hook for Tracking

```php
add_action('conecom_vat_service_used', function($service, $vat, $result) {
    if ('vatsense' === $service) {
        // VATSense usage counter
        $count = get_option('vatsense_usage_count', 0);
        update_option('vatsense_usage_count', $count + 1);
    }
}, 10, 3);
```

### Dashboard Widget

```php
add_action('wp_dashboard_setup', function() {
    wp_add_dashboard_widget(
        'vatsense_usage',
        'VATSense Usage',
        function() {
            $count = get_option('vatsense_usage_count', 0);
            echo "VATSense validations this month: $count / 500";
        }
    );
});
```

## Migration

### If You Were Using Only VIES

No action needed:
- VIES remains the primary service
- VATSense is optional fallback only
- If you don't configure API key, works as before

### If You Want to Use Only VATSense

```php
// Force VATSense usage (not recommended)
add_filter('conecom_force_vatsense', '__return_true');
```

## Best Practices

1. **Use VIES as primary** (it's free and official)
2. **Configure VATSense as fallback** (increases reliability)
3. **Enable aggressive caching** (reduces API calls)
4. **Monitor VATSense usage** (avoid unexpected charges)
5. **Logging enabled** for debugging

## Conclusion

VATSense integration provides a **robust dual-validation system**:

- **VIES**: Primary, free, official
- **VATSense**: Fallback, fast, reliable

This minimizes validation failures and improves user experience, especially during VIES high-load periods (end of quarter, tax reports, etc.).

## Useful Links

- **VATSense Sign up**: https://vatsense.com/signup?referral=CLOSEMARKETING
- **API Documentation**: https://vatsense.com/docs
- **Status Page**: https://status.vatsense.com
- **Pricing**: https://vatsense.com/pricing

