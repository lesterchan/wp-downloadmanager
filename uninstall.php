<?php
/**
 * Uninstall WP-DownloadManager.
 *
 * @package WP-DownloadManager
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

require_once __DIR__ . '/includes/class-downloadmanager-templates.php';
require_once __DIR__ . '/includes/class-downloadmanager-options.php';

/**
 * Remove every option row and the downloads table for the current site.
 *
 * @return void
 */
function downloadmanager_uninstall_site() {
	global $wpdb;

	// The legacy rows are listed by the options class, so this and the migration
	// can never disagree about which rows belong to the plugin. 2.0.0
	// consolidated them into download_options, but an install that never reached
	// the migration may still have them.
	$option_names = array_merge(
		array_keys( DownloadManager_Options::legacy_map() ),
		DownloadManager_Options::legacy_extra_rows(),
		array(
			DownloadManager_Options::OPTION,
			'download_db_version',
			'widget_downloads',
		)
	);

	foreach ( $option_names as $option_name ) {
		delete_option( $option_name );
	}

	// Dropping the plugin's own table is the entire job here. The table was
	// dropped once per option row before, which worked only by accident.
	$table = $wpdb->prefix . 'downloads';
	$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB
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
		downloadmanager_uninstall_site();
		// switch_to_blog() pushes onto a stack, so the restore belongs inside
		// the loop rather than once at the end.
		restore_current_blog();
	}
} else {
	downloadmanager_uninstall_site();
}
