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

## Phase 1 — REST API Endpoints in shaped-core

### 1.1 Where the code lives

| Concern | File |
|---------|------|
| Endpoint registration & handlers | New file: `includes/class-dashboard-pricing-api.php` |
| Autoloader entry | `includes/class-loader.php` — add `Shaped_Dashboard_Pricing_Api` |
| Bootstrap | `shaped-core.php` — call `Shaped_Dashboard_Pricing_Api::init()` after `Shaped_Dashboard_Api::init()` |
| Existing discount logic (reused, not duplicated) | `core/class-pricing.php` (`Shaped_Pricing`) |

A separate class keeps pricing endpoints isolated from the existing
`Shaped_Dashboard_Api` class while following the same patterns.

### 1.2 Auth & conventions

- Namespace: `shaped/v1` (same as existing dashboard endpoints)
- Auth: `X-Shaped-API-Key` header, verified with `shaped_dashboard_auth` permission callback (already defined in `class-dashboard-api.php`)
- Responses: `WP_REST_Response($data, $status)`, errors via `WP_Error($code, $msg, ['status' => $http])`
- Cache headers: `no-store, no-cache, must-revalidate, max-age=0, private` (same as other dashboard endpoints)
- Timestamps: `gmdate('c')` (ISO 8601)

### 1.3 Endpoints

#### GET `/dashboard/pricing`

Returns the full pricing configuration in one call.

**Response 200:**

```json
{
  "room_types": {
    "suite": "Suite",
    "apartment": "Apartment",
    "triple-room": "Triple Room",
    "double-room": "Double Room"
  },
  "defaults": {
    "suite": 10,
    "apartment": 10,
    "triple-room": 10,
    "double-room": 10
  },
  "seasons": {
    "recurring": [
      {
        "start_day": "01-06",
        "end_day": "08-31",
        "label": "Low Season",
        "discounts": { "suite": 5, "apartment": 5, "triple-room": 5, "double-room": 5 }
      }
    ],
    "overrides": [
      {
        "start_date": "2026-07-15",
        "end_date": "2026-08-31",
        "label": "Summer 2026",
        "discounts": { "suite": 20, "apartment": 20, "triple-room": 20, "double-room": 20 }
      }
    ]
  },
  "payment": {
    "mode": "scheduled",
    "deposit_percent": 30,
    "scheduled_charge_threshold": 7
  },
  "generated_at": "2026-04-15T10:00:00+00:00"
}
```

Implementation notes:
- `room_types` comes from `Shaped_Pricing::fetch_room_types()` (MotoPress post type query)
- `defaults` from `Shaped_Pricing::get_discounts()`
- `seasons` from `Shaped_Pricing::get_discount_seasons()`
- `payment.mode` from `Shaped_Pricing::get_payment_mode()`
- `payment.deposit_percent` from `Shaped_Pricing::get_deposit_percent()`
- `payment.scheduled_charge_threshold` from `Shaped_Pricing::get_scheduled_threshold_days()`

---

#### PUT `/dashboard/pricing/defaults`

Update default flat discounts per room type.

**Request body:**

```json
{
  "discounts": {
    "suite": 15,
    "apartment": 10,
    "triple-room": 12,
    "double-room": 8
  }
}
```

**Handling:**
1. Parse JSON body via `$request->get_json_params()`
2. Validate `discounts` key exists and is an array
3. Pass through `Shaped_Pricing::sanitize_discounts($input['discounts'])` — this already clamps 0–100 and validates against actual room types
4. `update_option(Shaped_Pricing::OPT_DISCOUNTS, $sanitized)`
5. Return the saved value in the response so the dashboard can confirm

**Response 200:**

```json
{
  "defaults": { "suite": 15, "apartment": 10, "triple-room": 12, "double-room": 8 },
  "updated_at": "2026-04-15T10:00:00+00:00"
}
```

**Errors:**
- 400 if `discounts` is missing or not an array

---

#### PUT `/dashboard/pricing/seasons`

Update seasonal discount configuration (both recurring and overrides).

**Request body:**

```json
{
  "recurring": [
    {
      "start_day": "01-06",
      "end_day": "08-31",
      "label": "Low Season",
      "discounts": { "suite": 5, "apartment": 5, "triple-room": 5, "double-room": 5 }
    }
  ],
  "overrides": [
    {
      "start_date": "2026-07-15",
      "end_date": "2026-08-31",
      "label": "Summer 2026",
      "discounts": { "suite": 20, "apartment": 20, "triple-room": 20, "double-room": 20 }
    }
  ]
}
```

**Handling:**
1. Parse JSON body
2. Validate top-level structure has `recurring` (array) and `overrides` (array)
3. Pass through `Shaped_Pricing::sanitize_discount_seasons($input)` — this validates date formats, detects overlapping ranges, and clamps discount values
4. `update_option(Shaped_Pricing::OPT_DISCOUNT_SEASONS, $sanitized)`
5. Return saved value

**Response 200:**

```json
{
  "seasons": { "recurring": [...], "overrides": [...] },
  "updated_at": "2026-04-15T10:00:00+00:00"
}
```

**Errors:**
- 400 if structure is invalid
- 422 if sanitization detects overlapping date ranges (need to surface the overlap error from `sanitize_discount_seasons` — currently it silently drops overlapping entries; we may want to return a warning or error instead for API consumers)

**Important note on `sanitize_discount_seasons`:** The existing method accepts dates in dd/mm (recurring) and dd/mm/yyyy (overrides) display format and converts to mm-dd / yyyy-mm-dd storage format. For API use, we should decide whether:
- **(a)** The API accepts storage format directly (mm-dd, yyyy-mm-dd) — simpler for the dashboard, no format conversion needed
- **(b)** The API accepts the same display format as the admin page

**Recommendation:** Option (a) — accept storage format. The admin page display format is a UI concern. The dashboard will work with ISO-ish dates natively. This means we either:
- Add an alternative sanitizer that expects storage format, or
- Pre-convert from storage format to display format before calling the existing sanitizer, or
- Adjust the existing sanitizer to detect which format it received

The cleanest approach: add a thin wrapper `sanitize_discount_seasons_from_api($input)` that validates the storage-format dates directly (simpler regex, no conversion step) and reuses the overlap-detection and discount-clamping logic from the existing method.

---

#### PUT `/dashboard/pricing/payment`

Update payment mode settings.

**Request body:**

```json
{
  "mode": "deposit",
  "deposit_percent": 30,
  "scheduled_charge_threshold": 7
}
```

**Handling:**
1. Parse JSON body
2. Sanitize each field using existing methods:
   - `Shaped_Pricing::sanitize_payment_mode($input['mode'])`
   - `Shaped_Pricing::sanitize_deposit_percent($input['deposit_percent'])`
   - `Shaped_Pricing::sanitize_scheduled_threshold($input['scheduled_charge_threshold'])`
3. Update three separate options:
   - `update_option(OPT_PAYMENT_MODE, ...)`
   - `update_option(OPT_DEPOSIT_PERCENT, ...)`
   - `update_option(OPT_SCHEDULED_CHARGE_THRESHOLD, ...)`
4. Return saved values

**Response 200:**

```json
{
  "payment": {
    "mode": "deposit",
    "deposit_percent": 30,
    "scheduled_charge_threshold": 7
  },
  "updated_at": "2026-04-15T10:00:00+00:00"
}
```

**Errors:**
- 400 if `mode` is missing or not `scheduled`/`deposit`

---

### 1.4 Implementation skeleton

```php
<?php
/**
 * REST endpoints for reading and writing pricing/discount config
 * via the external dashboard.
 */
class Shaped_Dashboard_Pricing_Api {

    const NAMESPACE = 'shaped/v1';

    public static function init(): void {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    public static function register_routes(): void {
        // GET  /dashboard/pricing
        register_rest_route(self::NAMESPACE, '/dashboard/pricing', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_pricing'],
            'permission_callback' => 'shaped_dashboard_auth',
        ]);

        // PUT  /dashboard/pricing/defaults
        register_rest_route(self::NAMESPACE, '/dashboard/pricing/defaults', [
            'methods'             => 'PUT',
            'callback'            => [__CLASS__, 'update_defaults'],
            'permission_callback' => 'shaped_dashboard_auth',
        ]);

        // PUT  /dashboard/pricing/seasons
        register_rest_route(self::NAMESPACE, '/dashboard/pricing/seasons', [
            'methods'             => 'PUT',
            'callback'            => [__CLASS__, 'update_seasons'],
            'permission_callback' => 'shaped_dashboard_auth',
        ]);

        // PUT  /dashboard/pricing/payment
        register_rest_route(self::NAMESPACE, '/dashboard/pricing/payment', [
            'methods'             => 'PUT',
            'callback'            => [__CLASS__, 'update_payment'],
            'permission_callback' => 'shaped_dashboard_auth',
        ]);
    }

    // ... handler methods call Shaped_Pricing getters/sanitizers
}
```

### 1.5 Testing strategy

Since shaped-core has no test framework yet, verify endpoints manually with
curl or a REST client before wiring the dashboard:

```bash
# Read all pricing config
curl -s -H "X-Shaped-API-Key: $KEY" \
  https://example.com/wp-json/shaped/v1/dashboard/pricing | jq .

# Update default discounts
curl -s -X PUT -H "X-Shaped-API-Key: $KEY" \
  -H "Content-Type: application/json" \
  -d '{"discounts":{"suite":15,"apartment":10,"triple-room":12,"double-room":8}}' \
  https://example.com/wp-json/shaped/v1/dashboard/pricing/defaults | jq .

# Update seasons
curl -s -X PUT -H "X-Shaped-API-Key: $KEY" \
  -H "Content-Type: application/json" \
  -d '{"recurring":[],"overrides":[]}' \
  https://example.com/wp-json/shaped/v1/dashboard/pricing/seasons | jq .

# Update payment mode
curl -s -X PUT -H "X-Shaped-API-Key: $KEY" \
  -H "Content-Type: application/json" \
  -d '{"mode":"deposit","deposit_percent":30,"scheduled_charge_threshold":7}' \
  https://example.com/wp-json/shaped/v1/dashboard/pricing/payment | jq .
```

**Checklist per endpoint:**
- [ ] 200 on valid request, response matches expected shape
- [ ] 401 without API key header
- [ ] 403 with wrong API key
- [ ] 400 on malformed body (missing keys, wrong types)
- [ ] Discount values clamped to 0–100
- [ ] Overlapping season ranges handled (error or silent drop — decide)
- [ ] GET reflects changes immediately after PUT
- [ ] WordPress admin "Shaped Pricing" page shows the same values after API update (round-trip consistency)

---

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
