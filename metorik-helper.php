<?php

/**
 * Plugin Name: Metorik Helper
 * Plugin URI: https://metorik.com
 * Description: Reports, integrations, automatic emails, and cart tracking for WooCommerce stores.
 * Version: 2.0.9
 * Author: Metorik
 * Author URI: https://metorik.com
 * Text Domain: metorik
 * WC requires at least: 4.0.0
 * WC tested up to: 9.4.3
 * Requires Plugins: woocommerce
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */
class Metorik_Helper {
	/**
	 * Current version of Metorik.
	 */
	public $version = '2.0.9';

	/**
	 * URL dir for plugin.
	 */
	public $url;

	/**
	 * The single instance of the class.
	 */
	protected static $_instance = null;

	/**
	 * Main Metorik Helper Instance.
	 *
	 * Ensures only one instance of the Metorik Helper is loaded or can be loaded.
	 *
	 * @return Metorik Helper - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'plugins_loaded', [ $this, 'init' ] );
		add_action( 'init', [ $this, 'load_text_domain' ] );

		// Set URL
		$this->url = plugin_dir_url( __FILE__ );
	}

	/**
	 * Start plugin.
	 */
	public function init() {
		if ( class_exists( 'WooCommerce' ) ) {
			add_action( 'before_woocommerce_init', function () {
				// Woo HPOS compatibility
				if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
					\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
				}

				// Woo Blocks Support
				if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
					\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
				}
			} );

			// Activate notice (shown once)
			add_action( 'admin_notices', [ $this, 'activate_notice' ] );

			// Require files for the plugin
			require_once 'inc/functions.php';
			require_once 'inc/import.php';
			require_once 'inc/api.php';
			require_once 'inc/admin-ui.php';
			require_once 'inc/source-tracking.php';
			require_once 'inc/cart-data.php';
			require_once 'inc/cart-tracking.php';
			require_once 'inc/cart-recovery.php';

			// enqueue scripts & styles
			add_action( 'wp_enqueue_scripts', [ $this, 'scripts_styles' ] );
		} else {
			add_action( 'admin_notices', [ $this, 'no_wc' ] );
		}
	}

	public function load_text_domain() {
		load_plugin_textdomain( 'metorik', false, basename( dirname( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Scripts & styles for Metorik's custom source tracking and cart tracking.
	 */
	public function scripts_styles() {
		/*
		 * Enqueue scripts.
		 */
		wp_enqueue_script( 'metorik-js', plugins_url( 'assets/js/metorik.min.js', __FILE__ ), [ 'jquery' ], $this->version, true );

		/*
		 * Enqueue styles.
		 */
		wp_enqueue_style( 'metorik-css', plugins_url( 'assets/css/metorik.css', __FILE__ ), '', $this->version );

		/*
		 * Prepare cart items - possible to disable through a filter.
		 */
		$cart_items_count = 0;
		if ( apply_filters( 'metorik_cart_items', true ) ) {
			$cart_items_count = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
		}

		/**
		 * Pass parameters to Metorik JS.
		 */
		$params = [
			'source_tracking' => [
				'enabled'         => Metorik_Source_Tracking::source_tracking_enabled(),
				'cookie_lifetime' => (int) apply_filters( 'metorik_cookie_lifetime', 6 ), // 6 months
				'session_length'  => (int) apply_filters( 'metorik_session_length', 30 ), // 30 minutes
				'sbjs_domain'     => apply_filters( 'metorik_sbjs_domain', false ),
				'cookie_name'     => Metorik_Source_Tracking::source_tracking_cookie_name(),
			],
			'cart_tracking'   => [
				'enabled'                           => metorik_cart_tracking_enabled(),
				'cart_items_count'                  => $cart_items_count,
				'item_was_added_to_cart'            => isset( $_REQUEST['add-to-cart'] ) && is_numeric( $_REQUEST['add-to-cart'] ),
				'wc_ajax_capture_customer_data_url' => WC_AJAX::get_endpoint( 'metorik_capture_customer_data' ),
				'wc_ajax_email_opt_out_url'         => WC_AJAX::get_endpoint( 'metorik_email_opt_out' ),
				'wc_ajax_email_opt_in_url'          => WC_AJAX::get_endpoint( 'metorik_email_opt_in' ),
				'wc_ajax_seen_add_to_cart_form_url' => WC_AJAX::get_endpoint( 'metorik_seen_add_to_cart_form' ),
				'add_cart_popup_should_scroll_to'   => apply_filters( 'metorik_acp_should_scroll_to', true ),
				'add_cart_popup_placement'          => apply_filters( 'metorik_acp_placement', 'bottom' ),
				'add_to_cart_should_mark_as_seen'   => apply_filters( 'metorik_acp_should_mark_as_seen', true ),
				'add_to_cart_form_selectors'        => apply_filters( 'metorik_acp_form_selectors', [
					'.ajax_add_to_cart',
					'.single_add_to_cart_button',
				] ),
			],
			'nonce'           => wp_create_nonce( 'metorik' ),
		];
		wp_localize_script( 'metorik-js', 'metorik_params', $params );
	}

	/**
	 * No WC notice.
	 */
	public function no_wc() {
		echo '<div class="notice notice-error"><p>' . sprintf( __( 'Metorik Helper requires %s to be installed and active.', 'metorik' ), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ) . '</p></div>';
	}

	/**
	 * Run on activation.
	 */
	public static function activate() {
		// Set Metorik's show activation notice option to true if it isn't already false (only first time)
		if ( get_option( 'metorik_show_activation_notice', true ) ) {
			update_option( 'metorik_show_activation_notice', true );
		}
	}

	/**
	 * Activate notice (if we should).
	 */
	public function activate_notice() {
		if ( get_option( 'metorik_show_activation_notice', false ) ) {
			echo '<div class="notice notice-success"><p>' . sprintf( __( 'The Metorik Helper is active! Go back to %s to complete the connection.', 'metorik' ), '<a href="https://app.metorik.com/" target="_blank">Metorik</a>' ) . '</p></div>';

			// Disable notice option
			update_option( 'metorik_show_activation_notice', false );
		}
	}

	/**
	 * Get & render template file
	 *
	 * Search for the template and include the file.
	 *
	 * @author https://jeroensormani.com/how-to-add-template-files-in-your-plugin/
	 *
	 * @see $this->locate_template()
	 *
	 * @param string $template_name Template to load.
	 * @param array $args Args passed for the template file.
	 * @param string $tempate_path Path to templates.
	 * @param string $default_path Default path to template files.
	 */
	public static function get_template( $template_name, $args = [], $tempate_path = '', $default_path = '' ) {
		if ( is_array( $args ) && isset( $args ) ) {
			extract( $args );
		}

		$template_file = self::locate_template( $template_name, $tempate_path, $default_path );

		if ( ! file_exists( $template_file ) ) {
			_doing_it_wrong( __FUNCTION__, sprintf( '<code>%s</code> does not exist.', $template_file ), '1.0.0' );

			return;
		}

		include $template_file;
	}


	/**
	 * Locate template.
	 *
	 * Locate the called template.
	 * Search Order:
	 * 1. /themes/theme/metorik/$template_name
	 * 2. /themes/theme/$template_name
	 * 3. /plugins/metorik/templates/$template_name.
	 *
	 * @author https://jeroensormani.com/how-to-add-template-files-in-your-plugin/
	 *
	 * @param string $template_name Template to load.
	 * @param string $template_path Path to template.
	 * @param string $default_path Default path to template files.
	 *
	 * @return string Path to the template file.
	 */
	public static function locate_template( $template_name, $template_path = '', $default_path = '' ) {
		// Set variable to search in metorik folder of theme.
		if ( ! $template_path ) {
			$template_path = 'metorik/';
		}

		// Set default plugin templates path.
		if ( ! $default_path ) {
			$default_path = plugin_dir_path( __FILE__ ) . 'templates/'; // Path to the template folder
		}

		// Search template file in theme folder.
		$template = locate_template( [
			$template_path . $template_name,
			$template_name,
		] );

		// Get plugins template file.
		if ( ! $template ) {
			$template = $default_path . $template_name;
		}

		return apply_filters( 'metorik_locate_template', $template, $template_name, $template_path, $default_path );
	}
}

// Notice after it's been activated
register_activation_hook( __FILE__, [ 'Metorik_Helper', 'activate' ] );

/**
 * For plugin-wide access to initial instance.
 */
function Metorik_Helper() {
	return Metorik_Helper::instance();
}

Metorik_Helper();
