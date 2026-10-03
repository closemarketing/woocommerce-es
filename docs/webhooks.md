# Product Webhooks

ERPs/CRMs can notify WooCommerce when a product changes. The core receives the
webhook, the **connector translates the payload into the universal product item**,
and the product is synced with the same logic used by the manual import
(`PROD::sync_product_item()`).

Related issue: [#156](https://github.com/closemarketing/woocommerce-es/issues/156).

## Endpoint

```
POST|GET|PUT /wp-json/conecom/v1/webhooks/products/{connector_id}?token={secret}
```

- `{connector_id}` is the configured connector instance (e.g. `holded`, `odoo`, `holded_2`).
- `{secret}` is a per-connector token generated automatically and stored in the
  `connect_ecommerce_webhook_tokens` option. It can also be sent in the
  `X-Conecom-Token` header. It can be regenerated from the Webhooks tab.
- The full URL is shown in **Connect Ecommerce > {Connector} > Products > Webhooks**.

Responses:

| HTTP | When |
|------|------|
| 200  | Processed (`status`: `ok`, `error` on sync errors, or `ignored`). Sync errors answer 200 so the ERP does not retry forever; they are logged. |
| 400  | No product ID found / payload could not be parsed. |
| 401  | Invalid token or connector signature. |
| 404  | Connector not found or not configured. |

## Flow

1. Validate the token (`WEBHOOK::permission_check()`).
2. Resolve the connector context (`HELPER::get_connector_by_id()`); skip when the
   connector is inactive or its `products` workflow is disabled.
3. If the connector implements `verify_webhook()`, validate its signature.
4. Translate the payload:
   - Connector implements `parse_webhook_product()` → use it.
   - Otherwise, generic parser: extract the ID from `?id=N`, `{"id": N}`,
     `product_id`, `productId`, `_id`, or nested `data` / `product` / `record`.
   - Filter `conecom_webhook_parse_request` can override the result.
5. If the translated item is **complete**, sync it directly. If not, fall back to
   `get_products( $id )` (second request — not ideal, but always works).
6. Log the execution (`connect_ecommerce_webhook_logs`, last 50 entries) and show
   it in the Webhooks tab.

## Connector contract

Three optional methods in `CONECOM_Abstract_Connector_API`:

```php
/**
 * @return array{action: string, id: string|int, item?: array, complete?: bool}|array{status: 'error', message: string}
 */
public function parse_webhook_product( $payload, $headers = array() );

/** Optional signature validation (HMAC, etc.). */
public function verify_webhook( $raw_body, $headers = array() );

/** HTML instructions for the Webhooks tab. */
public function get_webhook_instructions( $webhook_url = '' );
```

`parse_webhook_product()` returns:

| Key        | Description |
|------------|-------------|
| `action`   | `upsert` (create/update), `delete` or `ignore`. |
| `id`       | Remote product ID. Always required (except for `ignore`). |
| `item`     | The universal product item (see below). Optional. |
| `complete` | `true` when `item` has everything needed. `false` forces `get_products( $id )`. When omitted, the core decides: `id` + `name` + `sku` (simple) or `variants` (variable). |

**Rule of thumb:** map everything the webhook carries. Only return
`complete => false` when something the sync really needs is missing (e.g. variant
attributes, rates or taxes the store is configured to use).

## Universal product item

It is the item returned by `get_products( $id )`, historically the Holded API
product format. This is what `PROD::sync_product_item()` reads:

| Key            | Type | Notes |
|----------------|------|-------|
| `id`           | string | Remote ID. Saved as `connect_ecommerce_id` meta. **Required.** |
| `name`         | string | **Required** for a complete item. |
| `desc`         | string (HTML) | Description. Note: the Holded webhook sends `description`, the API uses `desc`. |
| `kind`         | string | `simple`, `variants` (or `variable`), `pack`. Defaults to `simple`. |
| `sku`          | string | **Required** for simple products. |
| `barcode`      | string | EAN/GTIN. |
| `price`        | number | Regular price (without tax unless the tax option says otherwise). |
| `cost`         | number | Cost price. |
| `stock`        | number | Stock quantity (used when stock import is enabled). |
| `taxes`        | array\|string | Tax identifiers, e.g. `["s_iva_21"]`. |
| `rates`        | array | Price rates: `[ { "id": "...", "subtotal": 10 } ]`. |
| `tags`         | array | Used by the tag filter. |
| `attributes`   | array | Product attributes / custom fields for merge vars. |
| `taxonomies`   | array | Custom taxonomies. |
| `images`       | array | `[ { "url": "...", "content_type": "image/jpeg" } ]` or URLs. If missing, `get_image_product()` is called. |
| `weight`, `height`, `width`, `lenght` | number | Dimensions (`lenght` spelling kept for compatibility). |
| `last_updated` | int\|string | Timestamp, used for the import statistics. |
| `variants`     | array | See below. Empty for simple products. |
| `packItems`    | array | `[ { "pid": "remote-id", "u": 2 } ]` for packs. |

Variant:

| Key              | Notes |
|------------------|-------|
| `id`             | Remote variant ID. |
| `sku`            | **Required.** |
| `barcode`, `price`, `cost`, `stock` | As in the product. |
| `categoryFields` | Variant attributes: `[ { "name": "Color", "field": "Red" } ]`. **Required** for variable products. |
| `image`          | Image URL or array. |

## Examples

### Holded

Holded's product webhook already carries almost the whole item:

```json
{
  "id": "6ac0c0726e2bde0e6408ad90",
  "name": "prueba producto webhook",
  "description": "En un lugar de la Mancha...",
  "kind": "simple",
  "sku": "234234234",
  "barcode": "4342324234324234234324234234",
  "price": "10",
  "cost": "10.00000",
  "stock": "0",
  "variants": [
    { "id": "6ac0c0726e2bde0e6408ad91", "sku": null, "barcode": null, "price": null, "cost": null, "stock": null, "description": null }
  ],
  "packItems": []
}
```

Translation in the Holded connector:

```php
public function parse_webhook_product( $payload, $headers = array() ) {
	$item         = $payload;
	$item['desc'] = $payload['description'] ?? '';
	unset( $item['description'] );

	// Simple products include a default variant with empty values.
	if ( 'simple' === ( $item['kind'] ?? '' ) ) {
		$item['variants'] = array();
	}

	// The webhook does not send taxes, tags, attributes or variant categoryFields.
	$needs_api = 'variants' === ( $item['kind'] ?? '' ) || ( ! empty( $this->settings['rates'] ) && 'default' !== $this->settings['rates'] );

	return array(
		'action'   => 'upsert',
		'id'       => $payload['id'] ?? '',
		'item'     => $item,
		'complete' => ! $needs_api,
	);
}
```

Missing in the current Holded webhook (would avoid the second request): `taxes`,
`tags`, `rates`, `categoryFields` in variants, images and the event type
(create/update/delete).

### Odoo

Odoo (17+) automation rules with "Send Webhook Notification" post the selected
fields of the record plus `_model`, `_id` and `id`. Many2many fields (variants,
taxes) only arrive as IDs, so variable products usually need the API fallback:

```php
public function parse_webhook_product( $payload, $headers = array() ) {
	if ( 'product.template' !== ( $payload['_model'] ?? 'product.template' ) ) {
		return array( 'action' => 'ignore', 'id' => '' );
	}

	$item = array(
		'id'      => (string) ( $payload['id'] ?? $payload['_id'] ?? '' ),
		'name'    => $payload['name'] ?? '',
		'desc'    => $payload['description_sale'] ?? '',
		'sku'     => $payload['default_code'] ?? '',
		'barcode' => $payload['barcode'] ?? '',
		'price'   => $payload['list_price'] ?? 0,
		'cost'    => $payload['standard_price'] ?? 0,
		'stock'   => $payload['qty_available'] ?? 0,
		'kind'    => ( $payload['product_variant_count'] ?? 1 ) > 1 ? 'variants' : 'simple',
	);

	return array(
		'action'   => empty( $payload['active'] ) && isset( $payload['active'] ) ? 'delete' : 'upsert',
		'id'       => $item['id'],
		'item'     => $item,
		'complete' => 'simple' === $item['kind'],
	);
}
```

## Hooks

| Hook | Type | Description |
|------|------|-------------|
| `conecom_webhook_parse_request` | filter | `( $parsed, $payload, $headers, $connector )` — override the translation. |
| `conecom_webhook_product_item` | filter | `( $item, $source, $connector, $payload )` — item right before sync. `$source` is `payload` or `api`. |
| `conecom_webhook_delete_behaviour` | filter | `( 'none', $post_id, $connector )` — return `draft` or `trash` to act on deleted products. |
| `conecom_webhook_instructions` | filter | `( $html, $connector_id, $webhook_url, $connapi_erp )` — Webhooks tab instructions. |
| `conecom_webhook_product_deleted` | action | `( $post_id, $remote_id, $connector )`. |
| `conecom_webhook_processed` | action | `( $result, $connector_id )` after every webhook. |

## Pending / future

- Async processing (Action Scheduler) when the ERP requires a very fast response.
- Stock-only webhooks (`get_products_stock` style payloads).
