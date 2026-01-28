# Per-Client Setup Checklist

## wp-config.php
2. Stripe secret key and webhook secret (test and production)
3. Supabase table name (use `clients/{client}/supabase-instructions.txt` for setup)
4. RoomCloud sync secret (if enabled)

## /wp-content/mu-plugins/shaped-client-config.php
2. Feature flags (RoomCloud, Reviews, Sessions, Price API)
3. Company info (name, tagline, location, legal entity, VAT, jurisdiction)
4. Contact (phone, email, address, coordinates, maps URL)
5. Email settings (from name/email, check-in/out times, instructions, signature, logo URL)
6. Schema.org (lodging type, price range, currency, amenities, social URLs)
7. Brand colors (primary, hover, secondary, surface, border, text, semantic)
8. Typography (families, weights, base size)
9. Layout (radius, max width, breakpoints)
10. Booking UI (price colors, badges, card styles)
11. Supabase reviews table name and auto-sync setting

## Stripe Dashboard
1. Endpoint URL: `https://{client-domain}/wp-json/shaped/v1/stripe-webhook`
2. Destination name: Shaped Webhook
2. Events: `checkout.session.completed`, 'payment_intent.succeeded', 'payment_intent.payment_failed', 'setup_intent.succeeded'
3. Copy webhook secret and secret keys to wp-config.php

## Supabase Dashboard
1. Create client-specific reviews table (see `clients/{client}/supabase-instructions.txt`)
2. Verify service key has access to table

## Hostinger Cron
1. Create Hostinger cron for client domain (or configure it on client's hosting platform)