<?php

if (class_exists(Metorik_Test_WC_Helpers::class)) {
	return;
}

class Metorik_Test_WC_Helpers {

	public const DEFAULT_CUSTOMER_EMAIL = 'tester@metorik.com';

	public static function clear_wc_data() {
		WC()->cart->cart_contents              = [];
		WC()->cart->removed_cart_contents      = [];
		WC()->cart->shipping_methods           = [];
		WC()->cart->coupon_discount_totals     = [];
		WC()->cart->coupon_discount_tax_totals = [];
		WC()->cart->applied_coupons            = [];
		WC()->cart->totals                     = WC()->cart->default_totals;

		WC()->customer->delete( true );
		WC()->session->set( 'customer', null );
		WC()->session->destroy_session();
		WC()->customer->set_defaults();

		// reset auth token & enabled/disabled
		Metorik_Cart_Tracking::reload_auth_token();
	}

	public static function clear_between_cart_syncs() {
		self::clear_cart_actions_and_filters();
		WC()->session->set( Metorik_Cart_Data::LAST_CART_HASH, null );
		Metorik_Cart_Tracking::reload_auth_token();
	}

	public static function clear_cart_tracking_enabled() {
		remove_all_filters( 'metorik_cart_tracking_enabled' );
	}

	public static function clear_cart_actions_and_filters() {
		global $wp_actions;
		unset( $wp_actions['metorik_synced_cart'] );
		remove_all_actions( 'pre_http_request' );
		remove_all_actions( 'metorik_synced_cart' );
	}

	// props WooCommerce testing suite
	// https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/tests/legacy/framework/helpers/class-wc-helper-product.php#L35
	public static function create_product( $save = true, $props = [] ) {
		$product       = new WC_Product_Simple();
		$default_props = [
			'name'          => 'Dummy Product',
			'regular_price' => rand( 10, 50 ),
			'price'         => rand( 10, 50 ),
			'sku'           => 'DUMMY SKU ' . rand( 1, 999 ),
			'manage_stock'  => false,
			'tax_status'    => 'taxable',
			'downloadable'  => false,
			'virtual'       => false,
			'stock_status'  => 'instock',
			'weight'        => '1.1',
		];

		$product->set_props( array_merge( $default_props, $props ) );

		if ( $save ) {
			$product->save();

			return wc_get_product( $product->get_id() );
		} else {
			return $product;
		}
	}

	// props WooCommerce testing suite
	// https://github.com/woocommerce/woocommerce/blob/f2cf6b56aa762cb36eb9496b878c5c295376f16d/plugins/woocommerce/tests/legacy/framework/helpers/class-wc-helper-coupon.php#L20C2-L59C40
	public static function create_coupon( $coupon_code = 'mtk_coupon', $save = true, $meta = [] ) {
		// Insert post
		$coupon_id = wp_insert_post( [
			'post_title'   => $coupon_code,
			'post_type'    => 'shop_coupon',
			'post_status'  => 'publish',
			'post_excerpt' => 'This is a dummy coupon',
		] );

		$meta = wp_parse_args( $meta, [
			'discount_type'              => 'fixed_cart',
			'coupon_amount'              => '1',
			'individual_use'             => 'no',
			'product_ids'                => '',
			'exclude_product_ids'        => '',
			'usage_limit'                => '',
			'usage_limit_per_user'       => '',
			'limit_usage_to_x_items'     => '',
			'expiry_date'                => '',
			'free_shipping'              => 'no',
			'exclude_sale_items'         => 'no',
			'product_categories'         => [],
			'exclude_product_categories' => [],
			'minimum_amount'             => '',
			'maximum_amount'             => '',
			'customer_email'             => [],
			'usage_count'                => '0',
		] );

		// Update meta.
		foreach ( $meta as $key => $value ) {
			update_post_meta( $coupon_id, $key, $value );
		}

		$coupon = new WC_Coupon( $coupon_code );
		if ( $save ) {
			$coupon->save();
		}

		return $coupon;
	}

	// props WooCommerce testing suite
	// https://github.com/woocommerce/woocommerce/blob/f2cf6b56aa762cb36eb9496b878c5c295376f16d/plugins/woocommerce/tests/legacy/framework/helpers/class-wc-helper-shipping.php#L22C2-L37C1
	public static function create_flat_rate_shipping_zone( $cost = 10 ) {
		$flat_rate_settings = array(
			'enabled'      => 'yes',
			'title'        => 'Flat rate (' . $cost . ')',
			'availability' => 'all',
			'countries'    => '',
			'tax_status'   => 'taxable',
			'cost'         => $cost,
		);

		update_option( 'woocommerce_flat_rate_settings', $flat_rate_settings );
		update_option( 'woocommerce_flat_rate', array() );
		WC_Cache_Helper::get_transient_version( 'shipping', true );
		WC()->shipping()->load_shipping_methods();
	}

	public static function capture_customer_email( $email = self::DEFAULT_CUSTOMER_EMAIL ) {
		// set customer email
		WC()->customer->set_email( sanitize_email( $email ) );
		WC()->customer->set_billing_email( sanitize_email( $email ) );
		WC()->customer->save();
	}

	public static function check_cart_sync_outgoing_request_data( $callback ) {
		// check the outgoing HTTP request
		// returning true prevents the request from actually going out
		add_action( 'pre_http_request', function ( $pre, $parsed_args, $url ) use ( $callback ) {
			// only check metorik requests
			if ( ! str_contains( $url, 'metorik' ) ) {
				return false;
			}

			expect( str_contains( $url, 'api/store/incoming/carts' ) )->toBeTrue();
			expect( $parsed_args['method'] )->toEqual( 'POST' );

			$data = $parsed_args['body']['data'];

			$callback( $data );

			// this prevents the request from firing off
			return true;
		}, 10, 3 );

		self::simulateShutdownActionToFireOffSync();

		// check if the action was fired but should only happen once
		expect( did_action( 'metorik_synced_cart' ) )->toEqual( 1 );
	}

	public static function check_cart_synced_once() {
		add_action( 'pre_http_request', function ( $pre, $parsed_args, $url ) {
			// only check metorik requests
			if ( ! str_contains( $url, 'metorik' ) ) {
				return false;
			}

			// prevent the request from firing
			return true;
		}, 10, 3 );

		self::simulateShutdownActionToFireOffSync();

		// check if the action was fired but should only happen once
		expect( did_action( 'metorik_synced_cart' ) )->toEqual( 1 );
	}

	public static function check_cart_did_not_sync() {
		self::simulateShutdownActionToFireOffSync();

		// check that the action was never fired
		expect( did_action( 'metorik_synced_cart' ) )->toEqual( 0 );
	}

	protected static function simulateShutdownActionToFireOffSync(): void {
		// simulate the shutdown action to fire off the sync
		// remove wp_ob_end_flush_all to prevent buffer error
		remove_action( 'shutdown', 'wp_ob_end_flush_all', 1 );
		do_action( 'shutdown' );
	}

}
