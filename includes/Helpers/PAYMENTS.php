<?php
/**
 * Payment Methods Helper
 *
 * @package    WordPress
 * @author     David Perez <david@closemarketing.es>
 * @copyright  2022 Closemarketing
 * @version    1.0
 */

namespace CLOSE\ConnectEcommerce\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Payment methods helper.
 *
 * @since 3.2.2
 */
class PAYMENTS {
	/**
	 * Option name for payment method mappings.
	 *
	 * @var string
	 */
	private static $option_name = 'connect_ecommerce_payment_methods';

	/**
	 * Get payment method mappings for a connector.
	 *
	 * @param string $connector_slug Connector slug.
	 * @return array Array with 'payment_methods' and 'treasury_accounts' keys.
	 */
	public static function get_payment_method_mappings( $connector_slug ) {
		if ( '' === $connector_slug ) {
			return array(
				'payment_methods'   => array(),
				'treasury_accounts' => array(),
			);
		}

		$stored = get_option( self::$option_name, array() );

		if ( ! is_array( $stored ) ) {
			return array(
				'payment_methods'   => array(),
				'treasury_accounts' => array(),
			);
		}

		if ( ! isset( $stored[ $connector_slug ] ) || ! is_array( $stored[ $connector_slug ] ) ) {
			return array(
				'payment_methods'   => array(),
				'treasury_accounts' => array(),
			);
		}

		$payment_methods    = array();
		$treasury_accounts  = array();

		foreach ( $stored[ $connector_slug ] as $gateway_id => $mapping ) {
			if ( ! is_scalar( $gateway_id ) ) {
				continue;
			}

			$sanitized_gateway_id = sanitize_text_field( (string) $gateway_id );
			if ( '' === $sanitized_gateway_id ) {
				continue;
			}

			if ( is_array( $mapping ) ) {
				if ( isset( $mapping['method'] ) && is_scalar( $mapping['method'] ) ) {
					$sanitized_method = sanitize_text_field( (string) $mapping['method'] );
					if ( '' !== $sanitized_method ) {
						$payment_methods[ $sanitized_gateway_id ] = $sanitized_method;
					}
				}
				if ( isset( $mapping['treasury'] ) && is_scalar( $mapping['treasury'] ) ) {
					$sanitized_treasury = sanitize_text_field( (string) $mapping['treasury'] );
					if ( '' !== $sanitized_treasury ) {
						$treasury_accounts[ $sanitized_gateway_id ] = $sanitized_treasury;
					}
				}
			} elseif ( is_scalar( $mapping ) ) {
				// Backward compatibility: if mapping is scalar, treat as payment method.
				$sanitized_method = sanitize_text_field( (string) $mapping );
				if ( '' !== $sanitized_method ) {
					$payment_methods[ $sanitized_gateway_id ] = $sanitized_method;
				}
			}
		}

		return array(
			'payment_methods'   => $payment_methods,
			'treasury_accounts' => $treasury_accounts,
		);
	}
}
