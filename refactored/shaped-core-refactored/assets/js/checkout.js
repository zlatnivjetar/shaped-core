// =============== SEARCH RESULTS & CHECKOUT: SHARED UTILITIES ======================
document.addEventListener('DOMContentLoaded', function() {

    const discountConfig = ShapedConfig.discounts || {};
    const urgencyConfig = { critical: 1, low: 2 };
    
    // Global cache for RoomCloud availability data
    let roomcloudAvailability = null;
    let availabilityCheckInProgress = false;

    // Convert DD/MM/YYYY to YYYY-MM-DD
    function convertDateFormat(dateStr) {
        if (!dateStr) return null;
        
        // Already in YYYY-MM-DD format?
        if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) {
            return dateStr;
        }
        
        // DD/MM/YYYY format?
        if (/^\d{2}\/\d{2}\/\d{4}$/.test(dateStr)) {
            const [day, month, year] = dateStr.split('/');
            return `${year}-${month}-${day}`;
        }
        
        return null;
    }

    async function fetchRoomCloudAvailability(checkIn, checkOut) {
        if (availabilityCheckInProgress) {
            // Wait for in-progress request
            await new Promise(resolve => {
                const interval = setInterval(() => {
                    if (!availabilityCheckInProgress) {
                        clearInterval(interval);
                        resolve();
                    }
                }, 100);
            });
            return roomcloudAvailability;
        }
        
        availabilityCheckInProgress = true;
        
        try {
            const response = await fetch(ShapedConfig.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'shaped_rc_get_availability',
                    check_in: checkIn,
                    check_out: checkOut
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                roomcloudAvailability = data.data;
                console.log('[RoomCloud] Availability for urgency badges:', roomcloudAvailability);
                return roomcloudAvailability;
            }
        } catch (error) {
            console.error('[RoomCloud] Error fetching availability:', error);
        } finally {
            availabilityCheckInProgress = false;
        }
        
        return null;
    }

    function detectAvailability(room) {
        const roomTitle = room.querySelector('.mphb-room-type-title')?.textContent.trim();
        if (!roomTitle) return null;
        
        const roomSlug = roomTitle.toLowerCase().replace(/\s+/g, '-');
        
        // PRIORITY 1: Check RoomCloud data first
        if (roomcloudAvailability && roomcloudAvailability.hasOwnProperty(roomSlug)) {
            const count = roomcloudAvailability[roomSlug];
            
            if (count !== null && count !== undefined) {
                return count;
            }
        }
        
        // PRIORITY 2: No RoomCloud data - check MotoPress DOM
        const availableRoomsEl = room.querySelector('.mphb-available-rooms-count');
        if (availableRoomsEl) {
            const match = availableRoomsEl.textContent.match(/(\d+)/);
            if (match) {
                const count = parseInt(match[1]);
                return count;
            }
        }
        
        // PRIORITY 3: Single room scenario (no counter shown)
        const multipleWrapper = room.querySelector('.mphb-rooms-quantity-wrapper');
        if (!multipleWrapper || multipleWrapper.classList.contains('mphb-hide')) {
            return 1;
        }
        
        return null;
    }

    // =============== SEARCH RESULTS: DISCOUNT & URGENCY BADGES ======================
    
    async function applyDiscountsAndUrgency() {
        // Get dates from URL or form
        const urlParams = new URLSearchParams(window.location.search);
        let checkIn = urlParams.get('mphb_check_in_date') || 
                      document.querySelector('input[name="mphb_check_in_date"]')?.value;
        let checkOut = urlParams.get('mphb_check_out_date') || 
                       document.querySelector('input[name="mphb_check_out_date"]')?.value;

        checkIn = convertDateFormat(checkIn);
        checkOut = convertDateFormat(checkOut);
        
        // Fetch RoomCloud availability if we have dates
        if (checkIn && checkOut) {
            await fetchRoomCloudAvailability(checkIn, checkOut);
        }
        
        const roomTypes = document.querySelectorAll('.mphb-room-type');
        
        roomTypes.forEach(room => {
            const roomTitle = room.querySelector('.mphb-room-type-title')?.textContent.trim();
            const roomSlug = roomTitle?.toLowerCase().replace(/\s+/g, '-');
            const discountPercent = discountConfig[roomSlug];
            
            // Find the price element
            const priceEl = room.querySelector('.mphb-price');
            if (!priceEl) return;
            
            // Check if already processed
            if (priceEl.closest('.mphb-price-discount-wrapper')) return;
            
            // Get availability count from RoomCloud
            const availableCount = detectAvailability(room);
            
            // Get original price from MotoPress
            const originalPriceText = priceEl.textContent;
            const originalPrice = parseInt(originalPriceText.replace(/[^0-9]/g, ''));
            
            // Calculate discounted price
            const discountedPrice = discountPercent 
                ? Math.round(originalPrice * (1 - discountPercent / 100))
                : originalPrice;
            
            // Find the parent paragraph
            const priceContainer = priceEl.closest('.mphb-regular-price');
            if (!priceContainer) return;
            
            // Create new wrapper structure
            const discountWrapper = document.createElement('div');
            discountWrapper.className = 'mphb-price-discount-wrapper';
            
            // Add original price with strikethrough if there's a discount
            if (discountPercent) {
                const originalPriceEl = document.createElement('span');
                originalPriceEl.className = 'mphb-price-original';
                originalPriceEl.innerHTML = `<span class="mphb-currency">€</span>${originalPrice}`;
                discountWrapper.appendChild(originalPriceEl);
            }
            
            // Create the current/discounted price element
            const currentPriceEl = document.createElement('span');
            currentPriceEl.className = 'mphb-price mphb-price-current';
            currentPriceEl.innerHTML = `<span class="mphb-currency">€</span>${discountedPrice}`;
            discountWrapper.appendChild(currentPriceEl);
            
            // Get and add the period text
            const periodEl = priceContainer.querySelector('.mphb-price-period');
            const periodText = periodEl ? periodEl.textContent.trim() : '';
            
            const newPeriodEl = document.createElement('span');
            newPeriodEl.className = 'mphb-price-period';
            newPeriodEl.textContent = periodText;
            discountWrapper.appendChild(newPeriodEl);
            
            // Add discount badge if applicable
            if (discountPercent) {
                const discountBadge = document.createElement('span');
                discountBadge.className = 'mphb-discount-badge';
                discountBadge.textContent = `${discountPercent}% off`;
                discountWrapper.appendChild(discountBadge);
            }
            
            // Add urgency badge if applicable - only for 1 or 2 rooms
            if (availableCount !== null && availableCount <= urgencyConfig.low) {
                const urgencyBadge = document.createElement('span');
                urgencyBadge.className = 'mphb-urgency-badge';
                
                if (availableCount === urgencyConfig.critical) {
                    urgencyBadge.textContent = `Last room`;
                } else if (availableCount === urgencyConfig.low) {
                    urgencyBadge.textContent = `2 left`;
                }
                
                discountWrapper.appendChild(urgencyBadge);
            }
            
            // Replace content after "Prices start at:"
            const strongEl = priceContainer.querySelector('strong');
            if (strongEl) {
                while (strongEl.nextSibling) {
                    strongEl.nextSibling.remove();
                }
                priceContainer.appendChild(discountWrapper);
            }
        });
    }

    // =============== SEARCH RESULTS: ROOM SELECTION HANDLERS ======================
    
    // Enhanced room selection handlers - store availability in localStorage
    document.querySelectorAll('.mphb-book-button, .mphb-view-details-button, .mphb-room-type-title a').forEach(link => {
        link.addEventListener('click', function(e) {
            const room = this.closest('.mphb-room-type');
            if (room) {
                const availableCount = detectAvailability(room);
                const roomTitle = room.querySelector('.mphb-room-type-title')?.textContent.trim();
                
                // Store availability data
                if (roomTitle && availableCount !== null) {
                    const roomSlug = roomTitle.toLowerCase().replace(/\s+/g, '-');
                    const data = { count: availableCount, timestamp: Date.now() };
                    sessionStorage.setItem(`mphb_availability_${roomSlug}`, JSON.stringify(data));
                    localStorage.setItem(`mphb_availability_${roomSlug}`, JSON.stringify(data));
                    
                    // Append to URL if it's a checkout link and availability is low
                    if (this.href && availableCount <= 2) {
                        const separator = this.href.includes('?') ? '&' : '?';
                        if (!this.href.includes('room_availability=')) {
                            this.href += `${separator}room_availability=${availableCount}`;
                        }
                    }
                }
            }
        });
    });

    // Save booking context when user interacts with room cards
    function saveBookingContext() {
        const urlParams = new URLSearchParams(window.location.search);
        
        const context = {
            checkIn: urlParams.get('mphb_check_in_date') || 
                    document.querySelector('input[name="mphb_check_in_date"]')?.value,
            checkOut: urlParams.get('mphb_check_out_date') || 
                     document.querySelector('input[name="mphb_check_out_date"]')?.value,
            adults: urlParams.get('mphb_adults') || '2',
            children: urlParams.get('mphb_children') || '0',
            rooms: '1',
            timestamp: Date.now()
        };
        
        if (context.checkIn && context.checkOut) {
            localStorage.setItem('preBookingCtx', JSON.stringify(context));
        }
    }
    
    saveBookingContext();

    // =============== CHECKOUT: DISCOUNT, URGENCY & PAYMENT NOTE ======================
    
    // Debounce flag for checkout availability fetch
    let checkoutAvailabilityFetched = false;
    let lastCheckoutDates = '';

    function checkRoomAvailability() {
        const roomTitleElement = document.querySelector('h3.mphb-room-type-title');
        if (!roomTitleElement) return null;
        
        const roomTitle = roomTitleElement.textContent.trim();
        const roomSlug = roomTitle.toLowerCase().replace(/\s+/g, '-');
        
        // First check for server-provided value
        const serverAvailability = document.getElementById('mphb_available_rooms');
        if (serverAvailability) {
            const count = parseInt(serverAvailability.value);
            return { count: count, roomType: roomTitle };
        }
        
        // Check URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        const urlAvailability = urlParams.get('room_availability');
        if (urlAvailability) {
            return { count: parseInt(urlAvailability), roomType: roomTitle };
        }
        
        // Check both storages, prefer session
        let stored = sessionStorage.getItem(`mphb_availability_${roomSlug}`);
        if (!stored) {
            stored = localStorage.getItem(`mphb_availability_${roomSlug}`);
        }
        
        if (stored) {
            const data = JSON.parse(stored);
            // Check if data is less than 24 hours old
            if (Date.now() - data.timestamp < 86400000) {
                return { count: data.count, roomType: roomTitle };
            }
        }
        
        // Fallback: assume low availability if we're on checkout with no data
        return { count: 1, roomType: roomTitle, assumed: true };
    }

    async function applyCheckoutDiscount() {
        const roomTitleElement = document.querySelector('h3.mphb-room-type-title');
        if (!roomTitleElement) return;

        const roomTitle = roomTitleElement.textContent.trim();
        const roomSlug = roomTitle.toLowerCase().replace(/\s+/g, '-');

        // Get dates from URL or form
        const urlParams = new URLSearchParams(window.location.search);
        let checkIn = urlParams.get('mphb_check_in_date') || 
                      document.querySelector('input[name="mphb_check_in_date"]')?.value;
        let checkOut = urlParams.get('mphb_check_out_date') || 
                       document.querySelector('input[name="mphb_check_out_date"]')?.value;

        checkIn = convertDateFormat(checkIn);
        checkOut = convertDateFormat(checkOut);

        // Create cache key from dates
        const currentDates = `${checkIn}-${checkOut}`;
        
        // Fetch fresh RoomCloud availability if we have dates AND haven't fetched yet
        if (checkIn && checkOut && (!checkoutAvailabilityFetched || lastCheckoutDates !== currentDates)) {
            console.log('[Checkout] Fetching fresh RoomCloud availability...');
            await fetchRoomCloudAvailability(checkIn, checkOut);
            
            // Mark as fetched for these dates
            checkoutAvailabilityFetched = true;
            lastCheckoutDates = currentDates;

            // Update localStorage with fresh data for this room
            if (roomcloudAvailability && roomcloudAvailability.hasOwnProperty(roomSlug)) {
                const freshCount = roomcloudAvailability[roomSlug];
                if (freshCount !== null && freshCount !== undefined) {
                    const data = { count: freshCount, timestamp: Date.now() };
                    sessionStorage.setItem(`mphb_availability_${roomSlug}`, JSON.stringify(data));
                    localStorage.setItem(`mphb_availability_${roomSlug}`, JSON.stringify(data));
                    console.log(`[Checkout] Fresh RoomCloud data: ${freshCount} available`);
                }
            } else {
                console.log('[Checkout] No RoomCloud data - will fall back to MotoPress');
            }
        } else if (checkIn && checkOut) {
            console.log('[Checkout] Using cached availability data');
        }

        const discountPercent = discountConfig[roomSlug] || 0;

        if (!discountPercent) {
            // Still try to add urgency even without discount
            addUrgencyMessage();
            return;
        }

        // Inject discount data into form for PHP
        const form = document.querySelector('.mphb_sc_checkout-form');
        if (form) {
            // Remove existing fields
            form.querySelectorAll('input[name^="mphb_discount_"]').forEach(el => el.remove());

            // Add room type and discount percentage
            const roomTypeField = document.createElement('input');
            roomTypeField.type = 'hidden';
            roomTypeField.name = 'mphb_discount_room_type';
            roomTypeField.value = roomSlug;
            form.appendChild(roomTypeField);

            const discountField = document.createElement('input');
            discountField.type = 'hidden';
            discountField.name = 'mphb_discount_percentage';
            discountField.value = discountPercent;
            form.appendChild(discountField);
        }

        // Update visual display
        updateCheckoutPriceDisplay(discountPercent);
        addUrgencyMessage();
    }
    
    function updateCheckoutPriceDisplay(discountPercent) {
        const totalPriceField = document.querySelector('.mphb-total-price-field');
        if (!totalPriceField) return;

        const priceSpan = totalPriceField.querySelector('.mphb-price');
        if (!priceSpan) return;

        // Get original price from MotoPress (might be in .mphb-price or .mphb-price-current)
        const currentPriceSpan = totalPriceField.querySelector('.mphb-price-current') || priceSpan;
        const priceText = currentPriceSpan.textContent;
        const currentPrice = parseInt(priceText.replace(/[^0-9]/g, ''));

        // Check if we already have discount structure - if so, get the ORIGINAL undiscounted price
        const existingOriginalPrice = totalPriceField.querySelector('.mphb-price-original');
        let originalPrice;

        if (existingOriginalPrice) {
            // Already showing discount - extract original price from the strikethrough element
            originalPrice = parseInt(existingOriginalPrice.textContent.replace(/[^0-9]/g, ''));
        } else {
            // First time applying discount - current price IS the original
            originalPrice = currentPrice;
        }

        // Calculate discount on TOTAL price (including breakfast/services)
        const discountAmount = Math.round(originalPrice * (discountPercent / 100));
        const discountedTotal = originalPrice - discountAmount;

        console.log('[Discount Badge] Updating display - Original:', originalPrice, 'Discounted:', discountedTotal, 'Amount saved:', discountAmount);

        // Update display
        totalPriceField.innerHTML = `
            <span class="mphb-price-original" style="text-decoration: line-through; color: #999; font-weight: normal;">
                <span class="mphb-currency">€</span>${originalPrice}
            </span>
            <span class="mphb-price mphb-price-current" style="color: #D1AF5D; margin-left: 8px; font-weight: bold;">
                <span class="mphb-currency">€</span>${discountedTotal}
            </span>
        `;

        // Force badge update after a tiny delay to ensure element exists
        setTimeout(() => {
            const totalPriceOutput = document.querySelector('.mphb-total-price output');
            if (totalPriceOutput) {
                let badge = totalPriceOutput.querySelector('.discount-badge-checkout');
                if (badge) {
                    console.log('[Discount Badge] Updating existing badge');
                    badge.textContent = `You're saving €${discountAmount} today`;
                } else {
                    console.log('[Discount Badge] Creating new badge');
                    badge = document.createElement('span');
                    badge.className = 'discount-badge-checkout';
                    badge.textContent = `You're saving €${discountAmount} today`;
                    totalPriceOutput.appendChild(badge);
                }
            }
        }, 10);
    }

    function addUrgencyMessage() {
        // Check if urgency message already exists
        if (document.querySelector('.checkout-urgency-badge')) return;
        
        const availability = checkRoomAvailability();
        
        if (availability && availability.count <= urgencyConfig.low) {
            const totalPriceOutput = document.querySelector('.mphb-total-price output');
            if (totalPriceOutput) {
                const urgencyMessage = document.createElement('span');
                urgencyMessage.className = 'checkout-urgency-badge mphb-urgency-badge';
                
                if (availability.count === urgencyConfig.critical) {
                    urgencyMessage.textContent = availability.assumed 
                        ? `Last room`
                        : `Last room`;
                } else if (availability.count === urgencyConfig.low) {
                    urgencyMessage.textContent = `2 rooms left`;
                }
                
                // Insert after discount badge or after price
                const discountBadge = totalPriceOutput.querySelector('.discount-badge-checkout');
                if (discountBadge) {
                    discountBadge.after(urgencyMessage);
                } else {
                    totalPriceOutput.appendChild(urgencyMessage);
                }
            }
        }
    }

    function updatePaymentNote() {
        const paymentNote = document.getElementById('shaped-payment-note');
        if (!paymentNote || paymentNote.dataset.paymentMode !== 'delayed') return;
        
        // Get current total from checkout display
        const totalPriceEl = document.querySelector('.mphb-total-price-field .mphb-price-current');
        if (!totalPriceEl) return;
        
        const totalText = totalPriceEl.textContent;
        const totalAmount = totalText.replace(/[^0-9.,]/g, '').replace(',', '.');
        
        // Update the note
        const noteBody = paymentNote.querySelector('.shaped-note__body');
        if (noteBody) {
            const chargeDate = paymentNote.dataset.chargeDate;
            const formattedDate = new Date(chargeDate).toLocaleDateString('en-US', { 
                month: 'long', 
                day: 'numeric', 
                year: 'numeric' 
            });
            
            noteBody.innerHTML = `
                We will charge <strong>€${totalAmount}</strong> 
                on <strong style="color:#141310">${formattedDate}</strong> 
                using the card you save now.
            `;
        }
    }

    // =============== CHECKOUT: PRICE BREAKDOWN DISCOUNT ROW ======================

    function addDiscountRow() {
        var discountPercent = document.querySelector('input[name="mphb_discount_percentage"]')?.value;
        if (!discountPercent || discountPercent == 0) return;

        var breakdown = document.querySelector('.mphb-price-breakdown');
        if (!breakdown) return;

        var accommodationRow = breakdown.querySelector('.mphb-price-breakdown-accommodation-total');
        var subtotalRow = breakdown.querySelector('.mphb-price-breakdown-subtotal');

        if (accommodationRow) {
            // Get accommodation total (excludes services like breakfast)
            var accommodationText = accommodationRow.querySelector('.mphb-table-price-column')?.textContent;
            var accommodationTotal = parseInt(accommodationText.replace(/[^0-9]/g, ''));

            // Calculate discount ONLY on accommodation (not on services)
            var discountAmount = Math.round(accommodationTotal * (discountPercent / 100));

            // Check if discount row already exists
            var existingDiscountRow = breakdown.querySelector('.mphb-discount-row');

            // Determine number of columns based on whether services exist
            var hasServices = breakdown.querySelectorAll('.mphb-price-breakdown-services').length > 0;
            var colSpan = hasServices ? 2 : 1;

            if (existingDiscountRow) {
                // Update existing row
                var discountCell = existingDiscountRow.querySelector('.mphb-table-price-column');
                if (discountCell) {
                    discountCell.innerHTML = '<span class="mphb-currency">€</span>' + discountAmount;
                }
                // Update colspan if services were added/removed
                var thElement = existingDiscountRow.querySelector('th[colspan]');
                if (thElement) {
                    thElement.setAttribute('colspan', colSpan);
                }
                console.log('[Discount Row] Updated with amount: €' + discountAmount);
            } else {
                // Create new discount row
                var discountRow = document.createElement('tr');
                discountRow.className = 'mphb-discount-row';
                discountRow.innerHTML =
                    '<th colspan="' + colSpan + '">You\'re saving:</th>' +
                    '<th class="mphb-table-price-column" style="color: #4C9155;">-<span class="mphb-currency">€</span>' + discountAmount + '</th>';

                // Insert after the subtotal row if it exists, otherwise after accommodation
                if (subtotalRow) {
                    subtotalRow.parentNode.insertBefore(discountRow, subtotalRow.nextSibling);
                } else {
                    accommodationRow.parentNode.insertBefore(discountRow, accommodationRow.nextSibling);
                }
                console.log('[Discount Row] Added with amount: €' + discountAmount);
            }

            // Update the total row to reflect the discount
            var totalRow = breakdown.querySelector('.mphb-price-breakdown-total');
            if (totalRow) {
                var totalCell = totalRow.querySelector('.mphb-table-price-column');
                if (totalCell) {
                    var totalText = totalCell.textContent;
                    var originalTotal = parseInt(totalText.replace(/[^0-9]/g, ''));
                    var newTotal = originalTotal - discountAmount;

                    totalCell.innerHTML = '<span class="mphb-price"><span class="mphb-currency">€</span>' + newTotal + '</span>';
                }
            }

            // Update discount badge with new amount
            updateDiscountBadge(discountAmount);
        }
    }

    function updateDiscountBadge(discountAmount) {
        console.log('[Discount Badge] updateDiscountBadge called with amount:', discountAmount);

        var totalPriceOutput = document.querySelector('.mphb-total-price output');
        if (!totalPriceOutput) {
            console.log('[Discount Badge] No total price output found');
            return;
        }

        var badge = totalPriceOutput.querySelector('.discount-badge-checkout');
        if (badge) {
            console.log('[Discount Badge] Updating existing badge text');
            badge.textContent = `You're saving €${discountAmount} today`;
        } else {
            console.log('[Discount Badge] Badge not found - creating new one');
            // Badge was removed - recreate it
            badge = document.createElement('span');
            badge.className = 'discount-badge-checkout';
            badge.textContent = `You're saving €${discountAmount} today`;

            // Insert after urgency badge if it exists, otherwise append
            const urgencyBadge = totalPriceOutput.querySelector('.checkout-urgency-badge');
            if (urgencyBadge) {
                urgencyBadge.parentNode.insertBefore(badge, urgencyBadge);
            } else {
                totalPriceOutput.appendChild(badge);
            }
            console.log('[Discount Badge] New badge created and appended');
        }
    }

    function ensureDiscountBadgeExists() {
        console.log('[Discount Badge] ensureDiscountBadgeExists called');

        const discountPercent = document.querySelector('input[name="mphb_discount_percentage"]')?.value;
        if (!discountPercent || discountPercent == 0) {
            console.log('[Discount Badge] No discount percentage found');
            return;
        }

        const totalPriceOutput = document.querySelector('.mphb-total-price output');
        if (!totalPriceOutput) {
            console.log('[Discount Badge] No total price output element found');
            return;
        }

        const badge = totalPriceOutput.querySelector('.discount-badge-checkout');
        if (badge) {
            console.log('[Discount Badge] Badge already exists');
            return; // Badge exists, no need to recreate
        }

        // Badge is missing - calculate discount and recreate
        console.log('[Discount Badge] Badge missing - recalculating');
        const breakdown = document.querySelector('.mphb-price-breakdown');
        if (!breakdown) {
            console.log('[Discount Badge] No price breakdown found');
            return;
        }

        const accommodationRow = breakdown.querySelector('.mphb-price-breakdown-accommodation-total');
        if (!accommodationRow) {
            console.log('[Discount Badge] No accommodation row found');
            return;
        }

        const accommodationText = accommodationRow.querySelector('.mphb-table-price-column')?.textContent;
        const accommodationTotal = parseInt(accommodationText.replace(/[^0-9]/g, ''));
        const discountAmount = Math.round(accommodationTotal * (discountPercent / 100));

        console.log('[Discount Badge] Recalculated discount:', discountAmount);
        updateDiscountBadge(discountAmount);
    }

    // =============== INITIALIZATION ======================
    
    // Initial application - search results
    (async () => {
        await applyDiscountsAndUrgency();
    })();

    // Checkout needs to wait for DOM and be async
    setTimeout(async () => {
        await applyCheckoutDiscount();
    }, 500);

    // Initial discount row
    setTimeout(addDiscountRow, 500);

    // Initial payment note update
    setTimeout(updatePaymentNote, 500);

    // Mutation observer for checkout dynamic updates
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.target.classList &&
                (mutation.target.classList.contains('mphb-total-price-field') ||
                 mutation.target.classList.contains('mphb-price-breakdown'))) {
                setTimeout(async () => {
                    await applyCheckoutDiscount();
                    addDiscountRow(); // Re-add discount row after MotoPress updates
                }, 50);
            }
        });
    });

    const priceElements = document.querySelectorAll('.mphb-total-price, .mphb-checkout-form');
    priceElements.forEach(el => {
        observer.observe(el, { childList: true, subtree: true });
    });

    // More aggressive monitoring for MotoPress AJAX updates
    if (window.jQuery) {
        jQuery(document).ajaxComplete(function(event, xhr, settings) {
            if (settings.url && settings.url.includes('mphb_update_checkout_info')) {
                console.log('[MotoPress] AJAX complete - re-adding discount row and badge');
                setTimeout(updatePaymentNote, 100);
                setTimeout(addDiscountRow, 150);
                setTimeout(addDiscountRow, 300); // Try again after 300ms in case MotoPress updates again
                setTimeout(ensureDiscountBadgeExists, 150);
                setTimeout(ensureDiscountBadgeExists, 300); // Try again for badge too
            }
        });
    }

    // Also watch for any changes to the price breakdown table specifically
    const breakdownObserver = new MutationObserver(function(mutations) {
        const hasDiscountRow = document.querySelector('.mphb-discount-row');
        if (!hasDiscountRow) {
            console.log('[Discount Row] Not found - re-adding');
            setTimeout(addDiscountRow, 100);
        }
    });

    const breakdown = document.querySelector('.mphb-price-breakdown');
    if (breakdown) {
        breakdownObserver.observe(breakdown, {
            childList: true,
            subtree: true
        });
    }

    // Watch for discount badge removal and re-add it
    const badgeObserver = new MutationObserver(function(mutations) {
        const discountPercent = document.querySelector('input[name="mphb_discount_percentage"]')?.value;
        if (!discountPercent || discountPercent == 0) return;

        const hasBadge = document.querySelector('.discount-badge-checkout');
        if (!hasBadge) {
            console.log('[Discount Badge] Not found - re-adding');
            setTimeout(ensureDiscountBadgeExists, 50);
        }
    });

    const totalPriceOutput = document.querySelector('.mphb-total-price output');
    if (totalPriceOutput) {
        badgeObserver.observe(totalPriceOutput, {
            childList: true,
            subtree: true
        });
    }
});
