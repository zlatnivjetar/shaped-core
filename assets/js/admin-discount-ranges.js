/**
 * Admin Discount Date Ranges
 *
 * Handles add/remove date range cards and overlap validation
 * on the Shaped Pricing admin page.
 */
(function () {
    'use strict';

    var container = document.getElementById('shaped-discount-ranges');
    var addBtn    = document.getElementById('shaped-add-range');
    var template  = document.getElementById('shaped-range-template');
    var warning   = document.getElementById('shaped-overlap-warning');

    if (!container || !addBtn || !template) {
        return;
    }

    /**
     * Get the next available index for a new range card.
     */
    function getNextIndex() {
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

    /**
     * Re-index all range cards sequentially (0, 1, 2, ...).
     * Updates data attributes and all input name attributes.
     */
    function reindexCards() {
        var cards = container.querySelectorAll('.shaped-date-range-card');
        for (var i = 0; i < cards.length; i++) {
            var oldIndex = cards[i].getAttribute('data-range-index');
            cards[i].setAttribute('data-range-index', i);

            var inputs = cards[i].querySelectorAll('input[name]');
            for (var j = 0; j < inputs.length; j++) {
                inputs[j].name = inputs[j].name.replace(
                    /\[\d+\]/,
                    '[' + i + ']'
                );
            }
        }
    }

    /**
     * Check for overlapping date ranges and show/hide warning.
     * Returns true if overlaps are found.
     */
    function checkOverlaps() {
        var cards = container.querySelectorAll('.shaped-date-range-card');
        var ranges = [];

        for (var i = 0; i < cards.length; i++) {
            var startInput = cards[i].querySelector('input[type="date"][name*="start_date"]');
            var endInput   = cards[i].querySelector('input[type="date"][name*="end_date"]');

            if (!startInput || !endInput || !startInput.value || !endInput.value) {
                continue;
            }

            ranges.push({
                start: startInput.value,
                end: endInput.value,
                card: cards[i]
            });
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

        if (warning) {
            warning.style.display = hasOverlap ? 'inline' : 'none';
        }

        return hasOverlap;
    }

    /**
     * Add a new date range card from the template.
     */
    addBtn.addEventListener('click', function () {
        var index = getNextIndex();
        var html = template.innerHTML.replace(/__INDEX__/g, index);

        var wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();
        var card = wrapper.firstElementChild;

        container.appendChild(card);
        bindRemoveButton(card);
        bindDateChangeListeners(card);
    });

    /**
     * Bind the remove button on a card.
     */
    function bindRemoveButton(card) {
        var btn = card.querySelector('.shaped-remove-range');
        if (!btn) return;

        btn.addEventListener('click', function () {
            card.remove();
            reindexCards();
            checkOverlaps();
        });
    }

    /**
     * Bind date input change listeners for overlap checking.
     */
    function bindDateChangeListeners(card) {
        var dateInputs = card.querySelectorAll('input[type="date"]');
        for (var i = 0; i < dateInputs.length; i++) {
            dateInputs[i].addEventListener('change', checkOverlaps);
        }
    }

    // Initialize existing cards
    var existingCards = container.querySelectorAll('.shaped-date-range-card');
    for (var i = 0; i < existingCards.length; i++) {
        bindRemoveButton(existingCards[i]);
        bindDateChangeListeners(existingCards[i]);
    }

    // Initial overlap check
    checkOverlaps();
})();
