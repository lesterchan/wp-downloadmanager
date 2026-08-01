/**
 * What a visitor sees, and what happens when they click Download.
 *
 * The downloads page, the two embed shortcodes, the feed and the widget are all
 * built from the same template strings and the same permission rules, so this
 * file walks each surface with one standing set of files behind it: two for
 * everyone, one only a logged-in member may have, one hidden from every listing
 * there is, and one that lives on somebody else's server.
 *
 * The suite's own browser is logged in as an administrator, who is allowed to
 * download everything. Every permission assertion therefore runs in a second
 * browser context with no session at all -- the same page seen by somebody who
 * has never logged in, which is who the rules are for.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	PERMISSION,
	anonymously,
	createDownloadsPage,
	downloadHits,
	listing,
	logInAs,
	resetSite,
	seedDownloads,
	setOption,
	uniqueName,
	usePrettyPermalinks,
	wpEval,
} = require( './helpers.js' );

/**
 * The standing set of files, keyed by shorthand.
 *
 * Names lead with A, B, C, D, E on purpose: the default sort is by name, so the
 * alphabet is what decides which file the first page of a paged listing holds,
 * and a test about paging should not lean on a coincidence. The ages are
 * explicit for the same reason -- the feed and the "recent" list sort by date,
 * and rows written in the same second break the tie on insertion order.
 */
function librarySpec() {
	return {
		manual: {
			name: uniqueName( 'Aardvark Manual' ),
			file: 'e2e-manual.txt',
			contents: 'a manual',
			description: 'The manual, for everybody.',
			size: 2048,
			category: 1,
			hits: 4,
			permission: PERMISSION.everyone,
			ageInDays: 4,
		},
		members: {
			name: uniqueName( 'Beetle Bundle' ),
			file: 'e2e-members.zip',
			contents: 'members only',
			description: 'Members only.',
			size: 4096,
			category: 1,
			hits: 9,
			permission: PERMISSION.registered,
			ageInDays: 3,
		},
		tool: {
			name: uniqueName( 'Cuttlefish Tool' ),
			file: 'e2e-tool.txt',
			contents: 'a tool',
			description: 'In the other category.',
			size: 8192,
			category: 2,
			hits: 1,
			permission: PERMISSION.everyone,
			ageInDays: 2,
		},
		secret: {
			name: uniqueName( 'Dodo Secret' ),
			file: 'e2e-secret.txt',
			contents: 'nobody sees this',
			description: 'Should never be listed.',
			size: 512,
			category: 2,
			hits: 99,
			permission: PERMISSION.hidden,
			ageInDays: 1,
		},
		elsewhere: {
			name: uniqueName( 'Emu Elsewhere' ),
			file: 'https://example.com/e2e-remote.zip',
			remote: true,
			description: 'Lives on another server.',
			size: 16384,
			category: 2,
			hits: 2,
			permission: PERMISSION.everyone,
			ageInDays: 0,
		},
	};
}

test.describe( 'The downloads page', () => {
	let files;
	let ids;
	let downloads;

	test.beforeAll( async ( { requestUtils } ) => {
		usePrettyPermalinks();
		await requestUtils.deleteAllPages();
		await requestUtils.deleteAllPosts();
	} );

	test.beforeEach( async ( { requestUtils } ) => {
		resetSite();

		files = librarySpec();
		ids = seedDownloads( files );

		downloads = await createDownloadsPage( requestUtils, uniqueName( 'Downloads' ) );

		// Several templates print %DOWNLOAD_PAGE_URL% and every category link is
		// built from it, so the plugin has to be told where its listing is. The
		// permalink is stored exactly as WordPress hands it back, trailing slash
		// and all: feed_link() decides whether to print the feed link by looking
		// for the request URI inside this value, and a stored URL missing the
		// slash the browser sends would never match.
		setOption( 'page_url', downloads.link );
	} );

	test.afterEach( async () => {
		// The sidebar is global: a widget left up by a test that failed halfway
		// would render into every page the next test looks at.
		removeWidget();
	} );

	test.afterAll( async () => {
		resetSite();
	} );

	test( 'the fixture really is five files, one of which no listing may show', async ( {
		page,
	} ) => {
		// The precondition every assertion below leans on. Without it a listing
		// that rendered nothing at all would pass "the hidden file is not there".
		expect( Object.keys( ids ) ).toHaveLength( 5 );
		expect( downloadHits( ids.secret ) ).toBe( 99 );

		await page.goto( downloads.link );

		// Four, not five: the totals in the page header come from the same query
		// as the rows, so the hidden file is missing from the count as well.
		await expect( listing( page ) ).toContainText( '4 files' );
		await expect( listing( page ) ).toBeVisible();
	} );

	test( 'the listing shows every visible file with its size, hits and description', async ( {
		page,
	} ) => {
		await page.goto( downloads.link );

		await expect( listing( page ) ).toContainText( files.manual.name );
		await expect( listing( page ) ).toContainText( 'The manual, for everybody.' );
		await expect( listing( page ) ).toContainText( '2.0 KiB' );
		await expect( listing( page ) ).toContainText( '4 hits' );

		// The hidden file is missing from the listing, not merely unlinked.
		await expect( listing( page ) ).not.toContainText( files.secret.name );
	} );

	test( 'each file is drawn with the icon its extension belongs to', async ( { page } ) => {
		await page.goto( downloads.link );

		// The icons are one inline SVG sprite, defined once per request and
		// referenced per file, so the assertion is on which symbol each row
		// points at rather than on an image URL.
		await expect( listing( page ).locator( '.wp-downloadmanager-sprite' ) ).toHaveCount( 1 );
		await expect(
			listing( page ).locator( 'use[href="#wp-downloadmanager-icon-document"]' ),
		).toHaveCount( 2 );
		await expect(
			listing( page ).locator( 'use[href="#wp-downloadmanager-icon-archive"]' ),
		).toHaveCount( 2 );
	} );

	test( 'the front-end stylesheet and the feed link are on the downloads page', async ( {
		page,
	} ) => {
		await page.goto( downloads.link );

		await expect(
			page.locator( 'link[rel="stylesheet"][href*="wp-downloadmanager.css"]' ),
		).toHaveCount( 1 );

		// The feed link is printed on the downloads page and nowhere else, which
		// is the whole of the condition in feed_link().
		await expect(
			page.locator( 'link[rel="alternate"][type="application/rss+xml"][href*="/download/rss/"]' ),
		).toHaveCount( 1 );

		await page.goto( '/' );
		await expect( page.locator( 'link[href*="/download/rss/"]' ) ).toHaveCount( 0 );
	} );

	test( 'grouping by category prints one heading per category, and switching it off removes them', async ( {
		page,
	} ) => {
		await page.goto( downloads.link );

		// The headings carry the category id, so they are the one thing on the
		// page that proves the grouping ran rather than the files happening to
		// come out in that order.
		await expect( page.locator( '#downloadcat-1' ) ).toContainText( 'Manuals' );
		await expect( page.locator( '#downloadcat-2' ) ).toContainText( 'Software' );

		setOption( 'sort.group', '0' );
		await page.goto( downloads.link );

		await expect( page.locator( '#downloadcat-1' ) ).toHaveCount( 0 );
		await expect( listing( page ) ).toContainText( files.manual.name );
	} );

	test( 'a category heading links to a listing of just that category', async ( { page } ) => {
		await page.goto( downloads.link );

		await page.locator( '#downloadcat-2 a' ).click();

		await expect( listing( page ) ).toContainText( files.tool.name );
		await expect( listing( page ) ).not.toContainText( files.manual.name );
	} );

	test( 'the search box filters the listing and highlights what was searched for', async ( {
		page,
	} ) => {
		await page.goto( downloads.link );

		// The plugin's own search form, not the theme's: twentytwentyone puts a
		// search block on the page too, and a bare "Search" button matches both.
		await listing( page ).locator( 'input[name="dl_search"]' ).fill( 'Cuttlefish' );
		await listing( page ).getByRole( 'button', { name: 'Search' } ).click();

		await expect( listing( page ) ).toContainText( files.tool.name );
		await expect( listing( page ) ).not.toContainText( files.manual.name );
		await expect( listing( page ).locator( '.wp-downloadmanager-highlight' ).first() ).toContainText(
			'Cuttlefish',
		);
	} );

	test( 'a search that matches nothing renders the "no files" template', async ( { page } ) => {
		await page.goto( downloads.link );

		await listing( page ).locator( 'input[name="dl_search"]' ).fill( 'Xenopus' );
		await listing( page ).getByRole( 'button', { name: 'Search' } ).click();

		await expect( listing( page ).locator( '.wp-downloadmanager-none' ) ).toContainText(
			'No Files Found.',
		);
	} );

	test( 'the listing pages, and the second page is reached through its own link', async ( {
		page,
	} ) => {
		setOption( 'sort.perpage', '1' );
		setOption( 'sort.group', '0' );

		await page.goto( downloads.link );

		// Four visible files, one per page.
		await expect( page.locator( '.wp-downloadmanager-paging .pages' ) ).toContainText(
			'Page 1 of 4',
		);
		await expect( listing( page ) ).toContainText( files.manual.name );
		await expect( listing( page ) ).not.toContainText( files.tool.name );

		// Through the link the plugin rendered, never by writing /page/2/ by hand.
		await page.locator( '.wp-downloadmanager-paging a[title="2"]' ).click();

		await expect( page.locator( '.wp-downloadmanager-paging .pages' ) ).toContainText(
			'Page 2 of 4',
		);
		await expect( listing( page ) ).toContainText( files.members.name );
		await expect( listing( page ) ).not.toContainText( files.manual.name );
	} );

	test( 'both names of the page shortcode work, and its category attribute filters', async ( {
		page,
		requestUtils,
	} ) => {
		// [page_downloads] is the same handler under a second name, and a plural
		// that only exists because both spellings shipped. A test that only ever
		// used one of them would not notice the other being dropped.
		const alt = await createDownloadsPage(
			requestUtils,
			uniqueName( 'Alternate' ),
			'[page_downloads category="2"]',
		);

		await page.goto( alt.link );

		await expect( listing( page ) ).toContainText( files.tool.name );
		await expect( listing( page ) ).not.toContainText( files.manual.name );
	} );

	test( 'the [download] shortcode embeds one file, a list of ids and a category', async ( {
		page,
		requestUtils,
	} ) => {
		const post = await requestUtils.createPost( {
			title: uniqueName( 'Embeds' ),
			content: `[download id="${ ids.tool }"]\n\n[download id="${ ids.manual },${ ids.members }"]\n\n[download category="2"]`,
			status: 'publish',
		} );

		await page.goto( post.link );

		const embeds = page.locator( '.entry-content .wp-downloadmanager' );
		await expect( embeds ).toHaveCount( 3 );

		await expect( embeds.nth( 0 ) ).toContainText( files.tool.name );
		await expect( embeds.nth( 0 ) ).not.toContainText( files.manual.name );

		await expect( embeds.nth( 1 ) ).toContainText( files.manual.name );
		await expect( embeds.nth( 1 ) ).toContainText( files.members.name );

		await expect( embeds.nth( 2 ) ).toContainText( files.tool.name );
		await expect( embeds.nth( 2 ) ).toContainText( files.elsewhere.name );
		await expect( embeds.nth( 2 ) ).not.toContainText( files.secret.name );
	} );

	test( 'the shortcode sorts the files it embeds, in either direction', async ( {
		page,
		requestUtils,
	} ) => {
		const post = await requestUtils.createPost( {
			title: uniqueName( 'Sorted embeds' ),
			content: `[download category="1" sort_by="file_hits" sort_order="asc"]\n\n[download category="1" sort_by="file_hits" sort_order="desc"]`,
			status: 'publish',
		} );

		await page.goto( post.link );

		const embeds = page.locator( '.entry-content .wp-downloadmanager' );

		// The manual has four hits and the bundle nine, so ascending leads with
		// the manual and descending with the bundle. Both directions, because a
		// sort argument that is quietly ignored passes any one-sided check.
		await expect( embeds.nth( 0 ).locator( 'p' ).first() ).toContainText( files.manual.name );
		await expect( embeds.nth( 1 ).locator( 'p' ).first() ).toContainText( files.members.name );
	} );

	test( 'stream_limit caps an embed outside a single post and offers the rest behind a link', async ( {
		page,
		requestUtils,
	} ) => {
		const shortcode = '[download category="2" sort_by="file_name" sort_order="asc" stream_limit="1"]';

		const post = await requestUtils.createPost( {
			title: uniqueName( 'Capped embed' ),
			content: shortcode,
			status: 'publish',
		} );

		// On the post itself the cap does not apply: is_single() is the whole
		// condition, and the point of the setting is to keep a listing short
		// everywhere the reader has not committed to the article yet.
		await page.goto( post.link );
		await expect( listing( page ) ).toContainText( files.tool.name );
		await expect( listing( page ) ).toContainText( files.elsewhere.name );
		await expect( page.getByRole( 'link', { name: 'More …' } ) ).toHaveCount( 0 );

		// The other side of is_single() is asked with a page rather than with the
		// blog index. is_single() is false for a page, which is the branch under
		// test -- and the index cannot be used for it at all under this theme,
		// which prints an excerpt there, and an excerpt has its shortcodes
		// stripped before it is built. The page would render nothing to assert on
		// and the test would be about the theme.
		const capped = await createDownloadsPage( requestUtils, uniqueName( 'Capped page' ), shortcode );

		await page.goto( capped.link );
		await expect( listing( page ) ).toContainText( files.tool.name );
		await expect( listing( page ) ).not.toContainText( files.elsewhere.name );
		await expect( page.getByRole( 'link', { name: 'More …' } ) ).toHaveCount( 1 );
	} );

	test( 'display="name" drops the description the embedded template asked for', async ( {
		page,
		requestUtils,
	} ) => {
		// The stock embedded template prints no description at all, so the
		// difference display= makes is invisible with it. A template that does
		// ask for one is what makes the branch observable -- and it is put back
		// afterwards, because a template left rewritten decides the next test.
		wpEval(
			`WP_DownloadManager_Options::flush();
			$all = WP_DownloadManager_Options::all();
			$all['templates']['embedded'] = array(
				'<p><strong>%FILE_NAME%</strong> %FILE_DESCRIPTION%</p>',
				'<p><strong>%FILE_NAME%</strong> %FILE_DESCRIPTION%</p>',
			);
			WP_DownloadManager_Options::save( $all );
			echo '<<<done>>>';`,
		);

		const post = await requestUtils.createPost( {
			title: uniqueName( 'Described embed' ),
			content: `[download id="${ ids.tool }" display="both"]\n\n[download id="${ ids.tool }" display="name"]`,
			status: 'publish',
		} );

		await page.goto( post.link );

		const embeds = page.locator( '.entry-content .wp-downloadmanager' );
		await expect( embeds.nth( 0 ) ).toContainText( 'In the other category.' );
		await expect( embeds.nth( 1 ) ).not.toContainText( 'In the other category.' );
	} );

	test( 'a visitor who may not download gets the denied text instead of a link', async ( {
		page,
	} ) => {
		const { context, visitor } = await anonymously( page );

		await visitor.goto( downloads.link );

		// Both halves in one test. The file is still listed -- it is not a secret,
		// it is just not theirs -- and the link is gone, replaced by the denied
		// template.
		await expect( listing( visitor ) ).toContainText( files.members.name );
		await expect(
			listing( visitor ).locator( `a[href*="/download/${ ids.members }/"]` ),
		).toHaveCount( 0 );
		await expect( listing( visitor ) ).toContainText(
			'You do not have permission to download this file.',
		);

		// And the file they may have is still a link, so the denial is about the
		// permission rather than about the page being broken.
		await expect(
			listing( visitor ).locator( `a[href*="/download/${ ids.manual }/"]` ),
		).toHaveCount( 1 );

		await context.close();
	} );

	test( 'a role-gated file is refused to an author and served to an editor', async ( {
		page,
		requestUtils,
	} ) => {
		// The permission column is a level ladder rather than a yes/no: 7 means
		// "at least an editor", and user_level() derives that from capabilities.
		// Both rungs are asked in one test, because "the author cannot have it"
		// passes just as well when the file is broken for everybody.
		const gated = uniqueName( 'Editors only' );
		const id = seedDownloads( {
			gated: {
				name: gated,
				file: 'e2e-gated.txt',
				contents: 'for editors',
				category: 1,
				permission: PERMISSION.editor,
			},
		} ).gated;

		const author = await logInAs( page, requestUtils, 'dm_author', 'author' );

		await author.page.goto( downloads.link );
		await expect( listing( author.page ) ).toContainText( gated );
		await expect( listing( author.page ) ).toContainText(
			'You do not have permission to download this file.',
		);
		await expect(
			listing( author.page ).locator( `a[href*="/download/${ id }/"]` ),
		).toHaveCount( 0 );

		await author.page.goto( `/download/${ id }/` );
		await expect( author.page.locator( 'body' ) ).toContainText(
			'You do not have permission to download this file.',
		);
		await author.context.close();

		const editor = await logInAs( page, requestUtils, 'dm_editor', 'editor' );

		await editor.page.goto( downloads.link );
		await expect(
			listing( editor.page ).locator( `a[href*="/download/${ id }/"]` ),
		).toHaveCount( 1 );

		const before = downloadHits( id );
		const response = await editor.page.request.get( `/download/${ id }/`, { maxRedirects: 0 } );

		expect( response.status() ).toBe( 302 );
		await expect.poll( () => downloadHits( id ) ).toBe( before + 1 );

		await editor.context.close();
	} );

	test( 'a search term holding a regular-expression character still finds and shows the file', async ( {
		page,
	} ) => {
		// The term goes straight into preg_replace() as the highlighter's pattern.
		// A bare "(" used to fail compilation, which returns null -- and the file
		// name being highlighted was replaced with that null, so searching for a
		// bracket blanked out the name of every row that matched.
		const bracketed = uniqueName( 'Widget (v2) Manual' );
		seedDownloads( {
			bracketed: {
				name: bracketed,
				file: 'e2e-bracketed.txt',
				category: 1,
			},
		} );

		await page.goto( downloads.link );

		await listing( page ).locator( 'input[name="dl_search"]' ).fill( '(v2)' );
		await listing( page ).getByRole( 'button', { name: 'Search' } ).click();

		await expect( listing( page ) ).toContainText( 'Widget' );
		await expect( listing( page ) ).toContainText( 'Manual' );
		await expect( listing( page ) ).not.toContainText( files.manual.name );
	} );

	test( 'following a download link hands over the file and counts the hit', async ( {
		page,
	} ) => {
		const before = downloadHits( ids.manual );

		await page.goto( downloads.link );
		await listing( page ).locator( `a[href*="/download/${ ids.manual }/"]` ).click();

		// The default method is "redirect to file", so the browser ends up at the
		// file itself rather than at a streamed copy of it.
		await expect( page ).toHaveURL( /e2e-manual\.txt$/ );
		await expect( page.locator( 'body' ) ).toContainText( 'a manual' );

		// The count is the far end: a download endpoint that served the bytes and
		// forgot to count them is the bug this plugin exists not to have.
		await expect.poll( () => downloadHits( ids.manual ) ).toBe( before + 1 );
	} );

	test( 'a remote file sends the visitor to the server it actually lives on', async ( {
		page,
	} ) => {
		const before = downloadHits( ids.elsewhere );

		// Followed by hand rather than by navigating: the target is off-site and
		// this test is about where the endpoint points, not about example.com
		// being up.
		const response = await page.request.get( `/download/${ ids.elsewhere }/`, {
			maxRedirects: 0,
		} );

		expect( response.status() ).toBe( 302 );
		expect( response.headers().location ).toBe( 'https://example.com/e2e-remote.zip' );
		await expect.poll( () => downloadHits( ids.elsewhere ) ).toBe( before + 1 );
	} );

	test( 'the endpoint refuses a file above the visitor and never counts it', async ( {
		page,
	} ) => {
		const before = downloadHits( ids.members );
		const { context, visitor } = await anonymously( page );

		await visitor.goto( `/download/${ ids.members }/` );

		await expect( visitor.locator( 'body' ) ).toContainText(
			'You do not have permission to download this file.',
		);
		expect( downloadHits( ids.members ) ).toBe( before );

		await context.close();
	} );

	test( 'the endpoint does not admit that a hidden file exists', async ( { page } ) => {
		// Asked as the administrator, who may download everything: -2 is not a
		// level an important enough visitor clears, it is a row the endpoint
		// refuses to look up at all.
		const response = await page.goto( `/download/${ ids.secret }/` );

		expect( response.status() ).toBe( 404 );
		await expect( page.locator( 'body' ) ).toContainText( 'Invalid File ID or File Name' );
		expect( downloadHits( ids.secret ) ).toBe( 99 );
	} );

	test( 'the endpoint 404s for a row whose file is no longer on disk', async ( { page } ) => {
		wpEval(
			`unlink( WP_DownloadManager_Options::get( 'path.dir' ) . '/e2e-tool.txt' );
			echo '<<<done>>>';`,
		);

		const response = await page.goto( `/download/${ ids.tool }/` );

		expect( response.status() ).toBe( 404 );
		await expect( page.locator( 'body' ) ).toContainText( 'File does not exist.' );
	} );

	test( 'the downloads feed lists the visible files and omits the hidden one', async ( {
		page,
	} ) => {
		const response = await page.request.get( '/?dl_name=rss' );
		const body = await response.text();

		expect( response.headers()[ 'content-type' ] ).toContain( 'rss+xml' );
		expect( body ).toContain( files.manual.name );
		expect( body ).toContain( files.tool.name );
		expect( body ).not.toContain( files.secret.name );

		// Each item carries the download URL, the size and the hit count, which
		// is the whole of what the feed is for.
		expect( body ).toContain( `/download/${ ids.manual }/` );
		expect( body ).toContain( 'File Size: 2.0 KiB' );
		expect( body ).toContain( 'File Hits: 4' );
	} );

	test( 'the widget lists each of its three kinds and links to the page', async ( { page } ) => {
		// One test rather than three: the three types differ only in which query
		// runs, they share every line of rendering, and each needs the same
		// sidebar put up and taken down again.
		for ( const [ type, expected, unexpected ] of [
			[ 'most_downloaded', files.members.name, files.secret.name ],
			[ 'recent_downloads', files.elsewhere.name, files.secret.name ],
			[ 'downloads_category', files.tool.name, files.manual.name ],
		] ) {
			installWidget( type );

			await page.goto( downloads.link );

			const widget = page.locator( 'ul.wp-downloadmanager' ).first();
			await expect( widget ).toContainText( expected );
			await expect( widget ).not.toContainText( unexpected );

			// The "Downloads Page" link comes from a template of its own, and this
			// is the only place that template is ever rendered.
			await expect( page.getByRole( 'link', { name: 'Downloads Page' } ) ).toHaveCount( 1 );
		}
	} );

	test( 'the widget can be told not to link to the downloads page', async ( { page } ) => {
		installWidget( 'most_downloaded', { link: 0 } );

		await page.goto( downloads.link );

		await expect( page.locator( 'ul.wp-downloadmanager' ).first() ).toContainText(
			files.members.name,
		);
		await expect( page.getByRole( 'link', { name: 'Downloads Page' } ) ).toHaveCount( 0 );
	} );

	test( 'the widget truncates a long file name to its character budget', async ( { page } ) => {
		const long = 'Supercalifragilistic Expialidocious Release Notes';
		seedDownloads( {
			verbose: {
				name: long,
				file: 'e2e-verbose.txt',
				hits: 5000,
				category: 1,
			},
		} );

		installWidget( 'most_downloaded', { chars: 12 } );

		await page.goto( downloads.link );

		const widget = page.locator( 'ul.wp-downloadmanager' ).first();
		await expect( widget ).toContainText( 'Supercalifra...' );
		await expect( widget ).not.toContainText( long );
	} );
} );

/**
 * Put the Downloads widget in the theme's sidebar.
 *
 * Widgets have no REST route worth driving from a test, and the block widget
 * editor would be a test of the editor rather than of this plugin, so the two
 * option rows core keeps them in are written directly.
 *
 * @param {string} type      Which of the three statistics the widget shows.
 * @param {Object} overrides Anything else to set on the instance.
 * @return {void}
 */
function installWidget( type, overrides = {} ) {
	const instance = Buffer.from(
		JSON.stringify( {
			title: 'Top Downloads',
			type,
			limit: 5,
			chars: 0,
			cat_ids: '2',
			link: 1,
			...overrides,
		} ),
		'utf8',
	).toString( 'base64' );

	wpEval(
		`update_option(
			'widget_downloads',
			array(
				2              => json_decode( base64_decode( '${ instance }' ), true ),
				'_multiwidget' => 1,
			)
		);
		$sidebars = (array) get_option( 'sidebars_widgets', array() );
		$sidebars['sidebar-1'] = array( 'downloads-2' );
		update_option( 'sidebars_widgets', $sidebars );
		echo '<<<done>>>';`,
	);
}

/**
 * Take it out again, so the next test's pages are the theme's own.
 *
 * @return {void}
 */
function removeWidget() {
	wpEval(
		`delete_option( 'widget_downloads' );
		$sidebars = (array) get_option( 'sidebars_widgets', array() );
		$sidebars['sidebar-1'] = array();
		update_option( 'sidebars_widgets', $sidebars );
		echo '<<<done>>>';`,
	);
}
