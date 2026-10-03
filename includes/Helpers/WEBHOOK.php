<?php
/**
 * Product webhooks
 *
 * Receives product change notifications from ERPs/CRMs, translates them into
 * the universal product item through the connector and syncs the product.
 *
 * @package    WordPress
 * @author     Closetechnology
 * @copyright  2026 Closemarketing
 * @version    1.0
 */

namespace CLOSE\ConnectEcommerce\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Product webhooks.
 *
 * @since 3.5.1
 */
class WEBHOOK {
	/**
	 * REST namespace.
	 */
	const REST_NAMESPACE = 'conecom/v1';

	/**
	 * REST route base. The connector ID is appended.
	 */
	const REST_ROUTE = '/webhooks/products';

	/**
	 * Option that stores the secret token of each connector.
	 */
	const OPTION_TOKENS = 'connect_ecommerce_webhook_tokens';

	/**
	 * Option that stores the signing secret given by the ERP for each connector.
	 */
	const OPTION_SECRETS = 'connect_ecommerce_webhook_secrets';

	/**
	 * Option that stores the latest webhook executions.
	 */
	const OPTION_LOGS = 'connect_ecommerce_webhook_logs';

	/**
	 * Maximum number of log entries kept per connector.
	 */
	const LOG_LIMIT = 50;

	/**
	 * Product meta storing the connector that synced it through a webhook.
	 */
	const META_CONNECTOR = 'connect_ecommerce_connector';

	/**
	 * Connector definitions (conecom_options_plugin).
	 *
	 * @var array
	 */
	private static $options = array();

	/**
	 * Registers the webhook endpoint.
	 *
	 * @param array $options Connector definitions.
	 * @return void
	 */
	public static function init( $options ) {
		self::$options = is_array( $options ) ? $options : array();
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( 'update_option_connect_ecommerce', array( __CLASS__, 'cleanup_removed_connectors' ), 10, 2 );
	}

	/**
	 * Revokes the webhook credentials and logs of connectors removed from the settings.
	 *
	 * Tokens and secrets live outside connect_ecommerce, so a connector added later
	 * with the same instance ID must not inherit the old credentials.
	 *
	 * @param mixed $old_value Previous connect_ecommerce value.
	 * @param mixed $new_value New connect_ecommerce value.
	 * @return void
	 */
	public static function cleanup_removed_connectors( $old_value, $new_value ) {
		$old_ids = array_keys( (array) ( $old_value['connectors_meta'] ?? array() ) );
		$new_ids = array_keys( (array) ( $new_value['connectors_meta'] ?? array() ) );
		foreach ( array_diff( $old_ids, $new_ids ) as $connector_id ) {
			self::forget_connector( (string) $connector_id );
		}
	}

	/**
	 * Deletes the token, signing secret and logs of a connector.
	 *
	 * @param string $connector_id Connector ID.
	 * @return void
	 */
	public static function forget_connector( $connector_id ) {
		$connector_id = sanitize_key( $connector_id );
		foreach ( array( self::OPTION_TOKENS, self::OPTION_SECRETS ) as $option ) {
			$values = get_option( $option, array() );
			if ( is_array( $values ) && isset( $values[ $connector_id ] ) ) {
				unset( $values[ $connector_id ] );
				update_option( $option, $values, false );
			}
		}

		$logs = get_option( self::OPTION_LOGS, array() );
		if ( is_array( $logs ) ) {
			$kept = array_values(
				array_filter(
					$logs,
					function ( $log ) use ( $connector_id ) {
						return ( $log['connector_id'] ?? '' ) !== $connector_id;
					}
				)
			);
			if ( count( $kept ) !== count( $logs ) ) {
				update_option( self::OPTION_LOGS, $kept, false );
			}
		}
	}

	/**
	 * Registers the REST routes.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE . '/(?P<connector_id>[a-z0-9_-]+)',
			array(
				'methods'             => array( 'GET', 'POST', 'PUT' ),
				'callback'            => array( __CLASS__, 'handle_request' ),
				'permission_callback' => array( __CLASS__, 'permission_check' ),
				'args'                => array(
					'connector_id' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);
	}

	/**
	 * Gets the secret token of a connector, creating it when missing.
	 *
	 * @param string $connector_id Connector ID.
	 * @param bool   $create       Create the token when it does not exist.
	 * @return string
	 */
	public static function get_token( $connector_id, $create = true ) {
		$connector_id = sanitize_key( $connector_id );
		$tokens       = get_option( self::OPTION_TOKENS, array() );
		$tokens       = is_array( $tokens ) ? $tokens : array();

		if ( empty( $tokens[ $connector_id ] ) && $create && '' !== $connector_id ) {
			$tokens[ $connector_id ] = wp_generate_password( 32, false, false );
			update_option( self::OPTION_TOKENS, $tokens, false );
		}

		return $tokens[ $connector_id ] ?? '';
	}

	/**
	 * Regenerates the secret token of a connector. The previous URL stops working.
	 *
	 * @param string $connector_id Connector ID.
	 * @return string New token.
	 */
	public static function regenerate_token( $connector_id ) {
		$connector_id = sanitize_key( $connector_id );
		$tokens       = get_option( self::OPTION_TOKENS, array() );
		$tokens       = is_array( $tokens ) ? $tokens : array();

		$tokens[ $connector_id ] = wp_generate_password( 32, false, false );
		update_option( self::OPTION_TOKENS, $tokens, false );

		return $tokens[ $connector_id ];
	}

	/**
	 * Gets the signing secret given by the ERP (e.g. Holded "whsec_...").
	 *
	 * @param string $connector_id Connector ID.
	 * @return string Empty when not configured.
	 */
	public static function get_signing_secret( $connector_id ) {
		$secrets = get_option( self::OPTION_SECRETS, array() );
		$secrets = is_array( $secrets ) ? $secrets : array();

		return (string) ( $secrets[ sanitize_key( $connector_id ) ] ?? '' );
	}

	/**
	 * Saves the signing secret given by the ERP. An empty value removes it.
	 *
	 * @param string $connector_id Connector ID.
	 * @param string $secret       Signing secret.
	 * @return void
	 */
	public static function save_signing_secret( $connector_id, $secret ) {
		$connector_id = sanitize_key( $connector_id );
		$secrets      = get_option( self::OPTION_SECRETS, array() );
		$secrets      = is_array( $secrets ) ? $secrets : array();
		// Opaque credential: keep it verbatim (sanitize_text_field would strip %xx sequences).
		$secret = trim( preg_replace( '/[\x00-\x1F\x7F]/', '', (string) $secret ) );

		if ( '' === $secret ) {
			unset( $secrets[ $connector_id ] );
		} else {
			$secrets[ $connector_id ] = $secret;
		}
		update_option( self::OPTION_SECRETS, $secrets, false );
	}

	/**
	 * Checks whether a connector authenticates webhooks with the ERP signature.
	 *
	 * True when the connector implements verify_webhook() and a signing secret
	 * is configured. Then the signature is mandatory and the URL token optional.
	 *
	 * @param string      $connector_id Connector ID.
	 * @param object|null $connapi_erp  Connector API object, resolved when null.
	 * @return bool
	 */
	public static function uses_signature( $connector_id, $connapi_erp = null ) {
		if ( '' === self::get_signing_secret( $connector_id ) ) {
			return false;
		}
		if ( null === $connapi_erp ) {
			$connector   = HELPER::get_connector_by_id( sanitize_key( $connector_id ), self::$options );
			$connapi_erp = $connector['connapi_erp'] ?? null;
		}

		return HELPER::connector_supports( $connapi_erp, 'verify_webhook' );
	}

	/**
	 * Gets the webhook URL of a connector.
	 *
	 * The token is left out when the connector authenticates with the ERP signature.
	 *
	 * @param string      $connector_id Connector ID.
	 * @param object|null $connapi_erp  Connector API object, resolved when null.
	 * @return string
	 */
	public static function get_webhook_url( $connector_id, $connapi_erp = null ) {
		$connector_id = sanitize_key( $connector_id );
		$url          = rest_url( self::REST_NAMESPACE . self::REST_ROUTE . '/' . $connector_id );
		if ( self::uses_signature( $connector_id, $connapi_erp ) ) {
			return $url;
		}

		return add_query_arg( 'token', self::get_token( $connector_id ), $url );
	}

	/**
	 * Authorizes the request.
	 *
	 * Connectors with a signing secret are authenticated by the ERP signature in
	 * process(), so the token is optional for them. The rest need the secret
	 * token (query parameter "token" or header "X-Conecom-Token").
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public static function permission_check( $request ) {
		$connector_id = sanitize_key( (string) $request->get_param( 'connector_id' ) );
		$connector    = HELPER::get_connector_by_id( $connector_id, self::$options );
		$connapi_erp  = $connector['connapi_erp'] ?? null;

		// Signed ERPs are authenticated here, before anything is processed or logged.
		if ( self::uses_signature( $connector_id, $connapi_erp ) ) {
			if ( self::is_signature_valid( $connector_id, $connapi_erp, (string) $request->get_body(), self::get_headers( $request ) ) ) {
				return true;
			}
			return new \WP_Error(
				'conecom_webhook_forbidden',
				__( 'Webhook signature could not be verified.', 'woocommerce-es' ),
				array( 'status' => 401 )
			);
		}

		$expected = self::get_token( $connector_id, false );
		$received = (string) $request->get_header( 'x_conecom_token' );
		if ( '' === $received ) {
			$query    = $request->get_query_params();
			$received = isset( $query['token'] ) ? (string) $query['token'] : '';
		}

		if ( '' === $expected || '' === $received || ! hash_equals( $expected, $received ) ) {
			return new \WP_Error(
				'conecom_webhook_forbidden',
				__( 'Invalid webhook token.', 'woocommerce-es' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * Checks the ERP signature with the connector and its signing secret.
	 *
	 * @param string $connector_id Connector ID.
	 * @param object $connapi_erp  Connector API object.
	 * @param string $raw_body     Raw request body.
	 * @param array  $headers      Request headers.
	 * @return bool
	 */
	private static function is_signature_valid( $connector_id, $connapi_erp, $raw_body, $headers ) {
		$secret = self::get_signing_secret( $connector_id );

		return '' !== $secret && (bool) $connapi_erp->verify_webhook( $raw_body, $headers, $secret );
	}

	/**
	 * Result for a rejected signature (not logged).
	 *
	 * @return array
	 */
	private static function signature_error() {
		return array(
			'status'  => 'error',
			'code'    => 401,
			'message' => __( 'Webhook signature could not be verified.', 'woocommerce-es' ),
		);
	}

	/**
	 * Handles a webhook request.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function handle_request( $request ) {
		// Some ERPs close the connection early (Odoo waits 1 second): finish the sync anyway.
		ignore_user_abort( true );

		// The signature only covers the body: never mix unsigned query parameters into a signed payload.
		$connector_id = sanitize_key( (string) $request->get_param( 'connector_id' ) );
		$connector    = HELPER::get_connector_by_id( $connector_id, self::$options );
		$body_only    = self::uses_signature( $connector_id, $connector['connapi_erp'] ?? null );
		$payload      = self::get_payload( $request, $body_only );
		$headers      = self::get_headers( $request );

		$result = self::process( $connector, $payload, $headers, (string) $request->get_body() );
		$status = self::get_http_status( $result );
		unset( $result['code'] );

		return new \WP_REST_Response( $result, $status );
	}

	/**
	 * Builds the payload: JSON or form body merged with the query parameters.
	 *
	 * @param \WP_REST_Request $request   Request.
	 * @param bool             $body_only Only the body (signed requests: the query string is not authenticated).
	 * @return array
	 */
	public static function get_payload( $request, $body_only = false ) {
		$query = $body_only ? array() : $request->get_query_params();
		$body  = $request->get_json_params();
		if ( empty( $body ) || ! is_array( $body ) ) {
			$body = $request->get_body_params();
		}
		$payload = array_merge( is_array( $query ) ? $query : array(), is_array( $body ) ? $body : array() );
		unset( $payload['token'], $payload['connector_id'] );

		return $payload;
	}

	/**
	 * Gets the request headers as a flat array.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array
	 */
	private static function get_headers( $request ) {
		$headers = array();
		foreach ( $request->get_headers() as $key => $values ) {
			$headers[ $key ] = is_array( $values ) ? implode( ',', $values ) : (string) $values;
		}
		unset( $headers['x_conecom_token'] );

		return $headers;
	}

	/**
	 * Processes a webhook for a connector.
	 *
	 * @param array|null $connector Connector context from HELPER::get_connector_by_id().
	 * @param array      $payload   Webhook payload.
	 * @param array      $headers   Request headers.
	 * @param string     $raw_body  Raw request body.
	 * @return array{status: string, message: string, code?: int, action?: string, id?: string, post_id?: int, source?: string}
	 */
	public static function process( $connector, $payload, $headers = array(), $raw_body = '' ) {
		$connector_id = $connector['id'] ?? '';
		$connapi_erp  = $connector['connapi_erp'] ?? null;

		if ( empty( $connector ) || empty( $connapi_erp ) ) {
			return self::finish(
				$connector_id,
				array(
					'status'  => 'error',
					'code'    => 404,
					'message' => __( 'Connector not found or not configured.', 'woocommerce-es' ),
				)
			);
		}

		$meta = $connector['meta'] ?? array();
		if ( 'active' !== ( $meta['status'] ?? 'active' ) || ! HELPER::is_workflow_enabled_for_connector( $meta, 'products' ) ) {
			return self::finish(
				$connector_id,
				array(
					'status'  => 'ignored',
					'message' => __( 'Product synchronization is disabled for this connector.', 'woocommerce-es' ),
				)
			);
		}

		// Signature mode only (token fallback otherwise). Rejected requests are not logged,
		// so forged deliveries cannot evict the legitimate execution history.
		if ( self::uses_signature( $connector_id, $connapi_erp ) && ! self::is_signature_valid( $connector_id, $connapi_erp, $raw_body, $headers ) ) {
			return self::signature_error();
		}

		$parsed = self::parse_payload( $connector, $payload, $headers );
		if ( isset( $parsed['status'] ) && 'error' === $parsed['status'] ) {
			return self::finish(
				$connector_id,
				array(
					'status'  => 'error',
					'code'    => 400,
					'message' => $parsed['message'] ?? __( 'Webhook payload could not be parsed.', 'woocommerce-es' ),
				)
			);
		}

		$action    = $parsed['action'] ?? 'upsert';
		$remote_id = isset( $parsed['id'] ) ? (string) $parsed['id'] : '';
		$base      = array(
			'action' => $action,
			'id'     => $remote_id,
		);

		if ( 'ignore' === $action ) {
			return self::finish(
				$connector_id,
				$base + array(
					'status'  => 'ignored',
					'message' => $parsed['message'] ?? __( 'Event ignored by the connector.', 'woocommerce-es' ),
				)
			);
		}

		if ( '' === $remote_id ) {
			return self::finish(
				$connector_id,
				$base + array(
					'status'  => 'error',
					'code'    => 400,
					'message' => __( 'Product ID not found in the webhook payload.', 'woocommerce-es' ),
				)
			);
		}

		if ( 'delete' === $action ) {
			return self::finish( $connector_id, $base + self::delete_product( $connector, $remote_id, $parsed['item'] ?? array() ) );
		}

		// Use the translated item when it is complete; otherwise ask the API (second request).
		$item   = isset( $parsed['item'] ) && is_array( $parsed['item'] ) ? $parsed['item'] : array();
		$source = 'payload';
		if ( ! self::is_item_complete( $item, $parsed['complete'] ?? null ) ) {
			$source = 'api';
			$item   = $connapi_erp->get_products( $remote_id );
			if ( empty( $item ) || ( isset( $item['status'] ) && 'error' === $item['status'] ) ) {
				return self::finish(
					$connector_id,
					$base + array(
						'status'  => 'error',
						'source'  => $source,
						'message' => __( 'Error getting product from the API', 'woocommerce-es' ) . ( ! empty( $item['message'] ) ? ': ' . $item['message'] : '' ),
					)
				);
			}
		}

		// Products created without SKU yet (e.g. Holded product.create) wait for the update that sets it.
		$kind = $item['kind'] ?? 'simple';
		if ( empty( $item['sku'] ) && empty( $item['variants'] ) && 'variants' !== $kind && 'variable' !== $kind ) {
			return self::finish(
				$connector_id,
				$base + array(
					'status'  => 'ignored',
					'source'  => $source,
					'message' => __( 'Product without SKU. It will be synced when the ERP sends it with a SKU.', 'woocommerce-es' ),
				)
			);
		}

		/**
		 * Filters the universal product item before it is synced from a webhook.
		 *
		 * @param array  $item         Universal product item.
		 * @param string $source       'payload' when it comes from the webhook, 'api' when it was fetched.
		 * @param array  $connector    Connector context.
		 * @param array  $payload      Raw webhook payload.
		 */
		$item = apply_filters( 'conecom_webhook_product_item', self::sanitize_item( $item ), $source, $connector, $payload );

		$post_id = self::find_post_id( $connector, $remote_id );

		// The sync falls back to a global SKU lookup: never let it take another connector's product.
		$sku_owner = $post_id ? '' : self::get_sku_owner( $item );
		if ( '' !== $sku_owner && $sku_owner !== $connector_id ) {
			return self::finish(
				$connector_id,
				$base + array(
					'status'  => 'error',
					'source'  => $source,
					/* translators: %s: connector ID owning the product. */
					'message' => sprintf( __( 'A product with this SKU belongs to the connector "%s". Not synced.', 'woocommerce-es' ), $sku_owner ),
				)
			);
		}

		$result = PROD::sync_product_item( $connector['settings'] ?? array(), $item, $connapi_erp, false, $post_id );

		// Remember which connector owns the product, so remote-ID lookups stay scoped.
		$synced = ! empty( $result['post_id'] ) && 'error' !== ( $result['status'] ?? '' ) && ! PROD::filter_product( $connector['settings'] ?? array(), $item );
		if ( $synced && '' !== $connector_id ) {
			update_post_meta( (int) $result['post_id'], self::META_CONNECTOR, $connector_id );
		}

		return self::finish(
			$connector_id,
			$base + array(
				'status'  => 'error' === ( $result['status'] ?? '' ) ? 'error' : 'ok',
				'source'  => $source,
				'post_id' => (int) ( $result['post_id'] ?? 0 ),
				'message' => wp_strip_all_tags( (string) ( $result['message'] ?? '' ) ),
			)
		);
	}

	/**
	 * Translates the payload through the connector, or with the generic parser.
	 *
	 * @param array $connector Connector context.
	 * @param array $payload   Webhook payload.
	 * @param array $headers   Request headers.
	 * @return array
	 */
	public static function parse_payload( $connector, $payload, $headers = array() ) {
		$connapi_erp = $connector['connapi_erp'] ?? null;
		$payload     = is_array( $payload ) ? $payload : array();

		if ( HELPER::connector_supports( $connapi_erp, 'parse_webhook_product' ) ) {
			$parsed = $connapi_erp->parse_webhook_product( $payload, $headers );
		} else {
			$parsed = array(
				'action'   => self::detect_action( $payload, $headers ),
				'id'       => self::extract_product_id( $payload ),
				'complete' => false,
			);
		}

		/**
		 * Filters the parsed webhook request, so an ERP with a custom payload can be supported.
		 *
		 * @param array $parsed    Parsed request: action, id, item, complete.
		 * @param array $payload   Raw webhook payload.
		 * @param array $headers   Request headers.
		 * @param array $connector Connector context.
		 */
		$parsed = apply_filters( 'conecom_webhook_parse_request', $parsed, $payload, $headers, $connector );

		return is_array( $parsed ) ? $parsed : array();
	}

	/**
	 * Verifies an HMAC signature header against the raw body.
	 *
	 * Helper for connectors implementing verify_webhook(). Accepts the value
	 * with or without the algorithm prefix, e.g. Holded's
	 * "X-Holded-Webhook-Signature: sha256=<hex>".
	 *
	 * @param string $raw_body  Raw request body.
	 * @param string $signature Signature header value.
	 * @param string $secret    Shared secret.
	 * @param string $algo      Hash algorithm.
	 * @return bool
	 */
	public static function verify_hmac_signature( $raw_body, $signature, $secret, $algo = 'sha256' ) {
		$signature = trim( (string) $signature );
		if ( '' === $signature || '' === (string) $secret ) {
			return false;
		}
		if ( 0 === stripos( $signature, $algo . '=' ) ) {
			$signature = substr( $signature, strlen( $algo ) + 1 );
		}
		$expected = hash_hmac( $algo, (string) $raw_body, (string) $secret );

		return hash_equals( $expected, strtolower( $signature ) );
	}

	/**
	 * Detects the generic event action from the headers or the payload.
	 *
	 * A product is considered deleted when an event header (e.g. Holded's
	 * "X-Holded-Webhook-Event: product.delete") or the payload "event" key
	 * mentions a deletion, or when the payload carries a deletion date
	 * ("deletedAt" / "deleted_at").
	 *
	 * @param array $payload Webhook payload.
	 * @param array $headers Request headers, keys lowercased with underscores.
	 * @return string 'delete' or 'upsert'.
	 */
	public static function detect_action( $payload, $headers = array() ) {
		$events = array();
		foreach ( (array) $headers as $key => $value ) {
			if ( '_event' === substr( (string) $key, -6 ) ) {
				$events[] = (string) $value;
			}
		}
		foreach ( array( 'event', 'action', 'type' ) as $key ) {
			if ( isset( $payload[ $key ] ) && is_string( $payload[ $key ] ) ) {
				$events[] = $payload[ $key ];
			}
		}
		foreach ( $events as $event ) {
			if ( preg_match( '/(^|[._:\-\s])(delete|deleted|remove|removed|unlink)($|[._:\-\s])/i', $event ) ) {
				return 'delete';
			}
		}

		if ( ! empty( $payload['deletedAt'] ) || ! empty( $payload['deleted_at'] ) ) {
			return 'delete';
		}

		return 'upsert';
	}

	/**
	 * Extracts the remote product ID from a generic payload.
	 *
	 * Supports ?id=N, {"id": N}, product_id/productId and nested data/product/record
	 * wrappers, which take priority over a top-level (delivery) id.
	 *
	 * @param array $payload Webhook payload.
	 * @return string
	 */
	public static function extract_product_id( $payload ) {
		// In envelopes (delivery id outside, product inside a data/product/record wrapper) the nested record wins.
		foreach ( array( 'data', 'product', 'record' ) as $wrapper ) {
			if ( isset( $payload[ $wrapper ] ) && is_array( $payload[ $wrapper ] ) ) {
				$id = self::extract_product_id( $payload[ $wrapper ] );
				if ( '' !== $id ) {
					return $id;
				}
			}
		}
		foreach ( array( 'product_id', 'productId', 'id', '_id' ) as $key ) {
			if ( isset( $payload[ $key ] ) && is_scalar( $payload[ $key ] ) && '' !== (string) $payload[ $key ] ) {
				return sanitize_text_field( (string) $payload[ $key ] );
			}
		}

		return '';
	}

	/**
	 * Checks whether a translated item can be synced without asking the API.
	 *
	 * Only an explicit 'complete' => true from the connector is trusted: a sparse
	 * item would reset data the sync writes (price, tax class, stock).
	 *
	 * @param array     $item     Universal product item.
	 * @param bool|null $complete Completeness declared by the connector, null when not declared.
	 * @return bool
	 */
	public static function is_item_complete( $item, $complete = null ) {
		if ( empty( $item ) || ! is_array( $item ) || empty( $item['id'] ) ) {
			return false;
		}

		return true === $complete;
	}

	/**
	 * Sanitizes the universal item, keeping HTML in descriptions, URL encoding in images,
	 * identifiers (id, sku, pid, barcode) verbatim and scalar types.
	 *
	 * @param array  $item       Universal product item.
	 * @param string $parent_key Key of the parent array, used to detect image lists.
	 * @return array
	 */
	public static function sanitize_item( $item, $parent_key = '' ) {
		if ( ! is_array( $item ) ) {
			return array();
		}
		$html_keys  = array( 'desc', 'description', 'shortDesc', 'short_description' );
		$url_keys   = array( 'url', 'image', 'src' );
		$id_keys    = array( 'id', 'sku', 'pid', 'barcode' );
		$image_list = in_array( $parent_key, array( 'images', 'image' ), true );
		foreach ( $item as $key => $value ) {
			if ( is_array( $value ) ) {
				$item[ $key ] = self::sanitize_item( $value, (string) $key );
			} elseif ( is_string( $value ) ) {
				if ( in_array( $key, $html_keys, true ) ) {
					$item[ $key ] = wp_kses_post( $value );
				} elseif ( in_array( $key, $url_keys, true ) || ( $image_list && is_int( $key ) ) ) {
					// Keeps percent-encoding and signed query strings (sanitize_text_field strips %xx).
					$item[ $key ] = esc_url_raw( $value );
				} elseif ( in_array( $key, $id_keys, true ) ) {
					// Opaque identifiers must match the stored remote ID/SKU: only control characters and tags are removed.
					$item[ $key ] = trim( preg_replace( '/[\x00-\x1F\x7F]/', '', wp_strip_all_tags( $value ) ) );
				} else {
					$item[ $key ] = sanitize_text_field( $value );
				}
			}
		}

		return $item;
	}

	/**
	 * Finds the WooCommerce product linked to a remote ID of this connector.
	 *
	 * The remote ID (connect_ecommerce_id) is not unique across connectors, so
	 * products synced by webhooks also store their connector
	 * (connect_ecommerce_connector). A product without that meta is only used
	 * when it is the only one with that remote ID and the site has at most one
	 * connector syncing products; otherwise the lookup is ambiguous and nothing
	 * is returned.
	 *
	 * Looked up before the SKU, so a SKU changed in the ERP updates the existing
	 * product instead of creating a duplicate (the sync falls back to the SKU).
	 *
	 * @param array  $connector Connector context.
	 * @param string $remote_id Remote product ID.
	 * @return int
	 */
	private static function find_post_id( $connector, $remote_id ) {
		$posts = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'any',
				'posts_per_page' => 10,
				'fields'         => 'ids',
				'meta_key'       => 'connect_ecommerce_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $remote_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		if ( empty( $posts ) ) {
			return 0;
		}

		$connector_id = $connector['id'] ?? '';
		$untagged     = array();
		foreach ( $posts as $post_id ) {
			$owner = (string) get_post_meta( $post_id, self::META_CONNECTOR, true );
			if ( '' !== $connector_id && $owner === $connector_id ) {
				return (int) $post_id;
			}
			if ( '' === $owner ) {
				$untagged[] = (int) $post_id;
			}
		}

		if ( 1 === count( $posts ) && 1 === count( $untagged ) && self::count_product_connectors() <= 1 ) {
			return $untagged[0];
		}

		return 0;
	}

	/**
	 * Gets the connector owning the product that matches the item SKU (or its variants' SKUs).
	 *
	 * @param array $item Universal product item.
	 * @return string Connector ID, or empty when no product matches or it has no owner.
	 */
	private static function get_sku_owner( $item ) {
		$post_id = ! empty( $item['sku'] ) ? (int) PROD::find_product( $item['sku'] ) : 0;
		if ( ! $post_id && ! empty( $item['variants'] ) && is_array( $item['variants'] ) ) {
			foreach ( $item['variants'] as $variant ) {
				$post_id = ! empty( $variant['sku'] ) ? (int) PROD::find_parent_product( $variant['sku'] ) : 0;
				if ( $post_id ) {
					break;
				}
			}
		}

		return $post_id ? (string) get_post_meta( $post_id, self::META_CONNECTOR, true ) : '';
	}

	/**
	 * Counts the active connectors with the products workflow enabled.
	 *
	 * @return int
	 */
	private static function count_product_connectors() {
		$connectors = HELPER::get_connectors( self::$options );
		$count      = 0;
		foreach ( $connectors['meta'] ?? array() as $meta ) {
			if ( 'active' === ( $meta['status'] ?? 'active' ) && HELPER::is_workflow_enabled_for_connector( $meta, 'products' ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Handles a product deleted in the remote API.
	 *
	 * The default behaviour keeps the product untouched. Use the filter
	 * 'conecom_webhook_delete_behaviour' to return 'draft' or 'trash'.
	 *
	 * @param array  $connector Connector context.
	 * @param string $remote_id Remote product ID.
	 * @param array  $item      Universal product item, when sent.
	 * @return array
	 */
	private static function delete_product( $connector, $remote_id, $item ) {
		$post_id = self::find_post_id( $connector, $remote_id );

		// SKU fallback only for a product this connector owns: the same SKU may belong to another connector.
		if ( ! $post_id && ! empty( $item['sku'] ) ) {
			$by_sku = (int) PROD::find_product( $item['sku'] );
			$owner  = $by_sku ? (string) get_post_meta( $by_sku, self::META_CONNECTOR, true ) : '';
			if ( $by_sku && '' !== ( $connector['id'] ?? '' ) && $owner === $connector['id'] ) {
				$post_id = $by_sku;
			}
		}

		if ( ! $post_id ) {
			return array(
				'status'  => 'ignored',
				'message' => __( 'Deleted product not found in WooCommerce.', 'woocommerce-es' ),
			);
		}

		/**
		 * Filters what happens with a WooCommerce product deleted in the ERP.
		 *
		 * @param string $behaviour 'none', 'draft' or 'trash'.
		 * @param int    $post_id   WooCommerce product ID.
		 * @param array  $connector Connector context.
		 */
		$behaviour = apply_filters( 'conecom_webhook_delete_behaviour', 'none', $post_id, $connector );

		if ( 'trash' === $behaviour ) {
			wp_trash_post( $post_id );
		} elseif ( 'draft' === $behaviour ) {
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => 'draft',
				)
			);
		}

		do_action( 'conecom_webhook_product_deleted', $post_id, $remote_id, $connector );

		return array(
			'status'  => 'ok',
			'post_id' => (int) $post_id,
			/* translators: %s: action applied (none, draft, trash). */
			'message' => sprintf( __( 'Product deleted in the ERP. Action applied: %s', 'woocommerce-es' ), $behaviour ),
		);
	}

	/**
	 * Logs the result and returns it.
	 *
	 * @param string $connector_id Connector ID.
	 * @param array  $result       Result.
	 * @return array
	 */
	private static function finish( $connector_id, $result ) {
		self::add_log( $connector_id, $result );

		/**
		 * Fires after a product webhook has been processed.
		 *
		 * @param array  $result       Result.
		 * @param string $connector_id Connector ID.
		 */
		do_action( 'conecom_webhook_processed', $result, $connector_id );

		return $result;
	}

	/**
	 * Gets the HTTP status for a result.
	 *
	 * @param array $result Result.
	 * @return int
	 */
	private static function get_http_status( $result ) {
		if ( ! empty( $result['code'] ) ) {
			return (int) $result['code'];
		}
		// Sync errors still answer 200 so the ERP does not retry forever; they are logged.
		return 200;
	}

	/**
	 * Adds a log entry.
	 *
	 * @param string $connector_id Connector ID.
	 * @param array  $result       Result.
	 * @return void
	 */
	public static function add_log( $connector_id, $result ) {
		$logs = get_option( self::OPTION_LOGS, array() );
		$logs = is_array( $logs ) ? $logs : array();

		array_unshift(
			$logs,
			array(
				'time'         => time(),
				'type'         => 'webhook',
				'connector_id' => sanitize_key( $connector_id ),
				'action'       => $result['action'] ?? '',
				'id'           => $result['id'] ?? '',
				'post_id'      => (int) ( $result['post_id'] ?? 0 ),
				'status'       => $result['status'] ?? '',
				'source'       => $result['source'] ?? '',
				'message'      => function_exists( 'mb_substr' ) ? mb_substr( (string) ( $result['message'] ?? '' ), 0, 500 ) : substr( (string) ( $result['message'] ?? '' ), 0, 500 ),
			)
		);

		// Keep the latest entries of each connector, so a busy one does not evict the others.
		$counts = array();
		$kept   = array();
		foreach ( $logs as $log ) {
			$owner            = $log['connector_id'] ?? '';
			$counts[ $owner ] = ( $counts[ $owner ] ?? 0 ) + 1;
			if ( $counts[ $owner ] <= self::LOG_LIMIT ) {
				$kept[] = $log;
			}
		}

		update_option( self::OPTION_LOGS, $kept, false );
	}

	/**
	 * Gets the log entries, optionally for one connector.
	 *
	 * @param string $connector_id Connector ID.
	 * @return array
	 */
	public static function get_logs( $connector_id = '' ) {
		$logs = get_option( self::OPTION_LOGS, array() );
		$logs = is_array( $logs ) ? $logs : array();
		if ( '' === $connector_id ) {
			return $logs;
		}

		return array_values(
			array_filter(
				$logs,
				function ( $log ) use ( $connector_id ) {
					return ( $log['connector_id'] ?? '' ) === $connector_id;
				}
			)
		);
	}
}
