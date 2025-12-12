# Price API Improvements - Stateless, Cacheable, and Optionally Protected

## Summary

The `/wp-json/shaped/v1/price` endpoint has been upgraded to be:

1. **Stateless** - No session cookies (WP_SESSION_COOKIE) for anonymous GET requests
2. **Cacheable** - 60-second TTL caching with proper Cache-Control headers
3. **Strictly Validated** - Defensive validation for all inputs
4. **Optionally Protected** - Shared-secret authentication (toggleable)

## Changes Made

### 1. Session Prevention (`mu-plugins/shaped-no-session-on-price-api.php` + `includes/pricing/class-rest-api.php:42-86`)

**Problem**: WordPress or plugins (like WP Session Manager) start sessions, which creates `WP_SESSION_COOKIE`, hurting caching and potentially triggering bot detection heuristics.

**Solution**: Two-tier approach for maximum effectiveness:

**Primary (MU-Plugin)**: `mu-plugins/shaped-no-session-on-price-api.php`
- Runs on `muplugins_loaded` (before all plugins)
- Disables PHP sessions via `ini_set()`
- Adds filters to disable WP Session Manager
- Removes session initialization hooks

**Fallback (Plugin)**: `includes/pricing/class-rest-api.php:42-86`
- Runs on `init` hook (priority 1)
- Additional session prevention if MU-plugin isn't installed
- Sets `SHAPED_NO_SESSION` constant

**Installation Required**:
```bash
cp mu-plugins/shaped-no-session-on-price-api.php /path/to/wp-content/mu-plugins/
```

Without the MU-plugin, sessions may still be created by WP Session Manager or other plugins that initialize early.

### 2. Transient Caching (`includes/pricing/class-rest-api.php:285-298, 325-342`)

**Implementation**:
- **Cache Key**: MD5 hash of serialized parameters (checkin, checkout, adults, children, room_type, locale)
- **TTL**: 60 seconds (configurable via `CACHE_TTL` constant)
- **Storage**: WordPress transients (fast, native)

**Cache Headers**:
```
Cache-Control: public, max-age=60
Vary: Accept-Encoding
X-Robots-Tag: noindex
X-Shaped-Cache: HIT|MISS
```

**Benefits**:
- Handles repeated LLM/bot queries efficiently
- Reduces database load
- Debug header shows cache status

### 3. Strict Validation (`includes/pricing/class-rest-api.php:178-238`)

**Enhanced Date Validation**:
- Strict regex check for `YYYY-MM-DD` format
- Valid calendar dates only (via `checkdate()`)
- Checkout must be after checkin
- Checkin cannot be in the past (allows today)
- Checkin cannot be more than 2 years in the future

**Response Codes**:
- `400 Bad Request` for invalid dates/ranges
- `401 Unauthorized` if API key required but missing/invalid
- `503 Service Unavailable` if pricing service fails

### 4. Optional Authentication (`includes/pricing/class-rest-api.php:240-275`)

**Configuration** (in `wp-config.php`):

```php
// Enable API key requirement (default: false)
define('SHAPED_PRICE_API_REQUIRE_KEY', true);

// Set the shared secret key
define('SHAPED_PRICE_API_KEY', 'your-random-secret-key-here');
```

**Usage**:

Option A - Header:
```bash
curl -H "X-Shaped-Key: your-secret-key" "https://site.com/wp-json/shaped/v1/price?..."
```

Option B - Query Parameter:
```bash
curl "https://site.com/wp-json/shaped/v1/price?checkin=2026-01-01&checkout=2026-01-02&adults=2&key=your-secret-key"
```

**Security**:
- Uses timing-safe comparison (`hash_equals()`)
- Fails open if misconfigured (logs error)
- Returns `401 Unauthorized` on invalid key

### 5. robots.txt Update

**File**: `robots.txt.example` (copy to WordPress root as `robots.txt`)

**Content**:
```
User-agent: *
Disallow: /wp-admin/
Disallow: /wp-includes/
Allow: /wp-admin/admin-ajax.php
Allow: /wp-json/
```

**Previous Issue**: Site had `Disallow: /` which blocked all crawling including REST API.

**Resolution**: Allow `/wp-json/` while protecting admin areas.

## Testing

### Test 1: No Session Cookie

```bash
curl -i "https://test.preelook.com/wp-json/shaped/v1/price?checkin=2026-01-01&checkout=2026-01-02&adults=2"
```

**Expected**:
- HTTP 200 OK
- **NO** `Set-Cookie: WP_SESSION_COOKIE` header
- `Cache-Control: public, max-age=60`
- `X-Shaped-Cache: MISS` (first call)

### Test 2: Cache Hit

Run the same command twice within 60 seconds:

```bash
curl -i "https://test.preelook.com/wp-json/shaped/v1/price?checkin=2026-01-01&checkout=2026-01-02&adults=2"
```

**Expected Second Call**:
- `X-Shaped-Cache: HIT`
- Faster response time

### Test 3: Invalid Date Range

```bash
curl -i "https://test.preelook.com/wp-json/shaped/v1/price?checkin=2026-01-02&checkout=2026-01-01&adults=2"
```

**Expected**:
- HTTP 400 Bad Request
- Error: "Check-out date must be after check-in date"

### Test 4: Invalid Date Format

```bash
curl -i "https://test.preelook.com/wp-json/shaped/v1/price?checkin=01/01/2026&checkout=01/02/2026&adults=2"
```

**Expected**:
- HTTP 400 Bad Request
- Error: Validation failure

### Test 5: Past Date

```bash
curl -i "https://test.preelook.com/wp-json/shaped/v1/price?checkin=2020-01-01&checkout=2020-01-02&adults=2"
```

**Expected**:
- HTTP 400 Bad Request
- Error: "Check-in date cannot be in the past"

### Test 6: Authentication (if enabled)

**With valid key**:
```bash
curl -i -H "X-Shaped-Key: your-secret-key" "https://test.preelook.com/wp-json/shaped/v1/price?checkin=2026-01-01&checkout=2026-01-02&adults=2"
```

**Expected**: HTTP 200 OK

**Without key** (if `SHAPED_PRICE_API_REQUIRE_KEY` is true):
```bash
curl -i "https://test.preelook.com/wp-json/shaped/v1/price?checkin=2026-01-01&checkout=2026-01-02&adults=2"
```

**Expected**: HTTP 401 Unauthorized

### Test 7: Cache Key Variations

Different parameters should create different cache entries:

```bash
# Different dates
curl "https://test.preelook.com/wp-json/shaped/v1/price?checkin=2026-01-01&checkout=2026-01-02&adults=2"
curl "https://test.preelook.com/wp-json/shaped/v1/price?checkin=2026-02-01&checkout=2026-02-02&adults=2"

# Different adults count
curl "https://test.preelook.com/wp-json/shaped/v1/price?checkin=2026-01-01&checkout=2026-01-02&adults=4"

# Different room type
curl "https://test.preelook.com/wp-json/shaped/v1/price?checkin=2026-01-01&checkout=2026-01-02&adults=2&room_type=deluxe"
```

Each should show `X-Shaped-Cache: MISS` on first call, then `HIT` on repeated calls with same params.

## Configuration Options

All configuration is done in `wp-config.php`:

```php
// ─── Optional: Require API Key ───
define('SHAPED_PRICE_API_REQUIRE_KEY', true);  // Default: false
define('SHAPED_PRICE_API_KEY', 'random-secret-key-123');

// ─── Optional: Custom Cache TTL ───
// Note: This requires editing RestApi.php::CACHE_TTL constant
// Default: 60 seconds
```

## Performance Impact

**Before**:
- Every request computed fresh pricing
- Session cookie created (stateful)
- No caching layer

**After**:
- First request: Same performance, result cached
- Subsequent requests (within 60s): ~95% faster (served from cache)
- No session cookies (stateless)
- Reduced database queries

**Example Metrics** (approximate):
- Uncached request: ~200-500ms
- Cached request: ~10-50ms
- Cache memory: ~1-5 KB per unique query

## Security Considerations

### Public Endpoint (default)

The endpoint is **public by default** because:
1. Pricing information is typically public on hotel websites
2. LLMs and booking tools need easy access
3. Rate limiting should be handled at infrastructure level (WAF, Cloudflare, nginx)

### Protected Endpoint (optional)

Enable `SHAPED_PRICE_API_REQUIRE_KEY` if:
1. You want to limit access to known clients only
2. You're concerned about abuse/scraping
3. You want to gate non-site usage

**Note**: This adds friction for legitimate LLM tools - only enable if necessary.

### Recommended Infrastructure Security

1. **Rate Limiting**: Configure at WAF/CDN level (e.g., 60 requests/minute per IP)
2. **DDoS Protection**: Use Cloudflare or similar
3. **Monitoring**: Track unusual traffic patterns
4. **robots.txt**: Already configured to prevent indexing

## Backward Compatibility

✅ **Fully backward compatible**

- No breaking changes to request/response format
- Existing clients will work without modification
- New features are opt-in (authentication) or transparent (caching)

## Files Modified

1. `includes/pricing/class-rest-api.php` - Main endpoint implementation

## Files Created

1. `mu-plugins/shaped-no-session-on-price-api.php` - Must-use plugin to prevent sessions (CRITICAL - must be installed)
2. `includes/pricing/API_IMPROVEMENTS.md` - This documentation
3. `robots.txt.example` - Recommended robots.txt configuration
4. `wp-config-additions.php` - Configuration examples

## Migration Checklist

- [ ] Deploy updated code
- [ ] **CRITICAL**: Copy `mu-plugins/shaped-no-session-on-price-api.php` to `wp-content/mu-plugins/`
  - Create the directory if it doesn't exist: `mkdir -p wp-content/mu-plugins`
  - Without this, sessions will still be created!
- [ ] Copy `robots.txt.example` to WordPress root as `robots.txt` (if not using custom robots.txt already)
- [ ] Test endpoint without session cookies (curl test #1)
- [ ] Test caching behavior (curl test #2)
- [ ] Test validation (curl tests #3-5)
- [ ] (Optional) Configure API key in `wp-config.php`
- [ ] (Optional) Test authentication (curl test #6)
- [ ] Monitor cache hit rates via `X-Shaped-Cache` header
- [ ] Monitor error logs for any issues

## Troubleshooting

### Session cookies still appearing

**Cause**: The MU-plugin is not installed or another plugin is starting sessions before it runs.

**Solution**:

1. **Install the MU-plugin** (most common fix):
   ```bash
   # From plugin root directory
   mkdir -p /path/to/wp-content/mu-plugins
   cp mu-plugins/shaped-no-session-on-price-api.php /path/to/wp-content/mu-plugins/
   ```

2. **Verify MU-plugin is loaded**:
   - Go to WordPress admin → Plugins → Must-Use
   - You should see "Shaped - Prevent Sessions on Price API"

3. **Check for conflicts**:
   - Look for other session-starting plugins (WP Session Manager, WooCommerce, etc.)
   - Check if they have settings to disable sessions on REST endpoints

4. **Last resort - Disable session plugin**:
   If sessions are still created and not needed elsewhere, temporarily disable WP Session Manager or similar plugins to test.

### Cache not working

**Debug**:
1. Check `X-Shaped-Cache` header (should be MISS then HIT)
2. Verify transients are being stored: `SELECT * FROM wp_options WHERE option_name LIKE '_transient_shaped_price_%'`
3. Check if object caching is interfering

**Solution**: Clear all caches and test again.

### Authentication not working

**Debug**:
1. Verify constants are set in `wp-config.php`
2. Check error logs for configuration warnings
3. Test with both header and query param methods

**Common Issues**:
- Key has extra whitespace (trim in config)
- Wrong constant names (check spelling)
- Caching the 401 response (clear cache)

## Future Enhancements

Potential improvements for future iterations:

1. **Configurable TTL**: Add filter for dynamic cache TTL
2. **Cache Warming**: Pre-populate cache for common date ranges
3. **Rate Limiting**: Built-in rate limiting (currently deferred to infrastructure)
4. **Analytics**: Track cache hit rates, popular queries
5. **Compression**: Consider compressing cached data for memory efficiency
6. **Edge Caching**: Add headers for CDN edge caching (longer TTL)

## Support

For issues or questions:
- Check error logs: `/wp-content/debug.log`
- Enable WP_DEBUG in `wp-config.php` for detailed errors
- Review this documentation's troubleshooting section
