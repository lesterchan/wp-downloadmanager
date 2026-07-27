/**
 * Vitest configuration for WP-DownloadManager.
 *
 * The scripts are IIFEs that attach delegated listeners to `document` and are
 * loaded into a jsdom page, so the tests drive them the same way an admin does:
 * build the markup the PHP emits, dispatch a real event, assert on the DOM.
 *
 * Excluded from the SVN deploy, so this never ships to users.
 */
export default {
	test: {
		environment: 'jsdom',
		include: [ 'tests/js/**/*.test.js' ],
		restoreMocks: true,
	},
};
