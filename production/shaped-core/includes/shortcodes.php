<?php
/**
 * Custom Shortcodes for Shaped Core
 * 
 * @package ShapedCore
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Room Details Shortcode
 * Displays room description with proper formatting
 * 
 * Usage: [shaped_room_details] or [shaped_room_details id="123"]
 * 
 * @param array $atts Shortcode attributes
 * @return string Room details HTML
 */
function shaped_room_details_shortcode($atts) {
    // Get current room ID dynamically if not provided
    $room_id = isset($atts['id']) ? intval($atts['id']) : get_the_ID();
    
    // Try to get from query var for single room pages
    if (!$room_id) {
        $room_id = get_query_var('mphb_room_type_id');
    }
    
    if (!$room_id) {
        return '';
    }
    
    // Get the room type post
    $room = get_post($room_id);
    if (!$room || $room->post_type !== 'mphb_room_type') {
        return '';
    }
    
    // Start output
    $output = '<div class="shaped-room-details">';
    
    // Get raw description without triggering other filters
    $description = $room->post_content;
    if ($description) {
        $output .= '<div class="room-description">';
        
        // Apply only basic formatting filters
        $description = wpautop($description);
        $description = wptexturize($description);
        $description = convert_smilies($description);
        
        $output .= $description;
        $output .= '</div>';
    }
    
    $output .= '</div>';
    
    return $output;
}
add_shortcode('shaped_room_details', 'shaped_room_details_shortcode');