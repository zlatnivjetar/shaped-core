# Hooks Reference

> **Last generated:** 2025-12-08
> **Related entry:** [CLAUDE.md#IMPL-001](CLAUDE.md#2025-12-08-impl-001--initial-plugin-architecture)

Complete reference for all hooks (actions and filters) in shaped-core.

---

## Table of Contents

1. [Overview](#overview)
2. [Filter Hooks](#filter-hooks)
   - [Core Filters](#core-filters)
   - [Pricing Filters](#pricing-filters)
   - [Admin Filters](#admin-filters)
   - [Reviews Module Filters](#reviews-module-filters)
   - [Amenity Filters](#amenity-filters)
3. [Action Hooks](#action-hooks)
   - [Payment Actions](#payment-actions)
   - [Booking Actions](#booking-actions)
   - [Module Actions](#module-actions)
   - [Scheduled Actions](#scheduled-actions)
4. [Hook Index](#hook-index)

---

## Overview

shaped-core provides hooks at key extension points to allow customization without editing core files.

### Naming Convention

- **Filters:** `shaped/category/hook_name` (forward-slash namespace)
- **Actions:** `shaped_hook_name` (underscore separated)

### Stability

| Label | Meaning |
|-------|---------|
| **Stable** | Safe to use, will be maintained |
| **Internal** | May change without notice |

---

## Filter Hooks

### Core Filters

---

### shaped/stripe_sdk_path

**Hook Type:** Filter
**Stability:** Stable
**Location:** `shaped-core.php:79`
**Priority:** 10 (default)

**Arguments:**
- `$default_path` (string) — Default path to Stripe SDK

**Returns:** Modified path to Stripe SDK

**Description:** Override the path to the Stripe PHP SDK. Useful if you have Stripe installed in mu-plugins or a different location.

**Usage:**
```php
add_filter('shaped/stripe_sdk_path', function($default_path) {
    return WPMU_PLUGIN_DIR . '/stripe-php/init.php';
}, 10, 1);
```

**See Also:** [ARCHITECTURE_GUIDE.md#bootstrap-flow](ARCHITECTURE_GUIDE.md#bootstrap-flow)

**Added:** 2025-12-08 (IMPL-001)

---

### shaped/admin_email

**Hook Type:** Filter
**Stability:** Stable
**Location:** `includes/helpers.php:24`, `modules/reviews/class-sync.php:466`
**Priority:** 10 (default)

**Arguments:**
- `$admin_email` (string) — Default WordPress admin email

**Returns:** Modified admin email address

**Description:** Override the admin email used for notifications. Useful for properties with a different admin contact.

**Usage:**
```php
add_filter('shaped/admin_email', function($email) {
    return 'bookings@myproperty.com';
}, 10, 1);
```

**See Also:** [CORE_MODULES.md#email-handlers](CORE_MODULES.md#email-handlers)

**Added:** 2025-12-08 (IMPL-001)

---

### shaped/property_name

**Hook Type:** Filter
**Stability:** Stable
**Location:** `includes/helpers.php:31`, `modules/reviews/class-sync.php:467`
**Priority:** 10 (default)

**Arguments:**
- `$property_name` (string) — Default WordPress blog name

**Returns:** Modified property name

**Description:** Override the property name used in emails and UI. Useful when the WordPress site name differs from the property name.

**Usage:**
```php
add_filter('shaped/property_name', function($name) {
    return 'Preelook Luxury Apartments';
}, 10, 1);
```

**See Also:** [CORE_MODULES.md#email-handlers](CORE_MODULES.md#email-handlers)

**Added:** 2025-12-08 (IMPL-001)

---

### shaped/property_email

**Hook Type:** Filter
**Stability:** Stable
**Location:** `includes/helpers.php:39`
**Priority:** 10 (default)

**Arguments:**
- `$default_email` (string) — Default property email

**Returns:** Modified property email address

**Description:** Override the property email used as the sender for guest communications.

**Usage:**
```php
add_filter('shaped/property_email', function($email) {
    return 'info@preelook.com';
}, 10, 1);
```

**See Also:** [CORE_MODULES.md#email-handlers](CORE_MODULES.md#email-handlers)

**Added:** 2025-12-08 (IMPL-001)

---

### Pricing Filters

---

### shaped/pricing/room_slugs

**Hook Type:** Filter
**Stability:** Stable
**Location:** `core/class-pricing.php:51`
**Priority:** 10 (default)

**Arguments:**
- `$room_slugs` (array) — Array of room type slugs

**Returns:** Modified array of room slugs

**Description:** Filter the list of room type slugs available for discount configuration. Useful for excluding certain room types from the discount system.

**Usage:**
```php
add_filter('shaped/pricing/room_slugs', function($slugs) {
    // Remove 'staff-room' from discount options
    return array_diff($slugs, ['staff-room']);
}, 10, 1);
```

**See Also:** [CORE_MODULES.md#shaped_pricing](CORE_MODULES.md#shaped_pricing)

**Added:** 2025-12-08 (IMPL-001)

---

### shaped/pricing/discount_defaults

**Hook Type:** Filter
**Stability:** Stable
**Location:** `core/class-pricing.php:83`
**Priority:** 10 (default)

**Arguments:**
- `$defaults` (array) — Array of room_slug => discount_percentage

**Returns:** Modified default discounts array

**Description:** Set default discount percentages for room types. Applied when no custom discount is saved.

**Usage:**
```php
add_filter('shaped/pricing/discount_defaults', function($defaults) {
    $defaults['studio-apartment'] = 15;  // 15% off
    $defaults['deluxe-suite'] = 10;      // 10% off
    return $defaults;
}, 10, 1);
```

**See Also:** [CUSTOMIZATION_GUIDE.md#pricing-customization](CUSTOMIZATION_GUIDE.md#pricing-customization)

**Added:** 2025-12-08 (IMPL-001)

---

### Admin Filters

---

### shaped/admin/modal_types

**Hook Type:** Filter
**Stability:** Stable
**Location:** `includes/class-admin.php:169`
**Priority:** 10 (default)

**Arguments:**
- `$modal_types` (array) — Array of modal_key => modal_label

**Returns:** Modified modal types array

**Description:** Register additional modal types for the admin settings page. Each modal type can be linked to a WordPress page.

**Usage:**
```php
add_filter('shaped/admin/modal_types', function($types) {
    $types['house-rules'] = 'House Rules';
    $types['cancellation-policy'] = 'Cancellation Policy';
    return $types;
}, 10, 1);
```

**Default Types:**
- `booking-terms` — Booking Terms
- `privacy` — Privacy Policy

**See Also:** [SHORTCODES_GUIDE.md#shaped_modal](SHORTCODES_GUIDE.md#shaped_modal)

**Added:** 2025-12-08 (IMPL-001)

---

### Reviews Module Filters

---

### shaped/reviews/table_name

**Hook Type:** Filter
**Stability:** Internal
**Location:** `modules/reviews/class-sync.php:27`
**Priority:** 10 (default)

**Arguments:**
- `$table_name` (string) — Supabase table name

**Returns:** Modified table name

**Description:** Override the Supabase table name for review sync. For advanced use cases only.

**Usage:**
```php
add_filter('shaped/reviews/table_name', function($table) {
    return 'production_reviews';
}, 10, 1);
```

**See Also:** [ROOMCLOUD_INTEGRATION.md](ROOMCLOUD_INTEGRATION.md) (reviews module has similar architecture)

**Added:** 2025-12-08 (IMPL-001)

---

### shaped/reviews/provider_links

**Hook Type:** Filter
**Stability:** Stable
**Location:** `modules/reviews/shortcodes.php:31`
**Priority:** 10 (default)

**Arguments:**
- `$links` (array) — Array of provider => url

**Returns:** Modified provider links array

**Description:** Set external URLs for each review provider. Used in "View on [Provider]" links.

**Usage:**
```php
add_filter('shaped/reviews/provider_links', function($links) {
    $links['booking'] = 'https://booking.com/hotel/hr/preelook.html';
    $links['tripadvisor'] = 'https://tripadvisor.com/Hotel_Review-Preelook';
    return $links;
}, 10, 1);
```

**See Also:** [SHORTCODES_GUIDE.md#review-shortcodes](SHORTCODES_GUIDE.md#review-shortcodes)

**Added:** 2025-12-08 (IMPL-001)

---

### Amenity Filters

---

### shaped/amenities/registry

**Hook Type:** Filter
**Stability:** Stable
**Location:** `includes/class-amenity-mapper.php:73`
**Priority:** 10 (default)

**Arguments:**
- `$registry` (array) — Array of amenity mappings

**Returns:** Modified amenity registry

**Description:** Add or modify amenity-to-icon mappings. Registry is loaded from `config/amenities-registry.json` then filtered.

**Usage:**
```php
add_filter('shaped/amenities/registry', function($registry) {
    $registry['ev-charging'] = [
        'icon' => 'ph-lightning',
        'label' => 'EV Charging'
    ];
    return $registry;
}, 10, 1);
```

**See Also:** [CORE_MODULES.md#shaped_amenity_mapper](CORE_MODULES.md#shaped_amenity_mapper)

**Added:** 2025-12-08 (IMPL-001)

---

### shaped/provider_badge/providers

**Hook Type:** Filter
**Stability:** Stable
**Location:** `shortcodes/class-provider-badge.php:78`
**Priority:** 10 (default)

**Arguments:**
- `$providers` (array) — Array of provider configurations

**Returns:** Modified providers array

**Description:** Add or modify provider badge configurations (colors, labels, rating scales).

**Usage:**
```php
add_filter('shaped/provider_badge/providers', function($providers) {
    $providers['vrbo'] = [
        'color' => '#3B5998',
        'label' => 'Vrbo',
        'max_rating' => 5
    ];
    return $providers;
}, 10, 1);
```

**Default Providers:**
- `booking` — Booking.com (#003580)
- `airbnb` — Airbnb (#FF5A5F)
- `tripadvisor` — TripAdvisor (#00AF87)
- `expedia` — Expedia (#FFCB00)
- `google` — Google (#4285F4)

**See Also:** [SHORTCODES_GUIDE.md#shaped_provider_badge](SHORTCODES_GUIDE.md#shaped_provider_badge)

**Added:** 2025-12-08 (IMPL-001)

---

## Action Hooks

### Payment Actions

---

### shaped_deposit_paid

**Hook Type:** Action
**Stability:** Stable
**Location:** `core/class-payment-processor.php:621`
**Priority:** 10 (default)

**Arguments:**
- `$booking_id` (int) — Booking post ID
- `$deposit_amount` (float) — Deposit amount charged
- `$balance_due` (float) — Remaining balance

**Description:** Fires when a deposit payment is successfully processed. Use for external integrations or custom logging.

**Usage:**
```php
add_action('shaped_deposit_paid', function($booking_id, $deposit, $balance) {
    // Log to external system
    external_api_log_deposit($booking_id, $deposit);

    // Send custom notification
    wp_mail('accounting@property.com',
        "Deposit received: $deposit EUR",
        "Booking #$booking_id - Balance due: $balance EUR"
    );
}, 10, 3);
```

**See Also:** [CORE_MODULES.md#payment-flows](CORE_MODULES.md#payment-flows)

**Added:** 2025-12-08 (IMPL-001)

---

### shaped_payment_completed

**Hook Type:** Action
**Stability:** Stable
**Location:** `core/class-payment-processor.php:622, 638, 783, 880`
**Priority:** 10 (default)

**Arguments:**
- `$booking_id` (int) — Booking post ID
- `$payment_mode` (string) — 'immediate', 'delayed', or 'deposit'

**Description:** Fires when any payment is successfully completed. This is the main hook for post-payment integrations.

**Usage:**
```php
add_action('shaped_payment_completed', function($booking_id, $mode) {
    // Sync booking to channel manager
    if (function_exists('roomcloud_sync_booking')) {
        roomcloud_sync_booking($booking_id);
    }

    // Update CRM
    crm_update_booking_status($booking_id, 'paid');

    error_log("[Custom] Payment completed: #$booking_id (mode: $mode)");
}, 10, 2);
```

**See Also:** [CORE_MODULES.md#shaped_payment_processor](CORE_MODULES.md#shaped_payment_processor)

**Added:** 2025-12-08 (IMPL-001)

---

### Booking Actions

---

### shaped_booking_cancelled

**Hook Type:** Action
**Stability:** Stable
**Location:** `core/class-booking-manager.php:575`
**Priority:** 10 (default)

**Arguments:**
- `$booking_id` (int) — Booking post ID

**Description:** Fires when a booking is cancelled by the guest. Use for external sync or custom cancellation handling.

**Usage:**
```php
add_action('shaped_booking_cancelled', function($booking_id) {
    // Sync cancellation to channel manager
    if (SHAPED_ENABLE_ROOMCLOUD) {
        Shaped_RC_Sync_Manager::sync_cancellation($booking_id);
    }

    // Update analytics
    analytics_track('booking_cancelled', ['booking_id' => $booking_id]);
}, 10, 1);
```

**See Also:** [CORE_MODULES.md#shaped_booking_manager](CORE_MODULES.md#shaped_booking_manager)

**Added:** 2025-12-08 (IMPL-001)

---

### Module Actions

---

### shaped_activate_module_roomcloud

**Hook Type:** Action
**Stability:** Stable
**Location:** `shaped-core.php:257`
**Priority:** 10 (default)

**Arguments:** None

**Description:** Fires when the RoomCloud module is activated. Used internally to create database tables and set defaults.

**Usage:**
```php
add_action('shaped_activate_module_roomcloud', function() {
    // Custom activation tasks
    update_option('my_roomcloud_activated', true);
}, 10, 0);
```

**See Also:** [ROOMCLOUD_INTEGRATION.md](ROOMCLOUD_INTEGRATION.md)

**Added:** 2025-12-08 (IMPL-001)

---

### shaped_activate_module_reviews

**Hook Type:** Action
**Stability:** Stable
**Location:** `shaped-core.php:260`
**Priority:** 10 (default)

**Arguments:** None

**Description:** Fires when the Reviews module is activated. Used internally to register post type and create defaults.

**Usage:**
```php
add_action('shaped_activate_module_reviews', function() {
    // Custom activation tasks
    flush_rewrite_rules();
}, 10, 0);
```

**See Also:** [CORE_MODULES.md#reviews-module](CORE_MODULES.md#reviews-module)

**Added:** 2025-12-08 (IMPL-001)

---

### Scheduled Actions

---

### shaped_check_abandoned_bookings

**Hook Type:** Action (WP-Cron)
**Stability:** Internal
**Location:** Scheduled by `class-booking-manager.php`
**Schedule:** Every minute

**Description:** Cron hook that checks for abandoned checkout sessions. Bookings older than 5 minutes without payment are marked as abandoned.

**See Also:** [CORE_MODULES.md#abandonment-tracking](CORE_MODULES.md#abandonment-tracking)

**Added:** 2025-12-08 (IMPL-001)

---

### shaped_charge_single_booking

**Hook Type:** Action (WP-Cron)
**Stability:** Internal
**Location:** Scheduled by `class-payment-processor.php`
**Schedule:** Single event, 7 days before check-in

**Arguments:**
- `$booking_id` (int) — Booking post ID
- `$idempotency_key` (string) — Stripe idempotency key

**Description:** Executes a scheduled charge for a delayed-payment booking. Fires 7 days before check-in date.

**See Also:** [CORE_MODULES.md#scheduled-charges](CORE_MODULES.md#scheduled-charges)

**Added:** 2025-12-08 (IMPL-001)

---

### shaped_daily_charge_fallback

**Hook Type:** Action (WP-Cron)
**Stability:** Internal
**Location:** Scheduled by `class-payment-processor.php`
**Schedule:** Daily

**Description:** Fallback scheduler that catches any missed scheduled charges. Runs daily at midnight.

**See Also:** [CORE_MODULES.md#fallback-scheduler](CORE_MODULES.md#fallback-scheduler)

**Added:** 2025-12-08 (IMPL-001)

---

## Hook Index

### Filters (Alphabetical)

| Hook | File | Purpose |
|------|------|---------|
| `shaped/admin/modal_types` | class-admin.php | Register modal types |
| `shaped/admin_email` | helpers.php | Override admin email |
| `shaped/amenities/registry` | class-amenity-mapper.php | Modify amenity icons |
| `shaped/pricing/discount_defaults` | class-pricing.php | Set default discounts |
| `shaped/pricing/room_slugs` | class-pricing.php | Filter room types |
| `shaped/property_email` | helpers.php | Override property email |
| `shaped/property_name` | helpers.php | Override property name |
| `shaped/provider_badge/providers` | class-provider-badge.php | Configure providers |
| `shaped/reviews/provider_links` | shortcodes.php | Set review links |
| `shaped/reviews/table_name` | class-sync.php | Override Supabase table |
| `shaped/stripe_sdk_path` | shaped-core.php | Override Stripe SDK path |

### Actions (Alphabetical)

| Hook | File | Purpose |
|------|------|---------|
| `shaped_activate_module_reviews` | shaped-core.php | Reviews module activation |
| `shaped_activate_module_roomcloud` | shaped-core.php | RoomCloud module activation |
| `shaped_booking_cancelled` | class-booking-manager.php | Booking cancelled |
| `shaped_charge_single_booking` | class-payment-processor.php | Execute scheduled charge |
| `shaped_check_abandoned_bookings` | class-booking-manager.php | Check abandoned checkouts |
| `shaped_daily_charge_fallback` | class-payment-processor.php | Daily charge fallback |
| `shaped_deposit_paid` | class-payment-processor.php | Deposit payment completed |
| `shaped_payment_completed` | class-payment-processor.php | Any payment completed |

---

## Adding New Hooks

When adding a new hook to shaped-core, follow this pattern:

1. **Update CLAUDE.md** with the new hook details
2. **Add documentation here** using the template above
3. **Update CUSTOMIZATION_GUIDE.md** with usage examples

See [MAINTENANCE.md](MAINTENANCE.md) for the complete workflow.
