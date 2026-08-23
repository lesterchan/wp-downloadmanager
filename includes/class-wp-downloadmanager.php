<?php
/**
 * Bootstrap and front-end wiring for WP-DownloadManager.
 *
 * @package WP-DownloadManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Boots the plugin: the table, the hooks, the components and the front end.
 */
class WP_DownloadManager {

	/**
	 * Register the table, the hooks and the activation hook.
	 *
	 * @return void
	 */
	public static function init() {
		self::register_table();

		// Must be registered at file-load time, which is when this runs.
		register_activation_hook( WP_DOWNLOADMANAGER_MAIN_FILE, array( 'WP_DownloadManager_Install', 'activate' ) );

		// Deliberately on init rather than admin_init. Activation does not fire
		// on a plugin update, which is the single most common reason a migration
		// never runs -- and an automatic background update runs on cron, which
		// is not an admin request.
		add_action( 'init', array( 'WP_DownloadManager_Install', 'maybe_upgrade' ), 5 );

		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_filter( 'generate_rewrite_rules', array( __CLASS__, 'rewrite_rules' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_styles' ) );
		add_action( 'enqueue_block_assets', array( __CLASS__, 'block_editor_styles' ) );
		add_action( 'wp_head', array( __CLASS__, 'feed_link' ) );

		add_action( 'widgets_init', array( __CLASS__, 'register_widget' ) );

		WP_DownloadManager_File::init();
		WP_DownloadManager_Display::init();
		WP_DownloadManager_Blocks::init();
		WP_DownloadManager_WPStats::init();
		WP_DownloadManager_Settings::init();
		WP_DownloadManager_Admin::init();

		self::register_command();
	}

	/**
	 * Register the downloads table with $wpdb.
	 *
	 * The tables[] entry is what keeps the name correct across
	 * switch_to_blog(): wpdb::set_blog_id() rebuilds every registered table
	 * name against the new prefix, while a bare assignment keeps pointing at
	 * whichever site happened to be current when this file loaded.
	 *
	 * @return void
	 */
	private static function register_table() {
		global $wpdb;

		if ( ! in_array( 'downloads', $wpdb->tables, true ) ) {
			$wpdb->tables[] = 'downloads';
		}
		$wpdb->downloads = $wpdb->prefix . 'downloads';
	}

	/**
	 * Register the widget.
	 *
	 * @return void
	 */
	public static function register_widget() {
		register_widget( 'WP_DownloadManager_Widget' );
	}

	/**
	 * Register the WP-CLI command.
	 *
	 * The class file is required here rather than at plugin load because it
	 * extends WP_CLI_Command, which only exists when WP-CLI is the one running
	 * WordPress. Requiring it unconditionally is a fatal error on every web
	 * request.
	 *
	 * @return void
	 */
	public static function register_command() {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		require_once WP_DOWNLOADMANAGER_DIR . 'includes/class-wp-downloadmanager-command.php';

		WP_CLI::add_command( 'downloadmanager', 'WP_DownloadManager_Command' );
	}

	/**
	 * Register the download query vars.
	 *
	 * @param array $vars Public query vars.
	 * @return array
	 */
	public static function query_vars( $vars ) {
		$vars[] = 'dl_id';
		$vars[] = 'dl_name';

		return $vars;
	}

	/**
	 * Prepend the /download/ rewrite rules.
	 *
	 * @param WP_Rewrite $wp_rewrite Rewrite object.
	 * @return void
	 */
	public static function rewrite_rules( $wp_rewrite ) {
		$wp_rewrite->rules = array_merge(
			array(
				'download/([0-9]{1,})/?$' => 'index.php?dl_id=$matches[1]',
				'download/(.*)$'          => 'index.php?dl_name=$matches[1]',
			),
			$wp_rewrite->rules
		);
	}

	/**
	 * The front-end stylesheet, overridable from the theme.
	 *
	 * A copy in the child theme wins, then one in the parent theme, then the
	 * plugin's own.
	 *
	 * @return void
	 */
	public static function enqueue_styles() {
		if ( file_exists( get_stylesheet_directory() . '/wp-downloadmanager.css' ) ) {
			$css_file = get_stylesheet_directory_uri() . '/wp-downloadmanager.css';
		} elseif ( file_exists( get_template_directory() . '/wp-downloadmanager.css' ) ) {
			$css_file = get_template_directory_uri() . '/wp-downloadmanager.css';
		} else {
			$css_file = WP_DOWNLOADMANAGER_URL . 'css/wp-downloadmanager.css';
		}

		wp_enqueue_style( 'wp-downloadmanager', $css_file, array(), WP_DOWNLOADMANAGER_VERSION );
	}

	/**
	 * The same stylesheet, inside the block editor.
	 *
	 * The blocks preview themselves through ServerSideRender, which draws the
	 * real front-end markup into the editor canvas -- and that markup opens with
	 * the inline SVG sprite, which is a definitions block the stylesheet hides.
	 * Without the stylesheet the editor shows an empty 300x150 box above every
	 * listing, and the icons and layout below it are unstyled.
	 *
	 * Guarded on is_admin() because `enqueue_block_assets` fires on the front
	 * end too, where enqueue_styles() has already done this on
	 * `wp_enqueue_scripts`.
	 *
	 * @return void
	 */
	public static function block_editor_styles() {
		if ( ! is_admin() ) {
			return;
		}

		self::enqueue_styles();
	}

	/**
	 * The downloads feed link, on the downloads page only.
	 *
	 * @return void
	 */
	public static function feed_link() {
		if ( ! is_page() ) {
			return;
		}

		$page_url    = WP_DownloadManager_Options::get( 'page_url' );
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		if ( ! $page_url || ! $request_uri || ! strpos( $page_url, $request_uri ) ) {
			return;
		}

		$link = 1 === (int) WP_DownloadManager_Options::get( 'nice_permalink', 1 )
			? get_option( 'home' ) . '/download/rss/'
			: get_option( 'home' ) . '/?dl_name=rss';

		printf(
			'<link rel="alternate" type="application/rss+xml" title="%1$s" href="%2$s" />' . "\n",
			esc_attr(
				sprintf(
					/* translators: %s: The site name. */
					__( '%s Downloads RSS Feed', 'wp-downloadmanager' ),
					get_bloginfo_rss( 'name' )
				)
			),
			esc_url( $link )
		);
	}
}
