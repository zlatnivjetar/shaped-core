<?php
/**
 * Webhook Handler
 * Receives and processes incoming messages from RoomCloud
 * Updated: Processes modify requests and stores inventory from RoomCloud
 * Fixed: Direct XML output to avoid WordPress REST JSON encoding
 */

if (!defined('ABSPATH')) {
    exit;
}

class Shaped_RC_Webhook_Handler
{
    private static $instance = null;
    
    // Reverse mapping: RoomCloud ID => MotoPress slug
    private static $room_mapping_reverse = [
        '42683' => 'deluxe-studio-apartment',
        '42685' => 'studio-apartment',
        '42686' => 'superior-studio-apartment',
        '42684' => 'deluxe-double-room',
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
        // Register REST endpoint
        add_action('rest_api_init', [$this, 'register_webhook_endpoint']);
    }
    
    /**
     * Register webhook endpoint
     */
    public function register_webhook_endpoint()
    {
        register_rest_route('preelook/v1', '/roomcloud-webhook', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_webhook'],
            'permission_callback' => '__return_true', // Validate credentials from XML body
        ]);
    }
    
    /**
     * Handle incoming webhook
     */
    public function handle_webhook(WP_REST_Request $request)
    {
        // Get raw body
        $body = $request->get_body();
        
        Shaped_RC_Error_Logger::log_info('Webhook received', [
            'body_preview' => substr($body, 0, 300) . '...',
        ]);
        
        // Parse XML
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        
        if ($xml === false) {
            $errors = libxml_get_errors();
            $error_msg = 'Invalid XML';
            if (!empty($errors)) {
                $error_msg .= ': ' . $errors[0]->message;
            }
            libxml_clear_errors();
            
            Shaped_RC_Error_Logger::log_error('Webhook XML parse error', [
                'error' => $error_msg,
            ]);
            
            return new WP_REST_Response([
                'success' => false,
                'error' => $error_msg,
            ], 400);
        }
        
        // VALIDATE CREDENTIALS FROM XML
        $username = (string) $xml['userName'];
        $password = (string) $xml['password'];
        
        $expected_username = get_option('shaped_rc_username', '9335');
        $expected_password = get_option('shaped_rc_password', '');
        
        if ($username !== $expected_username || $password !== $expected_password) {
            Shaped_RC_Error_Logger::log_warning('Webhook authentication failed', [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'username' => $username,
            ]);
            
            return new WP_REST_Response([
                'success' => false,
                'error' => 'Authentication failed',
            ], 401);
        }
        
        // Handle configuration messages - output XML directly
        if (isset($xml->getHotels)) {
            $this->handle_get_hotels();
            return; // Never reached due to exit in handler
        }
        
        if (isset($xml->getRates)) {
            $hotel_id = (string) $xml->getRates['hotelId'];
            $this->handle_get_rates($hotel_id);
            return; // Never reached due to exit in handler
        }
        
        if (isset($xml->getRooms)) {
            $hotel_id = (string) $xml->getRooms['hotelId'];
            $this->handle_get_rooms($hotel_id);
            return; // Never reached due to exit in handler
        }
        
        // Handle modify request (availability/rate updates from RoomCloud)
        if (isset($xml->modify)) {
            $this->handle_modify($xml->modify);
            return; // Never reached due to exit in handler
        }
        
        // Handle reservations list request (pull mode)
        if (isset($xml->reservations)) {
            $this->handle_get_reservations($xml->reservations);
            return; // Never reached due to exit in handler
        }
        
        // Handle reservation message (JSON response is OK here)
        if (isset($xml->reservation)) {
            $result = $this->process_reservation($xml->reservation);
            
            if ($result['success']) {
                return new WP_REST_Response([
                    'success' => true,
                    'booking_id' => $result['booking_id'],
                ], 200);
            } else {
                return new WP_REST_Response([
                    'success' => false,
                    'error' => $result['error'],
                ], 400);
            }
        }
        
        // Unknown message type
        Shaped_RC_Error_Logger::log_warning('Unknown webhook message type', [
            'xml_preview' => substr($body, 0, 200),
        ]);
        
        return new WP_REST_Response([
            'success' => false,
            'error' => 'Unknown message type',
        ], 400);
    }
    
    /**
     * Handle getHotels request
     */
    private function handle_get_hotels()
    {
        $hotel_id = get_option('shaped_rc_hotel_id', '9335');
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<Response>' . "\n";
        $xml .= '  <hotel id="' . esc_attr($hotel_id) . '" description="' . esc_attr(shaped_brand('company.name', 'Hotel')) . '" />' . "\n";
        $xml .= '</Response>';
        
        Shaped_RC_Error_Logger::log_info('Responded to getHotels', [
            'hotel_id' => $hotel_id,
        ]);
        
        // Output XML directly to avoid WordPress JSON encoding
        header('Content-Type: text/xml; charset=UTF-8');
        echo $xml;
        exit;
    }
    
    /**
     * Handle getRates request
     */
    private function handle_get_rates($hotel_id)
    {
        $rate_id = get_option('shaped_rc_rate_id', '26939');
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<Response>' . "\n";
        $xml .= '  <rate rateId="' . esc_attr($rate_id) . '" description="Standard Rate - Room only" />' . "\n";
        $xml .= '</Response>';
        
        Shaped_RC_Error_Logger::log_info('Responded to getRates', [
            'hotel_id' => $hotel_id,
            'rate_id' => $rate_id,
        ]);
        
        // Output XML directly to avoid WordPress JSON encoding
        header('Content-Type: text/xml; charset=UTF-8');
        echo $xml;
        exit;
    }
    
    /**
     * Handle getRooms request
     */
    private function handle_get_rooms($hotel_id)
    {
        $rate_id = get_option('shaped_rc_rate_id', '26939');
        $room_mapping = get_option('shaped_rc_room_mapping', [
            'deluxe-studio-apartment' => '42683',
            'studio-apartment' => '42685',
            'superior-studio-apartment' => '42686',
            'deluxe-double-room' => '42684',
        ]);
        
        // Get MotoPress room types
        $mphb_rooms = [
            'deluxe-studio-apartment' => 'Deluxe Studio Apartment',
            'studio-apartment' => 'Studio Apartment',
            'superior-studio-apartment' => 'Superior Studio Apartment',
            'deluxe-double-room' => 'Deluxe Double Room',
        ];
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<Response>' . "\n";
        
        foreach ($room_mapping as $slug => $roomcloud_id) {
            $description = isset($mphb_rooms[$slug]) ? $mphb_rooms[$slug] : ucwords(str_replace('-', ' ', $slug));
            
            // Base occupancy: 2 adults for all rooms
            // Additional beds: 0 (we handle pricing via base price)
            $xml .= '  <room id="' . esc_attr($roomcloud_id) . '" ';
            $xml .= 'baseOccupancy="2" ';
            $xml .= 'additionalBeds="0" ';
            $xml .= 'description="' . esc_attr($description) . '">' . "\n";
            $xml .= '    <rate rateId="' . esc_attr($rate_id) . '" />' . "\n";
            $xml .= '  </room>' . "\n";
        }
        
        $xml .= '</Response>';
        
        Shaped_RC_Error_Logger::log_info('Responded to getRooms', [
            'hotel_id' => $hotel_id,
            'room_count' => count($room_mapping),
        ]);
        
        // Output XML directly to avoid WordPress JSON encoding
        header('Content-Type: text/xml; charset=UTF-8');
        echo $xml;
        exit;
    }
    
    /**
     * Handle modify request (availability/rate updates from RoomCloud)
     * RoomCloud is source of truth - process and store updates
     */
    private function handle_modify($modify)
    {
        $hotel_id = (string) $modify['hotelId'];
        $start_date = (string) $modify['startDate'];
        $end_date = (string) $modify['endDate'];
        
        Shaped_RC_Error_Logger::log_info('Processing modify request', [
            'hotel_id' => $hotel_id,
            'start_date' => $start_date,
            'end_date' => $end_date,
        ]);
        
        $updates_processed = 0;
        
        // Process each availability element
        if (isset($modify->availability)) {
            foreach ($modify->availability as $avail) {
                $date = (string) $avail['day'];
                $roomcloud_id = (string) $avail['roomId'];
                $quantity = intval($avail['quantity']);
                
                // Store in availability manager
                Shaped_RC_Availability_Manager::update_inventory($roomcloud_id, $date, $quantity);
                
                $updates_processed++;
            }
        }
        
        Shaped_RC_Error_Logger::log_info('Modify request processed', [
            'updates' => $updates_processed,
        ]);
        
        // Return success
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<Response>' . "\n";
        $xml .= '  <ok/>' . "\n";
        $xml .= '</Response>';
        
        header('Content-Type: text/xml; charset=UTF-8');
        echo $xml;
        exit;
    }
    
    /**
     * Handle getReservations request (pull mode)
     * Returns list of recent reservations from website
     */
    private function handle_get_reservations($reservations)
    {
        $hotel_id = (string) $reservations['hotelId'];
        $start_date = (string) $reservations['startDate'];
        $end_date = (string) $reservations['endDate'];
        
        Shaped_RC_Error_Logger::log_info('Responded to getReservations (empty list)', [
            'hotel_id' => $hotel_id,
            'start_date' => $start_date,
            'end_date' => $end_date,
        ]);
        
        // Return empty list - we push reservations, don't support pull mode
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<Response>' . "\n";
        $xml .= '  <!-- No reservations - using push mode only -->' . "\n";
        $xml .= '</Response>';
        
        header('Content-Type: text/xml; charset=UTF-8');
        echo $xml;
        exit;
    }
    
    /**
     * Process incoming reservation
     */
    private function process_reservation($reservation)
    {
        // Extract reservation data
        $roomcloud_booking_id = (string) $reservation['id'];
        $status = (string) $reservation['status'];
        $check_in = (string) $reservation['checkin'];
        $check_out = (string) $reservation['checkout'];
        $first_name = (string) $reservation['firstName'];
        $last_name = (string) $reservation['lastName'];
        $email = (string) $reservation['email'];
        $phone = (string) $reservation['telephone'];
        $price = (float) $reservation['price'];
        
        // Get room details
        if (!isset($reservation->room)) {
            return [
                'success' => false,
                'error' => 'No room data in reservation',
            ];
        }
        
        $room = $reservation->room;
        $roomcloud_room_id = (string) $room['id'];
        
        // Map to MotoPress room type
        $room_slug = isset(self::$room_mapping_reverse[$roomcloud_room_id])
            ? self::$room_mapping_reverse[$roomcloud_room_id]
            : null;
        
        if (!$room_slug) {
            Shaped_RC_Error_Logger::log_critical('Room mapping not found for incoming booking', [
                'roomcloud_room_id' => $roomcloud_room_id,
                'roomcloud_booking_id' => $roomcloud_booking_id,
            ]);
            
            return [
                'success' => false,
                'error' => 'Room mapping not found',
            ];
        }
        
        // Get MotoPress room type
        $room_type_post = get_page_by_path($room_slug, OBJECT, 'mphb_room_type');
        if (!$room_type_post) {
            return [
                'success' => false,
                'error' => 'MotoPress room type not found',
            ];
        }
        
        $room_type_id = $room_type_post->ID;
        
        // Check if booking already exists
        $existing = $this->find_existing_booking($roomcloud_booking_id);
        
        if ($existing) {
            // Update existing booking
            return $this->update_existing_booking($existing, $status, $price);
        }
        
        // Handle status
        $status_map = [
            '2' => 'SUBMITTED',
            '4' => 'CONFIRMED',
            '7' => 'CANCELLED',
        ];
        $status_name = isset($status_map[$status]) ? $status_map[$status] : 'SUBMITTED';
        
        // Don't create booking if cancelled
        if ($status_name === 'CANCELLED') {
            Shaped_RC_Error_Logger::log_info('Skipping cancelled reservation', [
                'roomcloud_booking_id' => $roomcloud_booking_id,
            ]);
            
            return [
                'success' => true,
                'message' => 'Cancelled reservation ignored',
            ];
        }
        
        // Create MotoPress booking
        $booking_id = $this->create_motopress_booking([
            'room_type_id' => $room_type_id,
            'room_slug' => $room_slug,
            'check_in' => $check_in,
            'check_out' => $check_out,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'phone' => $phone,
            'price' => $price,
            'status' => $status_name,
            'roomcloud_booking_id' => $roomcloud_booking_id,
        ]);
        
        if (!$booking_id) {
            return [
                'success' => false,
                'error' => 'Failed to create booking',
            ];
        }
        
        Shaped_RC_Error_Logger::log_info('Booking created from RoomCloud', [
            'booking_id' => $booking_id,
            'roomcloud_booking_id' => $roomcloud_booking_id,
        ]);
        
        return [
            'success' => true,
            'booking_id' => $booking_id,
        ];
    }
    
    /**
     * Find existing booking by RoomCloud ID
     */
    private function find_existing_booking($roomcloud_booking_id)
    {
        $args = [
            'post_type' => 'mphb_booking',
            'post_status' => 'any',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => '_roomcloud_booking_id',
                    'value' => $roomcloud_booking_id,
                ],
            ],
        ];
        
        $posts = get_posts($args);
        
        return !empty($posts) ? $posts[0] : null;
    }
    
    /**
     * Update existing booking status
     */
    private function update_existing_booking($booking_id, $status, $price)
    {
        // Map RoomCloud status to MotoPress post_status
        $status_map = [
            '2' => 'pending-payment',
            '4' => 'confirmed',
            '7' => 'cancelled',
        ];
        
        // Map RoomCloud status to Shaped payment status
        $payment_status_map = [
            '2' => 'pending',
            '4' => 'completed',
            '7' => 'cancelled',
        ];
        
        $post_status = isset($status_map[$status]) ? $status_map[$status] : 'pending-payment';
        $payment_status = isset($payment_status_map[$status]) ? $payment_status_map[$status] : 'pending';
        
        // Update post status
        wp_update_post([
            'ID' => $booking_id,
            'post_status' => $post_status,
        ]);
        
        // Update Shaped payment status (critical for status matching)
        update_post_meta($booking_id, '_shaped_payment_status', $payment_status);
        
        // Update price if changed
        update_post_meta($booking_id, '_shaped_payment_amount', $price);
        update_post_meta($booking_id, 'mphb_total_price', $price);
        
        // Handle cancellation
        if ($status === '7') {
            // Release rooms
            $booking = MPHB()->getBookingRepository()->findById($booking_id);
            if ($booking) {
                $reserved_rooms = $booking->getReservedRooms();
                foreach ($reserved_rooms as $room) {
                    wp_delete_post($room->getId(), true);
                }
            }
        }
        
        Shaped_RC_Error_Logger::log_info('Updated booking from RoomCloud', [
            'booking_id' => $booking_id,
            'status' => $status,
            'payment_status' => $payment_status,
        ]);
        
        return [
            'success' => true,
            'booking_id' => $booking_id,
            'updated' => true,
        ];
    }
    
    /**
     * Create MotoPress booking (minimal fields only)
     */
    private function create_motopress_booking($data)
    {
        // OTA bookings are always confirmed (guest already paid OTA)
        // Don't create if cancelled
        if ($data['status'] === 'CANCELLED') {
            Shaped_RC_Error_Logger::log_info('Skipping cancelled reservation', [
                'roomcloud_booking_id' => $data['roomcloud_booking_id'],
            ]);
            
            return [
                'success' => true,
                'message' => 'Cancelled reservation ignored',
            ];
        }
        
        // All OTA bookings → confirmed + completed (guest already paid OTA)
        $post_status = 'confirmed';
        $payment_status = 'completed';
        
        // Create booking post with RoomCloud flags set IMMEDIATELY to prevent sync loop
        $booking_data = [
            'post_type' => 'mphb_booking',
            'post_status' => $post_status,
            'post_title' => sprintf(
                'Booking %s - %s',
                $data['first_name'] . ' ' . $data['last_name'],
                $data['check_in']
            ),
            'meta_input' => [
                // RoomCloud tracking meta (CRITICAL: Set immediately to prevent loop)
                '_roomcloud_source' => true,
                '_roomcloud_booking_id' => $data['roomcloud_booking_id'],
                '_roomcloud_received_at' => current_time('mysql'),
            ],
        ];
        
        $booking_id = wp_insert_post($booking_data);
        
        if (is_wp_error($booking_id) || !$booking_id) {
            Shaped_RC_Error_Logger::log_critical('Failed to create booking post', [
                'error' => is_wp_error($booking_id) ? $booking_id->get_error_message() : 'Unknown',
            ]);
            return false;
        }
        
        // MINIMAL booking meta (only what's needed for calendar + status)
        update_post_meta($booking_id, 'mphb_check_in_date', $data['check_in']);
        update_post_meta($booking_id, 'mphb_check_out_date', $data['check_out']);
        update_post_meta($booking_id, 'mphb_first_name', $data['first_name']);
        update_post_meta($booking_id, 'mphb_last_name', $data['last_name']);
        update_post_meta($booking_id, 'mphb_email', $data['email']);
        update_post_meta($booking_id, 'mphb_phone', $data['phone']);
        update_post_meta($booking_id, 'mphb_total_price', $data['price']);
        
        // Shaped payment meta (critical for status matching)
        update_post_meta($booking_id, '_shaped_payment_amount', $data['price']);
        update_post_meta($booking_id, '_shaped_payment_status', $payment_status);
        
        // Find first available room instance
        $room_instance_id = $this->find_available_room_instance($data['room_type_id'], $data['check_in'], $data['check_out']);
        
        if (!$room_instance_id) {
            Shaped_RC_Error_Logger::log_error('No available room instance found', [
                'room_type_id' => $data['room_type_id'],
                'room_slug' => $data['room_slug'],
            ]);
        }
        
        // Create reserved room post
        $reserved_room_data = [
            'post_type' => 'mphb_reserved_room',
            'post_status' => 'publish',
            'post_title' => 'Reserved Room for Booking #' . $booking_id,
            'post_parent' => $booking_id,
        ];
        
        $reserved_room_id = wp_insert_post($reserved_room_data);
        
        if ($reserved_room_id && !is_wp_error($reserved_room_id)) {
            // Minimal reserved room meta
            update_post_meta($reserved_room_id, 'mphb_room_type_id', $data['room_type_id']);
            update_post_meta($reserved_room_id, 'mphb_check_in_date', $data['check_in']);
            update_post_meta($reserved_room_id, 'mphb_check_out_date', $data['check_out']);
            update_post_meta($reserved_room_id, 'mphb_booking_id', $booking_id);
            
            // Assign specific room instance if found
            if ($room_instance_id) {
                update_post_meta($reserved_room_id, '_mphb_room_id', $room_instance_id);
            }
        }
        
        // Schedule flag cleanup (5 minutes)
        wp_schedule_single_event(
            time() + 300,
            'cleanup_roomcloud_flag',
            [$booking_id]
        );
        
        // Trigger MotoPress availability recalculation
        do_action('mphb_create_booking_by_user', $booking_id);
        
        return $booking_id;
    }
    
    /**
     * Find first available room instance of a given type
     */
    private function find_available_room_instance($room_type_id, $check_in, $check_out)
    {
        // Get all room instances of this type
        $instances = get_posts([
            'post_type' => 'mphb_room',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => 'mphb_room_type_id',
                    'value' => $room_type_id,
                ],
            ],
        ]);
        
        if (empty($instances)) {
            return null;
        }
        
        // Check each instance for conflicts
        foreach ($instances as $room_id) {
            if ($this->is_room_available($room_id, $check_in, $check_out)) {
                return $room_id;
            }
        }
        
        // All instances booked
        Shaped_RC_Error_Logger::log_warning('No available room instances found', [
            'room_type_id' => $room_type_id,
            'check_in' => $check_in,
            'check_out' => $check_out,
            'total_instances' => count($instances),
        ]);
        
        return null;
    }
    
    /**
     * Check if a specific room instance is available for date range
     */
    private function is_room_available($room_id, $check_in, $check_out)
    {
        global $wpdb;
        
        // Count overlapping confirmed reservations for this specific room
        $conflicts = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} AS p
            INNER JOIN {$wpdb->posts} AS booking ON p.post_parent = booking.ID
            INNER JOIN {$wpdb->postmeta} AS room ON p.ID = room.post_id AND room.meta_key = '_mphb_room_id'
            INNER JOIN {$wpdb->postmeta} AS checkin ON p.ID = checkin.post_id AND checkin.meta_key = 'mphb_check_in_date'
            INNER JOIN {$wpdb->postmeta} AS checkout ON p.ID = checkout.post_id AND checkout.meta_key = 'mphb_check_out_date'
            WHERE p.post_type = 'mphb_reserved_room'
            AND p.post_status = 'publish'
            AND booking.post_status IN ('confirmed', 'pending-payment')
            AND room.meta_value = %d
            AND NOT (
                checkout.meta_value <= %s
                OR checkin.meta_value >= %s
            )
        ", $room_id, $check_in, $check_out));
        
        return $conflicts == 0;
    }
}