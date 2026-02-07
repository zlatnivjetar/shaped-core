/**
 * Room Detail Modal
 *
 * Opens a full-detail modal when a search-result room card is clicked.
 * Content is pre-rendered server-side inside a <template> tag per card.
 * Includes a vanilla JS gallery slider with keyboard + touch support.
 *
 * @package Shaped_Core
 */
(function () {
    'use strict';

    /* =====================================================================
     * STATE
     * =================================================================== */
    let overlay = null;
    let currentSlide = 0;
    let totalSlides = 0;
    let touchStartX = 0;
    let touchStartY = 0;
    const SWIPE_THRESHOLD = 50;

    /* =====================================================================
     * INIT
     * =================================================================== */
    document.addEventListener('DOMContentLoaded', function () {
        createOverlay();
        bindCardClicks();
    });

    /* =====================================================================
     * OVERLAY (singleton — created once, content swapped per room)
     * =================================================================== */
    function createOverlay() {
        if (document.getElementById('shaped-room-modal-overlay')) {
            overlay = document.getElementById('shaped-room-modal-overlay');
            return;
        }

        overlay = document.createElement('div');
        overlay.id = 'shaped-room-modal-overlay';
        overlay.className = 'shaped-room-modal-overlay';
        overlay.setAttribute('aria-hidden', 'true');
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.innerHTML = `
            <div class="shaped-room-modal-container">
                <button class="shaped-room-modal-close" aria-label="Close room details">
                    <i class="ph ph-x" aria-hidden="true"></i>
                </button>
                <div class="shaped-room-modal-body"></div>
            </div>
        `;
        document.body.appendChild(overlay);

        // Close button
        overlay.querySelector('.shaped-room-modal-close').addEventListener('click', closeModal);

        // Click outside content
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) {
                closeModal();
            }
        });

        // ESC key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && overlay.classList.contains('is-open')) {
                closeModal();
            }
        });
    }

    /* =====================================================================
     * CARD CLICK BINDING
     * =================================================================== */
    function bindCardClicks() {
        var cards = document.querySelectorAll('.template-search .mphb-room-type');
        cards.forEach(function (card) {
            // Make entire card clickable
            card.style.cursor = 'pointer';

            card.addEventListener('click', function (e) {
                // Don't intercept clicks on the CTA form/button (let it POST to checkout)
                if (e.target.closest('.mphb-reserve-room-section form') ||
                    e.target.closest('.mphb-reserve-room-section a')) {
                    return;
                }

                e.preventDefault();
                e.stopPropagation();

                var tmpl = card.querySelector('template[data-room-modal]');
                if (!tmpl) return;

                openModal(tmpl);
            });
        });
    }

    /* =====================================================================
     * OPEN / CLOSE
     * =================================================================== */
    function openModal(template) {
        var body = overlay.querySelector('.shaped-room-modal-body');
        body.innerHTML = '';

        // Clone template content into modal
        var content = template.content.cloneNode(true);
        body.appendChild(content);

        // Set ARIA label from room title
        var title = body.querySelector('.shaped-room-modal__title');
        if (title) {
            overlay.setAttribute('aria-labelledby', 'shaped-room-modal-active-title');
            title.id = 'shaped-room-modal-active-title';
        }

        // Init gallery slider
        initGallery(body);

        // Show overlay
        overlay.classList.add('is-open');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.classList.add('shaped-room-modal-open');

        // Focus close button
        setTimeout(function () {
            overlay.querySelector('.shaped-room-modal-close').focus();
        }, 100);
    }

    function closeModal() {
        overlay.classList.remove('is-open');
        overlay.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('shaped-room-modal-open');

        // Clean up gallery event listeners
        destroyGallery();

        // Clear content after transition
        setTimeout(function () {
            overlay.querySelector('.shaped-room-modal-body').innerHTML = '';
        }, 300);
    }

    /* =====================================================================
     * GALLERY SLIDER (vanilla, no dependencies)
     * =================================================================== */
    var galleryEl = null;
    var galleryKeyHandler = null;

    function initGallery(container) {
        galleryEl = container.querySelector('.shaped-room-modal__gallery');
        if (!galleryEl) return;

        totalSlides = parseInt(galleryEl.dataset.total, 10) || 0;
        if (totalSlides <= 1) return;

        currentSlide = 0;
        updateSlide();

        // Nav buttons
        var prevBtn = galleryEl.querySelector('.shaped-room-modal__nav--prev');
        var nextBtn = galleryEl.querySelector('.shaped-room-modal__nav--next');

        if (prevBtn) prevBtn.addEventListener('click', handlePrev);
        if (nextBtn) nextBtn.addEventListener('click', handleNext);

        // Keyboard
        galleryKeyHandler = function (e) {
            if (!overlay.classList.contains('is-open')) return;
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                prevSlide();
            }
            if (e.key === 'ArrowRight') {
                e.preventDefault();
                nextSlide();
            }
        };
        document.addEventListener('keydown', galleryKeyHandler);

        // Touch / swipe
        galleryEl.addEventListener('touchstart', handleTouchStart, { passive: true });
        galleryEl.addEventListener('touchend', handleTouchEnd, { passive: true });
    }

    function destroyGallery() {
        if (galleryKeyHandler) {
            document.removeEventListener('keydown', galleryKeyHandler);
            galleryKeyHandler = null;
        }
        galleryEl = null;
        currentSlide = 0;
        totalSlides = 0;
    }

    function handlePrev(e) {
        e.stopPropagation();
        prevSlide();
    }

    function handleNext(e) {
        e.stopPropagation();
        nextSlide();
    }

    function prevSlide() {
        currentSlide = (currentSlide - 1 + totalSlides) % totalSlides;
        updateSlide();
    }

    function nextSlide() {
        currentSlide = (currentSlide + 1) % totalSlides;
        updateSlide();
    }

    function updateSlide() {
        if (!galleryEl) return;

        var slides = galleryEl.querySelectorAll('.shaped-room-modal__slide');
        slides.forEach(function (slide, i) {
            slide.classList.toggle('is-active', i === currentSlide);
        });

        var counter = galleryEl.querySelector('.shaped-room-modal__counter-current');
        if (counter) {
            counter.textContent = currentSlide + 1;
        }
    }

    /* ─── Touch gestures ─── */
    function handleTouchStart(e) {
        touchStartX = e.changedTouches[0].screenX;
        touchStartY = e.changedTouches[0].screenY;
    }

    function handleTouchEnd(e) {
        var dx = e.changedTouches[0].screenX - touchStartX;
        var dy = e.changedTouches[0].screenY - touchStartY;

        // Only register horizontal swipes (ignore vertical scrolling)
        if (Math.abs(dx) > SWIPE_THRESHOLD && Math.abs(dx) > Math.abs(dy)) {
            if (dx < 0) {
                nextSlide();
            } else {
                prevSlide();
            }
        }
    }
})();
