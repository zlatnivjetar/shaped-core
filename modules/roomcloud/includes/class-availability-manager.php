<?php
/**
 * Availability Manager
 * RoomCloud inventory is the availability source of truth in strict mode.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Shaped_RC_Availability_Manager
{
    private static $instance = null;

    const INVENTORY_OPTION = 'shaped_rc_inventory';
    const INVENTORY_META_OPTION = 'shaped_rc_inventory_meta';
    const STALENESS_THRESHOLD_HOURS = 24;
    const CRON_HOOK = 'shaped_rc_staleness_check';

    private static $logged_block_decisions = [];

    /** @var array<string, array<string, mixed>> Per-request cache keyed by "roomcloud_id:from:to". */
    private static $stay_cache = [];

    public static function init()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        add_action('pre_get_posts', [$this, 'filter_room_type_query'], 999, 1);

        add_action('wp_ajax_shaped_rc_get_availability', [$this, 'ajax_get_availability']);
        add_action('wp_ajax_nopriv_shaped_rc_get_availability', [$this, 'ajax_get_availability']);
        add_action('wp_ajax_shaped_rc_get_calendar_inventory', [$this, 'ajax_get_calendar_inventory']);
        add_action('wp_ajax_nopriv_shaped_rc_get_calendar_inventory', [$this, 'ajax_get_calendar_inventory']);

        add_filter('mphb_room_available', [$this, 'filter_individual_room_availability'], 10, 3);
        add_filter('mphb_search_rooms_atts', [$this, 'filter_search_rooms_atts_for_roomcloud'], 10, 2);
        add_filter('mphb_get_booking_rules_for_date', [$this, 'filter_booking_rules_for_roomcloud'], 10, 3);
        add_filter('mphb_has_not_stay_in_rules', [$this, 'enable_not_stay_in_rules']);
        add_filter('mphb_sc_search_results_available_rooms_count', [$this, 'filter_search_results_available_rooms_count'], 10, 2);
        add_filter('mphb_sc_checkout_step_checkout_pre_validate_selected_rooms', [$this, 'validate_selected_rooms_for_strict_mode'], 10, 2);
    }

    public static function is_stale(): bool
    {
        $last = self::get_last_inventory_update();
        if (!$last) {
            return true;
        }
        return (time() - strtotime($last)) > self::STALENESS_THRESHOLD_HOURS * HOUR_IN_SECONDS;
    }

    public static function schedule_staleness_cron(): void
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK);
        }
    }

    public static function run_staleness_check(): void
    {
        if (self::is_stale()) {
            Shaped_RC_Error_Logger::log_critical('RoomCloud inventory is stale', [
                'last_update' => self::get_last_inventory_update(),
            ]);
        }
    }

    public static function get_availability_mode(): string
    {
        return shaped_get_roomcloud_availability_mode();
    }

    public static function is_strict_mode(): bool
    {
        return shaped_is_roomcloud_strict_mode();
    }

    /**
     * @return array<string, array<string, int>>
     */
    public static function get_inventory(): array
    {
        $inventory = get_option(self::INVENTORY_OPTION, []);

        if (!is_array($inventory)) {
            $inventory = [];
            update_option(self::INVENTORY_OPTION, $inventory);
        }

        return $inventory;
    }

    public static function get_inventory_meta(): array
    {
        $meta = get_option(self::INVENTORY_META_OPTION, []);

        return is_array($meta) ? $meta : [];
    }

    public static function get_last_inventory_update(): ?string
    {
        $meta = self::get_inventory_meta();

        return !empty($meta['last_update']) ? (string) $meta['last_update'] : null;
    }

    public static function update_inventory($roomcloud_id, $date, $quantity)
    {
        $roomcloud_id = trim((string) $roomcloud_id);
        $date = trim((string) $date);

        if ($roomcloud_id === '' || $date === '') {
            return;
        }

        $inventory = self::get_inventory();

        if (!isset($inventory[$roomcloud_id]) || !is_array($inventory[$roomcloud_id])) {
            $inventory[$roomcloud_id] = [];
        }

        $inventory[$roomcloud_id][$date] = intval($quantity);

        update_option(self::INVENTORY_OPTION, $inventory);
        self::touch_inventory_meta($roomcloud_id, $date);

        Shaped_RC_Error_Logger::log_info('Inventory updated from RoomCloud', [
            'roomcloud_id' => $roomcloud_id,
            'date' => $date,
            'quantity' => (int) $quantity,
        ]);
    }

    public static function get_availability($roomcloud_id, $date): ?int
    {
        $inventory = self::get_inventory();
        $roomcloud_id = trim((string) $roomcloud_id);
        $date = trim((string) $date);

        if ($roomcloud_id === '' || $date === '' || !isset($inventory[$roomcloud_id][$date])) {
            return null;
        }

        return intval($inventory[$roomcloud_id][$date]);
    }

    public static function get_availability_by_slug($room_slug, $date): ?int
    {
        $roomcloud_id = self::get_roomcloud_id_for_slug((string) $room_slug);

        if ($roomcloud_id === null) {
            return null;
        }

        return self::get_availability($roomcloud_id, $date);
    }

    /**
     * @return array<string, string>
     */
    public static function get_room_mapping(): array
    {
        $option_mapping = get_option('shaped_rc_room_mapping', []);

        if (!is_array($option_mapping)) {
            return [];
        }

        $mapping = [];

        foreach ($option_mapping as $slug => $roomcloud_id) {
            $slug = sanitize_title((string) $slug);
            $roomcloud_id = trim((string) $roomcloud_id);

            if ($slug === '' || $roomcloud_id === '') {
                continue;
            }

            $mapping[$slug] = $roomcloud_id;
        }

        return $mapping;
    }

    public static function get_roomcloud_id_for_slug(string $room_slug): ?string
    {
        $slug = sanitize_title($room_slug);
        $mapping = self::get_room_mapping();

        if (isset($mapping[$slug])) {
            return $mapping[$slug];
        }

        $room_type_post = self::find_room_type_post($slug);

        if (!$room_type_post) {
            return null;
        }

        return self::get_roomcloud_id_for_room_type((int) $room_type_post->ID);
    }

    public static function get_roomcloud_id_for_room_type(int $room_type_id): ?string
    {
        if ($room_type_id <= 0) {
            return null;
        }

        $mapping = self::get_room_mapping();

        foreach (self::get_room_mapping_keys_for_room_type($room_type_id) as $room_key) {
            if (isset($mapping[$room_key])) {
                return $mapping[$room_key];
            }
        }

        return null;
    }

    public static function is_room_type_mapped(int $room_type_id): bool
    {
        return self::get_roomcloud_id_for_room_type($room_type_id) !== null;
    }

    /**
     * @return string[]
     */
    public static function get_room_mapping_keys_for_room_type(int $room_type_id): array
    {
        if ($room_type_id <= 0) {
            return [];
        }

        $keys = [];

        $post_name = get_post_field('post_name', $room_type_id);
        if (!empty($post_name)) {
            $keys[] = sanitize_title((string) $post_name);
        }

        $title = get_the_title($room_type_id);
        if (!empty($title)) {
            $keys[] = sanitize_title((string) $title);
        }

        return array_values(array_unique(array_filter($keys)));
    }

    public static function find_room_type_post(string $room_lookup): ?WP_Post
    {
        $lookup = sanitize_title($room_lookup);

        if ($lookup === '') {
            return null;
        }

        $room_type_post = get_page_by_path($lookup, OBJECT, 'mphb_room_type');

        if ($room_type_post instanceof WP_Post) {
            return $room_type_post;
        }

        $room_posts = get_posts([
            'post_type' => 'mphb_room_type',
            'post_status' => 'publish',
            'posts_per_page' => -1,
        ]);

        foreach ($room_posts as $room_post) {
            if (!($room_post instanceof WP_Post)) {
                continue;
            }

            foreach (self::get_room_mapping_keys_for_room_type((int) $room_post->ID) as $candidate_key) {
                if ($candidate_key === $lookup) {
                    return $room_post;
                }
            }
        }

        return null;
    }

    /**
     * @return array{
     *   has_any_data: bool,
     *   has_complete_data: bool,
     *   available_units: ?int,
     *   missing_dates: string[],
     *   total_nights: int
     * }
     */
    public static function inspect_roomcloud_stay(string $roomcloud_id, DateTime $from_date, DateTime $to_date): array
    {
        $cache_key = $roomcloud_id . ':' . $from_date->format('Y-m-d') . ':' . $to_date->format('Y-m-d');

        if (array_key_exists($cache_key, self::$stay_cache)) {
            return self::$stay_cache[$cache_key];
        }

        $inventory = self::get_inventory();
        $room_inventory = isset($inventory[$roomcloud_id]) && is_array($inventory[$roomcloud_id])
            ? $inventory[$roomcloud_id]
            : [];

        $current = clone $from_date;
        $min_units = null;
        $has_any_data = false;
        $missing_dates = [];
        $total_nights = 0;

        while ($current < $to_date) {
            $date_str = $current->format('Y-m-d');
            $total_nights++;

            if (array_key_exists($date_str, $room_inventory)) {
                $has_any_data = true;
                $units = intval($room_inventory[$date_str]);
                $min_units = $min_units === null ? $units : min($min_units, $units);
            } else {
                $missing_dates[] = $date_str;
            }

            $current->modify('+1 day');
        }

        $result = [
            'has_any_data' => $has_any_data,
            'has_complete_data' => empty($missing_dates),
            'available_units' => $min_units,
            'missing_dates' => $missing_dates,
            'total_nights' => $total_nights,
        ];

        self::$stay_cache[$cache_key] = $result;

        return $result;
    }

    /**
     * @param DateTime|string $from_date
     * @param DateTime|string $to_date
     * @return array<string, mixed>
     */
    public static function evaluate_room_type_availability(int $room_type_id, $from_date, $to_date, int $requested_units = 1): array
    {
        $from = self::normalize_date($from_date);
        $to = self::normalize_date($to_date);
        $roomcloud_id = self::get_roomcloud_id_for_room_type($room_type_id);

        $decision = [
            'mode' => self::get_availability_mode(),
            'mapped' => $roomcloud_id !== null,
            'room_type_id' => $room_type_id,
            'roomcloud_id' => $roomcloud_id,
            'has_complete_data' => false,
            'available_units' => null,
            'is_sellable' => true,
            'source' => 'motopress',
            'reason' => 'motopress_mode',
            'missing_dates' => [],
            'requested_units' => max(1, $requested_units),
        ];

        if (!self::is_strict_mode()) {
            return $decision;
        }

        $decision['source'] = 'roomcloud';

        if ($roomcloud_id === null) {
            $decision['is_sellable'] = false;
            $decision['reason'] = 'unmapped_room_type';
            return $decision;
        }

        if (!$from || !$to || $from >= $to) {
            $decision['is_sellable'] = false;
            $decision['reason'] = 'missing_roomcloud_data';
            return $decision;
        }

        $stay = self::inspect_roomcloud_stay($roomcloud_id, $from, $to);
        $decision['has_complete_data'] = $stay['has_complete_data'];
        $decision['available_units'] = $stay['available_units'];
        $decision['missing_dates'] = $stay['missing_dates'];

        if (!$stay['has_complete_data']) {
            $decision['is_sellable'] = false;
            $decision['reason'] = 'missing_roomcloud_data';
            return $decision;
        }

        if ((int) $stay['available_units'] < $decision['requested_units']) {
            $decision['is_sellable'] = false;
            $decision['reason'] = 'insufficient_roomcloud_units';
            return $decision;
        }

        $decision['is_sellable'] = true;
        $decision['reason'] = 'roomcloud_available';

        return $decision;
    }

    /**
     * @param DateTime|string $from_date
     * @param DateTime|string $to_date
     * @return array<string, mixed>
     */
    public static function evaluate_room_slug_availability(string $room_slug, $from_date, $to_date, int $requested_units = 1): array
    {
        $room_slug = sanitize_title($room_slug);
        $room_type_post = self::find_room_type_post($room_slug);

        if (!$room_type_post) {
            return [
                'mode' => self::get_availability_mode(),
                'mapped' => false,
                'room_type_id' => 0,
                'roomcloud_id' => null,
                'has_complete_data' => false,
                'available_units' => null,
                'is_sellable' => false,
                'source' => self::is_strict_mode() ? 'roomcloud' : 'motopress',
                'reason' => 'unmapped_room_type',
                'missing_dates' => [],
                'requested_units' => max(1, $requested_units),
            ];
        }

        return self::evaluate_room_type_availability((int) $room_type_post->ID, $from_date, $to_date, $requested_units);
    }

    public static function is_available($roomcloud_id, $check_in, $check_out, $quantity = 1): bool
    {
        $from = self::normalize_date($check_in);
        $to = self::normalize_date($check_out);

        if (!$from || !$to || $from >= $to) {
            return false;
        }

        $stay = self::inspect_roomcloud_stay((string) $roomcloud_id, $from, $to);

        return $stay['has_complete_data'] && (int) $stay['available_units'] >= max(1, (int) $quantity);
    }

    /**
     * @return array<string, int|null>
     */
    public static function get_available_rooms($check_in, $check_out): array
    {
        $from = self::normalize_date($check_in);
        $to = self::normalize_date($check_out);
        $available = [];

        if (!$from || !$to || $from >= $to) {
            return $available;
        }

        foreach (self::get_room_mapping() as $slug => $roomcloud_id) {
            $stay = self::inspect_roomcloud_stay($roomcloud_id, $from, $to);

            if (self::is_strict_mode()) {
                $available[$slug] = $stay['has_complete_data'] ? (int) $stay['available_units'] : 0;
            } else {
                $available[$slug] = null;
            }
        }

        return $available;
    }

    public static function get_min_availability_for_stay(string $roomcloud_id, DateTime $from_date, DateTime $to_date): ?int
    {
        $stay = self::inspect_roomcloud_stay($roomcloud_id, $from_date, $to_date);

        return $stay['has_complete_data'] ? $stay['available_units'] : null;
    }

    public static function get_availability_for_room_type_date(int $room_type_id, DateTime $date): ?int
    {
        $roomcloud_id = self::get_roomcloud_id_for_room_type($room_type_id);

        if ($roomcloud_id === null) {
            return null;
        }

        return self::get_availability($roomcloud_id, $date->format('Y-m-d'));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function find_upgrade_options($original_slug, $check_in, $check_out)
    {
        $original_slug = sanitize_title((string) $original_slug);
        $original_post = self::find_room_type_post($original_slug);

        if (!$original_post) {
            return [];
        }

        $original_price = self::get_room_base_price((int) $original_post->ID);
        $from = self::normalize_date($check_in);
        $to = self::normalize_date($check_out);

        if (!$from || !$to || $from >= $to) {
            return [];
        }

        $upgrades = [];
        $room_types = get_posts([
            'post_type' => 'mphb_room_type',
            'post_status' => 'publish',
            'posts_per_page' => -1,
        ]);

        foreach ($room_types as $post) {
            if (!($post instanceof WP_Post)) {
                continue;
            }

            if ($post->post_name === $original_slug) {
                continue;
            }

            $decision = self::evaluate_room_type_availability((int) $post->ID, $from, $to, 1);

            if (self::is_strict_mode()) {
                if (!$decision['is_sellable']) {
                    continue;
                }
            } else {
                $motopress_count = self::get_motopress_available_room_count((int) $post->ID, $from, $to);
                if ($motopress_count < 1) {
                    continue;
                }
            }

            $price = self::get_room_base_price((int) $post->ID);

            if ($price > $original_price) {
                $upgrades[$post->post_name] = [
                    'room_type_id' => (int) $post->ID,
                    'price' => $price,
                    'price_diff' => $price - $original_price,
                ];
            }
        }

        uasort($upgrades, function ($a, $b) {
            return $a['price'] <=> $b['price'];
        });

        return $upgrades;
    }

    public function ajax_get_availability()
    {
        $check_in = isset($_POST['check_in']) ? sanitize_text_field(wp_unslash($_POST['check_in'])) : '';
        $check_out = isset($_POST['check_out']) ? sanitize_text_field(wp_unslash($_POST['check_out'])) : '';

        if ($check_in === '' || $check_out === '') {
            wp_send_json_error(['message' => 'Missing dates']);
            return;
        }

        wp_send_json_success(self::get_available_rooms($check_in, $check_out));
    }

    public function ajax_get_calendar_inventory()
    {
        $check_in = isset($_POST['check_in']) ? sanitize_text_field(wp_unslash($_POST['check_in'])) : '';
        $check_out = isset($_POST['check_out']) ? sanitize_text_field(wp_unslash($_POST['check_out'])) : '';
        $room_slug = isset($_POST['room_slug']) ? sanitize_text_field(wp_unslash($_POST['room_slug'])) : '';

        if ($check_in === '' || $check_out === '' || $room_slug === '') {
            wp_send_json_error(['message' => 'Missing parameters']);
            return;
        }

        $from = self::normalize_date($check_in);
        $to = self::normalize_date($check_out);
        $roomcloud_id = self::get_roomcloud_id_for_slug($room_slug);

        if (!$from || !$to || $from >= $to || $roomcloud_id === null) {
            wp_send_json_error(['message' => 'Room not found']);
            return;
        }

        $date_availability = [];
        $current = clone $from;

        while ($current < $to) {
            $date_str = $current->format('Y-m-d');
            $units = self::get_availability($roomcloud_id, $date_str);

            if ($units !== null) {
                $date_availability[$date_str] = $units;
            } elseif (self::is_strict_mode()) {
                $date_availability[$date_str] = 0;
            }

            $current->modify('+1 day');
        }

        wp_send_json_success($date_availability);
    }

    public function filter_individual_room_availability($is_available, $room_id, $search_params)
    {
        if (!$is_available || !self::is_strict_mode()) {
            return $is_available;
        }

        $room_type_id = (int) get_post_meta($room_id, 'mphb_room_type_id', true);
        if ($room_type_id <= 0) {
            return $is_available;
        }

        $check_in = isset($search_params['from_date']) && $search_params['from_date'] instanceof DateTime
            ? $search_params['from_date']
            : null;
        $check_out = isset($search_params['to_date']) && $search_params['to_date'] instanceof DateTime
            ? $search_params['to_date']
            : null;

        if (!$check_in || !$check_out) {
            return $is_available;
        }

        $decision = self::evaluate_room_type_availability($room_type_id, $check_in, $check_out, 1);

        if (!$decision['is_sellable']) {
            self::log_blocked_decision($decision, [
                'gate' => 'single_room',
                'room_id' => (int) $room_id,
            ]);

            return false;
        }

        return $is_available;
    }

    public function filter_room_type_query($query)
    {
        if (!self::is_strict_mode() || is_admin() || $query->get('post_type') !== 'mphb_room_type') {
            return;
        }

        $check_in = $query->get('mphb_check_in_date');
        $check_out = $query->get('mphb_check_out_date');

        if (empty($check_in) || empty($check_out)) {
            return;
        }

        $room_types = get_posts([
            'post_type' => 'mphb_room_type',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'post_status' => 'publish',
        ]);

        $unavailable_ids = [];

        foreach ($room_types as $room_type_id) {
            $decision = self::evaluate_room_type_availability((int) $room_type_id, $check_in, $check_out, 1);

            if (!$decision['is_sellable']) {
                $unavailable_ids[] = (int) $room_type_id;
                self::log_blocked_decision($decision, [
                    'gate' => 'room_type_query',
                ]);
            }
        }

        if (!empty($unavailable_ids)) {
            $query->set('post__not_in', array_values(array_unique($unavailable_ids)));
        }
    }

    public static function clear_room_inventory($roomcloud_id)
    {
        $inventory = self::get_inventory();
        $roomcloud_id = trim((string) $roomcloud_id);

        if (isset($inventory[$roomcloud_id])) {
            unset($inventory[$roomcloud_id]);
            update_option(self::INVENTORY_OPTION, $inventory);
        }
    }

    public static function clear_all_inventory()
    {
        delete_option(self::INVENTORY_OPTION);
        delete_option(self::INVENTORY_META_OPTION);

        Shaped_RC_Error_Logger::log_info('All inventory cleared');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function get_inventory_summary($days = 30): array
    {
        $inventory = self::get_inventory();
        $summary = [];
        $start = new DateTime('today', wp_timezone());

        foreach (self::get_room_mapping() as $slug => $roomcloud_id) {
            $room_data = [
                'slug' => $slug,
                'roomcloud_id' => $roomcloud_id,
                'dates' => [],
            ];

            for ($i = 0; $i < $days; $i++) {
                $date = clone $start;
                $date->modify("+{$i} days");
                $date_str = $date->format('Y-m-d');

                $room_data['dates'][$date_str] = isset($inventory[$roomcloud_id][$date_str])
                    ? intval($inventory[$roomcloud_id][$date_str])
                    : null;
            }

            $summary[$slug] = $room_data;
        }

        return $summary;
    }

    /**
     * Build a per-room-type coverage report for the locally stored inventory.
     *
     * @param string[] $window_dates
     * @return array<string, array<string, mixed>>
     */
    public static function get_inventory_coverage(array $window_dates = []): array
    {
        $inventory = self::get_inventory();
        $coverage = [];
        $window_dates = array_values(array_filter(array_map('strval', $window_dates)));
        sort($window_dates);

        $room_types = get_posts([
            'post_type'      => 'mphb_room_type',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        foreach ($room_types as $room_type_post) {
            if (!($room_type_post instanceof WP_Post)) {
                continue;
            }

            $room_type_id = (int) $room_type_post->ID;
            $roomcloud_id = self::get_roomcloud_id_for_room_type($room_type_id);
            $room_inventory = ($roomcloud_id !== null && isset($inventory[$roomcloud_id]) && is_array($inventory[$roomcloud_id]))
                ? $inventory[$roomcloud_id]
                : [];

            $stored_dates = array_keys($room_inventory);
            sort($stored_dates);

            $stored_dates_count = count($stored_dates);
            $first_stored_date = $stored_dates_count > 0 ? $stored_dates[0] : null;
            $last_stored_date = $stored_dates_count > 0 ? $stored_dates[$stored_dates_count - 1] : null;

            $window_covered_dates_count = 0;
            $window_missing_dates_count = 0;
            $window_has_gaps = false;

            if (!empty($window_dates)) {
                foreach ($window_dates as $date_str) {
                    if (array_key_exists($date_str, $room_inventory)) {
                        $window_covered_dates_count++;
                    } else {
                        $window_missing_dates_count++;
                        $window_has_gaps = true;
                    }
                }
            }

            $coverage[$room_type_post->post_name] = [
                'room_type_id' => $room_type_id,
                'roomcloud_id' => $roomcloud_id,
                'mapped' => $roomcloud_id !== null,
                'first_stored_date' => $first_stored_date,
                'last_stored_date' => $last_stored_date,
                'stored_dates_count' => $stored_dates_count,
                'window_start_date' => !empty($window_dates) ? $window_dates[0] : null,
                'window_end_date' => !empty($window_dates) ? $window_dates[count($window_dates) - 1] : null,
                'window_dates_count' => count($window_dates),
                'window_covered_dates_count' => $window_covered_dates_count,
                'window_missing_dates_count' => $window_missing_dates_count,
                'window_has_gaps' => $window_has_gaps,
            ];
        }

        return $coverage;
    }

    public function enable_not_stay_in_rules(bool $has_rules): bool
    {
        return self::is_strict_mode() ? true : $has_rules;
    }

    public function filter_search_rooms_atts_for_roomcloud(array $atts, array $defaults): array
    {
        if (!self::is_strict_mode()) {
            return $atts;
        }

        if (!isset($atts['availability']) || $atts['availability'] !== 'free') {
            return $atts;
        }

        $room_type_id = isset($atts['room_type_id']) ? absint($atts['room_type_id']) : 0;

        if ($room_type_id <= 0) {
            return $atts;
        }

        if (!isset($atts['from_date']) || !($atts['from_date'] instanceof DateTime)) {
            return $atts;
        }

        if (!isset($atts['to_date']) || !($atts['to_date'] instanceof DateTime)) {
            return $atts;
        }

        $decision = self::evaluate_room_type_availability(
            $room_type_id,
            $atts['from_date'],
            $atts['to_date'],
            1
        );

        if (!$decision['is_sellable']) {
            $atts['count'] = 0;
            self::log_blocked_decision($decision, [
                'gate' => 'search_rooms',
            ]);
            return $atts;
        }

        $requested_count = isset($atts['count']) ? intval($atts['count']) : 0;
        $available_units = max(0, (int) $decision['available_units']);

        if ($requested_count > 0) {
            $atts['count'] = min($requested_count, $available_units);
        } else {
            $atts['count'] = $available_units;
        }

        return $atts;
    }

    public function filter_booking_rules_for_roomcloud(array $result, int $room_type_original_id, DateTime $requested_date): array
    {
        if (!self::is_strict_mode() || $room_type_original_id <= 0) {
            return $result;
        }

        $roomcloud_id = self::get_roomcloud_id_for_room_type($room_type_original_id);

        if ($roomcloud_id === null) {
            $result['not_stay_in'] = true;

            self::log_blocked_decision([
                'room_type_id' => $room_type_original_id,
                'roomcloud_id' => null,
                'reason' => 'unmapped_room_type',
                'requested_units' => 1,
            ], [
                'gate' => 'booking_rules',
                'date' => $requested_date->format('Y-m-d'),
            ]);

            return $result;
        }

        $date_str = $requested_date->format('Y-m-d');
        $available_units = self::get_availability($roomcloud_id, $date_str);

        if ($available_units === null || $available_units <= 0) {
            $result['not_stay_in'] = true;

            self::log_blocked_decision([
                'room_type_id' => $room_type_original_id,
                'roomcloud_id' => $roomcloud_id,
                'reason' => $available_units === null ? 'missing_roomcloud_data' : 'insufficient_roomcloud_units',
                'requested_units' => 1,
                'available_units' => $available_units,
            ], [
                'gate' => 'booking_rules',
                'date' => $date_str,
            ]);
        }

        return $result;
    }

    /**
     * @param array<int, int> $available_rooms_count
     * @param array<string, mixed> $context
     * @return array<int, int>
     */
    public function filter_search_results_available_rooms_count(array $available_rooms_count, array $context = []): array
    {
        if (!self::is_strict_mode() || empty($available_rooms_count)) {
            return $available_rooms_count;
        }

        $from = self::normalize_date($context['check_in_date'] ?? null);
        $to = self::normalize_date($context['check_out_date'] ?? null);

        if (!$from || !$to || $from >= $to) {
            return $available_rooms_count;
        }

        // Resolve all RoomCloud IDs up front so the mapping option is read once.
        $rc_id_by_room_type = [];
        foreach (array_keys($available_rooms_count) as $room_type_id) {
            $rc_id_by_room_type[(int) $room_type_id] = self::get_roomcloud_id_for_room_type((int) $room_type_id);
        }

        // Pre-warm the stay cache for every unique RoomCloud ID.
        // Each unique ID is inspected exactly once; subsequent evaluate_room_type_availability
        // calls below (and any later template calls) return immediately from $stay_cache.
        $seen_rc_ids = [];
        foreach ($rc_id_by_room_type as $rc_id) {
            if ($rc_id !== null && !isset($seen_rc_ids[$rc_id])) {
                $seen_rc_ids[$rc_id] = true;
                self::inspect_roomcloud_stay($rc_id, $from, $to);
            }
        }

        $filtered = [];

        foreach ($available_rooms_count as $room_type_id => $count) {
            // inspect_roomcloud_stay inside evaluate_room_type_availability is now a cache hit.
            $decision = self::evaluate_room_type_availability((int) $room_type_id, $from, $to, 1);

            if (!$decision['is_sellable']) {
                self::log_blocked_decision($decision, [
                    'gate' => 'search_results_counts',
                ]);
                continue;
            }

            $capped = min((int) $count, max(0, (int) $decision['available_units']));

            if ($capped > 0) {
                $filtered[(int) $room_type_id] = $capped;
            }
        }

        return $filtered;
    }

    public function validate_selected_rooms_for_strict_mode(array $errors, array $selected_rooms): array
    {
        if (!self::is_strict_mode() || empty($selected_rooms)) {
            return $errors;
        }

        $check_in = isset($_REQUEST['mphb_check_in_date']) ? sanitize_text_field(wp_unslash($_REQUEST['mphb_check_in_date'])) : '';
        $check_out = isset($_REQUEST['mphb_check_out_date']) ? sanitize_text_field(wp_unslash($_REQUEST['mphb_check_out_date'])) : '';

        $from = self::normalize_date($check_in);
        $to = self::normalize_date($check_out);

        if (!$from || !$to || $from >= $to) {
            return $errors;
        }

        foreach ($selected_rooms as $room_type_id => $rooms_count) {
            $room_type_id = filter_var($room_type_id, FILTER_VALIDATE_INT);
            $rooms_count = filter_var($rooms_count, FILTER_VALIDATE_INT);

            if (!$room_type_id || !$rooms_count) {
                continue;
            }

            $decision = self::evaluate_room_type_availability((int) $room_type_id, $from, $to, (int) $rooms_count);

            if ($decision['is_sellable']) {
                continue;
            }

            $room_type = MPHB()->getRoomTypeRepository()->findById((int) $room_type_id);
            $room_title = $room_type ? $room_type->getTitle() : __('Accommodation', 'motopress-hotel-booking');

            switch ($decision['reason']) {
                case 'unmapped_room_type':
                    $errors[] = sprintf(__('Accommodation %s is not configured for RoomCloud availability.', 'motopress-hotel-booking'), $room_title);
                    break;
                case 'missing_roomcloud_data':
                    $errors[] = sprintf(__('Accommodation %s is temporarily unavailable because RoomCloud inventory is incomplete for the selected stay.', 'motopress-hotel-booking'), $room_title);
                    break;
                default:
                    $errors[] = sprintf(__('Accommodation %s does not have enough RoomCloud availability for the selected stay.', 'motopress-hotel-booking'), $room_title);
                    break;
            }

            self::log_blocked_decision($decision, [
                'gate' => 'checkout_pre_validate',
            ]);
        }

        return $errors;
    }

    private static function get_room_base_price(int $room_type_id): float
    {
        $prices = get_post_meta($room_type_id, 'mphb_season_prices', true);

        if (is_array($prices) && !empty($prices)) {
            $first = reset($prices);
            return isset($first['price']) ? (float) $first['price'] : 0.0;
        }

        $base = get_post_meta($room_type_id, 'mphb_base_price', true);

        return $base ? (float) $base : 0.0;
    }

    public static function get_motopress_available_room_count(int $room_type_id, DateTime $from_date, DateTime $to_date): int
    {
        if (!function_exists('MPHB')) {
            return 0;
        }

        $available = MPHB()->getRoomRepository()->getAvailableRooms($from_date, $to_date, $room_type_id);
        $available_count = isset($available[$room_type_id]) ? count($available[$room_type_id]) : 0;

        $unavailable_ids = mphb_availability_facade()->getUnavailableRoomIds(
            $room_type_id,
            $from_date,
            $to_date,
            MPHB()->settings()->main()->isBookingRulesForAdminDisabled()
        );

        return max(0, $available_count - count($unavailable_ids));
    }

    private static function touch_inventory_meta(string $roomcloud_id, string $date): void
    {
        $meta = self::get_inventory_meta();
        $meta['last_update'] = current_time('mysql');
        $meta['last_roomcloud_id'] = $roomcloud_id;
        $meta['last_inventory_date'] = $date;

        update_option(self::INVENTORY_META_OPTION, $meta);
    }

    /**
     * @param DateTime|string $date
     */
    private static function normalize_date($date): ?DateTime
    {
        if ($date instanceof DateTime) {
            return clone $date;
        }

        if (!is_string($date) || trim($date) === '') {
            return null;
        }

        try {
            return new DateTime(trim($date), wp_timezone());
        } catch (Exception $exception) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $decision
     * @param array<string, mixed> $context
     */
    private static function log_blocked_decision(array $decision, array $context = []): void
    {
        $reason = isset($decision['reason']) ? (string) $decision['reason'] : '';

        if (!in_array($reason, ['unmapped_room_type', 'missing_roomcloud_data', 'insufficient_roomcloud_units'], true)) {
            return;
        }

        $key = md5(wp_json_encode([
            $decision['room_type_id'] ?? 0,
            $decision['roomcloud_id'] ?? '',
            $reason,
            $decision['requested_units'] ?? 1,
            $context,
        ]));

        if (isset(self::$logged_block_decisions[$key])) {
            return;
        }

        self::$logged_block_decisions[$key] = true;

        Shaped_RC_Error_Logger::log_info('RoomCloud strict availability blocked room', array_merge([
            'reason' => $reason,
            'room_type_id' => $decision['room_type_id'] ?? 0,
            'roomcloud_id' => $decision['roomcloud_id'] ?? '',
            'available_units' => $decision['available_units'] ?? null,
            'requested_units' => $decision['requested_units'] ?? 1,
            'missing_dates' => $decision['missing_dates'] ?? [],
        ], $context));
    }
}
