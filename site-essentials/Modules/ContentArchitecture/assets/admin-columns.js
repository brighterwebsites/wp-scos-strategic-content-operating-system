/* global jQuery, inlineEditPost, scosCols */
( function ( $ ) {
	'use strict';

	// -------------------------------------------------------------------------
	// Quick Edit — pre-populate from per-row data container
	// -------------------------------------------------------------------------

	/**
	 * Extend WordPress's Quick Edit open handler to populate our custom fields.
	 *
	 * WordPress uses `inlineEditPost.open` in WP ≥ 5.9 and `inlineEditPost.edit`
	 * in older versions. We wrap whichever is present.
	 *
	 * The `id` argument can be:
	 *   - a numeric post ID
	 *   - a string like "post-123"
	 *   - a DOM element (row TR)
	 * Use `inlineEditPost.getId()` which handles all three cases reliably.
	 */
	function getPostId( id ) {
		if ( typeof inlineEditPost.getId === 'function' ) {
			return inlineEditPost.getId( id );
		}
		// Fallback: parse digits from whatever was passed
		var num = parseInt( id, 10 );
		if ( ! isNaN( num ) ) { return num; }
		var match = String( id ).match( /(\d+)/ );
		return match ? parseInt( match[1], 10 ) : 0;
	}

	function populateQuickEdit( id ) {
		var postId = getPostId( id );
		if ( ! postId ) { return; }

		// Data container rendered in scos_ca_cluster column cell
		var $data = $( '#scos-col-data-' + postId );
		if ( ! $data.length ) { return; }

		var data = $data.data( 'qe' );
		if ( ! data ) { return; }

		// The quick-edit panel for this row
		var $panel = $( '#edit-' + postId );
		if ( ! $panel.length ) { return; }

		// ---- Selects ----
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

		// ---- Progress checkboxes ----
		var progress = Array.isArray( data.progress ) ? data.progress : [];
		$panel.find( '.scos-qe-progress-tags .scos-qe-progress-tag' ).each( function () {
			var $tag = $( this );
			var $cb  = $tag.find( 'input[type="checkbox"]' );
			var checked = progress.indexOf( $cb.val() ) !== -1;
			$cb.prop( 'checked', checked );
			$tag.toggleClass( 'is-selected', checked );
		} );
	}

	// Wrap whichever method WordPress exposes (try `open` first, fall back to `edit`)
	if ( typeof inlineEditPost !== 'undefined' ) {
		if ( typeof inlineEditPost.open === 'function' ) {
			var origOpen = inlineEditPost.open;
			inlineEditPost.open = function ( id ) {
				origOpen.apply( this, arguments );
				populateQuickEdit( id );
			};
		} else if ( typeof inlineEditPost.edit === 'function' ) {
			var origEdit = inlineEditPost.edit;
			inlineEditPost.edit = function ( id ) {
				origEdit.apply( this, arguments );
				populateQuickEdit( id );
			};
		}
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
