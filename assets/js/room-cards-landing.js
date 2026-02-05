/**
 * Open Check-In Datepicker
 *
 * Opens the MotoPress Hotel Booking check-in datepicker when any element
 * with `data-open-datepick` is clicked. When multiple search forms exist
 * on the page (e.g. hero + fixed bar), targets the last visible one.
 *
 * Usage: <button data-open-datepick>Select dates</button>
 *
 * Requires: jQuery, MPHB datepick already initialized on the page.
 */
(function ($) {
    if (!$) return;

    $(document).on('click', '[data-open-datepick]', function (e) {
        e.preventDefault();

        if (!$.fn.datepick) return;

        var $all = $('.mphb_sc_search-form .mphb-datepick[id^="mphb_check_in_date"]');
        var $visible = $all.filter(':visible');
        var $target = $visible.length ? $visible.last() : $all.last();

        if ($target.length) {
            $target.datepick('show');
        }
    });
})(window.jQuery);
