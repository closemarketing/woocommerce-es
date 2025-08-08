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
		$status         = 'ok';
		$message        = '';
		$is_filtered    = self::filter_product( $settings, $item );
		$item_kind      = ! empty( $item['kind'] ) ? $item['kind'] : 'simple';
		$is_new_product = $post_id ? false : true;

		if ( empty( $post_id ) ) {
			$post_id        = self::find_product( $item['sku'] ?? '' );
			$is_new_product = empty( $post_id ) ? true : false;
		}

		$plugin_pack_active = in_array( 'woo-product-bundle/wpc-product-bundles.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ? true : false;

		// Translations.
		$msg_product_created = __( 'Product created: ', 'connect-ecommerce' );
		$msg_product_synced  = __( 'Product synced: ', 'connect-ecommerce' );

		if ( ! $is_filtered && $item['sku'] && 'simple' === $item_kind ) {
			$result_post = self::sync_product_simple( $settings, $item, $api_erp, false, $post_id );
			$post_id     = $result_post['post_id'] ?? 0;
			$message    .= $result_post['message'] ?? '';
		} elseif ( ! $is_filtered && ( 'variants' === $item_kind || 'variable' === $item_kind ) ) {
			// Variable product.
			// Check if any variants exists.
			$any_variant_sku = true;
			if ( ! $post_id ) {
				$post_id = 0;
				// Activar para buscar un archivo.
				$any_variant_sku = false;
	
				foreach ( $item['variants'] as $variant ) {
					if ( ! $variant['sku'] ) {
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
				if ( $parent_product && ! $parent_product->get_sku() && ! empty( $item['sku'] ) ) {
					try {
						$parent_product->set_sku( $item['sku'] );
						$parent_product->save();
					} catch ( \Exception $e ) {}
				}
				unset( $parent_product );
			}

			if ( false === $any_variant_sku ) {
				$message .= __( 'Product not imported becouse any variant has got SKU: ', 'connect-ecommerce' ) . $item['name'] . '(' . $item_kind . ') <br/>';
			} else {
				// Update meta for product.
				$result_prod = self::sync_product( $settings, $item, $api_erp, $post_id, 'variable', null );
				$post_id     = $result_prod['prod_id'] ?? 0;
				$message    .= 0 === $post_id || false === $post_id ? $msg_product_created : $msg_product_synced;
				$message    .= $item['name'] . '. SKU: ' . $item['sku'] . '(' . $item_kind . ') ' . $result_prod['message'] ?? '';
			}
		} elseif ( ! $is_filtered && 'pack' === $item_kind && $plugin_pack_active ) {
			$post_id = ! empty( $post_id ) ? $post_id : self::find_product( $item['sku'] );

			if ( ! $post_id ) {
				$post_id = self::create_product_post( $settings, $item );
				wp_set_object_terms( $post_id, 'woosb', 'product_type' );
			}
			if ( $post_id && $item['sku'] && 'pack' === $item_kind ) {
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
					'message' => __( 'There was an error while inserting new product!', 'connect-ecommerce' ) . ' ' . $item['name'],
				);
			}
			$message .= $item['name'] . '. SKU: ' . $item['sku'] . ' (' . $item_kind . ')' . $result_prod['message'] ?? '';
		} elseif ( ! $is_filtered && 'pack' === $item_kind && ! $plugin_pack_active ) {
			$message .= '<span class="warning">' . __( 'Product needs Plugin to import: ', 'connect-ecommerce' );
			$message .= '<a href="https://wordpress.org/plugins/woo-product-bundle/" target="_blank">WPC Product Bundles for WooCommerce</a> ';
			$message .= '(' . $item_kind . ') </span>';
		} elseif ( $is_filtered ) {
			// Product not synced without SKU.
			$message .= '<span class="warning">' . __( 'Product filtered to not import: ', 'connect-ecommerce' ) . $item['name'] . '(' . $item_kind . ') </span>';
		} elseif ( '' === $item['sku'] && 'simple' === $item_kind ) {
			// Product not synced without SKU.
			return array(
				'status'  => 'error',
				'post_id' => (int) $post_id,
				'prod_id' => $item['id'] ? sanitize_text_field( $item['id'] ) : '',
				'message' => __( 'SKU not finded in Simple product. Product not imported: ', 'connect-ecommerce' ) . $item['name'] . '(' . $item_kind . ')</br>',
			);
		} elseif ( 'simple' !== $item_kind ) {
			// Product not synced type not supported.
			return array(
				'status'  => 'error',
				'post_id' => (int) $post_id,
				'prod_id' => $item['id'] ? sanitize_text_field( $item['id'] ) : '',
				'message' => __( 'Product type not supported. Product not imported: ', 'connect-ecommerce' ) . $item['name'] . '(' . $item_kind . ')',
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
						$message .=  __( 'Title not generated. ', 'connect-ecommerce' );
					}
					if ( ! empty( $result_ai['data']['body'] ) ) {
						$product_info['post_content'] = $result_ai['data']['body'];
					} else {
						$message .=  __( 'Post content not generated. ', 'connect-ecommerce' );
					}
					if ( ! empty( $result_ai['data']['seo_description'] ) ) {
						$product_info['post_excerpt'] = $result_ai['data']['seo_description'];
					} else {
						$message .=  __( 'Post excerpt not generated. ', 'connect-ecommerce' );
					}

					// Update product.
					wp_update_post(
						$product_info,
					);

					$seo_title = ! empty( $result_ai['data']['seo_title'] ) ? sanitize_text_field( $result_ai['data']['seo_title'] ) : '';
					$seo_desc  = ! empty( $result_ai['data']['seo_description'] ) ? sanitize_text_field( $result_ai['data']['seo_description'] ) : '';
					self::update_product_seo( $post_id, $seo_title, $seo_desc );
					$message .= __( 'Generated AI: ', 'connect-ecommerce' );
					$message .= $result_ai['message'] ?? '';
				} else {
					$message .= '<span class="error">' . __( 'Error AI: ', 'connect-ecommerce' );
					$message .= $result_ai['message'] ?? '';
					$message .= '</span>';
				}
			}
		}

		$tags     = ! empty( $item['tags'] ) ? $item['tags'] : array();
		$tags     = array_filter( $tags );
		$tags     = array_map( 'sanitize_text_field', $tags );
		$message .= ! empty( $item['tags'] ) ? ' ' . __( 'Tags: ', 'connect-ecommerce' ) . implode( ', ', $item['tags'] ) : '';
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
		$import_stock     = ! empty( $settings['stock'] ) ? $settings['stock'] : 'no';
		$is_virtual       = ! empty( $settings['virtual'] ) && 'yes' === $settings['virtual'] ? true : false;
		$allow_backorders = ! empty( $settings['backorders'] ) ? $settings['backorders'] : 'yes';
		$rate_id          = ! empty( $settings['rates'] ) ? $settings['rates'] : 'default';
		$post_status      = ! empty( $settings['prodst'] ) ? $settings['prodst'] : 'draft';
		$attribute_cat_id = ! empty( $settings['catattr'] ) ? $settings['catattr'] : '';
		$is_new_product   = ( 0 === $product_id || false === $product_id ) ? true : false;
		$message          = '';

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
				'message' => __( 'Error creating variable product: ', 'connect-ecommerce' ) . $e->getMessage(),
			);
		}

		// Common and default properties.
		$product_props     = array(
			'stock_status'     => 'instock',
			'backorders'       => $allow_backorders,
			'regular_price'    => self::get_rate_price( $item, $rate_id ),
			'length'           => isset( $item['lenght'] ) ? $item['lenght'] : '',
			'width'            => isset( $item['width'] ) ? $item['width'] : '',
			'height'           => isset( $item['height'] ) ? $item['height'] : '',
		);
		$price_sale = self::get_sale_price( $item, $settings );
		if ( ! empty( $price_sale ) ) {
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
		$product_props['sku'] = $item['sku'] ?? '';
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
					wp_remove_object_terms( $product_id, 'exclude-from-catalog', 'product_visibility' );
					wp_remove_object_terms( $product_id, 'exclude-from-search', 'product_visibility' );
				} elseif ( 'yes' === $import_stock && $item_stock > 0 ) {
					$product_props['manage_stock']       = true;
					$product_props['stock_quantity']     = $item_stock;
					$product_props['stock_status']       = 'instock';
					$product_props['catalog_visibility'] = 'visible';
					wp_remove_object_terms( $product_id, 'exclude-from-catalog', 'product_visibility' );
					wp_remove_object_terms( $product_id, 'exclude-from-search', 'product_visibility' );
				} elseif ( 'yes' === $import_stock && 0 === $item_stock ) {
					$product_props['manage_stock']       = true;
					$product_props['catalog_visibility'] = 'hidden';
					$product_props['stock_quantity']     = 0;
					$product_props['stock_status']       = 'outofstock';
					wp_set_object_terms( $product_id, array( 'exclude-from-catalog', 'exclude-from-search' ), 'product_visibility' );
				} else {
					$product_props['manage_stock']       = true;
					$product_props['catalog_visibility'] = 'hidden';
					$product_props['stock_quantity']     = $item['stock'];
					$product_props['stock_status']       = 'outofstock';
					wp_set_object_terms( $product_id, array( 'exclude-from-catalog', 'exclude-from-search' ), 'product_visibility' );
				}
				break;
			case 'variable':
				$result_var    = self::sync_product_variable( $settings, $product, $item, $is_new_product, $rate_id );
				$product_props = ! empty( $result_var['props'] ) ? $result_var['props'] : array();
				$message      .= ! empty( $result_var['message'] ) ? $result_var['message'] : '';
				break;
			case 'pack':
				$product_props = self::sync_product_pack( $settings, $product, $item, $pack_items );
				break;
		}

		// Set attributes.
		$attributes = ! empty( $item['attributes'] ) && is_array( $item['attributes'] ) ? $item['attributes'] : array();
		$cat_name   = array_column( $attributes, 'name', 'value' )[ $attribute_cat_id ] ?? '';
		if ( $cat_name ) {
			$categories_ids = TAX::get_categories_ids( $settings, $cat_name, $is_new_product );
			if ( ! empty( $categories_ids ) ) {
				$product_props['category_ids'] = $categories_ids;
			}
		}

		// Imports image.
		self::put_product_images( $settings, $item, $product_id, $api_erp );

		// Adds custom fields.
		$settings_mergevars = get_option( 'connect_ecommerce_prod_mergevars' );
		if ( ! empty( $settings_mergevars['prod_mergevars'] ) ) {
			$product_info = array();
			foreach ( $settings_mergevars['prod_mergevars'] as $custom_field ) {
				$field_key  = explode( '|', $custom_field );
				$field_type = isset( $field_key[0] ) ? $field_key[0] : 'cf';
				$field_slug = isset( $field_key[1] ) ? $field_key[1] : $field_key;
				if ( isset( $item[ $custom_field ] ) && 'cf' === $field_type ) {
					$product->update_meta_data( $field_slug, $item[ $custom_field ] );
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

		// Set properties and save.
		$product->set_props( $product_props );
		$product->save();
		if ( 'pack' === $type ) {
			wp_set_object_terms( $product_id, 'woosb', 'product_type' );
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
				$message .= __( 'Subproduct created: ', 'connect-ecommerce' );
			} else {
				$message .= __( 'Subproduct synced: ', 'connect-ecommerce' );
			}
		} else {
			if ( ! $post_id ) {
				$message .= __( 'Product created: ', 'connect-ecommerce' );
			} else {
				$message .= __( 'Product synced: ', 'connect-ecommerce' );
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
		$message         = '';

		if ( ! $is_new_product ) {
			foreach ( $product->get_children( false ) as $child_id ) {
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
		foreach ( $item['variants'] as $variant ) {
			$variation_id = 0; // default value.
			if ( ! $is_new_product && is_array( $variations_item ) ) {
				$variation_id = array_search( $variant['sku'], $variations_item );
				unset( $variations_item[ $variation_id ] );
			}

			if ( ! isset( $variant['categoryFields'] ) ) {
				$message .= '<span class="error">' . __( 'Variation has no attributes: ', 'connect-ecommerce' ) . $item['name'] . '. Variant SKU: ' . $variant['sku'] . '(' . $item['kind'] . ') </span>';
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
			// Make Variations.
			$variation_price   = self::get_rate_price( $variant, $rate_id );
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
					'tax_class'    => '',
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
			if ( isset( $variant['stock'] ) ) {
				$stock_status = 0 === (int) $variant['stock'] ? 'outofstock' : 'instock';
				$variation->set_stock_quantity( (int) $variant['stock'] );
				$variation->set_manage_stock( true );
				$variation->set_stock_status( $stock_status );
			} else {
				$variation->set_manage_stock( false );
			}
			$variation_prevent_id = self::find_product( $variant['sku'] );
			if ( ! empty( $variation_prevent_id ) ) {
				$message .= sprintf(
					/* translators: %s: SKU */
					__( 'Duplicated SKU: %s (not imported) ', 'connect-ecommerce' ),
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
			$variation->save();
			update_post_meta( $variation_id, '_connect_ecommerce_productid', $variant['id'] );
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
				$publish_status = $item[ $settings_mergevars['prod_mergevars'][ $key ] ];
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
		if ( empty( $settings['filter'] ) || empty( $item['tags'] ) ) {
			return false;
		}
		$tags_option = explode( ',', $settings['filter'] );
		$tags_option = array_map( 'trim', $tags_option );
		$tags_option = array_map( 'sanitize_text_field', $tags_option );

		$tags_prod = array_map( 'trim', $item['tags'] );
		$tags_prod = array_map( 'sanitize_text_field', $tags_prod );
		$tags_prod = array_filter( $tags_prod );

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
	 * Attachs images to a post id
	 *
	 * @param int    $post_id Post id.
	 * @param string $img_string Image string from API.
	 * @return int
	 */
	public static function attach_image( $post_id, $img_string ) {
		if ( ! $img_string || ! $post_id ) {
			return null;
		}

		$post         = get_post( $post_id );
		$upload_dir   = wp_upload_dir();
		$upload_path  = $upload_dir['path'];
		$filename     = $post->post_name . '.png';
		$image_upload = file_put_contents( $upload_path . $filename, $img_string );
		// HANDLE UPLOADED FILE.
		if ( ! function_exists( 'wp_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! function_exists( 'wp_get_current_user' ) ) {
			require_once ABSPATH . 'wp-includes/pluggable.php';
		}
		$file = array(
			'error'    => '',
			'tmp_name' => $upload_path . $filename,
			'name'     => $filename,
			'type'     => 'image/png',
			'size'     => filesize( $upload_path . $filename ),
		);
		if ( ! empty( $file ) ) {
			$file_return = wp_handle_sideload( $file, array( 'test_form' => false ) );
			$filename    = $file_return['file'];
		}
		if ( isset( $file_return['file'] ) && isset( $file_return['file'] ) ) {
			$attachment = array(
				'post_mime_type' => $file_return['type'],
				'post_title'     => preg_replace( '/\.[^.]+$/', ' ', basename( $file_return['file'] ) ),
				'post_content'   => '',
				'post_status'    => 'inherit',
				'guid'           => $file_return['url'],
			);
			$attach_id  = wp_insert_attachment( $attachment, $filename, $post_id );
			if ( $attach_id ) {
				require_once ABSPATH . 'wp-admin/includes/image.php';
				$post_thumbnail_id = get_post_thumbnail_id( $post_id );
				if ( $post_thumbnail_id ) {
					wp_delete_attachment( $post_thumbnail_id, true );
				}
				$attach_data = wp_generate_attachment_metadata( $attach_id, $filename );
				wp_update_attachment_metadata( $attach_id, $attach_data );
				set_post_thumbnail( $post_id, $attach_id );
			}
		}
	}

	/**
	 * Gets image from API products
	 *
	 * @param string $item Item of API to get information.
	 * @param string $product_id Id of product to get information.
	 * @param object $api_erp API Object.
	 *
	 * @return void
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

			if ( isset( $body_response['errors'] ) ) {
				$message = isset( $body_response['errors'][0]['message'] ) ? $body_response['errors'][0]['message'] : __( 'There was an error while inserting new product!', 'connect-ecommerce' );
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
			return;
		}

		$first_image = true;
		foreach ( $images as $image ) {
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
					continue;
				}

				$file_array = [];
				$file_array['name'] = basename( $image_url );
				$file_array['tmp_name'] = download_url( $image_url );

				// Check for download errors
				if ( is_wp_error( $file_array['tmp_name'] ) ) {
					continue;
				}

				$attach_id = media_handle_sideload( $file_array, $product_id, $attachment['post_title'], $attachment );

				// Check for attachment errors
				if (is_wp_error($attach_id)) {
					@unlink($file_array['tmp_name']);
					continue;
				}
			} else {
				if ( ! file_exists( $image['file'] ) ) {
					continue;
				}

				$attach_id  = wp_insert_attachment( $attachment, $image['file'], 0 );
			}

			if ( $first_image ) {
				$first_image = false;
				// Set the product image.
				add_post_meta( $product_id, '_thumbnail_id', $attach_id, true );
			} else {
				// Add the image to the product gallery.
				$gallery = get_post_meta( $product_id, '_product_image_gallery', true );
				$gallery = $gallery ? $gallery . ',' . $attach_id : $attach_id;
				update_post_meta( $product_id, '_product_image_gallery', $gallery );
			}
		}
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
			'prod|post_title'   => __( 'Product Title', 'connect-ecommerce' ),
			'prod|post_content' => __( 'Product Description', 'connect-ecommerce' ),
			'prod|post_excerpt' => __( 'Product Short Description', 'connect-ecommerce' ),
			'prod|post_status'  => __( 'Product Publish Status', 'connect-ecommerce' ),
		);
	}

	/**
	 * Update SEO for product
	 *
	 * @param int    $product_id Product ID.
	 * @param string $seo_title Title SEO.
	 * @param string $seo_desc Description SEO.
	 */
	private static function update_product_seo( $product_id, $seo_title, $seo_desc ) {
		// Yoast.
		if ( is_plugin_active( 'wordpress-seo/wp-seo.php' ) || is_plugin_active( 'wordpress-seo-premium/wp-seo-premium.php' ) ) {
			if ( ! empty( $seo_title ) ) {
				update_post_meta( $product_id, '_yoast_wpseo_title', $seo_title );
			}
			if ( ! empty( $seo_desc ) ) {
				update_post_meta( $product_id, '_yoast_wpseo_metadesc', $seo_desc );
			}
		}
		// Rank Math.
		if ( is_plugin_active( 'seo-by-rank-math/rank-math.php' ) ) {
			if ( ! empty( $seo_title ) ) {
				update_post_meta( $product_id, 'rank_math_title', $seo_title );
			}
			if ( ! empty( $seo_desc ) ) {
				update_post_meta( $product_id, 'rank_math_description', $seo_desc );
			}
		}
		// SEOPress.
		if ( is_plugin_active( 'seopress/seopress.php' ) ) {
			if ( ! empty( $seo_title ) ) {
				update_post_meta( $product_id, '_seopress_titles_title', $seo_title );
			}
			if ( ! empty( $seo_desc ) ) {
				update_post_meta( $product_id, '_seopress_titles_description', $seo_desc );
			}
		}
		// All in one SEO.
		if ( is_plugin_active( 'all-in-one-seo-pack/all_in_one_seo_pack.php' ) ) {
			if ( ! empty( $seo_title ) ) {
				update_post_meta( $product_id, '_aioseop_title', $seo_title );
			}
			if ( ! empty( $seo_desc ) ) {
				update_post_meta( $product_id, '_aioseop_description', $seo_desc );
			}
		}
		// SEOPress.
		if ( is_plugin_active( 'seopress-pro/seopress-pro.php' ) ) {
			if ( ! empty( $seo_title ) ) {
				update_post_meta( $product_id, '_seopress_titles_title', $seo_title );
			}
			if ( ! empty( $seo_desc ) ) {
				update_post_meta( $product_id, '_seopress_titles_description', $seo_desc );
			}
		}
		// WP Meta SEO.
		if ( is_plugin_active( 'wp-meta-seo/wp-meta-seo.php' ) ) {
			if ( ! empty( $seo_title ) ) {
				update_post_meta( $product_id, '_wpseo_title', $seo_title );
			}
			if ( ! empty( $seo_desc ) ) {
				update_post_meta( $product_id, '_wpseo_metadesc', $seo_desc );
			}
		}

		// SEO Framework.
		if ( is_plugin_active( 'autodescription/autodescription.php' ) ) {
			if ( ! empty( $seo_title ) ) {
				update_post_meta( $product_id, '_autodescription_title', $seo_title );
			}
			if ( ! empty( $seo_desc ) ) {
				update_post_meta( $product_id, '_autodescription_description', $seo_desc );
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
				TAX::assign_product_term( $product_id, $taxonomy['id'], $taxonomy['value'], true );
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
}
