<?php

/**
 * Orders API for Metorik.
 */
class Metorik_Helper_API_Orders extends WC_REST_Posts_Controller
{
    public $namespace = 'wc/v1';

    public $post_type = 'shop_order';

    /**
     * Constructor.
     */
    public function __construct()
    {
        add_action('rest_api_init', array($this, 'orders_ids_route'));
        add_action('rest_api_init', array($this, 'orders_updated_route'));
        add_action('rest_api_init', array($this, 'orders_statuses_route'));

        // if less than 2.7, add meta
        if (version_compare(WC()->version, '2.7.0', '<')) {
            add_filter('woocommerce_rest_prepare_shop_order', array($this, 'add_order_api_data'), 10, 3);
        }

        // if 3.0 or higher, remove coupon line items meta
        if (version_compare(WC()->version, '3.0.0', '>=')) {
            add_filter('woocommerce_rest_prepare_shop_order_object', array($this, 'remove_coupon_line_items_meta'), 10, 3);
        }

        // add author to order notes response
        add_filter('woocommerce_rest_prepare_order_note', array($this, 'add_order_note_author'), 10, 3);
    }

    /**
     * Orders IDs route definition.
     */
    public function orders_ids_route()
    {
        register_rest_route($this->namespace, '/orders/ids/', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'orders_ids_callback'),
            'permission_callback' => array($this, 'get_items_permissions_check'),
        ));
    }

    /**
     * Orders IDs route definition.
     */
    public function orders_updated_route()
    {
        register_rest_route($this->namespace, '/orders/updated/', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'orders_updated_callback'),
            'permission_callback' => array($this, 'get_items_permissions_check'),
        ));
    }

    /**
     * Orders statuses route definition.
     */
    public function orders_statuses_route()
    {
        register_rest_route($this->namespace, '/orders/statuses/', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'orders_statuses_callback'),
            'permission_callback' => array($this, 'get_items_permissions_check'),
        ));
    }

    /**
     * Callback for the Order IDs API endpoint.
     * Will likely be depreciated in a future version in favour of the orders updated endpoint.
     */
    public function orders_ids_callback()
    {
        /**
         * Get orders.
         */
        $orders = new WP_Query(array(
            'post_type'      => $this->post_type,
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'fields'         => 'ids',
        ));

        /*
         * No orders.
         */
        if (!$orders->have_posts()) {
            return false;
        }

        /**
         * Prepare response.
         */
        $data = array(
            'count' => $orders->post_count,
            'ids'   => $orders->posts,
        );

        /**
         * Response.
         */
        $response = rest_ensure_response($data);
        $response->set_status(200);

        return $response;
    }

    /**
     * Callback for the Orders updated API endpoint.
     * Later this will likely replace the IDs endpoint completely as it gets depreciated.
     */
    public function orders_updated_callback($request)
    {
        global $wpdb;

        /**
         * Check days set and use default if not.
         */
        $days = 30;
        if (isset($request['days'])) {
            $days = intval($request['days']);
        }

        /**
         * Check hours set and use default if not.
         */
        $hours = 0;
        if (isset($request['hours'])) {
            $hours = intval($request['hours']);
        }

        // How many days back?
        $time = strtotime('- '.$days.' days');

        // if have hours, subtract
        if ($hours) {
            $time = $time - (60 * 60 * $hours);
        }

        // format 'from date'
        $from = date('Y-m-d H:i:s', $time);

        // limit/offset
        $limit = 200000;
        $offset = 0;

        if (isset($request['limit'])) {
            $limit = intval($request['limit']);
        }

        if (isset($request['offset'])) {
            $offset = intval($request['offset']);
        }

        /**
         * Get orders where the date modified is greater than x days ago and not trashed.
         */
        $orders = $wpdb->get_results($wpdb->prepare(
            "
				SELECT 
					id,
					UNIX_TIMESTAMP(CONVERT_TZ(post_modified_gmt, '+00:00', @@session.time_zone)) as last_updated
				FROM $wpdb->posts
				WHERE post_type = 'shop_order' 
					AND post_modified > %s
					AND post_status != 'trash'
					AND post_status != 'draft'
				LIMIT %d, %d
			",
            array(
                $from,
                $offset,
                $limit,
            )
        ));

        /**
         * Prepare response.
         */
        $data = array(
            'orders' => $orders,
        );

        /**
         * Response.
         */
        $response = rest_ensure_response($data);
        $response->set_status(200);

        return $response;
    }

    /**
     * Callback for the Orders statuses API endpoint.
     */
    public function orders_statuses_callback($request)
    {
        /**
         * Prepare response.
         */
        $data = array(
            'statuses' => wc_get_order_statuses(),
        );

        /**
         * Response.
         */
        $response = rest_ensure_response($data);
        $response->set_status(200);

        return $response;
    }

    /**
     * Add some extra data to the order API endpoint.
     */
    public function add_order_api_data($response, $post, $request)
    {
        $data = $response->get_data();
        $data['meta_data'] = $this->get_order_post_meta($post->ID);
        $response->set_data($data);

        return $response;
    }

    /**
     * Remove the coupon lines meta data. Can be very
     * large when the coupon has rules/used a lot.
     */
    public function remove_coupon_line_items_meta($response, $post, $request)
    {
        // check for metorik user agent before continuing
        if (metorik_check_headers_agent($request->get_headers($_SERVER))) {
            // get response data
            $_data = $response->data;

            if (isset($_data['coupon_lines']) && count($_data['coupon_lines'])) {
                // remove meta each line item from response
                foreach ($_data['coupon_lines'] as $key => $line) {
                    unset($_data['coupon_lines'][$key]['meta_data']);
                }
            }

            // set response
            $response->data = $_data;
        }

        // return response
        return $response;
    }

    /**
     * Get the order's post meta for returning in filtered API response.
     */
    public function get_order_post_meta($id)
    {
        global $wpdb;

        // query to get all the post's meta
        $metadata = $wpdb->get_results($wpdb->prepare(
            "
				SELECT 
					meta_id,
					meta_key,
					meta_value
				FROM $wpdb->postmeta
				WHERE post_id = %d 
			",
            array(
                $id,
            )
        ));

        // ignore some keys
        $ignored_keys = array(
            '_customer_user',
            '_order_key',
            '_order_currency',
            '_billing_first_name',
            '_billing_last_name',
            '_billing_company',
            '_billing_address_1',
            '_billing_address_2',
            '_billing_city',
            '_billing_state',
            '_billing_postcode',
            '_billing_country',
            '_billing_email',
            '_billing_phone',
            '_shipping_first_name',
            '_shipping_last_name',
            '_shipping_company',
            '_shipping_address_1',
            '_shipping_address_2',
            '_shipping_city',
            '_shipping_state',
            '_shipping_postcode',
            '_shipping_country',
            '_completed_date',
            '_paid_date',
            '_edit_lock',
            '_edit_last',
            '_cart_discount',
            '_cart_discount_tax',
            '_order_shipping',
            '_order_shipping_tax',
            '_order_tax',
            '_order_total',
            '_payment_method',
            '_payment_method_title',
            '_transaction_id',
            '_customer_ip_address',
            '_customer_user_agent',
            '_created_via',
            '_order_version',
            '_prices_include_tax',
            '_date_completed',
            '_date_paid',
            '_payment_tokens',
            '_billing_address_index',
            '_shipping_address_index',
            '_recorded_sales',
            '_shipping_method',
            '_order_currency',
            '_cart_discount',
            '_cart_discount_tax',
            '_order_shipping',
            '_order_shipping_tax',
            '_order_tax',
            '_order_total',
            '_order_version',
            '_prices_include_tax',
            '_payment_tokens',
        );

        // format like 2.7 does
        $return = array();
        foreach ($metadata as $meta) {
            // skip if this is an ignored keys
            if (in_array($meta->meta_key, $ignored_keys)) {
                continue;
            }

            $return[] = array(
                'id'    => (int) $meta->meta_id,
                'key'   => $meta->meta_key,
                'value' => maybe_unserialize($meta->meta_value),
            );
        }

        return $return;
    }

    /**
     * Add some extra data to the order note API endpoint.
     */
    public function add_order_note_author($response, $note, $request)
    {
        $data = $response->get_data();

        // only if not set already (v3 api + has it)
        if (!isset($data['author'])) {
            $data['author'] = __('WooCommerce', 'woocommerce') === $note->comment_author ? 'system' : $note->comment_author;
        }

        $response->set_data($data);

        return $response;
    }
}

new Metorik_Helper_API_Orders();
