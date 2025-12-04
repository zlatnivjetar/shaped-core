<?php
/**
 * Amenities Display Example
 *
 * This template demonstrates how to use the Shaped amenity icon system.
 * Replace the hardcoded icon arrays in your MotoPress template with this code.
 *
 * To use in your theme:
 * Copy this code to your theme's MotoPress template file (e.g., facilities.php or loop-room-type-attributes.php)
 *
 * Available variables:
 * - WP_Term[] $facilities
 * - MPHB_Room_Type $roomType
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

    // Start amenities wrapper
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

    // ─── Facilities (using Shaped amenity mapper) ───

    // Get icon data for all facilities, automatically sorted by priority
    $amenities = [];
    $displayed_labels = []; // Track displayed labels to avoid duplicates

    foreach ($facilities as $facility) {
        $icon_data = shaped_get_amenity_icon($facility);

        if ($icon_data) {
            $label = $icon_data['label'];

            // Avoid duplicates (e.g., multiple TV-related facilities)
            if (!isset($displayed_labels[$label])) {
                $amenities[] = $icon_data;
                $displayed_labels[$label] = true;
            }
        }
    }

    // Sort by priority (lower number = higher priority)
    usort($amenities, function($a, $b) {
        return $a['priority'] - $b['priority'];
    });

    // Display amenities
    foreach ($amenities as $amenity) {
        echo '<li class="mphb-amenity-item">';
        echo '<span class="mphb-amenity-icon">' . $amenity['html'] . '</span>';
        echo '<span class="mphb-amenity-text">' . esc_html($amenity['label']) . '</span>';
        echo '</li>';
    }

    echo '</ul>';
    echo '</div>';
    ?>
<?php endif; ?>

<?php
/**
 * Alternative Approach: Use the helper function directly
 *
 * If you prefer a more concise approach, you can use the shaped_render_amenity_badge() function:
 */
?>
<!-- Alternative approach (commented out):
<div class="mphb-room-amenities-wrapper">
    <ul class="mphb-room-amenities-list">
        <?php
        // Size
        $size = $roomType->getSize();
        if (!empty($size)) {
            echo '<li class="mphb-amenity-item">';
            echo '<span class="mphb-amenity-icon"><i class="ph ph-ruler"></i></span>';
            echo '<span class="mphb-amenity-text">' . esc_html($size) . 'm²</span>';
            echo '</li>';
        }

        // Bed Type
        $bedType = $roomType->getBedType();
        if (!empty($bedType)) {
            echo '<li class="mphb-amenity-item">';
            echo '<span class="mphb-amenity-icon"><i class="ph ph-bed"></i></span>';
            echo '<span class="mphb-amenity-text">' . esc_html($bedType) . '</span>';
            echo '</li>';
        }

        // Facilities - Simple loop
        foreach ($facilities as $facility) {
            echo shaped_render_amenity_badge($facility);
        }
        ?>
    </ul>
</div>
-->
