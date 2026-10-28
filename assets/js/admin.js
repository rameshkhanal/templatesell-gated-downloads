/**
 * Gated Downloads admin JS: file source toggle and Media Library picker.
 */
( function ( $, cfg ) {
	'use strict';

	$( function () {
		// Brand color pickers (Settings, Appearance).
		if ( $.fn.wpColorPicker ) {
			$( '.tslgd-color' ).wpColorPicker();
		}

		// Toggle between URL and upload sources.
		$( document ).on( 'change', 'input[name="tslgd_source"]', function () {
			var source = $( this ).val();
			$( '.tslgd-source-url' ).toggle( 'url' === source );
			$( '.tslgd-source-upload' ).toggle( 'upload' === source );
		} );

		var frame;

		$( '#tslgd_select_file' ).on( 'click', function ( e ) {
			e.preventDefault();

			if ( frame ) {
				frame.open();
				return;
			}

			frame = wp.media( {
				title: cfg.mediaTitle,
				button: { text: cfg.mediaButton },
				multiple: false
			} );

			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();
				$( '#tslgd_attachment_id' ).val( attachment.id );
				$( '#tslgd_file_preview' ).text( attachment.filename || attachment.url );
				$( '#tslgd_remove_file' ).show();
			} );

			frame.open();
		} );

		$( '#tslgd_remove_file' ).on( 'click', function ( e ) {
			e.preventDefault();
			$( '#tslgd_attachment_id' ).val( '' );
			$( '#tslgd_file_preview' ).text( '' );
			$( this ).hide();
		} );

		// Click-to-copy shortcode (list table + metabox chip).
		function copyText( text ) {
			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				return navigator.clipboard.writeText( text );
			}
			// Fallback for non-secure contexts.
			var ta = document.createElement( 'textarea' );
			ta.value = text;
			ta.style.position = 'fixed';
			ta.style.opacity = '0';
			document.body.appendChild( ta );
			ta.focus();
			ta.select();
			try { document.execCommand( 'copy' ); } catch ( err ) {}
			document.body.removeChild( ta );
			return null;
		}

		function flashCopied( el ) {
			var $el = $( el );
			if ( $el.data( 'busy' ) ) { return; }
			var original = $el.text();
			$el.data( 'busy', true ).addClass( 'tslgd-copied' ).text( cfg.copied || 'Copied!' );
			setTimeout( function () {
				$el.text( original ).removeClass( 'tslgd-copied' ).data( 'busy', false );
			}, 1200 );
		}

		$( document ).on( 'click keydown', '.tslgd-copy', function ( e ) {
			if ( 'keydown' === e.type && e.key !== 'Enter' && e.key !== ' ' ) { return; }
			e.preventDefault();
			copyText( $( this ).data( 'clipboard' ) || $( this ).text() );
			flashCopied( this );
		} );

		// ---- Per-form field builder (Form Fields & Behavior meta box) ----

		// Drag to reorder fields. The hidden order[] inputs post in DOM order.
		if ( $.fn.sortable ) {
			$( '.tslgd-fb-list' ).sortable( {
				handle: '.tslgd-fb-handle',
				axis: 'y',
				items: '> .tslgd-fb-row',
				tolerance: 'pointer',
				cursor: 'grabbing'
			} );
		}

		// Enable or disable a card's controls from its override toggle.
		// Inputs still post, the resolver ignores them unless the override flag is set.
		function syncSection( toggle ) {
			var $t = $( toggle );
			var name = $t.data( 'toggle' );
			$( '[data-section="' + name + '"]' ).attr( 'data-disabled', $t.is( ':checked' ) ? null : '1' );
		}

		$( '.tslgd-formcfg' ).find( '[data-toggle]' ).each( function () {
			syncSection( this );
		} ).on( 'change', function () {
			syncSection( this );
		} );
	} );
}( jQuery, window.TslgdAdmin || {} ) );
