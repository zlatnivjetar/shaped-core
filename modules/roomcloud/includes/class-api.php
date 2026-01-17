<?php
/**
 * RoomCloud XML API Wrapper
 * Handles all communication with RoomCloud API
 */

if (!defined('ABSPATH')) {
    exit;
}

class Shaped_RC_API
{
    private static $instance = null;
    
    // Will be loaded from settings
    private static $service_url = '';
    private static $username = '';
    private static $password = '';
    private static $hotel_id = '';
    private static $channel_id = '';
    
    public static function init()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct()
    {
        // Load config from settings
        self::$service_url = get_option('shaped_rc_service_url', '');
        self::$username = get_option('shaped_rc_username', '');
        self::$password = get_option('shaped_rc_password', '');
        self::$hotel_id = get_option('shaped_rc_hotel_id', '9335');
        self::$channel_id = get_option('shaped_rc_channel_id', '');
    }
    
    /**
     * Get configuration status
     */
    public static function is_configured()
    {
        self::init();
        return !empty(self::$service_url) && !empty(self::$username) && !empty(self::$password);
    }
    
    /**
     * Send reservation to RoomCloud (create or update)
     */
    public static function send_reservation($booking_id, $status = 'SUBMITTED', $amount = null)
    {
        if (!self::is_configured()) {
            Shaped_RC_Error_Logger::log_critical('RoomCloud not configured', ['booking_id' => $booking_id]);
            return false;
        }
        
        $booking = MPHB()->getBookingRepository()->findById($booking_id);
        if (!$booking) {
            Shaped_RC_Error_Logger::log_error('Booking not found', ['booking_id' => $booking_id]);
            return false;
        }
        
        // Build reservation XML
        $xml = self::build_reservation_xml($booking, $status, $amount);
        
        // Check if XML building failed
        if ($xml === false) {
            Shaped_RC_Error_Logger::log_error('Failed to build reservation XML', [
                'booking_id' => $booking_id,
                'status' => $status,
            ]);
            
            // Queue for retry
            Shaped_RC_Error_Logger::queue_retry($booking_id, 'send_reservation', [
                'status' => $status,
            ], 'Failed to build XML - room type or booking data missing');
            
            return false;
        }
        
        // Send to RoomCloud with booking_id for URL parameters
        $response = self::send_request($xml, $booking_id);
        
        if ($response['success']) {
            Shaped_RC_Error_Logger::log_info("Reservation sent successfully", [
                'booking_id' => $booking_id,
                'status' => $status,
            ]);
            
            // Mark as synced
            update_post_meta($booking_id, '_roomcloud_synced', true);
            update_post_meta($booking_id, '_roomcloud_status', $status);
            update_post_meta($booking_id, '_roomcloud_last_sync', current_time('mysql'));
            
            return true;
        } else {
            // Check if rejection due to no availability
            $is_availability_error = self::is_availability_error($response);
            
            Shaped_RC_Error_Logger::log_error('Failed to send reservation', [
                'booking_id' => $booking_id,
                'error' => $response['error'],
                'availability_error' => $is_availability_error,
            ]);
            
            if (!$is_availability_error) {
                // Queue for retry only if not availability issue
                Shaped_RC_Error_Logger::queue_retry($booking_id, 'send_reservation', [
                    'status' => $status,
                ], $response['error']);
            }
            
            return false;
        }
    }
    
    /**
     * Send availability update to RoomCloud
     */
    public static function modify_inventory($room_id, $date, $quantity, $rate_id = null)
    {
        if (!self::is_configured()) {
            Shaped_RC_Error_Logger::log_critical('RoomCloud not configured');
            return false;
        }
        
        // Get rate ID from settings if not provided
        if (!$rate_id) {
            $rate_id = get_option('shaped_rc_rate_id', '99999'); // Placeholder
        }
        
        // Build modify XML
        $xml = self::build_modify_xml($room_id, $date, $quantity, $rate_id);
        
        // Send to RoomCloud
        $response = self::send_request($xml);
        
        if ($response['success']) {
            Shaped_RC_Error_Logger::log_info("Availability updated", [
                'room_id' => $room_id,
                'date' => $date,
                'quantity' => $quantity,
            ]);
            return true;
        } else {
            Shaped_RC_Error_Logger::log_error('Failed to update availability', [
                'room_id' => $room_id,
                'error' => $response['error'],
            ]);
            return false;
        }
    }
    
    /**
     * Build reservation XML
     */
    private static function build_reservation_xml($booking, $status, $amount = null)
    {
        self::init();
        
        $booking_id = $booking->getId();
        $customer = $booking->getCustomer();
        $check_in = $booking->getCheckInDate()->format('Y-m-d');
        $check_out = $booking->getCheckOutDate()->format('Y-m-d');
        
        // Get room details
        $reserved_rooms = $booking->getReservedRooms();
        if (empty($reserved_rooms)) {
            Shaped_RC_Error_Logger::log_error('No reserved rooms found', ['booking_id' => $booking_id]);
            return false;
        }
        
        $room = reset($reserved_rooms);
        $room_type_id = $room->getRoomTypeId();
        
        // Fix: MotoPress API sometimes returns 0, fallback to direct meta read
        if (!$room_type_id || $room_type_id === 0) {
            $room_type_id = get_post_meta($room->getId(), 'mphb_room_type_id', true);
        }

        $room_type = MPHB()->getRoomTypeRepository()->findById($room_type_id);
        
        if (!$room_type) {
            Shaped_RC_Error_Logger::log_error('Room type not found', [
                'booking_id' => $booking_id,
                'room_type_id' => $room_type_id
            ]);
            return false;
        }
        
        $room_slug = sanitize_title($room_type->getTitle());
        
        // Get RoomCloud room ID from mapping
        $room_mapping = get_option('shaped_rc_room_mapping', []);
        $roomcloud_room_id = isset($room_mapping[$room_slug]) ? $room_mapping[$room_slug] : '';
        
        if (empty($roomcloud_room_id)) {
            Shaped_RC_Error_Logger::log_critical('Room mapping not found', [
                'booking_id' => $booking_id,
                'room_slug' => $room_slug,
            ]);
            return false;
        }
        
        // Get rate ID
        $rate_id = get_option('shaped_rc_rate_id', '99999');
        
        // Get amount - use provided value or fetch from meta
        if ($amount === null) {
            $amount = get_post_meta($booking_id, '_shaped_payment_amount', true);
            if (!$amount) {
                $amount = Shaped_Pricing::calculate_final_amount($booking);
            }
        }
        
        // Map status codes
        $status_map = [
            'SUBMITTED' => '2',
            'CONFIRMED' => '4',
            'CANCELLED' => '7',
        ];
        $status_code = isset($status_map[$status]) ? $status_map[$status] : '2';
        
        // Get adults/children count
        $adults = 2; // Default
        $children = 0;
        
        // Try to get from booking
        $price_breakdown = $booking->getPriceBreakdown();
        if (isset($price_breakdown['rooms'][0]['adults'])) {
            $adults = $price_breakdown['rooms'][0]['adults'];
        }
        if (isset($price_breakdown['rooms'][0]['children'])) {
            $children = $price_breakdown['rooms'][0]['children'];
        }
        
        // Cancellation policy
        $cancellation_policy = 'Free cancellation up to 7 days before check-in. Non-refundable within 7 days of arrival.';
        
        // Build XML
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<Request userName="' . esc_attr(self::$username) . '" password="' . esc_attr(self::$password) . '">' . "\n";
        $xml .= '  <reservation id="' . $booking_id . '" ';
        $xml .= 'checkin="' . $check_in . '" ';
        $xml .= 'checkout="' . $check_out . '" ';
        $xml .= 'firstName="' . esc_attr($customer->getFirstName()) . '" ';
        $xml .= 'lastName="' . esc_attr($customer->getLastName()) . '" ';
        $xml .= 'email="' . esc_attr($customer->getEmail()) . '" ';
        $xml .= 'telephone="' . esc_attr($customer->getPhone()) . '" ';
        $xml .= 'rooms="1" ';
        $xml .= 'adults="' . $adults . '" ';
        $xml .= 'children="' . $children . '" ';
        $xml .= 'price="' . number_format($amount, 2, '.', '') . '" ';
        $xml .= 'currency="EUR" ';
        $xml .= 'status="' . $status_code . '" ';
        $xml .= 'creation_date="' . get_post_field('post_date', $booking_id) . '" ';
        
        // Use channel_id if configured, otherwise fall back to "Website"
        if (!empty(self::$channel_id)) {
            $xml .= 'channel_id="' . esc_attr(self::$channel_id) . '" ';
        }
        $xml .= 'source_of_business="Website">' . "\n";
        
        // Room details
        $xml .= '    <room id="' . $roomcloud_room_id . '" ';
        $xml .= 'description="' . esc_attr($room_type->getTitle()) . '" ';
        $xml .= 'checkin="' . $check_in . '" ';
        $xml .= 'checkout="' . $check_out . '" ';
        $xml .= 'rateId="' . $rate_id . '" ';
        $xml .= 'quantity="1" ';
        $xml .= 'price="' . number_format($amount, 2, '.', '') . '" ';
        $xml .= 'adults="' . $adults . '" ';
        $xml .= 'children="' . $children . '" ';
        $xml .= 'status="' . $status_code . '" ';
        $xml .= 'cancellation_policy="' . esc_attr($cancellation_policy) . '"/>' . "\n";
        
        $xml .= '  </reservation>' . "\n";
        $xml .= '</Request>';
        
        return $xml;
    }
    
    /**
     * Build modify inventory XML
     */
    private static function build_modify_xml($roomcloud_room_id, $date, $quantity, $rate_id)
    {
        self::init();
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<Request userName="' . esc_attr(self::$username) . '" password="' . esc_attr(self::$password) . '">' . "\n";
        $xml .= '  <modify hotelId="' . self::$hotel_id . '" startDate="' . $date . '" endDate="' . $date . '">' . "\n";
        $xml .= '    <availability day="' . $date . '" roomId="' . $roomcloud_room_id . '" quantity="' . $quantity . '">' . "\n";
        $xml .= '      <rate rateId="' . $rate_id . '" currency="EUR"/>' . "\n";
        $xml .= '    </availability>' . "\n";
        $xml .= '  </modify>' . "\n";
        $xml .= '</Request>';
        
        return $xml;
    }
    
    /**
     * Send HTTP request to RoomCloud
     * @param string $xml XML request body
     * @param int|null $booking_id Booking ID to append to URL (for reservation push)
     */
    private static function send_request($xml, $booking_id = null)
    {
        self::init();
        
        // Build URL with parameters for reservation push
        $url = self::$service_url;
        if ($booking_id !== null) {
            $separator = (strpos($url, '?') !== false) ? '&' : '?';
            $url .= $separator . 'hotelId=' . urlencode(self::$hotel_id) . '&reservationId=' . urlencode($booking_id);
        }
        
        if (empty(self::$service_url)) {
            return [
                'success' => false,
                'error' => 'Service URL not configured',
            ];
        }
        
        Shaped_RC_Error_Logger::log_info('Sending request to RoomCloud', [
            'url' => $url,
            'xml_preview' => substr($xml, 0, 200) . '...',
        ]);
        
        $response = wp_remote_post($url, [
            'body' => $xml,
            'headers' => [
                'Content-Type' => 'text/xml; charset=UTF-8',
            ],
            'timeout' => 30,
            'sslverify' => true,
        ]);
        
        // Check for HTTP errors
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message(),
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // Log response
        Shaped_RC_Error_Logger::log_info('Received response from RoomCloud', [
            'status_code' => $status_code,
            'body_preview' => substr($body, 0, 200) . '...',
        ]);
        
        // Parse XML response
        $result = self::parse_response($body);
        
        if (!$result['success']) {
            Shaped_RC_Error_Logger::add_to_digest('RoomCloud API error: ' . $result['error'], [
                'status_code' => $status_code,
            ]);
        }
        
        return $result;
    }
    
    /**
     * Parse XML response
     */
    private static function parse_response($xml_string)
    {
        // Remove BOM if present
        $xml_string = preg_replace('/^[\x00-\x1F\x80-\xFF]{3}/', '', $xml_string);
        
        // Check if response is HTML (test page)
        if (stripos($xml_string, '<html') !== false || stripos($xml_string, '<!DOCTYPE') !== false) {
            return [
                'success' => false,
                'error' => 'Received HTML response instead of XML. Check API endpoint URL.',
            ];
        }
        
        // Try to parse XML
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xml_string);
        
        if ($xml === false) {
            $errors = libxml_get_errors();
            $error_msg = 'Invalid XML response';
            if (!empty($errors)) {
                $error_msg .= ': ' . $errors[0]->message;
            }
            libxml_clear_errors();
            
            return [
                'success' => false,
                'error' => $error_msg,
            ];
        }
        
        // Check for error element
        if (isset($xml->error)) {
            $error_code = (string) $xml->error['code'];
            $error_message = (string) $xml->error['message'];
            
            return [
                'success' => false,
                'error' => "RoomCloud Error {$error_code}: {$error_message}",
                'error_code' => $error_code,
            ];
        }
        
        // Check for <ok/> element (successful modify)
        if (isset($xml->ok)) {
            return [
                'success' => true,
                'data' => $xml,
            ];
        }
        
        // Default success if no error element
        return [
            'success' => true,
            'data' => $xml,
        ];
    }
    
    /**
     * Test outbound connection to RoomCloud's endpoint
     * Note: This tests if we can reach RoomCloud, NOT if RoomCloud can reach us
     */
    public static function test_connection()
    {
        self::init();

        if (!self::is_configured()) {
            return [
                'success' => false,
                'error' => 'Not configured - please fill in all API credentials',
            ];
        }

        // Test that we can reach the RoomCloud endpoint
        $response = wp_remote_get(self::$service_url, [
            'timeout' => 15,
            'sslverify' => true,
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => 'Cannot reach RoomCloud endpoint: ' . $response->get_error_message(),
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);

        // 200 or 405 (Method Not Allowed for GET) both indicate the endpoint is reachable
        if ($status_code >= 200 && $status_code < 500) {
            return [
                'success' => true,
                'message' => 'Outbound connection OK (can reach RoomCloud endpoint)',
                'note' => 'This only tests outbound connectivity. To verify inbound (RoomCloud → your site), use "Test Webhook" below.',
            ];
        } else {
            return [
                'success' => false,
                'error' => "RoomCloud endpoint returned HTTP {$status_code}",
            ];
        }
    }

    /**
     * Test the webhook endpoint (inbound from RoomCloud)
     * Sends a test request to our own webhook to verify it responds correctly
     */
    public static function test_webhook()
    {
        $webhook_url = rest_url('shaped/v1/roomcloud-webhook');

        // Build a minimal test request (getHotels)
        $username = get_option('shaped_rc_username', '');
        $password = get_option('shaped_rc_password', '');

        if (empty($username) || empty($password)) {
            return [
                'success' => false,
                'error' => 'Username or password not configured',
            ];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<Request userName="' . esc_attr($username) . '" password="' . esc_attr($password) . '">' . "\n";
        $xml .= '  <getHotels/>' . "\n";
        $xml .= '</Request>';

        // Send request to our own webhook
        $response = wp_remote_post($webhook_url, [
            'body' => $xml,
            'headers' => [
                'Content-Type' => 'text/xml; charset=UTF-8',
            ],
            'timeout' => 30,
            'sslverify' => false, // Allow self-signed for local testing
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => 'Webhook unreachable: ' . $response->get_error_message(),
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        // Check if response is valid XML
        if ($status_code !== 200) {
            return [
                'success' => false,
                'error' => "Webhook returned HTTP {$status_code}",
                'body_preview' => substr($body, 0, 200),
            ];
        }

        // Verify response is valid XML (not JSON or HTML)
        if (strpos($body, '<?xml') === false && strpos($body, '<Response') === false) {
            return [
                'success' => false,
                'error' => 'Webhook did not return valid XML response',
                'body_preview' => substr($body, 0, 200),
            ];
        }

        // Check for "Content is not allowed in prolog" scenario
        $first_char_pos = strpos($body, '<');
        if ($first_char_pos > 0) {
            $prefix = substr($body, 0, $first_char_pos);
            return [
                'success' => false,
                'error' => 'Webhook response has content before XML declaration (this causes RoomCloud errors)',
                'problematic_prefix' => $prefix,
            ];
        }

        return [
            'success' => true,
            'message' => 'Webhook responding correctly with valid XML',
        ];
    }
    
    /**
     * Get rate plans from RoomCloud
     */
    public static function get_rates()
    {
        self::init();
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<Request userName="' . esc_attr(self::$username) . '" password="' . esc_attr(self::$password) . '">' . "\n";
        $xml .= '  <getRates hotelId="' . self::$hotel_id . '"/>' . "\n";
        $xml .= '</Request>';
        
        $response = self::send_request($xml);
        
        if ($response['success'] && isset($response['data']->rate)) {
            $rates = [];
            foreach ($response['data']->rate as $rate) {
                $rates[] = [
                    'id' => (string) $rate['rateId'],
                    'description' => (string) $rate['description'],
                ];
            }
            return [
                'success' => true,
                'rates' => $rates,
            ];
        }
        
        return [
            'success' => false,
            'error' => $response['error'] ?? 'Unknown error',
        ];
    }
    
    /**
     * Get room types from RoomCloud
     */
    public static function get_rooms()
    {
        self::init();
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<Request userName="' . esc_attr(self::$username) . '" password="' . esc_attr(self::$password) . '">' . "\n";
        $xml .= '  <getRooms hotelId="' . self::$hotel_id . '"/>' . "\n";
        $xml .= '</Request>';
        
        $response = self::send_request($xml);
        
        if ($response['success'] && isset($response['data']->room)) {
            $rooms = [];
            foreach ($response['data']->room as $room) {
                $rooms[] = [
                    'id' => (string) $room['id'],
                    'description' => (string) $room['description'],
                ];
            }
            return [
                'success' => true,
                'rooms' => $rooms,
            ];
        }
        
        return [
            'success' => false,
            'error' => $response['error'] ?? 'Unknown error',
        ];
    }
    
    /**
     * Check if error response indicates availability issue
     * 
     * @param array $response Response from send_request
     * @return bool True if availability error
     */
    private static function is_availability_error($response)
    {
        if (!isset($response['error'])) {
            return false;
        }
        
        $error = strtolower($response['error']);
        
        // Common availability error patterns
        $patterns = [
            'no availability',
            'not available',
            'no rooms',
            'fully booked',
            'sold out',
            'unavailable',
        ];
        
        foreach ($patterns as $pattern) {
            if (strpos($error, $pattern) !== false) {
                return true;
            }
        }
        
        // Check error code if present
        if (isset($response['error_code'])) {
            // RoomCloud error codes for availability issues (adjust if needed)
            $availability_codes = ['100', '101', '102'];
            if (in_array($response['error_code'], $availability_codes)) {
                return true;
            }
        }
        
        return false;
    }
}