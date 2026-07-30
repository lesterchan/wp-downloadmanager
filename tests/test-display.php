<?php
/**
 * The downloads page, embedded downloads, the stats lists and the feed.
 *
 * @package WP-DownloadManager
 */

/**
 * Everything WP_DownloadManager_Display renders.
 */
class WP_DownloadManager_Display_Test extends WP_DownloadManager_TestCase {

	public function test_the_downloads_page_lists_the_visible_files() {
		$html = WP_DownloadManager_Display::downloads_page();

		$this->assertStringContainsString( 'The Manual', $html );
		$this->assertStringContainsString( 'Members Bundle', $html );
		$this->assertStringContainsString( 'Editor Notes', $html );
	}

	public function test_the_downloads_page_never_lists_a_hidden_file() {
		$html = WP_DownloadManager_Display::downloads_page();

		$this->assertStringNotContainsString( 'Hidden File', $html, 'permission -2 means nobody sees it' );
	}

	public function test_the_downloads_page_is_wrapped_in_one_root_element() {
		$html = WP_DownloadManager_Display::downloads_page();

		$this->assertStringStartsWith( '<div class="wp-downloadmanager">', $html, 'the stylesheet needs exactly one scope' );
		$this->assertStringEndsWith( '</div>', $html );
	}

	public function test_the_downloads_page_counts_and_totals_the_library() {
		$html = WP_DownloadManager_Display::downloads_page();

		// Four visible files: the hidden one is excluded from the counts too.
		$this->assertStringContainsString( '<strong>4 files</strong>', $html );
	}

	public function test_the_downloads_page_can_be_limited_to_one_category() {
		$html = WP_DownloadManager_Display::downloads_page( 2 );

		$this->assertStringContainsString( 'Editor Notes', $html );
		$this->assertStringNotContainsString( 'The Manual', $html, 'The Manual is in category 1' );
	}

	public function test_the_downloads_page_says_so_when_nothing_matches() {
		$html = WP_DownloadManager_Display::downloads_page( 99 );

		$this->assertStringContainsString( 'No Files Found', $html );
	}

	public function test_the_downloads_page_runs_through_the_prefixed_filter() {
		add_filter( 'wp_downloadmanager_page', fn( $out ) => $out . '<!-- filtered -->' );

		$this->assertStringContainsString( '<!-- filtered -->', WP_DownloadManager_Display::downloads_page() );
	}

	public function test_the_old_unprefixed_page_filter_is_gone() {
		add_filter( 'downloads_page', fn( $out ) => $out . '<!-- should never run -->' );

		$this->assertStringNotContainsString(
			'<!-- should never run -->',
			WP_DownloadManager_Display::downloads_page(),
			'the old name was dropped outright, with no deprecation shim'
		);
	}

	public function test_the_downloads_page_groups_by_category_when_asked() {
		WP_DownloadManager_Options::set( 'sort.group', 1 );

		$html = WP_DownloadManager_Display::downloads_page();

		$this->assertStringContainsString( 'id="downloadcat-1"', $html );
		$this->assertStringContainsString( 'id="downloadcat-2"', $html );
	}

	public function test_the_downloads_page_omits_the_category_headers_when_not_grouping() {
		WP_DownloadManager_Options::set( 'sort.group', 0 );

		$this->assertStringNotContainsString( 'id="downloadcat-', WP_DownloadManager_Display::downloads_page() );
	}

	public function test_the_downloads_page_pages_and_prints_paging_links() {
		WP_DownloadManager_Options::set( 'sort.perpage', 2 );

		$html = WP_DownloadManager_Display::downloads_page();

		$this->assertStringContainsString( 'wp-downloadmanager-paging', $html );
		$this->assertStringContainsString( 'Page 1 of 2', $html );
	}

	public function test_the_downloads_page_prints_no_paging_links_for_a_single_page() {
		$this->assertStringNotContainsString( 'wp-downloadmanager-paging', WP_DownloadManager_Display::downloads_page() );
	}

	public function test_a_search_term_narrows_the_listing() {
		$_GET['dl_search'] = 'Manual';

		$html = WP_DownloadManager_Display::downloads_page();

		$_GET = array();

		// The tags come out first. A matched term is wrapped in the plugin's own
		// highlight span, so the title of the file that matched is never a
		// contiguous string in the markup -- "The Manual" renders as
		// "The <span …>Manual</span>". That the highlight is applied is the next
		// test's business; this one is about which files the search left in.
		$text = wp_strip_all_tags( $html );

		$this->assertStringContainsString( 'The Manual', $text );
		$this->assertStringNotContainsString( 'Members Bundle', $text );
	}

	public function test_a_search_term_is_highlighted_under_the_plugins_own_class() {
		$this->assertSame(
			'The <span class="wp-downloadmanager-highlight">Manual</span>',
			WP_DownloadManager_Display::search_highlight( 'Manual', 'The Manual' )
		);
	}

	public function test_a_search_term_of_regex_metacharacters_does_not_blank_the_text() {
		$this->assertSame(
			'The Manual',
			WP_DownloadManager_Display::search_highlight( '(', 'The Manual' ),
			'preg_replace() returns null on a failed compile, which used to blank the file name'
		);
	}

	public function test_an_empty_search_term_leaves_the_text_alone() {
		$this->assertSame( 'The Manual', WP_DownloadManager_Display::search_highlight( '', 'The Manual' ) );
	}

	public function test_a_category_name_survives_its_category_being_deleted() {
		$this->assertSame( '', WP_DownloadManager_Display::category_name( array( '', 'General' ), 9 ), 'a file pointing at a deleted category used to be an undefined-key warning' );
	}

	public function test_a_category_name_is_unslashed() {
		$this->assertSame( "O'Reilly", WP_DownloadManager_Display::category_name( array( '', "O\\'Reilly" ), 1 ) );
	}

	public function test_snippet_text_truncates_at_the_budget() {
		$this->assertSame( 'The Ma...', WP_DownloadManager_Display::snippet_text( 'The Manual', 6 ) );
	}

	public function test_snippet_text_leaves_a_short_string_alone() {
		$this->assertSame( 'Short', WP_DownloadManager_Display::snippet_text( 'Short', 40 ) );
	}

	public function test_snippet_text_with_no_budget_truncates_nothing() {
		$this->assertSame( 'The Manual', WP_DownloadManager_Display::snippet_text( 'The Manual', 0 ) );
	}

	public function test_the_sprite_defines_a_symbol_for_every_family() {
		$sprite = WP_DownloadManager_Display::sprite();

		foreach ( WP_DownloadManager_File::icon_families() as $family ) {
			$this->assertStringContainsString( 'id="wp-downloadmanager-icon-' . $family . '"', $sprite, $family . ' needs a symbol' );
		}
	}

	public function test_the_sprite_is_printed_once_per_request() {
		WP_DownloadManager_Display::reset_sprite();

		$first  = WP_DownloadManager_Display::icon( '/a.zip' );
		$second = WP_DownloadManager_Display::icon( '/b.zip' );

		$this->assertStringContainsString( '<symbol', $first, 'the first icon of the request carries the definitions' );
		$this->assertStringNotContainsString( '<symbol', $second, 'and every one after it does not' );
	}

	public function test_an_icon_references_the_family_of_its_extension() {
		WP_DownloadManager_Display::reset_sprite();
		WP_DownloadManager_Display::icon( '/first.zip' );

		$this->assertStringContainsString( '<use href="#wp-downloadmanager-icon-archive" />', WP_DownloadManager_Display::icon( '/bundle.zip' ) );
		$this->assertStringContainsString( '<use href="#wp-downloadmanager-icon-image" />', WP_DownloadManager_Display::icon( '/photo.png' ) );
		$this->assertStringContainsString( '<use href="#wp-downloadmanager-icon-file" />', WP_DownloadManager_Display::icon( '/thing.qqq' ) );
	}

	public function test_the_listing_carries_a_drawn_icon_rather_than_an_image() {
		$html = WP_DownloadManager_Display::downloads_page();

		$this->assertStringContainsString( 'wp-downloadmanager-icon', $html );
		$this->assertStringNotContainsString( '<img', $html, 'the plugin ships no images at all now' );
	}

	public function test_embedded_downloads_render_one_file() {
		$html = WP_DownloadManager_Display::download_embedded( 'file_id = ' . $this->ids['public'] );

		$this->assertStringContainsString( 'The Manual', $html );
		$this->assertStringNotContainsString( 'Members Bundle', $html );
	}

	public function test_embedded_downloads_return_an_empty_string_when_nothing_matches() {
		$html = WP_DownloadManager_Display::download_embedded( 'file_id = 999999' );

		$this->assertIsString( $html, 'this used to fall off the end and return null' );
		$this->assertStringNotContainsString( 'The Manual', $html );
	}

	public function test_embedded_downloads_run_through_the_prefixed_filter() {
		add_filter( 'wp_downloadmanager_embedded', fn( $out ) => $out . '<!-- filtered -->' );

		$this->assertStringContainsString( '<!-- filtered -->', WP_DownloadManager_Display::download_embedded( 'file_id = ' . $this->ids['public'] ) );
	}

	public function test_the_empty_case_runs_through_the_filter_too() {
		add_filter( 'wp_downloadmanager_embedded', fn( $out ) => $out . '<!-- filtered -->' );

		$this->assertStringContainsString( '<!-- filtered -->', WP_DownloadManager_Display::download_embedded( 'file_id = 999999' ) );
	}

	public function test_the_old_unprefixed_embedded_filter_is_gone() {
		add_filter( 'download_embedded', fn( $out ) => $out . '<!-- should never run -->' );

		$this->assertStringNotContainsString(
			'<!-- should never run -->',
			WP_DownloadManager_Display::download_embedded( 'file_id = ' . $this->ids['public'] )
		);
	}

	public function test_embedded_downloads_never_show_a_hidden_file() {
		$this->assertStringNotContainsString(
			'Hidden File',
			WP_DownloadManager_Display::download_embedded( 'file_id = ' . $this->ids['hidden'] )
		);
	}

	public function test_the_download_shortcode_renders_by_id() {
		$this->assertStringContainsString( 'The Manual', do_shortcode( '[download id="' . $this->ids['public'] . '"]' ) );
	}

	public function test_the_download_shortcode_accepts_several_ids() {
		$html = do_shortcode( '[download id="' . $this->ids['public'] . ',' . $this->ids['members'] . '"]' );

		$this->assertStringContainsString( 'The Manual', $html );
		$this->assertStringContainsString( 'Members Bundle', $html );
	}

	public function test_the_download_shortcode_still_understands_the_legacy_form() {
		$this->assertStringContainsString( 'The Manual', do_shortcode( '[download=' . $this->ids['public'] . ']' ) );
	}

	public function test_the_download_shortcode_renders_nothing_without_an_id_or_category() {
		$this->assertSame( '', do_shortcode( '[download]' ) );
	}

	public function test_the_page_download_shortcode_renders_the_listing() {
		$this->assertStringContainsString( 'The Manual', do_shortcode( '[page_download]' ) );
	}

	public function test_the_page_downloads_shortcode_is_an_alias() {
		$this->assertStringContainsString( 'The Manual', do_shortcode( '[page_downloads]' ) );
	}

	public function test_most_downloaded_orders_by_hits() {
		$html = WP_DownloadManager_Display::most_downloaded( 2, 0, false );

		$this->assertStringContainsString( 'The Manual', $html, 'twelve hits is the most of the visible files' );
		$this->assertStringNotContainsString( 'Editor Notes', $html, 'three hits does not make the top two' );
	}

	public function test_recent_downloads_returns_markup_when_asked_not_to_echo() {
		$this->assertStringContainsString( '<li>', WP_DownloadManager_Display::recent_downloads( 3, 0, false ) );
	}

	public function test_the_stats_lists_echo_when_asked_to() {
		ob_start();
		WP_DownloadManager_Display::most_downloaded( 1 );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'The Manual', $html );
	}

	public function test_downloads_by_category_accepts_a_list_of_ids() {
		$html = WP_DownloadManager_Display::downloads_category( array( 2 ), 10, 0, false );

		$this->assertStringContainsString( 'Editor Notes', $html );
		$this->assertStringNotContainsString( 'The Manual', $html );
	}

	public function test_downloads_by_category_with_an_empty_list_matches_nothing() {
		$html = WP_DownloadManager_Display::downloads_category( array(), 10, 0, false );

		$this->assertStringContainsString( 'N/A', $html );
	}

	public function test_the_stats_lists_say_so_when_there_is_nothing_to_show() {
		global $wpdb;

		$table = $this->table();
		$wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', $table ) );

		$this->assertStringContainsString( 'N/A', WP_DownloadManager_Display::most_downloaded( 5, 0, false ) );
	}

	public function test_the_totals_count_the_whole_library() {
		$this->assertSame( '5', WP_DownloadManager_Display::total_files( false ), 'the totals include the hidden file' );
	}

	public function test_the_totals_survive_an_empty_library() {
		global $wpdb;

		$table = $this->table();
		$wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', $table ) );

		$this->assertSame( '0', WP_DownloadManager_Display::total_hits( false ), 'SUM() is NULL on an empty table' );
		$this->assertSame( 'unknown', WP_DownloadManager_Display::total_size( false ) );
	}

	public function test_the_feed_lists_the_visible_files() {
		$files = WP_DownloadManager_Display::feed_files();

		$names = wp_list_pluck( $files, 'file_name' );

		$this->assertContains( 'The Manual', $names );
		$this->assertNotContains( 'Hidden File', $names, 'permission -2 is hidden from the feed too' );
	}

	public function test_the_feed_honours_its_row_limit() {
		WP_DownloadManager_Options::set( 'rss.limit', 2 );

		$this->assertCount( 2, WP_DownloadManager_Display::feed_files() );
	}

	public function test_the_feed_renders_valid_looking_rss() {
		ob_start();
		WP_DownloadManager_Display::render_feed();
		$xml = ob_get_clean();

		$this->assertStringContainsString( '<rss version="2.0"', $xml );
		$this->assertStringContainsString( '<channel>', $xml );
		$this->assertStringContainsString( '<title>', $xml );
		$this->assertStringContainsString( 'The Manual', $xml );
	}

	public function test_the_feed_does_not_use_the_option_core_removed() {
		$this->assertStringNotContainsString(
			"get_option( 'rss_language' )",
			$this->code( 'includes/class-wp-downloadmanager-display.php' ),
			'rss_language is a legacy core row; bloginfo_rss() is the current API'
		);
	}

	public function test_a_category_url_carries_the_category_id() {
		$this->assertStringContainsString( 'dl_cat=2', WP_DownloadManager_Display::category_url( 2 ) );
	}

	public function test_a_page_link_carries_the_page_number() {
		$_SERVER['REQUEST_URI'] = '/downloads/';

		$this->assertStringContainsString( 'dl_page=3', WP_DownloadManager_Display::page_link( 3 ) );
	}

	public function test_the_query_args_are_unslashed_before_they_reach_a_url() {
		$_GET = array( 'dl_search' => "O\\'Reilly" );

		$args = WP_DownloadManager_Display::query_args();

		$_GET = array();

		$this->assertSame( "O'Reilly", $args['dl_search'], 'http_build_query() would otherwise encode the added backslash' );
	}

	public function test_the_allow_list_covers_the_sprite_and_nothing_dangerous() {
		$allowed = WP_DownloadManager_Display::allowed_html();

		foreach ( array( 'svg', 'symbol', 'use', 'path', 'circle' ) as $tag ) {
			$this->assertArrayHasKey( $tag, $allowed, $tag . ' is part of the sprite' );
		}

		$this->assertArrayNotHasKey( 'script', $allowed, 'the allow list adds drawing, not scripting' );
		$this->assertArrayNotHasKey( 'onload', $allowed['svg'], 'no event attributes' );
	}

	public function test_the_file_variables_are_all_substituted() {
		$file = $this->fetch_file( $this->ids['public'] );

		$out = WP_DownloadManager_Display::replace_file_vars(
			'%FILE_ID%|%FILE%|%FILE_NAME%|%FILE_EXT%|%FILE_SIZE%|%FILE_HITS%|%FILE_DOWNLOAD_URL%',
			$file
		);

		$this->assertStringNotContainsString( '%FILE_', $out, 'every variable in the template should have been replaced' );
		$this->assertStringContainsString( 'The Manual', $out );
		$this->assertStringContainsString( 'pdf', $out );
	}
}
