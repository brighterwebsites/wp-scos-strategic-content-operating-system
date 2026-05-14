<?php
/**
 * Tweaks Module Settings View
 *
 * @package    SiteEssentials
 * @subpackage Modules\Tweaks
 * @version    1.2.0
 *
 * Variables available:
 * @var array $tweaks Current tweak settings
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$groups = [
    'performance' => [
        'label'     => __( 'Performance & Speed', 'site-essentials' ),
        'guide_url' => 'https://brighterwebsites.com.au/software/performance/wordpress-performance-tweaks/#performance-speed',
        'tweaks'    => [
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
                'description' => __( 'Stops WordPress auto-converting plain pasted URLs (YouTube, Vimeo, Twitter, etc.) into embedded players in your content. Use when your page builder handles embeds, or you want URLs to stay as links. The <code>[embed]</code> shortcode still works if you need explicit embeds.', 'site-essentials' ),
            ],
            'remove_google_fonts' => [
                'label'       => __( 'Remove Google Fonts', 'site-essentials' ),
                'description' => wp_kses(
                    sprintf(
                        /* translators: 1: asset-preloading tab URL, 2: font optimisation guide URL */
                        __( 'Dequeues and strips all <code>fonts.googleapis.com</code> stylesheet links from the page — including Breakdance\'s font handle. Use when you self-host fonts or no longer need Google Fonts loaded by your theme or page builder.<br><br>For self-hosted fonts: add woff2 preload tags in <a href="%1$s">Asset Preloading &rarr; Font Preload</a>.<br><br><strong>LiteSpeed Cache recommended settings when this is ON:</strong> Remove Google Fonts &rarr; ON (removes the stylesheet request) &bull; Load Google Fonts Asynchronously &rarr; OFF (prevents fonts.googleapis.com being re-injected via LiteSpeed\'s own pipeline).<br><br><a href="%2$s" target="_blank" rel="noopener noreferrer">Full guide: Breakdance + LiteSpeed + Google Font Optimisation Best Practice</a>', 'site-essentials' ),
                        esc_url( admin_url( 'admin.php?page=site-essentials-essentials&tab=asset-preloading#font-preload' ) ),
                        'https://brighterwebsites.com.au/software/performance/font-optimisation/'
                    ),
                    [ 'code' => [], 'strong' => [], 'em' => [], 'br' => [], 'a' => [ 'href' => [], 'target' => [], 'rel' => [] ] ]
                ),
            ],
        ],
    ],
    'security' => [
        'label'     => __( 'Security & Hardening', 'site-essentials' ),
        'guide_url' => 'https://brighterwebsites.com.au/software/performance/wordpress-performance-tweaks/#security',
        'tweaks'    => [
            'disable_xmlrpc' => [
                'label'       => __( 'Disable XML-RPC', 'site-essentials' ),
                'description' => __( 'Rejects all XML-RPC POST requests and strips the <code>X-Pingback</code> header. Blocks a common brute-force vector. Enable unless you use the WordPress mobile app, Jetpack, or a service that requires XML-RPC. <em>Note: visiting <code>/xmlrpc.php</code> directly in a browser always shows "accepts POST requests only" — this is WordPress core behavior and is normal whether XML-RPC is enabled or disabled.</em>', 'site-essentials' ),
            ],
            'disable_rest_api' => [
                'label'       => __( 'Restrict REST API to Logged-In Users', 'site-essentials' ),
                'description' => __( 'Returns a 401 error for unauthenticated REST API requests. Protects user enumeration and content exposure. <strong>WooCommerce, Brighter, and other whitelisted endpoints remain open.</strong> Verify no public-facing integrations break before enabling.', 'site-essentials' ),
            ],
            'restrict_rest_users' => [
                'label'       => __( 'Restrict REST API Users Endpoint', 'site-essentials' ),
                'description' => __( 'Removes <code>/wp/v2/users</code> from the REST API for unauthenticated requests. Logged-in users retain access. Prevents public username enumeration without affecting plugins that require authenticated REST user lookups. Use alongside "Restrict REST API to Logged-In Users" for full coverage, or use this alone for a lighter restriction.', 'site-essentials' ),
            ],
        ],
    ],
    'seo_cleanup' => [
        'label'     => __( 'SEO & Metadata Code Cleanup', 'site-essentials' ),
        'guide_url' => 'https://brighterwebsites.com.au/software/performance/wordpress-performance-tweaks/#meta-code',
        'tweaks'    => [
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
            'remove_shortlink' => [
                'label'       => __( 'Remove Shortlink Tag', 'site-essentials' ),
                'description' => __( 'Removes <code>&lt;link rel="shortlink" href="/?p=123"&gt;</code> from the page <code>&lt;head&gt;</code> and the <code>Link:</code> HTTP header. No SEO value; just cleans up head clutter.', 'site-essentials' ),
            ],
            'remove_rest_api_links' => [
                'label'       => __( 'Remove REST API Discovery Links', 'site-essentials' ),
                'description' => __( 'Removes <code>&lt;link rel="https://api.w.org/"&gt;</code> and the per-page <code>&lt;link rel="alternate" type="application/json"&gt;</code> from <code>&lt;head&gt;</code>. These advertise your REST API URL and internal post IDs to crawlers. Pair with <strong>Restrict REST API to Logged-In Users</strong> for full cleanup — if the API is locked anyway, the discovery links serve no purpose.', 'site-essentials' ),
            ],
            'disable_embeds_inbound' => [
                'label'       => __( 'Disable Inbound Embeds', 'site-essentials' ),
                'description' => __( 'Stops other sites from discovering and embedding <em>your</em> content. Removes the oEmbed discovery <code>&lt;link&gt;</code> tags and the REST API endpoint. Zero impact on outbound embeds (YouTube, etc. still work on your pages).', 'site-essentials' ),
            ],
            'disable_rss_feeds' => [
                'label'       => __( 'Disable RSS Feeds', 'site-essentials' ),
                'description' => __( 'Disables all RSS feed generation (post feeds, comment feeds, category feeds) and removes <code>&lt;link rel="alternate"&gt;</code> tags from <code>&lt;head&gt;</code>. Reduces crawl budget waste and prevents duplicate content in Google Search Console. <strong>Note:</strong> Also update <code>robots.txt</code> to remove any feed disallow rules.', 'site-essentials' ),
            ],
            'disable_relational_links' => [
                'label'       => __( 'Disable Relational Links', 'site-essentials' ),
                'description' => __( 'Removes <code>rel="prev"</code> and <code>rel="next"</code> link tags from single posts and pages. These can confuse pagination signals. Safe to disable on sites with custom pagination.', 'site-essentials' ),
            ],
            'disable_gutenberg_block_library' => [
                'label'       => __( 'Remove Gutenberg Block Library CSS', 'site-essentials' ),
                'description' => __( 'Aggressively removes Gutenberg block library CSS from the frontend. Safe for Breakdance-only sites (no native blocks in use). Dequeues: <code>wp-block-library</code>, <code>wp-block-library-theme</code>, <code>wp-block-*</code> variants, and <code>wc-block-style</code>.', 'site-essentials' ),
            ],
            'disable_dashicons_frontend' => [
                'label'       => __( 'Disable Dashicons for Logged-Out Users', 'site-essentials' ),
                'description' => __( 'Prevents <code>dashicons.css</code> from loading on the frontend for logged-out users. Dashicons are only needed in the WordPress admin. Saves ~6 KB.', 'site-essentials' ),
            ],
        ],
    ],
    'admin_ui' => [
        'label'     => __( 'Admin UX/UI', 'site-essentials' ),
        'guide_url' => '',
        'tweaks'    => [
            'allow_editors_form_submissions' => [
                'label'       => __( 'Allow Editors to View Form Submissions', 'site-essentials' ),
                'description' => __( 'Grants users with the <code>edit_posts</code> capability (Editors and above) access to Breakdance form submission data. By default, only Admins can view submissions.', 'site-essentials' ),
            ],
        ],
    ],
];

$allowed_desc_tags = [ 'code' => [], 'strong' => [], 'em' => [], 'br' => [], 'a' => [ 'href' => [], 'target' => [], 'rel' => [] ] ];
?>

<div class="se-module-settings-tweaks">
    <p style="margin-bottom:20px"><?php esc_html_e( 'Enable or disable individual WordPress tweaks. Only enabled tweaks load any code — disabled options add zero overhead.', 'site-essentials' ); ?></p>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'site_essentials_tweaks', 'site_essentials_tweaks_nonce' ); ?>
        <input type="hidden" name="action" value="site_essentials_save_tweaks">

        <?php foreach ( $groups as $group_id => $group ) : ?>
        <div class="scos-card">
            <div class="scos-card__header">
                <div>
                    <h2 class="scos-card__title">
                        <?php echo esc_html( $group['label'] ); ?>
                        <?php if ( ! empty( $group['guide_url'] ) ) : ?>
                        <a href="<?php echo esc_url( $group['guide_url'] ); ?>"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="scos-badge scos-badge--soft">
                            <?php esc_html_e( 'guide', 'site-essentials' ); ?>
                        </a>
                        <?php endif; ?>
                    </h2>
                </div>
            </div>
            <div class="scos-card__body">
                <table class="scos-form">
                    <tbody>
                        <?php foreach ( $group['tweaks'] as $tweak_id => $tweak_data ) : ?>
                        <tr>
                            <td class="scos-checkbox-row">
                                <label>
                                    <input type="checkbox"
                                           id="tweak_<?php echo esc_attr( $tweak_id ); ?>"
                                           name="enabled_tweaks[<?php echo esc_attr( $tweak_id ); ?>]"
                                           value="1"
                                           <?php checked( ! empty( $tweaks[ $tweak_id ] ) ); ?> />
                                    <span class="scos-checkbox-row__label"><?php echo esc_html( $tweak_data['label'] ); ?></span>
                                </label>
                                <p class="description">
                                    <?php
                                    // Google Fonts description is pre-escaped via wp_kses above
                                    if ( 'remove_google_fonts' === $tweak_id ) {
                                        echo $tweak_data['description']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                    } else {
                                        echo wp_kses( $tweak_data['description'], $allowed_desc_tags );
                                    }
                                    ?>
                                </p>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
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
