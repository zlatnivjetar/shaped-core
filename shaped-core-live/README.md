# Shaped Core - Refactored Architecture

## 🚀 Multi-Client Deployment

**NEW**: Shaped Core uses **MU-Plugin configuration** for secure multi-client deployments.

- 📝 **Setup Checklist**: [SETUP.md](SETUP.md)
- 🔒 **Security**: Each site has only its own configuration
- ⚡ **Updates**: Push code via Git without touching client configs

**Deprecated**: `clients/` folder approach (see [clients/README.md](clients/README.md))

---

## Directory Structure

```
shaped-core/
├── shaped-core.php              # Main plugin entry point
├── uninstall.php                # Cleanup on plugin deletion
│
├── admin/                       # Admin UI & controllers
│   ├── class-menu-controller.php    # Admin menu registration
│   ├── class-noise-control.php      # Suppress unnecessary admin notices
│   ├── class-reviews-dashboard.php  # Reviews admin interface
│   ├── class-role-manager.php       # Custom roles & capabilities
│   └── pages/                       # Admin page templates
│       ├── ops-availability.php
│       ├── ops-dashboard.php
│       ├── reviews-dashboard.php
│       └── system-dashboard.php
│
├── clients/                     # DEPRECATED: Use MU-plugin instead
│   ├── README.md                # Migration guide
│   └── _template/               # Legacy template (reference only)
│
├── config/                      # Configuration
│   ├── brand-helpers.php        # Brand/white-label helpers
│   └── defaults.php             # Default settings
│
├── core/                        # Core business logic
│   ├── class-pricing.php        # Discounts, seasons, admin columns
│   ├── class-payment-processor.php  # Stripe sessions, webhooks, charges
│   ├── class-booking-manager.php    # Booking lifecycle, cancellations
│   ├── email-handler.php        # Guest emails (confirmation, reservation, cancellation)
│   └── email-handler-admin.php  # Admin notification emails
│
├── docs/                        # Documentation
│   └── wp-config-additions.php  # Example wp-config constants
│
├── includes/                    # Infrastructure
│   ├── class-admin.php          # Admin functionality
│   ├── class-amenity-mapper.php # Amenity icon/label mapping
│   ├── class-assets.php         # Conditional CSS/JS loading
│   ├── class-brand-config.php   # Brand configuration loader
│   ├── class-loader.php         # PSR-4-ish autoloader
│   ├── class-setup-wizard.php   # Setup wizard & config health
│   ├── compat-functions.php     # Backward compatibility wrappers
│   ├── helpers.php              # Utility functions
│   ├── pricing-helpers.php      # Pricing calculation helpers
│   ├── email/
│   │   └── email-templates.php  # HTML email templates
│   └── pricing/                 # Pricing system
│       ├── init.php             # Pricing module bootstrap
│       ├── interface-pricing-provider.php
│       ├── class-shaped-pricing-service.php
│       ├── class-price-request.php
│       ├── class-price-result.php
│       ├── class-rest-api.php   # /wp-json/shaped/v1/price endpoint
│       ├── class-motopress-pricing-provider.php
│       ├── class-roomcloud-pricing-provider.php
│       └── class-official-prices-page.php
│
├── assets/                      # Frontend assets
│   ├── css/
│   │   ├── admin-pricing.css    # Pricing admin styles
│   │   ├── admin-setup-wizard.css
│   │   ├── checkout.css         # Checkout page styles
│   │   ├── cookie-banner.css    # GDPR cookie banner
│   │   ├── design-tokens.css    # CSS custom properties
│   │   ├── gallery-element.css  # Image gallery styles
│   │   ├── guest-reviews.css    # Review display styles
│   │   ├── modals.css           # Modal dialog styles
│   │   ├── search-calendar.css  # Calendar picker styles
│   │   ├── search-form.css      # Search form styles
│   │   └── search-results.css   # Search results styles
│   └── js/
│       ├── admin-setup-wizard.js
│       ├── calendar-fix.js      # MPHB calendar fixes
│       ├── checkout.js          # Pricing logic, availability, urgency badges
│       ├── language-switch-fade.js  # WPML/Polylang transitions
│       ├── leave-page-modal-popup.js
│       ├── modals.js            # Modal dialog functionality
│       └── provider-badge-stars.js
│
├── shortcodes/
│   ├── class-modal-link.php     # [shaped_modal_link]
│   ├── class-provider-badge.php # [shaped_provider_badge]
│   ├── room-cards.php           # [shaped_room_cards]
│   ├── room-details.php         # [shaped_room_details]
│   └── room-meta.php            # [shaped_meta key="..."]
│
├── schema/
│   └── markup.php               # JSON-LD structured data
│
├── templates/
│   ├── amenities-example.php    # Amenities display example
│   ├── checkout-modals.php      # Checkout modal content
│   ├── facilities-replacement.php
│   ├── manage-booking.php       # Guest self-service template
│   ├── modal-wrapper.php        # Reusable modal wrapper
│   ├── room-card-home.php       # Room card for homepage
│   └── room-card-listing.php    # Room card for listings
│
├── modules/                     # Optional modules
│   ├── reviews/                 # Reviews system
│   │   ├── module.php           # Module bootstrap
│   │   ├── assets.php           # Asset registration
│   │   ├── class-admin.php      # Reviews admin
│   │   ├── class-cpt.php        # Custom post type
│   │   ├── class-sync.php       # External review sync
│   │   ├── shortcodes.php       # Review shortcodes
│   │   └── assets/
│   │       ├── provider-badges.css
│   │       └── reviews.css
│   └── roomcloud/               # RoomCloud integration
│       ├── module.php           # Module bootstrap
│       ├── cli/
│       │   └── class-cli.php    # WP-CLI commands
│       ├── includes/
│       │   ├── class-admin-settings.php
│       │   ├── class-api.php
│       │   ├── class-availability-manager.php
│       │   ├── class-error-logger.php
│       │   ├── class-sync-manager.php
│       │   └── class-webhook-handler.php
│       └── templates/
│           └── admin-settings.php
│
├── mu-plugins/                  # Must-use plugins (copy to wp-content/mu-plugins)
│   └── shaped-no-session-on-price-api.php  # Disable sessions on price API
│
└── vendor/                      # Third-party libraries
    └── stripe-php/              # Stripe PHP SDK
        └── init.php
```

---

## Migration Steps

### 1. Backup Current Installation
```bash
# On server
cd /path/to/wp-content/plugins
cp -r shaped-core shaped-core-backup-$(date +%Y%m%d)
```

### 2. Replace Plugin Files
1. Delete all files in `shaped-core/` EXCEPT:
   - Any client-specific customizations
   - The `.git` folder if present
   
2. Copy all files from this refactored version into `shaped-core/`

### 3. Move Stripe SDK
```bash
# If Stripe SDK is in mu-plugins
mv /path/to/wp-content/mu-plugins/stripe-php /path/to/wp-content/plugins/shaped-core/vendor/
```

Or keep it in mu-plugins - the plugin checks both locations.

### 4. Run Setup Wizard (Recommended)

After activation, the Setup Wizard will launch automatically to configure:
1. **Stripe Credentials** - Secret key and webhook secret (with live API validation)
2. **Payment Mode** - Scheduled Charge or Deposit
3. **Room Discounts** - Per-room direct booking discounts
4. **Modal Pages** - Booking terms, privacy policy pages

Access anytime at: **Admin → Shaped Core → Config Health → Run Setup Wizard**

### 5. Update wp-config.php (Alternative)
For production, define constants directly (takes priority over wizard settings):
```php
// Stripe credentials (optional if using Setup Wizard)
define('SHAPED_STRIPE_SECRET', 'sk_live_xxx');
define('SHAPED_STRIPE_WEBHOOK', 'whsec_xxx');

// Optional: Enable modules
define('SHAPED_ENABLE_ROOMCLOUD', true);  // false by default
define('SHAPED_ENABLE_REVIEWS', true);    // true by default
```

**Note:** Constants in wp-config.php always take priority over database values from the Setup Wizard.

### 6. Verify After Migration
1. Deactivate and reactivate the plugin
2. Check for PHP errors in `wp-content/debug.log`
3. Test:
   - Pricing admin page loads
   - Search results display with correct pricing
   - Checkout flow works (create test booking)
   - Webhook endpoint responds

---

## Module System

### Enabling RoomCloud
1. Ensure `SHAPED_ENABLE_ROOMCLOUD` is `true` in wp-config.php
2. Create `modules/roomcloud/module.php` with the bootstrap code
3. Copy RoomCloud classes into `modules/roomcloud/includes/`

### Enabling Reviews
1. Ensure `SHAPED_ENABLE_REVIEWS` is `true` in wp-config.php
2. Create `modules/reviews/module.php`
3. Configure Supabase credentials

### Module Bootstrap Template
```php
<?php
// modules/example/module.php

if (!defined('ABSPATH')) exit;

// Dependencies check
if (!class_exists('Shaped_Pricing')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>Shaped Example: Shaped Core required.</p></div>';
    });
    return;
}

// Module constants
define('SHAPED_EXAMPLE_DIR', __DIR__ . '/');

// Load module classes
require_once SHAPED_EXAMPLE_DIR . 'includes/class-main.php';

// Initialize
add_action('init', function() {
    new Shaped_Example_Main();
}, 5);
```

---

## Client Customization

### Per-Client Configuration

**Option 1: Filters in theme's functions.php**
```php
// Change admin email
add_filter('shaped/admin_email', function() {
    return 'bookings@clientsite.com';
});

// Change property name
add_filter('shaped/property_name', function() {
    return 'Oceanview Apartments';
});

// Change pricing defaults
add_filter('shaped/pricing/discount_defaults', function($defaults) {
    return [
        'oceanview-suite'  => 15,
        'garden-room'      => 10,
        'penthouse'        => 20,
    ];
});
```

**Option 2: Database options**
Set discounts via Admin > Shaped Pricing page.

---

## Asset Loading

Assets load conditionally based on page context:

| Asset | Loads On |
|-------|----------|
| `checkout.css/js` | Checkout page, search results |
| `search-results.css` | Search results page |
| `search-form.css` | Pages with search form |
| `search-calendar.css` | Pages with date picker |
| `modals.css/js` | Pages with modal dialogs |
| `guest-reviews.css` | Pages displaying reviews |
| `gallery-element.css` | Pages with image galleries |
| `cookie-banner.css` | All frontend pages (GDPR) |
| `design-tokens.css` | All frontend pages (CSS variables) |
| `calendar-fix.js` | All pages (lightweight) |
| `language-switch-fade.js` | Only if WPML/Polylang active |
| `admin-pricing.css` | Admin pricing page |
| `admin-setup-wizard.css/js` | Setup wizard page |

---

## Troubleshooting

### Assets not loading
Check `class-assets.php` page detection methods. Add your page slug:
```php
private function is_checkout_page(): bool {
    if (is_page(['checkout', 'book', 'booking', 'your-custom-slug'])) {
        return true;
    }
    // ...
}
```

### ShapedConfig undefined in JS
Ensure jQuery is loaded before your custom scripts. The config is attached to jQuery.

### Email not sending
Check:
1. `SHAPED_ADMIN_EMAIL` constant in `email-handler-admin.php`
2. WordPress mail configuration
3. Error logs for `[Shaped Email]` entries

### Stripe webhook 400 errors
1. Verify `SHAPED_STRIPE_WEBHOOK` matches Stripe dashboard
2. Check webhook URL: `https://yoursite.com/wp-json/shaped/v1/stripe-webhook`
3. Review `[Shaped Webhook]` entries in error log

---

## File Changes from Original

| Original | New Location |
|----------|--------------|
| `shaped-stripe-checkout.php` | `shaped-core.php` (renamed, refactored) |
| `includes/class-shaped-pricing.php` | `core/class-pricing.php` |
| `includes/class-shaped-payment-processor.php` | `core/class-payment-processor.php` |
| `includes/class-shaped-booking-manager.php` | `core/class-booking-manager.php` |
| `shaped-email-handler.php` | `core/email-handler.php` |
| `shaped-admin-email-handler.php` | `core/email-handler-admin.php` |
| `includes/shortcodes.php` | `shortcodes/room-details.php` |
| `_scratch/php/room-meta-shortcode.php` | `shortcodes/room-meta.php` |
| `_scratch/css/*` | `assets/css/*` |
| `_scratch/js/*` | `assets/js/*` |
| `_scratch/php/schema-markup.php` | `schema/markup.php` |
| `_scratch/load.php` | Removed (replaced by `class-assets.php`) |

---

## Version History

- **2.2.0** - Admin & Pricing Enhancements
  - Admin dashboard with menu controller, role manager, noise control
  - Pricing service with provider abstraction (MotoPress, RoomCloud)
  - REST API for pricing (`/wp-json/shaped/v1/price`)
  - Client-specific legal templates system
  - Additional shortcodes: `[shaped_modal_link]`, `[shaped_provider_badge]`, `[shaped_room_cards]`
  - Room card templates for homepage and listings
  - Reviews module with external sync, CPT, admin dashboard
  - RoomCloud module with CLI commands, availability manager, webhooks
  - MU-plugin for session optimization on price API
  - Design tokens CSS for consistent styling

- **2.1.0** - Setup Wizard & Configuration
  - Setup Wizard for quick client configuration
  - Config Health dashboard showing configuration status
  - Stripe credentials can be stored in database (encrypted)
  - Configurable scheduled charge threshold (0-60 days)
  - Helper functions `shaped_get_stripe_secret()` and `shaped_get_stripe_webhook()`

- **2.0.0** - Refactored architecture
  - Single entry point (`shaped-core.php`)
  - Modular structure with autoloader
  - Conditional asset loading
  - Module system for RoomCloud/Reviews
  - Proper constant definitions

- **3.1** (Original) - Legacy monolithic structure
