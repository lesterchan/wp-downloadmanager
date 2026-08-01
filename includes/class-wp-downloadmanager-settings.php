<?php
/**
 * The Settings screen.
 *
 * Download Options and Download Templates were two hand-rolled <form> + $_POST
 * screens with a menu entry each. Section 4.1 allows exactly one settings page
 * per plugin, so they are two tabs on one page now, and section 4.2 requires
 * both to be built entirely from the Settings API - there is no hand-written
 * form-table markup left in this file, because do_settings_sections() emits it.
 *
 * Both tabs write the same option row, so they share one sanitize callback:
 * register_setting() keys its arguments by option name, and the callback merges
 * whatever the submitted tab sent over the stored value rather than replacing
 * it, so saving one tab cannot blank the other.
 *
 * @package WP-DownloadManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the settings page.
 */
class WP_DownloadManager_Settings {

	/**
	 * Settings group. Always the same string as the option row it writes.
	 *
	 * @var string
	 */
	const GROUP = 'wp_downloadmanager_options';

	/**
	 * Paths, permalinks and categories.
	 *
	 * @var string
	 */
	const SECTION_GENERAL = 'wp_downloadmanager_general';

	/**
	 * How the downloads page lists and pages its files.
	 *
	 * @var string
	 */
	const SECTION_LISTING = 'wp_downloadmanager_listing';

	/**
	 * The downloads feed.
	 *
	 * @var string
	 */
	const SECTION_RSS = 'wp_downloadmanager_rss';

	/**
	 * What this plugin contributes to WP-Stats.
	 *
	 * @var string
	 */
	const SECTION_STATS = 'wp_downloadmanager_stats';

	/**
	 * Prefix for the eight template sections, which are generated rather than
	 * named one by one.
	 *
	 * @var string
	 */
	const SECTION_TEMPLATES = 'wp_downloadmanager_templates';

	/**
	 * Hook up.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	/**
	 * The two tabs, in the order they appear.
	 *
	 * @return array Tab slug => label.
	 */
	public static function tabs() {
		return array(
			'general'   => __( 'Settings', 'wp-downloadmanager' ),
			'templates' => __( 'Templates', 'wp-downloadmanager' ),
		);
	}

	/**
	 * The registry page slug one tab's sections are registered against.
	 *
	 * The settings group is shared - both tabs save the same option - so the
	 * tabs are told apart by the page they hand to add_settings_section() and
	 * do_settings_sections(), which is a separate registry from the group.
	 *
	 * @param string $tab Tab slug.
	 * @return string
	 */
	public static function tab_page( $tab ) {
		return self::GROUP . '_' . $tab;
	}

	/**
	 * The tab the request is asking for, constrained to the ones that exist.
	 *
	 * @return string
	 */
	public static function current_tab() {
		$tabs = self::tabs();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reads ?tab= to decide which tab to render, and the value is checked against self::tabs() below. The form itself is posted to options.php, which checks the settings nonce.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

		return isset( $tabs[ $tab ] ) ? $tab : 'general';
	}

	/**
	 * Register the option and everything on both tabs.
	 *
	 * @return void
	 */
	public static function register() {
		register_setting(
			self::GROUP,
			WP_DownloadManager_Options::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => WP_DownloadManager_Options::defaults(),
			)
		);

		self::register_fields();
	}

	/**
	 * Validate a submitted screen and merge it over the stored value.
	 *
	 * @param mixed $input Submitted values.
	 * @return array
	 */
	public static function sanitize( $input ) {
		WP_DownloadManager_Options::flush();
		$values = WP_DownloadManager_Options::all();

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

		// An unticked checkbox posts nothing at all, so "off" is indistinguishable
		// from "this tab was not submitted" unless something else on the tab
		// identifies it. page_url is on the Settings tab and always posts, so its
		// presence is what says the toggle beside it is authoritative.
		if ( isset( $input['page_url'] ) ) {
			$values['stats_display'] = empty( $input['stats_display'] ) ? 0 : 1;
		}
		if ( isset( $input['stats_most_limit'] ) ) {
			$values['stats_most_limit'] = max( 1, (int) $input['stats_most_limit'] );
		}

		if ( isset( $input['categories'] ) ) {
			$values['categories'] = self::sanitize_categories( $input['categories'] );
		}

		if ( isset( $input['sort'] ) ) {
			// The sort column has to come from the same list the query trusts, or
			// the screen offers a value the sanitizer silently rejects and the
			// setting reverts on the next load.
			$values['sort'] = array(
				'by'      => WP_DownloadManager_File::sort_column(
					isset( $input['sort']['by'] ) ? $input['sort']['by'] : '',
					'file_name'
				),
				'order'   => WP_DownloadManager_File::sort_order( isset( $input['sort']['order'] ) ? $input['sort']['order'] : '' ),
				'perpage' => max( 1, (int) ( isset( $input['sort']['perpage'] ) ? $input['sort']['perpage'] : 20 ) ),
				'group'   => ! empty( $input['sort']['group'] ) ? 1 : 0,
			);
		}

		if ( isset( $input['rss'] ) ) {
			$values['rss'] = array(
				'sortby' => WP_DownloadManager_File::sort_column(
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
		$path    = untrailingslashit( wp_normalize_path( trim( $path ) ) );
		$content = untrailingslashit( wp_normalize_path( WP_CONTENT_DIR ) );

		// realpath() resolves symlinks and .., but returns false for a directory
		// that does not exist yet. Falling back to the raw path there is what
		// stops a perfectly good setting being silently rewritten to wp-content
		// just because the directory has not been created - which is what the
		// old check did, on every save of this screen, to anyone whose downloads
		// directory was missing.
		$resolved = realpath( $path );
		$compare  = false !== $resolved ? untrailingslashit( wp_normalize_path( $resolved ) ) : $path;

		$escapes = false !== strpos( $path, '..' )
			|| '' === $path
			|| 0 !== strpos( $compare . '/', $content . '/' );

		if ( $escapes ) {
			add_settings_error(
				WP_DownloadManager_Options::OPTION,
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
		$paired             = WP_DownloadManager_Template::paired_keys();

		foreach ( WP_DownloadManager_Template::keys() as $key ) {
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
		$name = WP_DownloadManager_Options::OPTION;

		foreach ( explode( '.', $path ) as $segment ) {
			$name .= '[' . $segment . ']';
		}

		return $name;
	}

	/**
	 * Register every section and field with the Settings API.
	 *
	 * The field names are unchanged - they still post into the one consolidated
	 * option as download_options[sort][perpage] and so on - so the sanitize
	 * callback and the save path are exactly as before. What changes is that
	 * core lays the screens out from this registration rather than the class
	 * printing its own form-table markup.
	 *
	 * @return void
	 */
	public static function register_fields() {
		foreach ( self::option_sections() as $section => $spec ) {
			add_settings_section(
				$section,
				$spec['title'],
				isset( $spec['description'] ) ? $spec['description'] : '__return_false',
				self::tab_page( 'general' )
			);

			foreach ( $spec['fields'] as $field ) {
				add_settings_field(
					$field['id'],
					$field['label'],
					array( __CLASS__, 'render_field' ),
					self::tab_page( 'general' ),
					$section,
					// label_for makes core wrap the title in a <label>, which only
					// makes sense for the fields that are a single control.
					in_array( $field['type'], array( 'radio' ), true )
						? $field
						: array_merge( $field, array( 'label_for' => $field['id'] ) )
				);
			}
		}

		$index = 0;
		foreach ( self::template_fields() as $title => $fields ) {
			$section = self::SECTION_TEMPLATES . '_' . $index;
			++$index;

			add_settings_section( $section, $title, '__return_false', self::tab_page( 'templates' ) );

			foreach ( $fields as $field ) {
				$field['index']  = isset( $field['index'] ) ? (int) $field['index'] : 0;
				$paired          = in_array( $field['key'], WP_DownloadManager_Template::paired_keys(), true );
				$field['id']     = 'download_template_' . $field['key'] . ( $paired && $field['index'] ? '_2' : '' );
				$field['reset']  = $field['key'] . ( $paired && $field['index'] ? '_2' : '' );
				$field['name']   = $paired
					? self::name( 'templates.' . $field['key'] ) . '[' . $field['index'] . ']'
					: self::name( 'templates.' . $field['key'] );
				$field['paired'] = $paired;

				add_settings_field(
					$field['id'],
					$field['label'],
					array( __CLASS__, 'render_template_field' ),
					self::tab_page( 'templates' ),
					$section,
					array_merge( $field, array( 'label_for' => $field['id'] ) )
				);
			}
		}
	}

	/**
	 * The Download Options screen, as sections and fields.
	 *
	 * Declarative so the markup is generated once in render_field() rather than
	 * written out per control - which is what let the sort select and the
	 * sanitizer's allow list drift apart in the first place.
	 *
	 * @return array
	 */
	protected static function option_sections() {
		$home = get_option( 'home' );

		return array(
			self::SECTION_GENERAL => array(
				'title'  => __( 'General', 'wp-downloadmanager' ),
				'fields' => array(
					array(
						'id'    => 'download_path',
						'path'  => 'path.dir',
						'type'  => 'text',
						'label' => __( 'Download Path:', 'wp-downloadmanager' ),
						'desc'  => __( 'The absolute path to the directory where all the files are stored (without trailing slash).', 'wp-downloadmanager' ) . '<br />' . sprintf(
							/* translators: %s: the wp-content directory. */
							esc_html__( 'Due to security reasons, the path has to start inside your wp-content folder, which is %s', 'wp-downloadmanager' ),
							'<code>' . esc_html( WP_CONTENT_DIR ) . '</code>'
						),
					),
					array(
						'id'    => 'download_path_url',
						'path'  => 'path.url',
						'type'  => 'text',
						'label' => __( 'Download Path URL:', 'wp-downloadmanager' ),
						'desc'  => __( 'The url to the directory where all the files are stored (without trailing slash).', 'wp-downloadmanager' ),
					),
					array(
						'id'    => 'download_page_url',
						'path'  => 'page_url',
						'type'  => 'text',
						'label' => __( 'Download Page URL:', 'wp-downloadmanager' ),
						'desc'  => __( 'The url to the downloads page (without trailing slash).', 'wp-downloadmanager' ),
					),
					array(
						'id'      => 'download_nice_permalink',
						'path'    => 'nice_permalink',
						'type'    => 'radio',
						'label'   => __( 'Download Nice Permalink:', 'wp-downloadmanager' ),
						'choices' => array(
							1 => __( 'Yes', 'wp-downloadmanager' ) . '<br /><span dir="ltr">- ' . esc_html( $home ) . '/download/1/</span><br /><span dir="ltr">- ' . esc_html( $home ) . '/download/filename.ext</span>',
							0 => __( 'No', 'wp-downloadmanager' ) . '<br /><span dir="ltr">- ' . esc_html( $home ) . '/?dl_id=1</span><br /><span dir="ltr">- ' . esc_html( $home ) . '/?dl_name=filename.ext</span>',
						),
						'desc'    => __( 'Change it to <strong>No</strong> when you encounter 404 error.', 'wp-downloadmanager' ),
					),
					array(
						'id'      => 'download_use_filename',
						'path'    => 'use_filename',
						'type'    => 'radio',
						'label'   => __( 'Use File Name Or File ID In Download URL?', 'wp-downloadmanager' ),
						'choices' => array(
							0 => __( 'File ID', 'wp-downloadmanager' ) . '<br /><span dir="ltr">- ' . esc_html( $home ) . '/download/1/</span><br /><span dir="ltr">- ' . esc_html( $home ) . '/?dl_id=1</span>',
							1 => __( 'File Name', 'wp-downloadmanager' ) . '<br /><span dir="ltr">- ' . esc_html( $home ) . '/download/filename.ext</span><br /><span dir="ltr">- ' . esc_html( $home ) . '/?dl_name=filename.ext</span>',
						),
						'desc'    => __( 'Change it to <strong>File ID</strong> when you encounter 404 error.', 'wp-downloadmanager' ),
					),
					array(
						'id'      => 'download_method',
						'path'    => 'method',
						'type'    => 'select',
						'label'   => __( 'Download Method:', 'wp-downloadmanager' ),
						'choices' => array(
							0 => __( 'Output File', 'wp-downloadmanager' ),
							1 => __( 'Redirect To File', 'wp-downloadmanager' ),
						),
						'desc'    => __( 'Change it to <strong>Redirect To File</strong> when you have problem with large files.', 'wp-downloadmanager' ),
					),
					array(
						'id'    => 'download_categories',
						'path'  => 'categories',
						'type'  => 'categories',
						'label' => __( 'Download Categories:', 'wp-downloadmanager' ),
						'desc'  => __( 'Start each entry on a new line.', 'wp-downloadmanager' ) . '<br />' .
							__( 'The <strong>first line</strong> will have a category id of <strong>1</strong>.', 'wp-downloadmanager' ) . '<br />' .
							__( 'The <strong>2nd line</strong> will have a category id of <strong>2</strong>.', 'wp-downloadmanager' ) . '<br />' .
							__( 'And so on and so forth.', 'wp-downloadmanager' ),
					),
				),
			),
			self::SECTION_LISTING => array(
				'title'  => __( 'Listing', 'wp-downloadmanager' ),
				'fields' => array(
					array(
						'id'      => 'download_sort_by',
						'path'    => 'sort.by',
						'type'    => 'select',
						'label'   => __( 'Sort Downloads By:', 'wp-downloadmanager' ),
						'choices' => 'sort_columns',
					),
					array(
						'id'      => 'download_sort_order',
						'path'    => 'sort.order',
						'type'    => 'select',
						'label'   => __( 'Sort Order Of Downloads:', 'wp-downloadmanager' ),
						'choices' => array(
							'asc'  => __( 'Ascending', 'wp-downloadmanager' ),
							'desc' => __( 'Descending', 'wp-downloadmanager' ),
						),
					),
					array(
						'id'    => 'download_sort_perpage',
						'path'  => 'sort.perpage',
						'type'  => 'number',
						'label' => __( 'No. Of Downloads Per Page:', 'wp-downloadmanager' ),
					),
					array(
						'id'      => 'download_sort_group',
						'path'    => 'sort.group',
						'type'    => 'select',
						'label'   => __( 'Group By:', 'wp-downloadmanager' ),
						'choices' => array(
							0 => __( 'None', 'wp-downloadmanager' ),
							1 => __( 'Categories', 'wp-downloadmanager' ),
						),
					),
				),
			),
			self::SECTION_RSS     => array(
				'title'  => __( 'RSS', 'wp-downloadmanager' ),
				'fields' => array(
					array(
						'id'      => 'download_rss_sortby',
						'path'    => 'rss.sortby',
						'type'    => 'select',
						'label'   => __( 'Sort Downloads In Feed By:', 'wp-downloadmanager' ),
						'choices' => 'sort_columns',
						'desc'    => __( 'Sorting are done in descending order.', 'wp-downloadmanager' ),
					),
					array(
						'id'    => 'download_rss_limit',
						'path'  => 'rss.limit',
						'type'  => 'number',
						'label' => __( 'No. Of Downloads In Feed:', 'wp-downloadmanager' ),
					),
				),
			),
			self::SECTION_STATS   => array(
				'title'  => __( 'WP-Stats', 'wp-downloadmanager' ),
				'fields' => array(
					array(
						'id'    => 'download_stats_display',
						'path'  => 'stats_display',
						'type'  => 'checkbox',
						'label' => __( 'Show Downloads In WP-Stats:', 'wp-downloadmanager' ),
						'text'  => __( 'Add a downloads section to the WP-Stats page', 'wp-downloadmanager' ),
						'desc'  => __( 'These two settings used to live in WP-Stats\' own option rows, shared with six other plugins. They belong to this plugin now, and WP-Stats asks for the section rather than reading them. Nothing happens if WP-Stats is not installed.', 'wp-downloadmanager' ),
					),
					array(
						'id'    => 'download_stats_most_limit',
						'path'  => 'stats_most_limit',
						'type'  => 'number',
						'label' => __( 'No. Of Downloads In WP-Stats:', 'wp-downloadmanager' ),
					),
				),
			),
		);
	}

	/**
	 * Render one field of the Download Options screen.
	 *
	 * @param array $args Field spec, as registered.
	 * @return void
	 */
	public static function render_field( $args ) {
		$name    = self::name( $args['path'] );
		$value   = WP_DownloadManager_Options::get( $args['path'] );
		$choices = isset( $args['choices'] ) ? $args['choices'] : array();

		// The sort selects take their options from the same allow list the query
		// trusts, so the screen can never offer a value the sanitizer rejects.
		if ( 'sort_columns' === $choices ) {
			$choices = self::sort_column_labels();
		}

		switch ( $args['type'] ) {
			case 'number':
				printf(
					'<input type="number" min="1" id="%1$s" name="%2$s" value="%3$s" class="small-text" />',
					esc_attr( $args['id'] ),
					esc_attr( $name ),
					esc_attr( $value )
				);
				break;

			case 'select':
				printf( '<select id="%1$s" name="%2$s">', esc_attr( $args['id'] ), esc_attr( $name ) );
				foreach ( $choices as $choice => $label ) {
					printf(
						'<option value="%1$s"%2$s>%3$s</option>' . "\n",
						esc_attr( $choice ),
						selected( (string) $choice, (string) $value, false ),
						esc_html( $label )
					);
				}
				echo '</select>';
				break;

			case 'radio':
				foreach ( $choices as $choice => $label ) {
					printf(
						'<label><input type="radio" name="%1$s" value="%2$s"%3$s /> %4$s</label><br />',
						esc_attr( $name ),
						esc_attr( $choice ),
						checked( (string) $choice, (string) $value, false ),
						wp_kses_post( $label )
					);
				}
				break;

			case 'checkbox':
				printf(
					'<label><input type="checkbox" id="%1$s" name="%2$s" value="1"%3$s /> %4$s</label>',
					esc_attr( $args['id'] ),
					esc_attr( $name ),
					checked( (bool) $value, true, false ),
					esc_html( isset( $args['text'] ) ? $args['text'] : '' )
				);
				break;

			case 'categories':
				$text = '';
				foreach ( (array) $value as $category ) {
					if ( '' !== trim( (string) $category ) ) {
						$text .= $category . "\n";
					}
				}
				printf(
					'<textarea id="%1$s" name="%2$s" cols="30" rows="10">%3$s</textarea>',
					esc_attr( $args['id'] ),
					esc_attr( $name ),
					esc_textarea( $text )
				);
				break;

			default:
				printf(
					'<input type="text" id="%1$s" name="%2$s" value="%3$s" size="50" dir="ltr" class="regular-text" />',
					esc_attr( $args['id'] ),
					esc_attr( $name ),
					esc_attr( $value )
				);
		}

		if ( ! empty( $args['desc'] ) ) {
			printf( '<p class="description">%s</p>', wp_kses_post( $args['desc'] ) );
		}
	}

	/**
	 * Render one template textarea, with its variables and reset button.
	 *
	 * @param array $args Field spec, as registered.
	 * @return void
	 */
	public static function render_template_field( $args ) {
		printf(
			'<textarea cols="80" rows="12" class="large-text code" id="%1$s" name="%2$s">%3$s</textarea>',
			esc_attr( $args['id'] ),
			esc_attr( $args['name'] ),
			esc_textarea( WP_DownloadManager_Options::template( $args['key'], $args['index'] ) )
		);

		if ( ! empty( $args['desc'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['desc'] ) );
		}

		echo '<p class="description">' . esc_html__( 'Allowed Variables:', 'wp-downloadmanager' ) . '<br />';
		if ( empty( $args['vars'] ) ) {
			echo '- ' . esc_html__( 'N/A', 'wp-downloadmanager' ) . '<br />';
		} else {
			foreach ( $args['vars'] as $var ) {
				echo '- ' . esc_html( $var ) . '<br />';
			}
		}
		echo '</p>';

		printf(
			'<button type="button" class="button download-template-reset" data-template="%1$s" data-target="%2$s">%3$s</button>',
			esc_attr( $args['reset'] ),
			esc_attr( $args['id'] ),
			esc_html__( 'Restore Default Template', 'wp-downloadmanager' )
		);
	}

	/**
	 * The sort columns, labelled, in the order the allow list defines them.
	 *
	 * @return array
	 */
	protected static function sort_column_labels() {
		$labels = array(
			'file_id'           => __( 'File ID', 'wp-downloadmanager' ),
			'file'              => __( 'File', 'wp-downloadmanager' ),
			'file_name'         => __( 'File Name', 'wp-downloadmanager' ),
			'file_size'         => __( 'File Size', 'wp-downloadmanager' ),
			'file_date'         => __( 'File Date', 'wp-downloadmanager' ),
			'file_updated_date' => __( 'File Last Updated Date', 'wp-downloadmanager' ),
			'file_hits'         => __( 'File Hits', 'wp-downloadmanager' ),
		);

		$choices = array();
		foreach ( WP_DownloadManager_File::sort_columns() as $column ) {
			$choices[ $column ] = isset( $labels[ $column ] ) ? $labels[ $column ] : $column;
		}

		return $choices;
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
	 * The settings page: one page, two tabs, nothing hand-written.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( WP_DownloadManager_Admin::capability( 'settings' ) ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-downloadmanager' ), '', array( 'response' => 403 ) );
		}

		$current = self::current_tab();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Download Settings', 'wp-downloadmanager' ); ?></h1>
			<?php
			// A custom menu page has to render its own settings errors; WordPress
			// only does it automatically on the built-in Settings screens. Without
			// this a rejected value is corrected silently.
			settings_errors( WP_DownloadManager_Options::OPTION );
			?>
			<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Settings tabs', 'wp-downloadmanager' ); ?>">
				<?php foreach ( self::tabs() as $tab => $label ) : ?>
					<a class="nav-tab<?php echo $tab === $current ? ' nav-tab-active' : ''; ?>"
						href="<?php echo esc_url( add_query_arg( 'tab', $tab, WP_DownloadManager_Admin::screen_url( 'settings' ) ) ); ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>
			<form method="post" action="options.php">
				<?php
				settings_fields( self::GROUP );
				do_settings_sections( self::tab_page( $current ) );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
