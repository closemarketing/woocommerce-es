<?php
/**
 * Tests the connector API contract.
 *
 * @package Connect_Ecommerce
 */

use CLOSE\ConnectEcommerce\Connector\CONECOM_Abstract_Connector_API;

class ConnectorApiContractTest extends WP_UnitTestCase {
	/**
	 * Native connectors inherit the safe optional API defaults.
	 */
	public function test_native_connectors_inherit_the_optional_api_methods() {
		$clientify = new Connect_Ecommerce_Clientify(
			array(
				'clientify' => array(),
			)
		);
		$brevo     = new Connect_Ecommerce_Brevo(
			array(
				'brevo' => array(),
			)
		);

		foreach ( array( $clientify, $brevo ) as $connector ) {
			$this->assertInstanceOf( CONECOM_Abstract_Connector_API::class, $connector );
			$this->assertSame( '', $connector->get_attributes() );
			$this->assertSame( '', $connector->get_image_product( array(), 'remote-product', 123 ) );
			$this->assertSame( '', $connector->get_url_link_api() );
			$this->assertFalse( $connector->supports_capability( 'get_all_product_skus' ) );
		}
	}

	/**
	 * The contract exposes safe defaults for every optional core capability.
	 */
	public function test_contract_has_safe_defaults_for_optional_capabilities() {
		$connector = new class() extends CONECOM_Abstract_Connector_API {};

		$this->assertSame( array(), $connector->get_products() );
		$this->assertSame( array(), $connector->get_products_ids_since( '2026-01-01' ) );
		$this->assertFalse( $connector->get_products_stock() );
		$this->assertSame( array(), $connector->get_payment_methods() );
		$this->assertSame( array(), $connector->get_rates() );
		$this->assertSame( array(), $connector->get_taxes() );
		$this->assertSame( array(), $connector->get_companies() );
		$this->assertSame( array(), $connector->get_series_number( 'invoice' ) );
		$this->assertSame( array(), $connector->get_product_attributes() );
		$this->assertSame( '', $connector->get_order_pdf() );
		$this->assertFalse( $connector->has_product_updated() );
		$this->assertSame( 'error', $connector->check_can_sync()['status'] );
		$this->assertSame( 'error', $connector->get_product_by_sku( 'missing' )['status'] );
		$this->assertSame( 'error', $connector->get_all_product_skus()['status'] );
		$this->assertSame( 'error', $connector->create_order( array() )['status'] );
	}
}
