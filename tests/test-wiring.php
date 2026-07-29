<?php
/**
 * Menu registration, asset loading and the editor buttons.
 *
 * The asset loader and the menu used to derive their page lists separately,
 * and had already drifted: the loader still listed a download-uninstall.php
 * that has not existed for years, so the admin stylesheet was being matched
 * against a page that could never load. Both now come from
 * WP_DownloadManager_Admin::pages(), and these tests are what keeps them together.
 *
 * @package WP-DownloadManager
 */

/**
 * WP_DownloadManager and WP_DownloadManager_Admin wiring.
 */
class Test_Wiring extends WP_DownloadManager_TestCase {

	/**
	 * The page list is derived from the plugin directory name.
	 *
	 * Every path here used to be built from a literal "wp-downloadmanager", so
	 * installing under any other directory name broke the menu, the stylesheets
	 * and every extension icon.
	 */
	public function test_pages_are_derived_from_the_slug() {
		$pages = WP_DownloadManager_Admin::pages();

		$this->assertSame( WP_DOWNLOADMANAGER_SLUG . '/includes/screen-manage.php', $pages['manager'] );
		$this->assertSame( WP_DOWNLOADMANAGER_SLUG . '/includes/screen-add.php', $pages['add'] );
		$this->assertSame( 'wp-downloadmanager-options', $pages['options'] );
		$this->assertSame( 'wp-downloadmanager-templates', $pages['templates'] );
	}

	/**
	 * The page list names only files that exist.
	 *
	 * This is the assertion that would have caught download-uninstall.php.
	 */
	public function test_every_file_backed_page_exists() {
		$pages = WP_DownloadManager_Admin::pages();

		foreach ( array( 'manager', 'add' ) as $key ) {
			$file = WP_DOWNLOADMANAGER_DIR . basename( $pages[ $key ] );
			$this->assertFileExists( $file, $pages[ $key ] . ' is listed but missing' );
		}
	}

	/**
	 * The constants point at this plugin.
	 */
	public function test_path_constants() {
		$this->assertStringEndsWith( '/', WP_DOWNLOADMANAGER_DIR );
		$this->assertStringEndsWith( '/', WP_DOWNLOADMANAGER_URL );
		$this->assertFileExists( WP_DOWNLOADMANAGER_DIR . 'wp-downloadmanager.php' );
		$this->assertSame( basename( WP_DOWNLOADMANAGER_DIR ), WP_DOWNLOADMANAGER_SLUG );
		$this->assertSame( WP_DOWNLOADMANAGER_VERSION, '2.0.0' );
	}

	/**
	 * No source file hardcodes the plugin directory name.
	 *
	 * The literal appears legitimately as the text domain and in option and
	 * handle names, so this looks only for it being used as a path segment.
	 */
	public function test_no_hardcoded_directory_paths() {
		// Two globs rather than GLOB_BRACE, which is not available on every
		// build - the musl-based PHP images do not have it.
		$files = array_merge(
			(array) glob( WP_DOWNLOADMANAGER_DIR . '*.php' ),
			(array) glob( WP_DOWNLOADMANAGER_DIR . 'includes/*.php' )
		);

		$this->assertNotEmpty( $files );

		foreach ( $files as $file ) {
			$source = file_get_contents( $file );

			$this->assertDoesNotMatchRegularExpression(
				'#(plugins_url|WP_PLUGIN_DIR\s*\.\s*)[^;]*[\'"]/?wp-downloadmanager/#',
				$source,
				basename( $file ) . ' builds a path from the literal slug'
			);
		}
	}

	/**
	 * The menu registers under the plugin capability.
	 */
	public function test_menu_registers() {
		global $menu, $submenu;

		// Cleared so the assertions below see only what menu() registered. These
		// are core's globals, which is the whole point: the test is checking what
		// the plugin puts into them.
		$menu    = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		$submenu = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'dashboard' );

		WP_DownloadManager_Admin::menu();

		$pages = WP_DownloadManager_Admin::pages();
		$this->assertArrayHasKey( $pages['manager'], $submenu );

		$slugs = wp_list_pluck( $submenu[ $pages['manager'] ], 2 );
		$this->assertContains( $pages['manager'], $slugs );
		$this->assertContains( $pages['add'], $slugs );
		$this->assertContains( $pages['options'], $slugs );
		$this->assertContains( $pages['templates'], $slugs );

		// Every entry is behind manage_downloads.
		foreach ( $submenu[ $pages['manager'] ] as $entry ) {
			$this->assertSame( 'manage_downloads', $entry[1] );
		}
	}

	/**
	 * The templates script loads on its own screen and nowhere else.
	 *
	 * There used to be an admin stylesheet enqueued on all four screens as well.
	 * It had been a zero-byte file since the plugin's first commit, so it was
	 * dropped in 2.0.0 rather than shipped as a request that delivers nothing.
	 *
	 * @dataProvider admin_asset_provider
	 *
	 * @param string $hook_suffix The hook suffix WordPress hands back.
	 * @param bool   $expected    Whether the script should load.
	 */
	public function test_admin_script_scoping( $hook_suffix, $expected ) {
		wp_dequeue_script( 'wp-downloadmanager-admin' );
		wp_deregister_script( 'wp-downloadmanager-admin' );

		WP_DownloadManager_Admin::enqueue_assets( $hook_suffix );

		$this->assertSame(
			$expected,
			wp_script_is( 'wp-downloadmanager-admin', 'enqueued' ),
			$hook_suffix
		);
	}

	/**
	 * Hook suffixes to test the asset loader against.
	 *
	 * WordPress returns "downloads_page_<slug>" for the pages registered with a
	 * callback and the bare file path for the legacy ones, so both shapes are
	 * covered - the options screen in particular must not pick up the templates
	 * screen's script just because their slugs share a prefix.
	 *
	 * @return array
	 */
	public function admin_asset_provider() {
		$pages = WP_DownloadManager_Admin::pages();

		return array(
			'templates callback' => array( 'downloads_page_' . $pages['templates'], true ),
			'options callback'   => array( 'downloads_page_' . $pages['options'], false ),
			'manage downloads'   => array( $pages['manager'], false ),
			'add file'           => array( $pages['add'], false ),
			'unrelated screen'   => array( 'edit.php', false ),
			'dashboard'          => array( 'index.php', false ),
			'another plugin'     => array( 'settings_page_something-else', false ),
		);
	}

	/**
	 * No stylesheet is enqueued on any admin screen.
	 *
	 * A guard against the empty one coming back.
	 */
	public function test_no_admin_stylesheet_is_enqueued() {
		foreach ( WP_DownloadManager_Admin::pages() as $slug ) {
			WP_DownloadManager_Admin::enqueue_assets( 'downloads_page_' . $slug );
			WP_DownloadManager_Admin::enqueue_assets( $slug );
		}

		$ours = array_filter(
			wp_styles()->queue,
			static function ( $handle ) {
				return 0 === strpos( $handle, 'wp-downloadmanager' );
			}
		);

		$this->assertSame( array(), array_values( $ours ), 'the admin stylesheet was removed in 2.0.0' );
	}

	/**
	 * The plugin ships no zero-byte assets.
	 *
	 * The admin stylesheet was one for fifteen years, enqueued the whole time.
	 */
	public function test_no_empty_assets_are_shipped() {
		$assets = array_merge(
			(array) glob( WP_DOWNLOADMANAGER_DIR . '*.css' ),
			(array) glob( WP_DOWNLOADMANAGER_DIR . '*.js' )
		);

		$this->assertNotEmpty( $assets );

		foreach ( $assets as $asset ) {
			$this->assertGreaterThan(
				0,
				filesize( $asset ),
				basename( $asset ) . ' is empty and should not ship'
			);
		}
	}

	/**
	 * The templates screen loads its script and the stock markup with it.
	 */
	public function test_templates_screen_loads_its_script() {
		wp_dequeue_script( 'wp-downloadmanager-admin' );
		wp_deregister_script( 'wp-downloadmanager-admin' );

		$pages = WP_DownloadManager_Admin::pages();
		WP_DownloadManager_Admin::enqueue_assets( 'downloads_page_' . $pages['templates'] );

		$this->assertTrue( wp_script_is( 'wp-downloadmanager-admin', 'enqueued' ) );

		$data = wp_scripts()->get_data( 'wp-downloadmanager-admin', 'data' );
		$this->assertStringContainsString( 'wpDownloadManagerL10n', (string) $data );
		// The reset buttons read every template from here.
		foreach ( WP_DownloadManager_Template::for_script() as $key => $unused ) {
			$this->assertStringContainsString( '"' . $key . '"', (string) $data, $key . ' should be localised' );
		}
	}

	/**
	 * The options screen does not load the templates script.
	 */
	public function test_options_screen_does_not_load_the_templates_script() {
		wp_dequeue_script( 'wp-downloadmanager-admin' );
		wp_deregister_script( 'wp-downloadmanager-admin' );

		$pages = WP_DownloadManager_Admin::pages();
		WP_DownloadManager_Admin::enqueue_assets( 'downloads_page_' . $pages['options'] );

		$this->assertFalse( wp_script_is( 'wp-downloadmanager-admin', 'enqueued' ) );
	}

	/**
	 * The quicktag script is registered rather than printed inline.
	 */
	public function test_quicktag_script() {
		wp_dequeue_script( 'wp-downloadmanager-quicktag' );
		wp_deregister_script( 'wp-downloadmanager-quicktag' );

		WP_DownloadManager_Admin::quicktag();

		$this->assertTrue( wp_script_is( 'wp-downloadmanager-quicktag', 'enqueued' ) );

		$registered = wp_scripts()->registered['wp-downloadmanager-quicktag'];
		$this->assertContains( 'quicktags', $registered->deps );
		$this->assertNotContains( 'jquery', $registered->deps );

		$data = wp_scripts()->get_data( 'wp-downloadmanager-quicktag', 'data' );
		$this->assertStringContainsString( 'wpDownloadManagerL10n', (string) $data );
	}

	/**
	 * No plugin script depends on jQuery.
	 */
	public function test_no_script_depends_on_jquery() {
		WP_DownloadManager_Admin::quicktag();
		WP_DownloadManager_Admin::enqueue_assets( 'downloads_page_' . WP_DownloadManager_Admin::pages()['templates'] );

		foreach ( wp_scripts()->registered as $handle => $script ) {
			if ( 0 !== strpos( $handle, 'wp-downloadmanager' ) ) {
				continue;
			}
			$this->assertNotContains( 'jquery', $script->deps, $handle . ' should not need jQuery' );
		}
	}

	/**
	 * The TinyMCE button registers for an editor user.
	 */
	public function test_tinymce_button_registers() {
		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );
		update_user_option( $user_id, 'rich_editing', 'true' );

		remove_all_filters( 'mce_external_plugins' );
		remove_all_filters( 'mce_buttons' );

		WP_DownloadManager_Admin::editor_buttons();

		$this->assertNotFalse( has_filter( 'mce_external_plugins' ) );
		$this->assertNotFalse( has_filter( 'mce_buttons' ) );

		$plugins = WP_DownloadManager_Admin::mce_plugin( array() );
		$this->assertStringContainsString( 'tinymce/plugins/downloadmanager/plugin.js', $plugins['downloadmanager'] );
		// The hand-minified twin is gone, so nothing may point at it.
		$this->assertStringNotContainsString( 'plugin.min.js', $plugins['downloadmanager'] );

		$this->assertContains( 'downloadmanager', WP_DownloadManager_Admin::mce_button( array() ) );
	}

	/**
	 * A user who cannot edit content gets no button.
	 */
	public function test_tinymce_button_is_not_registered_for_subscribers() {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );
		update_user_option( $user_id, 'rich_editing', 'true' );

		remove_all_filters( 'mce_external_plugins' );

		WP_DownloadManager_Admin::editor_buttons();

		$this->assertFalse( has_filter( 'mce_external_plugins' ) );
	}

	/**
	 * The TinyMCE strings are translated, not escaped for JavaScript.
	 *
	 * They used to go through esc_js(), which double escapes once TinyMCE
	 * inserts them into the DOM.
	 */
	public function test_tinymce_translations() {
		$strings = WP_DownloadManager_Admin::mce_translation( array() );

		$this->assertArrayHasKey( 'Insert File Download', $strings );
		$this->assertSame( 'Insert File Download', $strings['Insert File Download'] );
		$this->assertStringNotContainsString( '\\', $strings['Enter File ID (Separate Multiple IDs By A Comma)'] );
	}

	/**
	 * The front-end stylesheet is enqueued from the plugin by default.
	 */
	public function test_front_end_stylesheet() {
		wp_dequeue_style( 'wp-downloadmanager' );
		wp_deregister_style( 'wp-downloadmanager' );

		WP_DownloadManager::enqueue_styles();

		$this->assertTrue( wp_style_is( 'wp-downloadmanager', 'enqueued' ) );
		$this->assertSame(
			WP_DOWNLOADMANAGER_URL . 'css/wp-downloadmanager.css',
			wp_styles()->registered['wp-downloadmanager']->src
		);
		$this->assertSame( WP_DOWNLOADMANAGER_VERSION, wp_styles()->registered['wp-downloadmanager']->ver );
	}

	/**
	 * A theme copy of the stylesheet wins.
	 */
	public function test_theme_stylesheet_overrides_the_plugin_one() {
		$theme_css = get_stylesheet_directory() . '/wp-downloadmanager.css';
		file_put_contents( $theme_css, '/* theme copy */' ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		wp_dequeue_style( 'wp-downloadmanager' );
		wp_deregister_style( 'wp-downloadmanager' );

		WP_DownloadManager::enqueue_styles();

		$this->assertSame(
			get_stylesheet_directory_uri() . '/wp-downloadmanager.css',
			wp_styles()->registered['wp-downloadmanager']->src
		);

		wp_delete_file( $theme_css );
	}

	/**
	 * The feed link is printed on the downloads page only.
	 */
	public function test_feed_link_on_the_downloads_page() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Downloads',
				'post_name'  => 'downloads',
			)
		);
		$this->go_to( get_permalink( $page_id ) );

		WP_DownloadManager_Options::set( 'page_url', get_permalink( $page_id ) );
		$_SERVER['REQUEST_URI'] = wp_parse_url( get_permalink( $page_id ), PHP_URL_PATH );

		ob_start();
		WP_DownloadManager::feed_link();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'application/rss+xml', $html );
		$this->assertStringContainsString( '/download/rss/', $html );
		$this->assertStringContainsString( 'Downloads RSS Feed', $html );
	}

	/**
	 * With plain permalinks the feed link uses the query form.
	 */
	public function test_feed_link_without_nice_permalinks() {
		$page_id = self::factory()->post->create(
			array(
				'post_type' => 'page',
				'post_name' => 'downloads',
			)
		);
		$this->go_to( get_permalink( $page_id ) );

		WP_DownloadManager_Options::set( 'page_url', get_permalink( $page_id ) );
		WP_DownloadManager_Options::set( 'nice_permalink', 0 );
		$_SERVER['REQUEST_URI'] = wp_parse_url( get_permalink( $page_id ), PHP_URL_PATH );

		ob_start();
		WP_DownloadManager::feed_link();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'dl_name=rss', $html );
	}

	/**
	 * No feed link anywhere else.
	 */
	public function test_no_feed_link_on_other_pages() {
		$this->go_to( home_url( '/' ) );

		ob_start();
		WP_DownloadManager::feed_link();
		$html = ob_get_clean();

		$this->assertSame( '', $html );
	}

	/**
	 * The file listing helpers render the downloads directory.
	 */
	public function test_print_files_and_folders() {
		WP_DownloadManager_Options::set( 'path.dir', WP_CONTENT_DIR . '/dm-wiring-files' );
		$dir = WP_DownloadManager_Options::get( 'path.dir' );

		$this->make_download_file( 'top.txt' );
		$this->make_download_file( 'sub/deep.txt' );

		ob_start();
		WP_DownloadManager_Admin::print_files( $dir, $dir, '/top.txt' );
		$files = ob_get_clean();

		$this->assertStringContainsString( '<option value="/top.txt" selected', $files );
		$this->assertStringContainsString( '/sub/deep.txt', $files );

		ob_start();
		WP_DownloadManager_Admin::print_folders( $dir, $dir );
		$folders = ob_get_clean();

		$this->assertStringContainsString( '<option value="/">/</option>', $folders );
		$this->assertStringContainsString( 'value="/sub"', $folders );

		$this->remove_download_files();
	}

	/**
	 * A missing downloads directory is not a fatal.
	 */
	public function test_print_files_with_no_directory() {
		WP_DownloadManager_Options::set( 'path.dir', WP_CONTENT_DIR . '/dm-does-not-exist' );
		$dir = WP_DownloadManager_Options::get( 'path.dir' );

		ob_start();
		WP_DownloadManager_Admin::print_files( $dir, $dir );
		WP_DownloadManager_Admin::print_folders( $dir, $dir );
		$html = ob_get_clean();

		$this->assertStringNotContainsString( 'Warning', $html );
	}

	/**
	 * The timestamp selects cover every part of a date.
	 */
	public function test_file_timestamp_selects() {
		ob_start();
		WP_DownloadManager_Admin::file_timestamp( gmmktime( 14, 25, 36, 6, 15, 2020 ) );
		$html = ob_get_clean();

		foreach ( array( 'day', 'month', 'year', 'hour', 'minute', 'second' ) as $part ) {
			$this->assertStringContainsString( 'id="file_timestamp_' . $part . '"', $html );
		}

		// The stored value is the selected option in each.
		$this->assertStringContainsString( '<option value="15" selected', $html );
		$this->assertStringContainsString( '<option value="6" selected', $html );
		$this->assertStringContainsString( '<option value="2020" selected', $html );
		$this->assertStringContainsString( '<option value="14" selected', $html );
		$this->assertStringContainsString( '<option value="25" selected', $html );
		$this->assertStringContainsString( '<option value="36" selected', $html );

		// Months read as names, from the site locale.
		$this->assertStringContainsString( 'June', $html );
	}

	/**
	 * Activation grants the capability and creates the table.
	 */
	public function test_activation_is_idempotent() {
		WP_DownloadManager_Install::activate();
		WP_DownloadManager_Install::activate();

		global $wpdb;
		$this->assertSame(
			$wpdb->downloads,
			$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->downloads ) )
		);
		$this->assertTrue( get_role( 'administrator' )->has_cap( 'manage_downloads' ) );
	}
}
