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
class DownloadManager {

	/**
	 * Hook up.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_filter( 'generate_rewrite_rules', array( __CLASS__, 'rewrite_rules' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_styles' ) );
		add_action( 'wp_head', array( __CLASS__, 'feed_link' ) );
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
	 * The downloads feed link, on the downloads page only.
	 *
	 * @return void
	 */
	public static function feed_link() {
		if ( ! is_page() ) {
			return;
		}

		$page_url    = DownloadManager_Options::get( 'page_url' );
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		if ( ! $page_url || ! $request_uri || ! strpos( $page_url, $request_uri ) ) {
			return;
		}

		$link = 1 === (int) DownloadManager_Options::get( 'nice_permalink', 1 )
			? get_option( 'home' ) . '/download/rss/'
			: get_option( 'home' ) . '/?dl_name=rss';

		printf(
			'<link rel="alternate" type="application/rss+xml" title="%1$s" href="%2$s" />' . "\n",
			esc_attr( get_bloginfo_rss( 'name' ) . __( ' Downloads RSS Feed', 'wp-downloadmanager' ) ),
			esc_url( $link )
		);
	}
}
