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
		$this->assertSame( 'wp-downloadmanager', WP_DownloadManager_Admin::PAGE, 'The top level slug is the plugin slug.' );
		$this->assertSame( 'wp-downloadmanager', WP_DownloadManager_Admin::screens()['downloads'], 'And the downloads screen is registered under it.' );
	}

	public function test_no_screen_slug_is_a_file_path() {
		foreach ( WP_DownloadManager_Admin::screens() as $slug ) {
			$this->assertStringNotContainsString( '/', $slug, 'the legacy "plugin file as menu slug" form is gone' );
			$this->assertStringNotContainsString( '.php', $slug, 'A screen slug that is a file path can be reached without going through the menu.' );
		}
	}

	public function test_the_menu_registers_one_top_level_entry() {
		global $menu, $submenu;

		$menu    = array();
		$submenu = array();
		$this->become_download_admin();
		set_current_screen( 'dashboard' );

		WP_DownloadManager_Admin::add_page();

		$slugs = wp_list_pluck( $menu, 2 );

		$this->assertContains( WP_DownloadManager_Admin::PAGE, $slugs, 'a plugin with data screens gets one top-level menu' );
	}

	public function test_the_data_screen_is_first_and_settings_is_last() {
		global $menu, $submenu;

		$menu    = array();
		$submenu = array();
		$this->become_download_admin();
		set_current_screen( 'dashboard' );

		WP_DownloadManager_Admin::add_page();

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

		WP_DownloadManager_Admin::add_page();

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

		WP_DownloadManager_Admin::add_page();

		$entries = $submenu[ WP_DownloadManager_Admin::PAGE ];

		$this->assertSame( 'manage_downloads', $entries[0][1], 'the downloads library keeps its own capability' );
		$this->assertSame( 'manage_downloads', $entries[1][1], 'A data screen sits behind the plugin capability, not a core one.' );
	}

	public function test_the_settings_screen_sits_behind_manage_options() {
		global $menu, $submenu;

		$menu    = array();
		$submenu = array();
		$this->become_download_admin();
		set_current_screen( 'dashboard' );

		WP_DownloadManager_Admin::add_page();

		$entries = $submenu[ WP_DownloadManager_Admin::PAGE ];

		$this->assertSame( 'manage_options', $entries[2][1], 'section 2.7: settings screens require manage_options' );
	}

	public function test_the_capability_goes_through_one_filter() {
		add_filter( 'wp_downloadmanager_capability', fn( $cap, $context ) => 'settings' === $context ? 'edit_theme_options' : 'edit_posts', 10, 2 );

		$this->assertSame( 'edit_posts', WP_DownloadManager_Admin::capability(), 'A filter can replace the data capability.' );
		$this->assertSame( 'edit_theme_options', WP_DownloadManager_Settings::capability(), 'And the settings capability, which is asked for separately.' );
	}

	public function test_a_screen_url_points_at_the_menu_slug() {
		$this->assertStringContainsString( 'page=wp-downloadmanager', WP_DownloadManager_Admin::screen_url(), 'The default screen URL points at the menu slug.' );
		$this->assertStringContainsString( 'page=wp-downloadmanager-add', WP_DownloadManager_Admin::screen_url( 'add' ), 'The add screen has its own slug.' );
		$this->assertStringContainsString( 'page=wp-downloadmanager-settings', WP_DownloadManager_Admin::screen_url( 'settings' ), 'And the settings screen.' );
	}

	public function test_an_edit_or_delete_url_carries_a_nonce() {
		$this->assertStringContainsString( '_wpnonce=', WP_DownloadManager_Admin::screen_url( 'edit', 3 ), 'An edit link carries a nonce.' );
		$this->assertStringContainsString( '_wpnonce=', WP_DownloadManager_Admin::screen_url( 'delete', 3 ), 'And so does a delete link.' );
		$this->assertStringContainsString( 'id=3', WP_DownloadManager_Admin::screen_url( 'edit', 3 ), 'And the edit link carries the row ID it is for.' );
	}

	public function test_a_listing_url_carries_no_nonce() {
		$this->assertStringNotContainsString( '_wpnonce', WP_DownloadManager_Admin::screen_url(), 'a nonce on a bookmarkable listing URL breaks bookmarking for no gain' );
	}

	public function test_the_downloads_screen_renders_the_list() {
		$this->become_download_admin();

		$html = $this->render( array( 'WP_DownloadManager_Admin', 'render_downloads' ) );

		$this->assertStringContainsString( 'The Manual', $html, 'A file is listed on the downloads screen.' );
		$this->assertStringContainsString( 'Members Bundle', $html, 'And so is another, so the list is not stopping at one.' );
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

		$this->assertStringContainsString( 'name="action"', $html, 'The list offers a bulk action select.' );
		$this->assertStringContainsString( 'value="delete"', $html, 'With delete among its options.' );
		$this->assertStringContainsString( 'name="file_ids[]"', $html, 'And a checkbox per row for it to act on.' );
	}

	public function test_the_downloads_screen_offers_row_actions() {
		$this->become_download_admin();

		$html = $this->render( array( 'WP_DownloadManager_Admin', 'render_downloads' ) );

		$this->assertStringContainsString( 'row-actions', $html, 'Each row carries the core row actions markup.' );
		$this->assertStringContainsString( 'action=edit', $html, 'With an edit link.' );
		$this->assertStringContainsString( 'action=delete', $html, 'And a delete link.' );
	}

	public function test_the_downloads_screen_shows_the_library_totals() {
		$this->become_download_admin();

		$html = $this->render( array( 'WP_DownloadManager_Admin', 'render_downloads' ) );

		$this->assertStringContainsString( 'Download Stats', $html, 'The screen shows the library totals.' );
		$this->assertStringContainsString( 'Total Bandwidth', $html, 'Including the bandwidth served.' );
	}

	public function test_the_downloads_screen_survives_an_empty_library() {
		global $wpdb;

		$this->become_download_admin();
		$table = $this->table();
		$wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', $table ) );

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

		$this->assertStringContainsString( 'The Manual', $html, 'A search returns the row that matches.' );
		$this->assertStringNotContainsString( 'Members Bundle', $html, 'And not the one that does not, so the term really filters.' );
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
		$this->assertSame( 5, $this->count_files(), 'A sort column off the allow list changes nothing rather than reaching the query.' );
	}

	public function test_every_sortable_column_has_a_real_sql_column_behind_it() {
		$table   = new WP_DownloadManager_List_Table();
		$columns = WP_DownloadManager_List_Table::sortable_sql_columns();

		foreach ( array_keys( $table->get_sortable_columns() ) as $column ) {
			$this->assertArrayHasKey( $column, $columns, $column . ' is offered as sortable but the query cannot sort by it' );
		}
	}

	public function test_the_list_pages_at_twenty_by_default() {
		$this->assertSame( 20, WP_DownloadManager_Admin::PER_PAGE, 'The list pages at twenty rows by default.' );
	}

	public function test_the_rows_per_page_preference_is_kept_rather_than_discarded() {
		$this->assertSame( 15, WP_DownloadManager_Admin::save_screen_option( false, 'wp_downloadmanager_per_page', '15' ), 'The rows per page preference is handed back to core to store, not discarded.' );
		$this->assertFalse( WP_DownloadManager_Admin::save_screen_option( false, 'some_other_plugin_per_page', '15' ), 'and another plugin\'s option is left to it' );
	}

	public function test_the_add_screen_renders_its_form() {
		$this->become_download_admin();

		$html = $this->render( array( 'WP_DownloadManager_Admin', 'render_add' ) );

		$this->assertStringContainsString( 'Add File', $html, 'The add screen renders its form.' );
		$this->assertStringContainsString( 'name="file_type"', $html, 'With the source type selector.' );
		$this->assertStringContainsString( 'name="file_upload"', $html, 'The upload field.' );
		$this->assertStringContainsString( 'name="file_remote"', $html, 'And the remote URL field.' );
		$this->assertScreenIsClean( $html );
	}

	/**
	 * Every value a typed input arrives holding, keyed by field name.
	 *
	 * @param string $html Rendered screen.
	 * @param string $type The input type to collect.
	 * @return array
	 */
	protected function prefilled_values( $html, $type ) {
		$found = array();

		foreach ( $this->fields_of_type( $html, $type ) as $name => $input ) {
			if ( ! preg_match( '/\bvalue="([^"]*)"/i', $input, $value ) || '' === $value[1] ) {
				continue;
			}

			$found[ $name ] = $value[1];
		}

		return $found;
	}

	/**
	 * Every input of a given type on the screen, keyed by field name.
	 *
	 * Separate from prefilled_values() so that a test can assert the screen
	 * really does render the kind of field it is about to make claims over.
	 *
	 * @param string $html Rendered screen.
	 * @param string $type The input type to collect.
	 * @return array
	 */
	protected function fields_of_type( $html, $type ) {
		preg_match_all( '/<input[^>]*>/i', $html, $inputs );

		$found = array();

		foreach ( $inputs[0] as $input ) {
			if ( ! preg_match( '/type="' . $type . '"/i', $input )
				|| ! preg_match( '/name="([^"]+)"/i', $input, $name ) ) {
				continue;
			}

			$found[ $name[1] ] = $input;
		}

		return $found;
	}

	public function test_no_field_arrives_holding_a_value_its_own_type_rejects() {
		$this->become_download_admin();

		foreach ( array( 'add', 'edit' ) as $screen ) {
			$html = 'add' === $screen
				? $this->render( array( 'WP_DownloadManager_Admin', 'render_add' ) )
				: $this->render(
					array( 'WP_DownloadManager_Admin', 'render_downloads' ),
					array(
						'action' => 'edit',
						'id'     => $this->ids['public'],
					)
				);

			// The fixture first. Once the fix is in there are no prefilled url
			// fields left, so the loop below runs zero times and the test passes
			// while asserting nothing -- which PHPUnit calls risky and the shared
			// config makes fatal, and rightly: the same vacuous pass is what a
			// renamed field or a screen that stopped rendering would produce.
			$this->assertNotEmpty(
				$this->fields_of_type( $html, 'url' ),
				'the ' . $screen . ' screen renders no type="url" field at all, so this test proves nothing'
			);

			// The browser validates <input type="url"> before it will submit the
			// form, and it validates every such field on the screen rather than
			// only the one in use. A field shipped holding "https://" -- no host,
			// so not a URL -- made the whole form unsubmittable on arrival: focus
			// jumped to it, a bubble appeared and no request was made, however the
			// admin had filled the rest in.
			foreach ( $this->prefilled_values( $html, 'url' ) as $name => $value ) {
				$this->assertNotFalse(
					filter_var( $value, FILTER_VALIDATE_URL ),
					$name . ' arrives on the ' . $screen . ' screen holding "' . $value . '", which no browser will submit'
				);
			}
		}
	}

	public function test_the_remote_url_field_suggests_the_scheme_without_prefilling_it() {
		$this->become_download_admin();

		$html = $this->render( array( 'WP_DownloadManager_Admin', 'render_add' ) );

		preg_match( '/<input[^>]*name="file_remote"[^>]*>/i', $html, $field );

		$this->assertNotEmpty( $field, 'the remote source field should render' );
		$this->assertStringContainsString( 'placeholder="https://"', $field[0], 'a hint the browser does not validate' );
		$this->assertStringNotContainsString( 'value="', $field[0], 'The remote URL field is left empty, so the scheme is a hint rather than a value to submit.' );
	}

	public function test_the_add_screen_carries_a_nonce() {
		$this->become_download_admin();

		$html = $this->render( array( 'WP_DownloadManager_Admin', 'render_add' ) );

		$this->assertStringContainsString( 'name="_wpnonce"', $html, 'The add form carries a nonce.' );
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

		$this->assertStringContainsString( 'Edit File', $html, 'The edit screen renders its form.' );
		$this->assertStringContainsString( 'value="The Manual"', $html, 'Prefilled with the stored name.' );
		$this->assertStringContainsString( 'manual.pdf', $html, 'And the stored path.' );
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

		$this->assertStringContainsString( 'Delete File', $html, 'The delete screen asks before it deletes.' );
		$this->assertStringContainsString( 'data-confirm=', $html, 'With the confirmation carried as data rather than as an inline handler.' );
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

		$this->assertStringContainsString( 'name="unlinkfile"', $html, 'The delete screen offers to remove the file from disk as well as the row.' );
	}

	/**
	 * The list table has always labelled a file in no category "N/A", and the
	 * Delete File screen showed the same file a blank cell -- one click apart, on
	 * the row the owner had just clicked. Both read one lookup, so both are asked
	 * here rather than the lookup being asked twice.
	 */
	public function test_the_delete_screen_labels_no_category_as_the_list_does() {
		$this->become_download_admin();

		$loose = $this->insert_file(
			array(
				'file_name'     => 'Loose File',
				'file_category' => 0,
			)
		);

		$table = new WP_DownloadManager_List_Table();
		$cell  = $table->column_default( (object) array( 'file_category' => 0 ), 'file_category' );

		$html = $this->render(
			array( 'WP_DownloadManager_Admin', 'render_downloads' ),
			array(
				'action' => 'delete',
				'id'     => $loose,
			)
		);

		$this->assertSame( 'N/A', $cell, 'the list table labels a file that is in no category' );
		$this->assertMatchesRegularExpression(
			'#<th[^>]*>File Category</th>\s*<td>N/A</td>#',
			$html,
			'and the delete screen gives that same file the same label'
		);
		$this->assertScreenIsClean( $html );
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

		$this->assertStringContainsString( 'no longer exists', $html, 'A stale edit link says so rather than fatalling on a missing row.' );
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
			$this->assertStringNotContainsString( ' align=', $html, 'The screen still uses a deprecated layout attribute where CSS belongs.' );
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

		$this->assertStringContainsString( '<option value="/top.txt" selected', $files, 'The stored file is selected in the file list.' );
		$this->assertStringContainsString( '/sub/deep.txt', $files, 'And a file in a subfolder is listed too, so the walk goes down.' );
		$this->assertStringContainsString( '<option value="/">/</option>', $folders, 'The folder list starts at the downloads directory itself.' );
		$this->assertStringContainsString( 'value="/sub"', $folders, 'And carries the subfolders below it.' );

		$this->remove_download_files();
	}

	public function test_a_missing_downloads_directory_is_not_a_fatal() {
		WP_DownloadManager_Options::set( 'path.dir', WP_CONTENT_DIR . '/dm-does-not-exist' );
		$dir = WP_DownloadManager_Options::get( 'path.dir' );

		ob_start();
		WP_DownloadManager_Admin::print_files( $dir, $dir );
		WP_DownloadManager_Admin::print_folders( $dir, $dir );
		$html = ob_get_clean();

		$this->assertStringNotContainsString( 'Warning', $html, 'A missing downloads directory renders without a warning.' );
	}

	public function test_the_timestamp_selects_cover_every_part_of_a_date() {
		ob_start();
		WP_DownloadManager_Admin::file_timestamp( gmmktime( 14, 25, 36, 6, 15, 2020 ) );
		$html = ob_get_clean();

		foreach ( array( 'day', 'month', 'year', 'hour', 'minute', 'second' ) as $part ) {
			$this->assertStringContainsString( 'id="file_timestamp_' . $part . '"', $html, 'The timestamp selects are missing the ' . $part . ' part of the date.' );
		}

		$this->assertStringContainsString( '<option value="15" selected', $html, 'The day of the stored date is selected.' );
		$this->assertStringContainsString( '<option value="2020" selected', $html, 'And the year, so every part of the timestamp is covered.' );
		$this->assertStringContainsString( 'June', $html, 'months read as names, from the site locale' );
	}
}
