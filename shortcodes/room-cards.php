<?php
/**
 * Room Cards Shortcode
 *
 * Display room cards using the refactored templates.
 *
 * Usage:
 *   [shaped_room_cards] - Show all rooms with homepage template
 *   [shaped_room_cards template="listing"] - Show all rooms with listing template
 *   [shaped_room_cards ids="94,95,96"] - Show specific rooms by ID
 *   [shaped_room_cards limit="3"] - Limit number of rooms
 *   [shaped_room_cards template="home" class="my-custom-class"] - Add custom wrapper class
 *
 * @package ShapedCore
 */

if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('shaped_room_cards', 'shaped_room_cards_shortcode');

function shaped_room_cards_shortcode($atts) {
    // Debug: Log that shortcode was called
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[Shaped Debug] shaped_room_cards shortcode called with atts: ' . print_r($atts, true));
    }

    $atts = shortcode_atts([
        'template' => 'home',        // 'home' or 'listing'
        'ids'      => '',            // Comma-separated room IDs
        'limit'    => -1,            // Number of rooms to show (-1 = all)
        'orderby'  => 'menu_order',  // Order by: menu_order, title, date, rand
        'order'    => 'ASC',         // ASC or DESC
        'class'    => '',            // Additional wrapper classes
    ], $atts);

    // Validate template
    $template = in_array($atts['template'], ['home', 'listing']) ? $atts['template'] : 'home';

    // Build query args
    $query_args = [
        'post_type'      => 'mphb_room_type',
        'posts_per_page' => (int) $atts['limit'],
        'orderby'        => sanitize_key($atts['orderby']),
        'order'          => strtoupper($atts['order']) === 'DESC' ? 'DESC' : 'ASC',
        'post_status'    => 'publish',
    ];

    // Debug: Log query args
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[Shaped Debug] Query args: ' . print_r($query_args, true));
    }

    // Filter by specific IDs if provided
    if (!empty($atts['ids'])) {
        $ids = array_map('intval', explode(',', $atts['ids']));
        $query_args['post__in'] = $ids;
        $query_args['orderby'] = 'post__in'; // Maintain order of IDs
    }

    // Run query
    $rooms_query = new WP_Query($query_args);

    // Debug: Log query results
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[Shaped Debug] Found ' . $rooms_query->found_posts . ' rooms');
        error_log('[Shaped Debug] Post count: ' . $rooms_query->post_count);
    }

    // Build wrapper classes
    $wrapper_classes = ['shaped-room-cards-wrapper', 'template-' . $template];
    if (!empty($atts['class'])) {
        $wrapper_classes[] = sanitize_html_class($atts['class']);
    }

    // Start output buffering
    ob_start();

    // Debug: Add visible marker when WP_DEBUG is on
    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY) {
        echo '<!-- Shaped Room Cards Shortcode Debug: Template=' . esc_html($template) . ', Found=' . $rooms_query->found_posts . ' rooms -->';
    }

    if ($rooms_query->have_posts()) {
        echo '<div class="' . esc_attr(implode(' ', $wrapper_classes)) . '">';

        while ($rooms_query->have_posts()) {
            $rooms_query->the_post();
            $room_type = get_post();

            // Debug: Log which room is being processed
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Shaped Debug] Processing room: ' . $room_type->post_title . ' (ID: ' . $room_type->ID . ')');
            }

            // Load the appropriate template
            $template_file = SHAPED_DIR . 'templates/room-card-' . $template . '.php';

            // Debug: Log template path
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Shaped Debug] Template file: ' . $template_file);
                error_log('[Shaped Debug] Template exists: ' . (file_exists($template_file) ? 'yes' : 'no'));
            }

            if (file_exists($template_file)) {
                include $template_file;
            } else {
                echo '<p>Template not found: room-card-' . esc_html($template) . '.php</p>';
            }
        }

        echo '</div>';

        // Reset post data
        wp_reset_postdata();
    } else {
        echo '<p class="shaped-no-rooms">No rooms available.</p>';

        // Debug: Log why no rooms found
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Shaped Debug] No rooms found. SQL query: ' . $rooms_query->request);
        }
    }

    $output = ob_get_clean();

    // Debug: Log final output length
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[Shaped Debug] Output length: ' . strlen($output) . ' characters');
    }

    return $output;
}

/**
 * Legacy shortcode alias for backward compatibility
 */
add_shortcode('pre_room_cards', function($atts) {
    return shaped_room_cards_shortcode($atts);
});
