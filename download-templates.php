<?php
### Check Whether User Can Manage Downloads
if(!current_user_can('manage_downloads')) {
    die('Access Denied');
}


### Variables Variables Variables
$base_name = plugin_basename('wp-downloadmanager/download-manager.php');
$base_page = 'admin.php?page='.$base_name;


### If Form Is Submitted
if( ! empty( $_POST['Submit'] ) ) {
    check_admin_referer('wp-downloadmanager_templates');
    $download_template_listing = array();
    $download_template_embedded = array();
    $download_template_most = array();
    $download_template_header = ! empty ( $_POST['download_template_header'] ) ? trim( $_POST['download_template_header'] ) : '';
    $download_template_footer = ! empty ( $_POST['download_template_footer'] ) ? trim( $_POST['download_template_footer'] ) : '';
    $download_template_pagingheader = ! empty ( $_POST['download_template_pagingheader'] ) ? trim( $_POST['download_template_pagingheader'] ) : '';
    $download_template_pagingfooter = ! empty ( $_POST['download_template_pagingfooter'] ) ? trim( $_POST['download_template_pagingfooter'] ) : '';
    $download_template_none = ! empty ( $_POST['download_template_none'] ) ? trim( $_POST['download_template_none'] ) : '';
    $download_template_category_header = ! empty ( $_POST['download_template_category_header'] ) ? trim( $_POST['download_template_category_header'] ) : '';
    $download_template_category_footer = ! empty ( $_POST['download_template_category_footer'] ) ? trim( $_POST['download_template_category_footer'] ) : '';
    $download_template_listing[] = ! empty ( $_POST['download_template_listing'] ) ? trim( $_POST['download_template_listing'] ) : '';
    $download_template_listing[] = ! empty ( $_POST['download_template_listing_2'] ) ? trim( $_POST['download_template_listing_2'] ) : '';
    $download_template_embedded[] = ! empty ( $_POST['download_template_embedded'] ) ? trim( $_POST['download_template_embedded'] ) : '';
    $download_template_embedded[] = ! empty ( $_POST['download_template_embedded_2'] ) ? trim( $_POST['download_template_embedded_2'] ) : '';
    $download_template_download_page_link = ! empty ( $_POST['download_template_download_page_link'] ) ? trim( $_POST['download_template_download_page_link'] ) : '';
    $download_template_most[] = ! empty ( $_POST['download_template_most'] ) ? trim( $_POST['download_template_most'] ) : '';
    $download_template_most[] = ! empty ( $_POST['download_template_most_2'] ) ? trim( $_POST['download_template_most_2'] ) : '';
    $update_download_queries = array();
    $update_download_text = array();
    $update_download_queries[] = update_option('download_template_header', $download_template_header);
    $update_download_queries[] = update_option('download_template_footer', $download_template_footer);
    $update_download_queries[] = update_option('download_template_pagingheader', $download_template_pagingheader);
    $update_download_queries[] = update_option('download_template_pagingfooter', $download_template_pagingfooter);
    $update_download_queries[] = update_option('download_template_none', $download_template_none);
    $update_download_queries[] = update_option('download_template_category_header', $download_template_category_header);
    $update_download_queries[] = update_option('download_template_category_footer', $download_template_category_footer);
    $update_download_queries[] = update_option('download_template_listing', $download_template_listing);
    $update_download_queries[] = update_option('download_template_embedded', $download_template_embedded);
    $update_download_queries[] = update_option('download_template_download_page_link', $download_template_download_page_link);
    $update_download_queries[] = update_option('download_template_most', $download_template_most);
    $update_download_text[] = __('Download Page Header Template', 'wp-downloadmanager');
    $update_download_text[] = __('Download Page Footer Template', 'wp-downloadmanager');
    $update_download_text[] = __('Download Page Paging Header Template', 'wp-downloadmanager');
    $update_download_text[] = __('Download Page Paging Footer Template', 'wp-downloadmanager');
    $update_download_text[] = __('No Files Found Template', 'wp-downloadmanager');
    $update_download_text[] = __('Download Category Header Template', 'wp-downloadmanager');
    $update_download_text[] = __('Download Category Footer Template', 'wp-downloadmanager');
    $update_download_text[] = __('Download Listing Template', 'wp-downloadmanager');
    $update_download_text[] = __('Download Embedded Template', 'wp-downloadmanager');
    $update_download_text[] = __('Download Page Link Template', 'wp-downloadmanager');
    $update_download_text[] = __('Most Downloaded Template', 'wp-downloadmanager');
    $i=0;
    $text = '';
    foreach($update_download_queries as $update_download_query) {
        if($update_download_query) {
            $text .= '<p style="color: green;">'.$update_download_text[$i].' '.__('Updated', 'wp-downloadmanager').'</p>';
        }
        $i++;
    }
    if(empty($text)) {
        $text = '<p style="color: red;">'.__('No Download Option Updated', 'wp-downloadmanager').'</p>';
    }
}


### Get Arrayed Templates
$download_template_embedded = get_option('download_template_embedded');
$download_template_listing = get_option('download_template_listing');
$download_template_most = get_option('download_template_most');
?>
<?php if(!empty($text)) { echo '<!-- Last Action --><div id="message" class="updated fade"><p>'.$text.'</p></div>'; } ?>
<script type="text/javascript">
/* <![CDATA[*/
    function download_default_templates(template) {
        var default_template;
        switch(template) {
            case "header":
                default_template = "<p><?php _e('There are <strong>%TOTAL_FILES_COUNT% files</strong>, weighing <strong>%TOTAL_SIZE%</strong> with <strong>%TOTAL_HITS% hits</strong> in <strong>%FILE_CATEGORY_NAME%</strong>.</p><p>Displaying <strong>%RECORD_START%</strong> to <strong>%RECORD_END%</strong> of <strong>%TOTAL_FILES_COUNT%</strong> files.', 'wp-downloadmanager'); ?></p>";
                break;
            case "footer":
                default_template = "<form action=\"%DOWNLOAD_PAGE_URL%\" method=\"get\"><p><input type=\"hidden\" name=\"dl_cat\" value=\"%CATEGORY_ID%\" /><input type=\"text\" name=\"dl_search\" value=\"%FILE_SEARCH_WORD%\" />&nbsp;&nbsp;&nbsp;<input type=\"submit\" value=\"<?php _e('Search', 'wp-downloadmanager'); ?>\" /></p></form>";
                break;
            case "pagingheader":
                default_template = "";
                break;
            case "pagingfooter":
                default_template = "";
                break;
            case "none":
                default_template = "<p style=\"text-align: center;\"><?php _e('No Files Found.', 'wp-downloadmanager'); ?></p>";
                break;
            case "category_header":
                default_template = "<h2 id=\"downloadcat-%CATEGORY_ID%\"><a href=\"%CATEGORY_URL%\" title=\"<?php _e('View all downloads in %FILE_CATEGORY_NAME%', 'wp-downloadmanager'); ?>\">%FILE_CATEGORY_NAME%</a></h2>";
                break;
            case "category_footer":
                default_template = "";
                break;
            case "listing":
                default_template = "<p><img src=\"<?php echo plugins_url('wp-downloadmanager/images/ext'); ?>/%FILE_ICON%\" alt=\"\" title=\"\" style=\"vertical-align: middle;\" />&nbsp;&nbsp;<strong><a href=\"%FILE_DOWNLOAD_URL%\">%FILE_NAME%</a></strong><br /><strong>&raquo; %FILE_SIZE% - %FILE_HITS% <?php _e('hits', 'wp-downloadmanager'); ?> - %FILE_DATE%</strong><br />%FILE_DESCRIPTION%</p>";
                break;
            case "embedded":
                default_template = "<p><img src=\"<?php echo plugins_url('wp-downloadmanager/images/ext'); ?>/%FILE_ICON%\" alt=\"\" title=\"\" style=\"vertical-align: middle;\" />&nbsp;&nbsp;<strong><a href=\"%FILE_DOWNLOAD_URL%\">%FILE_NAME%</a></strong> (%FILE_SIZE%<?php _e(',', 'wp-downloadmanager'); ?> %FILE_HITS% <?php _e('hits', 'wp-downloadmanager'); ?>)</p>";
                break;
            case "listing_2":
                default_template = "<p><img src=\"<?php echo plugins_url('wp-downloadmanager/images/ext'); ?>/%FILE_ICON%\" alt=\"\" title=\"\" style=\"vertical-align: middle;\" />&nbsp;&nbsp;<strong>%FILE_NAME%</strong><br /><i><?php _e('You do not have permission to download this file.', 'wp-downloadmanager'); ?></i><br /><strong>&raquo; %FILE_SIZE% - %FILE_HITS% <?php _e('hits', 'wp-downloadmanager'); ?> - %FILE_DATE%</strong><br />%FILE_DESCRIPTION%</p>";
                break;
            case "embedded_2":
                default_template = "<p><img src=\"<?php echo plugins_url('wp-downloadmanager/images/ext'); ?>/%FILE_ICON%\" alt=\"\" title=\"\" style=\"vertical-align: middle;\" />&nbsp;&nbsp;<strong>%FILE_NAME%</strong> (%FILE_SIZE%<?php _e(',', 'wp-downloadmanager'); ?> %FILE_HITS% <?php _e('hits', 'wp-downloadmanager'); ?>)<br /><i><?php _e('You do not have permission to download this file.', 'wp-downloadmanager'); ?></i></p>";
                break;
            case 'download_page_link':
                default_template = "<p><a href=\"%DOWNLOAD_PAGE_URL%\" title=\"<?php _e('Downloads Page', 'wp-downloadmanager'); ?>\"><?php _e('Downloads Page', 'wp-downloadmanager'); ?></a></p>";
                break;
            case 'most':
                default_template = "<li><a href=\"%FILE_DOWNLOAD_URL%\">%FILE_NAME%</a> (%FILE_SIZE%<?php _e(',', 'wp-downloadmanager'); ?> %FILE_HITS% <?php _e('hits', 'wp-downloadmanager'); ?>)</li>";
                break;
            case 'most_2':
                default_template = "<li>%FILE_NAME% (%FILE_SIZE%<?php _e(',', 'wp-downloadmanager'); ?> %FILE_HITS% <?php _e('hits', 'wp-downloadmanager'); ?>)<br /><i><?php _e('You do not have permission to download this file.', 'wp-downloadmanager'); ?></i></li>";
                break;
        }
        jQuery("#download_template_" + template).val(default_template);
    }
/* ]]> */
</script>
<div class="wrap">
    <h2><?php _e('Download Templates', 'wp-downloadmanager'); ?></h2>
    <form method="post" action="<?php echo admin_url('admin.php?page='.plugin_basename(__FILE__)); ?>">
    <?php wp_nonce_field('wp-downloadmanager_templates'); ?>
    <h3><?php _e('Template Variables', 'wp-downloadmanager'); ?></h3>
    <table class="widefat">
        <tr>
            <td>
                <strong>%FILE_ID%</strong><br />
                <?php _e('Display the file\'s ID.', 'wp-downloadmanager'); ?>
            </td>
            <td>
                <strong>%FILE%</strong><br />
                <?php _e('Display the file\'s filename.', 'wp-downloadmanager'); ?>
            </td>
            <td>
                <strong>%CATEGORY_FILES_COUNT%</strong><br />
                <?php _e('Display the total number of files in the category.', 'wp-downloadmanager'); ?>
            </td>
            <td>
                <strong>%TOTAL_FILES_COUNT%</strong><br />
                <?php _e('Display the total number of files.', 'wp-downloadmanager'); ?>
            </td>
            <td>
                <strong>%RECORD_START%</strong><br />
                <?php _e('Display the start number of the record.', 'wp-downloadmanager'); ?>
            </td>
        </tr>
        <tr class="alternate">
            <td>
                <strong>%FILE_NAME%</strong><br />
                <?php _e('Display the file\'s name.', 'wp-downloadmanager'); ?>
            </td>
            <td>
                <strong>%FILE_EXT%</strong><br />
                <?php _e('Display the file\'s extension.', 'wp-downloadmanager'); ?>
            </td>
            <td>
                <strong>%FILE_DESCRIPTION%</strong><br />
                <?php _e('Display the file\'s description.', 'wp-downloadmanager'); ?>
            </td>
            <td>
                <strong>%CATEGORY_HITS%</strong><br />
                <?php _e('Display the total number of file hits in the category.', 'wp-downloadmanager'); ?>
            </td>
            <td>
                <strong>%TOTAL_HITS%</strong><br />
                <?php _e('Displays the total file hits.', 'wp-downloadmanager'); ?>
            </td>
            <td>
                <strong>%RECORD_END%</strong><br />
                <?php _e('Display the end number of the record.', 'wp-downloadmanager'); ?>
            </td>
        </tr>
        <tr>
            <td>
                <strong>%FILE_SIZE%</strong> (or <strong>%FILE_SIZE_DEC%</strong>)<br />
                <?php _e('Display the file\'s size in bytes/KiB/MiB/GiB/TiB (or bytes/KB/MB/GB/TB).', 'wp-downloadmanager'); ?>
            </td>
            <td>
                <strong>%FILE_CATEGORY_NAME%</strong><br />
                <?php _e('Display the files\'s category name.', 'wp-downloadmanager'); ?>
            </td>
            <td>
                <strong>%CATEGORY_SIZE%</strong> (or <strong>%CATEGORY_SIZE_DEC%</strong>)<br />
                <?php _e('Display the total size of all the files in the category.', 'wp-downloadmanager'); ?>
            </td>
            <td>
                <strong>%TOTAL_SIZE%</strong> (or <strong>%TOTAL_SIZE_DEC%</strong>)<br />
                <?php _e('Display the total file size.', 'wp-downloadmanager'); ?>
            </td>
            <td>
                <strong>%FILE_HITS%</strong><br />
                <?php _e('Display the number of times the file has been downloaded.', 'wp-downloadmanager'); ?>
            </td>
        </tr>
        <tr class="alternate">
            <td>
                <strong>%FILE_DATE%</strong><br />
                <?php _e('Displays the file\'s date.', 'wp-downloadmanager'); ?>
            </td>
            <td>
                <strong>%FILE_TIME%</strong><br />
                <?php _e('Displays the file\'s time.', 'wp-downloadmanager'); ?>
            </td>
            <td>
                <strong>%CATEGORY_URL%</strong><br />
                <?php _e('Displays the url to the category.', 'wp-downloadmanager'); ?>
            </td>
            <td>
                <strong>%FILE_DOWNLOAD_URL%</strong><br />
                <?php _e('Displays the file\'s download url.', 'wp-downloadmanager'); ?>
            </td>
            <td>
                <strong>%CATEGORY_ID%</strong><br />
                <?php _e('Display the category ID.', 'wp-downloadmanager'); ?>
            </td>
        </tr>
        <tr>
            <td>
                <strong>%FILE_ICON%</strong><br />
                <?php _e('Displays the file\'s extension icon.', 'wp-downloadmanager'); ?>
            </td>
            <td>
                <strong>%DOWNLOAD_PAGE_URL%</strong><br />
                <?php _e('Displays the URL to the download page.', 'wp-downloadmanager'); ?>
            </td>
            <td>
                <strong>%FILE_UPDATED_DATE%</strong><br />
                <?php _e('Displays the file\'s last updated date.', 'wp-downloadmanager'); ?>
            </td>
            <td>
                <strong>%FILE_UPDATED_TIME%</strong><br />
                <?php _e('Displays the file\'s last updated time.', 'wp-downloadmanager'); ?>
            </td>
            <td>
                <strong>%FILE_SEARCH_WORD%</strong><br />
                <?php _e('Displays the file search key word.', 'wp-downloadmanager'); ?>
            </td>
        </tr>
    </table>
    <h3><?php _e('Download Page Templates', 'wp-downloadmanager'); ?></h3>
    <table class="form-table">
        <tr valign="top">
            <td width="30%">
                <strong><?php _e('Download Page Header:', 'wp-downloadmanager'); ?></strong><br /><br /><br />
                <?php _e('Allowed Variables:', 'wp-downloadmanager'); ?><br />
                - %TOTAL_FILES_COUNT%<br />
                - %TOTAL_HITS%<br />
                - %TOTAL_SIZE%<br />
                - %TOTAL_SIZE_DEC%<br />
                - %RECORD_START%<br />
                - %RECORD_END%<br />
                - %CATEGORY_ID%<br />
                - %FILE_CATEGORY_NAME%<br />
                - %FILE_SEARCH_WORD%<br />
                - %DOWNLOAD_PAGE_URL%<br /><br />
                <input type="button" name="RestoreDefault" value="<?php _e('Restore Default Template', 'wp-downloadmanager'); ?>" onclick="download_default_templates('header');" class="button" />
            </td>
            <td><textarea cols="80" rows="20" id="download_template_header" name="download_template_header"><?php echo htmlspecialchars(stripslashes(get_option('download_template_header'))); ?></textarea></td>
         </tr>
        <tr valign="top">
            <td width="30%">
                <strong><?php _e('Download Page Footer:', 'wp-downloadmanager'); ?></strong><br /><br /><br />
                <?php _e('Allowed Variables:', 'wp-downloadmanager'); ?><br />
                - %TOTAL_FILES_COUNT%<br />
                - %TOTAL_HITS%<br />
                - %TOTAL_SIZE%<br />
                - %TOTAL_SIZE_DEC%<br />
                - %CATEGORY_ID%<br />
                - %FILE_CATEGORY_NAME%<br />
                - %FILE_SEARCH_WORD%<br />
                - %DOWNLOAD_PAGE_URL%<br /><br />
                <input type="button" name="RestoreDefault" value="<?php _e('Restore Default Template', 'wp-downloadmanager'); ?>" onclick="download_default_templates('footer');" class="button" />
            </td>
            <td><textarea cols="80" rows="20" id="download_template_footer" name="download_template_footer"><?php echo htmlspecialchars(stripslashes(get_option('download_template_footer'))); ?></textarea></td>
         </tr>
         <tr valign="top">
            <td width="30%">
                <strong><?php _e('Download Page Paging Header:', 'wp-downloadmanager'); ?></strong><br /><br /><br />
                <?php _e('Allowed Variables:', 'wp-downloadmanager'); ?><br />
                - N/A<br /><br />
                <input type="button" name="RestoreDefault" value="<?php _e('Restore Default Template', 'wp-downloadmanager'); ?>" onclick="download_default_templates('pagingheader');" class="button" />
            </td>
            <td><textarea cols="80" rows="20" id="download_template_pagingheader" name="download_template_pagingheader"><?php echo htmlspecialchars(stripslashes(get_option('download_template_pagingheader'))); ?></textarea></td>
         </tr>
         <tr valign="top">
            <td width="30%">
                <strong><?php _e('Download Page Paging Footer:', 'wp-downloadmanager'); ?></strong><br /><br /><br />
                <?php _e('Allowed Variables:', 'wp-downloadmanager'); ?><br />
                - N/A<br /><br />
                <input type="button" name="RestoreDefault" value="<?php _e('Restore Default Template', 'wp-downloadmanager'); ?>" onclick="download_default_templates('pagingfooter');" class="button" />
            </td>
            <td><textarea cols="80" rows="20" id="download_template_pagingfooter" name="download_template_pagingfooter"><?php echo htmlspecialchars(stripslashes(get_option('download_template_pagingfooter'))); ?></textarea></td>
         </tr>
    </table>
    <h3><?php _e('No Files Found Templates', 'wp-downloadmanager'); ?></h3>
    <table class="form-table">
         <tr valign="top">
            <td width="30%">
                <strong><?php _e('No Files Found:', 'wp-downloadmanager'); ?></strong><br /><br /><br />
                <?php _e('Allowed Variables:', 'wp-downloadmanager'); ?><br />
                - N/A<br /><br />
                <input type="button" name="RestoreDefault" value="<?php _e('Restore Default Template', 'wp-downloadmanager'); ?>" onclick="download_default_templates('none');" class="button" />
            </td>
            <td><textarea cols="80" rows="20" id="download_template_none" name="download_template_none"><?php echo htmlspecialchars(stripslashes(get_option('download_template_none'))); ?></textarea></td>
        </tr>
    </table>
    <h3><?php _e('Download Category Templates', 'wp-downloadmanager'); ?></h3>
    <table class="form-table">
         <tr valign="top">
            <td width="30%">
                <strong><?php _e('Download Category Header:', 'wp-downloadmanager'); ?></strong><br /><br /><br />
                <?php _e('Allowed Variables:', 'wp-downloadmanager'); ?><br />
                - %FILE_CATEGORY_NAME%<br />
                - %CATEGORY_ID%<br />
                - %CATEGORY_URL%<br />
                - %CATEGORY_FILES_COUNT%<br />
                - %CATEGORY_HITS%<br />
                - %CATEGORY_SIZE%<br />
                - %CATEGORY_SIZE_DEC%<br /><br />
                <input type="button" name="RestoreDefault" value="<?php _e('Restore Default Template', 'wp-downloadmanager'); ?>" onclick="download_default_templates('category_header');" class="button" />
            </td>
            <td><textarea cols="80" rows="20" id="download_template_category_header" name="download_template_category_header"><?php echo htmlspecialchars(stripslashes(get_option('download_template_category_header'))); ?></textarea></td>
        </tr>
        <tr valign="top">
            <td width="30%">
                <strong><?php _e('Download Category Footer:', 'wp-downloadmanager'); ?></strong><br /><br /><br />
                <?php _e('Allowed Variables:', 'wp-downloadmanager'); ?><br />
                - %FILE_CATEGORY_NAME%<br />
                - %CATEGORY_ID%<br />
                - %CATEGORY_URL%<br />
                - %CATEGORY_FILES_COUNT%<br />
                - %CATEGORY_HITS%<br />
                - %CATEGORY_SIZE%<br />
                - %CATEGORY_SIZE_DEC%<br /><br />
                <input type="button" name="RestoreDefault" value="<?php _e('Restore Default Template', 'wp-downloadmanager'); ?>" onclick="download_default_templates('category_footer');" class="button" />
            </td>
            <td><textarea cols="80" rows="20" id="download_template_category_footer" name="download_template_category_footer"><?php echo htmlspecialchars(stripslashes(get_option('download_template_category_footer'))); ?></textarea></td>
        </tr>
    </table>
    <h3><?php _e('Download Templates (With Permission)', 'wp-downloadmanager'); ?></h3>
    <table class="form-table">
        <tr valign="top">
            <td width="30%">
                <strong><?php _e('Download Listing:', 'wp-downloadmanager'); ?></strong><br />
                <?php _e('Displayed when listing files in the downloads page and users have permission to download the file.', 'wp-downloadmanager'); ?><br /><br />
                <?php _e('Allowed Variables:', 'wp-downloadmanager'); ?><br />
                - %FILE_ID%<br />
                - %FILE%<br />
                - %FILE_ICON%<br />
                - %FILE_NAME%<br />
                - %FILE_DESCRIPTION%<br />
                - %FILE_SIZE%<br />
                - %FILE_SIZE_DEC%<br />
                - %FILE_CATEGORY_ID%<br />
                - %FILE_CATEGORY_NAME%<br />
                - %FILE_DATE%<br />
                - %FILE_TIME%<br />
                - %FILE_UPDATED_DATE%<br />
                - %FILE_UPDATED_TIME%<br />
                - %FILE_HITS%<br />
                - %FILE_DOWNLOAD_URL%<br /><br />
                <input type="button" name="RestoreDefault" value="<?php _e('Restore Default Template', 'wp-downloadmanager'); ?>" onclick="download_default_templates('listing');" class="button" />
            </td>
            <td><textarea cols="80" rows="20" id="download_template_listing" name="download_template_listing"><?php echo htmlspecialchars(stripslashes($download_template_listing[0])); ?></textarea></td>
        </tr>
        <tr valign="top">
            <td width="30%">
                <strong><?php _e('Download Embedded File', 'wp-downloadmanager'); ?></strong><br />
                <?php _e('Displayed when you embedded a file within a post or a page and users have permission to download the file.', 'wp-downloadmanager'); ?><br /><br />
                <?php _e('Allowed Variables:', 'wp-downloadmanager'); ?><br />
                - %FILE_ID%<br />
                - %FILE%<br />
                - %FILE_ICON%<br />
                - %FILE_NAME%<br />
                - %FILE_DESCRIPTION%<br />
                - %FILE_SIZE%<br />
                - %FILE_SIZE_DEC%<br />
                - %FILE_CATEGORY_ID%<br />
                - %FILE_CATEGORY_NAME%<br />
                - %FILE_DATE%<br />
                - %FILE_TIME%<br />
                - %FILE_UPDATED_DATE%<br />
                - %FILE_UPDATED_TIME%<br />
                - %FILE_HITS%<br />
                - %FILE_DOWNLOAD_URL%<br /><br />
                <input type="button" name="RestoreDefault" value="<?php _e('Restore Default Template', 'wp-downloadmanager'); ?>" onclick="download_default_templates('embedded');" class="button" />
            </td>
            <td><textarea cols="80" rows="20" id="download_template_embedded" name="download_template_embedded"><?php echo htmlspecialchars(stripslashes($download_template_embedded[0])); ?></textarea></td>
        </tr>
    </table>
    <h3><?php _e('Download Templates (Without Permission)', 'wp-downloadmanager'); ?></h3>
    <table class="form-table">
        <tr valign="top">
            <td width="30%">
                <strong><?php _e('Download Listing:', 'wp-downloadmanager'); ?></strong><br />
                <?php _e('Displayed when listing files in the downloads page and users <strong>DO NOT</strong> have permission to download the file.', 'wp-downloadmanager'); ?><br /><br />
                <?php _e('Allowed Variables:', 'wp-downloadmanager'); ?><br />
                - %FILE_ID%<br />
                - %FILE%<br />
                - %FILE_ICON%<br />
                - %FILE_NAME%<br />
                - %FILE_DESCRIPTION%<br />
                - %FILE_SIZE%<br />
                - %FILE_SIZE_DEC%<br />
                - %FILE_CATEGORY_ID%<br />
                - %FILE_CATEGORY_NAME%<br />
                - %FILE_DATE%<br />
                - %FILE_TIME%<br />
                - %FILE_UPDATED_DATE%<br />
                - %FILE_UPDATED_TIME%<br />
                - %FILE_HITS%<br />
                - %FILE_DOWNLOAD_URL%<br /><br />
                <input type="button" name="RestoreDefault" value="<?php _e('Restore Default Template', 'wp-downloadmanager'); ?>" onclick="download_default_templates('listing_2');" class="button" />
            </td>
            <td><textarea cols="80" rows="20" id="download_template_listing_2" name="download_template_listing_2"><?php echo htmlspecialchars(stripslashes($download_template_listing[1])); ?></textarea></td>
        </tr>
        <tr valign="top">
            <td width="30%">
                <strong><?php _e('Download Embedded File', 'wp-downloadmanager'); ?></strong><br />
                <?php _e('Displayed when you embedded a file within a post or a page and users <strong>DO NOT</strong> have permission to download the file.', 'wp-downloadmanager'); ?><br /><br />
                <?php _e('Allowed Variables:', 'wp-downloadmanager'); ?><br />
                - %FILE_ID%<br />
                - %FILE%<br />
                - %FILE_ICON%<br />
                - %FILE_NAME%<br />
                - %FILE_DESCRIPTION%<br />
                - %FILE_SIZE%<br />
                - %FILE_SIZE_DEC%<br />
                - %FILE_CATEGORY_ID%<br />
                - %FILE_CATEGORY_NAME%<br />
                - %FILE_DATE%<br />
                - %FILE_TIME%<br />
                - %FILE_UPDATED_DATE%<br />
                - %FILE_UPDATED_TIME%<br />
                - %FILE_HITS%<br />
                - %FILE_DOWNLOAD_URL%<br /><br />
                <input type="button" name="RestoreDefault" value="<?php _e('Restore Default Template', 'wp-downloadmanager'); ?>" onclick="download_default_templates('embedded_2');" class="button" />
            </td>
            <td><textarea cols="80" rows="20" id="download_template_embedded_2" name="download_template_embedded_2"><?php echo htmlspecialchars(stripslashes($download_template_embedded[1])); ?></textarea></td>
        </tr>
    </table>
    <h3><?php _e('Download Page Link Template', 'wp-downloadmanager'); ?></h3>
    <table class="form-table">
        <tr valign="top">
            <td width="30%">
                <?php _e('This template is used to style the link to the Download Page, if you choose to display the Download Page Link in the Most Downloaded and Recent Downloads widget.', 'wp-downloadmanager'); ?><br />
                <?php _e('Allowed Variables:', 'wp-downloadmanager'); ?><br />
                - %DOWNLOAD_PAGE_URL%<br /><br />
                <input type="button" name="RestoreDefault" value="<?php _e('Restore Default Template', 'wp-downloadmanager'); ?>" onclick="download_default_templates('download_page_link');" class="button" />
            </td>
            <td><textarea cols="80" rows="20" id="download_template_download_page_link" name="download_template_download_page_link"><?php echo htmlspecialchars(stripslashes(get_option('download_template_download_page_link'))); ?></textarea></td>
        </tr>
    </table>
    <h3><?php _e('Download Stats Templates (With Permission)', 'wp-downloadmanager'); ?></h3>
    <table class="form-table">
        <tr valign="top">
            <td width="30%">
                <strong><?php _e('Most Downloaded', 'wp-downloadmanager'); ?></strong><br />
                <?php _e('Displayed when listing most downloaded files.', 'wp-downloadmanager'); ?><br />
                <strong><?php _e('Recent Downloads', 'wp-downloadmanager'); ?></strong><br />
                <?php _e('Displayed when listing recent downloads.', 'wp-downloadmanager'); ?><br />
                <strong><?php _e('Downloads By Category', 'wp-downloadmanager'); ?></strong><br />
                <?php _e('Displayed when listing downloads by category.', 'wp-downloadmanager'); ?><br /><br />
                <?php _e('Displayed when users have permission to download the file.', 'wp-downloadmanager'); ?><br /><br />
                <?php _e('Allowed Variables:', 'wp-downloadmanager'); ?><br />
                - %FILE_ID%<br />
                - %FILE%<br />
                - %FILE_ICON%<br />
                - %FILE_NAME%<br />
                - %FILE_DESCRIPTION%<br />
                - %FILE_SIZE%<br />
                - %FILE_SIZE_DEC%<br />
                - %FILE_CATEGORY_ID%<br />
                - %FILE_CATEGORY_NAME%<br />
                - %FILE_DATE%<br />
                - %FILE_TIME%<br />
                - %FILE_UPDATED_DATE%<br />
                - %FILE_UPDATED_TIME%<br />
                - %FILE_HITS%<br />
                - %FILE_DOWNLOAD_URL%<br /><br />
                <input type="button" name="RestoreDefault" value="<?php _e('Restore Default Template', 'wp-downloadmanager'); ?>" onclick="download_default_templates('most');" class="button" />
            </td>
            <td><textarea cols="80" rows="20" id="download_template_most" name="download_template_most"><?php echo htmlspecialchars(stripslashes($download_template_most[0])); ?></textarea></td>
        </tr>
    </table>
    <h3><?php _e('Download Stats Templates (Without Permission)', 'wp-downloadmanager'); ?></h3>
    <table class="form-table">
        <tr valign="top">
            <td width="30%">
                <strong><?php _e('Most Downloaded', 'wp-downloadmanager'); ?></strong><br />
                <?php _e('Displayed when listing most downloaded files.', 'wp-downloadmanager'); ?><br />
                <strong><?php _e('Recent Downloads', 'wp-downloadmanager'); ?></strong><br />
                <?php _e('Displayed when listing recent downloads.', 'wp-downloadmanager'); ?><br />
                <strong><?php _e('Downloads By Category', 'wp-downloadmanager'); ?></strong><br />
                <?php _e('Displayed when listing downloads by category.', 'wp-downloadmanager'); ?><br /><br />
                <?php _e('Displayed when users <strong>DO NOT</strong> have permission to download the file.', 'wp-downloadmanager'); ?><br /><br />
                <?php _e('Allowed Variables:', 'wp-downloadmanager'); ?><br />
                - %FILE_ID%<br />
                - %FILE%<br />
                - %FILE_ICON%<br />
                - %FILE_NAME%<br />
                - %FILE_DESCRIPTION%<br />
                - %FILE_SIZE%<br />
                - %FILE_SIZE_DEC%<br />
                - %FILE_CATEGORY_ID%<br />
                - %FILE_CATEGORY_NAME%<br />
                - %FILE_DATE%<br />
                - %FILE_TIME%<br />
                - %FILE_UPDATED_DATE%<br />
                - %FILE_UPDATED_TIME%<br />
                - %FILE_HITS%<br />
                - %FILE_DOWNLOAD_URL%<br /><br />
                <input type="button" name="RestoreDefault" value="<?php _e('Restore Default Template', 'wp-downloadmanager'); ?>" onclick="download_default_templates('most_2');" class="button" />
            </td>
            <td><textarea cols="80" rows="20" id="download_template_most_2" name="download_template_most_2"><?php echo htmlspecialchars(stripslashes($download_template_most[1])); ?></textarea></td>
        </tr>
    </table>
    <p class="submit">
        <input type="submit" name="Submit" class="button" value="<?php _e('Save Changes', 'wp-downloadmanager'); ?>" />
    </p>
    </form>
</div>
