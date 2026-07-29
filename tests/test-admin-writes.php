<?php
/**
 * The Add / Edit / Delete write paths.
 *
 * These are the only code in the plugin that writes to the downloads table
 * outside a migration, and the only code that touches the filesystem. They had
 * no coverage at all before, which is why the 2.0.0 rewrite left them alone.
 *
 * @package WP-WP_DownloadManager
 */

/**
 * Form processing on the two legacy admin screens.
 */
class Test_Admin_Writes extends WP_DownloadManager_TestCase {

	/**
	 * Become an administrator and give the write paths a real directory.
	 */
	public function set_up() {
		parent::set_up();
		$this->become_download_admin();

		WP_DownloadManager_Options::set( 'path.dir', WP_CONTENT_DIR . '/dm-test-files' );
	}

	/**
	 * Clean up anything written to disk.
	 */
	public function tear_down() {
		$this->remove_download_files();
		parent::tear_down();
	}

	/**
	 * The row for a file name, or null.
	 *
	 * @param string $file_name File name.
	 * @return object|null
	 */
	private function row_named( $file_name ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->downloads} WHERE file_name = %s", $file_name )
		);
	}

	/**
	 * Add a file by browsing to one already on disk.
	 */
	public function test_add_file_from_disk() {
		$this->make_download_file( 'brochure.txt', 'twelve bytes' );

		$html = $this->render_admin_page(
			'includes/screen-add.php',
			array(),
			array(
				'do'                    => 'Add File',
				'_wpnonce'              => $this->nonce( 'wp-downloadmanager_add-file' ),
				'file_type'             => '0',
				'file'                  => '/brochure.txt',
				'file_name'             => 'The Brochure',
				'file_des'              => 'A brochure.',
				'file_cat'              => '1',
				'file_permission'       => '-1',
				'file_hits'             => '3',
				'file_timestamp_day'    => '15',
				'file_timestamp_month'  => '6',
				'file_timestamp_year'   => '2020',
				'file_timestamp_hour'   => '8',
				'file_timestamp_minute' => '30',
				'file_timestamp_second' => '0',
			)
		);

		$this->assertStringContainsString( 'Added Successfully', $html );

		$row = $this->row_named( 'The Brochure' );
		$this->assertNotNull( $row );
		$this->assertSame( '/brochure.txt', $row->file );
		$this->assertSame( 'A brochure.', $row->file_des );
		$this->assertSame( 1, (int) $row->file_category );
		$this->assertSame( -1, (int) $row->file_permission );
		$this->assertSame( 3, (int) $row->file_hits );
		// Size was detected from the file rather than typed in.
		$this->assertSame( 12, (int) $row->file_size );
		$this->assertSame( gmmktime( 8, 30, 0, 6, 15, 2020 ), (int) $row->file_date );
	}

	/**
	 * An explicit file size wins over auto detection.
	 */
	public function test_add_file_with_an_explicit_size() {
		$this->make_download_file( 'brochure.txt', 'twelve bytes' );

		$this->render_admin_page(
			'includes/screen-add.php',
			array(),
			array(
				'do'        => 'Add File',
				'_wpnonce'  => $this->nonce( 'wp-downloadmanager_add-file' ),
				'file_type' => '0',
				'file'      => '/brochure.txt',
				'file_name' => 'Sized',
				'file_size' => '9999',
			)
		);

		$this->assertSame( 9999, (int) $this->row_named( 'Sized' )->file_size );
	}

	/**
	 * With no name given, the file's own basename is used.
	 */
	public function test_add_file_falls_back_to_the_basename() {
		$this->make_download_file( 'unnamed.txt' );

		$this->render_admin_page(
			'includes/screen-add.php',
			array(),
			array(
				'do'        => 'Add File',
				'_wpnonce'  => $this->nonce( 'wp-downloadmanager_add-file' ),
				'file_type' => '0',
				'file'      => '/unnamed.txt',
				'file_name' => '',
			)
		);

		$this->assertNotNull( $this->row_named( 'unnamed.txt' ) );
	}

	/**
	 * A remote file is accepted when its URL is one the plugin will fetch.
	 */
	public function test_add_remote_file() {
		$this->render_admin_page(
			'includes/screen-add.php',
			array(),
			array(
				'do'          => 'Add File',
				'_wpnonce'    => $this->nonce( 'wp-downloadmanager_add-file' ),
				'file_type'   => '2',
				'file_remote' => 'https://example.com/bundle.zip',
				'file_name'   => 'Remote Zip',
				'file_size'   => '4096',
			)
		);

		$row = $this->row_named( 'Remote Zip' );
		$this->assertNotNull( $row );
		$this->assertSame( 'https://example.com/bundle.zip', $row->file );
	}

	/**
	 * A remote URL with a scheme the plugin refuses is rejected.
	 */
	public function test_add_remote_file_rejects_a_bad_scheme() {
		$html = $this->render_admin_page(
			'includes/screen-add.php',
			array(),
			array(
				'do'          => 'Add File',
				'_wpnonce'    => $this->nonce( 'wp-downloadmanager_add-file' ),
				'file_type'   => '2',
				'file_remote' => 'javascript:alert(1)',
				'file_name'   => 'Nasty',
			)
		);

		$this->assertStringContainsString( 'Error Parsing Remote File URL', $html );
		$this->assertNull( $this->row_named( 'Nasty' ) );
	}

	/**
	 * Values are stored unslashed rather than doubly escaped.
	 *
	 * $_POST arrives slashed from WordPress and the old code ran addslashes()
	 * on top, so a name with an apostrophe was stored as O\'Brien and rendered
	 * back with a stray backslash.
	 */
	public function test_add_file_stores_values_unslashed() {
		global $wpdb;

		$this->make_download_file( 'quoted.txt' );

		$this->render_admin_page(
			'includes/screen-add.php',
			array(),
			array(
				'do'        => 'Add File',
				'_wpnonce'  => $this->nonce( 'wp-downloadmanager_add-file' ),
				'file_type' => '0',
				'file'      => '/quoted.txt',
				// As WordPress delivers it: slashed.
				'file_name' => "O\\'Brien and Co",
				'file_des'  => "It\\'s fine",
			)
		);

		// phpcs:ignore WordPress.DB
		$row = $wpdb->get_row( "SELECT * FROM {$wpdb->downloads} WHERE file = '/quoted.txt'" );

		$this->assertNotNull( $row );
		// The point of the test: one level of slashes, not two.
		$this->assertSame( "O'Brien and Co", $row->file_name );
		$this->assertSame( "It's fine", $row->file_des );
		$this->assertStringNotContainsString( '\\', $row->file_name );
		$this->assertStringNotContainsString( '\\', $row->file_des );
	}

	/**
	 * A bare ampersand is normalised by kses, and not then double encoded.
	 *
	 * Encoding on the way in is correct - wp_kses_post() does it.
	 * What must not happen is the stored &amp; being encoded again on the way
	 * out, which renders &amp;amp; on screen.
	 */
	public function test_add_file_does_not_double_encode_an_ampersand() {
		global $wpdb;

		$this->make_download_file( 'amp.txt' );

		$this->render_admin_page(
			'includes/screen-add.php',
			array(),
			array(
				'do'        => 'Add File',
				'_wpnonce'  => $this->nonce( 'wp-downloadmanager_add-file' ),
				'file_type' => '0',
				'file'      => '/amp.txt',
				'file_name' => 'Tea & Coffee',
			)
		);

		// phpcs:ignore WordPress.DB
		$stored = $wpdb->get_var( "SELECT file_name FROM {$wpdb->downloads} WHERE file = '/amp.txt'" );
		$this->assertSame( 'Tea &amp; Coffee', $stored );

		$html = $this->render_admin_page( 'includes/screen-manage.php' );
		$this->assertStringContainsString( 'Tea &amp; Coffee', $html );
		$this->assertStringNotContainsString( '&amp;amp;', $html );
	}

	/**
	 * Adding without a valid nonce is refused.
	 */
	public function test_add_file_requires_a_nonce() {
		$this->expectException( 'WPDieException' );

		$this->render_admin_page(
			'includes/screen-add.php',
			array(),
			array(
				'do'        => 'Add File',
				'_wpnonce'  => 'not-a-nonce',
				'file_type' => '2',
				'file_name' => 'Should Not Exist',
			)
		);
	}

	/**
	 * Editing updates the row and stamps the updated date.
	 */
	public function test_edit_file() {
		$file_id = $this->insert_file(
			array(
				'file'      => '/old.zip',
				'file_name' => 'Old Name',
				'file_hits' => 5,
			)
		);

		$html = $this->render_admin_page(
			'includes/screen-manage.php',
			array(),
			array(
				'do'              => 'Edit File',
				'_wpnonce'        => $this->nonce( 'wp-downloadmanager_edit-file' ),
				'file_id'         => (string) $file_id,
				'file_type'       => '-1',
				'old_file'        => '/old.zip',
				'file_name'       => 'New Name',
				'file_des'        => 'Updated description.',
				'file_cat'        => '2',
				'file_permission' => '7',
				'file_hits'       => '11',
				'auto_filesize'   => '0',
				'file_size'       => '2048',
			)
		);

		$this->assertStringContainsString( 'Edited Successfully', $html );

		$row = $this->row_named( 'New Name' );
		$this->assertNotNull( $row );
		$this->assertSame( 2, (int) $row->file_category );
		$this->assertSame( 7, (int) $row->file_permission );
		$this->assertSame( 11, (int) $row->file_hits );
		$this->assertSame( 2048, (int) $row->file_size );
		$this->assertSame( 'Updated description.', $row->file_des );
		// The file itself was left alone under file_type -1.
		$this->assertSame( '/old.zip', $row->file );
	}

	/**
	 * Saving a row unchanged reports success rather than an error.
	 *
	 * $wpdb->update() returns 0 when the row already held these values, which
	 * the old "if ( ! $editfile )" check reported as a failure.
	 */
	public function test_edit_file_with_no_changes_reports_success() {
		$file_id = $this->insert_file(
			array(
				'file'      => '/same.zip',
				'file_name' => 'Same',
				'file_des'  => '',
				'file_size' => 1024,
				'file_hits' => 0,
			)
		);

		// Save once so file_updated_date settles, then save the identical values.
		$post = array(
			'do'              => 'Edit File',
			'_wpnonce'        => $this->nonce( 'wp-downloadmanager_edit-file' ),
			'file_id'         => (string) $file_id,
			'file_type'       => '-1',
			'old_file'        => '/same.zip',
			'file_name'       => 'Same',
			'file_cat'        => '1',
			'file_permission' => '-1',
			'auto_filesize'   => '0',
			'file_size'       => '1024',
		);

		$this->render_admin_page( 'includes/screen-manage.php', array(), $post );
		$html = $this->render_admin_page( 'includes/screen-manage.php', array(), $post );

		$this->assertStringContainsString( 'Edited Successfully', $html );
		$this->assertStringNotContainsString( 'Error In Editing', $html );
	}

	/**
	 * The reset checkbox zeroes the hit count.
	 */
	public function test_edit_file_resets_hits() {
		$file_id = $this->insert_file(
			array(
				'file_name' => 'Popular',
				'file_hits' => 500,
			)
		);

		$this->render_admin_page(
			'includes/screen-manage.php',
			array(),
			array(
				'do'              => 'Edit File',
				'_wpnonce'        => $this->nonce( 'wp-downloadmanager_edit-file' ),
				'file_id'         => (string) $file_id,
				'file_type'       => '-1',
				'old_file'        => '/file.zip',
				'file_name'       => 'Popular',
				'file_cat'        => '1',
				'file_permission' => '-1',
				'file_hits'       => '500',
				'reset_filehits'  => '1',
				'auto_filesize'   => '0',
				'file_size'       => '1024',
			)
		);

		$this->assertSame( 0, (int) $this->row_named( 'Popular' )->file_hits );
	}

	/**
	 * The timestamp is only rewritten when the box is ticked.
	 */
	public function test_edit_file_timestamp_is_opt_in() {
		$file_id = $this->insert_file( array( 'file_name' => 'Dated' ) );

		$base = array(
			'do'                    => 'Edit File',
			'_wpnonce'              => $this->nonce( 'wp-downloadmanager_edit-file' ),
			'file_id'               => (string) $file_id,
			'file_type'             => '-1',
			'old_file'              => '/file.zip',
			'file_name'             => 'Dated',
			'file_cat'              => '1',
			'file_permission'       => '-1',
			'auto_filesize'         => '0',
			'file_size'             => '1024',
			'file_timestamp_day'    => '1',
			'file_timestamp_month'  => '1',
			'file_timestamp_year'   => '2001',
			'file_timestamp_hour'   => '0',
			'file_timestamp_minute' => '0',
			'file_timestamp_second' => '0',
		);

		// Without the checkbox the original date stands.
		$this->render_admin_page( 'includes/screen-manage.php', array(), $base );
		$this->assertSame( self::T0, (int) $this->row_named( 'Dated' )->file_date );

		// With it, the posted parts are used.
		$this->render_admin_page(
			'includes/screen-manage.php',
			array(),
			array_merge( $base, array( 'edit_filetimestamp' => '1' ) )
		);
		$this->assertSame(
			gmmktime( 0, 0, 0, 1, 1, 2001 ),
			(int) $this->row_named( 'Dated' )->file_date
		);
	}

	/**
	 * Editing without a valid nonce is refused.
	 */
	public function test_edit_file_requires_a_nonce() {
		$file_id = $this->insert_file( array( 'file_name' => 'Protected' ) );

		$this->expectException( 'WPDieException' );

		$this->render_admin_page(
			'includes/screen-manage.php',
			array(),
			array(
				'do'        => 'Edit File',
				'_wpnonce'  => 'not-a-nonce',
				'file_id'   => (string) $file_id,
				'file_type' => '-1',
				'file_name' => 'Renamed',
			)
		);
	}

	/**
	 * Deleting removes the row and leaves the file on disk by default.
	 */
	public function test_delete_file_keeps_the_file_on_disk() {
		$path    = $this->make_download_file( 'keep-me.txt' );
		$file_id = $this->insert_file(
			array(
				'file'      => '/keep-me.txt',
				'file_name' => 'Keep Me',
			)
		);

		$html = $this->render_admin_page(
			'includes/screen-manage.php',
			array(),
			array(
				'do'       => 'Delete File',
				'_wpnonce' => $this->nonce( 'wp-downloadmanager_delete-file' ),
				'file_id'  => (string) $file_id,
			)
		);

		$this->assertStringContainsString( 'Deleted Successfully', $html );
		$this->assertNull( $this->row_named( 'Keep Me' ) );
		$this->assertFileExists( $path );
	}

	/**
	 * Ticking the box also removes the file from the server.
	 */
	public function test_delete_file_removes_it_from_disk() {
		$path    = $this->make_download_file( 'delete-me.txt' );
		$file_id = $this->insert_file(
			array(
				'file'      => '/delete-me.txt',
				'file_name' => 'Delete Me',
			)
		);

		$html = $this->render_admin_page(
			'includes/screen-manage.php',
			array(),
			array(
				'do'         => 'Delete File',
				'_wpnonce'   => $this->nonce( 'wp-downloadmanager_delete-file' ),
				'file_id'    => (string) $file_id,
				'unlinkfile' => '1',
			)
		);

		$this->assertStringContainsString( 'Deleted From Server Successfully', $html );
		$this->assertNull( $this->row_named( 'Delete Me' ) );
		$this->assertFileDoesNotExist( $path );
	}

	/**
	 * Deleting without a valid nonce is refused.
	 */
	public function test_delete_file_requires_a_nonce() {
		$file_id = $this->insert_file( array( 'file_name' => 'Safe' ) );

		$this->expectException( 'WPDieException' );

		$this->render_admin_page(
			'includes/screen-manage.php',
			array(),
			array(
				'do'       => 'Delete File',
				'_wpnonce' => 'not-a-nonce',
				'file_id'  => (string) $file_id,
			)
		);
	}

	/**
	 * The upload target cannot escape the downloads directory.
	 *
	 * The subfolder comes from a select the screen builds, but nothing stopped
	 * a hand-crafted POST from sending '../../..'.
	 *
	 * @dataProvider traversal_provider
	 *
	 * @param string $subfolder The posted subfolder.
	 */
	public function test_upload_subfolder_cannot_escape( $subfolder ) {
		$dir = WP_DownloadManager_Options::get( 'path.dir' );
		wp_mkdir_p( $dir . '/real' );

		$this->assertSame(
			'/',
			WP_DownloadManager_File::safe_subfolder( $dir, $subfolder ),
			$subfolder . ' should be refused'
		);
	}

	/**
	 * Subfolders that must be refused.
	 *
	 * @return array
	 */
	public function traversal_provider() {
		return array(
			'parent'          => array( '/..' ),
			'deep parent'     => array( '/../../..' ),
			'embedded parent' => array( '/real/../../etc' ),
			'backslash'       => array( '\\..\\..' ),
			'null byte'       => array( "/real\0/../.." ),
			'absolute'        => array( '/etc' ),
		);
	}

	/**
	 * A genuine subfolder is accepted.
	 */
	public function test_upload_subfolder_accepts_a_real_folder() {
		$dir = WP_DownloadManager_Options::get( 'path.dir' );
		wp_mkdir_p( $dir . '/real' );

		$this->assertSame( '/real', WP_DownloadManager_File::safe_subfolder( $dir, '/real' ) );
		$this->assertSame( '/', WP_DownloadManager_File::safe_subfolder( $dir, '/' ) );
	}

	/**
	 * A sibling directory sharing a prefix is not mistaken for a subfolder.
	 *
	 * Without the trailing separator in the comparison, /files-public would pass
	 * as being inside /files.
	 */
	public function test_upload_subfolder_rejects_a_prefix_sibling() {
		$dir = WP_DownloadManager_Options::get( 'path.dir' );
		wp_mkdir_p( $dir );
		wp_mkdir_p( $dir . '-public' );

		$this->assertSame( '/', WP_DownloadManager_File::safe_subfolder( $dir, '/../' . basename( $dir ) . '-public' ) );

		rmdir( $dir . '-public' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	/**
	 * Stored file names are stripped of characters that do not belong.
	 */
	public function test_rename_file_normalises_the_name() {
		$dir = WP_DownloadManager_Options::get( 'path.dir' );
		$this->make_download_file( 'my file (1).txt' );

		$renamed = WP_DownloadManager_File::rename_file( trailingslashit( $dir ), 'my file (1).txt' );

		$this->assertSame( 'my_file_1.txt', $renamed );
		$this->assertFileExists( trailingslashit( $dir ) . 'my_file_1.txt' );
	}

	/**
	 * A name that needs no change is returned untouched.
	 */
	public function test_rename_file_leaves_a_clean_name_alone() {
		$dir = WP_DownloadManager_Options::get( 'path.dir' );
		$this->make_download_file( 'clean-name.txt' );

		$this->assertSame(
			'clean-name.txt',
			WP_DownloadManager_File::rename_file( trailingslashit( $dir ), 'clean-name.txt' )
		);
	}
}
