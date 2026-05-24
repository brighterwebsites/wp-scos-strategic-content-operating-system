<?php
/**
 * Recommended Custom Posts & Fields — page chrome + tab dispatcher.
 *
 * v1.0 | 2026-05-19
 *
 * Variables available:
 * @var array  $opts        Current CPT options
 * @var string $active_tab  Active tab slug (settings | faq | projects | reviews | author)
 *
 * @package    SiteEssentials
 * @subpackage Modules\CustomPosts
 */

defined( 'ABSPATH' ) || exit;

use SiteEssentials\Core\Admin_UI;

// ── Enable flags ─────────────────────────────────────────────────────────────
$faq_enabled     = ! empty( $opts['enable_faq'] );
$projects_on     = ! empty( $opts['customer_success_stories'] );
$reviews_on      = ! empty( $opts['enable_reviews'] );
$author_on       = ! empty( $opts['enable_author_extension'] );

// ── Tab whitelist (only includes tabs whose submodule is on) ─────────────────
$available_tabs = [ 'settings' => true ];
if ( $faq_enabled )  { $available_tabs['faq']      = true; }
if ( $projects_on )  { $available_tabs['projects'] = true; }
if ( $reviews_on )   { $available_tabs['reviews']  = true; }
if ( $author_on )    { $available_tabs['author']   = true; }

if ( ! isset( $available_tabs[ $active_tab ] ) ) {
	$active_tab = 'settings';
}

// ── Guide URLs (per audit plan) ──────────────────────────────────────────────
$guide_main     = 'https://brighterwebsites.com.au/software/custom-posts-fields/';
$guide_faq      = 'https://brighterwebsites.com.au/software/custom-posts-fields/'; // TODO: update when /faq-system/ doc exists
$guide_projects = 'https://brighterwebsites.com.au/software/custom-posts-fields/success-stories/';
$guide_reviews  = 'https://brighterwebsites.com.au/software/custom-posts-fields/review-system/';
$guide_author   = 'https://brighterwebsites.com.au/software/custom-posts-fields/author-extension/';

$current_guide = $guide_main;
switch ( $active_tab ) {
	case 'faq':      $current_guide = $guide_faq;      break;
	case 'projects': $current_guide = $guide_projects; break;
	case 'reviews':  $current_guide = $guide_reviews;  break;
	case 'author':   $current_guide = $guide_author;   break;
}

$page_url    = admin_url( 'admin.php' );
$tab_url_for = static function ( string $slug ) use ( $page_url ) {
	return add_query_arg(
		[ 'page' => Admin_UI::CPT_PAGE_SLUG, 'tab' => $slug ],
		$page_url
	);
};

// ── Tab labels ───────────────────────────────────────────────────────────────
$tabs = [
	'settings' => [ 'label' => __( 'Settings',        'site-essentials' ), 'always' => true ],
	'faq'      => [ 'label' => __( 'FAQ System',      'site-essentials' ), 'always' => false, 'on' => $faq_enabled ],
	'projects' => [ 'label' => __( 'Success Stories', 'site-essentials' ), 'always' => false, 'on' => $projects_on ],
	'reviews'  => [ 'label' => __( 'Review System',   'site-essentials' ), 'always' => false, 'on' => $reviews_on ],
	'author'   => [ 'label' => __( 'Author Extension','site-essentials' ), 'always' => false, 'on' => $author_on ],
];

// ── Saved / import notice flags ──────────────────────────────────────────────
$saved         = isset( $_GET['updated'] ) && 'true' === sanitize_text_field( wp_unslash( $_GET['updated'] ) );
$import_status = isset( $_GET['reviews_import'] ) ? sanitize_text_field( wp_unslash( $_GET['reviews_import'] ) ) : '';
?>
<div class="wrap scos">

	<header class="scos__header">
		<div>
			<h1 class="scos__title"><?php esc_html_e( 'Recommended Custom Posts & Fields', 'site-essentials' ); ?></h1>
			<p class="scos__subtitle"><?php esc_html_e( 'Site Essentials › Custom Posts', 'site-essentials' ); ?></p>
		</div>
		<div class="scos__header-actions">
			<a href="<?php echo esc_url( $current_guide ); ?>"
			   class="scos-btn scos-btn--ghost"
			   target="_blank" rel="noopener">
				<?php esc_html_e( 'Guide', 'site-essentials' ); ?> ↗
			</a>
		</div>
	</header>

	<?php if ( $saved ) : ?>
		<div class="scos-notice scos-notice--success">
			<p><?php esc_html_e( 'Settings saved.', 'site-essentials' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( 'success' === $import_status ) :
		$imported = isset( $_GET['imported'] ) ? intval( $_GET['imported'] ) : 0;
		$skipped  = isset( $_GET['skipped'] )  ? intval( $_GET['skipped'] )  : 0; ?>
		<div class="scos-notice scos-notice--success">
			<p><?php printf( esc_html__( '%1$d reviews imported, %2$d skipped.', 'site-essentials' ), $imported, $skipped ); ?></p>
		</div>
	<?php elseif ( 'error' === $import_status ) :
		$err_msg = isset( $_GET['error_msg'] ) ? rawurldecode( sanitize_text_field( wp_unslash( $_GET['error_msg'] ) ) ) : __( 'Import failed.', 'site-essentials' ); ?>
		<div class="scos-notice scos-notice--danger">
			<p><strong><?php esc_html_e( 'Import error:', 'site-essentials' ); ?></strong> <?php echo esc_html( $err_msg ); ?></p>
		</div>
	<?php endif; ?>

	<nav class="scos__tabs">
		<?php foreach ( $tabs as $slug => $tab ) :
			if ( empty( $tab['always'] ) && empty( $tab['on'] ) ) { continue; }
			$active_cls = $slug === $active_tab ? ' scos__tab--active' : ''; ?>
			<a href="<?php echo esc_url( $tab_url_for( $slug ) ); ?>"
			   class="scos__tab<?php echo esc_attr( $active_cls ); ?>">
				<?php echo esc_html( $tab['label'] ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<?php
	switch ( $active_tab ) {
		case 'faq':
			include __DIR__ . '/tab-faq.php';
			break;
		case 'projects':
			include __DIR__ . '/tab-projects.php';
			break;
		case 'reviews':
			include __DIR__ . '/tab-reviews.php';
			break;
		case 'author':
			include __DIR__ . '/tab-author.php';
			break;
		case 'settings':
		default:
			include __DIR__ . '/tab-settings.php';
			break;
	}
	?>

</div>
