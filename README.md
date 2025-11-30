# Important context

### 1. In production folder are 3 plugins with poora architecture. They will be merged into 1, **shaped-core-refactored**. refactored folder contains the current progress.

### 2. The final plugin should follow shaped-core-architecture.md



## To-do (Claude code)

1. Checkout discount row is not showing. On production version, the css class of the row is .mphb-discount-row
2. Checkout price is  not including breakfast in the discount. In prod, css class is .mphb-price .mphb-price-current. The discounted amount sent to Stripe is correct, and that same number should be displayed on the website checkout, before entering Stripe checkout
3. Reviews and provider badges are not styled. On website they are elements with shortcodes  [unified_rating],  [provider_badge_v2], [review_content], and also parent element has css class .prs-review-card-root. Figure out and fix this
