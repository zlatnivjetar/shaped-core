/**
 * Search Form Visibility Toggle
 *
 * Uses IntersectionObserver to show/hide the fixed search bar (#search-fixed)
 * based on whether the hero search form (#search-hero) is in the viewport.
 *
 * #search-fixed starts hidden via CSS and is revealed only after #search-hero
 * scrolls out of view.
 *
 * @package Shaped_Core
 */
(function () {
  var hero = document.getElementById('search-hero');
  var fixed = document.getElementById('search-fixed');

  if (!hero || !fixed) {
    return;
  }

  var observer = new IntersectionObserver(
    function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          fixed.classList.remove('is-visible');
        } else {
          fixed.classList.add('is-visible');
        }
      });
    },
    { threshold: 0 }
  );

  observer.observe(hero);
})();
