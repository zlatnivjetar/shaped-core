<?php
/**
 * Checkout Helper Functions
 * Helper functions for checkout page elements
 *
 * @package Shaped_Core
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get Terms and Conditions modal link
 *
 * @param string $label Link text
 * @param string $class Additional CSS classes
 * @return string Modal link HTML
 */
function shaped_get_terms_modal_link($label = null, $class = '') {
    if ($label === null) {
        $label = __('Booking Terms', 'motopress-hotel-booking');
    }

    $termsPageId = MPHB()->settings()->pages()->getTermsAndConditionsPageId();

    if (!$termsPageId) {
        return '';
    }

    $termsPageUrl = get_permalink($termsPageId);
    $classes = 'shaped-terms-link modal-trigger';

    if (!empty($class)) {
        $classes .= ' ' . esc_attr($class);
    }

    return sprintf(
        '<a class="%s" href="%s" data-modal="terms" data-page-id="%d">%s</a>',
        esc_attr($classes),
        esc_url($termsPageUrl),
        absint($termsPageId),
        esc_html($label)
    );
}

/**
 * Get Privacy Policy modal link
 *
 * @param string $label Link text
 * @param string $class Additional CSS classes
 * @return string Modal link HTML
 */
function shaped_get_privacy_modal_link($label = null, $class = '') {
    if ($label === null) {
        $label = __('Privacy Policy', 'motopress-hotel-booking');
    }

    $privacyPageId = get_option('wp_page_for_privacy_policy');

    if (!$privacyPageId) {
        return '';
    }

    $privacyPageUrl = get_permalink($privacyPageId);
    $classes = 'shaped-privacy-link modal-trigger';

    if (!empty($class)) {
        $classes .= ' ' . esc_attr($class);
    }

    return sprintf(
        '<a class="%s" href="%s" data-modal="privacy" data-page-id="%d">%s</a>',
        esc_attr($classes),
        esc_url($privacyPageUrl),
        absint($privacyPageId),
        esc_html($label)
    );
}
