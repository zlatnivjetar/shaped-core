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
        add_action('wp_ajax_shaped_generate_dashboard_api_key', [__CLASS__, 'ajax_generate_dashboard_api_key']);
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
        $dashboard_api_key_configured = class_exists('Shaped_Dashboard_Api')
            && Shaped_Dashboard_Api::has_configured_api_key();
        $dashboard_api_key_nonce = wp_create_nonce('shaped_dashboard_api_key');

        ?>
        <div class="wrap">
            <h1>Shaped Core Settings</h1>
            <p class="description">Configure modal pages and dashboard API access for your booking system.</p>

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
                <h3>Dashboard API Key</h3>
                <p>
                    Generate a key for the external Shaped Dashboard. The generated value is not stored in WordPress,
                    so copy it immediately and place it in <code>wp-config.php</code>.
                </p>
                <p>
                    <strong>Status:</strong>
                    <?php echo $dashboard_api_key_configured ? 'Configured in wp-config.php' : 'Not configured in wp-config.php'; ?>
                </p>
                <p>
                    <button
                        type="button"
                        class="button button-primary"
                        id="shaped-generate-dashboard-api-key"
                        data-nonce="<?php echo esc_attr($dashboard_api_key_nonce); ?>"
                    >
                        Generate Dashboard API Key
                    </button>
                </p>
                <div id="shaped-dashboard-api-key-result" class="shaped-dashboard-api-result" hidden>
                    <p class="description" style="margin-top: 0;">
                        Copy this value now. It will only be shown for this browser session.
                    </p>
                    <input
                        type="text"
                        id="shaped-dashboard-api-key-value"
                        class="regular-text code"
                        readonly
                        value=""
                        style="width: 100%; max-width: 520px;"
                    >
                    <p class="description" style="margin-top: 12px;">Add this line to <code>wp-config.php</code>:</p>
                    <pre id="shaped-dashboard-api-key-config-line"></pre>
                </div>
            </div>

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
                    white-space: pre-wrap;
                    word-break: break-all;
                }
                .shaped-dashboard-api-result {
                    margin-top: 16px;
                    padding: 16px;
                    background: #fff;
                    border: 1px solid #c3c4c7;
                    border-radius: 4px;
                }
            </style>

            <script>
                jQuery(function($) {
                    $('#shaped-generate-dashboard-api-key').on('click', function() {
                        var $button = $(this);
                        var $result = $('#shaped-dashboard-api-key-result');
                        var $value = $('#shaped-dashboard-api-key-value');
                        var $configLine = $('#shaped-dashboard-api-key-config-line');

                        $button.prop('disabled', true).text('Generating...');

                        $.post(ajaxurl, {
                            action: 'shaped_generate_dashboard_api_key',
                            nonce: $button.data('nonce')
                        }).done(function(response) {
                            if (!response || !response.success) {
                                var errorMessage = response && response.data && response.data.message
                                    ? response.data.message
                                    : 'Could not generate a dashboard API key.';
                                window.alert(errorMessage);
                                return;
                            }

                            $value.val(response.data.api_key).trigger('focus').trigger('select');
                            $configLine.text(response.data.wp_config_line);
                            $result.prop('hidden', false);
                        }).fail(function() {
                            window.alert('Could not generate a dashboard API key.');
                        }).always(function() {
                            $button.prop('disabled', false).text('Generate Dashboard API Key');
                        });
                    });
                });
            </script>
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

    /**
     * AJAX handler for generating a dashboard API key.
     */
    public static function ajax_generate_dashboard_api_key(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions'], 403);
        }

        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'shaped_dashboard_api_key')) {
            wp_send_json_error(['message' => 'Invalid nonce'], 403);
        }

        if (!class_exists('Shaped_Dashboard_Api')) {
            wp_send_json_error(['message' => 'Dashboard API bootstrap is unavailable'], 500);
        }

        $api_key = Shaped_Dashboard_Api::generate_api_key();

        wp_send_json_success([
            'api_key'        => $api_key,
            'wp_config_line' => "define( 'SHAPED_DASHBOARD_API_KEY', '" . $api_key . "' );",
        ]);
    }
}
