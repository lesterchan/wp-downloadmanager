<?php
/**
 * Golden master: exactly what the plugin renders today.
 *
 * These assertions were written against the 1.69.2 code before any
 * modernization, and they pin the strings a theme or a visitor actually sees.
 * A modernization is allowed to move every one of these functions into a class,
 * but it is not allowed to change a single byte of what they emit.
 *
 * @package WP-DownloadManager
 */

/**
 * Rendering and formatting, byte for byte.
 */
class Test_Golden extends DownloadManager_TestCase {

	/**
	 * Byte sizes render in binary units.
	 *
	 * @dataProvider data_filesize
	 *
	 * @param int    $bytes    Raw size.
	 * @param string $expected Rendered size.
	 */
	public function test_format_filesize( $bytes, $expected ) {
		$this->assertSame( $expected, format_filesize( $bytes ) );
	}

	/**
	 * Binary unit boundaries.
	 *
	 * @return array
	 */
	public function data_filesize() {
		return array(
			'zero'        => array( 0, 'unknown' ),
			'one byte'    => array( 1, 'unknown' ),
			'two bytes'   => array( 2, '2 bytes' ),
			'1 KiB exact' => array( 1024, '1,024 bytes' ),
			'just over K' => array( 1025, '1.0 KiB' ),
			'1 MiB'       => array( 1048577, '1.0 MiB' ),
			'1 GiB'       => array( 1073741825, '1.0 GiB' ),
			'1 TiB'       => array( 1099511627777, '1.0 TiB' ),
		);
	}

	/**
	 * Byte sizes render in decimal units.
	 *
	 * @dataProvider data_filesize_dec
	 *
	 * @param int    $bytes    Raw size.
	 * @param string $expected Rendered size.
	 */
	public function test_format_filesize_dec( $bytes, $expected ) {
		$this->assertSame( $expected, format_filesize_dec( $bytes ) );
	}

	/**
	 * Decimal unit boundaries.
	 *
	 * @return array
	 */
	public function data_filesize_dec() {
		return array(
			'zero'      => array( 0, 'unknown' ),
			'two bytes' => array( 2, '2 bytes' ),
			'1 KB'      => array( 1001, '1.0 KB' ),
			'1 MB'      => array( 1000001, '1.0 MB' ),
			'1 GB'      => array( 1000000001, '1.0 GB' ),
			'1 TB'      => array( 1000000000001, '1.0 TB' ),
		);
	}

	/**
	 * Extensions come back lowercased, last segment only.
	 */
	public function test_file_extension() {
		$this->assertSame( 'pdf', file_extension( '/manual.pdf' ) );
		$this->assertSame( 'gz', file_extension( 'archive.tar.gz' ) );
		$this->assertSame( 'zip', file_extension( '/UPPER.ZIP' ) );
		$this->assertSame( 'noext', file_extension( 'noext' ) );
	}

	/**
	 * A known extension maps to its icon, an unknown one to unknown.gif.
	 */
	public function test_file_extension_image() {
		$images = file_extension_images();

		$this->assertSame( 'pdf.gif', file_extension_image( '/manual.pdf', $images ) );
		$this->assertSame( 'zip.gif', file_extension_image( '/bundle.zip', $images ) );
		$this->assertSame( 'unknown.gif', file_extension_image( '/thing.qqq', $images ) );
	}

	/**
	 * Remote files are recognised by scheme.
	 */
	public function test_is_remote_file() {
		$this->assertTrue( is_remote_file( 'https://example.com/a.zip' ) );
		$this->assertTrue( is_remote_file( 'http://example.com/a.zip' ) );
		$this->assertTrue( is_remote_file( 'ftp://example.com/a.zip' ) );
		$this->assertFalse( is_remote_file( '/local.zip' ) );
	}

	/**
	 * Download URLs in all four permalink/filename combinations.
	 */
	public function test_download_file_url() {
		$home = get_option( 'home' );

		DownloadManager_Options::set( 'nice_permalink', 1 );
		DownloadManager_Options::set( 'use_filename', 0 );
		$this->assertSame( $home . '/download/7/', download_file_url( 7, '/manual.pdf' ) );

		DownloadManager_Options::set( 'use_filename', 1 );
		$this->assertSame( $home . '/download/manual.pdf', download_file_url( 7, '/manual.pdf' ) );

		DownloadManager_Options::set( 'nice_permalink', 0 );
		DownloadManager_Options::set( 'use_filename', 0 );
		$this->assertSame( $home . '/?dl_id=7', download_file_url( 7, '/manual.pdf' ) );

		DownloadManager_Options::set( 'use_filename', 1 );
		$this->assertSame( $home . '/?dl_name=manual.pdf', download_file_url( 7, '/manual.pdf' ) );
	}

	/**
	 * A remote file keeps its full URL rather than losing the first character.
	 */
	public function test_download_file_url_remote() {
		$home = get_option( 'home' );

		DownloadManager_Options::set( 'nice_permalink', 1 );
		DownloadManager_Options::set( 'use_filename', 1 );

		$this->assertSame(
			$home . '/download/https://example.com/remote.zip',
			download_file_url( 9, 'https://example.com/remote.zip' )
		);
	}

	/**
	 * Permission labels.
	 */
	public function test_file_permission_labels() {
		$this->assertSame( 'Hidden', file_permission( -2 ) );
		$this->assertSame( 'Everyone', file_permission( -1 ) );
		$this->assertSame( 'Registered Users Only', file_permission( 0 ) );
		$this->assertSame( 'At Least Contributor Role', file_permission( 1 ) );
		$this->assertSame( 'At Least Author Role', file_permission( 2 ) );
		$this->assertSame( 'At Least Editor Role', file_permission( 7 ) );
		$this->assertSame( 'At Least Administrator Role', file_permission( 10 ) );
		$this->assertSame( '', file_permission( 5 ) );
	}

	/**
	 * User levels map from capabilities, not from the removed user_level field.
	 */
	public function test_get_wp_user_level() {
		$this->login_as( '' );
		$this->assertSame( -1, get_wp_user_level() );

		$this->login_as( 'subscriber' );
		$this->assertSame( 0, get_wp_user_level() );

		$this->login_as( 'contributor' );
		$this->assertSame( 1, get_wp_user_level() );

		$this->login_as( 'author' );
		$this->assertSame( 2, get_wp_user_level() );

		$this->login_as( 'editor' );
		$this->assertSame( 7, get_wp_user_level() );

		$this->login_as( 'administrator' );
		$this->assertSame( 10, get_wp_user_level() );
	}

	/**
	 * Totals across the whole table, hidden files included.
	 */
	public function test_totals() {
		$this->assertSame( '5', get_download_files( false ) );
		$this->assertSame( '126', get_download_hits( false ) );
		$this->assertSame( '1.0 MiB', get_download_size( false ) );
	}

	/**
	 * The totals report zero on an empty table rather than warning.
	 *
	 * SUM() returns NULL when there are no rows, and passing that to
	 * number_format() is deprecated on PHP 8.1 and later - which only shows on
	 * the newer half of the CI matrix, so it is asserted here directly.
	 */
	public function test_totals_on_an_empty_table() {
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->downloads}" ); // phpcs:ignore WordPress.DB

		$this->assertSame( '0', get_download_files( false ) );
		$this->assertSame( '0', get_download_hits( false ) );
		$this->assertSame( 'unknown', get_download_size( false ) );
	}

	/**
	 * The downloads page survives an empty table without warning.
	 */
	public function test_downloads_page_totals_on_an_empty_table() {
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->downloads}" ); // phpcs:ignore WordPress.DB

		$out = downloads_page();

		$this->assertStringContainsString( 'No Files Found.', $out );
		$this->assertDoesNotMatchRegularExpression( '/%[A-Z_]+%/', $out );
	}

	/**
	 * The embedded template renders the download link for a permitted file.
	 */
	public function test_download_embedded_permitted() {
		$this->login_as( '' );

		$out = download_embedded( 'file_id = ' . $this->ids['public'] );

		$this->assertStringContainsString( 'The Manual', $out );
		$this->assertStringContainsString( download_file_url( $this->ids['public'], '/manual.pdf' ), $out );
		$this->assertStringContainsString( 'pdf.gif', $out );
		$this->assertStringContainsString( '2.0 KiB', $out );
		$this->assertStringNotContainsString( 'You do not have permission', $out );
	}

	/**
	 * The second embedded template renders for a file the visitor may not have.
	 */
	public function test_download_embedded_denied() {
		$this->login_as( '' );

		$out = download_embedded( 'file_id = ' . $this->ids['members'] );

		$this->assertStringContainsString( 'Members Bundle', $out );
		$this->assertStringContainsString( 'You do not have permission to download this file.', $out );
		$this->assertStringNotContainsString( 'href="' . download_file_url( $this->ids['members'], '/members.zip' ) . '"', $out );
	}

	/**
	 * A registered user gets the link for a members-only file.
	 */
	public function test_download_embedded_member_sees_link() {
		$this->login_as( 'subscriber' );

		$out = download_embedded( 'file_id = ' . $this->ids['members'] );

		$this->assertStringContainsString( download_file_url( $this->ids['members'], '/members.zip' ), $out );
		$this->assertStringNotContainsString( 'You do not have permission', $out );
	}

	/**
	 * An editor-only file stays locked for an author and opens for an editor.
	 */
	public function test_download_embedded_role_gate() {
		$this->login_as( 'author' );
		$this->assertStringContainsString(
			'You do not have permission',
			download_embedded( 'file_id = ' . $this->ids['editors'] )
		);

		$this->login_as( 'editor' );
		$this->assertStringNotContainsString(
			'You do not have permission',
			download_embedded( 'file_id = ' . $this->ids['editors'] )
		);
	}

	/**
	 * Hidden files never appear, whoever is looking.
	 */
	public function test_hidden_file_never_rendered() {
		$this->login_as( 'administrator' );

		$this->assertEmpty( download_embedded( 'file_id = ' . $this->ids['hidden'] ) );
		$this->assertStringNotContainsString( 'Hidden File', (string) downloads_page() );
		$this->assertStringNotContainsString( 'Hidden File', get_most_downloaded( 10, 0, false ) );
		$this->assertStringNotContainsString( 'Hidden File', get_recent_downloads( 10, 0, false ) );
	}

	/**
	 * Most-downloaded orders by hits descending and skips hidden files.
	 */
	public function test_get_most_downloaded_order() {
		$this->login_as( '' );

		$out = get_most_downloaded( 10, 0, false );

		$this->assertStringNotContainsString( 'Hidden File', $out );
		$this->assertLessThan(
			strpos( $out, 'Remote Bundle' ),
			strpos( $out, 'The Manual' ),
			'12 hits should sort above 7 hits'
		);
	}

	/**
	 * An empty result set renders the N/A list item.
	 */
	public function test_get_most_downloaded_empty() {
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->downloads}" ); // phpcs:ignore WordPress.DB

		$this->assertSame( "<li>N/A</li>\n", get_most_downloaded( 10, 0, false ) );
	}

	/**
	 * Category filtering accepts a single id and a list of ids.
	 */
	public function test_get_downloads_category() {
		$this->login_as( '' );

		$one = get_downloads_category( 1, 10, 0, false );
		$this->assertStringContainsString( 'The Manual', $one );
		$this->assertStringNotContainsString( 'Remote Bundle', $one );

		$many = get_downloads_category( array( 1, 2 ), 10, 0, false );
		$this->assertStringContainsString( 'The Manual', $many );
		$this->assertStringContainsString( 'Remote Bundle', $many );
	}

	/**
	 * The downloads page renders the header, the category grouping and a listing.
	 */
	public function test_downloads_page_structure() {
		$this->login_as( '' );

		$out = $this->squash( downloads_page() );

		// Header template, with its counts substituted.
		$this->assertStringContainsString( 'There are <strong>4 files</strong>', $out );
		// Grouping is on, so both category headers appear.
		$this->assertStringContainsString( 'id="downloadcat-1"', $out );
		$this->assertStringContainsString( 'id="downloadcat-2"', $out );
		$this->assertStringContainsString( 'General', $out );
		$this->assertStringContainsString( 'Software', $out );
		// Footer template carries the search form.
		$this->assertStringContainsString( 'name="dl_search"', $out );
		// No %VARIABLE% escaped the substitution pass.
		$this->assertDoesNotMatchRegularExpression( '/%[A-Z_]+%/', $out );
	}

	/**
	 * Restricting to one category drops the other one's files.
	 */
	public function test_downloads_page_category_filter() {
		$this->login_as( '' );

		$out = downloads_page( 2 );

		$this->assertStringContainsString( 'Editor Notes', $out );
		$this->assertStringNotContainsString( 'The Manual', $out );
	}

	/**
	 * With no matching files the "none" template renders.
	 */
	public function test_downloads_page_empty() {
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->downloads}" ); // phpcs:ignore WordPress.DB

		$this->assertStringContainsString( 'No Files Found.', downloads_page() );
	}

	/**
	 * Pagination appears once there are more files than fit on a page.
	 */
	public function test_downloads_page_pagination() {
		DownloadManager_Options::set( 'sort.perpage', 2 );
		DownloadManager_Options::set( 'sort.group', 0 );

		$out = downloads_page();

		$this->assertStringContainsString( 'wp-downloadmanager-paging', $out );
		$this->assertStringContainsString( 'Page 1 of 2', $out );
		$this->assertStringContainsString( 'class="current"', $out );
	}

	/**
	 * The search term filters the listing and gets highlighted in it.
	 */
	public function test_downloads_page_search_highlight() {
		$_GET['dl_search'] = 'Manual';

		$out = downloads_page();

		// The matched word is wrapped, so the title is no longer contiguous.
		$this->assertStringContainsString(
			'The <span class="download-search-highlight">Manual</span>',
			$out
		);
		$this->assertStringNotContainsString( 'Editor Notes', $out );

		unset( $_GET['dl_search'] );
	}

	/**
	 * The shortcode reaches download_embedded() for one id and for a list.
	 */
	public function test_download_shortcode() {
		$this->login_as( '' );

		$one = do_shortcode( '[download id="' . $this->ids['public'] . '"]' );
		$this->assertStringContainsString( 'The Manual', $one );

		$two = do_shortcode( '[download id="' . $this->ids['public'] . ',' . $this->ids['remote'] . '"]' );
		$this->assertStringContainsString( 'The Manual', $two );
		$this->assertStringContainsString( 'Remote Bundle', $two );
	}

	/**
	 * Passing display="name" blanks the description placeholder.
	 */
	public function test_download_shortcode_display_name_only() {
		$this->login_as( '' );

		$out = do_shortcode( '[download id="' . $this->ids['public'] . '" display="name"]' );

		$this->assertStringContainsString( 'The Manual', $out );
		$this->assertStringNotContainsString( 'A public manual.', $out );
	}

	/**
	 * The category attribute selects by category.
	 */
	public function test_download_shortcode_category() {
		$this->login_as( '' );

		$out = do_shortcode( '[download category="2"]' );

		$this->assertStringContainsString( 'Editor Notes', $out );
		$this->assertStringNotContainsString( 'The Manual', $out );
	}

	/**
	 * The page_download shortcode renders the downloads page.
	 */
	public function test_page_download_shortcode() {
		$this->login_as( '' );

		$out = do_shortcode( '[page_download]' );

		$this->assertStringContainsString( 'There are <strong>4 files</strong>', $out );
	}

	/**
	 * The sort_by and sort_order attributes are honoured, and an unknown value falls back.
	 */
	public function test_download_shortcode_sorting() {
		$this->login_as( '' );

		$asc = do_shortcode( '[download category="2" sort_by="file_hits" sort_order="asc"]' );
		$this->assertLessThan(
			strpos( $asc, 'Remote Bundle' ),
			strpos( $asc, 'Editor Notes' ),
			'3 hits should come before 7 ascending'
		);

		$desc = do_shortcode( '[download category="2" sort_by="file_hits" sort_order="desc"]' );
		$this->assertLessThan(
			strpos( $desc, 'Editor Notes' ),
			strpos( $desc, 'Remote Bundle' ),
			'7 hits should come before 3 descending'
		);
	}

	/**
	 * The stream_limit attribute truncates the list outside a single post and adds the More link.
	 */
	public function test_download_shortcode_stream_limit() {
		$this->login_as( '' );

		$out = do_shortcode( '[download category="2" stream_limit="1"]' );

		$this->assertStringContainsString( 'More', $out );
	}

	/**
	 * In a feed the shortcode returns its placeholder line instead of markup.
	 */
	public function test_download_shortcode_in_feed() {
		global $wp_query;
		$wp_query->is_feed = true;

		$out = do_shortcode( '[download id="' . $this->ids['public'] . '"]' );

		$this->assertSame(
			'Note: There is a file embedded within this post, please visit this post to download the file.',
			$out
		);

		$wp_query->is_feed = false;
	}

	/**
	 * The downloads_page filter can rewrite the whole page.
	 */
	public function test_downloads_page_filter() {
		add_filter( 'downloads_page', '__return_empty_string' );
		$this->assertSame( '', downloads_page() );
		remove_filter( 'downloads_page', '__return_empty_string' );
	}

	/**
	 * The download_embedded filter can rewrite the embedded output.
	 */
	public function test_download_embedded_filter() {
		add_filter( 'download_embedded', '__return_empty_string' );
		$this->assertSame( '', download_embedded( 'file_id = ' . $this->ids['public'] ) );
		remove_filter( 'download_embedded', '__return_empty_string' );
	}

	/**
	 * The file extension icon list is filterable.
	 */
	public function test_file_extension_image_filter() {
		add_filter(
			'wp_downloadmanager_file_extension_image',
			static function () {
				return 'custom.gif';
			}
		);

		$this->assertSame( 'custom.gif', file_extension_image( '/manual.pdf', file_extension_images() ) );

		remove_all_filters( 'wp_downloadmanager_file_extension_image' );
	}

	/**
	 * Remote URL validation accepts the allowed schemes and rejects the rest.
	 */
	public function test_is_file_remote_valid() {
		$this->assertTrue( is_file_remote_valid( 'https://example.com/a.zip' ) );
		$this->assertTrue( is_file_remote_valid( 'ftp://example.com/a.zip' ) );
		$this->assertFalse( is_file_remote_valid( 'javascript:alert(1)' ) );
		$this->assertFalse( is_file_remote_valid( '/local.zip' ) );
		$this->assertFalse( is_file_remote_valid( 'https://example.com:8080/a.zip' ) );
	}

	/**
	 * The widget renders the list it is configured for.
	 */
	public function test_widget_renders() {
		$this->login_as( '' );

		$widget = new DownloadManager_Widget();

		ob_start();
		$widget->widget(
			array(
				'before_widget' => '<aside>',
				'after_widget'  => '</aside>',
				'before_title'  => '<h2>',
				'after_title'   => '</h2>',
			),
			array(
				'title'   => 'Downloads',
				'type'    => 'most_downloaded',
				'mode'    => '',
				'limit'   => 5,
				'chars'   => 0,
				'cat_ids' => '0',
				'link'    => 1,
			)
		);
		$out = ob_get_clean();

		$this->assertStringContainsString( '<aside><h2>Downloads</h2>', $out );
		$this->assertStringContainsString( 'The Manual', $out );
		$this->assertStringContainsString( 'Downloads Page', $out );
		$this->assertStringContainsString( '</aside>', $out );
	}

	/**
	 * Query vars the rewrite rules feed are registered.
	 */
	public function test_query_vars_registered() {
		$vars = apply_filters( 'query_vars', array() );

		$this->assertContains( 'dl_id', $vars );
		$this->assertContains( 'dl_name', $vars );
	}

	/**
	 * The download rewrite rules are prepended.
	 */
	public function test_rewrite_rules() {
		$rewrite        = new stdClass();
		$rewrite->rules = array( 'existing/?$' => 'index.php?x=1' );

		DownloadManager::rewrite_rules( $rewrite );

		$this->assertSame(
			'index.php?dl_id=$matches[1]',
			$rewrite->rules['download/([0-9]{1,})/?$']
		);
		$this->assertSame(
			'index.php?dl_name=$matches[1]',
			$rewrite->rules['download/(.*)$']
		);
		$this->assertArrayHasKey( 'existing/?$', $rewrite->rules );
	}

	/**
	 * Activation grants the plugin capability to administrators.
	 */
	public function test_capability_granted() {
		$this->assertTrue( get_role( 'administrator' )->has_cap( 'manage_downloads' ) );
	}
}
