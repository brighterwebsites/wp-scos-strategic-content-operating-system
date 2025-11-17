<?php
/**
 * Module Interface
 *
 * Defines the contract that all Site Essentials modules must implement.
 * This ensures consistency across all modules and enables the Module_Loader
 * to dynamically load and manage modules.
 *
 * @package    SiteEssentials
 * @subpackage Core
 * @version    1.0.0
 * @since      1.0.0
 */

namespace SiteEssentials\Core;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Module Interface
 *
 * All modules must implement this interface to be loadable by the Module_Loader.
 *
 * @since 1.0.0
 */
interface Module_Interface {
    /**
     * Get the unique module ID
     *
     * Used for settings storage, toggles, and dependencies.
     * Must be unique across all modules.
     *
     * @since  1.0.0
     * @return string Module ID (e.g., 'tweaks', 'analytics', 'faq')
     */
    public static function get_id();

    /**
     * Get the human-readable module name
     *
     * Displayed in admin UI and settings pages.
     *
     * @since  1.0.0
     * @return string Module name (e.g., 'WordPress Tweaks', 'Analytics')
     */
    public static function get_name();

    /**
     * Get the module description
     *
     * Short description of what the module does.
     * Displayed in admin UI to help users understand the module.
     *
     * @since  1.0.0
     * @return string Module description
     */
    public static function get_description();

    /**
     * Get the module tier
     *
     * Determines which pricing tier this module belongs to.
     * Used for licensing and feature gating.
     *
     * @since  1.0.0
     * @return string Tier ('basic', 'pro', or 'agency')
     */
    public static function get_tier();

    /**
     * Get module dependencies
     *
     * Returns an array of module IDs that this module depends on.
     * Module_Loader will check these before loading the module.
     *
     * @since  1.0.0
     * @return array Array of module IDs (e.g., ['business_info', 'analytics'])
     */
    public static function get_dependencies();

    /**
     * Get the module version
     *
     * Used for tracking module versions independently from core.
     * Important for standalone module extraction.
     *
     * @since  1.0.0
     * @return string Version number (e.g., '1.0.0')
     */
    public static function get_version();

    /**
     * Initialize the module
     *
     * Called by Module_Loader when the module is enabled and dependencies are met.
     * This is where you register hooks, enqueue assets, and set up your module.
     *
     * @since 1.0.0
     * @return void
     */
    public function init();

    /**
     * Render module settings
     *
     * Called by Admin_UI to display this module's settings section.
     * Use WordPress Settings API or custom HTML.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_settings();
}
