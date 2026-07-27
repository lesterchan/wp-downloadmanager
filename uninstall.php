<?php
/**
 * Uninstall WP-DownloadManager.
 *
 * @package WP-DownloadManager
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

/**
 * Remove every option row and the downloads table for the current site.
 *
 * @return void
 */
function downloadmanager_uninstall_site() {
	global $wpdb;

	$option_names = array(
		'download_path',
		'download_path_url',
		'download_page_url',
		'download_method',
		'download_categories',
		'download_sort',
		'download_template_header',
		'download_template_footer',
		'download_template_category_header',
		'download_template_category_footer',
		'download_template_listing',
		'download_template_embedded',
		'download_template_most',
		'download_template_pagingheader',
		'download_template_pagingfooter',
		'download_nice_permalink',
		'download_template_download_page_link',
		'download_template_none',
		'download_options',
		'widget_download_most_downloaded',
		'widget_download_recent_downloads',
		'widget_downloads',
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
