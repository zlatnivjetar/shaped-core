/**
 * Shaped Leave Review Form Script
 *
 * Handles rating selection, form submission, and feedback flow.
 *
 * @package Shaped_Core
 * @subpackage ReviewEmail
 */

(function($) {
    'use strict';

    // Rating descriptions
    const ratingDescriptions = {
        1: 'Very Poor',
        2: 'Poor',
        3: 'Below Average',
        4: 'Fair',
        5: 'Average',
        6: 'Above Average',
        7: 'Good',
        8: 'Very Good',
        9: 'Excellent',
        10: 'Outstanding'
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        initRatingButtons();
        initReviewForm();
        initFeedbackForm();
    });

    /**
     * Initialize rating button selection
     */
    function initRatingButtons() {
        const $buttons = $('.rating-btn');
        const $input = $('#shaped-rating-input');
        const $description = $('#rating-description');
        const $submitBtn = $('.shaped-review-form .shaped-submit-btn');

        $buttons.on('click', function() {
            const rating = $(this).data('rating');

            // Update selection
            $buttons.removeClass('selected');
            $(this).addClass('selected');

            // Update hidden input
            $input.val(rating);

            // Update description
            $description.text(ratingDescriptions[rating] || '');

            // Enable submit button
            $submitBtn.prop('disabled', false);
        });
    }

    /**
     * Initialize review form submission
     */
    function initReviewForm() {
        const $form = $('#shaped-review-form');
        const $container = $('.shaped-review-container');
        const $feedbackSection = $('#shaped-feedback-section');
        const $thankYouSection = $('#shaped-thank-you-section');
        const $errorDiv = $('.shaped-form-error');

        $form.on('submit', function(e) {
            e.preventDefault();

            const $submitBtn = $form.find('.shaped-submit-btn');
            const rating = $('#shaped-rating-input').val();

            if (!rating) {
                showError($errorDiv, 'Please select a rating');
                return;
            }

            // Show loading state
            $submitBtn.addClass('loading').prop('disabled', true);
            $errorDiv.hide();

            // Submit via AJAX
            $.ajax({
                url: shapedReview.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'shaped_submit_review',
                    nonce: shapedReview.nonce,
                    booking_id: $container.data('booking-id'),
                    token: $container.data('token'),
                    rating: rating,
                    comment: $('#shaped-review-comment').val(),
                    author_name: $('#shaped-review-name').val()
                },
                success: function(response) {
                    $submitBtn.removeClass('loading').prop('disabled', false);

                    if (response.success) {
                        if (response.data.action === 'feedback') {
                            // Low rating - show feedback form
                            showFeedbackSection($form, $feedbackSection, rating);
                        } else if (response.data.action === 'published') {
                            // High rating - show thank you
                            showThankYou($form, $feedbackSection, $thankYouSection, response.data.message);
                        }
                    } else {
                        showError($errorDiv, response.data.message || 'An error occurred');
                    }
                },
                error: function() {
                    $submitBtn.removeClass('loading').prop('disabled', false);
                    showError($errorDiv, 'Network error. Please try again.');
                }
            });
        });
    }

    /**
     * Initialize feedback form submission
     */
    function initFeedbackForm() {
        const $form = $('#shaped-feedback-form');
        const $container = $('.shaped-review-container');
        const $reviewForm = $('#shaped-review-form');
        const $feedbackSection = $('#shaped-feedback-section');
        const $thankYouSection = $('#shaped-thank-you-section');

        $form.on('submit', function(e) {
            e.preventDefault();

            const $submitBtn = $form.find('.shaped-submit-btn');
            const rating = $('#shaped-original-rating').val();

            // Show loading state
            $submitBtn.addClass('loading').prop('disabled', true);

            // Collect selected issues
            const issues = [];
            $form.find('input[name="issues[]"]:checked').each(function() {
                issues.push($(this).val());
            });

            // Submit via AJAX
            $.ajax({
                url: shapedReview.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'shaped_submit_feedback',
                    nonce: shapedReview.nonce,
                    booking_id: $container.data('booking-id'),
                    token: $container.data('token'),
                    original_rating: rating,
                    issues: issues,
                    details: $('#shaped-feedback-details').val()
                },
                success: function(response) {
                    $submitBtn.removeClass('loading').prop('disabled', false);

                    if (response.success) {
                        showThankYou($reviewForm, $feedbackSection, $thankYouSection, response.data.message);
                    } else {
                        alert(response.data.message || 'An error occurred');
                    }
                },
                error: function() {
                    $submitBtn.removeClass('loading').prop('disabled', false);
                    alert('Network error. Please try again.');
                }
            });
        });
    }

    /**
     * Show feedback section for low ratings
     */
    function showFeedbackSection($form, $feedbackSection, rating) {
        // Store original rating
        $('#shaped-original-rating').val(rating);

        // Hide form, show feedback
        $form.slideUp(300, function() {
            $feedbackSection.slideDown(300);

            // Scroll to feedback section
            $('html, body').animate({
                scrollTop: $feedbackSection.offset().top - 50
            }, 300);
        });
    }

    /**
     * Show thank you message
     */
    function showThankYou($reviewForm, $feedbackSection, $thankYouSection, message) {
        // Update message
        $('#thank-you-message').text(message);

        // Hide forms, show thank you
        $reviewForm.slideUp(300);
        $feedbackSection.slideUp(300, function() {
            $thankYouSection.slideDown(300);

            // Scroll to thank you section
            $('html, body').animate({
                scrollTop: $thankYouSection.offset().top - 100
            }, 300);
        });
    }

    /**
     * Show error message
     */
    function showError($errorDiv, message) {
        $errorDiv.text(message).slideDown(200);

        // Scroll to error
        $('html, body').animate({
            scrollTop: $errorDiv.offset().top - 100
        }, 200);
    }

})(jQuery);
