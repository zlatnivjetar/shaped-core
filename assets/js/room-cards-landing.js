/**
 * Open Check-In Datepicker
 *
 * Opens the MotoPress Hotel Booking check-in datepicker when any element
 * with `data-open-datepick` is clicked.
 *
 * Usage: <button data-open-datepick>Select dates</button>
 *
 * Requires: jQuery, MPHB datepick already initialized on the page.
 */
(function ($) {
    if (!$) return;

    $(document).on('click', '[data-open-datepick]', function (e) {
        e.preventDefault();

        var $checkin = $('.mphb_sc_search-form .mphb-datepick[id^="mphb_check_in_date"]');

        if ($checkin.length && $.fn.datepick) {
            $checkin.datepick('show');
        }
    });
})(window.jQuery);
