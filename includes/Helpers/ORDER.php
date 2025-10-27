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
	 *
	 * @return array
	 */
	public static function create_invoice( $settings, $order_id, $meta_key_order, $option_prefix, $api_erp, $force = false ) {
		$order          = wc_get_order( $order_id );
		$order_total    = (float) $order->get_total();
		$ec_invoice_id  = $order->get_meta( $meta_key_order );
		$freeorder      = isset( $settings['freeorder'] ) ? $settings['freeorder'] : 'no';
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

				$doc_id     = 'error' === $result['status'] ? '' : $result['document_id'];
				$invoice_id = isset( $result['invoice_id'] ) ? $result['invoice_id'] : $invoice_id;
				$order->update_meta_data( $meta_key_order, $invoice_id );
				$order->update_meta_data( '_' . $option_prefix . '_doc_id', $doc_id );
				$order->update_meta_data( '_' . $option_prefix . '_doc_type', $doctype );
				$order->save();

				$order_msg = __( 'Order synced correctly with ERP, ID: ', 'woocommerce-es' ) . $invoice_id;

				$order->add_order_note( $order_msg );
			} catch ( \Exception $e ) {
				$result = array(
					'status'  => 'error',
					'message' => $e->getMessage(),
				);
				// Send alert for order error.
				ALERT::send_order_error_alert( $order_id, $e->getMessage() );
			}
		} else {
			$result = array(
				'status'  => 'error',
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
	 * Create refund invoice
	 *
	 * @param array  $settings Settings data.
	 * @param int    $refund_id Refund id.
	 * @param array  $args Arguments.
	 * @param string $option_prefix Option prefix.
	 * @param object $api_erp API ERP.
	 *
	 * @return array
	 */
	public static function create_refund_invoice( $settings, $refund_id, $args, $option_prefix, $api_erp ) {
		if ( ! method_exists( $api_erp, 'create_refund' ) ) {
			return array(
				'status'  => 'error',
				'message' => __( 'API ERP does not support create refund', 'woocommerce-es' ),
			);
		}
		$refund_data = self::generate_order_refund_data( $settings, $refund_id, $args, $option_prefix );
		$result      = $api_erp->create_refund( $settings, $refund_data );

		return $result;
	}

	/**
	 * Generate data for Order ERP
	 *
	 * @param object $setttings Settings data.
	 * @param object $order Order data from WooCommerce.
	 * @param string $option_prefix Option prefix.
	 *
	 * @return array
	 */
	public static function generate_order_data( $setttings, $order, $option_prefix ) {
		$order_label_id = self::generate_label_id( $order->get_id() );
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
		$billing_address      = $order->get_billing_address_1() . ',' . $order->get_billing_address_2();
		$billing_city         = $order->get_billing_city();
		$billing_postcode     = $order->get_billing_postcode();
		$billing_state_code   = $order->get_billing_state();
		$billing_country_code = $order->get_billing_country();

		// Clean special chars.
		if ( isset( $setttings['cleanchars'] ) && 'on' === $setttings['cleanchars'] ) {
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

		$contact_code = self::get_billing_vat( $order );

		// Order Reference.
		$prefix = self::generate_prefix();

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
			'pmtype'                 => null,
			'items'                  => array(),
			'approveDoc'             => false,
		);

		// Approve document.
		$approve_document = isset( $setttings['approve_document'] ) ? $setttings['approve_document'] : 'no';
		if ( 'yes' === $approve_document ) {
			$order_data['approveDoc'] = true;
		}

		// DesignID.
		$design_id = isset( $setttings['design_id'] ) ? $setttings['design_id'] : '';
		if ( $design_id ) {
			$order_data['designId'] = $design_id;
		}

		// Series ID.
		$series_number = isset( $setttings['series'] ) ? $setttings['series'] : '';
		if ( ! empty( $series_number ) && 'default' !== $series_number ) {
			$order_data['numSerieId'] = $series_number;
		}

		// Visitor Key.
		$visitor_key = isset( $setttings['clientify_vk'] ) ? $setttings['clientify_vk'] : '';
		if ( ! empty( $visitor_key ) ) {
			$order_data['clientify_vk'] = $visitor_key;
		}

		// Payment method.
		$wc_payment_method = $order->get_payment_method();
		if ( ! empty( $wc_payment_method ) ) {
			$order_data['paymentMethod'] = $wc_payment_method;
		}
		$settings_prod_mergevars = isset( $setttings['prod_mergevars'] ) ? $setttings['prod_mergevars'] : '';
		if ( ! empty( $settings_prod_mergevars ) ) {
			foreach ( $settings_prod_mergevars as $key => $value ) {
				if ( false === strpos( $key, 'paymentmethods|' ) ) {
					continue;
				}
				$payment_method     = explode( '|', $key );
				$payment_method_woo = explode( '|', $value );
				if ( $payment_method_woo[1] === $wc_payment_method ) {
					$order_data['paymentMethodId'] = $payment_method[1];
				}
			}
		}

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
					$fields_items[ $index_bund ]['tax']      = round( $vat_per, 0 );
				}
			} else {
				$item_qty   = (int) $item->get_quantity();
				$price_line = $item->get_subtotal() / $item_qty;

				// Taxes.
				$item_tax  = (float) $item->get_total_tax();
				$taxes     = $tax->get_rates( $product->get_tax_class() );
				$rates     = array_shift( $taxes );
				$item_rate = ! empty( $item_tax ) ? floor( array_shift( $rates ) ) : 0;

				$item_data = array(
					'name'      => $item->get_name(),
					'desc'      => get_the_excerpt( $product_id ),
					'units'     => $item_qty,
					'subtotal'  => (float) $price_line,
					'tax'       => $item_rate,
					'sku'       => ! empty( $product ) ? $product->get_sku() : '',
					'image_url' => get_the_post_thumbnail_url( $product_id, 'post-thumbnail' ),
					'permalink' => get_the_permalink( $product_id ),
				);

				// Discount.
				$line_discount = $item->get_subtotal() - $item->get_total();
				if ( $line_discount > 0 ) {
					$coupon = array_search( (string) $line_discount, array_column( $order_discounts, 'discount' ), true );
					if ( false !== $coupon ) {
						$item_data['discount'] = 'percent' !== $order_discounts[ $coupon ]['type'] ? ( $item->get_subtotal() * $order_discounts[ $coupon ]['amount'] ) / 100 : $order_discounts[ $coupon ]['amount'];
					} else {
						$item_data['discount'] = round( ( $line_discount * 100 ) / $item->get_subtotal(), 0 );
					}
				}

				$fields_items[] = $item_data;
				$index++;
			}
		}

		// Shipping Items.
		$shipping_items = $order->get_items( 'shipping' );
		if ( ! empty( $shipping_items ) ) {
			foreach ( $shipping_items as $shipping_item ) {
				// Taxes.
				$item_tax  = (float) $shipping_item->get_total_tax();
				$taxes     = $tax->get_rates( $shipping_item->get_tax_class() );
				$tax_rates = array_shift( $taxes );
				$item_rate = ! empty( $item_tax ) && is_array( $item_tax ) ? floor( array_shift( $tax_rates ) ) : 0;

				$fields_items[] = array(
					'name'     => __( 'Shipping:', 'woocommerce-es' ) . ' ' . $shipping_item->get_name(),
					'desc'     => '',
					'units'    => 1,
					'subtotal' => (float) $shipping_item->get_total(),
					'tax'      => $item_rate,
					'sku'      => 'shipping',
				);
			}
		}

		// Items Fee.
		$items_fee = $order->get_items( 'fee' );
		if ( ! empty( $items_fee ) ) {
			foreach ( $items_fee as $item_fee ) {
				// Taxes.
				$item_tax  = (float) $item_fee->get_total_tax();
				$taxes     = $tax->get_rates( $item_fee->get_tax_class() );
				$tax_rates = array_shift( $taxes );
				$item_rate = ! empty( $item_tax ) ? floor( array_shift( $tax_rates ) ) : 0;

				$fields_items[] = array(
					'name'     => $item_fee->get_name(),
					'desc'     => '',
					'units'    => 1,
					'subtotal' => (float) $item_fee->get_total(),
					'tax'      => $item_rate,
					'sku'      => 'fee',
				);
			}
		}

		return [
			'items'      => $fields_items,
			'has_virtual' => $has_virtual,
		];
	}

	/**
	 * Generate order refund data
	 *
	 * @param array  $settings Settings plugin.
	 * @param string $refund_id Refund id.
	 * @param array  $args Args.
	 *
	 * @return array
	 */
	public static function generate_order_refund_data( $settings, $refund_id, $args ) {
		$refund         = wc_get_order( $refund_id );
		$order          = wc_get_order( $refund->get_parent_id() );
		$order_label_id = self::generate_label_id( $order->get_id() );
		$prefix         = self::generate_prefix();

		$refund_data = array(
			'contactCode'          => self::get_billing_vat( $order ),
			'woocommerceOrderId'   => self::generate_label_id( $order->get_id() ),
			'woocommerceReference' => $prefix . $order_label_id,
			'date'                 => $refund->get_date_created(),
		);

		$refund_data['items'] = self::generate_refund_items( $order, $refund, $args );

		return $refund_data;
	}

	/**
	 * Generate refund items
	 *
	 * @param object $order Order object.
	 * @param object $refund Refund object.
	 * @param array  $args Refund args.
	 *
	 * @return array
	 */
	public static function generate_refund_items( $order, $refund, $args ) {
		$items = array();
		foreach ( $args['line_items'] as $item_id => $item ) {
			if ( 0 === (int) $item['qty'] ) {
				continue;
			}
			$order_item = $order->get_item( $item_id );
			if ( ! $order_item ) {
				continue;
			}

			$product = $order_item->get_product();
			if ( ! $product ) {
				continue;
			}

			$items[] = array(
				'name'     => $product->get_name(),
				'desc'     => $product->get_description(),
				'sku'      => $product->get_sku(),
				'units'    => $item['qty'],
				'subtotal' => $item['refund_total'],
				'tax'      => isset( $item['refund_tax'][1] ) ? $item['refund_tax'][1] : 0,
			);
		}
		return $items;
	}

	/**
	 * Gets Billing VAT info from order
	 *
	 * @param object $order Order object to get info.
	 *
	 * @return string
	 */
	private static function get_billing_vat( $order ) {
		$code_labels = array(
			'_billing_vat',
			'_billing_nif',
			'_billing_vat_number',
			'VAT Number', // Support to SIMBA Hosting.
		);
		$contact_code = '';
		foreach ( $code_labels as $code_label ) {
			$contact_code = $order->get_meta( $code_label );
			if ( ! empty( $contact_code ) ) {
				break;
			}
		}
		return $contact_code;
	}

	/**
	 * Generate reference
	 *
	 * @param int $order_id Order id.
	 *
	 * @return int
	 */
	private static function generate_label_id( $order_id ) {
		return is_multisite() ? ( get_current_blog_id() * 100000000 ) + $order_id : $order_id;
	}

	/**
	 * Generate prefix
	 *
	 * @return string
	 */
	private static function generate_prefix() {
		$base_domain = basename( sanitize_text_field( $_SERVER['HTTP_HOST'] ) );
		$base_domain = str_replace( 'www.', '', $base_domain );
		return $base_domain . '_';
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
			'á'=>'a', 'é'=>'e', 'í'=>'i', 'ó'=>'o', 'ú'=>'u', 'ñ'=>'ñ', 'Á'=>'A', 'É'=>'E', 'Í'=>'I', 'Ó'=>'O', 'Ú'=>'U', 'Ñ'=>'Ñ', 'à'=>'a', 'è'=>'e', 'ì'=>'i', 'ò'=>'o', 'ù'=>'u', 'À'=>'A', 'È'=>'E', 'Ì'=>'I', 'Ò'=>'O', 'Ù'=>'U', 'â'=>'a', 'ê'=>'e', 'î'=>'i', 'ô'=>'o', 'û'=>'u', 'Â'=>'A', 'Ê'=>'E', 'Î'=>'I', 'Ô'=>'O', 'Û'=>'U', 'ä'=>'a', 'ë'=>'e', 'ï'=>'i', 'ö'=>'o', 'ü'=>'u', 'Ä'=>'A', 'Ë'=>'E', 'Ï'=>'I', 'Ö'=>'O', 'Ü'=>'U', 'ã'=>'a', 'õ'=>'o', 'Ã'=>'A', 'Õ'=>'O', 'š'=>'s', 'Š'=>'S', 'ž'=>'z', 'Ž'=>'Z', 'ý'=>'y', 'Ý'=>'Y', 'ÿ'=>'y', 'Ÿ'=>'Y', 'ø'=>'o', 'Ø'=>'O', 'æ'=>'ae', 'Æ'=>'AE', 'œ'=>'oe', 'Œ'=>'OE', 'ß'=>'ss', '@'=>' ', '#'=>' ', '&' => 'Y', 'ğ'=>'g', 'Ğ'=>'G', 
		];
		$ascii = strtr( $value, $map );

		// Replace non-whitelisted characters with spaces
		$ascii = preg_replace('/[^' . $whitelist . ']/', ' ', $ascii);
		
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
