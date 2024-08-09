<?php

use Yoast\WPTestUtils\BrainMonkey\TestCase;

uses()->group( 'integration' )->in( 'Integration' );
uses()->group( 'unit' )->in( 'Unit' );

uses( TestCase::class )->in( 'Unit', 'Integration' );

require_once 'helpers/wc-helpers.php';

function isUnitTest() {
	return ! empty( $GLOBALS['argv'] ) && $GLOBALS['argv'][1] === '--group=unit';
}
