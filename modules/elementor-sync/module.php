<?php
/**
 * Elementor Global Colors Sync Module
 *
 * Syncs brand configuration colors to Elementor global colors.
 * Single source of truth: mu-plugins/shaped-client-config.php
 *
 * @package Shaped_Core
 * @subpackage Elementor_Sync
 */

namespace Shaped\Modules\ElementorSync;

if (!defined('ABSPATH')) {
    exit;
}

// Module constants
define('SHAPED_ELEMENTOR_SYNC_VERSION', '1.0.0');
define('SHAPED_ELEMENTOR_SYNC_DIR', __DIR__ . '/');

/**
 * Load module components
 */
require_once SHAPED_ELEMENTOR_SYNC_DIR . 'class-color-mapper.php';
require_once SHAPED_ELEMENTOR_SYNC_DIR . 'class-color-sync.php';

/**
 * Initialize module after Elementor is loaded
 */
add_action('elementor/loaded', function() {
    error_log('[Shaped Elementor Sync] Module loaded');
}, 20);

/**
 * Auto-sync on plugin activation
 * This runs once when the plugin is activated
 */
register_activation_hook(SHAPED_PLUGIN_FILE, function() {
    // Delay sync to ensure Elementor is loaded
    add_action('init', function() {
        if (Color_Sync::is_elementor_active()) {
            $result = Color_Sync::sync();

            if (is_wp_error($result)) {
                error_log('[Shaped Elementor Sync] Activation sync failed: ' . $result->get_error_message());
            } else {
                error_log('[Shaped Elementor Sync] Activation sync completed successfully');
            }
        }
    }, 999);
});

/**
 * Manual sync trigger via WordPress action
 * Usage: do_action('shaped/elementor/trigger_sync');
 */
add_action('shaped/elementor/trigger_sync', function() {
    if (!Color_Sync::is_elementor_active()) {
        error_log('[Shaped Elementor Sync] Manual sync skipped - Elementor not active');
        return;
    }

    $result = Color_Sync::sync();

    if (is_wp_error($result)) {
        error_log('[Shaped Elementor Sync] Manual sync failed: ' . $result->get_error_message());
    } else {
        error_log('[Shaped Elementor Sync] Manual sync completed successfully');
    }
});

/**
 * Force sync (clears cache and re-syncs)
 * Usage: do_action('shaped/elementor/force_sync');
 */
add_action('shaped/elementor/force_sync', function() {
    if (!Color_Sync::is_elementor_active()) {
        error_log('[Shaped Elementor Sync] Force sync skipped - Elementor not active');
        return;
    }

    $result = Color_Sync::force_sync();

    if (is_wp_error($result)) {
        error_log('[Shaped Elementor Sync] Force sync failed: ' . $result->get_error_message());
    } else {
        error_log('[Shaped Elementor Sync] Force sync completed successfully');
    }
});

/**
 * Daily cron sync (optional safety net)
 * Can be disabled via filter: add_filter('shaped/elementor/enable_daily_sync', '__return_false');
 */
add_action('shaped_elementor_daily_sync', function() {
    if (!Color_Sync::is_elementor_active()) {
        return;
    }

    // Check if daily sync is enabled
    $enabled = apply_filters('shaped/elementor/enable_daily_sync', false);
    if (!$enabled) {
        return;
    }

    $result = Color_Sync::sync();

    if (is_wp_error($result)) {
        error_log('[Shaped Elementor Sync] Daily sync failed: ' . $result->get_error_message());
    }
});

// Schedule daily cron if not already scheduled and enabled
if (apply_filters('shaped/elementor/enable_daily_sync', false)) {
    if (!wp_next_scheduled('shaped_elementor_daily_sync')) {
        wp_schedule_event(time(), 'daily', 'shaped_elementor_daily_sync');
    }
}

/**
 * Sync when Elementor kit is activated/changed
 */
add_action('elementor/kit/activated', function($kit_id) {
    error_log('[Shaped Elementor Sync] Kit activated (ID: ' . $kit_id . '), triggering sync');

    $result = Color_Sync::sync();

    if (is_wp_error($result)) {
        error_log('[Shaped Elementor Sync] Kit activation sync failed: ' . $result->get_error_message());
    }
});

/**
 * Helper function to manually trigger sync from anywhere
 * Usage: shaped_elementor_sync();
 *
 * @return bool|\WP_Error
 */
function shaped_elementor_sync() {
    return Color_Sync::sync();
}

/**
 * Helper function to get sync status
 * Usage: shaped_elementor_sync_status();
 *
 * @return array
 */
function shaped_elementor_sync_status() {
    return Color_Sync::get_sync_status();
}
