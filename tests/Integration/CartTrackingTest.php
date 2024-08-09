<?php

namespace Tests\Integration;

if ( isUnitTest() ) {
	return;
}

afterEach( function () {
	// WordPress and thus wp-pest doesn't support PHPUnit 10 yet
	// as such there is no process isolation between tests,
	// which in turn means that $wp_actions and $wp_filters aren't cleared between tests.
	// So, any action/filters we set or check for in one test would remain set in the next test.
	// As a workaround for this, we can manually clear the actions/filters we set/care about in the tests.
	// See https://github.com/dingo-d/wp-pest/issues/36 for more details
	\Metorik_Test_WC_Helpers::clear_cart_actions_and_filters();
	\Metorik_Test_WC_Helpers::clear_cart_tracking_enabled();
} );


test( 'cart tracking is enabled based on auth token set', function () {
	update_option( \Metorik_Cart_Tracking::AUTH_TOKEN_OPTION, 'test' );
	expect( metorik_cart_tracking_enabled() )->toBeTrue();

	update_option( \Metorik_Cart_Tracking::AUTH_TOKEN_OPTION, false );
	expect( metorik_cart_tracking_enabled() )->toBeFalse();

	update_option( \Metorik_Cart_Tracking::AUTH_TOKEN_OPTION, 'token-1234' );
	expect( metorik_cart_tracking_enabled() )->toBeTrue();

	delete_option( \Metorik_Cart_Tracking::AUTH_TOKEN_OPTION );
	expect( metorik_cart_tracking_enabled() )->toBeFalse();
} );

test( 'cart tracking is enabled based on filter', function () {
	update_option( \Metorik_Cart_Tracking::AUTH_TOKEN_OPTION, 'test' );
	add_filter( 'metorik_cart_tracking_enabled', '__return_false' );
	expect( metorik_cart_tracking_enabled() )->toBeFalse();

	remove_filter( 'metorik_cart_tracking_enabled', '__return_false' );
	expect( metorik_cart_tracking_enabled() )->toBeTrue();

	delete_option( \Metorik_Cart_Tracking::AUTH_TOKEN_OPTION );
	expect( metorik_cart_tracking_enabled() )->toBeFalse();

	add_filter( 'metorik_cart_tracking_enabled', '__return_true' );
	expect( metorik_cart_tracking_enabled() )->toBeTrue();
} );

describe( 'cart sync tests', function () {
	beforeEach( function () {
		// enable cart tracking
		\Metorik_Test_WC_Helpers::clear_cart_tracking_enabled();
		add_filter( 'metorik_cart_tracking_enabled', '__return_true' );
	} );

	afterEach( function () {
		// clear the cart between tests
		\Metorik_Test_WC_Helpers::clear_wc_data();

		// clear actions and filters between tests
		\Metorik_Test_WC_Helpers::clear_cart_actions_and_filters();
	} );

	test( 'no cart sync when tracking is disabled', function () {
		// disable cart tracking
		add_filter( 'metorik_cart_tracking_enabled', '__return_false' );

		// Create dummy product && add to cart
		$product = \Metorik_Test_WC_Helpers::create_product();
		WC()->cart->add_to_cart( $product->get_id() );

		// set customer email
		\Metorik_Test_WC_Helpers::capture_customer_email();

		\Metorik_Test_WC_Helpers::check_cart_did_not_sync();
	} );

	test( 'no cart sync when no customer info provided', function () {
		// Create dummy product && add to cart
		$product = \Metorik_Test_WC_Helpers::create_product();
		WC()->cart->add_to_cart( $product->get_id() );

		\Metorik_Test_WC_Helpers::check_cart_did_not_sync();
	} );

	test( 'initiates cart sync when email provided and item added to cart', function () {
		// Create dummy product && add to cart
		$product = \Metorik_Test_WC_Helpers::create_product();
		WC()->cart->add_to_cart( $product->get_id() );

		// set customer email
		\Metorik_Test_WC_Helpers::capture_customer_email();

		\Metorik_Test_WC_Helpers::check_cart_sync_outgoing_request_data( function ( $data ) use ( $product ) {
			expect( $data['email'] )->toEqual( \Metorik_Test_WC_Helpers::DEFAULT_CUSTOMER_EMAIL );
			expect( $data['cart'] )->toHaveCount( 1 );

			$cart_item = array_pop( $data['cart'] );
			expect( $cart_item['product_id'] )->toEqual( $product->get_id() );
			expect( $cart_item['quantity'] )->toEqual( 1 );
			expect( $cart_item['line_subtotal'] )->toEqual( 1 * $product->get_price() );
			expect( $data['subtotal'] )->toEqual( 1 * $product->get_price() );
		} );

	} );

	test( 'initiates cart sync when cart quantity modified', function () {
		// Create dummy product && add to cart
		$product = \Metorik_Test_WC_Helpers::create_product();
		WC()->cart->add_to_cart( $product->get_id() );

		// set customer email
		\Metorik_Test_WC_Helpers::capture_customer_email();

		// check it synced once
		\Metorik_Test_WC_Helpers::check_cart_synced_once();

		// reset so that we can test again
		\Metorik_Test_WC_Helpers::clear_between_cart_syncs();

		$cart_contents = WC()->cart->get_cart_contents();
		$cart_item     = array_pop( $cart_contents );
		WC()->cart->set_quantity( $cart_item['key'], 3 );

		\Metorik_Test_WC_Helpers::check_cart_sync_outgoing_request_data( function ( $data ) use ( $product ) {
			expect( $data['email'] )->toEqual( \Metorik_Test_WC_Helpers::DEFAULT_CUSTOMER_EMAIL );
			expect( $data['cart'] )->toHaveCount( 1 );

			$cart_item = array_pop( $data['cart'] );
			expect( $cart_item['product_id'] )->toEqual( $product->get_id() );
			expect( $cart_item['quantity'] )->toEqual( 3 );
			expect( $cart_item['line_subtotal'] )->toEqual( 3 * $product->get_price() );
			expect( $data['subtotal'] )->toEqual( 3 * $product->get_price() );
		} );
	} );

	test( 'initiates cart sync when multiple items added', function () {
		// Create dummy product && add to cart
		$product1 = \Metorik_Test_WC_Helpers::create_product();
		$product2 = \Metorik_Test_WC_Helpers::create_product();
		WC()->cart->add_to_cart( $product1->get_id() );

		// set customer email
		\Metorik_Test_WC_Helpers::capture_customer_email();

		// check it synced once
		\Metorik_Test_WC_Helpers::check_cart_synced_once();

		// reset so that we can test again
		\Metorik_Test_WC_Helpers::clear_between_cart_syncs();

		WC()->cart->add_to_cart( $product2->get_id() );

		\Metorik_Test_WC_Helpers::check_cart_sync_outgoing_request_data( function ( $data ) use ( $product1, $product2 ) {
			expect( $data['email'] )->toEqual( \Metorik_Test_WC_Helpers::DEFAULT_CUSTOMER_EMAIL );
			expect( $data['cart'] )->toHaveCount( 2 );

			$cart_item1 = array_pop( $data['cart'] );
			$cart_item2 = array_pop( $data['cart'] );
			expect( $cart_item2['product_id'] )->toEqual( $product1->get_id() );
			expect( $cart_item2['quantity'] )->toEqual( 1 );
			expect( $cart_item1['product_id'] )->toEqual( $product2->get_id() );
			expect( $cart_item1['quantity'] )->toEqual( 1 );
			expect( $data['subtotal'] )->toEqual( $product1->get_price() + $product2->get_price() );
		} );
	} );

	test( 'initiates cart sync when cart items removed', function () {
		// Create dummy product && add to cart
		$product1 = \Metorik_Test_WC_Helpers::create_product();
		$product2 = \Metorik_Test_WC_Helpers::create_product();
		WC()->cart->add_to_cart( $product1->get_id() );
		WC()->cart->add_to_cart( $product2->get_id() );

		// set customer email
		\Metorik_Test_WC_Helpers::capture_customer_email();

		// check it synced once
		\Metorik_Test_WC_Helpers::check_cart_synced_once();

		// reset so that we can test again
		\Metorik_Test_WC_Helpers::clear_between_cart_syncs();

		$cart_contents = WC()->cart->get_cart_contents();
		$cart_item     = array_pop( $cart_contents );
		WC()->cart->remove_cart_item( $cart_item['key'] );

		\Metorik_Test_WC_Helpers::check_cart_sync_outgoing_request_data( function ( $data ) use ( $product1 ) {
			expect( $data['email'] )->toEqual( \Metorik_Test_WC_Helpers::DEFAULT_CUSTOMER_EMAIL );
			expect( $data['cart'] )->toHaveCount( 1 );

			$cart_item = array_pop( $data['cart'] );
			expect( $cart_item['product_id'] )->toEqual( $product1->get_id() );
			expect( $cart_item['quantity'] )->toEqual( 1 );
			expect( $data['subtotal'] )->toEqual( $product1->get_price() );
		} );
	} );

	test( 'initiates cart sync when coupon added and removed', function () {
		// Create dummy product && add to cart
		$product = \Metorik_Test_WC_Helpers::create_product();
		WC()->cart->add_to_cart( $product->get_id() );

		// set customer email
		\Metorik_Test_WC_Helpers::capture_customer_email();

		// check it synced once
		\Metorik_Test_WC_Helpers::check_cart_synced_once();

		// reset so that we can test again
		\Metorik_Test_WC_Helpers::clear_between_cart_syncs();

		// create & add coupon
		$coupon = \Metorik_Test_WC_Helpers::create_coupon();
		WC()->cart->add_discount( $coupon->get_code() );

		\Metorik_Test_WC_Helpers::check_cart_sync_outgoing_request_data( function ( $data ) use ( $product, $coupon ) {
			expect( $data['email'] )->toEqual( \Metorik_Test_WC_Helpers::DEFAULT_CUSTOMER_EMAIL );
			expect( $data['cart'] )->toHaveCount( 1 );

			$cart_item = array_pop( $data['cart'] );
			expect( $cart_item['product_id'] )->toEqual( $product->get_id() );
			expect( $cart_item['quantity'] )->toEqual( 1 );
			expect( $cart_item['line_subtotal'] )->toEqual( $product->get_price() );
			expect( $cart_item['line_total'] )->toEqual( $product->get_price() - $coupon->get_amount() );
			expect( $data['total'] )->toEqual( $product->get_price() - $coupon->get_amount() );
			expect( $data['total_discount'] )->toEqual( $coupon->get_amount() );
		} );

		// reset so that we can test again
		\Metorik_Test_WC_Helpers::clear_between_cart_syncs();

		// remove coupon
		WC()->cart->remove_coupon( $coupon->get_code() );
		WC()->cart->calculate_totals();

		\Metorik_Test_WC_Helpers::check_cart_sync_outgoing_request_data( function ( $data ) use ( $product, $coupon ) {
			expect( $data['email'] )->toEqual( \Metorik_Test_WC_Helpers::DEFAULT_CUSTOMER_EMAIL );
			expect( $data['cart'] )->toHaveCount( 1 );

			$cart_item = array_pop( $data['cart'] );
			expect( $cart_item['product_id'] )->toEqual( $product->get_id() );
			expect( $cart_item['quantity'] )->toEqual( 1 );
			expect( $cart_item['line_subtotal'] )->toEqual( $product->get_price() );
			expect( $cart_item['line_total'] )->toEqual( $product->get_price() );
			expect( $data['total'] )->toEqual( $product->get_price() );
			expect( $data['total_discount'] )->toEqual( 0 );
		} );
	} );
} );
