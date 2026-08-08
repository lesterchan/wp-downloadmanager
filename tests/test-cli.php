<?php
/**
 * Tests for the `wp downloadmanager` WP-CLI command.
 *
 * @package WP-DownloadManager
 */

/**
 * The command deletes downloads, and can take a file off the server with one,
 * with no browser and no nonce in front of it, so every subcommand is pinned
 * here.
 *
 * The WP_CLI facade these tests read is the stand-in from helper-wp-cli.php: it
 * records what the command reported instead of printing it, and its error()
 * throws, because the real one exits and every line after a call to it is
 * unreachable.
 */
class WP_DownloadManager_CLI_Test extends WP_DownloadManager_TestCase {

	/**
	 * Clears everything the stand-in recorded for the previous test.
	 */
	public function set_up() {
		parent::set_up();

		WP_CLI::$successes     = array();
		WP_CLI::$warnings      = array();
		WP_CLI::$logs          = array();
		WP_CLI::$confirmations = array();
		WP_CLI::$commands      = array();
		WP_CLI::$items         = array();
	}

	/**
	 * Runs one subcommand the way WP-CLI would.
	 *
	 * @param string $subcommand Method to call.
	 * @param array  $args       Positional arguments.
	 * @param array  $assoc_args Associative arguments.
	 * @return void
	 */
	protected function run_command( $subcommand, $args = array(), $assoc_args = array() ) {
		$command = new WP_DownloadManager_Command();
		$command->$subcommand( $args, $assoc_args );
	}

	/**
	 * The rows the last format_items() call was given.
	 *
	 * @return array
	 */
	protected function listed_rows() {
		$this->assertNotEmpty( WP_CLI::$items, 'The command formatted a table.' );

		$last = end( WP_CLI::$items );

		return $last['items'];
	}

	/**
	 * A field/value table, back as a single associative array.
	 *
	 * @return array
	 */
	protected function reported_fields() {
		$fields = array();

		foreach ( $this->listed_rows() as $row ) {
			$fields[ $row['field'] ] = $row['value'];
		}

		return $fields;
	}

	// --- registration ----------------------------------------------------

	/**
	 * The command registers under the bare noun, not the plugin slug.
	 *
	 * @return void
	 */
	public function test_the_command_registers_as_downloadmanager() {
		if ( ! defined( 'WP_CLI' ) ) {
			define( 'WP_CLI', true );
		}

		WP_DownloadManager::register_command();

		$this->assertArrayHasKey( 'downloadmanager', WP_CLI::$commands, 'The command is registered as `wp downloadmanager`.' );
		$this->assertSame( 'WP_DownloadManager_Command', WP_CLI::$commands['downloadmanager'], 'WP_DownloadManager_Command is what handles it.' );
		$this->assertArrayNotHasKey( 'wp-downloadmanager', WP_CLI::$commands, 'The plugin slug is not also claimed as a command.' );
	}

	/**
	 * The command neither adds a download nor edits one.
	 *
	 * Both are things the Add File and Edit File screens do, and both are
	 * deliberately absent: the four-way source choice those screens offer is
	 * only half meaningful without a browser to upload through. This is here so
	 * that adding either is a decision somebody makes rather than a gap somebody
	 * fills.
	 *
	 * @return void
	 */
	public function test_the_command_neither_adds_a_download_nor_edits_one() {
		$this->assertFalse( method_exists( 'WP_DownloadManager_Command', 'create' ), 'There is no create subcommand.' );
		$this->assertFalse( method_exists( 'WP_DownloadManager_Command', 'add' ), 'There is no add subcommand.' );
		$this->assertFalse( method_exists( 'WP_DownloadManager_Command', 'update' ), 'There is no update subcommand.' );
		$this->assertFalse( method_exists( 'WP_DownloadManager_Command', 'edit' ), 'There is no edit subcommand.' );
	}

	/**
	 * There is one delete, and the screens and the command share it.
	 *
	 * @return void
	 */
	public function test_deleting_is_implemented_once() {
		$this->assertFalse(
			method_exists( 'WP_DownloadManager_Admin', 'delete_files' ),
			'The admin class keeps no delete of its own beside the one on the downloads class.'
		);
		$this->assertTrue(
			method_exists( 'WP_DownloadManager_Download', 'delete' ),
			'Deleting a row lives on the downloads class, where both callers reach it.'
		);
	}

	// --- list ------------------------------------------------------------

	/**
	 * Listing returns the whole library.
	 *
	 * @return void
	 */
	public function test_list_returns_every_file() {
		$this->run_command( 'list_' );

		$this->assertCount( 5, $this->listed_rows(), 'Every seeded file is listed.' );
	}

	/**
	 * A file marked Hidden is inventory, and the screen lists it too.
	 *
	 * @return void
	 */
	public function test_list_includes_the_files_marked_hidden() {
		$this->run_command( 'list_' );

		$ids = wp_list_pluck( $this->listed_rows(), 'id' );

		$this->assertContains( $this->ids['hidden'], $ids, 'A hidden file is still part of the library the owner manages.' );
	}

	/**
	 * Each row carries the figures the Downloads screen shows.
	 *
	 * @return void
	 */
	public function test_list_reports_what_each_row_holds() {
		$this->run_command( 'list_', array(), array( 'search' => 'Manual' ) );

		$rows = $this->listed_rows();

		$this->assertCount( 1, $rows, 'The search matched one file.' );
		$this->assertSame( $this->ids['public'], $rows[0]['id'], 'And it is the one seeded with that name.' );
		$this->assertSame( 'The Manual', $rows[0]['name'], 'The display name comes back as stored.' );
		$this->assertSame( '/manual.pdf', $rows[0]['file'], 'Beside the path it is stored at.' );
		$this->assertSame( 2048, $rows[0]['size'], 'The size is reported in bytes.' );
		$this->assertSame( 12, $rows[0]['hits'], 'And the hit count as a number.' );
		$this->assertSame( 'Everyone', $rows[0]['permission'], 'The permission is the label the screen shows.' );
		$this->assertSame( 'General', $rows[0]['category'], 'And the category is named rather than numbered.' );
	}

	/**
	 * The default order is the one the Downloads screen opens on.
	 *
	 * @return void
	 */
	public function test_list_sorts_by_name_by_default() {
		$this->run_command( 'list_' );

		$names = wp_list_pluck( $this->listed_rows(), 'name' );

		$this->assertSame( 'Editor Notes', $names[0], 'The list opens sorted by name, ascending.' );
		$this->assertSame( 'The Manual', end( $names ), 'And ends at the other end of the alphabet.' );
	}

	/**
	 * --orderby and --order sort by any column the screen sorts by.
	 *
	 * @return void
	 */
	public function test_list_can_sort_by_hits() {
		$this->run_command(
			'list_',
			array(),
			array(
				'orderby' => 'file_hits',
				'order'   => 'desc',
			)
		);

		$rows = $this->listed_rows();

		$this->assertSame( $this->ids['hidden'], $rows[0]['id'], 'The most downloaded file is first.' );
		$this->assertSame( 99, $rows[0]['hits'], 'With the hit count that put it there.' );
	}

	/**
	 * A column nothing sorts by falls back rather than reaching the SQL.
	 *
	 * @return void
	 */
	public function test_list_ignores_a_sort_column_that_is_not_on_the_allow_list() {
		$this->run_command( 'list_', array(), array( 'orderby' => 'file_hits; DROP TABLE wp_downloads' ) );

		$names = wp_list_pluck( $this->listed_rows(), 'name' );

		$this->assertSame( 'Editor Notes', $names[0], 'An unrecognised column sorts by name, as the default does.' );
		$this->assertSame( 5, $this->count_files(), 'And the table is still there.' );
	}

	/**
	 * --limit caps how many rows come back.
	 *
	 * @return void
	 */
	public function test_list_limit_caps_the_number_of_rows() {
		$this->run_command( 'list_', array(), array( 'limit' => 2 ) );

		$this->assertCount( 2, $this->listed_rows(), 'Only as many rows as were asked for.' );
	}

	/**
	 * --category filters to one category.
	 *
	 * @return void
	 */
	public function test_list_can_be_filtered_to_one_category() {
		$this->run_command( 'list_', array(), array( 'category' => 2 ) );

		$ids = wp_list_pluck( $this->listed_rows(), 'id' );

		$this->assertContains( $this->ids['editors'], $ids, 'A file in the category is listed.' );
		$this->assertNotContains( $this->ids['public'], $ids, 'One in another category is not.' );
	}

	/**
	 * A search term is matched literally, wildcards and all.
	 *
	 * @return void
	 */
	public function test_a_search_term_containing_a_wildcard_is_taken_literally() {
		$this->run_command( 'list_', array(), array( 'search' => '%' ) );

		$this->assertNotEmpty( WP_CLI::$successes, 'A per cent sign matches nothing rather than everything.' );
		$this->assertEmpty( WP_CLI::$items, 'So no table is printed.' );
	}

	/**
	 * An empty library is reported as a success, not an error.
	 *
	 * @return void
	 */
	public function test_list_with_no_files_is_not_an_error() {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', $this->table() ) );

		$this->run_command( 'list_' );

		$this->assertNotEmpty( WP_CLI::$successes, 'Finding nothing is reported on the success channel.' );
		$this->assertEmpty( WP_CLI::$items, 'No table is printed when there is nothing to put in it.' );
	}

	// --- get -------------------------------------------------------------

	/**
	 * Getting a file reports the fields the Edit and Delete screens show.
	 *
	 * @return void
	 */
	public function test_get_reports_the_stored_fields() {
		$this->run_command( 'get', array( $this->ids['members'] ) );

		$fields = $this->reported_fields();

		$this->assertSame( $this->ids['members'], $fields['id'], 'The file it was asked for.' );
		$this->assertSame( 'Members Bundle', $fields['name'], 'With its display name.' );
		$this->assertSame( '/members.zip', $fields['file'], 'The path it is stored at.' );
		$this->assertSame( 'Registered users only.', $fields['description'], 'Its description.' );
		$this->assertSame( 1048576, $fields['size'], 'Its size in bytes.' );
		$this->assertSame( 5, $fields['hits'], 'Its hit count.' );
		$this->assertSame( 'Registered Users Only', $fields['permission'], 'And the permission label the screen shows.' );
	}

	/**
	 * The download URL is reported, because that is what a script wants.
	 *
	 * @return void
	 */
	public function test_get_reports_the_download_url() {
		$this->run_command( 'get', array( $this->ids['public'] ) );

		$fields = $this->reported_fields();

		$this->assertSame(
			WP_DownloadManager_File::download_url( $this->ids['public'], '/manual.pdf' ),
			$fields['url'],
			'The URL is the one the plugin builds for the file, so the two cannot disagree.'
		);
	}

	/**
	 * A remote file reports the URL it is stored at.
	 *
	 * @return void
	 */
	public function test_get_reports_a_remote_file_as_it_was_stored() {
		$this->run_command( 'get', array( $this->ids['remote'] ) );

		$fields = $this->reported_fields();

		$this->assertSame( 'https://example.com/remote.zip', $fields['file'], 'A remote file is stored as its URL and reported as one.' );
	}

	/**
	 * An id that matches nothing stops the command.
	 *
	 * @return void
	 */
	public function test_get_errors_on_an_unknown_file() {
		$this->expectException( RuntimeException::class );

		$this->run_command( 'get', array( 123456 ) );
	}

	// --- stats -----------------------------------------------------------

	/**
	 * The totals are the ones printed under the Downloads list.
	 *
	 * @return void
	 */
	public function test_stats_reports_the_library_totals() {
		$this->run_command( 'stats' );

		$fields = $this->reported_fields();

		$this->assertSame( 5, $fields['files'], 'Every row is counted.' );
		$this->assertSame( 1063424, $fields['size'], 'The sizes add up, in bytes.' );
		$this->assertSame( 126, $fields['hits'], 'So do the hit counts.' );
		$this->assertSame( 5387776, $fields['bandwidth'], 'And bandwidth is size times hits, summed.' );
	}

	/**
	 * An empty library reports zeroes rather than raising a deprecation.
	 *
	 * SUM() is NULL on an empty table, and number_format_i18n( null ) is
	 * deprecated on PHP 8.1 and later.
	 *
	 * @return void
	 */
	public function test_stats_survives_an_empty_library() {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', $this->table() ) );

		$this->run_command( 'stats' );

		$fields = $this->reported_fields();

		$this->assertSame( 0, $fields['files'], 'No files.' );
		$this->assertSame( 0, $fields['size'], 'No bytes.' );
		$this->assertSame( 0, $fields['hits'], 'No hits.' );
		$this->assertSame( 0, $fields['bandwidth'], 'And no bandwidth, rather than a null.' );
	}

	// --- reset-hits ------------------------------------------------------

	/**
	 * Resetting puts the counter back to zero.
	 *
	 * @return void
	 */
	public function test_reset_hits_zeroes_the_counter() {
		$this->run_command( 'reset_hits', array( $this->ids['public'] ), array( 'yes' => true ) );

		$this->assertSame( 0, (int) $this->fetch_file( $this->ids['public'] )->file_hits, 'The hit count is zero afterwards.' );
		$this->assertNotEmpty( WP_CLI::$successes, 'And the command says so.' );
	}

	/**
	 * Resetting touches the counter and nothing else.
	 *
	 * @return void
	 */
	public function test_reset_hits_leaves_the_updated_date_alone() {
		$before = $this->fetch_file( $this->ids['public'] );

		$this->run_command( 'reset_hits', array( $this->ids['public'] ), array( 'yes' => true ) );

		$after = $this->fetch_file( $this->ids['public'] );

		$this->assertSame( (int) $before->file_updated_date, (int) $after->file_updated_date, 'Clearing a tally is not an edit to the file.' );
		$this->assertSame( (int) $before->file_size, (int) $after->file_size, 'And nothing else about the row moves either.' );
	}

	/**
	 * Resetting one counter leaves the others where they were.
	 *
	 * @return void
	 */
	public function test_reset_hits_touches_only_the_files_it_was_given() {
		$this->run_command( 'reset_hits', array( $this->ids['public'] ), array( 'yes' => true ) );

		$this->assertSame( 5, (int) $this->fetch_file( $this->ids['members'] )->file_hits, 'The other files keep their hit counts.' );
	}

	/**
	 * Several ids at once is several resets.
	 *
	 * @return void
	 */
	public function test_reset_hits_accepts_more_than_one_file() {
		$this->run_command( 'reset_hits', array( $this->ids['public'], $this->ids['members'] ), array( 'yes' => true ) );

		$this->assertSame( 0, (int) $this->fetch_file( $this->ids['public'] )->file_hits, 'The first named file is reset.' );
		$this->assertSame( 0, (int) $this->fetch_file( $this->ids['members'] )->file_hits, 'And so is the second.' );
	}

	/**
	 * Without --yes the command asks, and a script that cannot answer resets
	 * nothing.
	 *
	 * @return void
	 */
	public function test_reset_hits_without_yes_asks_first_and_changes_nothing() {
		try {
			$this->run_command( 'reset_hits', array( $this->ids['public'] ) );
			$this->fail( 'The command stops at the confirmation instead of resetting.' );
		} catch ( RuntimeException $e ) {
			unset( $e );
		}

		$this->assertNotEmpty( WP_CLI::$confirmations, 'It asked before doing anything.' );
		$this->assertSame( 12, (int) $this->fetch_file( $this->ids['public'] )->file_hits, 'And the hit count is untouched.' );
	}

	/**
	 * An unknown id stops the command before it asks anything.
	 *
	 * @return void
	 */
	public function test_reset_hits_on_an_unknown_file_stops_before_the_prompt() {
		try {
			$this->run_command( 'reset_hits', array( 123456 ), array( 'yes' => true ) );
			$this->fail( 'An id matching no row stops the command.' );
		} catch ( RuntimeException $e ) {
			unset( $e );
		}

		$this->assertEmpty( WP_CLI::$confirmations, 'The ids are resolved before anything is confirmed.' );
	}

	/**
	 * Naming no file at all is an error rather than a no-op success.
	 *
	 * @return void
	 */
	public function test_reset_hits_with_no_ids_is_an_error() {
		$this->expectException( RuntimeException::class );

		$this->run_command( 'reset_hits', array(), array( 'yes' => true ) );
	}

	// --- delete ----------------------------------------------------------

	/**
	 * Deleting removes the row.
	 *
	 * @return void
	 */
	public function test_delete_removes_the_row() {
		$this->run_command( 'delete', array( $this->ids['public'] ), array( 'yes' => true ) );

		$this->assertNull( $this->fetch_file( $this->ids['public'] ), 'The row is gone.' );
		$this->assertSame( 4, $this->count_files(), 'And only that one went.' );
	}

	/**
	 * Deleting one file leaves the others where they were.
	 *
	 * @return void
	 */
	public function test_delete_touches_only_the_files_it_was_given() {
		$this->run_command( 'delete', array( $this->ids['public'] ), array( 'yes' => true ) );

		$this->assertNotNull( $this->fetch_file( $this->ids['members'] ), 'The file that was not named is still there.' );
	}

	/**
	 * Several ids at once is the bulk delete the list screen offers.
	 *
	 * @return void
	 */
	public function test_delete_accepts_more_than_one_file() {
		$this->run_command( 'delete', array( $this->ids['public'], $this->ids['members'] ), array( 'yes' => true ) );

		$this->assertNull( $this->fetch_file( $this->ids['public'] ), 'The first named file is gone.' );
		$this->assertNull( $this->fetch_file( $this->ids['members'] ), 'And so is the second.' );
		$this->assertSame( 3, $this->count_files(), 'Two rows went, and no more.' );
	}

	/**
	 * Without --yes the command asks, and a script that cannot answer deletes
	 * nothing.
	 *
	 * @return void
	 */
	public function test_delete_without_yes_asks_first_and_deletes_nothing() {
		try {
			$this->run_command( 'delete', array( $this->ids['public'] ) );
			$this->fail( 'The command stops at the confirmation instead of deleting.' );
		} catch ( RuntimeException $e ) {
			unset( $e );
		}

		$this->assertNotEmpty( WP_CLI::$confirmations, 'It asked before doing anything.' );
		$this->assertNotNull( $this->fetch_file( $this->ids['public'] ), 'And the file is still there.' );
	}

	/**
	 * The prompt says when a file is coming off the server as well.
	 *
	 * @return void
	 */
	public function test_delete_says_when_the_file_itself_is_going_too() {
		try {
			$this->run_command( 'delete', array( $this->ids['public'] ), array( 'delete-file' => true ) );
			$this->fail( 'The command stops at the confirmation instead of deleting.' );
		} catch ( RuntimeException $e ) {
			unset( $e );
		}

		$this->assertStringContainsString(
			'server',
			implode( ' ', WP_CLI::$confirmations ),
			'The question asked names the file on the server, because that is what cannot be undone.'
		);
	}

	/**
	 * Naming no file at all is an error rather than a no-op success.
	 *
	 * @return void
	 */
	public function test_delete_with_no_ids_is_an_error() {
		$this->expectException( RuntimeException::class );

		$this->run_command( 'delete', array(), array( 'yes' => true ) );
	}

	/**
	 * An unknown id stops the command before it asks anything.
	 *
	 * @return void
	 */
	public function test_delete_on_an_unknown_file_stops_before_the_prompt() {
		try {
			$this->run_command( 'delete', array( 123456 ), array( 'yes' => true ) );
			$this->fail( 'An id matching no row stops the command.' );
		} catch ( RuntimeException $e ) {
			unset( $e );
		}

		$this->assertEmpty( WP_CLI::$confirmations, 'The ids are resolved before anything is confirmed.' );
		$this->assertSame( 5, $this->count_files(), 'And nothing was deleted.' );
	}

	// --- delete, against both kinds of source -----------------------------

	/**
	 * A local file can be taken off the server with its row.
	 *
	 * @return void
	 */
	public function test_delete_can_remove_a_local_file_from_disk_too() {
		$path = $this->make_download_file( 'cli-doomed.txt' );
		$id   = $this->insert_file( array( 'file' => '/cli-doomed.txt' ) );

		$this->run_command(
			'delete',
			array( $id ),
			array(
				'delete-file' => true,
				'yes'         => true,
			)
		);

		$this->assertFileDoesNotExist( $path, 'With --delete-file the file itself is removed.' );
		$this->assertNull( $this->fetch_file( $id ), 'And the row goes with it.' );
	}

	/**
	 * Removing the row is not the same as removing the file.
	 *
	 * @return void
	 */
	public function test_delete_leaves_a_local_file_on_disk_unless_asked() {
		$path = $this->make_download_file( 'cli-spared.txt' );
		$id   = $this->insert_file( array( 'file' => '/cli-spared.txt' ) );

		$this->run_command( 'delete', array( $id ), array( 'yes' => true ) );

		$this->assertFileExists( $path, 'The file stays where it is.' );
		$this->assertNull( $this->fetch_file( $id ), 'Even though the row is gone.' );
	}

	/**
	 * A remote file has nothing on this server to unlink.
	 *
	 * The two sources a download can have behave differently here and the
	 * difference is easy to lose: the path a remote row would produce is the
	 * downloads directory with a URL glued to the end of it, which is not a file
	 * anybody meant to delete. --delete-file has to be a no-op for one.
	 *
	 * @return void
	 */
	public function test_delete_of_a_remote_file_removes_only_the_row() {
		$path = $this->make_download_file( 'cli-bystander.txt' );

		$this->run_command(
			'delete',
			array( $this->ids['remote'] ),
			array(
				'delete-file' => true,
				'yes'         => true,
			)
		);

		$this->assertNull( $this->fetch_file( $this->ids['remote'] ), 'The remote file\'s row is deleted.' );
		$this->assertFileExists( $path, 'And nothing on this server was unlinked for it.' );
		$this->assertNotEmpty( WP_CLI::$successes, 'The command reports the delete rather than failing on the missing path.' );
	}
}
