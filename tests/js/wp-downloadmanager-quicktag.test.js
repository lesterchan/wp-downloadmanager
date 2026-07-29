/**
 * Tests for js/wp-downloadmanager-quicktag.js, the Text tab button.
 *
 * This was an inline <script> printed into admin_footer that called
 * jQuery.trim( prompt( ... ) ). The guard on the entered id is the interesting
 * part: jQuery.trim( null ) returns the empty string, but a bare .trim() on the
 * null that prompt() returns when cancelled would throw.
 */
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { loadScript } from './helpers.js';

let callback;

beforeAll( () => {
	window.wpDownloadManagerL10n = {
		quicktag: {
			label: 'Download',
			prompt: 'Enter File ID (Separate Multiple IDs By A Comma)',
		},
	};

	window.QTags = {
		addButton: vi.fn( ( id, label, fn ) => {
			callback = { id, label, fn };
		} ),
		insertContent: vi.fn(),
	};

	loadScript( 'js/wp-downloadmanager-quicktag.js' );
} );

beforeEach( () => {
	window.QTags.insertContent.mockClear();
} );

describe( 'quicktag registration', () => {
	it( 'registers a button with the localised label', () => {
		expect( callback.id ).toBe( 'ed_wp_downloadmanager' );
		expect( callback.label ).toBe( 'Download' );
	} );
} );

describe( 'inserting a download', () => {
	it( 'inserts the shortcode for the entered id', () => {
		vi.spyOn( window, 'prompt' ).mockReturnValue( '42' );

		callback.fn();

		expect( window.QTags.insertContent ).toHaveBeenCalledWith( '[download id="42"]' );
	} );

	it( 'passes the localised prompt text', () => {
		const prompt = vi.spyOn( window, 'prompt' ).mockReturnValue( '1' );

		callback.fn();

		expect( prompt ).toHaveBeenCalledWith(
			'Enter File ID (Separate Multiple IDs By A Comma)',
		);
	} );

	it( 'accepts a comma separated list', () => {
		vi.spyOn( window, 'prompt' ).mockReturnValue( '1,2,3' );

		callback.fn();

		expect( window.QTags.insertContent ).toHaveBeenCalledWith( '[download id="1,2,3"]' );
	} );

	it( 'trims what was typed', () => {
		vi.spyOn( window, 'prompt' ).mockReturnValue( '  7  ' );

		callback.fn();

		expect( window.QTags.insertContent ).toHaveBeenCalledWith( '[download id="7"]' );
	} );

	it( 'inserts nothing when cancelled', () => {
		// prompt() returns null on cancel. jQuery.trim( null ) gave "", which
		// the old truthiness check then rejected; a bare .trim() would throw.
		vi.spyOn( window, 'prompt' ).mockReturnValue( null );

		expect( () => callback.fn() ).not.toThrow();
		expect( window.QTags.insertContent ).not.toHaveBeenCalled();
	} );

	it( 'inserts nothing for an empty entry', () => {
		vi.spyOn( window, 'prompt' ).mockReturnValue( '   ' );

		callback.fn();

		expect( window.QTags.insertContent ).not.toHaveBeenCalled();
	} );
} );
