/**
 * Shaped Core - Litepicker Adapter for MPHB
 *
 * Replaces MPHB's Keith Wood datepicker with Litepicker for a single
 * calendar popup with date range selection (like Booking.com).
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
     * Initialize Litepicker on all MPHB search forms
     */
    function initShapedLitepicker() {
        // Check if MPHB is available
        if (typeof MPHB === 'undefined' || !MPHB._data) {
            console.warn('Shaped Litepicker: MPHB not available');
            return;
        }

        // Find all search forms
        var searchForms = document.querySelectorAll('.mphb_sc_search-form');

        searchForms.forEach(function(form) {
            initLitepickerOnForm(form);
        });

        console.log('Shaped Litepicker: Initialized on', searchForms.length, 'form(s)');
    }

    /**
     * Initialize Litepicker on a single search form
     */
    function initLitepickerOnForm(form) {
        var checkInInput = form.querySelector('input[id^="mphb_check_in_date"]');
        var checkOutInput = form.querySelector('input[id^="mphb_check_out_date"]');

        if (!checkInInput || !checkOutInput) {
            console.warn('Shaped Litepicker: Date inputs not found in form');
            return;
        }

        // Get the uniqid from input ID
        var uniqid = checkInInput.id.replace('mphb_check_in_date-', '');

        // Find hidden inputs
        var checkInHidden = form.querySelector('#mphb_check_in_date-' + uniqid + '-hidden');
        var checkOutHidden = form.querySelector('#mphb_check_out_date-' + uniqid + '-hidden');

        if (!checkInHidden || !checkOutHidden) {
            console.warn('Shaped Litepicker: Hidden inputs not found');
            return;
        }

        // Destroy existing MPHB datepickers if they exist
        destroyMPHBDatepickers(checkInInput, checkOutInput);

        // Get first available check-in date from form data attribute
        var firstAvailableDate = form.getAttribute('data-first_available_check_in_date');
        var minDate = firstAvailableDate ? new Date(firstAvailableDate + 'T00:00:00') : new Date();

        // Create a wrapper element for the picker
        var pickerContainer = document.createElement('div');
        pickerContainer.className = 'shaped-litepicker-container';
        form.appendChild(pickerContainer);

        // Track loading state and current limitations
        var state = {
            isLoading: false,
            minCheckOutDate: null,
            maxCheckOutDate: null,
            checkInDate: null,
            loadedMonths: {}
        };

        // Initialize Litepicker
        var picker = new Litepicker({
            element: checkInInput,
            elementEnd: checkOutInput,
            parentEl: pickerContainer,
            singleMode: false,
            allowRepick: true,
            numberOfMonths: 2,
            numberOfColumns: 2,
            minDate: minDate,
            format: getMPHBDateFormat(),
            separator: ' - ',
            autoApply: true,
            showTooltip: true,
            tooltipNumber: function(totalDays) {
                return totalDays - 1;
            },
            tooltipText: {
                one: 'night',
                other: 'nights'
            },
            firstDay: MPHB._data.settings.firstDay || 0,
            lang: getLanguageCode(),

            // Lock unavailable days
            lockDays: [],
            lockDaysFilter: function(date1, date2, pickedDates) {
                return isDateLocked(date1, state, pickedDates);
            },

            // Highlight date range
            highlightedDays: [],

            // Event handlers
            setup: function(picker) {
                picker.on('show', function() {
                    // Load availability data when picker opens
                    var displayDate = picker.getDate() || new Date();
                    loadAvailabilityForMonths(displayDate, 2, 0, state, picker);
                });

                picker.on('change:month', function(date, calendarIdx) {
                    // Load more data when navigating months
                    loadAvailabilityForMonths(date, 2, 0, state, picker);
                });

                picker.on('selected', function(startDate, endDate) {
                    handleDateSelection(startDate, endDate, checkInHidden, checkOutHidden, state, picker);
                });

                picker.on('clear:selection', function() {
                    state.checkInDate = null;
                    state.minCheckOutDate = null;
                    state.maxCheckOutDate = null;
                    checkInHidden.value = '';
                    checkOutHidden.value = '';
                    checkInInput.value = '';
                    checkOutInput.value = '';
                });

                picker.on('preselect', function(startDate, endDate) {
                    // When first date is picked, calculate checkout limitations
                    if (startDate && !endDate) {
                        state.checkInDate = startDate.toJSDate();
                        calculateCheckOutLimitations(state.checkInDate, 0, state);
                        picker.render();
                    }
                });
            },

            // Custom day renderer to add availability classes
            onRenderDay: function(day) {
                // This is called for each day in the calendar
                var date = day.dateInstance;
                if (!date) return;

                var classes = getDayClasses(date, state);
                if (classes.length) {
                    day.classList.add(...classes);
                }
            }
        });

        // Store picker reference for later use
        form._shapedPicker = picker;
        form._shapedState = state;

        // Handle clicking on inputs to show picker
        checkInInput.addEventListener('focus', function(e) {
            e.preventDefault();
            picker.show();
        });

        checkOutInput.addEventListener('focus', function(e) {
            e.preventDefault();
            picker.show();
        });

        // Restore existing values if present
        if (checkInHidden.value && checkOutHidden.value) {
            var startDate = new Date(checkInHidden.value + 'T00:00:00');
            var endDate = new Date(checkOutHidden.value + 'T00:00:00');
            picker.setDateRange(startDate, endDate);
        }
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
        startLoadDate.setDate(startLoadDate.getDate() - 1); // Day before for calculations

        var endLoadDate = new Date(year, month + monthsCount + 1, 1);

        var formattedStart = formatDateYMD(startLoadDate);
        var formattedEnd = formatDateYMD(endLoadDate);

        // Create cache entry
        var cacheKey = roomTypeId.toString();
        if (!availabilityCache[cacheKey]) {
            availabilityCache[cacheKey] = {};
        }

        // Check if data is already cached
        if (availabilityCache[cacheKey][formattedStart] && availabilityCache[cacheKey][formattedEnd]) {
            state.loadedMonths[monthKey] = true;
            return;
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

                        // Recalculate limitations if check-in is selected
                        if (state.checkInDate) {
                            calculateCheckOutLimitations(state.checkInDate, roomTypeId, state);
                        }

                        // Re-render picker to apply new availability data
                        picker.render();
                    }
                } catch (e) {
                    console.error('Shaped Litepicker: Error parsing response', e);
                }
            }

            state.isLoading = false;
            hideLoadingState(picker);
        };

        xhr.onerror = function() {
            delete activeRequests[requestKey];
            state.isLoading = false;
            hideLoadingState(picker);
            console.error('Shaped Litepicker: Request failed');
        };

        xhr.send();
    }

    /**
     * Check if a date should be locked
     */
    function isDateLocked(date, state, pickedDates) {
        var jsDate = date.toJSDate ? date.toJSDate() : date;
        var formattedDate = formatDateYMD(jsDate);
        var roomTypeId = '0';
        var data = availabilityCache[roomTypeId] && availabilityCache[roomTypeId][formattedDate];

        if (!data) {
            return false; // Don't lock if no data yet
        }

        var status = data.roomTypeStatus;

        // Check basic unavailability
        if (status === STATUS.PAST ||
            status === STATUS.BOOKED ||
            status === STATUS.NOT_AVAILABLE) {

            // For check-out mode, booked/not-available dates might still be valid checkout dates
            if (state.checkInDate && pickedDates && pickedDates.length === 1) {
                // We're selecting check-out
                if (!data.isCheckOutNotAllowed) {
                    return false; // Can checkout on this date
                }
            }
            return true;
        }

        // Check advance booking rules
        if (status === STATUS.EARLIER_MIN_ADVANCE || status === STATUS.LATER_MAX_ADVANCE) {
            // These dates are blocked for check-in, but may be valid for checkout
            if (state.checkInDate && pickedDates && pickedDates.length === 1) {
                if (!data.isCheckOutNotAllowed) {
                    return false;
                }
            }
            return true;
        }

        // If we're in check-in selection mode
        if (!state.checkInDate || (pickedDates && pickedDates.length === 0)) {
            // Lock dates where check-in is not allowed
            if (data.isCheckInNotAllowed) {
                return true;
            }
            if (data.isStayInNotAllowed) {
                return true;
            }
        }

        // If we're in check-out selection mode
        if (state.checkInDate && pickedDates && pickedDates.length === 1) {
            // Check if date is before min checkout
            if (state.minCheckOutDate && jsDate < state.minCheckOutDate) {
                return true;
            }
            // Check if date is after max checkout
            if (state.maxCheckOutDate && jsDate > state.maxCheckOutDate) {
                return true;
            }
            // Check if checkout is not allowed on this date
            if (data.isCheckOutNotAllowed) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get CSS classes for a day based on availability
     */
    function getDayClasses(date, state) {
        var classes = [];
        var formattedDate = formatDateYMD(date);
        var roomTypeId = '0';
        var data = availabilityCache[roomTypeId] && availabilityCache[roomTypeId][formattedDate];

        if (!data) {
            return classes;
        }

        var status = data.roomTypeStatus;

        // Add status-based classes
        if (status === STATUS.AVAILABLE) {
            classes.push('shaped-date-available');
        } else if (status === STATUS.BOOKED) {
            classes.push('shaped-date-booked');
        } else if (status === STATUS.NOT_AVAILABLE) {
            classes.push('shaped-date-not-available');
        } else if (status === STATUS.PAST) {
            classes.push('shaped-date-past');
        }

        // Add rule-based classes
        if (data.isCheckInNotAllowed) {
            classes.push('shaped-no-checkin');
        }
        if (data.isCheckOutNotAllowed) {
            classes.push('shaped-no-checkout');
        }
        if (data.isStayInNotAllowed) {
            classes.push('shaped-no-stayin');
        }

        // Add min/max stay indicators when in checkout selection mode
        if (state.checkInDate) {
            if (state.minCheckOutDate && date < state.minCheckOutDate) {
                classes.push('shaped-before-min-stay');
            }
            if (state.maxCheckOutDate && date > state.maxCheckOutDate) {
                classes.push('shaped-after-max-stay');
            }
        }

        return classes;
    }

    /**
     * Calculate checkout date limitations based on check-in date
     */
    function calculateCheckOutLimitations(checkInDate, roomTypeId, state) {
        var formattedCheckIn = formatDateYMD(checkInDate);
        var cacheKey = roomTypeId.toString();
        var cache = availabilityCache[cacheKey] || {};
        var data = cache[formattedCheckIn];

        if (!data) {
            state.minCheckOutDate = null;
            state.maxCheckOutDate = null;
            return;
        }

        // Calculate min checkout based on minStayNights
        if (data.minStayNights) {
            var minCheckout = new Date(checkInDate);
            minCheckout.setDate(minCheckout.getDate() + data.minStayNights);
            minCheckout.setHours(0, 0, 0, 1);
            state.minCheckOutDate = minCheckout;
        } else {
            // Default: at least 1 night stay
            var minCheckout = new Date(checkInDate);
            minCheckout.setDate(minCheckout.getDate() + 1);
            minCheckout.setHours(0, 0, 0, 1);
            state.minCheckOutDate = minCheckout;
        }

        // Calculate max checkout based on maxStayNights and availability
        var maxCheckout = null;
        if (data.maxStayNights) {
            maxCheckout = new Date(checkInDate);
            maxCheckout.setDate(maxCheckout.getDate() + data.maxStayNights);
            maxCheckout.setHours(23, 59, 59, 999);
        }

        // Walk forward to find the actual max checkout (limited by stay-in restrictions and bookings)
        var processingDate = new Date(checkInDate);
        processingDate.setDate(processingDate.getDate() + 1);

        var foundMaxCheckout = null;
        var iterationLimit = 365; // Safety limit
        var iterations = 0;

        while (iterations < iterationLimit) {
            var formattedDate = formatDateYMD(processingDate);
            var dateData = cache[formattedDate];

            if (!dateData) {
                break; // No more data available
            }

            var isStayAllowed = !dateData.isStayInNotAllowed &&
                                dateData.roomTypeStatus !== STATUS.BOOKED &&
                                dateData.roomTypeStatus !== STATUS.NOT_AVAILABLE;

            if (!isStayAllowed) {
                // Can't stay on this date, but might be able to checkout
                if (!dateData.isCheckOutNotAllowed) {
                    foundMaxCheckout = new Date(processingDate);
                }
                break;
            }

            // Update max checkout if this date allows checkout
            if (!dateData.isCheckOutNotAllowed) {
                foundMaxCheckout = new Date(processingDate);
            }

            // Check against maxStayNights limit
            if (maxCheckout && processingDate >= maxCheckout) {
                break;
            }

            processingDate.setDate(processingDate.getDate() + 1);
            iterations++;
        }

        if (foundMaxCheckout) {
            foundMaxCheckout.setHours(23, 59, 59, 999);
            state.maxCheckOutDate = foundMaxCheckout;
        } else {
            state.maxCheckOutDate = maxCheckout;
        }
    }

    /**
     * Handle date selection
     */
    function handleDateSelection(startDate, endDate, checkInHidden, checkOutHidden, state, picker) {
        if (!startDate) {
            checkInHidden.value = '';
            checkOutHidden.value = '';
            state.checkInDate = null;
            return;
        }

        var startJs = startDate.toJSDate ? startDate.toJSDate() : startDate;
        checkInHidden.value = formatDateYMD(startJs);
        state.checkInDate = startJs;

        if (endDate) {
            var endJs = endDate.toJSDate ? endDate.toJSDate() : endDate;
            checkOutHidden.value = formatDateYMD(endJs);

            // Reset limitations when full range is selected
            state.minCheckOutDate = null;
            state.maxCheckOutDate = null;

            // Trigger MPHB form update if needed
            triggerFormUpdate(checkInHidden, checkOutHidden);
        } else {
            checkOutHidden.value = '';
            // Calculate checkout limitations for the selected check-in date
            calculateCheckOutLimitations(startJs, 0, state);
            picker.render();
        }
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
        var container = picker.options.parentEl;
        if (container && !container.querySelector('.shaped-loader')) {
            var loader = document.createElement('div');
            loader.className = 'shaped-loader';
            loader.innerHTML = '<div class="shaped-loader-spinner"></div>';
            container.appendChild(loader);
        }
    }

    /**
     * Hide loading state on picker
     */
    function hideLoadingState(picker) {
        var container = picker.options.parentEl;
        if (container) {
            var loader = container.querySelector('.shaped-loader');
            if (loader) {
                loader.remove();
            }
        }
    }

    /**
     * Convert MPHB date format to Litepicker format
     */
    function getMPHBDateFormat() {
        // MPHB uses PHP date format, Litepicker uses different tokens
        // Common formats: d/m/Y -> DD/MM/YYYY, Y-m-d -> YYYY-MM-DD
        var mphbFormat = MPHB._data.settings.dateFormat || 'DD/MM/YYYY';

        // Convert from MPHB JS format to Litepicker format
        var format = mphbFormat
            .replace('dd', 'DD')
            .replace('d', 'D')
            .replace('mm', 'MM')
            .replace('m', 'M')
            .replace('yy', 'YY')
            .replace('yyyy', 'YYYY');

        return format;
    }

    /**
     * Get language code for Litepicker
     */
    function getLanguageCode() {
        var lang = MPHB._data.settings.currentLanguage || document.documentElement.lang || 'en';
        // Handle WordPress locale format (en_US -> en)
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
        init: initShapedLitepicker
    };

})();
