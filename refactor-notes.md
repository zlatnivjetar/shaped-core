# Shaped Core Refactor Status

## Architecture vs Current State

### ✅ COMPLETE

| Component | Status | Notes |
|-----------|--------|-------|
| **shaped-core.php** | ✅ Complete | Bootstrap, constants, MPHB check, module loading |
| **includes/class-loader.php** | ✅ Complete | PSR-4-ish autoloader |
| **includes/class-assets.php** | ✅ Complete | Conditional CSS/JS enqueuing |
| **includes/helpers.php** | ✅ Complete | Utility functions |
| **includes/compat-functions.php** | ✅ Complete | Legacy function aliases |
| **core/class-pricing.php** | ✅ Complete | Discounts, seasons |
| **core/class-payment-processor.php** | ✅ Complete | Stripe sessions, webhooks |
| **core/class-booking-manager.php** | ✅ Complete | Booking lifecycle |
| **core/email-handler.php** | ✅ Complete | Guest emails |
| **core/email-handler-admin.php** | ✅ Complete | Admin notifications |
| **shortcodes/room-meta.php** | ✅ Complete | `[shaped_meta]` |
| **shortcodes/room-details.php** | ✅ Complete | `[shaped_room_details]` |
| **schema/markup.php** | ✅ Complete | JSON-LD structured data |
| **templates/manage-booking.php** | ✅ Complete | Guest self-service |
| **vendor/stripe-php/** | ✅ Complete | Full Stripe SDK |

### ✅ REVIEWS MODULE - Complete

| File | Status |
|------|--------|
| modules/reviews/module.php | ✅ |
| modules/reviews/class-cpt.php | ✅ |
| modules/reviews/class-sync.php | ✅ |
| modules/reviews/class-admin.php | ✅ |
| modules/reviews/shortcodes.php | ✅ |
| modules/reviews/assets.php | ✅ |

### ❌ ROOMCLOUD MODULE - CRITICAL: 6 Files Missing

The module.php exists and references these files, but **they don't exist**:

| Missing File | Purpose |
|--------------|---------|
| `modules/roomcloud/includes/class-error-logger.php` | Logging system |
| `modules/roomcloud/includes/class-api.php` | RoomCloud API client |
| `modules/roomcloud/includes/class-availability-manager.php` | Inventory sync |
| `modules/roomcloud/includes/class-sync-manager.php` | Bidirectional sync |
| `modules/roomcloud/includes/class-webhook-handler.php` | Incoming webhooks |
| `modules/roomcloud/includes/class-admin-settings.php` | Admin UI |

**Present but incomplete:**
- `modules/roomcloud/module.php` - Bootstrap (references missing files)
- `modules/roomcloud/cli/class-cli.php` - WP-CLI commands  
- `modules/roomcloud/templates/admin-settings.php` - Admin template

### ⚠️ ASSETS - Minor Differences

**Expected (architecture):**
```
assets/css/
├── checkout.css ✅
├── search-results.css ✅
└── modals.css ❌ MISSING

assets/js/
├── checkout.js ✅
├── calendar-fix.js ✅
├── home-room-cards.js ✅
├── language-switch-fade.js ✅
├── modals.js ❌ MISSING (AJAX modal loader)
└── provider-badge-stars.js ✅
```

**Extra (not in architecture):**
- `assets/css/admin-pricing.css` (bonus)
- `assets/js/leave-page-modal-popup.js` (bonus)

### ⚠️ MISSING FROM ARCHITECTURE

| Missing | Priority | Notes |
|---------|----------|-------|
| `config/defaults.php` | Low | Hardcoded in shaped-core.php for now |
| `includes/class-admin.php` | Medium | Main settings page |
| `shortcodes/class-provider-badge.php` | Low | May be in reviews module |
| `shortcodes/class-modal-link.php` | Low | Needs modals.js |
| `templates/room-card-home.php` | Low | May use Elementor instead |
| `templates/room-card-listing.php` | Low | May use Elementor instead |
| `templates/modal-wrapper.php` | Low | For AJAX modals |
| `uninstall.php` | Low | Cleanup on uninstall |

---

## Why Things Break on Staging

1. **RoomCloud Module Fatal Error**: If `SHAPED_ENABLE_ROOMCLOUD = true`, PHP will crash because module.php tries to `require_once` 6 non-existent files.

2. **Class Not Found**: If reviews module is enabled with old namespace-less code expecting it.

---

## Deployment Strategy Recommendation

### Option A: Fix-and-Upload-All-at-Once ✅ RECOMMENDED

**Why this is better:**
- Clean cut - old code completely replaced
- No partial states causing conflicts  
- Single point of testing
- Rollback = restore backup

**Steps:**
1. Download current 3 production plugins as backup
2. Deactivate all 3 plugins on production
3. Delete all 3 plugin folders
4. Upload refactored `shaped-core` folder
5. Set `wp-config.php` constants:
   ```php
   define('SHAPED_ENABLE_ROOMCLOUD', false); // Until RoomCloud files exist
   define('SHAPED_ENABLE_REVIEWS', true);    // Reviews module is complete
   ```
6. Activate Shaped Core
7. Test thoroughly

### Option B: Folder-by-Folder (NOT recommended)

Risk of partial states where old functions conflict with new namespaces.

---

## Claude Code Approach - YES, Recommended

Your idea is solid:

```
shaped-hospitality-stack/
├── production-backup/
│   ├── shaped-core-OLD/
│   ├── shaped-reviews-OLD/
│   └── roomcloud-integration-OLD/
│
├── shaped-core/           # The refactored unified plugin
│   └── (current refactored structure)
│
└── docs/
    └── migration-notes.md
```

**Benefits:**
1. Git history for all changes
2. Claude Code can diff production vs refactored
3. Extract missing RoomCloud classes from old plugin
4. Run automated tests locally before push to staging

---

## Immediate Action Items

### Priority 1: Fix RoomCloud Module (blocking)
Copy the 6 RoomCloud classes from your production `roomcloud-integration` plugin into `modules/roomcloud/includes/`, updating namespaces and class names to match:
- `Shaped_RC_Error_Logger`
- `Shaped_RC_API`
- `Shaped_RC_Availability_Manager`
- `Shaped_RC_Sync_Manager`
- `Shaped_RC_Webhook_Handler`
- `Shaped_RC_Admin_Settings`

### Priority 2: Test with RoomCloud disabled
Set `SHAPED_ENABLE_ROOMCLOUD = false` and test core functionality + reviews module on staging.

### Priority 3: Add missing modal files (nice to have)
- `assets/css/modals.css`
- `assets/js/modals.js`

---

## Summary

| Category | Status |
|----------|--------|
| Core Classes | ✅ 100% |
| Includes | ✅ 90% (missing class-admin.php) |
| Shortcodes | ⚠️ 50% (2 of 4) |
| Templates | ⚠️ 25% (1 of 4) |
| Assets | ⚠️ 85% (missing modals) |
| Reviews Module | ✅ 100% |
| RoomCloud Module | ❌ 30% (6 critical files missing) |
| Config | ❌ 0% (merged into shaped-core.php) |

**Overall: 75% complete** - Core booking/payment flow works, Reviews work, RoomCloud is broken.