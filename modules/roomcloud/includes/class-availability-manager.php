<?php
/**
 * Availability Manager
 * Stores and retrieves RoomCloud inventory state (source of truth)
 */

if (!defined('ABSPATH')) {
    exit;
}

class Shaped_RC_Availability_Manager
{
    private static $instance = null;
    
    // Option key for storing inventory
    const INVENTORY_OPTION = 'shaped_rc_inventory';
    
    // Room mapping
    private static $room_mapping = [
        'deluxe-studio-apartment' => '42683',
        'studio-apartment' => '42685',
        'superior-studio-apartment' => '42686',
        'deluxe-double-room' => '42684',
    ];
    
    public static function init()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct()
    {
        // Hook into WP_Query to exclude unavailable rooms
        add_action('pre_get_posts', [$this, 'filter_room_type_query'], 999, 1);

        // Ajax endpoint for JavaScript to get availability
        add_action('wp_ajax_shaped_rc_get_availability', [$this, 'ajax_get_availability']);
        add_action('wp_ajax_nopriv_shaped_rc_get_availability', [$this, 'ajax_get_availability']);
        add_action('wp_ajax_shaped_rc_get_calendar_inventory', [$this, 'ajax_get_calendar_inventory']);
        add_action('wp_ajax_nopriv_shaped_rc_get_calendar_inventory', [$this, 'ajax_get_calendar_inventory']);

        // Block individual room page bookings when unavailable
        add_filter('mphb_room_available', [$this, 'filter_individual_room_availability'], 10, 3);

        // TASK 1: Stay-level availability gating - cap room count based on RoomCloud inventory
        add_filter('mphb_search_rooms_atts', [$this, 'filter_search_rooms_atts_for_roomcloud'], 10, 2);

        // TASK 2: Per-day calendar disabling - block days with zero availability
        add_filter('mphb_get_booking_rules_for_date', [$this, 'filter_booking_rules_for_roomcloud'], 10, 3);

        // Enable the not_stay_in rules flag when RoomCloud is active
        add_filter('mphb_has_not_stay_in_rules', [$this, 'enable_not_stay_in_rules']);
    }
    
    /**
     * Get full inventory state
     * 
     * @return array Structure: [roomcloud_id => [date => quantity]]
     */
    public static function get_inventory()
    {
        $inventory = get_option(self::INVENTORY_OPTION, []);
        
        // Ensure it's an array
        if (!is_array($inventory)) {
            $inventory = [];
            update_option(self::INVENTORY_OPTION, $inventory);
        }
        
        return $inventory;
    }
    
    /**
     * Update inventory for specific room and date range
     * 
     * @param string $roomcloud_id RoomCloud room ID (e.g., '42683')
     * @param string $date Date in Y-m-d format
     * @param int $quantity Available units
     */
    public static function update_inventory($roomcloud_id, $date, $quantity)
    {
        $inventory = self::get_inventory();
        
        // Initialize room array if needed
        if (!isset($inventory[$roomcloud_id])) {
            $inventory[$roomcloud_id] = [];
        }
        
        // Update the specific date
        $inventory[$roomcloud_id][$date] = intval($quantity);
        
        // Save
        update_option(self::INVENTORY_OPTION, $inventory);
        
        Shaped_RC_Error_Logger::log_info('Inventory updated from RoomCloud', [
            'roomcloud_id' => $roomcloud_id,
            'date' => $date,
            'quantity' => $quantity,
        ]);
    }
    
    /**
     * Get availability for specific room and date
     * 
     * @param string $roomcloud_id RoomCloud room ID
     * @param string $date Date in Y-m-d format
     * @return int|null Available quantity, or null if not set
     */
    public static function get_availability($roomcloud_id, $date)
    {
        $inventory = self::get_inventory();
        
        if (!isset($inventory[$roomcloud_id][$date])) {
            return null;
        }
        
        return intval($inventory[$roomcloud_id][$date]);
    }
    
    /**
     * Get availability for MotoPress room type by slug
     * 
     * @param string $room_slug MotoPress room slug
     * @param string $date Date in Y-m-d format
     * @return int|null Available quantity, or null if not set
     */
    public static function get_availability_by_slug($room_slug, $date)
    {
        // Get RoomCloud ID from slug
        $roomcloud_id = isset(self::$room_mapping[$room_slug]) 
            ? self::$room_mapping[$room_slug] 
            : null;
        
        if (!$roomcloud_id) {
            return null;
        }
        
        return self::get_availability($roomcloud_id, $date);
    }
    
    /**
     * Check if room is available for date range
     * 
     * @param string $roomcloud_id RoomCloud room ID
     * @param string $check_in Check-in date (Y-m-d)
     * @param string $check_out Check-out date (Y-m-d)
     * @param int $quantity Number of units needed
     * @return bool True if available
     */
    public static function is_available($roomcloud_id, $check_in, $check_out, $quantity = 1)
    {
        $start = new DateTime($check_in);
        $end = new DateTime($check_out);
        
        $current = clone $start;
        
        // Check each night
        while ($current < $end) {
            $date_str = $current->format('Y-m-d');
            $available = self::get_availability($roomcloud_id, $date_str);
            
            // If no inventory data, assume unavailable (safer)
            if ($available === null || $available < $quantity) {
                return false;
            }
            
            $current->modify('+1 day');
        }
        
        return true;
    }
    
    /**
     * Get available room types for date range
     * Returns array of [slug => available_count]
     * 
     * @param string $check_in Check-in date (Y-m-d)
     * @param string $check_out Check-out date (Y-m-d)
     * @return array [room_slug => min_available_units]
     */
    public static function get_available_rooms($check_in, $check_out)
    {
        $available = [];
        
        foreach (self::$room_mapping as $slug => $roomcloud_id) {
            $min_units = PHP_INT_MAX;
            $has_data = false; // Track if we found ANY RoomCloud data
            
            $start = new DateTime($check_in);
            $end = new DateTime($check_out);
            $current = clone $start;
            
            // Find minimum availability across date range
            while ($current < $end) {
                $date_str = $current->format('Y-m-d');
                $units = self::get_availability($roomcloud_id, $date_str);
                
                if ($units === null) {
                    // No data for this date - skip it, continue checking other dates
                    $current->modify('+1 day');
                    continue;
                }
                
                // We have data!
                $has_data = true;
                $min_units = min($min_units, $units);
                $current->modify('+1 day');
            }
            
            // Return null if no RoomCloud data exists (let MotoPress handle it)
            // Otherwise return the minimum availability found
            $available[$slug] = $has_data ? $min_units : null;
        }
        
        return $available;
    }
    
/**
 * Ajax endpoint to get RoomCloud availability for JavaScript
 * Returns both minimum availability (for urgency) and per-date data (for calendar blocking)
 */
public function ajax_get_availability()
{
    $check_in = isset($_POST['check_in']) ? sanitize_text_field($_POST['check_in']) : '';
    $check_out = isset($_POST['check_out']) ? sanitize_text_field($_POST['check_out']) : '';
    
    if (empty($check_in) || empty($check_out)) {
        wp_send_json_error(['message' => 'Missing dates']);
        return;
    }
    
    // Get full inventory
    $inventory = self::get_inventory();
    $result = [];
    
    foreach (self::$room_mapping as $slug => $roomcloud_id) {
        if (!isset($inventory[$roomcloud_id])) {
            $result[$slug] = null; // No data
            continue;
        }
        
        $roomInventory = $inventory[$roomcloud_id];
        $minAvailability = PHP_INT_MAX;
        $hasData = false;
        
        // Calculate minimum across date range
        $start = new DateTime($check_in);
        $end = new DateTime($check_out);
        $current = clone $start;
        
        while ($current < $end) {
            $dateStr = $current->format('Y-m-d');
            
            if (isset($roomInventory[$dateStr])) {
                $hasData = true;
                $minAvailability = min($minAvailability, intval($roomInventory[$dateStr]));
            }
            
            $current->modify('+1 day');
        }
        
        // Return minimum availability (for urgency badges)
        // If no data found, return null (MotoPress will handle it)
        $result[$slug] = $hasData ? $minAvailability : null;
    }
    
    wp_send_json_success($result);
}

/**
 * NEW: Get per-date inventory for calendar blocking
 */
public function ajax_get_calendar_inventory()
{
    $check_in = isset($_POST['check_in']) ? sanitize_text_field($_POST['check_in']) : '';
    $check_out = isset($_POST['check_out']) ? sanitize_text_field($_POST['check_out']) : '';
    $room_slug = isset($_POST['room_slug']) ? sanitize_text_field($_POST['room_slug']) : '';
    
    if (empty($check_in) || empty($check_out) || empty($room_slug)) {
        wp_send_json_error(['message' => 'Missing parameters']);
        return;
    }
    
    // Get RoomCloud ID for this slug
    $roomcloud_id = isset(self::$room_mapping[$room_slug]) ? self::$room_mapping[$room_slug] : null;
    
    if (!$roomcloud_id) {
        wp_send_json_error(['message' => 'Room not found']);
        return;
    }
    
    // Get full inventory
    $inventory = self::get_inventory();
    
    if (!isset($inventory[$roomcloud_id])) {
        wp_send_json_success([]); // No data, return empty
        return;
    }
    
    $roomInventory = $inventory[$roomcloud_id];
    $dateAvailability = [];
    
    // Build per-date availability
    $start = new DateTime($check_in);
    $end = new DateTime($check_out);
    $current = clone $start;
    
    while ($current < $end) {
        $dateStr = $current->format('Y-m-d');
        
        if (isset($roomInventory[$dateStr])) {
            $dateAvailability[$dateStr] = intval($roomInventory[$dateStr]);
        }
        
        $current->modify('+1 day');
    }
    
    wp_send_json_success($dateAvailability);
}
    
    /**
     * Filter individual room availability check
     * Prevents booking unavailable rooms on single room pages ([mphb_availability] shortcode)
     */
    public function filter_individual_room_availability($is_available, $room_id, $search_params)
    {
      error_log('ROOMCLOUD FILTER CALLED: room_id=' . $room_id . ', is_available=' . ($is_available ? 'true' : 'false'));
    error_log('ROOMCLOUD SEARCH PARAMS: ' . print_r($search_params, true));
        if (!$is_available) {
            return false; // Already unavailable per MotoPress
        }
        
        // Get room slug
        $room_post = get_post($room_id);
        if (!$room_post) {
            return $is_available;
        }
        
        // Get room type from room instance
        $room_type_id = get_post_meta($room_id, 'mphb_room_type_id', true);
        if (!$room_type_id) {
            return $is_available;
        }
        
        $room_type_post = get_post($room_type_id);
        if (!$room_type_post) {
            return $is_available;
        }
        
        $room_slug = $room_type_post->post_name;
        
        // Get RoomCloud ID
        $roomcloud_id = isset(self::$room_mapping[$room_slug]) 
            ? self::$room_mapping[$room_slug] 
            : null;
        
        if (!$roomcloud_id) {
            return $is_available; // No mapping - allow MotoPress
        }
        
        // Extract dates from search params
        $check_in = isset($search_params['from_date']) ? $search_params['from_date']->format('Y-m-d') : null;
        $check_out = isset($search_params['to_date']) ? $search_params['to_date']->format('Y-m-d') : null;
        
        if (!$check_in || !$check_out) {
            return $is_available;
        }
        
        // Check RoomCloud availability
        $roomcloud_available = self::is_available($roomcloud_id, $check_in, $check_out);
        
        // Log if we're blocking
        if (!$roomcloud_available) {
            Shaped_RC_Error_Logger::log_info('Blocked booking on individual room page', [
                'room_slug' => $room_slug,
                'roomcloud_id' => $roomcloud_id,
                'check_in' => $check_in,
                'check_out' => $check_out,
                'motopress_says' => 'available',
                'roomcloud_says' => 'unavailable'
            ]);
        }
        
        return $roomcloud_available;
    }
        
    /**
     * Find upgrade options when primary room unavailable
     * Returns rooms with higher price that ARE available
     * 
     * @param string $original_slug Original room slug that's unavailable
     * @param string $check_in Check-in date (Y-m-d)
     * @param string $check_out Check-out date (Y-m-d)
     * @return array [slug => room_type_post_id], ordered by price ascending
     */
    public static function find_upgrade_options($original_slug, $check_in, $check_out)
    {
        // Get original room price
        $original_post = get_page_by_path($original_slug, OBJECT, 'mphb_room_type');
        if (!$original_post) {
            return [];
        }
        
        $original_price = self::get_room_base_price($original_post->ID);
        
        // Get available rooms
        $available = self::get_available_rooms($check_in, $check_out);
        
        $upgrades = [];
        
        foreach ($available as $slug => $units) {
            if ($units < 1) {
                continue; // Skip unavailable
            }
            
            if ($slug === $original_slug) {
                continue; // Skip original
            }
            
            $post = get_page_by_path($slug, OBJECT, 'mphb_room_type');
            if (!$post) {
                continue;
            }
            
            $price = self::get_room_base_price($post->ID);
            
            // Only include if more expensive
            if ($price > $original_price) {
                $upgrades[$slug] = [
                    'room_type_id' => $post->ID,
                    'price' => $price,
                    'price_diff' => $price - $original_price,
                ];
            }
        }
        
        // Sort by price ascending (cheapest upgrade first)
        uasort($upgrades, function($a, $b) {
            return $a['price'] <=> $b['price'];
        });
        
        return $upgrades;
    }
    
    /**
     * Get base price for room type
     * 
     * @param int $room_type_id MotoPress room type ID
     * @return float Base price per night
     */
    private static function get_room_base_price($room_type_id)
    {
        // Get base price from MotoPress
        $prices = get_post_meta($room_type_id, 'mphb_season_prices', true);
        
        if (is_array($prices) && !empty($prices)) {
            // Get first season price as base
            $first = reset($prices);
            return isset($first['price']) ? floatval($first['price']) : 0;
        }
        
        // Fallback: check for simple price
        $base = get_post_meta($room_type_id, 'mphb_base_price', true);
        return $base ? floatval($base) : 0;
    }
    
    /**
     * Override MotoPress availability with RoomCloud data
     * Hook: mphb_room_available
     */
    public function override_motopress_availability($is_available, $room_type_id, $check_in, $check_out)
    {
        // Get room slug from ID
        $room_post = get_post($room_type_id);
        if (!$room_post) {
            return $is_available;
        }
        
        $room_slug = $room_post->post_name;
        
        // Get RoomCloud ID
        $roomcloud_id = isset(self::$room_mapping[$room_slug]) 
            ? self::$room_mapping[$room_slug] 
            : null;
        
        if (!$roomcloud_id) {
            // No mapping - fall back to MotoPress calculation
            return $is_available;
        }
        
        // Check RoomCloud inventory
        $check_in_str = $check_in->format('Y-m-d');
        $check_out_str = $check_out->format('Y-m-d');
        
        return self::is_available($roomcloud_id, $check_in_str, $check_out_str);
    }

    /**
     * Filter WP_Query to exclude unavailable room types from search
     */
    public function filter_room_type_query($query)
    {
        // Only filter room type queries on frontend
        if (is_admin() || $query->get('post_type') !== 'mphb_room_type') {
            return;
        }
        
        // Get search dates from query
        $check_in = $query->get('mphb_check_in_date');
        $check_out = $query->get('mphb_check_out_date');
        
        if (empty($check_in) || empty($check_out)) {
            return;
        }
        
        // Get all room types
        $room_types = get_posts([
            'post_type' => 'mphb_room_type',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'post_status' => 'publish',
        ]);
        
        $unavailable_ids = [];
        
        foreach ($room_types as $room_type_id) {
            $room_slug = get_post_field('post_name', $room_type_id);
            $roomcloud_id = isset(self::$room_mapping[$room_slug]) ? self::$room_mapping[$room_slug] : null;
            
            if (!$roomcloud_id) {
                continue; // No mapping - let MotoPress handle it
            }
            
            // Check RoomCloud inventory
            if (!self::is_available($roomcloud_id, $check_in, $check_out)) {
                $unavailable_ids[] = $room_type_id;
            }
        }
        
        // Exclude unavailable rooms from query
        if (!empty($unavailable_ids)) {
            $query->set('post__not_in', $unavailable_ids);
            
            Shaped_RC_Error_Logger::log_info('Filtered unavailable rooms from search', [
                'check_in' => $check_in,
                'check_out' => $check_out,
                'excluded_ids' => $unavailable_ids,
            ]);
        }
    }
    
    /**
     * Clear inventory for specific room
     * 
     * @param string $roomcloud_id RoomCloud room ID
     */
    public static function clear_room_inventory($roomcloud_id)
    {
        $inventory = self::get_inventory();
        
        if (isset($inventory[$roomcloud_id])) {
            unset($inventory[$roomcloud_id]);
            update_option(self::INVENTORY_OPTION, $inventory);
        }
    }
    
    /**
     * Clear all inventory
     */
    public static function clear_all_inventory()
    {
        delete_option(self::INVENTORY_OPTION);
        
        Shaped_RC_Error_Logger::log_info('All inventory cleared');
    }
    
    /**
     * Get inventory summary for admin display
     * 
     * @param int $days Number of days to show
     * @return array Summary data
     */
    public static function get_inventory_summary($days = 30)
    {
        $inventory = self::get_inventory();
        $summary = [];
        
        $start = new DateTime();
        
        foreach (self::$room_mapping as $slug => $roomcloud_id) {
            $room_data = [
                'slug' => $slug,
                'roomcloud_id' => $roomcloud_id,
                'dates' => [],
            ];
            
            for ($i = 0; $i < $days; $i++) {
                $date = clone $start;
                $date->modify("+{$i} days");
                $date_str = $date->format('Y-m-d');
                
                $quantity = isset($inventory[$roomcloud_id][$date_str]) 
                    ? $inventory[$roomcloud_id][$date_str] 
                    : null;
                
                $room_data['dates'][$date_str] = $quantity;
            }
            
            $summary[$slug] = $room_data;
        }
        
        return $summary;
    }

    // =========================================================================
    // ROOMCLOUD AVAILABILITY GATING FILTERS
    // =========================================================================

    /**
     * Get room mapping from option or fall back to static mapping
     *
     * @return array [room_slug => roomcloud_id]
     */
    public static function get_room_mapping(): array
    {
        $option_mapping = get_option('shaped_rc_room_mapping', []);

        // If option is set and not empty, use it
        if (!empty($option_mapping) && is_array($option_mapping)) {
            return $option_mapping;
        }

        // Fall back to static mapping
        return self::$room_mapping;
    }

    /**
     * Get RoomCloud ID for an MPHB room type ID
     *
     * @param int $room_type_id MPHB room type post ID
     * @return string|null RoomCloud room ID or null if not mapped
     */
    public static function get_roomcloud_id_for_room_type(int $room_type_id): ?string
    {
        if ($room_type_id <= 0) {
            return null;
        }

        // Get the room slug from the post
        $room_slug = get_post_field('post_name', $room_type_id);

        if (empty($room_slug)) {
            return null;
        }

        // Get the mapping
        $mapping = self::get_room_mapping();

        return isset($mapping[$room_slug]) ? $mapping[$room_slug] : null;
    }

    /**
     * Get minimum availability across a date range for a RoomCloud room
     *
     * @param string $roomcloud_id RoomCloud room ID
     * @param DateTime $from_date Check-in date (inclusive)
     * @param DateTime $to_date Check-out date (exclusive)
     * @return int|null Minimum available units across the stay, or null if no data
     */
    public static function get_min_availability_for_stay(string $roomcloud_id, DateTime $from_date, DateTime $to_date): ?int
    {
        $inventory = self::get_inventory();

        if (!isset($inventory[$roomcloud_id])) {
            return null;
        }

        $room_inventory = $inventory[$roomcloud_id];
        $min_units = PHP_INT_MAX;
        $has_data = false;

        $current = clone $from_date;

        // Check each night from check-in (inclusive) to check-out (exclusive)
        while ($current < $to_date) {
            $date_str = $current->format('Y-m-d');

            if (isset($room_inventory[$date_str])) {
                $has_data = true;
                $min_units = min($min_units, intval($room_inventory[$date_str]));

                // Early exit: if we find 0 availability, no point checking further
                if ($min_units === 0) {
                    break;
                }
            }

            $current->modify('+1 day');
        }

        // If no data found for any date, return null to let MPHB handle it
        // (This allows bookings for dates beyond the RoomCloud inventory horizon)
        if (!$has_data) {
            self::rc_debug_log('No RoomCloud data found for stay - letting MPHB handle', [
                'roomcloud_id' => $roomcloud_id,
                'from_date' => $from_date->format('Y-m-d'),
                'to_date' => $to_date->format('Y-m-d'),
            ]);
            return null;
        }

        return $min_units;
    }

    /**
     * Get availability for a specific room type and date
     *
     * @param int $room_type_id MPHB room type post ID
     * @param DateTime $date The date to check
     * @return int|null Available units for that date, or null if no mapping
     */
    public static function get_availability_for_room_type_date(int $room_type_id, DateTime $date): ?int
    {
        $roomcloud_id = self::get_roomcloud_id_for_room_type($room_type_id);

        if ($roomcloud_id === null) {
            return null;
        }

        $date_str = $date->format('Y-m-d');
        return self::get_availability($roomcloud_id, $date_str);
    }

    /**
     * Debug logging helper - only logs when WP_DEBUG is enabled
     *
     * @param string $message Log message
     * @param array $context Additional context data
     */
    private static function rc_debug_log(string $message, array $context = []): void
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $log_entry = '[Shaped RC Gating] ' . $message;

        if (!empty($context)) {
            $log_entry .= ' | ' . wp_json_encode($context);
        }

        error_log($log_entry);
    }

    /**
     * Enable not_stay_in rules when RoomCloud is active
     *
     * @param bool $has_rules Current value
     * @return bool
     */
    public function enable_not_stay_in_rules(bool $has_rules): bool
    {
        if (shaped_is_roomcloud_active()) {
            return true;
        }

        return $has_rules;
    }

    /**
     * TASK 1: Filter search rooms attributes to cap count based on RoomCloud availability
     *
     * This ensures MPHB never returns more free room IDs than RoomCloud allows for the stay.
     *
     * @param array $atts Search attributes
     * @param array $defaults Default attributes
     * @return array Modified attributes
     */
    public function filter_search_rooms_atts_for_roomcloud(array $atts, array $defaults): array
    {
        // Only apply when RoomCloud mode is enabled
        if (!shaped_is_roomcloud_active()) {
            return $atts;
        }

        // Only apply when searching for free rooms
        if (!isset($atts['availability']) || $atts['availability'] !== 'free') {
            return $atts;
        }

        // Only apply when room_type_id is a single positive integer
        if (!isset($atts['room_type_id']) || !is_int($atts['room_type_id']) || $atts['room_type_id'] <= 0) {
            // Could be array or 0 (all types) - skip
            return $atts;
        }

        // Validate from_date and to_date are DateTime objects
        if (!isset($atts['from_date']) || !($atts['from_date'] instanceof DateTime)) {
            return $atts;
        }

        if (!isset($atts['to_date']) || !($atts['to_date'] instanceof DateTime)) {
            return $atts;
        }

        $room_type_id = $atts['room_type_id'];
        $from_date = $atts['from_date'];
        $to_date = $atts['to_date'];

        // Get RoomCloud ID for this room type
        $roomcloud_id = self::get_roomcloud_id_for_room_type($room_type_id);

        if ($roomcloud_id === null) {
            // No mapping - let MPHB handle it
            self::rc_debug_log('No RoomCloud mapping for room type', [
                'room_type_id' => $room_type_id,
            ]);
            return $atts;
        }

        // Compute minimum free units across the stay
        $rc_free_stay = self::get_min_availability_for_stay($roomcloud_id, $from_date, $to_date);

        if ($rc_free_stay === null) {
            // No data for this date range - let MPHB handle it
            // (Allows bookings for dates beyond RoomCloud inventory horizon)
            self::rc_debug_log('No availability data - letting MPHB handle', [
                'room_type_id' => $room_type_id,
                'roomcloud_id' => $roomcloud_id,
            ]);
            return $atts;
        }

        $requested_count = isset($atts['count']) ? intval($atts['count']) : 0;

        // Cap the count based on RoomCloud availability
        if ($requested_count > 0) {
            // Requested a specific count - cap it
            $atts['count'] = min($requested_count, $rc_free_stay);
        } else {
            // Requested all (0) - limit to RoomCloud availability
            $atts['count'] = $rc_free_stay;
        }

        self::rc_debug_log('Capped search room count', [
            'room_type_id' => $room_type_id,
            'roomcloud_id' => $roomcloud_id,
            'from_date' => $from_date->format('Y-m-d'),
            'to_date' => $to_date->format('Y-m-d'),
            'rc_free_stay' => $rc_free_stay,
            'requested_count' => $requested_count,
            'capped_count' => $atts['count'],
        ]);

        return $atts;
    }

    /**
     * TASK 2: Filter booking rules to block days with zero RoomCloud availability
     *
     * This makes 0-availability days unselectable in MPHB's calendar/datepicker logic.
     *
     * @param array $result Booking rules result
     * @param int $room_type_original_id Room type original ID (for WPML compatibility)
     * @param DateTime $requested_date The date being checked
     * @return array Modified result
     */
    public function filter_booking_rules_for_roomcloud(array $result, int $room_type_original_id, DateTime $requested_date): array
    {
        // Only apply when RoomCloud mode is enabled
        if (!shaped_is_roomcloud_active()) {
            return $result;
        }

        // Only apply when we have a valid room type ID
        if ($room_type_original_id <= 0) {
            return $result;
        }

        // Get RoomCloud ID for this room type
        $roomcloud_id = self::get_roomcloud_id_for_room_type($room_type_original_id);

        if ($roomcloud_id === null) {
            // No mapping - let MPHB handle it
            return $result;
        }

        // Get availability for this specific date
        $date_str = $requested_date->format('Y-m-d');
        $rc_free_day = self::get_availability($roomcloud_id, $date_str);

        // Only block if we have explicit data showing 0 availability
        // If no data exists (null), let MPHB handle it (allows dates beyond inventory horizon)
        if ($rc_free_day !== null && $rc_free_day <= 0) {
            $result['not_stay_in'] = true;

            self::rc_debug_log('Blocked day due to zero RoomCloud availability', [
                'room_type_id' => $room_type_original_id,
                'roomcloud_id' => $roomcloud_id,
                'date' => $date_str,
                'rc_free_day' => $rc_free_day,
            ]);
        }

        return $result;
    }
}