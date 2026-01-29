<?php
/**
 * Review Strip Settings Page
 * Adds a settings page under Guest Reviews menu for configuring review strip ratings
 *
 * @package Shaped_Core
 * @subpackage Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Shaped_Review_Strip_Settings {

    /**
     * Option name for storing ratings
     */
    const OPTION_NAME = 'shaped_review_strip_ratings';

    /**
     * Default ratings per provider
     */
    const DEFAULT_RATINGS = [
        'booking'     => ['rating' => 9.0, 'enabled' => true],
        'expedia'     => ['rating' => 9.4, 'enabled' => true],
        'google'      => ['rating' => 4.6, 'enabled' => true],
        'tripadvisor' => ['rating' => 4.5, 'enabled' => false],
        'airbnb'      => ['rating' => 4.8, 'enabled' => false],
        'direct'      => ['rating' => 9.5, 'enabled' => false],
    ];

    /**
     * Initialize
     */
    public static function init(): void {
        // Add settings page as submenu under Guest Reviews
        add_action('admin_menu', [__CLASS__, 'register_settings_page'], 20);

        // Register settings
        add_action('admin_init', [__CLASS__, 'register_settings']);

        // Fix menu highlighting
        add_filter('parent_file', [__CLASS__, 'fix_parent_file'], 998);
        add_filter('submenu_file', [__CLASS__, 'fix_submenu_file'], 998, 2);

        // Enqueue admin styles
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
    }

    /**
     * Register the settings page as submenu under Guest Reviews
     */
    public static function register_settings_page(): void {
        $post_type = 'shaped_review';
        $parent_slug = 'edit.php?post_type=' . $post_type;

        add_submenu_page(
            $parent_slug,
            'Review Strip Settings',
            'Review Strip',
            'manage_options',
            'shaped-review-strip-settings',
            [__CLASS__, 'render']
        );
    }

    /**
     * Register settings
     */
    public static function register_settings(): void {
        register_setting(
            'shaped_review_strip_settings',
            self::OPTION_NAME,
            [
                'type' => 'array',
                'sanitize_callback' => [__CLASS__, 'sanitize_ratings'],
                'default' => self::DEFAULT_RATINGS,
            ]
        );
    }

    /**
     * Sanitize ratings input
     */
    public static function sanitize_ratings($input): array {
        if (!is_array($input)) {
            return self::DEFAULT_RATINGS;
        }

        $provider_configs = self::get_provider_configs();
        $sanitized = [];

        foreach ($provider_configs as $key => $config) {
            $max_rating = $config['scale'];

            $sanitized[$key] = [
                'rating' => isset($input[$key]['rating'])
                    ? min(max(floatval($input[$key]['rating']), 0), $max_rating)
                    : self::DEFAULT_RATINGS[$key]['rating'],
                'enabled' => !empty($input[$key]['enabled']),
            ];
        }

        return $sanitized;
    }

    /**
     * Get provider configurations (duplicated from shortcodes for admin context)
     */
    public static function get_provider_configs(): array {
        return [
            'booking'     => ['name' => 'Booking', 'bg' => '#003580', 'text' => '#ffffff', 'scale' => 10],
            'expedia'     => ['name' => 'Expedia', 'bg' => '#ffda00', 'text' => '#000000', 'scale' => 10],
            'tripadvisor' => ['name' => 'TripAdvisor', 'bg' => '#00af87', 'text' => '#ffffff', 'scale' => 5],
            'google'      => ['name' => 'Google', 'bg' => '#4285f4', 'text' => '#ffffff', 'scale' => 5],
            'airbnb'      => ['name' => 'Airbnb', 'bg' => '#ff385c', 'text' => '#ffffff', 'scale' => 5],
            'direct'      => ['name' => 'Direct', 'bg' => '#6366f1', 'text' => '#ffffff', 'scale' => 10],
        ];
    }

    /**
     * Get saved ratings merged with defaults
     */
    public static function get_ratings(): array {
        $saved = get_option(self::OPTION_NAME, []);
        return wp_parse_args($saved, self::DEFAULT_RATINGS);
    }

    /**
     * Get rating for a specific provider
     */
    public static function get_provider_rating(string $provider): ?array {
        $ratings = self::get_ratings();
        return $ratings[$provider] ?? null;
    }

    /**
     * Fix parent file highlighting for settings page
     */
    public static function fix_parent_file(?string $parent_file): string {
        $page = $_GET['page'] ?? '';

        if ($page === 'shaped-review-strip-settings') {
            return 'edit.php?post_type=shaped_review';
        }

        return $parent_file ?? '';
    }

    /**
     * Fix submenu file highlighting for settings page
     */
    public static function fix_submenu_file(?string $submenu_file, ?string $parent_file): ?string {
        $page = $_GET['page'] ?? '';

        if ($page === 'shaped-review-strip-settings') {
            return 'shaped-review-strip-settings';
        }

        return $submenu_file;
    }

    /**
     * Enqueue admin assets
     */
    public static function enqueue_admin_assets(string $hook): void {
        if ($hook !== 'guest-reviews_page_shaped-review-strip-settings') {
            return;
        }

        wp_enqueue_style(
            'shaped-review-strip-admin',
            SHAPED_URL . 'assets/css/admin/review-strip-settings.css',
            [],
            SHAPED_VERSION
        );
    }

    /**
     * Render the settings page
     */
    public static function render(): void {
        require_once SHAPED_DIR . 'admin/pages/review-strip-settings.php';
    }
}
