# Metorik Helper

This is a WordPress plugin that helps [Metorik](https://app.metorik.com) connect and work better with WooCommerce stores.

## Development

When setting up initially, run:

```
yarn
```

To install its dependencies.

After making changes to a JS file, namely `metorik.js`, run:

```
gulp magic
```

To compile a minified JS file.

---

## Testing

As of v2 of the plugin, we now have some limited unit & integration testing in the plugin.

In order to run the tests you'll first need to set up dependencies using `composer`:

```
composer install
```

Then you'll need to set up the test suite using `wp-pest`:

```
vendor/bin/wp-pest setup plugin --plugin-slug=metorik-helper --skip-delete --wp-version=6.4.2
```
(at the moment there is issues with installing WP 6.5+ with `wp-pest` so we are using 6.4.2)

This will create the necessary files in your project to run the tests.

Then you can run the tests using:

```
composer test:unit
composer test:integration
```
