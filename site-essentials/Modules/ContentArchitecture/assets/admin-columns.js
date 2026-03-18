/* global jQuery, inlineEditPost, scosCols */
( function ( $ ) {
	'use strict';

	// -------------------------------------------------------------------------
	// Quick Edit — pre-populate from per-row data container
	// -------------------------------------------------------------------------

	/**
	 * Extend WordPress's inlineEditPost.open to populate our custom fields.
	 * inlineEditPost is provided by wp-admin/js/inline-edit-post.js
	 * (enqueued via the 'inline-edit-post' handle).
	 */
	if ( typeof inlineEditPost !== 'undefined' ) {
		var $wpOpen = inlineEditPost.open;

		inlineEditPost.open = function ( id ) {
			// Call original first so the panel exists in the DOM
			$wpOpen.apply( this, arguments );

			// Resolve post ID (can be passed as element ID string e.g. "post-123")
			var postId = parseInt( id, 10 );
			if ( isNaN( postId ) ) {
				var match = String( id ).match( /\d+/ );
				postId = match ? parseInt( match[0], 10 ) : 0;
			}
			if ( ! postId ) { return; }

			// Data container output in scos_ca_cluster column cell
			var $data = $( '#scos-col-data-' + postId );
			if ( ! $data.length ) { return; }

			var data = $data.data( 'qe' );
			if ( ! data ) { return; }

			// The quick-edit panel for this row
			var $panel = $( '#edit-' + postId );
			if ( ! $panel.length ) { return; }

			// Populate selects
			var fieldMap = {
				'cluster':      'scos_ca_qe_cluster',
				'topic':        'scos_ca_qe_topic',
				'intent':       'scos_ca_qe_intent',
				'purpose':      'scos_ca_qe_purpose',
				'index-status': 'scos_ca_qe_index_status',
				'next-step':    'scos_ca_qe_next_step',
			};

			$.each( fieldMap, function ( dataKey, fieldName ) {
				var val = data[ dataKey ];
				if ( val !== undefined && val !== null ) {
					$panel.find( 'select[name="' + fieldName + '"]' ).val( String( val ) );
				}
			} );

			// Populate progress checkboxes
			var progress = data.progress || [];
			$panel.find( '.scos-qe-progress-tag' ).each( function () {
				var $tag = $( this );
				var $cb  = $tag.find( 'input[type="checkbox"]' );
				var val  = $cb.val();
				var checked = ( progress.indexOf( val ) !== -1 );
				$cb.prop( 'checked', checked );
				$tag.toggleClass( 'is-selected', checked );
			} );
		};
	}

	// -------------------------------------------------------------------------
	// Progress tag toggle — Quick Edit + Bulk Edit panels
	// (mirrors the meta-box behaviour but scoped to edit.php panels)
	// -------------------------------------------------------------------------

	$( document ).on( 'click', '.scos-qe-progress-tag', function ( e ) {
		// Don't double-fire when clicking the hidden checkbox directly
		if ( $( e.target ).is( 'input[type="checkbox"]' ) ) { return; }
		e.preventDefault();
		var $tag = $( this );
		var $cb  = $tag.find( 'input[type="checkbox"]' );
		var next = ! $cb.prop( 'checked' );
		$cb.prop( 'checked', next );
		$tag.toggleClass( 'is-selected', next );
	} );

	// When the hidden checkbox itself fires change (keyboard navigation etc.)
	$( document ).on( 'change', '.scos-qe-progress-tag input[type="checkbox"]', function () {
		$( this ).closest( '.scos-qe-progress-tag' ).toggleClass( 'is-selected', $( this ).prop( 'checked' ) );
	} );

	// -------------------------------------------------------------------------
	// Social Post column button
	// -------------------------------------------------------------------------

	$( document ).on( 'click', '.scos-col-social-btn', function ( e ) {
		e.preventDefault();
		var $btn   = $( this );
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
					$btn.addClass( 'is-sent' );
					$btn.attr( 'title', scosCols.i18n.sent );
					// Update or create the "just now" timestamp underneath
					var $meta = $btn.siblings( '.scos-col-social-meta' );
					if ( $meta.length ) {
						$meta.text( scosCols.i18n.justNow );
					} else {
						$btn.after( '<span class="scos-col-social-meta">' + scosCols.i18n.justNow + '</span>' );
					}
					setTimeout( function () {
						$btn.removeClass( 'is-sent' );
					}, 3000 );
				} else {
					$btn.addClass( 'is-error' );
					setTimeout( function () {
						$btn.removeClass( 'is-error' );
					}, 3000 );
				}
			}
		).fail( function () {
			$btn.removeClass( 'is-sending' ).prop( 'disabled', false ).addClass( 'is-error' );
			setTimeout( function () {
				$btn.removeClass( 'is-error' );
			}, 3000 );
		} );
	} );

} )( jQuery );
