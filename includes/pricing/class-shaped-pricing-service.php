<?php
/**
 * Shaped Pricing Service
 *
 * Single entry point for all pricing operations across the plugin.
 * Wraps provider implementations and enforces validation/guardrails.
 *
 * @package Shaped_Core
 * @since 2.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Shaped_Pricing_Service
{
    /**
     * Active pricing provider
     * @var Shaped_Pricing_Provider_Interface
     */
    private $provider;

    /**
     * Service configuration
     * @var array
     */
    private $config;

    /**
     * Constructor
     *
     * @param Shaped_Pricing_Provider_Interface $provider Active pricing provider
     * @param array $config Service configuration:
     *   - max_nights: int (default: 30)
     *   - max_future_months: int (default: 18)
     *   - max_guests: int (default: 10)
     *   - cache_ttl: int (default: 300) Cache TTL in seconds
     */
    public function __construct(Shaped_Pricing_Provider_Interface $provider, array $config = [])
    {
        $this->provider = $provider;
        $this->config = wp_parse_args($config, [
            'max_nights'        => 30,
            'max_future_months' => 18,
            'max_guests'        => 10,
            'cache_ttl'         => 300, // 5 minutes
        ]);
    }

    /**
     * Get price quote for a stay
     *
     * Main entry point for pricing requests. Validates input and delegates to provider.
     *
     * @param array|Shaped_Price_Request $request Request params or PriceRequest object
     * @return Shaped_Price_Result Price result
     * @throws InvalidArgumentException If request is invalid
     * @throws Exception If pricing fails
     */
    public function quote($request): Shaped_Price_Result
    {
        // Convert array to PriceRequest if needed
        if (is_array($request)) {
            try {
                $request = new Shaped_Price_Request($request);
            } catch (Exception $e) {
                throw new InvalidArgumentException(
                    'Invalid pricing request: ' . $e->getMessage()
                );
            }
        }

        if (!($request instanceof Shaped_Price_Request)) {
            throw new InvalidArgumentException(
                'Request must be array or Shaped_Price_Request object'
            );
        }

        // Apply service-level validation (stricter than provider)
        $this->validate_request($request);

        // Check cache first
        $cache_key = $this->get_cache_key($request);
        $cached = $this->get_cached_result($cache_key);

        if ($cached !== null) {
            return $cached;
        }

        // Delegate to provider
        $result = $this->provider->quote($request);

        // Cache result
        $this->cache_result($cache_key, $result);

        return $result;
    }

    /**
     * Validate pricing request against service rules
     *
     * @param Shaped_Price_Request $request Request to validate
     * @throws InvalidArgumentException If validation fails
     */
    private function validate_request(Shaped_Price_Request $request): void
    {
        // Run basic validation first
        $validation = $request->validate();
        if (!$validation['valid']) {
            throw new InvalidArgumentException(
                'Invalid request: ' . implode(', ', $validation['errors'])
            );
        }

        // Enforce service-level limits
        $nights = $request->get_nights();
        if ($nights > $this->config['max_nights']) {
            throw new InvalidArgumentException(
                sprintf('Stay cannot exceed %d nights', $this->config['max_nights'])
            );
        }

        $guests = $request->get_total_guests();
        if ($guests > $this->config['max_guests']) {
            throw new InvalidArgumentException(
                sprintf('Total guests cannot exceed %d', $this->config['max_guests'])
            );
        }

        // Check future booking limit
        $today = new DateTime('today');
        $max_future = (clone $today)->modify('+' . $this->config['max_future_months'] . ' months');

        if ($request->checkin > $max_future) {
            throw new InvalidArgumentException(
                sprintf(
                    'Check-in date cannot be more than %d months in the future',
                    $this->config['max_future_months']
                )
            );
        }

        // Ensure check-in is not in the past
        if ($request->checkin < $today) {
            throw new InvalidArgumentException('Check-in date cannot be in the past');
        }
    }

    /**
     * Get active pricing provider
     *
     * @return Shaped_Pricing_Provider_Interface
     */
    public function get_provider(): Shaped_Pricing_Provider_Interface
    {
        return $this->provider;
    }

    /**
     * Get service configuration
     *
     * @return array
     */
    public function get_config(): array
    {
        return $this->config;
    }

    /**
     * Check if service is ready to handle requests
     *
     * @return bool True if provider is available
     */
    public function is_ready(): bool
    {
        return $this->provider->is_available();
    }

    /**
     * Generate cache key for request
     *
     * @param Shaped_Price_Request $request Request object
     * @return string Cache key
     */
    private function get_cache_key(Shaped_Price_Request $request): string
    {
        $parts = [
            'shaped_pricing',
            $this->provider->get_name(),
            $request->checkin->format('Ymd'),
            $request->checkout->format('Ymd'),
            $request->adults,
            $request->children,
            $request->room_type_slug ?? 'any',
        ];

        return implode('_', $parts);
    }

    /**
     * Get cached pricing result
     *
     * @param string $cache_key Cache key
     * @return Shaped_Price_Result|null Cached result or null if not found/expired
     */
    private function get_cached_result(string $cache_key): ?Shaped_Price_Result
    {
        $cached = get_transient($cache_key);

        if ($cached === false) {
            return null;
        }

        // Reconstruct PriceResult from cached array
        try {
            return new Shaped_Price_Result($cached);
        } catch (Exception $e) {
            // Invalid cache data - delete it
            delete_transient($cache_key);
            return null;
        }
    }

    /**
     * Cache pricing result
     *
     * @param string $cache_key Cache key
     * @param Shaped_Price_Result $result Result to cache
     */
    private function cache_result(string $cache_key, Shaped_Price_Result $result): void
    {
        set_transient(
            $cache_key,
            $result->to_array(),
            $this->config['cache_ttl']
        );
    }

    /**
     * Clear all pricing cache
     *
     * Useful when prices or discounts are updated
     */
    public function clear_cache(): void
    {
        global $wpdb;

        // Delete all pricing transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_shaped_pricing_%'
             OR option_name LIKE '_transient_timeout_shaped_pricing_%'"
        );
    }

    /**
     * Get pricing service info for debugging
     *
     * @return array Service information
     */
    public function get_info(): array
    {
        return [
            'provider'      => $this->provider->get_name(),
            'provider_available' => $this->provider->is_available(),
            'service_ready' => $this->is_ready(),
            'config'        => $this->config,
        ];
    }
}
