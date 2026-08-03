<?php
/**
 * Uninstalling, on a single site and on a network.
 *
 * @package WP-DownloadManager
 */

/**
 * What uninstall.php leaves behind, which should be nothing.
 */
class WP_DownloadManager_Uninstall_Test extends WP_DownloadManager_TestCase {

	/**
	 * Run uninstall.php the way WordPress does.
	 *
	 * @return void
	 */
	protected function uninstall() {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'wp-downloadmanager/wp-downloadmanager.php' );
		}

		require WP_DOWNLOADMANAGER_DIR . 'uninstall.php';
	}

	/**
	 * Put the table back so the next test has something to truncate.
	 */
	public function tear_down() {
		WP_DownloadManager_Install::activate();

		parent::tear_down();
	}

	public function test_the_settings_row_is_removed() {
		WP_DownloadManager_Options::save( WP_DownloadManager_Options::defaults() );

		$this->uninstall();

		$this->assertFalse( get_option( WP_DownloadManager_Options::OPTION, false ), 'Uninstall deletes the settings row.' );
	}

	public function test_the_marker_row_is_removed() {
		WP_DownloadManager_Options::save_markers( '2.0.0', '3' );

		$this->uninstall();

		$this->assertFalse( get_option( WP_DownloadManager_Options::VERSION, false ), 'Uninstall deletes the version row.' );
	}

	public function test_every_legacy_row_is_removed_even_if_the_migration_never_ran() {
		$names = array_diff(
			array_merge(
				array_keys( WP_DownloadManager_Options::legacy_map() ),
				array_values( WP_DownloadManager_Options::legacy_structured_rows() ),
				WP_DownloadManager_Options::legacy_extra_rows()
			),
			// Every legacy row this plugin owns -- which is not all of them. The
			// two shared WP-Stats rows are on those lists so the migration knows
			// where to fold them, and uninstall deliberately leaves them behind.
			WP_DownloadManager_Options::legacy_shared_rows()
		);

		foreach ( $names as $name ) {
			update_option( $name, 'left over' );
		}

		$this->uninstall();

		foreach ( $names as $name ) {
			$this->assertFalse( get_option( $name, false ), $name . ' should have been removed' );
		}
	}

	public function test_the_widget_instance_row_is_removed() {
		update_option( 'widget_downloads', array( 'title' => 'Downloads' ) );

		$this->uninstall();

		$this->assertFalse( get_option( 'widget_downloads', false ), 'Uninstall deletes the widget instance row.' );
	}

	public function test_nothing_matching_the_plugin_prefix_survives() {
		global $wpdb;

		WP_DownloadManager_Options::save( WP_DownloadManager_Options::defaults() );
		WP_DownloadManager_Options::save_markers( '2.0.0', '3' );

		$this->uninstall();

		$left = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'wp\\_downloadmanager\\_%'" );

		$this->assertSame( array(), $left, 'left behind: ' . implode( ', ', $left ) );
	}

	public function test_the_downloads_table_is_dropped() {
		/*
		 * Watch for the DROP rather than looking for the table afterwards.
		 *
		 * WP_UnitTestCase filters every query through _create_temporary_tables()
		 * and _drop_temporary_tables(), which rewrite CREATE/DROP TABLE into the
		 * TEMPORARY forms so that a test cannot alter real schema. SHOW TABLES
		 * never lists a temporary table and a DROP TEMPORARY TABLE cannot remove
		 * a real one, so "is the table gone" is a question this environment
		 * cannot answer: it reports whatever real wp_downloads the bootstrap left
		 * behind, whatever uninstall did. That the uninstaller issued the drop is
		 * the part that is actually about this plugin, and it is answerable.
		 */
		$dropped = false;
		$watch   = static function ( $query ) use ( &$dropped ) {
			if ( false !== stripos( $query, 'DROP' ) && false !== stripos( $query, 'downloads' ) ) {
				$dropped = true;
			}

			return $query;
		};

		add_filter( 'query', $watch );
		$this->uninstall();
		remove_filter( 'query', $watch );

		$this->assertTrue( $dropped, 'uninstall issued no DROP for the downloads table' );
	}

	public function test_the_uninstaller_reads_its_row_list_from_the_options_class() {
		$source = $this->code( 'uninstall.php' );

		$this->assertStringContainsString( 'legacy_map()', $source, 'the uninstaller and the migration must never disagree about which rows belong to the plugin' );
		$this->assertStringContainsString( 'legacy_extra_rows()', $source );
		$this->assertStringContainsString( 'legacy_structured_rows()', $source );

		// And the one exception it has to apply, from the same class, so that
		// nobody reinstates the shared rows by re-deriving the list here.
		$this->assertStringContainsString( 'legacy_shared_rows()', $source, 'the uninstaller must subtract the rows six sibling plugins are still reading' );
	}

	/**
	 * The rows this plugin does not own stay behind.
	 *
	 * The stats_display and stats_mostlimit rows were shared with WP-Stats and
	 * five others, and up to six of them may not have upgraded yet and be reading
	 * them still. §13.2 splits the jobs: the migration deletes a shared row as it
	 * has folded it in, and uninstall leaves it alone. Deleting WP-DownloadManager
	 * was reconfiguring every sibling's WP-Stats blocks with nothing said
	 * anywhere -- and neither row was visible as a problem from inside this
	 * plugin, which is why both lived on the migration's lists unremarked.
	 *
	 * @return void
	 */
	public function test_the_shared_stats_rows_survive_uninstall() {
		$display = array(
			'downloads' => 1,
			'polls'     => 1,
		);

		update_option( 'stats_display', $display );
		update_option( 'stats_mostlimit', 15 );

		$this->uninstall();

		$this->assertSame( $display, get_option( 'stats_display' ), 'six sibling plugins read stats_display' );
		$this->assertSame( '15', (string) get_option( 'stats_mostlimit' ), 'and stats_mostlimit with it' );
	}

	public function test_the_uninstaller_handles_a_network_site_by_site() {
		$source = $this->code( 'uninstall.php' );

		$this->assertStringContainsString( 'is_multisite()', $source );
		$this->assertStringContainsString( 'switch_to_blog(', $source );
		$this->assertStringContainsString( 'restore_current_blog()', $source );
	}

	public function test_the_uninstaller_does_not_use_the_function_core_deprecated() {
		$source = $this->code( 'uninstall.php' );

		$this->assertStringNotContainsString( 'wp_get_sites(', $source, 'deprecated in WordPress 4.6 and capped at 100 sites, so a larger network uninstalls in part' );
		$this->assertStringContainsString( 'get_sites(', $source );
	}

	public function test_the_uninstaller_lifts_the_hundred_site_cap() {
		$source = $this->code( 'uninstall.php' );

		$this->assertMatchesRegularExpression(
			"/'number'\s*=>\s*0/",
			$source,
			'without this a network larger than a hundred sites keeps its options and tables, and uninstall still reports success'
		);
	}

	public function test_the_uninstaller_restores_the_blog_inside_the_loop() {
		$source = $this->code( 'uninstall.php' );

		$switch  = strpos( $source, 'switch_to_blog(' );
		$restore = strpos( $source, 'restore_current_blog()' );
		$closing = strpos( $source, '}', $restore );

		$this->assertGreaterThan( $switch, $restore, 'switch_to_blog() pushes onto a stack, so the restore belongs inside the loop' );
		$this->assertNotFalse( $closing, 'The loop has a closing brace to compare against, or the ordering below means nothing.' );
	}

	public function test_the_table_is_dropped_once_rather_than_once_per_option_row() {
		$source = $this->code( 'uninstall.php' );

		$this->assertSame( 1, substr_count( $source, 'Install::drop_table()' ), 'the old code dropped it inside the option loop, which worked only by accident' );
		$this->assertStringNotContainsString( 'DROP TABLE', $source, 'the statement itself belongs in includes/, where the plugin does the rest of its table work' );
	}

	public function test_uninstalling_a_site_that_never_finished_installing_is_harmless() {
		global $wpdb;

		$table = $this->table();
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
		delete_option( WP_DownloadManager_Options::OPTION );
		delete_option( WP_DownloadManager_Options::VERSION );

		$this->uninstall();

		$this->assertFalse( get_option( WP_DownloadManager_Options::OPTION, false ), 'Uninstalling a site that never finished installing leaves no row behind.' );
	}
}
