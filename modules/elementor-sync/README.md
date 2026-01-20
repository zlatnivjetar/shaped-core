# Elementor Global Colors Sync

Automatically syncs brand configuration colors to Elementor's global color system, making `shaped-client-config.php` the single source of truth for all branding.

## Overview

This module creates a **one-way sync** from your brand config to Elementor global colors. When you change colors in `shaped-client-config.php`, you can sync them to Elementor with a single action.

## How It Works

1. **Reads** colors from `shaped-client-config.php`
2. **Maps** them to Elementor's global color slots (system + custom)
3. **Updates** the active Elementor Kit with new colors
4. **Clears** Elementor cache to ensure changes take effect

## Color Mapping

### System Colors (4 default Elementor slots)

| Elementor Slot | Brand Config Path | Purpose |
|---|---|---|
| Primary | `colors.brand.primary` | Main brand color |
| Secondary | `colors.surface.pageBlack` | Dark/inverse color |
| Text Muted | `colors.text.muted` | Muted text color |
| Accent | `colors.brand.primary` | Accent (same as primary) |

### Custom Colors (extensible)

| Elementor Slot | Brand Config Path | Purpose |
|---|---|---|
| Button Hover | `colors.brand.primaryHover` | Button hover state |
| Text Primary | `colors.text.primary` | Primary text color |
| Background Warm | `colors.surface.page` | Warm background |
| Background Alt | `colors.surface.highlight` | Alternate background |
| Background Dark | `colors.surface.pageDark` | Dark background |
| Text Muted | `colors.text.muted` | Muted text |
| Border Light | `#EDEDED` | Light border (constant) |

## Automatic Sync Triggers

The module automatically syncs colors in these scenarios:

1. **Plugin Activation** - Initial sync when plugin is activated
2. **Kit Activation** - When Elementor kit is changed/activated
3. **Daily Cron** - Optional (disabled by default)

## Manual Sync

### Via WordPress Action

Trigger sync programmatically:

```php
// Basic sync
do_action('shaped/elementor/trigger_sync');

// Force sync (clears cache first)
do_action('shaped/elementor/force_sync');
```

### Via Helper Function

```php
// Trigger sync
$result = shaped_elementor_sync();

if (is_wp_error($result)) {
    error_log($result->get_error_message());
}

// Get sync status
$status = shaped_elementor_sync_status();
print_r($status);
```

## Customization

### Modify Color Mapping

Use filters to customize which colors map to which Elementor slots:

```php
// Modify system colors mapping
add_filter('shaped/elementor/system_color_mapping', function($mapping) {
    // Change what "secondary" maps to
    foreach ($mapping as &$item) {
        if ($item['id'] === 'secondary') {
            $item['path'] = 'colors.brand.secondary';
        }
    }
    return $mapping;
});

// Modify custom colors mapping
add_filter('shaped/elementor/custom_color_mapping', function($mapping) {
    // Add new custom color
    $mapping[] = [
        'id' => 'brand_secondary',
        'title' => 'Brand Secondary',
        'path' => 'colors.brand.secondary',
        'fallback' => '#000000',
    ];
    return $mapping;
});
```

### Enable Daily Cron Sync

By default, daily sync is disabled. To enable:

```php
add_filter('shaped/elementor/enable_daily_sync', '__return_true');
```

### Modify Kit Settings Before Sync

```php
add_filter('shaped/elementor/kit_settings_before_sync', function($settings, $kit_id) {
    // Modify settings before they're saved
    // For example, add other theme settings here
    return $settings;
}, 10, 2);
```

### After Sync Hook

```php
add_action('shaped/elementor/colors_synced', function($kit_id, $colors) {
    error_log('Colors synced to Kit ID: ' . $kit_id);
    error_log('System colors: ' . print_r($colors['system_colors'], true));
}, 10, 2);
```

## Workflow

### Initial Setup

1. Configure colors in `mu-plugins/shaped-client-config.php`
2. Activate Shaped Core plugin → colors sync automatically
3. Use Elementor's global colors in your designs

### Changing Colors

1. Update colors in `shaped-client-config.php`
2. Trigger manual sync: `do_action('shaped/elementor/trigger_sync');`
3. Colors update in Elementor globally
4. All pages using global colors update automatically

## Troubleshooting

### Check Sync Status

```php
$status = shaped_elementor_sync_status();
/*
Array (
    [elementor_active] => true
    [kit_id] => 123
    [kit_title] => "Default Kit"
    [last_sync] => "2026-01-20 10:30:45"
    [can_sync] => true
)
*/
```

### Force Re-sync

If colors aren't updating, force a sync:

```php
do_action('shaped/elementor/force_sync');
```

### Check Error Logs

All sync operations log to WordPress error log:

```
[Shaped Elementor Sync] Successfully synced colors to Kit ID: 123
[Shaped Elementor Sync] Manual sync failed: Elementor not active
```

### Common Issues

**Colors not updating in Elementor editor:**
- Clear Elementor cache: Elementor → Tools → Regenerate CSS
- Force sync: `do_action('shaped/elementor/force_sync')`

**"Elementor not active" error:**
- Install and activate Elementor plugin
- Ensure Elementor is loaded before sync runs

**Kit not found:**
- Module will auto-create a Kit if none exists
- Check that Elementor is properly installed

## Architecture

```
modules/elementor-sync/
├── module.php                  # Bootstrap & hooks
├── class-color-mapper.php      # Brand config → Elementor mapping
├── class-color-sync.php        # Sync engine (interfaces with Elementor)
└── README.md                   # This file
```

### Class Responsibilities

**Color_Mapper:**
- Define mapping between brand config and Elementor slots
- Process mapping configuration
- Generate Elementor-formatted color arrays

**Color_Sync:**
- Interface with Elementor Kit system
- Get/create active Kit
- Update Kit post meta
- Clear Elementor cache

## Technical Details

### Elementor Kit Storage

Elementor stores global colors in:
- **Option:** `elementor_active_kit` (contains Kit post ID)
- **Post Meta:** `_elementor_page_settings` (on Kit post, contains all theme styles)

### Color Format

Elementor expects colors in this format:

```php
[
    'system_colors' => [
        ['_id' => 'primary', 'title' => 'Primary', 'color' => '#E2BD27'],
        ['_id' => 'secondary', 'title' => 'Secondary', 'color' => '#0B0B09'],
        ...
    ],
    'custom_colors' => [
        ['_id' => 'button_hover', 'title' => 'Button Hover', 'color' => '#B7991F'],
        ...
    ]
]
```

## Future Enhancements

Potential additions (not yet implemented):

- [ ] Admin UI with manual sync button
- [ ] Color preview table in admin
- [ ] Sync history/audit log
- [ ] Elementor → Brand config sync (bidirectional)
- [ ] Font sync (typography settings)
- [ ] Spacing/radius sync

## Support

For issues or questions:
- Check error logs for sync failures
- Use `shaped_elementor_sync_status()` for debugging
- Review color mapping with `Color_Mapper::get_mapping_preview()`
