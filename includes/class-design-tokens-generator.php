<?php
/**
 * Shaped Design Tokens Generator
 *
 * Generates CSS custom properties dynamically from client configuration.
 * Works with shaped-client-config.php in mu-plugins (outside repo).
 */

if (!defined('ABSPATH')) {
    exit;
}

class Shaped_Design_Tokens_Generator {

    /**
     * Generate CSS custom properties from client configuration
     *
     * @return string CSS with custom property overrides
     */
    public static function generate_tokens_css(): string {
        // Get client config - works even when mu-plugins is outside repo
        $config = self::get_config();

        if (empty($config)) {
            return "/* No client config found - using defaults */\n";
        }

        $css = "/* Design Tokens - Generated from shaped-client-config.php */\n";
        $css .= ":root {\n";

        // ─── Brand Colors ───
        if (isset($config['colors']['brand'])) {
            $brand = $config['colors']['brand'];
            if (isset($brand['primary'])) {
                $css .= "    --color-brand-primary: {$brand['primary']};\n";
            }
            if (isset($brand['primaryHover'])) {
                $css .= "    --color-brand-primary-hover: {$brand['primaryHover']};\n";
            }
            if (isset($brand['secondary'])) {
                $css .= "    --color-brand-secondary: {$brand['secondary']};\n";
            }
        }

        // ─── Surface Colors (CLIENT-SPECIFIC ONLY) ───
        // Note: alt, white, card are CONSTANTS in plugin - don't inject them
        if (isset($config['colors']['surface'])) {
            $surface = $config['colors']['surface'];

            // Only inject CLIENT-SPECIFIC surface colors
            // Constants (alt, white) are hardcoded in design-tokens.css
            $surface_map = [
                'page'      => 'page',
                'highlight' => 'highlight',
                'pageDark'  => 'page-dark',
                'pageBlack' => 'page-black',
            ];

            foreach ($surface_map as $config_key => $css_key) {
                if (isset($surface[$config_key])) {
                    $css .= "    --color-surface-{$css_key}: {$surface[$config_key]};\n";
                }
            }
        }

        // ─── Text Colors ───
        if (isset($config['colors']['text'])) {
            $text = $config['colors']['text'];
            if (isset($text['primary'])) {
                $css .= "    --color-text-primary: {$text['primary']};\n";
            }
            if (isset($text['muted'])) {
                $css .= "    --color-text-muted: {$text['muted']};\n";
            }
            if (isset($text['inverse'])) {
                $css .= "    --color-text-inverse: {$text['inverse']};\n";
            }
            if (isset($text['onPrimary'])) {
                $css .= "    --color-text-on-primary: {$text['onPrimary']};\n";
            }
        }

        // ─── Typography ───
        if (isset($config['type'])) {
            $type = $config['type'];

            // Heading font
            if (isset($type['heading']['family'])) {
                $heading_family = $type['heading']['family'];
                $heading_fallback = $type['heading']['fallback'] ?? 'sans-serif';
                $css .= "    --font-heading: '{$heading_family}', {$heading_fallback};\n";
            }

            // Body font
            if (isset($type['body']['family'])) {
                $body_family = $type['body']['family'];
                $body_fallback = $type['body']['fallback'] ?? 'sans-serif';
                $css .= "    --font-body: '{$body_family}', {$body_fallback};\n";
            }
        }

        $css .= "}\n";

        return $css;
    }

    /**
     * Get configuration from shaped_get_client_config()
     * This function is defined in mu-plugins/shaped-client-config.php
     *
     * @return array
     */
    private static function get_config(): array {
        // Method 1: Direct function call (MU-plugin)
        if (function_exists('shaped_get_client_config')) {
            return shaped_get_client_config();
        }

        // Method 2: Via Shaped_Brand_Config class
        if (class_exists('Shaped_Brand_Config')) {
            return Shaped_Brand_Config::instance()->get_all();
        }

        // Method 3: Fallback - use brand config helper if available
        if (function_exists('shaped_brand_config')) {
            return shaped_brand_config();
        }

        // No config available - return empty array
        return [];
    }
}
