<?php
/**
 * Caption Generator
 *
 * Builds social captions for the amplification pipeline. Reads brand knowledge
 * from wp-content/ai-knowledge/, assembles the prompt, and generates through
 * the WordPress AI Client — provider and model resolved at runtime and
 * governed by the site's connector approvals.
 *
 * Replaces Anthropic_Client, which hardcoded an endpoint, a model constant and
 * a private API key. That key was not a registered connector credential, so
 * Connector_Approval\Http_Guard could not attribute the request and the calls
 * bypassed the approval layer entirely. See CLAUDE.md § 6.
 *
 * Knowledge files (all optional — missing files are silently skipped):
 *   301-social-brand-voice.md — brand identity, tone, positioning
 *   302-social-media-meta.md  — platform-specific rules and formatting
 *   303-social-media-gmb.md   — Google Business post rules
 *   304-brand-vocabulary.md   — approved / banned word list
 *
 * Guards wp-content/ai-knowledge/ against HTTP access on every call: creates
 * the folder if needed, writes an Apache 2.2 + 2.4 deny .htaccess, and drops
 * an index.php fallback (hardening carried over from Anthropic_Client 1.1.0).
 *
 * @package    SiteEssentials
 * @subpackage Modules\SocialAmplification\Amplification
 * v1.0 | 2026-09-11
 * v1.1 | 2026-09-11 — Normalise ai-knowledge files to UTF-8 (Windows-1252 files
 *                      broke the strict AI Client JSON encoding); scrub prompts.
 */

namespace SiteEssentials\Modules\SocialAmplification\Amplification;

defined( 'ABSPATH' ) || exit;

class Caption_Generator {

	const MAX_TOKENS  = 2500;
	const LOG_PREFIX  = '[SCOS SMA Captions]';

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
	 * @param  array    $post_context post_id, title, excerpt, permalink, shortlink, content_type.
	 * @param  string[] $frames       Framing angle strings. Cycled via modulo when count > frames.
	 * @param  int      $count        Number of captions to generate (default 3).
	 * @return array<string, string>  Keys post_1 … post_N.
	 * @throws \RuntimeException on generation error or unparseable response.
	 */
	public static function generate_captions( array $post_context, array $frames = [], int $count = 3 ): array {
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

		$knowledge = self::read_knowledge_files();
		$system    = self::system_prompt( $knowledge, $count );
		$prompt    = self::build_prompt( $post_context, $frames, $count );

		$post_id = $post_context['post_id'] ?? '?';
		error_log( self::LOG_PREFIX . " Generating {$count} captions for post #{$post_id}" );

		$text = self::generate( $prompt, $system, 0.7 );

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
		self::maybe_create_htaccess();

		$knowledge = self::read_knowledge_files();
		$system    = self::system_prompt_gmb( $knowledge );
		$prompt    = self::build_gmb_prompt( $post_context );
		$post_id   = $post_context['post_id'] ?? '?';

		error_log( self::LOG_PREFIX . " Generating GMB caption for post #{$post_id}" );

		$text = self::generate( $prompt, $system, 0.7 );

		return self::parse_gmb_caption( $text );
	}

	// ──────────────────────────────────────────────────────────────────────────
	// Generation
	// ──────────────────────────────────────────────────────────────────────────

	/**
	 * Run a prompt through the WordPress AI Client.
	 *
	 * No provider, model, endpoint or credential is named here by design — the
	 * client resolves them from the site's configured providers and the
	 * connector approval registry.
	 *
	 * @param  string $prompt      User turn.
	 * @param  string $system      System instruction.
	 * @param  float  $temperature Sampling temperature.
	 * @return string Raw model output.
	 * @throws \RuntimeException when the AI client is unavailable or returns an error.
	 */
	private static function generate( string $prompt, string $system, float $temperature ): string {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			$msg = 'WordPress AI Client is not available. Activate the AI plugin and at least one AI Provider plugin.';
			error_log( self::LOG_PREFIX . ' ' . $msg );
			throw new \RuntimeException( $msg );
		}

		// The AI Client JSON-encodes strictly, so a single invalid byte from any
		// input fails the whole request ("Malformed UTF-8 characters"). Knowledge
		// files are converted at read time; this catches anything else.
		if ( function_exists( 'mb_scrub' ) ) {
			$prompt = mb_scrub( $prompt, 'UTF-8' );
			$system = mb_scrub( $system, 'UTF-8' );
		}

		$builder = wp_ai_client_prompt( $prompt )
			->using_system_instruction( $system )
			->using_temperature( $temperature )
			->using_max_tokens( self::MAX_TOKENS );

		if ( function_exists( 'WordPress\AI\get_preferred_models_for_text_generation' ) ) {
			$builder = $builder->using_model_preference(
				...\WordPress\AI\get_preferred_models_for_text_generation()
			);
		}

		$result = $builder->generate_text();

		if ( is_wp_error( $result ) ) {
			$msg = 'AI generation failed: ' . $result->get_error_message();
			error_log( self::LOG_PREFIX . ' ' . $msg . ' [' . $result->get_error_code() . ']' );
			throw new \RuntimeException( $msg );
		}

		return (string) $result;
	}

	// ──────────────────────────────────────────────────────────────────────────
	// Prompts
	// ──────────────────────────────────────────────────────────────────────────

	/**
	 * Build the system prompt.
	 *
	 * Business identity comes from scos_biz_* options; all tone and rule content
	 * comes from the ai-knowledge files so none of it is hardcoded here.
	 *
	 * @param  array $knowledge Knowledge file contents keyed by type.
	 * @param  int   $count     Number of captions (used to declare expected keys).
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

	// ──────────────────────────────────────────────────────────────────────────
	// Parsing
	// ──────────────────────────────────────────────────────────────────────────

	/**
	 * Parse the model's text response into an array of N captions.
	 * Handles JSON that may be wrapped in markdown fences despite the instruction.
	 *
	 * @return array<string, string> Keys post_1 … post_N.
	 * @throws \RuntimeException if captions cannot be parsed.
	 */
	private static function parse_captions( string $text, int $count = 3 ): array {
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
					$fallback_key        = $keys[ ( $i - 1 ) % count( $keys ) ];
					$result["post_{$i}"] = $result[ $fallback_key ];
				}
				return $result;
			}
		}

		// ── Fallback: model returned an array of objects ─────────────────────
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

		throw new \RuntimeException(
			"AI response did not contain expected post_1…post_{$count} keys. Raw: " . substr( $text, 0, 400 )
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
			'AI response did not contain expected gmb_caption key. Raw: ' . substr( $text, 0, 400 )
		);
	}

	// ──────────────────────────────────────────────────────────────────────────
	// Knowledge file helpers
	// ──────────────────────────────────────────────────────────────────────────

	/**
	 * Return a knowledge file's contents as valid UTF-8.
	 *
	 * Files saved from Windows as "ANSI" (Windows-1252) carry em/en dashes,
	 * smart quotes and ® as single bytes (0x96, 0x97, 0xAE …) that are not valid
	 * UTF-8, and the WP AI Client rejects the whole request because of them.
	 * A file is saved in one encoding, so converting it as a whole is safe —
	 * unlike the assembled prompt, which mixes these files with genuine UTF-8.
	 *
	 * @param  string $content  Raw file contents.
	 * @param  string $filename For the log line only.
	 * @return string
	 */
	private static function to_utf8( string $content, string $filename ): string {
		if ( '' === $content || ! function_exists( 'mb_check_encoding' ) || mb_check_encoding( $content, 'UTF-8' ) ) {
			return $content;
		}

		error_log( self::LOG_PREFIX . " ai-knowledge/{$filename} is not UTF-8 — converted from Windows-1252. Resave it as UTF-8 to silence this." );

		$converted = mb_convert_encoding( $content, 'UTF-8', 'Windows-1252' );
		return is_string( $converted ) ? $converted : $content;
	}

	/**
	 * Read all knowledge files and return their contents keyed by type.
	 * Missing files return an empty string — non-fatal. Contents are normalised
	 * to UTF-8 (see to_utf8()).
	 */
	private static function read_knowledge_files(): array {
		$base   = WP_CONTENT_DIR . '/ai-knowledge/';
		$result = [];

		foreach ( self::KNOWLEDGE_FILES as $key => $filename ) {
			$path = $base . $filename;
			if ( file_exists( $path ) ) {
				$content        = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				$result[ $key ] = ( false !== $content ) ? self::to_utf8( $content, $filename ) : '';
			} else {
				$result[ $key ] = '';
			}
		}

		return $result;
	}

	/**
	 * Ensure wp-content/ai-knowledge/ is not reachable over HTTP.
	 *
	 * The folder holds client strategy documents, so exposure is a confidentiality
	 * problem rather than an execution one. PHP reads the files from disk, so
	 * blocking direct HTTP access costs nothing.
	 *
	 * @since 1.1.0 Creates the directory guard unconditionally, emits both Apache
	 *              2.2 and 2.4 syntax, adds an index.php, and reports write failures.
	 */
	private static function maybe_create_htaccess(): void {
		$dir = WP_CONTENT_DIR . '/ai-knowledge';

		// Previously this returned early when the directory did not exist, so a
		// folder created later by another process (CLI sync, deploy) was left
		// unguarded until something happened to call this again.
		if ( ! is_dir( $dir ) ) {
			if ( ! wp_mkdir_p( $dir ) ) {
				return;
			}
		}

		if ( ! is_writable( $dir ) ) {
			error_log( self::LOG_PREFIX . ' ai-knowledge directory is not writable; cannot install access guard.' );
			return;
		}

		$htaccess = $dir . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			// Order/Deny is Apache 2.2 (mod_access). Apache 2.4 needs mod_authz_core
			// syntax, and without it the old directives are either ignored or fatal
			// depending on whether mod_access_compat is loaded — either way the
			// folder was not reliably protected.
			$content = "# Block direct HTTP access - PHP reads this folder from disk.\n"
					 . "<IfModule mod_authz_core.c>\n"
					 . "\tRequire all denied\n"
					 . "</IfModule>\n"
					 . "<IfModule !mod_authz_core.c>\n"
					 . "\tOrder deny,allow\n"
					 . "\tDeny from all\n"
					 . "</IfModule>\n"
					 . "\n"
					 . "# Nginx ignores .htaccess. Add to the server block:\n"
					 . "# location ^~ /wp-content/ai-knowledge/ { deny all; }\n";

			if ( false === file_put_contents( $htaccess, $content ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
				error_log( self::LOG_PREFIX . ' failed to write ai-knowledge/.htaccess; folder may be publicly readable.' );
			}
		}

		// Second line of defence: stops directory listing where .htaccess is not
		// honoured at all (nginx), though it does not stop direct file requests.
		$index = $dir . '/index.php';
		if ( ! file_exists( $index ) ) {
			@file_put_contents( $index, "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
	}
}
