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

        // Fix menu highlighting for our custom submenu links (high priority to run last)
        add_filter('parent_file', [__CLASS__, 'fix_parent_file'], 999);
        add_filter('submenu_file', [__CLASS__, 'fix_submenu_file'], 999, 2);

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

            // Availability calendar (only when RoomCloud is enabled)
            if (defined('SHAPED_ENABLE_ROOMCLOUD') && SHAPED_ENABLE_ROOMCLOUD) {
                add_submenu_page(
                    'shaped-ops',
                    'Availability',
                    'Availability',
                    'shaped_view_ops',
                    'shaped-ops-availability',
                    [__CLASS__, 'render_ops_availability']
                );
            }

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

            // System submenus - use add_submenu_page with redirects (same pattern as Shaped Ops)
            // Order: Overview, RoomCloud, Config Health, Settings, Plugins, Tools, Updates

            add_submenu_page(
                'shaped-system',
                'System Overview',
                'Overview',
                'manage_options',
                'shaped-system',
                [__CLASS__, 'render_system_dashboard']
            );

            // RoomCloud (2nd place)
            if (defined('SHAPED_ENABLE_ROOMCLOUD') && SHAPED_ENABLE_ROOMCLOUD) {
                add_submenu_page(
                    'shaped-system',
                    'RoomCloud',
                    'RoomCloud',
                    'manage_options',
                    'shaped-system-roomcloud',
                    [__CLASS__, 'redirect_to_roomcloud']
                );
            }

            add_submenu_page(
                'shaped-system',
                'Config Health',
                'Config Health',
                'manage_options',
                'shaped-system-health',
                [__CLASS__, 'render_config_health']
            );

            add_submenu_page(
                'shaped-system',
                'Shortcodes',
                'Shortcodes',
                'manage_options',
                'shaped-system-shortcodes',
                [__CLASS__, 'render_shortcodes_page']
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
    public static function fix_parent_file(?string $parent_file): string {
        global $pagenow, $typenow;

        $page = $_GET['page'] ?? '';

        // Shaped Ops parent highlighting
        if ($page === 'shaped-pricing') {
            return 'shaped-ops';
        }

        // Shaped System parent highlighting
        $system_pages = [
            'shaped-roomcloud',
        ];

        if (in_array($page, $system_pages, true)) {
            return 'shaped-system';
        }

        return $parent_file ?? '';
    }

    /**
     * Fix submenu file highlighting
     */
    public static function fix_submenu_file(?string $submenu_file, ?string $parent_file): ?string {
        global $pagenow, $typenow;

        $page = $_GET['page'] ?? '';

        // Shaped Ops submenu highlighting
        if ($page === 'shaped-pricing') {
            return 'shaped-ops-pricing';
        }

        // Shaped System submenu highlighting - return the registered submenu slug
        if ($page === 'shaped-roomcloud') {
            return 'shaped-system-roomcloud';
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
            'shaped-ops-availability',
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

    public static function redirect_to_pricing(): void {
        wp_safe_redirect(admin_url('admin.php?page=shaped-pricing'));
        exit;
    }

    public static function redirect_to_roomcloud(): void {
        wp_safe_redirect(admin_url('admin.php?page=shaped-roomcloud'));
        exit;
    }

    // ─── Page renderers ───

    public static function render_ops_dashboard(): void {
        require_once SHAPED_DIR . 'admin/pages/ops-dashboard.php';
    }

    public static function render_ops_availability(): void {
        require_once SHAPED_DIR . 'admin/pages/ops-availability.php';
    }

    public static function render_system_dashboard(): void {
        require_once SHAPED_DIR . 'admin/pages/system-dashboard.php';
    }

    public static function render_shortcodes_page(): void {
        require_once SHAPED_DIR . 'admin/pages/shortcodes.php';
    }

    public static function render_config_health(): void {
        require_once SHAPED_DIR . 'admin/pages/config-health.php';
    }
}
