<?php
/**
 * Room Modal Content Template
 *
 * Rendered server-side as a hidden <template> inside each search card.
 * ShapedRoomModal JS clones this into the modal overlay on card click.
 *
 * Layout (desktop): gallery left (60%), name/description right (40%).
 * Amenities full-width below. Sticky footer with pricing + CTA.
 *
 * @package Shaped_Core
 * @var int    $room_id         Room type post ID
 * @var string $room_title      Room title
 * @var string $room_slug       Sanitized room slug
 * @var object $mphb_room       MPHB RoomType entity
 * @var string $size             Room size (e.g. "55")
 * @var string $bed_type         Bed type description
 * @var array  $search_pricing   Pricing data from shaped_get_room_search_pricing()
 * @var string $checkout_url     MPHB checkout page URL (POST target)
 * @var string $check_in         Check-in date (Y-m-d)
 * @var string $check_out        Check-out date (Y-m-d)
 * @var array  $search_context   Search parameters (check_in, check_out, adults, children)
 */

if (!defined('ABSPATH')) {
    exit;
}

// ─── Gallery Images ───
$gallery = shaped_get_room_gallery($room_id);
$gallery_count = count($gallery);

// ─── Full Description ───
$description = get_post_field('post_content', $room_id);
if ($description) {
    $description = wpautop($description);
    $description = wptexturize($description);
    $description = convert_smilies($description);
}

// ─── All Amenities (flat grid) ───
$all_amenities = shaped_get_amenities_for_room($room_id, ['skip_fallback' => true]);

// ─── Currency ───
$currency = function_exists('MPHB')
    ? MPHB()->settings()->currency()->getCurrencySymbol()
    : '€';
?>

<div class="shaped-room-modal" data-room-id="<?php echo esc_attr($room_id); ?>">

    <?php // ─── Top Section: Gallery + Details (side-by-side on desktop) ─── ?>
    <div class="shaped-room-modal__top">

        <?php // ─── Left Column: Gallery ─── ?>
        <div class="shaped-room-modal__left">
            <?php if ($gallery_count > 0): ?>
            <div class="shaped-room-modal__gallery"
                 data-total="<?php echo esc_attr($gallery_count); ?>">
                <div class="shaped-room-modal__gallery-track">
                    <?php foreach ($gallery as $index => $image): ?>
                    <div class="shaped-room-modal__slide <?php echo $index === 0 ? 'is-active' : ''; ?>"
                         data-index="<?php echo esc_attr($index); ?>">
                        <img <?php echo $index === 0 ? '' : 'loading="lazy"'; ?>
                             decoding="async"
                             src="<?php echo esc_url($image['url']); ?>"
                             alt="<?php echo esc_attr($image['alt']); ?>"
                             class="shaped-room-modal__image">
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($gallery_count > 1): ?>
                <span class="shaped-room-modal__nav shaped-room-modal__nav--prev" role="button" tabindex="0" aria-label="Previous image">
                    <i class="ph ph-caret-left" aria-hidden="true"></i>
                </span>
                <span class="shaped-room-modal__nav shaped-room-modal__nav--next" role="button" tabindex="0" aria-label="Next image">
                    <i class="ph ph-caret-right" aria-hidden="true"></i>
                </span>
                <span class="shaped-room-modal__counter">
                    <span class="shaped-room-modal__counter-current">1</span>
                    / <?php echo esc_html($gallery_count); ?>
                </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php // ─── Right Column: Name + Description ─── ?>
        <div class="shaped-room-modal__details">
            <h2 class="shaped-room-modal__title"><?php echo esc_html($room_title); ?></h2>

            <?php if ($description): ?>
            <div class="shaped-room-modal__description">
                <?php echo $description; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php // ─── Amenities (full-width section) ─── ?>
    <?php if (!empty($all_amenities)): ?>
    <div class="shaped-room-modal__amenities">
        <h3 class="shaped-room-modal__section-title">Amenities</h3>
        <ul class="shaped-room-modal__amenities-grid">
            <?php if (!empty($size)): ?>
            <li class="shaped-room-modal__amenity">
                <span class="shaped-room-modal__amenity-icon"><i class="ph ph-ruler" aria-hidden="true"></i></span>
                <span class="shaped-room-modal__amenity-label"><?php echo esc_html($size); ?>m²</span>
            </li>
            <?php endif; ?>

            <?php if (!empty($bed_type)): ?>
            <li class="shaped-room-modal__amenity">
                <span class="shaped-room-modal__amenity-icon"><i class="ph ph-bed" aria-hidden="true"></i></span>
                <span class="shaped-room-modal__amenity-label"><?php echo esc_html($bed_type); ?></span>
            </li>
            <?php endif; ?>

            <?php foreach ($all_amenities as $amenity): ?>
            <li class="shaped-room-modal__amenity">
                <span class="shaped-room-modal__amenity-icon"><?php echo $amenity['html']; ?></span>
                <span class="shaped-room-modal__amenity-label"><?php echo esc_html($amenity['label']); ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php // ─── Pricing + CTA ─── ?>
    <div class="shaped-room-modal__footer">
        <?php shaped_render_search_price($room_id, $room_slug, $search_pricing); ?>
        <?php shaped_render_rates_indicator($room_id); ?>

        <div class="shaped-room-modal__cta">
            <form method="POST" action="<?php echo esc_url($checkout_url); ?>" class="shaped-checkout-form">
                <input type="hidden" name="mphb_check_in_date" value="<?php echo esc_attr($check_in); ?>">
                <input type="hidden" name="mphb_check_out_date" value="<?php echo esc_attr($check_out); ?>">
                <input type="hidden" name="mphb_rooms_details[<?php echo esc_attr($room_id); ?>]" value="1">
                <?php wp_nonce_field('mphb-checkout', 'mphb-checkout-nonce', false); ?>
                <button type="submit"
                        class="button mphb-button mphb-book-button shaped-room-modal__book-btn">
                    Secure Your Stay
                </button>
            </form>
        </div>
    </div>

</div>
