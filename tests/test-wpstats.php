<?php
/**
 * WP-Stats integration.
 *
 * WP-Stats is a separate plugin that may or may not be installed, and its own
 * 3.0.0 consolidated the option rows these panels used to read directly. So
 * every accessor here has two paths - through WP-Stats' helper API when it is
 * present, and through the legacy option row when it is not - and the suite
 * runs without WP-Stats installed, which exercises the fallback.
 *
 * @package WP-WP_DownloadManager
 */

/**
 * The download panels WP-Stats renders.
 */
class Test_WPStats extends WP_DownloadManager_TestCase {

	/**
	 * Put the legacy toggle row back to a known state.
	 */
	public function set_up() {
		parent::set_up();

		update_option(
			'stats_display',
			array(
				'downloads'        => 1,
				'recent_downloads' => 1,
				'downloaded_most'  => 1,
			)
		);
		update_option( 'stats_mostlimit', 5 );
	}

	/**
	 * The three toggles this plugin owns are registered as defaults.
	 *
	 * They were never registered before, so the panels only existed once
	 * somebody had saved the WP-Stats options screen.
	 */
	public function test_toggles_are_registered_as_defaults() {
		$defaults = WP_DownloadManager_WPStats::defaults( array() );

		$this->assertSame( 1, $defaults['downloads'] );
		$this->assertSame( 1, $defaults['recent_downloads'] );
		$this->assertSame( 1, $defaults['downloaded_most'] );
	}

	/**
	 * WP-Stats' own defaults win over ours.
	 */
	public function test_existing_defaults_are_not_overridden() {
		$defaults = WP_DownloadManager_WPStats::defaults( array( 'downloads' => 0 ) );

		$this->assertSame( 0, $defaults['downloads'] );
	}

	/**
	 * A toggle reads from the legacy row when WP-Stats is absent.
	 */
	public function test_toggle_reads_the_legacy_row() {
		$this->assertTrue( WP_DownloadManager_WPStats::enabled( 'downloads' ) );

		update_option( 'stats_display', array( 'downloads' => 0 ) );
		$this->assertFalse( WP_DownloadManager_WPStats::enabled( 'downloads' ) );
	}

	/**
	 * A missing toggle is off rather than a PHP notice.
	 */
	public function test_missing_toggle_is_off() {
		delete_option( 'stats_display' );

		$this->assertFalse( WP_DownloadManager_WPStats::enabled( 'downloads' ) );
		$this->assertFalse( WP_DownloadManager_WPStats::enabled( 'nonexistent' ) );
	}

	/**
	 * The limit falls back to the legacy row too.
	 */
	public function test_limit_reads_the_legacy_row() {
		$this->assertSame( 5, WP_DownloadManager_WPStats::limit() );
	}

	/**
	 * The general panel reports the totals across the whole table.
	 */
	public function test_general_panel_reports_totals() {
		$content = WP_DownloadManager_WPStats::page_general( '' );

		$this->assertStringContainsString( 'WP-WP_DownloadManager', $content );
		// Five fixtures, 126 hits between them, just over a megabyte.
		$this->assertStringContainsString( '5', $content );
		$this->assertStringContainsString( '126', $content );
		$this->assertStringContainsString( '1.0 MiB', $content );
	}

	/**
	 * Each panel is silent when its toggle is off.
	 *
	 * @dataProvider panel_provider
	 *
	 * @param string $method Panel method.
	 * @param string $toggle Toggle key.
	 */
	public function test_panel_respects_its_toggle( $method, $toggle ) {
		update_option( 'stats_display', array( $toggle => 0 ) );

		$this->assertSame(
			'existing',
			WP_DownloadManager_WPStats::$method( 'existing' ),
			$method . ' should add nothing when switched off'
		);
	}

	/**
	 * Each panel appends when its toggle is on.
	 *
	 * @dataProvider panel_provider
	 *
	 * @param string $method Panel method.
	 * @param string $toggle Toggle key.
	 */
	public function test_panel_appends_when_enabled( $method, $toggle ) {
		update_option( 'stats_display', array( $toggle => 1 ) );

		$content = WP_DownloadManager_WPStats::$method( 'existing' );

		$this->assertStringStartsWith( 'existing', $content );
		$this->assertGreaterThan( strlen( 'existing' ), strlen( $content ) );
	}

	/**
	 * The three front-end panels.
	 *
	 * @return array
	 */
	public function panel_provider() {
		return array(
			'general' => array( 'page_general', 'downloads' ),
			'recent'  => array( 'page_recent', 'recent_downloads' ),
			'most'    => array( 'page_most', 'downloaded_most' ),
		);
	}

	/**
	 * The recent panel lists files, newest first, and skips hidden ones.
	 */
	public function test_recent_panel_lists_files() {
		$content = WP_DownloadManager_WPStats::page_recent( '' );

		$this->assertStringContainsString( 'The Manual', $content );
		$this->assertStringNotContainsString( 'Hidden File', $content );
		$this->assertStringContainsString( '<ul>', $content );
		$this->assertStringContainsString( '</ul>', $content );
	}

	/**
	 * The most-downloaded panel orders by hits.
	 */
	public function test_most_panel_orders_by_hits() {
		$content = WP_DownloadManager_WPStats::page_most( '' );

		$this->assertLessThan(
			strpos( $content, 'Remote Bundle' ),
			strpos( $content, 'The Manual' ),
			'12 hits should sort above 7'
		);
	}

	/**
	 * The panels honour the configured limit.
	 */
	public function test_panels_honour_the_limit() {
		update_option( 'stats_mostlimit', 1 );

		$content = WP_DownloadManager_WPStats::page_most( '' );

		$this->assertSame( 1, substr_count( $content, '<li>' ) );
	}

	/**
	 * The options-screen checkboxes render for each toggle.
	 *
	 * @dataProvider admin_checkbox_provider
	 *
	 * @param string $method Checkbox method.
	 * @param string $value  Toggle key it renders.
	 */
	public function test_admin_checkbox_renders( $method, $value ) {
		$html = WP_DownloadManager_WPStats::$method( '' );

		$this->assertStringContainsString( 'type="checkbox"', $html );
		$this->assertStringContainsString( 'value="' . $value . '"', $html );
		$this->assertStringContainsString( 'id="wpstats_' . $value . '"', $html );
		// It reflects the stored state.
		$this->assertStringContainsString( 'checked', $html );
	}

	/**
	 * The three checkboxes.
	 *
	 * @return array
	 */
	public function admin_checkbox_provider() {
		return array(
			'general' => array( 'admin_general', 'downloads' ),
			'recent'  => array( 'admin_recent', 'recent_downloads' ),
			'most'    => array( 'admin_most', 'downloaded_most' ),
		);
	}

	/**
	 * An unchecked toggle renders unchecked.
	 */
	public function test_admin_checkbox_reflects_an_off_toggle() {
		update_option( 'stats_display', array( 'downloads' => 0 ) );

		$this->assertStringNotContainsString( 'checked', WP_DownloadManager_WPStats::admin_general( '' ) );
	}

	/**
	 * Every filter WP-Stats offers is hooked up.
	 */
	public function test_filters_are_registered() {
		WP_DownloadManager_WPStats::register();

		$filters = array(
			'wp_stats_display_defaults',
			'wp_stats_page_admin_plugins',
			'wp_stats_page_admin_recent',
			'wp_stats_page_admin_most',
			'wp_stats_page_plugins',
			'wp_stats_page_recent',
			'wp_stats_page_most',
		);

		foreach ( $filters as $filter ) {
			$this->assertNotFalse(
				has_filter( $filter ),
				$filter . ' should have a callback'
			);
		}
	}

	/**
	 * The panels survive an empty table.
	 */
	public function test_panels_with_no_files() {
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->downloads}" ); // phpcs:ignore WordPress.DB

		$this->assertStringContainsString( 'N/A', WP_DownloadManager_WPStats::page_recent( '' ) );
		$this->assertStringContainsString( 'N/A', WP_DownloadManager_WPStats::page_most( '' ) );

		// SUM() is NULL with no rows, and _n() and number_format() are both
		// deprecated for null on PHP 8.1 and later. The panel must report zeroes.
		$general = WP_DownloadManager_WPStats::page_general( '' );
		$this->assertStringContainsString( 'WP-WP_DownloadManager', $general );
		$this->assertStringContainsString( '<strong>0</strong> files were added.', $general );
		$this->assertStringContainsString( '<strong>0</strong> hits were generated.', $general );
	}

	/**
	 * The helper API is preferred when WP-Stats provides it.
	 *
	 * The suite runs without WP-Stats, so the functions are declared here to
	 * prove the branch is taken. They cannot be undeclared afterwards, which is
	 * why this runs in its own process.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_helper_api_is_preferred_when_available() {
		if ( ! function_exists( 'wp_stats_display_enabled' ) ) {
			/**
			 * Stand-in for WP-Stats 3.0.0's accessor.
			 *
			 * @param string $key Toggle key.
			 * @return bool
			 */
			function wp_stats_display_enabled( $key ) { // phpcs:ignore
				return 'downloads' === $key;
			}

			/**
			 * Stand-in for WP-Stats 3.0.0's limit accessor.
			 *
			 * @return int
			 */
			function wp_stats_most_limit() { // phpcs:ignore
				return 42;
			}
		}

		// The legacy row says otherwise; the helper API must win.
		update_option( 'stats_display', array( 'downloads' => 0 ) );
		update_option( 'stats_mostlimit', 5 );

		$this->assertTrue( WP_DownloadManager_WPStats::enabled( 'downloads' ) );
		$this->assertFalse( WP_DownloadManager_WPStats::enabled( 'recent_downloads' ) );
		$this->assertSame( 42, WP_DownloadManager_WPStats::limit() );
	}
}
