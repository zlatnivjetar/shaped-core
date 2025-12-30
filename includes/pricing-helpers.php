<?php
/**
 * Pricing Helper Functions for Room Cards
 *
 * @package Shaped_Core
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get the default/base price for a room type
 *
 * Attempts to get MPHB's default price, falls back to base price meta
 *
 * @param int $room_type_id Room type post ID
 * @return float Base price
 */
function shaped_get_room_base_price(int $room_type_id): float {
    // Try to get MPHB's default price first
    if (function_exists('MPHB') && class_exists('\MPHB\Entities\RoomType')) {
        $room_type = MPHB()->getRoomTypeRepository()->findById($room_type_id);

        if ($room_type && method_exists($room_type, 'getDefaultPrice')) {
            $price = $room_type->getDefaultPrice();
            if ($price > 0) {
                return (float) $price;
            }
        }
    }

    // Fallback to base price meta
    $base_price = get_post_meta($room_type_id, '_mphb_base_price', true);

    return $base_price ? (float) $base_price : 0.0;
}

/**
 * Format price for display (no decimals for whole numbers, 1 decimal otherwise)
 *
 * @param float $price Price to format
 * @param string $currency Currency code
 * @return string Formatted price
 */
function shaped_format_room_price(float $price, string $currency = 'EUR'): string {
    $symbols = [
        'EUR' => '€',
        'USD' => '$',
        'GBP' => '£',
        'HRK' => 'kn',
    ];

    $symbol = $symbols[$currency] ?? '€';

    // Check if it's a whole number
    if (floor($price) == $price) {
        return $symbol . number_format($price, 0, ',', '.');
    }

    // Has decimals - show 1 decimal place
    return $symbol . number_format($price, 1, ',', '.');
}

/**
 * Get pricing data for a room with discount applied
 *
 * @param int $room_type_id Room type post ID
 * @param string $room_slug Room slug for discount lookup
 * @return array Pricing data array
 */
function shaped_get_room_pricing_data(int $room_type_id, string $room_slug): array {
    $base_price = shaped_get_room_base_price($room_type_id);

    if ($base_price <= 0) {
        return [
            'base_price' => 0,
            'discount_percent' => 0,
            'discount_price' => 0,
            'has_discount' => false,
        ];
    }

    $discount_percent = Shaped_Pricing::get_room_discount($room_slug);
    $discount_price = $base_price * (1 - $discount_percent / 100);

    return [
        'base_price' => $base_price,
        'discount_percent' => $discount_percent,
        'discount_price' => $discount_price,
        'has_discount' => $discount_percent > 0,
    ];
}

/**
 * Render the price section with discount for room cards
 *
 * @param int $room_type_id Room type post ID
 * @param string $room_slug Room slug for discount lookup
 * @param string $currency Currency code
 * @return void
 */
function shaped_render_room_price(int $room_type_id, string $room_slug, string $currency = 'EUR'): void {
    $pricing = shaped_get_room_pricing_data($room_type_id, $room_slug);

    if ($pricing['base_price'] <= 0) {
        return;
    }

    $base_formatted = shaped_format_room_price($pricing['base_price'], $currency);
    $discount_formatted = shaped_format_room_price($pricing['discount_price'], $currency);

    ?>
    <div class="mphb-regular-price" style="margin-bottom:0">
        <strong>Prices start at:</strong>
        <div class="mphb-price-discount-wrapper">
            <?php if ($pricing['has_discount']): ?>
                <span class="mphb-price-original">
                    <span class="mphb-currency"><?php echo esc_html($base_formatted); ?></span>
                </span>
                <span class="mphb-price mphb-price-current">
                    <span class="mphb-currency"><?php echo esc_html($discount_formatted); ?></span>
                </span>
                <span class="mphb-price-period">per night</span>
                <span class="mphb-discount-badge"><?php echo esc_html($pricing['discount_percent']); ?>% off</span>
            <?php else: ?>
                <span class="mphb-price mphb-price-current">
                    <span class="mphb-currency"><?php echo esc_html($base_formatted); ?></span>
                </span>
                <span class="mphb-price-period">per night</span>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * Get count of available rates for a room type
 *
 * Queries MPHB rates (mphb_rate post type) associated with this room type.
 * In MPHB, rates define pricing rules for different scenarios (seasons, length of stay, etc.)
 *
 * @param int $room_type_id Room type post ID
 * @return int Number of rates available (minimum 1)
 */
function shaped_get_room_rates_count(int $room_type_id): int {
    if (!function_exists('MPHB')) {
        return 1;
    }

    // Query rates associated with this room type
    $rates = get_posts([
        'post_type'      => 'mphb_rate',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'meta_query'     => [
            [
                'key'   => 'mphb_room_type_id',
                'value' => $room_type_id,
            ]
        ]
    ]);

    $count = count($rates);

    // Return at least 1 (default rate always exists even if not stored as separate post)
    return $count > 0 ? $count : 1;
}

/**
 * Render the rates available indicator
 *
 * Displays "X rates available" below price, informing guests they can select rates at checkout.
 *
 * @param int $room_type_id Room type post ID
 * @return void
 */
function shaped_render_rates_indicator(int $room_type_id): void {
    $rates_count = shaped_get_room_rates_count($room_type_id);

    // Only show if there are multiple rates
    if ($rates_count <= 1) {
        return;
    }

    $rate_text = $rates_count === 1 ? __('rate available', 'shaped') : __('rates available', 'shaped');
    ?>
    <div class="shaped-rates-indicator">
        <span class="rates-count"><?php echo esc_html($rates_count); ?></span>
        <span class="rates-text"><?php echo esc_html($rate_text); ?></span>
    </div>
    <?php
}

/**
 * Hook into MPHB search results to show rates indicator after price
 *
 * This hooks into the book button action with priority 5 (before the button at priority 10)
 * to inject the rates indicator between price and button.
 */
add_action('mphb_sc_search_results_render_book_button', function() {
    $room_type_id = get_the_ID();
    if ($room_type_id) {
        shaped_render_rates_indicator($room_type_id);
    }
}, 5);
