# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Run all tests
composer test

# Run a single test class
composer test -- --filter=CreateProductSimpleTest

# Run a specific test suite
composer test -- --testsuite=woocommerce

# Lint
composer lint

# Auto-fix lint issues
composer format

# Static analysis
composer phpstan

# Install WordPress test environment (first-time setup)
composer test-install
```

## Architecture

**Entry point:** `woocommerce-es.php` → calls `conecom_loads()` on `init` → instantiates `CLOSE\ConnectEcommerce\Base` from `includes/Plugin_Main.php`.

**Autoloading:** PSR-4, namespace `CLOSE\ConnectEcommerce\` maps to `includes/`.

**Module layout:**
- `includes/Admin/` — Admin-only classes (Settings, Import_Products, Orders, Taxes, Notices). Instantiated only when `is_admin()`.
- `includes/Frontend/` — Customer-facing classes (Checkout, MyAccount). Always instantiated.
- `includes/Connector/` — ERP/CRM adapter(s). Currently only `class-api-clientify.php` (Clientify). The connector object is passed into helpers as `$api_erp`.
- `includes/Helpers/` — Stateless static-method utility classes: `PROD` (product sync), `ORDER` (order data), `TAX`/`TAXES`/`VAT` (tax/VAT logic), `ALERT`, `CRON`, `AI`, `HELPER` (logging, connector routing, settings).

**Options storage:**
- `connect_ecommerce` — main plugin settings keyed by connector slug (e.g. `['holded' => [...]]`)
- `connect_ecommerce_prod_mergevars` — product field mappings (`prod_mergevars` key); format: `[ 'sourceField' => 'type|destination' ]` where type is `cf` (custom field meta), `tax` (taxonomy), or `prod` (product prop)
- `connect_ecommerce_payment_methods` — payment method mappings

**Product sync flow:** `PROD::sync_product_item()` → `sync_product_simple()` / `sync_product()` for simple products; → `sync_product()` (type=variable) + `sync_product_variable()` for variable products. `PROD::filter_product()` gates import based on tags, SKU pattern, and merge var post_status.

**Tests:** Two suites in `phpunit.xml.dist` — `testing` (Unit, no WooCommerce needed) and `woocommerce` (integration tests that require a full WP+WC environment). Test data fixtures live in `tests/Data/`.

## Code Style

- Tabs for indentation; align `=` vertically within groups of consecutive variable assignments.
- Always use Yoda conditions (`'value' === $var`).
- Every PHP file starts with a doc header (author: Closetechnology) followed by `defined( 'ABSPATH' ) || exit;`.
- Inline comments: capital first letter, end with period, English only.
- No jQuery — plain JavaScript only.
- Global functions/options prefixed with `conecom_`; classes namespaced under `CLOSE\ConnectEcommerce\`.
- Text domain: `woocommerce-es`.
