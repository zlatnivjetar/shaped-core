/**
 * Search Guests Field — Submit & Sync
 *
 * 1. Repositions the guests <select> into .search-input-wrapper when rendered
 *    outside it (non-book pages where output-buffering isn't used).
 * 2. Intercepts MPHB search form navigation so `mphb_adults` always travels
 *    in the URL (MPHB's JS only forwards its own native fields).
 * 3. Keeps the guests value in sync between #search-hero and #search-fixed
 *    forms so scrolling between them doesn't lose the selection.
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

  // ── 2. Ensure mphb_adults reaches the search results URL ─────────
  //
  // MPHB's JS navigates to the results page by reading only its own
  // fields and building the URL.  We observe URL changes (since MPHB
  // may use window.location or a link click) and also hook the submit
  // event as a belt-and-suspenders approach.

  /**
   * Read the current guests value from any visible search form.
   * Prefers the form inside the viewport (#search-hero when visible,
   * else #search-fixed, else whichever is first).
   */
  function getGuestsValue() {
    // Try hero first (primary), then fixed, then any form
    var selectors = [
      '#search-hero .mphb_sc_search-guests select[name="mphb_adults"]',
      '#search-fixed .mphb_sc_search-guests select[name="mphb_adults"]',
      '.mphb_sc_search-guests select[name="mphb_adults"]'
    ];

    for (var i = 0; i < selectors.length; i++) {
      var el = document.querySelector(selectors[i]);
      if (el) return el.value;
    }
    return null;
  }

  // Hook every search form's submit event
  document.querySelectorAll('.mphb_sc_search-form').forEach(function (form) {
    form.addEventListener('submit', function () {
      // The select is inside the form with name="mphb_adults",
      // so a normal GET submit includes it. But if MPHB overrides
      // the submit, we also store the value for the URL observer below.
      var val = form.querySelector('select[name="mphb_adults"]');
      if (val) {
        sessionStorage.setItem('shaped_guests_pending', val.value);
      }
    });
  });

  // Watch for MPHB's JS-driven navigation (it changes window.location)
  // by monitoring clicks on the submit button and patching the URL after.
  document.querySelectorAll('.mphb_sc_search-submit-button-wrapper input[type="submit"]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var form = btn.closest('.mphb_sc_search-form');
      var select = form ? form.querySelector('select[name="mphb_adults"]') : null;
      if (select) {
        sessionStorage.setItem('shaped_guests_pending', select.value);
      }

      // Give MPHB's JS time to set window.location, then patch it
      setTimeout(patchUrlWithGuests, 50);
      setTimeout(patchUrlWithGuests, 200);
      setTimeout(patchUrlWithGuests, 500);
    });
  });

  function patchUrlWithGuests() {
    var pending = sessionStorage.getItem('shaped_guests_pending');
    if (!pending) return;

    var url = new URL(window.location.href);
    if (!url.searchParams.has('mphb_adults') && url.searchParams.has('mphb_check_in_date')) {
      url.searchParams.set('mphb_adults', pending);
      sessionStorage.removeItem('shaped_guests_pending');
      window.location.replace(url.toString());
    }
  }

  // On page load: if we arrived at search results without mphb_adults
  // but have a pending value, patch the URL.
  var params = new URLSearchParams(window.location.search);
  if (!params.has('mphb_adults') && params.has('mphb_check_in_date')) {
    var pending = sessionStorage.getItem('shaped_guests_pending');
    if (pending) {
      patchUrlWithGuests();
    }
  }

  // ── 3. Sync guests value between hero ↔ fixed search bars ────────
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
    var urlAdults = params.get('mphb_adults');
    if (urlAdults) {
      allSelects.forEach(function (sel) {
        sel.value = urlAdults;
      });
    }
  }
})();
