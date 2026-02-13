<?php
/**
 * Shaped Admin Email Handler - Production Ready Version
 * Handles sending confirmation, cancellation, and reschedule notifications to the site administrator.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get the admin email address at call time (not at file-load time).
 *
 * Using a function instead of a constant avoids locking in a stale value
 * when the brand config or WordPress options resolve to a staging domain
 * during early file inclusion.
 *
 * Priority: brand config contact.email → shaped_get_admin_email() → WP admin_email
 *
 * @return string Admin email address
 */
function shaped_get_admin_to_email(): string {
    $admin_email = shaped_email_config('email', '');
    if (empty($admin_email)) {
        $admin_email = function_exists('shaped_get_admin_email')
            ? shaped_get_admin_email()
            : get_option('admin_email');
    }
    return $admin_email;
}

// Keep backward compatibility for any external code referencing the constant
if (!defined('SHAPED_ADMIN_EMAIL')) {
    define('SHAPED_ADMIN_EMAIL', shaped_get_admin_to_email());
}

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

        // Get rate name
        $rate_name = function_exists( 'shaped_get_booking_rate_name' )
            ? shaped_get_booking_rate_name( $booking_id )
            : '';

        // FIX: Cast to float before formatting
        $total_paid_raw = get_post_meta( $booking_id, '_mphb_paid_amount', true );
        $total_paid     = (float) $total_paid_raw; // Cast to float
        
        // If still no paid amount, try to get from booking total
        if ( $total_paid <= 0 ) {
            $total_paid = (float) $booking->getTotalPrice();
        }

        // Email setup
        $to      = shaped_get_admin_to_email();
        $subject = '✅ Booking Confirmed: #' . $booking_id;

        $message = shaped_get_admin_confirmation_template( array(
            'booking_id'      => $booking_id,
            'customer_name'   => $customer->getFirstName() . ' ' . $customer->getLastName(),
            'customer_email'  => $customer->getEmail(),
            'customer_phone'  => $customer->getPhone(),
            'check_in'        => $check_in,
            'check_out'       => $check_out,
            'room_list'       => $room_list,
            'rate_name'       => $rate_name,
            'total_paid'      => $currency . number_format( $total_paid, 2 ),
        ) );

        // Get sender details from brand config (consistent with guest emails)
        $from_name  = shaped_email_config('from_name', get_bloginfo('name'));
        $from_email = shaped_email_config('from_email', get_option('admin_email'));

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' Bookings <' . $from_email . '>',
            'Reply-To: ' . $customer->getEmail(),
            'X-Mailer: PHP/' . phpversion(),
            'MIME-Version: 1.0',
            'X-Priority: 3',
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

        // Get rate name
        $rate_name = function_exists( 'shaped_get_booking_rate_name' )
            ? shaped_get_booking_rate_name( $booking_id )
            : '';

        // Get pending amount for delayed payment
        $pending_amount_raw = get_post_meta( $booking_id, '_stripe_pending_amount', true );
        $pending_amount = (float) $pending_amount_raw;
        
        if ( $pending_amount <= 0 ) {
            $pending_amount = (float) $booking->getTotalPrice();
        }
        
        // Get charge date
        $charge_date = get_post_meta( $booking_id, '_shaped_charge_date', true );
        $charge_date_formatted = $charge_date ? date( 'F j, Y', strtotime( $charge_date ) ) : 'N/A';

        $to      = shaped_get_admin_to_email();
        $subject = '🔔 Booking Reserved (Card Saved): #' . $booking_id;

        $message = shaped_get_admin_reservation_template( array(
            'booking_id'      => $booking_id,
            'customer_name'   => $customer->getFirstName() . ' ' . $customer->getLastName(),
            'customer_email'  => $customer->getEmail(),
            'customer_phone'  => $customer->getPhone(),
            'check_in'        => $check_in,
            'check_out'       => $check_out,
            'room_list'       => $room_list,
            'rate_name'       => $rate_name,
            'pending_amount'  => $currency . number_format( $pending_amount, 2 ),
            'charge_date'     => $charge_date_formatted,
        ) );

        $from_name  = shaped_email_config('from_name', get_bloginfo('name'));
        $from_email = shaped_email_config('from_email', get_option('admin_email'));

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' Bookings <' . $from_email . '>',
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

        $to      = shaped_get_admin_to_email();
        $subject = '❌ Booking Cancellation: #' . $booking_id;

        $message = shaped_get_admin_cancellation_template( array(
            'booking_id'        => $booking_id,
            'customer_name'     => $customer->getFirstName() . ' ' . $customer->getLastName(),
            'customer_email'    => $customer->getEmail(),
            'refund_amount'     => $currency . number_format( $refund_amount, 2 ),
            'refund_percentage' => $refund_percentage,
        ) );

        $from_name  = shaped_email_config('from_name', get_bloginfo('name'));
        $from_email = shaped_email_config('from_email', get_option('admin_email'));

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' Bookings <' . $from_email . '>',
            'Reply-To: ' . $customer->getEmail(),
            'X-Mailer: PHP/' . phpversion(),
            'MIME-Version: 1.0',
            'X-Priority: 3',
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
            <?php if ( ! empty( $data['rate_name'] ) ) : ?>
            <p><strong>Rate:</strong> <?php echo esc_html( $data['rate_name'] ); ?></p>
            <?php endif; ?>
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
            <?php if ( ! empty( $data['rate_name'] ) ) : ?>
            <p><strong>Rate:</strong> <?php echo esc_html( $data['rate_name'] ); ?></p>
            <?php endif; ?>
            <p><strong>Check-in:</strong> <?php echo esc_html( $data['check_in'] ); ?></p>
            <p><strong>Check-out:</strong> <?php echo esc_html( $data['check_out'] ); ?></p>
            <p><strong>Total Amount:</strong> <strong style="color: #1976d2;"><?php echo esc_html( $data['pending_amount'] ); ?></strong></p>
            <p style="background: #fffbf0; padding: 10px; border-radius: 3px; border-left: 3px solid #2563EB;">
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

        // Get rate name
        $rate_name = function_exists( 'shaped_get_booking_rate_name' )
            ? shaped_get_booking_rate_name( $booking_id )
            : '';

        // Get deposit details
        $deposit_amount = (float) get_post_meta( $booking_id, '_shaped_deposit_paid', true );
        $balance_due = (float) get_post_meta( $booking_id, '_shaped_balance_due', true );
        $total_price = (float) $booking->getTotalPrice();

        // Fallback if meta not set
        if ( $deposit_amount <= 0 ) {
            $deposit_amount = (float) get_post_meta( $booking_id, '_mphb_paid_amount', true );
        }
        if ( $balance_due <= 0 && $deposit_amount > 0 ) {
            $balance_due = $total_price - $deposit_amount;
        }

        $to = shaped_get_admin_to_email();
        $subject = '💰 Deposit Received: #' . $booking_id;

        $message = shaped_get_admin_deposit_template( array(
            'booking_id'      => $booking_id,
            'customer_name'   => $customer->getFirstName() . ' ' . $customer->getLastName(),
            'customer_email'  => $customer->getEmail(),
            'customer_phone'  => $customer->getPhone(),
            'check_in'        => $check_in,
            'check_out'       => $check_out,
            'room_list'       => $room_list,
            'rate_name'       => $rate_name,
            'deposit_paid'    => $currency . number_format( $deposit_amount, 2 ),
            'balance_due'     => $currency . number_format( $balance_due, 2 ),
            'total_amount'    => $currency . number_format( $total_price, 2 ),
        ) );

        $from_name  = shaped_email_config('from_name', get_bloginfo('name'));
        $from_email = shaped_email_config('from_email', get_option('admin_email'));

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' Bookings <' . $from_email . '>',
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

function shaped_get_admin_deposit_template( $data ) {
    ob_start();
    ?>
    <div style="font-family: Arial, sans-serif; font-size: 14px; color: #333; max-width: 600px; border: 1px solid #ddd; border-radius: 5px; margin: 20px auto;">
        <div style="background-color: #2563EB; color: white; padding: 15px; border-radius: 5px 5px 0 0;">
            <h2 style="margin: 0;">Deposit Received: #<?php echo esc_html( $data['booking_id'] ); ?></h2>
        </div>
        <div style="padding: 20px;">
            <h3 style="margin-top: 0;">Payment Details:</h3>
            <p><strong>Deposit Paid:</strong> <strong style="color: #2e7d32;"><?php echo esc_html( $data['deposit_paid'] ); ?></strong></p>
            <p><strong>Balance Due:</strong> <strong style="color: #2563EB;"><?php echo esc_html( $data['balance_due'] ); ?></strong> (at check-in)</p>
            <p><strong>Total Booking:</strong> <?php echo esc_html( $data['total_amount'] ); ?></p>
            <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">
            <h3 style="margin-top: 0;">Booking Details:</h3>
            <p><strong>Accommodation:</strong> <?php echo esc_html( $data['room_list'] ); ?></p>
            <?php if ( ! empty( $data['rate_name'] ) ) : ?>
            <p><strong>Rate:</strong> <?php echo esc_html( $data['rate_name'] ); ?></p>
            <?php endif; ?>
            <p><strong>Check-in:</strong> <?php echo esc_html( $data['check_in'] ); ?></p>
            <p><strong>Check-out:</strong> <?php echo esc_html( $data['check_out'] ); ?></p>
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

/**
 * Send admin notification when a scheduled payment fails
 *
 * @param int $booking_id The booking ID
 * @return bool True if sent successfully
 */
function shaped_send_admin_payment_failed_email( $booking_id ) {
    try {
        error_log( '[Shaped Admin Email] Starting admin payment failed notification for booking #' . $booking_id );

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

        // Get pending amount
        $pending_amount = (float) get_post_meta( $booking_id, '_stripe_pending_amount', true );
        if ( $pending_amount <= 0 ) {
            $pending_amount = (float) $booking->getTotalPrice();
        }

        $to = shaped_get_admin_to_email();
        $subject = '⚠️ Payment Failed: #' . $booking_id . ' - Action Required';

        $message = shaped_get_admin_payment_failed_template( array(
            'booking_id'      => $booking_id,
            'customer_name'   => $customer->getFirstName() . ' ' . $customer->getLastName(),
            'customer_email'  => $customer->getEmail(),
            'customer_phone'  => $customer->getPhone(),
            'check_in'        => $check_in,
            'check_out'       => $check_out,
            'room_list'       => $room_list,
            'amount_due'      => $currency . number_format( $pending_amount, 2 ),
        ) );

        $from_name  = shaped_email_config('from_name', get_bloginfo('name'));
        $from_email = shaped_email_config('from_email', get_option('admin_email'));

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' Bookings <' . $from_email . '>',
            'Reply-To: ' . $customer->getEmail()
        );

        $sent = wp_mail( $to, $subject, $message, $headers );

        if ( $sent ) {
            error_log( '[Shaped Admin Email] Payment failed notification successfully sent to ' . $to );
        } else {
            error_log( '[Shaped Admin Email] FAILED to send payment failed notification to ' . $to );
        }

        return $sent;

    } catch ( Exception $e ) {
        error_log( '[Shaped Admin Email] ERROR in payment failed notification: ' . $e->getMessage() );
        return false;
    }
}

function shaped_get_admin_payment_failed_template( $data ) {
    ob_start();
    ?>
    <div style="font-family: Arial, sans-serif; font-size: 14px; color: #333; max-width: 600px; border: 1px solid #ddd; border-radius: 5px; margin: 20px auto;">
        <div style="background-color: #c00; color: white; padding: 15px; border-radius: 5px 5px 0 0;">
            <h2 style="margin: 0;">⚠️ Payment Failed: #<?php echo esc_html( $data['booking_id'] ); ?></h2>
        </div>
        <div style="padding: 20px;">
            <p style="background: #FFF5F5; padding: 15px; border-radius: 5px; border-left: 4px solid #c00; margin: 0 0 20px 0;">
                <strong>Action Required:</strong> The scheduled payment for this booking has failed. Please contact the customer immediately.
            </p>
            <h3 style="margin-top: 0;">Payment Details:</h3>
            <p><strong>Amount Due:</strong> <strong style="color: #c00;"><?php echo esc_html( $data['amount_due'] ); ?></strong></p>
            <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">
            <h3 style="margin-top: 0;">Booking Details:</h3>
            <p><strong>Accommodation:</strong> <?php echo esc_html( $data['room_list'] ); ?></p>
            <p><strong>Check-in:</strong> <?php echo esc_html( $data['check_in'] ); ?></p>
            <p><strong>Check-out:</strong> <?php echo esc_html( $data['check_out'] ); ?></p>
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