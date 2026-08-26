<?php
/**
 * Tests the connector API contract.
 *
 * @package Connect_Ecommerce
 */

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
		}
	}
}
