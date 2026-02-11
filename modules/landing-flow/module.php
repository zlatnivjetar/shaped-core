<?php
/**
 * Landing Flow Module
 *
 * Tracks when a visitor enters the booking flow via the /book landing page
 * and applies landing-specific Elementor header/footer templates on
 * /search-results and /checkout pages within that same flow.
 *
 * Mechanism:
 *   1. /book page → MPHB search form gets a hidden `flow=landing` field
 *   2. /search-results → detects `flow=landing` in GET, propagates it in
 *      room card checkout POST forms
 *   3. /checkout → detects `flow=landing` in POST
 *   4. Elementor Pro's template resolution is filtered to swap header/footer
 *      template IDs when the landing flow is active.
 *
 * Template IDs are configured via wp_options (admin UI) or wp-config.php
 * constants for easy per-site setup.
 *
 * @package Shaped_Core
 * @subpackage Landing_Flow
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SHAPED_LANDING_FLOW_DIR', __DIR__ . '/');

require_once SHAPED_LANDING_FLOW_DIR . 'class-flow-detector.php';
require_once SHAPED_LANDING_FLOW_DIR . 'class-template-swap.php';

// Initialize on 'wp' so page context is available
add_action('wp', function () {
    Shaped_Landing_Flow_Detector::init();
    Shaped_Landing_Template_Swap::init();
});
