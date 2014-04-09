(function() {
	tinymce.PluginManager.add('downloadmanager', function(editor, url) {
		editor.addCommand('WP-DownloadManager-Insert_Download', function() {
			var download_id = jQuery.trim(prompt(downloadsEdL10n.enter_download_id));
			if (download_id != null && download_id != "") {
				editor.insertContent('[download="' + download_id + '"]');
			}
		});
		editor.addButton('downloadmanager', {
			text: false,
			tooltip: downloadsEdL10n.insert_download,
			icon: 'downloadmanager dashicons-before dashicons-download',
			onclick: function() {
				tinyMCE.activeEditor.execCommand( 'WP-DownloadManager-Insert_Download' )
			}
		});
	});
})();