<?php
/**
 * Room Card Template - Landing Page
 *
 * Room card that opens a modal instead of navigating to room page.
 * Used on conversion-optimized landing pages.
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

// Build wrapper classes
$wrapper_classes = [
    'shaped-landing-room-card',
    'mphb-room-type',
    'post-' . $room_id,
];
?>

<div class="<?php echo esc_attr(implode(' ', $wrapper_classes)); ?>"
     data-room-id="<?php echo esc_attr($room_id); ?>"
     data-room-title="<?php echo esc_attr($room_title); ?>">

    <?php if ($room_thumbnail): ?>
    <div class="shaped-landing-room-image">
        <a href="#" class="shaped-room-modal-trigger" data-room-id="<?php echo esc_attr($room_id); ?>">
            <img src="<?php echo esc_url($room_thumbnail); ?>"
                 alt="<?php echo esc_attr($room_title); ?>"
                 loading="lazy">
        </a>
    </div>
    <?php endif; ?>

    <div class="shaped-landing-room-content">
        <h3 class="shaped-landing-room-title">
            <a href="#" class="shaped-room-modal-trigger" data-room-id="<?php echo esc_attr($room_id); ?>">
                <?php echo esc_html($room_title); ?>
            </a>
        </h3>

        <?php if ($room_excerpt): ?>
        <p class="shaped-landing-room-excerpt"><?php echo esc_html($room_excerpt); ?></p>
        <?php endif; ?>

        <?php if (!empty($facilities) && !is_wp_error($facilities)): ?>
        <div class="shaped-landing-room-amenities">
            <ul class="mphb-room-amenities-list">
                <?php if (!empty($size)): ?>
                <li class="mphb-amenity-item">
                    <span class="mphb-amenity-icon"><i class="ph ph-ruler" aria-hidden="true"></i></span>
                    <span class="mphb-amenity-text"><?php echo esc_html($size); ?>m²</span>
                </li>
                <?php endif; ?>

                <?php if (!empty($bed_type)): ?>
                <li class="mphb-amenity-item">
                    <span class="mphb-amenity-icon"><i class="ph ph-bed" aria-hidden="true"></i></span>
                    <span class="mphb-amenity-text"><?php echo esc_html($bed_type); ?></span>
                </li>
                <?php endif; ?>

                <?php
                // Get amenities using Shaped system (limited for card display)
                $amenities = shaped_get_amenities_for_room($room_id, ['limit' => 6, 'skip_fallback' => true]);
                foreach ($amenities as $amenity):
                ?>
                <li class="mphb-amenity-item">
                    <span class="mphb-amenity-icon"><?php echo $amenity['html']; ?></span>
                    <span class="mphb-amenity-text"><?php echo esc_html($amenity['label']); ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>

    <div class="shaped-landing-room-footer">
        <?php shaped_render_room_price($room_id, $room_slug); ?>

        <div class="shaped-landing-room-actions">
            <button type="button"
                    class="shaped-room-modal-trigger shaped-btn shaped-btn-secondary"
                    data-room-id="<?php echo esc_attr($room_id); ?>">
                <?php esc_html_e('View Details', 'shaped'); ?>
            </button>

            <a href="<?php echo esc_url(add_query_arg('mphb_room_type_id', $room_id, home_url('/checkout/'))); ?>"
               class="shaped-btn shaped-btn-primary shaped-book-now-btn"
               data-room-id="<?php echo esc_attr($room_id); ?>">
                <?php esc_html_e('Book Now', 'shaped'); ?>
            </a>
        </div>
    </div>
</div>
