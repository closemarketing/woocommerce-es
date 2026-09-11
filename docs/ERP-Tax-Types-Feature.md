# ERP Tax Type Column Feature

## Overview

This feature adds an **ERP Tax Type** column to the WooCommerce Tax Rates management table. This allows administrators to map WooCommerce tax rates to corresponding tax types in their connected ERP system (Odoo, Clientify, Neo, etc.).

## Features

- **Custom Database Column**: Adds `erp_tax_type` VARCHAR(50) column to `woocommerce_tax_rates` table
- **Dynamic ERP Tax Loading**: Fetches available tax types from the connected ERP via API
- **Select Dropdown Interface**: User-friendly select dropdown in the WooCommerce tax rates table
- **Auto-save Functionality**: Automatically saves changes when a tax type is selected
- **AJAX-powered**: All operations (load, save, retrieve) use AJAX for smooth user experience
- **Multi-ERP Support**: Works with any connected ERP that implements the `get_taxes()` method

## Database Schema

### Migration

The feature automatically creates the following column in the `woocommerce_tax_rates` table:

| Column Name | Type | Description |
|-------------|------|-------------|
| `erp_tax_type` | VARCHAR(50) | Stores the selected ERP tax type ID or code |

## Usage

### For Administrators

1. Navigate to **WooCommerce → Settings → Tax**
2. Select a tax class (Standard, Reduced Rate, etc.)
3. In the tax rates table, you'll see a new **ERP Tax Type** column
4. Click the dropdown in each row to select the corresponding ERP tax type
5. The selection is automatically saved

### For Developers

#### Implementing `get_taxes()` Method

To support this feature in your ERP connector, implement the `get_taxes()` method:

```php
/**
 * Gets taxes from ERP
 *
 * @return array Array of taxes with id, name, and code.
 */
public function get_taxes() {
    // Fetch taxes from your ERP API
    $taxes_data = $this->api_call_to_get_taxes();
    
    // Format as array of arrays with id, name, code
    $formatted_taxes = array();
    foreach ( $taxes_data as $tax ) {
        $formatted_taxes[] = array(
            'id'   => $tax['id'],   // Unique identifier
            'name' => $tax['name'], // Display name
            'code' => $tax['code'], // Optional tax code
        );
    }
    
    return $formatted_taxes;
}
```

#### Example: Odoo Implementation

```php
public function get_taxes() {
    $taxes_data = $this->get_odoo_data_by_model( 'account.tax' );
    
    if ( empty( $taxes_data ) || isset( $taxes_data['status'] ) && 'error' === $taxes_data['status'] ) {
        return array();
    }

    $formatted_taxes = array();
    foreach ( $taxes_data as $tax ) {
        $formatted_taxes[] = array(
            'id'   => isset( $tax['id'] ) ? $tax['id'] : 0,
            'name' => isset( $tax['name'] ) ? $tax['name'] : '',
            'code' => isset( $tax['description'] ) ? $tax['description'] : ( isset( $tax['name'] ) ? $tax['name'] : '' ),
        );
    }

    return $formatted_taxes;
}
```

## API Reference

### AJAX Endpoints

#### Get ERP Taxes

**Action**: `conecom_get_erp_taxes`

**Method**: POST

**Parameters**:
- `nonce`: Security nonce

**Response**:
```json
{
  "success": true,
  "data": {
    "taxes": [
      {
        "id": "1",
        "name": "IVA 21%",
        "code": "IVA21"
      },
      {
        "id": "2",
        "name": "IVA 10%",
        "code": "IVA10"
      }
    ]
  }
}
```

#### Save ERP Tax Type

**Action**: `conecom_save_erp_tax_type`

**Method**: POST

**Parameters**:
- `nonce`: Security nonce
- `tax_rate_id`: WooCommerce tax rate ID
- `erp_tax_type`: Selected ERP tax type ID/code

**Response**:
```json
{
  "success": true,
  "data": {
    "message": "ERP tax type saved successfully."
  }
}
```

#### Get ERP Tax Type

**Action**: `conecom_get_erp_tax_type`

**Method**: POST

**Parameters**:
- `nonce`: Security nonce
- `tax_rate_id`: WooCommerce tax rate ID

**Response**:
```json
{
  "success": true,
  "data": {
    "erp_tax_type": "1"
  }
}
```

## Files Created/Modified

### New Files

- `includes/Admin/ERP_Tax_Types.php` - Main admin class handling database, UI, and AJAX
- `includes/assets/erp-tax-types.js` - Frontend JavaScript for select functionality
- `docs/ERP-Tax-Types-Feature.md` - This documentation file

### Modified Files

- `includes/Plugin_Main.php` - Added ERP_Tax_Types initialization
- `includes/Connector/class-api-clientify.php` - Added get_taxes() method
- `connect-ecommerce-odoo/includes/class-api-odoo.php` - Added get_taxes() implementation

## Troubleshooting

### Column Not Appearing

**Problem**: The ERP Tax Type column doesn't appear in the tax rates table.

**Solution**: 
1. Check that you're on the WooCommerce → Settings → Tax page
2. Make sure a tax class is selected (not the main tax tab)
3. Clear your browser cache and refresh the page
4. Check browser console for JavaScript errors

### No Tax Types in Dropdown

**Problem**: The select dropdown shows "Select ERP Tax Type" but no options.

**Solution**:
1. Verify that an ERP is configured in Connect WooCommerce settings
2. Ensure the ERP connector has the `get_taxes()` method implemented
3. Check browser console for AJAX errors
4. Verify ERP API connection is working

### Changes Not Saving

**Problem**: Selected tax type doesn't persist after page reload.

**Solution**:
1. Check browser console for AJAX errors
2. Verify database column exists: `SELECT * FROM wp_woocommerce_tax_rates LIMIT 1`
3. Check user permissions (must have `manage_woocommerce` capability)
4. Review server error logs for PHP errors

## Support

For issues related to this feature:

1. Check the [GitHub Issues](https://github.com/closemarketing/woocommerce-es/issues)
2. Contact support at <david@closemarketing.es>
3. Visit [Close Technology](https://close.technology/)

## Changelog

### Version 3.2.1
- Initial implementation of ERP Tax Type column feature
- Added database migration for `erp_tax_type` column
- Implemented AJAX handlers for loading and saving tax types
- Added JavaScript for dynamic select functionality
- Added `get_taxes()` method to Odoo and Clientify connectors


