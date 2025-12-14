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
        
        // Email setup
        $to = $customer->getEmail();
        $subject = 'Booking Confirmed #' . $booking_id . ' - Preelook Apartments';
        
        // Build professional HTML email
        $message = shaped_get_confirmation_template( array(
            'booking_id' => $booking_id,
            'customer_name' => $customer->getFirstName() . ' ' . $customer->getLastName(),
            'customer_first' => $customer->getFirstName(),
            'check_in' => $check_in,
            'check_out' => $check_out,
            'room_list' => $room_list,
            'total_paid' => $currency . number_format( $total_paid, 2 ), // Changed key name
            'customer_email' => $customer->getEmail(),
            'customer_phone' => $customer->getPhone()
        ));
        
        // Set headers
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Preelook Apartments <info@preelook.com>',
            'Reply-To: info@preelook.com'
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

    // Greeting
    $content .= shaped_email_block_greeting($data['customer_first']);

    // Intro
    $content .= shaped_email_block_intro(
        "Thank you for choosing Preelook Apartments! We're excited to welcome you to the beautiful seaside town of Rijeka, just steps from the Adriatic Sea."
    );

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

    // Explore the Area
    $content .= shaped_email_render_explore_area();

    // Contact
    $content .= shaped_email_render_contact();

    // Closing
    $content .= shaped_email_render_closing();

    // Render full email
    return shaped_render_email([
        'title'       => 'Booking Confirmed - Preelook Apartments',
        'header'      => 'Booking Confirmed!',
        'subtitle'    => 'Your seaside escape awaits',
        'content'     => $content,
        'footer_text' => 'This is an automated confirmation email.',
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
        
        $to = $customer->getEmail();
        $subject = 'Reservation Confirmed #' . $booking_id . ' - Preelook Apartments';
        
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
            'From: Preelook Apartments <info@preelook.com>',
            'Reply-To: info@preelook.com'
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

    // Greeting
    $content .= shaped_email_block_greeting($data['customer_first']);

    // Intro
    $content .= shaped_email_block_intro(
        "Your reservation at Preelook Apartments has been confirmed! Your card has been securely saved."
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
    $primary = shaped_brand_color('primary');
    $text_muted = shaped_brand_color('textMuted');
    $content .= '<p style="margin: 0; font-size: 14px; color: ' . $text_muted . '; text-align: center; line-height: 1.6;">';
    $content .= "You'll receive full booking details after payment is processed.<br>";
    $content .= 'Questions? Contact us at <a href="tel:+385916133609" style="color: ' . $primary . ';">+385 91 613 3609</a>';
    $content .= '</p>';

    // Render full email
    return shaped_render_email([
        'title'       => 'Reservation Confirmed - Preelook Apartments',
        'header'      => 'Reservation Confirmed!',
        'subtitle'    => 'Card saved successfully',
        'content'     => $content,
        'footer_text' => '',
    ]);
}

function shaped_send_cancellation_email($booking_id, $is_refundable = false) {
    $booking = MPHB()->getBookingRepository()->findById($booking_id);
    $customer = $booking->getCustomer();
    
    $to = $customer->getEmail();
    $subject = 'Booking Cancelled #' . $booking_id . ' - Preelook Apartments';
    
    $currency = MPHB()->settings()->currency()->getCurrencySymbol();
    
    $message = shaped_get_cancellation_template([
        'booking_id' => $booking_id,
        'customer_first' => $customer->getFirstName(),
        'is_refundable' => $is_refundable
    ]);
    
    // FIX: Add proper headers like confirmation email
    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: Preelook Apartments <info@preelook.com>',
        'Reply-To: info@preelook.com'
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
        'We hope to welcome you to Preelook in the future.',
        'The Preelook Team',
        'neutral'
    );

    // Render full email
    return shaped_render_email([
        'title'       => 'Booking Cancelled - Preelook Apartments',
        'header'      => 'Booking Cancelled',
        'subtitle'    => "We've processed your cancellation",
        'content'     => $content,
        'footer_text' => 'This is an automated cancellation confirmation.',
    ]);
}

/**
 * Send deposit confirmation email to guest
 * Sent when deposit payment is successfully processed
 *
 * @param int $booking_id The booking ID
 * @return bool True if sent successfully
 */
function shaped_send_deposit_confirmation_email($booking_id) {
    try {
        error_log('[Shaped Email] Starting deposit confirmation for booking #' . $booking_id);

        $booking = MPHB()->getBookingRepository()->findById($booking_id, true);
        if (!$booking) return false;

        $customer = $booking->getCustomer();
        if (!$customer || !$customer->getEmail()) return false;

        // Prevent duplicate sends
        $already_sent = get_post_meta($booking_id, '_shaped_deposit_confirmation_sent', true);
        if ($already_sent) {
            error_log('[Shaped Email] Deposit confirmation already sent on ' . $already_sent);
            return false;
        }

        // Get deposit details
        $check_in = $booking->getCheckInDate()->format('d.m.Y');
        $check_out = $booking->getCheckOutDate()->format('d.m.Y');
        $currency = MPHB()->settings()->currency()->getCurrencySymbol();
        $deposit_amount = get_post_meta($booking_id, '_shaped_deposit_amount', true);
        $balance_due = get_post_meta($booking_id, '_shaped_balance_due', true);

        $room_type_ids = $booking->getReservedRoomTypeIds();
        $room_names = array_map('get_the_title', $room_type_ids);
        $room_list = implode(', ', $room_names);

        $to = $customer->getEmail();
        $subject = 'Deposit Received #' . $booking_id . ' - Preelook Apartments';

        // Build professional HTML email
        $message = shaped_get_deposit_confirmation_template([
            'booking_id' => $booking_id,
            'customer_name' => $customer->getFirstName() . ' ' . $customer->getLastName(),
            'customer_first' => $customer->getFirstName(),
            'check_in' => $check_in,
            'check_out' => $check_out,
            'room_list' => $room_list,
            'deposit_paid' => $currency . number_format($deposit_amount, 2),
            'balance_due' => $currency . number_format($balance_due, 2),
            'total_amount' => $currency . number_format($deposit_amount + $balance_due, 2),
            'customer_email' => $customer->getEmail(),
            'customer_phone' => $customer->getPhone()
        ]);

        // Set headers
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: Preelook Apartments <info@preelook.com>',
            'Reply-To: info@preelook.com'
        ];

        $sent = wp_mail($to, $subject, $message, $headers);

        if ($sent) {
            update_post_meta($booking_id, '_shaped_deposit_confirmation_sent', current_time('mysql'));
            error_log('[Shaped Email] Deposit confirmation sent to ' . $to);
        }

        return $sent;

    } catch (Exception $e) {
        error_log('[Shaped Email] ERROR in deposit confirmation: ' . $e->getMessage());
        return false;
    }
}

function shaped_get_deposit_confirmation_template($data) {
    // Build content using reusable blocks
    $content = '';

    // Greeting
    $content .= shaped_email_block_greeting($data['customer_first']);

    // Intro
    $content .= shaped_email_block_intro(
        "Thank you for choosing Preelook Apartments! We've successfully received your deposit payment. We're excited to welcome you to the beautiful seaside town of Rijeka, just steps from the Adriatic Sea."
    );

    // Deposit Payment Details
    $content .= shaped_email_render_deposit_details([
        'booking_id' => $data['booking_id'],
        'check_in'   => $data['check_in'],
        'check_out'  => $data['check_out'],
        'room_list'  => $data['room_list'],
        'deposit_paid' => $data['deposit_paid'],
        'balance_due' => $data['balance_due'],
        'total_amount' => $data['total_amount'],
    ]);

    // Getting Here
    $content .= shaped_email_render_getting_here();

    // Explore the Area
    $content .= shaped_email_render_explore_area();

    // Contact
    $content .= shaped_email_render_contact();

    // Closing
    $content .= shaped_email_render_closing();

    // Render full email
    return shaped_render_email([
        'title'       => 'Deposit Received - Preelook Apartments',
        'header'      => 'Deposit Received!',
        'subtitle'    => 'Your booking is confirmed',
        'content'     => $content,
        'footer_text' => 'This is an automated confirmation email.',
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

        $check_in = $booking->getCheckInDate()->format('d.m.Y');
        $currency = MPHB()->settings()->currency()->getCurrencySymbol();
        $pending_amount = get_post_meta($booking_id, '_stripe_pending_amount', true);

        $to = $customer->getEmail();
        $subject = 'Payment Failed - Action Required #' . $booking_id . ' - Preelook Apartments';

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
        $message .= "Phone: +385 91 613 3609\n";
        $message .= "Email: info@preelook.com\n\n";
        $message .= "We're here to help resolve this quickly.\n\n";
        $message .= "Regards,\n";
        $message .= "The Preelook Team";

        $headers = [
            'From: Preelook Apartments <info@preelook.com>',
            'Reply-To: info@preelook.com'
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