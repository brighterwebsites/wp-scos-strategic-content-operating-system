<?php
/**
 * CPT — Author Extension tab.
 *
 * v1.0 | 2026-05-19
 *
 * Variables in scope from cpt-page.php:
 * @var array  $opts
 * @var string $guide_author
 *
 * @package    SiteEssentials
 * @subpackage Modules\CustomPosts
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="scos-card">
	<div class="scos-card__header">
		<h2 class="scos-card__title"><?php esc_html_e( 'Author Extension', 'site-essentials' ); ?></h2>
		<p class="scos-card__desc">
			<?php esc_html_e( 'Extended author metadata for E-E-A-T signals and structured Person schema. Fields are added to user profiles.', 'site-essentials' ); ?>
		</p>
	</div>
	<div class="scos-card__body">
		<p>
			<a href="<?php echo esc_url( admin_url( 'users.php' ) ); ?>" class="scos-btn scos-btn--ghost">
				<?php esc_html_e( 'Manage Authors', 'site-essentials' ); ?>
			</a>
		</p>
		<p class="description">
			<?php esc_html_e( 'Edit user profiles to add job title, organisation, LinkedIn URL, expert biography, and other E-E-A-T fields.', 'site-essentials' ); ?>
		</p>
	</div>
</div>
