<?php
/**
 * Shaped Email Handler - Production Ready Version
 * Handles confirmation, abandonment, and cancellation emails
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


function shaped_send_confirmation_email( $booking_id ) {
    try {
        error_log( '[Shaped Email] Starting confirmation email for booking #' . $booking_id );
        
        // Verify MotoPress is available
        if ( ! class_exists( 'MPHB\Entities\Booking' ) ) {
            error_log( '[Shaped Email] MotoPress not available' );
            return false;
        }
        
        // Get booking object
        $booking = MPHB()->getBookingRepository()->findById( $booking_id, true );
        if ( ! $booking ) {
            error_log( '[Shaped Email] Booking not found: #' . $booking_id );
            return false;
        }
        
        // Get customer details
        $customer = $booking->getCustomer();
        if ( ! $customer || ! $customer->getEmail() ) {
            error_log( '[Shaped Email] No customer email for booking #' . $booking_id );
            return false;
        }
        
        // Prevent duplicate sends
        $already_sent = get_post_meta( $booking_id, '_shaped_confirmation_sent', true );
        if ( $already_sent ) {
            error_log( '[Shaped Email] Confirmation already sent on ' . $already_sent );
            return false;
        }
        
        // Get booking details
        $check_in = $booking->getCheckInDate()->format( 'd.m.Y' );
        $check_out = $booking->getCheckOutDate()->format( 'd.m.Y' );
        $currency = MPHB()->settings()->currency()->getCurrencySymbol();
        
        // Get room details
        $room_type_ids = $booking->getReservedRoomTypeIds();
        $room_names = array_map( 'get_the_title', $room_type_ids );
        $room_list = implode( ', ', $room_names );
        
        // FIX: Use the same logic as admin email to get the actual paid amount
        $total_paid_raw = get_post_meta( $booking_id, '_mphb_paid_amount', true );
        $total_paid = (float) $total_paid_raw;
        
        // Fallback to booking total if no paid amount recorded
        if ( $total_paid <= 0 ) {
            $total_paid = (float) $booking->getTotalPrice();
        }
        
        // Get email config
        $from_name = shaped_email_config('from_name', get_bloginfo('name'));
        $from_email = shaped_email_config('from_email', get_option('admin_email'));

        // Email setup
        $to = $customer->getEmail();
        $subject = 'Booking Confirmed #' . $booking_id . ' - ' . $from_name;

        // Build professional HTML email
        $message = shaped_get_confirmation_template( array(
            'booking_id' => $booking_id,
            'customer_name' => $customer->getFirstName() . ' ' . $customer->getLastName(),
            'customer_first' => $customer->getFirstName(),
            'check_in' => $check_in,
            'check_out' => $check_out,
            'room_list' => $room_list,
            'total_paid' => $currency . number_format( $total_paid, 2 ),
            'customer_email' => $customer->getEmail(),
            'customer_phone' => $customer->getPhone()
        ));

        // Set headers
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
            'Reply-To: ' . $from_email
        );
        
        // Send email
        $sent = wp_mail( $to, $subject, $message, $headers );
        
        if ( $sent ) {
            // Mark as sent with timestamp
            update_post_meta( $booking_id, '_shaped_confirmation_sent', current_time( 'mysql' ) );
            error_log( '[Shaped Email] Confirmation successfully sent to ' . $to );
            
        } else {
            error_log( '[Shaped Email] FAILED to send confirmation to ' . $to );
        }
        
        return $sent;
        
    } catch ( Exception $e ) {
        error_log( '[Shaped Email] ERROR in confirmation: ' . $e->getMessage() );
        return false;
    }
}

function shaped_get_confirmation_template( $data ) {
    // Build content using reusable blocks
    $content = '';
    $company_name = shaped_email_config('company_name', 'our property');
    $company_location = shaped_email_config('company_location', '');

    // Greeting
    $content .= shaped_email_block_greeting($data['customer_first']);

    // Intro
    $intro_text = "Thank you for choosing " . $company_name . "!";
    if ($company_location) {
        $intro_text .= " We're excited to welcome you to the beautiful " . $company_location . ".";
    }
    $content .= shaped_email_block_intro($intro_text);

    // Booking Details
    $content .= shaped_email_render_booking_details([
        'booking_id' => $data['booking_id'],
        'check_in'   => $data['check_in'],
        'check_out'  => $data['check_out'],
        'room_list'  => $data['room_list'],
        'total_paid' => $data['total_paid'],
    ]);

    // Getting Here
    $content .= shaped_email_render_getting_here();

    // Contact
    $content .= shaped_email_render_contact();

    // Closing
    $content .= shaped_email_render_closing();

    // Render full email
    $tagline = shaped_email_config('company_tagline', 'Your stay awaits');
    return shaped_render_email([
        'title'       => 'Booking Confirmed - ' . $company_name,
        'header'      => 'Booking Confirmed!',
        'subtitle'    => $tagline,
        'content'     => $content,
        'footer_text' => '',
    ]);
}

function shaped_send_reservation_email($booking_id) {
    try {
        error_log('[Shaped Email] Starting reservation email for booking #' . $booking_id);
        
        $booking = MPHB()->getBookingRepository()->findById($booking_id, true);
        if (!$booking) return false;
        
        $customer = $booking->getCustomer();
        if (!$customer || !$customer->getEmail()) return false;
        
        // Prevent duplicate sends
        $already_sent = get_post_meta($booking_id, '_shaped_reservation_sent', true);
        if ($already_sent) {
            error_log('[Shaped Email] Reservation already sent on ' . $already_sent);
            return false;
        }
        
        // Get basic details
        $check_in = $booking->getCheckInDate()->format('d.m.Y');
        $check_out = $booking->getCheckOutDate()->format('d.m.Y');
        $charge_date = get_post_meta($booking_id, '_shaped_charge_date', true);
        $pending_amount = get_post_meta($booking_id, '_stripe_pending_amount', true);
        
        $charge_date_formatted = date('F j, Y', strtotime($charge_date));

        // Get email config
        $from_name = shaped_email_config('from_name', get_bloginfo('name'));
        $from_email = shaped_email_config('from_email', get_option('admin_email'));

        $to = $customer->getEmail();
        $subject = 'Reservation Confirmed #' . $booking_id . ' - ' . $from_name;

        $message = shaped_get_reservation_template([
            'booking_id' => $booking_id,
            'customer_first' => $customer->getFirstName(),
            'check_in' => $check_in,
            'check_out' => $check_out,
            'charge_date' => $charge_date_formatted,
            'amount' => number_format($pending_amount, 2),
            'customer_email' => $customer->getEmail()
        ]);

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
            'Reply-To: ' . $from_email
        ];
        
        $sent = wp_mail($to, $subject, $message, $headers);
        
        if ($sent) {
            update_post_meta($booking_id, '_shaped_reservation_sent', current_time('mysql'));
            error_log('[Shaped Email] Reservation email sent to ' . $to);
        }
        
        return $sent;
        
    } catch (Exception $e) {
        error_log('[Shaped Email] ERROR in reservation: ' . $e->getMessage());
        return false;
    }
}

function shaped_get_reservation_template($data) {
    // Build content using reusable blocks
    $content = '';
    $company_name = shaped_email_config('company_name', 'our property');
    $phone = shaped_email_config('phone', '');

    // Greeting
    $content .= shaped_email_block_greeting($data['customer_first']);

    // Intro
    $content .= shaped_email_block_intro(
        "Your reservation at " . $company_name . " has been confirmed! Your card has been securely saved."
    );

    // Booking Summary
    $content .= shaped_email_render_booking_summary([
        'booking_id' => $data['booking_id'],
        'check_in'   => $data['check_in'],
        'check_out'  => $data['check_out'],
    ]);

    // Payment Info
    $content .= shaped_email_block_payment_info(
        '€' . $data['amount'],
        $data['charge_date'],
        '(7 days before your arrival)'
    );

    // Manage Booking Button
    $manage_url = shaped_email_get_manage_url($data['booking_id'], $data['customer_email']);
    $content .= shaped_email_block_button(
        'Manage Booking',
        $manage_url,
        'Free cancellation until ' . $data['charge_date']
    );

    // Footer note
    $primary = shaped_email_color('primary', '#D1AF5D');
    $text_muted = shaped_email_color('textMuted', '#666666');
    $content .= '<p style="margin: 0; font-size: 14px; color: ' . $text_muted . '; text-align: center; line-height: 1.6;">';
    $content .= "You'll receive full booking details after payment is processed.<br>";
    if ($phone) {
        $phone_clean = preg_replace('/[^0-9+]/', '', $phone);
        $content .= 'Questions? Contact us at <a href="tel:' . $phone_clean . '" style="color: ' . $primary . ';">' . esc_html($phone) . '</a>';
    }
    $content .= '</p>';

    // Render full email
    return shaped_render_email([
        'title'       => 'Reservation Confirmed - ' . $company_name,
        'header'      => 'Reservation Confirmed!',
        'subtitle'    => 'Card saved successfully',
        'content'     => $content,
        'footer_text' => '',
    ]);
}

function shaped_send_cancellation_email($booking_id, $is_refundable = false) {
    $booking = MPHB()->getBookingRepository()->findById($booking_id);
    $customer = $booking->getCustomer();

    // Get email config
    $from_name = shaped_email_config('from_name', get_bloginfo('name'));
    $from_email = shaped_email_config('from_email', get_option('admin_email'));

    $to = $customer->getEmail();
    $subject = 'Booking Cancelled #' . $booking_id . ' - ' . $from_name;

    $message = shaped_get_cancellation_template([
        'booking_id' => $booking_id,
        'customer_first' => $customer->getFirstName(),
        'is_refundable' => $is_refundable
    ]);

    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $from_name . ' <' . $from_email . '>',
        'Reply-To: ' . $from_email
    ];

    $sent = wp_mail($to, $subject, $message, $headers);

    if ($sent) {
        update_post_meta($booking_id, '_shaped_cancellation_sent', current_time('mysql'));
        error_log('[Shaped] Cancellation email sent for booking #' . $booking_id);
    }

    return $sent;
}

function shaped_get_cancellation_template($data) {
    // Build content using reusable blocks
    $content = '';
    $company_name = shaped_email_config('company_name', 'our property');
    $signature = shaped_email_config('signature', 'The Team');

    // Greeting
    $content .= shaped_email_block_greeting($data['customer_first']);

    // Cancellation notice
    $content .= shaped_email_block_intro(
        'Your booking <strong>#' . esc_html($data['booking_id']) . '</strong> has been successfully cancelled.'
    );

    // Refund card (if applicable)
    $content .= shaped_email_render_cancellation_card($data['is_refundable']);

    // Closing message
    $content .= shaped_email_block_closing(
        'We hope to welcome you in the future.',
        $signature,
        'neutral'
    );

    // Render full email
    return shaped_render_email([
        'title'       => 'Booking Cancelled - ' . $company_name,
        'header'      => 'Booking Cancelled',
        'subtitle'    => "We've processed your cancellation",
        'content'     => $content,
        'footer_text' => 'This is an automated cancellation confirmation.',
    ]);
}

/**
 * Send payment failed email to guest (delayed charge failed)
 * Sent when scheduled charge attempt fails
 *
 * @param int $booking_id The booking ID
 * @return bool True if sent successfully
 */
function shaped_send_payment_failed_email($booking_id) {
    try {
        error_log('[Shaped Email] Starting payment failed notification for booking #' . $booking_id);

        $booking = MPHB()->getBookingRepository()->findById($booking_id, true);
        if (!$booking) return false;

        $customer = $booking->getCustomer();
        if (!$customer || !$customer->getEmail()) return false;

        // Get email config
        $from_name = shaped_email_config('from_name', get_bloginfo('name'));
        $from_email = shaped_email_config('from_email', get_option('admin_email'));
        $phone = shaped_email_config('phone', '');
        $contact_email = shaped_email_config('email', $from_email);

        $check_in = $booking->getCheckInDate()->format('d.m.Y');
        $currency = MPHB()->settings()->currency()->getCurrencySymbol();
        $pending_amount = get_post_meta($booking_id, '_stripe_pending_amount', true);

        $to = $customer->getEmail();
        $subject = 'Payment Failed - Action Required #' . $booking_id . ' - ' . $from_name;

        // Plain text message
        $message = "Dear " . $customer->getFirstName() . ",\n\n";
        $message .= "We attempted to charge your payment for booking #" . $booking_id . " but the payment was unsuccessful.\n\n";
        $message .= "BOOKING DETAILS:\n";
        $message .= "Booking ID: #" . $booking_id . "\n";
        $message .= "Amount Due: " . $currency . number_format($pending_amount, 2) . "\n";
        $message .= "Check-in: " . $check_in . "\n\n";
        $message .= "WHAT HAPPENS NEXT:\n";
        $message .= "Your booking is at risk of cancellation. Please contact us immediately to resolve this payment issue.\n\n";
        $message .= "Common reasons for payment failure:\n";
        $message .= "- Insufficient funds\n";
        $message .= "- Expired card\n";
        $message .= "- Card limit exceeded\n";
        $message .= "- Bank declined the transaction\n\n";
        $message .= "CONTACT US URGENTLY:\n";
        if ($phone) {
            $message .= "Phone: " . $phone . "\n";
        }
        $message .= "Email: " . $contact_email . "\n\n";
        $message .= "We're here to help resolve this quickly.\n\n";
        $message .= "Regards,\n";
        $message .= "The " . $from_name . " Team";

        $headers = [
            'From: ' . $from_name . ' <' . $from_email . '>',
            'Reply-To: ' . $from_email
        ];

        $sent = wp_mail($to, $subject, $message, $headers);

        if ($sent) {
            error_log('[Shaped Email] Payment failed notification sent to ' . $to);
        }

        return $sent;

    } catch (Exception $e) {
        error_log('[Shaped Email] ERROR in payment failed notification: ' . $e->getMessage());
        return false;
    }
}

add_action( 'transition_post_status', function( $new_status, $old_status, $post ) {
    // Only for bookings
    if ( $post->post_type !== 'mphb_booking' ) {
        return;
    }

    // When transitioning to confirmed
    if ( $new_status === 'confirmed' && $old_status !== 'confirmed' ) {
        // Check if payment is complete
        $paid_amount = get_post_meta( $post->ID, '_mphb_paid_amount', true );
        $payment_verified = get_post_meta( $post->ID, '_mphb_payment_verified', true );
        $payment_type = get_post_meta( $post->ID, '_shaped_payment_type', true );

        // Skip if deposit payment (deposit email is sent separately)
        if ( $payment_type === 'deposit' ) {
            return;
        }

        if ( ( $paid_amount > 0 || $payment_verified ) && ! get_post_meta( $post->ID, '_shaped_confirmation_sent', true ) ) {
            error_log( '[Shaped Email] Booking confirmed with payment, sending email' );
            shaped_send_confirmation_email( $post->ID );
        }
    }
}, 20, 3 );