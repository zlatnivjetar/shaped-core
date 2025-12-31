/**
 * MPHB Datepicker Chain Fix
 *
 * Prevents checkout auto-fill and chains check-in selection to checkout picker
 *
 * @package Shaped Core
 * @since 2.3.0
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        if (typeof MPHB === 'undefined') {
            return;
        }

        setTimeout(function() {
            initDatepickerChain();
        }, 100);
    });

    function initDatepickerChain() {
        // SearchCheckInDatepicker (search forms)
        if (MPHB.SearchCheckInDatepicker) {
            var originalSearchGetSettings = MPHB.SearchCheckInDatepicker.prototype.getDatepickSettings;

            MPHB.SearchCheckInDatepicker.prototype.getDatepickSettings = function() {
                var settings = originalSearchGetSettings.call(this);
                var self = this;
                var originalOnSelect = settings.onSelect;

                settings.onSelect = function(dates) {
                    if (originalOnSelect) {
                        originalOnSelect.call(this, dates);
                    }

                    if (self.form && self.form.checkOutDatepicker) {
                        self.form.checkOutDatepicker.element.val('');
                        self.form.checkOutDatepicker.hiddenElement.val('');

                        setTimeout(function() {
                            self.form.checkOutDatepicker.element.datepick('show');
                        }, 150);
                    }
                };

                return settings;
            };
        }

        // RoomTypeCheckInDatepicker (room type pages)
        if (MPHB.RoomTypeCheckInDatepicker) {
            var originalRoomTypeGetSettings = MPHB.RoomTypeCheckInDatepicker.prototype.getDatepickSettings;

            MPHB.RoomTypeCheckInDatepicker.prototype.getDatepickSettings = function() {
                var settings = originalRoomTypeGetSettings.call(this);
                var self = this;
                var originalOnSelect = settings.onSelect;

                settings.onSelect = function(dates) {
                    if (originalOnSelect) {
                        originalOnSelect.call(this, dates);
                    }

                    if (self.form && self.form.checkOutDatepicker) {
                        setTimeout(function() {
                            self.form.checkOutDatepicker.element.datepick('show');
                        }, 150);
                    }
                };

                return settings;
            };
        }
    }

})(jQuery);
