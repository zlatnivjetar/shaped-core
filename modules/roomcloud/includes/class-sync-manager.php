<?php
/**
 * Sync Manager
 * Orchestrates bidirectional sync between MotoPress and RoomCloud.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Shaped_RC_Sync_Manager
{
    private const RETRY_OPERATION = 'send_reservation';

    private static $instance = null;

    public static function init()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        // Durable outbound sync points for website bookings.
        add_action('shaped_payment_completed', [$this, 'on_payment_completed'], 10, 2);
        add_action('shaped_booking_authorized', [$this, 'on_booking_authorized'], 10, 1);
        add_action('shaped_booking_cancelled', [$this, 'on_booking_cancelled'], 10, 1);
        add_action('mphb_booking_status_changed', [$this, 'on_booking_status_changed'], 10, 2);
        add_action('wp_trash_post', [$this, 'on_booking_trashed'], 10, 1);
        add_action('before_delete_post', [$this, 'on_booking_deleted'], 10, 1);

        // Retry failed syncs.
        add_action('init', [$this, 'schedule_retry_cron']);
        add_action('shaped_rc_retry_failed_syncs', [$this, 'process_retry_queue']);

        // Cleanup old sync flags for imported RoomCloud bookings.
        add_action('cleanup_roomcloud_flag', [$this, 'cleanup_sync_flag'], 10, 1);
    }

    /**
     * A delayed-charge booking was authorized and should now block dates in RoomCloud.
     */
    public function on_booking_authorized($booking_id)
    {
        self::sync_booking((int) $booking_id, null, [
            'handle_rejection' => true,
            'source' => 'authorized',
        ]);
    }

    /**
     * Payment completed for an immediate, delayed, or deposit booking.
     */
    public function on_payment_completed($booking_id, $mode)
    {
        $result = self::sync_booking((int) $booking_id, null, [
            'handle_rejection' => true,
            'source' => 'payment_completed',
        ]);

        if (!empty($result['skipped'])) {
            Shaped_RC_Error_Logger::log_info('Skipped payment sync to RoomCloud', [
                'booking_id' => (int) $booking_id,
                'mode' => $mode,
                'reason' => $result['skipped'],
            ]);
        }
    }

    /**
     * Booking explicitly cancelled by the website flow.
     */
    public function on_booking_cancelled($booking_id)
    {
        self::sync_booking((int) $booking_id, self::resolve_sync_state((int) $booking_id, [
            'status' => 'CANCELLED',
        ]), [
            'source' => 'booking_cancelled',
        ]);
    }

    /**
     * Catch abandoned/cancelled status transitions after a booking was already synced.
     */
    public function on_booking_status_changed($booking, $new_status)
    {
        if (!in_array($new_status, ['abandoned', 'cancelled', 'trash'], true)) {
            return;
        }

        $booking_id = is_object($booking) && method_exists($booking, 'getId')
            ? (int) $booking->getId()
            : (int) $booking;

        if (!$booking_id) {
            return;
        }

        self::sync_booking($booking_id, self::resolve_sync_state($booking_id, [
            'status' => 'CANCELLED',
        ]), [
            'source' => 'status_changed',
        ]);
    }

    /**
     * Catch manual trashing of a booking.
     */
    public function on_booking_trashed($post_id)
    {
        $post_id = (int) $post_id;

        if ($post_id <= 0 || get_post_type($post_id) !== 'mphb_booking') {
            return;
        }

        self::sync_booking($post_id, self::resolve_sync_state($post_id, [
            'status' => 'CANCELLED',
        ]), [
            'source' => 'trashed',
        ]);
    }

    /**
     * Catch hard deletes before the booking record disappears.
     */
    public function on_booking_deleted($post_id)
    {
        $post_id = (int) $post_id;

        if ($post_id <= 0 || get_post_type($post_id) !== 'mphb_booking') {
            return;
        }

        self::sync_booking($post_id, self::resolve_sync_state($post_id, [
            'status' => 'CANCELLED',
        ]), [
            'source' => 'deleted',
        ]);
    }

    /**
     * Build the RoomCloud sync state for a booking.
     */
    public static function resolve_sync_state(int $booking_id, array $overrides = []): array
    {
        $state = [
            'booking_id' => $booking_id,
            'should_send' => false,
            'status' => '',
            'prepaid' => 0.0,
            'paymentType' => '',
            'payload_hash' => '',
            'reason' => 'unresolved',
            'is_cancellation' => false,
            'was_synced' => self::has_synced_to_roomcloud($booking_id),
            'has_retry' => Shaped_RC_Error_Logger::has_pending_retry($booking_id, self::RETRY_OPERATION),
        ];

        if ($booking_id <= 0) {
            $state['reason'] = 'invalid_booking_id';
            return $state;
        }

        if (self::is_roomcloud_source($booking_id)) {
            $state['reason'] = 'roomcloud_source';
            return $state;
        }

        $booking = MPHB()->getBookingRepository()->findById($booking_id, true);
        if (!$booking) {
            $state['reason'] = 'booking_missing';
            return $state;
        }

        $post_status = (string) get_post_status($booking_id);
        $payment_status = (string) get_post_meta($booking_id, '_shaped_payment_status', true);
        $payment_mode = (string) get_post_meta($booking_id, '_shaped_payment_mode', true);
        $force_status = isset($overrides['status']) ? (string) $overrides['status'] : '';

        if ($force_status === 'CANCELLED') {
            if (!$state['was_synced'] && !$state['has_retry']) {
                $state['reason'] = 'cancelled_before_sync';
                return $state;
            }

            $state['should_send'] = true;
            $state['status'] = 'CANCELLED';
            $state['reason'] = 'forced_cancel';
            $state['is_cancellation'] = true;
        } elseif (
            in_array($post_status, ['cancelled', 'abandoned', 'trash'], true) ||
            in_array($payment_status, ['cancelled', 'abandoned', 'refund_required'], true)
        ) {
            if (!$state['was_synced'] && !$state['has_retry']) {
                $state['reason'] = 'cancelled_before_sync';
                return $state;
            }

            $state['should_send'] = true;
            $state['status'] = 'CANCELLED';
            $state['reason'] = 'cancelled_after_sync';
            $state['is_cancellation'] = true;
        } elseif ($payment_status === 'authorized') {
            $state['should_send'] = true;
            $state['status'] = 'CONFIRMED';
            $state['prepaid'] = 0.0;
            $state['reason'] = 'authorized';
        } elseif ($payment_status === 'deposit_paid') {
            $state['should_send'] = true;
            $state['status'] = 'CONFIRMED';
            $state['prepaid'] = self::get_deposit_prepaid_amount($booking_id);
            $state['reason'] = 'deposit_paid';
        } elseif ($payment_status === 'completed') {
            $state['should_send'] = true;
            $state['status'] = 'CONFIRMED';
            $state['prepaid'] = self::get_paid_amount($booking_id, $payment_mode);
            $state['reason'] = 'completed';
        } elseif ($post_status === 'confirmed') {
            $fallback_prepaid = self::get_paid_amount($booking_id, $payment_mode);

            if ($fallback_prepaid > 0 || $state['was_synced'] || $state['has_retry']) {
                $state['should_send'] = true;
                $state['status'] = 'CONFIRMED';
                $state['prepaid'] = $fallback_prepaid;
                $state['reason'] = 'confirmed_fallback';
            } else {
                $state['reason'] = 'confirmed_without_payment';
                return $state;
            }
        } else {
            $state['reason'] = 'not_durable';
            return $state;
        }

        $state['paymentType'] = isset($overrides['paymentType'])
            ? (string) $overrides['paymentType']
            : self::get_payment_type_code($state['status']);

        $state['payload_hash'] = self::build_payload_hash($state);

        return $state;
    }

    /**
     * Send a booking update to RoomCloud using the resolved state.
     */
    public static function sync_booking(int $booking_id, ?array $state = null, array $args = []): array
    {
        $args = wp_parse_args($args, [
            'force' => false,
            'handle_rejection' => false,
            'source' => 'live',
        ]);

        if (self::is_roomcloud_source($booking_id)) {
            return [
                'success' => true,
                'skipped' => 'roomcloud_source',
            ];
        }

        $state = $state ?: self::resolve_sync_state($booking_id);

        if (empty($state['should_send'])) {
            return [
                'success' => true,
                'skipped' => $state['reason'] ?? 'noop',
                'state' => $state,
            ];
        }

        $last_hash = (string) get_post_meta($booking_id, '_roomcloud_last_payload_hash', true);
        $payload_hash = isset($state['payload_hash']) ? (string) $state['payload_hash'] : '';

        if (
            !$args['force'] &&
            $payload_hash !== '' &&
            $last_hash !== '' &&
            hash_equals($last_hash, $payload_hash)
        ) {
            return [
                'success' => true,
                'skipped' => 'duplicate_payload',
                'state' => $state,
            ];
        }

        Shaped_RC_Error_Logger::log_info('Sending booking state to RoomCloud', [
            'booking_id' => $booking_id,
            'status' => $state['status'],
            'prepaid' => $state['prepaid'],
            'source' => $args['source'],
        ]);

        $result = Shaped_RC_API::send_reservation(
            $booking_id,
            $state['status'],
            $state['prepaid'],
            $state
        );

        if (!empty($result['success'])) {
            return [
                'success' => true,
                'state' => $state,
            ];
        }

        if (
            $args['handle_rejection'] &&
            empty($state['is_cancellation']) &&
            !empty($result['availability_error']) &&
            !self::is_test_endpoint()
        ) {
            self::init()->handle_booking_rejection($booking_id);
        }

        return [
            'success' => false,
            'error' => $result['error'] ?? 'Unknown RoomCloud error',
            'availability_error' => !empty($result['availability_error']),
            'state' => $state,
        ];
    }

    /**
     * Trigger a forced repair sync for a single direct booking.
     */
    public static function repair_direct_booking(int $booking_id, array $args = []): array
    {
        $args = wp_parse_args($args, [
            'dry_run' => false,
        ]);

        if (self::is_roomcloud_source($booking_id)) {
            return [
                'success' => true,
                'action' => 'skip',
                'reason' => 'roomcloud_source',
            ];
        }

        $state = self::resolve_sync_state($booking_id);

        if (empty($state['should_send'])) {
            return [
                'success' => true,
                'action' => 'skip',
                'reason' => $state['reason'] ?? 'noop',
                'state' => $state,
            ];
        }

        if ($args['dry_run']) {
            return [
                'success' => true,
                'action' => 'dry_run',
                'state' => $state,
            ];
        }

        $result = self::sync_booking($booking_id, $state, [
            'force' => true,
            'handle_rejection' => empty($state['is_cancellation']),
            'source' => 'repair',
        ]);

        return [
            'success' => !empty($result['success']),
            'action' => !empty($result['success']) ? 'synced' : 'failed',
            'reason' => $result['error'] ?? '',
            'state' => $state,
        ];
    }

    /**
     * Get repair candidates for one booking or the whole direct-booking set.
     */
    public static function get_repair_candidates(?int $booking_id = null): array
    {
        global $wpdb;

        if ($booking_id !== null) {
            return self::is_candidate_for_repair($booking_id) ? [$booking_id] : [];
        }

        $ids = $wpdb->get_col(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'mphb_booking'"
        );

        if (empty($ids)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', $ids), [self::class, 'is_candidate_for_repair']));
    }

    /**
     * Handle booking rejection from RoomCloud.
     * Attempts auto-upgrade, or cancels with refund.
     */
    private function handle_booking_rejection($booking_id)
    {
        $booking = MPHB()->getBookingRepository()->findById($booking_id, true);
        if (!$booking) {
            return;
        }

        $reserved_rooms = $booking->getReservedRooms();
        if (empty($reserved_rooms)) {
            return;
        }

        $room = reset($reserved_rooms);
        $room_type_id = $room->getRoomTypeId();

        if (!$room_type_id || $room_type_id === 0) {
            $room_type_id = get_post_meta($room->getId(), 'mphb_room_type_id', true);
        }

        $room_type = MPHB()->getRoomTypeRepository()->findById($room_type_id);
        if (!$room_type) {
            return;
        }

        $room_slug = sanitize_title($room_type->getTitle());
        $check_in = $booking->getCheckInDate()->format('Y-m-d');
        $check_out = $booking->getCheckOutDate()->format('Y-m-d');

        $upgrades = Shaped_RC_Availability_Manager::find_upgrade_options(
            $room_slug,
            $check_in,
            $check_out
        );

        if (!empty($upgrades)) {
            $upgrade = reset($upgrades);
            $this->process_auto_upgrade($booking_id, $room_slug, $upgrade);
            return;
        }

        $this->cancel_rejected_booking($booking_id);
    }

    /**
     * Process automatic upgrade.
     */
    private function process_auto_upgrade($booking_id, $original_slug, $upgrade)
    {
        $booking = MPHB()->getBookingRepository()->findById($booking_id, true);
        if (!$booking) {
            return;
        }

        $new_room_type_id = $upgrade['room_type_id'];

        $reserved_rooms = $booking->getReservedRooms();
        if (!empty($reserved_rooms)) {
            $room = reset($reserved_rooms);
            update_post_meta($room->getId(), 'mphb_room_type_id', $new_room_type_id);
        }

        update_post_meta($booking_id, '_roomcloud_upgraded', true);
        update_post_meta($booking_id, '_roomcloud_original_room', $original_slug);
        update_post_meta($booking_id, '_roomcloud_upgrade_comped', true);

        $original_room = get_page_by_path($original_slug, OBJECT, 'mphb_room_type');
        $new_room = get_post($new_room_type_id);

        $original_name = $original_room ? $original_room->post_title : $original_slug;
        $new_name = $new_room ? $new_room->post_title : 'upgraded room';

        $customer = $booking->getCustomer();
        $to = $customer->getEmail();
        $subject = 'Great News - Complimentary Room Upgrade!';

        $message = sprintf(
            "Dear %s,\n\n" .
            "We have some wonderful news! Due to high demand for the %s you originally booked, " .
            "we've upgraded you to our %s at no additional cost.\n\n" .
            "Your booking details:\n" .
            "- Check-in: %s\n" .
            "- Check-out: %s\n" .
            "- Room: %s (upgraded!)\n\n" .
            "We look forward to welcoming you!\n\n" .
            "Best regards,\n" .
            "%s",
            $customer->getFirstName(),
            $original_name,
            $new_name,
            $booking->getCheckInDate()->format('F j, Y'),
            $booking->getCheckOutDate()->format('F j, Y'),
            $new_name,
            shaped_brand('company.name', 'The Hotel Team')
        );

        wp_mail($to, $subject, $message);

        self::sync_booking($booking_id, null, [
            'force' => true,
            'source' => 'auto_upgrade',
        ]);

        Shaped_RC_Error_Logger::log_info('Booking auto-upgraded', [
            'booking_id' => $booking_id,
            'original_room' => $original_slug,
            'upgraded_to' => $new_name,
            'comped' => true,
        ]);
    }

    /**
     * Cancel booking that was rejected and has no upgrade path.
     */
    private function cancel_rejected_booking($booking_id)
    {
        $booking = MPHB()->getBookingRepository()->findById($booking_id, true);
        if (!$booking) {
            return;
        }

        wp_update_post([
            'ID' => $booking_id,
            'post_status' => 'cancelled',
        ]);

        update_post_meta($booking_id, '_shaped_payment_status', 'refund_required');
        update_post_meta($booking_id, '_roomcloud_rejection_reason', 'no_availability');

        $customer = $booking->getCustomer();
        $to = $customer->getEmail();
        $subject = 'Booking Unavailable - Our Apologies';

        $message = sprintf(
            "Dear %s,\n\n" .
            "We sincerely apologize, but due to a last-second booking conflict, " .
            "we are unable to confirm your reservation for %s to %s.\n\n" .
            "Your card has not been charged, and if any holds were placed, they will be released within 24 hours.\n\n" .
            "We would love to accommodate you on alternative dates or offer you a discount for a future stay. " .
            "Please reply to this email and we'll make it right.\n\n" .
            "Again, our sincere apologies for this inconvenience.\n\n" .
            "Best regards,\n" .
            "%s",
            $customer->getFirstName(),
            $booking->getCheckInDate()->format('F j, Y'),
            $booking->getCheckOutDate()->format('F j, Y'),
            shaped_brand('company.name', 'The Hotel Team')
        );

        wp_mail($to, $subject, $message);

        Shaped_RC_Error_Logger::log_critical('Booking rejected and cancelled', [
            'booking_id' => $booking_id,
            'reason' => 'No availability, no upgrades available',
        ]);
    }

    /**
     * Schedule retry cron.
     */
    public function schedule_retry_cron()
    {
        if (!wp_next_scheduled('shaped_rc_retry_failed_syncs')) {
            wp_schedule_event(time(), 'hourly', 'shaped_rc_retry_failed_syncs');
        }
    }

    /**
     * Process retry queue.
     */
    public function process_retry_queue()
    {
        $pending = Shaped_RC_Error_Logger::get_pending_retries();

        if (empty($pending)) {
            return;
        }

        Shaped_RC_Error_Logger::log_info('Processing retry queue', [
            'count' => count($pending),
        ]);

        foreach ($pending as $item) {
            $booking_id = (int) $item->booking_id;
            $operation = (string) $item->operation;
            $payload = maybe_unserialize($item->payload);

            $booking = MPHB()->getBookingRepository()->findById($booking_id, true);
            if (!$booking) {
                Shaped_RC_Error_Logger::remove_from_queue($item->id);
                continue;
            }

            Shaped_RC_Error_Logger::log_info('Retrying operation', [
                'queue_id' => $item->id,
                'booking_id' => $booking_id,
                'operation' => $operation,
                'attempt' => $item->attempts + 1,
            ]);

            $success = false;

            if ($operation === self::RETRY_OPERATION) {
                $state = self::normalize_retry_payload($booking_id, $payload);
                $result = self::sync_booking($booking_id, $state, [
                    'force' => false,
                    'handle_rejection' => empty($state['is_cancellation']),
                    'source' => 'retry',
                ]);
                $success = !empty($result['success']);
            }

            if ($success) {
                Shaped_RC_Error_Logger::remove_from_queue($item->id);
                Shaped_RC_Error_Logger::log_info('Retry successful', [
                    'queue_id' => $item->id,
                    'booking_id' => $booking_id,
                ]);
                continue;
            }

            if ($item->attempts >= 4) {
                Shaped_RC_Error_Logger::mark_retry_failed(
                    $item->id,
                    'Max retry attempts reached'
                );
            }
        }
    }

    /**
     * Cleanup sync flag after 5 minutes.
     */
    public function cleanup_sync_flag($booking_id)
    {
        delete_post_meta($booking_id, '_roomcloud_source');

        Shaped_RC_Error_Logger::log_info('Cleaned up sync flag', [
            'booking_id' => $booking_id,
        ]);
    }

    /**
     * Manual sync trigger (for admin use).
     */
    public static function manual_sync_booking($booking_id)
    {
        $booking_id = (int) $booking_id;
        $booking = MPHB()->getBookingRepository()->findById($booking_id, true);

        if (!$booking) {
            return [
                'success' => false,
                'error' => 'Booking not found',
            ];
        }

        $result = self::sync_booking($booking_id, null, [
            'force' => true,
            'handle_rejection' => true,
            'source' => 'manual',
        ]);

        if (!empty($result['success'])) {
            return [
                'success' => true,
                'message' => !empty($result['skipped'])
                    ? 'Booking already matches RoomCloud state'
                    : 'Booking synced successfully',
            ];
        }

        return [
            'success' => false,
            'error' => $result['error'] ?? 'Failed to sync booking',
        ];
    }

    /**
     * Determine whether a booking originated from RoomCloud.
     */
    public static function is_roomcloud_source(int $booking_id): bool
    {
        return (bool) get_post_meta($booking_id, '_roomcloud_source', true)
            || get_post_meta($booking_id, '_roomcloud_booking_id', true) !== ''
            || get_post_meta($booking_id, '_roomcloud_received_at', true) !== '';
    }

    /**
     * Determine whether a booking has already been sent to RoomCloud.
     */
    private static function has_synced_to_roomcloud(int $booking_id): bool
    {
        return (bool) get_post_meta($booking_id, '_roomcloud_synced', true)
            || get_post_meta($booking_id, '_roomcloud_status', true) !== ''
            || get_post_meta($booking_id, '_roomcloud_last_sync', true) !== '';
    }

    /**
     * Infer the prepaid amount for a paid booking.
     */
    private static function get_paid_amount(int $booking_id, string $payment_mode = ''): float
    {
        if ($payment_mode === 'deposit') {
            return self::get_deposit_prepaid_amount($booking_id);
        }

        $paid_amount = (float) get_post_meta($booking_id, '_mphb_paid_amount', true);
        if ($paid_amount > 0) {
            return $paid_amount;
        }

        $deposit_amount = (float) get_post_meta($booking_id, '_shaped_deposit_amount', true);
        if ($deposit_amount > 0) {
            return $deposit_amount;
        }

        return (float) get_post_meta($booking_id, '_shaped_payment_amount', true);
    }

    /**
     * Infer the prepaid amount for a deposit booking.
     */
    private static function get_deposit_prepaid_amount(int $booking_id): float
    {
        $deposit_amount = (float) get_post_meta($booking_id, '_shaped_deposit_amount', true);
        if ($deposit_amount > 0) {
            return $deposit_amount;
        }

        return (float) get_post_meta($booking_id, '_mphb_paid_amount', true);
    }

    /**
     * Build the payment type code for RoomCloud.
     */
    private static function get_payment_type_code(string $status): string
    {
        return $status === 'CANCELLED' ? '4' : '4';
    }

    /**
     * Hash the payload-driving state so duplicate pushes can be skipped safely.
     */
    private static function build_payload_hash(array $state): string
    {
        $hashable = [
            'booking_id' => (int) ($state['booking_id'] ?? 0),
            'status' => (string) ($state['status'] ?? ''),
            'prepaid' => number_format((float) ($state['prepaid'] ?? 0), 2, '.', ''),
            'paymentType' => (string) ($state['paymentType'] ?? ''),
        ];

        return sha1(wp_json_encode($hashable));
    }

    /**
     * Normalise retry payloads. Legacy queue items are rebuilt from current booking state.
     */
    private static function normalize_retry_payload(int $booking_id, $payload): array
    {
        if (is_array($payload)) {
            $is_complete = isset($payload['status'], $payload['prepaid'], $payload['paymentType'], $payload['payload_hash']);

            if ($is_complete) {
                $payload['booking_id'] = $booking_id;
                $payload['should_send'] = true;
                $payload['prepaid'] = (float) $payload['prepaid'];
                $payload['is_cancellation'] = (($payload['status'] ?? '') === 'CANCELLED');
                return $payload;
            }

            if (($payload['status'] ?? '') === 'CANCELLED') {
                return self::resolve_sync_state($booking_id, ['status' => 'CANCELLED']);
            }
        }

        return self::resolve_sync_state($booking_id);
    }

    /**
     * Determine if a booking should be included in the repair sweep.
     */
    private static function is_candidate_for_repair(int $booking_id): bool
    {
        if ($booking_id <= 0 || self::is_roomcloud_source($booking_id)) {
            return false;
        }

        $payment_status = (string) get_post_meta($booking_id, '_shaped_payment_status', true);
        $post_status = (string) get_post_status($booking_id);

        return self::has_synced_to_roomcloud($booking_id)
            || Shaped_RC_Error_Logger::has_pending_retry($booking_id, self::RETRY_OPERATION)
            || in_array($payment_status, ['authorized', 'completed', 'deposit_paid', 'cancelled', 'abandoned', 'refund_required'], true)
            || in_array($post_status, ['confirmed', 'cancelled', 'abandoned', 'trash'], true);
    }

    /**
     * Detect the RoomCloud test endpoint so rejection handling can stay disabled there.
     */
    private static function is_test_endpoint(): bool
    {
        $service_url = defined('SHAPED_RC_SERVICE_URL') ? SHAPED_RC_SERVICE_URL : '';

        return $service_url === 'https://apitest.roomcloud.net/be/ota/testOtaApi.jsp';
    }
}
