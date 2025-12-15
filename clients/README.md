# Client Configuration

This directory contains client-specific configuration for multi-client deployments of Shaped Core. Each client can have their own brand settings, legal content, and custom configurations.

## Directory Structure

```
clients/
├── README.md                       # This file
├── _template/                      # Template for new clients (copy this)
│   ├── brand.json                  # Template brand configuration
│   └── legal/
│       ├── terms.php               # Template Terms & Conditions
│       └── privacy.php             # Template Privacy Policy
├── preelook/                       # Example: Preelook client
│   ├── brand.json                  # Brand overrides (optional, uses base if not present)
│   └── legal/
│       ├── terms.php               # Property-specific Terms & Conditions
│       └── privacy.php             # Property-specific Privacy Policy
└── [your-client]/                  # Your new client
    ├── brand.json
    └── legal/
        ├── terms.php
        └── privacy.php
```

## Quick Start: Setting Up a New Client

### Step 1: Copy the Template

```bash
cp -r clients/_template clients/your-client-name
```

### Step 2: Configure brand.json

Edit `clients/your-client-name/brand.json`:

```json
{
  "company": {
    "name": "Your Property Name",
    "tagline": "Your tagline",
    "location": "City, Country",
    "legalEntity": "Your Legal Entity Ltd",
    "vatId": "YOUR-VAT-ID",
    "jurisdiction": "Your Country"
  },
  "contact": {
    "phone": "+1 234 567 8900",
    "email": "info@yourproperty.com",
    "address": {
      "street": "123 Main Street",
      "city": "Your City",
      "postalCode": "12345",
      "country": "Your Country",
      "countryCode": "XX"
    }
  },
  "colors": {
    "brand": {
      "primary": "#2563EB",
      "primaryHover": "#1D4ED8"
    }
  }
}
```

### Step 3: Customize Legal Content

Edit the legal files to match your property's terms and local legal requirements:

- `clients/your-client-name/legal/terms.php` - Terms & Conditions
- `clients/your-client-name/legal/privacy.php` - Privacy Policy

**Important:** The template files contain placeholder text. You MUST customize these with your actual legal content. Consult a legal professional.

### Step 4: Activate the Client

Add to `wp-config.php`:

```php
define('SHAPED_CLIENT', 'your-client-name');
```

**OR** the system will auto-detect based on domain (e.g., `your-client-name.com` → `your-client-name`)

## Configuration Reference

### brand.json Structure

The brand.json file supports the following sections:

| Section | Purpose |
|---------|---------|
| `company` | Company name, legal entity, VAT ID, jurisdiction |
| `contact` | Phone, email, address, coordinates |
| `email` | Email sender settings, check-in/out times, signature |
| `schema` | SEO schema.org settings (lodging type, amenities) |
| `colors` | Brand colors, semantic colors, text colors |
| `type` | Typography settings |
| `booking` | Booking UI specific colors and styles |

### Legal Content Templates

Legal content files are PHP templates with access to the `$brand` variable:

```php
<?php
// Available in legal templates:
$company_name = $brand['company']['name'];
$email = $brand['contact']['email'];
$address = $brand['contact']['address'];
// ... etc
?>

<h2>Terms & Conditions for <?php echo esc_html($company_name); ?></h2>
```

## How It Works

### Brand Configuration

1. **Base Configuration**: `config/brand.json` contains default values
2. **Client Overrides**: `clients/[client]/brand.json` overrides specific values
3. **Deep Merge**: Only the values you specify are overridden; others inherit from base

### Legal Content Loading

1. System checks for `clients/[client]/legal/[type].php`
2. Falls back to `clients/_template/legal/[type].php` if not found
3. Shows placeholder message if neither exists

## Usage in Code

### PHP (Templates, Emails)

```php
// Get any brand value using dot notation
$primary = shaped_brand('colors.brand.primary');
$company = shaped_brand('company.name');
$email = shaped_brand('contact.email');

// Shorthand for colors
$primary = shaped_brand_color('primary');
$success = shaped_brand_color('success');

// Get full brand config
$brand = shaped_brand_all();

// Check current client
$client = shaped_brand_client(); // Returns 'preelook' or null

// Check if specific client
if (shaped_is_client('preelook')) { ... }
```

### Legal Content

```php
// Render legal content in templates
shaped_render_legal_content('terms');
shaped_render_legal_content('privacy');

// Get legal content as string
$terms_html = shaped_get_legal_content('terms');

// Check if legal content exists
if (shaped_has_legal_content('terms')) { ... }
```

### JavaScript

Brand colors are automatically available:

```javascript
console.log(ShapedBrand.primary);      // '#2563EB'
console.log(ShapedBrand.primaryHover); // '#1D4ED8'
console.log(ShapedBrand.success);      // '#10B981'
```

## New Client Checklist

When setting up a new hospitality client:

### Brand & Identity
- [ ] Create `clients/[client-name]/` directory
- [ ] Configure `brand.json` with company details
- [ ] Set brand colors (primary, secondary, hover states)
- [ ] Configure contact information and address
- [ ] Set email settings (fromName, check-in/out times, signature)

### Legal Content
- [ ] Create `legal/terms.php` with property-specific Terms & Conditions
- [ ] Create `legal/privacy.php` with property-specific Privacy Policy
- [ ] Review legal content with legal professional
- [ ] Ensure compliance with local laws (GDPR, local regulations)

### WordPress Setup
- [ ] Set `SHAPED_CLIENT` constant in `wp-config.php`
- [ ] Configure Stripe keys (`SHAPED_STRIPE_SECRET`, `SHAPED_STRIPE_WEBHOOK`)
- [ ] Set success/cancel URLs if needed

### Testing
- [ ] Verify brand colors display correctly
- [ ] Test email templates render with correct branding
- [ ] Verify checkout modals show correct legal content
- [ ] Test booking flow end-to-end
- [ ] Verify schema.org markup in page source

## Troubleshooting

### Legal content not showing
- Ensure files are named exactly `terms.php` and `privacy.php`
- Check file permissions (readable by web server)
- Verify client detection: `echo shaped_brand_client();`

### Brand colors not applying
- Clear any caching plugins
- Check browser dev tools for CSS conflicts
- Verify `brand.json` is valid JSON (use a JSON validator)

### Client not detected
- Set `SHAPED_CLIENT` constant explicitly in wp-config.php
- Ensure client folder name matches domain (without TLD)
- Check folder exists: `clients/[client-name]/`

## Support

For issues or questions, refer to the main plugin documentation or contact Shaped Systems.
