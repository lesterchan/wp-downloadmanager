<?php
/**
 * File naming, sizing, permissions and the download endpoint.
 *
 * @package WP-DownloadManager
 */

/**
 * Everything WP_DownloadManager_File knows about a file.
 */
class WP_DownloadManager_File_Test extends WP_DownloadManager_TestCase {

	public function test_an_extension_is_lowercased() {
		$this->assertSame( 'pdf', WP_DownloadManager_File::extension( '/Manual.PDF' ), 'An extension is lowercased, so PDF and pdf are one family.' );
	}

	public function test_a_file_with_no_extension_reads_as_its_own_name() {
		$this->assertSame( 'readme', WP_DownloadManager_File::extension( 'README' ), 'A file with no extension reads as its own name.' );
	}

	public function test_every_known_extension_maps_to_a_family_that_has_a_symbol() {
		$families = WP_DownloadManager_File::icon_families();

		foreach ( WP_DownloadManager_File::extension_families() as $extension => $family ) {
			$this->assertContains( $family, $families, $extension . ' maps to a family with no symbol behind it' );
		}
	}

	public function test_an_unknown_extension_falls_back_to_a_plain_document() {
		$this->assertSame( 'file', WP_DownloadManager_File::extension_family( '/thing.qqq' ), 'An unknown extension falls back to a plain document.' );
	}

	public function test_the_families_cover_the_extensions_the_gifs_used_to() {
		foreach ( array( 'zip', 'mp3', 'php', 'pdf', 'png', 'ppt', 'xls', 'avi', 'exe' ) as $extension ) {
			$this->assertNotSame( 'file', WP_DownloadManager_File::extension_family( '/x.' . $extension ), $extension . ' had a GIF of its own before 2.0.0' );
		}
	}

	public function test_the_families_cover_file_types_the_gifs_never_had() {
		foreach ( array( 'webp', 'mp4', 'docx', 'xlsx', '7z', 'json', 'flac' ) as $extension ) {
			$this->assertNotSame( 'file', WP_DownloadManager_File::extension_family( '/x.' . $extension ), $extension . ' should be grouped now' );
		}
	}

	public function test_the_family_filter_can_override_the_choice() {
		add_filter( 'wp_downloadmanager_file_extension_image', fn() => 'video' );

		$this->assertSame( 'video', WP_DownloadManager_File::extension_family( '/bundle.zip' ), 'The filter can override the family the extension would have chosen.' );
	}

	public function test_the_family_filter_cannot_invent_a_family_with_no_symbol() {
		add_filter( 'wp_downloadmanager_file_extension_image', fn() => 'nonsense' );

		$this->assertSame( 'file', WP_DownloadManager_File::extension_family( '/bundle.zip' ), 'a <use> pointing at nothing renders nothing at all' );
	}

	public function test_a_sort_column_off_the_allow_list_falls_back() {
		$this->assertSame( 'file_id', WP_DownloadManager_File::sort_column( 'DROP TABLE' ), 'this value reaches ORDER BY' );
		$this->assertSame( 'file_name', WP_DownloadManager_File::sort_column( 'nonsense', 'file_name' ), 'A sort column off the allow list falls back rather than reaching the query.' );
	}

	public function test_every_allow_listed_sort_column_is_accepted() {
		foreach ( WP_DownloadManager_File::sort_columns() as $column ) {
			$this->assertSame( $column, WP_DownloadManager_File::sort_column( $column ), $column . ' is allow-listed but not accepted by sort_column().' );
		}
	}

	public function test_a_sort_direction_is_constrained_to_two_values() {
		$this->assertSame( 'asc', WP_DownloadManager_File::sort_order( 'ASC' ), 'A direction is lowercased.' );
		$this->assertSame( 'desc', WP_DownloadManager_File::sort_order( 'DeSc' ), 'Whatever case it arrives in.' );
		$this->assertSame( 'asc', WP_DownloadManager_File::sort_order( '; DROP TABLE' ), 'And anything else becomes ascending rather than reaching the query.' );
	}

	public function test_a_remote_file_is_recognised_by_its_scheme() {
		$this->assertTrue( WP_DownloadManager_File::is_remote( 'https://example.com/a.zip' ), 'An https URL is recognised as remote.' );
		$this->assertTrue( WP_DownloadManager_File::is_remote( 'ftp://example.com/a.zip' ), 'An ftp URL is recognised as remote.' );
		$this->assertFalse( WP_DownloadManager_File::is_remote( '/local.zip' ), 'A local path is not remote.' );
	}

	public function test_a_remote_url_on_an_unexpected_scheme_is_refused() {
		$this->assertFalse( WP_DownloadManager_File::is_remote_valid( 'file:///etc/passwd' ), 'this guard is what stops server-side request forgery' );
		$this->assertFalse( WP_DownloadManager_File::is_remote_valid( 'gopher://example.com/' ), 'A URL on a scheme outside the allow list is refused.' );
		$this->assertFalse( WP_DownloadManager_File::is_remote_valid( 'not a url at all' ), 'A string that is not a URL is refused.' );
	}

	public function test_a_remote_url_on_an_unexpected_port_is_refused() {
		$this->assertFalse( WP_DownloadManager_File::is_remote_valid( 'http://example.com:22/a.zip' ), 'A URL on a port outside the allow list is refused.' );
		$this->assertTrue( WP_DownloadManager_File::is_remote_valid( 'https://example.com:443/a.zip' ), 'The standard https port is allowed.' );
	}

	public function test_the_scheme_allow_list_is_filterable() {
		add_filter( 'wp_downloadmanager_schemes', fn() => array( 'https' ) );

		$this->assertFalse( WP_DownloadManager_File::is_remote_valid( 'http://example.com/a.zip' ), 'A filter can take http out of the scheme allow list.' );
		$this->assertTrue( WP_DownloadManager_File::is_remote_valid( 'https://example.com/a.zip' ), 'Filtering the allow list leaves https in it.' );
	}

	public function test_the_port_allow_list_is_filterable() {
		add_filter( 'wp_downloadmanager_ports', fn() => array( 8080 ) );

		$this->assertTrue( WP_DownloadManager_File::is_remote_valid( 'http://example.com:8080/a.zip' ), 'A filter can add a port to the allow list.' );
		$this->assertFalse( WP_DownloadManager_File::is_remote_valid( 'http://example.com:80/a.zip' ), 'Filtering the port list replaces it rather than adding to it.' );
	}

	public function test_a_size_is_formatted_in_binary_units() {
		$this->assertStringContainsString( 'KiB', WP_DownloadManager_File::format_size( 2048 ), 'Two kilobytes are formatted in binary units.' );
		$this->assertStringContainsString( 'MiB', WP_DownloadManager_File::format_size( 5 * 1048576 ), 'And so are megabytes.' );
		$this->assertStringContainsString( 'bytes', WP_DownloadManager_File::format_size( 900 ), 'While a small size stays in bytes.' );
	}

	public function test_a_size_is_formatted_in_decimal_units_too() {
		$this->assertStringContainsString( 'KB', WP_DownloadManager_File::format_size_dec( 2000 ), 'The decimal form uses decimal units.' );
		$this->assertStringContainsString( 'MB', WP_DownloadManager_File::format_size_dec( 5000000 ), 'For megabytes too.' );
	}

	public function test_a_size_of_zero_reads_as_unknown() {
		$this->assertSame( 'unknown', WP_DownloadManager_File::format_size( 0 ), 'A size of zero reads as unknown rather than as no bytes.' );
		$this->assertSame( 'unknown', WP_DownloadManager_File::format_size_dec( 0 ), 'In the decimal form as well.' );
	}

	public function test_every_permission_level_has_a_label() {
		foreach ( array( -2, -1, 0, 1, 2, 7, 10 ) as $permission ) {
			$this->assertNotSame( '', WP_DownloadManager_File::permission_label( $permission ), $permission . ' needs a label' );
		}
	}

	public function test_an_unknown_permission_level_has_no_label() {
		$this->assertSame( '', WP_DownloadManager_File::permission_label( 42 ), 'An unknown permission level has no label rather than a wrong one.' );
	}

	public function test_a_logged_out_visitor_may_download_a_public_file_only() {
		$this->login_as( '' );

		$this->assertTrue( WP_DownloadManager_File::can_download( -1 ), 'A logged out visitor may download a public file.' );
		$this->assertFalse( WP_DownloadManager_File::can_download( 0 ), 'A logged out visitor may not download a registered-users file.' );
		$this->assertFalse( WP_DownloadManager_File::can_download( 10 ), 'A logged out visitor may not download a privileged file.' );
	}

	public function test_a_subscriber_may_download_a_registered_users_file() {
		$this->login_as( 'subscriber' );

		$this->assertTrue( WP_DownloadManager_File::can_download( 0 ), 'A subscriber may download a registered-users file.' );
		$this->assertFalse( WP_DownloadManager_File::can_download( 1 ), 'A subscriber may not download anything above their level.' );
	}

	public function test_an_editor_may_download_up_to_the_editor_level() {
		$this->login_as( 'editor' );

		$this->assertTrue( WP_DownloadManager_File::can_download( 7 ), 'An editor may download up to the editor level.' );
		$this->assertFalse( WP_DownloadManager_File::can_download( 10 ), 'An editor may not download above their level.' );
	}

	public function test_an_administrator_may_download_everything_permitted() {
		$this->login_as( 'administrator' );

		foreach ( array( -1, 0, 1, 2, 7, 10 ) as $permission ) {
			$this->assertTrue( WP_DownloadManager_File::can_download( $permission ), 'an administrator should reach level ' . $permission );
		}
	}

	public function test_the_user_level_is_derived_from_capabilities_not_the_removed_field() {
		$this->login_as( 'administrator' );
		$this->assertSame( 10, WP_DownloadManager_File::user_level(), 'An administrator is level ten.' );

		$this->login_as( 'editor' );
		$this->assertSame( 7, WP_DownloadManager_File::user_level(), 'An editor is level seven.' );

		$this->login_as( '' );
		$this->assertSame( -1, WP_DownloadManager_File::user_level(), 'And a visitor with no account is below them both.' );
	}

	public function test_an_upload_subfolder_that_escapes_the_downloads_directory_is_refused() {
		WP_DownloadManager_Options::set( 'path.dir', WP_CONTENT_DIR . '/dm-subfolder' );
		$this->make_download_file( 'sub/keep.txt' );

		$path = WP_DownloadManager_Options::get( 'path.dir' );

		$this->assertSame( '/', WP_DownloadManager_File::safe_subfolder( $path, '../../..' ), 'nothing stopped a hand-crafted POST dropping an upload anywhere writable' );
		$this->assertSame( '/', WP_DownloadManager_File::safe_subfolder( $path, "/sub\0/x" ), 'A subfolder carrying a null byte is refused and the root is used instead.' );
		$this->assertSame( '/sub', WP_DownloadManager_File::safe_subfolder( $path, '/sub' ), 'While an ordinary subfolder is accepted.' );

		$this->remove_download_files();
	}

	public function test_a_sibling_directory_with_a_shared_prefix_is_refused() {
		WP_DownloadManager_Options::set( 'path.dir', WP_CONTENT_DIR . '/dm-files' );
		$this->make_download_file( 'keep.txt' );
		wp_mkdir_p( WP_CONTENT_DIR . '/dm-files-public' );

		$this->assertSame(
			'/',
			WP_DownloadManager_File::safe_subfolder( WP_CONTENT_DIR . '/dm-files', '../dm-files-public' ),
			'without the separator in the comparison, /dm-files-public would pass as /dm-files'
		);

		$this->remove_directory( WP_CONTENT_DIR . '/dm-files-public' );
		$this->remove_download_files();
	}

	public function test_a_file_name_is_stripped_of_characters_that_do_not_belong() {
		WP_DownloadManager_Options::set( 'path.dir', WP_CONTENT_DIR . '/dm-rename' );
		$path = trailingslashit( WP_DownloadManager_Options::get( 'path.dir' ) );
		$this->make_download_file( 'my file (1).txt' );

		$renamed = WP_DownloadManager_File::rename_file( $path, 'my file (1).txt' );

		$this->assertSame( 'my_file_1.txt', $renamed, 'Characters that do not belong in a file name are replaced rather than kept.' );
		$this->assertFileExists( $path . $renamed, 'The file is stored under the stripped name, not the one that was uploaded.' );

		$this->remove_download_files();
	}

	public function test_a_clean_file_name_is_left_alone() {
		WP_DownloadManager_Options::set( 'path.dir', WP_CONTENT_DIR . '/dm-rename2' );
		$path = trailingslashit( WP_DownloadManager_Options::get( 'path.dir' ) );
		$this->make_download_file( 'clean.txt' );

		$this->assertSame( 'clean.txt', WP_DownloadManager_File::rename_file( $path, 'clean.txt' ), 'A name that is already clean is left alone.' );

		$this->remove_download_files();
	}

	public function test_a_download_url_uses_the_file_id_by_default() {
		$this->assertSame( get_option( 'home' ) . '/download/7/', WP_DownloadManager_File::download_url( 7, '/manual.pdf' ), 'The download URL is built from the file id.' );
	}

	public function test_a_download_url_can_use_the_file_name_instead() {
		WP_DownloadManager_Options::set( 'use_filename', 1 );

		$this->assertSame( get_option( 'home' ) . '/download/manual.pdf', WP_DownloadManager_File::download_url( 7, '/manual.pdf' ), 'Or from the file name, when that is what is configured.' );
	}

	public function test_a_download_url_falls_back_to_a_query_string_without_nice_permalinks() {
		WP_DownloadManager_Options::set( 'nice_permalink', 0 );

		$this->assertSame( get_option( 'home' ) . '/?dl_id=7', WP_DownloadManager_File::download_url( 7, '/manual.pdf' ), 'Without nice permalinks it falls back to a query string.' );
	}

	public function test_a_remote_download_url_is_not_mangled() {
		WP_DownloadManager_Options::set( 'use_filename', 1 );

		$this->assertSame(
			get_option( 'home' ) . '/download/https://example.com/remote.zip',
			WP_DownloadManager_File::download_url( 7, 'https://example.com/remote.zip' ),
			'the leading character is only stripped from a local path'
		);
	}

	public function test_now_is_the_site_local_timestamp_the_column_has_always_held() {
		update_option( 'gmt_offset', 8 );

		$this->assertEqualsWithDelta(
			time() + ( 8 * HOUR_IN_SECONDS ),
			WP_DownloadManager_File::now(),
			5,
			'file dates are stored shifted by the GMT offset and rendered back with gmdate()'
		);
	}

	public function test_the_maximum_upload_size_comes_from_wordpress() {
		$this->assertSame( (int) wp_max_upload_size(), WP_DownloadManager_File::max_upload_size(), 'The upload limit is the one WordPress reports, not one of the plugin.' );
	}

	public function test_the_endpoint_is_hooked_before_the_theme_gets_a_look_in() {
		$this->assertSame( 5, has_action( 'template_redirect', array( 'WP_DownloadManager_File', 'serve' ) ), 'The endpoint runs before the theme gets a look in.' );
	}

	public function test_the_endpoint_ignores_a_request_that_names_no_file() {
		set_query_var( 'dl_id', 0 );
		set_query_var( 'dl_name', '' );

		WP_DownloadManager_File::serve();

		// Reaching this line at all is the assertion: serve() returned instead of
		// ending the request.
		$this->assertTrue( true, 'a request with no download in it must fall through to WordPress' );
	}

	public function test_the_endpoint_refuses_a_file_the_visitor_may_not_have() {
		$this->login_as( '' );
		set_query_var( 'dl_id', $this->ids['editors'] );

		$this->expectException( WPDieException::class );

		WP_DownloadManager_File::serve();
	}

	public function test_the_endpoint_404s_an_unknown_file_id() {
		set_query_var( 'dl_id', 999999 );

		$this->expectException( WPDieException::class );

		WP_DownloadManager_File::serve();
	}

	public function test_the_endpoint_404s_a_hidden_file() {
		set_query_var( 'dl_id', $this->ids['hidden'] );

		$this->expectException( WPDieException::class );

		WP_DownloadManager_File::serve();
	}

	public function test_the_endpoint_404s_a_row_whose_file_is_no_longer_on_disk() {
		WP_DownloadManager_Options::set( 'path.dir', WP_CONTENT_DIR . '/dm-missing' );
		WP_DownloadManager_Options::set( 'method', 0 );
		set_query_var( 'dl_id', $this->ids['public'] );

		$this->expectException( WPDieException::class );

		WP_DownloadManager_File::serve();
	}

	public function test_the_endpoint_counts_a_hit_and_stamps_the_download_date() {
		WP_DownloadManager_Options::set( 'path.dir', WP_CONTENT_DIR . '/dm-serve' );
		WP_DownloadManager_Options::set( 'method', 1 );
		$this->make_download_file( 'manual.pdf' );

		$before = $this->fetch_file( $this->ids['public'] );

		set_query_var( 'dl_id', $this->ids['public'] );

		// Method 1 hands the visitor to the download URL, and wp_redirect() calls
		// header() -- which cannot succeed here, because PHPUnit has been printing
		// its progress dots since the bootstrap. Returning false from the filter
		// makes wp_redirect() give up before the header() call, exactly as the WP
		// test suite does for every other redirect it has to survive. Nothing about
		// the plugin is different in a real request, where nothing has been sent.
		add_filter( 'wp_redirect', '__return_false' );

		// finish() fires this action and then exits; hooking it is how the suite
		// observes the endpoint instead of being killed by it.
		add_action( 'wp_downloadmanager_served', fn() => throw new WPDieException( 'served' ) );

		try {
			WP_DownloadManager_File::serve();
			$this->fail( 'the endpoint should have ended the request' );
		} catch ( WPDieException $e ) {
			unset( $e );
		}

		$after = $this->fetch_file( $this->ids['public'] );

		$this->assertSame( (int) $before->file_hits + 1, (int) $after->file_hits, 'the hit counter is the whole point of the endpoint' );
		$this->assertNotSame( $before->file_last_downloaded_date, $after->file_last_downloaded_date, 'Serving a file stamps the download date.' );

		$this->remove_download_files();
	}
}
