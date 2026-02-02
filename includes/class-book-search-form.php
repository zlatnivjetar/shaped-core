<?php
/**
 * Book Page Search Form Modifications
 *
 * Modifies the MotoPress Hotel Booking search form on the /book page
 * to include inline benefits text: "Best rate direct • Instant confirmation • Secure payment"
 *
 * @package Shaped_Core
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Shaped_Book_Search_Form {

    /**
     * Initialize the class
     */
    public function __construct() {
        // Use 'wp' action to check page context (runs after query is set up)
        add_action('wp', [$this, 'maybe_init_book_page_hooks']);
    }

    /**
     * Conditionally add hooks only on /book page
     */
    public function maybe_init_book_page_hooks(): void {
        if (!$this->is_book_page()) {
            return;
        }

        // Open container wrapper before the form
        add_action('mphb_sc_search_before_form', [$this, 'render_container_open'], 10);

        // Add benefits line after the form and close container
        add_action('mphb_sc_search_after_form', [$this, 'render_benefits_and_close_container'], 10);

        // Add custom class to wrapper for Elementor targeting
        add_filter('mphb_sc_search_wrapper_class', [$this, 'add_book_page_wrapper_class']);
    }

    /**
     * Check if current page is the /book page
     *
     * @return bool
     */
    private function is_book_page(): bool {
        // Method 1: Check by page slug
        if (is_page('book')) {
            return true;
        }

        // Method 2: Check by URL path (fallback)
        global $wp;
        if (isset($wp->request) && $wp->request === 'book') {
            return true;
        }

        return false;
    }

    /**
     * Open the container wrapper before the form
     */
    public function render_container_open(): void {
        ?>
        <div class="mphb-book-search-container">
        <?php
    }

    /**
     * Render the inline benefits line after the form and close container
     */
    public function render_benefits_and_close_container(): void {
        ?>
        <div class="mphb-search-benefits-inline">
            <span class="benefit-item">Best rate direct</span>
            <span class="benefit-separator">&bull;</span>
            <span class="benefit-item">Instant confirmation</span>
            <span class="benefit-separator nomobile">&bull;</span>
            <span class="benefit-item">Secure payment</span>
        </div>
        </div><!-- .mphb-book-search-container -->
        <?php
    }

    /**
     * Add custom class to search form wrapper for Elementor targeting
     *
     * @param string $class Existing wrapper class
     * @return string Modified wrapper class
     */
    public function add_book_page_wrapper_class(string $class): string {
        return $class . ' mphb-search-book-page';
    }
}
