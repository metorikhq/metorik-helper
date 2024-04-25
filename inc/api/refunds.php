<?php

/**
 * Refunds API for Metorik.
 */
class Metorik_Helper_API_Refunds extends WC_REST_Posts_Controller
{
    public $namespace = 'wc/v1';

    public $post_type = 'shop_order_refund';

    /**
     * Constructor.
     */
    public function __construct()
    {
        add_action('rest_api_init', array($this, 'refunds_ids_route'));
    }

    /**
     * Refunds IDs route definition.
     */
    public function refunds_ids_route()
    {
        register_rest_route($this->namespace, '/refunds/ids/', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'refunds_ids_callback'),
            'permission_callback' => array($this, 'get_items_permissions_check'),
        ));
    }

    /**
     * Callback for the Order IDs API endpoint.
     * Will likely be depreciated in a future version in favour of the refunds updated endpoint.
     */
    public function refunds_ids_callback()
    {
        /**
         * Get refunds.
         */
        $refunds = new WP_Query(array(
            'post_type'      => $this->post_type,
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'fields'         => 'id=>parent',
        ));

        /*
         * No refunds.
         */
        if (!$refunds->have_posts()) {
            return false;
        }

        /**
         * Prepare response.
         */
        $data = array(
            'count' => $refunds->post_count,
            'ids'   => $refunds->posts,
        );

        /**
         * Response.
         */
        $response = rest_ensure_response($data);
        $response->set_status(200);

        return $response;
    }
}

new Metorik_Helper_API_Refunds();
