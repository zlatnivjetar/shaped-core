# shaped-core Implementation Log

**Purpose:** Track all features, hooks, classes, and changes made to shaped-core. Auto-updated by Claude Code. All documentation is generated from and references this log.

**Last synchronized with docs:** 2025-12-08
**Total implementations:** 1

---

## How This File Works

1. **Single Source of Truth** — All documentation references entries in this file
2. **Auto-Updated** — Claude Code adds entries after each implementation
3. **Entry Format** — Each entry documents: files changed, hooks added, methods created
4. **Doc Links** — Entries link to where they're documented in other files

### Entry Template

```markdown
### YYYY-MM-DD IMPL-### – [Feature Name]
**Status:** Complete | In Progress | Blocked
**Category:** Pricing | Payments | Bookings | Email | RoomCloud | Reviews | Shortcode | Core

**Files Changed:**
- path/to/file.php (what changed)
- path/to/another.php (what changed)

**What was added:**
- New hook: `shaped_hook_name`
  - Type: Filter | Action
  - Args: $arg1 (type), $arg2 (type)
  - Returns: description (for filters)
- New method: `ClassName::method_name($params)`
  - Purpose: What it does
  - Returns: Return type

**Where to find it in docs:**
- HOOKS_REFERENCE.md#hook-name
- CORE_MODULES.md#class-section

**Example usage:**
```php
// Copy-paste-ready code example
```

**Related to:** Previous IMPL-### entries this builds on
**Blocked by:** External dependencies (if applicable)
```

---

## Implementation Entries

---

### 2025-12-08 IMPL-001 – Initial Plugin Architecture

**Status:** Complete
**Category:** Core

**Files Changed:**
- `shaped-core.php` (main plugin bootstrap, 274 lines)
- `includes/class-loader.php` (PSR-4 style autoloader, 65 lines)
- `includes/class-assets.php` (conditional CSS/JS loading, 328 lines)
- `includes/class-admin.php` (admin settings page, 216 lines)
- `includes/class-amenity-mapper.php` (amenity icon mapping)
- `includes/helpers.php` (utility functions, 200 lines)
- `includes/pricing-helpers.php` (pricing utilities)
- `includes/compat-functions.php` (backward compatibility)
- `core/class-pricing.php` (discount system, payment modes, 508 lines)
- `core/class-payment-processor.php` (Stripe integration, webhooks, 1009 lines)
- `core/class-booking-manager.php` (booking lifecycle, 776 lines)
- `core/email-handler.php` (guest email notifications)
- `core/email-handler-admin.php` (admin email notifications)
- `config/defaults.php` (default configuration values)
- `config/amenities-registry.json` (amenity icon mappings)
- `shortcodes/room-cards.php` ([shaped_room_cards])
- `shortcodes/room-details.php` ([shaped_room_details])
- `shortcodes/room-meta.php` ([shaped_meta], [shaped_meta_keys])
- `shortcodes/class-provider-badge.php` ([shaped_provider_badge])
- `shortcodes/class-modal-link.php` ([shaped_modal])
- `modules/roomcloud/module.php` (RoomCloud bootstrap)
- `modules/roomcloud/includes/class-api.php` (RoomCloud API wrapper)
- `modules/roomcloud/includes/class-sync-manager.php` (sync orchestration)
- `modules/roomcloud/includes/class-webhook-handler.php` (webhook processing)
- `modules/roomcloud/includes/class-availability-manager.php` (availability sync)
- `modules/roomcloud/includes/class-error-logger.php` (error handling)
- `modules/roomcloud/cli/class-cli.php` (WP-CLI commands)
- `modules/reviews/module.php` (Reviews bootstrap)
- `modules/reviews/class-cpt.php` (review custom post type)
- `modules/reviews/class-sync.php` (Supabase sync)
- `modules/reviews/shortcodes.php` (review shortcodes)

**What was added:**

**Constants:**
- `SHAPED_VERSION` = '2.0.0'
- `SHAPED_DIR`, `SHAPED_URL`, `SHAPED_FILE`
- `SHAPED_ENABLE_ROOMCLOUD`, `SHAPED_ENABLE_REVIEWS`
- `SHAPED_STRIPE_SECRET`, `SHAPED_STRIPE_WEBHOOK`
- `SHAPED_SUCCESS_URL`, `SHAPED_CANCEL_URL`

**Core Classes:**
- `Shaped_Loader` — PSR-4 style autoloader
- `Shaped_Pricing` — Discount system and payment mode config
- `Shaped_Payment_Processor` — Stripe integration, webhook handling
- `Shaped_Booking_Manager` — Booking lifecycle management
- `Shaped_Assets` — Conditional asset loading
- `Shaped_Admin` — Admin settings page
- `Shaped_Amenity_Mapper` — Amenity icon mapping

**Filter Hooks:**
- `shaped/stripe_sdk_path` — Override Stripe SDK path
- `shaped/admin_email` — Override admin email
- `shaped/property_name` — Override property name
- `shaped/property_email` — Override property email
- `shaped/pricing/room_slugs` — Filter room types for discounts
- `shaped/pricing/discount_defaults` — Set default discounts
- `shaped/admin/modal_types` — Register modal types
- `shaped/amenities/registry` — Modify amenity icons
- `shaped/provider_badge/providers` — Configure provider badges
- `shaped/reviews/table_name` — Override Supabase table
- `shaped/reviews/provider_links` — Set review provider links

**Action Hooks:**
- `shaped_deposit_paid` — Deposit payment completed
- `shaped_payment_completed` — Any payment completed
- `shaped_booking_cancelled` — Booking cancelled
- `shaped_activate_module_roomcloud` — RoomCloud activation
- `shaped_activate_module_reviews` — Reviews activation
- `shaped_check_abandoned_bookings` — Cron: abandonment check
- `shaped_charge_single_booking` — Cron: execute scheduled charge
- `shaped_daily_charge_fallback` — Cron: daily fallback check

**Shortcodes:**
- `[shaped_room_cards]` — Display room type cards
- `[shaped_room_details]` — Display room description
- `[shaped_meta]` — Display post meta value
- `[shaped_meta_keys]` — Debug: list all meta keys
- `[shaped_provider_badge]` — Display provider rating badge
- `[shaped_modal]` — Display modal link
- `[shaped_manage_booking]` — Guest booking management
- `[shaped_booking_cancelled]` — Cancellation confirmation
- `[shaped_thank_you]` — Thank you page
- `[shaped_unified_rating]` — Star rating (Reviews module)
- `[shaped_review_author]` — Review author (Reviews module)
- `[shaped_review_date]` — Review date (Reviews module)
- `[shaped_review_content]` — Review text (Reviews module)

**RoomCloud Module Classes:**
- `Shaped_RC_API` — XML API wrapper
- `Shaped_RC_Sync_Manager` — Sync orchestration
- `Shaped_RC_Webhook_Handler` — Webhook processing
- `Shaped_RC_Availability_Manager` — Availability sync
- `Shaped_RC_Error_Logger` — Error handling and retry queue
- `Shaped_RC_CLI` — WP-CLI commands
- `Shaped_RC_Admin_Settings` — Settings page

**Reviews Module:**
- `Shaped\Modules\Reviews\CPT` — Custom post type
- `Shaped\Modules\Reviews\Sync` — Supabase sync
- `Shaped\Modules\Reviews\Admin` — Admin functionality

**Database:**
- Option: `shaped_discounts` — Room discount percentages
- Option: `shaped_payment_mode` — 'scheduled' or 'deposit'
- Option: `shaped_deposit_percent` — Deposit percentage
- Option: `shaped_modal_pages` — Modal page assignments
- Table: `wp_roomcloud_sync_queue` — RoomCloud retry queue

**Where to find it in docs:**
- ARCHITECTURE_GUIDE.md — Complete plugin structure
- HOOKS_REFERENCE.md — All hooks documented
- CORE_MODULES.md — All core classes documented
- ROOMCLOUD_INTEGRATION.md — RoomCloud module details
- SHORTCODES_GUIDE.md — All shortcodes documented
- CUSTOMIZATION_GUIDE.md — Extension examples
- DEBUGGING.md — Troubleshooting guide

**Example usage:**

```php
// Apply discount to room pricing
add_filter('shaped/pricing/discount_defaults', function($defaults) {
    $defaults['studio-apartment'] = 15;  // 15% off
    return $defaults;
});

// React to payment completion
add_action('shaped_payment_completed', function($booking_id, $mode) {
    // Sync to external system
    external_api_sync($booking_id);
    error_log("Payment completed: #$booking_id (mode: $mode)");
}, 10, 2);

// Override admin email for notifications
add_filter('shaped/admin_email', fn() => 'bookings@property.com');

// Add custom provider badge
add_filter('shaped/provider_badge/providers', function($providers) {
    $providers['vrbo'] = ['color' => '#3B5998', 'label' => 'Vrbo'];
    return $providers;
});
```

**Related to:** Initial plugin development
**Blocked by:** None

---

## Future Entries

*New entries will be added above this line as features are implemented.*

---

## Entry Index

| ID | Date | Feature | Status | Category |
|----|------|---------|--------|----------|
| IMPL-001 | 2025-12-08 | Initial Plugin Architecture | Complete | Core |

---

## Statistics

- **Total Implementations:** 1
- **Complete:** 1
- **In Progress:** 0
- **Blocked:** 0

### By Category

| Category | Count |
|----------|-------|
| Core | 1 |
| Pricing | 0 |
| Payments | 0 |
| Bookings | 0 |
| Email | 0 |
| RoomCloud | 0 |
| Reviews | 0 |
| Shortcode | 0 |

---

## Maintenance Notes

- This file is the **source of truth** for all shaped-core documentation
- After any implementation, Claude Code will add a new entry here
- All other docs reference entries by IMPL-### ID
- Run doc audit every 2 weeks (see MAINTENANCE.md)
