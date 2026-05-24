<?php
/**
 * CPT — Success Stories (Projects) tab.
 *
 * v1.0 | 2026-05-19
 *
 * Variables in scope from cpt-page.php:
 * @var array  $opts
 * @var string $guide_projects
 *
 * @package    SiteEssentials
 * @subpackage Modules\CustomPosts
 */

defined( 'ABSPATH' ) || exit;

$archive_slug       = isset( $opts['archive_slug'] )       ? (string) $opts['archive_slug']      : 'projects';
$include_categories = ! empty( $opts['include_categories'] );
$include_tags       = ! empty( $opts['include_tags'] );
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'site_essentials_cpt', 'site_essentials_cpt_nonce' ); ?>
	<input type="hidden" name="action" value="site_essentials_save_cpt">
	<input type="hidden" name="_scos_cpt_tab" value="projects">

	<?php // Preserve other module toggles ?>
	<input type="hidden" name="cpt_options[enable_faq]" value="<?php echo ! empty( $opts['enable_faq'] ) ? '1' : '0'; ?>">
	<input type="hidden" name="cpt_options[customer_success_stories]" value="<?php echo ! empty( $opts['customer_success_stories'] ) ? '1' : '0'; ?>">
	<input type="hidden" name="cpt_options[enable_reviews]" value="<?php echo ! empty( $opts['enable_reviews'] ) ? '1' : '0'; ?>">
	<input type="hidden" name="cpt_options[enable_author_extension]" value="<?php echo ! empty( $opts['enable_author_extension'] ) ? '1' : '0'; ?>">
	<input type="hidden" name="cpt_options[post_link_mode]" value="<?php echo esc_attr( isset( $opts['post_link_mode'] ) ? (string) $opts['post_link_mode'] : 'default' ); ?>">
	<input type="hidden" name="cpt_options[general_post_slug_prefix]" value="<?php echo esc_attr( isset( $opts['general_post_slug_prefix'] ) ? (string) $opts['general_post_slug_prefix'] : '' ); ?>">
	<input type="hidden" name="cpt_options[general_remove_category_base]" value="<?php echo ! empty( $opts['general_remove_category_base'] ) ? '1' : '0'; ?>">

	<div class="scos-card">
		<div class="scos-card__header">
			<h2 class="scos-card__title"><?php esc_html_e( 'Success Stories (Projects)', 'site-essentials' ); ?></h2>
			<p class="scos-card__desc">
				<?php esc_html_e( 'Configuration for the Projects / Case Studies custom post type.', 'site-essentials' ); ?>
			</p>
		</div>
		<div class="scos-card__body">
			<table class="scos-form">
				<tbody>
					<tr>
						<th>
							<label for="cpt_include_categories"><?php esc_html_e( 'WordPress Categories', 'site-essentials' ); ?></label>
							<div class="scos-form__slug">cpt_options[include_categories]</div>
						</th>
						<td>
							<label class="scos-checkbox-row">
								<input type="checkbox" id="cpt_include_categories" name="cpt_options[include_categories]" value="1" <?php checked( $include_categories ); ?>>
								<?php esc_html_e( 'Use WordPress Categories for Project posts', 'site-essentials' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th>
							<label for="cpt_include_tags"><?php esc_html_e( 'WordPress Tags', 'site-essentials' ); ?></label>
							<div class="scos-form__slug">cpt_options[include_tags]</div>
						</th>
						<td>
							<label class="scos-checkbox-row">
								<input type="checkbox" id="cpt_include_tags" name="cpt_options[include_tags]" value="1" <?php checked( $include_tags ); ?>>
								<?php esc_html_e( 'Use WordPress Tags for Project posts', 'site-essentials' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th>
							<label for="cpt_archive_slug"><?php esc_html_e( 'Archive slug', 'site-essentials' ); ?></label>
							<div class="scos-form__slug">cpt_options[archive_slug]</div>
						</th>
						<td>
							<input type="text" id="cpt_archive_slug" name="cpt_options[archive_slug]"
								value="<?php echo esc_attr( $archive_slug ); ?>"
								class="scos-input scos-input--mono"
								placeholder="projects">
							<p class="description">
								<?php esc_html_e( 'Sets the archive URL slug. Derives the admin menu label and breadcrumb automatically (e.g. "customer-success-stories" → "Customer Success Stories"). Rewrite rules are flushed on save.', 'site-essentials' ); ?>
							</p>
							<p class="description">
								<strong><?php esc_html_e( '⚠ Changing the slug on a live site will break existing URLs — set up redirects first.', 'site-essentials' ); ?></strong>
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
