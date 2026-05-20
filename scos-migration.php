<?php
/**
 * SCOS Field Migration — DEPRECATED stub
 *
 * Replaces the legacy standalone migration mu-plugin. All client sites have
 * completed bw_* / altc_* → scos_* migration. Safe to delete this file from
 * mu-plugins once deployed everywhere; Site Essentials also removes the Tools
 * menu if an older copy of the full migration script is still present.
 *
 * @deprecated 2026-05
 * @package    BrighterWebsites
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'SCOS_MIGRATION_DEPRECATED' ) ) {
	define( 'SCOS_MIGRATION_DEPRECATED', true );
}
