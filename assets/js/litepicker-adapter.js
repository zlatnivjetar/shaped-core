/**
 * Shaped Core - Litepicker Adapter for MPHB
 *
 * Replaces MPHB's Keith Wood datepicker with Litepicker for a single
 * calendar popup with date range selection (like Booking.com).
 *
 * Features:
 * - Booking.com-style date selection:
 *   - First click: always sets check-in date
 *   - Second click: sets check-out (if after check-in)
 *   - Third click (or click before/on check-in): resets and sets new check-in
 * - Min/max nights validation with warnings
 * - MPHB availability integration
 *
 * Uses MPHB's existing AJAX endpoint (mphb_get_room_type_calendar_data)
 * for availability data.
 */

(function() {
    'use strict';

    // Wait for DOM and MPHB to be ready
    document.addEventListener('DOMContentLoaded', function() {
        // Small delay to ensure MPHB has initialized
        setTimeout(initShapedLitepicker, 100);
    });

    /**
     * Availability data cache
     * Key: roomTypeId
     * Value: { 'YYYY-MM-DD': {...availability data...} }
     */
    var availabilityCache = {};
    var activeRequests = {};

    /**
     * Room type availability status constants (matching MPHB)
     */
    var STATUS = {
        AVAILABLE: 'available',
        NOT_AVAILABLE: 'not-available',
        BOOKED: 'booked',
        PAST: 'past',
        EARLIER_MIN_ADVANCE: 'earlier-min-advance',
        LATER_MAX_ADVANCE: 'later-max-advance'
    };

    /**
     * Get booking rules from backend or use defaults
     */
    function getBookingRules() {
        var defaults = {
            minNights: 1,
            maxNights: 30
        };

        if (typeof ShapedBookingRules !== 'undefined') {
            return {
                minNights: parseInt(ShapedBookingRules.minNights, 10) || defaults.minNights,
                maxNights: parseInt(ShapedBookingRules.maxNights, 10) || defaults.maxNights
            };
        }

        return defaults;
    }

    /**
     * Initialize Litepicker on all MPHB search forms
     */
    function initShapedLitepicker() {
        // Check if Litepicker is available
        if (typeof Litepicker === 'undefined') {
            return;
        }

        // Check if MPHB is available
        if (typeof MPHB === 'undefined' || !MPHB._data) {
            return;
        }

        // Find all search forms
        var searchForms = document.querySelectorAll('.mphb_sc_search-form');

        searchForms.forEach(function(form) {
            initLitepickerOnForm(form);
        });
    }

    /**
     * Initialize Litepicker on a single search form
     */
    function initLitepickerOnForm(form) {
        var checkInInput = form.querySelector('input[id^="mphb_check_in_date"]:not([type="hidden"])');
        var checkOutInput = form.querySelector('input[id^="mphb_check_out_date"]:not([type="hidden"])');

        if (!checkInInput || !checkOutInput) {
            return;
        }

        // Get the uniqid from input ID
        var uniqid = checkInInput.id.replace('mphb_check_in_date-', '');

        // Find hidden inputs
        var checkInHidden = form.querySelector('#mphb_check_in_date-' + uniqid + '-hidden');
        var checkOutHidden = form.querySelector('#mphb_check_out_date-' + uniqid + '-hidden');

        if (!checkInHidden || !checkOutHidden) {
            return;
        }

        // Destroy existing MPHB datepickers if they exist
        destroyMPHBDatepickers(checkInInput, checkOutInput);

        // Get first available check-in date from form data attribute
        var firstAvailableDate = form.getAttribute('data-first_available_check_in_date');
        var minDate = firstAvailableDate ? new Date(firstAvailableDate + 'T00:00:00') : new Date();

        // Get booking rules
        var rules = getBookingRules();

        // Track state for Booking.com-style selection
        var state = {
            isLoading: false,
            isRendering: false,
            isUpdating: false,
            checkInDate: null,      // Currently selected check-in date (JS Date)
            checkOutDate: null,     // Currently selected check-out date (JS Date)
            hasCompleteRange: false, // True when both dates are selected
            loadedMonths: {}
        };

        // Initialize Litepicker in single mode for full control over selection behavior
        var picker = new Litepicker({
            element: checkInInput,
            elementEnd: checkOutInput,
            inlineMode: false,
            singleMode: true,       // Single mode gives us full control
            numberOfMonths: 2,
            numberOfColumns: 2,
            minDate: minDate,
            format: getMPHBDateFormat(),
            autoApply: true,
            showTooltip: false,     // We'll handle tooltips ourselves
            scrollToDate: true,
            resetButton: true,
            dropdowns: {
                minYear: new Date().getFullYear(),
                maxYear: new Date().getFullYear() + 2,
                months: true,
                years: true
            },
            firstDay: MPHB._data.settings.firstDay || 0,
            lang: getLanguageCode(),

            // Event handlers
            setup: function(picker) {
                picker.on('show', function() {
                    // Load availability data when picker opens
                    var displayDate = state.checkInDate || picker.getDate() || new Date();
                    loadAvailabilityForMonths(displayDate, 2, 0, state, picker);

                    // Re-render highlighting if we have dates selected
                    if (state.checkInDate) {
                        setTimeout(function() {
                            updateDateHighlighting(picker, state);
                        }, 50);
                    }
                });

                picker.on('change:month', function(date, calendarIdx) {
                    // Load more data when navigating months
                    loadAvailabilityForMonths(date, 2, 0, state, picker);

                    // Re-apply highlighting after month change
                    setTimeout(function() {
                        updateDateHighlighting(picker, state);
                    }, 50);
                });

                picker.on('selected', function(date) {
                    // Prevent handling if we're already updating
                    if (state.isUpdating) {
                        return;
                    }

                    handleSingleDateClick(date, checkInHidden, checkOutHidden, checkInInput, checkOutInput, state, picker, rules, form);
                });

                picker.on('clear:selection', function() {
                    resetSelection(state, checkInHidden, checkOutHidden, checkInInput, checkOutInput, form, picker);
                });

                // Handle render to apply custom highlighting
                picker.on('render', function(ui) {
                    setTimeout(function() {
                        updateDateHighlighting(picker, state);
                    }, 10);
                });
            }
        });

        // Store picker reference for later use
        form._shapedPicker = picker;
        form._shapedState = state;

        // Handle clicking on inputs to show picker
        checkInInput.addEventListener('click', function(e) {
            picker.show();
        });

        checkOutInput.addEventListener('click', function(e) {
            picker.show();
        });

        // Also handle focus
        checkInInput.addEventListener('focus', function(e) {
            picker.show();
        });

        checkOutInput.addEventListener('focus', function(e) {
            picker.show();
        });

        // Restore existing values if present
        if (checkInHidden.value && checkOutHidden.value) {
            var startDate = new Date(checkInHidden.value + 'T00:00:00');
            var endDate = new Date(checkOutHidden.value + 'T00:00:00');
            state.checkInDate = startDate;
            state.checkOutDate = endDate;
            state.hasCompleteRange = true;

            // Update visible inputs
            checkInInput.value = formatDateForDisplay(startDate);
            checkOutInput.value = formatDateForDisplay(endDate);

            // Validate existing selection
            validateAndWarn(state, rules, form);
        }
    }

    /**
     * Handle single date click with Booking.com-style behavior
     *
     * Booking.com behavior:
     * 1. If no dates selected or have complete range: clicked date becomes new check-in
     * 2. If only check-in selected: clicked date becomes check-out (if after check-in)
     *    OR becomes new check-in (if on/before current check-in)
     */
    function handleSingleDateClick(date, checkInHidden, checkOutHidden, checkInInput, checkOutInput, state, picker, rules, form) {
        if (state.isUpdating) {
            return;
        }

        if (!date) {
            return;
        }

        var clickedDate = date.toJSDate ? date.toJSDate() : date;

        // Scenario 1: No check-in selected OR complete range exists -> set as new check-in
        if (!state.checkInDate || state.hasCompleteRange) {
            // Reset and set new check-in
            state.checkInDate = clickedDate;
            state.checkOutDate = null;
            state.hasCompleteRange = false;

            // Update inputs
            checkInHidden.value = formatDateYMD(clickedDate);
            checkOutHidden.value = '';
            checkInInput.value = formatDateForDisplay(clickedDate);
            checkOutInput.value = '';

            hideWarning(form);
            updateDateHighlighting(picker, state);
            return;
        }

        // Scenario 2: Only check-in selected (waiting for check-out)
        // If clicked date is AFTER check-in -> becomes check-out
        // If clicked date is ON or BEFORE check-in -> becomes new check-in
        if (clickedDate > state.checkInDate) {
            // Set as check-out
            state.checkOutDate = clickedDate;
            state.hasCompleteRange = true;

            // Update inputs
            checkOutHidden.value = formatDateYMD(clickedDate);
            checkOutInput.value = formatDateForDisplay(clickedDate);

            // Validate the selection
            validateAndWarn(state, rules, form);

            // Trigger MPHB form update
            state.isUpdating = true;
            triggerFormUpdate(checkInHidden, checkOutHidden);
            setTimeout(function() {
                state.isUpdating = false;
            }, 100);
        } else {
            // Clicked on or before check-in -> reset to this date as new check-in
            state.checkInDate = clickedDate;
            state.checkOutDate = null;
            state.hasCompleteRange = false;

            // Update inputs
            checkInHidden.value = formatDateYMD(clickedDate);
            checkOutHidden.value = '';
            checkInInput.value = formatDateForDisplay(clickedDate);
            checkOutInput.value = '';

            hideWarning(form);
        }

        updateDateHighlighting(picker, state);
    }

    /**
     * Reset selection state
     */
    function resetSelection(state, checkInHidden, checkOutHidden, checkInInput, checkOutInput, form, picker) {
        state.checkInDate = null;
        state.checkOutDate = null;
        state.hasCompleteRange = false;
        checkInHidden.value = '';
        checkOutHidden.value = '';
        checkInInput.value = '';
        checkOutInput.value = '';
        hideWarning(form);
        clearDateHighlighting(picker);
    }

    /**
     * Update visual highlighting for selected date range
     */
    function updateDateHighlighting(picker, state) {
        // Clear existing highlighting first
        clearDateHighlighting(picker);

        if (!picker.ui) {
            return;
        }

        var dayItems = picker.ui.querySelectorAll('.day-item');

        dayItems.forEach(function(dayEl) {
            var dateStr = dayEl.getAttribute('data-time');
            if (!dateStr) return;

            var dayDate = new Date(parseInt(dateStr, 10));

            // Normalize to start of day for comparison
            dayDate.setHours(0, 0, 0, 0);

            var checkIn = state.checkInDate ? new Date(state.checkInDate.getTime()) : null;
            var checkOut = state.checkOutDate ? new Date(state.checkOutDate.getTime()) : null;

            if (checkIn) checkIn.setHours(0, 0, 0, 0);
            if (checkOut) checkOut.setHours(0, 0, 0, 0);

            // Check-in date
            if (checkIn && dayDate.getTime() === checkIn.getTime()) {
                dayEl.classList.add('shaped-check-in', 'is-start-date');
            }

            // Check-out date
            if (checkOut && dayDate.getTime() === checkOut.getTime()) {
                dayEl.classList.add('shaped-check-out', 'is-end-date');
            }

            // In-range dates
            if (checkIn && checkOut && dayDate > checkIn && dayDate < checkOut) {
                dayEl.classList.add('shaped-in-range', 'is-in-range');
            }
        });
    }

    /**
     * Clear visual highlighting
     */
    function clearDateHighlighting(picker) {
        if (!picker.ui) {
            return;
        }

        var dayItems = picker.ui.querySelectorAll('.day-item');
        dayItems.forEach(function(dayEl) {
            dayEl.classList.remove('shaped-check-in', 'shaped-check-out', 'shaped-in-range', 'is-start-date', 'is-end-date', 'is-in-range');
        });
    }

    /**
     * Format date for display using MPHB format
     */
    function formatDateForDisplay(date) {
        var format = getMPHBDateFormat();

        var day = String(date.getDate()).padStart(2, '0');
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var year = date.getFullYear();

        // Handle common formats
        return format
            .replace('DD', day)
            .replace('D', date.getDate())
            .replace('MM', month)
            .replace('M', date.getMonth() + 1)
            .replace('YYYY', year)
            .replace('YY', String(year).slice(-2));
    }

    /**
     * Destroy MPHB's existing datepickers
     */
    function destroyMPHBDatepickers(checkInInput, checkOutInput) {
        try {
            if (typeof jQuery !== 'undefined' && jQuery.fn.datepick) {
                jQuery(checkInInput).datepick('destroy');
                jQuery(checkOutInput).datepick('destroy');
            }
        } catch (e) {
            // Ignore errors if datepicker wasn't initialized
        }

        // Remove MPHB's datepicker popups if they exist
        var popups = document.querySelectorAll('.datepick-popup');
        popups.forEach(function(popup) {
            popup.remove();
        });
    }

    /**
     * Calculate number of nights between two dates
     */
    function calculateNights(checkIn, checkOut) {
        if (!checkIn || !checkOut) {
            return 0;
        }
        var diffTime = checkOut.getTime() - checkIn.getTime();
        return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    }

    /**
     * Validate selection and show warning if needed
     */
    function validateAndWarn(state, rules, form) {
        if (!state.checkInDate || !state.checkOutDate) {
            hideWarning(form);
            return;
        }

        var nights = calculateNights(state.checkInDate, state.checkOutDate);
        var warnings = [];

        if (nights < rules.minNights) {
            warnings.push('Minimum stay is ' + rules.minNights + ' night' + (rules.minNights > 1 ? 's' : '') + '.');
        }

        if (nights > rules.maxNights) {
            warnings.push('Maximum stay is ' + rules.maxNights + ' nights.');
        }

        if (warnings.length > 0) {
            showWarning(form, warnings.join(' '));
        } else {
            hideWarning(form);
        }
    }

    /**
     * Show warning message near the form
     */
    function showWarning(form, message) {
        var warningId = 'shaped-booking-warning';
        var warning = form.querySelector('#' + warningId);

        if (!warning) {
            warning = document.createElement('div');
            warning.id = warningId;
            warning.className = 'shaped-booking-warning';
            warning.style.cssText = 'background: #fff3cd; border: 1px solid #ffc107; color: #856404; padding: 10px 15px; margin: 10px 0; border-radius: 4px; font-size: 14px;';

            // Insert after the date inputs wrapper or at the start of the form
            var dateWrapper = form.querySelector('.mphb-reserve-dates-wrapper') ||
                              form.querySelector('.mphb_sc_search-form-dates') ||
                              form.querySelector('.mphb-datepicker');

            if (dateWrapper) {
                dateWrapper.parentNode.insertBefore(warning, dateWrapper.nextSibling);
            } else {
                form.insertBefore(warning, form.firstChild);
            }
        }

        warning.textContent = message;
        warning.style.display = 'block';
    }

    /**
     * Hide warning message
     */
    function hideWarning(form) {
        var warning = form.querySelector('#shaped-booking-warning');
        if (warning) {
            warning.style.display = 'none';
        }
    }

    /**
     * Load availability data for specified months
     */
    function loadAvailabilityForMonths(startDate, monthsCount, roomTypeId, state, picker) {
        var year = startDate.getFullYear();
        var month = startDate.getMonth();

        // Check if already loaded
        var monthKey = year + '-' + (month + 1);
        if (state.loadedMonths[monthKey]) {
            return;
        }

        // Calculate date range
        var startLoadDate = new Date(year, month, 1);
        startLoadDate.setDate(startLoadDate.getDate() - 1);

        var endLoadDate = new Date(year, month + monthsCount + 1, 1);

        var formattedStart = formatDateYMD(startLoadDate);
        var formattedEnd = formatDateYMD(endLoadDate);

        // Create cache entry
        var cacheKey = roomTypeId.toString();
        if (!availabilityCache[cacheKey]) {
            availabilityCache[cacheKey] = {};
        }

        // Check if request is already in progress
        var requestKey = cacheKey + '-' + formattedStart + '-' + formattedEnd;
        if (activeRequests[requestKey]) {
            return;
        }

        // Show loading state
        state.isLoading = true;
        showLoadingState(picker);

        // Make AJAX request using MPHB's endpoint
        var requestData = {
            action: 'mphb_get_room_type_calendar_data',
            mphb_nonce: MPHB._data.nonces['mphb_get_room_type_calendar_data'],
            mphb_is_admin: MPHB._data.isAdmin,
            mphb_locale: MPHB._data.settings.currentLanguage,
            start_date: formattedStart,
            end_date: formattedEnd,
            room_type_id: roomTypeId,
            is_show_prices: false,
            is_truncate_prices: false,
            is_show_prices_currency: false
        };

        var xhr = new XMLHttpRequest();
        xhr.open('GET', MPHB._data.ajaxUrl + '?' + serializeParams(requestData), true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

        activeRequests[requestKey] = xhr;

        xhr.onload = function() {
            delete activeRequests[requestKey];

            if (xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success && response.data) {
                        // Merge data into cache
                        Object.assign(availabilityCache[cacheKey], response.data);
                        state.loadedMonths[monthKey] = true;

                        // Update lock days based on availability - use setTimeout to avoid render loop
                        setTimeout(function() {
                            updateLockedDays(picker, roomTypeId);
                        }, 0);
                    }
                } catch (e) {
                    // Silent fail - availability just won't be shown
                }
            }

            state.isLoading = false;
            hideLoadingState(picker);
        };

        xhr.onerror = function() {
            delete activeRequests[requestKey];
            state.isLoading = false;
            hideLoadingState(picker);
        };

        xhr.send();
    }

    /**
     * Update locked days on the picker based on availability data
     */
    function updateLockedDays(picker, roomTypeId) {
        var cacheKey = roomTypeId.toString();
        var cache = availabilityCache[cacheKey] || {};
        var lockedDays = [];

        // Build array of locked dates
        for (var dateStr in cache) {
            var data = cache[dateStr];
            var status = data.roomTypeStatus;

            // Lock unavailable dates
            if (status === STATUS.PAST ||
                status === STATUS.BOOKED ||
                status === STATUS.NOT_AVAILABLE ||
                status === STATUS.EARLIER_MIN_ADVANCE ||
                status === STATUS.LATER_MAX_ADVANCE ||
                data.isCheckInNotAllowed) {
                lockedDays.push(dateStr);
            }
        }

        // Update picker's locked days
        picker.setLockDays(lockedDays);
    }

    /**
     * Trigger MPHB form update
     */
    function triggerFormUpdate(checkInHidden, checkOutHidden) {
        // Dispatch change events on hidden inputs
        checkInHidden.dispatchEvent(new Event('change', { bubbles: true }));
        checkOutHidden.dispatchEvent(new Event('change', { bubbles: true }));

        // Also dispatch on visible inputs
        var form = checkInHidden.closest('form');
        if (form) {
            var checkInVisible = form.querySelector('input[id^="mphb_check_in_date"]:not([type="hidden"])');
            var checkOutVisible = form.querySelector('input[id^="mphb_check_out_date"]:not([type="hidden"])');

            if (checkInVisible) {
                checkInVisible.dispatchEvent(new Event('change', { bubbles: true }));
            }
            if (checkOutVisible) {
                checkOutVisible.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
    }

    /**
     * Show loading state on picker
     */
    function showLoadingState(picker) {
        var pickerUI = picker.ui;
        if (pickerUI && !pickerUI.querySelector('.shaped-loader')) {
            var loader = document.createElement('div');
            loader.className = 'shaped-loader';
            loader.innerHTML = '<div class="shaped-loader-spinner"></div>';
            pickerUI.appendChild(loader);
        }
    }

    /**
     * Hide loading state on picker
     */
    function hideLoadingState(picker) {
        var pickerUI = picker.ui;
        if (pickerUI) {
            var loader = pickerUI.querySelector('.shaped-loader');
            if (loader) {
                loader.remove();
            }
        }
    }

    /**
     * Convert MPHB date format to Litepicker format
     */
    function getMPHBDateFormat() {
        var mphbFormat = MPHB._data.settings.dateFormat || 'DD/MM/YYYY';

        // Replace longer patterns first to avoid partial replacements
        var format = mphbFormat
            .replace('yyyy', 'YYYY')
            .replace('yy', 'YY')
            .replace('dd', 'DD')
            .replace('d', 'D')
            .replace('mm', 'MM')
            .replace('m', 'M');

        return format;
    }

    /**
     * Get language code for Litepicker
     */
    function getLanguageCode() {
        var lang = MPHB._data.settings.currentLanguage || document.documentElement.lang || 'en';
        return lang.split(/[-_]/)[0];
    }

    /**
     * Format date as YYYY-MM-DD
     */
    function formatDateYMD(date) {
        var year = date.getFullYear();
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var day = String(date.getDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
    }

    /**
     * Serialize object to URL params
     */
    function serializeParams(obj) {
        var str = [];
        for (var key in obj) {
            if (obj.hasOwnProperty(key)) {
                str.push(encodeURIComponent(key) + '=' + encodeURIComponent(obj[key]));
            }
        }
        return str.join('&');
    }

    // Expose for debugging
    window.ShapedLitepicker = {
        cache: availabilityCache,
        init: initShapedLitepicker,
        getBookingRules: getBookingRules
    };

})();
