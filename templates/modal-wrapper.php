<?php
/**
 * Modal Wrapper Template
 * AJAX modal container for displaying page content
 *
 * @package Shaped_Core
 */

if (!defined('ABSPATH')) {
    exit;
}

// This template is loaded via AJAX
// The content will be populated dynamically
?>
<div id="shaped-modal-overlay" class="shaped-modal-overlay" style="display:none;">
    <div class="shaped-modal-container">
        <div class="shaped-modal-header">
            <h2 class="shaped-modal-title"></h2>
            <button class="shaped-modal-close" aria-label="<?php esc_attr_e('Close modal', 'shaped'); ?>">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <div class="shaped-modal-content">
            <div class="shaped-modal-loading">
                <span class="spinner is-active"></span>
                <p><?php _e('Loading...', 'shaped'); ?></p>
            </div>
            <div class="shaped-modal-body"></div>
        </div>
    </div>
</div>
