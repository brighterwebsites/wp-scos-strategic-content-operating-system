/* global jQuery, inlineEditPost */
// v1.1 | 2026-10-13 — Resolve the post from edit()'s own argument, not a click
//                      listener that fired after edit() and left the previous
//                      post's ID in place (copied one post's SEO meta onto the next).
// v1.2 | 2026-10-18 — Target query field.
( function ( $ ) {
	'use strict';

	// -------------------------------------------------------------------------
	// Quick Edit — pre-populate SEO fields from per-row data container
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

		var _origEdit = inlineEditPost.edit;

		inlineEditPost.edit = function ( id ) {
			var result = _origEdit.apply( this, arguments );

			var postId = resolvePostId( id );
			if ( ! postId ) { return result; }

			// Only ever fill this post's own panel. If either is missing the
			// loaded marker stays empty and PHP skips the SEO fields on save.
			var $panel  = $( '#edit-' + postId );
			var $dataEl = $( '#scos-seo-data-' + postId );
			if ( ! $panel.length || ! $dataEl.length ) { return result; }

			$panel.find( 'input[name="scos_seo_qe_title"]' ).val( $dataEl.attr( 'data-title' ) || '' );
			$panel.find( 'textarea[name="scos_seo_qe_description"]' ).val( $dataEl.attr( 'data-desc' ) || '' );
			$panel.find( 'input[name="scos_seo_qe_breadcrumb"]' ).val( $dataEl.attr( 'data-breadcrumb' ) || '' );
			$panel.find( 'input[name="scos_seo_qe_target_query"]' ).val( $dataEl.attr( 'data-query' ) || '' );
			$panel.find( 'input[name="scos_seo_qe_loaded"]' ).val( String( postId ) );

			return result;
		};
	}

} )( jQuery );
