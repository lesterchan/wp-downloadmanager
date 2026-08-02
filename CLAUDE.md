# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

WP-DownloadManager follows `_standards/STANDARDS.md` in the parent folder, which
is the contract for all nineteen plugins in the collection. Where this file and
that one disagree, that one wins.

## What it is

A download library: files (local or remote URL) with categories, per-file
permission levels, hit counting, a listing page, an RSS feed, two widgets, a
`[download]` shortcode and a TinyMCE button. One top-level menu — Manage
Downloads, Add File, Settings (Settings / Templates tabs).

At ~6,100 lines of `includes/` it is one of the three heaviest plugins in the
collection.

## Data

* **A custom table**, `$wpdb->downloads`.
* `wp_downloadmanager_options` — folds in **nineteen** `download_*` rows, driven
  by three lists on the options class: `legacy_map()` (flat dot-path renames),
  `legacy_structured_rows()` (`download_options` and the shared `stats_display`,
  neither of which folds in with one assignment) and `legacy_extra_rows()`
  (rows carrying no value forward). `uninstall.php` reads the same three lists,
  so it and the migration cannot disagree about what belongs to the plugin.
* `wp_downloadmanager_version` — from `download_db_version`.
* **`uninstall.php` drops the table.** With wp-draftsforfriends it is one of only
  two schema-touching uninstallers, which changes how the uninstall test must be
  written (§7.2.1).
* One of the seven WP-Stats plugins (§13).

## The two shared WP-Stats rows

`stats_mostlimit` is in `legacy_map()` and `stats_display` in
`legacy_structured_rows()`, because the migration has to know where each one
lands. **Both are named again in `legacy_shared_rows()`, and everything that
deletes rows on the way out subtracts that list** — `uninstall.php` and
`helper-testcase.php::run_uninstall()`, which is the same subtraction written
twice on purpose so the suite deletes exactly what uninstall deletes.

That split is the fix for a release blocker: one list did both jobs, so removing
this plugin deleted two rows the other six WP-Stats plugins were still reading
and silently reconfigured every one of them. §13.2 draws the line — the
migration deletes a shared row because it has folded it in, uninstall leaves it
alone. **Do not fold `legacy_shared_rows()` back into the other lists**: the
single-source-of-truth argument is what caused this. wp-postratings documents the
same arrangement at `includes/class-wp-postratings-options.php:73-89`, and
wp-polls had the identical defect on `stats_display`.

Pinned by `test_the_shared_stats_rows_survive_uninstall` and by
`test_the_uninstaller_reads_its_row_list_from_the_options_class`, which requires
`uninstall.php` to name `legacy_shared_rows()` so nobody re-derives the list
locally and loses the exception.

## Traps

* **`user_level()` maps capabilities to the old 0–10 levels, and it must use
  `manage_options`, not `activate_plugins`.** Both belong to an administrator on
  a single site, but under multisite core's `map_meta_cap()` adds
  `manage_network_plugins` to `activate_plugins` — so every site administrator on
  every network silently dropped to level 7 and lost the level 8–10 downloads on
  their own site. `manage_options` means "administers this site" in both
  (commit `c4866b6`). This was one of the two real bugs the multisite sweep found.
* **`sort_columns()` is an allow-list, not decoration.** The `sort.by` and
  `rss.sortby` settings are written by the settings screen and read straight into
  an `ORDER BY`, where `sanitize_text_field()` would prove nothing.
* **`safe_subfolder()` rejects `..` and null bytes and then confirms with
  `realpath()`.** The subfolder comes from a select the admin screen builds, but
  nothing stopped a hand-crafted POST from sending `../../..` and dropping an
  upload anywhere the web server could write.
* **`file_permission != -2` appears in every query against the table.** `-2` is
  the "hidden/disabled" marker; dropping it from a `WHERE` clause exposes files
  the owner has withdrawn.
* **`handle_add()` must read the posted `file_type` radio.** It used to pass a
  hardcoded `0`, so an upload or a remote URL was accepted by the form and then
  discarded, the row written from whatever Browse held —
  `_standards/RESUME.md` calls it the most user-visible bug in the programme.
  Pinned by `test_adding_a_remote_file_stores_the_url_it_was_given`, which was
  confirmed to fail when the `0` is put back. `handle_edit()` always read it.
* **`%FILE_NAME%` and `%FILE_DESCRIPTION%` are escaped once, in
  `replace_file_vars()`, and nowhere else.** They are the two stored fields a
  site owner may put markup in, so the allow list is `allowed_html()` rather than
  `esc_html()` — escaping them wholesale would turn every existing library's
  formatting into visible tags. Five paths render these templates and each used
  to decide for itself: `output()`, the widget and the two WP-Stats sections ran
  the assembled markup back through `wp_kses()`, while `downloads_page()` and
  `download_embedded()` — the two most exposed — did not, which is the stored XSS
  the e2e sweep found. `wp_kses_post()` on write does not save you: a row from a
  restored backup or a direct write never passed it. **Do not add a sixth
  wrapper**; it would only be a sixth place to forget. wp-useronline's
  `[page_useronline]` carries the same note for the same reason (commit
  `e49b290`). Pinned by the `test_a_hostile_row_is_inert_*` tests.
* **The widget must guard every key it reads.** Six unguarded reads fatalled on
  any partial save — block editor, customizer, first save. Pinned by
  `test_the_widget_keeps_edits_made_without_the_legacy_submit_marker`.
* **File icons are drawn, not shipped.** `images/` is gone; `%FILE_ICON%` now
  produces the whole icon rather than a GIF filename, so a saved template reading
  `<img src="…%FILE_ICON%" />` is rewritten by the migration. The filter
  `wp_downloadmanager_file_extension_images_path` is gone and
  `wp_downloadmanager_file_extension_image` receives a **family** name (`archive`)
  rather than `zip.gif`.
* **`downloads_page` → `wp_downloadmanager_page` and `download_embedded` →
  `wp_downloadmanager_embedded`**, no shims; they fail silently.
* `format_filesize()` used to be a global here and wp-serverinfo defined one too
  — whichever loaded first won. Both are class methods now.
* The `tinymce/` directory is one of only two in the collection (wp-polls has the
  other) and is exempted by §1. Its `plugin.js` is vanilla JS; do not reintroduce
  jQuery.
* The theme stylesheet override is `wp-downloadmanager.css`, and
  `download-search-highlight` is now `wp-downloadmanager-highlight`.

## Tests

`test-admin-writes.php` and `test-security.php` are the ones to read first —
between them they cover the Add/Edit write paths and the permission gate.
`test-wpstats.php` pins the §13.2 hazard (commit `e498907`), which is worth
knowing given the blocker above.

`tests/e2e/` (4 specs, 73 tests) is among the twelve suites `_standards/RESUME.md`
lists as never run to green. Note the e2e suite once asserted the active tab read
"General"; §4.2.2 uses that as its example that renaming a tab is never only the
label.
