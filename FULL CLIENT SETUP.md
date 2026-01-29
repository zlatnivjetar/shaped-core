# Direct booking integration setup

## WordPress Admin
### Motopress: 
- Accommodation types (name, description, excerpt/subheading, amenities, # of instances,  capacity, size, bed type, photo gallery, featured image, SEO) 
- Seasons
- Rates
- Booking Rules (+Settings > checkin/checkout times)

### Other
- Settings (Site name, tagline, admin email)
- WP Mail SMTP (configure client email)
- Verify reviews are visible in admin and on website

## wp-config
- Stripe secret key and webhook secret (test and production)
- wp_home/wp_siteurl set client domain
- disable wp-cron
(Roomcloud and Supabase settings stay as-is, optional Supabase sync secret)

## shaped-client-config
- Feature flags (enable Roomcloud, Reviews, client identifier)
- Client config (Company info, branding, typography)
- Email config
- Schema.org
- Elementor sync (off by default)
- Supabase table name + sync (off by default, on only for retainer clients)

## RoomCloud
### Property dashboard
- Connect Shaped Systems channel
- Map rooms
- Grab rooms and rate IDs
### Website
- Paste rooms, rate and hotel IDs
- Verify inventory syncs

## Stripe Webhook
- Endpoint URL: `https://{client-domain}/wp-json/shaped/v1/stripe-webhook`
- Destination name: Shaped Webhook
- Events: `checkout.session.completed`, 'payment_intent.succeeded', 'payment_intent.payment_failed', 'setup_intent.succeeded'
- Copy webhook secret and secret keys to wp-config.php

## Supabase Dashboard
1. Create client-specific reviews table in the same project
2. Verify service key has access to table

## N8N Workflow
- Clone Reviews Base
- Find client OTA links (4 max)
- Upsert to {client}_reviews table
- WordPress sync to https://{client-domain}/wp-json/shaped/v1/sync-reviews

## Hosting Cron
- Create Hostinger cron for client domain (or configure it on client's hosting platform)

## GA4/GTM
- Create accounts (or login into existing)
- Copy Base Container
- Add GTM head to header, and GTM body to body (in client backend or Elementor custom code)

# Full direct booking system

**All from integration, plus:**

## General
- Prepare "source of truth" for texts (old website text, client provided texts, Booking profile description...)
- Optimize client images and add to Media
- Client logo as Site logo, and in Header/Footer

## Elementor
- Set up/verify globals to reflect client-config
- Setup all copy using the "source of truth" to multiply content and distribute across site
- Setup hero images/images
- Setup correct contact info across site (header, footer, contact, contact form recipent...)

## Plugins
- Basic SEO (RankMath)
- Verify cookies plugin (Compliantz)
- Verify GTranslate works properly

## Legal pages
- Privacy policy, Terms and Conditions, Cookie policy
- Paste HTML from Privacy/Terms in Checkout Modals element