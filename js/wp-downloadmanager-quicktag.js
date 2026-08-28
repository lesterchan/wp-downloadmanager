/** The Download quicktag on the Text tab of the classic editor. */
( function() {
	'use strict';

	if ( typeof window.QTags === 'undefined' ) {
		return;
	}

	const l10n = ( window.wpDownloadManagerL10n || {} ).quicktag || {};

	window.QTags.addButton( 'ed_wp_downloadmanager', l10n.label || 'Download', function() {
		let id = window.prompt( l10n.prompt || '' );

		if ( id === null ) {
			return;
		}

		id = id.trim();

		if ( id === '' ) {
			return;
		}

		window.QTags.insertContent( '[download id="' + id + '"]' );
	} );
}() );
