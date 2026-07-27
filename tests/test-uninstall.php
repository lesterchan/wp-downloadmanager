<?php
/**
 * uninstall.php.
 *
 * The destructive path runs once, in a single test: WordPress's test framework
 * rewrites CREATE/DROP TABLE into their TEMPORARY equivalents, so the physical
 * drop cannot be observed from inside the suite and the queries the uninstaller
 * issues are asserted instead.
 *
 * The multisite branch cannot be exercised here at all - you cannot build a
 * 101-site network from a single-site suite - so the three bugs that lived in
 * it are pinned at source level, against the comment-stripped file so that a
 * docblock explaining a fix cannot satisfy the assertion.
 *
 * @package WP-DownloadManager
 */

/**
 * Option and table cleanup.
 */
class Test_Uninstall extends DownloadManager_TestCase {

	/**
	 * Put the option row back for whatever runs next.
	 *
	 * The table needs no rebuilding: see the note in the destructive test - the
	 * framework rewrites the drop, so the real table is never actually removed.
	 */
	public function tear_down() {
		DownloadManager_Install::create_table();
		DownloadManager_Options::flush();

		parent::tear_down();
	}

	/**
	 * Run uninstall.php the way WordPress does.
	 *
	 * @return void
	 */
	private function run_uninstall() {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'wp-downloadmanager/wp-downloadmanager.php' );
		}

		require WP_DOWNLOADMANAGER_DIR . 'uninstall.php';
	}

	/**
	 * The one destructive run: everything the uninstaller is supposed to remove.
	 *
	 * Deliberately a single test rather than one per row. Dropping a table
	 * implicitly commits, which leaves the WordPress test framework's
	 * per-test transaction unwound - so a second uninstall in the same process
	 * silently does nothing, and splitting these up gives four passing tests
	 * and one mystifying failure. Asserting the whole outcome once is both
	 * accurate and faster.
	 */
	public function test_uninstall_removes_everything_it_owns() {
		global $wpdb;

		// Settings, the version marker, the widget, and leftovers from an
		// install that never reached the migration.
		DownloadManager_Options::set( 'page_url', 'https://example.com/downloads' );
		update_option( DownloadManager_Install::DB_VERSION_OPTION, DownloadManager_Install::DB_VERSION );
		update_option( 'widget_downloads', array( 'anything' ) );

		$legacy = array_merge(
			array_keys( DownloadManager_Options::legacy_map() ),
			DownloadManager_Options::legacy_extra_rows()
		);
		foreach ( $legacy as $option ) {
			update_option( $option, 'left over' );
		}

		// Things belonging to other people, which must survive.
		update_option( 'blogname', 'Keep Me' );
		update_option( 'download_unrelated_third_party', 'keep' );

		// The physical drop cannot be asserted from inside the suite: WordPress's
		// test framework filters "query" to rewrite CREATE/DROP TABLE into their
		// TEMPORARY equivalents, so DROP TABLE IF EXISTS on the real table
		// returns true, raises no error, and leaves it standing. What is both
		// stable and actually meaningful is that the uninstaller issues the drop
		// for its own table, so that is what this captures.
		$queries = array();
		$spy     = static function ( $query ) use ( &$queries ) {
			$queries[] = $query;
			return $query;
		};
		add_filter( 'query', $spy );

		$this->run_uninstall();

		remove_filter( 'query', $spy );

		$drops = array_values(
			array_filter(
				$queries,
				static function ( $query ) {
					// WordPress's test framework rewrites DROP TABLE into DROP
					// TEMPORARY TABLE, so match both shapes.
					return 1 === preg_match( '/^\s*DROP\s+(TEMPORARY\s+)?TABLE/i', $query );
				}
			)
		);

		$this->assertCount( 1, $drops, 'the table should be dropped exactly once' );
		$this->assertStringContainsString( $wpdb->prefix . 'downloads', $drops[0] );
		$this->assertStringContainsString( 'IF EXISTS', $drops[0] );

		$this->assertFalse( get_option( DownloadManager_Options::OPTION, false ) );
		$this->assertFalse( get_option( DownloadManager_Install::DB_VERSION_OPTION, false ) );
		$this->assertFalse( get_option( 'widget_downloads', false ) );

		foreach ( $legacy as $option ) {
			$this->assertFalse( get_option( $option, false ), $option . ' should be gone' );
		}

		$this->assertSame( 'Keep Me', get_option( 'blogname' ) );
		$this->assertSame( 'keep', get_option( 'download_unrelated_third_party' ) );

		delete_option( 'download_unrelated_third_party' );
	}

	/**
	 * The row list is derived from the options class rather than duplicated.
	 *
	 * A second hand-maintained list is how a newly added option ends up
	 * orphaned on uninstall forever.
	 */
	public function test_row_list_comes_from_the_options_class() {
		$source = $this->code( 'uninstall.php' );

		$this->assertStringContainsString( 'DownloadManager_Options::legacy_map()', $source );
		$this->assertStringContainsString( 'DownloadManager_Options::legacy_extra_rows()', $source );
		$this->assertStringContainsString( 'DownloadManager_Options::OPTION', $source );
	}

	/**
	 * The uninstaller refuses to run outside an uninstall.
	 */
	public function test_guarded_by_the_uninstall_constant() {
		$this->assertStringContainsString( "defined( 'WP_UNINSTALL_PLUGIN' )", $this->code( 'uninstall.php' ) );
	}

	/**
	 * Every site on a network is visited.
	 *
	 * get_sites() defaults 'number' to 100, so without this a network larger
	 * than that silently keeps its options and tables on every site past the
	 * hundredth while uninstall still reports success. Asserted at source level
	 * because a single-site suite cannot build the network to prove it.
	 */
	public function test_multisite_loop_visits_every_site() {
		$source = $this->code( 'uninstall.php' );

		$this->assertMatchesRegularExpression( "/'number'\s*=>\s*0/", $source );
		$this->assertMatchesRegularExpression( "/'fields'\s*=>\s*'ids'/", $source );
	}

	/**
	 * Each switch is restored, inside the loop.
	 */
	public function test_multisite_loop_restores_each_blog() {
		$source = $this->code( 'uninstall.php' );

		$this->assertSame(
			substr_count( $source, 'switch_to_blog' ),
			substr_count( $source, 'restore_current_blog' ),
			'every switch_to_blog() needs its own restore_current_blog()'
		);
	}

	/**
	 * The function core removed in WP 5.1 is not called.
	 */
	public function test_does_not_call_wp_get_sites() {
		$this->assertStringNotContainsString( 'wp_get_sites', $this->code( 'uninstall.php' ) );
	}

	/**
	 * The table is dropped once, not once per option row.
	 */
	public function test_table_is_dropped_once() {
		$source = $this->code( 'uninstall.php' );

		$this->assertSame(
			1,
			substr_count( $source, 'DROP TABLE' ),
			'the drop belongs outside the option loop'
		);
	}
}
