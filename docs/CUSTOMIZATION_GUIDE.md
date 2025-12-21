# Customization Guide

> **Last generated:** 2025-12-21
> **Related entries:** [CLAUDE.md#IMPL-001](CLAUDE.md#2025-12-08-impl-001--initial-plugin-architecture), [CLAUDE.md#IMPL-002](CLAUDE.md#2025-12-21-impl-002--setup-wizard--config-health)

How to extend shaped-core without editing core files.

---

## Table of Contents

1. [Quick Setup (Setup Wizard)](#quick-setup-setup-wizard)
2. [Philosophy](#philosophy)
3. [Using Hooks](#using-hooks)
   - [Filter Hooks](#filter-hooks)
   - [Action Hooks](#action-hooks)
4. [Common Customizations](#common-customizations)
   - [Pricing Customization](#pricing-customization)
   - [Email Customization](#email-customization)
   - [Payment Customization](#payment-customization)
   - [UI Customization](#ui-customization)
5. [Creating a Child Plugin](#creating-a-child-plugin)
6. [Client-Specific Settings](#client-specific-settings)
7. [Module Configuration](#module-configuration)

---

## Quick Setup (Setup Wizard)

For new client deployments, use the **Setup Wizard** for quick configuration:

1. **Automatic Launch:** Wizard auto-launches on plugin activation
2. **Manual Access:** Admin → Shaped Core → Config Health → Run Setup Wizard

### What the Wizard Configures

| Step | Settings |
|------|----------|
| Stripe Credentials | Secret key, webhook secret (with live validation) |
| Payment Mode | Scheduled Charge vs Deposit, threshold days |
| Room Discounts | Per-room direct booking discount percentages |
| Modal Pages | Booking terms, privacy policy page assignments |

### Configuration Health Dashboard

After setup, check configuration status at **Admin → Shaped Core → Config Health**:

- Green/red status for all critical settings
- Quick links to fix issues
- Environment information display

### Credential Priority

Stripe credentials can come from multiple sources (in priority order):

1. **Constants** in `wp-config.php` (highest priority)
2. **Environment variables** (`STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET`)
3. **Database** via Setup Wizard (encrypted storage)

```php
// Get credentials programmatically (respects priority)
$secret = shaped_get_stripe_secret();
$webhook = shaped_get_stripe_webhook();
```

---

## Philosophy

shaped-core is designed for **extensibility without core edits**:

1. **Never edit core files** — Changes will be lost on updates
2. **Use hooks** — The plugin provides hooks at key decision points
3. **Child plugins** — Create property-specific customization plugins
4. **Configuration constants** — Use wp-config.php for environment settings

### Where to Put Customizations

| Type | Location |
|------|----------|
| Single property | `wp-content/mu-plugins/property-customizations.php` |
| Multi-property | `wp-content/plugins/shaped-property-name/` |
| Theme-specific | `functions.php` (not recommended) |

---

## Using Hooks

### Filter Hooks

Filters **modify data** before it's used. Return the modified value.

**Pattern:**
```php
add_filter('hook_name', function($value, $arg2) {
    // Modify $value
    return $value;
}, 10, 2);  // priority, number of args
```

**Example: Modify discount defaults**
```php
add_filter('shaped/pricing/discount_defaults', function($defaults) {
    // Set 15% discount for all rooms
    foreach ($defaults as $slug => $value) {
        $defaults[$slug] = 15;
    }
    return $defaults;
}, 10, 1);
```

### Action Hooks

Actions **react to events**. No return value needed.

**Pattern:**
```php
add_action('hook_name', function($arg1, $arg2) {
    // Do something
}, 10, 2);  // priority, number of args
```

**Example: React to payment completion**
```php
add_action('shaped_payment_completed', function($booking_id, $mode) {
    // Log to external system
    error_log("Payment completed: #$booking_id (mode: $mode)");

    // Send to CRM
    crm_api_update_booking($booking_id, 'paid');
}, 10, 2);
```

---

## Common Customizations

### Pricing Customization

#### Set Default Discounts

```php
// mu-plugins/shaped-pricing.php

add_filter('shaped/pricing/discount_defaults', function($defaults) {
    return [
        'studio-apartment' => 15,   // 15% off
        'deluxe-suite' => 10,       // 10% off
        'penthouse' => 5,           // 5% off
    ];
}, 10, 1);
```

#### Exclude Room Types from Discounts

```php
add_filter('shaped/pricing/room_slugs', function($slugs) {
    // Remove staff room from discount options
    return array_filter($slugs, function($slug) {
        return $slug !== 'staff-room';
    });
}, 10, 1);
```

#### Custom Commission Calculation

If you add a commission hook (see [MAINTENANCE.md](MAINTENANCE.md)), you could use it like:

```php
add_filter('shaped_commission_calculation', function($commission, $booking) {
    // Reduce commission for specific properties
    $property_id = get_post_meta($booking->ID, '_property_id', true);

    if ($property_id === 123) {
        return $commission * 0.85;  // 15% reduction
    }

    return $commission;
}, 10, 2);
```

---

### Email Customization

#### Override Admin Email

```php
add_filter('shaped/admin_email', function($email) {
    return 'bookings@myproperty.com';
}, 10, 1);
```

#### Override Property Name

```php
add_filter('shaped/property_name', function($name) {
    return 'Preelook Luxury Apartments';
}, 10, 1);
```

#### Override Property Email

```php
add_filter('shaped/property_email', function($email) {
    return 'info@preelook.com';
}, 10, 1);
```

#### Custom Email Actions

```php
add_action('shaped_payment_completed', function($booking_id, $mode) {
    // Send additional email to housekeeping
    $booking = get_post($booking_id);
    $checkin = get_post_meta($booking_id, '_mphb_check_in_date', true);

    wp_mail(
        'housekeeping@property.com',
        'New Booking - Prepare Room',
        "Booking #$booking_id check-in: $checkin"
    );
}, 20, 2);  // Priority 20 = after default emails
```

---

### Payment Customization

#### React to Payment Events

```php
// After any payment completes
add_action('shaped_payment_completed', function($booking_id, $mode) {
    // Sync to channel manager
    if (function_exists('roomcloud_sync_booking')) {
        roomcloud_sync_booking($booking_id);
    }

    // Update CRM
    $customer_email = get_post_meta($booking_id, '_mphb_email', true);
    crm_update_customer($customer_email, ['status' => 'paid']);
}, 10, 2);

// Specifically after deposit payment
add_action('shaped_deposit_paid', function($booking_id, $deposit, $balance) {
    // Log deposit details
    error_log(sprintf(
        'Deposit received: #%d - €%.2f paid, €%.2f balance',
        $booking_id, $deposit, $balance
    ));
}, 10, 3);

// After booking cancellation
add_action('shaped_booking_cancelled', function($booking_id) {
    // Sync cancellation externally
    external_api_cancel($booking_id);

    // Update availability
    refresh_room_availability($booking_id);
}, 10, 1);
```

#### Custom Stripe SDK Path

```php
add_filter('shaped/stripe_sdk_path', function($path) {
    // Use Stripe from mu-plugins
    return WPMU_PLUGIN_DIR . '/stripe-php/init.php';
}, 10, 1);
```

---

### UI Customization

#### Add Custom Modal Types

```php
add_filter('shaped/admin/modal_types', function($types) {
    $types['house-rules'] = 'House Rules';
    $types['cancellation-policy'] = 'Cancellation Policy';
    $types['covid-policy'] = 'COVID-19 Policy';
    return $types;
}, 10, 1);
```

Then use in templates:
```php
[shaped_modal page="house-rules" label="View House Rules"]
```

#### Add Custom Provider Badges

```php
add_filter('shaped/provider_badge/providers', function($providers) {
    $providers['vrbo'] = [
        'color' => '#3B5998',
        'label' => 'Vrbo',
        'max_rating' => 5
    ];
    $providers['hostelworld'] = [
        'color' => '#F47920',
        'label' => 'Hostelworld',
        'max_rating' => 10
    ];
    return $providers;
}, 10, 1);
```

#### Add Custom Amenity Icons

```php
add_filter('shaped/amenities/registry', function($registry) {
    $registry['ev-charging'] = [
        'icon' => 'ph-lightning',
        'label' => 'EV Charging Station'
    ];
    $registry['coworking'] = [
        'icon' => 'ph-desktop',
        'label' => 'Co-working Space'
    ];
    return $registry;
}, 10, 1);
```

#### Set Review Provider Links

```php
add_filter('shaped/reviews/provider_links', function($links) {
    return [
        'booking' => 'https://booking.com/hotel/hr/preelook-apartments.html',
        'tripadvisor' => 'https://tripadvisor.com/Hotel_Review-Preelook.html',
        'google' => 'https://g.page/preelook-apartments',
    ];
}, 10, 1);
```

---

## Creating a Child Plugin

For complex customizations, create a dedicated plugin:

### File Structure

```
wp-content/plugins/shaped-preelook/
├── shaped-preelook.php    # Main plugin file
├── includes/
│   ├── pricing.php        # Pricing customizations
│   ├── emails.php         # Email customizations
│   └── integrations.php   # External integrations
└── README.md
```

### Main Plugin File

```php
<?php
/**
 * Plugin Name: Shaped Core - Preelook Customizations
 * Description: Property-specific customizations for Preelook Apartments
 * Version: 1.0.0
 * Requires Plugins: shaped-core
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Define constants
define('SHAPED_PREELOOK_VERSION', '1.0.0');
define('SHAPED_PREELOOK_DIR', plugin_dir_path(__FILE__));

// Load after shaped-core
add_action('plugins_loaded', function() {
    // Check shaped-core is active
    if (!defined('SHAPED_VERSION')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>Shaped Preelook requires Shaped Core plugin.</p></div>';
        });
        return;
    }

    // Load customizations
    require_once SHAPED_PREELOOK_DIR . 'includes/pricing.php';
    require_once SHAPED_PREELOOK_DIR . 'includes/emails.php';
    require_once SHAPED_PREELOOK_DIR . 'includes/integrations.php';
}, 25);  // Priority 25 = after shaped-core (20)
```

### Pricing Customizations (`includes/pricing.php`)

```php
<?php
// Property-specific discount structure
add_filter('shaped/pricing/discount_defaults', function($defaults) {
    return [
        'studio-sea-view' => 12,
        'one-bedroom-city' => 10,
        'penthouse-suite' => 8,
    ];
});

// Custom commission logic
add_filter('shaped_commission_calculation', function($commission, $booking) {
    // Preelook gets reduced commission
    return $commission * 0.80;  // 20% reduction
}, 10, 2);
```

### Email Customizations (`includes/emails.php`)

```php
<?php
// Override sender details
add_filter('shaped/admin_email', fn() => 'reservations@preelook.com');
add_filter('shaped/property_name', fn() => 'Preelook Luxury Apartments');
add_filter('shaped/property_email', fn() => 'info@preelook.com');

// Additional notification on booking
add_action('shaped_payment_completed', function($booking_id, $mode) {
    // Notify property manager
    wp_mail(
        'manager@preelook.com',
        'New Booking Received',
        "Booking #$booking_id has been confirmed."
    );
}, 15, 2);
```

### External Integrations (`includes/integrations.php`)

```php
<?php
// Sync to external CRM
add_action('shaped_payment_completed', function($booking_id) {
    $booking = get_post($booking_id);

    wp_remote_post('https://crm.preelook.com/api/bookings', [
        'body' => json_encode([
            'booking_id' => $booking_id,
            'customer' => get_post_meta($booking_id, '_mphb_email', true),
            'dates' => [
                'checkin' => get_post_meta($booking_id, '_mphb_check_in_date', true),
                'checkout' => get_post_meta($booking_id, '_mphb_check_out_date', true),
            ],
        ]),
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . PREELOOK_CRM_TOKEN,
        ],
    ]);
}, 10, 1);
```

---

## Client-Specific Settings

### Setup Wizard (Recommended)

For most settings, use the **Setup Wizard** which provides a guided interface:

| Setting | Wizard Step | Admin Page |
|---------|-------------|------------|
| Stripe keys | Step 1 | N/A (wizard only) |
| Payment mode | Step 2 | Shaped Pricing |
| Room discounts | Step 3 | Shaped Pricing |
| Modal pages | Step 4 | Shaped Core |

Access: Admin → Shaped Core → Config Health → Run Setup Wizard

### Using wp-config.php

For production deployments or when constants are preferred:

```php
// Shaped Core settings
define('SHAPED_ENABLE_ROOMCLOUD', true);
define('SHAPED_ENABLE_REVIEWS', true);

// Stripe credentials (optional if using Setup Wizard)
// Constants take priority over wizard database storage
define('SHAPED_STRIPE_SECRET', 'sk_live_...');
define('SHAPED_STRIPE_WEBHOOK', 'whsec_...');

// URLs
define('SHAPED_SUCCESS_URL', 'https://preelook.com/thank-you/?booking_id={BOOKING_ID}');
define('SHAPED_CANCEL_URL', 'https://preelook.com/checkout');

// RoomCloud (if enabled)
define('ROOMCLOUD_SERVICE_URL', 'https://api.roomcloud.net/...');
define('ROOMCLOUD_USERNAME', 'preelook');
define('ROOMCLOUD_PASSWORD', '...');
define('ROOMCLOUD_HOTEL_ID', '9335');

// Reviews (if enabled)
define('SUPABASE_URL', 'https://xxx.supabase.co');
define('SUPABASE_SERVICE_KEY', '...');
```

### WordPress Options

Some settings are stored as WordPress options (configurable via Setup Wizard or admin pages):

```php
// Get/set discounts
$discounts = get_option('shaped_discounts', []);
update_option('shaped_discounts', ['room-slug' => 15]);

// Get/set payment mode
$mode = get_option('shaped_payment_mode', 'scheduled');
update_option('shaped_payment_mode', 'deposit');

// Get/set deposit percentage
$percent = get_option('shaped_deposit_percent', 30);
update_option('shaped_deposit_percent', 25);

// Get/set scheduled charge threshold (days)
$threshold = get_option('shaped_scheduled_charge_threshold', 7);
update_option('shaped_scheduled_charge_threshold', 14);
```

### Helper Functions

Use these functions to get credentials that respect the priority chain:

```php
// Get Stripe secret key (constant > env > database)
$secret = shaped_get_stripe_secret();

// Get Stripe webhook secret (constant > env > database)
$webhook = shaped_get_stripe_webhook();
```

---

## Module Configuration

### Enabling/Disabling Modules

```php
// wp-config.php

// Enable RoomCloud channel manager
define('SHAPED_ENABLE_ROOMCLOUD', true);

// Enable reviews aggregation
define('SHAPED_ENABLE_REVIEWS', true);
```

### Module Activation Hooks

```php
// React to RoomCloud activation
add_action('shaped_activate_module_roomcloud', function() {
    // Custom setup for RoomCloud
    update_option('my_roomcloud_enabled', true);
});

// React to Reviews activation
add_action('shaped_activate_module_reviews', function() {
    // Custom setup for Reviews
    flush_rewrite_rules();
});
```

---

## Customization Checklist

When customizing for a new property:

**Initial Setup (use Setup Wizard):**
- [ ] Run Setup Wizard (auto-launches on activation)
- [ ] Configure Stripe credentials
- [ ] Set payment mode and threshold
- [ ] Configure room discounts
- [ ] Assign modal pages
- [ ] Verify at Config Health dashboard

**Advanced Customization:**
- [ ] Create child plugin or mu-plugin file (if needed)
- [ ] Set up additional pricing discounts via filter
- [ ] Configure email sender details
- [ ] Set up external integrations (CRM, analytics)
- [ ] Configure wp-config.php constants (for production)
- [ ] Add custom modal types if needed
- [ ] Configure review provider links
- [ ] Test payment flow end-to-end
- [ ] Document customizations in [clients/](clients/) folder

---

## Next Steps

- **[HOOKS_REFERENCE.md](HOOKS_REFERENCE.md)** — Complete hook reference
- **[CORE_MODULES.md](CORE_MODULES.md)** — Understanding core classes
- **[DEBUGGING.md](DEBUGGING.md)** — Troubleshooting customizations
- **[MAINTENANCE.md](MAINTENANCE.md)** — Adding new features
