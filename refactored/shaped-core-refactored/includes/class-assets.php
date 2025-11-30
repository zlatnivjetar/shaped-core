<?php
/**
 * Shaped Assets Handler
 * 
 * Handles conditional loading of CSS and JS assets based on page context.
 * Only loads assets where they're actually needed for better performance.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Shaped_Assets {
    
    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend'], 20);
    }
    
    /**
     * Enqueue frontend assets conditionally
     */
    public function enqueue_frontend(): void {
        
        // ─── Always Load (lightweight utilities) ───
        if (file_exists(SHAPED_DIR . 'assets/js/calendar-fix.js')) {
            wp_enqueue_script(
                'shaped-calendar-fix',
                SHAPED_URL . 'assets/js/calendar-fix.js',
                ['jquery'],
                SHAPED_VERSION,
                true
            );
        }
        
        // ─── Checkout Page ───
        if ($this->is_checkout_page()) {
            $this->enqueue_checkout_assets();
        }
        
        // ─── Search Results Page ───
        if ($this->is_search_results_page()) {
            $this->enqueue_search_assets();
        }
        
        // ─── Home Page ───
        if (is_front_page() && file_exists(SHAPED_DIR . 'assets/js/home-room-cards.js')) {
            wp_enqueue_script(
                'shaped-home-cards',
                SHAPED_URL . 'assets/js/home-room-cards.js',
                ['jquery'],
                SHAPED_VERSION,
                true
            );
        }
        
        // ─── Language Switcher (only if WPML/Polylang active) ───
        if ($this->has_multilingual_plugin() && file_exists(SHAPED_DIR . 'assets/js/language-switch-fade.js')) {
            wp_enqueue_script(
                'shaped-language-fade',
                SHAPED_URL . 'assets/js/language-switch-fade.js',
                ['jquery'],
                SHAPED_VERSION,
                true
            );
        }

        // ─── Modals (always load for modal links) ───
        $this->enqueue_modal_assets();
    }
    
    /**
     * Enqueue checkout-specific assets
     */
    private function enqueue_checkout_assets(): void {
        // Checkout CSS
        if (file_exists(SHAPED_DIR . 'assets/css/checkout.css')) {
            wp_enqueue_style(
                'shaped-checkout',
                SHAPED_URL . 'assets/css/checkout.css',
                [],
                SHAPED_VERSION
            );
        }
        
        // Checkout JS (handles pricing display, form validation, etc.)
        if (file_exists(SHAPED_DIR . 'assets/js/checkout.js')) {
            wp_enqueue_script(
                'shaped-checkout',
                SHAPED_URL . 'assets/js/checkout.js',
                ['jquery'],
                SHAPED_VERSION,
                true
            );
        }
        
        // Leave page modal
        if (file_exists(SHAPED_DIR . 'assets/js/leave-page-modal-popup.js')) {
            wp_enqueue_script(
                'shaped-leave-modal',
                SHAPED_URL . 'assets/js/leave-page-modal-popup.js',
                ['jquery'],
                SHAPED_VERSION,
                true
            );
        }
    }
    
    /**
     * Enqueue modal assets
     */
    private function enqueue_modal_assets(): void {
        // Modal CSS
        if (file_exists(SHAPED_DIR . 'assets/css/modals.css')) {
            wp_enqueue_style(
                'shaped-modals',
                SHAPED_URL . 'assets/css/modals.css',
                [],
                SHAPED_VERSION
            );
        }

        // Modal JS
        if (file_exists(SHAPED_DIR . 'assets/js/modals.js')) {
            wp_enqueue_script(
                'shaped-modals',
                SHAPED_URL . 'assets/js/modals.js',
                ['jquery'],
                SHAPED_VERSION,
                true
            );
        }
    }

    /**
     * Enqueue search results page assets
     */
    private function enqueue_search_assets(): void {
        // Search results CSS
        if (file_exists(SHAPED_DIR . 'assets/css/search-results.css')) {
            wp_enqueue_style(
                'shaped-search-results',
                SHAPED_URL . 'assets/css/search-results.css',
                [],
                SHAPED_VERSION
            );
        }
        
        // Checkout JS also handles search results pricing logic
        if (file_exists(SHAPED_DIR . 'assets/js/checkout.js')) {
            wp_enqueue_script(
                'shaped-checkout',
                SHAPED_URL . 'assets/js/checkout.js',
                ['jquery'],
                SHAPED_VERSION,
                true
            );
        }
        
        // Provider badge stars (for ratings display)
        if (file_exists(SHAPED_DIR . 'assets/js/provider-badge-stars.js')) {
            wp_enqueue_script(
                'shaped-provider-stars',
                SHAPED_URL . 'assets/js/provider-badge-stars.js',
                ['jquery'],
                SHAPED_VERSION,
                true
            );
        }
    }
    
    /* =========================================================================
     * PAGE DETECTION HELPERS
     * ========================================================================= */
    
    /**
     * Check if current page is checkout
     */
    private function is_checkout_page(): bool {
        // Check page slug
        if (is_page(['checkout', 'book', 'booking'])) {
            return true;
        }
        
        // Check for MPHB checkout shortcode
        global $post;
        if ($post && has_shortcode($post->post_content, 'mphb_checkout')) {
            return true;
        }
        
        // URL pattern check
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($uri, '/checkout') !== false) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if current page is search results
     */
    private function is_search_results_page(): bool {
        // Check for MPHB search results shortcode
        global $post;
        if ($post && has_shortcode($post->post_content, 'mphb_search_results')) {
            return true;
        }
        
        // URL pattern check
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($uri, 'mphb_room_type') !== false || strpos($uri, '/search-results') !== false) {
            return true;
        }
        
        // Query string check (MPHB uses these parameters)
        if (isset($_GET['mphb_room_type_id']) || isset($_GET['mphb_check_in_date'])) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if a multilingual plugin is active
     */
    private function has_multilingual_plugin(): bool {
        return defined('ICL_SITEPRESS_VERSION') || defined('POLYLANG_VERSION');
    }
}
