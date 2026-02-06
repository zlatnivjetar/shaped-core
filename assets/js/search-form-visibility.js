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

  // ── iOS Chrome first-load viewport workaround ──
  // On a fresh iOS Chrome tab the visual viewport may not match the layout
  // viewport until after the first resize/scroll. Force a reflow once the
  // viewport settles so the parent fixed-position container recalculates.
  if (/iPhone|iPad/.test(navigator.userAgent) && window.visualViewport) {
    var settled = false;
    var onViewportResize = function () {
      if (!settled) {
        settled = true;
        window.visualViewport.removeEventListener('resize', onViewportResize);
        // Force a layout recalc on the fixed container
        var parent = fixed.parentElement;
        if (parent) {
          parent.style.display = 'none';
          // Read offsetHeight to flush the style change synchronously
          void parent.offsetHeight;
          parent.style.display = '';
        }
      }
    };
    window.visualViewport.addEventListener('resize', onViewportResize);

    // Clean up if it never fires (desktop UA spoofing, etc.)
    setTimeout(function () {
      window.visualViewport.removeEventListener('resize', onViewportResize);
    }, 5000);
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
