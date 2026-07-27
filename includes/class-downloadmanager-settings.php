<?php
/**
 * The Download Options and Download Templates screens.
 *
 * Both were hand-rolled <form> + $_POST handlers before 2.0.0. They are now
 * Settings API screens writing to the one consolidated option row, which is why
 * they share a sanitize callback: register_setting() keys its arguments by
 * option name, so the last registration would otherwise win for both groups.
 * The callback merges whatever the submitted screen sent over the stored value
 * rather than replacing it, so saving one screen cannot blank the other.
 *
 * @package WP-DownloadManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the two settings screens.
 */
class DownloadManager_Settings {

	/**
	 * Settings group for the options screen.
	 *
	 * @var string
	 */
	const GROUP_OPTIONS = 'wp-downloadmanager-options';

	/**
	 * Settings group for the templates screen.
	 *
	 * @var string
	 */
	const GROUP_TEMPLATES = 'wp-downloadmanager-templates';

	/**
	 * Hook up.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register the option under both groups.
	 *
	 * @return void
	 */
	public static function register() {
		$args = array(
			'type'              => 'array',
			'sanitize_callback' => array( __CLASS__, 'sanitize' ),
			'default'           => DownloadManager_Options::defaults(),
		);

		register_setting( self::GROUP_OPTIONS, DownloadManager_Options::OPTION, $args );
		register_setting( self::GROUP_TEMPLATES, DownloadManager_Options::OPTION, $args );
	}

	/**
	 * Validate a submitted screen and merge it over the stored value.
	 *
	 * @param mixed $input Submitted values.
	 * @return array
	 */
	public static function sanitize( $input ) {
		DownloadManager_Options::flush();
		$values = DownloadManager_Options::all();

		if ( ! is_array( $input ) ) {
			return $values;
		}

		if ( isset( $input['path'] ) ) {
			$values['path']['dir'] = self::sanitize_download_path(
				isset( $input['path']['dir'] ) ? sanitize_text_field( $input['path']['dir'] ) : ''
			);
			$values['path']['url'] = isset( $input['path']['url'] ) ? esc_url_raw( $input['path']['url'] ) : '';
		}

		if ( isset( $input['page_url'] ) ) {
			$values['page_url'] = esc_url_raw( $input['page_url'] );
		}
		if ( isset( $input['method'] ) ) {
			$values['method'] = 1 === (int) $input['method'] ? 1 : 0;
		}
		if ( isset( $input['nice_permalink'] ) ) {
			$values['nice_permalink'] = 1 === (int) $input['nice_permalink'] ? 1 : 0;
		}
		if ( isset( $input['use_filename'] ) ) {
			$values['use_filename'] = 1 === (int) $input['use_filename'] ? 1 : 0;
		}

		if ( isset( $input['categories'] ) ) {
			$values['categories'] = self::sanitize_categories( $input['categories'] );
		}

		if ( isset( $input['sort'] ) ) {
			// The sort column has to come from the same list the query trusts, or
			// the screen offers a value the sanitizer silently rejects and the
			// setting reverts on the next load.
			$values['sort'] = array(
				'by'      => DownloadManager_File::sort_column(
					isset( $input['sort']['by'] ) ? $input['sort']['by'] : '',
					'file_name'
				),
				'order'   => DownloadManager_File::sort_order( isset( $input['sort']['order'] ) ? $input['sort']['order'] : '' ),
				'perpage' => max( 1, (int) ( isset( $input['sort']['perpage'] ) ? $input['sort']['perpage'] : 20 ) ),
				'group'   => ! empty( $input['sort']['group'] ) ? 1 : 0,
			);
		}

		if ( isset( $input['rss'] ) ) {
			$values['rss'] = array(
				'sortby' => DownloadManager_File::sort_column(
					isset( $input['rss']['sortby'] ) ? $input['rss']['sortby'] : '',
					'file_date'
				),
				'limit'  => max( 1, (int) ( isset( $input['rss']['limit'] ) ? $input['rss']['limit'] : 20 ) ),
			);
		}

		if ( isset( $input['templates'] ) && is_array( $input['templates'] ) ) {
			$values['templates'] = self::sanitize_templates( $input['templates'], $values['templates'] );
		}

		return $values;
	}

	/**
	 * Keep the downloads directory inside wp-content.
	 *
	 * @param string $path Submitted path.
	 * @return string
	 */
	protected static function sanitize_download_path( $path ) {
		$real_path    = realpath( $path );
		$real_content = realpath( WP_CONTENT_DIR );

		if ( false === $real_path || false === $real_content
			|| 0 !== strpos( $real_path . DIRECTORY_SEPARATOR, rtrim( $real_content, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR )
			|| false !== strpos( $path, '../' ) ) {
			add_settings_error(
				DownloadManager_Options::OPTION,
				'download_path',
				sprintf(
					/* translators: %s: the wp-content directory. */
					__( 'The download path has to start inside your wp-content folder, which is %s. It has been reset.', 'wp-downloadmanager' ),
					esc_html( WP_CONTENT_DIR )
				)
			);

			return WP_CONTENT_DIR;
		}

		return $path;
	}

	/**
	 * One category per line, index 0 reserved for the "all" label.
	 *
	 * @param string $raw Newline separated category names.
	 * @return array
	 */
	protected static function sanitize_categories( $raw ) {
		$categories = array( '' );

		foreach ( explode( "\n", sanitize_textarea_field( $raw ) ) as $category ) {
			$category = trim( $category );
			if ( '' !== $category ) {
				$categories[] = $category;
			}
		}

		return $categories;
	}

	/**
	 * Run the submitted templates through kses.
	 *
	 * @param array $input    Submitted templates.
	 * @param array $existing Stored templates.
	 * @return array
	 */
	protected static function sanitize_templates( $input, $existing ) {
		// The page footer template ships a search <form>, which wp_kses_post()
		// would strip.
		$form_tags          = wp_kses_allowed_html( 'post' );
		$form_tags['form']  = array(
			'action' => true,
			'class'  => true,
			'id'     => true,
			'method' => true,
			'name'   => true,
		);
		$form_tags['input'] = array(
			'class' => true,
			'id'    => true,
			'name'  => true,
			'type'  => true,
			'value' => true,
		);
		$paired             = DownloadManager_Templates::paired_keys();

		foreach ( DownloadManager_Templates::keys() as $key ) {
			if ( ! isset( $input[ $key ] ) ) {
				continue;
			}

			$allowed = 'footer' === $key ? $form_tags : wp_kses_allowed_html( 'post' );

			if ( in_array( $key, $paired, true ) ) {
				$existing[ $key ] = array(
					isset( $input[ $key ][0] ) ? wp_kses( trim( $input[ $key ][0] ), $allowed ) : '',
					isset( $input[ $key ][1] ) ? wp_kses( trim( $input[ $key ][1] ), $allowed ) : '',
				);
			} else {
				$existing[ $key ] = wp_kses( trim( $input[ $key ] ), $allowed );
			}
		}

		return $existing;
	}

	/**
	 * A field name inside the consolidated option.
	 *
	 * @param string $path Dot separated path.
	 * @return string
	 */
	protected static function name( $path ) {
		$name = DownloadManager_Options::OPTION;

		foreach ( explode( '.', $path ) as $segment ) {
			$name .= '[' . $segment . ']';
		}

		return $name;
	}

	/**
	 * The Download Options screen.
	 *
	 * @return void
	 */
	public static function render_options_page() {
		if ( ! current_user_can( 'manage_downloads' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-downloadmanager' ) );
		}

		$home          = get_option( 'home' );
		$categories    = (array) DownloadManager_Options::get( 'categories', array() );
		$category_text = '';
		foreach ( $categories as $category ) {
			if ( '' !== trim( (string) $category ) ) {
				$category_text .= $category . "\n";
			}
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Download Options', 'wp-downloadmanager' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP_OPTIONS ); ?>

				<h2><?php esc_html_e( 'Download Options', 'wp-downloadmanager' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="download_path"><?php esc_html_e( 'Download Path:', 'wp-downloadmanager' ); ?></label></th>
						<td>
							<input type="text" id="download_path" name="<?php echo esc_attr( self::name( 'path.dir' ) ); ?>" value="<?php echo esc_attr( DownloadManager_Options::get( 'path.dir' ) ); ?>" size="50" dir="ltr" class="regular-text" />
							<p class="description">
								<?php esc_html_e( 'The absolute path to the directory where all the files are stored (without trailing slash).', 'wp-downloadmanager' ); ?><br />
								<?php
								printf(
									/* translators: %s: the wp-content directory. */
									esc_html__( 'Due to security reasons, the path has to start inside your wp-content folder, which is %s', 'wp-downloadmanager' ),
									'<code>' . esc_html( WP_CONTENT_DIR ) . '</code>'
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="download_path_url"><?php esc_html_e( 'Download Path URL:', 'wp-downloadmanager' ); ?></label></th>
						<td>
							<input type="text" id="download_path_url" name="<?php echo esc_attr( self::name( 'path.url' ) ); ?>" value="<?php echo esc_attr( DownloadManager_Options::get( 'path.url' ) ); ?>" size="50" dir="ltr" class="regular-text" />
							<p class="description"><?php esc_html_e( 'The url to the directory where all the files are stored (without trailing slash).', 'wp-downloadmanager' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="download_page_url"><?php esc_html_e( 'Download Page URL:', 'wp-downloadmanager' ); ?></label></th>
						<td>
							<input type="text" id="download_page_url" name="<?php echo esc_attr( self::name( 'page_url' ) ); ?>" value="<?php echo esc_attr( DownloadManager_Options::get( 'page_url' ) ); ?>" size="50" dir="ltr" class="regular-text" />
							<p class="description"><?php esc_html_e( 'The url to the downloads page (without trailing slash).', 'wp-downloadmanager' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Download Nice Permalink:', 'wp-downloadmanager' ); ?></th>
						<td>
							<?php $nice = (int) DownloadManager_Options::get( 'nice_permalink', 1 ); ?>
							<label><input type="radio" name="<?php echo esc_attr( self::name( 'nice_permalink' ) ); ?>" value="1" <?php checked( 1, $nice ); ?> />
								<?php esc_html_e( 'Yes', 'wp-downloadmanager' ); ?>
								<br /><span dir="ltr">- <?php echo esc_html( $home ); ?>/download/1/</span>
								<br /><span dir="ltr">- <?php echo esc_html( $home ); ?>/download/filename.ext</span>
							</label><br />
							<label><input type="radio" name="<?php echo esc_attr( self::name( 'nice_permalink' ) ); ?>" value="0" <?php checked( 0, $nice ); ?> />
								<?php esc_html_e( 'No', 'wp-downloadmanager' ); ?>
								<br /><span dir="ltr">- <?php echo esc_html( $home ); ?>/?dl_id=1</span>
								<br /><span dir="ltr">- <?php echo esc_html( $home ); ?>/?dl_name=filename.ext</span>
							</label>
							<p class="description"><?php echo wp_kses_post( __( 'Change it to <strong>No</strong> when you encounter 404 error.', 'wp-downloadmanager' ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Use File Name Or File ID In Download URL?', 'wp-downloadmanager' ); ?></th>
						<td>
							<?php $use_filename = (int) DownloadManager_Options::get( 'use_filename', 0 ); ?>
							<label><input type="radio" name="<?php echo esc_attr( self::name( 'use_filename' ) ); ?>" value="0" <?php checked( 0, $use_filename ); ?> />
								<?php esc_html_e( 'File ID', 'wp-downloadmanager' ); ?>
								<br /><span dir="ltr">- <?php echo esc_html( $home ); ?>/download/1/</span>
								<br /><span dir="ltr">- <?php echo esc_html( $home ); ?>/?dl_id=1</span>
							</label><br />
							<label><input type="radio" name="<?php echo esc_attr( self::name( 'use_filename' ) ); ?>" value="1" <?php checked( 1, $use_filename ); ?> />
								<?php esc_html_e( 'File Name', 'wp-downloadmanager' ); ?>
								<br /><span dir="ltr">- <?php echo esc_html( $home ); ?>/download/filename.ext</span>
								<br /><span dir="ltr">- <?php echo esc_html( $home ); ?>/?dl_name=filename.ext</span>
							</label>
							<p class="description"><?php echo wp_kses_post( __( 'Change it to <strong>File ID</strong> when you encounter 404 error.', 'wp-downloadmanager' ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="download_method"><?php esc_html_e( 'Download Method:', 'wp-downloadmanager' ); ?></label></th>
						<td>
							<?php $method = (int) DownloadManager_Options::get( 'method', 1 ); ?>
							<select id="download_method" name="<?php echo esc_attr( self::name( 'method' ) ); ?>">
								<option value="0" <?php selected( 0, $method ); ?>><?php esc_html_e( 'Output File', 'wp-downloadmanager' ); ?></option>
								<option value="1" <?php selected( 1, $method ); ?>><?php esc_html_e( 'Redirect To File', 'wp-downloadmanager' ); ?></option>
							</select>
							<p class="description"><?php echo wp_kses_post( __( 'Change it to <strong>Redirect To File</strong> when you have problem with large files.', 'wp-downloadmanager' ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="download_categories"><?php esc_html_e( 'Download Categories:', 'wp-downloadmanager' ); ?></label></th>
						<td>
							<textarea id="download_categories" cols="30" rows="10" name="<?php echo esc_attr( self::name( 'categories' ) ); ?>"><?php echo esc_textarea( $category_text ); ?></textarea>
							<p class="description">
								<?php esc_html_e( 'Start each entry on a new line.', 'wp-downloadmanager' ); ?><br />
								<?php echo wp_kses_post( __( 'The <strong>first line</strong> will have a category id of <strong>1</strong>.', 'wp-downloadmanager' ) ); ?><br />
								<?php echo wp_kses_post( __( 'The <strong>2nd line</strong> will have a category id of <strong>2</strong>.', 'wp-downloadmanager' ) ); ?><br />
								<?php esc_html_e( 'And so on and so forth.', 'wp-downloadmanager' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Download Listing Options', 'wp-downloadmanager' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="download_sort_by"><?php esc_html_e( 'Sort Downloads By:', 'wp-downloadmanager' ); ?></label></th>
						<td>
							<select id="download_sort_by" name="<?php echo esc_attr( self::name( 'sort.by' ) ); ?>">
								<?php self::sort_column_options( DownloadManager_Options::get( 'sort.by' ) ); ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="download_sort_order"><?php esc_html_e( 'Sort Order Of Downloads:', 'wp-downloadmanager' ); ?></label></th>
						<td>
							<?php $order = DownloadManager_Options::get( 'sort.order' ); ?>
							<select id="download_sort_order" name="<?php echo esc_attr( self::name( 'sort.order' ) ); ?>">
								<option value="asc" <?php selected( 'asc', $order ); ?>><?php esc_html_e( 'Ascending', 'wp-downloadmanager' ); ?></option>
								<option value="desc" <?php selected( 'desc', $order ); ?>><?php esc_html_e( 'Descending', 'wp-downloadmanager' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="download_sort_perpage"><?php esc_html_e( 'No. Of Downloads Per Page:', 'wp-downloadmanager' ); ?></label></th>
						<td><input type="number" min="1" id="download_sort_perpage" name="<?php echo esc_attr( self::name( 'sort.perpage' ) ); ?>" value="<?php echo esc_attr( DownloadManager_Options::get( 'sort.perpage' ) ); ?>" class="small-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="download_sort_group"><?php esc_html_e( 'Group By:', 'wp-downloadmanager' ); ?></label></th>
						<td>
							<?php $group = (int) DownloadManager_Options::get( 'sort.group', 0 ); ?>
							<select id="download_sort_group" name="<?php echo esc_attr( self::name( 'sort.group' ) ); ?>">
								<option value="0" <?php selected( 0, $group ); ?>><?php esc_html_e( 'None', 'wp-downloadmanager' ); ?></option>
								<option value="1" <?php selected( 1, $group ); ?>><?php esc_html_e( 'Categories', 'wp-downloadmanager' ); ?></option>
							</select>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Download RSS Options', 'wp-downloadmanager' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="download_rss_sortby"><?php esc_html_e( 'Sort Downloads In Feed By:', 'wp-downloadmanager' ); ?></label></th>
						<td>
							<select id="download_rss_sortby" name="<?php echo esc_attr( self::name( 'rss.sortby' ) ); ?>">
								<?php self::sort_column_options( DownloadManager_Options::get( 'rss.sortby' ) ); ?>
							</select>
							<p class="description"><?php esc_html_e( 'Sorting are done in descending order.', 'wp-downloadmanager' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="download_rss_limit"><?php esc_html_e( 'No. Of Downloads In Feed:', 'wp-downloadmanager' ); ?></label></th>
						<td><input type="number" min="1" id="download_rss_limit" name="<?php echo esc_attr( self::name( 'rss.limit' ) ); ?>" value="<?php echo esc_attr( DownloadManager_Options::get( 'rss.limit' ) ); ?>" class="small-text" /></td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * The sort column options, labelled.
	 *
	 * Derived from the same allow list the query trusts, so the screen can never
	 * offer a value the sanitizer rejects.
	 *
	 * @param string $selected Currently selected column.
	 * @return void
	 */
	protected static function sort_column_options( $selected ) {
		$labels = array(
			'file_id'           => __( 'File ID', 'wp-downloadmanager' ),
			'file'              => __( 'File', 'wp-downloadmanager' ),
			'file_name'         => __( 'File Name', 'wp-downloadmanager' ),
			'file_size'         => __( 'File Size', 'wp-downloadmanager' ),
			'file_date'         => __( 'File Date', 'wp-downloadmanager' ),
			'file_updated_date' => __( 'File Last Updated Date', 'wp-downloadmanager' ),
			'file_hits'         => __( 'File Hits', 'wp-downloadmanager' ),
		);

		foreach ( DownloadManager_File::sort_columns() as $column ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>' . "\n",
				esc_attr( $column ),
				selected( $column, $selected, false ),
				esc_html( isset( $labels[ $column ] ) ? $labels[ $column ] : $column )
			);
		}
	}

	/**
	 * The per-template metadata the templates screen renders from.
	 *
	 * @return array
	 */
	protected static function template_fields() {
		$file_vars = array(
			'%FILE_ID%',
			'%FILE%',
			'%FILE_ICON%',
			'%FILE_NAME%',
			'%FILE_DESCRIPTION%',
			'%FILE_SIZE%',
			'%FILE_SIZE_DEC%',
			'%FILE_CATEGORY_ID%',
			'%FILE_CATEGORY_NAME%',
			'%FILE_DATE%',
			'%FILE_TIME%',
			'%FILE_UPDATED_DATE%',
			'%FILE_UPDATED_TIME%',
			'%FILE_HITS%',
			'%FILE_DOWNLOAD_URL%',
		);
		$page_vars = array(
			'%TOTAL_FILES_COUNT%',
			'%TOTAL_HITS%',
			'%TOTAL_SIZE%',
			'%TOTAL_SIZE_DEC%',
			'%CATEGORY_ID%',
			'%FILE_CATEGORY_NAME%',
			'%FILE_SEARCH_WORD%',
			'%DOWNLOAD_PAGE_URL%',
		);
		$cat_vars  = array(
			'%FILE_CATEGORY_NAME%',
			'%CATEGORY_ID%',
			'%CATEGORY_URL%',
			'%CATEGORY_FILES_COUNT%',
			'%CATEGORY_HITS%',
			'%CATEGORY_SIZE%',
			'%CATEGORY_SIZE_DEC%',
		);

		return array(
			__( 'Download Page Templates', 'wp-downloadmanager' )               => array(
				array(
					'key'   => 'header',
					'label' => __( 'Download Page Header:', 'wp-downloadmanager' ),
					'vars'  => array_merge( array( '%RECORD_START%', '%RECORD_END%' ), $page_vars ),
				),
				array(
					'key'   => 'footer',
					'label' => __( 'Download Page Footer:', 'wp-downloadmanager' ),
					'vars'  => $page_vars,
				),
				array(
					'key'   => 'pagingheader',
					'label' => __( 'Download Page Paging Header:', 'wp-downloadmanager' ),
					'vars'  => array(),
				),
				array(
					'key'   => 'pagingfooter',
					'label' => __( 'Download Page Paging Footer:', 'wp-downloadmanager' ),
					'vars'  => array(),
				),
			),
			__( 'No Files Found Templates', 'wp-downloadmanager' )              => array(
				array(
					'key'   => 'none',
					'label' => __( 'No Files Found:', 'wp-downloadmanager' ),
					'vars'  => array(),
				),
			),
			__( 'Download Category Templates', 'wp-downloadmanager' )           => array(
				array(
					'key'   => 'category_header',
					'label' => __( 'Download Category Header:', 'wp-downloadmanager' ),
					'vars'  => $cat_vars,
				),
				array(
					'key'   => 'category_footer',
					'label' => __( 'Download Category Footer:', 'wp-downloadmanager' ),
					'vars'  => $cat_vars,
				),
			),
			__( 'Download Templates (With Permission)', 'wp-downloadmanager' )   => array(
				array(
					'key'   => 'listing',
					'index' => 0,
					'label' => __( 'Download Listing:', 'wp-downloadmanager' ),
					'desc'  => __( 'Displayed when listing files in the downloads page and users have permission to download the file.', 'wp-downloadmanager' ),
					'vars'  => $file_vars,
				),
				array(
					'key'   => 'embedded',
					'index' => 0,
					'label' => __( 'Download Embedded File', 'wp-downloadmanager' ),
					'desc'  => __( 'Displayed when you embedded a file within a post or a page and users have permission to download the file.', 'wp-downloadmanager' ),
					'vars'  => $file_vars,
				),
			),
			__( 'Download Templates (Without Permission)', 'wp-downloadmanager' ) => array(
				array(
					'key'   => 'listing',
					'index' => 1,
					'label' => __( 'Download Listing:', 'wp-downloadmanager' ),
					'desc'  => __( 'Displayed when listing files in the downloads page and users DO NOT have permission to download the file.', 'wp-downloadmanager' ),
					'vars'  => $file_vars,
				),
				array(
					'key'   => 'embedded',
					'index' => 1,
					'label' => __( 'Download Embedded File', 'wp-downloadmanager' ),
					'desc'  => __( 'Displayed when you embedded a file within a post or a page and users DO NOT have permission to download the file.', 'wp-downloadmanager' ),
					'vars'  => $file_vars,
				),
			),
			__( 'Download Page Link Template', 'wp-downloadmanager' )           => array(
				array(
					'key'   => 'download_page_link',
					'label' => __( 'Download Page Link', 'wp-downloadmanager' ),
					'desc'  => __( 'This template is used to style the link to the Download Page, if you choose to display the Download Page Link in the Most Downloaded and Recent Downloads widget.', 'wp-downloadmanager' ),
					'vars'  => array( '%DOWNLOAD_PAGE_URL%' ),
				),
			),
			__( 'Download Stats Templates (With Permission)', 'wp-downloadmanager' ) => array(
				array(
					'key'   => 'most',
					'index' => 0,
					'label' => __( 'Most Downloaded', 'wp-downloadmanager' ),
					'desc'  => __( 'Displayed when listing most downloaded files, recent downloads and downloads by category, and users have permission to download the file.', 'wp-downloadmanager' ),
					'vars'  => $file_vars,
				),
			),
			__( 'Download Stats Templates (Without Permission)', 'wp-downloadmanager' ) => array(
				array(
					'key'   => 'most',
					'index' => 1,
					'label' => __( 'Most Downloaded', 'wp-downloadmanager' ),
					'desc'  => __( 'Displayed when listing most downloaded files, recent downloads and downloads by category, and users DO NOT have permission to download the file.', 'wp-downloadmanager' ),
					'vars'  => $file_vars,
				),
			),
		);
	}

	/**
	 * The Download Templates screen.
	 *
	 * @return void
	 */
	public static function render_templates_page() {
		if ( ! current_user_can( 'manage_downloads' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-downloadmanager' ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Download Templates', 'wp-downloadmanager' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP_TEMPLATES ); ?>

				<?php foreach ( self::template_fields() as $section => $fields ) : ?>
					<h2><?php echo esc_html( $section ); ?></h2>
					<table class="form-table" role="presentation">
						<?php foreach ( $fields as $field ) : ?>
							<?php
							$key    = $field['key'];
							$index  = isset( $field['index'] ) ? (int) $field['index'] : 0;
							$paired = in_array( $key, DownloadManager_Templates::paired_keys(), true );
							$name   = $paired
								? self::name( 'templates.' . $key ) . '[' . $index . ']'
								: self::name( 'templates.' . $key );
							$id     = 'download_template_' . $key . ( $paired && $index ? '_2' : '' );
							$reset  = $key . ( $paired && $index ? '_2' : '' );
							?>
							<tr>
								<th scope="row" style="width: 30%;">
									<label for="<?php echo esc_attr( $id ); ?>"><strong><?php echo esc_html( $field['label'] ); ?></strong></label>
									<?php if ( ! empty( $field['desc'] ) ) : ?>
										<p class="description"><?php echo esc_html( $field['desc'] ); ?></p>
									<?php endif; ?>
									<p class="description">
										<?php esc_html_e( 'Allowed Variables:', 'wp-downloadmanager' ); ?><br />
										<?php if ( empty( $field['vars'] ) ) : ?>
											- <?php esc_html_e( 'N/A', 'wp-downloadmanager' ); ?><br />
										<?php else : ?>
											<?php foreach ( $field['vars'] as $var ) : ?>
												- <?php echo esc_html( $var ); ?><br />
											<?php endforeach; ?>
										<?php endif; ?>
									</p>
									<button type="button" class="button download-template-reset" data-template="<?php echo esc_attr( $reset ); ?>" data-target="<?php echo esc_attr( $id ); ?>">
										<?php esc_html_e( 'Restore Default Template', 'wp-downloadmanager' ); ?>
									</button>
								</th>
								<td>
									<textarea cols="80" rows="12" class="large-text code" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"><?php echo esc_textarea( DownloadManager_Options::template( $key, $index ) ); ?></textarea>
								</td>
							</tr>
						<?php endforeach; ?>
					</table>
				<?php endforeach; ?>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
