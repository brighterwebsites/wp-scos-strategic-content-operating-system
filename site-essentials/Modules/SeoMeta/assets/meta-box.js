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
		updateNoindexSitemapNotice();
		updateCanonicalHint();
	});

	// ── Noindex ↔ sitemap notice ─────────────────────────────────────────────

	function updateNoindexSitemapNotice() {
		var isNoindex  = $('#scos_seo_robots_noindex').is(':checked');
		var $notice    = $('#scos-noindex-sitemap-notice');
		var $override  = $('#scos_seo_sitemap_noindex_override').closest('label');
		var $overrideP = $override.next('p.scos-seo-help');

		if (isNoindex) {
			if (!$notice.length) {
				$('.scos-seo-field:has(#scos_seo_sitemap_noindex_override)').prepend(
					'<div class="scos-seo-notice scos-seo-notice--warn" id="scos-noindex-sitemap-notice">' +
					scosSeoMeta.noindexSitemapMsg +
					'</div>'
				);
			} else {
				$notice.show();
			}
			$override.show();
			$overrideP.show();
		} else {
			$notice.hide();
			// Only hide the override row if it's not already checked (user may want to keep it)
			if (!$('#scos_seo_sitemap_noindex_override').is(':checked')) {
				$override.hide();
				$overrideP.hide();
			}
		}
	}

	$(document).on('change', '#scos_seo_robots_noindex', updateNoindexSitemapNotice);

	// ── Canonical non-self hint ──────────────────────────────────────────────

	function updateCanonicalHint() {
		var $canonical  = $('#scos_seo_canonical');
		var selfUrl     = $canonical.attr('placeholder') || '';
		var val         = $.trim($canonical.val());
		var isNonSelf   = val !== '' && val !== selfUrl;
		$('.scos-seo-help--canonical-hint').toggle(isNonSelf);
	}

	$(document).on('input blur', '#scos_seo_canonical', updateCanonicalHint);

}(jQuery));
