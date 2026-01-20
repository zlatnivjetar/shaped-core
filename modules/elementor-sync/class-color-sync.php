<?php
/**
 * Elementor Color Sync
 *
 * Interfaces with Elementor's Kit system to sync brand colors.
 * Handles Kit creation, updates, and error handling.
 *
 * @package Shaped_Core
 * @subpackage Elementor_Sync
 */

namespace Shaped\Modules\ElementorSync;

if (!defined('ABSPATH')) {
    exit;
}

class Color_Sync {

    /**
     * Sync brand colors to Elementor
     *
     * @return bool|\WP_Error True on success, WP_Error on failure
     */
    public static function sync() {
        // Check if Elementor is active
        if (!self::is_elementor_active()) {
            return new \WP_Error(
                'elementor_not_active',
                'Elementor is not active. Please install and activate Elementor to sync colors.'
            );
        }

        // Get or create Kit
        $kit_id = self::get_active_kit_id();
        if (!$kit_id) {
            $kit_id = self::create_kit();
            if (is_wp_error($kit_id)) {
                return $kit_id;
            }
        }

        // Map brand colors to Elementor format
        $colors = Color_Mapper::map_colors();

        // Get current kit settings
        $kit_settings = self::get_kit_settings($kit_id);

        // Merge colors with existing settings (preserve other settings)
        $kit_settings['system_colors'] = $colors['system_colors'];
        $kit_settings['custom_colors'] = $colors['custom_colors'];

        // Allow filtering before sync
        $kit_settings = apply_filters('shaped/elementor/kit_settings_before_sync', $kit_settings, $kit_id);

        // Update kit
        $success = self::update_kit_settings($kit_id, $kit_settings);

        if (!$success) {
            return new \WP_Error(
                'sync_failed',
                'Failed to update Elementor Kit settings. Please check permissions.'
            );
        }

        // Update last sync time
        update_option('shaped_elementor_last_sync', current_time('mysql'));

        // Fire action hook
        do_action('shaped/elementor/colors_synced', $kit_id, $colors);

        error_log('[Shaped Elementor Sync] Successfully synced colors to Kit ID: ' . $kit_id);

        return true;
    }

    /**
     * Get active Elementor kit ID
     *
     * @return int|null Kit post ID or null if not found
     */
    private static function get_active_kit_id(): ?int {
        $active_kit_id = get_option('elementor_active_kit');

        if (!$active_kit_id) {
            return null;
        }

        // Verify the kit post still exists
        $kit = get_post($active_kit_id);
        if (!$kit || $kit->post_type !== 'elementor_library') {
            return null;
        }

        return (int) $active_kit_id;
    }

    /**
     * Get current Elementor kit settings
     *
     * @param int $kit_id Kit post ID
     * @return array Kit settings array
     */
    private static function get_kit_settings(int $kit_id): array {
        $settings = get_post_meta($kit_id, '_elementor_page_settings', true);

        if (!is_array($settings)) {
            return [];
        }

        return $settings;
    }

    /**
     * Update Elementor kit with new settings
     *
     * @param int $kit_id Kit post ID
     * @param array $settings Kit settings
     * @return bool Success status
     */
    private static function update_kit_settings(int $kit_id, array $settings): bool {
        $result = update_post_meta($kit_id, '_elementor_page_settings', $settings);

        // Clear Elementor cache to ensure changes take effect
        if (class_exists('\Elementor\Plugin')) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
        }

        return $result !== false;
    }

    /**
     * Create Elementor kit if it doesn't exist
     *
     * @return int|\WP_Error New kit ID or error
     */
    private static function create_kit() {
        // Check if Elementor's Kit Manager is available
        if (!class_exists('\Elementor\Core\Kits\Manager')) {
            return new \WP_Error(
                'kit_manager_not_available',
                'Elementor Kit Manager not found. Please update Elementor to the latest version.'
            );
        }

        // Create new kit post
        $kit_id = wp_insert_post([
            'post_title' => 'Shaped Default Kit',
            'post_type' => 'elementor_library',
            'post_status' => 'publish',
            'meta_input' => [
                '_elementor_template_type' => 'kit',
            ],
        ]);

        if (is_wp_error($kit_id)) {
            return $kit_id;
        }

        // Set as active kit
        update_option('elementor_active_kit', $kit_id);

        error_log('[Shaped Elementor Sync] Created new Kit ID: ' . $kit_id);

        return $kit_id;
    }

    /**
     * Check if Elementor is active
     *
     * @return bool
     */
    public static function is_elementor_active(): bool {
        return did_action('elementor/loaded');
    }

    /**
     * Get last sync time
     *
     * @return string|null Timestamp or null
     */
    public static function get_last_sync_time(): ?string {
        return get_option('shaped_elementor_last_sync', null);
    }

    /**
     * Get sync status information (for admin UI)
     *
     * @return array Status data
     */
    public static function get_sync_status(): array {
        $status = [
            'elementor_active' => self::is_elementor_active(),
            'kit_id' => self::get_active_kit_id(),
            'last_sync' => self::get_last_sync_time(),
            'can_sync' => self::is_elementor_active(),
        ];

        // Get kit title if available
        if ($status['kit_id']) {
            $kit = get_post($status['kit_id']);
            $status['kit_title'] = $kit ? $kit->post_title : 'Unknown';
        }

        return $status;
    }

    /**
     * Force sync (clears cache and re-syncs)
     *
     * @return bool|\WP_Error
     */
    public static function force_sync() {
        // Clear Elementor cache first
        if (class_exists('\Elementor\Plugin')) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
        }

        return self::sync();
    }
}
