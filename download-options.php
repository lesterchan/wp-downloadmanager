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
|	- Download Options												|
|	- wp-content/plugins/wp-downloadmanager/download-options.php	|
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


### If Form Is Submitted
if($_POST['Submit']) {
	check_admin_referer('wp-downloadmanager_options');
	$download_path = trim($_POST['download_path']);
	$download_path_url = trim($_POST['download_path_url']);
	$download_page_url = trim($_POST['download_page_url']);
	$download_nice_permalink = intval($_POST['download_nice_permalink']);
	$download_options_use_filename =  intval($_POST['download_options_use_filename']);
	$download_options_rss_sortby =  strip_tags(trim($_POST['download_options_rss_sortby']));
	$download_options_rss_limit =  intval($_POST['download_options_rss_limit']);
	$download_method = intval($_POST['download_method']);
	$download_categories_post = explode("\n", trim($_POST['download_categories']));
	$download_sort_by = strip_tags(trim($_POST['download_sort_by']));
	$download_sort_order = strip_tags(trim($_POST['download_sort_order']));
	$download_sort_perpage = intval($_POST['download_sort_perpage']);
	$download_sort_group = intval($_POST['download_sort_group']);
	$download_sort = array('by' => $download_sort_by, 'order' => $download_sort_order, 'perpage' => $download_sort_perpage, 'group' => $download_sort_group);
	if(!empty($download_categories_post)) {
		$download_categories = array();
		$download_categories[] = '';
		foreach($download_categories_post as $download_category) {
			if(!empty($download_category)) {
				$download_categories[] = trim($download_category);
			}
		}
	}
	$download_options = array('use_filename' => $download_options_use_filename, 'rss_sortby' => $download_options_rss_sortby, 'rss_limit' => $download_options_rss_limit);
	$update_download_queries = array();
	$update_download_text = array();
	$update_download_queries[] = update_option('download_path', $download_path);
	$update_download_queries[] = update_option('download_path_url', $download_path_url);
	$update_download_queries[] = update_option('download_page_url', $download_page_url);
	$update_download_queries[] = update_option('download_nice_permalink', $download_nice_permalink);
	$update_download_queries[] = update_option('download_options', $download_options);
	$update_download_queries[] = update_option('download_method', $download_method);
	$update_download_queries[] = update_option('download_categories', $download_categories);
	$update_download_queries[] = update_option('download_sort', $download_sort);
	$update_download_text[] = __('Download Path', 'wp-downloadmanager');
	$update_download_text[] = __('Download Path URL', 'wp-downloadmanager');
	$update_download_text[] = __('Download Page URL', 'wp-downloadmanager');
	$update_download_text[] = __('Download Nice Permalink', 'wp-downloadmanager');
	$update_download_text[] = __('Download Options', 'wp-downloadmanager');
	$update_download_text[] = __('Download Method', 'wp-downloadmanager');
	$update_download_text[] = __('Download Categories', 'wp-downloadmanager');
	$update_download_text[] = __('Download Sorting', 'wp-downloadmanager');
	$i=0;
	$text = '';
	foreach($update_download_queries as $update_download_query) {
		if($update_download_query) {
			$text .= '<font color="green">'.$update_download_text[$i].' '.__('Updated', 'wp-downloadmanager').'</font><br />';
		}
		$i++;
	}
	if(empty($text)) {
		$text = '<font color="red">'.__('No Download Option Updated', 'wp-downloadmanager').'</font>';
	}
}


### Get File Categories
$download_categories = get_option('download_categories');
$download_categories_display = '';
if(!empty($download_categories)) {
	foreach($download_categories as $download_category) {
		if(!empty($download_category)) {
			$download_categories_display .= $download_category."\n";
		}
	}
}


### Get File Sorting
$download_sort = get_option('download_sort');

### Get File Download Method
$download_method = intval(get_option('download_method'));

### Get Download Options
$download_options = get_option('download_options');
?>
<?php if(!empty($text)) { echo '<!-- Last Action --><div id="message" class="updated fade"><p>'.$text.'</p></div>'; } ?>
<form method="post" action="<?php echo admin_url('admin.php?page='.plugin_basename(__FILE__)); ?>">
	<?php wp_nonce_field('wp-downloadmanager_options'); ?>
	<div class="wrap">
		<div id="icon-wp-downloadmanager" class="icon32"><br /></div>
		<h2><?php _e('Download Options', 'wp-downloadmanager'); ?></h2>
		<h3><?php _e('Download Options', 'wp-downloadmanager'); ?></h3>
		<table class="form-table">
			 <tr valign="top">
				<th><?php _e('Download Path:', 'wp-downloadmanager'); ?></th>
				<td><input type="text" name="download_path" value="<?php echo stripslashes(get_option('download_path')); ?>" size="50" dir="ltr" /><br /><?php _e('The absolute path to the directory where all the files are stored (without trailing slash).', 'wp-downloadmanager'); ?></td>
			</tr>
			 <tr valign="top">
				<th><?php _e('Download Path URL:', 'wp-downloadmanager'); ?></th>
				<td><input type="text" name="download_path_url" value="<?php echo stripslashes(get_option('download_path_url')); ?>" size="50" dir="ltr" /><br /><?php _e('The url to the directory where all the files are stored (without trailing slash).', 'wp-downloadmanager'); ?></td>
			</tr>
			<tr valign="top">
				<th><?php _e('Download Page URL:', 'wp-downloadmanager'); ?></th>
				<td><input type="text" name="download_page_url" value="<?php echo stripslashes(get_option('download_page_url')); ?>" size="50" dir="ltr" /><br /><?php _e('The url to the downloads page (without trailing slash).', 'wp-downloadmanager'); ?></td>
			</tr>
			<tr valign="top">
				<th><?php _e('Download Nice Permalink:', 'wp-downloadmanager'); ?></th>
				<td>
					<input type="radio" id="download_nice_permalink-1" name="download_nice_permalink" value="1"<?php checked('1', get_option('download_nice_permalink')); ?>>&nbsp;<label for="download_nice_permalink-1"><?php _e('Yes', 'wp-downloadmanager'); ?><br /><span dir="ltr">- <?php echo get_option('home'); ?>/download/1/</span><br /><span dir="ltr">- <?php echo get_option('home'); ?>/download/filename.ext</span></label>
					<br />
					<input type="radio" id="download_nice_permalink-0" name="download_nice_permalink" value="0"<?php checked('0', get_option('download_nice_permalink')); ?>>&nbsp;<label for="download_nice_permalink-0"><?php _e('No', 'wp-downloadmanager'); ?><br /><span dir="ltr">- <?php echo get_option('home'); ?>/?dl_id=1</span><br /><span dir="ltr">- <?php echo get_option('home'); ?>/?dl_name=filename.ext</span></label>
					<br />
					<?php _e('Change it to <strong>No</strong> when you encounter 404 error.', 'wp-downloadmanager'); ?>
				</td>
			</tr>
			<tr valign="top">
				<th><?php _e('Use File Name Or File ID In Download URL?', 'wp-downloadmanager'); ?></th>
				<td>
					<input type="radio" id="download_options_use_filename-0" name="download_options_use_filename" value="0"<?php checked('0', $download_options['use_filename']); ?>>&nbsp;<label for="download_options_use_filename-0"><?php _e('File ID', 'wp-downloadmanager'); ?><br /><span dir="ltr">- <?php echo get_option('home'); ?>/download/1/</span><br /><span dir="ltr">- <?php echo get_option('home'); ?>/?dl_id=1</span></label>
					<br />
					<input type="radio" id="download_options_use_filename-1" name="download_options_use_filename" value="1"<?php checked('1', $download_options['use_filename']); ?>>&nbsp;<label for="download_options_use_filename-1"><?php _e('File Name', 'wp-downloadmanager'); ?><br /><span dir="ltr">- <?php echo get_option('home'); ?>/download/filename.ext</span><br /><span dir="ltr">- <?php echo get_option('home'); ?>/?dl_name=filename.ext</span></label>
					<br />
					<?php _e('Change it to <strong>File ID</strong> when you encounter 404 error.', 'wp-downloadmanager'); ?>
				</td>
			</tr>
			<tr valign="top">
				<th><?php _e('Download Method:', 'wp-downloadmanager'); ?></th>
				<td>
					<select name="download_method" size="1">
						<option value="0"<?php selected('0', $download_method); ?>><?php _e('Output File', 'wp-downloadmanager'); ?></option>
						<option value="1"<?php selected('1', $download_method); ?>><?php _e('Redirect To File', 'wp-downloadmanager'); ?></option>
					</select>
					<br /><?php _e('Change it to <strong>Redirect To File</strong> when you have problem with large files.', 'wp-downloadmanager'); ?>
				</td>
			</tr>
			<tr>
				<td valign="top">
					<strong><?php _e('Download Categories:', 'wp-downloadmanager'); ?></strong><br />
					<?php _e('Start each entry on a new line.', 'wp-downloadmanager'); ?><br /><br />
					<?php _e('The <strong>first line</strong> will have a category id of <strong>1</strong>.', 'wp-downloadmanager'); ?><br />
					<?php _e('The <strong>2nd line</strong> will have a category id of <strong>2</strong>.', 'wp-downloadmanager'); ?><br />
					<?php _e('And so on and so forth.', 'wp-downloadmanager'); ?>
				</td>
				<td>
					<textarea cols="30" rows="10" name="download_categories"><?php echo $download_categories_display; ?></textarea>
				</td>
			</tr>
		</table>
		<h3><?php _e('Download Listing Options', 'wp-downloadmanager'); ?></h3>
		<table class="form-table">
			 <tr valign="top">
				<th><?php _e('Sort Downloads By:', 'wp-downloadmanager'); ?></th>
				<td>
					<select name="download_sort_by" size="1">
						<option value="file_id"<?php selected('file_id', $download_sort['by']); ?>><?php _e('File ID', 'wp-downloadmanager'); ?></option>
						<option value="file"<?php selected('file', $download_sort['by']); ?>><?php _e('File', 'wp-downloadmanager'); ?></option>
						<option value="file_name"<?php selected('file_name', $download_sort['by']); ?>><?php _e('File Name', 'wp-downloadmanager'); ?></option>
						<option value="file_size"<?php selected('file_size', $download_sort['by']); ?>><?php _e('File Size', 'wp-downloadmanager'); ?></option>
						<option value="file_date"<?php selected('file_date', $download_sort['by']); ?>><?php _e('File Date', 'wp-downloadmanager'); ?></option>
						<option value="file_hits"<?php selected('file_hits', $download_sort['by']); ?>><?php _e('File Hits', 'wp-downloadmanager'); ?></option>
					</select>
				</td>
			</tr>
			 <tr valign="top">
				<th><?php _e('Sort Order Of Downloads:', 'wp-downloadmanager'); ?></th>
				<td>
					<select name="download_sort_order" size="1">
						<option value="asc"<?php selected('asc', $download_sort['order']); ?>><?php _e('Ascending', 'wp-downloadmanager'); ?></option>
						<option value="desc"<?php selected('desc', $download_sort['order']); ?>><?php _e('Descending', 'wp-downloadmanager'); ?></option>
					</select>
				</td>
			</tr>
			<tr valign="top">
				<th><?php _e('No. Of Downloads Per Page:', 'wp-downloadmanager'); ?></th>
				<td><input type="text" name="download_sort_perpage" value="<?php echo intval($download_sort['perpage']); ?>" size="5" /></td>
			</tr>
			<tr valign="top">
				<th><?php _e('Group By:', 'wp-downloadmanager'); ?></th>
				<td>
					<select name="download_sort_group" size="1">
						<option value="0"<?php selected('0', $download_sort['group']); ?>><?php _e('None', 'wp-downloadmanager'); ?></option>
						<option value="1"<?php selected('1', $download_sort['group']); ?>><?php _e('Categories', 'wp-downloadmanager'); ?></option>
					</select>
				</td>
			</tr>
		</table>
		<h3><?php _e('Download RSS Options', 'wp-downloadmanager'); ?></h3>
		<table class="form-table">
			 <tr valign="top">
				<th><?php _e('Sort Downloads In Feed By:', 'wp-downloadmanager'); ?></th>
				<td>
					<select name="download_options_rss_sortby" size="1">
						<option value="file_id"<?php selected('file_id', $download_options['rss_sortby']); ?>><?php _e('File ID', 'wp-downloadmanager'); ?></option>
						<option value="file_date"<?php selected('file_date', $download_options['rss_sortby']); ?>><?php _e('File Date', 'wp-downloadmanager'); ?></option>
						<option value="file_updated_date"<?php selected('file_updated_date', $download_options['rss_sortby']); ?>><?php _e('File Last Updated Date', 'wp-downloadmanager'); ?></option>
						<option value="file_size"<?php selected('file_size', $download_options['rss_sortby']); ?>><?php _e('File Size', 'wp-downloadmanager'); ?></option>
						<option value="file_hits"<?php selected('file_hits', $download_options['rss_sortby']); ?>><?php _e('File Hits', 'wp-downloadmanager'); ?></option>
					</select>
					<br />
					<?php _e('Sorting are done in descending order.', 'wp-downloadmanager'); ?>
				</td>
			</tr>
			<tr valign="top">
				<th><?php _e('No. Of Downloads In Feed:', 'wp-downloadmanager'); ?></th>
				<td><input type="text" name="download_options_rss_limit" value="<?php echo intval($download_options['rss_limit']); ?>" size="5" /></td>
			</tr>
		</table>
		<p class="submit">
			<input type="submit" name="Submit" class="button" value="<?php _e('Save Changes', 'wp-downloadmanager'); ?>" />
		</p>
	</div>
</form>
