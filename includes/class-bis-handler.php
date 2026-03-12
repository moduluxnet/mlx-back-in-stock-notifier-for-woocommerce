<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Woo_BIS_Handler {

    public static function init() {
        // Frontend: Show opt-in button
        add_action('woocommerce_single_product_summary', array(__CLASS__, 'render_opt_in_form'), 35);
        add_action('wp_ajax_bis_subscribe', array(__CLASS__, 'handle_subscription'));
        add_action('wp_ajax_nopriv_bis_subscribe', array(__CLASS__, 'handle_subscription'));

        // Backend: Monitor stock changes
        //add_action('woocommerce_product_set_stock', array(__CLASS__, 'check_stock_status_and_notify'));
        //add_action('woocommerce_variation_set_stock', array(__CLASS__, 'check_stock_status_and_notify'));
        add_action('woocommerce_product_set_stock', array(__CLASS__, 'check_stock_and_notify'));
        add_action('woocommerce_variation_set_stock', array(__CLASS__, 'check_stock_and_notify'));
        add_action('woocommerce_product_set_stock_status', array(__CLASS__, 'check_stock_status_and_notify'), 10, 3);        

        // Account: Manage notifications
        add_filter('woocommerce_account_menu_items', array(__CLASS__, 'add_account_menu_item'));
        add_action('init', array(__CLASS__, 'add_endpoint'));
        add_action('woocommerce_account_stock-notifications_endpoint', array(__CLASS__, 'endpoint_content'));

        // Background Processing: Send queued emails
        add_action('mlx_bis_send_restock_email', array(__CLASS__, 'process_restock_email'), 10, 2);        

        // Cleanup: If product is deleted
        add_action('before_delete_post', array(__CLASS__, 'cleanup_deleted_product'));
    }

    public static function check_stock_status_and_notify($product_id, $stock_status, $product) {
        if ($stock_status === 'instock') {
            self::check_stock_and_notify($product);
        }
    }    

    public static function render_opt_in_form() {
        global $product;

        $is_variable = $product->is_type('variable');

        // If it's a simple product and in stock, do nothing.
        if (!$is_variable && $product->is_in_stock()) {
            return;
        }

        $user_id = get_current_user_id();
        $product_id = $product->get_id();
        
        // Hide the wrapper by default ONLY if it's a variable product (JS will reveal it)
        $display_style = $is_variable ? ' bis-hidden' : '';
        
        echo '<div class="bis-notification-wrapper' . esc_attr($display_style) . '" id="bis-wrapper">';

        if (!is_user_logged_in()) {
            echo '<p class="bis-login-notice">' . esc_html__('Please log in to subscribe for back in stock notifications.', 'mlx-back-in-stock-notifier-for-woocommerce') . '</p>';
            echo '</div>'; // close wrapper
        } else {
            // Pass all of the user's active subscriptions to JavaScript so we can check variations instantly
            $user_subs = get_user_meta($user_id, '_bis_subscribed_product');
            $subs_json = wp_json_encode(is_array($user_subs) ? array_map('intval', $user_subs) : array());
            $subscribed = !$is_variable && in_array($product_id, (array)$user_subs);

            echo '<div id="bis-subscribed-msg" style="' . ($subscribed ? '' : 'display:none;') . '">';
            echo '<p>' . esc_html__('You are on the waitlist for this item.', 'mlx-back-in-stock-notifier-for-woocommerce') . '</p>';
            echo '<a href="' . esc_url(wc_get_account_endpoint_url('stock-notifications')) . '" class="button">' . esc_html__('Manage Notifications', 'mlx-back-in-stock-notifier-for-woocommerce') . '</a>';
            echo '</div>';

            echo '<div id="bis-form-msg" style="' . (!$subscribed ? '' : 'display:none;') . '">';
            echo '<p>' . esc_html__('This item is currently out of stock. Want an email when it returns?', 'mlx-back-in-stock-notifier-for-woocommerce') . '</p>';
            echo '<button type="button" id="bis-subscribe-btn" class="button alt" data-id="' . esc_attr($product_id) . '" data-nonce="' . esc_attr(wp_create_nonce('bis_subscribe_nonce')) . '">' . esc_html__('Notify Me', 'mlx-back-in-stock-notifier-for-woocommerce') . '</button>';
            echo '</div>';
            echo '</div>'; // close wrapper
        }

        // Pass the user's current subscriptions to JavaScript for instant variation checks
        wp_localize_script( 'mlx-bis-frontend', 'mlx_bis_data', array(
            'userSubs' => $subs_json,
            'notifyText' => esc_js(__('Notify Me', 'mlx-back-in-stock-notifier-for-woocommerce')),
            'subscribedText' => esc_js(__('Subscribed', 'mlx-back-in-stock-notifier-for-woocommerce'))            
        ) );
    }        

    public static function handle_subscription() {
        check_ajax_referer('bis_subscribe_nonce', 'nonce');
        
        if( !is_user_logged_in() || !isset($_POST['product_id']) ) {
            wp_die();
        }

        $product_id = intval($_POST['product_id']);
        $user_id = get_current_user_id();

        if ($product_id && $user_id) {
            if (!self::is_user_subscribed($user_id, $product_id)) {
                add_user_meta($user_id, '_bis_subscribed_product', $product_id);
                
                // Clear the admin waitlist cache so the counter updates immediately
                wp_cache_delete('bis_waitlist_aggregate', 'woo_bis');
            }
        }
        wp_die();
    }

    public static function check_stock_and_notify($product) {
        if ($product->is_in_stock()) {
            $product_id = $product->get_id();
            
            // We only need the user IDs now, which is much faster to query
            $users = get_users(array(
                'meta_key'   => '_bis_subscribed_product',
                'meta_value' => $product_id,
                'fields'     => 'ID'
            ));

            if (empty($users)) {
                return;
            }

            foreach ($users as $user_id) {
                // Queue the email to be sent in the background safely
                if (function_exists('as_enqueue_async_action')) {
                    as_enqueue_async_action('mlx_bis_send_restock_email', array($user_id, $product_id));
                } else {
                    // Fallback just in case Action Scheduler is disabled
                    self::process_restock_email($user_id, $product_id);
                }
            }
            
            // Clear the admin waitlist cache since we just queued these notifications
            wp_cache_delete('bis_waitlist_aggregate', 'woo_bis');
        }
    }

    public static function process_restock_email($user_id, $product_id) {
        // Get fresh user and product data at the exact moment the email sends
        $user = get_userdata($user_id);
        $product = wc_get_product($product_id);

        if (!$user || !$product) return;

        // Double-check they are still subscribed in case they removed it manually while in the queue
        if (!self::is_user_subscribed($user_id, $product_id)) return;

        // Get WooCommerce official sender details to prevent SMTP blocking
        $from_name  = get_option('woocommerce_email_from_name', get_bloginfo('name'));
        $from_email = get_option('woocommerce_email_from_address', get_bloginfo('admin_email'));
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>'
        );

        $to = $user->user_email;
        $subject = __('Item back in stock:', 'mlx-back-in-stock-notifier-for-woocommerce') . ' ' . $product->get_name();
        
        $message  = '<p>' . __('Great news!', 'mlx-back-in-stock-notifier-for-woocommerce') . '</p>';
        $message .= '<p><strong>' . $product->get_name() . '</strong> ' . __('is back in stock.', 'mlx-back-in-stock-notifier-for-woocommerce') . '</p>';
        $message .= '<p><a href="' . esc_url(get_permalink($product_id)) . '">' . __('Shop now', 'mlx-back-in-stock-notifier-for-woocommerce') . '</a></p>';

        /*if (wp_mail($to, $subject, $message, $headers)) {
            // Delete the meta only after the background task successfully sends it
            delete_user_meta($user_id, '_bis_subscribed_product', $product_id);
        }*/
        // Attempt to send the email
        wp_mail($to, $subject, $message, $headers);

        // Always remove them from the list so they aren't stuck forever if the mail server hiccups
        delete_user_meta($user_id, '_bis_subscribed_product', $product_id);            
    }

    public static function add_account_menu_item($items) {
        $items['stock-notifications'] = __('Stock Notifications', 'mlx-back-in-stock-notifier-for-woocommerce');
        return $items;
    }

    public static function add_endpoint() {
        add_rewrite_endpoint('stock-notifications', EP_PAGES);
    }

    public static function endpoint_content() {
        $user_id = get_current_user_id();
        
        if (isset($_GET['remove_bis'])) {
            check_admin_referer('bis_remove_' . intval($_GET['remove_bis']));
            delete_user_meta($user_id, '_bis_subscribed_product', intval($_GET['remove_bis']));
            echo '<div class="woocommerce-message">' . esc_html__('Notification removed.', 'mlx-back-in-stock-notifier-for-woocommerce') . '</div>';
        }

        $subscriptions = get_user_meta($user_id, '_bis_subscribed_product');

        if (empty($subscriptions)) {
            echo '<p>' . esc_html__('You have no active stock notifications.', 'mlx-back-in-stock-notifier-for-woocommerce') . '</p>';
            return;
        }

        echo '<table class="woocommerce-orders-table bis-woocommerce-orders-table v-table">';
        echo '<thead><tr><th>' . esc_html__('Product', 'mlx-back-in-stock-notifier-for-woocommerce') . '</th><th>' . esc_html__('Action', 'mlx-back-in-stock-notifier-for-woocommerce') . '</th></tr></thead>';
        foreach ($subscriptions as $pid) {
            $p = wc_get_product($pid);
            if (!$p) continue;
            echo '<tr>';
            echo '<td><a href="' . esc_url($p->get_permalink()) . '" target="_blank">' . esc_html($p->get_name()) . '</a></td>';
            echo '<td class="bis-woocommerce-table__cell-actions"><a href="' . esc_url(wp_nonce_url(add_query_arg('remove_bis', $pid), 'bis_remove_' . $pid)) . '" class="button">' . esc_html__('Remove', 'mlx-back-in-stock-notifier-for-woocommerce') . '</a></td>';
            echo '</tr>';
        }
        echo '</table>';
    }

    public static function cleanup_deleted_product($post_id) {
        if (get_post_type($post_id) !== 'product') {
            return;
        }

        $users = get_users(array(
            'meta_key'   => '_bis_subscribed_product',
            'meta_value' => $post_id,
            'fields'     => 'ID',
        ));

        if (empty($users)) {
            return;
        }

        foreach ($users as $user_id) {
            delete_user_meta($user_id, '_bis_subscribed_product', $post_id);
            clean_user_cache($user_id);
        }
    }

    private static function is_user_subscribed($user_id, $product_id) {
        $subs = get_user_meta($user_id, '_bis_subscribed_product');
        return in_array($product_id, $subs);
    }
}
