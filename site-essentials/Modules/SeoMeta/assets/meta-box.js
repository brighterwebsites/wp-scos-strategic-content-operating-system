/**
 * SEO Meta Box — JavaScript
 * Tab switching + character counter with progress bar, and a guard that keeps
 * the TLDR editor usable inside the block editor's meta box pane.
 *
 * v1.1 | 2026-09-11 — Rebuild the TLDR TinyMCE instance when its iframe is
 *                      reloaded by the block editor moving the meta box DOM.
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

	// ── TLDR editor: survive the block editor moving the meta box ────────────
	//
	// scos_seo_tldr is a TinyMCE (wp_editor) instance, which renders into an
	// iframe. In the block editor the meta box DOM can be moved after TinyMCE has
	// initialised, and moving an iframe makes the browser reload it. TinyMCE keeps
	// pointing at the discarded document, so the box shows blank and can't be
	// clicked or typed in — while scripted writes (Suggest TLDR) still save,
	// because they go through the textarea. Whether it happens depends on load
	// order, so it's intermittent per machine and per page load.
	//
	// Fix: whenever the iframe reloads and TinyMCE is no longer attached to its
	// document, rebuild the editor on the same textarea. Rebuild from WordPress's
	// own per-editor settings (tinyMCEPreInit.mceInit) — 'mceAddEditor' would
	// reuse whichever settings were initialised last (e.g. an ACF WYSIWYG).

	var TLDR_ID = 'scos_seo_tldr';

	function tldrEditorDetached(editor) {
		var ifr = document.getElementById(TLDR_ID + '_ifr');
		if (!editor || !ifr) { return false; }
		var doc = ifr.contentDocument;
		return !doc || editor.getBody() !== doc.body;
	}

	function rebuildTldrEditorIfDetached() {
		var editor = window.tinymce ? window.tinymce.get(TLDR_ID) : null;
		if (!tldrEditorDetached(editor)) { return; }

		var init = window.tinyMCEPreInit && window.tinyMCEPreInit.mceInit ? window.tinyMCEPreInit.mceInit[TLDR_ID] : null;
		editor.save(); // flush the latest content (e.g. a Suggest TLDR pick) into the textarea
		editor.remove();
		if (init) {
			window.tinymce.init(init);
		} else {
			window.tinymce.execCommand('mceAddEditor', false, TLDR_ID);
		}
	}

	function watchTldrIframe(editor) {
		if (!editor || editor.id !== TLDR_ID) { return; }
		var ifr = document.getElementById(TLDR_ID + '_ifr');
		if (ifr) {
			ifr.addEventListener('load', function () {
				window.setTimeout(rebuildTldrEditorIfDetached, 0);
			});
		}
	}

	// TinyMCE's scripts print after this file in the footer, so wait for DOM ready.
	$(function () {
		if (!window.tinymce || !document.getElementById(TLDR_ID)) { return; }

		// Editors created from now on — including our own rebuilds.
		window.tinymce.on('AddEditor', function (e) {
			if (e.editor && e.editor.id === TLDR_ID) {
				e.editor.on('init', function () { watchTldrIframe(e.editor); });
			}
		});

		// The editor if it already exists.
		var existing = window.tinymce.get(TLDR_ID);
		if (existing) {
			if (existing.initialized) {
				watchTldrIframe(existing);
			} else {
				existing.on('init', function () { watchTldrIframe(existing); });
			}
		}

		// Safety net: a move that happened before the load listener was attached.
		[1000, 3000, 6000].forEach(function (ms) {
			window.setTimeout(rebuildTldrEditorIfDetached, ms);
		});
	});

}(jQuery));
