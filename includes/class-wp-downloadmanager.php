<?php
/**
 * Front-end wiring for WP-DownloadManager.
 *
 * @package WP-DownloadManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Query vars, rewrite rules, assets and the feed link.
 */
class WP_DownloadManager {

	/**
	 * Hook up.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_filter( 'generate_rewrite_rules', array( __CLASS__, 'rewrite_rules' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_styles' ) );
		add_action( 'enqueue_block_assets', array( __CLASS__, 'block_editor_styles' ) );
		add_action( 'wp_head', array( __CLASS__, 'feed_link' ) );

		self::register_command();
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
	 * @return void
	 */
	public static function enqueue_styles() {
		$theme_css = get_stylesheet_directory() . '/wp-downloadmanager.css';

		$src = file_exists( $theme_css )
			? get_stylesheet_directory_uri() . '/wp-downloadmanager.css'
			: WP_DOWNLOADMANAGER_URL . 'css/wp-downloadmanager.css';

		wp_enqueue_style( 'wp-downloadmanager', $src, array(), WP_DOWNLOADMANAGER_VERSION );
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
