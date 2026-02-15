<?php
/**
 * Brand Helper Functions
 *
 * Convenient global functions for accessing brand configuration.
 * Use these in email templates and PHP inline styles.
 *
 * @package Shaped_Core
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get brand configuration value using dot notation
 *
 * @param string $path Dot-separated path (e.g., 'colors.brand.primary')
 * @param mixed $default Default value if not found
 * @return mixed
 *
 * @example shaped_brand('colors.brand.primary') // Returns '#E2BD27'
 * @example shaped_brand('colors.semantic.success') // Returns '#4C9155'
 * @example shaped_brand('type.baseSize') // Returns 16
 */
function shaped_brand($path, $default = null) {
    return Shaped_Brand_Config::instance()->get($path, $default);
}

/**
 * Get brand color by key
 *
 * Automatically searches common color paths:
 * - colors.brand.{key}
 * - colors.semantic.{key}
 * - colors.text.{key}
 * - colors.surface.{key}
 * - colors.border.{key}
 *
 * Falls back to plugin constants if not found in client config.
 *
 * @param string $key Color key
 * @return string|null Color value (e.g., '#E2BD27') or null
 *
 * @example shaped_brand_color('primary') // Returns '#E2BD27'
 * @example shaped_brand_color('success') // Returns '#4C9155'
 * @example shaped_brand_color('textMuted') // Returns '#666666'
 */
function shaped_brand_color($key) {
    $color = Shaped_Brand_Config::instance()->get_color($key);

    // If not found in config, fall back to plugin constants
    // These are CONSTANTS that should NOT be in client config
    if ($color === null) {
        $constants = [
            // Semantic colors (CONSTANTS - same for all clients)
            'success' => '#4C9155',
            'warning' => '#f59e0b',
            'error' => '#b83c2e',

            // Surface constants (generic white/light gray)
            'alt' => '#F8F8F8',
            'white' => '#FFFFFF',
            'card' => '#FFFFFF',

            // Overlay
            'scrim' => 'rgba(0, 0, 0, 0.5)',

            // Border
            'default' => '#e4e4e4',
        ];

        return $constants[$key] ?? null;
    }

    return $color;
}

/**
 * Get all brand colors as associative array
 *
 * @return array
 *
 * @example
 * $colors = shaped_brand_colors();
 * echo $colors['brand']['primary']; // '#E2BD27'
 */
function shaped_brand_colors() {
    return Shaped_Brand_Config::instance()->get_all_colors();
}

/**
 * Get current client identifier
 *
 * @return string|null Client name or null if using base config
 *
 * @example shaped_brand_client() // Returns 'preelook' or null
 */
function shaped_brand_client() {
    return Shaped_Brand_Config::instance()->get_client();
}

/**
 * Check if a specific client is active
 *
 * @param string $client_name Client identifier to check
 * @return bool
 *
 * @example shaped_is_client('preelook') // Returns true/false
 */
function shaped_is_client($client_name) {
    return shaped_brand_client() === $client_name;
}

/**
 * Get brand color with fallback
 *
 * Tries to get color, falls back to default if not found.
 * Useful for ensuring a color is always returned.
 *
 * @param string $key Primary color key to try
 * @param string $fallback Fallback color (hex value)
 * @return string Color value
 *
 * @example shaped_brand_color_or('customColor', '#E2BD27')
 */
function shaped_brand_color_or($key, $fallback) {
    $color = shaped_brand_color($key);
    return $color !== null ? $color : $fallback;
}

/**
 * Get inline style attribute with brand color
 *
 * Convenience function for generating inline style strings.
 *
 * @param string $property CSS property name
 * @param string $color_key Brand color key
 * @return string CSS style string
 *
 * @example shaped_brand_style('color', 'primary') // Returns 'color: #E2BD27;'
 * @example shaped_brand_style('background', 'success') // Returns 'background: #4C9155;'
 */
function shaped_brand_style($property, $color_key) {
    $color = shaped_brand_color($color_key);
    if ($color === null) {
        return '';
    }
    return esc_attr($property) . ': ' . esc_attr($color) . ';';
}

/**
 * Get brand colors formatted for JavaScript
 *
 * Returns colors in format suitable for wp_localize_script().
 * Use this to pass brand colors to JavaScript.
 *
 * @return array Flat array of color values
 *
 * @example
 * wp_localize_script('my-script', 'ShapedBrand', shaped_brand_colors_for_js());
 * // JS: console.log(ShapedBrand.primary) // '#E2BD27'
 */
function shaped_brand_colors_for_js() {
    $colors = shaped_brand_colors();

    // Flatten nested structure for easier JS access
    $flat = [];

    // Brand colors
    if (isset($colors['brand'])) {
        foreach ($colors['brand'] as $key => $value) {
            $flat[$key] = $value;
        }
    }

    // Semantic colors
    if (isset($colors['semantic'])) {
        foreach ($colors['semantic'] as $key => $value) {
            $flat[$key] = $value;
        }
    }

    // Text colors
    if (isset($colors['text'])) {
        foreach ($colors['text'] as $key => $value) {
            $flat['text' . ucfirst($key)] = $value;
        }
    }

    // Surface colors
    if (isset($colors['surface'])) {
        foreach ($colors['surface'] as $key => $value) {
            $flat['surface' . ucfirst($key)] = $value;
        }
    }

    // Border colors
    if (isset($colors['border'])) {
        foreach ($colors['border'] as $key => $value) {
            $flat['border' . ucfirst($key)] = $value;
        }
    }

    return $flat;
}

/**
 * Echo brand color (for use in templates)
 *
 * @param string $key Color key
 * @return void
 *
 * @example <div style="color: <?php shaped_brand_color_e('primary'); ?>">
 */
function shaped_brand_color_e($key) {
    echo esc_attr(shaped_brand_color($key));
}

/**
 * Echo brand value (for use in templates)
 *
 * @param string $path Dot-separated path
 * @param mixed $default Default value
 * @return void
 *
 * @example <p style="font-size: <?php shaped_brand_e('type.baseSize'); ?>px">
 */
function shaped_brand_e($path, $default = null) {
    echo esc_attr(shaped_brand($path, $default));
}

/**
 * Get full brand configuration array
 *
 * @return array Complete brand configuration
 */
function shaped_brand_all() {
    return Shaped_Brand_Config::instance()->get_all();
}

/* =========================================================================
 * LEGAL CONTENT HELPERS
 * ========================================================================= */

/**
 * Get path to client legal content directory
 *
 * @param string|null $client Client identifier (uses current if null)
 * @return string Path to legal directory
 */
function shaped_legal_path($client = null) {
    if ($client === null) {
        $client = shaped_brand_client();
    }

    if ($client) {
        return SHAPED_DIR . 'clients/' . $client . '/legal/';
    }

    return SHAPED_DIR . 'clients/_template/legal/';
}

/**
 * Check if legal content exists for a specific type
 *
 * @param string $type Legal content type ('terms' or 'privacy')
 * @param string|null $client Client identifier (uses current if null)
 * @return bool
 */
function shaped_has_legal_content($type, $client = null) {
    $path = shaped_legal_path($client) . $type . '.php';
    return file_exists($path);
}

/**
 * Render legal content
 *
 * Loads legal content from client's legal directory.
 * Falls back to template if client content doesn't exist.
 *
 * @param string $type Legal content type ('terms' or 'privacy')
 * @return void
 */
function shaped_render_legal_content($type) {
    $client = shaped_brand_client();
    $client_path = shaped_legal_path($client) . $type . '.php';
    $template_path = SHAPED_DIR . 'clients/_template/legal/' . $type . '.php';

    // Make brand config available to the template
    $brand = shaped_brand_all();

    // Try client-specific content first
    if (file_exists($client_path)) {
        include $client_path;
        return;
    }

    // Fall back to template
    if (file_exists($template_path)) {
        include $template_path;
        return;
    }

    // Final fallback: show placeholder
    echo '<p><em>Legal content not configured. Please add <code>' . esc_html($type) . '.php</code> to your client\'s legal directory.</em></p>';
}

/**
 * Get legal content as string (for use in APIs, etc.)
 *
 * @param string $type Legal content type ('terms' or 'privacy')
 * @return string HTML content
 */
function shaped_get_legal_content($type) {
    ob_start();
    shaped_render_legal_content($type);
    return ob_get_clean();
}
