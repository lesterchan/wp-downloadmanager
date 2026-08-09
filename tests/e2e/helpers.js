/**
 * Shared steps for the WP-DownloadManager end-to-end suite.
 *
 * A download is not a post: it is a row in the plugin's own table, pointing at a
 * file that has to exist on the server's disk before the Add File screen will
 * even offer it. So the fixtures come from three places. Anything a person would
 * type goes through the Add File screen, so that screen is exercised by the
 * tests which depend on it. Anything a person could not type -- a file name
 * holding a script tag, a row as a compromised install has it -- goes straight
 * into the table through WP-CLI, because what the front end does with a row it
 * did not sanitise itself is the whole point of those tests. And the files
 * themselves are written into the downloads directory from inside the container,
 * because a browser cannot put a file where the server expects to find it.
 */

const { execFileSync } = require( 'child_process' );
const path = require( 'path' );

const { expect } = require( '@wordpress/e2e-test-utils-playwright' );

/** The plugin root, which is where wp-env reads .wp-env.json from. */
const PLUGIN_ROOT = path.join( __dirname, '../..' );

const LIST_URL = '/wp-admin/admin.php?page=wp-downloadmanager';
const ADD_URL = '/wp-admin/admin.php?page=wp-downloadmanager-add';
const SETTINGS_URL = '/wp-admin/admin.php?page=wp-downloadmanager-settings';
const TEMPLATES_URL = `${ SETTINGS_URL }&tab=templates`;

/**
 * The values the "Allowed To Download" select stores.
 *
 * -2 is the odd one out: it does not mean "a very senior role", it means the row
 * is hidden from every listing and from the download endpoint entirely.
 */
const PERMISSION = {
	hidden: '-2',
	everyone: '-1',
	registered: '0',
	contributor: '1',
	author: '2',
	editor: '7',
	administrator: '10',
};

/**
 * The categories every spec starts from.
 *
 * Index 0 is not a category. The settings screen always writes an empty first
 * entry, because the listing overwrites index 0 with the "total" label, so a
 * name stored there would never be seen. Writing the fixture the same way the
 * screen does keeps the ids in the tests and the ids on the screen the same.
 */
const CATEGORIES = [ '', 'Manuals', 'Software' ];

/**
 * Run PHP inside the tests environment and hand back what it printed.
 *
 * The code is base64'd rather than passed as itself: a file name holding quotes,
 * angle brackets and a script tag is exactly the sort of string that arrives at
 * the other end subtly different, and a fixture that is not the payload byte for
 * byte proves nothing about escaping it.
 *
 * @param {string} code PHP to evaluate, without an opening tag.
 * @return {string} Whatever the code echoed between its markers.
 */
function wpEval( code ) {
	const encoded = Buffer.from( code, 'utf8' ).toString( 'base64' );

	const output = execFileSync(
		'npx',
		[
			'--yes',
			'@wordpress/env',
			'run',
			'tests-cli',
			'wp',
			'eval',
			`eval( base64_decode( '${ encoded }' ) );`,
		],
		{ cwd: PLUGIN_ROOT, encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'pipe' ] },
	);

	// wp-env prints its own progress around the command's output, so the code
	// wraps what it wants to return in markers rather than the caller trying to
	// tell the two apart by position.
	//
	// Greedy on purpose. A stored value ending in "</script>" puts a ">" hard up
	// against the closing marker, and a lazy match stops at the first ">>>" it
	// finds -- which is inside the payload, one character short of the end. The
	// only other text wp-env prints is the base64 of this very command, which
	// cannot contain an angle bracket, so running to the last ">>>" is safe.
	const matched = output.match( /<<<([\s\S]*)>>>/ );

	return matched ? matched[ 1 ] : '';
}

/**
 * Insert a download straight into the table, exactly as given.
 *
 * Nothing is sanitised on the way in, which is the point: this is the row an
 * install that has been running since 1.69.2 already has, or the row a
 * compromised one was handed.
 *
 * @param {Object} spec               What to insert.
 * @param {string} spec.file          Stored path, relative to the downloads directory, or a remote URL.
 * @param {string} spec.name          Display name, which the templates render.
 * @param {string} [spec.description] File description.
 * @param {number} [spec.size]        Size in bytes.
 * @param {number} [spec.category]    Category id, indexing CATEGORIES.
 * @param {number} [spec.hits]        Starting hit count.
 * @param {string} [spec.permission]  A value from PERMISSION.
 * @param {number} [spec.ageInDays]   How long ago the file was added, for ordering.
 * @return {number} The new file's id.
 */
function createDownload( spec ) {
	const data = Buffer.from(
		JSON.stringify( {
			description: '',
			size: 1024,
			category: 1,
			hits: 0,
			permission: PERMISSION.everyone,
			ageInDays: 0,
			...spec,
		} ),
		'utf8',
	).toString( 'base64' );

	const id = wpEval(
		`global $wpdb;
		$data = json_decode( base64_decode( '${ data }' ), true );
		$when = WP_DownloadManager_File::now() - ( (int) $data['ageInDays'] * DAY_IN_SECONDS );
		$wpdb->insert(
			$wpdb->downloads,
			array(
				'file'                      => $data['file'],
				'file_name'                 => $data['name'],
				'file_des'                  => $data['description'],
				'file_size'                 => (int) $data['size'],
				'file_category'             => (int) $data['category'],
				'file_date'                 => $when,
				'file_updated_date'         => $when,
				'file_last_downloaded_date' => $when,
				'file_hits'                 => (int) $data['hits'],
				'file_permission'           => (int) $data['permission'],
			)
		);
		echo '<<<' . (int) $wpdb->insert_id . '>>>';`,
	);

	return parseInt( id, 10 );
}

/**
 * Write a whole library -- files on disk and rows in the table -- in one go.
 *
 * One WP-CLI round trip rather than one per file. That is not a micro
 * optimisation: `wp-env run` starts a container command each time, which costs
 * seconds, and a fixture built file by file spends longer setting up than the
 * test spends testing. Building the whole set in a single call also makes the
 * insertion order deterministic, which matters because two rows written in the
 * same second break a date tie on insertion order.
 *
 * @param {Object} specs Keyed by whatever the test wants to call each file; each
 *                       value takes the fields createDownload() takes, plus
 *                       'contents' for what to write into the file.
 * @return {Object} The same keys, mapped to the ids the table assigned.
 */
function seedDownloads( specs ) {
	const encoded = Buffer.from( JSON.stringify( specs ), 'utf8' ).toString( 'base64' );

	return JSON.parse(
		wpEval(
			`global $wpdb;
			$specs = json_decode( base64_decode( '${ encoded }' ), true );
			$dir   = WP_DownloadManager_Options::get( 'path.dir' );
			wp_mkdir_p( $dir );
			$ids = array();
			foreach ( $specs as $key => $spec ) {
				$spec = array_merge(
					array(
						'contents'    => 'download manager test payload',
						'description' => '',
						'size'        => 1024,
						'category'    => 1,
						'hits'        => 0,
						'permission'  => -1,
						'ageInDays'   => 0,
						'remote'      => false,
					),
					$spec
				);

				if ( $spec['remote'] ) {
					$stored = $spec['file'];
				} else {
					file_put_contents( $dir . '/' . $spec['file'], $spec['contents'] );
					$stored = '/' . $spec['file'];
				}

				$when = WP_DownloadManager_File::now() - ( (int) $spec['ageInDays'] * DAY_IN_SECONDS );

				$wpdb->insert(
					$wpdb->downloads,
					array(
						'file'                      => $stored,
						'file_name'                 => $spec['name'],
						'file_des'                  => $spec['description'],
						'file_size'                 => (int) $spec['size'],
						'file_category'             => (int) $spec['category'],
						'file_date'                 => $when,
						'file_updated_date'         => $when,
						'file_last_downloaded_date' => $when,
						'file_hits'                 => (int) $spec['hits'],
						'file_permission'           => (int) $spec['permission'],
					)
				);
				$ids[ $key ] = (int) $wpdb->insert_id;
			}
			echo '<<<' . wp_json_encode( $ids ) . '>>>';`,
		),
	);
}

/**
 * Empty the table, clear the suite's files off disk and restore every setting.
 *
 * All three in one call, for the same reason seedDownloads() builds a whole
 * library in one: this runs before every test in the suite, and three separate
 * container round trips would be most of what the suite spends its time doing.
 *
 * @return {void}
 */
function resetSite() {
	const categories = Buffer.from( JSON.stringify( CATEGORIES ), 'utf8' ).toString( 'base64' );

	wpEval(
		`global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->downloads}" );

		$defaults = WP_DownloadManager_Options::defaults();
		$defaults['categories'] = json_decode( base64_decode( '${ categories }' ), true );
		WP_DownloadManager_Options::save( $defaults );
		WP_DownloadManager_Options::flush();

		foreach ( (array) glob( WP_DownloadManager_Options::get( 'path.dir' ) . '/e2e-*' ) as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file );
			}
		}
		echo '<<<done>>>';`,
	);
}

/**
 * Switch the site onto pretty permalinks and write the rewrite rules out.
 *
 * The tests environment ships with plain permalinks, and this plugin's whole
 * download endpoint is a rewrite rule: /download/12/ and /download/name.ext are
 * two of the four URL shapes the settings screen offers, and neither of them is
 * reachable at all while WordPress is not matching rewrite rules. A suite left
 * on plain permalinks would report the endpoint as broken on every one of those
 * tests while proving nothing about the plugin.
 *
 * It also decides the downloads page's own URL, which the search form posts to.
 * Under ?page_id=N a GET form drops the query string and the search lands on the
 * front page -- the plugin has a workaround for that, but it is gated on the
 * "nice permalink" setting being off, which is not the configuration the rest of
 * these tests are about.
 *
 * Idempotent, so a spec can call it in beforeAll without caring whether another
 * file got there first. Not restored afterwards: the tests database is rebuilt
 * by the PHPUnit run that shares it, and a permalink structure is the shape a
 * real install has rather than something one test borrowed.
 *
 * @return {void}
 */
function usePrettyPermalinks() {
	wpEval(
		`global $wp_rewrite;
		if ( '/%postname%/' !== get_option( 'permalink_structure' ) ) {
			$wp_rewrite->set_permalink_structure( '/%postname%/' );
		}
		$wp_rewrite->init();
		$wp_rewrite->flush_rules( true );
		echo '<<<' . get_option( 'permalink_structure' ) . '>>>';`,
	);
}

/**
 * One column of one download row, as the database holds it.
 *
 * @param {number} fileId File to read.
 * @param {string} column Column of the downloads table.
 * @return {string} The stored value, or the empty string when the row is gone.
 */
function downloadColumn( fileId, column ) {
	return wpEval(
		`global $wpdb;
		echo '<<<' . $wpdb->get_var( $wpdb->prepare( "SELECT ${ column } FROM {$wpdb->downloads} WHERE file_id = %d", ${ fileId } ) ) . '>>>';`,
	);
}

/**
 * How many times a file has been served.
 *
 * @param {number} fileId File to read.
 * @return {number} The stored hit count.
 */
function downloadHits( fileId ) {
	return parseInt( downloadColumn( fileId, 'file_hits' ) || '0', 10 );
}

/**
 * Empty the downloads table.
 *
 * The library totals under the list screen are site-wide and the front-end
 * listing shows every row there is, so a spec that counts anything has to start
 * from a known table rather than from whatever the last run left behind.
 *
 * @return {void}
 */
function deleteAllDownloads() {
	wpEval(
		`global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->downloads}" );
		echo '<<<done>>>';`,
	);
}

/**
 * Write a real file into the downloads directory.
 *
 * The Add File screen's Browse select is built by scanning that directory, and
 * the endpoint 404s on a row whose file is not on disk, so several tests need a
 * file that genuinely exists. It has to be written from inside the container:
 * the downloads directory lives on the server, not on the machine running the
 * browser.
 *
 * @param {string} name     File name, no directory part.
 * @param {string} contents What to put in it.
 * @return {string} The stored form of the name, i.e. with its leading slash.
 */
function writeDownloadFile( name, contents = 'download manager test payload' ) {
	const encoded = Buffer.from( contents, 'utf8' ).toString( 'base64' );

	return wpEval(
		`$dir = WP_DownloadManager_Options::get( 'path.dir' );
		wp_mkdir_p( $dir );
		file_put_contents( $dir . '/${ name }', base64_decode( '${ encoded }' ) );
		echo '<<</${ name }>>>';`,
	);
}

/**
 * Remove everything this suite wrote into the downloads directory.
 *
 * Only the files the tests made: the directory is the site's, and a test that
 * emptied it would take an unrelated fixture with it.
 *
 * @param {string} prefix File name prefix to remove.
 * @return {void}
 */
function removeDownloadFiles( prefix = 'e2e-' ) {
	wpEval(
		`$dir = WP_DownloadManager_Options::get( 'path.dir' );
		foreach ( (array) glob( $dir . '/${ prefix }*' ) as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file );
			}
		}
		echo '<<<done>>>';`,
	);
}

/**
 * One value from the single option row, by its dotted path.
 *
 * @param {string} dotted Path into the option array, e.g. 'sort.perpage'.
 * @return {string} The stored value, as a string.
 */
function option( dotted ) {
	const walk = dotted
		.split( '.' )
		.map( ( key ) => `[ '${ key }' ]` )
		.join( '' );

	return wpEval(
		`WP_DownloadManager_Options::flush();
		$all = WP_DownloadManager_Options::all();
		echo '<<<' . $all${ walk } . '>>>';`,
	);
}

/**
 * Put every setting back to the value a fresh install has.
 *
 * Written straight to the row rather than through the screen: this runs in
 * afterEach, where the point is to be certain of the starting state for the next
 * test rather than to exercise the form a second time. The categories are the
 * one deliberate difference, and they are written in the shape the settings
 * screen produces -- see CATEGORIES.
 *
 * @return {void}
 */
function resetOptions() {
	const categories = Buffer.from( JSON.stringify( CATEGORIES ), 'utf8' ).toString( 'base64' );

	wpEval(
		`$defaults = WP_DownloadManager_Options::defaults();
		$defaults['categories'] = json_decode( base64_decode( '${ categories }' ), true );
		WP_DownloadManager_Options::save( $defaults );
		WP_DownloadManager_Options::flush();
		echo '<<<done>>>';`,
	);
}

/**
 * The whole option row as the database holds it, with no defaults merged in.
 *
 * Not the same question as option() above, and the difference is the whole of
 * §7.6.1: WP_DownloadManager_Options::all() merges over the defaults, so it
 * answers identically for a row holding the defaults and for no row at all --
 * which is what a migration that read, deleted and never wrote leaves behind.
 * Ask the database when the question is "was it written".
 *
 * @return {Object|false} The stored array, or false when there is no row.
 */
function rawOptions() {
	return JSON.parse(
		wpEval( "echo '<<<' . wp_json_encode( get_option( 'wp_downloadmanager_options' ) ) . '>>>';" ),
	);
}

/**
 * The defaults the running code would fall back to.
 *
 * Asked of the install rather than transcribed: half of them are built from the
 * site's own URLs, and a default copied into a test file is a second place
 * holding one fact.
 *
 * @return {Object} The default option array.
 */
function defaultOptions() {
	return JSON.parse(
		wpEval( "echo '<<<' . wp_json_encode( WP_DownloadManager_Options::defaults() ) . '>>>';" ),
	);
}

/**
 * Put the install back into the shape a pre-2.0.0 site is in.
 *
 * The two prefixed rows go away entirely and the scattered unprefixed ones take
 * their place, because that is what the migration has to meet: thirty-odd rows
 * named after the plugin's folder, no markers, and nothing else.
 *
 * @param {Object} rows Legacy option name => value, stored exactly as given.
 * @return {void}
 */
function installLegacyRows( rows ) {
	const data = Buffer.from( JSON.stringify( rows ), 'utf8' ).toString( 'base64' );

	wpEval(
		`delete_option( 'wp_downloadmanager_options' );
		delete_option( 'wp_downloadmanager_version' );
		foreach ( json_decode( base64_decode( '${ data }' ), true ) as $name => $value ) {
			update_option( $name, $value );
		}
		WP_DownloadManager_Options::flush();
		echo '<<<done>>>';`,
	);
}

/**
 * Which of the pre-2.0.0 rows are still in the database.
 *
 * Read through the plugin's own lists rather than a set typed out here, so a
 * row that is added to the migration and forgotten by the cleanup -- or the
 * other way round -- shows up as a failure instead of going unnoticed.
 *
 * @return {string[]} The legacy rows that survive.
 */
function survivingLegacyRows() {
	return JSON.parse(
		wpEval(
			`$names = array_merge(
				array_keys( WP_DownloadManager_Options::legacy_map() ),
				array_values( WP_DownloadManager_Options::legacy_structured_rows() ),
				WP_DownloadManager_Options::legacy_extra_rows()
			);
			$alive = array();
			foreach ( array_unique( $names ) as $name ) {
				if ( false !== get_option( $name, false ) ) {
					$alive[] = $name;
				}
			}
			echo '<<<' . wp_json_encode( array_values( $alive ) ) . '>>>';`,
		),
	);
}

/**
 * The upgrade markers, as the database holds them.
 *
 * @return {Object|false} The stored array, or false when there is no row.
 */
function versionRow() {
	return JSON.parse(
		wpEval( "echo '<<<' . wp_json_encode( get_option( 'wp_downloadmanager_version' ) ) . '>>>';" ),
	);
}

/**
 * Stamp the upgrade markers.
 *
 * @param {Object} versions The two markers.
 * @return {void}
 */
function setVersionRow( versions ) {
	const data = Buffer.from( JSON.stringify( versions ), 'utf8' ).toString( 'base64' );

	wpEval(
		`update_option( 'wp_downloadmanager_version', json_decode( base64_decode( '${ data }' ), true ) );
		echo '<<<done>>>';`,
	);
}

/**
 * The version numbers the running code expects to find stamped.
 *
 * @return {{plugin: string, db: string}} The two markers.
 */
function runningVersions() {
	return JSON.parse(
		wpEval(
			`echo '<<<' . wp_json_encode( array(
				'plugin' => WP_DOWNLOADMANAGER_VERSION,
				'db'     => (string) WP_DOWNLOADMANAGER_DB_VERSION,
			) ) . '>>>';`,
		),
	);
}

/**
 * Write one setting straight into the option row.
 *
 * For the preconditions a test needs but is not itself testing -- pointing the
 * plugin at the page a fixture just created, say. Settings whose saving is under
 * test go through the form instead.
 *
 * @param {string} dotted Path into the option array.
 * @param {string} value  Value to store.
 * @return {void}
 */
function setOption( dotted, value ) {
	const encoded = Buffer.from( String( value ), 'utf8' ).toString( 'base64' );

	wpEval(
		`WP_DownloadManager_Options::flush();
		WP_DownloadManager_Options::set( '${ dotted }', base64_decode( '${ encoded }' ) );
		echo '<<<done>>>';`,
	);
}

/**
 * A name no earlier run can have used.
 *
 * The list screen shows every download there is, and several assertions locate a
 * row by its text, so two runs of the same test would otherwise leave two rows
 * saying the same thing and the second run would fail for no reason worth
 * chasing.
 *
 * @param {string} base What the name should say.
 * @return {string} That, plus enough to tell this run from the last.
 */
function uniqueName( base ) {
	return `${ base } ${ Date.now().toString( 36 ) }`;
}

/**
 * Publish a page that renders the downloads listing.
 *
 * A page rather than a post: the listing is a page in every install that has
 * one, and the plugin's own feed link only prints on is_page().
 *
 * @param {Object} requestUtils The e2e-test-utils request helper.
 * @param {string} title        Page title.
 * @param {string} shortcode    Shortcode to put in the content.
 * @return {Promise<Object>} The created page.
 */
function createDownloadsPage( requestUtils, title, shortcode = '[page_download]' ) {
	return requestUtils.createPage( {
		title,
		content: shortcode,
		status: 'publish',
	} );
}

/**
 * Open the settings screen, on whichever tab.
 *
 * @param {import('@playwright/test').Page} page Page under test.
 * @param {string}                          tab  Either 'general' or 'templates'.
 * @return {Promise<void>} Resolves once the screen is up.
 */
async function openSettings( page, tab = 'general' ) {
	await page.goto( 'templates' === tab ? TEMPLATES_URL : SETTINGS_URL );

	await expect( page.getByRole( 'heading', { name: 'Download Settings' } ) ).toBeVisible();
}

/**
 * Save the settings form and wait for WordPress to have processed it.
 *
 * The wait is on the redirect options.php sends back, not on a notice. This
 * screen scopes settings_errors() to its own option, which filters out the
 * "Settings saved." core registers under the general slug -- so on a save that
 * had nothing to complain about, there is no notice at all to wait for. That is
 * asserted as its own test in settings.spec.js rather than being papered over
 * here.
 *
 * @param {import('@playwright/test').Page} page Page under test.
 * @return {Promise<void>} Resolves once the screen has come back.
 */
async function saveSettings( page ) {
	await page.getByRole( 'button', { name: 'Save Changes' } ).click();

	await expect( page.getByRole( 'heading', { name: 'Download Settings' } ) ).toBeVisible();
}

/**
 * A second page carrying no session at all.
 *
 * The suite runs as an administrator, who may download everything, so the
 * permission branches are only reachable from a browser that has never logged
 * in. The caller closes the context when it is done with it.
 *
 * @param {import('@playwright/test').Page} page Page under test, for its browser.
 * @return {Promise<Object>} Keys 'context' and 'visitor'.
 */
async function anonymously( page ) {
	const context = await page.context().browser().newContext( { storageState: undefined } );

	return { context, visitor: await context.newPage() };
}

/**
 * Press one of the file form's submit buttons.
 *
 * This used to blank #file_remote first, because that field is
 * <input type="url"> and once shipped value="https://" -- no host, so not a
 * valid URL, so the browser's own constraint validation refused to submit
 * either screen and the button silently did nothing. The field carries a
 * placeholder now, so there is nothing to clear and the guard has been
 * removed. "No field on the Add File form blocks the form from being
 * submitted" is the test that says so, and it passes.
 *
 * @param {import('@playwright/test').Page} page  Page under test.
 * @param {string}                          label The button's text.
 * @return {Promise<void>} Resolves once the form has been submitted.
 */
async function submitFileForm( page, label ) {
	await page.getByRole( 'button', { name: label } ).click();
}

/**
 * Fill in and submit the Add File screen.
 *
 * @param {import('@playwright/test').Page} page   Page under test.
 * @param {Object}                          fields What to type in.
 * @return {Promise<number>} The id the plugin assigned, read out of its notice.
 */
async function addFile( page, fields ) {
	const {
		file,
		name,
		description = '',
		category = '1',
		permission = PERMISSION.everyone,
	} = fields;

	await page.goto( ADD_URL );

	// The four sources are radios; picking the file out of the Browse select is
	// what an admin does after dropping a file into the downloads directory.
	await page.locator( '#file_type_0' ).check();
	await page.locator( 'select[name="file"]' ).selectOption( file );

	await page.locator( '#file_name' ).fill( name );
	await page.locator( '#file_des' ).fill( description );
	await page.locator( '#file_cat' ).selectOption( category );
	await page.locator( '#file_permission' ).selectOption( permission );

	await submitFileForm( page, 'Add File' );

	// The screen does not redirect: it stays on Add File and prints a notice
	// carrying the new file's id. That notice is the confirmation, and the id in
	// it is how the rest of the test finds the row.
	const notice = page.locator( '#setting-error-wp_downloadmanager_success' );
	await expect( notice ).toContainText( 'added, with file ID' );

	return parseInt( ( await notice.innerText() ).match( /file ID (\d+)/ )[ 1 ], 10 );
}

/**
 * The row of the downloads list table showing one file.
 *
 * @param {import('@playwright/test').Page} page Page showing the list.
 * @param {string}                          name Display name to look for.
 * @return {import('@playwright/test').Locator} That row.
 */
function listRow( page, name ) {
	return page.locator( '.wp-list-table tbody tr', { hasText: name } );
}

/**
 * The rendered listing on the front end.
 *
 * Every rendered surface is wrapped in this one element, so a test can assert
 * against what the plugin drew rather than against the whole page -- the theme
 * puts the page title on screen too, and the title of a fixture page usually
 * contains the same words as its contents.
 *
 * @param {import('@playwright/test').Page} page Page showing the listing.
 * @return {import('@playwright/test').Locator} The plugin's own container.
 */
function listing( page ) {
	return page.locator( '.entry-content .wp-downloadmanager' ).first();
}

/**
 * Create a user, if they are not there already, and log a fresh browser in.
 *
 * @param {import('@playwright/test').Page} page         Page under test, for its browser.
 * @param {Object}                          requestUtils The e2e-test-utils request helper.
 * @param {string}                          username     Login name.
 * @param {string}                          role         Role to give them.
 * @param {string}                          [capability] An extra capability to grant.
 * @return {Promise<Object>} Keys 'context' and 'page'.
 */
async function logInAs( page, requestUtils, username, role, capability = '' ) {
	await requestUtils
		.rest( {
			method: 'POST',
			path: '/wp/v2/users',
			data: {
				username,
				email: `${ username }@example.com`,
				password: 'correct-horse-battery-staple',
				roles: [ role ],
			},
		} )
		.catch( () => {} ); // Already there from an earlier run.

	if ( capability ) {
		wpEval(
			`$user = get_user_by( 'login', '${ username }' );
			$user->add_cap( '${ capability }' );
			echo '<<<done>>>';`,
		);
	}

	const context = await page.context().browser().newContext( { storageState: undefined } );
	const other = await context.newPage();

	await other.goto( '/wp-login.php' );

	// wp-login.php focuses and selects #user_login on a 200ms timer, so that a
	// visitor can start typing. Filling across that moment puts the password into
	// the username box: Playwright focuses #user_pass, the timer takes focus back
	// and selects what is there, and the typed text replaces the selection.
	// Waiting for the timer's own effect is the signal that it has already fired.
	await expect( other.locator( '#user_login' ) ).toBeFocused();

	await other.locator( '#user_login' ).fill( username );
	await other.locator( '#user_pass' ).fill( 'correct-horse-battery-staple' );
	await other.locator( '#wp-submit' ).click();
	await expect( other.locator( '#wpadminbar' ) ).toBeVisible();

	return { context, page: other };
}

module.exports = {
	ADD_URL,
	CATEGORIES,
	LIST_URL,
	PERMISSION,
	SETTINGS_URL,
	TEMPLATES_URL,
	addFile,
	anonymously,
	createDownload,
	createDownloadsPage,
	defaultOptions,
	deleteAllDownloads,
	downloadColumn,
	downloadHits,
	installLegacyRows,
	listRow,
	listing,
	logInAs,
	openSettings,
	option,
	rawOptions,
	removeDownloadFiles,
	resetOptions,
	resetSite,
	runningVersions,
	saveSettings,
	seedDownloads,
	setOption,
	setVersionRow,
	survivingLegacyRows,
	submitFileForm,
	uniqueName,
	usePrettyPermalinks,
	versionRow,
	wpEval,
	writeDownloadFile,
};
