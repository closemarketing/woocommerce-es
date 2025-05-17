<?php
/**
 * Library for importing products
 *
 * @package    WordPress
 * @author     David Perez <david@closemarketing.es>
 * @copyright  2019 Closemarketing
 * @version    1.0
 */

namespace CLOSE\ConnectEcommerce\Admin;

defined( 'ABSPATH' ) || exit;

use CLOSE\ConnectEcommerce\Helpers\PROD;
use CLOSE\ConnectEcommerce\Helpers\HELPER;
use CLOSE\ConnectEcommerce\Helpers\CRON;

/**
 * Library for WooCommerce Settings
 *
 * Settings in order to importing products
 *
 * @package    WordPress
 * @author     David Perez <david@closemarketing.es>
 * @copyright  2019 Closemarketing
 * @version    0.1
 */
class Import_Products {
	/**
	 * Saves the products with errors to send after
	 *
	 * @var array
	 */
	private $error_product_import;

	/**
	 * Options of plugin
	 *
	 * @var array
	 */
	private $options;

	/**
	 * Settings of plugin
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * API Object
	 *
	 * @var object
	 */
	private $sync_period;

	/**
	 * API Object
	 *
	 * @var object
	 */
	private $connapi_erp;

	/**
	 * Message of error products
	 *
	 * @var object
	 */
	private $msg_error_products;

	/**
	 * Constructs of class
	 *
	 * @param array $options Options of plugin.
	 * @return void
	 */
	public function __construct( $options ) {
		$settings_base = get_option( 'connect_ecommerce' );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueues' ) );
		$connector         = ! empty( $settings_base['connector'] ) ? $settings_base['connector'] : '';
		if ( empty( $connector ) ) {
			return;
		}
		$this->options     = $options[ $connector ];
		$apiname           = 'Connect_Ecommerce_' . $this->options['name'];
		$this->connapi_erp = new $apiname( $options );
		$this->settings    = $settings_base[ $connector ] ?? array();
		$this->sync_period = isset( $this->settings['sync'] ) ? strval( $this->settings['sync'] ) : 'no';

		// Admin Styles.
		add_action( 'wp_ajax_connect_ecommerce_sync_products', array( $this, 'sync_products' ) );

		// Schedule.
		if ( $this->sync_period && 'no' !== $this->sync_period ) {
			add_action( 'init', array( $this, 'cron_products' ) );
			add_action( $this->sync_period, array( $this, 'cron_sync_products' ) );
		}
	}

	/**
	 * Enqueues Styles for admin
	 *
	 * @return void
	 */
	public function admin_enqueues() {
		wp_enqueue_style(
			'connect-ecommerce',
			CONECOM_PLUGIN_URL . 'includes/assets/admin.css',
			array(),
			CONECOM_VERSION
		);

		wp_enqueue_script(
			'connect-ecommerce-repeat',
			CONECOM_PLUGIN_URL . 'includes/assets/repeatable-fields.js',
			array(),
			CONECOM_VERSION,
			true
		);

		wp_enqueue_script(
			'connect-ecommerce-import',
			CONECOM_PLUGIN_URL . 'includes/assets/sync-import.js',
			array(),
			CONECOM_VERSION,
			true
		);

		wp_localize_script(
			'connect-ecommerce-import',
			'ConEcom_ajaxAction',
			array(
				'url'                 => admin_url( 'admin-ajax.php' ),
				'label_sync'          => __( 'Sync', 'connect-ecommerce' ),
				'label_syncing'       => __( 'Syncing', 'connect-ecommerce' ),
				'label_sync_complete' => __( 'Finished', 'connect-ecommerce' ),
				'nonce'               => wp_create_nonce( 'conecom_manual_import_nonce' ),
			)
		);

		// AJAX Pedidos.
		wp_enqueue_script(
			'cw-sync-order-widget',
			CONECOM_PLUGIN_URL . 'includes/assets/sync-order-widget.js',
			array(),
			CONECOM_VERSION,
			true
		);

		wp_localize_script(
			'cw-sync-order-widget',
			'ConEcom_ajaxActionOrder',
			array(
				'url'           => admin_url( 'admin-ajax.php' ),
				'label_syncing' => __( 'Syncing', 'connect-ecommerce' ),
				'label_synced'  => __( 'Synced', 'connect-ecommerce' ),
				'nonce'         => wp_create_nonce( 'sync_erp_order_nonce' ),
			)
		);
	}

	/**
	 * Import products from API
	 *
	 * @return void
	 */
	public function sync_products() {
		if ( ! check_ajax_referer( 'conecom_manual_import_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'error' => 'Invalid nonce' ) );
			return;
		}
		$sync_loop      = isset( $_POST['loop'] ) ? (int) $_POST['loop'] : 0;
		$product_erp_id = isset( $_POST['product_erp_id'] ) ? sanitize_text_field( wp_unslash( $_POST['product_erp_id'] ) ) : '';
		$product_sku    = isset( $_POST['product_sku'] ) ? sanitize_text_field( wp_unslash( $_POST['product_sku'] ) ) : '';
		$product_id     = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : '';
		$message        = '';
		$res_message    = '';
		$generate_ai    = ! empty( $_POST['product_ai'] ) ? sanitize_key( $_POST['product_ai'] ) : 'none';
		$api_pagination = ! empty( $this->options['api_pagination'] ) ? $this->options['api_pagination'] : false;

		// Action for one product.
		if ( ! empty( $product_erp_id ) ) {
			$result_api = $this->connapi_erp->get_products( $product_erp_id );
			if ( empty( $result_api ) ) {
				wp_send_json_error( array( 'message' => 'No products' ) );
			}
			$api_products = array( -1 => $result_api );
		} elseif ( ! empty( $product_sku ) && method_exists( $this->connapi_erp, 'get_product_by_sku' ) ) {
			$result_api = $this->connapi_erp->get_product_by_sku( $product_sku );
			if ( empty( $result_api ) ) {
				wp_send_json_error( array( 'message' => 'No products' ) );
			}
			$api_products = array( -1 => $result_api );
		}

		// Start.
		if ( ! session_id() ) {
			session_start();
		}
		$page = 1;
		if ( $api_pagination ) {
			$loop_page = $sync_loop % $api_pagination;
			$page      = intval( $sync_loop / $api_pagination, 0 );
		}

		if ( 0 === $sync_loop || ( $api_pagination && 0 === $loop_page ) ) {
			$api_products                     = $this->connapi_erp->get_products( null, $sync_loop );
			$_SESSION['conecom_api_products'] = HELPER::sanitize_array_recursive( $api_products );
			$res_message             .= __( 'Connecting with API...', 'connect-ecommerce' ) . '<br/>';
		} elseif ( 0 < $sync_loop ) {
			$api_products = isset( $_SESSION['conecom_api_products'] ) ? HELPER::sanitize_array_recursive( $_SESSION['conecom_api_products'] ) : array();
		}

		if ( isset( $api_products['status'] ) && 'error' === $api_products['status'] ) {
			wp_send_json_error( array( 'message' => $api_products['message'] ) );
		}

		if ( empty( $api_products ) ) {
			wp_send_json_error( array( 'message' => 'No products' ) );
		}

		$products_count           = count( $api_products );
		$item                     = $api_products[ $sync_loop - ( $api_pagination * $page ) ];
		$this->msg_error_products = array();

		$result_sync = PROD::sync_product_item( $this->settings, $item, $this->connapi_erp, $generate_ai, $product_id );
		$post_id     = $result_sync['post_id'] ?? 0;
		if ( 'error' === $result_sync['status'] ) {
			$this->error_product_import[] = array(
				'prod_id' => $item['id'],
				'name'    => $item['name'],
				'sku'     => $item['sku'],
				'error'   => $result_sync['message'],
			);
		}
		$message .= $result_sync['message'];

		$products_synced = $sync_loop + 1;
		if ( $api_pagination ) {
			$finish = $products_count < $api_pagination && $products_count === $sync_loop ? true : false;
		} else {
			$finish = $products_count === $sync_loop ? true : false;
		}
		$finish       = -1 === $sync_loop ? true : $finish;
		$res_message .= '[' . date_i18n( 'H:i:s' ) . ']';
		if ( 0 <= $sync_loop ) {
			$res_message .= '[' . $products_synced;
			$res_message .= empty( $api_pagination ) ? '/' . $products_count : '';
			$res_message .= '] ';
		}
		$res_message .= $message;

		if ( $post_id ) {
			// Get taxonomies from post_id.
			$term_list = wp_get_post_terms( $post_id, 'product_cat', array( 'fields' => 'names' ) );
			if ( ! empty( $term_list ) && is_array( $term_list ) ) {
				$res_message .= ' <span class="taxonomies">' . __( 'Categories: ', 'connect-ecommerce' );
				$res_message .= implode( ', ', $term_list ) . '</span>';
			}

			// Get link to product.
			if ( 0 <= $sync_loop ) {
				$res_message .= ' <a href="' . get_edit_post_link( $post_id ) . '" target="_blank">' . __( 'View', 'connect-ecommerce' ) . '</a>';
			}
		}
		if ( $finish ) {
			$res_message .= '<p class="finish">' . __( 'All caught up!', 'connect-ecommerce' ) . '</p>';
		}

		$args = array(
			'loop'          => $sync_loop + 1,
			'message'       => $res_message,
			'finish'        => $finish,
			'product_count' => $products_count,
		);
		if ( $finish && 0 < $sync_loop ) {
			// Email errors.
			HELPER::send_product_errors( $this->error_product_import, $this->options['slug'] );
		}
		wp_send_json_success( $args );
	}

	/**
	 * Cron advanced with Action Scheduler
	 *
	 * @return void
	 */
	public function cron_products() {
		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			return;
		}
		$pos = array_search( $this->sync_period, array_column( $this->options['cron'], 'cron' ), true );
		if ( false !== $pos ) {
			$cron_option = $this->options['cron'][ $pos ];
		}

		if ( isset( $cron_option['cron'] ) && false === as_has_scheduled_action( $cron_option['cron'] ) ) {
			as_schedule_recurring_action( time(), $cron_option['interval'], $cron_option['cron'] );
		}
	}

	/**
	 * Cron sync products
	 *
	 * @return void
	 */
	public function cron_sync_products() {
		$is_table_sync = ! empty( $this->options['table_sync'] ) ? true : false;
		$products_sync = CRON::get_products_sync( $this->settings, $this->options, $this->connapi_erp );

		if ( $is_table_sync ) {
			HELPER::check_table_sync( $this->options['table_sync'] );
		}

		if ( empty( $products_sync ) && $is_table_sync ) {
			CRON::send_sync_ended_products( $this->settings, $this->options['table_sync'], $this->options['name'], $this->options['slug'] );
			CRON::fill_table_sync( $this->settings, $this->options['table_sync'], $this->connapi_erp, $this->options['slug'] );
		} elseif ( ! empty( $products_sync ) ) {
			foreach ( $products_sync as $product_sync ) {
				$product_id = isset( $product_sync['prod_id'] ) ? $product_sync['prod_id'] : $product_sync;

				$product_api = $this->connapi_erp->get_products( $product_id );
				$result      = PROD::sync_product_item( $this->settings, $product_api, $this->connapi_erp );
				if ( $is_table_sync ) {
					CRON::save_product_sync( $this->options['table_sync'], $result['prod_id'], $this->options['slug'] );
				}
			}
		}
	}
}
