/**
 * WordPress JS coding standards for WP-DownloadManager.
 *
 * "recommended-with-formatting" uses native ESLint formatting rules rather
 * than delegating to Prettier, so no Prettier install is needed.
 *
 * Excluded from the SVN deploy, so this never ships to users.
 */
import wordpress from '@wordpress/eslint-plugin';
import globals from 'globals';

export default [
	{
		ignores: [ '**/node_modules/**', '**/vendor/**', '**/*.min.js' ],
	},
	...wordpress.configs[ 'recommended-with-formatting' ],
	{
		languageOptions: {
			globals: {
				...globals.browser,
				// Localised into the page by wp_localize_script().
				downloadManagerDefaults: 'readonly',
				downloadManagerQuicktag: 'readonly',
				QTags: 'readonly',
			},
		},
		rules: {
			// Properties stay exempt: the wp_localize_script() objects are named
			// on the PHP side, and the template keys they carry are snake_case by
			// necessity.
			camelcase: [ 'error', { properties: 'never' } ],

			// The plugin asks for a file ID and confirms deletions with the native
			// dialogs. Replacing them means building a modal, which is a UX
			// change, not a lint fix.
			'no-alert': 'off',
		},
		settings: {
			react: { version: '18.0' },
		},
	},
	{
		// The TinyMCE button runs inside the editor iframe against TinyMCE's own API.
		files: [ 'tinymce/**/*.js' ],
		languageOptions: {
			globals: {
				...globals.browser,
				tinyMCE: 'readonly',
				tinymce: 'readonly',
			},
		},
	},
];
