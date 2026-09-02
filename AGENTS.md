# Connect WooCommerce Shop to ERP/CRM, Verifactu and EU/VAT Compliance

This file provides guidance to AI coding agents (Claude Code, Cursor, etc.) when working with code in this repository.

## Plugin Overview

**Connect and EU VAT Compliance for WooCommerce** (`woocommerce-es`) — connects WooCommerce to ERPs/CRMs (products, customers, orders, stock sync) and adds EU VAT compliance tools (VAT validation via VIES/VATSense, zero-rate B2B, tax import).

- Text domain / prefix: `woocommerce-es` / `conecom_`
- Namespace: `CLOSE\ConnectEcommerce\` (PSR-4, mapped to `includes/`)
- Requires: PHP 7.4+, WordPress 6.3+, WooCommerce

## Commands

```bash
# Install dependencies
composer install

# Lint (PHPCS)
composer lint

# Auto-fix code style
composer format

# Static analysis (PHPStan level 1)
composer phpstan

# Run all tests
composer test

# Run a single test class
composer test -- --filter=CreateProductSimpleTest

# Run a specific test suite
composer test -- --testsuite=woocommerce

# Run a single test file
./vendor/bin/phpunit tests/Unit/VATValidationTest.php

# Run with Xdebug
composer test-debug

# Install WordPress test environment (DB setup, first-time setup)
composer test-install
# equivalent: bash bin/install-wp-tests.sh wordpress_test root 'root' 127.0.0.1 latest
```

Test suites defined in `phpunit.xml.dist`:
- `testing` — `tests/Unit/` (pure unit tests, no WP/WC required)
- `woocommerce` — `tests/WooCommerce/` (integration tests, requires WC active, set via `WP_TESTS_ACTIVATE_PLUGINS`)

Test data fixtures live in `tests/Data/`.

## Architecture

### Entry point & bootstrap

`woocommerce-es.php` defines constants (`CONECOM_VERSION`, `CONECOM_FILE`, etc.) and the `conecom_get_options()` function. Each ERP/CRM connector registers itself via the `conecom_options_plugin` filter, adding its config array. On `init`, `CLOSE\ConnectEcommerce\Base` is instantiated with the merged options array.

### `includes/Plugin_Main.php` — `Base` class

The central bootstrap class. Receives the `$options` array, resolves the configured connector(s) via `HELPER::get_connector()` / `HELPER::get_connectors()`, then wires up all subsystems:

- Admin context (only when `is_admin()`): `Settings`, `Setup_Wizard`, `Import_Products`, `Widget_Product`, `Widget_Order`, `Notices`, `Taxes_Rates`, `Taxes_Types_ERP`
- Always: `Orders`, `Checkout`, `MyAccount`

### `includes/` namespace layout

| Path | Responsibility |
|------|---------------|
| `Admin/Settings.php` | Plugin settings page, renders connector-specific fields, manages the multi-connector list |
| `Admin/Setup_Wizard.php` | First-install setup wizard (AJAX-driven onboarding) |
| `Admin/Import_Products.php` | Batch product import from ERP (AJAX + pagination) |
| `Admin/Orders.php` | Order admin column, sync actions |
| `Admin/Taxes_Rates.php` | Imports EU tax rates into WooCommerce |
| `Admin/Taxes_Types_ERP.php` | Maps ERP tax types to WC rates |
| `Admin/Widget_Order.php` | Order meta box: manual sync |
| `Admin/Widget_Product.php` | Product meta box: ERP product data |
| `Frontend/Checkout.php` | VAT field on checkout, validation hooks |
| `Frontend/MyAccount.php` | VAT field on My Account |
| `Helpers/VAT.php` | VAT number validation logic (VIES + VATSense) |
| `Helpers/TAX.php` / `TAXES.php` | Tax rate helpers |
| `Helpers/ORDER.php` | Order sync helpers |
| `Helpers/PROD.php` | Product sync helpers |
| `Helpers/PAYMENTS.php` | Payment method mapping |
| `Helpers/CRON.php` | WP Cron scheduled sync |
| `Helpers/AI.php` | AI-assisted product descriptions |
| `Helpers/ALERT.php` | Admin alert/notification system |
| `Helpers/HELPER.php` | Shared utilities (connector resolution, settings migration, logging) |
| `Connector/Abstract_Connector_API.php` | Base class defining the connector API contract (optional capabilities) |
| `Connector/class-api-clientify.php` | Example connector (Clientify CRM) |
| `Connector/class-api-brevo.php` | Brevo connector (order/email sync only, no product catalog) |
| `CLI/Import_Products_Command.php` | WP-CLI: `wp conecom` commands |

The connector object is passed into helpers as `$api_erp`.

### Connector pattern

Each ERP/CRM connector is a separate plugin (or class in `Connector/`) that hooks `conecom_options_plugin` to inject its config. The config array drives: API credentials fields, product/order sync options, admin UI labels, and the DB sync table name (`{prefix}sync_{slug}`). The `Connector/` directory holds the connector class; `Connector/assets/` holds its logo.

Connectors may extend `CONECOM_Abstract_Connector_API` to declare which optional capabilities they implement; use `HELPER::connector_supports( $connector, $method )` (rather than a raw `method_exists()`) wherever an optional method is called, so both legacy and contract-based connectors are handled consistently.

### Multi-connector support

The plugin can hold several simultaneously configured connectors (see `docs/multi-connectors.md`). `HELPER::get_connectors()` builds a context (`connapi_erp`, `settings`, `options`, capability flags) per connector ID; `HELPER::get_connector()` resolves the single active/default one for backwards compatibility. Admin AJAX handlers (`Import_Products`, `Orders`) accept an optional `connector_id` from the request and resolve that connector's own `connapi_erp`/`settings`/`options` before acting, falling back to the default connector when none is specified.

### Options storage

- `connect_ecommerce` — main plugin settings keyed by connector ID (e.g. `['holded' => [...]]`), plus a `connectors_meta` entry describing each configured connector (type, label, workflow flags, status) and a `connector` key naming the active one.
- `connect_ecommerce_prod_mergevars` — product field mappings (`prod_mergevars` key); format: `[ 'sourceField' => 'type|destination' ]` where type is `cf` (custom field meta), `tax` (taxonomy), or `prod` (product prop)
- `connect_ecommerce_payment_methods` — payment method mappings

### Product sync flow

`PROD::sync_product_item()` → `sync_product_simple()` / `sync_product()` for simple products; → `sync_product()` (type=variable) + `sync_product_variable()` for variable products. `PROD::filter_product()` gates import based on tags, SKU pattern, and merge var post_status.

### VAT field slug detection

`CONECOM_VAT_FIELD_SLUGS` (defined in main file) lists all known meta key variants for the VAT/NIF field to ensure compatibility across checkout block and classic checkout.

## Releasing a New Version

Version and changelog live in two files that must stay in sync:

- `woocommerce-es.php` — `Version:` header comment AND `CONECOM_VERSION` constant (both must match).
- `readme.txt` — `Stable tag:` / `Version:` header fields, AND the `== Changelog ==` section.

During development, unreleased changes accumulate under a `= next =` heading in the changelog (each PR appends its own `* Added:` / `* Fixed:` / `* Enhancement:` bullet there — see existing entries for the tone/format). To ship a release:

1. Rename `= next =` to `= X.Y.Z =` in `readme.txt` (keep its bullets as-is — that heading becomes the release notes).
2. Bump `Stable tag:` and `Version:` in the `readme.txt` header to `X.Y.Z`.
3. Bump `Version:` and `CONECOM_VERSION` in `woocommerce-es.php` to `X.Y.Z` (drop any `-beta.N` suffix).
4. Run `composer lint`, `composer phpstan`, `composer test` — all must pass before tagging.
5. Commit (conventionally as a standalone `version` commit) and push to `trunk`.
6. Create the GitHub release, tagged at that commit:
   ```bash
   gh release create X.Y.Z --repo closemarketing/woocommerce-es \
     --title "Version X.Y.Z" \
     --notes "## What's Changed

   <paste the readme.txt changelog bullets for this version verbatim>"
   ```
   Match past releases' format exactly: title is `Version X.Y.Z`, tag is the bare version, body is `## What's Changed` followed by the same `* Added:` / `* Fixed:` / `* Enhancement:` bullets just written to `readme.txt` — don't reword them.

A new `= next =` placeholder is added later, by whichever PR is the first to need it after the release — not as part of the release itself.

Between releases, a beta suffix (`X.Y.Z-beta.N`) may be used in both files' version fields while a feature is still in review — drop it in step 3 above.

`.distignore` controls what's excluded from the packaged plugin zip (composer/npm manifests, CI config, test tooling, docs). Check it before adding new dev-only files at the plugin root so they don't ship in releases.

## Code Style

- **Tabs** for indentation; align `=` operators vertically within variable groups.
- **Yoda conditions** always (`null === $var`).
- PHP file header: `/** ... @author Closetechnology ... */` → `namespace ...;` → `defined( 'ABSPATH' ) || exit;`
- Inline comments: capital letter, end with period. Comment blocks of logic, not individual lines.
- **No jQuery** — vanilla JavaScript only.
- Global functions/options prefixed with `conecom_`; classes namespaced under `CLOSE\ConnectEcommerce\`.
- Text domain: `woocommerce-es`.
- All documentation goes in `/docs/` and is listed in `.distignore`.
- **Language convention**: all code, comments, PR and issues written in English.

## Cursor Cloud specific instructions

This agent has access to the **agent-browser** CLI tool. Use it to test and debug web applications and websites end-to-end (e.g. opening URLs, interacting with the WordPress admin or storefront, checking sync or checkout flows).

You can also use **WordPress Playground** (e.g. via `npx @wp-playground/cli`) to spin up a disposable WordPress instance and test the plugin, sync, or checkout flows without a full local stack.
