/**
 * Litepicker Initialization for MPHB Integration
 *
 * Replaces MPHB's native datepicker with Litepicker while maintaining
 * full compatibility with MPHB's search functionality and RoomCloud availability.
 */

(function($) {
    'use strict';

    // Configuration
    const DATE_FORMAT = 'DD/MM/YYYY';
    const MIN_NIGHTS = 1;
    let litepickerInstance = null;

    /**
     * Initialize Litepicker on MPHB search forms
     */
    function initLitepicker() {
        // Find MPHB date input fields
        const checkInInput = document.querySelector('input[name="mphb_check_in_date"]');
        const checkOutInput = document.querySelector('input[name="mphb_check_out_date"]');

        // Exit if inputs not found
        if (!checkInInput || !checkOutInput) {
            console.log('[Litepicker] MPHB date inputs not found on this page');
            return;
        }

        console.log('[Litepicker] Initializing on MPHB search form');

        // Remove readonly attribute from inputs (MPHB adds this)
        checkInInput.removeAttribute('readonly');
        checkOutInput.removeAttribute('readonly');

        // Remove MPHB's datepicker classes to prevent conflicts
        checkInInput.classList.remove('mphb-datepick');
        checkOutInput.classList.remove('mphb-datepick');

        // Initialize Litepicker
        try {
            litepickerInstance = new Litepicker({
                element: checkInInput,
                elementEnd: checkOutInput,

                // Date format matching MPHB
                format: DATE_FORMAT,

                // Range selection (check-in to check-out)
                singleMode: false,

                // Display configuration
                numberOfMonths: 2,
                numberOfColumns: 2,

                // Prevent selecting past dates
                minDate: new Date(),

                // Minimum stay (1 night)
                minDays: MIN_NIGHTS,

                // Auto apply selection (no "Apply" button needed)
                autoApply: true,

                // Show weekday labels
                showWeekNumbers: false,

                // First day of week (0 = Sunday, 1 = Monday)
                firstDay: 1,

                // Dropdown for month/year selection
                dropdowns: {
                    minYear: new Date().getFullYear(),
                    maxYear: new Date().getFullYear() + 2,
                    months: true,
                    years: true,
                },

                // Positioning
                position: 'auto',

                // Mobile responsive
                mobileFriendly: true,

                // Split view on mobile
                splitView: window.innerWidth >= 768,

                // Tooltips
                tooltipText: {
                    one: 'night',
                    other: 'nights'
                },

                // Setup callback - called when picker is initialized
                setup: (picker) => {
                    console.log('[Litepicker] Picker initialized successfully');

                    // Store reference for later use
                    window.shapedLitepickerInstance = picker;
                },

                // Callback when dates are selected
                onSelect: (start, end) => {
                    if (start && end) {
                        console.log('[Litepicker] Dates selected:', {
                            checkIn: start.format(DATE_FORMAT),
                            checkOut: end.format(DATE_FORMAT)
                        });

                        // Update input values in MPHB format
                        checkInInput.value = start.format(DATE_FORMAT);
                        checkOutInput.value = end.format(DATE_FORMAT);

                        // Trigger change events for MPHB compatibility
                        triggerChangeEvent(checkInInput);
                        triggerChangeEvent(checkOutInput);

                        // Trigger any existing MPHB handlers
                        if (typeof window.MPHB !== 'undefined') {
                            $(checkInInput).trigger('mphb_datepick_change');
                            $(checkOutInput).trigger('mphb_datepick_change');
                        }
                    }
                },

                // Callback when picker is shown
                onShow: () => {
                    console.log('[Litepicker] Calendar opened');

                    // Add custom class for styling hooks
                    const pickerElement = document.querySelector('.litepicker');
                    if (pickerElement) {
                        pickerElement.classList.add('shaped-litepicker');
                    }
                },

                // Callback when picker is hidden
                onHide: () => {
                    console.log('[Litepicker] Calendar closed');
                },

                // Lock days filter - will be enhanced with RoomCloud availability
                lockDaysFilter: (date) => {
                    // For now, just block past dates (handled by minDate)
                    // RoomCloud blocking will be added in Phase 3
                    return false;
                }
            });

            console.log('[Litepicker] Instance created successfully');

            // Pre-fill dates if they exist in URL or form
            prefillDatesFromURL();

        } catch (error) {
            console.error('[Litepicker] Initialization failed:', error);
        }
    }

    /**
     * Trigger native change event on element
     */
    function triggerChangeEvent(element) {
        const event = new Event('change', { bubbles: true });
        element.dispatchEvent(event);
    }

    /**
     * Pre-fill dates from URL parameters or existing form values
     */
    function prefillDatesFromURL() {
        if (!litepickerInstance) return;

        // Check URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        const checkInParam = urlParams.get('mphb_check_in_date');
        const checkOutParam = urlParams.get('mphb_check_out_date');

        if (checkInParam && checkOutParam) {
            try {
                // Parse dates from URL (DD/MM/YYYY format)
                const checkInDate = parseDate(checkInParam);
                const checkOutDate = parseDate(checkOutParam);

                if (checkInDate && checkOutDate) {
                    litepickerInstance.setDateRange(checkInDate, checkOutDate);
                    console.log('[Litepicker] Pre-filled dates from URL');
                }
            } catch (error) {
                console.error('[Litepicker] Error parsing URL dates:', error);
            }
        } else {
            // Check if inputs already have values
            const checkInInput = document.querySelector('input[name="mphb_check_in_date"]');
            const checkOutInput = document.querySelector('input[name="mphb_check_out_date"]');

            if (checkInInput?.value && checkOutInput?.value) {
                try {
                    const checkInDate = parseDate(checkInInput.value);
                    const checkOutDate = parseDate(checkOutInput.value);

                    if (checkInDate && checkOutDate) {
                        litepickerInstance.setDateRange(checkInDate, checkOutDate);
                        console.log('[Litepicker] Pre-filled dates from form inputs');
                    }
                } catch (error) {
                    console.error('[Litepicker] Error parsing form dates:', error);
                }
            }
        }
    }

    /**
     * Parse date string in DD/MM/YYYY format
     */
    function parseDate(dateString) {
        if (!dateString) return null;

        const parts = dateString.split('/');
        if (parts.length !== 3) return null;

        const day = parseInt(parts[0], 10);
        const month = parseInt(parts[1], 10) - 1; // Month is 0-indexed
        const year = parseInt(parts[2], 10);

        const date = new Date(year, month, day);

        // Validate the date
        if (isNaN(date.getTime())) return null;

        return date;
    }

    /**
     * Convert date to YYYY-MM-DD format for RoomCloud AJAX
     */
    function toRoomCloudFormat(date) {
        if (!date) return null;

        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');

        return `${year}-${month}-${day}`;
    }

    /**
     * Handle responsive breakpoint changes
     */
    function handleResponsive() {
        if (!litepickerInstance) return;

        const isMobile = window.innerWidth < 768;

        // Update split view based on screen size
        if (litepickerInstance.options) {
            litepickerInstance.options.splitView = !isMobile;
        }
    }

    /**
     * Initialize when DOM is ready
     */
    $(document).ready(function() {
        console.log('[Litepicker] DOM ready, initializing...');

        // Small delay to ensure MPHB scripts have loaded
        setTimeout(initLitepicker, 100);

        // Handle responsive changes
        let resizeTimeout;
        $(window).on('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(handleResponsive, 250);
        });
    });

    /**
     * Re-initialize on AJAX complete (for dynamic content)
     */
    $(document).on('ajaxComplete', function(event, xhr, settings) {
        // Check if this might be MPHB updating search form
        if (settings.url && settings.url.includes('admin-ajax.php')) {
            setTimeout(function() {
                // Only re-init if the current instance is destroyed
                if (!litepickerInstance || !document.querySelector('.litepicker')) {
                    initLitepicker();
                }
            }, 500);
        }
    });

    /**
     * Expose utility functions globally for debugging
     */
    window.shapedLitepicker = {
        getInstance: () => litepickerInstance,
        parseDate: parseDate,
        toRoomCloudFormat: toRoomCloudFormat,
        reinit: initLitepicker
    };

    console.log('[Litepicker] Script loaded');

})(jQuery);
