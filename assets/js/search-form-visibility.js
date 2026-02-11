/**
 * Search Form Visibility Toggle
 *
 * Uses IntersectionObserver to show/hide the fixed search bar (#search-fixed)
 * based on whether the hero search form (#search-hero) is in the viewport.
 *
 * #search-fixed starts hidden via CSS and is revealed only after #search-hero
 * scrolls out of view.
 *
 * Chrome iOS fix: The element is completely removed from the render tree for
 * 2 seconds on first load to let the browser chrome settle, then faded in.
 * This avoids a known viewport calculation bug in Chrome iOS (CriOS).
 *
 * @package Shaped_Core
 */
(function () {
  var hero = document.getElementById('search-hero');
  var fixed = document.getElementById('search-fixed');

  if (!hero || !fixed) {
    return;
  }

  var isChromeiOS = /CriOS/.test(navigator.userAgent);

  if (isChromeiOS) {
    // Completely remove from render tree so Chrome iOS cannot
    // calculate an incorrect position during toolbar settling.
    fixed.classList.add('chrome-ios-deferred');

    setTimeout(function () {
      // Re-enter the render tree
      fixed.classList.remove('chrome-ios-deferred');

      // Force the browser to recalculate layout at the correct viewport
      fixed.getBoundingClientRect();

      // Subtle fade-in (CSS animation on opacity only, won't affect positioning)
      fixed.classList.add('chrome-ios-reveal');

      fixed.addEventListener('animationend', function onRevealEnd() {
        fixed.removeEventListener('animationend', onRevealEnd);
        fixed.classList.remove('chrome-ios-reveal');
      });
    }, 2000);
  }

  // IntersectionObserver: toggle .is-visible based on hero visibility
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
