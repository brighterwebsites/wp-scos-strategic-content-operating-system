<?php
/**
 * Agentic — Agent Notices (shortcodes)
 *
 * v1.0 | 2026-10-25
 *
 * Two pieces of page copy that must match what this site actually does, so
 * they are generated from the live settings instead of typed into each page:
 *
 *   [scos_agent_privacy]       What happens to people's details when an AI
 *                              assistant uses this site. For the /mcp/ page
 *                              (and the privacy policy, if wanted).
 *   [scos_agent_enquiry_hint]  A short note on the enquiry page telling an AI
 *                              assistant it can hand the person a pre-filled
 *                              link. Chat assistants that look the business up
 *                              land on the contact page, not on llms.txt.
 *
 * Both render nothing when the feature they describe is off, so a page can
 * carry the shortcode before the feature is switched on.
 *
 * @package    SiteEssentials
 * @subpackage Modules\Agentic
 */

namespace SiteEssentials\Modules\Agentic;

defined( 'ABSPATH' ) || exit;

class Agent_Notices {

	public static function init(): void {
		add_shortcode( 'scos_agent_privacy', [ __CLASS__, 'render_privacy' ] );
		add_shortcode( 'scos_agent_enquiry_hint', [ __CLASS__, 'render_enquiry_hint' ] );
	}

	// ── [scos_agent_privacy] ────────────────────────────────────────────────

	public static function render_privacy(): string {
		$items = [
			__( 'Reading this site through the tools (search, page content, business details) needs no login and stores nothing about you beyond the normal server logs every website keeps.', 'site-essentials' ),
		];

		if ( Enquiry_Form::is_enabled() ) {
			$items[] = __( 'If an assistant prepares an enquiry link for you, your details are only in the link. They reach us when you press send on the form, the same as typing them in yourself.', 'site-essentials' );
		}

		if ( Enquiry_Send::is_enabled() ) {
			$hours   = (int) ( Enquiry_Send::TTL / HOUR_IN_SECONDS );
			$items[] = sprintf(
				/* translators: %d: hours an unconfirmed enquiry is kept */
				__( 'If an assistant sends an enquiry for you, the details it gives us (name, email, message and the other form fields) are held on this site for up to %d hours while we email you to confirm. Nothing reaches us until you confirm. If you don\'t, the details are deleted automatically.', 'site-essentials' ),
				$hours
			);
			$items[] = __( 'To stop misuse we count how many enquiries come from each email address. The counter stores a one-way hash, not the address, and clears after a day.', 'site-essentials' );
			$items[] = __( 'Once you confirm, the enquiry is handled exactly like one sent through the contact form.', 'site-essentials' );
		}

		$items[] = __( 'Whatever you tell your AI assistant is handled by the company that runs it, under their privacy policy, not ours.', 'site-essentials' );

		$html = '<ul class="scos-agent-note scos-agent-note--privacy">';
		foreach ( $items as $item ) {
			$html .= '<li>' . esc_html( $item ) . '</li>';
		}
		$html .= '</ul>';

		$policy = get_privacy_policy_url();
		if ( $policy ) {
			$html .= '<p class="scos-agent-note">' . sprintf(
				/* translators: %s: privacy policy link */
				esc_html__( 'See our %s for how we handle enquiries.', 'site-essentials' ),
				'<a href="' . esc_url( $policy ) . '">' . esc_html__( 'privacy policy', 'site-essentials' ) . '</a>'
			) . '</p>';
		}

		return $html;
	}

	// ── [scos_agent_enquiry_hint] ───────────────────────────────────────────

	public static function render_enquiry_hint(): string {
		$format = Enquiry_Form::is_enabled() ? Enquiry_Form::link_format() : null;
		if ( ! $format ) {
			return '';
		}

		$html  = '<div class="scos-agent-note scos-agent-note--enquiry">';
		$html .= '<p><strong>' . esc_html__( 'For AI assistants:', 'site-essentials' ) . '</strong> ';
		$html .= esc_html__( 'you can give the person a link that opens this form already filled in, for them to check and send. Start with', 'site-essentials' );
		$html .= ' <code>' . esc_html( $format['base'] ) . '</code> ';
		$html .= esc_html__( 'and add', 'site-essentials' ) . ' <code>&amp;name=value</code> ';
		$html .= sprintf(
			/* translators: %s: link to the form fields JSON */
			esc_html__( 'for each field (%s). Example:', 'site-essentials' ),
			'<a href="' . esc_url( Enquiry_API::get_url() ) . '">' . esc_html__( 'field names', 'site-essentials' ) . '</a>'
		);
		$html .= ' <code>' . esc_html( $format['example'] ) . '</code></p>';

		if ( Enquiry_Send::is_enabled() || Mcp_Server::is_enabled() ) {
			$html .= '<p>';
			if ( Enquiry_Send::is_enabled() ) {
				$html .= esc_html__( 'Assistants that can send requests can also send it for them; the person confirms by email first.', 'site-essentials' ) . ' ';
			}
			$mcp = self::guide_url();
			if ( '' !== $mcp ) {
				$html .= '<a href="' . esc_url( $mcp ) . '">' . esc_html__( 'How AI assistants can use this site', 'site-essentials' ) . '</a>';
			}
			$html .= '</p>';
		}

		return $html . '</div>';
	}

	/**
	 * The /mcp/ guide page, when one is set and published.
	 */
	private static function guide_url(): string {
		$id   = absint( get_option( 'scos_agentic_webmcp_page_id', 0 ) );
		$page = $id ? get_post( $id ) : get_page_by_path( 'mcp' );
		return ( $page && 'publish' === $page->post_status ) ? (string) get_permalink( $page ) : '';
	}
}
