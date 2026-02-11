<?php
/**
 * Landing Flow Template Swap
 *
 * Intercepts Elementor Pro's template resolution to return landing-specific
 * header/footer template IDs when the booking flow was initiated from /book.
 *
 * Template IDs are read from:
 *   1) Client config: elementor.landing_header_id / elementor.landing_footer_id
 *   2) WordPress options: shaped_landing_header_id / shaped_landing_footer_id
 *
 * @package Shaped_Core
 * @subpackage Landing_Flow
 */

namespace Shaped\Modules\LandingFlow;

if (!defined('ABSPATH')) {
    exit;
}

class Template_Swap {

    private const FLOW_PAGES = ['book', 'search-results', 'checkout'];

    /**
     * Filter callback for elementor/theme/get_location_templates/template_id.
     *
     * @param int    $template_id The template ID Elementor resolved via conditions.
     * @param string $location    The location: 'header', 'footer', 'single', 'archive'.
     * @return int The (possibly overridden) template ID.
     */
    public static function maybe_swap_template(int $template_id, string $location): int {
        if (!in_array($location, ['header', 'footer'], true)) {
            return $template_id;
        }

        if (!Cookie_Manager::is_active()) {
            return $template_id;
        }

        if (!self::is_flow_page()) {
            return $template_id;
        }

        $landing_id = self::get_landing_template_id($location);

        if (!$landing_id) {
            return $template_id;
        }

        return $landing_id;
    }

    /**
     * Fallback: Override templates via Elementor's Locations Manager API.
     *
     * Use this if the filter-based approach doesn't work with the installed
     * Elementor Pro version. Called on 'template_redirect' from module.php.
     */
    public static function maybe_swap_via_locations_manager(): void {
        if (!Cookie_Manager::is_active()) {
            return;
        }

        if (!self::is_flow_page()) {
            return;
        }

        if (!class_exists('\ElementorPro\Plugin')) {
            return;
        }

        $theme_builder = \ElementorPro\Plugin::instance()->modules_manager->get_modules('theme-builder');
        if (!$theme_builder) {
            return;
        }

        $locations_manager = $theme_builder->get_locations_manager();
        if (!$locations_manager) {
            return;
        }

        foreach (['header', 'footer'] as $location) {
            $landing_id = self::get_landing_template_id($location);
            if (!$landing_id) {
                continue;
            }

            // Get currently assigned documents for this location
            $documents = $locations_manager->get_documents_for_location($location);

            // Remove existing documents from the location
            foreach ($documents as $document) {
                $locations_manager->remove_doc_from_location($location, $document->get_id());
            }

            // Add the landing template
            $locations_manager->add_doc_to_location($location, $landing_id);
        }
    }

    private static function is_flow_page(): bool {
        foreach (self::FLOW_PAGES as $slug) {
            if (is_page($slug)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get the landing-specific Elementor template ID for a given location.
     *
     * @param string $location 'header' or 'footer'
     * @return int|null Template post ID or null if not configured.
     */
    private static function get_landing_template_id(string $location): ?int {
        $config_key = 'elementor.landing_' . $location . '_id';

        if (function_exists('shaped_brand')) {
            $id = shaped_brand($config_key, null);
            if ($id) {
                return (int) $id;
            }
        }

        $option_key = 'shaped_landing_' . $location . '_id';
        $id = get_option($option_key, null);
        if ($id) {
            return (int) $id;
        }

        return null;
    }
}
