(function($) {
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
        if (sbjs.get) {
            $('input[name="metorik_source_type"]').val(sbjs.get.current.typ);
            $('input[name="metorik_source_url"]').val(sbjs.get.current_add.rf);
            $('input[name="metorik_source_mtke"]').val(sbjs.get.current.mtke);

            $('input[name="metorik_source_utm_campaign"]').val(sbjs.get.current.cmp);
            $('input[name="metorik_source_utm_source"]').val(sbjs.get.current.src);
            $('input[name="metorik_source_utm_medium"]').val(sbjs.get.current.mdm);
            $('input[name="metorik_source_utm_content"]').val(sbjs.get.current.cnt);
            $('input[name="metorik_source_utm_id"]').val(sbjs.get.current.id);
            $('input[name="metorik_source_utm_term"]').val(sbjs.get.current.trm);

            $('input[name="metorik_source_session_entry"]').val(sbjs.get.current_add.ep);
            $('input[name="metorik_source_session_start_time"]').val(sbjs.get.current_add.fd);
            $('input[name="metorik_source_session_pages"]').val(sbjs.get.session.pgs);
            $('input[name="metorik_source_session_count"]').val(sbjs.get.udata.vst);
        }
    };

    /**
     * Add source values to checkout.
     */
    $(document.body).on('init_checkout', function(event) {
        setFields();
    });

    /**
     * Add source values to register.
     */
    if ($('.woocommerce form.register').length) {
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
                var email = isValidEmail($('#billing_email').val()) ? $('#billing_email').val() : null;
                if (customEmail) {
                    email = customEmail;
                }

                var name = $('#billing_first_name').val();

                var data = {
                    action: 'metorik_send_cart',
                    email: email,
                    name: name,
                };

                $.post(metorik_params.ajaxurl, data, function (response) {
                    //
                });
            }, 500);
        };

        /**
         * Function to check if an email is valid.
         * @param {*} email
         */
        var isValidEmail = function(email) {
            return /[^\s@]+@[^\s@]+\.[^\s@]+/.test(email);
        };

        /**
         * Listen for cart change events then send cart data.
         */
        $(document.body).on(
            'added_to_cart removed_from_cart updated_cart_totals updated_shipping_method applied_coupon removed_coupon updated_checkout',
            function (event) {
                sendCartData();
            }
        );

        /**
         * Watch for fragments separate and determine if have data to send.
         * As this event may trigger with no items in cart (initial page
         * load) but no need to send cart then. So we check for items.
         */
        
        $(document.body).on(
            'wc_fragments_refreshed',
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
        $('#billing_email, .metorik-capture-guest-email').bind('blur', function(e) {
            var _this = $(this);

            clearTimeout(email_input_timer);
            email_input_timer = setTimeout(function() {
                if (isValidEmail(_this.val())) {
                    sendCartData(_this.val());
                }
            }, 500);
        });

        /**
         * Watch for name input changes.
         */
        var name_input_timer;
        $('#billing_first_name').bind('blur', function (e) {
            var _this = $(this);

            clearTimeout(name_input_timer);
            name_input_timer = setTimeout(function () {
                sendCartData();
            }, 500);
        });

        /**
         * Popup to capture email when added to cart (if wrapper class exists/output on page).
         */
        var addToCartSeen = false;

        if ($('.add-cart-email-wrapper').length) {
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
            $(document.body).on(
                'wc_fragments_refreshed',
                function (e) {
                    // only if cart items 1 or more
                    if (metorik_params.cart_items >= 1) {
                        // show tippy on add cart button
                        var singleButton = $('.single_add_to_cart_button');
                        if (singleButton.length) {
                            singleButton[0]._tippy.show();
                        }

                        // show tippy on cart update button (if cart checkout button enabled)
                        if (metorik_params.cart_checkout_button) {
                            var cartButton = $('.button.checkout-button');
                            if (cartButton.length) {
                                cartButton[0]._tippy.show();
                            }
                        }
                    }
                }
            );

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
                        // Set the add to cart fomr as having been senen so it doesn't get shown again.
                        addToCartSeen = true;

                        // Make an AJAX request to set the add cart form as 'seen'.
                        var data = {
                            action: 'metorik_add_cart_form_seen',
                        };

                        $.post(metorik_params.ajaxurl, data, function (response) {
                            //
                        });
                    },
                });
            });
        }

        /**
         * Listen for closing the add cart email form tippy.
         */
        $(document).on('click', '.metorik-add-cart-email-form .close-button', function (e) {
            e.preventDefault();

            // close/hide tippy if have
            var button = $('.tippy-active');
            if (button.length && button[0]._tippy) {
                button[0]._tippy.hide();
            }
        });

        /**
         * Listen for add cart email input changes.
         */
        var addCartTimer;
        $(document).on('input', '.metorik-add-cart-email-form .email-input', function(e) {
            var _this = $(this);
            var _wrapper = _this.parent();

            // clear classes on input change
            _wrapper.removeClass('success');

            clearTimeout(addCartTimer);
            addCartTimer = setTimeout(function() {
                if (isValidEmail(_this.val())) {
                    _wrapper.addClass('success');
                    sendCartData(_this.val());
                }
            }, 500);
        });

        /**
         * Listen for email usage opt-out clicks and send AJAX request to do so.
         */
        $(document).on('click', '.metorik-email-usage-notice-link', function(e) {
            e.preventDefault();

            // loading
            $('.metorik-email-usage-notice').css({ opacity: '0.5', 'pointer-events': 'none' });

            var data = {
                action: 'metorik_email_opt_out',
            };

            $.post(metorik_params.ajaxurl, data, function(response) {
                // hide email usage notice
                $('.metorik-email-usage-notice').css('display', 'none');

                // close/hide tippy if have
                var button = $('.tippy-active');
                if (button.length && button[0]._tippy) {
                    button[0]._tippy.hide();
                }

                // send cart so we can send email opted out
                sendCartData();
            });
        });
    }
})(jQuery);
