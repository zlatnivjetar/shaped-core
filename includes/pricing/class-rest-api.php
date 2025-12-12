<?php
/**
 * Pricing REST API Endpoints
 *
 * Provides JSON and HTML endpoints for LLMs, bots, and assistants.
 * These endpoints are publicly accessible (no authentication required).
 *
 * @package Shaped_Core
 * @since 2.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Shaped_Pricing_Rest_Api
{
    /**
     * API namespace
     */
    const NAMESPACE = 'shaped/v1';

    /**
     * Cache TTL in seconds (default: 60 seconds)
     */
    const CACHE_TTL = 60;

    /**
     * Initialize REST API endpoints
     */
    public static function init(): void
    {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);

        // Prevent session start for our price endpoints (stateless requirement)
        add_action('init', [__CLASS__, 'prevent_session_on_price_endpoint'], 1);

        // Add cache headers to price endpoint responses
        add_filter('rest_post_dispatch', [__CLASS__, 'add_cache_headers'], 10, 3);
    }

    /**
     * Prevent session start for price endpoint requests
     *
     * Sessions create WP_SESSION_COOKIE which hurts caching and may trigger bot heuristics.
     * Our price endpoint should be completely stateless for anonymous GET requests.
     */
    public static function prevent_session_on_price_endpoint(): void
    {
        // Check if this is a REST request to our price endpoints
        if (empty($_SERVER['REQUEST_URI'])) {
            return;
        }

        $request_uri = $_SERVER['REQUEST_URI'];

        // Match /wp-json/shaped/v1/price or /wp-json/shaped/v1/price-html
        if (strpos($request_uri, '/wp-json/shaped/v1/price') !== false) {
            // Prevent any code from starting a session
            if (!defined('SHAPED_NO_SESSION')) {
                define('SHAPED_NO_SESSION', true);
            }

            // Override session_start() to prevent any plugin from starting sessions
            if (!function_exists('wp_session_start_override')) {
                function wp_session_start_override() {
                    // Do nothing - prevent session start
                    return false;
                }
            }

            // Disable WP Session Manager plugin for this request
            add_filter('wp_session_manager_use_cookie', '__return_false', 999);

            // Remove any session-related actions that may have been added
            remove_action('init', 'wp_session_manager_initialize', 1);
            remove_action('plugins_loaded', 'wp_session_manager_initialize', 1);

            // Prevent PHP sessions
            if (!headers_sent()) {
                ini_set('session.use_cookies', '0');
                ini_set('session.use_only_cookies', '0');
                ini_set('session.cache_limiter', '');
            }
        }
    }

    /**
     * Add cache control headers to price endpoint responses
     *
     * @param WP_HTTP_Response $response Response object
     * @param WP_REST_Server   $server   Server instance
     * @param WP_REST_Request  $request  Request object
     * @return WP_HTTP_Response Modified response
     */
    public static function add_cache_headers($response, $server, $request)
    {
        // Only apply to our price endpoints
        $route = $request->get_route();
        if (strpos($route, '/shaped/v1/price') !== 0) {
            return $response;
        }

        // Add cache control headers
        $response->header('Cache-Control', 'public, max-age=' . self::CACHE_TTL);
        $response->header('Vary', 'Accept-Encoding');

        // Keep noindex for SEO (price data shouldn't be indexed)
        $response->header('X-Robots-Tag', 'noindex');

        // Add cache status header for debugging
        $cache_key = self::get_cache_key($request);
        $cached = get_transient($cache_key);
        $response->header('X-Shaped-Cache', $cached !== false ? 'HIT' : 'MISS');

        return $response;
    }

    /**
     * Register REST routes
     */
    public static function register_routes(): void
    {
        // JSON endpoint: /wp-json/shaped/v1/price
        register_rest_route(self::NAMESPACE, '/price', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_price_json'],
            'permission_callback' => '__return_true', // Public endpoint
            'args'                => self::get_endpoint_args(),
        ]);

        // HTML endpoint: /wp-json/shaped/v1/price-html
        register_rest_route(self::NAMESPACE, '/price-html', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_price_html'],
            'permission_callback' => '__return_true', // Public endpoint
            'args'                => self::get_endpoint_args(),
        ]);
    }

    /**
     * Get endpoint argument definitions
     *
     * @return array Argument definitions for REST API
     */
    private static function get_endpoint_args(): array
    {
        return [
            'checkin' => [
                'required'          => true,
                'type'              => 'string',
                'format'            => 'date',
                'description'       => 'Check-in date in Y-m-d format (e.g., 2025-12-19)',
                'validate_callback' => function($param) {
                    return self::validate_date($param);
                },
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'checkout' => [
                'required'          => true,
                'type'              => 'string',
                'format'            => 'date',
                'description'       => 'Check-out date in Y-m-d format (e.g., 2025-12-20)',
                'validate_callback' => function($param) {
                    return self::validate_date($param);
                },
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'adults' => [
                'required'          => false,
                'type'              => 'integer',
                'default'           => 2,
                'minimum'           => 1,
                'maximum'           => 10,
                'description'       => 'Number of adults (default: 2)',
                'sanitize_callback' => 'absint',
            ],
            'children' => [
                'required'          => false,
                'type'              => 'integer',
                'default'           => 0,
                'minimum'           => 0,
                'maximum'           => 10,
                'description'       => 'Number of children (default: 0)',
                'sanitize_callback' => 'absint',
            ],
            'room_type' => [
                'required'          => false,
                'type'              => 'string',
                'description'       => 'Specific room type slug (optional, returns best available if omitted)',
                'sanitize_callback' => 'sanitize_title',
            ],
        ];
    }

    /**
     * Validate date parameter
     *
     * @param string $date Date string to validate
     * @return bool True if valid
     */
    private static function validate_date(string $date): bool
    {
        // Strict Y-m-d format validation
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }

        $parsed = date_parse($date);
        return $parsed !== false &&
               $parsed['error_count'] === 0 &&
               checkdate($parsed['month'], $parsed['day'], $parsed['year']);
    }

    /**
     * Validate date range (checkout must be after checkin)
     *
     * @param string $checkin  Check-in date
     * @param string $checkout Check-out date
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    private static function validate_date_range(string $checkin, string $checkout)
    {
        $checkin_time = strtotime($checkin);
        $checkout_time = strtotime($checkout);

        if ($checkout_time <= $checkin_time) {
            return new WP_Error(
                'invalid_date_range',
                'Check-out date must be after check-in date',
                ['status' => 400]
            );
        }

        // Also validate that dates are not too far in the past or future
        $now = time();
        $max_future = strtotime('+2 years');

        if ($checkin_time < $now - 86400) { // Allow today
            return new WP_Error(
                'invalid_checkin_date',
                'Check-in date cannot be in the past',
                ['status' => 400]
            );
        }

        if ($checkin_time > $max_future) {
            return new WP_Error(
                'invalid_checkin_date',
                'Check-in date is too far in the future',
                ['status' => 400]
            );
        }

        return true;
    }

    /**
     * Check optional authentication
     *
     * If SHAPED_PRICE_API_REQUIRE_KEY is enabled, validates the provided key.
     *
     * @param WP_REST_Request $request Request object
     * @return bool|WP_Error True if authorized, WP_Error if unauthorized
     */
    private static function check_authentication(WP_REST_Request $request)
    {
        // Check if authentication is required
        if (!defined('SHAPED_PRICE_API_REQUIRE_KEY') || !SHAPED_PRICE_API_REQUIRE_KEY) {
            return true; // Public access
        }

        // Get the required key from config
        $required_key = defined('SHAPED_PRICE_API_KEY') ? SHAPED_PRICE_API_KEY : '';

        if (empty($required_key)) {
            error_log('Shaped Pricing API: SHAPED_PRICE_API_REQUIRE_KEY is enabled but SHAPED_PRICE_API_KEY is not set');
            return true; // Fail open if misconfigured
        }

        // Check for key in header or query param
        $provided_key = $request->get_header('X-Shaped-Key') ?? $request->get_param('key');

        if (empty($provided_key) || !hash_equals($required_key, $provided_key)) {
            return new WP_Error(
                'unauthorized',
                'Invalid or missing API key',
                ['status' => 401]
            );
        }

        return true;
    }

    /**
     * Get cache key for a request
     *
     * Includes all parameters that affect the price calculation.
     *
     * @param WP_REST_Request $request Request object
     * @return string Cache key
     */
    private static function get_cache_key(WP_REST_Request $request): string
    {
        $params = [
            'checkin'   => $request->get_param('checkin'),
            'checkout'  => $request->get_param('checkout'),
            'adults'    => $request->get_param('adults') ?? 2,
            'children'  => $request->get_param('children') ?? 0,
            'room_type' => $request->get_param('room_type') ?? '',
            // Include site language/currency if available
            'locale'    => get_locale(),
        ];

        return 'shaped_price_' . md5(serialize($params));
    }

    /**
     * JSON endpoint callback
     *
     * Returns structured pricing data in JSON format
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object
     */
    public static function get_price_json(WP_REST_Request $request)
    {
        // Check authentication (if required)
        $auth_check = self::check_authentication($request);
        if (is_wp_error($auth_check)) {
            return $auth_check;
        }

        // Validate date range
        $range_check = self::validate_date_range(
            $request->get_param('checkin'),
            $request->get_param('checkout')
        );
        if (is_wp_error($range_check)) {
            return $range_check;
        }

        // Check cache first
        $cache_key = self::get_cache_key($request);
        $cached_result = get_transient($cache_key);

        if ($cached_result !== false) {
            // Return cached response
            return new WP_REST_Response($cached_result, 200);
        }

        try {
            // Get fresh pricing result
            $result = self::get_pricing_result($request);
            $response_data = $result->to_array();

            // Cache the result
            set_transient($cache_key, $response_data, self::CACHE_TTL);

            return new WP_REST_Response($response_data, 200);

        } catch (InvalidArgumentException $e) {
            return new WP_Error(
                'invalid_request',
                $e->getMessage(),
                ['status' => 400]
            );
        } catch (Exception $e) {
            // Log error
            error_log(sprintf(
                'Shaped Pricing API Error: %s (checkin: %s, checkout: %s)',
                $e->getMessage(),
                $request->get_param('checkin'),
                $request->get_param('checkout')
            ));

            return new WP_Error(
                'pricing_unavailable',
                'Pricing service temporarily unavailable: ' . $e->getMessage(),
                ['status' => 503]
            );
        }
    }

    /**
     * HTML endpoint callback
     *
     * Returns human-readable sentence in HTML format
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object
     */
    public static function get_price_html(WP_REST_Request $request)
    {
        // Check authentication (if required)
        $auth_check = self::check_authentication($request);
        if (is_wp_error($auth_check)) {
            return $auth_check;
        }

        // Validate date range
        $range_check = self::validate_date_range(
            $request->get_param('checkin'),
            $request->get_param('checkout')
        );
        if (is_wp_error($range_check)) {
            return $range_check;
        }

        // Check cache first (different cache key for HTML vs JSON)
        $cache_key = self::get_cache_key($request) . '_html';
        $cached_html = get_transient($cache_key);

        if ($cached_html !== false) {
            // Return cached response
            $response = new WP_REST_Response($cached_html, 200);
            $response->header('Content-Type', 'text/html; charset=utf-8');
            return $response;
        }

        try {
            // Get fresh pricing result
            $result = self::get_pricing_result($request);

            // Generate HTML sentence
            $html = $result->to_html();

            // Cache the result
            set_transient($cache_key, $html, self::CACHE_TTL);

            // Return as HTML response
            $response = new WP_REST_Response($html, 200);
            $response->header('Content-Type', 'text/html; charset=utf-8');

            return $response;

        } catch (InvalidArgumentException $e) {
            return new WP_Error(
                'invalid_request',
                $e->getMessage(),
                ['status' => 400]
            );
        } catch (Exception $e) {
            // Log error
            error_log(sprintf(
                'Shaped Pricing API Error: %s (checkin: %s, checkout: %s)',
                $e->getMessage(),
                $request->get_param('checkin'),
                $request->get_param('checkout')
            ));

            return new WP_Error(
                'pricing_unavailable',
                'Pricing service temporarily unavailable: ' . $e->getMessage(),
                ['status' => 503]
            );
        }
    }

    /**
     * Get pricing result from service
     *
     * @param WP_REST_Request $request Request object
     * @return Shaped_Price_Result Pricing result
     * @throws Exception If service unavailable or pricing fails
     */
    private static function get_pricing_result(WP_REST_Request $request): Shaped_Price_Result
    {
        // Get pricing service
        $service = shaped_pricing_service();

        if ($service === null || !$service->is_ready()) {
            throw new Exception('Pricing service is not available');
        }

        // Build request parameters
        $params = [
            'checkin'        => $request->get_param('checkin'),
            'checkout'       => $request->get_param('checkout'),
            'adults'         => $request->get_param('adults') ?? 2,
            'children'       => $request->get_param('children') ?? 0,
            'room_type_slug' => $request->get_param('room_type'),
        ];

        // Get quote from service
        return $service->quote($params);
    }

    /**
     * Get endpoint documentation for developers
     *
     * @return array Documentation array
     */
    public static function get_documentation(): array
    {
        $base_url = get_rest_url(null, self::NAMESPACE);

        return [
            'endpoints' => [
                'json' => [
                    'url'         => $base_url . '/price',
                    'method'      => 'GET',
                    'description' => 'Returns structured pricing data in JSON format',
                    'parameters'  => [
                        'checkin'   => 'required, string, Y-m-d format (e.g., 2025-12-19)',
                        'checkout'  => 'required, string, Y-m-d format (e.g., 2025-12-20)',
                        'adults'    => 'optional, integer, default: 2, min: 1, max: 10',
                        'children'  => 'optional, integer, default: 0, min: 0, max: 10',
                        'room_type' => 'optional, string, room type slug',
                    ],
                    'example' => $base_url . '/price?checkin=2025-12-19&checkout=2025-12-20&adults=2',
                ],
                'html' => [
                    'url'         => $base_url . '/price-html',
                    'method'      => 'GET',
                    'description' => 'Returns human-readable sentence in HTML format',
                    'parameters'  => [
                        'checkin'   => 'required, string, Y-m-d format (e.g., 2025-12-19)',
                        'checkout'  => 'required, string, Y-m-d format (e.g., 2025-12-20)',
                        'adults'    => 'optional, integer, default: 2, min: 1, max: 10',
                        'children'  => 'optional, integer, default: 0, min: 0, max: 10',
                        'room_type' => 'optional, string, room type slug',
                    ],
                    'example' => $base_url . '/price-html?checkin=2025-12-19&checkout=2025-12-20&adults=2',
                ],
            ],
            'rate_limits' => [
                'recommended' => '60 requests per minute per IP',
                'implementation' => 'Configure at infrastructure level (WAF, nginx, Cloudflare)',
            ],
            'caching' => [
                'ttl' => '5 minutes',
                'note' => 'Results are cached server-side for performance',
            ],
        ];
    }
}
