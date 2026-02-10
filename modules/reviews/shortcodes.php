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
 * Get provider logo URLs
 */
function get_provider_logo_urls(): array {
    $base = plugin_dir_url(__FILE__) . 'assets/logos/';
    return [
        'booking'     => $base . 'bookinglogo.png',
        'expedia'     => $base . 'expedialogo.png',
        'tripadvisor' => $base . 'tripadvisorlogo.png',
        'google'      => $base . 'googlelogo.png',
        'airbnb'      => $base . 'airbnblogo.png',
    ];
}

/**
 * Render provider badge as logo image
 */
function render_provider_badge_html(string $provider_key, string $tag = 'span', array $link_attrs = []): string {
    $configs = get_provider_configs();
    $logos = get_provider_logo_urls();

    $config = $configs[$provider_key] ?? ['name' => ucfirst($provider_key)];
    $name = $config['name'] ?? ucfirst($provider_key);

    if (isset($logos[$provider_key])) {
        $img = sprintf(
            '<img src="%s" alt="%s" class="shaped-provider-logo" />',
            esc_url($logos[$provider_key]),
            esc_attr($name)
        );
    } else {
        $img = esc_html($name);
    }

    $attrs = sprintf('class="shaped-provider-badge" data-provider="%s"', esc_attr($provider_key));
    foreach ($link_attrs as $k => $v) {
        $attrs .= sprintf(' %s="%s"', esc_attr($k), esc_attr($v));
    }

    return sprintf('<%s %s>%s</%s>', $tag, $attrs, $img, $tag);
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
        return '<span class="shaped-provider-badge">Unknown</span>';
    }

    $provider_key = normalize_provider($provider);
    $links = get_provider_links();
    $url = $links[$provider_key] ?? '#';

    return render_provider_badge_html($provider_key, 'a', [
        'href'   => esc_url($url),
        'target' => '_blank',
        'rel'    => 'noopener',
    ]);
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

/**
 * [shaped_reviews] - Standalone reviews grid with filters and pagination
 *
 * Renders a complete review display without Elementor dependency.
 * Includes provider filter buttons, 3-column grid, and Load More pagination.
 *
 * Usage: [shaped_reviews]
 *
 * The grid respects URL parameter ?provider=<slug> for direct linking to filtered views.
 */
add_shortcode('shaped_reviews', function() {
    // Check for provider filter in URL
    $provider = isset($_GET['provider']) ? sanitize_text_field($_GET['provider']) : 'all';

    // Validate provider
    $valid_providers = Frontend::PROVIDER_ORDER;
    if ($provider !== 'all' && !in_array($provider, $valid_providers, true)) {
        $provider = 'all';
    }

    return Frontend::render_grid($provider, 1);
});

/**
 * [shaped_review_strip] - Single provider review strip for header/footer
 *
 * Displays a provider badge with star rating and numeric score.
 * Ratings are pulled from admin settings (Guest Reviews > Review Strip).
 *
 * Usage: [shaped_review_strip provider="booking"]
 *
 * @param string $provider   Provider key: booking, expedia, google, tripadvisor, airbnb, direct
 * @param bool   $show_stars Whether to show star icons (default: true)
 */
add_shortcode('shaped_review_strip', function($atts) {
    $atts = shortcode_atts([
        'provider'   => 'booking',
        'show_stars' => 'true',
    ], $atts);

    $provider_key = normalize_provider($atts['provider']);
    $show_stars = filter_var($atts['show_stars'], FILTER_VALIDATE_BOOLEAN);

    // Get provider config
    $configs = get_provider_configs();
    if (!isset($configs[$provider_key])) {
        return '<!-- Invalid provider: ' . esc_html($provider_key) . ' -->';
    }

    $config = $configs[$provider_key];
    $scale = $config['scale'];

    // Get rating from admin settings
    $rating_data = null;
    if (class_exists('Shaped_Review_Strip_Settings')) {
        $rating_data = \Shaped_Review_Strip_Settings::get_provider_rating($provider_key);
    }

    // Fall back to defaults if not configured
    if (!$rating_data || !isset($rating_data['rating'])) {
        $defaults = [
            'booking'     => 9.0,
            'expedia'     => 9.4,
            'google'      => 4.6,
            'tripadvisor' => 4.5,
            'airbnb'      => 4.8,
            'direct'      => 9.5,
        ];
        $rating = $defaults[$provider_key] ?? 0;
    } else {
        $rating = floatval($rating_data['rating']);
    }

    // Calculate star rating (convert 10-scale to 5 stars)
    if ($scale === 10) {
        $star_rating = $rating / 2;
        $display_rating = number_format($rating, 1) . '/10';
    } else {
        $star_rating = $rating;
        $display_rating = number_format($rating, 1) . '/5';
    }

    // Generate stars HTML
    $stars_html = '';
    if ($show_stars) {
        $full_stars = floor($star_rating);
        $partial = $star_rating - $full_stars;
        $partial_percent = round($partial * 100);

        for ($i = 0; $i < 5; $i++) {
            if ($i < $full_stars) {
                $stars_html .= '<span class="star full">&#9733;</span>';
            } elseif ($i === $full_stars && $partial >= 0.25) {
                $stars_html .= '<span class="star partial" style="--fill: ' . $partial_percent . '%">&#9733;</span>';
            } else {
                $stars_html .= '<span class="star empty">&#9733;</span>';
            }
        }
    }

    // Get provider link
    $links = get_provider_links();
    $url = $links[$provider_key] ?? '#';

    // Build HTML output matching existing structure
    $html = '<div class="shaped-review-strip">';
    $html .= '<div class="review-item">';

    // Provider badge (logo image)
    $html .= render_provider_badge_html($provider_key);

    // Star rating section
    $html .= '<div class="star-rating" data-rating="' . esc_attr($star_rating) . '">';

    if ($show_stars) {
        $html .= '<div class="stars-container">' . $stars_html . '</div>';
    }

    $html .= '<div class="rate-wrap">';
    $html .= '<span class="star full">&#9733;</span>';
    $html .= '<span class="rating-text">' . esc_html($display_rating) . '</span>';
    $html .= '</div>';

    $html .= '</div>'; // .star-rating
    $html .= '</div>'; // .review-item
    $html .= '</div>'; // .shaped-review-strip

    return $html;
});

/**
 * [shaped_review_strips] - Grid of multiple review strips
 *
 * Displays 2 or 3 provider review strips in a responsive grid.
 * Use this in Elementor shortcode widget to replace manual HTML.
 *
 * Usage: [shaped_review_strips providers="booking,expedia,google"]
 *        [shaped_review_strips providers="booking,google"]
 *
 * @param string $providers Comma-separated list of provider keys
 */
add_shortcode('shaped_review_strips', function($atts) {
    $atts = shortcode_atts([
        'providers' => 'booking,expedia,google',
    ], $atts);

    // Parse providers
    $provider_list = array_map('trim', explode(',', $atts['providers']));
    $provider_list = array_filter($provider_list);

    if (empty($provider_list)) {
        return '<!-- No providers specified -->';
    }

    // Validate providers
    $valid_providers = array_keys(get_provider_configs());
    $provider_list = array_filter($provider_list, function($p) use ($valid_providers) {
        return in_array(normalize_provider($p), $valid_providers, true);
    });

    if (empty($provider_list)) {
        return '<!-- No valid providers specified -->';
    }

    // Determine column count
    $columns = count($provider_list);
    if ($columns > 3) {
        $columns = 3; // Max 3 columns
    }

    // Build grid HTML
    $html = '<div class="shaped-review-strips-grid" data-columns="' . esc_attr($columns) . '">';

    foreach ($provider_list as $provider) {
        $html .= '<div class="shaped-strip-column">';
        $html .= do_shortcode('[shaped_review_strip provider="' . esc_attr($provider) . '"]');
        $html .= '</div>';
    }

    $html .= '</div>';

    return $html;
});
