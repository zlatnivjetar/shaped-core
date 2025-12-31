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
        // Check if Litepicker is available
        if (typeof Litepicker === 'undefined') {
            console.warn('Shaped Litepicker: Litepicker library not loaded');
            return;
        }

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
        var checkInInput = form.querySelector('input[id^="mphb_check_in_date"]:not([type="hidden"])');
        var checkOutInput = form.querySelector('input[id^="mphb_check_out_date"]:not([type="hidden"])');

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

        // Track state - use flag to prevent render loops
        var state = {
            isLoading: false,
            isRendering: false,
            isUpdating: false,
            checkInDate: null,
            loadedMonths: {}
        };

        // Initialize Litepicker with minimal config first
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
                    // Prevent handling if we're already updating
                    if (state.isUpdating) {
                        return;
                    }
                    handleDateSelection(startDate, endDate, checkInHidden, checkOutHidden, state);
                });

                picker.on('clear:selection', function() {
                    state.checkInDate = null;
                    checkInHidden.value = '';
                    checkOutHidden.value = '';
                    checkInInput.value = '';
                    checkOutInput.value = '';
                });
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
     * Handle date selection
     */
    function handleDateSelection(startDate, endDate, checkInHidden, checkOutHidden, state) {
        // Prevent re-entrancy from MPHB event handlers
        if (state.isUpdating) {
            return;
        }

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

            // Trigger MPHB form update with guard
            state.isUpdating = true;
            triggerFormUpdate(checkInHidden, checkOutHidden);
            // Reset flag after a short delay to allow MPHB handlers to complete
            setTimeout(function() {
                state.isUpdating = false;
            }, 100);
        } else {
            checkOutHidden.value = '';
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
        var mphbFormat = MPHB._data.settings.dateFormat || 'DD/MM/YYYY';

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
