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
class ORDER {
	/**
	 * Creates invoice data to API
	 *
	 * @param array  $settings Settings data.
	 * @param string $order_id Order id to api.
	 * @param string $meta_key_order Meta key order.
	 * @param string $option_prefix Option prefix.
	 * @param object $api_erp API ERP.
	 * @param bool   $force    Force create.
	 * @param string $default_freeorder Fallback for the "Create document for free Orders?"
	 *                                  setting when the merchant never saved it, lets a
	 *                                  connector (e.g. one with no document to skip) opt in
	 *                                  to processing free orders by default.
	 *
	 * @return array
	 */
	public static function create_invoice( $settings, $order_id, $meta_key_order, $option_prefix, $api_erp, $force = false, $default_freeorder = 'no' ) {
		$order          = wc_get_order( $order_id );
		$order_total    = (float) $order->get_total();
		$ec_invoice_id  = $order->get_meta( $meta_key_order );
		$freeorder      = isset( $settings['freeorder'] ) ? $settings['freeorder'] : $default_freeorder;
		$order_free_msg = __( 'Free order not created ', 'woocommerce-es' );
		$is_debug_log   = isset( $settings['debug_log'] ) && 'on' === $settings['debug_log'] ? true : false;

		// Not create order if free.
		if ( 'no' === $freeorder && empty( $order_total ) && empty( $ec_invoice_id ) ) {
			$order->update_meta_data( $meta_key_order, 'nocreate' );
			$order->save();

			$order->add_order_note( $order_free_msg );
			return array(
				'status'  => 'ok',
				'message' => $order_free_msg,
			);
		} elseif ( ! empty( $ec_invoice_id ) && 'nocreate' === $ec_invoice_id ) {
			$order_free_msg = __( 'Free order not created ', 'woocommerce-es' );
			return array(
				'status'  => 'ok',
				'message' => $order_free_msg,
			);
		}

		// Order refund.
		if ( is_a( $order, 'WC_Order_Refund' ) ) {
			return array(
				'status'  => 'error',
				'message' => __( 'Connot create refund', 'woocommerce-es' ),
			);
		}
		$doctype = isset( $settings['doctype'] ) ? $settings['doctype'] : 'invoice';

		// Create the inovice.
		$order_data = self::generate_order_data( $settings, $order, $option_prefix );
		if ( empty( $ec_invoice_id ) || $force ) {
			try {
				$doc_id     = $order->get_meta( '_' . $option_prefix . '_doc_id' );
				$invoice_id = $order->get_meta( $meta_key_order );
				$result     = $api_erp->create_order( $order_data, $doc_id, $invoice_id, $force );

				$doc_id     = 'error' === $result['status'] ? '' : ( $result['document_id'] ?? '' );
				$invoice_id = isset( $result['invoice_id'] ) ? $result['invoice_id'] : $invoice_id;
				$order->update_meta_data( $meta_key_order, $invoice_id );
				$order->update_meta_data( '_' . $option_prefix . '_doc_id', $doc_id );
				$order->update_meta_data( '_' . $option_prefix . '_doc_type', $doctype );
				if ( $is_debug_log && isset( $result['log_payload'] ) ) {
					$order->update_meta_data( '_' . $option_prefix . '_log_payload', $result['log_payload'] );
				}
				$order->save();

				$order_msg = __( 'Order synced correctly with ERP, ID: ', 'woocommerce-es' ) . $invoice_id;

				$order->add_order_note( $order_msg );
			} catch ( \Exception $e ) {
				$result = array(
					'status'  => 'error',
					'message' => $e->getMessage(),
				);
			}
		} else {
			$result = array(
				'status'  => 'ok',
				'message' => $doctype . ' ' . __( 'num: ', 'woocommerce-es' ) . $ec_invoice_id,
			);
		}
		if ( $is_debug_log ) {
			HELPER::save_log( 'create_order', $order_data, $result );
		}
		// Send alert if there was an error creating the order.
		if ( 'error' === $result['status'] && ! empty( $result['message'] ) ) {
			ALERT::send_order_error_alert( $order_id, $result['message'] );
		}
		return $result;
	}

	/**
	 * Generate data for Order ERP
	 *
	 * @param object $settings Settings data.
	 * @param object $order Order data from WooCommerce.
	 * @param string $option_prefix Option prefix.
	 *
	 * @return array
	 */
	public static function generate_order_data( $settings, $order, $option_prefix ) {
		$order_label_id = is_multisite() ? ( get_current_blog_id() * 100000000 ) + $order->get_id() : $order->get_id();
		$doclang        = $order->get_billing_country() !== 'ES' ? 'en' : 'es';
		$shop_url       = wc_get_endpoint_url( 'shop' );

		if ( empty( $order->get_billing_company() ) ) {
			$contact_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
		} else {
			$contact_name = $order->get_billing_company();
		}
		$first_name           = $order->get_billing_first_name();
		$last_name            = $order->get_billing_last_name();
		$billing_company      = $order->get_billing_company();
		$billing_address      = implode( ', ', array_filter( array( $order->get_billing_address_1(), $order->get_billing_address_2() ), 'strlen' ) );
		$billing_city         = $order->get_billing_city();
		$billing_postcode     = $order->get_billing_postcode();
		$billing_state_code   = $order->get_billing_state();
		$billing_country_code = $order->get_billing_country();

		// Clean special chars.
		if ( isset( $settings['cleanchars'] ) && 'on' === $settings['cleanchars'] ) {
			$contact_name         = self::clean_special_chars( $contact_name );
			$first_name           = self::clean_special_chars( $first_name );
			$last_name            = self::clean_special_chars( $last_name );
			$billing_company      = self::clean_special_chars( $billing_company );
			$billing_address      = self::clean_special_chars( $billing_address );
			$billing_city         = self::clean_special_chars( $billing_city );
			$billing_postcode     = self::clean_special_chars( $billing_postcode );
			$billing_state_code   = self::clean_special_chars( $billing_state_code );
			$billing_country_code = self::clean_special_chars( $billing_country_code );
		}

		// State and Country.
		$order_description = get_bloginfo( 'name', 'display' ) . ' WooCommerce ' . $order_label_id;
		$woo_states        = WC()->countries->get_states( $billing_country_code );
		$billing_state     = ! empty( $billing_state_code ) && ! empty( $billing_country_code ) && ! empty( $woo_states[ $billing_state_code ] ) ? $woo_states[ $billing_state_code ] : '';
		if ( isset( $settings['cleanchars'] ) && 'on' === $settings['cleanchars'] ) {
			$billing_state = self::clean_special_chars( $billing_state );
		}

		$contact_code = self::get_billing_vat( $order );

		// Order Reference.
		$base_domain = basename( sanitize_text_field( $_SERVER['HTTP_HOST'] ) );
		$base_domain = str_replace( 'www.', '', $base_domain );
		$prefix      = $base_domain . '_';

		/**
		 * ## Fields
		 * --------------------------- */
		$order_data = array(
			'contactUserID'          => $order->get_user_id(),
			'contactCode'            => $contact_code,
			'contactName'            => $contact_name,
			'contactFirstName'       => $first_name,
			'contactLastName'        => $last_name,
			'woocommerceCustomer'    => $order->get_user()->data->user_login ?? '',
			'marketplace'            => 'woocommerce',
			'woocommerceOrderStatus' => $order->get_status(),
			'woocommerceOrderId'     => $order_label_id,
			'woocommerceReference'   => $prefix . $order_label_id,
			'woocommerceUrl'         => $shop_url,
			'woocommerceOrderEdit'   => $order->get_edit_order_url(),
			'woocommerceStore'       => get_bloginfo( 'name', 'display' ),
			'contactEmail'           => $order->get_billing_email(),
			'contactCompany'         => $billing_company,
			'contact_phone'          => $order->get_billing_phone(),
			'contactAddress'         => $billing_address,
			'contactCity'            => $billing_city,
			'contactCp'              => $billing_postcode,
			'contactProvince'        => $billing_state,
			'contactCountryCode'     => $billing_country_code,
			'desc'                   => $order_description,
			'date'                   => $order->get_date_completed() ? strtotime( $order->get_date_completed() ) : strtotime( $order->get_date_created() ),
			'datestart'              => strtotime( $order->get_date_created() ),
			'notes'                  => $order->get_customer_note(),
			'saleschannel'           => null,
			'currency'               => get_woocommerce_currency(),
			'language'               => $doclang,
			'items'                  => array(),
			'approveDoc'             => false,
			'total'                  => (float) $order->get_total(),
			'total_tax'              => (float) $order->get_total_tax(),
			'is_paid'                => $order->is_paid(),
		);

		// Approve document.
		$approve_document = isset( $settings['approve_document'] ) ? $settings['approve_document'] : 'no';
		if ( 'yes' === $approve_document ) {
			$order_data['approveDoc'] = true;
		}

		// DesignID.
		$design_id = isset( $settings['design_id'] ) ? $settings['design_id'] : '';
		if ( $design_id ) {
			$order_data['designId'] = $design_id;
		}

		// Series ID.
		$series_number = isset( $settings['series'] ) ? $settings['series'] : '';
		if ( ! empty( $series_number ) && 'default' !== $series_number ) {
			$order_data['numSerieId'] = $series_number;
		}

		// Visitor Key.
		$visitor_key = isset( $settings['clientify_vk'] ) ? $settings['clientify_vk'] : '';
		if ( ! empty( $visitor_key ) ) {
			$order_data['clientify_vk'] = $visitor_key;
		}

		// Payment method.
		$order_data = array_merge( $order_data, PAYMENTS::get_equivalent_payment_method( $order, $settings ) );

		// Treasury.
		$order_data = array_merge( $order_data, PAYMENTS::get_equivalent_treasury( $order, $settings ) );

		$result_items        = self::review_items( $order, $option_prefix );
		$order_data['items'] = $result_items['items'];

		// Add shipping if products not virtual.
		if ( ! $result_items['has_virtual'] ) {
			$order_data = array_merge(
				$order_data,
				array(
					'shippingAddress'    => $order->get_shipping_address_1() ? $order->get_shipping_address_1() . ',' . $order->get_shipping_address_2() : '',
					'shippingPostalCode' => $order->get_shipping_postcode(),
					'shippingCity'       => $order->get_shipping_city(),
					'shippingProvince'   => $order->get_shipping_state(),
					'shippingCountry'    => $order->get_shipping_country(),
				)
			);
		}

		return $order_data;
	}

	/**
	 * Review items
	 *
	 * @param object $order Order.
	 * @param string $option_prefix Option prefix.
	 *
	 * @return object
	 */
	private static function review_items( $order, $option_prefix ) {
		$subproducts  = 0;
		$fields_items = array();
		$index        = 0;
		$index_bund   = 0;
		$has_virtual  = true;
		$tax          = new \WC_Tax();

		$coupons         = $order->get_items( 'coupon' );
		$order_discounts = array();
		foreach ( $coupons as $item_coupon ) {
			$coupon      = new \WC_Coupon( $item_coupon->get_code() );
			$coupon_type = $coupon->get_discount_type();

			$order_discounts[] = array(
				'qty'      => $item_coupon->get_quantity(),
				'type'     => $coupon_type,
				'discount' => (string) $item_coupon->get_discount(),
				'amount'   => 'percent' === $coupon_type ? (float) $coupon->get_amount() : (float) $item_coupon->get_discount(),
				'tax'      => (float) $item_coupon->get_discount_tax(),
			);
		}
		// Order Items.
		foreach ( $order->get_items() as $item_id => $item ) {
			$product    = $item->get_product();
			$product_id = ! empty( $product ) ? $product->get_id() : 0;

			if ( $product && ! $product->is_virtual() ) {
				$has_virtual = false;
			}

			if ( ! empty( $product ) && $product->is_type( 'woosb' ) ) {
				$woosb_ids   = get_post_meta( $item['product_id'], 'woosb_ids', true );
				$woosb_prods = explode( ',', $woosb_ids );

				foreach ( $woosb_prods as $woosb_ids ) {
					$wb_prod    = explode( '/', $woosb_ids );
					$wb_prod_id = $wb_prod[0];
				}
				$subproducts = count( $woosb_prods );

				$fields_items[ $index ] = array(
					'name'     => $item['name'],
					'desc'     => '',
					'units'    => floatval( $item['qty'] ),
					'subtotal' => 0,
					'tax'      => 0,
					'stock'    => $product->get_stock_quantity(),
				);

				// Use Source product ID instead of SKU.
				$prod_key         = '_' . $option_prefix . '_productid';
				$source_productid = get_post_meta( $item['product_id'], $prod_key, true );
				if ( $source_productid ) {
					$fields_items[ $index ]['productId'] = $source_productid;
				} else {
					$fields_items[ $index ]['sku'] = $product->get_sku();
				}
				$index_bund = $index;
				$index++;

				if ( $subproducts > 0 ) {
					$subproducts = --$subproducts;
					$vat_per     = 0;
					if ( floatval( $item['line_total'] ) ) {
						$vat_per = round( ( floatval( $item['line_tax'] ) * 100 ) / ( floatval( $item['line_total'] ) ), 4 );
					}
					$product_cost                            = floatval( $item['line_total'] );
					$fields_items[ $index_bund ]['subtotal'] = $fields_items[ $index_bund ]['subtotal'] + $product_cost;
					$fields_items[ $index_bund ]['tax']      = (float) number_format( $vat_per, 2, '.', '' );
				}
			} else {
				$item_qty   = (int) $item->get_quantity();
				$price_line = $item->get_subtotal() / $item_qty;

				$item_data = array(
					'name'      => $item->get_name(),
					'desc'      => get_the_excerpt( $product_id ),
					'units'     => $item_qty,
					'subtotal'  => (float) number_format( $price_line, 2, '.', '' ),
					'sku'       => ! empty( $product ) ? $product->get_sku() : '',
					'image_url' => get_the_post_thumbnail_url( $product_id, 'post-thumbnail' ),
					'permalink' => get_the_permalink( $product_id ),
				);
				$item_data = array_merge(
					$item_data,
					self::get_taxes( $item, $tax )
				);

				// Discount.
				$line_discount = $item->get_subtotal() - $item->get_total();
				if ( $line_discount > 0 ) {
					$coupon = array_search( (string) $line_discount, array_column( $order_discounts, 'discount' ), true );
					if ( false !== $coupon && 'percent' === $order_discounts[ $coupon ]['type'] ) {
						// Percentage discount: amount is already the percentage value.
						$item_data['discount'] = (float) number_format( (float) $order_discounts[ $coupon ]['amount'], 2, '.', '' );
					} else {
						$item_data['discount'] = (float) number_format( ( $line_discount * 100 ) / $item->get_subtotal(), 2, '.', '' );
					}
				}

				$fields_items[] = $item_data;
				++$index;
			}
		}

		// Shipping Items.
		$shipping_items = $order->get_items( 'shipping' );
		if ( ! empty( $shipping_items ) ) {
			foreach ( $shipping_items as $shipping_item ) {
				$shipping_data = array(
					'name'     => __( 'Shipping:', 'woocommerce-es' ) . ' ' . $shipping_item->get_name(),
					'desc'     => '',
					'units'    => 1,
					'subtotal' => (float) $shipping_item->get_total(),
					'sku'      => 'shipping',
				);
				$shipping_data = array_merge(
					$shipping_data,
					self::get_taxes( $shipping_item, $tax )
				);

				$fields_items[] = $shipping_data;
			}
		}

		// Items Fee.
		$items_fee = $order->get_items( 'fee' );
		if ( ! empty( $items_fee ) ) {
			foreach ( $items_fee as $item_fee ) {
				$fee_data = array(
					'name'     => $item_fee->get_name(),
					'desc'     => '',
					'units'    => 1,
					'subtotal' => (float) $item_fee->get_total(),
					'sku'      => 'fee',
				);
				$fee_data = array_merge(
					$fee_data,
					self::get_taxes( $item_fee, $tax )
				);

				$fields_items[] = $fee_data;
			}
		}

		return array(
			'items'       => $fields_items,
			'has_virtual' => $has_virtual,
		);
	}

	/**
	 * Get tax rate
	 *
	 * - Strategy: "Key Only".
	 * - Gets the key configured in WooCommerce (e.g.: s_ivait22).
	 * - Sends ONLY the 'taxes' array.
	 * - Explicitly REMOVES the 'tax' field to avoid precedence conflicts in Holded.
	 *
	 * @param object $item Item object.
	 * @param object $tax Tax object.
	 *
	 * @return array
	 */
	private static function get_taxes( $item, $tax = null ) {
		$item_taxes = array();

		if ( empty( $tax ) ) {
			$tax = new \WC_Tax();
		}
		
		// Get the taxes applied to the line of the order
		$item_tax_data = $item->get_taxes();
		
		if ( ! empty( $item_tax_data['total'] ) && is_array( $item_tax_data['total'] ) ) {
			$tax_rate_ids = array_keys( $item_tax_data['total'] );

			$tax_rate_id_from_item = $tax_rate_ids[0];
			
			// Get the KEY of text configured in the plugin (Database)
			$tax_key = '';
			if ( class_exists( __NAMESPACE__ . '\\TAXES' ) ) {
				$tax_key = TAXES::get_tax_types_map( $tax_rate_id_from_item );
			}
				
			if ( ! empty( $tax_key ) ) {
				$item_taxes['taxes'] = array( trim( $tax_key ) );
			} else {
				$item_taxes['tax']     = ! empty( $item_tax_data['total'][ $tax_rate_id_from_item ] ) ? (float) number_format( (float) $item_tax_data['total'][ $tax_rate_id_from_item ], 2, '.', '' ) : 0;
			}
		}

		return $item_taxes;
	}

	/**
	 * Gets Billing VAT info from order
	 *
	 * @param object $order Order object to get info.
	 *
	 * @return string
	 */
	public static function get_billing_vat( $order ) {
		$contact_code = '';
		foreach ( CONECOM_VAT_FIELD_SLUGS as $code_label ) {
			// Add underscore prefix for meta fields.
			$meta_key = 'VAT Number' === $code_label ? $code_label : '_' . $code_label;
			$contact_code = $order->get_meta( $meta_key );
			if ( ! empty( $contact_code ) ) {
				break;
			}
		}
		return sanitize_text_field( $contact_code );
	}

	/**
	 * Sanitize strings for AEAT (VeriFactu) submission.
	 *
	 * - Removes diacritics and special characters.
	 * - Allows only whitelisted characters.
	 * - Collapses spaces and trims edges.
	 * - Truncates to the maximum allowed length.
	 *
	 * @param string      $value     Input string (UTF-8).
	 * @param int         $maxLen    Maximum allowed length (default 120).
	 * @param string      $whitelist Regex character class WITHOUT brackets.
	 * @param string|null $fallback  Value to return if empty after sanitization (null = empty string).
	 * @return string
	 */
	public static function clean_special_chars( string $value, int $maxLen = 120, string $whitelist = 'A-Za-z0-9ÑÇñç &\- ', ?string $fallback = null ): string {
		$value = trim( preg_replace( '/\s+/u', ' ', $value ) );
		if ( $value === '' ) {
			return $fallback ?? '';
		}

		$map = [
			'á'=>'a', 'é'=>'e', 'í'=>'i', 'ó'=>'o', 'ú'=>'u', 'ñ'=>'ñ', 'Á'=>'A', 'É'=>'E', 'Í'=>'I', 'Ó'=>'O', 'Ú'=>'U', 'Ñ'=>'Ñ', 'à'=>'a', 'è'=>'e', 'ì'=>'i', 'ò'=>'o', 'ù'=>'u', 'À'=>'A', 'È'=>'E', 'Ì'=>'I', 'Ò'=>'O', 'Ù'=>'U', 'â'=>'a', 'ê'=>'e', 'î'=>'i', 'ô'=>'o', 'û'=>'u', 'Â'=>'A', 'Ê'=>'E', 'Î'=>'I', 'Ô'=>'O', 'Û'=>'U', 'ä'=>'a', 'ë'=>'e', 'ï'=>'i', 'ö'=>'o', 'ü'=>'u', 'Ä'=>'A', 'Ë'=>'E', 'Ï'=>'I', 'Ö'=>'O', 'Ü'=>'U', 'ã'=>'a', 'õ'=>'o', 'Ã'=>'A', 'Õ'=>'O', 'å'=>'a', 'Å'=>'A', 'š'=>'s', 'Š'=>'S', 'ž'=>'z', 'Ž'=>'Z', 'ý'=>'y', 'Ý'=>'Y', 'ÿ'=>'y', 'Ÿ'=>'Y', 'ø'=>'o', 'Ø'=>'O', 'æ'=>'ae', 'Æ'=>'AE', 'œ'=>'oe', 'Œ'=>'OE', 'ß'=>'ss', 'ł'=>'l', 'Ł'=>'L', '@'=>' ', '#'=>' ', '&' => 'Y', 'ğ'=>'g', 'Ğ'=>'G', 'ő'=>'o', 'Ő'=>'O', 'Ė' => 'E', 'ė' => 'e', 'į' => 'i', 'Į' => 'I',
		];
		$ascii = strtr( $value, $map );
		$ascii = strtr( strtoupper( $ascii ), array( 'ñ' => 'Ñ', 'ç' => 'Ç' ) );

		// Replace non-whitelisted characters with spaces.
		$ascii = preg_replace( '/[^' . $whitelist . ']/u', ' ', $ascii );

		// Collapse multiple spaces and clean up
		$ascii = preg_replace('/\s+/', ' ', $ascii);
		$ascii = preg_replace('/-{2,}/', '-', $ascii);
		$ascii = trim($ascii, " \t\n\r\0\x0B-");

		if ($maxLen > 0 && strlen($ascii) > $maxLen) {
				$ascii = substr($ascii, 0, $maxLen);
				$ascii = rtrim($ascii);
		}

		if ($ascii === '' && $fallback !== null) {
				return $fallback;
		}

		return $ascii;
	}
}
