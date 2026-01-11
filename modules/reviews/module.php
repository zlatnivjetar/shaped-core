<?php
/**
 * Shaped Reviews Module
 * 
 * Syncs reviews from Supabase (aggregated from Booking, Expedia, Google, TripAdvisor)
 * and displays them via WordPress CPT + Elementor Loop.
 * 
 * Activation: Define SHAPED_ENABLE_REVIEWS in wp-config.php
 * Required: SUPABASE_URL, SUPABASE_SERVICE_KEY constants
 * 
 * @package Shaped_Core
 * @subpackage Reviews
 * @since 2.0.0
 */

namespace Shaped\Modules\Reviews;

if (!defined('ABSPATH')) {
    exit;
}

// Check if module is enabled
if (!defined('SHAPED_ENABLE_REVIEWS') || !SHAPED_ENABLE_REVIEWS) {
    return;
}

/**
 * Module constants
 */
define('SHAPED_REVIEWS_VERSION', '2.0.0');
define('SHAPED_REVIEWS_DIR', __DIR__ . '/');

/**
 * Load module components
 */
require_once SHAPED_REVIEWS_DIR . 'class-cpt.php';
require_once SHAPED_REVIEWS_DIR . 'class-sync.php';
require_once SHAPED_REVIEWS_DIR . 'class-admin.php';
require_once SHAPED_REVIEWS_DIR . 'shortcodes.php';
require_once SHAPED_REVIEWS_DIR . 'assets.php';

/**
 * Initialize module
 */
add_action('init', function() {
    // Register CPT and taxonomies
    CPT::register();

    // Initialize sync system
    new Sync();

    // Initialize admin enhancements
    if (is_admin()) {
        Admin::init();
    } else {
        // Initialize frontend hooks (sorting, etc.)
        Admin::init_frontend();
    }
}, 5);

/**
 * Run migrations on activation
 */
add_action('admin_init', function() {
    // Migrate provider meta to taxonomy (one-time)
    if (get_option('shaped_reviews_taxonomy_migrated') !== 'yes') {
        Admin::migrate_providers_to_taxonomy();
        update_option('shaped_reviews_taxonomy_migrated', 'yes');
    }

    // Assign themes to existing reviews (one-time)
    if (get_option('shaped_reviews_themes_assigned') !== 'yes') {
        Admin::assign_themes_to_existing();
        update_option('shaped_reviews_themes_assigned', 'yes');
    }

    // Clean up old provider taxonomy terms (one-time)
    if (get_option('shaped_reviews_providers_cleaned') !== 'yes') {
        Admin::cleanup_old_provider_terms();
        update_option('shaped_reviews_providers_cleaned', 'yes');
    }

    // Set content_locked to '1' for all existing reviews (one-time migration)
    // Changed from content_lock_added to content_lock_default_enabled to re-run
    if (get_option('shaped_reviews_content_lock_default_enabled') !== 'yes') {
        // Set all existing reviews to locked by default
        // This protects existing content from being overwritten on sync
        $reviews = get_posts([
            'post_type'      => CPT::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'fields'         => 'ids'
        ]);

        foreach ($reviews as $review_id) {
            // Set to '1' (locked) regardless of current value
            update_post_meta($review_id, 'content_locked', '1');
        }

        update_option('shaped_reviews_content_lock_default_enabled', 'yes');
    }
});

/**
 * Schedule automatic sync
 */
add_action('init', function() {
    // Check brand config first, fallback to constant
    $auto_sync = false;

    if (function_exists('shaped_brand')) {
        $auto_sync = shaped_brand('integrations.supabase.autoSync', false);
    }

    // Allow constant override for backwards compatibility
    if (defined('SHAPED_REVIEWS_AUTO_SYNC') && SHAPED_REVIEWS_AUTO_SYNC) {
        $auto_sync = true;
    }

    if (!$auto_sync) {
        return;
    }

    if (!wp_next_scheduled('shaped_reviews_scheduled_sync')) {
        wp_schedule_event(time(), 'daily', 'shaped_reviews_scheduled_sync');
    }
}, 20);

add_action('shaped_reviews_scheduled_sync', function() {
    $sync = new Sync();
    $results = $sync->sync_reviews();

    error_log('[Shaped Reviews] Scheduled sync completed: ' . json_encode($results));
});

/**
 * Clear schedule on deactivation
 */
if (defined('SHAPED_FILE')) {
    register_deactivation_hook(SHAPED_FILE, function() {
        wp_clear_scheduled_hook('shaped_reviews_scheduled_sync');
    });
}
