<?php
/**
 * What is true of WP-DownloadManager and of no other plugin.
 *
 * The twenty-three assertions §7.2 asks of all nineteen live in
 * Plugin_Metadata_TestCase, a byte-identical copy of
 * _standards/templates/helper-metadata-testcase.php. This file holds the four
 * declarations that class cannot derive - the shipped version, the class
 * prefix, the Upgrade Notice subjects and the handful of hooks - plus the
 * assertions that are genuinely about this plugin: the TinyMCE plugin it
 * ships, the stylesheet it writes, the images it no longer ships, and the
 * hooks and option rows its own source names.
 *
 * @package WP-DownloadManager
 */

/**
 * WP-DownloadManager against §7.2.
 */
class WP_DownloadManager_Metadata_Test extends Plugin_Metadata_TestCase {

	/**
	 * The version this release ships.
	 *
	 * @return string
	 */
	protected function expected_version() {
		return '2.0.0';
	}

	/**
	 * The prefix every class the plugin declares carries.
	 *
	 * @return string
	 */
	protected function class_prefix() {
		return 'WP_DownloadManager';
	}

	/**
	 * Every break a site owner updating from the released 1.68.7 would notice.
	 *
	 * Two screens merged and moved, two public filters renamed with no shim,
	 * every class renamed, nineteen option rows folded into one, the icon
	 * template variable changed meaning, and the stylesheet and its one public
	 * class name both renamed.
	 *
	 * @return string[]
	 */
	protected function upgrade_notice_subjects() {
		return array(
			'6.8',
			'8.2',
			// The screens that moved.
			'wp-downloadmanager-options',
			// The two renamed filters, old name and new.
			'downloads_page',
			'wp_downloadmanager_page',
			'download_embedded',
			'wp_downloadmanager_embedded',
			// The class rename.
			'DownloadManager_Options',
			// The option rows that moved.
			'wp_downloadmanager_options',
			'download_db_version',
			'wp_downloadmanager_version',
			// The icon work: a template variable that changed meaning, a
			// filter that is gone and one whose argument changed.
			'%FILE_ICON%',
			'images/',
			'wp_downloadmanager_file_extension_images_path',
			'wp_downloadmanager_file_extension_image',
			// The stylesheet, and the one class name a theme could have used.
			'download-css.css',
			'wp-downloadmanager.css',
			'download-search-highlight',
			'wp-downloadmanager-highlight',
		);
	}

	/**
	 * The settings row, the marker row and every legacy row uninstall clears.
	 *
	 * @return void
	 */
	protected function seed_option_rows() {
		WP_DownloadManager_Options::save( WP_DownloadManager_Options::defaults() );
		$this->write_version_row();
	}

	/**
	 * Write the marker row through the plugin's own writer.
	 *
	 * @return void
	 */
	protected function write_version_row() {
		WP_DownloadManager_Options::save_markers( $this->expected_version(), WP_DOWNLOADMANAGER_DB_VERSION );
	}

	/**
	 * Round-trip the settings sanitiser.
	 *
	 * @param array $input What the settings form is pretending to have posted.
	 * @return array
	 */
	protected function sanitize_settings( array $input ) {
		return (array) WP_DownloadManager_Settings::sanitize( $input );
	}

	/**
	 * A real settings key, so the sanitiser has something of its own to do.
	 *
	 * @return array
	 */
	protected function settings_fixture() {
		return array( 'page_url' => 'https://example.com/downloads' );
	}

	/**
	 * Register everything the plugin registers.
	 *
	 * The quicktag is registered on its own hook and the admin bundle needs a
	 * screen id, so neither appears without being asked for.
	 *
	 * @return void
	 */
	protected function register_plugin_assets() {
		WP_DownloadManager_Admin::quicktag();
		WP_DownloadManager_Admin::enqueue_assets( 'toplevel_page_' . WP_DownloadManager_Admin::PAGE );
		WP_DownloadManager::enqueue_styles();
	}

	/**
	 * The one dependency in the collection §6 allows.
	 *
	 * The wp-downloadmanager-quicktag script extends the Text editor's quicktag
	 * bar, which is core's `quicktags`. The dependency is not incidental - the
	 * script has nothing to add itself to without it.
	 *
	 * @return string[]
	 */
	protected function allowed_script_dependencies() {
		return array( 'quicktags' );
	}

	/**
	 * One of the seven.
	 *
	 * @return bool
	 */
	protected function wp_stats_family() {
		return true;
	}

	/**
	 * Both shared rows: the migration folds the section toggle and the row
	 * count into this plugin's own settings.
	 *
	 * @return string[]
	 */
	protected function shared_wp_stats_rows() {
		return array( 'stats_display', 'stats_mostlimit' );
	}

	/**
	 * The TinyMCE plugin reaches for no DOM library either.
	 *
	 * The shared jQuery test walks js/, which is where eighteen plugins keep
	 * every script they ship. This one also ships a Classic Editor button under
	 * tinymce/plugins/, and that script was the last jQuery in the plugin.
	 */
	public function test_the_tinymce_plugin_uses_no_dom_library() {
		$scripts = (array) glob( $this->metadata_root() . '/tinymce/plugins/downloadmanager/*.js' );

		$this->assertNotEmpty( $scripts, 'The Classic Editor button ships a script.' );

		foreach ( $scripts as $script ) {
			$code = $this->without_js_comments( $script );

			$this->assertStringNotContainsStringIgnoringCase( 'jquery', $code, basename( $script ) . ' still references jQuery.' );
			$this->assertStringNotContainsString( '$(', $code, basename( $script ) . ' still uses the jQuery alias.' );
		}
	}

	/**
	 * The one stylesheet serves both directions and sets nothing it need not.
	 *
	 * §5.1: no physical properties, so no mirrored sheet is needed, and no
	 * `!important`, which a theme cannot get out from under.
	 */
	public function test_the_stylesheet_uses_logical_properties_only() {
		$css = (string) file_get_contents( $this->metadata_root() . '/css/wp-downloadmanager.css' );

		$physical = array(
			'margin-left',
			'margin-right',
			'padding-left',
			'padding-right',
			'border-left',
			'border-right',
			'text-align: left',
			'text-align: right',
			'float: left',
			'float: right',
		);

		foreach ( $physical as $property ) {
			$this->assertStringNotContainsString(
				$property,
				$css,
				$property . ' is a physical property; use the logical one so one sheet serves both directions.'
			);
		}

		$this->assertStringNotContainsString( '!important', $css, 'No rule may use !important.' );
	}

	/**
	 * Not one raster image ships.
	 *
	 * 2.0.0 replaced the forty-odd file-type GIFs with an inline SVG sprite, so
	 * images/ is gone entirely and no directory may quietly grow another one.
	 */
	public function test_the_plugin_ships_no_images_at_all() {
		$this->assertDirectoryDoesNotExist( $this->metadata_root() . '/images', 'images/ was replaced by the inline SVG sprite.' );

		foreach ( $this->shipped_directories() as $directory ) {
			foreach ( array( 'gif', 'png', 'jpg', 'jpeg', 'bmp', 'ico' ) as $extension ) {
				$this->assertSame(
					array(),
					(array) glob( $directory . '/*.' . $extension ),
					'No raster image may ship with the plugin, and ' . $directory . ' has one.'
				);
			}
		}
	}

	/**
	 * Nothing but class files, the template tags and the silence guard.
	 *
	 * The shared test holds every class-*.php file to §2.4. This one is about
	 * the inventory: template-tags.php is the single non-class file this plugin
	 * is allowed, because the nine template tags are functions a theme calls.
	 */
	public function test_includes_holds_only_class_files_and_the_template_tags() {
		foreach ( (array) glob( $this->metadata_root() . '/includes/*.php' ) as $path ) {
			$name = basename( $path );

			if ( in_array( $name, array( 'index.php', 'template-tags.php' ), true ) ) {
				continue;
			}

			$this->assertStringStartsWith( 'class-wp-downloadmanager', $name, $name . ' is neither a class file nor the template tags.' );
		}
	}

	/**
	 * The copyright block agrees with the header two lines above it.
	 *
	 * The shared test asserts the GPL text ships and bin/verify.py asserts the
	 * License header field reads "GPLv2 or later". Neither reads the block in
	 * the plugin file, and a version-2-only block there contradicts both.
	 */
	public function test_the_gpl_block_is_the_or_later_variant() {
		$plugin = $this->plugin_file();

		$this->assertSame( 'GPLv2 or later', $this->header_field( 'License' ), 'The header licence is GPLv2 or later.' );
		$this->assertStringContainsString( 'either version 2 of the License, or', $plugin, 'The copyright block must be the "or later" variant, matching the header.' );
		$this->assertStringContainsString( '(at your option) any later version.', $plugin, 'The "or later" clause is part of the block.' );
		$this->assertStringContainsString( 'Copyright 2026  Lester Chan  (email : lesterchan@gmail.com)', $plugin, 'Two spaces after the year and around "email :".' );
	}

	/**
	 * A silenced sniff sits inside includes/ and says why.
	 *
	 * §9: phpcs.xml is the shared template and nothing else, so a sniff that is
	 * wrong for one plugin cannot quietly be turned off for all of them. The
	 * residue is an inline suppression, allowed only inside includes/ and only
	 * when it carries a -- reason on the same line saying why the sniff is
	 * wrong. A bare suppression is the failure, not the suppression itself.
	 */
	public function test_every_inline_sniff_suppression_is_inside_includes_and_carries_a_reason() {
		foreach ( $this->plugin_php_files() as $file ) {
			$lines = explode( "\n", (string) file_get_contents( $this->metadata_root() . '/' . $file ) );

			foreach ( $lines as $number => $line ) {
				if ( ! preg_match( '/phpcs:(disable|ignore)\b(.*)$/', $line, $matches ) ) {
					continue;
				}

				$where = $file . ':' . ( $number + 1 );

				$this->assertStringStartsWith( 'includes/', $file, $where . ' silences a sniff outside includes/; fix the code instead.' );
				$this->assertStringContainsString( '--', $matches[2], $where . ' silences a sniff with no reason on the same line.' );
			}
		}
	}

	/**
	 * Every hook the plugin fires carries the plugin prefix.
	 *
	 * The four exceptions are core's own, fired by the widget and the feed.
	 */
	public function test_every_hook_the_plugin_fires_is_prefixed() {
		$core = array( 'rss_update_period', 'rss_update_frequency', 'rss2_head', 'widget_title' );

		foreach ( $this->plugin_php_files() as $file ) {
			preg_match_all(
				"/(?:apply_filters|do_action)(?:_ref_array)?\(\s*'([a-z0-9_]+)'/",
				$this->code( $file ),
				$matches
			);

			foreach ( $matches[1] as $hook ) {
				if ( in_array( $hook, $core, true ) ) {
					continue;
				}

				$this->assertStringStartsWith( 'wp_downloadmanager_', $hook, $hook . ' in ' . $file . ' needs the plugin prefix.' );
			}
		}
	}

	/**
	 * No source file reads an unprefixed option row.
	 *
	 * Collected first and asserted once. Written as an assertion inside two
	 * loops this performed no assertion at all and was reported risky - not
	 * because it had nothing to say, but because it had nothing to find: every
	 * live read goes through a class constant, so no literal option name is
	 * left in the source. That is the desired state, and it now says so out
	 * loud rather than quietly passing over an empty set.
	 */
	public function test_no_source_file_reads_an_unprefixed_option_row() {
		$core      = array( 'home', 'blog_charset', 'date_format', 'time_format', 'gmt_offset' );
		$offenders = array();

		foreach ( $this->plugin_php_files() as $file ) {
			preg_match_all( "/(?:get_option|update_option|add_option)\(\s*'([a-z0-9_]+)'/", $this->code( $file ), $matches );

			foreach ( $matches[1] as $option ) {
				if ( in_array( $option, $core, true ) || 0 === strpos( $option, 'wp_downloadmanager_' ) ) {
					continue;
				}

				$offenders[] = $option . ' in ' . $file;
			}
		}

		$this->assertSame(
			array(),
			$offenders,
			'Unprefixed option rows are named in the source; only the migration may name one, and only to delete it: ' . implode( ', ', $offenders )
		);
	}
}
