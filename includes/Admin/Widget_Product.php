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

use CLOSE\ConnectEcommerce\Base;
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
	 * Active connector id (default selection).
	 *
	 * @var string
	 */
	private $connector_id;

	/**
	 * All configured connectors (id => connector data).
	 *
	 * @var array
	 */
	private $connectors;

	/**
	 * Construct of Class
	 *
	 * @param array $connector       Active connector.
	 * @param array $connectors_data Connectors payload from HELPER::get_connectors() (optional).
	 */
	public function __construct( $connector, $connectors_data = array() ) {
		if ( empty( $connector ) || empty( $connector['connector'] ) || empty( $connector['options'] ) ) {
			return;
		}
		if ( in_array( 'product', $connector['options']['disable_modules'] ?? array(), true ) ) {
			return;
		}
		$this->options        = $connector['options'];
		$this->is_disabled_ai = $connector['is_disabled_ai'] ?? false;
		$this->connector_id   = $connectors_data['active'] ?? '';
		$this->connectors     = $connectors_data['items'] ?? array();
		// Register Meta box for post type product.
		add_action( 'add_meta_boxes', array( $this, 'metabox_products' ) );
	}

	/**
	 * Connectors with the products workflow enabled, for the connector selector.
	 *
	 * @return array Id => label.
	 */
	private function get_syncable_connectors() {
		$syncable = array();
		foreach ( $this->connectors as $conn_id => $conn_data ) {
			$conn_meta = $conn_data['meta'] ?? array();
			if ( ! HELPER::is_workflow_enabled_for_connector( $conn_meta, 'products' ) ) {
				continue;
			}
			$syncable[ $conn_id ] = $conn_meta['label'] ?? $conn_id;
		}
		return $syncable;
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
		$syncable       = $this->get_syncable_connectors();

		echo '<table>';
		if ( count( $syncable ) > 1 ) {
			echo '<tr><td><strong>' . esc_html__( 'Connector:', 'woocommerce-es' ) . '</strong></td>';
			echo '<td><select id="connwoo-widget-connector-' . esc_attr( $product_id ) . '">';
			foreach ( $syncable as $conn_id => $conn_label ) {
				echo '<option value="' . esc_attr( $conn_id ) . '" ' . selected( $this->connector_id, $conn_id, false ) . '>';
				echo esc_html( $conn_label ) . '</option>';
			}
			echo '</select></td></tr>';
		}
		// Send Product.
		echo '<tr><td><strong>' . esc_html__( 'Product:', 'woocommerce-es' ) . '</strong></td>';
		echo '<td>';
		echo '<div name="connwoo-sync-product" id="sync-erp-products-' . esc_html( $product_id ) . '" ';
		echo 'class="button button-primary" onclick="syncProductERP(this,\'';
		echo 'connect_ecommerce_sync_products\',';
		echo '\'' . esc_html( $product_erp_id ) . '\',';
		echo '\'' . esc_html( $product->get_sku() ) . '\',';
		echo '\'' . esc_html( $product_id ) . '\',';
		echo '\'connwoo-widget-connector-' . esc_html( $product_id ) . '\'';
		echo ')">' . esc_html__( 'Sync', 'woocommerce-es' ) . '</div>';
		echo '</td>';
		echo '</tr>';
		echo '</table>';
		if ( ! $this->is_disabled_ai ) {
			echo '<input type="checkbox" name="connwoo-sync-product-ai" ';
			echo 'id="connect_ecommerce_ai" ';
			echo ' /><label for="connect_ecommerce_ai">';
			echo esc_html__( 'Use AI to regenerate title, description and seo.', 'woocommerce-es' ) . '</label>';
		}
	}
}
