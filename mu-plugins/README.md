# Must-Use Plugins (mu-plugins) for Shaped Core

## What are MU-Plugins?

Must-Use (MU) plugins are WordPress plugins that are automatically activated and run before all other plugins. They cannot be disabled from the WordPress admin interface.

## Installation

These files must be copied to your WordPress installation's `wp-content/mu-plugins/` directory:

```bash
# From the shaped-core plugin directory
mkdir -p /path/to/wordpress/wp-content/mu-plugins
cp mu-plugins/*.php /path/to/wordpress/wp-content/mu-plugins/
```

## Included MU-Plugins

### shaped-no-session-on-price-api.php

**Purpose**: Prevents session cookies on the `/wp-json/shaped/v1/price` REST API endpoints.

**Why it's needed**:
- WordPress plugins (like WP Session Manager) often start sessions on every request
- Sessions create `WP_SESSION_COOKIE` which hurts caching and may trigger bot detection
- For stateless REST APIs, sessions are unnecessary and harmful

**How it works**:
- Runs on `muplugins_loaded` (before all other plugins)
- Detects requests to `/wp-json/shaped/v1/price*`
- Disables PHP sessions via `ini_set()`
- Removes session initialization hooks from WP Session Manager and similar plugins

**Installation verification**:
1. Copy the file to `wp-content/mu-plugins/`
2. Go to WordPress admin → Plugins → Must-Use
3. You should see "Shaped - Prevent Sessions on Price API" listed

**Testing**:
```bash
# Should NOT have Set-Cookie: WP_SESSION_COOKIE header
curl -i "https://yoursite.com/wp-json/shaped/v1/price?checkin=2026-01-01&checkout=2026-01-02&adults=2"
```

## Troubleshooting

### MU-Plugin not loading

**Symptoms**: Session cookies still appear on price API requests

**Solutions**:
1. Verify the file is in `wp-content/mu-plugins/` (not in a subdirectory)
2. Check file permissions (should be readable by web server)
3. Look for PHP errors in `/wp-content/debug.log`

### Conflicts with other plugins

If the MU-plugin prevents functionality elsewhere on your site:
1. The plugin specifically checks for `/wp-json/shaped/v1/price` URLs only
2. It should not affect other parts of WordPress
3. If issues persist, review the plugin code and adjust the URL detection logic

## Security Note

MU-plugins cannot be deactivated from the WordPress admin, so only install trusted code. All Shaped Core MU-plugins are:
- Open source
- Reviewed and tested
- Focused on specific, isolated functionality
- Safe to use in production

## More Information

- [WordPress MU-Plugins Documentation](https://wordpress.org/support/article/must-use-plugins/)
- [Shaped Core Price API Documentation](../includes/pricing/API_IMPROVEMENTS.md)
