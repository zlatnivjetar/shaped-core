<?php
/**
 * Shaped Client Configuration
 * Copy to /wp-content/mu-plugins/ and customize for each client.
 * Secrets (Stripe, Supabase keys) go in wp-config.php.
 */

if (!defined('ABSPATH')) {
    exit;
}

// =============================================================================
// FEATURE FLAGS
// =============================================================================

define('SHAPED_CLIENT', 'shaped');              // Unique client identifier
define('SHAPED_ENABLE_ROOMCLOUD', false);       // RoomCloud channel manager
define('SHAPED_ENABLE_REVIEWS', true);          // Supabase reviews module
define('SHAPED_NO_SESSION', true);              // Disable WP sessions (performance)
define('SHAPED_PRICE_API_REQUIRE_KEY', false);  // Require API key for price endpoint

// =============================================================================
// BRAND CONFIGURATION
// =============================================================================

/**
 * Returns the complete brand configuration for this client
 * @return array Complete brand configuration
 */
function shaped_get_client_config() {
    return [

        // --- Company ---
        'company' => [
            'name'         => 'Preelook Apartments',
            'tagline'      => 'Your seaside escape awaits',
            'location'     => 'Rijeka, Croatia',
            'legalEntity'  => 'Vigilo j.d.o.o',
            'vatId'        => '10083013956',
            'jurisdiction' => 'Croatia',
        ],

        // --- Contact ---
        'contact' => [
            'phone'   => '+385 91 613 3609',
            'email'   => 'client@email.com',
            'address' => [
                'street'      => 'Preluk 4',
                'city'        => 'Rijeka',
                'postalCode'  => '51000',
                'country'     => 'Croatia',
                'countryCode' => 'HR',
            ],
            'mapsUrl'     => 'https://maps.app.goo.gl/Zn5MTHb858g4aEUL8',
            'coordinates' => [
                'latitude'  => 45.3438,
                'longitude' => 14.3360,
            ],
        ],

        // --- Colors (client-specific, overrides design-tokens.css defaults) ---
        'colors' => [
            'brand' => [
                'primary'      => '#E2BD27',
                'primaryHover' => '#B7991F',
            ],
            'surface' => [
                'page'      => '#FBFBF9',       // Main background
                'highlight' => '#fffbf0',       // Forms, cards
                'pageDark'  => '#2b2a26',       // Dark sections
                'pageBlack' => '#0b0b09',       // Inverse anchor
            ],
            'text' => [
                'primary'      => '#0B0B09',
                'muted'        => '#51504D',
                'inverse'      => '#FFFFFF',
                'inverseMuted' => 'rgba(255, 255, 255, 0.72)',
                'onPrimary'    => '#0B0B09',    // Text on brand primary
            ],
        ],

        // --- Typography ---
        'type' => [
            'baseSize' => 16,
            'heading'  => [
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

        // --- Layout ---
        'layout' => [
            'radius' => [
                'sm' => 4,
                'md' => 8,
            ],
            'maxWidth' => [
                'content' => 1280,
            ],
            'breakpoint' => [
                'mobile'  => 480,
                'tablet'  => 768,
                'desktop' => 1280,
            ],
        ],

        // --- Email ---
        'email' => [
            'fromName'             => 'Preelook Apartments',
            'fromEmail'            => 'client@email.com',
            'logoUrl'              => 'https://powderblue-falcon-973302.hostingersite.com/wp-content/uploads/2026/01/preelook-goldblack-1.png',
            'checkInTime'          => 'from 16:00',
            'checkOutTime'         => 'until 11:00',
            'checkInInstructions'  => 'Visit us at the hotel reception upon arrival. We\'ll personally show you to your apartment and ensure you feel right at home.',
            'closingMessage'       => 'We\'re looking forward to hosting you in beautiful Rijeka!',
            'signature'            => 'Warm regards,<br>The Preelook Team',
            'footerText'           => 'This is an automated confirmation email.',
        ],

        // --- Schema.org / SEO ---
        'schema' => [
            'lodgingType'     => 'LodgingBusiness',
            'priceRange'      => '€€',
            'currency'        => 'EUR',
            'paymentAccepted' => ['Credit Card', 'Debit Card'],
            'checkinTime'     => '16:00',
            'checkoutTime'    => '11:00',
            'petsAllowed'     => true,
            'amenities'       => [
                ['name' => 'Free Parking', 'value' => true],
                ['name' => 'Free WiFi', 'value' => true],
                ['name' => 'Air Conditioning', 'value' => true],
                ['name' => 'Kitchen', 'value' => true],
            ],
            'sameAs' => [],  // Social media URLs
        ],

        // --- Elementor ---
        // SSH to run into public_html to sync globals: wp eval "do_action('shaped/elementor/force_sync');"
        'elementor' => [
            'sync_colors' => false,  // Sync brand colors to Elementor globals (enable for new builds only)
        ],

        // --- Integrations ---
        'integrations' => [
            'supabase' => [
                'reviewsTable' => 'preelook_reviews',
                // Enable automatic review syncing
                'autoSync' => false,
            ],
        ],

    ];
}

// =============================================================================
// PERFORMANCE
// =============================================================================

// Disable sessions for Price API endpoint
add_action('muplugins_loaded', function () {
    if (strpos($_SERVER['REQUEST_URI'] ?? '', '/wp-json/shaped/v1/price') === false) {
        return;
    }
    if (!defined('SHAPED_NO_SESSION')) {
        define('SHAPED_NO_SESSION', true);
    }
}, 1);
