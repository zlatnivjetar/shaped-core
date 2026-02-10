/**
 * Search Guests Field — Sync between forms
 *
 * Keeps the guests (mphb_adults) value in sync between #search-hero and
 * #search-fixed forms so scrolling between them doesn't lose the selection.
 *
 * The guests field itself is rendered natively by MPHB's search-form.php
 * inside .search-input-wrapper.
 *
 * @package Shaped_Core
 */
(function () {
  'use strict';

  var allSelects = document.querySelectorAll('select[name="mphb_adults"]');

  if (allSelects.length < 2) return;

  // Sync on change
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

  // On page load, sync from URL param if present
  var urlAdults = new URLSearchParams(window.location.search).get('mphb_adults');
  if (urlAdults) {
    allSelects.forEach(function (sel) {
      sel.value = urlAdults;
    });
  }
})();
