<?php
/**
 * Amenity Icon Admin Page
 *
 * Provides an admin interface under Shaped Core for managing amenity icons.
 *
 * @package Shaped
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Shaped_Amenity_Admin
 */
class Shaped_Amenity_Admin {

    /**
     * Custom field names for taxonomy term meta
     */
    private const CUSTOM_FIELD_ICON = '_shaped_amenity_icon';
    private const CUSTOM_FIELD_WEIGHT = '_shaped_amenity_icon_weight';

    /**
     * Available icon weights
     */
    private const ICON_WEIGHTS = [
        'regular' => 'Regular',
        'bold' => 'Bold',
        'light' => 'Light',
        'thin' => 'Thin',
        'duotone' => 'Duotone',
        'fill' => 'Fill'
    ];

    /**
     * Initialize admin hooks
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_page']);
        add_action('admin_post_shaped_save_amenity_icons', [$this, 'handle_save']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    /**
     * Add admin submenu page under Shaped Core
     */
    public function add_admin_page(): void {
        // Add under Shaped Core menu
        add_submenu_page(
            'shaped-settings',       // Parent slug (Shaped Core)
            'Amenity Icons',         // Page title
            'Amenity Icons',         // Menu title
            'manage_options',        // Capability
            'shaped-amenity-icons',  // Menu slug
            [$this, 'render_admin_page'] // Callback
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook): void {
        // Only load on our amenity icons page
        if ($hook !== 'shaped-core_page_shaped-amenity-icons') {
            return;
        }

        // Enqueue Phosphor Icons for preview
        wp_enqueue_style(
            'phosphor-icons',
            'https://unpkg.com/@phosphor-icons/web@2.0.3/src/regular/style.css',
            [],
            '2.0.3'
        );

        // Enqueue custom admin styles
        wp_add_inline_style('phosphor-icons', '
            .shaped-amenity-table { width: 100%; }
            .shaped-amenity-table th { text-align: left; padding: 10px; background: #f0f0f1; }
            .shaped-amenity-table td { padding: 10px; border-bottom: 1px solid #e0e0e0; }
            .shaped-icon-preview { font-size: 24px; display: inline-block; width: 40px; text-align: center; }
            .shaped-icon-input { width: 200px; }
            .shaped-weight-select { width: 120px; }
            .shaped-no-icon { color: #999; font-style: italic; }
            .shaped-amenity-label { font-weight: 600; }
            .shaped-save-button { margin: 20px 0; }
        ');
    }

    /**
     * Render amenity icons admin page
     */
    public function render_admin_page(): void {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        // Get all amenities (mphb_room_facility taxonomy terms)
        $amenities = get_terms([
            'taxonomy' => 'mphb_room_facility',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC'
        ]);

        if (is_wp_error($amenities)) {
            $amenities = [];
        }

        // Initialize mapper to get icon data
        $mapper = new Shaped_Amenity_Mapper();

        ?>
        <div class="wrap">
            <h1><?php _e('Amenity Icon Management', 'shaped'); ?></h1>
            <p><?php _e('Customize icons and icon weights for your amenities. Leave blank to use automatic matching from the registry.', 'shaped'); ?></p>

            <?php if (isset($_GET['updated']) && $_GET['updated'] === '1'): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('Amenity icons updated successfully!', 'shaped'); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <?php wp_nonce_field('shaped_amenity_icons_save', 'shaped_amenity_icons_nonce'); ?>
                <input type="hidden" name="action" value="shaped_save_amenity_icons">

                <table class="shaped-amenity-table widefat">
                    <thead>
                        <tr>
                            <th><?php _e('Preview', 'shaped'); ?></th>
                            <th><?php _e('Amenity', 'shaped'); ?></th>
                            <th><?php _e('Custom Icon', 'shaped'); ?></th>
                            <th><?php _e('Icon Weight', 'shaped'); ?></th>
                            <th><?php _e('Auto-matched Icon', 'shaped'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($amenities)): ?>
                            <tr>
                                <td colspan="5">
                                    <?php _e('No amenities found. Add amenities via Accommodation → Amenities.', 'shaped'); ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($amenities as $amenity): ?>
                                <?php
                                $custom_icon = get_term_meta($amenity->term_id, self::CUSTOM_FIELD_ICON, true);
                                $custom_weight = get_term_meta($amenity->term_id, self::CUSTOM_FIELD_WEIGHT, true);
                                if (empty($custom_weight)) {
                                    $custom_weight = 'regular';
                                }

                                // Get current icon data (respecting custom overrides)
                                $icon_data = $mapper->get_icon($amenity);

                                // Get auto-matched icon (ignoring custom field)
                                $auto_icon_data = $this->get_auto_matched_icon($mapper, $amenity);
                                ?>
                                <tr>
                                    <td class="shaped-icon-preview">
                                        <?php if ($icon_data): ?>
                                            <?php echo $icon_data['html']; ?>
                                        <?php else: ?>
                                            <span class="shaped-no-icon">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="shaped-amenity-label"><?php echo esc_html($amenity->name); ?></span>
                                        <br>
                                        <small style="color: #666;"><?php echo esc_html($amenity->slug); ?></small>
                                    </td>
                                    <td>
                                        <input
                                            type="text"
                                            name="amenity_icon[<?php echo $amenity->term_id; ?>]"
                                            value="<?php echo esc_attr($custom_icon); ?>"
                                            class="shaped-icon-input"
                                            placeholder="e.g., wifi-high"
                                        >
                                    </td>
                                    <td>
                                        <select name="amenity_weight[<?php echo $amenity->term_id; ?>]" class="shaped-weight-select">
                                            <?php foreach (self::ICON_WEIGHTS as $value => $label): ?>
                                                <option value="<?php echo esc_attr($value); ?>" <?php selected($custom_weight, $value); ?>>
                                                    <?php echo esc_html($label); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <?php if ($auto_icon_data): ?>
                                            <?php echo $auto_icon_data['html']; ?>
                                            <code style="margin-left: 10px;"><?php echo esc_html($auto_icon_data['icon']); ?></code>
                                        <?php else: ?>
                                            <span class="shaped-no-icon"><?php _e('No match found', 'shaped'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <p class="shaped-save-button">
                    <?php submit_button(__('Save Icon Settings', 'shaped'), 'primary', 'submit', false); ?>
                </p>

                <div style="margin-top: 30px; padding: 20px; background: #f9f9f9; border-left: 4px solid #2271b1;">
                    <h3><?php _e('How to Use', 'shaped'); ?></h3>
                    <ul style="line-height: 1.8;">
                        <li><strong><?php _e('Custom Icon:', 'shaped'); ?></strong> <?php _e('Enter a Phosphor icon name (without "ph-" prefix)', 'shaped'); ?></li>
                        <li><strong><?php _e('Icon Weight:', 'shaped'); ?></strong> <?php _e('Choose icon thickness/style', 'shaped'); ?></li>
                        <li><strong><?php _e('Auto-matched:', 'shaped'); ?></strong> <?php _e('Shows what icon will be used if you leave Custom Icon blank', 'shaped'); ?></li>
                        <li><strong><?php _e('Preview:', 'shaped'); ?></strong> <?php _e('Shows the currently active icon', 'shaped'); ?></li>
                    </ul>
                    <p>
                        <a href="https://phosphoricons.com/" target="_blank" class="button button-secondary">
                            <?php _e('Browse Phosphor Icons →', 'shaped'); ?>
                        </a>
                    </p>
                </div>
            </form>
        </div>
        <?php
    }

    /**
     * Get auto-matched icon (bypass custom field)
     */
    private function get_auto_matched_icon($mapper, WP_Term $amenity): ?array {
        // Temporarily remove the custom field to get auto-match
        $custom_icon_backup = get_term_meta($amenity->term_id, self::CUSTOM_FIELD_ICON, true);
        delete_term_meta($amenity->term_id, self::CUSTOM_FIELD_ICON);

        // Clear cache to force re-evaluation
        Shaped_Amenity_Mapper::clear_cache();

        // Get the icon
        $auto_icon = $mapper->get_icon($amenity, ['skip_fallback' => true]);

        // Restore custom field
        if (!empty($custom_icon_backup)) {
            update_term_meta($amenity->term_id, self::CUSTOM_FIELD_ICON, $custom_icon_backup);
        }

        // Clear cache again
        Shaped_Amenity_Mapper::clear_cache();

        return $auto_icon;
    }

    /**
     * Handle form submission
     */
    public function handle_save(): void {
        // Check nonce
        if (!isset($_POST['shaped_amenity_icons_nonce']) ||
            !wp_verify_nonce($_POST['shaped_amenity_icons_nonce'], 'shaped_amenity_icons_save')) {
            wp_die(__('Security check failed.'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.'));
        }

        $icon_data = $_POST['amenity_icon'] ?? [];
        $weight_data = $_POST['amenity_weight'] ?? [];

        foreach ($icon_data as $term_id => $icon_name) {
            $term_id = intval($term_id);
            $icon_name = sanitize_text_field($icon_name);
            $weight = isset($weight_data[$term_id]) ? sanitize_text_field($weight_data[$term_id]) : 'regular';

            // Save or delete icon
            if (empty($icon_name)) {
                delete_term_meta($term_id, self::CUSTOM_FIELD_ICON);
            } else {
                update_term_meta($term_id, self::CUSTOM_FIELD_ICON, $icon_name);
            }

            // Save weight
            update_term_meta($term_id, self::CUSTOM_FIELD_WEIGHT, $weight);
        }

        // Clear cache
        Shaped_Amenity_Mapper::clear_cache();

        // Redirect back with success message
        wp_redirect(add_query_arg('updated', '1', admin_url('admin.php?page=shaped-amenity-icons')));
        exit;
    }
}
