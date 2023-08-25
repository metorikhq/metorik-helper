<?php

/**
 * Custom changes that Metorik implements, like tracking referer.
 */
class Metorik_Custom
{
    /**
     * Current version of Metorik.
     */
    public $version = '1.7.1';

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

        /*
         * Prepare cart items - possible to disable through a filter.
         */
        $cart_items = 0;
        if (apply_filters('metorik_cart_items', true)) {
            $cart_items = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
        }

        /**
         * Pass parameters to Metorik JS.
         */
        $params = array(
            'lifetime'                 => (int) apply_filters('metorik_cookie_lifetime', 6), // 6 months
            'session'                  => (int) apply_filters('metorik_session_length', 30), // 30 minutes
            'ajaxurl'                  => admin_url('admin-ajax.php'),
            'cart_tracking'            => metorik_cart_tracking_enabled(),
            'cart_items'               => $cart_items,
            'cart_checkout_button'     => apply_filters('metorik_acp_checkout_button', true),
            'add_cart_popup_placement' => apply_filters('metorik_acp_placement', 'bottom'),
            'send_cart_events'         => apply_filters('metorik_send_cart_events', 'added_to_cart removed_from_cart updated_cart_totals updated_shipping_method applied_coupon removed_coupon updated_checkout'),
            'sbjs_domain'              => apply_filters('metorik_sbjs_domain', false),
            'send_cart_fragments'      => apply_filters('metorik_send_cart_fragments', true),
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

        // by default order should NOT save
        $orderShouldSave = false;

        // if orders, need to get the order object
        if ($resource == 'order') {
            $order = wc_get_order($id);

            if (!$order instanceof WC_Order) {
                return;
            }
        }

        // metorik engage
        if ($values['mtke'] && $values['mtke'] !== '(none)') {
            if ($resource == 'order') {
                $order->update_meta_data('_metorik_engage', $values['mtke']);
                $orderShouldSave = true;
            } else {
                update_user_meta($id, '_metorik_engage', $values['mtke']);
            }
        }
        unset($values['mtke']);

        // only set next fields if filter not set to false
        if (apply_filters('metorik_source_tracking_enabled', true)) {
            // type
            if ($values['type'] && $values['type'] !== '(none)') {
                if ($resource == 'order') {
                    $order->update_meta_data('_metorik_source_type', $values['type']);
                    $orderShouldSave = true;
                } else {
                    update_user_meta($id, '_metorik_source_type', $values['type']);
                }
            }
            unset($values['type']);

            // referer url
            if ($values['url'] && $values['url'] !== '(none)') {
                if ($resource == 'order') {
                    $order->update_meta_data('_metorik_referer', $values['url']);
                    $orderShouldSave = true;
                } else {
                    update_user_meta($id, '_metorik_referer', $values['url']);
                }
            }
            unset($values['url']);

            // rest of fields - UTMs & sessions (if not '(none)')
            foreach ($values as $key => $value) {
                if ($value && $value !== '(none)') {
                    if ($resource == 'order') {
                        $order->update_meta_data('_metorik_'.$key, $value);
                        $orderShouldSave = true;
                    } else {
                        update_user_meta($id, '_metorik_'.$key, $value);
                    }
                }
            }
        }

        // now save for orders (regardless filter) if SHOULD save (at least one meta updated above)
        if ($resource == 'order' && $orderShouldSave) {
            $order->save();
        }
    }
}

new Metorik_Custom();
