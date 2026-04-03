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

defined( 'ABSPATH' ) || exit;

$opts = Image_SEO::get();

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

		<!-- ── Robots.txt (coming soon) ───────────────────────────────────── -->
		<h2 class="scos-adv-section"><?php esc_html_e( 'Robots.txt', 'site-essentials' ); ?></h2>
		<div class="scos-adv-coming-soon">
			<strong><?php esc_html_e( 'Coming soon —', 'site-essentials' ); ?></strong>
			<?php esc_html_e( 'Edit and preview the virtual robots.txt directly from the admin. Override WP\'s default output with custom directives.', 'site-essentials' ); ?>
		</div>

		<!-- ── LLMs.txt (coming soon) ─────────────────────────────────────── -->
		<h2 class="scos-adv-section"><?php esc_html_e( 'LLMs.txt', 'site-essentials' ); ?></h2>
		<div class="scos-adv-coming-soon">
			<strong><?php esc_html_e( 'Coming soon —', 'site-essentials' ); ?></strong>
			<?php esc_html_e( 'Auto-generate an LLMs.txt file (and LLMs-full.txt) describing your site\'s content structure for AI crawlers and large language models.', 'site-essentials' ); ?>
		</div>

		<!-- ── Sticky save bar ────────────────────────────────────────────── -->
		<div class="scos-adv-save-bar">
			<?php submit_button( __( 'Save Advanced SEO Settings', 'site-essentials' ), 'primary', 'submit', false ); ?>
		</div>

	</form>
</div>
