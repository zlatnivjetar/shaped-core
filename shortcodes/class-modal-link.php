<?php
/**
 * Modal Link Shortcode
 * [shaped_modal page="terms" label="Terms & Conditions"]
 *
 * @package Shaped_Core
 */

if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('shaped_modal', 'shaped_modal_link_shortcode');

/**
 * Render modal link
 *
 * @param array $atts Shortcode attributes
 * @return string HTML output
 */
function shaped_modal_link_shortcode($atts): string {
    $atts = shortcode_atts([
        'page'  => '',
        'label' => '',
        'class' => '',
    ], $atts, 'shaped_modal');

    $page_key = sanitize_key($atts['page']);
    $label = sanitize_text_field($atts['label']);
    $custom_class = sanitize_html_class($atts['class']);

    if (empty($page_key) || empty($label)) {
        return '';
    }

    // Get page ID from settings
    if (!class_exists('Shaped_Admin')) {
        return '<span class="shaped-error">Admin class not loaded</span>';
    }

    $page_id = Shaped_Admin::get_modal_page($page_key);

    if (!$page_id) {
        return '<span class="shaped-error">Modal page "' . esc_html($page_key) . '" not configured</span>';
    }

    $page = get_post($page_id);
    if (!$page || $page->post_status !== 'publish') {
        return '<span class="shaped-error">Modal page not found</span>';
    }

    // Build modal link
    $classes = ['shaped-modal-link'];
    if ($custom_class) {
        $classes[] = $custom_class;
    }

    return sprintf(
        '<a href="#" class="%s" data-modal-page="%s" data-page-id="%d">%s</a>',
        esc_attr(implode(' ', $classes)),
        esc_attr($page_key),
        absint($page_id),
        esc_html($label)
    );
}
