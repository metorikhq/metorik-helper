<?php

/**
 * This class implements Metorik's Cart Tracking
 *
 */
class Metorik_Cart_Tracking {
	private static $instance;
	private static $auth_token = null;
	private static $enabled = null;
	private static $dispatched_sync = false;
	public const API_URL = 'https://app.metorik.com/api/store';
	public const AUTH_TOKEN_OPTION = 'metorik_auth_token';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		// ensure static token is updated if option is updated
		add_action( "update_option_" . self::AUTH_TOKEN_OPTION, [ self::class, 'reload_auth_token' ] );
		add_action( "delete_option_" . self::AUTH_TOKEN_OPTION, [ self::class, 'reload_auth_token' ] );

		// sync cart whenever cart is updated (add, remove, update, etc)
		$this->initiate_sync_actions();

		// Customer login - link existing cart token to user account
		add_action( 'wp_login', [ $this, 'link_customer_existing_cart' ], 10, 2 );

		// Unset cart when order completed
		add_action( 'woocommerce_payment_complete', [ $this, 'unset_cart_token' ] );
		add_action( 'woocommerce_thankyou', [ $this, 'unset_cart_token' ] );

		// Render the add cart email form (that shows up when adding to cart)
		add_action( 'wp_footer', [ $this, 'add_cart_email_form' ] );

		// seen add to cart form
		add_action( 'wc_ajax_metorik_seen_add_to_cart_form', [ $this, 'set_seen_add_to_cart_form' ] );

		// opt out
		add_action( 'wc_ajax_metorik_email_opt_out', [ $this, 'opt_out' ] );

		// opt in
		add_action( 'wc_ajax_metorik_email_opt_in', [ $this, 'opt_in' ] );


		// ================
		// Blocks Support
		// ================

		// register block checkout fields
		add_action( 'woocommerce_blocks_loaded', [ $this, 'register_block_checkout_fields' ] );

		// Checkout
		add_action( 'woocommerce_store_api_checkout_order_processed', [ $this, 'checkout_order_processed' ] );


		// ================
		// Legacy (before blocks) Fields & Actions
		// ================

		// Move legacy email checkout field
		add_filter( 'woocommerce_checkout_fields', [ $this, 'move_checkout_email_field' ], 5 );

		// Add usage notice to legacy email field
		add_filter( 'woocommerce_form_field_email', [ $this, 'checkout_add_email_usage_notice' ], 100, 2 );

		// Capture email, name, phone from legacy checkout form
		add_action( 'wc_ajax_metorik_capture_customer_data', [ $this, 'capture_customer_data' ] );

		// Checkout
		add_action( 'woocommerce_checkout_order_processed', [ $this, 'checkout_order_processed' ] );
	}

	/**
	 * Add hooks to WooCommerce events, and trigger a cart sync
	 * whenever cart is updated (add, remove, update, etc)
	 *
	 * @return void
	 */
	protected function initiate_sync_actions() {
		$woo_cart_actions = apply_filters(
			'metorik_initate_cart_sync_events',
			[
				'woocommerce_add_to_cart',
				'woocommerce_applied_coupon',
				'woocommerce_cart_item_removed',
				'woocommerce_cart_item_restored',
				'woocommerce_cart_item_set_quantity',
				'woocommerce_after_calculate_totals',
			]
		);

		foreach ( $woo_cart_actions as $action ) {
			add_action( $action, [ $this, 'initiate_sync' ] );
		}
	}

	/**
	 * Get the auth token from the settings
	 *
	 * @return string
	 */
	public static function get_auth_token() {
		self::$auth_token = self::$auth_token ?: get_option( self::AUTH_TOKEN_OPTION );

		return self::$auth_token;
	}

	/**
	 * Reset the static auth token and enabled status
	 * when the metorik auth token is updated
	 *
	 * @return void
	 */
	public static function reload_auth_token() {
		self::$auth_token      = null;
		self::$enabled         = null;
		self::$dispatched_sync = false;
	}

	/**
	 * Determine if cart tracking is enabled
	 * This is determined by the auth token being present
	 * and can be filtered for finer control
	 *
	 * @return bool
	 */
	public static function cart_tracking_enabled() {
		self::$enabled = self::$enabled ?: (bool) self::get_auth_token();

		return (bool) apply_filters( 'metorik_cart_tracking_enabled', self::$enabled );
	}

	/**
	 * Get the Metorik API URL
	 * This can be filtered if needed
	 *
	 * @return string
	 */
	public static function metorik_api_url() {
		return apply_filters( 'metorik_carts_api_url', self::API_URL );
	}

	/**
	 * Initiate a cart sync on cart actions.
	 * e.g. add to cart, remove from cart, update cart, etc.
	 * The actual sync happens on shutdown for performance reasons
	 *
	 * @return void
	 */
	public function initiate_sync() {
		// stop if already dispatched
		if ( self::$dispatched_sync ) {
			return;
		}

		// stop if metorik cart tracking disabled
		if ( ! self::cart_tracking_enabled() ) {
			return;
		}

		// this will only fire once per page load
		add_action( 'shutdown', [ $this, 'sync_cart' ] );
		self::$dispatched_sync = true;
	}

	/**
	 * Sync cart data with Metorik.
	 *
	 * @return void
	 */
	public function sync_cart() {
		// stop if metorik cart tracking disabled
		if ( ! self::cart_tracking_enabled() ) {
			return;
		}

		// currently checking out? don't sync
		// we don't want to sync the cart when checking out, otherwise we end up with an empty cart sync
		// instead, the cart will get updated when the order is synced to Metorik
		if ( defined( 'WOOCOMMERCE_CHECKOUT' ) && WOOCOMMERCE_CHECKOUT === true ) {
			return;
		}

		$cart_data = ( new Metorik_Cart_Data() );

		// bail if no customer id, no email, and not pending recovery
		// since we don't store anonymous carts
		// the cart will be rejected at Metorik's end otherwise
		if ( empty( $cart_data->customer_id )
		     && empty( $cart_data->email )
		     && ! $cart_data->cart_is_pending_recovery()
		) {
			return;
		}

		// don't send if the cart hasn't changed since last hash was sent to metorik
		if ( ! empty( $cart_data->get_last_hash() ) && $cart_data->get_last_hash() === $cart_data->get_hash() ) {
			return;
		}

		$this->send_cart_data( [
			'api_token' => self::get_auth_token(),
			'data'      => $cart_data->to_array(),
		] );
		$cart_data->save_last_hash();
		do_action( 'metorik_synced_cart', $cart_data->to_array() );

		if ( $cart_data->cart_is_empty() ) {
			$this->unset_cart_token();
		}
	}

	/**
	 * Send cart data to Metorik.
	 *
	 * @param array $cart_data
	 *
	 * @return WP_Error|array
	 */
	public function send_cart_data( $cart_data ) {
		return wp_remote_post( self::metorik_api_url() . '/incoming/carts', [
			'body' => $cart_data,
		] );
	}

	/**
	 * Link a customer's existing cart when logging in.
	 *
	 * @param string $user_login
	 * @param WP_User $user
	 *
	 * @return void
	 */
	public function link_customer_existing_cart( $user_login, $user ) {
		$session_token = ( WC()->session ) ? WC()->session->get( 'metorik_cart_token' ) : '';

		// if session token and user, set in user meta
		if ( $session_token && $user ) {
			update_user_meta( $user->ID, '_metorik_cart_token', $session_token );
		}

		// trigger a sync
		$this->initiate_sync();
	}

	/**
	 * This is called once the checkout has been processed and an order has been created.
	 * We save the cart token to the order meta so we can link the order to the cart.
	 *
	 * @param int $order
	 *
	 * @return void
	 */
	public function checkout_order_processed( $order ) {
		// stop if metorik cart tracking disabled
		if ( ! self::cart_tracking_enabled() ) {
			return;
		}

		$cart_data  = ( new Metorik_Cart_Data() );
		$cart_token = $cart_data->get_cart_token();

		// save cart token to order meta
		if ( $cart_token ) {
			$order = wc_get_order( $order );

			if ( ! $order instanceof WC_Order ) {
				return;
			}

			$order->update_meta_data( '_metorik_cart_token', $cart_token );
			$order->save();
		}

		// check if pending recovery - if so, set in order meta
		if ( Metorik_Cart_Data::cart_is_pending_recovery() ) {
			$this->mark_order_as_recovered( $order );
		}
	}

	/**
	 * Mark an order as recovered by Metorik.
	 *
	 * @param int|WC_Order $order will be the order object or order ID
	 *
	 * @return void
	 */
	public function mark_order_as_recovered( $order ) {
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order );
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$order->update_meta_data( '_metorik_cart_recovered', true );
		$order->add_order_note( __( 'Order cart recovered by Metorik.', 'metorik' ) );
		$order->save();
	}

	/**
	 * Unset a cart token/recovery status.
	 * This should be performed when checking out after payment or when a cart is cleared
	 *
	 * @return void
	 */
	public function unset_cart_token() {
		if ( WC()->session ) {
			unset( WC()->session->metorik_cart_token, WC()->session->metorik_pending_recovery );
		}

		if ( $user_id = get_current_user_id() ) {
			delete_user_meta( $user_id, '_metorik_cart_token' );
			delete_user_meta( $user_id, '_metorik_pending_recovery' );
		}
	}

	/**
	 * Register block checkout fields
	 *
	 * @return void
	 * @throws Exception
	 */
	public function register_block_checkout_fields() {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}

		if ( ! apply_filters( 'metorik_cart_tracking_opt_in_checkbox_enabled', Metorik_Cart_Data::get_cart_setting( 'opt_in_checkbox_enabled', true ) ) ) {
			return;
		}

		woocommerce_register_additional_checkout_field( [
			'id'       => 'metorik/opt-in',
			'label'    => apply_filters( 'metorik_cart_tracking_opt_in_checkbox_label', Metorik_Cart_Data::get_cart_setting( 'opt_in_checkbox_label', __( 'Opt-in to receive cart reminders e-mails from this store', 'metorik' ) ) ),
			'location' => 'contact',
			'type'     => 'checkbox',
		] );

		// get the value from cart data
		add_filter(
			"woocommerce_get_default_value_for_metorik/opt-in",
			function ( $value ) {
				if ( is_null( $value ) ) {
					$value = Metorik_Cart_Data::get_customer_email_opt_in();
				}

				return $value;
			} );

		// don't set the value if in the admin
		if ( is_admin() ) {
			return;
		}

		// set the value to cart data
		add_action(
			'woocommerce_set_additional_field_value',
			function ( $key, $value, $group, $wc_object ) {
				if ( 'metorik/opt-in' !== $key ) {
					return;
				}

				Metorik_Cart_Data::set_customer_email_opt_in( (bool) $value );
			},
			10,
			4
		);
	}

	/**
	 * Render the add to cart email form into the footer (hidden)
	 * It is shown via JS via tippy.js:
	 *  - when a user adds to cart
	 *  - is not logged in
	 *  - has not seen/dismissed the form before
	 *
	 * It can be disabled via cart settings or via filter
	 *
	 * @return void
	 */
	public function add_cart_email_form() {
		// bail if metorik cart tracking disabled
		if ( ! metorik_cart_tracking_enabled() ) {
			return;
		}

		// bail if setting not enabled
		if ( ! Metorik_Cart_Data::get_cart_setting( 'add_cart_popup' ) ) {
			return;
		}

		// bail if user is logged in
		if ( is_user_logged_in() ) {
			return;
		}

		// bail if user has seen the form before
		if ( Metorik_Cart_Data::user_has_seen_add_to_cart_form() ) {
			return;
		}

		// Title
		$title = Metorik_Cart_Data::get_cart_setting( 'add_cart_popup_title' );
		if ( ! $title ) {
			$title = 'Save your cart?';
		}

		// Email usage notice
		$email_usage_notice = false;
		if ( Metorik_Cart_Data::get_cart_setting( 'email_usage_notice' ) && ! Metorik_Cart_Data::get_customer_email_opt_out() ) {
			$email_usage_notice = $this->render_email_usage_notice();
		}

		// Variables
		$args = [
			'title'              => $title,
			'email_usage_notice' => $email_usage_notice,
		];

		// Output template wrapped in 'add-cart-email-wrapper' div (used by JS)
		echo '<div class="add-cart-email-wrapper" style="display: none;">';
		Metorik_Helper::get_template( 'add-cart-email-form.php', $args );
		echo '</div>';
	}

	/**
	 * Move the email field to the top of the checkout billing form
	 * (WC 3.0+ but when no block support only).
	 *
	 * @param array $fields the checkout fields
	 *
	 * @return array $fields the modified fields
	 */
	public function move_checkout_email_field( $fields ) {
		// bail if metorik cart tracking disabled
		if ( ! metorik_cart_tracking_enabled() ) {
			return $fields;
		}

		// bail if setting disabled
		if ( ! Metorik_Cart_Data::get_cart_setting( 'move_email_field_top_checkout' ) ) {
			return $fields;
		}

		// bail if WC below 3
		if ( ! version_compare( WC()->version, '3.0.0', '>=' ) ) {
			return $fields;
		}

		// bail if field doesn't exist
		if ( ! isset( $fields['billing']['billing_email']['priority'] ) ) {
			return $fields;
		}

		$fields['billing']['billing_email']['priority']  = 5;
		$fields['billing']['billing_email']['class']     = [ 'form-row-wide' ];
		$fields['billing']['billing_email']['autofocus'] = true;

		// adjust layout of postcode/phone fields
		if ( isset( $fields['billing']['billing_postcode'], $fields['billing']['billing_phone'] ) ) {
			$fields['billing']['billing_postcode']['class'] = [ 'form-row-first', 'address-field' ];
			$fields['billing']['billing_phone']['class']    = [ 'form-row-last' ];
		}

		// remove autofocus from billing first name (set to email above)
		if ( isset( $fields['billing']['billing_first_name'] ) && ! empty( $fields['billing']['billing_first_name']['autofocus'] ) ) {
			$fields['billing']['billing_first_name']['autofocus'] = false;
		}

		return $fields;
	}

	/**
	 * Add the email usage notice to the email checkout field
	 * (WC 3.4+ but when no block support only).
	 *
	 * @param string $field the field being rendered
	 * @param string $key the key of the field being rendered
	 *
	 * @return string the field being rendered
	 */
	public function checkout_add_email_usage_notice( $field, $key ) {
		// bail if this isn't the email field
		if ( 'billing_email' !== $key ) {
			return $field;
		}

		// bail if metorik cart tracking disabled
		if ( ! metorik_cart_tracking_enabled() ) {
			return $field;
		}

		// bail if setting disabled
		if ( ! Metorik_Cart_Data::get_cart_setting( 'email_usage_notice' ) ) {
			return $field;
		}

		// bail if WC below 3.4
		if ( ! version_compare( WC()->version, '3.4.0', '>=' ) ) {
			return $field;
		}

		// bail if customer has opted out already
		if ( Metorik_Cart_Data::get_customer_email_opt_out() ) {
			return $field;
		}

		// find the trailing </p> tag to replace with our notice + </p>
		$pos     = strrpos( $field, '</p>' );
		$replace = $this->render_email_usage_notice() . '</p>';

		if ( false !== $pos ) {
			$field = substr_replace( $field, $replace, $pos, strlen( '</p>' ) );
		}

		return $field;
	}


	/**
	 * Render the email usage notice.
	 *
	 * @return string the email usage notice
	 */
	public function render_email_usage_notice() {
		/* translators: Placeholders: %1$s - opening HTML <a> link tag, %2$s - closing HTML </a> link tag */
		$notice = sprintf(
			__( 'We save your email and cart so we can send you reminders - %1$sdon\'t email me%2$s.', 'metorik' ),
			'<a href="#" class="metorik-email-usage-notice-link">',
			'</a>'
		);

		/**
		 * Filters the email usage notice contents.
		 */
		$notice = (string) apply_filters( 'metorik_cart_email_usage_notice', $notice );

		return '<span class="metorik-email-usage-notice" style="display:inline-block;padding-top:10px;">' . $notice . '</span>';
	}

	/**
	 * Capture customer data from checkout form (WC AJAX endpoint)
	 * This is used to update the customer's email, name, phone
	 * in the session and trigger a sync
	 *
	 * @return void
	 */
	public function capture_customer_data() {
		check_ajax_referer( 'metorik', 'security' );

		if ( ! empty( $_POST['email'] ) && is_email( $_POST['email'] ) ) {
			WC()->customer->set_email( sanitize_email( $_POST['email'] ) );
			WC()->customer->set_billing_email( sanitize_email( $_POST['email'] ) );
		}

		// Customer first name.
		if ( isset( $_POST['first_name'] ) ) {
			WC()->customer->set_first_name( sanitize_text_field( $_POST['first_name'] ) );
			WC()->customer->set_billing_first_name( sanitize_text_field( $_POST['first_name'] ) );
		}

		// Customer last name.
		if ( isset( $_POST['last_name'] ) ) {
			WC()->customer->set_last_name( sanitize_text_field( $_POST['last_name'] ) );
			WC()->customer->set_billing_last_name( sanitize_text_field( $_POST['last_name'] ) );
		}

		// Customer phone
		if ( isset( $_POST['phone'] ) ) {
			WC()->customer->set_billing_phone( sanitize_text_field( $_POST['phone'] ) );
		}

		WC()->customer->save();
		$this->initiate_sync();
	}

	/**
	 * Process WC ajax function to mark cart form as seen
	 *
	 * @return void
	 */
	public function set_seen_add_to_cart_form() {
		check_ajax_referer( 'metorik', 'security' );

		Metorik_Cart_Data::set_user_has_seen_add_to_cart_form();
	}

	/**
	 * Process WC ajax function to opt out of cart tracking
	 *
	 * @return void
	 */
	public function opt_out() {
		check_ajax_referer( 'metorik', 'security' );

		Metorik_Cart_Data::set_customer_email_opt_out();
		Metorik_Cart_Data::set_user_has_seen_add_to_cart_form();
		$this->initiate_sync();
	}

	public function opt_in() {
		check_ajax_referer( 'metorik', 'security' );

		Metorik_Cart_Data::set_customer_email_opt_in();
		$this->initiate_sync();
	}
}

Metorik_Cart_Tracking::instance();
