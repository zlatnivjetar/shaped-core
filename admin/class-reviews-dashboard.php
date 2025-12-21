<?php
/**
 * Reviews Dashboard Page
 * Registers and renders the reviews dashboard
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
        add_action('admin_menu', [__CLASS__, 'register_page'], 20);
    }

    /**
     * Register the dashboard page
     */
    public static function register_page(): void {
        add_submenu_page(
            null, // Hidden from menu (we add it via Menu Controller)
            'Reviews Dashboard',
            'Reviews Dashboard',
            'shaped_view_ops',
            'shaped-reviews-dashboard',
            [__CLASS__, 'render']
        );
    }

    /**
     * Render the dashboard
     */
    public static function render(): void {
        require_once SHAPED_DIR . 'admin/pages/reviews-dashboard.php';
    }
}
