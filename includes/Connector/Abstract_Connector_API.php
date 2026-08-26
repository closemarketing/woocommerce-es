<?php
/**
 * Connector API contract.
 *
 * @package WordPress
 * @author Closetechnology
 */

defined( 'ABSPATH' ) || exit;

/**
 * Provides safe optional API methods for ERP and CRM connectors.
 *
 * Connector add-ons should extend this class. Optional capabilities use safe
 * defaults so an unsupported API feature does not interrupt a product import.
 *
 * @since 3.4.1
 */
abstract class CONECOM_Abstract_Connector_API {
	/**
	 * Gets the attributes available from the remote API.
	 *
	 * @return string
	 */
	public function get_attributes() {
		return '';
	}

	/**
	 * Gets an image for a product when the product response has no image data.
	 *
	 * @param array      $settings      Connector settings.
	 * @param string|int $product_id    Remote product ID.
	 * @param int        $attachment_id WordPress product ID.
	 * @return string
	 */
	public function get_image_product( $settings = array(), $product_id = '', $attachment_id = 0 ) {
		unset( $settings, $product_id, $attachment_id );
		return '';
	}

	/**
	 * Gets the remote API URL for an order.
	 *
	 * @param array $order Order data.
	 * @return string
	 */
	public function get_url_link_api( $order = array() ) {
		unset( $order );
		return '';
	}
}
