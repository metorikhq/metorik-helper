<?php
/**
 * Plugin Name: Metorik Helper
 * Plugin URI: https://metorik.com
 * Description: Reports, integrations, automatic emails, and cart tracking for WooCommerce stores.
 * Version: 1.4.1
 * Author: Metorik
 * Author URI: https://metorik.com
 * Text Domain: metorik
 * WC requires at least: 2.6.0
 * WC tested up to: 4.4.0.
 */
class Metorik_Helper
{
    /**
     * Current version of Metorik.
     */
    public $version = '1.4.1';

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
    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * Constructor.
     */
    public function __construct()
    {
        add_action('plugins_loaded', array($this, 'init'));

        // Set URL
        $this->url = plugin_dir_url(__FILE__);
    }

    /**
     * Start plugin.
     */
    public function init()
    {
        if (class_exists('WooCommerce')) {
            // Activate notice (shown once)
            add_action('admin_notices', array($this, 'activate_notice'));

            // Require files for the plugin
            require_once 'inc/functions.php';
            require_once 'inc/import.php';
            require_once 'inc/api.php';
            require_once 'inc/ui.php';
            require_once 'inc/custom.php';
            require_once 'inc/carts.php';
        } else {
            add_action('admin_notices', array($this, 'no_wc'));
        }

        // Plugin textdomain
        load_plugin_textdomain('metorik', false, basename(dirname(__FILE__)).'/languages/');
    }

    /**
     * No WC notice.
     */
    public function no_wc()
    {
        echo '<div class="notice notice-error"><p>'.sprintf(__('Metorik Helper requires %s to be installed and active.', 'metorik'), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>').'</p></div>';
    }

    /**
     * Run on activation.
     */
    public static function activate()
    {
        // Set Metorik's show activation notice option to true if it isn't already false (only first time)
        if (get_option('metorik_show_activation_notice', true)) {
            update_option('metorik_show_activation_notice', true);
        }
    }

    /**
     * Activate notice (if we should).
     */
    public function activate_notice()
    {
        if (get_option('metorik_show_activation_notice', false)) {
            echo '<div class="notice notice-success"><p>'.sprintf(__('The Metorik Helper is active! Go back to %s to complete the connection.', 'metorik'), '<a href="https://app.metorik.com/" target="_blank">Metorik</a>').'</p></div>';

            // Disable notice option
            update_option('metorik_show_activation_notice', false);
        }
    }
}

// Notice after it's been activated
register_activation_hook(__FILE__, array('Metorik_Helper', 'activate'));

/**
 * For plugin-wide access to initial instance.
 */
function Metorik_Helper()
{
    return Metorik_Helper::instance();
}

Metorik_Helper();
