<?php
/*
Plugin Name: WP-DownloadManager
Plugin URI: https://lesterchan.net/portfolio/programming/php/
Description: Adds a simple download manager to your WordPress blog.
Version: 1.69.2
Author: Lester 'GaMerZ' Chan
Author URI: https://lesterchan.net
Text Domain: wp-downloadmanager
*/


/*
	Copyright 2026  Lester Chan  (email : lesterchan@gmail.com)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/


// Version
define( 'WP_DOWNLOADMANAGER_VERSION', '1.69.2' );

// Create text domain for translations
add_action( 'plugins_loaded', 'downloadmanager_textdomain' );
function downloadmanager_textdomain() {
	load_plugin_textdomain( 'wp-downloadmanager' );
}


// Downloads Table Name
// Registering the name in $wpdb->tables is what makes it survive
// switch_to_blog(): wpdb::set_blog_id() rebuilds every registered table name
// against the new prefix, while a bare assignment keeps pointing at whichever
// site happened to be current when this file loaded.
global $wpdb;
if ( ! in_array( 'downloads', $wpdb->tables, true ) ) {
	$wpdb->tables[] = 'downloads';
}
$wpdb->downloads = $wpdb->prefix . 'downloads';


// Function: Downloads Administration Menu
add_action( 'admin_menu', 'downloads_menu' );
function downloads_menu() {
	add_menu_page( __( 'Downloads', 'wp-downloadmanager' ), __( 'Downloads', 'wp-downloadmanager' ), 'manage_downloads', 'wp-downloadmanager/download-manager.php', '', 'dashicons-download' );

	add_submenu_page( 'wp-downloadmanager/download-manager.php', __( 'Manage Downloads', 'wp-downloadmanager' ), __( 'Manage Downloads', 'wp-downloadmanager' ), 'manage_downloads', 'wp-downloadmanager/download-manager.php' );
	add_submenu_page( 'wp-downloadmanager/download-manager.php', __( 'Add File', 'wp-downloadmanager' ), __( 'Add File', 'wp-downloadmanager' ), 'manage_downloads', 'wp-downloadmanager/download-add.php' );
	add_submenu_page( 'wp-downloadmanager/download-manager.php', __( 'Download Options', 'wp-downloadmanager' ), __( 'Download Options', 'wp-downloadmanager' ), 'manage_downloads', 'wp-downloadmanager/download-options.php' );
	add_submenu_page( 'wp-downloadmanager/download-manager.php', __( 'Download Templates', 'wp-downloadmanager' ), __( 'Download Templates', 'wp-downloadmanager' ), 'manage_downloads', 'wp-downloadmanager/download-templates.php' );
}


// Function: Enqueue Downloads Stylesheets
add_action( 'wp_enqueue_scripts', 'downloads_stylesheets' );
function downloads_stylesheets() {
	if ( @file_exists( TEMPLATEPATH . '/download-css.css' ) ) {
		wp_enqueue_style( 'wp-downloadmanager', get_stylesheet_directory_uri() . '/download-css.css', false, WP_DOWNLOADMANAGER_VERSION, 'all' );
	} else {
		wp_enqueue_style( 'wp-downloadmanager', plugins_url( 'wp-downloadmanager/download-css.css' ), false, WP_DOWNLOADMANAGER_VERSION, 'all' );
	}
}


// Function: Enqueue Downloads Stylesheets In WP-Admin
add_action( 'admin_enqueue_scripts', 'downloads_stylesheets_admin' );
function downloads_stylesheets_admin( $hook_suffix ) {
	$downloads_admin_pages = array( 'wp-downloadmanager/download-manager.php', 'wp-downloadmanager/download-add.php', 'wp-downloadmanager/download-options.php', 'wp-downloadmanager/download-templates.php', 'wp-downloadmanager/download-uninstall.php' );
	if ( in_array( $hook_suffix, $downloads_admin_pages ) ) {
		wp_enqueue_style( 'wp-downloadmanager-admin', plugins_url( 'wp-downloadmanager/download-admin-css.css' ), false, WP_DOWNLOADMANAGER_VERSION, 'all' );
	}
}


// Function: Displays Download Manager Footer In WP-Admin
add_action( 'admin_footer-post-new.php', 'downloads_footer_admin' );
add_action( 'admin_footer-post.php', 'downloads_footer_admin' );
add_action( 'admin_footer-page-new.php', 'downloads_footer_admin' );
add_action( 'admin_footer-page.php', 'downloads_footer_admin' );
function downloads_footer_admin() {
	?>
	<script type="text/javascript">
		QTags.addButton('ed_wp_downloadmanager', '<?php echo esc_js( __( 'Download', 'wp-downloadmanager' ) ); ?>', function() {
			var download_id = jQuery.trim(prompt('<?php echo esc_js( __( 'Enter File ID (Separate Multiple IDs By A Comma)', 'wp-downloadmanager' ) ); ?>'));
			if (download_id != null && download_id != "") {
				QTags.insertContent('[download id="' + download_id + '"]');
			}
		});
	</script>
	<?php
}


// Function: Add Quick Tag For Poll In TinyMCE >= WordPress 2.5
add_action( 'init', 'download_tinymce_addbuttons' );
function download_tinymce_addbuttons() {
	if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'edit_pages' ) ) {
		return;
	}
	if ( get_user_option( 'rich_editing' ) == 'true' ) {
		add_filter( 'mce_external_plugins', 'download_tinymce_addplugin' );
		add_filter( 'mce_buttons', 'download_tinymce_registerbutton' );
		add_filter( 'wp_mce_translation', 'download_tinymce_translation' );
	}
}
function download_tinymce_registerbutton( $buttons ) {
	array_push( $buttons, 'separator', 'downloadmanager' );
	return $buttons;
}
function download_tinymce_addplugin( $plugin_array ) {
	if ( WP_DEBUG ) {
		$plugin_array['downloadmanager'] = plugins_url( 'wp-downloadmanager/tinymce/plugins/downloadmanager/plugin.js?v=' . WP_DOWNLOADMANAGER_VERSION );
	} else {
		$plugin_array['downloadmanager'] = plugins_url( 'wp-downloadmanager/tinymce/plugins/downloadmanager/plugin.min.js?v= ' . WP_DOWNLOADMANAGER_VERSION );
	}
	return $plugin_array;
}
function download_tinymce_translation( $mce_translation ) {
	$mce_translation['Enter File ID (Separate Multiple IDs By A Comma)'] = esc_js( __( 'Enter File ID (Separate Multiple IDs By A Comma)', 'wp-downloadmanager' ) );
	$mce_translation['Insert File Download']                             = esc_js( __( 'Insert File Download', 'wp-downloadmanager' ) );
	return $mce_translation;
}


// Function: Add Download Query Vars
add_filter( 'query_vars', 'download_query_vars' );
function download_query_vars( $public_query_vars ) {
	$public_query_vars[] = 'dl_id';
	$public_query_vars[] = 'dl_name';
	return $public_query_vars;
}


// Function: Download htaccess ReWrite Rules
add_filter( 'generate_rewrite_rules', 'download_rewrite' );
function download_rewrite( $wp_rewrite ) {
	$wp_rewrite->rules = array_merge(
		array(
			'download/([0-9]{1,})/?$' => 'index.php?dl_id=$matches[1]',
			'download/(.*)$'          => 'index.php?dl_name=$matches[1]',
		),
		$wp_rewrite->rules
	);
}


// Function: Add Download RSS Link To Download Page
add_action( 'wp_head', 'download_rss_link' );
function download_rss_link() {
	if ( is_page() && strpos( get_option( 'download_page_url' ), $_SERVER['REQUEST_URI'] ) ) {
		$download_nice_permalink = (int) get_option( 'download_nice_permalink' );
		if ( $download_nice_permalink == 1 ) {
			$download_rss_link = get_option( 'home' ) . '/download/rss/';
		} else {
			$download_rss_link = get_option( 'home' ) . '/?dl_name=rss';
		}
		echo '<link rel="alternate" type="application/rss+xml" title="' . get_bloginfo_rss( 'name' ) . __( ' Downloads RSS Feed', 'wp-downloadmanager' ) . '" href="' . $download_rss_link . '" />' . "\n";
	}
}


// Function: Download File
add_action( 'template_redirect', 'download_file', 5 );
function download_file() {
	global $wpdb, $user_ID;
	$dl_id                            = (int) get_query_var( 'dl_id' );
	$dl_name                          = (string) get_query_var( 'dl_name' );
	$download_options                 = (array) get_option( 'download_options' );
	$download_options['use_filename'] = (int) ( isset( $download_options['use_filename'] ) ? $download_options['use_filename'] : 0 );
	if ( $dl_name === 'rss' ) {
		load_template( WP_PLUGIN_DIR . '/wp-downloadmanager/download-rss.php' );
		exit;
	}
	if ( $dl_id > 0 || ! empty( $dl_name ) ) {
		if ( $dl_id > 0 && $download_options['use_filename'] === 0 ) {
			$file = $wpdb->get_row( $wpdb->prepare( "SELECT file_id, file, file_permission FROM $wpdb->downloads WHERE file_id = %d AND file_permission != -2", $dl_id ) );
		} elseif ( ! empty( $dl_name ) && $download_options['use_filename'] === 1 ) {
			if ( ! is_remote_file( $dl_name ) ) {
				$dl_name = '/' . $dl_name;
			}
			$file = $wpdb->get_row( $wpdb->prepare( "SELECT file_id, file, file_permission FROM $wpdb->downloads WHERE file = %s AND file_permission != -2", $dl_name ) );
		}
		if ( empty( $file ) ) {
			header( 'HTTP/1.0 404 Not Found' );
			die( __( 'Invalid File ID or File Name.', 'wp-downloadmanager' ) );
		}
		$file_path       = stripslashes( get_option( 'download_path' ) );
		$file_url        = stripslashes( get_option( 'download_path_url' ) );
		$download_method = (int) get_option( 'download_method' );
		$file_id         = (int) $file->file_id;
		$file_name       = stripslashes( $file->file );
		$file_permission = (int) $file->file_permission;
		$current_user    = wp_get_current_user();
		if ( $file_permission === -1 || ( $file_permission === 0 && (int) $user_ID > 0 ) || ( $file_permission > 0 && get_wp_user_level() >= $file_permission && (int) $user_ID > 0 ) ) {
			$update_hits = $wpdb->query( $wpdb->prepare( "UPDATE $wpdb->downloads SET file_hits = (file_hits + 1), file_last_downloaded_date = %s WHERE file_id = %d AND file_permission != -2", current_time( 'timestamp' ), $file_id ) );
			if ( ! is_remote_file( $file_name ) ) {
				if ( ! is_file( $file_path . $file_name ) ) {
					header( 'HTTP/1.0 404 Not Found' );
					die( __( 'File does not exist.', 'wp-downloadmanager' ) );
				}
				if ( $download_method === 0 ) {
					header( 'Pragma: public' );
					header( 'Expires: 0' );
					header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
					header( 'Content-Type: application/force-download' );
					header( 'Content-Type: application/octet-stream' );
					header( 'Content-Type: application/download' );
					header( 'Content-Disposition: attachment; filename=' . basename( $file_name ) . ';' );
					header( 'Content-Transfer-Encoding: binary' );
					header( 'Content-Length: ' . filesize( $file_path . $file_name ) );
					@readfile( $file_path . $file_name );
				} else {
					header( 'Location: ' . $file_url . $file_name );
				}
				exit();
			} else {
				if ( ini_get( 'allow_url_fopen' ) && $download_method == 0 ) {
					header( 'Pragma: public' );
					header( 'Expires: 0' );
					header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
					header( 'Content-Type: application/force-download' );
					header( 'Content-Type: application/octet-stream' );
					header( 'Content-Type: application/download' );
					header( 'Content-Disposition: attachment; filename=' . basename( $file_name ) . ';' );
					header( 'Content-Transfer-Encoding: binary' );
					$file_size = remote_filesize( $file_name );
					if ( $file_size !== __( 'unknown', 'wp-downloadmanager' ) ) {
						header( 'Content-Length: ' . $file_size );
					}
					@readfile( $file_name );
				} else {
					header( 'Location: ' . $file_name );
				}
				exit();
			}
		} else {
			_e( 'You do not have permission to download this file.', 'wp-downloadmanager' );
			exit();
		}
	}
}


// Function: Print Out File Extension Image
function file_extension_image( $file_name, $file_ext_images ) {
	$file_ext             = file_extension( $file_name ) . '.gif';
	$file_extension_image = 'unknown.gif';
	if ( in_array( $file_ext, $file_ext_images, true ) ) {
		$file_extension_image = $file_ext;
	}

	return apply_filters( 'wp_downloadmanager_file_extension_image', $file_extension_image, $file_ext, $file_name );
}


// Function: Get File Extension Images
function file_extension_images() {
	$file_ext_images = array();
	$dir             = apply_filters( 'wp_downloadmanager_file_extension_images_path', WP_PLUGIN_DIR . '/wp-downloadmanager/images/ext' );
	if ( is_dir( $dir ) ) {
		if ( $dh = opendir( $dir ) ) {
			while ( ( $file = readdir( $dh ) ) !== false ) {
				if ( $file != '.' && $file != '..' ) {
					$file_ext_images[] = $file;
				}
			}
			closedir( $dh );
		}
	}
	return $file_ext_images;
}


// Function: Get File Extension
if ( ! function_exists( 'file_extension' ) ) {
	function file_extension( $filename ) {
		$file_ext = explode( '.', $filename );
		$file_ext = $file_ext[ sizeof( $file_ext ) - 1 ];
		$file_ext = strtolower( $file_ext );
		return $file_ext;
	}
}


// Function: Get Remote File Size
if ( ! function_exists( 'remote_filesize' ) ) {
	function remote_filesize( $uri ) {
		$header_array = @get_headers( $uri, 1 );
		$file_size    = $header_array['Content-Length'];
		if ( ! empty( $file_size ) ) {
			return $file_size;
		} else {
			return __( 'unknown', 'wp-downloadmanager' );
		}
	}
}


// Function: Format Bytes Into TiB/GiB/MiB/KiB/Bytes
if ( ! function_exists( 'format_filesize' ) ) {
	function format_filesize( $rawSize ) {
		$rawSize = (int) $rawSize;
		if ( $rawSize / 1099511627776 > 1 ) {
			return number_format_i18n( $rawSize / 1099511627776, 1 ) . ' ' . __( 'TiB', 'wp-downloadmanager' );
		} elseif ( $rawSize / 1073741824 > 1 ) {
			return number_format_i18n( $rawSize / 1073741824, 1 ) . ' ' . __( 'GiB', 'wp-downloadmanager' );
		} elseif ( $rawSize / 1048576 > 1 ) {
			return number_format_i18n( $rawSize / 1048576, 1 ) . ' ' . __( 'MiB', 'wp-downloadmanager' );
		} elseif ( $rawSize / 1024 > 1 ) {
			return number_format_i18n( $rawSize / 1024, 1 ) . ' ' . __( 'KiB', 'wp-downloadmanager' );
		} elseif ( $rawSize > 1 ) {
			return number_format_i18n( $rawSize ) . ' ' . __( 'bytes', 'wp-downloadmanager' );
		} else {
			return __( 'unknown', 'wp-downloadmanager' );
		}
	}
}

// Function: Format Bytes Into TB/GB/MB/KB/Bytes
if ( ! function_exists( 'format_filesize_dec' ) ) {
	function format_filesize_dec( $rawSize ) {
		$rawSize = (int) $rawSize;
		if ( $rawSize / 1000000000000 > 1 ) {
			return number_format_i18n( $rawSize / 1000000000000, 1 ) . ' ' . __( 'TB', 'wp-downloadmanager' );
		} elseif ( $rawSize / 1000000000 > 1 ) {
			return number_format_i18n( $rawSize / 1000000000, 1 ) . ' ' . __( 'GB', 'wp-downloadmanager' );
		} elseif ( $rawSize / 1000000 > 1 ) {
			return number_format_i18n( $rawSize / 1000000, 1 ) . ' ' . __( 'MB', 'wp-downloadmanager' );
		} elseif ( $rawSize / 1000 > 1 ) {
			return number_format_i18n( $rawSize / 1000, 1 ) . ' ' . __( 'KB', 'wp-downloadmanager' );
		} elseif ( $rawSize > 1 ) {
			return number_format_i18n( $rawSize ) . ' ' . __( 'bytes', 'wp-downloadmanager' );
		} else {
			return __( 'unknown', 'wp-downloadmanager' );
		}
	}
}

// Function: Get Max File Size That Can Be Uploaded
function get_max_upload_size() {
	$maxsize = ini_get( 'upload_max_filesize' );
	if ( ! is_numeric( $maxsize ) ) {
		if ( strpos( $maxsize, 'M' ) !== false ) {
			$maxsize = (int) $maxsize * 1024 * 1024;
		} elseif ( strpos( $maxsize, 'K' ) !== false ) {
			$maxsize = (int) $maxsize * 1024;
		} elseif ( strpos( $maxsize, 'G' ) !== false ) {
			$maxsize = (int) $maxsize * 1024 * 1024 * 1024;
		}
	}
	return $maxsize;
}


// Function: Is Remote File
function is_remote_file( $file_name ) {
	if ( strpos( $file_name, 'http://' ) === false && strpos( $file_name, 'https://' ) === false && strpos( $file_name, 'ftp://' ) === false ) {
		return false;
	}
	return true;
}


// Function: Snippet Text
if ( ! function_exists( 'snippet_text' ) ) {
	function snippet_text( $text, $length = 0 ) {
		if ( defined( 'MB_OVERLOAD_STRING' ) ) {
			$text = @html_entity_decode( $text, ENT_QUOTES, get_option( 'blog_charset' ) );
			if ( mb_strlen( $text ) > $length ) {
				return htmlentities( mb_substr( $text, 0, $length ), ENT_COMPAT, get_option( 'blog_charset' ) ) . '...';
			} else {
				return htmlentities( $text, ENT_COMPAT, get_option( 'blog_charset' ) );
			}
		} else {
			$text = @html_entity_decode( $text, ENT_QUOTES, get_option( 'blog_charset' ) );
			if ( strlen( $text ) > $length ) {
				return htmlentities( substr( $text, 0, $length ), ENT_COMPAT, get_option( 'blog_charset' ) ) . '...';
			} else {
				return htmlentities( $text, ENT_COMPAT, get_option( 'blog_charset' ) );
			}
		}
	}
}


// Function: Download URL
function download_file_url( $file_id, $file_name ) {
	$file_id   = (int) $file_id;
	$file_name = stripslashes( $file_name );
	if ( ! is_remote_file( $file_name ) ) {
		$file_name = substr( $file_name, 1 );
	}
	$download_options        = get_option( 'download_options' );
	$download_use_filename   = (int) $download_options['use_filename'];
	$download_nice_permalink = (int) get_option( 'download_nice_permalink' );
	if ( $download_nice_permalink === 1 ) {
		if ( $download_use_filename === 1 ) {
			$download_file_url = get_option( 'home' ) . '/download/' . $file_name;
		} else {
			$download_file_url = get_option( 'home' ) . '/download/' . $file_id . '/';
		}
	} elseif ( $download_use_filename === 1 ) {
			$download_file_url = get_option( 'home' ) . '/?dl_name=' . $file_name;
	} else {
		$download_file_url = get_option( 'home' ) . '/?dl_id=' . $file_id;
	}
	return $download_file_url;
}


// Function: The Columns A Download Listing May Be Sorted By
// download_sort['by'] and download_options['rss_sortby'] are written by the
// options screen and read straight into an ORDER BY clause, so they need an
// allow list rather than sanitize_text_field().
function download_sort_columns() {
	return array( 'file_id', 'file', 'file_name', 'file_size', 'file_date', 'file_updated_date', 'file_hits' );
}


// Function: Validate A Sort Column, Falling Back To The Default
function download_sort_column( $column, $fallback = 'file_id' ) {
	return in_array( $column, download_sort_columns(), true ) ? $column : $fallback;
}


// Function: Validate A Sort Direction
function download_sort_order( $order, $fallback = 'asc' ) {
	$order = strtolower( (string) $order );
	return in_array( $order, array( 'asc', 'desc' ), true ) ? $order : $fallback;
}


// Function: The Files The Downloads Feed Lists
// Extracted from download-rss.php so the sort column and the row limit are
// validated in one place - both come from the options screen and both used to be
// interpolated into the query as written.
function downloadmanager_feed_files() {
	global $wpdb;

	$download_options = (array) get_option( 'download_options' );
	$sortby           = download_sort_column( isset( $download_options['rss_sortby'] ) ? $download_options['rss_sortby'] : '', 'file_date' );
	$limit            = max( 1, (int) ( isset( $download_options['rss_limit'] ) ? $download_options['rss_limit'] : 20 ) );

	return (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM $wpdb->downloads WHERE file_permission != -2 ORDER BY {$sortby} DESC LIMIT %d",
			$limit
		)
	);
}


// Function: Look Up A Category Name By ID
// Deleting a category on the options screen leaves files pointing at an ID that
// is no longer in the list, which used to be an "Undefined array key" warning on
// every affected row.
function download_category_name( $categories, $cat_id ) {
	$cat_id = (int) $cat_id;
	if ( ! is_array( $categories ) || ! isset( $categories[ $cat_id ] ) ) {
		return '';
	}

	return stripslashes( $categories[ $cat_id ] );
}


// Function: The Current Request's Query Args, Unslashed
// $_GET is slashed by WordPress. http_build_query() would otherwise re-encode
// the added backslashes into every generated link.
function download_query_args() {
	return array_map( 'sanitize_text_field', wp_unslash( $_GET ) );
}


// Function: Download Category URL
function download_category_url( $cat_id ) {
	return esc_url( get_option( 'download_page_url' ) . '?' . http_build_query( array_merge( download_query_args(), array( 'dl_cat' => (int) $cat_id ) ) ) );
}


// Function: Download Page Link
function download_page_link( $page ) {
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	return esc_url( parse_url( $request_uri, PHP_URL_PATH ) . '?' . http_build_query( array_merge( download_query_args(), array( 'dl_page' => (int) $page ) ) ) );
}


// Function Highlight Download Search
function download_search_highlight( $search_word, $search_text ) {
	if ( ! empty( $search_word ) ) {
		$search_words_array = explode( ' ', $search_word );
		foreach ( $search_words_array as $search_word_array ) {
			if ( '' === trim( $search_word_array ) ) {
				continue;
			}
			// preg_quote() the term: it comes from ?dl_search=, so a bare '(' was
			// enough to make preg_replace() fail compilation, return null and blank
			// out the file name it was supposed to be highlighting.
			$pattern  = '/\w*?' . preg_quote( $search_word_array, '/' ) . '\w*/i';
			$replaced = preg_replace( $pattern, '<span class="download-search-highlight">$0</span>', $search_text );
			if ( null !== $replaced ) {
				$search_text = $replaced;
			}
		}
	}
	return $search_text;
}


// Function: Short Code For Inserting Downloads Page Into Page
add_shortcode( 'page_download', 'download_page_shortcode' );
add_shortcode( 'page_downloads', 'download_page_shortcode' );
function download_page_shortcode( $atts ) {
	$attributes = shortcode_atts( array( 'category' => 0 ), $atts );
	return downloads_page( $attributes['category'] );
}


// Function: Short Code For Inserting Files Download Into Posts
add_shortcode( 'download', 'download_shortcode' );
function download_shortcode( $atts ) {
	$attributes = shortcode_atts(
		array(
			'id'           => 0,
			'category'     => 0,
			'display'      => 'both',
			'sort_by'      => 'file_id',
			'sort_order'   => 'asc',
			'stream_limit' => 0,
		),
		$atts
	);
	if ( ! is_feed() ) {
		$conditions = array();
		$id         = $attributes['id'];
		$category   = $attributes['category'];

		// To maintain backward compatibility with [download=1].
		if ( ! $id && ! empty( $atts[0] ) ) {
			$id = trim( $atts[0], '="\'' );
		}

		if ( $id !== 0 ) {
			if ( strpos( $id, ',' ) !== false ) {
				$ids          = array_map( 'intval', explode( ',', $id ) );
				$conditions[] = 'file_id IN (' . implode( ',', $ids ) . ')';
			} else {
				$conditions[] = 'file_id = ' . (int) $id;
			}
		}
		if ( $category !== 0 ) {
			if ( strpos( $category, ',' ) !== false ) {
				$categories   = array_map( 'intval', explode( ',', $category ) );
				$conditions[] = 'file_category IN (' . implode( ',', $categories ) . ')';
			} else {
				$conditions[] = 'file_category = ' . (int) $category;
			}
		}
		if ( $conditions ) {
			return download_embedded( implode( ' AND ', $conditions ), $attributes['display'], $attributes['sort_by'], $attributes['sort_order'], $attributes['stream_limit'] );
		}

		return '';
	}

	return __( 'Note: There is a file embedded within this post, please visit this post to download the file.', 'wp-downloadmanager' );
}


// Function: Downloads Page
function downloads_page( $category_id = 0 ) {
	global $wpdb, $user_ID;
	// Variables
	$category_id            = (int) $category_id;
	$category               = ! empty( $_GET['dl_cat'] ) ? (int) $_GET['dl_cat'] : 0;
	$page                   = ! empty( $_GET['dl_page'] ) ? (int) $_GET['dl_page'] : 0;
	$search_word            = ! empty( $_GET['dl_search'] ) ? strip_tags( trim( sanitize_text_field( wp_unslash( $_GET['dl_search'] ) ) ) ) : '';
	$search_words_array     = array();
	$search                 = $search_word;
	$download_categories    = (array) get_option( 'download_categories' );
	$download_categories[0] = __( 'total', 'wp-downloadmanager' );
	$category_stats         = array();
	$total_stats            = array(
		'files' => 0,
		'size'  => 0,
		'hits'  => 0,
	);
	$file_sort              = (array) get_option( 'download_sort' );
	$file_sort['by']        = download_sort_column( isset( $file_sort['by'] ) ? $file_sort['by'] : '', 'file_name' );
	$file_sort['order']     = download_sort_order( isset( $file_sort['order'] ) ? $file_sort['order'] : '' );
	$file_sort['perpage']   = max( 1, (int) ( isset( $file_sort['perpage'] ) ? $file_sort['perpage'] : 20 ) );
	$file_sort['group']     = (int) ( isset( $file_sort['group'] ) ? $file_sort['group'] : 0 );
	if ( $file_sort['by'] === 'file_date' ) {
		$file_sort['by'] = 'FROM_UNIXTIME(file_date)';
	}
	$file_extensions_images = file_extension_images();
	$current_user           = wp_get_current_user();
	// If There Is Category Set
	$category_sql = '';
	if ( $category === 0 && $category_id > 0 ) {
		$category = $category_id;
	}
	if ( $category > 0 ) {
		$category_sql = "AND file_category = $category";
	}
	// If There Is A Search Term
	// Kept as a placeholder fragment plus its arguments rather than a prepared
	// string: the clause goes into two more queries, and feeding an
	// already-prepared fragment back through prepare() would have the % wildcards
	// either re-read as placeholders or flagged as unescaped.
	$search_sql  = '';
	$search_args = array();
	if ( ! empty( $search ) ) {
		$search_words_array = explode( ' ', $search_word );
		foreach ( $search_words_array as $search_word_array ) {
			if ( '' === trim( $search_word_array ) ) {
				continue;
			}
			// esc_like() so a literal % or _ in the term is not read as a wildcard.
			$like        = '%' . $wpdb->esc_like( $search_word_array ) . '%';
			$search_sql .= ' AND ((file_name LIKE %s OR file_des LIKE %s OR file LIKE %s))';
			array_push( $search_args, $like, $like, $like );
		}
	}
	// Calculate Categories And Total Stats
	$categories_sql = "SELECT file_category, COUNT(file_id) as category_files, SUM(file_size) category_size, SUM(file_hits) as category_hits FROM $wpdb->downloads WHERE 1=1 $category_sql $search_sql AND file_permission != -2 GROUP BY file_category";
	$categories     = $wpdb->get_results( $search_args ? $wpdb->prepare( $categories_sql, $search_args ) : $categories_sql );
	if ( $categories ) {
		foreach ( $categories as $cat ) {
			$cat_id                             = (int) $cat->file_category;
			$category_stats[ $cat_id ]['files'] = $cat->category_files;
			$category_stats[ $cat_id ]['hits']  = $cat->category_hits;
			$category_stats[ $cat_id ]['size']  = $cat->category_size;
			$total_stats['files']              += $cat->category_files;
			$total_stats['hits']               += $cat->category_hits;
			$total_stats['size']               += $cat->category_size;
		}
	}

	// Calculate Paging
	$numposts = $total_stats['files'];
	$perpage  = $file_sort['perpage'];
	$max_page = ceil( $numposts / $perpage );
	if ( empty( $page ) || $page === 0 ) {
		$page = 1;
	}
	$offset                = ( $page - 1 ) * $perpage;
	$pages_to_show         = 10;
	$pages_to_show_minus_1 = $pages_to_show - 1;
	$half_page_start       = floor( $pages_to_show_minus_1 / 2 );
	$half_page_end         = ceil( $pages_to_show_minus_1 / 2 );
	$start_page            = $page - $half_page_start;
	if ( $start_page <= 0 ) {
		$start_page = 1;
	}
	$end_page = $page + $half_page_end;
	if ( ( $end_page - $start_page ) !== $pages_to_show_minus_1 ) {
		$end_page = $start_page + $pages_to_show_minus_1;
	}
	if ( $end_page > $max_page ) {
		$start_page = $max_page - $pages_to_show_minus_1;
		$end_page   = $max_page;
	}
	if ( $start_page <= 0 ) {
		$start_page = 1;
	}
	if ( ( $offset + $perpage ) > $numposts ) {
		$max_on_page = $numposts;
	} else {
		$max_on_page = ( $offset + $perpage );
	}
	if ( ( $offset + 1 ) > ( $numposts ) ) {
		$display_on_page = $numposts;
	} else {
		$display_on_page = ( $offset + 1 );
	}

	// Get Sorting Group
	$group_sql = '';
	if ( $file_sort['group'] === 1 ) {
		$group_sql = 'file_category ASC,';
	}
	// Get Files
	$output = '';
	// $file_sort['by'] and ['order'] are allow listed above and $group_sql is a
	// fixed literal, so the values still to bind are the search terms and the
	// LIMIT bounds - all in one prepare() call.
	$files = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM $wpdb->downloads WHERE 1=1 $category_sql $search_sql AND file_permission != -2 ORDER BY $group_sql {$file_sort['by']} {$file_sort['order']} LIMIT %d, %d",
			array_merge( $search_args, array( $offset, $file_sort['perpage'] ) )
		)
	);
	if ( $files ) {
		// Get Download Page Header
		$template_download_header = stripslashes( get_option( 'download_template_header' ) );
		if ( (int) get_option( 'download_nice_permalink' ) === 0 && preg_match( '/[\?\&]page_id=(\d+)/i', get_option( 'download_page_url' ), $matches ) ) {
			$template_download_header = preg_replace( '/(<form[^>]+>)/i', '$1<input type="hidden" name="page_id" value="' . $matches[1] . '" />', $template_download_header );
		}
		$template_download_header = str_replace( '%TOTAL_FILES_COUNT%', number_format_i18n( $total_stats['files'] ), $template_download_header );
		$template_download_header = str_replace( '%TOTAL_HITS%', number_format_i18n( $total_stats['hits'] ), $template_download_header );
		$template_download_header = str_replace( '%TOTAL_SIZE%', format_filesize( $total_stats['size'] ), $template_download_header );
		$template_download_header = str_replace( '%TOTAL_SIZE_DEC%', format_filesize_dec( $total_stats['size'] ), $template_download_header );
		$template_download_header = str_replace( '%RECORD_START%', number_format_i18n( $display_on_page ), $template_download_header );
		$template_download_header = str_replace( '%RECORD_END%', number_format_i18n( $max_on_page ), $template_download_header );
		$template_download_header = str_replace( '%CATEGORY_ID%', $category, $template_download_header );
		$template_download_header = str_replace( '%FILE_CATEGORY_NAME%', download_category_name( $download_categories, $category ), $template_download_header );
		$template_download_header = str_replace( '%FILE_SEARCH_WORD%', esc_attr( $search ), $template_download_header );
		$template_download_header = str_replace( '%DOWNLOAD_PAGE_URL%', get_option( 'download_page_url' ), $template_download_header );
		$output                   = $template_download_header;
		// Loop Through Files
		$i           = 1;
		$k           = 1;
		$temp_cat_id = -1;
		$need_footer = 0;
		foreach ( $files as $file ) {
			$cat_id = (int) $file->file_category;
			// Print Out Category Footer
			if ( $need_footer && $temp_cat_id !== $cat_id && (int) $file_sort['group'] === 1 ) {
				// Get Download Category Footer
				$template_download_category_footer = stripslashes( get_option( 'download_template_category_footer' ) );
				$template_download_category_footer = str_replace( '%FILE_CATEGORY_NAME%', download_category_name( $download_categories, $cat_id ), $template_download_category_footer );
				$template_download_category_footer = str_replace( '%CATEGORY_ID%', $cat_id, $template_download_category_footer );
				$template_download_category_footer = str_replace( '%CATEGORY_URL%', download_category_url( $cat_id ), $template_download_category_footer );
				$template_download_category_footer = str_replace( '%CATEGORY_FILES_COUNT%', number_format_i18n( $category_stats[ $cat_id ]['files'] ), $template_download_category_footer );
				$template_download_category_footer = str_replace( '%CATEGORY_HITS%', number_format_i18n( $category_stats[ $cat_id ]['hits'] ), $template_download_category_footer );
				$template_download_category_footer = str_replace( '%CATEGORY_SIZE%', format_filesize( $category_stats[ $cat_id ]['size'] ), $template_download_category_footer );
				$template_download_category_footer = str_replace( '%CATEGORY_SIZE_DEC%', format_filesize_dec( $category_stats[ $cat_id ]['size'] ), $template_download_category_footer );
				$output                           .= $template_download_category_footer;
				$need_footer                       = 0;
			}
			// Print Out Category Header
			if ( $temp_cat_id !== $cat_id && (int) $file_sort['group'] === 1 ) {
				// Get Download Category Header
				$template_download_category_header = stripslashes( get_option( 'download_template_category_header' ) );
				$template_download_category_header = str_replace( '%FILE_CATEGORY_NAME%', download_category_name( $download_categories, $cat_id ), $template_download_category_header );
				$template_download_category_header = str_replace( '%CATEGORY_ID%', $cat_id, $template_download_category_header );
				$template_download_category_header = str_replace( '%CATEGORY_URL%', download_category_url( $cat_id ), $template_download_category_header );
				$template_download_category_header = str_replace( '%CATEGORY_FILES_COUNT%', number_format_i18n( $category_stats[ $cat_id ]['files'] ), $template_download_category_header );
				$template_download_category_header = str_replace( '%CATEGORY_HITS%', number_format_i18n( $category_stats[ $cat_id ]['hits'] ), $template_download_category_header );
				$template_download_category_header = str_replace( '%CATEGORY_SIZE%', format_filesize( $category_stats[ $cat_id ]['size'] ), $template_download_category_header );
				$template_download_category_header = str_replace( '%CATEGORY_SIZE_DEC%', format_filesize_dec( $category_stats[ $cat_id ]['size'] ), $template_download_category_header );
				$output                           .= $template_download_category_header;
				$i                                 = 1;
				$need_footer                       = 1;
			}
			// Get Download Listing
			$file_permission           = (int) $file->file_permission;
			$template_download_listing = get_option( 'download_template_listing' );
			if ( $file_permission === -1 || ( $file_permission === 0 && (int) $user_ID > 0 ) || ( $file_permission > 0 && get_wp_user_level() >= $file_permission && (int) $user_ID > 0 ) ) {
				$template_download_listing = stripslashes( $template_download_listing[0] );
			} else {
				$template_download_listing = stripslashes( $template_download_listing[1] );
			}
			$template_download_listing = str_replace( '%FILE_ID%', $file->file_id, $template_download_listing );
			$template_download_listing = str_replace( '%FILE%', stripslashes( $file->file ), $template_download_listing );
			$template_download_listing = str_replace( '%FILE_NAME%', download_search_highlight( $search, stripslashes( $file->file_name ) ), $template_download_listing );
			$template_download_listing = str_replace( '%FILE_EXT%', download_search_highlight( $search, file_extension( stripslashes( $file->file ) ) ), $template_download_listing );
			$template_download_listing = str_replace( '%FILE_ICON%', file_extension_image( stripslashes( $file->file ), $file_extensions_images ), $template_download_listing );
			$template_download_listing = str_replace( '%FILE_DESCRIPTION%', download_search_highlight( $search, stripslashes( $file->file_des ) ), $template_download_listing );
			$template_download_listing = str_replace( '%FILE_SIZE%', format_filesize( $file->file_size ), $template_download_listing );
			$template_download_listing = str_replace( '%FILE_SIZE_DEC%', format_filesize_dec( $file->file_size ), $template_download_listing );
			$template_download_listing = str_replace( '%FILE_CATEGORY_ID%', $cat_id, $template_download_listing );
			$template_download_listing = str_replace( '%FILE_CATEGORY_NAME%', download_category_name( $download_categories, $cat_id ), $template_download_listing );
			$template_download_listing = str_replace( '%FILE_DATE%', mysql2date( get_option( 'date_format' ), gmdate( 'Y-m-d H:i:s', $file->file_date ) ), $template_download_listing );
			$template_download_listing = str_replace( '%FILE_TIME%', mysql2date( get_option( 'time_format' ), gmdate( 'Y-m-d H:i:s', $file->file_date ) ), $template_download_listing );
			$template_download_listing = str_replace( '%FILE_UPDATED_DATE%', mysql2date( get_option( 'date_format' ), gmdate( 'Y-m-d H:i:s', $file->file_updated_date ) ), $template_download_listing );
			$template_download_listing = str_replace( '%FILE_UPDATED_TIME%', mysql2date( get_option( 'time_format' ), gmdate( 'Y-m-d H:i:s', $file->file_updated_date ) ), $template_download_listing );
			$template_download_listing = str_replace( '%FILE_HITS%', number_format_i18n( $file->file_hits ), $template_download_listing );
			$template_download_listing = str_replace( '%FILE_DOWNLOAD_URL%', download_file_url( $file->file_id, $file->file ), $template_download_listing );
			$output                   .= $template_download_listing;
			// Assign Cat ID To Temp Cat ID
			$temp_cat_id = $cat_id;
			// Count Files
			++$i;
			++$k;
		}
		// Print Out Category Footer
		if ( $need_footer ) {
			// Get Download Category Footer
			$template_download_category_footer = stripslashes( get_option( 'download_template_category_footer' ) );
			$template_download_category_footer = str_replace( '%FILE_CATEGORY_NAME%', download_category_name( $download_categories, $cat_id ), $template_download_category_footer );
			$template_download_category_footer = str_replace( '%CATEGORY_ID%', $cat_id, $template_download_category_footer );
			$template_download_category_footer = str_replace( '%CATEGORY_URL%', download_category_url( $cat_id ), $template_download_category_footer );
			$template_download_category_footer = str_replace( '%CATEGORY_FILES_COUNT%', number_format_i18n( $category_stats[ $cat_id ]['files'] ), $template_download_category_footer );
			$template_download_category_footer = str_replace( '%CATEGORY_HITS%', number_format_i18n( $category_stats[ $cat_id ]['hits'] ), $template_download_category_footer );
			$template_download_category_footer = str_replace( '%CATEGORY_SIZE%', format_filesize( $category_stats[ $cat_id ]['size'] ), $template_download_category_footer );
			$template_download_category_footer = str_replace( '%CATEGORY_SIZE_DEC%', format_filesize_dec( $category_stats[ $cat_id ]['size'] ), $template_download_category_footer );
			$output                           .= $template_download_category_footer;
			$need_footer                       = 0;
		}
		// Get Download Page Footer
		$template_download_footer = stripslashes( get_option( 'download_template_footer' ) );
		if ( (int) get_option( 'download_nice_permalink' ) === 0 && preg_match( '/[\?\&]page_id=(\d+)/i', get_option( 'download_page_url' ), $matches ) ) {
			$template_download_footer = preg_replace( '/(<form[^>]+>)/i', '$1<input type="hidden" name="page_id" value="' . $matches[1] . '" />', $template_download_footer );
		}
		$template_download_footer = str_replace( '%TOTAL_FILES_COUNT%', number_format_i18n( $total_stats['files'] ), $template_download_footer );
		$template_download_footer = str_replace( '%TOTAL_HITS%', number_format_i18n( $total_stats['hits'] ), $template_download_footer );
		$template_download_footer = str_replace( '%TOTAL_SIZE%', format_filesize( $total_stats['size'] ), $template_download_footer );
		$template_download_footer = str_replace( '%TOTAL_SIZE_DEC%', format_filesize_dec( $total_stats['size'] ), $template_download_footer );
		$template_download_footer = str_replace( '%CATEGORY_ID%', $category, $template_download_footer );
		$template_download_footer = str_replace( '%FILE_CATEGORY_NAME%', download_category_name( $download_categories, $category ), $template_download_footer );
		$template_download_footer = str_replace( '%FILE_SEARCH_WORD%', esc_attr( $search ), $template_download_footer );
		$template_download_footer = str_replace( '%DOWNLOAD_PAGE_URL%', get_option( 'download_page_url' ), $template_download_footer );
		$output                  .= $template_download_footer;
	} else {
		$template_download_none = stripslashes( get_option( 'download_template_none' ) );
		$output                .= $template_download_none;
	}
	// Download Paging
	if ( $max_page > 1 ) {
		$output .= stripslashes( get_option( 'download_template_pagingheader' ) );
		if ( function_exists( 'wp_pagenavi' ) ) {
			$output .= '<div class="wp-pagenavi">' . "\n";
		} else {
			$output .= '<div class="wp-downloadmanager-paging">' . "\n";
		}
		$output .= '<span class="pages">&#8201;' . sprintf( __( 'Page %1$s of %2$s', 'wp-downloadmanager' ), number_format_i18n( $page ), number_format_i18n( $max_page ) ) . '&#8201;</span>';
		if ( $start_page >= 2 && $pages_to_show < $max_page ) {
			$output .= '<a href="' . download_page_link( 1 ) . '" title="' . __( '&laquo; First', 'wp-downloadmanager' ) . '">&#8201;' . __( '&laquo; First', 'wp-downloadmanager' ) . '&#8201;</a>';
			$output .= '<span class="extend">...</span>';
		}
		if ( $page > 1 ) {
			$output .= '<a href="' . download_page_link( ( $page - 1 ) ) . '" title="' . __( '&laquo;', 'wp-downloadmanager' ) . '">&#8201;' . __( '&laquo;', 'wp-downloadmanager' ) . '&#8201;</a>';
		}
		for ( $i = $start_page; $i <= $end_page; $i++ ) {
			if ( $i === $page ) {
				$output .= '<span class="current">&#8201;' . number_format_i18n( $i ) . '&#8201;</span>';
			} else {
				$output .= '<a href="' . download_page_link( $i ) . '" title="' . number_format_i18n( $i ) . '">&#8201;' . number_format_i18n( $i ) . '&#8201;</a>';
			}
		}
		if ( empty( $page ) || ( $page + 1 ) <= $max_page ) {
			$output .= '<a href="' . download_page_link( ( $page + 1 ) ) . '" title="' . __( '&raquo;', 'wp-downloadmanager' ) . '">&#8201;' . __( '&raquo;', 'wp-downloadmanager' ) . '&#8201;</a>';
		}
		if ( $end_page < $max_page ) {
			$output .= '<span class="extend">...</span>';
			$output .= '<a href="' . download_page_link( $max_page ) . '" title="' . __( 'Last &raquo;', 'wp-downloadmanager' ) . '">&#8201;' . __( 'Last &raquo;', 'wp-downloadmanager' ) . '&#8201;</a>';
		}
		$output .= '</div>';
		$output .= stripslashes( get_option( 'download_template_pagingfooter' ) );
	}
	return apply_filters( 'downloads_page', $output );
}


// Function: List Out All Files In Downloads Directory
function list_downloads_files( $dir, $orginal_dir ) {
	global $download_files, $download_files_subfolder;
	if ( is_dir( $dir ) ) {
		if ( $dh = opendir( $dir ) ) {
			while ( ( $file = readdir( $dh ) ) !== false ) {
				if ( $file != '.' && $file != '..' && $file != '.htaccess' ) {
					if ( is_dir( $dir . '/' . $file ) ) {
						list_downloads_files( $dir . '/' . $file, $orginal_dir );
					} else {
						$folder_file = str_replace( $orginal_dir, '', $dir . '/' . $file );
						$sub_dir     = explode( '/', $folder_file );
						if ( sizeof( $sub_dir ) > 2 ) {
							$download_files_subfolder[] = $folder_file;
						} else {
							$download_files[] = $folder_file;
						}
					}
				}
			}
			closedir( $dh );
		}
	}
}


// Function: List Out All Files In Downloads Directory
function list_downloads_folders( $dir, $orginal_dir ) {
	global $download_folders;
	if ( is_dir( $dir ) ) {
		if ( $dh = opendir( $dir ) ) {
			while ( ( $file = readdir( $dh ) ) !== false ) {
				if ( $file != '.' && $file != '..' ) {
					if ( is_dir( $dir . '/' . $file ) ) {
						$folder             = str_replace( $orginal_dir, '', $dir . '/' . $file );
						$download_folders[] = $folder;
						list_downloads_folders( $dir . '/' . $file, $orginal_dir );
					}
				}
			}
			closedir( $dh );
		}
	}
}


// Function: Print Listing Of Files In Alphabetical Order
function print_list_files( $dir, $orginal_dir, $selected = '' ) {
	global $download_files, $download_files_subfolder;
	list_downloads_files( $dir, $orginal_dir );
	if ( $download_files ) {
		natcasesort( $download_files );
	}
	if ( $download_files_subfolder ) {
		natcasesort( $download_files_subfolder );
	}
	if ( $download_files ) {
		foreach ( $download_files as $download_file ) {
			if ( $download_file == $selected ) {
				echo '<option value="' . esc_attr( $download_file ) . '" selected="selected">' . esc_html( $download_file ) . '</option>' . "\n";
			} else {
				echo '<option value="' . esc_attr( $download_file ) . '">' . esc_html( $download_file ) . '</option>' . "\n";
			}
		}
	}
	if ( $download_files_subfolder ) {
		foreach ( $download_files_subfolder as $download_file_subfolder ) {
			if ( $download_file_subfolder == $selected ) {
				echo '<option value="' . esc_attr( $download_file_subfolder ) . '" selected="selected">' . esc_html( $download_file_subfolder ) . '</option>' . "\n";
			} else {
				echo '<option value="' . esc_attr( $download_file_subfolder ) . '">' . esc_html( $download_file_subfolder ) . '</option>' . "\n";
			}
		}
	}
}


// Function: Print Listing Of Folders In Alphabetical Order
function print_list_folders( $dir, $orginal_dir ) {
	global $download_folders;
	list_downloads_folders( $dir, $orginal_dir );
	if ( $download_folders ) {
		natcasesort( $download_folders );
		echo '<option value="/">/</option>' . "\n";
		foreach ( $download_folders as $download_folder ) {
			echo '<option value="' . esc_attr( $download_folder ) . '">' . esc_html( $download_folder ) . '</option>' . "\n";
		}
	}
}


// Function: Constrain An Upload Subfolder To The Downloads Directory
// The subfolder comes from a <select> the admin screen builds, but nothing stopped
// a hand-crafted POST from sending '../../..' and dropping the upload anywhere the
// web server could write. Falls back to the root of the downloads directory.
function download_safe_subfolder( $file_path, $subfolder ) {
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


// Function: Rename File To Ensure (Credits: imvain2)
function download_rename_file( $file_path, $file ) {
	$rename   = false;
	$file_old = $file;
	$file     = str_replace( ' ', '_', $file );
	$file     = preg_replace( '/[^A-Za-z0-9\-._\/]/', '', $file );
	if ( $file !== $file_old ) {
		$rename = rename( $file_path . $file_old, $file_path . $file );
	}
	if ( $rename ) {
		return $file;
	}

	return $file_old;
}


// Function: Editable Timestamp
function file_timestamp( $file_timestamp ) {
	global $month;
	$day = (int) gmdate( 'j', $file_timestamp );
	echo '<select id="file_timestamp_day" name="file_timestamp_day" size="1">' . "\n";
	for ( $i = 1; $i <= 31; $i++ ) {
		if ( $day === $i ) {
			echo "<option value=\"$i\" selected=\"selected\">$i</option>\n";
		} else {
			echo "<option value=\"$i\">$i</option>\n";
		}
	}
	echo '</select>&nbsp;&nbsp;' . "\n";
	$month2 = (int) gmdate( 'n', $file_timestamp );
	echo '<select id="file_timestamp_month" name="file_timestamp_month" size="1">' . "\n";
	for ( $i = 1; $i <= 12; $i++ ) {
		if ( $i < 10 ) {
			$ii = '0' . $i;
		} else {
			$ii = $i;
		}
		if ( $month2 === $i ) {
			echo "<option value=\"$i\" selected=\"selected\">$month[$ii]</option>\n";
		} else {
			echo "<option value=\"$i\">$month[$ii]</option>\n";
		}
	}
	echo '</select>&nbsp;&nbsp;' . "\n";
	$year         = (int) gmdate( 'Y', $file_timestamp );
	$current_year = (int) gmdate( 'Y' );
	echo '<select id="file_timestamp_year" name="file_timestamp_year" size="1">' . "\n";
	for ( $i = 2000; $i <= $current_year; $i++ ) {
		if ( $year === $i ) {
			echo "<option value=\"$i\" selected=\"selected\">$i</option>\n";
		} else {
			echo "<option value=\"$i\">$i</option>\n";
		}
	}
	echo '</select>&nbsp;@' . "\n";
	echo '<span dir="ltr">' . "\n";
	$hour = (int) gmdate( 'H', $file_timestamp );
	echo '<select id="file_timestamp_hour" name="file_timestamp_hour" size="1">' . "\n";
	for ( $i = 0; $i < 24; $i++ ) {
		if ( $hour === $i ) {
			echo "<option value=\"$i\" selected=\"selected\">$i</option>\n";
		} else {
			echo "<option value=\"$i\">$i</option>\n";
		}
	}
	echo '</select>&nbsp;:' . "\n";
	$minute = (int) gmdate( 'i', $file_timestamp );
	echo '<select id="file_timestamp_minute" name="file_timestamp_minute" size="1">' . "\n";
	for ( $i = 0; $i < 60; $i++ ) {
		if ( $minute === $i ) {
			echo "<option value=\"$i\" selected=\"selected\">$i</option>\n";
		} else {
			echo "<option value=\"$i\">$i</option>\n";
		}
	}

	echo '</select>&nbsp;:' . "\n";
	$second = (int) gmdate( 's', $file_timestamp );
	echo '<select id="file_timestamp_second" name="file_timestamp_second" size="1">' . "\n";
	for ( $i = 0; $i <= 60; $i++ ) {
		if ( $second === $i ) {
			echo "<option value=\"$i\" selected=\"selected\">$i</option>\n";
		} else {
			echo "<option value=\"$i\">$i</option>\n";
		}
	}
	echo '</select>' . "\n";
	echo '</span>' . "\n";
}

function get_wp_user_level() {
	// Everyone
	$current_user = wp_get_current_user();
	if ( empty( $current_user ) ) {
		return -1;
	}
	// At Least Administrator Role
	if ( current_user_can( 'activate_plugins' ) ) {
		return 10;
	}
	// At Least Editor Role
	if ( current_user_can( 'delete_others_posts' ) ) {
		return 7;
	}
	// At Least Author Role
	if ( current_user_can( 'publish_posts' ) ) {
		return 2;
	}
	// At Least Contributor Role
	if ( current_user_can( 'edit_posts' ) ) {
		return 1;
	}
	// Registered Users Only
	if ( current_user_can( 'read' ) ) {
		return 0;
	}

	// In case
	return -1;
}


// Function: File Permission
function file_permission( $file_permission ) {
	$file_permission_name = '';
	switch ( (int) $file_permission ) {
		case -2:
			$file_permission_name = __( 'Hidden', 'wp-downloadmanager' );
			break;
		case -1:
			$file_permission_name = __( 'Everyone', 'wp-downloadmanager' );
			break;
		case 0:
			$file_permission_name = __( 'Registered Users Only', 'wp-downloadmanager' );
			break;
		case 1:
			$file_permission_name = __( 'At Least Contributor Role', 'wp-downloadmanager' );
			break;
		case 2:
			$file_permission_name = __( 'At Least Author Role', 'wp-downloadmanager' );
			break;
		case 7:
			$file_permission_name = __( 'At Least Editor Role', 'wp-downloadmanager' );
			break;
		case 10:
			$file_permission_name = __( 'At Least Administrator Role', 'wp-downloadmanager' );
			break;
	}
	return $file_permission_name;
}


// Function: Get Total Download Files
function get_download_files( $display = true ) {
	global $wpdb;
	$totalfiles = $wpdb->get_var( "SELECT COUNT(file_id) FROM $wpdb->downloads" );
	if ( $display ) {
		echo number_format_i18n( $totalfiles );
	} else {
		return number_format_i18n( $totalfiles );
	}
}


// Function Get Total Download Size
function get_download_size( $display = true ) {
	global $wpdb;
	$totalsize = $wpdb->get_var( "SELECT SUM(file_size) FROM $wpdb->downloads" );
	if ( $display ) {
		echo format_filesize( $totalsize );
	} else {
		return format_filesize( $totalsize );
	}
}


// Function: Get Total Download Hits
function get_download_hits( $display = true ) {
	global $wpdb;
	$totalhits = $wpdb->get_var( "SELECT SUM(file_hits) FROM $wpdb->downloads" );
	if ( $display ) {
		echo number_format_i18n( $totalhits );
	} else {
		return number_format_i18n( $totalhits );
	}
}


// Function: Download Embedded
function download_embedded( $condition = '', $display = 'both', $sort_by = 'file_id', $sort_order = 'asc', $stream_limit = 0 ) {
	global $wpdb, $user_ID;
	$valid_sort_by    = array( 'file_id', 'file', 'file_name', 'file_size', 'file_date', 'file_hits' );
	$valid_sort_order = array( 'asc', 'desc' );
	if ( ! in_array( $sort_by, $valid_sort_by, true ) ) {
		$sort_by = 'file_id';
	}
	if ( ! in_array( $sort_order, $valid_sort_order, true ) ) {
		$sort_order = 'asc';
	}
	if ( $sort_by === 'file_date' ) {
		$sort_by = 'FROM_UNIXTIME(file_date)';
	}
	$stream_limit = max( (int) $stream_limit, 0 );
	$output       = '';
	if ( $condition !== '' ) {
		$condition .= ' AND ';
	}
	$query_string = "SELECT * FROM $wpdb->downloads WHERE $condition file_permission != -2 ORDER BY {$sort_by} {$sort_order}";
	if ( ! is_single() && $stream_limit != 0 ) {
		// We don't need to retrieve ALL matching files, we just need to know if there are more files than $stream_limit.
		// This can cut down on memory usage if there are many many matching files but the $stream_limit is relatively small.
		$query_limit   = $stream_limit + 1;
		$query_string .= " LIMIT {$query_limit}";
	}
	$files = $wpdb->get_results( $query_string );
	if ( $files ) {
		$current_user                    = wp_get_current_user();
		$file_extensions_images          = file_extension_images();
		$download_categories             = get_option( 'download_categories' );
		$template_download_embedded_temp = get_option( 'download_template_embedded' );
		if ( is_single() || $stream_limit === 0 ) {
			$stream_limit = count( $files );
		} else {
			$stream_limit = min( $stream_limit, count( $files ) );
		}
		for ( $i = 0; $i < $stream_limit; $i++ ) {
			$file                       = $files[ $i ];
			$file_permission            = (int) $file->file_permission;
			$template_download_embedded = $template_download_embedded_temp;
			if ( $file_permission === -1 || ( $file_permission === 0 && (int) $user_ID > 0 ) || ( $file_permission > 0 && get_wp_user_level() >= $file_permission && (int) $user_ID > 0 ) ) {
				$template_download_embedded = stripslashes( $template_download_embedded[0] );
			} else {
				$template_download_embedded = stripslashes( $template_download_embedded[1] );
			}
			$template_download_embedded = str_replace( '%FILE_ID%', $file->file_id, $template_download_embedded );
			$template_download_embedded = str_replace( '%FILE%', stripslashes( $file->file ), $template_download_embedded );
			$template_download_embedded = str_replace( '%FILE_NAME%', stripslashes( $file->file_name ), $template_download_embedded );
			$template_download_embedded = str_replace( '%FILE_EXT%', file_extension( stripslashes( $file->file ) ), $template_download_embedded );
			$template_download_embedded = str_replace( '%FILE_ICON%', file_extension_image( stripslashes( $file->file ), $file_extensions_images ), $template_download_embedded );
			if ( $display === 'both' ) {
				$template_download_embedded = str_replace( '%FILE_DESCRIPTION%', stripslashes( $file->file_des ), $template_download_embedded );
			} else {
				$template_download_embedded = str_replace( '%FILE_DESCRIPTION%', '', $template_download_embedded );
			}
			$template_download_embedded = str_replace( '%FILE_SIZE%', format_filesize( $file->file_size ), $template_download_embedded );
			$template_download_embedded = str_replace( '%FILE_SIZE_DEC%', format_filesize_dec( $file->file_size ), $template_download_embedded );
			$template_download_embedded = str_replace( '%FILE_CATEGORY_ID%', (int) $file->file_category, $template_download_embedded );
			$template_download_embedded = str_replace( '%FILE_CATEGORY_NAME%', download_category_name( $download_categories, $file->file_category ), $template_download_embedded );
			$template_download_embedded = str_replace( '%FILE_DATE%', mysql2date( get_option( 'date_format' ), gmdate( 'Y-m-d H:i:s', (int) $file->file_date ) ), $template_download_embedded );
			$template_download_embedded = str_replace( '%FILE_TIME%', mysql2date( get_option( 'time_format' ), gmdate( 'Y-m-d H:i:s', (int) $file->file_date ) ), $template_download_embedded );
			$template_download_embedded = str_replace( '%FILE_UPDATED_DATE%', mysql2date( get_option( 'date_format' ), gmdate( 'Y-m-d H:i:s', (int) $file->file_updated_date ) ), $template_download_embedded );
			$template_download_embedded = str_replace( '%FILE_UPDATED_TIME%', mysql2date( get_option( 'time_format' ), gmdate( 'Y-m-d H:i:s', (int) $file->file_updated_date ) ), $template_download_embedded );
			$template_download_embedded = str_replace( '%FILE_HITS%', number_format_i18n( $file->file_hits ), $template_download_embedded );
			$template_download_embedded = str_replace( '%FILE_DOWNLOAD_URL%', download_file_url( $file->file_id, $file->file ), $template_download_embedded );
			$output                    .= $template_download_embedded;
		}
		if ( ! is_single() && $stream_limit != 0 && $stream_limit < count( $files ) ) {
			$output .= '<p><a href="' . get_permalink() . '">' . __( 'More …', 'wp-downloadmanager' ) . '</a></p>';
		}
		return apply_filters( 'download_embedded', $output );
	}

	// Used to fall off the end and return null, which is a TypeError waiting to
	// happen for any caller that concatenates the result.
	return apply_filters( 'download_embedded', '' );
}


// Function: Get Most Downloaded Files
if ( ! function_exists( 'get_most_downloaded' ) ) {
	function get_most_downloaded( $limit = 10, $chars = 0, $display = true ) {
		global $wpdb, $user_ID;
		$output = '';
		$files  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->downloads WHERE file_permission != -2 ORDER BY file_hits DESC LIMIT %d", (int) $limit ) );
		if ( $files ) {
			$current_user                = wp_get_current_user();
			$file_extensions_images      = file_extension_images();
			$download_categories         = get_option( 'download_categories' );
			$template_download_most_temp = get_option( 'download_template_most' );
			foreach ( $files as $file ) {
				$file_permission        = (int) $file->file_permission;
				$template_download_most = $template_download_most_temp;
				if ( $file_permission === -1 || ( $file_permission === 0 && (int) $user_ID > 0 ) || ( $file_permission > 0 && get_wp_user_level() >= $file_permission && (int) $user_ID > 0 ) ) {
					$template_download_most = stripslashes( $template_download_most[0] );
				} else {
					$template_download_most = stripslashes( $template_download_most[1] );
				}
				if ( $chars > 0 ) {
					$file_name = snippet_text( stripslashes( $file->file_name ), $chars );
				} else {
					$file_name = stripslashes( $file->file_name );
				}
				$template_download_most = str_replace( '%FILE_ID%', $file->file_id, $template_download_most );
				$template_download_most = str_replace( '%FILE%', stripslashes( $file->file ), $template_download_most );
				$template_download_most = str_replace( '%FILE_NAME%', $file_name, $template_download_most );
				$template_download_most = str_replace( '%FILE_EXT%', file_extension( stripslashes( $file->file ) ), $template_download_most );
				$template_download_most = str_replace( '%FILE_ICON%', file_extension_image( stripslashes( $file->file ), $file_extensions_images ), $template_download_most );
				$template_download_most = str_replace( '%FILE_DESCRIPTION%', stripslashes( $file->file_des ), $template_download_most );
				$template_download_most = str_replace( '%FILE_SIZE%', format_filesize( $file->file_size ), $template_download_most );
				$template_download_most = str_replace( '%FILE_SIZE_DEC%', format_filesize_dec( $file->file_size ), $template_download_most );
				$template_download_most = str_replace( '%FILE_CATEGORY_ID%', (int) $file->file_category, $template_download_most );
				$template_download_most = str_replace( '%FILE_CATEGORY_NAME%', download_category_name( $download_categories, $file->file_category ), $template_download_most );
				$template_download_most = str_replace( '%FILE_DATE%', mysql2date( get_option( 'date_format' ), gmdate( 'Y-m-d H:i:s', $file->file_date ) ), $template_download_most );
				$template_download_most = str_replace( '%FILE_TIME%', mysql2date( get_option( 'time_format' ), gmdate( 'Y-m-d H:i:s', $file->file_date ) ), $template_download_most );
				$template_download_most = str_replace( '%FILE_UPDATED_DATE%', mysql2date( get_option( 'date_format' ), gmdate( 'Y-m-d H:i:s', $file->file_updated_date ) ), $template_download_most );
				$template_download_most = str_replace( '%FILE_UPDATED_TIME%', mysql2date( get_option( 'time_format' ), gmdate( 'Y-m-d H:i:s', $file->file_updated_date ) ), $template_download_most );
				$template_download_most = str_replace( '%FILE_HITS%', number_format_i18n( $file->file_hits ), $template_download_most );
				$template_download_most = str_replace( '%FILE_DOWNLOAD_URL%', download_file_url( $file->file_id, $file->file ), $template_download_most );
				$output                .= $template_download_most;
			}
		} else {
			$output = '<li>' . __( 'N/A', 'wp-downloadmanager' ) . '</li>' . "\n";
		}
		if ( $display ) {
			echo $output;
		} else {
			return $output;
		}
	}
}


// Function: Get Newest Downloads
if ( ! function_exists( 'get_recent_downloads' ) ) {
	function get_recent_downloads( $limit = 10, $chars = 0, $display = true ) {
		global $wpdb, $user_ID;
		$output = '';
		$files  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->downloads WHERE file_permission != -2 ORDER BY FROM_UNIXTIME(file_date) DESC LIMIT %d", (int) $limit ) );
		if ( $files ) {
			$current_user                = wp_get_current_user();
			$file_extensions_images      = file_extension_images();
			$download_categories         = get_option( 'download_categories' );
			$template_download_most_temp = get_option( 'download_template_most' );
			foreach ( $files as $file ) {
				$file_permission        = (int) $file->file_permission;
				$template_download_most = $template_download_most_temp;
				if ( $file_permission === -1 || ( $file_permission === 0 && (int) $user_ID > 0 ) || ( $file_permission > 0 && get_wp_user_level() >= $file_permission && (int) $user_ID > 0 ) ) {
					$template_download_most = stripslashes( $template_download_most[0] );
				} else {
					$template_download_most = stripslashes( $template_download_most[1] );
				}
				if ( $chars > 0 ) {
					$file_name = snippet_text( stripslashes( $file->file_name ), $chars );
				} else {
					$file_name = stripslashes( $file->file_name );
				}
				$template_download_most = str_replace( '%FILE_ID%', $file->file_id, $template_download_most );
				$template_download_most = str_replace( '%FILE%', stripslashes( $file->file ), $template_download_most );
				$template_download_most = str_replace( '%FILE_NAME%', $file_name, $template_download_most );
				$template_download_most = str_replace( '%FILE_EXT%', file_extension( stripslashes( $file->file ) ), $template_download_most );
				$template_download_most = str_replace( '%FILE_ICON%', file_extension_image( stripslashes( $file->file ), $file_extensions_images ), $template_download_most );
				$template_download_most = str_replace( '%FILE_DESCRIPTION%', stripslashes( $file->file_des ), $template_download_most );
				$template_download_most = str_replace( '%FILE_SIZE%', format_filesize( $file->file_size ), $template_download_most );
				$template_download_most = str_replace( '%FILE_SIZE_DEC%', format_filesize_dec( $file->file_size ), $template_download_most );
				$template_download_most = str_replace( '%FILE_CATEGORY_ID%', (int) $file->file_category, $template_download_most );
				$template_download_most = str_replace( '%FILE_CATEGORY_NAME%', download_category_name( $download_categories, $file->file_category ), $template_download_most );
				$template_download_most = str_replace( '%FILE_DATE%', mysql2date( get_option( 'date_format' ), gmdate( 'Y-m-d H:i:s', $file->file_date ) ), $template_download_most );
				$template_download_most = str_replace( '%FILE_TIME%', mysql2date( get_option( 'time_format' ), gmdate( 'Y-m-d H:i:s', $file->file_date ) ), $template_download_most );
				$template_download_most = str_replace( '%FILE_UPDATED_DATE%', mysql2date( get_option( 'date_format' ), gmdate( 'Y-m-d H:i:s', $file->file_updated_date ) ), $template_download_most );
				$template_download_most = str_replace( '%FILE_UPDATED_TIME%', mysql2date( get_option( 'time_format' ), gmdate( 'Y-m-d H:i:s', $file->file_updated_date ) ), $template_download_most );
				$template_download_most = str_replace( '%FILE_HITS%', number_format_i18n( $file->file_hits ), $template_download_most );
				$template_download_most = str_replace( '%FILE_DOWNLOAD_URL%', download_file_url( $file->file_id, $file->file ), $template_download_most );
				$output                .= $template_download_most;
			}
		} else {
			$output = '<li>' . __( 'N/A', 'wp-downloadmanager' ) . '</li>' . "\n";
		}
		if ( $display ) {
			echo $output;
		} else {
			return $output;
		}
	}
}


// Function: Get Downloads By Category ID
if ( ! function_exists( 'get_downloads_category' ) ) {
	function get_downloads_category( $cat_id = 0, $limit = 10, $chars = 0, $display = true ) {
		global $wpdb, $user_ID;
		// Cast every id. The widget passes explode( ',', $instance['cat_ids'] )
		// straight in, so without this anyone able to edit a widget could rewrite
		// the WHERE clause - including the file_permission guard that hides files.
		if ( is_array( $cat_id ) ) {
			$cat_ids = array_map( 'intval', $cat_id );
			if ( empty( $cat_ids ) ) {
				$cat_ids = array( 0 );
			}
			$category_sql = 'file_category IN (' . implode( ',', $cat_ids ) . ')';
		} else {
			$category_sql = 'file_category = ' . (int) $cat_id;
		}
		$output = '';
		$files  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->downloads WHERE $category_sql AND file_permission != -2 ORDER BY FROM_UNIXTIME(file_date) DESC LIMIT %d", (int) $limit ) );
		if ( $files ) {
			$current_user                = wp_get_current_user();
			$file_extensions_images      = file_extension_images();
			$download_categories         = get_option( 'download_categories' );
			$template_download_most_temp = get_option( 'download_template_most' );
			foreach ( $files as $file ) {
				$file_permission        = (int) $file->file_permission;
				$template_download_most = $template_download_most_temp;
				if ( $file_permission === -1 || ( $file_permission === 0 && (int) $user_ID > 0 ) || ( $file_permission > 0 && get_wp_user_level() >= $file_permission && (int) $user_ID > 0 ) ) {
					$template_download_most = stripslashes( $template_download_most[0] );
				} else {
					$template_download_most = stripslashes( $template_download_most[1] );
				}
				if ( $chars > 0 ) {
					$file_name = snippet_text( stripslashes( $file->file_name ), $chars );
				} else {
					$file_name = stripslashes( $file->file_name );
				}
				$template_download_most = str_replace( '%FILE_ID%', $file->file_id, $template_download_most );
				$template_download_most = str_replace( '%FILE%', stripslashes( $file->file ), $template_download_most );
				$template_download_most = str_replace( '%FILE_NAME%', $file_name, $template_download_most );
				$template_download_most = str_replace( '%FILE_EXT%', file_extension( stripslashes( $file->file ) ), $template_download_most );
				$template_download_most = str_replace( '%FILE_ICON%', file_extension_image( stripslashes( $file->file ), $file_extensions_images ), $template_download_most );
				$template_download_most = str_replace( '%FILE_DESCRIPTION%', stripslashes( $file->file_des ), $template_download_most );
				$template_download_most = str_replace( '%FILE_SIZE%', format_filesize( $file->file_size ), $template_download_most );
				$template_download_most = str_replace( '%FILE_SIZE_DEC%', format_filesize_dec( $file->file_size ), $template_download_most );
				$template_download_most = str_replace( '%FILE_CATEGORY_ID%', (int) $file->file_category, $template_download_most );
				$template_download_most = str_replace( '%FILE_CATEGORY_NAME%', download_category_name( $download_categories, $file->file_category ), $template_download_most );
				$template_download_most = str_replace( '%FILE_DATE%', mysql2date( get_option( 'date_format' ), gmdate( 'Y-m-d H:i:s', $file->file_date ) ), $template_download_most );
				$template_download_most = str_replace( '%FILE_TIME%', mysql2date( get_option( 'time_format' ), gmdate( 'Y-m-d H:i:s', $file->file_date ) ), $template_download_most );
				$template_download_most = str_replace( '%FILE_UPDATED_DATE%', mysql2date( get_option( 'date_format' ), gmdate( 'Y-m-d H:i:s', $file->file_updated_date ) ), $template_download_most );
				$template_download_most = str_replace( '%FILE_UPDATED_TIME%', mysql2date( get_option( 'time_format' ), gmdate( 'Y-m-d H:i:s', $file->file_updated_date ) ), $template_download_most );
				$template_download_most = str_replace( '%FILE_HITS%', number_format_i18n( $file->file_hits ), $template_download_most );
				$template_download_most = str_replace( '%FILE_DOWNLOAD_URL%', download_file_url( $file->file_id, $file->file ), $template_download_most );
				$output                .= $template_download_most;
			}
		} else {
			$output = '<li>' . __( 'N/A', 'wp-downloadmanager' ) . '</li>' . "\n";
		}
		if ( $display ) {
			echo $output;
		} else {
			return $output;
		}
	}
}


// Function: Plug Into WP-Stats
add_action( 'plugins_loaded', 'downloadmanager_wp_stats' );
function downloadmanager_wp_stats() {
	add_filter( 'wp_stats_display_defaults', 'downloadmanager_wp_stats_defaults' );
	add_filter( 'wp_stats_page_admin_plugins', 'downloadmanager_page_admin_general_stats' );
	add_filter( 'wp_stats_page_admin_recent', 'downloadmanager_page_admin_recent_stats' );
	add_filter( 'wp_stats_page_admin_most', 'downloadmanager_page_admin_most_stats' );
	add_filter( 'wp_stats_page_plugins', 'downloadmanager_page_general_stats' );
	add_filter( 'wp_stats_page_recent', 'downloadmanager_page_recent_stats' );
	add_filter( 'wp_stats_page_most', 'downloadmanager_page_most_stats' );
}


// Function: Tell WP-Stats About The Toggles This Plugin Owns
// These three keys were never registered with WP-Stats, so they only existed
// once someone had saved the options screen. Registering them means the panels
// are on by default on a fresh install, as the other companion plugins are.
function downloadmanager_wp_stats_defaults( $defaults ) {
	// WP-Stats' own defaults win, so this only ever adds.
	return array_merge(
		array(
			'downloads'        => 1,
			'recent_downloads' => 1,
			'downloaded_most'  => 1,
		),
		(array) $defaults
	);
}


// Function: Whether A WP-Stats Display Toggle Is On
function downloadmanager_wp_stats_enabled( $key ) {
	if ( function_exists( 'wp_stats_display_enabled' ) ) {
		return wp_stats_display_enabled( $key );
	}

	// WP-Stats before 3.0.0 kept the toggles in their own option row.
	$stats_display = get_option( 'stats_display' );

	return is_array( $stats_display ) && 1 === (int) ( $stats_display[ $key ] ?? 0 );
}


// Function: How Many "Most" Entries WP-Stats Shows
function downloadmanager_wp_stats_limit() {
	if ( function_exists( 'wp_stats_most_limit' ) ) {
		return wp_stats_most_limit();
	}

	return (int) get_option( 'stats_mostlimit' );
}


// Function: One Checkbox On The WP-Stats Options Screen
function downloadmanager_wp_stats_checkbox( $value, $label ) {
	// WP-Stats 3.0.0 owns the field name, which changed when it consolidated its
	// option rows.
	if ( function_exists( 'wp_stats_checkbox' ) ) {
		return wp_stats_checkbox( $value, $label );
	}

	return '<input type="checkbox" name="stats_display[]" id="wpstats_' . esc_attr( $value ) . '" value="' . esc_attr( $value ) . '"' .
		checked( downloadmanager_wp_stats_enabled( $value ), true, false ) .
		' />&nbsp;&nbsp;<label for="wpstats_' . esc_attr( $value ) . '">' . esc_html( $label ) . '</label><br />' . "\n";
}


// Function: Add WP-DownloadManager General Stats To WP-Stats Page Options
function downloadmanager_page_admin_general_stats( $content ) {
	return $content . downloadmanager_wp_stats_checkbox( 'downloads', __( 'WP-DownloadManager', 'wp-downloadmanager' ) );
}


// Function: Add WP-DownloadManager Top Recent Stats To WP-Stats Page Options
function downloadmanager_page_admin_recent_stats( $content ) {
	$stats_mostlimit = downloadmanager_wp_stats_limit();

	/* translators: %s: number of downloads. */
	$label = _n( '%s Most Recent Download', '%s Most Recent Downloads', $stats_mostlimit, 'wp-downloadmanager' );

	return $content . downloadmanager_wp_stats_checkbox( 'recent_downloads', sprintf( $label, number_format_i18n( $stats_mostlimit ) ) );
}


// Function: Add WP-DownloadManager Top Most/Highest Stats To WP-Stats Page Options
function downloadmanager_page_admin_most_stats( $content ) {
	$stats_mostlimit = downloadmanager_wp_stats_limit();

	/* translators: %s: number of files. */
	$label = _n( '%s Most Downloaded File', '%s Most Downloaded Files', $stats_mostlimit, 'wp-downloadmanager' );

	return $content . downloadmanager_wp_stats_checkbox( 'downloaded_most', sprintf( $label, number_format_i18n( $stats_mostlimit ) ) );
}


// Function: Add WP-DownloadManager General Stats To WP-Stats Page
function downloadmanager_page_general_stats( $content ) {
	global $wpdb;
	if ( downloadmanager_wp_stats_enabled( 'downloads' ) ) {
		$download_stats = $wpdb->get_row( "SELECT COUNT(file_id) as total_files, SUM(file_size) total_size, SUM(file_hits) as total_hits FROM $wpdb->downloads" );
		$content       .= '<p><strong>' . __( 'WP-DownloadManager', 'wp-downloadmanager' ) . '</strong></p>' . "\n";
		$content       .= '<ul>' . "\n";
		$content       .= '<li>' . sprintf( _n( '<strong>%s</strong> file was added.', '<strong>%s</strong> files were added.', $download_stats->total_files, 'wp-downloadmanager' ), number_format_i18n( $download_stats->total_files ) ) . '</li>' . "\n";
		$content       .= '<li>' . sprintf( _n( '<strong>%s</strong> worth of files.', '<strong>%s</strong> worth of files.', $download_stats->total_size, 'wp-downloadmanager' ), format_filesize( $download_stats->total_size ) ) . '</li>' . "\n";
		$content       .= '<li>' . sprintf( _n( '<strong>%s</strong> hit was generated.', '<strong>%s</strong> hits were generated.', $download_stats->total_hits, 'wp-downloadmanager' ), number_format_i18n( $download_stats->total_hits ) ) . '</li>' . "\n";
		$content       .= '</ul>' . "\n";
	}
	return $content;
}


// Function: Add WP-DownloadManager Top Recent Stats To WP-Stats Page
function downloadmanager_page_recent_stats( $content ) {
	$stats_mostlimit = downloadmanager_wp_stats_limit();
	if ( downloadmanager_wp_stats_enabled( 'recent_downloads' ) ) {
		$content .= '<p><strong>' . sprintf( _n( '%s Most Recent Download', '%s Most Recent Downloads', $stats_mostlimit, 'wp-downloadmanager' ), number_format_i18n( $stats_mostlimit ) ) . '</strong></p>' . "\n";
		$content .= '<ul>' . "\n";
		$content .= get_recent_downloads( $stats_mostlimit, 0, false );
		$content .= '</ul>' . "\n";
	}
	return $content;
}


// Function: Add WP-DownloadManager Top Most/Highest Stats To WP-Stats Page
function downloadmanager_page_most_stats( $content ) {
	$stats_mostlimit = downloadmanager_wp_stats_limit();
	if ( downloadmanager_wp_stats_enabled( 'downloaded_most' ) ) {
		$content .= '<p><strong>' . sprintf( _n( '%s Most Downloaded File', '%s Most Downloaded Files', $stats_mostlimit, 'wp-downloadmanager' ), number_format_i18n( $stats_mostlimit ) ) . '</strong></p>' . "\n";
		$content .= '<ul>' . "\n";
		$content .= get_most_downloaded( $stats_mostlimit, 0, false );
		$content .= '</ul>' . "\n";
	}
	return $content;
}


// Function: Validate File Remote
function is_file_remote_valid( $file ) {
	$file_parsed = parse_url( $file );
	if ( ! is_array( $file_parsed ) ) {
		return false;
	}

	if ( empty( $file_parsed['scheme'] ) ) {
		return false;
	}
	$schemes = apply_filters( 'wp_downloadmanager_schemes', array( 'http', 'https', 'ftp' ) );
	if ( ! in_array( $file_parsed['scheme'], $schemes, true ) ) {
		return false;
	}

	$ports = apply_filters( 'wp_downloadmanager_ports', array( 80, 443, 21 ) );
	if ( ! empty( $file_parsed['port'] ) && ! in_array( $file_parsed['port'], $ports, true ) ) {
		return false;
	}

	return true;
}

if ( ! function_exists( 'removeslashes' ) ) {
	function removeslashes( $string ) {
		$string = implode( '', explode( '\\', $string ) );
		return stripslashes( trim( $string ) );
	}
}

// Class: WP-DownloadManager Widget
class WP_Widget_DownloadManager extends WP_Widget {
	// Constructor
	public function __construct() {
		$widget_ops = array( 'description' => __( 'WP-DownloadManager downloads statistics', 'wp-downloadmanager' ) );
		parent::__construct( 'downloads', __( 'Downloads', 'wp-downloadmanager' ), $widget_ops );
	}

	// Display Widget
	public function widget( $args, $instance ) {
		$instance = wp_parse_args( (array) $instance, self::defaults() );
		$title    = apply_filters( 'widget_title', esc_attr( $instance['title'] ) );
		$type     = esc_attr( $instance['type'] );
		$limit    = (int) $instance['limit'];
		$chars    = (int) $instance['chars'];
		$cat_ids  = explode( ',', esc_attr( $instance['cat_ids'] ) );
		$link     = (int) $instance['link'];
		echo $args['before_widget'] . $args['before_title'] . $title . $args['after_title'];
		echo '<ul>' . "\n";
		switch ( $type ) {
			case 'downloads_category':
				get_downloads_category( $cat_ids, $limit, $chars );
				break;
			case 'recent_downloads':
				get_recent_downloads( $limit, $chars );
				break;
			case 'most_downloaded':
				get_most_downloaded( $limit, $chars );
				break;
		}
		echo '</ul>' . "\n";
		if ( $link ) {
			$download_template_download_page_link = stripslashes( get_option( 'download_template_download_page_link' ) );
			$download_template_download_page_link = str_replace( '%DOWNLOAD_PAGE_URL%', get_option( 'download_page_url' ), $download_template_download_page_link );
			echo $download_template_download_page_link;
		}
		echo $args['after_widget'];
	}

	// Default Instance Values
	public static function defaults() {
		return array(
			'title'   => __( 'Downloads', 'wp-downloadmanager' ),
			'type'    => 'most_downloaded',
			'limit'   => 10,
			'chars'   => 200,
			'cat_ids' => '0',
			'link'    => 1,
		);
	}

	// When Widget Control Form Is Posted
	public function update( $new_instance, $old_instance ) {
		// The old "did the hidden submit marker come back?" guard returned false
		// here, which discarded every edit made in the block widget editor and the
		// customizer - neither of them posts that field.
		$new_instance        = wp_parse_args( (array) $new_instance, (array) $old_instance );
		$instance            = (array) $old_instance;
		$instance['title']   = strip_tags( $new_instance['title'] );
		$instance['type']    = strip_tags( $new_instance['type'] );
		$instance['limit']   = (int) $new_instance['limit'];
		$instance['chars']   = (int) $new_instance['chars'];
		$instance['cat_ids'] = strip_tags( $new_instance['cat_ids'] );
		$instance['link']    = (int) $new_instance['link'];
		unset( $instance['submit'], $instance['mode'] );
		return $instance;
	}

	// DIsplay Widget Control Form
	public function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, self::defaults() );
		$title    = esc_attr( $instance['title'] );
		$type     = esc_attr( $instance['type'] );
		$limit    = (int) $instance['limit'];
		$chars    = (int) $instance['chars'];
		$cat_ids  = esc_attr( $instance['cat_ids'] );
		$link     = (int) $instance['link'];
		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:', 'wp-downloadmanager' ); ?> <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>" /></label>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'type' ); ?>"><?php _e( 'Statistics Type:', 'wp-downloadmanager' ); ?>
				<select name="<?php echo $this->get_field_name( 'type' ); ?>" id="<?php echo $this->get_field_id( 'type' ); ?>" class="widefat">
					<option value="downloads_category"<?php selected( 'downloads_category', $type ); ?>><?php _e( 'Display Downloads In Category', 'wp-downloadmanager' ); ?></option>
					<option value="recent_downloads"<?php selected( 'recent_downloads', $type ); ?>><?php _e( 'Recent Downloads', 'wp-downloadmanager' ); ?></option>
					<option value="most_downloaded"<?php selected( 'most_downloaded', $type ); ?>><?php _e( 'Most Downloaded', 'wp-downloadmanager' ); ?></option>
				</select>
			</label>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'limit' ); ?>"><?php _e( 'No. Of Records To Show:', 'wp-downloadmanager' ); ?> <input class="widefat" id="<?php echo $this->get_field_id( 'limit' ); ?>" name="<?php echo $this->get_field_name( 'limit' ); ?>" type="text" value="<?php echo $limit; ?>" /></label>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'chars' ); ?>"><?php _e( 'Maximum Title Length (Characters):', 'wp-downloadmanager' ); ?> <input class="widefat" id="<?php echo $this->get_field_id( 'chars' ); ?>" name="<?php echo $this->get_field_name( 'chars' ); ?>" type="text" value="<?php echo $chars; ?>" /></label><br />
			<small><?php _e( '<strong>0</strong> to disable.', 'wp-downloadmanager' ); ?></small>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'cat_ids' ); ?>"><?php _e( 'Category IDs:', 'wp-downloadmanager' ); ?> <span style="color: red;">*</span> <input class="widefat" id="<?php echo $this->get_field_id( 'cat_ids' ); ?>" name="<?php echo $this->get_field_name( 'cat_ids' ); ?>" type="text" value="<?php echo $cat_ids; ?>" /></label><br />
			<small><?php _e( 'Seperate mutiple categories with commas.', 'wp-downloadmanager' ); ?></small>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'link' ); ?>"><?php _e( 'Display Link To Download Page?', 'wp-downloadmanager' ); ?>
				<select name="<?php echo $this->get_field_name( 'link' ); ?>" id="<?php echo $this->get_field_id( 'link' ); ?>" class="widefat">
					<?php // These compared against $type rather than $link, so the saved value never rendered as selected. ?>
					<option value="0"<?php selected( 0, $link ); ?>><?php _e( 'No', 'wp-downloadmanager' ); ?></option>
					<option value="1"<?php selected( 1, $link ); ?>><?php _e( 'Yes', 'wp-downloadmanager' ); ?></option>
				</select>
			</label>
		</p>
		<p style="color: red;">
			<small><?php _e( '* If you are not using any category statistics, you can ignore it.', 'wp-downloadmanager' ); ?></small>
		<p>
		<?php
	}
}


// Function: Init WP-DownloadManager Widget
add_action( 'widgets_init', 'widget_downloadmanager_init' );
function widget_downloadmanager_init() {
	register_widget( 'WP_Widget_DownloadManager' );
}


// Function: Activate Plugin
register_activation_hook( __FILE__, 'downloadmanager_activation' );
function downloadmanager_activation( $network_wide ) {
	if ( is_multisite() && $network_wide ) {
		// wp_get_sites() was removed in WP 5.1, so this branch used to fatal
		// rather than merely skip sites. get_sites() defaults 'number' to 100,
		// which would silently leave every site past the hundredth uninstalled.
		$site_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		);

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( (int) $site_id );
			downloadmanager_activate();
			// switch_to_blog() pushes onto a stack, so the restore belongs inside
			// the loop. Restoring once at the end leaves it unwound by one.
			restore_current_blog();
		}
	} else {
		downloadmanager_activate();
	}
}


function downloadmanager_activate() {
	global $wpdb;

	// upgrade-functions.php has been a deprecation shim since WP 2.5 and loading
	// it emits a _deprecated_file() notice on every activation.
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset_collate = '';
	if ( $wpdb->has_cap( 'collation' ) ) {
		if ( ! empty( $wpdb->charset ) ) {
			$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
		}
		if ( ! empty( $wpdb->collate ) ) {
			$charset_collate .= " COLLATE $wpdb->collate";
		}
	}
	// Create WP-Downloads Table
	$create_table = "CREATE TABLE $wpdb->downloads (" .
							'file_id int(10) NOT NULL auto_increment,' .
							'file tinytext NOT NULL,' .
							'file_name text character set utf8 NOT NULL,' .
							'file_des text character set utf8 NOT NULL,' .
							"file_size varchar(20) NOT NULL default ''," .
							"file_category int(2) NOT NULL default '0'," .
							"file_date varchar(20) NOT NULL default ''," .
							"file_updated_date varchar(20) NOT NULL default ''," .
							"file_last_downloaded_date varchar(20) NOT NULL default ''," .
							"file_hits int(10) NOT NULL default '0'," .
							"file_permission TINYINT(2) NOT NULL default '0'," .
							"PRIMARY KEY (file_id)) $charset_collate;";
	maybe_create_table( $wpdb->downloads, $create_table );
	// WP-Downloads Options
	// add_option()'s third argument has been deprecated with no alternative
	// since WP 2.3 and emits a notice on every activation, so it is gone from
	// every call below. The is_site_admin() branch that used to sit here was
	// dead too: core removed that function in WP 3.0, so only the else branch
	// has run for the last fifteen years.
	add_option( 'download_path', WP_CONTENT_DIR . '/files' );
	add_option( 'download_path_url', content_url( 'files' ) );
	add_option( 'download_page_url', site_url( 'downloads' ) );
	add_option( 'download_method', 1 );
	add_option( 'download_categories', array( 'General' ) );
	add_option(
		'download_sort',
		array(
			'by'      => 'file_name',
			'order'   => 'asc',
			'perpage' => 20,
			'group'   => 1,
		)
	);
	add_option( 'download_template_header', '<p>' . __( 'There are <strong>%TOTAL_FILES_COUNT% files</strong>, weighing <strong>%TOTAL_SIZE%</strong> with <strong>%TOTAL_HITS% hits</strong> in <strong>%FILE_CATEGORY_NAME%</strong>.</p><p>Displaying <strong>%RECORD_START%</strong> to <strong>%RECORD_END%</strong> of <strong>%TOTAL_FILES_COUNT%</strong> files.', 'wp-downloadmanager' ) . '</p>' );
	add_option( 'download_template_footer', '<form action="%DOWNLOAD_PAGE_URL%" method="get"><p><input type="hidden" name="dl_cat" value="%CATEGORY_ID%" /><input type="text" name="dl_search" value="%FILE_SEARCH_WORD%" />&nbsp;&nbsp;&nbsp;<input type="submit" value="' . __( 'Search', 'wp-downloadmanager' ) . '" /></p></form>' );
	add_option( 'download_template_category_header', '<h2 id="downloadcat-%CATEGORY_ID%"><a href="%CATEGORY_URL%" title="' . __( 'View all downloads in %FILE_CATEGORY_NAME%', 'wp-downloadmanager' ) . '">%FILE_CATEGORY_NAME%</a></h2>' );
	add_option( 'download_template_category_footer', '' );
	add_option( 'download_template_listing', array( '<p><img src="' . plugins_url( 'wp-downloadmanager/images/ext' ) . '/%FILE_ICON%" alt="" title="" style="vertical-align: middle;" />&nbsp;&nbsp;<strong><a href="%FILE_DOWNLOAD_URL%">%FILE_NAME%</a></strong><br /><strong>&raquo; %FILE_SIZE% - %FILE_HITS% ' . __( 'hits', 'wp-downloadmanager' ) . ' - %FILE_DATE%</strong><br />%FILE_DESCRIPTION%</p>', '<p><img src="' . plugins_url( 'wp-downloadmanager/images/ext' ) . '/%FILE_ICON%" alt="" title="" style="vertical-align: middle;" />&nbsp;&nbsp;<strong>%FILE_NAME%</strong><br /><strong>&raquo; %FILE_SIZE% - %FILE_HITS% ' . __( 'hits', 'wp-downloadmanager' ) . ' - %FILE_DATE%</strong><br /><i>' . __( 'You do not have permission to download this file.', 'wp-downloadmanager' ) . '</i><br />%FILE_DESCRIPTION%</p>' ) );
	add_option( 'download_template_embedded', array( '<p><img src="' . plugins_url( 'wp-downloadmanager/images/ext' ) . '/%FILE_ICON%" alt="" title="" style="vertical-align: middle;" />&nbsp;&nbsp;<strong><a href="%FILE_DOWNLOAD_URL%">%FILE_NAME%</a></strong> (%FILE_SIZE%' . __( ',', 'wp-downloadmanager' ) . ' %FILE_HITS% ' . __( 'hits', 'wp-downloadmanager' ) . ')</p>', '<p><img src="' . plugins_url( 'wp-downloadmanager/images/ext' ) . '/%FILE_ICON%" alt="" title="" style="vertical-align: middle;" />&nbsp;&nbsp;<strong>%FILE_NAME%</strong> (%FILE_SIZE%' . __( ',', 'wp-downloadmanager' ) . ' %FILE_HITS% ' . __( 'hits', 'wp-downloadmanager' ) . ')<br /><i>' . __( 'You do not have permission to download this file.', 'wp-downloadmanager' ) . '</i></p>' ) );
	add_option( 'download_template_most', array( '<li><a href="%FILE_DOWNLOAD_URL%">%FILE_NAME%</a> (%FILE_SIZE%' . __( ',', 'wp-downloadmanager' ) . ' %FILE_HITS% ' . __( 'hits', 'wp-downloadmanager' ) . ')</li>', '<li>%FILE_NAME% (%FILE_SIZE%' . __( ',', 'wp-downloadmanager' ) . ' %FILE_HITS% ' . __( 'hits', 'wp-downloadmanager' ) . ')<br /><i>' . __( 'You do not have permission to download this file.', 'wp-downloadmanager' ) . '</i></li>' ) );
	// Database Upgrade For WP-DownloadManager 1.30
	$check_for_130 = $wpdb->get_var( "SELECT option_value FROM $wpdb->options WHERE option_name = 'download_nice_permalink'" );
	if ( ! $check_for_130 ) {
		maybe_add_column( $wpdb->downloads, 'file_updated_date', "ALTER TABLE $wpdb->downloads ADD file_updated_date VARCHAR(20) NOT NULL AFTER file_date;" );
		$wpdb->query( "UPDATE $wpdb->downloads SET file_updated_date = file_date;" );
		maybe_add_column( $wpdb->downloads, 'file_last_downloaded_date', "ALTER TABLE $wpdb->downloads ADD file_last_downloaded_date VARCHAR(20) NOT NULL AFTER file_updated_date;" );
		$wpdb->query( "UPDATE $wpdb->downloads SET file_last_downloaded_date = file_date;" );
	}
	add_option( 'download_template_pagingheader', '' );
	add_option( 'download_template_pagingfooter', '' );
	add_option( 'download_nice_permalink', 1 );
	add_option( 'download_template_download_page_link', '<p><a href="%DOWNLOAD_PAGE_URL%" title="' . __( 'Downloads Page', 'wp-downloadmanager' ) . '">' . __( 'Downloads Page', 'wp-downloadmanager' ) . '</a></p>' );
	add_option( 'download_template_none', '<p style="text-align: center;">' . __( 'No Files Found.', 'wp-downloadmanager' ) . '</p>' );
	// Database Upgrade For WP-DownloadManager 1.50
	$check_for_150 = $wpdb->get_var( "SELECT option_value FROM $wpdb->options WHERE option_name = 'download_options'" );
	if ( ! $check_for_150 ) {
		$update_permission_1 = $wpdb->query( "UPDATE $wpdb->downloads SET file_permission = -2 WHERE file_permission = -1;" );
		if ( $update_permission_1 ) {
			$update_permission_2 = $wpdb->query( "UPDATE $wpdb->downloads SET file_permission = -1 WHERE file_permission = 0;" );
			if ( $update_permission_2 ) {
				$wpdb->query( "UPDATE $wpdb->downloads SET file_permission = 0 WHERE file_permission = 1;" );
			}
		}
	}
	add_option(
		'download_options',
		array(
			'use_filename' => 0,
			'rss_sortby'   => 'file_date',
			'rss_limit'    => 20,
		)
	);
	// Create Files Folder
	// The is_site_admin() branch that used to sit here was dead: core removed
	// that function in WP 3.0. is_writable() was also being asked about the
	// directory the code was about to create, which is never true, so the
	// directory was never actually made - ask about the parent instead.
	if ( ! is_dir( WP_CONTENT_DIR . '/files/' ) && is_writable( WP_CONTENT_DIR ) ) {
		wp_mkdir_p( WP_CONTENT_DIR . '/files/' );
	}
	delete_option( 'widget_download_recent_downloads' );
	delete_option( 'widget_download_most_downloaded' );
	// Set 'manage_downloads' Capabilities To Administrator
	$role = get_role( 'administrator' );
	if ( $role && ! $role->has_cap( 'manage_downloads' ) ) {
		$role->add_cap( 'manage_downloads' );
	}

	flush_rewrite_rules();
}
