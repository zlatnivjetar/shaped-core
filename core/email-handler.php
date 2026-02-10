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

        // Get rate name (e.g., "Room only" or "Breakfast included")
        $rate_name = function_exists( 'shaped_get_booking_rate_name' )
            ? shaped_get_booking_rate_name( $booking_id )
            : '';

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
            'rate_name' => $rate_name,
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
        'rate_name'  => isset($data['rate_name']) ? $data['rate_name'] : '',
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

        // Get room details
        $room_type_ids = $booking->getReservedRoomTypeIds();
        $room_names = array_map('get_the_title', $room_type_ids);
        $room_list = implode(', ', $room_names);

        // Get rate name
        $rate_name = function_exists('shaped_get_booking_rate_name')
            ? shaped_get_booking_rate_name($booking_id)
            : '';

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
            'room_list' => $room_list,
            'rate_name' => $rate_name,
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
        'room_list'  => isset($data['room_list']) ? $data['room_list'] : '',
        'rate_name'  => isset($data['rate_name']) ? $data['rate_name'] : '',
    ]);

    // Payment Info
    $threshold_days = class_exists('Shaped_Pricing') ? Shaped_Pricing::get_scheduled_threshold_days() : 7;
    $content .= shaped_email_block_payment_info(
        '€' . $data['amount'],
        $data['charge_date'],
        '(' . $threshold_days . ' days before your arrival)'
    );

    // Manage Booking Button
    $manage_url = shaped_email_get_manage_url($data['booking_id'], $data['customer_email']);
    $content .= shaped_email_block_button(
        'Manage Booking',
        $manage_url,
        'Free cancellation until ' . $data['charge_date']
    );

    // Footer note
    $text_primary = shaped_email_color('textMuted', '#26272c');
    $text_muted = shaped_email_color('textMuted', '#666666');
    $content .= '<p style="margin: 0; font-size: 14px; color: ' . $text_muted . '; text-align: center; line-height: 1.6;">';
    $content .= "You'll receive full booking details after payment is processed.<br>";
    if ($phone) {
        $phone_clean = preg_replace('/[^0-9+]/', '', $phone);
        $content .= 'Questions? Contact us at <a href="tel:' . $phone_clean . '" style="color: ' . $text_primary . ';">' . esc_html($phone) . '</a>';
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

        $check_in = $booking->getCheckInDate()->format('d.m.Y');
        $currency = MPHB()->settings()->currency()->getCurrencySymbol();
        $pending_amount = get_post_meta($booking_id, '_stripe_pending_amount', true);

        $to = $customer->getEmail();
        $subject = 'Payment Failed - Action Required #' . $booking_id . ' - ' . $from_name;

        $message = shaped_get_payment_failed_template([
            'booking_id' => $booking_id,
            'customer_first' => $customer->getFirstName(),
            'check_in' => $check_in,
            'amount_due' => $currency . number_format($pending_amount, 2),
        ]);

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
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

/**
 * Get styled HTML template for payment failed email
 *
 * @param array $data Email data
 * @return string HTML email content
 */
function shaped_get_payment_failed_template($data) {
    $content = '';
    $company_name = shaped_email_config('company_name', 'our property');
    $phone = shaped_email_config('phone', '');
    $contact_email = shaped_email_config('email', '');
    $signature = shaped_email_config('signature', 'The Team');

    // Greeting
    $content .= shaped_email_block_greeting($data['customer_first']);

    // Alert intro
    $content .= shaped_email_block_intro(
        'We attempted to charge your payment for booking <strong>#' . esc_html($data['booking_id']) . '</strong> but the payment was unsuccessful.'
    );

    // Payment details card
    $content .= shaped_email_block_card_start('highlight');
    $content .= shaped_email_block_section_title('Payment Details', '💳');
    $content .= shaped_email_block_rows_start();
    $content .= shaped_email_block_row('Booking ID:', '#' . $data['booking_id'], ['bold_value' => true]);
    $content .= shaped_email_block_row('Amount Due:', $data['amount_due'], ['bold_value' => true]);
    $content .= shaped_email_block_row('Check-in:', $data['check_in'], ['bold_value' => true]);
    $content .= shaped_email_block_rows_end();
    $content .= shaped_email_block_card_end();

    // Warning card
    $text_primary = shaped_email_color('textMuted', '#26272C');
    $text_muted = shaped_email_color('textMuted', '#666666');
    $content .= '<div class="email-alert-danger" style="background: #FFF5F5; border-left: 4px solid #b83c2e; border-radius: 8px; padding: 20px; margin: 0 0 24px 0;">';
    $content .= '<p class="email-text-primary" style="margin: 0 0 12px 0; font-size: 16px; color: ' . $text_primary . '; font-weight: 700;">⚠️ Action Required</p>';
    $content .= '<p class="email-text-muted" style="margin: 0 0 12px 0; font-size: 14px; color: ' . $text_muted . '; line-height: 1.6;">Your booking is at risk of cancellation. Please contact us immediately to resolve this payment issue.</p>';
    $content .= '<p class="email-text-muted" style="margin: 0; font-size: 14px; color: ' . $text_muted . '; line-height: 1.6;"><strong>Common reasons:</strong> Insufficient funds, expired card, card limit exceeded, or bank declined the transaction.</p>';
    $content .= '</div>';

    // Contact section
    $content .= shaped_email_render_contact([
        'title' => 'Contact Us Urgently',
        'intro' => "We're here to help resolve this quickly:",
        'phone' => $phone,
        'email' => $contact_email,
    ]);

    // Closing
    $content .= shaped_email_block_closing(
        'Please reach out as soon as possible to keep your booking.',
        $signature,
        'neutral'
    );

    return shaped_render_email([
        'title'       => 'Payment Failed - ' . $company_name,
        'header'      => 'Payment Failed',
        'subtitle'    => 'Action required',
        'content'     => $content,
        'footer_text' => '',
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

        // Get booking details
        $check_in = $booking->getCheckInDate()->format('d.m.Y');
        $check_out = $booking->getCheckOutDate()->format('d.m.Y');
        $currency = MPHB()->settings()->currency()->getCurrencySymbol();

        $room_type_ids = $booking->getReservedRoomTypeIds();
        $room_names = array_map('get_the_title', $room_type_ids);
        $room_list = implode(', ', $room_names);

        // Get rate name
        $rate_name = function_exists('shaped_get_booking_rate_name')
            ? shaped_get_booking_rate_name($booking_id)
            : '';

        // Get deposit details
        $deposit_amount = (float) get_post_meta($booking_id, '_shaped_deposit_paid', true);
        $balance_due = (float) get_post_meta($booking_id, '_shaped_balance_due', true);

        // Fallback if meta not set
        if ($deposit_amount <= 0) {
            $deposit_amount = (float) get_post_meta($booking_id, '_mphb_paid_amount', true);
        }
        if ($balance_due <= 0 && $deposit_amount > 0) {
            $total_from_booking = (float) $booking->getTotalPrice();
            $balance_due = $total_from_booking - $deposit_amount;
        }

        // Calculate total price as deposit + balance to get the correct discounted amount
        // This ensures we show the actual amount customer pays, not the undiscounted price
        $total_price = $deposit_amount + $balance_due;

        // Get email config
        $from_name = shaped_email_config('from_name', get_bloginfo('name'));
        $from_email = shaped_email_config('from_email', get_option('admin_email'));

        $to = $customer->getEmail();
        $subject = 'Deposit Received #' . $booking_id . ' - ' . $from_name;

        $message = shaped_get_deposit_confirmation_template([
            'booking_id' => $booking_id,
            'customer_first' => $customer->getFirstName(),
            'check_in' => $check_in,
            'check_out' => $check_out,
            'room_list' => $room_list,
            'rate_name' => $rate_name,
            'deposit_paid' => $currency . number_format($deposit_amount, 2),
            'balance_due' => $currency . number_format($balance_due, 2),
            'total_amount' => $currency . number_format($total_price, 2),
        ]);

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
            'Reply-To: ' . $from_email
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

/**
 * Get styled HTML template for deposit confirmation email
 *
 * @param array $data Email data
 * @return string HTML email content
 */
function shaped_get_deposit_confirmation_template($data) {
    $content = '';
    $company_name = shaped_email_config('company_name', 'our property');
    $company_location = shaped_email_config('company_location', '');
    $check_in_time = shaped_email_config('check_in_time', 'from 16:00');
    $check_out_time = shaped_email_config('check_out_time', 'until 11:00');

    // Greeting
    $content .= shaped_email_block_greeting($data['customer_first']);

    // Intro
    $intro_text = "Thank you for choosing " . $company_name . "! We've successfully received your deposit payment.";
    if ($company_location) {
        $intro_text .= " We're excited to welcome you to the beautiful " . $company_location . ".";
    }
    $content .= shaped_email_block_intro($intro_text);

    // Booking Details Card
    $content .= shaped_email_block_card_start('highlight');
    $content .= shaped_email_block_section_title('Booking Details', '📋');
    $content .= shaped_email_block_rows_start();
    $content .= shaped_email_block_row('Booking ID:', '#' . $data['booking_id'], ['bold_value' => true]);
    $content .= shaped_email_block_row('Check-in:', $data['check_in'], [
        'bold_value' => true,
        'sub_text' => $check_in_time,
    ]);
    $content .= shaped_email_block_row('Check-out:', $data['check_out'], [
        'bold_value' => true,
        'sub_text' => $check_out_time,
    ]);
    $content .= shaped_email_block_row('Accommodation:', $data['room_list'], ['bold_value' => true]);

    if (!empty($data['rate_name'])) {
        $content .= shaped_email_block_row('Rate:', $data['rate_name'], ['bold_value' => true]);
    }

    $content .= shaped_email_block_rows_end();
    $content .= shaped_email_block_card_end();

    // Payment Summary Card
    $content .= shaped_email_block_card_start('neutral');
    $content .= shaped_email_block_section_title('Payment Summary', '💰');
    $content .= shaped_email_block_rows_start();
    $content .= shaped_email_block_row('Deposit Paid:', $data['deposit_paid'], ['bold_value' => true]);
    $content .= shaped_email_block_row('Balance Due at Check-in:', $data['balance_due'], ['bold_value' => true]);
    $content .= shaped_email_block_total_divider();
    $content .= shaped_email_block_total_row('Total Booking Value:', $data['total_amount']);
    $content .= shaped_email_block_rows_end();
    $content .= shaped_email_block_card_end();

    // Getting Here
    $content .= shaped_email_render_getting_here();

    // Contact
    $content .= shaped_email_render_contact();

    // Closing
    $content .= shaped_email_render_closing();

    // Render full email
    $tagline = shaped_email_config('company_tagline', 'Your stay awaits');
    return shaped_render_email([
        'title'       => 'Deposit Received - ' . $company_name,
        'header'      => 'Deposit Received!',
        'subtitle'    => $tagline,
        'content'     => $content,
        'footer_text' => '',
    ]);
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