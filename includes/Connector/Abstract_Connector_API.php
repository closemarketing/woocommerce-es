<?php
/**
 * Abstract Connector API
 * Base class for all ERP/CRM connectors
 *
 * This abstract class defines the required interface that all connector implementations
 * must follow. It ensures consistency across different ERP/CRM integrations.
 *
 * @package    WordPress
 * @author     David Perez <david@close.technology>
 * @copyright  2025 Closemarketing
 * @version    1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Abstract Connector API Class
 *
 * All ERP/CRM connector classes must extend this abstract class.
 *
 * @since 3.3.0
 */
abstract class CONECOM_Abstract_Connector_API {

	/**
	 * Connector settings from database.
	 *
	 * @var array
	 */
	protected $settings;

	/**
	 * Connector options/configuration.
	 *
	 * @var array
	 */
	protected $options;

	/**
	 * Connector identifier.
	 *
	 * @var string
	 */
	protected $connector_id;

	/**
	 * Constructor
	 *
	 * @param array  $options All connector options.
	 * @param string $connector_id Connector identifier.
	 */
	public function __construct( $options, $connector_id = '' ) {
		$this->connector_id = $connector_id;
		$connector_key      = $connector_id ?: array_key_first( $options );

		if ( isset( $options[ $connector_key ] ) ) {
			$this->options = $options[ $connector_key ];
		}

		$all_settings   = get_option( 'connect_ecommerce', array() );
		$this->settings = $all_settings[ $connector_key ] ?? array();
	}

	/**
	 * Check if API connection is valid and can sync
	 *
	 * This method should test the API credentials and return whether
	 * the connection is working properly.
	 *
	 * @param array $settings Optional settings to test (useful for testing before saving).
	 * @return bool|array True if can sync, or array with 'status' and 'message' keys.
	 */
	abstract public function check_can_sync( $settings = array() );

	/**
	 * Get products from API
	 *
	 * This method retrieves products from the ERP/CRM system.
	 * It should handle pagination via the $loop parameter.
	 *
	 * @param string|null $product_id Specific product ID or null for all products.
	 * @param int         $loop Loop number for pagination (0 = first page).
	 * @return array Array of products with their data, or error array with 'status' => 'error'.
	 */
	abstract public function get_products( $product_id = null, $loop = 0 );

	/**
	 * Create order in ERP/CRM
	 *
	 * This method sends order data to the ERP/CRM to create an invoice/order.
	 *
	 * @param array $order_data Complete order data including items, customer, etc.
	 * @return array Response array with 'status' (ok/error) and 'message' keys.
	 */
	abstract public function create_order( $order_data );

	/**
	 * Get taxes from ERP/CRM
	 *
	 * Retrieves available tax rates from the ERP/CRM system.
	 *
	 * @return array Array of taxes with structure: [ ['id' => '', 'name' => '', 'rate' => ''], ... ].
	 */
	abstract public function get_taxes();

	/**
	 * Get product by SKU
	 *
	 * Optional method to retrieve a single product by its SKU.
	 * Override this if the API supports SKU-based lookups.
	 *
	 * @param string $sku Product SKU.
	 * @return array|null Product data array or null if not found/supported.
	 */
	public function get_product_by_sku( $sku ) {
		return null;
	}

	/**
	 * Get product attributes for merge vars
	 *
	 * Optional method that returns custom product attributes/fields
	 * that can be mapped in WooCommerce.
	 *
	 * @return array Array of available attributes from the ERP/CRM.
	 */
	public function get_product_attributes() {
		return array();
	}

	/**
	 * Get payment methods
	 *
	 * Optional method to retrieve available payment methods from the ERP/CRM.
	 *
	 * @return array Array of payment methods with id and name.
	 */
	public function get_payment_methods() {
		return array();
	}

	/**
	 * Get URL for product images
	 *
	 * Optional method that returns the base URL where product images are stored.
	 *
	 * @return string Image base URL or empty string if not applicable.
	 */
	public function get_image_product() {
		return '';
	}

	/**
	 * Get URL link to API/Dashboard
	 *
	 * Optional method that returns the URL to view orders/products in the ERP/CRM dashboard.
	 *
	 * @return string Dashboard URL or empty string.
	 */
	public function get_url_link_api() {
		return '';
	}

	/**
	 * Get product attributes (legacy compatibility)
	 *
	 * Legacy method for compatibility with older connectors.
	 *
	 * @return string|array Attributes data.
	 */
	public function get_attributes() {
		return '';
	}

	/**
	 * Get companies list
	 *
	 * Optional method to retrieve available companies from multi-company ERPs.
	 *
	 * @return array Array of companies with id and name.
	 */
	public function get_companies() {
		return array();
	}

	/**
	 * Get treasury accounts
	 *
	 * Optional method to retrieve treasury/bank accounts from the ERP.
	 *
	 * @return array Array of treasury accounts.
	 */
	public function get_treasury_accounts() {
		return array();
	}

	/**
	 * Get product categories
	 *
	 * Optional method to retrieve product categories from the ERP/CRM.
	 *
	 * @return array Array of categories.
	 */
	public function get_categories() {
		return array();
	}

	/**
	 * Update product stock
	 *
	 * Optional method to update stock levels for a product.
	 *
	 * @param string $product_id Product identifier in ERP/CRM.
	 * @param int    $quantity New stock quantity.
	 * @return array Response with status.
	 */
	public function update_stock( $product_id, $quantity ) {
		return array(
			'status'  => 'error',
			'message' => __( 'Stock update not supported by this connector.', 'woocommerce-es' ),
		);
	}

	/**
	 * Get connector name
	 *
	 * Helper method to get the connector's display name.
	 *
	 * @return string Connector name.
	 */
	public function get_connector_name() {
		return $this->options['name'] ?? 'Unknown Connector';
	}

	/**
	 * Get connector slug
	 *
	 * Helper method to get the connector's slug.
	 *
	 * @return string Connector slug.
	 */
	public function get_connector_slug() {
		return $this->options['slug'] ?? '';
	}

	/**
	 * Check if method exists and is callable
	 *
	 * Helper method to check if a specific method is implemented.
	 *
	 * @param string $method Method name to check.
	 * @return bool True if method exists and is callable.
	 */
	public function has_method( $method ) {
		return method_exists( $this, $method ) && is_callable( array( $this, $method ) );
	}
}
