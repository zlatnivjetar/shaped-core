<?php
/**
 * Shaped Client Configuration - Must-Use Plugin
 *
 * DEPLOYMENT INSTRUCTIONS:
 * 1. Copy this file to /wp-content/mu-plugins/shaped-client-config.php
 * 2. Customize all values below for your specific client
 * 3. Keep secrets (Stripe, Supabase keys) in wp-config.php
 *
 * This file contains ALL client-specific configuration, keeping the
 * shaped-core plugin clean and identical across all installations.
 *
 * @package Shaped_Core
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// ============================================================================
// CLIENT IDENTIFIER
// ============================================================================

/**
 * Unique client identifier
 * Used for client detection and logging
 */
define('SHAPED_CLIENT', 'preelook');

// ============================================================================
// FEATURE FLAGS
// ============================================================================

/**
 * Enable/disable RoomCloud channel manager integration
 * Set to true if this client uses RoomCloud for inventory management
 */
define('SHAPED_ENABLE_ROOMCLOUD', true);

/**
 * Enable/disable Reviews module
 * Set to true to enable Supabase reviews integration and display
 */
define('SHAPED_ENABLE_REVIEWS', true);

/**
 * Disable WordPress sessions (recommended for performance)
 * Only set to false if you need WordPress session functionality
 */
define('SHAPED_NO_SESSION', true);

/**
 * Enable Price API authentication (optional)
 * Set to true to require API key for /wp-json/shaped/v1/price endpoint
 * If enabled, also define SHAPED_PRICE_API_KEY in wp-config.php
 */
define('SHAPED_PRICE_API_REQUIRE_KEY', false);

// ============================================================================
// BRAND CONFIGURATION
// ============================================================================

/**
 * Returns the complete brand configuration for this client
 * This replaces the need for JSON config files
 *
 * @return array Complete brand configuration
 */
function shaped_get_client_config() {
    return [

        // ====================================================================
        // COMPANY INFORMATION
        // ====================================================================
        'company' => [
            'name'        => 'Preelook Apartments',
            'tagline'     => 'Your seaside escape awaits',
            'location'    => 'Rijeka, Croatia',
            'legalEntity' => 'Vigilo j.d.o.o',
            'vatId'       => '10083013956',
            'jurisdiction' => 'Croatia',
        ],

        // ====================================================================
        // CONTACT INFORMATION
        // ====================================================================
        'contact' => [
            'phone' => '+385 91 613 3609',
            'email' => 'info@preelook.com',
            'address' => [
                'street'      => 'Preluk 4',
                'city'        => 'Rijeka',
                'postalCode'  => '51000',
                'country'     => 'Croatia',
                'countryCode' => 'HR',
            ],
            'mapsUrl' => 'https://maps.app.goo.gl/Zn5MTHb858g4aEUL8',
            'coordinates' => [
                'latitude'  => 45.3438,
                'longitude' => 14.3360,
            ],
        ],

        // ====================================================================
        // EMAIL CONFIGURATION
        // ====================================================================
        'email' => [
            'fromName'   => 'Preelook Apartments',
            'fromEmail'  => 'info@preelook.com',
            'footerText' => 'This is an automated confirmation email.',
            'checkInInstructions' => 'Visit us at the hotel reception upon arrival. We\'ll personally show you to your apartment and ensure you feel right at home.',
            'checkInTime'  => 'from 16:00',
            'checkOutTime' => 'until 11:00',
            'closingMessage' => 'We\'re looking forward to hosting you in beautiful Rijeka!',
            'signature' => 'Warm regards,<br>The Preelook Team',
            'logoUrl'   => 'https://preelook.com/wp-content/uploads/2026/01/preelook-goldblack-1.png',
        ],

        // ====================================================================
        // SCHEMA.ORG / SEO CONFIGURATION
        // ====================================================================
        'schema' => [
            'lodgingType'    => 'LodgingBusiness',
            'priceRange'     => '€€',
            'currency'       => 'EUR',
            'paymentAccepted' => ['Credit Card', 'Debit Card'],
            'checkinTime'    => '16:00',
            'checkoutTime'   => '11:00',
            'petsAllowed'    => true,
            'amenities' => [
                ['name' => 'Free Parking', 'value' => true],
                ['name' => 'Free WiFi', 'value' => true],
                ['name' => 'Air Conditioning', 'value' => true],
                ['name' => 'Kitchen', 'value' => true],
            ],
            'sameAs' => [
                // Add social media URLs here
                // 'https://facebook.com/yourpage',
                // 'https://instagram.com/yourpage',
            ],
        ],

        // ====================================================================
        // BRAND COLORS
        // ====================================================================
        'colors' => [
            'brand' => [
                'primary'      => '#D1AF5D',
                'primaryHover' => '#C39937',
                'secondary'    => '#94772E',
            ],
            'surface' => [
                'page' => '#FFFFFF',
                'alt'  => '#F9FAFB',
                'card' => '#FFFFFF',
            ],
            'border' => [
                'default' => '#E5E7EB',
            ],
            'text' => [
                'primary'  => '#111827',
                'muted'    => '#6B7280',
                'inverse'  => '#FFFFFF',
                'onAccent' => '#FFFFFF',
            ],
            'semantic' => [
                'success' => '#10B981',
                'error'   => '#EF4444',
                'warning' => '#F59E0B',
            ],
            'overlay' => [
                'scrim' => 'rgba(0, 0, 0, 0.5)',
            ],
        ],

        // ====================================================================
        // TYPOGRAPHY
        // ====================================================================
        'type' => [
            'baseSize' => 16,
            'heading' => [
                'family'   => 'DM Sans',
                'fallback' => '-apple-system, BlinkMacSystemFont, \'Segoe UI\', sans-serif',
                'weights'  => [400, 500, 700],
            ],
            'body' => [
                'family'   => 'DM Sans',
                'fallback' => '-apple-system, BlinkMacSystemFont, \'Segoe UI\', sans-serif',
                'weights'  => [400, 500, 700],
            ],
        ],

        // ====================================================================
        // LAYOUT & SPACING
        // ====================================================================
        'radius' => [
            'sm' => 4,
            'md' => 8,
        ],
        'layout' => [
            'maxWidth' => [
                'content' => 1200,
            ],
            'breakpoint' => [
                'mobile'  => 480,
                'tablet'  => 768,
                'desktop' => 1200,
            ],
        ],

        // ====================================================================
        // BOOKING UI CONFIGURATION
        // ====================================================================
        'booking' => [
            'colors' => [
                'price' => [
                    'primary' => '#D1AF5D',
                    'muted'   => '#9CA3AF',
                ],
                'badge' => [
                    'urgency'  => ['bg' => '#FEF2F2', 'text' => '#EF4444'],
                    'discount' => ['bg' => '#10B981', 'text' => '#FFFFFF'],
                ],
            ],
            'card' => [
                'radius' => '8px',
                'shadow' => '0 4px 16px rgba(0, 0, 0, 0.1)',
            ],
        ],

        // ====================================================================
        // INTEGRATIONS
        // ====================================================================
        'integrations' => [

            // Supabase Reviews Configuration
            'supabase' => [
                // Table name in Supabase for reviews (client-specific)
                'reviewsTable' => 'preelook_reviews_all',
                // Enable automatic review syncing
                'autoSync' => true,
            ],

            // Add other integration configs here as needed
            // Example: Google Analytics, Facebook Pixel, etc.
        ],
    ];
}

// ============================================================================
// NOTES FOR DEVELOPERS
// ============================================================================

/*
 * DEPLOYMENT CHECKLIST:
 *
 * 1. Copy this file to /wp-content/mu-plugins/shaped-client-config.php
 *
 * 2. Update SHAPED_CLIENT identifier (line 26)
 *
 * 3. Configure feature flags (lines 33-51)
 *
 * 4. Customize all brand configuration values (lines 62-300)
 *
 * 5. Ensure wp-config.php contains SECRETS ONLY:
 *    - SHAPED_STRIPE_SECRET
 *    - SHAPED_STRIPE_WEBHOOK
 *    - SUPABASE_URL
 *    - SUPABASE_SERVICE_KEY
 *    - SHAPED_SYNC_SECRET (for RoomCloud)
 *    - SHAPED_PRICE_API_KEY (optional, if SHAPED_PRICE_API_REQUIRE_KEY is true)
 *
 * 6. Test that shaped-core plugin loads configuration correctly
 *
 * HELPER FUNCTIONS:
 * - The shaped-core plugin provides helper functions like shaped_brand()
 * - DO NOT redefine these in the MU-plugin (will cause redeclaration errors)
 * - Helper functions: shaped_brand(), shaped_brand_color(), etc.
 * - See: shaped-core/config/brand-helpers.php
 *
 * SECURITY NOTES:
 * - This file is loaded BEFORE WordPress plugins
 * - It is NOT accessible via HTTP (protected by WordPress)
 * - Keep sensitive API keys in wp-config.php, NOT here
 * - Each client's installation should have ONLY their own config
 *
 * MAINTENANCE:
 * - This file should be version-controlled separately per client
 * - shaped-core plugin updates won't touch this file
 * - Easy to backup/restore client-specific settings
 */
