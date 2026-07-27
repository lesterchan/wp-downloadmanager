/**
 * Download Templates screen.
 *
 * One delegated listener rather than an inline onclick= per button, and the
 * stock markup comes from wp_localize_script() rather than a copy of every
 * template duplicated inside this file - which is how the reset buttons used to
 * drift out of step with the defaults written on activation.
 */
( function() {
	'use strict';

	function onReady( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
			return;
		}
		document.addEventListener( 'DOMContentLoaded', fn );
	}

	onReady( function() {
		const defaults = window.downloadManagerDefaults || {};

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
	} );
}() );
