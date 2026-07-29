<?php
/**
 * PHPUnit bootstrap for WP-DownloadManager.
 *
 * Runs inside the wp-env "tests" container, where WP_TESTS_DIR is already
 * exported and the WordPress test library is present.
 *
 * @package WP-DownloadManager
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = '/wordpress-phpunit';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find the WordPress test library at {$_tests_dir}." . PHP_EOL;
	echo 'Run the suite through wp-env: bash bin/test.sh' . PHP_EOL;
	exit( 1 );
}

require_once $_tests_dir . '/includes/functions.php';

/**
 * Load the plugin under test before WordPress finishes booting.
 */
function _wp_downloadmanager_manually_load_plugin() {
	require dirname( __DIR__ ) . '/wp-downloadmanager.php';

	// register_activation_hook() never fires in the test environment, so the
	// table and the option rows have to be created by hand.
	if ( class_exists( 'WP_DownloadManager_Install' ) ) {
		WP_DownloadManager_Install::activate();
	} else {
		// Pre-2.0.0 layout, still used while the golden master runs against the
		// baseline.
		wp_downloadmanager_activate();
	}
}
tests_add_filter( 'muplugins_loaded', '_wp_downloadmanager_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';

// After the WordPress bootstrap, which is what declares WP_UnitTestCase - the
// fixture base class extends it, so it cannot be loaded any earlier.
require_once __DIR__ . '/helper-fixtures.php';
