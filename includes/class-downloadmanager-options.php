<?php
/**
 * Consolidated option storage for WP-DownloadManager.
 *
 * Everything the plugin configures lives in one wp_options row holding a nested
 * array, rather than the nineteen separate rows used up to 2.0.0. It reuses the
 * existing download_options name, which before 2.0.0 held only use_filename,
 * rss_sortby and rss_limit - reusing it adds no new row name and means the
 * existing value merges over the defaults for free.
 *
 * The value is a plain PHP array: update_option() serialises it and
 * get_option() unserialises it, so there is no encode/decode layer at the call
 * sites and register_setting()'s sanitize_callback receives the structure
 * intact.
 *
 * One thing deliberately stays in its own row: download_db_version, because it
 * is read to decide whether this option needs migrating and so cannot live
 * inside the thing being migrated.
 *
 * @package WP-DownloadManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the single download_options row.
 */
class DownloadManager_Options {

	/**
	 * Name of the consolidated option row.
	 *
	 * @var string
	 */
	const OPTION = 'download_options';

	/**
	 * Runtime cache so a page render does not re-read the row per lookup.
	 *
	 * @var array|null
	 */
	protected static $cache = null;

	/**
	 * Legacy option name => dot path in the consolidated array.
	 *
	 * Drives both the migration and uninstall, so the two can never disagree
	 * about which rows belong to the plugin.
	 *
	 * @return array
	 */
	public static function legacy_map() {
		$map = array(
			'download_path'           => 'path.dir',
			'download_path_url'       => 'path.url',
			'download_page_url'       => 'page_url',
			'download_method'         => 'method',
			'download_nice_permalink' => 'nice_permalink',
			'download_categories'     => 'categories',
			'download_sort'           => 'sort',
		);

		foreach ( DownloadManager_Templates::keys() as $key ) {
			$map[ 'download_template_' . $key ] = 'templates.' . $key;
		}

		return $map;
	}

	/**
	 * Legacy rows that carry no value forward but must still be cleaned up.
	 *
	 * download_options is NOT listed: it is the row the settings now live in, so
	 * deleting it would throw away everything the migration just wrote.
	 *
	 * @return array
	 */
	public static function legacy_extra_rows() {
		return array(
			'widget_download_most_downloaded',
			'widget_download_recent_downloads',
		);
	}

	/**
	 * Default values for every key.
	 *
	 * These mirror the pre-2.0.0 add_option() calls exactly. Changing any of
	 * them silently changes what a fresh install looks like.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'path'           => array(
				'dir' => WP_CONTENT_DIR . '/files',
				'url' => content_url( 'files' ),
			),
			'page_url'       => site_url( 'downloads' ),
			'method'         => 1,
			'nice_permalink' => 1,
			'use_filename'   => 0,
			'categories'     => array( 'General' ),
			'sort'           => array(
				'by'      => 'file_name',
				'order'   => 'asc',
				'perpage' => 20,
				'group'   => 1,
			),
			'rss'            => array(
				'sortby' => 'file_date',
				'limit'  => 20,
			),
			'templates'      => DownloadManager_Templates::defaults(),
		);
	}

	/**
	 * The whole option, defaults merged in.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$stored = get_option( self::OPTION, array() );
			if ( ! is_array( $stored ) ) {
				$stored = array();
			}
			self::$cache = self::merge( self::defaults(), $stored );
		}

		return self::$cache;
	}

	/**
	 * Read one value by dot path, e.g. 'templates.footer'.
	 *
	 * @param string $path     Dot separated path.
	 * @param mixed  $fallback Returned when the path is absent.
	 * @return mixed
	 */
	public static function get( $path, $fallback = null ) {
		$value = self::all();

		foreach ( explode( '.', $path ) as $segment ) {
			if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
				return $fallback;
			}
			$value = $value[ $segment ];
		}

		return $value;
	}

	/**
	 * One template string, by key and permission index.
	 *
	 * @param string $key   Template key.
	 * @param int    $index 0 for permitted, 1 for denied. Ignored for singles.
	 * @return string
	 */
	public static function template( $key, $index = 0 ) {
		$value = self::get( 'templates.' . $key, '' );

		if ( is_array( $value ) ) {
			return isset( $value[ $index ] ) ? (string) $value[ $index ] : '';
		}

		return (string) $value;
	}

	/**
	 * Write one value by dot path and persist the row.
	 *
	 * @param string $path  Dot separated path.
	 * @param mixed  $value Value to store.
	 * @return bool
	 */
	public static function set( $path, $value ) {
		$all      = self::all();
		$cursor   = &$all;
		$segments = explode( '.', $path );
		$last     = array_pop( $segments );

		foreach ( $segments as $segment ) {
			if ( ! isset( $cursor[ $segment ] ) || ! is_array( $cursor[ $segment ] ) ) {
				$cursor[ $segment ] = array();
			}
			$cursor = &$cursor[ $segment ];
		}
		$cursor[ $last ] = $value;
		unset( $cursor );

		return self::save( $all );
	}

	/**
	 * Replace the whole option.
	 *
	 * @param array $values Full option array.
	 * @return bool
	 */
	public static function save( $values ) {
		self::$cache = self::merge( self::defaults(), (array) $values );

		return update_option( self::OPTION, self::$cache );
	}

	/**
	 * Drop the runtime cache. Needed after a migration writes the row.
	 *
	 * @return void
	 */
	public static function flush() {
		self::$cache = null;
	}

	/**
	 * Recursive defaults merge that does not renumber list arrays.
	 *
	 * @param array $defaults Defaults.
	 * @param array $values   Stored values.
	 * @return array
	 */
	protected static function merge( $defaults, $values ) {
		foreach ( $values as $key => $value ) {
			if ( is_array( $value ) && isset( $defaults[ $key ] ) && is_array( $defaults[ $key ] ) ) {
				$defaults[ $key ] = self::merge( $defaults[ $key ], $value );
			} else {
				$defaults[ $key ] = $value;
			}
		}

		return $defaults;
	}

	/**
	 * Fold the pre-2.0.0 option rows into the single row, then delete them.
	 *
	 * Gated by the caller on the stored version rather than on "do the old rows
	 * still exist" - an install that has already migrated has no old rows, and a
	 * presence check would write defaults straight over its settings.
	 *
	 * @return void
	 */
	public static function migrate_from_legacy_rows() {
		// Start from whatever is already stored, not from the defaults. The
		// version gate is the primary guard, but it is not sufficient on its own:
		// an install whose download_db_version row is missing while
		// download_options survives - a partial restore, a downgrade and
		// re-upgrade, an over-eager cleanup plugin - would otherwise have every
		// setting overwritten with defaults, because there are no legacy rows
		// left to read them back from. Seeding from all() makes the migration a
		// no-op in that case instead of destructive.
		self::flush();
		$values = self::all();

		foreach ( self::legacy_map() as $legacy => $path ) {
			$stored = get_option( $legacy, null );
			if ( null === $stored || false === $stored ) {
				continue;
			}

			$segments = explode( '.', $path );
			if ( 1 === count( $segments ) ) {
				$values[ $segments[0] ] = $stored;
			} else {
				// Two levels is all the structure has.
				$values[ $segments[0] ][ $segments[1] ] = $stored;
			}
		}

		// download_options is the row being written, so its pre-2.0.0 contents
		// arrived through all() above as stray top-level keys rather than through
		// the loop. Fold them into their new homes and drop the strays, or they
		// would sit in the row forever shadowing nothing.
		if ( isset( $values['rss_sortby'] ) ) {
			$values['rss']['sortby'] = $values['rss_sortby'];
		}
		if ( isset( $values['rss_limit'] ) ) {
			$values['rss']['limit'] = $values['rss_limit'];
		}
		unset( $values['rss_sortby'], $values['rss_limit'] );

		self::save( $values );

		foreach ( array_keys( self::legacy_map() ) as $legacy ) {
			delete_option( $legacy );
		}
		foreach ( self::legacy_extra_rows() as $legacy ) {
			delete_option( $legacy );
		}
	}
}
