<?php
/**
 * Default template markup for WP-DownloadManager.
 *
 * The markup lives here rather than being duplicated between the option
 * defaults and the "Restore Default Template" buttons on the templates screen,
 * which is how the two used to drift apart: the buttons were a switch statement
 * in a jQuery snippet, and it had already fallen out of step with the defaults
 * written on activation.
 *
 * @package WP-DownloadManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Supplies the stock template strings.
 */
class DownloadManager_Templates {

	/**
	 * The template slugs, in the order the settings screen shows them.
	 *
	 * The three paired templates hold two strings each: index 0 renders when the
	 * visitor may download the file, index 1 when they may not.
	 *
	 * @return array
	 */
	public static function keys() {
		return array(
			'header',
			'footer',
			'pagingheader',
			'pagingfooter',
			'none',
			'category_header',
			'category_footer',
			'listing',
			'embedded',
			'download_page_link',
			'most',
		);
	}

	/**
	 * Which templates are permission pairs rather than single strings.
	 *
	 * @return array
	 */
	public static function paired_keys() {
		return array( 'listing', 'embedded', 'most' );
	}

	/**
	 * The URL of the extension icon directory.
	 *
	 * Derived from the main file rather than a hardcoded "wp-downloadmanager"
	 * slug, so the icons still resolve when the plugin is installed under a
	 * different directory name.
	 *
	 * @return string
	 */
	public static function icons_url() {
		return WP_DOWNLOADMANAGER_URL . 'images/ext';
	}

	/**
	 * Every default template, keyed as the option stores them.
	 *
	 * @return array
	 */
	public static function defaults() {
		$icons  = self::icons_url();
		$denied = '<i>' . __( 'You do not have permission to download this file.', 'wp-downloadmanager' ) . '</i>';
		$img    = '<img src="' . $icons . '/%FILE_ICON%" alt="" title="" style="vertical-align: middle;" />';

		return array(
			'header'             => '<p>' . __( 'There are <strong>%TOTAL_FILES_COUNT% files</strong>, weighing <strong>%TOTAL_SIZE%</strong> with <strong>%TOTAL_HITS% hits</strong> in <strong>%FILE_CATEGORY_NAME%</strong>.</p><p>Displaying <strong>%RECORD_START%</strong> to <strong>%RECORD_END%</strong> of <strong>%TOTAL_FILES_COUNT%</strong> files.', 'wp-downloadmanager' ) . '</p>',
			'footer'             => '<form action="%DOWNLOAD_PAGE_URL%" method="get"><p><input type="hidden" name="dl_cat" value="%CATEGORY_ID%" /><input type="text" name="dl_search" value="%FILE_SEARCH_WORD%" />&nbsp;&nbsp;&nbsp;<input type="submit" value="' . __( 'Search', 'wp-downloadmanager' ) . '" /></p></form>',
			'pagingheader'       => '',
			'pagingfooter'       => '',
			'none'               => '<p style="text-align: center;">' . __( 'No Files Found.', 'wp-downloadmanager' ) . '</p>',
			'category_header'    => '<h2 id="downloadcat-%CATEGORY_ID%"><a href="%CATEGORY_URL%" title="' . __( 'View all downloads in %FILE_CATEGORY_NAME%', 'wp-downloadmanager' ) . '">%FILE_CATEGORY_NAME%</a></h2>',
			'category_footer'    => '',
			'listing'            => array(
				'<p>' . $img . '&nbsp;&nbsp;<strong><a href="%FILE_DOWNLOAD_URL%">%FILE_NAME%</a></strong><br /><strong>&raquo; %FILE_SIZE% - %FILE_HITS% ' . __( 'hits', 'wp-downloadmanager' ) . ' - %FILE_DATE%</strong><br />%FILE_DESCRIPTION%</p>',
				'<p>' . $img . '&nbsp;&nbsp;<strong>%FILE_NAME%</strong><br /><strong>&raquo; %FILE_SIZE% - %FILE_HITS% ' . __( 'hits', 'wp-downloadmanager' ) . ' - %FILE_DATE%</strong><br />' . $denied . '<br />%FILE_DESCRIPTION%</p>',
			),
			'embedded'           => array(
				'<p>' . $img . '&nbsp;&nbsp;<strong><a href="%FILE_DOWNLOAD_URL%">%FILE_NAME%</a></strong> (%FILE_SIZE%' . __( ',', 'wp-downloadmanager' ) . ' %FILE_HITS% ' . __( 'hits', 'wp-downloadmanager' ) . ')</p>',
				'<p>' . $img . '&nbsp;&nbsp;<strong>%FILE_NAME%</strong> (%FILE_SIZE%' . __( ',', 'wp-downloadmanager' ) . ' %FILE_HITS% ' . __( 'hits', 'wp-downloadmanager' ) . ')<br />' . $denied . '</p>',
			),
			'download_page_link' => '<p><a href="%DOWNLOAD_PAGE_URL%" title="' . __( 'Downloads Page', 'wp-downloadmanager' ) . '">' . __( 'Downloads Page', 'wp-downloadmanager' ) . '</a></p>',
			'most'               => array(
				'<li><a href="%FILE_DOWNLOAD_URL%">%FILE_NAME%</a> (%FILE_SIZE%' . __( ',', 'wp-downloadmanager' ) . ' %FILE_HITS% ' . __( 'hits', 'wp-downloadmanager' ) . ')</li>',
				'<li>%FILE_NAME% (%FILE_SIZE%' . __( ',', 'wp-downloadmanager' ) . ' %FILE_HITS% ' . __( 'hits', 'wp-downloadmanager' ) . ')<br />' . $denied . '</li>',
			),
		);
	}

	/**
	 * One default template, by key and permission index.
	 *
	 * @param string $key   Template key.
	 * @param int    $index 0 for permitted, 1 for denied. Ignored for singles.
	 * @return string
	 */
	public static function get_default( $key, $index = 0 ) {
		$defaults = self::defaults();

		if ( ! isset( $defaults[ $key ] ) ) {
			return '';
		}
		if ( is_array( $defaults[ $key ] ) ) {
			return isset( $defaults[ $key ][ $index ] ) ? $defaults[ $key ][ $index ] : '';
		}

		return $defaults[ $key ];
	}

	/**
	 * The defaults flattened for wp_localize_script().
	 *
	 * The reset buttons on the templates screen read from this rather than from
	 * a duplicated copy of the markup inside the script.
	 *
	 * @return array
	 */
	public static function for_script() {
		$flat = array();

		foreach ( self::defaults() as $key => $value ) {
			if ( is_array( $value ) ) {
				$flat[ $key ]        = $value[0];
				$flat[ $key . '_2' ] = $value[1];
			} else {
				$flat[ $key ] = $value;
			}
		}

		return $flat;
	}
}
