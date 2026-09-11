# Multi-connector Support

Connect Ecommerce now lets you configure and store multiple connectors, each with its own credentials, behaviour toggles, and workflow assignments. This document explains how to use the new admin UI and how developers can register additional connector types.

## Managing connectors in the admin UI

1. Open **WooCommerce → Connect Ecommerce → Settings → Connection**.
2. Use the **Connectors** card to:
   - Rename existing connectors, toggle product/order workflows, or mark a connector as inactive.
   - Remove connectors that are no longer needed (settings are erased when removed).
   - Add a new connector by selecting its type, giving it a label, and optionally providing a custom identifier.
3. Pick the connector you want to configure from the dropdown at the top of the page. Only the selected connector’s settings fields are displayed.
4. Save the form to persist changes. Each connector keeps its own set of credentials and automation flags.

> **Tip:** The active connector is highlighted in the table. All synchronization screens continue to operate using the active connector’s configuration for backwards compatibility.

## Workflow flags

Every connector stores flags that describe which workflows it participates in:

| Workflow | Description |
| --- | --- |
| Products | Enables the connector for product synchronisation (manual or cron). |
| Orders | Enables the connector for order submission and invoice downloads. |

The current release still executes tasks sequentially using the active connector, but the workflow flags make it easy to extend the behaviour (e.g. by iterating through connectors flagged as `products`).

## Registering custom connector types

Connector types are registered via the existing `conecom_options_plugin` filter, so third parties can expose new ERPs/CRMs without touching the core plugin. Each definition must expose at least a `name`, `slug`, and any capability flags used by the UI.

```php
add_filter( 'conecom_options_plugin', function( $options ) {
	$options['myerp'] = array(
		'name'                       => 'My ERP',
		'slug'                       => 'conecom-myerp',
		'plugin_name'                => 'Connect WooCommerce MyERP',
		'plugin_slug'                => 'connect-ecommerce-myerp',
		'api_pagination'             => 100,
		'product_price_tax_option'   => true,
		'product_option_stock'       => true,
		'settings_admin_message'     => __( 'Enter the API credentials provided by MyERP.', 'woocommerce-es' ),
		'settings_fields'            => array( 'apipassword', 'username' ),
	);

	return $options;
} );
```

After registering the definition and loading its API class (`Connect_Ecommerce_MyERP`), the connector will appear in the admin **Add connector** dropdown and can be configured multiple times with different identifiers.
