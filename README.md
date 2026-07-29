# WP-DownloadManager
Contributors: GamerZ  
Donate link: https://lesterchan.net/site/donation/  
Tags: download, downloads, file, files, manager  
Requires at least: 6.8  
Tested up to: 7.0  
Stable tag: 2.0.0  
Requires PHP: 8.2  
License: GPLv2 or later  
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds a simple download manager to your WordPress blog.

## Description

WP-DownloadManager keeps a library of downloadable files, counts how often each
one is fetched, and gives you a downloads page, a feed, a widget and a shortcode
to put them in front of your readers. Files can live in a folder on your server
or anywhere else on the web, and each one can be limited to a role.

### Features
* A downloads page with categories, search, sorting and paging.
* A `[download]` shortcode, plus a button on both the Visual and Text editors.
* Per-file permissions, from everyone down to administrators only.
* Local files, uploads and remote URLs in one library.
* A downloads RSS feed.
* A widget for most downloaded, recent downloads and downloads by category.
* Every piece of markup is a template you can edit.

### Donations
I spent most of my free time creating, updating, maintaining and supporting
these plugins, if you really love my plugins and could spare me a couple of
bucks, I will really appreciate it. If not feel free to use it without any
obligations.

## Usage

1. You Need To Re-Generate The Permalink `WP-Admin -> Settings -> Permalinks -> Save Changes`
1. To embed a specific file to be downloaded into a post/page, use `[download id="2"]` where 2 is your file id.
1. To embed multiple files to be downloaded into a post/page, use `[download id="1,2,3"]` where 1,2,3 are your file ids.
1. To limit the number of embedded downloads shown for each post in a post stream, use the `stream_limit` option.
 1. Example: `[download id="2" stream_limit="4"]`
 1. This will only display the first 4 downloads for the post when rendered in a post stream, and display the full list of downloads when viewing the single post.
1. To sort embedded downloads, use the `sort_by` and `sort_order` options.
 1. Example: `[download id="2" sort_by="file_id" sort_order="asc"]`
 1. This will sort the embedded downloads by file ID in ascending order.
 1. Valid values for `sort_by` are: `file_id`, `file`, `file_name`, `file_size`, `file_date`, and `file_hits`
1. To choose what to display within the embedded file, use `[download id="1" display="both"]` where 1 is your file id and both will display both the file name and file description, whereas name will only display the filename. Note that this will overwrite the "Download Embedded File" template you have in your Download Templates.
1. To embed files as well as categories, use `[download id="1,2,3" category="4,5,6"]` where 1,2,3 are your file id and 4,5,6 are your category ids.
1. If you are using Default Permalinks, the file direct download link will be `http://yoursite.com/index.php?dl_id=2`. If you are using Nice Permalinks, the file direct download link will be `http://yoursite.com/download/2/`, where yoursite.com is your WordPress URL and 2 is your file id.
1. The direct download category link will be `http://yoursite.com/downloads/?dl_cat=3`, where yoursite.com is your WordPress URL, downloads is your Downloads Page name and 3 is your download category id.
1. In order to upload the files straight to the downloads folder, the folder must be writable by the web server. You can specify which folder to be the downloads folder in `WP-Admin -> Downloads -> Settings`.
1. You can configure everything else in `WP-Admin -> Downloads -> Settings`, on the General and Templates tabs.

### Downloads Page
1. Go to `WP-Admin -> Pages -> Add New`
1. Type any title you like in the post's title area
1. If you `ARE ` using nice permalinks, after typing the title, WordPress will generate the permalink to the page. You will see an 'Edit' link just beside the permalink.
1. Click 'Edit' and type in `downloads` in the text field and click 'Save'.
1. Type `[page_download]` in the post's content area.
1. You can also use `[page_download category="1"]`, this will display all downloads in Category ID 1.
1. Click 'Publish'

### Download Stats (With Widgets)
1. Go to `WP-Admin -> Appearance -> Widgets`
1. The widget name is `Downloads`.

## Frequently Asked Questions

### Where did Download Options and Download Templates go?
They are one page now: `WP-Admin -> Downloads -> Settings`, with a General tab
and a Templates tab. WordPress only allows a plugin one settings page, and two
menu entries for one set of settings was one too many. Your settings are carried
over automatically; nothing needs re-entering. Update any bookmark you have.

### My custom code that read a download_* option stopped working
2.0.0 consolidated the nineteen `wp_options` rows into a single
`wp_downloadmanager_options` row holding a nested array, and deleted the old
ones. Read them through the plugin instead:

```php
WP_DownloadManager_Options::get( 'page_url' );
WP_DownloadManager_Options::get( 'sort.perpage' );
WP_DownloadManager_Options::template( 'header' );
WP_DownloadManager_Options::template( 'listing', 1 ); // the no-permission variant
```

### My template shows a broken image where the file icon used to be
`%FILE_ICON%` is the whole icon now, not the name of a GIF to drop into an
`<img src>`. Upgrading rewrites the stock `<img src="...%FILE_ICON%" />` in your
saved templates for you, but if you wrote your own wrapper around it, delete the
wrapper and leave `%FILE_ICON%` on its own.

### To Display Most Downloaded

```php
<?php if (function_exists('get_most_downloaded')): ?>
	<?php get_most_downloaded(); ?>
<?php endif; ?>
```

* The first value you pass in is the maximum number of files you want to get.
* Default: `get_most_downloaded(10);`

### To Display Recent Downloads

```php
<?php if (function_exists('get_recent_downloads')): ?>
	<?php get_recent_downloads(); ?>
<?php endif; ?>
```

* The first value you pass in is the maximum number of files you want to get.
* Default: `get_recent_downloads(10);`

### To Display Downloads By Category

```php
<?php if (function_exists('get_downloads_category')): ?>
	<?php get_downloads_category(1); ?>
<?php endif; ?>
```

* The first value you pass in is the category id.
* The second value you pass in is the maximum number of files you want to get.
* Default: `get_downloads_category(1, 10);`

## Screenshots

1. Admin - Downloads Embedded
2. Admin - Downloads Add
3. Admin - Download Manage
4. Admin - Download Options
5. Admin - Download Stats
6. Admin - Download Templates
7. Admin - Download Templates
8. Download Embedded
9. Downloads Page

## Changelog
### 2.0.0
* BREAKING: Requires WordPress 6.8 and PHP 8.2, up from 6.0 and 7.4.
* BREAKING: The `downloads_page` filter is now `wp_downloadmanager_page` and the `download_embedded` filter is now `wp_downloadmanager_embedded`. The old names are gone.
* BREAKING: The nineteen `download_*` option rows are consolidated into `wp_downloadmanager_options`, and the schema marker into `wp_downloadmanager_version`. Settings are migrated automatically and the old rows deleted.
* BREAKING: Download Options and Download Templates are two tabs on one Settings page, so their admin URLs have changed.
* BREAKING: Every class gained a `WP_DownloadManager_` prefix, and `DownloadManager_Templates` became `WP_DownloadManager_Template`.
* BREAKING: `%FILE_ICON%` is the complete icon element rather than a GIF file name, and the plugin ships no images at all.
* BREAKING: The `stats_display` and `stats_mostlimit` rows shared with WP-Stats are replaced by this plugin's own settings, and WP-Stats collects sections through the `wp_stats_sections` filter.
* NEW: Manage Downloads is a real `WP_List_Table`, with sortable columns, row actions, bulk delete, a search box and a per-user rows-per-page setting.
* NEW: One inline SVG icon sprite, drawn in the theme's own colour and covering modern file types the GIF set never had.
* NEW: Restructured into `includes/class-wp-downloadmanager-*.php`, with nothing loose at the plugin root.
* NEW: The `wp_downloadmanager_capability` filter controls who reaches each screen.
* NEW: The widget supports selective refresh in the customizer.
* CHANGED: Dropped jQuery entirely. The quicktag, editor button, timestamp toggle and template reset buttons are vanilla JavaScript, and inline `onclick=` handlers are gone.
* CHANGED: The stylesheet sets no fonts or colours, uses CSS logical properties so one file serves both text directions, and honours `prefers-color-scheme` and `prefers-reduced-motion`.
* FIXED: SQL injection in `get_downloads_category()`, reachable by anyone able to edit a widget, which could expose files marked Hidden.
* FIXED: SQL injection in the Manage Downloads search box.
* FIXED: The listing and feed sort columns reached `ORDER BY` unvalidated.
* FIXED: The upload subfolder was not constrained to the downloads directory.
* FIXED: A search term containing regex metacharacters blanked the file name it was highlighting.
* FIXED: `wp_get_sites()` was removed in WordPress 5.1, so activating or uninstalling on a network fatalled; uninstall also stopped at 100 sites and unwound `switch_to_blog()` one short.
* FIXED: The widget's "Display Link To Download Page?" setting never showed as selected and silently reverted, and widget edits made in the block widget editor or the customizer were discarded.
* FIXED: Files in a deleted category no longer emit "Undefined array key" warnings on PHP 8.
* FIXED: The downloads directory is actually created on activation now.
* FIXED: Saving the options screen no longer resets the download path when the directory does not exist yet.
* FIXED: Deprecated `upgrade-functions.php` include and `add_option()` third argument removed.
* FIXED: Removed `download-admin-css.css`, a zero-byte stylesheet that had been enqueued on every plugin admin screen since 2010.
* NOTE: Some translated strings gained numbered placeholders (`%1$s`), which changes their msgid. Those strings need retranslating.

### 1.69.2
* NEW: WordPress 7.0
* FIXED: Security hardening to escape output and prevent cross-site scripting (XSS).

### 1.69.1
* FIXED: Use file_id to fetch file again before deleting files.
* FIXED: Don't allow directory traversal for download_path

### 1.69
* FIXED: Only allow certain files to be uploaded based on `wp_check_filetype_and_ext()`

### 1.68.12
* FIXED: Add a warning to let user know that if any users manage to guess the direct file URI, he will be able to download the file as well.

### 1.68.11
* FIXED: Ensure that Download Path starts only with your wp-content folder for additional security.

### 1.68.10
* FIXED: Allow form in Download Page Footer template.

### 1.68.9
* FIXED: XSS file_sortby and file_sortorder in download-manager.php

### 1.68.8
* FIXED: Download Categories not parsing properly.

### 1.68.7
* FIXED: esc_attr()

### 1.68.6
* NEW: Add filter wp_downloadmanager_file_extension_image and wp_downloadmanager_file_extension_images_path
* FIXED: XSS in download-manager.php. Props to Ngo Van Thien and Patchstack.

### 1.68.5
* FIXED: Validation of remote file to prevent Server Side Request Forgery (SSRF) as reported by WordPress Plugin Review Team

### 1.68.4
* NEW: Bump WordPress 5.4
* FIXED: Unix timestamp sorting order

### 1.68.3
* NEW: Bump WordPress 5.3

### 1.68.2
* NEW: WordPress 4.7
* FIXED: Pagination not working
* FIXED: Remove eregi
* FIXED: Remote file URL will get be broken, if the remote file URL gets really ugly

### 1.68.1
* NEW: Uses wp_kses_post() for better field sanitization

### 1.68
* NEW: Use translate.wordpress.org to translate the plugin
* FIXED: Some WP doesn't have wp_user_level because it has been deprecated

### 1.67
* FIXED: Notices

### 1.66
* FIXED: Notices in Widget Constructor for WordPress 4.3

### 1.65
* FIXED: Integration with WP-Stats

### 1.64
* NEW: Supports WordPress MultiSite Network Activate
* NEW: Uses native WordPress uninstall.php
* FIXED: Notices

### 1.63
* NEW: Added %FILE_EXT% template variable that output the file extension
* FIXED: Editor button was outputting the wrong shortcode.
* FIXED: ReferenceError: downloadssEdL10n is not defined if TinyMCE 4.0 is loaded outside the Add/Edit Posts/Pages.
* FIXED: Added backward compatibility with [download=1] in order not to break older downloads.

### 1.62
* NEW: Uses Dash Icons
* NEW: Supports TinyMCE 4.0 For WordPress 3.9
* NEW: Added sorting to embedded downloads. Props ksze.
* NEW: You can now choose to display file sizes in either binary base or decimal base (i.e. KiB vs KB), using either `%FILE_SIZE` or `%FILE_SIZE_DEC`; `%CATEGORY_SIZE` and `%TOTAL_SIZE` also have their `_DEC` counterparts.. Props ksze.

### 1.61
* FIXED: Added nonce to Options. Credits to Charlie Eriksen via Secunia SVCRP.

### 1.60 (08-11-2010)
* NEW: Display File ID In Message After Adding A File
* FIXED: Bug In Remote File With Using Nice Permalink and File Name

## Upgrade Notice

### 2.0.0

This is a large update. Everything below is something you would notice coming
from 1.69.2. Your files, hit counts and settings all survive the upgrade — the
plugin migrates them the first time you load the dashboard — but a handful of
things move, and custom code may need a small edit.

**Your site has to be on WordPress 6.8 and PHP 8.2 or newer.** This is the one
most likely to affect you: an older site simply will not be offered the update.
Check `Tools -> Site Health` before you upgrade. If you are behind, ask your host
to move you to a current PHP version — every version below 8.2 stopped receiving
security fixes some time ago.

**Download Options and Download Templates are one page.** Look under
`WP-Admin -> Downloads -> Settings`, where they are the General and Templates
tabs. Old bookmarks to `admin.php?page=wp-downloadmanager-options` and
`...-templates` will not work. Manage Downloads and Add File are unchanged in
spirit but have new addresses too, so re-bookmark from the menu.

**Two filters were renamed and the old names no longer exist.** If your theme or
a snippet in `functions.php` hooks either of these, update it before you upgrade
or the customisation will simply stop applying — nothing errors, the filter just
never fires:

* `downloads_page` is now `wp_downloadmanager_page`
* `download_embedded` is now `wp_downloadmanager_embedded`

**Every class was renamed.** `DownloadManager_Options` is now
`WP_DownloadManager_Options`, `DownloadManager_Templates` is now
`WP_DownloadManager_Template`, and so on for the rest. The template tags —
`downloads_page()`, `download_embedded()`, `get_most_downloaded()`,
`get_recent_downloads()`, `get_downloads_category()`, `get_download_files()`,
`get_download_size()`, `get_download_hits()`, `download_file_url()` — keep their
names, so a theme that only calls those needs no edits.

**Your settings moved to one database row.** The nineteen `download_*` rows are
folded into `wp_downloadmanager_options` and deleted, and `download_db_version`
becomes `wp_downloadmanager_version`. Anything reading those rows with
`get_option()` needs to go through `WP_DownloadManager_Options::get()` instead.

**The file icons are drawn, not shipped.** The `images/` folder is gone, and
`%FILE_ICON%` now produces the complete icon rather than the name of a GIF. Your
saved templates are rewritten for you where they use the stock
`<img src="...%FILE_ICON%" />`; if you wrapped it in markup of your own, remove
the wrapper and leave `%FILE_ICON%` by itself. Icons take your theme's text
colour now, so they may look different — that is deliberate. The two filters
`wp_downloadmanager_file_extension_images_path` and
`wp_downloadmanager_file_extension_image` are affected: the first is gone, and
the second is passed a family name such as `archive` rather than `zip.gif`.

**The stylesheet was rewritten.** If you copied `download-css.css` into your
theme to override it, that copy is now `wp-downloadmanager.css` and the class
`download-search-highlight` is `wp-downloadmanager-highlight`. The plugin no
longer sets fonts or colours at all, so your theme's own styling comes through.

**WP-Stats settings moved.** The toggle for the downloads section and the number
of rows it lists used to live in WP-Stats and were shared with several other
plugins. They are on this plugin's own Settings page now, under WP-Stats
Options. Set them once after upgrading. If you use WP-Stats, upgrade it at the
same time.
