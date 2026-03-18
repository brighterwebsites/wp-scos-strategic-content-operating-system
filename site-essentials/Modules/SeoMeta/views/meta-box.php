<?php
/**
 * SEO Meta box view — 3-tab UI.
 *
 * Variables available from Meta_Box::render():
 *   $post              WP_Post
 *   $breadcrumb_title  string
 *   $tldr              string
 *   $title             string
 *   $description       string
 *   $canonical         string
 *   $robots            string[]
 *   $sitemap_exclude   string[]
 *
 * @package SiteEssentials
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="scos-seo-wrap">

	<!-- ── TAB NAV ── -->
	<nav class="scos-seo-tabs" role="tablist">
		<button type="button" class="scos-seo-tab-btn is-active" data-tab="core"
			role="tab" aria-selected="true" aria-controls="scos-seo-tab-core">
			<?php esc_html_e( 'Core SEO', 'site-essentials' ); ?>
		</button>
		<button type="button" class="scos-seo-tab-btn" data-tab="advanced"
			role="tab" aria-selected="false" aria-controls="scos-seo-tab-advanced">
			<?php esc_html_e( 'Advanced', 'site-essentials' ); ?>
		</button>
		<button type="button" class="scos-seo-tab-btn" data-tab="og"
			role="tab" aria-selected="false" aria-controls="scos-seo-tab-og">
			<?php esc_html_e( 'OG Social', 'site-essentials' ); ?>
		</button>
	</nav>

	<!-- ══ CORE SEO TAB ══ -->
	<div class="scos-seo-tab-panel is-active" id="scos-seo-tab-core" role="tabpanel">

		<!-- Breadcrumb Title -->
		<div class="scos-seo-field">
			<label for="scos_seo_breadcrumb_title">
				<?php esc_html_e( 'Breadcrumb Label', 'site-essentials' ); ?>
			</label>
			<input type="text"
				name="scos_seo_breadcrumb_title"
				id="scos_seo_breadcrumb_title"
				value="<?php echo esc_attr( $breadcrumb_title ); ?>"
				placeholder="<?php esc_attr_e( 'e.g. seo-signals (used in breadcrumbs and shortlinks)', 'site-essentials' ); ?>">
			<p class="scos-seo-help"><?php esc_html_e( 'Short label for breadcrumb navigation and YOURLS shortlinks. Slug format, no spaces.', 'site-essentials' ); ?></p>
		</div>

		<!-- TLDR Summary -->
		<div class="scos-seo-field">
			<label for="scos_seo_tldr">
				<?php esc_html_e( 'TLDR / Article Summary', 'site-essentials' ); ?>
			</label>
			<textarea name="scos_seo_tldr" id="scos_seo_tldr" rows="3"
				placeholder="<?php esc_attr_e( 'Brief 1–3 sentence summary used for voice search, social sharing, and the [tldr] shortcode…', 'site-essentials' ); ?>"><?php echo esc_textarea( $tldr ); ?></textarea>
			<p class="scos-seo-help"><?php esc_html_e( 'Shown via [tldr] shortcode. Also used for Google Speakable and Airtable sync.', 'site-essentials' ); ?></p>
		</div>

		<!-- Meta Title -->
		<div class="scos-seo-field scos-seo-field--counted">
			<label for="scos_seo_title">
				<?php esc_html_e( 'Meta Title', 'site-essentials' ); ?>
				<span class="scos-seo-counter" data-target="scos_seo_title" data-max="60">
					<span class="scos-seo-count">0</span>/60
				</span>
			</label>
			<input type="text"
				name="scos_seo_title"
				id="scos_seo_title"
				value="<?php echo esc_attr( $title ); ?>"
				maxlength="100"
				placeholder="<?php esc_attr_e( 'Leave blank to use post title', 'site-essentials' ); ?>">
			<div class="scos-seo-bar" data-target="scos_seo_title" data-max="60">
				<div class="scos-seo-bar__fill"></div>
			</div>
		</div>

		<!-- Meta Description -->
		<div class="scos-seo-field scos-seo-field--counted">
			<label for="scos_seo_description">
				<?php esc_html_e( 'Meta Description', 'site-essentials' ); ?>
				<span class="scos-seo-counter" data-target="scos_seo_description" data-max="160">
					<span class="scos-seo-count">0</span>/160
				</span>
			</label>
			<textarea name="scos_seo_description" id="scos_seo_description" rows="3"
				maxlength="320"
				placeholder="<?php esc_attr_e( 'Leave blank to use post excerpt or auto-generated snippet', 'site-essentials' ); ?>"><?php echo esc_textarea( $description ); ?></textarea>
			<div class="scos-seo-bar" data-target="scos_seo_description" data-max="160">
				<div class="scos-seo-bar__fill"></div>
			</div>
		</div>

	</div><!-- /core -->

	<!-- ══ ADVANCED TAB ══ -->
	<div class="scos-seo-tab-panel" id="scos-seo-tab-advanced" role="tabpanel" hidden>

		<!-- Canonical URL -->
		<div class="scos-seo-field">
			<label for="scos_seo_canonical">
				<?php esc_html_e( 'Canonical URL', 'site-essentials' ); ?>
			</label>
			<input type="url"
				name="scos_seo_canonical"
				id="scos_seo_canonical"
				value="<?php echo esc_url( $canonical ); ?>"
				placeholder="<?php echo esc_attr( get_permalink( $post->ID ) ?: '' ); ?>">
			<p class="scos-seo-help"><?php esc_html_e( 'Leave blank to use the default page URL. Set only if this content is syndicated or has duplicate URLs.', 'site-essentials' ); ?></p>
		</div>

		<!-- Meta Robots -->
		<div class="scos-seo-field">
			<label><?php esc_html_e( 'Meta Robots', 'site-essentials' ); ?></label>
			<div class="scos-seo-checks">
				<?php foreach ( \SiteEssentials\Modules\SeoMeta\Meta_Fields::robots_options() as $val => $label ) : ?>
					<label class="scos-seo-check-label<?php echo 'noindex' === $val ? ' scos-seo-check-label--danger' : ''; ?>">
						<input type="checkbox"
							name="scos_seo_robots[]"
							value="<?php echo esc_attr( $val ); ?>"
							<?php checked( in_array( $val, $robots, true ) ); ?>>
						<code><?php echo esc_html( $val ); ?></code>
						<span><?php echo esc_html( preg_replace( '/^[a-z]+\s—\s/i', '', $label ) ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- Sitemap Visibility -->
		<div class="scos-seo-field">
			<label><?php esc_html_e( 'Sitemap Visibility', 'site-essentials' ); ?></label>
			<div class="scos-seo-checks">
				<?php foreach ( \SiteEssentials\Modules\SeoMeta\Meta_Fields::sitemap_options() as $val => $label ) : ?>
					<label class="scos-seo-check-label">
						<input type="checkbox"
							name="scos_seo_sitemap_exclude[]"
							value="<?php echo esc_attr( $val ); ?>"
							<?php checked( in_array( $val, $sitemap_exclude, true ) ); ?>>
						<?php echo esc_html( $label ); ?>
					</label>
				<?php endforeach; ?>
			</div>
		</div>

	</div><!-- /advanced -->

	<!-- ══ OG SOCIAL TAB (Phase 2) ══ -->
	<div class="scos-seo-tab-panel" id="scos-seo-tab-og" role="tabpanel" hidden>
		<div class="scos-seo-phase2-notice">
			<strong><?php esc_html_e( 'Phase 2 — Coming Soon', 'site-essentials' ); ?></strong>
			<p><?php esc_html_e( 'Custom Open Graph title, description, and image for Facebook / LinkedIn. Twitter/X card override (falls back to OG settings).', 'site-essentials' ); ?></p>
			<p class="scos-seo-help"><?php esc_html_e( 'Currently handled by existing OG meta output in brighter-og-meta.php.', 'site-essentials' ); ?></p>
		</div>
	</div><!-- /og -->

</div><!-- /scos-seo-wrap -->
