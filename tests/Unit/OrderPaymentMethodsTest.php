<?php
/**
 * Tests for ORDER payment method mapping.
 *
 * @package Connect_Ecommerce
 */

use CLOSE\ConnectEcommerce\Helpers\ORDER;

/**
 * Class OrderPaymentMethodsTest.
 */
class OrderPaymentMethodsTest extends WP_UnitTestCase {
	/**
	 * ReflectionMethod instance for the private helper.
	 *
	 * @var ReflectionMethod
	 */
	private $payment_method_reflection;

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->payment_method_reflection = new ReflectionMethod( ORDER::class, 'get_equivalent_payment_method' );
		$this->payment_method_reflection->setAccessible( true );
	}

	/**
	 * Tear down environment.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$this->payment_method_reflection = null;
		parent::tearDown();
	}

	/**
	 * Helper to invoke private payment method mapper.
	 *
	 * @param WC_Order $order Order instance.
	 * @param array    $settings Connector settings.
	 *
	 * @return array
	 */
	private function invoke_payment_method( WC_Order $order, array $settings ): array {
		return $this->payment_method_reflection->invoke( null, $order, $settings );
	}

	/**
	 * Should return empty array when order has no payment method.
	 *
	 * @return void
	 */
	public function test_returns_empty_array_when_no_payment_method(): void {
		$order = wc_create_order();
		$order->set_payment_method( '' );
		$order->save();

		$result = $this->invoke_payment_method( $order, array() );

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

		$result = $this->invoke_payment_method(
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

		$result = $this->invoke_payment_method(
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


