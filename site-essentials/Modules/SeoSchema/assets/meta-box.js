/* global jQuery */
( function ( $ ) {
	'use strict';

	var textarea = $( '#scos-schema-custom' );
	var statusEl = $( '#scos-schema-status' );

	if ( ! textarea.length ) { return; }

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	function showStatus( type, icon, message ) {
		statusEl
			.removeClass( 'is-valid is-invalid' )
			.addClass( 'is-' + type )
			.html( '<span class="dashicons dashicons-' + icon + '"></span> ' + message )
			.removeAttr( 'hidden' );
	}

	function clearStatus() {
		statusEl.attr( 'hidden', '' ).removeClass( 'is-valid is-invalid' ).html( '' );
		textarea.removeClass( 'has-error is-valid' );
	}

	function tryParse( raw ) {
		if ( raw.trim() === '' ) { return null; }
		try {
			return JSON.parse( raw );
		} catch ( e ) {
			return e;
		}
	}

	// ------------------------------------------------------------------
	// Validate button
	// ------------------------------------------------------------------

	$( '#scos-schema-validate' ).on( 'click', function () {
		var raw    = textarea.val();
		var result = tryParse( raw );

		if ( raw.trim() === '' ) {
			clearStatus();
			return;
		}

		if ( result instanceof Error ) {
			textarea.addClass( 'has-error' ).removeClass( 'is-valid' );
			showStatus( 'invalid', 'warning', result.message );
		} else {
			textarea.addClass( 'is-valid' ).removeClass( 'has-error' );
			var type = result && result['@type'] ? ' — @type: <strong>' + result['@type'] + '</strong>' : '';
			showStatus( 'valid', 'yes-alt', 'Valid JSON-LD' + type );
		}
	} );

	// ------------------------------------------------------------------
	// Format button  (pretty-print)
	// ------------------------------------------------------------------

	$( '#scos-schema-format' ).on( 'click', function () {
		var raw = textarea.val();
		if ( raw.trim() === '' ) { return; }

		var result = tryParse( raw );
		if ( result instanceof Error ) {
			textarea.addClass( 'has-error' ).removeClass( 'is-valid' );
			showStatus( 'invalid', 'warning', 'Cannot format: ' + result.message );
			return;
		}

		textarea.val( JSON.stringify( result, null, '\t' ) );
		textarea.addClass( 'is-valid' ).removeClass( 'has-error' );
		showStatus( 'valid', 'yes-alt', 'Formatted &amp; valid' );
	} );

	// ------------------------------------------------------------------
	// Clear button
	// ------------------------------------------------------------------

	$( '#scos-schema-clear' ).on( 'click', function () {
		if ( textarea.val().trim() === '' ) { return; }
		if ( ! window.confirm( 'Clear the schema? (Save the post to make this permanent.)' ) ) { return; }
		textarea.val( '' ).removeClass( 'has-error is-valid' );
		clearStatus();
	} );

	// ------------------------------------------------------------------
	// Live validation indicator — subtle; don't yell until they ask
	// (only clear the valid/error colour state as the user types)
	// ------------------------------------------------------------------

	textarea.on( 'input', function () {
		if ( textarea.val().trim() === '' ) {
			clearStatus();
		} else {
			textarea.removeClass( 'has-error is-valid' );
			statusEl.attr( 'hidden', '' ).removeClass( 'is-valid is-invalid' );
		}
	} );

} )( jQuery );
