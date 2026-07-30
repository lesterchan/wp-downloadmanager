<?php
/**
 * Admin wiring for WP-DownloadManager.
 *
 * @package WP-DownloadManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * The Downloads menu, its assets and the editor buttons.
 */
class WP_DownloadManager_Admin {

	/**
	 * Slug of the top-level menu, and of the first screen under it.
	 *
	 * @var string
	 */
	const PAGE = 'wp-downloadmanager';

	/**
	 * Capability guarding the data screens.
	 *
	 * The plugin has granted administrators manage_downloads since its first
	 * release, which is what lets a site hand the downloads library to an editor
	 * without handing over the whole dashboard. Settings stay on manage_options
	 * per section 2.7; capability() is where the two are told apart.
	 *
	 * @var string
	 */
	const CAPABILITY = 'manage_downloads';

	/**
	 * Rows the downloads list shows before paging.
	 *
	 * @var int
	 */
	const PER_PAGE = 20;

	/**
	 * The capability required to reach one of the plugin's screens.
	 *
	 * @param string $context Screen context: 'downloads' or 'settings'.
	 * @return string
	 */
	public static function capability( $context = 'downloads' ) {
		/**
		 * Filters the capability a WP-DownloadManager screen requires.
		 *
		 * @since 2.0.0
		 *
		 * @param string $capability Capability name.
		 * @param string $context    Screen context: 'downloads' or 'settings'.
		 */
		return apply_filters(
			'wp_downloadmanager_capability',
			'settings' === $context ? 'manage_options' : self::CAPABILITY,
			$context
		);
	}

	/**
	 * Hook up.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'init', array( __CLASS__, 'editor_buttons' ) );
		add_filter( 'set-screen-option', array( __CLASS__, 'save_screen_option' ), 10, 3 );

		foreach ( array( 'post-new.php', 'post.php', 'page-new.php', 'page.php' ) as $screen ) {
			add_action( 'admin_footer-' . $screen, array( __CLASS__, 'quicktag' ) );
		}
	}

	/**
	 * Load the list table, and WP_List_Table under it.
	 *
	 * Not required from the plugin file: WP_List_Table lives in wp-admin and
	 * pulls in a screen object with it, so loading it on every front-end request
	 * would be a cost paid by every visitor for a class only three admin screens
	 * ever touch.
	 *
	 * @return void
	 */
	public static function load_list_table() {
		if ( ! class_exists( 'WP_List_Table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		}

		require_once WP_DOWNLOADMANAGER_DIR . 'includes/class-wp-downloadmanager-list-table.php';
	}

	/**
	 * The URL of one of the plugin's admin screens.
	 *
	 * Every link on every screen comes from here, so the menu slugs are written
	 * down once rather than reassembled by hand at each call site - which is how
	 * the old screens ended up linking at a download-uninstall.php that had not
	 * existed for years.
	 *
	 * @param string $action  Screen action: '', 'edit', 'delete', 'add' or 'settings'.
	 * @param int    $file_id File the action applies to.
	 * @return string
	 */
	public static function screen_url( $action = '', $file_id = 0 ) {
		$args = array( 'page' => self::PAGE );

		$screens = self::screens();

		if ( 'add' === $action ) {
			$args['page'] = $screens['add'];
		} elseif ( 'settings' === $action ) {
			$args['page'] = $screens['settings'];
		} elseif ( '' !== $action ) {
			$args['action'] = $action;
		}

		if ( $file_id ) {
			$args['id'] = (int) $file_id;
		}

		$url = add_query_arg( $args, admin_url( 'admin.php' ) );

		return in_array( $action, array( 'edit', 'delete' ), true )
			? wp_nonce_url( $url, 'wp_downloadmanager_' . $action . '_' . (int) $file_id )
			: $url;
	}

	/**
	 * Register the Downloads menu.
	 *
	 * One top-level menu, the data screen first and Settings last, exactly as
	 * section 4.1 lays down. Download Options and Download Templates used to be
	 * two more entries in this list; they are two tabs on the one settings page
	 * now.
	 *
	 * @return void
	 */
	public static function menu() {
		$capability = self::capability();

		add_menu_page(
			__( 'Downloads', 'wp-downloadmanager' ),
			__( 'WP-DownloadManager', 'wp-downloadmanager' ),
			$capability,
			self::PAGE,
			array( __CLASS__, 'render_downloads' ),
			'dashicons-download'
		);

		$hook = add_submenu_page(
			self::PAGE,
			__( 'Downloads', 'wp-downloadmanager' ),
			__( 'Downloads', 'wp-downloadmanager' ),
			$capability,
			self::PAGE,
			array( __CLASS__, 'render_downloads' )
		);

		// Registering the per-page option on load, not on render: core reads it
		// while building the Screen Options tab, which happens first.
		if ( $hook ) {
			add_action( 'load-' . $hook, array( __CLASS__, 'add_screen_options' ) );
		}

		add_submenu_page(
			self::PAGE,
			__( 'Add File', 'wp-downloadmanager' ),
			__( 'Add File', 'wp-downloadmanager' ),
			$capability,
			self::screens()['add'],
			array( __CLASS__, 'render_add' )
		);

		add_submenu_page(
			self::PAGE,
			__( 'Settings', 'wp-downloadmanager' ),
			__( 'Settings', 'wp-downloadmanager' ),
			self::capability( 'settings' ),
			self::screens()['settings'],
			array( 'WP_DownloadManager_Settings', 'render_page' )
		);
	}

	/**
	 * The Screen Options tab on the downloads list.
	 *
	 * @return void
	 */
	public static function add_screen_options() {
		add_screen_option(
			'per_page',
			array(
				'label'   => __( 'Downloads per page', 'wp-downloadmanager' ),
				'default' => self::PER_PAGE,
				'option'  => 'wp_downloadmanager_per_page',
			)
		);
	}

	/**
	 * Keep a per-user Screen Options value rather than discarding it.
	 *
	 * @param mixed  $status Value core would store, false by default.
	 * @param string $option Option being saved.
	 * @param mixed  $value  Submitted value.
	 * @return mixed
	 */
	public static function save_screen_option( $status, $option, $value ) {
		return 'wp_downloadmanager_per_page' === $option ? (int) $value : $status;
	}

	/**
	 * The downloads screen, in whichever mode the request asked for.
	 *
	 * @return void
	 */
	public static function render_downloads() {
		self::require_capability();

		$action  = self::request( 'action' );
		$file_id = (int) self::request( 'id' );

		if ( 'edit' === $action && $file_id ) {
			self::render_edit( $file_id );
			return;
		}

		if ( 'delete' === $action && $file_id ) {
			self::render_delete( $file_id );
			return;
		}

		self::render_list();
	}

	/**
	 * The list of files, with the library totals below it.
	 *
	 * @return void
	 */
	public static function render_list() {
		self::load_list_table();
		self::handle_bulk_delete();

		$table = new WP_DownloadManager_List_Table();
		$table->prepare_items();
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Downloads', 'wp-downloadmanager' ); ?></h1>
			<a href="<?php echo esc_url( self::screen_url( 'add' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add File', 'wp-downloadmanager' ); ?></a>
			<hr class="wp-header-end" />
			<?php settings_errors( 'wp_downloadmanager' ); ?>
			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>" />
				<?php
				$table->search_box( __( 'Search Files', 'wp-downloadmanager' ), 'wp-downloadmanager-search' );
				// No wp_nonce_field() here: $table->display() emits the bulk nonce
				// this form is checked against, and a second _wpnonce input would
				// override it. See WP_DownloadManager_List_Table::bulk_nonce_action().
				$table->display();
				?>
			</form>
			<h2><?php esc_html_e( 'Download Stats', 'wp-downloadmanager' ); ?></h2>
			<?php self::render_totals(); ?>
		</div>
		<?php
	}

	/**
	 * Files, size, hits and bandwidth across the whole library.
	 *
	 * @return void
	 */
	protected static function render_totals() {
		global $wpdb;

		$unknown = __( 'unknown', 'wp-downloadmanager' );

		// Cast: SUM() is NULL on an empty table, and number_format_i18n( null )
		// is deprecated on PHP 8.1 and later.
		$totals = array(
			__( 'Total Files', 'wp-downloadmanager' )     => number_format_i18n( (int) $wpdb->get_var( "SELECT COUNT(file_id) FROM {$wpdb->downloads}" ) ),
			__( 'Total Size', 'wp-downloadmanager' )      => WP_DownloadManager_File::format_size(
				(int) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(file_size) FROM {$wpdb->downloads} WHERE file_size != %s", $unknown ) )
			),
			__( 'Total Hits', 'wp-downloadmanager' )      => number_format_i18n( (int) $wpdb->get_var( "SELECT SUM(file_hits) FROM {$wpdb->downloads}" ) ),
			__( 'Total Bandwidth', 'wp-downloadmanager' ) => WP_DownloadManager_File::format_size(
				(int) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(file_hits*file_size) FROM {$wpdb->downloads} WHERE file_size != %s", $unknown ) )
			),
		);
		?>
		<table class="widefat striped">
			<tbody>
				<?php foreach ( $totals as $label => $value ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $label ); ?></th>
						<td><?php echo esc_html( $value ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * The Add File screen.
	 *
	 * @return void
	 */
	public static function render_add() {
		self::require_capability();
		self::handle_add();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Add File', 'wp-downloadmanager' ); ?></h1>
			<?php settings_errors( 'wp_downloadmanager' ); ?>
			<form method="post" action="<?php echo esc_url( self::screen_url( 'add' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo esc_attr( WP_DownloadManager_File::max_upload_size() ); ?>" />
				<?php
				wp_nonce_field( 'wp_downloadmanager_add' );
				self::render_file_form( null );
				submit_button( __( 'Add File', 'wp-downloadmanager' ), 'primary', 'do' );
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * The Edit File screen.
	 *
	 * @param int $file_id File to edit.
	 * @return void
	 */
	public static function render_edit( $file_id ) {
		self::handle_edit( $file_id );

		$file = self::get_file( $file_id );

		if ( ! $file ) {
			self::render_missing();
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Edit File', 'wp-downloadmanager' ); ?></h1>
			<?php settings_errors( 'wp_downloadmanager' ); ?>
			<form method="post" action="<?php echo esc_url( self::screen_url( 'edit', $file->file_id ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo esc_attr( WP_DownloadManager_File::max_upload_size() ); ?>" />
				<input type="hidden" name="file_id" value="<?php echo (int) $file->file_id; ?>" />
				<input type="hidden" name="old_file" value="<?php echo esc_attr( stripslashes( $file->file ) ); ?>" />
				<?php
				wp_nonce_field( 'wp_downloadmanager_edit_' . (int) $file->file_id );
				self::render_file_form( $file );
				submit_button( __( 'Edit File', 'wp-downloadmanager' ), 'primary', 'do' );
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * The Delete File confirmation screen.
	 *
	 * @param int $file_id File to delete.
	 * @return void
	 */
	public static function render_delete( $file_id ) {
		if ( self::handle_delete( $file_id ) ) {
			// Deleted, so there is nothing left to confirm. Falling through to
			// the list rather than redirecting keeps the notice, and keeps this
			// screen reachable from a test without an exit() in the middle of it.
			self::render_list();
			return;
		}

		$file = self::get_file( $file_id );

		if ( ! $file ) {
			self::render_missing();
			return;
		}

		$categories = (array) WP_DownloadManager_Options::get( 'categories', array() );
		$rows       = array(
			__( 'File', 'wp-downloadmanager' )          => stripslashes( $file->file ),
			__( 'File Name', 'wp-downloadmanager' )     => wp_strip_all_tags( stripslashes( $file->file_name ) ),
			__( 'File Category', 'wp-downloadmanager' ) => WP_DownloadManager_Display::category_name( $categories, $file->file_category ),
			__( 'File Size', 'wp-downloadmanager' )     => WP_DownloadManager_File::format_size( $file->file_size ),
			__( 'File Hits', 'wp-downloadmanager' )     => number_format_i18n( (int) $file->file_hits ),
			__( 'Allowed To Download', 'wp-downloadmanager' ) => WP_DownloadManager_File::permission_label( $file->file_permission ),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Delete File', 'wp-downloadmanager' ); ?></h1>
			<?php settings_errors( 'wp_downloadmanager' ); ?>
			<form method="post" action="<?php echo esc_url( self::screen_url( 'delete', $file->file_id ) ); ?>">
				<input type="hidden" name="file_id" value="<?php echo (int) $file->file_id; ?>" />
				<?php wp_nonce_field( 'wp_downloadmanager_delete_' . (int) $file->file_id ); ?>
				<table class="widefat striped">
					<tbody>
						<?php foreach ( $rows as $label => $value ) : ?>
							<tr>
								<th scope="row"><?php echo esc_html( $label ); ?></th>
								<td><?php echo esc_html( $value ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php if ( ! WP_DownloadManager_File::is_remote( stripslashes( $file->file ) ) ) : ?>
					<p>
						<input type="checkbox" id="unlinkfile" name="unlinkfile" value="1" />
						<label for="unlinkfile"><?php esc_html_e( 'Delete the file from the server as well', 'wp-downloadmanager' ); ?></label>
					</p>
				<?php endif; ?>
				<p class="submit">
					<button type="submit" name="do" value="delete" class="button button-primary"
						data-confirm="<?php echo esc_attr( __( 'This cannot be undone. Delete this file?', 'wp-downloadmanager' ) ); ?>">
						<?php esc_html_e( 'Delete File', 'wp-downloadmanager' ); ?>
					</button>
					<a class="button" href="<?php echo esc_url( self::screen_url() ); ?>"><?php esc_html_e( 'Cancel', 'wp-downloadmanager' ); ?></a>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Shown when an edit or delete link names a row that is no longer there.
	 *
	 * @return void
	 */
	protected static function render_missing() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Downloads', 'wp-downloadmanager' ); ?></h1>
			<div class="notice notice-error"><p><?php esc_html_e( 'That file no longer exists.', 'wp-downloadmanager' ); ?></p></div>
			<p><a class="button" href="<?php echo esc_url( self::screen_url() ); ?>"><?php esc_html_e( 'Back to Downloads', 'wp-downloadmanager' ); ?></a></p>
		</div>
		<?php
	}

	/**
	 * The fields shared by Add File and Edit File.
	 *
	 * @param object|null $file Row being edited, or null when adding.
	 * @return void
	 */
	protected static function render_file_form( $file ) {
		$file_path  = WP_DownloadManager_Options::get( 'path.dir' );
		$categories = (array) WP_DownloadManager_Options::get( 'categories', array() );
		$timestamp  = $file ? (int) $file->file_date : WP_DownloadManager_File::now();
		?>
		<table class="form-table" role="presentation">
			<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'File', 'wp-downloadmanager' ); ?></th>
				<td>
					<?php if ( $file ) : ?>
						<p>
							<input type="radio" id="file_type_-1" name="file_type" value="-1" checked="checked" />
							<label for="file_type_-1"><?php esc_html_e( 'Current file:', 'wp-downloadmanager' ); ?> <code dir="ltr"><?php echo esc_html( stripslashes( $file->file ) ); ?></code></label>
						</p>
					<?php endif; ?>
					<p>
						<input type="radio" id="file_type_0" name="file_type" value="0" <?php checked( ! $file ); ?> />
						<label for="file_type_0"><?php esc_html_e( 'Browse file:', 'wp-downloadmanager' ); ?></label>
						<select name="file" data-checks="file_type_0" dir="ltr">
							<?php self::print_files( $file_path, $file_path, $file ? stripslashes( $file->file ) : '' ); ?>
						</select>
						<span class="description">
							<?php
							/* translators: %s: the downloads directory. */
							printf( esc_html__( 'Upload the file to %s first.', 'wp-downloadmanager' ), '<code dir="ltr">' . esc_html( $file_path ) . '</code>' );
							?>
						</span>
					</p>
					<p>
						<input type="radio" id="file_type_1" name="file_type" value="1" />
						<label for="file_type_1"><?php esc_html_e( 'Upload file:', 'wp-downloadmanager' ); ?></label>
						<input type="file" name="file_upload" data-checks="file_type_1" dir="ltr" />
						<label for="file_upload_to"><?php esc_html_e( 'to', 'wp-downloadmanager' ); ?></label>
						<select id="file_upload_to" name="file_upload_to" data-checks="file_type_1" dir="ltr">
							<?php self::print_folders( $file_path, $file_path ); ?>
						</select>
						<span class="description">
							<?php
							/* translators: %s: the maximum upload size. */
							printf( esc_html__( 'Maximum file size is %s.', 'wp-downloadmanager' ), esc_html( WP_DownloadManager_File::format_size( WP_DownloadManager_File::max_upload_size() ) ) );
							?>
						</span>
					</p>
					<p>
						<input type="radio" id="file_type_2" name="file_type" value="2" />
						<label for="file_type_2"><?php esc_html_e( 'Remote file:', 'wp-downloadmanager' ); ?></label>
						<input type="url" class="regular-text" name="file_remote" maxlength="255" data-checks="file_type_2" value="https://" dir="ltr" />
						<span class="description"><?php esc_html_e( 'Include https:// or ftp:// in front.', 'wp-downloadmanager' ); ?></span>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="file_name"><?php esc_html_e( 'File Name', 'wp-downloadmanager' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="file_name" name="file_name" maxlength="200" value="<?php echo $file ? esc_attr( stripslashes( $file->file_name ) ) : ''; ?>" />
					<p class="description"><?php esc_html_e( 'Leave blank to use the file name on disk.', 'wp-downloadmanager' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="file_des"><?php esc_html_e( 'File Description', 'wp-downloadmanager' ); ?></label></th>
				<td><textarea class="large-text" rows="5" id="file_des" name="file_des"><?php echo $file ? esc_textarea( stripslashes( $file->file_des ) ) : ''; ?></textarea></td>
			</tr>
			<tr>
				<th scope="row"><label for="file_cat"><?php esc_html_e( 'File Category', 'wp-downloadmanager' ); ?></label></th>
				<td>
					<select id="file_cat" name="file_cat">
						<?php foreach ( $categories as $index => $category ) : ?>
							<?php if ( '' !== trim( (string) $category ) ) : ?>
								<option value="<?php echo (int) $index; ?>" <?php selected( $file ? (int) $file->file_category : 0, (int) $index ); ?>><?php echo esc_html( $category ); ?></option>
							<?php endif; ?>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="file_size"><?php esc_html_e( 'File Size', 'wp-downloadmanager' ); ?></label></th>
				<td>
					<input type="number" class="small-text" id="file_size" name="file_size" min="0" value="<?php echo $file ? (int) $file->file_size : ''; ?>" />
					<?php esc_html_e( 'bytes', 'wp-downloadmanager' ); ?>
					<p>
						<input type="checkbox" id="auto_filesize" name="auto_filesize" value="1" checked="checked" />
						<label for="auto_filesize"><?php esc_html_e( 'Detect the file size automatically', 'wp-downloadmanager' ); ?></label>
					</p>
					<p class="description"><?php esc_html_e( 'Automatic detection sometimes fails for a remote file.', 'wp-downloadmanager' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'File Date', 'wp-downloadmanager' ); ?></th>
				<td>
					<?php self::file_timestamp( $timestamp ); ?>
					<?php if ( $file ) : ?>
						<p>
							<input type="checkbox" id="edit_filetimestamp" name="edit_filetimestamp" value="1" />
							<label for="edit_filetimestamp"><?php esc_html_e( 'Save this date', 'wp-downloadmanager' ); ?></label>
						</p>
						<p>
							<input type="checkbox" id="edit_usetodaydate" value="1"
								data-actual="<?php echo esc_attr( wp_json_encode( self::date_parts( (int) $file->file_date ) ) ); ?>"
								data-today="<?php echo esc_attr( wp_json_encode( self::date_parts( WP_DownloadManager_File::now() ) ) ); ?>" />
							<label for="edit_usetodaydate"><?php esc_html_e( 'Use today\'s date', 'wp-downloadmanager' ); ?></label>
						</p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="file_hits"><?php esc_html_e( 'File Hits', 'wp-downloadmanager' ); ?></label></th>
				<td>
					<input type="number" class="small-text" id="file_hits" name="file_hits" min="0" value="<?php echo $file ? (int) $file->file_hits : 0; ?>" />
					<?php if ( $file ) : ?>
						<p>
							<input type="checkbox" id="reset_filehits" name="reset_filehits" value="1" />
							<label for="reset_filehits"><?php esc_html_e( 'Reset the hit count to zero', 'wp-downloadmanager' ); ?></label>
						</p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="file_permission"><?php esc_html_e( 'Allowed To Download', 'wp-downloadmanager' ); ?></label></th>
				<td>
					<select id="file_permission" name="file_permission">
						<?php foreach ( array( -2, -1, 0, 1, 2, 7, 10 ) as $permission ) : ?>
							<option value="<?php echo (int) $permission; ?>" <?php selected( $file ? (int) $file->file_permission : -1, $permission ); ?>>
								<?php echo esc_html( WP_DownloadManager_File::permission_label( $permission ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Role-based access is enforced by the download link, but anyone who guesses the file\'s own URL can still reach it.', 'wp-downloadmanager' ); ?></p>
				</td>
			</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * A timestamp split into the parts the date selects hold.
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return array
	 */
	protected static function date_parts( $timestamp ) {
		return array(
			'day'    => (int) gmdate( 'j', $timestamp ),
			'month'  => (int) gmdate( 'n', $timestamp ),
			'year'   => (int) gmdate( 'Y', $timestamp ),
			'hour'   => (int) gmdate( 'G', $timestamp ),
			'minute' => (int) gmdate( 'i', $timestamp ),
			'second' => (int) gmdate( 's', $timestamp ),
		);
	}

	/**
	 * One row of the downloads table.
	 *
	 * @param int $file_id File ID.
	 * @return object|null
	 */
	public static function get_file( $file_id ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->downloads} WHERE file_id = %d", (int) $file_id ) );
	}

	/**
	 * Add a file.
	 *
	 * @return void
	 */
	protected static function handle_add() {
		if ( ! isset( $_POST['do'], $_POST['_wpnonce'] ) ) {
			return;
		}

		check_admin_referer( 'wp_downloadmanager_add' );
		self::require_capability();

		global $wpdb;

		$post = wp_unslash( $_POST );
		// The three fields of the upload are pulled out here, beside the nonce
		// check, rather than in a helper: an upload is request input like any
		// other, and it is worth being able to see the check and the read in one
		// screenful.
		$upload = array(
			'size'     => isset( $_FILES['file_upload']['size'] ) ? (int) $_FILES['file_upload']['size'] : 0,
			'tmp_name' => isset( $_FILES['file_upload']['tmp_name'] ) ? sanitize_text_field( wp_unslash( $_FILES['file_upload']['tmp_name'] ) ) : '',
			'name'     => isset( $_FILES['file_upload']['name'] ) ? sanitize_file_name( basename( sanitize_text_field( wp_unslash( $_FILES['file_upload']['name'] ) ) ) ) : '',
		);

		/*
		 * The posted source, not a hardcoded one.
		 *
		 * This passed a literal 0, so the Add File screen ignored the four-way
		 * radio entirely and always took the "browse the downloads directory"
		 * branch: an uploaded file and a remote URL were both accepted by the form
		 * and then silently dropped, and the row was written from whatever the
		 * Browse select happened to hold. handle_edit() has always read the field.
		 *
		 * -1 means "keep the current file", which the Add screen has no current
		 * file for and does not offer, so it and anything else unrecognised fall
		 * back to 0 -- the radio the form checks when there is no file yet.
		 */
		$file_type = self::posted_int( $post, 'file_type', 0 );

		if ( ! in_array( $file_type, array( 0, 1, 2 ), true ) ) {
			$file_type = 0;
		}

		$source = self::resolve_source( $post, $upload, $file_type );
		if ( is_wp_error( $source ) ) {
			self::notice( $source->get_error_message(), 'error' );
			return;
		}

		$file_name = self::posted_name( $post );
		if ( '' === $file_name ) {
			$file_name = basename( $source['file'] );
		}

		$date = self::posted_timestamp( $post, WP_DownloadManager_File::now() );

		$inserted = $wpdb->insert(
			$wpdb->downloads,
			array(
				'file'                      => $source['file'],
				'file_name'                 => $file_name,
				'file_des'                  => self::posted_description( $post ),
				'file_size'                 => self::posted_size( $post, $source['size'] ),
				'file_category'             => self::posted_int( $post, 'file_cat' ),
				'file_date'                 => $date,
				'file_updated_date'         => $date,
				'file_last_downloaded_date' => $date,
				'file_hits'                 => self::posted_int( $post, 'file_hits' ),
				'file_permission'           => self::posted_permission( $post ),
			)
		);

		if ( ! $inserted ) {
			self::notice( __( 'The file could not be added.', 'wp-downloadmanager' ), 'error' );
			return;
		}

		self::notice(
			sprintf(
				/* translators: 1: file name, 2: file ID. */
				__( '%1$s added, with file ID %2$s.', 'wp-downloadmanager' ),
				wp_strip_all_tags( $file_name ),
				number_format_i18n( (int) $wpdb->insert_id )
			)
		);
	}

	/**
	 * Save an edited file.
	 *
	 * @param int $file_id File being edited.
	 * @return void
	 */
	protected static function handle_edit( $file_id ) {
		if ( ! isset( $_POST['do'], $_POST['_wpnonce'] ) ) {
			return;
		}

		check_admin_referer( 'wp_downloadmanager_edit_' . (int) $file_id );
		self::require_capability();

		global $wpdb;

		$existing = self::get_file( $file_id );
		if ( ! $existing ) {
			return;
		}

		$post = wp_unslash( $_POST );
		// The three fields of the upload are pulled out here, beside the nonce
		// check, rather than in a helper: an upload is request input like any
		// other, and it is worth being able to see the check and the read in one
		// screenful.
		$upload = array(
			'size'     => isset( $_FILES['file_upload']['size'] ) ? (int) $_FILES['file_upload']['size'] : 0,
			'tmp_name' => isset( $_FILES['file_upload']['tmp_name'] ) ? sanitize_text_field( wp_unslash( $_FILES['file_upload']['tmp_name'] ) ) : '',
			'name'     => isset( $_FILES['file_upload']['name'] ) ? sanitize_file_name( basename( sanitize_text_field( wp_unslash( $_FILES['file_upload']['name'] ) ) ) ) : '',
		);
		$source = self::resolve_source( $post, $upload, self::posted_int( $post, 'file_type', -1 ), $existing );
		if ( is_wp_error( $source ) ) {
			self::notice( $source->get_error_message(), 'error' );
			return;
		}

		$file_name = self::posted_name( $post );
		if ( '' === $file_name ) {
			$file_name = basename( $source['file'] );
		}

		$data = array(
			'file'              => $source['file'],
			'file_name'         => $file_name,
			'file_des'          => self::posted_description( $post ),
			'file_size'         => self::posted_size( $post, $source['size'] ),
			'file_category'     => self::posted_int( $post, 'file_cat' ),
			'file_permission'   => self::posted_permission( $post ),
			'file_updated_date' => WP_DownloadManager_File::now(),
			'file_hits'         => self::posted_int( $post, 'reset_filehits' ) ? 0 : self::posted_int( $post, 'file_hits' ),
		);

		if ( self::posted_int( $post, 'edit_filetimestamp' ) ) {
			$data['file_date'] = self::posted_timestamp( $post, (int) $existing->file_date );
		}

		// update() returns 0 when the row already held these values, which is a
		// successful save rather than the failure the old ! $result check
		// reported.
		$result = $wpdb->update( $wpdb->downloads, $data, array( 'file_id' => (int) $file_id ) );

		if ( false === $result ) {
			self::notice( __( 'The file could not be saved.', 'wp-downloadmanager' ), 'error' );
			return;
		}

		/* translators: %s: file name. */
		self::notice( sprintf( __( '%s saved.', 'wp-downloadmanager' ), wp_strip_all_tags( $file_name ) ) );
	}

	/**
	 * Delete one file from the confirmation screen.
	 *
	 * @param int $file_id File being deleted.
	 * @return bool Whether the row was deleted.
	 */
	protected static function handle_delete( $file_id ) {
		if ( ! isset( $_POST['do'], $_POST['_wpnonce'] ) ) {
			return false;
		}

		check_admin_referer( 'wp_downloadmanager_delete_' . (int) $file_id );
		self::require_capability();

		$post    = wp_unslash( $_POST );
		$deleted = self::delete_files( array( $file_id ), (bool) self::posted_int( $post, 'unlinkfile' ) );

		if ( ! $deleted ) {
			self::notice( __( 'That file no longer exists.', 'wp-downloadmanager' ), 'error' );

			return false;
		}

		self::notice(
			sprintf(
				/* translators: %s: number of files. */
				_n( '%s file deleted.', '%s files deleted.', $deleted, 'wp-downloadmanager' ),
				number_format_i18n( $deleted )
			)
		);

		return true;
	}

	/**
	 * Delete the files ticked on the list screen.
	 *
	 * @return void
	 */
	protected static function handle_bulk_delete() {
		/*
		 * $_REQUEST, not $_POST.
		 *
		 * The form around this table is method="get" -- it carries the search box
		 * and the pagination links, both of which have to stay shareable, and it
		 * is what core's own posts list does. So a browser submits the bulk action
		 * as GET and $_POST is empty, which meant this returned at the guard below
		 * and the bulk delete quietly did nothing at all. The tests never caught
		 * it because they drove the screen with a POST array.
		 *
		 * The nonce is what makes this safe to accept over GET, and it is checked
		 * below before anything is read.
		 */
		if ( ! isset( $_REQUEST['file_ids'], $_REQUEST['_wpnonce'] ) ) {
			return;
		}

		self::load_list_table();

		$table = new WP_DownloadManager_List_Table();
		if ( 'delete' !== $table->current_action() ) {
			return;
		}

		check_admin_referer( $table->bulk_nonce_action() );
		self::require_capability();

		$request = wp_unslash( $_REQUEST );
		$ids     = isset( $request['file_ids'] ) ? array_map( 'intval', (array) $request['file_ids'] ) : array();

		if ( ! $ids ) {
			return;
		}

		$deleted = self::delete_files( $ids, false );

		self::notice(
			sprintf(
				/* translators: %s: number of files. */
				_n( '%s file deleted.', '%s files deleted.', $deleted, 'wp-downloadmanager' ),
				number_format_i18n( $deleted )
			)
		);
	}

	/**
	 * Remove rows, and optionally the files behind them.
	 *
	 * @param array $file_ids  File IDs.
	 * @param bool  $from_disk Also delete the file inside the downloads directory.
	 * @return int Number of rows removed.
	 */
	protected static function delete_files( $file_ids, $from_disk ) {
		global $wpdb;

		$path    = WP_DownloadManager_Options::get( 'path.dir' );
		$deleted = 0;

		foreach ( $file_ids as $file_id ) {
			$file = self::get_file( $file_id );
			if ( ! $file ) {
				continue;
			}

			if ( $from_disk && ! WP_DownloadManager_File::is_remote( stripslashes( $file->file ) ) ) {
				// wp_delete_file_from_directory() refuses anything that resolves
				// outside the downloads directory, so a crafted row cannot make
				// this unlink somewhere else.
				wp_delete_file_from_directory( $path . stripslashes( $file->file ), $path );
			}

			$deleted += (int) $wpdb->delete( $wpdb->downloads, array( 'file_id' => (int) $file_id ) );
		}

		return $deleted;
	}

	/**
	 * Work out which file the form is pointing at, and how big it is.
	 *
	 * @param array       $post      Unslashed form data.
	 * @param array       $upload    The $_FILES entry for the upload field.
	 * @param int         $file_type Source: -1 keep, 0 browse, 1 upload, 2 remote.
	 * @param object|null $existing  Row being edited, when there is one.
	 * @return array|WP_Error Keys 'file' and 'size', or the reason it failed.
	 */
	protected static function resolve_source( $post, $upload, $file_type, $existing = null ) {
		$path = WP_DownloadManager_Options::get( 'path.dir' );

		switch ( (int) $file_type ) {
			case -1:
				$file = $existing ? stripslashes( $existing->file ) : '';

				return array(
					'file' => $file,
					'size' => WP_DownloadManager_File::is_remote( $file )
						? WP_DownloadManager_File::remote_filesize( $file )
						: self::size_on_disk( $path . $file ),
				);

			case 1:
				return self::resolve_upload( $post, $upload, $path );

			case 2:
				$remote = isset( $post['file_remote'] ) ? esc_url_raw( $post['file_remote'] ) : '';

				if ( ! WP_DownloadManager_File::is_remote_valid( $remote ) ) {
					return new WP_Error( 'remote', __( 'That remote file URL could not be read.', 'wp-downloadmanager' ) );
				}

				return array(
					'file' => $remote,
					'size' => WP_DownloadManager_File::remote_filesize( $remote ),
				);

			default:
				$file = isset( $post['file'] ) ? trim( sanitize_text_field( $post['file'] ) ) : '';
				$file = WP_DownloadManager_File::rename_file( $path, $file );

				return array(
					'file' => $file,
					'size' => self::size_on_disk( $path . $file ),
				);
		}
	}

	/**
	 * Move an upload into the downloads directory.
	 *
	 * @param array  $post   Unslashed form data.
	 * @param array  $upload The $_FILES entry for the upload field.
	 * @param string $path   Downloads directory.
	 * @return array|WP_Error
	 */
	protected static function resolve_upload( $post, $upload, $path ) {
		$size     = (int) $upload['size'];
		$tmp_name = (string) $upload['tmp_name'];
		$name     = (string) $upload['name'];

		if ( $size > WP_DownloadManager_File::max_upload_size() ) {
			return new WP_Error(
				'upload_size',
				sprintf(
					/* translators: %s: the maximum upload size. */
					__( 'That file is too large. The maximum size is %s.', 'wp-downloadmanager' ),
					WP_DownloadManager_File::format_size( WP_DownloadManager_File::max_upload_size() )
				)
			);
		}

		if ( ! is_uploaded_file( $tmp_name ) ) {
			return new WP_Error( 'upload', __( 'The file could not be uploaded.', 'wp-downloadmanager' ) );
		}

		$validated = wp_check_filetype_and_ext( $tmp_name, $name );
		if ( false === $validated['type'] ) {
			return new WP_Error( 'upload_type', __( 'That file type is not allowed.', 'wp-downloadmanager' ) );
		}

		$folder = isset( $post['file_upload_to'] ) ? sanitize_text_field( $post['file_upload_to'] ) : '';
		$folder = WP_DownloadManager_File::safe_subfolder( $path, $folder );
		if ( '/' !== $folder ) {
			$folder .= '/';
		}

		if ( ! move_uploaded_file( $tmp_name, $path . $folder . $name ) ) {
			return new WP_Error( 'upload', __( 'The file could not be uploaded.', 'wp-downloadmanager' ) );
		}

		$file = WP_DownloadManager_File::rename_file( $path, $folder . $name );

		return array(
			'file' => $file,
			'size' => self::size_on_disk( $path . $file ),
		);
	}

	/**
	 * The size of a file on disk, or zero when it is not there.
	 *
	 * @param string $path Absolute path.
	 * @return int
	 */
	protected static function size_on_disk( $path ) {
		return is_file( $path ) ? (int) filesize( $path ) : 0;
	}

	/**
	 * One submitted integer.
	 *
	 * The handlers pass in the already-unslashed $_POST they read straight after
	 * checking the nonce, so these accessors never touch a superglobal
	 * themselves - the nonce check and the read are in the same method, where
	 * anyone auditing them can see both at once.
	 *
	 * @param array  $post     Unslashed form data.
	 * @param string $key      Field name.
	 * @param int    $fallback Value when the field is absent.
	 * @return int
	 */
	protected static function posted_int( $post, $key, $fallback = 0 ) {
		return isset( $post[ $key ] ) ? (int) $post[ $key ] : $fallback;
	}

	/**
	 * The submitted display name, which may carry markup.
	 *
	 * @param array $post Unslashed form data.
	 * @return string
	 */
	protected static function posted_name( $post ) {
		return isset( $post['file_name'] ) ? trim( wp_kses_post( $post['file_name'] ) ) : '';
	}

	/**
	 * The submitted description, which may carry markup.
	 *
	 * @param array $post Unslashed form data.
	 * @return string
	 */
	protected static function posted_description( $post ) {
		return isset( $post['file_des'] ) ? trim( wp_kses_post( $post['file_des'] ) ) : '';
	}

	/**
	 * The permission level, constrained to the levels the select offers.
	 *
	 * @param array $post Unslashed form data.
	 * @return int
	 */
	protected static function posted_permission( $post ) {
		$permission = self::posted_int( $post, 'file_permission', -1 );

		return in_array( $permission, array( -2, -1, 0, 1, 2, 7, 10 ), true ) ? $permission : -1;
	}

	/**
	 * The file size to store: the detected one, or whatever was typed.
	 *
	 * @param array $post     Unslashed form data.
	 * @param mixed $detected Size worked out from the source.
	 * @return int
	 */
	protected static function posted_size( $post, $detected ) {
		if ( self::posted_int( $post, 'auto_filesize' ) ) {
			return (int) $detected;
		}

		return self::posted_int( $post, 'file_size', (int) $detected );
	}

	/**
	 * The date the six selects add up to.
	 *
	 * @param array $post     Unslashed form data.
	 * @param int   $fallback Used when the selects were not submitted.
	 * @return int
	 */
	protected static function posted_timestamp( $post, $fallback ) {
		if ( ! isset( $post['file_timestamp_year'] ) ) {
			return $fallback;
		}

		return gmmktime(
			self::posted_int( $post, 'file_timestamp_hour' ),
			self::posted_int( $post, 'file_timestamp_minute' ),
			self::posted_int( $post, 'file_timestamp_second' ),
			self::posted_int( $post, 'file_timestamp_month' ),
			self::posted_int( $post, 'file_timestamp_day' ),
			self::posted_int( $post, 'file_timestamp_year' )
		);
	}

	/**
	 * One request value, unslashed and sanitized.
	 *
	 * Routing only: which of the three modes of the downloads screen to render.
	 * Every handler that writes checks a nonce of its own first.
	 *
	 * @param string $key Query argument name.
	 * @return string
	 */
	protected static function request( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Routing only: ?action and ?id pick which of the three modes of the downloads screen to draw. Every handler that writes returns early unless a nonce is posted and then verifies the admin referer - add, edit, delete and bulk delete all do, and the edit and delete nonces have the file id baked into them.
		return isset( $_REQUEST[ $key ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ $key ] ) ) : '';
	}

	/**
	 * Queue an admin notice.
	 *
	 * Every message goes through add_settings_error() rather than a hand-rolled
	 * <div class="updated">, so they all look and behave the same.
	 *
	 * @param string $message Message text.
	 * @param string $type    'success' or 'error'.
	 * @return void
	 */
	protected static function notice( $message, $type = 'success' ) {
		add_settings_error( 'wp_downloadmanager', 'wp_downloadmanager_' . $type, $message, $type );
	}

	/**
	 * Stop a request that has no business being here.
	 *
	 * @return void
	 */
	protected static function require_capability() {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die(
				esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-downloadmanager' ),
				'',
				array( 'response' => 403 )
			);
		}
	}

	/**
	 * The one admin script, on the plugin's own screens.
	 *
	 * There was an admin stylesheet here too, enqueued on every plugin screen. It
	 * had been a zero-byte file since the plugin's first commit, so every one of
	 * those screens paid for a request that delivered nothing.
	 *
	 * @param string $hook_suffix Current admin page.
	 * @return void
	 */
	public static function enqueue_assets( $hook_suffix ) {
		if ( ! self::is_plugin_screen( $hook_suffix ) ) {
			return;
		}

		wp_enqueue_script(
			'wp-downloadmanager-admin',
			WP_DOWNLOADMANAGER_URL . 'js/wp-downloadmanager-admin.js',
			array(),
			WP_DOWNLOADMANAGER_VERSION,
			true
		);

		// The reset buttons read the stock markup from here rather than from a
		// duplicated copy inside the script, which is how the two used to drift.
		wp_localize_script( 'wp-downloadmanager-admin', 'wpDownloadManagerL10n', self::script_data() );
	}

	/**
	 * Whether a hook suffix belongs to one of this plugin's screens.
	 *
	 * WordPress hands back "toplevel_page_<slug>" and "downloads_page_<slug>",
	 * so the test is on the slug rather than on the whole suffix.
	 *
	 * @param string $hook_suffix Current admin page.
	 * @return bool
	 */
	public static function is_plugin_screen( $hook_suffix ) {
		foreach ( self::screens() as $slug ) {
			if ( (string) $hook_suffix === $slug || str_ends_with( (string) $hook_suffix, '_page_' . $slug ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The three page slugs this plugin registers.
	 *
	 * The menu and the asset loader both read this, so they cannot drift apart -
	 * which is how the old loader ended up matching a download-uninstall.php that
	 * had not existed for years.
	 *
	 * @return array
	 */
	public static function screens() {
		return array(
			'downloads' => self::PAGE,
			'add'       => self::PAGE . '-add',
			'settings'  => self::PAGE . '-settings',
		);
	}

	/**
	 * The Text tab quicktag.
	 *
	 * @return void
	 */
	public static function quicktag() {
		wp_enqueue_script(
			'wp-downloadmanager-quicktag',
			WP_DOWNLOADMANAGER_URL . 'js/wp-downloadmanager-quicktag.js',
			array( 'quicktags' ),
			WP_DOWNLOADMANAGER_VERSION,
			true
		);

		wp_localize_script( 'wp-downloadmanager-quicktag', 'wpDownloadManagerL10n', self::script_data() );
	}

	/**
	 * Everything the admin scripts read, in one localised object.
	 *
	 * Section 6 of the standard gives every plugin exactly one localised global,
	 * named after the class prefix, so the two scripts share this payload rather
	 * than each inventing a name of its own.
	 *
	 * @return array
	 */
	public static function script_data() {
		return array(
			'templates' => WP_DownloadManager_Template::for_script(),
			'quicktag'  => array(
				'label'  => __( 'Download', 'wp-downloadmanager' ),
				'prompt' => __( 'Enter File ID (Separate Multiple IDs By A Comma)', 'wp-downloadmanager' ),
			),
		);
	}

	/**
	 * The TinyMCE button.
	 *
	 * @return void
	 */
	public static function editor_buttons() {
		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'edit_pages' ) ) {
			return;
		}
		if ( 'true' !== get_user_option( 'rich_editing' ) ) {
			return;
		}

		add_filter( 'mce_external_plugins', array( __CLASS__, 'mce_plugin' ) );
		add_filter( 'mce_buttons', array( __CLASS__, 'mce_button' ) );
		add_filter( 'wp_mce_translation', array( __CLASS__, 'mce_translation' ) );
	}

	/**
	 * Register the TinyMCE plugin script.
	 *
	 * One unminified file: there is no build step here, so a hand-minified twin
	 * only drifts out of sync.
	 *
	 * @param array $plugins TinyMCE plugins.
	 * @return array
	 */
	public static function mce_plugin( $plugins ) {
		$plugins['downloadmanager'] = WP_DOWNLOADMANAGER_URL . 'tinymce/plugins/downloadmanager/plugin.js?v=' . WP_DOWNLOADMANAGER_VERSION;

		return $plugins;
	}

	/**
	 * Add the button to the toolbar.
	 *
	 * @param array $buttons TinyMCE buttons.
	 * @return array
	 */
	public static function mce_button( $buttons ) {
		array_push( $buttons, 'separator', 'downloadmanager' );

		return $buttons;
	}

	/**
	 * Translate the button's strings.
	 *
	 * @param array $translations TinyMCE translations.
	 * @return array
	 */
	public static function mce_translation( $translations ) {
		$translations['Enter File ID (Separate Multiple IDs By A Comma)'] = __( 'Enter File ID (Separate Multiple IDs By A Comma)', 'wp-downloadmanager' );
		$translations['Insert File Download']                             = __( 'Insert File Download', 'wp-downloadmanager' );

		return $translations;
	}

	/**
	 * List the files in the downloads directory as option tags.
	 *
	 * @param string $dir      Directory to scan.
	 * @param string $base_dir Downloads root, stripped from the labels.
	 * @param string $selected Currently selected file.
	 * @return void
	 */
	public static function print_files( $dir, $base_dir, $selected = '' ) {
		$files      = array();
		$subfolders = array();

		self::collect_files( $dir, $base_dir, $files, $subfolders );

		natcasesort( $files );
		natcasesort( $subfolders );

		foreach ( array_merge( $files, $subfolders ) as $file ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>' . "\n",
				esc_attr( $file ),
				selected( $file, $selected, false ),
				esc_html( $file )
			);
		}
	}

	/**
	 * List the folders in the downloads directory as option tags.
	 *
	 * @param string $dir      Directory to scan.
	 * @param string $base_dir Downloads root, stripped from the labels.
	 * @return void
	 */
	public static function print_folders( $dir, $base_dir ) {
		$folders = array();

		self::collect_folders( $dir, $base_dir, $folders );

		natcasesort( $folders );

		echo '<option value="/">/</option>' . "\n";
		foreach ( $folders as $folder ) {
			printf( '<option value="%1$s">%2$s</option>' . "\n", esc_attr( $folder ), esc_html( $folder ) );
		}
	}

	/**
	 * Recursively collect files, split by nesting depth.
	 *
	 * @param string $dir        Directory to scan.
	 * @param string $base_dir   Downloads root.
	 * @param array  $files      Top level files, by reference.
	 * @param array  $subfolders Nested files, by reference.
	 * @return void
	 */
	protected static function collect_files( $dir, $base_dir, &$files, &$subfolders ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		foreach ( (array) scandir( $dir ) as $file ) {
			if ( '.' === $file || '..' === $file || '.htaccess' === $file ) {
				continue;
			}

			if ( is_dir( $dir . '/' . $file ) ) {
				self::collect_files( $dir . '/' . $file, $base_dir, $files, $subfolders );
				continue;
			}

			$relative = str_replace( $base_dir, '', $dir . '/' . $file );
			if ( count( explode( '/', $relative ) ) > 2 ) {
				$subfolders[] = $relative;
			} else {
				$files[] = $relative;
			}
		}
	}

	/**
	 * Recursively collect folders.
	 *
	 * @param string $dir      Directory to scan.
	 * @param string $base_dir Downloads root.
	 * @param array  $folders  Collected folders, by reference.
	 * @return void
	 */
	protected static function collect_folders( $dir, $base_dir, &$folders ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		foreach ( (array) scandir( $dir ) as $file ) {
			if ( '.' === $file || '..' === $file ) {
				continue;
			}
			if ( ! is_dir( $dir . '/' . $file ) ) {
				continue;
			}

			$folders[] = str_replace( $base_dir, '', $dir . '/' . $file );
			self::collect_folders( $dir . '/' . $file, $base_dir, $folders );
		}
	}

	/**
	 * The editable file timestamp selects.
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return void
	 */
	public static function file_timestamp( $timestamp ) {
		global $wp_locale;

		$fields = array(
			'day'    => array( 'j', 1, 31 ),
			'month'  => array( 'n', 1, 12 ),
			'year'   => array( 'Y', 2000, (int) gmdate( 'Y' ) ),
			'hour'   => array( 'H', 0, 23 ),
			'minute' => array( 'i', 0, 59 ),
			'second' => array( 's', 0, 60 ),
		);

		$separators = array(
			'day'    => '&nbsp;&nbsp;',
			'month'  => '&nbsp;&nbsp;',
			'year'   => '&nbsp;@' . "\n" . '<span dir="ltr">',
			'hour'   => '&nbsp;:',
			'minute' => '&nbsp;:',
			'second' => '</span>',
		);

		foreach ( $fields as $name => $spec ) {
			list( $format, $min, $max ) = $spec;
			$current                    = (int) gmdate( $format, $timestamp );

			printf( '<select id="file_timestamp_%1$s" name="file_timestamp_%1$s" size="1">' . "\n", esc_attr( $name ) );
			for ( $i = $min; $i <= $max; $i++ ) {
				$label = $i;
				if ( 'month' === $name ) {
					$label = $wp_locale->get_month( zeroise( $i, 2 ) );
				}
				printf(
					'<option value="%1$d"%2$s>%3$s</option>' . "\n",
					(int) $i,
					selected( $i, $current, false ),
					esc_html( $label )
				);
			}
			echo '</select>';
			echo wp_kses_post( $separators[ $name ] ) . "\n";
		}
	}
}
