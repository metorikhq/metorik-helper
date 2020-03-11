<?php

/**
 * Subscriptions API for Metorik.
 */
class Metorik_Helper_API_Subscriptions extends WC_REST_Posts_Controller
{
    public $namespace = 'wc/v1';

    public $post_type = 'shop_subscription';

    /**
     * Constructor.
     */
    public function __construct()
    {
        add_action('rest_api_init', array($this, 'subscriptions_ids_route'));
        add_action('rest_api_init', array($this, 'subscriptions_updated_route'));
        add_filter('woocommerce_rest_prepare_shop_subscription', array($this, 'add_subscription_meta'), 10, 3);
    }

    /**
     * Subscriptions IDs route definition.
     */
    public function subscriptions_ids_route()
    {
        register_rest_route($this->namespace, '/subscriptions/ids/', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'subscriptions_ids_callback'),
            'permission_callback' => array($this, 'get_items_permissions_check'),
        ));
    }

    /**
     * Subscriptions updated route definition.
     */
    public function subscriptions_updated_route()
    {
        register_rest_route($this->namespace, '/subscriptions/updated/', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'subscriptions_updated_callback'),
            'permission_callback' => array($this, 'get_items_permissions_check'),
        ));
    }

    /**
     * Callback for the Order IDs API endpoint.
     * Will likely be depreciated in a future version in favour of the subscriptions updated endpoint.
     */
    public function subscriptions_ids_callback()
    {
        /**
         * Get subscriptions.
         */
        $subscriptions = new WP_Query(array(
            'post_type'      => $this->post_type,
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'fields'         => 'ids',
        ));

        /*
         * No subscriptions.
         */
        if (!$subscriptions->have_posts()) {
            return false;
        }

        /**
         * Prepare response.
         */
        $data = array(
            'count' => $subscriptions->post_count,
            'ids'   => $subscriptions->posts,
        );

        /**
         * Response.
         */
        $response = rest_ensure_response($data);
        $response->set_status(200);

        return $response;
    }

    /**
     * Callback for the Subscriptions updated API endpoint.
     * Later this will likely replace the IDs endpoint completely as it gets depreciated.
     */
    public function subscriptions_updated_callback($request)
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
         * Get subscriptions where the date modified is greater than x days ago.
         */
        $subscriptions = $wpdb->get_results($wpdb->prepare(
            "
				SELECT 
					id,
					UNIX_TIMESTAMP(CONVERT_TZ(post_modified_gmt, '+00:00', @@session.time_zone)) as last_updated
				FROM $wpdb->posts
				WHERE post_type = 'shop_subscription' 
					AND post_modified > %s
					AND post_status != 'trash'
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
            'subscriptions' => $subscriptions,
        );

        /**
         * Response.
         */
        $response = rest_ensure_response($data);
        $response->set_status(200);

        return $response;
    }

    /**
     * Add the Subscriptions meta to response.
     */
    public function add_subscription_meta($response, $post, $request)
    {
        $data = $response->get_data();
        $data['meta_data'] = $this->get_subscription_meta_data($post->ID);
        $response->set_data($data);

        return $response;
    }

    /**
     * Get the order's post meta for returning in filtered API response.
     */
    public function get_subscription_meta_data($id)
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

        // format like wc api from 3.0+ does
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
}

new Metorik_Helper_API_Subscriptions();
