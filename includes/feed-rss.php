<?php
/**
 * The downloads RSS feed.
 *
 * Loaded by DownloadManager_File::serve() for /download/rss/ and ?dl_name=rss.
 *
 * @package WP-DownloadManager
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;

// phpcs:ignore WordPress.DB
$file_last_download  = $wpdb->get_var( "SELECT file_updated_date FROM {$wpdb->downloads} WHERE file_permission != -2 ORDER BY file_updated_date DESC LIMIT 1" );
$download_categories = (array) DownloadManager_Options::get( 'categories' );
$files               = downloadmanager_feed_files();
$feed_title          = get_bloginfo_rss( 'name' ) . __( ' Downloads RSS Feed', 'wp-downloadmanager' );

// Guarded: if anything has already sent output - an early echo from another
// plugin, or a caller that is buffering - header() would emit a warning
// straight into the feed body rather than setting the type.
if ( ! headers_sent() ) {
	header( 'Content-Type: ' . feed_content_type( 'rss2' ) . '; charset=' . get_option( 'blog_charset' ), true );
}

echo '<?xml version="1.0" encoding="' . esc_attr( get_option( 'blog_charset' ) ) . '"?>';
?>

<rss version="2.0"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wfw="http://wellformedweb.org/CommentAPI/"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	xmlns:atom="http://www.w3.org/2005/Atom"
	xmlns:sy="http://purl.org/rss/1.0/modules/syndication/"
>
<channel>
	<title><?php echo esc_html( $feed_title ); ?></title>
	<atom:link href="<?php self_link(); ?>" rel="self" type="application/rss+xml" />
	<link><?php echo esc_url( DownloadManager_Options::get( 'page_url' ) ); ?></link>
	<description><?php echo esc_html( $feed_title ); ?></description>
	<pubDate><?php echo esc_html( mysql2date( 'D, d M Y H:i:s +0000', gmdate( 'Y-m-d H:i:s', (int) $file_last_download ) ) ); ?></pubDate>
	<?php the_generator( 'rss2' ); ?>
	<language><?php echo esc_html( get_option( 'rss_language' ) ); ?></language>
	<sy:updatePeriod><?php echo esc_html( apply_filters( 'rss_update_period', 'hourly' ) ); ?></sy:updatePeriod>
	<sy:updateFrequency><?php echo esc_html( apply_filters( 'rss_update_frequency', '1' ) ); ?></sy:updateFrequency>
	<?php do_action( 'rss2_head' ); ?>
	<?php foreach ( $files as $file ) : ?>
		<item>
			<title><?php echo esc_html( stripslashes( $file->file_name ) ); ?></title>
			<link><?php echo esc_url( download_file_url( $file->file_id, $file->file ) ); ?></link>
			<pubDate><?php echo esc_html( mysql2date( 'D, d M Y H:i:s +0000', gmdate( 'Y-m-d H:i:s', (int) $file->file_date ), false ) ); ?></pubDate>
			<category><![CDATA[<?php echo esc_html( download_category_name( $download_categories, $file->file_category ) ); ?>]]></category>
			<guid isPermaLink="false"><?php echo esc_url( get_option( 'home' ) . '/?dl_id=' . (int) $file->file_id ); ?></guid>
			<description><![CDATA[
				<?php echo wp_kses_post( stripslashes( $file->file_des ) ); ?><br /><br />
				<?php
				printf(
					/* translators: %s: file size. */
					esc_html__( 'File Size: %s', 'wp-downloadmanager' ),
					esc_html( format_filesize( $file->file_size ) )
				);
				?>
				<br />
				<?php
				printf(
					/* translators: %s: number of hits. */
					esc_html__( 'File Hits: %s', 'wp-downloadmanager' ),
					esc_html( number_format_i18n( $file->file_hits ) )
				);
				?>
				<br />
				<?php
				printf(
					/* translators: %s: date the file was last updated. */
					esc_html__( 'File Last Updated: %s', 'wp-downloadmanager' ),
					esc_html( mysql2date( get_option( 'time_format' ) . ' ' . get_option( 'date_format' ), gmdate( 'Y-m-d H:i:s', (int) $file->file_updated_date ) ) )
				);
				?>
			]]></description>
		</item>
	<?php endforeach; ?>
</channel>
</rss>
