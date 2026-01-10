# Adding Phosphor Icons to Elementor Pro

This plugin already loads Phosphor icons globally on your site. To make them available in Elementor's Icon Picker, follow these steps:

## Option 1: Via Elementor Pro Icon Manager (Recommended)

1. **Download Phosphor Icon Library for Elementor**
   - Visit: https://github.com/phosphor-icons/pack
   - Download the Elementor-compatible version
   - OR create a ZIP file with our local files (see below)

2. **Upload to Elementor**
   - Go to: **WordPress Admin → Elementor → Custom Icons**
   - Click **"Upload Icon Set"**
   - Upload the Phosphor icon ZIP file

3. **Use in Elementor**
   - In any Elementor widget with an icon picker
   - Search for "Phosphor" in the icon library dropdown
   - Select any Phosphor icon

## Option 2: Create Custom Icon Pack from Local Files

Since we're already hosting Phosphor icons locally, you can create a custom icon pack:

### Files Needed:
```
phosphor-icons/
├── style.css (located at: assets/css/phosphor-icons.css)
└── fonts/
    ├── phosphor-regular.woff2
    ├── phosphor-regular.woff
    └── phosphor-regular.ttf
```

### Steps:
1. Create a folder named `phosphor-icons`
2. Copy `assets/css/phosphor-icons.css` → `phosphor-icons/style.css`
3. Copy font files from `assets/fonts/phosphor-regular.*` to `phosphor-icons/fonts/`
4. Update paths in `style.css`:
   ```css
   /* Change from: */
   url("../fonts/phosphor-regular.woff2")

   /* To: */
   url("fonts/phosphor-regular.woff2")
   ```
5. ZIP the folder
6. Upload via **Elementor → Custom Icons**

## Verify Icons Are Loaded

The plugin automatically loads Phosphor icons on all frontend pages. To verify:

1. Inspect any page
2. Look for: `<link id="phosphor-icons-css" ...>`
3. Icons use class: `.ph` (e.g., `.ph-heart`, `.ph-star`)

## Icon Usage in Code

If building custom Elementor widgets:
```html
<i class="ph ph-heart"></i>
<i class="ph ph-star"></i>
<i class="ph ph-calendar"></i>
```

## Icon Reference

Browse all available icons:
- https://phosphoricons.com
- All regular weight icons are included
- ~1,200+ icons available

## Notes

- Icons are self-hosted for better performance and GDPR compliance
- Font-display is set to `swap` for optimal loading
- No external CDN calls (unpkg.com is not used)
