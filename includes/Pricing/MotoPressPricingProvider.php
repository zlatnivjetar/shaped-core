<?php
/**
 * MotoPress Pricing Provider
 *
 * Pricing provider for sites using only MotoPress (no RoomCloud integration).
 * Uses MotoPress base prices and Shaped direct booking discounts.
 *
 * @package Shaped_Core
 * @since 2.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Shaped_MotoPress_Pricing_Provider implements Shaped_Pricing_Provider_Interface
{
    /**
     * Property configuration
     */
    private $property_name;
    private $currency;

    /**
     * Constructor
     *
     * @param array $config Configuration options
     */
    public function __construct(array $config = [])
    {
        $this->property_name = $config['property_name'] ?? get_bloginfo('name');
        $this->currency = $config['currency'] ?? 'EUR';
    }

    /**
     * Get price quote using MotoPress pricing
     *
     * @param Shaped_Price_Request $request Pricing request
     * @return Shaped_Price_Result Price result
     * @throws Exception If no rooms available or pricing fails
     */
    public function quote(Shaped_Price_Request $request): Shaped_Price_Result
    {
        if (!$this->is_available()) {
            throw new Exception('MotoPress Hotel Booking plugin is not available');
        }

        // Validate the request
        $validation = $request->validate();
        if (!$validation['valid']) {
            throw new InvalidArgumentException(
                'Invalid pricing request: ' . implode(', ', $validation['errors'])
            );
        }

        // Get all published room types
        $room_types = $this->get_all_room_types();

        if (empty($room_types)) {
            throw new Exception('No room types configured in MotoPress');
        }

        // If specific room requested, filter to that room
        if ($request->room_type_slug !== null) {
            $room_types = array_filter($room_types, function($room) use ($request) {
                return $room['slug'] === $request->room_type_slug;
            });

            if (empty($room_types)) {
                throw new Exception(
                    sprintf('Room type "%s" not found', $request->room_type_slug)
                );
            }
        }

        // Build rates for all available rooms
        $room_rates = [];

        foreach ($room_types as $room) {
            try {
                $rate = $this->build_rate_for_room($room['slug'], $room['id'], $request);
                $room_rates[] = $rate;
            } catch (Exception $e) {
                // Skip rooms that can't be priced
                error_log(sprintf(
                    'MotoPress Provider: Skipped room %s - %s',
                    $room['slug'],
                    $e->getMessage()
                ));
                continue;
            }
        }

        if (empty($room_rates)) {
            throw new Exception('No rooms available for pricing');
        }

        // Sort by price (cheapest first)
        usort($room_rates, function($a, $b) {
            return $a['total'] <=> $b['total'];
        });

        // Best rate = cheapest
        $best_rate = array_shift($room_rates);

        // Other options = remaining rooms
        $other_options = $room_rates;

        return $this->build_result($request, $best_rate, $other_options);
    }

    /**
     * Get all published room types
     *
     * @return array Array of room types with id, slug, name
     */
    private function get_all_room_types(): array
    {
        $posts = get_posts([
            'post_type'      => 'mphb_room_type',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
        ]);

        $room_types = [];
        foreach ($posts as $post) {
            $room_types[] = [
                'id'   => $post->ID,
                'slug' => $post->post_name,
                'name' => $post->post_title,
            ];
        }

        return $room_types;
    }

    /**
     * Build rate data for a specific room
     *
     * @param string $room_slug Room type slug
     * @param int $room_id Room type post ID
     * @param Shaped_Price_Request $request Pricing request
     * @return array Rate data
     * @throws Exception If pricing fails
     */
    private function build_rate_for_room(string $room_slug, int $room_id, Shaped_Price_Request $request): array
    {
        // Get base price from MotoPress
        $base_price_per_night = $this->get_base_price($room_id);

        if ($base_price_per_night <= 0) {
            throw new Exception("No pricing configured for room: {$room_slug}");
        }

        // Calculate total base price for stay
        $nights = $request->get_nights();
        $base_total = $base_price_per_night * $nights;

        // Apply direct booking discount
        $discount_percent = Shaped_Pricing::get_room_discount($room_slug);
        $discount_multiplier = (100 - $discount_percent) / 100;
        $final_total = round($base_total * $discount_multiplier, 2);
        $final_per_night = round($final_total / $nights, 2);

        // Build discounts_applied array
        $discounts_applied = [];
        if ($discount_percent > 0) {
            $discounts_applied[] = sprintf('%d%% direct booking discount', $discount_percent);
        }

        // Get room name
        $room_name = get_the_title($room_id);

        // Determine board type (default: Room Only)
        $board = $this->get_board_type($room_id);

        // Determine refundability (based on payment mode)
        $refundable = $this->is_refundable($request);

        return [
            'room_type_slug'     => $room_slug,
            'room_type_name'     => $room_name,
            'board'              => $board,
            'refundable'         => $refundable,
            'total'              => $final_total,
            'per_night'          => $final_per_night,
            'tax_included'       => true,
            'discounts_applied'  => $discounts_applied,
        ];
    }

    /**
     * Get base price for room type
     *
     * Uses existing shaped_get_room_base_price() helper
     *
     * @param int $room_type_id Room type post ID
     * @return float Base price per night
     */
    private function get_base_price(int $room_type_id): float
    {
        // Use existing helper function
        if (function_exists('shaped_get_room_base_price')) {
            return shaped_get_room_base_price($room_type_id);
        }

        // Fallback: direct MotoPress query
        if (function_exists('MPHB')) {
            $room_type = MPHB()->getRoomTypeRepository()->findById($room_type_id);
            if ($room_type && method_exists($room_type, 'getDefaultPrice')) {
                $price = $room_type->getDefaultPrice();
                if ($price > 0) {
                    return (float) $price;
                }
            }
        }

        // Last resort: post meta
        $base = get_post_meta($room_type_id, '_mphb_base_price', true);
        return $base ? (float) $base : 0.0;
    }

    /**
     * Get board type for room
     *
     * @param int $room_type_id Room type post ID
     * @return string Board type
     */
    private function get_board_type(int $room_type_id): string
    {
        // Check for board type meta
        $board = get_post_meta($room_type_id, '_shaped_board_type', true);

        if (!empty($board)) {
            return $board;
        }

        // Default
        return 'Room Only';
    }

    /**
     * Determine if booking is refundable based on payment mode and dates
     *
     * @param Shaped_Price_Request $request Pricing request
     * @return bool True if refundable
     */
    private function is_refundable(Shaped_Price_Request $request): bool
    {
        $payment_mode = Shaped_Pricing::get_payment_mode();

        // Scheduled mode: refundable up to 7 days before checkin
        if ($payment_mode === 'scheduled') {
            $days_until_checkin = $this->get_days_until($request->checkin);
            return $days_until_checkin >= 7;
        }

        // Deposit mode: typically non-refundable
        if ($payment_mode === 'deposit') {
            return false;
        }

        return false;
    }

    /**
     * Get days from today until a date
     *
     * @param DateTime $date Target date
     * @return int Days until date
     */
    private function get_days_until(DateTime $date): int
    {
        $today = new DateTime('today');
        $diff = $today->diff($date);
        return $diff->days;
    }

    /**
     * Build final PriceResult
     *
     * @param Shaped_Price_Request $request Original request
     * @param array $best_rate Best rate data
     * @param array $other_options Alternative rates
     * @return Shaped_Price_Result Price result
     */
    private function build_result(
        Shaped_Price_Request $request,
        array $best_rate,
        array $other_options
    ): Shaped_Price_Result {
        return new Shaped_Price_Result([
            'property_name'  => $this->property_name,
            'currency'       => $this->currency,
            'checkin'        => $request->checkin->format('Y-m-d'),
            'checkout'       => $request->checkout->format('Y-m-d'),
            'nights'         => $request->get_nights(),
            'adults'         => $request->adults,
            'children'       => $request->children,
            'best_rate'      => $best_rate,
            'other_options'  => $other_options,
            'source'         => 'motopress',
            'generated_at'   => gmdate('c'),
        ]);
    }

    /**
     * Check if MotoPress is available
     *
     * @return bool True if MotoPress is installed
     */
    public function is_available(): bool
    {
        return function_exists('MPHB');
    }

    /**
     * Get provider name
     *
     * @return string Provider identifier
     */
    public function get_name(): string
    {
        return 'motopress';
    }
}
