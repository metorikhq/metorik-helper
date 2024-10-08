<?php

/**
 * Orders API for Metorik.
 */
class Metorik_Helper_API_Metorik extends WC_REST_Posts_Controller {
	public $namespace = 'wc/v1';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'metorik_info_route' ) );
		add_action( 'rest_api_init', array( $this, 'metorik_importing_route' ) );
		add_action( 'rest_api_init', array( $this, 'metorik_auth_route' ) );
		add_action( 'rest_api_init', array( $this, 'metorik_cart_settings_routes' ) );
	}

	/**
	 * Metorik info route definition.
	 */
	public function metorik_info_route() {
		register_rest_route( $this->namespace, '/metorik/info/', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'metorik_info_callback' ),
			'permission_callback' => array( $this, 'get_items_permissions_check' ),
		) );
	}

	/**
	 * Metorik importing route definition.
	 */
	public function metorik_importing_route() {
		register_rest_route( $this->namespace, '/metorik/importing/', array(
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => array( $this, 'update_metorik_importing_callback' ),
			'permission_callback' => array( $this, 'update_items_permissions_check' ),
		) );
	}

	/**
	 * Metorik auth store data route definition.
	 */
	public function metorik_auth_route() {
		register_rest_route( $this->namespace, '/metorik/auth/', array(
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => array( $this, 'update_metorik_auth_callback' ),
			'permission_callback' => array( $this, 'update_items_permissions_check' ),
		) );
	}

	/**
	 * Metorik cart settings routes (GET AND PUT).
	 */
	public function metorik_cart_settings_routes() {
		register_rest_route( $this->namespace, '/metorik/cart-settings/', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_metorik_cart_settings_callback' ),
			'permission_callback' => array( $this, 'get_items_permissions_check' ),
		) );

		register_rest_route( $this->namespace, '/metorik/cart-settings/', array(
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => array( $this, 'update_metorik_cart_settings_callback' ),
			'permission_callback' => array( $this, 'update_items_permissions_check' ),
		) );
	}

	/**
	 * Check whether a given request has permission to read info.
	 */
	public function get_items_permissions_check( $request ) {
		if ( ! wc_rest_check_user_permissions( 'read' ) ) {
			return new WP_Error( 'woocommerce_rest_cannot_view', __( 'Sorry, you cannot list resources.', 'woocommerce' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Need write/create permission.
	 */
	public function update_items_permissions_check() {
		if ( ! wc_rest_check_user_permissions( 'create' ) ) {
			return new WP_Error( 'woocommerce_rest_cannot_create', __( 'Sorry, you are not allowed to create resources.', 'woocommerce' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Callback for the Metorik Info API endpoint.
	 */
	public function metorik_info_callback() {
		/*
		 * Get plugins.
		 */
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = get_plugins();

		/**
		 * Prepare response.
		 */
		$data = array(
			'active'    => true,
			'version'   => Metorik_Helper()->version,
			'server_ip' => $this->get_server_ip_address(),
			'plugins'   => $plugins,
		);

		/**
		 * Response.
		 */
		$response = rest_ensure_response( $data );
		$response->set_status( 200 );

		return $response;
	}

	private function is_valid_server_ip( $ip ) {
		if ( function_exists( 'filter_var' ) ) {
			$valid = filter_var( $ip, FILTER_VALIDATE_IP );
		} else {
			$valid = preg_match( '/^((\d{1,3}\.){3}\d{1,3}|([a-f0-9:]+:+)+[a-f0-9]+)$/i', $ip );
		}

		if ( ! $valid ) {
			return false;
		}

		return $ip !== '127.0.0.1' && $ip !== '0.0.0.0';
	}

	private function get_server_ip_address() {
		if ( ! empty( $_SERVER['SERVER_ADDR'] ) && $this->is_valid_server_ip( $_SERVER['SERVER_ADDR'] ) ) {
			return $_SERVER['SERVER_ADDR'];
		}

		if ( function_exists( 'gethostname' ) ) {
			$hostname         = gethostname();
			$ip_from_hostname = gethostbyname( $hostname );
			if ( $ip_from_hostname && $this->is_valid_server_ip( $ip_from_hostname ) ) {
				return $ip_from_hostname;
			}
		}

		$proxy_headers = [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR' ];
		foreach ( $proxy_headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip_list = explode(',', $_SERVER[$header]);
				foreach ($ip_list as $ip) {
					if ($this->is_valid_server_ip(trim($ip))) {
						return trim($ip);
					}
				}
			}
		}

		if ( ini_get( 'allow_url_fopen' ) && function_exists( 'file_get_contents' ) ) {
			$context = stream_context_create(['http' => ['timeout' => 5]]);
			$external_ip = @file_get_contents( 'https://api.ipify.org', false, $context );
			if ( $external_ip && $this->is_valid_server_ip( $external_ip ) ) {
				return $external_ip;
			}
		}

		return null;
	}

	/**
	 * Callback for the Orders API endpoint.
	 */
	public function update_metorik_importing_callback( $request ) {
		/*
		 * Check status set.
		 */
		if ( ! isset( $request['status'] ) ) {
			return new WP_Error( 'woocommerce_rest_metorik_invalid_importing_status', __( 'Invalid status.', 'woocommerce' ), array( 'status' => 400 ) );
		}

		/**
		 * Get and sanitize status.
		 */
		$status = $request['status'] ? true : false;

		/*
		 * Update status.
		 */
		update_option( 'metorik_importing_currently', $status );

		/**
		 * Prepare response.
		 */
		$data = array(
			'updated' => true,
			'status'  => get_option( 'metorik_importing_currently' ),
		);

		/**
		 * Response.
		 */
		$response = rest_ensure_response( $data );
		$response->set_status( 200 );

		return $response;
	}

	/**
	 * Callback.
	 */
	public function update_metorik_auth_callback( $request ) {
		/*
		 * Check token set.
		 */
		if ( ! isset( $request['token'] ) ) {
			return new WP_Error( 'woocommerce_rest_metorik_invalid_auth_token', __( 'Invalid token.', 'woocommerce' ), array( 'status' => 400 ) );
		}

		/**
		 * Get and sanitize token.
		 */
		$token = $request['token'] ? sanitize_text_field( $request['token'] ) : false;

		/*
		 * Update token.
		 */
		update_option( Metorik_Cart_Tracking::AUTH_TOKEN_OPTION, $token );

		/**
		 * Prepare response.
		 */
		$data = array(
			'updated' => true,
			'token'   => Metorik_Cart_Tracking::get_auth_token(),
		);

		/**
		 * Response.
		 */
		$response = rest_ensure_response( $data );
		$response->set_status( 200 );

		return $response;
	}

	/**
	 * Callback.
	 */
	public function get_metorik_cart_settings_callback( $request ) {
		/*
		 * Get settings.
		 */
		$settings = get_option( 'metorik_cart_settings' );

		/**
		 * Prepare response.
		 */
		$data = array(
			'settings' => $settings,
		);

		/**
		 * Response.
		 */
		$response = rest_ensure_response( $data );
		$response->set_status( 200 );

		return $response;
	}

	/**
	 * Callback.
	 */
	public function update_metorik_cart_settings_callback( $request ) {
		/*
		 * Check settings set.
		 */
		if ( ! isset( $request['settings'] ) ) {
			return new WP_Error( 'woocommerce_rest_metorik_missing_cart_settings', __( 'Missing settings.', 'woocommerce' ), array( 'status' => 400 ) );
		}

		/**
		 * Get, json encode, and sanitize settings.
		 */
		$settings = sanitize_text_field( json_encode( $request['settings'] ) );

		/*
		 * Update settings.
		 */
		update_option( 'metorik_cart_settings', $settings );

		/**
		 * Prepare response.
		 */
		$data = array(
			'updated'  => true,
			'settings' => get_option( 'metorik_cart_settings' ),
		);

		/**
		 * Response.
		 */
		$response = rest_ensure_response( $data );
		$response->set_status( 200 );

		return $response;
	}
}

new Metorik_Helper_API_Metorik();
