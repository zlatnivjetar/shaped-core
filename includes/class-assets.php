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
        add_action('wp_head', [$this, 'inline_critical_search_css'], 1);
    }
    
    /**
     * Enqueue frontend assets conditionally
     */
    public function enqueue_frontend(): void {

        // ─── Local Fonts (generated from brand.json) ───
        // Self-hosted fonts for performance and GDPR compliance
        // Single source of truth: config/brand.json
        wp_register_style('shaped-fonts', false);
        wp_enqueue_style('shaped-fonts');

        // Generate font CSS dynamically from brand.json
        if (class_exists('Shaped_Font_Loader')) {
            $font_css = Shaped_Font_Loader::generate_font_css();
            wp_add_inline_style('shaped-fonts', $font_css);
        }

        // ─── Design Tokens (must load after fonts) ───
        // CSS custom properties used by all other stylesheets
        if (file_exists(SHAPED_DIR . 'assets/css/design-tokens.css')) {
            wp_enqueue_style(
                'shaped-design-tokens',
                SHAPED_URL . 'assets/css/design-tokens.css',
                ['shaped-fonts'],
                SHAPED_VERSION
            );

            // Inject client-specific CSS variables from shaped-client-config.php
            // This works even when mu-plugins is outside the repo
            if (class_exists('Shaped_Design_Tokens_Generator')) {
                $tokens_css = Shaped_Design_Tokens_Generator::generate_tokens_css();
                wp_add_inline_style('shaped-design-tokens', $tokens_css);
            }
        }

        // ─── Global Button Styles (must load after design tokens) ───
        // Provides consistent button styling across the entire plugin
        // Works independently of Elementor
        if (file_exists(SHAPED_DIR . 'assets/css/buttons.css')) {
            wp_enqueue_style(
                'shaped-buttons',
                SHAPED_URL . 'assets/css/buttons.css',
                ['shaped-design-tokens'],
                SHAPED_VERSION
            );
        }

        // ─── Mobile Menu Styles (must load after design tokens) ───
        // Provides mobile menu styling for Elementor nav-menu widget
        if (file_exists(SHAPED_DIR . 'assets/css/mobile-menu.css')) {
            wp_enqueue_style(
                'shaped-mobile-menu',
                SHAPED_URL . 'assets/css/mobile-menu.css',
                ['shaped-design-tokens'],
                SHAPED_VERSION
            );
        }

        // ─── Always Load (lightweight utilities) ───

        // Phosphor Icons for amenity display (self-hosted for performance)
        if (file_exists(SHAPED_DIR . 'assets/css/phosphor-icons.css')) {
            wp_enqueue_style(
                'phosphor-icons',
                SHAPED_URL . 'assets/css/phosphor-icons.css',
                [],
                SHAPED_VERSION
            );
        }

        // Cookie Banner & Language Switcher styles
        if (file_exists(SHAPED_DIR . 'assets/css/cookie-banner.css')) {
            wp_enqueue_style(
                'shaped-cookie-banner',
                SHAPED_URL . 'assets/css/cookie-banner.css',
                ['shaped-design-tokens'],
                SHAPED_VERSION
            );
        }

        if (file_exists(SHAPED_DIR . 'assets/js/calendar-fix.js')) {
            wp_enqueue_script(
                'shaped-calendar-fix',
                SHAPED_URL . 'assets/js/calendar-fix.js',
                ['jquery'],
                SHAPED_VERSION,
                true
            );
        }

        if (file_exists(SHAPED_DIR . 'assets/js/language-switch-fade.js')) {
            wp_enqueue_script(
                'shaped-language-switch-fade',
                SHAPED_URL . 'assets/js/language-switch-fade.js',
                [],
                SHAPED_VERSION,
                true
            );
        }

        // Leave page confirmation modal (for external links)
        if (file_exists(SHAPED_DIR . 'assets/js/leave-page-modal-popup.js')) {
            wp_enqueue_script(
                'shaped-leave-modal',
                SHAPED_URL . 'assets/js/leave-page-modal-popup.js',
                [],
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
        
        // ─── Search Form & Calendar (on pages with search form) ───
        if ($this->has_search_form()) {
            if (file_exists(SHAPED_DIR . 'assets/css/search-form.css')) {
                wp_enqueue_style(
                    'shaped-search-form',
                    SHAPED_URL . 'assets/css/search-form.css',
                    ['shaped-design-tokens'],
                    SHAPED_VERSION
                );
            }

            if (file_exists(SHAPED_DIR . 'assets/css/search-calendar.css')) {
                wp_enqueue_style(
                    'shaped-search-calendar',
                    SHAPED_URL . 'assets/css/search-calendar.css',
                    ['shaped-design-tokens'],
                    SHAPED_VERSION
                );
            }

            // data-open-datepick handler (opens check-in picker from any element)
            if (file_exists(SHAPED_DIR . 'assets/js/room-cards-landing.js')) {
                wp_enqueue_script(
                    'shaped-open-datepick',
                    SHAPED_URL . 'assets/js/room-cards-landing.js',
                    ['jquery'],
                    SHAPED_VERSION,
                    true
                );
            }
        }

        // ─── Hero ↔ fixed search bar visibility toggle ───
        // Loaded on any page with a search form; the script self-guards
        // by checking for #search-hero and #search-fixed elements.
        if ($this->has_search_form() && file_exists(SHAPED_DIR . 'assets/js/search-form-visibility.js')) {
            wp_enqueue_script(
                'shaped-search-form-visibility',
                SHAPED_URL . 'assets/js/search-form-visibility.js',
                [],
                SHAPED_VERSION,
                true
            );
        }

        // ─── Book Page Search Form (benefits line) ───
        if ($this->is_book_page() && file_exists(SHAPED_DIR . 'assets/css/book-search-form.css')) {
            wp_enqueue_style(
                'shaped-book-search-form',
                SHAPED_URL . 'assets/css/book-search-form.css',
                ['shaped-design-tokens', 'shaped-search-form'],
                SHAPED_VERSION
            );
        }

        // ─── Modals (always load for modal links) ───
        $this->enqueue_modal_assets();

        // ─── Elementor-specific CSS (guest reviews, gallery) ───
        if (file_exists(SHAPED_DIR . 'assets/css/guest-reviews.css')) {
            wp_enqueue_style(
                'shaped-guest-reviews',
                SHAPED_URL . 'assets/css/guest-reviews.css',
                ['shaped-design-tokens'],
                SHAPED_VERSION
            );
        }

        if (file_exists(SHAPED_DIR . 'assets/css/gallery-element.css')) {
            wp_enqueue_style(
                'shaped-gallery-element',
                SHAPED_URL . 'assets/css/gallery-element.css',
                ['shaped-design-tokens'],
                SHAPED_VERSION
            );
        }
    }

    /**
     * Inline critical search-form CSS in <head> to prevent FOUC.
     *
     * Outputs design tokens (both the static fallbacks and dynamic
     * client-specific values) followed by the minimum layout rules so the
     * form is correctly laid out on first paint. The full external
     * stylesheets still load for transitions, focus states, responsive
     * overrides, etc.
     */
    public function inline_critical_search_css(): void {
        if (!$this->has_search_form()) {
            return;
        }

        // ── Only the token defaults referenced by the critical rules below ──
        $tokens = ':root{--color-surface-page: ;--color-surface-white:#FFFFFF;--color-border-default:#e4e4e4;--shadow-search-form:0 6px 24px rgba(0,0,0,0.1);--shadow-sm:0 1px 2px rgba(0,0,0,0.05);--radius-md:8px;--radius-lg:12px}';

        // Client-specific overrides (fills --color-surface-page, --font-body, etc.)
        if (class_exists('Shaped_Design_Tokens_Generator')) {
            $tokens .= Shaped_Design_Tokens_Generator::generate_tokens_css();
        }
        ?>
        <style id="shaped-search-critical">
            <?php echo $tokens; ?>
            .mphb-required-fields-tip{display:none!important}
            .mphb_sc_search-check-in-date br,.mphb_sc_search-check-out-date br,.mphb_sc_search-guests br{display:none}
            .mphb_sc_search-check-in-date abbr,.mphb_sc_search-check-out-date abbr{display:none!important}
            .mphb_sc_search-form{display:flex;flex-direction:column;align-items:center;background:var(--color-surface-page);border:1px solid var(--color-border-default);padding:24px;box-shadow:var(--shadow-search-form);border-radius:var(--radius-lg)}
            .search-form-wrapper{display:flex;width:100%;gap:24px;align-items:flex-end}
            .search-input-wrapper{display:flex;width:100%;gap:24px;align-items:flex-end}
            .mphb_sc_search-check-in-date,.mphb_sc_search-check-out-date{flex:1;min-width:160px;margin:0}
            .mphb_sc_search-check-in-date label,.mphb_sc_search-check-out-date label,.mphb_sc_search-guests label{display:block;font-size:14px;font-weight:600;text-transform:uppercase;margin-bottom:8px}
            .mphb-datepick{width:100%;height:48px;padding:10px 14px;border:1px solid var(--color-border-default);border-radius:var(--radius-md);font-size:16px;background:var(--color-surface-white);box-shadow:var(--shadow-sm);box-sizing:border-box}
            .mphb_sc_search-submit-button-wrapper{margin:0;flex-shrink:0}
            .mphb_sc_search-submit-button-wrapper input{height:48px;padding:14px 32px!important}
            .mphb_sc_search-guests{flex:1;max-width:64px;margin:0}
            .mphb_sc_search-guests select{width:100%;height:48px;padding:10px 14px;border:1px solid var(--color-border-default);border-radius:var(--radius-md);background:var(--color-surface-white);box-sizing:border-box;appearance:none}
            .mphb-book-search-container{display:flex;flex-direction:column;align-items:center;padding:24px;border-radius:var(--radius-lg);width:100%}
            .mphb-book-search-container .mphb_sc_search-form{background:transparent;border:none;padding:0;box-shadow:none;border-radius:0;width:800px;max-width:100%}
            #search-fixed{opacity:0;pointer-events:none;transform:translateY(100%)}
            #search-hero .mphb-search-benefits-inline{display:none}
        </style>
        <?php
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
                ['shaped-design-tokens'],
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
                ['shaped-design-tokens'],
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
                ['shaped-design-tokens'],
                SHAPED_VERSION
            );
        }

        // Search form CSS
        if (file_exists(SHAPED_DIR . 'assets/css/search-form.css')) {
            wp_enqueue_style(
                'shaped-search-form',
                SHAPED_URL . 'assets/css/search-form.css',
                ['shaped-design-tokens'],
                SHAPED_VERSION
            );
        }

        // Search calendar CSS
        if (file_exists(SHAPED_DIR . 'assets/css/search-calendar.css')) {
            wp_enqueue_style(
                'shaped-search-calendar',
                SHAPED_URL . 'assets/css/search-calendar.css',
                ['shaped-design-tokens'],
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

        // Room detail modal (opens from search result cards)
        if (file_exists(SHAPED_DIR . 'assets/css/room-modal.css')) {
            wp_enqueue_style(
                'shaped-room-modal',
                SHAPED_URL . 'assets/css/room-modal.css',
                ['shaped-design-tokens'],
                SHAPED_VERSION
            );
        }

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
    
    /* =========================================================================
     * PAGE DETECTION HELPERS
     * ========================================================================= */
    
    /**
     * Check if current page is the /book or /search-results page specifically
     */
    private function is_book_page(): bool {
        // Check by page slug
        if (is_page('book') || is_page('search-results')) {
            return true;
        }

        // Check by URL path (fallback)
        global $wp;
        if (isset($wp->request) && ($wp->request === 'book' || $wp->request === 'search-results')) {
            return true;
        }

        return false;
    }

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
     * Check if current page has search form
     */
    private function has_search_form(): bool {
        global $post;

        // Check for MotoPress search shortcodes
        if ($post && (has_shortcode($post->post_content, 'mphb_search') ||
                      has_shortcode($post->post_content, 'mphb_availability_search'))) {
            return true;
        }

        // Home page usually has search form
        if (is_front_page()) {
            return true;
        }

        // Room pages (MotoPress room type single pages under /accommodation/)
        if (is_singular('mphb_room_type')) {
            return true;
        }

        // Also check URL pattern for room pages
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($uri, '/accommodation/') !== false) {
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
