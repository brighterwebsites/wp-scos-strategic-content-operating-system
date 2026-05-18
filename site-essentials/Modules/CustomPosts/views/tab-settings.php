<?php
/**
 * CPT — Settings tab (module cards + General Post Permalinks).
 *
 * v1.0 | 2026-05-19
 *
 * Variables in scope from cpt-page.php:
 * @var array  $opts
 * @var bool   $faq_enabled
 * @var bool   $projects_on
 * @var bool   $reviews_on
 * @var bool   $author_on
 * @var string $guide_faq
 * @var string $guide_projects
 * @var string $guide_reviews
 * @var string $guide_author
 *
 * @package    SiteEssentials
 * @subpackage Modules\CustomPosts
 */

defined( 'ABSPATH' ) || exit;

$post_link_mode = isset( $opts['post_link_mode'] ) ? sanitize_key( (string) $opts['post_link_mode'] ) : 'default';
if ( ! in_array( $post_link_mode, [ 'default', 'custom_prefix', 'category_prefix' ], true ) ) {
	$post_link_mode = 'default';
}
$general_post_slug_prefix     = isset( $opts['general_post_slug_prefix'] ) ? (string) $opts['general_post_slug_prefix'] : '';
$general_remove_category_base = ! empty( $opts['general_remove_category_base'] );

$modules = [
	'faq' => [
		'key'         => 'enable_faq',
		'name'        => __( 'FAQ System', 'site-essentials' ),
		'tier'        => 'basic',
		'description' => __( 'Reusable FAQ entries with parent/child grouping, FAQPage schema in the unified site graph, and a Gutenberg selector block. Tag FAQs with your topic vocabulary for topical coverage reporting.', 'site-essentials' ),
		'enabled'     => $faq_enabled,
		'tab'         => 'faq',
		'guide'       => $guide_faq,
	],
	'projects' => [
		'key'         => 'customer_success_stories',
		'name'        => __( 'Success Stories (Projects)', 'site-essentials' ),
		'tier'        => 'pro',
		'description' => __( 'Display high-impact case studies and project portfolios with advanced filtering and editorial grids.', 'site-essentials' ),
		'enabled'     => $projects_on,
		'tab'         => 'projects',
		'guide'       => $guide_projects,
	],
	'reviews' => [
		'key'         => 'enable_reviews',
		'name'        => __( 'Review System', 'site-essentials' ),
		'tier'        => 'basic',
		'description' => __( 'Integrate structured customer feedback directly into your search engine results with ease.', 'site-essentials' ),
		'enabled'     => $reviews_on,
		'tab'         => 'reviews',
		'guide'       => $guide_reviews,
	],
	'author' => [
		'key'         => 'enable_author_extension',
		'name'        => __( 'Author Extension', 'site-essentials' ),
		'tier'        => 'basic',
		'description' => __( 'Extend user profiles with social links, expert biographies, and custom taxonomy credentials for stronger E-E-A-T signals.', 'site-essentials' ),
		'enabled'     => $author_on,
		'tab'         => 'author',
		'guide'       => $guide_author,
	],
];
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'site_essentials_cpt', 'site_essentials_cpt_nonce' ); ?>
	<input type="hidden" name="action" value="site_essentials_save_cpt">
	<input type="hidden" name="_scos_cpt_tab" value="settings">

	<p class="scos__section-label"><?php esc_html_e( 'Feature modules', 'site-essentials' ); ?></p>

	<div class="scos-modules">
		<?php foreach ( $modules as $id => $mod ) :
			$is_on = ! empty( $mod['enabled'] );
			$card_cls = 'scos-module' . ( $is_on ? ' scos-module--on' : '' );
		?>
			<div class="<?php echo esc_attr( $card_cls ); ?>" data-module-id="<?php echo esc_attr( $id ); ?>">
				<div class="scos-module__head">
					<div>
						<div class="scos-module__meta">
							<span class="scos-badge scos-badge--<?php echo esc_attr( $mod['tier'] ); ?>">
								<?php echo esc_html( strtoupper( $mod['tier'] ) ); ?>
							</span>
						</div>
						<h3 class="scos-module__title"><?php echo esc_html( $mod['name'] ); ?></h3>
						<p class="scos-module__desc"><?php echo esc_html( $mod['description'] ); ?></p>
					</div>

					<label class="scos-toggle">
						<input type="checkbox"
							name="cpt_options[<?php echo esc_attr( $mod['key'] ); ?>]"
							value="1"
							<?php checked( $is_on ); ?>>
						<span class="scos-toggle__track"></span>
					</label>
				</div>

				<div class="scos-module__divider"></div>

				<div class="scos-module__foot">
					<?php if ( $is_on ) : ?>
						<span class="scos-module__status scos-module__status--on">
							<span class="dashicons dashicons-yes-alt" style="font-size:14px;vertical-align:middle;margin-top:-2px;"></span>
							<?php esc_html_e( 'Loaded', 'site-essentials' ); ?>
						</span>
					<?php else : ?>
						<span class="scos-module__status scos-module__status--off">
							<?php esc_html_e( 'Disabled', 'site-essentials' ); ?>
						</span>
					<?php endif; ?>

					<div class="scos-module__links">
						<a href="<?php echo esc_url( $mod['guide'] ); ?>" class="scos-module__link"
						   target="_blank" rel="noopener">
							<?php esc_html_e( 'Guide ↗', 'site-essentials' ); ?>
						</a>
						<?php if ( $is_on ) : ?>
							<a href="<?php echo esc_url( add_query_arg( [ 'page' => \SiteEssentials\Core\Admin_UI::CPT_PAGE_SLUG, 'tab' => $mod['tab'] ], admin_url( 'admin.php' ) ) ); ?>"
							   class="scos-module__link">
								<?php esc_html_e( 'Settings', 'site-essentials' ); ?>
							</a>
						<?php endif; ?>
					</div>
				</div>
			</div>
		<?php endforeach; ?>
	</div>

	<p class="scos__section-label" style="margin-top:24px;"><?php esc_html_e( 'General — default WP posts', 'site-essentials' ); ?></p>

	<div class="scos-card">
		<div class="scos-card__header">
			<h2 class="scos-card__title"><?php esc_html_e( 'Default post permalinks', 'site-essentials' ); ?></h2>
			<p class="scos-card__desc">
				<?php esc_html_e( 'These options apply to WordPress default posts only (post type "post"), not to FAQs, Projects, or other CPTs.', 'site-essentials' ); ?>
			</p>
		</div>
		<div class="scos-card__body">
			<table class="scos-form">
				<tbody>
					<tr>
						<th>
							<label><?php esc_html_e( 'Single post URL', 'site-essentials' ); ?></label>
							<div class="scos-form__slug">cpt_options[post_link_mode]</div>
						</th>
						<td>
							<fieldset>
								<label style="display:block;margin-bottom:10px;">
									<input type="radio" name="cpt_options[post_link_mode]" value="default" <?php checked( $post_link_mode, 'default' ); ?> />
									<?php esc_html_e( 'Default — use your site Permalink settings (no extra segment from Site Essentials).', 'site-essentials' ); ?>
								</label>
								<label style="display:block;margin-bottom:10px;">
									<input type="radio" name="cpt_options[post_link_mode]" value="custom_prefix" <?php checked( $post_link_mode, 'custom_prefix' ); ?> />
									<?php esc_html_e( 'Single custom slug prefix for all posts', 'site-essentials' ); ?>
									<input type="text" name="cpt_options[general_post_slug_prefix]" id="scos_cpt_general_post_prefix"
										value="<?php echo esc_attr( $general_post_slug_prefix ); ?>"
										class="scos-input scos-input--mono"
										style="max-width:240px;margin-left:8px;display:inline-block;"
										placeholder="<?php esc_attr_e( 'e.g. blog', 'site-essentials' ); ?>" />
								</label>
								<p class="description" style="margin:4px 0 10px 28px;">
									<?php esc_html_e( 'Example: prefix "blog" → example.com/blog/my-post/. Saves a rewrite rule; flush runs when you save this page.', 'site-essentials' ); ?>
								</p>
								<label style="display:block;margin-bottom:10px;">
									<input type="radio" name="cpt_options[post_link_mode]" value="category_prefix" <?php checked( $post_link_mode, 'category_prefix' ); ?> />
									<?php esc_html_e( 'Use the first assigned category as the path segment before the post slug', 'site-essentials' ); ?>
								</label>
								<p class="description" style="margin:4px 0 0 28px;">
									<?php esc_html_e( 'Outbound links use the first assigned category (order on the post), or the default category when none is set. A rewrite rule is added per category slug so those URLs resolve; rules refresh when you save this page or when categories change. Pretty permalinks must stay enabled under Settings → Permalinks.', 'site-essentials' ); ?>
								</p>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th>
							<label for="cpt_general_remove_category_base"><?php esc_html_e( 'Category URLs', 'site-essentials' ); ?></label>
							<div class="scos-form__slug">cpt_options[general_remove_category_base]</div>
						</th>
						<td>
							<label class="scos-checkbox-row">
								<input type="checkbox" id="cpt_general_remove_category_base" name="cpt_options[general_remove_category_base]" value="1" <?php checked( $general_remove_category_base ); ?> />
								<?php esc_html_e( 'Remove the default "category" base from category archive permalinks', 'site-essentials' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Adjusts rewrite rules and generated category links. Test category archives after enabling; save this page to flush rewrites.', 'site-essentials' ); ?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<div class="scos-card__footer">
			<button type="submit" class="scos-btn scos-btn--primary">
				<?php esc_html_e( 'Save changes', 'site-essentials' ); ?>
			</button>
		</div>
	</div>
</form>
