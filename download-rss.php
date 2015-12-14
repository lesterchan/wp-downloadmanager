<?php
### Get Download Information
$file_last_download = $wpdb->get_var("SELECT file_updated_date FROM $wpdb->downloads WHERE file_permission != -2 ORDER BY file_updated_date DESC LIMIT 1");
$download_categories = get_option('download_categories');
$download_options = get_option('download_options');


### Get Latest Downloads
$files = $wpdb->get_results("SELECT * FROM $wpdb->downloads WHERE file_permission != -2 ORDER BY {$download_options['rss_sortby']} DESC LIMIT {$download_options['rss_limit']}");


### Set Header
header('Content-Type: '.feed_content_type('rss2').'; charset='.get_option('blog_charset'), true);
?>
<?php echo '<?xml version="1.0" encoding="'.get_option('blog_charset').'"?'.'>'; ?>

<rss version="2.0"
    xmlns:content="http://purl.org/rss/1.0/modules/content/"
    xmlns:wfw="http://wellformedweb.org/CommentAPI/"
    xmlns:dc="http://purl.org/dc/elements/1.1/"
    xmlns:atom="http://www.w3.org/2005/Atom"
    xmlns:sy="http://purl.org/rss/1.0/modules/syndication/"
>
<channel>
    <title><?php bloginfo_rss('name'); _e(' Downloads RSS Feed', 'wp-downloadmanager'); ?></title>
    <atom:link href="<?php self_link(); ?>" rel="self" type="application/rss+xml" />
    <link><?php echo get_option('download_page_url'); ?></link>
    <description><?php bloginfo_rss('name'); _e(' Downloads RSS Feed', 'wp-downloadmanager'); ?></description>
    <pubDate><?php echo mysql2date('D, d M Y H:i:s +0000', gmdate('Y-m-d H:i:s', $file_last_download)); ?></pubDate>
    <?php the_generator('rss2'); ?>
    <language><?php echo get_option('rss_language'); ?></language>
    <sy:updatePeriod><?php echo apply_filters( 'rss_update_period', 'hourly' ); ?></sy:updatePeriod>
    <sy:updateFrequency><?php echo apply_filters( 'rss_update_frequency', '1' ); ?></sy:updateFrequency>
    <?php do_action('rss2_head'); ?>
    <?php if($files): ?>
        <?php foreach($files as $file): ?>
            <item>
                <title><?php echo stripslashes($file->file_name); ?></title>
                <link><?php echo download_file_url($file->file_id, $file->file) ?></link>
                <pubDate><?php echo mysql2date('D, d M Y H:i:s +0000', gmdate('Y-m-d H:i:s', $file->file_date), false); ?></pubDate>
                <category><![CDATA[<?php echo stripslashes($download_categories[intval($file->file_category)]); ?>]]></category>
                <guid isPermaLink="false"><?php echo get_option('home').'/?dl_id='.$file->file_id; ?></guid>
                <description><![CDATA[<?php echo stripslashes($file->file_des); ?><br /><br /><?php printf(__('File Size: %s', 'wp-downloadmanager'), format_filesize($file->file_size)); ?><br /><?php printf(__('File Hits: %s', 'wp-downloadmanager'), number_format_i18n($file->file_hits)); ?><br /><?php printf(__('File Last Updated: %s', 'wp-downloadmanager'), mysql2date(get_option('time_format').' '.get_option('date_format'), gmdate('Y-m-d H:i:s', $file->file_updated_date))); ?>]]></description>
                </item>
        <?php endforeach; ?>
    <?php endif; ?>
</channel>
</rss>