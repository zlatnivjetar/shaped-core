<?php
/**
 * Room Card Template - Home Page
 *
 * Clean template for displaying room cards on the homepage.
 * Uses Shaped amenity registry for icons (Phosphor Icons).
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
?>

<div id="<?php echo esc_attr(sanitize_title($room_title)); ?>" class="mphb-room-type post-<?php echo esc_attr($room_id); ?> mphb_room_type type-mphb_room_type">

    <?php if ($room_thumbnail): ?>
    <p class="post-thumbnail mphb-loop-room-thumbnail">
        <a href="<?php echo esc_url($room_permalink); ?>">
            <img loading="lazy" decoding="async" src="<?php echo esc_url($room_thumbnail); ?>"
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
    $amenities = shaped_get_amenities_for_room($room_id, ['skip_fallback' => true]);
    $amenities = array_slice($amenities, 0, 8);
    ?>
    <div class="mphb-room-amenities-wrapper">
        <ul class="mphb-room-amenities-list">

            <?php foreach ($amenities as $amenity): ?>
            <li class="mphb-amenity-item">
                <span class="mphb-amenity-icon"><?php echo $amenity['html']; ?></span>
                <span class="mphb-amenity-text"><?php echo esc_html($amenity['label']); ?></span>
            </li>
            <?php endforeach; ?>

        </ul>
    </div>

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
