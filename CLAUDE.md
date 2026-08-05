# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What it is

A download library: files (local or remote URL) with categories, per-file
permission levels, hit counting, a listing page, an RSS feed, two widgets, a
`[download]` shortcode and a TinyMCE button. One top-level menu — Manage
Downloads, Add File, Settings (Settings / Templates tabs). At ~6,100 lines of
`includes/` it is a large plugin, and most of that is the listing templates.

## Data

* **A custom table**, `$wpdb->downloads`.
* `wp_downloadmanager_options` — folds in **nineteen** `download_*` rows, driven
  by three lists on the options class: `legacy_map()` (flat dot-path renames),
  `legacy_structured_rows()` (`download_options` and the shared `stats_display`,
  neither of which folds in with one assignment) and `legacy_extra_rows()`
  (rows carrying no value forward). `uninstall.php` reads the same three lists,
  so it and the migration cannot disagree about what belongs to the plugin.
* `wp_downloadmanager_version` — the `plugin` and `db` upgrade markers, from
  `download_db_version`. Keep them out of the settings array: a marker in there
  has to be rescued from the stored value on every save, because the settings
  form never posts one.
* **`uninstall.php` drops the table**, which is why the uninstall test cannot
  simply `require_once` it — doing that would drop the table the rest of the
  suite runs against. `helper-testcase.php::run_uninstall()` performs the
  deletions itself instead.
* It contributes a section to **WP-Stats**, a separate plugin, by answering the
  `wp_stats_sections` filter.

## The two shared WP-Stats rows

`stats_mostlimit` is in `legacy_map()` and `stats_display` in
`legacy_structured_rows()`, because the migration has to know where each one
lands. **Both are named again in `legacy_shared_rows()`, and everything that
deletes rows on the way out subtracts that list** — `uninstall.php` and
`helper-testcase.php::run_uninstall()`, which is the same subtraction written
twice on purpose so the suite deletes exactly what uninstall deletes.

That split is the fix for a release blocker: one list did both jobs, so removing
this plugin deleted two rows that WP-Stats and its other companion plugins were
still reading, and silently reconfigured every one of them. The line is: **the
migration deletes a shared row because it has folded it in; uninstall leaves it
alone**, because a sibling that has not upgraded yet is still reading it. **Do
not fold `legacy_shared_rows()` back into the other lists** — the
single-source-of-truth argument is exactly what caused this.

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
  discarded and the row was written from whatever Browse held.
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
  wrapper**; it would only be a sixth place to forget. Pinned by the
  `test_a_hostile_row_is_inert_*` tests.
* **`file_remote` carries a `placeholder`, never a `value`.** It is
  `<input type="url">`, and the browser validates every such field on the screen
  before it will submit the form, not only the one in use. Shipping the old
  `value="https://"` — no host, so not a URL — made Add File and Edit File
  unsubmittable the moment they loaded: focus jumped to the field, a bubble
  appeared and no request was made, whichever of the four sources the admin had
  picked. `resolve_source()` already rejects an empty remote with a WP_Error, so
  nothing wanted the prefill. Pinned by
  `test_no_field_arrives_holding_a_value_its_own_type_rejects`, which asks the
  same question of both screens generically rather than of this one field.
  `tests/e2e/helpers.js::submitFileForm()` still blanks the field, guarded on the
  old value, and is now a no-op.
* **The settings screen calls `settings_errors()` itself, and unscoped.** Both
  halves matter, and the rule is: call it if and only if the screen is *not*
  under Settings. Core prints notices from `wp-admin/options-head.php`,
  which `admin-header.php` requires only when `$parent_file` is
  `options-general.php`; this plugin is a top-level menu, so core never prints
  them. And `options.php` registers "Settings saved." against the **`general`**
  slug, so `settings_errors( self::OPTION )` filtered out the only message a save
  produces — the screen saved and said nothing. Scoped is right for the four
  screens in `Admin`, which show their own `wp_downloadmanager` notices and are
  not posted to by `options.php`. Pinned by
  `test_a_save_is_reported_exactly_once`, which catches the mirror-image bug —
  printing every notice twice, which is what an `add_options_page()` screen gets
  for calling this at all — with the same assertion.
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
* The `tinymce/` directory holds the Classic Editor button. Its `plugin.js` is
  vanilla JS; do not reintroduce jQuery.
* The theme stylesheet override is `wp-downloadmanager.css`, and
  `download-search-highlight` is now `wp-downloadmanager-highlight`.

## Migrations, and why they are tested through a browser

`maybe_upgrade()` hangs off `admin_init` as well as activation, because
activation hooks do not fire on a plugin update — the usual reason a migration
never runs at all. `tests/e2e/upgrade.spec.js` drives that path, and three
things about it are worth knowing before changing either side:

* **The legacy row wins over an existing current row.** The migration seeds from
  `all()` and lays the old rows over it, because the old rows are what the site
  was actually running on. The opposite reading is equally plausible from the
  code, so the test asserts both halves: a dedicated `download_*` row wins, and
  a setting no legacy row names survives untouched.
* **Read the row raw when the question is "was it written".** `all()` merges
  over the defaults, so it cannot tell a written row from an absent one — which
  is the state a migration that read, deleted and never wrote leaves behind.
  Seed the *shipped* defaults for the same reason.
* **`wp-downloadmanager.php` calls `Install::init()` before `Settings::init()`**,
  both hooking `admin_init` at the same priority, so the migration runs before
  `register_setting()` attaches its `default` to the row. Swap those two lines
  and an install whose migrated settings equal the defaults writes no row at all
  while its old rows are deleted anyway. The stock-settings test is what would
  catch that.

## Tests

`test-admin-writes.php` and `test-security.php` are the ones to read first —
between them they cover the Add/Edit write paths and the permission gate.
`test-wpstats.php` pins the shared-row hazard above (commit `e498907`).

`bin/test.sh` runs PHPUnit, `bin/test-multisite.sh` the network pass, and
`bin/test-e2e.sh` the Playwright suite. **Run them rather than trusting a note
about their last result** — CI is the authority, and this file cannot be.

Two things the e2e suite has already been caught on. It once asserted the active
tab read "General" — renaming a tab is never only the label. And a scalar legacy
row reads back from `wp_options` as a **string**: `update_option(
'download_method', 2 )` comes back as `"2"`, while the rows that were arrays
keep their types. Every reader casts, so it has never mattered in the plugin;
a test asserting the integer is asserting something untrue of every install.
