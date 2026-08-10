/**
 * The pre-2.0.0 migration, run the way a real site runs it.
 *
 * Activation does not fire when a plugin is merely updated -- a site that
 * updates from the Plugins screen never calls activate() -- so maybe_upgrade()
 * also hangs off admin_init. That is the hook every real upgrade goes through,
 * and loading an admin page in a browser is the only way to reach it.
 *
 * Nineteen `download_*` rows fold into one here, plus the two WP-Stats rows this
 * plugin shared with six siblings, so the questions this file asks are: did
 * every value land where the new shape keeps it, did every old row go, and does
 * the plugin still work on the far side.
 *
 * Every row is read *raw*. WP_DownloadManager_Options::all() merges over the
 * defaults, so it answers identically for a row holding the defaults and for no
 * row at all -- which is precisely the state §7.6.1 describes, a set of rows
 * read, deleted and never written. Asking the plugin what it sees is how that
 * hides; asking the database is how it does not.
 *
 * One of these tests is about hook order and says so where it stands.
 * wp-downloadmanager.php calls Install::init() before Settings::init(), both
 * hooking admin_init at the same priority, so the migration runs before
 * register_setting() attaches a `default` to the row. Swap those two lines and
 * an install whose migrated settings equal the defaults writes no row at all
 * while its old rows are deleted anyway. Nothing else in the collection would
 * notice; this does.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	LIST_URL,
	SETTINGS_URL,
	createDownloadsPage,
	defaultOptions,
	deleteAllDownloads,
	downloadColumn,
	installLegacyRows,
	listRow,
	openSettings,
	option,
	rawOptions,
	removeDownloadFiles,
	resetOptions,
	runningVersions,
	seedDownloads,
	setVersionRow,
	survivingLegacyRows,
	uniqueName,
	versionRow,
	wpEval,
} = require( './helpers.js' );

/** The Dashboard: an ordinary admin request, which is what an update goes through. */
const DASHBOARD_URL = '/wp-admin/index.php';

/**
 * The rows a 1.69.1 install carries, in the shapes it wrote them.
 *
 * Not every one of the nineteen -- the templates alone would drown the file --
 * but one of each kind the migration has to handle differently: a flat rename,
 * a nested one, the structured `download_options` row that held three settings
 * of its own, both shared WP-Stats rows, and one that carries nothing forward
 * and must still be cleaned up.
 *
 * @param {Object} overrides Anything this particular site had changed.
 * @return {Object} Legacy option name => value.
 */
function legacyInstall( overrides = {} ) {
	return {
		download_page_url: 'https://example.com/library',
		download_method: 2,
		download_nice_permalink: 0,
		download_categories: [ '', 'Manuals', 'Firmware' ],
		download_sort: { by: 'file_hits', order: 'desc', perpage: 7, group: 0 },
		download_template_footer: '<p class="legacy-footer">Downloads footer</p>',

		// The 1.69.1 settings row. Three settings lived only in here, under names
		// the current shape does not have, so it cannot fold in by rename.
		download_options: { use_filename: 1, rss_sortby: 'file_hits', rss_limit: 5 },

		// WP-Stats' two shared rows. Whichever of the seven plugins saved that
		// screen last wrote the first one, and every one of the seven migrations
		// deletes both -- which is why an absent row means "on" and not "off".
		stats_display: { downloads: 1 },
		stats_mostlimit: 7,

		// Carries nothing forward and must still go.
		download_db_version: '1.69.1',
		...overrides,
	};
}

test.describe( 'The pre-2.0.0 upgrade', () => {
	test.afterEach( async () => {
		// Back to a current install: markers stamped, settings at a fresh
		// install's. Every other spec in this suite starts from that, and this
		// one deliberately takes it apart.
		setVersionRow( runningVersions() );
		resetOptions();
	} );

	test( 'the scattered rows fold into one, every old row goes, and the markers are stamped', async ( {
		page,
	} ) => {
		installLegacyRows( legacyInstall() );

		// The fixture really is a pre-2.0.0 install: old rows present, new ones
		// absent. Without this the assertions below could be describing a site
		// that was already migrated, and would pass with the fold-in deleted.
		expect( survivingLegacyRows().length ).toBeGreaterThan( 0 );
		expect( rawOptions() ).toBe( false );
		expect( versionRow() ).toBe( false );

		await page.goto( DASHBOARD_URL );

		const stored = rawOptions();

		// Written, not merely readable through the defaults.
		expect( stored ).not.toBe( false );

		// One of each kind the migration handles differently: a flat rename, a
		// nested one, the two-level sort array, a template, the three settings
		// that lived inside download_options, and both shared WP-Stats rows.
		expect( stored.page_url ).toBe( 'https://example.com/library' );

		// Strings, not integers, and that is WordPress rather than this plugin:
		// a scalar option round-trips through the database as text, so what the
		// fold-in reads out of download_method is "2" whatever was written.
		// Every read casts -- WP_DownloadManager_File does (int) on this one --
		// which is why it has never mattered, and why a test asserting 2 here
		// would be asserting something untrue about every install in the world.
		expect( stored.method ).toBe( '2' );
		expect( stored.nice_permalink ).toBe( '0' );
		expect( stored.sort.perpage ).toBe( 7 );
		expect( stored.sort.by ).toBe( 'file_hits' );
		expect( stored.use_filename ).toBe( 1 );
		expect( stored.rss.sortby ).toBe( 'file_hits' );
		expect( stored.rss.limit ).toBe( 5 );
		expect( stored.stats_most_limit ).toBe( 7 );
		expect( stored.templates.footer ).toBe( '<p class="legacy-footer">Downloads footer</p>' );

		// Every old row gone rather than left to rot, read through the plugin's
		// own three lists so a row added to the migration and forgotten by the
		// cleanup shows up here rather than going unnoticed.
		expect( survivingLegacyRows() ).toEqual( [] );

		// One write, both markers, matching the code that is running.
		expect( versionRow() ).toEqual( runningVersions() );
	} );

	test( 'a site on the shipped settings still gets a row written', async ( { page } ) => {
		const defaults = defaultOptions();

		// The commonest install there is: never changed a setting. Its migrated
		// result is what the defaults would have answered anyway, which is the
		// one shape where a skipped write leaves no trace -- and the shape every
		// customised fixture in this file is blind to.
		//
		// This is also the test that pins the hook order in
		// wp-downloadmanager.php. Install::init() is called before
		// Settings::init(), so the migration runs before register_setting()
		// attaches its `default` to this row; with that filter live, an absent
		// row reads back as the defaults, update_option() finds nothing to
		// change and writes nothing, and the legacy rows are deleted anyway.
		installLegacyRows( {
			download_page_url: defaults.page_url,
			download_method: defaults.method,
			download_nice_permalink: defaults.nice_permalink,
			download_categories: defaults.categories,
			download_sort: defaults.sort,
			download_options: {
				use_filename: defaults.use_filename,
				rss_sortby: defaults.rss.sortby,
				rss_limit: defaults.rss.limit,
			},
			stats_mostlimit: defaults.stats_most_limit,
			download_db_version: '1.69.1',
		} );

		expect( rawOptions() ).toBe( false );

		await page.goto( DASHBOARD_URL );

		const stored = rawOptions();

		expect( stored ).not.toBe( false );
		expect( stored.page_url ).toBe( defaults.page_url );
		expect( survivingLegacyRows() ).toEqual( [] );
	} );

	test( 'the migrated settings reach the screen and the listing', async ( {
		page,
		requestUtils,
	} ) => {
		installLegacyRows( legacyInstall() );

		await page.goto( DASHBOARD_URL );

		// Present is not alive. A row in the right place that nothing reads is a
		// migration that passed and a plugin that broke.
		await openSettings( page );

		await expect( page.locator( '#download_page_url' ) ).toHaveValue(
			'https://example.com/library',
		);
		await expect( page.locator( '#download_sort_perpage' ) ).toHaveValue( '7' );
		await expect( page.locator( '#download_categories' ) ).toHaveValue(
			/Manuals\s+Firmware/,
		);

		// And the template the site had is what the downloads page renders,
		// which is the only assertion here that goes all the way through.
		const name = uniqueName( 'Migrated listing' );

		seedDownloads( { one: { file: 'e2e-migrated.txt', name } } );

		const downloads = await createDownloadsPage(
			requestUtils,
			uniqueName( 'Downloads after the upgrade' ),
		);

		await page.goto( downloads.link );

		await expect( page.locator( '.entry-content .legacy-footer' ) ).toBeVisible();
		await expect( page.locator( '.entry-content' ) ).toContainText( name );
	} );

	test( 'a dedicated legacy row wins, and everything it does not name survives', async ( {
		page,
	} ) => {
		// The shape an install lands in when it ran a development build, saved
		// through the new screen, and only then met the migration: markers gone,
		// both the new row and the old ones present.
		//
		// The migration seeds from the *current* row and lays the legacy ones
		// over it, so a dedicated download_* row is the later word and wins.
		// That is deliberate -- those rows are what the site was actually
		// running on, and the new row on a half-migrated install is as likely to
		// be defaults nobody chose -- and it is worth pinning, because the
		// opposite arrangement is equally plausible to read into the code and
		// would silently discard a setting on every upgrade.
		// Without stats_mostlimit, so the assertion below has a setting no legacy
		// row names to be about. With it, the legacy row would win there too --
		// correctly, and that is the rule the first half of this test states.
		const legacy = legacyInstall( { download_page_url: 'https://example.com/old' } );

		delete legacy.stats_mostlimit;

		installLegacyRows( legacy );

		const data = Buffer.from(
			JSON.stringify( {
				...defaultOptions(),
				page_url: 'https://example.com/new',
				stats_most_limit: 3,
			} ),
			'utf8',
		).toString( 'base64' );

		wpEval(
			`update_option( 'wp_downloadmanager_options', json_decode( base64_decode( '${ data }' ), true ) );
			WP_DownloadManager_Options::flush();
			echo '<<<done>>>';`,
		);

		await page.goto( DASHBOARD_URL );

		const stored = rawOptions();

		expect( stored.page_url ).toBe( 'https://example.com/old' );

		// And a setting no legacy row names is left exactly as the owner saved
		// it: seeding from all() rather than from the defaults is what makes the
		// migration a no-op for everything it has nothing to say about.
		expect( stored.stats_most_limit ).toBe( 3 );
		expect( survivingLegacyRows() ).toEqual( [] );
	} );

	test( 'an install already at this schema is left alone', async ( { page } ) => {
		// Markers saying the upgrade has already happened, and a legacy row that
		// should therefore never be read. is_behind() returning false is what
		// keeps every admin request from being an option write, and the proof is
		// that this row survives untouched.
		setVersionRow( runningVersions() );

		wpEval(
			`update_option( 'download_page_url', 'https://example.com/never-read' );
			echo '<<<done>>>';`,
		);

		await page.goto( DASHBOARD_URL );

		expect( option( 'page_url' ) ).not.toBe( 'https://example.com/never-read' );
		expect( survivingLegacyRows() ).toEqual( [ 'download_page_url' ] );

		wpEval( "delete_option( 'download_page_url' ); echo '<<<done>>>';" );
	} );

	test( 'a category sitting in slot 0 moves up one, and its files move with it', async ( {
		page,
	} ) => {
		// A 2.0.0 install, which shipped its one category in the slot index 0
		// reserves for "no category": the Add File dropdown offered it as value 0,
		// so that is what the rows hold. The list and the rows have to move
		// together or every download reads as uncategorised, and the only way to
		// see that is to ask the screen the owner would have been looking at.
		const stored = Buffer.from(
			JSON.stringify( { ...defaultOptions(), categories: [ 'Manuals', 'Firmware' ] } ),
			'utf8',
		).toString( 'base64' );

		wpEval(
			`update_option( 'wp_downloadmanager_options', json_decode( base64_decode( '${ stored }' ), true ) );
			WP_DownloadManager_Options::flush();
			echo '<<<done>>>';`,
		);
		setVersionRow( { plugin: '2.0.0', db: '3' } );

		const name = uniqueName( 'Filed under slot zero' );
		const ids = seedDownloads( {
			one: { file: 'e2e-slot-zero.txt', name, category: 0 },
		} );

		await page.goto( DASHBOARD_URL );

		expect( rawOptions().categories ).toEqual( [ '', 'Manuals', 'Firmware' ] );
		expect( downloadColumn( ids.one, 'file_category' ) ).toBe( '1' );

		// Present is not alive: the number moved, so the name the screen prints
		// has to be the one the file was filed under and not N/A.
		await page.goto( LIST_URL );

		await expect( listRow( page, name ) ).toContainText( 'Manuals' );
		await expect( listRow( page, name ) ).not.toContainText( 'N/A' );

		// And a second admin request must not move it again.
		await page.goto( DASHBOARD_URL );

		expect( rawOptions().categories ).toEqual( [ '', 'Manuals', 'Firmware' ] );
		expect( downloadColumn( ids.one, 'file_category' ) ).toBe( '1' );

		deleteAllDownloads();
		removeDownloadFiles();
	} );

	test( 'the settings screen is reachable after all of it', async ( { page } ) => {
		await page.goto( SETTINGS_URL );

		await expect( page.getByRole( 'heading', { name: 'Download Settings' } ) ).toBeVisible();
	} );
} );
