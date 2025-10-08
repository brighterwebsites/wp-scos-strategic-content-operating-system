<?php
/**
 * Brighter Tools: PHP Limits
 *
 * File: php-limits.php
 * Purpose: Adjust default PHP resource limits (memory, timeouts, and upload/post limits)
 * to ensure WordPress sites have adequate capacity for admin tasks and plugin operations.
 *
 * Version: 4.0.0
 *
 * Responsibilities:
 * - Define `WP_MEMORY_LIMIT` and `WP_MAX_MEMORY_LIMIT` if not already set.
 * - Increase execution and input timeouts to a minimum of 300 seconds.
 * - Raise `post_max_size`, `upload_max_filesize`, and `max_input_vars` if lower than recommended.
 *
 * Notes:
 * - Runs early via MU plugin, ensuring these values are applied before most plugins/themes load.
 * - Uses conditional checks to avoid overriding higher values set at server or php.ini level.
 * - These settings are site-specific and may be overridden by host-level config (php.ini or .htaccess).
 * - No LiteSpeed quota logic here — no update needed for LS toggle.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Memory Limits
if ( ! defined( 'WP_MEMORY_LIMIT' ) ) {
    define( 'WP_MEMORY_LIMIT', '256M' );
}
if ( ! defined( 'WP_MAX_MEMORY_LIMIT' ) ) {
    define( 'WP_MAX_MEMORY_LIMIT', '512M' );
}

// Timeouts
if ( ! ini_get( 'max_execution_time' ) || ini_get( 'max_execution_time' ) < 300 ) {
    ini_set( 'max_execution_time', '300' );
}
if ( ! ini_get( 'max_input_time' ) || ini_get( 'max_input_time' ) < 300 ) {
    ini_set( 'max_input_time', '300' );
}

// Upload/Post Limits
if ( ! ini_get( 'post_max_size' ) || (int) ini_get( 'post_max_size' ) < 64 ) {
    ini_set( 'post_max_size', '64M' );
}
if ( ! ini_get( 'upload_max_filesize' ) || (int) ini_get( 'upload_max_filesize' ) < 64 ) {
    ini_set( 'upload_max_filesize', '64M' );
}
if ( ! ini_get( 'max_input_vars' ) || (int) ini_get( 'max_input_vars' ) < 3000 ) {
    ini_set( 'max_input_vars', '3000' );
}

