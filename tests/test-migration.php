<?php
/**
 * Folding the pre-2.0.0 option rows into wp_downloadmanager_options.
 *
 * A site upgrading from 1.69.2 has nineteen download_* rows plus two rows it
 * shared with WP-Stats. All of them have to end up inside one array with the
 * right values, and all of them have to be gone afterwards - a migration that
 * copies but does not clean up leaves the next version guessing which copy is
 * authoritative.
 *
 * @package WP-DownloadManager
 */

/**
 * The upgrade path from the released version.
 */
class WP_DownloadManager_Migration_Test extends WP_DownloadManager_TestCase {

	/**
	 * Put the database back the way 1.69.2 left it.
	 *
	 * @param array $extra Additional legacy rows to seed.
	 * @return void
	 */
	protected function seed_legacy_rows( $extra = array() ) {
		delete_option( WP_DownloadManager_Options::OPTION );
		delete_option( WP_DownloadManager_Options::VERSION );
		WP_DownloadManager_Options::flush();

		$rows = array_merge(
			array(
				'download_path'           => WP_CONTENT_DIR . '/legacy-files',
				'download_path_url'       => 'https://example.com/legacy-files',
				'download_page_url'       => 'https://example.com/legacy-downloads',
				'download_method'         => 0,
				'download_nice_permalink' => 0,
				'download_categories'     => array( '', 'Legacy', 'Archive' ),
				'download_sort'           => array(
					'by'      => 'file_hits',
					'order'   => 'desc',
					'perpage' => 15,
					'group'   => 0,
				),
				'download_options'        => array(
					'use_filename' => 1,
					'rss_sortby'   => 'file_size',
					'rss_limit'    => 5,
				),
				'download_db_version'     => 2,
			),
			$extra
		);

		foreach ( $rows as $name => $value ) {
			update_option( $name, $value );
		}
	}

	/**
	 * Put the database back the way 1.69.2 left it on a site nobody configured.
	 *
	 * Every value comes out of the plugin's own defaults, through legacy_map(),
	 * rather than being typed -- so a row added to the map is in this fixture
	 * too, and a changed default cannot quietly turn it into a second
	 * customised fixture.
	 *
	 * @return void
	 */
	protected function seed_stock_legacy_rows() {
		delete_option( WP_DownloadManager_Options::OPTION );
		delete_option( WP_DownloadManager_Options::VERSION );
		WP_DownloadManager_Options::flush();

		// With no stored row and the cache dropped, get() answers with the
		// shipped default for each path, which is what makes each row below
		// stock rather than merely plausible.
		foreach ( WP_DownloadManager_Options::legacy_map() as $legacy => $path ) {
			update_option( $legacy, WP_DownloadManager_Options::get( $path ) );
		}

		WP_DownloadManager_Options::flush();
	}

	/**
	 * Run the migration the way the upgrade path does.
	 *
	 * @return void
	 */
	protected function migrate() {
		WP_DownloadManager_Options::migrate_from_legacy_rows();
		WP_DownloadManager_Options::save_markers( WP_DOWNLOADMANAGER_VERSION, WP_DOWNLOADMANAGER_DB_VERSION );
		WP_DownloadManager_Options::flush();
	}

	/**
	 * A site that configured nothing still comes out with a settings row.
	 *
	 * The fixture above is customised in every field, which is right for "did the
	 * values carry across" but cannot see the case that matters here: a result
	 * differing from the defaults is written whatever happened on the way in.
	 *
	 * This seeds the stock values instead, and registers the setting first so the
	 * default_option_wp_downloadmanager_options filter is live while save() runs.
	 * An absent row then reads back as the defaults, and update_option() on its
	 * own returns early on a value identical to the one it just read -- writing
	 * nothing, while the legacy rows are deleted a few lines later.
	 *
	 * What stops that is the explicit add_option() in save(). It used to be an
	 * accident instead: the sanitiser could not read a stored category list, so
	 * it altered the defaults on the way past and core found a difference to
	 * write. Both are pinned at the door as well, in test-options.php and
	 * test-settings.php, so this test is the end-to-end pass rather than the only
	 * thing standing between the migration and a silently absent row.
	 *
	 * Asserted on the raw row, because get() merges over the defaults and cannot
	 * tell a write that happened from one that did not.
	 */
	public function test_a_stock_install_still_gets_its_row_written() {
		$this->seed_stock_legacy_rows();

		$this->assertFalse( get_option( WP_DownloadManager_Options::OPTION, false ), 'The fixture is only pre-migration if the consolidated row is genuinely absent.' );

		WP_DownloadManager_Settings::register();

		$this->migrate();

		$stored = get_option( WP_DownloadManager_Options::OPTION, false );

		$this->assertIsArray( $stored, 'The migration must write the consolidated row even when its result equals the shipped defaults.' );
		$this->assertArrayHasKey( 'templates', $stored, 'The written row is the whole structure, not a fragment of it.' );

		foreach ( array_keys( WP_DownloadManager_Options::legacy_map() ) as $legacy ) {
			$this->assertFalse( get_option( $legacy, false ), sprintf( 'The legacy row %s must not survive the migration.', $legacy ) );
		}
	}

	public function test_the_scalar_rows_land_in_the_consolidated_array() {
		$this->seed_legacy_rows();
		$this->migrate();

		$this->assertSame( WP_CONTENT_DIR . '/legacy-files', WP_DownloadManager_Options::get( 'path.dir' ), 'download_path becomes path.dir' );
		$this->assertSame( 'https://example.com/legacy-files', WP_DownloadManager_Options::get( 'path.url' ), 'download_path_url becomes path.url' );
		$this->assertSame( 'https://example.com/legacy-downloads', WP_DownloadManager_Options::get( 'page_url' ), 'download_page_url becomes page_url' );
		$this->assertSame( 0, WP_DownloadManager_Options::get( 'method' ), 'download_method becomes method' );
	}

	public function test_the_bare_nice_permalink_row_folds_in() {
		$this->seed_legacy_rows();
		$this->migrate();

		$this->assertSame( 0, WP_DownloadManager_Options::get( 'nice_permalink' ), 'the row that was never part of download_options folds in too' );
		$this->assertFalse( get_option( 'download_nice_permalink', false ), 'and is deleted afterwards' );
	}

	public function test_the_nested_rows_land_in_the_consolidated_array() {
		$this->seed_legacy_rows();
		$this->migrate();

		$this->assertSame( 'file_hits', WP_DownloadManager_Options::get( 'sort.by' ), 'the sort array comes across whole' );
		$this->assertSame( 15, WP_DownloadManager_Options::get( 'sort.perpage' ), 'A nested legacy row lands under its consolidated key.' );
		$this->assertSame( array( '', 'Legacy', 'Archive' ), WP_DownloadManager_Options::get( 'categories' ), 'the category list keeps its numbering' );
	}

	public function test_the_old_settings_row_is_unpacked_into_its_new_homes() {
		$this->seed_legacy_rows();
		$this->migrate();

		$this->assertSame( 1, WP_DownloadManager_Options::get( 'use_filename' ), 'use_filename kept its name' );
		$this->assertSame( 'file_size', WP_DownloadManager_Options::get( 'rss.sortby' ), 'rss_sortby became rss.sortby' );
		$this->assertSame( 5, WP_DownloadManager_Options::get( 'rss.limit' ), 'rss_limit became rss.limit' );
	}

	public function test_no_stray_keys_survive_from_the_old_settings_row() {
		$this->seed_legacy_rows();
		$this->migrate();

		$stored = get_option( WP_DownloadManager_Options::OPTION );

		$this->assertArrayNotHasKey( 'rss_sortby', $stored, 'the flat key must not sit beside the nested one shadowing nothing' );
		$this->assertArrayNotHasKey( 'rss_limit', $stored, 'the flat key must not sit beside the nested one shadowing nothing' );
	}

	public function test_the_templates_come_across() {
		$this->seed_legacy_rows( array( 'download_template_header' => '<p>My header</p>' ) );
		$this->migrate();

		$this->assertSame( '<p>My header</p>', WP_DownloadManager_Options::template( 'header' ), 'a customised template survives the upgrade' );
	}

	public function test_the_stock_image_wrapper_is_stripped_from_stored_templates() {
		$this->seed_legacy_rows(
			array(
				'download_template_listing' => array(
					'<p><img src="https://example.com/wp-content/plugins/wp-downloadmanager/images/ext/%FILE_ICON%" alt="" title="" style="vertical-align: middle;" /> <strong>%FILE_NAME%</strong></p>',
					'<p>%FILE_NAME%</p>',
				),
			)
		);
		$this->migrate();

		$permitted = WP_DownloadManager_Options::template( 'listing', 0 );

		$this->assertStringNotContainsString( '<img', $permitted, '%FILE_ICON% is the whole icon now, so the wrapper has to go' );
		$this->assertStringContainsString( '%FILE_ICON%', $permitted, 'the variable itself stays' );
		$this->assertStringContainsString( '%FILE_NAME%', $permitted, 'and the rest of the template is untouched' );
	}

	public function test_every_legacy_row_is_deleted_afterwards() {
		$this->seed_legacy_rows( array( 'download_template_header' => '<p>My header</p>' ) );
		$this->migrate();

		$names = array_merge(
			array_keys( WP_DownloadManager_Options::legacy_map() ),
			array_values( WP_DownloadManager_Options::legacy_structured_rows() ),
			WP_DownloadManager_Options::legacy_extra_rows()
		);

		foreach ( $names as $name ) {
			$this->assertFalse( get_option( $name, false ), $name . ' should have been deleted by the migration' );
		}
	}

	public function test_the_dead_widget_rows_are_deleted_too() {
		$this->seed_legacy_rows(
			array(
				'widget_download_most_downloaded'  => array( 'title' => 'old' ),
				'widget_download_recent_downloads' => array( 'title' => 'old' ),
			)
		);
		$this->migrate();

		$this->assertFalse( get_option( 'widget_download_most_downloaded', false ), 'the pre-WP_Widget rows carry nothing forward' );
		$this->assertFalse( get_option( 'widget_download_recent_downloads', false ), 'the pre-WP_Widget rows carry nothing forward' );
	}

	public function test_the_new_rows_hold_the_values_afterwards() {
		$this->seed_legacy_rows();
		$this->migrate();

		$this->assertIsArray( get_option( WP_DownloadManager_Options::OPTION ), 'the settings row exists' );
		$this->assertSame(
			array(
				'plugin' => WP_DOWNLOADMANAGER_VERSION,
				'db'     => WP_DOWNLOADMANAGER_DB_VERSION,
			),
			WP_DownloadManager_Options::markers(),
			'and the marker row records where the upgrade got to'
		);
	}

	public function test_the_wp_stats_toggle_folds_in_when_any_panel_was_on() {
		$this->seed_legacy_rows(
			array(
				'stats_display'   => array(
					'downloads'        => 0,
					'recent_downloads' => 1,
					'downloaded_most'  => 0,
				),
				'stats_mostlimit' => 5,
			)
		);
		$this->migrate();

		$this->assertSame( 1, WP_DownloadManager_Options::get( 'stats_display' ), 'this plugin contributes one section now, so any panel being on means yes' );
		$this->assertSame( 5, WP_DownloadManager_Options::get( 'stats_most_limit' ), 'stats_mostlimit becomes stats_most_limit' );
	}

	public function test_the_wp_stats_toggle_stays_off_when_every_panel_was_off() {
		$this->seed_legacy_rows(
			array(
				'stats_display' => array(
					'downloads'        => 0,
					'recent_downloads' => 0,
					'downloaded_most'  => 0,
				),
			)
		);
		$this->migrate();

		$this->assertSame( 0, WP_DownloadManager_Options::get( 'stats_display' ), 'a site that had turned all three off keeps them off' );
	}

	public function test_another_plugins_wp_stats_panels_do_not_turn_ours_on() {
		$this->seed_legacy_rows(
			array(
				'stats_display' => array(
					'polls'     => 1,
					'downloads' => 0,
				),
			)
		);
		$this->migrate();

		$this->assertSame( 0, WP_DownloadManager_Options::get( 'stats_display' ), 'only this plugin\'s three keys are consulted' );
	}

	public function test_a_shared_row_a_sibling_already_deleted_reads_as_on_not_off() {
		// Section 13.2. Seven plugins read stats_display and every one of their
		// migrations deletes it, so whichever plugin a site upgrades first takes
		// it away from the other six. Reading its absence as an opt-out is what
		// makes a downloads block vanish with no error on a site that updated
		// WP-Stats first.
		$this->seed_legacy_rows();
		delete_option( 'stats_display' );

		$this->migrate();

		$this->assertSame(
			1,
			WP_DownloadManager_Options::get( 'stats_display' ),
			'a block someone has to switch off again beats a block that disappears without explanation'
		);
	}

	public function test_a_shared_row_a_sibling_already_deleted_does_not_lower_the_row_limit() {
		$this->seed_legacy_rows();
		delete_option( 'stats_mostlimit' );

		$this->migrate();

		$this->assertSame( 10, WP_DownloadManager_Options::get( 'stats_most_limit' ), 'the shipped default stands when there is nothing to migrate' );
	}

	public function test_an_explicit_opt_out_in_the_shared_row_is_still_honoured() {
		$this->seed_legacy_rows( array( 'stats_display' => array( 'downloads' => 0 ) ) );

		$this->migrate();

		$this->assertSame( 0, WP_DownloadManager_Options::get( 'stats_display' ), 'a row that is present and says no means no' );
	}

	public function test_the_shared_wp_stats_rows_are_deleted() {
		$this->seed_legacy_rows(
			array(
				'stats_display'   => array( 'downloads' => 1 ),
				'stats_mostlimit' => 5,
			)
		);
		$this->migrate();

		$this->assertFalse( get_option( 'stats_display', false ), 'the shared row goes; each plugin owns its own copy now' );
		$this->assertFalse( get_option( 'stats_mostlimit', false ), 'the shared row goes; each plugin owns its own copy now' );
	}

	public function test_a_zero_row_limit_is_lifted_to_one() {
		$this->seed_legacy_rows( array( 'stats_mostlimit' => 0 ) );
		$this->migrate();

		$this->assertSame( 1, WP_DownloadManager_Options::get( 'stats_most_limit' ), 'a limit of zero would render an empty list forever' );
	}

	public function test_a_missing_marker_row_does_not_blank_the_settings() {
		// A partial restore, a downgrade and re-upgrade, or an over-eager cleanup
		// plugin: the marker row is gone but the settings row survives. Running
		// the migration must be a no-op, not destructive.
		WP_DownloadManager_Options::save( array( 'page_url' => 'https://example.com/keep-me' ) );
		delete_option( WP_DownloadManager_Options::VERSION );

		$this->migrate();

		$this->assertSame( 'https://example.com/keep-me', WP_DownloadManager_Options::get( 'page_url' ), 'there are no legacy rows to read the setting back from, so it must not be overwritten' );
	}

	public function test_running_the_migration_twice_changes_nothing() {
		$this->seed_legacy_rows();
		$this->migrate();

		$after_once = get_option( WP_DownloadManager_Options::OPTION );

		$this->migrate();

		$this->assertSame( $after_once, get_option( WP_DownloadManager_Options::OPTION ), 'the migration is idempotent' );
	}

	public function test_the_upgrade_runs_when_the_schema_marker_is_behind() {
		$this->seed_legacy_rows();

		WP_DownloadManager_Install::upgrade();
		WP_DownloadManager_Options::flush();

		$this->assertSame( WP_DOWNLOADMANAGER_DB_VERSION, WP_DownloadManager_Options::markers()['db'], 'the upgrade records the schema it reached' );
		$this->assertSame( 'https://example.com/legacy-downloads', WP_DownloadManager_Options::get( 'page_url' ), 'and it actually migrated' );
	}

	public function test_the_upgrade_re_sanitises_settings_when_only_the_plugin_marker_is_behind() {
		WP_DownloadManager_Options::save( array( 'sort' => array( 'by' => 'DROP TABLE' ) ) );
		WP_DownloadManager_Options::save_markers( '1.0.0', WP_DOWNLOADMANAGER_DB_VERSION );

		WP_DownloadManager_Install::upgrade();
		WP_DownloadManager_Options::flush();

		$this->assertSame( 'file_name', WP_DownloadManager_Options::get( 'sort.by' ), 'a release that tightens a sanitiser cleans up what an older one let through' );
		$this->assertSame( WP_DOWNLOADMANAGER_VERSION, WP_DownloadManager_Options::markers()['plugin'], 'and the plugin marker catches up' );
	}

	public function test_the_upgrade_does_nothing_when_both_markers_are_current() {
		WP_DownloadManager_Options::save( array( 'page_url' => 'https://example.com/untouched' ) );
		WP_DownloadManager_Options::save_markers( WP_DOWNLOADMANAGER_VERSION, WP_DOWNLOADMANAGER_DB_VERSION );

		WP_DownloadManager_Install::upgrade();
		WP_DownloadManager_Options::flush();

		$this->assertSame( 'https://example.com/untouched', WP_DownloadManager_Options::get( 'page_url' ), 'an up-to-date install pays nothing on every admin request' );
	}
}
