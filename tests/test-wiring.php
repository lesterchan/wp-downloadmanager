<?php
/**
 * Front-end wiring, assets, the editor buttons and the widget.
 *
 * @package WP-DownloadManager
 */

/**
 * Query vars, rewrites, enqueues and registrations.
 */
class WP_DownloadManager_Wiring_Test extends WP_DownloadManager_TestCase {

	public function test_the_download_query_vars_are_registered() {
		$vars = apply_filters( 'query_vars', array() );

		$this->assertContains( 'dl_id', $vars );
		$this->assertContains( 'dl_name', $vars );
	}

	public function test_the_download_rewrite_rules_are_prepended() {
		global $wp_rewrite;

		$wp_rewrite->rules = array( 'existing/?$' => 'index.php?p=1' );

		WP_DownloadManager::rewrite_rules( $wp_rewrite );

		$rules = array_keys( $wp_rewrite->rules );

		$this->assertSame( 'download/([0-9]{1,})/?$', $rules[0], 'the download rules have to win over a catch-all page rule' );
		$this->assertContains( 'download/(.*)$', $rules );
		$this->assertContains( 'existing/?$', $rules, 'and the existing rules survive' );
	}

	public function test_the_front_end_stylesheet_is_enqueued_from_the_plugin() {
		wp_dequeue_style( 'wp-downloadmanager' );
		wp_deregister_style( 'wp-downloadmanager' );

		WP_DownloadManager::enqueue_styles();

		$this->assertTrue( wp_style_is( 'wp-downloadmanager', 'enqueued' ) );
		$this->assertSame( WP_DOWNLOADMANAGER_URL . 'css/wp-downloadmanager.css', wp_styles()->registered['wp-downloadmanager']->src );
		$this->assertSame( WP_DOWNLOADMANAGER_VERSION, wp_styles()->registered['wp-downloadmanager']->ver );
	}

	public function test_a_theme_copy_of_the_stylesheet_wins() {
		$theme_css = get_stylesheet_directory() . '/wp-downloadmanager.css';
		$this->filesystem()->put_contents( $theme_css, '/* theme copy */' );

		wp_dequeue_style( 'wp-downloadmanager' );
		wp_deregister_style( 'wp-downloadmanager' );

		WP_DownloadManager::enqueue_styles();

		$this->assertSame( get_stylesheet_directory_uri() . '/wp-downloadmanager.css', wp_styles()->registered['wp-downloadmanager']->src );

		wp_delete_file( $theme_css );
	}

	public function test_the_admin_script_loads_on_the_plugins_screens() {
		foreach ( WP_DownloadManager_Admin::screens() as $slug ) {
			wp_dequeue_script( 'wp-downloadmanager-admin' );
			wp_deregister_script( 'wp-downloadmanager-admin' );

			WP_DownloadManager_Admin::enqueue_assets( 'downloads_page_' . $slug );

			$this->assertTrue( wp_script_is( 'wp-downloadmanager-admin', 'enqueued' ), $slug . ' should load the admin script' );
		}
	}

	public function test_the_admin_script_loads_on_the_top_level_screen_too() {
		wp_dequeue_script( 'wp-downloadmanager-admin' );
		wp_deregister_script( 'wp-downloadmanager-admin' );

		WP_DownloadManager_Admin::enqueue_assets( 'toplevel_page_' . WP_DownloadManager_Admin::PAGE );

		$this->assertTrue( wp_script_is( 'wp-downloadmanager-admin', 'enqueued' ) );
	}

	public function test_the_admin_script_loads_nowhere_else() {
		foreach ( array( 'edit.php', 'index.php', 'settings_page_something-else', 'options-general.php' ) as $hook ) {
			wp_dequeue_script( 'wp-downloadmanager-admin' );
			wp_deregister_script( 'wp-downloadmanager-admin' );

			WP_DownloadManager_Admin::enqueue_assets( $hook );

			$this->assertFalse( wp_script_is( 'wp-downloadmanager-admin', 'enqueued' ), $hook . ' is not this plugin\'s screen' );
		}
	}

	public function test_the_admin_script_carries_the_stock_templates() {
		wp_dequeue_script( 'wp-downloadmanager-admin' );
		wp_deregister_script( 'wp-downloadmanager-admin' );

		WP_DownloadManager_Admin::enqueue_assets( 'toplevel_page_' . WP_DownloadManager_Admin::PAGE );

		$data = (string) wp_scripts()->get_data( 'wp-downloadmanager-admin', 'data' );

		$this->assertStringContainsString( 'wpDownloadManagerL10n', $data );
		foreach ( array_keys( WP_DownloadManager_Template::for_script() ) as $key ) {
			$this->assertStringContainsString( '"' . $key . '"', $data, $key . ' should be localised for its reset button' );
		}
	}

	public function test_there_is_exactly_one_localised_global() {
		wp_dequeue_script( 'wp-downloadmanager-admin' );
		wp_deregister_script( 'wp-downloadmanager-admin' );
		wp_dequeue_script( 'wp-downloadmanager-quicktag' );
		wp_deregister_script( 'wp-downloadmanager-quicktag' );

		WP_DownloadManager_Admin::enqueue_assets( 'toplevel_page_' . WP_DownloadManager_Admin::PAGE );
		WP_DownloadManager_Admin::quicktag();

		foreach ( array( 'wp-downloadmanager-admin', 'wp-downloadmanager-quicktag' ) as $handle ) {
			$data = (string) wp_scripts()->get_data( $handle, 'data' );

			$this->assertStringContainsString( 'wpDownloadManagerL10n', $data, $handle . ' must use the one global named after the class prefix' );
		}
	}

	public function test_the_localised_payload_carries_both_halves() {
		$data = WP_DownloadManager_Admin::script_data();

		$this->assertArrayHasKey( 'templates', $data );
		$this->assertArrayHasKey( 'quicktag', $data );
		$this->assertArrayHasKey( 'prompt', $data['quicktag'] );
	}

	public function test_no_stylesheet_is_enqueued_on_any_admin_screen() {
		foreach ( WP_DownloadManager_Admin::screens() as $slug ) {
			WP_DownloadManager_Admin::enqueue_assets( 'downloads_page_' . $slug );
		}

		$ours = array_filter(
			wp_styles()->queue,
			static fn( $handle ) => 0 === strpos( $handle, 'wp-downloadmanager' )
		);

		$this->assertSame( array(), array_values( $ours ), 'the admin stylesheet had been a zero-byte file since 2010' );
	}

	public function test_the_plugin_ships_no_empty_assets() {
		$assets = array_merge(
			(array) glob( WP_DOWNLOADMANAGER_DIR . 'css/*.css' ),
			(array) glob( WP_DOWNLOADMANAGER_DIR . 'js/*.js' )
		);

		$this->assertNotEmpty( $assets );

		foreach ( $assets as $asset ) {
			$this->assertGreaterThan( 0, filesize( $asset ), basename( $asset ) . ' is empty and should not ship' );
		}
	}

	public function test_the_quicktag_script_is_registered_rather_than_printed_inline() {
		wp_dequeue_script( 'wp-downloadmanager-quicktag' );
		wp_deregister_script( 'wp-downloadmanager-quicktag' );

		WP_DownloadManager_Admin::quicktag();

		$this->assertTrue( wp_script_is( 'wp-downloadmanager-quicktag', 'enqueued' ) );

		$registered = wp_scripts()->registered['wp-downloadmanager-quicktag'];

		$this->assertContains( 'quicktags', $registered->deps );
		$this->assertNotContains( 'jquery', $registered->deps );
	}

	public function test_the_editor_button_registers_for_an_editor() {
		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );
		update_user_option( $user_id, 'rich_editing', 'true' );

		remove_all_filters( 'mce_external_plugins' );
		remove_all_filters( 'mce_buttons' );

		WP_DownloadManager_Admin::editor_buttons();

		$this->assertNotFalse( has_filter( 'mce_external_plugins' ) );
		$this->assertNotFalse( has_filter( 'mce_buttons' ) );
	}

	public function test_the_editor_button_points_at_the_unminified_script() {
		$plugins = WP_DownloadManager_Admin::mce_plugin( array() );

		$this->assertStringContainsString( 'tinymce/plugins/downloadmanager/plugin.js', $plugins['downloadmanager'] );
		$this->assertStringNotContainsString( 'plugin.min.js', $plugins['downloadmanager'], 'a hand-minified twin only drifts out of sync' );
	}

	public function test_the_editor_button_is_added_to_the_toolbar() {
		$this->assertContains( 'downloadmanager', WP_DownloadManager_Admin::mce_button( array() ) );
	}

	public function test_the_editor_button_is_not_registered_for_a_subscriber() {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );
		update_user_option( $user_id, 'rich_editing', 'true' );

		remove_all_filters( 'mce_external_plugins' );

		WP_DownloadManager_Admin::editor_buttons();

		$this->assertFalse( has_filter( 'mce_external_plugins' ) );
	}

	public function test_the_editor_strings_are_translated_not_escaped_for_javascript() {
		$strings = WP_DownloadManager_Admin::mce_translation( array() );

		$this->assertArrayHasKey( 'Insert File Download', $strings );
		$this->assertSame( 'Insert File Download', $strings['Insert File Download'] );
		$this->assertStringNotContainsString( '\\', $strings['Enter File ID (Separate Multiple IDs By A Comma)'], 'esc_js() double escapes once TinyMCE inserts them into the DOM' );
	}

	public function test_the_feed_link_is_printed_on_the_downloads_page() {
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
	}

	public function test_the_feed_link_uses_the_query_form_without_nice_permalinks() {
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

	public function test_no_feed_link_is_printed_anywhere_else() {
		$this->go_to( home_url( '/' ) );

		ob_start();
		WP_DownloadManager::feed_link();
		$html = ob_get_clean();

		$this->assertSame( '', $html );
	}

	public function test_activation_creates_the_table_and_grants_the_capability() {
		global $wpdb;

		WP_DownloadManager_Install::activate();
		WP_DownloadManager_Install::activate();

		$this->assertSame( $this->table(), $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $this->table() ) ), 'activation is idempotent' );
		$this->assertTrue( get_role( 'administrator' )->has_cap( 'manage_downloads' ) );
	}

	public function test_the_downloads_table_is_registered_with_wpdb() {
		global $wpdb;

		$this->assertContains( 'downloads', $wpdb->tables, 'registering the name is what makes it survive switch_to_blog()' );
		$this->assertSame( $wpdb->prefix . 'downloads', $wpdb->downloads );
	}

	public function test_the_widget_is_registered_with_core() {
		do_action( 'widgets_init' );

		$registered = array_map( 'get_class', $GLOBALS['wp_widget_factory']->widgets );

		$this->assertContains( 'WP_DownloadManager_Widget', array_values( $registered ), 'the widget has to reach the widget factory to appear in the block editor' );
	}

	public function test_the_widget_supports_selective_refresh() {
		$widget = new WP_DownloadManager_Widget();

		$this->assertTrue( $widget->widget_options['customize_selective_refresh'] );
	}

	public function test_the_widget_renders_its_chosen_list() {
		$widget = new WP_DownloadManager_Widget();

		ob_start();
		$widget->widget(
			array(
				'before_widget' => '<aside>',
				'after_widget'  => '</aside>',
				'before_title'  => '<h2>',
				'after_title'   => '</h2>',
			),
			array(
				'title' => 'Downloads',
				'type'  => 'most_downloaded',
				'limit' => 2,
				'chars' => 0,
				'link'  => 0,
			)
		);
		$html = ob_get_clean();

		$this->assertStringContainsString( '<aside>', $html );
		$this->assertStringContainsString( '<h2>Downloads</h2>', $html );
		$this->assertStringContainsString( 'The Manual', $html );
	}

	public function test_the_widget_scopes_its_list_with_the_plugin_class() {
		$widget = new WP_DownloadManager_Widget();

		ob_start();
		$widget->widget(
			array(
				'before_widget' => '',
				'after_widget'  => '',
				'before_title'  => '',
				'after_title'   => '',
			),
			array( 'type' => 'recent_downloads' )
		);
		$html = ob_get_clean();

		$this->assertStringContainsString( '<ul class="wp-downloadmanager">', $html, 'the stylesheet needs one scope' );
	}

	public function test_the_widget_can_link_to_the_downloads_page() {
		$widget = new WP_DownloadManager_Widget();

		ob_start();
		$widget->widget(
			array(
				'before_widget' => '',
				'after_widget'  => '',
				'before_title'  => '',
				'after_title'   => '',
			),
			array(
				'type' => 'recent_downloads',
				'link' => 1,
			)
		);
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Downloads Page', $html );
	}

	public function test_the_widget_saves_its_form() {
		$widget = new WP_DownloadManager_Widget();

		$saved = $widget->update(
			array(
				'title'   => '<b>Files</b>',
				'type'    => 'downloads_category',
				'limit'   => '5',
				'chars'   => '30',
				'cat_ids' => '1,2',
				'link'    => '1',
			),
			array()
		);

		$this->assertSame( 'Files', $saved['title'], 'the title is plain text' );
		$this->assertSame( 'downloads_category', $saved['type'] );
		$this->assertSame( 5, $saved['limit'] );
		$this->assertSame( 30, $saved['chars'] );
		$this->assertSame( '1,2', $saved['cat_ids'] );
		$this->assertSame( 1, $saved['link'] );
	}

	public function test_the_widget_keeps_edits_made_without_the_legacy_submit_marker() {
		$widget = new WP_DownloadManager_Widget();

		$saved = $widget->update( array( 'title' => 'From the customizer' ), array( 'title' => 'Old' ) );

		$this->assertSame( 'From the customizer', $saved['title'], 'the old guard discarded every edit made in the block widget editor or the customizer' );
	}

	public function test_the_widget_form_marks_the_saved_link_choice_as_selected() {
		$widget = new WP_DownloadManager_Widget();

		ob_start();
		$widget->form(
			array(
				'type' => 'recent_downloads',
				'link' => 1,
			)
		);
		$html = ob_get_clean();

		$this->assertMatchesRegularExpression(
			'/<option value="1"\s*selected/',
			$html,
			'these used to be compared against $type rather than $link, so the saved value never showed as selected'
		);
	}

	public function test_the_widget_category_ids_cannot_rewrite_the_query() {
		$widget = new WP_DownloadManager_Widget();

		ob_start();
		$widget->widget(
			array(
				'before_widget' => '',
				'after_widget'  => '',
				'before_title'  => '',
				'after_title'   => '',
			),
			array(
				'type'    => 'downloads_category',
				'cat_ids' => '1) OR (1=1',
			)
		);
		$html = ob_get_clean();

		$this->assertStringNotContainsString( 'Hidden File', $html, 'anyone able to edit a widget could once rewrite the WHERE clause, including the guard that hides files' );
	}
}
