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
use CLOSE\ConnectEcommerce\Helpers\Background_Process_Handler;

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
	 * @param array $connector Connector.
	 * @return void
	 */
	public function __construct( $connector ) {
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueues' ) );
		if ( empty( $connector ) || empty( $connector['connector'] ) || empty( $connector['options'] ) ) {
			return;
		}
		$this->options     = $connector['options'];
		$this->connapi_erp = $connector['connapi_erp'] ?? null;
		$this->settings    = $connector['settings'] ?? array();
		$this->sync_period = isset( $this->settings['sync'] ) ? strval( $this->settings['sync'] ) : 'no';

		// Admin Styles.
		add_action( 'wp_ajax_connect_ecommerce_sync_products', array( $this, 'sync_products' ) );

		// Background import AJAX endpoints.
		add_action( 'wp_ajax_conecom_start_background_import', array( $this, 'ajax_start_background_import' ) );
		add_action( 'wp_ajax_conecom_pause_import', array( $this, 'ajax_pause_import' ) );
		add_action( 'wp_ajax_conecom_resume_import', array( $this, 'ajax_resume_import' ) );
		add_action( 'wp_ajax_conecom_stop_import', array( $this, 'ajax_stop_import' ) );
		add_action( 'wp_ajax_conecom_get_import_status', array( $this, 'ajax_get_import_status' ) );

		// Schedule.
		if ( $this->sync_period && 'no' !== $this->sync_period ) {
			$this->cron_products();
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
			'woocommerce-es',
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

		// Background import script.
		wp_enqueue_script(
			'connect-ecommerce-background-import',
			CONECOM_PLUGIN_URL . 'includes/assets/background-import.js',
			array(),
			CONECOM_VERSION,
			true
		);

		wp_localize_script(
			'connect-ecommerce-import',
			'ConEcom_ajaxAction',
			array(
				'url'                 => admin_url( 'admin-ajax.php' ),
				'label_sync'          => __( 'Sync', 'woocommerce-es' ),
				'label_syncing'       => __( 'Syncing', 'woocommerce-es' ),
				'label_sync_complete' => __( 'Finished', 'woocommerce-es' ),
				'label_progress'      => __( 'Progress', 'woocommerce-es' ),
				'label_synced'        => __( 'Synced', 'woocommerce-es' ),
				'label_errors'        => __( 'Errors', 'woocommerce-es' ),
				'confirm_stop'        => __( 'Are you sure you want to stop the import? Progress will be saved and you can resume later.', 'woocommerce-es' ),
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
				'label_syncing' => __( 'Syncing', 'woocommerce-es' ),
				'label_synced'  => __( 'Synced', 'woocommerce-es' ),
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
		$generate_ai    = 'true' === $generate_ai ? 'all' : $generate_ai;
		$api_pagination = ! empty( $this->options['api_pagination'] ) ? $this->options['api_pagination'] : false;

		// Action for one product.
		if ( ! empty( $product_erp_id ) ) {
			$result_api = $this->connapi_erp->get_products( $product_erp_id );
			if ( isset( $result_api['status'] ) && 'error' === $result_api['status'] ) {
				wp_send_json_error( array( 'message' => __( 'Error getting product', 'woocommerce-es' ) . ': ' . $result_api['message'] ) );
			}
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
			$res_message             .= __( 'Connecting with API...', 'woocommerce-es' ) . '<br/>';
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
				$res_message .= ' <span class="taxonomies">' . __( 'Categories: ', 'woocommerce-es' );
				$res_message .= implode( ', ', $term_list ) . '</span>';
			}

			// Get link to product.
			if ( 0 <= $sync_loop ) {
				$res_message .= ' <a href="' . get_edit_post_link( $post_id ) . '" target="_blank">' . __( 'View', 'woocommerce-es' ) . '</a>';
			}
		}
		if ( $finish ) {
			$res_message .= '<p class="finish">' . __( 'All caught up!', 'woocommerce-es' ) . '</p>';
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
		$cron_option = CRON::get_active_period( $this->sync_period );

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
		if ( $is_table_sync ) {
			HELPER::check_table_sync( $this->options['table_sync'] );
		} else {
			// Check if the API method exists.
			if ( ! method_exists( $this->connapi_erp, 'get_products_ids_since' ) ) {
				return;
			}
		}

		// Get products to sync.
		$products_sync = CRON::get_products_sync( $this->settings, $this->options, $this->connapi_erp );
		if ( empty( $products_sync ) && $is_table_sync ) {
			CRON::send_sync_ended_products( $this->settings, $this->options['table_sync'], $this->options['name'], $this->options['slug'] );
			CRON::fill_table_sync( $this->settings, $this->options, $this->connapi_erp );
			$products_sync = CRON::get_products_sync( $this->settings, $this->options, $this->connapi_erp );
		}
		if ( ! empty( $products_sync ) ) {
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

	/**
	 * AJAX handler to start background import
	 *
	 * @return void
	 */
	public function ajax_start_background_import() {
		if ( ! check_ajax_referer( 'conecom_manual_import_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'error' => 'Invalid nonce' ) );
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'error' => 'Insufficient permissions' ) );
			return;
		}

		$generate_ai    = ! empty( $_POST['product_ai'] ) ? sanitize_key( $_POST['product_ai'] ) : 'none';
		$generate_ai    = 'true' === $generate_ai ? 'all' : $generate_ai;
		$api_pagination = ! empty( $this->options['api_pagination'] ) ? (int) $this->options['api_pagination'] : 50;

		$config = array(
			'generate_ai'    => $generate_ai,
			'api_pagination' => $api_pagination,
		);

		$handler    = new Background_Process_Handler();
		$process_id = $handler->start( $config );

		wp_send_json_success(
			array(
				'process_id' => $process_id,
				'message'    => __( 'Background import started', 'woocommerce-es' ),
			)
		);
	}

	/**
	 * AJAX handler to pause import
	 *
	 * @return void
	 */
	public function ajax_pause_import() {
		if ( ! check_ajax_referer( 'conecom_manual_import_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'error' => 'Invalid nonce' ) );
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'error' => 'Insufficient permissions' ) );
			return;
		}

		$process_id = isset( $_POST['process_id'] ) ? sanitize_text_field( wp_unslash( $_POST['process_id'] ) ) : '';

		if ( empty( $process_id ) ) {
			wp_send_json_error( array( 'error' => 'Invalid process ID' ) );
			return;
		}

		$handler = new Background_Process_Handler( $process_id );
		$result  = $handler->pause();

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Import paused', 'woocommerce-es' ) ) );
		} else {
			wp_send_json_error( array( 'error' => __( 'Could not pause import', 'woocommerce-es' ) ) );
		}
	}

	/**
	 * AJAX handler to resume import
	 *
	 * @return void
	 */
	public function ajax_resume_import() {
		if ( ! check_ajax_referer( 'conecom_manual_import_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'error' => 'Invalid nonce' ) );
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'error' => 'Insufficient permissions' ) );
			return;
		}

		$process_id = isset( $_POST['process_id'] ) ? sanitize_text_field( wp_unslash( $_POST['process_id'] ) ) : '';

		if ( empty( $process_id ) ) {
			wp_send_json_error( array( 'error' => 'Invalid process ID' ) );
			return;
		}

		$handler = new Background_Process_Handler( $process_id );
		$result  = $handler->resume();

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Import resumed', 'woocommerce-es' ) ) );
		} else {
			wp_send_json_error( array( 'error' => __( 'Could not resume import', 'woocommerce-es' ) ) );
		}
	}

	/**
	 * AJAX handler to stop import
	 *
	 * @return void
	 */
	public function ajax_stop_import() {
		if ( ! check_ajax_referer( 'conecom_manual_import_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'error' => 'Invalid nonce' ) );
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'error' => 'Insufficient permissions' ) );
			return;
		}

		$process_id = isset( $_POST['process_id'] ) ? sanitize_text_field( wp_unslash( $_POST['process_id'] ) ) : '';

		if ( empty( $process_id ) ) {
			wp_send_json_error( array( 'error' => 'Invalid process ID' ) );
			return;
		}

		$handler = new Background_Process_Handler( $process_id );
		$result  = $handler->stop();

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Import stopped', 'woocommerce-es' ) ) );
		} else {
			wp_send_json_error( array( 'error' => __( 'Could not stop import', 'woocommerce-es' ) ) );
		}
	}

	/**
	 * AJAX handler to get import status and logs
	 *
	 * @return void
	 */
	public function ajax_get_import_status() {
		if ( ! check_ajax_referer( 'conecom_manual_import_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'error' => 'Invalid nonce' ) );
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'error' => 'Insufficient permissions' ) );
			return;
		}

		$process_id = isset( $_POST['process_id'] ) ? sanitize_text_field( wp_unslash( $_POST['process_id'] ) ) : '';
		$log_offset = isset( $_POST['log_offset'] ) ? (int) $_POST['log_offset'] : 0;

		// Get active process if no process_id provided.
		if ( empty( $process_id ) ) {
			$state = Background_Process_Handler::get_state();
		} else {
			$state = Background_Process_Handler::get_state( $process_id );
		}

		if ( ! $state ) {
			wp_send_json_success(
				array(
					'status' => 'none',
					'logs'   => array(),
				)
			);
			return;
		}

		$process_id = isset( $state['process_id'] ) ? $state['process_id'] : $process_id;
		$logs       = Background_Process_Handler::get_logs( $process_id, $log_offset, 50 );

		wp_send_json_success(
			array(
				'status'        => $state['status'],
				'process_id'    => $process_id,
				'current_loop'  => isset( $state['current_loop'] ) ? $state['current_loop'] : 0,
				'synced_count'  => isset( $state['synced_count'] ) ? $state['synced_count'] : 0,
				'error_count'   => isset( $state['error_count'] ) ? $state['error_count'] : 0,
				'total'         => isset( $state['total_products'] ) ? $state['total_products'] : 0,
				'started_at'    => isset( $state['started_at'] ) ? $state['started_at'] : '',
				'logs'          => $logs,
				'logs_offset'   => $log_offset + count( $logs ),
			)
		);
	}
}
