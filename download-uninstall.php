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
|	- Uninstall WP-DownloadManager									|
|	- wp-content/plugins/wp-downloadmanager/download-uninstall.php	|
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
$downloads_tables = array($wpdb->downloads);
$downloads_settings = array('download_path', 'download_path_url', 'download_page_url', 'download_method', 'download_categories', 'download_sort', 'download_template_header', 'download_template_footer', 'download_template_category_header', 'download_template_category_footer', 'download_template_listing', 'download_template_embedded', 'download_template_most', 'download_template_pagingheader', 'download_template_pagingfooter', 'download_nice_permalink', 'download_template_download_page_link', 'download_template_none', 'widget_download_most_downloaded', 'widget_download_recent_downloads', 'download_options', 'widget_downloads');
$download_path = get_option('download_path');


### Form Processing
if(!empty($_POST['do'])) {
	// Decide What To Do
	switch($_POST['do']) {
		//  Uninstall WP-DownloadManager
		case __('UNINSTALL WP-DownloadManager', 'wp-downloadmanager') :
			if(trim($_POST['uninstall_download_yes']) == 'yes') {
				echo '<div id="message" class="updated fade">';
				echo '<p>';
				foreach($downloads_tables as $table) {
					$wpdb->query("DROP TABLE {$table}");
					echo '<font style="color: green;">';
					printf(__('Table \'%s\' has been deleted.', 'wp-downloadmanager'), "<strong><em>{$table}</em></strong>");
					echo '</font><br />';
				}
				echo '</p>';
				echo '<p>';
				foreach($downloads_settings as $setting) {
					$delete_setting = delete_option($setting);
					if($delete_setting) {
						echo '<font color="green">';
						printf(__('Setting Key \'%s\' has been deleted.', 'wp-downloadmanager'), "<strong><em>{$setting}</em></strong>");
						echo '</font><br />';
					} else {
						echo '<font color="red">';
						printf(__('Error deleting Setting Key \'%s\'.', 'wp-downloadmanager'), "<strong><em>{$setting}</em></strong>");
						echo '</font><br />';
					}
				}
				echo '</p>';
				echo '<p style="color: blue;">';
				_e('The download files uploaded by WP-DownloadManager <strong>WILL NOT</strong> be deleted. You will have to delete it manually.', 'wp-downloadmanager');
				echo '<br />';
				printf(__('The path to the downloads folder is <strong>\'%s\'</strong>.', 'wp-downloadmanager'), $download_path);
				echo '</p>';
				echo '</div>';
				$mode = 'end-UNINSTALL';
			}
			break;
	}
}


### Determines Which Mode It Is
switch($mode) {
		//  Deactivating WP-DownloadManager
		case 'end-UNINSTALL':
			$deactivate_url = 'plugins.php?action=deactivate&amp;plugin=wp-downloadmanager/wp-downloadmanager.php';
			if(function_exists('wp_nonce_url')) {
				$deactivate_url = wp_nonce_url($deactivate_url, 'deactivate-plugin_wp-downloadmanager/wp-downloadmanager.php');
			}
			echo '<div class="wrap">';
			echo '<div id="icon-wp-downloadmanager" class="icon32"><br /></div>';
			echo '<h2>'.__('Uninstall WP-DownloadManager', 'wp-downloadmanager').'</h2>';
			echo '<p><strong>'.sprintf(__('<a href="%s">Click Here</a> To Finish The Uninstallation And WP-DownloadManager Will Be Deactivated Automatically.', 'wp-downloadmanager'), $deactivate_url).'</strong></p>';
			echo '</div>';
			break;
	// Main Page
	default:
?>
<!-- Uninstall WP-DownloadManager -->
<form method="post" action="<?php echo admin_url('admin.php?page='.plugin_basename(__FILE__)); ?>">
<div class="wrap">
	<div id="icon-wp-downloadmanager" class="icon32"><br /></div>
	<h2><?php _e('Uninstall WP-DownloadManager', 'wp-downloadmanager'); ?></h2>
	<p>
		<?php _e('Deactivating WP-DownloadManager plugin does not remove any data that may have been created, such as the download options and the download data. To completely remove this plugin, you can uninstall it here.', 'wp-downloadmanager'); ?>
	</p>
	<p style="color: red">
		<strong><?php _e('NOTE:', 'wp-downloadmanager'); ?></strong><br />
		<?php _e('The download files uploaded by WP-DownloadManager <strong>WILL NOT</strong> be deleted. You will have to delete it manually.', 'wp-downloadmanager'); ?><br />
		<?php printf(__('The path to the downloads folder is <strong>\'%s\'</strong>.', 'wp-downloadmanager'), $download_path); ?>
	</p>
	<p style="color: red">
		<strong><?php _e('WARNING:', 'wp-downloadmanager'); ?></strong><br />
		<?php _e('Once uninstalled, this cannot be undone. You should use a Database Backup plugin of WordPress to back up all the data first.', 'wp-downloadmanager'); ?>
	</p>
	<p style="color: red">
		<strong><?php _e('The following WordPress Options/Tables will be DELETED:', 'wp-downloadmanager'); ?></strong><br />
	</p>
	<table class="widefat">
		<thead>
			<tr>
				<th><?php _e('WordPress Options', 'wp-downloadmanager'); ?></th>
				<th><strong><?php _e('WordPress Tables', 'wp-downloadmanager'); ?></th>
			</tr>
		</thead>
		<tr>
			<td valign="top">
				<ol>
				<?php
					foreach($downloads_settings as $settings) {
						echo '<li>'.$settings.'</li>'."\n";
					}
				?>
				</ol>
			</td>
			<td valign="top" class="alternate">
				<ol>
				<?php
					foreach($downloads_tables as $tables) {
						echo '<li>'.$tables.'</li>'."\n";
					}
				?>
				</ol>
			</td>
		</tr>
	</table>
	<p>&nbsp;</p>
	<p style="text-align: center;">
		<input type="checkbox" name="uninstall_download_yes" value="yes" />&nbsp;<?php _e('Yes', 'wp-downloadmanager'); ?><br /><br />
		<input type="submit" name="do" value="<?php _e('UNINSTALL WP-DownloadManager', 'wp-downloadmanager'); ?>" class="button" onclick="return confirm('<?php _e('You Are About To Uninstall WP-DownloadManager From WordPress.\nThis Action Is Not Reversible.\n\n Choose [Cancel] To Stop, [OK] To Uninstall.', 'wp-downloadmanager'); ?>')" />
	</p>
</div>
</form>
<?php
} // End switch($mode)
?>
