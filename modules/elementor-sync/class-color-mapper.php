<?php
/**
 * Elementor Color Mapper
 *
 * Maps brand configuration colors to Elementor global color format.
 * Provides filterable mapping configuration for customization.
 *
 * @package Shaped_Core
 * @subpackage Elementor_Sync
 */

namespace Shaped\Modules\ElementorSync;

if (!defined('ABSPATH')) {
    exit;
}

class Color_Mapper {

    /**
     * Map brand colors to Elementor format
     *
     * @return array ['system_colors' => [...], 'custom_colors' => [...]]
     */
    public static function map_colors(): array {
        $brand_config = self::get_brand_config();

        return [
            'system_colors' => self::map_system_colors($brand_config),
            'custom_colors' => self::map_custom_colors($brand_config),
        ];
    }

    /**
     * Map system colors (Elementor's 4 default slots)
     *
     * Uses exact Elementor CSS variable IDs: primary, text, accent, secondary
     *
     * @param array $config Brand configuration
     * @return array System colors in Elementor format
     */
    private static function map_system_colors(array $config): array {
        $mapping = apply_filters('shaped/elementor/system_color_mapping', [
            [
                'id' => 'primary',
                'title' => 'Surface Page',
                'path' => 'colors.surface.page',
                'fallback' => '#FBFBF9',
            ],
            [
                'id' => 'text',
                'title' => 'Text Primary / Surface Black',
                'path' => 'colors.surface.pageBlack',
                'fallback' => '#0B0B09',
            ],
            [
                'id' => 'accent',
                'title' => 'Brand Primary',
                'path' => 'colors.brand.primary',
                'fallback' => '#E2BD27',
            ],
            [
                'id' => 'secondary',
                'title' => 'Surface Dark',
                'path' => 'colors.surface.pageDark',
                'fallback' => '#2C2B25',
            ],
        ]);

        return self::process_color_mapping($mapping, $config);
    }

    /**
     * Map custom colors (extensible slots)
     *
     * Uses exact Elementor-generated IDs from the active site configuration.
     * Constants (Surface Alt, Border, Success, Error) are hardcoded.
     *
     * @param array $config Brand configuration
     * @return array Custom colors in Elementor format
     */
    private static function map_custom_colors(array $config): array {
        $mapping = apply_filters('shaped/elementor/custom_color_mapping', [
            // Brand config colors (will override)
            [
                'id' => '73fe071',
                'title' => 'Hover Primary',
                'path' => 'colors.brand.primaryHover',
                'fallback' => '#B7991F',
            ],
            [
                'id' => '3f7ee3f2',
                'title' => 'Surface Highlight',
                'path' => 'colors.surface.highlight',
                'fallback' => '#fffbf0',
            ],
            [
                'id' => '170f6343',
                'title' => 'Surface White / Text White',
                'path' => 'colors.text.inverse',
                'fallback' => '#FFFFFF',
            ],
            [
                'id' => '456087ab',
                'title' => 'Text Muted',
                'path' => 'colors.text.muted',
                'fallback' => '#51504D',
            ],
            [
                'id' => '9e7def5',
                'title' => 'Text White Muted',
                'path' => 'colors.text.inverseMuted',
                'fallback' => 'var(--color-text-muted)',
            ],
            [
                'id' => 'f3d7197',
                'title' => 'On Brand Primary',
                'path' => 'colors.text.onPrimary',
                'fallback' => '#0B0B09',
            ],

            // Constants (same across all projects)
            [
                'id' => 'd273c21',
                'title' => 'Surface Alt',
                'constant' => '#F8F8F8',
            ],
            [
                'id' => 'cdbc92d',
                'title' => 'Border',
                'constant' => '#E4E4E4',
            ],
            [
                'id' => '3475a1d',
                'title' => 'Success',
                'constant' => '#4C9155',
            ],
            [
                'id' => '5e85910',
                'title' => 'Error',
                'constant' => '#B83C2E',
            ],
        ]);

        return self::process_color_mapping($mapping, $config);
    }

    /**
     * Process color mapping and return Elementor-formatted array
     *
     * @param array $mapping Color mapping configuration
     * @param array $config Brand configuration
     * @return array Processed colors
     */
    private static function process_color_mapping(array $mapping, array $config): array {
        $processed = [];

        foreach ($mapping as $item) {
            $color_value = null;

            // Check if constant is defined (overrides path)
            if (isset($item['constant'])) {
                $color_value = $item['constant'];
            }
            // Otherwise, get from brand config using path
            elseif (isset($item['path'])) {
                $color_value = self::get_nested_value($config, $item['path']);

                // Use fallback if path not found
                if ($color_value === null && isset($item['fallback'])) {
                    $color_value = $item['fallback'];
                }
            }

            // Only add if we have a color value
            if ($color_value !== null) {
                $processed[] = [
                    '_id' => $item['id'],
                    'title' => $item['title'],
                    'color' => $color_value,
                ];
            }
        }

        return $processed;
    }

    /**
     * Get nested value from array using dot notation
     *
     * @param array $array Source array
     * @param string $path Dot-separated path (e.g., 'colors.brand.primary')
     * @return mixed|null Value or null if not found
     */
    private static function get_nested_value(array $array, string $path) {
        $keys = explode('.', $path);
        $value = $array;

        foreach ($keys as $key) {
            if (!isset($value[$key])) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * Get brand configuration from Shaped_Brand_Config
     *
     * @return array Brand configuration
     */
    private static function get_brand_config(): array {
        // Method 1: Direct function call (MU-plugin)
        if (function_exists('shaped_get_client_config')) {
            return shaped_get_client_config();
        }

        // Method 2: Via Shaped_Brand_Config class
        if (class_exists('Shaped_Brand_Config')) {
            return \Shaped_Brand_Config::instance()->get_all();
        }

        // Fallback - return empty array
        error_log('[Shaped Elementor Sync] No brand config found');
        return [];
    }

    /**
     * Get preview of color mapping (for admin UI)
     *
     * @return array Array of color mappings with values
     */
    public static function get_mapping_preview(): array {
        $config = self::get_brand_config();
        $system = self::map_system_colors($config);
        $custom = self::map_custom_colors($config);

        return array_merge($system, $custom);
    }
}
