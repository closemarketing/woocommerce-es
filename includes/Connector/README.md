# Connector API Documentation

## Creating a New Connector

All ERP/CRM connectors **must** extend the `CONECOM_Abstract_Connector_API` class to ensure compatibility and consistency.

### Required Setup

1. **Extend the Abstract Class**

```php
class Connect_Ecommerce_YourERP extends CONECOM_Abstract_Connector_API {
    // Your implementation
}
```

2. **Constructor**

The constructor receives two parameters:
- `$options` (array): All connector options from the filter
- `$connector_id` (string): The connector identifier

```php
public function __construct( $options, $connector_id = '' ) {
    parent::__construct( $options, $connector_id );
    
    // Your custom initialization
    // Access settings: $this->settings
    // Access options: $this->options
}
```

### Required Methods (Abstract)

These methods **must** be implemented:

#### 1. `check_can_sync( $settings = array() )`
Tests if the API connection is valid.

**Returns:**
- `true` if connection is successful
- `array` with `['status' => 'ok/error', 'message' => 'Description']`

**Example:**
```php
public function check_can_sync( $settings = array() ) {
    if ( ! empty( $settings ) ) {
        $this->settings = $settings;
    }
    
    $result = $this->api_call( 'test/connection' );
    
    if ( isset( $result['error'] ) ) {
        return array(
            'status'  => 'error',
            'message' => $result['error'],
        );
    }
    
    return array(
        'status'  => 'ok',
        'message' => __( 'Connection successful!', 'your-text-domain' ),
    );
}
```

#### 2. `get_products( $product_id = null, $loop = 0 )`
Retrieves products from the ERP/CRM.

**Parameters:**
- `$product_id`: Specific product ID or null for all
- `$loop`: Pagination number (0 = first page)

**Returns:** Array of products

**Example:**
```php
public function get_products( $product_id = null, $loop = 0 ) {
    if ( $product_id ) {
        return $this->api_call( 'products/' . $product_id );
    }
    
    $page = $loop + 1;
    return $this->api_call( 'products?page=' . $page );
}
```

#### 3. `create_order( $order_data )`
Creates an order/invoice in the ERP/CRM.

**Parameters:**
- `$order_data`: Complete order data array

**Returns:** Array with status and message

**Example:**
```php
public function create_order( $order_data ) {
    $result = $this->api_call( 'orders', 'POST', $order_data );
    
    if ( isset( $result['id'] ) ) {
        return array(
            'status'  => 'ok',
            'message' => sprintf( __( 'Order created: %s', 'your-text-domain' ), $result['id'] ),
            'id'      => $result['id'],
        );
    }
    
    return array(
        'status'  => 'error',
        'message' => __( 'Failed to create order', 'your-text-domain' ),
    );
}
```

#### 4. `get_taxes()`
Retrieves available tax rates.

**Returns:** Array of taxes

**Example:**
```php
public function get_taxes() {
    $result = $this->api_call( 'taxes' );
    
    $taxes = array();
    foreach ( $result as $tax ) {
        $taxes[] = array(
            'id'   => $tax['id'],
            'name' => $tax['name'],
            'rate' => $tax['percentage'],
        );
    }
    
    return $taxes;
}
```

### Optional Methods

These methods have default implementations but can be overridden:

- `get_product_by_sku( $sku )` - Get product by SKU
- `get_product_attributes()` - Get custom product attributes
- `get_payment_methods()` - Get available payment methods
- `get_image_product()` - Get product image URL
- `get_url_link_api()` - Get dashboard URL
- `get_attributes()` - Legacy compatibility
- `get_companies()` - Get companies list (multi-company ERPs)
- `get_treasury_accounts()` - Get bank accounts
- `get_categories()` - Get product categories
- `update_stock( $product_id, $quantity )` - Update stock

### Plugin Structure

Your connector plugin should:

1. **Define connector options** via filter:

```php
add_filter( 'conecom_options_plugin', 'your_connector_options' );
function your_connector_options( $options = array() ) {
    $options['yourerp'] = array(
        'name'                       => 'YourERP',
        'slug'                       => 'connwoo_yourerp',
        'version'                    => '1.0.0',
        'plugin_name'                => 'Connect WooCommerce YourERP',
        'plugin_slug'                => 'connect-ecommerce-yourerp',
        'api_pagination'             => 100,
        'product_price_tax_option'   => true,
        'settings_fields'            => array( 'apipassword' ),
        // ... more options
    );
    return $options;
}
```

2. **Load the Abstract Class**:

```php
// Load CONECOM Abstract Connector API if available
if ( defined( 'CONECOM_PLUGIN_PATH' ) && file_exists( CONECOM_PLUGIN_PATH . 'includes/Connector/Abstract_Connector_API.php' ) ) {
    require_once CONECOM_PLUGIN_PATH . 'includes/Connector/Abstract_Connector_API.php';
}

require_once YOUR_PLUGIN_PATH . '/includes/class-api-erp-yourerp.php';
```

3. **Require woocommerce-es** in plugin header:

```php
* Requires Plugins: woocommerce-es
```

### Helper Methods

The abstract class provides these helper methods:

- `get_connector_name()` - Get connector display name
- `get_connector_slug()` - Get connector slug
- `has_method( $method )` - Check if method exists

### Error Handling

Always return proper error arrays:

```php
return array(
    'status'  => 'error',
    'message' => __( 'Error description', 'your-text-domain' ),
);
```

### Testing

PHP will enforce that all abstract methods are implemented. If you forget a method, you'll get:

```
Fatal error: Class Connect_Ecommerce_YourERP contains X abstract methods and must therefore be declared abstract or implement the remaining methods
```

This ensures you never forget to implement required functionality! ✅

### Example Implementation

See `class-api-clientify.php` and `class-api-erp-facturadirecta.php` for complete examples.

---

**Questions?** Check the inline documentation in `Abstract_Connector_API.php` or contact the development team.

### Class Naming Convention

The abstract class uses the `CONECOM_` prefix to follow WordPress coding standards and avoid naming conflicts with other plugins or themes.
