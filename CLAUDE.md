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
* **Category 0 is "no category", and the shipped list reserves it.** The listing
  page overwrites slot 0 with the totals label, the settings textarea leaves it
  blank every time it rebuilds the numbering, and the Add File dropdown hides any
  category whose name is empty — so a real category at index 0 is offered as
  value 0, filed against as 0, and then renumbered out from under its own files
  the first time anybody saves the settings screen. The default shipped as
  `array( 'General' )` for a decade and did exactly that. Two things now stop it:
  the default is `array( '', 'General' )`, and `upgrade_pre_201()` shifts a stored
  list whose slot 0 is non-empty **and adds one to every `file_category` in the
  same breath** — the list and the rows only mean anything together. The two
  writes cannot be made one, so `wp_downloadmanager_category_shift_pending`
  stands between them: set before the list moves, cleared after the rows follow,
  and either half alone says what is still owed. Drop it and an interrupted run
  leaves a list one ahead of its rows with nothing able to tell, because an
  empty slot 0 is also what an install that never needed shifting looks like.
* **A file in no category reads "N/A" on the two admin screens and blank in the
  feed, the listing heading and WP-CLI's csv/json/yaml — deliberately.**
  `category_name()`'s no-name fallback is a parameter so the choice sits at each
  call site; its docblock names the three callers that decline it and why. Do
  not "fix" the blanks to N/A, and do not hardcode the fallback: the agreement
  test asks both admin surfaces about one uncategorised file, which is what
  stops the screens drifting apart again one click from each other.
* **`merge()` fills in a stored array from the defaults, and must not do it to
  `categories`.** The keys of that list are data — element 3 is category 3, which
  is what a row's `file_category` points at — so a stored list shorter than the
  default would be handed elements it never had: `array( 'General' )` comes back
  as 'General' twice under two numbers, and a site that emptied its list gets
  'General' back on every read. `list_keys()` is the exception list, and it has
  one entry.
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
  `tests/e2e/helpers.js::submitFileForm()` used to blank the field before
  submitting; that guard is gone, and the helper only presses the button.
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

## The blocks

`wp-downloadmanager/download` and `wp-downloadmanager/page-download`, registered
by `WP_DownloadManager_Blocks` from the metadata `bin/build` compiles out of
`src/` into `build/`. **`build/` is generated and gitignored**, so a checkout
that has never been built registers no blocks; `bin/test.sh` and
`bin/test-e2e.sh` build first.

**Three shortcodes, two blocks, and that is not an oversight.**
`[page_download]` and `[page_downloads]` are both registered to
`page_shortcode()`, which takes `$atts` and never the tag, so it cannot tell
which invoked it: identical output, identical single `category` attribute. The
singular is canonical -- it is what this README documents and what the tests
call the listing -- and the plural is an alias that **stays registered and
supported** while getting no block of its own.

**The blocks wrap the shortcodes and never replace them**, and both entry points
meet at `WP_DownloadManager_Display::render_download()` and `downloads_page()`.
**Neither calls the other**; the block does not run `do_shortcode()`.

**`render_download()` exists because the shortcode compared a string to an
integer.** `download_shortcode()` tested `0 !== $id`, strictly, against integer
zero -- but `shortcode_atts()` returns what was typed, so a shortcode's `"0"`
took a branch a block's `0` did not. It was invisible while the resulting query
matched no rows, and diverged for real once combined: `[download id="0"
category="3"]` rendered nothing where the equivalent block rendered category 3.
The extracted renderer trims both to strings and treats `''` and `'0'` alike.

**`id` and `category` are strings on the block, not numbers.**
`[download id="1,2,3"]` is a documented feature, and a numeric attribute would
make the block say less than the shortcode it wraps.

**The icon sprite defeats naive identical-markup tests.** `icon()` emits the
whole SVG sprite with the *first* icon of a request and nothing afterwards, so
two renders in one PHP process are never byte-identical. `reset_sprite()` is
what the block tests call between renders; without it every "the two agree" test
fails on a difference that is not one.

## WP-CLI

`wp downloadmanager list|get|stats|reset-hits|delete`, registered as the bare
noun rather than the plugin slug. There is no REST namespace: the plugin
registers no `admin-ajax.php` action, so there is no client a route would be an
improvement for.

**Everything goes through `WP_DownloadManager_Download`, and that class exists
for a reason.** The listing query was inside the list table's `prepare_items()`,
beside the per-page preference and the paging it also has to work out; the
library totals were inside a method that echoed a table around them; the delete
was a protected method on the admin class. A second caller could only have
copied all three, and a copied delete is one more chance to forget that a local
file may be unlinked and a remote one never can be.

**`delete --delete-file` takes the file off the server**, which is the checkbox
the Delete File screen offers, and is why both destructive subcommands go
through `WP_CLI::confirm()` and a script has to pass `--yes`. It is a no-op for
a file stored as a remote URL: gluing a URL onto the end of the downloads
directory names nothing anybody meant to delete, and the screen does not offer
the checkbox for one either. Pinned from both sides in `test-cli.php`.

**`reset-hits` touches the counter and nothing else.** The Edit File screen
resets it as one column of a whole-row save and stamps `file_updated_date` on
the way through; that is a fact about saving that form rather than about the
counter, and a listing sorted by "last updated" should not reshuffle because
somebody cleared a tally.

**Nothing adds or edits a download.** Add File and Edit File offer four sources
and two of them are a browser handing over a multipart body, so a command can
only half implement the choice. A test asserts no `create` or `update`
subcommand exists, which makes growing one a decision rather than a gap somebody
fills.

**`list` and `stats` include the files marked Hidden**, because they report the
library's own inventory rather than what a visitor can see, and the Downloads
screen makes the same choice. The `file_permission != -2` rule above belongs to
the front-end queries.

## Migrations, and why they are tested through a browser

`maybe_upgrade()` hangs off `init` (priority 5) as well as activation, because
activation hooks do not fire on a plugin update — the usual reason a migration
never runs at all. It costs a current site nothing: `is_behind()` reads one
autoloaded row and compares two strings, so there is no query and the lock below
is never reached. What it does change is that the migration can now run on a
front-end request rather than only an admin one, which is why **the upgrade
takes a lock before it does anything** — two visitors arriving together would
otherwise both migrate, and both add their own 1 to every `file_category`. The
lock is an `add_option()` row, because the unique key on `option_name` is the
only atomic thing available on a site with no persistent object cache;
`wp_cache_add()` succeeds in every request on such a site and would protect
nothing. An abandoned lock times out rather than stranding the site.

`tests/e2e/upgrade.spec.js` drives that path, and three things about it are
worth knowing before changing either side:

* **The legacy row wins over an existing current row.** The migration seeds from
  `all()` and lays the old rows over it, because the old rows are what the site
  was actually running on. The opposite reading is equally plausible from the
  code, so the test asserts both halves: a dedicated `download_*` row wins, and
  a setting no legacy row names survives untouched.
* **Read the row raw when the question is "was it written".** `all()` merges
  over the defaults, so it cannot tell a written row from an absent one — which
  is the state a migration that read, deleted and never wrote leaves behind.
  Seed the *shipped* defaults for the same reason.
* **The migration runs on `init` (priority 5) and `register_setting()` on
  `admin_init`**, so the migration runs before `register_setting()` touches the
  row. That order used to be load-bearing twice
  over, and neither dependency was asserted anywhere. `save()` now adds the row
  outright when a bare `get_option()` says it is absent, so an install whose
  migrated settings equal the defaults no longer writes nothing while its old
  rows are deleted anyway; and `sanitize_categories()` now accepts the stored
  array as well as the textarea's string, so the sanitize pass core runs on every
  `add_option()`/`update_option()` no longer collapses the category list to one
  blank entry. Both are pinned by tests that write through the door with
  `register_setting()` already in place.

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
