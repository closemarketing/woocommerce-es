<?php
/**
 * Sync Products
 *
 * @package    WordPress
 * @author     David Perez <david@close.technology>
 * @copyright  2023 Closemarketing
 * @version    1.0
 */

namespace CLOSE\ConnectEcommerce\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Sync Products.
 *
 * @since 1.0.0
 */
class TAX {
	/**
	 * Create global attributes in WooCommerce
	 *
	 * @param array   $attributes Attributes array.
	 * @param boolean $for_variation Is for variation.
	 * @return array
	 */
	public static function make_attributes( $attributes, $for_variation = true ) {
		$position          = 0;
		$attributes_return = array();
		foreach ( $attributes as $attr_name => $attr_values ) {
			$attribute = new \WC_Product_Attribute();
			$attribute->set_id( 0 );
			$attribute->set_position( $position );
			$attribute->set_visible( true );
			$attribute->set_variation( $for_variation );

			$attribute_labels = wp_list_pluck( wc_get_attribute_taxonomies(), 'attribute_label', 'attribute_name' );
			$attribute_name   = array_search( $attr_name, $attribute_labels, true );

			if ( ! $attribute_name ) {
				$attribute_name = wc_sanitize_taxonomy_name( $attr_name );
			}

			$attribute_id = wc_attribute_taxonomy_id_by_name( $attribute_name );

			if ( ! $attribute_id ) {
				$attribute_id = self::create_global_attribute( $attr_name );
			}
			if ( is_wp_error( $attribute_id ) ) {
				continue;
			}

			$slug          = wc_sanitize_taxonomy_name( $attr_name );
			$taxonomy_name = wc_attribute_taxonomy_name( $slug );

			$attribute->set_name( $taxonomy_name );
			$attribute->set_id( $attribute_id );
			$attribute->set_options( $attr_values );

			$attributes_return[] = $attribute;
			$position++;
		}
		return $attributes_return;
	}

	/**
	 * Create a new global attribute.
	 *
	 * @param string $raw_name Attribute name (label).
	 * @return int Attribute ID.
	 */
	private static function create_global_attribute( $raw_name ) {
		$slug = wc_sanitize_taxonomy_name( $raw_name );

		$attribute_id = wc_create_attribute(
			array(
				'name'         => $raw_name,
				'slug'         => $slug,
				'type'         => 'select',
				'order_by'     => 'menu_order',
				'has_archives' => false,
			)
		);

		$taxonomy_name = wc_attribute_taxonomy_name( $slug );
		register_taxonomy(
			$taxonomy_name,
			apply_filters( 'woocommerce_taxonomy_objects_' . $taxonomy_name, array( 'product' ) ),
			apply_filters(
				'woocommerce_taxonomy_args_' . $taxonomy_name,
				array(
					'labels'       => array(
						'name' => $raw_name,
					),
					'hierarchical' => true,
					'show_ui'      => false,
					'query_var'    => true,
					'rewrite'      => false,
				)
			)
		);

		delete_transient( 'wc_attribute_taxonomies' );

		return $attribute_id;
	}

	/**
	 * Set Terms Taxonomy
	 *
	 * @param string       $taxonomy Taxonomy name.
	 * @param array|string $terms Terms to set.
	 * @param int          $post_id Post id.
	 *
	 * @return void
	 */
	public static function set_terms_taxonomy( $settings, $taxonomy, $terms, $post_id ) {
		$categories_name = self::split_categories_name( $settings, $terms );
		$terms_ids       = self::find_categories_ids( $categories_name, $taxonomy );
		wp_set_object_terms( $post_id, $terms_ids, $taxonomy );
	}

	/**
	 * Finds product categories ids from array of names given
	 *
	 * @param array  $product_cat_names Array of names.
	 * @param string $taxonomy_name Name of taxonomy.
	 * @return string IDS of categories.
	 */
	private static function find_categories_ids( $product_cat_names, $taxonomy_name = 'product_cat' ) {
		$level    = 0;
		$cats_ids = array();

		foreach ( $product_cat_names as $product_cat_name ) {
			$product_cat_name = is_array( $product_cat_name ) ? $product_cat_name['value'] : $product_cat_name;
			$cat_slug         = sanitize_title( $product_cat_name );
			$product_cat      = get_term_by( 'slug', $cat_slug, $taxonomy_name );

			if ( $product_cat ) {
				// Finds the category.
				$cats_ids[ $level ] = $product_cat->term_id;
			} else {
				$parent_prod_id = 0;
				if ( $level > 0 ) {
					$parent_prod_id = $cats_ids[ $level - 1 ];
				}
				// Creates the category.
				$term = wp_insert_term(
					$product_cat_name,
					$taxonomy_name,
					array(
						'slug'   => $cat_slug,
						'parent' => $parent_prod_id,
					)
				);
				if ( ! is_wp_error( $term ) ) {
					$cats_ids[ $level ] = $term['term_id'];
				}
			}
			$level++;
		}

		return $cats_ids;
	}

	/**
	 * Get categories ids
	 *
	 * @param array   $settings Settings of the plugin.
	 * @param string  $item_type Type of the product.
	 * @param boolean $is_new_product Is new.
	 * @return array
	 */
	public static function get_categories_ids( $settings, $item_type, $is_new_product ) {
		if ( empty( $item_type ) ) {
			return array();
		}
		$categories_ids = array();
		$category_newp = isset( $settings['catnp'] ) ? $settings['catnp'] : 'yes';

		if ( ( 'yes' === $category_newp && $is_new_product ) || 'no' === $category_newp ) {
			$categories_name = self::split_categories_name( $settings, $item_type );
			$categories_ids  = self::find_categories_ids( $categories_name );
		}
		return $categories_ids;
	}

	/**
	 * Assign product categories, based on the item data and merge vars.
	 *
	 * @param array  $item Item data.
	 * @param array  $attributes Attributes of the product.
	 * @param array  $settings_mergevars Settings merge variables.
	 * @return array
	 */
	public static function assign_product_categories( $attributes, $settings, $settings_mergevars, $is_new_product = true ) {
		if ( empty( $attributes )  ) {
			return array();
		}
		
		$attribute_cat_id = ! empty( $settings['catattr'] ) ? $settings['catattr'] : '';
		$categories_ids   = array();
		$items_cat_value  = array();

		foreach ( $attributes as $attribute ) {
			if ( $attribute['value'] === $attribute_cat_id ) {
				$items_cat_value[] = $attribute['name'];
			}
		}

		if ( empty( $items_cat_value ) ) {
			return array();
		}

		foreach ( $items_cat_value as $item_cat_value ) {
			// Custom merge categories.
			if ( ! empty( $settings_mergevars['prod_mergevars'] ) ){
				foreach ( $settings_mergevars['prod_mergevars'] as $source => $target ) {
					$target_cat      = explode( '|', $target );
					$source_cat      = explode( '|', $source );
					$target_cat_type = isset( $target_cat[0] ) ? $target_cat[0] : '';
					$source_cat_type = isset( $source_cat[0] ) ? $source_cat[0] : '';

					if ( $target_cat_type !== $source_cat_type ) {
						continue;
					}
					$source_cat_name = isset( $source_cat[1] ) ? sanitize_text_field( $source_cat[1] ) : '';
					$target_cat_id   = isset( $target_cat[1] ) ? (int) $target_cat[1] : 0;

					if ( $item_cat_value === $source_cat_name ) {
						$categories_ids[] = $target_cat_id;
					}
				}
				if ( ! empty( $categories_ids ) ) {
					$categories_ids = array_unique( $categories_ids );
					return $categories_ids;
				}
			}

			// Default mode.
			$category_id    = self::get_categories_ids( $settings, $item_cat_value, $is_new_product );
			$categories_ids = array_merge( $categories_ids, $category_id );
		}

		$categories_ids = array_unique( $categories_ids );
		return $categories_ids;
	}

	/**
	 * Split categories name
	 *
	 * @param array  $settings   Settings of the plugin.
	 * @param string $item_types Types of the product.
	 * @return array
	 */
	public static function split_categories_name( $settings, $item_types ) {
		$categories_name = array();
		$category_sep    = isset( $settings['catsep'] ) ? $settings['catsep'] : '';

		$item_types = is_array( $item_types ) ? $item_types : array( $item_types );

		foreach ( $item_types as $item_type ) {
			$item_value = isset( $item_type['value'] ) ? $item_type['value'] : $item_type;
			if ( empty( $item_value ) ) {
				continue;
			}
			if ( $category_sep && strpos( $item_value, $category_sep ) ) {
				$cats_name = explode( $category_sep, $item_value );
			} else {
				$cats_name[] = $item_value;
			}
			$categories_name = array_merge( $categories_name, $cats_name );
			$categories_name = array_map( 'trim', $categories_name );
		}

		return $categories_name;
	}

	public static function get_terms_product_cat() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}
		
		$terms_wp = wp_list_pluck( $terms, 'name', 'term_id' );
		$terms    = array();
		
		foreach ( $terms_wp as $term_id => $term_name ) {
			$term_parent = get_term( $term_id, 'product_cat' );
			$label = $term_name;
			
			// Add parent term information if it exists
			if ( $term_parent && $term_parent->parent > 0 ) {
				$parent_term = get_term( $term_parent->parent, 'product_cat' );
				if ( $parent_term && ! is_wp_error( $parent_term ) ) {
					$label = $parent_term->name . ' > ' . $term_name;
				}
			}
			
			$terms[ 'product_cat|' . $term_id ] = $label;
		}
		return $terms;
	}

	/**
	 * Assigns the array to a taxonomy, and creates missing term
	 *
	 * @param string $post_id Post id of actual post id.
	 * @param array  $taxonomy_slug Slug of taxonomy.
	 * @param array|string  $terms Array of terms.
	 * @return void
	 */
	public static function assign_product_term( $post_id, $taxonomy_slug, $terms ) {
		$parent_term      = '';
		$terms						= is_array( $terms ) ? $terms : array( $terms );
		$term_levels      = count( $terms );
		$term_level_index = 1;

		foreach ( $terms as $term ) {
			$term        = self::sanitize_text( $term );
			$search_term = term_exists( $term, $taxonomy_slug );

			if ( 0 === $search_term || null === $search_term ) {
				// Creates taxonomy.
				$args_term = array(
					'slug' => sanitize_title( $term ),
				);
				if ( $parent_term ) {
					$args_term['parent'] = $parent_term;
				}
				$search_term = wp_insert_term(
					$term,
					$taxonomy_slug,
					$args_term
				);
			}
			
			// Check if term was found or created successfully
			if ( ! is_wp_error( $search_term ) && $term_level_index === $term_levels ) {
				$term_id = isset( $search_term['term_id'] ) ? (int) $search_term['term_id'] : (int) $search_term;
				wp_set_object_terms( $post_id, $term_id, $taxonomy_slug );
			}

			// Next iteration for child.
			$parent_term = (int) $search_term['term_id'];
			$term_level_index++;
		}
	}

	/**
	 * Internal function to sanitize text
	 *
	 * @param string $text Text to sanitize.
	 * @return string Sanitized text.
	 */
	private static function sanitize_text( $text ) {
		$text = str_replace( '>', '&gt;', $text );
		return sanitize_text_field( $text );
	}

	/**
	 * Return all custom taxonomies
	 *
	 * @return array
	 */
	public static function get_all_custom_taxonomies() {
		$taxonomies        = get_taxonomies( array( 'public' => true ), 'objects' );
		$custom_taxonomies = array();
		foreach ( $taxonomies as $taxonomy ) {
			if ( in_array( $taxonomy->name, array( 'product_type', 'product_shipping_class' ), true ) ) {
				continue;
			}
			$custom_taxonomies[ 'tax|' . $taxonomy->name ] = $taxonomy->label;
		}
		return $custom_taxonomies;
	}
}
