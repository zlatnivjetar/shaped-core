<?php
/**
 * Room Card Template - Search Results
 *
 * Hybrid template that combines:
 * - Card body from shaped_room_cards listing style (image, title, excerpt, full amenities)
 * - Card footer from MPHB search results (date-aware pricing, discount/urgency badges, rates indicator, CTA)
 *
 * Uses date-aware pricing when search context (check_in/check_out) is available,
 * falls back to per-night base pricing otherwise.
 *
 * @package Shaped_Core
 * @var WP_Post $room_type      The room type post object (from shortcode loop)
 * @var array   $search_context  Search parameters: check_in, check_out, adults, children (from shortcode)
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!isset($room_type) || !($room_type instanceof WP_Post)) {
    return;
}

if (!function_exists('MPHB')) {
    return;
}

// ─── Room Data ───
$room_id        = $room_type->ID;
$room_title     = get_the_title($room_id);
$room_permalink = get_permalink($room_id);
$room_excerpt   = get_the_excerpt($room_id);
$room_thumbnail = get_the_post_thumbnail_url($room_id, 'large');
$room_slug      = sanitize_title($room_title);

// ─── MPHB Room Object ───
$mphb_room = MPHB()->getRoomTypeRepository()->findById($room_id);
$size      = $mphb_room ? $mphb_room->getSize() : '';
$bed_type  = $mphb_room ? $mphb_room->getBedType() : '';

// ─── Facilities ───
$facilities = get_the_terms($room_id, 'mphb_room_type_facility');

// ─── Search Context (passed from shortcode) ───
$check_in  = $search_context['check_in'] ?? '';
$check_out = $search_context['check_out'] ?? '';
$adults    = $search_context['adults'] ?? 2;
$children  = $search_context['children'] ?? 0;
$has_dates = !empty($check_in) && !empty($check_out);

// ─── Pricing ───
$search_pricing = shaped_get_room_search_pricing(
    $room_id,
    $room_slug,
    $check_in,
    $check_out,
    $adults,
    $children
);

// ─── Build CTA URL (room page with date params for checkout flow) ───
$cta_url = $room_permalink;
if ($has_dates) {
    $cta_url = add_query_arg([
        'mphb_check_in_date'  => $check_in,
        'mphb_check_out_date' => $check_out,
        'mphb_adults'         => $adults,
        'mphb_children'       => $children,
    ], $cta_url);
}

// ─── Wrapper Classes (compatible with existing CSS and checkout.js selectors) ───
$wrapper_classes = [
    'mphb-room-type',
    'post-' . $room_id,
    'mphb_room_type',
    'type-mphb_room_type',
    'status-publish',
];
if (!empty($facilities) && !is_wp_error($facilities)) {
    $wrapper_classes[] = 'has-post-thumbnail';
    foreach ($facilities as $facility) {
        $wrapper_classes[] = 'mphb_room_type_facility-' . $facility->slug;
    }
}
?>

<div id="<?php echo esc_attr($room_slug); ?>"
     class="<?php echo esc_attr(implode(' ', $wrapper_classes)); ?>"
     data-room-type-id="<?php echo esc_attr($room_id); ?>">

    <?php // ─── Image ─── ?>
    <?php if ($room_thumbnail): ?>
    <p class="post-thumbnail mphb-loop-room-thumbnail">
        <img decoding="async"
             src="<?php echo esc_url($room_thumbnail); ?>"
             class="attachment-post-thumbnail size-post-thumbnail wp-post-image"
             alt="<?php echo esc_attr($room_title); ?>">
    </p>
    <?php endif; ?>

    <?php // ─── Title ─── ?>
    <h3 class="mphb-room-type-title entry-title">
        <?php echo esc_html($room_title); ?>
    </h3>

    <?php // ─── Description ─── ?>
    <?php if ($room_excerpt): ?>
    <p><?php echo esc_html($room_excerpt); ?></p>
    <?php endif; ?>

    <?php // ─── Amenities ─── ?>
    <?php if (!empty($facilities) && !is_wp_error($facilities)): ?>
    <h3 class="mphb-room-type-details-title">Amenities</h3>
    <ul class="mphb-loop-room-type-attributes">
        <?php // Hidden capacity element for urgency detection by checkout.js ?>
        <li class="mphb-room-type-total-capacity" style="display:none;">
            <span class="mphb-attribute-title mphb-total-capacity-title">Guests:</span>
            <span class="mphb-attribute-value"><?php echo esc_html($mphb_room ? $mphb_room->getTotalCapacity() : ''); ?></span>
        </li>

        <div class="mphb-room-amenities-wrapper">
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

        <?php // Legacy hidden attributes for checkout.js compatibility ?>
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

    <?php // ─── Price Section (date-aware) ─── ?>
    <?php shaped_render_search_price($room_id, $room_slug, $search_pricing); ?>

    <?php // ─── Rates Indicator ─── ?>
    <?php shaped_render_rates_indicator($room_id); ?>

    <?php // ─── CTA Button ─── ?>
    <div class="mphb-reserve-room-section"
         data-room-type-id="<?php echo esc_attr($room_id); ?>"
         data-room-type-title="<?php echo esc_attr($room_title); ?>"
         data-room-price="<?php echo esc_attr($search_pricing['total']); ?>">
        <a href="<?php echo esc_url($cta_url); ?>">
            <button class="button mphb-button mphb-book-button">Secure Your Stay</button>
        </a>
    </div>

</div>
