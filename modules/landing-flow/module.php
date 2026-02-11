<?php
/**
 * Landing Flow Module
 *
 * Swaps Elementor Pro Theme Builder header/footer templates when the user
 * enters the booking flow from the /book landing page. Uses a cookie to
 * track flow origin across /search-results and /checkout pages.
 *
 * Configuration (mu-plugins/shaped-client-config.php → elementor section):
 *   'landing_header_id' => 123,  // Elementor template post ID
 *   'landing_footer_id' => 456,  // Elementor template post ID
 *
 * Or via WP options: shaped_landing_header_id, shaped_landing_footer_id
 *
 * Caching note: /search-results and /checkout must be excluded from page
 * cache for this to work (they contain dynamic booking data anyway).
 *
 * @package Shaped_Core
 * @subpackage Landing_Flow
 */

namespace Shaped\Modules\LandingFlow;

if (!defined('ABSPATH')) {
    exit;
}

define('SHAPED_LANDING_FLOW_DIR', __DIR__ . '/');

require_once SHAPED_LANDING_FLOW_DIR . 'class-cookie-manager.php';
require_once SHAPED_LANDING_FLOW_DIR . 'class-template-swap.php';

/**
 * Cookie management — runs on 'wp' after query is parsed so is_page() works.
 */
add_action('wp', function () {
    Cookie_Manager::maybe_set_cookie();
    Cookie_Manager::maybe_clear_cookie();
});

/**
 * Elementor Pro template override.
 *
 * Primary approach: filter on template_id (Elementor Pro 3.8+).
 * Fallback: Locations Manager API via template_redirect.
 *
 * Both are registered; the filter is a no-op if it doesn't fire,
 * and the fallback checks if the swap already happened.
 */
add_filter(
    'elementor/theme/get_location_templates/template_id',
    [Template_Swap::class, 'maybe_swap_template'],
    10,
    2
);

// Fallback for Elementor Pro versions without the template_id filter
add_action('template_redirect', [Template_Swap::class, 'maybe_swap_via_locations_manager']);
