<?php
/**
 * Preelook Review Display System - Streamlined Version
 * File: /wp-content/plugins/preelook-reviews-sync/includes/review-enhancements.php
 */

// ===============================
// RATING DISPLAY
// ===============================

function prs_get_unified_rating($rating, $provider) {
    $rating = floatval($rating);
    
    if ($rating <= 0) {
        return '<div class="prs-rating-unified"><div class="prs-rating-error">No rating</div></div>';
    }
    
    $provider = strtolower(trim($provider));
    
    // Normalize provider variations
    $provider_map = [
        'google' => 'google-maps',
        'google maps' => 'google-maps',
        'googlemaps' => 'google-maps',
        'booking.com' => 'booking'
    ];
    $provider = $provider_map[$provider] ?? $provider;
    
    // Determine scale
    $is_five_scale = in_array($provider, ['google-maps', 'tripadvisor']);
    
    if ($is_five_scale) {
        $rating = min($rating, 5);
        $numeric_value = floatval($rating);
        $display_value = ($numeric_value == intval($numeric_value)) 
            ? intval($numeric_value) 
            : number_format($numeric_value, 1);
        $numeric_display = $display_value . '/5';
        $star_rating = round($rating * 2) / 2;
    } else {
        $rating = min($rating, 10);
        $numeric_display = intval(round($rating)) . '/10';
        $star_rating = round((floatval($rating) / 2) * 2) / 2;
    }
    
    // Generate stars HTML
    $stars_html = '';
    $full_stars = floor($star_rating);
    $has_half = ($star_rating - $full_stars) >= 0.5;
    
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $full_stars) {
            $stars_html .= '<span class="prs-star prs-star-full">★</span>';
        } elseif ($i == $full_stars + 1 && $has_half) {
            $stars_html .= '<span class="prs-star prs-star-half">★</span>';
        } else {
            $stars_html .= '<span class="prs-star prs-star-empty">★</span>';
        }
    }
    
    return sprintf(
        '<div class="prs-rating-unified">
            <div class="prs-rating-stars">%s</div>
            <div class="prs-rating-numeric">%s</div>
        </div>',
        $stars_html,
        esc_html($numeric_display)
    );
}

add_shortcode('unified_rating', function() {
    $rating = get_post_meta(get_the_ID(), 'review_rating', true);
    $provider = get_post_meta(get_the_ID(), 'provider', true);
    
    if (empty($rating) || empty($provider)) {
        return '<div class="prs-rating-unified"><div class="prs-rating-error">Rating unavailable</div></div>';
    }
    
    return prs_get_unified_rating($rating, $provider);
});

// ===============================
// AUTHOR NAME SHORTCODE
// ===============================

add_shortcode('review_author', function() {
    $author = get_post_meta(get_the_ID(), 'author_name', true);
    return !empty($author) ? esc_html($author) : 'Guest';
});

// ===============================
// REVIEW DATE SHORTCODE
// ===============================

add_shortcode('review_date', function() {
    $date = get_post_meta(get_the_ID(), 'review_date', true);
    
    if (empty($date)) {
        return '<span class="prs-review-date">Date unavailable</span>';
    }
    
    // Format date as d.m.Y per your existing pattern
    $formatted_date = date('d.m.Y', strtotime($date));
    return '<span class="prs-review-date">' . esc_html($formatted_date) . '</span>';
});

// ===============================
// PROVIDER BADGES
// ===============================

function prs_get_provider_badge($provider) {
    if (empty($provider)) {
        return '<span class="prs-provider-badge" style="background-color: #666666; color: #ffffff;">Unknown</span>';
    }
    
    $provider_normalized = strtolower(trim(str_replace(['-', '_'], ' ', $provider)));
    
    $configs = [
        'booking' => ['name' => 'Booking', 'bg' => '#003580', 'text' => '#ffffff'],
        'booking.com' => ['name' => 'Booking', 'bg' => '#003580', 'text' => '#ffffff'],
        'expedia' => ['name' => 'Expedia', 'bg' => '#ffda00', 'text' => '#000000'],
        'tripadvisor' => ['name' => 'TripAdvisor', 'bg' => '#00af87', 'text' => '#ffffff'],
        'google maps' => ['name' => 'Google', 'bg' => '#4285f4', 'text' => '#ffffff'],
        'google' => ['name' => 'Google', 'bg' => '#4285f4', 'text' => '#ffffff'],
        'googlemaps' => ['name' => 'Google', 'bg' => '#4285f4', 'text' => '#ffffff']
    ];
    
    $config = $configs[$provider_normalized] ?? [
        'name' => ucfirst($provider),
        'bg' => '#666666',
        'text' => '#ffffff'
    ];
    
    $links = [
        'booking' => 'https://www.booking.com/hotel/hr/preelook.html',
        'booking.com' => 'https://www.booking.com/hotel/hr/preelook.html',
        'tripadvisor' => 'https://www.tripadvisor.com/Hotel_Review-g297515-d12615209-Reviews-Apartments_Rooms_Preelook-Opatija_Primorje_Gorski_Kotar_County.html',
        'expedia' => 'https://www.expedia.com/Rijeka-Hotels-Preelook-Apartments-And-Rooms.h19283623.Hotel-Information',
        'google maps' => 'https://maps.app.goo.gl/tsxa5iKwYQqvtZ7R7',
        'google' => 'https://maps.app.goo.gl/tsxa5iKwYQqvtZ7R7',
        'googlemaps' => 'https://maps.app.goo.gl/tsxa5iKwYQqvtZ7R7'
    ];
    
    $url = $links[$provider_normalized] ?? '#';
    
    return sprintf(
        '<a href="%s" target="_blank" rel="noopener" class="prs-provider-badge" 
            style="background-color: %s; color: %s;">%s</a>',
        esc_url($url),
        esc_attr($config['bg']),
        esc_attr($config['text']),
        esc_html($config['name'])
    );
}

add_shortcode('provider_badge_v2', function() {
    $provider = get_post_meta(get_the_ID(), 'provider', true);
    return prs_get_provider_badge($provider);
});

// ===============================
// REVIEW CONTENT WITH READ MORE
// ===============================

add_shortcode('review_content', function() {
    $content = get_the_content();
    
    if (empty($content)) {
        return '<div class="prs-review-text">No review content available.</div>';
    }
    
    $content = wp_strip_all_tags($content);
    $char_limit_desktop = 145;
    $char_limit_mobile = 135;
    
    // Check if content needs truncation at all
    $needs_truncation = strlen($content) > $char_limit_mobile;
    
    if (!$needs_truncation) {
        return '<div class="prs-review-text">' . esc_html($content) . '</div>';
    }
    
    // Create desktop truncated version (145 chars)
    $truncated_desktop = substr($content, 0, $char_limit_desktop);
    $last_space_desktop = strrpos($truncated_desktop, ' ');
    if ($last_space_desktop !== false && $last_space_desktop > ($char_limit_desktop * 0.8)) {
        $truncated_desktop = substr($truncated_desktop, 0, $last_space_desktop);
    }
    
    // Create mobile truncated version (135 chars)
    $truncated_mobile = substr($content, 0, $char_limit_mobile);
    $last_space_mobile = strrpos($truncated_mobile, ' ');
    if ($last_space_mobile !== false && $last_space_mobile > ($char_limit_mobile * 0.8)) {
        $truncated_mobile = substr($truncated_mobile, 0, $last_space_mobile);
    }
    
    // Generate unique ID for this review
    $unique_id = 'review-' . get_the_ID();
    
    return sprintf(
        '<div class="prs-review-content-wrapper" id="%s">
            <div class="prs-review-text">
                <span class="prs-text-truncated prs-desktop-truncated">%s...</span>
                <span class="prs-text-truncated prs-mobile-truncated">%s...</span>
                <span class="prs-text-full" style="display:none;">%s</span>
            </div>
            <button class="prs-read-more-btn" onclick="prsToggleReadMore(\'%s\')">Read more</button>
        </div>',
        esc_attr($unique_id),
        esc_html($truncated_desktop),
        esc_html($truncated_mobile),
        esc_html($content),
        esc_attr($unique_id)
    );
});

// ===============================
// STYLES AND SCRIPTS
// ===============================

add_action('wp_head', function() {
    ?>
  <style>
    /* ===== REVIEW CARD CONTAINER FIXES ===== */
    .elementor-loop-container .elementor-loop-item {
        height: 100%;
    }
    
    /* ===== RATING STYLES ===== */
    .prs-rating-unified {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .prs-rating-stars {
        display: flex;
        gap: 1px;
        line-height: 1;
    }
    
    .prs-star {
        font-size: 1rem;
        display: inline-block;
    }
    
    @media (max-width: 767px) {
        .prs-star {
            font-size: 0.875rem;
        }
    }
    
    .prs-star-full {
        color: #D1AF5D !important;
    }
    
    .prs-star-half {
        position: relative;
        color: #e0e0e0 !important;
    }
    
    .prs-star-half::before {
        content: "★";
        position: absolute;
        left: 0;
        top: 0;
        width: 50%;
        overflow: hidden;
        color: #D1AF5D !important;
    }
    
    .prs-star-empty {
        color: #e0e0e0 !important;
    }
    
    .prs-rating-numeric {
        font-size: 0.875rem;
        font-weight: 700;
        color: #D1AF5D;
    }
    
    @media (max-width: 767px) {
        .prs-rating-numeric {
            font-size: 0.8125rem;
        }
    }
    
    /* ===== PROVIDER BADGE ===== */
    .prs-provider-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        letter-spacing: -0.4px;
        text-decoration: none;
        transition: opacity 200ms ease;
        white-space: nowrap;
    }
    
    .prs-provider-badge:hover {
        opacity: 0.9;
    }
    
    @media (max-width: 767px) {
        .prs-provider-badge {
            font-size: 11px;
            letter-spacing: 0;
            padding: 3px 8px;
        }
    }
    
    /* ===== REVIEW CONTENT WITH EXPANSION ===== */
    .prs-review-card-root {
        background: #ffffff;
        border-radius: 8px;
        padding: 24px 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.08) !important;
        transition: transform 200ms ease, box-shadow 200ms ease;
        min-height: 17.375rem;
        display: flex;
        flex-direction: column;
    }
    
    .prs-review-card-root:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.12) !important;
    }

    @media (max-width: 767px) {
        .prs-review-card-root {
            min-height:12.375rem;
            padding: 16px;
        }
    }
    
    .prs-review-content-wrapper {
        flex: 1;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    
    .prs-review-text {
        color: #666666;
        line-height: 1.5;
        font-size: 0.9375rem;
        flex: 1;
        overflow: hidden;
        transition: all 300ms ease;
    }
    
    /* Responsive truncated text display - CRITICAL FIX */
    .prs-mobile-truncated {
        display: none !important;
    }
    
    .prs-desktop-truncated {
        display: inline !important;
    }
    
    /* Hide both when full text is shown */
    .prs-review-content-wrapper.expanded .prs-text-truncated {
        display: none !important;
    }
    
    .prs-review-content-wrapper.expanded .prs-text-full {
        display: inline !important;
    }
    
    /* Mobile responsive display */
    @media (max-width: 479px) {
        .prs-mobile-truncated {
            display: inline !important;
        }
        
        .prs-desktop-truncated {
            display: none !important;
        }
        
        /* Ensure expanded state works on mobile */
        .prs-review-content-wrapper.expanded .prs-mobile-truncated {
            display: none !important;
        }
    }
    
    /* Expanded state with scroll */
    .prs-review-content-wrapper.expanded .prs-review-text {
        max-height: 7.5em;
        overflow-y: auto;
        padding-right: 8px;
        margin-right: -8px;
        scrollbar-width: thin;
        scrollbar-color: #D1AF5D20 #f0f0f0;
    }
    
    /* Webkit scrollbar styling */
    .prs-review-content-wrapper.expanded .prs-review-text::-webkit-scrollbar {
        width: 4px;
    }
    
    .prs-review-content-wrapper.expanded .prs-review-text::-webkit-scrollbar-track {
        background: #f0f0f0;
        border-radius: 2px;
    }
    
    .prs-review-content-wrapper.expanded .prs-review-text::-webkit-scrollbar-thumb {
        background: #D1AF5D40;
        border-radius: 2px;
    }
    
    .prs-review-content-wrapper.expanded .prs-review-text::-webkit-scrollbar-thumb:hover {
        background: #D1AF5D60;
    }
    
    @media (max-width: 767px) {
        .prs-review-text {
            font-size: 0.875rem;
        }
        
        .prs-review-content-wrapper.expanded .prs-review-text {
            max-height: none;
        }
    }
    
    .prs-read-more-btn {
        background: none;
        border: none;
        color: #D1AF5D;
        font-weight: 600;
        font-size: 0.9375rem;
        cursor: pointer;
        padding: 0.5rem 0 0;
        margin-top: auto;
        text-align: left;
        transition: color 200ms ease;
    }

    .prs-read-more-btn:hover {
        color: #C5A24A;
        text-decoration: none;
    }

    @media (max-width: 767px) {
        .prs-read-more-btn {
            font-size: 0.875rem;
            padding-top: 0.375rem;
            margin-top: 0px;
        }
    }
    
    /* ===== REVIEW DATE ===== */
    .prs-review-date {
        font-size: 0.875rem;
        color: #999999;
    }
    
    @media (max-width: 767px) {
        .prs-review-date {
            font-size: 0.8125rem;
        }
    }
    
    /* ===== TITLE STYLES ===== */
    .prs-review-title {
        font-size: 1.125rem;
        font-weight: 700;
        color: #141310;
        margin: 0;
        line-height: 1.2;
    }
    
    @media (max-width: 767px) {
        .prs-review-title {
            font-size: 1rem;
        }
    }
  </style>

  <script>
  function prsToggleReadMore(reviewId) {
      try {
          const wrapper = document.getElementById(reviewId);
          if (!wrapper) {
              console.error('Review wrapper not found:', reviewId);
              return;
          }
          
          const full = wrapper.querySelector('.prs-text-full');
          const button = wrapper.querySelector('.prs-read-more-btn');
          
          if (!full || !button) {
              console.error('Required elements not found in review:', reviewId);
              return;
          }
          
          const isExpanded = wrapper.classList.contains('expanded');
          
          if (isExpanded) {
              // Collapse - CSS will handle showing the correct truncated version
              wrapper.classList.remove('expanded');
              full.style.display = 'none';
              button.textContent = 'Read more';
          } else {
              // Expand - CSS will handle hiding truncated versions
              wrapper.classList.add('expanded');
              full.style.display = 'inline';
              button.textContent = 'Read less';
          }
      } catch(e) {
          console.error('Read more toggle error:', e);
      }
  }
  </script>
  <?php
});