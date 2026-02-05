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
     * Initialize the mapper
     */
    public function __construct() {
        // Load registry on first use
        $this->load_registry();
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
     * 1. Exact slug match in registry
     * 2. Normalized name match
     * 3. Keyword contains match
     * 4. Fallback icon (or null if skip_fallback is true)
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

        // Support known taxonomy slug aliases.
        $slug = $this->normalize_alias_slug($slug);

        // Check cache first
        $cache_key = $slug . '_' . md5(serialize($args));
        if (isset(self::$icon_cache[$cache_key])) {
            return self::$icon_cache[$cache_key];
        }

        // Priority 1: Exact slug match
        $registry_item = $this->find_by_slug($slug);
        if ($registry_item) {
            $icon_data = $this->build_icon_data($registry_item['icon'], $registry_item['label'], $args, $registry_item);
            $icon_data['is_fallback'] = false;
            self::$icon_cache[$cache_key] = $icon_data;
            return $icon_data;
        }

        // Priority 2: Normalized name match
        $registry_item = $this->find_by_normalized_name($name);
        if ($registry_item) {
            $icon_data = $this->build_icon_data($registry_item['icon'], $registry_item['label'], $args, $registry_item);
            $icon_data['is_fallback'] = false;
            self::$icon_cache[$cache_key] = $icon_data;
            return $icon_data;
        }

        // Priority 3: Keyword contains match
        $registry_item = $this->find_by_keywords($name);
        if ($registry_item) {
            $icon_data = $this->build_icon_data($registry_item['icon'], $registry_item['label'], $args, $registry_item);
            $icon_data['is_fallback'] = false;
            self::$icon_cache[$cache_key] = $icon_data;
            return $icon_data;
        }

        // Priority 4: Fallback or null
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
     * Normalize known slug aliases to canonical registry slugs.
     *
     * @param string $slug Incoming taxonomy slug
     * @return string Canonical slug
     */
    private function normalize_alias_slug(string $slug): string {
        $aliases = [
            'free-parking' => 'private-parking',
        ];

        return $aliases[$slug] ?? $slug;
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
        $facilities = get_the_terms($room_type_id, 'mphb_room_type_facility');

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
}
