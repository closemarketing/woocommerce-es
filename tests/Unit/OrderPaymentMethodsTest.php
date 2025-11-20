<?php
/**
 * Tests for PAYMENTS payment method mapping.
 *
 * @package Connect_Ecommerce
 */

use CLOSE\ConnectEcommerce\Helpers\PAYMENTS;

/**
 * Class OrderPaymentMethodsTest.
 */
class OrderPaymentMethodsTest extends WP_UnitTestCase {

	/**
	 * Should return empty array when order has no payment method.
	 *
	 * @return void
	 */
	/**
	 * Should return empty array when order has no payment method.
	 *
	 * @return void
	 */
	public function test_returns_empty_array_when_no_payment_method(): void {
		$order = wc_create_order();
		$order->set_payment_method( '' );
		$order->save();

		$result = PAYMENTS::get_equivalent_payment_method( $order, array() );

		$this->assertSame( array(), $result );

		$order->delete( true );
	}

	/**
	 * Should return WooCommerce payment method when no mapping configured.
	 *
	 * @return void
	 */
	public function test_returns_wc_method_without_mapping(): void {
		$order = wc_create_order();
		$order->set_payment_method( 'cod' );
		$order->save();

		$result = PAYMENTS::get_equivalent_payment_method(
			$order,
			array(
				'payment_methods' => array(),
			)
		);

		$this->assertArrayHasKey( 'paymentMethod', $result );
		$this->assertSame( 'cod', $result['paymentMethod'] );
		$this->assertArrayNotHasKey( 'paymentMethodId', $result );

		$order->delete( true );
	}

	/**
	 * Should return mapped connector payment method.
	 *
	 * @return void
	 */
	public function test_returns_mapped_connector_method(): void {
		$order = wc_create_order();
		$order->set_payment_method( 'stripe' );
		$order->save();

		$result = PAYMENTS::get_equivalent_payment_method(
			$order,
			array(
				'payment_methods' => array(
					'stripe' => 'connector_stripe',
				),
			)
		);

		$this->assertArrayHasKey( 'paymentMethod', $result );
		$this->assertSame( 'stripe', $result['paymentMethod'] );
		$this->assertArrayHasKey( 'paymentMethodId', $result );
		$this->assertSame( 'connector_stripe', $result['paymentMethodId'] );

		$order->delete( true );
	}
}


