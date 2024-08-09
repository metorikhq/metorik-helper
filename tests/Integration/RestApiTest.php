<?php

namespace Tests\Integration;

if ( isUnitTest() ) {
	return;
}

beforeEach( function () {
	parent::setUp();

	// Set up a REST server instance.
	global $wp_rest_server;

	$this->server = $wp_rest_server = new \WP_REST_Server();
	do_action( 'rest_api_init', $this->server );
} );

afterEach( function () {
	global $wp_rest_server;
	$wp_rest_server = null;

	parent::tearDown();
} );

test( 'Metorik Rest API endpoints are loaded', function () {
	$routes = $this->server->get_routes();

	expect( $routes )
		->toBeArray()
		->toHaveKey( '/wc/v1/metorik/info' )
		->toHaveKey( '/wc/v1/metorik/importing' )
		->toHaveKey( '/wc/v1/metorik/auth' )
		->toHaveKey( '/wc/v1/metorik/cart-settings' );
} );
