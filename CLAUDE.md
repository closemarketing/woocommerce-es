# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

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

# Run unit tests
composer test

# Run a single test file
./vendor/bin/phpunit tests/Unit/VATValidationTest.php

# Run with Xdebug
composer test-debug

# Install WP test environment (DB setup)
composer test-install
# equivalent: bash bin/install-wp-tests.sh wordpress_test root 'root' 127.0.0.1 latest
```

Test suites defined in `phpunit.xml.dist`:
- `testing` — `tests/Unit/` (pure unit tests, no WP/WC required)
- `woocommerce` — `tests/WooCommerce/` (requires WC active, set via `WP_TESTS_ACTIVATE_PLUGINS`)

## Architecture

### Entry point & bootstrap

`woocommerce-es.php` defines constants (`CONECOM_VERSION`, `CONECOM_FILE`, etc.) and the `conecom_get_options()` function. Each ERP/CRM connector registers itself via the `conecom_options_plugin` filter, adding its config array. On `init`, `CLOSE\ConnectEcommerce\Base` is instantiated with the merged options array.

### `includes/Plugin_Main.php` — `Base` class

The central bootstrap class. Receives the `$options` array, resolves the active connector via `HELPER::get_connector()`, then wires up all subsystems:

- Admin context: `Settings`, `Import_Products`, `Widget_Product`, `Widget_Order`, `Notices`, `Taxes_Rates`, `Taxes_Types_ERP`
- Always: `Orders`, `Checkout`, `MyAccount`

### `includes/` namespace layout

| Path | Responsibility |
|------|---------------|
| `Admin/Settings.php` | Plugin settings page, renders connector-specific fields |
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
| `Connector/class-api-clientify.php` | Example connector (Clientify CRM) |
| `CLI/Import_Products_Command.php` | WP-CLI: `wp conecom` commands |

### Connector pattern

Each ERP/CRM connector is a separate plugin that hooks `conecom_options_plugin` to inject its config. The config array drives: API credentials fields, product/order sync options, admin UI labels, and the DB sync table name (`{prefix}sync_{slug}`). The `Connector/` directory holds the connector class; `Connector/assets/` holds its logo.

### VAT field slug detection

`CONECOM_VAT_FIELD_SLUGS` (defined in main file) lists all known meta key variants for the VAT/NIF field to ensure compatibility across checkout block and classic checkout.

## Code Style

- **Tabs** for indentation; align `=` operators vertically within variable groups.
- **Yoda conditions** always (`null === $var`).
- PHP file header: `/** ... @author Closetechnology ... */` → `namespace ...;` → `defined( 'ABSPATH' ) || exit;`
- Inline comments: capital letter, end with period. Comment blocks of logic, not individual lines.
- **No jQuery** — vanilla JavaScript only.
- All documentation goes in `/docs/` and is listed in `.distignore`.
