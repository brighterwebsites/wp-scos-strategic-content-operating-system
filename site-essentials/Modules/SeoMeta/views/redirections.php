<?php
/**
 * SEO — Redirections tab view
 *
 * v1.1 | 2026-05-19
 *
 * SCOS design system: scos-card per section, scos-badge for counts,
 * scos-input--mono for textareas, scos-save-bar.
 * No functional changes — form action, field names, nonce unchanged.
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
	echo '<div class="scos-notice scos-notice--success" style="margin-bottom:var(--scos-s-4)"><p>'
	   . esc_html__( 'Redirections saved.', 'site-essentials' )
	   . '</p></div>';
}
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="scos_save_redirections">
	<?php wp_nonce_field( 'scos_save_redirections', 'scos_redirections_nonce' ); ?>

	<!-- ── 301 Permanent Redirects ──────────────────────────────────── -->
	<div class="scos-card" style="margin-bottom:var(--scos-s-4)">
		<div class="scos-card__header">
			<div>
				<h2 class="scos-card__title">
					<?php esc_html_e( '301 Permanent Redirects', 'site-essentials' ); ?>
					<span class="scos-badge <?php echo $count_301 > 0 ? 'scos-badge--success' : 'scos-badge--soft'; ?>" style="margin-left:var(--scos-s-2)">
						<?php echo esc_html( $count_301 ); ?> <?php esc_html_e( 'active', 'site-essentials' ); ?>
					</span>
				</h2>
			</div>
		</div>
		<div class="scos-card__body">
			<p class="description" style="margin-bottom:var(--scos-s-3)">
				<?php esc_html_e( 'One rule per line. Format:', 'site-essentials' ); ?>
				<code>/old-path => /new-path</code>
				<?php esc_html_e( 'or', 'site-essentials' ); ?>
				<code>/old-path => https://external.com/page</code>
				<br>
				<?php esc_html_e( 'Trailing slashes on the source are normalised. Query strings are automatically appended to the destination. Lines starting with', 'site-essentials' ); ?>
				<code>#</code>
				<?php esc_html_e( 'are comments.', 'site-essentials' ); ?>
			</p>
			<textarea
				name="scos_301_rules"
				class="scos-input scos-input--mono"
				rows="18"
				placeholder="<?php esc_attr_e( "# Examples\n/seo => /services/seo/\n/branding => /services/website-design/\n/old-blog-post => https://newsite.com/post/", 'site-essentials' ); ?>"
			><?php echo esc_textarea( $raw_301 ); ?></textarea>
		</div>
	</div>

	<!-- ── 410 Gone ─────────────────────────────────────────────────── -->
	<div class="scos-card" style="margin-bottom:var(--scos-s-4)">
		<div class="scos-card__header">
			<div>
				<h2 class="scos-card__title">
					<?php esc_html_e( '410 Gone — Permanently Removed', 'site-essentials' ); ?>
					<span class="scos-badge <?php echo $count_410 > 0 ? 'scos-badge--success' : 'scos-badge--soft'; ?>" style="margin-left:var(--scos-s-2)">
						<?php echo esc_html( $count_410 ); ?> <?php esc_html_e( 'active', 'site-essentials' ); ?>
					</span>
				</h2>
			</div>
		</div>
		<div class="scos-card__body">
			<p class="description" style="margin-bottom:var(--scos-s-3)">
				<?php esc_html_e( 'One path per line. These URLs return an HTTP 410 Gone response — telling search engines the content has been permanently deleted and to remove it from their index. No destination needed. Lines starting with', 'site-essentials' ); ?>
				<code>#</code>
				<?php esc_html_e( 'are comments.', 'site-essentials' ); ?>
			</p>
			<textarea
				name="scos_410_rules"
				class="scos-input scos-input--mono"
				rows="10"
				placeholder="<?php esc_attr_e( "# Paths that no longer exist\n/old-deleted-page\n/category/removed-category", 'site-essentials' ); ?>"
			><?php echo esc_textarea( $raw_410 ); ?></textarea>
		</div>
	</div>

	<!-- ── Redirect Settings ─────────────────────────────────────────── -->
	<div class="scos-card" style="margin-bottom:var(--scos-s-4)">
		<div class="scos-card__header scos-card__header--plain">
			<h2 class="scos-card__title"><?php esc_html_e( 'Redirect Settings', 'site-essentials' ); ?></h2>
		</div>
		<div class="scos-card__body">
			<table class="scos-form">
				<tbody>
					<tr>
						<th><?php esc_html_e( '404 redirect guessing', 'site-essentials' ); ?></th>
						<td>
							<label class="scos-checkbox-row">
								<input type="checkbox" name="scos_disable_404_redirect_guess" value="1" <?php checked( $disable_404_guess ); ?> />
								<span><?php esc_html_e( 'Stop WordPress from guessing redirects on 404s', 'site-essentials' ); ?></span>
							</label>
							<p class="description"><?php esc_html_e( 'By default, WordPress may redirect some 404 requests to a "similar" URL. That can fight explicit 301/410 rules and confuse audits.', 'site-essentials' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>

	<!-- ── Admin UX/UI ───────────────────────────────────────────────── -->
	<div class="scos-card" style="margin-bottom:var(--scos-s-6)">
		<div class="scos-card__header scos-card__header--plain">
			<h2 class="scos-card__title"><?php esc_html_e( 'Breakdance: "Use default editor"', 'site-essentials' ); ?></h2>
		</div>
		<div class="scos-card__body">
			<p class="description" style="margin-bottom:var(--scos-s-3)"><?php esc_html_e( 'When Breakdance data exists on a post, the launcher shows "Use default editor". Saving from the block editor can clear Breakdance layout. Choose how strongly to discourage that (CSS only — not role-based).', 'site-essentials' ); ?></p>
			<fieldset>
				<label class="scos-checkbox-row" style="margin-bottom:var(--scos-s-2)">
					<input type="radio" name="scos_breakdance_editor_guard" value="off" <?php checked( $bd_guard, 'off' ); ?> />
					<span><?php esc_html_e( 'Off (default)', 'site-essentials' ); ?></span>
				</label>
				<label class="scos-checkbox-row" style="margin-bottom:var(--scos-s-2)">
					<input type="radio" name="scos_breakdance_editor_guard" value="guard" <?php checked( $bd_guard, 'guard' ); ?> />
					<span><?php esc_html_e( 'Guard — show a red warning above the buttons; style "Use default editor" as secondary', 'site-essentials' ); ?></span>
				</label>
				<label class="scos-checkbox-row">
					<input type="radio" name="scos_breakdance_editor_guard" value="protect" <?php checked( $bd_guard, 'protect' ); ?> />
					<span><?php esc_html_e( 'Protect — hide "Use default editor" (strongest)', 'site-essentials' ); ?></span>
				</label>
			</fieldset>
		</div>
	</div>

	<div class="scos-save-bar">
		<button type="submit" class="scos-btn scos-btn--primary">
			<?php esc_html_e( 'Save Redirections', 'site-essentials' ); ?>
		</button>
		<?php if ( $count_301 + $count_410 > 0 ) : ?>
			<span style="font-size:var(--scos-fs-sm);color:var(--scos-ink-subtle)">
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
