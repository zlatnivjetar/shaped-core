<?php
/**
 * Review Frontend Assets
 * 
 * Styles and scripts for review display
 * 
 * @package Shaped_Core
 * @subpackage Reviews
 */

namespace Shaped\Modules\Reviews;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enqueue external CSS files
 */
add_action('wp_enqueue_scripts', function() {
    // Only load on pages with reviews
    if (!is_singular(CPT::POST_TYPE) && !has_shortcode_on_page()) {
        return;
    }

    // Enqueue external reviews CSS (Elementor-specific styling)
    $css_file = dirname(__FILE__) . '/assets/reviews.css';
    if (file_exists($css_file)) {
        wp_enqueue_style(
            'shaped-reviews',
            plugin_dir_url(__FILE__) . 'assets/reviews.css',
            [],
            filemtime($css_file)
        );
    }

    // Enqueue provider badge CSS
    $badge_css = dirname(__FILE__) . '/assets/provider-badges.css';
    if (file_exists($badge_css)) {
        wp_enqueue_style(
            'shaped-provider-badges',
            plugin_dir_url(__FILE__) . 'assets/provider-badges.css',
            [],
            filemtime($badge_css)
        );
    }
}, 10);

/**
 * Enqueue inline JavaScript only
 */
add_action('wp_footer', function() {
    // Only load on pages with reviews
    if (!is_singular(CPT::POST_TYPE) && !has_shortcode_on_page()) {
        return;
    }
    ?>
    <script id="shaped-reviews-js">
    function shapedToggleReadMore(reviewId) {
        try {
            const wrapper = document.getElementById(reviewId);
            if (!wrapper) return;

            const full = wrapper.querySelector('.shaped-text-full');
            const button = wrapper.querySelector('.shaped-read-more-btn');
            if (!full || !button) return;

            const isExpanded = wrapper.classList.contains('expanded');

            if (isExpanded) {
                wrapper.classList.remove('expanded');
                full.style.display = 'none';
                button.textContent = 'Read more';
            } else {
                wrapper.classList.add('expanded');
                full.style.display = 'inline';
                button.textContent = 'Read less';
            }
        } catch(e) {
            console.error('Read more toggle error:', e);
        }
    }

    // Legacy function name (backward compatibility)
    function prsToggleReadMore(reviewId) {
        shapedToggleReadMore(reviewId);
    }
    </script>
    <?php
}, 999);

/**
 * Check if current page has review shortcodes
 */
function has_shortcode_on_page(): bool {
    global $post;
    
    if (!$post) {
        return false;
    }

    $shortcodes = [
        'shaped_unified_rating',
        'shaped_review_author',
        'shaped_review_date',
        'shaped_provider_badge',
        'shaped_review_content',
        // Legacy
        'unified_rating',
        'review_author',
        'review_date',
        'provider_badge_v2',
        'review_content',
    ];

    foreach ($shortcodes as $shortcode) {
        if (has_shortcode($post->post_content, $shortcode)) {
            return true;
        }
    }

    // Check for Elementor loop with reviews
    if (strpos($post->post_content, 'featured_reviews_query') !== false) {
        return true;
    }

    // Check for review CPT archive
    if (is_post_type_archive(CPT::POST_TYPE)) {
        return true;
    }

    return false;
}
