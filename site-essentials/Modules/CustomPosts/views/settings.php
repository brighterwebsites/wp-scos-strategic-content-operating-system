<?php
/**
 * Custom Posts — Settings View
 *
 * @package    SiteEssentials
 * @subpackage Modules\CustomPosts
 * @version    2.2.0
 *
 * Variables available:
 * @var array $opts Current CPT options
 */

defined( 'ABSPATH' ) || exit;

// Card states follow saved CPT module options (init() syncs bw_author_extension_enabled from these on each load).
$author_extension_enabled = ! empty( $opts['enable_author_extension'] );
$faq_enabled              = ! empty( $opts['enable_faq'] );
$projects_enabled         = ! empty( $opts['customer_success_stories'] );
$reviews_enabled          = ! empty( $opts['enable_reviews'] );

$post_link_mode = isset( $opts['post_link_mode'] ) ? sanitize_key( (string) $opts['post_link_mode'] ) : 'default';
if ( ! in_array( $post_link_mode, [ 'default', 'custom_prefix', 'category_prefix' ], true ) ) {
	$post_link_mode = 'default';
}
$general_post_slug_prefix     = isset( $opts['general_post_slug_prefix'] ) ? (string) $opts['general_post_slug_prefix'] : '';
$general_remove_category_base = ! empty( $opts['general_remove_category_base'] );

// Import result notices
$import_status = isset( $_GET['reviews_import'] ) ? sanitize_text_field( $_GET['reviews_import'] ) : '';
if ( $import_status === 'success' ) {
	$imported = isset( $_GET['imported'] ) ? intval( $_GET['imported'] ) : 0;
	$skipped  = isset( $_GET['skipped'] )  ? intval( $_GET['skipped'] )  : 0;
	echo '<div class="notice notice-success is-dismissible"><p>' . sprintf( esc_html__( '%1$d reviews imported, %2$d skipped.', 'site-essentials' ), $imported, $skipped ) . '</p></div>';
} elseif ( $import_status === 'error' ) {
	$err_msg = isset( $_GET['error_msg'] ) ? rawurldecode( sanitize_text_field( $_GET['error_msg'] ) ) : __( 'Import failed.', 'site-essentials' );
	echo '<div class="notice notice-error is-dismissible"><p><strong>' . esc_html__( 'Import error:', 'site-essentials' ) . '</strong> ' . esc_html( $err_msg ) . '</p></div>';
}

// Module definitions
$modules = [
	'faq' => [
		'key'         => 'enable_faq',
		'name'        => __( 'FAQ System', 'site-essentials' ),
		'tier'        => 'basic',
		'description' => __( 'Reusable FAQ entries with parent/child grouping, FAQPage schema, and a Gutenberg selector block. Tag FAQs with your topic vocabulary for topical coverage reporting.', 'site-essentials' ),
		'enabled'     => $faq_enabled,
		'disabled'    => false,
		'coming_soon' => false,
		'settings_id' => 'scos-cpt-faq-settings',
	],
	'projects' => [
		'key'         => 'customer_success_stories',
		'name'        => __( 'Success Stories (Projects)', 'site-essentials' ),
		'tier'        => 'pro',
		'description' => __( 'Display high-impact case studies and project portfolios with advanced filtering and editorial grids.', 'site-essentials' ),
		'enabled'     => $projects_enabled,
		'disabled'    => false,
		'coming_soon' => false,
		'settings_id' => 'scos-cpt-projects-settings',
	],
	'reviews' => [
		'key'         => 'enable_reviews',
		'name'        => __( 'Review System', 'site-essentials' ),
		'tier'        => 'basic',
		'description' => __( 'Integrate structured customer feedback directly into your search engine results with ease.', 'site-essentials' ),
		'enabled'     => $reviews_enabled,
		'disabled'    => false,
		'coming_soon' => false,
		'settings_id' => 'scos-cpt-reviews-settings',
	],
	'author' => [
		'key'         => 'enable_author_extension',
		'name'        => __( 'Author Extension', 'site-essentials' ),
		'tier'        => 'basic',
		'description' => __( 'Extend user profiles with social links, expert biographies, and custom taxonomy credentials.', 'site-essentials' ),
		'enabled'     => $author_extension_enabled,
		'disabled'    => false,
		'coming_soon' => false,
		'settings_id' => 'scos-cpt-author-settings',
	],
];
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'site_essentials_cpt', 'site_essentials_cpt_nonce' ); ?>
	<input type="hidden" name="action" value="site_essentials_save_cpt">

	<!-- ── General Settings (not a module card) ─────────────────────────── -->
	<div id="scos-cpt-general-settings" class="scos-cpt-section" style="border-top:0;padding-top:0;margin-bottom:28px;">
		<h2 style="margin:0 0 12px;font-size:18px;"><?php esc_html_e( 'General Settings', 'site-essentials' ); ?></h2>
		<p class="description" style="margin:0 0 16px;max-width:820px;">
			<?php esc_html_e( 'These options apply to WordPress default posts only (post type “post”), not to Projects, FAQs, or other CPTs.', 'site-essentials' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Single post URL', 'site-essentials' ); ?></th>
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
								class="regular-text code" style="max-width:240px;margin-left:8px;"
								placeholder="<?php esc_attr_e( 'e.g. blog', 'site-essentials' ); ?>" />
						</label>
						<p class="description" style="margin:4px 0 10px 28px;">
							<?php esc_html_e( 'Example: prefix “blog” → example.com/blog/my-post/. Saves a rewrite rule; flush runs when you save this page.', 'site-essentials' ); ?>
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
				<th scope="row"><?php esc_html_e( 'Category URLs', 'site-essentials' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="cpt_options[general_remove_category_base]" value="1" <?php checked( $general_remove_category_base ); ?> />
						<?php esc_html_e( 'Remove the default “category” base from category archive permalinks', 'site-essentials' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Adjusts rewrite rules and generated category links. Test category archives after enabling; save this page to flush rewrites.', 'site-essentials' ); ?>
					</p>
				</td>
			</tr>
		</table>
	</div>

	<p style="color:#646970;margin:0 0 20px;">
		<?php esc_html_e( 'Enable or disable the feature modules below. Sub-pages appear when a module is on.', 'site-essentials' ); ?>
	</p>

	<!-- ── Module Cards Grid ── -->
	<div class="site-essentials-modules" style="margin-bottom:28px;">
		<?php foreach ( $modules as $id => $mod ) :
			$is_loaded = $mod['enabled'] && ! $mod['coming_soon'];
			$card_cls  = 'se-module-card' . ( $mod['enabled'] ? ' enabled' : '' );
		?>
		<div class="<?php echo esc_attr( $card_cls ); ?>">
			<div class="se-module-header">
				<div class="se-module-title">
					<span class="se-module-tier tier-<?php echo esc_attr( $mod['tier'] ); ?>">
						<?php echo esc_html( strtoupper( $mod['tier'] ) ); ?>
					</span>
					<h3 style="margin-top:8px;"><?php echo esc_html( $mod['name'] ); ?></h3>
				</div>

				<label class="se-toggle" title="<?php echo $mod['coming_soon'] ? esc_attr__( 'Coming soon', 'site-essentials' ) : ''; ?>">
					<input type="checkbox"
						name="cpt_options[<?php echo esc_attr( $mod['key'] ); ?>]"
						value="1"
						<?php checked( $mod['enabled'] ); ?>
						<?php disabled( $mod['disabled'] ); ?>>
					<span class="se-toggle-slider"></span>
				</label>
			</div>

			<div class="se-module-body">
				<p class="se-module-description"><?php echo esc_html( $mod['description'] ); ?></p>

				<div class="se-module-status" style="display:flex;justify-content:space-between;align-items:center;">
					<?php if ( $mod['coming_soon'] ) : ?>
						<span class="status-indicator disabled">
							<span class="dashicons dashicons-clock" style="font-size:14px;vertical-align:middle;margin-top:-2px;"></span>
							<?php esc_html_e( 'Coming soon', 'site-essentials' ); ?>
						</span>
					<?php elseif ( $is_loaded ) : ?>
						<span class="status-indicator loaded">
							<span class="dashicons dashicons-yes-alt" style="font-size:14px;vertical-align:middle;margin-top:-2px;"></span>
							<?php esc_html_e( '✓ Loaded', 'site-essentials' ); ?>
						</span>
					<?php else : ?>
						<span class="status-indicator disabled">
							<?php esc_html_e( 'Disabled', 'site-essentials' ); ?>
						</span>
					<?php endif; ?>

					<?php if ( $mod['settings_id'] && $mod['enabled'] ) : ?>
						<a href="#<?php echo esc_attr( $mod['settings_id'] ); ?>"
						   style="font-size:13px;text-decoration:none;color:#2271b1;">
							<?php esc_html_e( 'Settings', 'site-essentials' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php endforeach; ?>
	</div>

	<p class="submit" style="margin-bottom:32px;">
		<button type="submit" class="button button-primary">
			<?php esc_html_e( 'Save Settings', 'site-essentials' ); ?>
		</button>
	</p>

	<!-- ── FAQ Settings ── -->
	<?php if ( $faq_enabled ) :
		$faq_count = wp_count_posts( 'faq' );
		$faq_total = isset( $faq_count->publish ) ? (int) $faq_count->publish : 0;
	?>
	<div id="scos-cpt-faq-settings" class="scos-cpt-section">
		<h2><?php esc_html_e( 'FAQ System', 'site-essentials' ); ?></h2>
		<p class="description" style="margin-bottom:16px;">
			<?php esc_html_e( 'FAQs are reusable content snippets. Add them to any page or post using the', 'site-essentials' ); ?>
			<strong><?php esc_html_e( 'FAQ Selector', 'site-essentials' ); ?></strong>
			<?php esc_html_e( 'Gutenberg block or the', 'site-essentials' ); ?>
			<code>[faqs ids="1,2,3"]</code>
			<?php esc_html_e( 'shortcode. FAQPage schema is output automatically.', 'site-essentials' ); ?>
		</p>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Published FAQs', 'site-essentials' ); ?></th>
				<td>
					<strong><?php echo esc_html( $faq_total ); ?></strong>
					<?php if ( $faq_total > 0 ) : ?>
						&nbsp;—&nbsp;
						<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=faq' ) ); ?>">
							<?php esc_html_e( 'View all FAQs', 'site-essentials' ); ?>
						</a>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Manage', 'site-essentials' ); ?></th>
				<td>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=faq' ) ); ?>" class="button button-secondary">
						<?php esc_html_e( 'All FAQs', 'site-essentials' ); ?>
					</a>
					&nbsp;
					<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=faq' ) ); ?>" class="button button-secondary">
						<?php esc_html_e( 'Add New FAQ', 'site-essentials' ); ?>
					</a>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Parent / Child', 'site-essentials' ); ?></th>
				<td>
					<p class="description">
						<?php esc_html_e( 'FAQs support parent/child nesting. Use the "Parent FAQ" field in the editor to group related questions — e.g. set a category-level FAQ as the parent of specific follow-up FAQs.', 'site-essentials' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Primary Topic', 'site-essentials' ); ?></th>
				<td>
					<p class="description">
						<?php esc_html_e( 'Assign each FAQ a Primary Topic (from your scos_topic vocabulary) to power topical coverage reporting and link suggestions in the Content Architecture module.', 'site-essentials' ); ?>
					</p>
					<a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=scos_topic&post_type=faq' ) ); ?>" style="font-size:13px;">
						<?php esc_html_e( 'Manage Topics', 'site-essentials' ); ?>
					</a>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Gutenberg Block', 'site-essentials' ); ?></th>
				<td>
					<p class="description">
						<?php esc_html_e( 'Search for "FAQ Selector" in the block inserter. Choose display format (accordion or plain), heading level, and whether to inject FAQPage schema.', 'site-essentials' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<h3 style="margin-top:20px;"><?php esc_html_e( 'Archive &amp; URL Settings', 'site-essentials' ); ?></h3>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable FAQ Archive', 'site-essentials' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="scos_faq[archive_enabled]" value="1"
							<?php checked( get_option( 'scos_faq_archive_enabled', false ) ); ?>>
						<?php esc_html_e( 'Enable the /faq/ archive page', 'site-essentials' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'When off, /faq/ redirects to the URL below (or returns 404 if left empty).', 'site-essentials' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="scos_faq_archive_redirect"><?php esc_html_e( 'Redirect /faq/ to', 'site-essentials' ); ?></label>
				</th>
				<td>
					<input type="url" id="scos_faq_archive_redirect" name="scos_faq[archive_redirect]"
						value="<?php echo esc_attr( get_option( 'scos_faq_archive_redirect', '' ) ); ?>"
						class="regular-text" placeholder="https://example.com/frequently-asked-questions/">
					<p class="description">
						<?php esc_html_e( 'Only used when the archive is disabled. Leave empty to return a 404.', 'site-essentials' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="scos_faq_topic_redirect"><?php esc_html_e( 'Redirect /faq/topic/ to', 'site-essentials' ); ?></label>
				</th>
				<td>
					<input type="url" id="scos_faq_topic_redirect" name="scos_faq[topic_redirect]"
						value="<?php echo esc_attr( get_option( 'scos_faq_topic_redirect', '' ) ); ?>"
						class="regular-text" placeholder="<?php echo esc_attr( home_url( '/faq/' ) ); ?>">
					<p class="description">
						<?php esc_html_e( 'Where to send visitors who land on a topic-folder URL (e.g. /faq/pricing/). Defaults to /faq/ if empty.', 'site-essentials' ); ?>
					</p>
				</td>
			</tr>
		</table>
	</div>
	<?php endif; ?>

	<!-- ── Projects Settings ── -->
	<?php if ( $projects_enabled ) : ?>
	<div id="scos-cpt-projects-settings" class="scos-cpt-section">
		<h2><?php esc_html_e( 'Success Stories (Projects)', 'site-essentials' ); ?></h2>
		<p class="description" style="margin-bottom:16px;">
			<?php esc_html_e( 'Configuration for the Projects/Case Studies custom post type.', 'site-essentials' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="cpt_include_categories"><?php esc_html_e( 'WordPress Categories', 'site-essentials' ); ?></label></th>
				<td>
					<label>
						<input type="checkbox" id="cpt_include_categories" name="cpt_options[include_categories]" value="1" <?php checked( ! empty( $opts['include_categories'] ) ); ?>>
						<?php esc_html_e( 'Use WordPress Categories for Project posts', 'site-essentials' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cpt_include_tags"><?php esc_html_e( 'WordPress Tags', 'site-essentials' ); ?></label></th>
				<td>
					<label>
						<input type="checkbox" id="cpt_include_tags" name="cpt_options[include_tags]" value="1" <?php checked( ! empty( $opts['include_tags'] ) ); ?>>
						<?php esc_html_e( 'Use WordPress Tags for Project posts', 'site-essentials' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cpt_archive_slug"><?php esc_html_e( 'Archive Slug', 'site-essentials' ); ?></label></th>
				<td>
					<input type="text" id="cpt_archive_slug" name="cpt_options[archive_slug]"
						value="<?php echo esc_attr( ! empty( $opts['archive_slug'] ) ? $opts['archive_slug'] : 'projects' ); ?>"
						class="regular-text" placeholder="projects">
					<p class="description">
						<?php esc_html_e( 'Sets the archive URL slug. Derives the admin menu label and breadcrumb automatically (e.g. "customer-success-stories" → "Customer Success Stories"). Rewrite rules are flushed on save.', 'site-essentials' ); ?>
					</p>
					<p class="description">
						<strong><?php esc_html_e( '⚠ Changing the slug on a live site will break existing URLs — set up redirects first.', 'site-essentials' ); ?></strong>
					</p>
				</td>
			</tr>
		</table>
	</div>
	<?php endif; ?>

	<!-- ── Reviews Settings ── -->
	<?php if ( $reviews_enabled ) : ?>
	<div id="scos-cpt-reviews-settings" class="scos-cpt-section">
		<h2><?php esc_html_e( 'Review System', 'site-essentials' ); ?></h2>
		<p class="description" style="margin-bottom:12px;">
			<?php esc_html_e( 'Reviews are a structured data source (SSOT). No public archive or single URLs — query via WP_Query using post_type=bw_reviews, combined with tax_query (bw_review_platform) and meta_query.', 'site-essentials' ); ?>
			<?php printf( esc_html__( 'Manage reviews in %1$sReviews → All Reviews%2$s.', 'site-essentials' ), '<a href="' . esc_url( admin_url( 'edit.php?post_type=bw_reviews' ) ) . '">', '</a>' ); ?>
		</p>
		<p class="description">
			<?php esc_html_e( 'Recommendation: Exclude from sitemap, add /reviews/ to robots.txt.', 'site-essentials' ); ?>
		</p>
	</div>
	<?php endif; ?>

	<!-- ── Author Extension Settings ── -->
	<?php if ( $author_extension_enabled ) : ?>
	<div id="scos-cpt-author-settings" class="scos-cpt-section">
		<h2><?php esc_html_e( 'Author Extension', 'site-essentials' ); ?></h2>
		<p class="description" style="margin-bottom:16px;">
			<?php esc_html_e( 'Extended author metadata for E-E-A-T signals and structured Person schema. Fields are added to user profiles.', 'site-essentials' ); ?>
		</p>
		<p>
			<a href="<?php echo esc_url( admin_url( 'users.php' ) ); ?>" class="button button-secondary">
				<?php esc_html_e( 'Manage Authors', 'site-essentials' ); ?>
			</a>
			<span style="margin-left:10px;color:#646970;font-size:13px;">
				<?php esc_html_e( 'Edit user profiles to add job title, organisation, LinkedIn URL, expert biography, etc.', 'site-essentials' ); ?>
			</span>
		</p>
	</div>
	<?php endif; ?>

</form>

<!-- ── Reviews CSV Import (separate form — needs multipart) ── -->
<?php if ( $reviews_enabled ) : ?>
<div id="scos-cpt-reviews-import" class="scos-cpt-section" style="margin-top:32px;">
	<h2><?php esc_html_e( 'Import Reviews from CSV', 'site-essentials' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Upload a CSV to bulk-import reviews. Rows where customer_name is empty are skipped. Platform terms are matched by name (case-insensitive) — new terms are created automatically.', 'site-essentials' ); ?>
	</p>
	<p><strong><?php esc_html_e( 'Required CSV headers (case-sensitive):', 'site-essentials' ); ?></strong></p>
	<code style="display:block;padding:8px 12px;background:#f6f7f7;border:1px solid #ddd;border-radius:4px;margin-bottom:16px;font-size:12px;word-break:break-all;">
		customer_name, review_text, rating, platform, date, date_precision, verify_url, success_outcome, customer_detail, is_featured, review_excerpt
	</code>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
		<?php wp_nonce_field( 'bw_reviews_csv_import', 'bw_reviews_csv_nonce' ); ?>
		<input type="hidden" name="action" value="site_essentials_reviews_csv_import">
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="bw_reviews_csv"><?php esc_html_e( 'CSV File', 'site-essentials' ); ?></label></th>
				<td>
					<input type="file" id="bw_reviews_csv" name="bw_reviews_csv" accept=".csv,text/csv">
					<p class="description"><?php esc_html_e( 'Upload a .csv file with the headers listed above.', 'site-essentials' ); ?></p>
				</td>
			</tr>
		</table>
		<button type="submit" class="button button-secondary">
			<?php esc_html_e( 'Import Reviews from CSV', 'site-essentials' ); ?>
		</button>
	</form>
</div>
<?php endif; ?>

<style>
.scos-cpt-section {
	border-top: 1px solid #e0e0e0;
	padding-top: 24px;
	margin-bottom: 28px;
}
.scos-cpt-section h2 {
	font-size: 16px;
	font-weight: 600;
	margin: 0 0 8px;
	color: #1d2327;
}
.scos-cpt-section .form-table th {
	width: 220px;
}
</style>
