<?php
/**
 * Dashboard REST API bootstrap.
 *
 * Shared authentication and health endpoint for the external dashboard app.
 *
 * @package Shaped_Core
 */

if (!defined('ABSPATH')) {
    exit;
}

class Shaped_Dashboard_Api
{
    /**
     * REST API namespace.
     */
    const NAMESPACE = 'shaped/v1';

    /**
     * Register dashboard routes.
     */
    public static function init(): void
    {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    /**
     * Shared permission callback for dashboard endpoints.
     *
     * @param WP_REST_Request $request Request object.
     * @return true|WP_Error
     */
    public static function authorize_request(WP_REST_Request $request)
    {
        $api_key = $request->get_header('X-Shaped-API-Key');

        if (!self::has_configured_api_key() || empty($api_key)) {
            return new WP_Error(
                'unauthorized',
                'Missing API key',
                ['status' => 401]
            );
        }

        if (!hash_equals(SHAPED_DASHBOARD_API_KEY, $api_key)) {
            return new WP_Error(
                'forbidden',
                'Invalid API key',
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * Register dashboard API routes.
     */
    public static function register_routes(): void
    {
        register_rest_route(self::NAMESPACE, '/dashboard/health', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_health'],
            'permission_callback' => 'shaped_dashboard_auth',
        ]);
    }

    /**
     * Dashboard health endpoint.
     *
     * @return WP_REST_Response
     */
    public static function get_health(): WP_REST_Response
    {
        return new WP_REST_Response([
            'status'         => 'ok',
            'plugin_version' => SHAPED_VERSION,
            'site_name'      => get_bloginfo('name'),
            'timestamp'      => gmdate('c'),
        ], 200);
    }

    /**
     * Generate a dashboard API key for wp-config.php.
     *
     * The generated key is shown once in the admin UI and is not stored.
     */
    public static function generate_api_key(): string
    {
        try {
            $random = bin2hex(random_bytes(16));
        } catch (Exception $exception) {
            $random = md5(uniqid((string) wp_rand(), true));
        }

        return 'sk_shaped_' . strtolower($random);
    }

    /**
     * Whether the dashboard API key is configured in wp-config.php.
     */
    public static function has_configured_api_key(): bool
    {
        return defined('SHAPED_DASHBOARD_API_KEY')
            && is_string(SHAPED_DASHBOARD_API_KEY)
            && SHAPED_DASHBOARD_API_KEY !== '';
    }
}

if (!function_exists('shaped_dashboard_auth')) {
    /**
     * Shared permission callback wrapper for dashboard endpoints.
     *
     * @param WP_REST_Request $request Request object.
     * @return true|WP_Error
     */
    function shaped_dashboard_auth(WP_REST_Request $request)
    {
        return Shaped_Dashboard_Api::authorize_request($request);
    }
}
