/**
 * Shared setup for the script tests.
 *
 * The scripts are IIFEs with no exports: they read their localised data off
 * `window` as they execute, then attach delegated listeners to `document`. So
 * the l10n object has to exist before the script is evaluated, and a script can
 * only be evaluated once per test file or every listener fires twice.
 */
import { readFileSync } from 'node:fs';

/**
 * Evaluate a plugin script in the current jsdom page.
 *
 * @param {string} name File name relative to the plugin root.
 */
export function loadScript( name ) {
	const src = readFileSync( new URL( '../../' + name, import.meta.url ), 'utf8' );

	new Function( src )();
}

/**
 * Dispatch a bubbling event on an element.
 *
 * The scripts listen on `document`, so nothing happens unless the event
 * bubbles - which is exactly the mistake a hand-written test makes.
 *
 * @param {Element} el   Target element.
 * @param {string}  type Event type.
 */
export function fire( el, type ) {
	el.dispatchEvent( new window.Event( type, { bubbles: true } ) );
}

/**
 * Click an element with a bubbling MouseEvent.
 *
 * @param {Element} el Target element.
 */
export function click( el ) {
	el.dispatchEvent( new window.MouseEvent( 'click', { bubbles: true, cancelable: true } ) );
}

export const DATE_PARTS = [ 'day', 'month', 'year', 'hour', 'minute', 'second' ];

/**
 * Markup for the timestamp selects the edit screen renders.
 *
 * Each select carries every value the tests use, because assigning a value a
 * <select> has no option for is silently ignored - which would make a broken
 * script look like a working one.
 *
 * @param {Object} options          Options.
 * @param {Array}  options.values   Values each select must be able to hold.
 * @param {Object} options.selected Initially selected value per part.
 * @return {string} HTML.
 */
export function timestampSelects( { values = [], selected = {} } = {} ) {
	return DATE_PARTS.map( ( part ) => {
		const choices = [ ...new Set( [ ...values, selected[ part ] ] ) ]
			.filter( ( value ) => value !== undefined )
			.map( ( value ) => {
				const isSelected = String( value ) === String( selected[ part ] );
				return `<option value="${ value }"${ isSelected ? ' selected' : '' }>${ value }</option>`;
			} )
			.join( '' );

		return `<select id="file_timestamp_${ part }">${ choices }</select>`;
	} ).join( '' );
}

/**
 * The current value of each timestamp select.
 *
 * @return {Object} Part name to value.
 */
export function timestampValues() {
	return Object.fromEntries(
		DATE_PARTS.map( ( part ) => [
			part,
			document.getElementById( 'file_timestamp_' + part ).value,
		] ),
	);
}
