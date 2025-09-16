=== Connect Ecommerce - Connect WooCommerce Shop to ERP/CRM ===
Contributors: closetechnology, closemarketing, davidperez, sacrajaimez
Tags: connect, integrate, ecommerce, woocommerce, connect woocommerce
Donate link: https://close.marketing/go/donate/
Requires at least: 5.0
Requires PHP: 7.4
Tested up to: 6.8
Stable tag: 3.1.4
Version: 3.1.4
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect WooCommerce with ERPs and CRMs. Products, Clients and Orders with ERP/CRM.

== Description ==

Connect your WooCommerce store to your ERP or CRM software. This plugin makes it easy to connect your store by synchronizing products, customers, and orders.

Save hours of administrative work by eliminating the need to manually enter products, customers, and orders.

You can now use AI to generate product marketing descriptions based on information from your ERP/CRM.

There’s no need for additional plugins to request VAT numbers from companies — this plugin has it covered.

**How does it work?**
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

This plugin serves as the foundation for various connectors. The free version supports:
- [Clientify](https://close.marketing/likes/clientify/)

Premium connectors include:
- [Holded](https://close.technology/en/wordpress-plugins/connect-woocommerce-holded/)
- [Odoo](https://close.technology/en/wordpress-plugins/connect-woocommerce-odoo/)
- [NEO POS](https://close.technology/en/wordpress-plugins/connect-woocommerce-neo/)
- [Datisa](https://close.technology/en/wordpress-plugins/connect-woocommerce-datisa/)

Need another connector? We offer custom integration services. [Contact us](https://close.technology/en/contact/)

== Frequently Asked Questions ==

= What does this plugin do? =
Connect Ecommerce allows you to import products from an ERP/CRM to your WooCommerce store via API. It also sends orders from the store to your ERP/CRM and creates associated customers.

= How are products and orders synced? =
Products are synced from the ERP/CRM to WooCommerce because the ERP should always contain the most up-to-date business information. This ensures accurate management of products, prices, and other business data.

Orders are synced from WooCommerce to the ERP/CRM so that every time a customer places an order, it is sent to your ERP for proper order and invoice management.

= What happens when a product is out of stock? =
By default, the product disappears from the store catalog but remains visible to search engines. This is intentional and matches the expected store behavior.

= Does it comply with Verifactu? =
Yes, it does. It makes the order data more readable for Verifactu.

== Installation ==

- Go to Add Plugin, search for Connect Ecommerce, and Install it. Then Activate the plugin. You will need to have WooCommerce Installed.
- Go to WooCommerce > Connect Ecommerce for the configuration.

== Developers ==
[Official Repository GitHub](https://github.com/closemarketing/connect-ecommerce)

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

== Changelog ==

= 3.1.5 =
* Enhancement: Added support to merge categories from API to WooCommerce.
* Enhancement: More robust import products. Prevents missing variables from API.
* Enhancement: Added support to Odoo company field.
* Enhancement: Added support to clean special chars in order data (Verifactu).
* Enhancement: Added support to approve document for Verifactu in some ERPs. First version for Holded.
* Enhancement: Added support to VAT Number SIMBA Hosting plugin.
* Enhancement: added support to importing images in variations.
* Enhancement: Don't add image if already exists in WooCommerce.
* Enhancement: Added support to more SEO plugins.
* Enhancement: Added seo focus keyword to be generated by AI.
* Fixed: Solved static analysis errors.
* Fixed: error importing products without pricesale_discount.
* Fixed: error importing products with categories.

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
