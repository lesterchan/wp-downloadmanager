<?php
/**
 * Public template tags for WP-DownloadManager.
 *
 * These are the plugin's API: themes call them directly, so their names and
 * signatures do not move even though everything behind them did in 2.0.0.
 * Each one is a thin wrapper over the class that now owns the behaviour.
 *
 * @package WP-DownloadManager
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'downloads_page' ) ) {
	/**
	 * The downloads page.
	 *
	 * @param int $category_id Restrict to one category, 0 for all.
	 * @return string
	 */
	function downloads_page( $category_id = 0 ) {
		return WP_DownloadManager_Display::downloads_page( $category_id );
	}
}

if ( ! function_exists( 'download_embedded' ) ) {
	/**
	 * Files embedded in a post or page.
	 *
	 * @param string $condition    Extra SQL for the WHERE clause.
	 * @param string $display      'both' or 'name'.
	 * @param string $sort_by      Sort column.
	 * @param string $sort_order   Sort direction.
	 * @param int    $stream_limit Cap outside a single post, 0 for no cap.
	 * @return string
	 */
	function download_embedded( $condition = '', $display = 'both', $sort_by = 'file_id', $sort_order = 'asc', $stream_limit = 0 ) {
		return WP_DownloadManager_Display::download_embedded( $condition, $display, $sort_by, $sort_order, $stream_limit );
	}
}

if ( ! function_exists( 'get_most_downloaded' ) ) {
	/**
	 * The most downloaded files.
	 *
	 * @param int  $limit   Row limit.
	 * @param int  $chars   Truncate file names to this many characters.
	 * @param bool $display Echo rather than return.
	 * @return string|void
	 */
	function get_most_downloaded( $limit = 10, $chars = 0, $display = true ) {
		return WP_DownloadManager_Display::most_downloaded( $limit, $chars, $display );
	}
}

if ( ! function_exists( 'get_recent_downloads' ) ) {
	/**
	 * The most recently added files.
	 *
	 * @param int  $limit   Row limit.
	 * @param int  $chars   Truncate file names to this many characters.
	 * @param bool $display Echo rather than return.
	 * @return string|void
	 */
	function get_recent_downloads( $limit = 10, $chars = 0, $display = true ) {
		return WP_DownloadManager_Display::recent_downloads( $limit, $chars, $display );
	}
}

if ( ! function_exists( 'get_downloads_category' ) ) {
	/**
	 * Files in one or more categories.
	 *
	 * @param int|array $cat_id  Category ID or list of them.
	 * @param int       $limit   Row limit.
	 * @param int       $chars   Truncate file names to this many characters.
	 * @param bool      $display Echo rather than return.
	 * @return string|void
	 */
	function get_downloads_category( $cat_id = 0, $limit = 10, $chars = 0, $display = true ) {
		return WP_DownloadManager_Display::downloads_category( $cat_id, $limit, $chars, $display );
	}
}

if ( ! function_exists( 'get_download_files' ) ) {
	/**
	 * Total number of files.
	 *
	 * @param bool $display Echo rather than return.
	 * @return string|void
	 */
	function get_download_files( $display = true ) {
		return WP_DownloadManager_Display::total_files( $display );
	}
}

if ( ! function_exists( 'get_download_size' ) ) {
	/**
	 * Total size of all files.
	 *
	 * @param bool $display Echo rather than return.
	 * @return string|void
	 */
	function get_download_size( $display = true ) {
		return WP_DownloadManager_Display::total_size( $display );
	}
}

if ( ! function_exists( 'get_download_hits' ) ) {
	/**
	 * Total number of hits.
	 *
	 * @param bool $display Echo rather than return.
	 * @return string|void
	 */
	function get_download_hits( $display = true ) {
		return WP_DownloadManager_Display::total_hits( $display );
	}
}

if ( ! function_exists( 'download_file_url' ) ) {
	/**
	 * The public URL that downloads a file.
	 *
	 * @param int    $file_id   File ID.
	 * @param string $file_name Stored file name.
	 * @return string
	 */
	function download_file_url( $file_id, $file_name ) {
		return WP_DownloadManager_File::download_url( $file_id, $file_name );
	}
}

if ( ! function_exists( 'download_category_url' ) ) {
	/**
	 * The URL that filters the downloads page to one category.
	 *
	 * @param int $cat_id Category ID.
	 * @return string
	 */
	function download_category_url( $cat_id ) {
		return WP_DownloadManager_Display::category_url( $cat_id );
	}
}

if ( ! function_exists( 'download_page_link' ) ) {
	/**
	 * The URL of another page of the downloads listing.
	 *
	 * @param int $page Page number.
	 * @return string
	 */
	function download_page_link( $page ) {
		return WP_DownloadManager_Display::page_link( $page );
	}
}

if ( ! function_exists( 'download_search_highlight' ) ) {
	/**
	 * Wrap search terms found in a string.
	 *
	 * @param string $search_word Space separated search terms.
	 * @param string $search_text Text to highlight within.
	 * @return string
	 */
	function download_search_highlight( $search_word, $search_text ) {
		return WP_DownloadManager_Display::search_highlight( $search_word, $search_text );
	}
}

if ( ! function_exists( 'file_extension' ) ) {
	/**
	 * The lowercased extension of a file name.
	 *
	 * @param string $filename File name or path.
	 * @return string
	 */
	function file_extension( $filename ) {
		return WP_DownloadManager_File::extension( $filename );
	}
}

if ( ! function_exists( 'file_extension_images' ) ) {
	/**
	 * The icon families the plugin can draw.
	 *
	 * Returned a list of GIF file names up to 1.69.2; the plugin ships no images
	 * at all now, so this is the list of family keys instead.
	 *
	 * @return array
	 */
	function file_extension_images() {
		return WP_DownloadManager_File::icon_families();
	}
}

if ( ! function_exists( 'file_extension_image' ) ) {
	/**
	 * The icon family a file belongs to.
	 *
	 * Returned a GIF file name up to 1.69.2. The second argument is ignored and
	 * kept only so existing calls keep working.
	 *
	 * @param string $file_name Stored file name.
	 * @param array  $images    Unused.
	 * @return string
	 */
	function file_extension_image( $file_name, $images = array() ) {
		unset( $images );

		return WP_DownloadManager_File::extension_family( $file_name );
	}
}

if ( ! function_exists( 'file_permission' ) ) {
	/**
	 * The label for a permission level.
	 *
	 * @param int $permission Stored permission value.
	 * @return string
	 */
	function file_permission( $permission ) {
		return WP_DownloadManager_File::permission_label( $permission );
	}
}

if ( ! function_exists( 'is_remote_file' ) ) {
	/**
	 * Whether a stored file name points somewhere else entirely.
	 *
	 * @param string $file_name Stored file name.
	 * @return bool
	 */
	function is_remote_file( $file_name ) {
		return WP_DownloadManager_File::is_remote( $file_name );
	}
}

if ( ! function_exists( 'is_file_remote_valid' ) ) {
	/**
	 * Whether a remote URL is one this plugin will fetch.
	 *
	 * @param string $file Remote URL.
	 * @return bool
	 */
	function is_file_remote_valid( $file ) {
		return WP_DownloadManager_File::is_remote_valid( $file );
	}
}

if ( ! function_exists( 'remote_filesize' ) ) {
	/**
	 * The size of a remote file, from its headers.
	 *
	 * @param string $uri Remote URL.
	 * @return string
	 */
	function remote_filesize( $uri ) {
		return WP_DownloadManager_File::remote_filesize( $uri );
	}
}

if ( ! function_exists( 'format_filesize' ) ) {
	/**
	 * Format a byte count in binary units.
	 *
	 * @param int $raw_size Bytes.
	 * @return string
	 */
	function format_filesize( $raw_size ) {
		return WP_DownloadManager_File::format_size( $raw_size );
	}
}

if ( ! function_exists( 'format_filesize_dec' ) ) {
	/**
	 * Format a byte count in decimal units.
	 *
	 * @param int $raw_size Bytes.
	 * @return string
	 */
	function format_filesize_dec( $raw_size ) {
		return WP_DownloadManager_File::format_size_dec( $raw_size );
	}
}

if ( ! function_exists( 'get_max_upload_size' ) ) {
	/**
	 * The largest upload PHP will accept, in bytes.
	 *
	 * @return int
	 */
	function get_max_upload_size() {
		return WP_DownloadManager_File::max_upload_size();
	}
}

if ( ! function_exists( 'get_wp_user_level' ) ) {
	/**
	 * The current user's level, derived from capabilities.
	 *
	 * @return int
	 */
	function get_wp_user_level() {
		return WP_DownloadManager_File::user_level();
	}
}

if ( ! function_exists( 'snippet_text' ) ) {
	/**
	 * Truncate a string to a character budget.
	 *
	 * @param string $text   Text.
	 * @param int    $length Maximum length, 0 for no limit.
	 * @return string
	 */
	function snippet_text( $text, $length = 0 ) {
		return WP_DownloadManager_Display::snippet_text( $text, $length );
	}
}

if ( ! function_exists( 'download_category_name' ) ) {
	/**
	 * Look up a category name by ID.
	 *
	 * @param array $categories Category list.
	 * @param int   $cat_id     Category ID.
	 * @return string
	 */
	function download_category_name( $categories, $cat_id ) {
		return WP_DownloadManager_Display::category_name( $categories, $cat_id );
	}
}
