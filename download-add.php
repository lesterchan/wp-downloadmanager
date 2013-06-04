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
|	- Add File Download												|
|	- wp-content/plugins/wp-downloadmanager/download-add.php		|
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
$file_path = get_option('download_path');
$file_categories = get_option('download_categories');


### Form Processing
if(!empty($_POST['do'])) {
	check_admin_referer('wp-downloadmanager_add-file');
	// Decide What To Do
	switch($_POST['do']) {
		// Add File
		case __('Add File', 'wp-downloadmanager'):
			$file_type = intval($_POST['file_type']);
			switch($file_type) {
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
			$file_name = addslashes(trim($_POST['file_name']));
			if(empty($file_name)) {
				$file_name = basename($file);
			}
			$file_des = addslashes(trim($_POST['file_des']));
			$file_category = intval($_POST['file_cat']);
			if(!empty($_POST['file_size'])) {
				$file_size = intval($_POST['file_size']);
			}
			$file_hits = intval($_POST['file_hits']);
			$file_timestamp_day = intval($_POST['file_timestamp_day']);
			$file_timestamp_month = intval($_POST['file_timestamp_month']);
			$file_timestamp_year = intval($_POST['file_timestamp_year']);
			$file_timestamp_hour = intval($_POST['file_timestamp_hour']);
			$file_timestamp_minute = intval($_POST['file_timestamp_minute']);
			$file_timestamp_second = intval($_POST['file_timestamp_second']);
			$file_date = gmmktime($file_timestamp_hour, $file_timestamp_minute, $file_timestamp_second, $file_timestamp_month, $file_timestamp_day, $file_timestamp_year);
			$file_permission = intval($_POST['file_permission']);
			$addfile = $wpdb->query("INSERT INTO $wpdb->downloads VALUES (0, '$file', '$file_name', '$file_des', '$file_size', $file_category, '$file_date', '$file_date', '$file_date', $file_hits, $file_permission)");
			if(!$addfile) {
				$text = '<font color="red">'.sprintf(__('Error In Adding File \'%s (%s)\'', 'wp-downloadmanager'), $file_name, $file).'</font>';
			} else {
				$file_id = intval($wpdb->insert_id);
				$text = '<font color="green">'.sprintf(__('File \'%s (%s) (ID: %s)\' Added Successfully', 'wp-downloadmanager'), $file_name, $file, $file_id).'</font>';
			}
			break;
	}
}
?>
<?php if(!empty($text)) { echo '<!-- Last Action --><div id="message" class="updated fade"><p>'.stripslashes($text).'</p></div>'; } ?>
<!-- Add A File -->
<form method="post" action="<?php echo admin_url('admin.php?page='.plugin_basename(__FILE__)); ?>" enctype="multipart/form-data">
	<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo get_max_upload_size(); ?>" />
	<?php wp_nonce_field('wp-downloadmanager_add-file'); ?>
	<div class="wrap">
		<div id="icon-wp-downloadmanager" class="icon32"><br /></div>
		<h2><?php _e('Add A File', 'wp-downloadmanager'); ?></h2>
		<table class="form-table">
			<tr>
				<td valign="top"><strong><?php _e('File:', 'wp-downloadmanager') ?></strong></td>
				<td>
					<!-- Browse File -->
					<input type="radio" id="file_type_0" name="file_type" value="0" checked="checked" />&nbsp;&nbsp;<label for="file_type_0"><?php _e('Browse File:', 'wp-downloadmanager'); ?></label>&nbsp;
					<select name="file" size="1" onclick="document.getElementById('file_type_0').checked = true;" dir="ltr">
						<?php print_list_files($file_path, $file_path); ?>
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
				<td><input type="text" size="50" maxlength="200" name="file_name" /></td>
			</tr>
			<tr>
				<td valign="top"><strong><?php _e('File Description:', 'wp-downloadmanager'); ?></strong></td>
				<td><textarea rows="5" cols="50" name="file_des"></textarea></td>
			</tr>
			<tr>
				<td><strong><?php _e('File Category:', 'wp-downloadmanager'); ?></strong></td>
				<td>
					<select name="file_cat" size="1">
						<?php
							for($i=0; $i<sizeof($file_categories); $i++) {
								if(!empty($file_categories[$i])) {
									echo '<option value="'.$i.'">'.$file_categories[$i].'</option>'."\n";
								}
							}
						?>
					</select>
				</td>
			</tr>
			<tr>
				<td valign="top"><strong><?php _e('File Size:', 'wp-downloadmanager') ?></strong></td>
				<td><input type="text" size="10" name="file_size" />&nbsp;<?php _e('bytes', 'wp-downloadmanager'); ?><br /><small><?php _e('Leave blank for auto detection. Auto detection sometimes will not work for Remote File.', 'wp-downloadmanager'); ?></small></td>
			</tr>
			<tr>
				<td valign="top"><strong><?php _e('File Date:', 'wp-downloadmanager') ?></strong></td>
				<td><?php file_timestamp(current_time('timestamp')); ?></td>
			</tr>
			<tr>
				<td><strong><?php _e('Starting File Hits:', 'wp-downloadmanager') ?></strong></td>
				<td><input type="text" size="6" maxlength="10" name="file_hits" value="0" /></td>
			</tr>
			<tr>
				<td><strong><?php _e('Allowed To Download:', 'wp-downloadmanager') ?></strong></td>
				<td>
					<select name="file_permission" size="1">
						<option value="-2"><?php _e('Hidden', 'wp-downloadmanager'); ?></option>
						<option value="-1" selected="selected"><?php _e('Everyone', 'wp-downloadmanager'); ?></option>
						<option value="0"><?php _e('Registered Users Only', 'wp-downloadmanager'); ?></option>
						<option value="1"><?php _e('At Least Contributor Role', 'wp-downloadmanager'); ?></option>
						<option value="2"><?php _e('At Least Author Role', 'wp-downloadmanager'); ?></option>
						<option value="7"><?php _e('At Least Editor Role', 'wp-downloadmanager'); ?></option>
						<option value="10"><?php _e('At Least Administrator Role', 'wp-downloadmanager'); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<td colspan="2" align="center"><input type="submit" name="do" value="<?php _e('Add File', 'wp-downloadmanager'); ?>"  class="button" />&nbsp;&nbsp;<input type="button" name="cancel" value="<?php _e('Cancel', 'wp-downloadmanager'); ?>" class="button" onclick="javascript:history.go(-1)" /></td>
			</tr>
		</table>
	</div>
</form>