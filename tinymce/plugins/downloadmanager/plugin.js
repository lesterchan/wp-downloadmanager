( function() {
	'use strict';

	tinymce.PluginManager.add( 'downloadmanager', function( editor ) {
		editor.addCommand( 'WP-DownloadManager-Insert_Download', function() {
			// jQuery.trim() before, which was the only reason this file needed
			// jQuery at all. Note prompt() returns null on cancel, which the old
			// jQuery.trim( null ) quietly turned into the string "null".
			let id = window.prompt(
				tinymce.translate( 'Enter File ID (Separate Multiple IDs By A Comma)' ),
			);

			if ( id === null ) {
				return;
			}

			id = id.trim();

			if ( id === '' ) {
				return;
			}

			editor.insertContent( '[download id="' + id + '"]' );
		} );

		editor.addButton( 'downloadmanager', {
			text: false,
			tooltip: tinymce.translate( 'Insert File Download' ),
			icon: 'downloadmanager dashicons-before dashicons-download',
			onclick() {
				editor.execCommand( 'WP-DownloadManager-Insert_Download' );
			},
		} );
	} );
}() );
