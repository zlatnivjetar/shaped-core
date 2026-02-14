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

// Localize AJAX URL for the modal scripts
?>
<script>
window.shapedAjax = {
    ajaxUrl: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>'
};
</script>
<!-- Checkout Modals -->
<div id="terms-modal" class="shaped-modal" style="display:none;">
    <div class="shaped-modal-content">
        <span class="shaped-modal-close">&times;</span>
        <h2 class="checkoutmodalheading">Booking Terms & Conditions</h2>
        <div class="shaped-modal-body" id="terms-content">
            <div class="shaped-modal-loading">
                <span class="spinner is-active" style="float: none; margin: 20px auto;"></span>
                <p style="text-align: center; color: #666;">Loading...</p>
            </div>
        </div>
    </div>
</div>

<div id="privacy-modal" class="shaped-modal" style="display:none;">
    <div class="shaped-modal-content">
        <span class="shaped-modal-close">&times;</span>
        <h2 class="checkoutmodalheading">Privacy Policy</h2>
        <div class="shaped-modal-body" id="privacy-content">
            <div class="shaped-modal-loading">
                <span class="spinner is-active" style="float: none; margin: 20px auto;"></span>
                <p style="text-align: center; color: #666;">Loading...</p>
            </div>
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
// Checkout Modal Handlers - Load WordPress pages via AJAX
(function() {
    'use strict';

    // Track which modals have loaded content
    const loadedModals = {};

    // Load page content via WordPress AJAX
    function loadModalContent(modal, pageUrl, pageId) {
        const modalBody = modal.querySelector('.shaped-modal-body');
        const loadingDiv = modal.querySelector('.shaped-modal-loading');

        console.log('[Shaped Modal] Loading content for page ID:', pageId);
        console.log('[Shaped Modal] Page URL:', pageUrl);
        console.log('[Shaped Modal] AJAX URL:', window.shapedAjax?.ajaxUrl);

        // If already loaded, don't load again
        if (loadedModals[pageId]) {
            console.log('[Shaped Modal] Content already loaded for page ID:', pageId);
            return;
        }

        // Check if AJAX URL is available
        if (!window.shapedAjax || !window.shapedAjax.ajaxUrl) {
            console.error('[Shaped Modal] AJAX URL not available');
            modalBody.innerHTML = '<p style="color: #d00; padding: 20px; text-align: center;">Error: AJAX configuration missing. Please refresh the page.</p>';
            return;
        }

        // Check if page ID is valid
        if (!pageId || pageId === '0') {
            console.error('[Shaped Modal] Invalid page ID:', pageId);
            modalBody.innerHTML = '<p style="color: #d00; padding: 20px; text-align: center;">Error: Page not configured. Please visit the <a href="' + pageUrl + '" target="_blank">full page</a>.</p>';
            return;
        }

        // Show loading state
        if (loadingDiv) {
            loadingDiv.style.display = 'block';
        }

        // Use WordPress AJAX to load content
        const formData = new FormData();
        formData.append('action', 'shaped_load_modal_content');
        formData.append('page_id', pageId);

        console.log('[Shaped Modal] Sending AJAX request...');

        fetch(window.shapedAjax.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
            .then(function(response) {
                console.log('[Shaped Modal] Response status:', response.status);
                if (!response.ok) {
                    throw new Error('Network error: ' + response.status);
                }
                return response.json();
            })
            .then(function(data) {
                console.log('[Shaped Modal] Response data:', data);
                if (data.success && data.data.content) {
                    // Hide loading spinner
                    if (loadingDiv) {
                        loadingDiv.style.display = 'none';
                    }

                    // Insert content
                    modalBody.innerHTML = data.data.content;

                    // Mark as loaded
                    loadedModals[pageId] = true;
                    console.log('[Shaped Modal] Content loaded successfully');
                } else {
                    throw new Error(data.data?.message || 'Failed to load content');
                }
            })
            .catch(function(error) {
                console.error('[Shaped Modal] Error loading modal content:', error);
                modalBody.innerHTML = '<p style="color: #d00; padding: 20px; text-align: center;">Error loading content: ' + error.message + '<br>Please try again or visit the <a href="' + pageUrl + '" target="_blank">full page</a>.</p>';
            });
    }

    // Use event delegation on document for modal triggers
    document.addEventListener('click', function(e) {
        // Check if clicked element or parent is a modal trigger
        const trigger = e.target.closest('.modal-trigger');

        if (trigger && trigger.dataset.modal) {
            e.preventDefault();
            e.stopPropagation();

            console.log('[Shaped Modal] Trigger clicked:', trigger);
            console.log('[Shaped Modal] Trigger dataset:', trigger.dataset);

            const modalType = trigger.dataset.modal;
            const modal = document.getElementById(modalType + '-modal');

            console.log('[Shaped Modal] Modal type:', modalType);
            console.log('[Shaped Modal] Modal element:', modal);

            if (modal) {
                modal.style.display = 'block';
                document.body.style.overflow = 'hidden';

                // Get URL and page ID from the trigger's attributes
                const pageUrl = trigger.getAttribute('href');
                const pageId = trigger.dataset.pageId;

                console.log('[Shaped Modal] Extracted pageUrl:', pageUrl);
                console.log('[Shaped Modal] Extracted pageId:', pageId);

                if (pageUrl && pageId) {
                    loadModalContent(modal, pageUrl, pageId);
                } else {
                    console.error('[Shaped Modal] Missing pageUrl or pageId');
                }
            }
        }

        // Handle close button clicks
        if (e.target.classList.contains('shaped-modal-close')) {
            const modal = e.target.closest('.shaped-modal');
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }

        // Close on outside click (clicking the overlay)
        if (e.target.classList.contains('shaped-modal')) {
            e.target.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    });

    // Close on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' || e.key === 'Esc') {
            const modals = document.querySelectorAll('.shaped-modal');
            modals.forEach(function(modal) {
                if (modal.style.display === 'block') {
                    modal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                }
            });
        }
    });
})();
</script>
