<?php
/**
 * The download endpoint.
 *
 * DownloadManager_File::serve() is what actually hands a file over, so it is
 * where the permission model is enforced for real - the templates only decide
 * whether to show a link. It had no coverage at all before 2.0.0.
 *
 * @package WP-DownloadManager
 */

/**
 * Serving, permission gating and hit counting.
 */
class Test_File_Serving extends DownloadManager_TestCase {

	/**
	 * Give the endpoint a real directory to serve from.
	 */
	public function set_up() {
		parent::set_up();

		DownloadManager_Options::set( 'path.dir', WP_CONTENT_DIR . '/dm-serve-files' );
		DownloadManager_Options::set( 'path.url', content_url( 'dm-serve-files' ) );

		// serve() checks is_file() before it looks at the download method, so
		// even the redirect branch needs the local fixtures to exist on disk.
		foreach ( array( 'manual.pdf', 'members.zip', 'editors.doc', 'secret.exe' ) as $name ) {
			$this->make_download_file( $name, 'fixture' );
		}
	}

	/**
	 * Clean up.
	 */
	public function tear_down() {
		$this->remove_download_files();
		parent::tear_down();
	}

	/**
	 * Run serve() for a query var, capturing output and any wp_die().
	 *
	 * serve() exits on success, and wp_redirect() plus exit is unreachable from
	 * a test, so the redirect branch is asserted through the wp_redirect filter
	 * instead. Everything returns through a WPDieException or the filter.
	 *
	 * @param array $vars Query vars to set.
	 * @return array { redirect, died, output }
	 */
	private function serve( $vars ) {
		global $wp_query;

		foreach ( $vars as $key => $value ) {
			$wp_query->set( $key, $value );
		}

		$redirect = null;
		$capture  = function ( $location ) use ( &$redirect ) {
			$redirect = $location;
			// Returning false makes wp_redirect() a no-op, but serve() still
			// terminates afterwards - which is what the action below unwinds.
			return false;
		};
		add_filter( 'wp_redirect', $capture );

		// serve() ends every branch through DownloadManager_File::finish(),
		// which fires this action and then exits. Throwing here unwinds before
		// the exit so the runner survives.
		$served = static function () {
			throw new Exception( 'wp_downloadmanager_served' );
		};
		add_action( 'wp_downloadmanager_served', $served );

		$died   = null;
		$output = '';
		$depth  = ob_get_level();

		ob_start();
		try {
			DownloadManager_File::serve();
		} catch ( WPDieException $e ) {
			$died = $e->getMessage();
		} catch ( Exception $e ) {
			// The endpoint finished normally.
			unset( $e );
		} finally {
			while ( ob_get_level() > $depth ) {
				$output = ob_get_clean() . $output;
			}
		}

		remove_filter( 'wp_redirect', $capture );
		remove_action( 'wp_downloadmanager_served', $served );

		foreach ( array_keys( $vars ) as $key ) {
			$wp_query->set( $key, '' );
		}

		return compact( 'redirect', 'died', 'output' );
	}

	/**
	 * The endpoint fires its action before ending the request.
	 */
	public function test_serving_fires_the_served_action() {
		$fired = 0;
		$count = static function () use ( &$fired ) {
			++$fired;
			throw new Exception( 'stop' );
		};
		add_action( 'wp_downloadmanager_served', $count );

		global $wp_query;
		$wp_query->set( 'dl_id', $this->ids['public'] );
		add_filter( 'wp_redirect', '__return_false' );

		try {
			DownloadManager_File::serve();
		} catch ( Exception $e ) {
			unset( $e );
		}

		remove_filter( 'wp_redirect', '__return_false' );
		remove_action( 'wp_downloadmanager_served', $count );
		$wp_query->set( 'dl_id', '' );

		$this->assertSame( 1, $fired );
	}

	/**
	 * Nothing happens when neither query var is present.
	 */
	public function test_serve_ignores_an_unrelated_request() {
		$result = $this->serve( array() );

		$this->assertNull( $result['died'] );
		$this->assertNull( $result['redirect'] );
	}

	/**
	 * An unknown id is a 404 rather than a blank page.
	 */
	public function test_unknown_id_is_a_404() {
		$result = $this->serve( array( 'dl_id' => 999999 ) );

		$this->assertStringContainsString( 'Invalid File ID or File Name', $result['died'] );
	}

	/**
	 * A hidden file is not served, whoever asks.
	 */
	public function test_hidden_file_is_never_served() {
		$this->login_as( 'administrator' );

		$result = $this->serve( array( 'dl_id' => $this->ids['hidden'] ) );

		$this->assertStringContainsString( 'Invalid File ID or File Name', $result['died'] );
	}

	/**
	 * A public file redirects to the file under the default method.
	 */
	public function test_public_file_redirects() {
		$this->login_as( '' );

		$result = $this->serve( array( 'dl_id' => $this->ids['public'] ) );

		$this->assertNull( $result['died'] );
		$this->assertSame(
			DownloadManager_Options::get( 'path.url' ) . '/manual.pdf',
			$result['redirect']
		);
	}

	/**
	 * A logged-out visitor is refused a members-only file.
	 */
	public function test_members_only_file_is_refused_when_logged_out() {
		$this->login_as( '' );

		$result = $this->serve( array( 'dl_id' => $this->ids['members'] ) );

		$this->assertStringContainsString( 'do not have permission', $result['died'] );
		$this->assertNull( $result['redirect'] );
	}

	/**
	 * A subscriber gets it.
	 */
	public function test_members_only_file_is_served_to_a_subscriber() {
		$this->login_as( 'subscriber' );

		$result = $this->serve( array( 'dl_id' => $this->ids['members'] ) );

		$this->assertNull( $result['died'] );
		$this->assertNotNull( $result['redirect'] );
	}

	/**
	 * The role ladder is enforced at the endpoint, not just in the template.
	 *
	 * @dataProvider role_provider
	 *
	 * @param string $role    Role to log in as.
	 * @param bool   $allowed Whether the editor-only file should be served.
	 */
	public function test_role_gating( $role, $allowed ) {
		$this->login_as( $role );

		$result = $this->serve( array( 'dl_id' => $this->ids['editors'] ) );

		if ( $allowed ) {
			$this->assertNull( $result['died'], $role . ' should be allowed' );
		} else {
			$this->assertStringContainsString( 'do not have permission', (string) $result['died'], $role . ' should be refused' );
		}
	}

	/**
	 * Roles against the editor-only file.
	 *
	 * @return array
	 */
	public function role_provider() {
		return array(
			'logged out'    => array( '', false ),
			'subscriber'    => array( 'subscriber', false ),
			'contributor'   => array( 'contributor', false ),
			'author'        => array( 'author', false ),
			'editor'        => array( 'editor', true ),
			'administrator' => array( 'administrator', true ),
		);
	}

	/**
	 * A served file increments its hit count and stamps the download date.
	 */
	public function test_serving_counts_a_hit() {
		global $wpdb;

		$this->login_as( '' );
		$before = (int) $wpdb->get_var( $wpdb->prepare( "SELECT file_hits FROM {$wpdb->downloads} WHERE file_id = %d", $this->ids['public'] ) ); // phpcs:ignore WordPress.DB

		$this->serve( array( 'dl_id' => $this->ids['public'] ) );

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->downloads} WHERE file_id = %d", $this->ids['public'] ) ); // phpcs:ignore WordPress.DB

		$this->assertSame( $before + 1, (int) $row->file_hits );
		$this->assertGreaterThan( self::T0, (int) $row->file_last_downloaded_date );
	}

	/**
	 * A refused download does not count a hit.
	 */
	public function test_refused_download_does_not_count_a_hit() {
		global $wpdb;

		$this->login_as( '' );
		$before = (int) $wpdb->get_var( $wpdb->prepare( "SELECT file_hits FROM {$wpdb->downloads} WHERE file_id = %d", $this->ids['members'] ) ); // phpcs:ignore WordPress.DB

		$this->serve( array( 'dl_id' => $this->ids['members'] ) );

		$after = (int) $wpdb->get_var( $wpdb->prepare( "SELECT file_hits FROM {$wpdb->downloads} WHERE file_id = %d", $this->ids['members'] ) ); // phpcs:ignore WordPress.DB

		$this->assertSame( $before, $after );
	}

	/**
	 * A remote file redirects to its own URL rather than to the local path.
	 */
	public function test_remote_file_redirects_to_its_url() {
		$this->login_as( '' );

		$result = $this->serve( array( 'dl_id' => $this->ids['remote'] ) );

		$this->assertSame( 'https://example.com/remote.zip', $result['redirect'] );
	}

	/**
	 * Under the output method a missing local file is a 404, not a blank body.
	 */
	public function test_output_method_404s_a_missing_file() {
		DownloadManager_Options::set( 'method', 0 );
		$this->login_as( '' );

		$id = $this->insert_file(
			array(
				'file'            => '/never-written.zip',
				'file_name'       => 'Missing',
				'file_permission' => -1,
			)
		);

		$result = $this->serve( array( 'dl_id' => $id ) );

		$this->assertStringContainsString( 'File does not exist', (string) $result['died'] );
	}

	/**
	 * A missing local file 404s under the redirect method too.
	 *
	 * The existence check runs before the method branch, so a broken row does
	 * not turn into a redirect to a URL that 404s further downstream.
	 */
	public function test_redirect_method_404s_a_missing_file() {
		$this->login_as( '' );

		$id = $this->insert_file(
			array(
				'file'            => '/never-written.zip',
				'file_name'       => 'Missing',
				'file_permission' => -1,
			)
		);

		$result = $this->serve( array( 'dl_id' => $id ) );

		$this->assertStringContainsString( 'File does not exist', (string) $result['died'] );
		$this->assertNull( $result['redirect'] );
	}

	/**
	 * Under the output method an existing file is sent.
	 */
	public function test_output_method_sends_the_file() {
		DownloadManager_Options::set( 'method', 0 );
		$this->make_download_file( 'served.txt', 'file contents here' );

		$id = $this->insert_file(
			array(
				'file'            => '/served.txt',
				'file_name'       => 'Served',
				'file_permission' => -1,
			)
		);

		$this->login_as( '' );
		$result = $this->serve( array( 'dl_id' => $id ) );

		$this->assertSame( 'file contents here', $result['output'] );
	}

	/**
	 * Lookup by file name works when the option asks for it.
	 */
	public function test_lookup_by_file_name() {
		DownloadManager_Options::set( 'use_filename', 1 );
		$this->login_as( '' );

		$result = $this->serve( array( 'dl_name' => 'manual.pdf' ) );

		$this->assertNull( $result['died'] );
		$this->assertStringContainsString( 'manual.pdf', (string) $result['redirect'] );
	}

	/**
	 * A crafted file name cannot break the lookup query.
	 */
	public function test_lookup_by_file_name_is_not_injectable() {
		global $wpdb;

		DownloadManager_Options::set( 'use_filename', 1 );
		$this->login_as( '' );

		$result = $this->serve( array( 'dl_name' => 'manual.pdf" OR "1"="1' ) );

		$this->assertEmpty( $wpdb->last_error );
		$this->assertStringContainsString( 'Invalid File ID or File Name', (string) $result['died'] );
	}

	/**
	 * Asking by id while the option wants names finds nothing.
	 *
	 * The two lookups are deliberately exclusive; mixing them is how a file
	 * marked Hidden could be reached by the other key.
	 */
	public function test_id_lookup_is_ignored_when_names_are_configured() {
		DownloadManager_Options::set( 'use_filename', 1 );
		$this->login_as( '' );

		$result = $this->serve( array( 'dl_id' => $this->ids['public'] ) );

		$this->assertStringContainsString( 'Invalid File ID or File Name', (string) $result['died'] );
	}

	/**
	 * The feed is served for the reserved rss name.
	 */
	public function test_rss_name_loads_the_feed() {
		$this->login_as( '' );

		$result = $this->serve( array( 'dl_name' => 'rss' ) );

		$this->assertStringContainsString( '<rss', $result['output'] );
		$this->assertStringContainsString( 'The Manual', $result['output'] );
	}

	/**
	 * A file the visitor may not download is absent from the feed's links.
	 */
	public function test_feed_never_lists_hidden_files() {
		$this->login_as( '' );

		$result = $this->serve( array( 'dl_name' => 'rss' ) );

		$this->assertStringNotContainsString( 'Hidden File', $result['output'] );
	}

	/**
	 * The feed is well-formed XML.
	 */
	public function test_feed_is_well_formed() {
		$this->login_as( '' );

		$result = $this->serve( array( 'dl_name' => 'rss' ) );

		$xml = $result['output'];
		// Strip the leading declaration the template echoes before the doctype.
		$start = strpos( $xml, '<rss' );
		$this->assertNotFalse( $start );

		$previous = libxml_use_internal_errors( true );
		$doc      = simplexml_load_string( substr( $xml, $start ) );
		libxml_use_internal_errors( $previous );

		$this->assertNotFalse( $doc, 'the feed should parse as XML' );
		$this->assertSame( 1, $doc->channel->count() );
		$this->assertGreaterThan( 0, $doc->channel->item->count() );
	}

	/**
	 * A file name carrying an ampersand does not break the feed.
	 */
	public function test_feed_escapes_special_characters() {
		$this->insert_file(
			array(
				'file'      => '/tea.zip',
				'file_name' => 'Tea & Coffee',
				'file_des'  => 'Sugar & spice',
			)
		);

		$this->login_as( '' );
		$result = $this->serve( array( 'dl_name' => 'rss' ) );

		$start    = strpos( $result['output'], '<rss' );
		$previous = libxml_use_internal_errors( true );
		$doc      = simplexml_load_string( substr( $result['output'], $start ) );
		libxml_use_internal_errors( $previous );

		$this->assertNotFalse( $doc, 'an ampersand must not break the feed' );
	}
}
