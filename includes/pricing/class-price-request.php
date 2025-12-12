<?php
/**
 * Price Request Data Structure
 *
 * Standardized input format for pricing queries.
 * Used by all pricing providers (RoomCloud, MotoPress, etc.)
 *
 * @package Shaped_Core
 * @since 2.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Shaped_Price_Request
{
    /**
     * Check-in date
     * @var DateTime
     */
    public $checkin;

    /**
     * Check-out date
     * @var DateTime
     */
    public $checkout;

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
     * Room type slug (optional, null = get best available)
     * @var string|null
     */
    public $room_type_slug;

    /**
     * Create a new price request
     *
     * @param array $params Parameters:
     *   - checkin: DateTime|string (Y-m-d format)
     *   - checkout: DateTime|string (Y-m-d format)
     *   - adults: int (default: 2)
     *   - children: int (default: 0)
     *   - room_type_slug: string|null (default: null)
     *
     * @throws InvalidArgumentException If required params missing or invalid
     */
    public function __construct(array $params)
    {
        // Validate and set check-in date
        if (empty($params['checkin'])) {
            throw new InvalidArgumentException('Check-in date is required');
        }
        $this->checkin = $this->parse_date($params['checkin'], 'checkin');

        // Validate and set check-out date
        if (empty($params['checkout'])) {
            throw new InvalidArgumentException('Check-out date is required');
        }
        $this->checkout = $this->parse_date($params['checkout'], 'checkout');

        // Validate date order
        if ($this->checkin >= $this->checkout) {
            throw new InvalidArgumentException('Check-out must be after check-in');
        }

        // Set guest counts with defaults
        $this->adults = isset($params['adults']) ? max(1, intval($params['adults'])) : 2;
        $this->children = isset($params['children']) ? max(0, intval($params['children'])) : 0;

        // Set room type slug (optional)
        $this->room_type_slug = !empty($params['room_type_slug'])
            ? sanitize_title($params['room_type_slug'])
            : null;
    }

    /**
     * Parse date from string or DateTime
     *
     * @param mixed $date Date value (DateTime object or string)
     * @param string $field_name Field name for error messages
     * @return DateTime
     * @throws InvalidArgumentException If date is invalid
     */
    private function parse_date($date, string $field_name): DateTime
    {
        if ($date instanceof DateTime) {
            return $date;
        }

        if (is_string($date)) {
            try {
                return new DateTime($date);
            } catch (Exception $e) {
                throw new InvalidArgumentException(
                    sprintf('Invalid %s date format: %s', $field_name, $date)
                );
            }
        }

        throw new InvalidArgumentException(
            sprintf('%s must be a DateTime object or date string', $field_name)
        );
    }

    /**
     * Get number of nights
     *
     * @return int
     */
    public function get_nights(): int
    {
        return $this->checkin->diff($this->checkout)->days;
    }

    /**
     * Get total number of guests
     *
     * @return int
     */
    public function get_total_guests(): int
    {
        return $this->adults + $this->children;
    }

    /**
     * Convert to array for API requests
     *
     * @return array
     */
    public function to_array(): array
    {
        return [
            'checkin'        => $this->checkin->format('Y-m-d'),
            'checkout'       => $this->checkout->format('Y-m-d'),
            'nights'         => $this->get_nights(),
            'adults'         => $this->adults,
            'children'       => $this->children,
            'room_type_slug' => $this->room_type_slug,
        ];
    }

    /**
     * Create from URL query parameters
     *
     * @param array $query_params URL query parameters ($_GET)
     * @return Shaped_Price_Request
     * @throws InvalidArgumentException If validation fails
     */
    public static function from_query_params(array $query_params): Shaped_Price_Request
    {
        return new self([
            'checkin'        => $query_params['checkin'] ?? '',
            'checkout'       => $query_params['checkout'] ?? '',
            'adults'         => $query_params['adults'] ?? 2,
            'children'       => $query_params['children'] ?? 0,
            'room_type_slug' => $query_params['room_type'] ?? null,
        ]);
    }

    /**
     * Validate the request meets basic business rules
     *
     * @return array ['valid' => bool, 'errors' => string[]]
     */
    public function validate(): array
    {
        $errors = [];

        // Check-in date validation
        $today = new DateTime('today');
        if ($this->checkin < $today) {
            $errors[] = 'Check-in date cannot be in the past';
        }

        // Maximum future booking (18 months)
        $max_future = (clone $today)->modify('+18 months');
        if ($this->checkin > $max_future) {
            $errors[] = 'Check-in date cannot be more than 18 months in the future';
        }

        // Stay length validation
        $nights = $this->get_nights();
        if ($nights < 1) {
            $errors[] = 'Stay must be at least 1 night';
        }
        if ($nights > 30) {
            $errors[] = 'Stay cannot exceed 30 nights';
        }

        // Guest count validation
        if ($this->adults < 1) {
            $errors[] = 'At least 1 adult is required';
        }
        if ($this->get_total_guests() > 10) {
            $errors[] = 'Total guests cannot exceed 10';
        }

        return [
            'valid'  => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * String representation for debugging
     *
     * @return string
     */
    public function __toString(): string
    {
        return sprintf(
            'PriceRequest(checkin=%s, checkout=%s, adults=%d, children=%d, room=%s)',
            $this->checkin->format('Y-m-d'),
            $this->checkout->format('Y-m-d'),
            $this->adults,
            $this->children,
            $this->room_type_slug ?? 'any'
        );
    }
}
