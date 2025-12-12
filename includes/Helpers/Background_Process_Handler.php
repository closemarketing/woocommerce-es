<?php
/**
 * Background Process Handler
 *
 * Manages background import processes with pause/stop/resume functionality
 *
 * @package    WordPress
 * @author     David Perez <david@close.technology>
 * @copyright  2024 Closemarketing
 * @version    1.0
 */

namespace CLOSE\ConnectEcommerce\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Background Process Handler for product imports.
 *
 * @since 1.0.0
 */
class Background_Process_Handler {
	/**
	 * Process ID for current import session
	 *
	 * @var string
	 */
	private $process_id;

	/**
	 * Option name for storing process state
	 *
	 * @var string
	 */
	const STATE_OPTION = 'conecom_import_state';

	/**
	 * Option name for storing process logs
	 *
	 * @var string
	 */
	const LOGS_OPTION = 'conecom_import_logs';

	/**
	 * Action Scheduler hook name
	 *
	 * @var string
	 */
	const AS_HOOK = 'conecom_process_import_batch';

	/**
	 * Maximum logs to keep in memory
	 *
	 * @var int
	 */
	const MAX_LOGS = 500;

	/**
	 * Constructor
	 *
	 * @param string $process_id Process ID.
	 */
	public function __construct( $process_id = '' ) {
		$this->process_id = $process_id ? $process_id : $this->generate_process_id();
		
		// Register Action Scheduler hook.
		add_action( self::AS_HOOK, array( $this, 'process_batch' ), 10, 1 );
	}

	/**
	 * Generates a unique process ID
	 *
	 * @return string
	 */
	private function generate_process_id() {
		return 'import_' . time() . '_' . wp_rand( 1000, 9999 );
	}

	/**
	 * Gets current process state
	 *
	 * @param string $process_id Process ID.
	 * @return array|false
	 */
	public static function get_state( $process_id = '' ) {
		$states = get_option( self::STATE_OPTION, array() );
		
		if ( empty( $process_id ) ) {
			// Return the most recent active process.
			foreach ( $states as $id => $state ) {
				if ( in_array( $state['status'], array( 'running', 'paused' ), true ) ) {
					return array_merge( $state, array( 'process_id' => $id ) );
				}
			}
			return false;
		}
		
		return isset( $states[ $process_id ] ) ? array_merge( $states[ $process_id ], array( 'process_id' => $process_id ) ) : false;
	}

	/**
	 * Updates process state
	 *
	 * @param array $data State data to update.
	 * @return void
	 */
	public function update_state( $data ) {
		$states = get_option( self::STATE_OPTION, array() );
		
		if ( ! isset( $states[ $this->process_id ] ) ) {
			$states[ $this->process_id ] = array(
				'created_at' => current_time( 'mysql' ),
			);
		}
		
		$states[ $this->process_id ] = array_merge(
			$states[ $this->process_id ],
			$data,
			array( 'updated_at' => current_time( 'mysql' ) )
		);
		
		update_option( self::STATE_OPTION, $states );
	}

	/**
	 * Starts a new import process
	 *
	 * @param array $config Import configuration.
	 * @return string Process ID.
	 */
	public function start( $config ) {
		$this->update_state(
			array(
				'status'         => 'running',
				'config'         => $config,
				'current_loop'   => 0,
				'total_products' => 0,
				'synced_count'   => 0,
				'error_count'    => 0,
				'started_at'     => current_time( 'mysql' ),
			)
		);
		
		$this->add_log( __( 'Import process started', 'woocommerce-es' ) );
		
		// Schedule first batch.
		$this->schedule_next_batch();
		
		return $this->process_id;
	}

	/**
	 * Pauses the import process
	 *
	 * @return bool
	 */
	public function pause() {
		$state = self::get_state( $this->process_id );
		
		if ( ! $state || 'running' !== $state['status'] ) {
			return false;
		}
		
		$this->update_state( array( 'status' => 'paused' ) );
		$this->add_log( __( 'Import process paused', 'woocommerce-es' ) );
		
		// Cancel pending scheduled actions.
		$this->cancel_scheduled_batches();
		
		return true;
	}

	/**
	 * Resumes a paused import process
	 *
	 * @return bool
	 */
	public function resume() {
		$state = self::get_state( $this->process_id );
		
		if ( ! $state || 'paused' !== $state['status'] ) {
			return false;
		}
		
		$this->update_state( array( 'status' => 'running' ) );
		$this->add_log( __( 'Import process resumed', 'woocommerce-es' ) );
		
		// Schedule next batch.
		$this->schedule_next_batch();
		
		return true;
	}

	/**
	 * Stops the import process
	 *
	 * @return bool
	 */
	public function stop() {
		$state = self::get_state( $this->process_id );
		
		if ( ! $state || 'completed' === $state['status'] ) {
			return false;
		}
		
		$this->update_state(
			array(
				'status'      => 'stopped',
				'finished_at' => current_time( 'mysql' ),
			)
		);
		$this->add_log( __( 'Import process stopped', 'woocommerce-es' ) );
		
		// Cancel pending scheduled actions.
		$this->cancel_scheduled_batches();
		
		return true;
	}

	/**
	 * Schedules the next batch for processing
	 *
	 * @return void
	 */
	private function schedule_next_batch() {
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			return;
		}
		
		as_enqueue_async_action(
			self::AS_HOOK,
			array( 'process_id' => $this->process_id ),
			'conecom_imports'
		);
	}

	/**
	 * Cancels all scheduled batches for this process
	 *
	 * @return void
	 */
	private function cancel_scheduled_batches() {
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}
		
		as_unschedule_all_actions(
			self::AS_HOOK,
			array( 'process_id' => $this->process_id ),
			'conecom_imports'
		);
	}

	/**
	 * Processes a single batch of products
	 *
	 * @param array $args Action Scheduler arguments.
	 * @return void
	 */
	public function process_batch( $args ) {
		$process_id = isset( $args['process_id'] ) ? $args['process_id'] : '';
		
		if ( ! $process_id ) {
			return;
		}
		
		$this->process_id = $process_id;
		$state            = self::get_state( $process_id );
		
		if ( ! $state || 'running' !== $state['status'] ) {
			return;
		}
		
		$config         = $state['config'];
		$current_loop   = isset( $state['current_loop'] ) ? (int) $state['current_loop'] : 0;
		$api_pagination = isset( $config['api_pagination'] ) ? (int) $config['api_pagination'] : 50;
		
		// Get connector data.
		$conecom_options = conecom_get_options();
		$connector_data  = HELPER::get_connector( $conecom_options );
		
		if ( empty( $connector_data['connapi_erp'] ) || empty( $connector_data['settings'] ) ) {
			$this->add_log( __( 'Error: Connector not configured', 'woocommerce-es' ), 'error' );
			$this->stop();
			return;
		}
		
		$connapi_erp = $connector_data['connapi_erp'];
		$settings    = $connector_data['settings'];
		$generate_ai = isset( $config['generate_ai'] ) ? $config['generate_ai'] : 'none';
		
		// Calculate page for pagination.
		$page      = floor( $current_loop / $api_pagination );
		$loop_page = $current_loop % $api_pagination;
		
		// Get products from API if needed.
		if ( 0 === $loop_page || 0 === $current_loop ) {
			$api_products = $connapi_erp->get_products( null, $current_loop );
			
			if ( isset( $api_products['status'] ) && 'error' === $api_products['status'] ) {
				$this->add_log( __( 'API Error: ', 'woocommerce-es' ) . $api_products['message'], 'error' );
				$this->stop();
				return;
			}
			
			if ( empty( $api_products ) ) {
				$this->complete_import();
				return;
			}
			
			// Store products in state.
			$this->update_state(
				array(
					'api_products'   => HELPER::sanitize_array_recursive( $api_products ),
					'total_products' => count( $api_products ),
				)
			);
		}
		
		// Refresh state to get stored products.
		$state        = self::get_state( $process_id );
		$api_products = isset( $state['api_products'] ) ? $state['api_products'] : array();
		
		if ( empty( $api_products ) ) {
			$this->complete_import();
			return;
		}
		
		$products_count = count( $api_products );
		$item_index     = $current_loop - ( $api_pagination * $page );
		
		if ( ! isset( $api_products[ $item_index ] ) ) {
			$this->complete_import();
			return;
		}
		
		$item = $api_products[ $item_index ];
		
		// Sync product.
		$result_sync = PROD::sync_product_item( $settings, $item, $connapi_erp, $generate_ai );
		
		// Log result.
		$log_message = '[' . date_i18n( 'H:i:s' ) . '][' . ( $current_loop + 1 ) . '] ' . $result_sync['message'];
		$log_type    = 'error' === $result_sync['status'] ? 'error' : 'success';
		
		// Add product link if successful.
		if ( ! empty( $result_sync['post_id'] ) ) {
			$log_message .= ' <a href="' . get_edit_post_link( $result_sync['post_id'] ) . '" target="_blank">' . __( 'View', 'woocommerce-es' ) . '</a>';
		}
		
		$this->add_log( $log_message, $log_type );
		
		// Update counters.
		$synced_count = isset( $state['synced_count'] ) ? (int) $state['synced_count'] : 0;
		$error_count  = isset( $state['error_count'] ) ? (int) $state['error_count'] : 0;
		
		if ( 'error' === $result_sync['status'] ) {
			$error_count++;
		} else {
			$synced_count++;
		}
		
		// Check if finished.
		$is_finished = false;
		if ( $api_pagination ) {
			$is_finished = $products_count < $api_pagination && $products_count === ( $item_index + 1 );
		} else {
			$is_finished = $products_count === ( $current_loop + 1 );
		}
		
		if ( $is_finished ) {
			$this->complete_import();
			return;
		}
		
		// Update state and schedule next batch.
		$this->update_state(
			array(
				'current_loop' => $current_loop + 1,
				'synced_count' => $synced_count,
				'error_count'  => $error_count,
			)
		);
		
		// Schedule next batch.
		$this->schedule_next_batch();
	}

	/**
	 * Completes the import process
	 *
	 * @return void
	 */
	private function complete_import() {
		$state = self::get_state( $this->process_id );
		
		$this->update_state(
			array(
				'status'      => 'completed',
				'finished_at' => current_time( 'mysql' ),
			)
		);
		
		$synced_count = isset( $state['synced_count'] ) ? $state['synced_count'] : 0;
		$error_count  = isset( $state['error_count'] ) ? $state['error_count'] : 0;
		
		$this->add_log(
			sprintf(
				/* translators: %1$d synced products, %2$d errors */
				__( 'Import completed! Products synced: %1$d, Errors: %2$d', 'woocommerce-es' ),
				$synced_count,
				$error_count
			)
		);
	}

	/**
	 * Adds a log entry
	 *
	 * @param string $message Log message.
	 * @param string $type Log type (info, success, error, warning).
	 * @return void
	 */
	public function add_log( $message, $type = 'info' ) {
		$logs = get_option( self::LOGS_OPTION, array() );
		
		if ( ! isset( $logs[ $this->process_id ] ) ) {
			$logs[ $this->process_id ] = array();
		}
		
		$logs[ $this->process_id ][] = array(
			'message'   => $message,
			'type'      => $type,
			'timestamp' => current_time( 'mysql' ),
		);
		
		// Keep only last MAX_LOGS entries.
		if ( count( $logs[ $this->process_id ] ) > self::MAX_LOGS ) {
			$logs[ $this->process_id ] = array_slice( $logs[ $this->process_id ], -self::MAX_LOGS );
		}
		
		update_option( self::LOGS_OPTION, $logs );
	}

	/**
	 * Gets logs for a process
	 *
	 * @param string $process_id Process ID.
	 * @param int    $offset Offset for pagination.
	 * @param int    $limit Limit for pagination.
	 * @return array
	 */
	public static function get_logs( $process_id, $offset = 0, $limit = 100 ) {
		$logs = get_option( self::LOGS_OPTION, array() );
		
		if ( ! isset( $logs[ $process_id ] ) ) {
			return array();
		}
		
		$all_logs = $logs[ $process_id ];
		
		if ( $offset > 0 || $limit < count( $all_logs ) ) {
			return array_slice( $all_logs, $offset, $limit );
		}
		
		return $all_logs;
	}

	/**
	 * Clears old completed processes
	 *
	 * @param int $days_old Number of days to keep.
	 * @return void
	 */
	public static function cleanup_old_processes( $days_old = 7 ) {
		$states = get_option( self::STATE_OPTION, array() );
		$logs   = get_option( self::LOGS_OPTION, array() );
		
		$cutoff_time = strtotime( "-$days_old days" );
		
		foreach ( $states as $process_id => $state ) {
			if ( in_array( $state['status'], array( 'completed', 'stopped' ), true ) ) {
				$finished_at = isset( $state['finished_at'] ) ? strtotime( $state['finished_at'] ) : 0;
				
				if ( $finished_at && $finished_at < $cutoff_time ) {
					unset( $states[ $process_id ] );
					unset( $logs[ $process_id ] );
				}
			}
		}
		
		update_option( self::STATE_OPTION, $states );
		update_option( self::LOGS_OPTION, $logs );
	}

	/**
	 * Gets the process ID
	 *
	 * @return string
	 */
	public function get_process_id() {
		return $this->process_id;
	}
}
