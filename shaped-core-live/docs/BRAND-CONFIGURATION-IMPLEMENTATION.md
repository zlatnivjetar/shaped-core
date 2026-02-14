# Brand Configuration Implementation Guide

**Project:** Shaped Core Multi-Client Branding System
**Date:** December 9, 2025
**Status:** Complete (Phases 1-3)

---

## 📋 Table of Contents

1. [Overview](#overview)
2. [Phase 1: Infrastructure](#phase-1-infrastructure)
3. [Phase 2: Email Templates](#phase-2-email-templates)
4. [Phase 3: PHP Inline Styles](#phase-3-php-inline-styles)
5. [File Changes Summary](#file-changes-summary)
6. [Configuration Guide](#configuration-guide)
7. [Testing](#testing)

---

## Overview

This implementation adds a centralized brand configuration system to support multiple clients with different brand colors while minimizing code duplication.

### Problem Solved
- **Before:** Hardcoded colors (#D1AF5D, #666666, etc.) scattered across email templates and PHP UI components
- **After:** Centralized brand configuration with client-specific overrides via helper functions

### Architecture
```
Base Config (brand.json)
    ↓
Client Override (clients/[client]/brand.json) [optional]
    ↓
PHP Helpers (shaped_brand_color(), etc.)
    ↓
Templates & Components
```

---

## Phase 1: Infrastructure

### Files Created

#### 1. `includes/class-brand-config.php`
**Purpose:** Core brand configuration loader with singleton pattern

**Key Features:**
- Loads base `config/brand.json`
- Auto-detects client via `SHAPED_CLIENT` constant or domain
- Deep merges client overrides into base config
- Provides dot-notation access (`get('colors.brand.primary')`)

**Methods:**
```php
Shaped_Brand_Config::instance()->get($path, $default)
Shaped_Brand_Config::instance()->get_color($key)
Shaped_Brand_Config::instance()->get_all_colors()
Shaped_Brand_Config::instance()->get_client()
```

#### 2. `config/brand-helpers.php`
**Purpose:** Convenient global helper functions for templates

**Functions:**
```php
shaped_brand($path, $default)          // Get any config value
shaped_brand_color($key)               // Get color by key (smart search)
shaped_brand_colors()                  // Get all colors
shaped_brand_client()                  // Get current client name
shaped_brand_colors_for_js()           // Flat array for JavaScript
shaped_brand_color_e($key)             // Echo color (template helper)
shaped_brand_e($path, $default)        // Echo value (template helper)
```

**Usage Example:**
```php
// In PHP templates
<div style="color: <?php echo shaped_brand_color('primary'); ?>;">

// Or using echo helper
<div style="color: <?php shaped_brand_color_e('primary'); ?>;">
```

#### 3. `clients/README.md`
**Purpose:** Documentation for setting up new clients

**Contains:**
- Client setup instructions
- Configuration examples
- Usage guide
- Migration checklist

#### 4. `test-brand-config.php`
**Purpose:** Automated testing suite for brand configuration

**Tests:**
- Class loading
- Helper function existence
- Color retrieval
- Dot notation access
- Client detection
- JavaScript format export

### Files Modified

#### `shaped-core.php` (Main Plugin File)

**Lines 130-132:** Load brand configuration system
```php
// Load brand configuration system
require_once SHAPED_DIR . 'includes/class-brand-config.php';
require_once SHAPED_DIR . 'config/brand-helpers.php';
```

**Lines 197-200:** Export colors to JavaScript
```php
// Localize brand colors for JavaScript
if (function_exists('shaped_brand_colors_for_js')) {
    wp_localize_script($handle, 'ShapedBrand', shaped_brand_colors_for_js());
}
```

---

## Phase 2: Email Templates

### File Modified: `core/email-handler.php`

### Changes Summary

Replaced all hardcoded colors in 3 email templates:
1. **Confirmation Email** (`shaped_get_confirmation_template`)
2. **Reservation Email** (`shaped_get_reservation_template`)
3. **Cancellation Email** (`shaped_get_cancellation_template`)

### Color Mappings

| Old Hardcoded Value | New Helper Function | Purpose |
|---------------------|---------------------|---------|
| `#D1AF5D` | `shaped_brand_color('primary')` | Primary brand color |
| `#94772E` | `shaped_brand_color('secondary')` | Secondary brand color |
| `#141310` | `shaped_brand_color('textPrimary')` | Primary text |
| `#666666` | `shaped_brand_color('textMuted')` | Muted/secondary text |
| `#ffffff` | `shaped_brand_color('textInverse')` | Text on dark backgrounds |
| `#4C9155` | `shaped_brand_color('success')` | Success states |
| `#b83c2e` | `shaped_brand_color('error')` | Error states |

### Example Transformation

**Before:**
```php
<td style="background: linear-gradient(135deg, #D1AF5D 0%, #94772E 100%);">
    <h1 style="color: #ffffff;">Booking Confirmed!</h1>
</td>
```

**After:**
```php
<td style="background: linear-gradient(135deg, <?php echo shaped_brand_color('primary'); ?> 0%, <?php echo shaped_brand_color('secondary'); ?> 100%);">
    <h1 style="color: <?php echo shaped_brand_color('textInverse'); ?>;">Booking Confirmed!</h1>
</td>
```

### Sections Updated

**Confirmation Email:**
- Header gradient
- Greeting text
- Booking details table
- Total price highlight
- Location links
- Contact information
- Closing message

**Reservation Email:**
- Header gradient
- Booking summary
- Payment amount highlight
- Manage booking button
- Contact links

**Cancellation Email:**
- Header gradient
- Cancellation message
- Refund details
- Closing message

---

## Phase 3: PHP Inline Styles

### File Modified: `core/class-booking-manager.php`

### Changes Summary

Replaced all hardcoded colors in 3 shortcode outputs:
1. **Manage Booking** (`shortcode_manage_booking`)
2. **Booking Cancelled** (`shortcode_booking_cancelled`)
3. **Thank You Page** (`shortcode_thank_you`)

### Sections Updated

**Manage Booking Page:**
- "Booking not found" message
- Cancelled booking notice
- Booking details header
- Payment status indicators (paid/pending/failed)
- Deposit and balance highlights
- Cancel button styling
- Contact information
- Hover effects (CSS)

**Booking Cancelled Page:**
- Success checkmark icon
- Page header
- Booking ID section
- No charge applied message
- Error messages (if charged)
- Confirmation text

**Thank You Page:**
- Success indicator
- Booking confirmation header
- Booking details section
- Payment information
- Deposit/balance breakdown
- Getting Here section
- Contact information
- Button hover effects

### Example Transformation

**Before:**
```php
<h2 style="color: #141310; border-bottom: 2px solid #D1AF5D;">
    Booking Details
</h2>
<strong style="color: #4C9155;">Paid in Full</strong>
```

**After:**
```php
<h2 style="color: <?php shaped_brand_color_e('textPrimary'); ?>; border-bottom: 2px solid <?php shaped_brand_color_e('primary'); ?>;">
    Booking Details
</h2>
<strong style="color: <?php shaped_brand_color_e('success'); ?>;">Paid in Full</strong>
```

### Hover Effects Updated

**Before:**
```css
box-shadow:
    0 0 4px #b83c2e88,
    0 0 8px #b83c2e78,
    0 0 16px #b83c2e50;
```

**After:**
```php
box-shadow:
    0 0 4px <?php echo shaped_brand_color('error'); ?>88,
    0 0 8px <?php echo shaped_brand_color('error'); ?>78,
    0 0 16px <?php echo shaped_brand_color('error'); ?>50;
```

---

## File Changes Summary

### New Files (4)
1. `includes/class-brand-config.php` - 260 lines
2. `config/brand-helpers.php` - 245 lines
3. `clients/README.md` - 215 lines
4. `test-brand-config.php` - 145 lines

### Modified Files (3)
1. `shaped-core.php` - 2 sections modified
2. `core/email-handler.php` - 60+ color replacements
3. `core/class-booking-manager.php` - 80+ color replacements

### Total Lines Changed
- **Added:** ~865 lines (new files)
- **Modified:** ~140 color references replaced

---

## Configuration Guide

### For the Current Client (Preelook)

1. Create client directory:
```bash
mkdir -p clients/preelook
```

2. Create `clients/preelook/brand.json`:
```json
{
  "colors": {
    "brand": {
      "primary": "#D1AF5D",
      "primaryHover": "#C39937",
      "secondary": "#94772E"
    }
  }
}
```

3. Add to `wp-config.php`:
```php
define('SHAPED_CLIENT', 'preelook');
```

### For New Clients

1. Create client directory:
```bash
mkdir -p clients/new-client
```

2. Create minimal override `clients/new-client/brand.json`:
```json
{
  "colors": {
    "brand": {
      "primary": "#2563EB",
      "primaryHover": "#1D4ED8",
      "secondary": "#1E40AF"
    }
  }
}
```

3. Activate:
```php
// In wp-config.php
define('SHAPED_CLIENT', 'new-client');
```

### Auto-Detection by Domain

The system automatically detects clients based on domain:
- `preelook.com` → loads `clients/preelook/brand.json`
- `acme-hotel.com` → looks for `clients/acme-hotel/brand.json`

---

## Benefits

✅ **Single Source of Truth:** All brand colors in one place
✅ **Easy Client Onboarding:** Only override what's different
✅ **Type-Safe:** JSON structure is validated
✅ **No Breaking Changes:** Falls back to base config
✅ **JavaScript Compatible:** Colors available in JS via `ShapedBrand`
✅ **Backward Compatible:** Existing `brand.json` becomes base config

---

## Next Steps

1. Test the configuration system
2. Create client-specific overrides
3. Update JavaScript files to use `ShapedBrand` object (Phase 4 - future)
4. Document color token usage for designers

---

## Maintenance

### Adding New Colors

1. Add to `config/brand.json`:
```json
{
  "colors": {
    "semantic": {
      "info": "#3B82F6"
    }
  }
}
```

2. Use in templates:
```php
<div style="color: <?php shaped_brand_color_e('info'); ?>;">
```

### Checking Active Configuration

```php
// Get current client
$client = shaped_brand_client(); // Returns 'preelook' or null

// Get all colors
$colors = shaped_brand_colors();
print_r($colors);
```

---

**Documentation Version:** 1.0
**Last Updated:** December 9, 2025
