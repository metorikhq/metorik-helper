<?php

namespace Tests\Integration;

if (isUnitTest()) {
    return;
}

test('cart tracking is enabled based on auth token set', function () {
    update_option('metorik_auth_token', 'test');
    expect(metorik_cart_tracking_enabled())->toBeTrue();

    update_option('metorik_auth_token', false);
    expect(metorik_cart_tracking_enabled())->toBeFalse();

    update_option('metorik_auth_token', 'token-1234');
    expect(metorik_cart_tracking_enabled())->toBeTrue();

    delete_option('metorik_auth_token');
    expect(metorik_cart_tracking_enabled())->toBeFalse();
});
