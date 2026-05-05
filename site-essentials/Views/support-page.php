<?php
/**
 * Support hub landing page — read-only display page.
 *
 * Pulls from se_agency_* and se_support_* options.
 * Accessible to administrator, editor, shop_manager.
 *
 * SCOS-SUPPORT-PASS2 — replaced placeholder shell with real display page
 *
 * @package SiteEssentials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// SCOS-SUPPORT-PASS2 — collect non-empty support tool links
$tools = [];
for ( $i = 1; $i <= 6; $i++ ) {
	$title = get_option( "se_support_tool_{$i}_title", '' );
	$url   = get_option( "se_support_tool_{$i}_url", '' );
	if ( $title && $url ) {
		$tools[] = [ 'title' => $title, 'url' => $url ];
	}
}

// SCOS-SUPPORT-PASS2 — collect non-empty AI tool links
$ai_tools = [];
for ( $i = 1; $i <= 4; $i++ ) {
	$title = get_option( "se_support_ai_{$i}_title", '' );
	$url   = get_option( "se_support_ai_{$i}_url", '' );
	if ( $title && $url ) {
		$ai_tools[] = [ 'title' => $title, 'url' => $url ];
	}
}

$agency_name  = get_option( 'se_agency_name', get_bloginfo( 'name' ) ); // SCOS-SUPPORT-PASS2 — agency branding
$agency_logo  = get_option( 'se_agency_logo' );
$logo_url     = $agency_logo ? wp_get_attachment_image_url( absint( $agency_logo ), 'medium' ) : '';
$agency_email = get_option( 'se_agency_email', '' );
$agency_phone = get_option( 'se_agency_phone', '' );
$agency_url   = get_option( 'se_agency_url', '' );
?>
<div class="wrap scos">

	<?php /* ── Hero ─────────────────────────────────────────────── */ ?>
	<div class="scos-support__hero">
		<?php if ( $logo_url ) : ?>
			<img src="<?php echo esc_url( $logo_url ); ?>"
				alt="<?php echo esc_attr( $agency_name ); ?>"
				class="scos-support__logo" />
		<?php endif; ?>
		<h1 class="scos-support__hero-title">
			<?php echo esc_html( $agency_name ); ?> <?php esc_html_e( 'Support', 'site-essentials' ); ?>
		</h1>
		<p class="scos-support__hero-sub">
			<?php echo esc_html( get_bloginfo( 'name' ) ); ?>
		</p>
	</div>

	<?php /* ── Support tools ─────────────────────────────────────── */ ?>
	<?php if ( ! empty( $tools ) ) : ?>
	<p class="scos__section-label"><?php esc_html_e( 'Manuals &amp; references', 'site-essentials' ); ?></p>
	<div class="scos-support__grid">
		<?php foreach ( $tools as $tool ) : ?>
		<a href="<?php echo esc_url( $tool['url'] ); ?>"
		   target="_blank"
		   rel="noopener noreferrer"
		   class="scos-support__tile">
			<?php echo esc_html( $tool['title'] ); ?>
		</a>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

	<?php /* ── AI tools ─────────────────────────────────────────── */ ?>
	<?php if ( ! empty( $ai_tools ) ) : ?>
	<p class="scos__section-label"><?php esc_html_e( 'AI tools', 'site-essentials' ); ?></p>
	<div class="scos-support__grid">
		<?php foreach ( $ai_tools as $tool ) : ?>
		<a href="<?php echo esc_url( $tool['url'] ); ?>"
		   target="_blank"
		   rel="noopener noreferrer"
		   class="scos-support__tile">
			<?php echo esc_html( $tool['title'] ); ?>
		</a>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

	<?php /* ── Contact card ─────────────────────────────────────── */ ?>
	<?php if ( $agency_email || $agency_phone || $agency_url ) : ?>
	<div class="scos-support__row">
		<div class="scos-card">
			<div class="scos-card__header">
				<h2 class="scos-card__title"><?php esc_html_e( 'Get in touch', 'site-essentials' ); ?></h2>
			</div>
			<div class="scos-card__body">
				<ul class="scos-support__list">
					<?php if ( $agency_email ) : ?>
					<li>
						<a href="mailto:<?php echo esc_attr( $agency_email ); ?>">
							<?php echo esc_html( $agency_email ); ?>
						</a>
					</li>
					<?php endif; ?>
					<?php if ( $agency_phone ) : ?>
					<li>
						<a href="tel:<?php echo esc_attr( $agency_phone ); ?>">
							<?php echo esc_html( $agency_phone ); ?>
						</a>
					</li>
					<?php endif; ?>
					<?php if ( $agency_url ) : ?>
					<li>
						<a href="<?php echo esc_url( $agency_url ); ?>"
						   target="_blank" rel="noopener noreferrer">
							<?php echo esc_html( $agency_url ); ?>
						</a>
					</li>
					<?php endif; ?>
				</ul>
			</div>
		</div>
	</div>
	<?php endif; ?>

	<?php /* ── Performance & Search — hardcoded standard tools ──── */ ?>
	<p class="scos__section-label"><?php esc_html_e( 'Performance &amp; Search', 'site-essentials' ); ?></p>
	<div class="scos-support__grid">
		<a href="https://search.google.com/search-console" target="_blank" rel="noopener noreferrer" class="scos-support__tile">
			<?php esc_html_e( 'Google Search Console', 'site-essentials' ); ?>
		</a>
		<a href="https://analytics.google.com/analytics/web/" target="_blank" rel="noopener noreferrer" class="scos-support__tile">
			<?php esc_html_e( 'Google Analytics', 'site-essentials' ); ?>
		</a>
		<a href="https://app.ahrefs.com/site-audit" target="_blank" rel="noopener noreferrer" class="scos-support__tile">
			<?php esc_html_e( 'AHREFS Site Audit', 'site-essentials' ); ?>
		</a>
		<a href="https://pagespeed.web.dev/" target="_blank" rel="noopener noreferrer" class="scos-support__tile">
			<?php esc_html_e( 'PageSpeed Insights', 'site-essentials' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'site-health.php' ) ); ?>" class="scos-support__tile">
			<?php esc_html_e( 'WP Website Health', 'site-essentials' ); ?>
		</a>
	</div>

	<?php /* ── Empty state — nothing configured yet ────────────── */ ?>
	<?php if ( empty( $tools ) && empty( $ai_tools ) && ! $agency_email && ! $agency_phone && ! $agency_url ) : ?>
	<div class="scos-empty">
		<div class="scos-empty__icon">&#x1F6DF;</div>
		<p class="scos-empty__title"><?php esc_html_e( 'Support hub', 'site-essentials' ); ?></p>
		<p class="scos-empty__desc">
			<?php esc_html_e( 'No content configured yet. Add agency details and support links via Site Essentials → Agency.', 'site-essentials' ); ?>
		</p>
	</div>
	<?php endif; ?>

</div>
