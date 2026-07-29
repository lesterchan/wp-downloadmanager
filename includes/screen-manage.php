<?php
/**
 * The Manage Downloads screen, and its Edit and Delete views.
 *
 * Included by wp-admin: the menu uses the legacy "plugin file as menu slug"
 * form, so this file's path relative to the plugins directory is its slug.
 *
 * @package WP-DownloadManager
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
$base_name           = WP_DOWNLOADMANAGER_SLUG . '/includes/screen-manage.php';
$base_page           = 'admin.php?page=' . $base_name;
$dl_mode             = ! empty( $_GET['mode'] ) ? sanitize_text_field( wp_unslash( $_GET['mode'] ) ) : '';
$file_id             = ! empty( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
$file_path           = DownloadManager_Options::get( 'path.dir' );
$file_categories     = DownloadManager_Options::get( 'categories' );
$file_page           = ! empty( $_GET['filepage'] ) ? intval( $_GET['filepage'] ) : 0;
$file_sortby         = ! empty( $_GET['by'] ) ? sanitize_text_field( wp_unslash( $_GET['by'] ) ) : '';
$file_sortby_text    = '';
$file_sortorder      = ! empty( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : '';
$file_sortorder_text = '';
$file_perpage        = ! empty( $_GET['perpage'] ) ? intval( $_GET['perpage'] ) : 0;
$file_sort_url       = '';
$file_search         = ! empty( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';
$file_search_query   = '';

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


// Build the sorting URL.
if ( ! empty( $file_sortby ) ) {
	$file_sort_url .= '&by=' . $file_sortby;
}
if ( ! empty( $file_sortorder ) ) {
	$file_sort_url .= '&order=' . $file_sortorder;
}
if ( ! empty( $file_perpage ) ) {
	$file_sort_url .= '&perpage=' . $file_perpage;
}


// Searching.
if ( ! empty( $file_search ) ) {
	// sanitize_text_field() does not escape quotes, so the term used to be able
	// to terminate the string and rewrite the rest of the query.
	$file_search_like  = '%' . $wpdb->esc_like( $file_search ) . '%';
	$file_search_query = $wpdb->prepare( 'AND (file LIKE %s OR file_name LIKE %s OR file_des LIKE %s)', $file_search_like, $file_search_like, $file_search_like );
	$file_sort_url    .= '&search=' . rawurlencode( $file_search );
}


// Resolve the order-by column.
switch ( $file_sortby ) {
	case 'id':
		$file_sortby      = 'file_id';
		$file_sortby_text = __( 'File ID', 'wp-downloadmanager' );
		break;
	case 'file':
		$file_sortby      = 'file';
		$file_sortby_text = __( 'File', 'wp-downloadmanager' );
		break;
	case 'size':
		$file_sortby      = '(file_size+0.00)';
		$file_sortby_text = __( 'File Size', 'wp-downloadmanager' );
		break;
	case 'category':
		$file_sortby      = 'file_category';
		$file_sortby_text = __( 'File Category', 'wp-downloadmanager' );
		break;
	case 'hits':
		$file_sortby      = 'file_hits';
		$file_sortby_text = __( 'File Hits', 'wp-downloadmanager' );
		break;
	case 'permission':
		$file_sortby      = 'file_permission';
		$file_sortby_text = __( 'File Permission', 'wp-downloadmanager' );
		break;
	case 'date':
		$file_sortby      = 'FROM_UNIXTIME(file_date)';
		$file_sortby_text = __( 'File Date', 'wp-downloadmanager' );
		break;
	case 'updated_date':
		$file_sortby      = 'FROM_UNIXTIME(file_updated_date)';
		$file_sortby_text = __( 'File Updated Date', 'wp-downloadmanager' );
		break;
	case 'last_downloaded_date':
		$file_sortby      = 'FROM_UNIXTIME(file_last_downloaded_date)';
		$file_sortby_text = __( 'File Last Downloaded Date', 'wp-downloadmanager' );
		break;
	case 'name':
	default:
		$file_sortby      = 'file_name';
		$file_sortby_text = __( 'File Name', 'wp-downloadmanager' );
}


// Resolve the sort direction.
switch ( $file_sortorder ) {
	case 'desc':
		$file_sortorder      = 'DESC';
		$file_sortorder_text = __( 'Descending', 'wp-downloadmanager' );
		break;
	case 'asc':
	default:
		$file_sortorder      = 'ASC';
		$file_sortorder_text = __( 'Ascending', 'wp-downloadmanager' );
}


// Form processing.
if ( ! empty( $_POST['do'] ) ) {
	// Decide what to do.
	switch ( $_POST['do'] ) {
		// Edit a file.
		case __( 'Edit File', 'wp-downloadmanager' ):
			check_admin_referer( 'wp-downloadmanager_edit-file' );
			$file_size_sql = '';
			$file_sql      = '';
			$file_id       = ! empty( $_POST['file_id'] ) ? intval( $_POST['file_id'] ) : 0;
			$file_type     = ! empty( $_POST['file_type'] ) ? intval( $_POST['file_type'] ) : 0;
			$file_name     = ! empty( $_POST['file_name'] ) ? trim( wp_kses_post( wp_unslash( $_POST['file_name'] ) ) ) : '';
			switch ( $file_type ) {
				case -1:
					$file = ! empty( $_POST['old_file'] ) ? sanitize_text_field( wp_unslash( $_POST['old_file'] ) ) : '';
					if ( is_remote_file( $file ) ) {
						$file_size = remote_filesize( $file );
						if ( 'unknown' === $file_size ) {
							$file_size = 0;
						}
					} else {
						$file_size = filesize( $file_path . $file );
					}
					break;
				case 0:
					$file      = ! empty( $_POST['file'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['file'] ) ) ) : '';
					$file      = DownloadManager_File::rename_file( $file_path, $file );
					$file_size = filesize( $file_path . $file );
					break;
				case 1:
					$upload_size = isset( $_FILES['file_upload']['size'] ) ? (int) $_FILES['file_upload']['size'] : 0;
					$tmp_name    = isset( $_FILES['file_upload']['tmp_name'] ) ? sanitize_text_field( wp_unslash( $_FILES['file_upload']['tmp_name'] ) ) : '';
					$upload_name = isset( $_FILES['file_upload']['name'] ) ? sanitize_file_name( basename( sanitize_text_field( wp_unslash( $_FILES['file_upload']['name'] ) ) ) ) : '';
					if ( $upload_size > get_max_upload_size() ) {
						/* translators: %s: the maximum upload size. */
						$text = '<p style="color: red;">' . sprintf( __( 'File Size Too Large. Maximum Size Is %s', 'wp-downloadmanager' ), format_filesize( get_max_upload_size() ) ) . '</p>';
						break;
					} elseif ( is_uploaded_file( $tmp_name ) ) {
							$file_upload_to = ! empty( $_POST['file_upload_to'] ) ? sanitize_text_field( wp_unslash( $_POST['file_upload_to'] ) ) : '';
							$file_upload_to = DownloadManager_File::safe_subfolder( $file_path, $file_upload_to );
						if ( '/' !== $file_upload_to ) {
							$file_upload_to = $file_upload_to . '/';
						}
							$validate = wp_check_filetype_and_ext( $tmp_name, $upload_name );
						if ( false === $validate['type'] ) {
								$text = '<p style="color: red;">' . __( 'File type is invalid', 'wp-downloadmanager' ) . '</p>';
								break;
						}
						if ( move_uploaded_file( $tmp_name, $file_path . $file_upload_to . $upload_name ) ) {
							$file      = $file_upload_to . $upload_name;
							$file      = DownloadManager_File::rename_file( $file_path, $file );
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
				if ( $file_type > -1 ) {
					if ( empty( $file_name ) ) {
						$file_name = basename( $file );
					}
				}
				$file_des           = ! empty( $_POST['file_des'] ) ? trim( wp_kses_post( wp_unslash( $_POST['file_des'] ) ) ) : '';
				$file_category      = ! empty( $_POST['file_cat'] ) ? intval( $_POST['file_cat'] ) : 0;
				$file_hits          = ! empty( $_POST['file_hits'] ) ? intval( $_POST['file_hits'] ) : 0;
				$edit_filetimestamp = ! empty( $_POST['edit_filetimestamp'] ) ? intval( $_POST['edit_filetimestamp'] ) : 0;
				$auto_filesize      = ! empty( $_POST['auto_filesize'] ) ? intval( $_POST['auto_filesize'] ) : 0;
				if ( 0 === $auto_filesize ) {
					$file_size = ! empty( $_POST['file_size'] ) ? intval( $_POST['file_size'] ) : 0;
				}
				$reset_filehits    = ! empty( $_POST['reset_filehits'] ) ? intval( $_POST['reset_filehits'] ) : 0;
				$file_permission   = ! empty( $_POST['file_permission'] ) ? intval( $_POST['file_permission'] ) : 0;
				$file_updated_date = current_time( 'timestamp' );

				// Built by hand and interpolated before, which both double slashed
				// every text value on the way in and left the write unprepared.
				$file_data = array(
					'file_name'         => $file_name,
					'file_des'          => $file_des,
					'file_size'         => $file_size,
					'file_category'     => $file_category,
					'file_permission'   => $file_permission,
					'file_updated_date' => $file_updated_date,
					'file_hits'         => ( 1 === $reset_filehits ) ? 0 : $file_hits,
				);
				if ( $file_type > -1 ) {
					$file_data['file'] = $file;
				}
				if ( 1 === $edit_filetimestamp ) {
					$file_timestamp_day     = ! empty( $_POST['file_timestamp_day'] ) ? intval( $_POST['file_timestamp_day'] ) : 0;
					$file_timestamp_month   = ! empty( $_POST['file_timestamp_month'] ) ? intval( $_POST['file_timestamp_month'] ) : 0;
					$file_timestamp_year    = ! empty( $_POST['file_timestamp_year'] ) ? intval( $_POST['file_timestamp_year'] ) : 0;
					$file_timestamp_hour    = ! empty( $_POST['file_timestamp_hour'] ) ? intval( $_POST['file_timestamp_hour'] ) : 0;
					$file_timestamp_minute  = ! empty( $_POST['file_timestamp_minute'] ) ? intval( $_POST['file_timestamp_minute'] ) : 0;
					$file_timestamp_second  = ! empty( $_POST['file_timestamp_second'] ) ? intval( $_POST['file_timestamp_second'] ) : 0;
					$file_data['file_date'] = gmmktime( $file_timestamp_hour, $file_timestamp_minute, $file_timestamp_second, $file_timestamp_month, $file_timestamp_day, $file_timestamp_year );
				}
				$editfile = $wpdb->update( $wpdb->downloads, $file_data, array( 'file_id' => $file_id ) );
				// update() returns 0 when the row already held these values, which
				// is a successful save rather than the failure the old !$editfile
				// check reported.
				if ( false === $editfile ) {
					/* translators: 1: file name, 2: file path. */
					$text = '<p style="color: red;">' . sprintf( __( 'Error In Editing File \'%1$s (%2$s)\'', 'wp-downloadmanager' ), $file_name, $file ) . '</p>';
				} else {
					/* translators: 1: file name, 2: file path. */
					$text = '<p style="color: green;">' . sprintf( __( 'File \'%1$s (%2$s)\' Edited Successfully', 'wp-downloadmanager' ), $file_name, $file ) . '</p>';
				}
			}
			break;
		// Delete a file.
		case __( 'Delete File', 'wp-downloadmanager' ):
			check_admin_referer( 'wp-downloadmanager_delete-file' );
			$file_id    = ! empty( $_POST['file_id'] ) ? intval( $_POST['file_id'] ) : 0;
			$file       = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->downloads WHERE file_id = %d", $file_id ) );
			$unlinkfile = ! empty( $_POST['unlinkfile'] ) ? intval( $_POST['unlinkfile'] ) : 0;
			if ( 1 === $unlinkfile ) {
				if ( ! wp_delete_file_from_directory( $file_path . $file->file, $file_path ) ) {
					/* translators: 1: file name, 2: file path. */
					$text = '<p style="color: red;">' . sprintf( __( 'Error In Deleting File \'%1$s (%2$s)\' From Server', 'wp-downloadmanager' ), $file->file_name, $file->file ) . '</p>';
				} else {
					/* translators: 1: file name, 2: file path. */
					$text = '<p style="color: green;">' . sprintf( __( 'File \'%1$s (%2$s)\' Deleted From Server Successfully', 'wp-downloadmanager' ), $file->file_name, $file->file ) . '</p>';
				}
			}
			$deletefile = $wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->downloads WHERE file_id = %d", $file->file_id ) );
			if ( ! $deletefile ) {
				/* translators: 1: file name, 2: file path. */
				$text .= '<p style="color: red;">' . sprintf( __( 'Error In Deleting File \'%1$s (%2$s)\'', 'wp-downloadmanager' ), $file->file_name, $file->file ) . '</p>';
			} else {
				/* translators: 1: file name, 2: file path. */
				$text .= '<p style="color: green;">' . sprintf( __( 'File \'%1$s (%2$s)\' Deleted Successfully', 'wp-downloadmanager' ), $file->file_name, $file->file ) . '</p>';
			}
			break;
	}
}


// Determine which mode this is.
switch ( $dl_mode ) {
	// Edit a file.
	case 'edit':
		$file = $wpdb->get_row( "SELECT * FROM $wpdb->downloads WHERE file_id = $file_id" );
		?>
		<?php
		if ( ! empty( $text ) ) {
			echo '<!-- Last Action --><div id="message" class="updated fade"><p>' . wp_kses_post( stripslashes( $text ) ) . '</p></div>'; }
		?>
		<!-- Edit A File -->
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . WP_DOWNLOADMANAGER_SLUG . '/includes/' . basename( __FILE__ ) . '&mode=edit&id=' . intval( $file->file_id ) ) ); ?>" enctype="multipart/form-data">
			<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo esc_attr( get_max_upload_size() ); ?>" />
			<input type="hidden" name="file_id" value="<?php echo intval( $file->file_id ); ?>" />
			<input type="hidden" name="old_file" value="<?php echo esc_attr( stripslashes( $file->file ) ); ?>" />
			<?php wp_nonce_field( 'wp-downloadmanager_edit-file' ); ?>
			<div class="wrap">
				<h2><?php esc_html_e( 'Edit A File', 'wp-downloadmanager' ); ?></h2>
				<table class="form-table">
					<tr>
						<td valign="top"><strong><?php esc_html_e( 'File:', 'wp-downloadmanager' ); ?></strong></td>
						<td>
							<!-- File Name -->
							<input type="radio" id="file_type_-1" name="file_type" value="-1" checked="checked" />&nbsp;&nbsp;<label for="file_type_-1"><?php esc_html_e( 'Current File:', 'wp-downloadmanager' ); ?>&nbsp;<strong dir="ltr"><?php echo esc_html( stripslashes( $file->file ) ); ?></strong></label>&nbsp;
							<br /><br />
							<!-- Browse File -->
							<input type="radio" id="file_type_0" name="file_type" value="0" />&nbsp;&nbsp;<label for="file_type_0"><?php esc_html_e( 'Browse File:', 'wp-downloadmanager' ); ?></label>&nbsp;
							<select name="file" size="1" data-checks="file_type_0" dir="ltr">
								<?php DownloadManager_Admin::print_files( $file_path, $file_path, stripslashes( $file->file ) ); ?>
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
								<?php DownloadManager_Admin::print_folders( $file_path, $file_path ); ?>
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
						<td><input type="text" size="50" maxlength="200" name="file_name" value="<?php echo esc_attr( stripslashes( $file->file_name ) ); ?>" /></td>
					</tr>
					<tr>
						<td valign="top"><strong><?php esc_html_e( 'File Description:', 'wp-downloadmanager' ); ?></strong></td>
						<td><textarea rows="5" cols="50" name="file_des"><?php echo esc_attr( stripslashes( $file->file_des ) ); ?></textarea></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'File Category:', 'wp-downloadmanager' ); ?></strong></td>
						<td>
							<select name="file_cat" size="1">
								<?php
								$category_count = count( $file_categories );
								for ( $i = 0; $i < $category_count; $i++ ) {
									if ( ! empty( $file_categories[ $i ] ) ) {
										if ( intval( $file->file_category ) === $i ) {
											printf( '<option value="%1$s" selected="selected">%2$s</option>' . "\n", esc_attr( $i ), esc_html( $file_categories[ $i ] ) );
										} else {
											printf( '<option value="%1$s">%2$s</option>' . "\n", esc_attr( $i ), esc_html( $file_categories[ $i ] ) );
										}
									}
								}
								?>
							</select>
						</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'File Size:', 'wp-downloadmanager' ); ?></strong></td>
						<td><?php echo esc_html( format_filesize( $file->file_size ) ); ?><br /><input type="text" size="10" name="file_size" value="<?php echo esc_attr( $file->file_size ); ?>" />&nbsp;<?php esc_html_e( 'bytes', 'wp-downloadmanager' ); ?><br /><input type="checkbox" id="auto_filesize" name="auto_filesize" value="1" checked="checked" />&nbsp;<label for="auto_filesize"><?php esc_html_e( 'Auto Detection Of File Size', 'wp-downloadmanager' ); ?></label></td>
					</tr>
					<tr>
						<td valign="top"><strong><?php esc_html_e( 'File Hits:', 'wp-downloadmanager' ); ?></strong></td>
						<td>
						<?php
						/* translators: %s: number of hits. */
						printf( esc_html( _n( '%s hit', '%s hits', (int) $file->file_hits, 'wp-downloadmanager' ) ), esc_html( number_format_i18n( $file->file_hits ) ) );
						?>
						<br /><input type="text" size="6" maxlength="10" name="file_hits" value="<?php echo esc_attr( $file->file_hits ); ?>" /><br /><input type="checkbox" id="reset_filehits" name="reset_filehits" value="1" />&nbsp;<label for="reset_filehits"><?php esc_html_e( 'Reset File Hits', 'wp-downloadmanager' ); ?></label></td>
					</tr>
					<tr>
						<td valign="top"><strong><?php esc_html_e( 'File Date:', 'wp-downloadmanager' ); ?></strong></td>
						<td><?php esc_html_e( 'Existing Timestamp:', 'wp-downloadmanager' ); ?> <?php echo esc_html( mysql2date( sprintf( '%s @ %s', get_option( 'date_format' ), get_option( 'time_format' ) ), gmdate( 'Y-m-d H:i:s', $file->file_date ) ) ); ?><br /><?php DownloadManager_Admin::file_timestamp( $file->file_date ); ?><br /><input type="checkbox" id="edit_filetimestamp" name="edit_filetimestamp" value="1" />&nbsp;<label for="edit_filetimestamp"><?php esc_html_e( 'Edit Timestamp', 'wp-downloadmanager' ); ?></label>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type="checkbox" id="edit_usetodaydate" value="1"
							data-actual="
							<?php
							echo esc_attr(
								wp_json_encode(
									array(
										'day'    => (int) gmdate( 'j', $file->file_date ),
										'month'  => (int) gmdate( 'n', $file->file_date ),
										'year'   => (int) gmdate( 'Y', $file->file_date ),
										'hour'   => (int) gmdate( 'G', $file->file_date ),
										'minute' => (int) gmdate( 'i', $file->file_date ),
										'second' => (int) gmdate( 's', $file->file_date ),
									)
								)
							);
							?>
											"
							data-today="
							<?php
							echo esc_attr(
								wp_json_encode(
									array(
										'day'    => (int) gmdate( 'j', current_time( 'timestamp' ) ),
										'month'  => (int) gmdate( 'n', current_time( 'timestamp' ) ),
										'year'   => (int) gmdate( 'Y', current_time( 'timestamp' ) ),
										'hour'   => (int) gmdate( 'G', current_time( 'timestamp' ) ),
										'minute' => (int) gmdate( 'i', current_time( 'timestamp' ) ),
										'second' => (int) gmdate( 's', current_time( 'timestamp' ) ),
									)
								)
							);
							?>
										" />&nbsp;<label for="edit_usetodaydate"><?php esc_html_e( 'Use Today\'s Date', 'wp-downloadmanager' ); ?></label></td>
					</tr>
					<tr>
						<td valign="top"><strong><?php esc_html_e( 'File Updated Date:', 'wp-downloadmanager' ); ?></strong></td>
						<td><?php echo esc_html( mysql2date( sprintf( '%s @ %s', get_option( 'date_format' ), get_option( 'time_format' ) ), gmdate( 'Y-m-d H:i:s', $file->file_updated_date ) ) ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'File Last Downloaded Date:', 'wp-downloadmanager' ); ?></strong></td>
						<td><?php echo esc_html( mysql2date( sprintf( '%s @ %s', get_option( 'date_format' ), get_option( 'time_format' ) ), gmdate( 'Y-m-d H:i:s', $file->file_last_downloaded_date ) ) ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Allowed To Download:', 'wp-downloadmanager' ); ?></strong></td>
						<td>
							<select name="file_permission" size="1">
								<option value="-2" <?php selected( '-2', $file->file_permission ); ?>><?php esc_html_e( 'Hidden', 'wp-downloadmanager' ); ?></option>
								<option value="-1" <?php selected( '-1', $file->file_permission ); ?>><?php esc_html_e( 'Everyone', 'wp-downloadmanager' ); ?></option>
								<option value="0" <?php selected( '0', $file->file_permission ); ?>><?php esc_html_e( 'Registered Users Only', 'wp-downloadmanager' ); ?></option>
								<option value="1" <?php selected( '1', $file->file_permission ); ?>><?php esc_html_e( 'At Least Contributor Role', 'wp-downloadmanager' ); ?></option>
								<option value="2" <?php selected( '2', $file->file_permission ); ?>><?php esc_html_e( 'At Least Author Role', 'wp-downloadmanager' ); ?></option>
								<option value="7" <?php selected( '7', $file->file_permission ); ?>><?php esc_html_e( 'At Least Editor Role', 'wp-downloadmanager' ); ?></option>
								<option value="10" <?php selected( '10', $file->file_permission ); ?>><?php esc_html_e( 'At Least Administrator Role', 'wp-downloadmanager' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<td colspan="2" style="text-align: center;"><input type="submit" name="do" value="<?php esc_html_e( 'Edit File', 'wp-downloadmanager' ); ?>"  class="button" />&nbsp;&nbsp;<button type="button" name="cancel" class="button download-cancel"><?php esc_html_e( 'Cancel', 'wp-downloadmanager' ); ?></button></td>
					</tr>
				</table>
			</div>
		</form>
		<?php
		break;
	// Delete a file.
	case 'delete':
		$file = $wpdb->get_row( "SELECT * FROM $wpdb->downloads WHERE file_id = $file_id" );
		?>
		<?php
		if ( ! empty( $text ) ) {
			echo '<!-- Last Action --><div id="message" class="updated fade"><p>' . wp_kses_post( stripslashes( $text ) ) . '</p></div>'; }
		?>
		<!-- Delete A File -->
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . WP_DOWNLOADMANAGER_SLUG . '/includes/' . basename( __FILE__ ) ) ); ?>">
			<input type="hidden" name="file_id" value="<?php echo esc_attr( intval( $file->file_id ) ); ?>" />
			<?php wp_nonce_field( 'wp-downloadmanager_delete-file' ); ?>
			<div class="wrap">
				<h2><?php esc_html_e( 'Delete A File', 'wp-downloadmanager' ); ?></h2>
				<br style="clear" />
				<table class="widefat">
					<tr>
						<td valign="top"><strong><?php esc_html_e( 'File:', 'wp-downloadmanager' ); ?></strong></td>
						<td><span dir="ltr"><?php echo esc_html( stripslashes( $file->file ) ); ?></span></td>
					</tr>
					<tr class="alternate">
						<td><strong><?php esc_html_e( 'File Name:', 'wp-downloadmanager' ); ?></strong></td>
						<td><?php echo wp_kses_post( stripslashes( $file->file_name ) ); ?></td>
					</tr>
					<tr>
						<td valign="top"><strong><?php esc_html_e( 'File Description:', 'wp-downloadmanager' ); ?></strong></td>
						<td><?php echo wp_kses_post( stripslashes( $file->file_des ) ); ?></td>
					</tr>
					<tr class="alternate">
						<td><strong><?php esc_html_e( 'File Category:', 'wp-downloadmanager' ); ?></strong></td>
						<td><?php echo esc_html( download_category_name( $file_categories, $file->file_category ) ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'File Size:', 'wp-downloadmanager' ); ?></strong></td>
						<td><?php echo esc_html( format_filesize( $file->file_size ) ); ?></td>
					</tr>
					<tr class="alternate">
						<td><strong><?php esc_html_e( 'File Hits', 'wp-downloadmanager' ); ?></strong></td>
						<td><?php echo esc_html( number_format_i18n( $file->file_hits ) ); ?> <?php esc_html_e( 'hits', 'wp-downloadmanager' ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'File Date', 'wp-downloadmanager' ); ?></strong></td>
						<td><?php echo esc_html( mysql2date( sprintf( '%s @ %s', get_option( 'date_format' ), get_option( 'time_format' ) ), gmdate( 'Y-m-d H:i:s', $file->file_date ) ) ); ?></td>
					</tr>
					<tr class="alternate">
						<td><strong><?php esc_html_e( 'File Updated Date:', 'wp-downloadmanager' ); ?></strong></td>
						<td><?php echo esc_html( mysql2date( sprintf( '%s @ %s', get_option( 'date_format' ), get_option( 'time_format' ) ), gmdate( 'Y-m-d H:i:s', $file->file_updated_date ) ) ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'File Last Downloaded Date:', 'wp-downloadmanager' ); ?></strong></td>
						<td><?php echo esc_html( mysql2date( sprintf( '%s @ %s', get_option( 'date_format' ), get_option( 'time_format' ) ), gmdate( 'Y-m-d H:i:s', $file->file_last_downloaded_date ) ) ); ?></td>
					</tr>
					<tr class="alternate">
						<td><strong><?php esc_html_e( 'Allowed To Download:', 'wp-downloadmanager' ); ?></strong></td>
						<td><?php echo esc_html( file_permission( $file->file_permission ) ); ?></td>
					</tr>
					<?php if ( ! is_remote_file( stripslashes( $file->file ) ) ) : ?>
						<tr>
							<td colspan="2" style="text-align: center;"><input type="checkbox" id="unlinkfile" name="unlinkfile" value="1" />&nbsp;<label for="unlinkfile"><?php esc_html_e( 'Delete File From Server?', 'wp-downloadmanager' ); ?></label></td>
						</tr>
					<?php endif; ?>
					<tr class="alternate">
						<td colspan="2" style="text-align: center;"><input type="submit" name="do" value="<?php esc_attr_e( 'Delete File', 'wp-downloadmanager' ); ?>" class="button"
							data-confirm="
							<?php
								/* translators: 1: file name, 2: file path. */
								echo esc_attr( sprintf( __( "You Are About To The Delete This File '%1\$s (%2\$s)'.\nThis Action Is Not Reversible.\n\n Choose 'Cancel' to stop, 'OK' to delete.", 'wp-downloadmanager' ), stripslashes( $file->file_name ), stripslashes( $file->file ) ) );
							?>
							" />&nbsp;&nbsp;<button type="button" name="cancel" class="button download-cancel"><?php esc_html_e( 'Cancel', 'wp-downloadmanager' ); ?></button></td>
					</tr>
				</table>
			</div>
		</form>
		<?php
		break;
	// The listing.
	default:
		// Totals for the stats panel.
		$get_total_files = $wpdb->get_var( "SELECT COUNT(file_id) FROM $wpdb->downloads WHERE 1=1 $file_search_query" );
		$total_file      = $wpdb->get_var( "SELECT COUNT(file_id) FROM $wpdb->downloads WHERE 1=1" );
		// Cast: SUM() is NULL on an empty table, and passing that to
		// number_format() is deprecated on PHP 8.1 and later.
		$total_bandwidth = (int) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(file_hits*file_size) AS total_bandwidth FROM {$wpdb->downloads} WHERE file_size != %s", __( 'unknown', 'wp-downloadmanager' ) ) );
		$total_filesize  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(file_size) AS total_filesize FROM {$wpdb->downloads} WHERE file_size != %s", __( 'unknown', 'wp-downloadmanager' ) ) );
		$total_filehits  = (int) $wpdb->get_var( "SELECT SUM(file_hits) AS total_filehits FROM $wpdb->downloads" );

		// Normalise the page and per-page values.
		if ( empty( $file_page ) || 0 === $file_page ) {
			$file_page = 1; }
		if ( empty( $offset ) ) {
			$offset = 0; }
		if ( empty( $file_perpage ) || 0 === $file_perpage ) {
			$file_perpage = 20; }

		// Work out the offset.
		$offset = ( $file_page - 1 ) * $file_perpage;

		// The last record shown on this page.
		if ( ( $offset + $file_perpage ) > $get_total_files ) {
			$max_on_page = $get_total_files;
		} else {
			$max_on_page = ( $offset + $file_perpage );
		}

		// The first record shown on this page.
		if ( ( $offset + 1 ) > ( $get_total_files ) ) {
			$display_on_page = $get_total_files;
		} else {
			$display_on_page = ( $offset + 1 );
		}

		// Total number of pages.
		$total_pages = ceil( $get_total_files / $file_perpage );

		// Fetch the page of files.
		$files = $wpdb->get_results( "SELECT * FROM $wpdb->downloads WHERE 1=1 $file_search_query ORDER BY $file_sortby $file_sortorder LIMIT $offset, $file_perpage" );
		?>
		<?php
		if ( ! empty( $text ) ) {
			echo '<!-- Last Action --><div id="message" class="updated fade"><p>' . wp_kses_post( stripslashes( $text ) ) . '</p></div>'; }
		?>
		<!-- Manage Downloads -->
		<div class="wrap">
			<h2><?php esc_html_e( 'Manage Downloads', 'wp-downloadmanager' ); ?></h2>
			<h3><?php esc_html_e( 'Downloads', 'wp-downloadmanager' ); ?></h3>
			<p>
			<?php
			/* translators: 1: first record shown, 2: last record shown, 3: total number of files. */
			printf( wp_kses_post( __( 'Displaying <strong>%1$s</strong> To <strong>%2$s</strong> Of <strong>%3$s</strong> Files', 'wp-downloadmanager' ) ), esc_html( number_format_i18n( $display_on_page ) ), esc_html( number_format_i18n( $max_on_page ) ), esc_html( number_format_i18n( $get_total_files ) ) );
			?>
			</p>
			<p>
			<?php
			/* translators: 1: column the list is sorted by, 2: sort direction. */
			printf( wp_kses_post( __( 'Sorted By <strong>%1$s</strong> In <strong>%2$s</strong> Order', 'wp-downloadmanager' ) ), esc_html( $file_sortby_text ), esc_html( $file_sortorder_text ) );
			?>
			</p>
			<table class="widefat">
				<thead>
				<tr>
					<th><?php esc_html_e( 'ID', 'wp-downloadmanager' ); ?></th>
					<th><?php esc_html_e( 'File', 'wp-downloadmanager' ); ?></th>
					<th><?php esc_html_e( 'Size', 'wp-downloadmanager' ); ?></th>
					<th><?php esc_html_e( 'Hits', 'wp-downloadmanager' ); ?></th>
					<th><?php esc_html_e( 'Permission', 'wp-downloadmanager' ); ?></th>
					<th><?php esc_html_e( 'Category', 'wp-downloadmanager' ); ?></th>
					<th><?php esc_html_e( 'Date/Time Added', 'wp-downloadmanager' ); ?></th>
					<th colspan="2"><?php esc_html_e( 'Action', 'wp-downloadmanager' ); ?></th>
				</tr>
				</thead>
				<?php
				if ( $files ) {

					$i = 0;
					foreach ( $files as $file ) {
						$file_id                   = intval( $file->file_id );
						$file_name                 = stripslashes( $file->file );
						$file_nicename             = wp_kses_post( stripslashes( $file->file_name ) );
						$file_des                  = stripslashes( $file->file_des );
						$file_size                 = $file->file_size;
						$file_cat                  = intval( $file->file_category );
						$file_category             = ! empty( $file_categories[ $file_cat ] ) ? $file_categories[ $file_cat ] : __( 'N/A', 'wp-downloadmanager' );
						$file_date                 = mysql2date( get_option( 'date_format' ), gmdate( 'Y-m-d H:i:s', $file->file_date ) );
						$file_time                 = mysql2date( get_option( 'time_format' ), gmdate( 'Y-m-d H:i:s', $file->file_date ) );
						$file_updated_date         = mysql2date( get_option( 'date_format' ), gmdate( 'Y-m-d H:i:s', $file->file_updated_date ) );
						$file_updated_time         = mysql2date( get_option( 'time_format' ), gmdate( 'Y-m-d H:i:s', $file->file_updated_date ) );
						$file_last_downloaded_date = mysql2date( get_option( 'date_format' ), gmdate( 'Y-m-d H:i:s', $file->file_last_downloaded_date ) );
						$file_last_downloaded_time = mysql2date( get_option( 'time_format' ), gmdate( 'Y-m-d H:i:s', $file->file_last_downloaded_date ) );
						$file_hits                 = intval( $file->file_hits );
						$file_permission           = file_permission( $file->file_permission );
						$file_name_actual          = basename( $file_name );
						if ( 0 === $i % 2 ) {
							$style = '';
						} else {
							$style = ' class="alternate"';
						}
						printf( '<tr%s>' . "\n", '' === $style ? '' : ' class="alternate"' );
						printf( '<td valign="top">%s</td>' . "\n", esc_html( number_format_i18n( $file_id ) ) );

						// The nice name is the one field allowed to carry markup:
						// it is stored through wp_kses_post() and rendered with the
						// same allow list, rather than escaped twice.
						echo '<td>' . wp_kses_post( $file_nicename );
						echo '<br /><strong>&raquo;</strong> <i dir="ltr">' . esc_html( snippet_text( $file_name, 45 ) ) . '</i><br /><br /><i>';
						printf(
							/* translators: 1: time the file was last updated, 2: date it was last updated. */
							esc_html__( 'Last Updated: %1$s, %2$s', 'wp-downloadmanager' ),
							esc_html( $file_updated_time ),
							esc_html( $file_updated_date )
						);
						echo '</i><br /><i>';
						printf(
							/* translators: 1: time the file was last downloaded, 2: date it was last downloaded. */
							esc_html__( 'Last Downloaded: %1$s, %2$s', 'wp-downloadmanager' ),
							esc_html( $file_last_downloaded_time ),
							esc_html( $file_last_downloaded_date )
						);
						echo '</i></td>' . "\n";

						printf( '<td style="text-align: center;">%s</td>' . "\n", esc_html( format_filesize( $file_size ) ) );
						printf( '<td style="text-align: center;">%s</td>' . "\n", esc_html( number_format_i18n( $file_hits ) ) );
						printf( '<td style="text-align: center;">%s</td>' . "\n", esc_html( $file_permission ) );
						printf( '<td style="text-align: center;">%s</td>' . "\n", esc_html( $file_category ) );
						printf( '<td>%1$s, %2$s</td>' . "\n", esc_html( $file_time ), esc_html( $file_date ) );
						printf(
							'<td style="text-align: center;"><a href="%1$s" class="edit">%2$s</a></td>' . "\n",
							esc_url( $base_page . '&mode=edit&id=' . $file_id ),
							esc_html__( 'Edit', 'wp-downloadmanager' )
						);
						printf(
							'<td style="text-align: center;"><a href="%1$s" class="delete">%2$s</a></td>' . "\n",
							esc_url( $base_page . '&mode=delete&id=' . $file_id ),
							esc_html__( 'Delete', 'wp-downloadmanager' )
						);
						echo '</tr>';
						++$i;
					}
				} else {
					echo '<tr><td colspan="9" style="text-align: center;"><strong>' . esc_html__( 'No Files Found', 'wp-downloadmanager' ) . '</strong></td></tr>';
				}
				?>
			</table>
			<!-- <Paging> -->
			<?php
			if ( $total_pages > 1 ) {
				?>
				<br />
				<table class="widefat">
					<tr>
						<td style="text-align: <?php echo is_rtl() ? 'right' : 'left'; ?>" width="50%">
							<?php
							if ( $file_page > 1 && ( ( ( $file_page * $file_perpage ) - ( $file_perpage - 1 ) ) <= $get_total_files ) ) {
								printf(
									'<strong>&laquo;</strong> <a href="%1$s" title="&laquo; %2$s">%2$s</a>',
									esc_url( $base_page . '&filepage=' . ( $file_page - 1 ) . $file_sort_url ),
									esc_attr__( 'Previous Page', 'wp-downloadmanager' )
								);
							} else {
								echo '&nbsp;';
							}
							?>
						</td>
						<td style="text-align: <?php echo is_rtl() ? 'left' : 'right'; ?>" width="50%">
							<?php
							if ( $file_page >= 1 && ( ( ( $file_page * $file_perpage ) + 1 ) <= $get_total_files ) ) {
								printf(
									'<a href="%1$s" title="%2$s &raquo;">%2$s</a> <strong>&raquo;</strong>',
									esc_url( $base_page . '&filepage=' . ( $file_page + 1 ) . $file_sort_url ),
									esc_attr__( 'Next Page', 'wp-downloadmanager' )
								);
							} else {
								echo '&nbsp;';
							}
							?>
						</td>
					</tr>
					<tr class="alternate">
						<td colspan="2" style="text-align: center;">
							<?php esc_html_e( 'Pages', 'wp-downloadmanager' ); ?> (<?php echo esc_html( number_format_i18n( $total_pages ) ); ?>):
							<?php
							if ( $file_page >= 4 ) {
								printf(
									'<strong><a href="%1$s" title="%2$s">&laquo; %3$s</a></strong> ... ',
									esc_url( $base_page . '&filepage=1' . $file_sort_url ),
									esc_attr__( 'Go to First Page', 'wp-downloadmanager' ),
									esc_html__( 'First', 'wp-downloadmanager' )
								);
							}
							if ( $file_page > 1 ) {
								printf(
									' <strong><a href="%1$s" title="&laquo; %2$s %3$s">&laquo;</a></strong> ',
									esc_url( $base_page . '&filepage=' . ( $file_page - 1 ) . $file_sort_url ),
									esc_attr__( 'Go to Page', 'wp-downloadmanager' ),
									esc_attr( number_format_i18n( $file_page - 1 ) )
								);
							}
							for ( $i = $file_page - 2; $i <= $file_page + 2; $i++ ) {
								if ( $i >= 1 && $i <= $total_pages ) {
									if ( $i === $file_page ) {
										echo '<strong>[' . esc_html( number_format_i18n( $i ) ) . ']</strong> ';
									} else {
										printf(
											'<a href="%1$s" title="%2$s %3$s">%3$s</a> ',
											esc_url( $base_page . '&filepage=' . $i . $file_sort_url ),
											esc_attr__( 'Page', 'wp-downloadmanager' ),
											esc_html( number_format_i18n( $i ) )
										);
									}
								}
							}
							if ( $file_page < $total_pages ) {
								printf(
									' <strong><a href="%1$s" title="%2$s %3$s &raquo;">&raquo;</a></strong> ',
									esc_url( $base_page . '&filepage=' . ( $file_page + 1 ) . $file_sort_url ),
									esc_attr__( 'Go to Page', 'wp-downloadmanager' ),
									esc_attr( number_format_i18n( $file_page + 1 ) )
								);
							}
							if ( ( $file_page + 2 ) < $total_pages ) {
								printf(
									' ... <strong><a href="%1$s" title="%2$s">%3$s &raquo;</a></strong>',
									esc_url( $base_page . '&filepage=' . $total_pages . $file_sort_url ),
									esc_attr__( 'Go to Last Page', 'wp-downloadmanager' ),
									esc_html__( 'Last', 'wp-downloadmanager' )
								);
							}
							?>
						</td>
					</tr>
				</table>
				<!-- </Paging> -->
				<?php
			}
			?>
			<br />
			<form action="<?php echo esc_url( admin_url( 'admin.php?page=' . WP_DOWNLOADMANAGER_SLUG . '/includes/' . basename( __FILE__ ) ) ); ?>" method="get">
				<table class="widefat">
					<tr>
						<th><?php esc_html_e( 'Filter Options: ', 'wp-downloadmanager' ); ?></th>
						<td><?php esc_html_e( 'Keywords:', 'wp-downloadmanager' ); ?><input type="text" name="search" size="30" maxlength="200" value="<?php echo esc_attr( $file_search ); ?>" /></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Sort Options:', 'wp-downloadmanager' ); ?></th>
						<td>
							<input type="hidden" name="page" value="<?php echo esc_attr( $base_name ); ?>" />
							<select name="by" size="1">
								<option value="id"
								<?php
								if ( 'file_id' === $file_sortby ) {
									echo ' selected="selected"'; }
								?>
								><?php esc_html_e( 'File ID', 'wp-downloadmanager' ); ?></option>
								<option value="file"
								<?php
								if ( 'file' === $file_sortby ) {
									echo ' selected="selected"'; }
								?>
								><?php esc_html_e( 'File', 'wp-downloadmanager' ); ?></option>
								<option value="name"
								<?php
								if ( 'file_name' === $file_sortby ) {
									echo ' selected="selected"'; }
								?>
								><?php esc_html_e( 'File Name', 'wp-downloadmanager' ); ?></option>
								<option value="date"
								<?php
								if ( 'FROM_UNIXTIME(file_date)' === $file_sortby ) {
									echo ' selected="selected"'; }
								?>
								><?php esc_html_e( 'File Date', 'wp-downloadmanager' ); ?></option>
								<option value="updated_date"
								<?php
								if ( 'FROM_UNIXTIME(updated_date)' === $file_sortby ) {
									echo ' selected="selected"'; }
								?>
								><?php esc_html_e( 'File Updated Date', 'wp-downloadmanager' ); ?></option>
								<option value="last_downloaded_date"
								<?php
								if ( 'FROM_UNIXTIME(last_downloaded_date)' === $file_sortby ) {
									echo ' selected="selected"'; }
								?>
								><?php esc_html_e( 'File Last Downloaded Date', 'wp-downloadmanager' ); ?></option>
								<option value="size"
								<?php
								if ( '(file_size+0.00)' === $file_sortby ) {
									echo ' selected="selected"'; }
								?>
								><?php esc_html_e( 'File Size', 'wp-downloadmanager' ); ?></option>
								<option value="category"
								<?php
								if ( 'file_category' === $file_sortby ) {
									echo ' selected="selected"'; }
								?>
								><?php esc_html_e( 'File Category', 'wp-downloadmanager' ); ?></option>
								<option value="hits"
								<?php
								if ( 'file_hits' === $file_sortby ) {
									echo ' selected="selected"'; }
								?>
								><?php esc_html_e( 'File Hits', 'wp-downloadmanager' ); ?></option>
								<option value="permission"
								<?php
								if ( 'file_permission' === $file_sortby ) {
									echo ' selected="selected"'; }
								?>
								><?php esc_html_e( 'File Permission', 'wp-downloadmanager' ); ?></option>
							</select>
							&nbsp;&nbsp;&nbsp;
							<select name="order" size="1">
								<option value="asc"
								<?php
								if ( 'ASC' === $file_sortorder ) {
									echo ' selected="selected"'; }
								?>
								><?php esc_html_e( 'Ascending', 'wp-downloadmanager' ); ?></option>
								<option value="desc"
								<?php
								if ( 'DESC' === $file_sortorder ) {
									echo ' selected="selected"'; }
								?>
								><?php esc_html_e( 'Descending', 'wp-downloadmanager' ); ?></option>
							</select>
							&nbsp;&nbsp;&nbsp;
							<select name="perpage" size="1">
								<?php
								for ( $k = 10; $k <= 100; $k += 10 ) {
									if ( $k === $file_perpage ) {
										printf( '<option value="%1$s" selected="selected">%2$s: %3$s</option>' . "\n", esc_attr( $k ), esc_html__( 'Per Page', 'wp-downloadmanager' ), esc_html( number_format_i18n( $k ) ) );
									} else {
										printf( '<option value="%1$s">%2$s: %3$s</option>' . "\n", esc_attr( $k ), esc_html__( 'Per Page', 'wp-downloadmanager' ), esc_html( number_format_i18n( $k ) ) );
									}
								}
								?>
							</select>
						</td>
					</tr>
					<tr>
						<td colspan="2" style="text-align: center;"><input type="submit" value="<?php esc_html_e( 'Go', 'wp-downloadmanager' ); ?>" class="button" /></td>
					</tr>
				</table>
			</form>
		</div>
		<p>&nbsp;</p>

		<!-- Download Stats -->
		<div class="wrap">
			<h3><?php esc_html_e( 'Download Stats', 'wp-downloadmanager' ); ?></h3>
			<br style="clear" />
			<table class="widefat">
				<tr>
					<th><?php esc_html_e( 'Total Files:', 'wp-downloadmanager' ); ?></th>
					<td><?php echo esc_html( number_format_i18n( $total_file ) ); ?></td>
				</tr>
				<tr class="alternate">
					<th><?php esc_html_e( 'Total Size:', 'wp-downloadmanager' ); ?></th>
					<td><?php echo esc_html( format_filesize( $total_filesize ) ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Total Hits:', 'wp-downloadmanager' ); ?></th>
					<td><?php echo esc_html( number_format_i18n( $total_filehits ) ); ?></td>
				</tr>
				<tr class="alternate">
					<th><?php esc_html_e( 'Total Bandwidth:', 'wp-downloadmanager' ); ?></th>
					<td><?php echo esc_html( format_filesize( $total_bandwidth ) ); ?></td>
				</tr>
			</table>
		</div>
		<?php
} // End of the mode switch.
?>
