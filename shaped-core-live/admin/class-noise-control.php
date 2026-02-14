<?php
/**
 * Noise Control
 * Hides update notices, nags, and unnecessary UI elements for operators
 *
 * @package Shaped_Core
 * @subpackage Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Shaped_Noise_Control {

    /**
     * Initialize noise control
     */
    public static function init(): void {
        add_action('admin_init', [__CLASS__, 'remove_operator_noise']);
        add_action('admin_head', [__CLASS__, 'hide_operator_ui_elements']);
        add_filter('admin_footer_text', [__CLASS__, 'custom_footer_text']);
        add_filter('update_footer', [__CLASS__, 'custom_version_text'], 20);
    }

    /**
     * Remove noise for operators (non-admins)
     */
    public static function remove_operator_noise(): void {
        // Only apply to operators
        if (current_user_can('manage_options')) {
            return;
        }

        // Remove update nags
        remove_action('admin_notices', 'update_nag', 3);
        remove_action('admin_notices', 'maintenance_nag', 10);

        // Remove "Welcome" panel
        remove_action('welcome_panel', 'wp_welcome_panel');

        // Remove admin bar items
        add_action('admin_bar_menu', [__CLASS__, 'clean_admin_bar'], 999);

        // Remove dashboard widgets
        add_action('wp_dashboard_setup', [__CLASS__, 'remove_dashboard_widgets'], 999);

        // Hide plugin/theme update notices
        remove_action('load-update-core.php', 'wp_update_plugins');
        remove_action('load-update-core.php', 'wp_update_themes');

        // Remove "At a Glance" updates info
        add_filter('pre_site_transient_update_core', '__return_null');
        add_filter('pre_site_transient_update_plugins', '__return_null');
        add_filter('pre_site_transient_update_themes', '__return_null');
    }

    /**
     * Clean admin bar for operators
     */
    public static function clean_admin_bar(\WP_Admin_Bar $admin_bar): void {
        // Remove items that operators don't need
        $admin_bar->remove_node('updates');
        $admin_bar->remove_node('comments');
        $admin_bar->remove_node('new-content');
        $admin_bar->remove_node('wp-logo');
        $admin_bar->remove_node('customize');
        $admin_bar->remove_node('search');
    }

    /**
     * Remove dashboard widgets for operators
     */
    public static function remove_dashboard_widgets(): void {
        global $wp_meta_boxes;

        // Remove all default dashboard widgets
        $widgets_to_remove = [
            'dashboard_primary',           // WordPress Events and News
            'dashboard_secondary',
            'dashboard_quick_press',       // Quick Draft
            'dashboard_recent_drafts',
            'dashboard_incoming_links',
            'dashboard_plugins',
            'dashboard_recent_comments',
            'dashboard_right_now',         // At a Glance (partially)
            'dashboard_activity',          // Activity
            'dashboard_site_health',       // Site Health
        ];

        foreach ($widgets_to_remove as $widget) {
            remove_meta_box($widget, 'dashboard', 'normal');
            remove_meta_box($widget, 'dashboard', 'side');
        }
    }

    /**
     * Hide UI elements via CSS for operators
     */
    public static function hide_operator_ui_elements(): void {
        if (current_user_can('manage_options')) {
            return;
        }

        ?>
        <style>
            /* Hide update/notice noise */
            .update-nag,
            .notice-warning.notice-alt,
            .try-gutenberg-panel,
            #wp-admin-bar-updates,
            #wp-admin-bar-comments,
            .plugin-update-tr,
            .update-message {
                display: none !important;
            }

            /* Hide screen options and help that might confuse operators */
            #screen-options-link-wrap {
                display: none;
            }

            /* Clean up dashboard */
            #dashboard-widgets .postbox-container:empty {
                display: none;
            }

            /* Hide footer links */
            #wpfooter #footer-left {
                display: none;
            }
        </style>
        <?php
    }

    /**
     * Custom footer text for all users
     */
    public static function custom_footer_text(string $text): string {
        if (!current_user_can('manage_options')) {
            return 'Powered by Shaped Systems';
        }
        return $text;
    }

    /**
     * Custom version text (hide WordPress version for operators)
     */
    public static function custom_version_text(string $text): string {
        if (!current_user_can('manage_options')) {
            return '';
        }
        return $text;
    }
}
