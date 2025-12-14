# Core Modules Reference

> **Last generated:** 2025-12-08
> **Related entry:** [CLAUDE.md#IMPL-001](CLAUDE.md#2025-12-08-impl-001--initial-plugin-architecture)

Deep-dive documentation for each core class in shaped-core.

---

## Table of Contents

1. [Shaped_Pricing](#shaped_pricing)
2. [Shaped_Payment_Processor](#shaped_payment_processor)
3. [Shaped_Booking_Manager](#shaped_booking_manager)
4. [Shaped_Assets](#shaped_assets)
5. [Shaped_Admin](#shaped_admin)
6. [Shaped_Amenity_Mapper](#shaped_amenity_mapper)
7. [Shaped_Loader](#shaped_loader)
8. [Email Handlers](#email-handlers)

---

## Shaped_Pricing

**File:** `core/class-pricing.php` (508 lines)
**Purpose:** Discount system, payment mode configuration, pricing calculations

### Overview

The pricing class manages:
- Per-room discount percentages
- Payment mode selection (scheduled vs deposit)
- Deposit percentage configuration
- Price calculations with discounts applied

### Database Options

| Option | Type | Description |
|--------|------|-------------|
| `shaped_discounts` | array | `['room-slug' => percentage]` |
| `shaped_payment_mode` | string | `'scheduled'` or `'deposit'` |
| `shaped_deposit_percent` | int | 1-100 |

### Key Methods

#### `init()`

```php
public static function init(): void
```

Registers settings, admin menu, and hooks. Called during plugin bootstrap.

**Hooks:**
- `admin_menu` — Adds pricing settings page
- `admin_init` — Registers settings
- `wp_enqueue_scripts` — Localizes config to JS

---

#### `get_discounts()`

```php
public static function get_discounts(): array
```

Get saved discount percentages for all room types.

**Returns:** Array of `['room-slug' => int]`

**Example:**
```php
$discounts = Shaped_Pricing::get_discounts();
// ['studio-apartment' => 15, 'deluxe-suite' => 10]
```

---

#### `get_payment_mode()`

```php
public static function get_payment_mode(): string
```

Get the current payment mode.

**Returns:** `'scheduled'` or `'deposit'`

---

#### `get_deposit_percent()`

```php
public static function get_deposit_percent(): int
```

Get the deposit percentage (1-100).

**Returns:** Integer between 1 and 100

---

#### `calculate_deposit($total)`

```php
public static function calculate_deposit(float $total): array
```

Calculate deposit and balance breakdown.

**Parameters:**
- `$total` (float) — Total booking amount

**Returns:**
```php
[
    'deposit' => 150.00,    // Amount to charge now
    'balance' => 350.00,    // Amount due on arrival
    'percent' => 30         // Deposit percentage
]
```

**Example:**
```php
$breakdown = Shaped_Pricing::calculate_deposit(500.00);
// With 30% deposit: ['deposit' => 150, 'balance' => 350, 'percent' => 30]
```

---

#### `calculate_final_amount($base_or_booking, $room_slug)`

```php
public static function calculate_final_amount(
    float|WP_Post $base_or_booking,
    string $room_slug = ''
): float
```

Apply discounts to calculate final price.

**Parameters:**
- `$base_or_booking` — Base amount or booking post object
- `$room_slug` — Room type slug (optional if booking provided)

**Returns:** Final amount after discount

**Example:**
```php
// From base amount
$final = Shaped_Pricing::calculate_final_amount(100.00, 'studio-apartment');
// With 15% discount: 85.00

// From booking
$final = Shaped_Pricing::calculate_final_amount($booking);
```

---

#### `get_room_discount($room_slug)`

```php
public static function get_room_discount(string $room_slug): int
```

Get discount percentage for specific room type.

**Parameters:**
- `$room_slug` — Room type slug

**Returns:** Discount percentage (0-100)

---

#### `format_price($amount, $currency)`

```php
public static function format_price(float $amount, string $currency = 'EUR'): string
```

Format amount for display with currency symbol.

**Parameters:**
- `$amount` — Amount to format
- `$currency` — Currency code (default: EUR)

**Returns:** Formatted string like `€150.00`

---

### Hooks Triggered

| Hook | Type | When |
|------|------|------|
| `shaped/pricing/room_slugs` | Filter | Getting room type list |
| `shaped/pricing/discount_defaults` | Filter | Setting default discounts |

### Customization Example

```php
// Set property-wide 10% discount
add_filter('shaped/pricing/discount_defaults', function($defaults) {
    foreach ($defaults as $slug => $value) {
        $defaults[$slug] = 10;
    }
    return $defaults;
});
```

---

## Shaped_Payment_Processor

**File:** `core/class-payment-processor.php` (1009 lines)
**Purpose:** Stripe integration, webhook handling, scheduled charges

### Overview

The payment processor handles:
- Stripe Checkout session creation
- Payment intents vs setup intents (based on timing)
- Webhook processing
- Scheduled charge execution
- Refund processing

### Payment Modes

| Mode | When | What Happens |
|------|------|--------------|
| **Immediate** | < 7 days to check-in | Full payment charged now |
| **Delayed** | ≥ 7 days to check-in | Card saved, charge 7 days before |
| **Deposit** | Deposit mode enabled | Deposit charged now, balance on arrival |

### Post Meta Keys

| Meta Key | Type | Description |
|----------|------|-------------|
| `_shaped_payment_mode` | string | 'immediate', 'delayed', 'deposit' |
| `_shaped_payment_status` | string | 'pending', 'authorized', 'completed', 'deposit_paid', 'charge_failed' |
| `_shaped_deposit_amount` | float | Deposit amount charged |
| `_shaped_balance_due` | float | Remaining balance |
| `_stripe_customer_id` | string | Stripe customer ID |
| `_stripe_payment_method_id` | string | Saved payment method |
| `_stripe_checkout_session_id` | string | Stripe session ID |
| `_stripe_pending_amount` | float | Amount to charge later |
| `_shaped_charge_date` | string | When to charge (YYYY-MM-DD) |
| `_shaped_idempotency_key` | string | Idempotency key for scheduled charge |

### Key Methods

#### `get_payment_context($booking)`

```php
public static function get_payment_context(WP_Post $booking): array
```

Get payment information for a booking.

**Returns:**
```php
[
    'mode' => 'delayed',           // immediate|delayed|deposit
    'amount' => 500.00,            // Total amount
    'deposit' => 0,                // Deposit amount (if deposit mode)
    'balance' => 0,                // Balance due (if deposit mode)
    'charge_date' => '2025-01-08', // When card will be charged
    'days_until_checkin' => 14
]
```

---

#### `intercept_checkout_redirect()`

```php
public static function intercept_checkout_redirect(): void
```

Intercepts MPHB checkout and creates Stripe session instead.

**Flow:**
1. Validates booking data
2. Determines payment mode based on check-in date
3. Creates Stripe Checkout session (payment or setup)
4. Redirects to Stripe

---

#### `handle_stripe_webhook(WP_REST_Request $request)`

```php
public static function handle_stripe_webhook(WP_REST_Request $request): WP_REST_Response
```

Process Stripe webhooks.

**Handled Events:**
- `checkout.session.completed` — Payment/setup successful
- `payment_intent.succeeded` — Scheduled charge succeeded

**Webhook Endpoint:** `POST /wp-json/shaped/v1/stripe-webhook`

---

#### `charge_single_booking($booking_id, $idempotency_key)`

```php
public static function charge_single_booking(
    int $booking_id,
    string $idempotency_key
): bool
```

Execute a scheduled charge for delayed-payment booking.

**Parameters:**
- `$booking_id` — Booking post ID
- `$idempotency_key` — Stripe idempotency key

**Returns:** Success boolean

**Flow:**
1. Retrieves saved payment method
2. Creates PaymentIntent with idempotency key
3. Confirms payment
4. Updates booking status
5. Fires `shaped_payment_completed` action

---

#### `detach_payment_method($booking_id)`

```php
public static function detach_payment_method(int $booking_id): void
```

Clean up saved payment method after successful charge.

---

#### `daily_charge_fallback()`

```php
public static function daily_charge_fallback(): void
```

Fallback scheduler that catches missed charges. Runs daily via WP-Cron.

**Checks for:**
- Bookings with `_shaped_payment_status` = 'authorized'
- Check-in date ≤ 7 days away
- No successful charge yet

---

### Payment Flow Diagrams

**Immediate Payment (< 7 days):**
```
Guest selects dates
       ↓
Check-in < 7 days away
       ↓
Create Stripe PaymentIntent session
       ↓
Guest completes payment on Stripe
       ↓
Webhook: checkout.session.completed
       ↓
Mark booking as paid
       ↓
do_action('shaped_payment_completed', $id, 'immediate')
       ↓
Send confirmation email
```

**Delayed Payment (≥ 7 days):**
```
Guest selects dates
       ↓
Check-in ≥ 7 days away
       ↓
Create Stripe SetupIntent session
       ↓
Guest saves card on Stripe
       ↓
Webhook: checkout.session.completed
       ↓
Save payment method to booking
       ↓
Schedule charge 7 days before check-in
       ↓
[7 days before check-in]
       ↓
WP-Cron: shaped_charge_single_booking
       ↓
Execute PaymentIntent
       ↓
Webhook: payment_intent.succeeded
       ↓
do_action('shaped_payment_completed', $id, 'delayed')
       ↓
Send payment confirmation email
```

### Hooks Triggered

| Hook | Type | When |
|------|------|------|
| `shaped_deposit_paid` | Action | Deposit successfully charged |
| `shaped_payment_completed` | Action | Any payment completed |

### Customization Example

```php
// Custom action after payment
add_action('shaped_payment_completed', function($booking_id, $mode) {
    // Sync to external CRM
    $booking = get_post($booking_id);
    crm_api_create_booking([
        'id' => $booking_id,
        'payment_mode' => $mode,
        'customer_email' => get_post_meta($booking_id, '_mphb_email', true)
    ]);
}, 10, 2);
```

---

## Shaped_Booking_Manager

**File:** `core/class-booking-manager.php` (776 lines)
**Purpose:** Booking lifecycle, abandonment tracking, cancellations

### Overview

The booking manager handles:
- Checkout abandonment detection
- Guest booking management page
- Cancellation processing
- Thank-you page display

### Booking Status Flow

```
MPHB Pending → Checkout Started → [5 min timeout] → Abandoned
                     ↓
            Payment Completed → Confirmed
                     ↓
            [Guest Cancels] → Cancelled
```

### Key Methods

#### `schedule_abandonment_check()`

```php
public static function schedule_abandonment_check(): void
```

Schedules the `shaped_check_abandoned_bookings` cron event (runs every minute).

---

#### `process_abandoned_bookings()`

```php
public static function process_abandoned_bookings(): void
```

Processes abandoned checkouts. Bookings older than 5 minutes without payment are marked abandoned.

**Criteria:**
- `_shaped_checkout_started` timestamp exists
- Timestamp > 5 minutes old
- `_shaped_payment_status` != 'completed'

---

#### `handle_checkout_cancellation()`

```php
public static function handle_checkout_cancellation(): void
```

Handles cancellation via URL parameter `?cancel_booking=1&booking_id=X&token=Y`.

**Validation:**
- Token = MD5(booking_id + customer_email)
- Booking not already cancelled
- Cancellation allowed (based on policy)

---

#### `shortcode_manage_booking()`

```php
public static function shortcode_manage_booking(): string
```

Renders the `[shaped_manage_booking]` shortcode output.

**Required URL Parameters:**
- `booking_id` — Booking post ID
- `token` — MD5(booking_id + customer_email)

**Displays:**
- Booking details (dates, room, guests)
- Payment status
- Cancel button (if cancellation allowed)

---

#### `shortcode_thank_you()`

```php
public static function shortcode_thank_you(): string
```

Renders the `[shaped_thank_you]` shortcode output.

**Required URL Parameters:**
- `booking_id` — Booking post ID
- `session_id` — Stripe session ID

**Displays:**
- Booking confirmation
- Payment summary
- Property address
- Check-in instructions

---

### Cancellation Policy

Cancellations are processed based on a 7-day threshold:

| Timing | Policy |
|--------|--------|
| > 7 days before check-in | Full refund |
| ≤ 7 days before check-in | No refund (card charged) |

### Hooks Triggered

| Hook | Type | When |
|------|------|------|
| `shaped_booking_cancelled` | Action | Guest cancels booking |

### Customization Example

```php
// Sync cancellation to external system
add_action('shaped_booking_cancelled', function($booking_id) {
    $booking = get_post($booking_id);

    // Notify channel manager
    if (SHAPED_ENABLE_ROOMCLOUD) {
        Shaped_RC_Sync_Manager::cancel_booking($booking_id);
    }

    // Log for analytics
    analytics_track('booking_cancelled', [
        'booking_id' => $booking_id,
        'cancelled_at' => current_time('mysql')
    ]);
}, 10, 1);
```

---

## Shaped_Assets

**File:** `includes/class-assets.php` (328 lines)
**Purpose:** Conditional CSS/JS loading based on page context

### Overview

The assets class optimizes performance by loading styles and scripts only where needed.

### Asset Groups

| Group | Pages | Assets |
|-------|-------|--------|
| **Always** | All pages | Phosphor icons, cookie banner, calendar fix, language switch |
| **Checkout** | Checkout page | checkout.css, checkout.js, leave-page modal |
| **Search Results** | Search results | search-results.css, search-form.css, checkout.js |
| **Search Form** | Pages with search | search-form.css, search-calendar.css |
| **Modals** | All pages | modals.css, modals.js |

### Key Methods

#### `enqueue_frontend()`

```php
public static function enqueue_frontend(): void
```

Main enqueue logic. Determines page context and loads appropriate assets.

---

#### `is_checkout_page()`

```php
public static function is_checkout_page(): bool
```

Detects if current page is MPHB checkout.

---

#### `is_search_results_page()`

```php
public static function is_search_results_page(): bool
```

Detects if current page is search results.

---

### Enqueued Assets Reference

**CSS Files:**
- `shaped-cookie-banner` — Cookie banner styles
- `shaped-checkout` — Checkout page styles
- `shaped-search-results` — Search results styles
- `shaped-search-form` — Search form styles
- `shaped-search-calendar` — Calendar widget styles
- `shaped-modals` — Modal styles
- `shaped-guest-reviews` — Review display styles
- `shaped-gallery-element` — Gallery styles

**JS Files:**
- `shaped-calendar-fix` — MPHB calendar fixes
- `shaped-language-switch-fade` — Language switcher transitions
- `shaped-checkout` — Checkout functionality
- `shaped-leave-page-modal` — Leave page warning
- `shaped-modals` — Modal functionality
- `shaped-provider-badge-stars` — Star rating display

---

## Shaped_Admin

**File:** `includes/class-admin.php` (216 lines)
**Purpose:** Admin settings page, modal configuration

### Overview

The admin class manages:
- Settings page for modal page assignments
- AJAX handler for loading modal content

### Database Options

| Option | Type | Description |
|--------|------|-------------|
| `shaped_modal_pages` | array | `['modal-key' => page_id]` |

### Key Methods

#### `add_admin_menu()`

```php
public static function add_admin_menu(): void
```

Adds "Shaped Core" menu to WordPress admin.

---

#### `get_modal_types()`

```php
public static function get_modal_types(): array
```

Get available modal types.

**Returns:**
```php
[
    'booking-terms' => 'Booking Terms',
    'privacy' => 'Privacy Policy'
]
```

**Filter:** `shaped/admin/modal_types`

---

#### `get_modal_page($key)`

```php
public static function get_modal_page(string $key): int|null
```

Get page ID for a modal type.

**Parameters:**
- `$key` — Modal key (e.g., 'booking-terms')

**Returns:** Page ID or null

---

#### `ajax_load_modal_content()`

```php
public static function ajax_load_modal_content(): void
```

AJAX handler for loading modal content.

**Request:** `POST /wp-admin/admin-ajax.php?action=shaped_load_modal`
**Parameters:** `modal_key`
**Response:** JSON with `success` and `content`

---

### Customization Example

```php
// Add custom modal type
add_filter('shaped/admin/modal_types', function($types) {
    $types['house-rules'] = 'House Rules';
    $types['cancellation-policy'] = 'Cancellation Policy';
    return $types;
});
```

---

## Shaped_Amenity_Mapper

**File:** `includes/class-amenity-mapper.php`
**Purpose:** Map MPHB amenities to Phosphor icons

### Overview

Maps facility/amenity names from MPHB to display icons using the Phosphor icon set.

### Key Methods

#### `get_icon($facility, $args)`

```php
public static function get_icon(string $facility, array $args = []): string
```

Get icon class for an amenity.

**Parameters:**
- `$facility` — Amenity name/slug
- `$args` — Display arguments (size, class)

**Returns:** Icon HTML or empty string

---

#### `get_all_amenities()`

```php
public static function get_all_amenities(): array
```

Get complete amenity registry.

**Source:** `config/amenities-registry.json`

**Returns:** Array of amenity configurations

---

#### `get_room_amenities($room_type_id, $args)`

```php
public static function get_room_amenities(int $room_type_id, array $args = []): array
```

Get amenities for a specific room type.

**Returns:** Array of amenity data with icons

---

### Hooks Triggered

| Hook | Type | When |
|------|------|------|
| `shaped/amenities/registry` | Filter | Loading amenity registry |

---

## Shaped_Loader

**File:** `includes/class-loader.php` (65 lines)
**Purpose:** PSR-4 style autoloader for Shaped_ classes

### Class Map

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

### Key Methods

#### `register()`

```php
public static function register(): void
```

Register SPL autoloader.

---

#### `add_class($class_name, $relative_path)`

```php
public static function add_class(string $class_name, string $relative_path): void
```

Manually register a class for autoloading.

---

## Email Handlers

**Files:** `core/email-handler.php`, `core/email-handler-admin.php`
**Purpose:** Email notifications for guests and admins

### Guest Emails (`email-handler.php`)

| Function | When Sent |
|----------|-----------|
| `shaped_send_confirmation_email($booking_id)` | Payment completed (full or deposit) |
| `shaped_send_reservation_email($booking_id)` | Card saved (delayed mode) |
| `shaped_send_cancellation_email($booking_id, $refund, $fee)` | Booking cancelled |
| `shaped_send_payment_failed_email($booking_id)` | Charge failed |

### Admin Emails (`email-handler-admin.php`)

| Function | When Sent |
|----------|-----------|
| `shaped_send_admin_confirmation_email($booking_id)` | New booking received |
| `shaped_send_admin_reservation_email($booking_id)` | Reservation created |
| `shaped_send_admin_cancellation_email($booking_id, $refund, $fee)` | Booking cancelled |

### Email Template Structure

All emails use HTML templates with:
- Property branding
- Booking details (dates, room, guests)
- Payment information
- Contact information
- Unsubscribe/manage booking link

### Customization

Override email content via filters:

```php
add_filter('shaped/admin_email', fn() => 'bookings@property.com');
add_filter('shaped/property_name', fn() => 'Luxury Apartments');
add_filter('shaped/property_email', fn() => 'info@property.com');
```

---

## Next Steps

- **[HOOKS_REFERENCE.md](HOOKS_REFERENCE.md)** — Complete hook reference
- **[CUSTOMIZATION_GUIDE.md](CUSTOMIZATION_GUIDE.md)** — How to extend shaped-core
- **[DEBUGGING.md](DEBUGGING.md)** — Troubleshooting guide
