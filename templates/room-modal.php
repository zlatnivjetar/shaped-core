<?php
/**
 * Room Modal Template
 *
 * Displays room gallery slider and full description in a modal.
 * Loaded via AJAX when user clicks on room card in landing page.
 *
 * @package Shaped_Core
 * @var int $room_id The room type ID
 * @var array $dates Optional check-in/check-out dates
 */

if (!defined('ABSPATH')) {
    exit;
}

// Validate room ID
if (!isset($room_id) || !$room_id) {
    echo '<p class="error">Room not found.</p>';
    return;
}

// Get room post
$room = get_post($room_id);
if (!$room || $room->post_type !== 'mphb_room_type') {
    echo '<p class="error">Invalid room type.</p>';
    return;
}

// Get room data
$room_title = get_the_title($room_id);
$room_description = apply_filters('the_content', $room->post_content);
$featured_image_id = get_post_thumbnail_id($room_id);

// Get gallery images (MPHB stores these in mphb_gallery meta)
$gallery_ids = get_post_meta($room_id, 'mphb_gallery', true);
$gallery_array = [];

// Parse gallery IDs (can be comma-separated string or array)
if (!empty($gallery_ids)) {
    if (is_string($gallery_ids)) {
        $gallery_array = array_filter(array_map('intval', explode(',', $gallery_ids)));
    } elseif (is_array($gallery_ids)) {
        $gallery_array = array_filter(array_map('intval', $gallery_ids));
    }
}

// Build complete image list: featured image first, then gallery
$all_images = [];

if ($featured_image_id) {
    $all_images[] = $featured_image_id;
}

foreach ($gallery_array as $img_id) {
    if ($img_id && $img_id !== $featured_image_id) {
        $all_images[] = $img_id;
    }
}

// Get checkout URL with dates if provided
$checkout_url = home_url('/checkout/');
$url_params = ['mphb_room_type_id' => $room_id];

if (!empty($dates['check_in'])) {
    $url_params['mphb_check_in_date'] = $dates['check_in'];
}
if (!empty($dates['check_out'])) {
    $url_params['mphb_check_out_date'] = $dates['check_out'];
}
if (!empty($dates['adults'])) {
    $url_params['mphb_adults'] = $dates['adults'];
}

$checkout_url = add_query_arg($url_params, $checkout_url);
?>

<div class="shaped-room-modal">
    <?php if (!empty($all_images)): ?>
    <!-- Gallery Slider -->
    <div class="shaped-room-gallery">
        <div class="shaped-gallery-slider" data-current="0">
            <div class="shaped-gallery-track">
                <?php foreach ($all_images as $index => $image_id):
                    $image_url = wp_get_attachment_image_url($image_id, 'large');
                    $image_alt = get_post_meta($image_id, '_wp_attachment_image_alt', true) ?: $room_title;
                    if (!$image_url) continue;
                ?>
                <div class="shaped-gallery-slide <?php echo $index === 0 ? 'is-active' : ''; ?>" data-index="<?php echo esc_attr($index); ?>">
                    <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($image_alt); ?>" loading="<?php echo $index === 0 ? 'eager' : 'lazy'; ?>">
                </div>
                <?php endforeach; ?>
            </div>

            <?php if (count($all_images) > 1): ?>
            <!-- Navigation Arrows -->
            <button class="shaped-gallery-nav shaped-gallery-prev" aria-label="Previous image">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
            </button>
            <button class="shaped-gallery-nav shaped-gallery-next" aria-label="Next image">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </button>

            <!-- Dots Navigation -->
            <div class="shaped-gallery-dots">
                <?php foreach ($all_images as $index => $image_id): ?>
                <button class="shaped-gallery-dot <?php echo $index === 0 ? 'is-active' : ''; ?>"
                        data-index="<?php echo esc_attr($index); ?>"
                        aria-label="Go to image <?php echo $index + 1; ?>">
                </button>
                <?php endforeach; ?>
            </div>

            <!-- Counter -->
            <div class="shaped-gallery-counter">
                <span class="shaped-gallery-current">1</span> / <span class="shaped-gallery-total"><?php echo count($all_images); ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Room Description -->
    <div class="shaped-room-modal-content">
        <?php if ($room_description): ?>
        <div class="shaped-room-description">
            <?php echo $room_description; ?>
        </div>
        <?php else: ?>
        <p class="shaped-room-no-description">
            <?php esc_html_e('Contact us for more information about this room.', 'shaped'); ?>
        </p>
        <?php endif; ?>
    </div>

    <!-- Book Button (sticky at bottom) -->
    <div class="shaped-room-modal-footer">
        <a href="<?php echo esc_url($checkout_url); ?>" class="shaped-room-book-btn">
            <?php esc_html_e('Book This Room', 'shaped'); ?>
        </a>
    </div>
</div>
