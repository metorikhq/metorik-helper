document.addEventListener("DOMContentLoaded", function(event) {
    /**
     * Initialize sourcebuster.js.
     */
    sbjs.init({
        lifetime: metorik_params.life,
        session_length: metorik_params.session,
        timezone_offset: '0', // utc
    });

    /**
     * Set values.
     */
    var setFields = function() {
        // only continue with sbjs data and at least the first input field being present
        if (sbjs.get && document.querySelector('input[name="metorik_source_type"]')) {
            document.querySelector('input[name="metorik_source_type"]').value = sbjs.get.current.typ;
            document.querySelector('input[name="metorik_source_url"]').value = sbjs.get.current_add.rf;
            document.querySelector('input[name="metorik_source_mtke"]').value = sbjs.get.current.mtke;

            document.querySelector('input[name="metorik_source_utm_campaign"]').value = sbjs.get.current.cmp;
            document.querySelector('input[name="metorik_source_utm_source"]').value = sbjs.get.current.src;
            document.querySelector('input[name="metorik_source_utm_medium"]').value = sbjs.get.current.mdm;
            document.querySelector('input[name="metorik_source_utm_content"]').value = sbjs.get.current.cnt;
            document.querySelector('input[name="metorik_source_utm_id"]').value = sbjs.get.current.id;
            document.querySelector('input[name="metorik_source_utm_term"]').value = sbjs.get.current.trm;

            document.querySelector('input[name="metorik_source_session_entry"]').value = sbjs.get.current_add.ep;
            document.querySelector('input[name="metorik_source_session_start_time"]').value = sbjs.get.current_add.fd;
            document.querySelector('input[name="metorik_source_session_pages"]').value = sbjs.get.session.pgs;
            document.querySelector('input[name="metorik_source_session_count"]').value = sbjs.get.udata.vst;
        }
    };

    /**
     * Add source values to checkout.
     */
    document.body.addEventListener('init_checkout', function(event) {
        setFields();
    });

    /**
     * Add source values to register.
     */
    if (document.querySelector('.woocommerce form.register')) {
        setFields();
    }

    /**
     * Cart functionality - only if cart tracking enabled.
     */
    if (metorik_params.cart_tracking) {
        /**
         * Send cart data.
         * @todo Only if cart token set up.
         */
        var cartTimer;
        var sendCartData = function(customEmail) {
            clearTimeout(cartTimer);
            cartTimer = setTimeout(function () {
                // get email
                var email = document.querySelector('#billing_email') ? document.querySelector('#billing_email').value : null;
                email = isValidEmail(email) ? email : null;

                if (customEmail) {
                    email = customEmail;
                }

                var name = document.querySelector('#billing_first_name') ? document.querySelector('#billing_first_name').value : null;
                var phone = document.querySelector('#billing_phone') ? document.querySelector('#billing_phone').value : null;

                var form_data = new FormData;
                form_data.append('action', 'metorik_send_cart');
                form_data.append('email', email);
                form_data.append('name', name);
                form_data.append('phone', phone);

                axios.post(metorik_params.ajaxurl, form_data, function (response) {
                    //
                });
            }, 1000);
        };

        /**
         * Function to check if an email is valid.
         * @param {*} email
         */
        var isValidEmail = function(email) {
            return /[^\s@]+@[^\s@]+\.[^\s@]+/.test(email);
        };


        /**
         * Listen for closing the add cart email form tippy.
         */
        var setupCartEmailCloseWatcher = function() {
            if (document.querySelector('.metorik-add-cart-email-form .close-button')) {
                document.querySelector('.metorik-add-cart-email-form .close-button')
                    .addEventListener('click', function(e) {
                        e.preventDefault();

                        // close/hide tippy if have
                        var button = document.querySelector('.tippy-active');
                        if (button && button._tippy) {
                            button._tippy.hide();
                        }
                    });
                }
        }

        /**
         * Listen for add cart email input changes.
         */
        var setupCartEmailInputWatcher = function() {
            if (document.querySelector('.metorik-add-cart-email-form .email-input')) {
                var addCartTimer;
                //if (typeof(document.querySelector('.metorik-add-cart-email-form .email-input').input) == "undefined") {
                    document.querySelector('.metorik-add-cart-email-form .email-input')
                        .addEventListener('input', function(e) {
                            var _this = e.target;
                            var _wrapper = _this.parentElement;

                            // clear classes on input change
                            _wrapper.classList.remove('success');

                            clearTimeout(addCartTimer);
                            addCartTimer = setTimeout(function() {
                                if (isValidEmail(_this.value)) {
                                    _wrapper.classList.add('success');
                                    sendCartData(_this.value);
                                }
                            }, 500);
                        });
                //}
            }
        }

        /**
         * Listen for email usage opt-out clicks and send AJAX request to do so.
         */
        var setupEmailUsageNoticeWatcher = function() {
            if (document.querySelector('.metorik-email-usage-notice-link')) {
                document.querySelector('.metorik-email-usage-notice-link')
                    .addEventListener('click', function(e) {
                        e.preventDefault();

                        // loading
                        document.querySelector('.metorik-email-usage-notice').style.opacity = '0.5';
                        document.querySelector('.metorik-email-usage-notice').style['pointer-events'] = 'none';

                        var form_data = new FormData;
                        form_data.append('action', 'metorik_email_opt_out');
                        
                        axios.post(metorik_params.ajaxurl, form_data, function(response) {
                            // hide email usage notice
                            document.querySelector('.metorik-email-usage-notice').style.display = 'none';

                            // close/hide tippy if have
                            var button = document.querySelector('.tippy-active');
                            if (button && button._tippy) {
                                button._tippy.hide();
                            }

                            // send cart so we can send email opted out
                            sendCartData();
                        });
                    });
            }
        }

        /**
         * Listen for cart change events then send cart data.
         */
        metorik_params.send_cart_events.split(' ').forEach(function(event) {
            document.body.addEventListener(event, sendCartData);
        })

        /**
         * Watch for fragments separate and determine if have data to send.
         * As this event may trigger with no items in cart (initial page
         * load) but no need to send cart then. So we check for items.
         */
        document.body.addEventListener('wc_fragments_refreshed',
            function(event) {
                // Only continue if wc_cart_fragments_params defined
                if (wc_cart_fragments_params) {
                    // Get cart hash key from wc_cart_fragments_params variable
                    var cart_hash_key = wc_cart_fragments_params.cart_hash_key;

                    try {
                        // Get local storage and session storage for cart dash
                        var localStorageItem = localStorage.getItem(cart_hash_key)
                        var sessionStorageItem = sessionStorage.getItem(cart_hash_key)

                        // Check if have local storage or session storage
                        if (localStorageItem || sessionStorageItem) {
                            // Have items so we'll send the cart data now
                            sendCartData();
                        }
                    } catch (e) {

                    }
                }
            }
        );

        /**
         * Watch for email input changes.
         */
        var email_input_timer;
        var handleEmailInputChanges = function(e) {
            var _this = e.target;

            clearTimeout(email_input_timer);
            email_input_timer = setTimeout(function() {
                if (isValidEmail(_this.value)) {
                    sendCartData(_this.value);
                }
            }, 500);
        }
        if (document.querySelector('#billing_email')) {
            document.querySelector('#billing_email').addEventListener('blur', handleEmailInputChanges);
        }
        if (document.querySelector('.metorik-capture-guest-email')) {
            document.querySelector('.metorik-capture-guest-email').addEventListener('blur', handleEmailInputChanges);
        }

        /**
         * Watch for name input changes.
         */
        var name_input_timer;
        var handleNameInputChanges = function(e) {
            var _this = e.target;

            clearTimeout(name_input_timer);
            name_input_timer = setTimeout(function () {
                sendCartData();
            }, 500);
        };
        if (document.querySelector('#billing_first_name')) {
            document.querySelector('#billing_first_name').addEventListener('blur', handleNameInputChanges);
        }
        if (document.querySelector('#billing_phone')) {
            document.querySelector('#billing_phone').addEventListener('blur', handleNameInputChanges);
        }

        /**
         * Popup to capture email when added to cart (if wrapper class exists/output on page).
         */
        var addToCartSeen = false;
        var addCartEmailWrapper = document.querySelector('.add-cart-email-wrapper');
        if (addCartEmailWrapper) {
            // classes/buttons that we're targeting
            var classes = [
                '.button.ajax_add_to_cart',
                '.single_add_to_cart_button',
            ];

            // add cart checkout button if enabled (filterable)
            if (metorik_params.cart_checkout_button) {
                classes.push('.button.checkout-button');
            }

            // listen for page reloads after products added to the cart
            document.body.addEventListener('wc_fragments_refreshed', function (e) {
                console.log('in here');
                // only if cart items 1 or more
                if (metorik_params.cart_items >= 1) {
                    // show tippy on add cart button
                    var singleButton = document.querySelector('.single_add_to_cart_button');
                    if (singleButton) {
                        singleButton._tippy.show();
                    }

                    // show tippy on cart update button (if cart checkout button enabled)
                    if (metorik_params.cart_checkout_button) {
                        var cartButton = document.querySelector('.button.checkout-button');
                        if (cartButton) {
                            cartButton._tippy.show();
                        }
                    }
                }
            });

            // add tippy for each class
            classes.forEach(function(c) {
                tippy(c, {
                    html: '.add-cart-email-wrapper',
                    theme: 'light',
                    trigger: (c == '.button.ajax_add_to_cart') ? 'click' : 'manual',
                    hideOnClick: true,
                    interactive: true,
                    arrow: true,
                    distance: 15,
                    placement: metorik_params.add_cart_popup_placement,
                    wait: function(show) {
                        // Only show if add to cart seen not true. Delay 100ms
                        if(!addToCartSeen) {
                            setTimeout(function() {
                                show();
                            }, 250);
                        }
                    },
                    onShow: function () {
                        setupCartEmailInputWatcher();

                        // Set the add to cart fomr as having been senen so it doesn't get shown again.
                        addToCartSeen = true;

                        // Make an AJAX request to set the add cart form as 'seen'.
                        var form_data = new FormData;
                        form_data.append('action', 'metorik_add_cart_form_seen');

                        axios.post(metorik_params.ajaxurl, form_data, function (response) {
                            //
                        });
                    },
                });
            });
        }
    }
});