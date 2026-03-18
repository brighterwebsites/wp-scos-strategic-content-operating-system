/* global jQuery, inlineEditPost, scosCols */
( function ( $ ) {
	'use strict';

	// -------------------------------------------------------------------------
	// Quick Edit — pre-populate from per-row data container
	// -------------------------------------------------------------------------

	/**
	 * WordPress's list table click handler calls inlineEditPost.edit(this),
	 * where `this` is the "Quick Edit" anchor element.
	 * We extend .edit (not .open) to match what actually fires in WP.
	 *
	 * inlineEditPost.getId() handles DOM elements, strings, and numbers.
	 */
	if ( typeof inlineEditPost !== 'undefined' && typeof inlineEditPost.edit === 'function' ) {

		var $origEdit = inlineEditPost.edit;

		inlineEditPost.edit = function ( id ) {
			// Call WordPress's original handler first (opens the panel)
			$origEdit.apply( this, arguments );

			// Resolve post ID using WP's own helper (handles element/string/number)
			var postId = ( typeof id === 'object' ) ? this.getId( id ) : parseInt( id, 10 );
			if ( ! postId ) { return; }

			// Data container is rendered in the scos_ca_cluster column cell
			var $dataEl = $( '#scos-col-data-' + postId );
			if ( ! $dataEl.length ) { return; }

			// Read attribute directly and parse — more reliable than $.data() auto-parse
			var raw = $dataEl.attr( 'data-qe' );
			if ( ! raw ) { return; }

			var data;
			try {
				data = JSON.parse( raw );
			} catch ( e ) {
				return;
			}

			// ---- Populate select fields ----
			// Search within the specific panel row for this post
			var $panel = $( '#edit-' + postId );
			if ( ! $panel.length ) {
				// Fallback: any open inline-edit row (only one is open at a time)
				$panel = $( 'tr.inline-edit-row:visible' ).last();
			}
			if ( ! $panel.length ) { return; }

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
					$panel.find( 'select[name="' + fieldName + '"]' ).val( String( val ) );
				}
			} );

			// ---- Populate progress checkboxes ----
			var progress = Array.isArray( data.progress ) ? data.progress : [];
			$panel.find( '.scos-qe-progress-tags .scos-qe-progress-tag' ).each( function () {
				var $tag = $( this );
				var $cb  = $tag.find( 'input[type="checkbox"]' );
				var checked = progress.indexOf( $cb.val() ) !== -1;
				$cb.prop( 'checked', checked );
				$tag.toggleClass( 'is-selected', checked );
			} );
		};
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

	// -------------------------------------------------------------------------
	// Social Post column button
	// -------------------------------------------------------------------------

	$( document ).on( 'click', '.scos-col-social-btn', function ( e ) {
		e.preventDefault();
		var $btn = $( this );
		if ( $btn.prop( 'disabled' ) ) { return; }

		var postId = $btn.data( 'post-id' );
		var nonce  = $btn.data( 'nonce' );

		$btn.addClass( 'is-sending' ).prop( 'disabled', true );

		$.post(
			scosCols.ajaxurl,
			{
				action:  'bw_trigger_social_webhook',
				post_id: postId,
				nonce:   nonce,
			},
			function ( response ) {
				$btn.removeClass( 'is-sending' ).prop( 'disabled', false );
				if ( response && response.success ) {
					$btn.addClass( 'is-sent' ).attr( 'title', scosCols.i18n.sent );
					var $meta = $btn.siblings( '.scos-col-social-meta' );
					if ( $meta.length ) {
						$meta.text( scosCols.i18n.justNow );
					} else {
						$btn.after( '<span class="scos-col-social-meta">' + scosCols.i18n.justNow + '</span>' );
					}
					setTimeout( function () { $btn.removeClass( 'is-sent' ); }, 3000 );
				} else {
					$btn.addClass( 'is-error' );
					setTimeout( function () { $btn.removeClass( 'is-error' ); }, 3000 );
				}
			}
		).fail( function () {
			$btn.removeClass( 'is-sending' ).prop( 'disabled', false ).addClass( 'is-error' );
			setTimeout( function () { $btn.removeClass( 'is-error' ); }, 3000 );
		} );
	} );

} )( jQuery );
