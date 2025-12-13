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
        $check_in = $booking->getCheckInDate()->format( 'F j, Y' );
        $check_out = $booking->getCheckOutDate()->format( 'F j, Y' );
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
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Booking Confirmed - Preelook Apartments</title>
        <style>
            @media only screen and (max-width: 600px) {
                .container { width: 100% !important; margin: 16px 0 !important; }
                .content-padding { padding: 24px 16px !important; }
                .header-padding { padding: 24px 16px !important; }
                .section-padding { padding: 16px !important; }
                h1 { font-size: 28px !important; }
                .total-price { font-size: 28px !important; }
            }
        </style>
    </head>
    <body style="margin: 0; padding: 0; font-family: 'DM Sans', Arial, sans-serif; line-height: 1.5;">
        
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td align="center">
                    <table class="container" width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 12px; overflow: hidden; max-width: 600px; margin: 0 auto; margin-top: 0 !important; margin-bottom: 0 !important;">
                        
                        <!-- Header -->
                        <tr>
                            <td class="header-padding" style="background: linear-gradient(135deg, <?php echo shaped_brand_color('primary'); ?> 0%, <?php echo shaped_brand_color('secondary'); ?> 100%); padding: 32px; text-align: center;">
                                <h1 style="margin: 0 0 8px 0; color: <?php echo shaped_brand_color('textInverse'); ?>; font-size: 32px; font-weight: 700; letter-spacing: -0.5px; line-height: 1.2;">Booking Confirmed!</h1>
                                <p style="margin: 0; color: <?php echo shaped_brand_color('textInverse'); ?>; font-size: 18px; opacity: 0.95;">Your seaside escape awaits</p>
                            </td>
                        </tr>
                        
                        <!-- Main Content -->
                        <tr>
                            <td class="content-padding" style="padding: 32px;">
                                
                                <!-- Greeting -->
                                <p style="margin: 0 0 24px 0; font-size: 16px; color: <?php echo shaped_brand_color('textPrimary'); ?>;">
                                    Dear <strong><?php echo esc_html( $data['customer_first'] ); ?></strong>,
                                </p>

                                <p style="margin: 0 0 32px 0; font-size: 16px; color: <?php echo shaped_brand_color('textMuted'); ?>; line-height: 1.5;">
                                    Thank you for choosing Preelook Apartments! We're excited to welcome you to the beautiful seaside town of Rijeka, just steps from the Adriatic Sea.
                                </p>
                                
                                <!-- Booking Details -->
                                <div style="background: #fffbf0; border-radius: 8px; padding: 24px; margin: 0 0 24px 0; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);">
                                    <h2 style="margin: 0 0 16px 0; font-size: 20px; color: <?php echo shaped_brand_color('textPrimary'); ?>; font-weight: 700;">📋 Booking Details</h2>
                                    
                                    <table width="100%" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td style="padding: 8px 0; color: <?php echo shaped_brand_color('textMuted'); ?>; font-size: 14px; font-weight: 600;">Booking ID:</td>
                                            <td style="padding: 8px 0; color: <?php echo shaped_brand_color('textPrimary'); ?>; font-size: 14px; font-weight: 600; text-align: right;">#<?php echo esc_html( $data['booking_id'] ); ?></td>
                                        </tr>
                                        <tr class="mobile-stack">
                                            <td style="padding: 8px 0; color: <?php echo shaped_brand_color('textMuted'); ?>; font-size: 14px; font-weight: 600;">Check-in:</td>
                                            <td class="mobile-right" style="padding: 8px 0; color: <?php echo shaped_brand_color('textPrimary'); ?>; font-size: 14px; text-align: right;">
                                                <strong><?php echo esc_html( $data['check_in'] ); ?></strong><br><span style="font-weight: 600;">from 16:00</span>
                                            </td>
                                        </tr>
                                        <tr class="mobile-stack">
                                            <td style="padding: 8px 0; color: <?php echo shaped_brand_color('textMuted'); ?>; font-size: 14px; font-weight: 600;">Check-out:</td>
                                            <td class="mobile-right" style="padding: 8px 0; color: <?php echo shaped_brand_color('textPrimary'); ?>; font-size: 14px; text-align: right;">
                                                <strong><?php echo esc_html( $data['check_out'] ); ?></strong><br><span style="font-weight: 600;">until 11:00</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 8px 0; color: <?php echo shaped_brand_color('textMuted'); ?>; font-size: 14px; font-weight: 600;">Accommodation:</td>
                                            <td style="padding: 8px 0; color: <?php echo shaped_brand_color('textPrimary'); ?>; font-size: 14px; font-weight: 600; text-align: right;"><?php echo esc_html( $data['room_list'] ); ?></td>
                                        </tr>
                                        <tr>
                                            <td colspan="2" style="padding: 16px 0 0 0; border-top: 1px solid <?php echo shaped_brand('colors.border.default'); ?>;">
                                                <table width="100%" cellpadding="0" cellspacing="0">
                                                    <tr>
                                                        <td style="color: <?php echo shaped_brand_color('textPrimary'); ?>; font-size: 16px; font-weight: 700;">Total Paid:</td>
                                                        <td style="text-align: right;">
                                                            <span class="total-price" style="color: <?php echo shaped_brand_color('primary'); ?>; font-size: 24px; font-weight: 700;"><?php echo esc_html( $data['total_paid'] ); ?></span>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                
                                <!-- Getting Here -->
                                <div style="background: #f8f8f8; border-radius: 8px; padding: 24px; margin: 0 0 24px 0; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);">
                                    <h3 style="margin: 0 0 16px 0; font-size: 18px; color: <?php echo shaped_brand_color('textPrimary'); ?>; font-weight: 700;">📍 Getting Here</h3>
                                    <p style="margin: 0 0 12px 0; font-size: 14px; color: <?php echo shaped_brand_color('textPrimary'); ?>; line-height: 1.5;">
                                        <strong>Address:</strong><br>
                                        <a href="https://maps.app.goo.gl/Zn5MTHb858g4aEUL8" style="color: <?php echo shaped_brand_color('primary'); ?>; font-weight: 600;">Preluk 4, 51000 Rijeka, Croatia</a>
                                    </p>
                                    <p style="margin: 0; font-size: 14px; color: <?php echo shaped_brand_color('textPrimary'); ?>; line-height: 1.5;">
                                        <strong>Check-in:</strong><br>
                                        <span style="color: <?php echo shaped_brand_color('textMuted'); ?>;">Visit us at the hotel reception upon arrival. We'll personally show you to your apartment and ensure you feel right at home.</span>
                                    </p>
                                </div>
                                
                                <!-- Explore the Area -->
                                <div style="background: #f8f8f8; border-radius: 8px; padding: 24px; margin: 0 0 24px 0; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);">
                                    <h3 style="margin: 0 0 16px 0; font-size: 18px; color: <?php echo shaped_brand_color('textPrimary'); ?>; font-weight: 700;">✨ Explore the Area</h3>
                                    <p style="margin: 0 0 16px 0; font-size: 14px; color: <?php echo shaped_brand_color('textMuted'); ?>; line-height: 1.5;">
                                        Discover the charm of Croatian coastline right from your doorstep:
                                    </p>

                                    <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0;">
                                        <tr class="mobile-stack">
                                            <td style="padding: 8px 0; vertical-align: top; width: 70%;">
                                                <a href="https://maps.app.goo.gl/KtRTD5gGpsNEPGQC9" style="color: <?php echo shaped_brand_color('primary'); ?>;"><strong style="font-size: 14px;">Volosko harbour</strong></a><br>
                                                <span style="color: <?php echo shaped_brand_color('textMuted'); ?>; font-size: 13px; line-height: 1.5;">Cozy fishing port with seafront cafés.</span>
                                            </td>
                                            <td class="mobile-right" style="padding: 8px 0; text-align: right; color: <?php echo shaped_brand_color('textPrimary'); ?>; font-size: 13px; font-weight: 600; vertical-align: top; width: 30%;">
                                                1.5 km
                                            </td>
                                        </tr>
                                        <tr class="mobile-stack">
                                            <td style="padding: 8px 0; vertical-align: top;">
                                                <a href="https://maps.app.goo.gl/1uMSDEgY3NebzP14A" style="color: <?php echo shaped_brand_color('primary'); ?>;"><strong style="font-size: 14px;">Opatija centre</strong></a><br>
                                                <span style="color: <?php echo shaped_brand_color('textMuted'); ?>; font-size: 13px; line-height: 1.5;">Stroll the Lungomare and grand villas.</span>
                                            </td>
                                            <td class="mobile-right" style="padding: 8px 0; text-align: right; color: <?php echo shaped_brand_color('textPrimary'); ?>; font-size: 13px; font-weight: 600; vertical-align: top;">
                                                4 km
                                            </td>
                                        </tr>
                                        <tr class="mobile-stack">
                                            <td style="padding: 8px 0; vertical-align: top;">
                                                <a href="https://maps.app.goo.gl/S4R7E1fhw7ZWtE6B8" style="color: <?php echo shaped_brand_color('primary'); ?>;"><strong style="font-size: 14px;">Kastav old town</strong></a><br>
                                                <span style="color: <?php echo shaped_brand_color('textMuted'); ?>; font-size: 13px; line-height: 1.5;">Medieval lanes, wine bars, and sunset vistas.</span>
                                            </td>
                                            <td class="mobile-right" style="padding: 8px 0; text-align: right; color: <?php echo shaped_brand_color('textPrimary'); ?>; font-size: 13px; font-weight: 600; vertical-align: top;">
                                                9 km
                                            </td>
                                        </tr>
                                        <tr class="mobile-stack">
                                            <td style="padding: 8px 0; vertical-align: top;">
                                                <a href="https://maps.app.goo.gl/p6o888sxkrCvTF9TA" style="color: <?php echo shaped_brand_color('primary'); ?>;"><strong style="font-size: 14px;">Rijeka city centre</strong></a><br>
                                                <span style="color: <?php echo shaped_brand_color('textMuted'); ?>; font-size: 13px; line-height: 1.5;">Korzo buzzes with shops and markets.</span>
                                            </td>
                                            <td class="mobile-right" style="padding: 8px 0; text-align: right; color: <?php echo shaped_brand_color('textPrimary'); ?>; font-size: 13px; font-weight: 600; vertical-align: top;">
                                                10 km
                                            </td>
                                        </tr>
                                        <tr class="mobile-stack">
                                            <td style="padding: 8px 0; vertical-align: top;">
                                                <a href="https://maps.app.goo.gl/VHFgMMstrHfo81Ht8" style="color: <?php echo shaped_brand_color('primary'); ?>;"><strong style="font-size: 14px;">Trsat Castle</strong></a><br>
                                                <span style="color: <?php echo shaped_brand_color('textMuted'); ?>; font-size: 13px; line-height: 1.5;">Hill-top fortress with sweeping bay views.</span>
                                            </td>
                                            <td class="mobile-right" style="padding: 8px 0; text-align: right; color: <?php echo shaped_brand_color('textPrimary'); ?>; font-size: 13px; font-weight: 600; vertical-align: top;">
                                                14 km
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                
                                <!-- Contact & Next Steps -->
                                <div style="background: #f8f8f8; border-radius: 8px; padding: 24px; margin: 0 0 32px 0; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);">
                                    <h3 style="margin: 0 0 16px 0; font-size: 18px; color: <?php echo shaped_brand_color('textPrimary'); ?>; font-weight: 700;">📞 Need Help?</h3>
                                    <p style="margin: 0 0 16px 0; font-size: 14px; color: <?php echo shaped_brand_color('textMuted'); ?>; line-height: 1.5;">
                                        We're here to ensure your perfect stay. Contact us anytime:
                                    </p>
                                    <p style="margin: 0; font-size: 14px; color: <?php echo shaped_brand_color('textPrimary'); ?>; line-height: 1.7;">
                                        <strong>Phone:</strong> <a href="tel:+38591613309" style="color: <?php echo shaped_brand_color('primary'); ?>; font-weight: 600;">+385 91 613 3609</a><br>
                                        <strong>Email:</strong> <a href="mailto:info@preelook.com" style="color: <?php echo shaped_brand_color('primary'); ?>; font-weight: 600;">info@preelook.com</a>
                                    </p>
                                </div>

                                <!-- Closing Message -->
                                <div style="text-align: center; padding: 24px; background: #fffbf0; border-radius: 8px; margin: 0;">
                                    <p style="margin: 0 0 12px 0; font-size: 16px; color: <?php echo shaped_brand_color('textPrimary'); ?>; line-height: 1.5;">
                                        We're looking forward to hosting you in beautiful Rijeka!
                                    </p>
                                    <p style="margin: 0; font-size: 16px; color: <?php echo shaped_brand_color('primary'); ?>; font-weight: 600; line-height: 1.5;">
                                        Warm regards,<br>
                                        The Preelook Team
                                    </p>
                                </div>
                                
                            </td>
                        </tr>
                        
                        <!-- Footer -->
                        <tr>
                            <td style="background: #26272C; padding: 24px 32px; text-align: center;">
                                <p style="margin: 0 0 8px 0; color: #ffffff; font-size: 14px; line-height: 1.5;">
                                    Preelook Apartments | Rijeka, Croatia
                                </p>
                                <p style="margin: 0; color: #999999; font-size: 12px; line-height: 1.5;">
                                    This is an automated confirmation email.
                                </p>
                            </td>
                        </tr>
                        
                    </table>
                </td>
            </tr>
        </table>
        
    </body>
</html>
    <?php
    return ob_get_clean();
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
        $check_in = $booking->getCheckInDate()->format('F j, Y');
        $check_out = $booking->getCheckOutDate()->format('F j, Y');
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
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Reservation Confirmed - Preelook Apartments</title>
    </head>
    <body style="margin: 0; padding: 0; font-family: 'DM Sans', Arial, sans-serif; line-height: 1.5;">
        
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td align="center">
                    <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 12px; overflow: hidden; max-width: 600px; margin: 0 auto;">
                        
                        <!-- Header -->
                        <tr>
                            <td style="background: linear-gradient(135deg, <?php echo shaped_brand_color('primary'); ?> 0%, <?php echo shaped_brand_color('secondary'); ?> 100%); padding: 32px; text-align: center;">
                                <h1 style="margin: 0 0 8px 0; color: <?php echo shaped_brand_color('textInverse'); ?>; font-size: 32px; font-weight: 700;">Reservation Confirmed!</h1>
                                <p style="margin: 0; color: <?php echo shaped_brand_color('textInverse'); ?>; font-size: 18px; opacity: 0.95;">Card saved successfully</p>
                            </td>
                        </tr>
                        
                        <!-- Main Content -->
                        <tr>
                            <td style="padding: 32px;">
                                
                                <p style="margin: 0 0 24px 0; font-size: 16px; color: <?php echo shaped_brand_color('textPrimary'); ?>;">
                                    Dear <strong><?php echo esc_html($data['customer_first']); ?></strong>,
                                </p>

                                <p style="margin: 0 0 32px 0; font-size: 16px; color: <?php echo shaped_brand_color('textMuted'); ?>; line-height: 1.5;">
                                    Your reservation at Preelook Apartments has been confirmed! Your card has been securely saved.
                                </p>

                                <!-- Booking Summary -->
                                <div style="background: #f8f8f8; border-radius: 8px; padding: 24px; margin: 0 0 24px 0;">
                                    <h2 style="margin: 0 0 16px 0; font-size: 20px; color: <?php echo shaped_brand_color('textPrimary'); ?>; font-weight: 700;">📋 Booking #<?php echo esc_html($data['booking_id']); ?></h2>

                                    <table width="100%" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td style="padding: 8px 0; color: <?php echo shaped_brand_color('textMuted'); ?>; font-size: 14px;">Check-in:</td>
                                            <td style="padding: 8px 0; color: <?php echo shaped_brand_color('textPrimary'); ?>; font-size: 14px; text-align: right;">
                                                <strong><?php echo esc_html($data['check_in']); ?></strong>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 8px 0; color: <?php echo shaped_brand_color('textMuted'); ?>; font-size: 14px;">Check-out:</td>
                                            <td style="padding: 8px 0; color: <?php echo shaped_brand_color('textPrimary'); ?>; font-size: 14px; text-align: right;">
                                                <strong><?php echo esc_html($data['check_out']); ?></strong>
                                            </td>
                                        </tr>
                                    </table>
                                </div>

                                <!-- Payment Info -->
                                <div style="background: #fffbf0; border-radius: 8px; padding: 24px; margin: 0 0 24px 0; text-align: center;">
                                    <p style="margin: 0 0 12px 0; font-size: 16px; color: <?php echo shaped_brand_color('textPrimary'); ?>;">
                                        We'll charge <strong style="color: <?php echo shaped_brand_color('primary'); ?>; font-size: 20px;">€<?php echo esc_html($data['amount']); ?></strong>
                                    </p>
                                    <p style="margin: 0; font-size: 14px; color: <?php echo shaped_brand_color('textMuted'); ?>;">
                                        on <strong><?php echo esc_html($data['charge_date']); ?></strong><br>
                                        (7 days before your arrival)
                                    </p>
                                </div>

                                <!-- Manage Link -->
                                <div style="text-align: center; margin: 0 0 24px 0;">
                                    <a href="<?php echo home_url('/manage-booking/?booking_id=' . $data['booking_id'] . '&token=' . md5($data['booking_id'] . $data['customer_email'])); ?>"
                                       style="display: inline-block; background: <?php echo shaped_brand_color('primary'); ?>; color: <?php echo shaped_brand_color('textInverse'); ?>; padding: 14px 30px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 15px; letter-spacing:0.2px; text-transform:uppercase;">
                                        Manage Booking
                                    </a>
                                    <p style="margin: 12px 0 0 0; font-size: 13px; color: #999999;">
                                        Free cancellation until <?php echo esc_html($data['charge_date']); ?>
                                    </p>
                                </div>

                                <p style="margin: 0; font-size: 14px; color: <?php echo shaped_brand_color('textMuted'); ?>; text-align: center; line-height: 1.5;">
                                    You'll receive full booking details after payment is processed.<br>
                                    Questions? Contact us at <a href="tel:+385916133609" style="color: <?php echo shaped_brand_color('primary'); ?>;">+385 91 613 3609</a>
                                </p>
                                
                            </td>
                        </tr>
                        
                        <!-- Footer -->
                        <tr>
                            <td style="background: #26272C; padding: 24px; text-align: center;">
                                <p style="margin: 0; color: #ffffff; font-size: 14px;">
                                    Preelook Apartments | Rijeka, Croatia
                                </p>
                            </td>
                        </tr>
                        
                    </table>
                </td>
            </tr>
        </table>
        
    </body>
    </html>
    <?php
    return ob_get_clean();
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
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Booking Cancelled - Preelook Apartments</title>
    </head>
    <body style="margin: 0; padding: 0; font-family: 'DM Sans', Arial, sans-serif; line-height: 1.5;">
        
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td align="center">
                    <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 12px; overflow: hidden; max-width: 600px; margin: 0 auto;">
                        
                        <!-- Header -->
                        <tr>
                            <td style="background: linear-gradient(135deg, <?php echo shaped_brand_color('primary'); ?> 0%, <?php echo shaped_brand_color('secondary'); ?> 100%); padding: 32px 30px; text-align: center;">
                                <h1 style="margin: 0 0 8px 0; color: <?php echo shaped_brand_color('textInverse'); ?>; font-size: 32px; font-weight: 700;">Booking Cancelled</h1>
                                <p style="margin: 0; color: <?php echo shaped_brand_color('textInverse'); ?>; font-size: 18px; opacity: 0.95;">We've processed your cancellation</p>
                            </td>
                        </tr>
                        
                        <!-- Main Content -->
                        <tr>
                            <td style="padding: 32px 30px;">
                                
                                <p style="margin: 0 0 24px 0; font-size: 16px; color: <?php echo shaped_brand_color('textPrimary'); ?>;">
                                    Dear <strong><?php echo esc_html($data['customer_first']); ?></strong>,
                                </p>

                                <p style="margin: 0 0 32px 0; font-size: 16px; color: <?php echo shaped_brand_color('textMuted'); ?>; line-height: 1.6;">
                                    Your booking <strong>#<?php echo esc_html($data['booking_id']); ?></strong> has been successfully cancelled.
                                </p>

                            <?php if ($data['is_refundable']): ?>
                            <!-- Refund Details -->
                            <div style="background: #fffbf0; border-radius: 8px; padding: 24px; margin: 0 0 24px 0;">
                                <h2 style="margin: 0 0 20px 0; font-size: 20px; color: <?php echo shaped_brand_color('textPrimary'); ?>; font-weight: 700;">Cancellation Confirmed</h2>
                                <p style="margin: 0; color: <?php echo shaped_brand_color('textPrimary'); ?>; font-size: 14px;">
                                    You successfully cancelled your Preelook Apartments & Rooms booking.
                                    Your card will not be charged.
                                </p>
                            </div>
                            <?php endif; ?>

                                <p style="margin: 0; font-size: 16px; color: <?php echo shaped_brand_color('primary'); ?>; font-weight: 600; text-align: center;">
                                    We hope to welcome you to Preelook in the future.<br>
                                    The Preelook Team
                                </p>
                                
                            </td>
                        </tr>
                        
                        <!-- Footer -->
                        <tr>
                            <td style="background: #26272C; padding: 24px 30px; text-align: center;">
                                <p style="margin: 0 0 8px 0; color: #ffffff; font-size: 14px;">
                                    Preelook Apartments | Rijeka, Croatia
                                </p>
                                <p style="margin: 0; color: #999999; font-size: 12px;">
                                    This is an automated cancellation confirmation.
                                </p>
                            </td>
                        </tr>
                        
                    </table>
                </td>
            </tr>
        </table>
        
    </body>
    </html>
    <?php
    return ob_get_clean();
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
        $check_in = $booking->getCheckInDate()->format('F j, Y');
        $check_out = $booking->getCheckOutDate()->format('F j, Y');
        $currency = MPHB()->settings()->currency()->getCurrencySymbol();
        $deposit_amount = get_post_meta($booking_id, '_shaped_deposit_paid', true);
        $balance_due = get_post_meta($booking_id, '_shaped_balance_due', true);

        $room_type_ids = $booking->getReservedRoomTypeIds();
        $room_names = array_map('get_the_title', $room_type_ids);
        $room_list = implode(', ', $room_names);

        $to = $customer->getEmail();
        $subject = 'Deposit Received #' . $booking_id . ' - Preelook Apartments';

        // Plain text message
        $message = "Dear " . $customer->getFirstName() . ",\n\n";
        $message .= "Thank you! We've successfully received your deposit payment for booking #" . $booking_id . ".\n\n";
        $message .= "DEPOSIT DETAILS:\n";
        $message .= "Deposit Paid: " . $currency . number_format($deposit_amount, 2) . "\n";
        $message .= "Balance Due: " . $currency . number_format($balance_due, 2) . "\n";
        $message .= "Payment Due: On arrival at check-in\n\n";
        $message .= "BOOKING DETAILS:\n";
        $message .= "Booking ID: #" . $booking_id . "\n";
        $message .= "Check-in: " . $check_in . " (from 16:00)\n";
        $message .= "Check-out: " . $check_out . " (until 11:00)\n";
        $message .= "Accommodation: " . $room_list . "\n\n";
        $message .= "GETTING HERE:\n";
        $message .= "Address: Preluk 4, 51000 Rijeka, Croatia\n";
        $message .= "Google Maps: https://maps.app.goo.gl/Zn5MTHb858g4aEUL8\n\n";
        $message .= "NEED HELP?\n";
        $message .= "Phone: +385 91 613 3609\n";
        $message .= "Email: info@preelook.com\n\n";
        $message .= "We're looking forward to welcoming you to Preelook Apartments!\n\n";
        $message .= "Warm regards,\n";
        $message .= "The Preelook Team";

        $headers = [
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

/**
 * Send payment confirmation email to guest (delayed charge succeeded)
 * Sent when scheduled charge is successfully processed
 *
 * @param int $booking_id The booking ID
 * @return bool True if sent successfully
 */
function shaped_send_payment_confirmation_email($booking_id) {
    try {
        error_log('[Shaped Email] Starting payment confirmation for booking #' . $booking_id);

        $booking = MPHB()->getBookingRepository()->findById($booking_id, true);
        if (!$booking) return false;

        $customer = $booking->getCustomer();
        if (!$customer || !$customer->getEmail()) return false;

        $check_in = $booking->getCheckInDate()->format('F j, Y');
        $currency = MPHB()->settings()->currency()->getCurrencySymbol();
        $paid_amount = get_post_meta($booking_id, '_mphb_paid_amount', true);

        $to = $customer->getEmail();
        $subject = 'Payment Confirmed #' . $booking_id . ' - Preelook Apartments';

        // Plain text message
        $message = "Dear " . $customer->getFirstName() . ",\n\n";
        $message .= "Your payment has been successfully processed for booking #" . $booking_id . ".\n\n";
        $message .= "PAYMENT DETAILS:\n";
        $message .= "Amount Charged: " . $currency . number_format($paid_amount, 2) . "\n";
        $message .= "Booking ID: #" . $booking_id . "\n";
        $message .= "Check-in: " . $check_in . "\n\n";
        $message .= "Your booking is now fully confirmed. We look forward to welcoming you!\n\n";
        $message .= "GETTING HERE:\n";
        $message .= "Address: Preluk 4, 51000 Rijeka, Croatia\n";
        $message .= "Google Maps: https://maps.app.goo.gl/Zn5MTHb858g4aEUL8\n";
        $message .= "Check-in time: from 16:00\n\n";
        $message .= "CONTACT US:\n";
        $message .= "Phone: +385 91 613 3609\n";
        $message .= "Email: info@preelook.com\n\n";
        $message .= "Warm regards,\n";
        $message .= "The Preelook Team";

        $headers = [
            'From: Preelook Apartments <info@preelook.com>',
            'Reply-To: info@preelook.com'
        ];

        $sent = wp_mail($to, $subject, $message, $headers);

        if ($sent) {
            error_log('[Shaped Email] Payment confirmation sent to ' . $to);
        }

        return $sent;

    } catch (Exception $e) {
        error_log('[Shaped Email] ERROR in payment confirmation: ' . $e->getMessage());
        return false;
    }
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

        $check_in = $booking->getCheckInDate()->format('F j, Y');
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

        if ( ( $paid_amount > 0 || $payment_verified ) && ! get_post_meta( $post->ID, '_shaped_confirmation_sent', true ) ) {
            error_log( '[Shaped Email] Booking confirmed with payment, sending email' );
            shaped_send_confirmation_email( $post->ID );
        }
    }
}, 20, 3 );