<?php
/**
 * Review Email Sender
 *
 * Sends the review request email to guests.
 *
 * @package Shaped_Core
 * @subpackage ReviewEmail
 */

namespace Shaped\Modules\ReviewEmail;

if (!defined('ABSPATH')) {
    exit;
}

class Email {

    /**
     * Send review request email
     *
     * @param int $booking_id Booking ID
     * @return bool
     */
    public static function send(int $booking_id): bool {
        try {
            error_log('[Shaped Review Email] Starting review email for booking #' . $booking_id);

            // Verify MotoPress is available
            if (!class_exists('MPHB\Entities\Booking')) {
                error_log('[Shaped Review Email] MotoPress not available');
                return false;
            }

            // Get booking object (may be null for test bookings created via wp_insert_post)
            $booking = \MPHB()->getBookingRepository()->findById($booking_id, true);

            // Get customer details - with fallback to meta
            $customer = $booking ? $booking->getCustomer() : null;
            $customer_email = $customer ? $customer->getEmail() : null;
            $customer_first = $customer ? $customer->getFirstName() : null;

            // Fallback to meta values if MPHB customer not hydrated
            if (!$customer_email) {
                $customer_email = get_post_meta($booking_id, '_mphb_email', true);
            }
            if (!$customer_first) {
                $customer_first = get_post_meta($booking_id, '_mphb_first_name', true) ?: 'Guest';
            }

            if (!$customer_email) {
                error_log('[Shaped Review Email] No customer email for booking #' . $booking_id);
                return false;
            }

            // Check if booking was cancelled (check both MPHB status and WP post status)
            $booking_status = $booking ? $booking->getStatus() : get_post_status($booking_id);
            if ($booking_status === 'cancelled') {
                error_log('[Shaped Review Email] Booking cancelled, skipping review email for #' . $booking_id);
                return false;
            }

            // Get booking details - fallback to meta if MPHB object doesn't have dates
            $check_in_date = $booking ? $booking->getCheckInDate() : null;
            $check_out_date = $booking ? $booking->getCheckOutDate() : null;

            if (!$check_in_date) {
                $check_in_meta = get_post_meta($booking_id, '_mphb_check_in_date', true);
                $check_in_date = $check_in_meta ? \DateTime::createFromFormat('Y-m-d', $check_in_meta) : null;
            }
            if (!$check_out_date) {
                $check_out_meta = get_post_meta($booking_id, '_mphb_check_out_date', true);
                $check_out_date = $check_out_meta ? \DateTime::createFromFormat('Y-m-d', $check_out_meta) : null;
            }

            if (!$check_in_date || !$check_out_date) {
                error_log('[Shaped Review Email] Missing check-in/out dates for booking #' . $booking_id);
                return false;
            }

            $check_in = $check_in_date->format('d.m.Y');
            $check_out = $check_out_date->format('d.m.Y');

            // Get room details
            $room_type_ids = $booking ? $booking->getReservedRoomTypeIds() : [];
            $room_names = array_map('get_the_title', $room_type_ids);
            $room_list = implode(', ', $room_names) ?: 'Accommodation';

            // Get email config
            $from_name = shaped_email_config('from_name', get_bloginfo('name'));
            $from_email = shaped_email_config('from_email', get_option('admin_email'));

            // Email setup
            $to = $customer_email;
            $subject = 'How was your stay? - ' . $from_name;

            // Get review URL
            $review_url = Scheduler::get_review_url($booking_id);

            // Build email
            $message = self::get_template([
                'booking_id'     => $booking_id,
                'customer_first' => $customer_first,
                'check_in'       => $check_in,
                'check_out'      => $check_out,
                'room_list'      => $room_list,
                'review_url'     => $review_url,
            ]);

            // Set headers
            $headers = [
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . $from_name . ' <' . $from_email . '>',
                'Reply-To: ' . $from_email
            ];

            // Send email
            $sent = wp_mail($to, $subject, $message, $headers);

            if ($sent) {
                error_log('[Shaped Review Email] Successfully sent to ' . $to);
            } else {
                error_log('[Shaped Review Email] FAILED to send to ' . $to);
            }

            return $sent;

        } catch (\Exception $e) {
            error_log('[Shaped Review Email] ERROR: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get email template
     *
     * @param array $data Email data
     * @return string
     */
    private static function get_template(array $data): string {
        $content = '';
        $company_name = shaped_email_config('company_name', 'our property');
        $company_location = shaped_email_config('company_location', '');
        $signature = shaped_email_config('signature', 'The Team');

        // Greeting
        $content .= shaped_email_block_greeting($data['customer_first']);

        // Intro
        $intro_text = "Thank you for choosing " . $company_name . " for your recent stay";
        if ($company_location) {
            $intro_text .= " in " . $company_location;
        }
        $intro_text .= ". We hope you had a wonderful experience!";
        $content .= shaped_email_block_intro($intro_text);

        // Stay summary card
        $content .= shaped_email_block_card_start('neutral');
        $content .= shaped_email_block_section_title('Your Stay', '');
        $content .= shaped_email_block_rows_start();
        $content .= shaped_email_block_row('Check-in:', $data['check_in'], ['bold_value' => true]);
        $content .= shaped_email_block_row('Check-out:', $data['check_out'], ['bold_value' => true]);
        $content .= shaped_email_block_row('Accommodation:', $data['room_list'], ['bold_value' => true]);
        $content .= shaped_email_block_rows_end();
        $content .= shaped_email_block_card_end();

        // Request text
        $text_muted = shaped_email_color('textMuted', '#666666');
        $content .= '<p style="margin: 0 0 24px 0; font-size: 16px; color: ' . $text_muted . '; line-height: 1.6; text-align: center;">';
        $content .= 'Your feedback helps us improve and helps other travelers make informed decisions. We\'d love to hear about your experience!';
        $content .= '</p>';

        // CTA Button
        $content .= shaped_email_block_button(
            'Leave a Review',
            $data['review_url'],
            'It only takes a minute'
        );

        // Closing
        $content .= shaped_email_block_closing(
            'Thank you for being our guest!',
            $signature,
            'highlight'
        );

        // Render full email
        return shaped_render_email([
            'title'       => 'Share Your Experience - ' . $company_name,
            'header'      => 'How was your stay?',
            'subtitle'    => 'We\'d love your feedback',
            'content'     => $content,
            'footer_text' => 'You received this email because you recently stayed with us.',
        ]);
    }
}
