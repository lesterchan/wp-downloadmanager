<?php
/**
 * The Downloads widget.
 *
 * @package WP-DownloadManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lists downloads by category, recency or popularity.
 */
class DownloadManager_Widget extends WP_Widget {

	/**
	 * Register the widget.
	 */
	public function __construct() {
		parent::__construct(
			'downloads',
			__( 'Downloads', 'wp-downloadmanager' ),
			array(
				'description'                 => __( 'WP-DownloadManager downloads statistics', 'wp-downloadmanager' ),
				'customize_selective_refresh' => true,
			)
		);
	}

	/**
	 * Hook up.
	 *
	 * @return void
	 */
	public static function init() {
		add_action(
			'widgets_init',
			static function () {
				register_widget( 'DownloadManager_Widget' );
			}
		);
	}

	/**
	 * Default instance values.
	 *
	 * @return array
	 */
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

	/**
	 * Render the widget.
	 *
	 * @param array $args     Sidebar arguments.
	 * @param array $instance Saved values.
	 * @return void
	 */
	public function widget( $args, $instance ) {
		$instance = wp_parse_args( (array) $instance, self::defaults() );

		$title   = apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base );
		$type    = $instance['type'];
		$limit   = (int) $instance['limit'];
		$chars   = (int) $instance['chars'];
		$cat_ids = explode( ',', (string) $instance['cat_ids'] );
		$link    = (int) $instance['link'];

		echo $args['before_widget'] . $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<ul>' . "\n";

		switch ( $type ) {
			case 'downloads_category':
				DownloadManager_Display::downloads_category( $cat_ids, $limit, $chars );
				break;
			case 'recent_downloads':
				DownloadManager_Display::recent_downloads( $limit, $chars );
				break;
			case 'most_downloaded':
				DownloadManager_Display::most_downloaded( $limit, $chars );
				break;
		}

		echo '</ul>' . "\n";

		if ( $link ) {
			$template = stripslashes( DownloadManager_Options::template( 'download_page_link' ) );
			echo str_replace( '%DOWNLOAD_PAGE_URL%', DownloadManager_Options::get( 'page_url' ), $template ); // phpcs:ignore WordPress.Security.EscapeOutput
		}

		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/**
	 * Save the widget form.
	 *
	 * The old "did the hidden submit marker come back?" guard returned false
	 * here, which discarded every edit made in the block widget editor and the
	 * customizer - neither of them posts that field.
	 *
	 * @param array $new_instance Submitted values.
	 * @param array $old_instance Previously saved values.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$new_instance = wp_parse_args( (array) $new_instance, (array) $old_instance );
		$instance     = (array) $old_instance;

		$instance['title']   = wp_strip_all_tags( $new_instance['title'] );
		$instance['type']    = wp_strip_all_tags( $new_instance['type'] );
		$instance['limit']   = (int) $new_instance['limit'];
		$instance['chars']   = (int) $new_instance['chars'];
		$instance['cat_ids'] = wp_strip_all_tags( $new_instance['cat_ids'] );
		$instance['link']    = (int) $new_instance['link'];

		unset( $instance['submit'], $instance['mode'] );

		return $instance;
	}

	/**
	 * The widget form.
	 *
	 * @param array $instance Saved values.
	 * @return void
	 */
	public function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, self::defaults() );

		$title   = $instance['title'];
		$type    = $instance['type'];
		$limit   = (int) $instance['limit'];
		$chars   = (int) $instance['chars'];
		$cat_ids = $instance['cat_ids'];
		$link    = (int) $instance['link'];
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'wp-downloadmanager' ); ?>
				<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
			</label>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'type' ) ); ?>"><?php esc_html_e( 'Statistics Type:', 'wp-downloadmanager' ); ?>
				<select name="<?php echo esc_attr( $this->get_field_name( 'type' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'type' ) ); ?>" class="widefat">
					<option value="downloads_category"<?php selected( 'downloads_category', $type ); ?>><?php esc_html_e( 'Display Downloads In Category', 'wp-downloadmanager' ); ?></option>
					<option value="recent_downloads"<?php selected( 'recent_downloads', $type ); ?>><?php esc_html_e( 'Recent Downloads', 'wp-downloadmanager' ); ?></option>
					<option value="most_downloaded"<?php selected( 'most_downloaded', $type ); ?>><?php esc_html_e( 'Most Downloaded', 'wp-downloadmanager' ); ?></option>
				</select>
			</label>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>"><?php esc_html_e( 'No. Of Records To Show:', 'wp-downloadmanager' ); ?>
				<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'limit' ) ); ?>" type="text" value="<?php echo esc_attr( $limit ); ?>" />
			</label>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'chars' ) ); ?>"><?php esc_html_e( 'Maximum Title Length (Characters):', 'wp-downloadmanager' ); ?>
				<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'chars' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'chars' ) ); ?>" type="text" value="<?php echo esc_attr( $chars ); ?>" />
			</label><br />
			<small><?php echo wp_kses_post( __( '<strong>0</strong> to disable.', 'wp-downloadmanager' ) ); ?></small>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'cat_ids' ) ); ?>"><?php esc_html_e( 'Category IDs:', 'wp-downloadmanager' ); ?> <span style="color: red;">*</span>
				<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'cat_ids' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'cat_ids' ) ); ?>" type="text" value="<?php echo esc_attr( $cat_ids ); ?>" />
			</label><br />
			<small><?php esc_html_e( 'Seperate mutiple categories with commas.', 'wp-downloadmanager' ); ?></small>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'link' ) ); ?>"><?php esc_html_e( 'Display Link To Download Page?', 'wp-downloadmanager' ); ?>
				<select name="<?php echo esc_attr( $this->get_field_name( 'link' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'link' ) ); ?>" class="widefat">
					<?php // These compared against $type rather than $link, so the saved value never rendered as selected. ?>
					<option value="0"<?php selected( 0, $link ); ?>><?php esc_html_e( 'No', 'wp-downloadmanager' ); ?></option>
					<option value="1"<?php selected( 1, $link ); ?>><?php esc_html_e( 'Yes', 'wp-downloadmanager' ); ?></option>
				</select>
			</label>
		</p>
		<p style="color: red;">
			<small><?php esc_html_e( '* If you are not using any category statistics, you can ignore it.', 'wp-downloadmanager' ); ?></small>
		</p>
		<?php
	}
}
