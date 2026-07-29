<?php
/**
 * The Add A File screen.
 *
 * Included by wp-admin: the menu uses the legacy "plugin file as menu slug"
 * form, so this file's path relative to the plugins directory is its slug.
 *
 * @package WP-WP_DownloadManager
 */

// Check whether the user can manage downloads.
if ( ! current_user_can( 'manage_downloads' ) ) {
	// wp_die() rather than a bare die(): it renders a styled page, sends a 403
	// instead of a 200, and is catchable, so the capability guard can be tested.
	wp_die(
		esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-downloadmanager' ),
		'',
		array( 'response' => 403 )
	);
}


// Variables.
$base_name       = WP_DOWNLOADMANAGER_SLUG . '/includes/screen-manage.php';
$base_page       = 'admin.php?page=' . $base_name;
$file_path       = WP_DownloadManager_Options::get( 'path.dir' );
$file_categories = WP_DownloadManager_Options::get( 'categories' );


// WordPress includes this file at global scope from admin.php, so $wpdb is
// already in scope. Declared explicitly so the dependency is visible.
global $wpdb;


// The form screens' behaviour lives in one script rather than in inline
// handlers. Enqueued for every mode: add, edit and delete all use it.
wp_enqueue_script(
	'wp-downloadmanager-forms',
	WP_DOWNLOADMANAGER_URL . 'js/wp-downloadmanager-forms.js',
	array(),
	WP_DOWNLOADMANAGER_VERSION,
	true
);


// Form processing.
if ( ! empty( $_POST['do'] ) ) {
	check_admin_referer( 'wp-downloadmanager_add-file' );
	// Decide what to do.
	switch ( $_POST['do'] ) {
		// Add a file.
		case __( 'Add File', 'wp-downloadmanager' ):
			$file_type = ! empty( $_POST['file_type'] ) ? intval( $_POST['file_type'] ) : 0;
			switch ( $file_type ) {
				case 0:
					$file      = ! empty( $_POST['file'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['file'] ) ) ) : '';
					$file      = WP_DownloadManager_File::rename_file( $file_path, $file );
					$file_size = filesize( $file_path . $file );
					break;
				case 1:
					$upload_size = isset( $_FILES['file_upload']['size'] ) ? (int) $_FILES['file_upload']['size'] : 0;
					if ( $upload_size > get_max_upload_size() ) {
						/* translators: %s: the maximum upload size. */
						$text = '<p style="color: red;">' . sprintf( __( 'File Size Too Large. Maximum Size Is %s', 'wp-downloadmanager' ), format_filesize( get_max_upload_size() ) ) . '</p>';
						break;
					}
					$file_name = ! empty( $_FILES['file_upload']['name'] ) ? sanitize_file_name( basename( sanitize_text_field( wp_unslash( $_FILES['file_upload']['name'] ) ) ) ) : '';
					$tmp_name  = isset( $_FILES['file_upload']['tmp_name'] ) ? sanitize_text_field( wp_unslash( $_FILES['file_upload']['tmp_name'] ) ) : '';
					$validate  = wp_check_filetype_and_ext( $tmp_name, $file_name );
					if ( false === $validate['type'] ) {
							$text = '<p style="color: red;">' . __( 'File type is invalid', 'wp-downloadmanager' ) . '</p>';
							break;
					}
					if ( is_uploaded_file( $tmp_name ) ) {
						$file_upload_to = ! empty( $_POST['file_upload_to'] ) ? sanitize_text_field( wp_unslash( $_POST['file_upload_to'] ) ) : '';
						$file_upload_to = WP_DownloadManager_File::safe_subfolder( $file_path, $file_upload_to );
						if ( '/' !== $file_upload_to ) {
							$file_upload_to = $file_upload_to . '/';
						}
						if ( move_uploaded_file( $tmp_name, $file_path . $file_upload_to . $file_name ) ) {
							$file      = $file_upload_to . $file_name;
							$file      = WP_DownloadManager_File::rename_file( $file_path, $file );
							$file_size = filesize( $file_path . $file );
						} else {
							$text = '<p style="color: red;">' . __( 'Error In Uploading File', 'wp-downloadmanager' ) . '</p>';
							break;
						}
					} else {
						$text = '<p style="color: red;">' . __( 'Error In Uploading File', 'wp-downloadmanager' ) . '</p>';
						break;
					}
					break;
				case 2:
					$file = ! empty( $_POST['file_remote'] ) ? esc_url_raw( wp_unslash( $_POST['file_remote'] ) ) : '';
					if ( is_file_remote_valid( $file ) ) {
						$file_size = remote_filesize( $file );
					} else {
						$text = '<p style="color: red;">' . __( 'There Is An Error Parsing Remote File URL', 'wp-downloadmanager' ) . '</p>';
					}
					break;
			}
			if ( empty( $text ) ) {
				$file_name = ! empty( $_POST['file_name'] ) ? trim( wp_kses_post( wp_unslash( $_POST['file_name'] ) ) ) : '';
				if ( empty( $file_name ) ) {
					$file_name = basename( $file );
				}
				$file_des      = ! empty( $_POST['file_des'] ) ? trim( wp_kses_post( wp_unslash( $_POST['file_des'] ) ) ) : '';
				$file_category = ! empty( $_POST['file_cat'] ) ? intval( $_POST['file_cat'] ) : 0;
				if ( ! empty( $_POST['file_size'] ) ) {
					$file_size = ! empty( $_POST['file_size'] ) ? intval( $_POST['file_size'] ) : 0;
				}
				$file_hits             = ! empty( $_POST['file_hits'] ) ? intval( $_POST['file_hits'] ) : 0;
				$file_timestamp_day    = ! empty( $_POST['file_timestamp_day'] ) ? intval( $_POST['file_timestamp_day'] ) : 0;
				$file_timestamp_month  = ! empty( $_POST['file_timestamp_month'] ) ? intval( $_POST['file_timestamp_month'] ) : 0;
				$file_timestamp_year   = ! empty( $_POST['file_timestamp_year'] ) ? intval( $_POST['file_timestamp_year'] ) : 0;
				$file_timestamp_hour   = ! empty( $_POST['file_timestamp_hour'] ) ? intval( $_POST['file_timestamp_hour'] ) : 0;
				$file_timestamp_minute = ! empty( $_POST['file_timestamp_minute'] ) ? intval( $_POST['file_timestamp_minute'] ) : 0;
				$file_timestamp_second = ! empty( $_POST['file_timestamp_second'] ) ? intval( $_POST['file_timestamp_second'] ) : 0;
				$file_date             = gmmktime( $file_timestamp_hour, $file_timestamp_minute, $file_timestamp_second, $file_timestamp_month, $file_timestamp_day, $file_timestamp_year );
				$file_permission       = ! empty( $_POST['file_permission'] ) ? intval( $_POST['file_permission'] ) : 0;
				// Positional VALUES built by hand before, which relied on the column
				// order never changing and left every text value interpolated.
				$addfile = $wpdb->insert(
					$wpdb->downloads,
					array(
						'file'                      => $file,
						'file_name'                 => $file_name,
						'file_des'                  => $file_des,
						'file_size'                 => $file_size,
						'file_category'             => $file_category,
						'file_date'                 => $file_date,
						'file_updated_date'         => $file_date,
						'file_last_downloaded_date' => $file_date,
						'file_hits'                 => $file_hits,
						'file_permission'           => $file_permission,
					)
				);
				if ( ! $addfile ) {
					/* translators: 1: file name, 2: file path. */
					$text = '<p style="color: red;">' . sprintf( __( 'Error In Adding File \'%1$s (%2$s)\'', 'wp-downloadmanager' ), $file_name, $file ) . '</p>';
				} else {
					$file_id = intval( $wpdb->insert_id );
					/* translators: 1: file name, 2: file path, 3: file ID. */
					$text = '<p style="color: green;">' . sprintf( __( 'File \'%1$s (%2$s) (ID: %3$s)\' Added Successfully', 'wp-downloadmanager' ), $file_name, $file, $file_id ) . '</p>';
				}
			}
			break;
	}
}
?>
<?php
if ( ! empty( $text ) ) {
	echo '<!-- Last Action --><div id="message" class="updated fade"><p>' . wp_kses_post( stripslashes( $text ) ) . '</p></div>';
}
?>
<!-- Add A File -->
<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . WP_DOWNLOADMANAGER_SLUG . '/includes/' . basename( __FILE__ ) ) ); ?>" enctype="multipart/form-data">
	<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo esc_attr( get_max_upload_size() ); ?>" />
	<?php wp_nonce_field( 'wp-downloadmanager_add-file' ); ?>
	<div class="wrap">
		<h2><?php esc_html_e( 'Add A File', 'wp-downloadmanager' ); ?></h2>
		<table class="form-table">
			<tr>
				<td valign="top"><strong><?php esc_html_e( 'File:', 'wp-downloadmanager' ); ?></strong></td>
				<td>
					<!-- Browse File -->
					<input type="radio" id="file_type_0" name="file_type" value="0" checked="checked" />&nbsp;&nbsp;<label for="file_type_0"><?php esc_html_e( 'Browse File:', 'wp-downloadmanager' ); ?></label>&nbsp;
					<select name="file" size="1" data-checks="file_type_0" dir="ltr">
						<?php WP_DownloadManager_Admin::print_files( $file_path, $file_path ); ?>
					</select>
					<br /><small>
					<?php
					/* translators: %s: the downloads directory. */
					printf( esc_html__( 'Please upload the file to \'%s\' directory first.', 'wp-downloadmanager' ), esc_html( $file_path ) );
					?>
					</small>
					<br /><br />
					<!-- Upload File -->
					<input type="radio" id="file_type_1" name="file_type" value="1" />&nbsp;&nbsp;<label for="file_type_1"><?php esc_html_e( 'Upload File:', 'wp-downloadmanager' ); ?></label>&nbsp;
					<input type="file" name="file_upload" size="25" data-checks="file_type_1" dir="ltr" />&nbsp;&nbsp;<?php esc_html_e( 'to', 'wp-downloadmanager' ); ?>&nbsp;&nbsp;
					<select name="file_upload_to" size="1" data-checks="file_type_1" dir="ltr">
						<?php WP_DownloadManager_Admin::print_folders( $file_path, $file_path ); ?>
					</select>
					<br /><small>
					<?php
					/* translators: %s: the maximum upload size. */
					printf( esc_html__( 'Maximum file size is %s.', 'wp-downloadmanager' ), esc_html( format_filesize( get_max_upload_size() ) ) );
					?>
					</small>
					<!-- Remote File -->
					<br /><br />
					<input type="radio" id="file_type_2" name="file_type" value="2" />&nbsp;&nbsp;<label for="file_type_2"><?php esc_html_e( 'Remote File:', 'wp-downloadmanager' ); ?></label>&nbsp;
					<input type="text" name="file_remote" size="50" maxlength="255" data-checks="file_type_2" value="http://" dir="ltr" />
					<br /><small><?php esc_html_e( 'Please include http:// or ftp:// in front.', 'wp-downloadmanager' ); ?></small>
				</td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'File Name:', 'wp-downloadmanager' ); ?></strong></td>
				<td><input type="text" size="50" maxlength="200" name="file_name" /></td>
			</tr>
			<tr>
				<td valign="top"><strong><?php esc_html_e( 'File Description:', 'wp-downloadmanager' ); ?></strong></td>
				<td><textarea rows="5" cols="50" name="file_des"></textarea></td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'File Category:', 'wp-downloadmanager' ); ?></strong></td>
				<td>
					<select name="file_cat" size="1">
						<?php
						$category_count = count( $file_categories );
						for ( $i = 0; $i < $category_count; $i++ ) {
							if ( ! empty( $file_categories[ $i ] ) ) {
								printf( '<option value="%1$s">%2$s</option>' . "\n", esc_attr( $i ), esc_html( $file_categories[ $i ] ) );
							}
						}
						?>
					</select>
				</td>
			</tr>
			<tr>
				<td valign="top"><strong><?php esc_html_e( 'File Size:', 'wp-downloadmanager' ); ?></strong></td>
				<td><input type="text" size="10" name="file_size" />&nbsp;<?php esc_html_e( 'bytes', 'wp-downloadmanager' ); ?><br /><small><?php esc_html_e( 'Leave blank for auto detection. Auto detection sometimes will not work for Remote File.', 'wp-downloadmanager' ); ?></small></td>
			</tr>
			<tr>
				<td valign="top"><strong><?php esc_html_e( 'File Date:', 'wp-downloadmanager' ); ?></strong></td>
				<td><?php WP_DownloadManager_Admin::file_timestamp( current_time( 'timestamp' ) ); ?></td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'Starting File Hits:', 'wp-downloadmanager' ); ?></strong></td>
				<td><input type="text" size="6" maxlength="10" name="file_hits" value="0" /></td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'Allowed To Download:', 'wp-downloadmanager' ); ?></strong></td>
				<td>
					<select name="file_permission" size="1">
						<option value="-2"><?php esc_html_e( 'Hidden', 'wp-downloadmanager' ); ?></option>
						<option value="-1" selected="selected"><?php esc_html_e( 'Everyone', 'wp-downloadmanager' ); ?></option>
						<option value="0"><?php esc_html_e( 'Registered Users Only', 'wp-downloadmanager' ); ?></option>
						<option value="1"><?php esc_html_e( 'At Least Contributor Role', 'wp-downloadmanager' ); ?></option>
						<option value="2"><?php esc_html_e( 'At Least Author Role', 'wp-downloadmanager' ); ?></option>
						<option value="7"><?php esc_html_e( 'At Least Editor Role', 'wp-downloadmanager' ); ?></option>
						<option value="10"><?php esc_html_e( 'At Least Administrator Role', 'wp-downloadmanager' ); ?></option>
					</select>
					<p>
						<?php esc_html_e( 'Note: While role-based authentication is enforced, users who directly guess the file URL may still be able to access the file without authorization.', 'wp-downloadmanager' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<td colspan="2" align="center"><input type="submit" name="do" value="<?php esc_html_e( 'Add File', 'wp-downloadmanager' ); ?>" class="button" />&nbsp;&nbsp;<button type="button" name="cancel" class="button download-cancel"><?php esc_html_e( 'Cancel', 'wp-downloadmanager' ); ?></button></td>
			</tr>
		</table>
	</div>
</form>