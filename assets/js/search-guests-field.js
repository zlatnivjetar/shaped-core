/**
 * Search Guests Field — Reposition & Sync
 *
 * 1. Repositions the guests <select> into .search-input-wrapper when rendered
 *    outside it (non-book pages where output-buffering isn't used).
 * 2. Keeps the guests value in sync between #search-hero and #search-fixed
 *    forms so scrolling between them doesn't lose the selection.
 *
 * The actual form submission is handled natively by the browser (GET) and
 * MPHB's submit handler in mphb.js ensures the correct mphb_adults value
 * reaches the search results URL.
 *
 * @package Shaped_Core
 */
(function () {
  'use strict';

  // ── 1. Reposition guests field if needed ──────────────────────────
  document.querySelectorAll('.mphb_sc_search-form').forEach(function (form) {
    var guests = form.querySelector('.mphb_sc_search-guests');
    var wrapper = form.querySelector('.search-input-wrapper');
    if (guests && wrapper && !wrapper.contains(guests)) {
      wrapper.appendChild(guests);
    }
  });

  // ── 2. Sync guests value between hero ↔ fixed search bars ────────
  var allSelects = document.querySelectorAll('.mphb_sc_search-guests select[name="mphb_adults"]');

  if (allSelects.length > 1) {
    allSelects.forEach(function (select) {
      select.addEventListener('change', function () {
        var newVal = this.value;
        allSelects.forEach(function (other) {
          if (other !== select) {
            other.value = newVal;
          }
        });
      });
    });

    // On load, sync from URL param if present
    var urlAdults = new URLSearchParams(window.location.search).get('mphb_adults');
    if (urlAdults) {
      allSelects.forEach(function (sel) {
        sel.value = urlAdults;
      });
    }
  }
})();
