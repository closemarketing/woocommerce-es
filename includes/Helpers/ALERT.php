<?php
/**
 * Alert Notifications Helper
 *
 * @package    WordPress
 * @author     David Perez <david@close.technology>
 * @copyright  2025 Closemarketing
 * @version    1.0
 */

namespace CLOSE\ConnectEcommerce\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Alert Notifications.
 *
 * @since 1.0.0
 */
class ALERT {
	/**
	 * Send alert notification
	 *
	 * @param string $subject Subject of alert.
	 * @param string $message Message content.
	 * @param array  $context Additional context data.
	 *
	 * @return bool Success status.
	 */
	public static function send_alert( $subject, $message, $context = array(), $is_error = true ) {
		$settings      = get_option( 'connect_ecommerce_alerts' );
		$alert_method  = isset( $settings['alert_method'] ) ? $settings['alert_method'] : 'email';
		$alert_enabled = isset( $settings['alert_enabled'] ) ? $settings['alert_enabled'] : 'no';

		if ( 'yes' !== $alert_enabled ) {
			return false;
		}

		// Log to WooCommerce logger always.
		$logger = wc_get_logger();
		if ( $is_error ) {
			$logger->error(
				$subject . ': ' . $message,
				array(
					'source'  => 'connect-ecommerce-alerts',
					'context' => $context,
				)
			);
		} else {
			$logger->info(
				$subject . ': ' . $message,
				array(
					'source'  => 'connect-ecommerce-alerts',
					'context' => $context,
				)
			);
		}

		$result = false;

		if ( 'email' === $alert_method ) {
			$result = self::send_email_alert( $subject, $message, $context, $is_error );
		} elseif ( 'slack' === $alert_method ) {
			$result = self::send_slack_alert( $subject, $message, $context, $is_error );
		}

		return $result;
	}

	/**
	 * Send email alert
	 *
	 * @param string $subject Subject of email.
	 * @param string $message Message content.
	 * @param array  $context Additional context data.
	 *
	 * @return bool Success status.
	 */
	private static function send_email_alert( $subject, $message, $context = array(), $is_error = true ) {
		$settings      = get_option( 'connect_ecommerce_alerts' );
		$email_address = isset( $settings['alert_email'] ) ? $settings['alert_email'] : get_option( 'admin_email' );

		$border_color = $is_error ? '#dc3232' : '#46b450';

		$body  = '<html><body>';
		$body .= '<h2>' . esc_html( $subject ) . '</h2>';
		$body .= '<div style="background: #f8f8f8; padding: 15px; margin: 10px 0; border-left: 4px solid ' . esc_attr( $border_color ) . ';">';
		$body .= wp_kses_post( $message );
		$body .= '</div>';

		if ( ! empty( $context ) ) {
			$body .= '<h3>' . __( 'Additional Information:', 'woocommerce-es' ) . '</h3>';
			$body .= '<ul>';
			foreach ( $context as $key => $value ) {
				$body .= '<li><strong>' . esc_html( ucfirst( str_replace( '_', ' ', $key ) ) ) . ':</strong> ' . esc_html( $value ) . '</li>';
			}
			$body .= '</ul>';
		}

		$body .= '<p><em>' . __( 'Site:', 'woocommerce-es' ) . ' ' . esc_html( get_bloginfo( 'name' ) ) . '</em></p>';
		$body .= '<p><em>' . __( 'Time:', 'woocommerce-es' ) . ' ' . esc_html( current_time( 'mysql' ) ) . '</em></p>';
		$body .= '</body></html>';

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		return wp_mail( $email_address, '[' . get_bloginfo( 'name' ) . '] ' . $subject, $body, $headers );
	}

	/**
	 * Send Slack alert
	 *
	 * @param string $subject Subject of alert.
	 * @param string $message Message content.
	 * @param array  $context Additional context data.
	 *
	 * @return bool Success status.
	 */
	private static function send_slack_alert( $subject, $message, $context = array(), $is_error = true ) {
		$settings    = get_option( 'connect_ecommerce_alerts' );
		$webhook_url = isset( $settings['slack_webhook'] ) ? $settings['slack_webhook'] : '';

		if ( empty( $webhook_url ) ) {
			return false;
		}

		$header_emoji = $is_error ? '🚨' : '✅';

		// Build Slack message blocks.
		$blocks = array(
			array(
				'type' => 'header',
				'text' => array(
					'type' => 'plain_text',
					'text' => $header_emoji . ' ' . $subject,
				),
			),
			array(
				'type' => 'section',
				'text' => array(
					'type' => 'mrkdwn',
					'text' => $message,
				),
			),
		);

		if ( ! empty( $context ) ) {
			$context_text = '';
			foreach ( $context as $key => $value ) {
				$context_text .= '*' . ucfirst( str_replace( '_', ' ', $key ) ) . ':* ' . $value . "\n";
			}
			$blocks[] = array(
				'type' => 'section',
				'text' => array(
					'type' => 'mrkdwn',
					'text' => $context_text,
				),
			);
		}

		$blocks[] = array(
			'type'     => 'context',
			'elements' => array(
				array(
					'type' => 'mrkdwn',
					'text' => '_Site:_ ' . get_bloginfo( 'name' ) . ' | _Time:_ ' . current_time( 'mysql' ),
				),
			),
		);

		$payload = array(
			'blocks' => $blocks,
		);

		$response = wp_remote_post(
			$webhook_url,
			array(
				'body'    => wp_json_encode( $payload ),
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'Slack alert failed: ' . $response->get_error_message() );
			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		return 200 === $response_code;
	}

	/**
	 * Send product import errors alert
	 *
	 * @param array $product_errors Array of product errors.
	 *
	 * @return bool Success status.
	 */
	public static function send_product_errors_alert( $product_errors ) {
		if ( empty( $product_errors ) ) {
			return false;
		}

		$subject = __( 'Product Import Errors', 'woocommerce-es' );
		$message = sprintf(
			// translators: %d is the number of products that failed to import.
			_n(
				'%d product failed to import during automated synchronization.',
				'%d products failed to import during automated synchronization.',
				count( $product_errors ),
				'woocommerce-es'
			),
			count( $product_errors )
		);

		$message .= "\n\n" . '*' . __( 'Failed Products:', 'woocommerce-es' ) . "*\n";
		foreach ( $product_errors as $error ) {
			$message .= '• ' . $error['name'] . ' (' . __( 'SKU:', 'woocommerce-es' ) . ' ' . $error['sku'] . '): ' . $error['error'] . "\n";
		}

		$context = array(
			'error_count' => count( $product_errors ),
		);

		return self::send_alert( $subject, $message, $context );
	}

	/**
	 * Send order submission error alert
	 *
	 * @param int    $order_id Order ID.
	 * @param string $error_message Error message.
	 *
	 * @return bool Success status.
	 */
	public static function send_order_error_alert( $order_id, $error_message ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		$subject = __( 'Order Submission Error', 'woocommerce-es' );
		$message = sprintf(
			// translators: %s is the order ID.
			__( 'Failed to submit order #%s to ERP.', 'woocommerce-es' ),
			$order_id
		);

		$message .= "\n\n" . '*' . __( 'Error:', 'woocommerce-es' ) . '* ' . $error_message;
		$message .= "\n\n" . '*' . __( 'Order Details:', 'woocommerce-es' ) . '*';
		$message .= "\n" . '• ' . __( 'Customer:', 'woocommerce-es' ) . ' ' . $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
		$message .= "\n" . '• ' . __( 'Total:', 'woocommerce-es' ) . ' ' . $order->get_total() . ' ' . $order->get_currency();
		$message .= "\n" . '• ' . __( 'Date:', 'woocommerce-es' ) . ' ' . $order->get_date_created()->date_i18n( 'Y-m-d H:i:s' );

		$context = array(
			'order_id'     => $order_id,
			'order_status' => $order->get_status(),
			'order_total'  => $order->get_total(),
		);

		return self::send_alert( $subject, $message, $context );
	}

	/**
	 * Test alert notification
	 *
	 * @return bool Success status.
	 */
	public static function send_test_alert() {
		$subject = __( 'Test Alert - Connect Ecommerce', 'woocommerce-es' );
		$message = __( 'This is a test alert to verify your notification settings are working correctly.', 'woocommerce-es' );

		$context = array(
			'test' => 'true',
		);

		return self::send_alert( $subject, $message, $context );
	}
}
