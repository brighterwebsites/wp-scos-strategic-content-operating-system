/**
 * Content Architecture Meta Box — JavaScript
 *
 * Handles:
 *  - Tab switching (Strategy / Analysis / Workflow)
 *  - Progress tag toggle (checkbox <-> visual badge)
 *  - Next Step select colour update
 *  - Quick-add term (AJAX: scos_ca_add_term)
 *  - FAQ intent goal picker: search, select, clear, Add FAQ modal
 *
 * v1.1 | 2026-05-22 — FAQ intent goal picker added.
 */
/* global scosCA, jQuery */
(function ($) {
	'use strict';

	// ─── Tab switching ───────────────────────────────────────────────────────

	$(document).on('click', '.scos-ca-tab-btn', function () {
		var $btn    = $(this);
		var tabId   = $btn.data('tab');
		var $wrap   = $btn.closest('.scos-ca-wrap');

		$wrap.find('.scos-ca-tab-btn').removeClass('is-active').attr('aria-selected', 'false');
		$btn.addClass('is-active').attr('aria-selected', 'true');

		$wrap.find('.scos-ca-tab-panel').removeClass('is-active').attr('hidden', 'hidden');
		$wrap.find('#scos-tab-' + tabId).addClass('is-active').removeAttr('hidden');
	});

	// ─── Progress tag toggle ─────────────────────────────────────────────────

	$(document).on('change', '.scos-ca-progress-tag input[type="checkbox"]', function () {
		$(this).closest('.scos-ca-progress-tag').toggleClass('is-selected', this.checked);
	});

	// Allow clicking the label to toggle (checkbox is visually hidden).
	$(document).on('click', '.scos-ca-progress-tag', function (e) {
		// Clicks bubble from the hidden checkbox naturally — no extra JS needed.
		// This handler just ensures the label click doesn't fire twice.
		if ($(e.target).is('input')) { return; }
	});

	// ─── Index Status — colour update ────────────────────────────────────────

	function updateIndexSelectStyle($sel) {
		var $opt = $sel.find('option:selected');
		var color = $opt.data('color');
		var bg    = $opt.data('bg');
		if (color && bg) {
			$sel.css({ color: color, background: bg, fontWeight: '500' });
		} else {
			$sel.css({ color: '', background: '', fontWeight: '' });
		}
	}

	$(document).on('change', '.scos-ca-index-select', function () {
		updateIndexSelectStyle($(this));
	});
	$('.scos-ca-index-select').each(function () {
		updateIndexSelectStyle($(this));
	});

	// ─── Next Step — colour update ───────────────────────────────────────────

	function updateNextStepStyle($sel) {
		var $opt = $sel.find('option:selected');
		var color = $opt.data('color');
		var bg    = $opt.data('bg');
		if (color && bg) {
			$sel.css({ color: color, background: bg, fontWeight: '500' });
		} else {
			$sel.css({ color: '', background: '', fontWeight: '' });
		}
	}

	$(document).on('change', '.scos-ca-next-step-select', function () {
		updateNextStepStyle($(this));
	});
	$('.scos-ca-next-step-select').each(function () {
		updateNextStepStyle($(this));
	});

	// ─── Quick-add Term ──────────────────────────────────────────────────────

	var $quickAdd = $('#scos-ca-quick-add');

	function getQA() {
		// Always get fresh references so DOM moves don't break anything.
		return {
			$label:  $('#scos-ca-quick-add-label'),
			$input:  $('#scos-ca-quick-add-name'),
			$save:   $('#scos-ca-quick-add-save'),
			$cancel: $('#scos-ca-quick-add-cancel'),
		};
	}

	function showQuickAdd($btn) {
		var qa = getQA();
		// Store taxonomy + target directly on the panel so no closure variable needed.
		$quickAdd
			.data('taxonomy', $btn.data('taxonomy'))
			.data('target',   $btn.data('target'));

		qa.$label.text($btn.data('label') || scosCA.i18n.add);
		qa.$input.val('');
		$quickAdd.removeAttr('hidden').insertAfter($btn);
		qa.$input.focus();
	}

	function hideQuickAdd() {
		$quickAdd.attr('hidden', 'hidden').removeData('taxonomy').removeData('target');
	}

	$(document).on('click', '.scos-ca-add-term', function (e) {
		e.preventDefault();
		showQuickAdd($(this));
	});

	$(document).on('click', '#scos-ca-quick-add-cancel', hideQuickAdd);

	$(document).on('keydown', '#scos-ca-quick-add-name', function (e) {
		if (e.which === 13) { e.preventDefault(); $('#scos-ca-quick-add-save').trigger('click'); }
		if (e.which === 27) { hideQuickAdd(); }
	});

	$(document).on('click', '#scos-ca-quick-add-save', function () {
		var qa         = getQA();
		var name       = $.trim(qa.$input.val());
		var taxonomy   = $quickAdd.data('taxonomy');
		var target     = $quickAdd.data('target');

		if (!name) {
			alert(scosCA.i18n.errorEmpty);
			qa.$input.focus();
			return;
		}

		qa.$save.prop('disabled', true).text(scosCA.i18n.adding);

		$.post(
			scosCA.ajaxurl,
			{
				action:      'scos_ca_add_term',
				taxonomy:    taxonomy,
				name:        name,
				_ajax_nonce: scosCA.nonce,
			},
			function (resp) {
				if (resp.success && resp.data && resp.data.term_id) {
					$('<option>', {
						value:    resp.data.term_id,
						text:     resp.data.name,
						selected: true,
					}).appendTo($('#' + target));
					hideQuickAdd();
				} else {
					alert(resp.data || scosCA.i18n.errorFailed);
				}
			}
		).fail(function () {
			alert(scosCA.i18n.errorFailed);
		}).always(function () {
			qa.$save.prop('disabled', false).text(scosCA.i18n.add);
		});
	});

	// ─── FAQ Intent Goal Picker ──────────────────────────────────────────────

	if ( scosCA.faqModuleActive ) {

		var faqSearchTimer = null;

		// ── Helpers ─────────────────────────────────────────────────────────

		function faqPanelHtml(faq) {
			var incomplete = faq.incomplete
				? '<div class="scos-ca-intent-faq-incomplete">' +
					scosCA.i18n.faqIncomplete +
					'<a href="' + faq.edit_url + '" target="_blank" rel="noopener">' +
					scosCA.i18n.faqEditLink + '</a></div>'
				: '';
			var topic = faq.topic
				? '<span class="scos-ca-intent-faq-topic">' + $('<span>').text(faq.topic).html() + '</span>'
				: '';
			return '<div class="scos-ca-intent-faq-panel" id="scos-intent-faq-panel">' +
				'<div class="scos-ca-intent-faq-question">' + $('<span>').text(faq.title).html() + topic + '</div>' +
				'<div class="scos-ca-intent-faq-actions">' +
					'<a href="' + faq.edit_url + '" target="_blank" rel="noopener" class="scos-ca-intent-faq-edit">' +
					scosCA.i18n.faqEditLink + '</a>' +
					'<button type="button" class="scos-ca-intent-faq-clear button-link">' + scosCA.i18n.faqClear + '</button>' +
				'</div>' +
				incomplete +
				'</div>';
		}

		function faqResultItemHtml(faq) {
			var badge = faq.incomplete
				? '<span class="scos-ca-intent-faq-badge scos-ca-intent-faq-badge--warn">' + scosCA.i18n.faqNeedsAnswer + '</span>'
				: '';
			var draft = 'draft' === faq.status
				? '<span class="scos-ca-intent-faq-badge scos-ca-intent-faq-badge--draft">' + scosCA.i18n.faqDraft + '</span>'
				: '';
			var topic = faq.topic
				? '<span class="scos-ca-intent-faq-topic">' + $('<span>').text(faq.topic).html() + '</span>'
				: '';
			return '<li class="scos-ca-intent-faq-result" data-id="' + faq.id + '" data-title="' + $('<span>').text(faq.title).html() + '">' +
				$('<span>').text(faq.title).html() + topic + draft + badge +
				'</li>';
		}

		function showPicker() {
			$('#scos-intent-faq-panel').remove();
			var $wrap = $('#scos-intent-goal-wrap');
			if ( !$wrap.find('#scos-intent-faq-picker').length ) {
				$wrap.prepend(
					'<div class="scos-ca-intent-faq-picker" id="scos-intent-faq-picker">' +
					'<div class="scos-ca-intent-faq-search-row">' +
					'<input type="text" id="scos-intent-faq-search" class="scos-ca-intent-faq-search" placeholder="' + scosCA.i18n.faqSearch + '" autocomplete="off">' +
					'<button type="button" class="button scos-ca-intent-faq-add-btn" id="scos-intent-faq-add-btn">' + scosCA.i18n.faqAddNew + '</button>' +
					'</div>' +
					'<ul class="scos-ca-intent-faq-results" id="scos-intent-faq-results" hidden></ul>' +
					'</div>'
				);
			}
		}

		function hidePicker() {
			$('#scos-intent-faq-picker').remove();
		}

		function selectFaq(faq) {
			$('#scos_ca_intent_goal_faq_id').val(faq.id);
			$('#scos_ca_intent_goal_pending_faq_title').val('');
			hidePicker();
			$('#scos-intent-faq-panel').remove();
			$('#scos-intent-goal-wrap').prepend(faqPanelHtml(faq));
		}

		// ── Search ──────────────────────────────────────────────────────────

		$(document).on('input', '#scos-intent-faq-search', function () {
			var q = $.trim($(this).val());
			var $results = $('#scos-intent-faq-results');
			clearTimeout(faqSearchTimer);

			if (q.length < 2) {
				$results.attr('hidden', 'hidden').empty();
				return;
			}

			faqSearchTimer = setTimeout(function () {
				$.ajax({
					url:      scosCA.restUrl + '/search',
					method:   'GET',
					data:     { q: q, context: 'intent_goal' },
					beforeSend: function (xhr) {
						xhr.setRequestHeader('X-WP-Nonce', scosCA.restNonce);
					},
					success: function (faqs) {
						$results.empty();
						if (faqs.length) {
							$.each(faqs, function (i, faq) {
								$results.append(faqResultItemHtml(faq));
							});
							$results.removeAttr('hidden');
						} else {
							$results.attr('hidden', 'hidden');
						}
					},
				});
			}, 280);
		});

		$(document).on('click', '.scos-ca-intent-faq-result', function () {
			var id    = parseInt($(this).data('id'), 10);
			var title = $(this).data('title');
			var topic = $(this).find('.scos-ca-intent-faq-topic').text();
			var incomplete = $(this).find('.scos-ca-intent-faq-badge--warn').length > 0;
			var status     = $(this).find('.scos-ca-intent-faq-badge--draft').length > 0 ? 'draft' : 'publish';

			selectFaq({
				id:         id,
				title:      title,
				topic:      topic,
				status:     status,
				incomplete: incomplete,
				edit_url:   '', // edit link not available in search result; panel will just omit it
			});
		});

		// ── Clear linked FAQ ────────────────────────────────────────────────

		$(document).on('click', '.scos-ca-intent-faq-clear', function () {
			$('#scos_ca_intent_goal_faq_id').val('0');
			$('#scos_ca_intent_goal_pending_faq_title').val('');
			$('#scos-intent-faq-panel').remove();
			showPicker();
		});

		// ── Add FAQ button → open modal ──────────────────────────────────────

		$(document).on('click', '#scos-intent-faq-add-btn', function () {
			$('#scos-intent-faq-modal').removeAttr('hidden');
			$('#scos-intent-faq-new-title').val('').focus();
			$('#scos-intent-faq-modal-status').attr('hidden', 'hidden').text('');
		});

		$(document).on('click', '#scos-intent-faq-modal-cancel', function () {
			$('#scos-intent-faq-modal').attr('hidden', 'hidden');
		});

		// ── Add now (REST POST) ──────────────────────────────────────────────

		$(document).on('click', '#scos-intent-faq-create-now', function () {
			var title = $.trim($('#scos-intent-faq-new-title').val());
			if (!title) {
				$('#scos-intent-faq-new-title').focus();
				return;
			}

			var useTopic  = $('#scos-intent-faq-use-topic').is(':checked');
			var topicId   = useTopic ? parseInt($('#scos_ca_topic').val(), 10) || 0 : 0;
			var postId    = parseInt($('input#post_ID').val(), 10) || 0;
			var $btn      = $(this);
			var $status   = $('#scos-intent-faq-modal-status');

			$btn.prop('disabled', true).text(scosCA.i18n.faqCreating);
			$status.removeAttr('hidden').text(scosCA.i18n.faqCreating);

			$.ajax({
				url:         scosCA.restUrl,
				method:      'POST',
				contentType: 'application/json',
				data:        JSON.stringify({ title: title, topic_id: topicId, source_post_id: postId }),
				beforeSend:  function (xhr) {
					xhr.setRequestHeader('X-WP-Nonce', scosCA.restNonce);
				},
				success: function (faq) {
					$('#scos-intent-faq-modal').attr('hidden', 'hidden');
					selectFaq(faq);
					$status.removeAttr('hidden').text(scosCA.i18n.faqCreated);
				},
				error: function (xhr) {
					var msg = xhr.responseJSON && xhr.responseJSON.message
						? xhr.responseJSON.message
						: scosCA.i18n.errorFailed;
					$status.removeAttr('hidden').text(msg);
				},
			}).always(function () {
				$btn.prop('disabled', false).text(scosCA.i18n.faqAddNow);
			});
		});

		// ── Create on save ───────────────────────────────────────────────────

		$(document).on('click', '#scos-intent-faq-create-on-save', function () {
			var title = $.trim($('#scos-intent-faq-new-title').val());
			if (!title) {
				$('#scos-intent-faq-new-title').focus();
				return;
			}

			$('#scos_ca_intent_goal_pending_faq_title').val(title);
			$('#scos_ca_intent_goal_faq_id').val('0');
			$('#scos-intent-faq-modal').attr('hidden', 'hidden');
			hidePicker();

			// Show a pending notice in place of the picker.
			$('#scos-intent-faq-panel').remove();
			$('#scos-intent-goal-wrap').prepend(
				'<div class="scos-ca-intent-faq-panel scos-ca-intent-faq-panel--pending" id="scos-intent-faq-panel">' +
				'<div class="scos-ca-intent-faq-question">' + $('<span>').text(title).html() +
				'<span class="scos-ca-intent-faq-badge scos-ca-intent-faq-badge--draft">Pending save</span></div>' +
				'<div class="scos-ca-intent-faq-actions">' +
				'<button type="button" class="scos-ca-intent-faq-clear button-link">' + scosCA.i18n.faqClear + '</button>' +
				'</div></div>'
			);
		});

		// Allow scos-ca-suggest.js to link an existing FAQ via custom event.
		document.addEventListener( 'scos:selectFaq', function ( e ) {
			if ( e.detail && e.detail.id ) {
				selectFaq( e.detail );
			}
		} );

	} // end if faqModuleActive

}(jQuery));
