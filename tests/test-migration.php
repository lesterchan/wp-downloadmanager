<?php
/**
 * The 1.x -> 2.0.0 option consolidation.
 *
 * The nineteen separate rows fold into one. This is the change most likely to
 * silently reset somebody's settings, so it gets the most tests.
 *
 * @package WP-DownloadManager
 */

/**
 * Migration from the pre-2.0.0 option rows.
 */
class Test_Migration extends DownloadManager_TestCase {

	/**
	 * Wipe the consolidated row and the version marker, then write the legacy
	 * layout as a 1.69.x install would have had it.
	 *
	 * @return void
	 */
	protected function seed_legacy_rows() {
		delete_option( DownloadManager_Options::OPTION );
		delete_option( DownloadManager_Install::DB_VERSION_OPTION );
		DownloadManager_Options::flush();

		update_option( 'download_path', WP_CONTENT_DIR . '/legacy-files' );
		update_option( 'download_path_url', 'https://example.com/legacy-files' );
		update_option( 'download_page_url', 'https://example.com/legacy-downloads' );
		update_option( 'download_method', 0 );
		update_option( 'download_nice_permalink', 0 );
		update_option( 'download_categories', array( '', 'Legacy One', 'Legacy Two' ) );
		update_option(
			'download_sort',
			array(
				'by'      => 'file_hits',
				'order'   => 'desc',
				'perpage' => 7,
				'group'   => 0,
			)
		);
		update_option( 'download_template_header', '<p>legacy header</p>' );
		update_option( 'download_template_none', '<p>legacy none</p>' );
		update_option(
			'download_template_listing',
			array( '<p>legacy listing yes</p>', '<p>legacy listing no</p>' )
		);
		update_option(
			'download_template_most',
			array( '<li>legacy most yes</li>', '<li>legacy most no</li>' )
		);
		// The row 2.0.0 reuses, in its pre-2.0.0 shape.
		update_option(
			'download_options',
			array(
				'use_filename' => 1,
				'rss_sortby'   => 'file_hits',
				'rss_limit'    => 3,
			)
		);
	}

	/**
	 * Every legacy row lands at its new path.
	 */
	public function test_scalar_and_array_rows_migrate() {
		$this->seed_legacy_rows();

		DownloadManager_Install::upgrade();

		$this->assertSame( WP_CONTENT_DIR . '/legacy-files', DownloadManager_Options::get( 'path.dir' ) );
		$this->assertSame( 'https://example.com/legacy-files', DownloadManager_Options::get( 'path.url' ) );
		$this->assertSame( 'https://example.com/legacy-downloads', DownloadManager_Options::get( 'page_url' ) );
		$this->assertSame( 0, (int) DownloadManager_Options::get( 'method' ) );
		$this->assertSame( 0, (int) DownloadManager_Options::get( 'nice_permalink' ) );
		$this->assertSame( array( '', 'Legacy One', 'Legacy Two' ), DownloadManager_Options::get( 'categories' ) );
		$this->assertSame( 'file_hits', DownloadManager_Options::get( 'sort.by' ) );
		$this->assertSame( 'desc', DownloadManager_Options::get( 'sort.order' ) );
		$this->assertSame( 7, (int) DownloadManager_Options::get( 'sort.perpage' ) );
	}

	/**
	 * Templates migrate, singles and permission pairs alike.
	 */
	public function test_templates_migrate() {
		$this->seed_legacy_rows();

		DownloadManager_Install::upgrade();

		$this->assertSame( '<p>legacy header</p>', DownloadManager_Options::template( 'header' ) );
		$this->assertSame( '<p>legacy none</p>', DownloadManager_Options::template( 'none' ) );
		$this->assertSame( '<p>legacy listing yes</p>', DownloadManager_Options::template( 'listing', 0 ) );
		$this->assertSame( '<p>legacy listing no</p>', DownloadManager_Options::template( 'listing', 1 ) );
		$this->assertSame( '<li>legacy most yes</li>', DownloadManager_Options::template( 'most', 0 ) );
		$this->assertSame( '<li>legacy most no</li>', DownloadManager_Options::template( 'most', 1 ) );
	}

	/**
	 * A template nobody customised keeps the stock markup.
	 */
	public function test_untouched_templates_fall_back_to_defaults() {
		$this->seed_legacy_rows();

		DownloadManager_Install::upgrade();

		$this->assertSame(
			DownloadManager_Templates::get_default( 'category_header' ),
			DownloadManager_Options::template( 'category_header' )
		);
	}

	/**
	 * The reused row's own pre-2.0.0 keys reach their new nested homes.
	 *
	 * download_options is the row being written, so its old contents arrive
	 * through the defaults merge as stray top-level keys rather than through the
	 * legacy loop. Missing this leaves rss_sortby sitting at the top level while
	 * rss.sortby quietly stays on the default.
	 */
	public function test_reused_row_keys_migrate() {
		$this->seed_legacy_rows();

		DownloadManager_Install::upgrade();

		$this->assertSame( 1, (int) DownloadManager_Options::get( 'use_filename' ) );
		$this->assertSame( 'file_hits', DownloadManager_Options::get( 'rss.sortby' ) );
		$this->assertSame( 3, (int) DownloadManager_Options::get( 'rss.limit' ) );

		$stored = get_option( DownloadManager_Options::OPTION );
		$this->assertArrayNotHasKey( 'rss_sortby', $stored, 'the stray top level key should be gone' );
		$this->assertArrayNotHasKey( 'rss_limit', $stored );
	}

	/**
	 * The legacy rows are deleted afterwards.
	 */
	public function test_legacy_rows_are_removed() {
		$this->seed_legacy_rows();

		DownloadManager_Install::upgrade();

		foreach ( array_keys( DownloadManager_Options::legacy_map() ) as $legacy ) {
			$this->assertFalse( get_option( $legacy, false ), "{$legacy} should be gone" );
		}
	}

	/**
	 * The row the settings now live in is NOT deleted.
	 *
	 * Listing download_options among the legacy rows would have the migration
	 * cheerfully delete the row it had just written, sending every setting back
	 * to defaults on the next load.
	 */
	public function test_consolidated_row_survives() {
		$this->seed_legacy_rows();

		DownloadManager_Install::upgrade();

		$this->assertNotFalse( get_option( DownloadManager_Options::OPTION, false ) );
		$this->assertNotContains( DownloadManager_Options::OPTION, array_keys( DownloadManager_Options::legacy_map() ) );
		$this->assertNotContains( DownloadManager_Options::OPTION, DownloadManager_Options::legacy_extra_rows() );
	}

	/**
	 * Running the migration twice changes nothing.
	 */
	public function test_migration_is_idempotent() {
		$this->seed_legacy_rows();

		DownloadManager_Install::upgrade();
		$first = get_option( DownloadManager_Options::OPTION );

		DownloadManager_Install::upgrade();
		DownloadManager_Options::flush();

		$this->assertSame( $first, get_option( DownloadManager_Options::OPTION ) );
	}

	/**
	 * The version gate, not the presence of old rows, decides.
	 *
	 * An install that has already migrated has no legacy rows left, so a
	 * presence check would write defaults straight over its settings.
	 */
	public function test_already_migrated_install_is_untouched() {
		DownloadManager_Options::set( 'page_url', 'https://example.com/kept' );
		update_option( DownloadManager_Install::DB_VERSION_OPTION, DownloadManager_Install::DB_VERSION );

		DownloadManager_Install::upgrade();
		DownloadManager_Options::flush();

		$this->assertSame( 'https://example.com/kept', DownloadManager_Options::get( 'page_url' ) );
	}

	/**
	 * A missing version marker beside a surviving consolidated row is a no-op.
	 *
	 * A partial restore, a downgrade and re-upgrade, or an over-eager cleanup
	 * plugin can leave exactly this state. Seeding the migration from the stored
	 * value rather than from the defaults makes it harmless instead of
	 * destructive - there are no legacy rows left to read the settings back
	 * from.
	 */
	public function test_missing_version_marker_does_not_reset_settings() {
		DownloadManager_Options::set( 'page_url', 'https://example.com/survivor' );
		DownloadManager_Options::set( 'sort.perpage', 42 );
		delete_option( DownloadManager_Install::DB_VERSION_OPTION );

		DownloadManager_Install::upgrade();
		DownloadManager_Options::flush();

		$this->assertSame( 'https://example.com/survivor', DownloadManager_Options::get( 'page_url' ) );
		$this->assertSame( 42, (int) DownloadManager_Options::get( 'sort.perpage' ) );
	}

	/**
	 * The version marker is written so the migration does not run again.
	 */
	public function test_version_is_recorded() {
		$this->seed_legacy_rows();

		DownloadManager_Install::upgrade();

		$this->assertSame(
			DownloadManager_Install::DB_VERSION,
			(int) get_option( DownloadManager_Install::DB_VERSION_OPTION )
		);
	}

	/**
	 * Everything ends up in one row rather than nineteen.
	 */
	public function test_only_one_option_row_remains() {
		global $wpdb;

		$this->seed_legacy_rows();
		DownloadManager_Install::upgrade();

		// phpcs:ignore WordPress.DB
		$rows = $wpdb->get_col(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'download\\_%'"
		);

		sort( $rows );

		$this->assertSame(
			array( 'download_db_version', 'download_options' ),
			$rows
		);
	}

	/**
	 * uninstall.php cleans up both the new row and any legacy leftovers.
	 */
	public function test_uninstall_covers_every_row() {
		$source = $this->code( 'uninstall.php' );

		$this->assertStringContainsString( 'legacy_map', $source );
		$this->assertStringContainsString( 'legacy_extra_rows', $source );
		$this->assertStringContainsString( 'DownloadManager_Options::OPTION', $source );
		$this->assertStringContainsString( 'download_db_version', $source );
	}
}
