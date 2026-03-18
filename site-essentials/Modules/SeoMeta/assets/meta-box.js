/**
 * SEO Meta Box — JavaScript
 * Tab switching + character counter with progress bar.
 */
/* global jQuery */
(function ($) {
	'use strict';

	// ── Tab switching ────────────────────────────────────────────────────────

	$(document).on('click', '.scos-seo-tab-btn', function () {
		var $btn   = $(this);
		var tabId  = $btn.data('tab');
		var $wrap  = $btn.closest('.scos-seo-wrap');

		$wrap.find('.scos-seo-tab-btn').removeClass('is-active').attr('aria-selected', 'false');
		$btn.addClass('is-active').attr('aria-selected', 'true');

		$wrap.find('.scos-seo-tab-panel').removeClass('is-active').attr('hidden', 'hidden');
		$wrap.find('#scos-seo-tab-' + tabId).addClass('is-active').removeAttr('hidden');
	});

	// ── Character counter + progress bar ────────────────────────────────────

	function updateCounter(fieldId) {
		var $field   = $('#' + fieldId);
		if (!$field.length) { return; }

		var max      = parseInt($field.closest('.scos-seo-field').find('[data-max]').data('max'), 10);
		var len      = $field.val().length;
		var pct      = Math.min(len / max * 100, 100);
		var isOver   = len > max;
		var isGood   = len > 0 && !isOver;

		// Counter text
		var $counter = $('[data-target="' + fieldId + '"].scos-seo-counter');
		$counter.find('.scos-seo-count').text(len);
		$counter.toggleClass('is-over', isOver).toggleClass('is-good', isGood);

		// Bar
		var $bar = $('[data-target="' + fieldId + '"].scos-seo-bar');
		$bar.find('.scos-seo-bar__fill').css('width', pct + '%');
		$bar.toggleClass('is-over', isOver).toggleClass('is-good', isGood);
	}

	// Live updates
	$(document).on('input', '#scos_seo_title, #scos_seo_description', function () {
		updateCounter(this.id);
	});

	// Init on load
	$(function () {
		updateCounter('scos_seo_title');
		updateCounter('scos_seo_description');
	});

}(jQuery));
