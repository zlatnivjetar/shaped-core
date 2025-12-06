/**
 * Modal Functionality
 * AJAX modal loader for displaying page content
 *
 * @package Shaped_Core
 */

(function($) {
    'use strict';

    /**
     * Modal Manager
     */
    const ShapedModal = {

        /**
         * Initialize
         */
        init: function() {
            this.createModal();
            this.bindEvents();
        },

        /**
         * Create modal HTML structure
         */
        createModal: function() {
            if ($('#shaped-modal-overlay').length) {
                return; // Already exists
            }

            const modalHTML = `
                <div id="shaped-modal-overlay" class="shaped-modal-overlay" aria-hidden="true" style="display:none;">
                    <div class="shaped-modal-container" role="dialog" aria-modal="true" aria-labelledby="shaped-modal-title">
                        <div class="shaped-modal-header">
                            <h2 id="shaped-modal-title" class="shaped-modal-title"></h2>
                            <button class="shaped-modal-close" aria-label="Close modal">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="shaped-modal-content">
                            <div class="shaped-modal-loading">
                                <span class="spinner is-active"></span>
                                <p>Loading...</p>
                            </div>
                            <div class="shaped-modal-body"></div>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(modalHTML);
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            const self = this;

            // Click on modal link
            $(document).on('click', '.shaped-modal-link', function(e) {
                e.preventDefault();

                const $link = $(this);
                const pageId = $link.data('page-id');
                const pageKey = $link.data('modal-page');
                const title = $link.text() || $link.attr('aria-label') || 'Information';

                if (!pageId) {
                    console.error('Shaped Modal: No page ID specified');
                    return;
                }

                self.openModal(pageId, title);
            });

            // Close button
            $(document).on('click', '.shaped-modal-close', function(e) {
                e.preventDefault();
                self.closeModal();
            });

            // Click outside modal
            $(document).on('click', '.shaped-modal-overlay', function(e) {
                if ($(e.target).hasClass('shaped-modal-overlay')) {
                    self.closeModal();
                }
            });

            // ESC key
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && $('#shaped-modal-overlay').is(':visible')) {
                    self.closeModal();
                }
            });
        },

        /**
         * Open modal
         */
        openModal: function(pageId, title) {
            const $overlay = $('#shaped-modal-overlay');
            const $content = $overlay.find('.shaped-modal-content');
            const $body = $overlay.find('.shaped-modal-body');
            const $title = $overlay.find('.shaped-modal-title');

            // Set title
            $title.text(title);

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

            // Focus on close button
            setTimeout(function() {
                $overlay.find('.shaped-modal-close').focus();
            }, 350);

            // Load content via AJAX
            this.loadContent(pageId);
        },

        /**
         * Close modal
         */
        closeModal: function() {
            const $overlay = $('#shaped-modal-overlay');

            $overlay.removeClass('is-visible');

            setTimeout(function() {
                $overlay.fadeOut(300);
            }, 300);

            // Restore body scroll
            $('body').removeClass('shaped-modal-open');

            // Set ARIA
            $overlay.attr('aria-hidden', 'true');
        },

        /**
         * Load content via AJAX
         */
        loadContent: function(pageId) {
            const $overlay = $('#shaped-modal-overlay');
            const $content = $overlay.find('.shaped-modal-content');
            const $body = $overlay.find('.shaped-modal-body');

            $.ajax({
                url: ShapedConfig?.ajaxUrl || '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: {
                    action: 'shaped_load_modal_content',
                    page_id: pageId,
                    nonce: ShapedConfig?.nonce || ''
                },
                success: function(response) {
                    if (response.success && response.data.content) {
                        $body.html(response.data.content);
                        $content.addClass('is-loaded');
                    } else {
                        $body.html('<p class="error">Failed to load content. Please try again.</p>');
                        $content.addClass('is-loaded');
                    }
                },
                error: function() {
                    $body.html('<p class="error">An error occurred. Please try again later.</p>');
                    $content.addClass('is-loaded');
                }
            });
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        ShapedModal.init();
    });

    // Expose to global scope if needed
    window.ShapedModal = ShapedModal;

})(jQuery);
