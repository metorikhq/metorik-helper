<?php

/**
 * Coupons API for Metorik.
 */
class Metorik_Helper_API_Coupons extends WC_REST_Posts_Controller
{
    public $namespace = 'wc/v1';

    public $post_type = 'shop_coupon';

    /**
     * Constructor.
     */
    public function __construct()
    {
        add_action('rest_api_init', array($this, 'coupons_ids_route'));
        add_action('rest_api_init', array($this, 'coupons_updated_route'));
    }

    /**
     * Coupons IDs route definition.
     */
    public function coupons_ids_route()
    {
        register_rest_route($this->namespace, '/coupons/ids/', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'coupons_ids_callback'),
            'permission_callback' => array($this, 'get_items_permissions_check'),
        ));
    }

    /**
     * Coupons updated route definition.
     */
    public function coupons_updated_route()
    {
        register_rest_route($this->namespace, '/coupons/updated/', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'coupons_updated_callback'),
            'permission_callback' => array($this, 'get_items_permissions_check'),
        ));
    }

    /**
     * Callback for the Order IDs API endpoint.
     * Will likely be depreciated in a future version in favour of the coupons updated endpoint.
     */
    public function coupons_ids_callback()
    {
        /**
         * Get coupons.
         */
        $coupons = new WP_Query(array(
            'post_type'      => $this->post_type,
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'fields'         => 'ids',
        ));

        /*
         * No coupons.
         */
        if (!$coupons->have_posts()) {
            return false;
        }

        /**
         * Prepare response.
         */
        $data = array(
            'count' => $coupons->post_count,
            'ids'   => $coupons->posts,
        );

        /**
         * Response.
         */
        $response = rest_ensure_response($data);
        $response->set_status(200);

        return $response;
    }

    /**
     * Callback for the coupons updated API endpoint.
     * Later this will likely replace the IDs endpoint completely as it gets depreciated.
     */
    public function coupons_updated_callback($request)
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
         * Get coupons where the date modified is greater than x days ago.
         */
        $coupons = $wpdb->get_results($wpdb->prepare(
            "
				SELECT 
					id,
					UNIX_TIMESTAMP(CONVERT_TZ(post_modified_gmt, '+00:00', @@session.time_zone)) as last_updated
				FROM $wpdb->posts
				WHERE post_type = 'shop_coupon' 
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
            'coupons' => $coupons,
        );

        /**
         * Response.
         */
        $response = rest_ensure_response($data);
        $response->set_status(200);

        return $response;
    }
}

new Metorik_Helper_API_Coupons();
