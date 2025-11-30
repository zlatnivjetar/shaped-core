<?php
/**
 * Room Card Template - Home Page
 * Displays room cards on the home page with pricing and booking info
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
$room_thumbnail = get_the_post_thumbnail($room_id, 'large', ['class' => 'room-card-image']);

// Get room meta
$capacity = get_post_meta($room_id, 'mphb_capacity', true);
$size = get_post_meta($room_id, 'mphb_size', true);
$view = get_post_meta($room_id, 'mphb_view', true);

// Get base price (this will be dynamically replaced by JavaScript)
$base_price_display = apply_filters('shaped/room_card/base_price_display', 'from €XX', $room_id);
?>

<div class="shaped-room-card shaped-room-card-home" data-room-id="<?php echo esc_attr($room_id); ?>">

    <?php if ($room_thumbnail): ?>
    <div class="room-card-image-wrapper">
        <a href="<?php echo esc_url($room_permalink); ?>">
            <?php echo $room_thumbnail; ?>
        </a>
    </div>
    <?php endif; ?>

    <div class="room-card-content">

        <h3 class="room-card-title">
            <a href="<?php echo esc_url($room_permalink); ?>">
                <?php echo esc_html($room_title); ?>
            </a>
        </h3>

        <?php if ($room_excerpt): ?>
        <div class="room-card-excerpt">
            <?php echo wp_kses_post($room_excerpt); ?>
        </div>
        <?php endif; ?>

        <div class="room-card-meta">
            <?php if ($capacity): ?>
            <span class="room-meta-item room-capacity">
                <i class="dashicons dashicons-groups"></i>
                <?php echo esc_html(sprintf(__('Up to %d guests', 'shaped'), $capacity)); ?>
            </span>
            <?php endif; ?>

            <?php if ($size): ?>
            <span class="room-meta-item room-size">
                <i class="dashicons dashicons-admin-home"></i>
                <?php echo esc_html($size); ?>
            </span>
            <?php endif; ?>

            <?php if ($view): ?>
            <span class="room-meta-item room-view">
                <i class="dashicons dashicons-visibility"></i>
                <?php echo esc_html($view); ?>
            </span>
            <?php endif; ?>
        </div>

        <div class="room-card-footer">
            <div class="room-card-price" data-room-slug="<?php echo esc_attr(sanitize_title($room_title)); ?>">
                <span class="price-label"><?php _e('From', 'shaped'); ?></span>
                <span class="price-amount"><?php echo esc_html($base_price_display); ?></span>
                <span class="price-period"><?php _e('per night', 'shaped'); ?></span>
            </div>

            <a href="<?php echo esc_url($room_permalink); ?>" class="room-card-cta button">
                <?php _e('View Details', 'shaped'); ?>
            </a>
        </div>

    </div>

</div>
