// ===== RENDER STAR RATINGS FOR PROVIDER BADGES =====
    function renderStarRating(rating, container) {
    const fullStars = Math.floor(rating);
    const partialStar = rating - fullStars;
    const emptyStars = 5 - Math.ceil(rating);
    
    let starsHTML = '';
    
    // Full stars
    for (let i = 0; i < fullStars; i++) {
        starsHTML += '<span class="star full">★</span>';
    }
    
    // Partial star
    if (partialStar > 0) {
        const percentage = Math.round(partialStar * 100);
        starsHTML += `<span class="star partial" style="--fill: ${percentage}%">★</span>`;
    }
    
    // Empty stars
    for (let i = 0; i < emptyStars; i++) {
        starsHTML += '<span class="star empty">★</span>';
    }
    
    container.querySelector('.stars-container').innerHTML = starsHTML;
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    const ratingContainers = document.querySelectorAll('.star-rating');
    ratingContainers.forEach(container => {
        const rating = parseFloat(container.dataset.rating);
        if (rating) {
            renderStarRating(rating, container);
        }
    });
});
// ===== END STAR RATINGS FOR PROVIDER BADGES =====