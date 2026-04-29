<?php
/**
 * WP-CLI maintenance commands for payment data.
 *
 * @package Shaped_Core
 */

if (!defined('ABSPATH')) {
    exit;
}

class Shaped_Payment_Maintenance_CLI
{
    public static function register(): void
    {
        \WP_CLI::add_command(
            'shaped payments backfill-scheduled-amounts',
            [__CLASS__, 'backfill_scheduled_amounts']
        );
        \WP_CLI::add_command(
            'shaped payments backfill-collected-at',
            [__CLASS__, 'backfill_collected_at']
        );
    }

    /**
     * Backfill frozen scheduled-charge amount metadata.
     *
     * ## OPTIONS
     *
     * [--apply]
     * : Write metadata. Without this flag the command is a dry run.
     *
     * [--statuses=<statuses>]
     * : Comma-separated payment statuses. Default: authorized.
     *
     * [--include-processed]
     * : Include bookings already marked as charge processed.
     *
     * [--format=<format>]
     * : WP-CLI output format. Default: table.
     *
     * ## EXAMPLES
     *
     *     wp shaped payments backfill-scheduled-amounts
     *     wp shaped payments backfill-scheduled-amounts --apply
     */
    public static function backfill_scheduled_amounts(array $args, array $assoc_args): void
    {
        if (!class_exists('Shaped_Payment_Processor')) {
            \WP_CLI::error('Shaped_Payment_Processor is not available.');
        }

        $apply = isset($assoc_args['apply']);
        $include_processed = isset($assoc_args['include-processed']);
        $format = isset($assoc_args['format']) ? (string) $assoc_args['format'] : 'table';
        $statuses = self::parse_statuses((string) ($assoc_args['statuses'] ?? 'authorized'));

        if (empty($statuses)) {
            \WP_CLI::error('No valid statuses supplied.');
        }

        $rows = self::get_candidate_rows($statuses, $include_processed);
        if (empty($rows)) {
            \WP_CLI::success('No delayed scheduled-charge bookings matched.');
            return;
        }

        $items = [];
        $applied = 0;
        $skipped = 0;
        $warnings = 0;

        foreach ($rows as $row) {
            $booking_id = (int) $row->booking_id;
            $base_amount = round((float) $row->mphb_total_price, 2);
            $frozen_amount = self::choose_frozen_amount($row);
            $current_amount = self::calculate_current_amount($booking_id);
            $discount_percent = Shaped_Payment_Processor::infer_discount_percent_from_amounts($base_amount, $frozen_amount);
            $warning = self::build_warning($row, $base_amount, $frozen_amount, $discount_percent);

            if ($warning !== '') {
                $warnings++;
            }

            $action = 'dry-run';
            if ($apply) {
                if ($warning !== '') {
                    $skipped++;
                    $action = 'skipped';
                } else {
                    Shaped_Payment_Processor::freeze_scheduled_charge_amount(
                        $booking_id,
                        $base_amount,
                        $frozen_amount,
                        'backfill_from_stored_amounts',
                        true
                    );
                    $applied++;
                    $action = 'applied';
                }
            }

            $items[] = [
                'booking_id' => $booking_id,
                'status' => (string) $row->shaped_payment_status,
                'check_in' => (string) $row->check_in,
                'check_out' => (string) $row->check_out,
                'base' => self::format_amount($base_amount),
                'stored' => self::format_amount((float) $row->shaped_payment_amount),
                'pending' => self::format_amount((float) $row->stripe_pending_amount),
                'frozen' => self::format_amount($frozen_amount),
                'discount_percent' => $discount_percent === null ? '' : (string) round($discount_percent, 4),
                'current_calc' => $current_amount === null ? '' : self::format_amount($current_amount),
                'current_minus_frozen' => $current_amount === null ? '' : self::format_amount($current_amount - $frozen_amount),
                'charge_at' => (string) $row->shaped_charge_at,
                'action' => $action,
                'warning' => $warning,
            ];
        }

        \WP_CLI\Utils\format_items($format, $items, [
            'booking_id',
            'status',
            'check_in',
            'check_out',
            'base',
            'stored',
            'pending',
            'frozen',
            'discount_percent',
            'current_calc',
            'current_minus_frozen',
            'charge_at',
            'action',
            'warning',
        ]);

        if ($apply) {
            \WP_CLI::success(sprintf(
                'Backfill complete. Applied: %d. Skipped: %d. Warnings: %d.',
                $applied,
                $skipped,
                $warnings
            ));
        } else {
            \WP_CLI::log('Dry run only. Re-run with --apply after reviewing the table.');
            if ($warnings > 0) {
                \WP_CLI::warning(sprintf('%d row(s) need manual review before apply.', $warnings));
            }
        }
    }

    /**
     * Backfill payment collection timestamps from Stripe PaymentIntents.
     *
     * ## OPTIONS
     *
     * [--apply]
     * : Write metadata. Without this flag the command is a dry run.
     *
     * [--booking-id=<id>]
     * : Limit to one booking.
     *
     * [--force]
     * : Include bookings that already have collection metadata and overwrite it when applying.
     *
     * [--limit=<n>]
     * : Maximum rows to scan. Default: 50.
     *
     * [--format=<format>]
     * : WP-CLI output format. Default: table.
     *
     * ## EXAMPLES
     *
     *     wp shaped payments backfill-collected-at --booking-id=12345
     *     wp shaped payments backfill-collected-at --booking-id=12345 --apply
     *     wp shaped payments backfill-collected-at --booking-id=12345 --force --apply
     */
    public static function backfill_collected_at(array $args, array $assoc_args): void
    {
        if (!class_exists('Shaped_Payment_Processor')) {
            \WP_CLI::error('Shaped_Payment_Processor is not available.');
        }

        if (!function_exists('shaped_load_stripe_sdk') || !function_exists('shaped_get_stripe_secret')) {
            \WP_CLI::error('Stripe helpers are not available.');
        }

        $apply = isset($assoc_args['apply']);
        $force = isset($assoc_args['force']);
        $booking_id = isset($assoc_args['booking-id']) ? absint($assoc_args['booking-id']) : 0;
        $limit = isset($assoc_args['limit']) ? max(1, min(500, absint($assoc_args['limit']))) : 50;
        $format = isset($assoc_args['format']) ? (string) $assoc_args['format'] : 'table';

        $rows = self::get_collected_at_backfill_rows($booking_id, $limit, $force);
        if (empty($rows)) {
            \WP_CLI::success($force
                ? 'No completed bookings with Stripe PaymentIntents matched.'
                : 'No completed bookings with missing collection timestamps matched.'
            );
            return;
        }

        shaped_load_stripe_sdk();
        $stripe = new \Stripe\StripeClient(shaped_get_stripe_secret());
        $items = [];
        $applied = 0;
        $warnings = 0;

        foreach ($rows as $row) {
            $row_booking_id = (int) $row->booking_id;
            $payment_intent_id = (string) $row->stripe_payment_intent_id;
            $warning = '';
            $timestamp = null;
            $stripe_status = '';

            try {
                $payment_intent = $stripe->paymentIntents->retrieve($payment_intent_id, [
                    'expand' => ['latest_charge'],
                ]);
                $stripe_status = isset($payment_intent->status) ? (string) $payment_intent->status : '';

                if ($stripe_status !== 'succeeded') {
                    $warning = 'payment_intent_not_succeeded';
                } else {
                    $timestamp = self::get_payment_intent_collected_timestamp($stripe, $payment_intent);
                    if ($timestamp === null) {
                        $warning = 'missing_stripe_charge_timestamp';
                    }
                }
            } catch (\Throwable $e) {
                $warning = 'stripe_lookup_failed: ' . $e->getMessage();
            }

            if ($warning !== '') {
                $warnings++;
            } elseif ($apply && $timestamp !== null) {
                Shaped_Payment_Processor::record_payment_collected($row_booking_id, $timestamp, $force);
                $applied++;
            }

            $items[] = [
                'booking_id' => $row_booking_id,
                'payment_status' => (string) $row->shaped_payment_status,
                'payment_intent' => $payment_intent_id,
                'stripe_status' => $stripe_status,
                'current_collected_at' => (string) $row->shaped_payment_collected_at,
                'collected_at_utc' => $timestamp ? gmdate('Y-m-d H:i:s', $timestamp) : '',
                'collected_date' => $timestamp ? wp_date('Y-m-d', $timestamp, wp_timezone()) : '',
                'action' => $apply && $warning === '' ? ($force ? 'overwritten' : 'applied') : 'dry-run',
                'warning' => $warning,
            ];
        }

        \WP_CLI\Utils\format_items($format, $items, [
            'booking_id',
            'payment_status',
            'payment_intent',
            'stripe_status',
            'current_collected_at',
            'collected_at_utc',
            'collected_date',
            'action',
            'warning',
        ]);

        if ($apply) {
            \WP_CLI::success(sprintf(
                'Backfill complete. Applied: %d. Warnings: %d.',
                $applied,
                $warnings
            ));
        } else {
            \WP_CLI::log('Dry run only. Re-run with --apply after reviewing the table.');
        }
    }

    private static function parse_statuses(string $statuses_arg): array
    {
        $allowed = [
            'authorized',
            'card_update_required',
            'manual_review',
        ];
        $allowed_lookup = array_fill_keys($allowed, true);
        $statuses = array_filter(array_map('trim', explode(',', strtolower($statuses_arg))));

        return array_values(array_unique(array_filter(
            $statuses,
            static fn(string $status): bool => isset($allowed_lookup[$status])
        )));
    }

    private static function get_candidate_rows(array $statuses, bool $include_processed): array
    {
        global $wpdb;

        $status_placeholders = implode(', ', array_fill(0, count($statuses), '%s'));
        $processed_clause = $include_processed
            ? ''
            : "AND (shaped_charge_processed IS NULL OR shaped_charge_processed = '')";

        $sql = "
            SELECT
                p.ID AS booking_id,
                p.post_status,
                p.post_date,
                MAX(CASE WHEN pm.meta_key IN ('mphb_check_in_date','_mphb_check_in_date') THEN pm.meta_value END) AS check_in,
                MAX(CASE WHEN pm.meta_key IN ('mphb_check_out_date','_mphb_check_out_date') THEN pm.meta_value END) AS check_out,
                MAX(CASE WHEN pm.meta_key='mphb_total_price' THEN pm.meta_value END) AS mphb_total_price,
                MAX(CASE WHEN pm.meta_key='_shaped_payment_amount' THEN pm.meta_value END) AS shaped_payment_amount,
                MAX(CASE WHEN pm.meta_key='_stripe_pending_amount' THEN pm.meta_value END) AS stripe_pending_amount,
                MAX(CASE WHEN pm.meta_key='_shaped_price_final_amount' THEN pm.meta_value END) AS shaped_price_final_amount,
                MAX(CASE WHEN pm.meta_key='_shaped_payment_status' THEN pm.meta_value END) AS shaped_payment_status,
                MAX(CASE WHEN pm.meta_key='_shaped_payment_mode' THEN pm.meta_value END) AS shaped_payment_mode,
                MAX(CASE WHEN pm.meta_key='_shaped_charge_at' THEN pm.meta_value END) AS shaped_charge_at,
                MAX(CASE WHEN pm.meta_key='_shaped_charge_processed' THEN pm.meta_value END) AS shaped_charge_processed
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
            WHERE p.post_type = 'mphb_booking'
            GROUP BY p.ID
            HAVING shaped_payment_mode = 'delayed'
                AND shaped_payment_status IN ({$status_placeholders})
                AND (
                    CAST(COALESCE(shaped_price_final_amount, '0') AS DECIMAL(10,2)) > 0
                    OR CAST(COALESCE(shaped_payment_amount, '0') AS DECIMAL(10,2)) > 0
                    OR CAST(COALESCE(stripe_pending_amount, '0') AS DECIMAL(10,2)) > 0
                )
                {$processed_clause}
            ORDER BY shaped_charge_at ASC, check_in ASC
        ";

        $prepared = call_user_func_array([$wpdb, 'prepare'], array_merge([$sql], $statuses));
        return $wpdb->get_results($prepared);
    }

    private static function choose_frozen_amount(object $row): float
    {
        foreach (['shaped_price_final_amount', 'shaped_payment_amount', 'stripe_pending_amount'] as $field) {
            $amount = isset($row->{$field}) ? (float) $row->{$field} : 0.0;
            if ($amount > 0) {
                return round($amount, 2);
            }
        }

        return 0.0;
    }

    private static function calculate_current_amount(int $booking_id): ?float
    {
        if (!function_exists('MPHB') || !class_exists('Shaped_Pricing')) {
            return null;
        }

        try {
            $booking = MPHB()->getBookingRepository()->findById($booking_id, true);
            if (!$booking) {
                return null;
            }

            return round((float) Shaped_Pricing::calculate_final_amount($booking), 2);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function build_warning(object $row, float $base_amount, float $frozen_amount, ?float $discount_percent): string
    {
        if ($base_amount <= 0) {
            return 'missing_base_amount';
        }

        if ($frozen_amount <= 0) {
            return 'missing_frozen_amount';
        }

        if ($discount_percent === null) {
            return 'invalid_discount_inference';
        }

        $stored = (float) $row->shaped_payment_amount;
        $pending = (float) $row->stripe_pending_amount;
        if ($stored > 0 && $pending > 0 && abs($stored - $pending) > 0.01) {
            return 'stored_pending_mismatch';
        }

        return '';
    }

    private static function get_collected_at_backfill_rows(int $booking_id, int $limit, bool $force = false): array
    {
        global $wpdb;

        $where = "WHERE p.post_type = 'mphb_booking'";
        $params = [];

        if ($booking_id > 0) {
            $where .= ' AND p.ID = %d';
            $params[] = $booking_id;
        }

        $collected_clause = $force
            ? ''
            : "AND (shaped_payment_collected_at IS NULL OR shaped_payment_collected_at = '')";

        $sql = "
            SELECT
                p.ID AS booking_id,
                MAX(CASE WHEN pm.meta_key='_shaped_payment_status' THEN pm.meta_value END) AS shaped_payment_status,
                MAX(CASE WHEN pm.meta_key='_stripe_payment_intent_id' THEN pm.meta_value END) AS stripe_payment_intent_id,
                MAX(CASE WHEN pm.meta_key='_shaped_payment_collected_at' THEN pm.meta_value END) AS shaped_payment_collected_at
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
            {$where}
            GROUP BY p.ID
            HAVING shaped_payment_status IN ('completed', 'deposit_paid')
                {$collected_clause}
                AND stripe_payment_intent_id IS NOT NULL
                AND stripe_payment_intent_id <> ''
            ORDER BY p.ID DESC
            LIMIT %d
        ";

        $params[] = $limit;

        return $wpdb->get_results(self::prepare_cli_query($sql, $params));
    }

    private static function get_payment_intent_collected_timestamp(\Stripe\StripeClient $stripe, $payment_intent): ?int
    {
        if (isset($payment_intent->latest_charge) && is_object($payment_intent->latest_charge) && !empty($payment_intent->latest_charge->created)) {
            return (int) $payment_intent->latest_charge->created;
        }

        if (isset($payment_intent->latest_charge) && is_string($payment_intent->latest_charge) && $payment_intent->latest_charge !== '') {
            try {
                $charge = $stripe->charges->retrieve($payment_intent->latest_charge);
                if (!empty($charge->created)) {
                    return (int) $charge->created;
                }
            } catch (\Throwable $e) {
                return null;
            }
        }

        if (!empty($payment_intent->charges->data) && !empty($payment_intent->charges->data[0]->created)) {
            return (int) $payment_intent->charges->data[0]->created;
        }

        return !empty($payment_intent->created) ? (int) $payment_intent->created : null;
    }

    private static function prepare_cli_query(string $sql, array $params): string
    {
        global $wpdb;

        if (empty($params)) {
            return $sql;
        }

        return $wpdb->prepare($sql, $params);
    }

    private static function format_amount(float $amount): string
    {
        return number_format(round($amount, 2), 2, '.', '');
    }
}
