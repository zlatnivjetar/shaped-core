<?php
/**
 * Sync Manager
 * Orchestrates bidirectional sync between MotoPress and RoomCloud
 * Updated: Removed inventory pushing - RoomCloud is source of truth
 */

if (!defined('ABSPATH')) {
    exit;
}

class Shaped_RC_Sync_Manager
{
    private static $instance = null;
    
    // Room mapping: MotoPress slug => RoomCloud ID
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
        // Hook into Shaped Core payment events
        add_action('shaped_payment_completed', [$this, 'on_payment_completed'], 10, 2);
        add_action('shaped_booking_cancelled', [$this, 'on_booking_cancelled'], 10, 1);
        
        // Hook into MotoPress booking creation
        add_action('mphb_create_booking_by_user', [$this, 'on_booking_created'], 10, 1);
        
        // Retry failed syncs
        add_action('init', [$this, 'schedule_retry_cron']);
        add_action('shaped_rc_retry_failed_syncs', [$this, 'process_retry_queue']);
        
        // Cleanup old sync flags
        add_action('cleanup_roomcloud_flag', [$this, 'cleanup_sync_flag'], 10, 1);
    }
    
    /**
     * When a new booking is created on the website
     */
    public function on_booking_created($booking)
    {
        if (!is_object($booking)) {
            $booking = MPHB()->getBookingRepository()->findById($booking);
        }
        
        if (!$booking) {
            return;
        }
        
        $booking_id = $booking->getId();
        
        // Check if this booking came FROM RoomCloud (prevent loop)
        if (get_post_meta($booking_id, '_roomcloud_source', true)) {
            Shaped_RC_Error_Logger::log_info('Skipping sync - booking came from RoomCloud', [
                'booking_id' => $booking_id,
            ]);
            return;
        }
        
        Shaped_RC_Error_Logger::log_info('Sending reservation to RoomCloud (SUBMITTED, no revenue)', [
            'booking_id' => $booking_id,
            'amount' => 0,
        ]);
        
        // Send reservation with SUBMITTED status and €0
        // RoomCloud will respond with modify containing updated availability
        $result = Shaped_RC_API::send_reservation($booking_id, 'SUBMITTED', 0);
        
        // Handle rejection (no availability)
        if ($result === false && get_option('shaped_rc_service_url') !== 'https://apitest.roomcloud.net/be/ota/testOtaApi.jsp') {
            $this->handle_booking_rejection($booking_id);
        }
    }
    
    /**
     * When payment is completed (immediate or delayed)
     */
    public function on_payment_completed($booking_id, $mode)
    {
        // Check if this booking came FROM RoomCloud (prevent loop)
        if (get_post_meta($booking_id, '_roomcloud_source', true)) {
            Shaped_RC_Error_Logger::log_info('Skipping payment sync - booking came from RoomCloud', [
                'booking_id' => $booking_id,
            ]);
            return;
        }
        
        // Update reservation status to CONFIRMED
        $amount = get_post_meta($booking_id, '_shaped_payment_amount', true);
        
        Shaped_RC_Error_Logger::log_info('Sending revenue to RoomCloud (CONFIRMED)', [
            'booking_id' => $booking_id,
            'mode' => $mode,
            'amount' => $amount,
        ]);
        
        // Send confirmed reservation with amount
        // RoomCloud will respond with modify containing updated availability
        Shaped_RC_API::send_reservation($booking_id, 'CONFIRMED', $amount);
    }
    
    /**
     * When a booking is cancelled
     */
    public function on_booking_cancelled($booking_id)
    {
        // Check if this booking came FROM RoomCloud (prevent loop)
        if (get_post_meta($booking_id, '_roomcloud_source', true)) {
            Shaped_RC_Error_Logger::log_info('Skipping cancellation sync - booking came from RoomCloud', [
                'booking_id' => $booking_id,
            ]);
            return;
        }
        
        Shaped_RC_Error_Logger::log_info('Booking cancelled - updating RoomCloud', [
            'booking_id' => $booking_id,
        ]);
        
        // Send cancellation to RoomCloud
        // RoomCloud will send back modify with updated availability
        Shaped_RC_API::send_reservation($booking_id, 'CANCELLED');
    }
    
    /**
     * Handle booking rejection from RoomCloud
     * Attempts auto-upgrade, or cancels with refund
     */
    private function handle_booking_rejection($booking_id)
    {
        $booking = MPHB()->getBookingRepository()->findById($booking_id);
        if (!$booking) {
            return;
        }
        
        // Get booking details
        $reserved_rooms = $booking->getReservedRooms();
        if (empty($reserved_rooms)) {
            return;
        }
        
        $room = reset($reserved_rooms);
        $room_type_id = $room->getRoomTypeId();
        
        // Fix: MotoPress API sometimes returns 0
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
        
        // Try to find upgrade
        $upgrades = Shaped_RC_Availability_Manager::find_upgrade_options(
            $room_slug,
            $check_in,
            $check_out
        );
        
        if (!empty($upgrades)) {
            // Auto-upgrade to first (cheapest) available upgrade
            $upgrade = reset($upgrades);
            $this->process_auto_upgrade($booking_id, $room_slug, $upgrade);
        } else {
            // No upgrades available - cancel booking
            $this->cancel_rejected_booking($booking_id);
        }
    }
    
    /**
     * Process automatic upgrade
     */
    private function process_auto_upgrade($booking_id, $original_slug, $upgrade)
    {
        $booking = MPHB()->getBookingRepository()->findById($booking_id);
        if (!$booking) {
            return;
        }
        
        $new_room_type_id = $upgrade['room_type_id'];
        
        // Update reserved room to new type
        $reserved_rooms = $booking->getReservedRooms();
        if (!empty($reserved_rooms)) {
            $room = reset($reserved_rooms);
            update_post_meta($room->getId(), 'mphb_room_type_id', $new_room_type_id);
        }
        
        // Mark as upgraded
        update_post_meta($booking_id, '_roomcloud_upgraded', true);
        update_post_meta($booking_id, '_roomcloud_original_room', $original_slug);
        update_post_meta($booking_id, '_roomcloud_upgrade_comped', true);
        
        // Get room names
        $original_room = get_page_by_path($original_slug, OBJECT, 'mphb_room_type');
        $new_room = get_post($new_room_type_id);
        
        $original_name = $original_room ? $original_room->post_title : $original_slug;
        $new_name = $new_room ? $new_room->post_title : 'upgraded room';
        
        // Send upgrade notification
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
            "Preelook Apartments",
            $customer->getFirstName(),
            $original_name,
            $new_name,
            $booking->getCheckInDate()->format('F j, Y'),
            $booking->getCheckOutDate()->format('F j, Y'),
            $new_name
        );
        
        wp_mail($to, $subject, $message);
        
        // Try sending to RoomCloud again with upgraded room
        Shaped_RC_API::send_reservation($booking_id, 'SUBMITTED', 0);
        
        Shaped_RC_Error_Logger::log_info('Booking auto-upgraded', [
            'booking_id' => $booking_id,
            'original_room' => $original_slug,
            'upgraded_to' => $new_name,
            'comped' => true,
        ]);
    }
    
    /**
     * Cancel booking that was rejected (no upgrades available)
     */
    private function cancel_rejected_booking($booking_id)
    {
        $booking = MPHB()->getBookingRepository()->findById($booking_id);
        if (!$booking) {
            return;
        }
        
        // Cancel booking
        wp_update_post([
            'ID' => $booking_id,
            'post_status' => 'cancelled',
        ]);
        
        // Mark payment for refund
        update_post_meta($booking_id, '_shaped_payment_status', 'refund_required');
        update_post_meta($booking_id, '_roomcloud_rejection_reason', 'no_availability');
        
        // Send apology email
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
            "Preelook Apartments",
            $customer->getFirstName(),
            $booking->getCheckInDate()->format('F j, Y'),
            $booking->getCheckOutDate()->format('F j, Y')
        );
        
        wp_mail($to, $subject, $message);
        
        Shaped_RC_Error_Logger::log_critical('Booking rejected and cancelled', [
            'booking_id' => $booking_id,
            'reason' => 'No availability, no upgrades available',
        ]);
    }
    
    /**
     * Schedule retry cron
     */
    public function schedule_retry_cron()
    {
        if (!wp_next_scheduled('shaped_rc_retry_failed_syncs')) {
            wp_schedule_event(time(), 'hourly', 'shaped_rc_retry_failed_syncs');
        }
    }
    
    /**
     * Process retry queue
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
            $booking_id = $item->booking_id;
            $operation = $item->operation;
            $payload = maybe_unserialize($item->payload);
            
            // CHECK IF BOOKING EXISTS - if not, remove from queue silently
            $booking = MPHB()->getBookingRepository()->findById($booking_id);
            if (!$booking) {
                Shaped_RC_Error_Logger::remove_from_queue($item->id);
                continue; // Skip to next without logging
            }
            
            Shaped_RC_Error_Logger::log_info('Retrying operation', [
                'queue_id' => $item->id,
                'booking_id' => $booking_id,
                'operation' => $operation,
                'attempt' => $item->attempts + 1,
            ]);
            
            $success = false;
            
            // Retry based on operation type
            if ($operation === 'send_reservation') {
                $status = isset($payload['status']) ? $payload['status'] : 'SUBMITTED';
                $success = Shaped_RC_API::send_reservation($booking_id, $status);
            }
            
            if ($success) {
                // Remove from queue
                Shaped_RC_Error_Logger::remove_from_queue($item->id);
                Shaped_RC_Error_Logger::log_info('Retry successful', [
                    'queue_id' => $item->id,
                    'booking_id' => $booking_id,
                ]);
            } else {
                // Check if max attempts reached
                if ($item->attempts >= 4) {
                    Shaped_RC_Error_Logger::mark_retry_failed(
                        $item->id,
                        'Max retry attempts reached'
                    );
                }
            }
        }
    }
    
    /**
     * Cleanup sync flag after 5 minutes
     */
    public function cleanup_sync_flag($booking_id)
    {
        delete_post_meta($booking_id, '_roomcloud_source');
        
        Shaped_RC_Error_Logger::log_info('Cleaned up sync flag', [
            'booking_id' => $booking_id,
        ]);
    }
    
    /**
     * Manual sync trigger (for admin use)
     */
    public static function manual_sync_booking($booking_id)
    {
        $booking = MPHB()->getBookingRepository()->findById($booking_id);
        if (!$booking) {
            return [
                'success' => false,
                'error' => 'Booking not found',
            ];
        }
        
        // Determine status based on payment
        $payment_status = get_post_meta($booking_id, '_shaped_payment_status', true);
        $status = ($payment_status === 'completed') ? 'CONFIRMED' : 'SUBMITTED';
        
        // Send to RoomCloud
        $result = Shaped_RC_API::send_reservation($booking_id, $status);
        
        if ($result) {
            return [
                'success' => true,
                'message' => 'Booking synced successfully',
            ];
        }
        
        return [
            'success' => false,
            'error' => 'Failed to sync booking',
        ];
    }
}