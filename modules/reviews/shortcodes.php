<?php
/**
 * Review Shortcodes
 * 
 * @package Shaped_Core
 * @subpackage Reviews
 */

namespace Shaped\Modules\Reviews;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Provider configurations (shared)
 */
function get_provider_configs(): array {
    return [
        'booking'     => ['name' => 'Booking', 'bg' => '#003580', 'text' => '#ffffff', 'scale' => 10],
        'expedia'     => ['name' => 'Expedia', 'bg' => '#ffda00', 'text' => '#000000', 'scale' => 10],
        'tripadvisor' => ['name' => 'TripAdvisor', 'bg' => '#00af87', 'text' => '#ffffff', 'scale' => 5],
        'google'      => ['name' => 'Google', 'bg' => '#4285f4', 'text' => '#ffffff', 'scale' => 5],
        'airbnb'      => ['name' => 'Airbnb', 'bg' => '#ff385c', 'text' => '#ffffff', 'scale' => 5],
        'direct'      => ['name' => 'Direct', 'bg' => '#6366f1', 'text' => '#ffffff', 'scale' => 10],
    ];
}

/**
 * Provider links (filterable per property)
 */
function get_provider_links(): array {
    return apply_filters('shaped/reviews/provider_links', [
        'booking'     => '#',
        'tripadvisor' => '#',
        'expedia'     => '#',
        'google'      => '#',
        'airbnb'      => '#',
        'direct'      => '#',
    ]);
}

/**
 * Normalize provider name
 */
function normalize_provider(string $provider): string {
    $provider = strtolower(trim(str_replace(['-', '_'], ' ', $provider)));
    return str_replace(' ', '-', $provider);
}

/**
 * [shaped_unified_rating] - Star rating display
 */
add_shortcode('shaped_unified_rating', function() {
    $rating = get_post_meta(get_the_ID(), 'review_rating', true);
    $provider = get_post_meta(get_the_ID(), 'provider', true);

    if (empty($rating) || empty($provider)) {
        return '<div class="shaped-rating-unified"><div class="shaped-rating-error">Rating unavailable</div></div>';
    }

    $rating = floatval($rating);
    if ($rating <= 0) {
        return '<div class="shaped-rating-unified"><div class="shaped-rating-error">No rating</div></div>';
    }

    $provider_key = normalize_provider($provider);
    $configs = get_provider_configs();
    $config = $configs[$provider_key] ?? ['scale' => 10];
    
    $is_five_scale = $config['scale'] === 5;

    if ($is_five_scale) {
        $rating = min($rating, 5);
        $display_value = ($rating == intval($rating)) ? intval($rating) : number_format($rating, 1);
        $numeric_display = $display_value . '/5';
        $star_rating = round($rating * 2) / 2;
    } else {
        $rating = min($rating, 10);
        $numeric_display = intval(round($rating)) . '/10';
        $star_rating = round(($rating / 2) * 2) / 2;
    }

    // Generate stars
    $stars_html = '';
    $full_stars = floor($star_rating);
    $has_half = ($star_rating - $full_stars) >= 0.5;

    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $full_stars) {
            $stars_html .= '<span class="shaped-star shaped-star-full">★</span>';
        } elseif ($i == $full_stars + 1 && $has_half) {
            $stars_html .= '<span class="shaped-star shaped-star-half">★</span>';
        } else {
            $stars_html .= '<span class="shaped-star shaped-star-empty">★</span>';
        }
    }

    return sprintf(
        '<div class="shaped-rating-unified">
            <div class="shaped-rating-stars">%s</div>
            <div class="shaped-rating-numeric">%s</div>
        </div>',
        $stars_html,
        esc_html($numeric_display)
    );
});

// Legacy alias
add_shortcode('unified_rating', function() {
    return do_shortcode('[shaped_unified_rating]');
});

/**
 * [shaped_review_author] - Author name
 */
add_shortcode('shaped_review_author', function() {
    $author = get_post_meta(get_the_ID(), 'author_name', true);
    return !empty($author) ? esc_html($author) : 'Guest';
});

// Legacy alias
add_shortcode('review_author', function() {
    return do_shortcode('[shaped_review_author]');
});

/**
 * [shaped_review_date] - Formatted review date
 */
add_shortcode('shaped_review_date', function($atts) {
    $atts = shortcode_atts([
        'format' => 'd.m.Y'
    ], $atts);

    $date = get_post_meta(get_the_ID(), 'review_date', true);

    if (empty($date)) {
        return '<span class="shaped-review-date">Date unavailable</span>';
    }

    $formatted = date($atts['format'], strtotime($date));
    return '<span class="shaped-review-date">' . esc_html($formatted) . '</span>';
});

// Legacy alias
add_shortcode('review_date', function() {
    return do_shortcode('[shaped_review_date]');
});

/**
 * [shaped_provider_badge] - Provider badge with link
 */
add_shortcode('shaped_provider_badge', function() {
    $provider = get_post_meta(get_the_ID(), 'provider', true);

    if (empty($provider)) {
        return '<span class="shaped-provider-badge" style="background-color: #666; color: #fff;">Unknown</span>';
    }

    $provider_key = normalize_provider($provider);
    $configs = get_provider_configs();
    $links = get_provider_links();

    $config = $configs[$provider_key] ?? [
        'name' => ucfirst($provider),
        'bg'   => '#666',
        'text' => '#fff'
    ];

    $url = $links[$provider_key] ?? '#';

    return sprintf(
        '<a href="%s" target="_blank" rel="noopener" class="shaped-provider-badge" 
            style="background-color: %s; color: %s;">%s</a>',
        esc_url($url),
        esc_attr($config['bg']),
        esc_attr($config['text']),
        esc_html($config['name'])
    );
});

// Legacy alias
add_shortcode('provider_badge_v2', function() {
    return do_shortcode('[shaped_provider_badge]');
});

/**
 * [shaped_review_content] - Review text with read more
 */
add_shortcode('shaped_review_content', function($atts) {
    $atts = shortcode_atts([
        'desktop_limit' => 145,
        'mobile_limit'  => 135,
    ], $atts);

    $content = get_the_content();

    if (empty($content)) {
        return '<div class="shaped-review-text">No review content available.</div>';
    }

    $content = wp_strip_all_tags($content);
    $char_limit_desktop = intval($atts['desktop_limit']);
    $char_limit_mobile = intval($atts['mobile_limit']);

    // Check if truncation needed
    if (strlen($content) <= $char_limit_mobile) {
        return '<div class="shaped-review-text">' . esc_html($content) . '</div>';
    }

    // Desktop truncated
    $truncated_desktop = substr($content, 0, $char_limit_desktop);
    $last_space = strrpos($truncated_desktop, ' ');
    if ($last_space !== false && $last_space > ($char_limit_desktop * 0.8)) {
        $truncated_desktop = substr($truncated_desktop, 0, $last_space);
    }

    // Mobile truncated
    $truncated_mobile = substr($content, 0, $char_limit_mobile);
    $last_space = strrpos($truncated_mobile, ' ');
    if ($last_space !== false && $last_space > ($char_limit_mobile * 0.8)) {
        $truncated_mobile = substr($truncated_mobile, 0, $last_space);
    }

    $unique_id = 'review-' . get_the_ID();

    return sprintf(
        '<div class="shaped-review-content-wrapper" id="%s">
            <div class="shaped-review-text">
                <span class="shaped-text-truncated shaped-desktop-truncated">%s...</span>
                <span class="shaped-text-truncated shaped-mobile-truncated">%s...</span>
                <span class="shaped-text-full" style="display:none;">%s</span>
            </div>
            <button class="shaped-read-more-btn" onclick="shapedToggleReadMore(\'%s\')">Read more</button>
        </div>',
        esc_attr($unique_id),
        esc_html($truncated_desktop),
        esc_html($truncated_mobile),
        esc_html($content),
        esc_attr($unique_id)
    );
});

// Legacy alias
add_shortcode('review_content', function() {
    return do_shortcode('[shaped_review_content]');
});
