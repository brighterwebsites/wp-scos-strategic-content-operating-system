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

## Need Help?

- **Review all custom fields**: `wp-admin/post.php?post={review_id}&action=edit`
- **Manage platforms**: `wp-admin/edit-tags.php?taxonomy=bw_review_platform&post_type=bw_reviews`
- **CSV Import**: `wp-admin/admin.php?page=site-essentials-cpt`
- **View this guide**: `/wp-content/mu-plugins/site-essentials/Modules/CustomPosts/REVIEWS-USAGE-GUIDE.md`
