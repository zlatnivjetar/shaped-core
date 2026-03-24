<?php
/**
 * RoomCloud XML API Wrapper
 * Handles all communication with RoomCloud API.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Shaped_RC_API
{
    private static $instance = null;

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
        self::$service_url = defined('SHAPED_RC_SERVICE_URL') ? SHAPED_RC_SERVICE_URL : '';
        self::$username = defined('SHAPED_RC_USERNAME') ? SHAPED_RC_USERNAME : '';
        self::$password = defined('SHAPED_RC_PASSWORD') ? SHAPED_RC_PASSWORD : '';
        self::$channel_id = defined('SHAPED_RC_CHANNEL_ID') ? SHAPED_RC_CHANNEL_ID : '';
        self::$hotel_id = get_option('shaped_rc_hotel_id', '');
    }

    /**
     * Get configuration status.
     */
    public static function is_configured()
    {
        self::init();

        return !empty(self::$service_url) && !empty(self::$username) && !empty(self::$password);
    }

    /**
     * Return the current RoomCloud configuration snapshot.
     */
    public static function get_configuration_snapshot(): array
    {
        self::init();

        return [
            'service_url' => self::$service_url,
            'username' => self::$username,
            'password_configured' => self::$password !== '',
            'hotel_id' => (string) get_option('shaped_rc_hotel_id', ''),
            'rate_id' => (string) get_option('shaped_rc_rate_id', ''),
            'channel_id' => self::$channel_id,
            'room_mapping' => get_option('shaped_rc_room_mapping', []),
        ];
    }

    /**
     * Send reservation to RoomCloud (create or update).
     */
    public static function send_reservation($booking_id, $status = 'SUBMITTED', $prepaid = 0, array $options = [])
    {
        if (!self::is_configured()) {
            Shaped_RC_Error_Logger::log_critical('RoomCloud not configured', ['booking_id' => $booking_id]);
            return [
                'success' => false,
                'error' => 'RoomCloud not configured',
                'availability_error' => false,
            ];
        }

        $booking = MPHB()->getBookingRepository()->findById($booking_id, true);
        if (!$booking) {
            Shaped_RC_Error_Logger::log_error('Booking not found', ['booking_id' => $booking_id]);
            return [
                'success' => false,
                'error' => 'Booking not found',
                'availability_error' => false,
            ];
        }

        $payload = array_merge([
            'status' => $status,
            'prepaid' => (float) $prepaid,
        ], $options);

        $xml = self::build_reservation_xml($booking, $payload['status'], $payload['prepaid'], $payload);

        if ($xml === false) {
            Shaped_RC_Error_Logger::log_error('Failed to build reservation XML', [
                'booking_id' => $booking_id,
                'status' => $payload['status'],
            ]);

            Shaped_RC_Error_Logger::queue_retry(
                $booking_id,
                'send_reservation',
                $payload,
                'Failed to build XML - room type or booking data missing'
            );

            return [
                'success' => false,
                'error' => 'Failed to build reservation XML',
                'availability_error' => false,
            ];
        }

        $response = self::send_request($xml, $booking_id);

        if (!empty($response['success'])) {
            Shaped_RC_Error_Logger::log_info('Reservation sent successfully', [
                'booking_id' => $booking_id,
                'status' => $payload['status'],
                'prepaid' => $payload['prepaid'],
            ]);

            update_post_meta($booking_id, '_roomcloud_synced', true);
            update_post_meta($booking_id, '_roomcloud_status', $payload['status']);
            update_post_meta($booking_id, '_roomcloud_last_sync', current_time('mysql'));

            if (!empty($payload['payload_hash'])) {
                update_post_meta($booking_id, '_roomcloud_last_payload_hash', $payload['payload_hash']);
            }

            Shaped_RC_Error_Logger::clear_retries($booking_id, 'send_reservation');

            return [
                'success' => true,
                'data' => $response['data'] ?? null,
            ];
        }

        $is_availability_error = self::is_availability_error($response);

        Shaped_RC_Error_Logger::log_error('Failed to send reservation', [
            'booking_id' => $booking_id,
            'error' => $response['error'] ?? 'Unknown error',
            'availability_error' => $is_availability_error,
        ]);

        if (!$is_availability_error) {
            Shaped_RC_Error_Logger::queue_retry(
                $booking_id,
                'send_reservation',
                $payload,
                $response['error'] ?? 'Unknown RoomCloud error'
            );
        }

        return [
            'success' => false,
            'error' => $response['error'] ?? 'Unknown RoomCloud error',
            'availability_error' => $is_availability_error,
            'error_code' => $response['error_code'] ?? '',
        ];
    }

    /**
     * Send availability update to RoomCloud.
     */
    public static function modify_inventory($room_id, $date, $quantity, $rate_id = null)
    {
        if (!self::is_configured()) {
            Shaped_RC_Error_Logger::log_critical('RoomCloud not configured');
            return false;
        }

        if (!$rate_id) {
            $rate_id = get_option('shaped_rc_rate_id', '');
        }

        $xml = self::build_modify_xml($room_id, $date, $quantity, $rate_id);
        $response = self::send_request($xml);

        if (!empty($response['success'])) {
            Shaped_RC_Error_Logger::log_info('Availability updated', [
                'room_id' => $room_id,
                'date' => $date,
                'quantity' => $quantity,
            ]);
            return true;
        }

        Shaped_RC_Error_Logger::log_error('Failed to update availability', [
            'room_id' => $room_id,
            'error' => $response['error'] ?? 'Unknown error',
        ]);
        return false;
    }

    /**
     * Build reservation XML.
     */
    private static function build_reservation_xml($booking, $status, $prepaid = 0, array $options = [])
    {
        self::init();

        $booking_id = $booking->getId();
        $customer = $booking->getCustomer();
        $check_in_date = $booking->getCheckInDate();
        $check_out_date = $booking->getCheckOutDate();
        $check_in = $check_in_date->format('Y-m-d');
        $check_out = $check_out_date->format('Y-m-d');

        $room_type_id = 0;
        $room_title = '';
        $reserved_rooms = $booking->getReservedRooms();

        if (!empty($reserved_rooms)) {
            $room = reset($reserved_rooms);
            $room_type_id = (int) $room->getRoomTypeId();

            if (!$room_type_id || $room_type_id === 0) {
                $room_type_id = (int) get_post_meta($room->getId(), 'mphb_room_type_id', true);
            }
        } elseif (method_exists($booking, 'getReservedRoomTypeIds')) {
            $room_type_ids = array_filter(array_map('intval', (array) $booking->getReservedRoomTypeIds()));
            if (!empty($room_type_ids)) {
                $room_type_id = (int) reset($room_type_ids);
            }
        }

        if (!$room_type_id) {
            $room_type_id = (int) get_post_meta($booking_id, 'mphb_room_type_id', true);
        }

        $room_type = $room_type_id ? MPHB()->getRoomTypeRepository()->findById($room_type_id) : null;

        if (!$room_type) {
            Shaped_RC_Error_Logger::log_error('Room type not found', [
                'booking_id' => $booking_id,
                'room_type_id' => $room_type_id,
            ]);
            return false;
        }

        $room_title = $room_type->getTitle();
        $room_slug = sanitize_title($room_title);

        $room_mapping = get_option('shaped_rc_room_mapping', []);
        $roomcloud_room_id = isset($room_mapping[$room_slug]) ? $room_mapping[$room_slug] : '';

        if (empty($roomcloud_room_id)) {
            Shaped_RC_Error_Logger::log_critical('Room mapping not found', [
                'booking_id' => $booking_id,
                'room_slug' => $room_slug,
            ]);
            return false;
        }

        $rate_id = get_option('shaped_rc_rate_id', '');
        $total_price = Shaped_Pricing::calculate_final_amount($booking);
        if (!$total_price || $total_price <= 0) {
            $total_price = (float) $booking->getTotalPrice();
        }

        $prepaid = max(0, (float) $prepaid);
        if ($prepaid > $total_price) {
            $prepaid = $total_price;
        }

        $status_map = [
            'SUBMITTED' => '2',
            'CONFIRMED' => '4',
            'CANCELLED' => '7',
        ];
        $status_code = isset($status_map[$status]) ? $status_map[$status] : '2';

        $adults = 2;
        $children = 0;
        $price_breakdown = $booking->getPriceBreakdown();

        if (isset($price_breakdown['rooms'][0]['adults'])) {
            $adults = (int) $price_breakdown['rooms'][0]['adults'];
        }
        if (isset($price_breakdown['rooms'][0]['children'])) {
            $children = (int) $price_breakdown['rooms'][0]['children'];
        }

        $nights = $check_in_date->diff($check_out_date)->days;
        $per_night_price = ($nights > 0) ? round($total_price / $nights, 2) : $total_price;
        $cancellation_policy = 'Free cancellation up to 7 days before check-in. Non-refundable within 7 days of arrival.';

        $payment_type = self::get_optional_string($options, 'paymentType');
        $lang = self::get_booking_language($booking_id, $booking, $options);
        $pin = self::get_optional_reservation_value(
            $booking_id,
            $booking,
            $options,
            'pin',
            ['_roomcloud_pin', '_shaped_promo_code', '_shaped_discount_code']
        );
        $offer = self::get_optional_reservation_value(
            $booking_id,
            $booking,
            $options,
            'offer',
            ['_roomcloud_offer', '_shaped_offer_description', '_shaped_offer_name']
        );
        $notes = self::get_optional_reservation_value(
            $booking_id,
            $booking,
            $options,
            'notes',
            ['_roomcloud_notes', '_shaped_booking_notes', '_shaped_notes', 'mphb_notes', 'mphb_note']
        );
        $source_of_business = self::get_optional_string($options, 'source_of_business');
        if ($source_of_business === '') {
            $source_of_business = 'Website';
        }

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
        $xml .= 'price="' . number_format($total_price, 2, '.', '') . '" ';
        $xml .= 'prepaid="' . number_format($prepaid, 2, '.', '') . '" ';
        $xml .= 'currency="EUR" ';
        $xml .= 'status="' . $status_code . '" ';
        $xml .= 'creation_date="' . get_post_field('post_date', $booking_id) . '" ';

        if ($payment_type !== '') {
            $xml .= 'paymentType="' . esc_attr($payment_type) . '" ';
        }
        if ($lang !== '') {
            $xml .= 'lang="' . esc_attr($lang) . '" ';
        }
        if ($offer !== '') {
            $xml .= 'offer="' . esc_attr($offer) . '" ';
        }
        if ($pin !== '') {
            $xml .= 'pin="' . esc_attr($pin) . '" ';
        }
        if ($notes !== '') {
            $xml .= 'notes="' . esc_attr($notes) . '" ';
        }
        if (!empty(self::$channel_id)) {
            $xml .= 'channel_id="' . esc_attr(self::$channel_id) . '" ';
        }

        $xml .= 'source_of_business="' . esc_attr($source_of_business) . '">' . "\n";

        $xml .= '    <room id="' . $roomcloud_room_id . '" ';
        $xml .= 'description="' . esc_attr($room_title) . '" ';
        $xml .= 'checkin="' . $check_in . '" ';
        $xml .= 'checkout="' . $check_out . '" ';
        $xml .= 'rateId="' . $rate_id . '" ';
        $xml .= 'quantity="1" ';
        $xml .= 'price="' . number_format($total_price, 2, '.', '') . '" ';
        $xml .= 'adults="' . $adults . '" ';
        $xml .= 'children="' . $children . '" ';
        $xml .= 'status="' . $status_code . '" ';
        $xml .= 'cancellation_policy="' . esc_attr($cancellation_policy) . '">' . "\n";

        $current_date = clone $check_in_date;
        $remaining_total = $total_price;

        for ($i = 0; $i < $nights; $i++) {
            if ($i === $nights - 1) {
                $day_price = $remaining_total;
            } else {
                $day_price = $per_night_price;
                $remaining_total -= $per_night_price;
            }

            $xml .= '      <dayPrice day="' . $current_date->format('Y-m-d') . '" ';
            $xml .= 'roomId="' . $roomcloud_room_id . '" ';
            $xml .= 'rateId="' . $rate_id . '" ';
            $xml .= 'price="' . number_format($day_price, 2, '.', '') . '"/>' . "\n";

            $current_date->modify('+1 day');
        }

        $xml .= '    </room>' . "\n";
        $xml .= '  </reservation>' . "\n";
        $xml .= '</Request>';

        return $xml;
    }

    /**
     * Build modify inventory XML.
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
     * Send HTTP request to RoomCloud.
     */
    private static function send_request($xml, $booking_id = null)
    {
        self::init();

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

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message(),
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        Shaped_RC_Error_Logger::log_info('Received response from RoomCloud', [
            'status_code' => $status_code,
            'body_preview' => substr($body, 0, 200) . '...',
        ]);

        $result = self::parse_response($body);

        if (empty($result['success'])) {
            Shaped_RC_Error_Logger::add_to_digest('RoomCloud API error: ' . ($result['error'] ?? 'Unknown error'), [
                'status_code' => $status_code,
            ]);
        }

        return $result;
    }

    /**
     * Parse XML response.
     */
    private static function parse_response($xml_string)
    {
        $xml_string = preg_replace('/^[\x00-\x1F\x80-\xFF]{3}/', '', $xml_string);

        if (stripos($xml_string, '<html') !== false || stripos($xml_string, '<!DOCTYPE') !== false) {
            return [
                'success' => false,
                'error' => 'Received HTML response instead of XML. Check API endpoint URL.',
            ];
        }

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

        if (isset($xml->error)) {
            $error_code = (string) $xml->error['code'];
            $error_message = (string) $xml->error['message'];

            return [
                'success' => false,
                'error' => "RoomCloud Error {$error_code}: {$error_message}",
                'error_code' => $error_code,
            ];
        }

        if (isset($xml->ok)) {
            return [
                'success' => true,
                'data' => $xml,
            ];
        }

        return [
            'success' => true,
            'data' => $xml,
        ];
    }

    /**
     * Test outbound connection to RoomCloud's endpoint.
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

        if ($status_code >= 200 && $status_code < 500) {
            return [
                'success' => true,
                'message' => 'Outbound connection OK (can reach RoomCloud endpoint)',
                'note' => 'This only tests outbound connectivity. To verify inbound (RoomCloud -> your site), use "Test Webhook" below.',
            ];
        }

        return [
            'success' => false,
            'error' => "RoomCloud endpoint returned HTTP {$status_code}",
        ];
    }

    /**
     * Test the webhook endpoint (inbound from RoomCloud).
     */
    public static function test_webhook()
    {
        $webhook_url = rest_url('shaped/v1/roomcloud-webhook');
        $username = defined('SHAPED_RC_USERNAME') ? SHAPED_RC_USERNAME : '';
        $password = defined('SHAPED_RC_PASSWORD') ? SHAPED_RC_PASSWORD : '';
        $echo_token = 'roomcloud-test-webhook';

        if (empty($username) || empty($password)) {
            return [
                'success' => false,
                'error' => 'Username or password not configured in wp-config.php',
            ];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<Request userName="' . esc_attr($username) . '" password="' . esc_attr($password) . '" echoToken="' . esc_attr($echo_token) . '">' . "\n";
        $xml .= '  <getHotels/>' . "\n";
        $xml .= '</Request>';

        $response = wp_remote_post($webhook_url, [
            'body' => $xml,
            'headers' => [
                'Content-Type' => 'text/xml; charset=UTF-8',
            ],
            'timeout' => 30,
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => 'Webhook unreachable: ' . $response->get_error_message(),
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($status_code !== 200) {
            return [
                'success' => false,
                'error' => "Webhook returned HTTP {$status_code}",
                'body_preview' => substr($body, 0, 200),
            ];
        }

        if (strpos($body, '<?xml') === false && strpos($body, '<Response') === false) {
            return [
                'success' => false,
                'error' => 'Webhook did not return valid XML response',
                'body_preview' => substr($body, 0, 200),
            ];
        }

        $first_char_pos = strpos($body, '<');
        if ($first_char_pos > 0) {
            $prefix = substr($body, 0, $first_char_pos);
            return [
                'success' => false,
                'error' => 'Webhook response has content before XML declaration (this causes RoomCloud errors)',
                'problematic_prefix' => $prefix,
            ];
        }

        if (strpos($body, 'echoToken="' . $echo_token . '"') === false) {
            return [
                'success' => false,
                'error' => 'Webhook XML response is missing the echoed echoToken attribute',
                'body_preview' => substr($body, 0, 200),
            ];
        }

        return [
            'success' => true,
            'message' => 'Webhook responding correctly with valid XML and echoed echoToken',
        ];
    }

    /**
     * Get rate plans from RoomCloud.
     */
    public static function get_rates()
    {
        self::init();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<Request userName="' . esc_attr(self::$username) . '" password="' . esc_attr(self::$password) . '">' . "\n";
        $xml .= '  <getRates hotelId="' . self::$hotel_id . '"/>' . "\n";
        $xml .= '</Request>';

        $response = self::send_request($xml);

        if (!empty($response['success']) && isset($response['data']->rate)) {
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
     * Get room types from RoomCloud.
     */
    public static function get_rooms()
    {
        self::init();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<Request userName="' . esc_attr(self::$username) . '" password="' . esc_attr(self::$password) . '">' . "\n";
        $xml .= '  <getRooms hotelId="' . self::$hotel_id . '"/>' . "\n";
        $xml .= '</Request>';

        $response = self::send_request($xml);

        if (!empty($response['success']) && isset($response['data']->room)) {
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
     * Check if error response indicates availability issue.
     */
    private static function is_availability_error($response)
    {
        if (!isset($response['error'])) {
            return false;
        }

        $error = strtolower($response['error']);
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

        if (isset($response['error_code'])) {
            $availability_codes = ['100', '101', '102'];
            if (in_array($response['error_code'], $availability_codes, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Read an optional string from a payload array.
     */
    private static function get_optional_string(array $options, string $key): string
    {
        if (!isset($options[$key])) {
            return '';
        }

        return is_scalar($options[$key]) ? trim((string) $options[$key]) : '';
    }

    /**
     * Resolve optional reservation attributes from payload, filters, or stored meta.
     */
    private static function get_optional_reservation_value(int $booking_id, $booking, array $options, string $key, array $meta_keys): string
    {
        $direct_value = self::get_optional_string($options, $key);
        if ($direct_value !== '') {
            return $direct_value;
        }

        $filtered = apply_filters('shaped/roomcloud/reservation_' . $key, '', $booking_id, $booking, $options);
        if (is_scalar($filtered) && trim((string) $filtered) !== '') {
            return trim((string) $filtered);
        }

        foreach ($meta_keys as $meta_key) {
            $meta_value = get_post_meta($booking_id, $meta_key, true);
            if (is_scalar($meta_value) && trim((string) $meta_value) !== '') {
                return trim((string) $meta_value);
            }
        }

        return '';
    }

    /**
     * Best-effort language resolution for RoomCloud.
     */
    private static function get_booking_language(int $booking_id, $booking, array $options): string
    {
        $lang = self::get_optional_reservation_value(
            $booking_id,
            $booking,
            $options,
            'lang',
            ['_shaped_booking_language', '_shaped_locale', '_shaped_lang', 'locale', 'lang']
        );

        if ($lang === '' && !wp_doing_cron() && !(defined('WP_CLI') && WP_CLI)) {
            $lang = determine_locale();
        }

        $lang = str_replace('_', '-', trim($lang));

        return $lang;
    }
}
