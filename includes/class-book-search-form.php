<?php
/**
 * Search Form Guests Field & Book Page Enhancements
 *
 * Adds a visible Guests dropdown to every MPHB search form rendered via
 * [mphb_availability_search]. On /book and /search-results pages it also
 * wraps the form in a container with inline benefits text.
 *
 * On book pages the guests field is repositioned server-side via output
 * buffering (no FOUC). On all other pages a tiny inline script moves the
 * field into .search-input-wrapper after first paint.
 *
 * Also hooks into MPHB's checkout to pre-fill the adults dropdown with
 * the guest count carried over from the search form.
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
     * Register hooks based on page context.
     *
     * Guests field: added on every page (global).
     * Container wrapper + benefits text: only on /book and /search-results.
     */
    public function init_hooks(): void {
        $is_book_page = $this->is_book_page();

        if ($is_book_page) {
            // Book pages: use output buffering to inject guests field server-side
            add_action('mphb_sc_search_before_form', [$this, 'render_container_open'], 10);
            add_action('mphb_sc_search_form_before_submit_btn', [$this, 'render_guests_field'], 10);
            add_action('mphb_sc_search_after_form', [$this, 'render_benefits_and_close_container'], 10);
            add_filter('mphb_sc_search_wrapper_class', [$this, 'add_book_page_wrapper_class']);
        } else {
            // All other pages: render guests field inline + reposition via JS
            add_action('mphb_sc_search_form_before_submit_btn', [$this, 'render_guests_field'], 10);
            add_action('mphb_sc_search_after_form', [$this, 'render_guests_field_reposition_script'], 10);
        }
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
     * On book pages this HTML is captured by the output buffer and later moved
     * into .search-input-wrapper by render_benefits_and_close_container().
     * On other pages it renders inline and is repositioned by JS.
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
     * Inline script to reposition guests field into .search-input-wrapper.
     *
     * Used on non-book pages where the output-buffer approach is not active.
     * Targets forms that have .mphb_sc_search-guests outside of .search-input-wrapper.
     */
    public function render_guests_field_reposition_script(): void {
        ?>
        <script>
        (function() {
            document.querySelectorAll('.mphb_sc_search-form').forEach(function(form) {
                var guestsField = form.querySelector('.mphb_sc_search-guests');
                var inputWrapper = form.querySelector('.search-input-wrapper');
                if (guestsField && inputWrapper && !inputWrapper.contains(guestsField)) {
                    inputWrapper.appendChild(guestsField);
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
