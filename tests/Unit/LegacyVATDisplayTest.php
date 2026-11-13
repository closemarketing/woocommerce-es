<?php
/**
 * Class LegacyVATDisplayTest
 *
 * Covers the VAT number fallback for orders whose VAT is stored under a
 * legacy meta key (e.g. migrated from another VAT plugin).
 *
 * Command: composer test -- --filter=LegacyVATDisplayTest
 *
 * @package Connect_Ecommerce
 */

use CLOSE\ConnectEcommerce\Frontend\Checkout;
use CLOSE\ConnectEcommerce\Helpers\ORDER;

/**
 * Legacy VAT display tests.
 */
class LegacyVATDisplayTest extends WP_UnitTestCase {
	/**
	 * Checkout instance.
	 *
	 * @var Checkout
	 */
	private $checkout;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		update_option(
			'connect_ecommerce_public',
			array(
				'vat_show'      => 'yes',
				'vat_mandatory' => 'no',
				'company_field' => 'yes',
			)
		);

		$this->checkout = new Checkout();
	}

	/**
	 * Creates an order with the given meta.
	 *
	 * @param array $meta Meta key => value.
	 * @return WC_Order
	 */
	private function create_order( $meta = array() ) {
		$order = wc_create_order();
		foreach ( $meta as $key => $value ) {
			$order->update_meta_data( $key, $value );
		}
		$order->save();
		return $order;
	}

	/**
	 * Legacy meta keys from third-party VAT plugins.
	 *
	 * @return array
	 */
	public function legacy_meta_keys_provider() {
		return array(
			'EU VAT Number < 2.3'  => array( '_vat_number' ),
			'EU VAT Number >= 2.3' => array( '_billing_vat_number' ),
			'EU VAT Compliance'    => array( 'VAT Number' ),
			'NIF field'            => array( '_billing_nif' ),
		);
	}

	/**
	 * ORDER::get_billing_vat() falls back to legacy meta keys.
	 *
	 * @dataProvider legacy_meta_keys_provider
	 *
	 * @param string $meta_key Legacy meta key.
	 * @return void
	 */
	public function test_get_billing_vat_reads_legacy_key( $meta_key ) {
		$order = $this->create_order( array( $meta_key => 'ESB12345678' ) );

		$this->assertSame( 'ESB12345678', ORDER::get_billing_vat( $order ) );

		$order->delete( true );
	}

	/**
	 * _billing_vat has priority over legacy keys.
	 *
	 * @return void
	 */
	public function test_get_billing_vat_prefers_billing_vat() {
		$order = $this->create_order(
			array(
				'_billing_vat' => 'ESB11111111',
				'_vat_number'  => 'ESB22222222',
			)
		);

		$this->assertSame( 'ESB11111111', ORDER::get_billing_vat( $order ) );

		$order->delete( true );
	}

	/**
	 * ORDER::get_billing_vat() returns an empty string for invalid input.
	 *
	 * @return void
	 */
	public function test_get_billing_vat_invalid_order() {
		$this->assertSame( '', ORDER::get_billing_vat( null ) );
		$this->assertSame( '', ORDER::get_billing_vat( false ) );
	}

	/**
	 * The conecom_vat_field_slugs filter allows custom meta keys.
	 *
	 * @return void
	 */
	public function test_vat_field_slugs_filter() {
		$order = $this->create_order( array( '_custom_tax_id' => 'ESB33333333' ) );

		$this->assertSame( '', ORDER::get_billing_vat( $order ) );

		$filter = function ( $slugs ) {
			$slugs[] = '_custom_tax_id';
			return $slugs;
		};
		add_filter( 'conecom_vat_field_slugs', $filter );

		$this->assertSame( 'ESB33333333', ORDER::get_billing_vat( $order ) );

		remove_filter( 'conecom_vat_field_slugs', $filter );
		$order->delete( true );
	}

	/**
	 * Admin billing fields show the legacy VAT number.
	 *
	 * @return void
	 */
	public function test_admin_billing_fields_use_legacy_vat() {
		$order  = $this->create_order( array( '_vat_number' => 'ESB44444444' ) );
		$fields = $this->checkout->add_billing_fields_admin( array(), $order );

		$this->assertArrayHasKey( 'vat', $fields );
		$this->assertSame( 'ESB44444444', $fields['vat']['value'] );

		$order->delete( true );
	}

	/**
	 * Admin billing fields leave the value unset when there is no VAT number.
	 *
	 * @return void
	 */
	public function test_admin_billing_fields_without_vat() {
		$order  = $this->create_order();
		$fields = $this->checkout->add_billing_fields_admin( array(), $order );

		$this->assertArrayHasKey( 'vat', $fields );
		$this->assertArrayNotHasKey( 'value', $fields['vat'] );

		$order->delete( true );
	}

	/**
	 * Admin billing fields work without an order.
	 *
	 * @return void
	 */
	public function test_admin_billing_fields_without_order() {
		$fields = $this->checkout->add_billing_fields_admin( array(), false );

		$this->assertArrayHasKey( 'vat', $fields );
		$this->assertArrayNotHasKey( 'value', $fields['vat'] );
	}

	/**
	 * Order emails show the legacy VAT number.
	 *
	 * @return void
	 */
	public function test_email_shows_legacy_vat() {
		$order = $this->create_order( array( 'VAT Number' => 'ES B55555555' ) );

		ob_start();
		$this->checkout->email_key_notification( $order, false, false );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'ES B55555555', $output );
		$this->assertStringContainsString( '<p>', $output );

		$order->delete( true );
	}

	/**
	 * Plain text emails print the VAT number without HTML.
	 *
	 * @return void
	 */
	public function test_email_plain_text() {
		$order = $this->create_order( array( '_billing_vat' => 'ESB66666666' ) );

		ob_start();
		$this->checkout->email_key_notification( $order, false, true );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'ESB66666666', $output );
		$this->assertStringNotContainsString( '<p>', $output );

		$order->delete( true );
	}

	/**
	 * Order emails print nothing when there is no VAT number.
	 *
	 * @return void
	 */
	public function test_email_without_vat() {
		$order = $this->create_order();

		ob_start();
		$this->checkout->email_key_notification( $order );
		$output = ob_get_clean();

		$this->assertSame( '', $output );

		$order->delete( true );
	}
}
