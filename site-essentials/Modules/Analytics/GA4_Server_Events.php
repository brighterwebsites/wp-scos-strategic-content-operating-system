<?php
/**
 * GA4 server events: generate_lead and sign_up
 *
 * generate_lead is sent from here, not from the browser (#319). A lead is a
 * row in the lead table (Agentic\Lead_Store), and every row announces itself
 * with the scos_lead_recorded action:
 *
 *   form / link  A Breakdance form with "SCOS: Save lead" ticked, after
 *                Breakdance's honeypot, CSRF, reCAPTCHA and field checks pass.
 *                The request is the visitor's, so the event joins their GA4
 *                session and keeps its source and campaign.
 *   agent        An enquiry an agent sent through MCP or the REST API. There
 *                is no browser, so the event gets a one-off client ID and a
 *                campaign_details event (source = the agent, medium = agent,
 *                campaign = scos-agent-enquiry) so GA4 can report agent leads.
 *
 * So add to cart, search, login, coupon and other forms never count, and a
 * submit that fails validation never counts.
 *
 * sign_up is sent when a customer or subscriber account is created on the
 * front end (WooCommerce checkout, My Account, or WordPress registration).
 * It is not a lead. Orders are not sent from here: purchase comes from the
 * WooCommerce GA plugin through Woo_GA_Bridge.
 *
 * Nothing is sent until the Measurement Protocol API secret is set
 * (GA4_Measurement_Protocol::is_configured()). Until then the browser script
 * fires generate_lead itself on Breakdance form success (serverLeads false).
 *
 * Filters:
 *   scos_ga4_lead_event( array $params, string $ref, array $lead )  return [] to skip
 *   scos_ga4_sign_up_event( array $params, int $user_id )            return [] to skip
 *
 * v1.0 | 2026-11-28 (#319)
 *
 * @package    SiteEssentials
 * @subpackage Modules\Analytics
 */

namespace SiteEssentials\Modules\Analytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GA4_Server_Events {

	const AGENT_CAMPAIGN = 'scos-agent-enquiry';

	/** Roles whose new accounts count as sign_up. */
	const SIGN_UP_ROLES = [ 'customer', 'subscriber' ];

	public static function init(): void {
		add_action( 'scos_lead_recorded', [ __CLASS__, 'on_lead_recorded' ], 10, 2 );
		add_action( 'user_register', [ __CLASS__, 'on_user_register' ], 10, 1 );
	}

	/** True when the server sends generate_lead (the browser then doesn't). */
	public static function sends_leads(): bool {
		return GA4_Measurement_Protocol::is_configured();
	}

	/**
	 * @param string $ref  The lead's ref.
	 * @param array  $lead What was passed to Lead_Store::insert().
	 */
	public static function on_lead_recorded( $ref, $lead ): void {
		if ( ! self::sends_leads() || ! is_array( $lead ) ) {
			return;
		}
		$channel = (string) ( $lead['channel'] ?? '' );
		$agent   = 'agent' === $channel;

		// Editors aren't tracked in the browser either; a test while logged in shouldn't count.
		if ( ! $agent && current_user_can( 'edit_posts' ) ) {
			return;
		}

		$post_id = (int) ( $lead['post_id'] ?? 0 );
		$params  = [
			'lead_channel' => $channel,
			'lead_source'  => (string) ( $lead['source'] ?? '' ),
			'lead_status'  => (string) ( $lead['status'] ?? '' ),
			'form_id'      => (string) (int) ( $lead['form_id'] ?? 0 ),
			'form_post_id' => (string) $post_id,
		];
		if ( $agent && '' !== (string) ( $lead['agent'] ?? '' ) ) {
			$params['agent'] = mb_substr( sanitize_text_field( (string) $lead['agent'] ), 0, 60 );
		}
		if ( $post_id && ( $url = get_permalink( $post_id ) ) ) {
			$params['page_location'] = $url;
			$params['page_title']    = get_the_title( $post_id );
		}

		$params = apply_filters( 'scos_ga4_lead_event', $params, (string) $ref, $lead );
		if ( ! is_array( $params ) || ! $params ) {
			return;
		}

		$events = [];
		if ( $agent ) {
			$events[] = [
				'name'   => 'campaign_details',
				'params' => [
					'source'   => sanitize_key( (string) ( $params['agent'] ?? 'ai_agent' ) ) ?: 'ai_agent',
					'medium'   => 'agent',
					'campaign' => self::AGENT_CAMPAIGN,
				],
			];
		}
		$events[] = [ 'name' => 'generate_lead', 'params' => $params ];

		$client = $agent ? GA4_Measurement_Protocol::one_off_client_id() : '';
		GA4_Measurement_Protocol::send( $events, $client, ! $agent );
	}

	/**
	 * A front-end account for a customer or subscriber. Accounts made in
	 * wp-admin or WP-CLI aren't sign-ups.
	 */
	public static function on_user_register( $user_id ): void {
		if ( ! GA4_Measurement_Protocol::is_configured() ) {
			return;
		}
		if ( ( defined( 'WP_CLI' ) && WP_CLI ) || ( is_admin() && ! wp_doing_ajax() ) || current_user_can( 'create_users' ) ) {
			return;
		}
		$user = get_userdata( (int) $user_id );
		if ( ! $user || ! array_intersect( self::SIGN_UP_ROLES, (array) $user->roles ) ) {
			return;
		}

		$params = apply_filters( 'scos_ga4_sign_up_event', [ 'method' => self::sign_up_method() ], (int) $user_id );
		if ( ! is_array( $params ) || ! $params ) {
			return;
		}
		GA4_Measurement_Protocol::send( [ [ 'name' => 'sign_up', 'params' => $params ] ] );
	}

	private static function sign_up_method(): string {
		if ( defined( 'WOOCOMMERCE_CHECKOUT' ) || ( function_exists( 'is_checkout' ) && is_checkout() ) ) {
			return 'woocommerce_checkout';
		}
		if ( isset( $_POST['woocommerce-register-nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- read only, WooCommerce verified it.
			return 'woocommerce_account';
		}
		return 'wordpress';
	}
}
