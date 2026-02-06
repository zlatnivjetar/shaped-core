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

  // ── iOS WebKit viewport fix ──
  // On iOS Chrome/Safari, pull-to-refresh and fresh tab navigations can
  // leave the layout viewport in a stale state. scrollTo to the current
  // position is a no-op visually but forces WebKit to recalculate layout,
  // which fixes fixed-position elements that appear offset from the
  // actual viewport bottom.
  if (/iPhone|iPad/.test(navigator.userAgent)) {
    window.addEventListener('pageshow', function () {
      requestAnimationFrame(function () {
        window.scrollTo(window.scrollX, window.scrollY);
      });
    });
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
