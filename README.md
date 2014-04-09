# WP-DownloadManager  
Contributors: GamerZ  
Donate link: http://lesterchan.net/site/donation/  
Tags: file, files, download, downloads, manager, downloadmanager, downloadsmanager, filemanager, filesmanager  
Requires at least: 3.9  
Tested up to: 3.9  
Stable tag: 1.62  
License: GPLv2  

Adds a simple download manager to your WordPress blog.

## Description
Adds a simple download manager to your WordPress blog.

### Development
* [https://github.com/lesterchan/wp-downloadmanager](https://github.com/lesterchan/wp-downloadmanager "https://github.com/lesterchan/wp-downloadmanager")

### Translations
* [http://dev.wp-plugins.org/browser/wp-downloadmanager/i18n/](http://dev.wp-plugins.org/browser/wp-downloadmanager/i18n/ "http://dev.wp-plugins.org/browser/wp-downloadmanager/i18n/")

### Credits
* Icons courtesy of [FamFamFam](http://www.famfamfam.com/ "FamFamFam")
* Download Icon by [Ryan Zimmerman](http://www.imvain.com/" "Ryan Zimmerman")
* Page Download Category by [Ryan Mueller](http://www.creativenotice.com/ "Ryan Mueller")
* File Last Downloaded by [Sevca](http://sevca.cz/ "Sevca")
* __ngetext() by [Anna Ozeritskaya](http://hweia.ru/ "Anna Ozeritskaya")
* Right To Left Language Support by [Kambiz R. Khojasteh](http://persian-programming.com/ "Kambiz R. Khojasteh")

### Donations
* I spent most of my free time creating, updating, maintaining and supporting these plugins, if you really love my plugins and could spare me a couple of bucks, I will really appericiate it. If not feel free to use it without any obligations.

## Changelog

### Version 1.62
* NEW: Uses Dash Icons
* NEW: Supports TinyMCE 4.0 For WordPress 3.9
* NEW: Added sorting to embedded downloads. Props ksze.
* NEW: You can now choose to display file sizes in either binary base or decimal base (i.e. KiB vs KB), using either `%FILE_SIZE` or `%FILE_SIZE_DEC`; `%CATEGORY_SIZE` and `%TOTAL_SIZE` also have their `_DEC` counterparts.. Props ksze.

### Version 1.61
* FIXED: Added nonce to Options. Credits to Charlie Eriksen via Secunia SVCRP.

### Version 1.60 (08-11-2010)
* NEW: Display File ID In Message After Adding A File
* FIXED: Bug In Remote File With Using Nice Permalink and File Name

### Version 1.50 (01-06-2009)
* NEW: Works For WordPress 2.8 Only
* NEW: Add "Add File" To WordPress Favourite Actions
* NEW: Minified editor_plugin.js And Added Non-Minified editor_plugin.dev.js
* NEW: Moved File Extension Icons To /images/ext/
* NEW: Added %FILE_CATEGORY_ID% Template Variable To Download Listing, Download Embedded And Most Downloaded Templates
* NEW: You Can Now Use File Name Instead Of File ID In The URL Via WP-Admin -> Downloads -> Download Options
* NEW: Ability To Filter Downloads By Keyword In The Backend
* NEW: Added Downloads RSS2 Feed
* NEW: Finer Control Of File Download Permission (At Least Contributor, Author, Editor Or Administrator Role)
* NEW: Use _n() Instead Of __ngettext() And _n_noop() Instead Of __ngettext_noop()
* NEW: Uses New Widget Class From WordPress
* NEW: Merge Widget Code To wp-downloadmanager.php And Remove wp-downloadmanager-widget.php
* FIXED: Uses $_SERVER['PHP_SELF'] With plugin_basename(__FILE__) Instead Of Just $_SERVER['REQUEST_URI']

### Version 1.40 (12-12-2008)
* NEW: Works For WordPress 2.7 Only
* NEW: Load Admin JS And CSS Only In WP-DownloadManager Admin Pages
* NEW: Added download-admin-css.css For WP-DownloadManager Admin CSS Styles
* NEW: Uses wp_register_style(), wp_print_styles(), plugins_url() And site_url()
* NEW: Download ShortCode Now Support Category by Kambiz R. Khojasteh
* NEW: Right To Left Language Support by Kambiz R. Khojasteh
* NEW: Output Of downloads_page() And download_embedded() Respectively Applied To "downloads_page" And "download_embedded" Filters by Kambiz R. Khojasteh
* NEW: Call downloadmanager_textdomain() Inside create_download_table() by Kambiz R. Khojasteh
* FIXED: SSL Support
* FIXED: Removed Hard Coded "text-align" Styles And Align Attributes In Widget And Plugin Options by Kambiz R. Khojasteh

### Version 1.31 (16-07-2008)
* NEW: Works For WordPress 2.6
* NEW: Better Translation Using __ngetext() by Anna Ozeritskaya
* FIXED: MYSQL Charset Issue Should Be Solved
* FIXED: Able To Search More Than 1 Word

### Version 1.30 (01-06-2008)
* NEW: Works For WordPress 2.5 Only
* NEW: Added Search Feature
* NEW: No Files Found Template
* NEW: [page_download category="1"] Will Display Downloads In Category ID 1 Only
* NEW: Use KiB/MiB/GiB/TiB Instead Of KB/MB/GB/TB
* NEW: Added Paging Header And Footer Template For Downloads Page
* NEW: Uses WP-PageNavi Style Paging For Downloads Page
* NEW: Additional %CATEGORY_ID% Variable In Category Header And Footer Template
* NEW: Updated WP-DownloadManager TinyMCE Plugin To Work With TinyMCE 3.0
* NEW: Uses Shortcode API
* NEW: Changed [page_downloads] to ### [page_download] ### For Consistency
* NEW: You Can Now Embed Multiple File Download IDs By Doing This: [download id="1,2,3"], Where 1,2,3 Are Your File Download IDs
* NEW: When Inserting File Download Into Post, It is Now [download id="1"], Where 1 Is Your File Download ID
* NEW: Ability To Input File Size Manually Because Remote File Size Does Not Always Work
* NEW: Added New File Permission, 'Hidden'
* NEW: Uses /wp-downloadmanager/ Folder Instead Of /downloadmanager/
* NEW: Uses wp-downloadmanager.php Instead Of downloadmanager.php
* NEW: Uses wp-downloadmanager-widget.php Instead Of download-widget.php
* NEW: Use number_format_i18n() Instead
* NEW: Able To Choose Download Nice Permalink In 'WP-Admin -> Downloads -> Download Options'
* NEW: Added File Extension Icons
* NEW: Added File Last Downloaded Date Column
* NEW: Added File Last Updated Date Column
* NEW: Added %FILE_UPDATED_DATE% And %FILE_UPDATED_TIME% Template Variables
* NEW: Added Download Page Link Template
* NEW: Option To Display Download Page Link In Most Downloaded Widget And Recent Downloads Widget
* FIXED: "Current File" Will Mess Up With The File Size
* FIXED: If SubFolders Do Not Exists, It Will Display PHP Error Message
* FIXED: Replaced get_newest_downloads() With get_recent_downloads()
* FIXED: Recent Downloads Widget Not Working
* FIXED: Conflict Permalink Structure With /%postname%/%post_id/
* FIXED: TinyMCE Tool Tip For Insert File Download Is Not Translated
* FIXED: Category Name Template Not Displaying Category Name

### Version 1.00 (01-10-2007)
* NEW: Initial Release


## Installation

1. Open `wp-content/plugins` Folder
2. Put: `Folder: wp-downloadmanager`
3. Activate `WP-DownloadManager` Plugin
4. You Need To Re-Generate The Permalink `WP-Admin -> Settings -> Permalinks -> Save Changes`

### General Usage
1. To embed a specific file to be downloaded into a post/page, use `[download id="2"]` where 2 is your file id.
1. To embed multiple files to be downloaded into a post/page, use `[download id="1,2,3"]` where 1,2,3 are your file ids.
1. To limit the number of embedded downloads shown for each post in a post stream, use the `stream_limit` option.
 1. Example: `[download id="2" stream_limit="4"]`
 1. This will only display the first 4 downloads for the post when rendered in a post stream, and display the full list of downloads when viewing the single post.
1. To sort embedded downloads, use the `sort_by` and `sort_order` options.
 1. Example: `[download id="2" sort_by="file_id" sort_order="asc"]`
 1. This will sort the embedded downloads by file ID in ascending order.
 1. Valid values for `sort_by` are: `file_id`, `file`, `file_name`, `file_size`, `file_date`, and `file_hits`
1. To choose what to display within the embedded file, use `[download id="1" display="both"]` where 1 is your file id and both will display both the file name and file desccription, whereas name will only display the filename. Note that this will overwrite the "Download Embedded File" template you have in your Download Templates.
1. To embed files as well as categories, use `[download id="1,2,3" category="4,5,6"]` where 1,2,3 are your file id and 4,5,6 are your category ids.
1. If you are using Default Permalinks, the file direct download link will be `http://yoursite.com/index.php?dl_id=2`. If you are using Nice Permalinks, the file direct download link will be `http://yoursite.com/download/2/`, where yoursite.com is your WordPress URL and 2 is your file id.
1. The direct download category link will be `http://yoursite.com/downloads/?dl_cat=3`, where yoursite.com is your WordPress URL, downloads is your Downloads Page name and 3 is your download category id.
1. In order to upload the files straight to the downloads folder, the folder must be first CHMOD to 777. You can specify which folder to be the downloads folder in Download Options.
1. You can configure the Download Options in `WP-Admin -> Downloads -> Download Options`
1. You can configure the Download Templates in `WP-Admin -> Downloads -> Download Templates`

### Downloads Page
1.  Go to `WP-Admin -> Pages -> Add New`
1.  Type any title you like in the post's title area
1. If you `ARE ` using nice permalinks, after typing the title, WordPress will generate the permalink to the page. You will see an 'Edit' link just beside the permalink.
1. Click 'Edit' and type in `downloads` in the text field and click 'Save'.
1. Type `[page_download]` in the post's content area.
1. You can also use `[page_download category="1"]`, this will display all downloads in Category ID 1.
1. Click 'Publish'

### Download Stats (With Widgets)
1. Go to `WP-Admin -> Appearance -> Widgets`
1. The widget name is `Downloads`.


## Upgrading

1. Deactivate `WP-DownloadManager` Plugin
2. Open `wp-content/plugins` Folder
3. Put/Overwrite: `Folder: wp-downloadmanager`
4. Activate `WP-DownloadManager` Plugin
5. Go to `WP-Admin -> Downloads -> Downloads Templates` and restore all the template variables to `Default`


## Upgrade Notice

N/A


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
10. Downloads Page


## Frequently Asked Questions

### To Display Most Downloaded
* Use:
<code>
<?php if (function_exists('get_most_downloaded')): ?>
	<?php get_most_downloaded(); ?>
<?php endif; ?>
</code>
* The first value you pass in is the maximum number of files you want to get.
* Default: `get_most_downloaded(10);`

### To Display Recent Downloads
* Use:
<code>
<?php if (function_exists('get_recent_downloads')): ?>
	<?php get_recent_downloads(); ?>
<?php endif; ?>
</code>
* The first value you pass in is the maximum number of files you want to get.
* Default: `get_recent_downloads(10);`

### To Display Downloads By Category
* Use:
<code>
<?php if (function_exists('get_downloads_category')): ?>
	<?php get_downloads_category(1); ?>
<?php endif; ?>
</code>
* The first value you pass in is the category id.
* The second value you pass in is the maximum number of files you want to get.

Default: `get_downloads_category(1, 10);`
