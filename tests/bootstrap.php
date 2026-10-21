<?php
/**
 * PHPUnit bootstrap for the WordPress-independent unit tests.
 *
 * @package AR_Net_Logger
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require_once dirname( __DIR__ ) . '/includes/class-arnl-recurrence.php';
require_once dirname( __DIR__ ) . '/includes/class-arnl-callsign-lookup.php';
require_once dirname( __DIR__ ) . '/includes/class-arnl-discord.php';
require_once dirname( __DIR__ ) . '/includes/class-arnl-facebook.php';
