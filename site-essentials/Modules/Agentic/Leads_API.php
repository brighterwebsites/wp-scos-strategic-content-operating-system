<?php
/**
 * Agentic — the lead feed a CRM pulls, and the CSV download
 *
 * v1.0 | 2026-11-24
 *
 *   GET /wp-json/scos/v1/leads?since=<cursor>&limit=100&status=&channel=
 *   GET /wp-json/scos/v1/leads/<id>
 *
 * Never public: the caller needs the scos_read_leads capability. A CRM gets
 * its own user with the Lead reader role and signs in with a WordPress
 * Application Password (HTTP Basic auth over HTTPS), so each CRM can be
 * revoked on its own and there is no SCOS login code to secure.
 *
 * How a CRM uses it: keep next_cursor from each reply and pass it as since
 * on the next call (every 1-5 minutes is plenty). Leads come oldest change
 * first, and a lead returns again whenever its status changes, so match on
 * id and update your record. While has_more is true, call again at once.
 *
 * The site never calls the CRM. That is deliberate (#269).
 *
 * @package    SiteEssentials
 * @subpackage Modules\Agentic
 */

namespace SiteEssentials\Modules\Agentic;

defined( 'ABSPATH' ) || exit;

class Leads_API {

	const NAMESPACE  = 'scos/v1';
	const CSV_ACTION = 'scos_leads_csv';

	public static function init(): void {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
		add_action( 'admin_post_' . self::CSV_ACTION, [ __CLASS__, 'download_csv' ] );
	}

	public static function get_url(): string {
		return rest_url( self::NAMESPACE . '/leads' );
	}

	public static function register_routes(): void {
		register_rest_route( self::NAMESPACE, '/leads', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ __CLASS__, 'list_leads' ],
			'permission_callback' => [ __CLASS__, 'can_read' ],
			'args'                => [
				'since'   => [ 'type' => 'string', 'description' => 'next_cursor from the previous reply. Leave out to start from the oldest lead.' ],
				'limit'   => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 500, 'default' => 100 ],
				'status'  => [ 'type' => 'string', 'enum' => Lead_Store::STATUSES ],
				'channel' => [ 'type' => 'string', 'enum' => Lead_Store::CHANNELS ],
			],
		] );

		register_rest_route( self::NAMESPACE, '/leads/(?P<id>[a-f0-9]{24})', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ __CLASS__, 'get_lead' ],
			'permission_callback' => [ __CLASS__, 'can_read' ],
		] );
	}

	/** @return bool|\WP_Error */
	public static function can_read() {
		if ( current_user_can( Lead_Store::CAPABILITY ) ) {
			return true;
		}
		return new \WP_Error(
			'scos_leads_forbidden',
			__( 'Sign in as a user with the Lead reader role, using an Application Password.', 'site-essentials' ),
			[ 'status' => is_user_logged_in() ? 403 : 401 ]
		);
	}

	public static function list_leads( \WP_REST_Request $request ) {
		$result = Lead_Store::query( [
			'since'   => (string) $request->get_param( 'since' ),
			'limit'   => (int) $request->get_param( 'limit' ),
			'status'  => (string) $request->get_param( 'status' ),
			'channel' => (string) $request->get_param( 'channel' ),
		] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$response = rest_ensure_response( $result );
		$response->header( 'Cache-Control', 'no-store, private' );
		return $response;
	}

	public static function get_lead( \WP_REST_Request $request ) {
		$lead = Lead_Store::get( (string) $request['id'] );
		if ( ! $lead ) {
			return new \WP_Error( 'scos_lead_not_found', __( 'No lead with that id. It may have passed the retention limit.', 'site-essentials' ), [ 'status' => 404 ] );
		}
		$response = rest_ensure_response( [ 'version' => Lead_Store::FEED_VERSION, 'lead' => $lead ] );
		$response->header( 'Cache-Control', 'no-store, private' );
		return $response;
	}

	// ── CSV download (Site Essentials › Agentic) ─────────────────────────────

	public static function csv_url(): string {
		return wp_nonce_url( admin_url( 'admin-post.php?action=' . self::CSV_ACTION ), self::CSV_ACTION );
	}

	public static function download_csv(): void {
		check_admin_referer( self::CSV_ACTION );
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( Lead_Store::CAPABILITY ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'site-essentials' ), '', [ 'response' => 403 ] );
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="leads-' . gmdate( 'Y-m-d' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // UTF-8 BOM, so Excel reads names with accents correctly.
		Lead_Store::write_csv( $out );
		fclose( $out );
		exit;
	}
}
