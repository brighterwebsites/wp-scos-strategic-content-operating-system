<?php
/**
 * Anthropic API Client
 *
 * Reads knowledge files from wp-content/ai-knowledge/, builds a structured
 * prompt, calls the Anthropic Messages API, and parses the JSON caption response.
 *
 * Knowledge files (all optional — missing files are silently skipped):
 *   301-social-brand-voice.md — brand identity, tone, positioning
 *   302-social-media-meta.md  — platform-specific rules and formatting
 *   303-social-media-gmb.md   — Google Business post rules
 *   304-brand-vocabulary.md   — approved / banned word list
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
	const MAX_TOKENS      = 2500;
	const LOG_PREFIX      = '[SCOS SMA Anthropic]';

	/** Knowledge files relative to WP_CONTENT_DIR/ai-knowledge/ */
	const KNOWLEDGE_FILES = [
		'brand_core'   => '301-social-brand-voice.md',
		'social_media' => '302-social-media-meta.md',
		'gmb_rules'    => '303-social-media-gmb.md',
		'vocabulary'   => '304-brand-vocabulary.md',
	];

	/**
	 * Generate N social media captions for the given post context.
	 *
	 * @param  array    $post_context {
	 *     @type int    $post_id
	 *     @type string $title
	 *     @type string $excerpt
	 *     @type string $permalink
	 *     @type string $shortlink
	 *     @type string $content_type
	 * }
	 * @param  string[] $frames  Framing angle strings. Cycled via modulo for post_count > count($frames).
	 * @param  int      $count   Number of captions to generate (default 3).
	 * @return array<string, string>  Keys post_1 … post_N.
	 * @throws \RuntimeException on API error or bad response.
	 */
	public static function generate_captions( array $post_context, array $frames = [], int $count = 3 ): array {
		$api_key = get_option( 'bw_anthropic_api_key', '' );
		if ( ! $api_key ) {
			$msg = 'Anthropic API key is not configured.';
			error_log( self::LOG_PREFIX . ' ' . $msg );
			throw new \RuntimeException( $msg );
		}

		self::maybe_create_htaccess();

		$count  = max( 1, $count );
		$frames = array_values( array_filter( $frames, 'strlen' ) );
		if ( empty( $frames ) ) {
			$frames = [
				'Storytelling angle — draw the reader into the project.',
				'Results / outcome angle — focus on what was delivered and why it holds up.',
				'Behind-the-scenes / process angle — tease the craft or a specific build decision.',
			];
		}

		$model     = (string) get_option( 'bw_anthropic_model', self::DEFAULT_MODEL ) ?: self::DEFAULT_MODEL;
		$knowledge = self::read_knowledge_files();
		$system    = self::system_prompt( $knowledge, $count );
		$prompt    = self::build_prompt( $post_context, $frames, $count );

		$post_id = $post_context['post_id'] ?? '?';
		error_log( self::LOG_PREFIX . " Generating {$count} captions for post #{$post_id} using model {$model}" );

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

		$captions = self::parse_captions( $text, $count );
		error_log( self::LOG_PREFIX . " Captions parsed successfully for post #{$post_id} (count={$count})" );

		return $captions;
	}

	/**
	 * Generate one GMB caption for the given post context.
	 *
	 * @throws \RuntimeException
	 */
	public static function generate_gmb_caption( array $post_context ): string {
		$api_key = get_option( 'bw_anthropic_api_key', '' );
		if ( ! $api_key ) {
			$msg = 'Anthropic API key is not configured.';
			error_log( self::LOG_PREFIX . ' ' . $msg );
			throw new \RuntimeException( $msg );
		}

		self::maybe_create_htaccess();

		$model     = (string) get_option( 'bw_anthropic_model', self::DEFAULT_MODEL ) ?: self::DEFAULT_MODEL;
		$knowledge = self::read_knowledge_files();
		$system    = self::system_prompt_gmb( $knowledge );
		$prompt    = self::build_gmb_prompt( $post_context );
		$post_id   = $post_context['post_id'] ?? '?';

		error_log( self::LOG_PREFIX . " Generating GMB caption for post #{$post_id} using model {$model}" );

		$payload = [
			'model'      => $model,
			'max_tokens' => 500,
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
			$msg = 'Anthropic API request failed (GMB): ' . $response->get_error_message();
			error_log( self::LOG_PREFIX . ' ' . $msg );
			throw new \RuntimeException( $msg );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code !== 200 ) {
			$err = $data['error']['message'] ?? $body;
			$msg = "Anthropic API error (GMB, {$code}): {$err}";
			error_log( self::LOG_PREFIX . ' ' . $msg );
			error_log( self::LOG_PREFIX . ' Full GMB response body: ' . $body );
			throw new \RuntimeException( $msg );
		}

		$text = $data['content'][0]['text'] ?? '';
		return self::parse_gmb_caption( $text );
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
	 * @param  int $count  Number of captions to generate (used to declare expected keys).
	 */
	private static function system_prompt( array $knowledge, int $count = 3 ): string {
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

		$key_list = implode( ', ', array_map( static fn( $i ) => '"post_' . $i . '"', range( 1, $count ) ) );

		return "You are a social media copywriter for {$identity}.{$guidelines}\n\n"
			. "Return valid JSON only. No preamble, no explanation, no markdown fences.\n"
			. "The JSON must have exactly these keys: {$key_list}. "
			. 'Each value is a complete, ready-to-publish social media caption string.';
	}

	/**
	 * Build the per-post user prompt with dynamic frame angles.
	 * Frames are cycled via modulo when $count > count($frames).
	 *
	 * @param  array    $ctx    Post context.
	 * @param  string[] $frames Framing angle strings.
	 * @param  int      $count  Number of captions to generate.
	 */
	private static function build_prompt( array $ctx, array $frames, int $count ): string {
		$title        = $ctx['title']        ?? '';
		$excerpt      = $ctx['excerpt']      ?? '';
		$shortlink    = $ctx['shortlink']    ?? ( $ctx['permalink'] ?? '' );
		$content_type = $ctx['content_type'] ?? 'project';

		$frame_count = count( $frames );
		$frame_lines = '';
		for ( $i = 1; $i <= $count; $i++ ) {
			$frame       = $frames[ ( $i - 1 ) % $frame_count ];
			$frame_lines .= "post_{$i}: {$frame}\n";
		}

		$json_example = '{' . implode( ', ', array_map( static fn( $i ) => '"post_' . $i . '": "caption here"', range( 1, $count ) ) ) . '}';

		return "Create {$count} social media captions for this {$content_type}:\n\n"
			. "Title: {$title}\n"
			. "Description: {$excerpt}\n"
			. "Link: {$shortlink}\n\n"
			. $frame_lines
			. "\nEach caption: 2–4 sentences, link included naturally, 3–5 hashtags at the end.\n\n"
			. "Respond with ONLY this exact JSON structure, no other text:\n"
			. $json_example;
	}

	private static function build_gmb_prompt( array $ctx ): string {
		$title        = $ctx['title']        ?? '';
		$excerpt      = $ctx['excerpt']      ?? '';
		$content_type = $ctx['content_type'] ?? 'project';

		return "Generate 1 Google Business Profile post for the following project.\n\n"
			. "Project Title: {$title}\n"
			. "Project Summary: {$excerpt}\n"
			. "Content Type: {$content_type}\n\n"
			. "Rules:\n"
			. "- Write 1–3 short sentences only (150–300 characters total).\n"
			. "- Lead with the customer benefit or outcome in the first sentence.\n"
			. "- Do NOT include any URLs, phone numbers, or hashtags.\n"
			. "- Do NOT use em dashes or en dashes.\n"
			. "- Write in plain Australian English.\n"
			. "- The post will have a \"Learn More\" button added automatically — do not reference it in the text.\n"
			. "- Do not fabricate specific details not present in the title or summary.\n\n"
			. "Return valid JSON only:\n"
			. "{\"gmb_caption\": \"...\"}";
	}

	/**
	 * Parse the model's text response into an array of N captions.
	 * Handles JSON that may be wrapped in markdown fences despite the instruction.
	 *
	 * @param  string $text  Raw model output.
	 * @param  int    $count Expected number of captions.
	 * @return array<string, string>  Keys post_1 … post_N.
	 * @throws \RuntimeException if captions cannot be parsed.
	 */
	private static function parse_captions( string $text, int $count = 3 ): array {
		// Strip markdown fences
		$text = preg_replace( '/^```(?:json)?\s*/i', '', trim( $text ) );
		$text = preg_replace( '/\s*```$/', '', $text );
		$text = trim( $text );

		$data = json_decode( $text, true );

		// ── Happy path: {post_1, …, post_N} object ───────────────────────────
		if ( is_array( $data ) && ! isset( $data[0] ) ) {
			$result   = [];
			$all_good = true;
			for ( $i = 1; $i <= $count; $i++ ) {
				$key = "post_{$i}";
				if ( ! empty( $data[ $key ] ) ) {
					$result[ $key ] = (string) $data[ $key ];
				} else {
					$all_good = false;
					break;
				}
			}
			if ( $all_good && count( $result ) === $count ) {
				return $result;
			}
			// Partial match: if at least post_1 exists, fill missing slots by cycling.
			if ( ! empty( $result ) ) {
				$keys = array_keys( $result );
				for ( $i = count( $result ) + 1; $i <= $count; $i++ ) {
					$fallback_key    = $keys[ ( $i - 1 ) % count( $keys ) ];
					$result["post_{$i}"] = $result[ $fallback_key ];
				}
				return $result;
			}
		}

		// ── Fallback: model returned an array of objects ─────────────────────
		// e.g. [{"angle":"storytelling","caption":"..."}, ...]
		if ( is_array( $data ) && isset( $data[0] ) && is_array( $data[0] ) ) {
			$captions = [];
			foreach ( $data as $item ) {
				$caption = $item['caption'] ?? $item['text'] ?? $item['content'] ?? $item['post'] ?? '';
				if ( $caption ) {
					$captions[] = (string) $caption;
				}
			}
			if ( count( $captions ) >= $count ) {
				$result = [];
				for ( $i = 1; $i <= $count; $i++ ) {
					$result["post_{$i}"] = $captions[ $i - 1 ];
				}
				return $result;
			}
		}

		// ── Nothing worked ────────────────────────────────────────────────────
		throw new \RuntimeException(
			"Anthropic response did not contain expected post_1…post_{$count} keys. Raw: " . substr( $text, 0, 400 )
		);
	}

	private static function parse_gmb_caption( string $text ): string {
		$text = preg_replace( '/^```(?:json)?\s*/i', '', trim( $text ) );
		$text = preg_replace( '/\s*```$/', '', $text );
		$text = trim( $text );

		$data = json_decode( $text, true );
		if ( is_array( $data ) && ! empty( $data['gmb_caption'] ) ) {
			return trim( (string) $data['gmb_caption'] );
		}

		throw new \RuntimeException(
			'Anthropic GMB response did not contain expected gmb_caption key. Raw: ' . substr( $text, 0, 400 )
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

	private static function system_prompt_gmb( array $knowledge ): string {
		$business_name = (string) get_option( 'scos_biz_business_name', get_bloginfo( 'name' ) );
		$service_desc  = (string) get_option( 'scos_biz_service_description', '' );
		$identity      = $business_name . ( $service_desc ? " — {$service_desc}" : '' );

		$parts = [];
		if ( ! empty( $knowledge['brand_core'] ) ) {
			$parts[] = "[BRAND CORE]\n\n{$knowledge['brand_core']}";
		}
		if ( ! empty( $knowledge['vocabulary'] ) ) {
			$parts[] = "[VOCABULARY]\n\n{$knowledge['vocabulary']}";
		}

		if ( ! empty( $knowledge['gmb_rules'] ) ) {
			$parts[] = "[GMB RULES]\n\n{$knowledge['gmb_rules']}";
		} else {
			error_log( self::LOG_PREFIX . ' 303-social-media-gmb.md missing. Falling back to inline GMB rules.' );
			$parts[] = "[GMB RULES]\n\n"
				. "- No URLs in caption body.\n"
				. "- No phone numbers.\n"
				. "- No hashtags.\n"
				. "- No em dash or en dash characters.\n"
				. "- 150-300 characters total.\n"
				. "- 1-3 sentences, plain Australian English.\n"
				. "- One idea only.\n";
		}

		return "You are a social media copywriter for {$identity}.\n\n"
			. implode( "\n\n", $parts ) . "\n\n"
			. "Return valid JSON only. No preamble, no explanation, no markdown fences.\n"
			. 'The JSON must have exactly this key: "gmb_caption".';
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
