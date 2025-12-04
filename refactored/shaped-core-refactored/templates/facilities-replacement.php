<?php
/**
 * Facilities Template - Shaped Version
 *
 * This replaces the hardcoded icon array with the Shaped amenity icon mapper system.
 * Uses Phosphor Icons instead of Font Awesome, Material Design Icons, and SVGs.
 *
 * To use: Copy this file to your theme's MotoPress template directory as facilities.php
 * Location: your-theme/hotel-booking/loop-room-type/facilities.php
 *
 * Available variables:
 * - WP_Term[] $facilities
 *
 * @package Shaped
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<?php if (!empty($facilities)) : ?>
    <?php
    // Get current room type
    $roomType = MPHB()->getCurrentRoomType();

    // Close any open list items from the default structure
    echo '</li>';

    // Start our custom amenities structure
    echo '<div class="mphb-room-amenities-wrapper">';
    echo '<ul class="mphb-room-amenities-list">';

    // ─── Size ───
    $size = $roomType->getSize();
    if (!empty($size)) {
        echo '<li class="mphb-amenity-item">';
        echo '<span class="mphb-amenity-icon"><i class="ph ph-ruler" aria-hidden="true"></i></span>';
        echo '<span class="mphb-amenity-text">' . esc_html($size) . 'm²</span>';
        echo '</li>';
    }

    // ─── Bed Type ───
    $bedType = $roomType->getBedType();
    if (!empty($bedType)) {
        echo '<li class="mphb-amenity-item">';
        echo '<span class="mphb-amenity-icon"><i class="ph ph-bed" aria-hidden="true"></i></span>';
        echo '<span class="mphb-amenity-text">' . esc_html($bedType) . '</span>';
        echo '</li>';
    }

    // ─── Process Facilities with Shaped Icon Mapper ───
    $orderedAmenities = [];
    $displayedTypes = [];

    foreach ($facilities as $facility) {
        // Get icon data using Shaped helper function
        $icon_data = shaped_get_amenity_icon($facility);

        if ($icon_data) {
            $label = $icon_data['label'];

            // Avoid duplicates (e.g., multiple TV-related facilities)
            if (!isset($displayedTypes[$label])) {
                $orderedAmenities[] = $icon_data;
                $displayedTypes[$label] = true;
            }
        }
    }

    // Sort by priority
    usort($orderedAmenities, function($a, $b) {
        return $a['priority'] - $b['priority'];
    });

    // Display amenities in order
    foreach ($orderedAmenities as $amenity) {
        echo '<li class="mphb-amenity-item">';
        echo '<span class="mphb-amenity-icon">' . $amenity['html'] . '</span>';
        echo '<span class="mphb-amenity-text">' . esc_html($amenity['label']) . '</span>';
        echo '</li>';
    }

    echo '</ul>';
    echo '</div>';

    // Start a new list item to maintain structure
    echo '<li style="display:none;">';
    ?>
<?php endif; ?>
