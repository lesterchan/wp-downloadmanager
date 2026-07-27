<?php
/**
 * Security and correctness regressions.
 *
 * Every test here fails against 1.69.2. They are the differential half of the
 * harness: the golden master proves the rewrite changed nothing, and these
 * prove it changed the things it was supposed to.
 *
 * @package WP-DownloadManager
 */

/**
 * Injection, escaping and multisite correctness.
 */
class Test_Security extends DownloadManager_TestCase {

	/**
	 * Category ids are cast before they reach the IN() list.
	 *
	 * The get_downloads_category() tag implode()d its array argument straight into the
	 * SQL. The widget hands it explode( ',', $instance['cat_ids'] ), so anyone
	 * who can edit a widget could rewrite the WHERE clause - and the payload
	 * below lifts the file_permission != -2 guard that hides files.
	 */
	public function test_get_downloads_category_array_is_not_injectable() {
		$this->login_as( '' );

		$out = get_downloads_category( array( '1', '2) OR 1=1 -- ' ), 10, 0, false );

		$this->assertStringNotContainsString( 'Hidden File', $out );
		$this->assertEmpty( $GLOBALS['wpdb']->last_error, 'the query should still be well formed' );
	}

	/**
	 * A single category id is cast too.
	 */
	public function test_get_downloads_category_scalar_is_not_injectable() {
		$this->login_as( '' );

		$out = get_downloads_category( '1 OR 1=1', 10, 0, false );

		$this->assertStringNotContainsString( 'Hidden File', $out );
		// Operator precedence hides the leak from the assertion above - AND
		// binds tighter than OR - so pin the actual selection as well. Only
		// category 1 may come back.
		$this->assertStringContainsString( 'The Manual', $out );
		$this->assertStringNotContainsString( 'Editor Notes', $out );
		$this->assertStringNotContainsString( 'Remote Bundle', $out );
	}

	/**
	 * The row limit is an integer.
	 */
	public function test_limits_are_cast() {
		$this->login_as( '' );

		$this->assertEmpty( $GLOBALS['wpdb']->last_error );

		get_most_downloaded( '5 UNION SELECT 1', 0, false );
		$this->assertEmpty( $GLOBALS['wpdb']->last_error, 'most downloaded limit should be cast' );

		get_recent_downloads( '5 UNION SELECT 1', 0, false );
		$this->assertEmpty( $GLOBALS['wpdb']->last_error, 'recent downloads limit should be cast' );
	}

	/**
	 * A search term containing regex metacharacters does not blow up the
	 * highlighter.
	 *
	 * The download_search_highlight() tag interpolated the raw term into a preg_replace
	 * pattern, so "(" was enough to make PHP emit a compilation warning and
	 * return null - blanking the file name.
	 */
	public function test_search_highlight_survives_regex_metacharacters() {
		foreach ( array( '(', '[a-', '*', '\\', '+' ) as $term ) {
			$this->assertSame(
				'The Manual',
				download_search_highlight( $term, 'The Manual' ),
				"search term {$term} should be treated literally"
			);
		}
	}

	/**
	 * A search term that does match is still highlighted after escaping.
	 */
	public function test_search_highlight_still_highlights() {
		$this->assertSame(
			'The <span class="download-search-highlight">Manual</span>',
			download_search_highlight( 'Manual', 'The Manual' )
		);
	}

	/**
	 * A search term is matched literally, wildcards and quotes included.
	 *
	 * The term went into a LIKE with only addslashes() behind it and no
	 * esc_like(), so "%" matched everything and a quote could terminate the
	 * string. The clause also feeds two further queries, which is why it is
	 * carried as placeholders plus arguments rather than a prepared fragment -
	 * re-preparing one would misread the LIKE wildcards.
	 */
	public function test_search_term_is_matched_literally() {
		$this->insert_file(
			array(
				'file'          => '/100_percent.zip',
				'file_name'     => '100% Complete',
				'file_category' => 1,
			)
		);

		$this->login_as( '' );

		$_GET['dl_search'] = '%';
		$out               = downloads_page();
		unset( $_GET['dl_search'] );

		$this->assertEmpty( $GLOBALS['wpdb']->last_error );
		// A literal % must match only the file whose name contains one.
		$this->assertStringContainsString( 'Complete', $out );
		$this->assertStringNotContainsString( 'Editor Notes', $out );
	}

	/**
	 * A quote in the search term cannot break the query.
	 */
	public function test_search_term_with_quote_is_safe() {
		$this->login_as( '' );

		$_GET['dl_search'] = "' OR 1=1 -- ";
		$out               = downloads_page();
		unset( $_GET['dl_search'] );

		$this->assertEmpty( $GLOBALS['wpdb']->last_error );
		$this->assertStringNotContainsString( 'Hidden File', $out );
	}

	/**
	 * The listing sort column is restricted to real columns.
	 *
	 * The download_sort['by'] value went into ORDER BY with only sanitize_text_field()
	 * behind it, so a value saved on the options screen reached the query
	 * verbatim.
	 */
	public function test_listing_sort_column_is_allow_listed() {
		DownloadManager_Options::set( 'sort.by', 'file_name, (SELECT 1)' );
		DownloadManager_Options::set( 'sort.order', 'asc; SELECT 1' );

		downloads_page();

		$this->assertEmpty(
			$GLOBALS['wpdb']->last_error,
			'an unknown sort column should fall back rather than reach the query'
		);
	}

	/**
	 * The feed sort column is restricted too.
	 */
	public function test_feed_sort_column_is_allow_listed() {
		DownloadManager_Options::set( 'rss.sortby', 'file_date, (SELECT 1)' );
		DownloadManager_Options::set( 'rss.limit', '5 UNION SELECT 1' );

		$files = downloadmanager_feed_files();

		$this->assertIsArray( $files );
		$this->assertEmpty( $GLOBALS['wpdb']->last_error );
	}

	/**
	 * A file in a category that no longer exists renders without a PHP notice.
	 *
	 * The templates read $download_categories[ $cat_id ] unguarded, so deleting
	 * a category on the options screen turned every listing into a stream of
	 * "Undefined array key" warnings on PHP 8.
	 */
	public function test_missing_category_does_not_warn() {
		$this->insert_file(
			array(
				'file'          => '/orphan.zip',
				'file_name'     => 'Orphan',
				'file_category' => 99,
			)
		);

		$this->login_as( '' );

		$out = downloads_page();

		$this->assertStringContainsString( 'Orphan', $out );
	}

	/**
	 * Same for the embedded and stats templates.
	 */
	public function test_missing_category_does_not_warn_in_embedded() {
		$id = $this->insert_file(
			array(
				'file'          => '/orphan.zip',
				'file_name'     => 'Orphan',
				'file_category' => 99,
			)
		);

		$this->login_as( '' );

		$this->assertStringContainsString( 'Orphan', download_embedded( 'file_id = ' . $id ) );
		$this->assertStringContainsString( 'Orphan', get_recent_downloads( 10, 0, false ) );
	}

	/**
	 * The widget's "link to download page" select reflects the saved value.
	 *
	 * The three <option> tags were compared against $type, not $link, so the
	 * setting always rendered as unselected and silently reverted to No.
	 */
	public function test_widget_form_link_select_reflects_saved_value() {
		$widget = new DownloadManager_Widget();

		ob_start();
		$widget->form(
			array(
				'title'   => 'Downloads',
				'type'    => 'most_downloaded',
				'limit'   => 10,
				'chars'   => 0,
				'cat_ids' => '0',
				'link'    => 1,
			)
		);
		$out = ob_get_clean();

		$this->assertMatchesRegularExpression(
			'/<option value="1"\s+selected=(\'|")selected(\'|")/',
			$out,
			'link=1 should render as the selected option'
		);
	}

	/**
	 * A widget saved with link=0 keeps link=0.
	 */
	public function test_widget_form_link_select_respects_zero() {
		$widget = new DownloadManager_Widget();

		ob_start();
		$widget->form(
			array(
				'title'   => 'Downloads',
				'type'    => 'most_downloaded',
				'limit'   => 10,
				'chars'   => 0,
				'cat_ids' => '0',
				'link'    => 0,
			)
		);
		$out = ob_get_clean();

		$this->assertMatchesRegularExpression(
			'/<option value="0"\s+selected=(\'|")selected(\'|")/',
			$out
		);
	}

	/**
	 * The widget saves without the hidden submit marker.
	 *
	 * The update() method bailed out with false unless $new_instance['submit'] was set,
	 * which the block widget editor and the customizer never send - so widget
	 * edits made there were silently discarded.
	 */
	public function test_widget_update_without_submit_marker() {
		$widget = new DownloadManager_Widget();

		$saved = $widget->update(
			array(
				'title'   => 'Latest files',
				'type'    => 'recent_downloads',
				'limit'   => '4',
				'chars'   => '30',
				'cat_ids' => '1,2',
				'link'    => '1',
			),
			array()
		);

		$this->assertIsArray( $saved );
		$this->assertSame( 'Latest files', $saved['title'] );
		$this->assertSame( 'recent_downloads', $saved['type'] );
		$this->assertSame( 4, $saved['limit'] );
	}

	/**
	 * A widget rendered from a bare instance does not warn about missing keys.
	 */
	public function test_widget_renders_with_partial_instance() {
		$this->login_as( '' );

		$widget = new DownloadManager_Widget();

		ob_start();
		$widget->widget(
			array(
				'before_widget' => '',
				'after_widget'  => '',
				'before_title'  => '<h2>',
				'after_title'   => '</h2>',
			),
			array( 'type' => 'most_downloaded' )
		);
		$out = ob_get_clean();

		$this->assertStringContainsString( 'The Manual', $out );
	}

	/**
	 * The download_embedded() tag returns a string when nothing matches.
	 *
	 * It used to fall off the end of the function and return null, which is a
	 * TypeError waiting to happen for any caller that concatenates the result.
	 */
	public function test_download_embedded_returns_string_when_empty() {
		$this->assertSame( '', download_embedded( 'file_id = 999999' ) );
	}

	/**
	 * The uninstaller does not call the function core removed in WP 5.1.
	 *
	 * That function is gone, so the multisite branch fatals rather than merely
	 * skipping sites. Asserted at source level because a single-site suite
	 * cannot build a network to run the branch against.
	 */
	public function test_uninstall_does_not_call_wp_get_sites() {
		$this->assertStringNotContainsString( 'wp_get_sites', $this->code( 'uninstall.php' ) );
	}

	/**
	 * The activation hook does not call it either.
	 */
	public function test_activation_does_not_call_wp_get_sites() {
		$this->assertStringNotContainsString( 'wp_get_sites', $this->code( 'includes/class-downloadmanager-install.php' ) );
	}

	/**
	 * The uninstaller lifts WP_Site_Query's default cap of 100 sites.
	 *
	 * A bare get_sites() silently stops at the hundredth site and leaves the
	 * options and the table behind on every site after that, reporting success.
	 * Source-level guard: a single-site suite cannot build a 101-site network.
	 */
	public function test_uninstall_queries_every_site() {
		$source = $this->code( 'uninstall.php' );

		$this->assertMatchesRegularExpression( "/'number'\s*=>\s*0/", $source );
		$this->assertMatchesRegularExpression( "/'fields'\s*=>\s*'ids'/", $source );
	}

	/**
	 * Each switch is restored inside the loop rather than once at the end.
	 *
	 * The switch_to_blog() call pushes onto a stack, so switching N times and restoring
	 * once leaves the stack unwound by exactly one.
	 */
	public function test_uninstall_restores_each_blog() {
		$source = $this->code( 'uninstall.php' );

		$this->assertSame(
			substr_count( $source, 'switch_to_blog' ),
			substr_count( $source, 'restore_current_blog' ),
			'every switch_to_blog() needs its own restore_current_blog()'
		);
	}

	/**
	 * Activation no longer reaches for the file core deprecated in 2.5.
	 */
	public function test_activation_uses_current_upgrade_include() {
		$this->assertStringNotContainsString( 'upgrade-functions.php', $this->code( 'includes/class-downloadmanager-install.php' ) );
	}

	/**
	 * The add_option() calls do not pass the deprecated description argument.
	 */
	public function test_activation_does_not_pass_deprecated_add_option_arg() {
		$source = $this->code( 'includes/class-downloadmanager-install.php' );

		$this->assertDoesNotMatchRegularExpression(
			"/add_option\(\s*'[a-z_]+'\s*,[^;]*,\s*'[A-Z]/",
			$source,
			"add_option()'s third argument has been deprecated since 2.3"
		);
	}

	/**
	 * REQUEST_URI is unslashed and sanitized before it is used.
	 */
	public function test_download_page_link_handles_slashed_request_uri() {
		$_SERVER['REQUEST_URI'] = '/downloads/';
		$_GET['dl_cat']         = '1';

		$link = download_page_link( 2 );

		$this->assertStringContainsString( 'dl_page=2', $link );
		$this->assertStringNotContainsString( '<', $link );

		unset( $_GET['dl_cat'] );
	}

	/**
	 * A crafted query string cannot break out of the paging link attribute.
	 */
	public function test_download_page_link_escapes_query_args() {
		$_SERVER['REQUEST_URI'] = '/downloads/';
		$_GET['x']              = '"><script>alert(1)</script>';

		$link = download_page_link( 2 );

		$this->assertStringNotContainsString( '<script>', $link );

		unset( $_GET['x'] );
	}

	/**
	 * The category link is escaped the same way.
	 */
	public function test_download_category_url_escapes_query_args() {
		$_GET['x'] = '"><script>alert(1)</script>';

		$this->assertStringNotContainsString( '<script>', download_category_url( 1 ) );

		unset( $_GET['x'] );
	}

	/**
	 * The downloads table is registered on $wpdb->tables, not just assigned.
	 *
	 * Registering the name is what makes it survive switch_to_blog():
	 * wpdb::set_blog_id() rebuilds every registered table name against the new
	 * prefix, while a bare assignment keeps pointing at whichever site was
	 * current when the plugin file loaded.
	 */
	public function test_downloads_table_is_registered_for_multisite() {
		global $wpdb;

		$this->assertContains( 'downloads', $wpdb->tables );
	}
}
