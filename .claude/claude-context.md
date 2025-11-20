# Claude Code Context - Site Essentials

**Quick reference for Claude Code when working on this project**

---

## Project Overview

**Name:** Site Essentials (formerly "Brighter Core")
**Type:** WordPress MU Plugin → Modular Premium Product
**Status:** Undergoing major refactor (see REFACTOR_PLAN.md)
**Purpose:** Site management system for client websites

---

## Key Documents

Read these first:
- `AUDIT.md` - Complete feature inventory
- `STRATEGY.md` - Long-term vision & product tiers
- `REFACTOR_PLAN.md` - Step-by-step migration plan

---

## Current State (Mid-Refactor)

### Old Structure (Being Phased Out)
```
brighter-core-loader.php             # Legacy loader
brighter-core/                       # Legacy code (38+ files)
```

### New Structure (Building)
```
site-essentials.php                  # New loader
site-essentials/
├── core/                            # Core framework
├── modules/                         # Feature modules
└── assets/                          # Shared assets
```

**Both coexist during migration!** Old code still runs until modules are fully migrated.

---

## Architecture Principles

### 1. Modular Everything
Each module:
- Lives in `site-essentials/modules/{name}/`
- Implements `Module_Interface`
- Can be toggled on/off
- Loads zero code when disabled

### 2. Namespacing
```php
namespace SiteEssentials\Core;           // Core classes
namespace SiteEssentials\Modules\SEO;    // Modules
```

### 3. Settings
- Core settings: `site_essentials_settings`
- Per-module: `site_essentials_{module_id}`
- Unified Settings Manager class

### 4. Caching
- Use `Cache_Helper::remember()` for expensive operations
- Cache group: `site_essentials_{module_name}`
- Auto-clear on save_post, delete_post

---

## Coding Standards

### Security
```php
// Always check capabilities
if (!current_user_can('manage_options')) {
    wp_die('Unauthorized');
}

// Always verify nonces
check_ajax_referer('my_action_nonce', 'nonce');

// Always sanitize input
$value = sanitize_text_field($_POST['value']);

// Always escape output
echo esc_html($value);
```

### Performance
```php
// Use caching for expensive queries
$data = Cache_Helper::remember('my_key', function() {
    return expensive_query();
}, 3600, 'my_module');

// Check if module is enabled before loading
if (!$settings->is_module_enabled('my_module')) {
    return;
}
```

### WordPress Hooks
```php
// Use appropriate hook priorities
add_action('init', [$this, 'register'], 10);
add_action('wp_head', [$this, 'inject'], 5);   // Early
add_filter('the_content', [$this, 'filter'], 20); // Late
```

---

## Common Tasks

### Creating a New Module

1. **Create module folder:**
   ```
   site-essentials/modules/my-module/
   ├── My_Module.php
   ├── README.md
   └── views/
       └── settings.php
   ```

2. **Implement Module_Interface:**
   ```php
   namespace SiteEssentials\Modules\MyModule;

   use SiteEssentials\Core\Module_Interface;

   class My_Module implements Module_Interface {
       public static function get_id() { return 'my-module'; }
       public static function get_name() { return 'My Module'; }
       public static function get_description() { return 'Does X'; }
       public static function get_tier() { return 'basic'; }
       public static function get_dependencies() { return []; }
       public static function get_version() { return '1.0.0'; }

       public function init() {
           // Module initialization here
       }

       public function render_settings() {
           include __DIR__ . '/views/settings.php';
       }
   }
   ```

3. **Register module in loader:**
   ```php
   // In site-essentials.php
   Module_Loader::register('my-module', \SiteEssentials\Modules\MyModule\My_Module::class);
   ```

---

### Adding Module Settings

```php
// In module init()
add_action('admin_init', function() {
    register_setting(
        'site_essentials_my_module',
        'site_essentials_my_module',
        [
            'sanitize_callback' => [$this, 'sanitize_settings'],
        ]
    );
});

// Sanitize callback
public function sanitize_settings($input) {
    return [
        'enabled' => isset($input['enabled']),
        'value' => sanitize_text_field($input['value']),
    ];
}

// Get settings
$settings = get_option('site_essentials_my_module', []);
```

---

### Using Cache Helper

```php
use SiteEssentials\Core\Cache_Helper;

// Simple get/set
Cache_Helper::set('my_key', $data, 3600, 'my_module');
$data = Cache_Helper::get('my_key', 'my_module');

// Remember pattern (best)
$data = Cache_Helper::remember('my_key', function() {
    return expensive_query();
}, 3600, 'my_module');

// Clear cache
Cache_Helper::delete('my_key', 'my_module');
Cache_Helper::flush('my_module'); // Clear all for module
```

---

### Registering Post Meta

```php
register_post_meta('', 'my_meta_key', [
    'type' => 'string',
    'single' => true,
    'sanitize_callback' => 'sanitize_text_field',
    'show_in_rest' => false,
    'auth_callback' => function() {
        return current_user_can('edit_posts');
    },
]);
```

---

## Module Migration Checklist

When migrating a feature from old to new:

- [ ] Read old code thoroughly
- [ ] Create new module structure
- [ ] Implement Module_Interface
- [ ] Copy functionality (refactor if needed)
- [ ] Add settings page
- [ ] Test with module enabled
- [ ] Test with module disabled
- [ ] Verify no code loads when disabled
- [ ] Document in module README.md
- [ ] Update REFACTOR_PLAN.md progress

---

## Database Schema

### Post Meta (Legacy - being migrated)
```
_bw_preloads                    # Performance: Preload URLs
bw_internal_link_count          # Content Analysis
bw_external_link_count          # Content Analysis
bw_word_count                   # Content Analysis
bw_image_count                  # Content Analysis
bw_h2_count                     # Content Analysis
bw_notes                        # Content Strategy
bw_intent                       # Content Strategy
bw_purpose                      # Content Strategy
bw_pillar_page_id               # Content Strategy
_brt_opt_status                 # Content Strategy
bw_index_status                 # Content Strategy
bw_primary_altc_id              # ALTC
bw_primary_topic_id             # ALTC
bw_cont_maturity                # ALTC
_faq_parent_page                # FAQ
_faq_tldr                       # FAQ
```

### Options
```
site_essentials_settings        # Core settings
site_essentials_{module_id}     # Per-module settings
brighter_*                      # Legacy (to be migrated)
```

### Taxonomies
```
altc_strategic_lens             # ALTC
altc_topic                      # ALTC
```

---

## Feature Flags

Some features use flags to control old vs. new code:

```php
// Check if new module is enabled
if (Settings_Manager::instance()->is_module_enabled('analytics')) {
    // Use new code
} else {
    // Use old code (or nothing)
}
```

---

## Known Issues

### Current Bugs (Priority: Fix During Refactor)

1. **Permission error:** `?bw_analyze_now=1` returns "Sorry, you are not allowed to access this page"
   - Location: `class-content-analysis-seeder.php`
   - Fix: Add proper capability check

2. **Background processing unclear:** Is it 5 per post or 5 total sitewide?
   - Location: Content analysis
   - Fix: Document and clarify

3. **Query count fluctuating**
   - Needs profiling with Query Monitor
   - May need to optimize admin columns

---

## Performance Notes

### Query Optimization
- Admin columns can trigger N+1 queries
- Solution: Prime meta cache with `update_meta_cache()`
- Use `WP_Query` with `update_post_meta_cache` and `update_post_term_cache`

### Caching Strategy
- Object cache for business info (working well)
- Transients for infrequent queries
- Runtime static cache for repeated calls in single request

### Asset Loading
- Enqueue admin assets only on relevant pages
- Use `$hook` parameter in `admin_enqueue_scripts`
- Defer/async where possible

---

## Testing Commands

```bash
# Check for PHP errors
tail -f /path/to/error.log

# Search for deprecated functions
grep -r "deprecated" site-essentials/

# Find hardcoded values
grep -r "brighterwebsites.com" site-essentials/
grep -r "Brighter Websites" site-essentials/

# Count lines of code
find site-essentials/ -name "*.php" -exec wc -l {} + | sort -n
```

---

## Development Workflow
Git Repo is on https://github.com/ not local
Before starting new updates suggest whether or or not to pull previous commits to main, avoid mulitple unpulled branches unless a strategic need for it.  

### Making Changes

1. **Work on your branch:**
   ```bash
   git checkout claude/your-feature-branch
   ```

2. **Make changes**

3. **Test locally:**
   - Enable Query Monitor
   - Check error logs
   - Test with module enabled/disabled

4. **Commit:**
   ```bash
   git add .
   git commit -m "FEATURE: Add X module"
   ```

5. **Push:**
   ```bash
   git push -u origin claude/your-feature-branch
   ```

---

### Git Commit Message Format

```
TYPE: Brief description

Longer explanation if needed.

- Bullet points for details
- Multiple changes

Fixes #123
```

**Types:**
- `FEATURE:` - New feature
- `FIX:` - Bug fix
- `REFACTOR:` - Code restructure
- `DOCS:` - Documentation
- `PERF:` - Performance improvement
- `TEST:` - Tests
- `STYLE:` - Code style/formatting

---

## WordPress APIs Used

### Core APIs
- Settings API (settings pages)
- REST API (custom endpoints)
- Rewrite API (custom URLs)
- Transients API (caching)
- Object Cache API (performance)
- Customizer API (future: for settings)

### Post/Meta APIs
- Custom Post Types
- Custom Taxonomies
- Post Meta Registration
- Term Meta Registration

### Admin APIs
- Admin Menu API
- Meta Boxes API
- Admin Columns API
- Quick/Bulk Edit

### Frontend APIs
- Shortcode API
- Template API
- Gutenberg Blocks (registerBlockType)

---

## Third-Party Integrations

### Optional Integrations
- **SEOPress** - Schema mapping, OG override
- **LiteSpeed Cache** - Lazy load CSS, cache compatibility
- **ACF** - Field scanning in content analysis
- **Breakdance** - Content scanning

### External Services
- **Google Analytics 4** - Tracking & measurement
- **CustomGPT API** - AI content analysis (future)
- **Make.com** - Monitoring webhooks (future)

---

## Helpful Filters & Actions

### Custom Hooks (Extensibility)

```php
// Allow other code to add tabs to support page
$tabs = apply_filters('brighter_support_tabs', $tabs);

// Allow custom exclusion tags in content analysis
$tags = apply_filters('bw_content_analysis_exclude_tags', ['header', 'footer', 'nav']);

// Allow custom exclusion classes
$classes = apply_filters('bw_content_analysis_exclude_classes', ['ga-hrcy-header']);

// Allow custom social domains (to exclude from link count)
$domains = apply_filters('bw_content_analysis_social_domains', ['facebook.com', ...]);
```

---

## Debugging Tips

### Enable WordPress Debug Mode
```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Check Object Cache
```php
// See if object cache is working
if (wp_cache_get('test', 'site_essentials')) {
    echo 'Cache is working';
}
```

### Profile Queries
```php
// Use Query Monitor plugin
// Or manually:
global $wpdb;
error_log('Queries: ' . $wpdb->num_queries);
error_log('Time: ' . timer_stop());
```

### Check Module Status
```php
$settings = \SiteEssentials\Core\Settings_Manager::instance();
var_dump($settings->get_all());
```

---

## Common Gotchas

### Don't Do This ❌
```php
// Don't load code if module is disabled
if (Settings_Manager::instance()->is_module_enabled('analytics')) {
    // Load code here - too late!
}
```

### Do This Instead ✅
```php
// Module loader handles this - only requires file if enabled
Module_Loader::load_modules();
```

---

### Don't Do This ❌
```php
// Don't use global functions from other modules
$business_name = brighter_get_business_info('business_name');
```

### Do This Instead ✅
```php
// Use module dependencies and get instance
$business_info = Module_Loader::get_module('business-info');
$business_name = $business_info->get('business_name');
```

---

## Resources

### WordPress Documentation
- https://developer.wordpress.org/apis/
- https://developer.wordpress.org/plugins/
- https://developer.wordpress.org/coding-standards/

### Plugin Handbook
- https://developer.wordpress.org/plugins/security/
- https://developer.wordpress.org/plugins/settings/

### Testing
- https://make.wordpress.org/core/handbook/testing/automated-testing/
- https://github.com/wp-phpunit/wp-phpunit

---

## Quick Reference

### File Paths
```php
SITE_ESSENTIALS_PATH       // /path/to/mu-plugins/site-essentials/
SITE_ESSENTIALS_URL        // https://example.com/wp-content/mu-plugins/site-essentials/
```

### Core Classes
```php
\SiteEssentials\Core\Module_Loader        // Module management
\SiteEssentials\Core\Settings_Manager     // Settings API
\SiteEssentials\Core\Cache_Helper         // Caching
\SiteEssentials\Core\Admin_UI             // Settings page
```

### Helper Functions
```php
// Get module instance
Module_Loader::get_module('analytics');

// Check if module enabled
Settings_Manager::instance()->is_module_enabled('analytics');

// Cache operations
Cache_Helper::remember('key', callable, 3600, 'module');
```
### Deployment Helpers
*alias createdfor both but Vanessa cant remember them 
Create similar for other sites on VPS if needed 
current code in script pulls from main brance
/root/scripts/deploy-guerilla.sh
/root/scripts/deploy-brighterwebsites.sh

---

## Version History

**Current:** 4.3.0 (legacy)
**Next:** 1.0.0 (new modular version)

### Breaking Changes in 1.0.0
- Namespace change: `Brighter_*` → `SiteEssentials\`
- Option names change: `brighter_*` → `site_essentials_*`
- File structure completely reorganized
- Module system introduced
- Settings centralized

**Migration Required:** See REFACTOR_PLAN.md

---

## Questions?

If you're uncertain about anything:

1. Check `AUDIT.md` for how it currently works
2. Check `STRATEGY.md` for how it should work
3. Check `REFACTOR_PLAN.md` for the migration plan
4. Look at similar modules for examples
5. Check WordPress Codex/Developer documentation

**Remember:** Disabled modules must not load ANY code!

---

**End of Claude Context**

*Keep this updated as the refactor progresses.*
