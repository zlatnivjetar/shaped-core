# Shaped Discounts — Remote Control via Dashboard

## Goal

Expose the discount and payment-mode settings currently managed inside the
WordPress admin page **Shaped Pricing** (`core/class-pricing.php`) through
authenticated REST endpoints, so the shaped-dashboard can read and write them
remotely.

Work is split into two phases:
1. **Phase 1 (this document)** — plugin-side API endpoints in shaped-core
2. **Phase 2 (brief outline)** — dashboard-side UI wired to those endpoints

---

## Phase 1 — REST API Endpoints in shaped-core (COMPLETED)

## Phase 2 — Dashboard UI (brief outline)

Once the API endpoints above are deployed and verified:

1. Extend `PropertyApiClient` (`lib/api/client.ts`) with `getPricing()`, `updateDefaults()`, `updateSeasons()`, `updatePayment()` methods
2. Add TypeScript types for the pricing API response shapes in `lib/types.ts`
3. Create a pricing page at `app/(dashboard)/[propertySlug]/pricing/page.tsx` with read access for owners, write access gated by role or a new permission
4. Build a default discounts editor — table of room types with number inputs (0–100%), save button
5. Build a recurring seasons editor — list of date-range rows with per-room-type discount inputs, add/remove rows
6. Build a year-specific overrides editor — same as recurring but with full date pickers instead of mm-dd
7. Build a payment mode section — radio toggle (scheduled vs deposit) with conditional fields (deposit %, charge threshold days)
8. Wire server actions that validate input client-side, call the plugin API, and revalidate the page cache
9. Show last-updated timestamp and optimistic UI feedback on save
10. Handle API errors gracefully — surface 400/422 validation messages from the plugin (e.g. overlapping ranges) as inline form errors
