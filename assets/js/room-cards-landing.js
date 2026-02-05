/**
 * Landing Room Cards - Select Dates Button
 *
 * Opens the MotoPress Hotel Booking check-in datepicker
 * when a "Select dates" button is clicked on a landing card.
 *
 * Requires: jQuery, MPHB datepick already initialized on the page.
 */
(function ($) {
    if (!$) return;

    $(document).on('click', '.js-shaped-open-checkin', function (e) {
        e.preventDefault();

        var $checkin = $('.mphb_sc_search-form .mphb-datepick[id^="mphb_check_in_date"]');

        if ($checkin.length && $.fn.datepick) {
            $checkin.datepick('show');
        }
    });
})(window.jQuery);
