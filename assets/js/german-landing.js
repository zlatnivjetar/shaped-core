/**
 * German Landing Page Auto-Translate
 *
 * Automatically switches the Google Translate widget to German when the page
 * is loaded with ?lang=de in the URL. This supports visitors from German-speaking
 * markets (DE, AT, CH) who arrive via German Google Ads.
 *
 * Requires the Google Translate widget (doGTranslate) to be present on the page.
 */

document.addEventListener('DOMContentLoaded', function () {
	var params = new URLSearchParams(window.location.search);
	if (params.get('lang') === 'de' && typeof doGTranslate === 'function') {
		doGTranslate('en|de');
	}
});
