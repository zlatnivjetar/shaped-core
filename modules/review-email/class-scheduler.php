<?php
/**
 * Review Email Scheduler
 *
 * Schedules and sends review request emails 4 hours after checkout.
 *
 * @package Shaped_Core
 * @subpackage ReviewEmail
 */

namespace Shaped\Modules\ReviewEmail;

if (!defined('ABSPATH')) {
    exit;
}

class Scheduler {

    /**
     * Hours after checkout to send review email
     */
    const HOURS_AFTER_CHECKOUT = 6;

    /**
     * Constructor
     */
    public function __construct() {
        // Schedule the hourly check
        add_action('init', [$this, 'schedule_hourly_check']);

        // Hook for processing review emails
        add_action('shaped_process_review_emails', [$this, 'process_review_emails']);

        // Schedule review email when booking is confirmed
        add_action('transition_post_status', [$this, 'schedule_on_confirmation'], 20, 3);
    }

    /**
     * Schedule hourly check for review emails
     */
    public function schedule_hourly_check(): void {
        if (!wp_next_scheduled('shaped_process_review_emails')) {
            wp_schedule_event(time(), 'every_hour', 'shaped_process_review_emails');
        }
    }

    /**
     * Schedule review email when booking is confirmed
     *
     * @param string   $new_status New post status
     * @param string   $old_status Old post status
     * @param \WP_Post $post       Post object
     */
    public function schedule_on_confirmation($new_status, $old_status, $post): void {
        if ($post->post_type !== 'mphb_booking') {
            return;
        }

        // When booking becomes confirmed
        if ($new_status === 'confirmed' && $old_status !== 'confirmed') {
            $booking_id = $post->ID;

            // Check if already scheduled
            if (get_post_meta($booking_id, '_shaped_review_email_scheduled', true)) {
                return;
            }

            // Get booking details
            $booking = \MPHB()->getBookingRepository()->findById($booking_id, true);
            if (!$booking) {
                return;
            }

            // Calculate send time: checkout date + 4 hours
            $checkout_date = $booking->getCheckOutDate();
            if (!$checkout_date) {
                return;
            }

            // Set checkout time to 11:00 (standard checkout time) + 4 hours = 15:00
            $checkout_time = shaped_email_config('check_out_time', 'until 11:00');
            preg_match('/\d{1,2}/', $checkout_time, $matches);
            $checkout_hour = isset($matches[0]) ? (int)$matches[0] : 11;

            $send_datetime = clone $checkout_date;
            $send_datetime->setTime($checkout_hour + self::HOURS_AFTER_CHECKOUT, 0, 0);

            // Store the scheduled send time
            update_post_meta($booking_id, '_shaped_review_email_scheduled', $send_datetime->format('Y-m-d H:i:s'));
            update_post_meta($booking_id, '_shaped_review_email_status', 'scheduled');

            error_log('[Shaped Review Email] Scheduled review email for booking #' . $booking_id . ' at ' . $send_datetime->format('Y-m-d H:i:s'));
        }
    }

    /**
     * Process scheduled review emails
     *
     * Runs hourly to check for emails that need to be sent
     */
    public function process_review_emails(): void {
        $now = current_time('mysql');

        // Find bookings with scheduled review emails that are due
        $args = [
            'post_type'      => 'mphb_booking',
            'post_status'    => 'confirmed',
            'posts_per_page' => 50,
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => '_shaped_review_email_scheduled',
                    'value'   => $now,
                    'compare' => '<=',
                    'type'    => 'DATETIME',
                ],
                [
                    'key'     => '_shaped_review_email_status',
                    'value'   => 'scheduled',
                ],
            ],
        ];

        $bookings = get_posts($args);

        foreach ($bookings as $booking_post) {
            $this->send_review_email($booking_post->ID);
        }
    }

    /**
     * Send review email for a booking
     *
     * @param int $booking_id Booking ID
     * @return bool
     */
    public function send_review_email(int $booking_id): bool {
        // Check if already sent
        $status = get_post_meta($booking_id, '_shaped_review_email_status', true);
        if ($status === 'sent') {
            return false;
        }

        // Send the email
        $sent = Email::send($booking_id);

        if ($sent) {
            update_post_meta($booking_id, '_shaped_review_email_status', 'sent');
            update_post_meta($booking_id, '_shaped_review_email_sent', current_time('mysql'));
            error_log('[Shaped Review Email] Sent review email for booking #' . $booking_id);
        } else {
            update_post_meta($booking_id, '_shaped_review_email_status', 'failed');
            error_log('[Shaped Review Email] Failed to send review email for booking #' . $booking_id);
        }

        return $sent;
    }

    /**
     * Generate secure token for review link
     *
     * @param int $booking_id Booking ID
     * @return string
     */
    public static function generate_token(int $booking_id): string {
        $booking = \MPHB()->getBookingRepository()->findById($booking_id, true);
        $email = null;

        // Try MPHB customer object first
        if ($booking && $booking->getCustomer()) {
            $email = $booking->getCustomer()->getEmail();
        }

        // Fallback to meta value
        if (!$email) {
            $email = get_post_meta($booking_id, '_mphb_email', true);
        }

        if (!$email) {
            return '';
        }

        return hash('sha256', $booking_id . $email . wp_salt('auth'));
    }

    /**
     * Verify review token
     *
     * @param int    $booking_id Booking ID
     * @param string $token      Token to verify
     * @return bool
     */
    public static function verify_token(int $booking_id, string $token): bool {
        $expected = self::generate_token($booking_id);
        return hash_equals($expected, $token);
    }

    /**
     * Get review URL for a booking
     *
     * @param int $booking_id Booking ID
     * @return string
     */
    public static function get_review_url(int $booking_id): string {
        $token = self::generate_token($booking_id);
        $review_page = get_option('shaped_review_page_url', home_url('/leave-review/'));

        return add_query_arg([
            'booking_id' => $booking_id,
            'token'      => $token,
        ], $review_page);
    }
}
