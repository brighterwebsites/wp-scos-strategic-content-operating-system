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
 * v1.1.0 | 2026-05-19 — SCOS design system applied to Overview; Force Re-analyze All button added.
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

	const MENU_SLUG         = 'scos-content-architecture';
	const OVERVIEW_SLUG     = 'scos-content-architecture';
	const INTEGRATIONS_SLUG = 'scos-ca-integrations';

	public static function init() {
		add_action( 'admin_menu',             [ __CLASS__, 'register' ] );
		add_action( 'admin_menu',             [ __CLASS__, 'suppress_legacy_integrations' ], 99 );
		add_action( 'admin_enqueue_scripts',  [ __CLASS__, 'enqueue_assets' ] );
		add_action( 'admin_post_scos_save_ca_integrations', [ __CLASS__, 'save_integrations' ] );
		// Fix submenu highlight for taxonomy management pages.
		add_filter( 'parent_file',  [ __CLASS__, 'fix_parent_file' ] );
		add_filter( 'submenu_file', [ __CLASS__, 'fix_submenu_file' ] );
	}

	/**
	 * Remove legacy bw-integration-car submenu from brighter_support once CA is active.
	 */
	public static function suppress_legacy_integrations(): void {
		remove_submenu_page( 'brighter_support', 'bw-integration-car' );
	}

	public static function enqueue_assets( string $hook ): void {
		if ( strpos( $hook, 'scos-content-architecture' ) === false ) {
			return;
		}

		wp_enqueue_style( 'scos-tokens', SITE_ESSENTIALS_URL . 'assets/css/tokens.css', [], SITE_ESSENTIALS_VERSION );
		wp_enqueue_style( 'scos-ui',     SITE_ESSENTIALS_URL . 'assets/css/scos-ui.css', [ 'scos-tokens' ], SITE_ESSENTIALS_VERSION );

		$asset_file = __DIR__ . '/assets/ca-overview.js';
		wp_enqueue_script(
			'scos-ca-overview',
			SITE_ESSENTIALS_URL . 'Modules/ContentArchitecture/assets/ca-overview.js',
			[ 'jquery' ],
			file_exists( $asset_file ) ? (string) filemtime( $asset_file ) : '1.0.0',
			true
		);
		wp_localize_script( 'scos-ca-overview', 'scosCA', [
			'nonce'      => wp_create_nonce( 'scos_analysis' ),
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'clearNonce' => wp_create_nonce( 'scos_clear_analysis' ),
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

		// Integrations — Airtable CAR sync configuration (scos_car_* keys).
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Integrations', 'site-essentials' ),
			__( 'Integrations', 'site-essentials' ),
			'manage_options',
			self::INTEGRATIONS_SLUG,
			[ __CLASS__, 'render_integrations' ]
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
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'site-essentials' ) );
		}

		$clusters      = get_terms( [ 'taxonomy' => 'scos_content_cluster', 'hide_empty' => false ] );
		$topics        = get_terms( [ 'taxonomy' => 'scos_topic',           'hide_empty' => false ] );
		$cluster_count = is_wp_error( $clusters ) ? 0 : count( $clusters );
		$topic_count   = is_wp_error( $topics )   ? 0 : count( $topics );
		?>
		<div class="wrap scos">

			<header class="scos__header">
				<div>
					<h1 class="scos__title"><?php esc_html_e( 'Content Architecture', 'site-essentials' ); ?></h1>
					<p class="scos__subtitle">Site Essentials &rsaquo; Content Architecture</p>
				</div>
				<div class="scos__header-actions">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::INTEGRATIONS_SLUG ) ); ?>" class="scos-btn scos-btn--ghost">
						<?php esc_html_e( 'Integrations', 'site-essentials' ); ?>
					</a>
				</div>
			</header>

			<?php // ── Taxonomy summary cards ──────────────────────────────────── ?>
			<div style="display:flex;gap:var(--scos-s-4);flex-wrap:wrap;margin-bottom:var(--scos-s-6)">

				<div class="scos-card" style="flex:1;min-width:200px">
					<div class="scos-card__header scos-card__header--plain">
						<span class="scos-card__title"><?php esc_html_e( 'Content Clusters', 'site-essentials' ); ?></span>
					</div>
					<div class="scos-card__body">
						<p style="font-size:2rem;font-weight:700;color:var(--scos-accent);margin:0 0 var(--scos-s-3)">
							<?php echo absint( $cluster_count ); ?>
						</p>
						<p style="color:var(--scos-ink-subtle);margin:0 0 var(--scos-s-4)"><?php esc_html_e( 'defined', 'site-essentials' ); ?></p>
					</div>
					<div class="scos-card__footer">
						<a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=scos_content_cluster' ) ); ?>" class="scos-btn scos-btn--ghost">
							<?php esc_html_e( 'Manage Clusters', 'site-essentials' ); ?>
						</a>
					</div>
				</div>

				<div class="scos-card" style="flex:1;min-width:200px">
					<div class="scos-card__header scos-card__header--plain">
						<span class="scos-card__title"><?php esc_html_e( 'Topics', 'site-essentials' ); ?></span>
					</div>
					<div class="scos-card__body">
						<p style="font-size:2rem;font-weight:700;color:var(--scos-accent);margin:0 0 var(--scos-s-3)">
							<?php echo absint( $topic_count ); ?>
						</p>
						<p style="color:var(--scos-ink-subtle);margin:0 0 var(--scos-s-4)"><?php esc_html_e( 'defined', 'site-essentials' ); ?></p>
					</div>
					<div class="scos-card__footer">
						<a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=scos_topic' ) ); ?>" class="scos-btn scos-btn--ghost">
							<?php esc_html_e( 'Manage Topics', 'site-essentials' ); ?>
						</a>
					</div>
				</div>

			</div>

			<?php if ( ! empty( $clusters ) && ! is_wp_error( $clusters ) ) : ?>
			<div class="scos-card" style="margin-bottom:var(--scos-s-6)">
				<div class="scos-card__header">
					<span class="scos-card__title"><?php esc_html_e( 'Cluster Breakdown', 'site-essentials' ); ?></span>
				</div>
				<div class="scos-card__body" style="padding:0">
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Cluster', 'site-essentials' ); ?></th>
								<th style="width:80px;text-align:center"><?php esc_html_e( 'Posts', 'site-essentials' ); ?></th>
								<th style="width:80px"></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $clusters as $cluster ) : ?>
								<tr>
									<td><?php echo esc_html( $cluster->name ); ?></td>
									<td style="text-align:center"><?php echo esc_html( $cluster->count ); ?></td>
									<td>
										<a href="<?php echo esc_url( admin_url( 'edit-tags.php?action=edit&taxonomy=scos_content_cluster&tag_ID=' . $cluster->term_id ) ); ?>">
											<?php esc_html_e( 'Edit', 'site-essentials' ); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
			<?php endif; ?>

			<?php // ── Content Analysis Section ──────────────────────────────── ?>
			<div class="scos-card">
				<div class="scos-card__header">
					<span class="scos-card__title"><?php esc_html_e( 'Content Analysis', 'site-essentials' ); ?></span>
					<span class="scos-card__desc"><?php esc_html_e( 'Word count, H2s, images, and links per post. Runs on save; use the buttons to backfill or force a full re-analysis.', 'site-essentials' ); ?></span>
				</div>
				<div class="scos-card__body" style="padding:0">
					<table class="wp-list-table widefat fixed striped" id="scos-analysis-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Post Type', 'site-essentials' ); ?></th>
								<th style="width:80px;text-align:center"><?php esc_html_e( 'Total', 'site-essentials' ); ?></th>
								<th style="width:80px;text-align:center"><?php esc_html_e( 'Analysed', 'site-essentials' ); ?></th>
								<th style="width:80px;text-align:center"><?php esc_html_e( 'Pending', 'site-essentials' ); ?></th>
								<th style="width:140px;text-align:center"><?php esc_html_e( 'Coverage', 'site-essentials' ); ?></th>
								<th style="width:120px"></th>
							</tr>
						</thead>
						<tbody id="scos-analysis-rows">
							<tr><td colspan="6" style="color:var(--scos-ink-subtle);text-align:center;padding:var(--scos-s-5)">
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
				<div class="scos-card__footer" style="flex-wrap:wrap;gap:var(--scos-s-3)">
					<button id="scos-run-all" class="scos-btn scos-btn--primary">
						&#9654; <?php esc_html_e( 'Run Analysis (pending only)', 'site-essentials' ); ?>
					</button>
					<button id="scos-force-all" class="scos-btn scos-btn--ghost" title="<?php esc_attr_e( 'Clears stored analysis and re-runs on all posts — use when Breakdance content was not being read correctly.', 'site-essentials' ); ?>">
						&#8635; <?php esc_html_e( 'Force Re-analyze All', 'site-essentials' ); ?>
					</button>
					<span id="scos-analysis-msg" style="color:var(--scos-ink-subtle);font-size:var(--scos-fs-sm);align-self:center"></span>
				</div>
				<div id="scos-analysis-progress" style="display:none;padding:0 var(--scos-s-5) var(--scos-s-5)">
					<div style="background:var(--scos-border);border-radius:var(--scos-r-sm);height:8px;overflow:hidden">
						<div id="scos-analysis-bar" style="background:var(--scos-accent);height:100%;width:0;transition:width .3s"></div>
					</div>
					<div id="scos-analysis-progress-label" style="font-size:var(--scos-fs-sm);color:var(--scos-ink-subtle);margin-top:var(--scos-s-2)"></div>
				</div>
			</div>

		</div>
		<?php
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Integrations page
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Helper: read scos_car_* option with bw_airtable_* fallback.
	 */
	private static function get_car_option( string $scos_key, string $legacy_key, string $default = '' ): string {
		$val = get_option( $scos_key, '' );
		if ( '' !== $val ) {
			return $val;
		}
		return (string) get_option( $legacy_key, $default );
	}

	/**
	 * Render the Integrations page (Airtable CAR Sync configuration).
	 */
	public static function render_integrations(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Insufficient permissions.', 'site-essentials' ) );
		}

		$saved = isset( $_GET['scos_ca_int_saved'] );

		$token     = self::get_car_option( 'scos_car_airtable_token',    'bw_airtable_api_token' );
		$base_id   = self::get_car_option( 'scos_car_airtable_base_id',  'bw_airtable_base_id' );
		$table_id  = self::get_car_option( 'scos_car_airtable_table_id', 'bw_airtable_table_id' );
		$altc_id   = self::get_car_option( 'scos_car_airtable_altc_id',  'bw_airtable_altc_table_id' );
		$topics_id = self::get_car_option( 'scos_car_airtable_topics_id','bw_airtable_topics_table_id' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Content Architecture — Integrations', 'site-essentials' ); ?></h1>

			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'site-essentials' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'scos_ca_integrations_save', 'scos_ca_int_nonce' ); ?>
				<input type="hidden" name="action" value="scos_save_ca_integrations">

				<h2><?php esc_html_e( 'Airtable CAR Sync', 'site-essentials' ); ?></h2>
				<p class="description" style="margin-bottom:18px;max-width:700px;">
					<?php esc_html_e( 'Sync Content Architecture Records (Clusters, Topics, Content) to Airtable. Settings are stored under scos_car_* option keys. Recommended sync order: 1) ALTC Clusters → 2) Topics → 3) Content.', 'site-essentials' ); ?>
				</p>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="scos_car_airtable_token"><?php esc_html_e( 'API Token', 'site-essentials' ); ?></label>
						</th>
						<td>
							<input type="text" id="scos_car_airtable_token" name="scos_car_airtable_token"
								value="<?php echo esc_attr( $token ); ?>"
								class="regular-text code" style="width:100%;max-width:560px;"
								placeholder="Bearer pat..." autocomplete="off" />
							<p class="description">
								<?php esc_html_e( 'Your Airtable Personal Access Token.', 'site-essentials' ); ?>
								<a href="https://airtable.com/create/tokens" target="_blank" rel="noopener">
									<?php esc_html_e( 'Airtable → Developer → Personal Access Tokens', 'site-essentials' ); ?>
								</a>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="scos_car_airtable_base_id"><?php esc_html_e( 'Base ID', 'site-essentials' ); ?></label>
						</th>
						<td>
							<input type="text" id="scos_car_airtable_base_id" name="scos_car_airtable_base_id"
								value="<?php echo esc_attr( $base_id ); ?>"
								class="regular-text code" style="width:100%;max-width:560px;"
								placeholder="appOqcQR79umbJJGP" />
							<p class="description">
								<?php esc_html_e( 'Found in the Airtable API docs URL (appXXXXXXXXXXXXXX).', 'site-essentials' ); ?>
								<a href="https://airtable.com/api" target="_blank" rel="noopener"><?php esc_html_e( 'Open API docs →', 'site-essentials' ); ?></a>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="scos_car_airtable_table_id"><?php esc_html_e( 'Content Table ID', 'site-essentials' ); ?></label>
						</th>
						<td>
							<input type="text" id="scos_car_airtable_table_id" name="scos_car_airtable_table_id"
								value="<?php echo esc_attr( $table_id ); ?>"
								class="regular-text code" style="width:100%;max-width:560px;"
								placeholder="tblXXXXXXXXXXXXXX" />
							<p class="description"><?php esc_html_e( 'Main content/posts table. Use Table ID from API docs (more stable than name).', 'site-essentials' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="scos_car_airtable_altc_id"><?php esc_html_e( 'ALTC Clusters Table ID', 'site-essentials' ); ?></label>
						</th>
						<td>
							<input type="text" id="scos_car_airtable_altc_id" name="scos_car_airtable_altc_id"
								value="<?php echo esc_attr( $altc_id ); ?>"
								class="regular-text code" style="width:100%;max-width:560px;"
								placeholder="tblXXXXXXXXXXXXXX" />
							<p class="description"><?php esc_html_e( 'Strategic Lens / ALTC clusters table. Terms sync on save; stores Airtable record ID in term meta for linked records.', 'site-essentials' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="scos_car_airtable_topics_id"><?php esc_html_e( 'Topics Table ID', 'site-essentials' ); ?></label>
						</th>
						<td>
							<input type="text" id="scos_car_airtable_topics_id" name="scos_car_airtable_topics_id"
								value="<?php echo esc_attr( $topics_id ); ?>"
								class="regular-text code" style="width:100%;max-width:560px;"
								placeholder="tblXXXXXXXXXXXXXX" />
							<p class="description"><?php esc_html_e( 'Topics table. Terms sync on save; stores Airtable record ID in term meta for linked records in Content.', 'site-essentials' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Bulk Sync', 'site-essentials' ); ?></th>
						<td>
							<p class="description" style="margin-bottom:8px;"><?php esc_html_e( 'Sync all data to Airtable in the correct order: ALTC Clusters → Topics → Content (phase 1: static) → Content (phase 2: links).', 'site-essentials' ); ?></p>
							<button type="button" id="scos-airtable-seed-bulk" class="button button-secondary">
								<?php esc_html_e( 'Seed Airtable — Sync All', 'site-essentials' ); ?>
							</button>
							<span id="scos-airtable-seed-status" style="margin-left:12px;font-family:monospace;font-size:12px;color:#555;"></span>
							<?php wp_nonce_field( 'bw_airtable_seed_bulk', 'bw_airtable_seed_nonce', false ); ?>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save Settings', 'site-essentials' ) ); ?>
			</form>
		</div>
		<script>
		jQuery(document).ready(function($){
			$('#scos-airtable-seed-bulk').on('click', function(){
				var $btn = $(this), $status = $('#scos-airtable-seed-status');
				$btn.prop('disabled', true);
				$status.text('<?php esc_html_e( 'Syncing…', 'site-essentials' ); ?>');
				$.post(ajaxurl, {
					action: 'bw_airtable_seed_bulk',
					nonce: $('#bw_airtable_seed_nonce').val()
				}).done(function(r){
					if (r.success) {
						var d = r.data, lines = [];
						lines.push('ALTC: '       + (d.altc          ? d.altc.synced          : 0) + ' synced, ' + (d.altc          ? d.altc.errors          : 0) + ' errors');
						lines.push('Topics: '     + (d.topics        ? d.topics.synced        : 0) + ' synced, ' + (d.topics        ? d.topics.errors        : 0) + ' errors');
						lines.push('Content P1: ' + (d.content_phase1? d.content_phase1.synced: 0) + ' synced, ' + (d.content_phase1? d.content_phase1.errors : 0) + ' errors');
						lines.push('Content P2: ' + (d.content_phase2? d.content_phase2.synced: 0) + ' synced, ' + (d.content_phase2? d.content_phase2.errors : 0) + ' errors');
						$status.text(lines.join(' | '));
					} else {
						$status.text('Error: ' + (r.data && r.data.message ? r.data.message : 'Unknown'));
					}
				}).fail(function(){ $status.text('Request failed.'); })
				  .always(function(){ $btn.prop('disabled', false); });
			});
		});
		</script>
		<?php
	}

	/**
	 * Handle Integrations form POST — saves scos_car_* keys, dual-writes to bw_airtable_* for compat.
	 */
	public static function save_integrations(): void {
		if ( ! isset( $_POST['scos_ca_int_nonce'] )
			|| ! wp_verify_nonce( $_POST['scos_ca_int_nonce'], 'scos_ca_integrations_save' ) ) {
			wp_die( __( 'Security check failed.', 'site-essentials' ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Insufficient permissions.', 'site-essentials' ) );
		}

		$fields = [
			'scos_car_airtable_token'    => 'bw_airtable_api_token',
			'scos_car_airtable_base_id'  => 'bw_airtable_base_id',
			'scos_car_airtable_table_id' => 'bw_airtable_table_id',
			'scos_car_airtable_altc_id'  => 'bw_airtable_altc_table_id',
			'scos_car_airtable_topics_id'=> 'bw_airtable_topics_table_id',
		];

		foreach ( $fields as $new_key => $legacy_key ) {
			$val = isset( $_POST[ $new_key ] ) ? sanitize_text_field( $_POST[ $new_key ] ) : '';
			update_option( $new_key,    $val );
			update_option( $legacy_key, $val ); // Dual-write: BW_Airtable_Helper reads bw_* keys
		}

		wp_redirect( add_query_arg(
			[ 'page' => self::INTEGRATIONS_SLUG, 'scos_ca_int_saved' => '1' ],
			admin_url( 'admin.php' )
		) );
		exit;
	}
}
