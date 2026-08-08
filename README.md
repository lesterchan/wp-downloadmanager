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

## Installation

1. Install and activate the plugin.
1. Re-save your permalinks at `WP-Admin -> Settings -> Permalinks -> Save Changes`, so the `/download/` links are registered.
1. Add your first file at `WP-Admin -> Downloads -> Add File`.
1. To upload straight into the downloads folder, that folder must be writable by the web server. Choose which folder at `WP-Admin -> Downloads -> Settings`.

## Usage

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
1. You can configure everything else in `WP-Admin -> Downloads -> Settings`, on the Settings and Templates tabs.

### Downloads Page
1. Go to `WP-Admin -> Pages -> Add New`
1. Type any title you like in the post's title area
1. If you `ARE ` using nice permalinks, after typing the title, WordPress will generate the permalink to the page. You will see an 'Edit' link just beside the permalink.
1. Click 'Edit' and type in `downloads` in the text field and click 'Save'.
1. Type `[page_download]` in the post's content area.
1. You can also use `[page_download category="1"]`, this will display all downloads in Category ID 1.
1. Click 'Publish'

### Showing Downloads In A Block

Two blocks are available in the editor, under **Widgets**:

* **Download** — one file or several, embedded in a post. **File IDs** and **Category IDs** in the sidebar each take one id or a comma-separated list, and between them they say everything `[download]` says: **Show** chooses between the name with its description and the name alone, and the Order panel carries the sort column, the direction and the limit that applies where the post is one of many in a stream.
* **Downloads Page** — the whole library with its categories, search box and paging, the same listing `[page_download]` produces. **Category ID** narrows it to one category, and zero lists them all.

Both render on the server, so the block preview in the editor is the real listing rather than an approximation, and adding a file updates every post showing it without re-saving anything.

**The shortcodes still work and are not going anywhere.** `[download]`, `[download=2]`, `[page_download]` and `[page_downloads]` behave exactly as they always have, and a post already containing one needs no change. The blocks call the same code the shortcodes call, so the two render identically — use whichever suits the post.

There is no third block for `[page_downloads]`. The plural and the singular are one and the same shortcode registered under two tags, so a block for each would be one block under two names — and unlike a shortcode, a block name is written into the post and stays there. The block wraps `[page_download]`; the plural remains a shortcode you can keep using.

### Download Stats (With Widgets)
1. Go to `WP-Admin -> Appearance -> Widgets`
1. The widget name is `Downloads`.

### WP-CLI
```
wp downloadmanager list
wp downloadmanager list --category=3 --orderby=file_hits --order=desc --limit=10
wp downloadmanager get 3
wp downloadmanager stats
wp downloadmanager reset-hits 3 --yes
wp downloadmanager delete 3 --yes
wp downloadmanager delete 3 --delete-file --yes
```

`list` and `stats` report what the Downloads screen shows, including the files whose permission is Hidden — this is the library's own inventory, not what a visitor can see. Sizes are in bytes rather than the rounded units the screen prints, because a figure a script is going to compare is worth having exact.

`reset-hits` is the "Reset the hit count to zero" checkbox on Edit File, and touches the counter and nothing else. **`delete --delete-file` deletes the file from the server as well**, which is the checkbox the Delete File screen offers; without it only the row goes. Both ask before doing anything, so a script has to pass `--yes`.

There is no `create` or `update`: adding and editing a file offer a four-way choice of source — keep the current file, pick one already in the downloads directory, upload one, or name a remote URL — and two of those are a browser handing over a multipart body.

## Frequently Asked Questions

### Where did Download Options and Download Templates go?
They are one page now: `WP-Admin -> Downloads -> Settings`, with a Settings tab
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

1. Downloads, every file with its size, its hits and who is allowed to take it
2. Add File, which uploads, browses the downloads folder, or points at a remote URL
3. Download Settings: the folder, how a file is delivered, and how the page sorts
4. The Templates tab, holding the markup of the download page and of every link
5. The downloads page a visitor sees, grouped by category
6. The Download block in the editor, with the file it embeds previewed and the sidebar choosing which files, which categories and what each row shows

## Changelog
### 2.0.0
* BREAKING: Requires WordPress 6.8 and PHP 8.2, up from 6.0 and 7.4.
* BREAKING: The `downloads_page` filter is now `wp_downloadmanager_page` and the `download_embedded` filter is now `wp_downloadmanager_embedded`. The old names are gone.
* BREAKING: The nineteen `download_*` option rows are consolidated into `wp_downloadmanager_options`, and the schema marker into `wp_downloadmanager_version`. Settings are migrated automatically and the old rows deleted.
* BREAKING: Download Options and Download Templates are two tabs on one Settings page, so their admin URLs have changed.
* BREAKING: Every class gained a `WP_DownloadManager_` prefix, and `DownloadManager_Templates` became `WP_DownloadManager_Template`.
* BREAKING: `%FILE_ICON%` is the complete icon element rather than a GIF file name, and the plugin ships no images at all.
* BREAKING: The `stats_display` and `stats_mostlimit` rows shared with WP-Stats are replaced by this plugin's own settings, and WP-Stats collects sections through the `wp_stats_sections` filter.
* NEW: A `wp downloadmanager` WP-CLI command: `list`, `get`, `stats`, `reset-hits` and `delete`, the last two asking for confirmation and `delete --delete-file` taking the file off the server as well.
* NEW: Two editor blocks, **Download** and **Downloads Page**, wrapping `[download]` and `[page_download]`. The shortcodes are unchanged, still registered and still supported — `[download]`, `[download=2]`, `[page_download]` and `[page_downloads]` all behave exactly as before, nothing needs re-saving, and the blocks render through the same code so the two are identical.
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
* FIXED: A file name or description holding markup reached the downloads page and the `[download]` shortcode unescaped, where the widget and the WP-Stats sections had always filtered it. All five now run it through one allow list.
* FIXED: A search term containing regex metacharacters blanked the file name it was highlighting.
* FIXED: `wp_get_sites()` was removed in WordPress 5.1, so activating or uninstalling on a network fatalled; uninstall also stopped at 100 sites and unwound `switch_to_blog()` one short.
* FIXED: The widget's "Display Link To Download Page?" setting never showed as selected and silently reverted, and widget edits made in the block widget editor or the customizer were discarded.
* FIXED: Files in a deleted category no longer emit "Undefined array key" warnings on PHP 8.
* FIXED: The downloads directory is actually created on activation now.
* FIXED: Saving the options screen no longer resets the download path when the directory does not exist yet.
* FIXED: Deprecated `upgrade-functions.php` include and `add_option()` third argument removed.
* FIXED: Removed `download-admin-css.css`, a zero-byte stylesheet that had been enqueued on every plugin admin screen since 2010.
* NOTE: Some translated strings gained numbered placeholders (`%1$s`), which changes their msgid. Those strings need retranslating.

## Upgrade Notice

### 2.0.0

Requires WordPress 6.8 and PHP 8.2.

Files, hit counts and settings all survive; the plugin migrates them on the first dashboard load after updating.

**Download Options and Download Templates are one page**, at `WP-Admin -> Downloads -> Settings`, as the Settings and Templates tabs. Bookmarks to `admin.php?page=wp-downloadmanager-options` and `...-templates` no longer resolve, and Manage Downloads and Add File have new addresses too.

**Two filters were renamed, and the old names are gone.** Code hooking either stops applying, silently:

* `downloads_page` is now `wp_downloadmanager_page`
* `download_embedded` is now `wp_downloadmanager_embedded`

**Every class was renamed**: `DownloadManager_Options` is `WP_DownloadManager_Options`, `DownloadManager_Templates` is `WP_DownloadManager_Template`, and so on. The template tags — `downloads_page()`, `download_embedded()`, `get_most_downloaded()`, `get_recent_downloads()`, `get_downloads_category()`, `get_download_files()`, `get_download_size()`, `get_download_hits()` and `download_file_url()` — keep their names.

**Settings moved to one row.** The nineteen `download_*` rows are folded into `wp_downloadmanager_options` and deleted, and `download_db_version` becomes `wp_downloadmanager_version`. Read them through `WP_DownloadManager_Options::get()` rather than `get_option()`.

**File icons are drawn, not shipped.** The `images/` folder is gone and `%FILE_ICON%` produces the complete icon rather than the name of a GIF. Saved templates using the stock `<img src="...%FILE_ICON%" />` are rewritten for you; if you wrapped it in markup of your own, remove the wrapper and leave `%FILE_ICON%` by itself. Icons take your theme's text colour. `wp_downloadmanager_file_extension_images_path` is gone, and `wp_downloadmanager_file_extension_image` is passed a family name such as `archive` rather than `zip.gif`.

**The stylesheet was rewritten.** A theme copy of `download-css.css` becomes `wp-downloadmanager.css`, and the class `download-search-highlight` is now `wp-downloadmanager-highlight`. The plugin no longer sets fonts or colours at all.

**Update all seven WP-Stats plugins together.** The downloads-section toggle and its row count lived in two shared, unprefixed WP-Stats rows. Each plugin keeps its own copy now and deletes the shared rows once it has read them, so whichever you update first takes them from the rest. A missing row means "show", so a block you had switched off may reappear — switch it off again under `WP-Admin -> Downloads -> Settings`, in the WP-Stats Options section of the Settings tab, where the row count also lives.
