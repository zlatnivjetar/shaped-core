<?php
/**
 * Dashboard REST API — availability endpoint.
 *
 * Exposes a read-only forward-looking inventory snapshot derived from the
 * RoomCloud data already stored in WordPress.
 *
 * Namespace : shaped/v1  (same as Shaped_Dashboard_Api)
 * Auth      : X-Shaped-API-Key header via shaped_dashboard_auth()
 *
 * Endpoints:
 *   GET /dashboard/availability   — month matrix + KPI summary
 *
 * Query params:
 *   month      YYYY-MM    (default: current month)
 *   date_from  YYYY-MM-DD (default: today)
 *   date_to    YYYY-MM-DD (default: today + 60 days)
 *
 * @package Shaped_Core
 */

if (!defined('ABSPATH')) {
    exit;
}

class Shaped_Dashboard_Availability_Api {

    const NAMESPACE = 'shaped/v1';

    /**
     * Register hooks.
     */
    public static function init(): void {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    /**
     * Register REST routes.
     */
    public static function register_routes(): void {
        register_rest_route(self::NAMESPACE, '/dashboard/availability', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_availability'],
            'permission_callback' => 'shaped_dashboard_auth',
        ]);
    }

    // =========================================================================
    // Handlers
    // =========================================================================

    /**
     * GET /dashboard/availability
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public static function get_availability(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $tz    = wp_timezone();
        $today = new DateTime('today', $tz);

        $month = $request->get_param('month') ?? $today->format('Y-m');
        if (!preg_match('/^\d{4}-\d{2}$/', (string) $month)) {
            return new WP_Error('bad_request', 'Invalid month; expected YYYY-MM.', ['status' => 400]);
        }

        $date_from = $request->get_param('date_from') ?? $today->format('Y-m-d');
        $date_to   = $request->get_param('date_to')   ?? (clone $today)->modify('+60 days')->format('Y-m-d');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date_from)
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date_to)) {
            return new WP_Error('bad_request', 'Invalid date; expected YYYY-MM-DD.', ['status' => 400]);
        }

        $result = Shaped_Dashboard_Availability_Service::get_availability(
            (string) $month,
            (string) $date_from,
            (string) $date_to
        );

        if (is_wp_error($result)) {
            return $result;
        }

        return new WP_REST_Response($result, 200);
    }
}
