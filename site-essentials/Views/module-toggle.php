<?php
/**
 * Module Toggle Template
 *
 * Individual module toggle card.
 *
 * @package    SiteEssentials
 * @subpackage Views
 * @version    1.0.0
 *
 * Variables available:
 * @var string $module_id    Module ID
 * @var string $name         Module name
 * @var string $description  Module description
 * @var string $tier         Module tier (basic, pro, agency)
 * @var array  $dependencies Module dependencies
 * @var bool   $is_enabled   Is module enabled
 * @var bool   $is_loaded    Is module loaded
 * @var bool   $has_failed   Did module fail to load
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$tier_class = 'tier-' . esc_attr($tier);
$status_class = $is_enabled ? 'enabled' : 'disabled';
if ($has_failed) {
    $status_class .= ' failed';
}
?>

<div class="se-module-card <?php echo esc_attr($tier_class . ' ' . $status_class); ?>"
     data-module-id="<?php echo esc_attr($module_id); ?>">

    <div class="se-module-header">
        <div class="se-module-title">
            <h3><?php echo esc_html($name); ?></h3>
            <span class="se-module-tier tier-<?php echo esc_attr($tier); ?>">
                <?php echo esc_html(ucfirst($tier)); ?>
            </span>
        </div>

        <label class="se-toggle">
            <input type="checkbox"
                   class="se-module-toggle"
                   data-module-id="<?php echo esc_attr($module_id); ?>"
                   <?php checked($is_enabled); ?>>
            <span class="se-toggle-slider"></span>
        </label>
    </div>

    <div class="se-module-body">
        <p class="se-module-description"><?php echo esc_html($description); ?></p>

        <?php if (!empty($dependencies)): ?>
            <p class="se-module-dependencies">
                <strong><?php esc_html_e('Dependencies:', 'site-essentials'); ?></strong>
                <?php echo esc_html(implode(', ', $dependencies)); ?>
            </p>
        <?php endif; ?>

        <div class="se-module-status">
            <?php if ($has_failed): ?>
                <span class="status-indicator failed">
                    ✗ <?php esc_html_e('Failed to load', 'site-essentials'); ?>
                </span>
            <?php elseif ($is_loaded): ?>
                <span class="status-indicator loaded">
                    ✓ <?php esc_html_e('Loaded', 'site-essentials'); ?>
                </span>
            <?php elseif ($is_enabled): ?>
                <span class="status-indicator enabled">
                    ○ <?php esc_html_e('Enabled (will load on next page load)', 'site-essentials'); ?>
                </span>
            <?php else: ?>
                <span class="status-indicator disabled">
                    ○ <?php esc_html_e('Disabled', 'site-essentials'); ?>
                </span>
            <?php endif; ?>
        </div>
    </div>
</div>
