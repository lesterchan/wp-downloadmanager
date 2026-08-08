/**
 * The `wp-downloadmanager/download` block.
 *
 * A dynamic block: `save` returns null, so nothing but the block comment is
 * written into post_content and every view re-renders from PHP. That is what
 * makes the block and the `[download]` shortcode able to share one renderer --
 * the markup is decided in exactly one place, at render time, for both of them.
 *
 * The preview is core's ServerSideRender, which posts the attributes to
 * /wp/v2/block-renderer/wp-downloadmanager/download and draws what the front
 * end would draw. That is also why this block registers no REST route of its
 * own.
 *
 * The attributes are the `[download]` shortcode's attributes, spelled in camel
 * case because that is what a block attribute is spelled in: `sortBy` here is
 * `sort_by` there, and the render callback maps one to the other.
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';

import metadata from './block.json';

/**
 * The columns the listing can be sorted by.
 *
 * The authority is WP_DownloadManager_File::sort_columns(), which is an allow
 * list rather than decoration: anything not on it is replaced by the fallback
 * before it reaches an ORDER BY. This offers the six the README documents for
 * the shortcode, so the two ways of writing the same thing agree.
 */
const SORT_COLUMNS = [
	{ label: __( 'File ID', 'wp-downloadmanager' ), value: 'file_id' },
	{ label: __( 'File', 'wp-downloadmanager' ), value: 'file' },
	{ label: __( 'File name', 'wp-downloadmanager' ), value: 'file_name' },
	{ label: __( 'File size', 'wp-downloadmanager' ), value: 'file_size' },
	{ label: __( 'Date added', 'wp-downloadmanager' ), value: 'file_date' },
	{ label: __( 'Hits', 'wp-downloadmanager' ), value: 'file_hits' },
];

/**
 * The editor view.
 *
 * A named component with a capitalised name rather than an `edit()` shorthand
 * on the settings object: useBlockProps is a React hook, and the hook rules can
 * only tell a component from a plain function by that capital.
 *
 * The files are chosen by id typed into a field rather than picked from a list.
 * A picker would need a route listing the library, and this plugin registers no
 * REST namespace at all -- it has no admin-ajax action a route would improve
 * on, and inventing one to populate a select is new public surface bought for
 * the convenience of a single control.
 *
 * @param {Object}   props               Block props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Attribute setter.
 * @return {Element} The editor view.
 */
function Edit( { attributes, setAttributes } ) {
	const { id, category, display, sortBy, sortOrder, streamLimit } =
		attributes;

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Files', 'wp-downloadmanager' ) }>
					<TextControl
						__nextHasNoMarginBottom
						label={ __( 'File IDs', 'wp-downloadmanager' ) }
						help={ __(
							'One id, or several separated by commas. Leave both this and the categories empty and the block renders nothing, which is what an empty [download] does.',
							'wp-downloadmanager',
						) }
						value={ id }
						onChange={ ( value ) => setAttributes( { id: value } ) }
					/>
					<TextControl
						__nextHasNoMarginBottom
						label={ __( 'Category IDs', 'wp-downloadmanager' ) }
						help={ __(
							'One category id, or several separated by commas.',
							'wp-downloadmanager',
						) }
						value={ category }
						onChange={ ( value ) =>
							setAttributes( { category: value } )
						}
					/>
					<SelectControl
						__nextHasNoMarginBottom
						label={ __( 'Show', 'wp-downloadmanager' ) }
						help={ __(
							'Overrides the Download Embedded File template for this block.',
							'wp-downloadmanager',
						) }
						value={ display }
						options={ [
							{
								label: __(
									'Name and description',
									'wp-downloadmanager',
								),
								value: 'both',
							},
							{
								label: __(
									'Name only',
									'wp-downloadmanager',
								),
								value: 'name',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { display: value } )
						}
					/>
				</PanelBody>
				<PanelBody
					title={ __( 'Order', 'wp-downloadmanager' ) }
					initialOpen={ false }
				>
					<SelectControl
						__nextHasNoMarginBottom
						label={ __( 'Sort by', 'wp-downloadmanager' ) }
						value={ sortBy }
						options={ SORT_COLUMNS }
						onChange={ ( value ) =>
							setAttributes( { sortBy: value } )
						}
					/>
					<SelectControl
						__nextHasNoMarginBottom
						label={ __( 'Sort order', 'wp-downloadmanager' ) }
						value={ sortOrder }
						options={ [
							{
								label: __( 'Ascending', 'wp-downloadmanager' ),
								value: 'asc',
							},
							{
								label: __( 'Descending', 'wp-downloadmanager' ),
								value: 'desc',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { sortOrder: value } )
						}
					/>
					<TextControl
						__nextHasNoMarginBottom
						label={ __(
							'Limit in a post stream',
							'wp-downloadmanager',
						) }
						help={ __(
							'How many files to show where this post is one of many. Zero shows them all. The single post always shows them all.',
							'wp-downloadmanager',
						) }
						type="number"
						min={ 0 }
						value={ streamLimit }
						onChange={ ( value ) =>
							setAttributes( {
								streamLimit: parseInt( value, 10 ) || 0,
							} )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...useBlockProps() }>
				{ /* A download link in the editor counts a hit and starts a
				     download, so the preview is deliberately not clickable. */ }
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
