<?php
/**
 * Email Templates System
 *
 * Consolidated email rendering system with full brand configuration support.
 * All templates use brand.json configuration for company details, colors, and content.
 *
 * @package Shaped_Core
 * @subpackage Email
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ==========================================================================
   CONFIGURATION HELPERS
   ========================================================================== */

/**
 * Get email configuration value with fallback
 *
 * @param string $key Configuration key (dot notation supported)
 * @param mixed $fallback Fallback value if not found
 * @return mixed
 */
function shaped_email_config($key, $fallback = '') {
    // Map common keys to brand.json paths
    $paths = [
        'company_name'      => 'company.name',
        'company_tagline'   => 'company.tagline',
        'company_location'  => 'company.location',
        'from_name'         => 'email.fromName',
        'from_email'        => 'email.fromEmail',
        'phone'             => 'contact.phone',
        'email'             => 'contact.email',
        'address'           => 'contact.address',
        'maps_url'          => 'contact.mapsUrl',
        'footer_text'       => 'email.footerText',
        'check_in_instructions' => 'email.checkInInstructions',
        'check_in_time'     => 'email.checkInTime',
        'check_out_time'    => 'email.checkOutTime',
        'closing_message'   => 'email.closingMessage',
        'signature'         => 'email.signature',
        'logo_url'          => 'email.logoUrl',
    ];

    // Check if key has a mapped path
    $path = isset($paths[$key]) ? $paths[$key] : $key;

    // Get value from brand config
    $value = shaped_brand($path, null);

    return $value !== null ? $value : $fallback;
}

/**
 * Get color with fallback
 *
 * @param string $key Color key
 * @param string $fallback Fallback hex color
 * @return string
 */
function shaped_email_color($key, $fallback = '#26272C') {
    $color = shaped_brand_color($key);
    return $color !== null ? $color : $fallback;
}

/**
 * Format address from brand config
 *
 * Handles both string and object address formats from brand.json
 *
 * @param mixed $address Address string or object
 * @return string Formatted address string
 */
function shaped_email_format_address($address) {
    // If address is already a string, return it
    if (is_string($address)) {
        return $address;
    }

    // If address is an array/object, format it
    if (is_array($address)) {
        $parts = [];

        if (!empty($address['street'])) {
            $parts[] = $address['street'];
        }
        if (!empty($address['city'])) {
            $city_part = $address['city'];
            if (!empty($address['postalCode'])) {
                $city_part = $address['postalCode'] . ' ' . $city_part;
            }
            $parts[] = $city_part;
        }
        if (!empty($address['country'])) {
            $parts[] = $address['country'];
        }

        return implode(', ', $parts);
    }

    return 'Address not configured';
}

/* ==========================================================================
   BOOKING DATA HELPERS
   ========================================================================== */

/**
 * Get the rate name for a booking
 *
 * Retrieves the rate title chosen by the guest at checkout from MPHB's
 * reserved room → rate entity chain. Returns the rate post title
 * (e.g., "Room only", "Breakfast included"), NOT the description.
 *
 * @param int $booking_id The booking ID
 * @return string Rate name (e.g., "Room only" or "Breakfast included") or empty string
 */
function shaped_get_booking_rate_name($booking_id) {
    try {
        $booking = MPHB()->getBookingRepository()->findById($booking_id, true);
        if (!$booking) return '';

        $reserved_rooms = $booking->getReservedRooms();
        if (empty($reserved_rooms)) return '';

        $room = reset($reserved_rooms);
        if (!method_exists($room, 'getRateId') || !$room->getRateId()) return '';

        // getRateTitle() returns the rate's WP post title
        // e.g., "Room only" or "Breakfast included"
        if (method_exists($room, 'getRateTitle')) {
            $title = $room->getRateTitle();
            if (!empty($title)) return $title;
        }

        // Fallback: get title directly from rate post
        $rate_id = $room->getRateId();
        $title = get_the_title($rate_id);
        return $title ?: '';

    } catch (\Throwable $e) {
        error_log('[Shaped Email] Failed to get rate name for booking #' . $booking_id . ': ' . $e->getMessage());
        return '';
    }
}

/* ==========================================================================
   BASE STYLES
   ========================================================================== */

/**
 * Get email base styles
 *
 * @return string CSS styles
 */
function shaped_email_get_styles() {
    $text_primary = shaped_email_color('textPrimary', '#26272C');

    ob_start();
    ?>
    <style type="text/css">
        /* Base Typography */
        body, p, td, a, li {
            font-family: 'DM Sans', Arial, sans-serif;
            font-size: 16px !important;
            line-height: 1.6;
        }

        /* Reset */
        body {
            margin: 0;
            padding: 0;
            width: 100%;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }

        table {
            border-collapse: collapse;
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }

        img {
            border: 0;
            height: auto;
            line-height: 100%;
            outline: none;
            text-decoration: none;
            -ms-interpolation-mode: bicubic;
        }

        /* Link styling */
        a {
            color: <?php echo $text_primary; ?>;
        }

        /* Heading margins */
        h2, h3 {
            margin-top: 0!important;
        }

        h2 {
            margin-bottom: 24px;
        }

        h3 {
            margin-bottom: 24px;
        }

        /* Mobile Responsive */
        @media only screen and (max-width: 600px) {
            .container {
                width: 100% !important;
                margin: 0 !important;
                border-radius: 0 !important;
            }

            body, p, td, a, li {
                font-size: 16px !important;
            }

            .content-padding {
                padding: 24px 8px !important;
            }

            .header-padding {
                padding: 24px 16px !important;
            }

            .section-padding {
                padding: 20px 16px !important;
            }

            .card-padding {
                padding: 20px !important;
            }

            h1 {
                font-size: 26px !important;
            }

            h2 {
                font-size: 22px !important;
                margin-bottom: 16px !important;
            }

            h3 {
                font-size: 18px !important;
                margin-bottom: 16px !important;
            }

            .total-price {
                font-size: 24px !important;
            }

            .mobile-stack {
                display: block !important;
                width: 100% !important;
            }

            .mobile-stack td {
                display: block !important;
                width: 100% !important;
                text-align: left !important;
                padding: 6px 0 !important;
            }

            .mobile-right {
                text-align: left !important;
            }

            .detail-row td {
                padding: 10px 0 !important;
            }

            .footer-padding {
                padding: 20px 16px !important;
            }
        }

        /* Dark Mode Support */
        :root {
            color-scheme: light dark;
            supported-color-schemes: light dark;
        }

        /* Gmail dark mode targeting */
        u + .email-body .email-button-link,
        u + .email-body .email-button a {
            color: <?php echo shaped_email_color('onPrimary', '#0B0B09'); ?> !important;
            -webkit-text-fill-color: <?php echo shaped_email_color('onPrimary', '#0B0B09'); ?> !important;
        }

        /* Force button text color in all contexts */
        .email-button-link,
        .email-button-link span,
        .email-button a,
        .email-button a span {
            color: <?php echo shaped_email_color('onPrimary', '#0B0B09'); ?> !important;
            -webkit-text-fill-color: <?php echo shaped_email_color('onPrimary', '#0B0B09'); ?> !important;
        }

        @media (prefers-color-scheme: dark) {
            /* Prevent email clients from changing our colors */
            .email-body {
                background-color: #1a1a1a !important;
            }

            .email-container {
                background-color: #2d2d2d !important;
            }

            /* Keep button colors intact - prevent dark mode inversion */
            .email-button,
            .email-button td,
            .email-button table,
            td[class="email-button"],
            td.email-button {
                background-color: <?php echo shaped_email_color('primary', '#E2BD27'); ?> !important;
                border-color: <?php echo shaped_email_color('primary', '#E2BD27'); ?> !important;
            }

            /* Button text - multiple selectors for maximum compatibility */
            .email-button a,
            .email-button-link,
            .email-button a span,
            .email-button-link span,
            a.email-button-link,
            a[class="email-button-link"],
            .email-button td a,
            td .email-button-link {
                color: <?php echo shaped_email_color('onPrimary', '#0B0B09'); ?> !important;
                -webkit-text-fill-color: <?php echo shaped_email_color('onPrimary', '#0B0B09'); ?> !important;
                background-color: <?php echo shaped_email_color('primary', '#E2BD27'); ?> !important;
            }

            /* Prevent email clients from auto-adjusting brand colors in dark mode */
            .email-card-highlight {
                border-left-color: <?php echo shaped_email_color('primary', '#E2BD27'); ?> !important;
            }

            /* Card backgrounds for dark mode */
            .email-card-highlight {
                background-color: #3d3520 !important;
            }

            .email-card-neutral {
                background-color: #363636 !important;
            }

            /* Text colors for dark mode */
            .email-text-primary {
                color: #ffffff !important;
            }

            .email-text-muted {
                color: #b0b0b0 !important;
            }

            /* Header stays the same - gradient colors work fine */

            /* Footer in dark mode */
            .email-footer {
                background-color: #1a1a1a !important;
            }

            /* Warning/alert boxes */
            .email-alert-warning {
                background-color: #4a3520 !important;
            }

            .email-alert-danger {
                background-color: #4a2020 !important;
            }
        }
    </style>
    <?php
    return ob_get_clean();
}

/* ==========================================================================
   BASE LAYOUT
   ========================================================================== */

/**
 * Start email document
 *
 * @param string $title Email title for the <title> tag
 * @return string Opening HTML markup
 */
function shaped_email_start($title = '') {
    if (empty($title)) {
        $title = shaped_email_config('company_name', 'Booking Confirmation');
    }

    ob_start();
    ?>
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title><?php echo esc_html($title); ?></title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <?php echo shaped_email_get_styles(); ?>
</head>
<body class="email-body" style="margin: 0; padding: 0; font-family: 'DM Sans', Arial, sans-serif; line-height: 1.6; background-color: #f5f5f5;">
    <!--[if mso]>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f5f5;">
    <tr>
    <td align="center">
    <![endif]-->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="email-body" style="background-color: #ffffff;">
        <tr>
            <td align="center" style="padding: 24px 0;">
                <table class="container email-container" role="presentation" width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 12px; overflow: hidden; max-width: 600px; margin: 0 auto;">
    <?php
    return ob_get_clean();
}

/**
 * End email document
 *
 * @return string Closing HTML markup
 */
function shaped_email_end() {
    ob_start();
    ?>
                </table>
            </td>
        </tr>
    </table>
    <!--[if mso]>
    </td>
    </tr>
    </table>
    <![endif]-->
</body>
</html>
    <?php
    return ob_get_clean();
}

/**
 * Render email header
 *
 * @param string $title Main heading text
 * @param string $subtitle Secondary text below heading
 * @return string Header HTML
 */
function shaped_email_header($title, $subtitle = '') {
    $primary = shaped_email_color('primary', '#E2BD27');
    $secondary = shaped_email_color('secondary', '#B7991F');

    ob_start();
    ?>
                    <!-- Header -->
                    <tr>
                        <td class="header-padding" style="background: linear-gradient(135deg, <?php echo $primary; ?> 0%, <?php echo $secondary; ?> 100%); padding: 24px 16px; text-align: center;">
                            <h1 style="margin: 0 0 8px 0; color: #ffffff; font-size: 32px; font-weight: 700; letter-spacing: -0.5px; line-height: 1.2;"><?php echo esc_html($title); ?></h1>
                            <?php if ($subtitle): ?>
                            <p style="margin: 0; color: #ffffff; font-size: 20px; opacity: 0.8;"><?php echo esc_html($subtitle); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
    <?php
    return ob_get_clean();
}

/**
 * Render email footer
 *
 * @param string $disclaimer Optional custom disclaimer text
 * @return string Footer HTML
 */
function shaped_email_footer($disclaimer = '') {
    if (empty($disclaimer)) {
        $disclaimer = shaped_email_config('footer_text', 'This is an automated confirmation email.');
    }

    $company_name = shaped_email_config('company_name', 'Booking System');
    $company_location = shaped_email_config('company_location', '');
    $footer_line = $company_location ? $company_name . ' | ' . $company_location : $company_name;

    ob_start();
    ?>
                    <!-- Footer -->
                    <tr>
                        <td class="footer-padding email-footer" style="background: #26272C; padding: 24px 32px; text-align: center;">
                            <p style="margin: 0 0 8px 0; color: #ffffff; font-size: 14px; line-height: 1.5;">
                                <?php echo esc_html($footer_line); ?>
                            </p>
                            <?php if ($disclaimer): ?>
                            <p style="margin: 0; color: #999999; font-size: 12px; line-height: 1.5;">
                                <?php echo esc_html($disclaimer); ?>
                            </p>
                            <?php endif; ?>
                        </td>
                    </tr>
    <?php
    return ob_get_clean();
}

/**
 * Start main content area
 *
 * @return string Opening content wrapper HTML
 */
function shaped_email_content_start() {
    ob_start();
    ?>
                    <!-- Main Content -->
                    <tr>
                        <td class="content-padding" style="padding: 24px 16px;">
    <?php
    return ob_get_clean();
}

/**
 * End main content area
 *
 * @return string Closing content wrapper HTML
 */
function shaped_email_content_end() {
    ob_start();
    ?>
                        </td>
                    </tr>
    <?php
    return ob_get_clean();
}

/* ==========================================================================
   BLOCK COMPONENTS
   ========================================================================== */

/**
 * Render a greeting block
 *
 * @param string $name Customer's first name
 * @return string HTML greeting
 */
function shaped_email_block_greeting($name) {
    $text_primary = shaped_email_color('textPrimary', '#26272C');
    ob_start();
    ?>
                                <p class="email-text-primary" style="margin: 0 0 24px 0; font-size: 16px; color: <?php echo $text_primary; ?>; line-height: 1.6;">
                                    Dear <strong><?php echo esc_html($name); ?></strong>,
                                </p>
    <?php
    return ob_get_clean();
}

/**
 * Render an intro paragraph
 *
 * @param string $text The intro text
 * @return string HTML paragraph
 */
function shaped_email_block_intro($text) {
    $text_muted = shaped_email_color('textMuted', '#666666');
    ob_start();
    ?>
                                <p class="email-text-muted" style="margin: 0 0 32px 0; font-size: 16px; color: <?php echo $text_muted; ?>; line-height: 1.6;">
                                    <?php echo wp_kses_post($text); ?>
                                </p>
    <?php
    return ob_get_clean();
}

/**
 * Start a card block
 *
 * @param string $variant Card style: 'highlight' (warm), 'neutral' (gray), or 'default'
 * @param string $margin_bottom Bottom margin (default: '24px')
 * @return string Opening card HTML
 */
function shaped_email_block_card_start($variant = 'neutral', $margin_bottom = '24px') {
    $backgrounds = [
        'highlight' => '#fffbf0',
        'neutral'   => '#f8f8f8',
        'default'   => '#ffffff',
    ];

    $bg = isset($backgrounds[$variant]) ? $backgrounds[$variant] : $backgrounds['neutral'];
    $primary = shaped_email_color('primary', '#E2BD27');
    $border_color = shaped_brand('colors.border.default', '#e5e5e5');

    // CSS class for dark mode targeting
    $css_class = 'email-card-' . $variant;

    // Build border style and padding based on variant
    if ($variant === 'highlight') {
        // Brand light background gets 4px border-left in brand main color + extra left padding
        $border_style = "border: 1px solid {$border_color}; border-left: 4px solid {$primary};";
        $padding = "padding: 24px 24px 24px 21px;";
    } elseif ($variant === 'default') {
        // White background - no border needed
        $border_style = "";
        $padding = "padding: 24px;";
    } else {
        // Non-white backgrounds get 1px border
        $border_style = "border: 1px solid {$border_color};";
        $padding = "padding: 24px;";
    }

    ob_start();
    ?>
                                <div class="<?php echo esc_attr($css_class); ?>" style="background: <?php echo $bg; ?>; border-radius: 8px; <?php echo $padding; ?> margin: 0 0 <?php echo esc_attr($margin_bottom); ?> 0; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); <?php echo $border_style; ?>">
    <?php
    return ob_get_clean();
}

/**
 * End a card block
 *
 * @return string Closing card HTML
 */
function shaped_email_block_card_end() {
    return '</div>';
}

/**
 * Render a section title within a card
 *
 * @param string $title Title text
 * @param string $emoji Optional emoji prefix
 * @param string $size Font size: 'large' (20px) or 'medium' (18px)
 * @return string Section title HTML
 */
function shaped_email_block_section_title($title, $emoji = '', $size = 'large') {
    $text_primary = shaped_email_color('textPrimary', '#26272C');
    $font_size = $size === 'large' ? '20px' : '18px';
    $display_title = $emoji ? $emoji . ' ' . $title : $title;

    ob_start();
    ?>
                                    <h2 class="email-text-primary" style="margin: 0 0 16px 0; font-size: <?php echo $font_size; ?>; color: <?php echo $text_primary; ?>; font-weight: 700;"><?php echo esc_html($display_title); ?></h2>
    <?php
    return ob_get_clean();
}

/**
 * Render a section title with h3 styling
 *
 * @param string $title Title text
 * @param string $emoji Optional emoji prefix
 * @return string Section title HTML
 */
function shaped_email_block_section_title_h3($title, $emoji = '') {
    $text_primary = shaped_email_color('textPrimary', '#26272C');
    $display_title = $emoji ? $emoji . ' ' . $title : $title;

    ob_start();
    ?>
                                    <h3 class="email-text-primary" style="margin: 0 0 16px 0; font-size: 18px; color: <?php echo $text_primary; ?>; font-weight: 700;"><?php echo esc_html($display_title); ?></h3>
    <?php
    return ob_get_clean();
}

/**
 * Start a detail rows table
 *
 * @return string Opening table HTML
 */
function shaped_email_block_rows_start() {
    ob_start();
    ?>
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
    <?php
    return ob_get_clean();
}

/**
 * End a detail rows table
 *
 * @return string Closing table HTML
 */
function shaped_email_block_rows_end() {
    return '</table>';
}

/**
 * Render a single detail row (key-value pair)
 *
 * @param string $label Row label
 * @param string $value Row value
 * @param array  $options Optional settings:
 *                        - 'bold_value' (bool) Make value bold
 *                        - 'sub_text' (string) Secondary line under value
 *                        - 'mobile_stack' (bool) Stack on mobile
 * @return string Row HTML
 */
function shaped_email_block_row($label, $value, $options = []) {
    $text_muted = shaped_email_color('textMuted', '#666666');
    $text_primary = shaped_email_color('textPrimary', '#26272C');

    $bold_value = isset($options['bold_value']) ? $options['bold_value'] : false;
    $sub_text = isset($options['sub_text']) ? $options['sub_text'] : '';
    $mobile_stack = isset($options['mobile_stack']) ? $options['mobile_stack'] : false;

    $row_class = $mobile_stack ? 'mobile-stack detail-row' : 'detail-row';
    $value_class = $mobile_stack ? 'mobile-right email-text-primary' : 'email-text-primary';

    ob_start();
    ?>
                                        <tr class="<?php echo esc_attr($row_class); ?>">
                                            <td class="email-text-muted" style="padding: 10px 0; color: <?php echo $text_muted; ?>; font-size: 14px; font-weight: 600; line-height: 1.6;"><?php echo esc_html($label); ?></td>
                                            <td class="<?php echo esc_attr($value_class); ?>" style="padding: 10px 0; color: <?php echo $text_primary; ?>; font-size: 14px; text-align: right; line-height: 1.6;">
                                                <?php if ($bold_value): ?><strong><?php endif; ?><?php echo esc_html($value); ?><?php if ($bold_value): ?></strong><?php endif; ?><?php if ($sub_text): ?><br><span class="email-text-muted" style="font-weight: 600; color: <?php echo $text_muted; ?>;"><?php echo esc_html($sub_text); ?></span><?php endif; ?>
                                            </td>
                                        </tr>
    <?php
    return ob_get_clean();
}

/**
 * Render a divider row for total sections
 *
 * @return string Divider row HTML
 */
function shaped_email_block_total_divider() {
    $border_color = shaped_brand('colors.border.default', '#e5e5e5');
    ob_start();
    ?>
                                        <tr>
                                            <td colspan="2" style="padding: 8px 0 0 0; border-bottom: 1px solid <?php echo $border_color; ?>;"></td>
                                        </tr>
                                        <tr>
                                            <td colspan="2" style="padding: 16px 0 0 0;"></td>
                                        </tr>
    <?php
    return ob_get_clean();
}

/**
 * Render a total row (used after divider)
 *
 * @param string $label Total label (e.g., "Total Paid:")
 * @param string $value Total value (e.g., "$299.00")
 * @return string Total row HTML
 */
function shaped_email_block_total_row($label, $value) {
    $text_primary = shaped_email_color('textPrimary', '#26272C');

    ob_start();
    ?>
                                        <tr>
                                            <td class="email-text-primary" style="padding: 0; color: <?php echo $text_primary; ?>; font-size: 16px; font-weight: 600; line-height: 1.6;"><?php echo esc_html($label); ?></td>
                                            <td style="padding: 0; text-align: right;">
                                                <span class="total-price email-text-primary" style="color: <?php echo $text_primary; ?>; font-size: 24px; font-weight: 600;"><?php echo esc_html($value); ?></span>
                                            </td>
                                        </tr>
    <?php
    return ob_get_clean();
}

/**
 * Render a CTA button
 *
 * Uses table-based structure for maximum email client compatibility,
 * including dark mode support on mobile devices.
 *
 * @param string $text Button text
 * @param string $url Button link URL
 * @param string $subtext Optional text below button
 * @return string Button HTML
 */
function shaped_email_block_button($text, $url, $subtext = '') {
    $primary = shaped_email_color('primary', '#E2BD27');
    $on_primary = shaped_email_color('onPrimary', '#0B0B09');

    ob_start();
    ?>
                                <div style="text-align: center; margin: 0 0 24px 0;">
                                    <!--[if mso]>
                                    <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="<?php echo esc_url($url); ?>" style="height:48px;v-text-anchor:middle;width:200px;" arcsize="17%" stroke="f" fillcolor="<?php echo $primary; ?>">
                                        <w:anchorlock/>
                                        <center style="color:<?php echo $on_primary; ?>;font-family:Arial,sans-serif;font-size:15px;font-weight:600;text-transform:uppercase;"><?php echo esc_html($text); ?></center>
                                    </v:roundrect>
                                    <![endif]-->
                                    <!--[if !mso]><!-->
                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" class="email-button" style="margin: 0 auto;">
                                        <tr>
                                            <td align="center" bgcolor="<?php echo $primary; ?>" style="background-color: <?php echo $primary; ?> !important; border-radius: 8px; mso-padding-alt: 16px 32px;">
                                                <a href="<?php echo esc_url($url); ?>" class="email-button-link" target="_blank" style="display: inline-block; background-color: <?php echo $primary; ?> !important; color: <?php echo $on_primary; ?> !important; -webkit-text-fill-color: <?php echo $on_primary; ?> !important; padding: 16px 32px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 15px; letter-spacing: 0.2px; text-transform: uppercase; font-family: 'DM Sans', Arial, sans-serif; mso-line-height-rule: exactly; line-height: 1.2;">
                                                    <span style="color: <?php echo $on_primary; ?> !important; -webkit-text-fill-color: <?php echo $on_primary; ?> !important;"><?php echo esc_html($text); ?></span>
                                                </a>
                                            </td>
                                        </tr>
                                    </table>
                                    <!--<![endif]-->
                                    <?php if ($subtext): ?>
                                    <p class="email-text-muted" style="margin: 12px 0 0 0; font-size: 13px; color: #999999; line-height: 1.6;">
                                        <?php echo esc_html($subtext); ?>
                                    </p>
                                    <?php endif; ?>
                                </div>
    <?php
    return ob_get_clean();
}

/**
 * Render a paragraph block
 *
 * @param string $text Paragraph text (can include basic HTML)
 * @param string $variant Text style: 'muted', 'primary', or 'center'
 * @return string Paragraph HTML
 */
function shaped_email_block_paragraph($text, $variant = 'muted') {
    $text_muted = shaped_email_color('textMuted', '#666666');
    $text_primary = shaped_email_color('textPrimary', '#26272C');

    $color = $variant === 'primary' ? $text_primary : $text_muted;
    $css_class = $variant === 'primary' ? 'email-text-primary' : 'email-text-muted';
    $align = $variant === 'center' ? 'center' : 'left';
    if ($variant === 'center') {
        $color = $text_muted;
        $css_class = 'email-text-muted';
    }

    ob_start();
    ?>
                                    <p class="<?php echo esc_attr($css_class); ?>" style="margin: 0 0 16px 0; font-size: 14px; color: <?php echo $color; ?>; line-height: 1.6; text-align: <?php echo $align; ?>;">
                                        <?php echo wp_kses_post($text); ?>
                                    </p>
    <?php
    return ob_get_clean();
}

/**
 * Render an address block
 *
 * @param string $label Label text (e.g., "Address:")
 * @param string $address Address text
 * @param string $url Optional Google Maps URL
 * @return string Address HTML
 */
function shaped_email_block_address($label, $address, $url = '') {
    $text_primary = shaped_email_color('textPrimary', '#26272C');
    $text_dark = shaped_email_color('textDark', '#26272C');

    ob_start();
    ?>
                                    <p class="email-text-primary" style="margin: 0 0 12px 0; font-size: 16px; color: <?php echo $text_primary; ?>; line-height: 1.6;">
                                        <strong><?php echo esc_html($label); ?></strong><br>
                                        <?php if ($url): ?>
                                        <a href="<?php echo esc_url($url); ?>" class="email-text-primary" style="color: <?php echo $text_dark; ?>; font-size: 16px; font-weight: 700;"><?php echo esc_html($address); ?></a>
                                        <?php else: ?>
                                        <?php echo esc_html($address); ?>
                                        <?php endif; ?>
                                    </p>
    <?php
    return ob_get_clean();
}

/**
 * Render a contact info block
 *
 * @param string $phone Phone number (optional - uses config if empty)
 * @param string $email Email address (optional - uses config if empty)
 * @return string Contact HTML
 */
function shaped_email_block_contact($phone = '', $email = '') {
    $text_primary = shaped_email_color('textPrimary', '#26272C');

    // Use config values if not provided
    if (empty($phone)) {
        $phone = shaped_email_config('phone', '+385 91 613 3609');
    }
    if (empty($email)) {
        $email = shaped_email_config('email', 'info@example.com');
    }

    ob_start();
    ?>
                                    <p class="email-text-primary" style="margin: 0; font-size: 16px; color: <?php echo $text_primary; ?>; line-height: 1.8;">
                                        <strong>Phone:</strong> <a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $phone)); ?>" class="email-text-primary" style="color: <?php echo $text_primary; ?>; font-weight: 700;"><?php echo esc_html($phone); ?></a><br>
                                        <strong>Email:</strong> <a href="mailto:<?php echo esc_attr($email); ?>" class="email-text-primary" style="color: <?php echo $text_primary; ?>; font-weight: 700;"><?php echo esc_html($email); ?></a>
                                    </p>
    <?php
    return ob_get_clean();
}

/**
 * Render a closing message block
 *
 * @param string $message Main message (optional - uses config if empty)
 * @param string $signature Signature line (optional - uses config if empty)
 * @param string $variant Card style: 'highlight' or 'neutral'
 * @param string $logo_url Logo URL (optional - uses config if empty)
 * @return string Closing message HTML
 */
function shaped_email_block_closing($message = '', $signature = '', $variant = 'highlight', $logo_url = null) {
    $text_primary = shaped_email_color('textPrimary', '#26272C');
    $primary = shaped_email_color('primary', '#E2BD27');
    $border_color = shaped_brand('colors.border.default', '#e5e5e5');
    $bg = $variant === 'highlight' ? '#fffbf0' : '#f8f8f8';

    // CSS class for dark mode targeting
    $css_class = 'email-card-' . $variant;

    // Build border style and padding based on variant
    if ($variant === 'highlight') {
        // Brand light background gets 4px border-left in brand main color + extra left padding
        $border_style = "border: 1px solid {$border_color}; border-left: 4px solid {$primary};";
        $padding = "padding: 24px 24px 24px 21px;";
    } else {
        // Non-white backgrounds get 1px border
        $border_style = "border: 1px solid {$border_color};";
        $padding = "padding: 24px;";
    }

    // Use config values if not provided
    if (empty($message)) {
        $message = shaped_email_config('closing_message', "We're looking forward to hosting you!");
    }
    if (empty($signature)) {
        $signature = shaped_email_config('signature', 'Warm regards,<br>The Team');
    }
    if ($logo_url === null) {
        $logo_url = shaped_email_config('logo_url', '');
    }

    ob_start();
    ?>
                                <div class="<?php echo esc_attr($css_class); ?>" style="text-align: center; <?php echo $padding; ?> background: <?php echo $bg; ?>; border-radius: 8px; margin: 0; <?php echo $border_style; ?>">
                                    <p class="email-text-primary" style="margin: 0 0 12px 0; font-size: 16px; color: <?php echo $text_primary; ?>; line-height: 1.6;">
                                        <?php echo esc_html($message); ?>
                                    </p>
                                    <p class="email-text-primary" style="margin: 0<?php if (!empty($logo_url)): ?> 0 16px 0<?php endif; ?>; font-size: 16px; color: <?php echo $text_primary; ?>; font-weight: 600; line-height: 1.6;">
                                        <?php echo wp_kses_post($signature); ?>
                                    </p>
                                    <?php if (!empty($logo_url)): ?>
                                    <div style="margin: 16px 0 0 0;">
                                        <img src="<?php echo esc_url($logo_url); ?>" alt="Company Logo" style="max-width: 80px; height: auto; display: inline-block;" />
                                    </div>
                                    <?php endif; ?>
                                </div>
    <?php
    return ob_get_clean();
}

/**
 * Render a payment info highlight box
 *
 * @param string $amount Amount to display
 * @param string $date Charge date
 * @param string $note Optional note text
 * @return string Payment info HTML
 */
function shaped_email_block_payment_info($amount, $date, $note = '') {
    $text_primary = shaped_email_color('textPrimary', '#26272C');
    $text_muted = shaped_email_color('textMuted', '#666666');
    $primary = shaped_email_color('primary', '#E2BD27');
    $border_color = shaped_brand('colors.border.default', '#e5e5e5');

    // Brand light background gets 4px border-left in brand main color + extra left padding
    $border_style = "border: 1px solid {$border_color}; border-left: 4px solid {$primary};";

    ob_start();
    ?>
                                <div class="email-card-highlight email-alert-warning" style="background: #fffbf0; border-radius: 8px; padding: 24px 24px 24px 21px; margin: 0 0 24px 0; text-align: center; <?php echo $border_style; ?>">
                                    <p class="email-text-primary" style="margin: 0 0 12px 0; font-size: 16px; color: <?php echo $text_primary; ?>; line-height: 1.6;">
                                        We'll charge <strong class="email-text-primary" style="color: <?php echo $text_primary; ?>; font-size: 20px;"><?php echo esc_html($amount); ?></strong>
                                    </p>
                                    <p class="email-text-muted" style="margin: 0; font-size: 14px; color: <?php echo $text_muted; ?>; line-height: 1.6;">
                                        on <strong><?php echo esc_html($date); ?></strong>
                                        <?php if ($note): ?><br><?php echo esc_html($note); ?><?php endif; ?>
                                    </p>
                                </div>
    <?php
    return ob_get_clean();
}

/**
 * Render a text-only paragraph (no margin at bottom)
 *
 * @param string $text Text content
 * @param string $variant 'muted', 'primary', 'center', or 'brand'
 * @return string Paragraph HTML
 */
function shaped_email_block_text($text, $variant = 'muted') {
    $text_muted = shaped_email_color('textMuted', '#666666');
    $text_primary = shaped_email_color('textPrimary', '#26272C');
    $primary = shaped_email_color('primary', '#E2BD27');

    $color = $variant === 'primary' ? $text_primary : $text_muted;
    $css_class = $variant === 'primary' ? 'email-text-primary' : 'email-text-muted';
    $align = 'left';
    $font_weight = 'normal';

    if ($variant === 'center') {
        $color = $text_muted;
        $css_class = 'email-text-muted';
        $align = 'center';
    }

    if ($variant === 'brand') {
        $color = $primary;
        $css_class = 'email-text-primary';
        $align = 'center';
        $font_weight = '600';
    }

    ob_start();
    ?>
                                <p class="<?php echo esc_attr($css_class); ?>" style="margin: 0; font-size: 14px; color: <?php echo $color; ?>; line-height: 1.6; text-align: <?php echo $align; ?>; font-weight: <?php echo $font_weight; ?>;">
                                    <?php echo wp_kses_post($text); ?>
                                </p>
    <?php
    return ob_get_clean();
}

/* ==========================================================================
   HIGH-LEVEL RENDER FUNCTIONS
   ========================================================================== */

/**
 * Render a complete email
 *
 * @param array $args Email configuration
 * @return string Complete email HTML
 */
function shaped_render_email($args) {
    $company_name = shaped_email_config('company_name', 'Booking System');

    $defaults = [
        'title'       => $company_name,
        'header'      => '',
        'subtitle'    => '',
        'content'     => '',
        'footer_text' => '',
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
 * @param array $data Booking information
 * @return string Booking details card HTML
 */
function shaped_email_render_booking_details($data) {
    $check_in_time = isset($data['check_in_time']) ? $data['check_in_time'] : shaped_email_config('check_in_time', 'from 16:00');
    $check_out_time = isset($data['check_out_time']) ? $data['check_out_time'] : shaped_email_config('check_out_time', 'until 11:00');

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

    if (!empty($data['rate_name'])) {
        $html .= shaped_email_block_row('Rate:', $data['rate_name'], ['bold_value' => true]);
    }

    if (!empty($data['total_paid'])) {
        $html .= shaped_email_block_total_divider();
        $html .= shaped_email_block_total_row('Total Paid:', $data['total_paid']);
    }

    $html .= shaped_email_block_rows_end();
    $html .= shaped_email_block_card_end();

    return $html;
}

/**
 * Render "Getting Here" section
 *
 * @param array $options Optional customizations
 * @return string Getting here card HTML
 */
function shaped_email_render_getting_here($options = []) {
    $address_raw = shaped_email_config('address', 'Address not configured');
    $address_formatted = shaped_email_format_address($address_raw);

    $defaults = [
        'address'      => $address_formatted,
        'maps_url'     => shaped_email_config('maps_url', ''),
        'instructions' => shaped_email_config('check_in_instructions', 'Please contact us for check-in instructions.'),
    ];

    $options = wp_parse_args($options, $defaults);

    $text_primary = shaped_email_color('textPrimary', '#26272C');
    $text_muted = shaped_email_color('textMuted', '#666666');

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
 * Render "Need Help?" contact section
 *
 * @param array $options Optional customizations
 * @return string Contact card HTML
 */
function shaped_email_render_contact($options = []) {
    $defaults = [
        'title' => 'Need Help?',
        'intro' => "We're here to ensure your perfect stay. Contact us anytime:",
        'phone' => shaped_email_config('phone', ''),
        'email' => shaped_email_config('email', ''),
    ];

    $options = wp_parse_args($options, $defaults);

    $text_muted = shaped_email_color('textMuted', '#666666');

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
 * @param string $message Optional custom message
 * @return string Closing HTML
 */
function shaped_email_render_closing($message = '') {
    if (empty($message)) {
        $message = shaped_email_config('closing_message', "We're looking forward to hosting you!");
    }
    $signature = shaped_email_config('signature', 'Warm regards,<br>The Team');

    return shaped_email_block_closing($message, $signature, 'highlight');
}

/**
 * Render simple booking summary card
 *
 * @param array $data Booking information
 * @return string Booking summary HTML
 */
function shaped_email_render_booking_summary($data) {
    $html = shaped_email_block_card_start('neutral');
    $html .= shaped_email_block_section_title('Booking #' . $data['booking_id'], '📋');
    $html .= shaped_email_block_rows_start();
    $html .= shaped_email_block_row('Check-in:', $data['check_in'], ['bold_value' => true]);
    $html .= shaped_email_block_row('Check-out:', $data['check_out'], ['bold_value' => true]);

    if (!empty($data['room_list'])) {
        $html .= shaped_email_block_row('Accommodation:', $data['room_list'], ['bold_value' => true]);
    }

    if (!empty($data['rate_name'])) {
        $html .= shaped_email_block_row('Rate:', $data['rate_name'], ['bold_value' => true]);
    }

    $html .= shaped_email_block_rows_end();
    $html .= shaped_email_block_card_end();

    return $html;
}

/**
 * Render cancellation confirmation card
 *
 * @param bool $is_refundable Whether the booking was refundable
 * @return string Cancellation card HTML
 */
function shaped_email_render_cancellation_card($is_refundable = false) {
    if (!$is_refundable) {
        return '';
    }

    $text_primary = shaped_email_color('textPrimary', '#26272C');
    $company_name = shaped_email_config('company_name', 'our property');

    $html = shaped_email_block_card_start('highlight');
    $html .= shaped_email_block_section_title('Cancellation Confirmed', '');

    ob_start();
    ?>
                                    <p style="margin: 0; color: <?php echo $text_primary; ?>; font-size: 14px; line-height: 1.6;">
                                        You successfully cancelled your <?php echo esc_html($company_name); ?> booking.
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
 * @param int    $booking_id    Booking ID
 * @param string $customer_email Customer email for token generation
 * @return string Manage booking URL
 */
function shaped_email_get_manage_url($booking_id, $customer_email) {
    $token = md5($booking_id . $customer_email);
    return home_url('/manage-booking/?booking_id=' . $booking_id . '&token=' . $token);
}
