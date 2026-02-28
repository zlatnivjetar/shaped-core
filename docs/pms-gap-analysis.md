# Shaped Core → PMS Gap Analysis

**Date:** 2026-02-28
**Scope:** Business logic and integrations only. WordPress plumbing excluded.

---

## Section 1 — Feature Inventory

### 1.1 Payment Orchestration

| # | Feature | What it does | MPHB dependency | PMS status |
|---|---------|-------------|-----------------|------------|
| 1 | **Deposit mode** | Charges a configurable percentage upfront via Stripe Checkout. Balance due on arrival. System-wide toggle, never per-booking. | Intercepts MPHB checkout form submission | **Partial** — PMS has `deposit_at_booking` payment mode and `deposit_percentage` on properties table. Stripe PI is created with manual capture. Missing: balance-due tracking, deposit-specific email messaging ("deposit paid, balance due on arrival"). |
| 2 | **Scheduled charge (dual-flow)** | Bookings ≥ N days out: saves payment method, charges automatically N days before check-in via cron. Bookings < N days out: immediate full charge via Stripe Checkout. | Intercepts MPHB checkout | **Missing** — PMS has no concept of deferred/scheduled charges. No payment method saving, no cron-based charge execution, no threshold configuration. |
| 3 | **Scheduled charge retry + fallback** | Exponential backoff retry (up to 3 attempts) on failed scheduled charges. Daily fallback cron at 16:30 Zagreb time catches any missed charges. | None | **Missing** |
| 4 | **Stripe Checkout sessions** | Creates Stripe Checkout sessions (redirect-based flow) for deposit and immediate-charge scenarios. Idempotency keys prevent duplicate sessions. | Intercepts MPHB form POST | **Partial** — PMS uses Stripe Payment Intents with Elements (inline), not Checkout Sessions (redirect). Both approaches work; the PMS approach is arguably better for UX. Idempotency is handled by pre-generating the reservation code. |
| 5 | **Stripe webhook handler** | Processes `charge.succeeded`, `charge.failed`, `payment_intent.*` events. Updates payment status, triggers confirmation email. | None | **Partial** — PMS has `api/v1/webhooks/stripe/route.ts` with handlers for `payment_intent.succeeded/failed` and `charge.refunded`, but it's not registered in Stripe dashboard. Webhook is not actively used; payment status is set synchronously after client-side confirmation. |
| 6 | **Payment status metadata** | Tracks: pending → authorized → paid / deposit_paid / failed / cancelled. Prevents duplicate charges, duplicate emails, and duplicate session creation via idempotency flags. | Stored on MPHB booking post meta | **Partial** — PMS `payments` table has status enum (pending, requires_capture, captured, failed, refunded). Missing: `authorized` state for scheduled-charge flow, idempotency flags for email sends. |
| 7 | **Capture & refund from dashboard** | Manual capture of held authorizations and full refunds from the reservation detail page. | None | **Exists** — `capturePaymentAction` and `refundPaymentAction` in dashboard reservation detail. |
| 8 | **Cancellation with auto-refund** | Cancelling a reservation auto-refunds captured payments or cancels uncaptured PIs. Rolls back inventory. | None | **Exists** — `cancelReservation` action handles both paths. |

### 1.2 Booking Management

| # | Feature | What it does | MPHB dependency | PMS status |
|---|---------|-------------|-----------------|------------|
| 9 | **Abandonment detection** | Cron runs every minute. Bookings pending >5 minutes without payment completion are marked abandoned, rooms released. | Reads MPHB booking status, releases MPHB room posts | **Missing** — PMS has no abandoned booking cleanup. A guest who starts checkout but never pays leaves a dangling state. |
| 10 | **Manage booking portal** | Guest-facing page at `/manage-booking/?booking_id=X&token=Y`. Token = MD5(booking_id + email). Shows booking details, payment status, balance due, cancellation option. 1-hour expiry. | Reads MPHB booking data | **Missing** — PMS has no guest self-service portal. Guests cannot view or cancel their booking after checkout. |
| 11 | **Thank-you page** | Post-payment confirmation page showing booking details, payment info, upcoming charge date (scheduled mode), check-in instructions. 1-hour security window. | Reads MPHB booking data | **Partial** — PMS has `step-complete.tsx` showing confirmation code and booking summary. Missing: check-in instructions, charge date info, security expiry. |
| 12 | **Guest cancellation flow** | Guest cancels from manage-booking portal. Detaches payment method, clears scheduled charge, releases rooms, updates status, sends cancellation emails to guest + admin. Non-refundable policy if already charged within threshold. | Updates MPHB booking status | **Partial** — Dashboard-side cancellation exists with refund + inventory rollback + email. No guest-initiated cancellation portal. No cancellation policy logic (non-refundable threshold). |

### 1.3 Pricing & Discounts

| # | Feature | What it does | MPHB dependency | PMS status |
|---|---------|-------------|-----------------|------------|
| 13 | **Room-type discounts** | Per-room-type percentage discounts. Configurable from admin. Applied to displayed prices and checkout totals. | Applied on top of MPHB rates | **Missing** — PMS rate plans support seasonal/LOS/occupancy overrides but not percentage discounts layered on top. |
| 14 | **Seasonal discount overrides** | Recurring date-range discounts (e.g., 10% off May 1–31 every year) with year-specific overrides (e.g., 5% for 2024 only). | None | **Missing** — PMS rate plans can achieve similar results by setting lower `rate_cents` for date ranges, but there's no concept of a percentage discount that stacks with the rate. |
| 15 | **Pricing service with provider interface** | Unified pricing API that supports multiple providers (MPHB, RoomCloud). Request validation (max 30 nights, max 18 months, max 10 guests). 5-minute cache. | Delegates to MPHB rate engine | **Partial** — PMS has `resolveRate()` in `pricing.ts` which resolves rates by priority. No provider abstraction, no caching, no request validation limits. The rate resolution logic itself is solid. |
| 16 | **Date-aware search pricing** | Search results show total price for the selected date range (not just per-night base rate). Includes discount calculation and "from €X/night" formatting. | Reads MPHB season rates | **Exists** — PMS booking engine step 2 (`step-select.tsx`) shows nightly rate and total for the stay, computed server-side via `getAvailableRoomTypes()`. |

### 1.4 Availability & Inventory

| # | Feature | What it does | MPHB dependency | PMS status |
|---|---------|-------------|-----------------|------------|
| 17 | **Inventory ledger** | Per-room-type, per-date inventory tracking with total/booked/blocked units and rate overrides. | Replaces MPHB's room-post availability | **Exists** — `inventory` table with identical structure. |
| 18 | **Double-booking prevention** | Conditional UPDATE that only increments `booked_units` when available >= 1. Partial rollback on failure. | None | **Exists** — Same conditional UPDATE pattern in PMS. |
| 19 | **Calendar availability view** | Month grid: room types as rows, dates as columns. Shows available count + effective rate. Color-coded by availability level. Rate override dialog per cell. | None | **Exists** — `/calendar` page with `AvailabilityCalendar` component. |
| 20 | **Inventory auto-sync on room changes** | Adding/removing a room recalculates `total_units` across 365-day window. | None | **Exists** — `upsertInventory()` called from `addRoom` and `deleteRoom` actions. |

### 1.5 Channel Manager (RoomCloud)

**Architecture note:** In Shaped Core, RoomCloud was the source of truth for inventory — it pushed availability to the plugin via `modify`, and three gating layers enforced RC's numbers over MPHB's. In the PMS, the architecture is inverted: **PMS is the source of truth**. The PMS pushes inventory and rates to the channel manager; the channel manager only sends bookings back. The three gating layers are unnecessary because there is a single inventory table with no competing system. See Appendix A for full details.

| # | Feature | What it does | MPHB dependency | PMS status |
|---|---------|-------------|-----------------|------------|
| 21 | **Channel manager webhook endpoint** | REST endpoint receives XML messages from channel manager: `getHotels`, `getRates`, `getRooms`, `view` (config/read queries), `modify` (inventory/rate pushes), `reservation` (OTA bookings). Username/password auth from XML attributes. | Room type mapping via MPHB slugs | **Missing** — PMS has `POST /reservations` REST API for OTA bookings but no channel manager protocol endpoint. |
| 22 | **Three-level availability gating** | Stay-level (cap room count), per-day (block calendar dates), individual room (block room pages). Only existed because MPHB and RoomCloud both owned inventory and needed reconciliation. | Filters MPHB search results, booking rules, and room queries | **Not needed** — PMS has a single inventory table. No competing availability source to reconcile. |
| 23 | **Outbound inventory/rate push** | Push availability and rate changes to channel manager whenever inventory or rates change in PMS. In Shaped Core this was partially implemented (reservation push only, not inventory push — RC was source of truth). | None | **Missing** — PMS has no outbound push to any channel manager. |
| 24 | **Outbound reservation push** | When a direct booking is created, confirmed, or cancelled, push to channel manager so it can block/release dates on OTAs. Includes loop prevention (don't push back bookings that came from the CM). | None | **Missing** |
| 25 | **Sync queue with retry** | Database table tracks outbound sync operations with retry logic (15-min initial delay, max 5 attempts), error tracking. Hourly cron processes pending retries. | None | **Missing** |
| 26 | **Channel manager admin settings** | Room type mapping UI (PMS room type → external room ID), webhook endpoint display, connection test buttons, sync log viewer. | None | **Missing** |
| 27 | **Inbound OTA reservation handling** | Receive bookings from OTAs via channel manager. Create reservation (status: confirmed, no Stripe), decrement inventory, upsert guest. Mark source to prevent sync loops. | Creates MPHB booking post with RC metadata | **Partial** — `POST /reservations` API handles OTA bookings with inventory locking, but doesn't speak channel manager protocol (XML), doesn't handle booking modifications (status 8) or all RC status codes, and has no loop prevention. |
| 28 | **Modify handler (self-correction)** | Accept inbound `modify` messages (protocol compliance), compare stated availability against PMS inventory, and if they differ, immediately push PMS's own numbers back. PMS always wins. | Stored RC inventory as WP option | **Missing** |

### 1.6 Email System

| # | Feature | What it does | MPHB dependency | PMS status |
|---|---------|-------------|-----------------|------------|
| 26 | **Booking confirmation email** | Sent after successful payment. Contains confirmation code, stay details, payment summary (deposit note if applicable), check-in time, property address. | None (replaces MPHB emails) | **Exists** — `sendBookingConfirmation` with React Email template. |
| 27 | **Cancellation email (guest + admin)** | Sent on cancellation. Shows refund status, cancellation reason. Separate admin notification variant. | None | **Partial** — Guest cancellation email exists (`sendCancellationConfirmation`). No admin notification email. |
| 28 | **Abandonment email** | Sent when booking is marked abandoned after 5 minutes. Invites guest to rebook. | None | **Missing** — No abandonment detection, so no abandonment email. |
| 29 | **Admin confirmation email** | Sent to property admin on every new booking. Contains full booking details, payment method last4, deposit/balance split. | None | **Missing** — PMS sends emails to guests only, not to property admins. |
| 30 | **Pre-arrival email** | Sent day before check-in. Contains check-in time, address, booking reference. Triggered by daily cron. | None | **Exists** — `sendPreArrival` triggered by Vercel cron daily job. |
| 31 | **Post-stay email** | Sent after checkout. Thank-you message with "Leave a Review" button. | None | **Exists** — `sendPostStay` triggered on checkout action. |
| 32 | **Review request email** | Follow-up 2 days after checkout for guests who haven't left a review. Triggered by daily cron. | None | **Exists** — Vercel cron daily job checks for unused review tokens 2+ days after checkout. |
| 33 | **Email duplicate prevention** | Meta flags (`_shaped_confirmation_sent`, `_shaped_review_email_sent`) prevent sending the same email twice. | Stored on MPHB booking post meta | **Partial** — PMS cron checks `email_logs` for existing entries before sending pre-arrival/review-request emails. Confirmation email has no explicit duplicate check (fire-and-forget after reservation creation). |
| 34 | **Brand-configurable email templates** | From name, logo URL, check-in instructions, closing message, contact info — all configurable per property via brand.json. | None | **Partial** — PMS email templates use property data (name, address, check-in/out times) but don't have a general brand config system (logo URL, custom closing message, etc.). |

### 1.7 Reviews

| # | Feature | What it does | MPHB dependency | PMS status |
|---|---------|-------------|-----------------|------------|
| 35 | **Direct guest reviews** | Token-based review submission after checkout. Rating 1–5, title, body. Token expires after 30 days. Status: pending → published → hidden. | None | **Exists** — Full review system with tokens, submission form, status management. |
| 36 | **OTA review syndication** | Imports reviews from Booking.com, Google, TripAdvisor, Expedia, Airbnb via Supabase. Deduplication by MD5 hash. Rating threshold filtering (4+ for most, 8+ for Booking/Expedia). | None | **Missing** — PMS only has direct reviews. No OTA review import. |
| 37 | **Review admin dashboard** | Filter by status, publish/hide toggle, property response, provider breakdown. | None | **Exists** — `/reviews` page with status filters, publish/hide actions, property response. |
| 38 | **Reviews on booking engine** | Up to 5 published reviews with average rating displayed on the search step of the booking flow. | None | **Exists** — Booking engine search step fetches and displays published reviews. |

### 1.8 Search & Room Display

| # | Feature | What it does | MPHB dependency | PMS status |
|---|---------|-------------|-----------------|------------|
| 39 | **Search form** | Date pickers, guest count selectors. Pre-fills adults in checkout. Benefits text ("Best rate direct • Instant confirmation • Secure payment"). | Wraps MPHB search form | **Exists** — PMS booking engine step 1 has date pickers and guest count selectors. No benefits text. |
| 40 | **Search results with capacity sorting** | Rooms sorted by capacity proximity to guest count (best-fit first). Rooms that can't fit guests shown last. Zero-availability rooms hidden entirely. | Queries MPHB room types | **Partial** — PMS `getAvailableRoomTypes()` filters out fully booked types but doesn't sort by capacity proximity to requested guest count. |
| 41 | **Room detail modal** | Clicking a search result opens an overlay with image gallery (prev/next navigation), full description, all amenities, and sticky footer with pricing + CTA. | Reads MPHB room type data | **Missing** — PMS booking engine shows room type cards in step 2 but no modal/overlay with gallery and full details. |
| 42 | **Room card templates** | Four template variants: homepage (linked cards), listing, landing (compact), search (date-aware pricing + urgency + checkout form). | Reads MPHB room type posts | **Partial** — PMS has room type cards in the booking engine (step 2) but only one variant. No homepage/landing/listing templates (those are marketing pages, not core PMS). |

### 1.9 Amenities & Room Features

| # | Feature | What it does | MPHB dependency | PMS status |
|---|---------|-------------|-----------------|------------|
| 43 | **Amenity registry** | 80+ amenity definitions with Phosphor icons, keywords for fuzzy matching, and priority ordering. Stored in `amenities-registry.json`. | Reads MPHB facility taxonomy terms | **Missing** — PMS schema has no amenities/facilities table. Room types have name, description, occupancy, and rate — no amenity data. |
| 44 | **Amenity icon mapper** | Fuzzy matching: exact slug → normalized name → keyword contains → fallback icon. Supports 6 icon weights. | Matches against MPHB facility terms | **Missing** |
| 45 | **"Sleeps X" special token** | Auto-generated amenity badge showing room capacity. Always priority 1, not stored in taxonomy. | Reads MPHB `getTotalCapacity()` | **Partial** — PMS has `base_occupancy` and `max_occupancy` on room types, displayed as text. No badge/icon treatment. |

### 1.10 Property & Brand Configuration

| # | Feature | What it does | MPHB dependency | PMS status |
|---|---------|-------------|-----------------|------------|
| 46 | **Brand configuration system** | JSON-based config for company info, contact details, email settings, colors, integrations. Supports multi-client via MU-plugin or per-client JSON files. | None | **Partial** — PMS `properties` table stores name, address, city, country, timezone, check-in/out times, currency, deposit %. No logo, phone, tagline, brand colors, or email template config. |
| 47 | **Design tokens / CSS variables** | Generates `:root` CSS variables from brand config (colors, spacing, typography, shadows). | None | **Missing** — Not relevant for PMS (uses Tailwind). |
| 48 | **JSON-LD schema markup** | Generates WebSite, LodgingBusiness, Accommodation, and WebPage structured data for SEO. Includes ReserveAction, amenities, pricing, geo coordinates. | Reads MPHB room type data | **Missing** — PMS has no structured data / SEO markup. |

### 1.11 Admin & Roles

| # | Feature | What it does | MPHB dependency | PMS status |
|---|---------|-------------|-----------------|------------|
| 49 | **Role-based access control** | Custom "Operator" role with `shaped_view_ops` capability. Operators see: ops dashboard, reviews, pages. Cannot access settings, plugins, users. | WordPress roles system | **Partial** — PMS `users` table has role enum (owner, manager, front_desk) but Better Auth is not implemented. Dashboard is currently publicly accessible (noted as known gap in M6). |
| 50 | **Ops dashboard** | Quick stats (bookings, revenue, occupancy), recent bookings list, payment status indicators. | Reads MPHB booking data | **Partial** — PMS `/dashboard` page has KPI cards (arrivals, departures, in-house, occupancy) but values are hardcoded to 0. Not connected to live data. |
| 51 | **Stripe credential management** | AES-256-CBC encrypted storage of Stripe keys in DB. Constant-based override takes priority. Admin UI for key entry. | None | **Exists** — PMS uses environment variables for Stripe keys (standard Next.js pattern, more secure than DB storage). |

### 1.12 Booking Rules

| # | Feature | What it does | MPHB dependency | PMS status |
|---|---------|-------------|-----------------|------------|
| 52 | **Min/max stay rules** | Minimum and maximum night restrictions per room type. | Entirely handled by MPHB (not in Shaped Core) | **Missing** — PMS schema has no booking rules table. The build plan doesn't include one. |
| 53 | **Check-in/out day restrictions** | Restrict which days of the week check-in or check-out is allowed. | Entirely handled by MPHB | **Missing** |

### 1.13 Other

| # | Feature | What it does | MPHB dependency | PMS status |
|---|---------|-------------|-----------------|------------|
| 54 | **Confirmation code generator** | `SHP-XXXXX` format using crypto-random characters, excluding ambiguous chars (0/O, 1/I). Collision-safe retry loop. | None | **Exists** — `generateConfirmationCode()` in `lib/confirmation-code.ts`. |
| 55 | **Booking status transitions** | pending → confirmed → checked_in → checked_out; pending/confirmed → cancelled; confirmed → no_show. Each transition triggers appropriate side effects (inventory, payments, emails). | Updates MPHB booking status | **Exists** — Full status transition system in dashboard reservation actions. |
| 56 | **Guest profile with lifetime stats** | Upsert by (property_id, email). Tracks total_stays and total_spent_cents, updated on each booking. | None | **Exists** — `guests` table with stats updated in `createReservation` action. |

---

## Section 2 — Implementation Plan

### Milestone 7: Amenities & Room Details

**What:** Add amenities/facilities system so room types can display features (Wi-Fi, parking, sea view, etc.) in the booking engine and dashboard.

**Files/tables affected:**
- `src/db/schema.ts` — new tables
- `src/app/(dashboard)/settings/room-types/` — edit amenity assignments
- `src/app/(booking)/[propertySlug]/` — display amenities on room cards
- `src/db/seed.ts` — seed amenities for Preelook

**Schema changes:**
```
amenities
├── id (uuid, pk)
├── property_id (fk → properties)
├── name (e.g. "Free Wi-Fi")
├── slug (e.g. "free-wifi")
├── icon (e.g. "wifi" — Lucide icon name)
├── sort_order (int)
├── created_at
└── updated_at
UNIQUE: (property_id, slug)

room_type_amenities
├── room_type_id (fk → room_types)
├── amenity_id (fk → amenities)
PRIMARY KEY: (room_type_id, amenity_id)
```

**Dependencies:** None

**Done when:**
- Amenities can be created/edited/deleted from dashboard settings
- Amenities can be assigned to room types via multi-select
- Booking engine room cards show amenity icons + labels
- Preelook seed includes realistic amenities (Wi-Fi, A/C, kitchen, parking, etc.)

---

### Milestone 8: Dashboard Live KPIs + Ops Dashboard

**What:** Connect the dashboard overview page to live data. Show today's arrivals, departures, in-house guests, tonight's occupancy, recent bookings, and revenue summary.

**Files/tables affected:**
- `src/app/(dashboard)/dashboard/page.tsx` — replace hardcoded values with queries
- `src/lib/dashboard.ts` — new file for dashboard query functions

**Schema changes:** None

**Dependencies:** None

**Done when:**
- Today's Arrivals: count of reservations with `check_in = today` and status `confirmed`
- Today's Departures: count of reservations with `check_out = today` and status `checked_in`
- In-House Guests: count of reservations with status `checked_in`
- Tonight's Occupancy: `(checked_in reservations) / (total rooms)` as percentage
- Recent bookings list with status and payment badges
- Revenue summary (this month vs last month)

---

### Milestone 9: Admin Email Notifications

**What:** Send admin notification emails on new bookings and cancellations. Add duplicate-send prevention for confirmation emails.

**Files/tables affected:**
- `src/lib/email.ts` — new `sendAdminBookingNotification` and `sendAdminCancellationNotification` functions
- `src/components/emails/` — new admin email templates
- `src/app/(booking)/[propertySlug]/actions.ts` — fire admin notification after booking
- `src/app/(dashboard)/reservations/actions.ts` — fire admin notification after cancellation

**Schema changes:** None (uses existing `email_logs` table)

**Dependencies:** None

**Done when:**
- Property admin receives email on every new booking with guest details, room, dates, payment info
- Property admin receives email on every cancellation with reason and refund status
- Confirmation email checks `email_logs` before sending to prevent duplicates

---

### Milestone 10: Guest Self-Service Portal

**What:** Public page where guests can view their booking details and cancel (if policy allows). Accessed via a link in the confirmation email.

**Files/tables affected:**
- `src/app/(booking)/manage/[confirmationCode]/page.tsx` — new route
- `src/app/(booking)/manage/[confirmationCode]/actions.ts` — guest cancellation action
- `src/components/emails/booking-confirmation.tsx` — add "Manage Booking" link
- `src/lib/validators.ts` — new schema for manage-booking token

**Schema changes:**
```
Add to reservations table:
├── manage_token (varchar, unique, nullable — generated on booking creation)
```

**Dependencies:** None

**Done when:**
- Confirmation email includes "Manage Your Booking" link
- Guest visits `/manage/SHP-XXXXX?token=abc` and sees booking details, payment status, check-in info
- Guest can cancel booking (if not already checked in or past a cancellation deadline)
- Cancellation triggers same refund + inventory rollback + email flow as dashboard cancellation
- Invalid/expired tokens show appropriate error

---

### Milestone 11: Cancellation Policy

**What:** Configurable cancellation policy per property. Determines whether a cancellation results in a full refund, partial refund, or no refund.

**Files/tables affected:**
- `src/db/schema.ts` — new columns on `properties`
- `src/app/(dashboard)/settings/property/` — policy configuration UI
- `src/app/(dashboard)/reservations/actions.ts` — apply policy on cancellation
- Guest self-service portal — show policy before confirming cancellation
- Email templates — include refund amount based on policy

**Schema changes:**
```
Add to properties table:
├── cancellation_policy (enum: flexible | moderate | strict)
├── cancellation_deadline_days (int, default 7 — days before check-in)
```
- `flexible`: full refund if cancelled before deadline
- `moderate`: 50% refund if cancelled before deadline, no refund after
- `strict`: no refund after booking

**Dependencies:** Milestone 10 (guest portal)

**Done when:**
- Property can set cancellation policy from settings
- Dashboard cancellation applies correct refund amount based on policy + timing
- Guest portal shows policy terms before cancellation
- Cancellation email shows actual refund amount

---

### Milestone 12: Booking Rules (Min/Max Stay, Day Restrictions)

**What:** Per-room-type rules for minimum/maximum stay length and check-in/check-out day restrictions. Enforced in both booking engine and REST API.

**Files/tables affected:**
- `src/db/schema.ts` — new table
- `src/lib/availability.ts` — enforce rules in availability checks
- `src/app/(booking)/[propertySlug]/` — validate in search + show rule violations
- `src/app/api/v1/reservations/route.ts` — enforce in API
- `src/app/(dashboard)/settings/room-types/` — rules configuration UI

**Schema changes:**
```
booking_rules
├── id (uuid, pk)
├── property_id (fk → properties)
├── room_type_id (fk → room_types)
├── min_nights (int, nullable)
├── max_nights (int, nullable)
├── allowed_check_in_days (int[] — 0=Sun..6=Sat, nullable — null means all days)
├── allowed_check_out_days (int[] — nullable)
├── date_start (date, nullable — null means always active)
├── date_end (date, nullable)
├── created_at
└── updated_at
```

**Dependencies:** None

**Done when:**
- Rules can be configured per room type from dashboard (with optional date ranges for seasonal rules)
- Booking engine prevents selection of invalid date ranges and shows clear error messages
- REST API returns 400 with rule violation details
- Availability calendar reflects rules (unavailable dates grayed out)

---

### Milestone 13: Scheduled Charges (Dual-Flow Payments)

**What:** Implement the second payment mode: save payment method for future charge. Bookings far out get charged automatically N days before check-in; bookings within threshold get charged immediately.

**Files/tables affected:**
- `src/db/schema.ts` — new columns on `payments`, new table for scheduled jobs
- `src/lib/payments.ts` — SetupIntent creation, deferred charge execution
- `src/app/(booking)/[propertySlug]/actions.ts` — dual-flow logic in booking action
- `src/app/(booking)/[propertySlug]/step-confirm.tsx` — SetupIntent UI for deferred bookings
- `src/app/api/v1/cron/` — new cron endpoint for charge execution
- `src/app/(dashboard)/settings/property/` — threshold configuration

**Schema changes:**
```
Add to properties table:
├── scheduled_charge_threshold_days (int, default 7)

Add to payments table:
├── stripe_setup_intent_id (nullable)
├── stripe_payment_method_id (nullable)
├── scheduled_charge_at (timestamp, nullable)
├── charge_attempts (int, default 0)

scheduled_charge_log
├── id (uuid, pk)
├── payment_id (fk → payments)
├── attempted_at (timestamp)
├── status (succeeded | failed)
├── error_message (nullable)
├── created_at
└── updated_at
```

**Dependencies:** None, but Milestone 11 (cancellation policy) should ideally land first since scheduled charges interact with cancellation refund logic.

**Done when:**
- Property can configure payment mode (full | deposit | scheduled) and threshold days
- Bookings beyond threshold: Stripe SetupIntent saves card, payment row created with `scheduled_charge_at`
- Bookings within threshold: immediate charge via Payment Intent (existing flow)
- Vercel cron runs daily, finds payments due, charges saved payment methods
- Failed charges retry with exponential backoff (max 3 attempts)
- Dashboard shows "Charge scheduled for [date]" on payment card
- Successful charge triggers confirmation email update

---

### Milestone 14: Abandoned Booking Cleanup

**What:** Detect and clean up bookings that were started but never completed (payment not received within a timeout window).

**Files/tables affected:**
- `src/app/api/v1/cron/` — new or extended cron job
- `src/app/(dashboard)/reservations/actions.ts` — abandonment handling
- `src/lib/email.ts` — optional abandonment email

**Schema changes:**
```
Add to reservations table:
├── checkout_started_at (timestamp, nullable — set when payment flow begins)
```

**Dependencies:** None

**Done when:**
- `checkout_started_at` is set when `createPaymentIntentForBooking` is called
- Cron job (every 5 minutes) finds reservations with `status = 'pending'` and `checkout_started_at` older than 15 minutes
- Abandoned reservations: status set to `cancelled`, inventory rolled back, Stripe PI cancelled
- Optional: abandonment email sent to guest with link to rebook

---

### Milestone 15: Discount System

**What:** Percentage-based discounts per room type, with optional seasonal overrides. Stacks with (applies on top of) rate plan pricing.

**Files/tables affected:**
- `src/db/schema.ts` — new table
- `src/lib/pricing.ts` — apply discounts in rate resolution
- `src/app/(dashboard)/rates/` — discount management UI
- `src/app/(booking)/[propertySlug]/step-select.tsx` — show original + discounted price

**Schema changes:**
```
discounts
├── id (uuid, pk)
├── property_id (fk → properties)
├── room_type_id (fk → room_types, nullable — null means all room types)
├── name (e.g. "Early Bird", "Summer Special")
├── percentage (int, 1-100)
├── date_start (date, nullable)
├── date_end (date, nullable)
├── status (active | inactive)
├── created_at
└── updated_at
```

**Dependencies:** None

**Done when:**
- Discounts can be created/edited/deleted from the rates page
- `resolveRate()` applies active discount percentage after resolving the base/plan rate
- Booking engine shows strikethrough original price + discounted price + "X% off" badge
- REST API availability endpoint returns discounted rates
- Total calculation uses discounted rate per night

---

### Milestone 16: Property Branding & Configuration

**What:** Extend properties table with branding fields (logo, phone, tagline, contact info) used in emails, booking engine, and structured data.

**Files/tables affected:**
- `src/db/schema.ts` — new columns on `properties`
- `src/app/(dashboard)/settings/property/` — extended form
- `src/components/emails/` — use branding in templates
- `src/app/(booking)/[propertySlug]/` — show branding in booking engine

**Schema changes:**
```
Add to properties table:
├── tagline (text, nullable)
├── phone (varchar, nullable)
├── email (varchar, nullable)
├── logo_url (text, nullable)
├── maps_url (text, nullable)
├── latitude (decimal, nullable)
├── longitude (decimal, nullable)
├── check_in_instructions (text, nullable)
├── website_url (text, nullable)
```

**Dependencies:** None

**Done when:**
- Property settings form includes all new fields
- Email templates use logo, phone, check-in instructions from property record
- Booking engine search step shows property info (address, phone)
- Data available for future structured data / SEO work

---

### Milestone 17: JSON-LD Structured Data

**What:** Generate Schema.org JSON-LD markup for the booking engine pages (LodgingBusiness, Accommodation, ReserveAction) for SEO.

**Files/tables affected:**
- `src/app/(booking)/[propertySlug]/layout.tsx` — inject JSON-LD in `<head>`
- `src/lib/schema.ts` — new file for structured data generation

**Schema changes:** None (uses data from Milestone 16)

**Dependencies:** Milestone 16 (property branding — needs logo, coordinates, phone)

**Done when:**
- Booking engine pages include valid JSON-LD in `<head>`
- LodgingBusiness entity with name, address, geo, amenities, price range
- Accommodation entities per room type with occupancy, offers, images
- Passes Google Rich Results Test validator

---

### Milestone 18: Authentication & RBAC

**What:** Implement Better Auth for dashboard access. Role-based access control for owner/manager/front_desk roles.

**Files/tables affected:**
- `src/app/(dashboard)/layout.tsx` — session check, login redirect
- `src/app/auth/` — login/register/forgot-password pages
- `src/lib/auth.ts` — Better Auth configuration
- `src/db/schema.ts` — Better Auth tables (sessions, accounts, etc.)

**Schema changes:** Better Auth's required tables (sessions, accounts, verification tokens). The existing `users` table may need adaptation to match Better Auth's expected schema.

**Dependencies:** None

**Done when:**
- Dashboard requires authentication
- Login page with email/password
- Users can only access their own property's data
- Role-based UI: owners see everything, managers see operations + settings, front_desk sees reservations + guests only
- Existing `users` table integrated with Better Auth

---

### Milestone 19: Channel Manager Integration (RoomCloud Adapter)

**What:** PMS-authoritative channel manager integration. PMS is the source of truth for inventory and rates. The channel manager is a distribution layer — it receives inventory/rates from PMS and sends OTA bookings back. First adapter: RoomCloud (XML OTA API v3.8). Architecture supports future adapters for other CMs.

**Design principles:**
- PMS owns inventory and rates. Channel manager never overwrites PMS data.
- All outbound pushes originate from PMS mutations (booking created/cancelled, rate changed, inventory blocked).
- Inbound `modify` messages are accepted (protocol compliance) but if they disagree with PMS inventory, PMS immediately pushes its own numbers back (self-correction).
- Inbound `reservation` messages create reservations through PMS's own inventory logic.
- Loop prevention: bookings received from CM are flagged so they aren't pushed back out.

**Files/tables affected:**
- `src/db/schema.ts` — new tables
- `src/lib/channel-manager/` — adapter interface + RoomCloud adapter
- `src/app/api/v1/channel-manager/webhook/route.ts` — inbound webhook endpoint
- `src/app/api/v1/cron/` — outbound sync queue processor
- `src/app/(dashboard)/settings/channel-manager/` — mapping UI + sync log
- `src/app/(dashboard)/reservations/actions.ts` — fire outbound push on status changes
- `src/app/(booking)/[propertySlug]/actions.ts` — fire outbound push on direct booking
- `src/lib/availability.ts` — fire outbound push on inventory changes

**Schema changes:**
```
channel_manager_configs
├── id (uuid, pk)
├── property_id (fk → properties)
├── provider (enum: roomcloud — extensible for future CMs)
├── credentials (jsonb, encrypted — e.g. {username, password, service_url})
├── hotel_id (varchar — property's ID in the CM system)
├── rate_id (varchar — default rate plan ID in CM)
├── is_active (boolean, default false)
├── created_at
└── updated_at
UNIQUE: (property_id, provider)

channel_mappings
├── id (uuid, pk)
├── property_id (fk → properties)
├── config_id (fk → channel_manager_configs)
├── room_type_id (fk → room_types)
├── external_room_id (varchar — room type ID in CM system)
├── created_at
└── updated_at
UNIQUE: (config_id, external_room_id)

sync_queue
├── id (uuid, pk)
├── property_id (fk → properties)
├── config_id (fk → channel_manager_configs)
├── direction (enum: outbound | inbound)
├── operation (enum: availability_push | rate_push | restriction_push | reservation_push | reservation_cancel)
├── payload (jsonb — room_type_id, dates, values, etc.)
├── status (enum: pending | processing | completed | failed)
├── attempts (int, default 0)
├── max_attempts (int, default 5)
├── last_error (text, nullable)
├── next_retry_at (timestamp, nullable)
├── created_at
└── updated_at

sync_log
├── id (uuid, pk)
├── property_id (fk → properties)
├── config_id (fk → channel_manager_configs)
├── direction (enum: outbound | inbound)
├── message_type (varchar — e.g. "modify", "reservation", "getHotels")
├── status (enum: success | error)
├── request_summary (text, nullable — sanitized, no credentials)
├── response_summary (text, nullable)
├── error_message (text, nullable)
├── created_at
└── updated_at

Add to reservations table:
├── channel_manager_source (boolean, default false)
├── external_booking_id (varchar, nullable)
```

**Adapter interface (TypeScript):**
```typescript
interface ChannelManagerAdapter {
  // Outbound
  pushAvailability(mapping: ChannelMapping, date: string, available: number): Promise<void>
  pushRate(mapping: ChannelMapping, date: string, rateCents: number, currency: string): Promise<void>
  pushRestrictions(mapping: ChannelMapping, date: string, restrictions: {
    minimumStay?: number
    closedToArrival?: boolean
    closedToDeparture?: boolean
    closedToSell?: boolean
  }): Promise<void>
  pushReservation(reservation: Reservation, status: 'new' | 'confirmed' | 'cancelled'): Promise<void>

  // Inbound (protocol compliance — CM polls these)
  handleGetHotels(property: Property): XMLResponse
  handleGetRates(property: Property, ratePlans: RatePlan[]): XMLResponse
  handleGetRooms(property: Property, roomTypes: RoomType[], mappings: ChannelMapping[]): XMLResponse
  handleView(property: Property, startDate: string, endDate: string): XMLResponse

  // Inbound (data)
  parseReservation(rawPayload: unknown): ParsedReservation
  parseModify(rawPayload: unknown): ParsedModify

  // Connection testing
  testOutbound(): Promise<{success: boolean, message: string}>
  testInbound(): Promise<{success: boolean, message: string}>
}
```

**Outbound push triggers:**
| PMS event | What gets pushed |
|---|---|
| Reservation created (direct or API) | Availability for affected room type + dates |
| Reservation cancelled | Availability for affected room type + dates |
| Reservation confirmed / checked-in | Reservation status update |
| Room added / deleted | Availability for affected room type (all dates) |
| Inventory manually blocked / unblocked | Availability for affected dates |
| Rate plan created / updated / deleted | Rates for affected room type + dates |
| Rate override set on calendar | Rate for that specific date |
| Booking rule changed | Restrictions for affected room type + dates |

**Inbound message handling:**
| Message | Action |
|---|---|
| `getHotels` | Respond with property ID + name from `properties` table |
| `getRates` | Respond with rate plan IDs + names from `rate_plans` table |
| `getRooms` | Respond with mapped room types, occupancy, associated rates |
| `view` | Respond with current availability + rates from `inventory` + `rate_plans` |
| `modify` | Return `<ok/>`. Compare stated availability with PMS. If different, queue outbound push with PMS values (self-correction). Log the discrepancy. |
| `reservation` (new/modified) | Create or update reservation via PMS inventory logic. Flag `channel_manager_source = true`. No Stripe. Fire confirmation email. |
| `reservation` (cancelled) | Cancel reservation, release inventory. Flag prevents outbound push loop. |
| `reservations` (pull) | Respond with direct bookings since last pull (or empty if push-only) |

**Dependencies:** Milestone 12 (booking rules — needed for restriction pushes)

**Done when:**
- RoomCloud adapter handles all 7 message types per API spec v3.8
- Direct bookings trigger outbound availability push within seconds
- OTA bookings received via webhook create reservations with correct inventory decrement
- Rate/availability changes in PMS dashboard trigger outbound pushes
- Inbound `modify` with wrong availability triggers self-correction push
- Retry queue processes failed outbound pushes (15-min delay, max 5 attempts)
- Admin settings page: CM credentials, room type mapping, connection test, sync log
- Loop prevention: bookings from CM not pushed back to CM
- All XML responses are well-formed (no content before XML declaration)
- Passes RoomCloud onboarding test suite (getHotels → getRates → getRooms → modify → reservation round-trip)

---

### Milestone 20: OTA Review Syndication

**What:** Import and display reviews from OTA platforms (Booking.com, Google, Airbnb, etc.) alongside direct reviews.

**Files/tables affected:**
- `src/db/schema.ts` — new columns on `reviews`
- `src/lib/reviews.ts` — import logic
- `src/app/(dashboard)/reviews/` — source filter, import UI
- `src/app/(booking)/[propertySlug]/` — show OTA reviews in booking engine

**Schema changes:**
```
Add to reviews table:
├── source (enum: direct | booking_com | google | tripadvisor | airbnb | expedia, default 'direct')
├── external_id (varchar, nullable — deduplication key)
├── source_url (text, nullable — link to original review)
├── source_rating_raw (decimal, nullable — original scale rating, e.g. 9.2 for Booking.com)
```

**Dependencies:** None

**Done when:**
- API endpoint or admin action to import reviews from external source (JSON format)
- Deduplication by `external_id` prevents duplicate imports
- Dashboard reviews page can filter by source
- Booking engine shows mixed direct + OTA reviews
- Rating normalization (Booking.com 0–10 → 1–5 stars)

---

## Priority Summary

| Priority | Milestone | Revenue impact | Complexity |
|----------|-----------|---------------|------------|
| 1 | M7: Amenities | Low (UX) | Low |
| 2 | M8: Live KPIs | Low (ops) | Low |
| 3 | M9: Admin emails | Medium (ops) | Low |
| 4 | M10: Guest portal | Medium (CX) | Medium |
| 5 | M11: Cancellation policy | Medium (revenue) | Medium |
| 6 | M12: Booking rules | Medium (correctness) | Medium |
| 7 | M13: Scheduled charges | High (revenue) | High |
| 8 | M14: Abandoned cleanup | Medium (data hygiene) | Low |
| 9 | M15: Discounts | Medium (revenue) | Medium |
| 10 | M16: Property branding | Low (polish) | Low |
| 11 | M17: JSON-LD schema | Low (SEO) | Low |
| 12 | M18: Auth & RBAC | High (security) | Medium |
| 13 | M19: Channel manager | High (distribution) | High |
| 14 | M20: OTA reviews | Low (social proof) | Medium |

---

## Appendix A — RoomCloud Integration: Architecture & Spec Reference

### A.1 Data Ownership Model

```
PMS (Source of Truth)
├── Hotelier manages rates, availability, restrictions in PMS dashboard
├── PMS pushes changes to Channel Manager (outbound modify)
├── Channel Manager distributes to Booking.com, Airbnb, Expedia, etc.
│
├── Guest books on Booking.com
├── Channel Manager sends reservation to PMS (inbound)
├── PMS creates reservation, decrements own inventory
├── PMS pushes updated availability to CM (outbound)
├── CM distributes updated availability to all other OTAs
│
├── Guest books direct on PMS booking engine
├── PMS creates reservation, decrements own inventory
├── PMS pushes reservation + updated availability to CM (outbound)
├── CM distributes to all OTAs
│
├── Someone changes availability directly in CM dashboard
├── CM sends modify to PMS (inbound)
├── PMS compares with own inventory, finds discrepancy
├── PMS immediately pushes its own numbers back (self-correction)
├── CM overwrites its state with PMS values
```

**Key principle:** The PMS inventory table is never overwritten by external data. The `modify` handler reads PMS inventory, compares, and corrects the channel manager if they disagree. The channel manager learns to converge to PMS state.

### A.2 Why the Shaped Core Approach Doesn't Apply

In the WordPress plugin, RoomCloud was the inventory authority. Three gating layers were needed because MPHB (the booking engine) had its own availability system that RoomCloud needed to override:

1. **Stay-level gating** — cap MPHB's room count search results based on RC inventory
2. **Per-day calendar blocking** — mark dates with 0 RC availability as unselectable
3. **Individual room page blocking** — prevent booking on room pages when RC says unavailable

None of this applies to the PMS because there is only one inventory system. The booking engine, dashboard calendar, REST API, and channel manager integration all read from the same `inventory` table. No reconciliation needed.

### A.3 RoomCloud XML OTA API v3.8 — Spec vs Shaped Core Implementation

This section documents what the existing PHP implementation gets wrong or ignores. The PMS implementation should build against the spec, not the PHP code.

#### A.3.1 Modify Handler — Bugs in Existing PHP

| Issue | Severity | Detail |
|---|---|---|
| **Missing `quantity` treated as 0** | Critical | PHP uses `intval($avail['quantity'])` — if `quantity` attribute is omitted (valid per spec for partial updates), this stores 0, incorrectly marking the date as sold out. Spec says: "Mandatory availability attributes are day and roomId. Attribute quantity as well as the subelements are optional. In case of partial update, all omitted values should be left unchanged." |
| **Rate data discarded** | Medium | Each `<availability>` element can contain `<rate>` sub-elements with `price`, `currency`, `minimumStay`, `coa`, `cod`, `closed`. The PHP handler ignores all of these. In the PMS model this is less critical (PMS owns rates) but the data should be logged for reconciliation. |
| **Occupancy/supplement prices discarded** | Low | `<occupancy>`, `<adult>`, `<child>` sub-elements with per-day pricing are ignored. Not critical if PMS doesn't do per-occupancy channel pricing. |

#### A.3.2 Reservation Handler — Missing Fields

**Status codes the PHP handler doesn't handle:**

| Code | Meaning | PHP handles? | PMS should handle? |
|---|---|---|---|
| 2 | SUBMITTED | Yes | Yes |
| 4 | CONFIRMED | Yes | Yes |
| 5 | REJECTED | No | Yes (log + alert) |
| 6 | NO SHOW | No | Yes (update reservation status) |
| 7 | DELETED/CANCELLED | Yes | Yes |
| 8 | MODIFIED | No — creates duplicate booking | Yes — must update existing reservation |

**Reservation attributes the PHP handler ignores:**

| Attribute | Spec mandatory | What PMS should do |
|---|---|---|
| `adults` | Yes | Store on reservation (PHP hardcodes to 2) |
| `children` | No | Store on reservation (PHP hardcodes to 0) |
| `rooms` | No | Support multi-room bookings |
| `prepaid` | No | Store as payment info (amount already collected by OTA) |
| `currency` | No | Store (PHP hardcodes EUR) |
| `notes` | No | Store as `special_requests` on reservation |
| `source_of_business` | No | Store as channel attribution |
| `creation_date` | Yes | Store as original booking timestamp |

**Sub-elements the PHP handler ignores:**

| Element | What it contains | What PMS should do |
|---|---|---|
| `<supplement>` | Extras (breakfast, parking, shuttle, etc.) with predefined IDs like `ota_bb`, `ota_parking` | Store as line items. 25+ predefined supplement types in the spec. |
| `<dayPrice>` | Per-night price breakdown from OTA | Store for revenue reporting / rate parity checks |
| `<childAge>` | Age of each child | Store for occupancy compliance |
| `<guest>` | Per-room guest details | Store for multi-room bookings |
| `<offer>` | Promotions/discounts applied by OTA | Store for rate parity analysis |

#### A.3.3 View Handler — Not Implemented

The spec defines a `view` message where RoomCloud reads your current inventory. The PHP handler has no case for this. In the PMS-as-source-of-truth model, `view` is the most important inbound message — it's how RC pulls your authoritative data.

**Request:** `<view hotelId="X" startDate="YYYY-MM-DD" endDate="YYYY-MM-DD"/>`

**Response:** For each room type and date in range, return:
- `<availability day="..." roomId="..." quantity="N">` — available units
- `<rate rateId="..." price="..." currency="..." minimumStay="..." coa="..." cod="..." closed="..."/>` — per-rate pricing and restrictions

This response is computed from PMS `inventory` table (for quantity) and `rate_plans` + `inventory.rate_override_cents` (for pricing).

#### A.3.4 Outbound Reservation Push — Minor Issues

The PHP outbound XML is mostly correct. Issues to fix in PMS port:
- `adults` defaults to 2 if not found in booking data — should use actual values
- No `<supplement>` elements in outbound XML — if PMS adds extras/supplements, they should be included
- Per-night price breakdown handles rounding drift correctly (last night gets remainder) — preserve this
- Push URL format matches spec: `service_url?hotelId=X&reservationId=Y`

#### A.3.5 Error Responses

The spec expects XML error responses: `<Response><error message="..." code="..."/></Response>`

The PHP handler returns JSON for auth failures and unknown message types. The PMS must return XML for all responses on the channel manager webhook endpoint.

**Error codes from spec:**
- 2001/2002/2003/2004: Transient errors (CM may retry)
- 2010/2011: Malformed request (CM will not retry)
- 2100/2101/2103: Auth errors (CM will not retry, requires operator action)
- 2202/2203: Mapping errors (CM will not retry)

#### A.3.6 echoToken

The spec shows `echoToken` as an optional attribute on `<Request>` and `<Response>`. If present in the request, it should be echoed back in the response. The PHP handler ignores it. The PMS should echo it back for request correlation.

#### A.3.7 Reservations Pull Mode

The spec defines `<reservations hotelId="X" useDLM="true"/>` for periodic polling (every 15 minutes with DLM, every 2 hours if push mode is active). The PHP handler returns an empty list. The PMS should return direct bookings that the CM hasn't seen yet, or continue returning empty if relying solely on push mode.

### A.4 Implementation Recommendation

Build the RoomCloud adapter against the API spec v3.8 directly, not by porting the PHP line-by-line. The PHP implementation has shortcuts (hardcoded values, missing status codes, ignored fields) that were acceptable for a single property but would cause issues at scale.

Use `fast-xml-parser` for XML parsing and building. The RoomCloud protocol is XML-in, XML-out on a single endpoint. All messages are distinguished by the child element of `<Request>`.

**Testing:** RoomCloud provides a test page at `https://apitest.roomcloud.net/be/ota/testOtaApi.jsp` and a test account. The onboarding process sends `getHotels` → `getRates` → `getRooms` → `modify` → `reservation` to verify your endpoint. Build and verify in that order.

**Reference:** RoomCloud XML OTA API v3.8 (05/04/2024), 21 pages. The spec is the source of truth for the XML protocol. This appendix summarizes the key findings but the implementing agent should have access to the full spec document.
