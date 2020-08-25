<?php

/**
 * Custom changes that Metorik implements, like tracking referer.
 */
class Metorik_Custom
{
    /**
     * Current version of Metorik.
     */
    public $version = '1.4.1';

    /**
     * Possible fields.
     */
    public $fields = array(
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
    );

    /**
     * Field prefix (for the input field names).
     */
    public $fieldPrefix = 'metorik_source_';

    public function __construct()
    {
        add_action('wp_enqueue_scripts', array($this, 'scripts_styles'));

        // fields
        add_action('woocommerce_after_order_notes', array($this, 'source_form_fields'));
        add_action('woocommerce_register_form', array($this, 'source_form_fields'));

        // update
        add_action('woocommerce_checkout_update_order_meta', array($this, 'set_order_source'));
        add_action('user_register', array($this, 'set_customer_source'));
    }

    /**
     * Scripts & styles for Metorik's custom source tracking and cart tracking.
     */
    public function scripts_styles()
    {
        /*
         * Enqueue scripts.
         */
        wp_enqueue_script('metorik-js', plugins_url('assets/js/metorik.min.js', dirname(__FILE__)), array('jquery'), $this->version, true);

        /*
         * Enqueue styles.
         */
        wp_enqueue_style('metorik-css', plugins_url('assets/css/metorik.css', dirname(__FILE__)), '', $this->version);

        /**
         * Pass parameters to Metorik JS.
         */
        $params = array(
            'lifetime'                 => (int) apply_filters('metorik_cookie_lifetime', 6), // 6 months
            'session'                  => (int) apply_filters('metorik_session_length', 30), // 30 minutes
            'ajaxurl'                  => admin_url('admin-ajax.php'),
            'cart_tracking'            => get_option('metorik_auth_token') ? true : false,
            'cart_items'               => WC()->cart ? WC()->cart->get_cart_contents_count() : 0,
            'cart_checkout_button'     => apply_filters('metorik_acp_checkout_button', true),
            'add_cart_popup_placement' => apply_filters('metorik_acp_placement', 'bottom'),
        );
        wp_localize_script('metorik-js', 'metorik_params', $params);
    }

    /**
     * Add Metorik hidden input fields for checkout & customer register froms.
     */
    public function source_form_fields()
    {
        /*
         * Hidden field for each possible field.
         */
        foreach ($this->fields as $field) {
            echo '<input type="hidden" name="'.$this->fieldPrefix.$field.'" value="" />';
        }
    }

    /**
     * Set the source data in the order post meta.
     */
    public function set_order_source($order_id)
    {
        $this->set_source_data($order_id, 'order');
    }

    /**
     * Set the source data in the customer user meta.
     */
    public function set_customer_source($customer_id)
    {
        $this->set_source_data($customer_id, 'customer');
    }

    /**
     * Set source data.
     */
    public function set_source_data($id, $resource)
    {
        /**
         * Values.
         */
        $values = array();

        /*
         * Get each field if POSTed.
         */
        foreach ($this->fields as $field) {
            // default empty
            $values[$field] = '';

            // set if have
            if (isset($_POST[$this->fieldPrefix.$field]) && $_POST[$this->fieldPrefix.$field]) {
                $values[$field] = sanitize_text_field($_POST[$this->fieldPrefix.$field]);
            }
        }

        /**
         * Now parse values to set in meta.
         */

        // update function based on order or customer
        $update_function = $resource == 'order' ? 'update_post_meta' : 'update_user_meta';

        // type
        if ($values['type'] && $values['type'] !== '(none)') {
            $update_function($id, '_metorik_source_type', $values['type']);
        }
        unset($values['type']);

        // referer url
        if ($values['url'] && $values['url'] !== '(none)') {
            $update_function($id, '_metorik_referer', $values['url']);
        }
        unset($values['url']);

        // metorik engage
        if ($values['mtke'] && $values['mtke'] !== '(none)') {
            $update_function($id, '_metorik_engage', $values['mtke']);
        }
        unset($values['mtke']);

        // rest of fields - UTMs & sessions (if not '(none)')
        foreach ($values as $key => $value) {
            if ($value && $value !== '(none)') {
                $update_function($id, '_metorik_'.$key, $value);
            }
        }
    }
}

new Metorik_Custom();
