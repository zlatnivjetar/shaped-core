<?php
/**
 * Room Cards Shortcode
 *
 * Display room cards using the refactored templates.
 *
 * Usage:
 *   [shaped_room_cards] - Show all rooms with homepage template
 *   [shaped_room_cards template="listing"] - Show all rooms with listing template
 *   [shaped_room_cards template="landing"] - Compact cards for landing pages
 *   [shaped_room_cards template="search"] - Search results with date-aware pricing
 *   [shaped_room_cards ids="94,95,96"] - Show specific rooms by ID
 *   [shaped_room_cards limit="3"] - Limit number of rooms
 *   [shaped_room_cards template="home" class="my-custom-class"] - Add custom wrapper class
 *
 * Search template also accepts:
 *   [shaped_room_cards template="search" check_in="2025-07-01" check_out="2025-07-04" adults="2"]
 *   Dates auto-detected from URL params (mphb_check_in_date, mphb_check_out_date) when not specified.
 *
 * @package ShapedCore
 */

if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('shaped_room_cards', 'shaped_room_cards_shortcode');

function shaped_room_cards_shortcode($atts) {
    $atts = shortcode_atts([
        'template'  => 'home',        // 'home', 'listing', 'landing', or 'search'
        'ids'       => '',            // Comma-separated room IDs
        'limit'     => -1,            // Number of rooms to show (-1 = all)
        'orderby'   => 'menu_order',  // Order by: menu_order, title, date, rand
        'order'     => 'ASC',         // ASC or DESC
        'class'     => '',            // Additional wrapper classes
        'check_in'  => '',            // Check-in date (Y-m-d) — search template
        'check_out' => '',            // Check-out date (Y-m-d) — search template
        'adults'    => '',            // Number of adults — search template
        'children'  => '',            // Number of children — search template
    ], $atts);

    // Validate template
    $valid_templates = ['home', 'listing', 'landing', 'search'];
    $template = in_array($atts['template'], $valid_templates) ? $atts['template'] : 'home';

    // Enqueue appropriate assets
    if ($template === 'landing') {
        add_action('wp_footer', 'shaped_enqueue_room_cards_landing_assets', 1);
    } elseif ($template === 'search') {
        add_action('wp_footer', 'shaped_enqueue_room_cards_css', 1);
        add_action('wp_footer', 'shaped_enqueue_room_cards_search_assets', 1);
    } else {
        add_action('wp_footer', 'shaped_enqueue_room_cards_css', 1);
    }

    // For search template, resolve date/guest context from shortcode attrs or URL params
    $search_context = null;
    if ($template === 'search') {
        $search_context = shaped_resolve_search_context($atts);
    }

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
    if ($template === 'search') {
        $wrapper_classes[] = 'mphb_sc_search_results-wrapper';
    }
    if (!empty($atts['class'])) {
        $wrapper_classes[] = sanitize_html_class($atts['class']);
    }

    // Start output buffering
    ob_start();

    if ($rooms_query->have_posts()) {
        echo '<div class="' . esc_attr(implode(' ', $wrapper_classes)) . '">';

        // Search results info bar (matches MPHB's mphb_sc_search_results-info)
        if ($template === 'search' && !empty($search_context['check_in']) && !empty($search_context['check_out'])) {
            $ci_dt = \DateTime::createFromFormat('Y-m-d', $search_context['check_in']);
            $co_dt = \DateTime::createFromFormat('Y-m-d', $search_context['check_out']);

            if ($ci_dt && $co_dt) {
                if (class_exists('\\MPHB\\Utils\\DateUtils')) {
                    $formatted_in  = \MPHB\Utils\DateUtils::formatDateWPFront($ci_dt);
                    $formatted_out = \MPHB\Utils\DateUtils::formatDateWPFront($co_dt);
                } else {
                    $wp_fmt        = get_option('date_format');
                    $formatted_in  = date_i18n($wp_fmt, $ci_dt->getTimestamp() + $ci_dt->getOffset());
                    $formatted_out = date_i18n($wp_fmt, $co_dt->getTimestamp() + $co_dt->getOffset());
                }

                printf(
                    '<p class="mphb_sc_search_results-info">%s</p>',
                    esc_html(sprintf(
                        __('Available units from %s until %s', 'motopress-hotel-booking'),
                        $formatted_in,
                        $formatted_out
                    ))
                );
            }
        }

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
 * Resolve search context from shortcode attributes and URL parameters
 *
 * Priority: shortcode attribute > MPHB URL params > empty
 *
 * @param array $atts Shortcode attributes
 * @return array Search context with keys: check_in, check_out, adults, children
 */
function shaped_resolve_search_context(array $atts): array {
    // Check-in: shortcode attr > MPHB URL param
    $check_in = !empty($atts['check_in'])
        ? sanitize_text_field($atts['check_in'])
        : sanitize_text_field($_GET['mphb_check_in_date'] ?? '');

    // Check-out: shortcode attr > MPHB URL param
    $check_out = !empty($atts['check_out'])
        ? sanitize_text_field($atts['check_out'])
        : sanitize_text_field($_GET['mphb_check_out_date'] ?? '');

    // Normalize date formats: MPHB uses DD/MM/YYYY, we need Y-m-d
    $check_in  = shaped_normalize_date($check_in);
    $check_out = shaped_normalize_date($check_out);

    // Adults: shortcode attr > MPHB URL param > default 2
    $adults = !empty($atts['adults'])
        ? (int) $atts['adults']
        : (int) ($_GET['mphb_adults'] ?? 2);

    // Children: shortcode attr > MPHB URL param > default 0
    $children = !empty($atts['children'])
        ? (int) $atts['children']
        : (int) ($_GET['mphb_children'] ?? 0);

    return [
        'check_in'  => $check_in,
        'check_out' => $check_out,
        'adults'    => max(1, $adults),
        'children'  => max(0, $children),
    ];
}

/**
 * Normalize date to Y-m-d format
 *
 * Handles DD/MM/YYYY (MPHB default) and Y-m-d formats.
 *
 * @param string $date Date string
 * @return string Date in Y-m-d format, or empty string if invalid
 */
function shaped_normalize_date(string $date): string {
    if (empty($date)) {
        return '';
    }

    // Already Y-m-d
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return $date;
    }

    // DD/MM/YYYY
    if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $date, $m)) {
        return sprintf('%s-%s-%s', $m[3], $m[2], $m[1]);
    }

    // Try DateTime as last resort
    try {
        return (new DateTime($date))->format('Y-m-d');
    } catch (Exception $e) {
        return '';
    }
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
 * Enqueue search template dependencies (checkout.js + room modal)
 *
 * The search template needs checkout.js for discount/urgency badge injection
 * and the room modal JS/CSS for the detail overlay. This ensures assets load
 * even if class-assets.php page detection doesn't trigger (e.g. page without
 * MPHB URL params).
 */
function shaped_enqueue_room_cards_search_assets() {
    // Checkout JS (discount badges, urgency badges, price formatting)
    if (file_exists(SHAPED_DIR . 'assets/js/checkout.js')) {
        wp_enqueue_script(
            'shaped-checkout',
            SHAPED_URL . 'assets/js/checkout.js',
            ['jquery'],
            SHAPED_VERSION,
            true
        );
    }

    // Room modal CSS
    if (file_exists(SHAPED_DIR . 'assets/css/room-modal.css')) {
        wp_enqueue_style(
            'shaped-room-modal',
            SHAPED_URL . 'assets/css/room-modal.css',
            [],
            SHAPED_VERSION
        );
    }

    // Room modal JS
    if (file_exists(SHAPED_DIR . 'assets/js/room-modal.js')) {
        wp_enqueue_script(
            'shaped-room-modal',
            SHAPED_URL . 'assets/js/room-modal.js',
            [],
            SHAPED_VERSION,
            true
        );
    }
}

/**
 * Enqueue landing card CSS when landing template is used
 */
function shaped_enqueue_room_cards_landing_assets() {
    if (file_exists(SHAPED_DIR . 'assets/css/room-cards-landing.css')) {
        wp_enqueue_style(
            'shaped-room-cards-landing',
            SHAPED_URL . 'assets/css/room-cards-landing.css',
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
