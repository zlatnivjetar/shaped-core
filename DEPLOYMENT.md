# Shaped Core - Multi-Client Deployment Guide

This guide explains how to deploy Shaped Core to multiple client sites securely.

## Architecture Overview

Shaped Core uses a **clean separation** between code and configuration:

- **shaped-core** (this repo): Pure code, identical across all clients
- **MU-plugin**: Client-specific configuration (per WordPress installation)
- **wp-config.php**: Secrets only (API keys, credentials)

### Why This Architecture?

✅ **Security**: Each client only sees their own configuration
✅ **Privacy**: Hotel Magnus config never appears in Prelook's repo
✅ **Easy Updates**: Push code updates via Git without touching config
✅ **No Merge Conflicts**: Configuration lives outside the repository
✅ **12-Factor Compliance**: Configuration through environment (WordPress standard)

---

## Initial Setup (First Time Only)

### 1. Connect Repository to Hostinger

1. In Hostinger, navigate to your WordPress installation
2. Go to **Git** section
3. Connect to repository: `https://github.com/zlatnivjetar/shaped-core`
4. Set deployment path: `/public_html/wp-content/plugins/shaped-core`
5. Branch: `main` (or your deployment branch)

Now any push to your repository automatically updates the plugin on all connected sites.

---

## Deploying to a New Client

### Step 1: Create MU-Plugin Configuration

1. **Copy the template** from repository root:
   ```bash
   cp shaped-client-config.php /path/to/client/wp-content/mu-plugins/shaped-client-config.php
   ```

2. **Edit the file** and customize ALL values:
   - Client identifier (`SHAPED_CLIENT`)
   - Feature flags (RoomCloud, Reviews, etc.)
   - Company information
   - Contact details
   - Brand colors
   - Typography
   - Email templates
   - Schema.org data
   - Supabase table name (client-specific!)

### Step 2: Add Secrets to wp-config.php

Open the client's `wp-config.php` and add:

```php
// ============================================================================
// SHAPED CORE - SECRETS ONLY
// ============================================================================

// Stripe (client-specific)
define('SHAPED_STRIPE_SECRET', 'sk_live_xxxxxxxxxxxxxxxxxxxxx');
define('SHAPED_STRIPE_WEBHOOK', 'whsec_xxxxxxxxxxxxxxxxxxxxx');

// Supabase (shared across all clients in same project)
define('SUPABASE_URL', 'https://xxxxxxxxxxxxxxxxxxxxx.supabase.co');
define('SUPABASE_SERVICE_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...');

// RoomCloud (if enabled)
define('SHAPED_SYNC_SECRET', 'your-random-sync-secret-here');

// Optional: Price API protection
// define('SHAPED_PRICE_API_REQUIRE_KEY', true);
// define('SHAPED_PRICE_API_KEY', 'your-random-api-key');
```

See `docs/wp-config-additions.php` for complete reference.

### Step 3: Verify Configuration

1. Activate the **Shaped Core** plugin in WordPress admin
2. Check WordPress debug.log for: `[Shaped Brand] Loaded config from MU-plugin for client: {client_name}`
3. Test booking flow with Stripe test keys first

---

## Configuration Management

### What Goes Where?

| Type | Location | Example | Git Tracked? |
|------|----------|---------|--------------|
| **Secrets** | wp-config.php | Stripe keys, Supabase credentials | ❌ Never |
| **Client Config** | MU-plugin | Brand colors, company info, features | ⚠️ Optional (per client) |
| **Code** | shaped-core | PHP classes, modules, APIs | ✅ Yes (this repo) |

### Per-Client Git Repository (Optional)

You can version-control each client's MU-plugin separately:

```bash
# In /wp-content/mu-plugins/
git init
git add shaped-client-config.php
git commit -m "Initial config for Hotel Magnus"
git remote add origin https://github.com/yourcompany/hotel-magnus-config
git push -u origin main
```

This allows:
- Configuration history tracking
- Easy rollback to previous settings
- Backup of client-specific settings

---

## Updating Shaped Core

### Push Updates to All Clients

1. Make changes in your local `shaped-core` repository
2. Commit and push to GitHub:
   ```bash
   git add .
   git commit -m "Add new feature XYZ"
   git push origin main
   ```
3. Hostinger automatically deploys to **all connected sites**

**Important**: Updates **never** touch:
- Client MU-plugins (`/wp-content/mu-plugins/`)
- wp-config.php
- Client-specific data

### Testing Before Production

Recommended workflow:
1. Create a staging branch: `staging`
2. Connect one test site to `staging` branch
3. Test thoroughly
4. Merge to `main` to deploy to all production sites

---

## Security Best Practices

### ✅ DO

- Keep secrets in `wp-config.php` only
- Use environment-specific Stripe keys (test vs live)
- Set proper file permissions (644 for PHP files, 755 for directories)
- Use HTTPS for all production sites
- Enable `SHAPED_PRICE_API_REQUIRE_KEY` if concerned about API abuse

### ❌ DON'T

- Commit wp-config.php to Git
- Put Stripe keys in MU-plugin
- Share Supabase service keys publicly
- Use production Stripe keys during development
- Commit client-specific data to shaped-core repository

---

## Troubleshooting

### Plugin not loading configuration

**Check debug.log for:**
```
[Shaped Brand] Loaded config from MU-plugin for client: preelook
```

**If missing:**
1. Verify MU-plugin exists: `/wp-content/mu-plugins/shaped-client-config.php`
2. Check file permissions (should be readable by web server)
3. Verify `SHAPED_CLIENT` constant is defined
4. Check for PHP syntax errors in MU-plugin

### Stripe payments failing

1. Verify keys in wp-config.php (not MU-plugin!)
2. Check if using test keys with live mode (or vice versa)
3. Verify webhook secret matches Stripe dashboard
4. Test webhook endpoint: `https://yoursite.com/wp-json/shaped/v1/stripe/webhook`

### Reviews not syncing

1. Verify `SHAPED_ENABLE_REVIEWS` is `true` in MU-plugin
2. Check `reviewsTable` name matches your Supabase table
3. Verify Supabase credentials in wp-config.php
4. Check Supabase table permissions (service key needs access)

---

## Migration from JSON Config

If you have existing sites using the old `clients/` folder approach:

### Quick Migration Script

```bash
# 1. Copy template to mu-plugins
cp shaped-client-config.php /var/www/html/wp-content/mu-plugins/

# 2. Extract client data from JSON (manual)
# Open: clients/preelook/preelook.json
# Copy values into: mu-plugins/shaped-client-config.php

# 3. Verify
tail -f /var/www/html/wp-content/debug.log | grep "Shaped Brand"

# 4. Remove old config (after verification)
rm -rf /var/www/html/wp-content/plugins/shaped-core/clients/preelook/
```

### Verification Checklist

- [ ] MU-plugin loaded (check debug.log)
- [ ] Client identifier correct (`SHAPED_CLIENT`)
- [ ] Brand colors displaying correctly
- [ ] Email templates using correct values
- [ ] Stripe integration working
- [ ] Reviews syncing (if enabled)
- [ ] RoomCloud syncing (if enabled)

---

## Support

For issues or questions:
1. Check debug.log: `wp-content/debug.log`
2. Review this guide
3. Check shaped-core GitHub issues
4. Contact development team

---

## Quick Reference

### File Locations

```
/wp-content/
├── mu-plugins/
│   └── shaped-client-config.php    ← Client config (copy from repo)
├── plugins/
│   └── shaped-core/                ← From Git (auto-updated)
│       ├── shaped-client-config.php ← Template (don't edit in production)
│       └── DEPLOYMENT.md           ← This file
└── wp-config.php                   ← Secrets only
```

### Constants Reference

```php
// MU-plugin constants
SHAPED_CLIENT                 // Client identifier
SHAPED_ENABLE_ROOMCLOUD       // Enable RoomCloud integration
SHAPED_ENABLE_REVIEWS         // Enable reviews module
SHAPED_NO_SESSION            // Disable WP sessions (recommended)
SHAPED_PRICE_API_REQUIRE_KEY // Require API key for price endpoint

// wp-config.php secrets
SHAPED_STRIPE_SECRET         // Stripe secret key
SHAPED_STRIPE_WEBHOOK        // Stripe webhook secret
SUPABASE_URL                 // Supabase project URL
SUPABASE_SERVICE_KEY         // Supabase service role key
SHAPED_SYNC_SECRET           // RoomCloud webhook secret
SHAPED_PRICE_API_KEY         // Price API key (if required)
```

### Helper Functions

```php
// In MU-plugin or theme
shaped_brand('company.name')              // "Preelook Apartments"
shaped_brand('colors.brand.primary')      // "#D1AF5D"
shaped_brand('contact.email')             // "info@preelook.com"
shaped_brand()                            // Full config array
```

---

**Last Updated**: 2026-01-17
**Version**: 1.0.0
