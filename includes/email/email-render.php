<?php
/**
 * Email Render Helpers
 *
 * High-level rendering functions for building complete emails.
 * These functions combine base layout with blocks to create full email templates.
 *
 * @package Shaped_Core
 * @subpackage Email
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load dependencies
require_once __DIR__ . '/email-base.php';
require_once __DIR__ . '/email-blocks.php';

/**
 * Render a complete email
 *
 * Main entry point for building emails. Combines base layout with provided content.
 *
 * @param array $args {
 *     Email configuration.
 *
 *     @type string $title       Page title for <title> tag
 *     @type string $header      Header main text
 *     @type string $subtitle    Header subtitle
 *     @type string $content     Main email content HTML
 *     @type string $footer_text Footer disclaimer text
 * }
 * @return string Complete email HTML
 */
function shaped_render_email($args) {
    $defaults = [
        'title'       => 'Preelook Apartments',
        'header'      => '',
        'subtitle'    => '',
        'content'     => '',
        'footer_text' => 'This is an automated confirmation email.',
    ];

    $args = wp_parse_args($args, $defaults);

    $html = shaped_email_start($args['title']);
    $html .= shaped_email_header($args['header'], $args['subtitle']);
    $html .= shaped_email_content_start();
    $html .= $args['content'];
    $html .= shaped_email_content_end();
    $html .= shaped_email_footer($args['footer_text']);
    $html .= shaped_email_end();

    return $html;
}

/**
 * Render booking details card
 *
 * Standard booking details section used in confirmation emails.
 *
 * @param array $data {
 *     Booking information.
 *
 *     @type int    $booking_id    Booking ID
 *     @type string $check_in      Check-in date (formatted)
 *     @type string $check_out     Check-out date (formatted)
 *     @type string $room_list     Accommodation name(s)
 *     @type string $total_paid    Total paid amount (formatted with currency)
 *     @type string $check_in_time Optional check-in time (default: "from 16:00")
 *     @type string $check_out_time Optional check-out time (default: "until 11:00")
 * }
 * @return string Booking details card HTML
 */
function shaped_email_render_booking_details($data) {
    $check_in_time = isset($data['check_in_time']) ? $data['check_in_time'] : 'from 16:00';
    $check_out_time = isset($data['check_out_time']) ? $data['check_out_time'] : 'until 11:00';

    $html = shaped_email_block_card_start('highlight');
    $html .= shaped_email_block_section_title('Booking Details', '📋');
    $html .= shaped_email_block_rows_start();
    $html .= shaped_email_block_row('Booking ID:', '#' . $data['booking_id'], ['bold_value' => true]);
    $html .= shaped_email_block_row('Check-in:', $data['check_in'], [
        'bold_value'   => true,
        'sub_text'     => $check_in_time,
    ]);
    $html .= shaped_email_block_row('Check-out:', $data['check_out'], [
        'bold_value'   => true,
        'sub_text'     => $check_out_time,
    ]);
    $html .= shaped_email_block_row('Accommodation:', $data['room_list'], ['bold_value' => true]);

    if (!empty($data['total_paid'])) {
        $html .= shaped_email_block_total_divider();
        $html .= shaped_email_block_total_row('Total Paid:', $data['total_paid']);
    }

    $html .= shaped_email_block_rows_end();
    $html .= shaped_email_block_card_end();

    return $html;
}

/**
 * Render deposit booking details card
 *
 * Booking details section for deposit payments, showing deposit paid and balance due.
 *
 * @param array $data {
 *     Booking information.
 *
 *     @type int    $booking_id    Booking ID
 *     @type string $check_in      Check-in date (formatted)
 *     @type string $check_out     Check-out date (formatted)
 *     @type string $room_list     Accommodation name(s)
 *     @type string $deposit_paid  Deposit amount paid (formatted with currency)
 *     @type string $balance_due   Balance due on arrival (formatted with currency)
 *     @type string $total_amount  Total booking amount (formatted with currency)
 *     @type string $check_in_time Optional check-in time (default: "from 16:00")
 *     @type string $check_out_time Optional check-out time (default: "until 11:00")
 * }
 * @return string Deposit booking details card HTML
 */
function shaped_email_render_deposit_details($data) {
    $check_in_time = isset($data['check_in_time']) ? $data['check_in_time'] : 'from 16:00';
    $check_out_time = isset($data['check_out_time']) ? $data['check_out_time'] : 'until 11:00';

    $success = shaped_brand_color('success') ?: '#4C9155';
    $primary = shaped_brand_color('primary') ?: '#D1AF5D';
    $text_primary = shaped_brand_color('textPrimary') ?: '#26272C';

    $html = shaped_email_block_card_start('highlight');
    $html .= shaped_email_block_section_title('Booking Details', '📋');
    $html .= shaped_email_block_rows_start();
    $html .= shaped_email_block_row('Booking ID:', '#' . $data['booking_id'], ['bold_value' => true]);
    $html .= shaped_email_block_row('Check-in:', $data['check_in'], [
        'bold_value'   => true,
        'sub_text'     => $check_in_time,
    ]);
    $html .= shaped_email_block_row('Check-out:', $data['check_out'], [
        'bold_value'   => true,
        'sub_text'     => $check_out_time,
    ]);
    $html .= shaped_email_block_row('Accommodation:', $data['room_list'], ['bold_value' => true]);

    // Deposit payment breakdown
    $html .= shaped_email_block_total_divider();
    $html .= '<tr><td colspan="2" style="padding: 16px 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
            <span style="font-size: 14px; color: ' . $text_primary . ';">Deposit Paid: </span>
            <strong style="font-size: 16px; color: ' . $primary . ';">' . esc_html($data['deposit_paid']) . '</strong>
        </div>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
            <span style="font-size: 14px; color: ' . $text_primary . ';">Balance Due on Arrival:</span>
            <strong style="font-size: 16px; color: ' . $text_primary . ';">' . esc_html($data['balance_due']) . '</strong>
        </div>
        <div style="padding-top: 8px; border-top: 1px solid #e0e0e0; margin-top: 8px;"></div>
    </td></tr>';
    $html .= shaped_email_block_total_row('Total Booking Amount:', $data['total_amount']);

    $html .= shaped_email_block_rows_end();
    $html .= shaped_email_block_card_end();

    return $html;
}

/**
 * Render "Getting Here" section
 *
 * Standard location and check-in instructions card.
 *
 * @param array $options {
 *     Optional customizations.
 *
 *     @type string $address      Address text (default: Preluk 4, 51000 Rijeka, Croatia)
 *     @type string $maps_url     Google Maps URL
 *     @type string $instructions Check-in instructions text
 * }
 * @return string Getting here card HTML
 */
function shaped_email_render_getting_here($options = []) {
    $defaults = [
        'address'      => 'Preluk 4, 51000 Rijeka, Croatia',
        'maps_url'     => 'https://maps.app.goo.gl/Zn5MTHb858g4aEUL8',
        'instructions' => 'Visit us at the hotel reception upon arrival. We\'ll personally show you to your apartment and ensure you feel right at home.',
    ];

    $options = wp_parse_args($options, $defaults);

    $text_primary = shaped_brand_color('textPrimary') ?: '#26272C';
    $text_muted = shaped_brand_color('textMuted') ?: '#666666';

    $html = shaped_email_block_card_start('neutral');
    $html .= shaped_email_block_section_title_h3('Getting Here', '📍');
    $html .= shaped_email_block_address('Address:', $options['address'], $options['maps_url']);

    ob_start();
    ?>
                                    <p style="margin: 0; font-size: 16px; color: <?php echo $text_primary; ?>; line-height: 1.6;">
                                        <strong>Check-in:</strong><br>
                                        <span style="color: <?php echo $text_muted; ?>;"><?php echo esc_html($options['instructions']); ?></span>
                                    </p>
    <?php
    $html .= ob_get_clean();

    $html .= shaped_email_block_card_end();

    return $html;
}

/**
 * Render "Explore the Area" section
 *
 * Card with nearby attractions and distances.
 *
 * @param array $locations Array of location items, each with: name, description, distance, url
 * @return string Explore area card HTML
 */
function shaped_email_render_explore_area($locations = []) {
    // Default locations if none provided
    if (empty($locations)) {
        $locations = [
            [
                'name'        => 'Volosko harbour',
                'description' => 'Cozy fishing port with seafront cafés.',
                'distance'    => '1.5 km',
                'url'         => 'https://maps.app.goo.gl/KtRTD5gGpsNEPGQC9',
            ],
            [
                'name'        => 'Opatija centre',
                'description' => 'Stroll the Lungomare and grand villas.',
                'distance'    => '4 km',
                'url'         => 'https://maps.app.goo.gl/1uMSDEgY3NebzP14A',
            ],
            [
                'name'        => 'Kastav old town',
                'description' => 'Medieval lanes, wine bars, and sunset vistas.',
                'distance'    => '9 km',
                'url'         => 'https://maps.app.goo.gl/S4R7E1fhw7ZWtE6B8',
            ],
            [
                'name'        => 'Rijeka city centre',
                'description' => 'Korzo buzzes with shops and markets.',
                'distance'    => '10 km',
                'url'         => 'https://maps.app.goo.gl/p6o888sxkrCvTF9TA',
            ],
            [
                'name'        => 'Trsat Castle',
                'description' => 'Hill-top fortress with sweeping bay views.',
                'distance'    => '14 km',
                'url'         => 'https://maps.app.goo.gl/VHFgMMstrHfo81Ht8',
            ],
        ];
    }

    $text_muted = shaped_brand_color('textMuted') ?: '#666666';

    $html = shaped_email_block_card_start('neutral');
    $html .= shaped_email_block_section_title_h3('Explore the Area', '✨');

    ob_start();
    ?>
                                    <p style="margin: 0 0 16px 0; font-size: 16px; color: <?php echo $text_muted; ?>; line-height: 1.6;">
                                        Discover the charm of Croatian coastline right from your doorstep:
                                    </p>
    <?php
    $html .= ob_get_clean();

    $html .= shaped_email_block_rows_start();
    foreach ($locations as $location) {
        $html .= shaped_email_block_explore_item(
            $location['name'],
            $location['description'],
            $location['distance'],
            $location['url']
        );
    }
    $html .= shaped_email_block_rows_end();

    $html .= shaped_email_block_card_end();

    return $html;
}

/**
 * Render "Need Help?" contact section
 *
 * Standard contact information card.
 *
 * @param array $options {
 *     Optional customizations.
 *
 *     @type string $title Title text (default: "Need Help?")
 *     @type string $intro Intro text before contact info
 *     @type string $phone Phone number
 *     @type string $email Email address
 * }
 * @return string Contact card HTML
 */
function shaped_email_render_contact($options = []) {
    $defaults = [
        'title' => 'Need Help?',
        'intro' => 'We\'re here to ensure your perfect stay. Contact us anytime:',
        'phone' => '+385 91 613 3609',
        'email' => 'info@preelook.com',
    ];

    $options = wp_parse_args($options, $defaults);

    $text_muted = shaped_brand_color('textMuted') ?: '#666666';

    $html = shaped_email_block_card_start('neutral', '32px');
    $html .= shaped_email_block_section_title_h3($options['title'], '📞');

    ob_start();
    ?>
                                    <p style="margin: 0 0 16px 0; font-size: 16px; color: <?php echo $text_muted; ?>; line-height: 1.6;">
                                        <?php echo esc_html($options['intro']); ?>
                                    </p>
    <?php
    $html .= ob_get_clean();

    $html .= shaped_email_block_contact($options['phone'], $options['email']);
    $html .= shaped_email_block_card_end();

    return $html;
}

/**
 * Render standard closing message
 *
 * Warm closing message used at the end of guest emails.
 *
 * @param string $message Optional custom message
 * @return string Closing HTML
 */
function shaped_email_render_closing($message = "We're looking forward to hosting you in beautiful Rijeka!") {
    return shaped_email_block_closing($message, "Warm regards,<br>The Preelook Team", 'highlight');
}

/**
 * Render simple booking summary card
 *
 * Simplified booking info for reservation emails.
 *
 * @param array $data {
 *     Booking information.
 *
 *     @type int    $booking_id Booking ID
 *     @type string $check_in   Check-in date
 *     @type string $check_out  Check-out date
 * }
 * @return string Booking summary HTML
 */
function shaped_email_render_booking_summary($data) {
    $html = shaped_email_block_card_start('neutral');
    $html .= shaped_email_block_section_title('Booking #' . $data['booking_id'], '📋');
    $html .= shaped_email_block_rows_start();
    $html .= shaped_email_block_row('Check-in:', $data['check_in'], ['bold_value' => true]);
    $html .= shaped_email_block_row('Check-out:', $data['check_out'], ['bold_value' => true]);
    $html .= shaped_email_block_rows_end();
    $html .= shaped_email_block_card_end();

    return $html;
}

/**
 * Render cancellation confirmation card
 *
 * Card for cancellation emails showing refund status.
 *
 * @param bool $is_refundable Whether the booking was refundable
 * @return string Cancellation card HTML
 */
function shaped_email_render_cancellation_card($is_refundable = false) {
    if (!$is_refundable) {
        return '';
    }

    $text_primary = shaped_brand_color('textPrimary') ?: '#26272C';

    $html = shaped_email_block_card_start('highlight');
    $html .= shaped_email_block_section_title('Cancellation Confirmed', '');

    ob_start();
    ?>
                                    <p style="margin: 0; color: <?php echo $text_primary; ?>; font-size: 14px; line-height: 1.6;">
                                        You successfully cancelled your Preelook Apartments & Rooms booking.
                                        Your card will not be charged.
                                    </p>
    <?php
    $html .= ob_get_clean();
    $html .= shaped_email_block_card_end();

    return $html;
}

/**
 * Generate manage booking URL
 *
 * Creates the URL for the manage booking page with security token.
 *
 * @param int    $booking_id    Booking ID
 * @param string $customer_email Customer email for token generation
 * @return string Manage booking URL
 */
function shaped_email_get_manage_url($booking_id, $customer_email) {
    $token = md5($booking_id . $customer_email);
    return home_url('/manage-booking/?booking_id=' . $booking_id . '&token=' . $token);
}
