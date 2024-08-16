<?php

/**
 * This class implements Metorik's Cart Recovery System
 */
class Metorik_Cart_Recovery {
	private static $instance;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'api_recover_cart_route' ] );
		add_action( 'woocommerce_cart_loaded_from_session', [ $this, 'maybe_apply_cart_recovery_coupon' ], 11 );

		// Coupon features
		add_action( 'wp_loaded', [ $this, 'add_coupon_code_to_cart_session' ] );
		add_action( 'woocommerce_add_to_cart', [ $this, 'add_coupon_code_to_cart' ] );
	}

	/**
	 * Register REST API route for recovering a cart.
	 *
	 * @return void
	 */
	public function api_recover_cart_route() {
		register_rest_route( 'metorik/v1', '/recover-cart', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'recover_cart_callback' ],
			'permission_callback' => '__return_true',
		] );
	}

	/**
	 * API route callback for recovering a cart.
	 * This is the endpoint that the recovery link will hit.
	 * It will restore the cart and redirect to the checkout page.
	 * It will also apply a coupon if one is provided in the URL.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return void
	 */
	public function recover_cart_callback( $request ) {
		// bail if no token
		$cart_token = isset( $request['token'] ) ? $request['token'] : null;
		if ( empty( $cart_token ) ) {
			return;
		}

		// cart start
		$this->check_prerequisites();

		// set the checkout URL to use
		$checkout_url = $this->get_checkout_url();

		// forward along any params from allowed list
		foreach ( $request->get_params() as $key => $val ) {
			$allowed_key_prefixes = array_merge([ 'utm_', 'mtk', 'lang' ], apply_filters( 'metorik_cart_recovery_allowed_url_params', [] ) );
			foreach($allowed_key_prefixes as $prefix) {
				if ( 0 === strpos( $key, $prefix ) ) {
					$checkout_url = add_query_arg( $key, $val, $checkout_url );
				}
			}
		}

		// no session? start so cart/notices work
		if ( ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}

		// try to restore the cart
		try {
			$this->restore_cart( $cart_token );

			// check for coupon in recovery URL to apply before checkout redirect
			if ( isset( $request['coupon'] ) && $coupon = rawurldecode( $request['coupon'] ) ) {
				$checkout_url = add_query_arg( array( 'coupon' => wc_clean( $coupon ) ), $checkout_url );
			}
		} catch ( Exception $e ) {
			// add a notice
			wc_add_notice( __( 'Sorry, we were not able to restore your cart. Please try adding your items to your cart again.', 'metorik' ), 'error' );
		}

		// redirect to the checkout url
		wp_safe_redirect( $checkout_url );
		exit;
	}

	/**
	 * Check any prerequisites required for our add to cart request.
	 *
	 * @return void
	 */
	private function check_prerequisites() {
		if ( defined( 'WC_ABSPATH' ) ) {
			// WC 3.6+ - Cart and notice functions are not included during a REST request.
			include_once WC_ABSPATH . 'includes/wc-cart-functions.php';
			include_once WC_ABSPATH . 'includes/wc-notice-functions.php';
		}

		if ( null === WC()->session ) {
			$session_class = apply_filters( 'woocommerce_session_handler', 'WC_Session_Handler' );

			// Prefix session class with global namespace if not already namespaced
			if ( false === strpos( $session_class, '\\' ) ) {
				$session_class = '\\' . $session_class;
			}

			WC()->session = new $session_class();
			WC()->session->init();
		}

		if ( null === WC()->customer ) {
			WC()->customer = new \WC_Customer( get_current_user_id(), true );
		}

		if ( null === WC()->cart ) {
			WC()->cart = new \WC_Cart();

			// We need to force a refresh of the cart contents from session here (cart contents are normally refreshed on wp_loaded, which has already happened by this point).
			WC()->cart->get_cart();
		}
	}

	/**
	 * Determine the checkout URL to use
	 *
	 * @param WP_REST_Request|null $request the request object
	 *
	 * @return string the checkout URL
	 */
	public function get_checkout_url( $request = null ) {
		// default
		$checkout_url = wc_get_checkout_url();

		// override via settings
		$override_checkout_url = get_option( 'metorik_checkout_url' );
		if ( ! empty( $override_checkout_url ) ) {
			$checkout_url = $override_checkout_url;
		}

		// override via request
		$redirect_url = isset( $request['redirect_url'] ) ? $request['redirect_url'] : null;
		if ( ! empty( $redirect_url ) ) {
			$checkout_url = $redirect_url;
		}

		return apply_filters( 'metorik_recover_cart_url', $checkout_url );
	}

	/**
	 * Given a Cart Token, restore the cart from Metorik's API and put it back into the session.
	 *
	 * @param string $cart_token
	 *
	 * @return void
	 */
	public function restore_cart( $cart_token ) {
		$auth_token = Metorik_Cart_Tracking::get_auth_token();
		if ( empty( $auth_token ) ) {
			throw new Exception( 'Missing Metorik authentication token' );
		}

		// get cart
		$response = wp_remote_get( Metorik_Cart_Tracking::metorik_api_url() . '/external/carts', array(
			'body' => array(
				'api_token'  => $auth_token,
				'cart_token' => $cart_token,
			),
		) );

		// Error during response?
		if ( is_wp_error( $response ) ) {
			throw new Exception( 'Error getting cart from Metorik' );
		}

		$body = wp_remote_retrieve_body( $response );

		// no response body/cart?
		if ( ! $body ) {
			throw new Exception( 'Error getting cart from Metorik' );
		}

		// json decode
		$body = json_decode( $body );

		// no data/cart? stop
		if ( ! isset( $body->data ) || ! isset( $body->data->cart ) ) {
			throw new Exception( 'Error getting cart from Metorik' );
		}

		// get cart
		$cart = $body->data->cart;

		// need to cast all to an array for putting back into the session
		$cart = json_decode( json_encode( $cart ), true );

		// Clear any existing cart, but don't trigger cart tracking
		remove_action( 'woocommerce_cart_emptied', [ Metorik_Cart_Tracking::instance(), 'initiate_sync' ] );
		WC()->cart->empty_cart();

		// set the variation to an empty array if it doesn't exist
		// this is workaround for a php notice that can occur later when Woo pulls the cart
		foreach ( $cart as $key => $cart_item ) {
			if ( ! isset( $cart_item['variation'] ) ) {
				$cart_item['variation'] = [];
				$cart[ $key ]           = $cart_item;
			}
		}

		// Restore cart
		WC()->session->set( 'cart', $cart );

		// Set the cart token and pending recovery in session
		WC()->session->set( 'metorik_cart_token', $cart_token );
		WC()->session->set( 'metorik_pending_recovery', true );

		// Set the cart token / pending recovery in user meta if this cart has a user
		$user_id = $body->data->customer_id;
		if ( $user_id ) {
			update_user_meta( $user_id, '_metorik_cart_token', $cart_token );
			update_user_meta( $user_id, '_metorik_pending_recovery', true );
		}

		// restore customer email
		if ( ! empty( $body->data->email ) ) {
			WC()->customer->set_email( sanitize_email( $body->data->email ) );
			WC()->customer->set_billing_email( sanitize_email( $body->data->email ) );
		}

		// restore customer name
		if ( ! empty( $body->data->first_name ) ) {
			$first_name = $body->data->first_name;
			WC()->customer->set_first_name( sanitize_text_field( $first_name ) );
			WC()->customer->set_billing_first_name( sanitize_text_field( $first_name ) );
			WC()->customer->set_shipping_first_name( sanitize_text_field( $first_name ) );
		}

		// Customer phone
		if ( ! empty( $body->data->phone ) ) {
			WC()->customer->set_billing_phone( sanitize_text_field( $body->data->phone ) );
		}

		WC()->customer->save();

		// Client session
		$session = $body->data->client_session;
		if ( $session ) {
			if ( isset( $session->applied_coupons ) ) {
				$applied_coupons = (array) $session->applied_coupons;
				WC()->session->set( 'applied_coupons', $this->valid_coupons( $applied_coupons ) );
			}

			if ( isset( $session->chosen_shipping_methods ) ) {
				$chosen_shipping_methods = (array) $session->chosen_shipping_methods;
				WC()->session->set( 'chosen_shipping_methods', $chosen_shipping_methods );
			}

			if ( isset( $session->shipping_method_counts ) ) {
				$shipping_method_counts = (array) $session->shipping_method_counts;
				WC()->session->set( 'shipping_method_counts', $shipping_method_counts );
			}

			if ( isset( $session->chosen_payment_method ) ) {
				$chosen_payment_method = $session->chosen_payment_method;
				WC()->session->set( 'chosen_payment_method', $chosen_payment_method );
			}
		}

		// don't show add to cart restore when cart was recovered
		WC()->session->set( 'metorik_seen_add_to_cart_form', true );
	}

	/**
	 * Maybe apply the recovery coupon provided in the recovery URL.
	 *
	 * @return void
	 */
	public function maybe_apply_cart_recovery_coupon() {
		if ( Metorik_Cart_data::cart_is_pending_recovery() && ! empty( $_REQUEST['coupon'] ) ) {
			$coupon_code = wc_clean( rawurldecode( $_REQUEST['coupon'] ) );

			if ( WC()->cart && ! WC()->cart->has_discount( $coupon_code ) ) {
				WC()->cart->calculate_totals();
				WC()->cart->add_discount( $coupon_code );
			}
		}
	}


	/**
	 * Checks the validity of coupons that we try to apply to the cart.
	 *
	 * @param array $coupons coupons to check
	 *
	 * @return array $coupons valid coupons
	 */
	private function valid_coupons( $coupons = [] ) {
		$valid_coupons = [];
		if ( empty( $coupons ) ) {
			return $valid_coupons;
		}

		$discounts = new WC_Discounts( WC()->cart );
		foreach ( $coupons as $coupon_code ) {
			$coupon = new WC_Coupon( $coupon_code );
			$valid  = $discounts->is_coupon_valid( $coupon );

			if ( ! $valid ) {
				continue;
			}

			$valid_coupons[] = $coupon_code;
		}

		return $valid_coupons;
	}

	/**
	 * Add a coupon code to the cart session.
	 *
	 * @return void
	 */
	public function add_coupon_code_to_cart_session() {
		// Stop if no code in URL
		if ( empty( $_GET['mtkc'] ) ) {
			return;
		}

		// cart start
		$this->check_prerequisites();

		// no session? start so cart/notices work
		if ( ! WC()->session || ( WC()->session && ! WC()->session->has_session() ) ) {
			WC()->session->set_customer_session_cookie( true );
		}

		// Set code in session
		$coupon_code = esc_attr( $_GET['mtkc'] );
		WC()->session->set( 'mtk_coupon', $coupon_code );

		// If there is an existing non empty cart active session we apply the coupon
		if ( WC()->cart && ! WC()->cart->is_empty() && ! WC()->cart->has_discount( $coupon_code ) ) {
			WC()->cart->calculate_totals();
			WC()->cart->add_discount( $coupon_code );

			// Unset the coupon from the session
			WC()->session->__unset( 'mtk_coupon' );
		}
	}

	/**
	 * Add the Metorik session coupon code to the cart when adding a product.
	 *
	 * @return void
	 */
	public function add_coupon_code_to_cart() {
		$coupon_code = WC()->session ? WC()->session->get( 'mtk_coupon' ) : false;

		// no coupon code? stop
		if ( ! $coupon_code || empty( $coupon_code ) ) {
			return;
		}

		// only if have a cart but not this discount yet
		if ( WC()->cart && ! WC()->cart->has_discount( $coupon_code ) ) {
			WC()->cart->calculate_totals();
			WC()->cart->add_discount( $coupon_code );

			// Unset the coupon from the session
			WC()->session->__unset( 'mtk_coupon' );
		}
	}


}

Metorik_Cart_Recovery::instance();
