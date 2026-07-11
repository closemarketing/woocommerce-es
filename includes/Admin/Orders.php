<?php
/**
 * Library for importing orders
 *
 * @package    WordPress
 * @author     David Perez <david@closemarketing.es>
 * @copyright  2019 Closemarketing
 * @version    1.0
 */

namespace CLOSE\ConnectEcommerce\Admin;

defined( 'ABSPATH' ) || exit;

use CLOSE\ConnectEcommerce\Helpers\ORDER;
use CLOSE\ConnectEcommerce\Helpers\HELPER;

/**
 * Class Orders integration
 */
class Orders {
	/**
	 * Array of options
	 *
	 * @var array
	 */
	private $options;

	/**
	 * Private Meta key order.
	 *
	 * @var [type]
	 */
	private $meta_key_order;

	/**
	 * API Object
	 *
	 * @var object
	 */
	private $connapi_erp;

	/**
	 * Settings
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Message error orders
	 *
	 * @var array
	 */
	private $msg_error_orders;

	/**
	 * Fallback for the "Create document for free Orders?" setting, from the
	 * connector's own order_import_free_order option.
	 *
	 * @var string
	 */
	private $default_freeorder;

	/**
	 * Order statuses that trigger/qualify for ERP sync: 'all', 'paid', 'completed' or 'manual'.
	 *
	 * @var string
	 */
	private $ecstatus;

	/**
	 * Init and hook in the integration.
	 *
	 * @param array $connector Connector.
	 */
	public function __construct( $connector ) {
		if ( empty( $connector ) || empty( $connector['connector'] ) || empty( $connector['options'] ) || empty( $connector['connapi_erp'] ) ) {
			return;
		}
		$this->options           = $connector['options'];
		$this->settings          = $connector['settings'] ?? array();
		$this->connapi_erp       = $connector['connapi_erp'];
		$ecstatus                = isset( $this->settings['ecstatus'] ) ? $this->settings['ecstatus'] : $this->options['order_only_order_completed'];
		$this->ecstatus          = $ecstatus;
		$this->meta_key_order    = '_' . $this->options['slug'] . '_invoice_id';
		$this->default_freeorder = ! empty( $this->options['order_import_free_order'] ) ? 'yes' : 'no';

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueues' ) );
		add_action( 'wp_ajax_connect_ecommerce_sync_orders', array( $this, 'sync_orders' ) );
		add_action( 'conecom_async_send_order_erp', array( $this, 'async_send_order_erp' ) );

		if ( 'all' === $ecstatus ) {
			add_action( 'woocommerce_order_status_pending', array( $this, 'send_order_erp' ) );
			add_action( 'woocommerce_order_status_failed', array( $this, 'send_order_erp' ) );
			add_action( 'woocommerce_order_status_processing', array( $this, 'send_order_erp' ) );
			add_action( 'woocommerce_order_status_refunded', array( $this, 'send_order_erp' ) );
			add_action( 'woocommerce_order_status_cancelled', array( $this, 'send_order_erp' ) );
			add_action( 'woocommerce_refund_created', array( $this, 'refunded_created' ), 10, 2 );
		} elseif ( 'paid' === $ecstatus ) {
			add_action( 'woocommerce_payment_complete', array( $this, 'send_order_erp' ) );
		}
		// With "manual", the document is only created on request (order metabox button
		// or manual sync), so none of the automatic status hooks are registered, including
		// the "completed" one that otherwise always applies regardless of $ecstatus.
		if ( 'manual' !== $ecstatus ) {
			add_action( 'woocommerce_order_status_completed', array( $this, 'send_order_erp' ) );
		}

		// Email attachments.
		if ( $this->options['order_send_attachments'] ) {
			add_filter( 'woocommerce_email_attachments', array( $this, 'attach_file_woocommerce_email' ), 10, 3 );
		}

		// Order Columns HPOS.
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'custom_shop_order_column' ), 20 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'custom_orders_list_column_content' ), 20, 2 );
		// Order Columns CPT.
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'custom_shop_order_column' ), 20 );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'custom_orders_list_column_content' ), 20, 2 );

		// Ajax.
		add_action( 'wp_ajax_sync_erp_order', array( $this, 'sync_erp_order' ) );
		add_action( 'wp_ajax_nopriv_sync_erp_order', array( $this, 'sync_erp_order' ) );
	}

	/**
	 * Enqueues styles for orders admin page
	 *
	 * @return void
	 */
	public function admin_enqueues() {
		$is_connect_ecommerce_page = isset( $_GET['page'] ) && 'connect_ecommerce' === $_GET['page'];
		$current_tab               = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : '';
		$current_subtab            = isset( $_GET['subtab'] ) ? sanitize_text_field( wp_unslash( $_GET['subtab'] ) ) : 'sync_products';

		$is_sync_page = $is_connect_ecommerce_page
			&& str_starts_with( $current_tab, 'connector_' )
			&& in_array( $current_subtab, array( 'sync_products', 'sync_orders' ), true );

		if ( $is_sync_page ) {
			wp_enqueue_style(
				'conecom-admin-import',
				CONECOM_PLUGIN_URL . 'includes/assets/admin-import.css',
				array(),
				CONECOM_VERSION
			);
		}
	}

	/**
	 * Send order to ERP
	 *
	 * @param int $order_id Order id.
	 *
	 * @return void
	 */
	public function send_order_erp( $order_id ) {
		if ( function_exists( 'as_schedule_single_action' ) ) {
			$pending = as_get_scheduled_actions(
				array(
					'hook'   => 'conecom_async_send_order_erp',
					'args'   => array( $order_id ),
					'status' => \ActionScheduler_Store::STATUS_PENDING,
				),
				'ids'
			);
			if ( empty( $pending ) ) {
				as_schedule_single_action( time() + 30, 'conecom_async_send_order_erp', array( $order_id ), 'connect-ecommerce' );
			}
		} else {
			ORDER::create_invoice( $this->settings, $order_id, $this->meta_key_order, $this->options['slug'], $this->connapi_erp, false, $this->default_freeorder );
		}
	}

	/**
	 * Process async order sync via Action Scheduler.
	 *
	 * Re-checks the current setting instead of trusting the hook that queued this action:
	 * with the 30 second delay before this runs, the merchant may have switched to "manual"
	 * in between, and an already-queued action must not create the document automatically.
	 *
	 * @param int $order_id Order id.
	 * @return void
	 */
	public function async_send_order_erp( $order_id ) {
		if ( 'manual' === $this->ecstatus ) {
			return;
		}
		ORDER::create_invoice( $this->settings, $order_id, $this->meta_key_order, $this->options['slug'], $this->connapi_erp, false, $this->default_freeorder );
	}

	/**
	 * Refund created
	 *
	 * @param int   $refund_id Refund id.
	 * @param array $args Arguments.
	 * @return void
	 */
	public function refunded_created( $refund_id, $args ) {
	}

	/**
	 * Order statuses eligible for the "Manual" bulk export, including any
	 * custom status a store has registered (e.g. "awaiting-fulfillment"),
	 * excluding checkout-draft orders which were never actually placed.
	 *
	 * @return array
	 */
	private function get_exportable_order_statuses() {
		$excluded = array( 'checkout-draft' );
		$statuses = array();
		foreach ( array_keys( wc_get_order_statuses() ) as $status ) {
			$status = str_replace( 'wc-', '', $status );
			if ( in_array( $status, $excluded, true ) ) {
				continue;
			}
			$statuses[] = $status;
		}
		return $statuses;
	}

	/**
	 * Resolves the connapi_erp/settings/options/meta_key_order quadruplet for a connector ID.
	 *
	 * Falls back to this instance's connector when no connector_id is given. When an explicit
	 * connector_id is requested but that connector is inactive, or has the 'orders' workflow
	 * disabled, connapi_erp is returned as null: callers must not sync orders in that case.
	 *
	 * @param string $connector_id Connector ID from request, or empty for the default connector.
	 * @return array List of ( $connapi_erp, $settings, $options, $meta_key_order ).
	 */
	private function resolve_connector( $connector_id ) {
		if ( ! empty( $connector_id ) ) {
			$connector_definitions = apply_filters( 'conecom_options_plugin', array() );
			$connector_data        = HELPER::get_connector_by_id( $connector_id, $connector_definitions );
			if ( ! $connector_data || ! HELPER::is_workflow_enabled_for_connector( $connector_data['meta'] ?? array(), 'orders' ) ) {
				return array( null, array(), array(), '' );
			}
			if ( isset( $connector_data['connapi_erp'] ) ) {
				$options = $connector_data['options'];
				return array( $connector_data['connapi_erp'], $connector_data['settings'], $options, '_' . $options['slug'] . '_invoice_id' );
			}
		}
		return array( $this->connapi_erp, $this->settings, $this->options, $this->meta_key_order );
	}

	/**
	 * Import products from API
	 *
	 * @return void
	 */
	public function sync_orders() {
		if ( ! check_ajax_referer( 'conecom_manual_import_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'error' => 'Invalid nonce' ) );
			return;
		}
		$not_sapi_cli = substr( php_sapi_name(), 0, 3 ) !== 'cli' ? true : false;
		$doing_ajax   = wp_doing_ajax();
		$sync_loop    = isset( $_POST['loop'] ) ? (int) $_POST['loop'] : 0;
		$message      = '';

		// Get connector from request or use default.
		$connector_id = isset( $_POST['connector_id'] ) ? sanitize_text_field( wp_unslash( $_POST['connector_id'] ) ) : '';
		list( $connapi_erp, $settings, $options, $meta_key_order ) = $this->resolve_connector( $connector_id );
		if ( empty( $connapi_erp ) ) {
			wp_send_json_error( array( 'msg' => __( 'Connector not available for orders sync', 'woocommerce-es' ) ) );
			return;
		}

		// Start.
		if ( ! session_id() ) {
			session_start();
		}
		if ( 0 === $sync_loop ) {
			$date_from = isset( $_POST['date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['date_from'] ) ) : '';
			$date_to   = isset( $_POST['date_to'] ) ? sanitize_text_field( wp_unslash( $_POST['date_to'] ) ) : '';

			if ( 'all' === $this->ecstatus ) {
				$sync_statuses = array( 'pending', 'failed', 'processing', 'on-hold', 'refunded', 'cancelled', 'completed' );
			} elseif ( 'manual' === $this->ecstatus ) {
				// Manual is the only mode with no automatic hook to catch orders left out here,
				// so it also includes any custom status a store may have registered.
				$sync_statuses = $this->get_exportable_order_statuses();
			} elseif ( 'paid' === $this->ecstatus ) {
				$sync_statuses = wc_get_is_paid_statuses();
			} else {
				$sync_statuses = array( 'completed' );
			}

			$query_args = array(
				'status'  => $sync_statuses,
				'limit'   => PHP_INT_MAX,
				'orderby' => 'date',
				'order'   => 'DESC',
				'return'  => 'ids',
			);
			if ( $date_from && $date_to ) {
				$query_args['date_created'] = $date_from . '...' . $date_to;
			} elseif ( $date_from ) {
				$query_args['date_created'] = '>=' . $date_from;
			} elseif ( $date_to ) {
				$query_args['date_created'] = '<=' . $date_to;
			} elseif ( ! empty( $this->settings['order_sync_from_date'] ) ) {
				$query_args['date_created'] = '>=' . $this->settings['order_sync_from_date'];
			}
			// Only order IDs are fetched here, not full WC_Order objects, so stores
			// with years of history don't exhaust PHP memory building this list.
			$order_ids   = wc_get_orders( $query_args );
			$sync_orders = array();
			foreach ( $order_ids as $order_id ) {
				$sync_orders[] = array( 'id' => $order_id );
			}
			$_SESSION['conecom_sync_orders'] = HELPER::sanitize_array_recursive( $sync_orders );
		} else {
			$sync_orders = HELPER::sanitize_array_recursive( $_SESSION['conecom_sync_orders'] );
		}

		if ( false === $sync_orders ) {
			if ( $doing_ajax ) {
				wp_send_json_error( array( 'msg' => 'Error' ) );
			} else {
				die();
			}
		} else {
			$orders_count           = count( $sync_orders );
			$item                   = $sync_orders[ $sync_loop ];
			$this->msg_error_orders = array();
			$order                  = wc_get_order( $item['id'] );

			if ( $orders_count ) {
				if ( $sync_loop > $orders_count ) {
					if ( $doing_ajax ) {
						wp_send_json_error(
							array(
								'msg' => __( 'No orders to import', 'woocommerce-es' ),
							)
						);
					} else {
						die( esc_html( __( 'No orders to import', 'woocommerce-es' ) ) );
					}
				} else {
					$ec_invoice_id = $order->get_meta( $meta_key_order );

					if ( ! empty( $ec_invoice_id ) && 'nocreate' !== $ec_invoice_id ) {
						$message .= __( 'Order already exported to API ID:', 'woocommerce-es' ) . $ec_invoice_id;
					} elseif ( 'nocreate' === $ec_invoice_id ) {
						$message .= __( 'Free order not exported', 'woocommerce-es' );
					} else {
						// Manual has no completion hook to retry a postponed order later, so this
						// batch export is treated the same as an explicit per-order manual request.
						$default_freeorder = ! empty( $options['order_import_free_order'] ) ? 'yes' : 'no';
						$result            = ORDER::create_invoice( $settings, $item['id'], $meta_key_order, $options['slug'], $connapi_erp, 'manual' === $this->ecstatus, $default_freeorder );

						$message .= 'ok' === $result['status'] ? __( 'Order Created.', 'woocommerce-es' ) : __( 'Order not created.', 'woocommerce-es' );
						$message .= ' ' . $result['message'];
					}
				}

				if ( $doing_ajax || $not_sapi_cli ) {
					$orders_synced = $sync_loop + 1;

					if ( $orders_synced <= $orders_count ) {
						$order_date = gmdate( 'd-m-Y H:m', strtotime( $order->get_date_created() ) );
						$message    = '[' . date_i18n( 'H:i:s' ) . '] ' . $orders_synced . '/' . $orders_count . ' ' . __( 'orders. ', 'woocommerce-es' ) . ' ' . __( 'Created:', 'woocommerce-es' ) . ' ' . $order_date . ' ' . $message;
						if ( $ec_invoice_id ) {
							$link     = get_bloginfo( 'wpurl' ) . '/wp-admin/admin.php?page=wc-orders&id=' . $item['id'] . '&action=edit';
							$message .= ' <a href="' . $link . '" target="_blank">' . __( 'View', 'woocommerce-es' ) . '</a>';
						}
						if ( $orders_synced == $orders_count ) {
							$message .= '<p class="finish">' . __( 'All caught up!', 'woocommerce-es' ) . '</p>';
						}

						$args = array(
							'message'      => $message,
							'orders_count' => $orders_count,
							'finish'       => $orders_synced >= $orders_count,
						);
						if ( $doing_ajax ) {
							if ( $orders_synced < $orders_count ) {
								$args['loop'] = $sync_loop + 1;
							}
							wp_send_json_success( $args );
						} elseif ( $not_sapi_cli && $orders_synced < $orders_count ) {
							$url  = home_url() . '/?sync=true';
							$url .= '&syncLoop=' . ( $sync_loop + 1 );
							echo esc_html( $args['msg'] );
							die( 0 );
						}
					}
				}
			} elseif ( $doing_ajax ) {
				wp_send_json_error( array( 'msg' => __( 'No orders to import', 'woocommerce-es' ) ) );
			} else {
				die( esc_html( __( 'No orders to import', 'woocommerce-es' ) ) );
			}
		}
		if ( $doing_ajax ) {
			wp_die();
		}
	}

	/**
	 * Email attachmets
	 *
	 * @param file    $attachments Files to attach.
	 * @param integer $action      Action name.
	 * @param object  $email_order Order object.
	 * @return file
	 */
	public function attach_file_woocommerce_email( $attachments, $action, $email_order ) {
		$order = wc_get_order( $email_order );
		if ( ! $order ) {
			return $attachments;
		}
		$api_doc_id   = $order->get_meta( '_' . $this->options['slug'] . '_doc_id' );
		$api_doc_type = $order->get_meta( '_' . $this->options['slug'] . '_doc_type' );

		if ( $api_doc_id && ! empty( $this->connapi_erp ) && HELPER::connector_supports( $this->connapi_erp, 'get_order_pdf' ) ) {
			$file_document_path = $this->connapi_erp->get_order_pdf( $this->settings, $api_doc_type, $api_doc_id );

			// Check if file exists and is readable before attaching.
			if ( is_readable( $file_document_path ) && is_file( $file_document_path ) ) {
				$attachments[] = $file_document_path;
			}
		}

		return $attachments;
	}

	/**
	 * Add columns to order list
	 *
	 * @param array $columns Columns for order.
	 * @return array
	 */
	public function custom_shop_order_column( $columns ) {
		$reordered_columns = array();
		// Inserting columns to a specific location.
		foreach ( $columns as $key => $column ) {
			$reordered_columns[ $key ] = $column;
			if ( 'order_status' === $key ) {
				// Inserting after "Status" column.
				$reordered_columns[ $this->options['slug'] ] = $this->options['name'];
			}
		}
		return $reordered_columns;
	}

	/**
	 * Adding custom fields meta data for each new column
	 *
	 * @param string $column Column name.
	 * @param int    $order_id $order id.
	 * @return void
	 */
	public function custom_orders_list_column_content( $column, $order_id ) {
		switch ( $column ) {
			case $this->options['slug']:
				// Get custom order meta data.
				$order = wc_get_order( $order_id );
				if ( ! $order ) {
					break;
				}
				$invoice_id = $order->get_meta( $this->meta_key_order );
				if ( 'nocreate' === $invoice_id ) {
					break;
				}
				$edit_url = $this->connapi_erp->get_url_link_api( $order );
				if ( $edit_url ) {
					echo '<a href="' . esc_url( $edit_url ) . '" target="_blank">';
				}
				echo esc_html( $invoice_id );
				if ( $edit_url ) {
					echo '</a>';
				}
				unset( $order );
				break;
		}
	}

	/**
	 * Función ajax para sincronizar usuarios con ERP
	 *
	 * @return void
	 */
	public function sync_erp_order() {
		if ( ! check_ajax_referer( 'sync_erp_order_nonce', 'nonce' ) ) {
			wp_send_json_error( array( 'error' => 'Error' ) );
		}
		$order_id     = isset( $_POST['order_id'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['order_id'] ) ) : 0;
		$type         = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';
		$connector_id = isset( $_POST['connector_id'] ) ? sanitize_text_field( wp_unslash( $_POST['connector_id'] ) ) : '';

		list( $connapi_erp, $settings, $options, $meta_key_order ) = $this->resolve_connector( $connector_id );
		if ( empty( $connapi_erp ) ) {
			wp_send_json_error( array( 'message' => __( 'Connector not available for orders sync', 'woocommerce-es' ) ) );
			return;
		}

		$default_freeorder = ! empty( $options['order_import_free_order'] ) ? 'yes' : 'no';

		if ( 'erp-post' === $type ) {
			$result = ORDER::create_invoice( $settings, $order_id, $meta_key_order, $options['slug'], $connapi_erp, true, $default_freeorder );
		}

		// Check result status and respond accordingly.
		if ( isset( $result['status'] ) && 'error' === $result['status'] ) {
			wp_send_json_error(
				array(
					'message'  => $result['message'] ?? __( 'Unknown error occurred', 'woocommerce-es' ),
					'order_id' => $order_id,
				)
			);
		} else {
			wp_send_json_success(
				array(
					'message'  => $result['message'] ?? __( 'Order sent successfully', 'woocommerce-es' ),
					'order_id' => $order_id,
				)
			);
		}
	}
}
