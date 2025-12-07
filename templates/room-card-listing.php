<?php
/**
 * Room Card Template - Listing/Rooms Page
 *
 * Clean template for displaying room cards on the rooms listing page.
 * Uses Shaped amenity registry for icons (Phosphor Icons).
 * Shows extended amenity list compared to homepage version.
 *
 * @package Shaped_Core
 * @var WP_Post $room_type The room type post object
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get room type data
$room_id = $room_type->ID;
$room_title = get_the_title($room_id);
$room_permalink = get_permalink($room_id);
$room_excerpt = get_the_excerpt($room_id);
$room_thumbnail = get_the_post_thumbnail_url($room_id, 'large');

// Get MotoPress room type object
if (class_exists('MPHB') && function_exists('MPHB')) {
    $mphb_room = MPHB()->getRoomTypeRepository()->findById($room_id);
} else {
    return; // MotoPress not available
}

// Get room attributes
$size = $mphb_room ? $mphb_room->getSize() : '';
$bed_type = $mphb_room ? $mphb_room->getBedType() : '';

// Get facilities for amenity display
$facilities = get_the_terms($room_id, 'mphb_room_type_facility');

// Get pricing - you can customize this based on your pricing logic
$base_price = get_post_meta($room_id, '_mphb_base_price', true) ?: '160';
$discount_percentage = 15; // You can make this dynamic
$discount_price = round($base_price * (1 - $discount_percentage / 100));

// Build comprehensive class list for the wrapper
$wrapper_classes = [
    'mphb-room-type',
    'post-' . $room_id,
    'mphb_room_type',
    'type-mphb_room_type',
    'status-publish',
];

// Add facility classes if needed (for filtering/styling)
if (!empty($facilities) && !is_wp_error($facilities)) {
    $wrapper_classes[] = 'has-post-thumbnail';
    foreach ($facilities as $facility) {
        $wrapper_classes[] = 'mphb_room_type_facility-' . $facility->slug;
    }
}
?>

<div id="<?php echo esc_attr(sanitize_title($room_title)); ?>"
     class="<?php echo esc_attr(implode(' ', $wrapper_classes)); ?>">

    <?php if ($room_thumbnail): ?>
    <p class="post-thumbnail mphb-loop-room-thumbnail">
        <a href="<?php echo esc_url($room_permalink); ?>">
            <img decoding="async" src="<?php echo esc_url($room_thumbnail); ?>"
                 class="attachment-post-thumbnail size-post-thumbnail wp-post-image"
                 alt="<?php echo esc_attr($room_title); ?>">
        </a>
    </p>
    <?php endif; ?>

    <h2 class="mphb-room-type-title entry-title">
        <a class="mphb-room-type-title" href="<?php echo esc_url($room_permalink); ?>">
            <?php echo esc_html($room_title); ?>
        </a>
    </h2>

    <?php if ($room_excerpt): ?>
    <p><?php echo esc_html($room_excerpt); ?></p>
    <?php endif; ?>

    <?php if (!empty($facilities) && !is_wp_error($facilities)): ?>
    <h3 class="mphb-room-type-details-title">Amenities</h3>
    <ul class="mphb-loop-room-type-attributes">
        <li class="mphb-room-type-total-capacity" style="display:none;">
            <span class="mphb-attribute-title mphb-total-capacity-title">Guests:</span>
            <span class="mphb-attribute-value">6</span>
        </li>

        <div class="mphb-room-amenities-wrapper">
            <ul class="mphb-room-amenities-list">

                <?php
                // Add Size
                if (!empty($size)):
                ?>
                <li class="mphb-amenity-item">
                    <span class="mphb-amenity-icon"><i class="ph ph-ruler" aria-hidden="true"></i></span>
                    <span class="mphb-amenity-text"><?php echo esc_html($size); ?>m²</span>
                </li>
                <?php endif; ?>

                <?php
                // Add Bed Type
                if (!empty($bed_type)):
                ?>
                <li class="mphb-amenity-item">
                    <span class="mphb-amenity-icon"><i class="ph ph-bed" aria-hidden="true"></i></span>
                    <span class="mphb-amenity-text"><?php echo esc_html($bed_type); ?></span>
                </li>
                <?php endif; ?>

                <?php
                // Get amenities using Shaped system (all amenities for listing page)
                $amenities = shaped_get_amenities_for_room($room_id, ['skip_fallback' => true]);

                foreach ($amenities as $amenity):
                ?>
                <li class="mphb-amenity-item">
                    <span class="mphb-amenity-icon"><?php echo $amenity['html']; ?></span>
                    <span class="mphb-amenity-text"><?php echo esc_html($amenity['label']); ?></span>
                </li>
                <?php endforeach; ?>

            </ul>
        </div>

        <p class="mphb-view-details-button-wrapper"></p>

        <!-- Legacy attributes for compatibility -->
        <li class="mphb-room-type-size" style="display:none;">
            <span class="mphb-attribute-title mphb-size-title">Size:</span>
            <span class="mphb-attribute-value"><?php echo esc_html($size); ?>m²</span>
        </li>

        <li class="mphb-room-type-bed-type" style="display:none;">
            <span class="mphb-attribute-title mphb-bed-type-title">Bed Type:</span>
            <span class="mphb-attribute-value"><?php echo esc_html($bed_type); ?></span>
        </li>
    </ul>
    <?php endif; ?>

    <div class="mphb-regular-price" style="margin-bottom:0">
        <strong>Prices start at:</strong>
        <div class="mphb-price-discount-wrapper">
            <span class="mphb-price-original">
                <span class="mphb-currency">€</span><?php echo esc_html($base_price); ?>
            </span>
            <span class="mphb-price mphb-price-current">
                <span class="mphb-currency">€</span><?php echo esc_html($discount_price); ?>
            </span>
            <span class="mphb-price-period">per night</span>
            <span class="mphb-discount-badge"><?php echo esc_html($discount_percentage); ?>% off</span>
        </div>
    </div>

    <div class="mphb-reserve-room-section"
         data-room-type-id="<?php echo esc_attr($room_id); ?>"
         data-room-type-title="<?php echo esc_attr($room_title); ?>"
         data-room-price="<?php echo esc_attr($base_price); ?>">
        <a href="<?php echo esc_url($room_permalink); ?>">
            <button class="button mphb-button mphb-book-button">View Room Details</button>
        </a>
    </div>

</div>
