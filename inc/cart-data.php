<?php

/**
 * This class holds Metorik's Cart Data for easy access by the tracking and recovery process
 */
class Metorik_Cart_Data {

	public array $cart = [];
	public ?int $customer_id = null;
	public ?string $email = null;
	public ?string $name = null;
	public ?string $phone = null;
	public const LAST_CART_HASH = 'metorik_last_cart_hash';

	public function __construct() {
		$this->cart = isset( WC()->cart ) ? WC()->cart->get_cart() : [];
		$this->determine_customer_data();
	}

	public function determine_customer_data() {
		$this->determine_customer_id();
		$this->determine_email();
		$this->determine_name();
		$this->determine_phone();
	}

	public function determine_customer_id() {
		if ( is_user_logged_in() ) {
			$this->customer_id = get_current_user_id();
		} elseif ( ! empty(WC()->session) &&
		           ! empty( WC()->session->get( 'customer' ) )
		           && isset( WC()->session->get( 'customer' )['id'] )
		           && absint( WC()->session->get( 'customer' )['id'] ) > 0
		) {
			$this->customer_id = absint( WC()->session->get( 'customer' )['id'] );
		}
	}

	public function determine_email() {
		if ( ! empty( WC()->customer )
		     && is_email( WC()->customer->get_billing_email() )
		) {
			$this->email = sanitize_email( WC()->customer->get_billing_email() );
		} elseif ( ! empty( WC()->customer )
		           && is_email( WC()->customer->get_email() )
		) {
			$this->email = sanitize_email( WC()->customer->get_email() );
		} elseif ( is_user_logged_in() && is_email( wp_get_current_user()->user_email ) ) {
			$this->email = sanitize_email( wp_get_current_user()->user_email );
		} elseif ( ! empty( $_POST['email'] ) && is_email( $_POST['email'] ) ) {
			$this->email = sanitize_email( $_POST['email'] );
		}
	}

	public function determine_name() {
		if ( ! empty( WC()->customer )
		     && ! empty( WC()->customer->get_billing_first_name() )
		) {
			$this->name = WC()->customer->get_billing_first_name();
		} elseif ( ! empty( $_POST['first_name'] ) ) {
			$this->name = sanitize_text_field( $_POST['first_name'] );
		} elseif ( is_user_logged_in() ) {
			$this->name = wp_get_current_user()->first_name;
		}
	}

	public function determine_phone() {
		if ( ! empty( WC()->customer )
		     && ! empty( WC()->customer->get_billing_phone() )
		) {
			$this->phone = sanitize_text_field( WC()->customer->get_billing_phone() );
		} elseif ( ! empty( $_POST['phone'] ) ) {
			$this->phone = sanitize_text_field( $_POST['phone'] );
		}
	}

	public function to_array() {
		if ( $this->cart_is_empty() ) {
			return [
				'token' => $this->get_cart_token(),
				'cart'  => false,
			];
		}

		// ensure we have the latest customer data
		$this->determine_customer_data();

		return [
			'token'                        => $this->get_cart_token(),
			'cart'                         => $this->cart,
			'started_at'                   => current_time( 'timestamp', true ), // utc timestamp
			'total'                        => $this->get_cart_total(),
			'subtotal'                     => $this->get_cart_subtotal(),
			'total_tax'                    => $this->get_cart_tax(),
			'total_discount'               => $this->get_cart_discount(),
			'total_shipping'               => $this->get_cart_shipping(),
			'total_fee'                    => $this->get_cart_fee(),
			'currency'                     => get_woocommerce_currency(),
			'customer_id'                  => $this->customer_id,
			'email'                        => $this->email,
			'name'                         => $this->name,
			'phone'                        => $this->phone,
			'locale'                       => determine_locale(),
			'email_opt_out'                => $this->get_customer_email_opt_out(),
			'client_session'               => $this->get_client_session_data(),
			'display_prices_including_tax' => isset( WC()->cart ) && WC()->cart->display_prices_including_tax(),
		];
	}

	/**
	 * Check if the cart is empty.
	 *
	 * @return bool
	 */
	public function cart_is_empty() {
		return (bool) isset( WC()->cart ) && WC()->cart->is_empty();
	}

	/**
	 * Get the cart hash.
	 * Hash is composed of everything we would send to metorik
	 * except for the 'started_at' timestamp (since that is always set to now)
	 * This is used to determine if the cart data has changed since the last time we sent it
	 *
	 * @return string $hash
	 */
	public function get_hash() {
		$cart_data_for_hash = $this->to_array();
		if ( isset( $cart_data_for_hash['started_at'] ) ) {
			unset( $cart_data_for_hash['started_at'] );
		}

		return md5( json_encode( $cart_data_for_hash ) );
	}


	/**
	 * Get the last cart hash that was sent to Metorik.
	 * This is stored in the WC session.
	 *
	 * @return null|string $hash The last cart hash or null if not set
	 */
	public function get_last_hash() {
		return WC()->session->get( self::LAST_CART_HASH );
	}

	/**
	 * Save the current hash as the last hash.
	 * This is stored in the WC session.
	 *
	 * @return void
	 */
	public function save_last_hash() {
		WC()->session->set( self::LAST_CART_HASH, $this->get_hash() );
	}


	/**
	 * Get (and generate/set if needed) the cart token.
	 * The token is stored in the WC session
	 * If a user is logged in, it's also stored in user meta.
	 *
	 * @param int|bool $user_id
	 *
	 * @return string $token
	 */
	public function get_cart_token( $user_id = false ) {
		$user_id        = $user_id ?: get_current_user_id();
		$token          = null;
		$from_user_meta = false;

		if ( ! empty( $user_id ) ) {
			$token          = get_user_meta( $user_id, '_metorik_cart_token', true );
			$from_user_meta = true;
		}

		if ( empty( $token ) && WC()->session ) {
			$token = WC()->session->get( 'metorik_cart_token' );
		}

		if ( empty( $token ) ) {
			$token = $this->generate_cart_token();
		}

		if ( ! empty( $user_id ) && ! $from_user_meta ) {
			update_user_meta( $user_id, '_metorik_cart_token', $token );
		}

		WC()->session->set( 'metorik_cart_token', $token );

		return $token;
	}

	/**
	 * Generate a cart token.
	 * @return string $token
	 */
	public function generate_cart_token() {
		// Generate a cart token (md5 of current time & random number).
		return md5( time() . rand( 100, 10000 ) );
	}


	/**
	 * Return if a user has seen the add to cart form before.
	 *
	 * @return bool
	 */
	public static function user_has_seen_add_to_cart_form() {
		return (bool) ( WC()->session ) ? WC()->session->get( 'metorik_seen_add_to_cart_form', false ) : false;
	}

	/**
	 * Set the add cart form to having been 'seen' in the session.
	 *
	 * @return void
	 */
	public static function set_user_has_seen_add_to_cart_form() {
		WC()->session->set( 'metorik_seen_add_to_cart_form', true );
	}

	/**
	 * Get the customer email opt out setting.
	 *
	 * @param int|null $user_id optional user ID, otherwise will check session
	 *
	 * @return bool is the user opted out
	 */
	public static function get_customer_email_opt_out( $user_id = null ) {
		$user_id = $user_id ?: get_current_user_id();
		$opt_out = false;

		if ( ! empty( $user_id ) ) {
			$opt_out = get_user_meta( $user_id, '_metorik_customer_email_opt_out', true );
		}

		if ( empty( $token ) && WC()->session ) {
			$opt_out = WC()->session->get( 'metorik_customer_email_opt_out' );
		}

		return (bool) $opt_out;
	}

	/**
	 * Set the customer email opt out setting.
	 *
	 * @param bool $opt_out defaults to true
	 *
	 * @return bool the opt out setting
	 */
	public static function set_customer_email_opt_out( $opt_out = true ) {
		WC()->session->set( 'metorik_customer_email_opt_out', $opt_out );

		if ( $user_id = get_current_user_id() ) {
			update_user_meta( $user_id, '_metorik_customer_email_opt_out', $opt_out );
		}

		return $opt_out;
	}

	/**
	 * Get the customer email opt in setting.
	 *
	 * @param int|null $user_id optional user ID, otherwise will check session
	 *
	 * @return bool is the user opted out
	 */
	public static function get_customer_email_opt_in( $user_id = null ) {
		return (bool) ! self::get_customer_email_opt_out( $user_id );
	}

	/**
	 * Set the customer email opt in setting.
	 *
	 * @param bool $opt_in defaults to true
	 *
	 * @return bool the opt in setting
	 */
	public static function set_customer_email_opt_in( $opt_in = true ) {
		return self::set_customer_email_opt_out( ! $opt_in );
	}

	/**
	 * Cart total.
	 * If we're on a checkout page, WC will have calculated it already
	 * Otherwise, we need to get the calculation directly
	 *
	 * @return float $total
	 */
	protected function get_cart_total() {
		if ( ! isset( WC()->cart ) ) {
			return 0;
		}

		if (
			is_checkout() ||
			is_cart() ||
			defined( 'WOOCOMMERCE_CHECKOUT' ) ||
			defined( 'WOOCOMMERCE_CART' )
		) {
			return (float) WC()->cart->total;
		} else {
			return (float) WC()->cart->get_total( false );
		}
	}

	/**
	 * Get the cart subtotal (maybe inclusive of taxes).
	 *
	 * @return float $subtotal
	 */
	public function get_cart_subtotal() {
		if ( ! isset( WC()->cart ) ) {
			return 0;
		}

		if ( 'excl' === WC()->cart->display_prices_including_tax() ) {
			$subtotal = WC()->cart->subtotal_ex_tax;
		} else {
			$subtotal = WC()->cart->subtotal;
		}

		return (float) $subtotal;
	}

	/**
	 * Get the cart tax.
	 *
	 * @return float $tax
	 */
	public function get_cart_tax() {
		if ( ! isset( WC()->cart ) ) {
			return 0;
		}

		return WC()->cart->get_total_tax();
	}

	/**
	 * Get the cart discount (maybe inclusive of taxes).
	 *
	 * @return float $discount
	 */
	public function get_cart_discount() {
		if ( ! isset( WC()->cart ) ) {
			return 0;
		}

		$discount_total = WC()->cart->get_discount_total();
		$discount_tax   = WC()->cart->get_discount_tax();

		if ( 'excl' === WC()->cart->display_prices_including_tax() ) {
			return $discount_total;
		} else {
			return $discount_total + $discount_tax;
		}
	}

	/**
	 * Get the cart shipping (maybe inclusive of taxes).
	 *
	 * @return float $shipping
	 */
	public function get_cart_shipping() {
		if ( ! isset( WC()->cart ) ) {
			return 0;
		}

		$shipping_total = WC()->cart->get_shipping_total();
		$shipping_tax   = WC()->cart->get_shipping_tax();

		if ( 'excl' === WC()->cart->display_prices_including_tax() ) {
			return $shipping_total;
		} else {
			return $shipping_total + $shipping_tax;
		}
	}

	/**
	 * Get the cart fee (maybe inclusive of taxes).
	 *
	 * @return float $fee
	 */
	public function get_cart_fee() {
		if ( ! isset( WC()->cart ) ) {
			return 0;
		}

		$fee_total = (float) WC()->cart->get_fee_total();
		$fee_tax   = (float) WC()->cart->get_fee_tax();

		if ( 'excl' === WC()->cart->display_prices_including_tax() ) {
			return $fee_total;
		} else {
			return $fee_total + $fee_tax;
		}
	}

	/**
	 * Get data about the client's current session - eg. coupons, shipping.
	 *
	 * @return array $session_data
	 */
	public function get_client_session_data() {
		// No session? Stop
		if ( ! WC()->session ) {
			return;
		}

		return [
			'applied_coupons'         => WC()->session->get( 'applied_coupons' ),
			'chosen_shipping_methods' => WC()->session->get( 'chosen_shipping_methods' ),
			'shipping_method_counts'  => WC()->session->get( 'shipping_method_counts' ),
			'chosen_payment_method'   => WC()->session->get( 'chosen_payment_method' ),
		];
	}

	/**
	 * Check if the cart is pending recovery.
	 *
	 * @param int|null $user_id
	 *
	 * @return bool
	 */
	public static function cart_is_pending_recovery( $user_id = null ) {
		$user_id          = $user_id ?: get_current_user_id();
		$pending_recovery = false;

		if ( ! empty( $user_id ) ) {
			$pending_recovery = get_user_meta( $user_id, '_metorik_pending_recovery', true );
		}

		if ( empty( $pending_recovery ) && WC()->session ) {
			$pending_recovery = WC()->session->get( 'metorik_pending_recovery' );
		}

		return (bool) $pending_recovery;
	}

	/**
	 * Get a cart setting
	 * The settings are stored as a JSON string in the metorik_cart_settings option
	 * See api/metorik.php for more info
	 *
	 * @param string $key
	 *
	 * @return mixed
	 */
	public static function get_cart_setting( $key = false, $default = false ) {
		$settings = get_option( 'metorik_cart_settings' );

		// no key defined or settings saved? default
		if ( ! $key || ! $settings ) {
			return $default;
		}

		// json decode
		$settings = json_decode( $settings );

		// not set? default
		if ( ! isset( $settings->$key ) ) {
			return $default;
		}

		return $settings->$key;
	}
}
