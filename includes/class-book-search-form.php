<?php
/**
 * Book Page Search Form Enhancements & Checkout Pre-fill
 *
 * On /book and /search-results pages, wraps the MPHB search form in a
 * container with inline benefits text ("Best rate direct …").
 *
 * Also hooks into MPHB's checkout to pre-fill the adults dropdown with
 * the guest count carried over from the search form.
 *
 * The guests field itself is rendered natively by MPHB's search-form.php
 * template (modified to always show adults as a visible select labelled
 * "Guests" inside .search-input-wrapper).
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
        add_action('wp', [$this, 'init_hooks']);

        // Pre-fill adults on checkout from search context (runs everywhere)
        add_filter('mphb_sc_checkout_preset_adults', [$this, 'preset_checkout_adults'], 10, 4);
    }

    /**
     * Register hooks for book/search-results page chrome.
     */
    public function init_hooks(): void {
        if (!$this->is_book_page()) {
            return;
        }

        // Wrap form in container + add benefits text
        add_action('mphb_sc_search_before_form', [$this, 'render_container_open'], 10);
        add_action('mphb_sc_search_after_form', [$this, 'render_benefits_and_close_container'], 10);
        add_filter('mphb_sc_search_wrapper_class', [$this, 'add_book_page_wrapper_class']);
    }

    /**
     * Check if current page is the /book or /search-results page
     *
     * @return bool
     */
    private function is_book_page(): bool {
        // Method 1: Check by page slug
        if (is_page('book') || is_page('search-results')) {
            return true;
        }

        // Method 2: Check by URL path (fallback)
        global $wp;
        if (isset($wp->request) && ($wp->request === 'book' || $wp->request === 'search-results')) {
            return true;
        }

        return false;
    }

    /**
     * Open the container wrapper before the form and start output buffering.
     */
    public function render_container_open(): void {
        echo '<div class="mphb-book-search-container">';
        ob_start();
    }

    /**
     * Output the buffered form, add benefits line, and close the container.
     */
    public function render_benefits_and_close_container(): void {
        $form_html = ob_get_clean();

        echo $form_html;
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

    /**
     * Pre-fill the adults dropdown on MPHB checkout with the guest count
     * carried over from the search form.
     *
     * Priority: POST data (from room card form) > GET param.
     * Clamped to the room type's adult capacity so the value is always valid.
     *
     * @param string|int                  $preset      Current preset value
     * @param \MPHB\Entities\RoomType     $roomType    The room type being booked
     * @param \MPHB\Entities\ReservedRoom $reservedRoom
     * @param \MPHB\Entities\Booking      $booking
     * @return string|int
     */
    public function preset_checkout_adults($preset, $roomType, $reservedRoom = null, $booking = null) {
        $guests = 0;

        if (isset($_POST['mphb_adults'])) {
            $guests = absint($_POST['mphb_adults']);
        } elseif (isset($_GET['mphb_adults'])) {
            $guests = absint($_GET['mphb_adults']);
        }

        if ($guests > 0 && $roomType) {
            $max_adults = $roomType->getAdultsCapacity();
            return min($guests, $max_adults);
        }

        return $preset;
    }
}
