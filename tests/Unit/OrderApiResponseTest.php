<?php

use Brain\Monkey\Functions;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

uses(TestCase::class)->group('unit');

beforeEach(function () {
    Functions\when('add_action')->justReturn(true);
    Functions\when('add_filter')->justReturn(true);
    Functions\when('WC')->justReturn((object) ['version' => '11.2.1']);
    require_once dirname(__DIR__, 2) . '/inc/api/orders.php';
    $this->ordersApi = (new ReflectionClass(Metorik_Helper_API_Orders::class))->newInstanceWithoutConstructor();
});

test('unexpected order responses pass through without the production property assignment fatal', function ($response) {
    $request = Mockery::mock();
    $request->shouldNotReceive('get_headers');

    expect($this->ordersApi->remove_coupon_line_items_meta($response, null, $request))->toBe($response);
})->with([
    'empty string returned by an earlier filter' => [''],
    'string response' => ['unavailable'],
    'null response' => [null],
    'false response' => [false],
    'array response' => [['id' => 123]],
    'unrelated object' => [new stdClass()],
    'WordPress error' => [new WP_Error()],
]);

test('valid Metorik order responses lose only coupon metadata', function () {
    $data = [
        'id' => 123,
        'meta_data' => [['key' => 'keep', 'value' => 'order metadata']],
        'coupon_lines' => [
            ['code' => 'SAVE', 'discount' => '5.00', 'meta_data' => [['key' => 'large', 'value' => 'coupon rules']]],
            ['code' => 'OTHER', 'discount' => '2.00'],
            'unexpected line',
        ],
    ];
    $expected = $data;
    unset($expected['coupon_lines'][0]['meta_data']);
    $response = Mockery::mock(WP_REST_Response::class);
    $response->shouldReceive('get_data')->once()->andReturn($data);
    $response->shouldReceive('set_data')->once()->with($expected);
    $request = Mockery::mock();
    $request->shouldReceive('get_headers')->once()->andReturn(['user_agent' => ['Metorik API Client']]);

    expect($this->ordersApi->remove_coupon_line_items_meta($response, null, $request))->toBe($response);
});

test('other clients keep their complete coupon response', function () {
    $response = Mockery::mock(WP_REST_Response::class);
    $response->shouldNotReceive('get_data');
    $response->shouldNotReceive('set_data');
    $request = Mockery::mock();
    $request->shouldReceive('get_headers')->once()->andReturn(['user_agent' => ['Other client']]);

    expect($this->ordersApi->remove_coupon_line_items_meta($response, null, $request))->toBe($response);
});

test('unexpected REST response data passes through without mutation', function ($data) {
    $response = Mockery::mock(WP_REST_Response::class);
    $response->shouldReceive('get_data')->once()->andReturn($data);
    $response->shouldNotReceive('set_data');
    $request = Mockery::mock();
    $request->shouldReceive('get_headers')->once()->andReturn(['user_agent' => ['Metorik API Client']]);

    expect($this->ordersApi->remove_coupon_line_items_meta($response, null, $request))->toBe($response);
})->with([
    'string data' => [''],
    'null data' => [null],
    'false data' => [false],
    'no coupons' => [['id' => 123]],
    'malformed coupons' => [['coupon_lines' => 'unexpected']],
]);
