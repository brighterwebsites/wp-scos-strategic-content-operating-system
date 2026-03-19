<?php
/**
 * Tweaks Module Settings View
 *
 * @package    SiteEssentials
 * @subpackage Modules\Tweaks
 * @version    1.1.0
 *
 * Variables available:
 * @var array $tweaks Current tweak settings
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$groups = [
    'performance' => [
        'label' => __( 'Performance & Speed', 'site-essentials' ),
        'tweaks' => [
            'disable_emojis' => [
                'label'       => __( 'Disable Emojis', 'site-essentials' ),
                'description' => __( 'Removes the WordPress emoji script, inline CSS, and DNS-prefetch hint. Saves one HTTP request per page load. Safe to enable on any site — native OS emoji still render.', 'site-essentials' ),
            ],
            'remove_jquery_migrate' => [
                'label'       => __( 'Remove jQuery Migrate', 'site-essentials' ),
                'description' => __( 'Drops the jQuery Migrate shim (~10 KB). Only enable if no plugins or themes depend on deprecated jQuery APIs — check the browser console for migration warnings first.', 'site-essentials' ),
            ],
            'optimize_heartbeat' => [
                'label'       => __( 'Slow Down Heartbeat', 'site-essentials' ),
                'description' => __( 'Reduces the WordPress Heartbeat API polling from every 15 s to 60 s and disables it on the front end. Lowers admin-ajax.php requests and server CPU on busy sites.', 'site-essentials' ),
            ],
            'remove_query_strings' => [
                'label'       => __( 'Remove Query Strings from CSS/JS', 'site-essentials' ),
                'description' => __( 'Strips the <code>?ver=</code> parameter from frontend CSS and JS URLs. Improves cache hit rate for proxies and CDNs that refuse to cache URLs with query strings. <strong>Not applied in the admin</strong> so deploy cache-busting still works.', 'site-essentials' ),
            ],
            'disable_embeds_outbound' => [
                'label'       => __( 'Disable Outbound Embeds', 'site-essentials' ),
                'description' => __( 'Prevents WordPress auto-converting raw pasted URLs (YouTube, Twitter, etc.) into embedded players. Saves the oEmbed HTTP fetch on page load. Use when your page builder (e.g. Breakdance) handles embeds, or you want URLs to stay as plain links.', 'site-essentials' ),
            ],
            'remove_google_fonts' => [
                'label'       => __( 'Remove Google Fonts', 'site-essentials' ),
                'description' => __( 'Strips all <code>fonts.googleapis.com</code> <code>&lt;link&gt;</code> tags and <code>@import</code> rules from the page. Use when you self-host fonts or your theme loads Google Fonts you no longer need. Add preload tags via the <strong>Asset Preloads</strong> tab if replacing with self-hosted files.', 'site-essentials' ),
            ],
        ],
    ],
    'security' => [
        'label' => __( 'Security & Hardening', 'site-essentials' ),
        'tweaks' => [
            'disable_xmlrpc' => [
                'label'       => __( 'Disable XML-RPC', 'site-essentials' ),
                'description' => __( 'Rejects all XML-RPC POST requests and strips the <code>X-Pingback</code> header. Blocks a common brute-force vector. Enable unless you use the WordPress mobile app, Jetpack, or a service that requires XML-RPC. <em>Note: visiting <code>/xmlrpc.php</code> directly in a browser always shows "accepts POST requests only" — this is WordPress core behavior and is normal whether XML-RPC is enabled or disabled.</em>', 'site-essentials' ),
            ],
            'disable_rest_api' => [
                'label'       => __( 'Restrict REST API to Logged-In Users', 'site-essentials' ),
                'description' => __( 'Returns a 401 error for unauthenticated REST API requests. Protects user enumeration and content exposure. <strong>WooCommerce, Brighter, and other whitelisted endpoints remain open.</strong> Verify no public-facing integrations break before enabling.', 'site-essentials' ),
            ],
        ],
    ],
    'seo_cleanup' => [
        'label' => __( 'SEO & Metadata Code Cleanup', 'site-essentials' ),
        'tweaks' => [
            'remove_rsd_link' => [
                'label'       => __( 'Remove RSD Link', 'site-essentials' ),
                'description' => __( 'Removes the <code>&lt;link rel="EditURI"&gt;</code> Really Simple Discovery tag from <code>&lt;head&gt;</code>. Only needed by desktop blog editors (MarsEdit, etc.). Zero SEO or user-facing impact.', 'site-essentials' ),
            ],
            'remove_wlw_link' => [
                'label'       => __( 'Remove Windows Live Writer Link', 'site-essentials' ),
                'description' => __( 'Removes the <code>&lt;link rel="wlwmanifest"&gt;</code> tag. Cleans up a legacy <code>&lt;head&gt;</code> tag with no modern use.', 'site-essentials' ),
            ],
            'remove_wp_version' => [
                'label'       => __( 'Remove WordPress Version Tag', 'site-essentials' ),
                'description' => __( 'Strips <code>&lt;meta name="generator" content="WordPress X.X.X"&gt;</code> from the page. Minor security hardening — removes an easy version-fingerprint. Does not affect RSS feeds or API responses.', 'site-essentials' ),
            ],
            'disable_embeds_inbound' => [
                'label'       => __( 'Disable Inbound Embeds', 'site-essentials' ),
                'description' => __( 'Stops other sites from easily embedding <em>your</em> content. Removes the oEmbed discovery <code>&lt;link&gt;</code> tags, <code>embed.js</code>, and the REST API endpoint from your <code>&lt;head&gt;</code>. Zero impact on what you can embed from external sites.', 'site-essentials' ),
            ],
        ],
    ],
];
?>

<div class="se-module-settings-tweaks">
    <p style="margin-bottom:20px"><?php esc_html_e( 'Enable or disable individual WordPress tweaks. Only enabled tweaks load any code — disabled options add zero overhead.', 'site-essentials' ); ?></p>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'site_essentials_tweaks', 'site_essentials_tweaks_nonce' ); ?>
        <input type="hidden" name="action" value="site_essentials_save_tweaks">

        <?php foreach ( $groups as $group_id => $group ) : ?>
            <h3 style="margin:28px 0 4px;padding-bottom:8px;border-bottom:1px solid #dcdcde;font-size:14px;font-weight:600">
                <?php echo esc_html( $group['label'] ); ?>
            </h3>
            <table class="form-table" role="presentation" style="margin-top:0">
                <tbody>
                    <?php foreach ( $group['tweaks'] as $tweak_id => $tweak_data ) : ?>
                        <tr>
                            <th scope="row" style="width:220px">
                                <label for="tweak_<?php echo esc_attr( $tweak_id ); ?>">
                                    <?php echo esc_html( $tweak_data['label'] ); ?>
                                </label>
                            </th>
                            <td>
                                <label style="display:flex;align-items:flex-start;gap:8px">
                                    <input type="checkbox"
                                           id="tweak_<?php echo esc_attr( $tweak_id ); ?>"
                                           name="enabled_tweaks[<?php echo esc_attr( $tweak_id ); ?>]"
                                           value="1"
                                           style="margin-top:3px;flex-shrink:0"
                                           <?php checked( ! empty( $tweaks[ $tweak_id ] ) ); ?>>
                                    <span class="description" style="line-height:1.5"><?php echo wp_kses( $tweak_data['description'], [ 'code' => [], 'strong' => [], 'em' => [] ] ); ?></span>
                                </label>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endforeach; ?>

        <p class="submit" style="margin-top:24px">
            <button type="submit" class="button button-primary">
                <?php esc_html_e( 'Save Settings', 'site-essentials' ); ?>
            </button>
        </p>
    </form>

    <div class="notice notice-info inline" style="margin-top:8px">
        <p>
            <strong><?php esc_html_e( 'Note:', 'site-essentials' ); ?></strong>
            <?php esc_html_e( 'Changes take effect on the next page load. If something breaks, uncheck the relevant option and save again.', 'site-essentials' ); ?>
        </p>
    </div>
</div>
