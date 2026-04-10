<?php
/**
 * Abstract Connector API
 *
 * Base class that all ERP/CRM connector plugins must extend.
 * Defines the contract required by the Connect WooCommerce main plugin.
 *
 * @package    WordPress
 * @author     David Perez <david@closemarketing.es>
 * @copyright  2025 Closemarketing
 * @version    1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Abstract_Connector_API
 *
 * All connector classes (Connect_Ecommerce_*) must extend this class
 * and implement every abstract method defined here.
 *
 * Optional methods have a default implementation and can be overridden.
 *
 * @since 1.0.0
 */
abstract class Abstract_Connector_API {

	/**
	 * Settings options for the connector.
	 *
	 * @var array
	 */
	protected $settings = array();

	/**
	 * Constructor.
	 *
	 * @param array  $options      All connector options.
	 * @param string $connector_id Connector identifier.
	 */
	abstract public function __construct( $options = array(), $connector_id = '' );

	// -------------------------------------------------------------------------
	// Abstract methods — MUST be implemented by every connector.
	// -------------------------------------------------------------------------

	/**
	 * Test the API connection with the stored (or provided) credentials.
	 *
	 * @param array $settings Optional settings override.
	 * @return array {
	 *     @type string $status  'ok' or 'error'.
	 *     @type string $message Human-readable message on error.
	 * }
	 */
	abstract public function check_can_sync( $settings = array() );

	/**
	 * Return a page of products from the ERP.
	 *
	 * @param array|null $filters  Optional ERP-specific filters.
	 * @param int        $page     1-based page number.
	 * @param array      $settings Optional settings override.
	 * @return array List of normalised product arrays, or an error array.
	 */
	abstract public function get_products( $filters = null, $page = 1, $settings = array() );

	/**
	 * Return the remote image URL for a product, or empty string if none.
	 *
	 * @param mixed $product Product data as returned by get_products().
	 * @return string Image URL or empty string.
	 */
	abstract public function get_image_product( $product = null );

	/**
	 * Return available invoice/document series for the connector.
	 *
	 * @return array Associative array: series_id => series_label.
	 */
	abstract public function get_series_number();

	/**
	 * Return available product attributes defined in the ERP.
	 *
	 * @return array List of attribute arrays, or empty array if not supported.
	 */
	abstract public function get_attributes();

	/**
	 * Return available price rates/tariffs defined in the ERP.
	 *
	 * @return array List of rate arrays, or empty array if not supported.
	 */
	abstract public function get_rates();

	/**
	 * Create an invoice/document in the ERP from a WooCommerce order.
	 *
	 * @param array  $order_data Normalised order data built by the main plugin.
	 * @param string $doc_id     Existing document ID (for updates).
	 * @param string $invoice_id Existing invoice number (for updates).
	 * @param bool   $force      Force creation even if a document already exists.
	 * @param array  $settings   Optional settings override.
	 * @return array {
	 *     @type string $status      'ok' or 'error'.
	 *     @type string $document_id Created document UUID.
	 *     @type string $invoice_id  Created invoice number.
	 * }
	 */
	abstract public function create_order( $order_data, $doc_id = '', $invoice_id = '', $force = false, $settings = array() );

	/**
	 * Return the public API URL shown in the settings screen.
	 *
	 * @return string Full URL (with trailing slash).
	 */
	abstract public function get_url_link_api();

	/**
	 * Push a WooCommerce product update to the ERP.
	 *
	 * Called automatically by the main plugin whenever a product is saved.
	 * Implement this method to keep ERP data in sync with WooCommerce changes.
	 *
	 * @param int        $product_id  WooCommerce product post ID.
	 * @param WC_Product $product     WooCommerce product object.
	 * @param array      $settings    Active connector settings.
	 * @return array {
	 *     @type string $status  'ok', 'error', or 'skipped'.
	 *     @type string $message Human-readable result message.
	 * }
	 */
	abstract public function sync_product( $product_id, $product, $settings = array() );

	// -------------------------------------------------------------------------
	// Optional methods — provide a sensible default; override when needed.
	// -------------------------------------------------------------------------

	/**
	 * Return available tax rates from the ERP.
	 *
	 * @return array List of tax arrays with keys: id, name, rate.
	 */
	public function get_taxes() {
		return array();
	}

	/**
	 * Return product attribute mappings (merge vars) available in the ERP.
	 *
	 * Used to populate the product merge-vars settings UI.
	 *
	 * @return array Associative array: erp_field_key => label.
	 */
	public function get_product_attributes() {
		return array();
	}

	/**
	 * Return all product SKUs currently in the ERP (used for import statistics).
	 *
	 * @return array Flat list of SKU strings.
	 */
	public function get_all_product_skus() {
		return array();
	}

	/**
	 * Return the PDF binary/URL for an existing ERP document.
	 *
	 * @param string $document_id ERP document identifier.
	 * @return string PDF content, URL, or empty string if not supported.
	 */
	public function get_order_pdf( $document_id = '' ) {
		return '';
	}

	/**
	 * Find and return a single product by its SKU.
	 *
	 * @param string $sku Product SKU to search for.
	 * @return array|null Normalised product array or null if not found/supported.
	 */
	public function get_product_by_sku( $sku = '' ) {
		return null;
	}
}
