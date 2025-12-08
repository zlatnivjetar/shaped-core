# RoomCloud Integration Guide

> **Last generated:** 2025-12-08
> **Related entry:** [CLAUDE.md#IMPL-001](CLAUDE.md#2025-12-08-impl-001--initial-plugin-architecture)

Complete documentation for the RoomCloud channel manager integration module.

---

## Table of Contents

1. [Overview](#overview)
2. [Enabling the Module](#enabling-the-module)
3. [Configuration](#configuration)
4. [Module Structure](#module-structure)
5. [Core Classes](#core-classes)
6. [Sync Flow](#sync-flow)
7. [Webhook Handling](#webhook-handling)
8. [WP-CLI Commands](#wp-cli-commands)
9. [Error Handling](#error-handling)
10. [Common Issues](#common-issues)

---

## Overview

The RoomCloud module provides **bidirectional sync** between shaped-core and RoomCloud Channel Manager:

- **Bookings → RoomCloud:** New bookings, cancellations synced to channel manager
- **RoomCloud → WordPress:** External bookings received via webhook
- **Availability → RoomCloud:** Room availability updates pushed to channels

### Key Features

- XML API integration with authentication
- Webhook receiver for external bookings
- Retry queue for failed sync operations
- WP-CLI commands for manual sync/debug
- Comprehensive error logging

### Requirements

- RoomCloud account with API credentials
- Hotel ID and Channel ID from RoomCloud
- HTTPS webhook endpoint accessible to RoomCloud

---

## Enabling the Module

### Step 1: Add Constants to wp-config.php

```php
// Enable RoomCloud module
define('SHAPED_ENABLE_ROOMCLOUD', true);

// RoomCloud API credentials
define('ROOMCLOUD_SERVICE_URL', 'https://api.roomcloud.net/...');
define('ROOMCLOUD_USERNAME', 'your-username');
define('ROOMCLOUD_PASSWORD', 'your-password');
define('ROOMCLOUD_HOTEL_ID', '9335');
define('ROOMCLOUD_CHANNEL_ID', 'your-channel-id');
```

### Step 2: Activate Plugin

The module will bootstrap automatically on next page load.

### Step 3: Configure Webhook in RoomCloud

Point RoomCloud webhook to:
```
https://your-site.com/wp-json/shaped-rc/v1/webhook
```

---

## Configuration

### WordPress Options

| Option | Type | Description |
|--------|------|-------------|
| `shaped_rc_service_url` | string | RoomCloud API endpoint |
| `shaped_rc_username` | string | API username |
| `shaped_rc_password` | string | API password |
| `shaped_rc_hotel_id` | string | Hotel ID (default: 9335) |
| `shaped_rc_channel_id` | string | Channel ID |

### Admin Settings

Navigate to **Shaped Core → RoomCloud** in WordPress admin to configure:

- API credentials
- Sync frequency
- Room type mappings
- Debug mode

---

## Module Structure

```
modules/roomcloud/
├── module.php                    # Module bootstrap
├── includes/
│   ├── class-api.php             # RoomCloud XML API wrapper
│   ├── class-sync-manager.php    # Sync orchestration
│   ├── class-webhook-handler.php # Webhook processor
│   ├── class-availability-manager.php  # Availability sync
│   ├── class-error-logger.php    # Error handling & retry queue
│   └── class-admin-settings.php  # Settings page
├── cli/
│   └── class-cli.php             # WP-CLI commands
└── templates/
    └── admin-settings.php        # Settings template
```

---

## Core Classes

### Shaped_RC_API

**File:** `modules/roomcloud/includes/class-api.php`
**Purpose:** RoomCloud XML API wrapper

#### Key Methods

##### `authenticate()`

```php
public function authenticate(): bool
```

Authenticate with RoomCloud API using stored credentials.

**Returns:** Success boolean

---

##### `send_booking($booking_data)`

```php
public function send_booking(array $booking_data): array
```

Send booking to RoomCloud.

**Parameters:**
- `$booking_data` — Array with booking details

**Returns:**
```php
[
    'success' => true,
    'roomcloud_id' => 'RC123456',
    'response' => [...raw API response...]
]
```

---

##### `cancel_booking($roomcloud_id)`

```php
public function cancel_booking(string $roomcloud_id): bool
```

Cancel booking in RoomCloud.

**Parameters:**
- `$roomcloud_id` — RoomCloud booking reference

**Returns:** Success boolean

---

##### `update_availability($room_type_id, $dates)`

```php
public function update_availability(
    int $room_type_id,
    array $dates
): bool
```

Update room availability in RoomCloud.

**Parameters:**
- `$room_type_id` — MPHB room type ID
- `$dates` — Array of dates with availability counts

**Returns:** Success boolean

---

### Shaped_RC_Sync_Manager

**File:** `modules/roomcloud/includes/class-sync-manager.php`
**Purpose:** Orchestrates sync operations

#### Key Methods

##### `init()`

```php
public static function init(): void
```

Initialize sync manager, register hooks.

**Hooks Registered:**
- `shaped_payment_completed` — Sync new bookings
- `shaped_booking_cancelled` — Sync cancellations
- `mphb_booking_status_changed` — Sync status changes

---

##### `sync_booking($booking_id)`

```php
public static function sync_booking(int $booking_id): bool
```

Sync booking to RoomCloud.

**Flow:**
1. Get booking data from MPHB
2. Transform to RoomCloud format
3. Send via API
4. Store RoomCloud ID in meta
5. Log result

---

##### `sync_cancellation($booking_id)`

```php
public static function sync_cancellation(int $booking_id): bool
```

Sync cancellation to RoomCloud.

**Flow:**
1. Get RoomCloud booking ID from meta
2. Send cancellation via API
3. Update local status
4. Log result

---

##### `process_retry_queue()`

```php
public static function process_retry_queue(): void
```

Process failed sync operations from retry queue.

**Schedule:** Every 15 minutes via WP-Cron

---

### Shaped_RC_Webhook_Handler

**File:** `modules/roomcloud/includes/class-webhook-handler.php`
**Purpose:** Process incoming RoomCloud webhooks

#### Key Methods

##### `init()`

```php
public static function init(): void
```

Register REST API endpoint for webhook.

**Endpoint:** `POST /wp-json/shaped-rc/v1/webhook`

---

##### `handle_webhook(WP_REST_Request $request)`

```php
public static function handle_webhook(WP_REST_Request $request): WP_REST_Response
```

Process incoming webhook payload.

**Webhook Types:**
- `NEW_BOOKING` — Create booking in MPHB
- `CANCELLATION` — Cancel existing booking
- `MODIFICATION` — Update booking dates/guests

**Validation:**
- Signature verification
- Payload structure validation
- Duplicate detection

---

##### `create_booking_from_webhook($data)`

```php
private static function create_booking_from_webhook(array $data): int|WP_Error
```

Create MPHB booking from webhook data.

**Returns:** Booking ID or WP_Error

---

### Shaped_RC_Availability_Manager

**File:** `modules/roomcloud/includes/class-availability-manager.php`
**Purpose:** Manage room availability sync

#### Key Methods

##### `sync_availability($room_type_id)`

```php
public static function sync_availability(int $room_type_id): bool
```

Push availability for a room type to RoomCloud.

---

##### `sync_all_availability()`

```php
public static function sync_all_availability(): void
```

Push availability for all room types.

**Schedule:** Daily via WP-Cron

---

##### `handle_availability_change($booking_id)`

```php
public static function handle_availability_change(int $booking_id): void
```

React to availability changes from bookings.

---

### Shaped_RC_Error_Logger

**File:** `modules/roomcloud/includes/class-error-logger.php`
**Purpose:** Error logging and retry queue

#### Database Table

**Table:** `wp_roomcloud_sync_queue`

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Auto-increment ID |
| `booking_id` | bigint | Related booking ID |
| `operation` | varchar | 'sync', 'cancel', 'availability' |
| `payload` | text | JSON payload |
| `attempts` | int | Number of retry attempts |
| `last_error` | text | Last error message |
| `created_at` | datetime | Queue entry time |
| `next_retry` | datetime | Next retry time |

#### Key Methods

##### `log_error($context, $message, $data)`

```php
public static function log_error(
    string $context,
    string $message,
    array $data = []
): void
```

Log error with context.

**Parameters:**
- `$context` — Error context (e.g., 'API', 'Webhook')
- `$message` — Error message
- `$data` — Additional data to log

---

##### `add_to_retry_queue($operation, $booking_id, $payload)`

```php
public static function add_to_retry_queue(
    string $operation,
    int $booking_id,
    array $payload
): void
```

Add failed operation to retry queue.

---

##### `get_recent_errors($limit)`

```php
public static function get_recent_errors(int $limit = 50): array
```

Get recent error log entries.

---

---

## Sync Flow

### Outbound Sync (WordPress → RoomCloud)

```
[New Booking Created]
         ↓
shaped_payment_completed action fires
         ↓
Shaped_RC_Sync_Manager::sync_booking()
         ↓
Transform booking to RoomCloud format
         ↓
Shaped_RC_API::send_booking()
         ↓
     ┌───┴───┐
 Success    Failure
     ↓         ↓
Store RC ID  Add to retry queue
in post meta    ↓
     ↓      [15 min later]
  Complete     ↓
          Retry (max 5 attempts)
```

### Inbound Sync (RoomCloud → WordPress)

```
[RoomCloud sends webhook]
         ↓
POST /wp-json/shaped-rc/v1/webhook
         ↓
Shaped_RC_Webhook_Handler::handle_webhook()
         ↓
Validate signature & payload
         ↓
Check for duplicate (by RC ID)
         ↓
     ┌───┴───────┐
  NEW_BOOKING  CANCELLATION
     ↓             ↓
Create MPHB   Cancel MPHB
  booking       booking
     ↓             ↓
Send guest    Send email
confirmation
     ↓             ↓
Return 200    Return 200
```

---

## Webhook Handling

### Webhook Security

1. **Signature Verification:** All webhooks must include valid signature
2. **IP Whitelist:** Optional IP restriction for webhook endpoint
3. **Idempotency:** Duplicate webhooks are detected and ignored

### Webhook Payload Structure

**New Booking:**
```json
{
  "type": "NEW_BOOKING",
  "booking_id": "RC123456",
  "hotel_id": "9335",
  "room_type": "studio-apartment",
  "check_in": "2025-01-15",
  "check_out": "2025-01-18",
  "guests": {
    "adults": 2,
    "children": 0
  },
  "customer": {
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "+1234567890"
  },
  "total": 450.00,
  "currency": "EUR",
  "signature": "abc123..."
}
```

**Cancellation:**
```json
{
  "type": "CANCELLATION",
  "booking_id": "RC123456",
  "reason": "Guest request",
  "signature": "abc123..."
}
```

---

## WP-CLI Commands

The RoomCloud module provides CLI commands for debugging and manual operations.

### List Commands

```bash
wp shaped-rc --help
```

### Sync Single Booking

```bash
wp shaped-rc sync-booking <booking_id>
```

Manually sync a booking to RoomCloud.

### Sync All Availability

```bash
wp shaped-rc sync-availability
```

Push availability for all room types.

### Process Retry Queue

```bash
wp shaped-rc process-queue
```

Process pending retry queue items.

### View Recent Errors

```bash
wp shaped-rc errors [--limit=50]
```

Display recent error log entries.

### Test API Connection

```bash
wp shaped-rc test-connection
```

Test RoomCloud API connectivity.

### Clear Retry Queue

```bash
wp shaped-rc clear-queue [--booking_id=<id>]
```

Clear retry queue (optionally for specific booking).

---

## Error Handling

### Error Log Format

```
[RoomCloud <CONTEXT>] <timestamp> - <message>
Data: <json_data>
```

### Retry Strategy

| Attempt | Delay |
|---------|-------|
| 1 | Immediate |
| 2 | 15 minutes |
| 3 | 1 hour |
| 4 | 4 hours |
| 5 | 24 hours |
| 6+ | Abandoned (manual intervention) |

### Common Error Codes

| Code | Meaning | Action |
|------|---------|--------|
| `AUTH_FAILED` | Invalid credentials | Check wp-config constants |
| `HOTEL_NOT_FOUND` | Invalid hotel ID | Verify ROOMCLOUD_HOTEL_ID |
| `ROOM_NOT_MAPPED` | Room type not in RoomCloud | Map room in RoomCloud admin |
| `DUPLICATE_BOOKING` | Booking already exists | Check for duplicate sync |
| `RATE_LIMITED` | Too many API requests | Wait and retry |

---

## Common Issues

### Issue: Bookings Not Syncing

**Symptoms:** New bookings don't appear in RoomCloud

**Debug Steps:**
```bash
# Check if module is enabled
wp option get shaped_enable_roomcloud

# Check API credentials
wp shaped-rc test-connection

# Check retry queue
wp shaped-rc errors --limit=10

# Manually sync a booking
wp shaped-rc sync-booking <booking_id>
```

**Common Causes:**
1. Module not enabled in wp-config.php
2. Invalid API credentials
3. Room type not mapped in RoomCloud
4. Network/firewall blocking outbound requests

---

### Issue: Webhooks Not Processing

**Symptoms:** External bookings from RoomCloud not appearing in WordPress

**Debug Steps:**
```bash
# Check webhook endpoint accessibility
curl -X POST https://your-site.com/wp-json/shaped-rc/v1/webhook \
  -H "Content-Type: application/json" \
  -d '{"type":"TEST"}'

# Check error logs
tail -f /path/to/debug.log | grep RoomCloud
```

**Common Causes:**
1. REST API disabled or blocked
2. Webhook URL incorrect in RoomCloud
3. SSL certificate issues
4. Plugin conflict blocking REST API

---

### Issue: Availability Mismatch

**Symptoms:** RoomCloud shows different availability than WordPress

**Debug Steps:**
```bash
# Force availability sync
wp shaped-rc sync-availability

# Check specific room type
wp shaped-rc sync-availability --room_type=<room_type_id>
```

**Common Causes:**
1. Sync not running (WP-Cron issue)
2. Room type mapping incorrect
3. Pending bookings not synced

---

### Issue: Duplicate Bookings

**Symptoms:** Same booking appears multiple times

**Debug Steps:**
```bash
# Check for duplicate RoomCloud IDs
wp post list --post_type=mphb_booking \
  --meta_key=_roomcloud_booking_id \
  --fields=ID,post_status
```

**Common Causes:**
1. Webhook retry without idempotency
2. Manual sync after webhook processed
3. Module re-enabled after being disabled

---

## Debugging Commands Summary

```bash
# Test API connection
wp shaped-rc test-connection

# View recent errors
wp shaped-rc errors --limit=20

# Sync specific booking
wp shaped-rc sync-booking 123

# Process retry queue
wp shaped-rc process-queue

# Full availability sync
wp shaped-rc sync-availability

# Clear stuck queue items
wp shaped-rc clear-queue
```

---

## Next Steps

- **[CORE_MODULES.md](CORE_MODULES.md)** — Core module reference
- **[DEBUGGING.md](DEBUGGING.md)** — General troubleshooting guide
- **[CUSTOMIZATION_GUIDE.md](CUSTOMIZATION_GUIDE.md)** — Extending shaped-core
