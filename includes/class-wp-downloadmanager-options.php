<?php
/**
 * Consolidated option storage for WP-DownloadManager.
 *
 * Everything a site owner can configure lives in one wp_options row holding a
 * nested array, rather than the nineteen separate rows used up to 1.69.2. The
 * two version markers live in a second row of their own - see
 * WP_DownloadManager_Options::VERSION for why they are not in here with
 * everything else.
 *
 * The value is a plain PHP array: update_option() serialises it and
 * get_option() unserialises it, so there is no encode/decode layer at the call
 * sites and register_setting()'s sanitize_callback receives the structure
 * intact.
 *
 * @package WP-DownloadManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the wp_downloadmanager_options and
 * wp_downloadmanager_version rows.
 */
class WP_DownloadManager_Options {

	/**
	 * Name of the consolidated option row.
	 *
	 * @var string
	 */
	const OPTION = 'wp_downloadmanager_options';

	/**
	 * Name of the row holding the two version markers.
	 *
	 * Kept out of the settings array on purpose: the settings form never posts
	 * the markers, so anything living in that array has to be rescued from the
	 * stored value on every save. With a separate row the settings screen writes
	 * OPTION, the upgrade routine writes VERSION, and neither can corrupt the
	 * other.
	 *
	 * @var string
	 */
	const VERSION = 'wp_downloadmanager_version';

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
			// Section 13: WP-Stats used to own these two rows and all seven
			// companion plugins read them. Each plugin keeps its own copy now,
			// so wp-stats never has to read a sibling's settings.
			'stats_mostlimit'         => 'stats_most_limit',
		);

		foreach ( WP_DownloadManager_Template::keys() as $key ) {
			$map[ 'download_template_' . $key ] = 'templates.' . $key;
		}

		return $map;
	}

	/**
	 * Legacy rows read by the migration outside the flat dot-path map.
	 *
	 * The pre-2.0.0 settings row held the whole settings array and stats_display
	 * an array of per-panel toggles shared with six other plugins, so neither
	 * folds in with a single assignment.
	 *
	 * @return array
	 */
	public static function legacy_structured_rows() {
		return array(
			'settings' => 'download_options',
			'stats'    => 'stats_display',
		);
	}

	/**
	 * The two unprefixed rows this plugin shared with WP-Stats and five others.
	 *
	 * They were never any one plugin's to own: whichever of the seven saved the
	 * WP-Stats screen last wrote the whole row. Each plugin keeps its own copy of
	 * both settings now, so the migration folds these in and deletes them -- they
	 * are named in legacy_map() and legacy_structured_rows() for exactly that
	 * reason, because the fold needs to know where each one lands.
	 *
	 * **Uninstall must subtract them.** §13.2 splits the two jobs: the migration
	 * deletes a shared row because it has folded it in, and uninstall leaves it
	 * alone because up to six siblings that have not upgraded yet are still
	 * reading it. Removing WP-DownloadManager from a site was taking the WP-Stats
	 * settings of every one of them with it, silently and on both rows.
	 *
	 * The single list that drives the migration and uninstall together is what
	 * keeps the two honest about the plugin's own rows, and it is also what caused
	 * this -- so the exception is named here rather than spelled out at each of
	 * the places that has to apply it.
	 *
	 * @return array
	 */
	public static function legacy_shared_rows() {
		return array( 'stats_display', 'stats_mostlimit' );
	}

	/**
	 * Legacy rows that carry no value forward but must still be cleaned up.
	 *
	 * @return array
	 */
	public static function legacy_extra_rows() {
		return array(
			'download_db_version',
			'widget_download_most_downloaded',
			'widget_download_recent_downloads',
		);
	}

	/**
	 * Default values for every key.
	 *
	 * These mirror the pre-2.0.0 add_option() calls, with one deliberate
	 * exception: `categories` gains the empty element at index 0 that the rest of
	 * the plugin has always assumed was there. 1.69.2 shipped
	 * `array( 'General' )`, so a fresh install put its only category in the slot
	 * a file uses to mean "no category", the Add File dropdown offered it as
	 * value 0, and the first save of the settings screen -- which rebuilds the
	 * numbering from the textarea, where index 0 is always blank -- moved
	 * 'General' to 1 and left every file behind at 0 reading as uncategorised.
	 *
	 * Changing any of the others silently changes what a fresh install looks
	 * like.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'path'             => array(
				'dir' => WP_CONTENT_DIR . '/files',
				'url' => content_url( 'files' ),
			),
			'page_url'         => site_url( 'downloads' ),
			'method'           => 1,
			'nice_permalink'   => 1,
			'use_filename'     => 0,
			'categories'       => array( '', 'General' ),
			'sort'             => array(
				'by'      => 'file_name',
				'order'   => 'asc',
				'perpage' => 20,
				'group'   => 1,
			),
			'rss'              => array(
				'sortby' => 'file_date',
				'limit'  => 20,
			),
			// Section 13. Whether to contribute a section to WP-Stats, and how
			// many rows that section lists. Read by WP_DownloadManager_WPStats
			// and by nothing outside this plugin.
			'stats_display'    => 1,
			'stats_most_limit' => 10,
			'templates'        => WP_DownloadManager_Template::defaults(),
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
	 * `update_option()` declines to write a value equal to the one `get_option()`
	 * would return, and `register_setting()` is passed a `default`, which installs
	 * a `default_option_wp_downloadmanager_options` filter answering with the
	 * shipped defaults for a row that does not exist. Core's `add_option()`
	 * fallback sits immediately below that comparison and is unreachable once the
	 * two compare equal. So a migration whose result happens to equal the
	 * defaults -- the commonest install there is -- writes nothing at all, the row
	 * is never created, and the markers are stamped complete either way, so the
	 * upgrade can never run again while the nineteen old rows have already been
	 * deleted.
	 *
	 * It was held off by nothing but hook order: the migration runs on `init`
	 * and `register_setting()` on `admin_init`, so the migration goes first.
	 * Any third-party `default_option_*` filter reaches it silently.
	 *
	 * Passing an explicit default to `get_option()` defeats the registered one --
	 * `filter_default_option()` returns early when a default was passed -- which
	 * is what lets an absent row be told apart from a defaulted one and added
	 * outright. `add_option()` runs the sanitize callback exactly as
	 * `update_option()` does, so nothing else about the stored value changes.
	 *
	 * @param array $values Full option array.
	 * @return bool
	 */
	public static function save( $values ) {
		self::$cache = self::merge( self::defaults(), (array) $values );

		if ( false === get_option( self::OPTION, false ) ) {
			return add_option( self::OPTION, self::$cache );
		}

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
	 * Keys holding a list, which a stored value replaces rather than fills in.
	 *
	 * `categories` is numbered and the numbers are data: element 3 of the list is
	 * category 3, and that is what every row's `file_category` column points at.
	 * So the stored list has to come back exactly as long as it was written.
	 * Filling the short end in from the defaults hands a site categories it does
	 * not have -- a stored `array( 'General' )`, which is what every install
	 * created before the empty slot at index 0 was shipped, comes back as
	 * 'General' twice under two different numbers, and a site that emptied its
	 * category list is given 'General' back on every read.
	 *
	 * It is the one key where that is true. Every other array here is a fixed set
	 * of named settings, and a stored one missing a key genuinely wants the
	 * default for it -- that is how a partial save keeps its siblings.
	 *
	 * @return array
	 */
	protected static function list_keys() {
		return array( 'categories' );
	}

	/**
	 * Recursive defaults merge that does not renumber list arrays.
	 *
	 * @param array $defaults Defaults.
	 * @param array $values   Stored values.
	 * @return array
	 */
	protected static function merge( $defaults, $values ) {
		$lists = self::list_keys();

		foreach ( $values as $key => $value ) {
			$fill_in = is_array( $value )
				&& isset( $defaults[ $key ] )
				&& is_array( $defaults[ $key ] )
				&& ! in_array( $key, $lists, true );

			if ( $fill_in ) {
				$defaults[ $key ] = self::merge( $defaults[ $key ], $value );
			} else {
				$defaults[ $key ] = $value;
			}
		}

		return $defaults;
	}

	/**
	 * Drop the <img> that used to wrap %FILE_ICON%.
	 *
	 * @param string $template Stored template markup.
	 * @return string
	 */
	protected static function unwrap_icon( $template ) {
		return (string) preg_replace( '#<img[^>]*%FILE_ICON%[^>]*/?>#i', '%FILE_ICON%', (string) $template );
	}

	/**
	 * The two version markers, normalised.
	 *
	 * @return array Keys 'plugin' and 'db', in that order, and nothing else.
	 */
	public static function markers() {
		$stored = get_option( self::VERSION, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return array(
			'plugin' => isset( $stored['plugin'] ) ? (string) $stored['plugin'] : '',
			'db'     => isset( $stored['db'] ) ? (string) $stored['db'] : '0',
		);
	}

	/**
	 * Record that this version's upgrade has finished.
	 *
	 * One update_option() for both markers, so a half-finished upgrade can
	 * never record itself as complete.
	 *
	 * @return void
	 */
	public static function update_markers() {
		update_option(
			self::VERSION,
			array(
				'plugin' => WP_DOWNLOADMANAGER_VERSION,
				'db'     => WP_DOWNLOADMANAGER_DB_VERSION,
			)
		);
	}

	/**
	 * Fold the pre-2.0.0 option rows into the single row, then delete them.
	 *
	 * Gated by the caller on the stored schema marker rather than on "do the old
	 * rows still exist" - an install that has already migrated has no old rows,
	 * and a presence check would write defaults straight over its settings.
	 *
	 * @return void
	 */
	public static function migrate_legacy_rows() {
		// Start from whatever is already stored, not from the defaults. The
		// marker gate is the primary guard, but it is not sufficient on its own:
		// an install whose marker row is missing while the settings row survives
		// - a partial restore, a downgrade and re-upgrade, an over-eager cleanup
		// plugin - would otherwise have every setting overwritten with defaults,
		// because there are no legacy rows left to read them back from. Seeding
		// from all() makes the migration a no-op in that case instead of
		// destructive.
		self::flush();
		$values = self::all();

		// The 1.69.2 settings row, which held use_filename, rss_sortby and
		// rss_limit under names that no longer exist. Read before the flat map so
		// a dedicated row still wins over the copy inside it.
		$structured      = self::legacy_structured_rows();
		$legacy_settings = get_option( $structured['settings'], array() );
		if ( is_array( $legacy_settings ) ) {
			// Anything already using a current key comes across as it stands.
			$values = self::merge( $values, array_intersect_key( $legacy_settings, self::defaults() ) );

			if ( isset( $legacy_settings['rss_sortby'] ) ) {
				$values['rss']['sortby'] = $legacy_settings['rss_sortby'];
			}
			if ( isset( $legacy_settings['rss_limit'] ) ) {
				$values['rss']['limit'] = (int) $legacy_settings['rss_limit'];
			}
		}

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

		// WP-Stats' shared toggle row carried one flag per panel, and this plugin
		// contributes one section now, so it is on if any of its three panels
		// was.
		//
		// The default for a MISSING row is on, not off, and that is the whole
		// point of the null here. Seven plugins read this row and every one of
		// their migrations deletes it, so whichever one a site upgrades first
		// takes it away from the other six. Reading its absence as a deliberate
		// opt-out would make a site that updated WP-Stats before this plugin
		// lose the downloads block with nothing to explain why. The worst the
		// rule below can do is leave a block switched on that someone has to
		// switch off again.
		$legacy = get_option( $structured['stats'], null );

		if ( null !== $legacy ) {
			$values['stats_display'] = is_array( $legacy )
				? (int) (bool) array_filter(
					array_intersect_key(
						$legacy,
						array_flip( array( 'downloads', 'recent_downloads', 'downloaded_most' ) )
					)
				)
				: (int) (bool) $legacy;
		}

		$values['stats_most_limit'] = max( 1, (int) $values['stats_most_limit'] );

		// %FILE_ICON% used to be a GIF file name a template dropped into an
		// <img src>. It is the whole icon element now, so the stock wrapper is
		// unwrapped wherever a stored template still has it - otherwise every
		// listing would render an <img> whose src is an inline <svg>.
		foreach ( WP_DownloadManager_Template::keys() as $key ) {
			if ( ! isset( $values['templates'][ $key ] ) ) {
				continue;
			}

			$values['templates'][ $key ] = is_array( $values['templates'][ $key ] )
				? array_map( array( __CLASS__, 'unwrap_icon' ), $values['templates'][ $key ] )
				: self::unwrap_icon( $values['templates'][ $key ] );
		}

		self::save( $values );

		$legacy_rows = array_merge(
			array_keys( self::legacy_map() ),
			array_values( self::legacy_structured_rows() ),
			self::legacy_extra_rows()
		);

		foreach ( $legacy_rows as $legacy ) {
			delete_option( $legacy );
		}
	}
}
