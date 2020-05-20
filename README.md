# WP-DownloadManager  
Contributors: GamerZ  
Donate link: https://lesterchan.net/site/donation/  
Tags: file, files, download, downloads, manager, downloadmanager, downloadsmanager, filemanager, filesmanager  
Requires at least: 4.0  
Tested up to: 5.4  
Stable tag: 1.68.4    
License: GPLv2  

Adds a simple download manager to your WordPress blog.

## Description

### General Usage
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

### Build Status
[![Build Status](https://travis-ci.org/lesterchan/wp-downloadmanager.svg?branch=master)](https://travis-ci.org/lesterchan/wp-downloadmanager)

### Development
* [https://github.com/lesterchan/wp-downloadmanager](https://github.com/lesterchan/wp-downloadmanager "https://github.com/lesterchan/wp-downloadmanager")

### Translations
* [http://dev.wp-plugins.org/browser/wp-downloadmanager/i18n/](http://dev.wp-plugins.org/browser/wp-downloadmanager/i18n/ "http://dev.wp-plugins.org/browser/wp-downloadmanager/i18n/")

### Credits
* Plugin icon by [Freepik](http://www.freepik.com) from [Flaticon](http://www.flaticon.com)
* Icons courtesy of [FamFamFam](http://www.famfamfam.com/ "FamFamFam")
* Download Icon by [Ryan Zimmerman](http://www.imvain.com/" "Ryan Zimmerman")

### Donations
* I spent most of my free time creating, updating, maintaining and supporting these plugins, if you really love my plugins and could spare me a couple of bucks, I will really appreciate it. If not feel free to use it without any obligations.

## Changelog
### Version 1.68.4
* NEW: Bump WordPress 5.4
* FIXED: Unix timestamp sorting order

### Version 1.68.3
* NEW: Bump WordPress 5.3

### Version 1.68.2
* NEW: WordPress 4.7
* FIXED: Pagination not working
* FIXED: Remove eregi
* FIXED: Remote file URL will get be broken, if the remote file URL gets really ugly

### Version 1.68.1
* NEW: Uses wp_kses_post() for better field sanitization

### Version 1.68
* NEW: Use translate.wordpress.org to translate the plugin
* FIXED: Some WP doesn't have wp_user_level because it has been deprecated

### Version 1.67
* FIXED: Notices

### Version 1.66
* FIXED: Notices in Widget Constructor for WordPress 4.3

### Version 1.65
* FIXED: Integration with WP-Stats

### Version 1.64
* NEW: Supports WordPress MultiSite Network Activate
* NEW: Uses native WordPress uninstall.php
* FIXED: Notices

### Version 1.63
* NEW: Added %FILE_EXT% template variable that  output the file extension
* FIXED: Editor button was outputting the wrong shortcode.
* FIXED: ReferenceError: downloadssEdL10n is not defined if TinyMCE 4.0 is loaded outside the Add/Edit Posts/Pages.
* FIXED: Added backward compatibility with [download=1] in order not to break older downloads.

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

## Frequently Asked Questions

### To Display Most Downloaded

```
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

Default: `get_downloads_category(1, 10);`
