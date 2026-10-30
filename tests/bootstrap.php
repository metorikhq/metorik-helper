<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

Yoast\WPTestUtils\BrainMonkey\TestCase::makeDoublesForUnavailableClasses([
    'WC_REST_Posts_Controller',
    'WP_REST_Response',
    'WP_Error',
]);

require_once dirname(__DIR__) . '/inc/functions.php';
