<?php
/**
 * Landing Flow Detector
 *
 * Detects whether the current request is part of the landing page booking
 * flow (/book → /search-results → /checkout) and exposes a static flag
 * for other components to check.
 *
 * Also injects hidden `flow=landing` fields into MPHB search and checkout
 * forms so the parameter propagates through the entire flow.
 *
 * @package Shaped_Core
 * @subpackage Landing_Flow
 */

if (!defined('ABSPATH')) {
    exit;
}

class Shaped_Landing_Flow_Detector {

    /** @var bool Whether the current request is within the landing flow */
    private static bool $is_landing_flow = false;

    /**
     * Initialize detection and form injection hooks.
     */
    public static function init(): void {
        self::detect();

        if (self::$is_landing_flow) {
            // Inject hidden field into MPHB search form (GET → /search-results)
            add_action('mphb_sc_search_before_form', [__CLASS__, 'inject_search_form_field']);

            // Inject hidden field into room card checkout forms (POST → /checkout)
            add_action('shaped_checkout_form_fields', [__CLASS__, 'render_hidden_field']);
        }
    }

    /**
     * Determine if the current request is part of the landing flow.
     *
     * - /book page: always considered landing flow origin
     * - /search-results: landing flow if `flow=landing` is in GET params
     * - /checkout: landing flow if `flow=landing` is in POST data
     */
    private static function detect(): void {
        // Origin page: /book is always the landing flow entry point
        if (is_page('book')) {
            self::$is_landing_flow = true;
            return;
        }

        // Search results: carried via GET (MPHB search form uses GET)
        if (isset($_GET['flow']) && $_GET['flow'] === 'landing') {
            self::$is_landing_flow = true;
            return;
        }

        // Checkout: carried via POST (room card forms use POST)
        if (isset($_POST['flow']) && $_POST['flow'] === 'landing') {
            self::$is_landing_flow = true;
            return;
        }
    }

    /**
     * Check if the current request is within the landing flow.
     *
     * @return bool
     */
    public static function is_landing_flow(): bool {
        return self::$is_landing_flow;
    }

    /**
     * Output a hidden input field with flow=landing.
     * Hooked to MPHB search form so the value travels in GET params.
     */
    public static function inject_search_form_field(): void {
        echo '<input type="hidden" name="flow" value="landing">';
    }

    /**
     * Output a hidden input field with flow=landing.
     * Called via the shaped_checkout_form_fields action in room card templates.
     */
    public static function render_hidden_field(): void {
        echo '<input type="hidden" name="flow" value="landing">';
    }
}
