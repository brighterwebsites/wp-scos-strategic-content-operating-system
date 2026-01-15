<?php
/**
 * SCOS Schema Output - Graphed JSON-LD
 *
 * Outputs consolidated @graph schema for all page types.
 * Uses template_redirect to ensure WordPress conditionals work.
 *
 * Meta fields used:
 * - bw_custom_schema: Custom JSON-LD to merge into @graph
 * - bw_breadcrumb_schema: Override breadcrumb label
 * - bw_purpose: Detect service pages (value: 'service-page')
 *
 * Kill Switch Controls:
 * - SCOS_DISABLE_SCHEMA: Set to true in wp-config.php to disable all schema output
 * - SCOS_SCHEMA_ALLOWED_SITES: Array of allowed domains (whitelist approach)
 *   Example: define('SCOS_SCHEMA_ALLOWED_SITES', ['brighterwebsites.com.au']);
 *
 * @package    BrighterCore
 * @subpackage Schema
 * @version    1.0.0
 * @since      1.0.0
 */

if (!defined('ABSPATH')) exit;

/**
 * Output schema on template_redirect (after WP knows what page we're on)
 */
add_action('template_redirect', 'bw_output_schema_graph', 1);

function bw_output_schema_graph() {
    
    // ============================================
    // KILL SWITCH SYSTEM
    // ============================================
    
    // Global kill switch: If SCOS_DISABLE_SCHEMA is true, stop all schema output
    if (defined('SCOS_DISABLE_SCHEMA') && SCOS_DISABLE_SCHEMA === true) {
        return;
    }
    
    // Whitelist approach: If SCOS_SCHEMA_ALLOWED_SITES is defined, check current domain
    if (defined('SCOS_SCHEMA_ALLOWED_SITES')) {
        $allowed_sites = SCOS_SCHEMA_ALLOWED_SITES;
        if (!is_array($allowed_sites)) {
            $allowed_sites = [];
        }
        
        // Get current domain (strip www. if present for comparison)
        $current_host = isset($_SERVER['HTTP_HOST']) ? strtolower($_SERVER['HTTP_HOST']) : '';
        $current_host = preg_replace('/^www\./', '', $current_host);
        
        // Check if current domain is in whitelist
        $is_allowed = false;
        foreach ($allowed_sites as $allowed_domain) {
            $allowed_domain = strtolower($allowed_domain);
            $allowed_domain = preg_replace('/^www\./', '', $allowed_domain);
            
            if ($current_host === $allowed_domain) {
                $is_allowed = true;
                break;
            }
        }
        
        // If not in whitelist, stop schema output
        if (!$is_allowed) {
            return;
        }
    }
    
    // ============================================
    // STANDARD CHECKS
    // ============================================
    
    // Skip admin, feeds, REST, AJAX
    if (is_admin() || is_feed() || wp_doing_ajax()) return;
    if (defined('REST_REQUEST') && REST_REQUEST) return;
    
    // Buffer the output to inject into wp_head
    add_action('wp_head', 'bw_render_schema_graph', 5);
}

/**
 * Get author schema @id from user data
 * 
 * @param int|null $user_id User ID (defaults to post author)
 * @return string Author @id URL (e.g., home_url('/#vanessa-wood'))
 */
function bw_get_author_schema_id($user_id = null) {
    if (!$user_id && is_singular()) {
        $user_id = get_post_field('post_author', get_the_ID());
    }
    
    if (!$user_id) {
        return home_url('/#vanessa-wood'); // Fallback
    }
    
    $user = get_userdata($user_id);
    if (!$user) {
        return home_url('/#vanessa-wood'); // Fallback
    }
    
    // Build slug from first-last name
    $first = sanitize_title($user->first_name ?: 'vanessa');
    $last = sanitize_title($user->last_name ?: 'wood');
    
    return home_url('/#' . $first . '-' . $last);
}

/**
 * Get author name from user data
 * 
 * @param int|null $user_id User ID
 * @return string Author display name
 */
function bw_get_author_name($user_id = null) {
    if (!$user_id && is_singular()) {
        $user_id = get_post_field('post_author', get_the_ID());
    }
    
    if (!$user_id) {
        return 'Vanessa Wood';
    }
    
    $user = get_userdata($user_id);
    return $user ? $user->display_name : 'Vanessa Wood';
}

/**
 * Render the actual schema JSON-LD
 */
function bw_render_schema_graph() {
    
    $graph = [];
    
    // ============================================
    // STATIC BLOCKS
    // ============================================
    
    // WebSite
    $graph[] = [
        "@type" => "WebSite",
        "@id" => home_url('/#website'),
        "url" => home_url('/'),
        "name" => "Brighter Websites",
        "publisher" => [
            "@id" => home_url('/#organization')
        ]
    ];
    
    // LocalBusiness
    $graph[] = [
        "@type" => "LocalBusiness",
        "@id" => home_url('/#organization'),
        "name" => "Brighter Websites",
        "legalName" => "Vanessa Wood Trading As Brighter Websites",
        "description" => "Ballarat-based web design and SEO specialist serving regional Australian service businesses. We build AI-ready, conversion-optimized websites that generate measurable ROI for trades, professional services, and consultants.",
        "url" => home_url('/'),
        "telephone" => "+61412401933",
        "email" => "support@brighterwebsites.com.au",
        "priceRange" => "$$$",
        "address" => [
            "@type" => "PostalAddress",
            "streetAddress" => "14 Messenger Pde Lucas",
            "addressLocality" => "Ballarat",
            "addressRegion" => "VIC",
            "postalCode" => "3350",
            "addressCountry" => "AU"
        ],
        "geo" => [
            "@type" => "GeoCoordinates",
            "latitude" => "-37.5662",
            "longitude" => "143.8496"
        ],
        "areaServed" => [
            ["@type" => "City", "name" => "Ballarat", "sameAs" => "https://www.wikidata.org/wiki/Q17856"],
            ["@type" => "State", "name" => "Victoria", "sameAs" => "https://www.wikidata.org/wiki/Q36687"],
            ["@type" => "Country", "name" => "Australia", "sameAs" => "https://www.wikidata.org/wiki/Q408"]
        ],
        "openingHoursSpecification" => [
            [
                "@type" => "OpeningHoursSpecification",
                "dayOfWeek" => ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"],
                "opens" => "09:00",
                "closes" => "17:00"
            ]
        ],
        "identifier" => [
            ["@type" => "PropertyValue", "name" => "ABN", "value" => "85 329 119 455"]
        ],
        "sameAs" => [
            "https://www.facebook.com/BrighterWeb",
            "https://www.linkedin.com/company/brighterwebsites",
            "https://www.instagram.com/brighterwebsites",
            "https://www.youtube.com/@brighterwebsites",
            "https://www.linkedin.com/in/vanessarosewood/"
        ],
        "logo" => [
            "@type" => "ImageObject",
            "url" => home_url('/wp-content/uploads/2024/01/brighter-websites-logo.png'),
            "width" => 1200,
            "height" => 1200
        ],
        "founder" => [
            "@type" => "Person",
            "@id" => home_url('/#vanessa-wood'),
            "name" => "Vanessa Wood",
            "url" => home_url('/author/vanessa-wood/'),
            "sameAs" => [
                "https://www.facebook.com/vanessarosewoodau",
                "https://www.linkedin.com/in/vanessarosewood/"
            ]
        ],
        "foundingDate" => "2016",
        "foundingLocation" => [
            "@type" => "Place",
            "name" => "Ballarat, VIC, Australia"
        ]
    ];
    
    // ============================================
    // DYNAMIC BLOCKS
    // ============================================
    
    $current_url = home_url($_SERVER['REQUEST_URI']);
    $post_id = is_singular() ? get_the_ID() : 0;
    
    // WebPage - always add
    $webpage = [
        "@type" => "WebPage",
        "@id" => esc_url($current_url),
        "url" => esc_url($current_url),
        "isPartOf" => [
            "@id" => home_url('/#website')
        ]
    ];
    

    // Use the "Content Type" field to be created for Social Amplification to generate the webpage schema ID
    // "mainEntity": {
    //   "@id":  "@id" => esc_url($current_url) "#service"  = Content Type
    //



    // Add author for singles (posts, projects), publisher for archives/pages
    // Author: articles, projects, news
    // Publisher only: service pages, Legal/Terms, Conversion Hub, Landing Page
    $post_type = is_singular() ? get_post_type() : '';
    $author_post_types = ['post', 'project', 'news']; // Post types that get author
    
    if (is_singular() && in_array($post_type, $author_post_types)) {
        $webpage["author"] = [
            "@type" => "Person",
            "@id" => bw_get_author_schema_id(),
            "name" => bw_get_author_name()
        ];
    } else {
        $webpage["publisher"] = [
            "@id" => home_url('/#organization')
        ];
    }
    
    $graph[] = $webpage;
    
    // ============================================
    // ARCHIVE PAGES - CollectionPage (includes blog home)
    // ============================================
    
    // is_home() = blog posts page (when using static front page)
    // is_archive() = category, tag, CPT archive, date archive, etc.
    if (is_home() || is_archive()) {
        $post_type = get_post_type() ?: 'post';
        $archive_url = get_post_type_archive_link($post_type) ?: $current_url;
        
        // Get archive title
        if (is_home()) {
            $archive_title = 'Blog';
        } else {
            $archive_title = post_type_archive_title('', false) ?: single_term_title('', false) ?: 'Archive';
        }
        
        $archive_description = term_description() ?: get_the_archive_description() ?: '';
        
        $posts = get_posts([
            'numberposts' => 5,
            'post_type' => $post_type,
            'post_status' => 'publish'
        ]);
        
        $entities = [];
        foreach ($posts as $post) {
            $entities[] = [
                "@type" => "BlogPosting",
                "headline" => get_the_title($post),
                "url" => get_permalink($post),
                "datePublished" => get_the_date('c', $post),
                "author" => [
                    "@type" => "Person",
                    "name" => get_the_author_meta('display_name', $post->post_author)
                ]
            ];
        }
        
        if (!empty($entities)) {
            $graph[] = [
                "@type" => "CollectionPage",
                "@id" => esc_url($archive_url) . '#collection',
                "name" => $archive_title,
                "url" => esc_url($archive_url),
                "description" => wp_strip_all_tags($archive_description),
                "mainEntity" => $entities
            ];
        }
    }
    
    // ============================================
    // SINGLE POSTS - Article (enhanced)
    // ============================================
    
    if (is_singular('post')) {
        // Get TLDR for description (fallback to excerpt)
        $tldr = get_post_meta($post_id, 'bw_tldr', true);
        if (empty($tldr)) {
            $tldr = get_post_meta($post_id, 'tldr', true); // ACF fallback
        }
        $description = !empty($tldr) ? wp_strip_all_tags($tldr) : (get_the_excerpt() ?: wp_trim_words(get_the_content(), 30));
        
        // Get SEOPress meta title for alternativeHeadline
        $meta_title = get_post_meta($post_id, '_seopress_titles_title', true);
        
        // Get primary topic for "about"
        $topic_id = get_post_meta($post_id, 'bw_primary_topic_id', true);
        $about = null;
        if ($topic_id) {
            $topic_term = get_term($topic_id, 'altc_topic');
            if ($topic_term && !is_wp_error($topic_term)) {
                $topic_sameas = get_term_meta($topic_id, 'topic_sameas_url', true);
                $about = [
                    "@type" => "Thing",
                    "name" => $topic_term->name
                ];
                if (!empty($topic_term->description)) {
                    $about["description"] = wp_strip_all_tags($topic_term->description);
                }
                if (!empty($topic_sameas)) {
                    $about["sameAs"] = $topic_sameas;
                }
            }
        }
        
        // Build image object (prefer OG image size 1200x630)
        $image_obj = null;
        if (has_post_thumbnail($post_id)) {
            $thumbnail_id = get_post_thumbnail_id($post_id);
            // Try to get 1200x630 OG size, fallback to large
            $image_url = wp_get_attachment_image_url($thumbnail_id, 'og-image'); // Custom OG size if registered
            if (!$image_url) {
                $image_url = get_the_post_thumbnail_url($post_id, 'large');
            }
            $image_caption = get_the_post_thumbnail_caption($post_id);
            $image_alt = get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true);
            
            $image_obj = [
                "@type" => "ImageObject",
                "url" => $image_url,
                "width" => 1200,
                "height" => 630
            ];
            if (!empty($image_caption)) {
                $image_obj["caption"] = $image_caption;
            } elseif (!empty($image_alt)) {
                $image_obj["caption"] = $image_alt;
            }
        }
        
        // Get word count and reading time
        $word_count = (int) get_post_meta($post_id, 'bw_word_count', true);
        $reading_time = (int) get_post_meta($post_id, 'bw_reading_time', true);
        
        // Build Article schema
        $article = [
            "@type" => "Article",
            "@id" => get_permalink() . '#article',
            "headline" => get_the_title(),
            "description" => $description,
            "url" => get_permalink(),
            "datePublished" => get_the_date('c'),
            "dateModified" => get_the_modified_date('c'),
            "author" => [
                "@type" => "Person",
                "@id" => bw_get_author_schema_id(),
                "name" => bw_get_author_name()
            ],
            "publisher" => [
                "@id" => home_url('/#organization')
            ],
            "mainEntityOfPage" => [
                "@id" => get_permalink()
            ],
            "isAccessibleForFree" => true,
            "speakable" => [
                "@type" => "SpeakableSpecification",
                "cssSelector" => [".speakthis", ".tldr-content", "h1", "h2"]
            ]
        ];
        
        // Add word count if available
        if ($word_count > 0) {
            $article["wordCount"] = $word_count;
        }
        
        // Add reading time if available (ISO 8601 duration format)
        if ($reading_time > 0) {
            $article["timeRequired"] = "PT{$reading_time}M"; // e.g., PT7M
            $article["additionalProperty"] = [
                "@type" => "PropertyValue",
                "name" => "reading_time_minutes",
                "value" => $reading_time
            ];
        }
        
        // Add optional fields if available
        if (!empty($meta_title) && $meta_title !== get_the_title()) {
            $article["alternativeHeadline"] = $meta_title;
        }
        
        if ($image_obj) {
            $article["image"] = $image_obj;
        }
        
        if ($about) {
            $article["about"] = $about;
        }
        
        // TODO: Future enhancements
        // - "about" array from bw_supporting_topics (multiple topics)
        // - "mentions" for Place (Ballarat) - could be site-wide or per-post
        
        $graph[] = $article;
    }
    
    // ============================================
    // NEWS - NewsArticle (same as Article + news-specific fields)
    // ============================================
    
    if (is_singular('news')) {
        // Get TLDR for description (fallback to excerpt)
        $tldr = get_post_meta($post_id, 'bw_tldr', true);
        if (empty($tldr)) {
            $tldr = get_post_meta($post_id, 'tldr', true); // ACF fallback
        }
        $description = !empty($tldr) ? wp_strip_all_tags($tldr) : (get_the_excerpt() ?: wp_trim_words(get_the_content(), 30));
        
        // Get SEOPress meta title for alternativeHeadline
        $meta_title = get_post_meta($post_id, '_seopress_titles_title', true);
        
        // Get word count and reading time
        $word_count = (int) get_post_meta($post_id, 'bw_word_count', true);
        $reading_time = (int) get_post_meta($post_id, 'bw_reading_time', true);
        
        // Get primary topic for "about"
        $topic_id = get_post_meta($post_id, 'bw_primary_topic_id', true);
        $about = null;
        if ($topic_id) {
            $topic_term = get_term($topic_id, 'altc_topic');
            if ($topic_term && !is_wp_error($topic_term)) {
                $topic_sameas = get_term_meta($topic_id, 'topic_sameas_url', true);
                $about = [
                    "@type" => "Thing",
                    "name" => $topic_term->name
                ];
                if (!empty($topic_term->description)) {
                    $about["description"] = wp_strip_all_tags($topic_term->description);
                }
                if (!empty($topic_sameas)) {
                    $about["sameAs"] = $topic_sameas;
                }
            }
        }
        
        // Build image object
        $image_obj = null;
        if (has_post_thumbnail($post_id)) {
            $thumbnail_id = get_post_thumbnail_id($post_id);
            $image_url = wp_get_attachment_image_url($thumbnail_id, 'og-image') ?: get_the_post_thumbnail_url($post_id, 'large');
            $image_caption = get_the_post_thumbnail_caption($post_id);
            $image_alt = get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true);
            
            $image_obj = [
                "@type" => "ImageObject",
                "url" => $image_url,
                "width" => 1200,
                "height" => 630
            ];
            if (!empty($image_caption)) {
                $image_obj["caption"] = $image_caption;
            } elseif (!empty($image_alt)) {
                $image_obj["caption"] = $image_alt;
            }
        }
        
        // Build NewsArticle schema
        $news_article = [
            "@type" => "NewsArticle",
            "@id" => get_permalink() . '#news-article',
            "headline" => get_the_title(),
            "description" => $description,
            "url" => get_permalink(),
            "datePublished" => get_the_date('c'),
            "dateModified" => get_the_modified_date('c'),
            "coverageStartTime" => get_the_date('c'), // Same as published for news
            "author" => [
                "@type" => "Person",
                "@id" => bw_get_author_schema_id(),
                "name" => bw_get_author_name()
            ],
            "publisher" => [
                "@id" => home_url('/#organization')
            ],
            "mainEntityOfPage" => [
                "@id" => get_permalink()
            ],
            "articleSection" => "AI & Digital Marketing News",
            "dateline" => "Ballarat, Australia",
            "locationCreated" => [
                "@type" => "Place",
                "name" => "Ballarat, Victoria, Australia"
            ],
            "isAccessibleForFree" => true,
            "speakable" => [
                "@type" => "SpeakableSpecification",
                "cssSelector" => [".speakthis", ".tldr-content", "h1", "h2"]
            ]
        ];
        
        // Add word count if available
        if ($word_count > 0) {
            $news_article["wordCount"] = $word_count;
        }
        
        // Add reading time if available
        if ($reading_time > 0) {
            $news_article["timeRequired"] = "PT{$reading_time}M";
            $news_article["additionalProperty"] = [
                "@type" => "PropertyValue",
                "name" => "reading_time_minutes",
                "value" => $reading_time
            ];
        }
        
        // Add optional fields
        if (!empty($meta_title) && $meta_title !== get_the_title()) {
            $news_article["alternativeHeadline"] = $meta_title;
        }
        
        if ($image_obj) {
            $news_article["image"] = $image_obj;
        }
        
        if ($about) {
            $news_article["about"] = $about;
        }
        
        $graph[] = $news_article;
    }
    
    // ============================================
    // SERVICE PAGES - Service (only if no custom schema)
    // ============================================
    
    if (is_singular('page')) {
        $purpose = get_post_meta($post_id, 'bw_purpose', true);
        $has_custom_schema = !empty(get_post_meta($post_id, 'bw_custom_schema', true));
        
        // Only auto-generate Service schema if it's a service page AND no custom schema
        if ($purpose === 'service-page' && !$has_custom_schema) {
            $description = get_post_meta($post_id, 'bw_service_description', true) ?: get_the_excerpt();
          
            $graph[] = [
                "@type" => "Service",
                "@id" => get_permalink() . '#service',
                "name" => get_the_title(),
                "description" => $description,
                "url" => get_permalink(),
                "provider" => [
                    "@id" => home_url('/#organization')
                ],
                "areaServed" => [
                    ["@type" => "Country", "name" => "Australia"]
                ]
            ];
        }
    }
    
    // ============================================
    // PROJECTS - CreativeWork
    // ============================================
    
    if (is_singular('project')) {
        $graph[] = [
            "@type" => "CreativeWork",
            "@id" => get_permalink() . '#creative-work',
            "name" => get_the_title(),
            "description" => get_the_excerpt(),
            "url" => get_permalink(),
            "creator" => [
                "@id" => home_url('/#organization')
            ],
            "dateCreated" => get_the_date('c'),
            "image" => has_post_thumbnail() ? get_the_post_thumbnail_url($post_id, 'large') : null
        ];
    }
    
    // ============================================
    // BREADCRUMBS
    // ============================================
    
    $breadcrumbs = [];
    $position = 1;
    
    // Home - always first
    $breadcrumbs[] = [
        "@type" => "ListItem",
        "position" => $position++,
        "name" => "Home",
        "item" => home_url('/')
    ];
    
    // FRONT PAGE - just Home, nothing else
    if (is_front_page()) {
        // Only Home breadcrumb for front page
    }
    
    // BLOG ARCHIVE (is_home() = true for blog posts page)
    elseif (is_home()) {
        $breadcrumbs[] = [
            "@type" => "ListItem",
            "position" => $position,
            "name" => "Blog",
            "item" => get_post_type_archive_link('post') ?: home_url('/blog/')
        ];
    }
    
    // OTHER ARCHIVES (categories, tags, CPT archives, date archives)
    elseif (is_archive()) {
        $post_type = get_post_type() ?: 'post';
        $post_type_obj = get_post_type_object($post_type);
        
        if ($post_type_obj) {
            $breadcrumbs[] = [
                "@type" => "ListItem",
                "position" => $position,
                "name" => $post_type_obj->labels->name,
                "item" => get_post_type_archive_link($post_type)
            ];
        }
    }
    
    // SINGLES (posts, pages, CPTs)
    elseif (is_singular()) {
        $post_type = get_post_type();
        
        // Add post type archive for posts
        if ($post_type === 'post') {
            $breadcrumbs[] = [
                "@type" => "ListItem",
                "position" => $position++,
                "name" => "Blog",
                "item" => get_post_type_archive_link('post') ?: home_url('/blog/')
            ];
        }
        
        // Add CPT archive for custom post types (not pages)
        if ($post_type !== 'post' && $post_type !== 'page') {
            $post_type_obj = get_post_type_object($post_type);
            if ($post_type_obj && $post_type_obj->has_archive) {
                $breadcrumbs[] = [
                    "@type" => "ListItem",
                    "position" => $position++,
                    "name" => $post_type_obj->labels->name,
                    "item" => get_post_type_archive_link($post_type)
                ];
            }
        }
        
        // Add parent pages for hierarchical post types (pages)
        if (is_page()) {
            $ancestors = get_post_ancestors($post_id);
            $ancestors = array_reverse($ancestors);
            
            foreach ($ancestors as $ancestor_id) {
                $breadcrumbs[] = [
                    "@type" => "ListItem",
                    "position" => $position++,
                    "name" => get_the_title($ancestor_id),
                    "item" => get_permalink($ancestor_id)
                ];
            }
        }
        
        // Current page - check for breadcrumb override
        $breadcrumb_override = get_post_meta($post_id, 'bw_breadcrumb_schema', true);
        $current_name = !empty($breadcrumb_override) ? $breadcrumb_override : get_the_title();
        
        $breadcrumbs[] = [
            "@type" => "ListItem",
            "position" => $position,
            "name" => $current_name,
            "item" => get_permalink()
        ];
    }
    
    $graph[] = [
        "@type" => "BreadcrumbList",
        "@id" => esc_url($current_url) . '#breadcrumb',
        "itemListElement" => $breadcrumbs
    ];
    
    // ============================================
    // CUSTOM SCHEMA (from meta field)
    // ============================================
    
    if (is_singular() && $post_id) {
        $custom_schema = get_post_meta($post_id, 'bw_custom_schema', true);
        
        if (!empty($custom_schema)) {
            $decoded = json_decode($custom_schema, true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                // If array of blocks, merge them
                if (isset($decoded[0])) {
                    $graph = array_merge($graph, $decoded);
                } else {
                    $graph[] = $decoded;
                }
            }
        }
    }
    
    // ============================================
    // OUTPUT
    // ============================================
    
    // Filter out any null values from image fields etc.
    $graph = array_map(function($block) {
        return array_filter($block, function($value) {
            return $value !== null;
        });
    }, $graph);
    
    $schema = [
        "@context" => "https://schema.org",
        "@graph" => array_values($graph)
    ];
    
    echo '<script id="bw-schema-graph" type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . '</script>' . "\n";
}

