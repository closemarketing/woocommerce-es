# Issue: Variation SKU left blank on resync when Odoo renames a variant's SKU

## Environment

- Site: https://medicalbodycontrol.com (production)
- Product: `wp-admin/post.php?post=8028&action=edit` — "Sujetador Embrace | ABC Ibérica" (variable product)
- ERP source: Odoo, product at `https://odoo-abc.nip.ccit.es/odoo/action-715/31202`
- Plugin: `woocommerce-es` (Connect WooCommerce)
- Connector: Odoo (premium, separate plugin, not in this repo) — the bug is in the
  shared sync core (`woocommerce-es`), not the connector.
- Installed version behaves like `3.3.4` (Stable tag). Note: `trunk` is currently
  **ahead** of what's installed — see "Status" below.

## Symptom

After Odoo re-synced product #8028 on 2026-07-22 ~16:55 CEST (`conecom_updated`
meta = `1784732145`), 24 variations were created with an **empty SKU**, and
8 pre-existing variations were silently set to `draft`.

## Data evidence (pulled from prod via SQL)

Sample from the affected product (see full CSVs supplied by the user):

| WC post ID | post_status | SKU | Odoo variant id | notes |
|---|---|---|---|---|
| 8110 | `draft` | `503-85D-CH` | `32374` | pre-existing variation, silently drafted |
| 14657 | `publish` | `NULL` | `32374` | **new variation created for the same Odoo variant id, blank SKU** |

Same pattern repeats for 8 Odoo variant ids (`32374, 32375, ..., 32381` and their
siblings), each producing one drafted old variation + one blank-SKU new variation
— 8 × 3 colors = 24 new blank-SKU variations, matching the CSV exactly.

The old variation's SKU (`503-85D-CH`) encoded the **wrong** talla (the post
title says "Champang, 80A" while the SKU says `85D`) — i.e., Odoo had a stale/
mismatched SKU for that variant and corrected it on this sync. The new SKUs
were never captured in the CSV because they were never written (that's the bug).

## Root cause

`includes/Helpers/PROD.php`, method `PROD::sync_product_variable()`.

Variants are matched to existing WooCommerce variations **by SKU equality**,
not by the ERP's own variant id:

```php
$found_variation_id = array_search( $variant_sku_str, $variations_item_lookup, true );
```

`$variations_item` is a snapshot of `{ variation_post_id => current WC SKU }`
built from `$product->get_children()`. When Odoo sends a **new SKU for a
variant it already synced before** (as happened here — Odoo corrected
`503-85D-CH` → `503-75AA-CH` for variant id `32374`), the old SKU is no longer
present in the payload and the new SKU isn't present in `$variations_item`, so
`array_search()` can't match it to the existing variation (8110). The variant
is treated as brand new:

1. A new `WC_Product_Variation` is created for it (would become 14657).
2. The old, now-unmatched variation (8110) is left over in `$variations_item`
   and gets set to `draft` at the end of the loop (the "check if WooCommerce
   variations have more than API and unset" step).

This orphan-on-rename behavior is **not fixed on trunk** as of this writing —
matching is still SKU-only (see `PROD.php` around the `array_search()` call).

### Blank SKU specifically — already fixed on trunk, not yet released

At the time this was investigated, the local checkout was several commits
behind `origin/trunk`. On the checked-out (older) code, the newly created
variation's SKU was gated by the **parent-level** `$is_new_product` flag:

```php
if ( $is_new_product ) {           // true only if the PARENT product is new
    $variation->set_sku( $variant['sku'] );
}
```

Since the parent product (#8028) already existed, `$is_new_product` was
`false`, so `set_sku()` was **never called** for the newly created variation
— hence the blank SKU.

`origin/trunk` (commit `3bff863`, "Fix numeric-SKU matching and duplicate-SKU
scope in variant sync") already changed this gate to check the **variation's
own** SKU instead of the parent flag:

```php
if ( empty( $variation->get_sku( 'edit' ) ) ) {
    $variation->set_sku( $variant['sku'] );
}
```

This means: once the fix is deployed to production, the *specific* symptom
"new variation has SKU = NULL" should no longer occur. **However**, the
underlying orphan/draft behavior (old variation drafted, new variation created
for what is really the same Odoo variant, just SKU-renamed) is still present
on trunk — it just no longer manifests as a *blank* SKU, but as a *duplicate*
variation (one drafted, one new) for the same logical variant. That is a data
integrity problem independent of the blank-SKU bug and worth a follow-up.

## Action items

1. **Immediate**: update production to a build including `3bff863` (or later)
   to stop new blank-SKU variations from being created.
2. **Follow-up** (separate issue): match variants to existing variations by the
   ERP's own variant id (stored in `_connect_ecommerce_productid` meta) as a
   primary key, falling back to SKU only when no id match exists. This would
   prevent the orphan-variation/draft churn entirely when an ERP corrects a
   SKU without changing the underlying variant identity.

## Repro / regression test

Added `test_variant_sku_renamed_on_resync_gets_new_sku` to
`tests/WooCommerce/CreateProductVariableTest.php`, using fixture
`tests/Data/product-variable-new-variant-resync.json` built from the real
production payload shape (Odoo variant id `32374`, SKU `503-85D-CH` →
`503-75AA-CH`):

1. Sync a variable product with 2 variants (creates it).
2. Resync the **same product ID**, but with the first variant's SKU renamed
   (same Odoo variant id, same attributes) — simulating Odoo's SKU correction.
3. Assert a variation exists carrying the new SKU (matched via barcode, which
   didn't change) and that its SKU is **not blank**.
4. Assert the old variation (if a distinct post) is set to `draft` — documents
   the current (still imperfect) orphaning behavior so a future id-based-match
   fix has a test to update.

On the code currently checked out (pre-`3bff863`) this test fails with the new
variation's SKU being empty. On `origin/trunk` (post-`3bff863`) the SKU assertion
passes; the orphan-draft behavior still occurs and is asserted as documented
current behavior, not asserted away.
