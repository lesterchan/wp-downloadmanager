<?php
/**
 * File helpers and the download endpoint for WP-DownloadManager.
 *
 * @package WP-DownloadManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Everything about a file: naming, sizing, permissions and serving.
 */
class WP_DownloadManager_File {

	/**
	 * Hook up.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'serve' ), 5 );
	}

	/**
	 * The columns a download listing may be sorted by.
	 *
	 * The sort.by and rss.sortby settings are written by the settings screen and read
	 * straight into an ORDER BY clause, so they need an allow list rather than
	 * sanitize_text_field().
	 *
	 * @return array
	 */
	public static function sort_columns() {
		return array( 'file_id', 'file', 'file_name', 'file_size', 'file_date', 'file_updated_date', 'file_hits' );
	}

	/**
	 * Validate a sort column, falling back to the default.
	 *
	 * @param string $column   Candidate column.
	 * @param string $fallback Used when the candidate is not a real column.
	 * @return string
	 */
	public static function sort_column( $column, $fallback = 'file_id' ) {
		return in_array( $column, self::sort_columns(), true ) ? $column : $fallback;
	}

	/**
	 * Validate a sort direction.
	 *
	 * @param string $order    Candidate direction.
	 * @param string $fallback Used when the candidate is neither asc nor desc.
	 * @return string
	 */
	public static function sort_order( $order, $fallback = 'asc' ) {
		$order = strtolower( (string) $order );

		return in_array( $order, array( 'asc', 'desc' ), true ) ? $order : $fallback;
	}

	/**
	 * The lowercased extension of a file name.
	 *
	 * @param string $filename File name or path.
	 * @return string
	 */
	public static function extension( $filename ) {
		$parts = explode( '.', $filename );

		return strtolower( $parts[ count( $parts ) - 1 ] );
	}

	/**
	 * The extension icons that ship with the plugin.
	 *
	 * @return array
	 */
	public static function extension_images() {
		$images = array();
		$dir    = apply_filters( 'wp_downloadmanager_file_extension_images_path', WP_DOWNLOADMANAGER_DIR . 'images/ext' );

		if ( is_dir( $dir ) ) {
			foreach ( (array) scandir( $dir ) as $file ) {
				if ( '.' !== $file && '..' !== $file ) {
					$images[] = $file;
				}
			}
		}

		return $images;
	}

	/**
	 * The icon file name for a given file.
	 *
	 * @param string $file_name Stored file name.
	 * @param array  $images    Available icons.
	 * @return string
	 */
	public static function extension_image( $file_name, $images ) {
		$ext  = self::extension( $file_name ) . '.gif';
		$icon = 'unknown.gif';

		if ( in_array( $ext, $images, true ) ) {
			$icon = $ext;
		}

		return apply_filters( 'wp_downloadmanager_file_extension_image', $icon, $ext, $file_name );
	}

	/**
	 * Whether a stored file name points somewhere else entirely.
	 *
	 * @param string $file_name Stored file name.
	 * @return bool
	 */
	public static function is_remote( $file_name ) {
		return false !== strpos( $file_name, 'http://' )
			|| false !== strpos( $file_name, 'https://' )
			|| false !== strpos( $file_name, 'ftp://' );
	}

	/**
	 * Whether a remote URL is one this plugin will fetch.
	 *
	 * @param string $file Remote URL.
	 * @return bool
	 */
	public static function is_remote_valid( $file ) {
		$parsed = wp_parse_url( $file );

		if ( ! is_array( $parsed ) || empty( $parsed['scheme'] ) ) {
			return false;
		}

		$schemes = apply_filters( 'wp_downloadmanager_schemes', array( 'http', 'https', 'ftp' ) );
		if ( ! in_array( $parsed['scheme'], $schemes, true ) ) {
			return false;
		}

		$ports = apply_filters( 'wp_downloadmanager_ports', array( 80, 443, 21 ) );
		if ( ! empty( $parsed['port'] ) && ! in_array( $parsed['port'], $ports, true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * The size of a remote file, from its headers.
	 *
	 * @param string $uri Remote URL.
	 * @return string Size in bytes, or the "unknown" label.
	 */
	public static function remote_filesize( $uri ) {
		$response = wp_safe_remote_head( $uri, array( 'timeout' => 10 ) );

		if ( ! is_wp_error( $response ) ) {
			$length = wp_remote_retrieve_header( $response, 'content-length' );
			if ( ! empty( $length ) ) {
				return is_array( $length ) ? reset( $length ) : $length;
			}
		}

		return __( 'unknown', 'wp-downloadmanager' );
	}

	/**
	 * Format a byte count in binary units.
	 *
	 * @param int $raw_size Bytes.
	 * @return string
	 */
	public static function format_size( $raw_size ) {
		$raw_size = (int) $raw_size;

		if ( $raw_size / 1099511627776 > 1 ) {
			return number_format_i18n( $raw_size / 1099511627776, 1 ) . ' ' . __( 'TiB', 'wp-downloadmanager' );
		} elseif ( $raw_size / 1073741824 > 1 ) {
			return number_format_i18n( $raw_size / 1073741824, 1 ) . ' ' . __( 'GiB', 'wp-downloadmanager' );
		} elseif ( $raw_size / 1048576 > 1 ) {
			return number_format_i18n( $raw_size / 1048576, 1 ) . ' ' . __( 'MiB', 'wp-downloadmanager' );
		} elseif ( $raw_size / 1024 > 1 ) {
			return number_format_i18n( $raw_size / 1024, 1 ) . ' ' . __( 'KiB', 'wp-downloadmanager' );
		} elseif ( $raw_size > 1 ) {
			return number_format_i18n( $raw_size ) . ' ' . __( 'bytes', 'wp-downloadmanager' );
		}

		return __( 'unknown', 'wp-downloadmanager' );
	}

	/**
	 * Format a byte count in decimal units.
	 *
	 * @param int $raw_size Bytes.
	 * @return string
	 */
	public static function format_size_dec( $raw_size ) {
		$raw_size = (int) $raw_size;

		if ( $raw_size / 1000000000000 > 1 ) {
			return number_format_i18n( $raw_size / 1000000000000, 1 ) . ' ' . __( 'TB', 'wp-downloadmanager' );
		} elseif ( $raw_size / 1000000000 > 1 ) {
			return number_format_i18n( $raw_size / 1000000000, 1 ) . ' ' . __( 'GB', 'wp-downloadmanager' );
		} elseif ( $raw_size / 1000000 > 1 ) {
			return number_format_i18n( $raw_size / 1000000, 1 ) . ' ' . __( 'MB', 'wp-downloadmanager' );
		} elseif ( $raw_size / 1000 > 1 ) {
			return number_format_i18n( $raw_size / 1000, 1 ) . ' ' . __( 'KB', 'wp-downloadmanager' );
		} elseif ( $raw_size > 1 ) {
			return number_format_i18n( $raw_size ) . ' ' . __( 'bytes', 'wp-downloadmanager' );
		}

		return __( 'unknown', 'wp-downloadmanager' );
	}

	/**
	 * Now, as a site-local Unix timestamp.
	 *
	 * File dates have always been stored shifted by the site's GMT offset and
	 * are rendered back with gmdate(), so a row written with a true UTC
	 * timestamp would show up hours away from the ones beside it. This is what
	 * self::now() does, spelled out - the WordPress coding
	 * standard rightly flags that call, and the answer here is to be explicit
	 * about the offset rather than to change what the column means.
	 *
	 * @return int
	 */
	public static function now() {
		return time() + (int) ( (float) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
	}

	/**
	 * The largest upload PHP will accept, in bytes.
	 *
	 * @return int
	 */
	public static function max_upload_size() {
		return (int) wp_max_upload_size();
	}

	/**
	 * The label for a permission level.
	 *
	 * @param int $permission Stored permission value.
	 * @return string
	 */
	public static function permission_label( $permission ) {
		switch ( (int) $permission ) {
			case -2:
				return __( 'Hidden', 'wp-downloadmanager' );
			case -1:
				return __( 'Everyone', 'wp-downloadmanager' );
			case 0:
				return __( 'Registered Users Only', 'wp-downloadmanager' );
			case 1:
				return __( 'At Least Contributor Role', 'wp-downloadmanager' );
			case 2:
				return __( 'At Least Author Role', 'wp-downloadmanager' );
			case 7:
				return __( 'At Least Editor Role', 'wp-downloadmanager' );
			case 10:
				return __( 'At Least Administrator Role', 'wp-downloadmanager' );
		}

		return '';
	}

	/**
	 * The current user's level, derived from capabilities.
	 *
	 * The user_level field core removed in 3.0 is not consulted.
	 *
	 * @return int
	 */
	public static function user_level() {
		if ( current_user_can( 'activate_plugins' ) ) {
			return 10;
		}
		if ( current_user_can( 'delete_others_posts' ) ) {
			return 7;
		}
		if ( current_user_can( 'publish_posts' ) ) {
			return 2;
		}
		if ( current_user_can( 'edit_posts' ) ) {
			return 1;
		}
		if ( current_user_can( 'read' ) ) {
			return 0;
		}

		return -1;
	}

	/**
	 * Whether the current visitor may download a file.
	 *
	 * @param int $permission Stored permission value.
	 * @return bool
	 */
	public static function can_download( $permission ) {
		$permission = (int) $permission;
		$user_id    = get_current_user_id();

		if ( -1 === $permission ) {
			return true;
		}
		if ( 0 === $permission && $user_id > 0 ) {
			return true;
		}

		return $permission > 0 && $user_id > 0 && self::user_level() >= $permission;
	}

	/**
	 * Constrain an upload subfolder to the downloads directory.
	 *
	 * The subfolder comes from a select the admin screen builds, but nothing
	 * stopped a hand-crafted POST from sending '../../..' and dropping the
	 * upload anywhere the web server could write.
	 *
	 * @param string $file_path Downloads directory.
	 * @param string $subfolder Requested subfolder.
	 * @return string The subfolder, or '/' when it escapes the directory.
	 */
	public static function safe_subfolder( $file_path, $subfolder ) {
		$subfolder = str_replace( '\\', '/', (string) $subfolder );

		if ( '' === $subfolder || '/' === $subfolder ) {
			return '/';
		}
		if ( false !== strpos( $subfolder, '..' ) || false !== strpos( $subfolder, "\0" ) ) {
			return '/';
		}

		$real_base   = realpath( $file_path );
		$real_target = realpath( rtrim( $file_path, '/' ) . '/' . ltrim( $subfolder, '/' ) );

		if ( false === $real_base || false === $real_target ) {
			return '/';
		}
		// The separator matters: without it /files-public would pass as /files.
		if ( 0 !== strpos( $real_target . DIRECTORY_SEPARATOR, rtrim( $real_base, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR ) ) {
			return '/';
		}

		return $subfolder;
	}

	/**
	 * Strip characters that do not belong in a stored file name.
	 *
	 * Credits: imvain2.
	 *
	 * @param string $file_path Downloads directory.
	 * @param string $file      Relative file name.
	 * @return string
	 */
	public static function rename_file( $file_path, $file ) {
		$rename   = false;
		$file_old = $file;
		$file     = str_replace( ' ', '_', $file );
		$file     = preg_replace( '/[^A-Za-z0-9\-._\/]/', '', $file );

		if ( $file !== $file_old ) {
			$rename = rename( $file_path . $file_old, $file_path . $file );
		}

		return $rename ? $file : $file_old;
	}

	/**
	 * The public URL that downloads a file.
	 *
	 * @param int    $file_id   File ID.
	 * @param string $file_name Stored file name.
	 * @return string
	 */
	public static function download_url( $file_id, $file_name ) {
		$file_id   = (int) $file_id;
		$file_name = stripslashes( $file_name );

		if ( ! self::is_remote( $file_name ) ) {
			$file_name = substr( $file_name, 1 );
		}

		$use_filename   = (int) WP_DownloadManager_Options::get( 'use_filename', 0 );
		$nice_permalink = (int) WP_DownloadManager_Options::get( 'nice_permalink', 1 );
		$home           = get_option( 'home' );

		if ( 1 === $nice_permalink ) {
			return 1 === $use_filename
				? $home . '/download/' . $file_name
				: $home . '/download/' . $file_id . '/';
		}

		return 1 === $use_filename
			? $home . '/?dl_name=' . $file_name
			: $home . '/?dl_id=' . $file_id;
	}

	/**
	 * Serve a file for ?dl_id= / ?dl_name= and the /download/ rewrites.
	 *
	 * @return void
	 */
	public static function serve() {
		global $wpdb;

		$dl_id   = (int) get_query_var( 'dl_id' );
		$dl_name = (string) get_query_var( 'dl_name' );

		if ( 'rss' === $dl_name ) {
			// $load_once = false: load_template() defaults to require_once, which
			// would make the feed render only the first time it is asked for in
			// a given process. get_template_part() passes false for the same
			// reason - this is a template, not a library.
			load_template( WP_DOWNLOADMANAGER_DIR . 'includes/feed-rss.php', false );
			self::finish();
		}

		if ( $dl_id <= 0 && '' === $dl_name ) {
			return;
		}

		$use_filename = (int) WP_DownloadManager_Options::get( 'use_filename', 0 );
		$file         = null;

		if ( $dl_id > 0 && 0 === $use_filename ) {
			$file = $wpdb->get_row( $wpdb->prepare( "SELECT file_id, file, file_permission FROM {$wpdb->downloads} WHERE file_id = %d AND file_permission != -2", $dl_id ) );
		} elseif ( '' !== $dl_name && 1 === $use_filename ) {
			if ( ! self::is_remote( $dl_name ) ) {
				$dl_name = '/' . $dl_name;
			}
			$file = $wpdb->get_row( $wpdb->prepare( "SELECT file_id, file, file_permission FROM {$wpdb->downloads} WHERE file = %s AND file_permission != -2", $dl_name ) );
		}

		if ( empty( $file ) ) {
			status_header( 404 );
			wp_die( esc_html__( 'Invalid File ID or File Name.', 'wp-downloadmanager' ), '', array( 'response' => 404 ) );
		}

		$file_path       = stripslashes( WP_DownloadManager_Options::get( 'path.dir' ) );
		$file_url        = stripslashes( WP_DownloadManager_Options::get( 'path.url' ) );
		$method          = (int) WP_DownloadManager_Options::get( 'method', 1 );
		$file_id         = (int) $file->file_id;
		$file_name       = stripslashes( $file->file );
		$file_permission = (int) $file->file_permission;

		if ( ! self::can_download( $file_permission ) ) {
			wp_die( esc_html__( 'You do not have permission to download this file.', 'wp-downloadmanager' ), '', array( 'response' => 403 ) );
		}

		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->downloads} SET file_hits = (file_hits + 1), file_last_downloaded_date = %s WHERE file_id = %d AND file_permission != -2", self::now(), $file_id ) );

		if ( ! self::is_remote( $file_name ) ) {
			if ( ! is_file( $file_path . $file_name ) ) {
				status_header( 404 );
				wp_die( esc_html__( 'File does not exist.', 'wp-downloadmanager' ), '', array( 'response' => 404 ) );
			}

			if ( 0 === $method ) {
				self::send_headers( basename( $file_name ), filesize( $file_path . $file_name ) );
				readfile( $file_path . $file_name );
			} else {
				wp_redirect( $file_url . $file_name );
			}
			self::finish();
		}

		if ( ini_get( 'allow_url_fopen' ) && 0 === $method ) {
			$file_size = self::remote_filesize( $file_name );
			self::send_headers( basename( $file_name ), __( 'unknown', 'wp-downloadmanager' ) === $file_size ? 0 : $file_size );
			readfile( $file_name );
		} else {
			wp_redirect( $file_name );
		}
		self::finish();
	}

	/**
	 * End the request after the endpoint has handed a file over.
	 *
	 * Every branch of serve() ends here rather than calling exit directly, so
	 * there is one place that fires the action below - which is both a genuine
	 * extension point for anyone wanting to log downloads, and what lets the
	 * test suite observe the endpoint instead of being killed by it.
	 *
	 * @return void
	 */
	protected static function finish() {
		/**
		 * Fires immediately before the download endpoint ends the request.
		 *
		 * @since 2.0.0
		 */
		do_action( 'wp_downloadmanager_served' );

		exit;
	}

	/**
	 * Emit the download headers.
	 *
	 * @param string $basename  File name shown to the browser.
	 * @param int    $file_size Size in bytes, 0 to omit Content-Length.
	 * @return void
	 */
	protected static function send_headers( $basename, $file_size ) {
		// Same guard as the feed: with output already started these would be
		// warnings printed into the file being served.
		if ( headers_sent() ) {
			return;
		}

		nocache_headers();
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="' . $basename . '"' );
		header( 'Content-Transfer-Encoding: binary' );

		if ( $file_size ) {
			header( 'Content-Length: ' . $file_size );
		}
	}
}
