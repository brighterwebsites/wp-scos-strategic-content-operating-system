<?php
/**
 * Custom Posts (Recommended CPT) Module
 *
 * Registers optional custom post types and taxonomy support:
 * - Customer Success Stories (post type: projects, has_archive, slug: projects)
 * - Include WP Categories for projects
 * - Include WP Tags for projects
 *
 * When an option is enabled the CPT/taxonomy is registered; when disabled it is not.
 *
 * @package    SiteEssentials
 * @subpackage Modules\CustomPosts
 * @version    1.0.0
 * @since      1.0.0
 */

namespace SiteEssentials\Modules\CustomPosts;

use SiteEssentials\Core\Module_Interface;
use SiteEssentials\Core\Settings_Manager;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cpt_Module Class
 *
 * @since 1.0.0
 */
class Cpt_Module implements Module_Interface {
    /**
     * Settings Manager instance
     *
     * @since 1.0.0
     * @var   Settings_Manager
     */
    private $settings;

    /**
     * Post type slug for Customer Success Stories
     */
    const POST_TYPE_PROJECTS = 'projects';

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->settings = Settings_Manager::instance();
    }

    /**
     * Get module ID
     *
     * @since  1.0.0
     * @return string
     */
    public static function get_id() {
        return 'cpt';
    }

    /**
     * Get module name
     *
     * @since  1.0.0
     * @return string
     */
    public static function get_name() {
        return __('Custom Posts', 'site-essentials');
    }

    /**
     * Get module description
     *
     * @since  1.0.0
     * @return string
     */
    public static function get_description() {
        return __('Recommended custom post types: Customer Success Stories (projects), and optional WP Categories/Tags for them.', 'site-essentials');
    }

    /**
     * Get module tier
     *
     * @since  1.0.0
     * @return string
     */
    public static function get_tier() {
        return 'basic';
    }

    /**
     * Get module dependencies
     *
     * @since  1.0.0
     * @return array
     */
    public static function get_dependencies() {
        return [];
    }

    /**
     * Get module version
     *
     * @since  1.0.0
     * @return string
     */
    public static function get_version() {
        return '1.0.0';
    }

    /**
     * Get default CPT options
     *
     * @since  1.0.0
     * @return array
     */
    private function get_default_options() {
        return [
            'customer_success_stories' => true,
            'include_categories'       => false,
            'include_tags'             => false,
        ];
    }

    /**
     * Initialize module
     *
     * Registers CPT and taxonomies only when corresponding options are enabled.
     *
     * @since 1.0.0
     * @return void
     */
    public function init() {
        $opts = $this->settings->get_module_setting('cpt', null, $this->get_default_options());
        $opts = wp_parse_args($opts, $this->get_default_options());

        if (!empty($opts['customer_success_stories'])) {
            add_action('init', [$this, 'register_projects_cpt'], 20);
        }

        if (!empty($opts['customer_success_stories'])) {
            if (!empty($opts['include_categories'])) {
                add_action('init', [$this, 'register_projects_categories'], 25);
            }
            if (!empty($opts['include_tags'])) {
                add_action('init', [$this, 'register_projects_tags'], 25);
            }
        }
    }

    /**
     * Register Projects CPT (Customer Success Stories)
     *
     * post_type=projects, has_archive, rewrite slug=projects.
     *
     * @since 1.0.0
     * @return void
     */
    public function register_projects_cpt() {
        $labels = [
            'name'               => _x('Customer Success Stories', 'post type general name', 'site-essentials'),
            'singular_name'      => _x('Customer Success Story', 'post type singular name', 'site-essentials'),
            'menu_name'          => _x('Success Stories', 'admin menu', 'site-essentials'),
            'name_admin_bar'     => _x('Success Story', 'add new on admin bar', 'site-essentials'),
            'add_new'            => _x('Add New', 'project', 'site-essentials'),
            'add_new_item'       => __('Add New Success Story', 'site-essentials'),
            'new_item'           => __('New Success Story', 'site-essentials'),
            'edit_item'          => __('Edit Success Story', 'site-essentials'),
            'view_item'          => __('View Success Story', 'site-essentials'),
            'all_items'          => __('Customer Success Stories', 'site-essentials'),
            'search_items'       => __('Search Success Stories', 'site-essentials'),
            'not_found'          => __('No success stories found.', 'site-essentials'),
            'not_found_in_trash' => __('No success stories found in Trash.', 'site-essentials'),
        ];

        $args = [
            'labels'             => $labels,
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'query_var'           => true,
            'rewrite'             => ['slug' => 'projects'],
            'capability_type'     => 'post',
            'has_archive'         => true,
            'hierarchical'       => false,
            'menu_position'       => 20,
            'menu_icon'           => 'dashicons-portfolio',
            'supports'            => ['title', 'editor', 'thumbnail', 'excerpt', 'revisions'],
            'show_in_rest'        => true,
        ];

        register_post_type(self::POST_TYPE_PROJECTS, $args);
    }

    /**
     * Register WordPress Category taxonomy for projects
     *
     * @since 1.0.0
     * @return void
     */
    public function register_projects_categories() {
        register_taxonomy_for_object_type('category', self::POST_TYPE_PROJECTS);
    }

    /**
     * Register WordPress Tags taxonomy for projects
     *
     * @since 1.0.0
     * @return void
     */
    public function register_projects_tags() {
        register_taxonomy_for_object_type('post_tag', self::POST_TYPE_PROJECTS);
    }

    /**
     * Register settings (placeholder)
     *
     * @since 1.0.0
     * @return void
     */
    public function register_settings() {
        // Settings saved via Admin_UI save handler
    }

    /**
     * Render settings section (options page content)
     *
     * @since 1.0.0
     * @return void
     */
    public function render_settings() {
        $opts = $this->settings->get_module_setting('cpt', null, $this->get_default_options());
        $opts = wp_parse_args($opts, $this->get_default_options());

        include __DIR__ . '/views/settings.php';
    }
}
