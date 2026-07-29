/**
 * Tests for js/wp-downloadmanager-admin.js, the template reset buttons.
 *
 * The stock markup used to be duplicated inside the script as a switch
 * statement, and had already fallen out of step with the defaults written on
 * activation. It comes from wp_localize_script() now, so the interesting
 * behaviour is that the button reads the right key and writes the right field.
 */
import { beforeAll, beforeEach, describe, expect, it } from 'vitest';
import { click, loadScript } from './helpers.js';

const DEFAULTS = {
	header: '<p>Default header</p>',
	footer: '<form action="%DOWNLOAD_PAGE_URL%"></form>',
	listing: '<p>Permitted listing</p>',
	listing_2: '<p>Denied listing</p>',
	pagingheader: '',
};

beforeAll( () => {
	window.wpDownloadManagerL10n = { templates: DEFAULTS };
	loadScript( 'js/wp-downloadmanager-admin.js' );
} );

beforeEach( () => {
	document.body.innerHTML = `
		<button class="button download-template-reset"
			data-template="header" data-target="download_template_header">Restore</button>
		<textarea id="download_template_header">edited by hand</textarea>

		<button class="button download-template-reset"
			data-template="listing_2" data-target="download_template_listing_2">Restore</button>
		<textarea id="download_template_listing_2">edited too</textarea>

		<button class="button download-template-reset"
			data-template="pagingheader" data-target="download_template_pagingheader">Restore</button>
		<textarea id="download_template_pagingheader">not empty</textarea>
	`;
} );

describe( 'restore default template', () => {
	it( 'writes the stock markup into the matching textarea', () => {
		click( document.querySelector( '[data-template="header"]' ) );

		expect( document.getElementById( 'download_template_header' ).value ).toBe(
			DEFAULTS.header,
		);
	} );

	it( 'touches only its own field', () => {
		click( document.querySelector( '[data-template="header"]' ) );

		expect( document.getElementById( 'download_template_listing_2' ).value ).toBe(
			'edited too',
		);
	} );

	it( 'handles the second half of a permission pair', () => {
		click( document.querySelector( '[data-template="listing_2"]' ) );

		expect( document.getElementById( 'download_template_listing_2' ).value ).toBe(
			DEFAULTS.listing_2,
		);
	} );

	it( 'restores a template whose default is the empty string', () => {
		// hasOwnProperty rather than a truthiness check: two of the templates
		// ship empty, and a falsy test would refuse to restore them.
		click( document.querySelector( '[data-template="pagingheader"]' ) );

		expect( document.getElementById( 'download_template_pagingheader' ).value ).toBe( '' );
	} );

	it( 'does not submit the form it sits in', () => {
		const button = document.querySelector( '[data-template="header"]' );
		const event = new window.MouseEvent( 'click', { bubbles: true, cancelable: true } );

		button.dispatchEvent( event );

		expect( event.defaultPrevented ).toBe( true );
	} );

	it( 'ignores a key with no default behind it', () => {
		document.body.innerHTML += `
			<button class="download-template-reset"
				data-template="nonexistent" data-target="download_template_header">Restore</button>
		`;

		click( document.querySelector( '[data-template="nonexistent"]' ) );

		expect( document.getElementById( 'download_template_header' ).value ).toBe(
			'edited by hand',
		);
	} );

	it( 'ignores a missing target field', () => {
		document.body.innerHTML += `
			<button class="download-template-reset"
				data-template="header" data-target="does_not_exist">Restore</button>
		`;

		expect( () =>
			click( document.querySelector( '[data-target="does_not_exist"]' ) ),
		).not.toThrow();
	} );

	it( 'ignores clicks elsewhere on the page', () => {
		document.body.innerHTML += '<button id="save">Save Changes</button>';

		click( document.getElementById( 'save' ) );

		expect( document.getElementById( 'download_template_header' ).value ).toBe(
			'edited by hand',
		);
	} );

	it( 'works when the click lands on something inside the button', () => {
		document.body.innerHTML = `
			<button class="download-template-reset"
				data-template="header" data-target="download_template_header"><span id="label">Restore</span></button>
			<textarea id="download_template_header">edited by hand</textarea>
		`;

		click( document.getElementById( 'label' ) );

		expect( document.getElementById( 'download_template_header' ).value ).toBe(
			DEFAULTS.header,
		);
	} );
} );
