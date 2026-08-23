<?php
/**
 * Plugin Name: WP-DownloadManager
 * Plugin URI: https://lesterchan.net/portfolio/programming/php/
 * Description: Adds a simple download manager to your WordPress blog.
 * Version: 2.0.1
 * Requires at least: 6.8
 * Requires PHP: 8.2
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
	Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
*/


// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;


// WP_DOWNLOADMANAGER_VERSION is the last-run plugin version and
// WP_DOWNLOADMANAGER_DB_VERSION the schema counter; both are compared against
// the two keys of the wp_downloadmanager_version option row on every admin
// request. Bump DB_VERSION whenever the table or the shape of the settings
// array changes.
define( 'WP_DOWNLOADMANAGER_VERSION', '2.0.1' );
define( 'WP_DOWNLOADMANAGER_DB_VERSION', '4' );
define( 'WP_DOWNLOADMANAGER_SLUG', 'wp-downloadmanager' );
define( 'WP_DOWNLOADMANAGER_MAIN_FILE', __FILE__ );
define( 'WP_DOWNLOADMANAGER_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_DOWNLOADMANAGER_URL', plugin_dir_url( __FILE__ ) );

// Classes. Required at file load because the activation hook and the option
// accessor are both reached before any action fires.
require_once WP_DOWNLOADMANAGER_DIR . 'includes/class-wp-downloadmanager-template.php';
require_once WP_DOWNLOADMANAGER_DIR . 'includes/class-wp-downloadmanager-options.php';
require_once WP_DOWNLOADMANAGER_DIR . 'includes/class-wp-downloadmanager-download.php';
require_once WP_DOWNLOADMANAGER_DIR . 'includes/class-wp-downloadmanager-file.php';
require_once WP_DOWNLOADMANAGER_DIR . 'includes/class-wp-downloadmanager-display.php';
require_once WP_DOWNLOADMANAGER_DIR . 'includes/class-wp-downloadmanager-blocks.php';
require_once WP_DOWNLOADMANAGER_DIR . 'includes/class-wp-downloadmanager-install.php';
require_once WP_DOWNLOADMANAGER_DIR . 'includes/class-wp-downloadmanager-settings.php';
require_once WP_DOWNLOADMANAGER_DIR . 'includes/class-wp-downloadmanager-widget.php';
require_once WP_DOWNLOADMANAGER_DIR . 'includes/class-wp-downloadmanager-wpstats.php';
require_once WP_DOWNLOADMANAGER_DIR . 'includes/class-wp-downloadmanager-admin.php';
require_once WP_DOWNLOADMANAGER_DIR . 'includes/class-wp-downloadmanager.php';
require_once WP_DOWNLOADMANAGER_DIR . 'includes/template-tags.php';

WP_DownloadManager::init();
