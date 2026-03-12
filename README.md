# MLX Back In Stock Notifier for WooCommerce

A lightweight, native, and high-performance WooCommerce extension that lets customers subscribe to restock alerts and allows store managers to view waitlists and suggest alternative products.

## Features

### Frontend (Customer Experience)
- **Notify Me Button:** Automatically displays a subscribe button on out-of-stock single product pages.
- **My Account Integration:** Adds a custom "Stock Notifications" endpoint to the WooCommerce account area for users to view and manage their tracked items.
- **Automatic Restock Alerts:** Sends a customizable email to all waiting customers immediately when a product's stock status is updated to "In Stock".
- **Full Variable Product Support:** Works flawlessly with product variations (e.g., sizes, colors). Customers can subscribe to specific out-of-stock variations, and the waitlist UI updates instantly as they select different options from the dropdowns.

### Backend (Admin Experience)
- **Waitlist Dashboard:** Located under `WooCommerce > Stock Waitlists`. Displays an aggregated view of products with the highest demand.
- **Alternative Product Suggestions:** Admins can select an out-of-stock item, use a native WooCommerce AJAX search to find a similar in-stock product, and blast a custom email template to all waiting customers.
- **Waitlist Pagination:** Safely view hundreds of waiting customers without crashing the admin panel.

### Technical & Compatibility
- **HPOS Ready:** Fully compatible with WooCommerce High-Performance Order Storage.
- **Block Ready:** Compatible with Cart and Checkout Blocks.
- **Performance:** Strictly adheres to WPCS. Uses standard `user_meta` for relationships and native Object Caching for dashboard aggregation queries. No direct SQL queries without caching.
- **Automatic Cleanup:** Triggers on `before_delete_post` to cleanly remove orphaned waitlist data when a product is deleted.
- **Asynchronous Email Queuing:** Utilizes WooCommerce Action Scheduler (as_enqueue_async_action). Bulk emails for restocks and alternative products are processed safely in the background, preventing PHP timeouts and frontend lag during product saves.
- **Performance & Security Optimized:** Zero inline JavaScript or CSS. All assets are properly enqueued and localized, ensuring maximum compatibility with caching plugins (like WP Rocket) and strict Content Security Policies (CSP).

## Installation

1. Download the latest release.
2. Extract and upload the folder to your `wp-content/plugins/` directory.
3. Activate the plugin via the WordPress admin dashboard.
4. **Important:** Navigate to `Settings > Permalinks` and click **Save Changes** to flush rewrite rules and activate the My Account endpoint.

## Development & Architecture

- **Main File:** `woo-back-in-stock.php` handles initialization and compatibility declarations.
- **Handler Class:** `includes/class-bis-handler.php` handles frontend display, AJAX subscriptions, stock status hooks, and My Account rendering.
- **Admin Class:** `includes/class-bis-admin.php` handles the custom dashboard, WooCommerce native select2 integrations, waitlist aggregation, and alternative product mailing logic.

## License

This project is licensed under the GPLv2 or later. See the `LICENSE` file for details. Copyright 2026 modulux.net.