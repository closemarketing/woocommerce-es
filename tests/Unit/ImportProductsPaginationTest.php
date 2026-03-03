<?php
/**
 * Class ImportProductsPaginationTest
 *
 * Tests for product import pagination end detection
 *
 * Command: composer test -- --filter=ImportProductsPaginationTest
 *
 * @package Connect_Ecommerce
 */

use CLOSE\ConnectEcommerce\Admin\Import_Products;

/**
 * Test Import Products Pagination
 *
 * @group woocommerce
 * @group import
 */
class ImportProductsPaginationTest extends WP_UnitTestCase {

	/**
	 * Test pagination finish detection with 102 products and 100 per page.
	 *
	 * This test verifies that the importer correctly detects the end
	 * of products when there are more products than one page but less
	 * than two full pages (e.g., 102 products with 100 per page).
	 *
	 * @see https://github.com/closemarketing/woocommerce-es/issues/107
	 */
	public function test_pagination_finish_detection_with_partial_last_page() {
		$api_pagination = 100;
		$total_products = 102;

		// Test Page 1 (products 0-99).
		for ( $sync_loop = 0; $sync_loop < 100; $sync_loop++ ) {
			$products_count = 100; // Full page.
			$finish         = Import_Products::should_finish_import( $sync_loop, $products_count, $api_pagination );

			$this->assertFalse(
				$finish,
				sprintf(
					'Should NOT finish on sync_loop=%d (page 1, product %d/%d)',
					$sync_loop,
					$sync_loop + 1,
					$products_count
				)
			);
		}

		// Test Page 2 (products 100-101).
		for ( $sync_loop = 100; $sync_loop < 102; $sync_loop++ ) {
			$products_count = 2; // Partial page.
			$finish         = Import_Products::should_finish_import( $sync_loop, $products_count, $api_pagination );

			if ( 101 === $sync_loop ) {
				// Last product - should finish.
				$this->assertTrue(
					$finish,
					sprintf(
						'Should FINISH on sync_loop=%d (page 2, last product)',
						$sync_loop
					)
				);
			} else {
				// First product of page 2 - should not finish yet.
				$this->assertFalse(
					$finish,
					sprintf(
						'Should NOT finish on sync_loop=%d (page 2, product %d/%d)',
						$sync_loop,
						$sync_loop - 99,
						$products_count
					)
				);
			}
		}
	}

	/**
	 * Test pagination finish detection with exactly 100 products and 100 per page.
	 *
	 * Edge case: when products exactly match pagination size.
	 */
	public function test_pagination_finish_detection_with_exact_page() {
		$api_pagination = 100;
		$total_products = 100;

		// Test all products in page 1.
		for ( $sync_loop = 0; $sync_loop < 100; $sync_loop++ ) {
			$products_count = 100; // Full page.
			$finish         = Import_Products::should_finish_import( $sync_loop, $products_count, $api_pagination );

			$this->assertFalse(
				$finish,
				sprintf(
					'Should NOT finish on sync_loop=%d when page is full (product %d/%d)',
					$sync_loop,
					$sync_loop + 1,
					$products_count
				)
			);
		}

		// Test Page 2 (empty).
		$sync_loop      = 100;
		$products_count = 0; // Empty page.
		$finish         = Import_Products::should_finish_import( $sync_loop, $products_count, $api_pagination );

		$this->assertFalse(
			$finish,
			'Empty page (0 products) should not match finish condition - API should return empty array'
		);
	}

	/**
	 * Test pagination finish detection with 205 products and 100 per page.
	 *
	 * Edge case: multiple pages with partial last page.
	 */
	public function test_pagination_finish_detection_with_multiple_pages() {
		$api_pagination = 100;

		// Test Page 1 (products 0-99) - should not finish.
		$sync_loop      = 50; // Sample product in page 1.
		$products_count = 100;
		$finish         = Import_Products::should_finish_import( $sync_loop, $products_count, $api_pagination );

		$this->assertFalse( $finish, 'Should NOT finish on page 1' );

		// Test Page 2 (products 100-199) - should not finish.
		$sync_loop      = 150; // Sample product in page 2.
		$products_count = 100;
		$finish         = Import_Products::should_finish_import( $sync_loop, $products_count, $api_pagination );

		$this->assertFalse( $finish, 'Should NOT finish on page 2' );

		// Test Page 3 (products 200-204) - should finish on last product.
		$sync_loop      = 204; // Last product.
		$products_count = 5; // Partial page.
		$finish         = Import_Products::should_finish_import( $sync_loop, $products_count, $api_pagination );

		$this->assertTrue( $finish, 'Should FINISH on last product of page 3' );

		// Test Page 3 - should not finish on previous products.
		$sync_loop      = 203; // Second-to-last product.
		$products_count = 5;
		$finish         = Import_Products::should_finish_import( $sync_loop, $products_count, $api_pagination );

		$this->assertFalse( $finish, 'Should NOT finish before last product on page 3' );
	}

	/**
	 * Test pagination finish detection with 1 product and 100 per page.
	 *
	 * Edge case: very small product count.
	 */
	public function test_pagination_finish_detection_with_single_product() {
		$api_pagination = 100;

		// First product (also last).
		$sync_loop      = 0;
		$products_count = 1;
		$finish         = Import_Products::should_finish_import( $sync_loop, $products_count, $api_pagination );

		$this->assertTrue( $finish, 'Should FINISH immediately when there is only 1 product' );
	}

	/**
	 * Test pagination finish detection with 50 products and 100 per page.
	 *
	 * Edge case: products less than one page.
	 */
	public function test_pagination_finish_detection_with_less_than_one_page() {
		$api_pagination = 100;
		$total_products = 50;

		// Test first 49 products - should not finish.
		for ( $sync_loop = 0; $sync_loop < 49; $sync_loop++ ) {
			$products_count = 50;
			$finish         = Import_Products::should_finish_import( $sync_loop, $products_count, $api_pagination );

			$this->assertFalse(
				$finish,
				sprintf(
					'Should NOT finish on sync_loop=%d (product %d/%d)',
					$sync_loop,
					$sync_loop + 1,
					$products_count
				)
			);
		}

		// Test last product - should finish.
		$sync_loop      = 49;
		$products_count = 50;
		$finish         = Import_Products::should_finish_import( $sync_loop, $products_count, $api_pagination );

		$this->assertTrue(
			$finish,
			sprintf(
				'Should FINISH on sync_loop=%d (last product %d/%d)',
				$sync_loop,
				$sync_loop + 1,
				$products_count
			)
		);
	}

	/**
	 * Test non-paginated import finish detection.
	 *
	 * When api_pagination is false/not set, test the original logic.
	 */
	public function test_non_paginated_import_finish_detection() {
		$total_products = 150;

		// Test products 0-148 - should not finish.
		for ( $sync_loop = 0; $sync_loop < 149; $sync_loop++ ) {
			$products_count = $total_products;
			$finish         = Import_Products::should_finish_import( $sync_loop, $products_count, false );

			$this->assertFalse(
				$finish,
				sprintf(
					'Should NOT finish on sync_loop=%d (product %d/%d)',
					$sync_loop,
					$sync_loop + 1,
					$products_count
				)
			);
		}

		// Test last product - should finish.
		$sync_loop      = 149;
		$products_count = $total_products;
		$finish         = Import_Products::should_finish_import( $sync_loop, $products_count, false );

		$this->assertTrue(
			$finish,
			sprintf(
				'Should FINISH on sync_loop=%d (last product %d/%d)',
				$sync_loop,
				$sync_loop + 1,
				$products_count
			)
		);
	}

	/**
	 * Test special case with sync_loop = -1 (single product import).
	 *
	 * When importing a single specific product, sync_loop is set to -1.
	 */
	public function test_single_product_import_with_negative_loop() {
		$sync_loop      = -1;
		$products_count = 1;
		$api_pagination = 100;

		$finish = Import_Products::should_finish_import( $sync_loop, $products_count, $api_pagination );

		$this->assertTrue(
			$finish,
			'Should FINISH immediately when sync_loop is -1 (single product import)'
		);
	}
}

