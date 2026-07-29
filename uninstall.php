<?php
/**
 * Uninstall WP-DownloadManager.
 *
 * @package WP-DownloadManager
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

require_once __DIR__ . '/includes/class-wp-downloadmanager-template.php';
require_once __DIR__ . '/includes/class-wp-downloadmanager-options.php';
// The table drop lives in the install class so the schema change stays in
// includes/ with the rest of the plugin's table work; this file declares
// nothing but a class, so requiring it here runs no code.
require_once __DIR__ . '/includes/class-wp-downloadmanager-install.php';

// Guarded because the test suite runs this file more than once in a process;
// WordPress itself only ever includes it the once.
if ( ! function_exists( 'wp_downloadmanager_uninstall_site' ) ) {
	/**
	 * Remove every option row and the downloads table for the current site.
	 *
	 * @return void
	 */
	function wp_downloadmanager_uninstall_site() {
		// The legacy rows are listed by the options class, so this and the
		// migration can never disagree about which rows belong to the plugin.
		// 2.0.0 consolidated them into wp_downloadmanager_options, but an
		// install that never reached the migration may still have them.
		$option_names = array_merge(
			array_keys( WP_DownloadManager_Options::legacy_map() ),
			array_values( WP_DownloadManager_Options::legacy_structured_rows() ),
			WP_DownloadManager_Options::legacy_extra_rows(),
			array(
				WP_DownloadManager_Options::OPTION,
				WP_DownloadManager_Options::VERSION,
				'widget_downloads',
			)
		);

		foreach ( $option_names as $option_name ) {
			delete_option( $option_name );
		}

		// Dropping the plugin's own table is the entire job here. The table was
		// dropped once per option row before, which worked only by accident.
		WP_DownloadManager_Install::drop_table();
	}
}

if ( is_multisite() ) {
	// 'number' => 0 lifts WP_Site_Query's default cap of 100. Without it a
	// network larger than that silently keeps its options and tables on every
	// site past the hundredth, and uninstall still reports success.
	$site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( (int) $site_id );
		wp_downloadmanager_uninstall_site();
		// switch_to_blog() pushes onto a stack, so the restore belongs inside
		// the loop rather than once at the end.
		restore_current_blog();
	}
} else {
	wp_downloadmanager_uninstall_site();
}
