# Pricing API Testing Guide

**Version:** 2.2.0
**Last Updated:** 2025-12-11

---

## Manual Testing Checklist (Phase 7 - Step 22)

### Pre-Test Setup

**Prerequisites:**
- [ ] WordPress site accessible
- [ ] MotoPress Hotel Booking active
- [ ] RoomCloud module enabled (`SHAPED_ENABLE_ROOMCLOUD = true`)
- [ ] At least one room type configured in MotoPress
- [ ] RoomCloud inventory synced (check availability manager)
- [ ] Direct booking discounts configured (Shaped Pricing admin)

**Test Tools:**
```bash
# Using curl (recommended)
curl -i "URL"

# Using browser (for HTML endpoint)
# Just paste URL in address bar

# Using Postman/Insomnia (optional)
# Import as GET request
```

---

## Test Suite

### Test 1: Basic JSON Request ✅

**Test:** Valid request with minimum parameters

```bash
curl -i "https://your-site.com/wp-json/shaped/v1/price?checkin=2025-12-20&checkout=2025-12-21&adults=2"
```

**Expected:**
- ✅ HTTP 200 OK
- ✅ Content-Type: application/json
- ✅ Response includes:
  - `property_name`
  - `best_rate.total` (number)
  - `best_rate.discounts_applied` (array)
  - `source: "roomcloud"`
  - `generated_at` (ISO8601 timestamp)

**Verify:**
- [ ] Price matches what UI would show (check room card)
- [ ] Discount percentage correct (check admin settings)
- [ ] Room name is human-readable

---

### Test 2: Basic HTML Request ✅

**Test:** Same query but HTML endpoint

```bash
curl -i "https://your-site.com/wp-json/shaped/v1/price-html?checkin=2025-12-20&checkout=2025-12-21&adults=2"
```

**Expected:**
- ✅ HTTP 200 OK
- ✅ Content-Type: text/html
- ✅ Human-readable sentence with:
  - Guest count
  - Dates
  - Property name
  - Final price with currency symbol
  - Room name
  - Discount mention

**Example output:**
```
For 2 adults from 2025-12-20 to 2025-12-21, the best direct price at Preelook Apartments is €153.00 (€153.00 per night) for Deluxe Studio Apartment, Room Only. Taxes included. 15% direct booking discount. Free cancellation available.
```

---

### Test 3: With All Parameters ✅

**Test:** Request with optional parameters

```bash
curl -i "https://your-site.com/wp-json/shaped/v1/price?checkin=2025-12-20&checkout=2025-12-22&adults=2&children=1&room_type=deluxe-studio-apartment"
```

**Expected:**
- ✅ HTTP 200 OK
- ✅ Specific room type returned (matches room_type param)
- ✅ Guest count reflects children
- ✅ Multi-night calculation correct (total = per_night × nights)

**Verify:**
- [ ] `nights` field = 2
- [ ] `children` field = 1
- [ ] `best_rate.room_type_slug` = "deluxe-studio-apartment"

---

### Test 4: Invalid Date Format ❌

**Test:** Bad date format should fail

```bash
curl -i "https://your-site.com/wp-json/shaped/v1/price?checkin=12-20-2025&checkout=2025-12-21&adults=2"
```

**Expected:**
- ✅ HTTP 400 Bad Request
- ✅ Error message about invalid date format

---

### Test 5: Past Date ❌

**Test:** Check-in in the past should fail

```bash
curl -i "https://your-site.com/wp-json/shaped/v1/price?checkin=2024-01-01&checkout=2024-01-02&adults=2"
```

**Expected:**
- ✅ HTTP 400 Bad Request
- ✅ Error message: "Check-in date cannot be in the past"

---

### Test 6: Checkout Before Checkin ❌

**Test:** Invalid date range

```bash
curl -i "https://your-site.com/wp-json/shaped/v1/price?checkin=2025-12-21&checkout=2025-12-20&adults=2"
```

**Expected:**
- ✅ HTTP 400 Bad Request
- ✅ Error message about checkout before checkin

---

### Test 7: Stay Too Long ❌

**Test:** Stay exceeds 30 nights

```bash
curl -i "https://your-site.com/wp-json/shaped/v1/price?checkin=2025-12-20&checkout=2026-02-01&adults=2"
```

**Expected:**
- ✅ HTTP 400 Bad Request
- ✅ Error message: "Stay cannot exceed 30 nights"

---

### Test 8: Too Many Guests ❌

**Test:** Exceed guest limit

```bash
curl -i "https://your-site.com/wp-json/shaped/v1/price?checkin=2025-12-20&checkout=2025-12-21&adults=8&children=5"
```

**Expected:**
- ✅ HTTP 400 Bad Request
- ✅ Error message about total guests exceeding 10

---

### Test 9: No Availability ❌

**Test:** Query dates with no availability

```bash
# Use dates you know are fully booked in RoomCloud
curl -i "https://your-site.com/wp-json/shaped/v1/price?checkin=YYYY-MM-DD&checkout=YYYY-MM-DD&adults=2"
```

**Expected:**
- ✅ HTTP 503 Service Unavailable
- ✅ Error message: "No rooms available for the selected dates"

---

### Test 10: Invalid Room Type ❌

**Test:** Request non-existent room

```bash
curl -i "https://your-site.com/wp-json/shaped/v1/price?checkin=2025-12-20&checkout=2025-12-21&adults=2&room_type=nonexistent-room"
```

**Expected:**
- ✅ HTTP 503 Service Unavailable
- ✅ Error message about room not available/not found

---

### Test 11: Caching Works ⚡

**Test:** Same request twice in 5 minutes

```bash
# First request (cache MISS)
time curl "https://your-site.com/wp-json/shaped/v1/price?checkin=2025-12-20&checkout=2025-12-21&adults=2"

# Second request immediately (cache HIT)
time curl "https://your-site.com/wp-json/shaped/v1/price?checkin=2025-12-20&checkout=2025-12-21&adults=2"
```

**Expected:**
- ✅ Second request significantly faster (<50ms vs ~500ms)
- ✅ Both responses identical
- ✅ `generated_at` timestamp same for both

**Verify caching:**
```sql
-- Check WordPress transients
SELECT * FROM wp_options WHERE option_name LIKE '%shaped_pricing_%';
```

---

### Test 12: Discount Calculation ✅

**Test:** Verify discount math

```bash
curl "https://your-site.com/wp-json/shaped/v1/price?checkin=2025-12-20&checkout=2025-12-21&adults=2&room_type=deluxe-studio-apartment"
```

**Manual Verification:**
1. Get base price from MotoPress admin (e.g., €180)
2. Get discount from Shaped Pricing admin (e.g., 15%)
3. Calculate: €180 × (1 - 0.15) = €153
4. Compare with `best_rate.total` in response

**Expected:**
- ✅ Math is correct
- ✅ `discounts_applied` mentions "15% direct booking discount"

---

### Test 13: Multiple Room Options ✅

**Test:** Check if other_options returned

```bash
curl "https://your-site.com/wp-json/shaped/v1/price?checkin=2025-12-20&checkout=2025-12-21&adults=2"
```

**Expected (if multiple rooms available):**
- ✅ `best_rate` = cheapest room
- ✅ `other_options` array has 1+ entries
- ✅ `other_options` sorted by price ascending

**Verify:**
- [ ] `best_rate.total` ≤ all `other_options[].total`

---

## Browser Testing

### Test 14: HTML Endpoint in Browser

**Test:** Open HTML endpoint in browser

```
https://your-site.com/wp-json/shaped/v1/price-html?checkin=2025-12-20&checkout=2025-12-21&adults=2
```

**Expected:**
- ✅ Browser displays human-readable sentence
- ✅ No JSON formatting
- ✅ Proper HTML rendering
- ✅ Currency symbols display correctly (€ not &euro;)

---

## Load Testing (Optional)

### Test 15: Rate Limiting

**Test:** Send many requests quickly

```bash
# Send 70 requests in 1 minute (should trigger rate limit)
for i in {1..70}; do
  curl -s "https://your-site.com/wp-json/shaped/v1/price?checkin=2025-12-20&checkout=2025-12-21&adults=2" &
done
wait
```

**Expected (if rate limiting configured):**
- ✅ Some requests return HTTP 429 Too Many Requests
- ✅ Or Cloudflare challenge page

**If NOT configured:**
- ⚠️ All requests succeed
- ⚠️ Action needed: Configure rate limiting (see SECURITY.md)

---

## Error Logging Verification

### Test 16: Check Error Logs

**Test:** Intentionally trigger error and verify logging

```bash
# Trigger error (past date)
curl "https://your-site.com/wp-json/shaped/v1/price?checkin=2024-01-01&checkout=2024-01-02&adults=2"

# Check log
tail -20 /wp-content/debug.log
```

**Expected:**
- ✅ Error logged with context:
  - Timestamp
  - Error message
  - Request parameters (checkin, checkout)
  - No sensitive data

---

## Production Smoke Test

### Test 17: Full End-to-End

**Test:** Complete workflow simulation

```bash
# 1. JSON request
curl "https://your-site.com/wp-json/shaped/v1/price?checkin=2025-12-20&checkout=2025-12-21&adults=2" | jq .

# 2. HTML request
curl "https://your-site.com/wp-json/shaped/v1/price-html?checkin=2025-12-20&checkout=2025-12-21&adults=2"

# 3. Verify response time
time curl -s "https://your-site.com/wp-json/shaped/v1/price?checkin=2025-12-20&checkout=2025-12-21&adults=2" > /dev/null

# 4. Check HTTPS
curl -I "https://your-site.com/wp-json/shaped/v1/price?checkin=2025-12-20&checkout=2025-12-21&adults=2"
```

**Expected:**
- ✅ All requests succeed
- ✅ Response time <2 seconds (uncached)
- ✅ HTTPS (not HTTP)
- ✅ Valid JSON/HTML

---

## Test Results Template

```
PRICING API TEST RESULTS
========================
Date: YYYY-MM-DD
Tester: [Name]
Environment: [Production/Staging]
Site URL: https://...

CORE FUNCTIONALITY
[ ] Test 1: Basic JSON Request
[ ] Test 2: Basic HTML Request
[ ] Test 3: With All Parameters
[ ] Test 12: Discount Calculation
[ ] Test 13: Multiple Room Options

VALIDATION
[ ] Test 4: Invalid Date Format
[ ] Test 5: Past Date
[ ] Test 6: Checkout Before Checkin
[ ] Test 7: Stay Too Long
[ ] Test 8: Too Many Guests

ERROR HANDLING
[ ] Test 9: No Availability
[ ] Test 10: Invalid Room Type
[ ] Test 16: Error Logging

PERFORMANCE
[ ] Test 11: Caching Works
[ ] Test 17: Response Time <2s

SECURITY
[ ] Test 15: Rate Limiting (if configured)
[ ] HTTPS enforced
[ ] No sensitive data in responses

NOTES:
[Any issues or observations]

OVERALL STATUS: PASS / FAIL
```

---

## Troubleshooting Common Issues

### "Service is not available"
```bash
# Check if service initialized
wp eval 'var_dump(shaped_pricing_service());'

# Check RoomCloud enabled
wp eval 'var_dump(defined("SHAPED_ENABLE_ROOMCLOUD") && SHAPED_ENABLE_ROOMCLOUD);'

# Check MotoPress active
wp plugin list | grep mphb
```

### "No rooms available" (but rooms should be)
```bash
# Check RoomCloud inventory
wp eval 'var_dump(Shaped_RC_Availability_Manager::get_inventory());'

# Check room mapping
wp option get shaped_rc_room_mapping
```

### Caching not working
```bash
# Clear cache
wp eval 'shaped_pricing_service()->clear_cache();'

# Check transients
wp transient list | grep shaped_pricing
```

---

**Testing Completed:** [ ] Yes [ ] No
**Production Ready:** [ ] Yes [ ] No
**Sign-off:** _________________ Date: _______
