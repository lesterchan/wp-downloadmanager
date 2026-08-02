<?php
/**
 * The one settings page and its two tabs.
 *
 * @package WP-DownloadManager
 */

/**
 * Settings API registration, the tabs, and the sanitize round trip.
 */
class WP_DownloadManager_Settings_Test extends WP_DownloadManager_TestCase {

	/**
	 * Register the sections and fields before each test.
	 *
	 * Core renders these screens from a global registry, so they have no fields
	 * until register() has run. add_settings_section() and add_settings_field()
	 * key on their ids, so calling this repeatedly is idempotent.
	 */
	public function set_up() {
		parent::set_up();

		WP_DownloadManager_Settings::register();
	}

	public function test_the_option_is_registered_once_under_the_one_group() {
		global $wp_registered_settings;

		$this->assertArrayHasKey( WP_DownloadManager_Options::OPTION, $wp_registered_settings );
		$this->assertSame(
			array( 'WP_DownloadManager_Settings', 'sanitize' ),
			$wp_registered_settings[ WP_DownloadManager_Options::OPTION ]['sanitize_callback'],
			'one registered setting means one sanitize callback for both tabs'
		);
	}

	public function test_there_are_exactly_two_tabs() {
		$this->assertSame( array( 'general', 'templates' ), array_keys( WP_DownloadManager_Settings::tabs() ) );
	}

	public function test_each_tab_registers_its_sections_against_its_own_page() {
		global $wp_settings_sections;

		$this->assertArrayHasKey( WP_DownloadManager_Settings::tab_page( 'general' ), $wp_settings_sections );
		$this->assertArrayHasKey( WP_DownloadManager_Settings::tab_page( 'templates' ), $wp_settings_sections );
	}

	public function test_the_general_tab_registers_the_sections_it_should() {
		global $wp_settings_sections;

		$sections = array_keys( $wp_settings_sections[ WP_DownloadManager_Settings::tab_page( 'general' ) ] );

		$this->assertContains( WP_DownloadManager_Settings::SECTION_GENERAL, $sections );
		$this->assertContains( WP_DownloadManager_Settings::SECTION_LISTING, $sections );
		$this->assertContains( WP_DownloadManager_Settings::SECTION_RSS, $sections );
		$this->assertContains( WP_DownloadManager_Settings::SECTION_STATS, $sections );
	}

	public function test_every_section_constant_is_prefixed() {
		foreach ( array( 'SECTION_GENERAL', 'SECTION_LISTING', 'SECTION_RSS', 'SECTION_STATS', 'SECTION_TEMPLATES' ) as $name ) {
			$value = constant( 'WP_DownloadManager_Settings::' . $name );

			$this->assertStringStartsWith( 'wp_downloadmanager_', $value, $name . ' needs the plugin prefix' );
		}
	}

	public function test_every_control_is_a_registered_field() {
		global $wp_settings_fields;

		$fields = array();
		foreach ( $wp_settings_fields[ WP_DownloadManager_Settings::tab_page( 'general' ) ] as $section ) {
			$fields = array_merge( $fields, array_keys( $section ) );
		}

		foreach ( array( 'download_path', 'download_page_url', 'download_sort_by', 'download_rss_limit', 'download_stats_display', 'download_stats_most_limit' ) as $id ) {
			$this->assertContains( $id, $fields, $id . ' should be registered rather than printed by hand' );
		}
	}

	public function test_every_template_has_a_registered_field_including_both_halves_of_a_pair() {
		global $wp_settings_fields;

		$fields = array();
		foreach ( $wp_settings_fields[ WP_DownloadManager_Settings::tab_page( 'templates' ) ] as $section ) {
			$fields = array_merge( $fields, array_keys( $section ) );
		}

		$this->assertContains( 'download_template_header', $fields );
		$this->assertContains( 'download_template_listing', $fields );
		$this->assertContains( 'download_template_listing_2', $fields, 'the no-permission half is a field of its own' );
	}

	public function test_the_page_writes_no_form_table_markup_of_its_own() {
		$source = $this->code( 'includes/class-wp-downloadmanager-settings.php' );

		$this->assertStringNotContainsString( '<table class="form-table"', $source, 'section 4.2: do_settings_sections() emits it' );
	}

	public function test_the_page_renders_the_general_tab_by_default() {
		$this->become_download_admin();

		$html = $this->render( array( 'WP_DownloadManager_Settings', 'render_page' ) );

		$this->assertStringContainsString( 'nav-tab-wrapper', $html );
		$this->assertStringContainsString( 'name="' . WP_DownloadManager_Options::OPTION . '[page_url]"', $html );
		$this->assertScreenIsClean( $html );
	}

	public function test_the_page_renders_the_templates_tab_when_asked() {
		$this->become_download_admin();

		$html = $this->render( array( 'WP_DownloadManager_Settings', 'render_page' ), array( 'tab' => 'templates' ) );

		$this->assertStringContainsString( 'name="' . WP_DownloadManager_Options::OPTION . '[templates][header]"', $html );
		$this->assertStringNotContainsString( 'name="' . WP_DownloadManager_Options::OPTION . '[page_url]"', $html, 'a tab shows its own fields and no others' );
		$this->assertScreenIsClean( $html );
	}

	public function test_the_active_tab_is_marked_as_active() {
		$this->become_download_admin();

		$html = $this->render( array( 'WP_DownloadManager_Settings', 'render_page' ), array( 'tab' => 'templates' ) );

		$this->assertMatchesRegularExpression( '/nav-tab nav-tab-active"[^>]*>\s*Templates/s', $html );
	}

	public function test_an_unknown_tab_falls_back_to_the_first_one() {
		$_GET = array( 'tab' => 'nonsense' );

		$tab = WP_DownloadManager_Settings::current_tab();

		$_GET = array();

		$this->assertSame( 'general', $tab );
	}

	public function test_both_tabs_post_to_the_same_settings_group() {
		$this->become_download_admin();

		foreach ( array( 'general', 'templates' ) as $tab ) {
			$html = $this->render( array( 'WP_DownloadManager_Settings', 'render_page' ), array( 'tab' => $tab ) );

			// settings_fields() emits single-quoted attributes.
			$this->assertStringContainsString( "option_page' value='" . WP_DownloadManager_Settings::GROUP, $html, $tab . ' posts to the shared group' );
			$this->assertStringContainsString( 'action="options.php"', $html );
		}
	}

	public function test_the_page_renders_its_own_settings_errors() {
		$this->assertStringContainsString(
			'settings_errors();',
			$this->code( 'includes/class-wp-downloadmanager-settings.php' ),
			'a top-level menu page has to call this itself, and unscoped, or the save says nothing'
		);
	}

	public function test_a_save_is_reported_exactly_once() {
		$this->become_download_admin();

		$html = $this->render(
			static function () {
				// What core's options.php registers once it has written the
				// option: under the 'general' slug, not under this screen's. That
				// is the whole of the message a save produces.
				add_settings_error( 'general', 'settings_updated', 'Settings saved.', 'success' );

				WP_DownloadManager_Settings::render_page();
			}
		);

		// One assertion, both failures. Zero is the bug this pins: scoping the
		// call to the plugin's own option filtered out the only message there was,
		// and a save that reports nothing looks like a save that did not happen.
		// Two is the mirror image, which a screen under Settings gets by calling
		// this at all -- core has already printed them from options-head.php.
		$this->assertSame(
			1,
			substr_count( $html, 'Settings saved.' ),
			'the save confirmation belongs on the screen once'
		);
	}

	public function test_the_settings_screen_is_not_under_core_settings() {
		// Which is what decides the test above. options-head.php, where core
		// prints settings errors, is required by admin-header.php only when
		// $parent_file is options-general.php.
		$this->assertStringNotContainsString(
			'add_options_page(',
			$this->code( 'includes/class-wp-downloadmanager-admin.php' ),
			'this plugin hangs its screens off a top-level menu; under Settings the call above would print everything twice'
		);
	}

	public function test_a_rejected_value_still_says_why_on_the_screen() {
		$this->become_download_admin();

		$html = $this->render(
			static function () {
				WP_DownloadManager_Settings::sanitize( array( 'path' => array( 'dir' => '/etc' ) ) );

				WP_DownloadManager_Settings::render_page();
			}
		);

		// Dropping the scope must not drop the plugin's own messages with it.
		$this->assertStringContainsString( 'has to start inside your wp-content folder', $html );
	}

	public function test_the_page_is_behind_manage_options() {
		$this->login_as( 'editor' );

		$this->expectException( WPDieException::class );

		$this->render( array( 'WP_DownloadManager_Settings', 'render_page' ) );
	}

	public function test_the_general_tab_round_trips() {
		$saved = WP_DownloadManager_Settings::sanitize(
			array(
				'path'           => array(
					'dir' => WP_CONTENT_DIR . '/files',
					'url' => 'https://example.com/files',
				),
				'page_url'       => 'https://example.com/downloads',
				'method'         => '0',
				'nice_permalink' => '0',
				'use_filename'   => '1',
				'categories'     => "Alpha\nBeta\n",
				'sort'           => array(
					'by'      => 'file_hits',
					'order'   => 'desc',
					'perpage' => '15',
					'group'   => '1',
				),
				'rss'            => array(
					'sortby' => 'file_size',
					'limit'  => '9',
				),
			)
		);

		$this->assertSame( 'https://example.com/files', $saved['path']['url'] );
		$this->assertSame( 'https://example.com/downloads', $saved['page_url'] );
		$this->assertSame( 0, $saved['method'] );
		$this->assertSame( 0, $saved['nice_permalink'] );
		$this->assertSame( 1, $saved['use_filename'] );
		$this->assertSame( array( '', 'Alpha', 'Beta' ), $saved['categories'], 'index 0 is reserved for the "all categories" label' );
		$this->assertSame( 'file_hits', $saved['sort']['by'] );
		$this->assertSame( 'desc', $saved['sort']['order'] );
		$this->assertSame( 15, $saved['sort']['perpage'] );
		$this->assertSame( 1, $saved['sort']['group'] );
		$this->assertSame( 'file_size', $saved['rss']['sortby'] );
		$this->assertSame( 9, $saved['rss']['limit'] );
	}

	public function test_every_offered_sort_column_survives_the_sanitizer() {
		foreach ( WP_DownloadManager_File::sort_columns() as $column ) {
			$saved = WP_DownloadManager_Settings::sanitize( array( 'sort' => array( 'by' => $column ) ) );
			$this->assertSame( $column, $saved['sort']['by'], $column . ' is offered by the select, so it must be accepted' );

			$saved = WP_DownloadManager_Settings::sanitize( array( 'rss' => array( 'sortby' => $column ) ) );
			$this->assertSame( $column, $saved['rss']['sortby'], $column . ' should be accepted for the feed too' );
		}
	}

	public function test_the_rendered_select_offers_exactly_the_allow_listed_columns() {
		$this->become_download_admin();

		$html = $this->render( array( 'WP_DownloadManager_Settings', 'render_page' ) );

		preg_match( '#<select id="download_sort_by".*?</select>#s', $html, $matches );
		$this->assertNotEmpty( $matches, 'the sort select should render' );

		preg_match_all( '/value="([^"]+)"/', $matches[0], $values );

		$this->assertSame(
			WP_DownloadManager_File::sort_columns(),
			$values[1],
			'this is the "saves, says Settings saved., then silently reverts" bug: two places computing the same list'
		);
	}

	public function test_an_unknown_sort_column_falls_back_rather_than_being_stored() {
		$saved = WP_DownloadManager_Settings::sanitize( array( 'sort' => array( 'by' => 'DROP TABLE' ) ) );

		$this->assertSame( 'file_name', $saved['sort']['by'] );
	}

	public function test_a_download_path_outside_wp_content_is_refused() {
		$saved = WP_DownloadManager_Settings::sanitize( array( 'path' => array( 'dir' => '/etc' ) ) );

		$this->assertSame( WP_CONTENT_DIR, $saved['path']['dir'] );
	}

	public function test_traversal_is_refused_even_when_it_lands_back_inside_wp_content() {
		$saved = WP_DownloadManager_Settings::sanitize( array( 'path' => array( 'dir' => WP_CONTENT_DIR . '/../../etc' ) ) );

		$this->assertSame( WP_CONTENT_DIR, $saved['path']['dir'] );
	}

	public function test_a_refused_download_path_says_why() {
		WP_DownloadManager_Settings::sanitize( array( 'path' => array( 'dir' => '/etc' ) ) );

		$errors = get_settings_errors( WP_DownloadManager_Options::OPTION );

		$this->assertNotEmpty( $errors, 'a silently corrected value is worse than a rejected one' );
	}

	public function test_a_directory_that_does_not_exist_yet_is_kept() {
		$missing = WP_CONTENT_DIR . '/files-not-created-yet';
		$this->assertDirectoryDoesNotExist( $missing );

		$saved = WP_DownloadManager_Settings::sanitize( array( 'path' => array( 'dir' => $missing ) ) );

		$this->assertSame( $missing, $saved['path']['dir'], 'the old check reset the path to wp-content on every save when the directory was missing' );
	}

	public function test_a_trailing_slash_is_normalised_away() {
		$saved = WP_DownloadManager_Settings::sanitize( array( 'path' => array( 'dir' => WP_CONTENT_DIR . '/uploads/' ) ) );

		$this->assertSame( WP_CONTENT_DIR . '/uploads', $saved['path']['dir'] );
	}

	public function test_saving_one_field_leaves_the_stored_path_alone() {
		$path = WP_CONTENT_DIR . '/files-not-created-yet';
		WP_DownloadManager_Options::set( 'path.dir', $path );

		$saved = WP_DownloadManager_Settings::sanitize( array( 'sort' => array( 'perpage' => '7' ) ) );

		$this->assertSame( $path, $saved['path']['dir'] );
		$this->assertSame( 7, $saved['sort']['perpage'] );
	}

	public function test_saving_the_templates_tab_does_not_blank_the_general_tab() {
		WP_DownloadManager_Options::set( 'page_url', 'https://example.com/keep-me' );

		$saved = WP_DownloadManager_Settings::sanitize( array( 'templates' => array( 'header' => '<p>new header</p>' ) ) );

		$this->assertSame( 'https://example.com/keep-me', $saved['page_url'], 'both tabs write the same row, so the callback merges rather than replaces' );
		$this->assertSame( '<p>new header</p>', $saved['templates']['header'] );
	}

	public function test_saving_the_general_tab_does_not_blank_the_templates_tab() {
		WP_DownloadManager_Options::set( 'templates.header', '<p>keep this header</p>' );

		$saved = WP_DownloadManager_Settings::sanitize( array( 'page_url' => 'https://example.com/new' ) );

		$this->assertSame( '<p>keep this header</p>', $saved['templates']['header'] );
		$this->assertSame( 'https://example.com/new', $saved['page_url'] );
	}

	public function test_a_permission_pair_keeps_both_halves() {
		$saved = WP_DownloadManager_Settings::sanitize(
			array( 'templates' => array( 'listing' => array( '<p>yes</p>', '<p>no</p>' ) ) )
		);

		$this->assertSame( '<p>yes</p>', $saved['templates']['listing'][0] );
		$this->assertSame( '<p>no</p>', $saved['templates']['listing'][1] );
	}

	public function test_the_page_footer_template_may_keep_its_search_form() {
		$saved = WP_DownloadManager_Settings::sanitize(
			array(
				'templates' => array(
					'footer' => '<form action="%DOWNLOAD_PAGE_URL%" method="get"><input type="text" name="dl_search" value="" /></form>',
				),
			)
		);

		$this->assertStringContainsString( '<form', $saved['templates']['footer'], 'plain wp_kses_post() would delete the search box from the one template that ships with one' );
		$this->assertStringContainsString( 'name="dl_search"', $saved['templates']['footer'] );
	}

	public function test_templates_are_still_run_through_kses() {
		$saved = WP_DownloadManager_Settings::sanitize(
			array( 'templates' => array( 'header' => '<p>ok</p><script>alert(1)</script>' ) )
		);

		$this->assertStringNotContainsString( '<script>', $saved['templates']['header'] );
		$this->assertStringContainsString( '<p>ok</p>', $saved['templates']['header'] );
	}

	public function test_a_garbage_submission_leaves_the_stored_value_alone() {
		WP_DownloadManager_Options::set( 'page_url', 'https://example.com/intact' );

		$saved = WP_DownloadManager_Settings::sanitize( 'not an array' );

		$this->assertSame( 'https://example.com/intact', $saved['page_url'] );
	}

	public function test_the_wp_stats_toggle_round_trips() {
		$on = WP_DownloadManager_Settings::sanitize(
			array(
				'page_url'      => 'https://example.com/downloads',
				'stats_display' => '1',
			)
		);
		$this->assertSame( 1, $on['stats_display'] );

		$off = WP_DownloadManager_Settings::sanitize( array( 'page_url' => 'https://example.com/downloads' ) );
		$this->assertSame( 0, $off['stats_display'], 'an unticked checkbox posts nothing at all, and page_url is what says the tab was submitted' );
	}

	public function test_the_wp_stats_toggle_is_untouched_by_the_other_tab() {
		WP_DownloadManager_Options::set( 'stats_display', 1 );

		$saved = WP_DownloadManager_Settings::sanitize( array( 'templates' => array( 'header' => '<p>x</p>' ) ) );

		$this->assertSame( 1, $saved['stats_display'], 'saving the Templates tab must not read as "the checkbox on the other tab was unticked"' );
	}

	public function test_the_wp_stats_row_limit_is_at_least_one() {
		$saved = WP_DownloadManager_Settings::sanitize( array( 'stats_most_limit' => '0' ) );

		$this->assertSame( 1, $saved['stats_most_limit'] );
	}

	public function test_every_template_shown_has_a_default_behind_its_reset_button() {
		$this->become_download_admin();
		$defaults = WP_DownloadManager_Template::for_script();

		$html = $this->render( array( 'WP_DownloadManager_Settings', 'render_page' ), array( 'tab' => 'templates' ) );

		preg_match_all( '/data-template="([^"]+)"/', $html, $matches );

		$this->assertNotEmpty( $matches[1] );
		foreach ( $matches[1] as $key ) {
			$this->assertArrayHasKey( $key, $defaults, $key . ' has a reset button but no default' );
		}
	}

	public function test_the_reset_buttons_are_data_attributes_rather_than_inline_handlers() {
		$this->become_download_admin();

		$html = $this->render( array( 'WP_DownloadManager_Settings', 'render_page' ), array( 'tab' => 'templates' ) );

		$this->assertStringContainsString( 'class="button download-template-reset"', $html );
		$this->assertStringNotContainsString( 'onclick', $html );
	}

	public function test_the_default_templates_carry_no_image_tag() {
		foreach ( WP_DownloadManager_Template::for_script() as $key => $markup ) {
			$this->assertStringNotContainsString( '<img', $markup, $key . ' should use the drawn icon, not an image' );
		}
	}

	public function test_every_default_template_key_is_declared() {
		$defaults = WP_DownloadManager_Template::defaults();

		foreach ( WP_DownloadManager_Template::keys() as $key ) {
			$this->assertArrayHasKey( $key, $defaults, $key . ' is listed but has no default' );
		}
	}

	public function test_a_paired_template_default_has_two_halves() {
		$defaults = WP_DownloadManager_Template::defaults();

		foreach ( WP_DownloadManager_Template::paired_keys() as $key ) {
			$this->assertCount( 2, $defaults[ $key ], $key . ' is a permission pair' );
		}
	}

	public function test_the_flattened_defaults_name_both_halves() {
		$flat = WP_DownloadManager_Template::for_script();

		foreach ( WP_DownloadManager_Template::paired_keys() as $key ) {
			$this->assertArrayHasKey( $key, $flat );
			$this->assertArrayHasKey( $key . '_2', $flat, 'the reset button for the no-permission half needs a key of its own' );
		}
	}

	public function test_an_unknown_template_default_is_the_empty_string() {
		$this->assertSame( '', WP_DownloadManager_Template::get_default( 'no-such-template' ) );
	}
}
