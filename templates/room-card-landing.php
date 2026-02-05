<?php
/**
 * Room Card Template - Landing Page
 *
 * Compact card for landing pages: photo, title, 3 priority amenities,
 * "Select dates" link-button, and a clean starting price.
 *
 * @package Shaped_Core
 * @var WP_Post $room_type The room type post object
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!isset($room_type) || !($room_type instanceof WP_Post)) {
    return;
}

$room_id        = $room_type->ID;
$room_title     = get_the_title($room_id);
$room_permalink = get_permalink($room_id);
$room_thumbnail = get_the_post_thumbnail_url($room_id, 'large');

if (!function_exists('MPHB')) {
    return;
}

// Pricing (clean, no discount display)
$base_price = shaped_get_room_base_price($room_id);
$currency   = function_exists('MPHB')
    ? MPHB()->settings()->currency()->getCurrencySymbol()
    : '€';

// Format price: whole number = no decimals
$price_display = floor($base_price) == $base_price
    ? number_format($base_price, 0, ',', '.')
    : number_format($base_price, 1, ',', '.');

// Landing amenities (top 3 from priority list)
$amenities = shaped_get_landing_amenities($room_id, 3);
?>

<div class="shaped-landing-card" id="<?php echo esc_attr(sanitize_title($room_title)); ?>">

    <?php if ($room_thumbnail): ?>
    <a href="<?php echo esc_url($room_permalink); ?>" class="shaped-landing-card__image-link">
        <img loading="lazy"
             decoding="async"
             src="<?php echo esc_url($room_thumbnail); ?>"
             class="shaped-landing-card__image"
             alt="<?php echo esc_attr($room_title); ?>">
    </a>
    <?php endif; ?>

    <div class="shaped-landing-card__body">

        <h3 class="shaped-landing-card__title">
            <?php echo esc_html($room_title); ?>
        </h3>

        <?php if (!empty($amenities)): ?>
        <ul class="shaped-landing-card__amenities">
            <?php foreach ($amenities as $amenity): ?>
            <li class="shaped-landing-card__amenity">
                <span class="shaped-landing-card__amenity-icon"><?php echo $amenity['html']; ?></span>
                <span class="shaped-landing-card__amenity-label"><?php echo esc_html($amenity['label']); ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <button type="button" class="shaped-landing-card__select-dates" data-open-datepick>
            Select dates
        </button>

        <?php if ($base_price > 0): ?>
        <p class="shaped-landing-card__price">
            From <?php echo esc_html($currency . $price_display); ?>
            <span class="shaped-landing-card__price-note">(lowest nightly, varies by dates)</span>
        </p>
        <?php endif; ?>

    </div>

</div>
