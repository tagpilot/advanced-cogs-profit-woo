=== Advanced COGS & Profit for WooCommerce ===
Contributors: tagconcierge
Tags: woocommerce, cogs, profit, analytics, cost of goods sold
Requires at least: 5.1
Tested up to: 6.8
Requires PHP: 7.0
Stable tag: 1.0.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Unlock powerful profit insights for your WooCommerce store. This plugin extends WooCommerce's native Cost of Goods Sold (COGS) functionality, allowing you to create dynamic COGS rules and track your true profitability in real-time.

== Description ==

Advanced COGS & Profit for WooCommerce gives you the tools to move beyond basic revenue tracking and understand your store's financial health with precision. Instead of manually setting a cost for each product, you can now apply costs in bulk using a flexible rules engine based on product categories, tags, or attributes.

The plugin also allows you to account for order-level expenses like payment processing and fulfillment fees, giving you a complete picture of your costs. All this information is neatly summarized on each order page, showing you the total revenue, costs, net profit, and profit margin for every sale.

## Features

-   Apply COGS in bulk using a flexible rules engine.
-   Create rules based on product **category**, **tag**, or **attribute**.
-   Use multiple operators for rules: equals, doesn't equal, contains, and doesn't contain.
-   Define additional order-level costs on top of COGS:
    -   Payment processing fees (percentage-based)
    -   Fulfilment costs (percentage-based)
-   See detailed COGS and profit calculations on every order page.
-   View breakdowns of costs, including product costs, shipping, fulfillment, and payment processing.

== Installation ==

1.  Upload `advanced-cogs-profit-for-woocommerce` to the `/wp-content/plugins/` directory or install it from the WordPress plugins directory.
2.  Activate the plugin through the 'Plugins' menu in WordPress.
3.  Go to `WooCommerce > Settings > Advanced > COGS & Profit` to configure the settings.

== Frequently Asked Questions ==

= How does it work? =

The plugin calculates COGS and profit for each order using a flexible rules engine. Instead of manually setting a COGS value for every single product, you can create global rules that apply costs automatically based on conditions you define. This saves you time and ensures consistency.

= How do I set up a rule for a product attribute? =

When creating a rule based on an attribute, you need to specify both the attribute's name and its value in the 'Matching value' field. Use the format `attribute_name:attribute_value`. For example, to match a product with a "Color" attribute set to "Blue", you would enter `color:blue`.

= Is the matching for rules case-sensitive? =

Yes, all matching for categories, tags, and attributes is case-sensitive. For example, a rule for the category `Books` will not match products in a category named `books`.

== Screenshots ==

1.  The COGS & Profit settings page in WooCommerce.
2.  The profit calculation meta box on the order details page.
3.  The profit calculation meta box on the product details page.


== Changelog ==

= 1.1.0 =

*   **Feature**: Added support for product attributes in the COGS rules engine.
*   **Feature**: Added more comparison operators: "doesn't equal", "contains", and "doesn't contain".
*   **Fix**: Corrected an issue where COGS rules were not saving correctly.
*   **Fix**: Resolved undefined variable errors in profit calculation.
*   **Tweak**: Added an informational notice in the admin panel explaining the attribute rule syntax.

= 1.0.1 =

*   Initial version.

