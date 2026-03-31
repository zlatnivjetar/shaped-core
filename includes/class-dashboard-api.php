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
        add_filter('rest_post_dispatch', [__CLASS__, 'add_no_cache_headers'], 10, 3);
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

    /**
     * Prevent caching for dashboard API responses.
     *
     * Dashboard endpoints are authenticated via a request header, so shared caches
     * must never reuse an authorized response for a different request.
     *
     * @param WP_HTTP_Response $response Response object.
     * @param WP_REST_Server   $server   Server instance.
     * @param WP_REST_Request  $request  Request object.
     * @return WP_HTTP_Response
     */
    public static function add_no_cache_headers($response, $server, $request)
    {
        $route = $request->get_route();
        if (strpos($route, '/shaped/v1/dashboard/') !== 0) {
            return $response;
        }

        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
        $response->header('Pragma', 'no-cache');
        $response->header('Expires', '0');
        $response->header('Vary', 'Origin, X-Shaped-API-Key');
        $response->header('X-Robots-Tag', 'noindex');

        return $response;
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
