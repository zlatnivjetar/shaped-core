/**
 * Landing Page Room Modal
 *
 * Handles room modal triggers and gallery slider functionality.
 * Works with the existing ShapedModal system.
 *
 * @package Shaped_Core
 */

(function($) {
    'use strict';

    /**
     * Room Modal Handler
     */
    const ShapedRoomModal = {

        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            const self = this;

            // Room modal triggers (image, title, or view details button)
            $(document).on('click', '.shaped-room-modal-trigger', function(e) {
                e.preventDefault();

                const $trigger = $(this);
                const roomId = $trigger.data('room-id');

                if (!roomId) {
                    console.error('Shaped Room Modal: No room ID specified');
                    return;
                }

                // Get dates from URL params or search form (if present)
                const dates = self.getBookingDates();

                self.openRoomModal(roomId, dates);
            });

            // Gallery navigation (delegated for dynamic content)
            $(document).on('click', '.shaped-gallery-prev', function(e) {
                e.preventDefault();
                self.navigateGallery('prev');
            });

            $(document).on('click', '.shaped-gallery-next', function(e) {
                e.preventDefault();
                self.navigateGallery('next');
            });

            $(document).on('click', '.shaped-gallery-dot', function(e) {
                e.preventDefault();
                const index = $(this).data('index');
                self.goToSlide(index);
            });

            // Keyboard navigation for gallery
            $(document).on('keydown', function(e) {
                if (!$('#shaped-modal-overlay').is(':visible')) {
                    return;
                }

                if (e.key === 'ArrowLeft') {
                    self.navigateGallery('prev');
                } else if (e.key === 'ArrowRight') {
                    self.navigateGallery('next');
                }
            });

            // Touch/swipe support for gallery
            this.initTouchSupport();
        },

        /**
         * Get booking dates from URL or search form
         */
        getBookingDates: function() {
            const urlParams = new URLSearchParams(window.location.search);

            // Try URL params first
            let checkIn = urlParams.get('mphb_check_in_date') || '';
            let checkOut = urlParams.get('mphb_check_out_date') || '';
            let adults = urlParams.get('mphb_adults') || '1';

            // Try to get from search form on page
            const $searchForm = $('.mphb-search-form, .mphb-availability-search-form').first();
            if ($searchForm.length) {
                const formCheckIn = $searchForm.find('[name="mphb_check_in_date"]').val();
                const formCheckOut = $searchForm.find('[name="mphb_check_out_date"]').val();
                const formAdults = $searchForm.find('[name="mphb_adults"]').val();

                if (formCheckIn) checkIn = formCheckIn;
                if (formCheckOut) checkOut = formCheckOut;
                if (formAdults) adults = formAdults;
            }

            return {
                check_in: checkIn,
                check_out: checkOut,
                adults: adults
            };
        },

        /**
         * Open room modal
         */
        openRoomModal: function(roomId, dates) {
            const self = this;
            const $overlay = $('#shaped-modal-overlay');
            const $content = $overlay.find('.shaped-modal-content');
            const $body = $overlay.find('.shaped-modal-body');
            const $title = $overlay.find('.shaped-modal-title');

            // Get room title from card
            const $card = $(`.shaped-landing-room-card[data-room-id="${roomId}"]`);
            const roomTitle = $card.data('room-title') || 'Room Details';

            // Set title
            $title.text(roomTitle);

            // Reset content
            $content.removeClass('is-loaded');
            $body.empty();

            // Show modal
            $overlay.fadeIn(300, function() {
                $overlay.addClass('is-visible');
            });

            // Prevent body scroll
            $('body').addClass('shaped-modal-open');

            // Set ARIA
            $overlay.attr('aria-hidden', 'false');

            // Focus management
            setTimeout(function() {
                $overlay.find('.shaped-modal-close').focus();
            }, 350);

            // Load room content via AJAX
            this.loadRoomContent(roomId, dates);
        },

        /**
         * Load room content via AJAX
         */
        loadRoomContent: function(roomId, dates) {
            const self = this;
            const $overlay = $('#shaped-modal-overlay');
            const $content = $overlay.find('.shaped-modal-content');
            const $body = $overlay.find('.shaped-modal-body');

            const config = window.ShapedLandingConfig || {};

            $.ajax({
                url: config.ajaxUrl || '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: {
                    action: 'shaped_load_room_modal',
                    room_id: roomId,
                    check_in: dates.check_in || '',
                    check_out: dates.check_out || '',
                    adults: dates.adults || 1,
                    nonce: config.nonce || ''
                },
                success: function(response) {
                    if (response.success && response.data.content) {
                        $body.html(response.data.content);
                        $content.addClass('is-loaded');

                        // Initialize gallery after content loads
                        self.initGallery();
                    } else {
                        $body.html('<p class="error">Failed to load room details. Please try again.</p>');
                        $content.addClass('is-loaded');
                    }
                },
                error: function() {
                    $body.html('<p class="error">An error occurred. Please try again later.</p>');
                    $content.addClass('is-loaded');
                }
            });
        },

        /**
         * Initialize gallery after content loads
         */
        initGallery: function() {
            const $slider = $('.shaped-gallery-slider');
            if (!$slider.length) return;

            // Set initial state
            $slider.data('current', 0);
            this.updateGalleryUI(0);
        },

        /**
         * Navigate gallery
         */
        navigateGallery: function(direction) {
            const $slider = $('.shaped-gallery-slider');
            if (!$slider.length) return;

            const $slides = $slider.find('.shaped-gallery-slide');
            const total = $slides.length;
            let current = parseInt($slider.data('current') || 0);

            if (direction === 'prev') {
                current = current > 0 ? current - 1 : total - 1;
            } else {
                current = current < total - 1 ? current + 1 : 0;
            }

            this.goToSlide(current);
        },

        /**
         * Go to specific slide
         */
        goToSlide: function(index) {
            const $slider = $('.shaped-gallery-slider');
            if (!$slider.length) return;

            const $slides = $slider.find('.shaped-gallery-slide');
            const total = $slides.length;

            // Clamp index
            index = Math.max(0, Math.min(index, total - 1));

            // Update slider position
            $slider.data('current', index);

            // Update track position (CSS transform)
            const $track = $slider.find('.shaped-gallery-track');
            $track.css('transform', `translateX(-${index * 100}%)`);

            // Update UI
            this.updateGalleryUI(index);
        },

        /**
         * Update gallery UI (dots, counter, active states)
         */
        updateGalleryUI: function(index) {
            const $slider = $('.shaped-gallery-slider');

            // Update active slide
            $slider.find('.shaped-gallery-slide').removeClass('is-active');
            $slider.find(`.shaped-gallery-slide[data-index="${index}"]`).addClass('is-active');

            // Update dots
            $slider.find('.shaped-gallery-dot').removeClass('is-active');
            $slider.find(`.shaped-gallery-dot[data-index="${index}"]`).addClass('is-active');

            // Update counter
            $slider.find('.shaped-gallery-current').text(index + 1);
        },

        /**
         * Initialize touch/swipe support
         */
        initTouchSupport: function() {
            const self = this;
            let touchStartX = 0;
            let touchEndX = 0;

            $(document).on('touchstart', '.shaped-gallery-slider', function(e) {
                touchStartX = e.changedTouches[0].screenX;
            });

            $(document).on('touchend', '.shaped-gallery-slider', function(e) {
                touchEndX = e.changedTouches[0].screenX;
                self.handleSwipe(touchStartX, touchEndX);
            });
        },

        /**
         * Handle swipe gesture
         */
        handleSwipe: function(startX, endX) {
            const threshold = 50; // Minimum swipe distance
            const diff = startX - endX;

            if (Math.abs(diff) < threshold) return;

            if (diff > 0) {
                // Swipe left - next slide
                this.navigateGallery('next');
            } else {
                // Swipe right - previous slide
                this.navigateGallery('prev');
            }
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        ShapedRoomModal.init();
    });

    // Expose to global scope
    window.ShapedRoomModal = ShapedRoomModal;

})(jQuery);
