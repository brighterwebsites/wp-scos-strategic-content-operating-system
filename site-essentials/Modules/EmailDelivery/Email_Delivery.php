<?php
/**
 * CyberPanel Email Delivery transport via REST API (pre_wp_mail).
 *
 * @package    SiteEssentials
 * @subpackage Modules\EmailDelivery
 * @since      1.0.0
 */

namespace SiteEssentials\Modules\EmailDelivery;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Routes wp_mail through platform.cyberpersons.com when enabled.
 */
class Email_Delivery {

	private const API_URL = 'https://platform.cyberpersons.com/email/v1/send';

	/**
	 * Register hooks when enabled and configured.
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( ! $this->is_configured_for_send() ) {
			return;
		}

		add_filter( 'pre_wp_mail', [ $this, 'intercept_mail' ], 10, 2 );
	}

	/**
	 * Whether transactional email is enabled and API key present (constant or option).
	 *
	 * @return bool
	 */
	public function is_configured_for_send(): bool {
		if ( ! get_option( 'se_email_enabled', false ) ) {
			return false;
		}
		$key = self::get_api_key();
		return $key !== '';
	}

	/**
	 * Resolve API key: constant wins over option.
	 *
	 * @return string
	 */
	public static function get_api_key(): string {
		if ( defined( 'SE_EMAIL_API_KEY' ) && is_string( SE_EMAIL_API_KEY ) && SE_EMAIL_API_KEY !== '' ) {
			return SE_EMAIL_API_KEY;
		}
		$opt = get_option( 'se_email_api_key', '' );
		return is_string( $opt ) ? $opt : '';
	}

	/**
	 * Generic config: constants override options.
	 *
	 * @param string $constant_name e.g. SE_EMAIL_FROM_ADDRESS.
	 * @param string $option_name   e.g. se_email_from_address.
	 * @return string
	 */
	public static function get_config( string $constant_name, string $option_name ): string {
		if ( defined( $constant_name ) ) {
			$v = constant( $constant_name );
			if ( is_string( $v ) && $v !== '' ) {
				return $v;
			}
		}
		$opt = get_option( $option_name, '' );
		return is_string( $opt ) ? $opt : '';
	}

	/**
	 * Short-circuit wp_mail when our API handles delivery.
	 *
	 * @param mixed $short_circuit Prior filter return.
	 * @param array $atts          wp_mail arguments.
	 * @return mixed bool|null — non-null short-circuits wp_mail.
	 */
	public function intercept_mail( $short_circuit, $atts ) {
		if ( null !== $short_circuit ) {
			return $short_circuit;
		}

		if ( ! $this->is_configured_for_send() ) {
			return $short_circuit;
		}

		if ( ! is_array( $atts ) ) {
			return false;
		}

		$result = $this->send_mail_payload( $atts, false );

		if ( is_wp_error( $result ) ) {
			return false;
		}

		return true;
	}

	/**
	 * AJAX / test helper: send using same pipeline as live mail.
	 *
	 * @param string $to Test recipient.
	 * @return array{success:bool, message?:string, message_id?:string}
	 */
	public static function send_test_email( string $to ): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			return [ 'success' => false, 'message' => __( 'Insufficient permissions.', 'site-essentials' ) ];
		}

		$to = sanitize_email( $to );
		if ( ! is_email( $to ) ) {
			return [ 'success' => false, 'message' => __( 'Invalid email address.', 'site-essentials' ) ];
		}

		$instance = new self();
		if ( ! $instance->is_configured_for_send() ) {
			return [ 'success' => false, 'message' => __( 'Transactional email is disabled or API key is missing.', 'site-essentials' ) ];
		}

		$atts = [
			'to'          => $to,
			'subject'     => __( '[Site Essentials] Test email', 'site-essentials' ),
			'message'     => '<p>' . esc_html__( 'This is a test email from Site Essentials transactional email.', 'site-essentials' ) . '</p>',
			'headers'     => '',
			'attachments' => [],
		];

		$result = $instance->send_mail_payload( $atts, true );

		if ( is_wp_error( $result ) ) {
			return [ 'success' => false, 'message' => $result->get_error_message() ];
		}

		return [
			'success'    => true,
			'message'    => __( 'Test email sent.', 'site-essentials' ),
			'message_id' => $result['message_id'] ?? '',
		];
	}

	/**
	 * Core send: build JSON, POST, log on success.
	 *
	 * @param array $atts     wp_mail-style args.
	 * @param bool  $is_test  If true, use slightly different log subject prefix.
	 * @return array<string, string>|WP_Error On success returns parsed ids.
	 */
	private function send_mail_payload( array $atts, bool $is_test ) {
		$api_key = self::get_api_key();
		if ( $api_key === '' ) {
			return new \WP_Error( 'se_email_no_key', __( 'API key is not configured.', 'site-essentials' ) );
		}

		$from_address = self::get_config( 'SE_EMAIL_FROM_ADDRESS', 'se_email_from_address' );
		if ( $from_address === '' || ! is_email( $from_address ) ) {
			return new \WP_Error( 'se_email_no_from', __( 'From email address is invalid or empty.', 'site-essentials' ) );
		}

		// CyberPanel Email API `from` must be a bare address (see platform.cyberpersons.com JSON examples).

		$parsed       = self::parse_headers( isset( $atts['headers'] ) ? $atts['headers'] : '' );
		$reply_option = self::get_config( 'SE_EMAIL_REPLY_TO', 'se_email_reply_to' );
		$reply_to     = $parsed['reply_to'] !== '' ? $parsed['reply_to'] : $reply_option;

		$to_addresses = self::normalize_email_list( isset( $atts['to'] ) ? $atts['to'] : '' );
		if ( empty( $to_addresses ) ) {
			return new \WP_Error( 'se_email_no_to', __( 'No recipients.', 'site-essentials' ) );
		}

		$subject = isset( $atts['subject'] ) ? $atts['subject'] : '';
		$subject = is_string( $subject ) ? $subject : '';

		$message = isset( $atts['message'] ) ? $atts['message'] : '';
		$message = is_string( $message ) ? $message : '';

		$html = self::build_html_body( $message, $parsed['content_type'] );

		$attachments = self::build_attachments_payload( isset( $atts['attachments'] ) ? $atts['attachments'] : [] );

		// One API request: comma-separated To (RFC-style), plus Cc/Bcc when present.
		$combined_to = implode( ', ', $to_addresses );

		$body = [
			'from'    => $from_address,
			'to'      => $combined_to,
			'subject' => $subject,
			'html'    => $html,
		];

		if ( $reply_to !== '' && is_email( $reply_to ) ) {
			$body['reply_to'] = $reply_to;
		}

		if ( ! empty( $parsed['cc'] ) ) {
			$body['cc'] = implode( ', ', $parsed['cc'] );
		}
		if ( ! empty( $parsed['bcc'] ) ) {
			$body['bcc'] = implode( ', ', $parsed['bcc'] );
		}

		if ( ! empty( $attachments ) ) {
			$body['attachments'] = $attachments;
		}

		$response = $this->post_api( $api_key, $body );

		if ( is_wp_error( $response ) && ! empty( $attachments ) ) {
			unset( $body['attachments'] );
			$response = $this->post_api( $api_key, $body );
			if ( ! is_wp_error( $response ) ) {
				error_log( '[Site Essentials Email] Attachments omitted after API rejected payload.' );
			}
		}

		if ( is_wp_error( $response ) ) {
			Email_Logger::log(
				$combined_to,
				$is_test ? '[test] ' . $subject : $subject,
				'failed',
				'',
				$response->get_error_message()
			);
			return $response;
		}

		Email_Logger::log(
			$combined_to,
			$is_test ? '[test] ' . $subject : $subject,
			'sent',
			$response['message_id'],
			''
		);

		return [ 'message_id' => $response['message_id'] ];
	}

	/**
	 * POST JSON to CyberPanel Email API.
	 *
	 * @param string               $api_key Bearer token.
	 * @param array<string, mixed> $body    Request JSON.
	 * @return array{message_id:string}|WP_Error
	 */
	private function post_api( string $api_key, array $body ) {
		$args = [
			'timeout' => 30,
			'headers' => [
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			],
			'body'    => wp_json_encode( $body ),
		];

		$response = wp_remote_post( self::API_URL, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		if ( $code < 200 || $code >= 300 ) {
			$msg = is_array( $data ) ? self::extract_api_error_message( $data ) : '';
			if ( $msg === '' && is_string( $raw ) ) {
				$msg = mb_substr( $raw, 0, 500 );
			}
			return new \WP_Error(
				'se_email_http',
				$msg !== '' ? $msg : __( 'Email API request failed.', 'site-essentials' )
			);
		}

		if ( ! is_array( $data ) ) {
			return new \WP_Error( 'se_email_api', __( 'Invalid JSON from email API.', 'site-essentials' ) );
		}

		// Some responses use HTTP 200 with {"success":false,"error":{"message":"..."}}.
		if ( isset( $data['success'] ) && false === $data['success'] ) {
			$msg = self::extract_api_error_message( $data );
			return new \WP_Error(
				'se_email_api',
				$msg !== '' ? $msg : __( 'Email API rejected the request.', 'site-essentials' )
			);
		}

		// Success: {"success":true,"data":{"message_id":"..."}}
		$payload = isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : $data;

		$message_id = '';
		foreach ( [ 'message_id', 'messageId', 'id' ] as $key ) {
			if ( isset( $payload[ $key ] ) && is_string( $payload[ $key ] ) ) {
				$message_id = $payload[ $key ];
				break;
			}
			if ( isset( $payload[ $key ] ) && is_numeric( $payload[ $key ] ) ) {
				$message_id = (string) $payload[ $key ];
				break;
			}
		}

		return [ 'message_id' => $message_id ];
	}

	/**
	 * Pull human-readable message from CyberPanel-style error payloads.
	 *
	 * @param array<string, mixed> $data Decoded JSON body.
	 * @return string
	 */
	private static function extract_api_error_message( array $data ): string {
		if ( isset( $data['error'] ) && is_array( $data['error'] ) && isset( $data['error']['message'] ) && is_string( $data['error']['message'] ) ) {
			return $data['error']['message'];
		}
		if ( isset( $data['message'] ) && is_string( $data['message'] ) ) {
			return $data['message'];
		}
		return '';
	}

	/**
	 * @param string $message Raw body.
	 * @param string $content_type From headers: text/plain or text/html.
	 * @return string HTML string for API `html` field.
	 */
	private static function build_html_body( string $message, string $content_type ): string {
		$content_type = strtolower( $content_type );
		if ( strpos( $content_type, 'text/html' ) !== false || preg_match( '/<[a-z][\s\S]*>/i', $message ) ) {
			return $message;
		}

		return '<pre style="white-space:pre-wrap;font-family:inherit;">' . esc_html( $message ) . '</pre>';
	}

	/**
	 * Parse mail headers string or array into reply-to, cc, bcc, content-type.
	 *
	 * @param string|string[] $headers wp_mail headers.
	 * @return array{reply_to:string,cc:string[],bcc:string[],content_type:string}
	 */
	private static function parse_headers( $headers ): array {
		$out = [
			'reply_to'      => '',
			'cc'            => [],
			'bcc'           => [],
			'content_type'  => '',
		];

		if ( empty( $headers ) ) {
			return $out;
		}

		$lines = is_array( $headers ) ? $headers : explode( "\n", str_replace( "\r\n", "\n", (string) $headers ) );

		foreach ( $lines as $line ) {
			$line = trim( (string) $line );
			if ( $line === '' ) {
				continue;
			}

			if ( stripos( $line, 'reply-to:' ) === 0 ) {
				$addr                    = trim( substr( $line, strlen( 'reply-to:' ) ) );
				$out['reply_to']         = self::extract_email_from_header_value( $addr );
				continue;
			}
			if ( stripos( $line, 'cc:' ) === 0 ) {
				$out['cc'] = array_merge( $out['cc'], self::parse_address_list( trim( substr( $line, 3 ) ) ) );
				continue;
			}
			if ( stripos( $line, 'bcc:' ) === 0 ) {
				$out['bcc'] = array_merge( $out['bcc'], self::parse_address_list( trim( substr( $line, 4 ) ) ) );
				continue;
			}
			if ( stripos( $line, 'content-type:' ) === 0 ) {
				$out['content_type'] = trim( substr( $line, strlen( 'content-type:' ) ) );
			}
		}

		return $out;
	}

	/**
	 * @param string $value Header value (possibly Name <email>).
	 * @return string
	 */
	private static function extract_email_from_header_value( string $value ): string {
		if ( preg_match( '/<([^>]+)>/', $value, $m ) ) {
			return sanitize_email( $m[1] );
		}
		return sanitize_email( $value );
	}

	/**
	 * @param string $list Comma-separated addresses.
	 * @return string[]
	 */
	private static function parse_address_list( string $list ): array {
		$parts = preg_split( '/[\s,]+/', $list );
		if ( ! is_array( $parts ) ) {
			return [];
		}
		$emails = [];
		foreach ( $parts as $p ) {
			$p = trim( (string) $p );
			if ( $p === '' ) {
				continue;
			}
			$em = self::extract_email_from_header_value( $p );
			if ( $em !== '' && is_email( $em ) ) {
				$emails[] = $em;
			}
		}
		return array_unique( $emails );
	}

	/**
	 * Flatten wp_mail $to to unique valid emails.
	 *
	 * @param mixed $to String, comma list, or array.
	 * @return string[]
	 */
	private static function normalize_email_list( $to ): array {
		if ( is_array( $to ) ) {
			$flat = implode( ',', array_map( 'strval', $to ) );
		} else {
			$flat = (string) $to;
		}

		return self::parse_address_list( $flat );
	}

	/**
	 * @param array<int|string, mixed> $attachments Paths from wp_mail.
	 * @return array<int, array{filename:string,content:string}>
	 */
	private static function build_attachments_payload( $attachments ): array {
		$out = [];
		foreach ( (array) $attachments as $path ) {
			$path = (string) $path;
			if ( $path === '' || ! is_readable( $path ) ) {
				continue;
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- binary attachment read.
			$bytes = file_get_contents( $path );
			if ( false === $bytes ) {
				continue;
			}
			$out[] = [
				'filename' => basename( $path ),
				'content'  => base64_encode( $bytes ),
			];
		}
		return $out;
	}

}
