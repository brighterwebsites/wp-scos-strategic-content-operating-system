<?php
/**
 * Validate Redirects — WordPress Ability
 *
 * Checks 301 and 410 rules for loops, a missing "=>", relative destinations,
 * source paths that can never match, and rules that hide a published page.
 * Call it before writing to the scos_redirects_301 / scos_redirects_410
 * options by any route, so agent-written rules get the same check as the
 * Redirections tab.
 *
 * Tool ability — no AI inside, read-only. All logic lives in Redirect_Validator.
 *
 * Ability slug: scos/validate-redirects (permanent — do not rename after deployment)
 * Category:     scos-seo-meta
 *
 * @package    SiteEssentials
 * @subpackage Modules\SeoMeta\Abilities\Validate_Redirects
 *
 * v1.0 | 2026-10-25
 */

declare( strict_types=1 );

namespace SiteEssentials\Modules\SeoMeta\Abilities\Validate_Redirects;

use SiteEssentials\Core\Abilities\Abstract_Scos_Ability;
use SiteEssentials\Core\Abilities\Ability_Support;
use SiteEssentials\Modules\SeoMeta\Redirections;
use SiteEssentials\Modules\SeoMeta\Redirect_Validator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Validate_Redirects extends Abstract_Scos_Ability {

	/**
	 * Register this ability with the WordPress core Abilities API.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! Ability_Support::is_abilities_api_available() ) {
			return;
		}
		wp_register_ability( 'scos/validate-redirects', [
			'label'         => __( 'SCOS: Validate Redirects', 'site-essentials' ),
			'description'   => __( 'Checks 301 redirect rules and 410 Gone paths. Errors (the save would be refused): redirect loops, a missing "=>", relative destinations, source paths without a leading "/", full URLs or query strings as the source, the homepage. Warnings: capital letters (matching is case-sensitive), a rule that hides a published page, duplicates, a path in both lists, redirect chains, a destination that is 410. Pass proposed rule text to check it before saving; omit it to check the saved rules. Read-only, no AI inside.', 'site-essentials' ),
			'category'      => 'scos-seo-meta',
			'ability_class' => self::class,
			'meta'          => [
				'show_in_rest' => true,
				'mcp'          => [
					'public' => true,
					'type'   => 'tool',
				],
			],
		] );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function input_schema(): array {
		return [
			'type'       => 'object',
			'default'    => [],
			'properties' => [
				'rules_301' => [
					'type'        => 'string',
					'description' => 'Proposed 301 rule text, one "/old-path => /new-path" (or "=> https://…") per line; "#" lines are comments. Omit to check the saved 301 rules.',
				],
				'rules_410' => [
					'type'        => 'string',
					'description' => 'Proposed 410 path list, one "/path" per line. Omit to check the saved 410 paths.',
				],
			],
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function output_schema(): array {
		$issues = [
			'type'  => 'array',
			'items' => [
				'type'       => 'object',
				'properties' => [
					'list'    => [ 'type' => 'string', 'description' => '301 or 410.' ],
					'line'    => [ 'type' => 'integer', 'description' => '1-based line in that list.' ],
					'rule'    => [ 'type' => 'string' ],
					'code'    => [ 'type' => 'string' ],
					'message' => [ 'type' => 'string' ],
				],
			],
		];
		return [
			'type'       => 'object',
			'properties' => [
				'valid'    => [
					'type'        => 'boolean',
					'description' => 'False when any rule has an error. The Redirections tab refuses to save invalid rules.',
				],
				'errors'   => $issues,
				'warnings' => $issues,
				'counts'   => [
					'type'        => 'object',
					'description' => 'rules_301, paths_410, errors, warnings.',
				],
			],
		];
	}

	/**
	 * Execute the ability — return the report.
	 *
	 * @param mixed $input Validated input array.
	 * @return array<string, mixed>
	 */
	public function execute_callback( $input ) {
		$input = is_array( $input ) ? $input : [];

		return Redirect_Validator::validate(
			isset( $input['rules_301'] ) ? (string) $input['rules_301'] : Redirections::get_301_raw(),
			isset( $input['rules_410'] ) ? (string) $input['rules_410'] : Redirections::get_410_raw()
		);
	}

	/**
	 * Same capability as saving the rules on the Redirections tab.
	 *
	 * @param mixed $input Validated input array.
	 * @return bool
	 */
	public function permission_callback( $input ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function meta(): array {
		return [
			'show_in_rest' => true,
			'annotations'  => [
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			],
			'mcp'          => [
				'public' => true,
				'type'   => 'tool',
			],
		];
	}
}

add_action( 'wp_abilities_api_init', [ Validate_Redirects::class, 'register' ] );
