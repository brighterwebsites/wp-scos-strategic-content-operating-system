<?php
/**
 * Social Amplification API Endpoints
 *
 * REST API endpoints for social media amplification system
 *
 * @package BrighterCore
 * @subpackage SocialAmplification
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

class BW_Social_Amplification_API {

    /**
     * @var Brighter_API_Auth Authentication handler
     */
    private $auth;

    /**
     * @var BW_Talking_Points Talking points manager
     */
    private $talking_points;

    /**
     * API namespace
     */
    const NAMESPACE = 'brighter-core/v1';

    /**
     * Constructor
     *
     * @param Brighter_API_Auth $auth Authentication handler
     * @param BW_Talking_Points $talking_points Talking points manager
     */
    public function __construct($auth, $talking_points) {
        $this->auth = $auth;
        $this->talking_points = $talking_points;
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Get content inventory
        register_rest_route(self::NAMESPACE, '/social-amplification/inventory', array(
            'methods'  => 'GET',
            'callback' => array($this, 'get_content_inventory'),
            'permission_callback' => array($this->auth, 'verify_token')
        ));

        // Get talking points
        register_rest_route(self::NAMESPACE, '/social-amplification/talking-points', array(
            'methods'  => 'GET',
            'callback' => array($this, 'get_talking_points'),
            'permission_callback' => array($this->auth, 'verify_token'),
            'args' => array(
                'content_type' => array(
                    'description' => 'Filter by content type slug',
                    'type'        => 'string',
                    'required'    => false
                )
            )
        ));

        // Generate AI prompt
        register_rest_route(self::NAMESPACE, '/social-amplification/generate-prompt', array(
            'methods'  => 'GET',
            'callback' => array($this, 'generate_prompt'),
            'permission_callback' => array($this->auth, 'verify_token'),
            'args' => array(
                'post_id' => array(
                    'description' => 'Post ID',
                    'type'        => 'integer',
                    'required'    => true
                ),
                'talking_point_id' => array(
                    'description' => 'Talking point ID',
                    'type'        => 'integer',
                    'required'    => true
                ),
                'cta_focus' => array(
                    'description' => 'CTA focus type',
                    'type'        => 'string',
                    'enum'        => array('learn', 'engage', 'act'),
                    'required'    => true
                ),
                'platform' => array(
                    'description' => 'Social platform',
                    'type'        => 'string',
                    'enum'        => array('facebook', 'linkedin', 'twitter', 'instagram', 'gmb'),
                    'required'    => true
                ),
                'word_count' => array(
                    'description' => 'Target word count',
                    'type'        => 'integer',
                    'required'    => false
                )
            )
        ));

        // Get content types
        register_rest_route(self::NAMESPACE, '/social-amplification/content-types', array(
            'methods'  => 'GET',
            'callback' => array($this, 'get_content_types'),
            'permission_callback' => array($this->auth, 'verify_token')
        ));
    }

    /**
     * Get content inventory
     *
     * Returns all published content that can be amplified
     */
    public function get_content_inventory($request) {
        $inventory = array();

        // Define post types to include
        $post_types = array('post', 'page', 'folio', 'projects');

        foreach ($post_types as $post_type) {
            if (!post_type_exists($post_type)) {
                continue;
            }

            $args = array(
                'post_type'      => $post_type,
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'orderby'        => 'date',
                'order'          => 'DESC'
            );

            $query = new WP_Query($args);

            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $post_id = get_the_ID();

                    $inventory[] = array(
                        'id'           => $post_id,
                        'title'        => get_the_title(),
                        'url'          => get_permalink(),
                        'excerpt'      => get_the_excerpt(),
                        'post_type'    => $post_type,
                        'content_type' => $this->determine_content_type($post_id, $post_type),
                        'date'         => get_the_date('c'),
                        'modified'     => get_the_modified_date('c')
                    );
                }
                wp_reset_postdata();
            }
        }

        return rest_ensure_response(array(
            'success' => true,
            'count'   => count($inventory),
            'items'   => $inventory
        ));
    }

    /**
     * Get talking points
     */
    public function get_talking_points($request) {
        $content_type = $request->get_param('content_type');
        $talking_points = $this->talking_points->get_talking_points_by_content_type($content_type);

        return rest_ensure_response(array(
            'success' => true,
            'count'   => count($talking_points),
            'items'   => $talking_points
        ));
    }

    /**
     * Get content types
     */
    public function get_content_types($request) {
        $content_types = $this->talking_points->get_content_types();

        return rest_ensure_response(array(
            'success' => true,
            'count'   => count($content_types),
            'items'   => $content_types
        ));
    }

    /**
     * Generate AI prompt for social post
     */
    public function generate_prompt($request) {
        $post_id = $request->get_param('post_id');
        $talking_point_id = $request->get_param('talking_point_id');
        $cta_focus = $request->get_param('cta_focus');
        $platform = $request->get_param('platform');
        $word_count = $request->get_param('word_count') ?: 90;

        // Get post content
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('invalid_post', 'Post not found', array('status' => 404));
        }

        // Get talking point
        $talking_point = get_post($talking_point_id);
        if (!$talking_point || $talking_point->post_type !== 'bw_talking_point') {
            return new WP_Error('invalid_talking_point', 'Talking point not found', array('status' => 404));
        }

        // Get talking point metadata
        $tp_context = get_post_meta($talking_point_id, '_bw_tp_context', true);
        $tp_example = get_post_meta($talking_point_id, '_bw_tp_example', true);
        $tp_cta_example = get_post_meta($talking_point_id, '_bw_tp_cta_example', true);

        // Build the prompt
        $prompt = $this->build_prompt(
            $post,
            $talking_point->post_title,
            $tp_context,
            $tp_example,
            $tp_cta_example,
            $cta_focus,
            $platform,
            $word_count
        );

        return rest_ensure_response(array(
            'success' => true,
            'prompt'  => $prompt,
            'meta'    => array(
                'post_id'          => $post_id,
                'post_title'       => $post->post_title,
                'post_url'         => get_permalink($post_id),
                'talking_point'    => $talking_point->post_title,
                'cta_focus'        => $cta_focus,
                'platform'         => $platform,
                'word_count'       => $word_count
            )
        ));
    }

    /**
     * Build AI prompt
     */
    private function build_prompt($post, $talking_point_name, $tp_context, $tp_example, $tp_cta_example, $cta_focus, $platform, $word_count) {
        // Get brand voice (from settings or default)
        $brand_voice = $this->get_brand_voice();

        // Platform-specific rules
        $platform_rules = $this->get_platform_rules($platform);

        // CTA focus instructions
        $cta_instructions = $this->get_cta_instructions($cta_focus);

        // Build the prompt
        $prompt = "You are a social media content writer for Brighter Websites.\n\n";

        $prompt .= "## BRAND VOICE\n";
        $prompt .= $brand_voice . "\n\n";

        $prompt .= "## CONTENT TO AMPLIFY\n";
        $prompt .= "Title: " . $post->post_title . "\n";
        $prompt .= "URL: " . get_permalink($post->ID) . "\n";
        $prompt .= "Excerpt: " . get_the_excerpt($post->ID) . "\n\n";
        $prompt .= "Full Content:\n" . wp_strip_all_tags($post->post_content) . "\n\n";

        $prompt .= "## TALKING POINT: " . $talking_point_name . "\n";
        $prompt .= $tp_context . "\n\n";

        if ($tp_example) {
            $prompt .= "Example angles:\n" . $tp_example . "\n\n";
        }

        $prompt .= "## YOUR TASK\n";
        $prompt .= "Create a social media post for " . strtoupper($platform) . ".\n\n";

        $prompt .= "Requirements:\n";
        $prompt .= "- Extract a single point about '" . $talking_point_name . "' from the content above\n";
        $prompt .= "- Target word count: " . $word_count . " words\n";
        $prompt .= "- CTA Focus: " . strtoupper($cta_focus) . " - " . $cta_instructions . "\n";
        $prompt .= "- Platform: " . strtoupper($platform) . " - " . $platform_rules . "\n";

        if ($tp_cta_example) {
            $prompt .= "- CTA examples: " . $tp_cta_example . "\n";
        }

        $prompt .= "\n## OUTPUT FORMAT\n";
        $prompt .= "Provide ONLY the social media post text. Do NOT include:\n";
        $prompt .= "- Meta labels like 'Facebook Post:' or 'LinkedIn Post:'\n";
        $prompt .= "- Explanations or commentary\n";
        $prompt .= "- Multiple versions\n\n";
        $prompt .= "Just the post text itself, ready to publish.\n";

        return $prompt;
    }

    /**
     * Get brand voice instructions
     */
    private function get_brand_voice() {
        // TODO: Make this configurable via settings
        return "Brighter Websites is authoritative but approachable. We're experts who explain clearly without jargon. We're confident but never arrogant. Direct, practical, and focused on results. We challenge bad practices respectfully and offer better solutions.";
    }

    /**
     * Get platform-specific rules
     */
    private function get_platform_rules($platform) {
        $rules = array(
            'facebook'  => 'Use line breaks for readability. Emojis sparingly (mainly for CTA/link). 3-4 hashtags at end.',
            'linkedin'  => 'Professional tone. Longer-form acceptable (up to 150 words). Line breaks for structure. 2-3 relevant hashtags.',
            'twitter'   => 'Concise and punchy. Max 280 characters. 1-2 hashtags. No URL needed (will be added separately).',
            'instagram' => 'Visual-first mindset. First line is hook. Line breaks. More hashtags ok (5-8). Emojis welcome.',
            'gmb'       => 'Short and local-focused. NO URLs. NO CTAs with links. Focus on value and location relevance.'
        );

        return $rules[$platform] ?? 'Follow best practices for this platform.';
    }

    /**
     * Get CTA focus instructions
     */
    private function get_cta_instructions($cta_focus) {
        $instructions = array(
            'learn'  => 'Educational, no ask. "Learn more", "Find out", "Discover" - value-focused.',
            'engage' => 'Soft ask, low friction. "Check it out and get the free thing", "Take the quiz", "Download the guide".',
            'act'    => 'Clear CTA. "Get a quote", "Book now", "DM for details", "Contact us" - direct action.'
        );

        return $instructions[$cta_focus] ?? 'Appropriate call-to-action.';
    }

    /**
     * Determine content type based on post type and metadata
     */
    private function determine_content_type($post_id, $post_type) {
        // Map post types to content types
        $type_map = array(
            'post'     => 'blog',
            'folio'    => 'project',
            'projects' => 'project',
            'page'     => 'authority'  // Default, can be overridden
        );

        $content_type = $type_map[$post_type] ?? 'blog';

        // Check if page has specific purpose meta
        if ($post_type === 'page') {
            $purpose = get_post_meta($post_id, 'bw_purpose', true);
            if ($purpose === 'service-page') {
                $content_type = 'conversion';
            } elseif ($purpose === 'about' || $purpose === 'team') {
                $content_type = 'authority';
            }
        }

        return $content_type;
    }
}
