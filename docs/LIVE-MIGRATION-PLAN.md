# Live Migration Plan

**Strategy:** Disable bookings, build everything in staging, publish staging to live (full overwrite — safe because no new data is created during the maintenance window).

**Estimated maintenance window:** ~2 hours.

---

## 1. Pre-Migration

- [ ] Trigger Hostinger daily backup, wait for completion
- [ ] Note down the current booking count in wp-admin for later verification
- [ ] **Disable bookings on live** — put the site in maintenance mode or disable the booking form so no new bookings/orders/registrations come in during the window
- [ ] **Pause RoomCloud sync** — prevent availability updates from hitting live during the window
- [ ] Create Hostinger staging environment (clones live site + database to subdomain)

---

## 2. Configure Staging

- [ ] In staging `wp-config.php`: swap Stripe live keys to test keys, set `SHAPED_ENABLE_ROOMCLOUD` to `false`
- [ ] Disable or redirect email sending on staging (mail trap plugin or change from-address)

---

## 3. Deploy Stack on Staging

- [ ] Replace `plugins/motopress-hotel-booking/` with frozen v5.2.3 from base template
- [ ] Replace `plugins/shaped-core/` with the new version from this repo
- [ ] Replace the Shaped child theme with the base template version (keep same directory name so WP recognizes it)
- [ ] Deploy `mu-plugins/shaped-client-config.php` + `shaped-client-assets/` to `wp-content/mu-plugins/`

### shaped-client-config.php — populate for client:
- Feature flags: `SHAPED_CLIENT`, `SHAPED_ENABLE_ROOMCLOUD` (false for staging), `SHAPED_ENABLE_REVIEWS`, `SHAPED_ENABLE_REVIEW_EMAIL`
- Brand: company info, colors, typography, contact details
- Email: from name, check-in/out times, property instructions
- Schema.org: lodging type, amenities, price range
- Elementor: header/footer template post IDs (will need updating after template import)
- Logos in `shaped-client-assets/logos/`

### Activate & verify:
- [ ] Activate plugins in wp-admin — Setup Wizard may redirect on first activation, complete or dismiss it (wp-config constants take priority for Stripe)
- [ ] Check `debug.log` for fatal errors
- [ ] Verify bookings are intact and unchanged in wp-admin

---

## 4. Elementor Templates

Export from base template, import on staging — this touches page design and database (post content).

- [ ] Save each updated page as Elementor Cloud template on base template site
- [ ] On staging, open each page in Elementor, import the cloud template, apply it

### Watch for:
- **Header/Footer** — import separately via Theme Builder, then update post IDs in `shaped-client-config.php`
- **Dynamic content widgets** — any widget referencing post IDs (featured rooms, etc.) needs IDs remapped to staging's accommodation posts
- **Images** — upload base template images to Media Library before or after applying templates
- **Contact forms** — verify recipient email is client's, not base template's
- **Accommodation singles** — only update the design template, not the content
- **Client-specific pages** (legal, About) — apply design but keep client's copy

---

## 5. Staging Verification

- [ ] Bookings: count matches pre-migration, spot-check 2-3 for intact postmeta
- [ ] Frontend: browse all pages, test search flow, test checkout (Stripe test mode), verify shortcodes render
- [ ] Admin: walk through Shaped Core admin pages, check error log
- [ ] Emails: create a test booking on staging, confirm email is generated (via mail trap)
- [ ] **Delete test bookings** created during verification — these will go live when you publish

---

## 6. Prepare Staging for Publish

Before publishing staging to live, undo staging-only config:

- [ ] In staging `wp-config.php`: restore live Stripe keys (remove test keys)
- [ ] In staging `mu-plugins/shaped-client-config.php`: set `SHAPED_ENABLE_ROOMCLOUD` to `true`
- [ ] Remove mail trap plugin or restore original email settings
- [ ] Verify no test/debug artifacts remain

---

## 7. Go Live

- [ ] Confirm bookings are still disabled on live (no new data since staging was created)
- [ ] In Hostinger hPanel: go to WordPress > Staging, click **Publish** (full overwrite — files + database)
- [ ] Wait for publish to complete

### Post-publish:
- [ ] Flush permalinks (Settings > Permalinks > Save) to register REST endpoints
- [ ] Check `debug.log` for fatal errors
- [ ] Verify booking count matches pre-migration count
- [ ] **Re-enable RoomCloud sync** — trigger a full availability re-sync
- [ ] **Re-enable bookings** — take the site out of maintenance mode

---

## 8. End-to-End Smoke Test

- [ ] Create a booking with your own card, targeting scheduled charge dates (Dual-flow)
- [ ] Verify: Stripe shows correct payment/setup intent, webhook fires
- [ ] Verify: RoomCloud receives booking, blocks dates
- [ ] Verify: guest + admin emails arrive with correct content and branding
- [ ] Cancel the booking
- [ ] Verify: Stripe detaches card / cancels scheduled charge, RoomCloud restores availability, cancellation email fires

---

## 9. Cleanup

- [ ] Delete the staging environment in Hostinger hPanel
- [ ] Delete backup plugin folders from server if any (`shaped-core-old-backup/`, `mphb-backup/`)
- [ ] Remove `shaped-core-live/` from this repo
- [ ] Verify Hostinger cron is pointing to live domain
- [ ] Monitor `debug.log` and Stripe webhook delivery for 48 hours

---

## Rollback

Hostinger auto-creates a backup before publishing staging. To roll back:
1. In hPanel: go to Files > Backups
2. Restore the auto-backup created right before the staging publish

This restores files + database to the exact pre-publish state. Stripe state is external and unaffected.
