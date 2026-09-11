/**
 * Social Amplification Meta Box — JavaScript
 * Handles the Postly "Create Social Post" / "Reset & Re-amplify" button (scos_sa_amplify)
 * and "Retry failed posts" (scos_sa_retry_failed). The server replies with the refreshed
 * status block as HTML plus a one-line summary, so this file only swaps them in.
 *
 * v1.1 | 2026-09-11 — Server-rendered status, retry button, real error messages.
 */
/* global scosSA, jQuery */
(function ($) {
	'use strict';

	var i18n = (window.scosSA && scosSA.i18n) || {};

	function showMessage(cls, text) {
		$('#scos-sa-reamp-msg')
			.removeAttr('hidden')
			.removeClass('is-success is-warning is-error')
			.addClass(cls || '')
			.text(text || '');
	}

	function outcomeClass(outcome) {
		if (outcome === 'complete') { return 'is-success'; }
		if (outcome === 'partial') { return 'is-warning'; }
		return 'is-error';
	}

	function showError(data) {
		if (!data || !data.message) {
			showMessage('is-error', (i18n.error || 'Error') + ': ' + (i18n.requestFailed || 'Request failed.'));
			return;
		}
		if (data.code === 'config_error' && scosSA.settingsUrl) {
			showMessage('is-error', (i18n.configError || 'Captions could not be generated. Check') + ' ');
			$('#scos-sa-reamp-msg')
				.append($('<a>').attr('href', scosSA.settingsUrl).text(i18n.settingsLink || 'Social Amplification settings'))
				.append(document.createTextNode('. ' + data.message));
			return;
		}
		showMessage('is-error', (i18n.error || 'Error') + ': ' + data.message);
	}

	function send(action, postId, busyText) {
		var $buttons = $('#scos-sa-reamp-btn, #scos-sa-retry-btn');
		$buttons.prop('disabled', true);
		showMessage('', busyText);

		$.post(scosSA.ajaxurl, { action: action, post_id: postId, nonce: scosSA.amplifyNonce })
			.done(function (resp) {
				var data = (resp && resp.data) || {};
				if (!resp || !resp.success) {
					showError(data);
					return;
				}
				if (data.html) {
					$('#scos-sa-amplify-status').html(data.html);
				}
				showMessage(outcomeClass(data.outcome), data.message || '');
			})
			.fail(function (jqXHR) {
				// wp_send_json_error() replies with a 4xx/5xx status, so its message arrives here.
				showError(jqXHR && jqXHR.responseJSON ? jqXHR.responseJSON.data : null);
			})
			.always(function () {
				// Buttons swapped in with the new HTML start enabled; this covers the old ones.
				$buttons.prop('disabled', false);
			});
	}

	$(document).on('click', '#scos-sa-reamp-btn', function (e) {
		e.preventDefault();
		var $btn = $(this);
		if ($btn.prop('disabled')) { return; }

		// A re-run schedules a fresh set; the last run's posts stay in Postly.
		if (parseInt($btn.attr('data-scheduled'), 10) > 0 && !window.confirm(i18n.confirmRerun || 'Schedule a new set of posts?')) {
			return;
		}
		send('scos_sa_amplify', $btn.data('post-id'), i18n.amplifying || 'Running…');
	});

	$(document).on('click', '#scos-sa-retry-btn', function (e) {
		e.preventDefault();
		var $btn = $(this);
		if ($btn.prop('disabled')) { return; }
		send('scos_sa_retry_failed', $btn.data('post-id'), i18n.retrying || 'Resending failed posts…');
	});

}(jQuery));
