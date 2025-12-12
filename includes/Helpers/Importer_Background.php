<?php
/**
 * Background Importer (Products / Orders)
 *
 * Runs long imports in the background using Action Scheduler and persists
 * progress markers (cursor) and logs in the database, so the import can be
 * paused/stopped and resumed from the exact point.
 *
 * @package    WordPress
 * @author     CloseTechnology
 * @copyright  2025 CloseTechnology
 * @version    1.0
 */

namespace CLOSE\ConnectEcommerce\Helpers;

defined( 'ABSPATH' ) || exit;

use CLOSE\ConnectEcommerce\Helpers\HELPER;
use CLOSE\ConnectEcommerce\Helpers\PROD;
use CLOSE\ConnectEcommerce\Helpers\ORDER;

/**
 * Background Importer.
 */
class Importer_Background {
	/**
	 * Action Scheduler hook for products import.
	 *
	 * @var string
	 */
	public const HOOK_PRODUCTS = 'conecom_background_import_products_batch';

	/**
	 * Action Scheduler hook for orders import.
	 *
	 * @var string
	 */
	public const HOOK_ORDERS = 'conecom_background_import_orders_batch';

	/**
	 * Action Scheduler group name.
	 *
	 * @var string
	 */
	public const AS_GROUP = 'conecom_importer';

	/**
	 * Session table name suffix (without prefix).
	 *
	 * @var string
	 */
	public const TABLE_SESSIONS = 'conecom_import_sessions';

	/**
	 * Log table name suffix (without prefix).
	 *
	 * @var string
	 */
	public const TABLE_LOGS = 'conecom_import_logs';

	/**
	 * Plugin options (connectors definition).
	 *
	 * @var array
	 */
	private $options = array();

	/**
	 * Constructor.
	 *
	 * @param array $options Plugin options.
	 */
	public function __construct( $options ) {
		$this->options = is_array( $options ) ? $options : array();

		self::maybe_install_tables();

		add_action( self::HOOK_PRODUCTS, array( $this, 'run_products_batch' ), 10, 1 );
		add_action( self::HOOK_ORDERS, array( $this, 'run_orders_batch' ), 10, 1 );

		if ( is_admin() ) {
			add_action( 'wp_ajax_conecom_importer_start', array( $this, 'ajax_start' ) );
			add_action( 'wp_ajax_conecom_importer_pause', array( $this, 'ajax_pause' ) );
			add_action( 'wp_ajax_conecom_importer_stop', array( $this, 'ajax_stop' ) );
			add_action( 'wp_ajax_conecom_importer_resume', array( $this, 'ajax_resume' ) );
			add_action( 'wp_ajax_conecom_importer_status', array( $this, 'ajax_status' ) );
		}
	}

	/**
	 * Create/upgrade required tables.
	 *
	 * @return void
	 */
	public static function install_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$table_sessions = $wpdb->prefix . self::TABLE_SESSIONS;
		$table_logs     = $wpdb->prefix . self::TABLE_LOGS;

		$sql_sessions = "CREATE TABLE $table_sessions (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			type varchar(20) NOT NULL,
			connector varchar(100) NOT NULL,
			status varchar(20) NOT NULL,
			cursor bigint(20) unsigned NOT NULL DEFAULT 0,
			total bigint(20) unsigned NULL,
			args longtext NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY type_status (type,status),
			KEY status (status)
		) $charset_collate;";

		$sql_logs = "CREATE TABLE $table_logs (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			session_id bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			level varchar(20) NOT NULL DEFAULT 'info',
			message longtext NOT NULL,
			PRIMARY KEY  (id),
			KEY session_id (session_id),
			KEY session_id_id (session_id,id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_sessions );
		dbDelta( $sql_logs );
	}

	/**
	 * Installs tables if they don't exist yet (upgrade-safe).
	 *
	 * @return void
	 */
	public static function maybe_install_tables() {
		global $wpdb;

		$installed = get_option( 'conecom_importer_tables_installed', '' );
		if ( 'yes' === $installed ) {
			return;
		}

		$table_sessions = $wpdb->prefix . self::TABLE_SESSIONS;
		$table_exists   = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_sessions
			)
		);

		if ( $table_exists !== $table_sessions ) {
			self::install_tables();
		}

		update_option( 'conecom_importer_tables_installed', 'yes' );
	}

	/**
	 * AJAX: Start import (creates a new session or reuses the latest resumable one).
	 *
	 * @return void
	 */
	public function ajax_start() {
		$this->check_ajax_permissions();

		if ( ! function_exists( 'as_schedule_single_action' ) || ! function_exists( 'as_has_scheduled_action' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Action Scheduler is not available. Please ensure WooCommerce is active.', 'woocommerce-es' ),
				)
			);
		}

		$type = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : 'products';
		$ai   = isset( $_POST['ai'] ) ? sanitize_key( wp_unslash( $_POST['ai'] ) ) : 'none';
		$ai   = ( 'true' === $ai ) ? 'all' : $ai;

		$session = $this->get_latest_resumable_session( $type );
		if ( ! empty( $session ) ) {
			$session_id = (int) $session['id'];
			$this->update_session_status( $session_id, 'running' );
			$this->log( $session_id, __( 'Import resumed.', 'woocommerce-es' ) );
		} else {
			$session_id = $this->create_session( $type, array( 'ai' => $ai ) );
			$this->log( $session_id, __( 'Import started.', 'woocommerce-es' ) );
		}

		$this->schedule_next_batch( $type, $session_id, 0 );

		wp_send_json_success(
			array(
				'session_id' => $session_id,
			)
		);
	}

	/**
	 * AJAX: Pause import.
	 *
	 * @return void
	 */
	public function ajax_pause() {
		$this->check_ajax_permissions();

		$session_id = isset( $_POST['session_id'] ) ? (int) $_POST['session_id'] : 0;
		if ( 0 >= $session_id ) {
			wp_send_json_error( array( 'message' => __( 'Missing session_id.', 'woocommerce-es' ) ) );
		}

		$session = $this->get_session( $session_id );
		if ( empty( $session ) ) {
			wp_send_json_error( array( 'message' => __( 'Session not found.', 'woocommerce-es' ) ) );
		}

		$this->update_session_status( $session_id, 'paused' );
		$this->unschedule_batches( $session['type'], $session_id );
		$this->log( $session_id, __( 'Import paused by user.', 'woocommerce-es' ) );

		wp_send_json_success( array( 'session_id' => $session_id ) );
	}

	/**
	 * AJAX: Stop import.
	 *
	 * @return void
	 */
	public function ajax_stop() {
		$this->check_ajax_permissions();

		$session_id = isset( $_POST['session_id'] ) ? (int) $_POST['session_id'] : 0;
		if ( 0 >= $session_id ) {
			wp_send_json_error( array( 'message' => __( 'Missing session_id.', 'woocommerce-es' ) ) );
		}

		$session = $this->get_session( $session_id );
		if ( empty( $session ) ) {
			wp_send_json_error( array( 'message' => __( 'Session not found.', 'woocommerce-es' ) ) );
		}

		$this->update_session_status( $session_id, 'stopped' );
		$this->unschedule_batches( $session['type'], $session_id );
		$this->log( $session_id, __( 'Import stopped by user.', 'woocommerce-es' ) );

		wp_send_json_success( array( 'session_id' => $session_id ) );
	}

	/**
	 * AJAX: Resume import from the last cursor.
	 *
	 * @return void
	 */
	public function ajax_resume() {
		$this->check_ajax_permissions();

		$session_id = isset( $_POST['session_id'] ) ? (int) $_POST['session_id'] : 0;
		if ( 0 >= $session_id ) {
			wp_send_json_error( array( 'message' => __( 'Missing session_id.', 'woocommerce-es' ) ) );
		}

		$session = $this->get_session( $session_id );
		if ( empty( $session ) ) {
			wp_send_json_error( array( 'message' => __( 'Session not found.', 'woocommerce-es' ) ) );
		}

		$this->update_session_status( $session_id, 'running' );
		$this->log( $session_id, __( 'Import resumed by user.', 'woocommerce-es' ) );

		$this->schedule_next_batch( $session['type'], $session_id, 0 );

		wp_send_json_success( array( 'session_id' => $session_id ) );
	}

	/**
	 * AJAX: Get current session status and logs.
	 *
	 * @return void
	 */
	public function ajax_status() {
		$this->check_ajax_permissions();

		$type       = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : 'products';
		$session_id = isset( $_POST['session_id'] ) ? (int) $_POST['session_id'] : 0;
		$since_id   = isset( $_POST['since_id'] ) ? (int) $_POST['since_id'] : 0;

		if ( 0 >= $session_id ) {
			$session = $this->get_latest_session( $type );
		} else {
			$session = $this->get_session( $session_id );
		}

		if ( empty( $session ) ) {
			wp_send_json_success(
				array(
					'session' => null,
					'logs'    => array(),
				)
			);
		}

		$logs = $this->get_logs( (int) $session['id'], $since_id, 200 );

		wp_send_json_success(
			array(
				'session' => array(
					'id'         => (int) $session['id'],
					'type'       => $session['type'],
					'status'     => $session['status'],
					'cursor'     => (int) $session['cursor'],
					'total'      => isset( $session['total'] ) ? (int) $session['total'] : 0,
					'updated_at' => $session['updated_at'],
				),
				'logs'    => $logs,
			)
		);
	}

	/**
	 * Action Scheduler handler: imports the next batch of products.
	 *
	 * @param int $session_id Session id.
	 * @return void
	 */
	public function run_products_batch( $session_id ) {
		$session_id = (int) $session_id;
		$session    = $this->get_session( $session_id );

		if ( empty( $session ) ) {
			return;
		}
		if ( 'running' !== $session['status'] ) {
			return;
		}

		$connector_data = HELPER::get_connector( $this->options );
		$connapi_erp    = $connector_data['connapi_erp'] ?? null;
		$settings       = $connector_data['settings'] ?? array();
		$options        = $connector_data['options'] ?? array();

		if ( empty( $connapi_erp ) || empty( $options ) ) {
			$this->update_session_status( $session_id, 'failed' );
			$this->log( $session_id, __( 'Import failed: connector not configured.', 'woocommerce-es' ), 'error' );
			return;
		}

		$args        = $this->decode_args( $session['args'] ?? '' );
		$generate_ai = $args['ai'] ?? 'none';

		$cursor         = (int) $session['cursor'];
		$api_pagination = ! empty( $options['api_pagination'] ) ? (int) $options['api_pagination'] : 100;

		$api_products = $connapi_erp->get_products( null, $cursor );
		if ( isset( $api_products['status'] ) && 'error' === $api_products['status'] ) {
			$this->update_session_status( $session_id, 'failed' );
			$this->log(
				$session_id,
				__( 'Import failed: API error: ', 'woocommerce-es' ) . esc_html( $api_products['message'] ?? '' ),
				'error'
			);
			return;
		}

		if ( empty( $api_products ) || ! is_array( $api_products ) ) {
			$this->update_session_status( $session_id, 'completed' );
			$this->log( $session_id, __( 'Import completed. No more products returned by the API.', 'woocommerce-es' ) );
			return;
		}

		$processed_in_batch = 0;
		foreach ( $api_products as $item ) {
			$session_check = $this->get_session( $session_id );
			if ( empty( $session_check ) || 'running' !== $session_check['status'] ) {
				$this->log( $session_id, __( 'Import interrupted (pause/stop detected).', 'woocommerce-es' ) );
				return;
			}

			$item        = HELPER::sanitize_array_recursive( $item );
			$result_sync = PROD::sync_product_item( $settings, $item, $connapi_erp, $generate_ai );
			++$cursor;
			++$processed_in_batch;

			$message = '[' . date_i18n( 'H:i:s' ) . '] ';
			$message .= sprintf(
				/* translators: %d: current index. */
				__( 'Product #%d: ', 'woocommerce-es' ),
				$cursor
			);
			$message .= $result_sync['message'] ?? '';

			$this->update_session_cursor( $session_id, $cursor );
			$this->log( $session_id, $message, ( ( $result_sync['status'] ?? '' ) === 'error' ) ? 'error' : 'info' );
		}

		// If the API returned less than the pagination size, we assume it ended.
		if ( $processed_in_batch < $api_pagination ) {
			$this->update_session_status( $session_id, 'completed' );
			$this->log( $session_id, __( 'Import completed. All caught up!', 'woocommerce-es' ) );
			return;
		}

		$this->schedule_next_batch( 'products', $session_id, 5 );
	}

	/**
	 * Action Scheduler handler: processes the next batch of orders (exports to ERP).
	 *
	 * @param int $session_id Session id.
	 * @return void
	 */
	public function run_orders_batch( $session_id ) {
		$session_id = (int) $session_id;
		$session    = $this->get_session( $session_id );

		if ( empty( $session ) ) {
			return;
		}
		if ( 'running' !== $session['status'] ) {
			return;
		}

		$connector_data = HELPER::get_connector( $this->options );
		$connapi_erp    = $connector_data['connapi_erp'] ?? null;
		$settings       = $connector_data['settings'] ?? array();
		$options        = $connector_data['options'] ?? array();

		if ( empty( $connapi_erp ) || empty( $options ) ) {
			$this->update_session_status( $session_id, 'failed' );
			$this->log( $session_id, __( 'Orders import failed: connector not configured.', 'woocommerce-es' ), 'error' );
			return;
		}

		$meta_key_order = '_' . $options['slug'] . '_invoice_id';
		$cursor         = (int) $session['cursor'];

		$orders = wc_get_orders(
			array(
				'status'         => array( 'wc-completed' ),
				'posts_per_page' => 25,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'paged'          => ( 0 === $cursor ) ? 1 : ( (int) $cursor + 1 ),
			)
		);

		if ( empty( $orders ) ) {
			$this->update_session_status( $session_id, 'completed' );
			$this->log( $session_id, __( 'Orders import completed. No more orders to process.', 'woocommerce-es' ) );
			return;
		}

		foreach ( $orders as $order ) {
			$session_check = $this->get_session( $session_id );
			if ( empty( $session_check ) || 'running' !== $session_check['status'] ) {
				$this->log( $session_id, __( 'Orders import interrupted (pause/stop detected).', 'woocommerce-es' ) );
				return;
			}

			$order_id = $order->get_id();
			$invoice  = $order->get_meta( $meta_key_order );

			if ( ! empty( $invoice ) && 'nocreate' !== $invoice ) {
				$this->log(
					$session_id,
					'[' . date_i18n( 'H:i:s' ) . '] ' . sprintf(
						/* translators: %d: order id. */
						__( 'Order #%d skipped (already exported).', 'woocommerce-es' ),
						$order_id
					)
				);
				continue;
			}

			$result = ORDER::create_invoice( $settings, $order_id, $meta_key_order, $options['slug'], $connapi_erp );
			$this->log(
				$session_id,
				'[' . date_i18n( 'H:i:s' ) . '] ' . sprintf(
					/* translators: 1: order id. 2: message. */
					__( 'Order #%1$d: %2$s', 'woocommerce-es' ),
					$order_id,
					$result['message'] ?? ''
				),
				( ( $result['status'] ?? '' ) === 'error' ) ? 'error' : 'info'
			);
		}

		++$cursor;
		$this->update_session_cursor( $session_id, $cursor );
		$this->schedule_next_batch( 'orders', $session_id, 10 );
	}

	/**
	 * Schedules the next batch for a given session.
	 *
	 * @param string $type Type (products/orders).
	 * @param int    $session_id Session id.
	 * @param int    $delay Delay in seconds.
	 * @return void
	 */
	private function schedule_next_batch( $type, $session_id, $delay = 0 ) {
		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			return;
		}

		$hook = ( 'orders' === $type ) ? self::HOOK_ORDERS : self::HOOK_PRODUCTS;
		$args = array( (int) $session_id );

		if ( false !== as_has_scheduled_action( $hook, $args, self::AS_GROUP ) ) {
			return;
		}

		$timestamp = time() + (int) $delay;
		as_schedule_single_action( $timestamp, $hook, $args, self::AS_GROUP );
	}

	/**
	 * Cancels scheduled batches for a given session.
	 *
	 * @param string $type Type.
	 * @param int    $session_id Session id.
	 * @return void
	 */
	private function unschedule_batches( $type, $session_id ) {
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}

		$hook = ( 'orders' === $type ) ? self::HOOK_ORDERS : self::HOOK_PRODUCTS;
		$args = array( (int) $session_id );

		as_unschedule_all_actions( $hook, $args, self::AS_GROUP );
	}

	/**
	 * Creates a new import session.
	 *
	 * @param string $type Type.
	 * @param array  $args Args to store.
	 * @return int
	 */
	private function create_session( $type, $args = array() ) {
		global $wpdb;

		$connector_data = HELPER::get_connector( $this->options );
		$connector      = $connector_data['connector'] ?? '';

		$table = $wpdb->prefix . self::TABLE_SESSIONS;
		$now   = gmdate( 'Y-m-d H:i:s' );

		$wpdb->insert(
			$table,
			array(
				'type'       => $type,
				'connector'  => $connector,
				'status'     => 'running',
				'cursor'     => 0,
				'total'      => 0,
				'args'       => wp_json_encode( $args ),
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Returns the latest session for the given type.
	 *
	 * @param string $type Type.
	 * @return array|null
	 */
	private function get_latest_session( $type ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_SESSIONS;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE type = %s ORDER BY id DESC LIMIT 1",
				$type
			),
			ARRAY_A
		);

		return ! empty( $row ) ? $row : null;
	}

	/**
	 * Returns the latest resumable session (running/paused/stopped) for the given type.
	 *
	 * @param string $type Type.
	 * @return array|null
	 */
	private function get_latest_resumable_session( $type ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_SESSIONS;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE type = %s AND status IN ('running','paused','stopped') ORDER BY id DESC LIMIT 1",
				$type
			),
			ARRAY_A
		);

		return ! empty( $row ) ? $row : null;
	}

	/**
	 * Gets a session by id.
	 *
	 * @param int $session_id Session id.
	 * @return array|null
	 */
	private function get_session( $session_id ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_SESSIONS;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d LIMIT 1",
				(int) $session_id
			),
			ARRAY_A
		);

		return ! empty( $row ) ? $row : null;
	}

	/**
	 * Updates session status.
	 *
	 * @param int    $session_id Session id.
	 * @param string $status New status.
	 * @return void
	 */
	private function update_session_status( $session_id, $status ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_SESSIONS;
		$now   = gmdate( 'Y-m-d H:i:s' );

		$wpdb->update(
			$table,
			array(
				'status'     => $status,
				'updated_at' => $now,
			),
			array(
				'id' => (int) $session_id,
			),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Updates session cursor.
	 *
	 * @param int $session_id Session id.
	 * @param int $cursor Cursor.
	 * @return void
	 */
	private function update_session_cursor( $session_id, $cursor ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_SESSIONS;
		$now   = gmdate( 'Y-m-d H:i:s' );

		$wpdb->update(
			$table,
			array(
				'cursor'     => (int) $cursor,
				'updated_at' => $now,
			),
			array(
				'id' => (int) $session_id,
			),
			array( '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Writes a log entry for the session.
	 *
	 * @param int    $session_id Session id.
	 * @param string $message Message (HTML allowed, will be sanitized).
	 * @param string $level Level.
	 * @return int
	 */
	private function log( $session_id, $message, $level = 'info' ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_LOGS;

		$message = wp_kses_post( $message );
		$level   = sanitize_key( $level );

		$wpdb->insert(
			$table,
			array(
				'session_id' => (int) $session_id,
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
				'level'      => $level,
				'message'    => $message,
			),
			array( '%d', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Fetch logs for a given session.
	 *
	 * @param int $session_id Session id.
	 * @param int $since_id Return logs after this id.
	 * @param int $limit Limit.
	 * @return array
	 */
	private function get_logs( $session_id, $since_id = 0, $limit = 200 ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_LOGS;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, created_at, level, message
				FROM {$table}
				WHERE session_id = %d AND id > %d
				ORDER BY id ASC
				LIMIT %d",
				(int) $session_id,
				(int) $since_id,
				(int) $limit
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Decodes args JSON safely.
	 *
	 * @param string $args_json Args json.
	 * @return array
	 */
	private function decode_args( $args_json ) {
		if ( empty( $args_json ) ) {
			return array();
		}
		$decoded = json_decode( (string) $args_json, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Ensures the request is authorized.
	 *
	 * @return void
	 */
	private function check_ajax_permissions() {
		if ( ! check_ajax_referer( 'conecom_importer_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'woocommerce-es' ) ) );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'woocommerce-es' ) ) );
		}
	}
}

