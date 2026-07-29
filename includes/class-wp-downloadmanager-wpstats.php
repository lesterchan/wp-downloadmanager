<?php
/**
 * WP-Stats integration.
 *
 * Section 13 of the standard replaced the arrangement where seven companion
 * plugins and WP-Stats all read and wrote two shared, unprefixed wp_options
 * rows - stats_display and stats_mostlimit. WP-Stats now asks, through one
 * filter, and each plugin answers for itself out of its own settings row. It is
 * therefore impossible for WP-Stats to render a section for a plugin that is
 * not installed, which the shared-option arrangement never guaranteed.
 *
 * This class is loaded unconditionally and is inert when WP-Stats is absent:
 * nothing fires the filter, so nothing here runs. There is no class_exists()
 * probing between plugins in either direction.
 *
 * @package WP-DownloadManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Contributes the downloads section to WP-Stats.
 */
class WP_DownloadManager_WPStats {

	/**
	 * Hook up.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'wp_stats_sections', array( __CLASS__, 'register_section' ) );
	}

	/**
	 * Answer WP-Stats' one question: does this plugin have a section?
	 *
	 * Reads this plugin's own settings row and nothing else.
	 *
	 * @param array $sections Sections keyed by plugin slug with underscores.
	 * @return array
	 */
	public static function register_section( $sections ) {
		if ( ! is_array( $sections ) ) {
			$sections = array();
		}

		if ( empty( WP_DownloadManager_Options::get( 'stats_display' ) ) ) {
			// Opted out. Contribute nothing rather than an entry with an empty
			// body, which WP-Stats would still print a heading for.
			return $sections;
		}

		$sections['wp_downloadmanager'] = array(
			'title'    => __( 'Downloads', 'wp-downloadmanager' ),
			'priority' => 10,
			'render'   => array( __CLASS__, 'render' ),
		);

		return $sections;
	}

	/**
	 * Echo the section body. Takes no arguments and returns nothing.
	 *
	 * @return void
	 */
	public static function render() {
		self::render_totals();
		self::render_recent();
		self::render_most();
	}

	/**
	 * How many rows the two "most" lists show.
	 *
	 * @return int
	 */
	public static function limit() {
		return max( 1, (int) WP_DownloadManager_Options::get( 'stats_most_limit', 10 ) );
	}

	/**
	 * Files, bytes and hits across the whole library.
	 *
	 * @return void
	 */
	protected static function render_totals() {
		global $wpdb;

		$stats = $wpdb->get_row( "SELECT COUNT(file_id) as total_files, SUM(file_size) total_size, SUM(file_hits) as total_hits FROM {$wpdb->downloads}" );

		// SUM() is NULL on an empty table, which _n() and number_format_i18n()
		// are both deprecated for on PHP 8.1 and later.
		$total_files = (int) $stats->total_files;
		$total_size  = (int) $stats->total_size;
		$total_hits  = (int) $stats->total_hits;

		echo '<ul class="wp-downloadmanager">' . "\n";
		printf(
			'<li>%s</li>' . "\n",
			wp_kses_post(
				sprintf(
					/* translators: %s: number of files. */
					_n( '<strong>%s</strong> file was added.', '<strong>%s</strong> files were added.', $total_files, 'wp-downloadmanager' ),
					number_format_i18n( $total_files )
				)
			)
		);
		printf(
			'<li>%s</li>' . "\n",
			wp_kses_post(
				sprintf(
					/* translators: %s: total size of all files. */
					__( '<strong>%s</strong> worth of files.', 'wp-downloadmanager' ),
					WP_DownloadManager_File::format_size( $total_size )
				)
			)
		);
		printf(
			'<li>%s</li>' . "\n",
			wp_kses_post(
				sprintf(
					/* translators: %s: number of hits. */
					_n( '<strong>%s</strong> hit was generated.', '<strong>%s</strong> hits were generated.', $total_hits, 'wp-downloadmanager' ),
					number_format_i18n( $total_hits )
				)
			)
		);
		echo '</ul>' . "\n";
	}

	/**
	 * The most recently added files.
	 *
	 * @return void
	 */
	protected static function render_recent() {
		$limit = self::limit();

		printf(
			'<p><strong>%s</strong></p>' . "\n",
			esc_html(
				sprintf(
					/* translators: %s: number of downloads. */
					_n( '%s Most Recent Download', '%s Most Recent Downloads', $limit, 'wp-downloadmanager' ),
					number_format_i18n( $limit )
				)
			)
		);

		echo '<ul class="wp-downloadmanager">' . "\n";
		echo wp_kses( WP_DownloadManager_Display::recent_downloads( $limit, 0, false ), WP_DownloadManager_Display::allowed_html() );
		echo '</ul>' . "\n";
	}

	/**
	 * The most downloaded files.
	 *
	 * @return void
	 */
	protected static function render_most() {
		$limit = self::limit();

		printf(
			'<p><strong>%s</strong></p>' . "\n",
			esc_html(
				sprintf(
					/* translators: %s: number of files. */
					_n( '%s Most Downloaded File', '%s Most Downloaded Files', $limit, 'wp-downloadmanager' ),
					number_format_i18n( $limit )
				)
			)
		);

		echo '<ul class="wp-downloadmanager">' . "\n";
		echo wp_kses( WP_DownloadManager_Display::most_downloaded( $limit, 0, false ), WP_DownloadManager_Display::allowed_html() );
		echo '</ul>' . "\n";
	}
}
