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

		$this->assertFalse( get_option( WP_DownloadManager_Options::OPTION, false ) );
	}

	public function test_the_marker_row_is_removed() {
		WP_DownloadManager_Options::save_markers( '2.0.0', '3' );

		$this->uninstall();

		$this->assertFalse( get_option( WP_DownloadManager_Options::VERSION, false ) );
	}

	public function test_every_legacy_row_is_removed_even_if_the_migration_never_ran() {
		$names = array_merge(
			array_keys( WP_DownloadManager_Options::legacy_map() ),
			array_values( WP_DownloadManager_Options::legacy_structured_rows() ),
			WP_DownloadManager_Options::legacy_extra_rows()
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

		$this->assertFalse( get_option( 'widget_downloads', false ) );
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
		global $wpdb;

		$this->uninstall();

		$this->assertNull( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $this->table() ) ) );
	}

	public function test_the_uninstaller_reads_its_row_list_from_the_options_class() {
		$source = $this->code( 'uninstall.php' );

		$this->assertStringContainsString( 'legacy_map()', $source, 'the uninstaller and the migration must never disagree about which rows belong to the plugin' );
		$this->assertStringContainsString( 'legacy_extra_rows()', $source );
		$this->assertStringContainsString( 'legacy_structured_rows()', $source );
	}

	public function test_the_uninstaller_handles_a_network_site_by_site() {
		$source = $this->code( 'uninstall.php' );

		$this->assertStringContainsString( 'is_multisite()', $source );
		$this->assertStringContainsString( 'switch_to_blog(', $source );
		$this->assertStringContainsString( 'restore_current_blog()', $source );
	}

	public function test_the_uninstaller_does_not_use_the_function_core_removed() {
		$source = $this->code( 'uninstall.php' );

		$this->assertStringNotContainsString( 'wp_get_sites(', $source, 'removed in WordPress 5.1, so this used to fatal on a network' );
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
		$this->assertNotFalse( $closing );
	}

	public function test_the_table_is_dropped_once_rather_than_once_per_option_row() {
		$source = $this->code( 'uninstall.php' );

		$this->assertSame( 1, substr_count( $source, 'DROP TABLE' ), 'the old code dropped it inside the option loop, which worked only by accident' );
	}

	public function test_uninstalling_a_site_that_never_finished_installing_is_harmless() {
		global $wpdb;

		$table = $this->table();
		$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
		delete_option( WP_DownloadManager_Options::OPTION );
		delete_option( WP_DownloadManager_Options::VERSION );

		$this->uninstall();

		$this->assertFalse( get_option( WP_DownloadManager_Options::OPTION, false ) );
	}
}
