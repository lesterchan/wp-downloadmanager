<?php
/**
 * The Downloads list table.
 *
 * The Manage Downloads screen used to be nine hundred lines of procedural
 * markup at the plugin root: its own <table class="widefat">, its own paging
 * links, its own sort form and its own alternating-row bookkeeping. All of that
 * is core's job, and core does it better - keyboard-accessible sort links,
 * screen-reader text on the row actions, a search box that keeps the rest of
 * the query string, and per-user "items per page" preferences.
 *
 * @package WP-DownloadManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lists the rows of the downloads table.
 */
class WP_DownloadManager_List_Table extends WP_List_Table {

	/**
	 * Set the singular and plural nouns core uses in its own strings.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'download',
				'plural'   => 'downloads',
				'ajax'     => false,
			)
		);
	}

	/**
	 * The columns, in display order.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'              => '<input type="checkbox" />',
			'file_name'       => __( 'File', 'wp-downloadmanager' ),
			'file_size'       => __( 'Size', 'wp-downloadmanager' ),
			'file_hits'       => __( 'Hits', 'wp-downloadmanager' ),
			'file_permission' => __( 'Permission', 'wp-downloadmanager' ),
			'file_category'   => __( 'Category', 'wp-downloadmanager' ),
			'file_date'       => __( 'Added', 'wp-downloadmanager' ),
		);
	}

	/**
	 * Which columns sort, and which one the table is sorted by to begin with.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'file_name'       => array( 'file_name', true ),
			'file_size'       => array( 'file_size', false ),
			'file_hits'       => array( 'file_hits', false ),
			'file_permission' => array( 'file_permission', false ),
			'file_category'   => array( 'file_category', false ),
			'file_date'       => array( 'file_date', false ),
		);
	}

	/**
	 * The column row actions hang off.
	 *
	 * @return string
	 */
	protected function get_default_primary_column_name() {
		return 'file_name';
	}

	/**
	 * The nonce action guarding the bulk form.
	 *
	 * WP_List_Table::display_tablenav() prints wp_nonce_field() for this action
	 * itself, in a field named _wpnonce. Printing a second _wpnonce beside it --
	 * which this screen used to do -- does not add a check, it replaces one: PHP
	 * keeps the last field of a repeated name, so the plugin's own nonce was
	 * discarded and check_admin_referer() was handed one for an action it was not
	 * checking. Verify the one core actually emits.
	 *
	 * @return string
	 */
	public function bulk_nonce_action() {
		return 'bulk-' . $this->_args['plural'];
	}

	/**
	 * Destructive operations available on a selection.
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		return array( 'delete' => __( 'Delete', 'wp-downloadmanager' ) );
	}

	/**
	 * Shown instead of an empty table.
	 *
	 * @return void
	 */
	public function no_items() {
		esc_html_e( 'No files found.', 'wp-downloadmanager' );
	}

	/**
	 * The columns the SQL is allowed to sort by.
	 *
	 * The request decides the ORDER BY clause, so it needs an allow list rather
	 * than sanitize_text_field(). The list itself belongs to the downloads table
	 * rather than to this screen, because the WP-CLI command sorts by the same
	 * columns and WP_List_Table lives in wp-admin, where a command cannot reach
	 * it.
	 *
	 * @return array
	 */
	public static function sortable_sql_columns() {
		return WP_DownloadManager_Download::sortable_columns();
	}

	/**
	 * Query the page of rows core is about to render.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		$per_page = $this->get_items_per_page( 'wp_downloadmanager_per_page', WP_DownloadManager_Admin::PER_PAGE );
		$paged    = max( 1, (int) $this->get_pagenum() );

		$args = array(
			'search'  => (string) $this->get_search(),
			'orderby' => $this->request( 'orderby' ),
			'order'   => $this->request( 'order' ),
			'number'  => $per_page,
			'offset'  => ( $paged - 1 ) * $per_page,
		);

		$total       = WP_DownloadManager_Download::count( $args );
		$this->items = WP_DownloadManager_Download::query( $args );

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			)
		);
	}

	/**
	 * One request value, unslashed and sanitized.
	 *
	 * The nonce lives on the bulk-action form; sorting, paging and searching are
	 * reads that change nothing, so they carry none.
	 *
	 * @param string $key Query argument name.
	 * @return string
	 */
	protected function request( $key ) {
		return isset( $_REQUEST[ $key ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ $key ] ) ) : '';
	}

	/**
	 * The current search term.
	 *
	 * @return string
	 */
	public function get_search() {
		return $this->request( 's' );
	}

	/**
	 * The bulk checkbox for one row.
	 *
	 * @param object $item Row from the downloads table.
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="file_ids[]" value="%1$d" /><span class="screen-reader-text">%2$s</span>',
			(int) $item->file_id,
			esc_html( sprintf( /* translators: %s: file name. */ __( 'Select %s', 'wp-downloadmanager' ), wp_strip_all_tags( $item->file_name ) ) )
		);
	}

	/**
	 * The primary column: name, path, dates and the row actions.
	 *
	 * @param object $item Row from the downloads table.
	 * @return string
	 */
	public function column_file_name( $item ) {
		$file_id = (int) $item->file_id;

		// The display name is the one field allowed to carry markup: it is stored
		// through wp_kses_post() and rendered with the same allow list, rather
		// than escaped a second time.
		$out  = '<strong>' . wp_kses_post( stripslashes( $item->file_name ) ) . '</strong>';
		$out .= '<br /><code dir="ltr">' . esc_html( WP_DownloadManager_Display::snippet_text( stripslashes( $item->file ), 45 ) ) . '</code>';
		$out .= '<br /><span class="description">' . esc_html(
			sprintf(
				/* translators: 1: time the file was last updated, 2: date it was last updated. */
				__( 'Last updated: %1$s, %2$s', 'wp-downloadmanager' ),
				mysql2date( get_option( 'time_format' ), gmdate( 'Y-m-d H:i:s', (int) $item->file_updated_date ) ),
				mysql2date( get_option( 'date_format' ), gmdate( 'Y-m-d H:i:s', (int) $item->file_updated_date ) )
			)
		) . '</span>';
		$out .= '<br /><span class="description">' . esc_html(
			sprintf(
				/* translators: 1: time the file was last downloaded, 2: date it was last downloaded. */
				__( 'Last downloaded: %1$s, %2$s', 'wp-downloadmanager' ),
				mysql2date( get_option( 'time_format' ), gmdate( 'Y-m-d H:i:s', (int) $item->file_last_downloaded_date ) ),
				mysql2date( get_option( 'date_format' ), gmdate( 'Y-m-d H:i:s', (int) $item->file_last_downloaded_date ) )
			)
		) . '</span>';

		$actions = array(
			'edit'   => sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( WP_DownloadManager_Admin::screen_url( 'edit', $file_id ) ),
				esc_html__( 'Edit', 'wp-downloadmanager' )
			),
			'delete' => sprintf(
				'<a class="submitdelete" href="%1$s">%2$s</a>',
				esc_url( WP_DownloadManager_Admin::screen_url( 'delete', $file_id ) ),
				esc_html__( 'Delete', 'wp-downloadmanager' )
			),
		);

		return $out . $this->row_actions( $actions );
	}

	/**
	 * Every other column.
	 *
	 * @param object $item        Row from the downloads table.
	 * @param string $column_name Column key.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'file_size':
				return esc_html( WP_DownloadManager_File::format_size( $item->file_size ) );

			case 'file_hits':
				return esc_html( number_format_i18n( (int) $item->file_hits ) );

			case 'file_permission':
				return esc_html( WP_DownloadManager_File::permission_label( $item->file_permission ) );

			case 'file_category':
				$name = WP_DownloadManager_Display::category_name(
					(array) WP_DownloadManager_Options::get( 'categories', array() ),
					$item->file_category
				);

				return esc_html( '' !== $name ? $name : __( 'N/A', 'wp-downloadmanager' ) );

			case 'file_date':
				return esc_html(
					sprintf(
						/* translators: 1: time of day, 2: date. */
						__( '%1$s, %2$s', 'wp-downloadmanager' ),
						mysql2date( get_option( 'time_format' ), gmdate( 'Y-m-d H:i:s', (int) $item->file_date ) ),
						mysql2date( get_option( 'date_format' ), gmdate( 'Y-m-d H:i:s', (int) $item->file_date ) )
					)
				);
		}

		return '';
	}
}
