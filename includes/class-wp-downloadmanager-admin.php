<?php
/**
 * Admin wiring for WP-WP_DownloadManager.
 *
 * @package WP-WP_DownloadManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * The Downloads menu, its assets and the editor buttons.
 */
class WP_DownloadManager_Admin {

	/**
	 * Slug of the top-level menu, and of the first screen under it.
	 *
	 * @var string
	 */
	const PAGE = 'wp-downloadmanager';

	/**
	 * Capability guarding the data screens.
	 *
	 * The plugin has granted administrators manage_downloads since its first
	 * release, which is what lets a site hand the downloads library to an editor
	 * without handing over the whole dashboard. Settings stay on manage_options
	 * per section 2.7; capability() is where the two are told apart.
	 *
	 * @var string
	 */
	const CAPABILITY = 'manage_downloads';

	/**
	 * The capability required to reach one of the plugin's screens.
	 *
	 * @param string $context Screen context: 'downloads' or 'settings'.
	 * @return string
	 */
	public static function capability( $context = 'downloads' ) {
		/**
		 * Filters the capability a WP-DownloadManager screen requires.
		 *
		 * @since 2.0.0
		 *
		 * @param string $capability Capability name.
		 * @param string $context    Screen context: 'downloads' or 'settings'.
		 */
		return apply_filters(
			'wp_downloadmanager_capability',
			'settings' === $context ? 'manage_options' : self::CAPABILITY,
			$context
		);
	}

	/**
	 * Hook up.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'init', array( __CLASS__, 'editor_buttons' ) );

		foreach ( array( 'post-new.php', 'post.php', 'page-new.php', 'page.php' ) as $screen ) {
			add_action( 'admin_footer-' . $screen, array( __CLASS__, 'quicktag' ) );
		}
	}

	/**
	 * The admin pages this plugin owns.
	 *
	 * The menu uses the legacy "plugin file as menu slug" form, so the directory
	 * name ends up inside page URLs and in the hook suffix WordPress hands back
	 * to admin_enqueue_scripts. Both are derived from WP_DOWNLOADMANAGER_SLUG so
	 * the plugin keeps working under any directory name, and both come from this
	 * one method so the menu and the asset loader cannot drift apart - which is
	 * how the old code ended up listing a download-uninstall.php that had not
	 * existed for years.
	 *
	 * @return array Page slug => file name.
	 */
	public static function pages() {
		return array(
			'manager'   => WP_DOWNLOADMANAGER_SLUG . '/includes/screen-manage.php',
			'add'       => WP_DOWNLOADMANAGER_SLUG . '/includes/screen-add.php',
			'options'   => 'wp-downloadmanager-options',
			'templates' => 'wp-downloadmanager-templates',
		);
	}

	/**
	 * Register the Downloads menu.
	 *
	 * @return void
	 */
	public static function menu() {
		$pages = self::pages();

		add_menu_page(
			__( 'Downloads', 'wp-downloadmanager' ),
			__( 'Downloads', 'wp-downloadmanager' ),
			'manage_downloads',
			$pages['manager'],
			'',
			'dashicons-download'
		);

		add_submenu_page(
			$pages['manager'],
			__( 'Manage Downloads', 'wp-downloadmanager' ),
			__( 'Manage Downloads', 'wp-downloadmanager' ),
			'manage_downloads',
			$pages['manager']
		);
		add_submenu_page(
			$pages['manager'],
			__( 'Add File', 'wp-downloadmanager' ),
			__( 'Add File', 'wp-downloadmanager' ),
			'manage_downloads',
			$pages['add']
		);
		add_submenu_page(
			$pages['manager'],
			__( 'Download Options', 'wp-downloadmanager' ),
			__( 'Download Options', 'wp-downloadmanager' ),
			'manage_downloads',
			$pages['options'],
			array( 'WP_DownloadManager_Settings', 'render_options_page' )
		);
		add_submenu_page(
			$pages['manager'],
			__( 'Download Templates', 'wp-downloadmanager' ),
			__( 'Download Templates', 'wp-downloadmanager' ),
			'manage_downloads',
			$pages['templates'],
			array( 'WP_DownloadManager_Settings', 'render_templates_page' )
		);
	}

	/**
	 * The templates screen's script.
	 *
	 * There was an admin stylesheet here too, enqueued on all four screens. It
	 * had been a zero-byte file since the plugin's first commit, so every one of
	 * those screens paid for a request that delivered nothing.
	 *
	 * @param string $hook_suffix Current admin page.
	 * @return void
	 */
	public static function enqueue_assets( $hook_suffix ) {
		$pages = self::pages();

		// The templates screen is the only one of the four with an asset of its
		// own. It is registered with a callback, so add_submenu_page() hands back
		// a hook suffix of the form "downloads_page_<slug>" rather than the bare
		// file path the two legacy screens get.
		if ( false === strpos( $hook_suffix, $pages['templates'] ) ) {
			return;
		}

		wp_enqueue_script(
			'wp-downloadmanager-admin',
			WP_DOWNLOADMANAGER_URL . 'js/wp-downloadmanager-admin.js',
			array(),
			WP_DOWNLOADMANAGER_VERSION,
			true
		);

		// The reset buttons read the stock markup from here rather than from a
		// duplicated copy inside the script, which is how the two used to drift.
		wp_localize_script( 'wp-downloadmanager-admin', 'wpDownloadManagerL10n', self::script_data() );
	}

	/**
	 * The Text tab quicktag.
	 *
	 * @return void
	 */
	public static function quicktag() {
		wp_enqueue_script(
			'wp-downloadmanager-quicktag',
			WP_DOWNLOADMANAGER_URL . 'js/wp-downloadmanager-quicktag.js',
			array( 'quicktags' ),
			WP_DOWNLOADMANAGER_VERSION,
			true
		);

		wp_localize_script( 'wp-downloadmanager-quicktag', 'wpDownloadManagerL10n', self::script_data() );
	}

	/**
	 * Everything the admin scripts read, in one localised object.
	 *
	 * Section 6 of the standard gives every plugin exactly one localised global,
	 * named after the class prefix, so the two scripts share this payload rather
	 * than each inventing a name of its own.
	 *
	 * @return array
	 */
	public static function script_data() {
		return array(
			'templates' => WP_DownloadManager_Template::for_script(),
			'quicktag'  => array(
				'label'  => __( 'Download', 'wp-downloadmanager' ),
				'prompt' => __( 'Enter File ID (Separate Multiple IDs By A Comma)', 'wp-downloadmanager' ),
			),
		);
	}

	/**
	 * The TinyMCE button.
	 *
	 * @return void
	 */
	public static function editor_buttons() {
		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'edit_pages' ) ) {
			return;
		}
		if ( 'true' !== get_user_option( 'rich_editing' ) ) {
			return;
		}

		add_filter( 'mce_external_plugins', array( __CLASS__, 'mce_plugin' ) );
		add_filter( 'mce_buttons', array( __CLASS__, 'mce_button' ) );
		add_filter( 'wp_mce_translation', array( __CLASS__, 'mce_translation' ) );
	}

	/**
	 * Register the TinyMCE plugin script.
	 *
	 * One unminified file: there is no build step here, so a hand-minified twin
	 * only drifts out of sync.
	 *
	 * @param array $plugins TinyMCE plugins.
	 * @return array
	 */
	public static function mce_plugin( $plugins ) {
		$plugins['downloadmanager'] = WP_DOWNLOADMANAGER_URL . 'tinymce/plugins/downloadmanager/plugin.js?v=' . WP_DOWNLOADMANAGER_VERSION;

		return $plugins;
	}

	/**
	 * Add the button to the toolbar.
	 *
	 * @param array $buttons TinyMCE buttons.
	 * @return array
	 */
	public static function mce_button( $buttons ) {
		array_push( $buttons, 'separator', 'downloadmanager' );

		return $buttons;
	}

	/**
	 * Translate the button's strings.
	 *
	 * @param array $translations TinyMCE translations.
	 * @return array
	 */
	public static function mce_translation( $translations ) {
		$translations['Enter File ID (Separate Multiple IDs By A Comma)'] = __( 'Enter File ID (Separate Multiple IDs By A Comma)', 'wp-downloadmanager' );
		$translations['Insert File Download']                             = __( 'Insert File Download', 'wp-downloadmanager' );

		return $translations;
	}

	/**
	 * List the files in the downloads directory as option tags.
	 *
	 * @param string $dir      Directory to scan.
	 * @param string $base_dir Downloads root, stripped from the labels.
	 * @param string $selected Currently selected file.
	 * @return void
	 */
	public static function print_files( $dir, $base_dir, $selected = '' ) {
		$files      = array();
		$subfolders = array();

		self::collect_files( $dir, $base_dir, $files, $subfolders );

		natcasesort( $files );
		natcasesort( $subfolders );

		foreach ( array_merge( $files, $subfolders ) as $file ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>' . "\n",
				esc_attr( $file ),
				selected( $file, $selected, false ),
				esc_html( $file )
			);
		}
	}

	/**
	 * List the folders in the downloads directory as option tags.
	 *
	 * @param string $dir      Directory to scan.
	 * @param string $base_dir Downloads root, stripped from the labels.
	 * @return void
	 */
	public static function print_folders( $dir, $base_dir ) {
		$folders = array();

		self::collect_folders( $dir, $base_dir, $folders );

		natcasesort( $folders );

		echo '<option value="/">/</option>' . "\n";
		foreach ( $folders as $folder ) {
			printf( '<option value="%1$s">%2$s</option>' . "\n", esc_attr( $folder ), esc_html( $folder ) );
		}
	}

	/**
	 * Recursively collect files, split by nesting depth.
	 *
	 * @param string $dir        Directory to scan.
	 * @param string $base_dir   Downloads root.
	 * @param array  $files      Top level files, by reference.
	 * @param array  $subfolders Nested files, by reference.
	 * @return void
	 */
	protected static function collect_files( $dir, $base_dir, &$files, &$subfolders ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		foreach ( (array) scandir( $dir ) as $file ) {
			if ( '.' === $file || '..' === $file || '.htaccess' === $file ) {
				continue;
			}

			if ( is_dir( $dir . '/' . $file ) ) {
				self::collect_files( $dir . '/' . $file, $base_dir, $files, $subfolders );
				continue;
			}

			$relative = str_replace( $base_dir, '', $dir . '/' . $file );
			if ( count( explode( '/', $relative ) ) > 2 ) {
				$subfolders[] = $relative;
			} else {
				$files[] = $relative;
			}
		}
	}

	/**
	 * Recursively collect folders.
	 *
	 * @param string $dir      Directory to scan.
	 * @param string $base_dir Downloads root.
	 * @param array  $folders  Collected folders, by reference.
	 * @return void
	 */
	protected static function collect_folders( $dir, $base_dir, &$folders ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		foreach ( (array) scandir( $dir ) as $file ) {
			if ( '.' === $file || '..' === $file ) {
				continue;
			}
			if ( ! is_dir( $dir . '/' . $file ) ) {
				continue;
			}

			$folders[] = str_replace( $base_dir, '', $dir . '/' . $file );
			self::collect_folders( $dir . '/' . $file, $base_dir, $folders );
		}
	}

	/**
	 * The editable file timestamp selects.
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return void
	 */
	public static function file_timestamp( $timestamp ) {
		global $wp_locale;

		$fields = array(
			'day'    => array( 'j', 1, 31 ),
			'month'  => array( 'n', 1, 12 ),
			'year'   => array( 'Y', 2000, (int) gmdate( 'Y' ) ),
			'hour'   => array( 'H', 0, 23 ),
			'minute' => array( 'i', 0, 59 ),
			'second' => array( 's', 0, 60 ),
		);

		$separators = array(
			'day'    => '&nbsp;&nbsp;',
			'month'  => '&nbsp;&nbsp;',
			'year'   => '&nbsp;@' . "\n" . '<span dir="ltr">',
			'hour'   => '&nbsp;:',
			'minute' => '&nbsp;:',
			'second' => '</span>',
		);

		foreach ( $fields as $name => $spec ) {
			list( $format, $min, $max ) = $spec;
			$current                    = (int) gmdate( $format, $timestamp );

			printf( '<select id="file_timestamp_%1$s" name="file_timestamp_%1$s" size="1">' . "\n", esc_attr( $name ) );
			for ( $i = $min; $i <= $max; $i++ ) {
				$label = $i;
				if ( 'month' === $name ) {
					$label = $wp_locale->get_month( zeroise( $i, 2 ) );
				}
				printf(
					'<option value="%1$d"%2$s>%3$s</option>' . "\n",
					(int) $i,
					selected( $i, $current, false ),
					esc_html( $label )
				);
			}
			echo '</select>';
			echo wp_kses_post( $separators[ $name ] ) . "\n";
		}
	}
}
