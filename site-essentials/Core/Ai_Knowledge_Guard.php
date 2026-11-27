<?php
/**
 * AI Knowledge — HTTP access guard for wp-content/ai-knowledge/.
 *
 * The folder holds client strategy, brand voice and agency reports. PHP reads
 * it from disk; nothing should reach it over HTTP. Admin screens and emails
 * open files through Ai_Knowledge_Viewer instead.
 *
 * What blocks the folder, per server:
 *   Apache        the folder's own .htaccess (Require / Deny) is enough.
 *   LiteSpeed,    ignore access directives in .htaccess and only honour
 *   OpenLiteSpeed rewrite rules, and a folder's rewrite rules only once loaded.
 *                 The reliable block is a vhost rule (fleet sites have one) or
 *                 a rewrite rule in the ROOT .htaccess.
 *   nginx         ignores .htaccess entirely. Needs a `location` rule in the
 *                 server block, which SCOS can't write.
 *
 * So the guard doesn't assume, it checks. Once per VERSION (on `init`) it
 * writes the folder's .htaccess, index.php and a probe file, then a cron event
 * requests the probe over HTTP:
 *   - the file itself comes back (exposed): writes the root .htaccess block (Apache/LiteSpeed only)
 *     and checks again. Sites that already block the folder are never touched.
 *   - still exposed, or nginx: status `exposed`, and admins get a notice with
 *     the rule to add.
 * The status is re-checked weekly. `wp scos ai-knowledge-guard` runs the whole
 * thing at once (use it after pushing strategy files over SSH).
 *
 * Moved out of Social Amplification's Caption_Generator, where it only ran
 * when captions were generated and never upgraded an existing file (#249).
 *
 * v1.0 | 2026-11-27
 *
 * @package    SiteEssentials
 * @subpackage Core
 */

declare( strict_types=1 );

namespace SiteEssentials\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ai_Knowledge_Guard {

	/** Bump to rewrite the guard files and re-probe on every site. */
	const VERSION = '2';

	/** Autoloaded option: v, status (pending|protected|exposed|unchecked), code, checked, root_written. */
	const OPT = 'se_ai_knowledge_guard';

	const PROBE = 'scos-guard-probe.md';

	/** Same marker the fleet's hand-added root blocks use, so the guard takes those over instead of adding a second. */
	const MARKER = 'AI-KNOWLEDGE PROTECTION';

	const CRON = 'se_ai_knowledge_guard_probe';

	const RECHECK = 'scos_ai_knowledge_recheck';

	const LOG = '[SCOS ai-knowledge guard]';

	public static function init(): void {
		add_action( 'init', [ self::class, 'maybe_install' ], 20 );
		add_action( self::CRON, [ self::class, 'probe' ] );
		add_action( 'admin_notices', [ self::class, 'render_notice' ] );
		add_action( 'admin_post_' . self::RECHECK, [ self::class, 'handle_recheck' ] );
	}

	/**
	 * @return array{v?:string,status?:string,code?:int,checked?:int,root_written?:int,error?:string}
	 */
	public static function state(): array {
		return (array) get_option( self::OPT, [] );
	}

	/**
	 * Cheap per-request check: one autoloaded option. Installs on a new VERSION,
	 * and queues the weekly re-probe.
	 */
	public static function maybe_install(): void {
		$state = self::state();
		if ( ( $state['v'] ?? '' ) !== self::VERSION ) {
			self::install();
			return;
		}
		if ( (int) ( $state['checked'] ?? 0 ) < time() - WEEK_IN_SECONDS ) {
			self::schedule_probe();
		}
	}

	/**
	 * Write the folder guard files, then queue a probe.
	 */
	public static function install(): void {
		$dir = self::dir();

		// Created even when empty, so files pushed later over SSH land in a guarded folder.
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			error_log( self::LOG . ' could not create ' . $dir );
			return;
		}
		if ( ! is_writable( $dir ) ) {
			error_log( self::LOG . ' ' . $dir . ' is not writable; guard files not written.' );
		} else {
			self::write_if_changed( $dir . '/.htaccess', self::folder_htaccess() );
			if ( ! file_exists( $dir . '/index.php' ) ) {
				self::write_if_changed( $dir . '/index.php', "<?php\n// Silence is golden.\n" );
			}
			self::write_if_changed(
				$dir . '/' . self::PROBE,
				self::PROBE_TEXT . "\nIf you can read it in a browser, the folder is exposed.\n"
			);
		}

		$state           = self::state();
		$state['v']      = self::VERSION;
		$state['status'] = $state['status'] ?? 'pending';
		update_option( self::OPT, $state, true );

		self::schedule_probe();
	}

	/**
	 * Request the probe file over HTTP and record the result. Writes the root
	 * .htaccess block only when the folder answers 200.
	 */
	public static function probe(): array {
		$state = self::state();
		$hit   = self::fetch_probe();

		if ( $hit['served'] && self::write_root_rule() ) {
			$state['root_written'] = time();
			$hit                   = self::fetch_probe();
		}

		if ( $hit['served'] ) {
			$state['status'] = 'exposed';
			error_log( self::LOG . ' the folder is readable over HTTP; see the admin notice for the server rule to add.' );
		} elseif ( $hit['code'] >= 200 && $hit['code'] < 500 ) {
			// Denied, not found, or some other page answered: the file itself isn't served.
			$state['status'] = 'protected';
		} else {
			$state['status'] = 'unchecked';
		}
		$state['code']    = $hit['code'];
		$state['checked'] = time();
		update_option( self::OPT, $state, true );

		return $state;
	}

	/** Text the probe file starts with; only a response carrying it counts as the file being served. */
	const PROBE_TEXT = 'Site Essentials uses this file to check that this folder is blocked over HTTP.';

	/**
	 * Request the probe file and say whether the file itself came back.
	 *
	 * Follows redirects (WP-CLI builds http:// URLs, and the edge redirects them
	 * to https://), but a 200 alone isn't proof: a site that redirects unknown
	 * URLs to the home page answers 200 too. So it's `served` only when the body
	 * is the probe's own text. `code` is the final status, 0 when the request
	 * failed (some hosts can't reach their own URL).
	 *
	 * @return array{code:int,served:bool}
	 */
	public static function fetch_probe(): array {
		$url = add_query_arg( 'scos-guard', (string) time(), content_url( Ai_Knowledge::DIR . '/' . self::PROBE ) );
		$res = wp_remote_get(
			$url,
			[
				'timeout'             => 8,
				'redirection'         => 3,
				'limit_response_size' => 2048,
				'headers'             => [ 'Cache-Control' => 'no-cache' ],
			]
		);
		if ( is_wp_error( $res ) ) {
			error_log( self::LOG . ' probe request failed: ' . $res->get_error_message() );
			return [ 'code' => 0, 'served' => false ];
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		return [
			'code'   => $code,
			'served' => 200 === $code && false !== strpos( (string) wp_remote_retrieve_body( $res ), self::PROBE_TEXT ),
		];
	}

	/**
	 * Put the block in the root .htaccess, before `# BEGIN WordPress`, replacing
	 * any existing block with the same marker. Apache and LiteSpeed only.
	 */
	private static function write_root_rule(): bool {
		global $is_apache;
		if ( empty( $is_apache ) ) {
			error_log( self::LOG . ' not Apache/LiteSpeed; the root .htaccess would be ignored, so it was not written.' );
			return false;
		}

		$file = ABSPATH . '.htaccess';
		if ( file_exists( $file ) ? ! is_writable( $file ) : ! is_writable( ABSPATH ) ) {
			error_log( self::LOG . ' root .htaccess is not writable.' );
			return false;
		}

		$current = file_exists( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
		$new     = self::with_root_block( $current );

		if ( $new === $current ) {
			return true;
		}
		if ( false === file_put_contents( $file, $new, LOCK_EX ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			error_log( self::LOG . ' failed to write the root .htaccess.' );
			return false;
		}
		error_log( self::LOG . ' wrote the ' . self::MARKER . ' block to the root .htaccess.' );
		return true;
	}

	/**
	 * A root .htaccess with the guard block in place: any block with the same
	 * marker removed, the current one inserted just before `# BEGIN WordPress`
	 * (or at the top when there is none). Everything else is left byte for byte.
	 * Pure, so it can be checked against a real file without writing it.
	 */
	public static function with_root_block( string $current ): string {
		$eol   = false !== strpos( $current, "\r\n" ) ? "\r\n" : "\n";
		$block = implode( $eol, self::root_block() ) . $eol;

		$begin = '# BEGIN ' . self::MARKER;
		$end   = '# END ' . self::MARKER;
		$clean = preg_replace( '/^' . preg_quote( $begin, '/' ) . '.*?^' . preg_quote( $end, '/' ) . '[^\n]*\n(?:\r?\n)?/ms', '', $current );
		$clean = is_string( $clean ) ? $clean : $current;

		$pos = strpos( $clean, '# BEGIN WordPress' );
		return false === $pos
			? $block . $eol . $clean
			: substr( $clean, 0, $pos ) . $block . $eol . substr( $clean, $pos );
	}

	/** @return string[] */
	private static function root_block(): array {
		$path = untrailingslashit( (string) wp_parse_url( content_url( Ai_Knowledge::DIR ), PHP_URL_PATH ) );
		// A URL path: keep it to safe characters and escape only the dot, so the rule stays readable.
		$path = str_replace( '.', '\.', (string) preg_replace( '~[^A-Za-z0-9/_.-]~', '', $path ) );
		return [
			'# BEGIN ' . self::MARKER,
			'# Managed by Site Essentials (Ai_Knowledge_Guard). Blocks HTTP access to ai-knowledge/,',
			'# which PHP reads from disk. Written only because the folder was readable over HTTP.',
			'# REQUEST_URI is used because a wp-content/.htaccess creates a per-directory rewrite',
			'# context that strips the wp-content/ prefix from the match string.',
			'<IfModule mod_rewrite.c>',
			'RewriteEngine On',
			'RewriteCond %{REQUEST_URI} ^' . $path . '(/|$) [NC]',
			'RewriteRule .* - [F,L]',
			'</IfModule>',
			'# END ' . self::MARKER,
		];
	}

	private static function folder_htaccess(): string {
		return "# SCOS ai-knowledge guard v" . self::VERSION . ". Managed by Site Essentials; edits are overwritten.\n"
			. "# Apache: the access rules below block the folder.\n"
			. "# LiteSpeed / OpenLiteSpeed ignore access rules here. The vhost or root .htaccess\n"
			. "# rule blocks it there (Ai_Knowledge_Guard writes the root one when needed).\n"
			. "<IfModule mod_authz_core.c>\n"
			. "\tRequire all denied\n"
			. "</IfModule>\n"
			. "<IfModule !mod_authz_core.c>\n"
			. "\tOrder deny,allow\n"
			. "\tDeny from all\n"
			. "</IfModule>\n"
			. "<IfModule mod_rewrite.c>\n"
			. "\tRewriteEngine On\n"
			. "\tRewriteRule .* - [F,L]\n"
			. "</IfModule>\n"
			. "\n"
			. "# nginx ignores .htaccess. Add to the server block:\n"
			. "# location ^~ /wp-content/ai-knowledge/ { deny all; }\n";
	}

	private static function write_if_changed( string $file, string $content ): void {
		if ( file_exists( $file ) && (string) file_get_contents( $file ) === $content ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			return;
		}
		if ( false === file_put_contents( $file, $content, LOCK_EX ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			error_log( self::LOG . ' failed to write ' . $file );
		}
	}

	private static function schedule_probe(): void {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_single_event( time() + 10, self::CRON );
		}
	}

	private static function dir(): string {
		return trailingslashit( WP_CONTENT_DIR ) . Ai_Knowledge::DIR;
	}

	// ── Admin notice ────────────────────────────────────────────────────────

	public static function render_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$state = self::state();
		if ( 'exposed' !== ( $state['status'] ?? '' ) ) {
			return;
		}

		$recheck = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::RECHECK ), self::RECHECK );
		$path    = untrailingslashit( (string) wp_parse_url( content_url( Ai_Knowledge::DIR ), PHP_URL_PATH ) );
		?>
		<div class="notice notice-error">
			<p><strong><?php esc_html_e( 'Client strategy files are readable by anyone.', 'site-essentials' ); ?></strong>
				<?php
				printf(
					/* translators: %s: folder path */
					esc_html__( 'Files in %s (strategy, brand voice, agency reports) are served over HTTP, and Site Essentials couldn\'t block them from here. Add one of these rules on the server:', 'site-essentials' ),
					'<code>' . esc_html( $path ) . '/</code>'
				);
				?>
			</p>
			<ul>
				<li><?php esc_html_e( 'nginx (server block):', 'site-essentials' ); ?> <code>location ^~ <?php echo esc_html( $path ); ?>/ { deny all; }</code></li>
				<li><?php esc_html_e( 'LiteSpeed / OpenLiteSpeed (vhost rewrite rules):', 'site-essentials' ); ?> <code>RewriteRule (^|/)wp-content/ai-knowledge(/|$) - [F,L,NC]</code></li>
				<li><?php esc_html_e( 'Apache: make the root .htaccess writable and check again.', 'site-essentials' ); ?></li>
			</ul>
			<p><a href="<?php echo esc_url( $recheck ); ?>"><?php esc_html_e( 'Check again', 'site-essentials' ); ?></a></p>
		</div>
		<?php
	}

	public static function handle_recheck(): void {
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), self::RECHECK ) ) {
			wp_die( esc_html__( 'Link expired. Go back and try again.', 'site-essentials' ), '', [ 'response' => 403 ] );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'site-essentials' ), '', [ 'response' => 403 ] );
		}
		self::install();
		self::probe();
		wp_safe_redirect( wp_get_referer() ?: admin_url() );
		exit;
	}

	// ── WP-CLI ──────────────────────────────────────────────────────────────

	/**
	 * Install the guard files and probe now. Run after pushing files into ai-knowledge/.
	 *
	 * ## EXAMPLES
	 *
	 *     wp scos ai-knowledge-guard
	 *
	 * @param array $args       Unused.
	 * @param array $assoc_args Unused.
	 */
	public static function cli( $args = [], $assoc_args = [] ): void {
		self::install();
		$state = self::probe();
		$line  = sprintf( 'ai-knowledge: %s (HTTP %d)%s', $state['status'], (int) $state['code'], ! empty( $state['root_written'] ) ? ', root .htaccess block written ' . gmdate( 'Y-m-d H:i', (int) $state['root_written'] ) . ' UTC' : '' );
		if ( 'exposed' === $state['status'] ) {
			\WP_CLI::warning( $line );
		} else {
			\WP_CLI::success( $line );
		}
	}
}
