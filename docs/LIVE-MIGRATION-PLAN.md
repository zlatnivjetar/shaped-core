# Live Migration Plan

**Rule #1:** Only files get replaced. The live database is never overwritten — only added to.

---

## 1. Staging Setup

- [ ] Trigger Hostinger daily backup, wait for completion
- [ ] Create Hostinger staging environment (clones live site + database to subdomain)
- [ ] In staging `wp-config.php`: swap Stripe live keys to your test keys, set `SHAPED_ENABLE_ROOMCLOUD` to `false`
- [ ] Disable or redirect email sending on staging (mail trap plugin or change from-address)

---

## 2. Deploy Stack on Staging

All file replacements — database untouched.

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

## 3. Elementor Templates

Export from base template, import on staging — this only touches page design, not booking data.

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

## 4. Staging Verification

- [ ] Bookings: count matches pre-migration, spot-check 2-3 for intact postmeta
- [ ] Frontend: browse all pages, test search flow, test checkout (Stripe test mode), verify shortcodes render
- [ ] Admin: walk through Shaped Core admin pages, check error log
- [ ] Emails: create a test booking on staging, confirm email is generated (via mail trap)

---

## 5. Go Live

- [ ] Trigger another Hostinger backup before touching live
- [ ] **Do NOT push staging database to live** — only deploy files

### File deployment (SFTP/File Manager):
- [ ] `plugins/shaped-core/` — replace
- [ ] `plugins/motopress-hotel-booking/` — replace
- [ ] `mu-plugins/shaped-client-config.php` + `shaped-client-assets/` — deploy
- [ ] `themes/{child-theme}/` — replace

### wp-config.php:
- [ ] Verify live Stripe keys are active (no test keys)
- [ ] `SHAPED_ENABLE_ROOMCLOUD` → `true` in mu-plugin

### Post-deploy:
- [ ] Flush permalinks (Settings > Permalinks > Save) to register REST endpoints
- [ ] Apply Elementor cloud templates on live (same process as staging)
- [ ] Update header/footer template IDs in `shaped-client-config.php` if they changed
- [ ] Check `debug.log`, verify bookings are intact

---

## 6. End-to-End Smoke Test

- [ ] Create a booking with your own card, targeting scheduled charge dates (Dual-flow)
- [ ] Verify: Stripe shows correct payment/setup intent, webhook fires
- [ ] Verify: RoomCloud receives booking, blocks dates
- [ ] Verify: guest + admin emails arrive with correct content and branding
- [ ] Cancel the booking
- [ ] Verify: Stripe detaches card / cancels scheduled charge, RoomCloud restores availability, cancellation email fires

---

## 7. Cleanup

- [ ] Delete backup plugin folders from server (`shaped-core-old-backup/`, `mphb-backup/`)
- [ ] Remove `shaped-core-live/` from this repo
- [ ] Verify Hostinger cron is pointing to live domain
- [ ] Monitor `debug.log` and Stripe webhook delivery for 48 hours

---

## Rollback

Restore the Hostinger backup taken at the start of Phase 5. This rolls back all files and database. Stripe state is external and unaffected.
