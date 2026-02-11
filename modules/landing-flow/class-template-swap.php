<?php
/**
 * Landing Flow Template Swap
 *
 * Swaps Elementor Pro header/footer templates when the visitor is inside
 * the landing page booking flow.
 *
 * Template IDs can be set via:
 *   1. wp-config.php constants (SHAPED_LANDING_HEADER_ID, SHAPED_LANDING_FOOTER_ID)
 *   2. wp_options (shaped_landing_header_id, shaped_landing_footer_id)
 *
 * To find your Elementor template IDs:
 *   WP Admin → Templates → Theme Builder → hover over the template → the
 *   post ID is visible in the URL (?post=XXXX) or in the ID column.
 *
 * @package Shaped_Core
 * @subpackage Landing_Flow
 */

if (!defined('ABSPATH')) {
    exit;
}

class Shaped_Landing_Template_Swap {

    /**
     * Hook into Elementor's template resolution if landing flow is active
     * and template IDs are configured.
     */
    public static function init(): void {
        if (!Shaped_Landing_Flow_Detector::is_landing_flow()) {
            return;
        }

        $header_id = self::get_landing_header_id();
        $footer_id = self::get_landing_footer_id();

        // Only hook if at least one template is configured
        if (!$header_id && !$footer_id) {
            return;
        }

        // Elementor Pro filters template documents per location
        add_filter('elementor/theme/get_location_templates/template_id', function ($template_id, $location) use ($header_id, $footer_id) {
            if ($location === 'header' && $header_id) {
                return $header_id;
            }
            if ($location === 'footer' && $footer_id) {
                return $footer_id;
            }
            return $template_id;
        }, 10, 2);
    }

    /**
     * Get the Elementor template ID for the landing page header.
     *
     * @return int 0 if not configured
     */
    private static function get_landing_header_id(): int {
        if (defined('SHAPED_LANDING_HEADER_ID')) {
            return absint(SHAPED_LANDING_HEADER_ID);
        }
        return absint(get_option('shaped_landing_header_id', 0));
    }

    /**
     * Get the Elementor template ID for the landing page footer.
     *
     * @return int 0 if not configured
     */
    private static function get_landing_footer_id(): int {
        if (defined('SHAPED_LANDING_FOOTER_ID')) {
            return absint(SHAPED_LANDING_FOOTER_ID);
        }
        return absint(get_option('shaped_landing_footer_id', 0));
    }
}
