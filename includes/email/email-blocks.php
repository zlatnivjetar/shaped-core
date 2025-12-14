<?php
/**
 * Email Block Components
 *
 * Reusable UI blocks for building consistent email templates.
 * All blocks use table-based layouts for maximum email client compatibility.
 *
 * @package Shaped_Core
 * @subpackage Email
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render a greeting block
 *
 * @param string $name Customer's first name
 * @return string HTML greeting
 */
function shaped_email_block_greeting($name) {
    $text_primary = shaped_brand_color('textPrimary') ?: '#26272C';
    ob_start();
    ?>
                                <p style="margin: 0 0 24px 0; font-size: 16px; color: <?php echo $text_primary; ?>; line-height: 1.6;">
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
    $text_muted = shaped_brand_color('textMuted') ?: '#666666';
    ob_start();
    ?>
                                <p style="margin: 0 0 32px 0; font-size: 16px; color: <?php echo $text_muted; ?>; line-height: 1.6;">
                                    <?php echo wp_kses_post($text); ?>
                                </p>
    <?php
    return ob_get_clean();
}

/**
 * Start a card block
 *
 * Cards are the main content sections with background color and padding.
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

    ob_start();
    ?>
                                <div style="background: <?php echo $bg; ?>; border-radius: 8px; padding: 24px; margin: 0 0 <?php echo esc_attr($margin_bottom); ?> 0; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); border: 1px solid #E0E0E0;">
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
    $text_primary = shaped_brand_color('textPrimary') ?: '#26272C';
    $font_size = $size === 'large' ? '20px' : '18px';
    $display_title = $emoji ? $emoji . ' ' . $title : $title;

    ob_start();
    ?>
                                    <h2 style="margin: 0 0 16px 0; font-size: <?php echo $font_size; ?>; color: <?php echo $text_primary; ?>; font-weight: 700;"><?php echo esc_html($display_title); ?></h2>
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
    $text_primary = shaped_brand_color('textPrimary') ?: '#26272C';
    $display_title = $emoji ? $emoji . ' ' . $title : $title;

    ob_start();
    ?>
                                    <h3 style="margin: 0 0 160px 0; font-size: 18px; color: <?php echo $text_primary; ?>; font-weight: 700;"><?php echo esc_html($display_title); ?></h3>
    <?php
    return ob_get_clean();
}

/**
 * Start a detail rows table
 *
 * Use this to render key-value pairs consistently.
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
    $text_muted = shaped_brand_color('textMuted') ?: '#666666';
    $text_primary = shaped_brand_color('textPrimary') ?: '#26272C';

    $bold_value = isset($options['bold_value']) ? $options['bold_value'] : false;
    $sub_text = isset($options['sub_text']) ? $options['sub_text'] : '';
    $mobile_stack = isset($options['mobile_stack']) ? $options['mobile_stack'] : false;

    $row_class = $mobile_stack ? 'class="mobile-stack detail-row"' : 'class="detail-row"';
    $value_class = $mobile_stack ? 'class="mobile-right"' : '';

    ob_start();
    ?>
                                        <tr <?php echo $row_class; ?>>
                                            <td style="padding: 10px 0; color: <?php echo $text_muted; ?>; font-size: 14px; font-weight: 600; line-height: 1.6;"><?php echo esc_html($label); ?></td>
                                            <td <?php echo $value_class; ?> style="padding: 10px 0; color: <?php echo $text_primary; ?>; font-size: 14px; text-align: right; line-height: 1.6;">
                                                <?php if ($bold_value): ?><strong><?php endif; ?><?php echo esc_html($value); ?><?php if ($bold_value): ?></strong><?php endif; ?><?php if ($sub_text): ?><br><span style="font-weight: 600; color: <?php echo $text_muted; ?>;"><?php echo esc_html($sub_text); ?></span><?php endif; ?>
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
    $text_primary = shaped_brand_color('textPrimary') ?: '#26272C';
    $primary = shaped_brand_color('primary') ?: '#D1AF5D';

    ob_start();
    ?>
                                        <tr>
                                            <td style="padding: 0; color: <?php echo $text_primary; ?>; font-size: 16px; font-weight: 700; line-height: 1.6;"><?php echo esc_html($label); ?></td>
                                            <td style="padding: 0; text-align: right;">
                                                <span class="total-price" style="color: <?php echo $primary; ?>; font-size: 24px; font-weight: 700;"><?php echo esc_html($value); ?></span>
                                            </td>
                                        </tr>
    <?php
    return ob_get_clean();
}

/**
 * Render a CTA button
 *
 * @param string $text Button text
 * @param string $url Button link URL
 * @param string $subtext Optional text below button
 * @return string Button HTML
 */
function shaped_email_block_button($text, $url, $subtext = '') {
    $primary = shaped_brand_color('primary') ?: '#D1AF5D';
    $text_inverse = shaped_brand_color('textInverse') ?: '#FFFFFF';

    ob_start();
    ?>
                                <div style="text-align: center; margin: 0 0 24px 0;">
                                    <a href="<?php echo esc_url($url); ?>"
                                       style="display: inline-block; background: <?php echo $primary; ?>; color: <?php echo $text_inverse; ?>; padding: 14px 30px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 15px; letter-spacing: 0.2px; text-transform: uppercase;">
                                        <?php echo esc_html($text); ?>
                                    </a>
                                    <?php if ($subtext): ?>
                                    <p style="margin: 12px 0 0 0; font-size: 13px; color: #999999; line-height: 1.6;">
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
    $text_muted = shaped_brand_color('textMuted') ?: '#666666';
    $text_primary = shaped_brand_color('textPrimary') ?: '#26272C';
    $primary = shaped_brand_color('primary') ?: '#D1AF5D';

    $color = $variant === 'primary' ? $text_primary : $text_muted;
    $align = $variant === 'center' ? 'center' : 'left';
    if ($variant === 'center') {
        $color = $text_muted;
    }

    ob_start();
    ?>
                                    <p style="margin: 0 0 16px 0; font-size: 14px; color: <?php echo $color; ?>; line-height: 1.6; text-align: <?php echo $align; ?>;">
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
    $text_primary = shaped_brand_color('textPrimary') ?: '#26272C';
    $text_dark = shaped_brand_color('textDark') ?: '#26272C';

    ob_start();
    ?>
                                    <p style="margin: 0 0 12px 0; font-size: 16px; color: <?php echo $text_primary; ?>; line-height: 1.6;">
                                        <strong><?php echo esc_html($label); ?></strong><br>
                                        <?php if ($url): ?>
                                        <a href="<?php echo esc_url($url); ?>" style="color: <?php echo $text_dark; ?>; font-size: 16px; font-weight: 700;"><?php echo esc_html($address); ?></a>
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
 * @param string $phone Phone number
 * @param string $email Email address
 * @return string Contact HTML
 */
function shaped_email_block_contact($phone, $email) {
    $text_primary = shaped_brand_color('textPrimary') ?: '#26272C';
    $text_dark = shaped_brand_color('textDark') ?: '#26272C';

    ob_start();
    ?>
                                    <p style="margin: 0; font-size: 16px; color: <?php echo $text_primary; ?>; line-height: 1.8;">
                                        <strong>Phone:</strong> <a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $phone)); ?>" style="color: <?php echo $text_primary; ?>; font-weight: 700;"><?php echo esc_html($phone); ?></a><br>
                                        <strong>Email:</strong> <a href="mailto:<?php echo esc_attr($email); ?>" style="color: <?php echo $text_primary; ?>; font-weight: 700;"><?php echo esc_html($email); ?></a>
                                    </p>
    <?php
    return ob_get_clean();
}

/**
 * Render an explore area item row
 *
 * @param string $name Location name
 * @param string $description Short description
 * @param string $distance Distance (e.g., "1.5 km")
 * @param string $url Google Maps URL
 * @return string Location row HTML
 */
function shaped_email_block_explore_item($name, $description, $distance, $url) {
    $text_primary = shaped_brand_color('textPrimary') ?: '#26272C';
    $text_muted = shaped_brand_color('textMuted') ?: '#666666';
    $text_dark = shaped_brand_color('textDark') ?: '#26272C';

    ob_start();
    ?>
                                        <tr>
                                            <td style="padding: 10px 0; vertical-align: top;">
                                                <a href="<?php echo esc_url($url); ?>" style="color: <?php echo $text_primary; ?>; font-weight: 700;"><?php echo esc_html($name); ?></a><br>
                                                <span style="color: <?php echo $text_muted; ?>; font-size: 16px; line-height: 1.5;"><?php echo esc_html($description); ?></span><br>
                                                <span style="color: <?php echo $text_primary; ?>; font-size: 13px; font-weight: 600; margin-top: 4px; display: inline-block;"><?php echo esc_html($distance); ?></span>
                                            </td>
                                        </tr>
    <?php
    return ob_get_clean();
}

/**
 * Render a closing message block
 *
 * @param string $message Main message
 * @param string $signature Signature line
 * @param string $variant Card style: 'highlight' or 'neutral'
 * @return string Closing message HTML
 */
function shaped_email_block_closing($message, $signature = 'Warm regards,<br>The Preelook Team', $variant = 'highlight') {
    $text_primary = shaped_brand_color('textPrimary') ?: '#26272C';
    $bg = $variant === 'highlight' ? '#fffbf0' : '#f8f8f8';

    ob_start();
    ?>
                                <div style="text-align: center; padding: 24px; background: <?php echo $bg; ?>; border-radius: 8px; margin: 0;">
                                    <p style="margin: 0 0 12px 0; font-size: 16px; color: <?php echo $text_primary; ?>; line-height: 1.6;">
                                        <?php echo esc_html($message); ?>
                                    </p>
                                    <p style="margin: 0; font-size: 16px; color: <?php echo $text_primary; ?>; font-weight: 600; line-height: 1.6;">
                                        <?php echo wp_kses_post($signature); ?>
                                    </p>
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
    $text_primary = shaped_brand_color('textPrimary') ?: '#26272C';
    $text_muted = shaped_brand_color('textMuted') ?: '#666666';

    ob_start();
    ?>
                                <div style="background: #fffbf0; border-radius: 8px; padding: 24px; margin: 0 0 24px 0; text-align: center;">
                                    <p style="margin: 0 0 12px 0; font-size: 16px; color: <?php echo $text_primary; ?>; line-height: 1.6;">
                                        We'll charge <strong style="color: <?php echo $text_primary; ?>; font-size: 20px;"><?php echo esc_html($amount); ?></strong>
                                    </p>
                                    <p style="margin: 0; font-size: 14px; color: <?php echo $text_muted; ?>; line-height: 1.6;">
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
 * @param string $variant 'muted', 'primary', or 'center'
 * @return string Paragraph HTML
 */
function shaped_email_block_text($text, $variant = 'muted') {
    $text_muted = shaped_brand_color('textMuted') ?: '#666666';
    $text_primary = shaped_brand_color('textPrimary') ?: '#26272C';
    $primary = shaped_brand_color('primary') ?: '#D1AF5D';

    $color = $variant === 'primary' ? $text_primary : $text_muted;
    $align = 'left';
    $font_weight = 'normal';

    if ($variant === 'center') {
        $color = $text_muted;
        $align = 'center';
    }

    if ($variant === 'brand') {
        $color = $primary;
        $align = 'center';
        $font_weight = '600';
    }

    ob_start();
    ?>
                                <p style="margin: 0; font-size: 14px; color: <?php echo $color; ?>; line-height: 1.6; text-align: <?php echo $align; ?>; font-weight: <?php echo $font_weight; ?>;">
                                    <?php echo wp_kses_post($text); ?>
                                </p>
    <?php
    return ob_get_clean();
}
