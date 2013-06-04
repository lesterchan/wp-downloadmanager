<?php
/*
+-------------------------------------------------------------------+
|																	|
|	WordPress Plugin: WP-DownloadManager							|
|	Copyright (c) 2013 Lester "GaMerZ" Chan							|
|																	|
|	File Written By:												|
|	- Lester "GaMerZ" Chan											|
|	- http://lesterchan.net											|
|																	|
|	File Information:												|
|	- Manage Your Downloads											|
|	- wp-content/plugins/wp-downloadmanager/download-manager.php	|
|																	|
+-------------------------------------------------------------------+
*/


### Check Whether User Can Manage Downloads
if(!current_user_can('manage_downloads')) {
	die('Access Denied');
}


### Variables Variables Variables
$base_name = plugin_basename('wp-downloadmanager/download-manager.php');
$base_page = 'admin.php?page='.$base_name;
$mode = trim($_GET['mode']);
$file_id = intval($_GET['id']);
$file_path = get_option('download_path');
$file_categories = get_option('download_categories');
$file_page = intval($_GET['filepage']);
$file_sortby = trim($_GET['by']);
$file_sortby_text = '';
$file_sortorder = trim($_GET['order']);
$file_sortorder_text = '';
$file_perpage = intval($_GET['perpage']);
$file_sort_url = '';
$file_search = addslashes($_GET['search']);
$file_search_query = '';


### Form Sorting URL
if(!empty($file_sortby)) {
	$file_sort_url .= '&amp;by='.$file_sortby;
}
if(!empty($file_sortorder)) {
	$file_sort_url .= '&amp;order='.$file_sortorder;
}
if(!empty($file_perpage)) {
	$file_sort_url .= '&amp;perpage='.$file_perpage;
}


### Searching
if(!empty($file_search)) {
	$file_search_query = "AND (file LIKE ('%$file_search%') OR file_name LIKE('%$file_search%') OR file_des LIKE ('%$file_search%'))";
	$file_sort_url .= '&amp;search='.stripslashes($file_search);
}


### Get Order By
switch($file_sortby) {
	case 'id':
		$file_sortby = 'file_id';
		$file_sortby_text = __('File ID', 'wp-downloadmanager');
		break;
	case 'file':
		$file_sortby = 'file';
		$file_sortby_text = __('File', 'wp-downloadmanager');
		break;
	case 'size':
		$file_sortby = '(file_size+0.00)';
		$file_sortby_text = __('File Size', 'wp-downloadmanager');
		break;
	case 'category':
		$file_sortby = 'file_category';
		$file_sortby_text = __('File Category', 'wp-downloadmanager');
		break;
	case 'hits':
		$file_sortby = 'file_hits';
		$file_sortby_text = __('File Hits', 'wp-downloadmanager');
		break;
	case 'permission':
		$file_sortby = 'file_permission';
		$file_sortby_text = __('File Permission', 'wp-downloadmanager');
		break;
	case 'date':
		$file_sortby = 'file_date';
		$file_sortby_text = __('File Date', 'wp-downloadmanager');
		break;
	case 'updated_date':
		$file_sortby = 'file_updated_date';
		$file_sortby_text = __('File Updated Date', 'wp-downloadmanager');
		break;
	case 'last_downloaded_date':
		$file_sortby = 'file_last_downloaded_date';
		$file_sortby_text = __('File Last Downloaded Date', 'wp-downloadmanager');
		break;
	case 'name':
	default:
		$file_sortby = 'file_name';
		$file_sortby_text = __('File Name', 'wp-downloadmanager');
}


### Get Sort Order
switch($file_sortorder) {
	case 'desc':
		$file_sortorder = 'DESC';
		$file_sortorder_text = __('Descending', 'wp-downloadmanager');
		break;
	case 'asc':
	default:
		$file_sortorder = 'ASC';
		$file_sortorder_text = __('Ascending', 'wp-downloadmanager');
}


### Form Processing
if(!empty($_POST['do'])) {
	// Decide What To Do
	switch($_POST['do']) {
		// Edit File
		case __('Edit File', 'wp-downloadmanager'):
			check_admin_referer('wp-downloadmanager_edit-file');
			$file_size_sql = '';
			$file_sql = '';
			$file_id  = intval($_POST['file_id']);
			$file_type = intval($_POST['file_type']);
			$file_name = addslashes(trim($_POST['file_name']));
			switch($file_type) {
				case -1:
					$file = $_POST['old_file'];
					if(is_remote_file($file)) {
						$file_size = remote_filesize($file);
						if ($file_size == 'unknown') {
							$file_size = 0;
						}
					} else {
						$file_size = filesize($file_path.$file);
					}
					break;
				case 0:
					$file = addslashes(trim($_POST['file']));
					$file = download_rename_file($file_path, $file);
					$file_size = filesize($file_path.$file);
					break;
				case 1:
					if($_FILES['file_upload']['size'] > get_max_upload_size()) {
						$text = '<font color="red">'.sprintf(__('File Size Too Large. Maximum Size Is %s', 'wp-downloadmanager'), format_filesize(get_max_upload_size())).'</font>';
						break;
					} else {
						if(is_uploaded_file($_FILES['file_upload']['tmp_name'])) {
							if($_POST['file_upload_to'] == '/') {
								$file_upload_to = '/';
							} else {
								$file_upload_to = $_POST['file_upload_to'].'/';
							}
							if(move_uploaded_file($_FILES['file_upload']['tmp_name'], $file_path.$file_upload_to.basename($_FILES['file_upload']['name']))) {
								$file = $file_upload_to.basename($_FILES['file_upload']['name']);
								$file = download_rename_file($file_path, $file);
								$file_size = filesize($file_path.$file);
							} else {
								$text = '<font color="red">'.__('Error In Uploading File', 'wp-downloadmanager').'</font>';
								break;
							}
						} else {
							$text = '<font color="red">'.__('Error In Uploading File', 'wp-downloadmanager').'</font>';
							break;
						}
					}
					break;
				case 2:
					$file = addslashes(trim($_POST['file_remote']));
					$file_size = remote_filesize($file);
					break;
			}
			if($file_type > -1) {
				$file_sql = "file = '$file',";
				if(empty($file_name)) {
					$file_name = basename($file);
				}
			}
			$file_des = addslashes(trim($_POST['file_des']));
			$file_category = intval($_POST['file_cat']);
			$file_hits = intval($_POST['file_hits']);
			$edit_filetimestamp = intval($_POST['edit_filetimestamp']);
			if(intval($_POST['auto_filesize']) == 0) {
				$file_size = intval($_POST['file_size']);
			}
			$file_size_sql = "file_size = '$file_size',";
			$reset_filehits = intval($_POST['reset_filehits']);
			$hits_sql = '';
			if($reset_filehits == 1) {
				$hits_sql = ', file_hits = 0';
			} else {
				$hits_sql = ", file_hits = $file_hits";
			}
			$timestamp_sql = '';
			if($edit_filetimestamp == 1) {
				$file_timestamp_day = intval($_POST['file_timestamp_day']);
				$file_timestamp_month = intval($_POST['file_timestamp_month']);
				$file_timestamp_year = intval($_POST['file_timestamp_year']);
				$file_timestamp_hour = intval($_POST['file_timestamp_hour']);
				$file_timestamp_minute = intval($_POST['file_timestamp_minute']);
				$file_timestamp_second = intval($_POST['file_timestamp_second']);
				$timestamp_sql = ", file_date = '".gmmktime($file_timestamp_hour, $file_timestamp_minute, $file_timestamp_second, $file_timestamp_month, $file_timestamp_day, $file_timestamp_year)."'";
			}
			$file_permission = intval($_POST['file_permission']);
			$file_updated_date = current_time('timestamp');
			$editfile = $wpdb->query("UPDATE $wpdb->downloads SET $file_sql file_name = '$file_name', file_des = '$file_des', $file_size_sql file_category = $file_category, file_permission = $file_permission, file_updated_date = '$file_updated_date' $timestamp_sql $hits_sql WHERE file_id = $file_id;");
			if(!$editfile) {
				$text = '<font color="red">'.sprintf(__('Error In Editing File \'%s (%s)\'', 'wp-downloadmanager'), $file_name, $file).'</font>';
			} else {
				$text = '<font color="green">'.sprintf(__('File \'%s (%s)\' Edited Successfully', 'wp-downloadmanager'), $file_name, $file).'</font>';
			}
			break;
		// Delete File
		case __('Delete File', 'wp-downloadmanager');
			check_admin_referer('wp-downloadmanager_delete-file');
			$file_id  = intval($_POST['file_id']);
			$file = trim($_POST['file']);
			$file_name = trim($_POST['file_name']);
			$unlinkfile = intval($_POST['unlinkfile']);
			if($unlinkfile == 1) {
				if(!unlink($file_path.$file)) {
					$text = '<font color="red">'.sprintf(__('Error In Deleting File \'%s (%s)\' From Server', 'wp-downloadmanager'), $file_name, $file).'</font><br />';
				} else {
					$text = '<font color="green">'.sprintf(__('File \'%s (%s)\' Deleted From Server Successfully', 'wp-downloadmanager'), $file_name, $file).'</font><br />';
				}
			}
			$deletefile = $wpdb->query("DELETE FROM $wpdb->downloads WHERE file_id = $file_id");
			if(!$deletefile) {
				$text .= '<font color="red">'.sprintf(__('Error In Deleting File \'%s (%s)\'', 'wp-downloadmanager'), $file_name, $file).'</font>';
			} else {
				$text .= '<font color="green">'.sprintf(__('File \'%s (%s)\' Deleted Successfully', 'wp-downloadmanager'), $file_name, $file).'</font>';
			}
			break;
	}
}


### Determines Which Mode It Is
switch($mode) {
	// Edit A File
	case 'edit':
		$file = $wpdb->get_row("SELECT * FROM $wpdb->downloads WHERE file_id = $file_id");
?>
		<script type="text/javascript">
			/* <![CDATA[*/
			var actual_day = "<?php echo gmdate('j', $file->file_date); ?>";
			var actual_month = "<?php echo gmdate('n', $file->file_date); ?>";
			var actual_year = "<?php echo gmdate('Y', $file->file_date); ?>";
			var actual_hour = "<?php echo gmdate('G', $file->file_date); ?>";
			var actual_minute = "<?php echo intval(gmdate('i', $file->file_date)); ?>";
			var actual_second = "<?php echo intval(gmdate('s', $file->file_date)); ?>";
			function file_usetodaydate() {
				if(jQuery('#edit_usetodaydate').is(':checked')) {
					jQuery('#edit_filetimestamp').attr('checked', true);
					jQuery('#file_timestamp_day').val("<?php echo gmdate('j', current_time('timestamp')); ?>");
					jQuery('#file_timestamp_month').val("<?php echo gmdate('n', current_time('timestamp')); ?>");
					jQuery('#file_timestamp_year').val("<?php echo gmdate('Y', current_time('timestamp')); ?>");
					jQuery('#file_timestamp_hour').val("<?php echo gmdate('G', current_time('timestamp')); ?>");
					jQuery('#file_timestamp_minute').val("<?php echo intval(gmdate('i', current_time('timestamp'))); ?>");
					jQuery('#file_timestamp_second').val("<?php echo intval(gmdate('s', current_time('timestamp'))); ?>");
				} else {
					jQuery('#edit_filetimestamp').attr('checked', false);
					jQuery('#file_timestamp_day').val(actual_day);
					jQuery('#file_timestamp_month').val(actual_month);
					jQuery('#file_timestamp_year').val(actual_year);
					jQuery('#file_timestamp_hour').val(actual_hour);
					jQuery('#file_timestamp_minute').val(actual_minute);
					jQuery('#file_timestamp_second').val(actual_second);
				}
			}
			/* ]]> */
		</script>
		<?php if(!empty($text)) { echo '<!-- Last Action --><div id="message" class="updated fade"><p>'.stripslashes($text).'</p></div>'; } ?>
		<!-- Edit A File -->
		<form method="post" action="<?php echo admin_url('admin.php?page='.plugin_basename(__FILE__).'&amp;mode=edit&amp;id='.intval($file->file_id)); ?>" enctype="multipart/form-data">
			<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo get_max_upload_size(); ?>" />
			<input type="hidden" name="file_id" value="<?php echo intval($file->file_id); ?>" />
			<input type="hidden" name="old_file" value="<?php echo stripslashes($file->file); ?>" />
			<?php wp_nonce_field('wp-downloadmanager_edit-file'); ?>
			<div class="wrap">
				<div id="icon-wp-downloadmanager" class="icon32"><br /></div>
				<h2><?php _e('Edit A File', 'wp-downloadmanager'); ?></h2>
				<table class="form-table">
					<tr>
						<td valign="top"><strong><?php _e('File:', 'wp-downloadmanager') ?></strong></td>
						<td>
							<!-- File Name -->
							<input type="radio" id="file_type_-1" name="file_type" value="-1" checked="checked" />&nbsp;&nbsp;<label for="file_type_-1"><?php _e('Current File:', 'wp-downloadmanager'); ?>&nbsp;<strong dir="ltr"><?php echo stripslashes($file->file); ?></strong></label>&nbsp;
							<br /><br />
							<!-- Browse File -->
							<input type="radio" id="file_type_0" name="file_type" value="0" />&nbsp;&nbsp;<label for="file_type_0"><?php _e('Browse File:', 'wp-downloadmanager'); ?></label>&nbsp;
							<select name="file" size="1" onclick="document.getElementById('file_type_0').checked = true;" dir="ltr">
								<?php print_list_files($file_path, $file_path, stripslashes($file->file)); ?>
							</select>
							<br /><small><?php printf(__('Please upload the file to \'%s\' directory first.', 'wp-downloadmanager'), $file_path); ?></small>
							<br /><br />
							<!-- Upload File -->
							<input type="radio" id="file_type_1" name="file_type" value="1" />&nbsp;&nbsp;<label for="file_type_1"><?php _e('Upload File:', 'wp-downloadmanager'); ?></label>&nbsp;
							<input type="file" name="file_upload" size="25" onclick="document.getElementById('file_type_1').checked = true;" dir="ltr" />&nbsp;&nbsp;<?php _e('to', 'wp-downloadmanager'); ?>&nbsp;&nbsp;
							<select name="file_upload_to" size="1" onclick="document.getElementById('file_type_1').checked = true;" dir="ltr">
								<?php print_list_folders($file_path, $file_path); ?>
							</select>
							<br /><small><?php printf(__('Maximum file size is %s.', 'wp-downloadmanager'), format_filesize(get_max_upload_size())); ?></small>
							<!-- Remote File -->
							<br /><br />
							<input type="radio" id="file_type_2" name="file_type" value="2" />&nbsp;&nbsp;<label for="file_type_2"><?php _e('Remote File:', 'wp-downloadmanager'); ?></label>&nbsp;
							<input type="text" name="file_remote" size="50" maxlength="255" onclick="document.getElementById('file_type_2').checked = true;" value="http://" dir="ltr" />
							<br /><small><?php _e('Please include http:// or ftp:// in front.', 'wp-downloadmanager'); ?></small>
						</td>
					</tr>
					<tr>
						<td><strong><?php _e('File Name:', 'wp-downloadmanager'); ?></strong></td>
						<td><input type="text" size="50" maxlength="200" name="file_name" value="<?php echo htmlspecialchars(stripslashes($file->file_name)); ?>" /></td>
					</tr>
					<tr>
						<td valign="top"><strong><?php _e('File Description:', 'wp-downloadmanager'); ?></strong></td>
						<td><textarea rows="5" cols="50" name="file_des"><?php echo htmlspecialchars(stripslashes($file->file_des)); ?></textarea></td>
					</tr>
					<tr>
						<td><strong><?php _e('File Category:', 'wp-downloadmanager'); ?></strong></td>
						<td>
							<select name="file_cat" size="1">
								<?php
									for($i = 0; $i<sizeof($file_categories); $i++) {
										if(!empty($file_categories[$i])) {
											if($i == intval($file->file_category)) {
												echo '<option value="'.$i.'" selected="selected">'.$file_categories[$i].'</option>'."\n";
											} else {
												echo '<option value="'.$i.'">'.$file_categories[$i].'</option>'."\n";
											}
										}
									}
								?>
							</select>
						</td>
					</tr>
					<tr>
						<td><strong><?php _e('File Size:', 'wp-downloadmanager') ?></strong></td>
						<td><?php echo format_filesize($file->file_size); ?><br /><input type="text" size="10" name="file_size" value="<?php echo $file->file_size; ?>" />&nbsp;<?php _e('bytes', 'wp-downloadmanager'); ?><br /><input type="checkbox" id="auto_filesize" name="auto_filesize" value="1" checked="checked" />&nbsp;<label for="auto_filesize"><?php _e('Auto Detection Of File Size', 'wp-downloadmanager') ?></label></td>
					</tr>
					<tr>
						<td valign="top"><strong><?php _e('File Hits:', 'wp-downloadmanager') ?></strong></td>
						<td><?php printf(_n('%s hit', '%s hits', number_format_i18n($file->file_hits),'wp-downloadmanager'), number_format_i18n($file->file_hits)) ?><br /><input type="text" size="6" maxlength="10" name="file_hits" value="<?php echo $file->file_hits; ?>" /><br /><input type="checkbox" id="reset_filehits" name="reset_filehits" value="1" />&nbsp;<label for="reset_filehits"><?php _e('Reset File Hits', 'wp-downloadmanager') ?></label></td>
					</tr>
					<tr>
						<td valign="top"><strong><?php _e('File Date:', 'wp-downloadmanager') ?></strong></td>
						<td><?php _e('Existing Timestamp:', 'wp-downloadmanager') ?> <?php echo mysql2date(sprintf('%s @ %s', get_option('date_format'), get_option('time_format')), gmdate('Y-m-d H:i:s', $file->file_date)); ?><br /><?php file_timestamp($file->file_date); ?><br /><input type="checkbox" id="edit_filetimestamp" name="edit_filetimestamp" value="1" />&nbsp;<label for="edit_filetimestamp"><?php _e('Edit Timestamp', 'wp-downloadmanager') ?></label>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type="checkbox" id="edit_usetodaydate" value="1" onclick="file_usetodaydate();" />&nbsp;<label for="edit_usetodaydate"><?php _e('Use Today\'s Date', 'wp-downloadmanager') ?></label></td>
					</tr>
					<tr>
						<td valign="top"><strong><?php _e('File Updated Date:', 'wp-downloadmanager') ?></strong></td>
						<td><?php echo mysql2date(sprintf('%s @ %s', get_option('date_format'), get_option('time_format')), gmdate('Y-m-d H:i:s', $file->file_updated_date)); ?></td>
					</tr>
					<tr>
						<td><strong><?php _e('File Last Downloaded Date:', 'wp-downloadmanager') ?></strong></td>
						<td><?php echo mysql2date(sprintf('%s @ %s', get_option('date_format'), get_option('time_format')), gmdate('Y-m-d H:i:s', $file->file_last_downloaded_date)); ?></td>
					</tr>
					<tr>
						<td><strong><?php _e('Allowed To Download:', 'wp-downloadmanager') ?></strong></td>
						<td>
							<select name="file_permission" size="1">
								<option value="-2" <?php selected('-2', $file->file_permission); ?>><?php _e('Hidden', 'wp-downloadmanager'); ?></option>
								<option value="-1" <?php selected('-1', $file->file_permission); ?>><?php _e('Everyone', 'wp-downloadmanager'); ?></option>
								<option value="0" <?php selected('0', $file->file_permission); ?>><?php _e('Registered Users Only', 'wp-downloadmanager'); ?></option>
								<option value="1" <?php selected('1', $file->file_permission); ?>><?php _e('At Least Contributor Role', 'wp-downloadmanager'); ?></option>
								<option value="2" <?php selected('2', $file->file_permission); ?>><?php _e('At Least Author Role', 'wp-downloadmanager'); ?></option>
								<option value="7" <?php selected('7', $file->file_permission); ?>><?php _e('At Least Editor Role', 'wp-downloadmanager'); ?></option>
								<option value="10" <?php selected('10', $file->file_permission); ?>><?php _e('At Least Administrator Role', 'wp-downloadmanager'); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<td colspan="2" align="center"><input type="submit" name="do" value="<?php _e('Edit File', 'wp-downloadmanager'); ?>"  class="button" />&nbsp;&nbsp;<input type="button" name="cancel" value="<?php _e('Cancel', 'wp-downloadmanager'); ?>" class="button" onclick="javascript:history.go(-1)" /></td>
					</tr>
				</table>
			</div>
		</form>
<?php
		break;
	// Delete A File
	case 'delete':
		$file = $wpdb->get_row("SELECT * FROM $wpdb->downloads WHERE file_id = $file_id");
?>
		<?php if(!empty($text)) { echo '<!-- Last Action --><div id="message" class="updated fade"><p>'.stripslashes($text).'</p></div>'; } ?>
		<!-- Delete A File -->
		<form method="post" action="<?php echo admin_url('admin.php?page='.plugin_basename(__FILE__)); ?>">
			<input type="hidden" name="file_id" value="<?php echo intval($file->file_id); ?>" />
			<input type="hidden" name="file" value="<?php echo stripslashes($file->file); ?>" />
			<input type="hidden" name="file_name" value="<?php echo htmlspecialchars(stripslashes($file->file_name)); ?>" />
			<?php wp_nonce_field('wp-downloadmanager_delete-file'); ?>
			<div class="wrap">
				<div id="icon-wp-downloadmanager" class="icon32"><br /></div>
				<h2><?php _e('Delete A File', 'wp-downloadmanager'); ?></h2>
				<br style="clear" />
				<table class="widefat">
					<tr>
						<td valign="top"><strong><?php _e('File:', 'wp-downloadmanager') ?></strong></td>
						<td><span dir="ltr"><?php echo stripslashes($file->file); ?></span></td>
					</tr>
					<tr class="alternate">
						<td><strong><?php _e('File Name:', 'wp-downloadmanager'); ?></strong></td>
						<td><?php echo stripslashes($file->file_name); ?></td>
					</tr>
					<tr>
						<td valign="top"><strong><?php _e('File Description:', 'wp-downloadmanager'); ?></strong></td>
						<td><?php echo stripslashes($file->file_des); ?></td>
					</tr>
					<tr class="alternate">
						<td><strong><?php _e('File Category:', 'wp-downloadmanager'); ?></strong></td>
						<td><?php echo $file_categories[intval($file->file_category)]; ?></td>
					</tr>
					<tr>
						<td><strong><?php _e('File Size:', 'wp-downloadmanager'); ?></strong></td>
						<td><?php echo format_filesize($file->file_size); ?></td>
					</tr>
					<tr class="alternate">
						<td><strong><?php _e('File Hits', 'wp-downloadmanager'); ?></strong></td>
						<td><?php echo number_format_i18n($file->file_hits); ?> <?php _e('hits', 'wp-downloadmanager'); ?></td>
					</tr>
					<tr>
						<td><strong><?php _e('File Date', 'wp-downloadmanager'); ?></strong></td>
						<td><?php echo mysql2date(sprintf('%s @ %s', get_option('date_format'), get_option('time_format')), gmdate('Y-m-d H:i:s', $file->file_date)); ?></td>
					</tr>
					<tr class="alternate">
						<td><strong><?php _e('File Updated Date:', 'wp-downloadmanager') ?></strong></td>
						<td><?php echo mysql2date(sprintf('%s @ %s', get_option('date_format'), get_option('time_format')), gmdate('Y-m-d H:i:s', $file->file_updated_date)); ?></td>
					</tr>
					<tr>
						<td><strong><?php _e('File Last Downloaded Date:', 'wp-downloadmanager') ?></strong></td>
						<td><?php echo mysql2date(sprintf('%s @ %s', get_option('date_format'), get_option('time_format')), gmdate('Y-m-d H:i:s', $file->file_last_downloaded_date)); ?></td>
					</tr>
					<tr class="alternate">
						<td><strong><?php _e('Allowed To Download:', 'wp-downloadmanager') ?></strong></td>
						<td><?php echo file_permission($file->file_permission); ?></td>
					</tr>
					<?php if(!is_remote_file(stripslashes($file->file))): ?>
					<tr>
						<td colspan="2" align="center"><input type="checkbox" id="unlinkfile" name="unlinkfile" value="1" />&nbsp;<label for="unlinkfile"><?php _e('Delete File From Server?', 'wp-downloadmanager'); ?></label></td>
					</tr>
					<?php endif; ?>
					<tr class="alternate">
						<td colspan="2" align="center"><input type="submit" name="do" value="<?php _e('Delete File', 'wp-downloadmanager'); ?>" class="button"  onclick="return confirm('You Are About To The Delete This File \'<?php echo stripslashes(strip_tags($file->file_name)); ?> (<?php echo stripslashes($file->file); ?>)\'.\nThis Action Is Not Reversible.\n\n Choose \'Cancel\' to stop, \'OK\' to delete.')"/>&nbsp;&nbsp;<input type="button" name="cancel" value="<?php _e('Cancel', 'wp-downloadmanager'); ?>" class="button" onclick="javascript:history.go(-1)" /></td>
					</tr>
				</table>
			</div>
		</form>
<?php
		break;
	// Main Page
	default:
		### Get Total Files
		$get_total_files = $wpdb->get_var("SELECT COUNT(file_id) FROM $wpdb->downloads WHERE 1=1 $file_search_query");
		$total_file = $wpdb->get_var("SELECT COUNT(file_id) FROM $wpdb->downloads WHERE 1=1");
		$total_bandwidth = $wpdb->get_var("SELECT SUM(file_hits*file_size) AS total_bandwidth FROM $wpdb->downloads WHERE file_size != '".__('unknown', 'wp-downloadmanager')."'");
		$total_filesize = $wpdb->get_var("SELECT SUM(file_size) AS total_filesize FROM $wpdb->downloads WHERE file_size != '".__('unknown', 'wp-downloadmanager')."'");
		$total_filehits = $wpdb->get_var("SELECT SUM(file_hits) AS total_filehits FROM $wpdb->downloads");

		### Checking $file_page and $offset
		if(empty($file_page) || $file_page == 0) { $file_page = 1; }
		if(empty($offset)) { $offset = 0; }
		if(empty($file_perpage) || $file_perpage == 0) { $file_perpage = 20; }

		### Determin $offset
		$offset = ($file_page-1) * $file_perpage;

		### Determine Max Number Of Polls To Display On Page
		if(($offset + $file_perpage) > $get_total_files) {
			$max_on_page = $get_total_files;
		} else {
			$max_on_page = ($offset + $file_perpage);
		}

		### Determine Number Of Polls To Display On Page
		if (($offset + 1) > ($get_total_files)) {
			$display_on_page = $get_total_files;
		} else {
			$display_on_page = ($offset + 1);
		}

		### Determing Total Amount Of Pages
		$total_pages = ceil($get_total_files / $file_perpage);

		### Get Files
		$files = $wpdb->get_results("SELECT * FROM $wpdb->downloads WHERE 1=1 $file_search_query ORDER BY $file_sortby $file_sortorder LIMIT $offset, $file_perpage");
?>
		<?php if(!empty($text)) { echo '<!-- Last Action --><div id="message" class="updated fade"><p>'.stripslashes($text).'</p></div>'; } ?>
		<!-- Manage Downloads -->
		<div class="wrap">
			<div id="icon-wp-downloadmanager" class="icon32"><br /></div>
			<h2><?php _e('Manage Downloads', 'wp-downloadmanager'); ?></h2>
			<h3><?php _e('Downloads', 'wp-downloadmanager'); ?></h3>
			<p><?php printf(__('Displaying <strong>%s</strong> To <strong>%s</strong> Of <strong>%s</strong> Files', 'wp-downloadmanager'), number_format_i18n($display_on_page), number_format_i18n($max_on_page), number_format_i18n($get_total_files)); ?></p>
			<p><?php printf(__('Sorted By <strong>%s</strong> In <strong>%s</strong> Order', 'wp-downloadmanager'), $file_sortby_text, $file_sortorder_text); ?></p>
			<table class="widefat">
				<thead>
					<tr>
						<th><?php _e('ID', 'wp-downloadmanager'); ?></th>
						<th><?php _e('File', 'wp-downloadmanager'); ?></th>
						<th><?php _e('Size', 'wp-downloadmanager'); ?></th>
						<th><?php _e('Hits', 'wp-downloadmanager'); ?></th>
						<th><?php _e('Permission', 'wp-downloadmanager'); ?></th>
						<th><?php _e('Category', 'wp-downloadmanager'); ?></th>
						<th><?php _e('Date/Time Added', 'wp-downloadmanager'); ?></th>
						<th colspan="2"><?php _e('Action', 'wp-downloadmanager'); ?></th>
					</tr>
				</thead>
			<?php
				if($files) {;
					$i = 0;
					foreach($files as $file) {
						$file_id = intval($file->file_id);
						$file_name = stripslashes($file->file);
						$file_nicename = stripslashes($file->file_name);
						$file_des = stripslashes($file->file_des);
						$file_size = $file->file_size;
						$file_cat = intval($file->file_category);
						$file_date = mysql2date(get_option('date_format'), gmdate('Y-m-d H:i:s', $file->file_date));
						$file_time = mysql2date(get_option('time_format'), gmdate('Y-m-d H:i:s', $file->file_date));
						$file_updated_date = mysql2date(get_option('date_format'), gmdate('Y-m-d H:i:s', $file->file_updated_date));
						$file_updated_time = mysql2date(get_option('time_format'), gmdate('Y-m-d H:i:s', $file->file_updated_date));
						$file_last_downloaded_date = mysql2date(get_option('date_format'), gmdate('Y-m-d H:i:s', $file->file_last_downloaded_date));
						$file_last_downloaded_time = mysql2date(get_option('time_format'), gmdate('Y-m-d H:i:s', $file->file_last_downloaded_date));
						$file_hits = intval($file->file_hits);
						$file_permission = file_permission($file->file_permission);
						$file_name_actual = basename($file_name);
						if($i%2 == 0) {
							$style = '';
						}  else {
							$style = ' class="alternate"';
						}
						echo "<tr$style>\n";
						echo '<td valign="top">'.number_format_i18n($file_id).'</td>'."\n";
						echo "<td>$file_nicename<br /><strong>&raquo;</strong> <i dir=\"ltr\">".snippet_text($file_name, 45)."</i><br /><br /><i>".sprintf(__('Last Updated: %s, %s', 'wp-downloadmanager'), $file_updated_time, $file_updated_date)."</i><br /><i>".sprintf(__('Last Downloaded: %s, %s', 'wp-downloadmanager'), $file_last_downloaded_time, $file_last_downloaded_date)."</i></td>\n";
						echo '<td style="text-align: center;">'.format_filesize($file_size).'</td>'."\n";
						echo '<td style="text-align: center;">'.number_format_i18n($file_hits).'</td>'."\n";
						echo '<td style="text-align: center;">'.$file_permission.'</td>'."\n";
						echo '<td style="text-align: center;">'.$file_categories[$file_cat].'</td>'."\n";
						echo "<td>$file_time, $file_date</td>\n";
						echo "<td style=\"text-align: center;\"><a href=\"$base_page&amp;mode=edit&amp;id=$file_id\" class=\"edit\">".__('Edit', 'wp-downloadmanager')."</a></td>\n";
						echo "<td style=\"text-align: center;\"><a href=\"$base_page&amp;mode=delete&amp;id=$file_id\" class=\"delete\">".__('Delete', 'wp-downloadmanager')."</a></td>\n";
						echo '</tr>';
						$i++;
					}
				} else {
					echo '<tr><td colspan="9" align="center"><strong>'.__('No Files Found', 'wp-downloadmanager').'</strong></td></tr>';
				}
			?>
			</table>
		<!-- <Paging> -->
		<?php
			if($total_pages > 1) {
		?>
		<br />
		<table class="widefat">
			<tr>
				<td align="<?php echo ('rtl' == $text_direction) ? 'right' : 'left'; ?>" width="50%">
					<?php
						if($file_page > 1 && ((($file_page*$file_perpage)-($file_perpage-1)) <= $get_total_files)) {
							echo '<strong>&laquo;</strong> <a href="'.$base_page.'&amp;filepage='.($file_page-1).$file_sort_url.'" title="&laquo; '.__('Previous Page', 'wp-downloadmanager').'">'.__('Previous Page', 'wp-downloadmanager').'</a>';
						} else {
							echo '&nbsp;';
						}
					?>
				</td>
				<td align="<?php echo ('rtl' == $text_direction) ? 'left' : 'right'; ?>" width="50%">
					<?php
						if($file_page >= 1 && ((($file_page*$file_perpage)+1) <= $get_total_files)) {
							echo '<a href="'.$base_page.'&amp;filepage='.($file_page+1).$file_sort_url.'" title="'.__('Next Page', 'wp-downloadmanager').' &raquo;">'.__('Next Page', 'wp-downloadmanager').'</a> <strong>&raquo;</strong>';
						} else {
							echo '&nbsp;';
						}
					?>
				</td>
			</tr>
			<tr class="alternate">
				<td colspan="2" align="center">
					<?php _e('Pages', 'wp-downloadmanager'); ?> (<?php echo number_format_i18n($total_pages); ?>):
					<?php
						if ($file_page >= 4) {
							echo '<strong><a href="'.$base_page.'&amp;filepage=1'.$file_sort_url.'" title="'.__('Go to First Page', 'wp-downloadmanager').'">&laquo; '.__('First', 'wp-downloadmanager').'</a></strong> ... ';
						}
						if($file_page > 1) {
							echo ' <strong><a href="'.$base_page.'&amp;filepage='.($file_page-1).$file_sort_url.'" title="&laquo; '.__('Go to Page', 'wp-downloadmanager').' '.number_format_i18n($file_page-1).'">&laquo;</a></strong> ';
						}
						for($i = $file_page - 2 ; $i  <= $file_page +2; $i++) {
							if ($i >= 1 && $i <= $total_pages) {
								if($i == $file_page) {
									echo '<strong>['.number_format_i18n($i).']</strong> ';
								} else {
									echo '<a href="'.$base_page.'&amp;filepage='.($i).$file_sort_url.'" title="'.__('Page', 'wp-downloadmanager').' '.number_format_i18n($i).'">'.number_format_i18n($i).'</a> ';
								}
							}
						}
						if($file_page < $total_pages) {
							echo ' <strong><a href="'.$base_page.'&amp;filepage='.($file_page+1).$file_sort_url.'" title="'.__('Go to Page', 'wp-downloadmanager').' '.number_format_i18n($file_page+1).' &raquo;">&raquo;</a></strong> ';
						}
						if (($file_page+2) < $total_pages) {
							echo ' ... <strong><a href="'.$base_page.'&amp;filepage='.($total_pages).$file_sort_url.'" title="'.__('Go to Last Page', 'wp-downloadmanager'), 'wp-downloadmanager'.'">'.__('Last', 'wp-downloadmanager').' &raquo;</a></strong>';
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
	<form action="<?php echo admin_url('admin.php?page='.plugin_basename(__FILE__)); ?>" method="get">
		<table class="widefat">
			<tr>
				<th><?php _e('Filter Options: ', 'wp-downloadmanager'); ?></th>
				<td><?php _e('Keywords:', 'wp-downloadmanager'); ?><input type="text" name="search" size="30" maxlength="200" value="<?php echo stripslashes($file_search); ?>" /></td>
			</tr>
			<tr>
				<th><?php _e('Sort Options:', 'wp-downloadmanager'); ?></th>
				<td>
					<input type="hidden" name="page" value="<?php echo $base_name; ?>" />
					<select name="by" size="1">
						<option value="id"<?php if($file_sortby == 'file_id') { echo ' selected="selected"'; }?>><?php _e('File ID', 'wp-downloadmanager'); ?></option>
						<option value="file"<?php if($file_sortby == 'file') { echo ' selected="selected"'; }?>><?php _e('File', 'wp-downloadmanager'); ?></option>
						<option value="name"<?php if($file_sortby == 'file_name') { echo ' selected="selected"'; }?>><?php _e('File Name', 'wp-downloadmanager'); ?></option>
						<option value="date"<?php if($file_sortby == 'file_date') { echo ' selected="selected"'; }?>><?php _e('File Date', 'wp-downloadmanager'); ?></option>
						<option value="updated_date"<?php if($file_sortby == 'updated_date') { echo ' selected="selected"'; }?>><?php _e('File Updated Date', 'wp-downloadmanager'); ?></option>
						<option value="last_downloaded_date"<?php if($file_sortby == 'last_downloaded_date') { echo ' selected="selected"'; }?>><?php _e('File Last Downloaded Date', 'wp-downloadmanager'); ?></option>
						<option value="size"<?php if($file_sortby == '(file_size+0.00)') { echo ' selected="selected"'; }?>><?php _e('File Size', 'wp-downloadmanager'); ?></option>
						<option value="category"<?php if($file_sortby == 'file_category') { echo ' selected="selected"'; }?>><?php _e('File Category', 'wp-downloadmanager'); ?></option>
						<option value="hits"<?php if($file_sortby == 'file_hits') { echo ' selected="selected"'; }?>><?php _e('File Hits', 'wp-downloadmanager'); ?></option>
						<option value="permission"<?php if($file_sortby == 'file_timestamp') { echo ' selected="selected"'; }?>><?php _e('File Permission', 'wp-downloadmanager'); ?></option>
					</select>
					&nbsp;&nbsp;&nbsp;
					<select name="order" size="1">
						<option value="asc"<?php if($file_sortorder == 'ASC') { echo ' selected="selected"'; }?>><?php _e('Ascending', 'wp-downloadmanager'); ?></option>
						<option value="desc"<?php if($file_sortorder == 'DESC') { echo ' selected="selected"'; } ?>><?php _e('Descending', 'wp-downloadmanager'); ?></option>
					</select>
					&nbsp;&nbsp;&nbsp;
					<select name="perpage" size="1">
					<?php
						for($k=10; $k <= 100; $k+=10) {
							if($file_perpage == $k) {
								echo "<option value=\"$k\" selected=\"selected\">".__('Per Page', 'wp-downloadmanager').": ".number_format_i18n($k)."</option>\n";
							} else {
								echo "<option value=\"$k\">".__('Per Page', 'wp-downloadmanager').": ".number_format_i18n($k)."</option>\n";
							}
						}
					?>
					</select>
				</td>
			</tr>
			<tr>
				<td colspan="2" align="center"><input type="submit" value="<?php _e('Go', 'wp-downloadmanager'); ?>" class="button" /></td>
			</tr>
		</table>
	</form>
</div>
<p>&nbsp;</p>

<!-- Download Stats -->
<div class="wrap">
	<h3><?php _e('Download Stats', 'wp-downloadmanager'); ?></h3>
	<br style="clear" />
	<table class="widefat">
		<tr>
			<th><?php _e('Total Files:', 'wp-downloadmanager'); ?></th>
			<td><?php echo number_format_i18n($total_file); ?></td>
		</tr>
		<tr class="alternate">
			<th><?php _e('Total Size:', 'wp-downloadmanager'); ?></th>
			<td><?php echo format_filesize($total_filesize); ?></td>
		</tr>
		<tr>
			<th><?php _e('Total Hits:', 'wp-downloadmanager'); ?></th>
			<td><?php echo number_format_i18n($total_filehits); ?></td>
		</tr>
		<tr class="alternate">
			<th><?php _e('Total Bandwidth:', 'wp-downloadmanager'); ?></th>
			<td><?php echo format_filesize($total_bandwidth); ?></td>
		</tr>
	</table>
</div>
<?php
} // End switch($mode)
?>
