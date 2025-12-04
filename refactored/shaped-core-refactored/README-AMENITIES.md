# Amenity Icon Mapping System

The Shaped Core plugin now includes a centralized amenity-to-icon mapping system that replaces hardcoded icon arrays with a flexible, extensible registry using Phosphor Icons.

## Features

- **40+ Pre-mapped Amenities**: Common hospitality amenities mapped to Phosphor icons
- **Fuzzy Matching**: Automatically matches facility names, slugs, and keywords
- **Priority Ordering**: Display amenities in importance order
- **Custom Icon Override**: Manual icon selection per taxonomy term in WordPress admin
- **Caching**: Performance-optimized with automatic caching
- **Extensible**: Filter-based system for custom modifications

## Architecture

### Files

```
shaped-core/
├── config/
│   └── amenities-registry.json          # Master icon registry (40 amenities)
├── includes/
│   ├── class-amenity-mapper.php         # Icon mapper with fuzzy matching & caching
│   ├── class-assets.php                 # Phosphor Icons CDN enqueued here
│   ├── class-loader.php                 # Autoloader (Shaped_Amenity_Mapper registered)
│   └── helpers.php                      # Public helper functions
├── templates/
│   ├── amenities-example.php            # Usage examples
│   └── facilities-replacement.php       # Drop-in replacement for MotoPress template
└── shaped-core.php                      # Plugin init (Shaped_Amenity_Mapper initialized)
```

## Usage

### Basic Usage

```php
// Get icon data for a facility term
$icon_data = shaped_get_amenity_icon($facility);

if ($icon_data) {
    echo $icon_data['html'];    // <i class="ph ph-wifi-high"></i>
    echo $icon_data['label'];   // "WiFi"
    echo $icon_data['priority']; // 10
}
```

### Display Amenities for a Room

```php
// Get all amenities for a room type, sorted by priority
$amenities = shaped_get_amenities_for_room($room_type_id);

foreach ($amenities as $amenity) {
    echo '<li>';
    echo '<span class="icon">' . $amenity['html'] . '</span>';
    echo '<span class="label">' . esc_html($amenity['label']) . '</span>';
    echo '</li>';
}
```

### Render Complete Badge

```php
// Render a complete amenity badge with icon and label
echo shaped_render_amenity_badge($facility);

// With custom label
echo shaped_render_amenity_badge($facility, 'Custom Label');

// With custom icon weight
echo shaped_render_amenity_badge($facility, '', ['weight' => 'bold']);
```

### Template Integration

Replace your MotoPress `facilities.php` template with:

```php
<?php
$roomType = MPHB()->getCurrentRoomType();

echo '<ul class="mphb-room-amenities-list">';

// Room size
$size = $roomType->getSize();
if (!empty($size)) {
    echo '<li class="mphb-amenity-item">';
    echo '<span class="mphb-amenity-icon"><i class="ph ph-ruler"></i></span>';
    echo '<span class="mphb-amenity-text">' . esc_html($size) . 'm²</span>';
    echo '</li>';
}

// Bed type
$bedType = $roomType->getBedType();
if (!empty($bedType)) {
    echo '<li class="mphb-amenity-item">';
    echo '<span class="mphb-amenity-icon"><i class="ph ph-bed"></i></span>';
    echo '<span class="mphb-amenity-text">' . esc_html($bedType) . '</span>';
    echo '</li>';
}

// Facilities with automatic icon mapping
$amenities = shaped_get_amenities_for_room(get_the_ID());
foreach ($amenities as $amenity) {
    echo '<li class="mphb-amenity-item">';
    echo '<span class="mphb-amenity-icon">' . $amenity['html'] . '</span>';
    echo '<span class="mphb-amenity-text">' . esc_html($amenity['label']) . '</span>';
    echo '</li>';
}

echo '</ul>';
?>
```

## Icon Matching Priority

The mapper uses this priority order:

1. **Custom field override** - Manual selection in taxonomy term edit screen
2. **Exact slug match** - Registry lookup by facility slug
3. **Normalized name match** - Case-insensitive, special chars removed
4. **Keyword contains** - Partial matching against keyword array
5. **Fallback icon** - Generic icon if no match found

### Examples

```php
// These all match to the same icon:
"Private Kitchen"   → cooking-pot
"Kitchen"           → cooking-pot
"Kitchenette"       → cooking-pot (keyword match)
"private-kitchen"   → cooking-pot (slug match)
```

## Admin Features

### Custom Icon Override

In WordPress admin:
1. Go to **Accommodations → Facilities**
2. Edit any facility term
3. Find the **"Custom Icon"** field
4. Enter a Phosphor icon name (e.g., `wifi-high`, `shower`, `coffee`)
5. Save

The custom icon will override automatic matching.

### Icon Preview

The facilities list table shows a preview of the icon for each term.

## Extending the System

### Add Custom Amenities

Filter the registry to add your own:

```php
add_filter('shaped/amenities/registry', function($registry) {
    $registry['amenities'][] = [
        'slug'     => 'hot-tub',
        'icon'     => 'bathtub',
        'label'    => 'Hot Tub',
        'priority' => 25,
        'keywords' => ['jacuzzi', 'spa bath', 'whirlpool']
    ];
    return $registry;
});
```

### Change Icon Weight

Use different Phosphor icon weights:

```php
// Bold weight
$icon_data = shaped_get_amenity_icon($facility, ['weight' => 'bold']);

// Light weight
$icon_data = shaped_get_amenity_icon($facility, ['weight' => 'light']);

// Available weights: regular (default), bold, light, thin, duotone, fill
```

### Custom Classes

Add custom CSS classes to icons:

```php
$icon_data = shaped_get_amenity_icon($facility, [
    'class' => 'my-custom-class another-class'
]);

echo $icon_data['html']; // Includes your custom classes
```

## Phosphor Icons

All icons use [Phosphor Icons](https://phosphoricons.com/) - a flexible icon family with 6 weights.

- **CDN**: Automatically enqueued via `https://unpkg.com/@phosphor-icons/web@2.0.3`
- **Usage**: `<i class="ph ph-{icon-name}"></i>`
- **Weights**: Regular (default), bold, light, thin, duotone, fill
- **Browse**: https://phosphoricons.com/

### Registry Icons

The registry includes 40 common amenities:

- Bathroom, Kitchen, Parking, Smoke-free, Sea view
- Room service, Living room, Air conditioning, Heating, WiFi
- TV, Balcony, Minibar, Safe, Coffee maker
- Washer, Dryer, Iron, Hairdryer, Desk
- Soundproof, Wheelchair accessible, Pet friendly, Pool, Gym
- Spa, Garden, Mountain view, City view, Breakfast
- Private entrance, Fireplace, Dishwasher, Microwave, Towels
- Toiletries, BBQ, EV charging, Bicycle rental, Games room

## API Reference

### Functions

#### `shaped_get_amenity_icon( $facility, $args = [] )`

Get icon data for a facility.

**Parameters:**
- `$facility` (WP_Term|string) - Facility term object or slug
- `$args` (array) - Optional arguments
  - `weight` (string) - Icon weight: regular, bold, light, thin, duotone, fill
  - `class` (string) - Additional CSS classes

**Returns:** `array|null`
- `icon` (string) - Icon name
- `label` (string) - Display label
- `weight` (string) - Icon weight
- `class` (string) - Full class string
- `html` (string) - Complete `<i>` tag
- `priority` (int) - Sort priority

#### `shaped_get_amenities_for_room( $room_type_id )`

Get all amenities for a room type, sorted by priority.

**Parameters:**
- `$room_type_id` (int) - Room type post ID

**Returns:** `array` - Array of icon data arrays

#### `shaped_render_amenity_badge( $facility, $label = '', $args = [] )`

Render complete amenity badge HTML.

**Parameters:**
- `$facility` (WP_Term|string) - Facility term or slug
- `$label` (string) - Optional custom label
- `$args` (array) - Optional arguments (same as above)

**Returns:** `string` - HTML output

#### `shaped_get_amenities_registry()`

Get the full amenities registry.

**Returns:** `array` - Complete registry array

### Filters

#### `shaped/amenities/registry`

Filter the amenities registry.

```php
add_filter('shaped/amenities/registry', function($registry) {
    // Modify registry
    return $registry;
});
```

## Performance

- **Caching**: Results cached per facility to avoid repeated lookups
- **Static loading**: Registry loaded once per request
- **Conditional**: Icon mapper only initializes when needed

## Migration from Hardcoded Icons

### Before (hardcoded array)

```php
$facilityIcons = [
    'private-bathroom' => ['icon' => 'bath', 'label' => 'Private Bathroom', 'priority' => 1, 'type' => 'svg'],
    'private-kitchen' => ['icon' => 'mdi-stove', 'label' => 'Private Kitchen', 'priority' => 2, 'type' => 'mdi'],
    // ... 9 more entries
];

foreach ($facilities as $facility) {
    $slug = $facility->slug;
    if (isset($facilityIcons[$slug])) {
        // Complex rendering logic for SVG, MDI, Font Awesome
    }
}
```

### After (centralized system)

```php
$amenities = shaped_get_amenities_for_room(get_the_ID());

foreach ($amenities as $amenity) {
    echo '<li>';
    echo '<span class="icon">' . $amenity['html'] . '</span>';
    echo '<span class="label">' . esc_html($amenity['label']) . '</span>';
    echo '</li>';
}
```

**Benefits:**
- ✅ Single icon library (Phosphor) instead of 3 different libraries
- ✅ Automatic matching with fuzzy logic
- ✅ 40 amenities vs 11 hardcoded
- ✅ Admin UI for custom overrides
- ✅ Extensible via filters
- ✅ Cached for performance
- ✅ Centralized in plugin, not template

## Support

For issues or questions:
1. Check the template examples in `templates/`
2. Review this documentation
3. Inspect `config/amenities-registry.json` for available icons
4. Browse Phosphor Icons at https://phosphoricons.com/
