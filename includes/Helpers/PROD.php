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

use CLOSE\ConnectEcommerce\Helpers\TAX;
use CLOSE\ConnectEcommerce\Helpers\AI;

/**
 * Sync Products.
 *
 * @since 1.0.0
 */
class PROD {
	/**
	 * Syncronizes the product from item api to WooCommerce
	 *
	 * @param array  $settings Settings of the plugin.
	 * @param array  $item Item from API.
	 * @param object $api_erp API Object.
	 * @param bool   $generate_ai Force AI.
	 * @param int    $post_id Post ID when it comes forced.
	 * @return array
	 */
	public static function sync_product_item( $settings, $item, $api_erp, $generate_ai = false, $post_id = 0 ) {
		// Prevent errors.
		if ( empty( $item['kind'] ) ) {
			$item['kind'] = 'simple';
		}

		if ( empty( $item['name'] ) ) {
			$item['name'] = __( 'Product without name', 'woocommerce-es' );
		}

		if ( empty( $item['sku'] ) && empty( $item['variants'] ) ) { // Only for simple products.
			return array(
				'status'  => 'error',
				'post_id' => 0,
				'message' => __( 'SKU not finded in product. Product not imported: ', 'woocommerce-es' ) . $item['name'] . '(' . $item['kind'] . ')</br>',
			);
		}
		$item_sku = ! empty( $item['sku'] ) ? $item['sku'] : '';

		if ( empty( $item['variants'] ) && ( 'variants' === $item['kind'] || 'variable' === $item['kind'] ) ) {
			return array(
				'status'  => 'error',
				'post_id' => 0,
				'message' => __( 'Product variable without variants', 'woocommerce-es' ),
			);
		}

		$status         = 'ok';
		$message        = '';
		$is_filtered    = self::filter_product( $settings, $item );
		$is_new_product = $post_id ? false : true;

		if ( empty( $post_id ) ) {
			$post_id        = self::find_product( $item_sku );
			$is_new_product = empty( $post_id ) ? true : false;
		}

		$plugin_pack_active = in_array( 'woo-product-bundle/wpc-product-bundles.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ? true : false;

		// Translations.
		$msg_product_created = __( 'Product created: ', 'woocommerce-es' );
		$msg_product_synced  = __( 'Product synced: ', 'woocommerce-es' );

		if ( ! $is_filtered && $item_sku && 'simple' === $item['kind'] ) {
			$result_post = self::sync_product_simple( $settings, $item, $api_erp, false, $post_id );
			$post_id     = $result_post['post_id'] ?? 0;
			$message    .= $result_post['message'] ?? '';
		} elseif ( ! $is_filtered && ( 'variants' === $item['kind'] || 'variable' === $item['kind'] ) ) {
			// Variable product.
			// Check if any variants exists.
			$any_variant_sku = true;
			if ( ! $post_id ) {
				$post_id = 0;
				// Activar para buscar un archivo.
				$any_variant_sku = false;

				foreach ( $item['variants'] as $variant ) {
					if ( empty( $variant['sku'] ) ) {
						break;
					} else {
						$any_variant_sku = true;
					}
					$post_id = self::find_parent_product( $variant['sku'] );
					if ( $post_id ) {
						// Do not iterate if it's find it.
						break;
					}
				}
			}

			// Fix Parent product without SKU.
			if ( $post_id ) {
				$parent_product = wc_get_product( $post_id );
				if ( $parent_product && ! $parent_product->get_sku() && $item_sku ) {
					try {
						$parent_product->set_sku( $item_sku );
						$parent_product->save();
					} catch ( \Exception $e ) {
						// Error.
						return array(
							'status'  => 'error',
							'post_id' => $post_id,
							'message' => __( 'Error setting SKU to parent product: ', 'woocommerce-es' ) . $e->getMessage(),
						);
					}
				}
				unset( $parent_product );
			}

			if ( false === $any_variant_sku ) {
				$message .= __( 'Product not imported becouse any variant has got SKU: ', 'woocommerce-es' ) . $item['name'] . '(' . $item['kind'] . ') <br/>';
			} else {
				// Update meta for product.
				$result_prod = self::sync_product( $settings, $item, $api_erp, $post_id, 'variable', null );
				$post_id     = $result_prod['prod_id'] ?? 0;
				$message    .= 0 === $post_id || false === $post_id ? $msg_product_created : $msg_product_synced;
				$message    .= $item['name'] . '. SKU: ' . $item_sku . '(' . $item['kind'] . ') ' . $result_prod['message'] ?? '';
			}
		} elseif ( ! $is_filtered && 'pack' === $item['kind'] && $plugin_pack_active ) {
			$post_id = ! empty( $post_id ) ? $post_id : self::find_product( $item_sku );

			if ( ! $post_id ) {
				$post_id = self::create_product_post( $settings, $item );
				wp_set_object_terms( $post_id, 'woosb', 'product_type' );
			}
			if ( $post_id && $item_sku && 'pack' === $item['kind'] ) {
				// Create subproducts before.
				$pack_items = '';
				if ( isset( $item['packItems'] ) && ! empty( $item['packItems'] ) ) {
					foreach ( $item['packItems'] as $pack_item ) {
						$item_simple     = $api_erp->get_products( $pack_item['pid'] );
						$product_pack_id = self::sync_product_simple( $settings, $item_simple, $api_erp, true );
						$pack_items     .= $product_pack_id . '/' . $pack_item['u'] . ',';
						$message        .= ' x ' . $pack_item['u'];
					}
					$message   .= ' ';
					$pack_items = substr( $pack_items, 0, -1 );
				}

				// Update meta for product.
				$result_prod = self::sync_product( $settings, $item, $api_erp, $post_id, 'pack', $pack_items );
				$post_id     = $result_prod['prod_id'] ?? 0;
			} else {
				return array(
					'status'  => 'error',
					'post_id' => $post_id,
					'message' => __( 'There was an error while inserting new product!', 'woocommerce-es' ) . ' ' . $item['name'],
				);
			}
			$message .= $item['name'] . '. SKU: ' . $item_sku . ' (' . $item['kind'] . ')' . $result_prod['message'] ?? '';
		} elseif ( ! $is_filtered && 'pack' === $item['kind'] && ! $plugin_pack_active ) {
			$message .= '<span class="warning">' . __( 'Product needs Plugin to import: ', 'woocommerce-es' );
			$message .= '<a href="https://wordpress.org/plugins/woo-product-bundle/" target="_blank">WPC Product Bundles for WooCommerce</a> ';
			$message .= '(' . $item['kind'] . ') </span>';
		} elseif ( $is_filtered ) {
			// Product not synced without SKU.
			$message .= '<span class="warning">' . __( 'Product filtered to not import: ', 'woocommerce-es' ) . $item['name'] . '(' . $item['kind'] . ') </span>';
		} elseif ( '' === $item_sku && 'simple' === $item['kind'] ) {
			// Product not synced without SKU.
			return array(
				'status'  => 'error',
				'post_id' => (int) $post_id,
				'prod_id' => $item['id'] ? sanitize_text_field( $item['id'] ) : '',
				'message' => __( 'SKU not finded in Simple product. Product not imported: ', 'woocommerce-es' ) . $item['name'] . '(' . $item['kind'] . ')</br>',
			);
		} elseif ( 'simple' !== $item['kind'] ) {
			// Product not synced type not supported.
			return array(
				'status'  => 'error',
				'post_id' => (int) $post_id,
				'prod_id' => $item['id'] ? sanitize_text_field( $item['id'] ) : '',
				'message' => __( 'Product type not supported. Product not imported: ', 'woocommerce-es' ) . $item['name'] . '(' . $item['kind'] . ')',
			);
		}

		if ( ( 'all' === $generate_ai && $post_id ) || ( 'new' === $generate_ai && $is_new_product && $post_id ) ) {
			// Generate description with AI for product.
			$settings_ai = get_option( 'connect_ecommerce_ai' );
			if ( ! empty( $settings_ai['provider'] ) ) {
				$result_ai = AI::generate_description( $settings_ai, $item );
				if ( ! empty( $result_ai ) && 'ok' === $result_ai['status'] ) {
					$message      = '';
					$product_info = array(
						'ID' => $post_id,
					);
					if ( ! empty( $result_ai['data']['title'] ) ) {
						$product_info['post_title'] = $result_ai['data']['title'];
					} else {
						$message .=  __( 'Title not generated. ', 'woocommerce-es' );
					}
					if ( ! empty( $result_ai['data']['body'] ) ) {
						$product_info['post_content'] = $result_ai['data']['body'];
					} else {
						$message .=  __( 'Post content not generated. ', 'woocommerce-es' );
					}
					if ( ! empty( $result_ai['data']['seo_description'] ) ) {
						$product_info['post_excerpt'] = $result_ai['data']['seo_description'];
					} else {
						$message .=  __( 'Post excerpt not generated. ', 'woocommerce-es' );
					}

					// Update product.
					wp_update_post(
						$product_info,
					);

					self::update_product_seo( $post_id, $result_ai['data'] );
					$message .= __( 'Generated AI: ', 'woocommerce-es' );
					$message .= $result_ai['message'] ?? '';
				} else {
					$message .= '<span class="error">' . __( 'Error AI: ', 'woocommerce-es' );
					$message .= $result_ai['message'] ?? '';
					$message .= '</span>';
				}
			}
		}

		$tags     = ! empty( $item['tags'] ) ? $item['tags'] : array();
		$tags     = array_filter( $tags );
		$tags     = array_map( 'sanitize_text_field', $tags );
		$message .= ! empty( $item['tags'] ) ? ' ' . __( 'Tags: ', 'woocommerce-es' ) . implode( ', ', $item['tags'] ) : '';
		return array(
			'status'  => $status,
			'post_id' => (int) $post_id,
			'prod_id' => $item['id'] ? sanitize_text_field( $item['id'] ) : '',
			'message' => $message,
		);
	}

	/**
	 * Update product meta with the object included in WooCommerce
	 *
	 * Coded inspired from: https://github.com/woocommerce/wc-smooth-generator/blob/master/includes/Generator/Product.php
	 *
	 * @param object $settings Product settings.
	 * @param object $item Item Object from ERP.
	 * @param object $api_erp API Object.
	 * @param string $product_id Product ID. If is null, is new product.
	 * @param string $type Type of the product.
	 * @param array  $pack_items Array of packs: post_id and qty.
	 *
	 * @return int $product_id Product ID.
	 */
	public static function sync_product( $settings, $item, $api_erp, $product_id = 0, $type = 'simple', $pack_items = null ) {
		$import_stock       = ! empty( $settings['stock'] ) ? $settings['stock'] : 'no';
		$is_virtual         = ! empty( $settings['virtual'] ) && 'yes' === $settings['virtual'] ? true : false;
		$allow_backorders   = ! empty( $settings['backorders'] ) ? $settings['backorders'] : 'yes';
		$rate_id            = ! empty( $settings['rates'] ) ? $settings['rates'] : 'default';
		$post_status        = ! empty( $settings['prodst'] ) ? $settings['prodst'] : 'draft';
		$is_new_product     = ( 0 === $product_id || false === $product_id ) ? true : false;
		$settings_mergevars = get_option( 'connect_ecommerce_prod_mergevars' );
		$message            = '';
		$product            = null;
		$item_sku           = ! empty( $item['sku'] ) ? $item['sku'] : '';

		// Start.
		try {
			if ( 'simple' === $type ) {
				$product = new \WC_Product( $product_id );
			} elseif ( 'variable' === $type ) {
				$product = new \WC_Product_Variable( $product_id );
			} elseif ( 'pack' === $type ) {
				$product = new \WC_Product( $product_id );
			}
		} catch ( \Exception $e ) {
			return array(
				'status'  => 'error',
				'props'   => [],
				'message' => __( 'Error creating variable product: ', 'woocommerce-es' ) . $e->getMessage(),
			);
		}

		// Common and default properties.
		$product_props     = array(
			'stock_status'     => 'instock',
			'backorders'       => $allow_backorders,
			'length'           => isset( $item['lenght'] ) ? $item['lenght'] : '',
			'width'            => isset( $item['width'] ) ? $item['width'] : '',
			'height'           => isset( $item['height'] ) ? $item['height'] : '',
		);

		if ( 'variable' !== $type ) {
			$product_props['regular_price'] = self::get_rate_price( $item, $rate_id );
		}

		$price_sale = self::get_sale_price( $item, $settings );
		if ( ! empty( $price_sale ) && 'variable' !== $type ) {
			$product_props['sale_price'] = $price_sale;
		}

		$product_props_new = array();
		if ( $is_new_product ) {
			$product_props_new = array(
				'menu_order'         => 0,
				'name'               => isset( $item['name'] ) ? $item['name'] : '',
				'featured'           => false,
				'catalog_visibility' => 'visible',
				'short_description'  => '',
				'description'        => isset( $item['desc'] ) ? $item['desc'] : '',
				'sale_price'         => '',
				'date_on_sale_from'  => '',
				'date_on_sale_to'    => '',
				'total_sales'        => '',
				'tax_status'         => 'taxable',
				'tax_class'          => '',
				'manage_stock'       => 'yes' === $import_stock ? true : false,
				'stock_quantity'     => null,
				'sold_individually'  => false,
				'weight'             => $is_virtual ? '' : $item['weight'],
				'upsell_ids'         => '',
				'cross_sell_ids'     => '',
				'parent_id'          => 0,
				'reviews_allowed'    => true,
				'purchase_note'      => '',
				'virtual'            => $is_virtual,
				'downloadable'       => false,
				'shipping_class_id'  => 0,
				'image_id'           => '',
				'gallery_image_ids'  => '',
				'status'             => $post_status,
			);
		}

		if ( ! empty( $item['barcode'] ) ) {
			try {
				$product->set_global_unique_id( $item['barcode'] );
			} catch ( \Exception $e ) {
				// Error.
			}
		}

		$product_props        = array_merge( $product_props, $product_props_new );
		$product_props['sku'] = $item_sku;
		// Set properties and save.
		$product->set_props( $product_props );
		$product->save();

		$product_id = $product->get_id();
		$item_stock = ! empty( $item['stock'] ) ? $item['stock'] : 0;

		switch ( $type ) {
			case 'simple':
			case 'grouped':
				// Values for simple products.
				// Check if the product can be sold.
				if ( 'no' === $import_stock && $item['price'] > 0 ) {
					$product_props['stock_status']       = 'instock';
					$product_props['catalog_visibility'] = 'visible';
					
					try {
						wp_remove_object_terms( $product_id, 'exclude-from-catalog', 'product_visibility' );
						wp_remove_object_terms( $product_id, 'exclude-from-search', 'product_visibility' );
					} catch ( \Exception $e ) {}
				} elseif ( 'yes' === $import_stock && $item_stock > 0 ) {
					$product_props['manage_stock']       = true;
					$product_props['stock_quantity']     = $item_stock;
					$product_props['stock_status']       = 'instock';
					$product_props['catalog_visibility'] = 'visible';
					// Only call taxonomy functions if taxonomy exists
					try {
						wp_remove_object_terms( $product_id, 'exclude-from-catalog', 'product_visibility' );
						wp_remove_object_terms( $product_id, 'exclude-from-search', 'product_visibility' );
					} catch ( \Exception $e ) {}
				} elseif ( 'yes' === $import_stock && 0 === $item_stock ) {
					$product_props['manage_stock']       = true;
					$product_props['catalog_visibility'] = 'hidden';
					$product_props['stock_quantity']     = 0;
					$product_props['stock_status']       = 'outofstock';
					// Only call taxonomy functions if taxonomy exists
					try {
						wp_set_object_terms( $product_id, array( 'exclude-from-catalog', 'exclude-from-search' ), 'product_visibility' );
					} catch ( \Exception $e ) {}
				} else {
					$product_props['manage_stock']       = true;
					$product_props['catalog_visibility'] = 'hidden';
					$product_props['stock_quantity']     = $item['stock'];
					$product_props['stock_status']       = 'outofstock';
					// Only call taxonomy functions if taxonomy exists
					try {
						wp_set_object_terms( $product_id, array( 'exclude-from-catalog', 'exclude-from-search' ), 'product_visibility' );
					} catch ( \Exception $e ) {}
				}
				break;
			case 'variable':
				$result_var    = self::sync_product_variable( $settings, $product, $item, $is_new_product, $rate_id );
				$product_props = ! empty( $result_var['props'] ) ? $result_var['props'] : array();
				$message      .= ! empty( $result_var['message'] ) ? $result_var['message'] : '';
				break;
			case 'pack':
				self::sync_product_pack( $product, $item, $pack_items );
				break;
		}

		// Set attributes.
		$attributes = ! empty( $item['attributes'] ) && is_array( $item['attributes'] ) ? $item['attributes'] : array();
		$categories_ids = TAX::assign_product_categories( $attributes, $settings, $settings_mergevars, $is_new_product );
		if ( ! empty( $categories_ids ) ) {
			$product_props['category_ids'] = $categories_ids;
		}

		// Imports image.
		self::put_product_images( $settings, $item, $product_id, $api_erp );

		// Adds custom fields and custom cats.
		if ( ! empty( $settings_mergevars['prod_mergevars'] ) ) {
			$product_info = array();
			foreach ( $settings_mergevars['prod_mergevars'] as $source_key => $custom_field ) {
				$field_key  = explode( '|', $custom_field );
				$field_type = isset( $field_key[0] ) ? $field_key[0] : 'cf';
				$field_slug = isset( $field_key[1] ) ? $field_key[1] : $field_key;
				if ( isset( $item[ $custom_field ] ) && 'cf' === $field_type ) {
					$product->update_meta_data( $field_slug, $item[ $custom_field ] );
				} elseif ( isset( $item[ $source_key ] ) && 'cf' === $field_type ) {
					$product->update_meta_data( $field_slug, $item[ $source_key ] );
				} elseif ( isset( $item[ $custom_field ] ) && 'tax' === $field_type ) {
					TAX::set_terms_taxonomy( $settings, $field_slug, $item[ $custom_field ], $product_id );
				} elseif ( isset( $item[ $custom_field ] ) && 'prod' === $field_type ) {
					$product_value               = $item[ $custom_field ];
					$product_info[ $field_slug ] = mb_convert_encoding( $product_value, 'UTF-8', mb_detect_encoding( $product_value ) );
				}
			}

			if ( ! empty( $product_info ) ) {
				$product_info['ID'] = $product_id;
				wp_update_post(
					$product_info,
				);
			}
		}

		$custom_fields = self::get_item_custom_fields( $item );
		if ( ! empty( $custom_fields ) ) {
			foreach ( $custom_fields as $key => $value ) {
				$product->update_meta_data( $key, $value );
			}
		}

		// Equivalence weight.
		if ( ! empty( $settings['prod_weight_eq'] ) && ! empty( $item[ $settings['prod_weight_eq'] ] ) ) {
			$product->update_meta_data( 'conecom_prod_weight_eq', $item[ $settings['prod_weight_eq'] ] );
		}

		// Save ERP ID.
		$product->update_meta_data( 'connect_ecommerce_id', $item['id'] );

		// Save last updated date from API (timestamp) for import stats; fallback to current time.
		$updated_ts = null;
		if ( ! empty( $item['last_updated'] ) ) {
			$updated_ts = is_numeric( $item['last_updated'] ) ? (int) $item['last_updated'] : strtotime( $item['last_updated'] );
		}
		if ( empty( $updated_ts ) || $updated_ts <= 0 ) {
			$updated_ts = current_time( 'timestamp' );
		}
		$product->update_meta_data( 'conecom_updated', $updated_ts );

		// Set properties and save.
		$product->set_props( $product_props );
		$product->save();
		if ( 'pack' === $type ) {
			// Only call taxonomy functions if taxonomy exists
			if ( taxonomy_exists( 'product_type' ) ) {
				wp_set_object_terms( $product_id, 'woosb', 'product_type' );
			}
		}

		return array(
			'status'  => 'ok',
			'message' => $message,
			'prod_id' => $product_id,
		);
	}

	/**
	 * Creates the simple product post from item
	 *
	 * @param object  $settings Product settings.
	 * @param array   $item Item from ERP.
	 * @param object  $api_erp API Object.
	 * @param boolean $from_pack Item is a pack.
	 * @param integer $post_id Post ID.
	 *
	 * @return array
	 */
	private static function sync_product_simple( $settings, $item, $api_erp, $from_pack = false, $post_id = 0 ) {
		$message = '';
		$post_id = empty( $post_id ) ? $post_id : self::find_product( $item['sku'] );

		// Update meta for product.
		$result_prod = self::sync_product( $settings, $item, $api_erp, $post_id, 'simple', null );
		$post_id     = $result_prod['prod_id'] ?? 0;

		// Add custom taxonomies.
		self::add_custom_taxonomies( $post_id, $item );

		if ( $from_pack ) {
			$message .= '<br/>';
			if ( ! $post_id ) {
				$message .= __( 'Subproduct created: ', 'woocommerce-es' );
			} else {
				$message .= __( 'Subproduct synced: ', 'woocommerce-es' );
			}
		} else {
			if ( ! $post_id ) {
				$message .= __( 'Product created: ', 'woocommerce-es' );
			} else {
				$message .= __( 'Product synced: ', 'woocommerce-es' );
			}
		}
		$message .= $item['name'] . '. SKU: ' . $item['sku'] . ' (' . $item['kind'] . ')' . $result_prod['message'] ?? '';

		return array(
			'post_id' => $post_id,
			'message' => $message,
		);
	}

	/**
	 * Syncs product variable
	 *
	 * @param object  $settings Product settings.
	 * @param object  $product Product WooCommerce.
	 * @param array   $item Item from API.
	 * @param boolean $is_new_product Is new product?.
	 * @param int     $rate_id Rate ID.
	 *
	 * @return array
	 */
	public static function sync_product_variable( $settings, $product, $item, $is_new_product, $rate_id ) {
		$attributes      = array();
		$attributes_prod = array();
		$parent_sku      = $product->get_sku();
		$product_id      = $product->get_id();
		$is_virtual      = ( isset( $settings['virtual'] ) && 'yes' === $settings['virtual'] ) ? true : false;
		$import_stock    = ! empty( $settings['stock'] ) ? $settings['stock'] : 'no';
		$message         = '';

		if ( ! $is_new_product ) {
			foreach ( $product->get_children() as $child_id ) {
				// get an instance of the WC_Variation_product Object.
				$variation_children = wc_get_product( $child_id );
				if ( ! $variation_children || ! $variation_children->exists() ) {
					continue;
				}
				$variations_item[ $child_id ] = $variation_children->get_sku();
			}
		}

		// Add custom taxonomies.
		self::add_custom_taxonomies( $product_id, $item );

		// Remove variations without SKU blank.
		if ( ! empty( $variations_item ) ) {
			foreach ( $variations_item as $variation_id => $variation_sku ) {
				if ( $parent_sku == $variation_sku ) {
					wp_delete_post(
						$variation_id,
						false,
					);
				}
			}
		}
		$variations_atachment_ids = array();
		foreach ( $item['variants'] as $variant ) {
			$variation_id = 0; // default value.
			if ( ! $is_new_product && ! empty( $variations_item ) && is_array( $variations_item ) ) {
				$variation_id = array_search( $variant['sku'], $variations_item );
				unset( $variations_item[ $variation_id ] );
			}

			if ( ! isset( $variant['categoryFields'] ) ) {
				$message .= '<span class="error">' . __( 'Variation has no attributes: ', 'woocommerce-es' ) . $item['name'] . '. Variant SKU: ' . $variant['sku'] . '(' . $item['kind'] . ') </span>';
				continue;
			}
			// Get all Attributes for the product.
			foreach ( $variant['categoryFields'] as $category_fields ) {
				if ( ! empty( $category_fields['field'] ) ) {
					if ( ! isset( $attributes[ $category_fields['name'] ] ) || ! in_array( $category_fields['field'], $attributes[ $category_fields['name'] ], true ) ) {
						$attributes[ $category_fields['name'] ][] = $category_fields['field'];
					}
					$attribute_name = wc_sanitize_taxonomy_name( $category_fields['name'] );
					// Array for product.
					$attributes_prod[ 'attribute_pa_' . $attribute_name ] = wc_sanitize_taxonomy_name( $category_fields['field'] );
				}
			}
			// Set stock parent product.
			if ( 'yes' === $import_stock ) {
				$product->set_manage_stock( true );
			} else {
				$product->set_manage_stock( false );
			}

			// Make Variations.
			$variation_price = self::get_rate_price( $variant, $rate_id );
			$variation_props = array(
				'parent_id'     => $product_id,
				'attributes'    => $attributes_prod,
				'regular_price' => $variation_price,
			);

			$price_sale = self::get_sale_price( $variant, $settings );
			if ( ! empty( $price_sale ) ) {
				$variation_props['sale_price'] = $price_sale;
			}
			if ( 0 === $variation_id ) {
				// New variation.
				$variation_props_new = array(
					'tax_status'   => 'taxable',
					'tax_class'    => 'parent',
					'weight'       => '',
					'length'       => '',
					'width'        => '',
					'height'       => '',
					'virtual'      => $is_virtual,
					'downloadable' => false,
					'image_id'     => '',
				);
				$variation_props     = array_merge( $variation_props, $variation_props_new );
			}
			$variation = new \WC_Product_Variation( $variation_id );
			if ( ! empty( $variant['barcode'] ) ) {
				try {
					$variation->set_global_unique_id( $variant['barcode'] );
				} catch ( \Exception $e ) {
					// Error.
				}
			}
			$variation->set_props( $variation_props );
			// Stock.
			if ( 'yes' === $import_stock && isset( $variant['stock'] ) ) {
				$item_stock   = (int) $variant['stock'];
				$stock_status = 0 === $item_stock ? 'outofstock' : 'instock';
				$variation->set_stock_quantity( $item_stock );
				$variation->set_manage_stock( true );
				$variation->set_stock_status( $stock_status );
			} else {
				$variation->set_manage_stock( false );
				$variation->set_stock_status( 'instock' );
			}
			$variation_prevent_id = self::find_product( $variant['sku'] );
			if ( ! empty( $variation_prevent_id ) ) {
				$message .= sprintf(
					/* translators: %s: SKU */
					__( 'Duplicated SKU: %s (not imported) ', 'woocommerce-es' ),
					$variant['sku']
				);
				continue;
			}
			if ( $is_new_product ) {
				$variation->set_sku( $variant['sku'] );
			}

			// Custom fields for variations.
			$custom_fields = self::get_item_custom_fields( $variant );
			if ( ! empty( $custom_fields ) ) {
				foreach ( $custom_fields as $key => $value ) {
					$variation->update_meta_data( $key, $value );
				}
			}
			$variation->update_meta_data( '_connect_ecommerce_productid', $variant['id'] );
			$variation->save();
			$variation_id = empty( $variation_id ) ? $variation->get_id() : $variation_id; // prevents new variation id.

			// Add image to variation.
			if ( ! empty( $variant['image'] ) ) {
				$variations_atachment_ids[] = self::attach_image_to_product( $variation_id, $variant['image'] );
			}
		}
		if ( ! empty( $variations_atachment_ids ) ) {
			$product->set_gallery_image_ids( $variations_atachment_ids );
		}
		$var_prop   = TAX::make_attributes( $attributes, true );
		$data_store = $product->get_data_store();
		$data_store->sort_all_product_variations( $product_id );

		// Check if WooCommerce Variations have more than API and unset.
		if ( ! $is_new_product && ! empty( $variations_item ) ) {
			foreach ( $variations_item as $variation_id => $variation_sku ) {
				wp_update_post(
					array(
						'ID'          => $variation_id,
						'post_status' => 'draft',
					)
				);
			}
		}
		$attributes      = array();
		$attributes_prod = array();
		$att_props       = array();

		if ( ! empty( $item['attributes'] ) ) {
			foreach ( $item['attributes'] as $attribute ) {
				if ( ! isset( $attributes[ $attribute['name'] ] ) || ! in_array( $attribute['value'], $attributes[ $attribute['name'] ], true ) ) {
					$attributes[ $attribute['name'] ][] = $attribute['value'];
				}

				$attribute_name = wc_sanitize_taxonomy_name( $attribute['name'] );
				$attributes_prod[ 'attribute_pa_' . $attribute_name ] = wc_sanitize_taxonomy_name( $attribute['value'] );

				$att_props = TAX::make_attributes( $attributes, false );
			}
		}
		if ( ! empty( $att_props ) ) {
			$product_props['attributes'] = array_merge( $var_prop, $att_props );
		} else {
			$product_props['attributes'] = $var_prop;
		}

		return array(
			'status'  => 'ok',
			'props'   => $product_props,
			'message' => $message,
		);
	}

	/**
	 * Syncs product pack with WooCommerce
	 *
	 * @param object $product Product WooCommerce.
	 * @param array  $item Item API.
	 * @param string $pack_items String with ids.
	 *
	 * @return void
	 */
	public static function sync_product_pack( $product, $item, $pack_items ) {
		$product_id = $product->get_id();

		$wosb_metas = array(
			'woosb_ids'                    => $pack_items,
			'woosb_disable_auto_price'     => 'off',
			'woosb_discount'               => 0,
			'woosb_discount_amount'        => '',
			'woosb_shipping_fee'           => 'whole',
			'woosb_optional_products'      => 'off',
			'woosb_manage_stock'           => 'off',
			'woosb_limit_each_min'         => '',
			'woosb_limit_each_max'         => '',
			'woosb_limit_each_min_default' => 'off',
			'woosb_limit_whole_min'        => '',
			'woosb_limit_whole_max'        => '',
			'woosb_custom_price'           => $item['price'],
		);
		foreach ( $wosb_metas as $key => $value ) {
			update_post_meta( $product_id, $key, $value );
		}
		update_post_meta( $product_id, '_connect_ecommerce_productid', $item['id'] );
	}

	/**
	 * Filters product to not import to web
	 *
	 * @param  array  $settings Settings of the plugin.
	 * @param  array  $item Item product to filter.
	 *
	 * @return boolean True to not get the product, false to get it.
	 */
	public static function filter_product( $settings, $item ) {
		// Filters by Merge vars.
		$settings_mergevars = get_option( 'connect_ecommerce_prod_mergevars' );
		if ( ! empty( $settings_mergevars['prod_mergevars'] ) ) {
			$key = array_search( 'prod|post_status', $settings_mergevars['prod_mergevars'] );
			if ( false !== $key ) {
				$publish_status = $item[ $key ] ?? '';
				if ( empty( $publish_status ) ) {
					return true;
				}
			}
		}

		// Filter by sku.
		$filter = isset( $settings['filter_sku'] ) ? $settings['filter_sku'] : '';
		if ( ! empty( $filter ) && ! empty( $item['sku'] ) && fnmatch( $filter, $item['sku'] ) ) {
			return false;
		} elseif ( ! empty( $filter ) && ! empty( $item['sku'] ) && ! fnmatch( $filter, $item['sku'] ) ) {
			return true;
		}

		// Filter by tags.
		if ( empty( $settings['filter'] ) ) {
			return false;
		}
		if ( empty( $item['tags'] ) ) {
			return false;
		}

		$tags_option = explode( ',', $settings['filter'] );
		$tags_option = array_map( 'trim', $tags_option );
		$tags_option = array_map( 'sanitize_text_field', $tags_option );

		$tags_prod   = array_map( 'trim', $item['tags'] );
		$tags_prod   = array_map( 'sanitize_text_field', $tags_prod );
		$tags_prod   = array_filter( $tags_prod );

		return empty( array_intersect( $tags_option, $tags_prod ) ) ? true : false;
	}

	/**
	 * Creates the post for the product from item
	 *
	 * @param array  $settings Settings of the plugin.
	 * @param [type] $item Item product from api.
	 * @return int
	 */
	public static function create_product_post( $settings, $item ) {
		$post_arg = array(
			'post_title'   => ( $item['name'] ) ? $item['name'] : '',
			'post_content' => ( $item['desc'] ) ? $item['desc'] : '',
			'post_status'  => ! empty( $settings['prodst'] ) ? $settings['prodst'] : 'draft',
			'post_type'    => 'product',
		);
		$post_id   = wp_insert_post( $post_arg );
		if ( $post_id ) {
			update_post_meta( $post_id, '_sku', $item['sku'] );
		}

		return $post_id;
	}

	/**
	 * Checks if product has custom fields.
	 *
	 * @param array $item Item from API.
	 * @return array
	 */
	public static function get_item_custom_fields( $item ) {
		$custom_fields = array();
		foreach ( $item as $key => $value ) {
			if ( 0 === strpos( $key, 'cf|' ) && ! empty( $value ) ) {
				$custom_key                   = str_replace( 'cf|', '', $key );
				$custom_key                   = sanitize_text_field( $custom_key );
				$custom_fields[ $custom_key ] = $value;
			}
		}
		return $custom_fields;
	}

	/**
	 * Gets WooCommerce product SKUs and last modified for import stats.
	 * Only products with connect_ecommerce_id (linked to connector) are returned.
	 * Uses conecom_updated (timestamp) when set, else post_modified.
	 *
	 * @return array Associative array sku => [ 'post_id' => int, 'last_modified' => 'Y-m-d H:i:s' ].
	 */
	public static function get_woocommerce_product_data_for_import_stats() {
		global $wpdb;
		$meta_link    = 'connect_ecommerce_id';
		$meta_sku     = '_sku';
		$meta_updated = 'conecom_updated';
		// Products with connect_ecommerce_id, SKU, and conecom_updated or post_modified for comparison.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT P.ID AS post_id, P.post_modified AS post_modified,
				PM_sku.meta_value AS sku, PM_updated.meta_value AS conecom_updated
				FROM {$wpdb->posts} AS P
				INNER JOIN {$wpdb->postmeta} AS PM_link ON PM_link.post_id = P.ID AND PM_link.meta_key = %s
				INNER JOIN {$wpdb->postmeta} AS PM_sku ON PM_sku.post_id = P.ID AND PM_sku.meta_key = %s
				LEFT JOIN {$wpdb->postmeta} AS PM_updated ON PM_updated.post_id = P.ID AND PM_updated.meta_key = %s
				WHERE P.post_type IN ( 'product', 'product_variation' )
				AND P.post_status != 'trash'
				AND PM_sku.meta_value != ''",
				$meta_link,
				$meta_sku,
				$meta_updated
			),
			ARRAY_A
		);
		$data = array();
		if ( ! empty( $results ) ) {
			foreach ( $results as $row ) {
				$sku = $row['sku'];
				$ts  = ! empty( $row['conecom_updated'] ) && is_numeric( $row['conecom_updated'] )
					? (int) $row['conecom_updated']
					: null;
				$data[ $sku ] = array(
					'post_id'       => (int) $row['post_id'],
					'last_modified' => $ts ? gmdate( 'Y-m-d H:i:s', $ts ) : $row['post_modified'],
				);
			}
		}
		return $data;
	}

	/**
	 * Finds simple and variation item in WooCommerce.
	 *
	 * @param string $sku SKU of product.
	 * @return string $product_id Products id.
	 */
	public static function find_product( $sku, $post_type = 'product' ) {
		global $wpdb;
		$meta_key     = '_sku';
		$result_query = $wpdb->get_var( $wpdb->prepare( "SELECT P.ID FROM $wpdb->posts AS P LEFT JOIN $wpdb->postmeta AS PM ON PM.post_id = P.ID WHERE P.post_type = '$post_type' AND PM.meta_key='$meta_key' AND PM.meta_value=%s AND P.post_status != 'trash' LIMIT 1", $sku ) );

		return (int) $result_query;
	}

	/**
	 * Finds simple and variation item in WooCommerce.
	 *
	 * @param string $sku SKU of product.
	 * @return string $product_id Products id.
	 */
	public static function find_parent_product( $sku ) {
		$post_id_var = self::find_product( $sku, 'product_variation' );

		if ( $post_id_var ) {
			$post_parent = wp_get_post_parent_id( (int) $post_id_var );
			if ( $post_parent && 'product' === get_post_type( $post_parent ) ) {
				return $post_parent;
			} else {
				// Remove orphaned variation.
				wp_delete_post( $post_id_var, true );
			}
		}
		return false;
	}

	/**
	 * Gets image from API products
	 *
	 * @param array  $item Item of API to get information.
	 * @param int $product_id Id of product to get information.
	 * @param object $api_erp API Object.
	 *
	 * @return bool
	 */
	public static function put_product_images( $settings, $item, $product_id, $api_erp ) {
		if ( self::has_valid_thumbnail( $product_id ) ) {
			return false;
		}

		$images = array();
		if ( ! empty( $item['images'] ) ) {
			$images = $item['images'] ?? array();
		} else {
			// Ask API for image.
			$result_api = $api_erp->get_image_product( $settings, $item['id'], $product_id );

			if ( isset( $result_api['errors'] ) ) {
				$message = isset( $result_api['errors'][0]['message'] ) ? $result_api['errors'][0]['message'] : __( 'There was an error while inserting new product!', 'woocommerce-es' );
				HELPER::save_log( 'sync_product_image', $result_api, $message );
				return false;
			}
			if ( isset( $result_api['upload']['url'] ) ){
				$images[] = [
					'url'          => $result_api['upload']['url'],
					'file'         => $result_api['upload']['file'],
					'content_type' => $result_api['content_type'],
				];
			}
		}

		if ( empty( $images ) ) {
			return false;
		}

		$first_image = true;
		foreach ( $images as $image ) {
			self::attach_image_to_product( $product_id, $image, $first_image );
			$first_image = false;
		}

		return true;
	}

	/**
	 * Adds image to product
	 *
	 * @param int    $product_id Product ID.
	 * @param array|string  $image Image data.
	 * @param bool   $first_image If is the first image for thumbnail.
	 *
	 * @return int $attach_id Attachment ID.
	 */
	private static function attach_image_to_product( $product_id, $image_data, $first_image = true ) {
		if ( empty( $image_data ) ) {
			return;
		}
		$image      = is_array( $image_data ) ? $image_data : [ 'url' => $image_data ];
		$attachment = array(
			'guid'           => $image['url'] ?? '',
			'post_mime_type' => $image['content_type'] ?? '',
			'post_title'     => $image['title'] ?? get_the_title( $product_id ),
			'post_content'   => $image['content'] ?? '',
			'post_status'    => 'inherit',
		);

		if ( empty( $image['file'] ) ) { 
			// Download image to server
			$image_url = $image['url'] ?? '';
			if ( empty( $image_url ) ) {
				return;
			}

			$handle_file         = [];
			$handle_file['name'] = basename( $image_url );
			$handle_file['tmp_name'] = download_url( $image_url );

			if ( empty( $attachment['post_mime_type'] ) && ! empty( $handle_file['tmp_name'] ) && file_exists( $handle_file['tmp_name'] ) ) {
				$finfo = finfo_open( FILEINFO_MIME_TYPE );
				if ( $finfo ) {
					$attachment['post_mime_type'] = finfo_file( $finfo, $handle_file['tmp_name'] );
					finfo_close( $finfo );
				}
			}

			// Check for download errors
			if ( is_wp_error( $handle_file['tmp_name'] ) ) {
				return 0;
			}

			// Prevents scripts in the name of the file.
			$base_name_after_download = basename( $handle_file['tmp_name'] );
			$handle_file['name'] = $base_name_after_download;

			// Check if the file already exists in the media library by GUID (URL)
			$attach_id = self::search_image( $image['url'] );
			$attach_id = $attach_id ? $attach_id : self::search_image( $base_name_after_download );
			if ( ! $attach_id ) {
				$attach_id = media_handle_sideload( $handle_file, $product_id, $attachment['post_title'], $attachment );
			}

			// Check for attachment errors
			if (is_wp_error($attach_id)) {
				@unlink($handle_file['tmp_name']);
				return;
			}
		} else {
			if ( ! file_exists( $image['file'] ) ) {
				return;
			}

			$attach_id  = wp_insert_attachment( $attachment, $image['file'], 0 );
		}

		if ( $first_image ) {
			// Set the product image.
			update_post_meta( $product_id, '_thumbnail_id', $attach_id );
		} else {
			// Add the image to the product gallery.
			$gallery = get_post_meta( $product_id, '_product_image_gallery', true );
			$gallery = $gallery ? $gallery . ',' . $attach_id : $attach_id;
			update_post_meta( $product_id, '_product_image_gallery', $gallery );
		}

		return $attach_id;
	}

	/**
	 * Search image by URL or filename
	 *
	 * @param [type] $image_url
	 * @return int
	 */
	private static function search_image( $image_url ) {
		global $wpdb;
		$attach_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM $wpdb->posts WHERE guid LIKE %s AND post_type = 'attachment' LIMIT 1", '%' . $wpdb->esc_like( $image_url )
			)
		);

		// If not found by URL, try searching by filename in the postmeta '_wp_attached_file'
		if ( ! $attach_id && ! empty( $image_url ) ) {
			$filename = basename( $image_url );
			$attach_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT p.ID FROM $wpdb->posts p
					INNER JOIN $wpdb->postmeta pm ON p.ID = pm.post_id
					WHERE pm.meta_key = '_wp_attached_file'
					AND pm.meta_value LIKE %s
					AND p.post_type = 'attachment'
					LIMIT 1",
					'%' . $wpdb->esc_like( $filename )
				)
			);
		}
		return (int) $attach_id; 
	}

	/**
	 * Return all meta keys from WordPress database in post type
	 *
	 * @return array Array of metakeys.
	 */
	public static function get_all_custom_fields() {
		global $wpdb, $table_prefix;
		// If not, query for it and store it for later.
		$fields    = array();
		$sql       = "SELECT DISTINCT( {$table_prefix}postmeta.meta_key )
				FROM {$table_prefix}posts
				LEFT JOIN {$table_prefix}postmeta
					ON {$table_prefix}posts.ID = {$table_prefix}postmeta.post_id
					WHERE {$table_prefix}posts.post_type = 'product'";
		$meta_keys = $wpdb->get_col( $sql );

		foreach ( $meta_keys as $meta_key ) {
			if ( false !== strpos( $meta_key, '_oembed_' ) ) {
				continue;
			}
			$fields[ 'cf|' . $meta_key ] = $meta_key;
		}
		asort( $fields );
		return $fields;
	}

	/**
	 * Return all product fields
	 *
	 * @return array
	 */
	public static function get_all_product_fields() {
		return array(
			'prod|post_title'   => __( 'Product Title', 'woocommerce-es' ),
			'prod|post_content' => __( 'Product Description', 'woocommerce-es' ),
			'prod|post_excerpt' => __( 'Product Short Description', 'woocommerce-es' ),
			'prod|post_status'  => __( 'Product Publish Status', 'woocommerce-es' ),
		);
	}

	/**
	 * Update SEO for product
	 *
	 * @param int    $product_id Product ID.
	 * @param array $seo_data SEO data.
	 * 
	 * @return void
	 */
	private static function update_product_seo( $product_id, $seo_data ) {
		$plugins_seo_meta = array(
			'wordpress-seo/wp-seo.php' => [ // Yoast
				'seo_title' => '_yoast_wpseo_title',
				'seo_description' => '_yoast_wpseo_metadesc',
				'seo_keyword' => '_yoast_wpseo_keywords',
			],
			'wordpress-seo-premium/wp-seo-premium.php' => [ // Yoast Premium
				'seo_title' => '_yoast_wpseo_title',
				'seo_description' => '_yoast_wpseo_metadesc',
				'seo_keyword' => '_yoast_wpseo_keywords',
			],
			'seo-by-rank-math/rank-math.php' => [ // Rank Math
				'seo_title' => 'rank_math_title',
				'seo_description' => 'rank_math_description',
				'seo_keyword' => 'rank_math_focus_keyword',
			],
			'seopress/seopress.php' => [ // SEO Press
				'seo_title' => '_seopress_titles_title',
				'seo_description' => '_seopress_titles_description',
				'seo_keyword' => '_seopress_titles_keywords',
			],
			'all-in-one-seo-pack/all_in_one_seo_pack.php' => [ // All in One SEO Pack
				'seo_title' => '_aioseop_title',
				'seo_description' => '_aioseop_description',
				'seo_keyword' => '_aioseop_keywords',
			],
			'seopress-pro/seopress-pro.php' => [ // SEO Press Pro
				'seo_title' => '_seopress_titles_title',
				'seo_description' => '_seopress_titles_description',
				'seo_keyword' => '_seopress_titles_keywords',
			],
			'wp-meta-seo/wp-meta-seo.php' => [ // WP Meta SEO
				'seo_title' => '_wpseo_title',
				'seo_description' => '_wpseo_metadesc',
				'seo_keyword' => '_wpseo_keywords',
			],
			'autodescription/autodescription.php' => [ // SEO Framework
				'seo_title' => '_autodescription_title',
				'seo_description' => '_autodescription_description',
				'seo_keyword' => '_autodescription_keywords',
			],
		);

		foreach ( $plugins_seo_meta as $plugin => $meta ) {
			if ( is_plugin_active( $plugin ) ) {
				foreach ( $meta as $key => $value ) {
					if ( ! empty( $seo_data[ $key ] ) ) {
						update_post_meta( $product_id, $value, $seo_data[ $key ] );
					}
				}
			}
		}
	}

	/**
	 * Get sale price from item
	 *
	 * @param array $item Item from API.
	 * @param array $settings Settings of the plugin.
	 *
	 * @return string
	 */
	private static function get_sale_price( $item, $settings ) {
		$pricesale_discount = (float) ( $settings['pricesale_discount'] ?? 0 );
		if ( empty( $pricesale_discount ) || empty( $item['price'] ) ) {
			return '';
		}
		$price_sale = $item['price'] - ( $item['price'] * ( $pricesale_discount / 100 ) );
		if ( $price_sale > 0 ) {
			return number_format( $price_sale, 2, '.', '' );
		} else {
			return '';
		}
	}

	/**
	 * Get attribute category ID
	 *
	 * @param array $item Item from API.
	 *
	 * @return int
	 */
	private static function get_rate_price( $item, $rate_id ) {
		$price = null;

		if ( empty( $item ) ) {
			return $price;
		}

		if ( 'default' === $rate_id || '' === $rate_id || empty( $item['rates'] ) || ! is_array( $item['rates'] ) ) {
			$price = isset( $item['price'] ) ? $item['price'] : null;
		} else {
			$price_key = array_search( $rate_id, array_column( $item['rates'], 'id' ) );
			$price     = isset( $item['rates'][ $price_key ]['subtotal'] ) ? $item['rates'][ $price_key ]['subtotal'] : null;
			$price     = empty( $price ) && isset( $item['rates'][ $rate_id ]['subtotal'] ) ? $item['rates'][ $rate_id ]['subtotal'] : $price;
		}
		return (float) $price;
	}

	/**
	 * Add custom taxonomies to product
	 *
	 * @param int   $product_id Product ID.
	 * @param array $item Item from API.
	 *
	 * @return void
	 */
	private static function add_custom_taxonomies( $product_id, $item ) {
		// Set taxonomies.
		if ( ! empty( $item['taxonomies'] ) && is_array( $item['taxonomies'] ) ) {
			foreach ( $item['taxonomies'] as $taxonomy ) {
				if ( empty( $taxonomy['id'] ) || empty( $taxonomy['value'] ) ) {
					continue;
				}
				TAX::assign_product_term( $product_id, $taxonomy['id'], $taxonomy['value'] );
			}
		}
	}

	/**
	 * Checks if product has a valid thumbnail.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	private static function has_valid_thumbnail( $product_id ) {
		$thumbnail_id = get_post_thumbnail_id( $product_id );
		if ( ! $thumbnail_id ) {
			return false;
		}
		$image_path = get_attached_file( $thumbnail_id );
		if ( ! $image_path || ! file_exists( $image_path ) ) {
			return false;
		}
		$image_url = wp_get_attachment_url( $thumbnail_id );
		if ( ! $image_url ) {
			return false;
		}
		return true;
	}

	/**
	 * Get import statistics (only when connector has get_all_product_skus).
	 *
	 * @param object $connapi_erp Connector API object.
	 * @param array  $options Plugin options.
	 * @return array Import statistics.
	 */
	public static function get_import_stats( $connapi_erp, $options, $settings = array() ) {
		if ( ! $connapi_erp || ! method_exists( $connapi_erp, 'get_all_product_skus' ) ) {
			return array(
				'status'  => 'error',
				'message' => __( 'Connector does not support import statistics', 'woocommerce-es' ),
			);
		}

		$transient_key = 'conecom_all_product_skus_' . sanitize_key( $options['slug'] );
		$api_result    = get_transient( $transient_key );
		if ( false === $api_result ) {
			$api_result = $connapi_erp->get_all_product_skus();
			if ( ! isset( $api_result['status'] ) || 'error' !== $api_result['status'] ) {
				set_transient( $transient_key, $api_result, HOUR_IN_SECONDS );
			}
		}

		// Accept 'error' as failure; 'ok' or 'success' (e.g. connect-woocommerce-neo) as success.
		if ( isset( $api_result['status'] ) && 'error' === $api_result['status'] ) {
			$message = isset( $api_result['message'] ) && ! empty( $api_result['message'] )
				? $api_result['message']
				: __( 'Error fetching product SKUs from API', 'woocommerce-es' );
			return array(
				'status'  => 'error',
				'message' => $message,
			);
		}

		// Normalize API result: array of SKUs or array of items with 'sku' (and optionally 'last_updated', 'tags').
		$api_skus         = array();
		$api_skus_updated = array();
		$api_skus_tags    = array();
		if ( isset( $api_result['data'] ) && is_array( $api_result['data'] ) ) {
			$raw = $api_result['data'];
		} elseif ( is_array( $api_result ) ) {
			$raw = $api_result;
		} else {
			$raw = array();
		}

		foreach ( $raw as $item ) {
			if ( is_string( $item ) ) {
				$api_skus[ $item ] = true;
			} elseif ( is_array( $item ) && ! empty( $item['sku'] ) ) {
				$sku              = $item['sku'];
				$api_skus[ $sku ] = true;
				if ( ! empty( $item['last_updated'] ) ) {
					$api_skus_updated[ $sku ] = $item['last_updated'];
				}
				if ( ! empty( $item['tags'] ) ) {
					$api_skus_tags[ $sku ] = (array) $item['tags'];
				}
			}
		}

		$api_total_count = count( $api_skus );

		// Apply tag filter if configured (mirrors PROD::filter_product() logic).
		$filter_tag = ! empty( $settings['filter'] ) ? $settings['filter'] : '';
		if ( ! empty( $filter_tag ) ) {
			$tags_option           = array_map( 'sanitize_text_field', array_map( 'trim', explode( ',', $filter_tag ) ) );
			$filtered_skus         = array();
			$filtered_skus_updated = array();

			foreach ( $api_skus as $sku => $dummy ) {
				$product_tags = isset( $api_skus_tags[ $sku ] ) ? $api_skus_tags[ $sku ] : array();
				$product_tags = array_map( 'sanitize_text_field', array_map( 'trim', (array) $product_tags ) );
				if ( ! empty( array_intersect( $tags_option, $product_tags ) ) ) {
					$filtered_skus[ $sku ] = true;
					if ( isset( $api_skus_updated[ $sku ] ) ) {
						$filtered_skus_updated[ $sku ] = $api_skus_updated[ $sku ];
					}
				}
			}

			$api_skus         = $filtered_skus;
			$api_skus_updated = $filtered_skus_updated;
		}

		$api_count = count( $api_skus );
		$api_ids   = array_keys( $api_skus );

		$wp_products = self::get_woocommerce_product_data_for_import_stats();
		$wp_count    = count( $wp_products );
		$wp_skus     = array_keys( $wp_products );

		$new_properties = array_diff( $api_ids, $wp_skus );
		$new_count      = count( $new_properties );

		$outdated_count = 0;
		foreach ( $wp_products as $sku => $wp_data ) {
			if ( ! isset( $api_skus[ $sku ] ) ) {
				continue;
			}
			$api_date = isset( $api_skus_updated[ $sku ] ) ? $api_skus_updated[ $sku ] : null;
			$wp_date  = isset( $wp_data['last_modified'] ) ? $wp_data['last_modified'] : null;
			if ( empty( $api_date ) || empty( $wp_date ) ) {
				continue;
			}
			$api_ts = is_numeric( $api_date ) ? (int) $api_date : strtotime( $api_date );
			$wp_ts  = is_numeric( $wp_date ) ? (int) $wp_date : strtotime( $wp_date );
			if ( $api_ts > 0 && $wp_ts > 0 && $api_ts > $wp_ts ) {
				++$outdated_count;
			}
		}

		$import_count = $new_count + $outdated_count;

		$to_delete    = array_diff( $wp_skus, $api_ids );
		$delete_count = count( $to_delete );

		return array(
			'status'          => 'success',
			'api_count'       => $api_count,
			'api_total_count' => $api_total_count,
			'available_count' => $api_count,
			'filter_tag'      => $filter_tag,
			'wp_count'        => $wp_count,
			'import_count'    => $import_count,
			'new_count'       => $new_count,
			'outdated_count'  => $outdated_count,
			'delete_count'    => $delete_count,
		);
	}
}
