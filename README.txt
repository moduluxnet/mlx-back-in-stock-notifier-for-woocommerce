=== MLX Back In Stock Notifier for WooCommerce ===
Contributors: modulux
Tags: woocommerce, back in stock, stock notifier, inventory, waitlist
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
WC requires at least: 3.0
WC tested up to: 10.6
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A lightweight, HPOS-compatible solution for WooCommerce stock notifications with advanced admin waitlist management.

== Description ==

MLX Back In Stock Notifier for WooCommerce allows your customers to opt-in for email notifications when their desired out-of-stock products are replenished. 

Designed strictly to WordPress and WooCommerce coding standards, this plugin provides a seamless frontend experience for customers and a powerful backend management interface for store administrators, without bloating your database.

### Core Features
* **Automated Notifications:** Customers receive an instant email the moment a product's stock status changes to "In Stock".
* **Customer Management:** Adds a native "Stock Notifications" endpoint to the WooCommerce My Account page where users can view and remove their waitlist items.
* **Admin Waitlist Dashboard:** View an aggregated list of all out-of-stock products that have customers waiting.
* **Alternative Product Suggestions:** Manually email waiting customers to suggest a different, in-stock product using WooCommerce's native AJAX product search and customizable message templates.
* **Performance Optimized:** Uses native WordPress metadata API and caching. No custom database tables.
* **Fully Compatible:** Officially declares compatibility with WooCommerce High-Performance Order Storage (HPOS) and Cart/Checkout Blocks.
* **Clean Cleanup:** Automatically removes waitlist data if a product is permanently deleted from the store.
* **Enterprise-Grade Scalability:** Built on WooCommerce Action Scheduler. Whether you have 10 or 10,000 customers on a waitlist, emails are processed safely in the background in batches. Your site will never freeze or crash when updating stock.
* **Full Variable Product Support:** Works flawlessly with product variations (e.g., sizes, colors). Customers can subscribe to specific out-of-stock variations, and the waitlist UI updates instantly as they select different options from the dropdowns.
* **Performance & Security Optimized:** Zero inline JavaScript or CSS. All assets are properly enqueued and localized, ensuring maximum compatibility with caching plugins (like WP Rocket) and strict Content Security Policies (CSP).

== Installation ==

1. Upload the `mlx-back-in-stock-notifier-for-woocommerce` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. **Important:** Go to Settings > Permalinks and click 'Save Changes'. This registers the new "Stock Notifications" tab in the WooCommerce My Account area.
4. Access the admin dashboard via WooCommerce > Stock Waitlists.

== Frequently Asked Questions ==

= Is this plugin compatible with High-Performance Order Storage (HPOS)? =
Yes. The plugin is 100% compatible with HPOS and does not interact with legacy order tables.

= Does this work with WooCommerce Cart and Checkout Blocks? =
Yes. Compatibility with modern block-based checkout flows is explicitly declared and supported.

= Can guest users subscribe to back-in-stock notifications? =
Currently, to ensure high-quality leads and prevent spam, users must be logged into their customer account to subscribe. They manage their subscriptions directly from their "My Account" page.

= How do I suggest an alternative product to waiting customers? =
Go to WooCommerce > Stock Waitlists. Click "Manage" next to the out-of-stock product. On the left side of the screen, you can search for an alternative product, customize the email message, and send it to all waiting customers.

= Will this slow down my store? =
No. The plugin uses WordPress's native object caching (`wp_cache_get` / `wp_cache_set`) for heavy dashboard queries and standard user metadata for storage, ensuring maximum performance.

= I updated my stock, why haven't the emails arrived immediately? =
To protect your server from crashing, emails are queued using WooCommerce's native background processor (Action Scheduler). They are sent safely in batches behind the scenes. You can view the queue in real-time under WooCommerce > Status > Scheduled Actions.

== Screenshots ==

1. The "Notify Me" button on an out-of-stock product page.
2. The "Stock Notifications" tab inside the customer's My Account area.
3. The Admin Waitlist Dashboard showing aggregated waiting counts.
4. The Alternative Product Suggestion management screen.

== Changelog ==

= 1.0.0 =
* Initial release.
* Feature: Automated back-in-stock email notifications using WooCommerce Action Scheduler for enterprise-grade background processing.
* Feature: Full support for variable products (subscribe to specific variations).
* Feature: Admin dashboard to view waitlists and send manual alternative product suggestions.
* Performance: Optimized asset loading (no inline scripts/styles) for strict CSP and caching compatibility.