<?php
/**
 * Menu Controller
 * Manages admin menu structure for operators vs administrators
 *
 * Operators see: Shaped Ops, Pages, Media, Profile
 * Admins see: Shaped Ops + Shaped System (with all tools)
 *
 * @package Shaped_Core
 * @subpackage Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Shaped_Menu_Controller {

    /**
     * Pages that operators are allowed to access
     */
    const OPERATOR_ALLOWLIST = [
        'shaped-ops',           // Our main ops menu
        'edit.php?post_type=page',  // Pages
        'upload.php',           // Media
        'profile.php',          // User profile
    ];

    /**
     * Initialize menu controller
     */
    public static function init(): void {
        // Run late to capture all registered menus
        add_action('admin_menu', [__CLASS__, 'register_menus'], 9);
        add_action('admin_menu', [__CLASS__, 'restructure_menus'], 999);

        // Redirect unauthorized access
        add_action('admin_init', [__CLASS__, 'restrict_admin_access']);
    }

    /**
     * Register our main menus
     */
    public static function register_menus(): void {
        $is_admin = current_user_can('manage_options');
        $can_ops = Shaped_Role_Manager::can_access_ops();

        // Shaped Ops - visible to operators and admins
        if ($can_ops) {
            add_menu_page(
                'Shaped Ops',
                'Shaped Ops',
                'shaped_view_ops',
                'shaped-ops',
                [__CLASS__, 'render_ops_dashboard'],
                'dashicons-store',
                2
            );

            // Ops submenus
            add_submenu_page(
                'shaped-ops',
                'Overview',
                'Overview',
                'shaped_view_ops',
                'shaped-ops',
                [__CLASS__, 'render_ops_dashboard']
            );

            add_submenu_page(
                'shaped-ops',
                'Reservations',
                'Reservations',
                'shaped_view_ops',
                'edit.php?post_type=mphb_booking'
            );

            add_submenu_page(
                'shaped-ops',
                'Inventory',
                'Inventory',
                'shaped_view_ops',
                'edit.php?post_type=mphb_room_type'
            );

            add_submenu_page(
                'shaped-ops',
                'Pricing',
                'Pricing',
                'shaped_view_ops',
                'admin.php?page=shaped-pricing'
            );

            add_submenu_page(
                'shaped-ops',
                'Reviews',
                'Reviews',
                'shaped_view_ops',
                'admin.php?page=shaped-reviews-dashboard'
            );
        }

        // Shaped System - admin only
        if ($is_admin) {
            add_menu_page(
                'Shaped System',
                'Shaped System',
                'manage_options',
                'shaped-system',
                [__CLASS__, 'render_system_dashboard'],
                'dashicons-admin-settings',
                3
            );

            // System submenus
            add_submenu_page(
                'shaped-system',
                'System Overview',
                'Overview',
                'manage_options',
                'shaped-system',
                [__CLASS__, 'render_system_dashboard']
            );

            add_submenu_page(
                'shaped-system',
                'Settings',
                'Settings',
                'manage_options',
                'admin.php?page=shaped-settings'
            );

            add_submenu_page(
                'shaped-system',
                'Setup Wizard',
                'Setup Wizard',
                'manage_options',
                'admin.php?page=shaped-setup-wizard'
            );

            add_submenu_page(
                'shaped-system',
                'Config Health',
                'Config Health',
                'manage_options',
                'admin.php?page=shaped-config-health'
            );

            // Integrations section
            if (defined('SHAPED_ENABLE_ROOMCLOUD') && SHAPED_ENABLE_ROOMCLOUD) {
                add_submenu_page(
                    'shaped-system',
                    'RoomCloud',
                    'Integrations',
                    'manage_options',
                    'admin.php?page=shaped-roomcloud'
                );
            }

            // Third-party tools - only add if they exist
            self::add_third_party_links();

            // WordPress core tools
            add_submenu_page(
                'shaped-system',
                'Plugins',
                'Plugins',
                'manage_options',
                'plugins.php'
            );

            add_submenu_page(
                'shaped-system',
                'Tools',
                'Tools',
                'manage_options',
                'tools.php'
            );

            add_submenu_page(
                'shaped-system',
                'Updates',
                'Updates',
                'manage_options',
                'update-core.php'
            );
        }
    }

    /**
     * Add third-party plugin links to System menu
     */
    private static function add_third_party_links(): void {
        global $menu;

        // Map of plugin slugs to labels
        $third_party = [
            'rank-math'       => 'SEO',
            'complianz'       => 'GDPR',
            'wp-mail-smtp'    => 'Email',
            'litespeed'       => 'Cache',
        ];

        foreach ($third_party as $slug => $label) {
            // Check if the plugin's menu page exists
            if (self::menu_page_exists($slug)) {
                add_submenu_page(
                    'shaped-system',
                    $label,
                    $label,
                    'manage_options',
                    'admin.php?page=' . $slug
                );
            }
        }
    }

    /**
     * Check if a menu page exists
     */
    private static function menu_page_exists(string $slug): bool {
        global $submenu, $menu;

        // Check top-level menus
        if ($menu) {
            foreach ($menu as $item) {
                if (isset($item[2]) && $item[2] === $slug) {
                    return true;
                }
            }
        }

        // Check submenus
        if ($submenu) {
            foreach ($submenu as $parent => $items) {
                foreach ($items as $item) {
                    if (isset($item[2]) && $item[2] === $slug) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Restructure menus based on user role
     */
    public static function restructure_menus(): void {
        global $menu, $submenu;

        $is_admin = current_user_can('manage_options');

        if ($is_admin) {
            // For admins: remove old top-level Shaped menus (they're now in Shaped System)
            self::remove_redundant_admin_menus();
        } else {
            // For operators: allowlist approach - remove everything not allowed
            self::apply_operator_allowlist();
        }
    }

    /**
     * Remove old Shaped menus that are now consolidated under Shaped System
     */
    private static function remove_redundant_admin_menus(): void {
        // Remove old standalone menus (now in Shaped System submenu)
        remove_menu_page('shaped-settings');       // Old "Shaped Core"
        remove_menu_page('shaped-pricing');        // Old "Shaped Pricing"
        remove_menu_page('shaped-roomcloud');      // Old "RoomCloud" standalone

        // Also remove third-party tools from top level (they're in System now)
        remove_menu_page('rank-math');
        remove_menu_page('complianz');
        remove_menu_page('wp-mail-smtp');
        remove_menu_page('litespeed');
    }

    /**
     * Apply allowlist for operators - remove everything not explicitly allowed
     */
    private static function apply_operator_allowlist(): void {
        global $menu;

        if (!$menu) {
            return;
        }

        $allowlist = self::OPERATOR_ALLOWLIST;

        foreach ($menu as $key => $item) {
            $slug = $item[2] ?? '';

            if (!$slug) {
                continue;
            }

            // Keep allowed items
            if (in_array($slug, $allowlist, true)) {
                continue;
            }

            // Remove everything else
            remove_menu_page($slug);
        }

        // Remove specific submenus that shouldn't be visible
        remove_submenu_page('index.php', 'update-core.php');
    }

    /**
     * Restrict admin page access for operators
     */
    public static function restrict_admin_access(): void {
        // Only restrict for non-admins
        if (current_user_can('manage_options')) {
            return;
        }

        // If not logged in or can't access ops, don't process
        if (!Shaped_Role_Manager::can_access_ops()) {
            return;
        }

        // Get current admin page
        global $pagenow;
        $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
        $post_type = isset($_GET['post_type']) ? sanitize_text_field($_GET['post_type']) : '';

        // Allowed pagenow values
        $allowed_pages = [
            'index.php',        // Dashboard
            'profile.php',      // Profile
            'upload.php',       // Media
            'media-new.php',    // Upload media
            'edit.php',         // Posts/Pages list
            'post.php',         // Edit post
            'post-new.php',     // New post
            'admin.php',        // Admin pages (checked via page param)
            'admin-ajax.php',   // AJAX
        ];

        // Allowed post types
        $allowed_post_types = [
            'page',
            'mphb_booking',
            'mphb_room_type',
            'mphb_room',
            'mphb_rate',
            'mphb_season',
            'mphb_room_service',
            'shaped_review',
        ];

        // Allowed admin.php pages
        $allowed_admin_pages = [
            'shaped-ops',
            'shaped-pricing',
            'shaped-reviews-dashboard',
            'shaped-reviews-sync',
        ];

        // Check page access
        $is_allowed = false;

        if (in_array($pagenow, $allowed_pages, true)) {
            // Further check for post types
            if ($pagenow === 'edit.php' || $pagenow === 'post.php' || $pagenow === 'post-new.php') {
                $check_type = $post_type ?: (isset($_GET['post']) ? get_post_type(absint($_GET['post'])) : 'post');
                $is_allowed = in_array($check_type, $allowed_post_types, true);
            } elseif ($pagenow === 'admin.php') {
                $is_allowed = in_array($page, $allowed_admin_pages, true);
            } else {
                $is_allowed = true;
            }
        }

        if (!$is_allowed && $pagenow !== 'admin-ajax.php') {
            wp_safe_redirect(admin_url('admin.php?page=shaped-ops'));
            exit;
        }
    }

    /**
     * Render Ops Dashboard
     */
    public static function render_ops_dashboard(): void {
        // Load dashboard page
        require_once SHAPED_DIR . 'admin/pages/ops-dashboard.php';
    }

    /**
     * Render System Dashboard
     */
    public static function render_system_dashboard(): void {
        // Load system dashboard page
        require_once SHAPED_DIR . 'admin/pages/system-dashboard.php';
    }
}
