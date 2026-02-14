# Debugging Guide

> **Last generated:** 2025-12-08
> **Related entry:** [CLAUDE.md#IMPL-001](CLAUDE.md#2025-12-08-impl-001--initial-plugin-architecture)

Troubleshooting guide for common shaped-core issues.

---

## Table of Contents

1. [Logging Strategy](#logging-strategy)
2. [Payment Issues](#payment-issues)
3. [Booking Issues](#booking-issues)
4. [Email Issues](#email-issues)
5. [RoomCloud Issues](#roomcloud-issues)
6. [Performance Issues](#performance-issues)
7. [Debug Commands](#debug-commands)

---

## Logging Strategy

### Log Locations

| Log | Location | Content |
|-----|----------|---------|
| WordPress Debug | `/wp-content/debug.log` | PHP errors, shaped-core logs |
| Stripe Dashboard | stripe.com/dashboard/logs | Webhook events, API calls |
| RoomCloud | WP Admin → Shaped → RoomCloud | Sync errors, webhook logs |

### Enable Debug Logging

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);  // Don't show errors on frontend
```

### Log Prefixes

shaped-core uses consistent prefixes for easy filtering:

| Prefix | Context |
|--------|---------|
| `[Shaped]` | General plugin logs |
| `[Shaped Charge]` | Payment charging |
| `[Shaped Webhook]` | Stripe webhook processing |
| `[Shaped Fallback]` | Scheduled charge fallback |
| `[Shaped Abandonment]` | Checkout abandonment |
| `[RoomCloud]` | RoomCloud module |
| `[Reviews]` | Reviews module |

### Filter Logs

```bash
# View all shaped-core logs
tail -f /path/to/wp-content/debug.log | grep "\[Shaped"

# View only payment logs
tail -f /path/to/wp-content/debug.log | grep "\[Shaped Charge\|Shaped Webhook"

# View RoomCloud logs
tail -f /path/to/wp-content/debug.log | grep "\[RoomCloud"
```

---

## Payment Issues

### Issue: Payment Not Processing

**Symptoms:**
- Checkout completes but booking shows "pending"
- No confirmation email sent
- Stripe shows no payment

**Debug Steps:**

1. **Check Stripe Keys**
   ```bash
   wp option get shaped_stripe_secret
   # Should return sk_live_... or sk_test_...
   ```

2. **Verify Webhook Configuration**
   - Go to Stripe Dashboard → Webhooks
   - Check endpoint: `https://your-site.com/wp-json/shaped/v1/stripe-webhook`
   - Verify signing secret matches `SHAPED_STRIPE_WEBHOOK` constant

3. **Check Webhook Logs**
   ```bash
   grep "Shaped Webhook" /path/to/debug.log | tail -20
   ```

4. **Test Webhook Endpoint**
   ```bash
   curl -X POST https://your-site.com/wp-json/shaped/v1/stripe-webhook \
     -H "Content-Type: application/json" \
     -d '{"type":"test"}'
   # Should return {"received":true} or signature error
   ```

**Common Causes:**
- Wrong webhook signing secret
- Webhook endpoint blocked by security plugin
- SSL certificate issues
- REST API disabled

---

### Issue: Scheduled Charge Failed

**Symptoms:**
- Booking shows "authorized" but never charged
- No charge 7 days before check-in
- Error in logs: "Charge failed"

**Debug Steps:**

1. **Check Scheduled Events**
   ```bash
   wp cron event list | grep shaped
   ```

2. **Check Booking Meta**
   ```bash
   wp post meta get <booking_id> _shaped_payment_status
   wp post meta get <booking_id> _shaped_charge_date
   wp post meta get <booking_id> _stripe_payment_method_id
   ```

3. **Manually Trigger Charge**
   ```bash
   wp eval "do_action('shaped_charge_single_booking', <booking_id>, 'manual-debug');"
   ```

4. **Check Stripe Payment Method**
   - Go to Stripe Dashboard → Customers
   - Find customer by email
   - Verify payment method is still attached

**Common Causes:**
- WP-Cron not running (use real cron)
- Payment method expired or removed
- Stripe customer deleted
- Idempotency key collision

---

### Issue: Duplicate Charges

**Symptoms:**
- Customer charged twice
- Two payment intents in Stripe
- Multiple confirmation emails

**Debug Steps:**

1. **Check Transient**
   ```bash
   wp transient get shaped_sess_<session_id>
   ```

2. **Check Booking Meta**
   ```bash
   wp post meta get <booking_id> _stripe_payment_charged
   ```

3. **Review Webhook Events**
   - Check Stripe Dashboard → Events
   - Look for duplicate `checkout.session.completed` events

**Common Causes:**
- Webhook retry without idempotency check
- Transient cache cleared mid-processing
- Multiple webhook endpoints configured

**Prevention:**
- shaped-core uses transients with 14-day expiry for idempotency
- Never clear transients during payment processing

---

### Issue: Refund Not Processing

**Symptoms:**
- Cancellation processed but no refund in Stripe
- Customer complaining about missing refund

**Debug Steps:**

1. **Check Booking Status**
   ```bash
   wp post meta get <booking_id> _shaped_payment_status
   wp post meta get <booking_id> _stripe_payment_intent_id
   ```

2. **Check Stripe Refund**
   - Go to Stripe Dashboard → Payments
   - Find payment intent
   - Check refund status

3. **Manual Refund**
   - If automated refund failed, process manually in Stripe Dashboard
   - Update booking meta: `_shaped_refund_processed = true`

**Common Causes:**
- Payment was SetupIntent (no charge to refund)
- Refund already processed
- Stripe API error

---

## Booking Issues

### Issue: Booking Stuck in Pending

**Symptoms:**
- Booking created but status never changes
- Guest completed payment but booking shows pending

**Debug Steps:**

1. **Check Payment Status**
   ```bash
   wp post meta get <booking_id> _shaped_payment_status
   wp post meta get <booking_id> _stripe_checkout_session_id
   ```

2. **Check Stripe Session**
   - Get session ID from meta
   - Look up in Stripe Dashboard → Checkout Sessions
   - Verify payment_status is "paid" or "no_payment_required"

3. **Check Webhook Processing**
   ```bash
   grep "<session_id>" /path/to/debug.log
   ```

4. **Manually Update Status**
   ```bash
   wp post meta update <booking_id> _shaped_payment_status completed
   wp post update <booking_id> --post_status=confirmed
   ```

**Common Causes:**
- Webhook not received
- Webhook processing error
- Session ID mismatch

---

### Issue: Bookings Marked Abandoned Incorrectly

**Symptoms:**
- Active checkout sessions being marked abandoned
- Customers losing their bookings

**Debug Steps:**

1. **Check Abandonment Cron**
   ```bash
   wp cron event list | grep abandoned
   ```

2. **Check Timeout Setting**
   - Default: 5 minutes
   - Located in `config/defaults.php`

3. **Check Checkout Started Timestamp**
   ```bash
   wp post meta get <booking_id> _shaped_checkout_started
   ```

**Common Causes:**
- System time mismatch
- Timeout too short for slow internet users
- Multiple browser tabs creating race condition

---

### Issue: Cancellation Not Working

**Symptoms:**
- Guest clicks cancel but nothing happens
- Error message on cancellation page

**Debug Steps:**

1. **Check Token**
   - Token should be MD5(booking_id + customer_email)
   - Verify email in booking matches

2. **Check Cancellation Policy**
   - Cancellation may be blocked if within 7 days of check-in
   - Check `_mphb_check_in_date` meta

3. **Check POST Data**
   ```bash
   grep "cancel_booking" /path/to/debug.log
   ```

**Common Causes:**
- Invalid token
- Booking already cancelled
- Within cancellation blackout period

---

## Email Issues

### Issue: Emails Not Sending

**Symptoms:**
- No confirmation emails received
- No admin notifications

**Debug Steps:**

1. **Check wp_mail Function**
   ```bash
   wp eval "var_dump(wp_mail('test@example.com', 'Test', 'Test body'));"
   ```

2. **Check SMTP Configuration**
   - Verify SMTP plugin is configured
   - Check SMTP credentials

3. **Check Email Logs**
   - If using email logging plugin, check for shaped-core emails

4. **Test Hook Firing**
   ```php
   add_action('shaped_payment_completed', function($id, $mode) {
       error_log("Payment completed hook fired: #$id");
   }, 5, 2);  // Priority 5 = before email functions
   ```

**Common Causes:**
- SMTP not configured
- Emails going to spam
- Email function erroring silently

---

### Issue: Wrong Sender Name/Email

**Symptoms:**
- Emails showing wrong "From" address
- Property name incorrect in emails

**Debug Steps:**

1. **Check Filters**
   ```bash
   wp eval "echo apply_filters('shaped/admin_email', get_option('admin_email'));"
   wp eval "echo apply_filters('shaped/property_name', get_bloginfo('name'));"
   ```

2. **Check Custom Filters**
   - Search for `shaped/admin_email` and `shaped/property_name` in theme/plugins

**Fix:**
```php
add_filter('shaped/admin_email', fn() => 'correct@email.com');
add_filter('shaped/property_name', fn() => 'Correct Name');
```

---

## RoomCloud Issues

### Issue: Bookings Not Syncing to RoomCloud

**Symptoms:**
- New bookings don't appear in RoomCloud
- Sync queue filling up

**Debug Steps:**

1. **Check Module Enabled**
   ```bash
   wp eval "echo defined('SHAPED_ENABLE_ROOMCLOUD') ? 'yes' : 'no';"
   ```

2. **Test API Connection**
   ```bash
   wp shaped-rc test-connection
   ```

3. **Check Credentials**
   ```bash
   wp option get shaped_rc_username
   wp option get shaped_rc_hotel_id
   ```

4. **Check Error Log**
   ```bash
   wp shaped-rc errors --limit=20
   ```

5. **Manually Sync**
   ```bash
   wp shaped-rc sync-booking <booking_id>
   ```

**Common Causes:**
- Invalid credentials
- Room type not mapped in RoomCloud
- Network/firewall issues

See [ROOMCLOUD_INTEGRATION.md](ROOMCLOUD_INTEGRATION.md) for more details.

---

### Issue: Webhooks Not Processing

**Symptoms:**
- External bookings from RoomCloud not appearing
- Webhook endpoint returning errors

**Debug Steps:**

1. **Test Endpoint**
   ```bash
   curl -X POST https://your-site.com/wp-json/shaped-rc/v1/webhook \
     -H "Content-Type: application/json" \
     -d '{"type":"TEST"}'
   ```

2. **Check REST API**
   ```bash
   curl https://your-site.com/wp-json/
   # Should return JSON, not 404 or HTML
   ```

3. **Check Plugin Conflicts**
   - Disable security plugins temporarily
   - Check for REST API blocking

**Common Causes:**
- REST API disabled
- Security plugin blocking POST requests
- SSL certificate issues

---

## Performance Issues

### Issue: Slow Checkout Page

**Symptoms:**
- Checkout page takes long to load
- Pricing calculations slow

**Debug Steps:**

1. **Check Query Count**
   - Install Query Monitor plugin
   - Check for excessive database queries

2. **Check Asset Loading**
   - Verify conditional asset loading is working
   - Should only load checkout assets on checkout page

3. **Profile with Xdebug**
   ```php
   xdebug_start_trace('/tmp/checkout-trace');
   // ... checkout code ...
   xdebug_stop_trace();
   ```

**Optimizations:**
- Enable object caching (Redis/Memcached)
- Use transients for pricing calculations
- Minimize external API calls

---

### Issue: High Server Load from Cron

**Symptoms:**
- Server CPU spikes every minute
- Abandonment checks taking too long

**Debug Steps:**

1. **Check Cron Jobs**
   ```bash
   wp cron event list
   ```

2. **Profile Cron Execution**
   ```bash
   time wp cron event run shaped_check_abandoned_bookings
   ```

3. **Check Pending Bookings Count**
   ```bash
   wp post list --post_type=mphb_booking --post_status=pending --format=count
   ```

**Optimizations:**
- Use real cron instead of WP-Cron
- Index `_shaped_checkout_started` meta key
- Reduce abandonment check frequency

---

## Debug Commands

### Quick Reference

```bash
# View debug log
tail -f /path/to/wp-content/debug.log

# Filter shaped-core logs
grep "\[Shaped" debug.log | tail -50

# Check cron events
wp cron event list | grep shaped

# Check booking meta
wp post meta list <booking_id>

# Test Stripe connection
wp eval "require_once SHAPED_DIR.'vendor/stripe-php/init.php'; \Stripe\Stripe::setApiKey(SHAPED_STRIPE_SECRET); print_r(\Stripe\Account::retrieve());"

# Clear transients
wp transient delete --all

# Check RoomCloud status
wp shaped-rc test-connection
wp shaped-rc errors --limit=10

# Force run cron
wp cron event run shaped_check_abandoned_bookings
wp cron event run shaped_daily_charge_fallback
```

### Useful SQL Queries

```sql
-- Find bookings with payment issues
SELECT ID, post_status,
       (SELECT meta_value FROM wp_postmeta WHERE post_id = p.ID AND meta_key = '_shaped_payment_status') as payment_status
FROM wp_posts p
WHERE post_type = 'mphb_booking'
  AND post_status = 'pending'
ORDER BY ID DESC LIMIT 20;

-- Find scheduled charges
SELECT ID,
       (SELECT meta_value FROM wp_postmeta WHERE post_id = p.ID AND meta_key = '_shaped_charge_date') as charge_date,
       (SELECT meta_value FROM wp_postmeta WHERE post_id = p.ID AND meta_key = '_shaped_payment_status') as status
FROM wp_posts p
WHERE post_type = 'mphb_booking'
  AND ID IN (SELECT post_id FROM wp_postmeta WHERE meta_key = '_shaped_charge_date')
ORDER BY charge_date;

-- Check RoomCloud sync queue
SELECT * FROM wp_roomcloud_sync_queue ORDER BY created_at DESC LIMIT 20;
```

---

## Getting Help

If you're stuck:

1. **Check this guide** — Most issues are covered above
2. **Check logs** — Always look at debug.log first
3. **Check Stripe Dashboard** — For payment issues
4. **Document the issue** — Include logs, steps to reproduce, expected vs actual behavior

---

## Next Steps

- **[CORE_MODULES.md](CORE_MODULES.md)** — Understanding payment processor
- **[ROOMCLOUD_INTEGRATION.md](ROOMCLOUD_INTEGRATION.md)** — RoomCloud troubleshooting
- **[MAINTENANCE.md](MAINTENANCE.md)** — Reporting and fixing issues
