<?php
/**
 * Dashboard REST API — pricing & discount endpoints.
 *
 * Exposes read/write access to the settings managed by Shaped_Pricing so the
 * external shaped-dashboard can control them remotely.
 *
 * Namespace : shaped/v1  (same as Shaped_Dashboard_Api)
 * Auth      : X-Shaped-API-Key header via shaped_dashboard_auth()
 *
 * Endpoints:
 *   GET  /dashboard/pricing            — full pricing snapshot
 *   PUT  /dashboard/pricing/defaults   — update default per-room-type discounts
 *   PUT  /dashboard/pricing/seasons    — update recurring + override seasons
 *   PUT  /dashboard/pricing/payment    — update payment mode settings
 *
 * @package Shaped_Core
 */

if (!defined('ABSPATH')) {
    exit;
}

class Shaped_Dashboard_Pricing_Api {

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
        register_rest_route(self::NAMESPACE, '/dashboard/pricing', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_pricing'],
            'permission_callback' => 'shaped_dashboard_auth',
        ]);

        register_rest_route(self::NAMESPACE, '/dashboard/pricing/defaults', [
            'methods'             => 'PUT',
            'callback'            => [__CLASS__, 'update_defaults'],
            'permission_callback' => 'shaped_dashboard_auth',
        ]);

        register_rest_route(self::NAMESPACE, '/dashboard/pricing/seasons', [
            'methods'             => 'PUT',
            'callback'            => [__CLASS__, 'update_seasons'],
            'permission_callback' => 'shaped_dashboard_auth',
        ]);

        register_rest_route(self::NAMESPACE, '/dashboard/pricing/payment', [
            'methods'             => 'PUT',
            'callback'            => [__CLASS__, 'update_payment'],
            'permission_callback' => 'shaped_dashboard_auth',
        ]);
    }

    // =========================================================================
    // Handlers
    // =========================================================================

    /**
     * GET /dashboard/pricing
     *
     * Returns the full pricing configuration in one call.
     *
     * @return WP_REST_Response
     */
    public static function get_pricing(): WP_REST_Response {
        return new WP_REST_Response([
            'room_types'   => Shaped_Pricing::fetch_room_types(),
            'defaults'     => Shaped_Pricing::get_discounts(),
            'seasons'      => Shaped_Pricing::get_discount_seasons(),
            'payment'      => [
                'mode'                       => Shaped_Pricing::get_payment_mode(),
                'deposit_percent'            => Shaped_Pricing::get_deposit_percent(),
                'scheduled_charge_threshold' => Shaped_Pricing::get_scheduled_threshold_days(),
            ],
            'generated_at' => gmdate('c'),
        ], 200);
    }

    /**
     * PUT /dashboard/pricing/defaults
     *
     * Update default flat discounts per room type.
     * Accepts: { "discounts": { "suite": 15, "apartment": 10, ... } }
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public static function update_defaults(WP_REST_Request $request) {
        $body = $request->get_json_params();

        if (!isset($body['discounts']) || !is_array($body['discounts'])) {
            return new WP_Error(
                'bad_request',
                'Missing or invalid "discounts" key; expected an object of room-type slugs to percentages.',
                ['status' => 400]
            );
        }

        $sanitized = Shaped_Pricing::sanitize_discounts($body['discounts']);
        update_option(Shaped_Pricing::OPT_DISCOUNTS, $sanitized);

        return new WP_REST_Response([
            'defaults'   => $sanitized,
            'updated_at' => gmdate('c'),
        ], 200);
    }

    /**
     * PUT /dashboard/pricing/seasons
     *
     * Update seasonal discount configuration.
     *
     * Accepts storage-format dates directly:
     *   recurring  → start_day / end_day in mm-dd  (e.g. "06-01")
     *   overrides  → start_date / end_date in yyyy-mm-dd (e.g. "2026-07-15")
     *
     * Returns 422 if any date ranges within the same tier overlap.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public static function update_seasons(WP_REST_Request $request) {
        $body = $request->get_json_params();

        if (!isset($body['recurring']) || !is_array($body['recurring'])) {
            return new WP_Error(
                'bad_request',
                'Missing or invalid "recurring" key; expected an array.',
                ['status' => 400]
            );
        }

        if (!isset($body['overrides']) || !is_array($body['overrides'])) {
            return new WP_Error(
                'bad_request',
                'Missing or invalid "overrides" key; expected an array.',
                ['status' => 400]
            );
        }

        $overlap = self::detect_season_overlaps($body);
        if ($overlap !== null) {
            return new WP_Error('overlapping_seasons', $overlap, ['status' => 422]);
        }

        $sanitized = Shaped_Pricing::sanitize_discount_seasons($body);
        update_option(Shaped_Pricing::OPT_DISCOUNT_SEASONS, $sanitized);

        return new WP_REST_Response([
            'seasons'    => $sanitized,
            'updated_at' => gmdate('c'),
        ], 200);
    }

    /**
     * PUT /dashboard/pricing/payment
     *
     * Update payment mode settings.
     * Accepts: { "mode": "deposit"|"scheduled", "deposit_percent": 30, "scheduled_charge_threshold": 7 }
     * "mode" is required. The other two fall back to existing saved values when absent.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public static function update_payment(WP_REST_Request $request) {
        $body = $request->get_json_params();

        if (!isset($body['mode']) || !in_array($body['mode'], ['scheduled', 'deposit'], true)) {
            return new WP_Error(
                'bad_request',
                'Missing or invalid "mode"; must be "scheduled" or "deposit".',
                ['status' => 400]
            );
        }

        $mode = Shaped_Pricing::sanitize_payment_mode($body['mode']);

        $deposit = isset($body['deposit_percent'])
            ? Shaped_Pricing::sanitize_deposit_percent($body['deposit_percent'])
            : Shaped_Pricing::get_deposit_percent();

        $threshold = isset($body['scheduled_charge_threshold'])
            ? Shaped_Pricing::sanitize_scheduled_threshold($body['scheduled_charge_threshold'])
            : Shaped_Pricing::get_scheduled_threshold_days();

        update_option(Shaped_Pricing::OPT_PAYMENT_MODE, $mode);
        update_option(Shaped_Pricing::OPT_DEPOSIT_PERCENT, $deposit);
        update_option(Shaped_Pricing::OPT_SCHEDULED_CHARGE_THRESHOLD, $threshold);

        return new WP_REST_Response([
            'payment' => [
                'mode'                       => $mode,
                'deposit_percent'            => $deposit,
                'scheduled_charge_threshold' => $threshold,
            ],
            'updated_at' => gmdate('c'),
        ], 200);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Pre-validate season input for overlapping date ranges.
     *
     * Run this before passing input to Shaped_Pricing::sanitize_discount_seasons()
     * so that overlapping entries produce a 422 rather than being silently dropped.
     *
     * Expects storage-format dates:
     *   recurring entries : start_day / end_day in mm-dd
     *   override entries  : start_date / end_date in yyyy-mm-dd
     *
     * Entries with unrecognised date formats are skipped (the sanitizer will
     * discard them anyway, so there is nothing to overlap-check).
     *
     * @param array $input Raw request body with 'recurring' and 'overrides' keys.
     * @return string|null Human-readable error message, or null when no overlaps found.
     */
    private static function detect_season_overlaps(array $input): ?string {
        // --- Recurring (mm-dd ranges, year-agnostic, may wrap across year boundary) ---
        if (!empty($input['recurring'])) {
            $accepted = [];
            foreach ($input['recurring'] as $i => $season) {
                $start = isset($season['start_day']) ? (string) $season['start_day'] : '';
                $end   = isset($season['end_day'])   ? (string) $season['end_day']   : '';

                if (!preg_match('/^\d{2}-\d{2}$/', $start) || !preg_match('/^\d{2}-\d{2}$/', $end)) {
                    continue;
                }

                foreach ($accepted as $j => $prev) {
                    if (self::recurring_ranges_overlap($prev['start'], $prev['end'], $start, $end)) {
                        return sprintf(
                            'Recurring seasons overlap: entry %d (%s–%s) overlaps entry %d (%s–%s).',
                            $j + 1, $prev['start'], $prev['end'],
                            $i + 1, $start, $end
                        );
                    }
                }

                $accepted[] = ['start' => $start, 'end' => $end];
            }
        }

        // --- Overrides (yyyy-mm-dd full dates) ---
        if (!empty($input['overrides'])) {
            $accepted = [];
            foreach ($input['overrides'] as $i => $override) {
                $start = isset($override['start_date']) ? (string) $override['start_date'] : '';
                $end   = isset($override['end_date'])   ? (string) $override['end_date']   : '';

                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
                    continue;
                }

                foreach ($accepted as $j => $prev) {
                    if ($start <= $prev['end'] && $end >= $prev['start']) {
                        return sprintf(
                            'Override seasons overlap: entry %d (%s–%s) overlaps entry %d (%s–%s).',
                            $j + 1, $prev['start'], $prev['end'],
                            $i + 1, $start, $end
                        );
                    }
                }

                $accepted[] = ['start' => $start, 'end' => $end];
            }
        }

        return null;
    }

    /**
     * Check if two recurring mm-dd ranges overlap (handles year-wrap).
     *
     * Mirrors the logic in Shaped_Pricing::recurring_ranges_overlap() (private).
     *
     * @param string $a_start mm-dd
     * @param string $a_end   mm-dd
     * @param string $b_start mm-dd
     * @param string $b_end   mm-dd
     * @return bool
     */
    private static function recurring_ranges_overlap(
        string $a_start,
        string $a_end,
        string $b_start,
        string $b_end
    ): bool {
        $in_range = static function (string $point, string $start, string $end): bool {
            if ($start <= $end) {
                return $point >= $start && $point <= $end;
            }
            // Wrapping range (e.g. 11-01 to 02-28)
            return $point >= $start || $point <= $end;
        };

        return $in_range($b_start, $a_start, $a_end)
            || $in_range($b_end,   $a_start, $a_end)
            || $in_range($a_start, $b_start, $b_end)
            || $in_range($a_end,   $b_start, $b_end);
    }
}
