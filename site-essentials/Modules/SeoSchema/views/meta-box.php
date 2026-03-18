<?php
/**
 * Schema meta box — view
 *
 * Variables available:
 *   $post          WP_Post
 *   $custom_schema string (may be empty or pretty-printed JSON)
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div class="scos-schema-wrap">

	<div class="scos-schema-editor">
		<div class="scos-schema-toolbar">
			<span class="scos-schema-label">
				<?php esc_html_e( 'Custom JSON-LD', 'site-essentials' ); ?>
				<span class="scos-schema-badge scos-schema-badge--phase1">Phase 1 · Manual override</span>
			</span>
			<div class="scos-schema-actions">
				<button type="button" id="scos-schema-validate" class="button button-secondary">
					<?php esc_html_e( 'Validate JSON', 'site-essentials' ); ?>
				</button>
				<button type="button" id="scos-schema-format" class="button button-secondary">
					<?php esc_html_e( 'Format', 'site-essentials' ); ?>
				</button>
				<button type="button" id="scos-schema-clear" class="button button-link scos-schema-btn--clear"
					title="<?php esc_attr_e( 'Clear schema', 'site-essentials' ); ?>">
					<?php esc_html_e( 'Clear', 'site-essentials' ); ?>
				</button>
			</div>
		</div>

		<div id="scos-schema-status" class="scos-schema-status" aria-live="polite" hidden></div>

		<textarea
			id="scos-schema-custom"
			name="scos_schema_custom"
			class="scos-schema-textarea"
			rows="16"
			spellcheck="false"
			autocomplete="off"
			placeholder='<?php esc_attr_e( 'Paste your JSON-LD here, e.g. {"@context":"https://schema.org","@type":"Article",...}', 'site-essentials' ); ?>'
		><?php echo esc_textarea( $custom_schema ); ?></textarea>

		<p class="scos-schema-help">
			<?php esc_html_e( 'Enter valid JSON-LD schema markup. The schema.org @context and @type are required. This is output as-is in a', 'site-essentials' ); ?>
			<code>&lt;script type="application/ld+json"&gt;</code>
			<?php esc_html_e( 'tag. Multiple blocks can be added as an array:', 'site-essentials' ); ?>
			<code>[{"@type": "FAQPage", ...}, {"@type": "HowTo", ...}]</code>
		</p>
	</div>

	<?php if ( ! empty( $custom_schema ) ) :
		$decoded = json_decode( $custom_schema, true );
		$schema_type = isset( $decoded['@type'] ) ? $decoded['@type'] : null;
		$is_valid    = ( JSON_ERROR_NONE === json_last_error() );
	?>
	<div class="scos-schema-summary">
		<?php if ( $is_valid ) : ?>
			<span class="scos-schema-pill scos-schema-pill--valid">
				<span class="dashicons dashicons-yes-alt"></span>
				<?php esc_html_e( 'Valid JSON', 'site-essentials' ); ?>
			</span>
			<?php if ( $schema_type ) : ?>
				<span class="scos-schema-pill scos-schema-pill--type">
					<?php echo esc_html( $schema_type ); ?>
				</span>
			<?php endif; ?>
		<?php else : ?>
			<span class="scos-schema-pill scos-schema-pill--invalid">
				<span class="dashicons dashicons-warning"></span>
				<?php esc_html_e( 'Saved value is not valid JSON — not injected into output.', 'site-essentials' ); ?>
			</span>
		<?php endif; ?>
	</div>
	<?php endif; ?>

</div>
