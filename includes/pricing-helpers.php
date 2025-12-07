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
