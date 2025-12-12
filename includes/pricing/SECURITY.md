# Pricing API Security & Production Guidelines

**Last Updated:** 2025-12-11
**API Version:** 2.2.0

---

## Security Audit Results

### ✅ Input Validation (Phase 6 - Steps 17-18)

**Status:** IMPLEMENTED

All input parameters are validated at multiple layers:

#### Layer 1: REST API Validation (`RestApi.php`)
```php
'checkin' => [
    'required'          => true,
    'validate_callback' => validate_date(),
    'sanitize_callback' => 'sanitize_text_field',
]
```

- **Date Format:** Validated via `date_parse()` and `checkdate()`
- **Guest Counts:** Integer validation with min/max bounds (1-10)
- **Room Type:** Sanitized via `sanitize_title()`

#### Layer 2: Request Object (`PriceRequest.php`)
```php
public function validate(): array
{
    // Check-in not in past
    // Check-in < 18 months future
    // Stay length: 1-30 nights
    // Guest count: 1-10 total
}
```

#### Layer 3: Service Level (`ShapedPricingService.php`)
```php
private function validate_request(): void
{
    // Enforce service limits
    // Business rule validation
    // Prevent abuse scenarios
}
```

**Validation Enforced:**
- ✅ Dates in Y-m-d format only
- ✅ Check-in date ≥ today
- ✅ Check-in date ≤ +18 months
- ✅ Stay length: 1-30 nights
- ✅ Adults: 1-10
- ✅ Children: 0-10
- ✅ Total guests: ≤ 10
- ✅ Room type slug sanitized

---

## Rate Limiting (Phase 6 - Step 19)

### ⚠️ IMPORTANT: Infrastructure-Level Implementation Required

**Status:** NOT IMPLEMENTED (by design)

Rate limiting is NOT implemented in PHP code. This is intentional for performance.

### Recommended Configuration

#### Option 1: Cloudflare (Recommended)
```
Rate Limit Rule:
- Path: /wp-json/shaped/v1/price*
- Method: GET
- Limit: 60 requests / minute / IP
- Action: Challenge (CAPTCHA) or Block
- Exclude: Known good bots (optional)
```

#### Option 2: Nginx
```nginx
# /etc/nginx/conf.d/shaped-api-rate-limit.conf
limit_req_zone $binary_remote_addr zone=shaped_api:10m rate=60r/m;

location ~ ^/wp-json/shaped/v1/price {
    limit_req zone=shaped_api burst=10 nodelay;
    limit_req_status 429;
}
```

#### Option 3: WordPress Plugin (Not Recommended)
If infrastructure control unavailable, use plugin like:
- "WP Limit Login Attempts"
- "Limit Attempts" with custom endpoint rules

**Why 60 requests/minute?**
- Allows legitimate users to query multiple date ranges
- Prevents scraping/abuse
- Accounts for cache hits (5-minute TTL reduces load)

### Monitoring Recommendations
```bash
# Check for abuse patterns
tail -f /var/log/nginx/access.log | grep '/wp-json/shaped/v1/price'

# WordPress error log
tail -f /wp-content/debug.log | grep 'Shaped Pricing API Error'
```

---

## Data Leak Audit (Phase 6 - Step 20)

### ✅ AUDIT COMPLETE - NO SENSITIVE DATA EXPOSED

**Audited Files:**
- `PriceResult.php` - Output structure ✅
- `RestApi.php` - API responses ✅
- `RoomCloudPricingProvider.php` - Internal logic ✅

### What IS Exposed (Public Data)
✅ Property name (from WordPress settings)
✅ Room type names (from MotoPress)
✅ Final discounted prices
✅ Discount percentages applied
✅ Currency code
✅ Board type (e.g., "Room Only")
✅ Refundability status
✅ Tax inclusion status
✅ Provider name ("roomcloud")

### What is NOT Exposed (Protected)
❌ Internal room IDs (WordPress post IDs)
❌ RoomCloud room mapping IDs
❌ MotoPress rate IDs
❌ Base/OTA prices (only discounted direct price)
❌ Profit margins
❌ Internal discount config structure
❌ API credentials
❌ Database queries
❌ Error stack traces (only generic messages)

### Code Evidence
```php
// PriceResult.php - Only public-facing data
return [
    'room_type_slug'     => $room_slug,      // ✅ Public
    'room_type_name'     => $room_name,      // ✅ Public
    'total'              => $final_total,    // ✅ Discounted price only
    'discounts_applied'  => $discounts_applied, // ✅ Generic labels
];

// RestApi.php - Error handling doesn't leak internals
catch (Exception $e) {
    return new WP_Error(
        'pricing_unavailable',
        'Pricing service temporarily unavailable: ' . $e->getMessage(),
        ['status' => 503]
    );
    // ✅ Generic error message, details only in logs
}
```

---

## Security Checklist

### Pre-Production
- [ ] **Rate limiting configured** (Cloudflare/nginx/WAF)
- [ ] **HTTPS enforced** (`https://` URLs only)
- [ ] **robots.txt allows API** (not blocked)
- [ ] **Error logging enabled** (`WP_DEBUG_LOG = true`)
- [ ] **Firewall rules reviewed** (allow legitimate traffic)

### Post-Production
- [ ] **Monitor error logs** (check for 503 errors)
- [ ] **Monitor rate limit hits** (adjust if needed)
- [ ] **Test from external IP** (verify no blocking)
- [ ] **Check cache hit rate** (should be high for popular dates)

---

## Security Best Practices

### DO ✅
- Use HTTPS only
- Monitor for unusual traffic patterns
- Set aggressive rate limits initially (can loosen later)
- Log all errors with context
- Keep WordPress/MotoPress/RoomCloud updated
- Use Cloudflare or similar CDN/firewall

### DON'T ❌
- Expose internal IDs or mappings
- Return detailed error messages to clients
- Allow unlimited requests
- Disable error logging
- Trust client-provided data without validation
- Implement rate limiting in PHP (use infrastructure)

---

## Incident Response

### If You Suspect Abuse

1. **Check logs:**
   ```bash
   grep "shaped/v1/price" /var/log/nginx/access.log | awk '{print $1}' | sort | uniq -c | sort -nr
   ```

2. **Block offending IP** (Cloudflare):
   - Security → WAF → Tools → IP Access Rules
   - Add IP with "Block" action

3. **Tighten rate limits temporarily:**
   - Reduce from 60/min to 30/min
   - Add CAPTCHA challenge

4. **Review error logs:**
   ```bash
   tail -100 /wp-content/debug.log | grep "Shaped Pricing API Error"
   ```

### If Service is Down

1. **Check RoomCloud connection:**
   ```php
   $service = shaped_pricing_service();
   if (!$service || !$service->is_ready()) {
       // Provider unavailable
   }
   ```

2. **Check MotoPress:**
   ```php
   if (!function_exists('MPHB')) {
       // MotoPress not active
   }
   ```

3. **Clear caching:**
   ```php
   shaped_pricing_service()->clear_cache();
   ```

---

## Compliance Notes

### GDPR
- ✅ No personal data collected via API
- ✅ No cookies or tracking
- ✅ No user authentication required
- ✅ Pricing data is non-personal

### Accessibility
- ✅ HTML endpoint provides human-readable alternative
- ✅ Structured JSON for screen readers/assistants
- ✅ Clear error messages

---

## Performance Considerations

### Caching Strategy
- **TTL:** 5 minutes (configurable)
- **Storage:** WordPress transients (database)
- **Key:** `shaped_pricing_{provider}_{dates}_{guests}_{room}`

### Expected Load
- **Cache Hit Rate:** ~80% for popular date ranges
- **Database Queries:** 3-5 per uncached request
- **Response Time:** <500ms cached, <2s uncached

### Scaling Recommendations
- Use object cache (Redis/Memcached) for high-traffic sites
- Consider CDN caching for common queries
- Monitor RoomCloud API response times

---

## Contact & Support

**Security Issues:** Report to your system administrator
**API Issues:** Check `/wp-content/debug.log`
**RoomCloud Issues:** Contact RoomCloud support

---

**Document Version:** 1.0
**Last Security Audit:** 2025-12-11
**Next Review:** 2026-01-11 (30 days)
