/**
 * SCOS CA Suggest — Intent Goal AI Suggestions
 *
 * Calls the scos/suggest-intent-goal WP Ability via the REST API and renders
 * clickable suggestion pills in the CA meta box. Handles three fill paths:
 *
 *   Path A — FAQ module not active: fills #scos_ca_intent_goal textarea directly.
 *   Path B — FAQ module active, no FAQ linked: pre-fills the Add FAQ modal title.
 *   Path C — FAQ module active, FAQ already linked: shows an informational note.
 *
 * v1.0 | 2026-06-23
 */

( function () {
	'use strict';

	var cfg = window.ScosCaSuggest;
	if ( ! cfg ) return;

	var btn     = document.getElementById( 'scos-ca-suggest-btn' );
	var spinner = document.querySelector( '.scos-ca-suggest-spinner' );
	var errorEl = document.getElementById( 'scos-ca-suggest-error' );
	var modal   = document.getElementById( 'scos-ca-suggest-modal' );

	if ( ! btn || ! modal ) return;

	// -------------------------------------------------------------------------
	// State helpers
	// -------------------------------------------------------------------------

	function setLoading( loading ) {
		btn.disabled = loading;
		if ( spinner ) spinner.classList.toggle( 'is-active', loading );
	}

	function showError( message ) {
		if ( errorEl ) {
			errorEl.textContent = message;
			errorEl.style.display = 'block';
		}
	}

	function clearError() {
		if ( errorEl ) {
			errorEl.textContent = '';
			errorEl.style.display = 'none';
		}
	}

	// -------------------------------------------------------------------------
	// Determine which fill path to use
	// -------------------------------------------------------------------------

	function detectFillPath() {
		if ( ! cfg.faqModuleActive ) {
			return 'freetext';
		}
		var panel  = document.getElementById( 'scos-intent-faq-panel' );
		var picker = document.getElementById( 'scos-intent-faq-picker' );
		if ( panel && ! panel.hidden ) {
			return 'faq-linked';
		}
		if ( picker && ! picker.hidden ) {
			return 'faq-picker';
		}
		// Fallback: try freetext textarea (legacy path inside <details>)
		return document.getElementById( 'scos_ca_intent_goal' ) ? 'freetext' : 'faq-linked';
	}

	// -------------------------------------------------------------------------
	// Fill actions
	// -------------------------------------------------------------------------

	function fillFreetext( goal ) {
		var textarea = document.getElementById( 'scos_ca_intent_goal' );
		if ( textarea ) {
			textarea.value = goal;
			// Open the <details> wrapper if present (legacy collapsed state)
			var details = textarea.closest( 'details' );
			if ( details ) details.open = true;
		}
		closeModal();
	}

	function fillFaqPicker( goal ) {
		var titleInput = document.getElementById( 'scos-intent-faq-new-title' );
		var addModal   = document.getElementById( 'scos-intent-faq-modal' );
		if ( titleInput ) titleInput.value = goal;
		if ( addModal ) {
			addModal.hidden = false;
			addModal.removeAttribute( 'hidden' );
			if ( titleInput ) titleInput.focus();
		}
		closeModal();
	}

	// -------------------------------------------------------------------------
	// Modal rendering
	// -------------------------------------------------------------------------

	function renderModal( goals ) {
		var path = detectFillPath();
		var html = '<div class="scos-ca-modal-inner">';
		html += '<h3 style="margin:0 0 4px;font-size:13px;">' + escHtml( 'AI Suggestions' ) + '</h3>';

		if ( path === 'faq-linked' ) {
			html += '<p class="scos-ca-modal-note">An FAQ is already linked \u2014 edit it directly to change the intent goal.</p>';
			html += '<button type="button" id="scos-ca-modal-close" class="button" style="margin-top:8px;">Close</button>';
		} else {
			html += '<p class="scos-ca-modal-note">Click a suggestion to fill the field. Save the post to keep changes.</p>';
			html += '<div class="scos-ca-section">';
			html += '<h4 style="margin:0 0 6px;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#888;">Intent Goals</h4>';
			html += '<div class="scos-ca-pills">';
			goals.forEach( function ( item ) {
				var pct = Math.round( ( item.confidence || 0 ) * 100 );
				html += '<button type="button" class="scos-ca-pill" data-goal="' + escAttr( item.goal ) + '">';
				html += escHtml( item.goal );
				if ( pct > 0 ) html += ' <span style="opacity:.6;font-size:11px;">(' + pct + '%)</span>';
				html += '</button>';
			} );
			html += '</div></div>';
			html += '<button type="button" id="scos-ca-modal-close" class="button" style="margin-top:12px;">Close</button>';
		}

		html += '</div>';
		modal.innerHTML = html;
		modal.style.display = 'block';

		// Bind pill clicks
		modal.querySelectorAll( '.scos-ca-pill' ).forEach( function ( pill ) {
			pill.addEventListener( 'click', function () {
				var goal = this.getAttribute( 'data-goal' );
				if ( path === 'freetext' ) {
					fillFreetext( goal );
				} else if ( path === 'faq-picker' ) {
					fillFaqPicker( goal );
				}
			} );
		} );

		// Close button
		var closeBtn = document.getElementById( 'scos-ca-modal-close' );
		if ( closeBtn ) closeBtn.addEventListener( 'click', closeModal );
	}

	function closeModal() {
		modal.style.display = 'none';
		modal.innerHTML = '';
		setLoading( false );
	}

	// -------------------------------------------------------------------------
	// Fetch
	// -------------------------------------------------------------------------

	btn.addEventListener( 'click', function () {
		clearError();
		setLoading( true );

		fetch( cfg.endpoint, {
			method:  'POST',
			headers: {
				'X-WP-Nonce':   cfg.nonce,
				'Content-Type': 'application/json',
				'Accept':       'application/json',
			},
			body: JSON.stringify( { input: { post_id: parseInt( cfg.postId, 10 ) } } ),
		} )
			.then( function ( response ) {
				return response.json().then( function ( data ) {
					if ( ! response.ok ) {
						throw new Error( data.message || 'Request failed (' + response.status + ')' );
					}
					return data;
				} );
			} )
			.then( function ( data ) {
				var goals = data.intent_goals || [];
				if ( ! goals.length ) {
					throw new Error( 'No suggestions returned. Please try again.' );
				}
				setLoading( false );
				renderModal( goals );
			} )
			.catch( function ( err ) {
				setLoading( false );
				showError( err.message || 'Something went wrong. Please try again.' );
				console.error( '[scos-ca-suggest]', err );
			} );
	} );

	// -------------------------------------------------------------------------
	// Utility
	// -------------------------------------------------------------------------

	function escHtml( str ) {
		var d = document.createElement( 'div' );
		d.appendChild( document.createTextNode( str ) );
		return d.innerHTML;
	}

	function escAttr( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#39;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' );
	}

} )();
