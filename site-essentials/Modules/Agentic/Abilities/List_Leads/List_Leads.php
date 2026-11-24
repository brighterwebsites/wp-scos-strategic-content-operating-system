<?php
/**
 * List Leads — WordPress Ability
 *
 * Reads the lead table (Lead_Store): enquiries from this site's forms, from
 * agents' pre-filled links and from agents sending on a person's behalf.
 * Same query and reply as GET /scos/v1/leads, for an agent-run CRM or a
 * script that collects leads through MCP. Never public: needs the
 * scos_read_leads capability (Lead reader role, or an administrator).
 *
 * Tool ability — no AI inside, read-only.
 *
 * Ability slug: scos/list-leads (permanent — do not rename after deployment)
 * Category:     scos-agentic
 *
 * @package    SiteEssentials
 * @subpackage Modules\Agentic\Abilities\List_Leads
 *
 * v1.0 | 2026-11-24
 */

declare( strict_types=1 );

namespace SiteEssentials\Modules\Agentic\Abilities\List_Leads;

use SiteEssentials\Core\Abilities\Abstract_Scos_Ability;
use SiteEssentials\Core\Abilities\Ability_Support;
use SiteEssentials\Modules\Agentic\Lead_Store;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class List_Leads extends Abstract_Scos_Ability {

	public static function register(): void {
		if ( ! Ability_Support::is_abilities_api_available() ) {
			return;
		}
		wp_register_ability( 'scos/list-leads', [
			'label'         => __( 'SCOS: List Leads', 'site-essentials' ),
			'description'   => __( 'Lists the leads this site has received, oldest change first: enquiries typed into its forms (channel form), sent from an agent\'s pre-filled link (link), and sent by an agent and confirmed by the person (agent). Each lead has a stable id, a status (unverified, verified, expired), a standard contact block (name, email, phone, message) and every form field. To collect only new or changed leads, pass next_cursor from the previous reply as since; a lead comes back again when its status changes, so match on id. While has_more is true, call again. Read-only, no AI inside.', 'site-essentials' ),
			'category'      => 'scos-agentic',
			'ability_class' => self::class,
		] );
	}

	/** @return array<string, mixed> */
	public function input_schema(): array {
		return [
			'type'       => 'object',
			'default'    => [],
			'properties' => [
				'since'   => [ 'type' => 'string', 'description' => 'next_cursor from the previous reply. Leave out to start from the oldest lead.' ],
				'limit'   => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 500, 'description' => 'Default 100.' ],
				'status'  => [ 'type' => 'string', 'enum' => Lead_Store::STATUSES ],
				'channel' => [ 'type' => 'string', 'enum' => Lead_Store::CHANNELS ],
			],
		];
	}

	/** @return array<string, mixed> */
	public function output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'version'     => [ 'type' => 'integer', 'description' => 'Feed format version.' ],
				'leads'       => [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ],
				'next_cursor' => [ 'type' => [ 'string', 'null' ], 'description' => 'Pass as since on the next call.' ],
				'has_more'    => [ 'type' => 'boolean' ],
			],
		];
	}

	/**
	 * @param mixed $input Validated input array.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function execute_callback( $input ) {
		return Lead_Store::query( is_array( $input ) ? $input : [] );
	}

	/**
	 * @param mixed $input Validated input array.
	 * @return bool
	 */
	public function permission_callback( $input ) {
		return current_user_can( Lead_Store::CAPABILITY );
	}

	/** @return array<string, mixed> */
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

add_action( 'wp_abilities_api_init', [ List_Leads::class, 'register' ] );
