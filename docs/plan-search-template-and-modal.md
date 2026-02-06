# Implementation Plan: Shaped Search Results Template + Room Detail Modal

## Overview

Replace dependency on `[mphb_search_results]` with a fully Shaped-owned search results card template, apply the hybrid card style, and replace per-room page links with an inline room detail modal.

---

## Phase 1 — Own the Search Results Card Template

**Goal:** New `[shaped_room_cards template="search"]` template that produces identical output to current MPHB search results but lives entirely in shaped-core.

### Key differences from existing listing template:
1. MPHB's shortcode passes search context (dates, guest count) and displays date-specific pricing (total for N nights)
2. MPHB renders `.mphb-available-rooms-count` element for urgency detection
3. MPHB renders the book button as a form submit to checkout with pre-filled dates

### Files to create/modify:

| File | Action | Description |
|------|--------|-------------|
| `templates/room-card-search.php` | Create | New search results template (hybrid card) |
| `shortcodes/room-cards.php` | Modify | Add `search` as valid template, accept date/guest params |
| `includes/pricing-helpers.php` | Modify | Add `shaped_get_room_search_pricing()` for date-range pricing |
| `assets/css/search-results.css` | Modify | Minor adjustments for new template classes |

### Shortcode usage:
```
[shaped_room_cards template="search" check_in="2025-07-01" check_out="2025-07-04" adults="2"]
```
Also auto-detects dates from `$_GET` params.

---

## Phase 2 — Hybrid Card Style

**Goal:** Card body matches `[shaped_room_cards]` style; card footer keeps MPHB pricing/urgency/rates section.

### Card body (from shaped_room_cards):
- Image with cover behavior
- Room title (no link)
- Room excerpt
- "Amenities" heading
- Full amenity list with Phosphor icons

### Card footer (from MPHB search results):
- "Prices start at:" with discount wrapper
- Date-aware pricing ("for N nights" when dates provided)
- Discount badge, urgency badge
- Rates indicator
- CTA button: "SECURE YOUR STAY"

---

## Phase 3 — Room Detail Modal

**Goal:** Clicking a room card opens a modal with full room details instead of navigating to room page.

### Architecture: Inline hidden content + JS reveal (not AJAX)
- All data available at render time
- No loading spinner needed
- Gallery images can be preloaded

### Modal layout:
```
+--------------------------------------------------+
|  [X close]                                        |
|  +-------------------+  Room Title                |
|  |  Gallery Slider   |  Description (full)        |
|  |  [< img 1/5  >]   |                            |
|  +-------------------+  Room highlights            |
|                         Guests, Layout, etc.       |
|  All amenities (full grid)                        |
|  Pricing section                                  |
|  [======= SECURE YOUR STAY =======]              |
+--------------------------------------------------+
```

### Files to create/modify:

| File | Action | Description |
|------|--------|-------------|
| `templates/room-card-search.php` | Modify | Remove links, add data-room-id, embed hidden modal content |
| `templates/room-modal-content.php` | Create | Modal inner content template |
| `assets/js/room-modal.js` | Create | ShapedRoomModal — open/close, gallery slider, accessibility |
| `assets/css/room-modal.css` | Create | Modal layout, gallery, responsive |
| `includes/helpers.php` | Modify | Add `shaped_get_room_gallery_ids()` helper |
| `includes/class-assets.php` | Modify | Enqueue modal assets on search results page |

### Gallery slider features:
- Prev/next arrows, image counter (4/5)
- Keyboard navigation (left/right arrows)
- Touch/swipe support
- Lazy loading for off-screen images

### Accessibility:
- Focus trap within modal
- ARIA attributes (role=dialog, aria-modal, aria-labelledby)
- ESC to close
- Body scroll lock

---

## Phase 4 — Cleanup & Migration

| Task | Description |
|------|-------------|
| Update search results page | Replace `[mphb_search_results]` with `[shaped_room_cards template="search"]` |
| Remove dead code | Clean up MPHB template overrides no longer needed |
| Test checkout flow | Ensure modal CTA passes dates/guest params to checkout |

---

## Execution Order

| Session | Scope | Risk |
|---------|-------|------|
| Session 1 | Phase 1 + 2: search template, shortcode, pricing helper | MEDIUM |
| Session 2 | Phase 3a: modal JS, CSS, content template | SAFE |
| Session 3 | Phase 3b: Wire modal into search card, gallery slider | MEDIUM |
| Session 4 | Phase 4: Migration, testing, cleanup | MEDIUM |

---

## Open Questions

1. Gallery slider: vanilla JS or lightweight library (Swiper/Tiny Slider)?
2. Amenity grouping in modal: flat grid (A) or categorized like Hilton (B)?
3. Search results page slug for migration
