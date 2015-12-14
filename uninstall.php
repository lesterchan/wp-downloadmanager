<?php
/*
 * Uninstall plugin
 */
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) )
    exit ();

$option_names = array(
    'download_path'
    , 'download_path_url'
    , 'download_page_url'
    , 'download_method'
    , 'download_categories'
    , 'download_sort'
    , 'download_template_header'
    , 'download_template_footer'
    , 'download_template_category_header'
    , 'download_template_category_footer'
    , 'download_template_listing'
    , 'download_template_embedded'
    , 'download_template_most'
    , 'download_template_pagingheader'
    , 'download_template_pagingfooter'
    , 'download_nice_permalink'
    , 'download_template_download_page_link'
    , 'download_template_none'
    , 'widget_download_most_downloaded'
    , 'widget_download_recent_downloads'
    , 'download_options'
    , 'widget_downloads'
);


if ( is_multisite() ) {
    $ms_sites = wp_get_sites();

    if( 0 < sizeof( $ms_sites ) ) {
        foreach ( $ms_sites as $ms_site ) {
            switch_to_blog( $ms_site['blog_id'] );
            if( sizeof( $option_names ) > 0 ) {
                foreach( $option_names as $option_name ) {
                    delete_option( $option_name );
                    plugin_uninstalled();
                }
            }
        }
    }

    restore_current_blog();
} else {
    if( sizeof( $option_names ) > 0 ) {
        foreach( $option_names as $option_name ) {
            delete_option( $option_name );
            plugin_uninstalled();
        }
    }
}

/**
 * Delete plugin table when uninstalled
 *
 * @access public
 * @return void
 */
function plugin_uninstalled() {
    global $wpdb;

    $table_names = array( 'downloads' );
    if( sizeof( $table_names ) > 0 ) {
        foreach( $table_names as $table_name ) {
            $table = $wpdb->prefix . $table_name;
            $wpdb->query( "DROP TABLE IF EXISTS $table" );
        }
    }
}