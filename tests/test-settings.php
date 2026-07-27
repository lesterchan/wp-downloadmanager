<?php
/**
 * The two Settings API screens.
 *
 * @package WP-DownloadManager
 */

/**
 * Registration, sanitizing and the save round-trip.
 */
class Test_Settings extends DownloadManager_TestCase {

	/**
	 * Both groups register the one consolidated option.
	 */
	public function test_option_is_registered_under_both_groups() {
		// Called directly rather than through do_action( 'admin_init' ), which
		// also fires core handlers that try to send headers.
		DownloadManager_Settings::register();

		global $wp_registered_settings;

		$this->assertArrayHasKey( DownloadManager_Options::OPTION, $wp_registered_settings );
		$this->assertSame(
			array( 'DownloadManager_Settings', 'sanitize' ),
			$wp_registered_settings[ DownloadManager_Options::OPTION ]['sanitize_callback']
		);
	}

	/**
	 * The options screen round-trips.
	 */
	public function test_options_screen_round_trip() {
		$saved = DownloadManager_Settings::sanitize(
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
		// Index 0 is reserved for the "all categories" label.
		$this->assertSame( array( '', 'Alpha', 'Beta' ), $saved['categories'] );
		$this->assertSame( 'file_hits', $saved['sort']['by'] );
		$this->assertSame( 'desc', $saved['sort']['order'] );
		$this->assertSame( 15, $saved['sort']['perpage'] );
		$this->assertSame( 1, $saved['sort']['group'] );
		$this->assertSame( 'file_size', $saved['rss']['sortby'] );
		$this->assertSame( 9, $saved['rss']['limit'] );
	}

	/**
	 * Every sort column the screen offers survives the sanitizer.
	 *
	 * This is the "saves, says Settings saved., then silently reverts" bug: two
	 * places computing the same list with slightly different rules, so the
	 * select offered a value the sanitizer rejected. Both now derive from
	 * DownloadManager_File::sort_columns().
	 */
	public function test_every_offered_sort_column_is_accepted() {
		foreach ( DownloadManager_File::sort_columns() as $column ) {
			$saved = DownloadManager_Settings::sanitize( array( 'sort' => array( 'by' => $column ) ) );
			$this->assertSame( $column, $saved['sort']['by'], "{$column} should be accepted" );

			$saved = DownloadManager_Settings::sanitize( array( 'rss' => array( 'sortby' => $column ) ) );
			$this->assertSame( $column, $saved['rss']['sortby'], "{$column} should be accepted for the feed" );
		}
	}

	/**
	 * The rendered select offers exactly the allow-listed columns.
	 */
	public function test_rendered_select_matches_the_allow_list() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		ob_start();
		DownloadManager_Settings::render_options_page();
		$html = ob_get_clean();

		preg_match( '#<select id="download_sort_by".*?</select>#s', $html, $matches );
		$this->assertNotEmpty( $matches, 'the sort select should render' );

		preg_match_all( '/value="([^"]+)"/', $matches[0], $values );

		$this->assertSame( DownloadManager_File::sort_columns(), $values[1] );
	}

	/**
	 * An unknown sort column falls back rather than being stored.
	 */
	public function test_unknown_sort_column_falls_back() {
		$saved = DownloadManager_Settings::sanitize( array( 'sort' => array( 'by' => 'DROP TABLE' ) ) );

		$this->assertSame( 'file_name', $saved['sort']['by'] );
	}

	/**
	 * A download path outside wp-content is refused.
	 */
	public function test_download_path_is_constrained_to_wp_content() {
		$saved = DownloadManager_Settings::sanitize(
			array( 'path' => array( 'dir' => '/etc' ) )
		);

		$this->assertSame( WP_CONTENT_DIR, $saved['path']['dir'] );
	}

	/**
	 * Saving the templates screen does not blank the options screen.
	 *
	 * Both screens write the same row, so a sanitize callback that replaced
	 * rather than merged would wipe whichever screen was not submitted.
	 */
	public function test_saving_templates_keeps_options() {
		DownloadManager_Options::set( 'page_url', 'https://example.com/keep-me' );

		$saved = DownloadManager_Settings::sanitize(
			array( 'templates' => array( 'header' => '<p>new header</p>' ) )
		);

		$this->assertSame( 'https://example.com/keep-me', $saved['page_url'] );
		$this->assertSame( '<p>new header</p>', $saved['templates']['header'] );
	}

	/**
	 * And the other way round.
	 */
	public function test_saving_options_keeps_templates() {
		DownloadManager_Options::set( 'templates.header', '<p>keep this header</p>' );

		$saved = DownloadManager_Settings::sanitize( array( 'page_url' => 'https://example.com/new' ) );

		$this->assertSame( '<p>keep this header</p>', $saved['templates']['header'] );
		$this->assertSame( 'https://example.com/new', $saved['page_url'] );
	}

	/**
	 * Permission pairs keep both halves.
	 */
	public function test_paired_templates_round_trip() {
		$saved = DownloadManager_Settings::sanitize(
			array(
				'templates' => array(
					'listing' => array( '<p>yes</p>', '<p>no</p>' ),
				),
			)
		);

		$this->assertSame( '<p>yes</p>', $saved['templates']['listing'][0] );
		$this->assertSame( '<p>no</p>', $saved['templates']['listing'][1] );
	}

	/**
	 * The page footer template may keep its search form.
	 *
	 * wp_kses_post() strips <form> and <input>, which would silently delete the
	 * search box from the one template that ships with one.
	 */
	public function test_footer_template_keeps_its_form() {
		$saved = DownloadManager_Settings::sanitize(
			array(
				'templates' => array(
					'footer' => '<form action="%DOWNLOAD_PAGE_URL%" method="get"><input type="text" name="dl_search" value="" /></form>',
				),
			)
		);

		$this->assertStringContainsString( '<form', $saved['templates']['footer'] );
		$this->assertStringContainsString( 'name="dl_search"', $saved['templates']['footer'] );
	}

	/**
	 * Templates are still run through kses.
	 */
	public function test_templates_are_kses_filtered() {
		$saved = DownloadManager_Settings::sanitize(
			array(
				'templates' => array(
					'header' => '<p>ok</p><script>alert(1)</script>',
				),
			)
		);

		$this->assertStringNotContainsString( '<script>', $saved['templates']['header'] );
		$this->assertStringContainsString( '<p>ok</p>', $saved['templates']['header'] );
	}

	/**
	 * A non-array submission leaves the stored value alone.
	 */
	public function test_garbage_submission_is_ignored() {
		DownloadManager_Options::set( 'page_url', 'https://example.com/intact' );

		$saved = DownloadManager_Settings::sanitize( 'not an array' );

		$this->assertSame( 'https://example.com/intact', $saved['page_url'] );
	}

	/**
	 * Both screens render without a PHP notice and with the right nonce group.
	 */
	public function test_screens_render() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		ob_start();
		DownloadManager_Settings::render_options_page();
		$options = ob_get_clean();

		ob_start();
		DownloadManager_Settings::render_templates_page();
		$templates = ob_get_clean();

		// settings_fields() emits single-quoted attributes.
		$this->assertStringContainsString( "option_page' value='" . DownloadManager_Settings::GROUP_OPTIONS, $options );
		$this->assertStringContainsString( "option_page' value='" . DownloadManager_Settings::GROUP_TEMPLATES, $templates );

		// Every field posts into the one consolidated option.
		$this->assertStringContainsString( 'name="' . DownloadManager_Options::OPTION . '[page_url]"', $options );
		$this->assertStringContainsString( 'name="' . DownloadManager_Options::OPTION . '[templates][header]"', $templates );

		// The reset buttons are data attributes, not inline onclick handlers.
		$this->assertStringContainsString( 'class="button download-template-reset"', $templates );
		$this->assertStringNotContainsString( 'onclick', $templates );

		foreach ( array( $options, $templates ) as $html ) {
			$this->assertStringNotContainsString( 'Warning', $html );
			$this->assertStringNotContainsString( 'Undefined', $html );
			$this->assertStringNotContainsString( 'translators:', $html );
		}
	}

	/**
	 * Every template the screen shows has a reset default behind it.
	 */
	public function test_every_template_has_a_default_for_its_reset_button() {
		$defaults = DownloadManager_Templates::for_script();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		ob_start();
		DownloadManager_Settings::render_templates_page();
		$html = ob_get_clean();

		preg_match_all( '/data-template="([^"]+)"/', $html, $matches );

		$this->assertNotEmpty( $matches[1] );
		foreach ( $matches[1] as $key ) {
			$this->assertArrayHasKey( $key, $defaults, "{$key} has a reset button but no default" );
		}
	}
}
