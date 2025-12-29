<?php
/**
 * ALTC GA4 Integration
 *
 * File: class-altc-ga4-integration.php
 * Version: 1.0.0
 *
 * Responsibilities:
 * - Add ALTC parameters to existing GA4 tracking
 * - Inject altc_primary, altc_topic, content_maturity to page view events
 * - Ensure content_purpose is sent (from existing bw_purpose field)
 */

if (!defined('ABSPATH')) exit;

class BW_ALTC_GA4_Integration {

    /**
     * Initialize GA4 integration
     * 
     * NOTE: ALTC parameter injection has been moved to scos-car-injection.php
     * as part of the consolidated SCOS CAR (Content Architecture Record) structure.
     * 
     * The window.brighterContentStrategy object now includes:
     * - altc_primary (from bw_primary_altc_id)
     * - altc_topic (from bw_primary_topic_id)
     * - content_maturity (from bw_cont_maturity)
     * 
     * This class is kept for backwards compatibility but no longer injects data.
     * 
     * See: brighter-core/includes/scos-car-injection.php
     */
    public static function init() {
        // Injection moved to scos-car-injection.php
        // Kept for backwards compatibility
    }

    /**
     * DEPRECATED: Inject ALTC parameters into the brighterContentStrategy object
     * 
     * This method is no longer called. ALTC parameters are now injected by
     * scos-car-injection.php as part of the consolidated SCOS CAR structure.
     * 
     * Kept for reference only.
     */
    public static function inject_altc_params() {
        // DEPRECATED - Now handled by scos-car-injection.php
        // This method is no longer called but kept for reference
    }
}

// Initialize
BW_ALTC_GA4_Integration::init();
