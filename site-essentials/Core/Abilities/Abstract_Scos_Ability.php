<?php
/**
 * SCOS Ability base class.
 *
 * Base class for every SCOS ability. Extends the WordPress core Abilities API
 * (`WP_Ability`, shipped in `wp-includes/abilities-api/` since WP 6.9) so that
 * abilities register on any site running a supported WordPress version,
 * whether or not the `ai` plugin is installed.
 *
 * SCOS abilities previously extended `WordPress\AI\Abstracts\Abstract_Ability`,
 * which lives in the `ai` plugin. That coupled registration to the plugin: on a
 * site without it the ability files were never loaded, so MCP agents could not
 * discover any `scos/*` ability. It also silently discarded the `category`
 * passed at registration in favour of the plugin's own default, which is why
 * every SCOS ability reported `ai-experiments` instead of its `scos-*`
 * category. This class honours the category it is given.
 *
 * AI generation is a runtime concern, not a registration concern. Abilities
 * that generate text call the WordPress AI Client inside their execute
 * callback and degrade to a WP_Error when it is unavailable — see
 * Ability_Support.
 *
 * @package    SiteEssentials
 * @subpackage Core\Abilities
 * @since      1.3.0
 *
 * v1.0 | 2026-09-29
 * v1.1 | 2026-09-30 — ensure_text_generation_supported() no longer rejects core's prompt builder.
 */

declare( strict_types=1 );

namespace SiteEssentials\Core\Abilities;

use ReflectionClass;
use WP_Ability;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Abstract_Scos_Ability extends WP_Ability {

	/**
	 * Category used when a subclass registers without one.
	 *
	 * Registration fails outright if the category is not registered, so this is
	 * a last resort rather than a useful default — always pass a category.
	 */
	const DEFAULT_CATEGORY = 'scos-content-architecture';

	/**
	 * Constructor.
	 *
	 * Core's registry validates `$properties` and then instantiates this class
	 * with them. The schemas, callbacks and meta are declared as methods on the
	 * subclass, so they are assembled here and handed to WP_Ability.
	 *
	 * The execute and permission callbacks are wrapped in closures rather than
	 * passed as `[ $this, 'method' ]` so that subclasses may keep them
	 * protected — the closure carries class scope.
	 *
	 * @since 1.3.0
	 *
	 * @param string              $name       Ability name, e.g. `scos/suggest-tldr`.
	 * @param array<string,mixed> $properties Properties from wp_register_ability().
	 */
	public function __construct( string $name, array $properties = array() ) {
		parent::__construct(
			$name,
			array(
				'label'               => $properties['label'] ?? '',
				'description'         => $properties['description'] ?? '',
				'category'            => $properties['category'] ?? static::DEFAULT_CATEGORY,
				'input_schema'        => $this->input_schema(),
				'output_schema'       => $this->output_schema(),
				'execute_callback'    => function ( $input = null ) {
					return $this->execute_callback( $input );
				},
				'permission_callback' => function ( $input = null ) {
					return $this->permission_callback( $input );
				},
				'meta'                => $this->meta(),
			)
		);
	}

	// -------------------------------------------------------------------------
	// Contract for subclasses
	// -------------------------------------------------------------------------

	/**
	 * JSON Schema for the input this ability accepts.
	 *
	 * @since 1.3.0
	 * @return array<string,mixed>
	 */
	abstract protected function input_schema(): array;

	/**
	 * JSON Schema for the output this ability returns.
	 *
	 * @since 1.3.0
	 * @return array<string,mixed>
	 */
	abstract protected function output_schema(): array;

	/**
	 * Run the ability.
	 *
	 * @since 1.3.0
	 * @param mixed $input Validated input.
	 * @return mixed|WP_Error
	 */
	abstract protected function execute_callback( $input );

	/**
	 * Whether the current user may run the ability.
	 *
	 * @since 1.3.0
	 * @param mixed $input Validated input.
	 * @return bool|WP_Error
	 */
	abstract protected function permission_callback( $input );

	/**
	 * Ability metadata — REST and MCP exposure.
	 *
	 * @since 1.3.0
	 * @return array<string,mixed>
	 */
	abstract protected function meta(): array;

	// -------------------------------------------------------------------------
	// System instructions
	// -------------------------------------------------------------------------

	/**
	 * Editorial guideline categories to append to the system instruction.
	 *
	 * Only has an effect when the `ai` plugin is present, since it owns the
	 * guidelines store. Valid values: site, copy, images, additional.
	 *
	 * @since 1.3.0
	 * @return array<string>
	 */
	protected function guideline_categories(): array {
		return array();
	}

	/**
	 * Get the system instruction for this ability.
	 *
	 * Loads `system-instruction.php` from the directory holding the subclass,
	 * located by reflection, and expects it to return a string. Keeping the
	 * lookup reflection-based preserves the existing file layout: every
	 * ability's instruction sits beside its class.
	 *
	 * @since 1.3.0
	 *
	 * @param string|null          $filename Optional explicit filename.
	 * @param array<string,mixed>  $data     Optional variables exposed to the file.
	 * @return string The system instruction, or an empty string when absent.
	 */
	public function get_system_instruction( ?string $filename = null, array $data = array() ): string {
		$block_name = null;
		if ( isset( $data['block_name'] ) && is_string( $data['block_name'] ) ) {
			$block_name = $data['block_name'];
			unset( $data['block_name'] );
		}

		$instruction = $this->load_system_instruction_from_file( $filename, $data );

		if ( '' !== $instruction ) {
			$guidelines = Ability_Support::get_guidelines_for_prompt(
				$this->guideline_categories(),
				$block_name
			);

			if ( '' !== $guidelines ) {
				$instruction .= "\n\n" . __( "The following guidelines represent the site's editorial standards. Apply them where relevant. Do not fabricate content to satisfy guidelines. If guidelines conflict with the input, prioritize accuracy.", 'site-essentials' );
				$instruction .= "\n\n" . $guidelines;
			}
		}

		/**
		 * Filters the system instruction for a SCOS ability.
		 *
		 * @since 1.3.0
		 *
		 * @param string              $instruction The system instruction text.
		 * @param string              $name        The ability name.
		 * @param array<string,mixed> $data        Data passed to the instruction file.
		 */
		return (string) apply_filters( 'scos_ability_system_instruction', $instruction, (string) $this->get_name(), $data );
	}

	/**
	 * Load the system instruction file sitting beside the subclass.
	 *
	 * @since 1.3.0
	 *
	 * @param string|null         $filename Optional explicit filename.
	 * @param array<string,mixed> $data     Optional variables exposed to the file.
	 * @return string
	 */
	protected function load_system_instruction_from_file( ?string $filename = null, array $data = array() ): string {
		$reflection = new ReflectionClass( $this );
		$class_file = $reflection->getFileName();

		if ( ! $class_file ) {
			return '';
		}

		$file_path = trailingslashit( dirname( $class_file ) ) . ( $filename ?? 'system-instruction.php' );

		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return '';
		}

		if ( ! empty( $data ) ) {
			extract( $data, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		}

		$content = require $file_path; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable

		return is_string( $content ) ? $content : '';
	}

	// -------------------------------------------------------------------------
	// Generation guards
	// -------------------------------------------------------------------------

	/**
	 * Confirm the prompt builder can run text generation.
	 *
	 * Also catches the case where the AI Client is absent entirely, so callers
	 * get one WP_Error to surface rather than a fatal on a missing method.
	 *
	 * @since 1.3.0
	 *
	 * @param mixed  $prompt_builder The configured prompt builder, or a WP_Error.
	 * @param string $message        User-visible error message.
	 * @return mixed|WP_Error The prompt builder, or a WP_Error.
	 */
	protected function ensure_text_generation_supported( $prompt_builder, string $message ) {
		if ( is_wp_error( $prompt_builder ) ) {
			return $prompt_builder;
		}

		// is_callable, not method_exists: core's prompt builder answers this
		// through __call, so method_exists is false even though the call works.
		if ( ! is_object( $prompt_builder ) || ! is_callable( array( $prompt_builder, 'is_supported_for_text_generation' ) ) ) {
			return new WP_Error( 'scos_ai_client_unavailable', $message, array( 'status' => 503 ) );
		}

		if ( ! $prompt_builder->is_supported_for_text_generation() ) {
			return new WP_Error( 'unsupported_model', $message, array( 'status' => 503 ) );
		}

		return $prompt_builder;
	}
}
