<?php
/**
 * The guards that stop a download library becoming a way into the site.
 *
 * Most of these pin a fix rather than a feature. Every one of them corresponds
 * to something that was genuinely reachable in 1.69.2 or earlier, which is why
 * they are gathered here rather than scattered through the suite: if one of
 * them ever fails, it is a regression with a CVE-shaped hole behind it.
 *
 * @package WP-DownloadManager
 */

/**
 * Injection, traversal, permission and escaping guards.
 */
class WP_DownloadManager_Security_Test extends WP_DownloadManager_TestCase {

	public function test_a_widget_category_list_cannot_reach_the_where_clause() {
		$html = WP_DownloadManager_Display::downloads_category( explode( ',', '1) OR (1=1' ), 10, 0, false );

		$this->assertStringNotContainsString(
			'Hidden File',
			$html,
			'anyone able to edit a widget could rewrite the WHERE clause, including the guard that hides files'
		);
	}

	public function test_a_widget_category_list_of_nonsense_matches_nothing() {
		$html = WP_DownloadManager_Display::downloads_category( array( 'not a number' ), 10, 0, false );

		$this->assertStringContainsString( 'N/A', $html );
	}

	public function test_a_shortcode_id_list_is_cast_before_it_reaches_the_query() {
		$html = do_shortcode( '[download id="1) OR (1=1"]' );

		$this->assertStringNotContainsString( 'Hidden File', $html );
	}

	public function test_a_shortcode_category_list_is_cast_before_it_reaches_the_query() {
		$html = do_shortcode( '[download category="2) OR (1=1"]' );

		$this->assertStringNotContainsString( 'Hidden File', $html );
	}

	public function test_a_search_term_of_sql_cannot_reach_the_listing_query() {
		$_GET['dl_search'] = "' OR 1=1 -- ";

		$html = WP_DownloadManager_Display::downloads_page();

		$_GET = array();

		$this->assertStringContainsString( 'No Files Found', $html );
		$this->assertSame( 5, $this->count_files(), 'and the table survives' );
	}

	public function test_a_stored_sort_column_of_sql_cannot_reach_order_by() {
		WP_DownloadManager_Options::set( 'sort.by', 'file_name; DROP TABLE wp_downloads' );

		$html = WP_DownloadManager_Display::downloads_page();

		$this->assertStringContainsString( 'The Manual', $html, 'the allow list catches it before the query is built' );
		$this->assertSame( 5, $this->count_files() );
	}

	public function test_a_stored_feed_sort_column_of_sql_cannot_reach_order_by() {
		WP_DownloadManager_Options::set( 'rss.sortby', 'file_date; DROP TABLE wp_downloads' );

		$this->assertNotEmpty( WP_DownloadManager_Display::feed_files(), 'The feed still answers, so the injected sort column was ignored rather than fatal.' );
		$this->assertSame( 5, $this->count_files() );
	}

	public function test_a_shortcode_sort_argument_of_sql_cannot_reach_order_by() {
		$html = do_shortcode( '[download id="' . $this->ids['public'] . '" sort_by="file_id; DROP TABLE wp_downloads"]' );

		$this->assertStringContainsString( 'The Manual', $html );
		$this->assertSame( 5, $this->count_files() );
	}

	public function test_a_hidden_file_is_invisible_everywhere_it_could_leak() {
		$surfaces = array(
			'listing'  => WP_DownloadManager_Display::downloads_page(),
			'embedded' => WP_DownloadManager_Display::download_embedded( 'file_id = ' . $this->ids['hidden'] ),
			'most'     => WP_DownloadManager_Display::most_downloaded( 10, 0, false ),
			'recent'   => WP_DownloadManager_Display::recent_downloads( 10, 0, false ),
			'category' => WP_DownloadManager_Display::downloads_category( 2, 10, 0, false ),
		);

		foreach ( $surfaces as $where => $html ) {
			$this->assertStringNotContainsString( 'Hidden File', $html, 'permission -2 leaked into the ' . $where );
		}
	}

	public function test_a_hidden_file_is_not_in_the_feed_either() {
		$names = wp_list_pluck( WP_DownloadManager_Display::feed_files(), 'file_name' );

		$this->assertNotContains( 'Hidden File', $names );
	}

	public function test_a_visitor_without_permission_gets_the_denied_template_not_the_link() {
		$this->login_as( '' );

		$html = WP_DownloadManager_Display::downloads_page();

		$this->assertStringContainsString( 'Editor Notes', $html, 'the file is still listed' );
		$this->assertStringContainsString( 'You do not have permission to download this file.', $html );
	}

	public function test_a_visitor_with_permission_gets_the_download_link() {
		$this->login_as( 'administrator' );

		$html = WP_DownloadManager_Display::downloads_page();

		$this->assertStringContainsString( '/download/' . $this->ids['editors'] . '/', $html );
	}

	public function test_the_endpoint_refuses_a_file_above_the_visitors_level() {
		$this->login_as( 'subscriber' );
		set_query_var( 'dl_id', $this->ids['editors'] );

		$this->expectException( WPDieException::class );

		WP_DownloadManager_File::serve();
	}

	public function test_an_upload_subfolder_cannot_escape_the_downloads_directory() {
		WP_DownloadManager_Options::set( 'path.dir', WP_CONTENT_DIR . '/dm-security' );
		$this->make_download_file( 'sub/keep.txt' );

		$path = WP_DownloadManager_Options::get( 'path.dir' );

		foreach ( array( '../../../', '/../etc', "sub\0/../..", '..' ) as $candidate ) {
			$this->assertSame( '/', WP_DownloadManager_File::safe_subfolder( $path, $candidate ), $candidate . ' must not resolve outside the downloads directory' );
		}

		$this->remove_download_files();
	}

	public function test_the_download_path_setting_cannot_be_moved_outside_wp_content() {
		foreach ( array( '/etc', '/', WP_CONTENT_DIR . '/../..', '' ) as $candidate ) {
			$saved = WP_DownloadManager_Settings::sanitize( array( 'path' => array( 'dir' => $candidate ) ) );

			$this->assertSame( WP_CONTENT_DIR, $saved['path']['dir'], $candidate . ' should have been refused' );
		}
	}

	public function test_a_template_cannot_be_used_to_inject_a_script() {
		$saved = WP_DownloadManager_Settings::sanitize(
			array(
				'templates' => array(
					'header' => '<p onclick="alert(1)">x</p><script>alert(1)</script><a href="javascript:alert(1)">y</a>',
				),
			)
		);

		$this->assertStringNotContainsString( '<script', $saved['templates']['header'] );
		$this->assertStringNotContainsString( 'onclick', $saved['templates']['header'] );
		$this->assertStringNotContainsString( 'javascript:', $saved['templates']['header'] );
	}

	public function test_the_svg_allow_list_does_not_open_a_hole() {
		$allowed = WP_DownloadManager_Display::allowed_html();

		foreach ( array( 'script', 'foreignObject', 'foreignobject', 'animate', 'set', 'image' ) as $tag ) {
			$this->assertArrayNotHasKey( $tag, $allowed, $tag . ' has no business in an icon sprite' );
		}

		foreach ( $allowed['use'] as $attribute => $unused ) {
			$this->assertStringStartsNotWith( 'on', $attribute, 'no event attributes anywhere in the allow list' );
		}
	}

	public function test_a_file_name_is_run_through_kses_on_the_way_in() {
		global $wpdb;

		WP_DownloadManager_Admin::load_list_table();
		$this->on_admin_screen();
		$this->become_download_admin();
		WP_DownloadManager_Options::set( 'path.dir', WP_CONTENT_DIR . '/dm-xss' );
		$this->make_download_file( 'thing.txt' );

		$this->render(
			array( 'WP_DownloadManager_Admin', 'render_add' ),
			array(),
			array(
				'do'              => 'Add File',
				'_wpnonce'        => $this->nonce( 'wp_downloadmanager_add' ),
				'file_type'       => '0',
				'file'            => '/thing.txt',
				'file_name'       => '<script>alert(1)</script><strong>Bold</strong>',
				'file_des'        => '<script>alert(1)</script>Fine',
				'file_permission' => '-1',
			)
		);

		$row = $wpdb->get_row( "SELECT * FROM {$wpdb->downloads} ORDER BY file_id DESC LIMIT 1" );

		$this->assertStringNotContainsString( '<script>', $row->file_name, 'the listing templates render this back as markup, so it has to be clean in the database' );
		$this->assertStringContainsString( '<strong>Bold</strong>', $row->file_name, 'the display name is the one field allowed to carry markup' );
		$this->assertStringNotContainsString( '<script>', $row->file_des );

		$this->remove_download_files();
	}

	public function test_a_file_name_carrying_markup_is_escaped_on_the_admin_list() {
		WP_DownloadManager_Admin::load_list_table();
		$this->on_admin_screen();
		$this->become_download_admin();

		$this->insert_file(
			array(
				'file'      => '/xss2.txt',
				'file_name' => '<script>alert(1)</script>Innocent',
			)
		);

		$html = $this->render( array( 'WP_DownloadManager_Admin', 'render_downloads' ) );

		$this->assertStringNotContainsString( '<script>alert(1)</script>', $html );
	}

	public function test_a_file_path_carrying_markup_is_escaped_on_the_admin_list() {
		WP_DownloadManager_Admin::load_list_table();
		$this->on_admin_screen();
		$this->become_download_admin();

		$this->insert_file(
			array(
				'file'      => '/"><script>alert(1)</script>.txt',
				'file_name' => 'Sneaky',
			)
		);

		$html = $this->render( array( 'WP_DownloadManager_Admin', 'render_downloads' ) );

		$this->assertStringNotContainsString( '<script>alert(1)</script>', $html );
	}

	/**
	 * A row carrying every payload, written the way an attacker would write it.
	 *
	 * Straight into the table, past wp_kses_post() on the Add File screen -- the
	 * row a restored backup, a compromised install or a release older than that
	 * check already has. Sanitising on the way in is the assumption under test,
	 * not a step to reproduce (STANDARDS.md 7.2.4).
	 *
	 * @return int Inserted file_id.
	 */
	protected function insert_hostile_file() {
		return $this->insert_file(
			array(
				'file'      => '/hostile.txt',
				'file_name' => 'Hostile <script>window.pwned = 1;</script>',
				'file_des'  => 'Description <img src=x onerror="window.pwned = 1"> and " onmouseover="window.pwned = 1',
			)
		);
	}

	/**
	 * Assert that rendered markup carries nothing a browser would run.
	 *
	 * Parsed rather than grepped, because the half of the payload that survives
	 * is meant to survive: the attribute breakout comes back as the literal text
	 * " onmouseover="window.pwned = 1, which reads like an attack in a string
	 * search and is inert in a text node. What matters is whether the parser
	 * ended up with a script element or an event handler, so ask the parser.
	 *
	 * @param string $html  Rendered markup.
	 * @param string $where The surface being checked, for the failure message.
	 * @return void
	 */
	protected function assert_nothing_can_run( $html, $where ) {
		$dom = new DOMDocument();

		libxml_use_internal_errors( true );
		$dom->loadHTML( '<meta http-equiv="Content-Type" content="text/html; charset=utf-8"><body>' . $html . '</body>' );
		libxml_clear_errors();
		libxml_use_internal_errors( false );

		$xpath = new DOMXPath( $dom );

		$this->assertSame(
			0,
			$xpath->query( '//script' )->length,
			'a script element reached ' . $where
		);
		$this->assertSame(
			0,
			$xpath->query( '//@*[starts-with(translate(name(), "ON", "on"), "on")]' )->length,
			'an event handler attribute reached ' . $where
		);
	}

	public function test_a_hostile_row_is_inert_where_the_templates_are_filled_in() {
		$file = $this->fetch_file( $this->insert_hostile_file() );

		$html = WP_DownloadManager_Display::replace_file_vars( '<p>%FILE_NAME%<br />%FILE_DESCRIPTION%</p>', $file );

		// The one place all five render paths pass through, which is why it is
		// the one place that decides.
		$this->assert_nothing_can_run( $html, 'replace_file_vars()' );
		$this->assertStringContainsString( 'window.pwned', $html, 'and the text of the value is still there, escaped' );
		$this->assertStringContainsString( 'Hostile', $html );
		$this->assertStringContainsString( 'onmouseover', $html, 'as text: escaping that ate the value would be its own bug' );
	}

	public function test_a_hostile_row_is_inert_on_the_downloads_page() {
		$this->insert_hostile_file();

		$html = WP_DownloadManager_Display::downloads_page();

		$this->assert_nothing_can_run( $html, 'the downloads page' );
		$this->assertStringContainsString( 'window.pwned', $html );
	}

	public function test_a_hostile_row_is_inert_in_the_download_shortcode() {
		$file_id = $this->insert_hostile_file();

		$html = do_shortcode( '[download id="' . $file_id . '"]' );

		$this->assert_nothing_can_run( $html, 'the [download] shortcode' );
		$this->assertStringContainsString( 'window.pwned', $html );
	}

	public function test_a_hostile_row_is_inert_in_the_stats_lists() {
		$this->insert_hostile_file();

		// $display false is the branch that returns rather than echoes, so it
		// skips the wp_kses() in output(). A theme calling the template tag that
		// way and echoing the result is the sixth path nobody counted.
		foreach ( array( 'most_downloaded', 'recent_downloads' ) as $tag ) {
			$html = call_user_func( array( 'WP_DownloadManager_Display', $tag ), 10, 0, false );

			$this->assert_nothing_can_run( $html, $tag . '() returning its markup' );
			$this->assertStringContainsString( 'window.pwned', $html, 'The hostile row is rendered at all, or the inertness assertions below are vacuous.' );
		}
	}

	public function test_a_hostile_row_is_inert_in_the_widget() {
		$this->insert_hostile_file();

		$widget = new WP_DownloadManager_Widget();

		ob_start();
		$widget->widget(
			array(
				'before_widget' => '<aside>',
				'after_widget'  => '</aside>',
				'before_title'  => '<h2>',
				'after_title'   => '</h2>',
			),
			array(
				'title' => 'Downloads',
				'type'  => 'most_downloaded',
				'limit' => 10,
				'chars' => 0,
				'link'  => 0,
			)
		);
		$html = ob_get_clean();

		$this->assert_nothing_can_run( $html, 'the widget' );
		$this->assertStringContainsString( 'window.pwned', $html );
	}

	public function test_markup_a_site_owner_is_allowed_to_use_still_renders() {
		$file = $this->fetch_file(
			$this->insert_file(
				array(
					'file'      => '/bold.txt',
					'file_name' => '<strong>Bold</strong> Release',
					'file_des'  => 'See <a href="https://example.com/notes">the notes</a>.',
				)
			)
		);

		$html = WP_DownloadManager_Display::replace_file_vars( '<p>%FILE_NAME%<br />%FILE_DESCRIPTION%</p>', $file );

		// The allow list is post-level kses, not esc_html(): these two fields are
		// the ones the plugin lets a site owner put markup in, and an escape that
		// turned every existing library's formatting into visible tags would be a
		// worse bug than the one being fixed.
		$this->assertStringContainsString( '<strong>Bold</strong>', $html );
		$this->assertStringContainsString( '<a href="https://example.com/notes">the notes</a>', $html );
	}

	public function test_a_remote_url_cannot_be_used_for_server_side_request_forgery() {
		foreach ( array( 'file:///etc/passwd', 'gopher://127.0.0.1:11211/', 'http://127.0.0.1:22/' ) as $candidate ) {
			$this->assertFalse( WP_DownloadManager_File::is_remote_valid( $candidate ), $candidate . ' should be refused' );
		}
	}

	public function test_the_search_highlight_cannot_be_used_to_inject_markup() {
		$out = WP_DownloadManager_Display::search_highlight( '<script>', 'a <script> b' );

		$this->assertStringNotContainsString( '<script>alert', $out );
		$this->assertStringContainsString( 'wp-downloadmanager-highlight', $out );
	}

	public function test_every_write_path_checks_a_nonce() {
		$source = $this->code( 'includes/class-wp-downloadmanager-admin.php' );

		$this->assertSame(
			4,
			substr_count( $source, 'check_admin_referer(' ),
			'add, edit, delete and bulk delete each check one'
		);
	}

	public function test_every_screen_checks_a_capability() {
		$source = $this->code( 'includes/class-wp-downloadmanager-admin.php' );

		$this->assertStringContainsString( 'current_user_can(', $source );
		$this->assertGreaterThanOrEqual( 3, substr_count( $source, 'require_capability()' ), 'every rendered screen and every write' );
	}

	public function test_the_capability_filter_cannot_be_bypassed_by_a_direct_constant_read() {
		$source = $this->code( 'includes/class-wp-downloadmanager-admin.php' );

		$this->assertSame(
			1,
			substr_count( $source, 'self::CAPABILITY' ),
			'every capability check goes through capability(), which is the one place the filter fires'
		);
	}

	public function test_uninstall_is_not_reachable_without_the_wordpress_constant() {
		$source = file_get_contents( WP_DOWNLOADMANAGER_DIR . 'uninstall.php' );

		$this->assertStringContainsString( "if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {", $source );
	}
}
