/**
 * German Landing Page Auto-Translate
 *
 * Automatically switches the Google Translate widget to German when the page
 * is loaded with ?lang=de in the URL. This supports visitors from German-speaking
 * markets (DE, AT, CH) who arrive via German Google Ads.
 *
 * Google Translate loads asynchronously, so doGTranslate may not be available
 * at DOMContentLoaded. We poll for it with a short interval and a timeout cap.
 */

(function () {
	var params = new URLSearchParams(window.location.search);
	if (params.get('lang') !== 'de') {
		return;
	}

	var attempts = 0;
	var maxAttempts = 40; // 40 × 250 ms = 10 s timeout

	var interval = setInterval(function () {
		attempts++;

		if (typeof doGTranslate === 'function') {
			clearInterval(interval);
			doGTranslate('en|de');
			return;
		}

		if (attempts >= maxAttempts) {
			clearInterval(interval);
		}
	}, 250);
})();
