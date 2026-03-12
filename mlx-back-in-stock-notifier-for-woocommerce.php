<?php
/**
 * Plugin Name: MLX Back In Stock Notifier for WooCommerce
 * Plugin URI: https://modulux.net/projects/back-in-stock-notifier-for-woocommerce
 * Description: Allows customers to opt-in for email notifications when products are back in stock.
 * Version: 1.0.0
 * Author: modulux.net
 * Author URI: https://modulux.net
 * Text Domain: mlx-back-in-stock-notifier-for-woocommerce
 * Domain Path: /languages
 * License: GPL2
 * Copyright: 2026 modulux.net
 * Requires at least: 5.0
 * Tested up to: 6.9
 * Requires PHP: 7.4
 * WC requires at least: 3.0
 * WC tested up to: 10.6
 * Requires: WooCommerce
 */

if (!defined('ABSPATH')) exit;

// Define constants
define('MLX_WOO_BIS_PATH', plugin_dir_path(__FILE__));
define('MLX_BIS_VERSION', '1.0.0');

require_once MLX_WOO_BIS_PATH . 'includes/class-bis-handler.php';

require_once MLX_WOO_BIS_PATH . 'includes/class-bis-admin.php';

// Declare compatibility with modern WooCommerce features
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        // Declare High-Performance Order Storage (HPOS) compatibility
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        
        // Declare Cart & Checkout Blocks compatibility
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

// Initialize the plugin
add_action('plugins_loaded', array('Woo_BIS_Handler', 'init'));

// Initialize admin features
add_action('plugins_loaded', array('Woo_BIS_Admin', 'init'));

// Enqueue CSS for admin page
add_action('admin_enqueue_scripts', function() {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a read-only admin page
    if (isset($_GET['page']) && $_GET['page'] === 'woo-bis') {
        wp_enqueue_style('mlx-bis-admin', plugin_dir_url(__FILE__) . 'assets/mlx-bis.css');
    }
});

// Enqueue CSS for frontend my-account/stock-notifications page
add_action('wp_enqueue_scripts', function() {
    if (is_account_page() || is_product()) {
        wp_enqueue_style('mlx-bis-frontend', plugin_dir_url(__FILE__) . 'assets/mlx-bis.css');
        wp_enqueue_script('mlx-bis-frontend', plugin_dir_url(__FILE__) . 'assets/mlx-bis.js', array('jquery'), MLX_BIS_VERSION, true);
    }
});

// Register activation hook to flush rewrite rules for the account tab
register_activation_hook(__FILE__, function() {
    add_rewrite_endpoint('stock-notifications', EP_PAGES);
    flush_rewrite_rules();
});