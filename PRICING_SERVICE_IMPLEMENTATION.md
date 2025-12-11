# Hotel Pricing Service Implementation Tracker

**Project:** LLM-accessible pricing API for hotel clients
**Start Date:** 2025-12-11
**Current Phase:** Session 1 Complete - Ready for Session 2
**Status:** ✅ Session 1 Complete | 🟡 Session 2 Pending

---

## Implementation Plan Overview

### Session 1: Discovery & Foundation ✅ IN PROGRESS
- **Phase 0:** Map existing pricing/availability logic (Steps 1-2)
- **Phase 1:** Define unified data models (Steps 3-4)
- **Target:** Understanding + PriceRequest/PriceResult classes

### Session 2: Service Architecture ⏳ NOT STARTED
- **Phase 2:** Abstract pricing into service (Steps 5-9)
- **Target:** Working pricing service abstraction

### Session 3: UI Refactoring ⏳ NOT STARTED
- **Phase 3:** Refactor UI to use new service (Steps 10-11)
- **Target:** All front-end uses new service

### Session 4: API Endpoints ⏳ NOT STARTED
- **Phase 4:** JSON endpoint (Steps 12-14)
- **Phase 5:** HTML endpoint (Steps 15-16)
- **Target:** Working REST API endpoints

### Session 5: Security & Validation ⏳ NOT STARTED
- **Phase 6:** Guardrails & security (Steps 17-20)
- **Target:** Production-ready hardened API

### Session 6: Testing & Finalization ⏳ NOT STARTED
- **Phase 7:** Verify end-to-end (Steps 21-23)
- **Phase 8:** Generalize to other clients (Steps 24-26)
- **Target:** Tested, documented, generalizable system

---

## Current Session Details

### Session 1: Discovery & Foundation

#### Phase 0 - Map What Already Exists

**Step 1: Locate pricing + availability logic**
- Status: ✅ Complete
- Search for:
  - [x] RoomCloud references
  - [x] Availability logic
  - [x] Rate/price calculation
  - [x] MotoPress hooks (search results, booking summary)
- Findings:
  - **Pricing Classes:**
    - `core/class-pricing.php` - Handles discount logic, payment modes (scheduled/deposit)
    - `includes/pricing-helpers.php` - Helper functions for room pricing display
  - **RoomCloud Integration:**
    - `modules/roomcloud/includes/class-api.php` - XML API wrapper for RoomCloud
    - `modules/roomcloud/includes/class-availability-manager.php` - Manages inventory from RoomCloud
  - **Current Pricing Flow:**
    1. Base prices come from MotoPress (`mphb_base_price` or `getDefaultPrice()`)
    2. Discounts applied via `Shaped_Pricing::calculate_final_amount()`
    3. Availability from RoomCloud (stored in `shaped_rc_inventory` option)
  - **Display Logic:**
    - Room cards use `shaped_render_room_price()` (line 115 in room-card-home.php, line 151 in room-card-listing.php)
    - Function calls `shaped_get_room_pricing_data()` → `shaped_get_room_base_price()`

**Step 2: Identify the right home for shared service**
- Status: ✅ Complete
- Confirm:
  - [x] shaped-core is loaded for all clients
  - [x] shaped-core is the right place for reusable logic
- Findings:
  - ✅ `shaped-core` plugin is the correct home (confirmed via CLAUDE.md)
  - ✅ Already has core/, includes/, and modules/ structure
  - ✅ New service will live in `includes/Pricing/` namespace

#### Phase 1 - Define Unified Pricing Model

**Step 3: Create standard "pricing request" structure**
- Status: ✅ Complete
- File: `includes/Pricing/PriceRequest.php` (235 lines)
- Features:
  - Validates check-in/checkout dates and guest counts
  - Supports DateTime objects or Y-m-d strings
  - Business rule validation (max 30 nights, 18-month booking window)
  - Factory method: `from_query_params()` for REST API
  - Utility methods: `get_nights()`, `get_total_guests()`, `to_array()`
- Fields:
  - checkin (DateTime)
  - checkout (DateTime)
  - adults (int, default: 2)
  - children (int, default: 0)
  - room_type_slug (string|null, optional)

**Step 4: Create standard "pricing result" structure**
- Status: ✅ Complete
- File: `includes/Pricing/PriceResult.php` (320 lines)
- Features:
  - Structured rate data with validation
  - Converts to JSON or HTML (human-readable sentence)
  - Price formatting with currency symbols
  - Discount and tax information tracking
- Fields:
  - property_name, currency, checkin, checkout, nights, adults, children
  - best_rate: {room_type_slug, room_type_name, board, refundable, total, per_night, tax_included, discounts_applied}
  - other_options (array of alternative rates)
  - source (string, e.g., 'roomcloud')
  - generated_at (ISO8601 timestamp)

---

## Discovery Findings Log

### Existing Pricing Logic

**RoomCloud Integration:**
- **Location:** `modules/roomcloud/includes/`
- **Key Classes:**
  - `Shaped_RC_API` - XML API wrapper, sends reservations and fetches availability
  - `Shaped_RC_Availability_Manager` - Stores inventory in `shaped_rc_inventory` option
  - Room mapping: MotoPress slug → RoomCloud ID (e.g., 'deluxe-studio-apartment' → '42683')
- **Availability Storage:** WordPress option `shaped_rc_inventory` (format: `[roomcloud_id => [date => quantity]]`)
- **Methods:** `get_available_rooms($checkin, $checkout)`, `is_available($roomcloud_id, $checkin, $checkout)`

**MotoPress Integration:**
- **Plugin:** MotoPress Hotel Booking (MPHB)
- **Post Type:** `mphb_room_type` (room types)
- **API:** `MPHB()` global function provides repositories
- **Pricing:** Base prices stored in `_mphb_base_price` post meta or via `getDefaultPrice()` method
- **Bookings:** `MPHB()->getBookingRepository()->findById($id)`

**Current Price Calculation Flow:**
1. **Base Price:** Fetched from MotoPress via `shaped_get_room_base_price($room_type_id)`
   - Tries `MPHB()->getRoomTypeRepository()->findById($id)->getDefaultPrice()`
   - Falls back to `_mphb_base_price` post meta
2. **Discount Applied:** `Shaped_Pricing::calculate_final_amount($base, $room_slug)`
   - Reads discount config from `shaped_discounts` option
   - Returns: `['final' => float, 'discount_percent' => int, 'saved' => float, 'base' => float]`
3. **Display:** `shaped_render_room_price($room_id, $room_slug)` outputs HTML with strikethrough + discount badge
4. **Availability:** RoomCloud is "source of truth" via `Shaped_RC_Availability_Manager`

**Discount Application:**
- **Where:** `core/class-pricing.php` (line 433)
- **How:** Percentage-based discount per room type (configurable via admin panel)
- **Storage:** `shaped_discounts` option (e.g., `['suite' => 10, 'apartment' => 15]`)
- **When:** Applied at display time (room cards) and checkout (final charge calculation)
- **Payment Modes:**
  - "scheduled" = charge full amount 7 days before checkin (or immediately if <7 days)
  - "deposit" = charge X% deposit immediately, rest on arrival

---

## Files Created/Modified

### Session 1 (Phase 0-1) ✅ COMPLETE
- [x] `PRICING_SERVICE_IMPLEMENTATION.md` (this file) - Implementation tracking
- [x] `includes/Pricing/PriceRequest.php` (235 lines) - Input data model
- [x] `includes/Pricing/PriceResult.php` (320 lines) - Output data model

### Session 2 (Phase 2)
- _[To be filled]_

---

## Branch & Commit Strategy

**Working Branch:** `claude/hotel-pricing-service-01MfwZ6tvwHdcYG3pW944HDs`

**Commit Plan:**
- Session 1 Commit: "feat: Phase 0-1 - Discovery findings and pricing data models"
- Session 2 Commit: "feat: Phase 2 - Implement pricing service architecture"
- Session 3 Commit: "refactor: Phase 3 - Migrate UI to unified pricing service"
- Session 4 Commit: "feat: Phase 4-5 - Add REST API endpoints for pricing"
- Session 5 Commit: "security: Phase 6 - Add validation and security guardrails"
- Session 6 Commit: "docs: Phase 7-8 - Testing, documentation, and generalization"

**PR Strategy:** One PR after each session (6 PRs total)

---

## Decision Log

### 2025-12-11: Implementation Segmentation
- **Decision:** Split into 6 sessions instead of one monolithic implementation
- **Rationale:** Smaller PRs, early issue detection, reduced rollback risk
- **Sessions:** Discovery, Architecture, UI, API, Security, Testing

---

## Questions & Blockers

_[Document any questions or blockers encountered during implementation]_

- None yet

---

## Next Steps

**Immediate:**
1. Search codebase for RoomCloud pricing logic
2. Search for MotoPress integration hooks
3. Identify current price calculation flow
4. Document findings in this file

**After Discovery:**
1. Create PriceRequest.php
2. Create PriceResult.php
3. Commit Session 1 work
4. Create PR #1

---

**Last Updated:** 2025-12-11 (Session 1 start)
