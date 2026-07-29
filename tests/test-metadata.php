<?php
/**
 * The checks every one of the nineteen plugins carries.
 *
 * These assert on the things that are supposed to be identical everywhere:
 * the README header, the canonical section list, the changelog prefixes, the
 * shape of the version row, the absence of jQuery and of an RTL stylesheet.
 * They are deliberately about metadata rather than behaviour - the point is
 * that the plugin cannot drift away from its siblings without a test failing.
 *
 * @package WP-DownloadManager
 */

/**
 * Plugin, README and layout metadata.
 */
class WP_DownloadManager_Metadata_Test extends WP_DownloadManager_TestCase {

	/**
	 * The plugin root.
	 *
	 * @var string
	 */
	protected $root;

	/**
	 * The README, whole.
	 *
	 * @var string
	 */
	protected $readme;

	/**
	 * The plugin file, whole.
	 *
	 * @var string
	 */
	protected $plugin;

	/**
	 * Read the two files the assertions compare.
	 */
	public function set_up() {
		parent::set_up();

		$this->root   = dirname( __DIR__ );
		$this->readme = file_get_contents( $this->root . '/README.md' );
		$this->plugin = file_get_contents( $this->root . '/wp-downloadmanager.php' );
	}

	/**
	 * The nine README header fields, in order.
	 *
	 * @return array
	 */
	protected function readme_header() {
		$lines  = explode( "\n", $this->readme );
		$header = array();

		foreach ( array_slice( $lines, 1 ) as $line ) {
			if ( '' === trim( $line ) ) {
				break;
			}
			$header[] = $line;
		}

		return $header;
	}

	/**
	 * One field out of the plugin file header.
	 *
	 * @param string $field Field name including its colon.
	 * @return string|null
	 */
	protected function plugin_header( $field ) {
		if ( preg_match( '/^\s*\*\s*' . preg_quote( $field, '/' ) . '\s*(.+?)\s*$/m', $this->plugin, $match ) ) {
			return $match[1];
		}

		return null;
	}

	/**
	 * One field out of the README header.
	 *
	 * @param string $field Field name including its colon.
	 * @return string|null
	 */
	protected function readme_field( $field ) {
		if ( preg_match( '/^' . preg_quote( $field, '/' ) . '\s*(.+?)\s*$/m', $this->readme, $match ) ) {
			return $match[1];
		}

		return null;
	}

	public function test_every_readme_header_line_keeps_its_line_break() {
		$header = $this->readme_header();

		$this->assertCount( 9, $header, 'the README header must be exactly nine fields' );

		foreach ( array_slice( $header, 0, 8 ) as $index => $line ) {
			$this->assertStringEndsWith(
				'  ',
				$line,
				'README header line ' . ( $index + 2 ) . ' needs two trailing spaces or Markdown swallows the break: ' . trim( $line )
			);
		}

		$last = $header[8];
		$this->assertSame( rtrim( $last ), $last, 'the last README header line must not have trailing spaces' );
	}

	public function test_canonical_lesterchan_urls() {
		$this->assertSame(
			'https://lesterchan.net/portfolio/programming/php/',
			$this->plugin_header( 'Plugin URI:' ),
			'Plugin URI is the same in all nineteen plugins'
		);
		$this->assertSame(
			'https://lesterchan.net',
			$this->plugin_header( 'Author URI:' ),
			'Author URI is the same in all nineteen plugins'
		);
		$this->assertSame(
			'https://lesterchan.net/site/donation/',
			$this->readme_field( 'Donate link:' ),
			'Donate link is the same in all nineteen plugins'
		);
		$this->assertSame(
			'https://www.gnu.org/licenses/gpl-2.0.html',
			$this->readme_field( 'License URI:' ),
			'License URI is the same in all nineteen plugins'
		);
		$this->assertSame(
			'https://www.gnu.org/licenses/gpl-2.0.html',
			$this->plugin_header( 'License URI:' ),
			'the plugin header License URI must match the README'
		);
	}

	public function test_contributors_is_gamerz_only() {
		$this->assertSame(
			'GamerZ',
			$this->readme_field( 'Contributors:' ),
			'Contributors is GamerZ and nothing else, in every plugin'
		);
	}

	public function test_text_domain_is_the_plugin_slug() {
		$this->assertSame( 'wp-downloadmanager', $this->plugin_header( 'Text Domain:' ), 'the text domain is the plugin slug' );
		$this->assertSame( '/languages', $this->plugin_header( 'Domain Path:' ), 'translations live in /languages' );
		$this->assertSame( 'wp-downloadmanager', WP_DOWNLOADMANAGER_SLUG, 'WP_DOWNLOADMANAGER_SLUG is the plugin slug' );
	}

	public function test_version_matches_everywhere() {
		$this->assertSame( '2.0.0', WP_DOWNLOADMANAGER_VERSION, 'the shipped version is 2.0.0' );
		$this->assertSame( WP_DOWNLOADMANAGER_VERSION, $this->plugin_header( 'Version:' ), 'the plugin header Version must match the constant' );
		$this->assertSame( WP_DOWNLOADMANAGER_VERSION, $this->readme_field( 'Stable tag:' ), 'the README Stable tag must match the constant' );
		$this->assertStringContainsString( '### ' . WP_DOWNLOADMANAGER_VERSION, $this->readme, 'the changelog needs an entry for the shipped version' );
	}

	public function test_requires_headers_match_readme() {
		$this->assertSame( '6.8', $this->plugin_header( 'Requires at least:' ), 'the WordPress floor is 6.8' );
		$this->assertSame( '8.2', $this->plugin_header( 'Requires PHP:' ), 'the PHP floor is 8.2' );
		$this->assertSame( $this->plugin_header( 'Requires at least:' ), $this->readme_field( 'Requires at least:' ), 'the two WordPress floors must agree' );
		$this->assertSame( $this->plugin_header( 'Requires PHP:' ), $this->readme_field( 'Requires PHP:' ), 'the two PHP floors must agree' );

		$composer = json_decode( file_get_contents( $this->root . '/composer.json' ), true );
		$this->assertSame( '>=8.2', $composer['require']['php'], 'composer.json must carry the same PHP floor' );

		$env = json_decode( file_get_contents( $this->root . '/.wp-env.json' ), true );
		$this->assertSame( '8.2', $env['phpVersion'], 'wp-env must run the floor PHP version' );
	}

	public function test_readme_sections_are_the_canonical_set() {
		preg_match_all( '/^## (.+?)\s*$/m', $this->readme, $matches );

		$this->assertSame(
			array( 'Description', 'Usage', 'Frequently Asked Questions', 'Screenshots', 'Changelog', 'Upgrade Notice' ),
			$matches[1],
			'the level-2 headings are a closed, ordered set'
		);
	}

	public function test_changelog_prefixes_are_canonical() {
		$allowed = array( 'BREAKING:', 'NEW:', 'CHANGED:', 'FIXED:', 'NOTE:' );
		$in_log  = false;
		$checked = 0;

		foreach ( explode( "\n", $this->readme ) as $line ) {
			if ( 0 === strpos( $line, '## Changelog' ) ) {
				$in_log = true;
				continue;
			}
			if ( $in_log && 0 === strpos( $line, '## ' ) ) {
				break;
			}
			if ( ! $in_log || 0 !== strpos( $line, '* ' ) ) {
				continue;
			}

			$body    = substr( $line, 2 );
			$matched = false;
			foreach ( $allowed as $prefix ) {
				if ( 0 === strpos( $body, $prefix ) ) {
					$matched = true;
					break;
				}
			}

			++$checked;
			$this->assertTrue( $matched, 'changelog bullet must start with one of the five allowed prefixes: ' . substr( $body, 0, 60 ) );
		}

		$this->assertGreaterThan( 0, $checked, 'the changelog should have bullets to check' );
	}

	public function test_no_jquery_is_enqueued() {
		WP_DownloadManager_Admin::quicktag();
		WP_DownloadManager_Admin::enqueue_assets( 'toplevel_page_' . WP_DownloadManager_Admin::PAGE );
		WP_DownloadManager::enqueue_styles();

		foreach ( wp_scripts()->registered as $handle => $script ) {
			if ( 0 !== strpos( $handle, 'wp-downloadmanager' ) ) {
				continue;
			}
			$this->assertNotContains( 'jquery', $script->deps, $handle . ' must not depend on jQuery' );
		}
	}

	public function test_no_shipped_script_references_the_old_dom_library() {
		$scripts = array_merge(
			(array) glob( $this->root . '/js/*.js' ),
			(array) glob( $this->root . '/tinymce/plugins/downloadmanager/*.js' )
		);

		$this->assertNotEmpty( $scripts, 'the plugin ships scripts to check' );

		foreach ( $scripts as $script ) {
			$this->assertDoesNotMatchRegularExpression(
				'/\bjQuery\b|\$\(/',
				file_get_contents( $script ),
				basename( $script ) . ' still reaches for jQuery'
			);
		}
	}

	public function test_every_directory_has_an_index_php() {
		$skip    = array( 'node_modules', 'vendor', '.git' );
		$checked = 0;

		foreach ( (array) glob( $this->root . '/*', GLOB_ONLYDIR ) as $top ) {
			if ( in_array( basename( $top ), $skip, true ) ) {
				continue;
			}

			foreach ( $this->directories_under( $top, $skip ) as $dir ) {
				++$checked;
				$this->assertFileExists( $dir . '/index.php', $dir . ' needs a silence-is-golden index.php' );
			}
		}

		$this->assertFileExists( $this->root . '/index.php', 'the plugin root needs one too' );
		$this->assertGreaterThan( 4, $checked, 'bin, css, includes, js and tests are all shipped directories' );
	}

	/**
	 * Every directory at or below a path, skipping dependency trees.
	 *
	 * @param string $path Directory to start from.
	 * @param array  $skip Directory names never descended into.
	 * @return array
	 */
	protected function directories_under( $path, $skip ) {
		$found = array( $path );

		foreach ( (array) glob( $path . '/*', GLOB_ONLYDIR ) as $child ) {
			if ( in_array( basename( $child ), $skip, true ) ) {
				continue;
			}
			$found = array_merge( $found, $this->directories_under( $child, $skip ) );
		}

		return $found;
	}

	public function test_uninstall_removes_every_option_row() {
		global $wpdb;

		WP_DownloadManager_Options::save( WP_DownloadManager_Options::defaults() );
		WP_DownloadManager_Options::save_markers( '2.0.0', '3' );

		$this->assertNotEmpty( get_option( WP_DownloadManager_Options::OPTION ), 'the settings row should exist before uninstall' );

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'wp-downloadmanager/wp-downloadmanager.php' );
		}
		require dirname( __DIR__ ) . '/uninstall.php';

		$left = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'wp\\_downloadmanager\\_%'" );

		$this->assertSame( array(), $left, 'no wp_downloadmanager_% row may survive uninstall: ' . implode( ', ', $left ) );
		$this->assertNull(
			$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $this->table() ) ),
			'the downloads table is dropped by uninstall'
		);

		// Put it back, or every test that runs after this one truncates a table
		// that is no longer there.
		WP_DownloadManager_Install::activate();
	}

	public function test_version_row_holds_exactly_plugin_and_db() {
		WP_DownloadManager_Options::save_markers( '2.0.0', '3' );

		$stored = get_option( WP_DownloadManager_Options::VERSION );

		$this->assertIsArray( $stored, 'the version row is an array' );
		$this->assertSame( array( 'plugin', 'db' ), array_keys( $stored ), 'the version row holds exactly plugin and db, in that order' );
		$this->assertSame( '2.0.0', $stored['plugin'], 'the plugin marker is the version last run' );
		$this->assertSame( '3', $stored['db'], 'the db marker is the schema counter' );
	}

	public function test_settings_sanitizer_never_stores_version_markers() {
		$saved = WP_DownloadManager_Settings::sanitize(
			array(
				'page_url' => 'https://example.com/downloads',
				'version'  => '9.9.9',
				'db'       => '99',
			)
		);

		foreach ( array( 'version', 'db_version', 'versions' ) as $forbidden ) {
			$this->assertArrayNotHasKey(
				$forbidden,
				$saved,
				'the settings array must never carry a version marker; that is what the separate row is for'
			);
		}

		$this->assertSame(
			array( 'plugin', 'db' ),
			array_keys( WP_DownloadManager_Options::markers() ),
			'the markers live in their own row and stay there'
		);
	}

	public function test_no_rtl_stylesheet_is_registered() {
		$this->assertSame(
			array(),
			(array) glob( $this->root . '/css/*-rtl.css' ),
			'there is no separate RTL stylesheet; the one sheet is direction-neutral'
		);

		WP_DownloadManager::enqueue_styles();

		$this->assertFalse(
			wp_styles()->get_data( 'wp-downloadmanager', 'rtl' ),
			'no rtl style data may be registered'
		);
	}

	public function test_the_stylesheet_uses_logical_properties_only() {
		$css = file_get_contents( $this->root . '/css/wp-downloadmanager.css' );

		foreach ( array( 'margin-left', 'margin-right', 'padding-left', 'padding-right', 'border-left', 'border-right', 'text-align: left', 'text-align: right', 'float: left', 'float: right' ) as $physical ) {
			$this->assertStringNotContainsString(
				$physical,
				$css,
				$physical . ' is a physical property; use the logical one so one sheet serves both directions'
			);
		}

		$this->assertStringNotContainsString( '!important', $css, 'no rule may use !important' );
	}

	public function test_the_gpl_block_is_the_or_later_variant() {
		$this->assertSame( 'GPLv2 or later', $this->plugin_header( 'License:' ), 'the header licence is GPLv2 or later' );
		$this->assertStringContainsString(
			'either version 2 of the License, or',
			$this->plugin,
			'the copyright block must be the "or later" variant, matching the header'
		);
		$this->assertStringContainsString( '(at your option) any later version.', $this->plugin, 'the "or later" clause is part of the block' );
		$this->assertStringContainsString( 'Copyright 2026  Lester Chan  (email : lesterchan@gmail.com)', $this->plugin, 'two spaces after the year and around "email :"' );
	}

	public function test_the_plugin_defines_all_six_constants() {
		foreach ( array( 'VERSION', 'DB_VERSION', 'SLUG', 'MAIN_FILE', 'DIR', 'URL' ) as $suffix ) {
			$this->assertTrue( defined( 'WP_DOWNLOADMANAGER_' . $suffix ), 'WP_DOWNLOADMANAGER_' . $suffix . ' must be defined' );
		}

		$this->assertStringEndsWith( '/', WP_DOWNLOADMANAGER_DIR, 'the directory constant is trailing-slashed' );
		$this->assertStringEndsWith( '/', WP_DOWNLOADMANAGER_URL, 'the URL constant is trailing-slashed' );
		$this->assertSame( WP_DOWNLOADMANAGER_DIR . 'wp-downloadmanager.php', WP_DOWNLOADMANAGER_MAIN_FILE, 'the main-file constant points at the plugin file' );
	}

	public function test_the_plugin_ships_no_images_at_all() {
		$this->assertDirectoryDoesNotExist( $this->root . '/images', 'images/ was replaced by the inline SVG sprite' );

		$skip = array( 'node_modules', 'vendor', '.git' );

		foreach ( $this->directories_under( $this->root, $skip ) as $dir ) {
			foreach ( array( 'gif', 'png', 'jpg', 'jpeg', 'bmp', 'ico' ) as $extension ) {
				$this->assertSame(
					array(),
					(array) glob( $dir . '/*.' . $extension ),
					'no raster image may ship with the plugin, and ' . $dir . ' has one'
				);
			}
		}
	}

	public function test_nothing_loose_sits_at_the_plugin_root() {
		$allowed = array( 'wp-downloadmanager.php', 'index.php', 'uninstall.php' );

		foreach ( (array) glob( $this->root . '/*.php' ) as $path ) {
			$this->assertContains( basename( $path ), $allowed, basename( $path ) . ' must move into includes/' );
		}

		$this->assertSame( array(), (array) glob( $this->root . '/*.css' ), 'stylesheets belong in css/' );
		$this->assertSame( array(), (array) glob( $this->root . '/*.js' ), 'scripts belong in js/' );
	}

	public function test_includes_holds_only_prefixed_classes_and_template_tags() {
		foreach ( (array) glob( $this->root . '/includes/*.php' ) as $path ) {
			$name = basename( $path );

			if ( in_array( $name, array( 'index.php', 'template-tags.php' ), true ) ) {
				continue;
			}

			$this->assertStringStartsWith( 'class-wp-downloadmanager-', $name, $name . ' is not a class file' );

			preg_match_all( '/^\s*(?:final\s+|abstract\s+)?class\s+(\w+)/m', file_get_contents( $path ), $matches );

			foreach ( $matches[1] as $class ) {
				$this->assertStringStartsWith( 'WP_DownloadManager', $class, $class . ' needs the plugin class prefix' );
				$this->assertSame(
					'class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php',
					$name,
					$class . ' must live in the file named after it'
				);
			}
		}
	}

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
				$this->assertStringStartsWith( 'wp_downloadmanager_', $hook, $hook . ' in ' . $file . ' needs the plugin prefix' );
			}
		}
	}

	public function test_no_source_file_reads_an_unprefixed_option_row() {
		$core = array( 'home', 'blog_charset', 'date_format', 'time_format', 'gmt_offset' );

		foreach ( $this->plugin_php_files() as $file ) {
			preg_match_all( "/(?:get_option|update_option|add_option)\(\s*'([a-z0-9_]+)'/", $this->code( $file ), $matches );

			foreach ( $matches[1] as $option ) {
				if ( in_array( $option, $core, true ) ) {
					continue;
				}
				$this->assertStringStartsWith(
					'wp_downloadmanager_',
					$option,
					$option . ' in ' . $file . ' is an unprefixed option row; only the migration may name one, and only to delete it'
				);
			}
		}
	}

	/**
	 * Section 9: phpcs.xml is the shared template and nothing else, so a sniff
	 * that is wrong for one plugin cannot quietly be turned off for all of them.
	 * The residue is an inline suppression, allowed only inside includes/ and
	 * only when it carries a -- reason on the same line saying why the sniff is
	 * wrong. A bare suppression is the failure, not the suppression itself.
	 */
	public function test_every_inline_sniff_suppression_is_inside_includes_and_carries_a_reason() {
		foreach ( $this->plugin_php_files() as $file ) {
			$lines = explode( "\n", (string) file_get_contents( dirname( __DIR__ ) . '/' . $file ) );

			foreach ( $lines as $number => $line ) {
				if ( ! preg_match( '/phpcs:(disable|ignore)\b(.*)$/', $line, $matches ) ) {
					continue;
				}

				$where = $file . ':' . ( $number + 1 );

				$this->assertStringStartsWith(
					'includes/',
					$file,
					$where . ' silences a sniff outside includes/; fix the code instead'
				);
				$this->assertStringContainsString(
					'--',
					$matches[2],
					$where . ' silences a sniff with no reason on the same line'
				);
			}
		}
	}
}
