<?php
/**
 * SEO Meta — Author OG Image
 *
 * Adds a 1200×630 OG image field to the user profile / user edit screens.
 * On author archive pages the image is output as og:image by Head_Output
 * via Archive_Settings og_image_id (archive-level). This class handles the
 * per-author override: a specific author can have their own OG image that
 * overrides the global author archive default.
 *
 * Meta key: scos_seo_author_og_image_id  (user meta, integer, attachment ID)
 *
 * Head_Output reads this key in output_archive_meta() on is_author() pages
 * by checking the queried author's user meta before falling back to the
 * archive-level og_image_id.
 *
 * @package    SiteEssentials
 * @subpackage Modules\SeoMeta
 * @version    1.0 | 2026-06-04
 * @since      1.0.0
 */

namespace SiteEssentials\Modules\SeoMeta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Author_SEO {

	const META_KEY = 'scos_seo_author_og_image_id';

	public static function init(): void {
		add_action( 'show_user_profile',        [ __CLASS__, 'render_field' ] );
		add_action( 'edit_user_profile',        [ __CLASS__, 'render_field' ] );
		add_action( 'personal_options_update',  [ __CLASS__, 'save_field' ] );
		add_action( 'edit_user_profile_update', [ __CLASS__, 'save_field' ] );

		// Enqueue WP media picker on user edit screens.
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_media' ] );
	}

	public static function enqueue_media( string $hook ): void {
		if ( ! in_array( $hook, [ 'profile.php', 'user-edit.php' ], true ) ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script(
			'scos-author-og-picker',
			SITE_ESSENTIALS_URL . 'Modules/SeoMeta/assets/author-og-picker.js',
			[ 'jquery' ],
			'1.0.0',
			true
		);
	}

	public static function render_field( \WP_User $user ): void {
		if ( ! current_user_can( 'edit_users' ) && get_current_user_id() !== $user->ID ) {
			return;
		}

		$image_id  = (int) get_user_meta( $user->ID, self::META_KEY, true );
		$image_url = $image_id > 0 ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';

		wp_nonce_field( 'scos_author_og_image_' . $user->ID, 'scos_author_og_nonce' );
		?>
		<h2><?php esc_html_e( 'SEO — Author OG Image', 'site-essentials' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th>
					<label for="scos_author_og_image_id">
						<?php esc_html_e( 'OG Image (1200×630)', 'site-essentials' ); ?>
					</label>
					<p class="description" style="font-weight:normal">
						<?php esc_html_e( 'scos_seo_author_og_image_id', 'site-essentials' ); ?>
					</p>
				</th>
				<td>
					<div id="scos-author-og-preview" style="margin-bottom:8px">
						<?php if ( $image_url ) : ?>
							<img src="<?php echo esc_url( $image_url ); ?>"
							     style="max-width:300px;height:auto;display:block;border:1px solid #ddd">
						<?php endif; ?>
					</div>
					<input type="hidden"
					       id="scos_author_og_image_id"
					       name="scos_author_og_image_id"
					       value="<?php echo esc_attr( $image_id ?: '' ); ?>">
					<button type="button" class="button" id="scos-author-og-select">
						<?php esc_html_e( $image_id ? 'Change image' : 'Select image', 'site-essentials' ); ?>
					</button>
					<?php if ( $image_id ) : ?>
						<button type="button" class="button" id="scos-author-og-remove" style="margin-left:4px">
							<?php esc_html_e( 'Remove', 'site-essentials' ); ?>
						</button>
					<?php endif; ?>
					<p class="description" style="margin-top:6px">
						<?php esc_html_e( 'Shown when this author\'s archive page is shared on Facebook, LinkedIn, etc. Recommended: 1200×630 px. Falls back to the global Author Archive OG image if not set.', 'site-essentials' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	public static function save_field( int $user_id ): void {
		if ( ! isset( $_POST['scos_author_og_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['scos_author_og_nonce'] ) ), 'scos_author_og_image_' . $user_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		if ( ! isset( $_POST['scos_author_og_image_id'] ) ) {
			return;
		}

		$image_id = absint( $_POST['scos_author_og_image_id'] );

		if ( $image_id > 0 ) {
			update_user_meta( $user_id, self::META_KEY, $image_id );
		} else {
			delete_user_meta( $user_id, self::META_KEY );
		}
	}
}
