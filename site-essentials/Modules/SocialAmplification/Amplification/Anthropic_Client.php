<?php
/**
 * Anthropic API Client
 *
 * Reads knowledge files from wp-content/ai-knowledge/, builds a structured
 * prompt, calls the Anthropic Messages API, and parses the JSON caption response.
 *
 * Knowledge files (all optional — missing files are silently skipped):
 *   brand-core.md   — brand identity, tone, positioning
 *   vocabulary.md   — approved / banned word list
 *   social-media.md — platform-specific rules and formatting
 *
 * Automatically writes wp-content/ai-knowledge/.htaccess if the folder
 * exists but the file does not, so HTTP access is blocked from day one.
 *
 * @package    SiteEssentials
 * @subpackage Modules\SocialAmplification\Amplification
 */

namespace SiteEssentials\Modules\SocialAmplification\Amplification;

defined( 'ABSPATH' ) || exit;

class Anthropic_Client {

	const API_URL         = 'https://api.anthropic.com/v1/messages';
	const API_VERSION     = '2023-06-01';
	const DEFAULT_MODEL   = 'claude-haiku-4-5-20251001';
	const MAX_TOKENS      = 1500;
	const LOG_PREFIX      = '[SCOS SMA Anthropic]';

	/** Knowledge files relative to WP_CONTENT_DIR/ai-knowledge/ */
	const KNOWLEDGE_FILES = [
		'brand_core'   => 'brand-core.md',
		'vocabulary'   => 'vocabulary.md',
		'social_media' => 'social-media.md',
	];

	/**
	 * Generate three social media captions for the given post context.
	 *
	 * @param  array  $post_context {
	 *     @type int    $post_id
	 *     @type string $title
	 *     @type string $excerpt
	 *     @type string $permalink
	 *     @type string $shortlink
	 *     @type string $content_type
	 * }
	 * @return array{post_1: string, post_2: string, post_3: string}
	 * @throws \RuntimeException on API error or bad response.
	 */
	public static function generate_captions( array $post_context ): array {
		$api_key = get_option( 'bw_anthropic_api_key', '' );
		if ( ! $api_key ) {
			$msg = 'Anthropic API key is not configured.';
			error_log( self::LOG_PREFIX . ' ' . $msg );
			throw new \RuntimeException( $msg );
		}

		self::maybe_create_htaccess();

		$model     = (string) get_option( 'bw_anthropic_model', self::DEFAULT_MODEL ) ?: self::DEFAULT_MODEL;
		$knowledge = self::read_knowledge_files();
		$system    = self::system_prompt( $knowledge );
		$prompt    = self::build_prompt( $post_context );

		$post_id = $post_context['post_id'] ?? '?';
		error_log( self::LOG_PREFIX . " Generating captions for post #{$post_id} using model {$model}" );

		$payload = [
			'model'      => $model,
			'max_tokens' => self::MAX_TOKENS,
			'system'     => $system,
			'messages'   => [
				[ 'role' => 'user', 'content' => $prompt ],
			],
		];

		$response = wp_remote_post( self::API_URL, [
			'timeout' => 60,
			'headers' => [
				'x-api-key'         => $api_key,
				'anthropic-version' => self::API_VERSION,
				'content-type'      => 'application/json',
			],
			'body' => wp_json_encode( $payload ),
		] );

		if ( is_wp_error( $response ) ) {
			$msg = 'Anthropic API request failed: ' . $response->get_error_message();
			error_log( self::LOG_PREFIX . ' ' . $msg );
			throw new \RuntimeException( $msg );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code !== 200 ) {
			$err = $data['error']['message'] ?? $body;
			$msg = "Anthropic API error ({$code}): {$err}";
			// Log the full raw body so nothing is hidden
			error_log( self::LOG_PREFIX . ' ' . $msg );
			error_log( self::LOG_PREFIX . ' Full response body: ' . $body );
			throw new \RuntimeException( $msg );
		}

		$text = $data['content'][0]['text'] ?? '';
		error_log( self::LOG_PREFIX . " Raw caption response for post #{$post_id}: " . substr( $text, 0, 500 ) );

		$captions = self::parse_captions( $text );
		error_log( self::LOG_PREFIX . " Captions parsed successfully for post #{$post_id}" );

		return $captions;
	}

	// ──────────────────────────────────────────────────────────────────────────

	/**
	 * Build the system prompt.
	 *
	 * Includes business identity from scos_biz_* options and all knowledge
	 * file contents. All rule content lives here so the user turn stays short,
	 * which improves Claude's instruction-following.
	 *
	 * @param  array{brand_core: string, vocabulary: string, social_media: string} $knowledge
	 */
	private static function system_prompt( array $knowledge ): string {
		$business_name = (string) get_option( 'scos_biz_business_name', get_bloginfo( 'name' ) );
		$service_desc  = (string) get_option( 'scos_biz_service_description', '' );

		$identity = $business_name;
		if ( $service_desc ) {
			$identity .= " — {$service_desc}";
		}

		$parts = [];

		if ( $knowledge['brand_core'] ) {
			$parts[] = "[BRAND CORE]\n\n{$knowledge['brand_core']}";
		}

		if ( $knowledge['vocabulary'] ) {
			$parts[] = "[VOCABULARY]\n\n{$knowledge['vocabulary']}";
		}

		if ( $knowledge['social_media'] ) {
			$parts[] = "[SOCIAL MEDIA RULES]\n\n{$knowledge['social_media']}";
		}

		$guidelines = $parts
			? "\n\nBefore writing anything, apply the following guidelines precisely:\n\n" . implode( "\n\n", $parts )
			: '';

		return "You are a social media copywriter for {$identity}.{$guidelines}\n\n"
			. "Return valid JSON only. No preamble, no explanation, no markdown fences.\n"
			. 'The JSON must have exactly these keys: "post_1", "post_2", "post_3". '
			. 'Each value is a complete, ready-to-publish social media caption string.';
	}

	/**
	 * Build the per-post user prompt.
	 * Rules stay in the system prompt — keep this as short as possible.
	 */
	private static function build_prompt( array $ctx ): string {
		$title        = $ctx['title']        ?? '';
		$excerpt      = $ctx['excerpt']      ?? '';
		$shortlink    = $ctx['shortlink']    ?? ( $ctx['permalink'] ?? '' );
		$content_type = $ctx['content_type'] ?? 'project';

		return "Create 3 social media captions for this {$content_type}:\n\n"
			. "Title: {$title}\n"
			. "Description: {$excerpt}\n"
			. "Link: {$shortlink}\n\n"
			. "post_1: Storytelling angle — draw the reader into the project.\n"
			. "post_2: Results / outcome angle — focus on what was delivered and why it holds up.\n"
			. "post_3: Behind-the-scenes / process angle — tease the craft or a specific build decision.\n\n"
			. "Each caption: 2–4 sentences, link included naturally, 3–5 hashtags at the end.\n\n"
			. "Respond with ONLY this exact JSON structure, no other text:\n"
			. '{"post_1": "caption one here", "post_2": "caption two here", "post_3": "caption three here"}';
	}

	/**
	 * Parse the model's text response into the expected array.
	 * Handles JSON that may be wrapped in markdown fences despite the instruction.
	 *
	 * @throws \RuntimeException if captions cannot be parsed.
	 */
	private static function parse_captions( string $text ): array {
		// Strip markdown fences
		$text = preg_replace( '/^```(?:json)?\s*/i', '', trim( $text ) );
		$text = preg_replace( '/\s*```$/', '', $text );
		$text = trim( $text );

		$data = json_decode( $text, true );

		// ── Happy path: expected {post_1, post_2, post_3} object ─────────────
		if ( is_array( $data ) && ! empty( $data['post_1'] ) && ! empty( $data['post_2'] ) && ! empty( $data['post_3'] ) ) {
			return [
				'post_1' => (string) $data['post_1'],
				'post_2' => (string) $data['post_2'],
				'post_3' => (string) $data['post_3'],
			];
		}

		// ── Fallback: model returned an array of objects ─────────────────────
		// e.g. [{"angle":"storytelling","caption":"..."}, ...]
		if ( is_array( $data ) && isset( $data[0] ) && is_array( $data[0] ) ) {
			$captions = [];
			foreach ( $data as $item ) {
				// Accept any key that looks like text content
				$caption = $item['caption'] ?? $item['text'] ?? $item['content'] ?? $item['post'] ?? '';
				if ( $caption ) {
					$captions[] = (string) $caption;
				}
			}
			if ( count( $captions ) >= 3 ) {
				return [
					'post_1' => $captions[0],
					'post_2' => $captions[1],
					'post_3' => $captions[2],
				];
			}
		}

		// ── Nothing worked ────────────────────────────────────────────────────
		throw new \RuntimeException(
			'Anthropic response did not contain expected caption keys. Raw: ' . substr( $text, 0, 400 )
		);
	}

	// ──────────────────────────────────────────────────────────────────────────
	// Knowledge file helpers
	// ──────────────────────────────────────────────────────────────────────────

	/**
	 * Read all knowledge files and return their contents keyed by type.
	 * Missing files return an empty string — non-fatal.
	 *
	 * @return array{brand_core: string, vocabulary: string, social_media: string}
	 */
	private static function read_knowledge_files(): array {
		$base   = WP_CONTENT_DIR . '/ai-knowledge/';
		$result = [];

		foreach ( self::KNOWLEDGE_FILES as $key => $filename ) {
			$path = $base . $filename;
			if ( file_exists( $path ) ) {
				$content = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				$result[ $key ] = ( false !== $content ) ? $content : '';
			} else {
				$result[ $key ] = '';
			}
		}

		return $result;
	}

	/**
	 * Create a deny-all .htaccess in wp-content/ai-knowledge/ if absent.
	 * PHP reads files via filesystem — only direct HTTP access is blocked.
	 */
	private static function maybe_create_htaccess(): void {
		$dir      = WP_CONTENT_DIR . '/ai-knowledge';
		$htaccess = $dir . '/.htaccess';

		if ( ! is_dir( $dir ) || file_exists( $htaccess ) || ! is_writable( $dir ) ) {
			return;
		}

		$content = "# Block direct HTTP access — PHP reads this folder safely via filesystem\n"
				 . "Order deny,allow\n"
				 . "Deny from all\n"
				 . "\n"
				 . "# Nginx: add to server block:\n"
				 . "# location ^~ /wp-content/ai-knowledge/ { deny all; }\n";

		@file_put_contents( $htaccess, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
}
