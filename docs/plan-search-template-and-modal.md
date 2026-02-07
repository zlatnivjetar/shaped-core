# Implementation Plan: Shaped Search Results Template + Room Detail Modal

**Status: COMPLETE** — All 4 sessions implemented.

## Overview

Replace dependency on `[mphb_search_results]` with a fully Shaped-owned search results card template, apply the hybrid card style, and replace per-room page links with an inline room detail modal.

---

## Phase 1+2 — Search Template + Hybrid Card Style (Session 1) ✓

### Files created/modified:
- `templates/room-card-search.php` — hybrid search card template
- `shortcodes/room-cards.php` — `template="search"` + date/guest params + asset enqueuing
- `includes/pricing-helpers.php` — `shaped_get_room_search_pricing()` + `shaped_render_search_price()`

### Shortcode usage:
```
[shaped_room_cards template="search"]
[shaped_room_cards template="search" check_in="2025-07-01" check_out="2025-07-04" adults="2"]
```
Auto-detects dates from MPHB URL params when not specified.

---

## Phase 3a — Room Modal Infrastructure (Session 2) ✓

### Files created/modified:
- `includes/helpers.php` — `shaped_get_room_gallery_ids()` + `shaped_get_room_gallery()`
- `templates/room-modal-content.php` — modal inner content (gallery, details, amenities, CTA)
- `assets/js/room-modal.js` — vanilla JS modal with gallery slider (keyboard + touch)
- `assets/css/room-modal.css` — responsive modal layout (desktop side-by-side, mobile fullscreen)
- `includes/class-assets.php` — enqueues modal assets on search results pages

---

## Phase 3b — Wire Modal Into Search Card (Session 3) ✓

### Files modified:
- `templates/room-card-search.php` — embedded `<template data-room-modal>` per card

---

## Phase 4 — Migration & Cleanup (Session 4) ✓

### Files modified:
- `includes/class-assets.php` — `is_search_results_page()` detects `shaped_room_cards` search template
- `shortcodes/room-cards.php` — search template self-enqueues checkout.js + room modal assets

### Migration step (manual):
Replace on search results page:
```
[mphb_search_results gallery="false"]
```
With:
```
[shaped_room_cards template="search"]
```

---

## Decisions Made

1. **Gallery slider:** Vanilla JS (zero dependencies)
2. **Amenity display in modal:** Flat grid (grouped categories can be added later by wrapping subsets)
3. **Modal architecture:** Inline `<template>` per card, cloned into overlay on click (no AJAX)
