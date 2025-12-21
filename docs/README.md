# shaped-core Documentation

> **Last generated:** 2025-12-21
> **Plugin version:** 2.1.0

Welcome to the shaped-core documentation. This is a WordPress plugin for boutique hospitality booking sites, providing Stripe payments, booking management, and modular integrations.

---

## Quick Start

**Setting up a new client?** Run the **Setup Wizard** (auto-launches on activation) or access at Admin → Shaped Core → Config Health → Run Setup Wizard.

**New to shaped-core?** Follow this reading order:

1. **[ARCHITECTURE_GUIDE.md](ARCHITECTURE_GUIDE.md)** — Understand the plugin structure
2. **[CORE_MODULES.md](CORE_MODULES.md)** — Learn the core classes
3. **[HOOKS_REFERENCE.md](HOOKS_REFERENCE.md)** — Find extension points
4. **[CUSTOMIZATION_GUIDE.md](CUSTOMIZATION_GUIDE.md)** — Start customizing

**Need to debug?** → [DEBUGGING.md](DEBUGGING.md)

**Setting up RoomCloud?** → [ROOMCLOUD_INTEGRATION.md](ROOMCLOUD_INTEGRATION.md)

**Adding shortcodes?** → [SHORTCODES_GUIDE.md](SHORTCODES_GUIDE.md)

**Check configuration status?** → Admin → Shaped Core → **Config Health**

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
| Immediate | < N days to check-in | Full payment now |
| Delayed | ≥ N days to check-in | Card saved, charged N days before |
| Deposit | Deposit mode enabled | Deposit now, balance on arrival |

**N** = Scheduled charge threshold (configurable, default 7 days). Set via Setup Wizard or Admin → Shaped Pricing.

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
| 2025-12-21 | IMPL-002 | Setup Wizard & Config Health |
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

**Recommended:** Use the **Setup Wizard** for quick configuration (auto-launches on activation).

**Alternative:** Define constants in `wp-config.php` (takes priority over wizard settings):

```php
// Stripe (optional if using Setup Wizard)
define('SHAPED_STRIPE_SECRET', 'sk_live_...');
define('SHAPED_STRIPE_WEBHOOK', 'whsec_...');

// URLs
define('SHAPED_SUCCESS_URL', home_url('/thank-you/?booking_id={BOOKING_ID}'));
define('SHAPED_CANCEL_URL', home_url('/checkout'));

// Modules
define('SHAPED_ENABLE_ROOMCLOUD', false);
define('SHAPED_ENABLE_REVIEWS', true);
```

**Credential Priority:** Constants → Environment Variables → Database (Setup Wizard)

### Common Tasks

| Task | Guide |
|------|-------|
| Set up new client | Admin → Setup Wizard (auto-launches on activation) |
| Check configuration | Admin → Shaped Core → Config Health |
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
| Plugin Version | 2.1.0 |
| PHP Files | ~63 |
| Lines of Code | ~7,600 |
| Hooks (Filters) | 11 |
| Hooks (Actions) | 8 |
| Shortcodes | 13 |
| Admin Pages | 4 (Pricing, Settings, Config Health, Setup Wizard) |
| Documentation Files | 10 |
