# Live Migration Plan — Base Template to Client Production Site

**Scope:** Migrate updated Shaped Core plugin, frozen MPHB v5.2.3, Shaped child theme, Elementor page designs, and mu-plugins configuration from the base template to the client's live Hostinger site — without disrupting existing bookings, Stripe state, or RoomCloud sync.

---

## Prerequisites & Assumptions

- Client's live site is on Hostinger with staging environment support
- Live site currently runs `shaped-core-live` (older version) + hardcoded config (no mu-plugins client config)
- Live site has real bookings with active Stripe payment intents and RoomCloud mappings
- Live Stripe uses client's own account with live keys; base template uses your Shaped test keys
- GitHub auto-deploy is **not** configured; deployment is manual
- MPHB on the live site must be replaced with frozen v5.2.3 simultaneously with the plugin update

---

## Phase 0 — Pre-Migration Snapshots & Inventory

**Goal:** Create recovery points and document the current live state before touching anything.

### 0.1 Full Live Site Backup
- [ ] Take a **full Hostinger backup** (files + database) from the Hostinger dashboard — label it clearly (e.g., `pre-migration-2026-02-XX`)
- [ ] Download the backup locally as an additional safety copy
- [ ] Export the database separately via phpMyAdmin or WP-CLI (`wp db export`) so you have an isolated `.sql` file

### 0.2 Document Current Live State
- [ ] Note the current MPHB version on the live site (Plugins page or `motopress-hotel-booking/motopress-hotel-booking.php` header)
- [ ] Screenshot or export the current Stripe webhook configuration from the client's Stripe dashboard (endpoint URL, events, webhook secret ID)
- [ ] Record active RoomCloud room/rate/hotel IDs from the live WordPress admin (Shaped Core > RoomCloud settings)
- [ ] Export the current `wp_options` rows with prefix `shaped_` — run via phpMyAdmin or WP-CLI:
  ```
  wp option list --search='shaped_*' --format=table
  ```
- [ ] Document the current live wp-config.php constants (Stripe keys, RoomCloud credentials, Supabase keys — note which are active vs commented out)
- [ ] List all active plugins on live site and their versions

### 0.3 Inventory Existing Bookings
- [ ] Count confirmed/pending bookings: `wp post list --post_type=mphb_booking --post_status=confirmed,pending --format=count`
- [ ] Spot-check 2-3 bookings to confirm they have correct `_shaped_*` and `_mphb_*` postmeta entries
- [ ] Verify no bookings have pending scheduled charges in Action Scheduler that would fire during migration

---

## Phase 1 — Staging Environment Setup

**Goal:** Create an isolated copy of the live site where all changes can be tested without risk.

### 1.1 Create Hostinger Staging
- [ ] Use Hostinger's staging feature to clone the live site — this gives you a subdomain (e.g., `staging.clientdomain.com`) with a full copy of files and database
- [ ] Verify the staging site loads and bookings are visible in wp-admin
- [ ] Confirm staging has its own database (changes here won't affect live)

### 1.2 Disable External Integrations on Staging
- [ ] **Stripe:** In staging's `wp-config.php`, switch to **test keys** from your Shaped Stripe account (comment out live keys, uncomment test keys). This prevents any staging actions from hitting the client's real Stripe account
- [ ] **RoomCloud:** In staging's `wp-config.php`, set `SHAPED_ENABLE_ROOMCLOUD` to `false` or comment out RoomCloud credentials entirely. You do **not** want staging to push availability changes to the channel manager
- [ ] **Emails:** Either install a mail trap plugin (e.g., WP Mail Logging) or temporarily change the admin/from email so no guest-facing emails fire from staging
- [ ] **Cron:** Disable the Hostinger cron job pointing to staging, or if one was cloned, remove it

---

## Phase 2 — Deploy Updated Stack on Staging

**Goal:** Replace MPHB, Shaped Core, theme, and mu-plugins on the staging environment.

### 2.1 Replace MPHB
- [ ] On staging, via SFTP or File Manager, rename the existing `wp-content/plugins/motopress-hotel-booking/` to `motopress-hotel-booking-backup/`
- [ ] Upload the frozen MPHB v5.2.3 from your base template to `wp-content/plugins/motopress-hotel-booking/`
- [ ] Do **not** activate yet — just place the files

### 2.2 Replace Shaped Core Plugin
- [ ] Rename `wp-content/plugins/shaped-core/` to `shaped-core-old-backup/` on staging
- [ ] Upload the new `shaped-core/` from this repo (excluding `shaped-core-live/`, `mu-plugins/`, `docs/`, and any dev-only files) to `wp-content/plugins/shaped-core/`
- [ ] Do **not** activate yet

### 2.3 Deploy MU-Plugins Configuration
This is the most important configuration step. The live site currently has hardcoded values instead of using the `shaped-client-config.php` mu-plugin.

- [ ] Copy `mu-plugins/shaped-client-config.php` to staging at `wp-content/mu-plugins/shaped-client-config.php`
- [ ] Copy `mu-plugins/shaped-client-assets/` to `wp-content/mu-plugins/shaped-client-assets/`
- [ ] **Customize shaped-client-config.php for the client:**
  - `SHAPED_CLIENT` — set to the client's identifier
  - `SHAPED_ENABLE_ROOMCLOUD` — keep `false` for staging, `true` for eventual live
  - `SHAPED_ENABLE_REVIEWS` — match current live behavior
  - `SHAPED_ENABLE_REVIEW_EMAIL` — match current live behavior or keep `false` for staging
  - Company name, legal entity, VAT, contact info — use client's real details
  - Colors, typography, layout — match client's branding (pull from whatever is hardcoded on the live site today)
  - Email config — set correct from name, from email, check-in/out times, property-specific instructions
  - Schema.org — update lodging type, amenities, price range for the client
  - Elementor sync — if using, set correct header/footer template post IDs (these will differ on the client site)
  - Supabase — set correct table name and sync settings
- [ ] Add client logos to `wp-content/mu-plugins/shaped-client-assets/logos/` (direct-logo.png, email-logo.png)

### 2.4 Update wp-config.php on Staging
- [ ] Add/verify these constants (using test values for staging):
  ```php
  define('SHAPED_STRIPE_SECRET', 'sk_test_...');      // YOUR test key for staging
  define('SHAPED_STRIPE_WEBHOOK', 'whsec_...');        // Test webhook secret
  ```
- [ ] If RoomCloud is disabled via mu-plugin flag, credentials can stay but won't be used
- [ ] Supabase constants if reviews module is active

### 2.5 Replace Theme
- [ ] On staging, upload the updated **Shaped** child theme (Hello Elementor child) from the base template to `wp-content/themes/shaped/` (or whatever the theme directory is named)
- [ ] If theme directory names differ between base template and live, use the live site's directory name to avoid breaking the active theme reference in the database
- [ ] Upload the parent Hello Elementor theme if the live version is outdated
- [ ] Activate the new theme from wp-admin (Appearance > Themes) — since it keeps the same directory name, WordPress should recognize it as the same theme

### 2.6 Activate Everything
- [ ] In wp-admin, deactivate old shaped-core if still active
- [ ] Activate the new shaped-core — **note:** the new version has a Setup Wizard redirect on first activation (line 345 of shaped-core.php). Either:
  - Complete the Setup Wizard to configure payment mode, discounts, and Stripe credentials (recommended if credentials are database-stored)
  - Or skip the wizard if you're using wp-config.php constants for Stripe (the plugin checks constants first)
- [ ] Verify MPHB is active and showing v5.2.3
- [ ] Check for PHP fatal errors in `wp-content/debug.log` (enable `WP_DEBUG` and `WP_DEBUG_LOG` temporarily)

---

## Phase 3 — Migrate Elementor Page Designs

**Goal:** Bring updated page designs from the base template to the staging site without overwriting booking data.

### 3.1 Strategy: Elementor Cloud Templates
Your instinct to use Elementor cloud templates is sound. This approach:
- Exports only page structure and design, not database content
- Does not touch `wp_posts` entries for bookings, accommodations, or rates
- Can be selectively applied page by page

Steps:
- [ ] On the **base template site**, for each updated page (Home, Rooms, Room Single, Booking/Checkout, Contact, About, etc.):
  - Open in Elementor > three-dot menu > Save as Template > choose "Cloud" (or save as `.json` and transfer manually)
- [ ] On **staging**, for each corresponding page:
  - Open in Elementor > Add Template > import from Cloud (or upload `.json`)
  - Apply the template — this replaces the page design but the page URL/slug stays the same
  - Review and adjust any dynamic content widgets that reference specific post IDs (these will differ between sites)

### 3.2 Things to Watch During Template Application
- [ ] **Header/Footer templates:** If these are Elementor Theme Builder templates, export and import them separately via Elementor > Templates > Theme Builder. The post IDs for header/footer will change — update `shaped-client-config.php` Elementor section with the new IDs
- [ ] **Dynamic content:** Any Elementor widgets pulling specific posts by ID (e.g., featured rooms) need IDs updated to match the staging/live site's accommodation post IDs
- [ ] **Menu references:** Navigation menus are stored separately in WordPress. If you've restructured the menu, recreate or update it in Appearance > Menus
- [ ] **Image references:** Images from the base template won't exist on the live site's media library. Either:
  - Upload them to staging's Media Library before applying templates
  - Or fix broken images after template application
- [ ] **Global colors/fonts:** If using Elementor global styles, verify they match the client branding after import. The `elementor-sync` module can help here if configured in shaped-client-config.php
- [ ] **Form widgets:** If any Elementor forms exist (contact forms), verify the recipient email address is set to the client's email, not the base template's

### 3.3 Pages That Must NOT Be Replaced Wholesale
- [ ] **Accommodation single pages** — these are generated by MPHB and contain booking-specific data. Only update the template/design, not the content
- [ ] **Any page where content is client-specific** (legal pages, About with client story, etc.) — apply design template but restore client copy

---

## Phase 4 — Verify Data Integrity on Staging

**Goal:** Confirm that existing bookings, payments, and integrations survived the migration.

### 4.1 Booking Integrity
- [ ] In wp-admin > Bookings, verify the total count matches the pre-migration inventory from Phase 0
- [ ] Open 3-5 existing bookings and confirm:
  - Booking status is unchanged (confirmed, pending, etc.)
  - Guest name, dates, room assignment are intact
  - `_shaped_*` postmeta (payment intent IDs, Stripe session IDs) are present
  - `_mphb_*` postmeta (check-in, check-out, room ID) are present
- [ ] Verify no bookings were duplicated or lost

### 4.2 MPHB Data Integrity
- [ ] Accommodations are visible and have correct pricing, photos, and descriptions
- [ ] Rates and seasons are intact
- [ ] Booking rules are unchanged
- [ ] Search availability works (test a date range search)

### 4.3 Payment Configuration
- [ ] Go to Shaped Core admin area and verify:
  - Payment mode is set correctly (Deposit or Dual-flow)
  - Deposit percentage or scheduled charge threshold matches intended configuration
  - Stripe keys are being read (check via Config Health page if available)
- [ ] In staging wp-config.php, temporarily verify Stripe connectivity by checking the Config Health dashboard (or check debug.log for Stripe API errors)

### 4.4 Plugin Compatibility
- [ ] Walk through every admin page added by Shaped Core (Setup Wizard, Config Health, RoomCloud if enabled, Reviews dashboard)
- [ ] Check PHP error log for warnings, notices, or deprecation errors
- [ ] Verify Action Scheduler is functional (Tools > Scheduled Actions) and no stale jobs exist

### 4.5 Frontend Verification
- [ ] Browse every major page on staging — Home, Rooms, individual room pages, Booking/Search page, Contact, Footer
- [ ] Test the full search flow: enter dates > see results > click room > view details
- [ ] Verify custom shortcodes render (`[shaped_room_cards]`, `[shaped_room_details]`, etc.)
- [ ] Check responsive behavior (mobile, tablet)
- [ ] Verify Phosphor icons render in MPHB templates
- [ ] Test the checkout flow up to the payment step (using Stripe test mode, it should redirect to Stripe Checkout or show a payment form depending on the mode)
- [ ] Check that emails are generated (via mail log plugin) when a test booking is created

---

## Phase 5 — Go Live

**Goal:** Push verified staging changes to the live site with minimal downtime.

### 5.1 Schedule a Maintenance Window
- [ ] Choose a low-traffic time (late night in the property's timezone)
- [ ] Warn the client that the site will be briefly unavailable
- [ ] If Hostinger supports maintenance mode, enable it — or use a simple maintenance plugin

### 5.2 Final Live Backup
- [ ] Take another full Hostinger backup of the live site immediately before starting (this is your rollback point)
- [ ] Export the live database one more time

### 5.3 Option A — Use Hostinger "Push Staging to Live"
If Hostinger's staging feature supports pushing staging to live:
- [ ] **Critical:** Understand exactly what Hostinger's push does. Most staging push features will **overwrite the live database**, which would destroy bookings created between staging creation and now. You need to determine:
  - Does it push only files, only database, or both?
  - If it pushes database, can you select specific tables?
- [ ] **If it pushes the database:** Do **NOT** use this feature directly. The staging database is a snapshot from when you created staging — any bookings made on the live site since then would be lost
- [ ] **If it pushes files only:** This is safe to use for the file deployment, then handle database changes separately (see 5.4)

### 5.4 Option B — Manual File Deployment (Recommended for Safety)
This is the safer approach because you control exactly what gets overwritten.

**Files to deploy (via SFTP or File Manager):**
- [ ] `wp-content/plugins/shaped-core/` — replace entirely with the new version
- [ ] `wp-content/plugins/motopress-hotel-booking/` — replace entirely with frozen v5.2.3
- [ ] `wp-content/mu-plugins/shaped-client-config.php` — deploy (new file)
- [ ] `wp-content/mu-plugins/shaped-client-assets/` — deploy (new directory)
- [ ] `wp-content/themes/shaped/` (or the child theme directory) — replace entirely

**Database changes to apply manually:**
- [ ] Elementor page designs — apply the cloud templates on the live site directly in Elementor (same process as staging, but on live). Alternatively, if you used Hostinger staging push for files, you can export/import Elementor templates via JSON
- [ ] Any new `wp_options` entries that the plugin creates on activation will be auto-created
- [ ] No table migrations are needed — the only custom table (`wp_roomcloud_sync_queue`) is created on module activation

### 5.5 Update Live wp-config.php
- [ ] Ensure **live Stripe keys** are active (not test keys):
  ```php
  define('SHAPED_STRIPE_SECRET', 'sk_live_...');
  define('SHAPED_STRIPE_WEBHOOK', 'whsec_...');
  ```
- [ ] Ensure RoomCloud credentials are present and `SHAPED_ENABLE_ROOMCLOUD` is `true` in mu-plugins
- [ ] Verify Supabase constants if using reviews
- [ ] **Double-check:** No test keys from your Shaped account should be in the live config

### 5.6 Activate on Live
- [ ] Visit wp-admin — the new Shaped Core should activate (or re-activate)
- [ ] If the Setup Wizard appears, either complete it or dismiss it (credentials from wp-config.php constants take priority)
- [ ] Verify MPHB shows v5.2.3
- [ ] Check `wp-content/debug.log` for errors
- [ ] Flush permalinks (Settings > Permalinks > Save) — this ensures REST API endpoints (`/wp-json/shaped/v1/*`) are registered

### 5.7 Apply Elementor Templates on Live
- [ ] Open each page in Elementor and apply the saved cloud templates
- [ ] Fix any dynamic content widget IDs, menu references, and image paths
- [ ] Update header/footer theme builder templates
- [ ] Update `shaped-client-config.php` with correct Elementor template post IDs if they changed

### 5.8 Post-Deployment Verification
- [ ] Repeat Phase 4 checks on the live site (bookings intact, frontend rendering, admin pages)
- [ ] Verify Stripe webhook is still receiving events — check Stripe Dashboard > Webhooks > recent attempts
- [ ] Verify the webhook endpoint URL is correct: `https://{client-domain}/wp-json/shaped/v1/stripe-webhook`
- [ ] If webhook secret changed, update it in Stripe dashboard
- [ ] Disable maintenance mode

---

## Phase 6 — Live Smoke Test (End-to-End Booking)

**Goal:** Validate the entire booking pipeline on the live site with a real transaction.

### 6.1 Create a Test Booking
- [ ] On the live site, search for available dates (pick dates that are far enough out to trigger the scheduled charge flow if using Dual-flow mode)
- [ ] Complete a booking using **your own card** — use a real card, not a test card, since this is live mode
- [ ] During checkout, verify:
  - Correct pricing and any discounts display properly
  - Stripe Checkout or Payment Intent form loads correctly
  - Payment completes successfully

### 6.2 Verify Stripe
- [ ] In the **client's Stripe dashboard**, confirm the payment/setup intent was created
- [ ] For Dual-flow scheduled charge: verify a SetupIntent was created and card details are saved to the Customer object
- [ ] For Deposit: verify a completed Checkout Session with the correct deposit amount
- [ ] Check that the webhook fired and was received (Stripe Dashboard > Webhooks > Events)

### 6.3 Verify RoomCloud
- [ ] After the booking is confirmed, check the RoomCloud dashboard (or ask the client to check)
- [ ] The new booking should appear as an availability update / reservation in RoomCloud
- [ ] Verify the correct room and dates are blocked

### 6.4 Verify Emails
- [ ] Check your inbox for the guest confirmation email
- [ ] Check the admin email inbox for the reservation notification email
- [ ] Verify email content is correct (property name, dates, pricing, branding)

### 6.5 Cancel the Test Booking
- [ ] In wp-admin, cancel the test booking
- [ ] Verify in Stripe:
  - For Dual-flow: the scheduled charge is cancelled and card details (PaymentMethod) are detached from the Customer
  - For Deposit: refund was processed (if auto-refund is configured) or no further charges occurred
- [ ] Verify in RoomCloud:
  - Cancellation webhook was sent
  - Room availability is restored for those dates
- [ ] Verify cancellation email was sent to the guest

### 6.6 Clean Up
- [ ] Delete or mark the test booking as a test in your records
- [ ] If a real charge was made, process the refund via Stripe dashboard if it wasn't automatic

---

## Phase 7 — Cleanup & Hardening

### 7.1 Remove Backup Files from Live Server
- [ ] Delete `shaped-core-old-backup/` from `wp-content/plugins/`
- [ ] Delete `motopress-hotel-booking-backup/` from `wp-content/plugins/`
- [ ] Remove any `.sql` backup files uploaded to the server

### 7.2 Remove Temporary Files from This Repo
- [ ] Remove `shaped-core-live/` directory from this repo (it was temporary reference)
- [ ] Commit cleanup

### 7.3 Verify Cron
- [ ] Ensure Hostinger cron is configured for the live domain
- [ ] Verify Action Scheduler is processing scheduled tasks (check Tools > Scheduled Actions for healthy heartbeat)

### 7.4 Verify GitHub Auto-Deploy (if applicable)
- [ ] If you plan to set up auto-deploy from GitHub to the live site, configure it now
- [ ] Ensure the deploy target path points to `wp-content/plugins/shaped-core/` on the live server

### 7.5 Monitor
- [ ] Keep `WP_DEBUG_LOG` enabled for 48 hours post-migration
- [ ] Check debug.log daily for errors
- [ ] Monitor Stripe webhook delivery success rate in the Stripe dashboard
- [ ] Ask the client to report any visual or functional issues

---

## Rollback Plan

If something goes critically wrong at any point after going live:

1. **Restore from the Phase 5.2 backup** via Hostinger's backup restore feature
2. This rolls back files AND database to the pre-migration state
3. The old shaped-core, old MPHB version, and old theme will be restored
4. All bookings made between backup and restore will be preserved in the backup
5. Stripe state is external and unaffected by WordPress rollback — any Payment Intents or Checkout Sessions created during the migration window will still exist in Stripe and may need manual cleanup

---

## Key Risks & Mitigations Summary

| Risk | Impact | Mitigation |
|------|--------|------------|
| Booking data loss from database overwrite | **Critical** — guests lose access to bookings | Never push staging database to live; only deploy files manually |
| Stripe test keys accidentally left in live config | **High** — payments fail silently | Dedicated verification step in Phase 5.5; post-deploy smoke test |
| RoomCloud fires from staging | **High** — false availability changes | Disable RoomCloud on staging in Phase 1.2 |
| Elementor template IDs mismatch | **Medium** — broken header/footer/landing | Update IDs in shaped-client-config.php after template import |
| Pricing logic changes affect existing bookings | **Medium** — incorrect charges | Existing bookings keep their stored prices; only new bookings use new logic. Verify with spot-checks |
| Setup Wizard redirect on activation | **Low** — confusing but harmless | Complete or dismiss the wizard; wp-config constants take priority |
| MPHB version mismatch with existing data | **Low** — unlikely since both are v5.2.3-based | Verify MPHB version on live before replacing |
| Scheduled charges fire during migration | **Medium** — could fail if Stripe keys are mid-swap | Schedule migration outside of any pending charge windows (check Action Scheduler) |

---

## Quick Reference: What Goes Where

| Component | Base Template Source | Live Site Destination |
|-----------|--------------------|-----------------------|
| Shaped Core plugin | `shaped-core/` (this repo, minus dev folders) | `wp-content/plugins/shaped-core/` |
| MPHB | Frozen v5.2.3 from base template | `wp-content/plugins/motopress-hotel-booking/` |
| Client config | `mu-plugins/shaped-client-config.php` (customized) | `wp-content/mu-plugins/shaped-client-config.php` |
| Client assets | `mu-plugins/shaped-client-assets/` | `wp-content/mu-plugins/shaped-client-assets/` |
| Theme | Shaped child theme from base template | `wp-content/themes/{theme-dir}/` |
| Stripe keys | Client's live keys | `wp-config.php` constants |
| RoomCloud config | Client's existing credentials | `wp-config.php` constants |
| Elementor pages | Cloud templates exported from base | Applied page-by-page on live |
| Bookings/database | **DO NOT TOUCH** | Stays on live database untouched |
