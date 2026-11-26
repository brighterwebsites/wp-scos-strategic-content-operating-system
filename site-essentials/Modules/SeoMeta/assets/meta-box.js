/**
 * SEO Meta Box — JavaScript
 * Tab switching + character counter with progress bar, and a guard that keeps
 * the TLDR editor usable inside the block editor's meta box pane.
 *
 * v1.4 | 2026-11-26 — Canonical hints compare with scosSameUrl(), the mirror of
 *                      Url_Seo_State::same_url() (#283).
 * v1.3 | 2026-11-26 — "Include in XML sitemap anyway" appears as soon as noindex is ticked or a
 *                      non-self canonical is typed (was looking for a non-existent #id).
 * v1.2 | 2026-11-26 — Advanced tab dot follows the fields live (#113).
 * v1.1 | 2026-09-11 — Rebuild the TLDR TinyMCE instance when its iframe is
 *                      reloaded by the block editor moving the meta box DOM.
 */
/* global jQuery */

// scos-same-url:start — mirror of Url_Seo_State::same_url(); tested against
// tests/fixtures/same-url-cases.json by tests/same-url-test.js. Ignores trailing
// slash, scheme, host case and #fragment; a query string or www makes it different.
window.scosSameUrl = function (a, b) {
	function key(u) {
		u = String(u == null ? '' : u).trim().split('#')[0];
		var m = u.match(/^(?:[a-z][a-z0-9+.-]*:)?(?:\/\/([^\/?]*))?([^?]*)(\?.*)?$/i);
		if (!m) { return u; }
		return '//' + (m[1] || '').toLowerCase() + (m[2] || '').replace(/\/+$/, '') + (m[3] || '');
	}
	return key(a) === key(b);
};
// scos-same-url:end

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
		var $row       = $('#scos-seo-sitemap-override');
		if (!$row.length) { return; }
		var isNoindex  = $('input[name="scos_seo_robots[]"][value="noindex"]').is(':checked') ||
			($('input[name="scos_seo_robots[]"]:checked').length === 0 && String($row.data('type-noindex')) === '1');
		var $canonical = $('#scos_seo_canonical');
		var canonVal   = $.trim($canonical.val() || '');
		var isNonSelf  = canonVal !== '' && !window.scosSameUrl(canonVal, $canonical.attr('placeholder') || '');
		var $notice    = $('#scos-noindex-sitemap-notice');

		if (isNoindex) {
			if (!$notice.length && window.scosSeoMeta && scosSeoMeta.noindexSitemapMsg) {
				$row.closest('.scos-seo-field').find('> label').first().after(
					'<div class="scos-seo-notice scos-seo-notice--warn" id="scos-noindex-sitemap-notice">' +
					scosSeoMeta.noindexSitemapMsg +
					'</div>'
				);
			} else {
				$notice.show();
			}
		} else {
			$notice.hide();
		}

		// Keep the override visible while it is ticked, so it can be unticked.
		$row.prop('hidden', !(isNoindex || isNonSelf || $('#scos_seo_sitemap_noindex_override').is(':checked')));
	}

	$(document).on('change', 'input[name="scos_seo_robots[]"]', updateNoindexSitemapNotice);
	$(document).on('change', '#scos_seo_sitemap_noindex_override', updateNoindexSitemapNotice);
	$(document).on('input blur', '#scos_seo_canonical', updateNoindexSitemapNotice);

	// ── Canonical non-self hint ──────────────────────────────────────────────

	function updateCanonicalHint() {
		var $canonical  = $('#scos_seo_canonical');
		var selfUrl     = $canonical.attr('placeholder') || '';
		var val         = $.trim($canonical.val());
		var isNonSelf   = val !== '' && !window.scosSameUrl(val, selfUrl);
		$('.scos-seo-help--canonical-hint').toggle(isNonSelf);
	}

	$(document).on('input blur', '#scos_seo_canonical', updateCanonicalHint);

	// ── Advanced tab dot: a field there holds a value for this post (#113) ──
	//
	// Mirrors Url_Seo_State::post_overrides(): any value set here replaces (or stops
	// following) the default, so the dot shows what the next save will store.

	function updateAdvancedDot() {
		var $panel = $('#scos-seo-tab-advanced');
		if (!$panel.length) { return; }
		var globalFreeze = String($('.scos-seo-tab-btn[data-tab="advanced"]').data('global-freeze')) === '1';
		var freezeOn     = $panel.find('input[name="scos_seo_freeze_og_date"]').is(':checked');

		var isSet = $.trim($('#scos_seo_canonical').val() || '') !== '' ||
			$panel.find('input[name="scos_seo_robots[]"]:checked').length > 0 ||
			freezeOn !== globalFreeze ||
			$panel.find('input[name="scos_seo_sitemap_exclude[]"]:checked').length > 0 ||
			$('#scos_seo_sitemap_noindex_override').is(':checked');

		$('#scos-seo-advanced-dot').prop('hidden', !isSet);
	}

	$(document).on('input change', '#scos-seo-tab-advanced input', updateAdvancedDot);
	$(updateAdvancedDot);

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
