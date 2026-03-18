/**
 * Content Architecture Meta Box — JavaScript
 *
 * Handles:
 *  - Tab switching (Strategy / Analysis / Workflow)
 *  - Progress tag toggle (checkbox <-> visual badge)
 *  - Next Step select colour update
 *  - Quick-add term (AJAX: scos_ca_add_term)
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

}(jQuery));
