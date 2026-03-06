/**
 * German Landing Page Auto-Translate
 *
 * Automatically switches GTranslate to German when the page is loaded with
 * ?lang=de in the URL. Supports visitors arriving via German Google Ads.
 *
 * Strategy: set the `googtrans` cookie that GTranslate reads at page load,
 * then reload once. On the reloaded page the cookie is already set so we
 * skip the reload — no async timing issues and works in incognito.
 */

(function () {
	var params = new URLSearchParams(window.location.search);
	if (params.get('lang') !== 'de') {
		return;
	}

	// Read existing googtrans cookie value
	var existing = document.cookie.split('; ').reduce(function (acc, pair) {
		var parts = pair.split('=');
		return parts[0] === 'googtrans' ? decodeURIComponent(parts[1]) : acc;
	}, '');

	// Cookie is already set to German — GTranslate handles it from here
	if (existing === '/en/de') {
		return;
	}

	// Set cookie for both the current hostname and the root domain,
	// then reload so GTranslate picks it up at page initialisation.
	document.cookie = 'googtrans=/en/de; path=/';
	document.cookie = 'googtrans=/en/de; path=/; domain=' + window.location.hostname;
	window.location.reload();
})();
