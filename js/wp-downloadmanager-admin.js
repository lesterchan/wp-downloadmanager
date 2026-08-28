/**
 * Behaviour for every WP-DownloadManager admin screen.
 *
 * Values arrive in data attributes rather than spliced into inline handlers,
 * which is where these screens used to hide their escaping bugs. One delegated
 * listener on document per behaviour, so a control added later still works.
 */
( function() {
	'use strict';

	const DATE_PARTS = [ 'day', 'month', 'year', 'hour', 'minute', 'second' ];

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
		DATE_PARTS.forEach( function( field ) {
			const select = document.getElementById( 'file_timestamp_' + field );

			if ( select && Object.prototype.hasOwnProperty.call( values, field ) ) {
				select.value = String( values[ field ] );
			}
		} );
	}

	onReady( function() {
		const defaults = ( window.wpDownloadManagerL10n || {} ).templates || {};

		// Restore Default Template writes the stock markup into its own field.
		document.addEventListener( 'click', function( event ) {
			const button = event.target.closest( '.download-template-reset' );

			if ( ! button ) {
				return;
			}

			event.preventDefault();

			const key = button.getAttribute( 'data-template' );
			const target = document.getElementById( button.getAttribute( 'data-target' ) );

			if ( ! target || ! Object.prototype.hasOwnProperty.call( defaults, key ) ) {
				return;
			}

			target.value = defaults[ key ];
		} );

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

		// Delegated like the rest rather than bound straight to the checkbox:
		// binding directly only works if the element already exists when this
		// runs, which silently does nothing everywhere else.
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
