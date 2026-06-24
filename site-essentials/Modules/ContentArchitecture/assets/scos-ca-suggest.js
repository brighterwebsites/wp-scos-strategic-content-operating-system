/**
 * SCOS CA Suggest — Chained AI Suggestions (Topics + Intent Goal)
 *
 * Two-step flow:
 *   Step 1 — calls scos/suggest-topics, renders topic pills with
 *             confidence badge and topic coverage estimate.
 *   Step 2 — calls scos/suggest-intent-goal (scoped to selected topic),
 *             renders intent goal pills. Back button returns to step 1
 *             without a second API call.
 *
 * Fill paths (step 2, unchanged from Phase 1):
 *   Path A — FAQ module not active: fills #scos_ca_intent_goal textarea.
 *   Path B — FAQ module active, no FAQ linked: pre-fills Add FAQ modal.
 *   Path C — FAQ module active, FAQ already linked: informational note.
 *
 * v2.0 | 2026-06-24
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
	// State
	// -------------------------------------------------------------------------

	var state = {
		step:           1,
		selectedTopic:  null,  // { term_id, name }
		step1Results:   null,  // cached suggestions array from step 1
	};

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
	// Fetch helpers
	// -------------------------------------------------------------------------

	function apiFetch( endpoint, body ) {
		return fetch( endpoint, {
			method:  'POST',
			headers: {
				'X-WP-Nonce':   cfg.nonce,
				'Content-Type': 'application/json',
				'Accept':       'application/json',
			},
			body: JSON.stringify( body ),
		} ).then( function ( response ) {
			return response.json().then( function ( data ) {
				if ( ! response.ok ) {
					throw new Error( data.message || 'Request failed (' + response.status + ')' );
				}
				return data;
			} );
		} );
	}

	// -------------------------------------------------------------------------
	// Fill path detection (step 2)
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
		return document.getElementById( 'scos_ca_intent_goal' ) ? 'freetext' : 'faq-linked';
	}

	function fillFreetext( goal ) {
		var textarea = document.getElementById( 'scos_ca_intent_goal' );
		if ( textarea ) {
			textarea.value = goal;
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
	// Topic dropdown fill (step 1 pick-to-fill)
	// -------------------------------------------------------------------------

	function applyTopicSelection( termId ) {
		var select = document.getElementById( 'scos_ca_topic' );
		if ( ! select ) return;
		var option = select.querySelector( 'option[value="' + termId + '"]' );
		if ( option ) {
			select.value = String( termId );
			// Trigger change so any dependent JS picks it up.
			var evt = new Event( 'change', { bubbles: true } );
			select.dispatchEvent( evt );
		}
	}

	// -------------------------------------------------------------------------
	// Modal close
	// -------------------------------------------------------------------------

	function closeModal() {
		modal.style.display = 'none';
		modal.innerHTML = '';
		setLoading( false );
		state.step          = 1;
		state.selectedTopic = null;
	}

	// -------------------------------------------------------------------------
	// Step 1 render — Topic suggestions
	// -------------------------------------------------------------------------

	function renderStep1( suggestions ) {
		state.step1Results = suggestions;
		state.step         = 1;

		var html = '<div class="scos-ca-modal-inner">';
		html += '<div class="scos-ca-step-header">Step 1 of 2 &mdash; Topic</div>';
		html += '<h3 style="margin:4px 0 4px;font-size:13px;font-weight:600;">Suggested Topics</h3>';
		html += '<p class="scos-ca-modal-note">Click a topic to scope intent goal suggestions, or pick to fill the Primary Topic field.</p>';

		html += '<div class="scos-ca-pills">';
		suggestions.forEach( function ( item ) {
			html += '<button type="button" class="scos-ca-pill scos-ca-pill--topic"'
				+ ' data-term-id="' + escAttr( String( item.term_id ) ) + '"'
				+ ' data-name="' + escAttr( item.name ) + '">';
			html += '<span class="scos-ca-confidence-badge scos-ca-confidence-badge--' + escAttr( item.confidence ) + '">'
				+ escHtml( item.confidence ) + '</span> ';
			html += escHtml( item.name );
			if ( item.topic_coverage ) {
				html += ' <span class="scos-ca-coverage">' + escHtml( item.topic_coverage ) + '</span>';
			}
			html += '</button>';
		} );
		html += '</div>';

		html += '<div style="margin-top:12px;display:flex;gap:8px;align-items:center;">';
		html += '<button type="button" id="scos-ca-modal-close" class="button">Close</button>';
		html += '</div>';
		html += '</div>';

		modal.innerHTML = html;
		modal.style.display = 'block';

		modal.querySelectorAll( '.scos-ca-pill--topic' ).forEach( function ( pill ) {
			pill.addEventListener( 'click', function () {
				var termId = parseInt( this.getAttribute( 'data-term-id' ), 10 );
				var name   = this.getAttribute( 'data-name' );

				state.selectedTopic = { term_id: termId, name: name };

				// Fill the topic select in the meta box.
				applyTopicSelection( termId );

				// Proceed to step 2.
				fetchStep2( termId );
			} );
		} );

		var closeBtn = document.getElementById( 'scos-ca-modal-close' );
		if ( closeBtn ) closeBtn.addEventListener( 'click', closeModal );
	}

	// -------------------------------------------------------------------------
	// Step 2 render — Intent Goal suggestions
	// -------------------------------------------------------------------------

	function renderStep2( goals ) {
		state.step = 2;
		var path   = detectFillPath();

		var topicLabel = state.selectedTopic ? state.selectedTopic.name : '';

		var html = '<div class="scos-ca-modal-inner">';
		html += '<div class="scos-ca-step-header">Step 2 of 2 &mdash; Intent Goal'
			+ ( topicLabel ? ' <span style="opacity:.7;">(scoped to: ' + escHtml( topicLabel ) + ')</span>' : '' )
			+ '</div>';
		html += '<h3 style="margin:4px 0 4px;font-size:13px;font-weight:600;">Suggested Intent Goals</h3>';

		if ( path === 'faq-linked' ) {
			html += '<p class="scos-ca-modal-note">An FAQ is already linked — edit it directly to change the intent goal.</p>';
		} else {
			html += '<p class="scos-ca-modal-note">Click a suggestion to fill the field. Save the post to keep changes.</p>';
			html += '<div class="scos-ca-pills">';
			goals.forEach( function ( item ) {
				var pct = Math.round( ( item.confidence || 0 ) * 100 );
				html += '<button type="button" class="scos-ca-pill scos-ca-pill--goal"'
					+ ' data-goal="' + escAttr( item.goal ) + '">';
				html += escHtml( item.goal );
				if ( pct > 0 ) html += ' <span style="opacity:.6;font-size:11px;">(' + pct + '%)</span>';
				html += '</button>';
			} );
			html += '</div>';
		}

		html += '<div style="margin-top:12px;display:flex;gap:8px;align-items:center;">';
		html += '<button type="button" id="scos-ca-back" class="scos-ca-modal-back">&larr; Back to Topics</button>';
		html += '<button type="button" id="scos-ca-modal-close" class="button">Close</button>';
		html += '</div>';
		html += '</div>';

		modal.innerHTML = html;

		modal.querySelectorAll( '.scos-ca-pill--goal' ).forEach( function ( pill ) {
			pill.addEventListener( 'click', function () {
				var goal = this.getAttribute( 'data-goal' );
				if ( path === 'freetext' ) {
					fillFreetext( goal );
				} else if ( path === 'faq-picker' ) {
					fillFaqPicker( goal );
				}
			} );
		} );

		var backBtn = document.getElementById( 'scos-ca-back' );
		if ( backBtn ) {
			backBtn.addEventListener( 'click', function () {
				// Restore step 1 from cache — no second API call.
				if ( state.step1Results ) {
					renderStep1( state.step1Results );
				} else {
					closeModal();
				}
			} );
		}

		var closeBtn = document.getElementById( 'scos-ca-modal-close' );
		if ( closeBtn ) closeBtn.addEventListener( 'click', closeModal );
	}

	// -------------------------------------------------------------------------
	// Step 1 fetch
	// -------------------------------------------------------------------------

	function fetchStep1() {
		clearError();
		setLoading( true );

		apiFetch( cfg.endpointTopics, {
			input: { post_id: parseInt( cfg.postId, 10 ) },
		} )
			.then( function ( data ) {
				var suggestions = data.suggestions || [];
				if ( ! suggestions.length ) {
					throw new Error( 'No topic suggestions returned. Please try again.' );
				}
				setLoading( false );
				renderStep1( suggestions );
			} )
			.catch( function ( err ) {
				setLoading( false );
				showError( err.message || 'Something went wrong. Please try again.' );
				console.error( '[scos-ca-suggest step1]', err );
			} );
	}

	// -------------------------------------------------------------------------
	// Step 2 fetch
	// -------------------------------------------------------------------------

	function fetchStep2( termId ) {
		// Show loading state inside the modal (already open from step 1).
		modal.innerHTML = '<div class="scos-ca-modal-inner">'
			+ '<div class="scos-ca-step-header">Step 2 of 2 &mdash; Intent Goal</div>'
			+ '<p class="scos-ca-modal-note" style="margin-top:8px;">Getting intent goal suggestions&hellip;</p>'
			+ '</div>';

		apiFetch( cfg.endpointIntentGoal, {
			input: {
				post_id:       parseInt( cfg.postId, 10 ),
				topic_term_id: termId,
			},
		} )
			.then( function ( data ) {
				var goals = data.intent_goals || [];
				if ( ! goals.length ) {
					throw new Error( 'No intent goal suggestions returned. Please try again.' );
				}
				renderStep2( goals );
			} )
			.catch( function ( err ) {
				// On step 2 failure, show error inside modal with back button.
				modal.innerHTML = '<div class="scos-ca-modal-inner">'
					+ '<p style="color:#cc0000;font-size:12px;">' + escHtml( err.message || 'Something went wrong.' ) + '</p>'
					+ '<div style="margin-top:8px;display:flex;gap:8px;">'
					+ '<button type="button" id="scos-ca-back" class="scos-ca-modal-back">&larr; Back to Topics</button>'
					+ '<button type="button" id="scos-ca-modal-close" class="button">Close</button>'
					+ '</div></div>';
				var backBtn  = document.getElementById( 'scos-ca-back' );
				var closeBtn = document.getElementById( 'scos-ca-modal-close' );
				if ( backBtn ) backBtn.addEventListener( 'click', function () { renderStep1( state.step1Results ); } );
				if ( closeBtn ) closeBtn.addEventListener( 'click', closeModal );
				console.error( '[scos-ca-suggest step2]', err );
			} );
	}

	// -------------------------------------------------------------------------
	// Entry point
	// -------------------------------------------------------------------------

	btn.addEventListener( 'click', fetchStep1 );

	// -------------------------------------------------------------------------
	// Utility
	// -------------------------------------------------------------------------

	function escHtml( str ) {
		var d = document.createElement( 'div' );
		d.appendChild( document.createTextNode( String( str ) ) );
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
