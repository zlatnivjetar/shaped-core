<?php
/**
 * Diagnostic Shortcode for MPHB Integration
 *
 * Usage: [shaped_diagnostic]
 *
 * @package ShapedCore
 */

if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('shaped_diagnostic', 'shaped_diagnostic_shortcode');

function shaped_diagnostic_shortcode($atts) {
    ob_start();
    ?>
    <div style="background: #f5f5f5; border: 2px solid #333; padding: 20px; margin: 20px 0; font-family: monospace;">
        <h3 style="margin-top: 0;">🔍 Shaped Core Diagnostic Report</h3>

        <h4>Plugin Constants</h4>
        <ul>
            <li><strong>SHAPED_DIR:</strong> <?php echo esc_html(defined('SHAPED_DIR') ? SHAPED_DIR : 'NOT DEFINED'); ?></li>
            <li><strong>SHAPED_VERSION:</strong> <?php echo esc_html(defined('SHAPED_VERSION') ? SHAPED_VERSION : 'NOT DEFINED'); ?></li>
        </ul>

        <h4>MotoPress Hotel Booking Status</h4>
        <ul>
            <li><strong>function_exists('MPHB'):</strong> <?php echo function_exists('MPHB') ? '✅ YES' : '❌ NO'; ?></li>
            <li><strong>class_exists('MPHB'):</strong> <?php echo class_exists('MPHB') ? '✅ YES' : '❌ NO'; ?></li>
            <?php if (function_exists('MPHB')): ?>
                <li><strong>MPHB() returns object:</strong> <?php echo is_object(MPHB()) ? '✅ YES' : '❌ NO'; ?></li>
                <?php if (is_object(MPHB())): ?>
                    <li><strong>MPHB class:</strong> <?php echo esc_html(get_class(MPHB())); ?></li>
                    <li><strong>Has getRoomTypeRepository():</strong> <?php echo method_exists(MPHB(), 'getRoomTypeRepository') ? '✅ YES' : '❌ NO'; ?></li>
                <?php endif; ?>
            <?php endif; ?>
        </ul>

        <h4>Room Type Posts</h4>
        <?php
        $rooms = get_posts([
            'post_type' => 'mphb_room_type',
            'post_status' => 'any',
            'posts_per_page' => -1,
        ]);
        ?>
        <ul>
            <li><strong>Total room posts:</strong> <?php echo count($rooms); ?></li>
            <li><strong>Published rooms:</strong> <?php echo count(array_filter($rooms, function($r) { return $r->post_status === 'publish'; })); ?></li>
        </ul>

        <?php if (!empty($rooms)): ?>
            <h5>Room List:</h5>
            <ul>
                <?php foreach ($rooms as $room): ?>
                    <li>ID: <?php echo $room->ID; ?> - <?php echo esc_html($room->post_title); ?> (<?php echo $room->post_status; ?>)</li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p style="background: #ffebee; padding: 10px;">⚠️ No room type posts found in database</p>
        <?php endif; ?>

        <h4>Active Plugins</h4>
        <?php
        $active_plugins = get_option('active_plugins', []);
        $mphb_found = false;
        ?>
        <ul>
            <?php foreach ($active_plugins as $plugin): ?>
                <?php if (stripos($plugin, 'hotel') !== false || stripos($plugin, 'booking') !== false || stripos($plugin, 'mphb') !== false): ?>
                    <li style="color: green;">✅ <?php echo esc_html($plugin); ?></li>
                    <?php $mphb_found = true; ?>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if (!$mphb_found): ?>
                <li style="color: red;">❌ No hotel booking plugin found in active plugins list</li>
            <?php endif; ?>
        </ul>

        <h4>Shaped Helper Functions</h4>
        <ul>
            <li><strong>shaped_get_amenities_for_room():</strong> <?php echo function_exists('shaped_get_amenities_for_room') ? '✅ Available' : '❌ Not available'; ?></li>
            <li><strong>shaped_get_amenity_icon():</strong> <?php echo function_exists('shaped_get_amenity_icon') ? '✅ Available' : '❌ Not available'; ?></li>
        </ul>

        <h4>Amenity Mapper</h4>
        <ul>
            <li><strong>Class exists:</strong> <?php echo class_exists('Shaped_Amenity_Mapper') ? '✅ YES' : '❌ NO'; ?></li>
        </ul>

        <h4>Template Files</h4>
        <ul>
            <li><strong>room-card-home.php:</strong> <?php echo file_exists(SHAPED_DIR . 'templates/room-card-home.php') ? '✅ Exists' : '❌ Not found'; ?></li>
            <li><strong>room-card-listing.php:</strong> <?php echo file_exists(SHAPED_DIR . 'templates/room-card-listing.php') ? '✅ Exists' : '❌ Not found'; ?></li>
        </ul>

        <p style="margin-top: 20px; padding: 10px; background: #e3f2fd; border-left: 4px solid #2196f3;">
            <strong>Tip:</strong> Copy this diagnostic information when reporting issues.
        </p>
    </div>
    <?php
    return ob_get_clean();
}
