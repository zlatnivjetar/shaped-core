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
    // Flag that the shortcode is being used (for CSS enqueuing)
    add_action('wp_footer', 'shaped_enqueue_room_cards_css', 1);

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

    // Filter by specific IDs if provided
    if (!empty($atts['ids'])) {
        $ids = array_map('intval', explode(',', $atts['ids']));
        $query_args['post__in'] = $ids;
        $query_args['orderby'] = 'post__in'; // Maintain order of IDs
    }

    // Run query
    $rooms_query = new WP_Query($query_args);

    // Build wrapper classes
    $wrapper_classes = ['shaped-room-cards-wrapper', 'template-' . $template];
    if (!empty($atts['class'])) {
        $wrapper_classes[] = sanitize_html_class($atts['class']);
    }

    // Start output buffering
    ob_start();

    if ($rooms_query->have_posts()) {
        echo '<div class="' . esc_attr(implode(' ', $wrapper_classes)) . '">';

        while ($rooms_query->have_posts()) {
            $rooms_query->the_post();
            $room_type = get_post();

            // Load the appropriate template
            $template_file = SHAPED_DIR . 'templates/room-card-' . $template . '.php';

            if (file_exists($template_file)) {
                include $template_file;
            }
        }

        echo '</div>';

        // Reset post data
        wp_reset_postdata();
    }

    return ob_get_clean();
}

/**
 * Enqueue search-results.css when shortcode is used
 */
function shaped_enqueue_room_cards_css() {
    if (file_exists(SHAPED_DIR . 'assets/css/search-results.css')) {
        wp_enqueue_style(
            'shaped-search-results',
            SHAPED_URL . 'assets/css/search-results.css',
            [],
            SHAPED_VERSION
        );
    }
}

/**
 * Legacy shortcode alias for backward compatibility
 */
add_shortcode('pre_room_cards', function($atts) {
    return shaped_room_cards_shortcode($atts);
});
