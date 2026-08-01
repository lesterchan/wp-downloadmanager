/**
 * The settings screen, and what each setting changes.
 *
 * A setting that saves but does nothing, and a setting that does something but
 * will not save, are the two failures a screenshot cannot tell apart. So each
 * test here drives the form and then checks the far end: the stored option for
 * the values the query logic reads, and the rendered page or the endpoint's own
 * answer for the ones a visitor meets.
 *
 * Every field is set through the form rather than through the option row,
 * because the sanitize callback between the two is one of the things these
 * tests are here to watch -- it is where the sort column is checked against the
 * allow list the query trusts, and where a download path pointing outside
 * wp-content is refused.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	ADD_URL,
	LIST_URL,
	PERMISSION,
	anonymously,
	createDownloadsPage,
	listing,
	openSettings,
	option,
	resetSite,
	saveSettings,
	seedDownloads,
	setOption,
	uniqueName,
	usePrettyPermalinks,
	wpEval,
} = require( './helpers.js' );

/**
 * A selector for one radio, which render_field() gives no id of its own.
 *
 * @param {string} key   Key inside the consolidated option.
 * @param {string} value The value that radio stores.
 * @return {string} A CSS selector for it.
 */
function radio( key, value ) {
	return `input[name="wp_downloadmanager_options[${ key }]"][value="${ value }"]`;
}

test.describe( 'Download settings', () => {
	let files;
	let ids;
	let downloads;

	test.beforeAll( async ( { requestUtils } ) => {
		// Two of the four URL shapes this file checks are rewrite rules, and a
		// site on plain permalinks matches no rewrite rules at all.
		usePrettyPermalinks();
		await requestUtils.deleteAllPages();
		await requestUtils.deleteAllPosts();
	} );

	test.beforeEach( async ( { requestUtils } ) => {
		resetSite();

		files = {
			manual: {
				name: uniqueName( 'Aardvark Manual' ),
				file: 'e2e-manual.txt',
				contents: 'a manual',
				description: 'For everybody.',
				size: 2048,
				category: 1,
				hits: 4,
				ageInDays: 2,
			},
			tool: {
				name: uniqueName( 'Cuttlefish Tool' ),
				file: 'e2e-tool.txt',
				contents: 'a tool',
				description: 'The other category.',
				size: 8192,
				category: 2,
				hits: 40,
				ageInDays: 1,
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
				ageInDays: 0,
			},
		};
		ids = seedDownloads( files );

		downloads = await createDownloadsPage( requestUtils, uniqueName( 'Downloads' ) );
		setOption( 'page_url', downloads.link );
	} );

	test.afterAll( async () => {
		resetSite();
	} );

	test( 'the fixture really is a settings screen with both of its tabs', async ( { page } ) => {
		// The precondition: two tabs registered against two separate section
		// registries. A page that lost one of them would still show the heading
		// every other test in this file waits for.
		await openSettings( page );
		await expect( page.locator( '.nav-tab' ) ).toHaveCount( 2 );
		await expect( page.locator( '.nav-tab-active' ) ).toHaveText( 'General' );
		await expect( page.locator( '#download_path' ) ).toBeVisible();

		await openSettings( page, 'templates' );
		await expect( page.locator( '.nav-tab-active' ) ).toHaveText( 'Templates' );
		await expect( page.locator( '#download_template_header' ) ).toBeVisible();
	} );

	test( 'the paths and the page URL save and reach the front end', async ( { page } ) => {
		await openSettings( page );

		await page.locator( '#download_path_url' ).fill( 'http://localhost:8899/wp-content/files' );
		await page.locator( '#download_page_url' ).fill( 'https://example.com/library' );
		await saveSettings( page );

		expect( option( 'path.url' ) ).toBe( 'http://localhost:8899/wp-content/files' );
		expect( option( 'page_url' ) ).toBe( 'https://example.com/library' );

		// The page URL is not decoration: the search form posts to it and every
		// category link is built from it.
		await page.goto( downloads.link );
		await expect( listing( page ).locator( 'form' ) ).toHaveAttribute(
			'action',
			'https://example.com/library',
		);
	} );

	test( 'a download path outside wp-content is refused, reset and complained about', async ( {
		page,
	} ) => {
		await openSettings( page );

		await page.locator( '#download_path' ).fill( '/tmp/somewhere-else' );
		await saveSettings( page );

		// The notice is the point here rather than a nicety: without it the screen
		// would silently rewrite a deliberate setting and give no reason.
		await expect( page.locator( '#setting-error-download_path' ) ).toContainText(
			'has to start inside your wp-content folder',
		);
		expect( option( 'path.dir' ) ).toBe( '/var/www/html/wp-content' );

		// And a path that is inside wp-content is kept as typed.
		await page.locator( '#download_path' ).fill( '/var/www/html/wp-content/files' );
		await saveSettings( page );
		expect( option( 'path.dir' ) ).toBe( '/var/www/html/wp-content/files' );
	} );

	test( 'all four permalink shapes are rendered and all four still serve the file', async ( {
		page,
	} ) => {
		// Four settings saves and four round trips through the endpoint, which is
		// more than the default budget allows for one test. Splitting it into four
		// would lose the point: what matters is that all four shapes work on the
		// same library, one after another.
		test.slow();

		// Two independent toggles, so four URLs. Each one is checked twice: that
		// the listing renders it, and that following it actually reaches the file
		// -- a URL shape the endpoint cannot parse back is the exact failure the
		// "change it to No when you encounter 404" advice is about.
		const cases = [
			{ nice: '1', filename: '0', href: `/download/${ ids.manual }/` },
			{ nice: '0', filename: '0', href: `/?dl_id=${ ids.manual }` },
			{ nice: '1', filename: '1', href: '/download/e2e-manual.txt' },
			{ nice: '0', filename: '1', href: '/?dl_name=e2e-manual.txt' },
		];

		for ( const { nice, filename, href: expected } of cases ) {
			await openSettings( page );
			await page.locator( radio( 'nice_permalink', nice ) ).check();
			await page.locator( radio( 'use_filename', filename ) ).check();
			await saveSettings( page );

			expect( option( 'nice_permalink' ) ).toBe( nice );
			expect( option( 'use_filename' ) ).toBe( filename );

			await page.goto( downloads.link );
			const link = listing( page ).locator( `a[href$="${ expected }"]` );
			await expect( link ).toHaveCount( 1 );

			const response = await page.request.get( expected, { maxRedirects: 0 } );
			expect( response.status(), `${ expected } should serve the file` ).toBe( 302 );
		}
	} );

	test( 'the "output file" method streams the bytes instead of redirecting', async ( {
		page,
	} ) => {
		await openSettings( page );
		await page.locator( '#download_method' ).selectOption( '0' );
		await saveSettings( page );

		expect( option( 'method' ) ).toBe( '0' );

		const response = await page.request.get( `/download/${ ids.manual }/`, { maxRedirects: 0 } );

		expect( response.status() ).toBe( 200 );
		expect( response.headers()[ 'content-disposition' ] ).toContain( 'attachment' );
		expect( response.headers()[ 'content-disposition' ] ).toContain( 'e2e-manual.txt' );
		expect( await response.text() ).toBe( 'a manual' );

		// And back the other way, so the setting is shown to be what decides it.
		await openSettings( page );
		await page.locator( '#download_method' ).selectOption( '1' );
		await saveSettings( page );

		const redirected = await page.request.get( `/download/${ ids.manual }/`, { maxRedirects: 0 } );
		expect( redirected.status() ).toBe( 302 );
	} );

	test( 'every sort column the screen offers is one the listing query accepts', async ( {
		page,
	} ) => {
		await openSettings( page );

		const columns = await page.locator( '#download_sort_by option' ).evaluateAll( ( options ) =>
			options.map( ( o ) => o.value ),
		);

		// The select is built from the same allow list the query is filtered
		// through, so a value it offers can never be one the sanitizer silently
		// rejects -- which is how a setting comes to revert on the next load.
		expect( columns ).toEqual( [
			'file_id',
			'file',
			'file_name',
			'file_size',
			'file_date',
			'file_updated_date',
			'file_hits',
		] );

		for ( const column of columns ) {
			await page.locator( '#download_sort_by' ).selectOption( column );
			await saveSettings( page );
			expect( option( 'sort.by' ), `${ column } should survive the sanitizer` ).toBe( column );
			await openSettings( page );
		}
	} );

	test( 'the sort column and direction decide the order the listing comes out in', async ( {
		page,
	} ) => {
		await openSettings( page );
		await page.locator( '#download_sort_by' ).selectOption( 'file_hits' );
		await page.locator( '#download_sort_order' ).selectOption( 'desc' );
		await page.locator( '#download_sort_group' ).selectOption( '0' );
		await saveSettings( page );

		await page.goto( downloads.link );

		// On the order the two names come out in rather than on a paragraph
		// index: the header template is itself two paragraphs and the footer is
		// another, so "the second <p>" is a fact about the stock templates and
		// not about the sort. Forty hits before four, descending.
		let text = await listing( page ).innerText();
		expect( text.indexOf( files.tool.name ) ).toBeLessThan( text.indexOf( files.manual.name ) );

		await openSettings( page );
		await page.locator( '#download_sort_order' ).selectOption( 'asc' );
		await saveSettings( page );

		await page.goto( downloads.link );

		text = await listing( page ).innerText();
		expect( text.indexOf( files.manual.name ) ).toBeLessThan( text.indexOf( files.tool.name ) );
	} );

	test( 'the per-page setting decides how many files a page of the listing holds', async ( {
		page,
	} ) => {
		await openSettings( page );
		await page.locator( '#download_sort_perpage' ).fill( '2' );
		await page.locator( '#download_sort_group' ).selectOption( '0' );
		await saveSettings( page );

		expect( option( 'sort.perpage' ) ).toBe( '2' );

		await page.goto( downloads.link );
		await expect( page.locator( '.wp-downloadmanager-paging .pages' ) ).toContainText(
			'Page 1 of 2',
		);
	} );

	test( 'a category added through the screen reaches the file form and the listing', async ( {
		page,
	} ) => {
		await openSettings( page );

		// One per line, and the first line is category 1 -- so appending a third
		// line makes category 3.
		await page.locator( '#download_categories' ).fill( 'Manuals\nSoftware\nFirmware' );
		await saveSettings( page );

		await page.goto( ADD_URL );
		await expect( page.locator( '#file_cat option[value="3"]' ) ).toHaveText( 'Firmware' );

		// And a file in it is grouped under that name on the front end.
		seedDownloads( {
			firmware: {
				name: uniqueName( 'Zebra Firmware' ),
				file: 'e2e-firmware.txt',
				category: 3,
			},
		} );

		await page.goto( downloads.link );
		await expect( page.locator( '#downloadcat-3' ) ).toContainText( 'Firmware' );
	} );

	test( 'the feed setting decides how the feed is sorted and how long it is', async ( {
		page,
	} ) => {
		await openSettings( page );
		await page.locator( '#download_rss_sortby' ).selectOption( 'file_hits' );
		await page.locator( '#download_rss_limit' ).fill( '1' );
		await saveSettings( page );

		expect( option( 'rss.sortby' ) ).toBe( 'file_hits' );
		expect( option( 'rss.limit' ) ).toBe( '1' );

		// Sorted descending, always, so the busiest file is the only item left
		// once the limit is one.
		const body = await ( await page.request.get( '/?dl_name=rss' ) ).text();

		expect( body ).toContain( files.tool.name );
		expect( body ).not.toContain( files.manual.name );
	} );

	test( 'the WP-Stats toggle and its row limit both save, and the toggle unticks', async ( {
		page,
	} ) => {
		await openSettings( page );

		// An unticked checkbox posts nothing at all, so "off" is only
		// distinguishable from "this tab was not submitted" because another field
		// on the tab always posts. Unticking it and getting a stored 0 back is the
		// test of that.
		await page.locator( '#download_stats_display' ).uncheck();
		await page.locator( '#download_stats_most_limit' ).fill( '25' );
		await saveSettings( page );

		expect( option( 'stats_display' ) ).toBe( '0' );
		expect( option( 'stats_most_limit' ) ).toBe( '25' );
		await expect( page.locator( '#download_stats_display' ) ).not.toBeChecked();

		await page.locator( '#download_stats_display' ).check();
		await saveSettings( page );

		expect( option( 'stats_display' ) ).toBe( '1' );
		await expect( page.locator( '#download_stats_display' ) ).toBeChecked();
	} );

	test( 'a template saved on the templates tab is what the front end renders', async ( {
		page,
	} ) => {
		const marker = uniqueName( 'Header from the settings screen' );

		await openSettings( page, 'templates' );
		await page.locator( '#download_template_header' ).fill( `<p>${ marker } %TOTAL_FILES_COUNT%</p>` );
		await saveSettings( page );

		await page.goto( downloads.link );
		await expect( listing( page ) ).toContainText( `${ marker } 3` );
	} );

	test( 'the two halves of a paired template are stored and used separately', async ( {
		page,
	} ) => {
		await openSettings( page, 'templates' );
		await page.locator( '#download_template_listing' ).fill( '<p>ALLOWED %FILE_NAME%</p>' );
		await page.locator( '#download_template_listing_2' ).fill( '<p>REFUSED %FILE_NAME%</p>' );
		await saveSettings( page );

		// The administrator may download everything, so every row uses the first
		// of the pair.
		await page.goto( downloads.link );
		await expect( listing( page ) ).toContainText( `ALLOWED ${ files.members.name }` );
		await expect( listing( page ) ).not.toContainText( 'REFUSED' );

		// A visitor with no session may not have the members' bundle, so that one
		// row -- and only that one -- uses the second.
		const { context, visitor } = await anonymously( page );
		await visitor.goto( downloads.link );
		await expect( listing( visitor ) ).toContainText( `REFUSED ${ files.members.name }` );
		await expect( listing( visitor ) ).toContainText( `ALLOWED ${ files.manual.name }` );
		await context.close();
	} );

	test( 'the download page link template is what the widget prints under its list', async ( {
		page,
	} ) => {
		// The one template with no other surface. It is rendered by the widget and
		// by nothing else, so a change to it that never reached the sidebar would
		// go unnoticed everywhere but here.
		const marker = uniqueName( 'Straight to the library' );

		await openSettings( page, 'templates' );
		await page
			.locator( '#download_template_download_page_link' )
			.fill( `<p><a href="%DOWNLOAD_PAGE_URL%">${ marker }</a></p>` );
		await saveSettings( page );

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

		await page.goto( downloads.link );

		const link = page.getByRole( 'link', { name: marker } );
		await expect( link ).toHaveCount( 1 );
		await expect( link ).toHaveAttribute( 'href', downloads.link );

		wpEval(
			`delete_option( 'widget_downloads' );
			$sidebars = (array) get_option( 'sidebars_widgets', array() );
			$sidebars['sidebar-1'] = array();
			update_option( 'sidebars_widgets', $sidebars );
			echo '<<<done>>>';`,
		);
	} );

	test( 'a template carrying a script is stripped before it is stored', async ( { page } ) => {
		await openSettings( page, 'templates' );

		await page
			.locator( '#download_template_none' )
			.fill( '<p onclick="window.__pwned = 1">none</p><script>window.__pwned = 1;</script>' );
		await saveSettings( page );

		const stored = option( 'templates.none' );

		expect( stored ).not.toContain( '<script' );
		expect( stored ).not.toContain( 'onclick' );
		expect( stored ).toContain( 'none' );
	} );

	test( 'Restore Default Template puts the stock markup back in the field', async ( { page } ) => {
		await openSettings( page, 'templates' );

		await page.locator( '#download_template_none' ).fill( 'nothing here' );
		await page.locator( '.download-template-reset[data-target="download_template_none"]' ).click();

		// The button is pure client-side: it writes the default the server
		// localised into the page, and it does not save. Both halves matter --
		// the field changes, and the stored value has not.
		await expect( page.locator( '#download_template_none' ) ).toHaveValue(
			/No Files Found\./,
		);
		expect( option( 'templates.none' ) ).toContain( 'No Files Found.' );
	} );

	test( 'the Screen Options per-page value pages the admin list', async ( { page } ) => {
		await page.goto( LIST_URL );

		await page.getByRole( 'button', { name: 'Screen Options' } ).click();
		await page.locator( '#wp_downloadmanager_per_page' ).fill( '1' );
		// The Screen Options panel's own Apply, by id: the list table prints two
		// more buttons reading "Apply" for its bulk actions, one at each end.
		await page.locator( '#screen-options-apply' ).click();

		await expect( page.locator( '.wp-list-table tbody tr' ) ).toHaveCount( 1 );
		await expect( page.locator( '.tablenav-pages .total-pages' ) ).toHaveText( '3' );

		// Per user, not per site, which is what the set-screen-option filter is
		// for -- and what makes it worth restoring rather than leaving behind.
		expect(
			wpEval(
				`echo '<<<' . get_user_meta( get_current_user_id(), 'wp_downloadmanager_per_page', true ) . '>>>';`,
			),
		).toBe( '1' );

		wpEval(
			`delete_user_meta( get_current_user_id(), 'wp_downloadmanager_per_page' );
			echo '<<<done>>>';`,
		);
	} );

	test( 'saving the settings says so', async ( { page } ) => {
		await openSettings( page );

		await page.locator( '#download_rss_limit' ).fill( '15' );
		await page.getByRole( 'button', { name: 'Save Changes' } ).click();

		// options.php registers "Settings saved." under the general slug. A
		// settings page that scopes settings_errors() to its own option filters
		// that notice out, and an admin gets no confirmation that anything
		// happened at all -- the setting saves silently, which looks exactly like
		// a form that did nothing.
		await expect( page.locator( '.notice-success, .settings-error' ).first() ).toContainText(
			'Settings saved.',
		);
	} );
} );
