<?php
/**
 * Tests for PROD::filter_product() method.
 *
 * Command: composer test -- --filter=FilterProductTest
 *
 * @package Connect_Ecommerce
 */

use CLOSE\ConnectEcommerce\Helpers\PROD;

/**
 * Class FilterProductTest.
 *
 * Covers all branching conditions in PROD::filter_product():
 * - Merge var post_status filter
 * - SKU filter (fnmatch)
 * - Tag filter (no filter configured, no tags on product, matching, non-matching)
 */
class FilterProductTest extends WP_UnitTestCase {

	/**
	 * Base settings with no filters active.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Base product item with tags.
	 *
	 * @var array
	 */
	private $item_with_tags;

	/**
	 * Base product item without tags.
	 *
	 * @var array
	 */
	private $item_without_tags;

	public function setUp(): void {
		parent::setUp();

		delete_option( 'connect_ecommerce_prod_mergevars' );

		$this->settings = [
			'filter'     => '',
			'filter_sku' => '',
		];

		$this->item_with_tags = [
			'id'   => 'prod-001',
			'sku'  => 'SKU-001',
			'name' => 'Test Product',
			'tags' => [ 'rrats', 'Ymountain', '2023' ],
		];

		$this->item_without_tags = [
			'id'   => 'prod-002',
			'sku'  => 'SKU-002',
			'name' => 'Test Product No Tags',
			'tags' => [],
		];
	}

	public function tearDown(): void {
		delete_option( 'connect_ecommerce_prod_mergevars' );
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Tag filter: no filter configured
	// -------------------------------------------------------------------------

	/**
	 * No tag filter + product has tags → not filtered (import it).
	 */
	public function test_no_tag_filter_product_has_tags_returns_false() {
		$result = PROD::filter_product( $this->settings, $this->item_with_tags );
		$this->assertFalse( $result, 'Product should not be filtered when no tag filter is set' );
	}

	/**
	 * No tag filter + product has no tags → not filtered (import it).
	 */
	public function test_no_tag_filter_product_no_tags_returns_false() {
		$result = PROD::filter_product( $this->settings, $this->item_without_tags );
		$this->assertFalse( $result, 'Product should not be filtered when no tag filter is set and product has no tags' );
	}

	// -------------------------------------------------------------------------
	// Tag filter: filter configured, product has no tags
	// -------------------------------------------------------------------------

	/**
	 * Tag filter set + product has no tags → not filtered (no tags to exclude by).
	 */
	public function test_tag_filter_set_product_no_tags_returns_false() {
		$settings           = $this->settings;
		$settings['filter'] = 'rrats';

		$result = PROD::filter_product( $settings, $this->item_without_tags );
		$this->assertFalse( $result, 'Product with no tags should not be filtered even when a tag filter is configured' );
	}

	// -------------------------------------------------------------------------
	// Tag filter: matching tags
	// -------------------------------------------------------------------------

	/**
	 * Tag filter matches one product tag → not filtered (import it).
	 */
	public function test_tag_filter_matches_single_tag_returns_false() {
		$settings           = $this->settings;
		$settings['filter'] = 'rrats';

		$result = PROD::filter_product( $settings, $this->item_with_tags );
		$this->assertFalse( $result, 'Product whose tag matches the filter should not be filtered' );
	}

	/**
	 * Tag filter matches one of several configured tags → not filtered.
	 */
	public function test_tag_filter_multiple_options_one_matches_returns_false() {
		$settings           = $this->settings;
		$settings['filter'] = 'other, rrats, another';

		$result = PROD::filter_product( $settings, $this->item_with_tags );
		$this->assertFalse( $result, 'Product should not be filtered when at least one tag matches the filter list' );
	}

	/**
	 * Tag filter matches all configured tags (exact list) → not filtered.
	 */
	public function test_tag_filter_exact_match_returns_false() {
		$settings           = $this->settings;
		$settings['filter'] = 'rrats, Ymountain, 2023';

		$result = PROD::filter_product( $settings, $this->item_with_tags );
		$this->assertFalse( $result, 'Product should not be filtered when all tags match the filter list' );
	}

	// -------------------------------------------------------------------------
	// Tag filter: non-matching tags
	// -------------------------------------------------------------------------

	/**
	 * Tag filter set + no product tag matches → filtered (skip it).
	 */
	public function test_tag_filter_no_match_returns_true() {
		$settings           = $this->settings;
		$settings['filter'] = 'unrelated-tag';

		$result = PROD::filter_product( $settings, $this->item_with_tags );
		$this->assertTrue( $result, 'Product should be filtered when none of its tags match the filter' );
	}

	/**
	 * Tag filter with whitespace around values still matches correctly.
	 */
	public function test_tag_filter_whitespace_trimmed_matches() {
		$settings           = $this->settings;
		$settings['filter'] = '  rrats  ,  Ymountain  ';

		$result = PROD::filter_product( $settings, $this->item_with_tags );
		$this->assertFalse( $result, 'Tag filter should trim whitespace before comparing' );
	}

	// -------------------------------------------------------------------------
	// SKU filter
	// -------------------------------------------------------------------------

	/**
	 * SKU filter matches → not filtered (import it).
	 */
	public function test_sku_filter_match_returns_false() {
		$settings               = $this->settings;
		$settings['filter_sku'] = 'SKU-*';

		$result = PROD::filter_product( $settings, $this->item_with_tags );
		$this->assertFalse( $result, 'Product whose SKU matches the filter pattern should not be filtered' );
	}

	/**
	 * SKU filter does not match → filtered (skip it).
	 */
	public function test_sku_filter_no_match_returns_true() {
		$settings               = $this->settings;
		$settings['filter_sku'] = 'OTHER-*';

		$result = PROD::filter_product( $settings, $this->item_with_tags );
		$this->assertTrue( $result, 'Product whose SKU does not match the filter pattern should be filtered' );
	}

	/**
	 * SKU filter set but product has no SKU → falls through to tag filter.
	 */
	public function test_sku_filter_set_no_sku_on_product_falls_through() {
		$settings               = $this->settings;
		$settings['filter_sku'] = 'SKU-*';

		$item_no_sku        = $this->item_with_tags;
		$item_no_sku['sku'] = '';

		// No tag filter either, so should not be filtered.
		$result = PROD::filter_product( $settings, $item_no_sku );
		$this->assertFalse( $result, 'SKU filter should be skipped when product has no SKU, falling through to tag filter' );
	}

	// -------------------------------------------------------------------------
	// Merge var post_status filter
	// -------------------------------------------------------------------------

	/**
	 * Merge var maps a field to prod|post_status and the field is empty → filtered.
	 */
	public function test_mergevar_post_status_empty_returns_true() {
		update_option(
			'connect_ecommerce_prod_mergevars',
			[
				'prod_mergevars' => [
					'forSale' => 'prod|post_status',
				],
			]
		);

		$item              = $this->item_with_tags;
		$item['forSale']   = '';

		$result = PROD::filter_product( $this->settings, $item );
		$this->assertTrue( $result, 'Product should be filtered when the post_status merge var field is empty' );
	}

	/**
	 * Merge var maps a field to prod|post_status and the field is non-empty → not filtered by this rule.
	 */
	public function test_mergevar_post_status_non_empty_returns_false() {
		update_option(
			'connect_ecommerce_prod_mergevars',
			[
				'prod_mergevars' => [
					'forSale' => 'prod|post_status',
				],
			]
		);

		$item            = $this->item_with_tags;
		$item['forSale'] = 'publish';

		$result = PROD::filter_product( $this->settings, $item );
		$this->assertFalse( $result, 'Product should not be filtered when the post_status merge var field is non-empty' );
	}

	/**
	 * No prod|post_status key in merge vars → merge var filter is skipped entirely.
	 */
	public function test_mergevar_no_post_status_key_skips_filter() {
		update_option(
			'connect_ecommerce_prod_mergevars',
			[
				'prod_mergevars' => [
					'factoryCode' => 'cf|my_custom_field',
				],
			]
		);

		$result = PROD::filter_product( $this->settings, $this->item_with_tags );
		$this->assertFalse( $result, 'Merge var filter should be skipped when no prod|post_status mapping exists' );
	}
}
