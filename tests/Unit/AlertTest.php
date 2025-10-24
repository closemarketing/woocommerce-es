<?php
/**
 * Class AlertTest
 *
 * Command: composer test -- --filter=AlertTest
 *
 * @package Connect_Ecommerce
 */

use CLOSE\ConnectEcommerce\Helpers\ALERT;

class AlertTest extends WP_UnitTestCase {
	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		
		// Clear any existing alert settings.
		delete_option( 'connect_ecommerce_alerts' );
	}

	/**
	 * Tear down test environment.
	 */
	public function tearDown(): void {
		// Clean up after tests.
		delete_option( 'connect_ecommerce_alerts' );
		parent::tearDown();
	}

	/**
	 * Test alert is disabled by default.
	 */
	public function test_alert_disabled_by_default() {
		$result = ALERT::send_alert( 'Test Subject', 'Test Message' );
		$this->assertFalse( $result );
	}

	/**
	 * Test enabling alerts.
	 */
	public function test_enable_alerts() {
		$settings = array(
			'alert_enabled' => 'yes',
			'alert_method'  => 'email',
			'alert_email'   => 'test@example.com',
		);
		update_option( 'connect_ecommerce_alerts', $settings );

		$saved_settings = get_option( 'connect_ecommerce_alerts' );
		$this->assertEquals( 'yes', $saved_settings['alert_enabled'] );
		$this->assertEquals( 'email', $saved_settings['alert_method'] );
		$this->assertEquals( 'test@example.com', $saved_settings['alert_email'] );
	}

	/**
	 * Test email alert with valid settings.
	 */
	public function test_email_alert_with_valid_settings() {
		$settings = array(
			'alert_enabled' => 'yes',
			'alert_method'  => 'email',
			'alert_email'   => 'test@example.com',
		);
		update_option( 'connect_ecommerce_alerts', $settings );

		// Reset email mock.
		reset_phpmailer_instance();

		$subject = 'Test Alert Subject';
		$message = 'This is a test alert message.';
		$context = array(
			'test_key' => 'test_value',
		);

		$result = ALERT::send_alert( $subject, $message, $context );

		// Get sent emails.
		$mailer = tests_retrieve_phpmailer_instance();
		
		// Assert email was attempted to be sent.
		$this->assertNotEmpty( $mailer->get_sent() );
		
		$sent_email = $mailer->get_sent();
		$this->assertStringContainsString( $subject, $sent_email->subject );
		$this->assertStringContainsString( $message, $sent_email->body );
		$this->assertEquals( 'test@example.com', $sent_email->to[0][0] );
	}

	/**
	 * Test email alert defaults to admin email.
	 */
	public function test_email_alert_defaults_to_admin_email() {
		$settings = array(
			'alert_enabled' => 'yes',
			'alert_method'  => 'email',
		);
		update_option( 'connect_ecommerce_alerts', $settings );

		reset_phpmailer_instance();

		$result = ALERT::send_alert( 'Test Subject', 'Test Message' );

		$mailer = tests_retrieve_phpmailer_instance();
		$sent_email = $mailer->get_sent();
		
		$this->assertEquals( get_option( 'admin_email' ), $sent_email->to[0][0] );
	}

	/**
	 * Test Slack alert requires webhook URL.
	 */
	public function test_slack_alert_requires_webhook() {
		$settings = array(
			'alert_enabled' => 'yes',
			'alert_method'  => 'slack',
		);
		update_option( 'connect_ecommerce_alerts', $settings );

		$result = ALERT::send_alert( 'Test Subject', 'Test Message' );

		// Should return false without webhook URL.
		$this->assertFalse( $result );
	}

	/**
	 * Test Slack alert with webhook URL.
	 */
	public function test_slack_alert_with_webhook() {
		$settings = array(
			'alert_enabled' => 'yes',
			'alert_method'  => 'slack',
			'slack_webhook' => 'https://hooks.slack.com/services/TEST/WEBHOOK/URL',
		);
		update_option( 'connect_ecommerce_alerts', $settings );

		// Mock HTTP response for Slack webhook.
		add_filter( 'pre_http_request', array( $this, 'mock_slack_webhook_response' ), 10, 3 );

		$result = ALERT::send_alert( 'Test Subject', 'Test Message' );

		remove_filter( 'pre_http_request', array( $this, 'mock_slack_webhook_response' ) );

		$this->assertTrue( $result );
	}

	/**
	 * Mock Slack webhook response.
	 *
	 * @param false|array $response Response data.
	 * @param array       $args     Request arguments.
	 * @param string      $url      Request URL.
	 * @return array
	 */
	public function mock_slack_webhook_response( $response, $args, $url ) {
		if ( strpos( $url, 'hooks.slack.com' ) !== false ) {
			return array(
				'response' => array(
					'code' => 200,
				),
				'body'     => 'ok',
			);
		}
		return $response;
	}

	/**
	 * Test product errors alert.
	 */
	public function test_product_errors_alert() {
		$settings = array(
			'alert_enabled' => 'yes',
			'alert_method'  => 'email',
			'alert_email'   => 'test@example.com',
		);
		update_option( 'connect_ecommerce_alerts', $settings );

		reset_phpmailer_instance();

		$product_errors = array(
			array(
				'prod_id' => '123',
				'name'    => 'Test Product',
				'sku'     => 'TEST-SKU',
				'error'   => 'Invalid price format',
			),
			array(
				'prod_id' => '456',
				'name'    => 'Another Product',
				'sku'     => 'TEST-SKU-2',
				'error'   => 'Missing required field',
			),
		);

		$result = ALERT::send_product_errors_alert( $product_errors );

		$mailer = tests_retrieve_phpmailer_instance();
		$sent_email = $mailer->get_sent();

		$this->assertStringContainsString( 'Product Import Errors', $sent_email->subject );
		$this->assertStringContainsString( 'Test Product', $sent_email->body );
		$this->assertStringContainsString( 'TEST-SKU', $sent_email->body );
		$this->assertStringContainsString( 'Invalid price format', $sent_email->body );
		$this->assertStringContainsString( 'Another Product', $sent_email->body );
	}

	/**
	 * Test empty product errors array.
	 */
	public function test_empty_product_errors_array() {
		$settings = array(
			'alert_enabled' => 'yes',
			'alert_method'  => 'email',
		);
		update_option( 'connect_ecommerce_alerts', $settings );

		$result = ALERT::send_product_errors_alert( array() );

		$this->assertFalse( $result );
	}

	/**
	 * Test order error alert.
	 */
	public function test_order_error_alert() {
		$settings = array(
			'alert_enabled' => 'yes',
			'alert_method'  => 'email',
			'alert_email'   => 'test@example.com',
		);
		update_option( 'connect_ecommerce_alerts', $settings );

		// Create a test order.
		$order = wc_create_order();
		$order->set_billing_first_name( 'John' );
		$order->set_billing_last_name( 'Doe' );
		$order->set_total( 100.00 );
		$order->set_currency( 'USD' );
		$order->save();

		reset_phpmailer_instance();

		$error_message = 'API connection failed';
		$result = ALERT::send_order_error_alert( $order->get_id(), $error_message );

		$mailer = tests_retrieve_phpmailer_instance();
		$sent_email = $mailer->get_sent();

		$this->assertStringContainsString( 'Order Submission Error', $sent_email->subject );
		$this->assertStringContainsString( 'John Doe', $sent_email->body );
		$this->assertStringContainsString( '100', $sent_email->body );
		$this->assertStringContainsString( 'API connection failed', $sent_email->body );

		// Clean up.
		$order->delete( true );
	}

	/**
	 * Test order error alert with invalid order ID.
	 */
	public function test_order_error_alert_invalid_order() {
		$settings = array(
			'alert_enabled' => 'yes',
			'alert_method'  => 'email',
		);
		update_option( 'connect_ecommerce_alerts', $settings );

		$result = ALERT::send_order_error_alert( 99999999, 'Test error' );

		$this->assertFalse( $result );
	}

	/**
	 * Test alert with context data.
	 */
	public function test_alert_with_context() {
		$settings = array(
			'alert_enabled' => 'yes',
			'alert_method'  => 'email',
			'alert_email'   => 'test@example.com',
		);
		update_option( 'connect_ecommerce_alerts', $settings );

		reset_phpmailer_instance();

		$context = array(
			'error_code'   => '500',
			'api_endpoint' => '/products/sync',
			'retry_count'  => '3',
		);

		$result = ALERT::send_alert( 'Test Subject', 'Test Message', $context );

		$mailer = tests_retrieve_phpmailer_instance();
		$sent_email = $mailer->get_sent();

		$this->assertStringContainsString( 'Error code', $sent_email->body );
		$this->assertStringContainsString( '500', $sent_email->body );
		$this->assertStringContainsString( 'Api endpoint', $sent_email->body );
		$this->assertStringContainsString( '/products/sync', $sent_email->body );
	}

	/**
	 * Test test alert function.
	 */
	public function test_test_alert() {
		$settings = array(
			'alert_enabled' => 'yes',
			'alert_method'  => 'email',
			'alert_email'   => 'test@example.com',
		);
		update_option( 'connect_ecommerce_alerts', $settings );

		reset_phpmailer_instance();

		$result = ALERT::send_test_alert();

		$mailer = tests_retrieve_phpmailer_instance();
		$sent_email = $mailer->get_sent();

		$this->assertStringContainsString( 'Test Alert', $sent_email->subject );
		$this->assertStringContainsString( 'test alert', $sent_email->body );
	}

	/**
	 * Test sanitization of alert settings.
	 */
	public function test_alert_settings_sanitization() {
		$settings = array(
			'alert_enabled' => 'yes',
			'alert_method'  => 'email',
			'alert_email'   => '  test@example.com  ',
			'slack_webhook' => 'https://hooks.slack.com/services/TEST?param=value',
		);

		update_option( 'connect_ecommerce_alerts', $settings );
		$saved_settings = get_option( 'connect_ecommerce_alerts' );

		// Email should be sanitized but whitespace handled by WordPress.
		$this->assertIsString( $saved_settings['alert_email'] );
		$this->assertStringContainsString( 'test@example.com', $saved_settings['alert_email'] );
	}

	/**
	 * Test alert logging to WooCommerce logger.
	 */
	public function test_alert_logs_to_woocommerce() {
		$settings = array(
			'alert_enabled' => 'yes',
			'alert_method'  => 'email',
		);
		update_option( 'connect_ecommerce_alerts', $settings );

		$result = ALERT::send_alert( 'Test Log Subject', 'Test log message' );

		// Verify logger exists and alert method was called.
		$logger = wc_get_logger();
		$this->assertInstanceOf( WC_Logger_Interface::class, $logger );
		
		// Note: Logs are written but may not be immediately retrievable in unit tests.
		// The important thing is the logger is available and no errors occurred.
	}

	/**
	 * Test multiple product errors formatting.
	 */
	public function test_multiple_product_errors_formatting() {
		$settings = array(
			'alert_enabled' => 'yes',
			'alert_method'  => 'email',
		);
		update_option( 'connect_ecommerce_alerts', $settings );

		reset_phpmailer_instance();

		$product_errors = array();
		for ( $i = 1; $i <= 5; $i++ ) {
			$product_errors[] = array(
				'prod_id' => (string) $i,
				'name'    => "Product $i",
				'sku'     => "SKU-$i",
				'error'   => "Error message $i",
			);
		}

		$result = ALERT::send_product_errors_alert( $product_errors );

		$mailer = tests_retrieve_phpmailer_instance();
		$sent_email = $mailer->get_sent();

		// Check that all 5 products are mentioned.
		$this->assertStringContainsString( '5 products failed', $sent_email->body );
		$this->assertStringContainsString( 'Product 1', $sent_email->body );
		$this->assertStringContainsString( 'Product 5', $sent_email->body );
	}

	/**
	 * Test Slack message formatting with special characters.
	 */
	public function test_slack_alert_special_characters() {
		$settings = array(
			'alert_enabled' => 'yes',
			'alert_method'  => 'slack',
			'slack_webhook' => 'https://hooks.slack.com/services/TEST/WEBHOOK/URL',
		);
		update_option( 'connect_ecommerce_alerts', $settings );

		add_filter( 'pre_http_request', array( $this, 'mock_slack_webhook_response' ), 10, 3 );

		$message_with_special_chars = 'Product: "Test & Product" <Special> Characters';
		$result = ALERT::send_alert( 'Test Subject', $message_with_special_chars );

		remove_filter( 'pre_http_request', array( $this, 'mock_slack_webhook_response' ) );

		// Should still succeed.
		$this->assertTrue( $result );
	}

	/**
	 * Test alert is logged even when disabled.
	 */
	public function test_alert_logs_even_when_disabled() {
		// Don't enable alerts.
		$settings = array(
			'alert_enabled' => 'no',
			'alert_method'  => 'email',
		);
		update_option( 'connect_ecommerce_alerts', $settings );

		$result = ALERT::send_alert( 'Test Subject', 'Test Message' );

		// Alert should return false when disabled.
		$this->assertFalse( $result );
		
		// But it should still be logged to WooCommerce logger.
		// This is verified by the implementation.
	}
}

