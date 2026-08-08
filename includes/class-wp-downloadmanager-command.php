<?php
/**
 * The WP-CLI command.
 *
 * @package WP-DownloadManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lists, inspects and clears out the download library from the command line.
 *
 * Registered as `wp downloadmanager` rather than `wp wp-downloadmanager`. A
 * `wp-` prefix is a wordpress.org directory convention for keeping one plugin's
 * download page apart from another's, not a command-naming one, and after the
 * `wp` binary it only stutters; WP-CLI's own ecosystem names commands after the
 * brand -- `wp wc`, `wp yoast`, `wp jetpack`. WP-CLI gives a collision to
 * whichever plugin registered last and a bare noun is claimable by anyone; that
 * is the accepted trade for a word as distinctive as `downloadmanager`.
 *
 * Every subcommand goes through WP_DownloadManager_Download, which is the class
 * the Downloads screen lists, totals and deletes through, so a file removed here
 * loses exactly what one removed through the browser loses -- and, with
 * --delete-file, the file on the server as well.
 *
 * **Nothing here adds a download or edits one, and the omission is
 * deliberate.** Add File and Edit File offer four sources: keep the current
 * file, pick one already sitting in the downloads directory, upload one, or name
 * a remote URL. Two of those are a browser handing over a multipart body, and
 * the other two are a form whose fields only mean anything beside the radio that
 * selects between them. What a shell is good for here is reading the library and
 * taking things out of it, so that is what this does.
 */
class WP_DownloadManager_Command extends WP_CLI_Command {

	/**
	 * List the files in the download library.
	 *
	 * Lists what the Downloads screen lists, which includes the files whose
	 * permission is Hidden: this is the library's own inventory rather than what
	 * a visitor can see.
	 *
	 * ## OPTIONS
	 *
	 * [--search=<term>]
	 * : Only files whose path, name or description contains this term.
	 *
	 * [--category=<id>]
	 * : Only files in this category.
	 *
	 * [--orderby=<column>]
	 * : Column to sort by.
	 * ---
	 * default: file_name
	 * options:
	 *   - file_id
	 *   - file_name
	 *   - file_size
	 *   - file_hits
	 *   - file_permission
	 *   - file_category
	 *   - file_date
	 * ---
	 *
	 * [--order=<order>]
	 * : Sort direction.
	 * ---
	 * default: asc
	 * options:
	 *   - asc
	 *   - desc
	 * ---
	 *
	 * [--limit=<number>]
	 * : How many files to list. Zero lists every one of them.
	 * ---
	 * default: 0
	 * ---
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - count
	 *   - ids
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Every file in the library.
	 *     $ wp downloadmanager list
	 *
	 *     # The ten most downloaded.
	 *     $ wp downloadmanager list --orderby=file_hits --order=desc --limit=10
	 *
	 *     # What is in category 3.
	 *     $ wp downloadmanager list --category=3
	 *
	 * @subcommand list
	 *
	 * @param array $args       Positional arguments. Unused.
	 * @param array $assoc_args Flags passed to the command.
	 * @return void
	 */
	public function list_( $args, $assoc_args ) {
		unset( $args );

		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

		$files = WP_DownloadManager_Download::query(
			array(
				'search'   => isset( $assoc_args['search'] ) ? (string) $assoc_args['search'] : '',
				'category' => isset( $assoc_args['category'] ) ? (int) $assoc_args['category'] : WP_DownloadManager_Download::ANY_CATEGORY,
				'orderby'  => isset( $assoc_args['orderby'] ) ? $assoc_args['orderby'] : 'file_name',
				'order'    => isset( $assoc_args['order'] ) ? $assoc_args['order'] : 'asc',
				'number'   => isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 0,
			)
		);

		if ( empty( $files ) ) {
			// An empty result is not an error, so this stays on the success
			// channel: a library with nothing in it, or a search that matched
			// nothing, is the answer to a reasonable question.
			WP_CLI::success( __( 'No files found.', 'wp-downloadmanager' ) );

			return;
		}

		$categories = (array) WP_DownloadManager_Options::get( 'categories', array() );
		$rows       = array();

		foreach ( $files as $file ) {
			$rows[] = array(
				'id'         => (int) $file->file_id,
				'name'       => wp_strip_all_tags( stripslashes( $file->file_name ) ),
				'file'       => stripslashes( $file->file ),
				'size'       => (int) $file->file_size,
				'hits'       => (int) $file->file_hits,
				'permission' => WP_DownloadManager_File::permission_label( $file->file_permission ),
				'category'   => WP_DownloadManager_Display::category_name( $categories, $file->file_category ),
			);
		}

		$this->print_rows( $format, $rows, array( 'id', 'name', 'file', 'size', 'hits', 'permission', 'category' ) );
	}

	/**
	 * Show one file.
	 *
	 * The three dates are the site-local timestamps this plugin stores, which is
	 * what the Downloads screen renders them from.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The file to show.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp downloadmanager get 3
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Flags passed to the command.
	 * @return void
	 */
	public function get( $args, $assoc_args ) {
		$file = $this->file_or_die( $args[0] );

		$format     = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';
		$categories = (array) WP_DownloadManager_Options::get( 'categories', array() );

		$fields = array(
			'id'              => (int) $file->file_id,
			'name'            => wp_strip_all_tags( stripslashes( $file->file_name ) ),
			'file'            => stripslashes( $file->file ),
			'url'             => WP_DownloadManager_File::download_url( $file->file_id, $file->file ),
			'description'     => wp_strip_all_tags( stripslashes( $file->file_des ) ),
			'category'        => WP_DownloadManager_Display::category_name( $categories, $file->file_category ),
			'size'            => (int) $file->file_size,
			'hits'            => (int) $file->file_hits,
			'permission'      => WP_DownloadManager_File::permission_label( $file->file_permission ),
			'added'           => gmdate( 'Y-m-d H:i:s', (int) $file->file_date ),
			'updated'         => gmdate( 'Y-m-d H:i:s', (int) $file->file_updated_date ),
			'last_downloaded' => gmdate( 'Y-m-d H:i:s', (int) $file->file_last_downloaded_date ),
		);

		$rows = array();

		foreach ( $fields as $field => $value ) {
			$rows[] = array(
				'field' => $field,
				'value' => $value,
			);
		}

		$this->print_rows( $format, $rows, array( 'field', 'value' ) );
	}

	/**
	 * Show the library totals the Downloads screen prints under the list.
	 *
	 * The two sizes are in bytes rather than the units the screen rounds them
	 * to, because a figure a script is going to compare is worth having exact.
	 * Files whose size could never be detected are left out of both, exactly as
	 * they are on the screen.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp downloadmanager stats
	 *
	 * @param array $args       Positional arguments. Unused.
	 * @param array $assoc_args Flags passed to the command.
	 * @return void
	 */
	public function stats( $args, $assoc_args ) {
		unset( $args );

		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';
		$totals = WP_DownloadManager_Download::totals();

		$rows = array();

		foreach ( array( 'files', 'size', 'hits', 'bandwidth' ) as $field ) {
			$rows[] = array(
				'field' => $field,
				'value' => $totals[ $field ],
			);
		}

		$this->print_rows( $format, $rows, array( 'field', 'value' ) );
	}

	/**
	 * Put a file's hit count back to zero.
	 *
	 * This is the "Reset the hit count to zero" checkbox on the Edit File
	 * screen, and it touches the counter and nothing else: the date the file was
	 * last updated is left where it was, because clearing a tally is not an edit
	 * to the file.
	 *
	 * ## OPTIONS
	 *
	 * <id>...
	 * : The files to reset.
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp downloadmanager reset-hits 3
	 *
	 *     # In a script, where there is nobody to answer the prompt.
	 *     $ wp downloadmanager reset-hits 3 7 --yes
	 *
	 * @subcommand reset-hits
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Flags passed to the command.
	 * @return void
	 */
	public function reset_hits( $args, $assoc_args ) {
		$file_ids = $this->file_ids_or_die( $args );

		// A hit count is a tally nothing else in the plugin keeps a second copy
		// of, so zeroing one cannot be undone from here or from the screen.
		WP_CLI::confirm(
			/* translators: %s: list of file ids. */
			sprintf( __( 'Reset the hit count of file(s) %s to zero?', 'wp-downloadmanager' ), implode( ', ', $file_ids ) ),
			$assoc_args
		);

		$reset = WP_DownloadManager_Download::reset_hits( $file_ids );

		WP_CLI::success(
			sprintf(
				/* translators: %s: number of files. */
				_n( '%s hit count reset.', '%s hit counts reset.', $reset, 'wp-downloadmanager' ),
				number_format_i18n( $reset )
			)
		);
	}

	/**
	 * Delete a file from the library.
	 *
	 * **With --delete-file this deletes the file from the server as well**,
	 * which is the checkbox the Delete File screen offers and the reason this
	 * subcommand asks before it does anything. Without it only the row goes and
	 * whatever is in the downloads directory stays where it is. A file stored as
	 * a remote URL has nothing on this server to unlink, so --delete-file does
	 * nothing for one -- and the screen does not offer the checkbox for one
	 * either.
	 *
	 * ## OPTIONS
	 *
	 * <id>...
	 * : The files to delete.
	 *
	 * [--delete-file]
	 * : Delete the file inside the downloads directory too.
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp downloadmanager delete 3
	 *
	 *     # The row and the file behind it, in a script.
	 *     $ wp downloadmanager delete 3 --delete-file --yes
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Flags passed to the command.
	 * @return void
	 */
	public function delete( $args, $assoc_args ) {
		$file_ids  = $this->file_ids_or_die( $args );
		$from_disk = ! empty( $assoc_args['delete-file'] );

		WP_CLI::confirm(
			$from_disk
				/* translators: %s: list of file ids. */
				? sprintf( __( 'Delete file(s) %s, and the file behind each one from the server?', 'wp-downloadmanager' ), implode( ', ', $file_ids ) )
				/* translators: %s: list of file ids. */
				: sprintf( __( 'Delete file(s) %s from the library?', 'wp-downloadmanager' ), implode( ', ', $file_ids ) ),
			$assoc_args
		);

		$deleted = WP_DownloadManager_Download::delete( $file_ids, $from_disk );

		WP_CLI::success(
			sprintf(
				/* translators: %s: number of files. */
				_n( '%s file deleted.', '%s files deleted.', $deleted, 'wp-downloadmanager' ),
				number_format_i18n( $deleted )
			)
		);
	}

	/**
	 * Resolve an id to a file, or stop the command.
	 *
	 * @param string $file_id File id as it arrived on the command line.
	 * @return object The row from the downloads table.
	 */
	private function file_or_die( $file_id ) {
		$file = WP_DownloadManager_Download::get( $file_id );

		if ( null === $file ) {
			/* translators: %d: the file id that was asked for. */
			WP_CLI::error( sprintf( __( 'No file with id %d.', 'wp-downloadmanager' ), (int) $file_id ) );
		}

		return $file;
	}

	/**
	 * Resolve every id a destructive subcommand was given, or stop.
	 *
	 * Each id is checked before anything is confirmed, so a mistyped one is
	 * reported instead of being silently skipped by an operation that then
	 * reports success for the files it did find.
	 *
	 * @param array $args Positional arguments.
	 * @return array File ids.
	 */
	private function file_ids_or_die( $args ) {
		if ( empty( $args ) ) {
			// An empty id list would otherwise read as "do this to nothing" and
			// report success, which is the wrong answer to a mistyped command.
			WP_CLI::error( __( 'Name at least one file id.', 'wp-downloadmanager' ) );
		}

		$file_ids = array();

		foreach ( $args as $arg ) {
			$file_ids[] = (int) $this->file_or_die( $arg )->file_id;
		}

		return $file_ids;
	}

	/**
	 * Print rows, reducing to the first column when ids were asked for.
	 *
	 * WP_CLI\Utils\format_items() hands `ids` straight to implode(), so a row
	 * that is an associative array comes out as the word `Array` and a PHP
	 * warning -- which is what `--format=ids` did here until it was run against
	 * a real WP-CLI rather than reasoned about. Reducing to the first column is
	 * what makes the documented `| xargs` pipes work.
	 *
	 * @param string $format Requested output format.
	 * @param array  $rows   Rows to print.
	 * @param array  $fields Columns, in order. The first is the id.
	 * @return void
	 */
	private function print_rows( $format, array $rows, array $fields ) {
		if ( 'ids' === $format ) {
			WP_CLI\Utils\format_items( $format, wp_list_pluck( $rows, $fields[0] ), $fields );

			return;
		}

		WP_CLI\Utils\format_items( $format, $rows, $fields );
	}
}
