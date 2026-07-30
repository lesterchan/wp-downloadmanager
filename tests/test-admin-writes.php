<?php
/**
 * Adding, editing and deleting files.
 *
 * These are the screens that write, so every one of them has to prove it
 * checked a nonce and a capability first, and that what it stored is what was
 * submitted rather than a doubled-slashed copy of it.
 *
 * @package WP-DownloadManager
 */

/**
 * The three write paths of the Downloads screens.
 */
class WP_DownloadManager_Admin_Writes_Test extends WP_DownloadManager_TestCase {

	/**
	 * Point the plugin at a scratch downloads directory.
	 */
	public function set_up() {
		parent::set_up();

		WP_DownloadManager_Admin::load_list_table();
		$this->on_admin_screen();
		WP_DownloadManager_Options::set( 'path.dir', WP_CONTENT_DIR . '/dm-writes' );
		$this->make_download_file( 'brochure.pdf', 'pretend this is a PDF' );
		$this->become_download_admin();
	}

	/**
	 * Take the scratch directory away again.
	 */
	public function tear_down() {
		$this->remove_download_files();

		parent::tear_down();
	}

	/**
	 * A complete Add File submission.
	 *
	 * @param array $overrides Field overrides.
	 * @return array
	 */
	protected function add_post( $overrides = array() ) {
		return array_merge(
			array(
				'do'                    => 'Add File',
				'_wpnonce'              => $this->nonce( 'wp_downloadmanager_add' ),
				'file_type'             => '0',
				'file'                  => '/brochure.pdf',
				'file_name'             => 'The Brochure',
				'file_des'              => 'A brochure.',
				'file_cat'              => '1',
				'file_hits'             => '0',
				'file_permission'       => '-1',
				'auto_filesize'         => '1',
				'file_timestamp_day'    => '15',
				'file_timestamp_month'  => '6',
				'file_timestamp_year'   => '2020',
				'file_timestamp_hour'   => '8',
				'file_timestamp_minute' => '30',
				'file_timestamp_second' => '0',
			),
			$overrides
		);
	}

	/**
	 * A complete Edit File submission.
	 *
	 * @param int   $file_id   File being edited.
	 * @param array $overrides Field overrides.
	 * @return array
	 */
	protected function edit_post( $file_id, $overrides = array() ) {
		return array_merge(
			array(
				'do'              => 'Edit File',
				'_wpnonce'        => $this->nonce( 'wp_downloadmanager_edit_' . $file_id ),
				'file_id'         => (string) $file_id,
				'file_type'       => '-1',
				'file_name'       => 'Renamed',
				'file_des'        => 'Edited.',
				'file_cat'        => '2',
				'file_hits'       => '4',
				'file_permission' => '0',
				'auto_filesize'   => '1',
			),
			$overrides
		);
	}

	public function test_adding_a_file_stores_a_row() {
		$before = $this->count_files();

		$this->render( array( 'WP_DownloadManager_Admin', 'render_add' ), array(), $this->add_post() );

		$this->assertSame( $before + 1, $this->count_files() );
	}

	public function test_adding_a_file_says_so_with_its_id() {
		$html = $this->render( array( 'WP_DownloadManager_Admin', 'render_add' ), array(), $this->add_post() );

		$this->assertStringContainsString( 'The Brochure added', $html );
		$this->assertStringContainsString( 'file ID', $html );
	}

	public function test_adding_a_file_stores_the_submitted_values() {
		global $wpdb;

		$this->render( array( 'WP_DownloadManager_Admin', 'render_add' ), array(), $this->add_post() );

		$row = $wpdb->get_row( "SELECT * FROM {$wpdb->downloads} ORDER BY file_id DESC LIMIT 1" );

		$this->assertSame( 'The Brochure', $row->file_name );
		$this->assertSame( 'A brochure.', $row->file_des );
		$this->assertSame( '/brochure.pdf', $row->file );
		$this->assertSame( 1, (int) $row->file_category );
		$this->assertSame( -1, (int) $row->file_permission );
	}

	public function test_adding_a_file_does_not_double_slash_the_text() {
		global $wpdb;

		$this->render(
			array( 'WP_DownloadManager_Admin', 'render_add' ),
			array(),
			$this->add_post( array( 'file_name' => "O'Reilly's Guide" ) )
		);

		$row = $wpdb->get_row( "SELECT * FROM {$wpdb->downloads} ORDER BY file_id DESC LIMIT 1" );

		$this->assertSame( "O'Reilly's Guide", $row->file_name, 'the old code built its own INSERT and slashed every value twice' );
	}

	public function test_adding_a_file_detects_its_size() {
		global $wpdb;

		$this->render( array( 'WP_DownloadManager_Admin', 'render_add' ), array(), $this->add_post() );

		$row = $wpdb->get_row( "SELECT * FROM {$wpdb->downloads} ORDER BY file_id DESC LIMIT 1" );

		$this->assertSame( strlen( 'pretend this is a PDF' ), (int) $row->file_size );
	}

	public function test_a_typed_size_wins_when_detection_is_switched_off() {
		global $wpdb;

		$this->render(
			array( 'WP_DownloadManager_Admin', 'render_add' ),
			array(),
			$this->add_post(
				array(
					'auto_filesize' => '0',
					'file_size'     => '4096',
				)
			)
		);

		$row = $wpdb->get_row( "SELECT * FROM {$wpdb->downloads} ORDER BY file_id DESC LIMIT 1" );

		$this->assertSame( 4096, (int) $row->file_size );
	}

	public function test_a_blank_name_falls_back_to_the_file_on_disk() {
		global $wpdb;

		$this->render( array( 'WP_DownloadManager_Admin', 'render_add' ), array(), $this->add_post( array( 'file_name' => '' ) ) );

		$row = $wpdb->get_row( "SELECT * FROM {$wpdb->downloads} ORDER BY file_id DESC LIMIT 1" );

		$this->assertSame( 'brochure.pdf', $row->file_name );
	}

	/**
	 * A remote URL is stored as the file, not quietly replaced by the Browse
	 * select's value.
	 *
	 * Until this release handle_add() passed a hardcoded 0 as the source instead
	 * of the posted file_type, so the Add File screen ignored its own four-way
	 * radio: an upload and a remote URL were both accepted by the form and then
	 * discarded, and the row was written from whatever Browse happened to hold.
	 * handle_edit() always read the field, which is why editing looked fine.
	 *
	 * The refusal test below covers the same field from the other side -- with
	 * the source hardcoded, a rejected scheme never reaches the check and no
	 * error appears -- but this is the assertion the bug actually needed: that
	 * choosing "Enter URL" puts that URL in the row.
	 *
	 * @return void
	 */
	public function test_adding_a_remote_file_stores_the_url_it_was_given() {
		global $wpdb;

		$this->render(
			array( 'WP_DownloadManager_Admin', 'render_add' ),
			array(),
			$this->add_post(
				array(
					'file_type'   => '2',
					'file_remote' => 'https://example.com/remote-brochure.pdf',
					'file'        => '/brochure.pdf',
				)
			)
		);

		$row = $wpdb->get_row( "SELECT * FROM {$wpdb->downloads} ORDER BY file_id DESC LIMIT 1" );

		$this->assertSame(
			'https://example.com/remote-brochure.pdf',
			$row->file,
			'the remote URL was discarded and the Browse value stored instead'
		);
	}

	public function test_adding_a_remote_file_on_a_refused_scheme_says_so() {
		$before = $this->count_files();

		$html = $this->render(
			array( 'WP_DownloadManager_Admin', 'render_add' ),
			array(),
			$this->add_post(
				array(
					'file_type'   => '2',
					'file_remote' => 'file:///etc/passwd',
				)
			)
		);

		$this->assertStringContainsString( 'could not be read', $html );
		$this->assertSame( $before, $this->count_files(), 'and nothing is stored' );
	}

	public function test_the_permission_is_constrained_to_the_levels_the_select_offers() {
		global $wpdb;

		$this->render( array( 'WP_DownloadManager_Admin', 'render_add' ), array(), $this->add_post( array( 'file_permission' => '5' ) ) );

		$row = $wpdb->get_row( "SELECT * FROM {$wpdb->downloads} ORDER BY file_id DESC LIMIT 1" );

		$this->assertSame( -1, (int) $row->file_permission, 'a hand-crafted POST cannot invent a permission level' );
	}

	public function test_adding_a_file_without_a_nonce_is_refused() {
		$before = $this->count_files();

		$this->expectException( WPDieException::class );

		try {
			$this->render(
				array( 'WP_DownloadManager_Admin', 'render_add' ),
				array(),
				$this->add_post( array( '_wpnonce' => 'not a nonce' ) )
			);
		} finally {
			$this->assertSame( $before, $this->count_files(), 'nothing may be written before the nonce is checked' );
		}
	}

	public function test_adding_a_file_as_a_subscriber_is_refused() {
		$this->login_as( 'subscriber' );

		$this->expectException( WPDieException::class );

		$this->render( array( 'WP_DownloadManager_Admin', 'render_add' ), array(), $this->add_post() );
	}

	public function test_editing_a_file_stores_the_submitted_values() {
		$id = $this->ids['public'];

		$this->render(
			array( 'WP_DownloadManager_Admin', 'render_downloads' ),
			array(
				'action' => 'edit',
				'id'     => $id,
			),
			$this->edit_post( $id )
		);

		$row = $this->fetch_file( $id );

		$this->assertSame( 'Renamed', $row->file_name );
		$this->assertSame( 'Edited.', $row->file_des );
		$this->assertSame( 2, (int) $row->file_category );
		$this->assertSame( 0, (int) $row->file_permission );
		$this->assertSame( 4, (int) $row->file_hits );
	}

	public function test_editing_a_file_leaves_the_file_alone_when_asked_to() {
		$id = $this->ids['public'];

		$this->render(
			array( 'WP_DownloadManager_Admin', 'render_downloads' ),
			array(
				'action' => 'edit',
				'id'     => $id,
			),
			$this->edit_post( $id )
		);

		$this->assertSame( '/manual.pdf', $this->fetch_file( $id )->file, 'file_type -1 means "keep the current file"' );
	}

	public function test_editing_a_file_stamps_the_updated_date() {
		$id     = $this->ids['public'];
		$before = $this->fetch_file( $id )->file_updated_date;

		$this->render(
			array( 'WP_DownloadManager_Admin', 'render_downloads' ),
			array(
				'action' => 'edit',
				'id'     => $id,
			),
			$this->edit_post( $id )
		);

		$this->assertNotSame( $before, $this->fetch_file( $id )->file_updated_date );
	}

	public function test_editing_a_file_can_reset_its_hit_count() {
		$id = $this->ids['public'];

		$this->render(
			array( 'WP_DownloadManager_Admin', 'render_downloads' ),
			array(
				'action' => 'edit',
				'id'     => $id,
			),
			$this->edit_post( $id, array( 'reset_filehits' => '1' ) )
		);

		$this->assertSame( 0, (int) $this->fetch_file( $id )->file_hits );
	}

	public function test_editing_a_file_leaves_its_date_alone_unless_asked() {
		$id = $this->ids['public'];

		$this->render(
			array( 'WP_DownloadManager_Admin', 'render_downloads' ),
			array(
				'action' => 'edit',
				'id'     => $id,
			),
			$this->edit_post(
				$id,
				array(
					'file_timestamp_day'   => '1',
					'file_timestamp_month' => '1',
					'file_timestamp_year'  => '2001',
				)
			)
		);

		$this->assertSame( (string) self::T0, $this->fetch_file( $id )->file_date, 'the date selects only take effect with the box ticked' );
	}

	public function test_editing_a_file_can_change_its_date() {
		$id = $this->ids['public'];

		$this->render(
			array( 'WP_DownloadManager_Admin', 'render_downloads' ),
			array(
				'action' => 'edit',
				'id'     => $id,
			),
			$this->edit_post(
				$id,
				array(
					'edit_filetimestamp'    => '1',
					'file_timestamp_day'    => '1',
					'file_timestamp_month'  => '1',
					'file_timestamp_year'   => '2001',
					'file_timestamp_hour'   => '0',
					'file_timestamp_minute' => '0',
					'file_timestamp_second' => '0',
				)
			)
		);

		$this->assertSame( (string) gmmktime( 0, 0, 0, 1, 1, 2001 ), $this->fetch_file( $id )->file_date );
	}

	public function test_saving_an_unchanged_row_reports_success() {
		$id = $this->ids['public'];

		$post = $this->edit_post( $id );
		$this->render(
			array( 'WP_DownloadManager_Admin', 'render_downloads' ),
			array(
				'action' => 'edit',
				'id'     => $id,
			),
			$post
		);

		$post['_wpnonce'] = $this->nonce( 'wp_downloadmanager_edit_' . $id );
		$html             = $this->render(
			array( 'WP_DownloadManager_Admin', 'render_downloads' ),
			array(
				'action' => 'edit',
				'id'     => $id,
			),
			$post
		);

		$this->assertStringContainsString( 'saved', $html, 'update() returns 0 when the row already held these values, which is success' );
		$this->assertStringNotContainsString( 'could not be saved', $html );
	}

	public function test_editing_a_file_with_the_wrong_nonce_is_refused() {
		$id = $this->ids['public'];

		$this->expectException( WPDieException::class );

		try {
			$this->render(
				array( 'WP_DownloadManager_Admin', 'render_downloads' ),
				array(
					'action' => 'edit',
					'id'     => $id,
				),
				$this->edit_post( $id, array( '_wpnonce' => $this->nonce( 'wp_downloadmanager_edit_999' ) ) )
			);
		} finally {
			$this->assertSame( 'The Manual', $this->fetch_file( $id )->file_name, 'the row is untouched' );
		}
	}

	public function test_deleting_a_file_removes_its_row() {
		$id = $this->ids['public'];

		$html = $this->render(
			array( 'WP_DownloadManager_Admin', 'render_downloads' ),
			array(
				'action' => 'delete',
				'id'     => $id,
			),
			array(
				'do'       => 'delete',
				'_wpnonce' => $this->nonce( 'wp_downloadmanager_delete_' . $id ),
				'file_id'  => (string) $id,
			)
		);

		$this->assertNull( $this->fetch_file( $id ) );
		$this->assertStringContainsString( '1 file deleted', $html );
	}

	public function test_deleting_a_file_lands_back_on_the_list() {
		$id = $this->ids['public'];

		$html = $this->render(
			array( 'WP_DownloadManager_Admin', 'render_downloads' ),
			array(
				'action' => 'delete',
				'id'     => $id,
			),
			array(
				'do'       => 'delete',
				'_wpnonce' => $this->nonce( 'wp_downloadmanager_delete_' . $id ),
				'file_id'  => (string) $id,
			)
		);

		$this->assertStringContainsString( 'Members Bundle', $html, 'the confirmation screen has nothing left to confirm' );
		$this->assertStringNotContainsString( 'data-confirm=', $html );
	}

	public function test_deleting_a_file_can_remove_it_from_disk_too() {
		$path = $this->make_download_file( 'doomed.txt' );
		$id   = $this->insert_file( array( 'file' => '/doomed.txt' ) );

		$this->render(
			array( 'WP_DownloadManager_Admin', 'render_downloads' ),
			array(
				'action' => 'delete',
				'id'     => $id,
			),
			array(
				'do'         => 'delete',
				'_wpnonce'   => $this->nonce( 'wp_downloadmanager_delete_' . $id ),
				'file_id'    => (string) $id,
				'unlinkfile' => '1',
			)
		);

		$this->assertFileDoesNotExist( $path );
		$this->assertNull( $this->fetch_file( $id ) );
	}

	public function test_deleting_a_file_leaves_the_file_on_disk_unless_asked() {
		$path = $this->make_download_file( 'spared.txt' );
		$id   = $this->insert_file( array( 'file' => '/spared.txt' ) );

		$this->render(
			array( 'WP_DownloadManager_Admin', 'render_downloads' ),
			array(
				'action' => 'delete',
				'id'     => $id,
			),
			array(
				'do'       => 'delete',
				'_wpnonce' => $this->nonce( 'wp_downloadmanager_delete_' . $id ),
				'file_id'  => (string) $id,
			)
		);

		$this->assertFileExists( $path, 'removing the row is not the same as removing the file' );
		$this->assertNull( $this->fetch_file( $id ) );
	}

	public function test_deleting_a_file_without_a_nonce_is_refused() {
		$id = $this->ids['public'];

		$this->expectException( WPDieException::class );

		try {
			$this->render(
				array( 'WP_DownloadManager_Admin', 'render_downloads' ),
				array(
					'action' => 'delete',
					'id'     => $id,
				),
				array(
					'do'       => 'delete',
					'_wpnonce' => 'not a nonce',
					'file_id'  => (string) $id,
				)
			);
		} finally {
			$this->assertNotNull( $this->fetch_file( $id ), 'the row survives' );
		}
	}

	public function test_a_bulk_delete_removes_every_ticked_row() {
		// Driven through $_GET, because the form around the table is method="get".
		// Sending this as POST is what hid two bugs at once: the handler read
		// $_POST, which a browser never fills here, and it verified a nonce the
		// form did not emit.
		$html = $this->render(
			array( 'WP_DownloadManager_Admin', 'render_downloads' ),
			array(
				'action'   => 'delete',
				'_wpnonce' => $this->nonce( 'bulk-downloads' ),
				'file_ids' => array( $this->ids['public'], $this->ids['members'] ),
			)
		);

		$this->assertNull( $this->fetch_file( $this->ids['public'] ) );
		$this->assertNull( $this->fetch_file( $this->ids['members'] ) );
		$this->assertNotNull( $this->fetch_file( $this->ids['editors'] ), 'and leaves the rest alone' );
		$this->assertStringContainsString( '2 files deleted', $html );
	}

	public function test_a_bulk_delete_without_a_nonce_is_refused() {
		$this->expectException( WPDieException::class );

		try {
			$this->render(
				array( 'WP_DownloadManager_Admin', 'render_downloads' ),
				array(
					'action'   => 'delete',
					'_wpnonce' => 'not a nonce',
					'file_ids' => array( $this->ids['public'] ),
				)
			);
		} finally {
			$this->assertNotNull( $this->fetch_file( $this->ids['public'] ) );
		}
	}

	public function test_a_bulk_delete_of_nothing_deletes_nothing() {
		$this->render(
			array( 'WP_DownloadManager_Admin', 'render_downloads' ),
			array(
				'action'   => 'delete',
				'_wpnonce' => $this->nonce( 'bulk-downloads' ),
				'file_ids' => array(),
			)
		);

		$this->assertSame( 5, $this->count_files() );
	}

	/**
	 * The form emits one nonce, and it is the one the handler checks.
	 *
	 * The regression test for a bulk delete that never ran in a browser.
	 * WP_List_Table::display_tablenav() prints wp_nonce_field() for bulk-downloads
	 * itself, in a field named _wpnonce; the screen printed a second one beside it
	 * under the same name, and PHP keeps only the last. Every unit test passed
	 * throughout, because they built the nonce themselves instead of reading the
	 * one the form actually emits.
	 */
	public function test_the_bulk_form_emits_one_nonce_and_it_verifies() {
		$html = $this->render( array( 'WP_DownloadManager_Admin', 'render_downloads' ) );

		preg_match_all( '/name="_wpnonce" value="([^"]+)"/', $html, $matches );

		$this->assertCount( 1, $matches[1], 'The form carries more than one _wpnonce field, so only the last survives the submit.' );

		$table = new WP_DownloadManager_List_Table();

		$this->assertSame(
			1,
			wp_verify_nonce( $matches[1][0], $table->bulk_nonce_action() ),
			'The nonce the form emits does not verify against the action the bulk handler checks.'
		);
	}

	public function test_messages_go_through_the_settings_error_api() {
		$this->assertStringContainsString(
			'add_settings_error(',
			$this->code( 'includes/class-wp-downloadmanager-admin.php' ),
			'section 4.2: no hand-rolled <div class="updated">'
		);
		$this->assertStringNotContainsString(
			'class="updated fade"',
			$this->code( 'includes/class-wp-downloadmanager-admin.php' )
		);
	}
}
