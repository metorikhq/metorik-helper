(function($) {

	class MetorikSourceTracking {

		sourceTrackingParams = {
			lifetime: metorik_params.source_tracking.cookie_lifetime,
			session_length: metorik_params.source_tracking.session_length,
			timezone_offset: '0', // utc
		};

		init() {

			// bail if source tracking is disabled
			if (!metorik_params.source_tracking.enabled) {
				return;
			}

			// bail if cookie already set
			if (this.cookieExists()) {
				return;
			}

			// set domain if overwritten via metorik_sbjs_domain filter
			if (metorik_params.source_tracking.sbjs_domain) {
				this.sourceTrackingParams.domain = metorik_params.source_tracking.sbjs_domain;
			}

			// Initialize sourcebuster.js
			sbjs.init(this.sourceTrackingParams);

			// set the cookie
			this.setSourceTrackingCookie();
		}

		cookieExists() {
			return document.cookie
				.split('; ')
				.find((row) => row.startsWith(metorik_params.source_tracking.cookie_name));
		}

		cookieContent() {
			const cookieContent = {
				type: sbjs.get.current.typ,
				url: sbjs.get.current_add.rf,
				mtke: sbjs.get.current.mtke,

				utm_campaign: sbjs.get.current.cmp,
				utm_source: sbjs.get.current.src,
				utm_medium: sbjs.get.current.mdm,
				utm_content: sbjs.get.current.cnt,
				utm_id: sbjs.get.current.id,
				utm_term: sbjs.get.current.trm,

				session_entry: sbjs.get.current_add.ep,
				session_start_time: sbjs.get.current_add.fd,
				session_pages: sbjs.get.session.pgs,
				session_count: sbjs.get.udata.vst,
			};

			return encodeURIComponent(JSON.stringify(cookieContent));
		}

		cookieExpiration() {
			const date = new Date();
			// cookie_lifetime is in months
			// milliseconds x seconds x minutes x hours x day x month
			date.setTime(date.getTime() + (metorik_params.source_tracking.cookie_lifetime * 1000 * 60 * 60 * 24 * 30));
			return date.toUTCString();
		}

		setSourceTrackingCookie() {
			if (!sbjs.get) {
				return;
			}

			document.cookie = metorik_params.source_tracking.cookie_name + '=' +
				this.cookieContent() + '; expires=' + this.cookieExpiration() + '; Secure; path=/;';
		}
	}

	class MetorikCartTracking {

		timers = {
			customer_data: null,
			email_field: null,
			checkout_field: null,
			add_cart: null,
		};

		addToCartEmailWrapper = $('.add-cart-email-wrapper');
		addToCartSeen = false;

		// by default, we only show the add to cart form once, and then mark it as seen
		// this can be disabled via the metorik_acp_should_mark_as_seen filter
		addToCartShouldMarkAsSeen = metorik_params.cart_tracking.add_to_cart_should_mark_as_seen;

		// classes/buttons that we're targeting for the popup
		// see metorik-helper.php where add_to_cart_button_classes is set and filterable
		selectors = metorik_params.cart_tracking.add_to_cart_form_selectors;

		init() {
			// bail if cart tracking is disabled
			if (!metorik_params.cart_tracking.enabled) {
				return;
			}

			this.initAddToCartPopup();

			this.initOptOutListener();

			this.captureDataListeners();
		}

		initAddToCartPopup() {
			// bail if not rendered (means it's disabled)
			if (!this.addToCartEmailWrapper.length) {
				return;
			}

			// add tippy for each class
			this.selectors.forEach(this.initiateTippyForElement.bind(this));

			// listen for cart reloads after products added to the cart
			$(document.body).on('wc_fragments_refreshed', this.showTippyOnAddToCart.bind(this));

			// Listen for closing the add cart email form/tippy
			$(document).on('click', '.metorik-add-cart-email-form .close-button', this.closeTippyAndMarkAsSeen.bind(this));

			// Listen for add cart email input changes
			$(document).on('input', '.metorik-add-cart-email-form .email-input', this.captureEmailFromAddCart.bind(this));

			// if an item was just added to the cart (from a product page), show the popup
			if (metorik_params.cart_tracking.item_was_added_to_cart) {
				this.showTippyOnAddToCart();
			}

		}

		initOptOutListener() {
			// Listen for email usage opt-out clicks
			$(document).on('click', '.metorik-email-usage-notice-link', this.optOutAndFadeNotice.bind(this));

			$(document).on('change', '#contact-metorik\\/opt-in, #contact-metorik-opt-in', this.toggleOptInOptOut.bind(this));
		}

		initiateTippyForElement(selector) {
			tippy(selector, {
				content: this.addToCartEmailWrapper.html(),
				allowHTML: true,
				theme: 'light',
				trigger: (selector == '.ajax_add_to_cart') ? 'click' : 'manual',
				hideOnClick: true,
				interactive: true,
				arrow: true,
				offset: [0, 15],
				placement: metorik_params.cart_tracking.add_cart_popup_placement,
				onShow: () => {
					// hide the tippy if the form has been seen already
					if (this.addToCartSeen) {
						return false;
					}
				},
				onShown: (instance) => {
					// scroll to the popover
					if (metorik_params.cart_tracking.add_cart_popup_should_scroll_to) {
						setTimeout(() => instance.popper.scrollIntoView({ behavior: 'smooth', block: 'center' }), 500);
					}

					if (this.addToCartShouldMarkAsSeen) {
						this.markAddToCartAsSeen();
					}
				},
			});
		}

		showTippyOnAddToCart(e) {
			// bail if there's no cart items
			if (metorik_params.cart_tracking.cart_items_count < 1) {
				return;
			}

			// show tippy on add cart button
			var singleButton = $('.single_add_to_cart_button, .wc-block-components-product-button__button.add_to_cart_button');
			if (singleButton.length) {
				singleButton[0]._tippy.show();
			}
		}

		closeTippyAndMarkAsSeen(e) {
			this.closeTippy(e);
			this.markAddToCartAsSeen();
		}

		closeTippy(e) {
			e.preventDefault();

			// close/hide tippy if active
			const tippyRoot = e.target.closest('[data-tippy-root]');
			if (tippyRoot && tippyRoot._tippy) {
				tippyRoot._tippy.hide();
			}
		}

		markAddToCartAsSeen() {
			// bail if already marked as seen
			if (this.addToCartSeen) {
				return;
			}

			// Set the add to cart form as having been seen, so it doesn't get shown again.
			this.addToCartSeen = true;
			$.post(metorik_params.cart_tracking.wc_ajax_seen_add_to_cart_form_url, { security: metorik_params.nonce });
		}

		captureDataListeners() {
			const emailFields = document.querySelectorAll('.metorik-capture-email, #billing_email');
			emailFields.forEach((field) => {
				if (field) {
					field.addEventListener('input', this.captureEmail.bind(this));
				}
			});

			const checkoutFields = document.querySelectorAll('#billing_first_name, #billing_phone');
			checkoutFields.forEach((field) => {
				if (field) {
					field.addEventListener('input', this.captureCheckoutField.bind(this));
				}
			});
		}

		captureEmailFromAddCart(e) {
			const emailField = $(e.target);
			const emailWrapper = emailField.parent();
			const email = emailField.val();

			// clear classes on input change
			emailWrapper.removeClass('success');

			clearTimeout(this.timers.add_cart);
			this.timers.add_cart = setTimeout(() => {
				if (this.isValidEmail(email)) {
					emailWrapper.addClass('success');
					this.captureCustomerData(email);

					// close the tippy & mark as seen after a short delay
					setTimeout(() => {
						this.closeTippyAndMarkAsSeen(e);
					}, 1500);
				}
			}, 500);
		}

		captureEmail(e) {
			const email = e.target.value;
			clearTimeout(this.timers.email_field);
			this.timers.email_field = setTimeout(() => {
				if (this.isValidEmail(email)) {
					this.captureCustomerData(email);
				}
			}, 500);
		}

		captureCheckoutField(e) {
			clearTimeout(this.timers.checkout_field);
			this.timers.checkout_field = setTimeout(() => {
				this.captureCustomerData();
			}, 500);
		}

		captureCustomerData(customEmail) {
			clearTimeout(this.timers.customer_data);
			this.timers.customer_data = setTimeout(() => {
				let email = null;
				if (this.isValidEmail(customEmail)) {
					email = customEmail;
				} else {
					const billingEmail = $('#billing_email').val();
					if (this.isValidEmail(billingEmail)) {
						email = billingEmail;
					}
				}

				const firstName = $('#billing_first_name').val();
				const lastName = $('#billing_last_name').val();
				const phone = $('#billing_phone').val();

				const data = {
					email: email,
					first_name: firstName,
					last_name: lastName,
					phone: phone,
					security: metorik_params.nonce,
				};

				$.post(metorik_params.cart_tracking.wc_ajax_capture_customer_data_url, data);
			}, 1000);
		}

		optOutAndFadeNotice(e) {
			e.preventDefault();
			const emailUsageNotice = $('.metorik-email-usage-notice');

			// loading
			emailUsageNotice.css({ opacity: '0.5', 'pointer-events': 'none' });

			this.optOut(() => {
				emailUsageNotice.hide();
				this.closeTippy(e);
			});
		}

		toggleOptInOptOut(e) {
			e.preventDefault();
			const optIn = e.target.checked;

			if (optIn) {
				this.optIn();
			} else {
				this.optOut();
			}
		}

		optOut(callback = null) {
			$.post(
				metorik_params.cart_tracking.wc_ajax_email_opt_out_url,
				{ security: metorik_params.nonce },
				callback,
			);
		}

		optIn(callback = null) {
			$.post(
				metorik_params.cart_tracking.wc_ajax_email_opt_in_url,
				{ security: metorik_params.nonce },
				callback,
			);
		}

		isValidEmail(email) {
			return /[^\s@]+@[^\s@]+\.[^\s@]+/.test(email);
		}
	}

	let metorikSourceTracking;
	let metorikCartTracking;

	function initMetorik() {
		if (typeof metorik_params === 'undefined') {
			return;
		}

		if (!metorikSourceTracking) {
			metorikSourceTracking = new MetorikSourceTracking();
			metorikSourceTracking.init();
		}

		if (!metorikCartTracking) {
			metorikCartTracking = new MetorikCartTracking();
			metorikCartTracking.init();
		}
	}

	initMetorik();

	// adds compatibility for Complianz consent plugin
	$(document).on('cmplz_run_after_all_scripts', initMetorik);
})(jQuery);
