<?php
/**
 * Price Result Data Structure
 *
 * Standardized output format for pricing queries.
 * Used by all pricing providers (RoomCloud, MotoPress, etc.)
 *
 * @package Shaped_Core
 * @since 2.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Shaped_Price_Result
{
    /**
     * Property name
     * @var string
     */
    public $property_name;

    /**
     * Currency code (ISO 4217)
     * @var string
     */
    public $currency;

    /**
     * Check-in date (Y-m-d)
     * @var string
     */
    public $checkin;

    /**
     * Check-out date (Y-m-d)
     * @var string
     */
    public $checkout;

    /**
     * Number of nights
     * @var int
     */
    public $nights;

    /**
     * Number of adults
     * @var int
     */
    public $adults;

    /**
     * Number of children
     * @var int
     */
    public $children;

    /**
     * Best available rate (lowest discounted price)
     * @var array {
     *   @type string $room_type_slug Room type identifier
     *   @type string $room_type_name Human-readable room name
     *   @type string $board Board type (e.g., 'Room Only', 'Breakfast Included')
     *   @type bool $refundable Cancellation policy
     *   @type float $total Total price for entire stay (with discounts)
     *   @type float $per_night Average price per night
     *   @type bool $tax_included Whether taxes are included in price
     *   @type array $discounts_applied List of applied discounts
     * }
     */
    public $best_rate;

    /**
     * Other available room options
     * @var array[]
     */
    public $other_options;

    /**
     * Data source identifier (e.g., 'roomcloud', 'motopress')
     * @var string
     */
    public $source;

    /**
     * Platform provider identifier (always 'shaped')
     * @var string
     */
    public $provider;

    /**
     * Timestamp when this result was generated (ISO8601)
     * @var string
     */
    public $generated_at;

    /**
     * Create a new price result
     *
     * @param array $data Result data
     * @throws InvalidArgumentException If required fields are missing
     */
    public function __construct(array $data)
    {
        // Required fields
        $required = [
            'property_name', 'currency', 'checkin', 'checkout',
            'nights', 'adults', 'children', 'best_rate', 'source'
        ];

        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new InvalidArgumentException("Missing required field: {$field}");
            }
            $this->$field = $data[$field];
        }

        // Optional fields with defaults
        $this->other_options = $data['other_options'] ?? [];
        $this->provider = $data['provider'] ?? 'shaped';
        $this->generated_at = $data['generated_at'] ?? gmdate('c');

        // Validate best_rate structure
        $this->validate_rate($this->best_rate, 'best_rate');
    }

    /**
     * Validate rate structure
     *
     * @param array $rate Rate data
     * @param string $context Context for error messages
     * @throws InvalidArgumentException If rate structure is invalid
     */
    private function validate_rate(array $rate, string $context): void
    {
        $required_rate_fields = [
            'room_type_slug', 'room_type_name', 'board',
            'refundable', 'total', 'per_night', 'tax_included'
        ];

        foreach ($required_rate_fields as $field) {
            if (!isset($rate[$field])) {
                throw new InvalidArgumentException(
                    sprintf('Missing required field in %s: %s', $context, $field)
                );
            }
        }

        // Validate numeric fields
        if (!is_numeric($rate['total']) || $rate['total'] < 0) {
            throw new InvalidArgumentException("{$context}.total must be a positive number");
        }
        if (!is_numeric($rate['per_night']) || $rate['per_night'] < 0) {
            throw new InvalidArgumentException("{$context}.per_night must be a positive number");
        }

        // Validate boolean fields
        if (!is_bool($rate['refundable'])) {
            throw new InvalidArgumentException("{$context}.refundable must be boolean");
        }
        if (!is_bool($rate['tax_included'])) {
            throw new InvalidArgumentException("{$context}.tax_included must be boolean");
        }

        // Validate discounts_applied (must be array if present)
        if (isset($rate['discounts_applied']) && !is_array($rate['discounts_applied'])) {
            throw new InvalidArgumentException("{$context}.discounts_applied must be an array");
        }
    }

    /**
     * Convert to array for JSON/API responses
     *
     * @return array
     */
    public function to_array(): array
    {
        return [
            'property_name' => $this->property_name,
            'currency'      => $this->currency,
            'checkin'       => $this->checkin,
            'checkout'      => $this->checkout,
            'nights'        => $this->nights,
            'adults'        => $this->adults,
            'children'      => $this->children,
            'best_rate'     => $this->best_rate,
            'other_options' => $this->other_options,
            'source'        => $this->source,
            'provider'      => $this->provider,
            'generated_at'  => $this->generated_at,
        ];
    }

    /**
     * Convert to JSON
     *
     * @param int $flags JSON encoding flags
     * @return string
     */
    public function to_json(int $flags = 0): string
    {
        return json_encode($this->to_array(), $flags);
    }

    /**
     * Generate human-readable HTML description
     *
     * Example output:
     * "For 2 adults from 2025-12-19 to 2025-12-20, the best direct price at
     *  Preelook Apartments is €180.00 (€90.00 per night) for Deluxe Studio
     *  Apartment, Room Only. Taxes included. 10% direct booking discount applied."
     *
     * @return string HTML string
     */
    public function to_html(): string
    {
        $rate = $this->best_rate;

        // Guest description
        $guests = $this->adults === 1 ? '1 adult' : "{$this->adults} adults";
        if ($this->children > 0) {
            $guests .= $this->children === 1 ? ' and 1 child' : " and {$this->children} children";
        }

        // Format prices
        $total_formatted = $this->format_price($rate['total']);
        $per_night_formatted = $this->format_price($rate['per_night']);

        // Build sentence
        $html = sprintf(
            'For %s from %s to %s, the best direct price at <strong>%s</strong> is <strong>%s</strong> (%s per night) for <strong>%s</strong>, %s.',
            $guests,
            $this->checkin,
            $this->checkout,
            esc_html($this->property_name),
            esc_html($total_formatted),
            esc_html($per_night_formatted),
            esc_html($rate['room_type_name']),
            esc_html($rate['board'])
        );

        // Add tax info
        if ($rate['tax_included']) {
            $html .= ' Taxes included.';
        } else {
            $html .= ' Taxes not included.';
        }

        // Add discount info
        if (!empty($rate['discounts_applied'])) {
            $discounts = implode(', ', $rate['discounts_applied']);
            $html .= ' ' . esc_html($discounts) . '.';
        }

        // Add cancellation policy
        if ($rate['refundable']) {
            $html .= ' Free cancellation available.';
        } else {
            $html .= ' Non-refundable.';
        }

        return $html;
    }

    /**
     * Format price with currency symbol
     *
     * @param float $amount Amount to format
     * @return string Formatted price
     */
    private function format_price(float $amount): string
    {
        $symbols = [
            'EUR' => '€',
            'USD' => '$',
            'GBP' => '£',
            'HRK' => 'kn',
        ];

        $symbol = $symbols[$this->currency] ?? $this->currency . ' ';

        // Format with 2 decimals
        return $symbol . number_format($amount, 2, '.', ',');
    }

    /**
     * Get a summary for logging/debugging
     *
     * @return string
     */
    public function get_summary(): string
    {
        return sprintf(
            '%s for %d nights: %s (room: %s, source: %s)',
            $this->format_price($this->best_rate['total']),
            $this->nights,
            "{$this->checkin} to {$this->checkout}",
            $this->best_rate['room_type_slug'],
            $this->source
        );
    }

    /**
     * Check if result has multiple room options
     *
     * @return bool
     */
    public function has_alternatives(): bool
    {
        return !empty($this->other_options);
    }

    /**
     * Get total number of room options (best + alternatives)
     *
     * @return int
     */
    public function get_option_count(): int
    {
        return 1 + count($this->other_options);
    }

    /**
     * Create from provider-specific data (factory method)
     *
     * Providers can use this to ensure consistent structure
     *
     * @param array $data Raw provider data
     * @return Shaped_Price_Result
     */
    public static function create(array $data): Shaped_Price_Result
    {
        return new self($data);
    }

    /**
     * String representation for debugging
     *
     * @return string
     */
    public function __toString(): string
    {
        return sprintf(
            'PriceResult(%s, %s, %d nights, best: %s @ %s/night)',
            $this->checkin,
            $this->checkout,
            $this->nights,
            $this->format_price($this->best_rate['total']),
            $this->format_price($this->best_rate['per_night'])
        );
    }
}
