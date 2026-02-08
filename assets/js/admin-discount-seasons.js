/**
 * Admin Discount Seasons
 *
 * Handles add/remove for recurring season cards and year-specific promo cards,
 * input masking for dd/mm and dd/mm/yyyy formats, and overlap validation.
 */
(function () {
    'use strict';

    // ── Recurring Seasons ──
    var recurringContainer = document.getElementById('shaped-recurring-seasons');
    var addRecurringBtn    = document.getElementById('shaped-add-recurring');
    var recurringTemplate  = document.getElementById('shaped-recurring-template');
    var recurringWarning   = document.getElementById('shaped-recurring-overlap-warning');

    // ── Year-Specific Promos ──
    var overrideContainer  = document.getElementById('shaped-override-seasons');
    var addOverrideBtn     = document.getElementById('shaped-add-override');
    var overrideTemplate   = document.getElementById('shaped-override-template');
    var overrideWarning    = document.getElementById('shaped-override-overlap-warning');

    // ── Helpers ──

    function getNextIndex(container) {
        var cards = container.querySelectorAll('.shaped-date-range-card');
        var max = -1;
        for (var i = 0; i < cards.length; i++) {
            var idx = parseInt(cards[i].getAttribute('data-range-index'), 10);
            if (!isNaN(idx) && idx > max) {
                max = idx;
            }
        }
        return max + 1;
    }

    function reindexCards(container, tier) {
        var cards = container.querySelectorAll('.shaped-date-range-card');
        for (var i = 0; i < cards.length; i++) {
            cards[i].setAttribute('data-range-index', i);
            var inputs = cards[i].querySelectorAll('input[name]');
            for (var j = 0; j < inputs.length; j++) {
                inputs[j].name = inputs[j].name.replace(
                    /\[' + tier + '\]\[\d+\]/,
                    '[' + tier + '][' + i + ']'
                );
                // More robust: replace the second bracket group (index) in the name
                var parts = inputs[j].name.split('[');
                if (parts.length >= 3) {
                    // shaped_discount_seasons[tier][INDEX][field]...
                    parts[2] = i + ']';
                    inputs[j].name = parts.join('[');
                }
            }
        }
    }

    // ── dd/mm auto-slash masking ──

    function maskDDMM(input) {
        input.addEventListener('input', function () {
            var v = this.value.replace(/[^\d/]/g, '');
            // Auto-insert slash after day
            if (v.length === 2 && !v.includes('/')) {
                v = v + '/';
            }
            // Prevent double slash
            if (v.length === 3 && v[2] !== '/') {
                v = v.substring(0, 2) + '/' + v.substring(2);
            }
            this.value = v.substring(0, 5);
        });
    }

    // ── dd/mm/yyyy auto-slash masking ──

    function maskDDMMYYYY(input) {
        input.addEventListener('input', function () {
            var v = this.value.replace(/[^\d/]/g, '');
            // Auto-insert slashes
            if (v.length === 2 && v.indexOf('/') === -1) {
                v = v + '/';
            }
            if (v.length === 5 && v.lastIndexOf('/') === 2) {
                v = v + '/';
            }
            // Prevent malformed slashes
            var parts = v.split('/');
            if (parts.length > 3) {
                v = parts[0] + '/' + parts[1] + '/' + parts.slice(2).join('');
            }
            this.value = v.substring(0, 10);
        });
    }

    function applyMasking(card) {
        var ddmmInputs = card.querySelectorAll('.shaped-date-ddmm');
        for (var i = 0; i < ddmmInputs.length; i++) {
            maskDDMM(ddmmInputs[i]);
        }
        var ddmmyyyyInputs = card.querySelectorAll('.shaped-date-ddmmyyyy');
        for (var i = 0; i < ddmmyyyyInputs.length; i++) {
            maskDDMMYYYY(ddmmyyyyInputs[i]);
        }
    }

    // ── Overlap checking for recurring seasons (dd/mm → mm-dd comparison) ──

    function ddmmToMmdd(ddmm) {
        var parts = ddmm.split('/');
        if (parts.length !== 2) return null;
        return parts[1] + '-' + parts[0];
    }

    function checkRecurringOverlaps() {
        if (!recurringContainer) return false;
        var cards = recurringContainer.querySelectorAll('.shaped-date-range-card');
        var ranges = [];

        for (var i = 0; i < cards.length; i++) {
            var startInput = cards[i].querySelector('input[name*="start_day"]');
            var endInput   = cards[i].querySelector('input[name*="end_day"]');
            if (!startInput || !endInput || !startInput.value || !endInput.value) continue;

            var startMd = ddmmToMmdd(startInput.value);
            var endMd   = ddmmToMmdd(endInput.value);
            if (!startMd || !endMd) continue;

            ranges.push({ start: startMd, end: endMd, card: cards[i] });
        }

        // Remove all existing overlap highlights
        for (var i = 0; i < cards.length; i++) {
            cards[i].classList.remove('has-overlap');
        }

        var hasOverlap = false;

        for (var i = 0; i < ranges.length; i++) {
            for (var j = i + 1; j < ranges.length; j++) {
                if (recurringRangesOverlap(ranges[i].start, ranges[i].end, ranges[j].start, ranges[j].end)) {
                    ranges[i].card.classList.add('has-overlap');
                    ranges[j].card.classList.add('has-overlap');
                    hasOverlap = true;
                }
            }
        }

        if (recurringWarning) {
            recurringWarning.style.display = hasOverlap ? 'inline' : 'none';
        }
        return hasOverlap;
    }

    /**
     * Check if two mm-dd recurring ranges overlap (handles year-wrap).
     * Simple approach: a date mm-dd is "in range" if:
     *   - normal range (start <= end): start <= date <= end
     *   - wrapping range (start > end): date >= start OR date <= end
     * Two ranges overlap if any day from one falls within the other.
     * Simplified: check representative points.
     */
    function recurringRangesOverlap(aStart, aEnd, bStart, bEnd) {
        // Check if a point falls within a range
        function inRange(point, rStart, rEnd) {
            if (rStart <= rEnd) {
                return point >= rStart && point <= rEnd;
            } else {
                return point >= rStart || point <= rEnd;
            }
        }

        // Check if any boundary of B is in A, or any boundary of A is in B
        return inRange(bStart, aStart, aEnd) ||
               inRange(bEnd, aStart, aEnd) ||
               inRange(aStart, bStart, bEnd) ||
               inRange(aEnd, bStart, bEnd);
    }

    // ── Overlap checking for promos (yyyy-mm-dd comparison) ──

    function ddmmyyyyToYmd(ddmmyyyy) {
        var parts = ddmmyyyy.split('/');
        if (parts.length !== 3) return null;
        return parts[2] + '-' + parts[1] + '-' + parts[0];
    }

    function checkOverrideOverlaps() {
        if (!overrideContainer) return false;
        var cards = overrideContainer.querySelectorAll('.shaped-date-range-card');
        var ranges = [];

        for (var i = 0; i < cards.length; i++) {
            var startInput = cards[i].querySelector('input[name*="start_date"]');
            var endInput   = cards[i].querySelector('input[name*="end_date"]');
            if (!startInput || !endInput || !startInput.value || !endInput.value) continue;

            var startYmd = ddmmyyyyToYmd(startInput.value);
            var endYmd   = ddmmyyyyToYmd(endInput.value);

            // Also handle already-stored yyyy-mm-dd format
            if (!startYmd && /^\d{4}-\d{2}-\d{2}$/.test(startInput.value)) {
                startYmd = startInput.value;
            }
            if (!endYmd && /^\d{4}-\d{2}-\d{2}$/.test(endInput.value)) {
                endYmd = endInput.value;
            }
            if (!startYmd || !endYmd) continue;

            ranges.push({ start: startYmd, end: endYmd, card: cards[i] });
        }

        // Remove all existing overlap highlights
        for (var i = 0; i < cards.length; i++) {
            cards[i].classList.remove('has-overlap');
        }

        var hasOverlap = false;

        for (var i = 0; i < ranges.length; i++) {
            for (var j = i + 1; j < ranges.length; j++) {
                if (ranges[i].start <= ranges[j].end && ranges[i].end >= ranges[j].start) {
                    ranges[i].card.classList.add('has-overlap');
                    ranges[j].card.classList.add('has-overlap');
                    hasOverlap = true;
                }
            }
        }

        if (overrideWarning) {
            overrideWarning.style.display = hasOverlap ? 'inline' : 'none';
        }
        return hasOverlap;
    }

    // ── Bind events ──

    function bindRemoveButton(card, container, tier, checkFn) {
        var btn = card.querySelector('.shaped-remove-range');
        if (!btn) return;
        btn.addEventListener('click', function () {
            card.remove();
            reindexCards(container, tier);
            checkFn();
        });
    }

    function bindDateChangeListeners(card, checkFn) {
        var textInputs = card.querySelectorAll('.shaped-date-text');
        for (var i = 0; i < textInputs.length; i++) {
            textInputs[i].addEventListener('change', checkFn);
            textInputs[i].addEventListener('blur', checkFn);
        }
    }

    function addCard(container, template, tier, checkFn) {
        if (!container || !template) return;
        var index = getNextIndex(container);
        var html = template.innerHTML.replace(/__INDEX__/g, index);

        var wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();
        var card = wrapper.firstElementChild;

        container.appendChild(card);
        applyMasking(card);
        bindRemoveButton(card, container, tier, checkFn);
        bindDateChangeListeners(card, checkFn);
    }

    // ── Add buttons ──

    if (addRecurringBtn) {
        addRecurringBtn.addEventListener('click', function () {
            addCard(recurringContainer, recurringTemplate, 'recurring', checkRecurringOverlaps);
        });
    }

    if (addOverrideBtn) {
        addOverrideBtn.addEventListener('click', function () {
            addCard(overrideContainer, overrideTemplate, 'overrides', checkOverrideOverlaps);
        });
    }

    // ── Initialize existing cards ──

    function initExistingCards(container, tier, checkFn) {
        if (!container) return;
        var cards = container.querySelectorAll('.shaped-date-range-card');
        for (var i = 0; i < cards.length; i++) {
            applyMasking(cards[i]);
            bindRemoveButton(cards[i], container, tier, checkFn);
            bindDateChangeListeners(cards[i], checkFn);
        }
        checkFn();
    }

    initExistingCards(recurringContainer, 'recurring', checkRecurringOverlaps);
    initExistingCards(overrideContainer, 'overrides', checkOverrideOverlaps);
})();
