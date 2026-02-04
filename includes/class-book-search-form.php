<?php
/**
 * Book Page Search Form Modifications
 *
 * Modifies the MotoPress Hotel Booking search form on the /book page
 * to include inline benefits text: "Best rate direct • Instant confirmation • Secure payment"
 * and a visible Guests dropdown field.
 *
 * The guests field is injected directly into .search-input-wrapper via
 * output buffering so the layout is correct on first paint (no FOUC).
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

        // Open container wrapper before the form and start output buffering
        add_action('mphb_sc_search_before_form', [$this, 'render_container_open'], 10);

        // Add guests field before submit button (captured by output buffer)
        add_action('mphb_sc_search_form_before_submit_btn', [$this, 'render_guests_field'], 10);

        // Capture buffer, inject guests into correct DOM position, add benefits, close container
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
     * Open the container wrapper before the form and start output buffering.
     *
     * Everything between this hook and mphb_sc_search_after_form is captured
     * so we can relocate the guests field into .search-input-wrapper server-side.
     */
    public function render_container_open(): void {
        echo '<div class="mphb-book-search-container">';
        ob_start();
    }

    /**
     * Render the guests select field before the submit button.
     *
     * This HTML is captured by the output buffer and later moved into
     * .search-input-wrapper by render_benefits_and_close_container().
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
            var containers = document.querySelectorAll('.mphb-book-search-container');
            
            containers.forEach(function(container) {
                var guestsField = container.querySelector('.mphb_sc_search-guests');
                var inputWrapper = container.querySelector('.search-input-wrapper');
                if (guestsField && inputWrapper) {
                    inputWrapper.appendChild(guestsField);
                }

                var submitBtn = container.querySelector('.mphb_sc_search-submit-button-wrapper input[type="submit"]');
                if (submitBtn) {
                    submitBtn.value = 'Check availability';
                }
            });
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
     * Capture buffered form HTML, move guests field into .search-input-wrapper,
     * then output the corrected form, benefits line, and close the container.
     *
     * .search-input-wrapper contains only <p> elements (no nested divs), so the
     * first </div> after its opening tag is its closing tag. We insert the guests
     * <p> right before that closing </div>.
     */
    public function render_benefits_and_close_container(): void {
        $form_html = ob_get_clean();

        // Extract the guests field HTML from the buffer
        if (preg_match('/<p class="mphb_sc_search-guests">.*?<\/p>/s', $form_html, $matches)) {
            $guests_html = $matches[0];

            // Remove guests field from its original position
            $form_html = str_replace($guests_html, '', $form_html);

            // Find .search-input-wrapper and inject guests before its closing </div>.
            // The wrapper only contains <p> children (no nested divs), so the first
            // </div> after the opening tag is the correct closing tag.
            $marker = 'class="search-input-wrapper"';
            $pos = strpos($form_html, $marker);
            if ($pos !== false) {
                $close_div = strpos($form_html, '</div>', $pos);
                if ($close_div !== false) {
                    $form_html = substr_replace($form_html, $guests_html, $close_div, 0);
                }
            }
        }

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
}
