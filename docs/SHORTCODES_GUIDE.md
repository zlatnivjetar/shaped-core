# Shortcodes Guide

> **Last generated:** 2025-12-08
> **Related entry:** [CLAUDE.md#IMPL-001](CLAUDE.md#2025-12-08-impl-001--initial-plugin-architecture)

Complete reference for all shortcodes in shaped-core.

---

## Table of Contents

1. [Overview](#overview)
2. [Room Display Shortcodes](#room-display-shortcodes)
   - [shaped_room_cards](#shaped_room_cards)
   - [shaped_room_details](#shaped_room_details)
   - [shaped_meta](#shaped_meta)
3. [UI Component Shortcodes](#ui-component-shortcodes)
   - [shaped_provider_badge](#shaped_provider_badge)
   - [shaped_modal](#shaped_modal)
4. [Booking Management Shortcodes](#booking-management-shortcodes)
   - [shaped_manage_booking](#shaped_manage_booking)
   - [shaped_booking_cancelled](#shaped_booking_cancelled)
   - [shaped_thank_you](#shaped_thank_you)
5. [Review Shortcodes](#review-shortcodes)
   - [shaped_unified_rating](#shaped_unified_rating)
   - [shaped_review_author](#shaped_review_author)
   - [shaped_review_date](#shaped_review_date)
   - [shaped_review_content](#shaped_review_content)
6. [Debug Shortcodes](#debug-shortcodes)
   - [shaped_meta_keys](#shaped_meta_keys)
7. [Legacy Aliases](#legacy-aliases)

---

## Overview

shaped-core provides shortcodes for:
- Displaying room cards and details
- Showing provider rating badges
- Managing booking flows (thank you, cancellation)
- Displaying aggregated reviews

### Shortcode Naming Convention

All shortcodes use the `shaped_` prefix:
```
[shaped_shortcode_name attribute="value"]
```

---

## Room Display Shortcodes

### shaped_room_cards

**File:** `shortcodes/room-cards.php`
**Purpose:** Display room cards using templates
**Added:** 2025-12-08 (IMPL-001)

Display a grid of room type cards with pricing and booking links.

#### Attributes

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `template` | string | `'home'` | Template: `'home'` or `'listing'` |
| `ids` | string | `''` | Comma-separated room type IDs |
| `limit` | int | `-1` | Number of rooms (-1 = all) |
| `orderby` | string | `'menu_order'` | Order by: `menu_order`, `title`, `date`, `rand` |
| `order` | string | `'ASC'` | Sort direction: `ASC` or `DESC` |
| `class` | string | `''` | Additional CSS classes |

#### Usage

**Basic usage:**
```php
[shaped_room_cards]
```

**Homepage cards:**
```php
[shaped_room_cards template="home" limit="3" orderby="menu_order"]
```

**Listing page cards:**
```php
[shaped_room_cards template="listing" orderby="title" order="ASC"]
```

**Specific rooms only:**
```php
[shaped_room_cards ids="12,45,78" template="listing"]
```

**Random selection:**
```php
[shaped_room_cards limit="3" orderby="rand"]
```

#### Output Structure

```html
<div class="shaped-room-cards-wrapper template-home custom-class">
    <!-- Room card 1 (from template) -->
    <!-- Room card 2 (from template) -->
    <!-- ... -->
</div>
```

#### Templates

Templates are located in `/templates/`:
- `room-card-home.php` — Homepage card with image, title, price
- `room-card-listing.php` — Listing card with more details

#### Template Variables

Both templates receive:
- `$room` — WP_Post object for room type
- `$room_id` — Room type post ID
- `$price` — Calculated price with discounts
- `$amenities` — Room amenities array

---

### shaped_room_details

**File:** `shortcodes/room-details.php`
**Purpose:** Display room description with formatting
**Added:** 2025-12-08 (IMPL-001)

Display the full description of a room type with WordPress formatting applied.

#### Attributes

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `id` | int | `0` | Room type post ID (0 = current post) |

#### Usage

**Current room (on single room page):**
```php
[shaped_room_details]
```

**Specific room:**
```php
[shaped_room_details id="123"]
```

#### Output

Outputs the room description with:
- `wpautop()` — Auto paragraph breaks
- `wptexturize()` — Smart typography (quotes, dashes)
- `convert_smilies()` — Emoji conversion

---

### shaped_meta

**File:** `shortcodes/room-meta.php`
**Purpose:** Display any post meta value
**Added:** 2025-12-08 (IMPL-001)

Generic shortcode to display any post meta value by key.

#### Attributes

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `key` | string | `''` | Meta key to display (required) |
| `post_id` | int | `0` | Post ID (0 = current post) |

#### Usage

**Display room capacity:**
```php
[shaped_meta key="_mphb_max_guests"]
```

**Display specific post meta:**
```php
[shaped_meta key="custom_field" post_id="123"]
```

**Display room size:**
```php
[shaped_meta key="_mphb_size"]
```

#### Output

- **Single value:** HTML-escaped string
- **Array value:** Comma-separated values

#### Legacy Alias

```php
[pre_meta key="field_name"]  // Works the same
```

---

## UI Component Shortcodes

### shaped_provider_badge

**File:** `shortcodes/class-provider-badge.php`
**Purpose:** Display provider rating badge
**Added:** 2025-12-08 (IMPL-001)

Display a styled rating badge for booking providers (Booking.com, Airbnb, etc.).

#### Attributes

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `provider` | string | `'booking'` | Provider: `booking`, `airbnb`, `tripadvisor`, `expedia`, `google` |
| `rating` | string | `'9.0'` | Numeric rating (0-10 scale) |
| `reviews` | string | `''` | Review count text (optional) |
| `class` | string | `''` | Additional CSS classes |

#### Usage

**Booking.com badge:**
```php
[shaped_provider_badge provider="booking" rating="9.2" reviews="128 reviews"]
```

**Airbnb badge:**
```php
[shaped_provider_badge provider="airbnb" rating="4.8"]
```

**TripAdvisor badge:**
```php
[shaped_provider_badge provider="tripadvisor" rating="4.5" reviews="45 reviews"]
```

**Google badge:**
```php
[shaped_provider_badge provider="google" rating="4.7"]
```

#### Output Structure

```html
<div class="shaped-provider-badge provider-booking custom-class">
    <div class="provider-logo">
        <!-- Provider icon -->
    </div>
    <div class="rating">
        <span class="rating-value">9.2</span>
        <div class="stars">★★★★★</div>
    </div>
    <div class="reviews">128 reviews</div>
</div>
```

#### Provider Colors

| Provider | Color |
|----------|-------|
| `booking` | #003580 (blue) |
| `airbnb` | #FF5A5F (red) |
| `tripadvisor` | #00AF87 (green) |
| `expedia` | #FFCB00 (yellow) |
| `google` | #4285F4 (blue) |

#### Adding Custom Providers

```php
add_filter('shaped/provider_badge/providers', function($providers) {
    $providers['vrbo'] = [
        'color' => '#3B5998',
        'label' => 'Vrbo',
        'max_rating' => 5
    ];
    return $providers;
});
```

---

### shaped_modal

**File:** `shortcodes/class-modal-link.php`
**Purpose:** Display modal link for configured pages
**Added:** 2025-12-08 (IMPL-001)

Create a link that opens a WordPress page content in a modal overlay.

#### Attributes

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `page` | string | `''` | Modal key (required) |
| `label` | string | `''` | Link text (required) |
| `class` | string | `''` | Additional CSS classes |

#### Usage

**Booking terms link:**
```php
[shaped_modal page="booking-terms" label="View Booking Terms"]
```

**Privacy policy link:**
```php
[shaped_modal page="privacy" label="Privacy Policy" class="footer-link"]
```

#### Configuration

1. Go to **Shaped Core → Settings** in WordPress admin
2. Assign WordPress pages to modal types
3. Use the shortcode with matching `page` key

#### Default Modal Types

| Key | Label |
|-----|-------|
| `booking-terms` | Booking Terms |
| `privacy` | Privacy Policy |

#### Adding Custom Modal Types

```php
add_filter('shaped/admin/modal_types', function($types) {
    $types['house-rules'] = 'House Rules';
    $types['cancellation-policy'] = 'Cancellation Policy';
    return $types;
});
```

Then use:
```php
[shaped_modal page="house-rules" label="View House Rules"]
```

#### Output Structure

```html
<a href="#" class="shaped-modal-link custom-class"
   data-modal="booking-terms">
    View Booking Terms
</a>
```

---

## Booking Management Shortcodes

### shaped_manage_booking

**File:** `core/class-booking-manager.php`
**Purpose:** Guest booking management interface
**Added:** 2025-12-08 (IMPL-001)

Display the booking management interface for guests.

#### URL Parameters (Required)

| Parameter | Description |
|-----------|-------------|
| `booking_id` | Booking post ID |
| `token` | MD5(booking_id + customer_email) |

#### Usage

Create a page with this shortcode:
```php
[shaped_manage_booking]
```

Guests access via URL:
```
https://your-site.com/manage-booking/?booking_id=123&token=abc123
```

#### Displayed Information

- Booking reference number
- Check-in / check-out dates
- Room type and guests
- Payment status
- Cancel booking button (if allowed)

#### Security

- Token must match MD5 hash of booking ID + customer email
- Prevents unauthorized access to booking details

---

### shaped_booking_cancelled

**File:** `core/class-booking-manager.php`
**Purpose:** Booking cancellation confirmation
**Added:** 2025-12-08 (IMPL-001)

Display cancellation confirmation page.

#### Usage

Create a page with this shortcode:
```php
[shaped_booking_cancelled]
```

#### Displayed Information

- Cancellation confirmation message
- Refund status (if applicable)
- Contact information

---

### shaped_thank_you

**File:** `core/class-booking-manager.php`
**Purpose:** Thank you / confirmation page
**Added:** 2025-12-08 (IMPL-001)

Display booking confirmation after successful payment.

#### URL Parameters (Required)

| Parameter | Description |
|-----------|-------------|
| `booking_id` | Booking post ID |
| `session_id` | Stripe checkout session ID |

#### Usage

Create a page with this shortcode:
```php
[shaped_thank_you]
```

Configured via `SHAPED_SUCCESS_URL` constant:
```php
define('SHAPED_SUCCESS_URL', home_url('/thank-you/?booking_id={BOOKING_ID}'));
```

#### Displayed Information

- Booking confirmation number
- Check-in / check-out dates
- Property address
- Payment summary
- Check-in instructions

---

## Review Shortcodes

These shortcodes are part of the **Reviews module** and require `SHAPED_ENABLE_REVIEWS` to be true.

### shaped_unified_rating

**File:** `modules/reviews/shortcodes.php`
**Purpose:** Display star rating with scale conversion
**Added:** 2025-12-08 (IMPL-001)

Display a unified star rating that converts different provider scales to 5 stars.

#### Usage

Inside a review loop or with review ID:
```php
[shaped_unified_rating]
```

#### Output

Converts provider ratings to 5-star display:
- Booking.com (0-10) → 5 stars
- TripAdvisor (0-5) → 5 stars
- Google (0-5) → 5 stars

---

### shaped_review_author

**File:** `modules/reviews/shortcodes.php`
**Purpose:** Display review author name
**Added:** 2025-12-08 (IMPL-001)

#### Usage

```php
[shaped_review_author]
```

#### Output

Author name from `author_name` post meta.

---

### shaped_review_date

**File:** `modules/reviews/shortcodes.php`
**Purpose:** Display formatted review date
**Added:** 2025-12-08 (IMPL-001)

#### Attributes

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `format` | string | `'d.m.Y'` | PHP date format |

#### Usage

```php
[shaped_review_date]
[shaped_review_date format="F j, Y"]
```

#### Output

Formatted date from `review_date` post meta.

---

### shaped_review_content

**File:** `modules/reviews/shortcodes.php`
**Purpose:** Display truncated review text
**Added:** 2025-12-08 (IMPL-001)

Display review content with "read more" functionality.

#### Usage

```php
[shaped_review_content]
```

#### Output

Review text truncated to configurable length with "read more" link.

---

## Debug Shortcodes

### shaped_meta_keys

**File:** `shortcodes/room-meta.php`
**Purpose:** Debug helper - list all post meta
**Added:** 2025-12-08 (IMPL-001)

**Admin only** — Lists all meta keys and values for debugging.

#### Usage

```php
[shaped_meta_keys]
```

#### Output

Pre-formatted list of all meta keys and their values for the current post.

**Note:** Only visible to administrators.

#### Legacy Alias

```php
[pre_meta_keys]
```

---

## Legacy Aliases

For backward compatibility, these aliases are supported:

| Legacy | Current |
|--------|---------|
| `[pre_meta]` | `[shaped_meta]` |
| `[pre_meta_keys]` | `[shaped_meta_keys]` |
| `[unified_rating]` | `[shaped_unified_rating]` |
| `[review_author]` | `[shaped_review_author]` |
| `[review_date]` | `[shaped_review_date]` |
| `[provider_badge_v2]` | `[shaped_provider_badge]` |
| `[review_content]` | `[shaped_review_content]` |

**Recommendation:** Use `shaped_` prefixed versions in new code.

---

## Shortcode Reference Table

| Shortcode | Purpose | File |
|-----------|---------|------|
| `[shaped_room_cards]` | Display room cards | room-cards.php |
| `[shaped_room_details]` | Room description | room-details.php |
| `[shaped_meta]` | Display post meta | room-meta.php |
| `[shaped_provider_badge]` | Rating badge | class-provider-badge.php |
| `[shaped_modal]` | Modal link | class-modal-link.php |
| `[shaped_manage_booking]` | Guest booking management | class-booking-manager.php |
| `[shaped_booking_cancelled]` | Cancellation page | class-booking-manager.php |
| `[shaped_thank_you]` | Thank you page | class-booking-manager.php |
| `[shaped_unified_rating]` | Star rating | modules/reviews/shortcodes.php |
| `[shaped_review_author]` | Review author | modules/reviews/shortcodes.php |
| `[shaped_review_date]` | Review date | modules/reviews/shortcodes.php |
| `[shaped_review_content]` | Review text | modules/reviews/shortcodes.php |
| `[shaped_meta_keys]` | Debug meta list | room-meta.php |

---

## Next Steps

- **[CUSTOMIZATION_GUIDE.md](CUSTOMIZATION_GUIDE.md)** — Extending shortcodes
- **[CORE_MODULES.md](CORE_MODULES.md)** — Booking manager details
- **[HOOKS_REFERENCE.md](HOOKS_REFERENCE.md)** — Related hooks
