/* global jQuery, inlineEditPost, scosCols */
( function ( $ ) {
	'use strict';

	// v1.0.5 | 2026-10-13 — Resolve the post from edit()'s own argument, not a click
	//                       listener that fired after edit() and left the previous
	//                       post's ID in place (copied one post's CA fields onto the next).
	console.log( '[SCOS] admin-columns.js v1.0.5 loaded' );

	// -------------------------------------------------------------------------
	// Quick Edit — pre-populate from per-row data container
	// -------------------------------------------------------------------------

	/**
	 * Post ID for the row being edited, from the argument WP passes to edit():
	 * the clicked button (inside the row) or a numeric ID.
	 */
	function resolvePostId( id ) {
		if ( id && typeof id === 'object' ) {
			var m = ( $( id ).closest( 'tr' ).attr( 'id' ) || '' ).match( /^post-(\d+)$/ );
			return m ? parseInt( m[1], 10 ) : 0;
		}
		return parseInt( id, 10 ) || 0;
	}

	if ( typeof inlineEditPost !== 'undefined' && typeof inlineEditPost.edit === 'function' ) {

		var $origEdit = inlineEditPost.edit;

		inlineEditPost.edit = function ( id ) {
			var result = $origEdit.apply( this, arguments );

			var postId = resolvePostId( id );
			console.log( '[SCOS QE] edit() fired, postId:', postId );

			if ( ! postId ) {
				console.warn( '[SCOS QE] could not resolve postId, skipping pre-population' );
				return result;
			}

			// Find data container in the cluster column cell for this row
			var $dataEl = $( '#scos-col-data-' + postId );
			console.log( '[SCOS QE] data element found:', $dataEl.length, '  id: scos-col-data-' + postId );

			if ( ! $dataEl.length ) {
				console.warn( '[SCOS QE] data element not found — cluster column may not be rendered for this post type' );
				return result;
			}

			var raw = $dataEl.attr( 'data-qe' );
			console.log( '[SCOS QE] raw data-qe attribute length:', raw ? raw.length : 0 );

			if ( ! raw ) {
				console.warn( '[SCOS QE] data-qe attribute is empty' );
				return result;
			}

			var data;
			try {
				data = JSON.parse( raw );
				console.log( '[SCOS QE] parsed data:', data );
			} catch ( e ) {
				console.error( '[SCOS QE] JSON.parse failed:', e.message );
				return result;
			}

			// Only ever fill this post's own panel. If it is missing the loaded
			// marker stays empty and PHP skips the CA fields on save.
			var $panel = $( '#edit-' + postId );
			if ( ! $panel.length ) {
				console.warn( '[SCOS QE] panel #edit-' + postId + ' not found' );
				return result;
			}
			console.log( '[SCOS QE] panel found:', $panel.attr( 'id' ) );

			// ---- Populate select fields ----
			var selectMap = {
				'cluster':      'scos_ca_qe_cluster',
				'topic':        'scos_ca_qe_topic',
				'intent':       'scos_ca_qe_intent',
				'purpose':      'scos_ca_qe_purpose',
				'maturity':     'scos_ca_qe_maturity',
				'index-status': 'scos_ca_qe_index_status',
				'next-step':    'scos_ca_qe_next_step',
				'pillar':       'scos_ca_qe_pillar_page_id',
				'pathway':      'scos_ca_qe_service_pathway_id',
			};

			$.each( selectMap, function ( dataKey, fieldName ) {
				var val = data[ dataKey ];
				if ( val !== undefined && val !== null ) {
					var $sel = $panel.find( 'select[name="' + fieldName + '"]' );
					var before = $sel.val();
					$sel.val( String( val ) );
					var after = $sel.val();
					console.log( '[SCOS QE] field "' + fieldName + '": data=' + val + '  before=' + before + '  after=' + after );
					if ( String( val ) !== '0' && String( val ) !== '' && after !== String( val ) ) {
						console.warn( '[SCOS QE] .val() set to "' + val + '" but select shows "' + after + '" — option may be missing' );
					}
				}
			} );

			// ---- Populate progress checkboxes ----
			var progress = Array.isArray( data.progress ) ? data.progress : [];
			$panel.find( '.scos-qe-progress-tags .scos-qe-progress-tag' ).each( function () {
				var $tag    = $( this );
				var $cb     = $tag.find( 'input[type="checkbox"]' );
				var checked = progress.indexOf( $cb.val() ) !== -1;
				$cb.prop( 'checked', checked );
				$tag.toggleClass( 'is-selected', checked );
			} );
			console.log( '[SCOS QE] progress:', progress );

			$panel.find( 'input[name="scos_ca_qe_loaded"]' ).val( String( postId ) );

			return result;
		};
	} else {
		console.warn( '[SCOS QE] inlineEditPost.edit not found — Quick Edit pre-population disabled' );
	}

	// -------------------------------------------------------------------------
	// Progress tag toggle — Quick Edit + Bulk Edit panels
	// -------------------------------------------------------------------------

	$( document ).on( 'click', '.scos-qe-progress-tag', function ( e ) {
		if ( $( e.target ).is( 'input[type="checkbox"]' ) ) { return; }
		e.preventDefault();
		var $tag = $( this );
		var $cb  = $tag.find( 'input[type="checkbox"]' );
		var next = ! $cb.prop( 'checked' );
		$cb.prop( 'checked', next );
		$tag.toggleClass( 'is-selected', next );
	} );

	$( document ).on( 'change', '.scos-qe-progress-tag input[type="checkbox"]', function () {
		$( this ).closest( '.scos-qe-progress-tag' ).toggleClass( 'is-selected', $( this ).prop( 'checked' ) );
	} );

} )( jQuery );
