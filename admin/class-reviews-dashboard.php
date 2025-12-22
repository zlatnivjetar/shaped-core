<?php
/**
 * Reviews Dashboard Page
 * Adds a dashboard as the first item under Guest Reviews menu
 *
 * @package Shaped_Core
 * @subpackage Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Shaped_Reviews_Dashboard {

    /**
     * Initialize
     */
    public static function init(): void {
        // Add dashboard as submenu under Guest Reviews, run after CPT registers (priority 15)
        add_action('admin_menu', [__CLASS__, 'register_dashboard'], 15);

        // Reorder submenus to put Dashboard first
        add_action('admin_menu', [__CLASS__, 'reorder_submenu'], 999);

        // Fix menu highlighting when on dashboard (priority 998, before Menu Controller's 999)
        add_filter('parent_file', [__CLASS__, 'fix_parent_file'], 998);
        add_filter('submenu_file', [__CLASS__, 'fix_submenu_file'], 998, 2);
    }

    /**
     * Register the dashboard as submenu under Guest Reviews
     */
    public static function register_dashboard(): void {
        $post_type = 'shaped_review';
        $parent_slug = 'edit.php?post_type=' . $post_type;

        add_submenu_page(
            $parent_slug,
            'Reviews Dashboard',
            'Dashboard',
            'shaped_view_ops',
            'shaped-reviews-dashboard',
            [__CLASS__, 'render']
        );
    }

    /**
     * Reorder submenu to put Dashboard first
     */
    public static function reorder_submenu(): void {
        global $submenu;

        $parent_slug = 'edit.php?post_type=shaped_review';

        if (!isset($submenu[$parent_slug])) {
            return;
        }

        $dashboard_item = null;
        $dashboard_key = null;

        // Find the dashboard item
        foreach ($submenu[$parent_slug] as $key => $item) {
            if (isset($item[2]) && $item[2] === 'shaped-reviews-dashboard') {
                $dashboard_item = $item;
                $dashboard_key = $key;
                break;
            }
        }

        if ($dashboard_item === null) {
            return;
        }

        // Remove from current position
        unset($submenu[$parent_slug][$dashboard_key]);

        // Prepend to beginning
        array_unshift($submenu[$parent_slug], $dashboard_item);

        // Re-index the array
        $submenu[$parent_slug] = array_values($submenu[$parent_slug]);
    }

    /**
     * Fix parent file highlighting for dashboard
     */
    public static function fix_parent_file(?string $parent_file): string {
        $page = $_GET['page'] ?? '';

        if ($page === 'shaped-reviews-dashboard') {
            return 'edit.php?post_type=shaped_review';
        }

        return $parent_file ?? '';
    }

    /**
     * Fix submenu file highlighting for dashboard
     */
    public static function fix_submenu_file(?string $submenu_file, ?string $parent_file): ?string {
        $page = $_GET['page'] ?? '';

        if ($page === 'shaped-reviews-dashboard') {
            return 'shaped-reviews-dashboard';
        }

        return $submenu_file;
    }

    /**
     * Render the dashboard
     */
    public static function render(): void {
        require_once SHAPED_DIR . 'admin/pages/reviews-dashboard.php';
    }
}
