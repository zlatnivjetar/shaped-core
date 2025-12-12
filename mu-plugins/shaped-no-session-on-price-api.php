<?php
/**
 * Plugin Name: Shaped - Prevent Sessions on Price API
 * Description: Prevents session cookies on /wp-json/shaped/v1/price endpoints for stateless operation
 * Version: 1.0.0
 * Author: Shaped Systems
 *
 * This must-use plugin runs before all other plugins to prevent session initialization
 * on the price API endpoints, ensuring stateless operation and better caching.
 *
 * Installation: Copy this file to wp-content/mu-plugins/
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('mphb_wp_session_start', function() {
    if (!empty($_SERVER['REQUEST_URI']) && 
        strpos($_SERVER['REQUEST_URI'], '/wp-json/shaped/v1/price') !== false) {
        
        // Prevent the session cookie from being set
        if (!headers_sent()) {
            remove_action('shutdown', '\MPHB\Libraries\WP_SessionManager\wp_session_write_close');
        }
        
        // Return a dummy session instance that doesn't write cookies
        // This prevents MotoPress from trying to create the session
        return false;
    }
}, 1); // Priority 1 to run before anything else

/**
 * Additionally, filter the WP_Session singleton initialization
 */
add_filter('plugins_loaded', function() {
    if (!empty($_SERVER['REQUEST_URI']) && 
        strpos($_SERVER['REQUEST_URI'], '/wp-json/shaped/v1/price') !== false) {
        
        // Remove MotoPress's session initialization for this request
        remove_action('plugins_loaded', '\MPHB\Libraries\WP_SessionManager\wp_session_start');
    }
}, 0); // Priority 0 to run before MotoPress (which runs at default priority 10)

/**
 * Prevent session start on Shaped price endpoints
 *
 * This runs very early (before plugins_loaded) to prevent any plugin
 * from starting a session on our stateless REST endpoints.
 */
add_action('muplugins_loaded', function() {
    // Check if this is a REST request to our price endpoints
    if (empty($_SERVER['REQUEST_URI'])) {
        return;
    }

    $request_uri = $_SERVER['REQUEST_URI'];

    // Match /wp-json/shaped/v1/price or /wp-json/shaped/v1/price-html
    if (strpos($request_uri, '/wp-json/shaped/v1/price') !== false) {

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
    }
}, 1);

/**
 * Additional filter to disable session usage in WP Session Manager
 */
add_filter('wp_session_manager_use_cookie', function($use_cookie) {
    if (!empty($_SERVER['REQUEST_URI']) &&
        strpos($_SERVER['REQUEST_URI'], '/wp-json/shaped/v1/price') !== false) {
        return false;
    }
    return $use_cookie;
}, 1);
