<?php
/**
 * Checkout Modal Templates
 * Terms & Conditions and Privacy Policy modals for checkout page
 *
 * Legal content is loaded dynamically from clients/{client}/legal/ directory.
 * This allows each client to have their own Terms & Privacy content.
 *
 * @package Shaped_Core
 */

if (!defined('ABSPATH')) {
    exit;
}

// Only load on checkout pages
if (!is_page(['checkout', 'book', 'booking'])) {
    global $post;
    if (!$post || !has_shortcode($post->post_content, 'mphb_checkout')) {
        return;
    }
}

?>
<!-- Checkout Modals -->
<div id="terms-modal" class="shaped-modal" style="display:none;">
    <div class="shaped-modal-content">
        <span class="shaped-modal-close">&times;</span>
        <h2 class="checkoutmodalheading">Booking Terms & Conditions</h2>
        <div class="shaped-modal-body" id="terms-content">
            <?php shaped_render_legal_content('terms'); ?>
        </div>
    </div>
</div>

<div id="privacy-modal" class="shaped-modal" style="display:none;">
    <div class="shaped-modal-content">
        <span class="shaped-modal-close">&times;</span>
        <h2 class="checkoutmodalheading">Privacy Policy</h2>
        <div class="shaped-modal-body" id="privacy-content">
            <?php shaped_render_legal_content('privacy'); ?>
        </div>
    </div>
</div>

<style>
/* Checkout Modal Styling */
.shaped-modal {
    display: none;
    position: fixed;
    z-index: 999999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.75);
}

.shaped-modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    padding: 0;
    border-radius: 8px;
    width: 90%;
    max-width: 800px;
    max-height: 85vh;
    display: flex;
    flex-direction: column;
}

.shaped-modal-close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    padding: 0 10px;
    line-height: 1;
}

.shaped-modal-close:hover,
.shaped-modal-close:focus {
    color: #000;
}

.checkoutmodalheading {
    padding: 20px 30px;
    margin: 0;
    border-bottom: 1px solid #e5e5e5;
}

.shaped-modal-body {
    padding: 30px;
    overflow-y: auto;
    flex: 1;
}

.shaped-modal-body h2 {
    margin-top: 1.5em;
    margin-bottom: 0.5em;
}

.shaped-modal-body h2:first-child {
    margin-top: 0;
}

.shaped-modal-body ul {
    margin-bottom: 1em;
}

@media (max-width: 768px) {
    .shaped-modal-content {
        width: 95%;
        margin: 10px auto;
        max-height: 95vh;
    }

    .checkoutmodalheading {
        padding: 15px 20px;
        font-size: 20px;
    }

    .shaped-modal-body {
        padding: 20px;
    }
}
</style>

<script>
// Checkout Modal Handlers
document.addEventListener('DOMContentLoaded', function() {
    const modalTriggers = document.querySelectorAll('.modal-trigger');
    const modals = {
        terms: document.getElementById('terms-modal'),
        privacy: document.getElementById('privacy-modal')
    };

    // Prevent default link behavior and open modal
    modalTriggers.forEach(trigger => {
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            const modalType = this.dataset.modal;
            const modal = modals[modalType];

            if (modal) {
                modal.style.display = 'block';
                document.body.style.overflow = 'hidden';
            }
        });
    });

    // Close modal functionality
    document.querySelectorAll('.shaped-modal-close').forEach(closeBtn => {
        closeBtn.addEventListener('click', function() {
            this.closest('.shaped-modal').style.display = 'none';
            document.body.style.overflow = 'auto';
        });
    });

    // Close on outside click
    window.addEventListener('click', function(e) {
        if (e.target.classList.contains('shaped-modal')) {
            e.target.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    });

    // Close on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.shaped-modal').forEach(modal => {
                if (modal.style.display === 'block') {
                    modal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                }
            });
        }
    });
});
</script>
