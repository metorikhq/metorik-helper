=== Metorik - Reports & Email Automation for WooCommerce ===
Contributors: bryceadams, metorik
Tags: woocommerce, woocommerce reports, woocommerce emails, woocommerce abandoned carts, woocommerce carts,  woocommerce filtering, woocommerce google analytics, woocommerce zendesk, woocommerce help scout, woocommerce freshdesk, woocommerce support, woocommerce analytics, subscription reports, woo reports, woocommerce email, woocommerce email automation, woocommerce cart emails, woocommerce export, woocommerce csv 
Requires at least: 4.4.0
Requires PHP: 5.6.20
Tested up to: 5.6.0
Stable tag: trunk
License: MIT
License URI: https://opensource.org/licenses/MIT

The Metorik Helper helps provide your WooCommerce store with powerful analytics, reports, and tools.

== Description ==

> **Note:** This plugin is only really of use to you if you have a Metorik account/store. You can set one up for free and enjoy a 30 day trial, but keep in mind that it is a paid service. [**Try Metorik for free!**](https://metorik.com?ref=wporg)

In just a few clicks, Metorik gives your store a powerful real-time dashboard, unifying your store's orders, customers, and products, helping you understand your data and make more informed decisions every day.

= Blazing fast =

Tired of spending half your day waiting for WooCommerce reports to load? Metorik spins up detailed reports and charts faster than any other tool available. It also reduces the load on your site's admin dashboard since you can do everything from Metorik, as such making your site faster.

= Every KPI you could ask for =

What's your average customer LTV (lifetime value)? What's your average numbers of items per order? How many of product A or variation B did you sell last month? If these are questions you've always wanted answers for, Metorik will be a lifesaver.

= Segment everything by anything =

Metorik offers a robust & powerful [segmenting system](https://help.metorik.com/article/54-all-about-segmenting). It allows you segment your data by anything and everything (seriously), export that segmented data to a CSV (automatically, if that's your thing). You can even save the filters you used for next time, or share them with your team.

Want all customers who have an LTV over $100 and own a certain product? **Easy.**

Orders that were made last month where 2 items were purchased and the customer was from the UK? **Easy.**

Subscriptions that were set to be canceled this week? **Easy.**

Customers who haven't ordered in 4 months and live in California? **Easy.**

Read more about the segmenting system [here](https://metorik.com/blog/improved-segmenting-filtering-in-metorik?ref=wporg).

= Send automated emails to customers =

With [Metorik Engage](https://metorik.com/engage?ref=wporg) and it's accompanying segmenting system, you can send targeted emails to customers automatically as soon as they match certain rules.

For example, email customers whenever they've spent over $500 and include a coupon code uniquely generated for them for 20% off.

Writing emails couldn't be easier with Engage's email builder and each automation comes with a comprehensive report so you can see which emails are converting and which ones are being ignored.

= Cart tracking and reports =

[Metorik tracks every cart](https://metorik.com/carts) started on your store, making it easy for you to see all open, abandoned, and placed carts. Also included is the Carts Report, covering all of your cart-related stats.

And better yet, through Metorik Engage, you can send automatic abandoned cart emails to try get customers to complete their purchase.

= Customer service integrations =

Metorik integrates with your existing support system to show customer data right alongside support tickets. Data like their contact information, lifetime value, order history, products purchased and more, instantly at you and your customer service teams' fingertips. Additionally, you'll find data from your support systems shown on order pages and customer profiles in Metorik.

Integrations are currently available for [Zendesk](https://metorik.com/blog/connecting-zendesk-and-woocommerce?ref=wporg), Help Scout, Freshdesk, and Intercom, with more to come.

= Google Analytics integration =

Connect your Google Analytics account to Metorik and get access to stats like conversion rates instantly. Better yet, you can get **historical conversion rates!** [Read more about it here](https://metorik.com/blog/conversation-rates-for-woocommerce-with-google-analytics?ref=wporg).

= Email + Slack reports =

Automatically receive reports summarising your store's activity as often as you'd like. They can be sent by both Email & Slack, and include your KPIs, charts, best sellers, and more.

= One-off and automated exports =

Any data can be exported from Metorik at any time in minutes. You can even schedule exports to happen automatically as often as you'd like.

*Bonus:* These exports have zero-impact on your site whatsoever. No more server downtime!

= WooCommerce Subscriptions support =

Metorik integrates seamlessly with [WooCommerce Subscriptions](https://metorik.com/go/subscriptions), offering subscription filtering & exporting, along with reports like MRR, Churn, Retention, Forecasting, and more. You can even have an automated subscriptions report sent to you every day summarising everything subscriptions-related.

= Live chat support =

Support is available through live chat to every Metorik user. Metorik's founder - [Bryce](https://twitter.com/bryceadams) - will personally work with you to ensure you and your team get the most out of Metorik.

= Bring your team =

Whether you're running a store solo or bringing your team, Metorik has your back through its team system. Each store can have **unlimited team members** at no extra cost, each with their own role & permissions. No more sharing sales reports with your support reps and no more analytics modifying orders by accident.

= More? =

Oh, there's so much more. Seriously. Just have a look around the [Metorik website](https://metorik.com?ref=wporg) to get an idea of how valuable Metorik will be for your store.

---

The Metorik Helper helps [Metorik](https://metorik.com?ref=wporg) connect and work better with your site. Simply install, activate and Metorik will take care of the rest!

== Installation ==
Install, activate and leave it to do the rest.

Keep in mind that you do need a Metorik account for it to work with, so if you don't yet have a store set up in Metorik, head to [Metorik](https://metorik.com?ref=wporg) and sign up now.

== Frequently Asked Questions ==
**Do I need a Metorik account to use this plugin?**

Yes, you do ([sign up here](https://metorik.com?ref=wporg)). It will still work but will really not be of much use to you without one.

**Can I hide the Metorik links in my WordPress dashboard?**

If you truly want to (but why! They're so handy), you can. Simply add:

`
add_filter( 'metorik_show_ui', '__return_false' );
`

To your theme's `functions.php` or a custom plugin.

The other option is to simply 'dimiss' a Metorik notice and they will no longer appear.

To hide the links from individual orders/products, you can click the 'Screen Options' tab at the top of the page and uncheck the Metorik option.

**I accidentally hid the notices. How can I get them back?**

We all make mistakes. To get them back, go to http://yoursite.com/wp-admin?show-metorik-notices=yes while logged in as an administrator.

== Changelog ==
= 1.4.1 =
* Fix PHP notice with WP 5.5 and REST API changes.

= 1.4.0 =
* Improve cart tracking performance.
* Added a setting (to Metorik) for customising the checkout URL for cart recoveries.
* Bug fix to stop an error from occurring if no server object exists when we filter the WP API.

= 1.3.0 =
* Improvements to sending guest carts.

= 1.2.0 =
* Additional WooCommerce 3.6 fixes for coupon applying.

= 1.1.2 =
* Further WooCommerce 3.6 fixes for cart recovery links.
* Better i18n support.

= 1.1.1 =
* Fix coupon-applying through a URL parameter for empty carts.

= 1.1.0 =
* WooCommerce 3.6 API fix.
* Apply coupons provided through a certain URL parameter (Metorik Engage).
* Send and restore more cart data, like the shipping method, coupons, payment method.

= 1.0.5 =
* Don't override customer source on checkout.
* Change some Metorik URLs to customer/order/etc. pages.
* Add author to order note API responses (for v1 and v2 of the API).

= 1.0.4 =
* Fix bug when cart object not available and localizing Metorik's JS.
* Compiled and minified JS.
* Filter for changing the cart recovery final URL (by default it takes them to the checkout page).
* Close button for the add to cart email popup.
* Class to add to inputs for additional custom email input tracking (.metorik-capture-guest-email).

= 1.0.3 =
* Fix add to cart popup bug where it sometimes showed at the bottom of the page.
* Show add to cart popup on single product pages and the cart page (after items are added to the cart).

= 1.0.2 =
* Improve cart token setting for guests that log in or register during their session.

= 1.0.1 =
* Fix tracking of the Engage automation ID during a cart recovery.

= 1.0.0 =
* Cart tracking.
* Cart email popup capturing.
* Cart restoring.

= 0.15.0 =
* Add meta data to WooCommerce Subscriptions API endpoints.

= 0.14.3 =
* Fix Safari bug.

= 0.14.2 =
* Fix helper active check and version code.

= 0.14.1 =
* Fix empty source data being stored.

= 0.14.0 =
* PHP 7.2 WC API fix.
* Move customer source tracking to JS.
* Additional source tracking data like session count, page count, etc.
* Performance improvements for the orders API endpoint.

= 0.13.0 =
* Add support for recording UTM term, content, and ID.

= 0.12.0 =
* Multisite support for customers/updated endpoint.
* Added WooCommerce 3.2 required/tested plugin headers.
* Improve Woo customers API performance.

= 0.11.0 =
* Change method for stopping customer spend calculations in API so it just does it for Metorik API requests instead of on a time-basis by option.

= 0.10.0 =
* Track 'Engage' data
* Improve UTM tracking
* Set tracking data in user meta during checkout
* Added an 'hours' arg to updated endpoints
* Added pagination to updated endpoints
* Don't include draft orders in updated endpoints

= 0.9.0 =
* Coupon endpoints

= 0.8.1 =
* Extend source & UTM cookie storing time to 6 months

= 0.8.0 =
* Track UTM tags in order/customer meta
* Filter for referer

= 0.7.1 =
* Further updated timezone fixes

= 0.7.0 =
* Include order post meta data when pre WC 2.7
* Subscriptions endpoints
* Open Metorik links in new tabs
* Fix updated timezone issue

= 0.6.1 =
* Fix notices for unset http referer

= 0.6.0 =
* Track and store customer/order referer (source)
* Endpoint for possible order statuses
* Endpoint for possible customer (user) roles
* Ignore trashed orders/products in updated endpoints
* Allow dismissing/hiding of the Metorik notices
* PHP 5.2 compat fix

= 0.5.2 =
* Fix minor PHP notices in admin

= 0.5.1 =
* Fix undefined variable notice

= 0.5.0 =
* Remove custom customer index/single endpoints if 2.7
* Links from resource admin pages to Metorik

= 0.4.2 =
* Make activation method static

= 0.4.1 =
* Fix undefined variable in products updated endpoint

= 0.4.0 =
* Refund IDs endpoint

= 0.3.1 =
* Improve stability of customers updated endpoint

= 0.3.0 =
* New endpoints for orders updated
* New endpoints for customers updated
* New endpoints for products updated
* Fix customer IDs endpoint query for custom DB prefixes

= 0.2.3 =
* Show notice prompting users to go back to Metorik after installing to complete connection

= 0.2.2 =
* Fix customer IDs endpoint permissions

= 0.2.1 =
* Override WC single customer endpoint too to make faster during imports

= 0.2.0 =
* Override WC customers endpoint to make faster during imports

= 0.1.0 =
* Initial beta release.