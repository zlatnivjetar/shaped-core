<?php
/**
 * Provider Badge Shortcode
 * [shaped_provider_badge provider="booking" rating="9.2"]
 *
 * @package Shaped_Core
 */

if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('shaped_provider_badge', 'shaped_provider_badge_shortcode');

/**
 * Render provider badge
 *
 * @param array $atts Shortcode attributes
 * @return string HTML output
 */
function shaped_provider_badge_shortcode($atts): string {
    $atts = shortcode_atts([
        'provider' => 'booking',
        'rating'   => '9.0',
        'reviews'  => '',
        'class'    => '',
    ], $atts, 'shaped_provider_badge');

    $provider = sanitize_key($atts['provider']);
    $rating = floatval($atts['rating']);
    $reviews = sanitize_text_field($atts['reviews']);
    $custom_class = sanitize_html_class($atts['class']);

    // Get provider info
    $providers = shaped_get_provider_info();
    if (!isset($providers[$provider])) {
        return '';
    }

    $provider_data = $providers[$provider];
    $provider_name = $provider_data['name'];
    $provider_color = $provider_data['color'];
    $provider_url = $provider_data['url'];

    // Generate star rating HTML
    $stars_html = shaped_generate_star_rating($rating);

    // Build output
    ob_start();
    ?>
    <div class="shaped-provider-badge <?php echo esc_attr($custom_class); ?>" data-provider="<?php echo esc_attr($provider); ?>">
        <div class="provider-header" style="background-color: <?php echo esc_attr($provider_color); ?>">
            <span class="provider-name"><?php echo esc_html($provider_name); ?></span>
        </div>
        <div class="provider-rating">
            <div class="rating-stars"><?php echo $stars_html; ?></div>
            <div class="rating-score">
                <strong><?php echo esc_html(number_format($rating, 1)); ?></strong>
                <?php if ($reviews): ?>
                <span class="review-count">(<?php echo esc_html($reviews); ?> reviews)</span>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($provider_url): ?>
        <a href="<?php echo esc_url($provider_url); ?>" target="_blank" rel="noopener noreferrer" class="provider-link">
            View on <?php echo esc_html($provider_name); ?>
        </a>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Get provider information
 */
function shaped_get_provider_info(): array {
    return apply_filters('shaped/provider_badge/providers', [
        'booking' => [
            'name'  => 'Booking.com',
            'color' => '#003580',
            'url'   => '',
        ],
        'airbnb' => [
            'name'  => 'Airbnb',
            'color' => '#FF5A5F',
            'url'   => '',
        ],
        'tripadvisor' => [
            'name'  => 'TripAdvisor',
            'color' => '#00AF87',
            'url'   => '',
        ],
        'expedia' => [
            'name'  => 'Expedia',
            'color' => '#FFCB00',
            'url'   => '',
        ],
        'google' => [
            'name'  => 'Google',
            'color' => '#4285F4',
            'url'   => '',
        ],
    ]);
}

/**
 * Generate star rating HTML
 */
function shaped_generate_star_rating(float $rating): string {
    $max_stars = 10;
    $stars_to_show = 5;
    $normalized_rating = ($rating / $max_stars) * $stars_to_show;

    $full_stars = floor($normalized_rating);
    $half_star = ($normalized_rating - $full_stars) >= 0.5 ? 1 : 0;
    $empty_stars = $stars_to_show - $full_stars - $half_star;

    $html = '<span class="star-rating" data-rating="' . esc_attr($rating) . '">';

    // Full stars
    for ($i = 0; $i < $full_stars; $i++) {
        $html .= '<span class="star star-full">★</span>';
    }

    // Half star
    if ($half_star) {
        $html .= '<span class="star star-half">★</span>';
    }

    // Empty stars
    for ($i = 0; $i < $empty_stars; $i++) {
        $html .= '<span class="star star-empty">☆</span>';
    }

    $html .= '</span>';

    return $html;
}
