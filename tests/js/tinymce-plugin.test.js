/**
 * Tests for the TinyMCE "Insert File Download" button.
 *
 * The plugin registers itself against TinyMCE's own API rather than the DOM, so
 * the editor is stubbed and the registered command invoked directly. Two things
 * changed in 2.0.0 and both are pinned here: the jQuery.trim() that was this
 * file's only reason to need jQuery, and the button acting on
 * tinyMCE.activeEditor rather than on the editor it was registered for.
 */
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { loadScript } from './helpers.js';

let command;
let button;
let editor;

beforeAll( () => {
	let register;

	window.tinymce = {
		PluginManager: {
			add: ( name, cb ) => {
				register = { name, cb };
			},
		},
		translate: ( text ) => text,
	};
	// Present so a regression back to tinyMCE.activeEditor would be visible
	// rather than a ReferenceError.
	window.tinyMCE = { activeEditor: { execCommand: vi.fn() } };

	loadScript( 'tinymce/plugins/downloadmanager/plugin.js' );

	expect( register.name ).toBe( 'downloadmanager' );

	editor = {
		addCommand: vi.fn( ( name, cb ) => {
			command = { name, cb };
		} ),
		addButton: vi.fn( ( name, config ) => {
			button = { name, config };
		} ),
		execCommand: vi.fn(),
		insertContent: vi.fn(),
	};

	register.cb( editor );
} );

beforeEach( () => {
	editor.insertContent.mockClear();
	editor.execCommand.mockClear();
	window.tinyMCE.activeEditor.execCommand.mockClear();
} );

describe( 'registration', () => {
	it( 'registers the insert command', () => {
		expect( command.name ).toBe( 'WP-DownloadManager-Insert_Download' );
	} );

	it( 'registers a toolbar button with a translated tooltip', () => {
		expect( button.name ).toBe( 'downloadmanager' );
		expect( button.config.tooltip ).toBe( 'Insert File Download' );
		expect( button.config.text ).toBe( false );
	} );

	it( 'runs the command on its own editor, not the active one', () => {
		button.config.onclick();

		expect( editor.execCommand ).toHaveBeenCalledWith( 'WP-DownloadManager-Insert_Download' );
		expect( window.tinyMCE.activeEditor.execCommand ).not.toHaveBeenCalled();
	} );
} );

describe( 'inserting a download', () => {
	it( 'inserts the shortcode for the entered id', () => {
		vi.spyOn( window, 'prompt' ).mockReturnValue( '42' );

		command.cb();

		expect( editor.insertContent ).toHaveBeenCalledWith( '[download id="42"]' );
	} );

	it( 'trims what was typed', () => {
		vi.spyOn( window, 'prompt' ).mockReturnValue( '  1,2  ' );

		command.cb();

		expect( editor.insertContent ).toHaveBeenCalledWith( '[download id="1,2"]' );
	} );

	it( 'inserts nothing when cancelled', () => {
		vi.spyOn( window, 'prompt' ).mockReturnValue( null );

		expect( () => command.cb() ).not.toThrow();
		expect( editor.insertContent ).not.toHaveBeenCalled();
	} );

	it( 'inserts nothing for an empty entry', () => {
		vi.spyOn( window, 'prompt' ).mockReturnValue( '   ' );

		command.cb();

		expect( editor.insertContent ).not.toHaveBeenCalled();
	} );

	it( 'asks with the translated prompt', () => {
		const prompt = vi.spyOn( window, 'prompt' ).mockReturnValue( '1' );

		command.cb();

		expect( prompt ).toHaveBeenCalledWith(
			'Enter File ID (Separate Multiple IDs By A Comma)',
		);
	} );
} );
