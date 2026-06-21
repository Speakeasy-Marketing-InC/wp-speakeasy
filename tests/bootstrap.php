<?php
/**
 * PHPUnit bootstrap file for WP Speakeasy
 *
 * @package WP_Speakeasy
 */

// Composer autoloader.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// WordPress test library path - adjust based on your environment.
$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

// Load WordPress test library.
if ( file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	require_once $_tests_dir . '/includes/functions.php';

	/**
	 * Manually load the plugin for testing.
	 */
	function _manually_load_plugin() {
		require dirname( __DIR__ ) . '/wp-speakeasy.php';
	}

	tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

	require $_tests_dir . '/includes/bootstrap.php';
} else {
	// Fallback for environments without WordPress test suite.
	echo "Warning: WordPress test suite not found. Using minimal bootstrap.\n";

	// Define minimal WordPress constants and functions for testing.
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', '/tmp/' );
	}

	// Mock WordPress functions used by the plugin.
	require_once __DIR__ . '/wordpress-mocks.php';
}
