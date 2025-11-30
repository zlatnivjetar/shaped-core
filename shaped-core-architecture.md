shaped-core/
├── shaped-core.php                     # Plugin header + bootstrap
├── uninstall.php                       # Cleanup on uninstall
│
├── config/
│   └── defaults.php                    # Default pricing, URLs, etc.
│
├── includes/
│   ├── class-loader.php                # PSR-4-ish autoloader
│   ├── class-assets.php                # Conditional CSS/JS enqueuing
│   ├── class-admin.php                 # Main settings page + modal page selectors
│   └── helpers.php                     # Utility functions (shaped_get_option, etc.)
│
├── core/
│   ├── class-pricing.php               # Discounts, seasons, admin columns
│   ├── class-payment-processor.php     # Stripe sessions, webhooks, charges
│   ├── class-booking-manager.php       # Booking lifecycle, cancellations
│   └── class-email-handler.php         # Consolidated guest + admin emails
│
├── modules/
│   ├── roomcloud/
│   │   ├── module.php                  # Bootstrap: checks deps, inits classes
│   │   ├── includes/
│   │   │   ├── class-api.php
│   │   │   ├── class-sync-manager.php
│   │   │   ├── class-webhook-handler.php
│   │   │   ├── class-availability-manager.php
│   │   │   ├── class-admin-settings.php
│   │   │   └── class-error-logger.php
│   │   ├── cli/
│   │   │   └── class-cli.php
│   │   └── templates/
│   │       └── admin-settings.php
│   │
│   └── reviews/
│       ├── module.php                  # Bootstrap
│       ├── includes/
│       │   ├── class-sync.php          # Supabase sync
│       │   ├── class-display.php       # Rating display, badges
│       │   └── class-admin.php         # CPT, taxonomy, admin UI
│       └── assets/
│           └── reviews.css
│
├── shortcodes/
│   ├── class-room-meta.php             # [shaped_meta key="..."]
│   ├── class-room-details.php          # [shaped_room_details]
│   ├── class-provider-badge.php        # [shaped_provider_badge provider="booking" rating="9.2"]
│   └── class-modal-link.php            # [shaped_modal page="terms" label="Terms & Conditions"]
│
├── templates/
│   ├── room-card-home.php              # Home page room cards
│   ├── room-card-listing.php           # Rooms page cards
│   ├── manage-booking.php              # Guest self-service page
│   └── modal-wrapper.php               # AJAX modal container
│
├── schema/
│   └── class-markup.php                # JSON-LD structured data
│
├── assets/
│   ├── css/
│   │   ├── checkout.css
│   │   ├── search-results.css
│   │   └── modals.css
│   │   └── search-calendar.css
│   │   └── search-form.css
│   └── js/
│       ├── checkout.js                 # search-checkout-logic.js (renamed for clarity)
│       ├── calendar-fix.js
│       ├── home-room-cards.js
│       ├── language-switch-fade.js
│       ├── modals.js                   # AJAX modal loader
│       └── provider-badge-stars.js
│
└── vendor/
    └── stripe-php/                     # Stripe SDK
        └── init.php