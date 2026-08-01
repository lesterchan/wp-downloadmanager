<?php
/**
 * The base test case for the WP-DownloadManager suite.
 *
 * Every test starts from the same table contents and the same option values, so
 * two tests never see each other's downloads and an assertion about rendered
 * markup compares like with like.
 *
 * @package WP-DownloadManager
 */

/**
 * A clean downloads table, known settings, and the helpers the suite shares.
 */
abstract class WP_DownloadManager_TestCase extends WP_UnitTestCase {

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
	 * Reset the table, the options and the sprite before each test.
	 */
	public function set_up() {
		parent::set_up();

		global $wpdb;

		$table = $this->table();
		$wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', $table ) );

		$this->reset_options();
		WP_DownloadManager_Display::reset_sprite();
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
		$defaults = WP_DownloadManager_Options::defaults();

		$defaults['categories'] = array( '', 'General', 'Software' );

		WP_DownloadManager_Options::save( $defaults );
		WP_DownloadManager_Options::save_markers( WP_DOWNLOADMANAGER_VERSION, WP_DOWNLOADMANAGER_DB_VERSION );
	}

	/**
	 * Insert the standing set of files.
	 *
	 * One per permission level so the access-control branches are all reachable,
	 * plus two in a second category so the grouping headers fire.
	 *
	 * @return array
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

		$wpdb->insert( $this->table(), $args );

		return (int) $wpdb->insert_id;
	}

	/**
	 * One row back out of the table.
	 *
	 * @param int $file_id File ID.
	 * @return object|null
	 */
	protected function fetch_file( $file_id ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->downloads} WHERE file_id = %d", (int) $file_id ) );
	}

	/**
	 * How many rows the table holds.
	 *
	 * @return int
	 */
	protected function count_files() {
		global $wpdb;

		return (int) $wpdb->get_var( "SELECT COUNT(file_id) FROM {$wpdb->downloads}" );
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
			$user_ID = 0;

			return 0;
		}

		$uid = self::factory()->user->create( array( 'role' => $role ) );
		wp_set_current_user( $uid );
		$user_ID = $uid;

		return $uid;
	}

	/**
	 * Stand in for wp-admin having routed to one of the plugin's screens.
	 *
	 * WP_List_Table's constructor reaches WP_Screen::get(), and its bulk-action
	 * and column filters are named after the screen id, so a list-table test
	 * that skips this is testing something wp-admin would never produce.
	 *
	 * @param string $screen Screen id.
	 * @return void
	 */
	protected function on_admin_screen( $screen = 'toplevel_page_wp-downloadmanager' ) {
		global $hook_suffix;

		$hook_suffix = $screen;
		set_current_screen( $screen );
	}

	/**
	 * Become a user who may manage downloads.
	 *
	 * The screens call wp_die() when the capability check fails, which would
	 * take the test runner with it, so every render test has to be one.
	 *
	 * @return int User ID.
	 */
	protected function become_download_admin() {
		return $this->login_as( 'administrator' );
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
	 * PHP diagnostics raised by the last render() call.
	 *
	 * @var array
	 */
	protected $notices = array();

	/**
	 * Run a callable with $_GET and $_POST set, and capture what it prints.
	 *
	 * Every notice, warning and deprecation raised while it runs is collected
	 * into $notices, so a test can assert the screen is clean under current PHP
	 * rather than only that it produced some output.
	 *
	 * @param callable $callback Screen renderer.
	 * @param array    $get      $_GET for the request.
	 * @param array    $post     $_POST for the request.
	 * @return string
	 */
	protected function render( $callback, $get = array(), $post = array() ) {
		global $hook_suffix;

		if ( ! isset( $hook_suffix ) ) {
			// wp-admin sets this before any page callback runs; WP_List_Table's
			// constructor reaches WP_Screen::get(), which reads it.
			$hook_suffix = '';
		}

		$this->notices = array();

		// add_settings_error() writes into a global no transaction rolls back, so
		// without this every screen renders the notices of every screen rendered
		// before it, and an assertion about what this one said is answered by an
		// earlier one.
		$GLOBALS['wp_settings_errors'] = array();

		$_GET     = $get;
		$_POST    = $post;
		$_REQUEST = array_merge( $get, $post );

		set_error_handler(
			function ( $errno, $errstr, $errfile, $errline ) {
				$this->notices[] = $errstr . ' in ' . basename( $errfile ) . ':' . $errline;
				return true;
			}
		);

		$depth = ob_get_level();
		$html  = '';

		try {
			ob_start();
			call_user_func( $callback );
		} finally {
			// check_admin_referer() calls wp_die(), which throws out of the
			// callback, so the buffer has to be closed here or it leaks into the
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
	 * Assert that the last render() raised nothing PHP would complain about.
	 *
	 * @param string $html Markup the screen produced.
	 * @return void
	 */
	protected function assertScreenIsClean( $html ) {
		$this->assertSame( array(), $this->notices, 'the screen raised PHP diagnostics: ' . implode( '; ', $this->notices ) );
		$this->assertStringNotContainsString( 'Warning', $html, 'the screen printed a warning' );
		$this->assertStringNotContainsString( 'Undefined', $html, 'the screen printed an undefined-key notice' );
		$this->assertStringNotContainsString( 'translators:', $html, 'a translator comment leaked into the markup' );
	}

	/**
	 * A nonce value for one of the admin forms.
	 *
	 * @param string $action Nonce action.
	 * @return string
	 */
	protected function nonce( $action ) {
		return wp_create_nonce( $action );
	}

	/**
	 * The WordPress filesystem abstraction, initialised for direct access.
	 *
	 * The tests write scratch files and then take the directories away again.
	 * Going through WP_Filesystem rather than the raw PHP functions is not
	 * ceremony here: it is the same API the plugin would have to use, and it
	 * means the suite needs no coding-standard exemption that includes/ does not
	 * also get.
	 *
	 * @return WP_Filesystem_Base
	 */
	protected function filesystem() {
		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		return $wp_filesystem;
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
		$dir = WP_DownloadManager_Options::get( 'path.dir' );

		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		$path = trailingslashit( $dir ) . ltrim( $name, '/' );
		wp_mkdir_p( dirname( $path ) );
		$this->filesystem()->put_contents( $path, $contents );

		return $path;
	}

	/**
	 * Remove a directory and everything under it.
	 *
	 * @param string $dir Absolute path.
	 * @return void
	 */
	protected function remove_directory( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$this->filesystem()->rmdir( $dir, true );
	}

	/**
	 * Remove the downloads directory created by make_download_file().
	 *
	 * @return void
	 */
	protected function remove_download_files() {
		$this->remove_directory( WP_DownloadManager_Options::get( 'path.dir' ) );
	}

	/**
	 * Delete the option rows uninstall.php deletes, and nothing else.
	 *
	 * The uninstaller cannot simply be required here: it drops the downloads
	 * table that the rest of the suite runs against, and because the include
	 * would only ever execute once, a second test file asking for it would
	 * silently prove nothing. The deletions it performs are therefore repeated
	 * here, read from the same lists on the options class so the two can never
	 * disagree, and WP_DownloadManager_Uninstall_Test asserts separately that
	 * uninstall.php reads those lists and delegates the drop to the install
	 * class.
	 *
	 * @return void
	 */
	protected function run_uninstall() {
		$option_names = array_merge(
			array_keys( WP_DownloadManager_Options::legacy_map() ),
			array_values( WP_DownloadManager_Options::legacy_structured_rows() ),
			WP_DownloadManager_Options::legacy_extra_rows(),
			array(
				WP_DownloadManager_Options::OPTION,
				WP_DownloadManager_Options::VERSION,
				'widget_downloads',
			)
		);

		foreach ( $option_names as $option_name ) {
			delete_option( $option_name );
		}
	}

	/**
	 * A plugin source file with its comments removed.
	 *
	 * The source-level guards in this suite assert that a removed API is no
	 * longer called. Grepping the raw file cannot tell a live call from a
	 * comment explaining why the call is gone, and every one of those guards has
	 * a comment beside it doing exactly that. Tokenising first means the
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

	/**
	 * Every shipped PHP file, so a guard can sweep the whole plugin.
	 *
	 * @return array Paths relative to the plugin root.
	 */
	protected function plugin_php_files() {
		$root  = dirname( __DIR__ );
		$files = array( 'wp-downloadmanager.php', 'uninstall.php', 'index.php' );

		foreach ( (array) glob( $root . '/includes/*.php' ) as $path ) {
			$files[] = 'includes/' . basename( $path );
		}

		return $files;
	}
}
