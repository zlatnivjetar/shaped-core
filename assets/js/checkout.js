// =============== SEARCH RESULTS PAGE (urgency badges) ======================
document.addEventListener('DOMContentLoaded', function() {

    const discountConfig = ShapedConfig.discounts || {};
    
    // Updated urgency thresholds - only 1 and 2
    const urgencyConfig = {
        critical: 1,  // "Only 1 left"
        low: 2        // "2 left"
    };
    
    // Helper: round like Stripe (1 decimal)
    function roundToOneDecimal(value) {
        const num = typeof value === 'number' ? value : parseFloat(value);
        if (isNaN(num)) return 0;
        return Math.round(num * 10) / 10;
    }

    // NEW: robust EU/US price parser
    // Handles:
    //   "€1.265,0"  -> 1265.0
    //   "1.265,80"  -> 1265.8
    //   "€140,0"    -> 140.0
    //   "€140.0"    -> 140.0
    //   "€1.300"    -> 1300.0   (thousands, not 1.3)
    function parsePrice(text) {
        if (!text) return 0;
    
        const numeric = text.replace(/[^0-9.,]/g, '');
        if (!numeric) return 0;
    
        const hasComma = numeric.indexOf(',') !== -1;
        const hasDot = numeric.indexOf('.') !== -1;
    
        let normalized;
    
        if (hasComma && hasDot) {
            // EU style: "." thousands, "," decimal (1.234,5)
            normalized = numeric.replace(/\./g, '').replace(',', '.');
        } else if (hasComma) {
            // Only comma: decimal separator (116,8)
            normalized = numeric.replace(',', '.');
        } else if (hasDot) {
            // Could be decimal (116.8) or thousands (1.300)
            const parts = numeric.split('.');
    
            if (parts.length === 2 && parts[1].length === 3 && parts[0].length >= 1) {
                // Pattern like "1.300", "12.500" -> thousands separator
                normalized = parts[0] + parts[1]; // "1" + "300" => "1300"
            } else if (parts.length > 2) {
                // Many groups: treat all but last as thousands
                const last = parts.pop();
                const intPart = parts.join('');
    
                if (last.length === 3) {
                    // "1.234.567" -> "1234567"
                    normalized = intPart + last;
                } else {
                    // "1.234.5" -> "1234.5"
                    normalized = intPart + '.' + last;
                }
            } else {
                // Standard decimal like "116.8"
                normalized = numeric;
            }
        } else {
            // Digits only
            normalized = numeric;
        }
    
        const result = parseFloat(normalized);
        return isNaN(result) ? 0 : result;
    }

    
    function formatPrice(value) {
        const rounded = roundToOneDecimal(value);
    
        // If rounded value is effectively an integer → no decimals
        const intVal = Math.round(rounded);
        if (Math.abs(rounded - intVal) < 1e-6) {
            return intVal
                .toString()
                .replace(/\B(?=(\d{3})+(?!\d))/g, '.'); // 1634 -> "1.634"
        }
    
        // Non-integer: 1 decimal, EU style
        const [intPartRaw, decPart] = rounded.toFixed(1).split('.'); // "1634.1"
    
        const intPartWithThousands = intPartRaw.replace(
            /\B(?=(\d{3})+(?!\d))/g,
            '.'
        ); // "1634" -> "1.634"
    
        return `${intPartWithThousands},${decPart}`; // "1.634,1"
    }


    // Percentage formatter (for deposit percent text)
    function formatPercent(value) {
        const num = typeof value === 'number' ? value : parseFloat(value);
        if (isNaN(num)) return '0';
        const asInt = Math.round(num);
        if (Math.abs(num - asInt) < 1e-6) return asInt.toString();
        return num.toFixed(1).replace('.', ',');
    }

    // Global cache for RoomCloud availability data
    let roomcloudAvailability = null;
    let availabilityCheckInProgress = false;

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
                const count = parseInt(match[1], 10);
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
    
    async function applyDiscountsAndUrgency() {
        const urlParams = new URLSearchParams(window.location.search);

        // Get dates and convert to YYYY-MM-DD format
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
            const originalPrice = parsePrice(originalPriceText);
            
            // Calculate discounted price
            const discountedPrice = discountPercent 
                ? formatPrice(originalPrice * (1 - discountPercent / 100))
                : formatPrice(originalPrice);
            
            // Find the parent paragraph
            const priceContainer = priceEl.closest('.mphb-regular-price');
            if (!priceContainer) return;
            
            // Create new wrapper structure
            const discountWrapper = document.createElement('div');
            discountWrapper.className = 'mphb-price-discount-wrapper';
            
            // Add original price with strikethrough if there's a discount
            if (discountPercent) {
                const originalPriceElNode = document.createElement('span');
                originalPriceElNode.className = 'mphb-price-original';
                originalPriceElNode.innerHTML = `<span class="mphb-currency">€</span>${formatPrice(originalPrice)}`;
                discountWrapper.appendChild(originalPriceElNode);
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

    // =============== CHECKOUT HELPERS ======================

    function checkRoomAvailability() {
        const roomTitleElement = document.querySelector('h3.mphb-room-type-title');
        if (!roomTitleElement) return null;
        
        const roomTitle = roomTitleElement.textContent.trim();
        const roomSlug = roomTitle.toLowerCase().replace(/\s+/g, '-');
        
        // First check for server-provided value (if you implement PHP solution)
        const serverAvailability = document.getElementById('mphb_available_rooms');
        if (serverAvailability) {
            const count = parseInt(serverAvailability.value, 10);
            return { count: count, roomType: roomTitle };
        }
        
        // Check URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        const urlAvailability = urlParams.get('room_availability');
        if (urlAvailability) {
            return { count: parseInt(urlAvailability, 10), roomType: roomTitle };
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

    function ensureCheckoutDiscountState() {
        const roomTitleElement = document.querySelector('h3.mphb-room-type-title');
        if (!roomTitleElement) return 0;

        // Check URL for availability and store it
        const urlParams = new URLSearchParams(window.location.search);
        const urlAvailability = urlParams.get('room_availability');
        
        if (urlAvailability) {
            const roomTitle = roomTitleElement.textContent.trim();
            const roomSlug = roomTitle.toLowerCase().replace(/\s+/g, '-');
            const data = { count: parseInt(urlAvailability, 10), timestamp: Date.now() };
            sessionStorage.setItem(`mphb_availability_${roomSlug}`, JSON.stringify(data));
            localStorage.setItem(`mphb_availability_${roomSlug}`, JSON.stringify(data));
        }
        
        const roomTitle = roomTitleElement.textContent.trim();
        const roomSlug = roomTitle.toLowerCase().replace(/\s+/g, '-');
        const discountPercent = discountConfig[roomSlug] || 0;

        const form = document.querySelector('.mphb_sc_checkout-form');
        if (form && discountPercent) {
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

        return discountPercent;
    }

    function updateCheckoutPriceDisplay(discountPercent) {
        if (!discountPercent) return;

        const totalPriceField = document.querySelector('.mphb-total-price-field');
        if (!totalPriceField) return;
        
        const priceSpan = totalPriceField.querySelector('.mphb-price');
        if (!priceSpan) return;
        
        // Skip if already showing discount
        if (totalPriceField.querySelector('.mphb-price-original')) return;
        
        const priceText = priceSpan.textContent;
        const originalPrice = parsePrice(priceText);
        
        // Get services total from price breakdown if available
        let servicesTotal = 0;
        const servicesRow = document.querySelector('.mphb-price-breakdown-services-total .mphb-table-price-column');
        if (servicesRow) {
            const servicesText = servicesRow.textContent;
            servicesTotal = parsePrice(servicesText) || 0;
        }
        
        // Calculate discount only on accommodation (total - services)
        const accommodationTotal = originalPrice - servicesTotal;
        const discountAmount = roundToOneDecimal(accommodationTotal * (discountPercent / 100));
        const discountedTotal = formatPrice(originalPrice - discountAmount);
        
        // Update display
        totalPriceField.innerHTML = `
            <span class="mphb-price-original" style="text-decoration: line-through; color: #999; font-weight: normal;">
                <span class="mphb-currency">€</span>${formatPrice(originalPrice)}
            </span>
            <span class="mphb-price mphb-price-current" style="color: #D1AF5D; margin-left: 8px; font-weight: bold;">
                <span class="mphb-currency">€</span>${discountedTotal}
            </span>
        `;
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
                    urgencyMessage.textContent = `Last room`;
                } else if (availability.count === urgencyConfig.low) {
                    urgencyMessage.textContent = `2 rooms left`;
                }
                
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
        if (!paymentNote) return;
    
        const mode = paymentNote.dataset.paymentMode;
        if (mode !== 'delayed' && mode !== 'deposit') return;
    
        // Prefer discounted total if present
        const totalPriceEl =
            document.querySelector('.mphb-total-price-field .mphb-price-current') ||
            document.querySelector('.mphb-total-price-field .mphb-price');
    
        if (!totalPriceEl) return;
    
        const totalTextRaw = totalPriceEl.textContent;
        const totalNumber = parsePrice(totalTextRaw);
        if (isNaN(totalNumber)) return;
    
        const totalDisplay = formatPrice(totalNumber);
    
        // ===== DELAYED MODE =====
        if (mode === 'delayed') {
            const noteBody = paymentNote.querySelector('.shaped-note__body');
            if (!noteBody) return;
    
            const chargeDate = paymentNote.dataset.chargeDate;
            if (!chargeDate) return;
    
            const formattedDate = new Date(chargeDate).toLocaleDateString('en-US', { 
                month: 'long', 
                day: 'numeric', 
                year: 'numeric' 
            });
    
            noteBody.innerHTML = `
                We will charge <strong>€${totalDisplay}</strong> 
                on <strong style="color:#141310">${formattedDate}</strong> 
                using the card you save now.
            `;
    
            // Keep dataset in sync
            paymentNote.dataset.total = totalNumber;
            return;
        }
    
        // ===== DEPOSIT MODE =====
        if (mode === 'deposit') {
            const headline = paymentNote.querySelector('.shaped-note__headline');
            const noteBody = paymentNote.querySelector('.shaped-note__body');
            if (!headline || !noteBody) return;
    
            // 1) Get deposit percent
            let depositPercent = paymentNote.dataset.depositPercent
                ? parseFloat(paymentNote.dataset.depositPercent)
                : null;
    
            if (!depositPercent || isNaN(depositPercent)) {
                const origTotal   = parseFloat(paymentNote.dataset.total || totalNumber);
                const origDeposit = parseFloat(paymentNote.dataset.depositAmount);
                if (!origTotal || isNaN(origTotal) || !origDeposit || isNaN(origDeposit)) {
                    return;
                }
                depositPercent = (origDeposit / origTotal) * 100;
                paymentNote.dataset.depositPercent = depositPercent; // cache
            }
    
            // 2) Recalculate deposit & balance from *current* total
            const depositNumber  = (totalNumber * depositPercent) / 100;
            const balanceNumber  = totalNumber - depositNumber;
    
            const depositDisplay = formatPrice(depositNumber);
            const balanceDisplay = formatPrice(balanceNumber);
    
            // 3) Update headline + body copy
            headline.innerHTML = `Pay €${depositDisplay} deposit today`;
    
            noteBody.innerHTML = `
                Secure your booking with a <strong>${formatPercent(depositPercent)}% deposit</strong>.<br>
                Remaining <strong>€${balanceDisplay}</strong> is due on arrival.
            `;
    
            // 4) Keep data attributes in sync
            paymentNote.dataset.total         = totalNumber;
            paymentNote.dataset.depositAmount = depositNumber;
            paymentNote.dataset.balanceDue    = balanceNumber;
        }
    }

    function addDiscountRow(discountPercent) {
        if (!discountPercent) return;

        const $ = window.jQuery;
        if (!$) return;

        var $breakdown = $('.mphb-price-breakdown');
        if ($breakdown.length && !$breakdown.find('.mphb-discount-row').length) {
            var $accommodationRow = $breakdown.find('.mphb-price-breakdown-accommodation-total').last();
            var $subtotalRow = $breakdown.find('.mphb-price-breakdown-subtotal').last();
            
            if ($accommodationRow.length) {
                var accommodationText = $accommodationRow.find('.mphb-table-price-column').text();
                var accommodationTotal = parsePrice(accommodationText);
                var discountAmountNum = roundToOneDecimal(accommodationTotal * (discountPercent / 100));
                
                // Determine number of columns based on whether services exist
                var hasServices = $('.mphb-price-breakdown-services').length > 0;
                var colSpan = hasServices ? 2 : 1;
                
                var discountRow = '<tr class="mphb-discount-row">' +
                    '<th colspan="' + colSpan + '">You\'re saving:</th>' +
                    '<th class="mphb-table-price-column" style="color: #4C9155;">-<span class="mphb-currency">€</span>' + formatPrice(discountAmountNum) + '</th>' +
                    '</tr>';
                
                // Insert after the last subtotal row
                if ($subtotalRow.length) {
                    $subtotalRow.last().after(discountRow);
                } else {
                    // Fallback if no subtotal found
                    $breakdown.find('tbody').append(discountRow);
                }
                
                // Update the total to reflect the discount
                var $totalRow = $breakdown.find('.mphb-price-breakdown-total');
                if ($totalRow.length) {
                    var $totalCell = $totalRow.find('.mphb-table-price-column');
                    var totalText = $totalCell.text();
                    var originalTotal = parsePrice(totalText);
                    var newTotal = formatPrice(originalTotal - discountAmountNum);
                    
                    $totalCell.html('<span class="mphb-price"><span class="mphb-currency">€</span>' + newTotal + '</span>');
                }
            }
        }
    }

    function renderCheckoutDiscountBadge(discountPercent) {
        // Remove existing badge first
        const existingBadge = document.querySelector('.discount-badge-checkout');
        if (existingBadge) {
            existingBadge.remove();
        }
        
        if (!discountPercent) return;
        
        const totalPriceOutput = document.querySelector('.mphb-total-price output');
        if (!totalPriceOutput) return;
        
        // Get current prices for calculation
        const originalPriceEl = document.querySelector('.mphb-total-price-field .mphb-price-original');
        const currentPriceEl = document.querySelector('.mphb-total-price-field .mphb-price-current');
        
        if (!originalPriceEl || !currentPriceEl) return;
        
        const originalPrice = parsePrice(originalPriceEl.textContent);
        const currentPrice = parsePrice(currentPriceEl.textContent);
        const discountAmount = formatPrice(originalPrice - currentPrice);
        
        // Create and append new badge
        const badge = document.createElement('span');
        badge.className = 'discount-badge-checkout';
        badge.textContent = `You're saving €${discountAmount} today`;
        totalPriceOutput.appendChild(badge);
    }

    // Discount all rate labels after MotoPress updates them
    function applyDiscountToAllRateLabels() {
        const rateChooser = document.querySelector('.mphb-rate-chooser');
        if (!rateChooser) return;

        const roomTitleEl = document.querySelector('h3.mphb-room-type-title');
        if (!roomTitleEl) return;

        const roomSlug = roomTitleEl.textContent.trim().toLowerCase().replace(/\s+/g, '-');
        const discountPercent = (discountConfig[roomSlug] || 0);
        if (!discountPercent || discountPercent <= 0) return;

        const priceSpans = rateChooser.querySelectorAll('.mphb-room-rate-variant .mphb-price');
        if (!priceSpans.length) return;

        priceSpans.forEach(span => {
            // Prevent double-decoration on the same DOM node
            if (span.dataset.shapedDecorated === '1') return;

            let basePrice;

            if (span.dataset.basePrice) {
                basePrice = parseFloat(span.dataset.basePrice);
            } else {
                const raw = span.textContent;
                basePrice = parsePrice(raw);
                if (isNaN(basePrice)) return;
                span.dataset.basePrice = basePrice; // cache original
            }

            const discountAmount = basePrice * (discountPercent / 100);
            const finalPrice = basePrice - discountAmount;

            const formattedOriginal   = formatPrice(basePrice);
            const formattedDiscounted = formatPrice(finalPrice);

            const currencyEl = span.querySelector('.mphb-currency');
            const currencyHTML = currencyEl ? currencyEl.outerHTML : '';

            const originalHTML = `
                <span class="mphb-price-original" style="text-decoration: line-through; color:#999; font-weight: normal;">
                    ${currencyHTML}${formattedOriginal}
                </span>
            `.trim();

            const discountedHTML = `
                <span class="mphb-price-discount" style="color:#D1AF5D; font-weight:bold; margin-left:4px;">
                    ${currencyHTML}${formattedDiscounted}
                </span>
            `.trim();

            span.innerHTML = `${originalHTML} ${discountedHTML}`;
            span.dataset.shapedDecorated = '1';
        });
    }

    // =============== UNIFIED CHECKOUT REFRESH ======================

    function refreshCheckoutUI() {
        // If there's no checkout form, bail fast (e.g. search results only)
        const checkoutForm = document.querySelector('.mphb_sc_checkout-form');
        if (!checkoutForm) return;

        // 1) Ensure hidden discount fields exist and get current percent
        const discountPercent = ensureCheckoutDiscountState();

        // 2) If discount exists, update all discount-related UI
        if (discountPercent) {
            updateCheckoutPriceDisplay(discountPercent);
            addDiscountRow(discountPercent);
            renderCheckoutDiscountBadge(discountPercent);
        }

        // 3) Always apply urgency + payment note if relevant
        addUrgencyMessage();
        updatePaymentNote();
    }

    // =============== CONTEXT SAVING / AJAX HOOKS ======================

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
    
    // Save on page load
    saveBookingContext();
    
    // Enhanced room selection handlers
    document.querySelectorAll('.mphb-book-button, .mphb-view-details-button, .mphb-room-type-title a').forEach(link => {
        link.addEventListener('click', function() {
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
    
    // AJAX handling for MotoPress updates
    if (window.jQuery) {
        const $ = window.jQuery;

        $(document).on('click', '.mphb-room-type', function() {
            const availableCount = detectAvailability(this);
            // availableCount currently unused
        });
        
        $(document).ajaxSend(function(event, xhr, settings) {
            if (settings.url && settings.url.includes('mphb_')) {
                // Add discount config to all MotoPress AJAX requests
                if (settings.data) {
                    settings.data += '&mphb_discount_config=' + encodeURIComponent(JSON.stringify(discountConfig));
                }
            }
        });

        // After MotoPress updates rate prices in the chooser
        $(document).ajaxComplete(function(event, xhr, settings) {
            if (!settings.url) return;

            if (settings.url.includes('mphb_update_rate_prices')) {
                // Apply discount to all rate labels after new base prices are inserted
                setTimeout(applyDiscountToAllRateLabels, 0);
            }
        });
    }

    // Listen to MotoPress checkout data change event
    const checkoutFormEl = document.querySelector('.mphb_sc_checkout-form');
    if (checkoutFormEl) {
        checkoutFormEl.addEventListener('CheckoutDataChanged', function () {
            refreshCheckoutUI();
        });
    }
    
    // Initial applications
    (async () => {
        await applyDiscountsAndUrgency();  // search results decoration
    })();

    // Initial checkout refresh (if on checkout)
    setTimeout(refreshCheckoutUI, 200);
});