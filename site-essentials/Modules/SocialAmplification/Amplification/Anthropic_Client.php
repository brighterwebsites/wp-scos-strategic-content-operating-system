<?php
/**
 * Anthropic API Client
 *
 * Reads brand-voice.md, builds a structured prompt, calls the Anthropic
 * Messages API (claude-3-5-sonnet), and parses the JSON caption response.
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

	const API_URL        = 'https://api.anthropic.com/v1/messages';
	const API_VERSION    = '2023-06-01';
	const MODEL          = 'claude-3-5-sonnet-20241022';
	const MAX_TOKENS     = 1500;
	const BRAND_VOICE_PATH = 'ai-knowledge/brand-voice.md';

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
			throw new \RuntimeException( 'Anthropic API key is not configured.' );
		}

		self::maybe_create_htaccess();

		$brand_voice = self::read_brand_voice();
		$prompt      = self::build_prompt( $post_context, $brand_voice );

		$response = wp_remote_post( self::API_URL, [
			'timeout' => 60,
			'headers' => [
				'x-api-key'         => $api_key,
				'anthropic-version' => self::API_VERSION,
				'content-type'      => 'application/json',
			],
			'body' => wp_json_encode( [
				'model'      => self::MODEL,
				'max_tokens' => self::MAX_TOKENS,
				'system'     => self::system_prompt( $brand_voice ),
				'messages'   => [
					[ 'role' => 'user', 'content' => $prompt ],
				],
			] ),
		] );

		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( 'Anthropic API request failed: ' . $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code !== 200 ) {
			$err = $data['error']['message'] ?? $body;
			throw new \RuntimeException( "Anthropic API error ({$code}): {$err}" );
		}

		$text = $data['content'][0]['text'] ?? '';
		return self::parse_captions( $text );
	}

	// ──────────────────────────────────────────────────────────────────────────

	private static function system_prompt( string $brand_voice ): string {
		$context = $brand_voice
			? "You have been provided with the brand's voice guidelines below. Follow them precisely.\n\n{$brand_voice}"
			: 'Write in a professional, engaging, and authentic tone suited for a landscape architecture / design studio.';

		return <<<SYSTEM
You are a social media copywriter creating posts for a professional service business.
{$context}

CRITICAL: Always respond with valid JSON only — no preamble, no markdown fences, no trailing text.
The JSON must have exactly these keys: "post_1", "post_2", "post_3".
Each value is a complete, ready-to-publish social media caption string.
SYSTEM;
	}

	private static function build_prompt( array $ctx, string $brand_voice ): string {
		$title        = $ctx['title']        ?? '';
		$excerpt      = $ctx['excerpt']      ?? '';
		$permalink    = $ctx['permalink']    ?? '';
		$shortlink    = $ctx['shortlink']    ?? $permalink;
		$content_type = $ctx['content_type'] ?? 'project';

		return <<<PROMPT
Create 3 distinct social media captions for the following {$content_type}:

Title: {$title}
Description: {$excerpt}
Link: {$shortlink}

Requirements:
- Post 1: Storytelling angle — draw the reader into the project's narrative.
- Post 2: Results / transformation angle — focus on the outcome or value delivered.
- Post 3: Behind-the-scenes / process angle — tease the craft or approach.

Each caption should:
- Be 2–4 sentences long.
- Include the link naturally at the end or integrated into the copy.
- Use 3–5 relevant hashtags at the end.
- Be ready to post directly — no placeholders.

Respond only with JSON: {"post_1": "...", "post_2": "...", "post_3": "..."}
PROMPT;
	}

	/**
	 * Parse the model's text response into the expected array.
	 * Handles JSON that may be wrapped in markdown fences.
	 *
	 * @throws \RuntimeException if captions cannot be parsed.
	 */
	private static function parse_captions( string $text ): array {
		// Strip markdown code fences if present
		$text = preg_replace( '/^```(?:json)?\s*/i', '', trim( $text ) );
		$text = preg_replace( '/\s*```$/', '', $text );

		$data = json_decode( trim( $text ), true );

		if ( ! is_array( $data )
			|| empty( $data['post_1'] )
			|| empty( $data['post_2'] )
			|| empty( $data['post_3'] ) ) {
			throw new \RuntimeException( 'Anthropic response did not contain expected caption keys. Raw: ' . substr( $text, 0, 300 ) );
		}

		return [
			'post_1' => (string) $data['post_1'],
			'post_2' => (string) $data['post_2'],
			'post_3' => (string) $data['post_3'],
		];
	}

	/**
	 * Read brand-voice.md from wp-content/ai-knowledge/.
	 * Returns empty string if the file is missing (non-fatal).
	 */
	private static function read_brand_voice(): string {
		$path = WP_CONTENT_DIR . '/' . self::BRAND_VOICE_PATH;
		if ( ! file_exists( $path ) ) {
			return '';
		}
		$content = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return ( false !== $content ) ? $content : '';
	}

	/**
	 * Create a deny-all .htaccess in wp-content/ai-knowledge/ if absent.
	 * PHP reads the file via filesystem — only direct HTTP access is blocked.
	 */
	private static function maybe_create_htaccess(): void {
		$dir     = WP_CONTENT_DIR . '/ai-knowledge';
		$htaccess = $dir . '/.htaccess';

		if ( ! is_dir( $dir ) || file_exists( $htaccess ) ) {
			return;
		}

		$content = "# Block direct HTTP access — PHP reads this folder safely via filesystem\n"
				 . "Order deny,allow\n"
				 . "Deny from all\n"
				 . "\n"
				 . "# Nginx: add to server block:\n"
				 . "# location ^~ /wp-content/ai-knowledge/ { deny all; }\n";

		file_put_contents( $htaccess, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
}
