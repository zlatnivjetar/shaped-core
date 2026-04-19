<?php
/**
 * Shaped Client Configuration Template
 * Copy to /wp-content/mu-plugins/ and customize per client.
 * Secrets (Stripe, Supabase keys) go in wp-config.php.
 *
 * This file is intentionally client agnostic.
 * Fill in the placeholders before using it in production.
 */

if (!defined('ABSPATH')) {
    exit;
}

// =============================================================================
// FEATURE FLAGS
// =============================================================================

define('SHAPED_CLIENT', 'client-slug');         // Unique client identifier
define('SHAPED_ENABLE_ROOMCLOUD', false);       // RoomCloud channel manager
define('SHAPED_ENABLE_REVIEWS', true);         // Supabase reviews module
define('SHAPED_NO_SESSION', true);              // Disable WP sessions (performance)
define('SHAPED_PRICE_API_REQUIRE_KEY', false);  // Require API key for price endpoint

// =============================================================================
// BRAND CONFIGURATION
// =============================================================================

/**
 * Returns the complete brand configuration for this client.
 */
function shaped_get_client_config() {
    return [

        // --- Company ---
        'company' => [
            'name'         => 'Client Name',
            'tagline'      => 'Client tagline',
            'location'     => 'City, Country',
            'legalEntity'  => 'Legal Entity Name',
            'vatId'        => '',
            'jurisdiction' => 'Country',
        ],

        // --- Contact ---
        'contact' => [
            'phone'   => '+000 00 000 0000',
            'email'   => 'hello@example.com',
            'address' => [
                'street'      => 'Street Address 1',
                'city'        => 'City',
                'postalCode'  => '00000',
                'country'     => 'Country',
                'countryCode' => 'HR',
            ],
            'mapsUrl'     => '',
            'coordinates' => [
                'latitude'  => null,
                'longitude' => null,
            ],
        ],

        // --- Colors (client-specific, overrides design-tokens.css defaults) ---
        // Keep these as valid colors even in template form so the site remains usable.
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
                'family'   => 'Inter',
                'fallback' => '-apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
                'weights'  => [400, 500, 600, 700],
            ],
            'body' => [
                'family'   => 'Inter',
                'fallback' => '-apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
                'weights'  => [400, 500, 600, 700],
            ],
        ],

        // --- Layout ---
        'layout' => [
            'radius' => [
                'sm' => 4,
                'md' => 8,
            ],
            'maxWidth' => [
                'content' => 1200,
            ],
            'breakpoint' => [
                'mobile'  => 480,
                'tablet'  => 768,
                'desktop' => 1200,
            ],
        ],

        // --- Email ---
        'email' => [
            'fromName'             => 'Client Name',
            'fromEmail'            => 'hello@example.com',
            'logoUrl'              => site_url('/wp-content/mu-plugins/shaped-client-assets/logos/email-logo.png'),
            'checkInTime'          => 'from 14:00',
            'checkOutTime'         => 'until 10:00',
            'checkInInstructions'  => 'Please contact us before arrival for check-in instructions.',
            'closingMessage'       => 'We look forward to welcoming you.',
            'signature'            => 'Best regards,<br>The Team',
            'footerText'           => 'This is an automated confirmation email.',
        ],

        // --- Schema.org / SEO ---
        // This is the richer config contract expected by the upgraded markup.php.
        // Keep values truthful and aligned with visible page content.
        'schema' => [
            // Core entity configuration
            'roomPostType'     => 'mphb_room_type',
            'bookPageSlug'     => 'book',
            'lodgingType'      => 'LodgingBusiness',
            'defaultUnitType'  => 'Accommodation',
            'propertyName'     => 'Property Name',
            'alternateName'    => null,
            'legalName'        => 'Legal Entity Name',
            'description'      => 'Short factual property description for schema output.',

            // Commercial / stay data
            'priceRange'       => '€€',
            'currency'         => 'EUR',
            'paymentAccepted'  => ['Credit Card', 'Debit Card'],
            'checkinTime'      => '14:00',
            'checkoutTime'     => '10:00',
            'petsAllowed'      => false,
            'numberOfRooms'    => null,
            'bookingUrl'       => null, // Null = fallback to /{bookPageSlug}/
            'enableReserveAction' => true,

            // Identity / trust signals
            'images'           => [],   // Array of absolute image URLs
            'logo'             => null, // Absolute logo URL
            'sameAs'           => [],   // Social / OTA / authoritative profile URLs
            'hasMap'           => '',   // Maps URL
            'starRating'       => null, // e.g. 4 or ['ratingValue' => 4, 'bestRating' => 5, 'author' => 'Official body']
            'vatID'            => '',
            'taxID'            => '',

            // ContactPoint schema nodes (optional)
            'contactPoint' => [
                [
                    'contactType'       => 'customer service',
                    'telephone'         => '+000 00 000 0000',
                    'email'             => 'hello@example.com',
                    'availableLanguage' => ['English'],
                ],
            ],

            // Site search action (optional)
            // Example:
            // 'websiteSearch' => [
            //     'urlTemplate' => home_url('/?s={search_term_string}'),
            //     'queryInput'  => 'required name=search_term_string',
            // ],
            'websiteSearch' => null,

            // Amenities at property level
            'amenities' => [
                ['name' => 'Free WiFi', 'value' => true],
                ['name' => 'Parking', 'value' => true],
            ],

            // Where unit schema should be emitted
            'includeUnitsOnArchive'   => true,
            'includeUnitsOnFrontPage' => false,

            // Per-unit overrides keyed by post slug, post ID, or an entry containing slug/postId.
            // Fill only what is true and known.
            'units' => [
                // 'deluxe-studio-apartment' => [
                //     'name'                  => 'Deluxe Studio Apartment',
                //     'type'                  => 'Accommodation',
                //     'additionalType'        => 'https://schema.org/Apartment',
                //     'description'           => 'Short factual unit description.',
                //     'images'                => [
                //         'https://example.com/path/to/image-1.jpg',
                //         'https://example.com/path/to/image-2.jpg',
                //     ],
                //     'accommodationCategory' => 'Studio apartment',
                //     'amenities'             => [
                //         ['name' => 'Sea View', 'value' => true],
                //         ['name' => 'Kitchen', 'value' => true],
                //     ],
                //     'beds' => [
                //         ['typeOfBed' => 'DoubleBed', 'numberOfBeds' => 1],
                //         ['typeOfBed' => 'SofaBed', 'numberOfBeds' => 1],
                //     ],
                //     'occupancy'     => 4,
                //     // Or use occupancyDetails for more explicit structure:
                //     // 'occupancyDetails' => [
                //     //     ['name' => 'Maximum occupancy', 'maxValue' => 4, 'unitCode' => 'C62'],
                //     // ],
                //     'floorSize'     => 35,
                //     'numberOfRooms' => 1,
                //     'offer' => [
                //         'price'         => 140,
                //         'priceCurrency' => 'EUR',
                //         'unitCode'      => 'DAY',
                //         'availability'  => 'https://schema.org/InStock',
                //     ],
                // ],
            ],
        ],

        // --- Elementor ---
        // SSH into public_html to sync globals: wp eval "do_action('shaped/elementor/force_sync');"
        'elementor' => [
            'sync_colors'       => false,
            'landing_header_id' => 0,
            'landing_footer_id' => 0,
        ],

        // --- Integrations ---
        'integrations' => [
            'supabase' => [
                'reviewsTable' => 'client_reviews',
                'autoSync'     => false,
            ],
        ],

    ];
}

// =============================================================================
// PERFORMANCE
// =============================================================================

// Disable sessions for Price API endpoint.
add_action('muplugins_loaded', function () {
    if (strpos($_SERVER['REQUEST_URI'] ?? '', '/wp-json/shaped/v1/price') === false) {
        return;
    }
    if (!defined('SHAPED_NO_SESSION')) {
        define('SHAPED_NO_SESSION', true);
    }
}, 1);
