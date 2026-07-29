<?php
/**
 * The Downloads menu and the three screens under it.
 *
 * @package WP-DownloadManager
 */

/**
 * Menu registration, screen routing and rendering.
 */
class WP_DownloadManager_Admin_Test extends WP_DownloadManager_TestCase {

	/**
	 * Register the settings before each test.
	 *
	 * Core renders a settings screen out of a global registry, so the page has
	 * no fields until register() has run. In production that happens on
	 * admin_init, which always fires before an admin page renders; here it has
	 * to be explicit.
	 */
	public function set_up() {
		parent::set_up();

		WP_DownloadManager_Settings::register();
		WP_DownloadManager_Admin::load_list_table();
		$this->on_admin_screen();
	}

	public function test_there_are_exactly_three_screens() {
		$screens = WP_DownloadManager_Admin::screens();

		$this->assertSame( array( 'downloads', 'add', 'settings' ), array_keys( $screens ), 'a data screen, an add screen and one settings page' );
	}

	public function test_the_top_level_slug_is_the_plugin_slug() {
		$this->assertSame( 'wp-downloadmanager', WP_DownloadManager_Admin::PAGE );
		$this->assertSame( 'wp-downloadmanager', WP_DownloadManager_Admin::screens()['downloads'] );
	}

	public function test_no_screen_slug_is_a_file_path() {
		foreach ( WP_DownloadManager_Admin::screens() as $slug ) {
			$this->assertStringNotContainsString( '/', $slug, 'the legacy "plugin file as menu slug" form is gone' );
			$this->assertStringNotContainsString( '.php', $slug );
		}
	}

	public function test_the_menu_registers_one_top_level_entry() {
		global $menu, $submenu;

		$menu    = array();
		$submenu = array();
		$this->become_download_admin();
		set_current_screen( 'dashboard' );

		WP_DownloadManager_Admin::menu();

		$slugs = wp_list_pluck( $menu, 2 );

		$this->assertContains( WP_DownloadManager_Admin::PAGE, $slugs, 'a plugin with data screens gets one top-level menu' );
	}

	public function test_the_data_screen_is_first_and_settings_is_last() {
		global $menu, $submenu;

		$menu    = array();
		$submenu = array();
		$this->become_download_admin();
		set_current_screen( 'dashboard' );

		WP_DownloadManager_Admin::menu();

		$slugs = wp_list_pluck( $submenu[ WP_DownloadManager_Admin::PAGE ], 2 );

		$this->assertSame(
			array( 'wp-downloadmanager', 'wp-downloadmanager-add', 'wp-downloadmanager-settings' ),
			$slugs,
			'section 4.1: data screen first, Settings last'
		);
	}

	public function test_settings_never_span_more_than_one_menu_entry() {
		global $menu, $submenu;

		$menu    = array();
		$submenu = array();
		$this->become_download_admin();
		set_current_screen( 'dashboard' );

		WP_DownloadManager_Admin::menu();

		$settings = array_filter(
			wp_list_pluck( $submenu[ WP_DownloadManager_Admin::PAGE ], 2 ),
			fn( $slug ) => str_contains( $slug, 'settings' ) || str_contains( $slug, 'options' ) || str_contains( $slug, 'templates' )
		);

		$this->assertCount( 1, $settings, 'Download Options and Download Templates are tabs now, not menu entries' );
	}

	public function test_the_data_screens_sit_behind_the_plugin_capability() {
		global $menu, $submenu;

		$menu    = array();
		$submenu = array();
		$this->become_download_admin();
		set_current_screen( 'dashboard' );

		WP_DownloadManager_Admin::menu();

		$entries = $submenu[ WP_DownloadManager_Admin::PAGE ];

		$this->assertSame( 'manage_downloads', $entries[0][1], 'the downloads library keeps its own capability' );
		$this->assertSame( 'manage_downloads', $entries[1][1] );
	}

	public function test_the_settings_screen_sits_behind_manage_options() {
		global $menu, $submenu;

		$menu    = array();
		$submenu = array();
		$this->become_download_admin();
		set_current_screen( 'dashboard' );

		WP_DownloadManager_Admin::menu();

		$entries = $submenu[ WP_DownloadManager_Admin::PAGE ];

		$this->assertSame( 'manage_options', $entries[2][1], 'section 2.7: settings screens require manage_options' );
	}

	public function test_the_capability_goes_through_one_filter() {
		add_filter( 'wp_downloadmanager_capability', fn( $cap, $context ) => 'settings' === $context ? 'edit_theme_options' : 'edit_posts', 10, 2 );

		$this->assertSame( 'edit_posts', WP_DownloadManager_Admin::capability() );
		$this->assertSame( 'edit_theme_options', WP_DownloadManager_Admin::capability( 'settings' ) );
	}

	public function test_a_screen_url_points_at_the_menu_slug() {
		$this->assertStringContainsString( 'page=wp-downloadmanager', WP_DownloadManager_Admin::screen_url() );
		$this->assertStringContainsString( 'page=wp-downloadmanager-add', WP_DownloadManager_Admin::screen_url( 'add' ) );
		$this->assertStringContainsString( 'page=wp-downloadmanager-settings', WP_DownloadManager_Admin::screen_url( 'settings' ) );
	}

	public function test_an_edit_or_delete_url_carries_a_nonce() {
		$this->assertStringContainsString( '_wpnonce=', WP_DownloadManager_Admin::screen_url( 'edit', 3 ) );
		$this->assertStringContainsString( '_wpnonce=', WP_DownloadManager_Admin::screen_url( 'delete', 3 ) );
		$this->assertStringContainsString( 'id=3', WP_DownloadManager_Admin::screen_url( 'edit', 3 ) );
	}

	public function test_a_listing_url_carries_no_nonce() {
		$this->assertStringNotContainsString( '_wpnonce', WP_DownloadManager_Admin::screen_url(), 'a nonce on a bookmarkable listing URL breaks bookmarking for no gain' );
	}

	public function test_the_downloads_screen_renders_the_list() {
		$this->become_download_admin();

		$html = $this->render( array( 'WP_DownloadManager_Admin', 'render_downloads' ) );

		$this->assertStringContainsString( 'The Manual', $html );
		$this->assertStringContainsString( 'Members Bundle', $html );
		$this->assertScreenIsClean( $html );
	}

	public function test_the_downloads_screen_uses_a_core_list_table() {
		$this->become_download_admin();

		$html = $this->render( array( 'WP_DownloadManager_Admin', 'render_downloads' ) );

		$this->assertStringContainsString( 'class="wp-list-table', $html, 'section 4.3: every tabular screen is a WP_List_Table' );
		$this->assertStringContainsString( 'tablenav', $html, 'which brings core paging and bulk actions with it' );
	}

	public function test_the_downloads_screen_offers_a_bulk_delete() {
		$this->become_download_admin();

		$html = $this->render( array( 'WP_DownloadManager_Admin', 'render_downloads' ) );

		$this->assertStringContainsString( 'name="action"', $html );
		$this->assertStringContainsString( 'value="delete"', $html );
		$this->assertStringContainsString( 'name="file_ids[]"', $html );
	}

	public function test_the_downloads_screen_offers_row_actions() {
		$this->become_download_admin();

		$html = $this->render( array( 'WP_DownloadManager_Admin', 'render_downloads' ) );

		$this->assertStringContainsString( 'row-actions', $html );
		$this->assertStringContainsString( 'action=edit', $html );
		$this->assertStringContainsString( 'action=delete', $html );
	}

	public function test_the_downloads_screen_shows_the_library_totals() {
		$this->become_download_admin();

		$html = $this->render( array( 'WP_DownloadManager_Admin', 'render_downloads' ) );

		$this->assertStringContainsString( 'Download Stats', $html );
		$this->assertStringContainsString( 'Total Bandwidth', $html );
	}

	public function test_the_downloads_screen_survives_an_empty_library() {
		global $wpdb;

		$this->become_download_admin();
		$table = $this->table();
		$wpdb->query( "TRUNCATE TABLE {$table}" );

		$html = $this->render( array( 'WP_DownloadManager_Admin', 'render_downloads' ) );

		$this->assertStringContainsString( 'No files found', $html, 'a list table needs a no_items() message' );
		$this->assertScreenIsClean( $html );
	}

	public function test_the_downloads_screen_can_be_searched() {
		$this->become_download_admin();

		$html = $this->render(
			array( 'WP_DownloadManager_Admin', 'render_downloads' ),
			array(
				'page' => 'wp-downloadmanager',
				's'    => 'Manual',
			)
		);

		$this->assertStringContainsString( 'The Manual', $html );
		$this->assertStringNotContainsString( 'Members Bundle', $html );
	}

	public function test_a_search_term_of_sql_cannot_rewrite_the_query() {
		$this->become_download_admin();

		$html = $this->render(
			array( 'WP_DownloadManager_Admin', 'render_downloads' ),
			array(
				'page' => 'wp-downloadmanager',
				's'    => "' OR 1=1 -- ",
			)
		);

		$this->assertStringContainsString( 'No files found', $html, 'the term is bound, not interpolated' );
		$this->assertSame( 5, $this->count_files(), 'and the table is untouched' );
	}

	public function test_a_search_term_containing_a_wildcard_is_taken_literally() {
		$this->become_download_admin();

		$html = $this->render(
			array( 'WP_DownloadManager_Admin', 'render_downloads' ),
			array(
				'page' => 'wp-downloadmanager',
				's'    => '%',
			)
		);

		$this->assertStringContainsString( 'No files found', $html, 'esc_like() means a literal % is not a wildcard' );
	}

	public function test_the_list_only_sorts_by_columns_on_its_allow_list() {
		$this->become_download_admin();

		$html = $this->render(
			array( 'WP_DownloadManager_Admin', 'render_downloads' ),
			array(
				'page'    => 'wp-downloadmanager',
				'orderby' => 'file_hits; DROP TABLE',
				'order'   => 'desc',
			)
		);

		$this->assertStringContainsString( 'The Manual', $html, 'an unknown column falls back rather than reaching ORDER BY' );
		$this->assertSame( 5, $this->count_files() );
	}

	public function test_every_sortable_column_has_a_real_sql_column_behind_it() {
		$table   = new WP_DownloadManager_List_Table();
		$columns = WP_DownloadManager_List_Table::sortable_sql_columns();

		foreach ( array_keys( $table->get_sortable_columns() ) as $column ) {
			$this->assertArrayHasKey( $column, $columns, $column . ' is offered as sortable but the query cannot sort by it' );
		}
	}

	public function test_the_list_pages_at_twenty_by_default() {
		$this->assertSame( 20, WP_DownloadManager_Admin::PER_PAGE );
	}

	public function test_the_rows_per_page_preference_is_kept_rather_than_discarded() {
		$this->assertSame( 15, WP_DownloadManager_Admin::save_screen_option( false, 'wp_downloadmanager_per_page', '15' ) );
		$this->assertFalse( WP_DownloadManager_Admin::save_screen_option( false, 'some_other_plugin_per_page', '15' ), 'and another plugin\'s option is left to it' );
	}

	public function test_the_add_screen_renders_its_form() {
		$this->become_download_admin();

		$html = $this->render( array( 'WP_DownloadManager_Admin', 'render_add' ) );

		$this->assertStringContainsString( 'Add File', $html );
		$this->assertStringContainsString( 'name="file_type"', $html );
		$this->assertStringContainsString( 'name="file_upload"', $html );
		$this->assertStringContainsString( 'name="file_remote"', $html );
		$this->assertScreenIsClean( $html );
	}

	public function test_the_add_screen_carries_a_nonce() {
		$this->become_download_admin();

		$html = $this->render( array( 'WP_DownloadManager_Admin', 'render_add' ) );

		$this->assertStringContainsString( 'name="_wpnonce"', $html );
	}

	public function test_the_edit_screen_is_prefilled_from_the_row() {
		$this->become_download_admin();

		$html = $this->render(
			array( 'WP_DownloadManager_Admin', 'render_downloads' ),
			array(
				'action' => 'edit',
				'id'     => $this->ids['public'],
			)
		);

		$this->assertStringContainsString( 'Edit File', $html );
		$this->assertStringContainsString( 'value="The Manual"', $html );
		$this->assertStringContainsString( 'manual.pdf', $html );
		$this->assertScreenIsClean( $html );
	}

	public function test_the_edit_screen_offers_to_keep_the_current_file() {
		$this->become_download_admin();

		$html = $this->render(
			array( 'WP_DownloadManager_Admin', 'render_downloads' ),
			array(
				'action' => 'edit',
				'id'     => $this->ids['public'],
			)
		);

		$this->assertStringContainsString( 'id="file_type_-1"', $html, 'only the edit screen has a "leave it alone" option' );
	}

	public function test_the_delete_screen_asks_before_it_deletes() {
		$this->become_download_admin();

		$html = $this->render(
			array( 'WP_DownloadManager_Admin', 'render_downloads' ),
			array(
				'action' => 'delete',
				'id'     => $this->ids['public'],
			)
		);

		$this->assertStringContainsString( 'Delete File', $html );
		$this->assertStringContainsString( 'data-confirm=', $html );
		$this->assertSame( 5, $this->count_files(), 'rendering the confirmation must not delete anything' );
		$this->assertScreenIsClean( $html );
	}

	public function test_the_delete_screen_offers_to_remove_the_file_from_disk() {
		$this->become_download_admin();

		$html = $this->render(
			array( 'WP_DownloadManager_Admin', 'render_downloads' ),
			array(
				'action' => 'delete',
				'id'     => $this->ids['public'],
			)
		);

		$this->assertStringContainsString( 'name="unlinkfile"', $html );
	}

	public function test_the_delete_screen_does_not_offer_to_unlink_a_remote_file() {
		$this->become_download_admin();

		$html = $this->render(
			array( 'WP_DownloadManager_Admin', 'render_downloads' ),
			array(
				'action' => 'delete',
				'id'     => $this->ids['remote'],
			)
		);

		$this->assertStringNotContainsString( 'name="unlinkfile"', $html, 'there is nothing on this server to unlink' );
	}

	public function test_a_stale_edit_link_says_so_rather_than_fatalling() {
		$this->become_download_admin();

		$html = $this->render(
			array( 'WP_DownloadManager_Admin', 'render_downloads' ),
			array(
				'action' => 'edit',
				'id'     => 999999,
			)
		);

		$this->assertStringContainsString( 'no longer exists', $html );
		$this->assertScreenIsClean( $html );
	}

	public function test_no_screen_uses_a_deprecated_layout_attribute() {
		$this->become_download_admin();

		$screens = array(
			$this->render( array( 'WP_DownloadManager_Admin', 'render_downloads' ) ),
			$this->render( array( 'WP_DownloadManager_Admin', 'render_add' ) ),
			$this->render(
				array( 'WP_DownloadManager_Admin', 'render_downloads' ),
				array(
					'action' => 'edit',
					'id'     => $this->ids['public'],
				)
			),
		);

		foreach ( $screens as $html ) {
			$this->assertStringNotContainsString( 'valign=', $html, 'section 4.4 allows no valign anywhere' );
			$this->assertStringNotContainsString( ' align=', $html );
			$this->assertStringNotContainsString( 'onclick=', $html, 'behaviour attaches through data attributes' );
		}
	}

	public function test_a_visitor_without_the_capability_is_stopped() {
		$this->login_as( 'subscriber' );

		$this->expectException( WPDieException::class );

		$this->render( array( 'WP_DownloadManager_Admin', 'render_downloads' ) );
	}

	public function test_the_add_screen_is_behind_the_capability_too() {
		$this->login_as( 'subscriber' );

		$this->expectException( WPDieException::class );

		$this->render( array( 'WP_DownloadManager_Admin', 'render_add' ) );
	}

	public function test_the_file_source_lists_show_what_is_in_the_downloads_directory() {
		WP_DownloadManager_Options::set( 'path.dir', WP_CONTENT_DIR . '/dm-admin-files' );
		$dir = WP_DownloadManager_Options::get( 'path.dir' );

		$this->make_download_file( 'top.txt' );
		$this->make_download_file( 'sub/deep.txt' );

		ob_start();
		WP_DownloadManager_Admin::print_files( $dir, $dir, '/top.txt' );
		$files = ob_get_clean();

		ob_start();
		WP_DownloadManager_Admin::print_folders( $dir, $dir );
		$folders = ob_get_clean();

		$this->assertStringContainsString( '<option value="/top.txt" selected', $files );
		$this->assertStringContainsString( '/sub/deep.txt', $files );
		$this->assertStringContainsString( '<option value="/">/</option>', $folders );
		$this->assertStringContainsString( 'value="/sub"', $folders );

		$this->remove_download_files();
	}

	public function test_a_missing_downloads_directory_is_not_a_fatal() {
		WP_DownloadManager_Options::set( 'path.dir', WP_CONTENT_DIR . '/dm-does-not-exist' );
		$dir = WP_DownloadManager_Options::get( 'path.dir' );

		ob_start();
		WP_DownloadManager_Admin::print_files( $dir, $dir );
		WP_DownloadManager_Admin::print_folders( $dir, $dir );
		$html = ob_get_clean();

		$this->assertStringNotContainsString( 'Warning', $html );
	}

	public function test_the_timestamp_selects_cover_every_part_of_a_date() {
		ob_start();
		WP_DownloadManager_Admin::file_timestamp( gmmktime( 14, 25, 36, 6, 15, 2020 ) );
		$html = ob_get_clean();

		foreach ( array( 'day', 'month', 'year', 'hour', 'minute', 'second' ) as $part ) {
			$this->assertStringContainsString( 'id="file_timestamp_' . $part . '"', $html );
		}

		$this->assertStringContainsString( '<option value="15" selected', $html );
		$this->assertStringContainsString( '<option value="2020" selected', $html );
		$this->assertStringContainsString( 'June', $html, 'months read as names, from the site locale' );
	}
}
