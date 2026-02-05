/**
 * Open Check-In Datepicker
 *
 * Opens the MotoPress Hotel Booking check-in datepicker when any element
 * with `data-open-datepick` is clicked. When both a hero and fixed search
 * bar exist, targets the fixed bar if it's active (is-visible class set by
 * IntersectionObserver in search-form-visibility.js), otherwise falls back
 * to the first available check-in input.
 *
 * Usage: <button data-open-datepick>Select dates</button>
 *
 * Requires: jQuery, MPHB datepick already initialized on the page.
 */
(function ($) {
    if (!$) return;

    var INPUT = '.mphb-datepick[id^="mphb_check_in_date"]';

    $(document).on('click', '[data-open-datepick]', function (e) {
        e.preventDefault();

        if (!$.fn.datepick) return;

        // Prefer the fixed bar when it's active (class toggled by IntersectionObserver)
        var $fixed = $('#search-fixed.is-visible ' + INPUT);
        if ($fixed.length) {
            $fixed.datepick('show');
            return;
        }

        // Fall back to first available input (hero, or single-form pages)
        var $fallback = $('.mphb_sc_search-form ' + INPUT).first();
        if ($fallback.length) {
            $fallback.datepick('show');
        }
    });
})(window.jQuery);
