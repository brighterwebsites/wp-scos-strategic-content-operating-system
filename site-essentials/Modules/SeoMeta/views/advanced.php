<?php
/**
 * SEO — Advanced tab view
 *
 * Rendered inside the "Advanced" tab of Site Essentials > SEO.
 * Currently contains the Image SEO settings section.
 *
 * @package    SiteEssentials
 * @subpackage Modules\SeoMeta\Views
 */

use SiteEssentials\Modules\SeoMeta\Image_SEO;
use SiteEssentials\Modules\SeoMeta\Virtual_Files;

defined( 'ABSPATH' ) || exit;

$opts         = Image_SEO::get();
$robots_opts  = Virtual_Files::get_robots();
$llms_opts    = Virtual_Files::get_llms();
$blog_public  = (bool) get_option( 'blog_public', 1 );

if ( isset( $_GET['updated'] ) && 'true' === $_GET['updated'] ) {
	echo '<div class="notice notice-success is-dismissible"><p>' .
	     esc_html__( 'Advanced SEO settings saved.', 'site-essentials' ) .
	     '</p></div>';
}
?>

<style>
.scos-adv-section {
	margin: 24px 0 8px;
	font-size: 15px;
	font-weight: 600;
	color: #1d2327;
	border-bottom: 2px solid #dcdcde;
	padding-bottom: 8px;
}
.scos-adv-card {
	background: #fff;
	border: 1px solid #dcdcde;
	border-radius: 4px;
	padding: 0;
	margin-bottom: 10px;
}
.scos-adv-toggle-row {
	display: flex;
	align-items: flex-start;
	gap: 14px;
	padding: 18px 20px;
	border-bottom: 1px solid #f0f0f1;
}
.scos-adv-toggle-row:last-child {
	border-bottom: none;
}
.scos-adv-toggle-row input[type="checkbox"] {
	margin-top: 3px;
	flex-shrink: 0;
}
.scos-adv-toggle-body strong {
	display: block;
	font-size: 13px;
	color: #1d2327;
	margin-bottom: 3px;
}
.scos-adv-toggle-body p {
	margin: 0;
	font-size: 12px;
	color: #50575e;
	line-height: 1.5;
}
.scos-adv-toggle-body .scos-adv-note {
	margin-top: 5px;
	font-size: 11px;
	color: #8c8f94;
	font-style: italic;
}
.scos-adv-coming-soon {
	background: #f6f7f7;
	border: 1px dashed #c3c4c7;
	border-radius: 4px;
	padding: 18px 20px;
	color: #8c8f94;
	font-size: 13px;
	margin-bottom: 10px;
}
.scos-adv-coming-soon strong {
	color: #50575e;
}
.scos-adv-save-bar {
	position: sticky;
	bottom: 0;
	background: #fff;
	border-top: 1px solid #dcdcde;
	padding: 14px 0;
	margin-top: 24px;
	z-index: 10;
}
</style>

<div class="scos-adv-wrap" style="max-width: 820px; margin-top: 20px;">

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="scos_save_image_seo">
		<?php wp_nonce_field( 'scos_save_image_seo', 'scos_image_seo_nonce' ); ?>

		<!-- ── Image SEO ──────────────────────────────────────────────────── -->
		<h2 class="scos-adv-section"><?php esc_html_e( 'Image SEO', 'site-essentials' ); ?></h2>
		<p style="color:#50575e; font-size:13px; margin-bottom:14px;">
			<?php esc_html_e( 'WordPress creates an "attachment page" for every uploaded file. These pages are thin-content and rarely useful for SEO. The options below let you hide, disable, or redirect them cleanly.', 'site-essentials' ); ?>
		</p>

		<div class="scos-adv-card">

			<!-- noindex attachment pages -->
			<label class="scos-adv-toggle-row" style="cursor:pointer;">
				<input type="checkbox"
					name="scos_image_seo[noindex_attachments]"
					value="1"
					<?php checked( $opts['noindex_attachments'] ); ?>>
				<div class="scos-adv-toggle-body">
					<strong><?php esc_html_e( 'Noindex attachment pages', 'site-essentials' ); ?></strong>
					<p><?php esc_html_e( 'Adds noindex,nofollow to the robots meta tag on every attachment page so search engines won\'t index them. The pages remain accessible — nothing is redirected or deleted.', 'site-essentials' ); ?></p>
					<p class="scos-adv-note"><?php esc_html_e( 'Redundant if "Redirect attachment pages" is also enabled — a redirected page is never indexed regardless.', 'site-essentials' ); ?></p>
				</div>
			</label>

			<!-- disable comments on attachments -->
			<label class="scos-adv-toggle-row" style="cursor:pointer;">
				<input type="checkbox"
					name="scos_image_seo[no_comments_attachments]"
					value="1"
					<?php checked( $opts['no_comments_attachments'] ); ?>>
				<div class="scos-adv-toggle-body">
					<strong><?php esc_html_e( 'Disable comments on attachment pages', 'site-essentials' ); ?></strong>
					<p><?php esc_html_e( 'Closes comments and hides the comment count on all attachment pages. Reduces spam surface and removes unnecessary UI on pages you don\'t actively maintain.', 'site-essentials' ); ?></p>
				</div>
			</label>

			<!-- redirect to file URL -->
			<label class="scos-adv-toggle-row" style="cursor:pointer;">
				<input type="checkbox"
					name="scos_image_seo[redirect_attachments]"
					value="1"
					<?php checked( $opts['redirect_attachments'] ); ?>>
				<div class="scos-adv-toggle-body">
					<strong><?php esc_html_e( 'Redirect attachment pages to file URL', 'site-essentials' ); ?></strong>
					<p><?php esc_html_e( 'Issues a 301 redirect from any attachment page (e.g. /site.com/photo/) directly to the physical file URL (e.g. /wp-content/uploads/…/photo.jpg). Eliminates thin attachment pages from your crawl budget entirely.', 'site-essentials' ); ?></p>
					<p class="scos-adv-note"><?php esc_html_e( 'Recommended. Compatible with Breakdance, Yoast, SEOPress, and most page builders.', 'site-essentials' ); ?></p>
				</div>
			</label>

			<!-- rename uploaded files -->
			<label class="scos-adv-toggle-row" style="cursor:pointer;">
				<input type="checkbox"
					name="scos_image_seo[rename_files]"
					value="1"
					<?php checked( $opts['rename_files'] ); ?>>
				<div class="scos-adv-toggle-body">
					<strong><?php esc_html_e( 'Auto-rename uploaded image files', 'site-essentials' ); ?></strong>
					<p><?php esc_html_e( 'At upload time, filenames are cleaned to be URL-friendly: forced lowercase, spaces and underscores replaced with hyphens, and consecutive hyphens collapsed. Applies to new uploads only — existing media is not renamed.', 'site-essentials' ); ?></p>
					<p class="scos-adv-note"><?php esc_html_e( 'Examples: "My Photo_01.JPG" → "my-photo-01.jpg" &nbsp;|&nbsp; "hero image.PNG" → "hero-image.png"', 'site-essentials' ); ?></p>
				</div>
			</label>

		</div><!-- /.scos-adv-card -->

		<!-- ── Robots.txt ────────────────────────────────────────────────── -->
		<h2 class="scos-adv-section"><?php esc_html_e( 'Robots.txt', 'site-essentials' ); ?></h2>

		<?php if ( ! $blog_public ) : ?>
		<div class="notice notice-warning inline" style="margin-bottom:14px;">
			<p>
				<strong><?php esc_html_e( 'Heads up:', 'site-essentials' ); ?></strong>
				<?php
				printf(
					/* translators: %s: link to Reading settings */
					esc_html__( 'Your site is set to "Discourage search engines" in %s. WordPress outputs Disallow: / before this editor runs, so custom robots.txt content will have no effect until that setting is turned off.', 'site-essentials' ),
					'<a href="' . esc_url( admin_url( 'options-reading.php' ) ) . '">' . esc_html__( 'Settings › Reading', 'site-essentials' ) . '</a>'
				);
				?>
			</p>
		</div>
		<?php endif; ?>

		<p style="color:#50575e; font-size:13px; margin-bottom:14px;">
			<?php esc_html_e( 'When enabled, Site Essentials completely replaces WordPress\'s default robots.txt output with the content below. The file is virtual — no physical file is written to disk.', 'site-essentials' ); ?>
			<a href="<?php echo esc_url( home_url( '/robots.txt' ) ); ?>" target="_blank" style="margin-left:6px;"><?php esc_html_e( 'View current robots.txt ↗', 'site-essentials' ); ?></a>
		</p>

		<div class="scos-adv-card" style="padding: 18px 20px;">
			<label style="display:flex; align-items:center; gap:10px; margin-bottom:16px; cursor:pointer;">
				<input type="checkbox"
					name="scos_robots[enabled]"
					id="scos_robots_enabled"
					value="1"
					<?php checked( $robots_opts['enabled'] ); ?>>
				<strong><?php esc_html_e( 'Enable custom robots.txt', 'site-essentials' ); ?></strong>
			</label>

			<div id="scos-robots-editor" <?php echo $robots_opts['enabled'] ? '' : 'style="opacity:.5;pointer-events:none;"'; ?>>
				<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
					<label for="scos_robots_content" style="font-size:13px; font-weight:600; color:#1d2327;">
						<?php esc_html_e( 'robots.txt content', 'site-essentials' ); ?>
					</label>
					<button type="button" id="scos-robots-reset"
						style="font-size:12px; color:#2271b1; background:none; border:none; cursor:pointer; padding:0;"
						data-default="<?php echo esc_attr( Virtual_Files::default_robots_txt() ); ?>">
						<?php esc_html_e( '↺ Reset to gold standard', 'site-essentials' ); ?>
					</button>
				</div>
				<textarea
					name="scos_robots[content]"
					id="scos_robots_content"
					rows="22"
					style="width:100%; font-family:monospace; font-size:12px; line-height:1.6; border:1px solid #dcdcde; border-radius:3px; padding:10px; resize:vertical;"
					placeholder="<?php esc_attr_e( 'Enter robots.txt content…', 'site-essentials' ); ?>"><?php echo esc_textarea( $robots_opts['content'] ?: Virtual_Files::default_robots_txt() ); ?></textarea>
				<p style="font-size:12px; color:#8c8f94; margin-top:6px;">
					<?php esc_html_e( 'Each directive on its own line. No PHP, no HTML — plain text only.', 'site-essentials' ); ?>
				</p>
			</div>
		</div>

		<!-- ── LLMs.txt ───────────────────────────────────────────────────── -->
		<h2 class="scos-adv-section"><?php esc_html_e( 'LLMs.txt', 'site-essentials' ); ?></h2>

		<p style="color:#50575e; font-size:13px; margin-bottom:14px;">
			<?php esc_html_e( 'LLMs.txt is an emerging standard that helps AI crawlers and large language models understand your site\'s content and purpose. When enabled, Site Essentials serves /llms.txt as a virtual plain-text file using a WordPress rewrite rule.', 'site-essentials' ); ?>
			<a href="https://llmstxt.org/" target="_blank" style="margin-left:6px;"><?php esc_html_e( 'Learn about LLMs.txt ↗', 'site-essentials' ); ?></a>
		</p>

		<?php if ( $llms_opts['enabled'] ) : ?>
		<p style="font-size:13px; margin-bottom:10px;">
			<a href="<?php echo esc_url( home_url( '/llms.txt' ) ); ?>" target="_blank"><?php esc_html_e( 'View current llms.txt ↗', 'site-essentials' ); ?></a>
		</p>
		<?php endif; ?>

		<div class="scos-adv-card" style="padding: 18px 20px;">
			<label style="display:flex; align-items:center; gap:10px; margin-bottom:16px; cursor:pointer;">
				<input type="checkbox"
					name="scos_llms[enabled]"
					id="scos_llms_enabled"
					value="1"
					<?php checked( $llms_opts['enabled'] ); ?>>
				<strong><?php esc_html_e( 'Enable virtual /llms.txt', 'site-essentials' ); ?></strong>
			</label>
			<p style="font-size:12px; color:#8c8f94; margin: -8px 0 16px;">
				<?php esc_html_e( 'Changing the enabled state will flush WordPress rewrite rules on save.', 'site-essentials' ); ?>
			</p>

			<div id="scos-llms-editor" <?php echo $llms_opts['enabled'] ? '' : 'style="opacity:.5;pointer-events:none;"'; ?>>
				<label for="scos_llms_content" style="display:block; font-size:13px; font-weight:600; color:#1d2327; margin-bottom:6px;">
					<?php esc_html_e( 'llms.txt content', 'site-essentials' ); ?>
				</label>
				<textarea
					name="scos_llms[content]"
					id="scos_llms_content"
					rows="16"
					style="width:100%; font-family:monospace; font-size:12px; line-height:1.6; border:1px solid #dcdcde; border-radius:3px; padding:10px; resize:vertical;"
					placeholder="<?php esc_attr_e( '# Add your llms.txt content here…', 'site-essentials' ); ?>"><?php echo esc_textarea( $llms_opts['content'] ); ?></textarea>
				<p style="font-size:12px; color:#8c8f94; margin-top:6px;">
					<?php esc_html_e( 'Plain text only. The file is served with Content-Type: text/plain and X-Robots-Tag: noindex.', 'site-essentials' ); ?>
				</p>
			</div>
		</div>

		<script>
		( function () {
			// robots.txt — toggle editor opacity when enable checkbox changes
			var robotsCb = document.getElementById( 'scos_robots_enabled' );
			var robotsEd = document.getElementById( 'scos-robots-editor' );
			if ( robotsCb && robotsEd ) {
				robotsCb.addEventListener( 'change', function () {
					robotsEd.style.opacity        = this.checked ? '' : '0.5';
					robotsEd.style.pointerEvents  = this.checked ? '' : 'none';
				} );
			}

			// robots.txt — reset to default
			var resetBtn = document.getElementById( 'scos-robots-reset' );
			var robotsTa = document.getElementById( 'scos_robots_content' );
			if ( resetBtn && robotsTa ) {
				resetBtn.addEventListener( 'click', function () {
					if ( window.confirm( '<?php echo esc_js( __( 'Replace the current content with the gold-standard template?', 'site-essentials' ) ); ?>' ) ) {
						robotsTa.value = this.dataset.default;
					}
				} );
			}

			// llms.txt — toggle editor opacity when enable checkbox changes
			var llmsCb = document.getElementById( 'scos_llms_enabled' );
			var llmsEd = document.getElementById( 'scos-llms-editor' );
			if ( llmsCb && llmsEd ) {
				llmsCb.addEventListener( 'change', function () {
					llmsEd.style.opacity       = this.checked ? '' : '0.5';
					llmsEd.style.pointerEvents = this.checked ? '' : 'none';
				} );
			}
		} )();
		</script>

		<!-- ── Sticky save bar ────────────────────────────────────────────── -->
		<div class="scos-adv-save-bar">
			<?php submit_button( __( 'Save Advanced SEO Settings', 'site-essentials' ), 'primary', 'submit', false ); ?>
		</div>

	</form>
</div>
