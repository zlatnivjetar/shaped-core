# Checkout Modal Fix - Terms & Privacy Links

## Problem

The modal links on the checkout page were showing the error:
```
Error loading content. Please try again or visit the full page.
```

This was because the JavaScript was trying to fetch the full WordPress page HTML and extract content using CSS selectors (`.entry-content`, `main`, `article`), which didn't exist in the theme.

## Solution

The modal system now uses WordPress AJAX to load page content properly using the `the_content` filter, which ensures the content is formatted correctly regardless of the theme structure.

## How to Update Your Code

### Before (Old Code - WILL NOT WORK)
```php
$termsLink = '<a class="shaped-terms-link modal-trigger" href="' . esc_url($termsPageUrl) . '" data-modal="terms">' . __('Booking Terms', 'motopress-hotel-booking') . '</a>';
```

### After (New Code - REQUIRED)
```php
$termsLink = shaped_get_terms_modal_link(__('Booking Terms', 'motopress-hotel-booking'));
$privacyLink = shaped_get_privacy_modal_link(__('Privacy Policy', 'motopress-hotel-booking'));
```

### Full Example for renderTermsAndConditions()

Replace the terms acceptance section in your `renderTermsAndConditions()` method with:

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

## Helper Functions

Two new helper functions are now available:

### `shaped_get_terms_modal_link()`
```php
/**
 * Get Terms and Conditions modal link
 *
 * @param string $label Link text (optional, defaults to "Booking Terms")
 * @param string $class Additional CSS classes (optional)
 * @return string Modal link HTML
 */
shaped_get_terms_modal_link($label = null, $class = '')
```

### `shaped_get_privacy_modal_link()`
```php
/**
 * Get Privacy Policy modal link
 *
 * @param string $label Link text (optional, defaults to "Privacy Policy")
 * @param string $class Additional CSS classes (optional)
 * @return string Modal link HTML
 */
shaped_get_privacy_modal_link($label = null, $class = '')
```

## What Changed

1. **JavaScript now uses WordPress AJAX** (`includes/class-admin.php:216-244`)
   - Uses the `shaped_load_modal_content` endpoint
   - Properly applies WordPress content filters
   - Works with any theme structure

2. **Links must include `data-page-id` attribute**
   - The helper functions automatically add this
   - The JavaScript needs the page ID to load content via AJAX

3. **AJAX URL is localized**
   - `window.shapedAjax.ajaxUrl` is now available in the checkout pages
   - Points to WordPress admin-ajax.php

## Technical Details

### Updated Files

1. **`templates/checkout-modals.php`**
   - Changed content loading from fetch + DOM parsing to WordPress AJAX
   - Added AJAX URL localization
   - Updated click handler to extract `data-page-id`

2. **`includes/checkout-helpers.php`** (NEW)
   - Helper functions for generating modal links
   - Automatically includes required attributes

3. **`shaped-core.php`**
   - Loads checkout-helpers.php

### How It Works

1. User clicks link with `modal-trigger` class and `data-modal="terms"` or `data-modal="privacy"`
2. JavaScript prevents default link behavior
3. Opens the corresponding modal (`#terms-modal` or `#privacy-modal`)
4. Extracts `data-page-id` from the link
5. Sends AJAX request to WordPress with the page ID
6. WordPress returns the formatted content using `the_content` filter
7. Content is inserted into the modal body

## WordPress Page Configuration

The modal links automatically use:

- **Terms & Conditions**: The page ID from `MPHB()->settings()->pages()->getTermsAndConditionsPageId()`
- **Privacy Policy**: The page ID from WordPress's built-in privacy policy setting (`wp_page_for_privacy_policy`)

Make sure these pages are configured correctly in:
1. **Hotel Booking → Settings → Confirmation & Cancellation** (for Terms)
2. **Settings → Privacy** (for Privacy Policy)

## Fallback Behavior

If the AJAX request fails, the modal will show an error message with a link to view the full page:

```
Error loading content. Please try again or visit the full page.
```

The "full page" link will open the WordPress page in a new tab.

## Testing

To test the fix:

1. Go to your checkout page
2. Click "Booking Terms" or "Privacy Policy" links
3. Modal should open with properly formatted content
4. Content should match what's on the actual WordPress pages
5. Close modal with X button, ESC key, or clicking outside

## Support

If you continue to see errors:

1. Check browser console for JavaScript errors
2. Verify the WordPress pages are published and contain content
3. Verify page IDs are configured in WordPress settings
4. Check that `admin-ajax.php` is accessible (not blocked by security plugins)
