=== Connect WooCommerce Shop to ERP/CRM, Verifactu and EU/VAT Compliance ===
Contributors: closetechnology, closemarketing, davidperez, sacrajaimez
Tags: connect, integrate, eu vat, vat compliance, woocommerce
Donate link: https://close.marketing/go/donate/
Requires at least: 5.0
Requires PHP: 7.4
Tested up to: 6.9
Stable tag: 3.3.2
Version: 3.3.2
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add VAT Fields, Import European Taxes and check VAT compliance. Connect WooCommerce with ERPs and CRMs. Products, Clients and Orders with ERP/CRM.

== Description ==

**Streamline Your E-commerce Operations with Professional ERP/CRM Integration and Complete EU VAT Compliance**

Connect WooCommerce Shop to ERP/CRM, Verifactu and EU/VAT Compliance is the ultimate solution for WooCommerce store owners who need seamless integration with their business management systems while ensuring full compliance with European tax regulations.

Whether you're managing a small online store or a large e-commerce operation, this powerful plugin eliminates manual data entry, reduces errors, and saves countless hours of administrative work. Automatically synchronize your products, customers, and orders between WooCommerce and your ERP or CRM system, ensuring your inventory, customer database, and sales data are always up-to-date across all platforms.

**Complete EU VAT Compliance Made Simple**

Stay compliant with European tax regulations effortlessly. The plugin includes comprehensive VAT number validation using the official VIES service, with optional VATSense integration for enhanced reliability. Real-time validation during checkout ensures accurate B2B transactions, automatically applying zero VAT rates for valid intra-community transactions while maintaining full compliance with EU directives.

**Key Benefits:**

* **Save Time & Reduce Errors**: Automate product, customer, and order synchronization between WooCommerce and your ERP/CRM
* **EU VAT Compliance**: Full compliance with European tax regulations, including real-time VAT validation and automatic tax rate application
* **Verifactu Ready**: Built-in support for Verifactu regulations, ensuring your invoices meet Spanish legal requirements
* **GDPR Compliant**: Direct connection architecture ensures customer data never passes through third-party servers
* **AI-Powered**: Generate compelling product descriptions automatically using AI technology
* **Professional Integration**: Connect with leading ERP and CRM systems through our premium connector plugins

Perfect for businesses selling across Europe, B2B e-commerce operations, and any WooCommerce store that needs professional-grade integration and tax compliance capabilities.

**Functionalities**

- Add VAT info in forms fields, Orders, and email notification (Gutenberg compatible).
- Supports WooCommerce PDF Invoices & Packing Slips for VAT info in invoices.
- EU/VAT Compliance: Import European Taxes and check VAT compliance.
- **NEW: Real-time VAT validation** with dual API system (VIES + VATSense) - Live validation as customer types with automatic B2B intra-community zero-rate application for different EU countries.
- (optional) Connect your WooCommerce store to your ERP or CRM software. This plugin makes it easy to connect your store by synchronizing products, customers, and orders.
- Save hours of administrative work by eliminating the need to manually enter products, customers, and orders.
- You can now use AI to generate product marketing descriptions based on information from your ERP/CRM.
- There's no need for additional plugins to request VAT numbers from companies — this plugin has it covered.
- This plugin is fully GDPR compliant. The synchronization between WooCommerce and your ERP/CRM is established through a direct connection, without intermediaries or third-party storage of personal data. This ensures maximum security and transparency, keeping customer information under your full control.
- This plugin also includes specific adjustments to comply with Verifactu regulations. Order and invoice data are processed and structured to meet the official requirements, ensuring your business adheres to current legal standards.


**EU/VAT Compliance: Import European Taxes and check VAT compliance.**

You can use this feature alone if you need it. You can import European Taxes and check VAT compliance.

**VAT Number Validation via VIES & VATSense**

The plugin includes advanced real-time VAT validation during checkout with the following features:

**Real-time Validation:**
- Live validation as customer types (800ms debounce)
- Modern Vanilla JavaScript (no jQuery dependency)
- Visual feedback with status icons (checking, valid, invalid)
- Works with both classic shortcode and Gutenberg blocks checkout
- Automatic checkout recalculation when VAT status changes

**Dual API System:**
- Primary: VIES (official EU service, free)
- Fallback: VATSense (commercial service, optional, higher reliability)
- Automatic failover if primary service is down
- Supports EU countries + Norway & Switzerland (via VATSense)
- Results cached for 24 hours to optimize performance

**B2B Intra-community Zero-Rate:**
- Automatic 0% VAT rate for valid B2B transactions between different EU countries
- Uses WooCommerce tax class system (not simple exemption)
- Fiscally correct: shows "Zero Rate [Country]" on invoices
- Complies with EU VAT Directive 2006/112/EC
- Automatic restoration of standard VAT when validation fails

**Additional Features:**
- Validates format and minimum length per country before API call
- Can be configured as mandatory (blocks checkout) or optional (warnings only)
- Stores validation results and exemption data in order metadata
- Detailed logging for debugging and audit compliance
- Graceful handling of service unavailability

**Connect your WooCommerce store to your ERP or CRM software.**
Connect your WooCommerce store to your ERP or CRM software. This plugin makes it easy to connect your store by synchronizing products, customers, and orders.

Save hours of administrative work by eliminating the need to manually enter products, customers, and orders.

You can now use AI to generate product marketing descriptions based on information from your ERP/CRM.

There’s no need for additional plugins to request VAT numbers from companies — this plugin has it covered.

**How does it work the synchronization?**
It synchronizes according to the natural workflow your business should have when connected to an online store.

Products: Imports products from your ERP/CRM with all the necessary sales information, supporting variable products. Keeps prices, stock, and product details up to date.

Orders and Customers: Once an order is placed, it is sent to the ERP/CRM. It creates a new customer or matches the existing one to the order in the ERP/CRM. Depending on the connector, you can choose the type of document to generate.

This plugin is fully GDPR compliant. The synchronization between WooCommerce and your ERP/CRM is established through a direct connection, without intermediaries or third-party storage of personal data. This ensures maximum security and transparency, keeping customer information under your full control.

This plugin also includes specific adjustments to comply with Verifactu regulations. Order and invoice data are processed and structured to meet the official requirements, ensuring your business adheres to current legal standards.

**Main Benefits**
- Imports simple, variable, and bundled products.
- Imports product attributes such as brand.
- Imports product categories.
- Automatically imports products.
- Sends orders immediately after they are placed, including historical orders.
- Sends invoices attached to the order email (Holded only).
- Allows you to choose the invoice design (Holded only).
- Uses AI to generate product marketing information.
- Adds the NIF/CIF field for proper invoicing.
- Complies with Verifactu and GDPR.
- For ERPs that support it, you can send the payment method.

This plugin serves as the foundation for various connectors. The free version supports:
- [Clientify](https://close.marketing/likes/clientify/)

**Merge variables**
You can use this section to merge variables from ERP to WooCommerce. That means that you can merge categories, attributes, products, custom fields, taxonomies, etc.

You will need to add Payment methods to merge variables to send the payment method to the ERP.

Premium connectors include:
- [Holded](https://close.technology/en/wordpress-plugins/connect-woocommerce-holded/)
- [FacturaDirecta](https://close.technology/en/wordpress-plugins/connect-woocommerce-facturadirecta/)
- [Odoo](https://close.technology/en/wordpress-plugins/connect-woocommerce-odoo/)
- [NEO POS](https://close.technology/en/wordpress-plugins/connect-woocommerce-neo/)
- [Datisa](https://close.technology/en/wordpress-plugins/connect-woocommerce-datisa/)

Need another connector? We offer custom integration services. [Contact us](https://close.technology/en/contact/)

== Frequently Asked Questions ==

= What does this plugin do? =
Connect Ecommerce allows you to import products from an ERP/CRM to your WooCommerce store via API. It also sends orders from the store to your ERP/CRM and creates associated customers. It also allows you to import European Taxes, validate VAT numbers via VIES, and check VAT compliance.

= How are products and orders synced? =
Products are synced from the ERP/CRM to WooCommerce because the ERP should always contain the most up-to-date business information. This ensures accurate management of products, prices, and other business data.

Orders are synced from WooCommerce to the ERP/CRM so that every time a customer places an order, it is sent to your ERP for proper order and invoice management.

= What happens when a product already exists in WooCommerce? =
If the product already exists in WooCommerce, it will be updated with the new data from the ERP/CRM. It does not update marketing information like description, title, url, slug, etc. but it will update the product data like price, stock, etc.

Products are matched by SKU. If the SKU is the same, the product will be updated. If the SKU is not the same, the product will be created as a new product.

= What happens when a product is out of stock? =
By default, the product disappears from the store catalog but remains visible to search engines. This is intentional and matches the expected store behavior.

= Does it comply with Verifactu? =
Yes, it does. It makes the order data more readable for Verifactu.

= How does the VAT validation work? =
The plugin includes advanced real-time VAT validation with a dual API system:
- **Primary service**: VIES (official EU service, free) validates VAT numbers against the European Commission database
- **Fallback service**: VATSense (optional commercial service) provides enhanced reliability when VIES is unavailable
- **Real-time feedback**: Validation happens as the customer types (800ms debounce) with visual status indicators
- **Smart caching**: Results cached for 24 hours (valid) or 1 hour (invalid) to optimize performance
- **Compliance tracking**: All validation results stored in order metadata for audit purposes

You can configure validation as mandatory (blocking invalid VAT) or optional (showing warnings only).

= What happens if VIES service is unavailable? =
The plugin uses an intelligent fallback system:
1. If VIES fails and VATSense is configured, it automatically uses VATSense as fallback
2. If both services are unavailable, the VAT number is accepted with a warning and standard VAT applies
3. All service failures are logged for monitoring and debugging
This multi-service approach ensures that temporary service issues don't block legitimate orders.

= How does B2B intra-community zero-rate work? =
When a valid VAT number is provided for a B2B transaction between different EU countries:
- The system automatically applies a "zero-rate" tax class (0% VAT)
- This is fiscally correct: invoices show "Zero Rate [Country]: €0.00"
- The exemption only applies when: both countries are in EU, countries are different, and VAT is successfully validated
- For same-country (domestic) transactions, standard VAT applies even with valid VAT number
- If validation fails or field is emptied, standard VAT is automatically restored

= Does it work with WooCommerce Gutenberg Blocks checkout? =
Yes, fully supported. The real-time VAT validation works seamlessly with both:
- Classic shortcode-based checkout
- Modern Gutenberg blocks checkout
The same validation logic, visual feedback, and tax exemption rules apply to both checkout types.

== Installation ==

- Go to Add Plugin, search for Connect Ecommerce, and Install it. Then Activate the plugin. You will need to have WooCommerce Installed.
- Go to WooCommerce > Connect Ecommerce for the configuration.

== Developers ==
[Official Repository GitHub](https://github.com/closemarketing/woocommerce-es)

You can use WP CLI to import products from the command line. The command is:
```
wp conecom products --update --ai=none,new,all
```

== External services ==

This plugin connects to an API to make AI SEO descriptions and product information.

It sends product data to the API, which then returns optimized SEO descriptions and enhanced product details.

Supported Services:
- OpenAI: [Terms of use](https://openai.com/policies/row-terms-of-use/) and [Privact policy](https://openai.com/policies/row-privacy-policy/)
- DeepSeek: [Terms of use](https://cdn.deepseek.com/policies/en-US/deepseek-terms-of-use.html) and [Privacy policy](https://cdn.deepseek.com/policies/en-US/deepseek-privacy-policy.html)

The core connector integrates with Clientify, a CRM and marketing automation tool. [Terms of use](https://clientify.com/aviso-legal/) and [privacy policy](https://clientify.com/politicas-de-privacidad). 

**VAT Number Validation Service**

This plugin uses the VIES (VAT Information Exchange System) service provided by the European Commission to validate EU VAT numbers. The VIES service is accessed through the dragonbe/vies PHP library. When a customer enters a VAT number during checkout, the plugin communicates with the VIES web service to verify the number's validity. This is an official EU service and does not store personal data. [VIES Information](https://ec.europa.eu/taxation_customs/vies/)

== Changelog ==

= n.e.x.t =
* Fixed: Variations now inherit parent tax class correctly by setting tax_class to "parent" on creation.
* Fixed: Tax class "parent" is preserved when products are re-synced/updated, preventing tax calculation inconsistencies.
* Fixed: Product importer now correctly detects end of paginated product list, preventing unnecessary API calls beyond last product.
* Enhancement: Added comprehensive test coverage for variation tax class inheritance and persistence on updates.
* Enhancement: Added pagination end detection tests for product import with various edge cases (102/100, exact pages, multiple pages).

= 3.3.2 =
* Added: Support to FacturaDirecta connector.
* Fixed: General setting not import Inventory was not working in variable products.
* Fixed: Error cleaning special chars in order data.
* Enhancement: Added VAT number validation via VIES (VAT Information Exchange System).
* Enhancement: Integrated dragonbe/vies library for EU VAT number validation.
* Enhancement: VIES validation enabled by default with configurable mandatory/optional modes.
* Enhancement: Added caching mechanism for VIES responses to improve performance.
* Enhancement: VAT validation results stored in order metadata for compliance tracking.
* Enhancement: Graceful handling of VIES service unavailability.

= 3.3.1 =
* Fixed: Error getting companies from API.
* Added: Show API connection status in settings.
* Added: Support to custom tabs in settings.

= 3.3.0 =
* Enhancement: Added support to ERP Tax Types.
* Enhancement: Added support to payment methods from API.
* **MAJOR: Real-time VAT validation** - Live validation as customer types with 800ms debounce, visual feedback, and automatic checkout updates.
* **MAJOR: Dual API system** - VIES (primary, official EU, free) + VATSense (fallback, commercial, higher reliability).
* **MAJOR: B2B intra-community zero-rate** - Automatic 0% VAT for valid B2B transactions between different EU countries using tax class system.
* Enhancement: Modern Vanilla JavaScript implementation (no jQuery dependency) with Fetch API and AbortController.
* Enhancement: WooCommerce Gutenberg Blocks full support with MutationObserver for React field detection.
* Enhancement: VATSense integration for enhanced reliability (optional, free tier: 500 validations/month).
* Enhancement: Tax class "zero-rate" automatically created with 0% rates for all EU countries.
* Enhancement: VAT exemption properly applied using WooCommerce tax class system (fiscally correct).
* Enhancement: Automatic restoration of standard VAT when validation fails or field is emptied.
* Enhancement: Detailed logging system for debugging and compliance auditing.
* Enhancement: Country-specific minimum VAT length validation before API calls.
* Enhancement: Visual feedback system with status icons (checking, valid, invalid, warning).
* Enhancement: Clean minimal CSS design without backgrounds.
* Enhancement: Duplicate feedback container cleanup to prevent UI issues.
* Enhancement: Cache system: 24h for valid results, 1h for invalid results.
* Enhancement: Session management for VAT exemption persistence across checkout updates.
* Enhancement: Order metadata includes validation results, exemption status, and service used.
* Enhancement: Comprehensive English documentation for all VAT features.
* Fixed: VAT exemption incorrectly applied for same-country (domestic) transactions.
* Fixed: Tax not restored when VAT field is emptied or validation fails.
* Fixed: Multiple feedback messages not properly cleared.

= 3.2.1 =
* Enhancement: Added support to send alerts to admin when there are errors in the products sync, and orders sent to ERP.
* Fixed: Terms and conditions validation user registration not applies in Admin.
* Fixed: Error in VAT info in WooCommerce PDF Invoices & Packing Slips.

= 3.2.0.2 =
* Fixed: Error updating tax rates.

= 3.2.0.1 =
* Fixed: Constant not defined.

= 3.2.0 =
* Enhancement: Added support to update tax rates from EU database.
* Enhancement: Moved to a new plugin repository: https://wordpress.org/plugins/woocommerce-es/
* Enhancement: Gutenberg support for VAT field in checkout.

= 3.1.6 =
* Enhancement: Added variation images to product gallery for APIs that allows images in variations.
* Enhancement: Added support to import variable products without SKU in parent.
* Fixed: error saving category separator.
* Enhancement: Added support to payment methods from API.
* Enhancement: Added support to smart doctype in Holded.

= 3.1.5 =
* Enhancement: Added support to merge categories from API to WooCommerce.
* Enhancement: More robust import products. Prevents missing variables from API.
* Enhancement: Added support to Odoo company field.
* Enhancement: Added support to clean special chars in order data (Verifactu).
* Enhancement: Added support to approve document for Verifactu in some ERPs. First version for Holded.
* Enhancement: Added support to VAT Number plugin.
* Enhancement: added support to importing images in variations.
* Enhancement: Don't add image if already exists in WooCommerce.
* Enhancement: Added support to more SEO plugins.
* Enhancement: Added seo focus keyword to be generated by AI.
* Fixed: Solved static analysis errors.
* Fixed: error importing products without pricesale_discount.
* Fixed: error importing products with categories.
* Fixed: Better management for premium plugins addons license that complies with WordPress.org.

= 3.1.4 =
* Enhancement: Added tests for product simple and variable.
* Fixed: sync barcode in product variations.
* Fixed: sync categories criteria.
* Fixed: deprecation notice for WooCommerce.

= 3.1.3 =
* Enhancement: updated versions to PHP that are supported.
* Fixed: Fatal error in PHP 7.4.

= 3.1.2 =
* Fixed: Getting prices rates from variation products.
* Fixed: Zero stock in variation products was giving not manage stock.
* Fixed: Automated products sync not working properly.

= 3.1.1 =
* Added: Merge categories from API to WooCommerce. You select the equivalence in the settings.
* Fixed: Error in orphaned variations to prevent SKU errors.
* Fixed: Cron not running properly.

= 3.1.0 =
* Added: WP CLI command to import products.
* Fully support to import EAN to WooCommerce.
* Added: Import image products with different method.
* Added: Result API import in widget product.
* Added: Save parent SKU in product if does not exist.
* Added: Check if image product file exists before import.
* Fixed: AI connection not working in some cases.
* Fixed: Some errors getting products from shop to syncronize.
* Fixed: Not getting prices rates from API.
* Fixed: Prevent error when WooCommerce does not load the product.

= 3.0.1 =
* Minor fixes.
* Fixed: Some errors in Clientify connector.
* Fixed: Don't add shipping details if all products are virtual.
* Added support different Vat order variables: _billing_vat, _billing_vat_number, _billing_nif.
* Added suppport to [WC – APG Campo NIF/CIF/NIE](https://es.wordpress.org/plugins/wc-apg-nifcifnie-field/)

= 3.0.0 =
* Added AI Generate content for new products.
* Added: Import EAN new WooCommerce code.
* Added: Fix category separator.
* Added: Filter by Producto SKU.
* Added: Make downloable the invoice in My account.
* Added: Domain field.
* Added: Series number.
* Added: contact company.
* Added: Added order tags.
* Added: Company Field option.
* Added: Publish status in Merge Vars.
* Added: Split Categories in merge vars.
* Products widgets allow search product by SKU.
* Added: Sync with APIs with modified date.
* Added: Support to Paginated APIs.
* Added: Coupons sends to the API.
* Fixed: Taxes calculation in order.
* Added: Option to send orders to ERP when it's paid.
* Fixed: error calculating taxes from order products.
* Added: Option to log problems in WooCommerce logs.
* Fixed: error while updating order with shipping items.
* Fix: Required parameter $option_prefix follows optional.
* Minor fixes.

= 2.3.1 =
* Fixed: Added support to import billing vat from other plugins.

= 2.3.0 =
* Refactored for better internal structure.
* Added: Merge vars for product custom fields, taxonomies and product fields importer.
* Added: Import product from product view.
* Added: Send manually order to ERP.
* Added: Option to log problems in WooCommerce logs.
* Fixed: Orders with products without tax sending tax items.
* Fixed: Prevents fatal error in variations while have duplicated SKUs.
* Fixed: error while updating order with shipping items.
* Fixed: Required parameter $option_prefix follows optional.
* Minor fixes.

= 2.2.3 =
*   Fixed: Prevents error while importing products with duplicated SKUs.

= 2.2.2 =
*   Fixed: Error when variable product has no attributes.
*   Fixed: error in variable products.

= 2.2.1 =
*   Fixed: Shipping and Fee items not imported correctly.

= 2.2.0 =
*   Added Series number.
*   Column in orders for Holded document.
*   Added widget in order to send to Holded or update to Holded.
*   Added send order with items fee.
*   Fixed: Error not sending order with free shipping item.
*   Fixed: Dates with invoices depends if you force the order or create by completed order.
*   Fixed: Calculation of taxes.
*   Fixed: Country code sent to Holded.
*   Fixed: Saves product options taxes.

= 2.1.2 =
*   Fix: Prevents table sync not created.
*   Fix: Some clients have less lenght in key products.

= 2.1.1 =
*   Fix: Blank filter not working.
*   Fix: Error importing products in database.

= 2.1.0 =
*   New Admin design.
*   Protected folder invoice.
*   Order columns for API.
*   Added option for Categories as attribute.
*   Fix: error message order.
*   Fix: Shipping order cost fixed not implemented in order.
*   Fix: Fatal error no products in automation.
*   Fix: Faster manual sync.
*   Fix: Errors in PHP8.
*   Premium Fix: Shipping order info updated.
*   Premium Fix: Fix not importing variables products.

= 2.0.1 =
*   Fix: Filtered product if empty.
*   Fix: Error rates empty.

= 2.0 =
*   Removed Freemius as engine sell.
*   Removed Support to Easy Digital Downloads.
*   Add Tags as list (separated with commas).
*   Add VAT Info in checkout.
*   Option to Company field in checkout.
*   Premium: Add PDF generated from Holded.
*   Premium: Better sync management WooCommerce Action Scheduler.
*   Premium: Refactoring code from free and fremium.
*   Premium: Select design in document holded.

= 1.4 =
*   Option to not create document if order is free.

= 1.3 =
*   Sync orders to Holded (Premium) automatically and force manually for past orders.
*   Sync Pack products to Holded (Premium).
*   Fix: Attributes duplicated in variation product not imported.
*   Fix: Categories not imported in simple products.

= 1.2 =
*   Automate your syncronization! (Premium).
*   Option email when is finished (Premium).
*   Fix sku saved for EDD.
*   Better metavalue search for SKU.
*   Fix Holded Pagination (thanks to itSerra).
*   Fix SKU variation (thanks to itSerra).

= Earlier versions =

For the changelog of earlier versions, please refer to the separate changelog.txt file.

== Links ==
*	[Closemarketing](https://close.marketing/)
*	[Closemarketing plugins](https://profiles.wordpress.org/closemarketing/#content-plugins)
