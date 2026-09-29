# Connect Ecommerce and EU/VAT Compliance

Add VAT Fields, Import European Taxes and check VAT compliance. Connect WooCommerce with ERPs and CRMs. Products, Clients and Orders with ERP/CRM.

- Add VAT info in forms fields, Orders, and email notification (Gutenberg compatible).
- EU/VAT Compliance: Import European Taxes and check VAT compliance.
- (optional) Connect your WooCommerce store to your ERP or CRM software. This plugin makes it easy to connect your store by synchronizing products, customers, and orders.
- Save hours of administrative work by eliminating the need to manually enter products, customers, and orders.
- You can now use AI to generate product marketing descriptions based on information from your ERP/CRM.
- There’s no need for additional plugins to request VAT numbers from companies — this plugin has it covered.
- This plugin is fully GDPR compliant. The synchronization between WooCommerce and your ERP/CRM is established through a direct connection, without intermediaries or third-party storage of personal data. This ensures maximum security and transparency, keeping customer information under your full control.
- This plugin also includes specific adjustments to comply with Verifactu regulations. Order and invoice data are processed and structured to meet the official requirements, ensuring your business adheres to current legal standards.

Plugin WordPress url: https://wordpress.org/plugins/woocommerce-es/

## Developers

### Addon for Connect Ecommerce
You will need to use this code in order to import the library.

```
/**
 * Default values
 */
global $wpdb;

$connwoo_options_erp = array(
	'name'                       => 'ERP',
	'slug'                       => 'connwoo_erp',
	'version'                    => PREFIX_VERSION,
	'plugin_name'                => 'Connect WooCommerce ERP',
	'plugin_slug'                => 'connect-woocommerce-erp',
	'api_url'                    => PREFIX_SHOP_URL,
	'product_price_tax_option'   => true,
	'product_price_rate_option'  => false,
	'product_option_stock'       => true,
	'order_send_attachments'     => true,
	'order_sync_partial'         => true,
	'order_import_free_order'    => true,
	'order_only_order_completed' => 'completed',
	'settings_logo'              => PREFIX_PLUGIN_URL . 'includes/assets/logo.svg',
	'settings_admin_message'     => sprintf(
		// translators: %s url of contact.
		__( 'Put the connection API key settings in order to connect and sync products. You can go here <a href = '%s' target = '_blank'>App ERP API</a>.', 'connect-woocommerce-erp' ),
		'https://app.erp.com/api'
	),
	'settings_fields'            => array( 'url', 'username', 'apipassword', 'dbname' ),
	'table_sync'                 => $wpdb->prefix . 'sync_connwoo_erp',
	'file'                       => __FILE__,
);

require_once PREFIX_PLUGIN_PATH . 'includes/class-api-erp.php';

// Connect WooCommerce Library.
require_once PREFIX_PLUGIN_PATH . 'vendor/closemarketing/connect-woocommerce-library/loader.php';

new Connect_WooCommerce( $connwoo_options_erp );
```

### Send products from API to Library

It needs this format:

Attributes need to have get_attributes with the same value of the id to import to category.

```
$products = [
	[
		'id'     => ID of product internal
		'kind'   => simple, variable, pack
		'name'   => Name of the Product
		'desc'   => Description of the product
		'sku'    => Product SKU
		'price'  => Normal price of the product without taxes
		'weight' => Weight of the product (numerical)
		'length' => Length of product
		'width'  => Width of product
		'height' => Heigth of product
		'taxes' => [ 's_iva_21' ],
		'total' => Price with taxes
		'hasStock' => 1
		'stock' => Number of units
		'barcode' => GTIN code
		'tags' => Array of tags
		'cf|custom_namefield' => Custom Name of field
		'attributes' => [
			[
				'id'    => slug or id that fits with settings
				'name'  => slug or id that fits with settings
				'value' => Value of the term in Taxonomy (has to be unique)
			]
		]
		'taxonomies' => [
			[
				'name'  => slug of taxonomy that it will be attached
				'value' => Value of the term in Taxonomy (has to be unique)
			]
		]
		'categoryId' => 
		'factoryCode' => 
		'full_info'   => all information that brings API for IA.
		'images' => [
			[
				'url' => URL of the image,
				'title'   => Title of image,
				'content' => Content of image,
				'content_type' => Content type from image,
			],
		'last_updated' => Date from last updated from the product.
		rates: array(4)
			0: array(3)
			id: "65f40aaf178216df3a054762"
			subtotal: 35
			taxes: array(1)
			0: "s_iva_21"
	]
];
```

Variants:
```
$products['variants'][] = array(
	'id'                    => Internal ID
	'name'                  => Name of the variation
	'barcode'               => EAN Code
	'sku'                   => SKU Name
	'stock'                 => Stock
	'categoryFields'        => [
		[
			/* IMPORTANT THEY DON'T HAVE TO BE IN ATTRIBUTES */
			'field' => Value of the variation
			'name'  => Name of the term of variation
		],
	],
	'image' => [
		'url' => URL of the image,
		'title'   => Title of image,
		'content' => Content of image,
		'content_type' => Content type from image,
	]
);
```

Method get_product_attributes

Flat map of ERP field key => human-readable label. Used to populate the "Field from [ERP]"
dropdown in Settings -> Merge Product Variables, and the key is read back as `$item[$key]`
when syncing a product, so it must match a real top-level key on the item array returned
by `get_products()`.
```
[
	'field_name'        => __( 'Field Label', 'woocommerce-es' ),
	'other_field'       => __( 'Other Field Label', 'woocommerce-es' ),
]
```

Method get_payment_methods
```
return [
	[
		'id' => Payment method id. It has to be prefixed with paymentmethods|
		'name' => Name of payment
	]
]
```

### Order data received to send to convert and send to API

```
$order_data = array(
	'contactUserID'          => User ID of WordPress
	'contactCode'            => VAT info code
	'contactName'            => Contact Name. It can be Company or Full name
	'contactFirstName'       => First 
	'contactLastName'        => Last name
	'woocommerceCustomer'    => $order->get_user()->data->user_login ?? '',
	'marketplace'            => 'woocommerce'
	'woocommerceOrderStatus' => Order status
	'woocommerceOrderId'     => Label ID
	'woocommerceReference'   => Reference
	'woocommerceUrl'         => URL shop
	'woocommerceOrderEdit'   => URL edit order
	'woocommerceStore'       => Name of Shop
	'contactEmail'           => Billing email
	'contactCompany'         => Billing company
	'contact_phone'          => Billing Phone
	'contactAddress'         => Billing Address
	'contactCity'            => Billing City
	'contactCp'              => Billing Postal Code
	'contactProvince'        => Billing Province
	'contactCountryCode'     => Billing Country Code
	'desc'                   => Order description
	'date'                   => Order date
	'datestart'              => Order start date
	'notes'                  => Order note
	'saleschannel'           => null
	'currency'               => Shop currency
	'language'               => Document language
	'approveDoc'             => false,
	'designId'               => Design ID for the documet
	'numSerieId'             => Serial number ID
	'clientify_vk'           => Clientify Visitor Key
	'paymentMethod'          => WooCommerce payment method identifier
	'paymentMethodId'        => Payment method API identifier
	'items'                  => [
		[
			'name'      => Product name
			'desc'      => Excerpt of product
			'units'     => Quantity
			'subtotal'  => Price
			'tax'       => Tax rate
			'sku'       => Product SKU
			'image_url' => URL image
			'permalink' => Permalink url from product
		],
		...
		[
			'name'     => Shipping product
			'desc'     => null
			'units'    => 1,
			'subtotal' => Subtotal
			'tax'      => Rate of tax
			'sku'      => 'shipping',
		],
		[
			'name'     => Fee title
			'desc'     => '',
			'units'    => 1,
			'subtotal' => Subtotal
			'tax'      => Tax
			'sku'      => 'fee',
		]
	]
);
```
