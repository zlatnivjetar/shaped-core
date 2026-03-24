<?php
/**
 * RoomCloud configuration and inventory validation.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Shaped_RC_Validation_Service
{
    /**
     * Run RoomCloud validation.
     *
     * @return array<string, mixed>
     */
    public static function validate(?int $days = null): array
    {
        $snapshot = Shaped_RC_API::get_configuration_snapshot();
        $mode = Shaped_RC_Availability_Manager::get_availability_mode();
        $room_mapping = Shaped_RC_Availability_Manager::get_room_mapping();
        $checks = [];
        $room_coverage = [];
        $horizon_days = self::get_effective_horizon_days($days);
        $today = new DateTime('today', wp_timezone());
        $horizon_end = (clone $today)->modify('+' . $horizon_days . ' days');
        $room_types = get_posts([
            'post_type' => 'mphb_room_type',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        $last_inventory_update = Shaped_RC_Availability_Manager::get_last_inventory_update();

        self::add_check(
            $checks,
            !empty($snapshot['service_url']) && !empty($snapshot['username']) && !empty($snapshot['password_configured']) ? 'pass' : 'fail',
            'credentials',
            'RoomCloud credentials',
            !empty($snapshot['service_url']) && !empty($snapshot['username']) && !empty($snapshot['password_configured'])
                ? 'Configured in wp-config.php.'
                : 'Missing one or more of SHAPED_RC_SERVICE_URL, SHAPED_RC_USERNAME, or SHAPED_RC_PASSWORD.'
        );

        self::add_check(
            $checks,
            !empty($snapshot['hotel_id']) && !empty($snapshot['rate_id']) && !empty($snapshot['channel_id']) ? 'pass' : 'fail',
            'identifiers',
            'RoomCloud property identifiers',
            !empty($snapshot['hotel_id']) && !empty($snapshot['rate_id']) && !empty($snapshot['channel_id'])
                ? 'Hotel ID, rate ID, and channel ID are configured.'
                : 'Hotel ID, rate ID, or SHAPED_RC_CHANNEL_ID is missing.'
        );

        self::add_check(
            $checks,
            in_array($mode, ['motopress', 'roomcloud_strict'], true) ? 'pass' : 'fail',
            'availability_mode',
            'Availability mode',
            sprintf('Current mode: %s.', $mode)
        );

        self::add_check(
            $checks,
            $last_inventory_update ? 'pass' : 'warn',
            'last_inventory_update',
            'Last inbound inventory update',
            $last_inventory_update
                ? sprintf('Last RoomCloud inventory update received at %s.', $last_inventory_update)
                : 'No RoomCloud inventory update has been recorded yet.'
        );

        $missing_mappings = [];
        $resolved_roomcloud_ids = [];

        foreach ($room_types as $room_type_post) {
            if (!($room_type_post instanceof WP_Post)) {
                continue;
            }

            $roomcloud_id = Shaped_RC_Availability_Manager::get_roomcloud_id_for_room_type((int) $room_type_post->ID);

            if (empty($roomcloud_id)) {
                $missing_mappings[$room_type_post->post_name] = $room_type_post->post_title;
                continue;
            }

            $resolved_roomcloud_ids[(int) $room_type_post->ID] = (string) $roomcloud_id;
        }

        $duplicate_roomcloud_ids = [];
        $mapping_counts = array_count_values(array_values($resolved_roomcloud_ids));
        foreach ($mapping_counts as $roomcloud_id => $count) {
            if ($count > 1) {
                $duplicate_roomcloud_ids[] = (string) $roomcloud_id;
            }
        }

        if ($mode === 'roomcloud_strict') {
            self::add_check(
                $checks,
                empty($room_mapping) ? 'fail' : 'pass',
                'room_mapping_exists',
                'Room mapping configured',
                empty($room_mapping)
                    ? 'No RoomCloud room mapping is configured.'
                    : sprintf('%d mapped room type(s) found.', count($room_mapping))
            );

            self::add_check(
                $checks,
                empty($missing_mappings) ? 'pass' : 'fail',
                'published_room_types_mapped',
                'Published MotoPress room types mapped',
                empty($missing_mappings)
                    ? 'All published MotoPress room types are mapped.'
                    : 'Missing mappings for: ' . implode(', ', array_keys($missing_mappings))
            );

            self::add_check(
                $checks,
                empty($duplicate_roomcloud_ids) ? 'pass' : 'fail',
                'duplicate_roomcloud_ids',
                'Duplicate RoomCloud room IDs',
                empty($duplicate_roomcloud_ids)
                    ? 'No duplicate RoomCloud room IDs detected.'
                    : 'Duplicate RoomCloud IDs found: ' . implode(', ', $duplicate_roomcloud_ids)
            );
        } else {
            self::add_check(
                $checks,
                empty($room_mapping) ? 'warn' : 'pass',
                'room_mapping_exists',
                'Room mapping configured',
                empty($room_mapping)
                    ? 'No RoomCloud room mapping is configured. This is acceptable in MotoPress mode.'
                    : sprintf('%d mapped room type(s) found.', count($room_mapping))
            );

            self::add_check(
                $checks,
                empty($duplicate_roomcloud_ids) ? 'pass' : 'warn',
                'duplicate_roomcloud_ids',
                'Duplicate RoomCloud room IDs',
                empty($duplicate_roomcloud_ids)
                    ? 'No duplicate RoomCloud room IDs detected.'
                    : 'Duplicate RoomCloud IDs found: ' . implode(', ', $duplicate_roomcloud_ids)
            );
        }

        foreach ($room_types as $room_type_post) {
            if (!($room_type_post instanceof WP_Post)) {
                continue;
            }

            $room_type_id = (int) $room_type_post->ID;
            $roomcloud_id = Shaped_RC_Availability_Manager::get_roomcloud_id_for_room_type($room_type_id);
            $coverage_status = 'pass';
            $message = 'Coverage complete through the validation horizon.';
            $first_missing_date = null;

            if (!$roomcloud_id) {
                $coverage_status = $mode === 'roomcloud_strict' ? 'fail' : 'warn';
                $message = 'Room type is not mapped to RoomCloud.';
            } else {
                $stay = Shaped_RC_Availability_Manager::inspect_roomcloud_stay($roomcloud_id, $today, $horizon_end);

                if (!$stay['has_complete_data']) {
                    $first_missing_date = !empty($stay['missing_dates']) ? $stay['missing_dates'][0] : null;
                    $coverage_status = $mode === 'roomcloud_strict' ? 'fail' : 'warn';
                    $message = $first_missing_date
                        ? sprintf('First missing inventory date: %s.', $first_missing_date)
                        : 'Inventory coverage is incomplete.';
                }
            }

            $room_coverage[] = [
                'slug' => $room_type_post->post_name,
                'label' => $room_type_post->post_title,
                'room_type_id' => $room_type_id,
                'roomcloud_id' => $roomcloud_id,
                'status' => $coverage_status,
                'first_missing_date' => $first_missing_date,
                'message' => $message,
            ];
        }

        if (!empty($room_coverage)) {
            $coverage_failures = array_filter($room_coverage, function ($row) {
                return $row['status'] === 'fail';
            });
            $coverage_warnings = array_filter($room_coverage, function ($row) {
                return $row['status'] === 'warn';
            });

            $coverage_status = !empty($coverage_failures)
                ? 'fail'
                : (!empty($coverage_warnings) ? 'warn' : 'pass');

            self::add_check(
                $checks,
                $coverage_status,
                'inventory_coverage',
                'Inventory coverage through booking horizon',
                $coverage_status === 'pass'
                    ? sprintf('All mapped room types have RoomCloud coverage for the next %d day(s).', $horizon_days)
                    : sprintf('Review room-level coverage for the next %d day(s).', $horizon_days)
            );
        }

        $status = self::calculate_overall_status($checks);

        return [
            'status' => $status,
            'mode' => $mode,
            'horizon_days' => $horizon_days,
            'last_inventory_update' => $last_inventory_update,
            'checks' => $checks,
            'room_coverage' => $room_coverage,
        ];
    }

    public static function get_effective_horizon_days(?int $days = null): int
    {
        if ($days !== null && $days > 0) {
            return $days;
        }

        if (function_exists('mphb_availability_facade')) {
            $max_advance = (int) mphb_availability_facade()->getMaxAdvanceReservationDaysCount(
                0,
                new DateTime('today', wp_timezone()),
                false
            );

            if ($max_advance > 0) {
                return $max_advance;
            }
        }

        return 365;
    }

    /**
     * @param array<int, array<string, string>> $checks
     */
    private static function add_check(array &$checks, string $status, string $code, string $label, string $message): void
    {
        $checks[] = [
            'status' => $status,
            'code' => $code,
            'label' => $label,
            'message' => $message,
        ];
    }

    /**
     * @param array<int, array<string, string>> $checks
     */
    private static function calculate_overall_status(array $checks): string
    {
        foreach ($checks as $check) {
            if (($check['status'] ?? '') === 'fail') {
                return 'fail';
            }
        }

        foreach ($checks as $check) {
            if (($check['status'] ?? '') === 'warn') {
                return 'warn';
            }
        }

        return 'pass';
    }
}
