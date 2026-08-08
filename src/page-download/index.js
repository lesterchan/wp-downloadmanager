/**
 * The `wp-downloadmanager/page-download` block.
 *
 * The listing the `[page_download]` shortcode renders: every file in the
 * library, or one category of it, with the search box, the category links and
 * the paging.
 *
 * There is no second block for `[page_downloads]`. Both spellings are
 * registered to one callback which is handed the attributes and never the tag,
 * so it cannot tell which of the two invoked it -- identical output, identical
 * single attribute. The singular is the spelling the README documents, and a
 * block name is written into post_content and outlives the post's edit history,
 * so the block wraps that one. The plural stays a working shortcode.
 *
 * The block name is hyphenated where the shortcode is underscored: a block name
 * must match [a-z0-9-] and an underscore is not allowed in one. That is the
 * only reason the two spellings differ.
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';

import metadata from './block.json';

/**
 * The editor view.
 *
 * Capitalised and named rather than an `edit()` shorthand because useBlockProps
 * is a React hook, and the hook rules identify a component by that capital.
 *
 * @param {Object}   props               Block props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Attribute setter.
 * @return {Element} The editor view.
 */
function Edit( { attributes, setAttributes } ) {
	const { category } = attributes;

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Downloads', 'wp-downloadmanager' ) }>
					<TextControl
						__nextHasNoMarginBottom
						label={ __( 'Category ID', 'wp-downloadmanager' ) }
						help={ __(
							'Zero lists every category, which is what an empty [page_download] does.',
							'wp-downloadmanager',
						) }
						type="number"
						min={ 0 }
						value={ category }
						onChange={ ( value ) =>
							setAttributes( {
								category: parseInt( value, 10 ) || 0,
							} )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...useBlockProps() }>
				{ /* The listing's links download files and count hits, so the
				     preview is deliberately not clickable. */ }
				<div inert="">
					<ServerSideRender
						block={ metadata.name }
						attributes={ attributes }
					/>
				</div>
			</div>
		</>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,

	save() {
		return null;
	},
} );
