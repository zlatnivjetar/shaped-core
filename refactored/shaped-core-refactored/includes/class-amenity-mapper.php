<?php
/**
 * Amenity Icon Mapper
 *
 * Handles loading, caching, and matching of amenity icons from registry.
 * Provides fuzzy matching and priority-based ordering for room amenities.
 *
 * @package Shaped
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Shaped_Amenity_Mapper
 */
class Shaped_Amenity_Mapper {

    /**
     * Registry data loaded from JSON
     *
     * @var array|null
     */
    private static $registry = null;

    /**
     * Cache for matched icons
     *
     * @var array
     */
    private static $icon_cache = [];

    /**
     * Custom field name for taxonomy term overrides
     *
     * @var string
     */
    private const CUSTOM_FIELD = '_shaped_amenity_icon';

    /**
     * Initialize the mapper
     */
    public function __construct() {
        // Load registry on first use
        $this->load_registry();

        // Hook into taxonomy term fields
        add_action('mphb_room_facility_add_form_fields', [$this, 'add_term_icon_field']);
        add_action('mphb_room_facility_edit_form_fields', [$this, 'edit_term_icon_field'], 10, 1);
        add_action('created_mphb_room_facility', [$this, 'save_term_icon_field']);
        add_action('edited_mphb_room_facility', [$this, 'save_term_icon_field']);
        add_filter('manage_edit-mphb_room_facility_columns', [$this, 'add_icon_column']);
        add_filter('manage_mphb_room_facility_custom_column', [$this, 'render_icon_column'], 10, 3);
    }

    /**
     * Load amenities registry from JSON file
     *
     * @return void
     */
    private function load_registry(): void {
        if (self::$registry !== null) {
            return;
        }

        $registry_file = SHAPED_DIR . 'config/amenities-registry.json';

        if (!file_exists($registry_file)) {
            error_log('Shaped Amenity Mapper: Registry file not found at ' . $registry_file);
            self::$registry = ['amenities' => [], 'fallback' => ['icon' => 'circle-dashed', 'weight' => 'regular']];
            return;
        }

        $json = file_get_contents($registry_file);
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Shaped Amenity Mapper: JSON parse error - ' . json_last_error_msg());
            self::$registry = ['amenities' => [], 'fallback' => ['icon' => 'circle-dashed', 'weight' => 'regular']];
            return;
        }

        self::$registry = $data;

        // Allow filtering of registry
        self::$registry = apply_filters('shaped/amenities/registry', self::$registry);
    }

    /**
     * Get icon data for a facility term
     *
     * Priority order:
     * 1. Custom field on term
     * 2. Exact slug match in registry
     * 3. Normalized name match
     * 4. Keyword contains match
     * 5. Fallback icon (or null if skip_fallback is true)
     *
     * @param WP_Term|string $facility Term object or slug
     * @param array          $args     Optional arguments:
     *                                 - weight (string): Icon weight (regular, bold, light, thin, duotone, fill)
     *                                 - class (string): Additional CSS classes
     *                                 - skip_fallback (bool): Return null if no icon found (default: false)
     * @return array|null Icon data array or null if no match found and skip_fallback is true
     */
    public function get_icon(WP_Term|string $facility, array $args = []): ?array {
        // Normalize input
        if (is_string($facility)) {
            $slug = $facility;
            $name = $facility;
            $term_id = null;
        } elseif ($facility instanceof WP_Term) {
            $slug = $facility->slug;
            $name = $facility->name;
            $term_id = $facility->term_id;
        } else {
            return null;
        }

        $skip_fallback = $args['skip_fallback'] ?? false;

        // Check cache first
        $cache_key = $slug . '_' . md5(serialize($args));
        if (isset(self::$icon_cache[$cache_key])) {
            return self::$icon_cache[$cache_key];
        }

        // Priority 1: Custom field override
        if ($term_id) {
            $custom_icon = get_term_meta($term_id, self::CUSTOM_FIELD, true);
            if (!empty($custom_icon)) {
                $icon_data = $this->build_icon_data($custom_icon, $name, $args);
                $icon_data['is_fallback'] = false;
                self::$icon_cache[$cache_key] = $icon_data;
                return $icon_data;
            }
        }

        // Priority 2: Exact slug match
        $registry_item = $this->find_by_slug($slug);
        if ($registry_item) {
            $icon_data = $this->build_icon_data($registry_item['icon'], $registry_item['label'], $args, $registry_item);
            $icon_data['is_fallback'] = false;
            self::$icon_cache[$cache_key] = $icon_data;
            return $icon_data;
        }

        // Priority 3: Normalized name match
        $registry_item = $this->find_by_normalized_name($name);
        if ($registry_item) {
            $icon_data = $this->build_icon_data($registry_item['icon'], $registry_item['label'], $args, $registry_item);
            $icon_data['is_fallback'] = false;
            self::$icon_cache[$cache_key] = $icon_data;
            return $icon_data;
        }

        // Priority 4: Keyword contains match
        $registry_item = $this->find_by_keywords($name);
        if ($registry_item) {
            $icon_data = $this->build_icon_data($registry_item['icon'], $registry_item['label'], $args, $registry_item);
            $icon_data['is_fallback'] = false;
            self::$icon_cache[$cache_key] = $icon_data;
            return $icon_data;
        }

        // Priority 5: Fallback or null
        if ($skip_fallback) {
            self::$icon_cache[$cache_key] = null;
            return null;
        }

        $fallback = self::$registry['fallback'] ?? ['icon' => 'circle-dashed', 'weight' => 'regular'];
        $icon_data = $this->build_icon_data($fallback['icon'], $name, $args, ['priority' => 999]);
        $icon_data['is_fallback'] = true;
        self::$icon_cache[$cache_key] = $icon_data;
        return $icon_data;
    }

    /**
     * Find amenity by exact slug match
     *
     * @param string $slug Amenity slug
     * @return array|null Registry item or null
     */
    private function find_by_slug(string $slug): ?array {
        foreach (self::$registry['amenities'] as $amenity) {
            if ($amenity['slug'] === $slug) {
                return $amenity;
            }
        }
        return null;
    }

    /**
     * Find amenity by normalized name
     *
     * @param string $name Amenity name
     * @return array|null Registry item or null
     */
    private function find_by_normalized_name(string $name): ?array {
        $normalized = $this->normalize_string($name);

        foreach (self::$registry['amenities'] as $amenity) {
            if ($this->normalize_string($amenity['label']) === $normalized) {
                return $amenity;
            }
        }
        return null;
    }

    /**
     * Find amenity by keyword matching
     *
     * @param string $name Amenity name
     * @return array|null Registry item or null
     */
    private function find_by_keywords(string $name): ?array {
        $normalized = $this->normalize_string($name);

        foreach (self::$registry['amenities'] as $amenity) {
            if (empty($amenity['keywords'])) {
                continue;
            }

            foreach ($amenity['keywords'] as $keyword) {
                $normalized_keyword = $this->normalize_string($keyword);

                // Check if normalized name contains keyword or keyword contains name
                if (str_contains($normalized, $normalized_keyword) ||
                    str_contains($normalized_keyword, $normalized)) {
                    return $amenity;
                }
            }
        }
        return null;
    }

    /**
     * Normalize string for matching
     *
     * @param string $str String to normalize
     * @return string Normalized string
     */
    private function normalize_string(string $str): string {
        // Convert to lowercase
        $str = strtolower($str);

        // Remove special characters and extra spaces
        $str = preg_replace('/[^a-z0-9\s]/', '', $str);
        $str = preg_replace('/\s+/', ' ', $str);

        return trim($str);
    }

    /**
     * Build icon data array
     *
     * @param string $icon_name      Icon name (without 'ph' prefix)
     * @param string $label          Display label
     * @param array  $args           Optional arguments
     * @param array  $registry_item  Original registry item (for priority)
     * @return array Icon data
     */
    private function build_icon_data(string $icon_name, string $label, array $args = [], array $registry_item = []): array {
        $weight = $args['weight'] ?? 'regular';
        $extra_classes = $args['class'] ?? '';

        // Build Phosphor classes
        $classes = ['ph'];

        // Add weight class if not regular
        if ($weight !== 'regular') {
            $classes[] = 'ph-' . $weight;
        }

        // Add icon name
        $classes[] = 'ph-' . $icon_name;

        // Add any extra classes
        if (!empty($extra_classes)) {
            $classes[] = $extra_classes;
        }

        $class_string = implode(' ', $classes);

        return [
            'icon' => $icon_name,
            'label' => $label,
            'weight' => $weight,
            'class' => $class_string,
            'html' => '<i class="' . esc_attr($class_string) . '" aria-hidden="true"></i>',
            'priority' => $registry_item['priority'] ?? 999,
        ];
    }

    /**
     * Get all amenities from registry
     *
     * @return array Amenities array
     */
    public function get_all_amenities(): array {
        return self::$registry['amenities'] ?? [];
    }

    /**
     * Get sorted amenities for a room type
     *
     * @param int   $room_type_id  Room type post ID
     * @param array $args          Optional arguments:
     *                             - skip_fallback (bool): Skip amenities without icons (default: true)
     * @return array Sorted array of icon data
     */
    public function get_room_amenities(int $room_type_id, array $args = []): array {
        $facilities = get_the_terms($room_type_id, 'mphb_room_facility');

        if (empty($facilities) || is_wp_error($facilities)) {
            return [];
        }

        // Default to skipping fallback icons (hide amenities without icons)
        $skip_fallback = $args['skip_fallback'] ?? true;

        $amenities = [];

        foreach ($facilities as $facility) {
            $icon_data = $this->get_icon($facility, ['skip_fallback' => $skip_fallback]);
            if ($icon_data) {
                $amenities[] = $icon_data;
            }
        }

        // Sort by priority
        usort($amenities, function($a, $b) {
            return $a['priority'] - $b['priority'];
        });

        return $amenities;
    }

    /**
     * Clear icon cache
     *
     * @return void
     */
    public static function clear_cache(): void {
        self::$icon_cache = [];
    }

    // ===========================
    // Taxonomy Custom Field Hooks
    // ===========================

    /**
     * Add custom icon field to term add form
     *
     * @param string $taxonomy Taxonomy name
     * @return void
     */
    public function add_term_icon_field(string $taxonomy): void {
        ?>
        <div class="form-field">
            <label for="shaped-amenity-icon"><?php esc_html_e('Custom Icon', 'shaped'); ?></label>
            <input type="text" id="shaped-amenity-icon" name="shaped_amenity_icon" value="" />
            <p class="description">
                <?php esc_html_e('Override the default icon. Enter a Phosphor icon name (e.g., "wifi-high", "shower", "bed"). Leave blank to use automatic matching.', 'shaped'); ?>
                <br>
                <a href="https://phosphoricons.com/" target="_blank"><?php esc_html_e('Browse Phosphor Icons →', 'shaped'); ?></a>
            </p>
        </div>
        <?php
    }

    /**
     * Add custom icon field to term edit form
     *
     * @param WP_Term $term Current term object
     * @return void
     */
    public function edit_term_icon_field(WP_Term $term): void {
        $icon = get_term_meta($term->term_id, self::CUSTOM_FIELD, true);
        ?>
        <tr class="form-field">
            <th scope="row">
                <label for="shaped-amenity-icon"><?php esc_html_e('Custom Icon', 'shaped'); ?></label>
            </th>
            <td>
                <input type="text" id="shaped-amenity-icon" name="shaped_amenity_icon" value="<?php echo esc_attr($icon); ?>" class="regular-text" />
                <p class="description">
                    <?php esc_html_e('Override the default icon. Enter a Phosphor icon name (e.g., "wifi-high", "shower", "bed"). Leave blank to use automatic matching.', 'shaped'); ?>
                    <br>
                    <a href="https://phosphoricons.com/" target="_blank"><?php esc_html_e('Browse Phosphor Icons →', 'shaped'); ?></a>
                </p>
                <?php
                // Show preview of current icon
                $current_icon = $this->get_icon($term);
                if ($current_icon) {
                    echo '<p class="description">';
                    echo '<strong>' . esc_html__('Current icon:', 'shaped') . '</strong> ';
                    echo $current_icon['html'] . ' ';
                    echo '<code>' . esc_html($current_icon['icon']) . '</code>';
                    echo '</p>';
                }
                ?>
            </td>
        </tr>
        <?php
    }

    /**
     * Save custom icon field
     *
     * @param int $term_id Term ID
     * @return void
     */
    public function save_term_icon_field(int $term_id): void {
        if (!isset($_POST['shaped_amenity_icon'])) {
            return;
        }

        $icon = sanitize_text_field($_POST['shaped_amenity_icon']);

        if (empty($icon)) {
            delete_term_meta($term_id, self::CUSTOM_FIELD);
        } else {
            update_term_meta($term_id, self::CUSTOM_FIELD, $icon);
        }

        // Clear cache when term is updated
        self::clear_cache();
    }

    /**
     * Add icon column to term list table
     *
     * @param array $columns Existing columns
     * @return array Modified columns
     */
    public function add_icon_column(array $columns): array {
        $new_columns = [];

        foreach ($columns as $key => $value) {
            if ($key === 'name') {
                $new_columns['icon'] = __('Icon', 'shaped');
            }
            $new_columns[$key] = $value;
        }

        return $new_columns;
    }

    /**
     * Render icon in column
     *
     * @param string $content    Column content
     * @param string $column_name Column name
     * @param int    $term_id    Term ID
     * @return string Column content
     */
    public function render_icon_column(string $content, string $column_name, int $term_id): string {
        if ($column_name !== 'icon') {
            return $content;
        }

        $term = get_term($term_id, 'mphb_room_facility');
        if (!$term || is_wp_error($term)) {
            return $content;
        }

        $icon_data = $this->get_icon($term);
        if ($icon_data) {
            return '<span style="font-size: 24px;">' . $icon_data['html'] . '</span>';
        }

        return '—';
    }
}
