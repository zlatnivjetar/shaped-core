# Debugging Modal Loading Issue

The modal gets stuck on "Loading..." which means the AJAX request is failing. I've added extensive console logging to help diagnose the issue.

## Step 1: Check Browser Console

1. Open your checkout page
2. Open browser developer tools (F12)
3. Go to the **Console** tab
4. Click on one of the modal links (Terms or Privacy)
5. Look for messages starting with `[Shaped Modal]`

## What to Look For

### Expected Console Output (Success):
```
[Shaped Modal] Trigger clicked: <a class="shaped-terms-link modal-trigger" ...>
[Shaped Modal] Trigger dataset: {modal: "terms", pageId: "123"}
[Shaped Modal] Modal type: terms
[Shaped Modal] Modal element: <div id="terms-modal" ...>
[Shaped Modal] Extracted pageUrl: https://yoursite.com/terms-and-conditions
[Shaped Modal] Extracted pageId: 123
[Shaped Modal] Loading content for page ID: 123
[Shaped Modal] Page URL: https://yoursite.com/terms-and-conditions
[Shaped Modal] AJAX URL: https://yoursite.com/wp-admin/admin-ajax.php
[Shaped Modal] Sending AJAX request...
[Shaped Modal] Response status: 200
[Shaped Modal] Response data: {success: true, data: {content: "...", title: "..."}}
[Shaped Modal] Content loaded successfully
```

### Possible Errors:

#### Error 1: Missing pageId
```
[Shaped Modal] Extracted pageId: undefined
[Shaped Modal] Missing pageUrl or pageId
```
**Cause:** You're not using the helper functions correctly
**Fix:** Make sure you're using:
```php
$termsLink = shaped_get_terms_modal_link(__('Booking Terms', 'motopress-hotel-booking'));
$privacyLink = shaped_get_privacy_modal_link(__('Privacy Policy', 'motopress-hotel-booking'));
```

#### Error 2: Invalid page ID (0 or undefined)
```
[Shaped Modal] Invalid page ID: 0
Error: Page not configured
```
**Cause:** The WordPress page is not configured
**Fix:**
- For Terms: Go to **Hotel Booking → Settings → Confirmation & Cancellation** and set the Terms & Conditions page
- For Privacy: Go to **Settings → Privacy** and set the Privacy Policy page

#### Error 3: AJAX URL not available
```
[Shaped Modal] AJAX URL not available
Error: AJAX configuration missing
```
**Cause:** The `window.shapedAjax` object is not being set
**Fix:** Make sure you're on a checkout page or a page with `[mphb_checkout]` shortcode

#### Error 4: Network error
```
[Shaped Modal] Response status: 404
Error loading content: Network error: 404
```
**Cause:** AJAX endpoint not found
**Fix:** Check if `admin-ajax.php` is accessible at `/wp-admin/admin-ajax.php`

#### Error 5: AJAX returns error
```
[Shaped Modal] Response data: {success: false, data: {message: "Page not found"}}
```
**Cause:** The page ID doesn't correspond to a published page
**Fix:** Verify the Terms/Privacy pages exist and are published

## Step 2: Verify Your Code

Make sure your `renderTermsAndConditions()` code looks like this:

```php
<p class="mphb-terms-and-conditions-accept">
    <label>
        <input type="checkbox" id="mphb_accept_terms" name="mphb_accept_terms" value="1" required="required" />
        <?php
            $termsLink = shaped_get_terms_modal_link(__('Booking Terms', 'motopress-hotel-booking'));
            $privacyLink = shaped_get_privacy_modal_link(__('Privacy Policy', 'motopress-hotel-booking'));

            printf(__('I agree to the %s and %s for this reservation', 'motopress-hotel-booking'), $termsLink, $privacyLink);
        ?>
        <abbr title="<?php esc_html_e('Required', 'motopress-hotel-booking'); ?>">*</abbr>
    </label>
</p>
```

## Step 3: Test AJAX Endpoint Directly

Test if the AJAX endpoint is working by running this in the browser console (on your checkout page):

```javascript
// Replace 123 with your actual terms page ID
const formData = new FormData();
formData.append('action', 'shaped_load_modal_content');
formData.append('page_id', '123');

fetch(window.shapedAjax.ajaxUrl, {
    method: 'POST',
    body: formData,
    credentials: 'same-origin'
})
.then(response => response.json())
.then(data => console.log('AJAX Response:', data));
```

Expected response:
```json
{
  "success": true,
  "data": {
    "content": "<p>Your terms and conditions content...</p>",
    "title": "Terms and Conditions"
  }
}
```

## Step 4: Check Page IDs

Verify the page IDs are correct:

```javascript
// In browser console, inspect the links
document.querySelector('.shaped-terms-link').dataset.pageId
document.querySelector('.shaped-privacy-link').dataset.pageId
```

This should return the actual WordPress page IDs (numbers like 123, 456, etc.)

## Step 5: Inspect Generated HTML

View page source and find the modal trigger links. They should look like:

```html
<a class="shaped-terms-link modal-trigger"
   href="https://yoursite.com/terms-and-conditions"
   data-modal="terms"
   data-page-id="123">Booking Terms</a>

<a class="shaped-privacy-link modal-trigger"
   href="https://yoursite.com/privacy-policy"
   data-modal="privacy"
   data-page-id="456">Privacy Policy</a>
```

## Common Issues

### Issue: Empty pageId
If you see `data-page-id="0"` or `data-page-id=""`:
- The WordPress page is not configured
- Configure it in WordPress admin settings

### Issue: No data-page-id attribute
If the attribute is missing entirely:
- You're not using the helper functions
- You're using old code
- Update to use `shaped_get_terms_modal_link()` and `shaped_get_privacy_modal_link()`

### Issue: Modal doesn't open at all
- Check console for JavaScript errors
- Verify `.modal-trigger` class is present on the links
- Verify `data-modal` attribute has value "terms" or "privacy"

## Report Back

After checking the console, report back with:
1. The exact console output when clicking a modal link
2. The generated HTML of the modal links (view source)
3. Any error messages you see

This will help me identify the exact issue!
