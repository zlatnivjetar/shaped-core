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
     * Initialize REST API endpoints
     */
    public static function init(): void
    {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
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
        $parsed = date_parse($date);
        return $parsed !== false &&
               $parsed['error_count'] === 0 &&
               checkdate($parsed['month'], $parsed['day'], $parsed['year']);
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
        try {
            $result = self::get_pricing_result($request);

            return new WP_REST_Response(
                $result->to_array(),
                200
            );
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
        try {
            $result = self::get_pricing_result($request);

            // Generate HTML sentence
            $html = $result->to_html();

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
