<?php
/**
 * Agentic — remote MCP server (Streamable HTTP, no login)
 *
 * v1.0 | 2026-10-25
 *
 * WHAT THIS IS FOR
 * ----------------
 * A chat assistant can't call the REST API on its own: it only opens pages it
 * has seen, and can't POST. Added as a connector (Claude, ChatGPT developer
 * mode, Cursor, any MCP client), this endpoint gives it the site's tools
 * directly, including sending an enquiry from inside the conversation (#229).
 *
 *   POST /wp-json/scos/v1/mcp    JSON-RPC 2.0, one message or a batch
 *   GET                          405 (no server-initiated stream)
 *
 * Stateless: no session ID, no stream. Every response is plain JSON, which
 * the Streamable HTTP transport allows. Methods: initialize, ping,
 * tools/list, tools/call, resources/list, prompts/list; notifications get 202.
 *
 * TOOLS
 * -----
 * The read tools are WebMCP_Tools' definitions, so their names, wording and
 * schemas match the browser tools; each runs its public REST route
 * internally. With the enquiry handoff on, three more: get_enquiry_form,
 * prepare_enquiry_link and, when Enquiry_Send is on, send_enquiry. That one
 * still needs the person to confirm by email (D19), so a connector can't send
 * in anyone's name.
 *
 * Nothing here exposes more than the public REST API already does.
 *
 * Settings:
 *   scos_agentic_mcp_enabled  on/off (off by default)
 *
 * @package    SiteEssentials
 * @subpackage Modules\Agentic
 */

namespace SiteEssentials\Modules\Agentic;

defined( 'ABSPATH' ) || exit;

class Mcp_Server {

	const OPTION_ENABLED = 'scos_agentic_mcp_enabled';
	const ROUTE          = '/mcp';
	const VERSIONS       = [ '2025-11-25', '2025-06-18', '2025-03-26', '2024-11-05' ];

	/** JSON-RPC error codes. */
	const PARSE_ERROR      = -32700;
	const INVALID_REQUEST  = -32600;
	const METHOD_NOT_FOUND = -32601;
	const INVALID_PARAMS   = -32602;

	// ── Boot ─────────────────────────────────────────────────────────────────

	public static function init(): void {
		if ( ! self::is_enabled() ) {
			return;
		}
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
		add_filter( 'rest_pre_serve_request', [ __CLASS__, 'serve_empty' ], 10, 4 );
	}

	public static function is_enabled(): bool {
		return (bool) get_option( self::OPTION_ENABLED, false );
	}

	public static function get_url(): string {
		return rest_url( Content_API::NAMESPACE . self::ROUTE );
	}

	public static function register_routes(): void {
		register_rest_route( Content_API::NAMESPACE, self::ROUTE, [
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ __CLASS__, 'handle_post' ],
				'permission_callback' => '__return_true', // Public, like the REST routes it wraps.
			],
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'handle_get' ],
				'permission_callback' => '__return_true',
			],
		] );
	}

	/** No server-initiated stream: GET is not allowed (Streamable HTTP). */
	public static function handle_get(): \WP_REST_Response {
		$response = new \WP_REST_Response( [ 'error' => 'Use POST with JSON-RPC. This server does not open an event stream.' ], 405 );
		$response->header( 'Allow', 'POST' );
		return $response;
	}

	// ── Transport ────────────────────────────────────────────────────────────

	public static function handle_post( \WP_REST_Request $request ): \WP_REST_Response {
		// Browsers on other sites can't drive this server (DNS rebinding guard).
		$origin = (string) $request->get_header( 'origin' );
		if ( '' !== $origin && wp_parse_url( $origin, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
			return self::json( self::error( null, self::INVALID_REQUEST, 'Origin not allowed.' ), 403 );
		}

		$body = json_decode( (string) $request->get_body(), true );
		if ( null === $body ) {
			return self::json( self::error( null, self::PARSE_ERROR, 'Parse error: the body must be JSON-RPC 2.0.' ), 400 );
		}

		$batch    = is_array( $body ) && array_is_list( $body );
		$messages = $batch ? $body : [ $body ];
		$replies  = [];

		foreach ( $messages as $message ) {
			$reply = self::dispatch( $message );
			if ( null !== $reply ) {
				$replies[] = $reply;
			}
		}

		if ( ! $replies ) {
			return self::empty_response(); // Notifications and responses only.
		}

		return self::json( $batch ? $replies : $replies[0] );
	}

	/**
	 * Answer one JSON-RPC message, or null for a notification.
	 *
	 * @param mixed $message Decoded message.
	 * @return array|null
	 */
	private static function dispatch( $message ): ?array {
		if ( ! is_array( $message ) || ( $message['jsonrpc'] ?? '' ) !== '2.0' || ! isset( $message['method'] ) ) {
			// A client's response to us (we never send requests) or junk.
			return isset( $message['id'] ) && ! isset( $message['result'] ) && ! isset( $message['error'] )
				? self::error( $message['id'], self::INVALID_REQUEST, 'Invalid JSON-RPC request.' )
				: null;
		}

		$id     = $message['id'] ?? null;
		$params = is_array( $message['params'] ?? null ) ? $message['params'] : [];

		if ( ! array_key_exists( 'id', $message ) ) {
			return null; // Notification, e.g. notifications/initialized.
		}

		switch ( (string) $message['method'] ) {
			case 'initialize':
				return self::result( $id, self::initialize( $params ) );
			case 'ping':
				return self::result( $id, new \stdClass() );
			case 'tools/list':
				return self::result( $id, [ 'tools' => self::tool_list() ] );
			case 'tools/call':
				return self::call_tool( $id, $params );
			case 'resources/list':
				return self::result( $id, [ 'resources' => [] ] );
			case 'resources/templates/list':
				return self::result( $id, [ 'resourceTemplates' => [] ] );
			case 'prompts/list':
				return self::result( $id, [ 'prompts' => [] ] );
			default:
				return self::error( $id, self::METHOD_NOT_FOUND, 'Method not found: ' . sanitize_text_field( (string) $message['method'] ) );
		}
	}

	// ── Methods ──────────────────────────────────────────────────────────────

	private static function initialize( array $params ): array {
		$asked   = (string) ( $params['protocolVersion'] ?? '' );
		$version = in_array( $asked, self::VERSIONS, true ) ? $asked : self::VERSIONS[0];
		$site    = self::site_name();

		return [
			'protocolVersion' => $version,
			'capabilities'    => [ 'tools' => [ 'listChanged' => false ] ],
			'serverInfo'      => [
				'name'    => sanitize_title( $site ) . '-website',
				'title'   => $site,
				'version' => Agentic_Module::get_version(),
			],
			'instructions'    => self::instructions(),
		];
	}

	private static function instructions(): string {
		$site  = self::site_name();
		$lines = [
			sprintf( 'Tools for the %s website. Call get_site_info first to learn who the business is and what it offers. All tools return published content only.', $site ),
		];
		if ( Enquiry_Send::is_enabled() ) {
			$lines[] = 'To contact the business for the person you are helping: read the form with get_enquiry_form, draft the enquiry with them, show them exactly what you will send, and only after they agree call send_enquiry. Their email address is required. Nothing reaches the business until they confirm from the email we send them, so tell them to check their inbox. Never tick newsletter or consent boxes for them.';
		} elseif ( Enquiry_Form::is_enabled() ) {
			$lines[] = 'To contact the business: read the form with get_enquiry_form, draft the enquiry with the person, then call prepare_enquiry_link and give them the link. It opens the business\'s form filled in, and they press Send.';
		}
		return implode( ' ', $lines );
	}

	/**
	 * Every tool this server offers, as MCP tool definitions.
	 *
	 * @return array[]
	 */
	public static function tool_list(): array {
		$tools = [];
		foreach ( self::tools() as $tool ) {
			$tools[] = array_filter( [
				'name'        => $tool['name'],
				'title'       => $tool['title'] ?? null,
				'description' => $tool['description'],
				'inputSchema' => $tool['inputSchema'],
				'annotations' => $tool['annotations'] ?? null,
			], static function ( $value ) {
				return null !== $value;
			} );
		}
		return $tools;
	}

	/**
	 * Tool definitions with how to run each.
	 *
	 * @return array[] name, description, inputSchema, annotations, run (callable taking args).
	 */
	private static function tools(): array {
		$tools = [];

		foreach ( WebMCP_Tools::definitions() as $def ) {
			$def['annotations'] = array_merge( [ 'openWorldHint' => false ], (array) ( $def['annotations'] ?? [] ) );
			$def['run']         = static function ( array $args ) use ( $def ) {
				$path = $def['path'];
				foreach ( $args as $key => $value ) {
					if ( is_scalar( $value ) ) {
						$path = str_replace( '{' . $key . '}', rawurlencode( (string) $value ), $path );
					}
				}
				if ( preg_match( '/\{[a-z_]+\}/', $path ) ) {
					return new \WP_Error( 'missing_argument', 'A required argument is missing.' );
				}
				$query = array_intersect_key( $args, array_flip( (array) $def['query'] ) );
				return self::rest( 'GET', '/' . Content_API::NAMESPACE . $path, $query );
			};
			$tools[] = $def;
		}

		if ( ! Enquiry_Form::is_enabled() ) {
			return $tools;
		}

		$site   = self::site_name();
		$fields = [
			'type'                 => 'object',
			'description'          => 'Field name => value, using the names from get_enquiry_form. A list of values for multiple-choice fields, matching the listed options.',
			'additionalProperties' => [
				'oneOf' => [
					[ 'type' => 'string' ],
					[ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
				],
			],
		];

		$tools[] = [
			'name'        => 'get_enquiry_form',
			'title'       => 'Read the enquiry form',
			'description' => sprintf( 'The fields of the %s enquiry form: name, type, whether required, and the options for multiple-choice fields. Read this before drafting an enquiry.', $site ),
			'inputSchema' => [ 'type' => 'object', 'properties' => new \stdClass() ],
			'annotations' => [ 'readOnlyHint' => true, 'openWorldHint' => false ],
			'run'         => static function () {
				return self::rest( 'GET', '/' . Content_API::NAMESPACE . '/enquiry' );
			},
		];

		$tools[] = [
			'name'        => 'prepare_enquiry_link',
			'title'       => 'Prepare a pre-filled enquiry link',
			'description' => sprintf( 'Returns a link that opens the %s form already filled in with these values. Nothing is sent: give the link to the person, who checks the form and presses Send.', $site ),
			'inputSchema' => [ 'type' => 'object', 'properties' => [ 'fields' => $fields ], 'required' => [ 'fields' ] ],
			'annotations' => [ 'readOnlyHint' => true, 'openWorldHint' => false ],
			'run'         => static function ( array $args ) {
				return Enquiry_Form::build_link( (array) ( $args['fields'] ?? [] ) );
			},
		];

		if ( Enquiry_Send::is_enabled() ) {
			$tools[] = [
				'name'        => 'send_enquiry',
				'title'       => sprintf( 'Send an enquiry to %s', $site ),
				'description' => sprintf( 'Send an enquiry to %s for the person you are helping. Only call this after they have asked you to make contact and agreed to the exact details. Their email address is required. We email them a link to check and send it, and nothing reaches the business until they do, so tell them to look for that email. Never tick newsletter or consent boxes for them.', $site ),
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'fields' => $fields,
						'agent'  => [ 'type' => 'string', 'description' => 'Your name as the assistant, e.g. "Claude". Shown to the person and the business.' ],
					],
					'required'   => [ 'fields' ],
				],
				'annotations' => [ 'readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => false, 'openWorldHint' => true ],
				'run'         => static function ( array $args ) {
					$agent = trim( (string) ( $args['agent'] ?? '' ) );
					return Enquiry_Send::submit( (array) ( $args['fields'] ?? [] ), '' !== $agent ? $agent : 'An AI assistant (MCP connector)', Enquiry_Send::CHANNEL_MCP );
				},
			];
		}

		return $tools;
	}

	private static function call_tool( $id, array $params ): array {
		$name = (string) ( $params['name'] ?? '' );
		$args = is_array( $params['arguments'] ?? null ) ? $params['arguments'] : [];

		foreach ( self::tools() as $tool ) {
			if ( $tool['name'] !== $name ) {
				continue;
			}

			$result = call_user_func( $tool['run'], $args );

			if ( is_wp_error( $result ) ) {
				$data = $result->get_error_data();
				return self::result( $id, [
					'content' => [ [ 'type' => 'text', 'text' => wp_json_encode( array_filter( [
						'error'            => $result->get_error_code(),
						'message'          => $result->get_error_message(),
						'missing_required' => is_array( $data ) ? ( $data['missing_required'] ?? null ) : null,
						'ignored'          => is_array( $data ) ? ( $data['ignored'] ?? null ) : null,
					] ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ] ],
					'isError' => true,
				] );
			}

			return self::result( $id, [
				'content' => [ [ 'type' => 'text', 'text' => wp_json_encode( $result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ] ],
				'isError' => false,
			] );
		}

		return self::error( $id, self::INVALID_PARAMS, 'Unknown tool: ' . sanitize_text_field( $name ) );
	}

	// ── Helpers ──────────────────────────────────────────────────────────────

	/**
	 * Run a public REST route internally and return its data, or a WP_Error.
	 *
	 * @return mixed|\WP_Error
	 */
	private static function rest( string $method, string $route, array $params = [] ) {
		$request = new \WP_REST_Request( $method, $route );
		foreach ( $params as $key => $value ) {
			if ( is_scalar( $value ) && '' !== (string) $value ) {
				$request->set_param( $key, (string) $value );
			}
		}

		$response = rest_do_request( $request );
		$data     = rest_get_server()->response_to_data( $response, false );

		if ( $response->is_error() || $response->get_status() >= 400 ) {
			return new \WP_Error(
				is_array( $data ) && isset( $data['code'] ) ? (string) $data['code'] : 'request_failed',
				is_array( $data ) ? (string) ( $data['message'] ?? $data['error'] ?? 'The request failed.' ) : 'The request failed.'
			);
		}
		return $data;
	}

	private static function result( $id, $result ): array {
		return [ 'jsonrpc' => '2.0', 'id' => $id, 'result' => $result ];
	}

	private static function error( $id, int $code, string $message ): array {
		return [ 'jsonrpc' => '2.0', 'id' => $id, 'error' => [ 'code' => $code, 'message' => $message ] ];
	}

	private static function json( $data, int $status = 200 ): \WP_REST_Response {
		$response = new \WP_REST_Response( $data, $status );
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}

	/** 202 with no body, for a POST carrying only notifications. */
	private static function empty_response(): \WP_REST_Response {
		$response = new \WP_REST_Response( null, 202 );
		$response->header( 'X-SCOS-MCP-Empty', '1' );
		return $response;
	}

	/**
	 * rest_pre_serve_request: send nothing for an empty 202 (WP would print "null").
	 *
	 * @param bool              $served  Whether the request was served.
	 * @param \WP_HTTP_Response $result  The response.
	 * @return bool
	 */
	public static function serve_empty( $served, $result, $request = null, $server = null ) {
		if ( $result instanceof \WP_HTTP_Response && '1' === ( $result->get_headers()['X-SCOS-MCP-Empty'] ?? '' ) ) {
			return true;
		}
		return $served;
	}

	private static function site_name(): string {
		return wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	}
}
