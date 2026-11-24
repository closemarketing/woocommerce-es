<?php
/**
 * Legacy VAT reading: user meta helper and Store API billing address extraction.
 *
 * Command: composer test-debug -- --filter=LegacyVatTest
 *
 * @package Connect_Ecommerce
 */

use CLOSE\ConnectEcommerce\Frontend\Checkout;
use CLOSE\ConnectEcommerce\Helpers\ORDER;

/**
 * Tests for legacy VAT handling.
 */
class LegacyVatTest extends WP_UnitTestCase {

	/**
	 * Current key wins over legacy ones.
	 */
	public function test_get_user_vat_prefers_current_key() {
		$user_id = self::factory()->user->create();
		update_user_meta( $user_id, 'billing_nif', 'LEGACY1' );
		update_user_meta( $user_id, 'billing_vat', 'ES111' );

		$this->assertSame( 'ES111', ORDER::get_user_vat( $user_id ) );
	}

	/**
	 * Legacy keys are read, with or without the underscore prefix.
	 *
	 * @dataProvider legacy_keys
	 *
	 * @param string $key Meta key.
	 */
	public function test_get_user_vat_reads_legacy_keys( $key ) {
		$user_id = self::factory()->user->create();
		update_user_meta( $user_id, $key, 'B12345678' );

		$this->assertSame( 'B12345678', ORDER::get_user_vat( $user_id ) );
	}

	/**
	 * Keys used by previous VAT plugins.
	 *
	 * @return array
	 */
	public function legacy_keys() {
		return array(
			'billing_nif'  => array( 'billing_nif' ),
			'_billing_nif' => array( '_billing_nif' ),
			'billing_cif'  => array( 'billing_cif' ),
			'vat_number'   => array( 'vat_number' ),
			'_vat_number'  => array( '_vat_number' ),
			'VAT Number'   => array( 'VAT Number' ),
		);
	}

	/**
	 * Reading never deletes or migrates legacy data.
	 */
	public function test_get_user_vat_does_not_modify_meta() {
		$user_id = self::factory()->user->create();
		update_user_meta( $user_id, 'billing_nif', 'B12345678' );

		ORDER::get_user_vat( $user_id );

		$this->assertSame( 'B12345678', get_user_meta( $user_id, 'billing_nif', true ) );
		$this->assertSame( '', get_user_meta( $user_id, 'billing_vat', true ) );
	}

	/**
	 * Empty for invalid user or no data.
	 */
	public function test_get_user_vat_empty() {
		$this->assertSame( '', ORDER::get_user_vat( 0 ) );
		$this->assertSame( '', ORDER::get_user_vat( self::factory()->user->create() ) );
	}

	/**
	 * Store API billing address extraction.
	 *
	 * @dataProvider billing_addresses
	 *
	 * @param array  $address  Billing address.
	 * @param string $expected Expected VAT.
	 */
	public function test_get_vat_from_billing_address( $address, $expected ) {
		$checkout = new Checkout( array() );
		$this->assertSame( $expected, $checkout->get_vat_from_billing_address( $address ) );
	}

	/**
	 * Billing address cases.
	 *
	 * @return array
	 */
	public function billing_addresses() {
		return array(
			'namespaced blocks field' => array( array( 'connect_ecommerce/billing_vat' => 'ES123' ), 'ES123' ),
			'billing_vat'             => array( array( 'billing_vat' => 'ES123' ), 'ES123' ),
			'vat'                     => array( array( 'vat' => 'ES123' ), 'ES123' ),
			'billing_nif'             => array( array( 'billing_nif' => 'B999' ), 'B999' ),
			'empty'                   => array( array( 'billing_vat' => '  ' ), '' ),
			'none'                    => array( array( 'country' => 'ES' ), '' ),
		);
	}

	/**
	 * Non-array input is safe.
	 */
	public function test_get_vat_from_billing_address_non_array() {
		$checkout = new Checkout( array() );
		$this->assertSame( '', $checkout->get_vat_from_billing_address( null ) );
	}

	/**
	 * Sets the VAT priority setting.
	 *
	 * @param string $priority billing|shipping.
	 */
	private function set_priority( $priority ) {
		update_option( 'connect_ecommerce_public', array( 'vat_priority' => $priority ) );
	}

	/**
	 * Default priority is billing.
	 */
	public function test_priority_defaults_to_billing() {
		delete_option( 'connect_ecommerce_public' );
		$this->assertSame( 'billing', ORDER::get_vat_priority() );

		$this->set_priority( 'garbage' );
		$this->assertSame( 'billing', ORDER::get_vat_priority() );
	}

	/**
	 * Store API: billing wins by default, shipping wins when configured, billing is the fallback.
	 */
	public function test_store_api_priority_and_fallback() {
		$checkout = new Checkout( array() );
		$billing  = array( 'connect_ecommerce/billing_vat' => 'BILL1' );
		$shipping = array( 'connect_ecommerce/billing_vat' => 'SHIP1' );

		$this->set_priority( 'billing' );
		$this->assertSame( 'BILL1', $checkout->get_vat_from_billing_address( $billing, $shipping ) );

		$this->set_priority( 'shipping' );
		$this->assertSame( 'SHIP1', $checkout->get_vat_from_billing_address( $billing, $shipping ) );
		$this->assertSame( 'BILL1', $checkout->get_vat_from_billing_address( $billing, array() ) );

		$this->set_priority( 'billing' );
		$this->assertSame( 'SHIP1', $checkout->get_vat_from_billing_address( array(), $shipping ) );
	}

	/**
	 * Order meta: priority and fallback.
	 */
	public function test_order_priority_and_fallback() {
		$order = new WC_Order();
		$order->update_meta_data( '_wc_billing/connect_ecommerce/billing_vat', 'BILL1' );
		$order->update_meta_data( '_wc_shipping/connect_ecommerce/billing_vat', 'SHIP1' );

		$this->set_priority( 'billing' );
		$this->assertSame( 'BILL1', ORDER::get_billing_vat( $order ) );

		$this->set_priority( 'shipping' );
		$this->assertSame( 'SHIP1', ORDER::get_billing_vat( $order ) );

		$order->delete_meta_data( '_wc_shipping/connect_ecommerce/billing_vat' );
		$this->assertSame( 'BILL1', ORDER::get_billing_vat( $order ) );
	}

	/**
	 * Order meta saved by other VAT plugins, with and without underscore prefix.
	 *
	 * @dataProvider legacy_order_keys
	 *
	 * @param string $key Meta key.
	 */
	public function test_order_reads_legacy_meta_keys( $key ) {
		$order = new WC_Order();
		$order->update_meta_data( $key, 'ES-LEGACY' );

		$this->assertSame( 'ES-LEGACY', ORDER::get_billing_vat( $order ) );
	}

	/**
	 * Order meta keys used by previous VAT plugins.
	 *
	 * @return array
	 */
	public function legacy_order_keys() {
		return array(
			'_billing_vat'        => array( '_billing_vat' ),
			'_billing_nif'        => array( '_billing_nif' ),
			'_billing_vat_number' => array( '_billing_vat_number' ),
			'_vat_number'         => array( '_vat_number' ),
			'billing_vat'         => array( 'billing_vat' ),
			'billing_cif'         => array( 'billing_cif' ),
			'blocks billing'      => array( '_wc_billing/connect_ecommerce/billing_vat' ),
			'VAT Number'          => array( 'VAT Number' ),
		);
	}

	/**
	 * Excluding Blocks keys returns only classic/legacy values.
	 */
	public function test_exclude_blocks_returns_classic_only() {
		$order = new WC_Order();
		$order->update_meta_data( '_wc_billing/connect_ecommerce/billing_vat', 'BLOCKS' );
		$this->assertSame( 'BLOCKS', ORDER::get_billing_vat( $order ) );
		$this->assertSame( '', ORDER::get_billing_vat( $order, true ) );
		$this->assertTrue( ORDER::has_blocks_vat( $order ) );

		$order->update_meta_data( '_billing_nif', 'CLASSIC' );
		$this->assertSame( 'CLASSIC', ORDER::get_billing_vat( $order, true ) );
	}

	/**
	 * Admin billing field: only one when values match, both (distinct labels) when they differ.
	 */
	public function test_admin_field_same_or_different_value() {
		$checkout = new Checkout( array() );
		$order    = new WC_Order();

		// No Blocks field: our field as usual.
		$fields = $checkout->add_billing_fields_admin( array(), $order );
		$plain  = $fields['vat']['label'];
		$this->assertArrayHasKey( 'vat', $fields );

		// Blocks only: ours is hidden.
		$order->update_meta_data( '_wc_billing/connect_ecommerce/billing_vat', 'ES-1' );
		$this->assertArrayNotHasKey( 'vat', $checkout->add_billing_fields_admin( array(), $order ) );

		// Same value in classic meta: still only one.
		$order->update_meta_data( '_billing_nif', 'ES-1' );
		$this->assertArrayNotHasKey( 'vat', $checkout->add_billing_fields_admin( array(), $order ) );

		// Different value: both, ours with a distinct label.
		$order->update_meta_data( '_billing_nif', 'ES-2' );
		$fields = $checkout->add_billing_fields_admin( array(), $order );
		$this->assertArrayHasKey( 'vat', $fields );
		$this->assertSame( 'ES-2', $fields['vat']['value'] );
		$this->assertNotSame( $plain, $fields['vat']['label'] );
	}

	/**
	 * Classic checkout: shipping_vat field is added when VAT is shown, optional.
	 */
	public function test_classic_shipping_vat_field_added() {
		update_option( 'connect_ecommerce_public', array( 'vat_show' => 'yes', 'vat_mandatory' => 'yes' ) );
		$checkout = new Checkout( array() );

		$fields = $checkout->add_shipping_fields( array( 'shipping_company' => array(), 'shipping_city' => array() ) );
		$this->assertArrayHasKey( 'shipping_vat', $fields );
		$this->assertFalse( $fields['shipping_vat']['required'] );

		// Also added when shipping_company does not exist.
		$this->assertArrayHasKey( 'shipping_vat', $checkout->add_shipping_fields( array( 'shipping_city' => array() ) ) );

		update_option( 'connect_ecommerce_public', array( 'vat_show' => 'no' ) );
		$checkout = new Checkout( array() );
		$this->assertArrayNotHasKey( 'shipping_vat', $checkout->add_shipping_fields( array( 'shipping_company' => array() ) ) );
	}

	/**
	 * Classic checkout: VAT and country come from the same address, by priority.
	 */
	public function test_classic_vat_and_country_follow_priority() {
		$checkout = new Checkout( array() );
		$data     = array(
			'billing_vat'      => 'ESB1',
			'billing_country'  => 'ES',
			'shipping_vat'     => 'FR12345678901',
			'shipping_country' => 'FR',
		);

		$this->set_priority( 'billing' );
		$this->assertSame( array( 'vat' => 'ESB1', 'country' => 'ES' ), $checkout->get_vat_and_country_from_classic_data( $data ) );

		$this->set_priority( 'shipping' );
		$this->assertSame( array( 'vat' => 'FR12345678901', 'country' => 'FR' ), $checkout->get_vat_and_country_from_classic_data( $data ) );

		// Shipping priority but no shipping VAT: billing fallback with billing country.
		unset( $data['shipping_vat'] );
		$this->assertSame( array( 'vat' => 'ESB1', 'country' => 'ES' ), $checkout->get_vat_and_country_from_classic_data( $data ) );

		// Billing priority but only shipping VAT: uses it with the shipping country.
		$this->set_priority( 'billing' );
		$this->assertSame( array( 'vat' => 'FR9', 'country' => 'FR' ), $checkout->get_vat_and_country_from_classic_data( array( 'shipping_vat' => 'FR9', 'shipping_country' => 'FR', 'billing_country' => 'ES' ) ) );
	}

	/**
	 * Store API: country is the one of the address the VAT comes from.
	 */
	public function test_store_api_country_follows_vat_source() {
		$checkout = new Checkout( array() );
		$billing  = array( 'country' => 'ES', 'connect_ecommerce/billing_vat' => 'ESB1' );
		$shipping = array( 'country' => 'FR', 'connect_ecommerce/billing_vat' => 'FR1' );

		$this->set_priority( 'billing' );
		$this->assertSame( 'ES', $checkout->get_vat_and_country_from_addresses( $billing, $shipping )['country'] );

		$this->set_priority( 'shipping' );
		$this->assertSame( 'FR', $checkout->get_vat_and_country_from_addresses( $billing, $shipping )['country'] );

		// Only shipping has VAT, billing priority: shipping country.
		$this->set_priority( 'billing' );
		$this->assertSame( 'FR', $checkout->get_vat_and_country_from_addresses( array( 'country' => 'ES' ), $shipping )['country'] );
	}

	/**
	 * Classic shipping VAT in order meta is a fallback and is not shown in the billing admin field.
	 */
	public function test_order_shipping_vat_meta() {
		$order = new WC_Order();
		$order->update_meta_data( '_shipping_vat', 'SHIP-CLASSIC' );

		$this->set_priority( 'billing' );
		$this->assertSame( 'SHIP-CLASSIC', ORDER::get_billing_vat( $order ) );
		$this->assertSame( '', ORDER::get_billing_vat( $order, true ) );

		$order->update_meta_data( '_billing_vat', 'BILL-CLASSIC' );
		$this->set_priority( 'shipping' );
		$this->assertSame( 'SHIP-CLASSIC', ORDER::get_billing_vat( $order ) );
		$this->set_priority( 'billing' );
		$this->assertSame( 'BILL-CLASSIC', ORDER::get_billing_vat( $order ) );
	}

	/**
	 * Blocks checkout default for the VAT field uses legacy user meta, read-only.
	 */
	public function test_blocks_default_prefilled_from_legacy_user_meta() {
		update_option( 'connect_ecommerce_public', array( 'vat_show' => 'yes' ) );
		$my_account = new \CLOSE\ConnectEcommerce\Frontend\MyAccount( array( 'all_options' => array() ) );

		$user_id = self::factory()->user->create();
		update_user_meta( $user_id, 'billing_nif', 'ES-LEGACY1' );
		update_user_meta( $user_id, 'shipping_vat', 'ES-SHIP1' );
		$customer = new WC_Customer( $user_id );

		$this->assertSame( 'ES-LEGACY1', $my_account->prefill_vat_blocks( null, 'billing', $customer ) );
		$this->assertSame( 'ES-SHIP1', $my_account->prefill_vat_blocks( null, 'shipping', $customer ) );
		$this->assertNull( $my_account->prefill_vat_blocks( null, 'other', $customer ) );
		// Orders and guests are left alone.
		$this->assertNull( $my_account->prefill_vat_blocks( null, 'billing', new WC_Order() ) );
		$this->assertSame( 'KEEP', $my_account->prefill_vat_blocks( 'KEEP', 'billing', $customer ) );
		// Nothing migrated.
		$this->assertSame( '', get_user_meta( $user_id, 'billing_vat', true ) );
	}
}
