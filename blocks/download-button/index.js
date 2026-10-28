/**
 * Gated Downloads — "Download" block (editor script, no build step required).
 *
 * Renders a settings panel to pick a gated file + button label. The front-end
 * output is produced server-side (see class-block.php render callback).
 */
( function ( blocks, element, blockEditor, components, i18n ) {
	'use strict';

	var el   = element.createElement;
	var __   = i18n.__;
	var data = window.TslgdBlock || { gates: [], defaultLabel: 'Download' };

	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps     = blockEditor.useBlockProps;
	var PanelBody         = components.PanelBody;
	var SelectControl     = components.SelectControl;
	var TextControl       = components.TextControl;

	blocks.registerBlockType( 'templatesell-gated-downloads/download-button', {
		edit: function ( props ) {
			var atts       = props.attributes;
			var blockProps = useBlockProps( { className: 'tslgd-block-preview' } );

			var options = [ { label: __( '— Select a file —', 'templatesell-gated-downloads' ), value: 0 } ];
			data.gates.forEach( function ( g ) {
				options.push( { label: g.title, value: g.id } );
			} );

			var controls = el(
				InspectorControls,
				{},
				el(
					PanelBody,
					{ title: __( 'Gated Download', 'templatesell-gated-downloads' ), initialOpen: true },
					el( SelectControl, {
						label: __( 'Gated file', 'templatesell-gated-downloads' ),
						value: atts.gateId,
						options: options,
						onChange: function ( v ) {
							props.setAttributes( { gateId: parseInt( v, 10 ) || 0 } );
						}
					} ),
					el( TextControl, {
						label: __( 'Button label', 'templatesell-gated-downloads' ),
						value: atts.label,
						placeholder: data.defaultLabel,
						onChange: function ( v ) {
							props.setAttributes( { label: v } );
						}
					} )
				)
			);

			var preview = el(
				'div',
				blockProps,
				el(
					'button',
					{ type: 'button', className: 'tslgd-btn', disabled: true },
					el( 'span', { className: 'dashicons dashicons-download', style: { fontSize: '16px', width: '16px', height: '16px' } } ),
					' ',
					atts.label || data.defaultLabel
				),
				atts.gateId ? null : el(
					'p',
					{ style: { margin: '8px 0 0', fontSize: '12px', opacity: 0.7 } },
					__( 'Select a gated file in the block settings →', 'templatesell-gated-downloads' )
				)
			);

			return el( element.Fragment, {}, controls, preview );
		},
		save: function () {
			// Dynamic block — rendered by PHP.
			return null;
		}
	} );
}(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.i18n
) );
