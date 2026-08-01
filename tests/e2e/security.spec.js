/**
 * The stored XSS regressions, and the capability gates, in a real browser.
 *
 * A download's display name and description are the two fields a site owner is
 * allowed to put markup in, and the plugin's answer to that is wp_kses_post()
 * on the way into the table. These tests put the payloads in without going
 * through that -- straight into the row, which is the row a compromised install,
 * a restored backup or a pre-fix release already has -- and then look at every
 * surface that renders them.
 *
 * The assertion is the same everywhere and it has two halves: the sentinel the
 * payload would set is never defined, and the payload's text is still on the
 * page. Escaping that ate the value entirely passes the first half and is its
 * own bug, because a download whose name silently vanishes is one.
 *
 * The escaping the plugin does keep is post-level kses, which allows a bare
 * <img> and strips only its onerror. So the check is not "no image" -- an image
 * is fine -- but "nothing that runs": no script element, no event handler, and
 * the sentinel still undefined.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	ADD_URL,
	LIST_URL,
	SETTINGS_URL,
	createDownloadsPage,
	listRow,
	listing,
	resetSite,
	seedDownloads,
	logInAs,
	setOption,
	uniqueName,
	usePrettyPermalinks,
	wpEval,
} = require( './helpers.js' );

const SCRIPT_PAYLOAD = '<script>window.__pwned = 1;</script>';
const IMG_PAYLOAD = '<img src=x onerror="window.__pwned = 1">';
const ATTR_PAYLOAD = '" onmouseover="window.__pwned = 1';

/**
 * Whether any payload managed to run.
 *
 * @param {import('@playwright/test').Page} page Page to ask.
 * @return {Promise<boolean>} True if the sentinel was set.
 */
function pwned( page ) {
	return page.evaluate( () => window.__pwned === 1 );
}

/**
 * A download whose every rendered field is an attack.
 *
 * @return {Object} Keys 'id' and 'name'.
 */
function createHostileDownload() {
	const name = `${ uniqueName( 'Hostile' ) } ${ SCRIPT_PAYLOAD }`;

	return {
		name,
		id: seedDownloads( {
			hostile: {
				name,
				description: `Description ${ IMG_PAYLOAD } and ${ ATTR_PAYLOAD }`,
				file: 'e2e-hostile.txt',
				contents: 'hostile',
				category: 1,
				hits: 3,
			},
		} ).hostile,
	};
}

test.describe( 'Stored markup stays inert', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		usePrettyPermalinks();
		await requestUtils.deleteAllPages();
		await requestUtils.deleteAllPosts();
	} );

	test.beforeEach( async () => {
		resetSite();
	} );

	test.afterAll( async () => {
		resetSite();
	} );

	test( 'the fixture really is stored exactly as written, unsanitised', async () => {
		const hostile = createHostileDownload();

		// The whole point of these tests is a row that never went through the
		// plugin's own sanitizer. If the fixture builder were quietly cleaning the
		// payload, every assertion below would pass while testing nothing.
		expect( wpEval(
			`global $wpdb;
			echo '<<<' . $wpdb->get_var( $wpdb->prepare( "SELECT file_name FROM {$wpdb->downloads} WHERE file_id = %d", ${ hostile.id } ) ) . '>>>';`,
		) ).toContain( SCRIPT_PAYLOAD );
	} );

	test( 'the downloads page renders a hostile row as text', async ( { page, requestUtils } ) => {
		createHostileDownload();
		const downloads = await createDownloadsPage( requestUtils, uniqueName( 'Hostile library' ) );
		setOption( 'page_url', downloads.link );

		await page.goto( downloads.link );

		expect( await pwned( page ) ).toBe( false );

		// As text, not merely absent: the name and the description still say what
		// the row says.
		await expect( listing( page ) ).toContainText( 'window.__pwned' );
		await expect( listing( page ) ).toContainText( 'onmouseover' );
		await expect( listing( page ).locator( 'img[onerror]' ) ).toHaveCount( 0 );
		await expect( listing( page ).locator( 'script' ) ).toHaveCount( 0 );
	} );

	test( 'the embedded shortcode renders a hostile row as text', async ( {
		page,
		requestUtils,
	} ) => {
		const hostile = createHostileDownload();
		const post = await requestUtils.createPost( {
			title: uniqueName( 'Hostile embed' ),
			content: `[download id="${ hostile.id }"]`,
			status: 'publish',
		} );

		await page.goto( post.link );

		expect( await pwned( page ) ).toBe( false );
		await expect( listing( page ) ).toContainText( 'window.__pwned' );
		await expect( listing( page ).locator( 'img[onerror]' ) ).toHaveCount( 0 );
		await expect( listing( page ).locator( 'script' ) ).toHaveCount( 0 );
	} );

	test( 'the widget renders a hostile row as text', async ( { page, requestUtils } ) => {
		createHostileDownload();

		// A page with nothing on it but its own title, on purpose. The sidebar is
		// site-wide, so hanging this off the downloads page would put the listing
		// on screen beside the widget and either surface could then be the one
		// that set the sentinel. The listing has tests of its own above.
		const plain = await requestUtils.createPage( {
			title: uniqueName( 'Hostile sidebar' ),
			content: 'Nothing here but the sidebar.',
			status: 'publish',
		} );

		wpEval(
			`update_option(
				'widget_downloads',
				array(
					2              => array( 'title' => 'Top', 'type' => 'most_downloaded', 'limit' => 5, 'chars' => 0, 'cat_ids' => '1', 'link' => 1 ),
					'_multiwidget' => 1,
				)
			);
			$sidebars = (array) get_option( 'sidebars_widgets', array() );
			$sidebars['sidebar-1'] = array( 'downloads-2' );
			update_option( 'sidebars_widgets', $sidebars );
			echo '<<<done>>>';`,
		);

		await page.goto( plain.link );

		const widget = page.locator( 'ul.wp-downloadmanager' ).first();

		expect( await pwned( page ) ).toBe( false );
		await expect( widget ).toContainText( 'window.__pwned' );
		await expect( widget.locator( 'img[onerror]' ) ).toHaveCount( 0 );

		wpEval(
			`delete_option( 'widget_downloads' );
			$sidebars = (array) get_option( 'sidebars_widgets', array() );
			$sidebars['sidebar-1'] = array();
			update_option( 'sidebars_widgets', $sidebars );
			echo '<<<done>>>';`,
		);
	} );

	test( 'the admin list renders a hostile row as text', async ( { page } ) => {
		createHostileDownload();

		await page.goto( LIST_URL );

		expect( await pwned( page ) ).toBe( false );
		await expect( page.locator( '.wp-list-table' ) ).toContainText( 'window.__pwned' );
		await expect( page.locator( '.wp-list-table img[onerror]' ) ).toHaveCount( 0 );
		await expect( page.locator( '.wp-list-table script' ) ).toHaveCount( 0 );
	} );

	test( 'a hostile file path is escaped on the admin list', async ( { page } ) => {
		// The path is not a field a site owner may put markup in, so unlike the
		// name it has no business surviving as anything but text -- and it is
		// printed inside a <code>, where a broken-out attribute would be enough.
		seedDownloads( {
			sneaky: {
				name: uniqueName( 'Sneaky path' ),
				file: 'e2e-sneaky.txt',
				category: 1,
			},
		} );
		wpEval(
			`global $wpdb;
			$wpdb->query( "UPDATE {$wpdb->downloads} SET file = '/\\"><script>window.__pwned = 1;</script>.txt'" );
			echo '<<<done>>>';`,
		);

		await page.goto( LIST_URL );

		expect( await pwned( page ) ).toBe( false );
		await expect( page.locator( '.wp-list-table script' ) ).toHaveCount( 0 );
	} );

	test( 'the edit screen round-trips a hostile row without running or mangling it', async ( {
		page,
	} ) => {
		const hostile = createHostileDownload();

		await page.goto( LIST_URL );
		const row = listRow( page, 'Hostile' );
		await row.hover();
		await row.getByRole( 'link', { name: 'Edit', exact: true } ).click();

		expect( await pwned( page ) ).toBe( false );

		// The fields must hold the stored value itself. An edit screen that
		// escaped the value into the attribute but decoded it on save is fine;
		// one that dropped or double-escaped it corrupts the row the moment an
		// admin presses save.
		await expect( page.locator( '#file_name' ) ).toHaveValue( /window\.__pwned/ );
		await expect( page.locator( '#file_des' ) ).toHaveValue( /window\.__pwned/ );
		expect( hostile.id ).toBeGreaterThan( 0 );
	} );

	test( 'the delete confirmation screen renders a hostile row as text', async ( { page } ) => {
		createHostileDownload();

		await page.goto( LIST_URL );
		const row = listRow( page, 'Hostile' );
		await row.hover();
		await row.getByRole( 'link', { name: 'Delete', exact: true } ).click();

		await expect( page.getByRole( 'heading', { name: 'Delete File' } ) ).toBeVisible();
		expect( await pwned( page ) ).toBe( false );
		await expect( page.locator( '#wpbody-content' ) ).toContainText( 'Hostile' );
		await expect( page.locator( '#wpbody-content script[src=""], #wpbody-content img[onerror]' ) ).toHaveCount( 0 );
	} );

	test( 'the feed carries a hostile row without a live script in it', async ( { page } ) => {
		createHostileDownload();

		const body = await ( await page.request.get( '/?dl_name=rss' ) ).text();

		// The title is stripped of tags entirely and the description goes through
		// kses, so the payload's text survives and nothing executable does.
		expect( body ).toContain( 'window.__pwned' );
		expect( body ).not.toContain( '<script>window.__pwned' );
		expect( body ).not.toContain( 'onerror' );
	} );
} );

test.describe( 'The admin screens are gated', () => {
	test.beforeAll( async () => {
		resetSite();
	} );

	test( 'a subscriber gets no Downloads menu and no screen, and an admin gets both', async ( {
		page,
		requestUtils,
	} ) => {
		// Both directions on purpose. "The subscriber sees nothing" passes with
		// the plugin deactivated; the admin half is what proves the gate is the
		// capability and not a missing page.
		await page.goto( '/wp-admin/index.php' );
		await expect( page.locator( '#adminmenu' ) ).toContainText( 'WP-DownloadManager' );

		await page.goto( LIST_URL );
		await expect( page.getByRole( 'heading', { name: 'Downloads' } ).first() ).toBeVisible();

		const visitor = await logInAs( page, requestUtils, 'dm_subscriber', 'subscriber' );

		await visitor.page.goto( '/wp-admin/index.php' );
		await expect(
			visitor.page.locator( '#adminmenu' ).getByText( 'WP-DownloadManager' ),
		).toHaveCount( 0 );

		await visitor.page.goto( LIST_URL );
		await expect( visitor.page.locator( 'body' ) ).toContainText(
			/not allowed to access this page|sufficient permissions/,
		);

		await visitor.context.close();
	} );

	test( 'manage_downloads opens the library but not the settings', async ( {
		page,
		requestUtils,
	} ) => {
		// The plugin's whole reason for having a capability of its own: a site can
		// hand the download library to an editor without handing over the
		// dashboard. So the two screens must part company for exactly that user --
		// the library open, the settings shut.
		const editor = await logInAs( page, requestUtils, 'dm_librarian', 'editor', 'manage_downloads' );

		await editor.page.goto( '/wp-admin/index.php' );
		await expect( editor.page.locator( '#adminmenu' ) ).toContainText( 'WP-DownloadManager' );

		await editor.page.goto( LIST_URL );
		await expect(
			editor.page.getByRole( 'heading', { name: 'Downloads' } ).first(),
		).toBeVisible();

		// The settings entry is registered under manage_options, so it is neither
		// in their menu nor reachable by URL.
		await expect(
			editor.page.locator( '#adminmenu' ).getByRole( 'link', { name: 'Settings' } ),
		).toHaveCount( 0 );

		await editor.page.goto( SETTINGS_URL );
		await expect( editor.page.locator( 'body' ) ).toContainText(
			/not allowed to access this page|sufficient permissions/,
		);

		// And an administrator does reach it, so the refusal above is the
		// capability rather than a screen that is broken for everyone.
		await page.goto( SETTINGS_URL );
		await expect( page.getByRole( 'heading', { name: 'Download Settings' } ) ).toBeVisible();

		await editor.context.close();
	} );

	test( 'the Add File screen is shut to a subscriber and open to an admin', async ( {
		page,
		requestUtils,
	} ) => {
		await page.goto( ADD_URL );
		await expect( page.getByRole( 'heading', { name: 'Add File' } ) ).toBeVisible();

		const visitor = await logInAs( page, requestUtils, 'dm_subscriber', 'subscriber' );

		await visitor.page.goto( ADD_URL );
		await expect( visitor.page.locator( 'body' ) ).toContainText(
			/not allowed to access this page|sufficient permissions/,
		);

		await visitor.context.close();
	} );
} );
