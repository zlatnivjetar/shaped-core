# Client Brand Configuration

This directory contains client-specific brand configuration overrides for multi-client deployments of Shaped Core.

## Structure

```
clients/
├── README.md                    # This file
├── preelook/
│   └── brand.json              # Preelook-specific overrides
└── [your-client]/
    └── brand.json              # Your client-specific overrides
```

## How It Works

1. **Base Configuration**: The base `config/brand.json` contains default brand colors and design tokens
2. **Client Overrides**: Each client can have a `clients/[client-name]/brand.json` that overrides specific values
3. **Deep Merge**: Client values are merged into the base config, only changing what's different

## Setting Up a New Client

### Step 1: Create Client Directory

```bash
mkdir clients/[client-name]
```

### Step 2: Create brand.json

Create `clients/[client-name]/brand.json` with only the values you want to override:

```json
{
  "colors": {
    "brand": {
      "primary": "#2563EB",
      "primaryHover": "#1D4ED8",
      "secondary": "#1E40AF"
    }
  }
}
```

### Step 3: Activate Client Configuration

Add to `wp-config.php`:

```php
define('SHAPED_CLIENT', 'client-name');
```

**OR** the system will auto-detect based on domain (e.g., `client-name.com` → `client-name`)

## Available Color Tokens

See `config/brand.json` for the complete structure. Key tokens include:

### Brand Colors
- `colors.brand.primary` - Main brand color
- `colors.brand.primaryHover` - Hover state
- `colors.brand.secondary` - Secondary brand color

### Semantic Colors
- `colors.semantic.success` - Success states
- `colors.semantic.error` - Error states
- `colors.semantic.warning` - Warning states

### Text Colors
- `colors.text.primary` - Primary text
- `colors.text.muted` - Muted/secondary text
- `colors.text.inverse` - Text on dark backgrounds

### Surface Colors
- `colors.surface.page` - Page background
- `colors.surface.card` - Card backgrounds
- `colors.surface.alt` - Alternate surface

## Usage in Code

### In PHP (Email Templates, Inline Styles)

```php
// Get any brand value using dot notation
$primary = shaped_brand('colors.brand.primary'); // '#D1AF5D'

// Shorthand for colors
$primary = shaped_brand_color('primary'); // '#D1AF5D'
$success = shaped_brand_color('success'); // '#4C9155'

// In email templates
<td style="background: <?php echo shaped_brand_color('primary'); ?>;">
    Content
</td>

// In PHP inline styles
$style = 'color: ' . shaped_brand_color('primary') . '; border: 1px solid ' . shaped_brand_color('secondary') . ';';
```

### In JavaScript (Hover Effects)

Brand colors are automatically available in JavaScript as `ShapedBrand`:

```javascript
// Access colors
console.log(ShapedBrand.primary);      // '#D1AF5D'
console.log(ShapedBrand.primaryHover); // '#C39937'
console.log(ShapedBrand.success);      // '#4C9155'

// Use in hover effects
button.onmouseenter = function() {
    this.style.background = ShapedBrand.primaryHover;
};
```

## Example: Full Client Configuration

```json
{
  "colors": {
    "brand": {
      "primary": "#2563EB",
      "primaryHover": "#1D4ED8",
      "secondary": "#1E40AF"
    },
    "semantic": {
      "success": "#10B981",
      "error": "#EF4444"
    },
    "text": {
      "primary": "#1F2937"
    }
  },
  "type": {
    "baseSize": 15
  }
}
```

## Testing

To test your configuration:

1. Load the test file: Include `test-brand-config.php` in your theme's `functions.php`
2. Visit WordPress Admin → Plugins page
3. View the test results panel

Or manually test in code:

```php
// Check active client
$client = shaped_brand_client(); // Returns 'preelook' or null

// Test color retrieval
$color = shaped_brand_color('primary');
var_dump($color); // Should output your brand color
```

## Migration Checklist

When setting up a new client:

- [ ] Create `clients/[client-name]/` directory
- [ ] Copy `brand.json` template and modify colors
- [ ] Set `SHAPED_CLIENT` constant in `wp-config.php`
- [ ] Test color retrieval with test script
- [ ] Verify email templates use correct colors
- [ ] Verify booking UI uses correct colors
- [ ] Test JavaScript hover effects

## Support

For issues or questions, refer to the main plugin documentation or contact Shaped Systems.
