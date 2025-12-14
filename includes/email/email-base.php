<?php
/**
 * Email Base Layout
 *
 * Provides the base HTML structure, typography, and spacing for all transactional emails.
 * This file controls the global email styling that affects all email templates.
 *
 * @package Shaped_Core
 * @subpackage Email
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get email base styles
 *
 * Returns CSS styles for responsive typography and spacing.
 * Desktop: 16px body text | Mobile: 16px body text
 *
 * @return string CSS styles
 */
function shaped_email_get_styles() {
    ob_start();
    ?>
    <style type="text/css">
        /* Base Typography */
        body, p, td, a, li {
            font-family: 'DM Sans', Arial, sans-serif;
            font-size: 16px;
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
            color: <?php echo shaped_brand_color('textPrimary') ?: '#26272C'; ?>;
            text-decoration: none;
            font-weight: 700;
        }

        /* Heading margins */
        h2, h3 {
            margin-top: 0;
        }

        h2 {
            margin-bottom: 24px;
        }

        h3 {
            margin-bottom: 24px;
        }

        /* Mobile Responsive */
        @media only screen and (max-width: 600px) {
            /* Container adjustments */
            .container {
                width: 100% !important;
                margin: 0 !important;
                border-radius: 0 !important;
            }

            /* Typography: Increase font size for mobile readability */
            body, p, td, a, li {
                font-size: 16px !important;
            }

            /* Improved mobile spacing */
            .content-padding {
                padding: 24px 0px !important;
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

            /* Header adjustments */
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

            /* Total price emphasis */
            .total-price {
                font-size: 24px !important;
            }

            /* Stack columns on mobile */
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

            /* Row padding improvements for mobile */
            .detail-row td {
                padding: 10px 0 !important;
            }

            /* Footer mobile */
            .footer-padding {
                padding: 20px 16px !important;
            }
        }
    </style>
    <?php
    return ob_get_clean();
}

/**
 * Start email document
 *
 * Opens the HTML document with proper doctype, head, and body tags.
 *
 * @param string $title Email title for the <title> tag
 * @return string Opening HTML markup
 */
function shaped_email_start($title = 'Preelook Apartments') {
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
<body style="margin: 0; padding: 0; font-family: 'DM Sans', Arial, sans-serif; line-height: 1.6; background-color: #f5f5f5;">
    <!--[if mso]>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f5f5;">
    <tr>
    <td align="center">
    <![endif]-->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #ffffff;">
        <tr>
            <td align="center" style="padding: 24px 0;">
                <table class="container" role="presentation" width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 12px; overflow: hidden; max-width: 600px; margin: 0 auto;">
    <?php
    return ob_get_clean();
}

/**
 * End email document
 *
 * Closes all HTML tags properly.
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
 * Displays the branded header with gradient background.
 *
 * @param string $title Main heading text
 * @param string $subtitle Secondary text below heading
 * @return string Header HTML
 */
function shaped_email_header($title, $subtitle = '') {
    $primary = shaped_brand_color('primary') ?: '#D1AF5D';
    $secondary = shaped_brand_color('secondary') ?: '#94772E';
    $text_inverse = shaped_brand_color('textInverse') ?: '#FFFFFF';

    ob_start();
    ?>
                    <!-- Header -->
                    <tr>
                        <td class="header-padding" style="background: linear-gradient(135deg, <?php echo $primary; ?> 0%, <?php echo $secondary; ?> 100%); padding: 16px; text-align: center;">
                            <h1 style="margin: 0 0 8px 0; color: #ffffff; font-size: 32px; font-weight: 700; letter-spacing: -0.5px; line-height: 1.2;"><?php echo esc_html($title); ?></h1>
                            <?php if ($subtitle): ?>
                            <p style="margin: 0; color: #ffffff; font-size: 20px; opacity: 0.95;"><?php echo esc_html($subtitle); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
    <?php
    return ob_get_clean();
}

/**
 * Render email footer
 *
 * Displays the standard footer with location and disclaimer.
 *
 * @param string $disclaimer Optional custom disclaimer text
 * @return string Footer HTML
 */
function shaped_email_footer($disclaimer = 'This is an automated confirmation email.') {
    ob_start();
    ?>
                    <!-- Footer -->
                    <tr>
                        <td class="footer-padding" style="background: #26272C; padding: 24px 32px; text-align: center;">
                            <p style="margin: 0 0 8px 0; color: #ffffff; font-size: 14px; line-height: 1.5;">
                                Preelook Apartments | Rijeka, Croatia
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
 * Opens the main content wrapper with proper padding.
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
 * Closes the main content wrapper.
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
