<?php
/**
 * Front-end rendering for WP-DownloadManager.
 *
 * @package WP-DownloadManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders the downloads page, embedded downloads and the stats lists.
 */
class WP_DownloadManager_Display {

	/**
	 * Hook up.
	 *
	 * @return void
	 */
	public static function init() {
		add_shortcode( 'download', array( __CLASS__, 'download_shortcode' ) );
		add_shortcode( 'page_download', array( __CLASS__, 'page_shortcode' ) );
		add_shortcode( 'page_downloads', array( __CLASS__, 'page_shortcode' ) );
	}

	/**
	 * Look up a category name by ID.
	 *
	 * Deleting a category on the settings screen leaves files pointing at an ID
	 * that is no longer in the list, which used to be an "Undefined array key"
	 * warning on every affected row.
	 *
	 * @param array $categories Category list.
	 * @param int   $cat_id     Category ID.
	 * @return string
	 */
	public static function category_name( $categories, $cat_id ) {
		$cat_id = (int) $cat_id;

		if ( ! is_array( $categories ) || ! isset( $categories[ $cat_id ] ) ) {
			return '';
		}

		return stripslashes( $categories[ $cat_id ] );
	}

	/**
	 * The current request's query args, unslashed and sanitized.
	 *
	 * $_GET is slashed by WordPress; http_build_query() would otherwise encode
	 * the added backslashes into every generated link.
	 *
	 * @return array
	 */
	public static function query_args() {

		return array_map( 'sanitize_text_field', wp_unslash( $_GET ) );
	}

	/**
	 * The URL that filters the downloads page to one category.
	 *
	 * @param int $cat_id Category ID.
	 * @return string
	 */
	public static function category_url( $cat_id ) {
		$args = array_merge( self::query_args(), array( 'dl_cat' => (int) $cat_id ) );

		return esc_url( WP_DownloadManager_Options::get( 'page_url' ) . '?' . http_build_query( $args ) );
	}

	/**
	 * The URL of another page of the downloads listing.
	 *
	 * @param int $page Page number.
	 * @return string
	 */
	public static function page_link( $page ) {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$args        = array_merge( self::query_args(), array( 'dl_page' => (int) $page ) );

		return esc_url( wp_parse_url( $request_uri, PHP_URL_PATH ) . '?' . http_build_query( $args ) );
	}

	/**
	 * Wrap search terms found in a string.
	 *
	 * @param string $search_word Space separated search terms.
	 * @param string $search_text Text to highlight within.
	 * @return string
	 */
	public static function search_highlight( $search_word, $search_text ) {
		if ( empty( $search_word ) ) {
			return $search_text;
		}

		foreach ( explode( ' ', $search_word ) as $term ) {
			if ( '' === trim( $term ) ) {
				continue;
			}
			// preg_quote() the term: it comes from ?dl_search=, so a bare "(" was
			// enough to make preg_replace() fail compilation, return null and
			// blank out the file name it was supposed to be highlighting.
			$replaced = preg_replace(
				'/\w*?' . preg_quote( $term, '/' ) . '\w*/i',
				'<span class="download-search-highlight">$0</span>',
				$search_text
			);
			if ( null !== $replaced ) {
				$search_text = $replaced;
			}
		}

		return $search_text;
	}

	/**
	 * Truncate a string to a character budget.
	 *
	 * @param string $text   Text.
	 * @param int    $length Maximum length, 0 for no limit.
	 * @return string
	 */
	public static function snippet_text( $text, $length = 0 ) {
		$charset = get_option( 'blog_charset' );
		$text    = html_entity_decode( $text, ENT_QUOTES, $charset );

		if ( $length > 0 && mb_strlen( $text ) > $length ) {
			return htmlentities( mb_substr( $text, 0, $length ), ENT_COMPAT, $charset ) . '...';
		}

		return htmlentities( $text, ENT_COMPAT, $charset );
	}

	/**
	 * Substitute the per-file template variables.
	 *
	 * Shared by the listing, embedded and stats templates, which each used to
	 * carry their own near-identical block of twenty str_replace() calls.
	 *
	 * @param string $template   Template markup.
	 * @param object $file       Row from the downloads table.
	 * @param array  $context    Optional overrides: 'icons', 'categories',
	 *                           'search', 'file_name', 'description'.
	 * @return string
	 */
	public static function replace_file_vars( $template, $file, $context = array() ) {
		$context = wp_parse_args(
			$context,
			array(
				'icons'       => array(),
				'categories'  => array(),
				'search'      => '',
				'file_name'   => null,
				'description' => null,
			)
		);

		$search    = $context['search'];
		$file_name = null === $context['file_name'] ? stripslashes( $file->file_name ) : $context['file_name'];
		$file_des  = null === $context['description'] ? stripslashes( $file->file_des ) : $context['description'];
		$cat_id    = (int) $file->file_category;

		$replacements = array(
			'%FILE_ID%'            => $file->file_id,
			'%FILE%'               => stripslashes( $file->file ),
			'%FILE_NAME%'          => self::search_highlight( $search, $file_name ),
			'%FILE_EXT%'           => self::search_highlight( $search, WP_DownloadManager_File::extension( stripslashes( $file->file ) ) ),
			'%FILE_ICON%'          => WP_DownloadManager_File::extension_image( stripslashes( $file->file ), $context['icons'] ),
			'%FILE_DESCRIPTION%'   => self::search_highlight( $search, $file_des ),
			'%FILE_SIZE%'          => WP_DownloadManager_File::format_size( $file->file_size ),
			'%FILE_SIZE_DEC%'      => WP_DownloadManager_File::format_size_dec( $file->file_size ),
			'%FILE_CATEGORY_ID%'   => $cat_id,
			'%FILE_CATEGORY_NAME%' => self::category_name( $context['categories'], $cat_id ),
			'%FILE_DATE%'          => mysql2date( get_option( 'date_format' ), gmdate( 'Y-m-d H:i:s', (int) $file->file_date ) ),
			'%FILE_TIME%'          => mysql2date( get_option( 'time_format' ), gmdate( 'Y-m-d H:i:s', (int) $file->file_date ) ),
			'%FILE_UPDATED_DATE%'  => mysql2date( get_option( 'date_format' ), gmdate( 'Y-m-d H:i:s', (int) $file->file_updated_date ) ),
			'%FILE_UPDATED_TIME%'  => mysql2date( get_option( 'time_format' ), gmdate( 'Y-m-d H:i:s', (int) $file->file_updated_date ) ),
			'%FILE_HITS%'          => number_format_i18n( $file->file_hits ),
			'%FILE_DOWNLOAD_URL%'  => WP_DownloadManager_File::download_url( $file->file_id, $file->file ),
		);

		return str_replace( array_keys( $replacements ), array_values( $replacements ), $template );
	}

	/**
	 * Substitute the category-level template variables.
	 *
	 * @param string $template Template markup.
	 * @param int    $cat_id   Category ID.
	 * @param array  $stats    Per-category totals.
	 * @param array  $names    Category names.
	 * @return string
	 */
	protected static function replace_category_vars( $template, $cat_id, $stats, $names ) {
		$cat_id = (int) $cat_id;
		$stat   = isset( $stats[ $cat_id ] ) ? $stats[ $cat_id ] : array(
			'files' => 0,
			'hits'  => 0,
			'size'  => 0,
		);

		$replacements = array(
			'%FILE_CATEGORY_NAME%'   => self::category_name( $names, $cat_id ),
			'%CATEGORY_ID%'          => $cat_id,
			'%CATEGORY_URL%'         => self::category_url( $cat_id ),
			'%CATEGORY_FILES_COUNT%' => number_format_i18n( $stat['files'] ),
			'%CATEGORY_HITS%'        => number_format_i18n( $stat['hits'] ),
			'%CATEGORY_SIZE%'        => WP_DownloadManager_File::format_size( $stat['size'] ),
			'%CATEGORY_SIZE_DEC%'    => WP_DownloadManager_File::format_size_dec( $stat['size'] ),
		);

		return str_replace( array_keys( $replacements ), array_values( $replacements ), $template );
	}

	/**
	 * Substitute the page-level template variables.
	 *
	 * @param string $template Template markup.
	 * @param array  $totals   Whole-listing totals.
	 * @param array  $context  Category, search term and record window.
	 * @return string
	 */
	protected static function replace_page_vars( $template, $totals, $context ) {
		$page_url = WP_DownloadManager_Options::get( 'page_url' );

		// The search form posts to the downloads page. With plain permalinks the
		// page is identified by ?page_id=N, which a GET form would drop.
		if ( 0 === (int) WP_DownloadManager_Options::get( 'nice_permalink', 1 )
			&& preg_match( '/[\?\&]page_id=(\d+)/i', $page_url, $matches ) ) {
			$template = preg_replace(
				'/(<form[^>]+>)/i',
				'$1<input type="hidden" name="page_id" value="' . $matches[1] . '" />',
				$template
			);
		}

		$replacements = array(
			'%TOTAL_FILES_COUNT%'  => number_format_i18n( $totals['files'] ),
			'%TOTAL_HITS%'         => number_format_i18n( $totals['hits'] ),
			'%TOTAL_SIZE%'         => WP_DownloadManager_File::format_size( $totals['size'] ),
			'%TOTAL_SIZE_DEC%'     => WP_DownloadManager_File::format_size_dec( $totals['size'] ),
			'%RECORD_START%'       => number_format_i18n( $context['record_start'] ),
			'%RECORD_END%'         => number_format_i18n( $context['record_end'] ),
			'%CATEGORY_ID%'        => $context['category'],
			'%FILE_CATEGORY_NAME%' => self::category_name( $context['categories'], $context['category'] ),
			'%FILE_SEARCH_WORD%'   => esc_attr( $context['search'] ),
			'%DOWNLOAD_PAGE_URL%'  => $page_url,
		);

		return str_replace( array_keys( $replacements ), array_values( $replacements ), $template );
	}

	/**
	 * The downloads page.
	 *
	 * @param int $category_id Restrict to one category, 0 for all.
	 * @return string
	 */
	public static function downloads_page( $category_id = 0 ) {
		global $wpdb;

		$category_id = (int) $category_id;

		$category    = ! empty( $_GET['dl_cat'] ) ? (int) $_GET['dl_cat'] : 0;
		$page        = ! empty( $_GET['dl_page'] ) ? (int) $_GET['dl_page'] : 0;
		$search_word = ! empty( $_GET['dl_search'] )
			? wp_strip_all_tags( trim( sanitize_text_field( wp_unslash( $_GET['dl_search'] ) ) ) )
			: '';

		$search     = $search_word;
		$categories = (array) WP_DownloadManager_Options::get( 'categories', array() );
		// Index 0 is the "all categories" label rather than a real category.
		$categories[0] = __( 'total', 'wp-downloadmanager' );

		$category_stats = array();
		$totals         = array(
			'files' => 0,
			'size'  => 0,
			'hits'  => 0,
		);

		$sort            = (array) WP_DownloadManager_Options::get( 'sort', array() );
		$sort_by         = WP_DownloadManager_File::sort_column( isset( $sort['by'] ) ? $sort['by'] : '', 'file_name' );
		$sort_order      = WP_DownloadManager_File::sort_order( isset( $sort['order'] ) ? $sort['order'] : '' );
		$per_page        = max( 1, (int) ( isset( $sort['perpage'] ) ? $sort['perpage'] : 20 ) );
		$group           = (int) ( isset( $sort['group'] ) ? $sort['group'] : 0 );
		$order_by_column = 'file_date' === $sort_by ? 'FROM_UNIXTIME(file_date)' : $sort_by;

		$icons = WP_DownloadManager_File::extension_images();

		if ( 0 === $category && $category_id > 0 ) {
			$category = $category_id;
		}
		$category_sql = $category > 0 ? 'AND file_category = ' . (int) $category : '';

		// Carried as a placeholder fragment plus its arguments rather than a
		// prepared string: the clause goes into two queries, and feeding an
		// already-prepared fragment back through prepare() would have the %
		// wildcards either re-read as placeholders or flagged as unescaped.
		$search_sql  = '';
		$search_args = array();
		if ( '' !== $search ) {
			foreach ( explode( ' ', $search_word ) as $term ) {
				if ( '' === trim( $term ) ) {
					continue;
				}
				// esc_like() so a literal % or _ is not read as a wildcard.
				$like        = '%' . $wpdb->esc_like( $term ) . '%';
				$search_sql .= ' AND ((file_name LIKE %s OR file_des LIKE %s OR file LIKE %s))';
				array_push( $search_args, $like, $like, $like );
			}
		}

		// The permission guard is carried as a bound argument rather than a
		// literal so the query always has at least one placeholder, which is what
		// lets both branches - with a search term and without - go through
		// prepare() rather than only the one that happens to have arguments.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT file_category, COUNT(file_id) as category_files, SUM(file_size) category_size, SUM(file_hits) as category_hits FROM {$wpdb->downloads} WHERE 1=1 {$category_sql} {$search_sql} AND file_permission != %d GROUP BY file_category",
				array_merge( $search_args, array( -2 ) )
			)
		);

		foreach ( (array) $rows as $row ) {
			$cat_id                    = (int) $row->file_category;
			$category_stats[ $cat_id ] = array(
				'files' => (int) $row->category_files,
				'hits'  => (int) $row->category_hits,
				'size'  => (int) $row->category_size,
			);
			$totals['files']          += (int) $row->category_files;
			$totals['hits']           += (int) $row->category_hits;
			$totals['size']           += (int) $row->category_size;
		}

		$paging = self::paging( $totals['files'], $per_page, $page );
		$page   = $paging['page'];

		$group_sql = 1 === $group ? 'file_category ASC,' : '';

		// The placeholder count is dynamic - three per search term, plus the two
		// LIMIT bounds - which is why phpcs.xml exempts this file from the
		// replacement-count sniff.

		$files = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->downloads} WHERE 1=1 {$category_sql} {$search_sql} AND file_permission != %d ORDER BY {$group_sql} {$order_by_column} {$sort_order} LIMIT %d, %d",
				array_merge( $search_args, array( -2, $paging['offset'], $per_page ) )
			)
		);

		$output = '';

		if ( $files ) {
			$page_context = array(
				'category'     => $category,
				'categories'   => $categories,
				'search'       => $search,
				'record_start' => $paging['display_on_page'],
				'record_end'   => $paging['max_on_page'],
			);

			$output .= self::replace_page_vars(
				stripslashes( WP_DownloadManager_Options::template( 'header' ) ),
				$totals,
				$page_context
			);

			$temp_cat_id = -1;
			$need_footer = false;

			foreach ( $files as $file ) {
				$cat_id = (int) $file->file_category;

				if ( $need_footer && $temp_cat_id !== $cat_id && 1 === $group ) {
					$output     .= self::replace_category_vars(
						stripslashes( WP_DownloadManager_Options::template( 'category_footer' ) ),
						$temp_cat_id,
						$category_stats,
						$categories
					);
					$need_footer = false;
				}

				if ( $temp_cat_id !== $cat_id && 1 === $group ) {
					$output     .= self::replace_category_vars(
						stripslashes( WP_DownloadManager_Options::template( 'category_header' ) ),
						$cat_id,
						$category_stats,
						$categories
					);
					$need_footer = true;
				}

				$index   = WP_DownloadManager_File::can_download( $file->file_permission ) ? 0 : 1;
				$output .= self::replace_file_vars(
					stripslashes( WP_DownloadManager_Options::template( 'listing', $index ) ),
					$file,
					array(
						'icons'      => $icons,
						'categories' => $categories,
						'search'     => $search,
					)
				);

				$temp_cat_id = $cat_id;
			}//end foreach

			if ( $need_footer ) {
				$output .= self::replace_category_vars(
					stripslashes( WP_DownloadManager_Options::template( 'category_footer' ) ),
					$temp_cat_id,
					$category_stats,
					$categories
				);
			}

			$output .= self::replace_page_vars(
				stripslashes( WP_DownloadManager_Options::template( 'footer' ) ),
				$totals,
				$page_context
			);
		} else {
			$output .= stripslashes( WP_DownloadManager_Options::template( 'none' ) );
		}//end if

		$output .= self::paging_markup( $paging );

		return apply_filters( 'downloads_page', $output );
	}

	/**
	 * Work out the paging window.
	 *
	 * @param int $total    Total matching files.
	 * @param int $per_page Files per page.
	 * @param int $page     Requested page.
	 * @return array
	 */
	protected static function paging( $total, $per_page, $page ) {
		$max_page = (int) ceil( $total / $per_page );

		if ( $page < 1 ) {
			$page = 1;
		}

		$offset        = ( $page - 1 ) * $per_page;
		$pages_to_show = 10;
		$span          = $pages_to_show - 1;
		$start_page    = $page - (int) floor( $span / 2 );

		if ( $start_page <= 0 ) {
			$start_page = 1;
		}

		$end_page = $page + (int) ceil( $span / 2 );
		if ( ( $end_page - $start_page ) !== $span ) {
			$end_page = $start_page + $span;
		}
		if ( $end_page > $max_page ) {
			$start_page = $max_page - $span;
			$end_page   = $max_page;
		}
		if ( $start_page <= 0 ) {
			$start_page = 1;
		}

		return array(
			'page'            => $page,
			'offset'          => $offset,
			'max_page'        => $max_page,
			'start_page'      => $start_page,
			'end_page'        => $end_page,
			'pages_to_show'   => $pages_to_show,
			'max_on_page'     => ( $offset + $per_page ) > $total ? $total : ( $offset + $per_page ),
			'display_on_page' => ( $offset + 1 ) > $total ? $total : ( $offset + 1 ),
		);
	}

	/**
	 * The paging links below the listing.
	 *
	 * @param array $paging Paging window from paging().
	 * @return string
	 */
	protected static function paging_markup( $paging ) {
		if ( $paging['max_page'] <= 1 ) {
			return '';
		}

		$page     = $paging['page'];
		$max_page = $paging['max_page'];

		$output  = stripslashes( WP_DownloadManager_Options::template( 'pagingheader' ) );
		$output .= function_exists( 'wp_pagenavi' )
			? '<div class="wp-pagenavi">' . "\n"
			: '<div class="wp-downloadmanager-paging">' . "\n";

		$output .= '<span class="pages">&#8201;' . sprintf(
			/* translators: 1: current page number, 2: total number of pages. */
			__( 'Page %1$s of %2$s', 'wp-downloadmanager' ),
			number_format_i18n( $page ),
			number_format_i18n( $max_page )
		) . '&#8201;</span>';

		if ( $paging['start_page'] >= 2 && $paging['pages_to_show'] < $max_page ) {
			$output .= '<a href="' . self::page_link( 1 ) . '" title="' . __( '&laquo; First', 'wp-downloadmanager' ) . '">&#8201;' . __( '&laquo; First', 'wp-downloadmanager' ) . '&#8201;</a>';
			$output .= '<span class="extend">...</span>';
		}
		if ( $page > 1 ) {
			$output .= '<a href="' . self::page_link( $page - 1 ) . '" title="' . __( '&laquo;', 'wp-downloadmanager' ) . '">&#8201;' . __( '&laquo;', 'wp-downloadmanager' ) . '&#8201;</a>';
		}
		for ( $i = $paging['start_page']; $i <= $paging['end_page']; $i++ ) {
			if ( $i === $page ) {
				$output .= '<span class="current">&#8201;' . number_format_i18n( $i ) . '&#8201;</span>';
			} else {
				$output .= '<a href="' . self::page_link( $i ) . '" title="' . number_format_i18n( $i ) . '">&#8201;' . number_format_i18n( $i ) . '&#8201;</a>';
			}
		}
		if ( ( $page + 1 ) <= $max_page ) {
			$output .= '<a href="' . self::page_link( $page + 1 ) . '" title="' . __( '&raquo;', 'wp-downloadmanager' ) . '">&#8201;' . __( '&raquo;', 'wp-downloadmanager' ) . '&#8201;</a>';
		}
		if ( $paging['end_page'] < $max_page ) {
			$output .= '<span class="extend">...</span>';
			$output .= '<a href="' . self::page_link( $max_page ) . '" title="' . __( 'Last &raquo;', 'wp-downloadmanager' ) . '">&#8201;' . __( 'Last &raquo;', 'wp-downloadmanager' ) . '&#8201;</a>';
		}

		$output .= '</div>';
		$output .= stripslashes( WP_DownloadManager_Options::template( 'pagingfooter' ) );

		return $output;
	}

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
	public static function download_embedded( $condition = '', $display = 'both', $sort_by = 'file_id', $sort_order = 'asc', $stream_limit = 0 ) {
		global $wpdb;

		$sort_by         = WP_DownloadManager_File::sort_column( $sort_by, 'file_id' );
		$sort_order      = WP_DownloadManager_File::sort_order( $sort_order );
		$order_by_column = 'file_date' === $sort_by ? 'FROM_UNIXTIME(file_date)' : $sort_by;
		$stream_limit    = max( (int) $stream_limit, 0 );

		if ( '' !== $condition ) {
			$condition .= ' AND ';
		}

		// Only enough rows to know whether there are more than the limit.
		$limit_sql = ( ! is_single() && 0 !== $stream_limit )
			? ' LIMIT ' . ( $stream_limit + 1 )
			: '';

		$files = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->downloads} WHERE {$condition} file_permission != %d ORDER BY {$order_by_column} {$sort_order}{$limit_sql}",
				-2
			)
		);

		if ( ! $files ) {
			// Used to fall off the end and return null, which is a TypeError
			// waiting to happen for any caller that concatenates the result.
			return apply_filters( 'download_embedded', '' );
		}

		$icons      = WP_DownloadManager_File::extension_images();
		$categories = (array) WP_DownloadManager_Options::get( 'categories', array() );

		$shown = ( is_single() || 0 === $stream_limit )
			? count( $files )
			: min( $stream_limit, count( $files ) );

		$output = '';

		for ( $i = 0; $i < $shown; $i++ ) {
			$file    = $files[ $i ];
			$index   = WP_DownloadManager_File::can_download( $file->file_permission ) ? 0 : 1;
			$output .= self::replace_file_vars(
				stripslashes( WP_DownloadManager_Options::template( 'embedded', $index ) ),
				$file,
				array(
					'icons'       => $icons,
					'categories'  => $categories,
					'description' => 'both' === $display ? null : '',
				)
			);
		}

		if ( ! is_single() && 0 !== $shown && $shown < count( $files ) ) {
			$output .= '<p><a href="' . get_permalink() . '">' . __( 'More …', 'wp-downloadmanager' ) . '</a></p>';
		}

		return apply_filters( 'download_embedded', $output );
	}

	/**
	 * Render one of the stats lists from the "most" template.
	 *
	 * @param array $files Rows from the downloads table.
	 * @param int   $chars Truncate file names to this many characters.
	 * @return string
	 */
	protected static function stats_list( $files, $chars ) {
		if ( ! $files ) {
			return '<li>' . __( 'N/A', 'wp-downloadmanager' ) . '</li>' . "\n";
		}

		$icons      = WP_DownloadManager_File::extension_images();
		$categories = (array) WP_DownloadManager_Options::get( 'categories', array() );
		$output     = '';

		foreach ( $files as $file ) {
			$index     = WP_DownloadManager_File::can_download( $file->file_permission ) ? 0 : 1;
			$file_name = $chars > 0
				? self::snippet_text( stripslashes( $file->file_name ), $chars )
				: stripslashes( $file->file_name );

			$output .= self::replace_file_vars(
				stripslashes( WP_DownloadManager_Options::template( 'most', $index ) ),
				$file,
				array(
					'icons'      => $icons,
					'categories' => $categories,
					'file_name'  => $file_name,
				)
			);
		}

		return $output;
	}

	/**
	 * The most downloaded files.
	 *
	 * @param int  $limit   Row limit.
	 * @param int  $chars   Truncate file names to this many characters.
	 * @param bool $display Echo rather than return.
	 * @return string|void
	 */
	public static function most_downloaded( $limit = 10, $chars = 0, $display = true ) {
		global $wpdb;

		$files = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$wpdb->downloads} WHERE file_permission != -2 ORDER BY file_hits DESC LIMIT %d", (int) $limit )
		);

		return self::output( self::stats_list( $files, $chars ), $display );
	}

	/**
	 * The most recently added files.
	 *
	 * @param int  $limit   Row limit.
	 * @param int  $chars   Truncate file names to this many characters.
	 * @param bool $display Echo rather than return.
	 * @return string|void
	 */
	public static function recent_downloads( $limit = 10, $chars = 0, $display = true ) {
		global $wpdb;

		$files = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$wpdb->downloads} WHERE file_permission != -2 ORDER BY FROM_UNIXTIME(file_date) DESC LIMIT %d", (int) $limit )
		);

		return self::output( self::stats_list( $files, $chars ), $display );
	}

	/**
	 * Files in one or more categories.
	 *
	 * @param int|array $cat_id  Category ID or list of them.
	 * @param int       $limit   Row limit.
	 * @param int       $chars   Truncate file names to this many characters.
	 * @param bool      $display Echo rather than return.
	 * @return string|void
	 */
	public static function downloads_category( $cat_id = 0, $limit = 10, $chars = 0, $display = true ) {
		global $wpdb;

		// Cast every id. The widget passes explode( ',', $instance['cat_ids'] )
		// straight in, so without this anyone able to edit a widget could rewrite
		// the WHERE clause - including the guard that hides files.
		if ( is_array( $cat_id ) ) {
			$cat_ids = array_map( 'intval', $cat_id );
			if ( empty( $cat_ids ) ) {
				$cat_ids = array( 0 );
			}
			$category_sql = 'file_category IN (' . implode( ',', $cat_ids ) . ')';
		} else {
			$category_sql = 'file_category = ' . (int) $cat_id;
		}

		$files = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$wpdb->downloads} WHERE {$category_sql} AND file_permission != -2 ORDER BY FROM_UNIXTIME(file_date) DESC LIMIT %d", (int) $limit )
		);

		return self::output( self::stats_list( $files, $chars ), $display );
	}

	/**
	 * The files the downloads feed lists.
	 *
	 * @return array
	 */
	public static function feed_files() {
		global $wpdb;

		$sortby = WP_DownloadManager_File::sort_column( WP_DownloadManager_Options::get( 'rss.sortby', '' ), 'file_date' );
		$limit  = max( 1, (int) WP_DownloadManager_Options::get( 'rss.limit', 20 ) );

		return (array) $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$wpdb->downloads} WHERE file_permission != -2 ORDER BY {$sortby} DESC LIMIT %d", $limit )
		);
	}

	/**
	 * Total number of files.
	 *
	 * @param bool $display Echo rather than return.
	 * @return string|void
	 */
	public static function total_files( $display = true ) {
		global $wpdb;

		return self::output( number_format_i18n( (int) $wpdb->get_var( "SELECT COUNT(file_id) FROM {$wpdb->downloads}" ) ), $display );
	}

	/**
	 * Total size of all files.
	 *
	 * @param bool $display Echo rather than return.
	 * @return string|void
	 */
	public static function total_size( $display = true ) {
		global $wpdb;

		// Cast: SUM() is NULL on an empty table.
		return self::output( WP_DownloadManager_File::format_size( (int) $wpdb->get_var( "SELECT SUM(file_size) FROM {$wpdb->downloads}" ) ), $display );
	}

	/**
	 * Total number of hits.
	 *
	 * @param bool $display Echo rather than return.
	 * @return string|void
	 */
	public static function total_hits( $display = true ) {
		global $wpdb;

		// Cast: SUM() is NULL on an empty table, and number_format_i18n( null )
		// is deprecated on PHP 8.1 and later.
		return self::output( number_format_i18n( (int) $wpdb->get_var( "SELECT SUM(file_hits) FROM {$wpdb->downloads}" ) ), $display );
	}

	/**
	 * Echo or return, preserving the historic $display argument.
	 *
	 * @param string $output  Markup.
	 * @param bool   $display Echo rather than return.
	 * @return string|void
	 */
	protected static function output( $output, $display ) {
		if ( $display ) {
			// The stats templates are stored through wp_kses() on save, so this
			// runs the same allow list back over what comes out of them.
			echo wp_kses_post( $output );
			return;
		}

		return $output;
	}

	/**
	 * The [download] shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function download_shortcode( $atts ) {
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

		if ( is_feed() ) {
			return __( 'Note: There is a file embedded within this post, please visit this post to download the file.', 'wp-downloadmanager' );
		}

		$conditions = array();
		$id         = $attributes['id'];
		$category   = $attributes['category'];

		// Backward compatibility with [download=1].
		if ( ! $id && ! empty( $atts[0] ) ) {
			$id = trim( $atts[0], '="\'' );
		}

		if ( 0 !== $id ) {
			if ( false !== strpos( $id, ',' ) ) {
				$ids          = array_map( 'intval', explode( ',', $id ) );
				$conditions[] = 'file_id IN (' . implode( ',', $ids ) . ')';
			} else {
				$conditions[] = 'file_id = ' . (int) $id;
			}
		}
		if ( 0 !== $category ) {
			if ( false !== strpos( $category, ',' ) ) {
				$categories   = array_map( 'intval', explode( ',', $category ) );
				$conditions[] = 'file_category IN (' . implode( ',', $categories ) . ')';
			} else {
				$conditions[] = 'file_category = ' . (int) $category;
			}
		}

		if ( ! $conditions ) {
			return '';
		}

		return self::download_embedded(
			implode( ' AND ', $conditions ),
			$attributes['display'],
			$attributes['sort_by'],
			$attributes['sort_order'],
			$attributes['stream_limit']
		);
	}

	/**
	 * The [page_download] shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function page_shortcode( $atts ) {
		$attributes = shortcode_atts( array( 'category' => 0 ), $atts );

		return self::downloads_page( $attributes['category'] );
	}
}
