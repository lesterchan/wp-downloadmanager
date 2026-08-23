<?php
/**
 * The WP-Stats contract, from this side of it.
 *
 * WP-Stats fires wp_stats_sections and this plugin answers with one entry. The
 * assertions here are deliberately about the shape of that entry rather than
 * about WP-Stats itself, because WP-Stats is not installed while these run -
 * which is the point: the class has to be inert without it and correct with it.
 *
 * @package WP-DownloadManager
 */

/**
 * WP_DownloadManager_WPStats against section 13.
 */
class WP_DownloadManager_WPStats_Test extends WP_DownloadManager_TestCase {

	/**
	 * Fire the filter the way WP-Stats does.
	 *
	 * @return array
	 */
	protected function sections() {
		return apply_filters( 'wp_stats_sections', array() );
	}

	public function test_the_class_hooks_the_one_filter_wp_stats_owns() {
		$this->assertNotFalse(
			has_filter( 'wp_stats_sections', array( 'WP_DownloadManager_WPStats', 'register_section' ) ),
			'the contract is one filter, hooked unconditionally'
		);
	}

	public function test_the_class_is_inert_without_wp_stats() {
		// Nothing fires the filter when WP-Stats is absent, so the only way this
		// class can misbehave is by doing something at load time. It does not:
		// init() adds a filter and stops.
		$this->assertStringNotContainsString( 'class_exists', $this->code( 'includes/class-wp-downloadmanager-wpstats.php' ), 'no probing between plugins in either direction' );
		$this->assertStringNotContainsString( 'function_exists', $this->code( 'includes/class-wp-downloadmanager-wpstats.php' ), 'no probing between plugins in either direction' );
	}

	public function test_the_entry_is_keyed_by_the_plugin_slug_with_underscores() {
		$sections = $this->sections();

		$this->assertArrayHasKey( 'wp_downloadmanager', $sections, 'one entry, keyed by this plugin' );
		$this->assertCount( 1, $sections, 'a contributor adds exactly one entry' );
	}

	public function test_the_entry_has_exactly_the_three_contract_keys() {
		$section = $this->sections()['wp_downloadmanager'];

		$this->assertSame( array( 'title', 'priority', 'render' ), array_keys( $section ), 'section 13.1 pins the shape; do not improvise' );
	}

	public function test_the_title_is_a_translated_non_empty_string() {
		$section = $this->sections()['wp_downloadmanager'];

		$this->assertIsString( $section['title'], 'the title is a string' );
		$this->assertNotSame( '', $section['title'], 'wp-stats skips an entry with an empty title' );
		$this->assertSame( 'Downloads', $section['title'], 'The section is titled.' );
	}

	public function test_the_priority_is_an_integer() {
		$section = $this->sections()['wp_downloadmanager'];

		$this->assertIsInt( $section['priority'], 'wp-stats sorts on this' );
		$this->assertSame( 10, $section['priority'], 'ten is the default position; nothing here earns a place ahead of a sibling' );
	}

	public function test_the_renderer_is_callable_and_takes_no_arguments() {
		$section = $this->sections()['wp_downloadmanager'];

		$this->assertIsCallable( $section['render'], 'wp-stats skips an entry whose render is not callable' );

		$reflection = new ReflectionMethod( 'WP_DownloadManager_WPStats', 'render' );
		$this->assertSame( 0, $reflection->getNumberOfParameters(), 'render takes no arguments' );
	}

	public function test_the_renderer_echoes_rather_than_returning() {
		ob_start();
		$returned = WP_DownloadManager_WPStats::render();
		$printed  = ob_get_clean();

		$this->assertNull( $returned, 'wp-stats assembles its page inside ob_start(), so a returned string would be dropped' );
		$this->assertNotSame( '', $printed, 'the body is echoed' );
	}

	public function test_the_renderer_does_not_echo_the_section_heading() {
		ob_start();
		WP_DownloadManager_WPStats::render();
		$printed = ob_get_clean();

		$this->assertStringNotContainsString(
			'<h2',
			$printed,
			'wp-stats echoes the title from its own listener; a contributor that printed one too would double it'
		);
	}

	public function test_opting_out_contributes_nothing_at_all() {
		WP_DownloadManager_Options::set( 'stats_display', 0 );

		$this->assertSame( array(), $this->sections(), 'an opted-out contributor returns the sections untouched' );
	}

	public function test_opting_out_does_not_add_an_empty_entry() {
		WP_DownloadManager_Options::set( 'stats_display', 0 );

		$this->assertArrayNotHasKey(
			'wp_downloadmanager',
			$this->sections(),
			'wp-stats would print a heading over an empty body'
		);
	}

	public function test_a_sibling_entry_already_in_the_array_survives() {
		$existing = array(
			'wp_polls' => array(
				'title'    => 'Polls',
				'priority' => 10,
				'render'   => '__return_true',
			),
		);

		$sections = WP_DownloadManager_WPStats::register_section( $existing );

		$this->assertArrayHasKey( 'wp_polls', $sections, 'a contributor adds to the array, it does not replace it' );
		$this->assertArrayHasKey( 'wp_downloadmanager', $sections, 'This plugin entry is added alongside the sibling already there.' );
	}

	public function test_a_non_array_from_an_earlier_filter_does_not_fatal() {
		$sections = WP_DownloadManager_WPStats::register_section( 'nonsense' );

		$this->assertIsArray( $sections, 'a badly behaved sibling must not take this plugin down with it' );
		$this->assertArrayHasKey( 'wp_downloadmanager', $sections, 'A non-array from an earlier filter is replaced rather than fatal.' );
	}

	public function test_the_class_reads_only_this_plugins_option_row() {
		$source = $this->code( 'includes/class-wp-downloadmanager-wpstats.php' );

		$this->assertStringNotContainsString( "get_option( 'stats_display'", $source, 'the shared rows are gone; a contributor reads its own settings' );
		$this->assertStringNotContainsString( "get_option( 'stats_mostlimit'", $source, 'the shared rows are gone; a contributor reads its own settings' );
		$this->assertStringNotContainsString( 'wp_stats_options', $source, 'and never reaches into wp-stats\' own row either' );
	}

	public function test_the_row_limit_comes_from_this_plugins_settings() {
		WP_DownloadManager_Options::set( 'stats_most_limit', 3 );

		$this->assertSame( 3, WP_DownloadManager_WPStats::most_limit(), 'stats_most_limit is namespaced inside this plugin\'s options' );
	}

	public function test_a_zero_row_limit_is_lifted_to_one() {
		WP_DownloadManager_Options::set( 'stats_most_limit', 0 );

		$this->assertSame( 1, WP_DownloadManager_WPStats::most_limit(), 'a limit of zero would render an empty list forever' );
	}

	public function test_the_body_reports_the_library_totals() {
		ob_start();
		WP_DownloadManager_WPStats::render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'files were added', $html, 'the totals block is part of the body' );
		$this->assertStringContainsString( 'hits were generated', $html, 'The body reports the library totals.' );
	}

	public function test_the_body_lists_recent_and_most_downloaded() {
		WP_DownloadManager_Options::set( 'stats_most_limit', 2 );

		ob_start();
		WP_DownloadManager_WPStats::render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Most Recent Downloads', $html, 'It lists the recent downloads.' );
		$this->assertStringContainsString( 'Most Downloaded Files', $html, 'And the most downloaded.' );
		$this->assertStringContainsString( 'The Manual', $html, 'and the lists have real rows in them' );
	}

	public function test_the_body_never_lists_a_hidden_file() {
		ob_start();
		WP_DownloadManager_WPStats::render();
		$html = ob_get_clean();

		$this->assertStringNotContainsString( 'Hidden File', $html, 'permission -2 means nobody sees it, WP-Stats included' );
	}

	public function test_the_body_survives_an_empty_library() {
		global $wpdb;

		$table = $this->table();
		$wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', $table ) );

		ob_start();
		WP_DownloadManager_WPStats::render();
		$html = ob_get_clean();

		// SUM() is NULL on an empty table, which _n() and number_format_i18n()
		// are both deprecated for on PHP 8.1 and later.
		$this->assertStringNotContainsString( 'Deprecated', $html, 'An empty library renders without a deprecation notice.' );
		$this->assertStringContainsString( 'N/A', $html, 'the lists say so rather than rendering nothing' );
	}

	public function test_the_body_keeps_its_markup_through_escaping() {
		ob_start();
		WP_DownloadManager_WPStats::render();
		$html = ob_get_clean();

		$this->assertStringContainsString( '<li>', $html, 'the list items survive the allow list' );
		$this->assertStringContainsString( '<a href=', $html, 'and so do the download links' );
	}

	public function test_a_template_carrying_an_icon_keeps_it_through_escaping() {
		WP_DownloadManager_Options::set( 'templates.most', array( '<li>%FILE_ICON% %FILE_NAME%</li>', '<li>%FILE_NAME%</li>' ) );

		ob_start();
		WP_DownloadManager_WPStats::render();
		$html = ob_get_clean();

		$this->assertStringContainsString(
			'<svg',
			$html,
			'core\'s post allow list knows nothing about SVG, so escaping with it would delete every icon'
		);
		$this->assertStringContainsString( '<use href="#wp-downloadmanager-icon-', $html, 'including the reference into the sprite' );
	}
}
