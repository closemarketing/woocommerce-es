<?php
/**
 * Brand mapping tests.
 *
 * @package Connect_Ecommerce
 */

use CLOSE\ConnectEcommerce\Helpers\TAX;

/**
 * Verifies ERP attributes can populate WooCommerce product brands.
 */
class BrandMappingTest extends WP_UnitTestCase {
	/**
	 * The selected ERP attribute is assigned to product_brand.
	 */
	public function test_selected_attribute_is_assigned_as_product_brand() {
		if ( ! taxonomy_exists( 'product_brand' ) ) {
			register_taxonomy( 'product_brand', 'product' );
		}

		$product_id = self::factory()->post->create(
			array(
				'post_type' => 'product',
			)
		);

		TAX::assign_product_brands(
			array(
				array(
					'id'    => 'brand',
					'name'  => 'Brand',
					'value' => 'Runize',
				),
			),
			array( 'catattr_brand' => 'brand' ),
			$product_id
		);

		$brands = wp_get_object_terms( $product_id, 'product_brand', array( 'fields' => 'names' ) );
		$this->assertSame( array( 'Runize' ), $brands );

		wp_delete_post( $product_id, true );
	}
}
