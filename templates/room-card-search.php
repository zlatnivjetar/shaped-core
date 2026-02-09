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

// ─── Availability Count (for urgency badges + zero-room gating) ───
// Priority: RoomCloud (if active) → MPHB fallback
$available_count = 0;
$rc_handled = false;

if (!empty($check_in) && !empty($check_out)) {
    $check_in_dt  = new \DateTime($check_in);
    $check_out_dt = new \DateTime($check_out);

    // PRIORITY 1: RoomCloud availability (source of truth when active)
    if (shaped_is_roomcloud_active() && class_exists('Shaped_RC_Availability_Manager')) {
        $rc_id = Shaped_RC_Availability_Manager::get_roomcloud_id_for_room_type($room_id);
        if ($rc_id !== null) {
            $rc_count = Shaped_RC_Availability_Manager::get_min_availability_for_stay($rc_id, $check_in_dt, $check_out_dt);
            if ($rc_count !== null) {
                $available_count = max(0, $rc_count);
                $rc_handled = true;
            }
        }
    }

    // PRIORITY 2: MPHB fallback (when RoomCloud has no data for this room)
    if (!$rc_handled) {
        $available = MPHB()->getRoomRepository()->getAvailableRooms($check_in_dt, $check_out_dt, $room_id);
        $available_count = isset($available[$room_id]) ? count($available[$room_id]) : 0;

        $unavailable_ids = mphb_availability_facade()->getUnavailableRoomIds(
            $room_id,
            $check_in_dt,
            $check_out_dt,
            false
        );
        $available_count -= count($unavailable_ids);
        $available_count = max(0, $available_count);
    }

    // Skip rendering if zero rooms available
    if ($available_count === 0) {
        return;
    }
}

// ─── Pricing ───
$search_pricing = shaped_get_room_search_pricing(
    $room_id,
    $room_slug,
    $check_in,
    $check_out,
    $adults,
    $children
);

// ─── Checkout URL (MPHB checkout page, POST target) ───
$checkout_url = MPHB()->settings()->pages()->getCheckoutPageUrl();

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
     data-room-type-id="<?php echo esc_attr($room_id); ?>"
     data-available-rooms="<?php echo esc_attr($available_count); ?>">

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
    <?php
    $total_capacity = $mphb_room ? $mphb_room->getTotalCapacity() : 0;
    $amenities = shaped_get_amenities_for_room($room_id, ['skip_fallback' => true]);
    $amenities = array_slice($amenities, 0, 8);
    ?>
    <ul class="mphb-loop-room-type-attributes">
        <?php // Hidden capacity element for urgency detection by checkout.js ?>
        <li class="mphb-room-type-total-capacity" style="display:none;">
            <span class="mphb-attribute-title mphb-total-capacity-title">Guests:</span>
            <span class="mphb-attribute-value"><?php echo esc_html($total_capacity); ?></span>
        </li>

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

    <?php // ─── Price Section (date-aware) ─── ?>
    <?php shaped_render_search_price($room_id, $room_slug, $search_pricing); ?>

    <?php // ─── Rates Indicator ─── ?>
    <?php shaped_render_rates_indicator($room_id); ?>

    <?php // ─── CTA ─── ?>
    <div class="mphb-reserve-room-section"
         data-room-type-id="<?php echo esc_attr($room_id); ?>"
         data-room-type-title="<?php echo esc_attr($room_title); ?>"
         data-room-price="<?php echo esc_attr($search_pricing['total']); ?>">
        <?php if ($has_dates): ?>
        <form method="POST" action="<?php echo esc_url($checkout_url); ?>" class="shaped-checkout-form">
            <input type="hidden" name="mphb_check_in_date" value="<?php echo esc_attr($check_in); ?>">
            <input type="hidden" name="mphb_check_out_date" value="<?php echo esc_attr($check_out); ?>">
            <input type="hidden" name="mphb_adults" value="<?php echo esc_attr($adults); ?>">
            <input type="hidden" name="mphb_rooms_details[<?php echo esc_attr($room_id); ?>]" value="1">
            <?php wp_nonce_field('mphb-checkout', 'mphb-checkout-nonce', false); ?>
            <button type="submit" class="button mphb-button mphb-book-button">Secure Your Stay</button>
        </form>
        <?php else: ?>
        <button type="button" class="button mphb-button mphb-book-button" data-open-datepick>Check Availability</button>
        <?php endif; ?>
    </div>

    <?php // ─── Hidden Modal Content (cloned into overlay by room-modal.js on click) ─── ?>
    <template data-room-modal>
        <?php include SHAPED_DIR . 'templates/room-modal-content.php'; ?>
    </template>

</div>
