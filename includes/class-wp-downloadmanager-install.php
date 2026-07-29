<?php
/**
 * Installation, schema and migration for WP-WP_DownloadManager.
 *
 * @package WP-WP_DownloadManager
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
	 * Run the upgrade path if the stored schema version is behind.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( (int) get_option( WP_DownloadManager_Options::VERSION, 0 ) >= (int) WP_DOWNLOADMANAGER_DB_VERSION ) {
			return;
		}

		self::create_table();
		self::upgrade();
	}

	/**
	 * Everything that has to happen once, gated on the stored version.
	 *
	 * @return void
	 */
	public static function upgrade() {
		$installed = (int) get_option( WP_DownloadManager_Options::VERSION, 0 );

		if ( $installed >= (int) WP_DOWNLOADMANAGER_DB_VERSION ) {
			return;
		}

		if ( $installed < 2 ) {
			self::upgrade_pre_130();
			self::upgrade_pre_150();
			WP_DownloadManager_Options::migrate_from_legacy_rows();
		}

		update_option( WP_DownloadManager_Options::VERSION, (int) WP_DOWNLOADMANAGER_DB_VERSION );
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
	 * Columns added in 1.30.
	 *
	 * @return void
	 */
	protected static function upgrade_pre_130() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		if ( get_option( 'download_nice_permalink', null ) ) {
			return;
		}

		maybe_add_column(
			$wpdb->downloads,
			'file_updated_date',
			"ALTER TABLE {$wpdb->downloads} ADD file_updated_date VARCHAR(20) NOT NULL AFTER file_date;"
		);
		$wpdb->query( "UPDATE {$wpdb->downloads} SET file_updated_date = file_date" ); // phpcs:ignore WordPress.DB

		maybe_add_column(
			$wpdb->downloads,
			'file_last_downloaded_date',
			"ALTER TABLE {$wpdb->downloads} ADD file_last_downloaded_date VARCHAR(20) NOT NULL AFTER file_updated_date;"
		);
		$wpdb->query( "UPDATE {$wpdb->downloads} SET file_last_downloaded_date = file_date" ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Permission values were renumbered in 1.50.
	 *
	 * @return void
	 */
	protected static function upgrade_pre_150() {
		global $wpdb;

		if ( false !== get_option( 'download_options', false ) ) {
			return;
		}

		$moved = $wpdb->query( "UPDATE {$wpdb->downloads} SET file_permission = -2 WHERE file_permission = -1" ); // phpcs:ignore WordPress.DB
		if ( $moved ) {
			$moved = $wpdb->query( "UPDATE {$wpdb->downloads} SET file_permission = -1 WHERE file_permission = 0" ); // phpcs:ignore WordPress.DB
			if ( $moved ) {
				$wpdb->query( "UPDATE {$wpdb->downloads} SET file_permission = 0 WHERE file_permission = 1" ); // phpcs:ignore WordPress.DB
			}
		}
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
	 * Give administrators the plugin capability.
	 *
	 * @return void
	 */
	protected static function grant_capability() {
		$role = get_role( 'administrator' );

		if ( $role && ! $role->has_cap( 'manage_downloads' ) ) {
			$role->add_cap( 'manage_downloads' );
		}
	}
}
