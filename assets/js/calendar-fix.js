// ====== REMOVE CALENDAR AVAILABLE TOOLTIP (UTILITY) ======
jQuery(document).ready(function($) {
    setInterval(function() {
        $('.mphb-date-cell[title]').removeAttr('title');
    }, 100);
});

