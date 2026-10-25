<?php
/**
 * Agentic — enquiry handoff REST routes
 *
 * v1.0 | 2026-10-22
 * v1.1 | 2026-10-25 — GET /enquiry also returns `link`: the link format, so an
 *                      agent that can't POST builds the link itself.
 * v1.2 | 2026-10-25 — POST /enquiry/send when Enquiry_Send is on: the agent
 *                      sends, the person confirms by email (#225).
 *
 * Routes (only registered when an enquiry page is set):
 *   GET  /wp-json/scos/v1/enquiry       the form's fields, and how to use the link
 *   POST /wp-json/scos/v1/enquiry/link  build the pre-filled link from {"fields": {...}}
 *   POST /wp-json/scos/v1/enquiry/send  send it, held until the person confirms by email
 *                                       (only when Enquiry_Send is on)
 *
 * Neither route stores or sends anything. The link route is POST only so the
 * values (a person's name, email, message) travel in the body, not the URL
 * that ends up in access logs. Agents that can only GET build the link from
 * the `link` format instead: values go in the fragment, which never reaches
 * the server either. Logic lives in Enquiry_Form. (#200 D17)
 *
 * @package    SiteEssentials
 * @subpackage Modules\Agentic
 */

namespace SiteEssentials\Modules\Agentic;

defined( 'ABSPATH' ) || exit;

class Enquiry_API {

	public static function init(): void {
		if ( ! Enquiry_Form::is_enabled() ) {
			return;
		}
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	public static function register_routes(): void {
		register_rest_route( Content_API::NAMESPACE, '/enquiry', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ __CLASS__, 'handle_schema' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( Content_API::NAMESPACE, '/enquiry/link', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ __CLASS__, 'handle_link' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'fields' => [
					'required'    => true,
					'type'        => 'object',
					'description' => 'Field name => value (or an array of values for multiple-choice fields), using the names from GET /enquiry.',
				],
			],
		] );

		if ( Enquiry_Send::is_enabled() ) {
			register_rest_route( Content_API::NAMESPACE, '/enquiry/send', [
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ __CLASS__, 'handle_send' ],
				'permission_callback' => '__return_true', // Public: the person's email confirmation is the check.
				'args'                => [
					'fields' => [
						'required'    => true,
						'type'        => 'object',
						'description' => 'Field name => value (or an array of values for multiple-choice fields), using the names from GET /enquiry. Must include the person\'s email address.',
					],
					'agent'  => [
						'type'        => 'string',
						'description' => 'Optional. Your name as the assistant, e.g. "ChatGPT". Shown to the person and the business.',
					],
				],
			] );
		}
	}

	public static function get_send_url(): string {
		return rest_url( Content_API::NAMESPACE . '/enquiry/send' );
	}

	public static function handle_send( \WP_REST_Request $request ) {
		$result = Enquiry_Send::submit( (array) $request->get_param( 'fields' ), (string) $request->get_param( 'agent' ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new \WP_REST_Response( $result, 202 );
	}

	public static function get_url(): string {
		return rest_url( Content_API::NAMESPACE . '/enquiry' );
	}

	public static function get_link_url(): string {
		return rest_url( Content_API::NAMESPACE . '/enquiry/link' );
	}

	public static function handle_schema( \WP_REST_Request $request ) {
		$schema = Enquiry_Form::public_schema();
		if ( ! $schema ) {
			return new \WP_Error( 'scos_enquiry_unavailable', __( 'No enquiry form is available on this site.', 'site-essentials' ), [ 'status' => 503 ] );
		}

		$schema['link'] = Enquiry_Form::link_format();
		$schema['send'] = Enquiry_Send::is_enabled() ? self::get_send_url() : null;
		$schema['how']  = self::instructions();
		return new \WP_REST_Response( $schema, 200 );
	}

	public static function handle_link( \WP_REST_Request $request ) {
		$result = Enquiry_Form::build_link( (array) $request->get_param( 'fields' ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$result['next'] = __( 'Nothing has been sent. Give this link to the person: it opens the form filled in, and they check it and press send.', 'site-essentials' );
		return new \WP_REST_Response( $result, 200 );
	}

	/**
	 * Shared by the REST response, the agent skill and llms.txt.
	 */
	public static function instructions(): string {
		if ( Enquiry_Send::is_enabled() ) {
			return sprintf(
				/* translators: 1: send route URL, 2: link route URL */
				__( 'You can send an enquiry for the person. Draft it with them, show them what you will send, then POST {"fields": {name: value}, "agent": "your name"} to %1$s. It must include their email address: we email them a link to check it and press Send, and nothing reaches the business until they do. Tell them to look for that email. Don\'t tick newsletter or consent boxes for them. If the person would rather send it themselves, POST the same fields to %2$s for a link that opens the form filled in, or build that link from `link` in GET /enquiry if you can\'t make a POST request.', 'site-essentials' ),
				self::get_send_url(),
				self::get_link_url()
			);
		}

		return sprintf(
			/* translators: %s: link route URL */
			__( 'Agents can\'t send enquiries. Draft one with the person, then POST {"fields": {name: value}} to %s. It returns a link that opens the site\'s form filled in; the person checks it and presses send. Multiple-choice fields take a list, and values must match the options exactly. If you can\'t make a POST request, build the same link yourself from `link` in GET /enquiry: no request is needed.', 'site-essentials' ),
			self::get_link_url()
		);
	}
}
