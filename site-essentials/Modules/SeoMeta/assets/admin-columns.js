/* global jQuery, inlineEditPost */
( function ( $ ) {
	'use strict';

	// -------------------------------------------------------------------------
	// Quick Edit — pre-populate SEO fields from per-row data container
	// -------------------------------------------------------------------------

	/**
	 * Capture the post ID from the row on button click — before WP runs its own
	 * handler — because inlineEditPost.getId() can return NaN from the button element.
	 */
	var _qePostId = 0;

	$( document ).on( 'click', 'button.editinline, a.editinline', function () {
		var $row = $( this ).closest( 'tr[id^="post-"]' );
		var m    = ( $row.attr( 'id' ) || '' ).match( /post-(\d+)/ );
		_qePostId = m ? parseInt( m[1], 10 ) : 0;
	} );

	if ( typeof inlineEditPost !== 'undefined' && typeof inlineEditPost.edit === 'function' ) {

		var _origEdit = inlineEditPost.edit;

		inlineEditPost.edit = function ( id ) {
			_origEdit.apply( this, arguments );

			var postId = _qePostId;
			if ( ! postId ) {
				postId = ( typeof id === 'object' ) ? this.getId( id ) : parseInt( id, 10 );
			}
			if ( ! postId || isNaN( postId ) ) { return; }

			var $dataEl = $( '#scos-seo-data-' + postId );
			if ( ! $dataEl.length ) { return; }

			var $panel = $( '#edit-' + postId );
			if ( ! $panel.length ) {
				$panel = $( '#post-' + postId ).nextAll( 'tr.inline-edit-row' ).first();
			}
			if ( ! $panel.length ) { return; }

			$panel.find( 'input[name="scos_seo_qe_title"]' ).val( $dataEl.attr( 'data-title' ) || '' );
			$panel.find( 'textarea[name="scos_seo_qe_description"]' ).val( $dataEl.attr( 'data-desc' ) || '' );
			$panel.find( 'input[name="scos_seo_qe_breadcrumb"]' ).val( $dataEl.attr( 'data-breadcrumb' ) || '' );
		};
	}

} )( jQuery );
