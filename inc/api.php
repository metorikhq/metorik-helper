<?php

/**
 * This class loads Metorik's API endpoints/code.
 */
class Metorik_Helper_API
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        // We must only include after this action since Woo 3.6+ uses it - https://github.com/woocommerce/woocommerce/commit/cd4039e07885b76d55dbccd4ab72edbe67c87628
        add_action('rest_api_init', array($this, 'includes'), 5);
    }

    /**
     * Include necessary files for the API.
     */
    public function includes()
    {
        require_once 'api/orders.php';
        require_once 'api/customers.php';
        require_once 'api/products.php';
        require_once 'api/coupons.php';
        require_once 'api/subscriptions.php';
        require_once 'api/refunds.php';
        require_once 'api/metorik.php';
    }
}

new Metorik_Helper_API();
