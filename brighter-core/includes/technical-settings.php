<?php
/**
 * Brighter Tools: Options Page Technical Settings
 *
 * File: technical-settings.php
 * Purpose: hold toggles (on/off switches) for MU features.
 *
 * Version: 4.0.0
 *
 * Responsibilities:
 * - Add a new “Technical Settings” options page
 * - Add toggles & settings for hard coded optimisations
 * - 
 *
 * Notes:
 * Draft only will be Part of the Brighter Support Tools for Client Sites MU plugin 
 * not included in mu-plugins/brighter-core.php
 */


// Add a new “Technical Settings” options page
add_action('admin_menu', function () {
    add_submenu_page(
  'brighter_support',           // parent slug
  'Technical Settings',
  'Technical Settings',
  'manage_options',
  'brighter-technical-settings',
  'brighter_render_technical_settings_page'
);
});

function brighter_render_technical_settings_page() {
    ?>
    <div class="wrap">
        <h1>Technical Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('brighter_technical_settings');
            do_settings_sections('brighter-technical-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

//Register Litespeed Quota Management setting and toggle field
add_action('admin_init', function () {
    register_setting('brighter_technical_settings', 'brighter_run_lsc_enabled');

    add_settings_section(
        'brighter_technical_section',
        'LiteSpeed Quota Settings',
        '__return_false',
        'brighter-technical-settings'
    );

    add_settings_field(
        'brighter_run_lsc_enabled',
        'Enable Monthly LiteSpeed Quota Reset',
        function () {
            $value = get_option('brighter_run_lsc_enabled', 1);
            ?>
            <input type="checkbox" name="brighter_run_lsc_enabled" value="1" <?php checked(1, $value); ?> />
            <label for="brighter_run_lsc_enabled">Run monthly quota reset automatically</label>
            <?php
        },
        'brighter-technical-settings',
        'brighter_technical_section'
    );
});


/**
 * ================================================================
 * Comment Control
 * Register Toggle for Comments on Media Attachments
 * Default: ON (comments disabled)
 * Filter in custom-wpemail.php
 * ----------------------------------------------------------------- */

add_action('admin_init', function () {
    register_setting('brighter_technical_settings', 'brighter_disable_media_comments', [
        'type' => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default' => true, // default = comments disabled
    ]);

    add_settings_section(
        'brighter_comments_section',
        'Comment Behaviour',
        '__return_false',
        'brighter-technical-settings'
    );

    add_settings_field(
        'brighter_disable_media_comments',
        'Disable Media Comments',
        function () {
            $checked = get_option('brighter_disable_media_comments', true) ? 'checked' : '';
            echo '<label><input type="checkbox" name="brighter_disable_media_comments" value="1" ' . $checked . '> Prevent comments on media attachments</label>';
        },
        'brighter-technical-settings',
        'brighter_comments_section'
    );
});
