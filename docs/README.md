# shaped-core Documentation

> **Last generated:** 2025-12-08
> **Plugin version:** 2.0.0

Welcome to the shaped-core documentation. This is a WordPress plugin for boutique hospitality booking sites, providing Stripe payments, booking management, and modular integrations.

---

## Quick Start

**New to shaped-core?** Follow this reading order:

1. **[ARCHITECTURE_GUIDE.md](ARCHITECTURE_GUIDE.md)** — Understand the plugin structure
2. **[CORE_MODULES.md](CORE_MODULES.md)** — Learn the core classes
3. **[HOOKS_REFERENCE.md](HOOKS_REFERENCE.md)** — Find extension points
4. **[CUSTOMIZATION_GUIDE.md](CUSTOMIZATION_GUIDE.md)** — Start customizing

**Need to debug?** → [DEBUGGING.md](DEBUGGING.md)

**Setting up RoomCloud?** → [ROOMCLOUD_INTEGRATION.md](ROOMCLOUD_INTEGRATION.md)

**Adding shortcodes?** → [SHORTCODES_GUIDE.md](SHORTCODES_GUIDE.md)

---

## Documentation Index

| Document | Description |
|----------|-------------|
| [ARCHITECTURE_GUIDE.md](ARCHITECTURE_GUIDE.md) | Plugin structure, bootstrap flow, namespacing |
| [CORE_MODULES.md](CORE_MODULES.md) | Pricing, payments, booking manager, emails |
| [HOOKS_REFERENCE.md](HOOKS_REFERENCE.md) | Complete hook reference (filters & actions) |
| [SHORTCODES_GUIDE.md](SHORTCODES_GUIDE.md) | All shortcodes with examples |
| [ROOMCLOUD_INTEGRATION.md](ROOMCLOUD_INTEGRATION.md) | RoomCloud channel manager module |
| [CUSTOMIZATION_GUIDE.md](CUSTOMIZATION_GUIDE.md) | How to extend without editing core |
| [DEBUGGING.md](DEBUGGING.md) | Troubleshooting common issues |
| [CLAUDE.md](CLAUDE.md) | Implementation log (source of truth) |
| [MAINTENANCE.md](MAINTENANCE.md) | Documentation workflow |

---

## Key Concepts

### Plugin Architecture

```
shaped-core/
├── shaped-core.php      # Bootstrap
├── config/              # Configuration
├── core/                # Business logic (pricing, payments, bookings)
├── includes/            # Infrastructure (loader, assets, admin)
├── shortcodes/          # Shortcode implementations
├── modules/             # Optional features (RoomCloud, Reviews)
├── templates/           # HTML templates
└── assets/              # CSS & JS
```

### Extension Pattern

shaped-core is designed for customization **without editing core files**:

```php
// Use hooks for customization
add_filter('shaped/pricing/discount_defaults', function($defaults) {
    $defaults['studio'] = 15;  // 15% discount
    return $defaults;
});

// React to events
add_action('shaped_payment_completed', function($booking_id, $mode) {
    // Sync to external system
}, 10, 2);
```

See [CUSTOMIZATION_GUIDE.md](CUSTOMIZATION_GUIDE.md) for full examples.

### Payment Modes

| Mode | When | What Happens |
|------|------|--------------|
| Immediate | < 7 days to check-in | Full payment now |
| Delayed | ≥ 7 days to check-in | Card saved, charged 7 days before |
| Deposit | Deposit mode enabled | Deposit now, balance on arrival |

See [CORE_MODULES.md#shaped_payment_processor](CORE_MODULES.md#shaped_payment_processor) for details.

---

## Change Tracking

This documentation uses an **auto-updating system**:

1. **[CLAUDE.md](CLAUDE.md)** is the source of truth for all implementations
2. All other docs reference CLAUDE.md entries
3. New features automatically update relevant docs
4. Regular audits keep everything in sync

See [MAINTENANCE.md](MAINTENANCE.md) for the complete workflow.

### Recent Changes

| Date | ID | Feature |
|------|-----|---------|
| 2025-12-08 | IMPL-001 | Initial Plugin Architecture |

*Full history in [CLAUDE.md](CLAUDE.md)*

---

## For Developers

### Requirements

- WordPress 6.0+
- PHP 8.0+
- MotoPress Hotel Booking plugin
- Stripe account

### Configuration

Essential constants for `wp-config.php`:

```php
// Stripe
define('SHAPED_STRIPE_SECRET', 'sk_live_...');
define('SHAPED_STRIPE_WEBHOOK', 'whsec_...');

// URLs
define('SHAPED_SUCCESS_URL', home_url('/thank-you/?booking_id={BOOKING_ID}'));
define('SHAPED_CANCEL_URL', home_url('/checkout'));

// Modules
define('SHAPED_ENABLE_ROOMCLOUD', false);
define('SHAPED_ENABLE_REVIEWS', true);
```

### Common Tasks

| Task | Guide |
|------|-------|
| Add a discount | [CUSTOMIZATION_GUIDE.md#pricing-customization](CUSTOMIZATION_GUIDE.md#pricing-customization) |
| Override emails | [CUSTOMIZATION_GUIDE.md#email-customization](CUSTOMIZATION_GUIDE.md#email-customization) |
| Add a shortcode | [SHORTCODES_GUIDE.md](SHORTCODES_GUIDE.md) |
| Debug payments | [DEBUGGING.md#payment-issues](DEBUGGING.md#payment-issues) |
| Set up RoomCloud | [ROOMCLOUD_INTEGRATION.md](ROOMCLOUD_INTEGRATION.md) |

---

## Support

- **Issues:** Check [DEBUGGING.md](DEBUGGING.md) first
- **Customization:** See [CUSTOMIZATION_GUIDE.md](CUSTOMIZATION_GUIDE.md)
- **New features:** Follow [MAINTENANCE.md](MAINTENANCE.md) workflow

---

## File Statistics

| Metric | Value |
|--------|-------|
| Plugin Version | 2.0.0 |
| PHP Files | ~60 |
| Lines of Code | ~5,700 |
| Hooks (Filters) | 11 |
| Hooks (Actions) | 8 |
| Shortcodes | 13 |
| Documentation Files | 10 |
