<?php
/**
 * Installation, schema and migration for WP-DownloadManager.
 *
 * @package WP-DownloadManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Creates the table, grants the capability and migrates old installs.
 */
class WP_DownloadManager_Install {

	/**
	 * Hook up.
	 *
	 * @return void
	 */
	public static function init() {
		register_activation_hook( WP_DOWNLOADMANAGER_MAIN_FILE, array( __CLASS__, 'on_activation' ) );

		// Activation does not fire on plugin *update*, which is the single most
		// common reason a migration never runs. Check on load as well.
		add_action( 'admin_init', array( __CLASS__, 'maybe_upgrade' ) );
	}

	/**
	 * Activation hook. Handles network activation site by site.
	 *
	 * @param bool $network_wide Whether the plugin is being network activated.
	 * @return void
	 */
	public static function on_activation( $network_wide = false ) {
		if ( is_multisite() && $network_wide ) {
			// 'number' => 0 lifts WP_Site_Query's default cap of 100.
			$site_ids = get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			);

			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::activate();
				// switch_to_blog() pushes onto a stack, so the restore belongs
				// inside the loop.
				restore_current_blog();
			}
		} else {
			self::activate();
		}
	}

	/**
	 * Install or upgrade the current site.
	 *
	 * @return void
	 */
	public static function activate() {
		self::create_table();
		self::upgrade();
		self::create_files_dir();
		self::grant_capability();

		flush_rewrite_rules();
	}

	/**
	 * Run the upgrade path if either stored marker is behind.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( ! self::is_behind() ) {
			return;
		}

		self::create_table();
		self::upgrade();
	}

	/**
	 * Whether the stored markers are behind the shipped ones.
	 *
	 * @return bool
	 */
	protected static function is_behind() {
		$markers = WP_DownloadManager_Options::markers();

		return (int) $markers['db'] < (int) WP_DOWNLOADMANAGER_DB_VERSION
			|| WP_DOWNLOADMANAGER_VERSION !== $markers['plugin'];
	}

	/**
	 * Everything that has to happen once, gated on the stored schema marker.
	 *
	 * The plugin marker drives the steps that are not schema changes - here,
	 * re-sanitising the settings so a release that tightens a sanitiser cleans
	 * up what an older one let through.
	 *
	 * @return void
	 */
	public static function upgrade() {
		if ( ! self::is_behind() ) {
			return;
		}

		$installed = (int) WP_DownloadManager_Options::markers()['db'];

		// The pre-2.0.0 rows, including WP-Stats' two shared ones. Schema 3 is
		// where they became wp_downloadmanager_options; anything below that has
		// never been through the fold.
		if ( $installed < 3 ) {
			self::upgrade_pre_130();
			self::upgrade_pre_150();
			WP_DownloadManager_Options::migrate_from_legacy_rows();
		} else {
			WP_DownloadManager_Options::flush();
			WP_DownloadManager_Options::save(
				WP_DownloadManager_Settings::sanitize( WP_DownloadManager_Options::all() )
			);
		}

		// Schema 4 is the empty slot at the head of the category list. It runs
		// after either branch above, because both can leave a list behind with a
		// real category at index 0 - the fold brings one across from
		// download_categories, and an install already at schema 3 has been
		// carrying one since it was installed.
		if ( $installed < 4 ) {
			self::upgrade_pre_201();
		}

		// Both markers in one write, so a half-finished upgrade never records
		// itself as complete.
		WP_DownloadManager_Options::save_markers(
			WP_DOWNLOADMANAGER_VERSION,
			WP_DOWNLOADMANAGER_DB_VERSION
		);
		WP_DownloadManager_Options::flush();
	}

	/**
	 * The downloads table.
	 *
	 * Created only when missing rather than run through dbDelta() on every load:
	 * the historical schema uses "text character set utf8", which dbDelta cannot
	 * round-trip, so it would emit an ALTER on every request forever.
	 *
	 * @return void
	 */
	public static function create_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$wpdb->downloads} (
			file_id int(10) NOT NULL auto_increment,
			file tinytext NOT NULL,
			file_name text NOT NULL,
			file_des text NOT NULL,
			file_size varchar(20) NOT NULL default '',
			file_category int(2) NOT NULL default '0',
			file_date varchar(20) NOT NULL default '',
			file_updated_date varchar(20) NOT NULL default '',
			file_last_downloaded_date varchar(20) NOT NULL default '',
			file_hits int(10) NOT NULL default '0',
			file_permission tinyint(2) NOT NULL default '0',
			PRIMARY KEY  (file_id)
		) {$charset_collate};";

		maybe_create_table( $wpdb->downloads, $sql );
	}

	/**
	 * Drop the downloads table for the current site.
	 *
	 * Lives here rather than in uninstall.php so the schema change happens in
	 * includes/, which is where the shared phpcs.xml accepts a direct database
	 * call from a plugin that owns a table. %i binds the identifier, which is
	 * what makes a DROP statement preparable at all.
	 *
	 * uninstall.php reaches this with the plugin inactive, so the registered
	 * $wpdb->downloads may not exist yet and the prefix has to stand in. Both
	 * spellings follow switch_to_blog(): the registered name because
	 * wpdb::set_blog_id() rebuilds it, the prefix because it is what gets
	 * rebuilt.
	 *
	 * @return void
	 */
	public static function drop_table() {
		global $wpdb;

		$table = isset( $wpdb->downloads ) ? $wpdb->downloads : $wpdb->prefix . 'downloads';

		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
	}

	/**
	 * Columns added in 1.30.
	 *
	 * @return void
	 */
	protected static function upgrade_pre_130() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// The 1.30 columns are already there wherever the pre-2.0.0 permalink
		// row still exists, because that row and the columns arrived together.
		$legacy = array_search( 'nice_permalink', WP_DownloadManager_Options::legacy_map(), true );
		if ( $legacy && get_option( $legacy, null ) ) {
			return;
		}

		maybe_add_column(
			$wpdb->downloads,
			'file_updated_date',
			"ALTER TABLE {$wpdb->downloads} ADD file_updated_date VARCHAR(20) NOT NULL AFTER file_date;"
		);
		$wpdb->query( "UPDATE {$wpdb->downloads} SET file_updated_date = file_date" );

		maybe_add_column(
			$wpdb->downloads,
			'file_last_downloaded_date',
			"ALTER TABLE {$wpdb->downloads} ADD file_last_downloaded_date VARCHAR(20) NOT NULL AFTER file_updated_date;"
		);
		$wpdb->query( "UPDATE {$wpdb->downloads} SET file_last_downloaded_date = file_date" );
	}

	/**
	 * Permission values were renumbered in 1.50.
	 *
	 * @return void
	 */
	protected static function upgrade_pre_150() {
		global $wpdb;

		// Only installs that predate the 1.50 renumbering have no settings row
		// at all, so its presence is the marker for "already renumbered".
		$structured = WP_DownloadManager_Options::legacy_structured_rows();
		if ( false !== get_option( $structured['settings'], false ) ) {
			return;
		}

		$moved = $wpdb->query( "UPDATE {$wpdb->downloads} SET file_permission = -2 WHERE file_permission = -1" );
		if ( $moved ) {
			$moved = $wpdb->query( "UPDATE {$wpdb->downloads} SET file_permission = -1 WHERE file_permission = 0" );
			if ( $moved ) {
				$wpdb->query( "UPDATE {$wpdb->downloads} SET file_permission = 0 WHERE file_permission = 1" );
			}
		}
	}

	/**
	 * Category numbers moved up one in 2.0.1, so index 0 is "no category".
	 *
	 * Every other part of the plugin already reads index 0 that way: the listing
	 * page overwrites it with the totals label, the settings textarea leaves it
	 * blank whenever it rebuilds the numbering, and the Add File dropdown hides
	 * any category whose name is empty. What was shipped, since 1.0, was
	 * `array( 'General' )` -- a real category in that slot. So the dropdown
	 * offered General as value 0, files were filed under 0, and the first save of
	 * the settings screen renumbered the list without renumbering the rows: every
	 * download the site had added dropped out of its category, on a screen the
	 * owner had visited to change something else entirely.
	 *
	 * The two halves have to happen together, which is what makes this a
	 * migration rather than a changed default. The list gains its empty head and
	 * every row's file_category moves up by the same one, so a file filed under
	 * General still reads General afterwards.
	 *
	 * A list whose index 0 is already empty is left alone, rows and all: that is
	 * an install whose owner has saved the settings screen at least once, which
	 * did the renumbering to the list years ago, and moving its rows now would be
	 * the same bug with the sign flipped.
	 *
	 * The option is written before the table, and the order is deliberate. Both
	 * the marker and the state of index 0 gate this, so the shift cannot run
	 * twice; writing the list first means a request that dies between the two
	 * cannot come back and add a second 1 to every row.
	 *
	 * @return void
	 */
	protected static function upgrade_pre_201() {
		global $wpdb;

		WP_DownloadManager_Options::flush();
		$categories = (array) WP_DownloadManager_Options::get( 'categories', array() );

		if ( ! isset( $categories[0] ) || '' === trim( (string) $categories[0] ) ) {
			return;
		}

		// Built by hand rather than with array_unshift(), which renumbers from
		// scratch and would close any gap in the keys. A stored list can have
		// gaps -- nothing renumbers it when a category is emptied -- and the
		// column update below adds one to every row, so the keys have to move by
		// exactly one each for the two to still agree.
		$shifted = array( '' );
		foreach ( $categories as $index => $category ) {
			$shifted[ (int) $index + 1 ] = $category;
		}

		WP_DownloadManager_Options::set( 'categories', $shifted );
		WP_DownloadManager_Options::flush();

		// Negative numbers are not category ids and never were; leaving them
		// where they are keeps a hand-written row out of slot 0, which now means
		// something.
		$wpdb->query( "UPDATE {$wpdb->downloads} SET file_category = file_category + 1 WHERE file_category >= 0" );
	}

	/**
	 * Create the downloads directory if it is missing.
	 *
	 * @return void
	 */
	protected static function create_files_dir() {
		$dir = WP_DownloadManager_Options::get( 'path.dir' );

		if ( $dir && ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
	}

	/**
	 * Give administrators the capability the screens actually check.
	 *
	 * `WP_DownloadManager_Admin::capability()`, not the `manage_downloads`
	 * literal it usually returns. Every download screen gates on the filtered
	 * value, so granting the raw string while checking the filtered one means a
	 * site that filters `wp_downloadmanager_capability` hands its administrator
	 * a capability nothing looks at and gates every screen on one nobody holds
	 * -- the owner is locked out of their own plugin, with nothing in any log to
	 * say why. Two places deciding one fact and only one of them told.
	 *
	 * The default context is deliberate: 'downloads' is the data screen, which
	 * is what this capability is for. The settings screen resolves to
	 * `manage_options`, which is core's and is not ours to grant.
	 *
	 * @return void
	 */
	protected static function grant_capability() {
		$role = get_role( 'administrator' );

		if ( $role instanceof WP_Role ) {
			$role->add_cap( WP_DownloadManager_Admin::capability() );
		}
	}

	/**
	 * Take the capability back off the administrator role.
	 *
	 * The same expression grant_capability() grants, filter and all: removing
	 * the literal while granting the filtered value would leave a site that
	 * uses `wp_downloadmanager_capability` with a capability nothing takes back.
	 *
	 * @return void
	 */
	public static function revoke_capability() {
		$role = get_role( 'administrator' );

		if ( $role instanceof WP_Role ) {
			$role->remove_cap( WP_DownloadManager_Admin::capability() );
		}
	}
}
