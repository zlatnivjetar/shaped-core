<?php
/**
 * Room Card Template - Listing/Archive Page
 * Displays room cards on archive/listing pages with extended information
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
$room_content = apply_filters('the_content', get_the_content(null, false, $room_id));
$room_thumbnail = get_the_post_thumbnail($room_id, 'large', ['class' => 'room-card-image']);

// Get room meta
$capacity = get_post_meta($room_id, 'mphb_capacity', true);
$size = get_post_meta($room_id, 'mphb_size', true);
$view = get_post_meta($room_id, 'mphb_view', true);
$bed_type = get_post_meta($room_id, 'mphb_bed_type', true);

// Get amenities
$amenities = wp_get_post_terms($room_id, 'mphb_room_type_facility', ['fields' => 'names']);

// Get base price
$base_price_display = apply_filters('shaped/room_card/base_price_display', 'from €XX', $room_id);
?>

<article class="shaped-room-card shaped-room-card-listing" data-room-id="<?php echo esc_attr($room_id); ?>">

    <div class="room-card-layout">

        <?php if ($room_thumbnail): ?>
        <div class="room-card-image-wrapper">
            <a href="<?php echo esc_url($room_permalink); ?>">
                <?php echo $room_thumbnail; ?>
            </a>
        </div>
        <?php endif; ?>

        <div class="room-card-content">

            <header class="room-card-header">
                <h2 class="room-card-title">
                    <a href="<?php echo esc_url($room_permalink); ?>">
                        <?php echo esc_html($room_title); ?>
                    </a>
                </h2>

                <div class="room-card-meta">
                    <?php if ($capacity): ?>
                    <span class="room-meta-item room-capacity">
                        <i class="dashicons dashicons-groups"></i>
                        <?php echo esc_html(sprintf(__('%d guests', 'shaped'), $capacity)); ?>
                    </span>
                    <?php endif; ?>

                    <?php if ($size): ?>
                    <span class="room-meta-item room-size">
                        <i class="dashicons dashicons-admin-home"></i>
                        <?php echo esc_html($size); ?>
                    </span>
                    <?php endif; ?>

                    <?php if ($bed_type): ?>
                    <span class="room-meta-item room-bed">
                        <i class="dashicons dashicons-admin-multisite"></i>
                        <?php echo esc_html($bed_type); ?>
                    </span>
                    <?php endif; ?>

                    <?php if ($view): ?>
                    <span class="room-meta-item room-view">
                        <i class="dashicons dashicons-visibility"></i>
                        <?php echo esc_html($view); ?>
                    </span>
                    <?php endif; ?>
                </div>
            </header>

            <div class="room-card-description">
                <?php echo wp_kses_post(wp_trim_words($room_content, 50, '...')); ?>
            </div>

            <?php if (!empty($amenities)): ?>
            <div class="room-card-amenities">
                <strong><?php _e('Amenities:', 'shaped'); ?></strong>
                <ul class="amenities-list">
                    <?php foreach (array_slice($amenities, 0, 5) as $amenity): ?>
                    <li><?php echo esc_html($amenity); ?></li>
                    <?php endforeach; ?>
                    <?php if (count($amenities) > 5): ?>
                    <li class="amenities-more">
                        <?php echo esc_html(sprintf(__('+%d more', 'shaped'), count($amenities) - 5)); ?>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
            <?php endif; ?>

            <footer class="room-card-footer">
                <div class="room-card-price" data-room-slug="<?php echo esc_attr(sanitize_title($room_title)); ?>">
                    <span class="price-label"><?php _e('From', 'shaped'); ?></span>
                    <span class="price-amount"><?php echo esc_html($base_price_display); ?></span>
                    <span class="price-period"><?php _e('per night', 'shaped'); ?></span>
                </div>

                <a href="<?php echo esc_url($room_permalink); ?>" class="room-card-cta button button-primary">
                    <?php _e('Check Availability', 'shaped'); ?>
                </a>
            </footer>

        </div>

    </div>

</article>
