// ====== LANGUAGE SWITCHER FADE ON SCROLL (UTILITY) ======
document.addEventListener("DOMContentLoaded", function () {
    const langSwitcher = document.querySelector("#gt_float_wrapper");
    if (!langSwitcher) return;

    let isHidden = false;
    let scrollTimeout = null;
    const DEBOUNCE_DELAY = 100; // milliseconds

    function handleScroll() {
        const scrollTop = window.scrollY;
        const halfVH = window.innerHeight * 0.5;

        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(() => {
            if (scrollTop > halfVH && !isHidden) {
                langSwitcher.classList.add("hidden-lang");
                isHidden = true;
            } else if (scrollTop === 0 && isHidden) {
                langSwitcher.classList.remove("hidden-lang");
                isHidden = false;
            }
        }, DEBOUNCE_DELAY);
    }

    // Use requestAnimationFrame for smooth performance
    let ticking = false;
    window.addEventListener("scroll", function () {
        if (!ticking) {
            window.requestAnimationFrame(function () {
                handleScroll();
                ticking = false;
            });
            ticking = true;
        }
    });
});
