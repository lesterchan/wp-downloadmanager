<?php
/**
 * The consolidated option row and the two version markers.
 *
 * @package WP-DownloadManager
 */

/**
 * Reading, writing and defaulting wp_downloadmanager_options.
 */
class WP_DownloadManager_Options_Test extends WP_DownloadManager_TestCase {

	public function test_the_settings_row_is_the_prefixed_name() {
		$this->assertSame( 'wp_downloadmanager_options', WP_DownloadManager_Options::OPTION, 'the settings row carries the plugin prefix' );
	}

	public function test_the_marker_row_is_the_prefixed_name() {
		$this->assertSame( 'wp_downloadmanager_version', WP_DownloadManager_Options::VERSION, 'the marker row carries the plugin prefix' );
	}

	public function test_the_settings_group_is_the_same_string_as_the_settings_row() {
		$this->assertSame(
			WP_DownloadManager_Options::OPTION,
			WP_DownloadManager_Settings::GROUP,
			'the settings group and the option row are one name, so register_setting() and the form agree'
		);
	}

	public function test_a_missing_row_falls_back_to_the_defaults() {
		delete_option( WP_DownloadManager_Options::OPTION );
		WP_DownloadManager_Options::flush();

		$this->assertSame( 1, WP_DownloadManager_Options::get( 'method' ), 'a fresh install reads the shipped defaults' );
		$this->assertSame( 20, WP_DownloadManager_Options::get( 'sort.perpage' ), 'nested defaults come through too' );
	}

	public function test_a_corrupt_row_falls_back_to_the_defaults() {
		update_option( WP_DownloadManager_Options::OPTION, 'not an array at all' );
		WP_DownloadManager_Options::flush();

		$this->assertIsArray( WP_DownloadManager_Options::all(), 'a non-array row must not become the settings' );
		$this->assertSame( 1, WP_DownloadManager_Options::get( 'nice_permalink' ), 'the defaults still apply' );
	}

	public function test_a_dot_path_reads_a_nested_value() {
		$this->assertSame( 'file_name', WP_DownloadManager_Options::get( 'sort.by' ), 'two levels is all the structure has' );
		$this->assertSame( 'file_date', WP_DownloadManager_Options::get( 'rss.sortby' ), 'the feed settings nest the same way' );
	}

	public function test_an_unknown_path_returns_the_fallback() {
		$this->assertSame( 'fallback', WP_DownloadManager_Options::get( 'no.such.path', 'fallback' ), 'an absent path returns what the caller asked for' );
		$this->assertNull( WP_DownloadManager_Options::get( 'nope' ), 'with no fallback the answer is null' );
	}

	public function test_setting_a_nested_value_persists_it() {
		WP_DownloadManager_Options::set( 'sort.perpage', 7 );
		WP_DownloadManager_Options::flush();

		$this->assertSame( 7, WP_DownloadManager_Options::get( 'sort.perpage' ), 'the write reached the database' );
		$this->assertSame( 'file_name', WP_DownloadManager_Options::get( 'sort.by' ), 'and left its siblings alone' );
	}

	public function test_setting_a_path_that_does_not_exist_yet_creates_it() {
		WP_DownloadManager_Options::set( 'sort.direction_hint', 'up' );

		$this->assertSame( 'up', WP_DownloadManager_Options::get( 'sort.direction_hint' ), 'missing levels are created on the way down' );
	}

	public function test_the_category_list_is_not_renumbered_by_the_defaults_merge() {
		WP_DownloadManager_Options::save( array( 'categories' => array( '', 'Alpha', 'Beta' ) ) );

		$this->assertSame(
			array( '', 'Alpha', 'Beta' ),
			WP_DownloadManager_Options::get( 'categories' ),
			'a list stored under a key that also has a default must not be merged element by element'
		);
	}

	public function test_a_single_template_reads_back_as_a_string() {
		$this->assertIsString( WP_DownloadManager_Options::template( 'header' ), 'single templates are strings' );
		$this->assertNotSame( '', WP_DownloadManager_Options::template( 'header' ), 'and the stock one is not empty' );
	}

	public function test_a_paired_template_reads_back_by_permission_index() {
		$permitted = WP_DownloadManager_Options::template( 'listing', 0 );
		$denied    = WP_DownloadManager_Options::template( 'listing', 1 );

		$this->assertNotSame( $permitted, $denied, 'the two halves of a pair are different markup' );
		$this->assertStringContainsString( '%FILE_DOWNLOAD_URL%', $permitted, 'the permitted half links to the file' );
		$this->assertStringNotContainsString( '%FILE_DOWNLOAD_URL%', $denied, 'the denied half must not' );
	}

	public function test_asking_a_single_template_for_an_index_ignores_it() {
		$this->assertSame(
			WP_DownloadManager_Options::template( 'header', 0 ),
			WP_DownloadManager_Options::template( 'header', 1 ),
			'an index means nothing to a template that is not a pair'
		);
	}

	public function test_the_markers_normalise_a_missing_row() {
		delete_option( WP_DownloadManager_Options::VERSION );

		$this->assertSame(
			array(
				'plugin' => '',
				'db'     => '0',
			),
			WP_DownloadManager_Options::markers(),
			'a site that has never run the plugin reads as schema zero'
		);
	}

	public function test_the_markers_normalise_a_corrupt_row() {
		update_option( WP_DownloadManager_Options::VERSION, '2' );

		$markers = WP_DownloadManager_Options::markers();

		$this->assertSame( array( 'plugin', 'db' ), array_keys( $markers ), 'the shape is fixed whatever is stored' );
		$this->assertSame( '0', $markers['db'], 'a scalar left over from an older layout reads as schema zero' );
	}

	public function test_both_markers_are_written_in_one_call() {
		WP_DownloadManager_Options::save_markers( '2.0.0', '3' );

		$this->assertSame(
			array(
				'plugin' => '2.0.0',
				'db'     => '3',
			),
			get_option( WP_DownloadManager_Options::VERSION ),
			'one update_option() for both, so a half-finished upgrade cannot record itself as complete'
		);
	}

	public function test_the_markers_are_not_in_the_settings_row() {
		WP_DownloadManager_Options::save_markers( '2.0.0', '3' );

		$settings = get_option( WP_DownloadManager_Options::OPTION );

		$this->assertArrayNotHasKey( 'plugin', $settings, 'the markers live in their own row' );
		$this->assertArrayNotHasKey( 'db', $settings, 'the markers live in their own row' );
	}

	public function test_saving_the_settings_cannot_disturb_the_markers() {
		WP_DownloadManager_Options::save_markers( '2.0.0', '3' );
		WP_DownloadManager_Options::save( WP_DownloadManager_Settings::sanitize( array( 'page_url' => 'https://example.com/d' ) ) );

		$this->assertSame(
			array(
				'plugin' => '2.0.0',
				'db'     => '3',
			),
			WP_DownloadManager_Options::markers(),
			'this is the wp-useronline bug: with a separate row it is impossible by construction'
		);
	}

	public function test_the_stats_settings_belong_to_this_plugin() {
		$defaults = WP_DownloadManager_Options::defaults();

		$this->assertArrayHasKey( 'stats_display', $defaults, 'the WP-Stats toggle is one of this plugin\'s own settings now' );
		$this->assertArrayHasKey( 'stats_most_limit', $defaults, 'so is the row limit' );
		$this->assertSame( 1, $defaults['stats_display'], 'a fresh install contributes a section by default' );
		$this->assertSame( 10, $defaults['stats_most_limit'], 'ten rows by default' );
	}

	public function test_the_legacy_map_covers_every_template() {
		$map = WP_DownloadManager_Options::legacy_map();

		foreach ( WP_DownloadManager_Template::keys() as $key ) {
			$this->assertArrayHasKey( 'download_template_' . $key, $map, 'the ' . $key . ' template had a row of its own before 2.0.0' );
		}
	}

	public function test_the_flush_drops_the_runtime_cache() {
		WP_DownloadManager_Options::get( 'method' );

		update_option( WP_DownloadManager_Options::OPTION, array( 'method' => 0 ) );
		$this->assertSame( 1, WP_DownloadManager_Options::get( 'method' ), 'the cached value is still the one the page started with' );

		WP_DownloadManager_Options::flush();
		$this->assertSame( 0, WP_DownloadManager_Options::get( 'method' ), 'and the flush is what picks the new one up' );
	}

	/**
	 * The write path creates the row even when the value equals the default.
	 *
	 * Pinned at the door rather than through the migration, so the guarantee
	 * belongs to save() rather than to whatever the migration happens to compute.
	 * The migration tests can only see this while their fixtures keep producing a
	 * value equal to the defaults; this one cannot stop seeing it.
	 *
	 * @return void
	 */
	public function test_save_creates_the_row_when_the_value_equals_the_registered_default() {
		delete_option( WP_DownloadManager_Options::OPTION );
		WP_DownloadManager_Options::flush();

		WP_DownloadManager_Settings::register();

		// The precondition the defect needs: a bare read of an absent row answers
		// with the defaults, so update_option() alone compares equal and declines
		// to write. Core's add_option() fallback sits below that comparison.
		$this->assertSame(
			WP_DownloadManager_Options::defaults(),
			get_option( WP_DownloadManager_Options::OPTION ),
			'the registered default is what an absent row reads back as'
		);

		$this->assertTrue( WP_DownloadManager_Options::save( WP_DownloadManager_Options::defaults() ), 'save() reports that it wrote' );
		$this->assertIsArray( get_option( WP_DownloadManager_Options::OPTION, false ), 'and the row is really there, read raw' );
	}

	/**
	 * The shipped defaults survive the sanitiser unchanged.
	 *
	 * The assertion whose absence would let a typo decide whether the test above
	 * means anything. A sanitiser that alters one character of the defaults makes
	 * the written value differ from them, update_option() finds a difference and
	 * writes the row -- so the equal-value case stops being exercised and the test
	 * above passes for a reason unrelated to the code.
	 *
	 * @return void
	 */
	public function test_the_shipped_defaults_survive_sanitisation_unchanged() {
		WP_DownloadManager_Settings::register();

		$defaults = WP_DownloadManager_Options::defaults();

		$this->assertSame(
			$defaults,
			sanitize_option( WP_DownloadManager_Options::OPTION, $defaults ),
			'the registered sanitize callback leaves the defaults alone'
		);
	}
}
