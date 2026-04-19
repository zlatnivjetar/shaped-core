<?php
/**
 * Dashboard Availability Service
 *
 * Resolves room types, RoomCloud mappings, and per-date inventory into a
 * dashboard-ready snapshot with KPI summaries.
 *
 * Namespace : shaped/v1
 *
 * @package Shaped_Core
 */

if (!defined('ABSPATH')) {
    exit;
}

class Shaped_Dashboard_Availability_Service {

    /**
     * Build the full availability payload.
     *
     * @param string $month     YYYY-MM  – which month's matrix to return
     * @param string $date_from Y-m-d    – KPI range start
     * @param string $date_to   Y-m-d    – KPI range end
     * @return array|WP_Error
     */
    public static function get_availability(string $month, string $date_from, string $date_to) {
        $tz = wp_timezone();

        $month_start = DateTime::createFromFormat('Y-m-d', $month . '-01', $tz);
        if (!$month_start) {
            return new WP_Error('invalid_month', 'Expected month in YYYY-MM format.', ['status' => 400]);
        }
        $month_end = clone $month_start;
        $month_end->modify('last day of this month');
        $visible_end = clone $month_start;
        $visible_end->modify('+2 months');
        $visible_end->modify('last day of this month');

        $range_from = DateTime::createFromFormat('Y-m-d', $date_from, $tz);
        $range_to   = DateTime::createFromFormat('Y-m-d', $date_to, $tz);

        if (!$range_from || !$range_to) {
            return new WP_Error('invalid_dates', 'Expected date_from and date_to in YYYY-MM-DD format.', ['status' => 400]);
        }
        if ($range_from > $range_to) {
            return new WP_Error('invalid_range', 'date_from must not be after date_to.', ['status' => 400]);
        }

        $inventory   = class_exists('Shaped_RC_Availability_Manager')
            ? Shaped_RC_Availability_Manager::get_inventory()
            : [];

        $visible_dates = self::date_range($month_start, $visible_end);
        $range_dates = self::date_range($range_from, $range_to);
        $today       = new DateTime('today', $tz);
        $next7_end   = (clone $today)->modify('+6 days');
        $next7_dates = self::date_range($today, $next7_end);

        $room_infos = self::resolve_room_types();
        $coverage_by_slug = class_exists('Shaped_RC_Availability_Manager')
            ? Shaped_RC_Availability_Manager::get_inventory_coverage($visible_dates)
            : [];
        $mapped_coverage_by_slug = array_filter(
            $coverage_by_slug,
            static fn(array $row): bool => !empty($row['mapped'])
        );

        $room_types_payload = [];
        $range_grid         = [];
        $next7_grid         = [];

        foreach ($room_infos as $rt) {
            $month_cells = [];
            foreach ($visible_dates as $date_str) {
                $month_cells[] = self::build_cell($inventory, $rt['roomcloud_id'], $date_str);
            }

            $range_grid[$rt['slug']] = [];
            foreach ($range_dates as $date_str) {
                $range_grid[$rt['slug']][$date_str] = self::build_cell($inventory, $rt['roomcloud_id'], $date_str);
            }

            $next7_grid[$rt['slug']] = [];
            foreach ($next7_dates as $date_str) {
                $next7_grid[$rt['slug']][$date_str] = self::build_cell($inventory, $rt['roomcloud_id'], $date_str);
            }

            $room_types_payload[] = [
                'id'              => $rt['id'],
                'slug'            => $rt['slug'],
                'name'            => $rt['name'],
                'roomcloud_id'    => $rt['roomcloud_id'],
                'total_inventory' => $rt['total_inventory'],
                'dates'           => $month_cells,
                'coverage'        => !empty($coverage_by_slug[$rt['slug']]['mapped'] ?? false)
                    ? $coverage_by_slug[$rt['slug']]
                    : null,
            ];
        }

        $last_synced_at = null;
        if (class_exists('Shaped_RC_Availability_Manager')) {
            $raw_update = Shaped_RC_Availability_Manager::get_last_inventory_update();
            if ($raw_update) {
                try {
                    $last_synced_at = (new DateTime($raw_update, $tz))->format('c');
                } catch (Exception $e) {}
            }
        }

        $generated_at = gmdate('c');
        $data_version = $last_synced_at ?? $generated_at;

        return [
            'month' => [
                'key'        => $month_start->format('Y-m'),
                'start_date' => $month_start->format('Y-m-d'),
                'end_date'   => $month_end->format('Y-m-d'),
            ],
            'range' => [
                'date_from' => $date_from,
                'date_to'   => $date_to,
            ],
            'meta' => [
                'generated_at'   => $generated_at,
                'last_synced_at' => $last_synced_at,
                'data_version'   => $data_version,
            ],
            'coverage' => [
                'visible_window' => [
                    'start_date' => $visible_dates[0] ?? null,
                    'end_date'    => $visible_dates !== [] ? $visible_dates[count($visible_dates) - 1] : null,
                    'total_dates' => count($visible_dates),
                ],
                'room_types' => $mapped_coverage_by_slug,
            ],
            'summary'    => self::compute_summary($room_infos, $range_grid, $next7_grid, $range_dates, $next7_dates),
            'room_types' => $room_types_payload,
        ];
    }

    // =========================================================================
    // Internal helpers
    // =========================================================================

    /**
     * @return array<int, array{id: int, slug: string, name: string, roomcloud_id: string|null, total_inventory: int}>
     */
    private static function resolve_room_types(): array {
        $posts = get_posts([
            'post_type'      => 'mphb_room_type',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        usort($posts, fn($a, $b) => strcmp($a->post_title, $b->post_title));

        $infos = [];
        foreach ($posts as $post) {
            if (!($post instanceof WP_Post)) {
                continue;
            }
            $rt_id = (int) $post->ID;
            $infos[] = [
                'id'              => $rt_id,
                'slug'            => $post->post_name,
                'name'            => $post->post_title,
                'roomcloud_id'    => class_exists('Shaped_RC_Availability_Manager')
                    ? Shaped_RC_Availability_Manager::get_roomcloud_id_for_room_type($rt_id)
                    : null,
                'total_inventory' => self::get_total_inventory($rt_id),
            ];
        }

        return $infos;
    }

    /**
     * Count the number of physical mphb_room posts for a room type.
     * This is the stable inventory figure used for occupancy denominators.
     */
    private static function get_total_inventory(int $room_type_id): int {
        $rooms = get_posts([
            'post_type'      => 'mphb_room',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [[
                'key'   => 'mphb_room_type_id',
                'value' => $room_type_id,
                'type'  => 'NUMERIC',
            ]],
        ]);

        return is_array($rooms) ? count($rooms) : 0;
    }

    /**
     * Build a single availability cell.
     *
     * Explicitly checks array_key_exists so that a stored 0 is "full" (not "no_data"),
     * and a missing key is always "no_data".
     */
    private static function build_cell(array $inventory, ?string $roomcloud_id, string $date_str): array {
        if ($roomcloud_id === null) {
            return ['date' => $date_str, 'available_units' => null, 'state' => 'no_data'];
        }

        $rc_inv = isset($inventory[$roomcloud_id]) && is_array($inventory[$roomcloud_id])
            ? $inventory[$roomcloud_id]
            : [];

        if (!array_key_exists($date_str, $rc_inv)) {
            return ['date' => $date_str, 'available_units' => null, 'state' => 'no_data'];
        }

        $units = (int) $rc_inv[$date_str];
        $state = match (true) {
            $units >= 2 => 'available',
            $units === 1 => 'low',
            default => 'full',
        };

        return ['date' => $date_str, 'available_units' => $units, 'state' => $state];
    }

    /**
     * Generate an inclusive list of Y-m-d strings from $from to $to.
     *
     * @return string[]
     */
    private static function date_range(DateTime $from, DateTime $to): array {
        $dates   = [];
        $current = clone $from;
        $current->setTime(0, 0, 0);
        $end = clone $to;
        $end->setTime(0, 0, 0);

        while ($current <= $end) {
            $dates[] = $current->format('Y-m-d');
            $current->modify('+1 day');
        }

        return $dates;
    }

    /**
     * Compute all four KPI cards plus the global has_no_data flag.
     *
     * If any cell in the evaluated window is no_data, the affected KPI is set
     * to null rather than computed from partial data.
     */
    private static function compute_summary(
        array $room_infos,
        array $range_grid,
        array $next7_grid,
        array $range_dates,
        array $next7_dates
    ): array {
        // ── No-data detection ────────────────────────────────────────────────

        $range_has_no_data = false;
        foreach ($room_infos as $rt) {
            foreach ($range_dates as $date_str) {
                if (($range_grid[$rt['slug']][$date_str]['state'] ?? 'no_data') === 'no_data') {
                    $range_has_no_data = true;
                    break 2;
                }
            }
        }

        $next7_has_no_data = false;
        foreach ($room_infos as $rt) {
            foreach ($next7_dates as $date_str) {
                if (($next7_grid[$rt['slug']][$date_str]['state'] ?? 'no_data') === 'no_data') {
                    $next7_has_no_data = true;
                    break 2;
                }
            }
        }

        // ── KPI 1: Occupancy in selected range ───────────────────────────────

        $occupancy_percent = null;
        if (!$range_has_no_data) {
            $total_nights    = 0;
            $open_nights     = 0;
            foreach ($room_infos as $rt) {
                if ($rt['total_inventory'] <= 0) {
                    continue;
                }
                $slug = $rt['slug'];
                foreach ($range_dates as $date_str) {
                    $total_nights += $rt['total_inventory'];
                    $open_nights  += (int) ($range_grid[$slug][$date_str]['available_units'] ?? 0);
                }
            }
            if ($total_nights > 0) {
                $occupancy_percent = round(($total_nights - $open_nights) / $total_nights * 100, 1);
            }
        }

        // ── KPI 2: Open inventory next 7 days ───────────────────────────────

        $open_next7 = null;
        if (!$next7_has_no_data) {
            $open_next7 = 0;
            foreach ($room_infos as $rt) {
                $slug = $rt['slug'];
                foreach ($next7_dates as $date_str) {
                    $open_next7 += (int) ($next7_grid[$slug][$date_str]['available_units'] ?? 0);
                }
            }
        }

        // ── KPI 3: Weakest room type in selected range ───────────────────────

        $weakest_room_type = null;
        if (!$range_has_no_data) {
            $min_occ = null;
            foreach ($room_infos as $rt) {
                if ($rt['total_inventory'] <= 0) {
                    continue;
                }
                $slug        = $rt['slug'];
                $rt_total    = $rt['total_inventory'] * count($range_dates);
                $rt_open     = 0;
                foreach ($range_dates as $date_str) {
                    $rt_open += (int) ($range_grid[$slug][$date_str]['available_units'] ?? 0);
                }
                $occ_pct = round(($rt_total - $rt_open) / $rt_total * 100, 1);

                if ($min_occ === null || $occ_pct < $min_occ) {
                    $min_occ           = $occ_pct;
                    $weakest_room_type = [
                        'room_type_slug'    => $rt['slug'],
                        'room_type_name'    => $rt['name'],
                        'occupancy_percent' => $occ_pct,
                    ];
                }
            }
        }

        // ── KPI 4: Weakest contiguous 7-day stretch ──────────────────────────

        $weakest_7day = null;
        $n            = count($range_dates);

        if (!$range_has_no_data && $n >= 7) {
            $min_occ = null;

            for ($i = 0; $i <= $n - 7; $i++) {
                $window      = array_slice($range_dates, $i, 7);
                $win_total   = 0;
                $win_open    = 0;

                foreach ($room_infos as $rt) {
                    if ($rt['total_inventory'] <= 0) {
                        continue;
                    }
                    $slug = $rt['slug'];
                    foreach ($window as $date_str) {
                        $win_total += $rt['total_inventory'];
                        $win_open  += (int) ($range_grid[$slug][$date_str]['available_units'] ?? 0);
                    }
                }

                if ($win_total <= 0) {
                    continue;
                }

                $occ_pct = round(($win_total - $win_open) / $win_total * 100, 1);

                if ($min_occ === null || $occ_pct < $min_occ) {
                    $min_occ      = $occ_pct;
                    $weakest_7day = [
                        'start_date'        => $window[0],
                        'end_date'          => $window[6],
                        'occupancy_percent' => $occ_pct,
                    ];
                }
            }
        }

        return [
            'occupancy_percent_in_range'  => $occupancy_percent,
            'open_inventory_next_7_days'  => $open_next7,
            'weakest_room_type'           => $weakest_room_type,
            'weakest_seven_day_window'    => $weakest_7day,
            'has_no_data'                 => $range_has_no_data || $next7_has_no_data,
        ];
    }
}
