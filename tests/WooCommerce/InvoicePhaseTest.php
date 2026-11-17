<?php
/**
 * Class InvoicePhaseTest
 *
 * Phase 1 (draft): the document is not approved and WooCommerce attaches no PDF.
 * Phase 2 (auto): the document is approved and the connector sends it to the customer;
 * any failure leaves an order note and the WooCommerce email stays as the fallback.
 *
 * Command: composer test -- --filter=InvoicePhaseTest
 *
 * @package Connect_Ecommerce
 */

use CLOSE\ConnectEcommerce\Admin\Orders;
use CLOSE\ConnectEcommerce\Helpers\ORDER;

/**
 * Fake connector that approves and sends documents.
 */
class Conecom_Test_Phase_Connector {
	/**
	 * Result returned by create_order().
	 *
	 * @var array
	 */
	public $create_result = array();

	/**
	 * Result returned by send_document().
	 *
	 * @var array
	 */
	public $send_result = array( 'status' => 'ok', 'message' => 'sent' );

	/**
	 * Arguments of every send_document() call.
	 *
	 * @var array
	 */
	public $sent = array();

	/**
	 * Fake document creation.
	 *
	 * @return array
	 */
	public function create_order() {
		return $this->create_result;
	}

	/**
	 * Fake document sending.
	 *
	 * @param array  $settings Settings.
	 * @param string $doctype  Document type.
	 * @param string $doc_id   Document id.
	 * @param string $email    Recipient.
	 * @param string $subject  Subject.
	 * @return array
	 */
	public function send_document( $settings, $doctype, $doc_id, $email, $subject = '' ) {
		$this->sent[] = array( $doctype, $doc_id, $email, $subject );
		return $this->send_result;
	}
}

/**
 * Fake connector that cannot send documents.
 */
class Conecom_Test_Phase_Connector_No_Send {
	/**
	 * Fake document creation, approved.
	 *
	 * @return array
	 */
	public function create_order() {
		return array(
			'status'      => 'ok',
			'message'     => 'created',
			'document_id' => 'doc1',
			'invoice_id'  => 'F-1',
			'approved'    => true,
		);
	}
}

/**
 * Tests for the invoice phases.
 *
 * @group woocommerce
 */
class InvoicePhaseTest extends WP_UnitTestCase {

	/**
	 * Approved result of a created document.
	 *
	 * @return array
	 */
	private function approved_result() {
		return array(
			'status'      => 'ok',
			'message'     => 'created',
			'document_id' => 'doc1',
			'invoice_id'  => 'F-1',
			'approved'    => true,
			'approve_msg' => 'approved',
		);
	}

	/**
	 * Creates an order with a billing email and total.
	 *
	 * @param string $email Billing email.
	 * @return WC_Order
	 */
	private function make_order( $email = 'customer@example.com' ) {
		$order = wc_create_order();
		$order->set_billing_email( $email );
		$order->set_total( 30 );
		$order->save();
		return $order;
	}

	/**
	 * Runs create_invoice() for the order with the given phase.
	 *
	 * @param object   $api      Fake connector.
	 * @param WC_Order $order    Order.
	 * @param string   $phase    Phase setting.
	 * @param array    $settings Extra settings.
	 * @return array
	 */
	private function run_sync( $api, $order, $phase, $settings = array() ) {
		return ORDER::create_invoice(
			array_merge( array( 'doctype' => 'invoice', 'freeorder' => 'yes', 'invoice_phase' => $phase ), $settings ),
			$order->get_id(),
			'_test_invoice_id',
			'test',
			$api,
			true,
			'no',
			'Test'
		);
	}

	/**
	 * Notes of the order as plain strings.
	 *
	 * @param WC_Order $order Order.
	 * @return string[]
	 */
	private function notes( $order ) {
		return wp_list_pluck( wc_get_order_notes( array( 'order_id' => $order->get_id() ) ), 'content' );
	}

	/**
	 * The phase decides the approval; without a phase the legacy setting rules.
	 */
	public function test_phase_decides_approval() {
		$this->assertSame( '', ORDER::get_invoice_phase( array() ) );
		$this->assertSame( '', ORDER::get_invoice_phase( array( 'invoice_phase' => 'other' ) ) );
		$this->assertSame( 'draft', ORDER::get_invoice_phase( array( 'invoice_phase' => 'draft' ) ) );

		$this->assertFalse( ORDER::should_approve_document( array( 'invoice_phase' => 'draft', 'approve_document' => 'yes' ) ) );
		$this->assertTrue( ORDER::should_approve_document( array( 'invoice_phase' => 'auto', 'approve_document' => 'no' ) ) );
		$this->assertTrue( ORDER::should_approve_document( array( 'approve_document' => 'yes' ) ) );
		$this->assertFalse( ORDER::should_approve_document( array() ) );
	}

	/**
	 * The phase is what generate_order_data() sends as approveDoc.
	 */
	public function test_order_data_approve_flag_follows_phase() {
		$order = $this->make_order();
		$base  = array( 'doctype' => 'invoice' );

		$auto  = ORDER::generate_order_data( array_merge( $base, array( 'invoice_phase' => 'auto' ) ), $order, 'test' );
		$draft = ORDER::generate_order_data( array_merge( $base, array( 'invoice_phase' => 'draft', 'approve_document' => 'yes' ) ), $order, 'test' );

		$this->assertTrue( $auto['approveDoc'] );
		$this->assertFalse( $draft['approveDoc'] );
	}

	/**
	 * WooCommerce never attaches the PDF when a phase is set, whatever attach_pdf says.
	 */
	public function test_woocommerce_attaches_no_pdf_with_a_phase() {
		$orders = new Orders(
			array(
				'connector'   => 'test',
				'options'     => array(
					'slug'                       => 'conecom_test',
					'name'                       => 'Test',
					'order_send_attachments'     => true,
					'order_only_order_completed' => 'completed',
					'order_import_free_order'    => false,
				),
				'settings'    => array( 'ecstatus' => 'completed', 'invoice_phase' => 'auto', 'attach_pdf' => 'yes' ),
				'connapi_erp' => new Conecom_Test_Pdf_Connector(),
			)
		);
		$order  = $this->make_order();
		$order->update_meta_data( '_conecom_test_doc_id', 'abc' );
		$order->save();

		$this->assertSame( array(), $orders->attach_file_woocommerce_email( array(), 'customer_completed_order', $order->get_id() ) );
		$this->assertTrue( $orders->maybe_hold_completed_email( true, $order, null ) );
	}

	/**
	 * Phase 1 sends nothing to the customer from the connector.
	 */
	public function test_draft_phase_does_not_send() {
		$api                = new Conecom_Test_Phase_Connector();
		$api->create_result = $this->approved_result();
		$order              = $this->make_order();

		$this->run_sync( $api, $order, 'draft' );

		$this->assertSame( array(), $api->sent );
		$this->assertEmpty( wc_get_order( $order->get_id() )->get_meta( '_test_doc_sent' ) );
	}

	/**
	 * Phase 2 sends the approved invoice to the billing email, once.
	 */
	public function test_auto_phase_sends_approved_invoice_once() {
		$api                = new Conecom_Test_Phase_Connector();
		$api->create_result = $this->approved_result();
		$order              = $this->make_order( 'alex@example.com' );

		$this->run_sync( $api, $order, 'auto' );
		$this->run_sync( $api, wc_get_order( $order->get_id() ), 'auto' );

		$this->assertCount( 1, $api->sent );
		$this->assertSame( 'invoice', $api->sent[0][0] );
		$this->assertSame( 'doc1', $api->sent[0][1] );
		$this->assertSame( 'alex@example.com', $api->sent[0][2] );
		$this->assertStringContainsString( 'F-1', $api->sent[0][3] );
		$this->assertEquals( 1, wc_get_order( $order->get_id() )->get_meta( '_test_doc_sent' ) );
		$this->assertNotEmpty( preg_grep( '/Invoice sent to the customer/', $this->notes( $order ) ) );
	}

	/**
	 * A document that could not be approved is not sent: note and no sent flag.
	 */
	public function test_auto_phase_does_not_send_unapproved_document() {
		$api                = new Conecom_Test_Phase_Connector();
		$api->create_result = array_merge( $this->approved_result(), array( 'approved' => false, 'approve_msg' => 'could not be approved: boom' ) );
		$order              = $this->make_order();

		$this->run_sync( $api, $order, 'auto' );

		$this->assertSame( array(), $api->sent );
		$this->assertEmpty( wc_get_order( $order->get_id() )->get_meta( '_test_doc_sent' ) );
		$notes = implode( ' ', $this->notes( $order ) );
		$this->assertStringContainsString( 'Invoice NOT sent from Test', $notes );
		$this->assertStringContainsString( 'could not be approved: boom', $notes );
		$this->assertStringContainsString( 'only got the WooCommerce email', $notes );
	}

	/**
	 * A failed send leaves a note, no sent flag, and a retry sends it.
	 */
	public function test_auto_phase_send_failure_is_noted_and_retried() {
		$api                = new Conecom_Test_Phase_Connector();
		$api->create_result = $this->approved_result();
		$api->send_result   = array( 'status' => 'error', 'message' => 'smtp down' );
		$order              = $this->make_order();

		$this->run_sync( $api, $order, 'auto' );
		$this->assertEmpty( wc_get_order( $order->get_id() )->get_meta( '_test_doc_sent' ) );
		$this->assertStringContainsString( 'smtp down', implode( ' ', $this->notes( $order ) ) );

		$api->send_result = array( 'status' => 'ok', 'message' => 'sent' );
		$this->run_sync( $api, wc_get_order( $order->get_id() ), 'auto' );
		$this->assertCount( 2, $api->sent );
		$this->assertEquals( 1, wc_get_order( $order->get_id() )->get_meta( '_test_doc_sent' ) );
	}

	/**
	 * Invalid billing emails and connectors that cannot send are reported, not sent.
	 */
	public function test_auto_phase_reports_missing_email_and_missing_capability() {
		$api                = new Conecom_Test_Phase_Connector();
		$api->create_result = $this->approved_result();
		$no_email           = $this->make_order( '' );
		$this->run_sync( $api, $no_email, 'auto' );
		$this->assertSame( array(), $api->sent );
		$this->assertStringContainsString( 'no valid billing email', implode( ' ', $this->notes( $no_email ) ) );

		$order = $this->make_order();
		$this->run_sync( new Conecom_Test_Phase_Connector_No_Send(), $order, 'auto' );
		$this->assertStringContainsString( 'cannot send documents', implode( ' ', $this->notes( $order ) ) );
	}
}
