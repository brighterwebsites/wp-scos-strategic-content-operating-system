<?php
/**
 * CPT — FAQ System tab.
 *
 * v1.0 | 2026-05-19
 *
 * Variables in scope from cpt-page.php:
 * @var array $opts
 * @var string $guide_faq
 *
 * @package    SiteEssentials
 * @subpackage Modules\CustomPosts
 */

defined( 'ABSPATH' ) || exit;

$faq_count_obj = wp_count_posts( 'faq' );
$faq_total     = isset( $faq_count_obj->publish ) ? (int) $faq_count_obj->publish : 0;

$archive_enabled  = (bool) get_option( 'scos_faq_archive_enabled', false );
$archive_redirect = (string) get_option( 'scos_faq_archive_redirect', '' );
$topic_redirect   = (string) get_option( 'scos_faq_topic_redirect', '' );

// SchemaDance compatibility check — `is_plugin_active` lives in wp-admin/includes/plugin.php
// which is NOT always loaded in mu-plugin context, so we load it here once.
if ( ! function_exists( 'is_plugin_active' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
$schemadance_active = function_exists( 'is_plugin_active' ) && is_plugin_active( 'schemadance/schemadance.php' );
?>

<?php if ( $schemadance_active ) : ?>
	<div class="scos-notice scos-notice--warning" role="alert">
		<strong class="scos-notice__title"><?php esc_html_e( 'SchemaDance plugin detected', 'site-essentials' ); ?></strong>
		<p>
			<?php esc_html_e( 'Site Essentials already emits a unified FAQPage JSON-LD entry via the site schema graph. The SchemaDance plugin will inject a second, conflicting FAQPage block on the same page. Deactivate SchemaDance to avoid duplicate schema.', 'site-essentials' ); ?>
		</p>
		<p>
			<a href="<?php echo esc_url( admin_url( 'plugins.php?s=schemadance' ) ); ?>" class="scos-btn scos-btn--ghost">
				<?php esc_html_e( 'Open Plugins page', 'site-essentials' ); ?>
			</a>
		</p>
	</div>
<?php endif; ?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'site_essentials_cpt', 'site_essentials_cpt_nonce' ); ?>
	<input type="hidden" name="action" value="site_essentials_save_cpt">
	<input type="hidden" name="_scos_cpt_tab" value="faq">

	<?php // Preserve submodule toggles so submit doesn't accidentally disable them ?>
	<input type="hidden" name="cpt_options[enable_faq]" value="<?php echo ! empty( $opts['enable_faq'] ) ? '1' : '0'; ?>">
	<input type="hidden" name="cpt_options[customer_success_stories]" value="<?php echo ! empty( $opts['customer_success_stories'] ) ? '1' : '0'; ?>">
	<input type="hidden" name="cpt_options[enable_reviews]" value="<?php echo ! empty( $opts['enable_reviews'] ) ? '1' : '0'; ?>">
	<input type="hidden" name="cpt_options[enable_author_extension]" value="<?php echo ! empty( $opts['enable_author_extension'] ) ? '1' : '0'; ?>">
	<input type="hidden" name="cpt_options[include_categories]" value="<?php echo ! empty( $opts['include_categories'] ) ? '1' : '0'; ?>">
	<input type="hidden" name="cpt_options[include_tags]" value="<?php echo ! empty( $opts['include_tags'] ) ? '1' : '0'; ?>">
	<input type="hidden" name="cpt_options[archive_slug]" value="<?php echo esc_attr( isset( $opts['archive_slug'] ) ? (string) $opts['archive_slug'] : 'projects' ); ?>">
	<input type="hidden" name="cpt_options[post_link_mode]" value="<?php echo esc_attr( isset( $opts['post_link_mode'] ) ? (string) $opts['post_link_mode'] : 'default' ); ?>">
	<input type="hidden" name="cpt_options[general_post_slug_prefix]" value="<?php echo esc_attr( isset( $opts['general_post_slug_prefix'] ) ? (string) $opts['general_post_slug_prefix'] : '' ); ?>">
	<input type="hidden" name="cpt_options[general_remove_category_base]" value="<?php echo ! empty( $opts['general_remove_category_base'] ) ? '1' : '0'; ?>">

	<div class="scos-card">
		<div class="scos-card__header">
			<h2 class="scos-card__title"><?php esc_html_e( 'FAQ System', 'site-essentials' ); ?></h2>
			<p class="scos-card__desc">
				<?php esc_html_e( 'FAQs are reusable content snippets. Add them to any page or post using the FAQ Selector Gutenberg block or the', 'site-essentials' ); ?>
				<code>[faqs ids="1,2,3"]</code>
				<?php esc_html_e( 'shortcode. FAQPage schema is contributed to the unified site schema graph automatically.', 'site-essentials' ); ?>
			</p>
		</div>
		<div class="scos-card__body">
			<table class="scos-form">
				<tbody>
					<tr>
						<th><?php esc_html_e( 'Published FAQs', 'site-essentials' ); ?></th>
						<td>
							<strong style="font-size:18px;"><?php echo esc_html( (string) $faq_total ); ?></strong>
							<?php if ( $faq_total > 0 ) : ?>
								&nbsp;—&nbsp;
								<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=faq' ) ); ?>">
									<?php esc_html_e( 'View all FAQs', 'site-essentials' ); ?>
								</a>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Manage', 'site-essentials' ); ?></th>
						<td>
							<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=faq' ) ); ?>" class="scos-btn scos-btn--ghost">
								<?php esc_html_e( 'All FAQs', 'site-essentials' ); ?>
							</a>
							&nbsp;
							<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=faq' ) ); ?>" class="scos-btn scos-btn--ghost">
								<?php esc_html_e( 'Add New FAQ', 'site-essentials' ); ?>
							</a>
							&nbsp;
							<a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=scos_topic&post_type=faq' ) ); ?>" class="scos-btn scos-btn--ghost">
								<?php esc_html_e( 'Manage Topics', 'site-essentials' ); ?>
							</a>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Parent / Child', 'site-essentials' ); ?></th>
						<td>
							<p class="description">
								<?php esc_html_e( 'FAQs support parent/child nesting. Use the "Parent FAQ" field in the editor to group related questions — e.g. set a category-level FAQ as the parent of specific follow-up FAQs.', 'site-essentials' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Primary Topic', 'site-essentials' ); ?></th>
						<td>
							<p class="description">
								<?php esc_html_e( 'Assign each FAQ a Primary Topic (from your scos_topic vocabulary) to power topical coverage reporting and link suggestions in the Content Architecture module.', 'site-essentials' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Gutenberg Block', 'site-essentials' ); ?></th>
						<td>
							<p class="description">
								<?php esc_html_e( 'Search for "FAQ Selector" in the block inserter. Choose display format (accordion or plain), heading level, and whether to merge selected FAQs into the page FAQPage schema.', 'site-essentials' ); ?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>

	<div class="scos-card">
		<div class="scos-card__header">
			<h2 class="scos-card__title"><?php esc_html_e( 'Archive & URL settings', 'site-essentials' ); ?></h2>
			<p class="scos-card__desc">
				<?php esc_html_e( 'Control whether /faq/ behaves as an archive, and where topic-folder URLs redirect to.', 'site-essentials' ); ?>
			</p>
		</div>
		<div class="scos-card__body">
			<table class="scos-form">
				<tbody>
					<tr>
						<th>
							<label for="scos_faq_archive_enabled"><?php esc_html_e( 'Enable FAQ archive', 'site-essentials' ); ?></label>
							<div class="scos-form__slug">scos_faq_archive_enabled</div>
						</th>
						<td>
							<label class="scos-checkbox-row">
								<input type="checkbox" id="scos_faq_archive_enabled" name="scos_faq[archive_enabled]" value="1" <?php checked( $archive_enabled ); ?>>
								<?php esc_html_e( 'Enable the /faq/ archive page', 'site-essentials' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'When off, /faq/ redirects to the URL below (or returns 404 if left empty).', 'site-essentials' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th>
							<label for="scos_faq_archive_redirect"><?php esc_html_e( 'Redirect /faq/ to', 'site-essentials' ); ?></label>
							<div class="scos-form__slug">scos_faq_archive_redirect</div>
						</th>
						<td>
							<input type="url" id="scos_faq_archive_redirect" name="scos_faq[archive_redirect]"
								value="<?php echo esc_attr( $archive_redirect ); ?>"
								class="scos-input"
								placeholder="https://example.com/frequently-asked-questions/">
							<p class="description">
								<?php esc_html_e( 'Only used when the archive is disabled. Leave empty to return a 404.', 'site-essentials' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th>
							<label for="scos_faq_topic_redirect"><?php esc_html_e( 'Redirect /faq/topic/ to', 'site-essentials' ); ?></label>
							<div class="scos-form__slug">scos_faq_topic_redirect</div>
						</th>
						<td>
							<input type="url" id="scos_faq_topic_redirect" name="scos_faq[topic_redirect]"
								value="<?php echo esc_attr( $topic_redirect ); ?>"
								class="scos-input"
								placeholder="<?php echo esc_attr( home_url( '/faq/' ) ); ?>">
							<p class="description">
								<?php esc_html_e( 'Where to send visitors who land on a topic-folder URL (e.g. /faq/pricing/). Defaults to /faq/ if empty.', 'site-essentials' ); ?>
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
