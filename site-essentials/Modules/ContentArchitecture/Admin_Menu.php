<?php
/**
 * Content Architecture — Admin Menu
 *
 * Registers a "Content Architecture" top-level admin menu with submenus for:
 *  - Content Clusters taxonomy management (edit-tags.php)
 *  - Topics taxonomy management (edit-tags.php)
 *  - Strategy Overview dashboard (simple stats page)
 *
 * The taxonomy management pages use the native WordPress edit-tags.php since
 * both taxonomies are registered with show_ui => true. Our meta_box_cb => false
 * setting means WP won't add sidebar boxes, but the term management pages work
 * perfectly.
 *
 * @package    SiteEssentials
 * @subpackage Modules\ContentArchitecture
 * @since      1.0.0
 */

namespace SiteEssentials\Modules\ContentArchitecture;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Menu {

	const MENU_SLUG       = 'scos-content-architecture';
	const OVERVIEW_SLUG   = 'scos-content-architecture';

	public static function init() {
		add_action( 'admin_menu',             [ __CLASS__, 'register' ] );
		add_action( 'admin_enqueue_scripts',  [ __CLASS__, 'enqueue_assets' ] );
		// Fix submenu highlight for taxonomy management pages.
		add_filter( 'parent_file',  [ __CLASS__, 'fix_parent_file' ] );
		add_filter( 'submenu_file', [ __CLASS__, 'fix_submenu_file' ] );
	}

	public static function enqueue_assets( string $hook ): void {
		if ( strpos( $hook, 'scos-content-architecture' ) === false ) {
			return;
		}
		$asset_file = __DIR__ . '/assets/ca-overview.js';
		wp_enqueue_script(
			'scos-ca-overview',
			SITE_ESSENTIALS_URL . 'Modules/ContentArchitecture/assets/ca-overview.js',
			[ 'jquery' ],
			file_exists( $asset_file ) ? (string) filemtime( $asset_file ) : '1.0.0',
			true
		);
		wp_localize_script( 'scos-ca-overview', 'scosCA', [
			'nonce'   => wp_create_nonce( 'scos_analysis' ),
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		] );
	}

	/**
	 * Register top-level menu and submenus.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register() {
		// Top-level menu (points to the overview/dashboard page).
		add_menu_page(
			__( 'Content Architecture', 'site-essentials' ),
			__( 'Content Architecture', 'site-essentials' ),
			'edit_posts',
			self::MENU_SLUG,
			[ __CLASS__, 'render_overview' ],
			'dashicons-analytics',
			25
		);

		// Rename first auto-generated submenu from menu title to "Overview".
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Strategy Overview', 'site-essentials' ),
			__( 'Overview', 'site-essentials' ),
			'edit_posts',
			self::OVERVIEW_SLUG,
			[ __CLASS__, 'render_overview' ]
		);

		// Content Clusters — links to native WP taxonomy management.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Content Clusters', 'site-essentials' ),
			__( 'Content Clusters', 'site-essentials' ),
			'manage_categories',
			'edit-tags.php?taxonomy=scos_content_cluster'
		);

		// Topics — links to native WP taxonomy management.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Topics', 'site-essentials' ),
			__( 'Topics', 'site-essentials' ),
			'manage_categories',
			'edit-tags.php?taxonomy=scos_topic'
		);
	}

	/**
	 * Keep the Content Architecture top-level menu open when managing terms.
	 *
	 * @since 1.0.0
	 * @param string $file Current parent file.
	 * @return string
	 */
	public static function fix_parent_file( $file ) {
		global $current_screen;
		if ( $current_screen && in_array( $current_screen->taxonomy, [ 'scos_content_cluster', 'scos_topic' ], true ) ) {
			return self::MENU_SLUG;
		}
		return $file;
	}

	/**
	 * Highlight the correct submenu item when managing terms.
	 *
	 * @since 1.0.0
	 * @param string $file Current submenu file.
	 * @return string
	 */
	public static function fix_submenu_file( $file ) {
		global $current_screen;
		if ( ! $current_screen ) {
			return $file;
		}
		if ( 'scos_content_cluster' === $current_screen->taxonomy ) {
			return 'edit-tags.php?taxonomy=scos_content_cluster';
		}
		if ( 'scos_topic' === $current_screen->taxonomy ) {
			return 'edit-tags.php?taxonomy=scos_topic';
		}
		return $file;
	}

	/**
	 * Render the Strategy Overview / dashboard page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function render_overview() {
		$clusters      = get_terms( [ 'taxonomy' => 'scos_content_cluster', 'hide_empty' => false ] );
		$topics        = get_terms( [ 'taxonomy' => 'scos_topic',           'hide_empty' => false ] );
		$cluster_count = is_wp_error( $clusters ) ? 0 : count( $clusters );
		$topic_count   = is_wp_error( $topics )   ? 0 : count( $topics );
		$post_types    = Taxonomies::get_post_types();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Content Architecture', 'site-essentials' ); ?></h1>

			<div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:20px">

				<div class="card" style="min-width:220px">
					<h2 class="title"><?php esc_html_e( 'Content Clusters', 'site-essentials' ); ?></h2>
					<p><?php printf( esc_html__( '%d defined', 'site-essentials' ), $cluster_count ); ?></p>
					<a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=scos_content_cluster' ) ); ?>" class="button">
						<?php esc_html_e( 'Manage Clusters', 'site-essentials' ); ?>
					</a>
				</div>

				<div class="card" style="min-width:220px">
					<h2 class="title"><?php esc_html_e( 'Topics', 'site-essentials' ); ?></h2>
					<p><?php printf( esc_html__( '%d defined', 'site-essentials' ), $topic_count ); ?></p>
					<a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=scos_topic' ) ); ?>" class="button">
						<?php esc_html_e( 'Manage Topics', 'site-essentials' ); ?>
					</a>
				</div>

			</div>

			<?php if ( ! empty( $clusters ) && ! is_wp_error( $clusters ) ) : ?>
				<h2 style="margin-top:30px"><?php esc_html_e( 'Cluster Breakdown', 'site-essentials' ); ?></h2>
				<table class="widefat striped" style="max-width:700px">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Cluster', 'site-essentials' ); ?></th>
							<th><?php esc_html_e( 'Posts', 'site-essentials' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $clusters as $cluster ) : ?>
							<tr>
								<td><?php echo esc_html( $cluster->name ); ?></td>
								<td><?php echo esc_html( $cluster->count ); ?></td>
								<td>
									<a href="<?php echo esc_url( admin_url( 'edit-tags.php?action=edit&taxonomy=scos_content_cluster&tag_ID=' . $cluster->term_id ) ); ?>">
										<?php esc_html_e( 'Edit', 'site-essentials' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<?php // ── Content Analysis Section ──────────────────────────────── ?>
			<h2 style="margin-top:36px"><?php esc_html_e( 'Content Analysis', 'site-essentials' ); ?></h2>
			<p style="color:#555;max-width:600px">
				<?php esc_html_e( 'Counts word count, H2s, images, and internal/external links for every published post. Runs automatically on save; use the button below to analyse posts that haven\'t been processed yet.', 'site-essentials' ); ?>
			</p>

			<div id="scos-analysis-status" style="margin-bottom:16px">
				<table class="widefat striped" style="max-width:760px" id="scos-analysis-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Post Type', 'site-essentials' ); ?></th>
							<th style="text-align:center"><?php esc_html_e( 'Total', 'site-essentials' ); ?></th>
							<th style="text-align:center"><?php esc_html_e( 'Analysed', 'site-essentials' ); ?></th>
							<th style="text-align:center"><?php esc_html_e( 'Pending', 'site-essentials' ); ?></th>
							<th style="text-align:center"><?php esc_html_e( 'Coverage', 'site-essentials' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody id="scos-analysis-rows">
						<tr><td colspan="6" style="color:#6b7280;text-align:center;padding:16px">
							<?php esc_html_e( 'Loading…', 'site-essentials' ); ?>
						</td></tr>
					</tbody>
					<tfoot id="scos-analysis-foot" style="display:none">
						<tr style="font-weight:600">
							<td><?php esc_html_e( 'Total', 'site-essentials' ); ?></td>
							<td id="scos-ft-total"  style="text-align:center">—</td>
							<td id="scos-ft-done"   style="text-align:center">—</td>
							<td id="scos-ft-pend"   style="text-align:center">—</td>
							<td id="scos-ft-bar"    style="text-align:center">—</td>
							<td></td>
						</tr>
					</tfoot>
				</table>
			</div>

			<div id="scos-analysis-controls" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
				<button id="scos-run-all" class="button button-primary">
					▶ <?php esc_html_e( 'Run Analysis (all pending)', 'site-essentials' ); ?>
				</button>
				<span id="scos-analysis-msg" style="color:#555;font-size:13px"></span>
			</div>

			<div id="scos-analysis-progress" style="display:none;margin-top:12px;max-width:400px">
				<div style="background:#e5e7eb;border-radius:4px;height:10px;overflow:hidden">
					<div id="scos-analysis-bar" style="background:#2563eb;height:100%;width:0;transition:width .3s"></div>
				</div>
				<div id="scos-analysis-progress-label" style="font-size:12px;color:#6b7280;margin-top:4px"></div>
			</div>
		</div>
		<?php
	}
}
