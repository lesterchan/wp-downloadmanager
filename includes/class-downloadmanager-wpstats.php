<?php
/**
 * WP-Stats integration.
 *
 * @package WP-DownloadManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds the download panels to WP-Stats.
 */
class DownloadManager_WPStats {

	/**
	 * Hook up.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register with WP-Stats.
	 *
	 * @return void
	 */
	public static function register() {
		add_filter( 'wp_stats_display_defaults', array( __CLASS__, 'defaults' ) );
		add_filter( 'wp_stats_page_admin_plugins', array( __CLASS__, 'admin_general' ) );
		add_filter( 'wp_stats_page_admin_recent', array( __CLASS__, 'admin_recent' ) );
		add_filter( 'wp_stats_page_admin_most', array( __CLASS__, 'admin_most' ) );
		add_filter( 'wp_stats_page_plugins', array( __CLASS__, 'page_general' ) );
		add_filter( 'wp_stats_page_recent', array( __CLASS__, 'page_recent' ) );
		add_filter( 'wp_stats_page_most', array( __CLASS__, 'page_most' ) );
	}

	/**
	 * Tell WP-Stats about the toggles this plugin owns.
	 *
	 * These three keys were never registered with WP-Stats, so they only existed
	 * once someone had saved the options screen. Registering them means the
	 * panels are on by default on a fresh install, as the other companion
	 * plugins are.
	 *
	 * @param array $defaults WP-Stats defaults.
	 * @return array
	 */
	public static function defaults( $defaults ) {
		// WP-Stats' own defaults win, so this only ever adds.
		return array_merge(
			array(
				'downloads'        => 1,
				'recent_downloads' => 1,
				'downloaded_most'  => 1,
			),
			(array) $defaults
		);
	}

	/**
	 * Whether a WP-Stats display toggle is on.
	 *
	 * @param string $key Toggle key.
	 * @return bool
	 */
	public static function enabled( $key ) {
		if ( function_exists( 'wp_stats_display_enabled' ) ) {
			return wp_stats_display_enabled( $key );
		}

		// WP-Stats before 3.0.0 kept the toggles in their own option row.
		$stats_display = get_option( 'stats_display' );

		return is_array( $stats_display ) && 1 === (int) ( $stats_display[ $key ] ?? 0 );
	}

	/**
	 * How many "most" entries WP-Stats shows.
	 *
	 * @return int
	 */
	public static function limit() {
		if ( function_exists( 'wp_stats_most_limit' ) ) {
			return wp_stats_most_limit();
		}

		return (int) get_option( 'stats_mostlimit' );
	}

	/**
	 * One checkbox on the WP-Stats options screen.
	 *
	 * @param string $value Toggle key.
	 * @param string $label Checkbox label.
	 * @return string
	 */
	public static function checkbox( $value, $label ) {
		// WP-Stats 3.0.0 owns the field name, which changed when it consolidated
		// its option rows.
		if ( function_exists( 'wp_stats_checkbox' ) ) {
			return wp_stats_checkbox( $value, $label );
		}

		return '<input type="checkbox" name="stats_display[]" id="wpstats_' . esc_attr( $value ) . '" value="' . esc_attr( $value ) . '"' .
			checked( self::enabled( $value ), true, false ) .
			' />&nbsp;&nbsp;<label for="wpstats_' . esc_attr( $value ) . '">' . esc_html( $label ) . '</label><br />' . "\n";
	}

	/**
	 * General stats checkbox.
	 *
	 * @param string $content Existing markup.
	 * @return string
	 */
	public static function admin_general( $content ) {
		return $content . self::checkbox( 'downloads', __( 'WP-DownloadManager', 'wp-downloadmanager' ) );
	}

	/**
	 * Recent downloads checkbox.
	 *
	 * @param string $content Existing markup.
	 * @return string
	 */
	public static function admin_recent( $content ) {
		$limit = self::limit();

		/* translators: %s: number of downloads. */
		$label = _n( '%s Most Recent Download', '%s Most Recent Downloads', $limit, 'wp-downloadmanager' );

		return $content . self::checkbox( 'recent_downloads', sprintf( $label, number_format_i18n( $limit ) ) );
	}

	/**
	 * Most downloaded checkbox.
	 *
	 * @param string $content Existing markup.
	 * @return string
	 */
	public static function admin_most( $content ) {
		$limit = self::limit();

		/* translators: %s: number of files. */
		$label = _n( '%s Most Downloaded File', '%s Most Downloaded Files', $limit, 'wp-downloadmanager' );

		return $content . self::checkbox( 'downloaded_most', sprintf( $label, number_format_i18n( $limit ) ) );
	}

	/**
	 * General stats panel.
	 *
	 * @param string $content Existing markup.
	 * @return string
	 */
	public static function page_general( $content ) {
		global $wpdb;

		if ( ! self::enabled( 'downloads' ) ) {
			return $content;
		}

		// phpcs:ignore WordPress.DB
		$stats = $wpdb->get_row( "SELECT COUNT(file_id) as total_files, SUM(file_size) total_size, SUM(file_hits) as total_hits FROM {$wpdb->downloads}" );

		$content .= '<p><strong>' . __( 'WP-DownloadManager', 'wp-downloadmanager' ) . '</strong></p>' . "\n";
		$content .= '<ul>' . "\n";
		$content .= '<li>' . sprintf(
			/* translators: %s: number of files. */
			_n( '<strong>%s</strong> file was added.', '<strong>%s</strong> files were added.', $stats->total_files, 'wp-downloadmanager' ),
			number_format_i18n( $stats->total_files )
		) . '</li>' . "\n";
		$content .= '<li>' . sprintf(
			/* translators: %s: total size of all files. */
			_n( '<strong>%s</strong> worth of files.', '<strong>%s</strong> worth of files.', $stats->total_size, 'wp-downloadmanager' ),
			DownloadManager_File::format_size( $stats->total_size )
		) . '</li>' . "\n";
		$content .= '<li>' . sprintf(
			/* translators: %s: number of hits. */
			_n( '<strong>%s</strong> hit was generated.', '<strong>%s</strong> hits were generated.', $stats->total_hits, 'wp-downloadmanager' ),
			number_format_i18n( $stats->total_hits )
		) . '</li>' . "\n";
		$content .= '</ul>' . "\n";

		return $content;
	}

	/**
	 * Recent downloads panel.
	 *
	 * @param string $content Existing markup.
	 * @return string
	 */
	public static function page_recent( $content ) {
		if ( ! self::enabled( 'recent_downloads' ) ) {
			return $content;
		}

		$limit = self::limit();

		$content .= '<p><strong>' . sprintf(
			/* translators: %s: number of downloads. */
			_n( '%s Most Recent Download', '%s Most Recent Downloads', $limit, 'wp-downloadmanager' ),
			number_format_i18n( $limit )
		) . '</strong></p>' . "\n";
		$content .= '<ul>' . "\n";
		$content .= DownloadManager_Display::recent_downloads( $limit, 0, false );
		$content .= '</ul>' . "\n";

		return $content;
	}

	/**
	 * Most downloaded panel.
	 *
	 * @param string $content Existing markup.
	 * @return string
	 */
	public static function page_most( $content ) {
		if ( ! self::enabled( 'downloaded_most' ) ) {
			return $content;
		}

		$limit = self::limit();

		$content .= '<p><strong>' . sprintf(
			/* translators: %s: number of files. */
			_n( '%s Most Downloaded File', '%s Most Downloaded Files', $limit, 'wp-downloadmanager' ),
			number_format_i18n( $limit )
		) . '</strong></p>' . "\n";
		$content .= '<ul>' . "\n";
		$content .= DownloadManager_Display::most_downloaded( $limit, 0, false );
		$content .= '</ul>' . "\n";

		return $content;
	}
}
