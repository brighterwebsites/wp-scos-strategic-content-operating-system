# Reviews CPT - Usage Guide

Complete guide for using the Reviews custom post type in Breakdance loops with PHP code blocks.

---

## Table of Contents

1. [Review Fields Reference](#review-fields-reference)
2. [Review Shortcodes](#review-shortcodes)
3. [Reviews Loop - Display Related Project](#reviews-loop---display-related-project)
4. [Projects Loop - Display Related Reviews](#projects-loop---display-related-reviews)
5. [Statistics & Aggregation](#statistics--aggregation)
6. [Common Patterns](#common-patterns)
7. [LLM-Ready Review Verification File](#llm-ready-review-verification-file)

---

## Review Fields Reference

### Standard WordPress Fields
- `get_the_title()` - Customer Name
- `get_the_content()` - Full review text
- `get_the_excerpt()` - Standard WP excerpt

### Custom Meta Fields

| Field Name | Meta Key | Type | Description |
|------------|----------|------|-------------|
| Rating | `bw_rating` | Integer (1-5) | Star rating |
| Date | `bw_date` | String (YYYY-MM-DD) | Review date |
| Date Precision | `bw_date_precision` | String | `year` \| `month-year` \| `full` |
| Verify URL | `bw_verify_url` | URL | Link to original review |
| Schema ID | `bw_schema_id` | String | Auto-generated unique ID |
| Success Outcome | `bw_success_outcome` | String (~100 chars) | Brief outcome summary |
| Customer Detail | `bw_customer_detail` | String (~100 chars) | Additional customer info |
| Is Featured | `bw_is_featured` | String | `1` or `0` |
| Review Excerpt | `bw_review_excerpt` | String (~150 chars) | Custom excerpt |

### ACF Relationship Field
- `bw_related_project` - Link to Project/Success Story (single)
- `bw_reviews_related` - Reverse: Links from Project to Reviews (multiple)

### Taxonomy
- **Platform**: `bw_review_platform` (Google, Facebook, Trustpilot, custom)

---

## Review Shortcodes

All shortcodes support optional `id` parameter. If omitted, uses current post in loop.

### Individual Review Data
```
[bw_review_rating]           <!-- Returns: 5 -->
[bw_review_rating id="42"]   <!-- Specific review -->
[bw_review_date]             <!-- Returns: March 15, 2024 (formatted) -->
[bw_review_verify_url]       <!-- Returns: https://... -->
[bw_review_schema_id]        <!-- Returns: john-smith-google -->
[bw_review_outcome]          <!-- Returns: Increased conversions by 40% -->
[bw_review_customer_detail]  <!-- Returns: CEO, Tech Startup -->
[bw_review_excerpt]          <!-- Returns: Excerpt or auto-truncated content -->
[bw_review_featured]         <!-- Returns: 1 or 0 -->
```

### Statistics Shortcodes
```
[bw_review_count]                        <!-- Total count -->
[bw_review_count platform="google"]      <!-- Filter by platform -->
[bw_review_count featured="1"]           <!-- Featured only -->

[bw_review_average]                      <!-- Average: 4.8 -->
[bw_review_average decimals="2"]         <!-- Average: 4.83 -->
[bw_review_average platform="google"]    <!-- Platform-specific -->
[bw_review_average featured="1"]         <!-- Featured only -->
```

---

## Reviews Loop - Display Related Project

Use this in a **Breakdance Loop (Query: Reviews)** to show the related project.

### Basic - Title Only
```php
<?php
$project_id = get_field('bw_related_project');
if ($project_id) {
    echo '<h3>' . get_the_title($project_id) . '</h3>';
}
?>
```

### With Featured Image
```php
<?php
$project_id = get_field('bw_related_project');
if ($project_id) {
    echo '<div class="related-project">';
    
    // Featured Image
    if (has_post_thumbnail($project_id)) {
        echo get_the_post_thumbnail($project_id, 'medium');
    }
    
    // Project Title
    echo '<h3>' . get_the_title($project_id) . '</h3>';
    
    echo '</div>';
}
?>
```

### With Link to Project
```php
<?php
$project_id = get_field('bw_related_project');
if ($project_id) {
    $project_url = get_permalink($project_id);
    echo '<div class="related-project">';
    
    // Featured Image with Link
    if (has_post_thumbnail($project_id)) {
        echo '<a href="' . esc_url($project_url) . '">';
        echo get_the_post_thumbnail($project_id, 'medium');
        echo '</a>';
    }
    
    // Project Title with Link
    echo '<h3><a href="' . esc_url($project_url) . '">';
    echo get_the_title($project_id);
    echo '</a></h3>';
    
    echo '</div>';
}
?>
```

### Complete - Image, Title, Excerpt, Link
```php
<?php
$project_id = get_field('bw_related_project');
if ($project_id) {
    $project_url = get_permalink($project_id);
    $project_title = get_the_title($project_id);
    $project_excerpt = get_the_excerpt($project_id);
    
    echo '<div class="related-project">';
    
    // Featured Image
    if (has_post_thumbnail($project_id)) {
        echo '<a href="' . esc_url($project_url) . '" class="project-thumb">';
        echo get_the_post_thumbnail($project_id, 'medium');
        echo '</a>';
    }
    
    echo '<div class="project-info">';
    
    // Title
    echo '<h3><a href="' . esc_url($project_url) . '">';
    echo esc_html($project_title);
    echo '</a></h3>';
    
    // Excerpt
    if ($project_excerpt) {
        echo '<p>' . esc_html($project_excerpt) . '</p>';
    }
    
    // Link
    echo '<a href="' . esc_url($project_url) . '" class="read-more">';
    echo 'View Project &rarr;</a>';
    
    echo '</div>'; // .project-info
    echo '</div>'; // .related-project
}
?>
```

---

## Projects Loop - Display Related Reviews

Use this in a **Breakdance Loop (Query: Projects)** to show related reviews.

### Basic - Customer Name, Excerpt, Outcome
```php
<?php
$review_ids = get_field('bw_reviews_related');
if ($review_ids) {
    foreach ((array)$review_ids as $review_id) {
        $customer_name = get_the_title($review_id);
        $excerpt = get_post_meta($review_id, 'bw_review_excerpt', true);
        $outcome = get_post_meta($review_id, 'bw_success_outcome', true);
        
        // Fallback excerpt from content
        if (empty($excerpt)) {
            $review_post = get_post($review_id);
            if ($review_post && !empty($review_post->post_content)) {
                $excerpt = wp_trim_words(wp_strip_all_tags($review_post->post_content), 25, '...');
            }
        }
        
        echo '<div class="review-item">';
        echo '<h4>' . esc_html($customer_name) . '</h4>';
        if ($excerpt) {
            echo '<p class="review-excerpt">' . esc_html($excerpt) . '</p>';
        }
        if ($outcome) {
            echo '<p class="review-outcome"><em>' . esc_html($outcome) . '</em></p>';
        }
        echo '</div>';
    }
}
?>
```

### With Rating and Platform
```php
<?php
$review_ids = get_field('bw_reviews_related');
if ($review_ids) {
    foreach ((array)$review_ids as $review_id) {
        // Get data
        $customer_name = get_the_title($review_id);
        $rating = get_post_meta($review_id, 'bw_rating', true);
        $excerpt = get_post_meta($review_id, 'bw_review_excerpt', true);
        $outcome = get_post_meta($review_id, 'bw_success_outcome', true);
        
        // Get platform
        $platform = '';
        $terms = get_the_terms($review_id, 'bw_review_platform');
        if ($terms && !is_wp_error($terms)) {
            $platform = $terms[0]->name;
        }
        
        echo '<div class="review-item">';
        
        // Header with name and rating
        echo '<div class="review-header">';
        echo '<h4>' . esc_html($customer_name) . '</h4>';
        if ($rating) {
            echo '<span class="rating">' . esc_html($rating) . '/5 ⭐</span>';
        }
        echo '</div>';
        
        // Platform badge
        if ($platform) {
            echo '<span class="platform-badge">' . esc_html($platform) . '</span>';
        }
        
        // Excerpt
        if ($excerpt) {
            echo '<p class="review-excerpt">' . esc_html($excerpt) . '</p>';
        }
        
        // Outcome
        if ($outcome) {
            echo '<p class="review-outcome"><strong>Outcome:</strong> ' . esc_html($outcome) . '</p>';
        }
        
        echo '</div>';
    }
}
?>
```

### Featured Reviews Only
```php
<?php
$review_ids = get_field('bw_reviews_related');
if ($review_ids) {
    foreach ((array)$review_ids as $review_id) {
        // Only show featured reviews
        $is_featured = get_post_meta($review_id, 'bw_is_featured', true);
        if ($is_featured !== '1') {
            continue;
        }
        
        $customer_name = get_the_title($review_id);
        $rating = get_post_meta($review_id, 'bw_rating', true);
        $excerpt = get_post_meta($review_id, 'bw_review_excerpt', true);
        
        echo '<div class="featured-review">';
        echo '<h4>' . esc_html($customer_name) . '</h4>';
        if ($rating) {
            echo '<span class="rating">' . str_repeat('⭐', intval($rating)) . '</span>';
        }
        if ($excerpt) {
            echo '<p>' . esc_html($excerpt) . '</p>';
        }
        echo '</div>';
    }
}
?>
```

### With Verification Link
```php
<?php
$review_ids = get_field('bw_reviews_related');
if ($review_ids) {
    foreach ((array)$review_ids as $review_id) {
        $customer_name = get_the_title($review_id);
        $rating = get_post_meta($review_id, 'bw_rating', true);
        $excerpt = get_post_meta($review_id, 'bw_review_excerpt', true);
        $verify_url = get_post_meta($review_id, 'bw_verify_url', true);
        
        echo '<div class="review-item">';
        echo '<h4>' . esc_html($customer_name) . '</h4>';
        if ($rating) {
            echo '<div class="rating">' . str_repeat('⭐', intval($rating)) . '</div>';
        }
        if ($excerpt) {
            echo '<p>' . esc_html($excerpt) . '</p>';
        }
        if ($verify_url) {
            echo '<a href="' . esc_url($verify_url) . '" target="_blank" rel="noopener">';
            echo 'Verify Review →</a>';
        }
        echo '</div>';
    }
}
?>
```

---

## Statistics & Aggregation

### Display Stats Outside Loops
```html
<!-- Global stats -->
<div class="review-stats">
    <div class="stat">
        <span class="value">[bw_review_average]</span>
        <span class="label">Average Rating</span>
    </div>
    <div class="stat">
        <span class="value">[bw_review_count]</span>
        <span class="label">Total Reviews</span>
    </div>
</div>

<!-- Platform-specific stats -->
<div class="google-reviews">
    <h3>Google Reviews</h3>
    <p>Average: [bw_review_average platform="google" decimals="2"]</p>
    <p>Count: [bw_review_count platform="google"]</p>
</div>
```

### Count Reviews for Current Project (in Project loop)
```php
<?php
$review_ids = get_field('bw_reviews_related');
$review_count = is_array($review_ids) ? count($review_ids) : 0;
echo '<p class="review-count">' . $review_count . ' Reviews</p>';
?>
```

### Calculate Average for Current Project
```php
<?php
$review_ids = get_field('bw_reviews_related');
if ($review_ids && is_array($review_ids)) {
    $total = 0;
    $count = 0;
    
    foreach ($review_ids as $review_id) {
        $rating = get_post_meta($review_id, 'bw_rating', true);
        if ($rating !== '' && is_numeric($rating)) {
            $total += floatval($rating);
            $count++;
        }
    }
    
    if ($count > 0) {
        $average = $total / $count;
        echo '<div class="project-rating">';
        echo '<span class="average">' . number_format($average, 1) . '</span>';
        echo '<span class="count">(' . $count . ' reviews)</span>';
        echo '</div>';
    }
}
?>
```

---

## Common Patterns

### Star Rating Display (Visual Stars)

#### Option 1: Simple Emoji Stars
```php
<?php
$rating = get_post_meta(get_the_ID(), 'bw_rating', true);
if ($rating && is_numeric($rating)) {
    $stars = intval($rating);
    $empty = 5 - $stars;
    
    echo '<div class="star-rating">';
    echo str_repeat('⭐', $stars);
    echo str_repeat('☆', $empty);
    echo '</div>';
}
?>
```

#### Option 2: SVG Stars (Styleable with CSS)
```php
<?php
$rating = get_post_meta(get_the_ID(), 'bw_rating', true);
if ($rating && is_numeric($rating)) {
    $stars = intval($rating);
    $empty = 5 - $stars;
    
    // SVG star icon (FontAwesome)
    $svg_star = '<svg aria-hidden="true" focusable="false" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512" class="bw-star"><path d="M259.3 17.8L194 150.2 47.9 171.5c-26.2 3.8-36.7 36.1-17.7 54.6l105.7 103-25 145.5c-4.5 26.3 23.2 46 46.4 33.7L288 439.6l130.7 68.7c23.2 12.2 50.9-7.4 46.4-33.7l-25-145.5 105.7-103c19-18.5 8.5-50.8-17.7-54.6L382 150.2 316.7 17.8c-11.7-23.6-45.6-23.9-57.4 0z"></path></svg>';
    
    echo '<div class="bw-star-rating">';
    
    // Filled stars
    for ($i = 0; $i < $stars; $i++) {
        echo '<span class="bw-star-filled">' . $svg_star . '</span>';
    }
    
    // Empty stars
    for ($i = 0; $i < $empty; $i++) {
        echo '<span class="bw-star-empty">' . $svg_star . '</span>';
    }
    
    echo '</div>';
}
?>
```

**Add this CSS to Breakdance Global Styles:**
```css
/* Star Rating Container */
.bw-star-rating {
    display: inline-flex;
    align-items: center;
    gap: 2px;
}

/* Base star SVG styling */
.bw-star-rating .bw-star {
    width: 1em;
    height: 1em;
    display: block;
}

/* Filled stars - customize color here */
.bw-star-rating .bw-star-filled .bw-star {
    fill: #FFD700; /* Gold color */
}

/* Empty stars - customize color here */
.bw-star-rating .bw-star-empty .bw-star {
    fill: #E0E0E0; /* Light gray */
}

/* Optional: Different sizes */
.bw-star-rating.size-small .bw-star {
    width: 0.875em;
    height: 0.875em;
}

.bw-star-rating.size-large .bw-star {
    width: 1.5em;
    height: 1.5em;
}

/* Optional: Hover effect */
.bw-star-rating .bw-star-filled:hover .bw-star {
    fill: #FFA500; /* Orange on hover */
}
```

**Usage with size modifier:**
```php
echo '<div class="bw-star-rating size-large">'; // or size-small
```

**Common Color Schemes:**
```css
/* Gold & Gray (default) */
.bw-star-filled .bw-star { fill: #FFD700; }
.bw-star-empty .bw-star { fill: #E0E0E0; }

/* Orange & Light Orange */
.bw-star-filled .bw-star { fill: #FF9800; }
.bw-star-empty .bw-star { fill: #FFE0B2; }

/* Blue (Brand) */
.bw-star-filled .bw-star { fill: #2196F3; }
.bw-star-empty .bw-star { fill: #BBDEFB; }

/* Purple (Accent) */
.bw-star-filled .bw-star { fill: #9C27B0; }
.bw-star-empty .bw-star { fill: #E1BEE7; }

/* Dark Mode */
.bw-star-filled .bw-star { fill: #FFD700; }
.bw-star-empty .bw-star { fill: #424242; }
```

### Formatted Date Display
```php
<?php
$date = get_post_meta(get_the_ID(), 'bw_date', true);
$precision = get_post_meta(get_the_ID(), 'bw_date_precision', true) ?: 'full';

if ($date) {
    $ts = strtotime($date);
    if ($ts) {
        switch ($precision) {
            case 'year':
                echo date_i18n('Y', $ts);
                break;
            case 'month-year':
                echo date_i18n('F Y', $ts);
                break;
            default:
                echo date_i18n(get_option('date_format'), $ts);
        }
    }
}
?>
```

### Platform Badge
```php
<?php
$terms = get_the_terms(get_the_ID(), 'bw_review_platform');
if ($terms && !is_wp_error($terms)) {
    $platform = $terms[0]->name;
    $slug = $terms[0]->slug;
    echo '<span class="platform-badge platform-' . esc_attr($slug) . '">';
    echo esc_html($platform);
    echo '</span>';
}
?>
```

### Check if Review Has Related Project
```php
<?php
$project_id = get_field('bw_related_project');
if ($project_id) {
    // Has related project
    echo '<span class="has-project">✓ Linked to Project</span>';
} else {
    // Standalone review
    echo '<span class="standalone">Standalone Review</span>';
}
?>
```

### Featured Badge
```php
<?php
$is_featured = get_post_meta(get_the_ID(), 'bw_is_featured', true);
if ($is_featured === '1') {
    echo '<span class="featured-badge">⭐ Featured</span>';
}
?>
```

---

## Image Sizes Reference

When using `get_the_post_thumbnail()` for project featured images:

```php
get_the_post_thumbnail($project_id, 'thumbnail')     // ~150x150
get_the_post_thumbnail($project_id, 'medium')        // ~300x300
get_the_post_thumbnail($project_id, 'medium_large')  // ~768px
get_the_post_thumbnail($project_id, 'large')         // ~1024px
get_the_post_thumbnail($project_id, 'full')          // Original size
get_the_post_thumbnail($project_id, array(400, 300)) // Custom size
```

---

## Tips & Best Practices

1. **Always cast to array** when looping `bw_reviews_related`:
   ```php
   foreach ((array)$review_ids as $review_id)
   ```

2. **Check if field exists** before using:
   ```php
   if ($review_ids) { ... }
   ```

3. **Use fallbacks** for optional fields:
   ```php
   $excerpt = get_post_meta($review_id, 'bw_review_excerpt', true);
   if (empty($excerpt)) {
       // Fallback logic
   }
   ```

4. **Escape output** for security:
   ```php
   echo esc_html($customer_name);
   echo esc_url($verify_url);
   echo esc_attr($class);
   ```

5. **Featured images** - Always check if they exist:
   ```php
   if (has_post_thumbnail($project_id)) { ... }
   ```

---

## Troubleshooting

### Reviews not showing in Breakdance loop?
- Visit **Settings > Permalinks** and click Save to flush rewrite rules
- Check that Reviews CPT is enabled in Site Essentials > Custom Posts

### ACF field returning empty?
- Ensure you've saved the relationship on at least one review/project
- Check that ACF and ACF Extended plugins are active
- Verify field name is correct: `bw_related_project` or `bw_reviews_related`

### No related reviews showing on Project?
- Make sure the relationship is set from the Review side (Review → Project)
- ACF Extended handles the bidirectional sync automatically
- If ACF Extended is not active, relationship only works one way

### Shortcodes not working?
- Ensure Reviews CPT is enabled in Site Essentials
- Check that you're using the correct shortcode name
- For filtered stats, verify platform slugs match taxonomy terms

---

## LLM-Ready Review Verification File

### Overview

When Reviews CPT is enabled, an auto-generated review verification file is available at:

```
https://yourdomain.com/docs/review-verification.txt
```

### Purpose

This file provides:
- **LLM-readable format** for AI tools like ChatGPT, Claude, and Gemini
- **Complete review data** including ratings, dates, outcomes, and source URLs
- **Platform-grouped reviews** (Google, Facebook, etc.)
- **Overall statistics** (average ratings per platform, total count)

### Example Output

```
# **Business Name — Client Reviews**

**Overall Rating:** 5.0 / 5.0 from 9 Reviews
**Google:** 5.0 / 5.0 from 7 Reviews
**Facebook:** 5.0 / 5.0 from 2 Reviews

**Last Updated:** February 10, 2026

---

## **Client Reviews (7 Verified Google Reviews)**

### **1. John Smith (Business Owner)**

**Date:** January 15, 2025
**Rating:** 5.0

"Exceptional service and attention to detail. Highly recommend!"

**What this proves:** Improved conversion rate by 300%

**Canonical Link:** [Google](https://g.page/r/...)

---
```

### Usage

1. **Manual reference**: Copy the URL and provide it to LLMs for context about your reviews
2. **Future automation**: Can be integrated with AI tools for automated review analysis
3. **Documentation**: Share with team members or stakeholders for review summaries

### Technical Details

- **Format**: Plain text with Markdown formatting
- **Updates**: Generated dynamically on each request (always current)
- **Requires**: Reviews CPT enabled in Site Essentials
- **Data source**: All published reviews in `bw_reviews` post type
- **Business name**: Uses `bw_business_name` option (from Business Info), falls back to site name

---

## Need Help?

- **Review all custom fields**: `wp-admin/post.php?post={review_id}&action=edit`
- **Manage platforms**: `wp-admin/edit-tags.php?taxonomy=bw_review_platform&post_type=bw_reviews`
- **CSV Import**: `wp-admin/admin.php?page=site-essentials-cpt`
- **View this guide**: `/wp-content/mu-plugins/site-essentials/Modules/CustomPosts/REVIEWS-USAGE-GUIDE.md`
- **Review verification file**: `/docs/review-verification.txt` (when Reviews CPT enabled)
