# Shaped Core - Refactored Architecture

## Directory Structure

```
shaped-core/
├── shaped-core.php              # Main plugin entry point
│
├── core/                        # Core business logic
│   ├── class-pricing.php        # Discounts, seasons, admin columns
│   ├── class-payment-processor.php  # Stripe sessions, webhooks, charges
│   ├── class-booking-manager.php    # Booking lifecycle, cancellations
│   ├── email-handler.php        # Guest emails (confirmation, reservation, cancellation)
│   └── email-handler-admin.php  # Admin notification emails
│
├── includes/                    # Infrastructure
│   ├── class-loader.php         # PSR-4-ish autoloader
│   ├── class-assets.php         # Conditional CSS/JS loading
│   ├── helpers.php              # Utility functions
│   └── compat-functions.php     # Backward compatibility wrappers
│
├── assets/                      # Frontend assets
│   ├── css/
│   │   ├── checkout.css         # Checkout page styles
│   │   └── search-results.css   # Search results styles
│   └── js/
│       ├── checkout.js          # Pricing logic, availability, urgency badges
│       ├── calendar-fix.js      # MPHB calendar fixes
│       ├── language-switch-fade.js  # WPML/Polylang transitions
│       ├── leave-page-modal-popup.js
│       └── provider-badge-stars.js
│
├── shortcodes/
│   ├── room-details.php         # [shaped_room_details]
│   └── room-meta.php            # [shaped_meta key="..."]
│
├── schema/
│   └── markup.php               # JSON-LD structured data
│
├── templates/
│   └── manage-booking.php       # Guest self-service template
│
├── modules/                     # Optional modules (add when needed)
│   ├── roomcloud/               # RoomCloud integration
│   │   └── module.php           # Module bootstrap
│   └── reviews/                 # Reviews system
│       └── module.php           # Module bootstrap
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

### 4. Update wp-config.php (if needed)
Ensure these constants are set:
```php
// Required: Stripe credentials
define('SHAPED_STRIPE_SECRET', 'sk_live_xxx');
define('SHAPED_STRIPE_WEBHOOK', 'whsec_xxx');

// Optional: Enable modules
define('SHAPED_ENABLE_ROOMCLOUD', true);  // false by default
define('SHAPED_ENABLE_REVIEWS', true);    // false by default
```

### 5. Verify After Migration
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
| `calendar-fix.js` | All pages (lightweight) |
| `language-switch-fade.js` | Only if WPML/Polylang active |

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

- **2.0.0** - Refactored architecture
  - Single entry point (`shaped-core.php`)
  - Modular structure with autoloader
  - Conditional asset loading
  - Module system for RoomCloud/Reviews
  - Proper constant definitions

- **3.1** (Original) - Legacy monolithic structure
