<?php
/**
 * Product Widget
 *
 * @package    WordPress
 * @author     David Perez <david@close.technology>
 * @copyright  2023 Closemarketing
 * @version    1.0
 */

namespace CLOSE\ConnectEcommerce\Admin;

defined( 'ABSPATH' ) || exit;

use CLOSE\ConnectEcommerce\Helpers\HELPER;

/**
 * Mejoras productos.
 *
 * Description.
 *
 * @since Version 3 digits
 */
class Widget_Product {
	/**
	 * Options of plugin.
	 *
	 * @var array
	 */
	private $options;

	/**
	 * Settings slug
	 *
	 * @var string
	 */
	private $is_disabled_ai;

	/**
	 * Construct of Class
	 *
	 * @param array $options Options of plugin.
	 */
	public function __construct( $options = array() ) {
		$settings_base = HELPER::get_settings();
		$connector     = ! empty( $settings_base['connector'] ) ? $settings_base['connector'] : '';
		if ( empty( $connector ) ) {
			return;
		}
		$this->options        = $options[ $connector ];
		$this->is_disabled_ai = isset( $this->options['disable_modules'] ) && in_array( 'ai', $this->options['disable_modules'], true ) ? true : false;
		// Register Meta box for post type product.
		add_action( 'add_meta_boxes', array( $this, 'metabox_products' ) );
	}
	/**
	 * Adds metabox
	 *
	 * @return void
	 */
	public function metabox_products() {
		add_meta_box(
			'connect-ecommerce-product-checker',
			__( 'Connect with ', 'connect-ecommerce' ) . $this->options['name'],
			array( $this, 'metabox_show_product' ),
			'product',
			'side',
			'core'
		);
	}

	/**
	 * Metabox inputs for post type.
	 *
	 * @param object $post Post object.
	 * @return void
	 */
	public function metabox_show_product( $post ) {
		$product_id     = (int) $post->ID;
		$product        = wc_get_product( $post->ID );
		$product_erp_id = $product->get_meta( 'connect_ecommerce_id' );

		echo '<table>';
		// Send Product.
		echo '<tr><td><strong>' . esc_html__( 'Product:', 'connect-ecommerce' ) . '</strong></td>';
		echo '<td>';
		echo '<div name="connwoo-sync-product" id="sync-erp-products-' . esc_html( $product_id ) . '" ';
		echo 'class="button button-primary" onclick="syncProductERP(this,\'';
		echo 'connect_ecommerce_sync_products\',';
		echo '\'' . esc_html( $product_erp_id ) . '\',';
		echo '\'' . esc_html( $product->get_sku() ) . '\',';
		echo '\'' . esc_html( $product_id ) . '\',';
		echo ')">' . esc_html__( 'Sync', 'connect-ecommerce' ) . '</div>';
		echo '</td>';
		echo '</tr>';
		echo '</table>';
		if ( ! $this->is_disabled_ai ) {
			echo '<input type="checkbox" name="connwoo-sync-product-ai" ';
			echo 'id="connect_ecommerce_ai" ';
			echo ' /><label for="connect_ecommerce_ai">';
			echo esc_html__( 'Use AI to regenerate title, description and seo.', 'connect-ecommerce' ) . '</label>';
		}
	}
}
