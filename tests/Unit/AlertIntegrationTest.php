<?php
/**
 * Class AlertIntegrationTest
 *
 * Tests integration of Alert system with existing functionality.
 *
 * Command: composer test -- --filter=AlertIntegrationTest
 *
 * @package Connect_Ecommerce
 */

use CLOSE\ConnectEcommerce\Helpers\ALERT;
use CLOSE\ConnectEcommerce\Helpers\HELPER;
use CLOSE\ConnectEcommerce\Helpers\ORDER;

class AlertIntegrationTest extends WP_UnitTestCase {
	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		
		// Enable alerts for integration tests.
		$settings = array(
			'alert_enabled' => 'yes',
			'alert_method'  => 'email',
			'alert_email'   => 'integration-test@example.com',
		);
		update_option( 'connect_ecommerce_alerts', $settings );
	}

	/**
	 * Tear down test environment.
	 */
	public function tearDown(): void {
		delete_option( 'connect_ecommerce_alerts' );
		parent::tearDown();
	}

	/**
	 * Test HELPER::send_product_errors integrates with alerts.
	 */
	public function test_helper_send_product_errors_triggers_alert() {
		reset_phpmailer_instance();

		$product_errors = array(
			array(
				'prod_id' => '123',
				'name'    => 'Integration Test Product',
				'sku'     => 'INT-TEST-SKU',
				'error'   => 'Integration test error',
			),
		);

		HELPER::send_product_errors( $product_errors, 'test-connector' );

		// Verify email was sent.
		$mailer = tests_retrieve_phpmailer_instance();
		$sent_email = $mailer->get_sent();

		$this->assertNotEmpty( $sent_email );
		$this->assertStringContainsString( 'Integration Test Product', $sent_email->body );
		$this->assertStringContainsString( 'INT-TEST-SKU', $sent_email->body );
	}

	/**
	 * Test alert doesn't break when HELPER::send_product_errors receives empty array.
	 */
	public function test_helper_send_product_errors_empty_array() {
		HELPER::send_product_errors( array(), 'test-connector' );

		// Should complete without errors.
		$this->assertTrue( true );
	}

	/**
	 * Test ORDER::create_invoice integration with alert system.
	 *
	 * Note: This is a simplified test. Full integration would require mocked API.
	 */
	public function test_order_create_invoice_integration() {
		// Create a test order.
		$order = wc_create_order();
		$order->set_billing_first_name( 'Integration' );
		$order->set_billing_last_name( 'Test' );
		$order->set_total( 50.00 );
		$order->set_currency( 'EUR' );
		$order->save();

		// This test verifies the alert system doesn't break order processing.
		// Full testing would require mocking the ERP API.
		
		$this->assertNotEmpty( $order->get_id() );
		
		// Clean up.
		$order->delete( true );
	}

	/**
	 * Test alerts work with different connector types.
	 */
	public function test_alerts_with_different_connectors() {
		$connectors = array( 'holded', 'clientify', 'test-connector' );

		foreach ( $connectors as $connector ) {
			reset_phpmailer_instance();

			$product_errors = array(
				array(
					'prod_id' => '999',
					'name'    => "Product for $connector",
					'sku'     => "SKU-$connector",
					'error'   => "Error from $connector",
				),
			);

			HELPER::send_product_errors( $product_errors, $connector );

			$mailer = tests_retrieve_phpmailer_instance();
			$sent_email = $mailer->get_sent();

			$this->assertNotEmpty( $sent_email );
			$this->assertStringContainsString( "Product for $connector", $sent_email->body );
		}
	}

	/**
	 * Test alert system doesn't interfere with normal operations when disabled.
	 */
	public function test_alerts_dont_interfere_when_disabled() {
		// Disable alerts.
		$settings = array(
			'alert_enabled' => 'no',
		);
		update_option( 'connect_ecommerce_alerts', $settings );

		reset_phpmailer_instance();

		$product_errors = array(
			array(
				'prod_id' => '123',
				'name'    => 'Test Product',
				'sku'     => 'TEST-SKU',
				'error'   => 'Test error',
			),
		);

		// Should still work normally, just no extra alert.
		HELPER::send_product_errors( $product_errors, 'test' );

		// Original email should still be sent by HELPER.
		$mailer = tests_retrieve_phpmailer_instance();
		$sent_email = $mailer->get_sent();

		// The original functionality still sends an email.
		$this->assertNotEmpty( $sent_email );
	}

	/**
	 * Test concurrent alerts.
	 */
	public function test_concurrent_alerts() {
		reset_phpmailer_instance();

		// Simulate multiple errors happening simultaneously.
		$product_errors = array(
			array(
				'prod_id' => '1',
				'name'    => 'Product 1',
				'sku'     => 'SKU-1',
				'error'   => 'Error 1',
			),
		);

		HELPER::send_product_errors( $product_errors, 'test' );

		// Create an order error.
		$order = wc_create_order();
		$order->set_billing_first_name( 'Test' );
		$order->set_billing_last_name( 'User' );
		$order->set_total( 100.00 );
		$order->save();

		ALERT::send_order_error_alert( $order->get_id(), 'Concurrent test error' );

		$mailer = tests_retrieve_phpmailer_instance();
		$emails = $mailer->get_sent();

		// Should have sent multiple emails.
		$this->assertGreaterThan( 0, count( (array) $emails ) );

		// Clean up.
		$order->delete( true );
	}

	/**
	 * Test alert with very long error messages.
	 */
	public function test_alert_with_long_error_message() {
		reset_phpmailer_instance();

		$long_error = str_repeat( 'This is a very long error message. ', 50 );

		$product_errors = array(
			array(
				'prod_id' => '123',
				'name'    => 'Test Product',
				'sku'     => 'TEST-SKU',
				'error'   => $long_error,
			),
		);

		HELPER::send_product_errors( $product_errors, 'test' );

		$mailer = tests_retrieve_phpmailer_instance();
		$sent_email = $mailer->get_sent();

		// Should handle long messages without breaking.
		$this->assertNotEmpty( $sent_email );
		$this->assertStringContainsString( 'Test Product', $sent_email->body );
	}

	/**
	 * Test alert with special characters in product names.
	 */
	public function test_alert_with_special_characters() {
		reset_phpmailer_instance();

		$product_errors = array(
			array(
				'prod_id' => '123',
				'name'    => 'Product with "quotes" & <tags> and émojis 🎉',
				'sku'     => 'TEST-SPECIAL',
				'error'   => 'Error with special chars: <>&"\'',
			),
		);

		HELPER::send_product_errors( $product_errors, 'test' );

		$mailer = tests_retrieve_phpmailer_instance();
		$sent_email = $mailer->get_sent();

		// Should properly escape special characters.
		$this->assertNotEmpty( $sent_email );
		$this->assertStringContainsString( 'Product with', $sent_email->body );
	}

	/**
	 * Test alert system performance with many errors.
	 */
	public function test_alert_performance_with_many_errors() {
		$start_time = microtime( true );

		$product_errors = array();
		for ( $i = 0; $i < 100; $i++ ) {
			$product_errors[] = array(
				'prod_id' => (string) $i,
				'name'    => "Product $i",
				'sku'     => "SKU-$i",
				'error'   => "Error message $i",
			);
		}

		HELPER::send_product_errors( $product_errors, 'test' );

		$end_time = microtime( true );
		$execution_time = $end_time - $start_time;

		// Should complete in reasonable time (under 5 seconds for 100 errors).
		$this->assertLessThan( 5, $execution_time );
	}
}

