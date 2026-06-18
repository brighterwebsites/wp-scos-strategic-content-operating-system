/**
 * SCOS Revision Viewer — Panel interactions
 *
 * Handles the Approve AJAX action. Other actions (comment, edit) use
 * plain href links — no JS needed.
 *
 * Expects scosRvData.ajaxUrl to be localised by Revision_Viewer::enqueue_assets().
 *
 * v1.0 | 2026-06-18
 */

( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		initApprove();
	} );

	function initApprove() {
		var btn = document.querySelector( '.scos-rv-action--approve' );
		if ( ! btn ) return;

		btn.addEventListener( 'click', function () {
			if ( btn.disabled || btn.classList.contains( 'scos-rv-action--done' ) ) return;

			btn.disabled = true;

			var body = new URLSearchParams( {
				action:  'scos_rv_approve',
				post_id: btn.dataset.postId,
				nonce:   btn.dataset.nonce,
			} );

			fetch( scosRvData.ajaxUrl, {
				method:  'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body:    body.toString(),
			} )
				.then( function ( r ) { return r.json(); } )
				.then( function ( data ) {
					if ( data.success ) {
						btn.innerHTML =
							'<svg class="scos-rv-action__icon" width="13" height="13" viewBox="0 0 16 16" fill="none" aria-hidden="true">' +
							'<circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.4"/>' +
							'<path d="M5 8l2 2.5 4-4.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>' +
							'</svg> Approved — status set to Testing';
						btn.classList.add( 'scos-rv-action--done' );

						var badge = document.querySelector( '.scos-rv-panel__badge' );
						if ( badge ) {
							badge.textContent = 'Testing';
							badge.className   = 'scos-rv-panel__badge scos-rv-badge--testing';
						}
					} else {
						btn.disabled = false;
					}
				} )
				.catch( function () {
					btn.disabled = false;
				} );
		} );
	}

} )();
