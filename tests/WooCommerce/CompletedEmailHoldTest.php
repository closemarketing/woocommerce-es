<?php
/**
 * Class CompletedEmailHoldTest
 *
 * The "Order completed" email is held while the ERP document is still pending,
 * and released once the async sync finishes, so the PDF can be attached.
 *
 * Command: composer test -- --filter=CompletedEmailHoldTest
 *
 * @package Connect_Ecommerce
 */

use CLOSE\ConnectEcommerce\Admin\Orders;

/**
 * Fake connector that supports PDF download.
 */
class Conecom_Test_Pdf_Connector {
	/**
	 * Returns no file; the tests only check that the email is sent.
	 *
	 * @return string
	 */
	public function get_order_pdf() {
		return '';
	}
}

/**
 * Fake connector without PDF support.
 */
class Conecom_Test_No_Pdf_Connector {
}

/**
 * Tests for holding and releasing the "Order completed" email.
 *
 * @group woocommerce
 */
class CompletedEmailHoldTest extends WP_UnitTestCase {

	/**
	 * Number of emails sent through wp_mail.
	 *
	 * @var int
	 */
	private $mails_sent = 0;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->assertTrue( class_exists( 'WooCommerce' ), 'WooCommerce is not active' );
		$this->mails_sent = 0;
		add_filter( 'pre_wp_mail', array( $this, 'count_mail' ) );
		as_unschedule_all_actions( 'conecom_async_send_order_erp' );
		as_unschedule_all_actions( 'conecom_release_held_email' );
	}

	/**
	 * Tear down test environment.
	 */
	public function tearDown(): void {
		remove_filter( 'pre_wp_mail', array( $this, 'count_mail' ) );
		parent::tearDown();
	}

	/**
	 * Counts sent emails without sending them.
	 *
	 * @return bool
	 */
	public function count_mail() {
		++$this->mails_sent;
		return true;
	}

	/**
	 * Builds an Orders instance for a test connector.
	 *
	 * @param object $connapi  Connector API object.
	 * @param string $ecstatus Order sync status setting.
	 * @return Orders
	 */
	private function make_orders( $connapi, $ecstatus = 'completed' ) {
		return new Orders(
			array(
				'connector'   => 'test',
				'options'     => array(
					'slug'                       => 'conecom_test',
					'name'                       => 'Test',
					'order_send_attachments'     => true,
					'order_only_order_completed' => $ecstatus,
					'order_import_free_order'    => false,
				),
				'settings'    => array( 'ecstatus' => $ecstatus ),
				'connapi_erp' => $connapi,
			)
		);
	}

	/**
	 * Creates an order with a billing email.
	 *
	 * @param float $total Order total.
	 * @return WC_Order
	 */
	private function make_order( $total = 10 ) {
		$order = wc_create_order();
		$order->set_billing_email( 'customer@example.com' );
		$order->set_total( $total );
		$order->save();
		return $order;
	}

	/**
	 * Holds the email and queues the fallback when the document is pending.
	 */
	public function test_holds_email_when_document_pending() {
		$orders = $this->make_orders( new Conecom_Test_Pdf_Connector() );
		$order  = $this->make_order();

		$this->assertFalse( $orders->maybe_hold_completed_email( true, $order, null ) );
		$this->assertEquals( 1, wc_get_order( $order->get_id() )->get_meta( '_conecom_test_email_held' ) );
		$this->assertNotFalse( as_next_scheduled_action( 'conecom_async_send_order_erp', array( $order->get_id() ) ) );
		$this->assertNotFalse( as_next_scheduled_action( 'conecom_release_held_email', array( $order->get_id() ) ) );
	}

	/**
	 * Does not hold when the document already exists.
	 */
	public function test_does_not_hold_when_document_exists() {
		$orders = $this->make_orders( new Conecom_Test_Pdf_Connector() );
		$order  = $this->make_order();
		$order->update_meta_data( '_conecom_test_doc_id', 'abc' );
		$order->save();

		$this->assertTrue( $orders->maybe_hold_completed_email( true, $order, null ) );
		$this->assertEmpty( $order->get_meta( '_conecom_test_email_held' ) );
	}

	/**
	 * Does not hold in manual mode, for free orders or without PDF support.
	 */
	public function test_does_not_hold_when_no_document_is_coming() {
		$order = $this->make_order();
		$this->assertTrue( $this->make_orders( new Conecom_Test_Pdf_Connector(), 'manual' )->maybe_hold_completed_email( true, $order, null ) );
		$this->assertTrue( $this->make_orders( new Conecom_Test_No_Pdf_Connector() )->maybe_hold_completed_email( true, $order, null ) );

		$order->update_meta_data( '_conecom_test_invoice_id', 'nocreate' );
		$order->save();
		$this->assertTrue( $this->make_orders( new Conecom_Test_Pdf_Connector() )->maybe_hold_completed_email( true, $order, null ) );

		$this->assertEmpty( wc_get_order( $order->get_id() )->get_meta( '_conecom_test_email_held' ) );
	}

	/**
	 * Uses a held flag per connector: another connector's flag is neither set nor released.
	 */
	public function test_held_flag_is_per_connector() {
		$this->assertSame( '_holded_email_held', Orders::get_email_held_meta_key( 'holded' ) );

		$orders = $this->make_orders( new Conecom_Test_Pdf_Connector() );
		$order  = $this->make_order();
		$order->update_meta_data( '_other_email_held', 1 );
		$order->save();

		$orders->release_held_email( $order->get_id() );
		$this->assertSame( 0, $this->mails_sent );
		$this->assertEquals( 1, wc_get_order( $order->get_id() )->get_meta( '_other_email_held' ) );

		$this->assertFalse( $orders->maybe_hold_completed_email( true, $order, null ) );
		$this->assertEquals( 1, wc_get_order( $order->get_id() )->get_meta( '_conecom_test_email_held' ) );
	}

	/**
	 * Keeps a disabled email disabled.
	 */
	public function test_keeps_disabled_email_disabled() {
		$orders = $this->make_orders( new Conecom_Test_Pdf_Connector() );
		$this->assertFalse( $orders->maybe_hold_completed_email( false, $this->make_order(), null ) );
	}

	/**
	 * Releasing sends the email once and clears the flag.
	 */
	public function test_release_sends_email_once() {
		$orders = $this->make_orders( new Conecom_Test_Pdf_Connector() );
		$order  = $this->make_order();
		$order->update_meta_data( '_conecom_test_email_held', 1 );
		$order->save();

		$orders->release_held_email( $order->get_id() );
		$orders->release_held_email( $order->get_id() );

		$this->assertSame( 1, $this->mails_sent );
		$this->assertEmpty( wc_get_order( $order->get_id() )->get_meta( '_conecom_test_email_held' ) );
		$this->assertFalse( as_next_scheduled_action( 'conecom_release_held_email', array( $order->get_id() ) ) );
	}

	/**
	 * The async sync releases the email even when no document is created.
	 */
	public function test_async_sync_releases_email_without_document() {
		$orders = $this->make_orders( new Conecom_Test_Pdf_Connector() );
		$order  = $this->make_order( 0 );
		$order->update_meta_data( '_conecom_test_email_held', 1 );
		$order->save();

		$orders->async_send_order_erp( $order->get_id() );

		$this->assertSame( 1, $this->mails_sent );
		$this->assertEmpty( wc_get_order( $order->get_id() )->get_meta( '_conecom_test_email_held' ) );
	}
}
