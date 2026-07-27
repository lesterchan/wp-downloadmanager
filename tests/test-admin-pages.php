<?php
/**
 * Render tests for the two legacy admin screens.
 *
 * The two files download-manager.php and download-add.php are the only part of the plugin
 * 2.0.0 did not rewrite - they keep the legacy "plugin file as menu slug" form,
 * so they stay at the plugin root - and they were the only files with no
 * coverage at all, which is why the escaping sniffs had to be excluded for
 * them. These tests are what makes that pass safe to do.
 *
 * Every rendering bug found during the modernization was visible in the HTML
 * and invisible to PHP tooling: a translators comment printed on screen, a
 * value escaped twice, a stray cast that made a branch permanently false. So
 * these tests assert on the HTML.
 *
 * @package WP-DownloadManager
 */

/**
 * The Manage Downloads and Add File screens.
 */
class Test_Admin_Pages extends DownloadManager_TestCase {

	/**
	 * Become an administrator before each render.
	 */
	public function set_up() {
		parent::set_up();
		$this->become_download_admin();
	}

	/**
	 * Clean up any files the write paths created.
	 */
	public function tear_down() {
		$this->remove_download_files();
		parent::tear_down();
	}

	/**
	 * Every admin view, as [ file, $_GET ].
	 *
	 * Manage Downloads appears three times because its edit and delete branches
	 * are separate screens sharing one file, and each renders markup the list
	 * view never touches. Covering only the default view is how a rendering bug
	 * in the edit branch stays hidden.
	 *
	 * %FILE_ID% is replaced with a seeded file's real id.
	 *
	 * @return array
	 */
	public function admin_page_provider() {
		return array(
			'manage downloads' => array( 'download-manager.php', array() ),
			'edit file'        => array(
				'download-manager.php',
				array(
					'mode' => 'edit',
					'id'   => '%FILE_ID%',
				),
			),
			'delete file'      => array(
				'download-manager.php',
				array(
					'mode' => 'delete',
					'id'   => '%FILE_ID%',
				),
			),
			'add file'         => array( 'download-add.php', array() ),
		);
	}

	/**
	 * Resolve %FILE_ID% in provider args against a seeded file.
	 *
	 * @param array $get  Query args from the provider.
	 * @param array $args Overrides for the seeded file.
	 * @return array
	 */
	private function resolve( $get, $args = array() ) {
		$file_id = $this->insert_file( $args );

		foreach ( $get as $key => $value ) {
			if ( '%FILE_ID%' === $value ) {
				$get[ $key ] = (string) $file_id;
			}
		}

		return $get;
	}

	/**
	 * Each page renders without raising a PHP diagnostic.
	 *
	 * @dataProvider admin_page_provider
	 *
	 * @param string $file File name.
	 * @param array  $get  Query args.
	 */
	public function test_page_renders_without_php_diagnostics( $file, $get ) {
		$get = $this->resolve( $get );

		$html = $this->render_admin_page( $file, $get );

		$this->assertNotEmpty( $html, $file . ' produced no output' );
		$this->assertSame(
			array(),
			$this->admin_page_notices,
			$file . ' raised PHP diagnostics: ' . implode( ' | ', $this->admin_page_notices )
		);
	}

	/**
	 * No page leaks source into the markup.
	 *
	 * A translators comment placed in HTML context rather than immediately
	 * before its gettext call renders literally on screen.
	 *
	 * @dataProvider admin_page_provider
	 *
	 * @param string $file File name.
	 * @param array  $get  Query args.
	 */
	public function test_page_does_not_leak_source_into_markup( $file, $get ) {
		$get = $this->resolve( $get );

		$html = $this->render_admin_page( $file, $get );

		$this->assertStringNotContainsString( 'translators:', $html, $file );
		$this->assertStringNotContainsString( '<?php', $html, $file );
		$this->assertStringNotContainsString( 'Fatal error', $html, $file );
		$this->assertStringNotContainsString( 'Undefined', $html, $file );
	}

	/**
	 * No view double escapes the file name it renders.
	 *
	 * Escaping an already-escaped value renders &amp;amp; on screen. That is
	 * exactly what esc_js( esc_attr( ... ) ) did to the delete confirmation
	 * before 2.0.0 moved it to a data attribute.
	 *
	 * @dataProvider admin_page_provider
	 *
	 * @param string $file File name.
	 * @param array  $get  Query args.
	 */
	public function test_no_view_double_escapes( $file, $get ) {
		$get = $this->resolve( $get, array( 'file_name' => 'Tabs & "spaces"' ) );

		$html = $this->render_admin_page( $file, $get );

		$this->assertStringNotContainsString( '&amp;amp;', $html, $file );
		$this->assertStringNotContainsString( '&amp;quot;', $html, $file );
		$this->assertStringNotContainsString( '&amp;#039;', $html, $file );
	}

	/**
	 * Every page is behind the plugin capability.
	 *
	 * @dataProvider admin_page_provider
	 *
	 * @param string $file File name.
	 * @param array  $get  Query args.
	 */
	public function test_page_requires_the_capability( $file, $get ) {
		$get = $this->resolve( $get );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->expectException( 'WPDieException' );
		$this->render_admin_page( $file, $get );
	}

	/**
	 * Manage Downloads lists the files that exist.
	 */
	public function test_manage_lists_files() {
		$html = $this->render_admin_page( 'download-manager.php' );

		$this->assertStringContainsString( 'The Manual', $html );
		$this->assertStringContainsString( 'Members Bundle', $html );
		// Hidden files are administrative rows, so the manager does show them.
		$this->assertStringContainsString( 'Hidden File', $html );
	}

	/**
	 * The totals panel adds up the whole table.
	 */
	public function test_manage_shows_totals() {
		$html = $this->render_admin_page( 'download-manager.php' );

		$this->assertStringContainsString( 'Total Files:', $html );
		$this->assertStringContainsString( 'Total Hits:', $html );
		$this->assertStringContainsString( 'Total Bandwidth:', $html );
	}

	/**
	 * The keyword filter narrows the list.
	 */
	public function test_manage_search_filters_the_list() {
		$html = $this->render_admin_page( 'download-manager.php', array( 'search' => 'Manual' ) );

		$this->assertStringContainsString( 'The Manual', $html );
		$this->assertStringNotContainsString( 'Members Bundle', $html );
		$this->assertSame( array(), $this->admin_page_notices );
	}

	/**
	 * A search term with a quote cannot break the query.
	 */
	public function test_manage_search_with_a_quote_is_safe() {
		global $wpdb;

		$html = $this->render_admin_page( 'download-manager.php', array( 'search' => "' OR 1=1 -- " ) );

		$this->assertEmpty( $wpdb->last_error );
		$this->assertStringContainsString( 'No Files Found', $html );
	}

	/**
	 * A search term with LIKE wildcards is matched literally.
	 */
	public function test_manage_search_escapes_wildcards() {
		$this->insert_file(
			array(
				'file'      => '/100_percent.zip',
				'file_name' => '100% Complete',
			)
		);

		$html = $this->render_admin_page( 'download-manager.php', array( 'search' => '%' ) );

		$this->assertStringContainsString( 'Complete', $html );
		$this->assertStringNotContainsString( 'Members Bundle', $html );
	}

	/**
	 * The search term comes back in the filter box without being mangled.
	 */
	public function test_manage_search_term_round_trips_into_the_box() {
		$html = $this->render_admin_page( 'download-manager.php', array( 'search' => 'Tabs & "spaces"' ) );

		$this->assertStringContainsString( 'value="Tabs &amp; &quot;spaces&quot;"', $html );
		$this->assertStringNotContainsString( '&amp;amp;', $html );
	}

	/**
	 * Sorting is honoured and reported.
	 *
	 * @dataProvider sort_provider
	 *
	 * @param string $by    The ?by= value.
	 * @param string $label The label the page reports.
	 */
	public function test_manage_sorting( $by, $label ) {
		$html = $this->render_admin_page(
			'download-manager.php',
			array(
				'by'    => $by,
				'order' => 'desc',
			)
		);

		$this->assertStringContainsString( $label, $html );
		$this->assertStringContainsString( 'Descending', $html );
		$this->assertSame( array(), $this->admin_page_notices );
	}

	/**
	 * Every sort key the filter form offers.
	 *
	 * @return array
	 */
	public function sort_provider() {
		return array(
			'id'                   => array( 'id', 'File ID' ),
			'file'                 => array( 'file', 'File' ),
			'name'                 => array( 'name', 'File Name' ),
			'date'                 => array( 'date', 'File Date' ),
			'updated_date'         => array( 'updated_date', 'File Updated Date' ),
			'last_downloaded_date' => array( 'last_downloaded_date', 'File Last Downloaded Date' ),
			'size'                 => array( 'size', 'File Size' ),
			'category'             => array( 'category', 'File Category' ),
			'hits'                 => array( 'hits', 'File Hits' ),
			'permission'           => array( 'permission', 'File Permission' ),
			'unknown falls back'   => array( 'nonsense', 'File Name' ),
		);
	}

	/**
	 * Paging appears once there are more files than fit on a page.
	 */
	public function test_manage_paging() {
		for ( $i = 0; $i < 12; $i++ ) {
			$this->insert_file( array( 'file_name' => 'Extra ' . $i ) );
		}

		$html = $this->render_admin_page( 'download-manager.php', array( 'perpage' => '10' ) );

		$this->assertStringContainsString( 'Next Page', $html );
		$this->assertStringContainsString( 'Pages', $html );

		$page_two = $this->render_admin_page(
			'download-manager.php',
			array(
				'perpage'  => '10',
				'filepage' => '2',
			)
		);

		$this->assertStringContainsString( 'Previous Page', $page_two );
	}

	/**
	 * An empty table renders the empty state rather than a broken table.
	 */
	public function test_manage_with_no_files() {
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->downloads}" ); // phpcs:ignore WordPress.DB

		$html = $this->render_admin_page( 'download-manager.php' );

		$this->assertStringContainsString( 'No Files Found', $html );
		$this->assertSame( array(), $this->admin_page_notices );
	}

	/**
	 * The edit screen is populated from the row it is editing.
	 */
	public function test_edit_screen_is_populated() {
		$file_id = $this->insert_file(
			array(
				'file'      => '/editable.zip',
				'file_name' => 'Editable File',
				'file_des'  => 'A description.',
				'file_hits' => 42,
			)
		);

		$html = $this->render_admin_page(
			'download-manager.php',
			array(
				'mode' => 'edit',
				'id'   => (string) $file_id,
			)
		);

		$this->assertStringContainsString( 'Edit A File', $html );
		$this->assertStringContainsString( 'value="Editable File"', $html );
		$this->assertStringContainsString( 'A description.', $html );
		$this->assertStringContainsString( 'name="file_id" value="' . $file_id . '"', $html );
		// The timestamp selects render from the stored date.
		$this->assertStringContainsString( 'id="file_timestamp_day"', $html );
		$this->assertStringContainsString( 'id="file_timestamp_month"', $html );
	}

	/**
	 * The edit screen carries the date data attributes the script reads.
	 *
	 * These replaced an inline jQuery function with both sets of date parts
	 * interpolated into JavaScript string literals.
	 */
	public function test_edit_screen_exposes_dates_as_data_attributes() {
		$file_id = $this->insert_file();

		$html = $this->render_admin_page(
			'download-manager.php',
			array(
				'mode' => 'edit',
				'id'   => (string) $file_id,
			)
		);

		$this->assertStringContainsString( 'id="edit_usetodaydate"', $html );
		$this->assertStringContainsString( 'data-actual="', $html );
		$this->assertStringContainsString( 'data-today="', $html );
		$this->assertStringNotContainsString( 'jQuery', $html );
		$this->assertStringNotContainsString( 'onclick=', $html );

		// The attributes hold valid JSON with the six date parts.
		preg_match( '/data-actual="([^"]*)"/', $html, $m );
		$actual = json_decode( html_entity_decode( $m[1] ), true );
		$this->assertIsArray( $actual );
		foreach ( array( 'day', 'month', 'year', 'hour', 'minute', 'second' ) as $part ) {
			$this->assertArrayHasKey( $part, $actual );
		}
	}

	/**
	 * The delete screen shows what is about to go and asks first.
	 */
	public function test_delete_screen_confirms() {
		$file_id = $this->insert_file(
			array(
				'file'      => '/doomed.zip',
				'file_name' => 'Doomed File',
			)
		);

		$html = $this->render_admin_page(
			'download-manager.php',
			array(
				'mode' => 'delete',
				'id'   => (string) $file_id,
			)
		);

		$this->assertStringContainsString( 'Delete A File', $html );
		$this->assertStringContainsString( 'Doomed File', $html );
		// The confirmation is a data attribute now, not an inline onclick.
		$this->assertStringContainsString( 'data-confirm="', $html );
		$this->assertStringNotContainsString( 'onclick=', $html );
		// A local file offers to be removed from disk; a remote one cannot be.
		$this->assertStringContainsString( 'name="unlinkfile"', $html );
	}

	/**
	 * A remote file is not offered for deletion from the server.
	 */
	public function test_delete_screen_hides_unlink_for_remote_files() {
		$file_id = $this->insert_file( array( 'file' => 'https://example.com/remote.zip' ) );

		$html = $this->render_admin_page(
			'download-manager.php',
			array(
				'mode' => 'delete',
				'id'   => (string) $file_id,
			)
		);

		$this->assertStringNotContainsString( 'name="unlinkfile"', $html );
	}

	/**
	 * The Add File screen renders its three file sources.
	 */
	public function test_add_screen_offers_every_file_source() {
		$html = $this->render_admin_page( 'download-add.php' );

		$this->assertStringContainsString( 'Add A File', $html );
		$this->assertStringContainsString( 'name="file_type" value="0"', $html );
		$this->assertStringContainsString( 'name="file_type" value="1"', $html );
		$this->assertStringContainsString( 'name="file_type" value="2"', $html );
		$this->assertStringContainsString( 'name="file_upload"', $html );
		$this->assertStringContainsString( 'name="file_remote"', $html );
	}

	/**
	 * The radio auto-select is a data attribute, not an inline handler.
	 */
	public function test_add_screen_uses_data_attributes_not_inline_handlers() {
		$html = $this->render_admin_page( 'download-add.php' );

		$this->assertStringContainsString( 'data-checks="file_type_0"', $html );
		$this->assertStringContainsString( 'data-checks="file_type_1"', $html );
		$this->assertStringContainsString( 'data-checks="file_type_2"', $html );
		$this->assertStringNotContainsString( 'onclick=', $html );
		$this->assertStringNotContainsString( 'jQuery', $html );
	}

	/**
	 * The category select is built from the configured categories.
	 *
	 * Index 0 is the "all categories" label rather than a real category, so it
	 * must not appear as a choice.
	 */
	public function test_add_screen_lists_categories() {
		$html = $this->render_admin_page( 'download-add.php' );

		$this->assertStringContainsString( '<option value="1">General</option>', $html );
		$this->assertStringContainsString( '<option value="2">Software</option>', $html );
		$this->assertStringNotContainsString( '<option value="0"></option>', $html );
	}

	/**
	 * A file whose category was deleted still lists, without a PHP notice.
	 */
	public function test_manage_handles_a_file_in_a_deleted_category() {
		$this->insert_file(
			array(
				'file_name'     => 'Orphaned File',
				'file_category' => 99,
			)
		);

		$html = $this->render_admin_page( 'download-manager.php' );

		$this->assertStringContainsString( 'Orphaned File', $html );
		$this->assertStringContainsString( 'N/A', $html );
		$this->assertSame( array(), $this->admin_page_notices );
	}

	/**
	 * The browse-file select lists what is in the downloads directory.
	 */
	public function test_add_screen_lists_files_on_disk() {
		$this->make_download_file( 'on-disk.txt' );
		$this->make_download_file( 'nested/deeper.txt' );

		$html = $this->render_admin_page( 'download-add.php' );

		$this->assertStringContainsString( '/on-disk.txt', $html );
		$this->assertStringContainsString( '/nested/deeper.txt', $html );
		// And the upload-target select lists the folder.
		$this->assertStringContainsString( 'value="/nested"', $html );
	}

	/**
	 * Both screens enqueue the shared form script rather than inlining it.
	 */
	public function test_screens_enqueue_the_form_script() {
		foreach ( array( 'download-manager.php', 'download-add.php' ) as $file ) {
			wp_dequeue_script( 'wp-downloadmanager-forms' );
			wp_deregister_script( 'wp-downloadmanager-forms' );

			$this->render_admin_page( $file );

			$this->assertTrue(
				wp_script_is( 'wp-downloadmanager-forms', 'enqueued' ),
				$file . ' should enqueue the form script'
			);
		}
	}
}
