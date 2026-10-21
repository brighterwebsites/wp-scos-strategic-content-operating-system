<?php
/**
 * Agentic — enquiry handoff: fill the form from the link
 *
 * v1.0 | 2026-10-22
 *
 * On the enquiry page only, a small inline script reads the #scos-enquiry
 * fragment built by Enquiry_Form::build_link(), fills the annotated form,
 * opens it if it lives in a Breakdance popup, and clears the fragment from
 * the address bar. It never submits: the person does. (#200 D17)
 *
 * Fields are matched with the same rule as Enquiry_Form::field_key().
 * Hidden fields, buttons and honeypots (tabindex -1) are never filled, so a
 * hand-built link can't trip the form's spam check.
 *
 * LiteSpeed: data-no-optimize keeps "Delay JS" from holding the script back
 * until the visitor's first interaction.
 *
 * @package    SiteEssentials
 * @subpackage Modules\Agentic
 */

namespace SiteEssentials\Modules\Agentic;

defined( 'ABSPATH' ) || exit;

class Enquiry_Prefill {

	public static function init(): void {
		if ( ! Enquiry_Form::is_enabled() ) {
			return;
		}
		add_action( 'wp_footer', [ __CLASS__, 'print_script' ] );
	}

	public static function print_script(): void {
		if ( ! is_page( absint( get_option( Enquiry_Form::OPTION_PAGE, 0 ) ) ) ) {
			return;
		}

		$config = [
			'fragment' => Enquiry_Form::FRAGMENT,
			'form'     => trim( (string) get_option( Enquiry_Form::OPTION_FORM, '' ) ),
		];

		wp_print_inline_script_tag(
			'(function(c){' . self::script() . '})(' . wp_json_encode( $config ) . ');',
			[ 'data-no-optimize' => '1' ]
		);
	}

	private static function script(): string {
		return <<<'JS'
var h = location.hash;
if (h.indexOf('#' + c.fragment) !== 0) return;
var p = new URLSearchParams(h.slice(c.fragment.length + 1));
history.replaceState(null, '', location.pathname + location.search);
var skip = ['hidden', 'submit', 'button', 'reset', 'image', 'file', 'password', 'hpinput'];
function key(n) {
	var m = n.match(/^fields\[([^\]]*)\](\[\])?$/);
	return m ? m[1] : n.replace(/\[\]$/, '');
}
function lower(v) { return String(v).toLowerCase(); }
function fill(f) {
	Array.prototype.forEach.call(f.elements, function (el) {
		var type = lower(el.getAttribute('type') || el.tagName);
		if (!el.name || skip.indexOf(type) > -1 || el.getAttribute('tabindex') === '-1') return;
		var k = key(el.name);
		if (!p.has(k)) return;
		var vals = p.getAll(k).map(lower);
		if (type === 'checkbox' || type === 'radio') {
			el.checked = vals.indexOf(lower(el.value)) > -1;
		} else if (el.tagName === 'SELECT') {
			Array.prototype.forEach.call(el.options, function (o) { o.selected = vals.indexOf(lower(o.value)) > -1; });
		} else {
			el.value = p.getAll(k).join(', ');
		}
		el.dispatchEvent(new Event('input', { bubbles: true }));
		el.dispatchEvent(new Event('change', { bubbles: true }));
	});
}
function show(f, tries) {
	var pop = f.closest('[data-breakdance-popup-id]');
	if (!pop) { f.scrollIntoView({ block: 'center' }); return; }
	var id = pop.getAttribute('data-breakdance-popup-id');
	if (window.BreakdancePopup && window.breakdancePopupInstances && window.breakdancePopupInstances[id]) {
		window.BreakdancePopup.runAction(id, 'open');
	} else if (tries < 20) {
		setTimeout(function () { show(f, tries + 1); }, 250);
	}
}
function go() {
	var f = document.querySelector(c.form ? 'form[toolname="' + c.form.replace(/"/g, '') + '"]' : 'form[toolname]');
	if (!f) return;
	fill(f);
	show(f, 0);
}
if (document.readyState === 'complete') go(); else window.addEventListener('load', go);
JS;
	}
}
