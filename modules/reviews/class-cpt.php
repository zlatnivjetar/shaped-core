<?php
/**
 * Reviews Custom Post Type
 * 
 * @package Shaped_Core
 * @subpackage Reviews
 */

namespace Shaped\Modules\Reviews;

if (!defined('ABSPATH')) {
    exit;
}

class CPT {

    /**
     * Post type name
     * Using original name for backward compatibility with existing data
     */
    const POST_TYPE = 'preelook_review';

    /**
     * Register post type and taxonomies
     */
    public static function register(): void {
        self::register_post_type();
        self::register_taxonomies();
        self::register_meta();
    }

    /**
     * Register the review post type
     */
    private static function register_post_type(): void {
        $labels = [
            'name'               => 'Reviews',
            'singular_name'      => 'Review',
            'menu_name'          => 'Guest Reviews',
            'add_new'            => 'Add New Review',
            'add_new_item'       => 'Add New Review',
            'edit_item'          => 'Edit Review',
            'new_item'           => 'New Review',
            'view_item'          => 'View Review',
            'search_items'       => 'Search Reviews',
            'not_found'          => 'No reviews found',
            'not_found_in_trash' => 'No reviews found in trash'
        ];

        $args = [
            'labels'              => $labels,
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'query_var'           => true,
            'rewrite'             => ['slug' => 'reviews'],
            'capability_type'     => 'post',
            'has_archive'         => true,
            'hierarchical'        => false,
            'menu_position'       => 25,
            'menu_icon'           => 'dashicons-star-filled',
            'supports'            => ['title', 'editor', 'custom-fields'],
            'show_in_rest'        => true,
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Register taxonomies
     */
    private static function register_taxonomies(): void {
        // Provider taxonomy
        register_taxonomy('review_provider', self::POST_TYPE, [
            'labels' => [
                'name'          => 'Providers',
                'singular_name' => 'Provider',
                'all_items'     => 'All Providers',
                'edit_item'     => 'Edit Provider',
                'view_item'     => 'View Provider',
                'update_item'   => 'Update Provider',
                'add_new_item'  => 'Add New Provider',
                'new_item_name' => 'New Provider Name',
                'menu_name'     => 'Providers'
            ],
            'public'            => true,
            'show_ui'           => true,
            'show_in_menu'      => true,
            'show_in_nav_menus' => true,
            'show_in_rest'      => true,
            'hierarchical'      => false,
            'rewrite'           => ['slug' => 'review-provider'],
            'query_var'         => true
        ]);

        // Create default provider terms
        $providers = [
            'booking'     => 'Booking',
            'google'      => 'Google',
            'tripadvisor' => 'TripAdvisor',
            'expedia'     => 'Expedia'
        ];

        foreach ($providers as $slug => $name) {
            if (!term_exists($slug, 'review_provider')) {
                wp_insert_term($name, 'review_provider', ['slug' => $slug]);
            }
        }

        // Themes taxonomy
        register_taxonomy('review_themes', self::POST_TYPE, [
            'labels' => [
                'name'          => 'Review Themes',
                'singular_name' => 'Theme',
                'all_items'     => 'All Themes',
                'edit_item'     => 'Edit Theme',
                'view_item'     => 'View Theme',
                'update_item'   => 'Update Theme',
                'add_new_item'  => 'Add New Theme',
                'new_item_name' => 'New Theme Name',
                'menu_name'     => 'Themes'
            ],
            'public'            => true,
            'show_ui'           => true,
            'show_in_menu'      => true,
            'show_in_nav_menus' => true,
            'show_in_rest'      => true,
            'hierarchical'      => false,
            'rewrite'           => ['slug' => 'review-theme'],
            'query_var'         => true
        ]);

        // Create default theme terms
        $themes = [
            'easy-parking'    => 'Easy Parking',
            'clean-place'     => 'Clean Place',
            'good-amenities'  => 'Good Amenities',
            'great-breakfast' => 'Great Breakfast',
            'mixed-reviews'   => 'Mixed Reviews',
            'well-equipped'   => 'Well equipped',
            'some-challenges' => 'Some Challenges'
        ];

        foreach ($themes as $slug => $name) {
            if (!term_exists($slug, 'review_themes')) {
                wp_insert_term($name, 'review_themes', ['slug' => $slug]);
            }
        }
    }

    /**
     * Register meta fields
     */
    private static function register_meta(): void {
        $meta_fields = [
            'external_key'    => 'string',
            'provider'        => 'string',
            'review_date'     => 'string',
            'review_rating'   => 'number',
            'author_name'     => 'string',
            'is_featured'     => 'boolean',
            'priority'        => 'number',
            'featured_locked' => 'boolean',
            'status'          => 'string',
        ];

        foreach ($meta_fields as $key => $type) {
            register_post_meta(self::POST_TYPE, $key, [
                'type'              => $type,
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => self::get_sanitize_callback($type),
            ]);
        }
    }

    /**
     * Get sanitize callback for meta type
     */
    private static function get_sanitize_callback(string $type): callable {
        switch ($type) {
            case 'number':
                return 'absint';
            case 'boolean':
                return 'rest_sanitize_boolean';
            default:
                return 'sanitize_text_field';
        }
    }
}
