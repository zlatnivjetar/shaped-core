<?php
/**
 * Pricing Service Initialization
 *
 * Bootstrap file for the pricing service.
 * Initializes the service and makes it globally accessible.
 *
 * @package Shaped_Core
 * @since 2.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Load Pricing classes
 */
require_once __DIR__ . '/class-price-request.php';
require_once __DIR__ . '/class-price-result.php';
require_once __DIR__ . '/interface-pricing-provider.php';
require_once __DIR__ . '/class-roomcloud-pricing-provider.php';
require_once __DIR__ . '/class-motopress-pricing-provider.php';
require_once __DIR__ . '/class-shaped-pricing-service.php';
require_once __DIR__ . '/class-rest-api.php';
require_once __DIR__ . '/class-official-prices-page.php';

/**
 * Global pricing service instance
 */
global $shaped_pricing_service;
$shaped_pricing_service = null;

/**
 * Initialize the pricing service
 *
 * Creates the appropriate provider based on site configuration
 * and initializes the unified pricing service.
 *
 * @param array $config Service configuration (optional)
 * @return Shaped_Pricing_Service|null Pricing service instance or null if unavailable
 */
function shaped_init_pricing_service(array $config = []): ?Shaped_Pricing_Service
{
    global $shaped_pricing_service;

    // Return existing instance if already initialized
    if ($shaped_pricing_service !== null) {
        return $shaped_pricing_service;
    }

    // Determine which provider to use
    $provider = null;

    // Priority 1: RoomCloud (if enabled and available)
    if (defined('SHAPED_ENABLE_ROOMCLOUD') &&
        SHAPED_ENABLE_ROOMCLOUD &&
        class_exists('Shaped_RC_Availability_Manager')) {

        $provider = new Shaped_RoomCloud_Pricing_Provider([
            'property_name' => get_bloginfo('name'),
            'currency'      => $config['currency'] ?? 'EUR',
        ]);

        if (!$provider->is_available()) {
            $provider = null;
        }
    }

    // Priority 2: MotoPress-only (fallback)
    if ($provider === null && function_exists('MPHB')) {
        $provider = new Shaped_MotoPress_Pricing_Provider([
            'property_name' => get_bloginfo('name'),
            'currency'      => $config['currency'] ?? 'EUR',
        ]);

        if (!$provider->is_available()) {
            $provider = null;
        }
    }

    // No provider available
    if ($provider === null) {
        return null;
    }

    // Create service instance
    $shaped_pricing_service = new Shaped_Pricing_Service($provider, $config);

    return $shaped_pricing_service;
}

/**
 * Get the global pricing service instance
 *
 * @return Shaped_Pricing_Service|null Service instance or null if not initialized
 */
function shaped_pricing_service(): ?Shaped_Pricing_Service
{
    global $shaped_pricing_service;

    // Auto-initialize if not yet done
    if ($shaped_pricing_service === null) {
        shaped_init_pricing_service();
    }

    return $shaped_pricing_service;
}

/**
 * Quick helper: Get a price quote
 *
 * Convenience function for getting pricing without manually accessing the service
 *
 * @param array $params Request parameters (checkin, checkout, adults, children, room_type_slug)
 * @return Shaped_Price_Result|null Price result or null if service unavailable
 * @throws Exception If request is invalid
 */
function shaped_get_price_quote(array $params): ?Shaped_Price_Result
{
    $service = shaped_pricing_service();

    if ($service === null || !$service->is_ready()) {
        return null;
    }

    return $service->quote($params);
}

/**
 * Initialize REST API endpoints
 *
 * Registers /wp-json/shaped/v1/price and /wp-json/shaped/v1/price-html
 */
Shaped_Pricing_Rest_Api::init();

/**
 * Initialize Official Prices Page
 *
 * Registers shortcode and page-specific schema
 */
Shaped_Official_Prices_Page::init();
