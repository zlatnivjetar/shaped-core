<?php
/**
 * Shaped Admin Email Handler - Production Ready Version
 * Handles sending confirmation, cancellation, and reschedule notifications to the site administrator.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define the administrator's email address for easy updating
define( 'SHAPED_ADMIN_EMAIL', 'info@test.preelook.com' );

/**
 * Send a notification to the admin when a new booking is confirmed.
 *
 * @param int $booking_id The ID of the confirmed booking.
 * @return bool True if the email was sent successfully, false otherwise.
 */
function shaped_send_admin_confirmation_email( $booking_id ) {
    // Ensure the main email handler functions are available if needed
    if ( ! function_exists( 'shaped_send_confirmation_email' ) ) {
        error_log( '[Shaped Admin Email] Main email handler not loaded. Cannot proceed.' );
        return false;
    }

    try {
        error_log( '[Shaped Admin Email] Starting admin confirmation for booking #' . $booking_id );

        $booking = MPHB()->getBookingRepository()->findById( $booking_id, true );
        if ( ! $booking ) {
            error_log( '[Shaped Admin Email] Booking not found: #' . $booking_id );
            return false;
        }

        $customer = $booking->getCustomer();
        if ( ! $customer ) {
            error_log( '[Shaped Admin Email] No customer found for booking #' . $booking_id );
            return false;
        }

        // Gather booking details
        $check_in       = $booking->getCheckInDate()->format( 'F j, Y' );
        $check_out      = $booking->getCheckOutDate()->format( 'F j, Y' );
        $currency       = MPHB()->settings()->currency()->getCurrencySymbol();
        $room_type_ids  = $booking->getReservedRoomTypeIds();
        $room_names     = array_map( 'get_the_title', $room_type_ids );
        $room_list      = implode( ', ', $room_names );
        
        // FIX: Cast to float before formatting
        $total_paid_raw = get_post_meta( $booking_id, '_mphb_paid_amount', true );
        $total_paid     = (float) $total_paid_raw; // Cast to float
        
        // If still no paid amount, try to get from booking total
        if ( $total_paid <= 0 ) {
            $total_paid = (float) $booking->getTotalPrice();
        }

        // Email setup
        $to      = SHAPED_ADMIN_EMAIL;
        $subject = '✅ Booking Confirmed: #' . $booking_id; 
        
        $message = shaped_get_admin_confirmation_template( array(
            'booking_id'      => $booking_id,
            'customer_name'   => $customer->getFirstName() . ' ' . $customer->getLastName(),
            'customer_email'  => $customer->getEmail(),
            'customer_phone'  => $customer->getPhone(),
            'check_in'        => $check_in,
            'check_out'       => $check_out,
            'room_list'       => $room_list,
            'total_paid'      => $currency . number_format( $total_paid, 2 ),
        ) );
        
        // Fix: Use WordPress default sender or site domain
        $site_email = get_option('admin_email');
        $site_domain = parse_url(home_url(), PHP_URL_HOST);
        $from_email = 'bookings@' . $site_domain; // Or use $site_email
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Preelook Bookings <' . $from_email . '>',
            'Reply-To: ' . $customer->getEmail(),
            'X-Mailer: PHP/' . phpversion(),
            'MIME-Version: 1.0',
            'X-Priority: 3',  // Normal priority
        );

        $sent = wp_mail( $to, $subject, $message, $headers );
        
        if ( $sent ) {
            error_log( '[Shaped Admin Email] Confirmation successfully sent to ' . $to );
        } else {
            error_log( '[Shaped Admin Email] FAILED to send admin confirmation to ' . $to );
        }
        
        return $sent;

    } catch ( Exception $e ) {
        error_log( '[Shaped Admin Email] ERROR in confirmation: ' . $e->getMessage() );
        return false;
    }
}

/**
 * Send a notification to the admin when a booking is reserved (delayed payment).
 *
 * @param int $booking_id The ID of the reserved booking.
 * @return bool True if the email was sent successfully, false otherwise.
 */
function shaped_send_admin_reservation_email( $booking_id ) {
    try {
        error_log( '[Shaped Admin Email] Starting admin reservation notification for booking #' . $booking_id );

        $booking = MPHB()->getBookingRepository()->findById( $booking_id, true );
        if ( ! $booking ) {
            error_log( '[Shaped Admin Email] Booking not found: #' . $booking_id );
            return false;
        }

        $customer = $booking->getCustomer();
        if ( ! $customer ) {
            error_log( '[Shaped Admin Email] No customer found for booking #' . $booking_id );
            return false;
        }

        // Gather booking details
        $check_in       = $booking->getCheckInDate()->format( 'F j, Y' );
        $check_out      = $booking->getCheckOutDate()->format( 'F j, Y' );
        $currency       = MPHB()->settings()->currency()->getCurrencySymbol();
        $room_type_ids  = $booking->getReservedRoomTypeIds();
        $room_names     = array_map( 'get_the_title', $room_type_ids );
        $room_list      = implode( ', ', $room_names );
        
        // Get pending amount for delayed payment
        $pending_amount_raw = get_post_meta( $booking_id, '_stripe_pending_amount', true );
        $pending_amount = (float) $pending_amount_raw;
        
        if ( $pending_amount <= 0 ) {
            $pending_amount = (float) $booking->getTotalPrice();
        }
        
        // Get charge date
        $charge_date = get_post_meta( $booking_id, '_shaped_charge_date', true );
        $charge_date_formatted = $charge_date ? date( 'F j, Y', strtotime( $charge_date ) ) : 'N/A';

        $to      = SHAPED_ADMIN_EMAIL;
        $subject = '🔔 Booking Reserved (Card Saved): #' . $booking_id;
        
        $message = shaped_get_admin_reservation_template( array(
            'booking_id'      => $booking_id,
            'customer_name'   => $customer->getFirstName() . ' ' . $customer->getLastName(),
            'customer_email'  => $customer->getEmail(),
            'customer_phone'  => $customer->getPhone(),
            'check_in'        => $check_in,
            'check_out'       => $check_out,
            'room_list'       => $room_list,
            'pending_amount'  => $currency . number_format( $pending_amount, 2 ),
            'charge_date'     => $charge_date_formatted,
        ) );
        
        $site_domain = parse_url(home_url(), PHP_URL_HOST);
        $from_email = 'bookings@' . $site_domain;
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Preelook Bookings <' . $from_email . '>',
            'Reply-To: ' . $customer->getEmail(),
            'X-Mailer: PHP/' . phpversion(),
            'MIME-Version: 1.0',
            'X-Priority: 3',
        );

        $sent = wp_mail( $to, $subject, $message, $headers );
        
        if ( $sent ) {
            error_log( '[Shaped Admin Email] Reservation notification successfully sent to ' . $to );
        } else {
            error_log( '[Shaped Admin Email] FAILED to send admin reservation notification to ' . $to );
        }
        
        return $sent;

    } catch ( Exception $e ) {
        error_log( '[Shaped Admin Email] ERROR in reservation notification: ' . $e->getMessage() );
        return false;
    }
}

/**
 * Send a notification to the admin when a booking is cancelled.
 *
 * @param int   $booking_id        The ID of the cancelled booking.
 * @param float $refund_amount     The amount refunded to the customer.
 * @param int   $refund_percentage The percentage of the total that was refunded.
 * @return bool True if the email was sent, false otherwise.
 */
function shaped_send_admin_cancellation_email( $booking_id, $refund_amount, $refund_percentage ) {
    try {
        error_log( '[Shaped Admin Email] Starting admin cancellation for booking #' . $booking_id );

        $booking = MPHB()->getBookingRepository()->findById( $booking_id, true );
        $customer = $booking->getCustomer();
        
        if ( ! $booking || ! $customer ) {
            error_log( '[Shaped Admin Email] Could not find booking or customer for cancellation notice.' );
            return false;
        }

        $currency = MPHB()->settings()->currency()->getCurrencySymbol();
        
        // Cast refund_amount to float to be safe
        $refund_amount = (float) $refund_amount;

        $to      = SHAPED_ADMIN_EMAIL;
        $subject = '❌ Booking Cancellation: #' . $booking_id;
        
        $message = shaped_get_admin_cancellation_template( array(
            'booking_id'        => $booking_id,
            'customer_name'     => $customer->getFirstName() . ' ' . $customer->getLastName(),
            'customer_email'    => $customer->getEmail(),
            'refund_amount'     => $currency . number_format( $refund_amount, 2 ),
            'refund_percentage' => $refund_percentage,
        ) );
            
        $site_domain = parse_url(home_url(), PHP_URL_HOST);
        $from_email = 'bookings@' . $site_domain;
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Preelook Bookings <' . $from_email . '>',
            'Reply-To: ' . $customer->getEmail(),
            'X-Mailer: PHP/' . phpversion(),
            'MIME-Version: 1.0',
            'X-Priority: 3',  // Normal priority
        );

        $sent = wp_mail( $to, $subject, $message, $headers );

        if ( $sent ) {
            error_log( '[Shaped Admin Email] Cancellation notice successfully sent to ' . $to );
        } else {
            error_log( '[Shaped Admin Email] FAILED to send admin cancellation notice.' );
        }
        
        return $sent;

    } catch ( Exception $e ) {
        error_log( '[Shaped Admin Email] ERROR in cancellation: ' . $e->getMessage() );
        return false;
    }
}

/* -------------------------------------------------------------------------
 * ADMIN EMAIL HTML TEMPLATES
 * Simple, data-focused templates for internal use.
 * --------------------------------------------------------------------- */

function shaped_get_admin_confirmation_template( $data ) {
    ob_start();
    ?>
    <div style="font-family: Arial, sans-serif; font-size: 14px; color: #333; max-width: 600px; border: 1px solid #ddd; border-radius: 5px; margin: 20px auto;">
        <div style="background-color: #2e7d32; color: white; padding: 15px; border-radius: 5px 5px 0 0;">
            <h2 style="margin: 0;">Booking Confirmed: #<?php echo esc_html( $data['booking_id'] ); ?></h2>
        </div>
        <div style="padding: 20px;">
            <h3 style="margin-top: 0;">Booking Details:</h3>
            <p><strong>Accommodation:</strong> <?php echo esc_html( $data['room_list'] ); ?></p>
            <p><strong>Check-in:</strong> <?php echo esc_html( $data['check_in'] ); ?></p>
            <p><strong>Check-out:</strong> <?php echo esc_html( $data['check_out'] ); ?></p>
            <p><strong>Booking Total:</strong> <strong style="color: #2e7d32;"><?php echo esc_html( $data['total_paid'] ); ?></strong></p>
            <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">
            <h3 style="margin-top: 0;">Customer Details:</h3>
            <p><strong>Name:</strong> <?php echo esc_html( $data['customer_name'] ); ?></p>
            <p><strong>Email:</strong> <a href="mailto:<?php echo esc_attr( $data['customer_email'] ); ?>"><?php echo esc_html( $data['customer_email'] ); ?></a></p>
            <p><strong>Phone:</strong> <?php echo esc_html( $data['customer_phone'] ); ?></p>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function shaped_get_admin_reservation_template( $data ) {
    ob_start();
    ?>
    <div style="font-family: Arial, sans-serif; font-size: 14px; color: #333; max-width: 600px; border: 1px solid #ddd; border-radius: 5px; margin: 20px auto;">
        <div style="background-color: #1976d2; color: white; padding: 15px; border-radius: 5px 5px 0 0;">
            <h2 style="margin: 0;">Booking Reserved: #<?php echo esc_html( $data['booking_id'] ); ?></h2>
        </div>
        <div style="padding: 20px;">
            <h3 style="margin-top: 0;">Booking Details:</h3>
            <p><strong>Accommodation:</strong> <?php echo esc_html( $data['room_list'] ); ?></p>
            <p><strong>Check-in:</strong> <?php echo esc_html( $data['check_in'] ); ?></p>
            <p><strong>Check-out:</strong> <?php echo esc_html( $data['check_out'] ); ?></p>
            <p><strong>Total Amount:</strong> <strong style="color: #1976d2;"><?php echo esc_html( $data['pending_amount'] ); ?></strong></p>
            <p style="background: #fffbf0; padding: 10px; border-radius: 3px; border-left: 3px solid #D1AF5D;">
                <strong>⚠️ Payment Status:</strong> Card saved. Will be charged on <?php echo esc_html( $data['charge_date'] ); ?>
            </p>
            <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">
            <h3 style="margin-top: 0;">Customer Details:</h3>
            <p><strong>Name:</strong> <?php echo esc_html( $data['customer_name'] ); ?></p>
            <p><strong>Email:</strong> <a href="mailto:<?php echo esc_attr( $data['customer_email'] ); ?>"><?php echo esc_html( $data['customer_email'] ); ?></a></p>
            <p><strong>Phone:</strong> <?php echo esc_html( $data['customer_phone'] ); ?></p>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function shaped_get_admin_cancellation_template( $data ) {
    ob_start();
    ?>
    <div style="font-family: Arial, sans-serif; font-size: 14px; color: #333; max-width: 600px; border: 1px solid #ddd; border-radius: 5px; margin: 20px auto;">
        <div style="background-color: #c00; color: white; padding: 15px; border-radius: 5px 5px 0 0;">
            <h2 style="margin: 0;">Booking Cancelled: #<?php echo esc_html( $data['booking_id'] ); ?></h2>
        </div>
        <div style="padding: 20px;">
            <h3 style="margin-top: 0;">Cancellation Details:</h3>
            <p><strong>Customer:</strong> <?php echo esc_html( $data['customer_name'] ); ?> (<?php echo esc_html( $data['customer_email'] ); ?>)</p>
            <p><strong>Booking ID:</strong> #<?php echo esc_html( $data['booking_id'] ); ?></p>
            <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">
            <h3 style="margin-top: 0;">Refund Processed:</h3>
            <p><strong>Refund Amount:</strong> <strong style="color: #c00;"><?php echo esc_html( $data['refund_amount'] ); ?></strong></p>
            <p><strong>Refund Percentage:</strong> <?php echo esc_html( $data['refund_percentage'] ); ?>%</p>
            <p style="font-size: 12px; color: #666;">The calendar has been updated and the room is now available for booking.</p>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Send admin notification when deposit payment is received
 *
 * @param int $booking_id The booking ID
 * @return bool True if sent successfully
 */
function shaped_send_admin_deposit_email( $booking_id ) {
    try {
        error_log( '[Shaped Admin Email] Starting admin deposit notification for booking #' . $booking_id );

        $booking = MPHB()->getBookingRepository()->findById( $booking_id, true );
        if ( ! $booking ) {
            error_log( '[Shaped Admin Email] Booking not found: #' . $booking_id );
            return false;
        }

        $customer = $booking->getCustomer();
        if ( ! $customer ) {
            error_log( '[Shaped Admin Email] No customer found for booking #' . $booking_id );
            return false;
        }

        // Gather booking details
        $check_in = $booking->getCheckInDate()->format( 'F j, Y' );
        $check_out = $booking->getCheckOutDate()->format( 'F j, Y' );
        $currency = MPHB()->settings()->currency()->getCurrencySymbol();
        $room_type_ids = $booking->getReservedRoomTypeIds();
        $room_names = array_map( 'get_the_title', $room_type_ids );
        $room_list = implode( ', ', $room_names );

        // Get deposit details
        $deposit_amount = (float) get_post_meta( $booking_id, '_shaped_deposit_paid', true );
        $balance_due = (float) get_post_meta( $booking_id, '_shaped_balance_due', true );
        $total_price = (float) $booking->getTotalPrice();

        $to = SHAPED_ADMIN_EMAIL;
        $subject = '💰 Deposit Received: #' . $booking_id;

        // Plain text message
        $message = "DEPOSIT RECEIVED\n\n";
        $message .= "Booking ID: #" . $booking_id . "\n\n";
        $message .= "DEPOSIT DETAILS:\n";
        $message .= "Deposit Paid: " . $currency . number_format( $deposit_amount, 2 ) . "\n";
        $message .= "Balance Due: " . $currency . number_format( $balance_due, 2 ) . "\n";
        $message .= "Total Booking: " . $currency . number_format( $total_price, 2 ) . "\n";
        $message .= "Payment Due: On arrival at check-in\n\n";
        $message .= "BOOKING DETAILS:\n";
        $message .= "Accommodation: " . $room_list . "\n";
        $message .= "Check-in: " . $check_in . "\n";
        $message .= "Check-out: " . $check_out . "\n\n";
        $message .= "CUSTOMER DETAILS:\n";
        $message .= "Name: " . $customer->getFirstName() . ' ' . $customer->getLastName() . "\n";
        $message .= "Email: " . $customer->getEmail() . "\n";
        $message .= "Phone: " . $customer->getPhone() . "\n\n";
        $message .= "---\n";
        $message .= "Preelook Apartments Booking System";

        $site_domain = parse_url( home_url(), PHP_URL_HOST );
        $from_email = 'bookings@' . $site_domain;

        $headers = array(
            'From: Preelook Bookings <' . $from_email . '>',
            'Reply-To: ' . $customer->getEmail()
        );

        $sent = wp_mail( $to, $subject, $message, $headers );

        if ( $sent ) {
            error_log( '[Shaped Admin Email] Deposit notification successfully sent to ' . $to );
        } else {
            error_log( '[Shaped Admin Email] FAILED to send deposit notification to ' . $to );
        }

        return $sent;

    } catch ( Exception $e ) {
        error_log( '[Shaped Admin Email] ERROR in deposit notification: ' . $e->getMessage() );
        return false;
    }
}