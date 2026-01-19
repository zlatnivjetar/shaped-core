// ===== HTML + JS FROM THE LEAVING CONFIRMATION MODAL ===== //
function showLeavingConfirmation(destination, url, isProvider = false) {
   // Create modal overlay
   const modal = document.createElement('div');
   modal.style.cssText = `
    box-shadow: none !important;
       position: fixed;
       top: 0;
       left: 0;
       width: 100%;
       height: 100%;
       background: var(--color-overlay-scrim, rgba(0,0,0,0.5));
       display: flex;
       align-items: center;
       justify-content: center;
       z-index: 10000;
   `;
   
   // Determine title and description based on type
   const title = isProvider 
       ? `Leaving to ${destination}'s website?`
       : `Explore ${destination}?`;
   
   const description = isProvider
       ? `You'll be redirected to ${destination} to read more reviews about our property.`
       : `You'll leave our website to learn more about this attraction.`;
   
   // Create modal content
   modal.innerHTML = `
       <div style="
        box-shadow: none !important;
           background: var(--color-surface-page, #FBFAF9);
           padding: 2rem;
           border-radius: var(--radius-md, 8px);
           max-width: 400px;
           margin: 1rem;
           text-align: center;
           font-family: var(--font-body, 'DM Sans', sans-serif);
       " class="modal-content">
           <h3 style="color: var(--color-text-primary, #141310); margin-bottom: 1rem;">
              ${title}
           </h3>
           <p style="color: var(--color-text-muted, #666666); margin-bottom: 1.5rem;">
               ${description}
           </p>
           <div style="display: flex; gap: 1rem; justify-content: center;" class="button-container">
               <button onclick="this.closest('[data-modal]').remove()" style="
                   padding: 0.875rem 1.875rem;
                   border: 1px solid var(--color-border-default, #e0e0e0);
                   background: var(--color-brand-primary, #2563EB);
                   color: var(--color-text-inverse, white);
                   border-radius: var(--radius-md, 8px);
                   cursor: pointer;
                   font-family: inherit;
                   transition: all 0.3s ease;
                   font-size: 1rem;
               " onmouseenter="this.style.transform='translateY(-2px)';this.style.background='#c39937'; this.style.boxShadow='0 0 4px rgba(209, 175, 93, 0.6), 0 0 8px rgba(209, 175, 93, 0.45), 0 0 16px rgba(209, 175, 93, 0.3)'; this.style.borderColor='#d1af5d';" onmouseleave="this.style.transform='none'; this.style.boxShadow='none'; this.style.borderColor='#e0e0e0'; this.style.background='#d1af5d';" class="modal-button">Stay Here</button>

               <button onclick="window.open('${url}', '_blank'); this.closest('[data-modal]').remove();" style="
                   padding: 0.875rem 1.875rem;
                   border: 1px solid var(--color-border-default, #e0e0e0);
                   background: var(--color-surface-page, #fbfaf9);
                   color: var(--color-text-primary, #141310);
                   border-radius: var(--radius-md, 8px);
                   cursor: pointer;
                   font-family: inherit;
                   font-weight: 600;
                   transition: all 0.3s ease;
                   font-size: 1rem;
               " onmouseenter="this.style.transform='translateY(-2px)';this.style.background='#c39937'; this.style.color='#fff'; this.style.boxShadow='0 0 4px rgba(209, 175, 93, 0.6), 0 0 8px rgba(209, 175, 93, 0.45), 0 0 16px rgba(209, 175, 93, 0.3)'; this.style.borderColor='#d1af5d';" onmouseleave="this.style.transform='none'; this.style.color='#141310'; this.style.background='#fbfaf9'; this.style.boxShadow='none'; this.style.borderColor='#e0e0e0';" class="modal-button">Continue</button>
           </div>
       </div>
       <style>
           @media (max-width: 479px) {
               .modal-content {
                   padding: 1.5rem 1rem !important;
               }
               .button-container {
                   flex-direction: column !important;
                   width: 100%;
               }
               .modal-button {
                   width: 100%;
               }
           }
       </style>
   `;
   
   modal.setAttribute('data-modal', 'true');
   document.body.appendChild(modal);
}

// Attach to links
document.addEventListener('DOMContentLoaded', function() {
   // Things to Do links (existing functionality)
   const thingsToDoLinks = document.querySelectorAll('.elementor-price-list a');
   
   thingsToDoLinks.forEach(function(link) {
       link.addEventListener('click', function(e) {
           e.preventDefault();
           
           // Extract only the title part (everything before the first number/distance)
           const fullText = this.textContent.trim();
           const destination = fullText.split(/\d/)[0].trim();
           
           const url = this.getAttribute('href');
           showLeavingConfirmation(destination, url, false);
       });
   });
   
   // Provider badge links (new functionality)
   const providerBadges = document.querySelectorAll('.prs-provider-badge');
   
   providerBadges.forEach(function(badge) {
       // Check if badge is wrapped in a link
       const parentLink = badge.closest('a');
       const clickTarget = parentLink || badge;
       
       clickTarget.addEventListener('click', function(e) {
           e.preventDefault();
           
           const providerName = badge.textContent.trim();
           const url = parentLink ? parentLink.getAttribute('href') : badge.getAttribute('data-href') || '#';
           
           showLeavingConfirmation(providerName, url, true);
       });
       
       // Make badge look clickable
       clickTarget.style.cursor = 'pointer';
   });
});
