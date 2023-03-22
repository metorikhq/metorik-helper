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
            add_action('add_meta_boxes', array($this, 'register_meta_boxes'), 0);

            // customers table
            add_filter('manage_users_columns', array($this, 'modify_user_table'));
            add_filter('manage_users_custom_column', array($this, 'add_user_table_column'), 10, 3);
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
            'metorik-subscription-box',
        );

        echo '<style>';

        foreach ($ids as $id) {
            echo '
				#'.$id.' button { display: none; }
				#'.$id.' h2 { display: none; }
				#'.$id.' .postbox-header { border-bottom: none; }
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
        $orderScreen = class_exists(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class) && wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id('shop-order')
            : 'shop_order';

        add_meta_box('metorik-product-box', __('Metorik', 'metorik'), array($this, 'product_box_display'), 'product', 'side', 'high');
        add_meta_box('metorik-order-box', __('Metorik', 'metorik'), array($this, 'order_box_display'), $orderScreen, 'side', 'high');

        $subscriptionScreen = class_exists(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class) && wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id('shop-subscription')
            : 'shop_subscription';

        if ($subscriptionScreen) {
            add_meta_box('metorik-subscription-box', __('Metorik', 'metorik'), array($this, 'subscription_box_display'), $subscriptionScreen, 'side', 'high');
        }
    }

    /**
     * Product meta box display callback.
     */
    public function product_box_display($post)
    {
        $shopUrl = str_replace(array('http://', 'https://'), '', home_url());
        echo '<a href="https://app.metorik.com/woo-admin-link?resource=products&shop='.$shopUrl.'&id='.$post->ID.'" target="_blank">
			<img src="'.Metorik_Helper()->url.'assets/img/metorik.png" /> View in Metorik <span class="dashicons dashicons-arrow-right-alt2"></span>
		</a>';
    }

    /**
     * Order meta box display callback.
     */
    public function order_box_display($post)
    {
        $orderID = ($post instanceof WP_Post) ? $post->ID : $post->get_id();
        $shopUrl = str_replace(array('http://', 'https://'), '', home_url());

        echo '<a href="https://app.metorik.com/woo-admin-link?resource=orders&shop='.$shopUrl.'&id='.$orderID.'" target="_blank">
			<img src="'.Metorik_Helper()->url.'assets/img/metorik.png" /> View in Metorik <span class="dashicons dashicons-arrow-right-alt2"></span>
		</a>';
    }

    /**
     * Subscription meta box display callback.
     */
    public function subscription_box_display($post)
    {
        $orderID = ($post instanceof WP_Post) ? $post->ID : $post->get_id();
        $shopUrl = str_replace(array('http://', 'https://'), '', home_url());

        echo '<a href="https://app.metorik.com/woo-admin-link?resource=subscriptions&shop='.$shopUrl.'&id='.$orderID.'" target="_blank">
			<img src="'.Metorik_Helper()->url.'assets/img/metorik.png" /> View in Metorik <span class="dashicons dashicons-arrow-right-alt2"></span>
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
        $shopUrl = str_replace(array('http://', 'https://'), '', home_url());

        switch ($column_name) {
            case 'metorik':
                return '<a href="https://app.metorik.com/woo-admin-link?resource=customers&shop='.$shopUrl.'&id='.$user_id.'" target="_blank">View</a>';
                break;
            default:
        }

        return $val;
    }
}

new Metorik_UI();
