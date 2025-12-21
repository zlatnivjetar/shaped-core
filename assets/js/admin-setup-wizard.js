/**
 * Shaped Setup Wizard JavaScript
 *
 * Handles step navigation, form submission, and Stripe validation.
 */

(function($) {
    'use strict';

    var Wizard = {
        /**
         * Initialize wizard
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Next/Save buttons
            $(document).on('click', '.shaped-wizard-next', this.handleNext.bind(this));

            // Skip buttons
            $(document).on('click', '.shaped-wizard-skip', this.handleSkip.bind(this));

            // Stripe key validation on blur
            $(document).on('blur', '#stripe_secret', this.validateStripeKey.bind(this));
        },

        /**
         * Handle next button click
         */
        handleNext: function(e) {
            e.preventDefault();

            var $button = $(e.currentTarget);
            var step = $button.data('step');
            var data = this.collectStepData(step);

            // Disable button and show loading
            $button.prop('disabled', true).text('Saving...');

            $.ajax({
                url: ShapedWizard.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'shaped_wizard_save_step',
                    nonce: ShapedWizard.nonce,
                    step: step,
                    data: data
                },
                success: function(response) {
                    if (response.success) {
                        window.location.href = response.data.next_url;
                    } else {
                        alert(response.data.message || 'Failed to save settings');
                        $button.prop('disabled', false).text('Save & Continue');
                    }
                },
                error: function() {
                    alert('Network error. Please try again.');
                    $button.prop('disabled', false).text('Save & Continue');
                }
            });
        },

        /**
         * Handle skip button click
         */
        handleSkip: function(e) {
            e.preventDefault();

            var $button = $(e.currentTarget);
            var step = $button.data('step');

            $button.prop('disabled', true).text('Skipping...');

            $.ajax({
                url: ShapedWizard.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'shaped_wizard_skip',
                    nonce: ShapedWizard.nonce,
                    step: step
                },
                success: function(response) {
                    if (response.success) {
                        window.location.href = response.data.next_url;
                    } else {
                        $button.prop('disabled', false).text('Skip');
                    }
                },
                error: function() {
                    $button.prop('disabled', false).text('Skip');
                }
            });
        },

        /**
         * Collect form data for current step
         */
        collectStepData: function(step) {
            var data = {};

            switch (step) {
                case 'stripe':
                    data.stripe_secret = $('#stripe_secret').val();
                    data.stripe_webhook = $('#stripe_webhook').val();
                    break;

                case 'payment_mode':
                    data.payment_mode = $('input[name="payment_mode"]:checked').val();
                    data.deposit_percent = $('#deposit_percent').val();
                    data.scheduled_threshold = $('#scheduled_threshold').val();
                    break;

                case 'discounts':
                    data.discounts = {};
                    $('input[name^="discounts["]').each(function() {
                        var name = $(this).attr('name');
                        var match = name.match(/discounts\[([^\]]+)\]/);
                        if (match) {
                            data.discounts[match[1]] = $(this).val();
                        }
                    });
                    break;

                case 'modals':
                    data.modal_pages = {};
                    $('select[name^="modal_pages["]').each(function() {
                        var name = $(this).attr('name');
                        var match = name.match(/modal_pages\[([^\]]+)\]/);
                        if (match) {
                            data.modal_pages[match[1]] = $(this).val();
                        }
                    });
                    break;
            }

            return data;
        },

        /**
         * Validate Stripe secret key
         */
        validateStripeKey: function(e) {
            var $input = $(e.currentTarget);
            var key = $input.val();
            var $validation = $('#stripe-validation');

            // Don't validate if empty or placeholder
            if (!key || key.indexOf('•') !== -1) {
                $validation.hide();
                return;
            }

            // Show validation UI
            $validation
                .removeClass('is-success is-error')
                .show()
                .find('.spinner').addClass('is-active');

            $validation.find('.validation-message').text('Validating...');

            $.ajax({
                url: ShapedWizard.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'shaped_wizard_validate_stripe',
                    nonce: ShapedWizard.nonce,
                    secret: key
                },
                success: function(response) {
                    $validation.find('.spinner').removeClass('is-active');

                    if (response.success) {
                        $validation
                            .addClass('is-success')
                            .find('.validation-message')
                            .html('<span class="dashicons dashicons-yes"></span> ' + response.data.message);

                        if (response.data.mode === 'test') {
                            $validation.find('.validation-message').append(
                                ' <span style="color: #dba617;">(Test Mode)</span>'
                            );
                        }
                    } else {
                        $validation
                            .addClass('is-error')
                            .find('.validation-message')
                            .html('<span class="dashicons dashicons-no"></span> ' + response.data.message);
                    }
                },
                error: function() {
                    $validation
                        .addClass('is-error')
                        .find('.spinner').removeClass('is-active');

                    $validation.find('.validation-message')
                        .html('<span class="dashicons dashicons-no"></span> Network error');
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        Wizard.init();
    });

})(jQuery);
