<?php
/**
 * Menu Controller
 * Manages admin menu structure for operators vs administrators
 *
 * Operators see: Shaped Ops, Guest Reviews, Pages, Media, Profile
 * Admins see: Everything (full WordPress admin) + Shaped Ops + Shaped System
 *
 * @package Shaped_Core
 * @subpackage Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Shaped_Menu_Controller {

    /**
     * Pages that operators are allowed to access (top-level menu slugs)
     */
    const OPERATOR_ALLOWLIST = [
        'shaped-ops',                       // Our main ops menu
        'edit.php?post_type=shaped_review', // Guest Reviews (with dashboard)
        'edit.php?post_type=page',          // Pages
        'upload.php',                       // Media
        'profile.php',                      // User profile
    ];

    /**
     * Initialize menu controller
     */
    public static function init(): void {
        // Register menus early
        add_action('admin_menu', [__CLASS__, 'register_menus'], 9);

        // Restructure menus late (after all plugins register theirs)
        add_action('admin_menu', [__CLASS__, 'restructure_menus'], 999);

        // Fix menu highlighting for our custom submenu links
        add_filter('parent_file', [__CLASS__, 'fix_parent_file']);
        add_filter('submenu_file', [__CLASS__, 'fix_submenu_file'], 10, 2);

        // Restrict access for operators
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

            // Ops submenus - use actual page slugs for proper highlighting
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
                'shaped-ops-reservations',
                [__CLASS__, 'redirect_to_reservations']
            );

            add_submenu_page(
                'shaped-ops',
                'Inventory',
                'Inventory',
                'shaped_view_ops',
                'shaped-ops-inventory',
                [__CLASS__, 'redirect_to_inventory']
            );

            add_submenu_page(
                'shaped-ops',
                'Pricing',
                'Pricing',
                'shaped_view_ops',
                'shaped-ops-pricing',
                [__CLASS__, 'redirect_to_pricing']
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
                'shaped-system-settings',
                [__CLASS__, 'redirect_to_settings']
            );

            add_submenu_page(
                'shaped-system',
                'Setup Wizard',
                'Setup Wizard',
                'manage_options',
                'shaped-system-wizard',
                [__CLASS__, 'redirect_to_wizard']
            );

            add_submenu_page(
                'shaped-system',
                'Config Health',
                'Config Health',
                'manage_options',
                'shaped-system-health',
                [__CLASS__, 'redirect_to_health']
            );

            // Integrations
            if (defined('SHAPED_ENABLE_ROOMCLOUD') && SHAPED_ENABLE_ROOMCLOUD) {
                add_submenu_page(
                    'shaped-system',
                    'Integrations',
                    'Integrations',
                    'manage_options',
                    'shaped-system-integrations',
                    [__CLASS__, 'redirect_to_integrations']
                );
            }

            // WordPress core tools
            add_submenu_page(
                'shaped-system',
                'Plugins',
                'Plugins',
                'manage_options',
                'shaped-system-plugins',
                [__CLASS__, 'redirect_to_plugins']
            );

            add_submenu_page(
                'shaped-system',
                'Tools',
                'Tools',
                'manage_options',
                'shaped-system-tools',
                [__CLASS__, 'redirect_to_tools']
            );

            add_submenu_page(
                'shaped-system',
                'Updates',
                'Updates',
                'manage_options',
                'shaped-system-updates',
                [__CLASS__, 'redirect_to_updates']
            );
        }
    }

    /**
     * Restructure menus based on user role
     */
    public static function restructure_menus(): void {
        $is_admin = current_user_can('manage_options');

        if ($is_admin) {
            // For admins: just remove redundant Shaped menus
            self::remove_redundant_admin_menus();
        } else {
            // For operators: allowlist approach
            self::apply_operator_allowlist();
        }
    }

    /**
     * Remove old Shaped menus that are now consolidated under Shaped System
     */
    private static function remove_redundant_admin_menus(): void {
        // Remove old standalone Shaped menus (now accessible via Shaped System)
        remove_menu_page('shaped-settings');
        remove_menu_page('shaped-pricing');
        remove_menu_page('shaped-roomcloud');
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
     * Fix parent file highlighting for pages accessed via Shaped menus
     */
    public static function fix_parent_file(string $parent_file): string {
        global $pagenow, $typenow;

        $page = $_GET['page'] ?? '';

        // Shaped Ops parent highlighting
        if ($pagenow === 'edit.php') {
            if ($typenow === 'mphb_booking') {
                return 'shaped-ops';
            }
            if ($typenow === 'mphb_room_type') {
                return 'shaped-ops';
            }
        }

        if ($page === 'shaped-pricing') {
            return 'shaped-ops';
        }

        // Shaped System parent highlighting
        $system_pages = [
            'shaped-settings',
            'shaped-setup-wizard',
            'shaped-config-health',
            'shaped-roomcloud',
        ];

        if (in_array($page, $system_pages, true)) {
            return 'shaped-system';
        }

        if ($pagenow === 'plugins.php' || $pagenow === 'tools.php' || $pagenow === 'update-core.php') {
            // Only for admins viewing via system menu context
            if (current_user_can('manage_options') && isset($_GET['from']) && $_GET['from'] === 'shaped-system') {
                return 'shaped-system';
            }
        }

        return $parent_file;
    }

    /**
     * Fix submenu file highlighting
     */
    public static function fix_submenu_file(string $submenu_file, string $parent_file): string {
        global $pagenow, $typenow;

        $page = $_GET['page'] ?? '';

        // Shaped Ops submenu highlighting
        if ($parent_file === 'shaped-ops') {
            if ($pagenow === 'edit.php' && $typenow === 'mphb_booking') {
                return 'shaped-ops-reservations';
            }
            if ($pagenow === 'edit.php' && $typenow === 'mphb_room_type') {
                return 'shaped-ops-inventory';
            }
            if ($page === 'shaped-pricing') {
                return 'shaped-ops-pricing';
            }
        }

        // Shaped System submenu highlighting
        if ($parent_file === 'shaped-system') {
            if ($page === 'shaped-settings') {
                return 'shaped-system-settings';
            }
            if ($page === 'shaped-setup-wizard') {
                return 'shaped-system-wizard';
            }
            if ($page === 'shaped-config-health') {
                return 'shaped-system-health';
            }
            if ($page === 'shaped-roomcloud') {
                return 'shaped-system-integrations';
            }
            if ($pagenow === 'plugins.php') {
                return 'shaped-system-plugins';
            }
            if ($pagenow === 'tools.php') {
                return 'shaped-system-tools';
            }
            if ($pagenow === 'update-core.php') {
                return 'shaped-system-updates';
            }
        }

        return $submenu_file;
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
            'index.php',
            'profile.php',
            'upload.php',
            'media-new.php',
            'edit.php',
            'post.php',
            'post-new.php',
            'edit-tags.php',
            'term.php',
            'admin.php',
            'admin-ajax.php',
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
            'shaped-ops-reservations',
            'shaped-ops-inventory',
            'shaped-ops-pricing',
            'shaped-pricing',
            'shaped-reviews-dashboard',
            'shaped-reviews-sync',
        ];

        // Check page access
        $is_allowed = false;

        if (in_array($pagenow, $allowed_pages, true)) {
            if ($pagenow === 'edit.php' || $pagenow === 'post.php' || $pagenow === 'post-new.php') {
                $check_type = $post_type ?: (isset($_GET['post']) ? get_post_type(absint($_GET['post'])) : 'post');
                $is_allowed = in_array($check_type, $allowed_post_types, true);
            } elseif ($pagenow === 'edit-tags.php' || $pagenow === 'term.php') {
                // Allow taxonomy pages for allowed post types
                $taxonomy = $_GET['taxonomy'] ?? '';
                $is_allowed = !empty($taxonomy);
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

    // ─── Redirect callbacks ───

    public static function redirect_to_reservations(): void {
        wp_safe_redirect(admin_url('edit.php?post_type=mphb_booking'));
        exit;
    }

    public static function redirect_to_inventory(): void {
        wp_safe_redirect(admin_url('edit.php?post_type=mphb_room_type'));
        exit;
    }

    public static function redirect_to_pricing(): void {
        wp_safe_redirect(admin_url('admin.php?page=shaped-pricing'));
        exit;
    }

    public static function redirect_to_settings(): void {
        wp_safe_redirect(admin_url('admin.php?page=shaped-settings'));
        exit;
    }

    public static function redirect_to_wizard(): void {
        wp_safe_redirect(admin_url('admin.php?page=shaped-setup-wizard'));
        exit;
    }

    public static function redirect_to_health(): void {
        wp_safe_redirect(admin_url('admin.php?page=shaped-config-health'));
        exit;
    }

    public static function redirect_to_integrations(): void {
        wp_safe_redirect(admin_url('admin.php?page=shaped-roomcloud'));
        exit;
    }

    public static function redirect_to_plugins(): void {
        wp_safe_redirect(admin_url('plugins.php'));
        exit;
    }

    public static function redirect_to_tools(): void {
        wp_safe_redirect(admin_url('tools.php'));
        exit;
    }

    public static function redirect_to_updates(): void {
        wp_safe_redirect(admin_url('update-core.php'));
        exit;
    }

    // ─── Page renderers ───

    public static function render_ops_dashboard(): void {
        require_once SHAPED_DIR . 'admin/pages/ops-dashboard.php';
    }

    public static function render_system_dashboard(): void {
        require_once SHAPED_DIR . 'admin/pages/system-dashboard.php';
    }
}
