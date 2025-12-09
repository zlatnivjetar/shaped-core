# Brand Configuration Testing Guide

**Comprehensive testing procedures for Phases 1-3**

---

## 📋 Testing Checklist

- [ ] Phase 1: Infrastructure
- [ ] Phase 2: Email Templates
- [ ] Phase 3: PHP Inline Styles
- [ ] Integration Testing
- [ ] Client Override Testing

---

## Phase 1: Infrastructure Testing

### Test 1: Automated Test Suite

**Run the automated test script:**

1. Add to your theme's `functions.php`:
```php
require_once WP_CONTENT_DIR . '/plugins/shaped-core/test-brand-config.php';
```

2. Visit: **WordPress Admin → Plugins** page

3. **Expected Results:**
   - ✅ Shaped_Brand_Config class is loaded
   - ✅ All helper functions exist (`shaped_brand`, `shaped_brand_color`, etc.)
   - ✅ Primary color retrieves `#D1AF5D`
   - ✅ Success color retrieves `#4C9155`
   - ✅ Dot notation works (`colors.brand.primary`)
   - ✅ No client override active (shows "No client override active")
   - ✅ Full color palette displays
   - ✅ JS format exports correctly

4. **Screenshot the test results** for documentation

5. **Clean up:** Remove the require line from functions.php

---

### Test 2: Manual PHP Testing

Create a temporary PHP file in your theme:

```php
<?php
// test-brand-config-manual.php

// Test 1: Basic color retrieval
$primary = shaped_brand_color('primary');
echo "Primary Color: " . $primary . "\n"; // Should output: #D1AF5D

// Test 2: Dot notation
$textMuted = shaped_brand('colors.text.muted');
echo "Text Muted: " . $textMuted . "\n"; // Should output: #666666

// Test 3: All colors
$colors = shaped_brand_colors();
echo "Total color groups: " . count($colors) . "\n"; // Should output: 5+

// Test 4: Client detection
$client = shaped_brand_client();
echo "Active Client: " . ($client ?? 'None (base config)') . "\n";

// Test 5: JS format
$jsColors = shaped_brand_colors_for_js();
echo "JS Colors Count: " . count($jsColors) . "\n"; // Should output: 15+
```

**Expected Output:**
```
Primary Color: #D1AF5D
Text Muted: #666666
Total color groups: 5
Active Client: None (base config)
JS Colors Count: 15
```

---

### Test 3: JavaScript Integration

1. Open browser console on any frontend page
2. Type: `console.log(ShapedBrand)`

**Expected Output:**
```javascript
{
  primary: "#D1AF5D",
  primaryHover: "#C39937",
  secondary: "#94772E",
  success: "#4C9155",
  error: "#b83c2e",
  warning: "#E69500",
  textPrimary: "#141310",
  textMuted: "#666666",
  // ... etc
}
```

3. Test access: `console.log(ShapedBrand.primary)` → `"#D1AF5D"`

---

## Phase 2: Email Templates Testing

### Test 4: Confirmation Email

**Trigger a booking confirmation email:**

1. Complete a test booking on the site
2. Check your email inbox
3. Open the confirmation email

**Visual Inspection Checklist:**
- [ ] Header gradient uses correct colors (gold gradient)
- [ ] "Booking Confirmed!" text is white
- [ ] All labels are muted gray (#666666 equivalent)
- [ ] All values are primary dark (#141310 equivalent)
- [ ] Total price is highlighted in gold (#D1AF5D equivalent)
- [ ] All links are gold color
- [ ] Map links are clickable and gold
- [ ] Contact phone/email links are gold
- [ ] "The Preelook Team" signature is gold

**Technical Inspection:**
1. View email source (HTML)
2. Search for hardcoded colors:
   - `#D1AF5D` → Should NOT exist (replaced with PHP)
   - `#666666` → Should NOT exist (replaced with PHP)
   - `<?php echo shaped_brand_color` → Should exist multiple times

---

### Test 5: Reservation Email (Delayed Payment)

**Trigger a reservation confirmation:**

1. Create a test booking with check-in >7 days away
2. Check the "Reservation Confirmed!" email

**Visual Inspection Checklist:**
- [ ] Header gradient matches brand colors
- [ ] Payment amount is highlighted in primary color
- [ ] Charge date is clearly visible
- [ ] "Manage Booking" button is primary brand color
- [ ] Button text is white
- [ ] "Free cancellation" text is visible
- [ ] Contact links are brand color

---

### Test 6: Cancellation Email

**Trigger a cancellation email:**

1. Create and then cancel a test booking
2. Check the "Booking Cancelled" email

**Visual Inspection Checklist:**
- [ ] Header gradient matches brand colors
- [ ] "Booking Cancelled" title is white on gradient
- [ ] Cancellation message is clear
- [ ] Refund amount (if shown) is highlighted
- [ ] "We hope to welcome you" message is in primary color

---

## Phase 3: PHP Inline Styles Testing

### Test 7: Manage Booking Page

**Access the manage booking page:**

1. Create a test booking
2. Get the manage booking link from confirmation email
3. Visit the link

**Visual Inspection Checklist:**

**Booking Details Section:**
- [ ] "Booking Details" header has gold bottom border
- [ ] All labels (Booking ID, Guest, etc.) are muted gray
- [ ] All values are primary dark color
- [ ] Deposit amount (if shown) is green (#4C9155)
- [ ] Balance due (if shown) is gold
- [ ] Payment status "Paid in Full" is green
- [ ] Payment status "Authorized" is gold
- [ ] Payment status "Failed" is red (#b83c2e)

**Cancel Booking Section:**
- [ ] "Cancel Your Booking" header is dark
- [ ] Description text is muted gray
- [ ] "Cancel Booking" button is red (#b83c2e)
- [ ] Button text is white
- [ ] Hover effect adds red glow (test by hovering)

**Contact Section:**
- [ ] "Questions?" text is muted gray
- [ ] Phone link is gold
- [ ] Email link is gold

---

### Test 8: Booking Cancelled Page

**Trigger the cancelled state:**

1. Cancel a test booking
2. View the cancellation confirmation page

**Visual Inspection Checklist:**
- [ ] Green checkmark icon (#4C9155)
- [ ] "Booking Cancelled" header is dark color
- [ ] Booking ID has gold bottom border
- [ ] "No Charge Applied" header is dark
- [ ] Refund amount is gold
- [ ] Confirmation text is muted gray

---

### Test 9: Thank You Page

**Complete a booking:**

1. Complete a full test booking flow
2. Reach the thank you page after payment

**Visual Inspection Checklist:**

**Success Section:**
- [ ] Green checkmark icon
- [ ] "Booking Confirmed!" is dark color
- [ ] "Successfully secured" is muted gray

**Booking Details:**
- [ ] Section header has gold bottom border
- [ ] Labels are muted gray
- [ ] Values are dark color
- [ ] Check-in/out times are clearly visible

**Payment Information:**
- [ ] Header is dark color
- [ ] Deposit amount is gold (if deposit)
- [ ] Total amount is gold
- [ ] Balance due shown correctly
- [ ] Description text is dark color

**Getting Here:**
- [ ] Header is dark color
- [ ] Address label is bold dark
- [ ] Description is muted gray

**Contact:**
- [ ] Email address is muted gray
- [ ] Phone link is gold
- [ ] Email link is gold

---

## Integration Testing

### Test 10: Color Consistency

**Verify all pages use same colors:**

1. Open in multiple tabs:
   - Confirmation email (in email client)
   - Manage booking page (in browser)
   - Thank you page (in browser)

2. **Use a color picker tool** (e.g., browser extension) to verify:
   - Primary color is consistent across all pages
   - Success green is consistent
   - Error red is consistent
   - Text colors match

3. **Create a color swatch comparison:**
   ```
   Primary:  #D1AF5D ✓
   Success:  #4C9155 ✓
   Error:    #b83c2e ✓
   Text Dark: #141310 ✓
   Text Muted: #666666 ✓
   ```

---

### Test 11: Responsive Design

**Test email rendering:**

1. Open confirmation email on:
   - [ ] Desktop email client (Gmail, Outlook)
   - [ ] Mobile email client (iPhone, Android)
   - [ ] Webmail (Gmail web, Outlook web)

2. Verify colors display correctly on all clients

**Test manage booking page:**

1. Open manage booking page on:
   - [ ] Desktop browser (1920x1080)
   - [ ] Tablet (768px width)
   - [ ] Mobile (375px width)

2. Verify:
   - [ ] Colors remain correct at all breakpoints
   - [ ] Text remains readable
   - [ ] Buttons maintain styling

---

## Client Override Testing

### Test 12: Create Test Client Override

**Set up a test client with different colors:**

1. Create `CLIENTS/test-client/brand.json`:
```json
{
  "colors": {
    "brand": {
      "primary": "#2563EB",
      "primaryHover": "#1D4ED8",
      "secondary": "#1E40AF"
    },
    "semantic": {
      "success": "#10B981",
      "error": "#EF4444"
    }
  }
}
```

2. Add to `wp-config.php`:
```php
define('SHAPED_CLIENT', 'test-client');
```

3. **Clear cache** (if using caching plugin)

4. Reload any page and check browser console:
```javascript
console.log(ShapedBrand.primary); // Should output: #2563EB (blue, not gold)
```

5. **Create a test booking** and verify:
   - [ ] All gold colors are now blue
   - [ ] Success states are new green
   - [ ] Error states are new red
   - [ ] Text colors remain unchanged (unless overridden)

6. **Clean up:** Remove test client or restore preelook config

---

### Test 13: Fallback Behavior

**Test missing color fallback:**

1. Create incomplete client override:
```json
{
  "colors": {
    "brand": {
      "primary": "#FF0000"
    }
  }
}
```

2. Verify:
   - [ ] Primary color is red (overridden)
   - [ ] Secondary color falls back to base config (#94772E)
   - [ ] All other colors use base config
   - [ ] No errors in browser console
   - [ ] No PHP errors in error log

---

## Browser Compatibility Testing

### Test 14: Cross-Browser Check

**Test in multiple browsers:**

- [ ] **Chrome:** All colors render correctly
- [ ] **Firefox:** All colors render correctly
- [ ] **Safari:** All colors render correctly
- [ ] **Edge:** All colors render correctly

**Check specifically:**
- CSS color values display
- PHP-generated inline styles work
- Hover effects function
- Links are clickable

---

## Performance Testing

### Test 15: Load Time Impact

**Measure performance:**

1. **Before brand config:**
   - Baseline load time: ___ ms

2. **After brand config:**
   - New load time: ___ ms
   - Difference: ___ ms

3. **Expected:** < 10ms additional overhead

4. **Test on:**
   - [ ] Home page
   - [ ] Booking page
   - [ ] Manage booking page
   - [ ] Thank you page

---

## Error Handling Testing

### Test 16: Missing Config File

**Test graceful degradation:**

1. Temporarily rename `config/brand.json`
2. Visit a page
3. **Expected:**
   - [ ] Error logged: "Failed to load base brand.json"
   - [ ] Page still loads (doesn't crash)
   - [ ] Helper functions return null for colors

4. **Restore** `brand.json`

---

### Test 17: Invalid JSON

**Test JSON validation:**

1. Add invalid JSON to client config:
```json
{
  "colors": {
    "brand": {
      "primary": "#D1AF5D",  // Missing closing brace
    }
  }
}
```

2. **Expected:**
   - [ ] Error logged with JSON error message
   - [ ] Falls back to base configuration
   - [ ] Page continues to function

3. **Fix** the JSON syntax

---

## Documentation Verification

### Test 18: README Accuracy

**Follow CLIENTS/README.md instructions:**

1. [ ] Create a new client using documented steps
2. [ ] Verify all code examples work
3. [ ] Confirm all paths are correct
4. [ ] Test all helper function examples

---

## Final Checklist

### All Phases Complete

- [x] **Phase 1:** Infrastructure working
  - [x] Classes load
  - [x] Helpers function
  - [x] JS integration works

- [x] **Phase 2:** Email templates updated
  - [x] Confirmation email
  - [x] Reservation email
  - [x] Cancellation email

- [x] **Phase 3:** PHP inline styles updated
  - [x] Manage booking page
  - [x] Cancelled page
  - [x] Thank you page

### Production Readiness

- [ ] All tests passing
- [ ] No hardcoded colors remaining
- [ ] Client override tested
- [ ] Documentation complete
- [ ] Performance acceptable
- [ ] No console errors
- [ ] No PHP errors
- [ ] Backward compatible

---

## Regression Testing

**After any code changes, re-run:**

1. Test 1: Automated test suite
2. Test 4-6: All email templates
3. Test 7-9: All PHP pages
4. Test 10: Color consistency
5. Test 15: Performance

---

## Troubleshooting

### Issue: Colors not changing

**Check:**
1. Is `SHAPED_CLIENT` defined correctly?
2. Does client directory exist?
3. Is `brand.json` valid JSON?
4. Clear browser cache
5. Check browser console for errors

### Issue: JavaScript colors undefined

**Check:**
1. View page source: search for `var ShapedBrand`
2. Verify wp_localize_script is running
3. Check if jQuery/shaped-checkout is enqueued
4. Clear browser cache

### Issue: Email colors not updating

**Check:**
1. Email clients cache aggressively - try new email
2. Verify PHP helpers are actually being called (view source)
3. Check server PHP error logs
4. Test in different email client

---

**Testing Guide Version:** 1.0
**Last Updated:** December 9, 2025
