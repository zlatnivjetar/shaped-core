<?php
/**
 * Admin Settings & Configuration
 * Main settings page + modal page selectors
 *
 * @package Shaped_Core
 */

if (!defined('ABSPATH')) {
    exit;
}

class Shaped_Admin {

    /**
     * Option keys
     */
    const OPT_MODAL_PAGES = 'shaped_modal_pages';

    /**
     * Initialize admin functionality
     */
    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'add_admin_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);

        // AJAX handler for loading modal content
        add_action('wp_ajax_shaped_load_modal_content', [__CLASS__, 'ajax_load_modal_content']);
        add_action('wp_ajax_nopriv_shaped_load_modal_content', [__CLASS__, 'ajax_load_modal_content']);

        // AJAX handler for feature flags
        add_action('wp_ajax_shaped_save_feature_flags', [__CLASS__, 'ajax_save_feature_flags']);
    }

    /**
     * Add admin menu
     */
    public static function add_admin_menu(): void {
        add_menu_page(
            'Shaped Settings',
            'Shaped Core',
            'manage_options',
            'shaped-settings',
            [__CLASS__, 'render_settings_page'],
            'dashicons-admin-generic',
            25
        );
    }

    /**
     * Register settings
     */
    public static function register_settings(): void {
        register_setting('shaped_settings_group', self::OPT_MODAL_PAGES, [
            'type'              => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize_modal_pages'],
            'default'           => [],
        ]);
    }

    /**
     * Sanitize modal pages input
     */
    public static function sanitize_modal_pages($input): array {
        if (!is_array($input)) {
            return [];
        }

        $output = [];
        foreach ($input as $key => $page_id) {
            $key = sanitize_key($key);
            $output[$key] = absint($page_id);
        }

        return $output;
    }

    /**
     * Render settings page
     */
    public static function render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $modal_pages = get_option(self::OPT_MODAL_PAGES, []);
        $modal_types = self::get_modal_types();

        ?>
        <div class="wrap">
            <h1>Shaped Core Settings</h1>
            <p class="description">Configure modal pages for your booking system.</p>

            <form method="post" action="options.php">
                <?php settings_fields('shaped_settings_group'); ?>

                <table class="form-table">
                    <tbody>
                        <?php foreach ($modal_types as $key => $label):
                            $page_id = isset($modal_pages[$key]) ? intval($modal_pages[$key]) : 0;
                        ?>
                        <tr>
                            <th scope="row">
                                <label for="shaped_modal_<?php echo esc_attr($key); ?>">
                                    <?php echo esc_html($label); ?>
                                </label>
                            </th>
                            <td>
                                <?php
                                wp_dropdown_pages([
                                    'name'              => self::OPT_MODAL_PAGES . '[' . esc_attr($key) . ']',
                                    'id'                => 'shaped_modal_' . esc_attr($key),
                                    'selected'          => $page_id,
                                    'show_option_none'  => '— Select Page —',
                                    'option_none_value' => '0',
                                ]);
                                ?>
                                <p class="description">
                                    Page to display in modal for <?php echo esc_html(strtolower($label)); ?>
                                </p>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php submit_button('Save Settings'); ?>
            </form>

            <div class="shaped-info-box">
                <h3>How to Use Modal Links</h3>
                <p>
                    Use the shortcode to create links that open these pages in a modal:
                </p>
                <p><strong>Examples:</strong></p>
                <pre><code>[shaped_modal page="booking-terms" label="Booking Terms"]</code></pre>
                <pre><code>[shaped_modal page="privacy" label="Privacy Policy"]</code></pre>
            </div>

            <style>
                .shaped-info-box {
                    background: #f0f6fc;
                    border-left: 4px solid #0073aa;
                    padding: 20px;
                    margin-top: 30px;
                }
                .shaped-info-box h3 {
                    margin-top: 0;
                    margin-bottom: 10px;
                }
                .shaped-info-box code {
                    background: #fff;
                    padding: 3px 8px;
                    border-radius: 3px;
                    font-family: monospace;
                }
                .shaped-info-box pre {
                    background: #fff;
                    padding: 10px;
                    border-radius: 3px;
                    margin: 5px 0;
                }
            </style>
        </div>
        <?php
    }

    /**
     * Get available modal types
     */
    public static function get_modal_types(): array {
        return apply_filters('shaped/admin/modal_types', [
            'booking-terms' => 'Booking Terms',
            'privacy'       => 'Privacy Policy',
        ]);
    }

    /**
     * Get modal page ID by type
     * Hardcoded to look up pages by title
     */
    public static function get_modal_page(string $key): int {
        $page_titles = [
            'booking-terms' => 'Terms and Conditions',
            'privacy'       => 'Privacy Policy',
        ];

        if (!isset($page_titles[$key])) {
            return 0;
        }

        $page = get_page_by_title($page_titles[$key], OBJECT, 'page');

        if (!$page) {
            // Fallback: try WP_Query for better compatibility
            $query = new WP_Query([
                'post_type'      => 'page',
                'title'          => $page_titles[$key],
                'post_status'    => 'publish',
                'posts_per_page' => 1,
            ]);

            if ($query->have_posts()) {
                return $query->posts[0]->ID;
            }

            return 0;
        }

        return $page->ID;
    }

    /**
     * AJAX handler for loading modal content
     */
    public static function ajax_load_modal_content(): void {
        // Optional nonce verification (commented out for now, can be enabled if needed)
        // if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'shaped_ajax')) {
        //     wp_send_json_error(['message' => 'Invalid nonce']);
        //     return;
        // }

        $page_id = isset($_POST['page_id']) ? absint($_POST['page_id']) : 0;

        if (!$page_id) {
            wp_send_json_error(['message' => 'Invalid page ID']);
            return;
        }

        $page = get_post($page_id);

        if (!$page || $page->post_status !== 'publish') {
            wp_send_json_error(['message' => 'Page not found']);
            return;
        }

        // Get page content
        $content = apply_filters('the_content', $page->post_content);

        wp_send_json_success([
            'content' => $content,
            'title'   => get_the_title($page_id),
        ]);
    }

    /**
     * AJAX handler for saving feature flags
     */
    public static function ajax_save_feature_flags(): void {
        // Verify user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
            return;
        }

        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'shaped_feature_flags')) {
            wp_send_json_error(['message' => 'Invalid nonce']);
            return;
        }

        // Get values from POST
        $roomcloud = isset($_POST['roomcloud']) ? filter_var($_POST['roomcloud'], FILTER_VALIDATE_BOOLEAN) : false;
        $reviews = isset($_POST['reviews']) ? filter_var($_POST['reviews'], FILTER_VALIDATE_BOOLEAN) : false;

        // Save to database
        update_option('shaped_enable_roomcloud', $roomcloud);
        update_option('shaped_enable_reviews', $reviews);

        wp_send_json_success([
            'message'   => 'Feature flags saved successfully. Please refresh the page for changes to take effect.',
            'roomcloud' => $roomcloud,
            'reviews'   => $reviews,
        ]);
    }
}
