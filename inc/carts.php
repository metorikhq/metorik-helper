<?php

/**
 * This class loads Metorik's carts endpoints/code.
 */
class Metorik_Helper_Carts
{
    protected $apiUrl = 'https://app.metorik.com/api/store';

    /**
     * Constructor.
     */
    public function __construct()
    {
        // Cart sending (ajax/actions)
        add_action('wp_ajax_nopriv_metorik_send_cart', array($this, 'ajax_send_cart'));
        add_action('wp_ajax_metorik_send_cart', array($this, 'ajax_send_cart'));
        add_action('woocommerce_cart_item_removed', array($this, 'check_cart_empty_and_send'));

        // Customer login - link existing cart token to user account
        add_action('wp_login', array($this, 'link_customer_existing_cart'), 10, 2);

        // Checkout
        add_action('woocommerce_checkout_order_processed', array($this, 'checkout_order_processed'));

        // Unset cart
        add_action('woocommerce_payment_complete', array($this, 'unset_cart_token'));
        add_action('woocommerce_thankyou', array($this, 'unset_cart_token'));

        // Cart recovery
        add_action('rest_api_init', array($this, 'api_recover_cart_route'));
        add_action('woocommerce_cart_loaded_from_session', array($this, 'maybe_apply_cart_recovery_coupon'), 11);

        // Email usage notices and opting out
        add_filter('woocommerce_form_field_email', array($this, 'checkout_add_email_usage_notice'), 100, 2);
        add_action('wp_ajax_nopriv_metorik_email_opt_out', array($this, 'ajax_email_opt_out'));
        add_action('wp_ajax_metorik_email_opt_out', array($this, 'ajax_email_opt_out'));

        // Move email checkout field
        add_filter('woocommerce_checkout_fields', array($this, 'move_checkout_email_field'), 5);

        // Email add cart form (display / ajax to not display again)
        add_action('wp_ajax_nopriv_metorik_add_cart_form_seen', array($this, 'ajax_set_seen_add_cart_form'));
        add_action('wp_ajax_metorik_add_cart_form_seen', array($this, 'ajax_set_seen_add_cart_form'));
        add_action('wp_footer', array($this, 'add_cart_email_form'));

        // Coupon features
        add_action('wp_loaded', array($this, 'add_coupon_code_to_cart_session'));
        add_action('woocommerce_add_to_cart', array($this, 'add_coupon_code_to_cart'));
    }

    /**
     * Check any prerequisites required for our add to cart request.
     * From https://barn2.co.uk/managing-cart-rest-api-woocommerce-3-6/.
     */
    private function check_prerequisites()
    {
        if (defined('WC_ABSPATH')) {
            // WC 3.6+ - Cart and notice functions are not included during a REST request.
            include_once WC_ABSPATH.'includes/wc-cart-functions.php';
            include_once WC_ABSPATH.'includes/wc-notice-functions.php';
        }

        if (null === WC()->session) {
            $session_class = apply_filters('woocommerce_session_handler', 'WC_Session_Handler');

            //Prefix session class with global namespace if not already namespaced
            if (false === strpos($session_class, '\\')) {
                $session_class = '\\'.$session_class;
            }

            WC()->session = new $session_class();
            WC()->session->init();
        }

        if (null === WC()->customer) {
            WC()->customer = new \WC_Customer(get_current_user_id(), true);
        }

        if (null === WC()->cart) {
            WC()->cart = new \WC_Cart();

            // We need to force a refresh of the cart contents from session here (cart contents are normally refreshed on wp_loaded, which has already happened by this point).
            WC()->cart->get_cart();
        }
    }

    /**
     * Generate a cart token (md5 of current time & random number).
     *
     * @todo improve.
     */
    public function generate_cart_token()
    {
        $token = md5(time().rand(100, 10000));

        return $token;
    }

    /**
     * Get or set the cart token.
     */
    public function get_or_set_cart_token()
    {
        if (!$token = $this->get_cart_token()) {
            $token = $this->set_cart_token();
        }

        return $token;
    }

    /**
     * Get cart token from session / user meta.
     */
    public function get_cart_token($user_id = false)
    {
        if ($user_id || ($user_id = get_current_user_id())) {
            $token = get_user_meta($user_id, '_metorik_cart_token', true);

            // if no user meta token, check session cart token first and use that
            if (!$token && WC()->session && WC()->session->get('metorik_cart_token')) {
                $token = WC()->session->get('metorik_cart_token');
                update_user_meta($user_id, '_metorik_cart_token', $token);
            }

            return $token;
        } else {
            return (WC()->session) ? WC()->session->get('metorik_cart_token') : '';
        }
    }

    /**
     * Set the cart token in session/user meta.
     */
    public function set_cart_token()
    {
        $token = $this->generate_cart_token();

        WC()->session->set('metorik_cart_token', $token);

        if ($user_id = get_current_user_id()) {
            update_user_meta($user_id, '_metorik_cart_token', $token);
        }

        return $token;
    }

    /**
     * Return if a user has seen the add to cart form before.
     */
    public function seen_add_cart_form()
    {
        return (bool) (WC()->session) ? WC()->session->get('metorik_seen_add_to_cart_form') : false;
    }

    /**
     * Ajax set the add cart form to having been 'seen' in the session.
     */
    public function ajax_set_seen_add_cart_form()
    {
        WC()->session->set('metorik_seen_add_to_cart_form', true);
    }

    /**
     * Ajax email opt out.
     */
    public function ajax_email_opt_out()
    {
        $this->set_customer_email_opt_out(true);
    }

    /**
     * Get the customer email opt out setting.
     */
    public function get_customer_email_opt_out($user_id = false)
    {
        if ($user_id || ($user_id = get_current_user_id())) {
            return (bool) get_user_meta($user_id, '_metorik_customer_email_opt_out', true);
        } elseif (isset(WC()->session)) {
            return (bool) WC()->session->metorik_customer_email_opt_out;
        }
    }

    /**
     * Set the customer email opt out setting.
     */
    public function set_customer_email_opt_out($opt_out = true)
    {
        WC()->session->set('metorik_customer_email_opt_out', $opt_out);

        if ($user_id = get_current_user_id()) {
            update_user_meta($user_id, '_metorik_customer_email_opt_out', $opt_out);
        }

        return $opt_out;
    }

    /**
     * Unset a cart token/recovery status.
     * Done when checking out after payment.
     */
    public function unset_cart_token()
    {
        if (WC()->session) {
            unset(WC()->session->metorik_cart_token, WC()->session->metorik_pending_recovery);
        }

        if ($user_id = get_current_user_id()) {
            delete_user_meta($user_id, '_metorik_cart_token');
            delete_user_meta($user_id, '_metorik_pending_recovery');
        }
    }

    /**
     * Was the current cart/checkout created by a Metorik recovery URL?
     *
     * @return bool
     */
    public static function cart_is_pending_recovery($user_id = null)
    {
        if ($user_id || ($user_id = get_current_user_id())) {
            return (bool) get_user_meta($user_id, '_metorik_pending_recovery', true);
        } elseif (isset(WC()->session)) {
            return (bool) WC()->session->metorik_pending_recovery;
        }

        return false;
    }

    /**
     * Send cart ajax. Only if have cart!
     * Only if metorik auth token set up.
     *
     * @return void
     */
    public function ajax_send_cart()
    {
        // metorik auth token? if none, stop
        $metorik_auth_token = get_option('metorik_auth_token');
        if (!$metorik_auth_token) {
            return;
        }

        // variables
        $cart = WC()->cart->get_cart();
        $token = $this->get_or_set_cart_token();
        $customer_id = get_current_user_id();
        $email = isset($_POST['email']) && $_POST['email'] ? sanitize_email($_POST['email']) : null;
        $name = isset($_POST['name']) && $_POST['name'] ? sanitize_text_field($_POST['name']) : null;

        // if no cart, stop (empty cart clearing is handled with separate action/method in this class)
        if (!$cart) {
            return;
        }

        $data = array(
            'api_token' => $metorik_auth_token,
            'data'      => array(
                'token'             => $token,
                'cart'              => $cart,
                'started_at'        => current_time('timestamp', true), // utc timestamp
                'total'             => (float) $this->get_cart_total(),
                'subtotal'          => (float) $this->get_cart_subtotal(),
                'total_tax'         => (float) (WC()->cart->tax_total + WC()->cart->shipping_tax_total),
                'total_discount'    => (float) WC()->cart->discount_cart,
                'total_shipping'    => (float) WC()->cart->shipping_total,
                'currency'          => get_woocommerce_currency(),
                'customer_id'       => $customer_id,
                'email'             => $email,
                'name'              => $name,
                'email_opt_out'     => $this->get_customer_email_opt_out(),
                'client_session'    => $this->get_client_session_data(),
            ),
        );

        $response = wp_remote_post($this->apiUrl.'/incoming/carts', array(
            'body' => $data,
        ));

        wp_die();
    }

    /**
     * Hooks into a cart item being removed action.
     * Checks if the cart is empty. If so, sends the
     * empty cart to Metorik (and clears token).
     *
     * @return void
     */
    public function check_cart_empty_and_send()
    {
        // only continue if the cart is empty
        if (WC()->cart->is_empty()) {
            // metorik auth token? if none, stop
            $metorik_auth_token = get_option('metorik_auth_token');
            if (!$metorik_auth_token) {
                return;
            }

            // clear cart remotely by sending empty cart
            $token = $this->get_or_set_cart_token();

            $response = wp_remote_post($this->apiUrl.'/incoming/carts', array(
                'body' => array(
                    'api_token' => $metorik_auth_token,
                    'data'      => array(
                        'token'             => $token,
                        'cart'              => false,
                    ),
                ),
            ));

            // clear the cart token/data from the session/user
            $this->unset_cart_token();
        }
    }

    /**
     * Cart total.
     * Since WC won't calculate total unless on cart/checkout,
     * we need an alternative method to do it manually.
     */
    protected function get_cart_total()
    {
        if (
            is_checkout() ||
            is_cart() ||
            defined('WOOCOMMERCE_CHECKOUT') ||
            defined('WOOCOMMERCE_CART')
        ) {
            return WC()->cart->total;
        } else {
            // product page, etc. - total not calculated but tax/shipping maybe
            return WC()->cart->subtotal_ex_tax +
                WC()->cart->tax_total +
                WC()->cart->shipping_tax_total +
                WC()->cart->shipping_total;
        }
    }

    /**
     * Get the cart subtotal (maybe inclusive of taxes).
     */
    public function get_cart_subtotal()
    {
        if ('excl' === get_option('woocommerce_tax_display_cart')) {
            $subtotal = WC()->cart->subtotal_ex_tax;
        } else {
            $subtotal = WC()->cart->subtotal;
        }

        return $subtotal;
    }

    /**
     * Get data about the client's current session - eg. coupons, shipping.
     * Later maybe more customer info like addresses.
     */
    public function get_client_session_data()
    {
        // No session? Stop
        if (!WC()->session) {
            return;
        }

        return array(
            'applied_coupons'         => WC()->session->get('applied_coupons'),
            'chosen_shipping_methods' => WC()->session->get('chosen_shipping_methods'),
            'shipping_method_counts'  => WC()->session->get('shipping_method_counts'),
            'chosen_payment_method'   => WC()->session->get('chosen_payment_method'),
        );
    }

    /**
     * Link a customer's existing cart when logging in.
     */
    public function link_customer_existing_cart($user_login, $user)
    {
        $session_token = (WC()->session) ? WC()->session->get('metorik_cart_token') : '';

        // if session token and user, set in user meta
        if ($session_token && $user) {
            update_user_meta($user->ID, '_metorik_cart_token', $session_token);
        }
    }

    /**
     * This is called once the checkout has been processed and an order has been created.
     */
    public function checkout_order_processed($order_id)
    {
        // no metorik auth token? Stop
        $metorik_auth_token = get_option('metorik_auth_token');
        if (!$metorik_auth_token) {
            return;
        }

        $cart_token = $this->get_cart_token();

        // save cart token to order meta
        if ($cart_token) {
            update_post_meta($order_id, '_metorik_cart_token', $cart_token);
        }

        // check if pending recovery - if so, set in order meta
        if ($this->cart_is_pending_recovery()) {
            $this->mark_order_as_recovered($order_id);
        }
    }

    /**
     * Mark an order as recovered by Metorik.
     */
    public function mark_order_as_recovered($order_id)
    {
        $order = wc_get_order($order_id);

        if (!$order instanceof WC_Order) {
            return;
        }

        update_post_meta($order_id, '_metorik_cart_recovered', true);

        $order->add_order_note(__('Order cart recovered by Metorik.', 'metorik'));
    }

    /**
     * Maybe apply the recovery coupon provided in the recovery URL.
     */
    public function maybe_apply_cart_recovery_coupon()
    {
        if ($this->cart_is_pending_recovery() && !empty($_REQUEST['coupon'])) {
            $coupon_code = wc_clean(rawurldecode($_REQUEST['coupon']));

            if (WC()->cart && !WC()->cart->has_discount($coupon_code)) {
                WC()->cart->calculate_totals();
                WC()->cart->add_discount($coupon_code);
            }
        }
    }

    /**
     * Rest API route for recovering a cart.
     *
     * @return void
     */
    public function api_recover_cart_route()
    {
        register_rest_route('metorik/v1', '/recover-cart', array(
            'methods'  => 'GET',
            'callback' => array($this, 'recover_cart_callback'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * API route callback for recovering a cart.
     */
    public function recover_cart_callback($request)
    {
        // Check token is set and has a value before continuing.
        if (isset($request['token']) && $cart_token = $request['token']) {
            // cart start
            $this->check_prerequisites();

            // base checkout url - default is the woo checkout url
            $checkout_url = wc_get_checkout_url();

            // if setting for it, use that
            if ($this->get_cart_setting('checkout_url')) {
                $checkout_url = $this->get_cart_setting('checkout_url');
            }

            // finally, allow the url to be filtered for more advanced customising
            $checkout_url = apply_filters('metorik_recover_cart_url', $checkout_url);

            // forward along any UTM or metorik params
            foreach ($request->get_params() as $key => $val) {
                if (0 === strpos($key, 'utm_') || 0 === strpos($key, 'mtk')) {
                    $checkout_url = add_query_arg($key, $val, $checkout_url);
                }
            }

            // no session? start so cart/notices work
            if (!WC()->session || (WC()->session && !WC()->session->has_session())) {
                WC()->session->set_customer_session_cookie(true);
            }

            // try restore the cart
            try {
                $this->restore_cart($cart_token);

                // check for coupon in recovery URL to apply before checkout redirect
                if (isset($request['coupon']) && $coupon = rawurldecode($request['coupon'])) {
                    $checkout_url = add_query_arg(array('coupon' => wc_clean($coupon)), $checkout_url);
                }
            } catch (Exception $e) {
                // add a notice
                wc_add_notice(__('Sorry, we were not able to restore your cart. Please try adding your items to your cart again.', 'metorik'), 'error');
            }

            // redirect checkout url
            wp_safe_redirect($checkout_url);
            exit;
        }
    }

    /**
     * Restore an actual cart.
     */
    public function restore_cart($cart_token)
    {
        // metorik auth token
        $metorik_auth_token = get_option('metorik_auth_token');
        if (!$metorik_auth_token) {
            throw new Exception('Missing Metorik authentication token');
        }

        // get cart
        $response = wp_remote_get($this->apiUrl.'/external/carts', array(
            'body' => array(
                'api_token'  => $metorik_auth_token,
                'cart_token' => $cart_token,
            ),
        ));

        // Error during response?
        if (is_wp_error($response)) {
            throw new Exception('Error getting cart from Metorik');
        }

        $body = wp_remote_retrieve_body($response);

        // no response body/cart?
        if (!$body) {
            throw new Exception('Error getting cart from Metorik');
        }

        // json decode
        $body = json_decode($body);

        // no data/cart? stop
        if (!isset($body->data->cart)) {
            throw new Exception('Error getting cart from Metorik');
        }

        // get cart
        $cart = $body->data->cart;

        // need to cast all to an array for putting back into the session
        $cart = json_decode(json_encode($cart), true);

        // Clear any existing cart
        WC()->cart->empty_cart();

        // Restore cart
        WC()->session->set('cart', $cart);

        // Set the cart token and pending recovery in session
        WC()->session->set('metorik_cart_token', $cart_token);
        WC()->session->set('metorik_pending_recovery', true);

        // Set the cart token / pending recovery in user meta if this cart has a user
        $user_id = $body->data->customer_id;
        if ($user_id) {
            update_user_meta($user_id, '_metorik_cart_token', $cart_token);
            update_user_meta($user_id, '_metorik_pending_recovery', true);
        }

        // Client session
        $session = $body->data->client_session;
        if ($session) {
            $applied_coupons = (array) $session->applied_coupons;
            $chosen_shipping_methods = (array) $session->chosen_shipping_methods;
            $shipping_method_counts = (array) $session->shipping_method_counts;
            $chosen_payment_method = $session->chosen_payment_method;

            WC()->session->set('applied_coupons', $this->return_valid_coupons($applied_coupons));
            WC()->session->set('chosen_shipping_methods', $chosen_shipping_methods);
            WC()->session->set('shipping_method_counts', $shipping_method_counts);
            WC()->session->set('chosen_payment_method', $chosen_payment_method);
        }
    }

    /**
     * Returns valid coupons for applying above.
     */
    private function return_valid_coupons($coupons)
    {
        $valid_coupons = array();

        if ($coupons) {
            foreach ($coupons as $coupon_code) {
                $the_coupon = new WC_Coupon($coupon_code);

                if (!$the_coupon->is_valid()) {
                    continue;
                }

                $valid_coupons[] = $coupon_code;
            }
        }

        return $valid_coupons;
    }

    /**
     * Get a cart setting (stored in metorik.php over API).
     */
    public function get_cart_setting($key = false)
    {
        $settings = get_option('metorik_cart_settings');

        // no key defined or settings saved? false
        if (!$key || !$settings) {
            return false;
        }

        // json decode
        $settings = json_decode($settings);

        // not set? false
        if (!isset($settings->$key)) {
            return false;
        }

        return $settings->$key;
    }

    /**
     * Add the email usage notice to checkout.
     * Method required due to WC < 3.4 html handling.
     */
    public function checkout_add_email_usage_notice($field, $key)
    {
        // metorik auth token? if none, stop
        $metorik_auth_token = get_option('metorik_auth_token');
        if (!$metorik_auth_token) {
            return $field;
        }

        // only if 3.4+, setting enabled, customer hasn't already opted out and billing email field exists
        if (
            $key === 'billing_email' &&
            $this->get_cart_setting('email_usage_notice') &&
            !$this->get_customer_email_opt_out()
        ) {
            // find the trailing </p> tag to replace with our notice + </p>
            $pos = strrpos($field, '</p>');
            $replace = $this->render_email_usage_notice().'</p>';

            if (false !== $pos) {
                $field = substr_replace($field, $replace, $pos, strlen('</p>'));
            }
        }

        return $field;
    }

    /**
     * Move the email field to the top of the checkout billing form (3.0+ only).
     */
    public function move_checkout_email_field($fields)
    {
        // metorik auth token? if none, stop and return fields as-is
        $metorik_auth_token = get_option('metorik_auth_token');
        if (!$metorik_auth_token) {
            return $fields;
        }

        // Oonly if setting is enabled and WC 3.0+)
        if (
            $this->get_cart_setting('move_email_field_top_checkout') &&
            version_compare(WC()->version, '3.0.0', '>=') &&
            isset($fields['billing']['billing_email']['priority'])
        ) {
            $fields['billing']['billing_email']['priority'] = 5;
            $fields['billing']['billing_email']['class'] = array('form-row-wide');
            $fields['billing']['billing_email']['autofocus'] = true;

            // adjust layout of postcode/phone fields
            if (isset($fields['billing']['billing_postcode'], $fields['billing']['billing_phone'])) {
                $fields['billing']['billing_postcode']['class'] = array('form-row-first', 'address-field');
                $fields['billing']['billing_phone']['class'] = array('form-row-last');
            }

            // remove autofocus from billing first name (set to email above)
            if (isset($fields['billing']['billing_first_name']) && !empty($fields['billing']['billing_first_name']['autofocus'])) {
                $fields['billing']['billing_first_name']['autofocus'] = false;
            }
        }

        return $fields;
    }

    /**
     * Render an email usage notice.
     */
    public function render_email_usage_notice()
    {
        /* translators: Placeholders: %1$s - opening HTML <a> link tag, %2$s - closing HTML </a> link tag */
        $notice = sprintf(
            __('We save your email and cart so we can send you reminders - %1$sdon\'t email me%2$s.', 'metorik'),
            '<a href="#" class="metorik-email-usage-notice-link">',
            '</a>'
        );

        /**
         * Filters the email usage notice contents.
         */
        $notice = (string) apply_filters('metorik_cart_email_usage_notice', $notice);

        return '<span class="metorik-email-usage-notice" style="display:inline-block;padding-top:10px;">'.$notice.'</span>';
    }

    /**
     * Add cart email form (end of DOM in WP footer). JS loads it.
     */
    public function add_cart_email_form()
    {
        // metorik auth token? if none, stop
        $metorik_auth_token = get_option('metorik_auth_token');
        if (!$metorik_auth_token) {
            return;
        }

        // Only if setting enabled, user not logged in, and never seen before
        if ($this->get_cart_setting('add_cart_popup')
            && !get_current_user_id()
            && !$this->seen_add_cart_form()) {
            // Title
            $title = $this->get_cart_setting('add_cart_popup_title');
            if (!$title) {
                $title = 'Save your cart?';
            }

            // Email usage notice
            $email_usage_notice = false;
            if ($this->get_cart_setting('email_usage_notice') && !$this->get_customer_email_opt_out()) {
                $email_usage_notice = $this->render_email_usage_notice();
            }

            // Variables
            $args = array(
                'title'              => $title,
                'email_usage_notice' => $email_usage_notice,
            );

            // Output template wrapped in 'add-cart-email-wrapper' div (used by JS)
            echo '<div class="add-cart-email-wrapper" style="display: none;">';
            $this->get_template('add-cart-email-form.php', $args);
            echo '</div>';
        }
    }

    /**
     * Get template.
     *
     * Search for the template and include the file.
     *
     * @author https://jeroensormani.com/how-to-add-template-files-in-your-plugin/
     *
     * @see $this->locate_template()
     *
     * @param string $template_name Template to load.
     * @param array  $args          Args passed for the template file.
     * @param string $string        $template_path	Path to templates.
     * @param string $default_path  Default path to template files.
     */
    public function get_template($template_name, $args = array(), $tempate_path = '', $default_path = '')
    {
        if (is_array($args) && isset($args)) {
            extract($args);
        }

        $template_file = $this->locate_template($template_name, $tempate_path, $default_path);

        if (!file_exists($template_file)) {
            _doing_it_wrong(__FUNCTION__, sprintf('<code>%s</code> does not exist.', $template_file), '1.0.0');

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
     * @param string $string        $template_path	Path to templates.
     * @param string $default_path  Default path to template files.
     *
     * @return string Path to the template file.
     */
    public function locate_template($template_name, $template_path = '', $default_path = '')
    {
        // Set variable to search in metorik folder of theme.
        if (!$template_path) {
            $template_path = 'metorik/';
        }

        // Set default plugin templates path.
        if (!$default_path) {
            $default_path = plugin_dir_path(dirname(__FILE__)).'templates/'; // Path to the template folder
        }

        // Search template file in theme folder.
        $template = locate_template(array(
            $template_path.$template_name,
            $template_name,
        ));

        // Get plugins template file.
        if (!$template) {
            $template = $default_path.$template_name;
        }

        return apply_filters('metorik_locate_template', $template, $template_name, $template_path, $default_path);
    }

    /**
     * Add a coupon code to the cart session.
     *
     * @return void
     */
    public function add_coupon_code_to_cart_session()
    {
        // Stop if no code in URL
        if (empty($_GET['mtkc'])) {
            return;
        }

        // cart start
        $this->check_prerequisites();

        // no session? start so cart/notices work
        if (!WC()->session || (WC()->session && !WC()->session->has_session())) {
            WC()->session->set_customer_session_cookie(true);
        }

        // Set code in session
        $coupon_code = esc_attr($_GET['mtkc']);
        WC()->session->set('mtk_coupon', $coupon_code);

        // If there is an existing non empty cart active session we apply the coupon
        if (WC()->cart && !WC()->cart->is_empty() && !WC()->cart->has_discount($coupon_code)) {
            WC()->cart->calculate_totals();
            WC()->cart->add_discount($coupon_code);

            // Unset the coupon from the session
            WC()->session->__unset('mtk_coupon');
        }
    }

    /**
     * Add the Metorik session coupon code to the cart when adding a product.
     */
    public function add_coupon_code_to_cart()
    {
        $coupon_code = WC()->session ? WC()->session->get('mtk_coupon') : false;

        // no coupon code? stop
        if (!$coupon_code || empty($coupon_code)) {
            return;
        }

        // only if have a cart but not this discount yet
        if (WC()->cart && !WC()->cart->has_discount($coupon_code)) {
            WC()->cart->calculate_totals();
            WC()->cart->add_discount($coupon_code);

            // Unset the coupon from the session
            WC()->session->__unset('mtk_coupon');
        }
    }
}

new Metorik_Helper_Carts();
