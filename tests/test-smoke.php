<?php
/**
 * Smoke test: the plugin loaded, the table exists, the fixtures are in it.
 *
 * @package WP-DownloadManager
 */

/**
 * Minimal checks that the harness itself works.
 */
class Test_Smoke extends DownloadManager_TestCase {

	/**
	 * The downloads table was created and registered on $wpdb.
	 */
	public function test_table_registered() {
		global $wpdb;

		$this->assertNotEmpty( $wpdb->downloads, '$wpdb->downloads should be set' );
		$this->assertSame(
			$wpdb->downloads,
			$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->downloads ) )
		);
	}

	/**
	 * The fixtures landed.
	 */
	public function test_fixtures_seeded() {
		global $wpdb;

		$this->assertSame(
			5,
			(int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->downloads}" ) // phpcs:ignore WordPress.DB
		);
	}
}
