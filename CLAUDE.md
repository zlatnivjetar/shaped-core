# Shaped Core Plugin

WordPress plugin that owns all custom logic for Shaped hospitality systems.
Sits on top of frozen MotoPress Hotel Booking (MPHB) v. 5.2.3.
## Architecture
```
shaped-core/
├── admin/          # WP admin UI, settings pages
├── config/         # Feature flags, constants, environment config
├── assets/         # CSS, JS and font files
├── core/           # Core classes, bootstrapping
├── modules/        # Feature modules (payments, emails, availability, etc.)
├── includes/       # Shared utilities, helpers
├── templates/      # Frontend templates, overrides
├── shortcodes/     # WP shortcodes
├── schema/         # Database schema, migrations
├── vendor/           # Stripe folder (do not touch)
├── docs/           # Internal documentation
├── shaped-core.php # Main plugin file
├── mu-plugins/   # Only for reference here, does not live inside the plugin
```

## Critical Rules

1. **Prefer hooking into MPHB** — we find hooks and create logic inside plugin
2. **All emails go through Shaped Core** — MPHB email system is disabled
3. **All payments go through Shaped Core** — MPHB payment handling is disabled
4. **Stripe is the only payment processor** — two modes: Deposit OR Dual-flow (system-wide, never per-booking)
5. **mu-plugins folder should never be modified** — here only for reference

## Payment Modes (mutually exclusive)

- **Deposit**: Percentage upfront via Stripe Checkout
- **Dual-flow**: If booking > N days out → scheduled Payment Intent; otherwise → immediate Stripe Checkout

## Key Integration Points

- `modules/` — where most feature code lives
- `config/` — feature toggles, Stripe keys, RoomCloud credentials
- `core/` — plugin initialization, hook registration
- `templates/` — checkout form overrides, search result cards

## Risk Levels

**HIGH** (test thoroughly, can break bookings/revenue):
- Payment orchestration logic
- Stripe webhook handlers
- RoomCloud webhook handlers
- Availability sync/override logic

**MEDIUM** (can break UX):
- Email templates and triggers
- Checkout flow modifications
- Search result rendering

**SAFE**:
- Admin UI changes
- Shortcode additions
- Documentation


## External Dependencies (not in this repo)

- **MPHB v5.2.3** (frozen, treated as read-only)
- **Stripe PHP SDK** (via Composer in vendor/)
- **RoomCloud API** (external, webhook-based)