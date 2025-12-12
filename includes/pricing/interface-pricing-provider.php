<?php
/**
 * Pricing Provider Interface
 *
 * Standard interface for all pricing providers (RoomCloud, MotoPress, etc.)
 * Ensures consistent API across different PMS/booking systems.
 *
 * @package Shaped_Core
 * @since 2.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

interface Shaped_Pricing_Provider_Interface
{
    /**
     * Get price quote for a stay
     *
     * Takes a standardized PriceRequest and returns a PriceResult with:
     * - Best available rate (lowest price after discounts)
     * - Alternative room options (if available)
     * - Availability information
     *
     * @param Shaped_Price_Request $request Pricing request with dates, guests, room preference
     * @return Shaped_Price_Result Price result with best rate and alternatives
     * @throws Exception If provider is unavailable or request fails
     */
    public function quote(Shaped_Price_Request $request): Shaped_Price_Result;

    /**
     * Check if provider is properly configured
     *
     * @return bool True if provider can handle requests
     */
    public function is_available(): bool;

    /**
     * Get provider identifier
     *
     * @return string Provider name (e.g., 'roomcloud', 'motopress')
     */
    public function get_name(): string;
}
