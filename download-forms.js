/**
 * The Add A File / Edit A File / Delete A File screens.
 *
 * Replaces four kinds of inline handler that used to be printed into the
 * markup: onclick= radio selection, a jQuery "use today's date" function with
 * both sets of date parts interpolated into string literals, a confirm() with
 * the file name spliced into a JavaScript string, and history.go(-1) cancels.
 * The file name in particular was going through esc_js( esc_attr( ... ) ),
 * which is where these screens hid their escaping bugs.
 */
( function() {
	'use strict';

	const FIELDS = [ 'day', 'month', 'year', 'hour', 'minute', 'second' ];

	function onReady( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
			return;
		}
		document.addEventListener( 'DOMContentLoaded', fn );
	}

	function parse( element, attribute ) {
		try {
			return JSON.parse( element.getAttribute( attribute ) ) || {};
		} catch {
			return {};
		}
	}

	function applyDate( values ) {
		FIELDS.forEach( function( field ) {
			const select = document.getElementById( 'file_timestamp_' + field );

			if ( select && Object.prototype.hasOwnProperty.call( values, field ) ) {
				select.value = String( values[ field ] );
			}
		} );
	}

	onReady( function() {
		// Selecting a file source ticks its radio.
		document.addEventListener( 'focusin', function( event ) {
			const field = event.target.closest( '[data-checks]' );

			if ( ! field ) {
				return;
			}

			const radio = document.getElementById( field.getAttribute( 'data-checks' ) );

			if ( radio ) {
				radio.checked = true;
			}
		} );

		// Destructive submits confirm first.
		document.addEventListener( 'click', function( event ) {
			const button = event.target.closest( '[data-confirm]' );

			if ( button && ! window.confirm( button.getAttribute( 'data-confirm' ) ) ) {
				event.preventDefault();
			}
		} );

		// Cancel goes back.
		document.addEventListener( 'click', function( event ) {
			if ( event.target.closest( '.download-cancel' ) ) {
				event.preventDefault();
				window.history.go( -1 );
			}
		} );

		// Delegated like the three above rather than bound straight to the
		// checkbox: binding directly only works if the element already exists
		// when this runs, which silently does nothing everywhere else.
		document.addEventListener( 'change', function( event ) {
			const toggle = event.target.closest( '#edit_usetodaydate' );

			if ( ! toggle ) {
				return;
			}

			const useToday = toggle.checked;
			const edit = document.getElementById( 'edit_filetimestamp' );

			if ( edit ) {
				edit.checked = useToday;
			}

			applyDate( parse( toggle, useToday ? 'data-today' : 'data-actual' ) );
		} );
	} );
}() );
