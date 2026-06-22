<?php
/**
 * Agentic Module
 *
 * v1.0 | 2026-06-22
 *
 * Handles AI agent discovery and content accessibility for client sites.
 * MVP: ?format=md plain-text rendering. Future: ARD catalog, Abilities API tools.
 *
 * @package    SiteEssentials
 * @subpackage Modules\Agentic
 * @since      1.0.0
 */

namespace SiteEssentials\Modules\Agentic;

use SiteEssentials\Core\Module_Interface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Agentic_Module implements Module_Interface {

	// ── Module_Interface metadata ─────────────────────────────────────────────

	public static function get_id(): string {
		return 'agentic';
	}

	public static function get_name(): string {
		return __( 'Agentic', 'site-essentials' );
	}

	public static function get_description(): string {
		return __( 'AI agent discovery and content accessibility — plain-text rendering, discovery signals, and capability exposure.', 'site-essentials' );
	}

	public static function get_tier(): string {
		return 'basic';
	}

	public static function get_dependencies(): array {
		return [];
	}

	public static function get_version(): string {
		return '1.0';
	}

	// ── Lifecycle ─────────────────────────────────────────────────────────────

	public function init(): void {
		if ( ! defined( 'SCOS_AGENTIC_ACTIVE' ) ) {
			define( 'SCOS_AGENTIC_ACTIVE', true );
		}

		Markdown_Renderer::init();
	}

	// ── Admin ─────────────────────────────────────────────────────────────────

	public function render_settings(): void {
		$enabled = (bool) get_option( 'scos_agentic_markdown_enabled', false );
		include __DIR__ . '/views/settings.php';
	}
}
