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
 * Enqueue inline styles and scripts
 */
add_action('wp_head', function() {
    // Only load on pages with reviews
    if (!is_singular(CPT::POST_TYPE) && !has_shortcode_on_page()) {
        return;
    }
    ?>
    <style id="shaped-reviews-css">
    /* ===== REVIEW CARD CONTAINER ===== */
    .elementor-loop-container .elementor-loop-item {
        height: 100%;
    }

    /* ===== RATING STYLES ===== */
    .shaped-rating-unified {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .shaped-rating-stars {
        display: flex;
        gap: 1px;
        line-height: 1;
    }

    .shaped-star {
        font-size: 1rem;
        display: inline-block;
    }

    @media (max-width: 767px) {
        .shaped-star {
            font-size: 0.875rem;
        }
    }

    .shaped-star-full {
        color: var(--shaped-accent, #D1AF5D) !important;
    }

    .shaped-star-half {
        position: relative;
        color: #e0e0e0 !important;
    }

    .shaped-star-half::before {
        content: "★";
        position: absolute;
        left: 0;
        top: 0;
        width: 50%;
        overflow: hidden;
        color: var(--shaped-accent, #D1AF5D) !important;
    }

    .shaped-star-empty {
        color: #e0e0e0 !important;
    }

    .shaped-rating-numeric {
        font-size: 0.875rem;
        font-weight: 700;
        color: var(--shaped-accent, #D1AF5D);
    }

    @media (max-width: 767px) {
        .shaped-rating-numeric {
            font-size: 0.8125rem;
        }
    }

    /* ===== PROVIDER BADGE ===== */
    .shaped-provider-badge {
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

    .shaped-provider-badge:hover {
        opacity: 0.9;
    }

    @media (max-width: 767px) {
        .shaped-provider-badge {
            font-size: 11px;
            letter-spacing: 0;
            padding: 3px 8px;
        }
    }

    /* ===== REVIEW CARD ===== */
    .shaped-review-card-root {
        background: var(--shaped-surface, #ffffff);
        border-radius: 8px;
        padding: 24px 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.08) !important;
        transition: transform 200ms ease, box-shadow 200ms ease;
        min-height: 17.375rem;
        display: flex;
        flex-direction: column;
    }

    .shaped-review-card-root:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.12) !important;
    }

    @media (max-width: 767px) {
        .shaped-review-card-root {
            min-height: 12.375rem;
            padding: 16px;
        }
    }

    /* ===== REVIEW CONTENT ===== */
    .shaped-review-content-wrapper {
        flex: 1;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .shaped-review-text {
        color: var(--shaped-text-secondary, #666666);
        line-height: 1.5;
        font-size: 0.9375rem;
        flex: 1;
        overflow: hidden;
        transition: all 300ms ease;
    }

    /* Responsive truncation */
    .shaped-mobile-truncated {
        display: none !important;
    }

    .shaped-desktop-truncated {
        display: inline !important;
    }

    .shaped-review-content-wrapper.expanded .shaped-text-truncated {
        display: none !important;
    }

    .shaped-review-content-wrapper.expanded .shaped-text-full {
        display: inline !important;
    }

    @media (max-width: 479px) {
        .shaped-mobile-truncated {
            display: inline !important;
        }

        .shaped-desktop-truncated {
            display: none !important;
        }

        .shaped-review-content-wrapper.expanded .shaped-mobile-truncated {
            display: none !important;
        }
    }

    /* Expanded state with scroll */
    .shaped-review-content-wrapper.expanded .shaped-review-text {
        max-height: 7.5em;
        overflow-y: auto;
        padding-right: 8px;
        margin-right: -8px;
        scrollbar-width: thin;
        scrollbar-color: rgba(209, 175, 93, 0.125) #f0f0f0;
    }

    .shaped-review-content-wrapper.expanded .shaped-review-text::-webkit-scrollbar {
        width: 4px;
    }

    .shaped-review-content-wrapper.expanded .shaped-review-text::-webkit-scrollbar-track {
        background: #f0f0f0;
        border-radius: 2px;
    }

    .shaped-review-content-wrapper.expanded .shaped-review-text::-webkit-scrollbar-thumb {
        background: rgba(209, 175, 93, 0.25);
        border-radius: 2px;
    }

    .shaped-review-content-wrapper.expanded .shaped-review-text::-webkit-scrollbar-thumb:hover {
        background: rgba(209, 175, 93, 0.375);
    }

    @media (max-width: 767px) {
        .shaped-review-text {
            font-size: 0.875rem;
        }

        .shaped-review-content-wrapper.expanded .shaped-review-text {
            max-height: none;
        }
    }

    /* ===== READ MORE BUTTON ===== */
    .shaped-read-more-btn {
        background: none;
        border: none;
        color: var(--shaped-accent, #D1AF5D);
        font-weight: 600;
        font-size: 0.9375rem;
        cursor: pointer;
        padding: 0.5rem 0 0;
        margin-top: auto;
        text-align: left;
        transition: color 200ms ease;
    }

    .shaped-read-more-btn:hover {
        color: var(--shaped-accent-hover, #C5A24A);
        text-decoration: none;
    }

    @media (max-width: 767px) {
        .shaped-read-more-btn {
            font-size: 0.875rem;
            padding-top: 0.375rem;
            margin-top: 0;
        }
    }

    /* ===== REVIEW DATE ===== */
    .shaped-review-date {
        font-size: 0.875rem;
        color: #999999;
    }

    @media (max-width: 767px) {
        .shaped-review-date {
            font-size: 0.8125rem;
        }
    }

    /* ===== TITLE ===== */
    .shaped-review-title {
        font-size: 1.125rem;
        font-weight: 700;
        color: var(--shaped-text, #141310);
        margin: 0;
        line-height: 1.2;
    }

    @media (max-width: 767px) {
        .shaped-review-title {
            font-size: 1rem;
        }
    }

    /* ===== LEGACY PRS CLASSES (backward compatibility) ===== */
    .prs-rating-unified { display: flex; align-items: center; gap: 0.5rem; }
    .prs-rating-stars { display: flex; gap: 1px; line-height: 1; }
    .prs-star { font-size: 1rem; }
    .prs-star-full { color: var(--shaped-accent, #D1AF5D) !important; }
    .prs-star-half { position: relative; color: #e0e0e0 !important; }
    .prs-star-half::before { content: "★"; position: absolute; left: 0; top: 0; width: 50%; overflow: hidden; color: var(--shaped-accent, #D1AF5D) !important; }
    .prs-star-empty { color: #e0e0e0 !important; }
    .prs-rating-numeric { font-size: 0.875rem; font-weight: 700; color: var(--shaped-accent, #D1AF5D); }
    .prs-provider-badge { display: inline-block; padding: 4px 12px; border-radius: 8px; font-size: 12px; font-weight: 600; text-decoration: none; transition: opacity 200ms ease; }
    .prs-provider-badge:hover { opacity: 0.9; }
    .prs-review-card-root { background: #fff; border-radius: 8px; padding: 24px 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.08) !important; }
    .prs-review-content-wrapper { flex: 1; display: flex; flex-direction: column; }
    .prs-review-text { color: #666; line-height: 1.5; }
    .prs-read-more-btn { background: none; border: none; color: var(--shaped-accent, #D1AF5D); font-weight: 600; cursor: pointer; }
    .prs-review-date { font-size: 0.875rem; color: #999; }
    </style>

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
});

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
