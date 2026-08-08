<?php
/**
 * The downloads table, apart from anything that draws it.
 *
 * @package WP-DownloadManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reading, counting and removing rows of the downloads table.
 *
 * WP_DownloadManager_File is about a file: what it is called, how big it is,
 * who may have it and how it is served. This is about the row that records one.
 * The four things here were spread across three screens - the listing query sat
 * inside the list table's prepare_items(), beside the per-page preference and
 * the pagination it also has to work out; the library totals sat inside a method
 * that echoed a table around them; and the delete was a protected method on the
 * admin class. Any second caller could only have copied all three, so they live
 * here and the screens call them.
 */
class WP_DownloadManager_Download {

	/**
	 * Passed as the category to mean "every category".
	 *
	 * Zero is a real category id, so the "no filter" value has to be one the
	 * column cannot hold.
	 *
	 * @var int
	 */
	const ANY_CATEGORY = -1;

	/**
	 * The columns a listing may be ordered by, and the SQL behind each.
	 *
	 * An ORDER BY column cannot be bound, so this is an allow list rather than
	 * decoration: whatever a request or a command line asks for is looked up
	 * here and anything absent falls back to the default.
	 *
	 * @return array Column key => SQL expression.
	 */
	public static function sortable_columns() {
		return array(
			'file_id'         => 'file_id',
			'file_name'       => 'file_name',
			'file_size'       => '(file_size+0.00)',
			'file_hits'       => 'file_hits',
			'file_permission' => 'file_permission',
			'file_category'   => 'file_category',
			'file_date'       => 'FROM_UNIXTIME(file_date)',
		);
	}

	/**
	 * The arguments query() and count() understand, and what each means unset.
	 *
	 * @return array
	 */
	public static function query_defaults() {
		return array(
			'search'   => '',
			'category' => self::ANY_CATEGORY,
			'orderby'  => 'file_name',
			'order'    => 'asc',
			'number'   => 0,
			'offset'   => 0,
		);
	}

	/**
	 * One row of the downloads table.
	 *
	 * @param int $file_id File ID.
	 * @return object|null
	 */
	public static function get( $file_id ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->downloads} WHERE file_id = %d", (int) $file_id ) );
	}

	/**
	 * The rows matching a set of arguments.
	 *
	 * No permission clause: this is the library's own inventory, which is what
	 * the Downloads screen shows and therefore includes the files marked Hidden.
	 * The front-end queries are the ones that subtract permission -2.
	 *
	 * @param array $args See query_defaults().
	 * @return array Rows.
	 */
	public static function query( $args = array() ) {
		global $wpdb;

		$args    = wp_parse_args( $args, self::query_defaults() );
		$columns = self::sortable_columns();
		$orderby = isset( $columns[ $args['orderby'] ] ) ? $columns[ $args['orderby'] ] : $columns['file_name'];
		$order   = 'desc' === strtolower( (string) $args['order'] ) ? 'DESC' : 'ASC';

		// MySQL has no way to spell "no limit", so an unbounded query asks for
		// more rows than the table can hold. The alternative is a second
		// statement with the clause left off, which is the same SQL written
		// twice for the sake of one keyword.
		$number = (int) $args['number'] > 0 ? (int) $args['number'] : PHP_INT_MAX;

		list( $any_category, $category, $any_search, $like ) = self::filter_values( $args );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $orderby is a value from self::sortable_columns() and $order is one of the two literals chosen above; an ORDER BY column and direction cannot be bound.
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->downloads} WHERE ( %d = 1 OR file_category = %d ) AND ( %d = 1 OR file LIKE %s OR file_name LIKE %s OR file_des LIKE %s ) ORDER BY {$orderby} {$order} LIMIT %d, %d",
				$any_category,
				$category,
				$any_search,
				$like,
				$like,
				$like,
				max( 0, (int) $args['offset'] ),
				$number
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * How many rows match, ignoring number and offset.
	 *
	 * @param array $args See query_defaults().
	 * @return int
	 */
	public static function count( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args( $args, self::query_defaults() );

		list( $any_category, $category, $any_search, $like ) = self::filter_values( $args );

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(file_id) FROM {$wpdb->downloads} WHERE ( %d = 1 OR file_category = %d ) AND ( %d = 1 OR file LIKE %s OR file_name LIKE %s OR file_des LIKE %s )",
				$any_category,
				$category,
				$any_search,
				$like,
				$like,
				$like
			)
		);
	}

	/**
	 * The four values the category and search clauses are built from.
	 *
	 * Both filters bind rather than being concatenated in: with nothing asked
	 * for, the first test of each group reads 1 = 1, which makes the whole OR
	 * group true and the clause a no-op - exactly what leaving it out would
	 * mean. esc_like() so a literal % or _ in a search term is not read as a
	 * wildcard. The search term is bound three times by both callers, once per
	 * column it is matched against.
	 *
	 * @param array $args Arguments, already merged over the defaults.
	 * @return array Whether any category matches, the category, whether any
	 *               search term matches, and the LIKE pattern.
	 */
	protected static function filter_values( $args ) {
		global $wpdb;

		$category = (int) $args['category'];
		$search   = (string) $args['search'];

		return array(
			$category < 0 ? 1 : 0,
			$category,
			'' === $search ? 1 : 0,
			'%' . $wpdb->esc_like( $search ) . '%',
		);
	}

	/**
	 * Files, bytes, hits and bandwidth across the whole library.
	 *
	 * Every figure is cast here rather than wherever it is printed: SUM() is
	 * NULL on an empty table, and number_format_i18n( null ) is deprecated on
	 * PHP 8.1 and later.
	 *
	 * @return array Keys 'files', 'size', 'hits' and 'bandwidth'.
	 */
	public static function totals() {
		global $wpdb;

		// Rows whose size could not be detected hold the word rather than a
		// number - a remote file whose host sends no Content-Length - and adding
		// those up would report a library smaller than it is only if they were
		// zero. They are excluded from both size sums instead.
		$unknown = __( 'unknown', 'wp-downloadmanager' );

		return array(
			'files'     => (int) $wpdb->get_var( "SELECT COUNT(file_id) FROM {$wpdb->downloads}" ),
			'size'      => (int) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(file_size) FROM {$wpdb->downloads} WHERE file_size != %s", $unknown ) ),
			'hits'      => (int) $wpdb->get_var( "SELECT SUM(file_hits) FROM {$wpdb->downloads}" ),
			'bandwidth' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(file_hits*file_size) FROM {$wpdb->downloads} WHERE file_size != %s", $unknown ) ),
		);
	}

	/**
	 * Remove rows, and optionally the files behind them.
	 *
	 * @param array $file_ids  File IDs.
	 * @param bool  $from_disk Also delete the file inside the downloads directory.
	 * @return int Number of rows removed.
	 */
	public static function delete( $file_ids, $from_disk = false ) {
		global $wpdb;

		$path    = WP_DownloadManager_Options::get( 'path.dir' );
		$deleted = 0;

		foreach ( (array) $file_ids as $file_id ) {
			$file = self::get( $file_id );
			if ( ! $file ) {
				continue;
			}

			// A remote file has nothing on this server to unlink, which is why
			// the Delete File screen offers the checkbox only for a local one.
			if ( $from_disk && ! WP_DownloadManager_File::is_remote( stripslashes( $file->file ) ) ) {
				// wp_delete_file_from_directory() refuses anything that resolves
				// outside the downloads directory, so a crafted row cannot make
				// this unlink somewhere else.
				wp_delete_file_from_directory( $path . stripslashes( $file->file ), $path );
			}

			$deleted += (int) $wpdb->delete( $wpdb->downloads, array( 'file_id' => (int) $file_id ) );
		}

		return $deleted;
	}

	/**
	 * Put hit counters back to zero.
	 *
	 * The counter and nothing else. The Edit File screen resets it as one column
	 * of a whole-row save and stamps file_updated_date on the way through, but
	 * that is a fact about saving that form rather than about the counter:
	 * clearing a tally does not update the file, and a listing sorted by "last
	 * updated" should not reshuffle because somebody reset one.
	 *
	 * @param array $file_ids File IDs.
	 * @return int Number of files reset.
	 */
	public static function reset_hits( $file_ids ) {
		global $wpdb;

		$reset = 0;

		foreach ( (array) $file_ids as $file_id ) {
			if ( ! self::get( $file_id ) ) {
				continue;
			}

			// Not counting the return of update(): it is 0 for a file whose hits
			// were already zero, which is a reset that had nothing to do rather
			// than one that failed.
			$wpdb->update( $wpdb->downloads, array( 'file_hits' => 0 ), array( 'file_id' => (int) $file_id ) );

			++$reset;
		}

		return $reset;
	}
}
