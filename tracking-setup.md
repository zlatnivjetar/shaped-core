In `class-booking-manager.php` (thank-you / confirmation output):

* Added stable tracking IDs to payment amounts so GTM can read them reliably:

  * `#shaped-booking-total` = total booking value (used as GA4/Ads conversion `value`)
  * `#shaped-paid-now` = amount charged today (hidden in full/authorize flow; visible in deposit flow)
  * `#shaped-balance-due` = remaining balance on arrival (deposit flow only)

* Added a hidden payment mode flag:

  * `#shaped-payment-mode` = `deposit` / `full` / `authorize`

* Added a hidden booking reference for deduping:

  * `#shaped-booking-id` = booking ID (for GA4 `transaction_id` / Ads dedupe)

That’s the whole implementation: **make conversion value + payment context + unique ID available on `/thank-you` via DOM IDs** for GTM/GA4/Ads.
