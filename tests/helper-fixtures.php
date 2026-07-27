<?php
/**
 * Shared fixtures for the WP-DownloadManager suite.
 *
 * Every test starts from the same table contents and the same option values,
 * so the golden-master assertions in test-golden.php compare like with like.
 *
 * @package WP-DownloadManager
 */

/**
 * Base class: a clean downloads table plus a known set of files.
 */
abstract class DownloadManager_TestCase extends WP_UnitTestCase {

	/**
	 * A fixed point in time, so rendered dates never depend on "now".
	 *
	 * 2020-06-15 08:30:00 UTC.
	 *
	 * @var int
	 */
	const T0 = 1592209800;

	/**
	 * The file_id of each fixture, keyed by the shorthand the tests use.
	 *
	 * @var array
	 */
	protected $ids = array();

	/**
	 * Reset the table and the options before each test.
	 */
	public function set_up() {
		parent::set_up();

		global $wpdb;

		$table = $this->table();
		$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB

		$this->reset_options();
		$this->seed_files();
	}

	/**
	 * The downloads table name, however the plugin happens to register it.
	 *
	 * @return string
	 */
	protected function table() {
		global $wpdb;

		return isset( $wpdb->downloads ) ? $wpdb->downloads : $wpdb->prefix . 'downloads';
	}

	/**
	 * Put every option back to the value a fresh install has.
	 *
	 * Writes the consolidated row directly. The migration from the pre-2.0.0
	 * rows has its own tests, which seed the legacy names themselves.
	 */
	protected function reset_options() {
		$defaults = DownloadManager_Options::defaults();

		$defaults['categories'] = array( '', 'General', 'Software' );

		DownloadManager_Options::save( $defaults );
	}

	/**
	 * Insert the standing set of files.
	 *
	 * One per permission level so the access-control branches are all reachable,
	 * plus two in a second category so the grouping headers fire.
	 */
	protected function seed_files() {
		$this->ids = array();

		$this->ids['public']  = $this->insert_file(
			array(
				'file'            => '/manual.pdf',
				'file_name'       => 'The Manual',
				'file_des'        => 'A public manual.',
				'file_size'       => 2048,
				'file_category'   => 1,
				'file_hits'       => 12,
				'file_permission' => -1,
			)
		);
		$this->ids['members'] = $this->insert_file(
			array(
				'file'            => '/members.zip',
				'file_name'       => 'Members Bundle',
				'file_des'        => 'Registered users only.',
				'file_size'       => 1048576,
				'file_category'   => 1,
				'file_hits'       => 5,
				'file_permission' => 0,
			)
		);
		$this->ids['editors'] = $this->insert_file(
			array(
				'file'            => '/editors.doc',
				'file_name'       => 'Editor Notes',
				'file_des'        => 'Editors and up.',
				'file_size'       => 4096,
				'file_category'   => 2,
				'file_hits'       => 3,
				'file_permission' => 7,
			)
		);
		$this->ids['hidden']  = $this->insert_file(
			array(
				'file'            => '/secret.exe',
				'file_name'       => 'Hidden File',
				'file_des'        => 'Should never be listed.',
				'file_size'       => 512,
				'file_category'   => 2,
				'file_hits'       => 99,
				'file_permission' => -2,
			)
		);
		$this->ids['remote']  = $this->insert_file(
			array(
				'file'            => 'https://example.com/remote.zip',
				'file_name'       => 'Remote Bundle',
				'file_des'        => 'Lives elsewhere.',
				'file_size'       => 8192,
				'file_category'   => 2,
				'file_hits'       => 7,
				'file_permission' => -1,
			)
		);

		return $this->ids;
	}

	/**
	 * Insert one row, defaults filled in.
	 *
	 * @param array $args Column overrides.
	 * @return int Inserted file_id.
	 */
	protected function insert_file( $args = array() ) {
		global $wpdb;

		$args = array_merge(
			array(
				'file'                      => '/file.zip',
				'file_name'                 => 'File',
				'file_des'                  => '',
				'file_size'                 => 1024,
				'file_category'             => 1,
				'file_date'                 => self::T0,
				'file_updated_date'         => self::T0,
				'file_last_downloaded_date' => self::T0,
				'file_hits'                 => 0,
				'file_permission'           => -1,
			),
			$args
		);

		$wpdb->insert( $this->table(), $args ); // phpcs:ignore WordPress.DB

		return (int) $wpdb->insert_id;
	}

	/**
	 * Log in as a user of the given role.
	 *
	 * @param string $role Role name, or '' for logged out.
	 * @return int User ID, 0 when logged out.
	 */
	protected function login_as( $role ) {
		global $user_ID;

		if ( '' === $role ) {
			wp_set_current_user( 0 );
			$user_ID = 0; // phpcs:ignore WordPress.WP.GlobalVariablesOverride

			return 0;
		}

		$uid = self::factory()->user->create( array( 'role' => $role ) );
		wp_set_current_user( $uid );
		$user_ID = $uid; // phpcs:ignore WordPress.WP.GlobalVariablesOverride

		return $uid;
	}

	/**
	 * Normalise markup for comparison: collapse runs of whitespace.
	 *
	 * @param string $html Markup.
	 * @return string
	 */
	protected function squash( $html ) {
		return trim( preg_replace( '/\s+/', ' ', (string) $html ) );
	}

	/**
	 * PHP diagnostics raised by the last render_admin_page() call.
	 *
	 * @var array
	 */
	protected $admin_page_notices = array();

	/**
	 * Render one of the legacy admin pages and return its HTML.
	 *
	 * Both admin pages are procedural and run at global
	 * scope under wp-admin, so they reach for $wpdb without owning it. Requiring
	 * them from inside a method only works because this helper pulls the same
	 * globals in first; without that they would silently render half a page.
	 *
	 * Every notice, warning and deprecation raised while the page runs is
	 * collected into $admin_page_notices, so a test can assert the page is clean
	 * under PHP 8 rather than only that it produced some output.
	 *
	 * @param string $file File name relative to the plugin root.
	 * @param array  $get  $_GET for the request.
	 * @param array  $post $_POST for the request.
	 * @return string
	 */
	protected function render_admin_page( $file, $get = array(), $post = array() ) {
		global $wpdb, $wp_locale, $hook_suffix;

		$this->admin_page_notices = array();

		$_GET     = $get;
		$_POST    = $post;
		$_REQUEST = array_merge( $get, $post );

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Collecting PHP diagnostics is the point of this helper.
		set_error_handler(
			function ( $errno, $errstr, $errfile, $errline ) {
				$this->admin_page_notices[] = $errstr . ' in ' . basename( $errfile ) . ':' . $errline;
				return true;
			}
		);

		$depth = ob_get_level();
		$html  = '';

		try {
			ob_start();
			require WP_DOWNLOADMANAGER_DIR . $file;
		} finally {
			// check_admin_referer() calls wp_die(), which throws out of the
			// require, so the buffer has to be closed here or it leaks into the
			// next test and PHPUnit reports the run as risky.
			while ( ob_get_level() > $depth ) {
				$html = ob_get_clean() . $html;
			}
			restore_error_handler();
			$_GET     = array();
			$_POST    = array();
			$_REQUEST = array();
		}

		return $html;
	}

	/**
	 * Become a user who may manage downloads.
	 *
	 * The admin pages call die() when the check fails, which would take the test
	 * runner with it, so every render test has to be an administrator.
	 *
	 * @return int User ID.
	 */
	protected function become_download_admin() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		return $user_id;
	}

	/**
	 * A nonce field value for one of the admin forms.
	 *
	 * @param string $action Nonce action.
	 * @return string
	 */
	protected function nonce( $action ) {
		return wp_create_nonce( $action );
	}

	/**
	 * Create the downloads directory and put a real file in it.
	 *
	 * The write paths call filesize() and rename() against the configured
	 * download path, so they need something on disk to work with.
	 *
	 * @param string $name     File name, relative to the downloads directory.
	 * @param string $contents File contents.
	 * @return string Absolute path.
	 */
	protected function make_download_file( $name = 'sample.txt', $contents = 'sample' ) {
		$dir = DownloadManager_Options::get( 'path.dir' );

		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		$path = trailingslashit( $dir ) . ltrim( $name, '/' );
		wp_mkdir_p( dirname( $path ) );
		file_put_contents( $path, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		return $path;
	}

	/**
	 * Remove the downloads directory created by make_download_file().
	 *
	 * @return void
	 */
	protected function remove_download_files() {
		$dir = DownloadManager_Options::get( 'path.dir' );

		if ( ! is_dir( $dir ) ) {
			return;
		}

		$items = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $items as $item ) {
			if ( $item->isDir() ) {
				rmdir( $item->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			} else {
				wp_delete_file( $item->getPathname() );
			}
		}

		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	/**
	 * A plugin source file with its comments removed.
	 *
	 * The source-level guards below assert that a removed API is no longer
	 * called. Grepping the raw file cannot tell a live call from a comment
	 * explaining why the call is gone, and every one of those guards has a
	 * comment beside it doing exactly that. Tokenising first means the
	 * assertions look only at code.
	 *
	 * @param string $file File name relative to the plugin root.
	 * @return string
	 */
	protected function code( $file ) {
		$source = file_get_contents( dirname( __DIR__ ) . '/' . $file );
		$out    = '';

		foreach ( token_get_all( $source ) as $token ) {
			if ( is_array( $token ) ) {
				if ( in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
					continue;
				}
				$out .= $token[1];
			} else {
				$out .= $token;
			}
		}

		return $out;
	}
}
