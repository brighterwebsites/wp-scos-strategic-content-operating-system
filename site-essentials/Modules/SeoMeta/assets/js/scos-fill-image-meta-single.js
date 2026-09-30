/**
 * SCOS Fill Image Meta — Edit Media screen, one image
 *
 * Drives the "Generate alt text and title" button in the Image Meta box.
 * Runs the scos/fill-image-meta ability for this one attachment with overwrite
 * on, then puts the new values into the form fields — the ability has already
 * saved them, and leaving the old values in the form would undo that on the
 * next Update.
 *
 * v1.0 | 2026-10-01
 */
/* global ScosFillImageMetaSingle */
( function () {
	'use strict';

	var cfg = window.ScosFillImageMetaSingle;
	if ( ! cfg ) {
		return;
	}

	var i18n    = cfg.i18n || {};
	var button  = document.getElementById( 'scos-fim-single-run' );
	var status  = document.getElementById( 'scos-fim-single-status' );
	if ( ! button || ! status ) {
		return;
	}

	function setField( id, value ) {
		var field = document.getElementById( id );
		if ( ! field || ! value ) {
			return;
		}
		field.value = value;
		field.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		field.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}

	function finish( message ) {
		status.textContent = message;
		button.disabled    = false;
	}

	button.addEventListener( 'click', function () {
		button.disabled    = true;
		status.textContent = i18n.running;

		var body = new URLSearchParams();
		body.append( 'action', cfg.action );
		body.append( 'nonce', cfg.nonce );
		body.append( 'attachment_ids', JSON.stringify( [ cfg.attachmentId ] ) );
		body.append( 'parent_post_id', String( cfg.parentPostId || 0 ) );
		body.append( 'overwrite', '1' );

		fetch( cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( json ) {
				if ( ! json || ! json.success ) {
					var reason = json && json.data && json.data.message ? json.data.message : '';
					finish( i18n.failed + ' ' + reason );
					return;
				}

				var result = null;
				( json.data.results || [] ).forEach( function ( item ) {
					if ( parseInt( item.id, 10 ) === parseInt( cfg.attachmentId, 10 ) ) {
						result = item;
					}
				} );

				if ( ! result || ( ! result.alt && ! result.title ) ) {
					finish( i18n.nothing );
					return;
				}

				setField( 'attachment_alt', result.alt );
				setField( 'title', result.title );
				finish( i18n.done );
			} )
			.catch( function ( error ) {
				finish( i18n.failed + ' ' + ( error && error.message ? error.message : '' ) );
			} );
	} );
} )();
