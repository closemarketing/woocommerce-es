# Connect Ecommerce Documentation

## Documentation

* This is a base plugin
* Usage

## Usage
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

## Send products from API to Library

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
```
'custom_fields' => [],
'product_cat' => [
	'id'   => 'product_cat',
	'name' => __( 'Product Category', 'connect-ecommerce-toptex' ),
	'elements' => [
	],
],
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

## Order data received to send to convert and send to API

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
