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

            <h2 class="nav-tab-wrapper">
                <a href="#modal-pages" class="nav-tab nav-tab-active">Modal Pages</a>
                <a href="<?php echo admin_url('admin.php?page=shaped-pricing'); ?>" class="nav-tab">Pricing</a>
                <?php if (SHAPED_ENABLE_ROOMCLOUD): ?>
                <a href="<?php echo admin_url('admin.php?page=shaped-roomcloud'); ?>" class="nav-tab">RoomCloud</a>
                <?php endif; ?>
            </h2>

            <div id="modal-pages" class="tab-content">
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
                                        Page to display in modal for "<?php echo esc_html($key); ?>"
                                    </p>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php submit_button('Save Settings'); ?>
                </form>

                <div class="shaped-info-box">
                    <h3>Modal Pages</h3>
                    <p>
                        Configure which pages should be displayed in modals when using the
                        <code>[shaped_modal page="key" label="Link Text"]</code> shortcode.
                    </p>
                    <p><strong>Example usage:</strong></p>
                    <code>[shaped_modal page="terms" label="Terms & Conditions"]</code>
                </div>
            </div>

            <style>
                .nav-tab-wrapper { margin-bottom: 20px; }
                .tab-content { background: #fff; padding: 20px; border: 1px solid #ccd0d4; }
                .shaped-info-box {
                    background: #f0f6fc;
                    border-left: 4px solid #0073aa;
                    padding: 15px;
                    margin-top: 30px;
                }
                .shaped-info-box h3 { margin-top: 0; }
                .shaped-info-box code {
                    background: #fff;
                    padding: 2px 6px;
                    border-radius: 3px;
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
            'terms'       => 'Terms & Conditions',
            'privacy'     => 'Privacy Policy',
            'cancellation'=> 'Cancellation Policy',
            'contact'     => 'Contact Information',
        ]);
    }

    /**
     * Get modal page ID
     */
    public static function get_modal_page(string $key): int {
        $modal_pages = get_option(self::OPT_MODAL_PAGES, []);
        return isset($modal_pages[$key]) ? absint($modal_pages[$key]) : 0;
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
}
