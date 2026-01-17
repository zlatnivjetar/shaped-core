# ⚠️ DEPRECATED: Client Configuration Folder

**This folder and JSON-based configuration approach is deprecated.**

## Why Deprecated?

The `clients/` folder approach has **critical security and privacy issues**:

❌ **Privacy Violation**: All client configs stored in one repository
- Hotel Magnus can see Prelook's configuration
- Client A sees Client B's data when repo is deployed
- Violates client confidentiality

❌ **Security Risk**: Configuration mixed with code
- Client-specific secrets might leak into Git
- Harder to manage separate client deployments
- Configuration changes require code commits

❌ **Deployment Issues**:
- Git deployment pushes ALL client configs to ALL sites
- Can't update one client without affecting others
- Merge conflicts when multiple clients updated

---

## ✅ New Approach: MU-Plugin Configuration

Use the **MU-plugin** approach for all new deployments:

### Benefits:
✅ Each site has only its own configuration
✅ Secrets stay in wp-config.php (WordPress standard)
✅ Code updates via Git don't touch configuration
✅ Perfect separation: code (Git) vs config (environment)
✅ Secure, private, and follows WordPress best practices

### Quick Start:

1. **Copy template**:
   ```bash
   cp shaped-client-config.php /wp-content/mu-plugins/shaped-client-config.php
   ```

2. **Edit configuration**: Update all values in the MU-plugin

3. **Add secrets to wp-config.php**:
   ```php
   define('SHAPED_STRIPE_SECRET', 'sk_live_...');
   define('SUPABASE_URL', 'https://...');
   ```

4. **Activate plugin**: WordPress will auto-load the MU-plugin

**See complete instructions**: [DEPLOYMENT.md](../DEPLOYMENT.md)

---

## Migration from JSON Config

If you have existing clients using this folder:

### For Each Client Site:

1. **Copy MU-plugin template**:
   ```bash
   cp /path/to/shaped-core/shaped-client-config.php \
      /path/to/wp-content/mu-plugins/shaped-client-config.php
   ```

2. **Transfer JSON values** from `clients/{client}/{client}.json` to MU-plugin function

3. **Verify** configuration loads:
   ```bash
   tail -f wp-content/debug.log | grep "Shaped Brand"
   ```
   Should see: `[Shaped Brand] Loaded config from MU-plugin for client: {name}`

4. **Test** thoroughly (booking, emails, branding)

5. **Delete old JSON** (after verification)

### What to Migrate:

From `clients/{client}/brand.json` → **MU-plugin**:
- Company information
- Contact details
- Brand colors
- Email settings
- Schema.org data
- Integration settings

From `wp-config.php` or current setup → **Keep in wp-config.php**:
- Stripe API keys
- Supabase credentials
- Webhook secrets
- API authentication keys

---

## Legacy Content (Deprecated)

This folder still contains:

### `_template/`
- Legacy JSON template (deprecated)
- Legal content templates (still useful as reference)
- **Do not use for new deployments**

### `preelook/`
- Example client configuration (deprecated)
- Will be removed in future version
- **Reference only**

### `supabase-instructions.txt`
- Still relevant for Supabase setup
- **Keep for reference**

---

## Files Reference

```
clients/
├── README.md                    ← This file (deprecation notice)
├── _template/                   ← DEPRECATED: JSON template
│   ├── template.json
│   └── legal/                   ← Legal templates (still useful)
│       ├── terms.php
│       └── privacy.php
├── preelook/                    ← DEPRECATED: Example client
│   └── preelook.json
└── supabase-instructions.txt    ← Still relevant
```

**✅ Use instead**: `shaped-client-config.php` in repository root

---

## Support

**New deployments**: See [DEPLOYMENT.md](../DEPLOYMENT.md)

**Migration help**: See migration section above

**Questions**: Check GitHub issues or contact development team

---

**Status**: DEPRECATED as of 2026-01-17
**Migration Required**: Yes
**Removal**: Future version (after migration period)
**Replacement**: MU-Plugin approach (see shaped-client-config.php)
