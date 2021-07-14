<?php

/**
 * Make changes to the admin UI, like links to resources in Metorik.
 */
class Metorik_UI
{
    public function __construct()
    {
        // filter to hide it
        if (apply_filters('metorik_show_ui', true)) {
            // product/order meta boxes
            add_action('admin_head', array($this, 'custom_css'));
            add_action('add_meta_boxes', array($this, 'register_meta_boxes'));

            // customers table
            add_filter('manage_users_columns', array($this, 'modify_user_table'));
            add_filter('manage_users_custom_column', array($this, 'add_user_table_column'), 10, 3);

            // admin notices (for reports)
            add_action('admin_init', array($this, 'check_admin_notices_dismiss'));
            add_action('admin_notices', array($this, 'admin_notices'));
        }
    }

    /**
     * Custom CSS for admin.
     */
    public function custom_css()
    {
        $ids = array(
            'metorik-product-box',
            'metorik-order-box',
        );

        echo '<style>';

        foreach ($ids as $id) {
            echo '
				#'.$id.' button { display: none; }
				#'.$id.' h2 { display: none; }
				#'.$id.' .inside { padding: 0; margin: 0; }
				#'.$id.' .inside a { display: block; font-weight: bold; padding: 12px; text-decoration: none; vertical-align: middle; }
				#'.$id.' .inside a:hover { background: #fafafa; }
				#'.$id.' .inside a img { display: inline-block; margin: -4px 5px 0 0; vertical-align: middle; width: 20px; }
				#'.$id.' .inside a span { float: right; }
			';
        }

        echo '.metorik-notice.notice button.notice-dismiss { display: none; }';
        echo '.metorik-notice.notice a.notice-dismiss { text-decoration: none; }';

        echo '</style>';
    }

    /**
     * Register meta box(es).
     */
    public function register_meta_boxes()
    {
        add_meta_box('metorik-product-box', __('Metorik', 'metorik'), array($this, 'product_box_display'), 'product', 'side', 'high');
        add_meta_box('metorik-order-box', __('Metorik', 'metorik'), array($this, 'order_box_display'), 'shop_order', 'side', 'high');
    }

    /**
     * Product meta box display callback.
     */
    public function product_box_display($post)
    {
        echo '<a href="https://app.metorik.com/products/'.$post->ID.'" target="_blank">
			<img src="'.Metorik_Helper()->url.'assets/img/metorik.png" /> View on Metorik <span class="dashicons dashicons-arrow-right-alt2"></span>
		</a>';
    }

    /**
     * Order meta box display callback.
     */
    public function order_box_display($post)
    {
        echo '<a href="https://app.metorik.com/orders/'.$post->ID.'" target="_blank">
			<img src="'.Metorik_Helper()->url.'assets/img/metorik.png" /> View on Metorik <span class="dashicons dashicons-arrow-right-alt2"></span>
		</a>';
    }

    /**
     * Add column header to users table.
     */
    public function modify_user_table($column)
    {
        $column['metorik'] = 'Metorik';

        return $column;
    }

    /**
     * Add column body to users table.
     */
    public function add_user_table_column($val, $column_name, $user_id)
    {
        switch ($column_name) {
            case 'metorik':
                return '<a href="https://app.metorik.com/customers/'.$user_id.'" target="_blank">View</a>';
                break;
            default:
        }

        return $val;
    }

    /**
     * Check if admin notices should be dismissed.
     */
    public function check_admin_notices_dismiss()
    {
        if (isset($_GET['dismiss-metorik-notices']) && check_admin_referer('dismiss-metorik-notices')) {
            update_option('metorik_show_notices', 'no');
        }

        if (isset($_GET['show-metorik-notices']) && is_user_logged_in() && current_user_can('administrator')) {
            update_option('metorik_show_notices', 'yes');
        }
    }

    /**
     * Admin notices.
     */
    public function admin_notices()
    {
        $screen = get_current_screen()->base;
        $links = false; // default
        $show_notices = get_option('metorik_show_notices', 'yes');

        // check if they've been disabled
        if ($show_notices == 'yes') {
            // reports
            if ($screen == 'woocommerce_page_wc-reports') {
                $report = isset($_GET['report']) ? sanitize_text_field($_GET['report']) : false;
                $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : false;

                // no report set? check if root of this tab
                if (!$report && $tab) {
                    switch ($tab) {
                        case 'orders':
                            $report = 'sales_by_date';
                            break;
                        case 'customers':
                            $report = 'customers';
                            break;
                    }
                }

                // no tab? sales
                if (!$tab) {
                    $report = 'sales_by_date';
                }

                switch ($report) {
                    case 'sales_by_date':
                        $links = array(
                            array(
                                'report' => 'Sales Report',
                                'link'   => 'reports/orders',
                            ),
                            array(
                                'report' => 'Refunds Report',
                                'link'   => 'reports/refunds',
                            ),
                        );
                        break;
                    case 'sales_by_product':
                        $links = array(
                            array(
                                'report' => 'All Products',
                                'link'   => 'products',
                            ),
                            array(
                                'report' => 'Compare Products',
                                'link'   => 'reports/products',
                            ),
                        );
                        break;
                    case 'sales_by_category':
                        $links = array(
                            array(
                                'report' => 'All Categories',
                                'link'   => 'categories',
                            ),
                        );
                        break;
                    case 'customers':
                        $links = array(
                            array(
                                'report' => 'Customers Report',
                                'link'   => 'reports/customers',
                            ),
                            array(
                                'report' => 'Customer Retention',
                                'link'   => 'reports/customer-retention',
                            ),
                        );
                        break;
                    case 'customer_list':
                        $links = array(
                            array(
                                'report' => 'All Customers',
                                'link'   => 'customers',
                            ),
                        );
                        break;
                }
            }

            // resources
            if ($screen == 'edit') {
                $type = isset($_GET['post_type']) ? sanitize_text_field($_GET['post_type']) : false;

                if ($type) {
                    switch ($type) {
                        case 'shop_order':
                            $links = array(
                                array(
                                    'report' => 'All Orders',
                                    'link'   => 'orders',
                                ),
                            );
                            break;
                        case 'shop_subscription':
                            $links = array(
                                array(
                                    'report' => 'All Subscriptions',
                                    'link'   => 'subscriptions',
                                ),
                            );
                            break;
                        case 'product':
                            $links = array(
                                array(
                                    'report' => 'All Products',
                                    'link'   => 'products',
                                ),
                            );
                            break;
                    }
                }
            }

            // users
            if ($screen == 'users') {
                $links = array(
                    array(
                        'report' => 'All Customers',
                        'link'   => 'customers',
                    ),
                );
            }

            if ($screen == 'edit-tags') {
                $tax = isset($_GET['taxonomy']) ? sanitize_text_field($_GET['taxonomy']) : false;
                $type = isset($_GET['post_type']) ? sanitize_text_field($_GET['post_type']) : false;

                if ($tax == 'product_cat' && $type == 'product') {
                    $links = array(
                        array(
                            'report' => 'All Categories',
                            'link'   => 'categories',
                        ),
                    );
                }
            }

            // output notice if have links
            if ($links) {
                echo '<div class="metorik-notice notice notice-info is-dismissible">';
                // notice message
                echo '<p>You can view a more detailed, powerful, and accurate version of this on Metorik: ';
                foreach ($links as $key => $link) {
                    echo '<a href="https://app.metorik.com/'.$link['link'].'" target="_blank">'.$link['report'].'</a>';
                    if ($key + 1 < count($links)) {
                        echo ' & ';
                    }
                }
                echo '</p>';

                // dismiss url and link
                global $wp;
                $current_url = add_query_arg($wp->query_string, '', home_url($wp->request));

                // for users, just set current url manually as doesn't work with above method
                if ($screen && $screen == 'users') {
                    $current_url = admin_url('users.php');
                }

                $dismiss_url = add_query_arg('dismiss-metorik-notices', 'yes', $current_url);
                $dismiss_url = wp_nonce_url($dismiss_url, 'dismiss-metorik-notices');
                echo '<a href="'.esc_url($dismiss_url).'" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></a>';
                echo '</div>';
            }
        }
    }
}

new Metorik_UI();
