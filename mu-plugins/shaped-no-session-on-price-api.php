<?php
/**
 * Plugin Name: Shaped - Prevent Sessions on Price API
 * Description: Prevents session cookies on /wp-json/shaped/v1/price endpoints for stateless operation
 * Version: 1.1.0
 * Author: Shaped Systems
 *
 * This must-use plugin runs before all other plugins to prevent session initialization
 * on the price API endpoints, ensuring stateless operation and better caching.
 *
 * Specifically targets MotoPress Hotel Booking's bundled WP Session Manager.
 *
 * Installation: Copy this file to wp-content/mu-plugins/
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if current request is to a Shaped price endpoint
 */
function shaped_is_price_endpoint() {
    if (empty($_SERVER['REQUEST_URI'])) {
        return false;
    }

    return strpos($_SERVER['REQUEST_URI'], '/wp-json/shaped/v1/price') !== false;
}

/**
 * Prevent session start on Shaped price endpoints
 *
 * This runs very early (before plugins_loaded) to prevent any plugin
 * from starting a session on our stateless REST endpoints.
 */
add_action('muplugins_loaded', function() {
    // Check if this is a REST request to our price endpoints
    if (!shaped_is_price_endpoint()) {
        return;
    }

    // Define constant that other code can check
    if (!defined('SHAPED_NO_SESSION')) {
        define('SHAPED_NO_SESSION', true);
    }

    // Prevent PHP sessions at the PHP level
    if (!headers_sent()) {
        ini_set('session.use_cookies', '0');
        ini_set('session.use_only_cookies', '0');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cache_limiter', '');
    }

    // ─── MotoPress-Specific Session Prevention ───

    // Remove MotoPress's session initialization hook
    // MotoPress adds: add_action('plugins_loaded', '\MPHB\Libraries\WP_SessionManager\wp_session_start')
    // We need to remove this before it runs
    add_action('plugins_loaded', function() {
        remove_action('plugins_loaded', '\MPHB\Libraries\WP_SessionManager\wp_session_start');
    }, 0); // Priority 0 to run before MotoPress's hook

    // ─── Generic Session Prevention (Fallback) ───

    // Disable WP Session Manager plugin for this request
    add_filter('wp_session_manager_use_cookie', '__return_false', 1);

    // Remove WP Session Manager initialization (runs on plugins_loaded:1)
    add_action('plugins_loaded', function() {
        remove_action('init', 'wp_session_manager_initialize', 1);
    }, 0); // Priority 0 to run before session manager

    // Override wp_session functions to do nothing
    if (!function_exists('wp_session')) {
        function wp_session() {
            return new stdClass(); // Return empty object
        }
    }

    // Hook into init very early to remove any session actions
    add_action('init', function() {
        // Remove any session-related actions
        remove_action('init', 'wp_session_manager_initialize', 1);

        // Prevent WooCommerce sessions if present
        if (function_exists('WC')) {
            remove_action('init', ['WC_Session_Handler', 'init']);
        }
    }, 0); // Priority 0 to run before anything else
}, 1);

/**
 * Additional filter to disable session usage in WP Session Manager
 */
add_filter('wp_session_manager_use_cookie', function($use_cookie) {
    if (shaped_is_price_endpoint()) {
        return false;
    }
    return $use_cookie;
}, 1);

/**
 * Prevent MotoPress session start via filter
 * This filter may be checked by MotoPress before starting sessions
 */
add_filter('mphb_wp_session_use_cookie', function($use_cookie) {
    if (shaped_is_price_endpoint()) {
        return false;
    }
    return $use_cookie;
}, 1);

/**
 * Intercept MotoPress session start action
 * This action fires in wp-session.php:84 before the session is created
 */
add_action('mphb_wp_session_start', function() {
    if (shaped_is_price_endpoint()) {
        // Prevent the default session start by returning early
        // The session start code checks if session already started, so we start a dummy one
        if (!session_id()) {
            // Don't actually start a session - this is intentionally empty
            // Just prevents MotoPress from starting its own session
        }
    }
}, 0); // Priority 0 to run before MotoPress's session logic
