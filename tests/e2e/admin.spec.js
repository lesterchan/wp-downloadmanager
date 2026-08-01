/**
 * Managing the download library from wp-admin.
 *
 * Adding, editing and deleting a file all happen through screens a person fills
 * in, so these tests fill them in the same way and then check the far end: the
 * stored row, or the front end the work was for. The delete button puts up a
 * native confirm(), which is answered both ways -- a dismissed confirm that
 * deleted anyway would be worse than no confirm at all.
 *
 * Every file here is a real file in the downloads directory. The Add File
 * screen builds its Browse select by scanning that directory, so a fixture that
 * only existed as a table row could not be added through the screen at all.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	ADD_URL,
	LIST_URL,
	PERMISSION,
	addFile,
	createDownload,
	createDownloadsPage,
	deleteAllDownloads,
	downloadColumn,
	listRow,
	listing,
	removeDownloadFiles,
	resetOptions,
	setOption,
	submitFileForm,
	uniqueName,
	usePrettyPermalinks,
	wpEval,
	writeDownloadFile,
} = require( './helpers.js' );

/**
 * The names of the files this suite has written into the downloads directory.
 *
 * @return {string[]} File names, without their directory.
 */
function filesOnDisk() {
	return JSON.parse(
		wpEval(
			`$dir = WP_DownloadManager_Options::get( 'path.dir' );
			$names = array_map( 'basename', (array) glob( $dir . '/e2e-*' ) );
			echo '<<<' . wp_json_encode( array_values( $names ) ) . '>>>';`,
		),
	);
}

test.describe( 'Managing downloads', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		// Every spec file asks for these, so no file depends on having run after
		// another one that switched them on.
		usePrettyPermalinks();
		await requestUtils.deleteAllPages();
	} );

	test.beforeEach( async () => {
		resetOptions();
		deleteAllDownloads();
		removeDownloadFiles();
	} );

	test.afterAll( async () => {
		removeDownloadFiles();
		deleteAllDownloads();
		resetOptions();
	} );

	test( 'the fixture really is a file on disk that the Add File screen offers', async ( {
		page,
	} ) => {
		// The precondition the rest of this file leans on. Without it every "add
		// a file" test would still pass -- against whatever else happens to be in
		// the downloads directory -- while proving nothing about the fixture.
		const stored = writeDownloadFile( 'e2e-precondition.txt' );
		expect( stored ).toBe( '/e2e-precondition.txt' );

		await page.goto( ADD_URL );

		await expect(
			page.locator( 'select[name="file"] option', { hasText: 'e2e-precondition.txt' } ),
		).toHaveCount( 1 );
	} );

	test( 'no field on the Add File form blocks the form from being submitted', async ( {
		page,
	} ) => {
		writeDownloadFile( 'e2e-validity.txt' );

		await page.goto( ADD_URL );

		// The browser's own constraint validation, asked before anything is
		// typed. A form that is already invalid on arrival cannot be submitted at
		// all: the browser cancels the submit, moves focus to the offending field
		// and shows a bubble, and no request is ever made -- which looks exactly
		// like a button that does nothing.
		const invalid = await page.evaluate( () => {
			const form = document.querySelector( '#wpbody-content form' );

			return Array.from( form.elements )
				.filter( ( el ) => el.willValidate && ! el.checkValidity() )
				.map( ( el ) => `${ el.name }="${ el.value }": ${ el.validationMessage }` );
		} );

		expect( invalid ).toEqual( [] );
	} );

	test( 'a file added through the screen appears in the list and on the front end', async ( {
		page,
		requestUtils,
	} ) => {
		const stored = writeDownloadFile( 'e2e-manual.txt', 'the manual, in twenty-two bytes' );
		const name = uniqueName( 'The Manual' );

		const fileId = await addFile( page, {
			file: stored,
			name,
			description: 'Everything you need to know.',
			category: '1',
		} );

		await page.goto( LIST_URL );
		await expect( listRow( page, name ) ).toHaveCount( 1 );

		// The size was detected from the file itself rather than typed, which is
		// what the ticked "Detect the file size automatically" box means.
		expect( downloadColumn( fileId, 'file_size' ) ).toBe( '31' );

		const downloads = await createDownloadsPage( requestUtils, uniqueName( 'Library' ) );
		setOption( 'page_url', downloads.link );
		await page.goto( downloads.link );

		await expect( listing( page ) ).toContainText( name );
		await expect( listing( page ) ).toContainText( 'Everything you need to know.' );
	} );

	test( 'editing a file changes what the front end shows', async ( { page, requestUtils } ) => {
		const stored = writeDownloadFile( 'e2e-before.txt' );
		const before = uniqueName( 'Before edit' );
		const fileId = createDownload( { file: stored, name: before, description: 'Old words.' } );

		const downloads = await createDownloadsPage( requestUtils, uniqueName( 'Edited library' ) );
		setOption( 'page_url', downloads.link );

		await page.goto( LIST_URL );
		const row = listRow( page, before );
		await row.hover();
		await row.getByRole( 'link', { name: 'Edit', exact: true } ).click();

		const after = uniqueName( 'After edit' );
		await page.locator( '#file_name' ).fill( after );
		await page.locator( '#file_des' ).fill( 'New words.' );
		await submitFileForm( page, 'Edit File' );

		// The stored row, not the notice: the notice says the name it was given
		// whether or not the write landed, and the front end renders from the row.
		expect( downloadColumn( fileId, 'file_name' ) ).toBe( after );

		await page.goto( downloads.link );
		await expect( listing( page ) ).toContainText( after );
		await expect( listing( page ) ).toContainText( 'New words.' );
		await expect( listing( page ) ).not.toContainText( before );
	} );

	test( 'the edit screen resets a hit count to zero when asked, and not otherwise', async ( {
		page,
	} ) => {
		const stored = writeDownloadFile( 'e2e-hits.txt' );
		const name = uniqueName( 'Popular' );
		const fileId = createDownload( { file: stored, name, hits: 42 } );

		await page.goto( LIST_URL );
		let row = listRow( page, name );
		await row.hover();
		await row.getByRole( 'link', { name: 'Edit', exact: true } ).click();

		// Saving without ticking the box keeps the count, because the number
		// field was pre-filled with it.
		await submitFileForm( page, 'Edit File' );
		expect( downloadColumn( fileId, 'file_hits' ) ).toBe( '42' );

		await page.goto( LIST_URL );
		row = listRow( page, name );
		await row.hover();
		await row.getByRole( 'link', { name: 'Edit', exact: true } ).click();
		await page.locator( '#reset_filehits' ).check();
		await submitFileForm( page, 'Edit File' );

		expect( downloadColumn( fileId, 'file_hits' ) ).toBe( '0' );
	} );

	test( 'a file uploaded through the form lands in the downloads directory', async ( {
		page,
	} ) => {
		// The second of the form's four sources, and the only one that puts a file
		// on the server. Until 2.0.0 handle_add() passed a hardcoded 0 for the
		// source, so an upload was accepted by the form and then silently dropped
		// in favour of whatever the Browse select happened to hold -- which looks
		// exactly like a working upload right up until somebody opens the file.
		const name = uniqueName( 'Uploaded' );

		await page.goto( ADD_URL );

		await page.locator( '#file_type_1' ).check();
		await page.locator( 'input[name="file_upload"]' ).setInputFiles( {
			name: 'e2e-uploaded.txt',
			mimeType: 'text/plain',
			buffer: Buffer.from( 'uploaded through the form' ),
		} );
		await page.locator( '#file_name' ).fill( name );
		await submitFileForm( page, 'Add File' );

		const notice = page.locator( '#setting-error-wp_downloadmanager_success' );
		await expect( notice ).toContainText( 'added, with file ID' );

		const fileId = parseInt( ( await notice.innerText() ).match( /file ID (\d+)/ )[ 1 ], 10 );

		// The far end is the filesystem and the row, not the notice: the row has
		// to point at the uploaded file and the file has to be where it points.
		expect( downloadColumn( fileId, 'file' ) ).toBe( '/e2e-uploaded.txt' );
		expect( downloadColumn( fileId, 'file_size' ) ).toBe( '25' );
		expect( filesOnDisk() ).toContain( 'e2e-uploaded.txt' );
	} );

	test( 'a remote file is stored as its URL and linked to as it stands', async ( {
		page,
		requestUtils,
	} ) => {
		// The fourth source. A remote row is not a path inside the downloads
		// directory, so everything downstream of it -- the download URL, the
		// listing link, the delete screen's "also remove the file" box -- has to
		// tell the two apart.
		const name = uniqueName( 'Remote release' );
		const url = 'https://example.com/e2e-remote-release.zip';

		await page.goto( ADD_URL );

		await page.locator( '#file_type_2' ).check();
		await page.locator( 'input[name="file_remote"]' ).fill( url );
		await page.locator( '#file_name' ).fill( name );
		await page.getByRole( 'button', { name: 'Add File' } ).click();

		const notice = page.locator( '#setting-error-wp_downloadmanager_success' );
		await expect( notice ).toContainText( 'added, with file ID' );

		const fileId = parseInt( ( await notice.innerText() ).match( /file ID (\d+)/ )[ 1 ], 10 );

		expect( downloadColumn( fileId, 'file' ) ).toBe( url );

		const downloads = await createDownloadsPage( requestUtils, uniqueName( 'Remote library' ) );
		setOption( 'page_url', downloads.link );
		await page.goto( downloads.link );

		// The endpoint hands the visitor on to the other server, so the listing
		// link is the plugin's own URL rather than the remote one -- and the
		// delete screen offers no "remove from the server" box for it.
		await expect( listing( page ).locator( `a[href$="/download/${ fileId }/"]` ) ).toHaveCount( 1 );

		await page.goto( LIST_URL );
		const row = listRow( page, name );
		await row.hover();
		await row.getByRole( 'link', { name: 'Delete', exact: true } ).click();
		await expect( page.locator( '#unlinkfile' ) ).toHaveCount( 0 );
	} );

	test( 'a typed file size is kept when automatic detection is switched off', async ( {
		page,
	} ) => {
		const stored = writeDownloadFile( 'e2e-sized.txt', 'eight!!!' );
		const name = uniqueName( 'Sized by hand' );

		await page.goto( ADD_URL );

		await page.locator( '#file_type_0' ).check();
		await page.locator( 'select[name="file"]' ).selectOption( stored );
		await page.locator( '#file_name' ).fill( name );

		// The checkbox is ticked by default and overwrites whatever is typed, so
		// the typed value only reaches the row when it is cleared. Both halves are
		// asserted: 8 bytes on disk, 4096 in the box.
		await page.locator( '#auto_filesize' ).uncheck();
		await page.locator( '#file_size' ).fill( '4096' );
		await submitFileForm( page, 'Add File' );

		const notice = page.locator( '#setting-error-wp_downloadmanager_success' );
		await expect( notice ).toContainText( 'added, with file ID' );

		const fileId = parseInt( ( await notice.innerText() ).match( /file ID (\d+)/ )[ 1 ], 10 );

		expect( downloadColumn( fileId, 'file_size' ) ).toBe( '4096' );
		await page.goto( LIST_URL );
		await expect( listRow( page, name ) ).toContainText( '4.0 KiB' );
	} );

	test( 'the file date only moves when the box beside it is ticked', async ( { page } ) => {
		const stored = writeDownloadFile( 'e2e-dated.txt' );
		const name = uniqueName( 'Dated' );
		const fileId = createDownload( { file: stored, name, ageInDays: 30 } );
		const before = downloadColumn( fileId, 'file_date' );

		await page.goto( LIST_URL );
		let row = listRow( page, name );
		await row.hover();
		await row.getByRole( 'link', { name: 'Edit', exact: true } ).click();

		// Changing the selects without ticking "Save this date" must not move the
		// row: the six selects are always submitted, so the checkbox is the only
		// thing standing between opening the screen and rewriting the date.
		await page.locator( '#file_timestamp_year' ).selectOption( '2020' );
		await submitFileForm( page, 'Edit File' );
		expect( downloadColumn( fileId, 'file_date' ) ).toBe( before );

		await page.goto( LIST_URL );
		row = listRow( page, name );
		await row.hover();
		await row.getByRole( 'link', { name: 'Edit', exact: true } ).click();

		// "Use today's date" is the script's job: it ticks "Save this date" for
		// you and fills the six selects from the data-today attribute. Driving it
		// rather than the selects is what proves the script is wired up at all.
		await page.locator( '#edit_usetodaydate' ).check();
		await expect( page.locator( '#edit_filetimestamp' ) ).toBeChecked();
		await submitFileForm( page, 'Edit File' );

		const after = parseInt( downloadColumn( fileId, 'file_date' ), 10 );

		expect( after ).toBeGreaterThan( parseInt( before, 10 ) );
	} );

	test( 'a file whose category has been deleted lists as N/A rather than warning', async ( {
		page,
	} ) => {
		// Deleting a category on the settings screen leaves every file in it
		// pointing at an id that is no longer in the list. That used to be an
		// "Undefined array key" warning on each affected row, which the shared
		// config turns into a fatal.
		const orphan = uniqueName( 'Orphaned' );
		createDownload( { file: writeDownloadFile( 'e2e-orphan.txt' ), name: orphan, category: 9 } );

		await page.goto( LIST_URL );

		await expect( listRow( page, orphan ) ).toContainText( 'N/A' );
		await expect( page.locator( '#wpbody-content' ) ).not.toContainText( 'Undefined array key' );
	} );

	test( 'deleting a file removes it, but only once the confirm is accepted', async ( {
		page,
	} ) => {
		const stored = writeDownloadFile( 'e2e-doomed.txt' );
		const name = uniqueName( 'Delete me' );
		const fileId = createDownload( { file: stored, name } );

		await page.goto( LIST_URL );
		const row = listRow( page, name );
		await row.hover();
		await row.getByRole( 'link', { name: 'Delete', exact: true } ).click();

		await expect( page.getByRole( 'heading', { name: 'Delete File' } ) ).toBeVisible();

		// Dismissed first: the confirm is the guard, and a delete that ignored it
		// would take the row anyway.
		page.once( 'dialog', ( dialog ) => dialog.dismiss() );
		await page.getByRole( 'button', { name: 'Delete File' } ).click();
		expect( downloadColumn( fileId, 'file_id' ) ).toBe( String( fileId ) );

		// Accepted: the row goes, and the screen falls through to the list.
		page.once( 'dialog', ( dialog ) => dialog.accept() );
		await page.getByRole( 'button', { name: 'Delete File' } ).click();

		await expect( page.locator( '.wp-list-table' ) ).toBeVisible();
		expect( downloadColumn( fileId, 'file_id' ) ).toBe( '' );
	} );

	test( 'the delete screen leaves the file on disk unless the box is ticked', async ( {
		page,
	} ) => {
		const kept = writeDownloadFile( 'e2e-kept.txt' );
		const unlinked = writeDownloadFile( 'e2e-unlinked.txt' );
		const keptName = uniqueName( 'Row only' );
		const unlinkedName = uniqueName( 'Row and file' );

		createDownload( { file: kept, name: keptName } );
		createDownload( { file: unlinked, name: unlinkedName } );

		for ( const [ name, unlink ] of [
			[ keptName, false ],
			[ unlinkedName, true ],
		] ) {
			await page.goto( LIST_URL );
			const row = listRow( page, name );
			await row.hover();
			await row.getByRole( 'link', { name: 'Delete', exact: true } ).click();

			if ( unlink ) {
				await page.locator( '#unlinkfile' ).check();
			}

			page.once( 'dialog', ( dialog ) => dialog.accept() );
			await page.getByRole( 'button', { name: 'Delete File' } ).click();
			await expect( page.locator( '.wp-list-table' ) ).toBeVisible();
		}

		// The far end is the filesystem, not the table: both rows are gone either
		// way, and the whole point of the checkbox is which file survives it.
		const onDisk = filesOnDisk();
		expect( onDisk ).toContain( 'e2e-kept.txt' );
		expect( onDisk ).not.toContain( 'e2e-unlinked.txt' );
	} );

	test( 'the bulk delete removes only the ticked rows', async ( { page } ) => {
		const doomed = uniqueName( 'Bulk doomed' );
		const spared = uniqueName( 'Bulk spared' );

		const doomedId = createDownload( { file: writeDownloadFile( 'e2e-bulk-a.txt' ), name: doomed } );
		const sparedId = createDownload( { file: writeDownloadFile( 'e2e-bulk-b.txt' ), name: spared } );

		await page.goto( LIST_URL );

		await listRow( page, doomed ).locator( 'input[name="file_ids[]"]' ).check();
		await page.locator( '#bulk-action-selector-top' ).selectOption( 'delete' );
		await page.locator( '#doaction' ).click();

		await expect( page.locator( '#setting-error-wp_downloadmanager_success' ) ).toContainText(
			'1 file deleted',
		);

		expect( downloadColumn( doomedId, 'file_id' ) ).toBe( '' );
		expect( downloadColumn( sparedId, 'file_id' ) ).toBe( String( sparedId ) );
	} );

	test( 'the list table searches names, paths and descriptions', async ( { page } ) => {
		const needle = uniqueName( 'Needle' );
		createDownload( { file: writeDownloadFile( 'e2e-needle.txt' ), name: needle } );
		createDownload( {
			file: writeDownloadFile( 'e2e-haystack.txt' ),
			name: uniqueName( 'Haystack' ),
		} );

		await page.goto( LIST_URL );
		await expect( page.locator( '.wp-list-table tbody tr' ) ).toHaveCount( 2 );

		await page.locator( '#wp-downloadmanager-search-search-input' ).fill( needle );
		await page.getByRole( 'button', { name: 'Search Files' } ).click();

		await expect( page.locator( '.wp-list-table tbody tr' ) ).toHaveCount( 1 );
		await expect( page.locator( '.wp-list-table' ) ).toContainText( needle );
	} );

	test( 'the list table sorts by hits when its column heading is clicked', async ( { page } ) => {
		createDownload( { file: writeDownloadFile( 'e2e-low.txt' ), name: uniqueName( 'Low' ), hits: 1 } );
		createDownload( {
			file: writeDownloadFile( 'e2e-high.txt' ),
			name: uniqueName( 'High' ),
			hits: 500,
		} );

		await page.goto( LIST_URL );
		await page.locator( 'th#file_hits a' ).click();

		// Ascending first, so the small count leads; the link then flips.
		await expect( page.locator( '.wp-list-table tbody tr' ).first() ).toContainText( 'Low' );

		await page.locator( 'th#file_hits a' ).click();
		await expect( page.locator( '.wp-list-table tbody tr' ).first() ).toContainText( 'High' );
	} );

	test( 'the library totals add up the whole table', async ( { page } ) => {
		createDownload( {
			file: writeDownloadFile( 'e2e-t1.txt' ),
			name: uniqueName( 'One' ),
			size: 2048,
			hits: 3,
		} );
		createDownload( {
			file: writeDownloadFile( 'e2e-t2.txt' ),
			name: uniqueName( 'Two' ),
			size: 4096,
			hits: 7,
		} );

		await page.goto( LIST_URL );

		const totals = page.locator( 'h2', { hasText: 'Download Stats' } ).locator( '+ table' );
		await expect( totals.locator( 'tr', { hasText: 'Total Files' } ) ).toContainText( '2' );
		await expect( totals.locator( 'tr', { hasText: 'Total Size' } ) ).toContainText( '6.0 KiB' );
		await expect( totals.locator( 'tr', { hasText: 'Total Hits' } ) ).toContainText( '10' );
	} );

	test( 'a hidden file is listed for an admin and invisible to the front end', async ( {
		page,
		requestUtils,
	} ) => {
		const hidden = uniqueName( 'Hidden file' );
		const shown = uniqueName( 'Shown file' );

		createDownload( {
			file: writeDownloadFile( 'e2e-hidden.txt' ),
			name: hidden,
			permission: PERMISSION.hidden,
		} );
		createDownload( { file: writeDownloadFile( 'e2e-shown.txt' ), name: shown } );

		await page.goto( LIST_URL );
		await expect( listRow( page, hidden ) ).toHaveCount( 1 );
		await expect( listRow( page, hidden ) ).toContainText( 'Hidden' );

		const downloads = await createDownloadsPage( requestUtils, uniqueName( 'Hidden library' ) );
		setOption( 'page_url', downloads.link );
		await page.goto( downloads.link );

		await expect( listing( page ) ).toContainText( shown );
		await expect( listing( page ) ).not.toContainText( hidden );
	} );
} );
