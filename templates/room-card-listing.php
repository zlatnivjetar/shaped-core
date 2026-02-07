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

// Validate that we have a room type object
if (!isset($room_type) || !($room_type instanceof WP_Post)) {
    return;
}

// Get room type data
$room_id = $room_type->ID;
$room_title = get_the_title($room_id);
$room_permalink = get_permalink($room_id);
$room_excerpt = get_the_excerpt($room_id);
$room_thumbnail = get_the_post_thumbnail_url($room_id, 'large');

// Get MotoPress room type object
if (!function_exists('MPHB')) {
    return;
}

$mphb_room = MPHB()->getRoomTypeRepository()->findById($room_id);

// Get room attributes
$size = $mphb_room ? $mphb_room->getSize() : '';
$bed_type = $mphb_room ? $mphb_room->getBedType() : '';

// Get facilities for amenity display
$facilities = get_the_terms($room_id, 'mphb_room_type_facility');

// Get room slug for discount lookup
$room_slug = sanitize_title($room_title);

// Get pricing data
$pricing = shaped_get_room_pricing_data($room_id, $room_slug);

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

    <h3 class="mphb-room-type-title entry-title">
        <a class="mphb-room-type-title" href="<?php echo esc_url($room_permalink); ?>">
            <?php echo esc_html($room_title); ?>
        </a>
    </h3>

    <?php if ($room_excerpt): ?>
    <p><?php echo esc_html($room_excerpt); ?></p>
    <?php endif; ?>

    <?php
    $total_capacity = $mphb_room ? $mphb_room->getTotalCapacity() : 0;
    $amenities = shaped_get_amenities_for_room($room_id, ['skip_fallback' => true]);
    $amenities = array_slice($amenities, 0, 7); // 7 mapper + Sleeps = 8 total
    ?>
    <ul class="mphb-loop-room-type-attributes">
        <li class="mphb-room-type-total-capacity" style="display:none;">
            <span class="mphb-attribute-title mphb-total-capacity-title">Guests:</span>
            <span class="mphb-attribute-value"><?php echo esc_html($total_capacity); ?></span>
        </li>

        <div class="mphb-room-amenities-wrapper">
            <ul class="mphb-room-amenities-list">

                <?php if ($total_capacity > 0): ?>
                <li class="mphb-amenity-item">
                    <span class="mphb-amenity-icon"><i class="ph ph-bed" aria-hidden="true"></i></span>
                    <span class="mphb-amenity-text">Sleeps <?php echo esc_html($total_capacity); ?></span>
                </li>
                <?php endif; ?>

                <?php foreach ($amenities as $amenity): ?>
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

    <?php shaped_render_room_price($room_id, $room_slug); ?>

    <div class="mphb-reserve-room-section"
         data-room-type-id="<?php echo esc_attr($room_id); ?>"
         data-room-type-title="<?php echo esc_attr($room_title); ?>"
         data-room-price="<?php echo esc_attr($pricing['base_price']); ?>">
        <a href="<?php echo esc_url($room_permalink); ?>">
            <button class="button mphb-button mphb-book-button">View Room Details</button>
        </a>
    </div>

</div>
