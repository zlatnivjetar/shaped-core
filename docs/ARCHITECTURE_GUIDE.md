# Architecture Guide

> **Last generated:** 2025-12-08
> **Related entry:** [CLAUDE.md#IMPL-001](CLAUDE.md#2025-12-08-impl-001--initial-plugin-architecture)

This guide explains the architecture of shaped-core, a modular WordPress plugin for boutique hospitality booking sites.

---

## Table of Contents

1. [Overview](#overview)
2. [Directory Structure](#directory-structure)
3. [Bootstrap Flow](#bootstrap-flow)
4. [Namespace Strategy](#namespace-strategy)
5. [Module System](#module-system)
6. [File Organization by Responsibility](#file-organization-by-responsibility)
7. [Design Rationale](#design-rationale)

---

## Overview

**shaped-core** is a direct booking system for boutique hospitality properties built on top of the MotoPress Hotel Booking (MPHB) plugin. It provides:

- **Stripe payments** with scheduled charges, deposits, and immediate payment
- **Booking lifecycle management** with abandonment tracking
- **Modular integrations** (RoomCloud channel manager, reviews aggregation)
- **Customization hooks** for per-property adjustments without core edits

### Key Stats

| Metric | Value |
|--------|-------|
| Version | 2.0.0 |
| Total PHP Files | ~60 |
| Lines of Code | ~5,700 |
| WordPress Required | 6.0+ |
| PHP Required | 8.0+ |
| Dependencies | MotoPress Hotel Booking |

---

## Directory Structure

```
shaped-core/
├── shaped-core.php              # Main plugin file (bootstrap)
├── uninstall.php                # Cleanup on uninstall
│
├── config/
│   └── defaults.php             # Default configuration values
│
├── core/                        # Core business logic
│   ├── class-pricing.php        # Discount system, payment modes
│   ├── class-payment-processor.php  # Stripe integration
│   ├── class-booking-manager.php    # Booking lifecycle
│   ├── email-handler.php        # Guest email notifications
│   └── email-handler-admin.php  # Admin email notifications
│
├── includes/                    # Infrastructure & utilities
│   ├── class-loader.php         # PSR-4 style autoloader
│   ├── class-assets.php         # Conditional CSS/JS loading
│   ├── class-admin.php          # Admin settings page
│   ├── class-amenity-mapper.php # Amenity icon mapping
│   ├── helpers.php              # Global utility functions
│   ├── pricing-helpers.php      # Pricing utilities
│   └── compat-functions.php     # Backward compatibility
│
├── shortcodes/                  # Shortcode implementations
│   ├── room-cards.php           # [shaped_room_cards]
│   ├── room-details.php         # [shaped_room_details]
│   ├── room-meta.php            # [shaped_meta]
│   ├── class-provider-badge.php # [shaped_provider_badge]
│   └── class-modal-link.php     # [shaped_modal]
│
├── schema/
│   └── markup.php               # JSON-LD structured data
│
├── templates/                   # HTML templates
│   ├── room-card-home.php       # Homepage room card
│   ├── room-card-listing.php    # Listing page room card
│   ├── modal-wrapper.php        # Modal container
│   ├── checkout-modals.php      # Checkout modal elements
│   └── manage-booking.php       # Guest booking management
│
├── assets/
│   ├── css/                     # Stylesheets (9 files)
│   └── js/                      # JavaScript (6 files)
│
├── modules/                     # Optional feature modules
│   ├── roomcloud/               # RoomCloud channel manager
│   │   ├── module.php           # Module bootstrap
│   │   ├── includes/            # Module classes
│   │   ├── cli/                 # WP-CLI commands
│   │   └── templates/           # Module templates
│   │
│   └── reviews/                 # Review aggregation
│       ├── module.php           # Module bootstrap
│       ├── class-cpt.php        # Custom post type
│       ├── class-sync.php       # Supabase sync
│       └── shortcodes.php       # Review shortcodes
│
├── vendor/
│   └── stripe-php/              # Stripe PHP SDK
│
└── docs/                        # Documentation (you are here)
```

---

## Bootstrap Flow

The plugin initializes in a specific order to ensure dependencies are loaded correctly:

### 1. Main Plugin File (`shaped-core.php`)

```
WordPress loads plugin
       ↓
shaped-core.php executes
       ↓
Define constants (SHAPED_VERSION, SHAPED_DIR, etc.)
       ↓
Hook into 'plugins_loaded' (priority 20)
```

### 2. Initialization (`plugins_loaded` @ priority 20)

```php
// shaped-core.php:93
add_action('plugins_loaded', function() {
    // 1. Check MPHB dependency
    if (!class_exists('HotelBookingPlugin')) {
        add_action('admin_notices', 'shaped_mphb_missing_notice');
        return;
    }

    // 2. Load Stripe SDK
    require_once SHAPED_DIR . 'vendor/stripe-php/init.php';

    // 3. Load autoloader
    require_once SHAPED_DIR . 'includes/class-loader.php';
    Shaped_Loader::register();

    // 4. Load helpers
    require_once SHAPED_DIR . 'includes/helpers.php';
    require_once SHAPED_DIR . 'includes/pricing-helpers.php';
    require_once SHAPED_DIR . 'includes/compat-functions.php';

    // 5. Initialize core classes
    Shaped_Pricing::init();
    Shaped_Payment_Processor::init();
    Shaped_Booking_Manager::init();
    Shaped_Assets::init();
    Shaped_Admin::init();

    // 6. Load shortcodes
    require_once SHAPED_DIR . 'shortcodes/room-cards.php';
    require_once SHAPED_DIR . 'shortcodes/room-details.php';
    // ... other shortcodes

    // 7. Load optional modules
    if (SHAPED_ENABLE_ROOMCLOUD) {
        require_once SHAPED_DIR . 'modules/roomcloud/module.php';
    }
    if (SHAPED_ENABLE_REVIEWS) {
        require_once SHAPED_DIR . 'modules/reviews/module.php';
    }
}, 20);
```

### 3. Hook Priority Reference

| Hook | Priority | What Happens |
|------|----------|--------------|
| `plugins_loaded` | 20 | Main plugin initialization |
| `wp_enqueue_scripts` | 25 | Frontend config localization |
| `init` | 10 | Shortcode registration |
| `admin_menu` | 10 | Admin pages registration |

---

## Namespace Strategy

shaped-core uses a **class-based architecture** with prefixed class names rather than PHP namespaces. This ensures WordPress compatibility and simplicity.

### Class Naming Convention

| Prefix | Purpose | Example |
|--------|---------|---------|
| `Shaped_` | Core plugin classes | `Shaped_Pricing`, `Shaped_Admin` |
| `Shaped_RC_` | RoomCloud module classes | `Shaped_RC_API`, `Shaped_RC_Sync_Manager` |
| `Shaped\Modules\Reviews\` | Reviews module (namespaced) | `Shaped\Modules\Reviews\CPT` |

### Autoloader Class Map (`includes/class-loader.php`)

```php
private static $class_map = [
    'Shaped_Pricing'           => 'core/class-pricing.php',
    'Shaped_Payment_Processor' => 'core/class-payment-processor.php',
    'Shaped_Booking_Manager'   => 'core/class-booking-manager.php',
    'Shaped_Assets'            => 'includes/class-assets.php',
    'Shaped_Admin'             => 'includes/class-admin.php',
    'Shaped_Amenity_Mapper'    => 'includes/class-amenity-mapper.php',
];
```

### Hook Naming Convention

All hooks use the `shaped` prefix with forward-slash namespacing:

```php
// Filters
apply_filters('shaped/pricing/room_slugs', $slugs);
apply_filters('shaped/admin/modal_types', $types);

// Actions
do_action('shaped_payment_completed', $booking_id, $mode);
do_action('shaped_booking_cancelled', $booking_id);
```

---

## Module System

Modules are **optional features** that can be enabled/disabled via constants in `wp-config.php`.

### Enabling Modules

```php
// wp-config.php
define('SHAPED_ENABLE_ROOMCLOUD', true);  // RoomCloud channel manager
define('SHAPED_ENABLE_REVIEWS', true);     // Review aggregation
```

### Module Structure

Each module follows this pattern:

```
modules/
└── module-name/
    ├── module.php           # Bootstrap file (required)
    ├── includes/            # Module classes
    ├── cli/                 # WP-CLI commands (optional)
    ├── templates/           # Module templates (optional)
    └── assets/              # Module assets (optional)
```

### Module Bootstrap (`module.php`)

```php
<?php
/**
 * Module: RoomCloud Integration
 * Version: 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Define module constants
define('SHAPED_RC_VERSION', '1.1.0');
define('SHAPED_RC_DIR', plugin_dir_path(__FILE__));

// Check dependencies
if (!shaped_is_mphb_active()) {
    return;
}

// Load module classes
require_once SHAPED_RC_DIR . 'includes/class-api.php';
require_once SHAPED_RC_DIR . 'includes/class-sync-manager.php';
// ... etc

// Initialize module
add_action('init', function() {
    Shaped_RC_Sync_Manager::init();
    Shaped_RC_Webhook_Handler::init();
});

// Activation hook
add_action('shaped_activate_module_roomcloud', function() {
    // Create tables, set defaults, etc.
});
```

---

## File Organization by Responsibility

### Core Layer (`/core/`)

Business logic that runs on every page load:

| File | Responsibility |
|------|---------------|
| `class-pricing.php` | Discount calculations, payment mode config |
| `class-payment-processor.php` | Stripe integration, webhook handling |
| `class-booking-manager.php` | Booking lifecycle, abandonment |
| `email-handler.php` | Guest notification emails |
| `email-handler-admin.php` | Admin notification emails |

### Infrastructure Layer (`/includes/`)

Support code and utilities:

| File | Responsibility |
|------|---------------|
| `class-loader.php` | PSR-4 style autoloader |
| `class-assets.php` | Conditional CSS/JS loading |
| `class-admin.php` | Settings page, modal config |
| `class-amenity-mapper.php` | Amenity icon registry |
| `helpers.php` | Global utility functions |

### Presentation Layer (`/shortcodes/`, `/templates/`)

UI components and templates:

| Directory | Responsibility |
|-----------|---------------|
| `shortcodes/` | Shortcode PHP logic |
| `templates/` | HTML output templates |
| `assets/css/` | Stylesheets |
| `assets/js/` | JavaScript files |

### Configuration (`/config/`)

Default values and settings:

| File | Purpose |
|------|---------|
| `defaults.php` | Default configuration array |
| `amenities-registry.json` | Amenity-to-icon mapping |

---

## Design Rationale

### Why This Architecture?

1. **Modular design** enables client customization without core edits
2. **Hook-based extension** allows per-property overrides via child plugins
3. **Conditional asset loading** improves performance on non-booking pages
4. **Clear separation of concerns** makes maintenance predictable

### Key Principles

1. **Never edit core files** — Use hooks for customization
2. **Modules are optional** — Disable what you don't need
3. **Fail gracefully** — Check dependencies before executing
4. **Log extensively** — Every error is logged with context

### Extensibility Pattern

The plugin provides hooks at key decision points:

```php
// Example: Override commission calculation
add_filter('shaped/pricing/discount_defaults', function($defaults) {
    $defaults['studio-apartment'] = 15;  // 15% discount
    return $defaults;
});

// Example: React to booking cancellation
add_action('shaped_booking_cancelled', function($booking_id) {
    // Sync with external system
    external_api_cancel($booking_id);
});
```

---

## Next Steps

- **[CORE_MODULES.md](CORE_MODULES.md)** — Deep dive into each core class
- **[HOOKS_REFERENCE.md](HOOKS_REFERENCE.md)** — Complete hook reference
- **[CUSTOMIZATION_GUIDE.md](CUSTOMIZATION_GUIDE.md)** — How to extend shaped-core
- **[DEBUGGING.md](DEBUGGING.md)** — Troubleshooting guide
