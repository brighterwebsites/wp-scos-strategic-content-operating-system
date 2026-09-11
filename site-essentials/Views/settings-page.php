<?php
/**
 * Settings Page Template
 *
 * v1.1 | 2026-05-19
 *
 * SCOS design system: scos__header, scos__tabs, scos-card per section.
 * Modules tab calls render_modules_section() directly instead of
 * do_settings_sections() to avoid the duplicate module-name list that
 * the Settings API renders as section headings.
 *
 * @package    SiteEssentials
 * @subpackage Views
 */

defined( 'ABSPATH' ) || exit;

use SiteEssentials\Core\Admin_UI;
use SiteEssentials\Core\Module_Loader;
use SiteEssentials\Core\Cache_Helper;

$page_slug   = Admin_UI::SETTINGS_PAGE_SLUG;
$deploy_info = Admin_UI::get_deployment_info();
?>

<div class="wrap scos">

	<header class="scos__header">
		<div>
			<h1 class="scos__title"><?php esc_html_e( 'Settings', 'site-essentials' ); ?></h1>
			<p class="scos__subtitle">
				<?php esc_html_e( 'Site Essentials › Settings', 'site-essentials' ); ?>
				&nbsp;&mdash;&nbsp;v<?php echo esc_html( $deploy_info['version'] ); ?>
				&nbsp;|&nbsp;<code><?php echo esc_html( $deploy_info['commit'] ); ?></code>
				&nbsp;|&nbsp;<?php echo esc_html( $deploy_info['deployed_at'] ); ?>
			</p>
		</div>
	</header>

	<nav class="scos__tabs">
		<a href="?page=<?php echo esc_attr( $page_slug ); ?>&tab=modules"
		   class="scos__tab<?php echo 'modules' === $active_tab ? ' scos__tab--active' : ''; ?>">
			<?php esc_html_e( 'Modules', 'site-essentials' ); ?>
		</a>
		<a href="?page=<?php echo esc_attr( $page_slug ); ?>&tab=import-export"
		   class="scos__tab<?php echo 'import-export' === $active_tab ? ' scos__tab--active' : ''; ?>">
			<?php esc_html_e( 'Import / Export', 'site-essentials' ); ?>
		</a>
		<a href="?page=<?php echo esc_attr( $page_slug ); ?>&tab=api"
		   class="scos__tab<?php echo 'api' === $active_tab ? ' scos__tab--active' : ''; ?>">
			<?php esc_html_e( 'API Settings', 'site-essentials' ); ?>
		</a>
		<a href="?page=<?php echo esc_attr( $page_slug ); ?>&tab=ai-keys"
		   class="scos__tab<?php echo 'ai-keys' === $active_tab ? ' scos__tab--active' : ''; ?>">
			<?php esc_html_e( 'AI Providers', 'site-essentials' ); ?>
		</a>
		<a href="?page=<?php echo esc_attr( $page_slug ); ?>&tab=email"
		   class="scos__tab<?php echo 'email' === $active_tab ? ' scos__tab--active' : ''; ?>">
			<?php esc_html_e( 'Email', 'site-essentials' ); ?>
		</a>
		<a href="?page=<?php echo esc_attr( $page_slug ); ?>&tab=cache"
		   class="scos__tab<?php echo 'cache' === $active_tab ? ' scos__tab--active' : ''; ?>">
			<?php esc_html_e( 'Cache', 'site-essentials' ); ?>
		</a>
		<a href="?page=<?php echo esc_attr( $page_slug ); ?>&tab=debug"
		   class="scos__tab<?php echo 'debug' === $active_tab ? ' scos__tab--active' : ''; ?>">
			<?php esc_html_e( 'Debug', 'site-essentials' ); ?>
		</a>
	</nav>

	<?php if ( 'modules' === $active_tab ) : ?>

		<p class="description" style="margin-bottom:var(--scos-s-4)">
			<?php esc_html_e( 'Enable or disable modules below. Module settings are available on their respective pages (SEO, Essentials, etc.).', 'site-essentials' ); ?>
		</p>

		<?php
		/*
		 * Call render_modules_section() directly rather than do_settings_sections().
		 * do_settings_sections() renders an <h2> for every registered module section
		 * (added via register_settings()), which produces the duplicate name list
		 * visible below the grid.
		 */
		global $wp_settings_sections;
		$section_callback = $wp_settings_sections[ Admin_UI::PAGE_SLUG ]['site_essentials_modules']['callback'] ?? null;
		if ( is_callable( $section_callback ) ) {
			call_user_func( $section_callback );
		}
		?>

	<?php elseif ( 'import-export' === $active_tab ) : ?>

		<div class="scos-card" style="margin-bottom:var(--scos-s-4)">
			<div class="scos-card__header">
				<div>
					<h2 class="scos-card__title"><?php esc_html_e( 'Export Settings', 'site-essentials' ); ?></h2>
					<p class="scos-card__desc"><?php esc_html_e( 'Export your Site Essentials settings as JSON.', 'site-essentials' ); ?></p>
				</div>
			</div>
			<div class="scos-card__body">
				<button type="button" class="scos-btn scos-btn--primary" id="se-export-settings">
					<?php esc_html_e( 'Export Settings', 'site-essentials' ); ?>
				</button>
			</div>
		</div>

		<div class="scos-card">
			<div class="scos-card__header">
				<div>
					<h2 class="scos-card__title"><?php esc_html_e( 'Import Settings', 'site-essentials' ); ?></h2>
					<p class="scos-card__desc"><?php esc_html_e( 'Import settings from a JSON file.', 'site-essentials' ); ?></p>
				</div>
			</div>
			<div class="scos-card__body">
				<textarea id="se-import-json" rows="10" class="scos-input scos-input--mono" style="max-width:100%;margin-bottom:var(--scos-s-3)"></textarea>
				<label class="scos-checkbox-row" style="margin-bottom:var(--scos-s-3)">
					<input type="checkbox" id="se-import-merge" checked>
					<span><?php esc_html_e( 'Merge with existing settings (unchecked = replace all)', 'site-essentials' ); ?></span>
				</label>
			</div>
			<div class="scos-card__footer">
				<button type="button" class="scos-btn scos-btn--primary" id="se-import-settings">
					<?php esc_html_e( 'Import Settings', 'site-essentials' ); ?>
				</button>
			</div>
		</div>

	<?php elseif ( 'api' === $active_tab ) : ?>

		<?php include SITE_ESSENTIALS_PATH . 'Views/settings-api.php'; ?>

	<?php elseif ( 'ai-keys' === $active_tab ) : ?>

		<?php
		// SCOS stores no AI provider credentials. The AI Provider plugins own the keys
		// (connectors_ai_*) and the WP AI Client resolves the model; access is granted
		// per plugin in the connector approval registry. See CLAUDE.md section 6.
		$scos_ai_approvals = (array) get_option( 'wpai_connector_approvals', [] );
		$scos_ai_approved  = (array) ( $scos_ai_approvals['site-essentials'] ?? [] );
		$scos_ai_approved  = array_keys( array_filter( $scos_ai_approved ) );
		$scos_ai_client_ok = function_exists( 'wp_ai_client_prompt' );
		?>

			<div class="scos-card">
				<div class="scos-card__header">
					<div>
						<h2 class="scos-card__title"><?php esc_html_e( 'AI Providers', 'site-essentials' ); ?></h2>
						<p class="scos-card__desc"><?php esc_html_e( 'Site Essentials stores no AI credentials and pins no model. Keys belong to the AI Provider plugins, and the WordPress AI Client picks the model at runtime — so every ability works with whichever provider you approve.', 'site-essentials' ); ?></p>
					</div>
				</div>
				<div class="scos-card__body">
					<?php if ( ! $scos_ai_client_ok ) : ?>
						<div class="scos-notice scos-notice--warning">
							<p>
								<?php esc_html_e( 'The WordPress AI Client is not available. Install and activate the AI plugin plus at least one AI Provider plugin before using any SCOS AI ability.', 'site-essentials' ); ?>
							</p>
						</div>
					<?php elseif ( empty( $scos_ai_approved ) ) : ?>
						<div class="scos-notice scos-notice--warning">
							<p>
								<?php esc_html_e( 'No AI provider is approved for Site Essentials yet. The first time an ability runs, WordPress will raise an approval request — approve it and generation starts working.', 'site-essentials' ); ?>
							</p>
						</div>
					<?php else : ?>
						<p class="description">
							<?php esc_html_e( 'Approved for Site Essentials:', 'site-essentials' ); ?>
							<strong><?php echo esc_html( implode( ', ', $scos_ai_approved ) ); ?></strong>
						</p>
					<?php endif; ?>

					<p class="description">
						<?php esc_html_e( 'Manage credentials under Settings → AI, and grant or revoke per-plugin provider access under', 'site-essentials' ); ?>
						<a href="<?php echo esc_url( admin_url( 'tools.php?page=ai-connector-approval' ) ); ?>"><?php esc_html_e( 'Tools → AI Connector Approval', 'site-essentials' ); ?></a>.
					</p>
					<p class="description">
						<?php esc_html_e( 'Note: approval is granted per calling plugin. An ability invoked through an agent connector is attributed to that connector, not to Site Essentials, so it needs its own approval.', 'site-essentials' ); ?>
					</p>
				</div>
			</div>

	<?php elseif ( 'email' === $active_tab ) : ?>

		<?php include SITE_ESSENTIALS_PATH . 'Modules/EmailDelivery/views/settings.php'; ?>

	<?php elseif ( 'cache' === $active_tab ) : ?>

		<?php $stats = Cache_Helper::get_stats(); ?>

		<div class="scos-card" style="margin-bottom:var(--scos-s-4)">
			<div class="scos-card__header scos-card__header--plain">
				<h2 class="scos-card__title"><?php esc_html_e( 'Cache Statistics', 'site-essentials' ); ?></h2>
			</div>
			<div class="scos-card__body">
				<table class="scos-form">
					<tbody>
						<tr>
							<th><?php esc_html_e( 'Object Cache', 'site-essentials' ); ?></th>
							<td>
								<?php if ( $stats['object_cache_enabled'] ) : ?>
									<span style="color:var(--scos-success)">✓ <?php esc_html_e( 'Enabled', 'site-essentials' ); ?></span>
								<?php else : ?>
									<span style="color:var(--scos-ink-subtle)">✗ <?php esc_html_e( 'Disabled (using transients)', 'site-essentials' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Cache Group', 'site-essentials' ); ?></th>
							<td><code><?php echo esc_html( $stats['cache_group'] ); ?></code></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Default Duration', 'site-essentials' ); ?></th>
							<td><?php echo esc_html( $stats['default_duration'] ); ?> <?php esc_html_e( 'seconds', 'site-essentials' ); ?></td>
						</tr>
						<?php if ( isset( $stats['transient_count'] ) ) : ?>
						<tr>
							<th><?php esc_html_e( 'Transient Count', 'site-essentials' ); ?></th>
							<td><?php echo esc_html( $stats['transient_count'] ); ?></td>
						</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>

		<div class="scos-card">
			<div class="scos-card__header scos-card__header--plain">
				<h2 class="scos-card__title"><?php esc_html_e( 'Clear Cache', 'site-essentials' ); ?></h2>
			</div>
			<div class="scos-card__body">
				<p class="description" style="margin-bottom:var(--scos-s-3)"><?php esc_html_e( 'Clear all Site Essentials cache entries.', 'site-essentials' ); ?></p>
				<button type="button" class="scos-btn scos-btn--ghost" id="se-clear-cache">
					<?php esc_html_e( 'Clear All Cache', 'site-essentials' ); ?>
				</button>
			</div>
		</div>

	<?php elseif ( 'debug' === $active_tab ) : ?>

		<?php $loaded_modules = Module_Loader::get_loaded_modules(); ?>
		<div class="scos-card" style="margin-bottom:var(--scos-s-4)">
			<div class="scos-card__header scos-card__header--plain">
				<h2 class="scos-card__title"><?php esc_html_e( 'Loaded Modules', 'site-essentials' ); ?></h2>
			</div>
			<div class="scos-card__body">
				<?php if ( empty( $loaded_modules ) ) : ?>
					<p class="description"><?php esc_html_e( 'No modules loaded.', 'site-essentials' ); ?></p>
				<?php else : ?>
					<table class="scos-form">
						<tbody>
						<?php foreach ( $loaded_modules as $module_id => $module ) : ?>
							<tr>
								<th><?php echo esc_html( $module::get_name() ); ?></th>
								<td>
									<code><?php echo esc_html( $module_id ); ?></code>
									&nbsp;&mdash;&nbsp;v<?php echo esc_html( $module::get_version() ); ?>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>

		<?php $failed_modules = Module_Loader::get_failed_modules(); ?>
		<div class="scos-card" style="margin-bottom:var(--scos-s-4)">
			<div class="scos-card__header scos-card__header--plain">
				<h2 class="scos-card__title"><?php esc_html_e( 'Failed Modules', 'site-essentials' ); ?></h2>
			</div>
			<div class="scos-card__body">
				<?php if ( empty( $failed_modules ) ) : ?>
					<p class="description" style="color:var(--scos-success)">✓ <?php esc_html_e( 'No module failures.', 'site-essentials' ); ?></p>
				<?php else : ?>
					<table class="scos-form">
						<tbody>
						<?php foreach ( $failed_modules as $module_id => $reason ) : ?>
							<tr>
								<th style="color:var(--scos-danger)"><?php echo esc_html( $module_id ); ?></th>
								<td><?php echo esc_html( $reason ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>

		<div class="scos-card">
			<div class="scos-card__header scos-card__header--plain">
				<h2 class="scos-card__title"><?php esc_html_e( 'System Info', 'site-essentials' ); ?></h2>
			</div>
			<div class="scos-card__body">
				<table class="scos-form">
					<tbody>
						<tr>
							<th><?php esc_html_e( 'Site Essentials Version', 'site-essentials' ); ?></th>
							<td><?php echo esc_html( SITE_ESSENTIALS_VERSION ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'WordPress Version', 'site-essentials' ); ?></th>
							<td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'PHP Version', 'site-essentials' ); ?></th>
							<td><?php echo esc_html( PHP_VERSION ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Object Cache', 'site-essentials' ); ?></th>
							<td>
								<?php if ( wp_using_ext_object_cache() ) : ?>
									<span style="color:var(--scos-success)">✓ <?php esc_html_e( 'Enabled', 'site-essentials' ); ?></span>
								<?php else : ?>
									<span style="color:var(--scos-ink-subtle)">✗ <?php esc_html_e( 'Disabled', 'site-essentials' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>

	<?php endif; ?>

</div>
