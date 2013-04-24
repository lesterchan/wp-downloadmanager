(function() {
	tinymce.PluginManager.requireLangPack('downloadmanager');
	tinymce.create('tinymce.plugins.DownloadManagerPlugin', {
		init : function(ed, url) {
			ed.addCommand('mceDownloadInsert', function() {
				ed.execCommand('mceInsertContent', 0, insertDownload('visual', ''));
			});
			ed.addButton('downloadmanager', {
				title : 'downloadmanager.insert_download',
				cmd : 'mceDownloadInsert',
				image : url + '/img/download.gif'
			});
			ed.onNodeChange.add(function(ed, cm, n) {
				cm.setActive('downloadmanager', n.nodeName == 'IMG');
			});
		},

		createControl : function(n, cm) {
			return null;
		},
		getInfo : function() {
			return {
				longname : 'WP-DownloadManager',
				author : 'Lester Chan',
				authorurl : 'http://lesterchan.net',
				infourl : 'http://lesterchan.net/portfolio/programming/php/',
				version : "1.61"
			};
		}
	});
	tinymce.PluginManager.add('downloadmanager', tinymce.plugins.DownloadManagerPlugin);
})();