<?php
/**
 * Shaped Font Loader
 *
 * Generates @font-face CSS based on brand.json configuration.
 * Single source of truth for font configuration.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Shaped_Font_Loader {

    /**
     * Generate @font-face CSS based on brand configuration
     *
     * @return string CSS with @font-face declarations
     */
    public static function generate_font_css(): string {
        $brand_config = shaped_brand_config();

        $font_family = $brand_config['type']['heading']['family'] ?? 'DM Sans';
        $weights = $brand_config['type']['heading']['weights'] ?? [400, 500, 700];

        // Start CSS output
        $css = "/* Font Declarations - Generated from brand.json */\n\n";

        // Generate @font-face declarations for each weight
        $font_slug = sanitize_title($font_family);
        $font_url = SHAPED_URL . 'assets/fonts/';

        foreach ($weights as $weight) {
            $weight_name = self::get_weight_name($weight);
            $font_file = "{$font_slug}-{$weight_name}.woff2";
            $font_path = SHAPED_DIR . "assets/fonts/{$font_file}";

            // Only generate @font-face if file exists
            if (file_exists($font_path)) {
                $css .= "@font-face {\n";
                $css .= "    font-family: '{$font_family}';\n";
                $css .= "    font-style: normal;\n";
                $css .= "    font-weight: {$weight};\n";
                $css .= "    font-display: swap;\n";
                $css .= "    src: url('{$font_url}{$font_file}') format('woff2');\n";
                $css .= "}\n\n";
            }
        }

        return $css;
    }

    /**
     * Get weight name from numeric value
     *
     * @param int $weight Font weight (100-900)
     * @return string Weight name (e.g., 'regular', 'bold')
     */
    private static function get_weight_name(int $weight): string {
        $weight_map = [
            100 => 'thin',
            200 => 'extralight',
            300 => 'light',
            400 => 'regular',
            500 => 'medium',
            600 => 'semibold',
            700 => 'bold',
            800 => 'extrabold',
            900 => 'black'
        ];

        return $weight_map[$weight] ?? 'regular';
    }
}
