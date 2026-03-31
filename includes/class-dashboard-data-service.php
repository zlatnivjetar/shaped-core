<?php
/**
 * Dashboard data service.
 *
 * Revenue and booking serializers for the external dashboard app.
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
            'month_basis'  => 'booked_at',
            'collected'    => [
                'month'    => self::get_revenue_total('collected', $month_bounds['start'], $month_bounds['end']),
                'all_time' => self::get_revenue_total('collected'),
            ],
            'pending'      => [
                'month'    => self::get_revenue_total('pending', $month_bounds['start'], $month_bounds['end']),
                'all_time' => self::get_revenue_total('pending'),
            ],
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
     * Sum collected or pending revenue.
     */
    private static function get_revenue_total(
        string $type,
        ?string $booked_from = null,
        ?string $booked_to = null
    ): float {
        global $wpdb;

        $confirmed_statuses = ['confirmed', 'publish', 'Confirmed'];
        $status_placeholders = implode(', ', array_fill(0, count($confirmed_statuses), '%s'));
        $params = $confirmed_statuses;

        if ($type === 'collected') {
            $sql = "
                SELECT COALESCE(SUM(CAST(amount.meta_value AS DECIMAL(10,2))), 0)
                FROM {$wpdb->posts} AS p
                INNER JOIN {$wpdb->postmeta} AS amount
                    ON p.ID = amount.post_id
                    AND amount.meta_key = '_shaped_payment_amount'
                INNER JOIN {$wpdb->postmeta} AS payment_status
                    ON p.ID = payment_status.post_id
                    AND payment_status.meta_key = '_shaped_payment_status'
                    AND payment_status.meta_value = 'completed'
                WHERE p.post_type = 'mphb_booking'
                    AND p.post_status IN ({$status_placeholders})
            ";
        } else {
            $sql = "
                SELECT COALESCE(SUM(CAST(pending_amount.meta_value AS DECIMAL(10,2))), 0)
                FROM {$wpdb->posts} AS p
                INNER JOIN {$wpdb->postmeta} AS pending_amount
                    ON p.ID = pending_amount.post_id
                    AND pending_amount.meta_key = '_stripe_pending_amount'
                INNER JOIN {$wpdb->postmeta} AS scheduled
                    ON p.ID = scheduled.post_id
                    AND scheduled.meta_key = '_shaped_charge_scheduled'
                    AND scheduled.meta_value IN ('1', 'true')
                LEFT JOIN {$wpdb->postmeta} AS charged
                    ON p.ID = charged.post_id
                    AND charged.meta_key = '_stripe_payment_charged'
                WHERE p.post_type = 'mphb_booking'
                    AND p.post_status IN ({$status_placeholders})
                    AND (
                        charged.meta_value IS NULL
                        OR charged.meta_value = ''
                        OR charged.meta_value IN ('0', 'false')
                    )
            ";
        }

        if ($booked_from) {
            $sql .= ' AND DATE(p.post_date) >= %s';
            $params[] = $booked_from;
        }

        if ($booked_to) {
            $sql .= ' AND DATE(p.post_date) <= %s';
            $params[] = $booked_to;
        }

        return self::round_amount($wpdb->get_var(self::prepare_query($sql, $params)));
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

        return array_merge([
            'page'             => $page,
            'per_page'         => $per_page,
            'sort'             => $sort,
            'order'            => $order,
            'statuses'         => $statuses,
            'payment_statuses' => $payment_statuses,
            'id'               => 0,
        ], $date_params);
    }

    /**
     * Query bookings list rows with pagination metadata.
     */
    private static function query_bookings(array $args): array
    {
        global $wpdb;

        $base_sql = self::get_bookings_base_sql();
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

        $base_sql = self::get_bookings_base_sql();
        [$from_where_sql, $params] = self::get_bookings_filter_sql($base_sql, ['id' => $booking_id]);
        $sql = "SELECT * {$from_where_sql} LIMIT 1";
        $row = $wpdb->get_row(self::prepare_query($sql, $params), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    /**
     * Base bookings aggregation query.
     */
    private static function get_bookings_base_sql(): string
    {
        global $wpdb;

        $dashboard_statuses = self::get_dashboard_booking_statuses();
        $status_placeholders = implode(', ', array_fill(0, count($dashboard_statuses), '%s'));

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
            WHERE p.post_type = 'mphb_booking'
                AND p.post_status IN ({$status_placeholders})
            GROUP BY p.ID
        ";

        return self::prepare_query($sql, $dashboard_statuses);
    }

    /**
     * Build filtered bookings query fragment and params.
     */
    private static function get_bookings_filter_sql(string $base_sql, array $args): array
    {
        $from_where_sql = "FROM ({$base_sql}) AS bookings WHERE 1=1";
        $params = [];

        if (!empty($args['id'])) {
            $from_where_sql .= ' AND bookings.ID = %d';
            $params[] = (int) $args['id'];
        }

        if (!empty($args['statuses'])) {
            $raw_statuses = self::expand_booking_status_filters($args['statuses']);
            $placeholders = implode(', ', array_fill(0, count($raw_statuses), '%s'));
            $from_where_sql .= " AND bookings.booking_status_raw IN ({$placeholders})";
            $params = array_merge($params, $raw_statuses);
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
            'booked_from'    => ['DATE(bookings.booked_at)', '>='],
            'booked_to'      => ['DATE(bookings.booked_at)', '<='],
        ] as $arg_key => $config) {
            if (!empty($args[$arg_key])) {
                $from_where_sql .= sprintf(' AND %s %s %%s', $config[0], $config[1]);
                $params[] = $args[$arg_key];
            }
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

        return [
            'primary_room_type_name' => $primary_room_type_name,
            'rate_name'              => $rate_name,
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
        ];
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
        $charge_scheduled = self::meta_is_truthy($summary['charge_scheduled']);
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
