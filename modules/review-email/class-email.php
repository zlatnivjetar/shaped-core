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

            // Get customer email - try MPHB first, fall back to post meta
            $customer_email = '';
            $customer_first = '';
            $customer_last = '';
            $check_in = '';
            $check_out = '';
            $room_list = '';

            // Try MPHB booking object first
            if (function_exists('MPHB') && class_exists('MPHB\Entities\Booking')) {
                $booking = \MPHB()->getBookingRepository()->findById($booking_id, true);

                if ($booking) {
                    $customer = $booking->getCustomer();
                    if ($customer && $customer->getEmail()) {
                        $customer_email = $customer->getEmail();
                        $customer_first = $customer->getFirstName();
                        $customer_last = $customer->getLastName();
                    }

                    if ($booking->getCheckInDate()) {
                        $check_in = $booking->getCheckInDate()->format('d.m.Y');
                    }
                    if ($booking->getCheckOutDate()) {
                        $check_out = $booking->getCheckOutDate()->format('d.m.Y');
                    }

                    // Check if booking was cancelled
                    if ($booking->getStatus() === 'cancelled') {
                        error_log('[Shaped Review Email] Booking cancelled, skipping review email for #' . $booking_id);
                        return false;
                    }

                    // Get room details
                    $room_type_ids = $booking->getReservedRoomTypeIds();
                    if (!empty($room_type_ids)) {
                        $room_names = array_map('get_the_title', $room_type_ids);
                        $room_list = implode(', ', $room_names);
                    }
                }
            }

            // Fall back to post meta if MPHB data not available
            if (empty($customer_email)) {
                $customer_email = get_post_meta($booking_id, '_mphb_email', true);
            }
            if (empty($customer_first)) {
                $customer_first = get_post_meta($booking_id, '_mphb_first_name', true);
            }
            if (empty($customer_last)) {
                $customer_last = get_post_meta($booking_id, '_mphb_last_name', true);
            }
            if (empty($check_in)) {
                $check_in_raw = get_post_meta($booking_id, '_mphb_check_in_date', true);
                $check_in = $check_in_raw ? date('d.m.Y', strtotime($check_in_raw)) : '';
            }
            if (empty($check_out)) {
                $check_out_raw = get_post_meta($booking_id, '_mphb_check_out_date', true);
                $check_out = $check_out_raw ? date('d.m.Y', strtotime($check_out_raw)) : '';
            }

            // Validate we have required data
            if (empty($customer_email)) {
                error_log('[Shaped Review Email] No customer email for booking #' . $booking_id);
                return false;
            }

            if (empty($customer_first)) {
                $customer_first = 'Guest';
            }

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
                'check_in'       => $check_in ?: 'N/A',
                'check_out'      => $check_out ?: 'N/A',
                'room_list'      => $room_list ?: 'Your accommodation',
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
            'header'      => 'How Was Your Stay?',
            'subtitle'    => 'We\'d love your feedback',
            'content'     => $content,
            'footer_text' => 'You received this email because you recently stayed with us.',
        ]);
    }
}
