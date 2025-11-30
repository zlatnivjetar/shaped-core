// =====  DELETE THIS  =====
// Rooms page discount logic
document.addEventListener('DOMContentLoaded', function() {
    
    let discountConfig = ShapedConfig.discounts || {};
		let SEASON_PRICES = ShapedConfig.seasonPrices || {};
    
    
    // Get current season based on date
    function getCurrentSeason() {
        const month = new Date().getMonth() + 1; // 1-12
        
        if (month === 7 || month === 8) return 'high';  // July-August
        if (month === 6 || month === 9) return 'mid';   // June & September
        return 'low'; // October-May
    }
    
    // Extract room slug from element or URL
    function getRoomSlug(element) {
        // Check for room type link within element
        const roomLink = element.closest('.mphb-room-type').querySelector('a.mphb-room-type-title');
        if (roomLink) {
            const href = roomLink.getAttribute('href');
            const match = href.match(/accommodation\/([^\/]+)/);
            if (match) return match[1];
        }
        
        // Fallback to page URL for single room pages
        const urlMatch = window.location.pathname.match(/accommodation\/([^\/]+)/);
        if (urlMatch) return urlMatch[1];
        
        return null;
    }
    
    // Update price display for a single element
    function updatePriceDisplay(element, roomSlug) {
        const season = getCurrentSeason();
        const prices = SEASON_PRICES[roomSlug];
        
        if (!prices) return;
        
        const basePrice = prices[season];
        const discountPercent = discountConfig[roomSlug] || 0;
        const discountedPrice = discountPercent 
            ? Math.round(basePrice * (1 - discountPercent / 100))
            : basePrice;
        
        // Update original price (if discount exists)
        const originalEl = element.querySelector('.mphb-price-original');
        if (discountPercent && originalEl) {
            originalEl.innerHTML = `<span class="mphb-currency">€</span>${basePrice}`;
            originalEl.style.display = 'inline-block';
        } else if (originalEl) {
            originalEl.style.display = 'none';
        }
        
        // Update current price
        const currentEl = element.querySelector('.mphb-price-current');
        if (currentEl) {
            currentEl.innerHTML = `<span class="mphb-currency">€</span>${discountedPrice}`;
        }
        
        // Update discount badge
        const badgeEl = element.querySelector('.mphb-discount-badge');
        if (discountPercent && badgeEl) {
            badgeEl.textContent = `${discountPercent}% off`;
            badgeEl.style.display = 'inline-block';
        } else if (badgeEl) {
            badgeEl.style.display = 'none';
        }
    }
    
    // Initialize: Update all price displays on page
    function init() {
        const priceWrappers = document.querySelectorAll('.mphb-price-discount-wrapper');
        
        priceWrappers.forEach(wrapper => {
            const roomSlug = getRoomSlug(wrapper);
            if (roomSlug) {
                updatePriceDisplay(wrapper, roomSlug);
            }
        });
    }
    
    init();
});
