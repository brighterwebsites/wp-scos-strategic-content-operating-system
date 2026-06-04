<?php
/**
 * FAQ Selector Gutenberg block (server-rendered).
 *
 * Block name `brighter/faq-selector` is retained for backward compatibility
 * with existing post_content references. Ownership moved from
 * brighter-core/includes/bw-faq.php to site-essentials.
 *
 * Render output: markup only — FAQPage JSON-LD is no longer emitted here.
 * Schema is contributed to the unified site graph by FAQ_Schema_Graph via
 * the `scos_schema_graph_items` filter.
 *
 * v1.1 | 2026-06-03
 *
 * @package    SiteEssentials
 * @subpackage Modules\CustomPosts\FAQ
 */

namespace SiteEssentials\Modules\CustomPosts\FAQ;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FAQ_Block {

	const SCRIPT_HANDLE = 'scos-faq-selector-block';

	/**
	 * Hook block registration into init.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', [ self::class, 'register_block' ] );
	}

	/**
	 * Register the block server-side.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register_block(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		$asset_url = trailingslashit( SITE_ESSENTIALS_URL ) . 'Modules/CustomPosts/FAQ/assets/faq-selector-block.js';

		wp_register_script(
			self::SCRIPT_HANDLE,
			$asset_url,
			[ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-api-fetch' ],
			defined( 'SITE_ESSENTIALS_VERSION' ) ? SITE_ESSENTIALS_VERSION : '1.0.0',
			true
		);

		register_block_type( FAQ_Module::BLOCK_NAME, [
			'api_version'     => 3,
			'editor_script'   => self::SCRIPT_HANDLE,
			'render_callback' => [ self::class, 'render' ],
			'attributes'      => [
				'selectedFaqs'  => [
					'type'    => 'array',
					'default' => [],
					'items'   => [ 'type' => 'number' ],
				],
				'displayFormat' => [
					'type'    => 'string',
					'default' => 'accordion', // accordion | plain
				],
				'headingLevel'  => [
					'type'    => 'string',
					'default' => 'h3', // h2 | h3 | h4 | p
				],
				'enableSchema'  => [
					'type'    => 'boolean',
					'default' => true,
				],
			],
		] );
	}

	/**
	 * Server-side render — returns block HTML only.
	 *
	 * Schema is contributed to the page @graph by FAQ_Schema_Graph, which
	 * also reads selectedFaqs from the post content via parse_blocks().
	 *
	 * @since 1.0.0
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render( $attributes ): string {
		$attrs = wp_parse_args( (array) $attributes, [
			'selectedFaqs'  => [],
			'displayFormat' => 'accordion',
			'headingLevel'  => 'h3',
			'enableSchema'  => true,
		] );

		$selected_faqs  = array_values( array_filter( array_map( 'intval', (array) $attrs['selectedFaqs'] ) ) );
		$display_format = in_array( $attrs['displayFormat'], [ 'accordion', 'plain' ], true )
			? $attrs['displayFormat']
			: 'accordion';
		$heading_level  = in_array( $attrs['headingLevel'], [ 'h2', 'h3', 'h4', 'p' ], true )
			? $attrs['headingLevel']
			: 'h3';

		if ( empty( $selected_faqs ) ) {
			return '<p>' . esc_html__( 'No FAQs selected.', 'site-essentials' ) . '</p>';
		}

		$faqs = FAQ_Module::get_by_ids( $selected_faqs );
		if ( empty( $faqs ) ) {
			return '<p>' . esc_html__( 'No FAQs found.', 'site-essentials' ) . '</p>';
		}

		ob_start();
		?>
		<div class="bw-faq-section" data-format="<?php echo esc_attr( $display_format ); ?>">
			<?php foreach ( $faqs as $faq ) : ?>
				<div class="bw-faq-item">
					<?php if ( 'accordion' === $display_format ) : ?>
						<details class="bw-faq-accordion">
							<summary class="bw-faq-question">
								<?php echo esc_html( get_the_title( $faq->ID ) ); ?>
							</summary>
							<div class="bw-faq-answer">
								<?php echo apply_filters( 'the_content', $faq->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						</details>
					<?php else : ?>
						<<?php echo esc_attr( $heading_level ); ?> class="bw-faq-question">
							<?php echo esc_html( get_the_title( $faq->ID ) ); ?>
						</<?php echo esc_attr( $heading_level ); ?>>
						<div class="bw-faq-answer">
							<?php echo apply_filters( 'the_content', $faq->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
