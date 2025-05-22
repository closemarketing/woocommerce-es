=== Connect Ecommerce - Connect WooCommerce Shop to ERP/CRM ===
Contributors: closetechnology, closemarketing, davidperez, sacrajaimez
Tags: connect, integrate, ecommerce, woocommerce, connect woocommerce
Donate link: https://close.marketing/go/donate/
Requires at least: 5.0
Requires PHP: 7.0
Tested up to: 6.8
Stable tag: 3.0.1
Version: 3.0.1
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect Ecommerce with ERPs and CRMs. Products and Orders from ERP/CRM to WooCommerce.

== Description ==

This plugin allows you to products from Clientify to WooCommerce. 

You can import products, and it will create new products if it does not find the SKU code from your WooCommerce. If the SKU exists, it will import all data except title and description from the product. The stock will be imported as well.

These are the features:
- Import categories from CRM/ERP.
- Import attributes as brands or others.
- Import variable products.
- Automate the syncronization.
- Send Orders to CRM/ERP.
- Send generated CRM/ERP Document attached in WooCommerce notifications.
- Option to select the Design of the generated CRM/ERP document.
- Import pack products from CRM/ERP.

At this time, Connect Ecommerce supports in free version:
- [Clientify](https://close.marketing/likes/clientify/)

And you will find, that there are Premium Addons to support:
- [Holded CRM](https://close.technology/en/wordpress-plugins/connect-woocommerce-holded/)
- [Odoo](https://close.technology/en/wordpress-plugins/connect-woocommerce-odoo/)
- [NEO TPV](https://close.technology/en/wordpress-plugins/connect-woocommerce-neo/)


== Installation ==

- Extract the zip file and just drop the contents in the wp-content/plugins/ directory of your
WordPress installation and then activate the Plugin from Plugins page.
- Go to WooCommerce > Connect Ecommerce for the configuration.

== Developers ==
[Official Repository GitHub](https://github.com/closemarketing/connect-ecommerce)

You can use WP CLI to import products from the command line. The command is:
```
wp conecom products --update --ai=none,new,all
```

== External services ==

This plugin connects to an API to make AI SEO descriptions and product information.

It send the product information to the API and it returns the SEO description and product information.
Services:
- OpenAI: [Terms of use](https://openai.com/policies/row-terms-of-use/) and [Privact policy](https://openai.com/policies/row-privacy-policy/)
- DeepSeek: [Terms of use](https://cdn.deepseek.com/policies/en-US/deepseek-terms-of-use.html) and [Privacy policy](https://cdn.deepseek.com/policies/en-US/deepseek-privacy-policy.html)

The connector base is to connect Clientify, an CRM Marketing Automation tool. [Terms of use](https://clientify.com/aviso-legal/) and [privacy policy](https://clientify.com/politicas-de-privacidad). 

== Changelog ==

= 3.1.0 =
* Added: WP CLI command to import products.
* Fully support to import EAN to WooCommerce.
* Added: Import image products with different method.
* Added: Result API import in widget product.
* Added: Save parent SKU in product if does not exist.
* Fixed: AI connection not working in some cases.
* Fixed: Some errors getting products from shop to syncronize.
* Fixed: Not getting prices rates from API.

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
