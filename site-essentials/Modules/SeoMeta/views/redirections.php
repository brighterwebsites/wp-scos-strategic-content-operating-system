<?php
/**
 * SEO — Redirections tab view
 *
 * Two plain-text rule editors:
 *   301 Permanent Redirects  — /old-path => /new-path
 *   410 Gone                 — /deleted-path  (one path per line)
 *
 * @package    SiteEssentials
 * @subpackage Modules\SeoMeta\Views
 */

use SiteEssentials\Modules\SeoMeta\Redirections;

defined( 'ABSPATH' ) || exit;

$raw_301   = Redirections::get_301_raw();
$raw_410   = Redirections::get_410_raw();
$count_301 = count( Redirections::parse_301_rules( $raw_301 ) );
$count_410 = count( Redirections::get_410_paths() );

$disable_404_guess = (bool) get_option( Redirections::OPTION_DISABLE_404_GUESS, false );
$bd_guard          = (string) get_option( Redirections::OPTION_BREAKDANCE_GUARD, 'off' );
if ( ! in_array( $bd_guard, [ 'off', 'guard', 'protect' ], true ) ) {
	$bd_guard = 'off';
}

if ( isset( $_GET['updated'] ) && 'true' === $_GET['updated'] ) {
	echo '<div class="notice notice-success is-dismissible"><p>'
	   . esc_html__( 'Redirections saved.', 'site-essentials' )
	   . '</p></div>';
}
?>
<style>
.scos-redir-wrap { max-width: 900px; margin-top: 20px; }
.scos-redir-section {
	margin: 0 0 28px;
}
.scos-redir-header {
	display: flex;
	align-items: center;
	gap: 10px;
	margin-bottom: 6px;
}
.scos-redir-header h2 {
	margin: 0;
	font-size: 15px;
	font-weight: 600;
	color: #1d2327;
}
.scos-redir-badge {
	font-size: 11px;
	background: #e8f0e8;
	color: #2d6a2d;
	border-radius: 10px;
	padding: 2px 9px;
	font-weight: 600;
}
.scos-redir-badge.zero {
	background: #f0f0f1;
	color: #787c82;
}
.scos-redir-desc {
	font-size: 12px;
	color: #50575e;
	margin: 0 0 8px;
	line-height: 1.6;
}
.scos-redir-desc code {
	background: #f0f0f1;
	padding: 1px 5px;
	border-radius: 3px;
}
.scos-redir-textarea {
	width: 100%;
	font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
	font-size: 12px;
	line-height: 1.7;
	border: 1px solid #dcdcde;
	border-radius: 4px;
	padding: 12px 14px;
	resize: vertical;
	color: #1d2327;
	background: #fafafa;
}
.scos-redir-textarea:focus {
	background: #fff;
	border-color: #2271b1;
	box-shadow: 0 0 0 1px #2271b1;
	outline: none;
}
.scos-redir-save-bar {
	position: sticky;
	bottom: 0;
	background: #fff;
	border-top: 1px solid #dcdcde;
	padding: 14px 0;
	margin-top: 8px;
	z-index: 10;
}
</style>

<div class="scos-redir-wrap">
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="scos_save_redirections">
	<?php wp_nonce_field( 'scos_save_redirections', 'scos_redirections_nonce' ); ?>

	<!-- ── 301 Permanent Redirects ──────────────────────────────────────── -->
	<div class="scos-redir-section">
		<div class="scos-redir-header">
			<h2><?php esc_html_e( '301 Permanent Redirects', 'site-essentials' ); ?></h2>
			<span class="scos-redir-badge <?php echo 0 === $count_301 ? 'zero' : ''; ?>">
				<?php echo esc_html( $count_301 ); ?> <?php esc_html_e( 'active', 'site-essentials' ); ?>
			</span>
		</div>
		<p class="scos-redir-desc">
			<?php esc_html_e( 'One rule per line. Format:', 'site-essentials' ); ?>
			<code>/old-path => /new-path</code>
			<?php esc_html_e( 'or', 'site-essentials' ); ?>
			<code>/old-path => https://external.com/page</code>
			<br>
			<?php esc_html_e( 'Trailing slashes on the source are normalised. Query strings from the original request are automatically appended to the destination. Lines starting with', 'site-essentials' ); ?>
			<code>#</code>
			<?php esc_html_e( 'are comments.', 'site-essentials' ); ?>
		</p>
		<textarea
			name="scos_301_rules"
			class="scos-redir-textarea"
			rows="18"
			placeholder="<?php esc_attr_e( "# Examples\n/seo => /services/seo/\n/branding => /services/website-design/\n/old-blog-post => https://newsite.com/post/", 'site-essentials' ); ?>"
		><?php echo esc_textarea( $raw_301 ); ?></textarea>
	</div>

	<!-- ── 410 Gone ─────────────────────────────────────────────────────── -->
	<div class="scos-redir-section">
		<div class="scos-redir-header">
			<h2><?php esc_html_e( '410 Gone — Permanently Removed', 'site-essentials' ); ?></h2>
			<span class="scos-redir-badge <?php echo 0 === $count_410 ? 'zero' : ''; ?>">
				<?php echo esc_html( $count_410 ); ?> <?php esc_html_e( 'active', 'site-essentials' ); ?>
			</span>
		</div>
		<p class="scos-redir-desc">
			<?php esc_html_e( 'One path per line. These URLs return an HTTP 410 Gone response — telling search engines the content has been permanently deleted and to remove it from their index. No destination needed. Lines starting with', 'site-essentials' ); ?>
			<code>#</code>
			<?php esc_html_e( 'are comments.', 'site-essentials' ); ?>
		</p>
		<textarea
			name="scos_410_rules"
			class="scos-redir-textarea"
			rows="10"
			placeholder="<?php esc_attr_e( "# Paths that no longer exist\n/old-deleted-page\n/category/removed-category", 'site-essentials' ); ?>"
		><?php echo esc_textarea( $raw_410 ); ?></textarea>
	</div>

	<!-- ── WordPress 404 “guess” redirect ───────────────────────────────── -->
	<div class="scos-redir-section">
		<div class="scos-redir-header">
			<h2><?php esc_html_e( '404 redirect guessing', 'site-essentials' ); ?></h2>
		</div>
		<p class="scos-redir-desc">
			<?php esc_html_e( 'By default, WordPress may redirect some 404 requests to a “similar” URL. That can fight explicit 301/410 rules and confuse audits. Turn this off to always return a true 404 unless one of your rules above matches.', 'site-essentials' ); ?>
		</p>
		<label>
			<input type="checkbox" name="scos_disable_404_redirect_guess" value="1" <?php checked( $disable_404_guess ); ?> />
			<?php esc_html_e( 'Stop WordPress from guessing redirects on 404s', 'site-essentials' ); ?>
		</label>
	</div>

	<!-- ── Breakdance: avoid accidental block editor saves ─────────────── -->
	<div class="scos-redir-section">
		<div class="scos-redir-header">
			<h2><?php esc_html_e( 'Breakdance: “Use default editor”', 'site-essentials' ); ?></h2>
		</div>
		<p class="scos-redir-desc">
			<?php esc_html_e( 'When Breakdance data exists on a post, the launcher shows “Use default editor”. Saving from the block editor can clear Breakdance layout. Choose how strongly to discourage that (CSS only — not role-based).', 'site-essentials' ); ?>
		</p>
		<fieldset>
			<label style="display:block;margin-bottom:8px;">
				<input type="radio" name="scos_breakdance_editor_guard" value="off" <?php checked( $bd_guard, 'off' ); ?> />
				<?php esc_html_e( 'Off (default)', 'site-essentials' ); ?>
			</label>
			<label style="display:block;margin-bottom:8px;">
				<input type="radio" name="scos_breakdance_editor_guard" value="guard" <?php checked( $bd_guard, 'guard' ); ?> />
				<?php esc_html_e( 'Guard — show a red warning above the buttons; style “Use default editor” as secondary', 'site-essentials' ); ?>
			</label>
			<label style="display:block;">
				<input type="radio" name="scos_breakdance_editor_guard" value="protect" <?php checked( $bd_guard, 'protect' ); ?> />
				<?php esc_html_e( 'Protect — hide “Use default editor” (strongest)', 'site-essentials' ); ?>
			</label>
		</fieldset>
	</div>

	<div class="scos-redir-save-bar">
		<?php submit_button( __( 'Save Redirections', 'site-essentials' ), 'primary', 'submit', false ); ?>
		<?php if ( $count_301 + $count_410 > 0 ) : ?>
			<span style="margin-left:14px; font-size:12px; color:#787c82;">
				<?php printf(
					esc_html__( '%1$d redirect%2$s + %3$d gone rule%4$s active', 'site-essentials' ),
					$count_301,
					1 === $count_301 ? '' : 's',
					$count_410,
					1 === $count_410 ? '' : 's'
				); ?>
			</span>
		<?php endif; ?>
	</div>
</form>
</div>
