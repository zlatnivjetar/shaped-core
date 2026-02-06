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

  // ── iOS WebKit viewport recalculation ──
  // On iOS Chrome/Safari, fresh navigations and pull-to-refresh can leave
  // the layout viewport in a stale state, causing position:fixed elements
  // to appear offset from the actual viewport bottom. Scrolling to the
  // *same* position is a no-op — WebKit only recalculates when the scroll
  // position actually changes. A 1px nudge-and-restore forces a full
  // viewport geometry recalculation.
  if (/iPhone|iPad/.test(navigator.userAgent)) {
    var nudge = function () {
      var y = window.scrollY;
      window.scrollTo(window.scrollX, y + 1);
      requestAnimationFrame(function () {
        window.scrollTo(window.scrollX, y);
      });
    };

    // pageshow fires on initial load, pull-to-refresh, and BFCache restore.
    // The 300ms delay lets the pull-to-refresh animation and browser chrome
    // settle before we nudge.
    window.addEventListener('pageshow', function () {
      setTimeout(function () {
        requestAnimationFrame(nudge);
      }, 300);
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
