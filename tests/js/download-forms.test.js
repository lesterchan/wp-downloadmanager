/**
 * Tests for download-forms.js.
 *
 * This script replaced four kinds of inline handler on the Add / Edit / Delete
 * screens: onclick= radio selection, a jQuery "use today's date" function with
 * both sets of date parts interpolated into JavaScript string literals, a
 * confirm() with the file name spliced into a string, and history.go(-1)
 * cancels. Each of those is a behaviour worth pinning.
 */
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import {
	click,
	fire,
	loadScript,
	timestampSelects,
	timestampValues,
} from './helpers.js';

const ACTUAL = { day: 15, month: 6, year: 2020, hour: 8, minute: 30, second: 0 };
const TODAY = { day: 27, month: 7, year: 2026, hour: 14, minute: 5, second: 45 };

beforeAll( () => {
	// The script attaches its listeners on DOMContentLoaded, and jsdom reports
	// the document as already complete, so it wires up immediately.
	loadScript( 'download-forms.js' );
} );

beforeEach( () => {
	document.body.innerHTML = '';
} );

describe( 'file source radios', () => {
	beforeEach( () => {
		document.body.innerHTML = `
			<input type="radio" id="file_type_0" name="file_type" value="0" />
			<select name="file" data-checks="file_type_0"><option>a</option></select>
			<input type="radio" id="file_type_1" name="file_type" value="1" />
			<input type="file" name="file_upload" data-checks="file_type_1" />
			<input type="radio" id="file_type_2" name="file_type" value="2" />
			<input type="text" name="file_remote" data-checks="file_type_2" />
		`;
	} );

	it( 'ticks the matching radio when a source field is focused', () => {
		fire( document.querySelector( '[name="file_upload"]' ), 'focusin' );

		expect( document.getElementById( 'file_type_1' ).checked ).toBe( true );
		expect( document.getElementById( 'file_type_0' ).checked ).toBe( false );
	} );

	it( 'ticks the radio for each of the three sources', () => {
		[ 'file', 'file_upload', 'file_remote' ].forEach( ( name, index ) => {
			fire( document.querySelector( `[name="${ name }"]` ), 'focusin' );
			expect( document.getElementById( `file_type_${ index }` ).checked ).toBe( true );
		} );
	} );

	it( 'ignores fields with no data-checks', () => {
		document.body.innerHTML += '<input id="unrelated" />';

		fire( document.getElementById( 'unrelated' ), 'focusin' );

		expect( document.getElementById( 'file_type_0' ).checked ).toBe( false );
	} );

	it( 'does nothing when the named radio is absent', () => {
		document.body.innerHTML = '<input id="orphan" data-checks="does_not_exist" />';

		expect( () => fire( document.getElementById( 'orphan' ), 'focusin' ) ).not.toThrow();
	} );
} );

describe( 'destructive confirmations', () => {
	beforeEach( () => {
		document.body.innerHTML = `
			<input type="submit" id="del" data-confirm="Delete O'Brien &amp; Co?" />
			<input type="submit" id="plain" />
		`;
	} );

	it( 'asks before submitting, using the message verbatim', () => {
		const confirm = vi.spyOn( window, 'confirm' ).mockReturnValue( true );

		click( document.getElementById( 'del' ) );

		// The message is a plain attribute value, so the file name arrives
		// unescaped rather than through esc_js( esc_attr( ... ) ).
		expect( confirm ).toHaveBeenCalledWith( "Delete O'Brien & Co?" );
	} );

	it( 'cancels the submit when the confirmation is declined', () => {
		vi.spyOn( window, 'confirm' ).mockReturnValue( false );

		const button = document.getElementById( 'del' );
		const event = new window.MouseEvent( 'click', { bubbles: true, cancelable: true } );
		button.dispatchEvent( event );

		expect( event.defaultPrevented ).toBe( true );
	} );

	it( 'lets the submit through when confirmed', () => {
		vi.spyOn( window, 'confirm' ).mockReturnValue( true );

		const button = document.getElementById( 'del' );
		const event = new window.MouseEvent( 'click', { bubbles: true, cancelable: true } );
		button.dispatchEvent( event );

		expect( event.defaultPrevented ).toBe( false );
	} );

	it( 'does not ask on a button with no confirmation', () => {
		const confirm = vi.spyOn( window, 'confirm' ).mockReturnValue( true );

		click( document.getElementById( 'plain' ) );

		expect( confirm ).not.toHaveBeenCalled();
	} );
} );

describe( 'cancel button', () => {
	it( 'goes back rather than submitting', () => {
		document.body.innerHTML = '<button class="button download-cancel">Cancel</button>';
		const go = vi.spyOn( window.history, 'go' ).mockImplementation( () => {} );

		const button = document.querySelector( '.download-cancel' );
		const event = new window.MouseEvent( 'click', { bubbles: true, cancelable: true } );
		button.dispatchEvent( event );

		expect( go ).toHaveBeenCalledWith( -1 );
		expect( event.defaultPrevented ).toBe( true );
	} );
} );

describe( 'use today\'s date', () => {
	const values = [
		...new Set( [ ...Object.values( ACTUAL ), ...Object.values( TODAY ) ] ),
	];

	beforeEach( () => {
		document.body.innerHTML = `
			<input type="checkbox" id="edit_filetimestamp" />
			<input type="checkbox" id="edit_usetodaydate"
				data-actual='${ JSON.stringify( ACTUAL ) }'
				data-today='${ JSON.stringify( TODAY ) }' />
			${ timestampSelects( { values, selected: ACTUAL } ) }
		`;
	} );

	it( 'fills in today when ticked', () => {
		const toggle = document.getElementById( 'edit_usetodaydate' );
		toggle.checked = true;
		fire( toggle, 'change' );

		expect( timestampValues() ).toEqual(
			Object.fromEntries( Object.entries( TODAY ).map( ( [ k, v ] ) => [ k, String( v ) ] ) ),
		);
	} );

	it( 'also ticks the edit-timestamp box, so the value is actually saved', () => {
		const toggle = document.getElementById( 'edit_usetodaydate' );
		toggle.checked = true;
		fire( toggle, 'change' );

		expect( document.getElementById( 'edit_filetimestamp' ).checked ).toBe( true );
	} );

	it( 'restores the stored date when unticked', () => {
		const toggle = document.getElementById( 'edit_usetodaydate' );

		toggle.checked = true;
		fire( toggle, 'change' );
		toggle.checked = false;
		fire( toggle, 'change' );

		expect( timestampValues() ).toEqual(
			Object.fromEntries( Object.entries( ACTUAL ).map( ( [ k, v ] ) => [ k, String( v ) ] ) ),
		);
		expect( document.getElementById( 'edit_filetimestamp' ).checked ).toBe( false );
	} );

	it( 'survives malformed date attributes', () => {
		const toggle = document.getElementById( 'edit_usetodaydate' );
		toggle.setAttribute( 'data-today', 'not json' );
		toggle.checked = true;

		expect( () => fire( toggle, 'change' ) ).not.toThrow();
	} );

	it( 'leaves selects alone when the payload omits them', () => {
		const toggle = document.getElementById( 'edit_usetodaydate' );
		toggle.setAttribute( 'data-today', JSON.stringify( { day: 27 } ) );
		toggle.checked = true;
		fire( toggle, 'change' );

		expect( document.getElementById( 'file_timestamp_day' ).value ).toBe( '27' );
		// Untouched, so still showing the stored month.
		expect( document.getElementById( 'file_timestamp_month' ).value ).toBe( String( ACTUAL.month ) );
	} );
} );
