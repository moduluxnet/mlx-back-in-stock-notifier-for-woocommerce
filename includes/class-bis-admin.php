<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Woo_BIS_Admin {

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_scripts'));
        add_action('admin_post_bis_send_alternative', array(__CLASS__, 'handle_alternative_email'));
        
        // Add the background processing hook for alternative emails (accepts 5 arguments)
        add_action('mlx_bis_send_alternative_email', array(__CLASS__, 'process_alternative_email'), 10, 5);
    }

    public static function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Back In Stock Waitlists', 'mlx-back-in-stock-notifier-for-woocommerce'),
            __('Stock Waitlists', 'mlx-back-in-stock-notifier-for-woocommerce'),
            'manage_woocommerce', // Allows Admins and Shop Managers
            'woo-bis',
            array(__CLASS__, 'render_admin_page')
        );
    }

    public static function enqueue_admin_scripts($hook) {
        // Only load scripts on our specific plugin page
        if ($hook === 'woocommerce_page_woo-bis') {
            wp_enqueue_style('woocommerce_admin_styles');
            wp_enqueue_script('wc-enhanced-select'); // Native WC Select2 for product search
        }
    }

    public static function render_admin_page() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'mlx-back-in-stock-notifier-for-woocommerce'));
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a read-only admin page
        $tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'waitlists';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a read-only admin page
        $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : '';

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Back In Stock Notifier', 'mlx-back-in-stock-notifier-for-woocommerce') . '</h1>';
        
        // Render Tabs
        echo '<h2 class="nav-tab-wrapper">';
        echo '<a href="?page=woo-bis&tab=waitlists" class="nav-tab ' . ($tab === 'waitlists' ? 'nav-tab-active' : '') . '">' . esc_html__('Waitlists', 'mlx-back-in-stock-notifier-for-woocommerce') . '</a>';
        echo '<a href="?page=woo-bis&tab=help" class="nav-tab ' . ($tab === 'help' ? 'nav-tab-active' : '') . '">' . esc_html__('Help & About', 'mlx-back-in-stock-notifier-for-woocommerce') . '</a>';
        echo '</h2>';

        // Display success notices if redirected from an action
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a read-only admin page
        if (isset($_GET['message']) && sanitize_text_field(wp_unslash($_GET['message'])) === 'sent') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Alternative product suggestion sent successfully.', 'mlx-back-in-stock-notifier-for-woocommerce') . '</p></div>';
        }

        if ($tab === 'waitlists') {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a read-only admin page
            if ($action === 'manage' && isset($_GET['product_id'])) {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a read-only admin page
                self::render_manage_product(intval(wp_unslash($_GET['product_id'])));
            } else {
                self::render_waitlists_table();
            }
        } elseif ($tab === 'help') {
            self::render_help_tab();
        }

        echo '</div>'; // End wrap
    }

    private static function render_waitlists_table() {
        global $wpdb;

        // Fetch aggregated waitlist counts. We use caching to avoid the DB warnings you saw earlier.
        $aggregate_data = wp_cache_get('bis_waitlist_aggregate', 'woo_bis');
        
        if (false === $aggregate_data) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery
            $aggregate_data = $wpdb->get_results( "
                SELECT meta_value as product_id, COUNT(user_id) as wait_count 
                FROM {$wpdb->usermeta} 
                WHERE meta_key = '_bis_subscribed_product' 
                GROUP BY meta_value 
                ORDER BY wait_count DESC
            " );
            // phpcs:enable
            wp_cache_set('bis_waitlist_aggregate', $aggregate_data, 'woo_bis', 5 * MINUTE_IN_SECONDS);
        }

        if (empty($aggregate_data)) {
            echo '<p>' . esc_html__('There are currently no users waiting for out-of-stock products.', 'mlx-back-in-stock-notifier-for-woocommerce') . '</p>';
            return;
        }

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Product Name', 'mlx-back-in-stock-notifier-for-woocommerce') . '</th>';
        echo '<th>' . esc_html__('Waiting Customers', 'mlx-back-in-stock-notifier-for-woocommerce') . '</th>';
        echo '<th>' . esc_html__('Current Stock Status', 'mlx-back-in-stock-notifier-for-woocommerce') . '</th>';
        echo '<th>' . esc_html__('Actions', 'mlx-back-in-stock-notifier-for-woocommerce') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($aggregate_data as $row) {
            $product = wc_get_product($row->product_id);
            if (!$product) continue;

            $manage_url = add_query_arg(array('page' => 'woo-bis', 'action' => 'manage', 'product_id' => $row->product_id), admin_url('admin.php'));
            $stock_status = $product->is_in_stock() ? __('In Stock', 'mlx-back-in-stock-notifier-for-woocommerce') : __('Out of Stock', 'mlx-back-in-stock-notifier-for-woocommerce');

            echo '<tr>';
            echo '<td><strong><a href="' . esc_url(get_edit_post_link($row->product_id)) . '">' . esc_html($product->get_name()) . '</a></strong></td>';
            echo '<td>' . esc_html($row->wait_count) . '</td>';
            echo '<td>' . esc_html($stock_status) . '</td>';
            echo '<td><a href="' . esc_url($manage_url) . '" class="button button-primary">' . esc_html__('Manage', 'mlx-back-in-stock-notifier-for-woocommerce') . '</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    /**
     * Displays the admin heading for managing the waitlist of a specific product.
     *
     * Outputs an HTML h2 element containing the product name within a translatable
     * message. The product name is escaped for safe HTML output.
     *
     * @since 1.0.0
     * @param WC_Product $product The WooCommerce product object.
     * @return void
     */
    private static function render_manage_product($product_id) {
        $product = wc_get_product($product_id);
        if (!$product) return;

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a read-only admin page
        $page = isset($_GET['paged']) ? max(1, intval(wp_unslash($_GET['paged']))) : 1;
        $per_page = 15;

        // Fetch users with pagination
        $user_query = new WP_User_Query(array(
            'meta_key'    => '_bis_subscribed_product',
            'meta_value'  => $product_id,
            'number'      => $per_page,
            'paged'       => $page,
            'count_total' => true
        ));

        $users = $user_query->get_results();
        $total_users = $user_query->get_total();
        $total_pages = ceil($total_users / $per_page);

        /* translators: %s is the product name */
        echo '<h2>' . sprintf(esc_html__('Managing Waitlist for: %s', 'mlx-back-in-stock-notifier-for-woocommerce'), esc_html($product->get_name())) . '</h2>';
        /* translators: %d is the total number of users waiting */
        echo '<p><strong>' . sprintf(esc_html__('Total users waiting: %d', 'mlx-back-in-stock-notifier-for-woocommerce'), esc_html($total_users)) . '</strong></p>';

        // Grid layout: Left side (Form), Right side (User List)
        echo '<div class="bis-manage-container">';

        // LEFT COLUMN: Alternative Product Form
        echo '<div class="bis-manage-form">';
        echo '<h3>' . esc_html__('Suggest Alternative Product', 'mlx-back-in-stock-notifier-for-woocommerce') . '</h3>';
        
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('bis_send_alt_nonce', 'bis_alt_nonce');
        echo '<input type="hidden" name="action" value="bis_send_alternative">';
        echo '<input type="hidden" name="target_product_id" value="' . esc_attr($product_id) . '">';

        echo '<p><label for="alt_product_id"><strong>' . esc_html__('Select Alternative Product:', 'mlx-back-in-stock-notifier-for-woocommerce') . '</strong></label><br>';
        // Using WooCommerce's native AJAX search class
        echo '<select class="wc-product-search bis-full-width" name="alt_product_id" data-placeholder="' . esc_attr__('Search for a product...', 'mlx-back-in-stock-notifier-for-woocommerce') . '" data-action="woocommerce_json_search_products_and_variations" required></select></p>';

        /* translators: %s is the original product name */
        $default_msg = sprintf(__('Hello! We noticed you are waiting for %s. While we work on restocking it, we thought you might love this alternative product!', 'mlx-back-in-stock-notifier-for-woocommerce'), $product->get_name());
        
        echo '<p><label for="alt_message"><strong>' . esc_html__('Message Template:', 'mlx-back-in-stock-notifier-for-woocommerce') . '</strong></label><br>';
        echo '<textarea name="alt_message" rows="5" class="bis-full-width" required>' . esc_textarea($default_msg) . '</textarea></p>';

        echo '<p><label><input type="checkbox" name="keep_on_list" value="1" checked> ' . esc_html__('Keep users on the waitlist for the original product after sending.', 'mlx-back-in-stock-notifier-for-woocommerce') . '</label></p>';

        echo '<p><button type="submit" class="button button-primary">' . esc_html__('Inform waiting users alternative product exists', 'mlx-back-in-stock-notifier-for-woocommerce') . '</button></p>';
        echo '</form>';
        echo '</div>';

        // RIGHT COLUMN: User List with Pagination
        echo '<div class="bis-user-list">';
        echo '<h3>' . esc_html__('Waiting Customers', 'mlx-back-in-stock-notifier-for-woocommerce') . '</h3>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>' . esc_html__('Email', 'mlx-back-in-stock-notifier-for-woocommerce') . '</th><th>' . esc_html__('Registered Name', 'mlx-back-in-stock-notifier-for-woocommerce') . '</th></tr></thead><tbody>';
        
        if (!empty($users)) {
            foreach ($users as $user) {
                echo '<tr>';
                echo '<td><a href="' . esc_url(get_edit_user_link($user->ID)) . '">' . esc_html($user->user_email) . '</a></td>';
                echo '<td>' . esc_html($user->display_name) . '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="2">' . esc_html__('No users found.', 'mlx-back-in-stock-notifier-for-woocommerce') . '</td></tr>';
        }
        echo '</tbody></table>';

        // Pagination rendering
        if ($total_pages > 1) {            
            $pagination = paginate_links(array(
                'base'      => add_query_arg('paged', '%#%'),
                'format'    => '',
                /* translators: &laquo; is a left-pointing double angle quotation mark */
                'prev_text' => __('&laquo; Previous', 'mlx-back-in-stock-notifier-for-woocommerce'),
                /* translators: &raquo; is a right-pointing double angle quotation mark */
                'next_text' => __('Next &raquo;', 'mlx-back-in-stock-notifier-for-woocommerce'),
                'total'     => absint($total_pages),
                'current'   => absint($page),
            ));

            if ($pagination) {
                echo '<div class="tablenav"><div class="tablenav-pages">';
                echo wp_kses_post($pagination);
                echo '</div></div>';
            }            
        }
        echo '</div>'; // End right column
        echo '</div>'; // End flex container
    }

    private static function render_help_tab() {
        echo '<div class="bis-help-tab-content">';
        echo '<h2>' . esc_html__('Help & Information', 'mlx-back-in-stock-notifier-for-woocommerce') . '</h2>';
        echo '<p>' . esc_html__('The MLX Back In Stock Notifier automatically adds a "Notify Me" button to any out-of-stock product.', 'mlx-back-in-stock-notifier-for-woocommerce') . '</p>';
        
        echo '<h3>' . esc_html__('How it works:', 'mlx-back-in-stock-notifier-for-woocommerce') . '</h3>';
        echo '<ul class="bis-help-tab-list">';
        echo '<li>' . esc_html__('Users click the button to join the waitlist.', 'mlx-back-in-stock-notifier-for-woocommerce') . '</li>';
        echo '<li>' . esc_html__('When you update the product inventory to "In Stock", the plugin queues an email to everyone on the list.', 'mlx-back-in-stock-notifier-for-woocommerce') . '</li>';
        echo '<li>' . esc_html__('You can use the "Waitlists" tab to manually suggest alternative products to users who are waiting.', 'mlx-back-in-stock-notifier-for-woocommerce') . '</li>';
        echo '</ul>';

        echo '<h3>' . esc_html__('Background Processing (Action Scheduler)', 'mlx-back-in-stock-notifier-for-woocommerce') . '</h3>';
        echo '<p>' . esc_html__('To ensure your website never slows down or crashes, all emails (both automatic restocks and manual alternative suggestions) are processed in the background using WooCommerce\'s native Action Scheduler.', 'mlx-back-in-stock-notifier-for-woocommerce') . '</p>';
        
        $scheduled_actions_url = admin_url('admin.php?page=wc-status&tab=action-scheduler');

        printf(
            '<p>%s</p>',
            wp_kses(
                sprintf(
                    /* translators: %s is the URL to the Scheduled Actions page. */
                    __('If you have hundreds of users waiting, emails will be sent safely in batches over a few minutes. You can view the status of pending emails by going to <a href="%s">WooCommerce > Status > Scheduled Actions</a>.', 'mlx-back-in-stock-notifier-for-woocommerce'),
                    esc_url($scheduled_actions_url)
                ),
                array(
                    'a' => array(
                        'href' => array(),
                    ),
                )
            )
        );
        
        echo '<hr>';
        echo '<p><strong>' . esc_html__('Author:', 'mlx-back-in-stock-notifier-for-woocommerce') . '</strong> <a href="https://modulux.net" target="_blank">modulux.net</a></p>';
        echo '<strong>' . esc_html__('Version:', 'mlx-back-in-stock-notifier-for-woocommerce') . '</strong> ' . esc_html(MLX_BIS_VERSION) . '</p>';
        echo '</div>';
    }

    public static function handle_alternative_email() {
        // Security checks
        if (!current_user_can('manage_woocommerce')) wp_die('Unauthorized');
        check_admin_referer('bis_send_alt_nonce', 'bis_alt_nonce');

        $target_product_id = isset($_POST['target_product_id']) ? intval(wp_unslash($_POST['target_product_id'])) : 0;
        $alt_product_id = isset($_POST['alt_product_id']) ? intval(wp_unslash($_POST['alt_product_id'])) : 0;
        $custom_message = isset($_POST['alt_message']) ? sanitize_textarea_field(wp_unslash($_POST['alt_message'])) : '';
        $keep_on_list = isset($_POST['keep_on_list']) ? true : false;

        $alt_product = wc_get_product($alt_product_id);
        if (!$alt_product || !$target_product_id) {
            wp_die('Invalid product selection.');
        }

        // We only need the user IDs now, which is much faster to query
        $users = get_users(array(
            'meta_key'   => '_bis_subscribed_product',
            'meta_value' => $target_product_id,
            'fields'     => 'ID'
        ));

        if (empty($users)) {
            wp_safe_redirect(add_query_arg(array('page' => 'woo-bis'), admin_url('admin.php')));
            exit;
        }

        foreach ($users as $user_id) {
            if (function_exists('as_enqueue_async_action')) {
                // Queue the email with all the necessary data
                as_enqueue_async_action('mlx_bis_send_alternative_email', array(
                    $user_id, 
                    $target_product_id, 
                    $alt_product_id, 
                    $custom_message, 
                    $keep_on_list
                ));
            } else {
                // Fallback just in case Action Scheduler is disabled
                self::process_alternative_email($user_id, $target_product_id, $alt_product_id, $custom_message, $keep_on_list);
            }
        }

        // Invalidate the cache
        wp_cache_delete('bis_waitlist_aggregate', 'woo_bis');

        // Redirect back to waitlists page instantly with success message
        wp_safe_redirect(add_query_arg(array('page' => 'woo-bis', 'message' => 'sent'), admin_url('admin.php')));
        exit;
    }

    public static function process_alternative_email($user_id, $target_product_id, $alt_product_id, $custom_message, $keep_on_list) {
        // Fetch fresh data when the queue runs
        $user = get_userdata($user_id);
        $alt_product = wc_get_product($alt_product_id);

        if (!$user || !$alt_product) return;

        // Double-check they are still on the waitlist for the target product
        $subs = get_user_meta($user_id, '_bis_subscribed_product');
        if (!is_array($subs) || !in_array($target_product_id, $subs)) return;

        // Get WooCommerce official sender details to prevent SMTP blocking
        $from_name  = get_option('woocommerce_email_from_name', get_bloginfo('name'));
        $from_email = get_option('woocommerce_email_from_address', get_bloginfo('admin_email'));
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>'
        );

        $subject = __('Alternative Product Suggestion', 'mlx-back-in-stock-notifier-for-woocommerce');
        $product_link = get_permalink($alt_product_id);

        // Convert plain text line breaks into HTML paragraphs
        $email_body  = wpautop(esc_html($custom_message));
        $email_body .= '<p>' . __('View alternative product here:', 'mlx-back-in-stock-notifier-for-woocommerce') . ' <br><a href="' . esc_url($product_link) . '">' . esc_html($alt_product->get_name()) . '</a></p>';

        if (wp_mail($user->user_email, $subject, $email_body, $headers)) {
            // Remove from waitlist ONLY if admin unchecked the box, and the email actually sent
            if (!$keep_on_list) {
                delete_user_meta($user_id, '_bis_subscribed_product', $target_product_id);
            }
        }
    }
}