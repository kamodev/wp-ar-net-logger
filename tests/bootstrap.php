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
