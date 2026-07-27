<?php
/**
 * Plugin Name: WP-DownloadManager
 * Plugin URI: https://lesterchan.net/portfolio/programming/php/
 * Description: Adds a simple download manager to your WordPress blog.
 * Version: 2.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Lester 'GaMerZ' Chan
 * Author URI: https://lesterchan.net
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-downloadmanager
 * Domain Path: /languages
 *
 * @package WP-DownloadManager
 */

/*
	Copyright 2026 Lester Chan  (email : lesterchan@gmail.com)

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


// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


// Version.
define( 'WP_DOWNLOADMANAGER_VERSION', '2.0.0' );
define( 'WP_DOWNLOADMANAGER_MAIN_FILE', __FILE__ );

// Paths. Derived from this file so the plugin keeps working if its directory is
// renamed. WP_DOWNLOADMANAGER_SLUG is that directory name on its own: the admin
// menu uses the legacy "plugin file as menu slug" form, so the slug ends up
// inside page URLs and in the hook suffix WordPress hands back.
define( 'WP_DOWNLOADMANAGER_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_DOWNLOADMANAGER_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_DOWNLOADMANAGER_SLUG', dirname( plugin_basename( __FILE__ ) ) );

// Classes. Required at file load because the activation hook and the option
// accessor are both reached before any action fires.
require_once __DIR__ . '/includes/class-downloadmanager-templates.php';
require_once __DIR__ . '/includes/class-downloadmanager-options.php';
require_once __DIR__ . '/includes/class-downloadmanager-file.php';
require_once __DIR__ . '/includes/class-downloadmanager-display.php';
require_once __DIR__ . '/includes/class-downloadmanager-install.php';
require_once __DIR__ . '/includes/class-downloadmanager-settings.php';
require_once __DIR__ . '/includes/class-downloadmanager-widget.php';
require_once __DIR__ . '/includes/class-downloadmanager-wpstats.php';
require_once __DIR__ . '/includes/class-downloadmanager-admin.php';
require_once __DIR__ . '/includes/class-downloadmanager.php';
require_once __DIR__ . '/includes/template-tags.php';

DownloadManager_Install::init();
DownloadManager_File::init();
DownloadManager_Display::init();
DownloadManager_Settings::init();
DownloadManager_Widget::init();
DownloadManager_WPStats::init();
DownloadManager_Admin::init();
DownloadManager::init();


// Downloads table name.
// Registering the name in $wpdb->tables is what makes it survive
// switch_to_blog(): wpdb::set_blog_id() rebuilds every registered table name
// against the new prefix, while a bare assignment keeps pointing at whichever
// site happened to be current when this file loaded.
global $wpdb;
if ( ! in_array( 'downloads', $wpdb->tables, true ) ) {
	$wpdb->tables[] = 'downloads';
}
$wpdb->downloads = $wpdb->prefix . 'downloads';
