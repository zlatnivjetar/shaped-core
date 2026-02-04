<?php
/**
 * Book Page Search Form Modifications
 *
 * Modifies the MotoPress Hotel Booking search form on the /book page
 * to include inline benefits text: "Best rate direct • Instant confirmation • Secure payment"
 * and a visible Guests dropdown field.
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

        // Inject critical CSS in head to prevent FOUC
        add_action('wp_head', [$this, 'render_critical_css'], 1);

        // Open container wrapper before the form
        add_action('mphb_sc_search_before_form', [$this, 'render_container_open'], 10);

        // Add guests field before submit button (will be moved via JS)
        add_action('mphb_sc_search_form_before_submit_btn', [$this, 'render_guests_field'], 10);

        // Add benefits line after the form and close container
        add_action('mphb_sc_search_after_form', [$this, 'render_benefits_and_close_container'], 10);

        // Add custom class to wrapper for Elementor targeting
        add_filter('mphb_sc_search_wrapper_class', [$this, 'add_book_page_wrapper_class']);

        // Add inline script to move guests field into search-input-wrapper
        add_action('wp_footer', [$this, 'render_guests_field_script'], 20);
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
     * Inject critical inline CSS to hide the form until stylesheets load.
     * Prevents FOUC (Flash of Unstyled Content).
     */
    public function render_critical_css(): void {
        ?>
        <style>.mphb-search-book-page{opacity:0}</style>
        <?php
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
     * Render the guests select field before the submit button
     */
    public function render_guests_field(): void {
        $uniqid = 'book-page-' . wp_unique_id();
        $adults_list = $this->get_adults_list();
        $current_adults = isset($_GET['mphb_adults']) ? absint($_GET['mphb_adults']) : $this->get_default_adults();
        ?>
        <p class="mphb_sc_search-guests">
            <label for="<?php echo esc_attr('mphb_adults-' . $uniqid); ?>">
                <?php esc_html_e('Guests', 'motopress-hotel-booking'); ?>
            </label>
            <br />
            <select id="<?php echo esc_attr('mphb_adults-' . $uniqid); ?>" name="mphb_adults">
                <?php foreach ($adults_list as $value) : ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($current_adults, $value); ?>>
                        <?php echo esc_html($value); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <?php
    }

    /**
     * Render inline script to move guests field into search-input-wrapper
     */
    public function render_guests_field_script(): void {
        ?>
        <script>
        (function() {
            var container = document.querySelector('.mphb-book-search-container');
            if (!container) return;

            var guestsField = container.querySelector('.mphb_sc_search-guests');
            var inputWrapper = container.querySelector('.search-input-wrapper');
            if (guestsField && inputWrapper) {
                inputWrapper.appendChild(guestsField);
            }

            var submitBtn = container.querySelector('.mphb_sc_search-submit-button-wrapper input[type="submit"]');
            if (submitBtn) {
                submitBtn.value = 'Check availability';
            }
        })();
        </script>
        <?php
    }

    /**
     * Get the list of adults values for the dropdown
     *
     * @return array
     */
    private function get_adults_list(): array {
        if (!class_exists('MPHB')) {
            return range(1, 10);
        }

        $min_adults = MPHB()->settings()->main()->getMinAdults();
        $max_adults = MPHB()->settings()->main()->getSearchMaxAdults();

        return range($min_adults, $max_adults);
    }

    /**
     * Get the default number of adults
     *
     * @return int
     */
    private function get_default_adults(): int {
        if (!class_exists('MPHB')) {
            return 2;
        }

        return MPHB()->settings()->main()->getMinAdults();
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