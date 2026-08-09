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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Carries the current listing's own query string into the links it prints; a nonce on a bookmarkable, shareable listing URL would break the back button and change nothing, and nothing here is written.
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

		/*
		 * Split into tags and text, and only ever rewrite the text.
		 *
		 * This runs over markup that kses has already approved, so a term that
		 * happened to appear inside an attribute value -- "example" in a stored
		 * <a href="https://example.com/notes"> -- had the span spliced into the
		 * middle of the attribute and terminated it early. The injected string
		 * is a fixed literal, so no handler or script could be introduced, but a
		 * visitor could corrupt the markup of any listing with ?dl_search=.
		 */
		$parts = preg_split( '/(<[^>]*+>)/', $search_text, -1, PREG_SPLIT_DELIM_CAPTURE );

		if ( ! is_array( $parts ) ) {
			return $search_text;
		}

		foreach ( explode( ' ', $search_word ) as $term ) {
			if ( '' === trim( $term ) ) {
				continue;
			}
			// preg_quote() the term: it comes from ?dl_search=, so a bare "(" was
			// enough to make preg_replace() fail compilation, return null and
			// blank out the file name it was supposed to be highlighting.
			foreach ( $parts as $index => $part ) {
				// Odd indexes are the captured tags; leave them exactly as they
				// are. Only the text between them is highlighted.
				if ( '' === $part || ( isset( $part[0] ) && '<' === $part[0] ) ) {
					continue;
				}

				$replaced = preg_replace(
					'/\w*?' . preg_quote( $term, '/' ) . '\w*/i',
					'<span class="wp-downloadmanager-highlight">$0</span>',
					$part
				);

				if ( null !== $replaced ) {
					$parts[ $index ] = $replaced;
				}
			}

			// Re-split, so a term matched later does not see the spans this pass
			// has just added as text to highlight again.
			$parts = preg_split( '/(<[^>]*+>)/', implode( '', $parts ), -1, PREG_SPLIT_DELIM_CAPTURE );

			if ( ! is_array( $parts ) ) {
				return $search_text;
			}
		}

		return implode( '', $parts );
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
	 * The tags a rendered listing may contain.
	 *
	 * Core's post allow list knows nothing about SVG, so running it over output
	 * that contains the icon sprite would quietly delete every icon. This is
	 * that list plus exactly the elements and attributes sprite() emits, and
	 * nothing else - no scripting hooks, no event attributes, no external
	 * references.
	 *
	 * @return array
	 */
	public static function allowed_html() {
		$shared = array(
			'class'       => true,
			'aria-hidden' => true,
			'focusable'   => true,
		);

		return array_merge(
			wp_kses_allowed_html( 'post' ),
			array(
				'svg'    => array_merge(
					$shared,
					array(
						'xmlns'   => true,
						'viewbox' => true,
					)
				),
				'symbol' => array_merge(
					$shared,
					array(
						'id'              => true,
						'viewbox'         => true,
						'fill'            => true,
						'stroke'          => true,
						'stroke-width'    => true,
						'stroke-linecap'  => true,
						'stroke-linejoin' => true,
					)
				),
				'use'    => array( 'href' => true ),
				'path'   => array( 'd' => true ),
				'circle' => array(
					'cx' => true,
					'cy' => true,
					'r'  => true,
				),
			)
		);
	}

	/**
	 * Whether this request has already printed the icon sprite.
	 *
	 * @var bool
	 */
	protected static $sprite_printed = false;

	/**
	 * The icon sprite: one <symbol> per file family, defined once per request.
	 *
	 * Drawn rather than shipped. Up to 1.69.2 the plugin carried thirty-four
	 * 16x16 GIFs plus drive.png and drive_go.gif - thirty-six raster files that
	 * could not take the theme's colour, blurred on any high-density screen and
	 * cost an HTTP request each. One inline sprite costs none, inherits
	 * currentColor, and scales with the surrounding text.
	 *
	 * @return string
	 */
	public static function sprite() {
		$symbols = array(
			'file'         => '',
			'archive'      => '<path d="M11 3h2v2h-2zM11 6h2v2h-2zM11 9h2v2h-2zM10.5 13h3v3.5h-3z" />',
			'audio'        => '<path d="M10 18.5v-5l5-1.5v5" /><circle cx="9" cy="18.5" r="1.4" /><circle cx="14" cy="17" r="1.4" />',
			'code'         => '<path d="M10.5 12.5 8 15.5l2.5 3M13.5 12.5 16 15.5l-2.5 3" />',
			'document'     => '<path d="M9 12.5h6M9 15.5h6M9 18.5h4" />',
			'image'        => '<circle cx="10" cy="13.5" r="1.1" /><path d="M8 19.5l3-3 2 2 3-3.5 2 4.5z" />',
			'presentation' => '<path d="M9 19.5v-3M12 19.5v-5.5M15 19.5v-8" />',
			'spreadsheet'  => '<path d="M8 12.5h8v7H8zM8 16h8M11.5 12.5v7" />',
			'video'        => '<path d="M10.5 13.5l5 3-5 3z" />',
			'application'  => '<circle cx="12" cy="16.5" r="2" /><path d="M12 12.5V14M12 19v1.5M8.5 16.5H10M14 16.5h1.5" />',
		);

		$out = '<svg xmlns="http://www.w3.org/2000/svg" class="wp-downloadmanager-sprite" aria-hidden="true" focusable="false">';

		foreach ( $symbols as $family => $mark ) {
			$out .= '<symbol id="wp-downloadmanager-icon-' . $family . '" viewBox="0 0 24 24"'
				. ' fill="none" stroke="currentColor" stroke-width="1.4"'
				. ' stroke-linecap="round" stroke-linejoin="round">'
				. '<path d="M6 2.5h7l5 5v14H6z" /><path d="M13 2.5v5h5" />'
				. $mark
				. '</symbol>';
		}

		return $out . '</svg>';
	}

	/**
	 * The icon markup for one file.
	 *
	 * The sprite rides along with the first icon of the request rather than
	 * being hooked to wp_footer: the listing is just as often produced by a
	 * template tag, a shortcode or the feed, none of which reach the footer.
	 *
	 * @param string $file_name Stored file name.
	 * @return string
	 */
	public static function icon( $file_name ) {
		$out = '';

		if ( ! self::$sprite_printed ) {
			self::$sprite_printed = true;
			$out                  = self::sprite();
		}

		return $out . sprintf(
			'<svg class="wp-downloadmanager-icon" aria-hidden="true" focusable="false"><use href="#wp-downloadmanager-icon-%s" /></svg>',
			esc_attr( WP_DownloadManager_File::extension_family( $file_name ) )
		);
	}

	/**
	 * Forget that the sprite has been printed.
	 *
	 * One sprite per request is the rule, and a web request ends when the page
	 * does. A long-running process - the test suite, a WP-CLI import - renders
	 * many pages in one process, and this is how it starts a fresh one.
	 *
	 * @return void
	 */
	public static function reset_sprite() {
		self::$sprite_printed = false;
	}

	/**
	 * Substitute the per-file template variables.
	 *
	 * Shared by the listing, embedded and stats templates, which each used to
	 * carry their own near-identical block of twenty str_replace() calls.
	 *
	 * @param string $template   Template markup.
	 * @param object $file       Row from the downloads table.
	 * @param array  $context    Optional overrides: 'categories', 'search',
	 *                           'file_name', 'description'.
	 * @return string
	 */
	public static function replace_file_vars( $template, $file, $context = array() ) {
		$context = wp_parse_args(
			$context,
			array(
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

		// The name and the description are the two stored fields a site owner is
		// allowed to put markup in, so they are run through the allow list rather
		// than escaped wholesale - and they are run through it here, once, where
		// they enter the templates. wp_kses_post() on the way in is only as good
		// as the way in: a row written straight into the table by a restored
		// backup, a compromised install or a release older than that check
		// reaches this function exactly as it was stored.
		//
		// Five paths render these templates and each used to decide escaping for
		// itself. Three ran the assembled markup back through allowed_html() and
		// two did not, and the two that did not were the downloads page and the
		// [download] shortcode - the two most exposed of the five. A sixth
		// wrapper would only be a sixth place to forget, so the value is clean
		// before it is ever substituted.
		$file_name = wp_kses( $file_name, self::allowed_html() );
		$file_des  = wp_kses( $file_des, self::allowed_html() );

		$replacements = array(
			'%FILE_ID%'            => $file->file_id,

			/*
			 * Escaped, where %FILE_NAME% and %FILE_DESCRIPTION% above are
			 * kses'd. Those two are the fields a site owner is meant to put
			 * markup in; this is a file path and that is a URL, and neither has
			 * any business carrying any. The stock templates put the URL inside
			 * an href, and esc_url_raw() -- which is what the remote branch of
			 * the Add File screen stores through -- permits a single quote, so a
			 * site-authored template using single-quoted attributes had nothing
			 * standing between it and a broken-out one. Both are also reachable
			 * by a direct row write, which is the threat model the kses-at-render
			 * decision above was made for in the first place.
			 */
			'%FILE%'               => esc_html( stripslashes( $file->file ) ),
			'%FILE_NAME%'          => self::search_highlight( $search, $file_name ),
			'%FILE_EXT%'           => self::search_highlight( $search, WP_DownloadManager_File::extension( stripslashes( $file->file ) ) ),
			'%FILE_ICON%'          => self::icon( stripslashes( $file->file ) ),
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
			'%FILE_DOWNLOAD_URL%'  => esc_url( WP_DownloadManager_File::download_url( $file->file_id, $file->file ) ),
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

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- ?dl_cat, ?dl_page and ?dl_search decide which page of the public listing to draw and nothing else; the whole class only reads. A nonce on a listing URL that is meant to be linked, bookmarked and shared would break all three and prove nothing about a request that writes nothing.
		$category    = ! empty( $_GET['dl_cat'] ) ? (int) $_GET['dl_cat'] : 0;
		$page        = ! empty( $_GET['dl_page'] ) ? (int) $_GET['dl_page'] : 0;
		$search_word = ! empty( $_GET['dl_search'] )
			? wp_strip_all_tags( trim( sanitize_text_field( wp_unslash( $_GET['dl_search'] ) ) ) )
			: '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

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

		if ( 0 === $category && $category_id > 0 ) {
			$category = $category_id;
		}

		// The category filter binds rather than concatenating a clause: with no
		// category chosen the first test reads 0 = 0, passes every row and so
		// means exactly what the absent clause used to mean. Both queries below
		// take the pair in this order.
		$category_args = array( $category, $category );

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

		// The permission guard is a bound argument rather than a literal -2, so
		// that both branches - with a search term and without - always have
		// arguments to pass and can go through prepare() the same way. The order
		// here is the order the placeholders appear in: category, search, guard.
		$stats_args = array_merge( $category_args, $search_args, array( -2 ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $search_sql is the only interpolated piece: a generated run of the literal ' AND ((file_name LIKE %s OR file_des LIKE %s OR file LIKE %s))', three placeholders per search term, so the count is only known at run time and prepare() cannot bind a variable-length set of LIKE clauses.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT file_category, COUNT(file_id) as category_files, SUM(file_size) category_size, SUM(file_hits) as category_hits FROM {$wpdb->downloads} WHERE ( %d = 0 OR file_category = %d ) {$search_sql} AND file_permission != %d GROUP BY file_category",
				$stats_args
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

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

		$page_args = array_merge( $category_args, $search_args, array( -2, $paging['offset'], $per_page ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Three interpolated pieces, none of them bindable: $search_sql is a generated run of literal LIKE placeholders, three per search term, so the count is only known at run time; $group_sql is one of the two literals set above; $order_by_column and $sort_order come from WP_DownloadManager_File::sort_columns() and sort_order(), and an ORDER BY column and direction cannot be placeholders.
		$files = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->downloads} WHERE ( %d = 0 OR file_category = %d ) {$search_sql} AND file_permission != %d ORDER BY {$group_sql} {$order_by_column} {$sort_order} LIMIT %d, %d",
				$page_args
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

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

		// One root element carrying the plugin's class, so the stylesheet needs
		// exactly one scope and a theme has one thing to override.
		$output = '<div class="wp-downloadmanager">' . $output . '</div>';

		/**
		 * Filters the whole rendered downloads page.
		 *
		 * Renamed from the unprefixed downloads_page in 2.0.0; the old name is
		 * gone rather than deprecated.
		 *
		 * @since 2.0.0
		 *
		 * @param string $output Rendered markup for the listing.
		 */
		return apply_filters( 'wp_downloadmanager_page', $output );
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

		// Only enough rows to know whether there are more than the limit. Bound
		// through prepare() rather than concatenated, so the fragment that ends up
		// in the statement below is a prepared string and not an integer this
		// method spelled out itself.
		$limit_sql = ( ! is_single() && 0 !== $stream_limit )
			? $wpdb->prepare( ' LIMIT %d', $stream_limit + 1 )
			: '';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $limit_sql is a $wpdb->prepare() result; $order_by_column and $sort_order come from WP_DownloadManager_File::sort_columns() and sort_order(), and an ORDER BY column and direction cannot be bound. $condition is the documented raw-SQL argument of the download_embedded() template tag, a contract older than this rewrite: every caller inside the plugin builds it from intval()ed ids in file_shortcode().
		$files = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->downloads} WHERE {$condition} file_permission != %d ORDER BY {$order_by_column} {$sort_order}{$limit_sql}",
				-2
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! $files ) {
			// Used to fall off the end and return null, which is a TypeError
			// waiting to happen for any caller that concatenates the result.
			/** This filter is documented at the end of this method. */
			return apply_filters( 'wp_downloadmanager_embedded', '' );
		}

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
					'categories'  => $categories,
					'description' => 'both' === $display ? null : '',
				)
			);
		}

		if ( ! is_single() && 0 !== $shown && $shown < count( $files ) ) {
			$output .= '<p><a href="' . get_permalink() . '">' . __( 'More …', 'wp-downloadmanager' ) . '</a></p>';
		}

		$output = '<div class="wp-downloadmanager">' . $output . '</div>';

		/**
		 * Filters the markup for files embedded in a post or page.
		 *
		 * Renamed from the unprefixed download_embedded in 2.0.0; the old name is
		 * gone rather than deprecated.
		 *
		 * @since 2.0.0
		 *
		 * @param string $output Rendered markup, or the empty string when the
		 *                       shortcode matched nothing.
		 */
		return apply_filters( 'wp_downloadmanager_embedded', $output );
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
		} else {
			$cat_ids = array( (int) $cat_id );
		}

		// IN () with one id means the same as = on that id, so both cases go
		// through the same bound list rather than one concatenated clause each.
		$placeholders = implode( ',', array_fill( 0, count( $cat_ids ), '%d' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $placeholders is a generated run of %d, one per category id; prepare() cannot bind a variable-length IN () list.
		$files = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->downloads} WHERE file_category IN ({$placeholders}) AND file_permission != -2 ORDER BY FROM_UNIXTIME(file_date) DESC LIMIT %d",
				array_merge( $cat_ids, array( (int) $limit ) )
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return self::output( self::stats_list( $files, $chars ), $display );
	}

	/**
	 * The downloads RSS feed.
	 *
	 * This was a loose download-rss.php at the plugin root, loaded through
	 * load_template(). Section 1 allows no loose PHP outside the plugin file, so
	 * it lives here, beside the query that feeds it.
	 *
	 * @return void
	 */
	public static function render_feed() {
		global $wpdb;

		$last_updated = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT file_updated_date FROM {$wpdb->downloads} WHERE file_permission != %d ORDER BY file_updated_date DESC LIMIT 1", -2 )
		);
		$categories   = (array) WP_DownloadManager_Options::get( 'categories', array() );
		$files        = self::feed_files();
		$title        = sprintf(
			/* translators: %s: The site name. */
			__( '%s Downloads RSS Feed', 'wp-downloadmanager' ),
			get_bloginfo_rss( 'name' )
		);
		$charset = get_option( 'blog_charset' );

		// Guarded: if anything has already sent output - an early echo from
		// another plugin, or a caller that is buffering - header() would emit a
		// warning straight into the feed body rather than setting the type.
		if ( ! headers_sent() ) {
			header( 'Content-Type: ' . feed_content_type( 'rss2' ) . '; charset=' . $charset, true );
		}

		echo '<?xml version="1.0" encoding="' . esc_attr( $charset ) . '"?>' . "\n";
		?>
<rss version="2.0"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wfw="http://wellformedweb.org/CommentAPI/"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	xmlns:atom="http://www.w3.org/2005/Atom"
	xmlns:sy="http://purl.org/rss/1.0/modules/syndication/"
>
<channel>
	<title><?php echo esc_html( $title ); ?></title>
	<atom:link href="<?php self_link(); ?>" rel="self" type="application/rss+xml" />
	<link><?php echo esc_url( WP_DownloadManager_Options::get( 'page_url' ) ); ?></link>
	<description><?php echo esc_html( $title ); ?></description>
	<pubDate><?php echo esc_html( mysql2date( 'D, d M Y H:i:s +0000', gmdate( 'Y-m-d H:i:s', $last_updated ) ) ); ?></pubDate>
		<?php the_generator( 'rss2' ); ?>
	<language><?php bloginfo_rss( 'language' ); ?></language>
	<sy:updatePeriod><?php echo esc_html( apply_filters( 'rss_update_period', 'hourly' ) ); ?></sy:updatePeriod>
	<sy:updateFrequency><?php echo esc_html( apply_filters( 'rss_update_frequency', '1' ) ); ?></sy:updateFrequency>
		<?php do_action( 'rss2_head' ); ?>
		<?php foreach ( $files as $file ) : ?>
		<item>
			<title><?php echo esc_html( wp_strip_all_tags( stripslashes( $file->file_name ) ) ); ?></title>
			<link><?php echo esc_url( WP_DownloadManager_File::download_url( $file->file_id, $file->file ) ); ?></link>
			<pubDate><?php echo esc_html( mysql2date( 'D, d M Y H:i:s +0000', gmdate( 'Y-m-d H:i:s', (int) $file->file_date ), false ) ); ?></pubDate>
			<category><![CDATA[<?php echo esc_html( self::category_name( $categories, $file->file_category ) ); ?>]]></category>
			<guid isPermaLink="false"><?php echo esc_url( get_option( 'home' ) . '/?dl_id=' . (int) $file->file_id ); ?></guid>
			<description><![CDATA[
				<?php echo wp_kses_post( stripslashes( $file->file_des ) ); ?><br /><br />
				<?php
				printf(
					/* translators: %s: file size. */
					esc_html__( 'File Size: %s', 'wp-downloadmanager' ),
					esc_html( WP_DownloadManager_File::format_size( $file->file_size ) )
				);
				?>
				<br />
				<?php
				printf(
					/* translators: %s: number of hits. */
					esc_html__( 'File Hits: %s', 'wp-downloadmanager' ),
					esc_html( number_format_i18n( (int) $file->file_hits ) )
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
		<?php
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
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $sortby is a column from WP_DownloadManager_File::sort_columns(), the allow list the rss.sortby setting is filtered through; an ORDER BY column cannot be bound.
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
			// runs the same allow list back over what comes out of them, plus the
			// handful of SVG elements the icons need.
			echo wp_kses( $output, self::allowed_html() );
			return;
		}

		return $output;
	}

	/**
	 * Files chosen by id and by category, embedded in a post.
	 *
	 * The renderer behind both the `[download]` shortcode and the
	 * `wp-downloadmanager/download` block. **Neither entry point is implemented
	 * in terms of the other**: both land here, which is what makes the two
	 * render identically, and what keeps the shortcode's positional
	 * `[download=1]` parsing out of a block that has no way to produce it.
	 *
	 * `$id` and `$category` are each one id or a comma-separated list of them,
	 * and each arrives as a string a user typed -- into a shortcode attribute
	 * or into the block's sidebar. They are normalised here for the same reason
	 * downloads_page() casts its category on entry: an empty string and a zero
	 * both have to mean "not given", or an attributeless block and an
	 * attributeless shortcode ask two different questions. Every surviving id
	 * goes through intval() before it reaches SQL.
	 *
	 * @param string|int $id           One file id, or several separated by commas.
	 * @param string|int $category     One category id, or several separated by commas.
	 * @param string     $display      'both' or 'name'.
	 * @param string     $sort_by      Sort column, validated against the allow list downstream.
	 * @param string     $sort_order   Sort direction.
	 * @param int        $stream_limit Cap outside a single post, 0 for no cap.
	 * @return string
	 */
	public static function render_download( $id = 0, $category = 0, $display = 'both', $sort_by = 'file_id', $sort_order = 'asc', $stream_limit = 0 ) {
		if ( is_feed() ) {
			return __( 'Note: There is a file embedded within this post, please visit this post to download the file.', 'wp-downloadmanager' );
		}

		$conditions = array();
		$id         = trim( (string) $id );
		$category   = trim( (string) $category );

		if ( '' !== $id && '0' !== $id ) {
			if ( false !== strpos( $id, ',' ) ) {
				$ids          = array_map( 'intval', explode( ',', $id ) );
				$conditions[] = 'file_id IN (' . implode( ',', $ids ) . ')';
			} else {
				$conditions[] = 'file_id = ' . (int) $id;
			}
		}
		if ( '' !== $category && '0' !== $category ) {
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
			$display,
			$sort_by,
			$sort_order,
			$stream_limit
		);
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

		$id = $attributes['id'];

		// Backward compatibility with [download=1]. Shortcode syntax with no
		// block equivalent, so it is parsed here rather than in the shared
		// renderer: a block has no positional attribute to produce it with.
		if ( ! $id && ! empty( $atts[0] ) ) {
			$id = trim( $atts[0], '="\'' );
		}

		return self::render_download(
			$id,
			$attributes['category'],
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
