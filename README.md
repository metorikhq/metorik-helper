[![StyleCI](https://styleci.io/repos/69536649/shield?branch=master)](https://styleci.io/repos/69536649)

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

## Testing

As of v2 of the plugin, we now have some limited unit & integration testing in the plugin.

In order to run the tests you'll first need to set up dependencies using `composer`:

```
composer install
```

Then you'll need to set up the test suite using `wp-pest`:

```
vendor/bin/wp-pest setup plugin --plugin-slug=metorik-helper --skip-delete
```

This will create the necessary files in your project to run the tests.

Then you can run the tests using:

```
composer test:unit
composer test:integration
```
