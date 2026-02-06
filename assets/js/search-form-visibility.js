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

  // ── IntersectionObserver with rootMargin buffer ──
  // A negative bottom margin (-48px) means the hero must be at least 48px
  // into the viewport before it counts as "intersecting". This prevents the
  // rapid show/hide toggling that causes the glitch when slowly scrolling
  // back up toward the hero.
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
    { threshold: 0, rootMargin: '0px 0px -48px 0px' }
  );

  observer.observe(hero);
})();
