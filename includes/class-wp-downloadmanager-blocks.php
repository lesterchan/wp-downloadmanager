<?php
/**
 * Block registration.
 *
 * @package WP-DownloadManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the plugin's blocks and renders them.
 *
 * **The blocks are added beside the shortcodes, never in place of them.**
 * `[download]`, `[page_download]` and `[page_downloads]` all stay registered,
 * documented and supported, and nothing here deprecates any of them. This
 * plugin has shipped for fifteen years and those shortcodes sit in an
 * unknowable number of published posts; a block that replaced them would break
 * every one of those pages on update.
 *
 * Both blocks are dynamic. Their `save` returns null in JavaScript, so what
 * lands in post_content is the block comment and its attributes and no markup
 * at all -- every view re-renders through the callbacks below. That is what
 * lets a block and a shortcode share one renderer: the markup is decided once,
 * at render time, for both of them.
 *
 * **Neither entry point calls the other.** The block does not run
 * `do_shortcode()` and the shortcode does not ask this class for anything. They
 * are siblings over `WP_DownloadManager_Display`, which is where the rendering
 * lives. Routing the block through `do_shortcode()` would make it inherit the
 * shortcode's attribute parsing -- including the positional `[download=1]`
 * form, which a block has no way to produce -- and would break the block
 * outright the day anybody unregistered the shortcode.
 *
 * **Three shortcodes and two blocks, because two of the three are one
 * shortcode.** `[page_download]` and `[page_downloads]` are both registered to
 * `WP_DownloadManager_Display::page_shortcode()`, which is handed the
 * attributes and never the tag, so it cannot tell which of the two invoked it:
 * identical output, identical single `category` attribute. The singular is the
 * spelling this plugin's README documents, and a block name is written into
 * post_content and outlives the post's edit history, so the block wraps that
 * one and the plural gets no block of its own. It remains a working shortcode.
 *
 * **One class registers both blocks**, rather than a class per block: a class
 * whose entire body is a single `register_block_type_from_metadata()` call
 * would be a file per block for no gain.
 */
class WP_DownloadManager_Blocks {

	/**
	 * Blocks this plugin registers, as build directory => render callback.
	 *
	 * The keys are directory names under `build/`, which is what
	 * `register_block_type_from_metadata()` reads: the name, title, attributes
	 * and script handle all come out of the `block.json` copied there by the
	 * build, so this class never restates any of them.
	 *
	 * @return array<string, callable>
	 */
	private static function blocks() {
		return array(
			'download'      => array( __CLASS__, 'render_download' ),
			'page-download' => array( __CLASS__, 'render_page_download' ),
		);
	}

	/**
	 * Hooks block registration.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Registers every block against its built metadata.
	 *
	 * A missing `build/` directory means the plugin was installed without its
	 * build step having run -- a git checkout rather than a release zip, since
	 * `bin/build` runs before the deploy copies anything. Registering a block
	 * whose script cannot be enqueued gives an editor that silently fails to
	 * load it, so this returns instead and leaves the shortcodes working.
	 *
	 * @return void
	 */
	public static function register() {
		foreach ( self::blocks() as $directory => $callback ) {
			$metadata = WP_DOWNLOADMANAGER_DIR . 'build/' . $directory;

			if ( ! file_exists( $metadata . '/block.json' ) ) {
				continue;
			}

			register_block_type_from_metadata(
				$metadata,
				array( 'render_callback' => $callback )
			);
		}
	}

	/**
	 * Renders the `wp-downloadmanager/download` block.
	 *
	 * The attributes are the `[download]` shortcode's, spelled the way a block
	 * attribute is spelled: `sortBy` here is `sort_by` there. Mapping them is
	 * this method's whole job, and it is why the mapping exists in one place
	 * rather than in the block sources and again in a template.
	 *
	 * The defaults are restated because a block saved before an attribute
	 * existed arrives without it, and `wp_parse_args()` is what fills the gap.
	 *
	 * @param array $attributes Block attributes.
	 *
	 * @return string
	 */
	public static function render_download( $attributes ) {
		$attributes = wp_parse_args(
			$attributes,
			array(
				'id'          => '',
				'category'    => '',
				'display'     => 'both',
				'sortBy'      => 'file_id',
				'sortOrder'   => 'asc',
				'streamLimit' => 0,
			)
		);

		return WP_DownloadManager_Display::render_download(
			$attributes['id'],
			$attributes['category'],
			$attributes['display'],
			$attributes['sortBy'],
			$attributes['sortOrder'],
			$attributes['streamLimit']
		);
	}

	/**
	 * Renders the `wp-downloadmanager/page-download` block.
	 *
	 * `downloads_page()` casts its argument on entry, which is what lets the
	 * integer a block declares and the string a shortcode attribute carries
	 * mean the same category.
	 *
	 * @param array $attributes Block attributes.
	 *
	 * @return string
	 */
	public static function render_page_download( $attributes ) {
		$attributes = wp_parse_args( $attributes, array( 'category' => 0 ) );

		return WP_DownloadManager_Display::downloads_page( $attributes['category'] );
	}
}
