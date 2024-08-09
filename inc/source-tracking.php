<?php

/**
 * This class implements Metorik's Source Tracking
 *
 */
class Metorik_Source_Tracking {
	private static $instance;
	private static $enabled = null;

	/**
	 * Possible fields.
	 */
	public const FIELDS = [
		// main
		'type',
		'url',
		'mtke',

		// utm
		'utm_campaign',
		'utm_source',
		'utm_medium',
		'utm_content',
		'utm_id',
		'utm_term',

		// additional
		'session_entry',
		'session_start_time',
		'session_pages',
		'session_count',
	];

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		if ( ! self::source_tracking_enabled() ) {
			return;
		}

		// legacy order
		add_action( 'woocommerce_checkout_update_order_meta', [ $this, 'set_order_source' ] );

		// blocks/api order
		add_action( 'woocommerce_store_api_checkout_order_processed', [ $this, 'set_order_source' ] );

		// user created
		add_action( 'user_register', [ $this, 'set_customer_source' ] );
	}

	/**
	 * Determine if source tracking is enabled
	 * Enabled by default unless overwritten by filter
	 *
	 * @return bool
	 */
	public static function source_tracking_enabled() {
		self::$enabled = self::$enabled ?? (bool) apply_filters( 'metorik_source_tracking_enabled', true );

		return self::$enabled;
	}

	/**
	 * The name of the cookie used for source tracking
	 *
	 * @return string
	 */
	public static function source_tracking_cookie_name() {
		return apply_filters( 'metorik_source_tracking_cookie_name', 'mtk_src_trk' );
	}

	/**
	 * Set the source data in the order post meta.
	 */
	public function set_order_source( $order_id ) {
		$this->set_source_data( $order_id, 'order' );
	}

	/**
	 * Set the source data in the customer user meta.
	 */
	public function set_customer_source( $customer_id ) {
		$this->set_source_data( $customer_id, 'customer' );
	}

	/**
	 * Set source data.
	 */
	public function set_source_data( $id, $resource ) {
		/**
		 * Values.
		 */
		$values = array();

		/*
		 * Get each field if in the cookie
		 */
		$cookie_name = self::source_tracking_cookie_name();
		$cookie      = isset( $_COOKIE[ $cookie_name ] ) && is_string( $_COOKIE[ $cookie_name ] ) ? $_COOKIE[ $cookie_name ] : '';
		$cookie      = json_decode( stripslashes( $cookie ), true );

		// if no/invalid cookie, bail
		if ( ! is_array( $cookie ) ) {
			return;
		}

		foreach ( self::FIELDS as $field ) {
			// default is empty
			$values[ $field ] = '';

			// if field is set in POST, sanitize and set
			if ( ! empty( $cookie[ $field ] ) ) {
				$values[ $field ] = sanitize_text_field( $cookie[ $field ] );
			}
		}


		// by default order should NOT save
		$orderShouldSave = false;

		// if orders, need to get the order object
		if ( $resource == 'order' ) {
			$order = wc_get_order( $id );

			if ( ! $order instanceof WC_Order ) {
				return;
			}
		}

		// metorik engage
		if ( $values['mtke'] && $values['mtke'] !== '(none)' ) {
			if ( $resource == 'order' ) {
				$order->update_meta_data( '_metorik_engage', $values['mtke'] );
				$orderShouldSave = true;
			} else {
				update_user_meta( $id, '_metorik_engage', $values['mtke'] );
			}
		}
		unset( $values['mtke'] );

		// only set next fields if filter not set to false
		// type
		if ( $values['type'] && $values['type'] !== '(none)' ) {
			if ( $resource == 'order' ) {
				$order->update_meta_data( '_metorik_source_type', $values['type'] );
				$orderShouldSave = true;
			} else {
				update_user_meta( $id, '_metorik_source_type', $values['type'] );
			}
		}
		unset( $values['type'] );

		// referer url
		if ( $values['url'] && $values['url'] !== '(none)' ) {
			if ( $resource == 'order' ) {
				$order->update_meta_data( '_metorik_referer', $values['url'] );
				$orderShouldSave = true;
			} else {
				update_user_meta( $id, '_metorik_referer', $values['url'] );
			}
		}
		unset( $values['url'] );

		// rest of fields - UTMs & sessions (if not '(none)')
		foreach ( $values as $key => $value ) {
			if ( $value && $value !== '(none)' ) {
				if ( $resource == 'order' ) {
					$order->update_meta_data( '_metorik_' . $key, $value );
					$orderShouldSave = true;
				} else {
					update_user_meta( $id, '_metorik_' . $key, $value );
				}
			}
		}

		// now save for orders (regardless filter) if SHOULD save (at least one meta updated above)
		if ( $resource == 'order' && $orderShouldSave ) {
			$order->save();
		}
	}
}

Metorik_Source_Tracking::instance();
