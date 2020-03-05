<?php

/**
 * These are small changes that help Metorik complete it's import of the store.
 */
class Metorik_Import_Helpers
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        add_action('rest_api_init', array($this, 'maybe_filter_customers'));
    }

    /**
     * Maybe filter customers if it's Metorik making the request or custom param set.
     */
    public function maybe_filter_customers($server)
    {
        // get headers if have server and server is an object (eg. not a string)
        $headers = $server && is_object($server) ? $server->get_headers($_SERVER) : false;

        // if headers and have metorik user agent, filter user meta to stop total spend/order count calculations
        if ($headers && metorik_check_headers_agent($headers)) {
            add_filter('get_user_metadata', array($this, 'filter_user_metadata'), 10, 4);
        } else {
            // or as a backup method - check if no spend data param is set
            if (isset($_GET['no_spend_data']) && $_GET['no_spend_data']) {
                add_filter('get_user_metadata', array($this, 'filter_user_metadata'), 10, 4);
            }
        }
    }

    /**
     * Filter user meta for total spent + order count so that
     * if it's not yet set, get_user_meta will return 0.
     * This is so WC doesn't attempt to calculate it
     * while Metorik is doing customer queries.
     *
     * This is called when Metorik is making a customer-related API
     * request to the store (determined by user agent/query param).
     */
    public function filter_user_metadata($value, $object_id, $meta_key, $single)
    {
        // Check if it's one of the keys we want to filter
        if (in_array($meta_key, array('_money_spent', '_order_count'))) {
            // Return 0 so WC doesn't try calculate it
            return 0;
        }

        // Default
        return $value;
    }
}

new Metorik_Import_Helpers();
