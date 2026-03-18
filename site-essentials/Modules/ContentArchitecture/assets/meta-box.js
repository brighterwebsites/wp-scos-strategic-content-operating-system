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

	var $quickAdd  = $('#scos-ca-quick-add');
	var $qaLabel   = $('#scos-ca-quick-add-label');
	var $qaInput   = $('#scos-ca-quick-add-name');
	var $qaSave    = $('#scos-ca-quick-add-save');
	var $qaCancel  = $('#scos-ca-quick-add-cancel');
	var currentTax    = '';
	var currentTarget = '';

	function showQuickAdd($btn) {
		currentTax    = $btn.data('taxonomy');
		currentTarget = $btn.data('target');
		var labelText = $btn.data('label') || scosCA.i18n.add;

		$qaLabel.text(labelText);
		$qaInput.val('');
		$quickAdd.removeAttr('hidden').insertAfter($btn);
		$qaInput.focus();
	}

	function hideQuickAdd() {
		$quickAdd.attr('hidden', 'hidden');
		currentTax    = '';
		currentTarget = '';
	}

	$(document).on('click', '.scos-ca-add-term', function (e) {
		e.preventDefault();
		showQuickAdd($(this));
	});

	$qaCancel.on('click', hideQuickAdd);

	$qaInput.on('keydown', function (e) {
		if (e.which === 13) { e.preventDefault(); $qaSave.trigger('click'); }
		if (e.which === 27) { hideQuickAdd(); }
	});

	$qaSave.on('click', function () {
		var name = $.trim($qaInput.val());
		if (!name) {
			alert(scosCA.i18n.errorEmpty);
			$qaInput.focus();
			return;
		}

		$qaSave.prop('disabled', true).text(scosCA.i18n.adding);

		$.post(
			scosCA.ajaxurl,
			{
				action:   'scos_ca_add_term',
				taxonomy: currentTax,
				name:     name,
				_ajax_nonce: scosCA.nonce,
			},
			function (resp) {
				if (resp.success && resp.data && resp.data.term_id) {
					// Append the new option and select it.
					var $select = $('#' + currentTarget);
					$('<option>', {
						value:    resp.data.term_id,
						text:     resp.data.name,
						selected: true,
					}).appendTo($select);

					hideQuickAdd();
				} else {
					alert(resp.data || scosCA.i18n.errorFailed);
				}
			}
		).fail(function () {
			alert(scosCA.i18n.errorFailed);
		}).always(function () {
			$qaSave.prop('disabled', false).text(scosCA.i18n.add);
		});
	});

}(jQuery));
