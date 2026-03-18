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
		add_action( 'admin_menu', [ __CLASS__, 'register' ] );
		// Fix submenu highlight for taxonomy management pages.
		add_filter( 'parent_file',  [ __CLASS__, 'fix_parent_file' ] );
		add_filter( 'submenu_file', [ __CLASS__, 'fix_submenu_file' ] );
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
		</div>
		<?php
	}
}
