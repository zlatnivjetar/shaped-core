<?php
/**
 * Dashboard data service.
 *
 * Revenue, booking, and review serializers for the external dashboard app.
 *
 * @package Shaped_Core
 */

if (!defined('ABSPATH')) {
    exit;
}

class Shaped_Dashboard_Data_Service
{
    /**
     * Default bookings page size.
     */
    const DEFAULT_PER_PAGE = 25;

    /**
     * Maximum bookings page size.
     */
    const MAX_PER_PAGE = 100;

    /**
     * Revenue summary response.
     */
    public static function get_revenue_response(): WP_REST_Response
    {
        $month_bounds = self::get_current_month_bounds();

        return new WP_REST_Response([
            'currency'     => self::get_currency_code(),
            'month_basis'  => 'payment_collected_date',
            'collected'    => [
                'month'    => self::get_revenue_total('collected', $month_bounds['start'], $month_bounds['end']),
                'all_time' => self::get_revenue_total('collected'),
                'basis'    => 'payment_collected_date',
            ],
            'pending'      => [
                'month'    => self::get_revenue_total('pending', $month_bounds['start'], $month_bounds['end']),
                'all_time' => self::get_revenue_total('pending'),
                'basis'    => 'charge_date',
            ],
            'generated_at' => gmdate('c'),
        ], 200);
    }

    /**
     * Collected revenue trend response.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public static function get_collected_revenue_trend_response(WP_REST_Request $request)
    {
        $args = self::get_collected_revenue_trend_query_args($request);
        if (is_wp_error($args)) {
            return $args;
        }

        $items = self::query_collected_revenue_trend($args);

        return new WP_REST_Response([
            'currency'     => self::get_currency_code(),
            'date_basis'   => 'payment_collected_date',
            'filters'      => [
                'collected_from' => $args['collected_from'],
                'collected_to'   => $args['collected_to'],
            ],
            'items'        => $items,
            'generated_at' => gmdate('c'),
        ], 200);
    }

    /**
     * Pending revenue trend response.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public static function get_pending_revenue_trend_response(WP_REST_Request $request)
    {
        $args = self::get_pending_revenue_trend_query_args($request);
        if (is_wp_error($args)) {
            return $args;
        }

        $items = self::query_pending_revenue_trend($args);

        return new WP_REST_Response([
            'currency'     => self::get_currency_code(),
            'date_basis'   => 'charge_date',
            'filters'      => [
                'charge_from' => $args['charge_from'],
                'charge_to'   => $args['charge_to'],
            ],
            'items'        => $items,
            'generated_at' => gmdate('c'),
        ], 200);
    }

    /**
     * Bookings list response.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public static function get_bookings_response(WP_REST_Request $request)
    {
        $args = self::get_bookings_query_args($request);
        if (is_wp_error($args)) {
            return $args;
        }

        $result = self::query_bookings($args);
        $total_pages = $result['total_items'] > 0
            ? (int) ceil($result['total_items'] / $args['per_page'])
            : 0;

        return new WP_REST_Response([
            'currency'     => self::get_currency_code(),
            'items'        => array_map([__CLASS__, 'build_booking_list_item'], $result['rows']),
            'pagination'   => [
                'page'        => $args['page'],
                'per_page'    => $args['per_page'],
                'total_items' => $result['total_items'],
                'total_pages' => $total_pages,
            ],
            'filters'      => [
                'status'         => $args['statuses'],
                'payment_status' => $args['payment_statuses'],
                'check_in_from'  => $args['check_in_from'],
                'check_in_to'    => $args['check_in_to'],
                'check_out_from' => $args['check_out_from'],
                'check_out_to'   => $args['check_out_to'],
                'booked_from'    => $args['booked_from'],
                'booked_to'      => $args['booked_to'],
                'search'         => $args['search'],
            ],
            'sort'         => [
                'field' => $args['sort'],
                'order' => strtolower($args['order']),
            ],
            'generated_at' => gmdate('c'),
        ], 200);
    }

    /**
     * Booking detail response.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public static function get_booking_detail_response(WP_REST_Request $request)
    {
        $booking_id = absint($request['id']);
        if ($booking_id <= 0) {
            return new WP_Error(
                'invalid_booking_id',
                'Invalid booking ID.',
                ['status' => 400]
            );
        }

        $summary = self::get_booking_summary_by_id($booking_id);
        if (!$summary) {
            return new WP_Error(
                'booking_not_found',
                'Booking not found.',
                ['status' => 404]
            );
        }

        $booking = self::get_booking_entity($booking_id);
        $payment_context = self::get_payment_context($booking);
        $payment = self::build_payment_payload($summary, $payment_context);

        return new WP_REST_Response([
            'id'                 => (int) $summary['ID'],
            'currency'           => $payment['currency'],
            'booking_status'     => self::normalize_booking_status((string) $summary['booking_status_raw']),
            'booking_status_raw' => (string) $summary['booking_status_raw'],
            'payment_status'     => $payment['status'],
            'booked_at'          => self::format_post_datetime(
                (string) $summary['booked_at'],
                (string) $summary['booked_at_gmt']
            ),
            'guest'              => self::build_guest_payload($booking_id, $booking, $summary),
            'stay'               => [
                'check_in'  => self::nullable_string($summary['check_in']),
                'check_out' => self::nullable_string($summary['check_out']),
                'nights'    => self::calculate_nights(
                    (string) $summary['check_in'],
                    (string) $summary['check_out']
                ),
            ],
            'room'               => self::build_room_payload($booking_id, $booking),
            'payment'            => $payment,
            'stripe'             => self::build_stripe_payload($summary, $payment),
            'timeline'           => self::build_timeline_payload($summary, $payment),
            'generated_at'       => gmdate('c'),
        ], 200);
    }

    /**
     * Reviews list response.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public static function get_reviews_response(WP_REST_Request $request)
    {
        $args = self::get_reviews_query_args($request);
        if (is_wp_error($args)) {
            return $args;
        }

        // Summary runs over all reviews (unfiltered) and is cached separately.
        $summary = self::query_reviews_summary();

        // Filtered, sorted, and paginated in SQL — no full PHP load.
        $result      = self::query_reviews_filtered($args);
        $total_items = $result['total'];
        $total_pages = $total_items > 0
            ? (int) ceil($total_items / $args['per_page'])
            : 0;
        $items = array_map([__CLASS__, 'build_review_list_item'], $result['rows']);

        return new WP_REST_Response([
            'items'        => $items,
            'pagination'   => [
                'page'        => $args['page'],
                'per_page'    => $args['per_page'],
                'total_items' => $total_items,
                'total_pages' => $total_pages,
            ],
            'filters'      => [
                'provider'         => $args['providers'],
                'min_rating'       => $args['min_rating'],
                'review_date_from' => $args['review_date_from'],
                'review_date_to'   => $args['review_date_to'],
            ],
            'sort'         => [
                'field' => $args['sort'],
                'order' => strtolower($args['order']),
            ],
            'summary'      => [
                'average_rating'   => $summary['average_rating'],
                'total_reviews'    => $summary['total_reviews'],
                'filtered_reviews' => $total_items,
                'provider_counts'  => $summary['provider_counts'],
            ],
            'generated_at' => gmdate('c'),
        ], 200);
    }

    /**
     * Consolidated overview endpoint response.
     *
     * Returns a single payload containing everything the main dashboard view needs:
     * revenue cards, recent bookings, recent reviews, and queue-count previews.
     * This removes the need for the dashboard to fan out into multiple calls on load.
     *
     * Accepted params: none required.  All data uses sensible defaults.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public static function get_overview_response(WP_REST_Request $request): WP_REST_Response
    {
        $month_bounds = self::get_current_month_bounds();

        $revenue = [
            'collected' => [
                'month'    => self::get_revenue_total('collected', $month_bounds['start'], $month_bounds['end']),
                'all_time' => self::get_revenue_total('collected'),
                'basis'    => 'payment_collected_date',
            ],
            'pending'   => [
                'month'    => self::get_revenue_total('pending', $month_bounds['start'], $month_bounds['end']),
                'all_time' => self::get_revenue_total('pending'),
                'basis'    => 'charge_date',
            ],
        ];

        $recent_bookings_result = self::query_bookings([
            'page'             => 1,
            'per_page'         => 5,
            'sort'             => 'date',
            'order'            => 'DESC',
            'statuses'         => [],
            'payment_statuses' => [],
            'check_in_from'    => null,
            'check_in_to'      => null,
            'check_out_from'   => null,
            'check_out_to'     => null,
            'booked_from'      => null,
            'booked_to'        => null,
            'search'           => null,
            'id'               => 0,
        ]);

        $reviews_summary      = self::query_reviews_summary();
        $recent_reviews_result = self::query_reviews_filtered([
            'page'             => 1,
            'per_page'         => 5,
            'sort'             => 'date',
            'order'            => 'DESC',
            'providers'        => [],
            'min_rating'       => null,
            'review_date_from' => null,
            'review_date_to'   => null,
        ]);

        return new WP_REST_Response([
            'currency'          => self::get_currency_code(),
            'revenue'           => $revenue,
            'recent_bookings'   => [
                'total' => $recent_bookings_result['total_items'],
                'items' => array_map([__CLASS__, 'build_booking_list_item'], $recent_bookings_result['rows']),
            ],
            'recent_reviews'    => [
                'total'          => $reviews_summary['total_reviews'],
                'average_rating' => $reviews_summary['average_rating'],
                'items'          => array_map([__CLASS__, 'build_review_list_item'], $recent_reviews_result['rows']),
            ],
            'pending_charges'   => self::get_pending_charges_summary(),
            'upcoming_arrivals' => self::get_upcoming_arrivals_summary(),
            'health'            => [
                'status'         => 'ok',
                'plugin_version' => defined('SHAPED_VERSION') ? SHAPED_VERSION : null,
                'site_name'      => get_bloginfo('name'),
            ],
            'generated_at'      => gmdate('c'),
        ], 200);
    }

    /**
     * Count and total confirmed bookings with an unprocessed pending charge due today or later.
     */
    private static function get_pending_charges_summary(): array
    {
        $cache_key = 'shaped_overview_pending_charges_v1';
        $cached    = wp_cache_get($cache_key, 'shaped_dashboard');
        if (false !== $cached && is_array($cached)) {
            return $cached;
        }

        global $wpdb;

        $confirmed_statuses  = ['confirmed', 'publish', 'Confirmed'];
        $status_placeholders = implode(', ', array_fill(0, count($confirmed_statuses), '%s'));
        $today               = current_time('Y-m-d');
        $params              = array_merge($confirmed_statuses, [$today]);

        $sql = "
            SELECT
                COUNT(*) AS count,
                COALESCE(SUM(CAST(pending_amount.meta_value AS DECIMAL(10,2))), 0) AS total_amount
            FROM {$wpdb->posts} AS p
            INNER JOIN {$wpdb->postmeta} AS pending_amount
                ON p.ID = pending_amount.post_id
                AND pending_amount.meta_key = '_stripe_pending_amount'
            LEFT JOIN {$wpdb->postmeta} AS scheduled
                ON p.ID = scheduled.post_id
                AND scheduled.meta_key = '_shaped_charge_scheduled'
            LEFT JOIN {$wpdb->postmeta} AS payment_status
                ON p.ID = payment_status.post_id
                AND payment_status.meta_key = '_shaped_payment_status'
            LEFT JOIN {$wpdb->postmeta} AS payment_mode
                ON p.ID = payment_mode.post_id
                AND payment_mode.meta_key = '_shaped_payment_mode'
            LEFT JOIN {$wpdb->postmeta} AS charge_at
                ON p.ID = charge_at.post_id
                AND charge_at.meta_key = '_shaped_charge_at'
            LEFT JOIN {$wpdb->postmeta} AS charge_date
                ON p.ID = charge_date.post_id
                AND charge_date.meta_key = '_shaped_charge_date'
            LEFT JOIN {$wpdb->postmeta} AS charged
                ON p.ID = charged.post_id
                AND charged.meta_key = '_stripe_payment_charged'
            LEFT JOIN {$wpdb->postmeta} AS charge_processed
                ON p.ID = charge_processed.post_id
                AND charge_processed.meta_key = '_shaped_charge_processed'
            WHERE p.post_type = 'mphb_booking'
                AND p.post_status IN ({$status_placeholders})
                AND CAST(pending_amount.meta_value AS DECIMAL(10,2)) > 0
                AND DATE(charge_date.meta_value) >= %s
                AND (
                    scheduled.meta_value IN ('1', 'true', 'yes')
                    OR (
                        (payment_status.meta_value = 'authorized' OR payment_mode.meta_value = 'delayed')
                        AND (
                            (charge_at.meta_value IS NOT NULL AND charge_at.meta_value <> '')
                            OR (charge_date.meta_value IS NOT NULL AND charge_date.meta_value <> '')
                        )
                    )
                )
                AND (charged.meta_value IS NULL OR charged.meta_value = '' OR charged.meta_value IN ('0', 'false', 'no'))
                AND (charge_processed.meta_value IS NULL OR charge_processed.meta_value = '' OR charge_processed.meta_value IN ('0', 'false', 'no'))
        ";

        $row = $wpdb->get_row(self::prepare_query($sql, $params), ARRAY_A);

        $result = [
            'count'        => (int) ($row['count'] ?? 0),
            'total_amount' => self::round_amount($row['total_amount'] ?? 0),
        ];

        wp_cache_set($cache_key, $result, 'shaped_dashboard', 15);

        return $result;
    }

    /**
     * Count confirmed bookings with a check-in date within the next 7 days (inclusive of today).
     */
    private static function get_upcoming_arrivals_summary(): array
    {
        $cache_key = 'shaped_overview_upcoming_arrivals_v1';
        $cached    = wp_cache_get($cache_key, 'shaped_dashboard');
        if (false !== $cached && is_array($cached)) {
            return $cached;
        }

        global $wpdb;

        $confirmed_statuses  = ['confirmed', 'publish', 'Confirmed'];
        $status_placeholders = implode(', ', array_fill(0, count($confirmed_statuses), '%s'));
        $today               = current_time('Y-m-d');
        $seven_days_out      = gmdate('Y-m-d', strtotime($today . ' +7 days'));
        $params              = array_merge($confirmed_statuses, [$today, $seven_days_out]);

        $sql = "
            SELECT COUNT(DISTINCT p.ID) AS count
            FROM {$wpdb->posts} AS p
            INNER JOIN {$wpdb->postmeta} AS check_in
                ON p.ID = check_in.post_id
                AND check_in.meta_key = '_mphb_check_in_date'
            WHERE p.post_type = 'mphb_booking'
                AND p.post_status IN ({$status_placeholders})
                AND check_in.meta_value >= %s
                AND check_in.meta_value <= %s
        ";

        $count  = (int) $wpdb->get_var(self::prepare_query($sql, $params));
        $result = ['count' => $count];

        wp_cache_set($cache_key, $result, 'shaped_dashboard', 30);

        return $result;
    }

    /**
     * Sum collected or pending revenue.
     */
    private static function get_revenue_total(
        string $type,
        ?string $date_from = null,
        ?string $date_to = null
    ): float {
        $cache_key = 'shaped_rev_v2_' . $type . '_' . ($date_from ?: 'x') . '_' . ($date_to ?: 'x');
        $cached    = wp_cache_get($cache_key, 'shaped_dashboard');
        if (false !== $cached) {
            return (float) $cached;
        }

        global $wpdb;

        if ($type === 'collected') {
            [$base_sql, $params] = self::get_collected_revenue_base_sql();
            $amount_sql = self::get_collected_revenue_amount_sql('bookings');

            $sql = "
                SELECT COALESCE(SUM({$amount_sql}), 0)
                FROM ({$base_sql}) AS bookings
                WHERE bookings.payment_status IN ('completed', 'deposit_paid')
                    AND bookings.payment_collected_date IS NOT NULL
                    AND ({$amount_sql}) > 0
            ";

            if ($date_from) {
                $sql .= ' AND bookings.payment_collected_date >= %s';
                $params[] = $date_from;
            }

            if ($date_to) {
                $sql .= ' AND bookings.payment_collected_date <= %s';
                $params[] = $date_to;
            }

            $result = self::round_amount($wpdb->get_var(self::prepare_query($sql, $params)));
            wp_cache_set($cache_key, $result, 'shaped_dashboard', 60);

            return $result;
        }

        $confirmed_statuses = ['confirmed', 'publish', 'Confirmed'];
        $status_placeholders = implode(', ', array_fill(0, count($confirmed_statuses), '%s'));
        $params = $confirmed_statuses;

        $sql = "
            SELECT COALESCE(SUM(CAST(pending_amount.meta_value AS DECIMAL(10,2))), 0)
            FROM {$wpdb->posts} AS p
            INNER JOIN {$wpdb->postmeta} AS pending_amount
                ON p.ID = pending_amount.post_id
                AND pending_amount.meta_key = '_stripe_pending_amount'
            LEFT JOIN {$wpdb->postmeta} AS scheduled
                ON p.ID = scheduled.post_id
                AND scheduled.meta_key = '_shaped_charge_scheduled'
            LEFT JOIN {$wpdb->postmeta} AS payment_status
                ON p.ID = payment_status.post_id
                AND payment_status.meta_key = '_shaped_payment_status'
            LEFT JOIN {$wpdb->postmeta} AS payment_mode
                ON p.ID = payment_mode.post_id
                AND payment_mode.meta_key = '_shaped_payment_mode'
            LEFT JOIN {$wpdb->postmeta} AS charge_at
                ON p.ID = charge_at.post_id
                AND charge_at.meta_key = '_shaped_charge_at'
            LEFT JOIN {$wpdb->postmeta} AS charge_date
                ON p.ID = charge_date.post_id
                AND charge_date.meta_key = '_shaped_charge_date'
            LEFT JOIN {$wpdb->postmeta} AS charged
                ON p.ID = charged.post_id
                AND charged.meta_key = '_stripe_payment_charged'
            LEFT JOIN {$wpdb->postmeta} AS charge_processed
                ON p.ID = charge_processed.post_id
                AND charge_processed.meta_key = '_shaped_charge_processed'
            WHERE p.post_type = 'mphb_booking'
                AND p.post_status IN ({$status_placeholders})
                AND CAST(pending_amount.meta_value AS DECIMAL(10,2)) > 0
                AND (
                    scheduled.meta_value IN ('1', 'true', 'yes')
                    OR (
                        (
                            payment_status.meta_value = 'authorized'
                            OR payment_mode.meta_value = 'delayed'
                        )
                        AND (
                            (charge_at.meta_value IS NOT NULL AND charge_at.meta_value <> '')
                            OR (charge_date.meta_value IS NOT NULL AND charge_date.meta_value <> '')
                        )
                    )
                )
                AND (
                    charged.meta_value IS NULL
                    OR charged.meta_value = ''
                    OR charged.meta_value IN ('0', 'false', 'no')
                )
                AND (
                    charge_processed.meta_value IS NULL
                    OR charge_processed.meta_value = ''
                    OR charge_processed.meta_value IN ('0', 'false', 'no')
                )
        ";

        $pending_date_sql = "DATE(COALESCE(NULLIF(charge_date.meta_value, ''), charge_at.meta_value))";
        if ($date_from) {
            $sql .= " AND {$pending_date_sql} >= %s";
            $params[] = $date_from;
        }

        if ($date_to) {
            $sql .= " AND {$pending_date_sql} <= %s";
            $params[] = $date_to;
        }

        $result = self::round_amount($wpdb->get_var(self::prepare_query($sql, $params)));
        wp_cache_set($cache_key, $result, 'shaped_dashboard', 60);

        return $result;
    }

    /**
     * Base aggregated rows for collected-revenue calculations.
     */
    private static function get_collected_revenue_base_sql(): array
    {
        global $wpdb;

        $confirmed_statuses = ['confirmed', 'publish', 'Confirmed'];
        $status_placeholders = implode(', ', array_fill(0, count($confirmed_statuses), '%s'));

        $sql = "
            SELECT
                p.ID,
                COALESCE(MAX(CASE WHEN pm.meta_key = '_shaped_payment_status' THEN pm.meta_value END), '') AS payment_status,
                COALESCE(MAX(CASE WHEN pm.meta_key = '_shaped_payment_mode' THEN pm.meta_value END), '') AS payment_mode,
                COALESCE(MAX(CASE WHEN pm.meta_key = '_shaped_charge_scheduled' THEN pm.meta_value END), '') AS charge_scheduled,
                CASE
                    WHEN NULLIF(MAX(CASE WHEN pm.meta_key = '_shaped_payment_collected_date' THEN pm.meta_value END), '') IS NOT NULL
                        THEN NULLIF(MAX(CASE WHEN pm.meta_key = '_shaped_payment_collected_date' THEN pm.meta_value END), '')
                    WHEN COALESCE(MAX(CASE WHEN pm.meta_key = '_shaped_payment_mode' THEN pm.meta_value END), '') = 'delayed'
                        OR COALESCE(MAX(CASE WHEN pm.meta_key = '_shaped_charge_scheduled' THEN pm.meta_value END), '') IN ('1', 'true', 'yes')
                        OR NULLIF(MAX(CASE WHEN pm.meta_key = '_shaped_charge_at' THEN pm.meta_value END), '') IS NOT NULL
                        OR NULLIF(MAX(CASE WHEN pm.meta_key = '_shaped_charge_date' THEN pm.meta_value END), '') IS NOT NULL
                        THEN NULL
                    ELSE DATE(p.post_date)
                END AS payment_collected_date,
                COALESCE(MAX(CASE WHEN pm.meta_key = '_shaped_payment_collected_at' THEN pm.meta_value END), '') AS payment_collected_at,
                CAST(
                    COALESCE(
                        MAX(CASE WHEN pm.meta_key = '_shaped_payment_amount' THEN pm.meta_value END),
                        MAX(CASE WHEN pm.meta_key = 'mphb_total_price' THEN pm.meta_value END),
                        '0'
                    ) AS DECIMAL(10,2)
                ) AS total_amount,
                CAST(COALESCE(MAX(CASE WHEN pm.meta_key = '_mphb_paid_amount' THEN pm.meta_value END), '0') AS DECIMAL(10,2)) AS paid_amount,
                CAST(COALESCE(MAX(CASE WHEN pm.meta_key = '_shaped_deposit_amount' THEN pm.meta_value END), '0') AS DECIMAL(10,2)) AS deposit_amount
            FROM {$wpdb->posts} AS p
            LEFT JOIN {$wpdb->postmeta} AS pm
                ON p.ID = pm.post_id
            WHERE p.post_type = 'mphb_booking'
                AND p.post_status IN ({$status_placeholders})
            GROUP BY p.ID
        ";

        return [$sql, $confirmed_statuses];
    }

    /**
     * SQL expression for the amount that was actually collected.
     */
    private static function get_collected_revenue_amount_sql(string $alias): string
    {
        return "
            CASE
                WHEN {$alias}.payment_status = 'deposit_paid' THEN
                    CASE
                        WHEN {$alias}.paid_amount > 0 THEN {$alias}.paid_amount
                        WHEN {$alias}.deposit_amount > 0 THEN {$alias}.deposit_amount
                        ELSE 0
                    END
                WHEN {$alias}.paid_amount > 0 THEN {$alias}.paid_amount
                ELSE {$alias}.total_amount
            END
        ";
    }

    /**
     * Parse and validate collected-revenue trend request args.
     *
     * @param WP_REST_Request $request Request object.
     * @return array|WP_Error
     */
    private static function get_collected_revenue_trend_query_args(WP_REST_Request $request)
    {
        $collected_from_raw = (string) ($request->get_param('collected_from') ?: $request->get_param('date_from'));
        $collected_to_raw = (string) ($request->get_param('collected_to') ?: $request->get_param('date_to'));

        $collected_from = self::parse_date_parameter($collected_from_raw, 'collected_from');
        if (is_wp_error($collected_from)) {
            return $collected_from;
        }

        $collected_to = self::parse_date_parameter($collected_to_raw, 'collected_to');
        if (is_wp_error($collected_to)) {
            return $collected_to;
        }

        if ($collected_from === null || $collected_to === null) {
            return new WP_Error(
                'invalid_parameter',
                'Both collected_from and collected_to are required. Expected YYYY-MM-DD.',
                ['status' => 400]
            );
        }

        if ($collected_from > $collected_to) {
            [$collected_from, $collected_to] = [$collected_to, $collected_from];
        }

        return [
            'collected_from' => $collected_from,
            'collected_to'   => $collected_to,
        ];
    }

    /**
     * Query grouped collected revenue by collection date.
     */
    private static function query_collected_revenue_trend(array $args): array
    {
        global $wpdb;

        [$base_sql, $params] = self::get_collected_revenue_base_sql();
        $amount_sql = self::get_collected_revenue_amount_sql('bookings');
        $params[] = $args['collected_from'];
        $params[] = $args['collected_to'];

        $sql = "
            SELECT
                bookings.payment_collected_date AS collected_date,
                COUNT(*) AS bookings,
                COALESCE(SUM({$amount_sql}), 0) AS collected_amount
            FROM ({$base_sql}) AS bookings
            WHERE bookings.payment_status IN ('completed', 'deposit_paid')
                AND bookings.payment_collected_date IS NOT NULL
                AND ({$amount_sql}) > 0
                AND bookings.payment_collected_date >= %s
                AND bookings.payment_collected_date <= %s
            GROUP BY bookings.payment_collected_date
            ORDER BY bookings.payment_collected_date ASC
        ";

        $rows = $wpdb->get_results(self::prepare_query($sql, $params), ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        return array_map(static function (array $row): array {
            return [
                'date'             => (string) $row['collected_date'],
                'bookings'         => (int) $row['bookings'],
                'collected_amount' => self::round_amount($row['collected_amount']),
            ];
        }, $rows);
    }

    /**
     * Parse and validate pending-revenue trend request args.
     *
     * @param WP_REST_Request $request Request object.
     * @return array|WP_Error
     */
    private static function get_pending_revenue_trend_query_args(WP_REST_Request $request)
    {
        $charge_from = self::parse_date_parameter(
            (string) $request->get_param('charge_from'),
            'charge_from'
        );
        if (is_wp_error($charge_from)) {
            return $charge_from;
        }

        $charge_to = self::parse_date_parameter(
            (string) $request->get_param('charge_to'),
            'charge_to'
        );
        if (is_wp_error($charge_to)) {
            return $charge_to;
        }

        if ($charge_from === null || $charge_to === null) {
            return new WP_Error(
                'invalid_parameter',
                'Both charge_from and charge_to are required. Expected YYYY-MM-DD.',
                ['status' => 400]
            );
        }

        if ($charge_from > $charge_to) {
            [$charge_from, $charge_to] = [$charge_to, $charge_from];
        }

        return [
            'charge_from' => $charge_from,
            'charge_to'   => $charge_to,
        ];
    }

    /**
     * Query grouped pending revenue by charge date.
     */
    private static function query_pending_revenue_trend(array $args): array
    {
        global $wpdb;

        $confirmed_statuses = ['confirmed', 'publish', 'Confirmed'];
        $status_placeholders = implode(', ', array_fill(0, count($confirmed_statuses), '%s'));
        $params = array_merge($confirmed_statuses, [
            $args['charge_from'],
            $args['charge_to'],
        ]);

        $sql = "
            SELECT
                charge_date.meta_value AS charge_date,
                COUNT(*) AS bookings,
                COALESCE(SUM(CAST(pending_amount.meta_value AS DECIMAL(10,2))), 0) AS pending_amount
            FROM {$wpdb->posts} AS p
            INNER JOIN {$wpdb->postmeta} AS pending_amount
                ON p.ID = pending_amount.post_id
                AND pending_amount.meta_key = '_stripe_pending_amount'
            LEFT JOIN {$wpdb->postmeta} AS scheduled
                ON p.ID = scheduled.post_id
                AND scheduled.meta_key = '_shaped_charge_scheduled'
            LEFT JOIN {$wpdb->postmeta} AS payment_status
                ON p.ID = payment_status.post_id
                AND payment_status.meta_key = '_shaped_payment_status'
            LEFT JOIN {$wpdb->postmeta} AS payment_mode
                ON p.ID = payment_mode.post_id
                AND payment_mode.meta_key = '_shaped_payment_mode'
            LEFT JOIN {$wpdb->postmeta} AS charge_at
                ON p.ID = charge_at.post_id
                AND charge_at.meta_key = '_shaped_charge_at'
            LEFT JOIN {$wpdb->postmeta} AS charge_date
                ON p.ID = charge_date.post_id
                AND charge_date.meta_key = '_shaped_charge_date'
            LEFT JOIN {$wpdb->postmeta} AS charged
                ON p.ID = charged.post_id
                AND charged.meta_key = '_stripe_payment_charged'
            LEFT JOIN {$wpdb->postmeta} AS charge_processed
                ON p.ID = charge_processed.post_id
                AND charge_processed.meta_key = '_shaped_charge_processed'
            WHERE p.post_type = 'mphb_booking'
                AND p.post_status IN ({$status_placeholders})
                AND charge_date.meta_value IS NOT NULL
                AND charge_date.meta_value <> ''
                AND DATE(charge_date.meta_value) >= %s
                AND DATE(charge_date.meta_value) <= %s
                AND CAST(pending_amount.meta_value AS DECIMAL(10,2)) > 0
                AND (
                    scheduled.meta_value IN ('1', 'true', 'yes')
                    OR (
                        (
                            payment_status.meta_value = 'authorized'
                            OR payment_mode.meta_value = 'delayed'
                        )
                        AND (
                            (charge_at.meta_value IS NOT NULL AND charge_at.meta_value <> '')
                            OR (charge_date.meta_value IS NOT NULL AND charge_date.meta_value <> '')
                        )
                    )
                )
                AND (
                    charged.meta_value IS NULL
                    OR charged.meta_value = ''
                    OR charged.meta_value IN ('0', 'false', 'no')
                )
                AND (
                    charge_processed.meta_value IS NULL
                    OR charge_processed.meta_value = ''
                    OR charge_processed.meta_value IN ('0', 'false', 'no')
                )
            GROUP BY charge_date.meta_value
            ORDER BY charge_date.meta_value ASC
        ";

        $rows = $wpdb->get_results(self::prepare_query($sql, $params), ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        return array_map(static function (array $row): array {
            return [
                'date'           => (string) $row['charge_date'],
                'bookings'       => (int) $row['bookings'],
                'pending_amount' => self::round_amount($row['pending_amount']),
            ];
        }, $rows);
    }

    /**
     * Parse and validate bookings list request args.
     *
     * @param WP_REST_Request $request Request object.
     * @return array|WP_Error
     */
    private static function get_bookings_query_args(WP_REST_Request $request)
    {
        $page = (int) $request->get_param('page');
        $page = $page > 0 ? $page : 1;

        $per_page = (int) $request->get_param('per_page');
        $per_page = $per_page > 0 ? $per_page : self::DEFAULT_PER_PAGE;
        $per_page = min($per_page, self::MAX_PER_PAGE);

        $sort = strtolower((string) $request->get_param('sort'));
        if ($sort === '' || $sort === 'booked_at') {
            $sort = 'date';
        }

        if (!in_array($sort, ['date', 'amount', 'status', 'check_in', 'check_out'], true)) {
            return new WP_Error(
                'invalid_sort',
                'Unsupported sort field.',
                ['status' => 400]
            );
        }

        $order = strtoupper((string) $request->get_param('order'));
        if ($order === '') {
            $order = 'DESC';
        }

        if (!in_array($order, ['ASC', 'DESC'], true)) {
            return new WP_Error(
                'invalid_order',
                'Unsupported sort order.',
                ['status' => 400]
            );
        }

        $statuses = self::parse_csv_parameter(
            (string) $request->get_param('status'),
            array_keys(self::get_booking_status_filter_map()),
            'status',
            ['pending' => 'pending-payment']
        );
        if (is_wp_error($statuses)) {
            return $statuses;
        }

        $payment_statuses = self::parse_csv_parameter(
            (string) $request->get_param('payment_status'),
            self::get_allowed_payment_statuses(),
            'payment_status',
            ['failed' => 'charge_failed', 'paid' => 'completed']
        );
        if (is_wp_error($payment_statuses)) {
            return $payment_statuses;
        }

        $date_params = [];
        foreach ([
            'check_in_from',
            'check_in_to',
            'check_out_from',
            'check_out_to',
            'booked_from',
            'booked_to',
        ] as $date_key) {
            $parsed = self::parse_date_parameter((string) $request->get_param($date_key), $date_key);
            if (is_wp_error($parsed)) {
                return $parsed;
            }
            $date_params[$date_key] = $parsed;
        }

        $raw_search = trim((string) $request->get_param('search'));
        $search = $raw_search !== '' ? mb_substr($raw_search, 0, 100) : null;

        return array_merge([
            'page'             => $page,
            'per_page'         => $per_page,
            'sort'             => $sort,
            'order'            => $order,
            'statuses'         => $statuses,
            'payment_statuses' => $payment_statuses,
            'id'               => 0,
            'search'           => $search,
        ], $date_params);
    }

    /**
     * Query bookings list rows with pagination metadata.
     */
    private static function query_bookings(array $args): array
    {
        global $wpdb;

        $base_sql = self::get_bookings_base_sql($args);
        [$from_where_sql, $params] = self::get_bookings_filter_sql($base_sql, $args);

        $count_sql = "SELECT COUNT(*) {$from_where_sql}";
        $total_items = (int) $wpdb->get_var(self::prepare_query($count_sql, $params));

        $offset = ($args['page'] - 1) * $args['per_page'];
        $rows_sql = sprintf(
            'SELECT * %s %s LIMIT %d OFFSET %d',
            $from_where_sql,
            self::get_bookings_order_sql($args['sort'], $args['order']),
            (int) $args['per_page'],
            (int) $offset
        );

        $rows = $wpdb->get_results(self::prepare_query($rows_sql, $params), ARRAY_A);

        return [
            'rows'        => is_array($rows) ? $rows : [],
            'total_items' => $total_items,
        ];
    }

    /**
     * Query a single booking summary row by ID.
     */
    private static function get_booking_summary_by_id(int $booking_id): ?array
    {
        global $wpdb;

        // Empty args → base SQL covers all dashboard statuses (no date narrowing).
        $base_sql = self::get_bookings_base_sql([]);
        [$from_where_sql, $params] = self::get_bookings_filter_sql($base_sql, ['id' => $booking_id]);
        $sql = "SELECT * {$from_where_sql} LIMIT 1";
        $row = $wpdb->get_row(self::prepare_query($sql, $params), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    /**
     * Parse and validate reviews list request args.
     *
     * @param WP_REST_Request $request Request object.
     * @return array|WP_Error
     */
    private static function get_reviews_query_args(WP_REST_Request $request)
    {
        $page = (int) $request->get_param('page');
        $page = $page > 0 ? $page : 1;

        $per_page = (int) $request->get_param('per_page');
        $per_page = $per_page > 0 ? $per_page : self::DEFAULT_PER_PAGE;
        $per_page = min($per_page, self::MAX_PER_PAGE);

        $sort = strtolower((string) $request->get_param('sort'));
        if ($sort === '' || $sort === 'review_date') {
            $sort = 'date';
        }

        if (!in_array($sort, ['date', 'rating', 'provider'], true)) {
            return new WP_Error(
                'invalid_sort',
                'Unsupported sort field.',
                ['status' => 400]
            );
        }

        $order = strtoupper((string) $request->get_param('order'));
        if ($order === '') {
            $order = 'DESC';
        }

        if (!in_array($order, ['ASC', 'DESC'], true)) {
            return new WP_Error(
                'invalid_order',
                'Unsupported sort order.',
                ['status' => 400]
            );
        }

        $providers = self::parse_csv_parameter(
            (string) $request->get_param('provider'),
            self::get_allowed_review_providers(),
            'provider',
            self::get_review_provider_aliases()
        );
        if (is_wp_error($providers)) {
            return $providers;
        }

        $min_rating = self::parse_rating_parameter(
            (string) $request->get_param('min_rating'),
            'min_rating'
        );
        if (is_wp_error($min_rating)) {
            return $min_rating;
        }

        $review_date_from = self::parse_date_parameter(
            (string) $request->get_param('review_date_from'),
            'review_date_from'
        );
        if (is_wp_error($review_date_from)) {
            return $review_date_from;
        }

        $review_date_to = self::parse_date_parameter(
            (string) $request->get_param('review_date_to'),
            'review_date_to'
        );
        if (is_wp_error($review_date_to)) {
            return $review_date_to;
        }

        return [
            'page'             => $page,
            'per_page'         => $per_page,
            'sort'             => $sort,
            'order'            => $order,
            'providers'        => $providers,
            'min_rating'       => $min_rating,
            'review_date_from' => $review_date_from,
            'review_date_to'   => $review_date_to,
        ];
    }

    /**
     * SQL expression for the normalized (0–5) rating of a review row.
     *
     * $provider_expr and $rating_expr are SQL expressions (column names or aggregate
     * expressions) that yield the raw provider string and the raw numeric rating.
     */
    private static function get_normalized_rating_sql(string $provider_expr, string $rating_expr): string
    {
        return "
            CASE
                WHEN CAST({$rating_expr} AS DECIMAL(5,2)) <= 0 THEN NULL
                WHEN LOWER({$provider_expr}) LIKE '%google%'
                  OR LOWER({$provider_expr}) LIKE '%tripadvisor%'
                  OR LOWER({$provider_expr}) LIKE '%trip-advisor%'
                  OR LOWER({$provider_expr}) LIKE '%airbnb%'
                  OR LOWER({$provider_expr}) LIKE '%air-bnb%'
                    THEN ROUND(CAST({$rating_expr} AS DECIMAL(5,2)), 2)
                WHEN LOWER({$provider_expr}) LIKE '%expedia%'
                  OR LOWER({$provider_expr}) LIKE '%direct%'
                    THEN ROUND(CAST({$rating_expr} AS DECIMAL(5,2)) / 2, 2)
                ELSE
                    CASE WHEN CAST({$rating_expr} AS DECIMAL(5,2)) > 5
                        THEN ROUND(CAST({$rating_expr} AS DECIMAL(5,2)) / 2, 2)
                        ELSE ROUND(CAST({$rating_expr} AS DECIMAL(5,2)), 2)
                    END
            END
        ";
    }

    /**
     * SQL fragment that maps a normalized provider string to a WHERE/HAVING condition.
     * The provider value must come from the validated allowed-providers list.
     */
    private static function get_provider_sql_condition(string $column, string $provider): string
    {
        switch ($provider) {
            case 'airbnb':
                return "(LOWER({$column}) LIKE '%airbnb%' OR LOWER({$column}) LIKE '%air-bnb%')";
            case 'booking':
                return "LOWER({$column}) LIKE '%booking%'";
            case 'tripadvisor':
                return "(LOWER({$column}) LIKE '%tripadvisor%' OR LOWER({$column}) LIKE '%trip-advisor%')";
            case 'google':
                return "LOWER({$column}) LIKE '%google%'";
            case 'expedia':
                return "LOWER({$column}) LIKE '%expedia%'";
            case 'direct':
                return "LOWER({$column}) LIKE '%direct%'";
            default:
                // Provider is from our validated allowed list — safe to embed directly.
                return sprintf("LOWER({$column}) = '%s'", esc_sql(strtolower($provider)));
        }
    }

    /**
     * Lightweight aggregate query for reviews summary stats (unfiltered, cached).
     *
     * Only fetches the two fields needed to compute average rating and provider counts,
     * avoiding the full column set on every request.
     */
    private static function query_reviews_summary(): array
    {
        $cache_key = 'shaped_reviews_summary_v1';
        $cached    = wp_cache_get($cache_key, 'shaped_dashboard');
        if (false !== $cached && is_array($cached)) {
            return $cached;
        }

        global $wpdb;

        $sql = "
            SELECT
                COALESCE(MAX(CASE WHEN pm.meta_key = 'provider' THEN pm.meta_value END), '') AS provider_raw,
                CAST(COALESCE(MAX(CASE WHEN pm.meta_key = 'review_rating' THEN pm.meta_value END), '0') AS DECIMAL(5,2)) AS rating_raw
            FROM {$wpdb->posts} AS p
            LEFT JOIN {$wpdb->postmeta} AS pm ON p.ID = pm.post_id
            WHERE p.post_type = 'shaped_review'
                AND p.post_status = 'publish'
            GROUP BY p.ID
        ";

        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows)) {
            $rows = [];
        }

        $provider_counts = [];
        $rating_total    = 0.0;
        $rating_count    = 0;

        foreach ($rows as $row) {
            $provider = self::normalize_review_provider((string) $row['provider_raw']);
            $provider_counts[$provider] = ($provider_counts[$provider] ?? 0) + 1;

            $rating_raw = (float) $row['rating_raw'];
            if ($rating_raw > 0) {
                $scale      = self::get_review_rating_scale($provider, $rating_raw);
                $normalized = self::normalize_review_rating($rating_raw, $scale);
                if ($normalized !== null) {
                    $rating_total += $normalized;
                    $rating_count++;
                }
            }
        }

        arsort($provider_counts);
        $provider_count_items = [];
        foreach ($provider_counts as $provider => $count) {
            $provider_count_items[] = ['provider' => $provider, 'count' => $count];
        }

        $summary = [
            'average_rating'  => $rating_count > 0 ? round($rating_total / $rating_count, 1) : null,
            'total_reviews'   => count($rows),
            'provider_counts' => $provider_count_items,
        ];

        wp_cache_set($cache_key, $summary, 'shaped_dashboard', 60);

        return $summary;
    }

    /**
     * Run a filtered, sorted, and paginated reviews query entirely in SQL.
     *
     * Returns ['rows' => array, 'total' => int] where total is the filtered row count
     * before pagination (used for page-count calculation).
     */
    private static function query_reviews_filtered(array $args): array
    {
        global $wpdb;

        $provider_agg = "COALESCE(MAX(CASE WHEN pm.meta_key = 'provider' THEN pm.meta_value END), '')";
        $rating_agg   = "CAST(COALESCE(MAX(CASE WHEN pm.meta_key = 'review_rating' THEN pm.meta_value END), '0') AS DECIMAL(5,2))";

        $normalized_rating_sql = self::get_normalized_rating_sql($provider_agg, $rating_agg);

        $inner_sql = "
            SELECT
                p.ID,
                p.post_content AS review_text,
                p.post_date,
                p.post_date_gmt,
                {$provider_agg} AS provider_raw,
                {$rating_agg} AS rating_raw,
                COALESCE(MAX(CASE WHEN pm.meta_key = 'author_name' THEN pm.meta_value END), '') AS author_name,
                COALESCE(MAX(CASE WHEN pm.meta_key = 'review_date' THEN pm.meta_value END), '') AS review_date_raw,
                COALESCE(MAX(CASE WHEN pm.meta_key = 'review_text_original' THEN pm.meta_value END), '') AS original_text,
                COALESCE(MAX(CASE WHEN pm.meta_key = 'source_language' THEN pm.meta_value END), '') AS source_language,
                COALESCE(MAX(CASE WHEN pm.meta_key = 'is_featured' THEN pm.meta_value END), '') AS is_featured,
                COALESCE(MAX(CASE WHEN pm.meta_key = 'priority' THEN pm.meta_value END), '0') AS priority,
                COALESCE(MAX(CASE WHEN pm.meta_key = 'external_key' THEN pm.meta_value END), '') AS external_key,
                COALESCE(
                    NULLIF(COALESCE(MAX(CASE WHEN pm.meta_key = 'review_date' THEN pm.meta_value END), ''), ''),
                    DATE(p.post_date)
                ) AS effective_review_date,
                ({$normalized_rating_sql}) AS normalized_rating
            FROM {$wpdb->posts} AS p
            LEFT JOIN {$wpdb->postmeta} AS pm ON p.ID = pm.post_id
            WHERE p.post_type = 'shaped_review'
                AND p.post_status = 'publish'
            GROUP BY p.ID
        ";

        [$where_sql, $params] = self::get_reviews_filter_sql($args);
        $order_sql = self::get_reviews_order_sql($args['sort'], $args['order']);

        $count_sql = "SELECT COUNT(*) FROM ({$inner_sql}) AS r {$where_sql}";
        $total     = (int) $wpdb->get_var(self::prepare_query($count_sql, $params));

        $offset   = ($args['page'] - 1) * $args['per_page'];
        $rows_sql = self::prepare_query(
            sprintf(
                'SELECT r.* FROM (%s) AS r %s %s LIMIT %d OFFSET %d',
                $inner_sql,
                $where_sql,
                $order_sql,
                (int) $args['per_page'],
                (int) $offset
            ),
            $params
        );

        $rows = $wpdb->get_results($rows_sql, ARRAY_A);

        return [
            'rows'  => is_array($rows) ? $rows : [],
            'total' => $total,
        ];
    }

    /**
     * Build WHERE clause and params for the reviews outer (filtered) query.
     */
    private static function get_reviews_filter_sql(array $args): array
    {
        $where  = 'WHERE 1=1';
        $params = [];

        if (!empty($args['providers'])) {
            $conditions = [];
            foreach ($args['providers'] as $provider) {
                $conditions[] = self::get_provider_sql_condition('r.provider_raw', $provider);
            }
            $where .= ' AND (' . implode(' OR ', $conditions) . ')';
        }

        if ($args['min_rating'] !== null) {
            $where   .= ' AND r.normalized_rating >= %f';
            $params[] = (float) $args['min_rating'];
        }

        if (!empty($args['review_date_from'])) {
            $where   .= ' AND r.effective_review_date >= %s';
            $params[] = $args['review_date_from'];
        }

        if (!empty($args['review_date_to'])) {
            $where   .= ' AND r.effective_review_date <= %s';
            $params[] = $args['review_date_to'];
        }

        return [$where, $params];
    }

    /**
     * Build ORDER BY clause for the reviews outer query.
     * Nulls are always sorted last regardless of direction.
     */
    private static function get_reviews_order_sql(string $sort, string $order): string
    {
        switch ($sort) {
            case 'rating':
                // (r.normalized_rating IS NULL) = 0 for real values → non-nulls first.
                return "ORDER BY (r.normalized_rating IS NULL) ASC, r.normalized_rating {$order}, r.effective_review_date DESC, r.ID DESC";

            case 'provider':
                return "ORDER BY r.provider_raw {$order}, r.effective_review_date DESC, r.ID DESC";

            case 'date':
            default:
                return "ORDER BY r.effective_review_date {$order}, r.ID DESC";
        }
    }

    /**
     * Base bookings aggregation query.
     *
     * Accepts optional $args to push status and booked-date filters into the GROUP BY
     * aggregation itself, reducing the number of rows processed before outer filters run.
     *
     * @param array $args  Subset of bookings query args; uses 'statuses', 'booked_from',
     *                     'booked_to'.  Pass [] for the detail endpoint (all statuses, no
     *                     date narrowing).
     */
    private static function get_bookings_base_sql(array $args = []): string
    {
        global $wpdb;

        // Use caller-requested statuses when provided; fall back to all dashboard statuses.
        $statuses = !empty($args['statuses'])
            ? self::expand_booking_status_filters($args['statuses'])
            : self::get_dashboard_booking_statuses();

        $status_placeholders = implode(', ', array_fill(0, count($statuses), '%s'));
        $params = $statuses;

        $base_where = "WHERE p.post_type = 'mphb_booking'
                AND p.post_status IN ({$status_placeholders})";

        // Push booked_at range directly into the aggregation WHERE — it targets a real
        // column (post_date) so it can cut the GROUP BY work before any JOINs aggregate.
        if (!empty($args['booked_from'])) {
            $base_where .= ' AND DATE(p.post_date) >= %s';
            $params[] = $args['booked_from'];
        }
        if (!empty($args['booked_to'])) {
            $base_where .= ' AND DATE(p.post_date) <= %s';
            $params[] = $args['booked_to'];
        }

        $sql = "
            SELECT
                p.ID,
                p.post_status AS booking_status_raw,
                p.post_date AS booked_at,
                p.post_date_gmt AS booked_at_gmt,
                COALESCE(
                    MAX(CASE WHEN pm.meta_key = '_mphb_check_in_date' THEN pm.meta_value END),
                    MAX(CASE WHEN pm.meta_key = 'mphb_check_in_date' THEN pm.meta_value END),
                    ''
                ) AS check_in,
                COALESCE(
                    MAX(CASE WHEN pm.meta_key = '_mphb_check_out_date' THEN pm.meta_value END),
                    MAX(CASE WHEN pm.meta_key = 'mphb_check_out_date' THEN pm.meta_value END),
                    ''
                ) AS check_out,
                COALESCE(
                    MAX(CASE WHEN pm.meta_key = '_mphb_customer_first_name' THEN pm.meta_value END),
                    MAX(CASE WHEN pm.meta_key = '_mphb_first_name' THEN pm.meta_value END),
                    MAX(CASE WHEN pm.meta_key = 'mphb_first_name' THEN pm.meta_value END),
                    ''
                ) AS guest_first_name,
                COALESCE(
                    MAX(CASE WHEN pm.meta_key = '_mphb_customer_last_name' THEN pm.meta_value END),
                    MAX(CASE WHEN pm.meta_key = '_mphb_last_name' THEN pm.meta_value END),
                    MAX(CASE WHEN pm.meta_key = 'mphb_last_name' THEN pm.meta_value END),
                    ''
                ) AS guest_last_name,
                COALESCE(
                    MAX(CASE WHEN pm.meta_key = '_mphb_customer_email' THEN pm.meta_value END),
                    MAX(CASE WHEN pm.meta_key = '_mphb_email' THEN pm.meta_value END),
                    MAX(CASE WHEN pm.meta_key = 'mphb_email' THEN pm.meta_value END),
                    ''
                ) AS guest_email,
                COALESCE(
                    MAX(CASE WHEN pm.meta_key = '_mphb_customer_phone' THEN pm.meta_value END),
                    MAX(CASE WHEN pm.meta_key = '_mphb_phone' THEN pm.meta_value END),
                    MAX(CASE WHEN pm.meta_key = 'mphb_phone' THEN pm.meta_value END),
                    ''
                ) AS guest_phone,
                COALESCE(MAX(CASE WHEN pm.meta_key = '_shaped_payment_status' THEN pm.meta_value END), '') AS payment_status_raw,
                COALESCE(MAX(CASE WHEN pm.meta_key = '_shaped_payment_type' THEN pm.meta_value END), '') AS payment_type,
                COALESCE(MAX(CASE WHEN pm.meta_key = '_shaped_payment_mode' THEN pm.meta_value END), '') AS payment_mode_raw,
                CAST(
                    COALESCE(
                        MAX(CASE WHEN pm.meta_key = '_shaped_payment_amount' THEN pm.meta_value END),
                        MAX(CASE WHEN pm.meta_key = 'mphb_total_price' THEN pm.meta_value END),
                        '0'
                    ) AS DECIMAL(10,2)
                ) AS total_amount,
                CAST(COALESCE(MAX(CASE WHEN pm.meta_key = '_shaped_deposit_amount' THEN pm.meta_value END), '0') AS DECIMAL(10,2)) AS deposit_amount,
                CAST(COALESCE(MAX(CASE WHEN pm.meta_key = '_shaped_balance_due' THEN pm.meta_value END), '0') AS DECIMAL(10,2)) AS balance_due,
                CAST(COALESCE(MAX(CASE WHEN pm.meta_key = '_mphb_paid_amount' THEN pm.meta_value END), '0') AS DECIMAL(10,2)) AS paid_amount,
                CAST(COALESCE(MAX(CASE WHEN pm.meta_key = '_stripe_pending_amount' THEN pm.meta_value END), '0') AS DECIMAL(10,2)) AS stripe_pending_amount,
                COALESCE(MAX(CASE WHEN pm.meta_key = '_shaped_charge_scheduled' THEN pm.meta_value END), '') AS charge_scheduled,
                COALESCE(MAX(CASE WHEN pm.meta_key = '_shaped_charge_date' THEN pm.meta_value END), '') AS charge_date,
                COALESCE(MAX(CASE WHEN pm.meta_key = '_shaped_charge_at' THEN pm.meta_value END), '') AS charge_at,
                COALESCE(MAX(CASE WHEN pm.meta_key = '_shaped_charge_processed' THEN pm.meta_value END), '') AS charge_processed,
                COALESCE(MAX(CASE WHEN pm.meta_key = '_stripe_payment_charged' THEN pm.meta_value END), '') AS stripe_charged,
                COALESCE(MAX(CASE WHEN pm.meta_key = '_shaped_payment_collected_at' THEN pm.meta_value END), '') AS payment_collected_at,
                COALESCE(MAX(CASE WHEN pm.meta_key = '_shaped_payment_collected_date' THEN pm.meta_value END), '') AS payment_collected_date,
                COALESCE(MAX(CASE WHEN pm.meta_key = '_stripe_currency' THEN pm.meta_value END), '') AS stripe_currency,
                COALESCE(MAX(CASE WHEN pm.meta_key = '_stripe_payment_intent_id' THEN pm.meta_value END), '') AS stripe_payment_intent_id,
                COALESCE(MAX(CASE WHEN pm.meta_key = '_stripe_checkout_session_id' THEN pm.meta_value END), '') AS stripe_checkout_session_id,
                COALESCE(MAX(CASE WHEN pm.meta_key = '_stripe_setup_intent_id' THEN pm.meta_value END), '') AS stripe_setup_intent_id,
                COALESCE(MAX(CASE WHEN pm.meta_key = '_stripe_customer_id' THEN pm.meta_value END), '') AS stripe_customer_id,
                COALESCE(MAX(CASE WHEN pm.meta_key = '_stripe_payment_method_id' THEN pm.meta_value END), '') AS stripe_payment_method_id,
                COALESCE(MAX(CASE WHEN pm.meta_key = '_shaped_abandoned_at' THEN pm.meta_value END), '') AS abandoned_at
            FROM {$wpdb->posts} AS p
            LEFT JOIN {$wpdb->postmeta} AS pm
                ON p.ID = pm.post_id
            {$base_where}
            GROUP BY p.ID
        ";

        return self::prepare_query($sql, $params);
    }

    /**
     * Build filtered bookings query fragment and params.
     *
     * Handles filters that must operate on aggregated/computed columns (payment_status,
     * check_in, check_out, id, search).  Status and booked_at filters are pushed into
     * get_bookings_base_sql() instead so they reduce the GROUP BY work upstream.
     */
    private static function get_bookings_filter_sql(string $base_sql, array $args): array
    {
        global $wpdb;

        $from_where_sql = "FROM ({$base_sql}) AS bookings WHERE 1=1";
        $params = [];

        if (!empty($args['id'])) {
            $from_where_sql .= ' AND bookings.ID = %d';
            $params[] = (int) $args['id'];
        }

        if (!empty($args['payment_statuses'])) {
            $placeholders = implode(', ', array_fill(0, count($args['payment_statuses']), '%s'));
            $from_where_sql .= " AND bookings.payment_status_raw IN ({$placeholders})";
            $params = array_merge($params, $args['payment_statuses']);
        }

        foreach ([
            'check_in_from'  => ['bookings.check_in', '>='],
            'check_in_to'    => ['bookings.check_in', '<='],
            'check_out_from' => ['bookings.check_out', '>='],
            'check_out_to'   => ['bookings.check_out', '<='],
        ] as $arg_key => $config) {
            if (!empty($args[$arg_key])) {
                $from_where_sql .= sprintf(' AND %s %s %%s', $config[0], $config[1]);
                $params[] = $args[$arg_key];
            }
        }

        if (!empty($args['search'])) {
            $like = '%' . $wpdb->esc_like($args['search']) . '%';
            $from_where_sql .= " AND (
                bookings.guest_first_name LIKE %s
                OR bookings.guest_last_name LIKE %s
                OR bookings.guest_email LIKE %s
                OR CONCAT(
                    COALESCE(bookings.guest_first_name, ''),
                    ' ',
                    COALESCE(bookings.guest_last_name, '')
                ) LIKE %s
            )";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        return [$from_where_sql, $params];
    }

    /**
     * Build ORDER BY clause for bookings list.
     */
    private static function get_bookings_order_sql(string $sort, string $order): string
    {
        switch ($sort) {
            case 'amount':
                $column = 'bookings.total_amount';
                break;

            case 'status':
                $column = "CASE LOWER(bookings.booking_status_raw)
                    WHEN 'confirmed' THEN 1
                    WHEN 'publish' THEN 1
                    WHEN 'pending-payment' THEN 2
                    WHEN 'pending' THEN 2
                    WHEN 'cancelled' THEN 3
                    WHEN 'abandoned' THEN 4
                    ELSE 5
                END";
                break;

            case 'check_in':
                $column = 'bookings.check_in';
                break;

            case 'check_out':
                $column = 'bookings.check_out';
                break;

            case 'date':
            default:
                $column = 'bookings.booked_at';
                break;
        }

        return "ORDER BY {$column} {$order}, bookings.ID DESC";
    }

    /**
     * Convert aggregated booking row into the list item contract.
     */
    private static function build_booking_list_item(array $row): array
    {
        $first_name = self::nullable_string($row['guest_first_name']);
        $last_name = self::nullable_string($row['guest_last_name']);

        return [
            'id'                 => (int) $row['ID'],
            'guest_first_name'   => $first_name,
            'guest_last_name'    => $last_name,
            'guest_name'         => self::build_guest_name($first_name, $last_name, self::nullable_string($row['guest_email'])),
            'guest_email'        => self::nullable_string($row['guest_email']),
            'check_in'           => self::nullable_string($row['check_in']),
            'check_out'          => self::nullable_string($row['check_out']),
            'booking_status'     => self::normalize_booking_status((string) $row['booking_status_raw']),
            'booking_status_raw' => (string) $row['booking_status_raw'],
            'payment_status'     => self::resolve_payment_status($row),
            'payment_type'       => self::resolve_payment_type($row),
            'payment_mode'       => self::resolve_payment_mode($row),
            'amount'             => self::resolve_total_amount($row),
            'booked_at'          => self::format_post_datetime(
                (string) $row['booked_at'],
                (string) $row['booked_at_gmt']
            ),
            'payment_collected_at'   => self::resolve_payment_collected_at($row),
            'payment_collected_date' => self::resolve_payment_collected_date($row),
        ];
    }

    /**
     * Build a dashboard review list item.
     */
    private static function build_review_list_item(array $row): array
    {
        $provider_raw = self::nullable_string($row['provider_raw']);
        $provider = self::normalize_review_provider((string) $row['provider_raw']);
        $rating_raw = self::normalize_review_rating_raw($row['rating_raw']);
        $rating_scale = self::get_review_rating_scale($provider, $rating_raw);
        $rating = self::normalize_review_rating($rating_raw, $rating_scale);
        $created_at = self::format_post_datetime(
            (string) $row['post_date'],
            (string) $row['post_date_gmt']
        );

        return [
            'id'            => (int) $row['ID'],
            'provider'      => $provider,
            'provider_raw'  => $provider_raw,
            'rating'        => $rating,
            'rating_raw'    => $rating_raw,
            'rating_scale'  => $rating_scale,
            'author_name'   => self::normalize_review_author((string) $row['author_name']),
            'review_date'   => self::normalize_review_date(
                (string) $row['review_date_raw'],
                (string) $row['post_date'],
                (string) $row['post_date_gmt']
            ),
            'created_at'    => $created_at,
            'review_text'   => self::normalize_review_text((string) $row['review_text']),
            'original_text' => self::normalize_review_text((string) $row['original_text']),
            'source_language' => self::normalize_language_code((string) $row['source_language']),
            'is_featured'   => self::meta_is_truthy($row['is_featured']),
            'priority'      => (int) $row['priority'],
            'external_key'  => self::nullable_string($row['external_key']),
        ];
    }


    /**
     * Build the guest section of booking detail.
     */
    private static function build_guest_payload(int $booking_id, $booking, array $summary): array
    {
        $customer_fields = self::get_booking_customer_meta($booking_id);
        $customer = self::get_booking_customer($booking);

        $first_name = self::nullable_string($summary['guest_first_name']);
        if (!$first_name && isset($customer_fields['first_name'])) {
            $first_name = self::nullable_string($customer_fields['first_name']);
        }
        if (!$first_name && $customer && method_exists($customer, 'getFirstName')) {
            $first_name = self::nullable_string($customer->getFirstName());
        }

        $last_name = self::nullable_string($summary['guest_last_name']);
        if (!$last_name && isset($customer_fields['last_name'])) {
            $last_name = self::nullable_string($customer_fields['last_name']);
        }
        if (!$last_name && $customer && method_exists($customer, 'getLastName')) {
            $last_name = self::nullable_string($customer->getLastName());
        }

        $email = self::nullable_string($summary['guest_email']);
        if (!$email && isset($customer_fields['email'])) {
            $email = self::nullable_string($customer_fields['email']);
        }
        if (!$email && $customer && method_exists($customer, 'getEmail')) {
            $email = self::nullable_string($customer->getEmail());
        }

        $phone = self::nullable_string($summary['guest_phone']);
        if (!$phone && isset($customer_fields['phone'])) {
            $phone = self::nullable_string($customer_fields['phone']);
        }

        return [
            'first_name'      => $first_name,
            'last_name'       => $last_name,
            'full_name'       => self::build_guest_name($first_name, $last_name, $email),
            'email'           => $email,
            'phone'           => $phone,
            'customer_fields' => $customer_fields,
        ];
    }

    /**
     * Extract any discovered _mphb_customer_* fields from booking meta.
     */
    private static function get_booking_customer_meta(int $booking_id): array
    {
        $meta = get_post_meta($booking_id);
        $customer_fields = [];

        foreach ($meta as $key => $values) {
            if (strpos($key, '_mphb_customer_') !== 0) {
                continue;
            }

            $field = substr($key, strlen('_mphb_customer_'));
            if ($field === '' || empty($values)) {
                continue;
            }

            $value = maybe_unserialize($values[0]);
            if (is_scalar($value) || $value === null) {
                $customer_fields[$field] = $value === null ? null : trim((string) $value);
            } else {
                $customer_fields[$field] = $value;
            }
        }

        return $customer_fields;
    }

    /**
     * Build the room section of booking detail.
     */
    private static function build_room_payload(int $booking_id, $booking): array
    {
        $booking_stay = self::get_booking_stay_dates($booking_id, $booking);
        $reserved_room_posts = get_posts([
            'post_type'      => 'mphb_reserved_room',
            'post_parent'    => $booking_id,
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ]);

        $room_objects = self::get_reserved_room_object_map($booking);
        $reserved_rooms = [];

        foreach ($reserved_room_posts as $reserved_room_post) {
            $reserved_room_id = (int) $reserved_room_post->ID;
            $reserved_room = isset($room_objects[$reserved_room_id]) ? $room_objects[$reserved_room_id] : null;
            $reserved_rooms[] = self::build_reserved_room_payload(
                $reserved_room_id,
                $reserved_room,
                $booking_stay['check_in'],
                $booking_stay['check_out']
            );
        }

        if (empty($reserved_rooms)) {
            foreach ($room_objects as $reserved_room_id => $reserved_room) {
                $reserved_rooms[] = self::build_reserved_room_payload(
                    (int) $reserved_room_id,
                    $reserved_room,
                    $booking_stay['check_in'],
                    $booking_stay['check_out']
                );
            }
        }

        $rate_name = function_exists('shaped_get_booking_rate_name')
            ? self::nullable_string(shaped_get_booking_rate_name($booking_id))
            : null;

        $primary_room_type_name = null;
        if (!empty($reserved_rooms)) {
            $primary_room_type_name = self::nullable_string($reserved_rooms[0]['room_type_name']);
        }

        $adults_total = 0;
        $children_total = 0;
        $has_occupancy = false;
        foreach ($reserved_rooms as $reserved_room) {
            if ($reserved_room['adults'] !== null) {
                $adults_total += $reserved_room['adults'];
                $has_occupancy = true;
            }
            if ($reserved_room['children'] !== null) {
                $children_total += $reserved_room['children'];
                $has_occupancy = true;
            }
        }

        return [
            'primary_room_type_name' => $primary_room_type_name,
            'rate_name'              => $rate_name,
            'adults'                 => $has_occupancy ? $adults_total : null,
            'children'               => $has_occupancy ? $children_total : null,
            'guest_count'            => $has_occupancy ? $adults_total + $children_total : null,
            'reserved_rooms'         => $reserved_rooms,
        ];
    }

    /**
     * Map reserved room entities by child post ID.
     */
    private static function get_reserved_room_object_map($booking): array
    {
        $room_map = [];

        if (!$booking || !method_exists($booking, 'getReservedRooms')) {
            return $room_map;
        }

        foreach ((array) $booking->getReservedRooms() as $reserved_room) {
            if (!is_object($reserved_room) || !method_exists($reserved_room, 'getId')) {
                continue;
            }

            $room_map[(int) $reserved_room->getId()] = $reserved_room;
        }

        return $room_map;
    }

    /**
     * Convert a reserved room child post into detail payload.
     */
    private static function build_reserved_room_payload(
        int $reserved_room_id,
        $reserved_room = null,
        ?string $fallback_check_in = null,
        ?string $fallback_check_out = null
    ): array
    {
        $room_type_id = (int) get_post_meta($reserved_room_id, 'mphb_room_type_id', true);
        if (!$room_type_id) {
            $room_type_id = (int) get_post_meta($reserved_room_id, '_mphb_room_type_id', true);
        }
        if (!$room_type_id && $reserved_room && method_exists($reserved_room, 'getRoomTypeId')) {
            $room_type_id = (int) $reserved_room->getRoomTypeId();
        }

        $room_id = (int) get_post_meta($reserved_room_id, '_mphb_room_id', true);

        $check_in = self::nullable_string(get_post_meta($reserved_room_id, 'mphb_check_in_date', true));
        if (!$check_in) {
            $check_in = self::nullable_string(get_post_meta($reserved_room_id, '_mphb_check_in_date', true));
        }

        $check_out = self::nullable_string(get_post_meta($reserved_room_id, 'mphb_check_out_date', true));
        if (!$check_out) {
            $check_out = self::nullable_string(get_post_meta($reserved_room_id, '_mphb_check_out_date', true));
        }

        if (!$check_in && $reserved_room && method_exists($reserved_room, 'getCheckInDate')) {
            $date = $reserved_room->getCheckInDate();
            $check_in = $date instanceof DateTimeInterface ? $date->format('Y-m-d') : $check_in;
        }

        if (!$check_out && $reserved_room && method_exists($reserved_room, 'getCheckOutDate')) {
            $date = $reserved_room->getCheckOutDate();
            $check_out = $date instanceof DateTimeInterface ? $date->format('Y-m-d') : $check_out;
        }

        if (!$check_in) {
            $check_in = $fallback_check_in;
        }

        if (!$check_out) {
            $check_out = $fallback_check_out;
        }

        $rate_id = 0;
        $rate_name = null;
        if ($reserved_room && method_exists($reserved_room, 'getRateId')) {
            $rate_id = (int) $reserved_room->getRateId();
        }
        if ($reserved_room && method_exists($reserved_room, 'getRateTitle')) {
            $rate_name = self::nullable_string($reserved_room->getRateTitle());
        }
        if (!$rate_name && $rate_id > 0) {
            $rate_name = self::nullable_string(get_the_title($rate_id));
        }

        $adults = null;
        if ($reserved_room && method_exists($reserved_room, 'getAdults')) {
            $adults = self::nullable_occupancy_count($reserved_room->getAdults());
        }
        if ($adults === null) {
            $adults = self::nullable_occupancy_count(get_post_meta($reserved_room_id, '_mphb_adults', true));
        }

        $children = null;
        if ($reserved_room && method_exists($reserved_room, 'getChildren')) {
            $children = self::nullable_occupancy_count($reserved_room->getChildren());
        }
        if ($children === null) {
            $children = self::nullable_occupancy_count(get_post_meta($reserved_room_id, '_mphb_children', true));
        }

        $guest_count = $adults !== null || $children !== null
            ? ($adults ?? 0) + ($children ?? 0)
            : null;

        return [
            'id'             => $reserved_room_id,
            'room_type_id'   => $room_type_id > 0 ? $room_type_id : null,
            'room_type_name' => $room_type_id > 0 ? self::nullable_string(get_the_title($room_type_id)) : null,
            'room_id'        => $room_id > 0 ? $room_id : null,
            'room_name'      => $room_id > 0 ? self::nullable_string(get_the_title($room_id)) : null,
            'rate_id'        => $rate_id > 0 ? $rate_id : null,
            'rate_name'      => $rate_name,
            'check_in'       => $check_in,
            'check_out'      => $check_out,
            'adults'         => $adults,
            'children'       => $children,
            'guest_count'    => $guest_count,
        ];
    }

    /**
     * Normalize reserved-room occupancy counts.
     */
    private static function nullable_occupancy_count($value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
        }

        if (!is_numeric($value)) {
            return null;
        }

        $count = (int) $value;

        return $count >= 0 ? $count : null;
    }

    /**
     * Resolve booking-level stay dates for reserved-room fallbacks.
     */
    private static function get_booking_stay_dates(int $booking_id, $booking): array
    {
        $check_in = self::nullable_string(get_post_meta($booking_id, '_mphb_check_in_date', true));
        if (!$check_in) {
            $check_in = self::nullable_string(get_post_meta($booking_id, 'mphb_check_in_date', true));
        }
        if (!$check_in && $booking && method_exists($booking, 'getCheckInDate')) {
            $date = $booking->getCheckInDate();
            $check_in = $date instanceof DateTimeInterface ? $date->format('Y-m-d') : $check_in;
        }

        $check_out = self::nullable_string(get_post_meta($booking_id, '_mphb_check_out_date', true));
        if (!$check_out) {
            $check_out = self::nullable_string(get_post_meta($booking_id, 'mphb_check_out_date', true));
        }
        if (!$check_out && $booking && method_exists($booking, 'getCheckOutDate')) {
            $date = $booking->getCheckOutDate();
            $check_out = $date instanceof DateTimeInterface ? $date->format('Y-m-d') : $check_out;
        }

        return [
            'check_in'  => $check_in,
            'check_out' => $check_out,
        ];
    }

    /**
     * Build payment detail payload.
     */
    private static function build_payment_payload(array $summary, ?array $payment_context = null): array
    {
        $charge_scheduled = self::has_scheduled_charge($summary, $payment_context);
        $stripe_charged = self::meta_is_truthy($summary['stripe_charged']);
        $charge_processed = self::meta_is_truthy($summary['charge_processed']);
        $payment_type = self::resolve_payment_type($summary, $payment_context);
        $payment_mode = self::resolve_payment_mode($summary, $payment_context);
        $currency = self::nullable_string($summary['stripe_currency']);
        $currency = $currency ? strtoupper($currency) : self::get_currency_code();

        return [
            'status'            => self::resolve_payment_status($summary),
            'status_display'    => $payment_context && isset($payment_context['payment_status'])
                ? (string) $payment_context['payment_status']
                : self::resolve_payment_status($summary),
            'type'              => $payment_type,
            'mode'              => $payment_mode,
            'amount'            => self::resolve_total_amount($summary),
            'deposit_amount'    => $payment_context && isset($payment_context['deposit_amount'])
                ? self::round_amount($payment_context['deposit_amount'])
                : self::round_amount($summary['deposit_amount']),
            'balance_due'       => $payment_context && isset($payment_context['balance_due'])
                ? self::round_amount($payment_context['balance_due'])
                : self::round_amount($summary['balance_due']),
            'paid_amount'       => self::round_amount($summary['paid_amount']),
            'pending_amount'    => self::round_amount($summary['stripe_pending_amount']),
            'currency'          => $currency,
            'is_charged'        => $stripe_charged || ($payment_context && !empty($payment_context['is_charged'])),
            'charge_scheduled'  => $charge_scheduled,
            'charge_processed'  => $charge_processed,
            'charge_date'       => self::nullable_string($summary['charge_date']),
            'charge_at'         => self::normalize_datetime_value((string) $summary['charge_at']),
            'collected_at'      => self::resolve_payment_collected_at($summary),
            'collected_date'    => self::resolve_payment_collected_date($summary),
            'days_until_charge' => $payment_context && isset($payment_context['days_until_charge'])
                ? (float) $payment_context['days_until_charge']
                : null,
            'threshold_days'    => $payment_context && isset($payment_context['threshold_days'])
                ? (int) $payment_context['threshold_days']
                : null,
            'is_immediate'      => $payment_context && isset($payment_context['is_immediate'])
                ? (bool) $payment_context['is_immediate']
                : ($payment_mode !== 'delayed'),
            'property_mode'     => $payment_context && isset($payment_context['property_mode'])
                ? (string) $payment_context['property_mode']
                : null,
        ];
    }

    /**
     * Determine whether a booking should be treated as having a scheduled charge.
     */
    private static function has_scheduled_charge(array $summary, ?array $payment_context = null): bool
    {
        if (self::meta_is_truthy($summary['charge_scheduled'])) {
            return true;
        }

        $pending_amount = self::round_amount($summary['stripe_pending_amount']);
        if ($pending_amount <= 0) {
            return false;
        }

        $payment_status = self::nullable_string($summary['payment_status_raw']);
        $payment_mode = self::nullable_string($summary['payment_mode_raw']);
        $charge_at = self::nullable_string($summary['charge_at']);
        $charge_date = self::nullable_string($summary['charge_date']);
        $has_charge_window = $charge_at !== null || $charge_date !== null;

        if (
            $payment_context
            && !empty($payment_context['mode'])
            && (string) $payment_context['mode'] === 'delayed'
            && array_key_exists('is_immediate', $payment_context)
            && $payment_context['is_immediate'] === false
        ) {
            return true;
        }

        return (
            ($payment_status === 'authorized' || $payment_mode === 'delayed')
            && $has_charge_window
        );
    }

    /**
     * Build Stripe detail payload.
     */
    private static function build_stripe_payload(array $summary, array $payment): array
    {
        return [
            'payment_intent_id'   => self::nullable_string($summary['stripe_payment_intent_id']),
            'checkout_session_id' => self::nullable_string($summary['stripe_checkout_session_id']),
            'setup_intent_id'     => self::nullable_string($summary['stripe_setup_intent_id']),
            'customer_id'         => self::nullable_string($summary['stripe_customer_id']),
            'payment_method_id'   => self::nullable_string($summary['stripe_payment_method_id']),
            'charged'             => (bool) $payment['is_charged'],
            'currency'            => $payment['currency'],
        ];
    }

    /**
     * Build booking timeline payload.
     */
    private static function build_timeline_payload(array $summary, array $payment): array
    {
        return [
            'created_at'         => self::format_post_datetime(
                (string) $summary['booked_at'],
                (string) $summary['booked_at_gmt']
            ),
            'booking_status'     => self::normalize_booking_status((string) $summary['booking_status_raw']),
            'booking_status_raw' => (string) $summary['booking_status_raw'],
            'payment_status'     => $payment['status'],
            'charge_date'        => $payment['charge_date'],
            'charge_at'          => $payment['charge_at'],
            'charge_processed'   => $payment['charge_processed'],
            'payment_collected_at' => $payment['collected_at'],
            'abandoned_at'       => self::normalize_datetime_value((string) $summary['abandoned_at']),
        ];
    }

    /**
     * Return the MPHB booking entity if available.
     */
    private static function get_booking_entity(int $booking_id)
    {
        if (!function_exists('MPHB')) {
            return null;
        }

        try {
            return MPHB()->getBookingRepository()->findById($booking_id, true);
        } catch (Throwable $throwable) {
            return null;
        }
    }

    /**
     * Return normalized payment context if available.
     */
    private static function get_payment_context($booking): ?array
    {
        if (!$booking || !class_exists('Shaped_Payment_Processor')) {
            return null;
        }

        try {
            $context = Shaped_Payment_Processor::get_payment_context($booking);
            return is_array($context) ? $context : null;
        } catch (Throwable $throwable) {
            return null;
        }
    }

    /**
     * Get booking customer entity if available.
     */
    private static function get_booking_customer($booking)
    {
        if (!$booking || !method_exists($booking, 'getCustomer')) {
            return null;
        }

        try {
            return $booking->getCustomer();
        } catch (Throwable $throwable) {
            return null;
        }
    }

    /**
     * Normalize guest display name.
     */
    private static function build_guest_name(?string $first_name, ?string $last_name, ?string $email = null): string
    {
        $name = trim((string) $first_name . ' ' . (string) $last_name);
        if ($name !== '') {
            return $name;
        }

        if ($email) {
            return $email;
        }

        return 'Guest';
    }

    /**
     * Normalize booking status for the dashboard contract.
     */
    private static function normalize_booking_status(string $status): string
    {
        $normalized = strtolower(trim($status));

        switch ($normalized) {
            case 'publish':
            case 'confirmed':
                return 'confirmed';

            case 'pending':
            case 'pending-payment':
                return 'pending-payment';

            case 'cancelled':
                return 'cancelled';

            case 'abandoned':
                return 'abandoned';

            default:
                return $normalized !== '' ? $normalized : 'unknown';
        }
    }

    /**
     * Resolve the raw payment status exposed by the dashboard contract.
     */
    private static function resolve_payment_status(array $summary): string
    {
        $payment_status = self::nullable_string($summary['payment_status_raw']);
        if ($payment_status) {
            return $payment_status;
        }

        $booking_status = self::normalize_booking_status((string) $summary['booking_status_raw']);
        if ($booking_status === 'abandoned') {
            return 'abandoned';
        }
        if ($booking_status === 'cancelled') {
            return 'cancelled';
        }
        if (self::meta_is_truthy($summary['stripe_charged']) || self::round_amount($summary['paid_amount']) > 0) {
            return 'completed';
        }
        if (self::meta_is_truthy($summary['charge_scheduled']) || self::round_amount($summary['stripe_pending_amount']) > 0) {
            return 'authorized';
        }

        return 'pending';
    }

    /**
     * Resolve payment type with sensible fallback.
     */
    private static function resolve_payment_type(array $summary, ?array $payment_context = null): string
    {
        if ($payment_context && !empty($payment_context['payment_type'])) {
            return (string) $payment_context['payment_type'];
        }

        $payment_type = self::nullable_string($summary['payment_type']);

        return $payment_type ?: 'full';
    }

    /**
     * Resolve payment mode with sensible fallback.
     */
    private static function resolve_payment_mode(array $summary, ?array $payment_context = null): string
    {
        if ($payment_context && !empty($payment_context['mode'])) {
            return (string) $payment_context['mode'];
        }

        $payment_mode = self::nullable_string($summary['payment_mode_raw']);
        if ($payment_mode) {
            return $payment_mode;
        }

        $payment_type = self::resolve_payment_type($summary, $payment_context);
        if ($payment_type === 'deposit') {
            return 'deposit';
        }

        if (
            self::meta_is_truthy($summary['charge_scheduled'])
            || self::round_amount($summary['stripe_pending_amount']) > 0
            || self::nullable_string($summary['charge_at'])
            || self::nullable_string($summary['charge_date'])
        ) {
            return 'delayed';
        }

        return 'immediate';
    }

    /**
     * Resolve total booking amount from summary row.
     */
    private static function resolve_total_amount(array $summary): float
    {
        $amount = self::round_amount($summary['total_amount']);
        if ($amount > 0) {
            return $amount;
        }

        $deposit = self::round_amount($summary['deposit_amount']);
        $balance_due = self::round_amount($summary['balance_due']);
        if ($deposit > 0 || $balance_due > 0) {
            return round($deposit + $balance_due, 2);
        }

        $paid_amount = self::round_amount($summary['paid_amount']);
        if ($paid_amount > 0) {
            return $paid_amount;
        }

        $pending_amount = self::round_amount($summary['stripe_pending_amount']);
        if ($pending_amount > 0) {
            return $pending_amount;
        }

        return 0.0;
    }

    /**
     * Resolve the timestamp when money was actually collected.
     */
    private static function resolve_payment_collected_at(array $summary): ?string
    {
        $raw = self::nullable_string($summary['payment_collected_at'] ?? '');
        if ($raw) {
            return self::normalize_utc_datetime_value($raw);
        }

        $payment_status = self::resolve_payment_status($summary);
        if (!in_array($payment_status, ['completed', 'deposit_paid'], true)) {
            return null;
        }

        if (self::resolve_payment_mode($summary, null) === 'delayed') {
            return null;
        }

        return self::format_post_datetime(
            (string) ($summary['booked_at'] ?? ''),
            (string) ($summary['booked_at_gmt'] ?? '')
        );
    }

    /**
     * Resolve the site-local date when money was actually collected.
     */
    private static function resolve_payment_collected_date(array $summary): ?string
    {
        $raw = self::nullable_string($summary['payment_collected_date'] ?? '');
        if ($raw) {
            return $raw;
        }

        $collected_at = self::resolve_payment_collected_at($summary);
        if (!$collected_at) {
            return null;
        }

        try {
            $date = new DateTimeImmutable($collected_at);
            return $date->setTimezone(wp_timezone())->format('Y-m-d');
        } catch (Throwable $throwable) {
            return null;
        }
    }

    /**
     * Expand canonical dashboard statuses into raw post statuses.
     */
    private static function expand_booking_status_filters(array $statuses): array
    {
        $mapping = self::get_booking_status_filter_map();
        $expanded = [];

        foreach ($statuses as $status) {
            if (!isset($mapping[$status])) {
                continue;
            }

            $expanded = array_merge($expanded, $mapping[$status]);
        }

        return array_values(array_unique($expanded));
    }

    /**
     * Canonical dashboard status to raw WP post status mapping.
     */
    private static function get_booking_status_filter_map(): array
    {
        return [
            'confirmed'       => ['confirmed', 'publish', 'Confirmed'],
            'pending-payment' => ['pending-payment', 'pending'],
            'cancelled'       => ['cancelled'],
            'abandoned'       => ['abandoned'],
        ];
    }

    /**
     * Booking post statuses covered by the dashboard API.
     */
    private static function get_dashboard_booking_statuses(): array
    {
        return [
            'confirmed',
            'publish',
            'Confirmed',
            'pending-payment',
            'pending',
            'cancelled',
            'abandoned',
        ];
    }

    /**
     * Allowed raw payment statuses for filters.
     */
    private static function get_allowed_payment_statuses(): array
    {
        return [
            'pending',
            'completed',
            'deposit_paid',
            'authorized',
            'card_update_required',
            'manual_review',
            'charge_failed',
            'cancelled',
            'abandoned',
        ];
    }

    /**
     * Parse comma-separated filter params.
     *
     * @return array|WP_Error
     */
    private static function parse_csv_parameter(
        string $value,
        array $allowed_values,
        string $parameter_name,
        array $aliases = []
    ) {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        $allowed_lookup = array_fill_keys($allowed_values, true);
        $items = array_filter(array_map('trim', explode(',', strtolower($value))));
        $parsed = [];

        foreach ($items as $item) {
            if (isset($aliases[$item])) {
                $item = $aliases[$item];
            }

            if (!isset($allowed_lookup[$item])) {
                return new WP_Error(
                    'invalid_parameter',
                    sprintf('Unsupported %s value: %s', $parameter_name, $item),
                    ['status' => 400]
                );
            }

            $parsed[] = $item;
        }

        return array_values(array_unique($parsed));
    }

    /**
     * Parse date filter params.
     *
     * @return string|WP_Error|null
     */
    private static function parse_date_parameter(string $value, string $parameter_name)
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return new WP_Error(
                'invalid_parameter',
                sprintf('Invalid %s value. Expected YYYY-MM-DD.', $parameter_name),
                ['status' => 400]
            );
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value, wp_timezone());
        if (!$date || $date->format('Y-m-d') !== $value) {
            return new WP_Error(
                'invalid_parameter',
                sprintf('Invalid %s value. Expected YYYY-MM-DD.', $parameter_name),
                ['status' => 400]
            );
        }

        return $value;
    }

    /**
     * Parse minimum-rating filter params.
     *
     * @return float|WP_Error|null
     */
    private static function parse_rating_parameter(string $value, string $parameter_name)
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return new WP_Error(
                'invalid_parameter',
                sprintf('Invalid %s value. Expected a number between 0 and 5.', $parameter_name),
                ['status' => 400]
            );
        }

        $rating = round((float) $value, 1);
        if ($rating < 0 || $rating > 5) {
            return new WP_Error(
                'invalid_parameter',
                sprintf('Invalid %s value. Expected a number between 0 and 5.', $parameter_name),
                ['status' => 400]
            );
        }

        return $rating;
    }

    /**
     * Supported review providers for dashboard filters.
     */
    private static function get_allowed_review_providers(): array
    {
        return [
            'airbnb',
            'booking',
            'direct',
            'expedia',
            'google',
            'tripadvisor',
        ];
    }

    /**
     * Accepted aliases for review provider filters.
     */
    private static function get_review_provider_aliases(): array
    {
        return [
            'air-bnb'        => 'airbnb',
            'booking.com'    => 'booking',
            'booking-com'    => 'booking',
            'bookingcom'     => 'booking',
            'trip-advisor'   => 'tripadvisor',
            'trip advisor'   => 'tripadvisor',
            'trip_advisor'   => 'tripadvisor',
        ];
    }

    /**
     * Normalize a stored review provider to a stable dashboard key.
     */
    private static function normalize_review_provider(string $provider): string
    {
        $provider = strtolower(trim($provider));
        if ($provider === '') {
            return 'unknown';
        }

        $provider = str_replace(['_', ' '], '-', $provider);

        if (strpos($provider, 'booking') !== false) {
            return 'booking';
        }

        if (strpos($provider, 'tripadvisor') !== false || strpos($provider, 'trip-advisor') !== false) {
            return 'tripadvisor';
        }

        if (strpos($provider, 'airbnb') !== false || strpos($provider, 'air-bnb') !== false) {
            return 'airbnb';
        }

        if (strpos($provider, 'google') !== false) {
            return 'google';
        }

        if (strpos($provider, 'expedia') !== false) {
            return 'expedia';
        }

        if (strpos($provider, 'direct') !== false) {
            return 'direct';
        }

        $provider = preg_replace('/[^a-z0-9-]+/', '', $provider);

        return $provider !== '' ? $provider : 'unknown';
    }

    /**
     * Normalize review author names with a safe fallback.
     */
    private static function normalize_review_author(string $author_name): string
    {
        $author_name = trim(wp_strip_all_tags($author_name));

        return $author_name !== '' ? $author_name : 'Guest';
    }

    /**
     * Normalize review text content to plain text.
     */
    private static function normalize_review_text(string $value): ?string
    {
        $value = trim(wp_strip_all_tags($value));

        return $value !== '' ? html_entity_decode($value, ENT_QUOTES, 'UTF-8') : null;
    }

    /**
     * Normalize source-language codes for API output.
     */
    private static function normalize_language_code(string $value): ?string
    {
        $value = strtolower(trim($value));

        return $value !== '' ? $value : null;
    }

    /**
     * Normalize stored rating values to floats.
     */
    private static function normalize_review_rating_raw($value): ?float
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $value = str_replace(',', '.', $value);
        if (!is_numeric($value)) {
            return null;
        }

        return round((float) $value, 1);
    }

    /**
     * Detect the original rating scale for a provider.
     */
    private static function get_review_rating_scale(string $provider, ?float $rating_raw): int
    {
        if (in_array($provider, ['google', 'tripadvisor', 'airbnb'], true)) {
            return 5;
        }

        if (in_array($provider, ['expedia', 'direct'], true)) {
            return 10;
        }

        if ($provider === 'booking') {
            return $rating_raw !== null && $rating_raw > 5 ? 10 : 5;
        }

        return $rating_raw !== null && $rating_raw > 5 ? 10 : 5;
    }

    /**
     * Normalize review ratings onto a /5 scale.
     */
    private static function normalize_review_rating(?float $rating_raw, int $rating_scale): ?float
    {
        if ($rating_raw === null || $rating_raw <= 0) {
            return null;
        }

        if ($rating_scale <= 0) {
            return round($rating_raw, 1);
        }

        return round(($rating_raw / $rating_scale) * 5, 1);
    }

    /**
     * Normalize review dates to YYYY-MM-DD.
     */
    private static function normalize_review_date(
        string $review_date,
        string $post_date = '',
        string $post_date_gmt = ''
    ): ?string {
        $review_date = trim($review_date);
        if ($review_date !== '') {
            try {
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $review_date)) {
                    $date = new DateTimeImmutable($review_date, wp_timezone());
                } else {
                    $date = new DateTimeImmutable($review_date, wp_timezone());
                }

                return $date->format('Y-m-d');
            } catch (Throwable $throwable) {
                // Fall back to the post date below.
            }
        }

        $created_at = self::format_post_datetime($post_date, $post_date_gmt);
        if (!$created_at) {
            return null;
        }

        try {
            $date = new DateTimeImmutable($created_at);
            return $date->setTimezone(wp_timezone())->format('Y-m-d');
        } catch (Throwable $throwable) {
            return null;
        }
    }

    /**
     * Compare scalars with null values sorted last.
     */
    private static function compare_nullable_scalars($left, $right): int
    {
        if ($left === $right) {
            return 0;
        }

        if ($left === null) {
            return 1;
        }

        if ($right === null) {
            return -1;
        }

        return $left <=> $right;
    }

    /**
     * Current month bounds in site timezone.
     */
    private static function get_current_month_bounds(): array
    {
        $timezone = wp_timezone();
        $now = new DateTimeImmutable('now', $timezone);

        return [
            'start' => $now->modify('first day of this month')->format('Y-m-d'),
            'end'   => $now->modify('last day of this month')->format('Y-m-d'),
        ];
    }

    /**
     * Best-effort booking currency code.
     */
    private static function get_currency_code(): string
    {
        if (function_exists('MPHB')) {
            try {
                $currency = MPHB()->settings()->currency()->getCurrencyCode();
                if (is_string($currency) && $currency !== '') {
                    return strtoupper($currency);
                }
            } catch (Throwable $throwable) {
                // Fall through to default.
            }
        }

        return 'EUR';
    }

    /**
     * Format booking post datetime as ISO 8601 in the site timezone.
     */
    private static function format_post_datetime(string $local_datetime, string $gmt_datetime = ''): ?string
    {
        $timezone = wp_timezone();

        try {
            if ($gmt_datetime !== '' && $gmt_datetime !== '0000-00-00 00:00:00') {
                $date = new DateTimeImmutable($gmt_datetime, new DateTimeZone('UTC'));
                return $date->setTimezone($timezone)->format(DATE_ATOM);
            }

            if ($local_datetime !== '' && $local_datetime !== '0000-00-00 00:00:00') {
                $date = new DateTimeImmutable($local_datetime, $timezone);
                return $date->format(DATE_ATOM);
            }
        } catch (Throwable $throwable) {
            return null;
        }

        return null;
    }

    /**
     * Normalize arbitrary datetime-ish values to ISO 8601 in site timezone.
     */
    private static function normalize_datetime_value(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || $value === '0000-00-00 00:00:00') {
            return null;
        }

        $timezone = wp_timezone();

        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $date = new DateTimeImmutable($value . ' 00:00:00', $timezone);
            } else {
                $date = new DateTimeImmutable($value, $timezone);
            }

            return $date->setTimezone($timezone)->format(DATE_ATOM);
        } catch (Throwable $throwable) {
            return null;
        }
    }

    /**
     * Normalize a UTC datetime stored without an offset.
     */
    private static function normalize_utc_datetime_value(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || $value === '0000-00-00 00:00:00') {
            return null;
        }

        try {
            $date = new DateTimeImmutable($value, new DateTimeZone('UTC'));
            return $date->setTimezone(wp_timezone())->format(DATE_ATOM);
        } catch (Throwable $throwable) {
            return null;
        }
    }

    /**
     * Calculate stay nights.
     */
    private static function calculate_nights(string $check_in, string $check_out): ?int
    {
        if ($check_in === '' || $check_out === '') {
            return null;
        }

        try {
            $start = new DateTimeImmutable($check_in, wp_timezone());
            $end = new DateTimeImmutable($check_out, wp_timezone());
            return max(0, (int) $start->diff($end)->days);
        } catch (Throwable $throwable) {
            return null;
        }
    }

    /**
     * Prepare SQL safely, supporting WPDB array args.
     */
    private static function prepare_query(string $sql, array $params = []): string
    {
        global $wpdb;

        if (empty($params)) {
            return $sql;
        }

        return $wpdb->prepare($sql, $params);
    }

    /**
     * Convert numeric-ish values to rounded floats.
     */
    private static function round_amount($value): float
    {
        return round((float) $value, 2);
    }

    /**
     * Check truthy postmeta values.
     */
    private static function meta_is_truthy($value): bool
    {
        return in_array((string) $value, ['1', 'true', 'yes'], true);
    }

    /**
     * Return trimmed string or null.
     */
    private static function nullable_string($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string !== '' ? $string : null;
    }
}
